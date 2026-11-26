# Google Health Heart Rate Intraday Data

This application retrieves detailed heart-rate data from the Google Health API, stores it locally, and provides a browser-based chart, daily heart-rate summary, navigation and export.

It began as a Fitbit intraday heart-rate project and retains some legacy Fitbit-compatible database column names and JSON structure so existing historical data can continue to be used alongside newly fetched Google Health data.

## Why was this created?

This project was inspired by <https://<scrubbed-url-domain>/technotablet/fitbit> by my need to track SVT that had not responded to two rounds of ablation and became more problematic post-COVID.

The high-resolution heart-rate data provides a useful long-term record that can be reviewed alongside clinical care. It is not a replacement for clinical monitoring such as a Holter monitor or ECG.

The application was subsequently updated as the Fitbit API was replaced by Google Health. It now fetches heart-rate data through Google Health while preserving compatibility with the application's existing stored data.

## Features

- Google OpenID Connect sign-in and per-user Google OAuth authorisation.
- Encrypted per-user local OAuth token storage.
- Multiple application users with isolated Google Health identities and stored heart-rate data.
- Detailed Google Health intraday heart-rate retrieval.
- Daily resting heart rate.
- Heart-rate zone thresholds and minutes spent in each zone.
- Interactive Highcharts display.
- Installable mobile PWA with a compact, touch-friendly heart-rate view.
- Previous-day, next-day, refresh, and Fetch and Next navigation.
- Export of stored intraday heart-rate data to an Excel-compatible `.xls` file.
- Persistent application sessions with separate browser logout and Google Health disconnect actions.
- Locally hosted application assets and a restrictive Content Security Policy.
- Docker build support for Composer dependencies with source bind-mounted at runtime in the supplied Compose example.

## Requirements

- PHP 8.6 or a compatible PHP version satisfying `^8.6`.
- MySQL/MariaDB-compatible database access through `mysqli`.
- HTTPS access to the application.
- A Google OAuth client configured for the application's callback URL.
- Composer dependencies from `composer.lock`.

The PHP dependency and Google API client requirements are defined in `composer.json`.

## Google OAuth configuration

The application expects these environment variables:

- `WEBSITE_HOSTNAME`
- `GOOGLE_OAUTH_CLIENT_ID`
- `GOOGLE_OAUTH_CLIENT_SECRET`
- `GOOGLE_OAUTH_ENCRYPTION_KEY`
- `MYSQL_HOST`
- `MYSQL_PORT`
- `MYSQL_DATABASE`
- `MYSQL_USER`
- `MYSQL_PASSWORD`

The Docker Compose example also uses the additional MySQL environment variables shown in `.env.fitbit.sample`.

`GOOGLE_OAUTH_ENCRYPTION_KEY` must be a Base64-encoded 32-byte key. It is used to encrypt the OAuth token bundle before it is stored in the database.

The OAuth callback URL is derived from `WEBSITE_HOSTNAME` and ends in `/token.php`.

## Database

For a new installation, create the application tables in this order:

1. `sql/default.sql` for stored heart-rate data.
2. `sql/users.sql` for Google-authenticated application users and their Google Health identity mapping.
3. `sql/google-oauth.sql` for encrypted per-user OAuth credentials.

`sql/google-oauth.sql` references the `users` table, so `sql/users.sql` must be applied first.

The `data_raw` table retains its historical `fbdate`, `fbuser`, and `fbjsondata` column names for compatibility. Newly retrieved Google Health heart-rate data is mapped into the application's established stored document format, allowing existing display and export functionality to work with both migrated historical data and newly fetched data.

The unique `(fbdate, fbuser)` key allows refreshing an existing day to update the stored document rather than create a duplicate row.

## Docker deployment

The included `Dockerfile` uses a multi-stage build:

1. A PHP 8.6 FPM Alpine base provides the runtime and MySQL extensions.
2. The build stage installs Composer dependencies from `composer.json` and `composer.lock` into `/var/www/vendor`.
3. The final runtime image copies the built `vendor/` directory from the build stage.

