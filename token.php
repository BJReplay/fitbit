<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-oauth.php';

/**
 * Fails an invalid OAuth callback without exposing OAuth credentials
 * or Google's response details to the browser.
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
 * Google may return an OAuth error instead of an authorisation code,
 * for example if consent is declined.
 */
$error = $_GET['error'] ?? null;

if (
    is_string($error) &&
    $error !== ''
) {
    googleOAuthCallbackFailure(
        'Google OAuth returned error: ' .
        $error
    );
}

/*
 * Validate the state sent back by Google.
 */
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

/*
 * Retrieve the PKCE verifier that belongs to this OAuth attempt.
 */
$codeVerifier =
    $_SESSION['google_oauth_code_verifier'] ??
    null;

if (
    !is_string($codeVerifier) ||
    $codeVerifier === ''
) {
    googleOAuthCallbackFailure(
        'Google OAuth PKCE verifier is unavailable.'
    );
}

/*
 * Get the short-lived authorisation code.
 */
$code = $_GET['code'] ?? null;

if (
    !is_string($code) ||
    $code === ''
) {
    googleOAuthCallbackFailure(
        'Google OAuth did not return an authorisation code.'
    );
}

/*
 * State and PKCE verifier are single-use values.
 *
 * Keep the local verifier variable for the token exchange, but remove
 * both values from the session before the exchange takes place.
 */
unset(
    $_SESSION['google_oauth_state'],
    $_SESSION['google_oauth_code_verifier']
);

try {
    $client = createGoogleOAuthClient(
        $config_redirect_uri
    );

    $token =
        $client->fetchAccessTokenWithAuthCode(
            $code,
            $codeVerifier
        );

    $tokenError =
        $token['error'] ??
        null;

    if (
        is_string($tokenError) &&
        $tokenError !== ''
    ) {
        $description =
            $token['error_description'] ??
            $tokenError;

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

    /*
    * The newly authorised token may represent a different Google account.
    * Force the application to resolve its Google Health identity again.
    */
    unset(
        $_SESSION['google_health_user_id']
    );

    /*
     * Rotate the session after successfully completing authorisation.
     */
    session_regenerate_id(true);

    /*
     * data.php is still Fitbit-based, but redirecting there preserves
     * existing behaviour while we test the OAuth plumbing.
     */
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