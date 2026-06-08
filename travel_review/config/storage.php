<?php

function viago_storage_config($constantName, $envName, $default = '') {
    if (defined($constantName)) {
        return constant($constantName);
    }

    $value = getenv($envName);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

function viago_base_path() {
    return rtrim(viago_storage_config('VIAGO_BASE_PATH', 'VIAGO_BASE_PATH', '/travel_review'), '/');
}

function viago_s3_bucket() {
    return viago_storage_config('VIAGO_S3_BUCKET', 'VIAGO_S3_BUCKET', 'viago-travel-image-bucket-1');
}

function viago_s3_region() {
    return viago_storage_config('VIAGO_S3_REGION', 'VIAGO_S3_REGION', 'ap-northeast-2');
}

function viago_cf_domain() {
    return preg_replace('#^https?://#', '', rtrim(viago_storage_config('VIAGO_CF_DOMAIN', 'VIAGO_CF_DOMAIN', ''), '/'));
}

function media_url($path) {
    if (!$path) {
        return viago_base_path() . '/assets/no-image.jpg';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return viago_base_path() . '/' . ltrim($path, '/');
}

function viago_default_profile_image() {
    return viago_base_path() . '/assets/images/default-profile.png';
}

function viago_uploaded_file_array($files, $key) {
    return [
        'name' => $files['name'][$key] ?? '',
        'type' => $files['type'][$key] ?? '',
        'tmp_name' => $files['tmp_name'][$key] ?? '',
        'error' => $files['error'][$key] ?? UPLOAD_ERR_NO_FILE,
        'size' => $files['size'][$key] ?? 0,
    ];
}

function viago_detect_media_type($extension) {
    return in_array(strtolower($extension), ['mp4', 'webm', 'mov'], true) ? 'video' : 'image';
}

function viago_upload_to_s3($file, $prefix = 'reviews') {
    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('파일 업로드 중 오류가 발생했습니다.');
    }

    $originalName = $file['name'] ?? '';
    $tmpName = $file['tmp_name'] ?? '';
    $size = (int)($file['size'] ?? 0);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowedImageExtensions = ['jpg', 'jpeg', 'png'];
    $allowedVideoExtensions = ['mp4', 'webm', 'mov'];
    $allowedExtensions = array_merge($allowedImageExtensions, $allowedVideoExtensions);
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception('허용되지 않는 파일 형식입니다. 이미지는 JPG/JPEG/PNG, 영상은 MP4/WEBM/MOV만 업로드할 수 있습니다.');
    }

    $mediaType = viago_detect_media_type($extension);

    if (trim($prefix, '/') === 'profiles' && $mediaType !== 'image') {
        throw new Exception('프로필 이미지는 JPG/JPEG/PNG 파일만 업로드할 수 있습니다.');
    }
    $maxSize = $mediaType === 'video' ? 200 * 1024 * 1024 : 20 * 1024 * 1024;
    if ($size > $maxSize) {
        throw new Exception($mediaType === 'video' ? '영상은 200MB 이하만 업로드할 수 있습니다.' : '이미지는 20MB 이하만 업로드할 수 있습니다.');
    }

    if (!is_uploaded_file($tmpName) && !file_exists($tmpName)) {
        throw new Exception('업로드 임시 파일을 찾을 수 없습니다.');
    }

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmpName);
        if ($detected) {
            $mime = $detected;
        }
    }

    $bucket = viago_s3_bucket();
    $region = viago_s3_region();
    $cfDomain = viago_cf_domain();

    if ($bucket === '' || $cfDomain === '' || strpos($cfDomain, 'REPLACE_WITH') !== false) {
        throw new Exception('S3 버킷 또는 CloudFront 도메인이 설정되지 않았습니다. config/env.php를 확인하세요.');
    }

    $safePrefix = trim($prefix, '/');
    $key = 'posts/' . $safePrefix . '/' . date('Y/m/d') . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
    $s3Uri = 's3://' . $bucket . '/' . $key;

    $cmd =
        'HOME=/tmp AWS_EC2_METADATA_DISABLED=false aws s3 cp ' .
        escapeshellarg($tmpName) . ' ' .
        escapeshellarg($s3Uri) .
        ' --region ' . escapeshellarg($region) .
        ' --content-type ' . escapeshellarg($mime) .
        ' --sse AES256' .
        ' --cache-control ' . escapeshellarg('public, max-age=31536000') .
        ' 2>&1';

    exec($cmd, $output, $code);
    if ($code !== 0) {
        throw new Exception('S3 업로드 실패: ' . implode("\n", $output));
    }

    return [
        'file_path' => 'https://' . $cfDomain . '/' . $key,
        'file_type' => $mediaType,
        's3_key' => $key,
        'bucket' => $bucket,
    ];
}

function viago_s3_key_from_url($url) {
    if (!$url) {
        return null;
    }

    $cfDomain = viago_cf_domain();
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || empty($parts['path'])) {
        return null;
    }

    if ($cfDomain && strtolower($parts['host']) === strtolower($cfDomain)) {
        return ltrim($parts['path'], '/');
    }

    return null;
}

function viago_delete_media_file($path) {
    if (!$path) {
        return;
    }

    $key = viago_s3_key_from_url($path);
    if ($key) {
        $bucket = viago_s3_bucket();
        $region = viago_s3_region();
        $cmd = 'HOME=/tmp AWS_EC2_METADATA_DISABLED=false aws s3 rm ' .
            escapeshellarg('s3://' . $bucket . '/' . $key) .
            ' --region ' . escapeshellarg($region) .
            ' 2>&1';
        exec($cmd, $output, $code);
        return;
    }

    $localPath = realpath(__DIR__ . '/../' . ltrim($path, '/'));
    $appRoot = realpath(__DIR__ . '/..');
    if ($localPath && $appRoot && str_starts_with($localPath, $appRoot) && file_exists($localPath)) {
        unlink($localPath);
    }
}
