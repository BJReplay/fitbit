<?php

declare(strict_types=1);

/**
 * Returns whether a date exactly matches the requested format.
 */
function validateDate(
    string $date,
    string $format = 'Y-m-d H:i:s'
): bool {
    $parsedDate = DateTimeImmutable::createFromFormat(
        $format,
        $date
    );

    return
        $parsedDate !== false &&
        $parsedDate->format($format) === $date;
}
