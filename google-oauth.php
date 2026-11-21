<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-oauth.php';

try {
    // Start the OAuth flow with a fresh session ID.
    session_regenerate_id(true);

    // Protect the callback against CSRF.
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;

    $client = createGoogleOAuthClient(
        $config_redirect_uri
    );

    $client->setState($state);

    /*
     * The application needs offline access so it can refresh the access
     * token without requiring interactive authorisation each time.
     *
     * createGoogleOAuthClient() already sets access_type=offline.
     *
     * Request consent for this connection so that a refresh token is
     * returned.
     */
    $client->setPrompt('consent');

    /*
     * Enable PKCE and explicitly generate the verifier before constructing
     * the authorisation URL.
     *
     * generateCodeVerifier() stores the verifier on the OAuth2 object.
     * buildFullAuthorizationUri() can therefore derive its S256 challenge
     * when createAuthUrl() is called.
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

    /*
     * The callback must use this exact verifier when exchanging the
     * authorisation code.
     */
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