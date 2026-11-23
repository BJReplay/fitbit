<?php

declare(strict_types=1);

/*
 * Centralised external runtime and other URLs.
 *
 * Keep literal external URLs in this file so application source files
 * can refer to named constants/helpers without duplicating endpoints.
 */

const GOOGLE_HEALTH_API_BASE =
    'https://health.googleapis.com/v4';

const GOOGLE_HEALTH_READ_SCOPE =
    'https://www.googleapis.com/auth/' .
    'googlehealth.health_metrics_and_measurements.readonly';

const GOOGLE_HEALTH_ACTIVITY_READ_SCOPE =
    'https://www.googleapis.com/auth/' .
    'googlehealth.activity_and_fitness.readonly';

const PROJECT_SOURCE_URL =
    'https://github.com/BJReplay/fitbit';

/*
 * Builds an HTTPS application URL for the configured hostname.
 */
function applicationHttpsUrl(
    string $host,
    string $path
): string {
    $host = trim($host);

    if ($host === '') {
        throw new InvalidArgumentException(
            'The application hostname must not be empty.'
        );
    }

    if (
        $path === '' ||
        !str_starts_with($path, '/')
    ) {
        throw new InvalidArgumentException(
            'The application URL path must start with "/".'
        );
    }

    return 'https://' .
        $host .
        $path;
}