<?php

// ViaGo 광고성 게시글 필터
// - AWS Comprehend 기본 API와 자체 광고 패턴 점수화를 함께 사용한다.
// - Comprehend 호출은 EC2 IAM Role 권한과 AWS CLI를 사용한다.
// - Comprehend 장애 시 글 작성 전체를 막지 않고 자체 패턴 점수만 사용한다.

function viago_moderation_config($constantName, $envName, $default = '') {
    if (defined($constantName)) {
        return constant($constantName);
    }

    if (function_exists('viago_config_value')) {
        return viago_config_value($constantName, $envName, $default);
    }

    $value = getenv($envName);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

function viago_comprehend_enabled(): bool {
    $value = strtolower((string) viago_moderation_config('VIAGO_COMPREHEND_ENABLED', 'VIAGO_COMPREHEND_ENABLED', 'true'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function viago_comprehend_region(): string {
    return (string) viago_moderation_config('VIAGO_COMPREHEND_REGION', 'VIAGO_COMPREHEND_REGION', 'ap-northeast-2');
}

function viago_ad_score_threshold(): int {
    return (int) viago_moderation_config('VIAGO_AD_SCORE_THRESHOLD', 'VIAGO_AD_SCORE_THRESHOLD', 50);
}

function viago_trim_for_comprehend(string $text, int $bytes = 4500): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (function_exists('mb_strcut')) {
        return mb_strcut($text, 0, $bytes, 'UTF-8');
    }
    return substr($text, 0, $bytes);
}

function viago_run_comprehend_cli(string $operation, array $args, string $region): array {
    $cmd = 'HOME=/tmp AWS_EC2_METADATA_DISABLED=false aws comprehend ' . escapeshellarg($operation);

    foreach ($args as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $cmd .= ' --' . $name . ' ' . escapeshellarg((string)$value);
    }

    $cmd .= ' --region ' . escapeshellarg($region) . ' --output json 2>&1';

    exec($cmd, $output, $code);
    $raw = implode("\n", $output);

    if ($code !== 0) {
        throw new RuntimeException("Comprehend {$operation} failed: " . $raw);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Comprehend {$operation} returned invalid JSON: " . $raw);
    }

    return $decoded;
}

function viago_add_reason(array &$reasons, string $reason): void {
    if (!in_array($reason, $reasons, true)) {
        $reasons[] = $reason;
    }
}

function viago_analyze_ad_risk(string $title, string $content): array {
    $text = trim($title . "\n" . $content);
    $score = 0;
    $reasons = [];
    $comprehendResult = [
        'enabled' => viago_comprehend_enabled(),
        'language' => null,
        'sentiment' => null,
        'key_phrases' => [],
        'errors' => [],
    ];

    // 1. 자체 광고/스팸 패턴 점수화
    $patterns = [
        '/https?:\/\//i' => [30, 'URL 포함'],
        '/(?:www\.)?[a-z0-9-]+\.(?:com|net|org|kr|co\.kr|io|today)\b/i' => [20, '도메인 주소 포함'],
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => [20, '이메일 주소 포함'],
        '/010[-\s]?\d{4}[-\s]?\d{4}/' => [25, '전화번호 포함'],
        '/(카카오톡|카톡|오픈채팅|오픈톡|텔레그램|라인|DM|디엠|문의|상담|예약|대행|홍보|협찬|제휴|광고)/u' => [25, '광고성 연락/홍보 키워드 포함'],
        '/(무료|특가|최저가|할인|이벤트|쿠폰|마감임박|선착순|수익|부업|대출|카지노|토토|성인)/u' => [30, '스팸성 판매/수익 키워드 포함'],
        '/(예약문의|가격문의|상담문의|구매문의)/u' => [25, '문의 유도 문구 포함'],
    ];

    foreach ($patterns as $regex => [$points, $reason]) {
        if (preg_match($regex, $text)) {
            $score += $points;
            viago_add_reason($reasons, $reason);
        }
    }

    if (function_exists('mb_strlen')) {
        $contentLength = mb_strlen($content, 'UTF-8');
    } else {
        $contentLength = strlen($content);
    }

    if ($contentLength < 30 && $score >= 30) {
        $score += 20;
        viago_add_reason($reasons, '짧은 본문과 광고성 요소 조합');
    }

    // 같은 단어/문장 반복이 심하면 광고성으로 가중치 부여
    if (preg_match('/(.{4,30})\1{2,}/u', preg_replace('/\s+/u', '', $text))) {
        $score += 15;
        viago_add_reason($reasons, '반복 문구 감지');
    }

    // 2. Amazon Comprehend 분석
    if (viago_comprehend_enabled() && $text !== '') {
        $region = viago_comprehend_region();
        $comprehendText = viago_trim_for_comprehend($text);

        try {
            $languageResult = viago_run_comprehend_cli('detect-dominant-language', [
                'text' => $comprehendText,
            ], $region);

            $languageCode = $languageResult['Languages'][0]['LanguageCode'] ?? 'ko';
            $comprehendResult['language'] = $languageCode;

            // Comprehend API가 지원하지 않는 언어 코드로 실패할 수 있으므로 실패 시 자체 패턴만 사용한다.
            try {
                $keyPhraseResult = viago_run_comprehend_cli('detect-key-phrases', [
                    'text' => $comprehendText,
                    'language-code' => $languageCode,
                ], $region);

                $phrases = [];
                foreach (($keyPhraseResult['KeyPhrases'] ?? []) as $phrase) {
                    if (!empty($phrase['Text'])) {
                        $phrases[] = $phrase['Text'];
                    }
                }
                $phrases = array_slice(array_values(array_unique($phrases)), 0, 20);
                $comprehendResult['key_phrases'] = $phrases;

                $adWords = ['할인', '예약', '문의', '이벤트', '최저가', '홍보', '협찬', '제휴', '카카오톡', '오픈채팅', '수익', '부업', '대출'];
                foreach ($phrases as $phrase) {
                    foreach ($adWords as $word) {
                        if (function_exists('mb_stripos') ? mb_stripos($phrase, $word, 0, 'UTF-8') !== false : stripos($phrase, $word) !== false) {
                            $score += 10;
                            viago_add_reason($reasons, "광고성 핵심 문구 감지: {$phrase}");
                            break;
                        }
                    }
                }
            } catch (Throwable $e) {
                $comprehendResult['errors'][] = $e->getMessage();
                error_log('[ViaGo Comprehend KeyPhrases] ' . $e->getMessage());
            }

            try {
                $sentimentResult = viago_run_comprehend_cli('detect-sentiment', [
                    'text' => $comprehendText,
                    'language-code' => $languageCode,
                ], $region);
                $comprehendResult['sentiment'] = $sentimentResult['Sentiment'] ?? null;
            } catch (Throwable $e) {
                $comprehendResult['errors'][] = $e->getMessage();
                error_log('[ViaGo Comprehend Sentiment] ' . $e->getMessage());
            }

        } catch (Throwable $e) {
            $comprehendResult['errors'][] = $e->getMessage();
            error_log('[ViaGo Comprehend Language] ' . $e->getMessage());
        }
    }

    $score = min($score, 100);
    $threshold = viago_ad_score_threshold();
    $status = $score >= $threshold ? 'flagged' : 'normal';

    if ($status === 'flagged' && empty($reasons)) {
        viago_add_reason($reasons, '광고성 게시글로 의심됨');
    }

    return [
        'status' => $status,
        'score' => $score,
        'reason' => implode(', ', $reasons),
        'comprehend_result' => $comprehendResult,
    ];
}
