<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-identity.php';
require_once __DIR__ . '/google-oauth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');

    exit('Method not allowed.');
}

$token = $_POST['token'] ?? null;
$expectedToken = $_SESSION['google_disconnect_token'] ?? null;

if (
    !is_string($token) ||
    !is_string($expectedToken) ||
    $token === '' ||
    $expectedToken === '' ||
    !hash_equals($expectedToken, $token)
) {
    http_response_code(400);

    exit('Invalid disconnect request.');
}

unset($_SESSION['google_disconnect_token']);

try {
    $googleSub = authenticatedGoogleSub();

    deleteGoogleOAuthToken(
        $config_conn,
        $googleSub
    );
} catch (Throwable $exception) {
    error_log(
        'Unable to disconnect Google Health: ' .
            $exception->getMessage()
    );

    http_response_code(500);

    exit('Unable to disconnect Google Health.');
}

/*
 * Preserve the application login. Only the stored Google Health OAuth
 * credentials are removed.
 */
header(
    'Location: index.php',
    true,
    303
);

exit;
