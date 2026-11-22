<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-oauth.php';

try {
    session_regenerate_id(true);

    /*
     * Protect the callback against CSRF.
     */
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;

    $client = createGoogleOAuthClient(
        $config_redirect_uri
    );

    $client->setState($state);

    /*
     * Request a new consent grant so that this initial connection
     * returns a refresh token. createGoogleOAuthClient() already
     * configures access_type=offline.
     */
    $client->setPrompt('consent');

    $codeVerifier = $client
        ->getOAuth2Service()
        ->generateCodeVerifier();

    if ($codeVerifier === '') {
        throw new RuntimeException(
            'Google OAuth did not generate a PKCE code verifier.'
        );
    }

    $_SESSION['google_oauth_code_verifier'] =
        $codeVerifier;

    $authUrl = $client->createAuthUrl();

    header(
        'Location: ' . $authUrl,
        true,
        302
    );

    exit;
} catch (Throwable $exception) {
    unset(
        $_SESSION['google_oauth_state'],
        $_SESSION['google_oauth_code_verifier']
    );

    error_log(
        'Unable to begin Google OAuth: ' .
            $exception->getMessage()
    );

    http_response_code(500);

    exit('Unable to begin Google Health authorisation.');
}
