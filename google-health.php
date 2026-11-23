<?php

declare(strict_types=1);

use Google\Client;

require_once __DIR__ . '/google-identity.php';
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
 * Sends an authenticated JSON POST request to Google Health.
 *
 * @param array<string, mixed> $requestBody
 * @return array<string, mixed>
 */
function googleHealthPost(
    Client $client,
    string $url,
    array $requestBody
): array {
    $accessToken = googleHealthAccessToken(
        $client
    );

    $httpClient = $client->getHttpClient();

    $response = $httpClient->request(
        'POST',
        $url,
        [
            'headers' => [
                'Authorization' =>
                'Bearer ' . $accessToken,

                'Accept' =>
                'application/json',

                'Content-Type' =>
                'application/json',
            ],

            'json' =>
            $requestBody,

            'http_errors' =>
            false,
        ]
    );

    $statusCode = $response->getStatusCode();

    $body = (string) $response->getBody();

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
 * Returns the healthUserId for the currently authorised Google Health
 * account.
 *
 * The value is cached in the PHP session after it has been verified
 * through the Google Health identity endpoint.
 */
function googleHealthCurrentUserId(
    mysqli $connection,
    string $redirectUri,
    string $googleSub
): string {

    $healthUserId = googleHealthUserIdForGoogleSub(
        $connection,
        $googleSub
    );

    if ($healthUserId !== null) {
        return $healthUserId;
    }

    $client = authorisedGoogleOAuthClient(
        $connection,
        $redirectUri,
        $googleSub
    );

    $identity = googleHealthIdentity(
        $client
    );

    $healthUserId =
        $identity['healthUserId'];

    saveGoogleHealthUserId(
        $connection,
        $googleSub,
        $healthUserId
    );

    return $healthUserId;
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
 * Builds a filter for a single daily Google Health record.
 */
function googleHealthDailyDateFilter(
    string $filterName,
    string $date
): string {
    if (!validateDate($date . ' 00:00:00')) {
        throw new InvalidArgumentException(
            'The Google Health daily date is invalid.'
        );
    }

    $nextDate = (new DateTimeImmutable($date))
        ->add(new DateInterval('P1D'))
        ->format('Y-m-d');

    return sprintf(
        '%s.date >= "%s" AND %s.date < "%s"',
        $filterName,
        $date,
        $filterName,
        $nextDate
    );
}

/**
 * Retrieves the daily resting heart rate.
 */
function googleHealthDailyRestingHeartRate(
    Client $client,
    string $date
): ?int {
    $response = googleHealthGet(
        $client,
        GOOGLE_HEALTH_API_BASE .
            '/users/me/dataTypes/' .
            'daily-resting-heart-rate/dataPoints',
        [
            'pageSize' => 10,
            'filter' => googleHealthDailyDateFilter(
                'daily_resting_heart_rate',
                $date
            ),
        ]
    );

    $dataPoints = $response['dataPoints'] ?? [];

    if (!is_array($dataPoints)) {
        throw new UnexpectedValueException(
            'Google Health returned invalid resting heart-rate data.'
        );
    }

    foreach ($dataPoints as $dataPoint) {
        if (!is_array($dataPoint)) {
            continue;
        }

        $daily = $dataPoint['dailyRestingHeartRate'] ?? null;

        if (!is_array($daily)) {
            continue;
        }

        $beatsPerMinute = $daily['beatsPerMinute'] ?? null;

        if (!is_numeric($beatsPerMinute)) {
            continue;
        }

        $beatsPerMinute = (int) $beatsPerMinute;

        if (
            $beatsPerMinute < 1 ||
            $beatsPerMinute > 300
        ) {
            continue;
        }

        return $beatsPerMinute;
    }

    return null;
}

/**
 * Converts a Google Health zone type into a display name.
 */
function googleHealthHeartRateZoneName(
    string $zoneType
): string {
    return match ($zoneType) {
        'LIGHT' =>
        'Light',

        'MODERATE' =>
        'Moderate',

        'VIGOROUS' =>
        'Vigorous',

        'PEAK' =>
        'Peak',

        default =>
        'Unknown',
    };
}

/**
 * Retrieves the daily Google Health heart-rate zone thresholds.
 *
 * Google Health provides zone type and min/max BPM here. It does not
 * provide the legacy Fitbit minutes or calories values in this record.
 *
 * @return list<array{
 *     name: string,
 *     minutes: int,
 *     min: int,
 *     max: int
 * }>
 */
function googleHealthDailyHeartRateZones(
    Client $client,
    string $date,
    array $zoneMinutes
): array {
    $response = googleHealthGet(
        $client,
        GOOGLE_HEALTH_API_BASE .
            '/users/me/dataTypes/' .
            'daily-heart-rate-zones/dataPoints',
        [
            'pageSize' => 10,
            'filter' => googleHealthDailyDateFilter(
                'daily_heart_rate_zones',
                $date
            ),
        ]
    );

    $dataPoints = $response['dataPoints'] ?? [];

    if (!is_array($dataPoints)) {
        throw new UnexpectedValueException(
            'Google Health returned invalid heart-rate zone data.'
        );
    }

    foreach ($dataPoints as $dataPoint) {
        if (!is_array($dataPoint)) {
            continue;
        }

        $daily = $dataPoint['dailyHeartRateZones'] ?? null;

        if (!is_array($daily)) {
            continue;
        }

        $googleZones = $daily['heartRateZones'] ?? [];

        if (!is_array($googleZones)) {
            continue;
        }

        $zones = [];

        foreach ($googleZones as $googleZone) {
            if (!is_array($googleZone)) {
                continue;
            }

            $zoneType =
                $googleZone['heartRateZoneType'] ??
                null;

            $min =
                $googleZone['minBeatsPerMinute'] ??
                null;

            $max =
                $googleZone['maxBeatsPerMinute'] ??
                null;

            if (
                !is_string($zoneType) ||
                !is_numeric($min) ||
                !is_numeric($max)
            ) {
                continue;
            }

            $zones[] = [
                'name' =>
                googleHealthHeartRateZoneName(
                    $zoneType
                ),

                'minutes' =>
                $zoneMinutes[$zoneType] ?? 0,

                'min' =>
                (int) $min,

                'max' =>
                (int) $max,
            ];
        }

        return $zones;
    }

    return [];
}

/**
 * Converts a Google protobuf duration string to whole minutes.
 *
 * Examples:
 *  "49320s" -> 822
 *  "3.5s"   -> 0
 */
function googleHealthDurationMinutes(
    string $duration
): int {
    if (
        preg_match(
            '/^([0-9]+(?:\.[0-9]+)?)s$/',
            $duration,
            $matches
        ) !== 1
    ) {
        throw new UnexpectedValueException(
            'Google Health returned an invalid duration.'
        );
    }

    $seconds = (float) $matches[1];

    return (int) floor(
        $seconds / 60
    );
}

/**
 * Retrieves the total minutes spent in each heart-rate zone for one
 * civil calendar day.
 *
 * @return array<string, int>
 */
function googleHealthDailyZoneMinutes(
    Client $client,
    string $date
): array {
    if (!validateDate($date . ' 00:00:00')) {
        throw new InvalidArgumentException(
            'The Google Health daily date is invalid.'
        );
    }

    $startDate = new DateTimeImmutable(
        $date
    );

    $endDate = $startDate->add(
        new DateInterval('P1D')
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

    /*
     * Initialise every known zone to zero. Google may omit a zone when
     * no time was recorded in it.
     */
    $minutes = [
        'LIGHT' => 0,
        'MODERATE' => 0,
        'VIGOROUS' => 0,
        'PEAK' => 0,
    ];

    $rollupDataPoints =
        $response['rollupDataPoints'] ??
        [];

    if (!is_array($rollupDataPoints)) {
        throw new UnexpectedValueException(
            'Google Health returned invalid zone-duration data.'
        );
    }

    foreach ($rollupDataPoints as $rollupDataPoint) {
        if (!is_array($rollupDataPoint)) {
            continue;
        }

        $rollup =
            $rollupDataPoint['timeInHeartRateZone'] ??
            null;

        if (!is_array($rollup)) {
            continue;
        }

        $zones =
            $rollup['timeInHeartRateZones'] ??
            [];

        if (!is_array($zones)) {
            continue;
        }

        foreach ($zones as $zone) {
            if (!is_array($zone)) {
                continue;
            }

            $zoneType =
                $zone['heartRateZone'] ??
                null;

            $duration =
                $zone['duration'] ??
                null;

            if (
                !is_string($zoneType) ||
                !is_string($duration) ||
                !array_key_exists(
                    $zoneType,
                    $minutes
                )
            ) {
                continue;
            }

            $minutes[$zoneType] =
                googleHealthDurationMinutes(
                    $duration
                );
        }
    }

    return $minutes;
}

/**
 * Wraps Google Health heart-rate data in the legacy JSON structure
 * consumed by data.php and download.php.
 *
 * @param list<array{time: string, value: int}> $dataset
 * @param list<array{name: string, minutes: int, min: int, max: int}> $heartRateZones
 * @return array<string, mixed>
 */
function googleHealthLegacyHeartRateDocument(
    string $date,
    array $dataset,
    ?int $restingHeartRate,
    array $heartRateZones
): array {
    $summary = [
        'heartRateZones' =>
        $heartRateZones,
    ];

    if ($restingHeartRate !== null) {
        $summary['restingHeartRate'] =
            $restingHeartRate;
    }

    return [
        'activities-heart' => [
            [
                'dateTime' =>
                $date,

                'value' =>
                $summary,
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
 * Fetches one day of Google Health heart-rate data.
 */
function googleHealthHeartRateDocument(
    mysqli $connection,
    string $redirectUri,
    string $date,
    string $googleSub

): array {
    $client = authorisedGoogleOAuthClient(
        $connection,
        $redirectUri,
        $googleSub
    );

    $identity = googleHealthIdentity(
        $client
    );

    $healthUserId =
        $identity['healthUserId'];

    $expectedHealthUserId =
        googleHealthUserIdForGoogleSub(
            $connection,
            $googleSub
        );

    if ($expectedHealthUserId === null) {
        saveGoogleHealthUserId(
            $connection,
            $googleSub,
            $healthUserId
        );
    } elseif (!hash_equals(
        $expectedHealthUserId,
        $healthUserId
    )) {
        throw new RuntimeException(
            'Google Health identity does not match ' .
                'the authenticated application user.'
        );
    }

    $dataset = googleHealthHeartRateDataset(
        $client,
        $date
    );

    $restingHeartRate =
        googleHealthDailyRestingHeartRate(
            $client,
            $date
        );

    $zoneMinutes =
        googleHealthDailyZoneMinutes(
            $client,
            $date
        );

    $heartRateZones =
        googleHealthDailyHeartRateZones(
            $client,
            $date,
            $zoneMinutes
        );

    return [
        'user' =>
        $healthUserId,

        'identity' =>
        $identity,

        'document' =>
        googleHealthLegacyHeartRateDocument(
            $date,
            $dataset,
            $restingHeartRate,
            $heartRateZones
        ),
    ];
}
