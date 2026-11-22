<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-health.php';

/*
 * Validate the requested date.
 */
$date =
    $_GET['date'] ??
    null;

if (
    !is_string($date) ||
    !validateDate($date . ' 00:00:00')
) {
    exit(
        'Date is not in a valid format. ' .
        'Please use YYYY-MM-DD format.'
    );
}

/*
 * Preserve the existing fetch-and-next behaviour.
 */
$next = $date;

$requestedNext =
    $_GET['next'] ??
    null;

if (
    is_string($requestedNext) &&
    validateDate($requestedNext . ' 00:00:00')
) {
    $next = $requestedNext;
}

try {
    $result = googleHealthHeartRateDocument(
        $config_conn,
        $config_redirect_uri,
        $date
    );

    $user =
        $result['user'];

    $document =
        $result['document'];

    if (
        !is_string($user) ||
        $user === ''
    ) {
        throw new UnexpectedValueException(
            'Google Health returned no usable user identifier.'
        );
    }

    if (!is_array($document)) {
        throw new UnexpectedValueException(
            'The Google Health adapter returned no document.'
        );
    }

    $json = json_encode(
        $document,
        JSON_THROW_ON_ERROR |
        JSON_UNESCAPED_SLASHES
    );

    /*
     * Keep the established session key temporarily so data.php and
     * download.php continue working without a simultaneous rewrite.
     */
    $_SESSION['fb_client_id'] =
        $user;

    mysqli_begin_transaction(
        $config_conn
    );

    $delete = mysqli_prepare(
        $config_conn,
        'DELETE FROM data_raw
          WHERE fbuser = ?
            AND fbdate = ?'
    );

    mysqli_stmt_bind_param(
        $delete,
        'ss',
        $user,
        $date
    );

    mysqli_stmt_execute(
        $delete
    );

    mysqli_stmt_close(
        $delete
    );

    $insert = mysqli_prepare(
        $config_conn,
        'INSERT INTO data_raw
            (fbuser, fbdate, fbjsondata)
         VALUES
            (?, ?, ?)'
    );

    mysqli_stmt_bind_param(
        $insert,
        'sss',
        $user,
        $date,
        $json
    );

    mysqli_stmt_execute(
        $insert
    );

    mysqli_stmt_close(
        $insert
    );

    mysqli_commit(
        $config_conn
    );
} catch (
    JsonException |
    InvalidArgumentException |
    RuntimeException |
    UnexpectedValueException |
    mysqli_sql_exception $exception
) {
    try {
        mysqli_rollback(
            $config_conn
        );
    } catch (mysqli_sql_exception) {
        // No active transaction, or the connection is unavailable.
    }

    error_log(
        sprintf(
            'Unable to retrieve/store Google Health data for %s: %s',
            $date,
            $exception->getMessage()
        )
    );

    http_response_code(502);

    exit(
        'Unable to retrieve Google Health heart-rate data.'
    );
}

mysqli_close(
    $config_conn
);

header(
    'Location: data.php?date=' .
    rawurlencode($next) .
    '&rand=' .
    random_int(0, PHP_INT_MAX)
);

exit;