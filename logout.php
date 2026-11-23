<?php

declare(strict_types=1);

include_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');

    exit('Method not allowed.');
}

$token = $_POST['token'] ?? null;
$expectedToken = $_SESSION['google_logout_token'] ?? null;

if (
    !is_string($token) ||
    !is_string($expectedToken) ||
    $token === '' ||
    $expectedToken === '' ||
    !hash_equals($expectedToken, $token)
) {
    http_response_code(400);

    exit('Invalid logout request.');
}

unset($_SESSION['google_logout_token']);

/*
 * Log out from this browser only. Stored Google OAuth credentials and
 * historical health data remain associated with the application user.
 */
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

header(
    'Location: index.php',
    true,
    303
);

exit;
