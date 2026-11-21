<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-oauth.php';

try {
    $client = authorisedGoogleOAuthClient(
        $config_conn,
        $config_redirect_uri
    );

    $token = $client->getAccessToken();

    $accessToken = $token['access_token'] ?? null;

    if (!is_string($accessToken) || $accessToken === '') {
        throw new RuntimeException(
            'No Google OAuth access token is available.'
        );
    }

    $httpClient = $client->getHttpClient();

    $response = $httpClient->request(
        'GET',
        'https://health.googleapis.com/v4/users/me/identity',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]
    );

    $statusCode = $response->getStatusCode();
    $body = (string) $response->getBody();

    $responseData = json_decode(
        $body,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'status' => $statusCode,
            'response' => $responseData,
        ],
        JSON_THROW_ON_ERROR
        | JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    error_log(
        'Google Health identity test failed: '
        . $exception->getMessage()
    );

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'error' => 'Google Health identity test failed.',
        ],
        JSON_THROW_ON_ERROR
        | JSON_PRETTY_PRINT
    );
}