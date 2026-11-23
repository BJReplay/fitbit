<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-oauth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');

    exit('Method not allowed.');
}

$token =
    $_POST['token'] ??
    null;

$expectedToken =
    $_SESSION['google_logout_token'] ??
    null;

if (
    !is_string($token) ||
    !is_string($expectedToken) ||
    $token === '' ||
    $expectedToken === '' ||
    !hash_equals(
        $expectedToken,
        $token
    )
) {
    http_response_code(400);

    exit('Invalid logout request.');
}

/*
 * The logout token is single-use.
 */
unset(
    $_SESSION['google_logout_token']
);

try {
    $googleSub =
        $_SESSION['google_user_sub'] ??
        null;

    if (
        is_string($googleSub) &&
        $googleSub !== ''
    ) {
        deleteGoogleOAuthToken(
            $config_conn,
            $googleSub
        );
    }
} catch (Throwable $exception) {
    error_log(
        'Unable to remove Google OAuth credentials during logout: ' .
            $exception->getMessage()
    );

    http_response_code(500);

    exit('Unable to log out of Google Health.');
}

/*
 * Remove all application session state.
 */
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params =
        session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header(
    'Location: index.php',
    true,
    303
);

exit;
