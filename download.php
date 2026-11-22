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
    exit(
        'Date is not valid. ' .
        'Please use YYYY-MM-DD format.'
    );
}

try {
    $healthUserId = googleHealthCurrentUserId(
        $config_conn,
        $config_redirect_uri
    );
} catch (
    JsonException |
    InvalidArgumentException |
    RuntimeException |
    UnexpectedValueException |
    mysqli_sql_exception $exception
) {
    error_log(
        'Unable to resolve Google Health identity for export: ' .
        $exception->getMessage()
    );

    http_response_code(401);

    exit(
        'Google Health is not connected. ' .
        '<a href="oauth-start.php">Connect Google Health</a>'
    );
}

$safeUserId = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    $healthUserId
);

if (
    !is_string($safeUserId) ||
    $safeUserId === ''
) {
    $safeUserId =
        'google-health-user';
}

$filename =
    'Google-Health-Intraday-Heartrate-' .
    $safeUserId .
    '-' .
    $date .
    '.xls';

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);

header(
    'Content-Type: application/vnd.ms-excel'
);

$dataset = [];

try {
    $statement = mysqli_prepare(
        $config_conn,
        'SELECT fbjsondata
           FROM data_raw
          WHERE fbdate = ?
            AND fbuser = ?
          LIMIT 1'
    );

    mysqli_stmt_bind_param(
        $statement,
        'ss',
        $date,
        $healthUserId
    );

    mysqli_stmt_execute(
        $statement
    );

    $json = '';

    mysqli_stmt_bind_result(
        $statement,
        $json
    );

    if (mysqli_stmt_fetch($statement)) {
        if ($json === '') {
            throw new UnexpectedValueException(
                'Stored heart-rate JSON is empty.'
            );
        }

        $series = json_decode(
            $json,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($series)) {
            throw new UnexpectedValueException(
                'Stored heart-rate JSON is not an object.'
            );
        }

        $dataset =
            $series['activities-heart-intraday']['dataset'] ??
            [];

        if (!is_array($dataset)) {
            throw new UnexpectedValueException(
                'The intraday dataset is not an array.'
            );
        }
    }

    mysqli_stmt_close(
        $statement
    );
} catch (
    JsonException |
    UnexpectedValueException |
    mysqli_sql_exception $exception
) {
    error_log(
        sprintf(
            'Unable to export Google Health data for %s: %s',
            $date,
            $exception->getMessage()
        )
    );

    $dataset = [];
}

?>
<table class="table table-bordered" id="hrdata">
    <thead>
        <tr>
            <th>Date &amp; Time</th>
            <th>Heart Rate</th>
        </tr>
    </thead>
    <tbody>
        <?php

        if ($dataset === []) {
            echo '<tr><td colspan="2">No data available</td></tr>' .
                "\n";
        }

        foreach ($dataset as $value) {
            if (!is_array($value)) {
                continue;
            }

            $time = htmlspecialchars(
                (string) ($value['time'] ?? ''),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $heartRate = htmlspecialchars(
                (string) ($value['value'] ?? ''),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $safeDate = htmlspecialchars(
                $date,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            echo
                '<tr><td>' .
                $safeDate .
                ' ' .
                $time .
                '</td><td>' .
                $heartRate .
                '</td></tr>' .
                "\n";
        }

        ?>
    </tbody>
</table>