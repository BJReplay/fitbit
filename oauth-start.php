<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-oauth.php';

try {
    /*
     * Rotate the existing PHP session ID before storing ephemeral OAuth
     * credentials in the session.
     */
    session_regenerate_id(true);

    $state = bin2hex(
        random_bytes(32)
    );

    $_SESSION['google_oauth_state'] = $state;

    $client = createGoogleOAuthClient(
    $config_redirect_uri
    );

    $client->setState($state);

    /*
    * Force consent during this initial connection so that we can obtain
    * offline credentials. The client factory already specifies
    * access_type=offline.
    */
    $client->setPrompt('consent');

    /*
    * Use PKCE in addition to the confidential web application's
    * client credentials.
    */
    $client->setUsePkce(true);

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

    exit(
        'Unable to begin Google Health authorisation.'
    );
}