<?php

declare(strict_types=1);

session_start();

$cspNonce = base64_encode(random_bytes(18));

$csp = implode('; ', [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "form-action 'self'",
    "script-src 'self' 'nonce-{$cspNonce}'",
    "style-src 'self'",
    "img-src 'self' data: blob:",
    "font-src 'self'",
    "connect-src 'self'",
    "manifest-src 'self'",
    "worker-src 'none'",
    "frame-ancestors 'none'",
    "frame-src 'none'",
    "upgrade-insecure-requests",
]);

// Uncomment the following line to enable reporting of CSP violations to a specified endpoint
// $csp .= "; report-uri /csp-report-endpoint.php";
// header("Content-Security-Policy-Report-Only: {$csp}");
// Comment the line below to disable enforcement of the CSP if you uncomment the report-uri line above
header("Content-Security-Policy: {$csp}");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
include_once __DIR__ . '/functions.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Retrieve and validate the database connection string.
$conn = getenv('MYSQLCONNSTR_localdb');
if (!is_string($conn) || trim($conn) === '') {
    error_log('MYSQLCONNSTR_localdb is not configured.');
    http_response_code(503);
    exit('The service is temporarily unavailable.');
}

$conarr = [];
foreach (explode(';', $conn) as $value) {
    $separator = strpos($value, '=');
    if ($separator === false) {
        continue;
    }
    $key = trim(substr($value, 0, $separator));
    if ($key !== '') {
        $conarr[$key] = substr($value, $separator + 1);
    }
}

foreach (['Data Source', 'User Id', 'Password', 'Database'] as $requiredKey) {
    if (!array_key_exists($requiredKey, $conarr)) {
        error_log(sprintf('MYSQLCONNSTR_localdb is missing the "%s" field.', $requiredKey));
        http_response_code(503);
        exit('The service is temporarily unavailable.');
    }
}

$host = $conarr['Data Source'];
$user = $conarr['User Id'];
$pass = $conarr['Password'];
$dbname = $conarr['Database'];

$config_base_host = getenv('WEBSITE_HOSTNAME');
if (!is_string($config_base_host) || trim($config_base_host) === '') {
    error_log('WEBSITE_HOSTNAME is not configured.');
    http_response_code(503);
    exit('The service is temporarily unavailable.');
}

$config_redirect_uri = 'https://' . trim($config_base_host) . '/token.php';

try {
    $config_conn = mysqli_connect($host, $user, $pass, $dbname);
    mysqli_set_charset($config_conn, 'utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The service is temporarily unavailable.');
}