In the supplied `docker-compose.yml`, the application source is bind-mounted read-only into `/var/www/html`. This allows ordinary PHP source changes to be deployed to the test environment without rebuilding the PHP image; restart the PHP container after updating the source files. Rebuild the image when Composer dependencies change so the baked `vendor/` directory is refreshed.

The supplied Compose file is an example deployment using PHP-FPM, Caddy and MySQL. Adapt paths, image names, networking and database exposure to suit your environment.

Use `.env.fitbit.sample` as a template for the environment variables required by the example Compose deployment. Do not commit your real `.env.fitbit` file or production credentials.

## Non-Docker deployment

For a conventional PHP deployment:

1. Clone or copy the application source to the server.
2. Install the Composer dependencies represented by `composer.lock` so that `vendor/` is available to the application.
3. Create the database tables from the SQL files in the order listed in the **Database** section.
4. Configure the required environment variables.
5. Configure the HTTPS virtual host so `WEBSITE_HOSTNAME` corresponds to the externally reachable application hostname.
6. Open the application and use **Sign in with Google** to complete Google sign-in and Google Health authorisation.

## Using the application

The home page distinguishes three states: signed out, signed in with Google Health not connected, and signed in with Google Health connected.

When connected:

- **View Heart Rate Data** opens the stored daily data view.
- **Refresh** fetches and stores the selected day again.
- **Fetch and Next** fetches the selected day and advances to the following day.
- **Export to Excel** exports the stored intraday readings for the selected date.
- **Reconnect Google Health** starts a new Google OAuth authorisation flow for the signed-in account.
- **Disconnect Google Health** removes the signed-in user's locally stored OAuth credentials while retaining the application session and historical heart-rate data.
- **Log out from this browser** clears the application session and browser session cookie while retaining locally stored Google Health OAuth credentials and historical data.

Application sessions use a 30-day lifetime. The browser session cookie is configured Secure, HttpOnly and SameSite=Lax.

## Mobile PWA

`manifest.webmanifest` defines an installable standalone PWA whose start URL is `/mobiledata.php`. Launching the installed PWA therefore opens the mobile-focused heart-rate view directly, while `data.php` remains the full desktop-oriented view.

The mobile view:

- Keeps previous-day, next-day, date-picker and refresh controls in a compact touch-friendly navigation row.
- Returns to `mobiledata.php` after refreshing data through `getdata.php`.
- Uses a taller Highcharts display sized specifically for a phone screen.
- Shows the daily resting heart rate and heart-rate zone summary without the detailed reading table or Excel export button.
- Includes bottom spacing for phones with on-screen navigation controls.

Mobile presentation rules are stored in `assets/css/fitbit.css`. `mobiledata.php` appends the stylesheet modification time as a query parameter so updated mobile styles are not hidden by a stale browser or installed-PWA cache.

If the PWA is already installed when `manifest.webmanifest` changes, reopening or reinstalling the application may be required before the platform uses the updated launch configuration.

## Content Security Policy

The application serves its browser assets locally and sends a restrictive Content Security Policy from `config.php`.

If CSP troubleshooting is required, `config.php` contains commented reporting-mode lines that can be used while investigating violations.

## Security and privacy

Heart-rate information is sensitive health data. The application stores retrieved heart-rate data in the configured database and stores each user's Google OAuth token bundle encrypted.

Google account identity is established through OpenID Connect. Stored heart-rate access is scoped through the Google account's associated Google Health identity, and live data fetches reject a Google Health identity that does not match the stored application-user mapping.

Protect the application host, database, environment file and `GOOGLE_OAUTH_ENCRYPTION_KEY` as sensitive infrastructure. Review `SECURITY.md` for project security information.

## Demo

A demo of the original inspiration is available at <https://<scrubbed-url-domain>/fitbit>.

## Licence and history

The project is licensed under the Apache License 2.0. It was originally derived from Fitbit intraday heart-rate work and has since been migrated to use Google Health while retaining compatibility with the existing application's stored data.
