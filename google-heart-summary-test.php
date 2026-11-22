<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-health.php';

$date = $_GET['date'] ?? date('Y-m-d');

if (
    !is_string($date) ||
    !validateDate($date . ' 00:00:00')
) {
    http_response_code(400);
    exit('Invalid date.');
}

try {
    $client = authorisedGoogleOAuthClient(
        $config_conn,
        $config_redirect_uri
    );

    $nextDate = (new DateTimeImmutable($date))
        ->add(new DateInterval('P1D'))
        ->format('Y-m-d');

    $restingHeartRate = googleHealthGet(
        $client,
        GOOGLE_HEALTH_API_BASE .
        '/users/me/dataTypes/daily-resting-heart-rate/dataPoints',
        [
            'pageSize' => 10,
            'filter' =>
                'daily_resting_heart_rate.date >= "' .
                $date .
                '" AND daily_resting_heart_rate.date < "' .
                $nextDate .
                '"',
        ]
    );

    $heartRateZones = googleHealthGet(
        $client,
        GOOGLE_HEALTH_API_BASE .
        '/users/me/dataTypes/daily-heart-rate-zones/dataPoints',
        [
            'pageSize' => 10,
            'filter' =>
                'daily_heart_rate_zones.date >= "' .
                $date .
                '" AND daily_heart_rate_zones.date < "' .
                $nextDate .
                '"',
        ]
    );

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'date' => $date,
            'restingHeartRate' => $restingHeartRate,
            'heartRateZones' => $heartRateZones,
        ],
        JSON_THROW_ON_ERROR |
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    error_log(
        'Google Health heart summary test failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'error' =>
                'Google Health heart summary test failed.',

            'message' =>
                $exception->getMessage(),
        ],
        JSON_THROW_ON_ERROR |
        JSON_PRETTY_PRINT
    );
}