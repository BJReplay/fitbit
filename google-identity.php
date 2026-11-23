<?php

declare(strict_types=1);

use Google\Client;

/**
 * Validates the OpenID Connect ID token returned by Google and extracts
 * the stable Google account identity used by this application.
 *
 * @param array<string, mixed> $token
 * @return array{
 *     googleSub: string,
 *     email: string|null,
 *     displayName: string|null
 * }
 */
function googleAuthenticatedIdentity(
    Client $client,
    array $token
): array {
    $idToken =
        $token['id_token'] ??
        null;

    if (!is_string($idToken) || $idToken === '') {
        throw new UnexpectedValueException(
            'Google did not return an OpenID Connect ID token.'
        );
    }

    $payload = $client->verifyIdToken(
        $idToken
    );

    if (!is_array($payload)) {
        throw new RuntimeException(
            'The Google OpenID Connect ID token is invalid.'
        );
    }

    $googleSub =
        $payload['sub'] ??
        null;

    if (!is_string($googleSub) || $googleSub === '') {
        throw new UnexpectedValueException(
            'The Google ID token does not contain a usable subject.'
        );
    }

    $email =
        $payload['email'] ??
        null;

    if (!is_string($email) || $email === '') {
        $email = null;
    }

    $displayName =
        $payload['name'] ??
        null;

    if (!is_string($displayName) || $displayName === '') {
        $displayName = null;
    }

    return [
        'googleSub' =>
        $googleSub,

        'email' =>
        $email,

        'displayName' =>
        $displayName,
    ];
}

/**
 * Creates or updates the local application profile for a Google account.
 *
 * @param array{
 *     googleSub: string,
 *     email: string|null,
 *     displayName: string|null
 * } $identity
 */
function saveGoogleAuthenticatedUser(
    mysqli $connection,
    array $identity
): void {
    $googleSub = $identity['googleSub'];
    $email = $identity['email'];
    $displayName = $identity['displayName'];

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO users
            (google_sub, email, display_name)
         VALUES
            (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            email = VALUES(email),
            display_name = VALUES(display_name),
            updated_at = UTC_TIMESTAMP()'
    );

    mysqli_stmt_bind_param(
        $statement,
        'sss',
        $googleSub,
        $email,
        $displayName
    );

    mysqli_stmt_execute(
        $statement
    );

    mysqli_stmt_close(
        $statement
    );
}

/**
 * Stores the validated Google identity in the application session.
 *
 * @param array{
 *     googleSub: string,
 *     email: string|null,
 *     displayName: string|null
 * } $identity
 */
function setGoogleAuthenticatedSession(
    array $identity
): void {
    $_SESSION['google_user_sub'] =
        $identity['googleSub'];

    $_SESSION['google_user_email'] =
        $identity['email'];

    $_SESSION['google_user_name'] =
        $identity['displayName'];
}
