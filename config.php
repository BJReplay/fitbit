<?php

declare(strict_types=1);

require_once __DIR__ . '/urls.php';
require_once __DIR__ . '/mysql-session-handler.php';

const APPLICATION_SESSION_LIFETIME = 60 * 60 * 24 * 30;

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
 * Retrieve and validate the database configuration before starting the
 * session because PHP sessions are stored in that database.
 */
$databaseEnvironment = [];

foreach (
    [
        'MYSQL_HOST',
        'MYSQL_PORT',
        'MYSQL_DATABASE',
        'MYSQL_USER',
        'MYSQL_PASSWORD',
    ] as $environmentName
) {
    $value = getenv($environmentName);

    if (
        !is_string($value) ||
        trim($value) === ''
    ) {
        error_log(
            sprintf(
                '%s is not configured.',
                $environmentName
            )
        );

        http_response_code(503);

        exit(
            'The service is temporarily unavailable.'
        );
    }

    $databaseEnvironment[$environmentName] =
        trim($value);
}

$host = $databaseEnvironment['MYSQL_HOST'];
$user = $databaseEnvironment['MYSQL_USER'];
$pass = $databaseEnvironment['MYSQL_PASSWORD'];
$dbname = $databaseEnvironment['MYSQL_DATABASE'];

$portValue =
    $databaseEnvironment['MYSQL_PORT'];

if (
    filter_var(
        $portValue,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
                'max_range' => 65535,
            ],
        ]
    ) === false
) {
    error_log(
        'MYSQL_PORT is not a valid TCP port.'
    );

    http_response_code(503);

    exit(
        'The service is temporarily unavailable.'
    );
}

$port = (int) $portValue;

$config_base_host =
    getenv('WEBSITE_HOSTNAME');

if (
    !is_string($config_base_host) ||
    trim($config_base_host) === ''
) {
    error_log(
        'WEBSITE_HOSTNAME is not configured.'
    );

    http_response_code(503);

    exit(
        'The service is temporarily unavailable.'
    );
}

$config_redirect_uri = applicationHttpsUrl(
    trim($config_base_host),
    '/token.php'
);

try {
    /*
     * PHP owns this connection through the session handler. It remains
     * independent of $config_conn so application code can close its own
     * connection before request shutdown.
     */
    $sessionConnection = mysqli_connect(
        $host,
        $user,
        $pass,
        $dbname,
        $port
    );

    mysqli_set_charset(
        $sessionConnection,
        'utf8mb4'
    );

    $sessionHandler = new MySqlSessionHandler(
        $sessionConnection
    );

    session_set_save_handler(
        $sessionHandler,
        true
    );

    ini_set(
        'session.gc_maxlifetime',
        (string) APPLICATION_SESSION_LIFETIME
    );

    session_set_cookie_params([
        'lifetime' =>
            APPLICATION_SESSION_LIFETIME,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    /*
     * This is the application's normal database connection.
     */
    $config_conn = mysqli_connect(
        $host,
        $user,
        $pass,
        $dbname,
        $port
    );

    mysqli_set_charset(
        $config_conn,
        'utf8mb4'
    );
} catch (mysqli_sql_exception $exception) {
    error_log(
        'Database or session initialisation failed: ' .
        $exception->getMessage()
    );

    http_response_code(503);

    exit(
        'The service is temporarily unavailable.'
    );
}

$cspNonce = base64_encode(
    random_bytes(18)
);

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
header(
    "Content-Security-Policy: {$csp}"
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Referrer-Policy: strict-origin-when-cross-origin'
);

include_once __DIR__ . '/functions.php';
