<?php

// AWS/ViaGo configuration loader.
$envFile = __DIR__ . '/env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}

require_once __DIR__ . '/storage.php';

function viago_config_value($constantName, $envName, $default = '') {
    if (defined($constantName)) {
        return constant($constantName);
    }

    $value = getenv($envName);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

$host = viago_config_value('VIAGO_DB_HOST', 'VIAGO_DB_HOST', 'REPLACE_WITH_RDS_ENDPOINT');
$dbname = viago_config_value('VIAGO_DB_NAME', 'VIAGO_DB_NAME', 'ViaGoDb');
$username = viago_config_value('VIAGO_DB_USER', 'VIAGO_DB_USER', 'viago_admin');
$password = viago_config_value('VIAGO_DB_PASS', 'VIAGO_DB_PASS', 'REPLACE_WITH_DB_PASSWORD');
$port = (int) viago_config_value('VIAGO_DB_PORT', 'VIAGO_DB_PORT', 3306);
$sslCa = viago_config_value('VIAGO_DB_SSL_CA', 'VIAGO_DB_SSL_CA', '/etc/pki/tls/certs/global-bundle.pem');

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($sslCa && file_exists($sslCa)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        $options
    );

} catch (PDOException $e) {
    http_response_code(500);
    die('DB 연결 실패: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
