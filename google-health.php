<?php

declare(strict_types=1);

use Google\Client;

require_once __DIR__ . '/google-oauth.php';

const GOOGLE_HEALTH_API_BASE =
    'https://health.googleapis.com/v4';

const GOOGLE_HEALTH_HEART_RATE_PAGE_SIZE =
    10000;

/**
 * Returns the access token currently held by an authorised Google client.
 */
function googleHealthAccessToken(
    Client $client
): string {
    $token = $client->getAccessToken();

    $accessToken =
        $token['access_token'] ??
        null;

    if (
        !is_string($accessToken) ||
        $accessToken === ''
    ) {
        throw new RuntimeException(
            'No Google Health access token is available.'
        );
    }

    return $accessToken;
}

/**
 * Sends an authenticated GET request to Google Health and returns the
 * decoded JSON response.
 *
 * @param array<string, scalar> $query
 * @return array<string, mixed>
 */
function googleHealthGet(
    Client $client,
    string $url,
    array $query = []
): array {
    $response = $client->getHttpClient()->request(
        'GET',
        $url,
        [
            'headers' => [
                'Authorization' =>
                    'Bearer ' .
                    googleHealthAccessToken($client),

                'Accept' =>
                    'application/json',
            ],
            'query' => $query,
            'http_errors' => false,
        ]
    );

    $statusCode =
        $response->getStatusCode();

    $body =
        (string) $response->getBody();

    try {
        $decoded = json_decode(
            $body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        throw new RuntimeException(
            sprintf(
                'Google Health returned invalid JSON with HTTP status %d.',
                $statusCode
            ),
            0,
            $exception
        );
    }

    if (!is_array($decoded)) {
        throw new UnexpectedValueException(
            'Google Health returned JSON that is not an object.'
        );
    }

    if (
        $statusCode < 200 ||
        $statusCode >= 300
    ) {
        $error = $decoded['error'] ?? [];

        $message =
            is_array($error) &&
            isset($error['message']) &&
            is_string($error['message'])
                ? $error['message']
                : 'No error message returned.';

        $status =
            is_array($error) &&
            isset($error['status']) &&
            is_string($error['status'])
                ? $error['status']
                : 'UNKNOWN';

        throw new RuntimeException(
            sprintf(
                'Google Health request failed with HTTP %d, %s: %s',
                $statusCode,
                $status,
                $message
            )
        );
    }

    return $decoded;
}

/**
 * Gets the identity associated with the authorised Google Health account.
 *
 * @return array{name: string, legacyUserId: string, healthUserId: string}
 */
function googleHealthIdentity(
    Client $client
): array {
    $identity = googleHealthGet(
        $client,
        GOOGLE_HEALTH_API_BASE .
        '/users/me/identity'
    );

    $name =
        $identity['name'] ??
        null;

    $legacyUserId =
        $identity['legacyUserId'] ??
        null;

    $healthUserId =
        $identity['healthUserId'] ??
        null;

    if (
        !is_string($name) ||
        $name === '' ||
        !is_string($healthUserId) ||
        $healthUserId === ''
    ) {
        throw new UnexpectedValueException(
            'Google Health returned an incomplete identity.'
        );
    }

    /*
     * The legacy Fitbit ID may not exist for accounts that were not migrated
     * from Fitbit. Use the Google Health user ID as the local storage key in
     * that case.
     */
    if (
        !is_string($legacyUserId) ||
        $legacyUserId === ''
    ) {
        $legacyUserId = $healthUserId;
    }

    return [
        'name' => $name,
        'legacyUserId' => $legacyUserId,
        'healthUserId' => $healthUserId,
    ];
}

/**
 * Builds the Google Health civil-time filter for one calendar day.
 */
function googleHealthHeartRateDateFilter(
    string $date
): string {
    if (!validateDate($date . ' 00:00:00')) {
        throw new InvalidArgumentException(
            'The heart-rate date must have YYYY-MM-DD format.'
        );
    }

    $start = new DateTimeImmutable(
        $date . ' 00:00:00'
    );

    $end = $start->add(
        new DateInterval('P1D')
    );

    return sprintf(
        'heart_rate.sample_time.civil_time >= "%s" ' .
        'AND heart_rate.sample_time.civil_time < "%s"',
        $start->format('Y-m-d'),
        $end->format('Y-m-d')
    );
}

/**
 * Retrieves every page of Google Health heart-rate samples for one civil
 * calendar day.
 *
 * @return list<array<string, mixed>>
 */
function googleHealthHeartRateDataPoints(
    Client $client,
    string $date
): array {
    $url =
        GOOGLE_HEALTH_API_BASE .
        '/users/me/dataTypes/heart-rate/dataPoints';

    $filter =
        googleHealthHeartRateDateFilter(
            $date
        );

    $dataPoints = [];
    $pageToken = null;
    $seenPageTokens = [];

    do {
        $query = [
            'pageSize' =>
                GOOGLE_HEALTH_HEART_RATE_PAGE_SIZE,

            'filter' =>
                $filter,
        ];

        if (
            is_string($pageToken) &&
            $pageToken !== ''
        ) {
            $query['pageToken'] =
                $pageToken;
        }

        $page = googleHealthGet(
            $client,
            $url,
            $query
        );

        $pageDataPoints =
            $page['dataPoints'] ??
            [];

        if (!is_array($pageDataPoints)) {
            throw new UnexpectedValueException(
                'Google Health returned an invalid dataPoints value.'
            );
        }

        foreach ($pageDataPoints as $dataPoint) {
            if (is_array($dataPoint)) {
                $dataPoints[] = $dataPoint;
            }
        }

        $nextPageToken =
            $page['nextPageToken'] ??
            null;

        if (
            $nextPageToken === null ||
            $nextPageToken === ''
        ) {
            $pageToken = null;
            continue;
        }

        if (!is_string($nextPageToken)) {
            throw new UnexpectedValueException(
                'Google Health returned an invalid nextPageToken.'
            );
        }

        if (isset($seenPageTokens[$nextPageToken])) {
            throw new RuntimeException(
                'Google Health repeated a pagination token.'
            );
        }

        $seenPageTokens[$nextPageToken] = true;
        $pageToken = $nextPageToken;
    } while ($pageToken !== null);

    return $dataPoints;
}

/**
 * Extracts a local civil timestamp and numeric heart rate from one Google
 * Health data point.
 *
 * The returned timestamp is formatted as YYYY-MM-DD HH:MM:SS.
 *
 * @param array<string, mixed> $dataPoint
 * @return array{timestamp: string, time: string, value: int}|null
 */
function googleHealthHeartRateSample(
    array $dataPoint
): ?array {
    $heartRate =
        $dataPoint['heartRate'] ??
        null;

    if (!is_array($heartRate)) {
        return null;
    }

    $beatsPerMinute =
        $heartRate['beatsPerMinute'] ??
        null;

    if (!is_numeric($beatsPerMinute)) {
        return null;
    }

    $beatsPerMinute =
        (int) $beatsPerMinute;

    if (
        $beatsPerMinute <= 0 ||
        $beatsPerMinute > 300
    ) {
        return null;
    }

    $sampleTime =
        $heartRate['sampleTime'] ??
        null;

    if (!is_array($sampleTime)) {
        return null;
    }

    $civilTime =
        $sampleTime['civilTime'] ??
        null;

    if (!is_array($civilTime)) {
        return null;
    }

    $civilDate =
        $civilTime['date'] ??
        null;

    $civilClock =
        $civilTime['time'] ??
        null;

    if (
        !is_array($civilDate) ||
        !is_array($civilClock)
    ) {
        return null;
    }

    $year =
        $civilDate['year'] ??
        null;

    $month =
        $civilDate['month'] ??
        null;

    $day =
        $civilDate['day'] ??
        null;

    $hours =
        $civilClock['hours'] ??
        0;

    $minutes =
        $civilClock['minutes'] ??
        0;

    $seconds =
        $civilClock['seconds'] ??
        0;

    foreach (
        [
            $year,
            $month,
            $day,
            $hours,
            $minutes,
            $seconds,
        ] as $component
    ) {
        if (!is_int($component)) {
            return null;
        }
    }

    if (
        !checkdate($month, $day, $year) ||
        $hours < 0 ||
        $hours > 23 ||
        $minutes < 0 ||
        $minutes > 59 ||
        $seconds < 0 ||
        $seconds > 59
    ) {
        return null;
    }

    $date = sprintf(
        '%04d-%02d-%02d',
        $year,
        $month,
        $day
    );

    $time = sprintf(
        '%02d:%02d:%02d',
        $hours,
        $minutes,
        $seconds
    );

    return [
        'timestamp' =>
            $date . ' ' . $time,

        'time' =>
            $time,

        'value' =>
            $beatsPerMinute,
    ];
}

/**
 * Converts Google Health data points into the Fitbit intraday JSON shape
 * currently consumed by data.php and download.php.
 *
 * @param list<array<string, mixed>> $dataPoints
 * @return array<string, mixed>
 */
function googleHealthToLegacyFitbitHeartRate(
    string $date,
    array $dataPoints
): array {
    $samples = [];

    foreach ($dataPoints as $dataPoint) {
        $sample =
            googleHealthHeartRateSample(
                $dataPoint
            );

        if ($sample === null) {
            continue;
        }

        /*
         * The civil-time API filter should already constrain the date.
         * Preserve this check so an unexpected result cannot leak into
         * another day's stored record.
         */
        if (
            !str_starts_with(
                $sample['timestamp'],
                $date . ' '
            )
        ) {
            continue;
        }

        $samples[] = $sample;
    }

    usort(
        $samples,
        static function (
            array $left,
            array $right
        ): int {
            return strcmp(
                $left['timestamp'],
                $right['timestamp']
            );
        }
    );

    $dataset = [];

    foreach ($samples as $sample) {
        $dataset[] = [
            'time' =>
                $sample['time'],

            'value' =>
                $sample['value'],
        ];
    }

    return [
        'activities-heart' => [
            [
                'dateTime' => $date,
                'value' => [
                    /*
                     * Google Health heart-rate samples do not themselves
                     * provide the legacy daily resting-rate or zone summary.
                     * Those can be added later from the corresponding Google
                     * Health daily data types.
                     */
                    'heartRateZones' => [],
                ],
            ],
        ],

        'activities-heart-intraday' => [
            'dataset' =>
                $dataset,

            'datasetInterval' =>
                1,

            'datasetType' =>
                'second',
        ],
    ];
}

/**
 * Fetches one day of Google Health heart-rate data and returns both the
 * local storage key and the Fitbit-compatible JSON document.
 *
 * @return array{
 *     user: string,
 *     identity: array{
 *         name: string,
 *         legacyUserId: string,
 *         healthUserId: string
 *     },
 *     document: array<string, mixed>
 * }
 */
function googleHealthHeartRateDocument(
    mysqli $connection,
    string $redirectUri,
    string $date
): array {
    $client = authorisedGoogleOAuthClient(
        $connection,
        $redirectUri
    );

    $identity = googleHealthIdentity(
        $client
    );

    $dataPoints =
        googleHealthHeartRateDataPoints(
            $client,
            $date
        );

    return [
        'user' =>
            $identity['legacyUserId'],

        'identity' =>
            $identity,

        'document' =>
            googleHealthToLegacyFitbitHeartRate(
                $date,
                $dataPoints
            ),
    ];
}