<?php

declare(strict_types=1);

use Google\Client;

require_once __DIR__ . '/vendor/autoload.php';

const GOOGLE_HEALTH_READ_SCOPE =
'https://www.googleapis.com/auth/' .
    'googlehealth.health_metrics_and_measurements.readonly';

const GOOGLE_HEALTH_ACTIVITY_READ_SCOPE =
'https://www.googleapis.com/auth/' .
    'googlehealth.activity_and_fitness.readonly';

/**
 * Returns a required environment variable.
 */
function requiredEnvironmentVariable(string $name): string
{
    $value = getenv($name);

    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException(
            sprintf(
                'Required environment variable "%s" is not configured.',
                $name
            )
        );
    }

    return trim($value);
}

/**
 * Creates the Google OAuth client shared by the authorisation,
 * callback and token-refresh paths.
 */
function createGoogleOAuthClient(string $redirectUri): Client
{
    if ($redirectUri === '') {
        throw new InvalidArgumentException(
            'The Google OAuth redirect URI must not be empty.'
        );
    }

    $client = new Client();

    $client->setApplicationName(
        'Google Health Heart Rate Intraday Data'
    );

    $client->setClientId(
        requiredEnvironmentVariable(
            'GOOGLE_OAUTH_CLIENT_ID'
        )
    );

    $client->setClientSecret(
        requiredEnvironmentVariable(
            'GOOGLE_OAUTH_CLIENT_SECRET'
        )
    );

    $client->setRedirectUri(
        $redirectUri
    );

    $client->setScopes([
        'openid',
        'email',
        'profile',
        GOOGLE_HEALTH_READ_SCOPE,
        GOOGLE_HEALTH_ACTIVITY_READ_SCOPE,
    ]);

    /*
     * Request offline access so that Google can issue a refresh token.
     */
    $client->setAccessType('offline');

    $client->setIncludeGrantedScopes(true);

    return $client;
}

/**
 * Loads and validates the key used to encrypt OAuth credentials.
 */
function googleOAuthEncryptionKey(): string
{
    if (!extension_loaded('sodium')) {
        throw new RuntimeException(
            'The PHP sodium extension is required.'
        );
    }

    $encodedKey = requiredEnvironmentVariable(
        'GOOGLE_OAUTH_ENCRYPTION_KEY'
    );

    $key = base64_decode(
        $encodedKey,
        true
    );

    if (
        $key === false ||
        strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES
    ) {
        throw new RuntimeException(
            'GOOGLE_OAUTH_ENCRYPTION_KEY must be a ' .
                'Base64-encoded 32-byte key.'
        );
    }

    return $key;
}

/**
 * Encrypts an OAuth token bundle for database storage.
 *
 * The random nonce is stored in front of the ciphertext. The nonce does
 * not need to be secret.
 *
 * @param array<string, mixed> $token
 */
function encryptGoogleOAuthToken(array $token): string
{
    if ($token === []) {
        throw new InvalidArgumentException(
            'The Google OAuth token must not be empty.'
        );
    }

    $json = json_encode(
        $token,
        JSON_THROW_ON_ERROR |
            JSON_UNESCAPED_SLASHES
    );

    $nonce = random_bytes(
        SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
    );

    $ciphertext = sodium_crypto_secretbox(
        $json,
        $nonce,
        googleOAuthEncryptionKey()
    );

    return base64_encode(
        $nonce . $ciphertext
    );
}

/**
 * Decrypts an OAuth token bundle loaded from the database.
 *
 * @return array<string, mixed>
 */
function decryptGoogleOAuthToken(
    string $encryptedToken
): array {
    if ($encryptedToken === '') {
        throw new InvalidArgumentException(
            'The encrypted Google OAuth token is empty.'
        );
    }

    $payload = base64_decode(
        $encryptedToken,
        true
    );

    if ($payload === false) {
        throw new RuntimeException(
            'The stored Google OAuth token is not valid Base64.'
        );
    }

    if (
        strlen($payload) <=
        SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
    ) {
        throw new RuntimeException(
            'The stored Google OAuth token is incomplete.'
        );
    }

    $nonce = substr(
        $payload,
        0,
        SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
    );

    $ciphertext = substr(
        $payload,
        SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
    );

    $json = sodium_crypto_secretbox_open(
        $ciphertext,
        $nonce,
        googleOAuthEncryptionKey()
    );

    if ($json === false) {
        throw new RuntimeException(
            'The stored Google OAuth token could not be decrypted.'
        );
    }

    $token = json_decode(
        $json,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($token)) {
        throw new UnexpectedValueException(
            'The stored Google OAuth token is not a JSON object.'
        );
    }

    return $token;
}

/**
 * Stores one user's encrypted OAuth token.
 *
 * @param array<string, mixed> $token
 */
