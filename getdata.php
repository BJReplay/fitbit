<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-health.php';

$date = $_GET['date'] ?? null;

if (
    !is_string($date) ||
    !validateDate($date . ' 00:00:00')
) {
    exit('Date is not in a valid format. ' .
        'Please use YYYY-MM-DD format.');
}

$next = $date;

$requestedNext = $_GET['next'] ?? null;

if (
    is_string($requestedNext) &&
    validateDate($requestedNext . ' 00:00:00')
) {
    $next = $requestedNext;
}

$googleSub =
    $_SESSION['google_user_sub'] ??
    null;

if (
    !is_string($googleSub) ||
    $googleSub === ''
) {
    http_response_code(401);

    exit('You are not signed in. ' .
        '<a href="oauth-start.php">Sign in with Google</a>');
}

try {
    $result = googleHealthHeartRateDocument(
        $config_conn,
        $config_redirect_uri,
        $date,
        $googleSub
    );

    $healthUserId = $result['user'];
    $document = $result['document'];

    if (!is_string($healthUserId) || $healthUserId === '') {
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

    $statement = mysqli_prepare(
        $config_conn,
        'INSERT INTO data_raw
            (fbuser, fbdate, fbjsondata, dateadded)
         VALUES
            (?, ?, ?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            fbjsondata = VALUES(fbjsondata),
            dateadded = UTC_TIMESTAMP()'
    );

    mysqli_stmt_bind_param(
        $statement,
        'sss',
        $healthUserId,
        $date,
        $json
    );

    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
} catch (GoogleHealthIdentityMismatchException $exception) {
    error_log(
        sprintf(
            'Google Health identity mismatch for %s: %s',
            $date,
            $exception->getMessage()
        )
    );

    http_response_code(409);

    exit(
        'Google Health account mismatch. ' .
        'The connected Google Health account does not match the ' .
        'account associated with this application user. ' .
        'No health data was retrieved or stored. ' .
        '<a href="index.php">Return to the home page</a>.'
    );
} catch (
    JsonException |
    InvalidArgumentException |
    RuntimeException |
    UnexpectedValueException |
    mysqli_sql_exception $exception
) {
    error_log(
        sprintf(
            'Unable to retrieve/store Google Health data for %s: %s',
            $date,
            $exception->getMessage()
        )
    );

    http_response_code(502);

    exit('Unable to retrieve Google Health heart-rate data.');
}

mysqli_close($config_conn);

header(
    'Location: data.php?date=' .
        rawurlencode($next) .
        '&rand=' .
        random_int(0, PHP_INT_MAX)
);

exit;
