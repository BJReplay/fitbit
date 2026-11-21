<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-oauth.php';

/**
 * Stops an invalid OAuth callback without exposing Google's response or
 * credential information to the browser.
 */
function googleOAuthCallbackFailure(
    string $logMessage
): never {
    unset(
        $_SESSION['google_oauth_state'],
        $_SESSION['google_oauth_code_verifier']
    );

    error_log($logMessage);

    http_response_code(400);

    exit(
        'Google Health authorisation failed. ' .
        'Please return to the home page and try again.'
    );
}

/*
 * Google can return an OAuth error rather than an authorisation code, for
 * example when the user declines consent.
 */
$error = $_GET['error'] ?? null;

if (is_string($error) && $error !== '') {
    googleOAuthCallbackFailure(
        'Google OAuth returned error: ' . $error
    );
}

$state = $_GET['state'] ?? null;

$expectedState =
    $_SESSION['google_oauth_state'] ??
    null;

if (
    !is_string($state) ||
    !is_string($expectedState) ||
    $state === '' ||
    $expectedState === '' ||
    !hash_equals($expectedState, $state)
) {
    googleOAuthCallbackFailure(
        'Google OAuth state validation failed.'
    );
}

$codeVerifier =
    $_SESSION['google_oauth_code_verifier'] ??
    null;

/*
 * Consume state immediately after successful validation. Even if token
 * exchange subsequently fails, the callback must not be replayable.
 */
unset(
    $_SESSION['google_oauth_state'],
    $_SESSION['google_oauth_code_verifier']
);

if (
    !is_string($codeVerifier) ||
    $codeVerifier === ''
) {
    googleOAuthCallbackFailure(
        'Google OAuth PKCE verifier is unavailable.'
    );
}

$code = $_GET['code'] ?? null;

if (
    !is_string($code) ||
    $code === ''
) {
    googleOAuthCallbackFailure(
        'Google OAuth did not return an authorisation code.'
    );
}

try {
    $client = createGoogleOAuthClient(
        $config_redirect_uri
    );

    $token = $client->fetchAccessTokenWithAuthCode(
        $code,
        $codeVerifier
    );

    $error = $token['error'] ?? null;

    if (
        is_string($error) &&
        $error !== ''
    ) {
        $description =
            $token['error_description'] ??
            $error;

        throw new RuntimeException(
            'Google OAuth token exchange failed: ' .
            (string) $description
        );
    }

    $accessToken =
        $token['access_token'] ??
        null;

    if (
        !is_string($accessToken) ||
        $accessToken === ''
    ) {
        throw new UnexpectedValueException(
            'Google did not return an access token.'
        );
    }

    $refreshToken =
        $token['refresh_token'] ??
        null;

    if (
        !is_string($refreshToken) ||
        $refreshToken === ''
    ) {
        throw new UnexpectedValueException(
            'Google did not return a refresh token.'
        );
    }

    saveGoogleOAuthToken(
        $config_conn,
        $token
    );

    session_regenerate_id(true);

    header(
        'Location: data.php',
        true,
        303
    );

    exit;
} catch (Throwable $exception) {
    error_log(
        'Google OAuth callback failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to complete Google Health authorisation.'
    );
}