<?php

declare(strict_types=1);

use Google\Client;

require_once __DIR__ . '/google-oauth.php';

const GOOGLE_HEALTH_API_BASE =
    'https://health.googleapis.com/v4';

const GOOGLE_HEALTH_HEART_RATE_PAGE_SIZE =
    1000;

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
    $response = $client
        ->getHttpClient()
        ->request(
            'GET',
            $url,
            [
                'headers' => [
                    'Authorization' =>
                        'Bearer ' .
                        googleHealthAccessToken(
                            $client
                        ),

                    'Accept' =>
                        'application/json',
                ],

                'query' =>
                    $query,

                /*
                 * Preserve non-success responses so that the Google error
                 * body can be inspected and included in our exception.
                 */
                'http_errors' =>
                    false,
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
        $error =
            $decoded['error'] ??
            [];

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
 * The legacy Fitbit ID is used as the local storage key when available,
 * allowing existing data_raw records to remain associated with the same
 * user identifier.
 *
 * @return array{
 *     name: string,
 *     legacyUserId: string,
 *     healthUserId: string
 * }
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
     * Accounts not migrated from legacy Fitbit might not have a legacy
     * Fitbit user ID. Use the Google Health user ID as the local key in
     * that situation.
     */
    if (
        !is_string($legacyUserId) ||
        $legacyUserId === ''
    ) {
        $legacyUserId =
            $healthUserId;
    }

    return [
        'name' =>
            $name,

        'legacyUserId' =>
            $legacyUserId,

        'healthUserId' =>
            $healthUserId,
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
 * Extracts a civil timestamp and numeric heart rate from one Google
 * Health data point.
 *
 * Google returns beatsPerMinute as a string, so it is explicitly
 * converted to an integer.
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

    /*
     * Ignore impossible or obviously malformed heart-rate readings.
     */
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
        !checkdate(
            $month,
            $day,
            $year
        ) ||
        $hours < 0 ||
        $hours > 23 ||
        $minutes < 0 ||
        $minutes > 59 ||
        $seconds < 0 ||
        $seconds > 59
    ) {
        return null;
    }

    $civilDateString = sprintf(
        '%04d-%02d-%02d',
        $year,
        $month,
        $day
    );

    $civilTimeString = sprintf(
        '%02d:%02d:%02d',
        $hours,
        $minutes,
        $seconds
    );

    return [
        'timestamp' =>
            $civilDateString .
            ' ' .
            $civilTimeString,

        'time' =>
            $civilTimeString,

        'value' =>
            $beatsPerMinute,
    ];
}

/**
 * Retrieves all Google Health heart-rate samples for one civil calendar
 * day and immediately converts every page into the compact legacy dataset
 * consumed by data.php and download.php.
 *
 * Raw Google Health data points are discarded after each page. Only the
 * compact time/value rows survive into the next iteration.
 *
 * @return list<array{time: string, value: int}>
 */
function googleHealthHeartRateDataset(
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

    $dataset = [];
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
            if (!is_array($dataPoint)) {
                continue;
            }

            $sample =
                googleHealthHeartRateSample(
                    $dataPoint
                );

            if ($sample === null) {
                continue;
            }

            /*
             * The API filter should already restrict results to the
             * requested civil date. Keep this defensive check so that
             * an unexpected result cannot be stored under another day.
             */
            if (
                !str_starts_with(
                    $sample['timestamp'],
                    $date . ' '
                )
            ) {
                continue;
            }

            /*
             * Retain only the fields required by the existing chart and
             * export code.
             */
            $dataset[] = [
                'time' =>
                    $sample['time'],

                'value' =>
                    $sample['value'],
            ];
        }

        $nextPageToken =
            $page['nextPageToken'] ??
            null;

        /*
         * Release the verbose decoded Google response before requesting
         * another page.
         */
        unset(
            $pageDataPoints,
            $page
        );

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

        $seenPageTokens[$nextPageToken] =
            true;

        $pageToken =
            $nextPageToken;
    } while ($pageToken !== null);

    /*
     * Google Health list results are returned newest first. The existing
     * chart and table expect chronological order.
     */
    return array_reverse(
        $dataset
    );
}

/**
 * Wraps an already compact heart-rate dataset in the legacy Fitbit JSON
 * structure consumed by data.php and download.php.
 *
 * @param list<array{time: string, value: int}> $dataset
 * @return array<string, mixed>
 */
function googleHealthLegacyHeartRateDocument(
    string $date,
    array $dataset
): array {
    return [
        'activities-heart' => [
            [
                'dateTime' =>
                    $date,

                'value' => [
                    /*
                     * Daily resting heart rate and zone summaries are not
                     * included in raw Google Health heart-rate samples.
                     * They can be added later from their daily data types.
                     */
                    'heartRateZones' =>
                        [],
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
 * local storage key and the Fitbit-compatible document.
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

    $dataset =
        googleHealthHeartRateDataset(
            $client,
            $date
        );

    return [
        'user' =>
            $identity['legacyUserId'],

        'identity' =>
            $identity,

        'document' =>
            googleHealthLegacyHeartRateDocument(
                $date,
                $dataset
            ),
    ];
}