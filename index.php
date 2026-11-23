<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-health.php';

$connected = false;
$healthUserId = '';
$connectionError = '';

$googleSub =
    $_SESSION['google_user_sub'] ??
    null;

$signedIn =
    is_string($googleSub) &&
    $googleSub !== '';

try {
    if (
        $signedIn &&
        hasStoredGoogleOAuthToken(
            $config_conn,
            $googleSub
        )
    ) {
        $healthUserId = googleHealthCurrentUserId(
            $config_conn,
            $config_redirect_uri,
            $googleSub
        );

        $connected = true;
    }
} catch (Throwable $exception) {
    error_log(
        'Unable to initialise Google Health connection: '
            . $exception->getMessage()
    );

    $connectionError =
        'The stored Google Health connection could not be verified.';
}

$safeHealthUserId = htmlspecialchars(
    $healthUserId,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$safeConnectionError = htmlspecialchars(
    $connectionError,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$logoutToken = bin2hex(random_bytes(32));
$_SESSION['google_logout_token'] = $logoutToken;

$disconnectToken = bin2hex(random_bytes(32));
$_SESSION['google_disconnect_token'] = $disconnectToken;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>Google Health Heart Rate Intraday Data</title>

    <link
        rel="stylesheet"
        href="/assets/css/bootstrap-3.3.7.min.css">

    <link
        rel="stylesheet"
        href="/assets/css/fitbit.css">

    <link
        rel="icon"
        href="/assets/favicon32.ico"
        type="image/x-icon">
</head>

<body>

    <nav class="navbar navbar-inverse navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <a class="navbar-brand" href="./">
                    <strong>
                        Google Health Heart Rate Intraday Data
                    </strong>
                </a>
            </div>
        </div>
    </nav>

    <div
        class="navbar-spacer"
        aria-hidden="true"></div>

    <div class="container">

        <div class="row">

            <div class="col-md-4">

                <div class="well">

                    <?php if (!$signedIn) { ?>

                        <h4>
                            <span class="label label-default">
                                Signed Out
                            </span>
                        </h4>

                        <p>
                            No Google user is signed in to this browser.
                        </p>

                        <p>
                            <a
                                class="btn btn-primary"
                                href="oauth-start.php">
                                Sign in with Google
                            </a>
                        </p>

                    <?php } elseif (!$connected) { ?>

                        <h4>
                            <span class="label label-default">
                                Google Health Not Connected
                            </span>
                        </h4>

                        <?php if ($safeConnectionError !== '') { ?>

                            <div
                                class="alert alert-warning"
                                role="alert">
                                <?php echo $safeConnectionError; ?>
                            </div>

                        <?php } ?>

                        <p>
                            You are signed in, but Google Health is not
                            connected for this account.
                        </p>

                        <p>
                            <a
                                class="btn btn-primary"
                                href="oauth-start.php">
                                Connect Google Health
                            </a>
                        </p>

                        <form
                            action="logout.php"
                            method="post">

                            <input
                                type="hidden"
                                name="token"
                                value="<?php echo htmlspecialchars(
                                            $logoutToken,
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ); ?>">

                            <button
                                class="btn btn-default"
                                type="submit">
                                Log out from this browser
                            </button>

                        </form>

                    <?php } else { ?>

                        <h4>
                            <span class="label label-success">
                                Google Health Connected
                            </span>
                        </h4>

                        <p>
                            This account is connected to Google Health.
                        </p>

                        <dl>
                            <dt>Google Health User ID</dt>
                            <dd>
                                <?php echo $safeHealthUserId; ?>
                            </dd>
                        </dl>

                        <p>
                            <a
                                class="btn btn-primary"
                                href="data.php">
                                View Heart Rate Data
                            </a>
                        </p>

                        <p>
                            <a
                                class="btn btn-default"
                                href="oauth-start.php">
                                Reconnect Google Health
                            </a>
                        </p>

                        <form
                            action="disconnect.php"
                            method="post">

                            <input
                                type="hidden"
                                name="token"
                                value="<?php echo htmlspecialchars(
                                            $disconnectToken,
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ); ?>">

                            <button
                                class="btn btn-warning"
                                type="submit">
                                Disconnect Google Health
                            </button>

                        </form>

                        <form
                            action="logout.php"
                            method="post">

                            <input
                                type="hidden"
                                name="token"
                                value="<?php echo htmlspecialchars(
                                            $logoutToken,
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ); ?>">

                            <button
                                class="btn btn-default"
                                type="submit">
                                Log out from this browser
                            </button>

                        </form>

                    <?php } ?>

                </div>

            </div>

            <div class="col-md-8">

                <h4>
                    About Google Health Intraday Heart Rate Data
                </h4>

                <p>
                    This application retrieves detailed heart-rate
                    measurements from Google Health and provides tools
                    to review and export the data.
                </p>

                <p>You can:</p>

                <ul>
                    <li>
                        Retrieve detailed intraday heart-rate data
                        from Google Health.
                    </li>

                    <li>
                        View the data on an interactive chart.
                    </li>

                    <li>
                        Review individual heart-rate measurements.
                    </li>

                    <li>
                        Export stored heart-rate data.
                    </li>
                </ul>

                <p>
                    The project is open source and available at
                    <a href="<?php echo PROJECT_SOURCE_URL; ?>">
                        <?php echo PROJECT_SOURCE_URL; ?>
                    </a>.
                </p>

                <hr>

                <h4>Privacy Information</h4>

                <p>
                    Heart-rate information is sensitive health data.
                    This application uses Google OAuth to request
                    permission to access your Google Health data.
                </p>

                <ul>
                    <li>
                        Your Google password is not provided to this
                        application.
                    </li>

                    <li>
                        OAuth credentials are stored encrypted by this
                        installation.
                    </li>

                    <li>
                        Retrieved heart-rate data is stored in the
                        configured database so historical data can
                        be reviewed.
                    </li>

                    <li>
                        Access can be removed through your Google
                        account.
                    </li>

                    <li>
                        You can run your own instance and retain
                        control of its database and configuration.
                    </li>
                </ul>

            </div>

        </div>

        <hr>

        <p>
            Originally based on an example by Vivek Kapoor.
        </p>

    </div>

</body>

</html>
