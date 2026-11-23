<?php

declare(strict_types=1);

/**
 * Loads and decodes the stored heart-rate document for one user and date.
 *
 * Returns null when no stored row exists.
 *
 * @return array<string, mixed>|null
 */
function loadStoredHeartRateDocument(
    mysqli $connection,
    string $userId,
    string $date
): ?array {
    $statement = mysqli_prepare(
        $connection,
        'SELECT fbjsondata
           FROM data_raw
          WHERE fbdate = ?
            AND fbuser = ?
          LIMIT 1'
    );

    try {
        mysqli_stmt_bind_param(
            $statement,
            'ss',
            $date,
            $userId
        );

        mysqli_stmt_execute(
            $statement
        );

        $json = '';

        mysqli_stmt_bind_result(
            $statement,
            $json
        );

        if (!mysqli_stmt_fetch($statement)) {
            return null;
        }
    } finally {
        mysqli_stmt_close(
            $statement
        );
    }

    if ($json === '') {
        throw new UnexpectedValueException(
            'Stored heart-rate JSON is empty.'
        );
    }

    $document = json_decode(
        $json,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($document)) {
        throw new UnexpectedValueException(
            'Stored heart-rate JSON is not an object.'
        );
    }

    return $document;
}

/**
 * Extracts the intraday dataset from a stored heart-rate document.
 *
 * @param array<string, mixed> $document
 * @return list<array<string, mixed>>
 */
function storedHeartRateDataset(
    array $document
): array {
    $dataset =
        $document['activities-heart-intraday']['dataset'] ??
        [];

    if (!is_array($dataset)) {
        throw new UnexpectedValueException(
            'The intraday dataset is not an array.'
        );
    }

    return array_values(
        array_filter(
            $dataset,
            'is_array'
        )
    );
}

/**
 * Extracts the daily heart-rate summary from a stored document.
 *
 * @param array<string, mixed> $document
 * @return array<string, mixed>
 */
function storedHeartRateSummary(
    array $document
): array {
    $summary =
        $document['activities-heart'][0]['value'] ??
        [];

    if (!is_array($summary)) {
        return [];
    }

    return $summary;
}
