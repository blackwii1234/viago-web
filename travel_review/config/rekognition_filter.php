<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/storage.php';

function viago_rekognition_enabled(): bool
{
    return defined('VIAGO_REKOGNITION_ENABLED') && VIAGO_REKOGNITION_ENABLED === 'true';
}

function viago_rekognition_region(): string
{
    return defined('VIAGO_REKOGNITION_REGION') ? VIAGO_REKOGNITION_REGION : viago_s3_region();
}

function viago_rekognition_min_confidence(): int
{
    return defined('VIAGO_REKOGNITION_MIN_CONFIDENCE') ? (int)VIAGO_REKOGNITION_MIN_CONFIDENCE : 70;
}

function viago_image_block_threshold(): int
{
    return defined('VIAGO_IMAGE_BLOCK_THRESHOLD') ? (int)VIAGO_IMAGE_BLOCK_THRESHOLD : 85;
}

function viago_image_flag_threshold(): int
{
    return defined('VIAGO_IMAGE_FLAG_THRESHOLD') ? (int)VIAGO_IMAGE_FLAG_THRESHOLD : 70;
}

function viago_is_rekognition_supported_image(string $fileType, ?string $s3Key): bool
{
    if ($fileType !== 'image' || !$s3Key) {
        return false;
    }

    $extension = strtolower(pathinfo($s3Key, PATHINFO_EXTENSION));
    return in_array($extension, ['jpg', 'jpeg', 'png'], true);
}

function viago_analyze_s3_image_moderation(string $bucket, string $objectKey, string $fileType = 'image'): array
{
    if (!viago_rekognition_enabled()) {
        return [
            'status' => 'normal',
            'score' => 0,
            'reason' => '',
            'labels' => [],
            'raw_result' => null,
        ];
    }

    if (!viago_is_rekognition_supported_image($fileType, $objectKey)) {
        return [
            'status' => 'normal',
            'score' => 0,
            'reason' => '',
            'labels' => [],
            'raw_result' => ['skipped' => 'Rekognition moderation is applied only to JPG/JPEG/PNG images.'],
        ];
    }

    $region = viago_rekognition_region();
    $minConfidence = viago_rekognition_min_confidence();
    $payload = [
        'S3Object' => [
            'Bucket' => $bucket,
            'Name' => $objectKey,
        ],
    ];

    $payloadFile = tempnam(sys_get_temp_dir(), 'viago-rekog-image-');
    file_put_contents($payloadFile, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $cmd =
        'HOME=/tmp AWS_EC2_METADATA_DISABLED=false aws rekognition detect-moderation-labels ' .
        '--region ' . escapeshellarg($region) . ' ' .
        '--image ' . escapeshellarg('file://' . $payloadFile) . ' ' .
        '--min-confidence ' . escapeshellarg((string)$minConfidence) . ' ' .
        '--output json 2>&1';

    exec($cmd, $output, $code);
    @unlink($payloadFile);

    $outputText = implode("\n", $output);
    $result = json_decode($outputText, true);

    if ($code !== 0 || !is_array($result)) {
        error_log('[Rekognition Error] ' . $outputText);
        return [
            'status' => 'flagged',
            'score' => viago_image_flag_threshold(),
            'reason' => '이미지 자동 검열 실패',
            'labels' => [],
            'raw_result' => ['error' => $outputText],
        ];
    }

    $labels = $result['ModerationLabels'] ?? [];

    $blockCategories = [
        'Explicit Nudity',
        'Explicit Sexual Activity',
        'Graphic Male Nudity',
        'Graphic Female Nudity',
        'Sexual Activity',
        'Graphic Violence',
        'Hate Symbols',
        'Extremist',
        'Extremist Symbols',
    ];

    $reviewCategories = [
        'Non-Explicit Nudity',
        'Violence',
        'Visually Disturbing',
        'Drugs & Tobacco',
        'Drugs',
        'Tobacco',
        'Alcohol',
        'Gambling',
        'Rude Gestures',
        'Weapons',
    ];

    $matched = [];
    $score = 0;
    $status = 'normal';

    foreach ($labels as $label) {
        $name = (string)($label['Name'] ?? '');
        $parent = (string)($label['ParentName'] ?? '');
        $confidence = (float)($label['Confidence'] ?? 0);

        $isBlock = in_array($name, $blockCategories, true) || in_array($parent, $blockCategories, true);
        $isReview = $isBlock || in_array($name, $reviewCategories, true) || in_array($parent, $reviewCategories, true);

        if ($isReview) {
            $score = max($score, (int)round($confidence));
            $matched[] = [
                'name' => $name,
                'parent' => $parent,
                'confidence' => $confidence,
            ];
        }

        if ($isBlock && $confidence >= viago_image_block_threshold()) {
            $status = 'blocked';
        }
    }

    if ($status !== 'blocked' && $score >= viago_image_flag_threshold()) {
        $status = 'flagged';
    }

    $reason = '';
    if ($matched) {
        $labelNames = [];
        foreach ($matched as $item) {
            $labelNames[] = $item['name'] . ($item['parent'] ? ' / ' . $item['parent'] : '') . ' ' . round($item['confidence'], 1) . '%';
        }
        $reason = 'Rekognition 이미지 모더레이션 라벨 감지: ' . implode(', ', array_slice($labelNames, 0, 5));
    }

    return [
        'status' => $status,
        'score' => min($score, 100),
        'reason' => $reason,
        'labels' => $matched,
        'raw_result' => $result,
    ];
}
