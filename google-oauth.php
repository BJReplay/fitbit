<?php

declare(strict_types=1);

use Google\Client;

require_once __DIR__ . '/vendor/autoload.php';

const GOOGLE_HEALTH_READ_SCOPE =
    'https://www.googleapis.com/auth/' .
    'googlehealth.health_metrics_and_measurements.readonly';

/**
 * Reads a required environment variable.
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
 * Creates the OAuth client shared by the authorisation, callback and refresh
 * paths.
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
        'Fitbit Heart Rate Intraday Data'
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

    $client->setRedirectUri($redirectUri);

    $client->setScopes([
        GOOGLE_HEALTH_READ_SCOPE,
    ]);

    /*
     * Offline access permits the authorisation server to issue a refresh
     * token. That allows the application to refresh an expired access token
     * without requiring another interactive authorisation.
     */
    $client->setAccessType('offline');

    /*
     * Preserve previously granted scopes if more scopes are added later.
     */
    $client->setIncludeGrantedScopes(true);

    return $client;
}

/**
 * Loads and validates the binary token-encryption key.
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

    $key = base64_decode($encodedKey, true);

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
 * Encrypts a token bundle before database storage.
 *
 * The nonce is not secret and is stored immediately before the ciphertext.
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
 * Decrypts an OAuth token loaded from the database.
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
 * Persists the single installation's encrypted OAuth token bundle.
 *
 * @param array<string, mixed> $token
 */
function saveGoogleOAuthToken(
    mysqli $connection,
    array $token
): void {
    $accessToken = $token['access_token'] ?? null;

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

    /*
     * The second placeholder avoids the deprecated MySQL
     * VALUES(encrypted_token) construction.
     */
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO oauth_credentials
            (id, encrypted_token, updated_at)
         VALUES
            (1, ?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            encrypted_token = ?,
            updated_at = UTC_TIMESTAMP()'
    );

    mysqli_stmt_bind_param(
        $statement,
        'ss',
        $encryptedToken,
        $encryptedToken
    );

    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Loads the stored OAuth token.
 *
 * Returns null when this installation has not yet been authorised.
 *
 * @return array<string, mixed>|null
 */
function loadGoogleOAuthToken(
    mysqli $connection
): ?array {
    $statement = mysqli_prepare(
        $connection,
        'SELECT encrypted_token
           FROM oauth_credentials
          WHERE id = 1'
    );

    mysqli_stmt_execute($statement);

    /*
     * Initialise this as a string both for PHP static analysers and because
     * encrypted_token is defined as NOT NULL.
     */
    $encryptedToken = '';

    mysqli_stmt_bind_result(
        $statement,
        $encryptedToken
    );

    $fetched = mysqli_stmt_fetch($statement);

    mysqli_stmt_close($statement);

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
 * Removes the local OAuth credentials.
 */
function deleteGoogleOAuthToken(
    mysqli $connection
): void {
    $statement = mysqli_prepare(
        $connection,
        'DELETE FROM oauth_credentials
          WHERE id = 1'
    );

    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Returns a Google client with a valid access token.
 *
 * If the access token has expired, this refreshes it and persists the
 * resulting token bundle.
 */
function authorisedGoogleOAuthClient(
    mysqli $connection,
    string $redirectUri
): Client {
    $storedToken = loadGoogleOAuthToken(
        $connection
    );

    if ($storedToken === null) {
        throw new RuntimeException(
            'Google Health has not been authorised.'
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

    $refreshError = $refreshedToken['error'] ?? null;

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
     * Google may not repeat the refresh token in the refresh response.
     * Preserve the stored refresh token in that case.
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
        $refreshedToken
    );

    $client->setAccessToken(
        $refreshedToken
    );

    return $client;
}

/**
 * Returns whether this installation currently has stored credentials.
 *
 * This does not make a network request and does not prove that Google still
 * accepts the credentials.
 */
function hasStoredGoogleOAuthToken(
    mysqli $connection
): bool {
    return loadGoogleOAuthToken(
        $connection
    ) !== null;
}