function saveGoogleOAuthToken(
    mysqli $connection,
    string $googleSub,
    array $token
): void {
    if ($googleSub === '') {
        throw new InvalidArgumentException(
            'The Google user identifier must not be empty.'
        );
    }

    $accessToken =
        $token['access_token'] ??
        null;

    if (
        !is_string($accessToken) ||
        $accessToken === ''
    ) {
        throw new InvalidArgumentException(
            'The Google OAuth response does not contain an access token.'
        );
    }

    $encryptedToken = encryptGoogleOAuthToken(
        $token
    );

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO oauth_credentials
            (google_sub, encrypted_token, updated_at)
         VALUES
            (?, ?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            encrypted_token = VALUES(encrypted_token),
            updated_at = UTC_TIMESTAMP()'
    );

    mysqli_stmt_bind_param(
        $statement,
        'ss',
        $googleSub,
        $encryptedToken
    );

    mysqli_stmt_execute(
        $statement
    );

    mysqli_stmt_close(
        $statement
    );
}

/**
 * Loads one user's stored OAuth token.
 *
 * Returns null if that user has not yet authorised Google Health.
 *
 * @return array<string, mixed>|null
 */
function loadGoogleOAuthToken(
    mysqli $connection,
    string $googleSub
): ?array {
    if ($googleSub === '') {
        throw new InvalidArgumentException(
            'The Google user identifier must not be empty.'
        );
    }

    $statement = mysqli_prepare(
        $connection,
        'SELECT encrypted_token
           FROM oauth_credentials
          WHERE google_sub = ?'
    );

    mysqli_stmt_bind_param(
        $statement,
        's',
        $googleSub
    );

    mysqli_stmt_execute(
        $statement
    );

    $encryptedToken = '';

    mysqli_stmt_bind_result(
        $statement,
        $encryptedToken
    );

    $fetched = mysqli_stmt_fetch(
        $statement
    );

    mysqli_stmt_close(
        $statement
    );

    if ($fetched !== true) {
        return null;
    }

    if ($encryptedToken === '') {
        throw new UnexpectedValueException(
            'The stored Google OAuth credentials are empty.'
        );
    }

    return decryptGoogleOAuthToken(
        $encryptedToken
    );
}

/**
 * Removes one user's locally stored OAuth credentials.
 */
function deleteGoogleOAuthToken(
    mysqli $connection,
    string $googleSub
): void {
    if ($googleSub === '') {
        throw new InvalidArgumentException(
            'The Google user identifier must not be empty.'
        );
    }

    $statement = mysqli_prepare(
        $connection,
        'DELETE FROM oauth_credentials
          WHERE google_sub = ?'
    );

    mysqli_stmt_bind_param(
        $statement,
        's',
        $googleSub
    );

    mysqli_stmt_execute(
        $statement
    );

    mysqli_stmt_close(
        $statement
    );
}

/**
 * Returns a Google OAuth client with a valid access token for one user.
 *
 * If the stored access token has expired, the refresh token is used to
 * obtain a replacement access token and the updated token bundle is
 * persisted against that same Google user.
 */
function authorisedGoogleOAuthClient(
    mysqli $connection,
    string $redirectUri,
    string $googleSub
): Client {
    $storedToken = loadGoogleOAuthToken(
        $connection,
        $googleSub
    );

    if ($storedToken === null) {
        throw new RuntimeException(
            'Google Health has not been authorised for this user.'
        );
    }

    $client = createGoogleOAuthClient(
        $redirectUri
    );

    $client->setAccessToken(
        $storedToken
    );

    if (!$client->isAccessTokenExpired()) {
        return $client;
    }

    $refreshToken =
        $storedToken['refresh_token'] ??
        $client->getRefreshToken();

    if (
        !is_string($refreshToken) ||
        $refreshToken === ''
    ) {
        throw new RuntimeException(
            'The Google OAuth refresh token is unavailable. ' .
                'Google Health must be authorised again.'
        );
    }

    $refreshedToken =
        $client->fetchAccessTokenWithRefreshToken(
            $refreshToken
        );

    $refreshError =
        $refreshedToken['error'] ??
        null;

    if (
        is_string($refreshError) &&
        $refreshError !== ''
    ) {
        $description =
            $refreshedToken['error_description'] ??
            $refreshError;

        throw new RuntimeException(
            'Unable to refresh the Google access token: ' .
                (string) $description
        );
    }

    $newAccessToken =
        $refreshedToken['access_token'] ??
        null;

    if (
        !is_string($newAccessToken) ||
        $newAccessToken === ''
    ) {
        throw new UnexpectedValueException(
            'Google did not return a refreshed access token.'
        );
    }

    /*
     * Google may not repeat the refresh token in a refresh response.
     * Preserve the existing refresh token when it is omitted.
     */
    if (
        !isset($refreshedToken['refresh_token']) ||
        !is_string($refreshedToken['refresh_token']) ||
        $refreshedToken['refresh_token'] === ''
    ) {
        $refreshedToken['refresh_token'] =
            $refreshToken;
    }

    saveGoogleOAuthToken(
        $connection,
        $googleSub,
        $refreshedToken
    );

    $client->setAccessToken(
        $refreshedToken
    );

    return $client;
}

/**
 * Returns whether one user has stored Google OAuth credentials.
 *
 * This checks only local storage. It does not contact Google or establish
 * whether the stored credentials are still valid.
 */
function hasStoredGoogleOAuthToken(
    mysqli $connection,
    string $googleSub
): bool {
    return loadGoogleOAuthToken(
        $connection,
        $googleSub
    ) !== null;
}
