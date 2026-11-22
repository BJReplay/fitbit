<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-health.php';

$date =
    $_GET['date'] ??
    date('Y-m-d');

if (
    !is_string($date) ||
    !validateDate($date . ' 00:00:00')
) {
    http_response_code(400);
    exit('Invalid date.');
}

$startDate =
    new DateTimeImmutable(
        $date
    );

$endDate =
    $startDate->add(
        new DateInterval('P1D')
    );

try {
    $client = authorisedGoogleOAuthClient(
        $config_conn,
        $config_redirect_uri
    );

    $response = googleHealthPost(
        $client,
        GOOGLE_HEALTH_API_BASE .
        '/users/me/dataTypes/' .
        'time-in-heart-rate-zone/' .
        'dataPoints:dailyRollUp',
        [
            'range' => [
                'start' => [
                    'date' => [
                        'year' =>
                            (int) $startDate->format('Y'),

                        'month' =>
                            (int) $startDate->format('m'),

                        'day' =>
                            (int) $startDate->format('d'),
                    ],

                    'time' => [
                        'hours' => 0,
                        'minutes' => 0,
                        'seconds' => 0,
                    ],
                ],

                'end' => [
                    'date' => [
                        'year' =>
                            (int) $endDate->format('Y'),

                        'month' =>
                            (int) $endDate->format('m'),

                        'day' =>
                            (int) $endDate->format('d'),
                    ],

                    'time' => [
                        'hours' => 0,
                        'minutes' => 0,
                        'seconds' => 0,
                    ],
                ],
            ],

            'windowSizeDays' => 1,
            'pageSize' => 10,

            'dataSourceFamily' =>
                'users/me/dataSourceFamilies/all-sources',
        ]
    );

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'date' =>
                $date,

            'zoneDurationRollup' =>
                $response,
        ],
        JSON_THROW_ON_ERROR |
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    error_log(
        'Google Health zone-duration test failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        [
            'error' =>
                'Google Health zone-duration test failed.',

            'message' =>
                $exception->getMessage(),
        ],
        JSON_THROW_ON_ERROR |
        JSON_PRETTY_PRINT
    );
}