# Google Health Heart Rate Intraday Data

This application retrieves detailed heart-rate data from the Google Health API, stores it locally, and provides a browser-based chart, daily heart-rate summary, navigation and export.

It began as a Fitbit intraday heart-rate project and retains some legacy Fitbit-compatible database column names and JSON structure so existing historical data can continue to be used alongside newly fetched Google Health data.

## Why was this created?

This project was inspired by <https://<scrubbed-url-domain>/technotablet/fitbit> by my need to track SVT that had not responded to two rounds of ablation and became more problematic post-COVID.

The high-resolution heart-rate data provides a useful long-term record that can be reviewed alongside clinical care. It is not a replacement for clinical monitoring such as a Holter monitor or ECG.

The application was subsequently updated as the Fitbit API was replaced by Google Health. It now fetches heart-rate data through Google Health while preserving compatibility with the application's existing stored data.

## Features

- Google OAuth authorisation with encrypted local token storage.
- Detailed Google Health intraday heart-rate retrieval.
- Daily resting heart rate.
- Heart-rate zone thresholds and minutes spent in each zone.
- Interactive Highcharts display.
- Previous-day, next-day, refresh, and Fetch and Next navigation.
- Export of stored intraday heart-rate data to an Excel-compatible `.xls` file.
- Local logout/disconnect and Google Health reconnection.
- Locally hosted application assets and a restrictive Content Security Policy.
- Docker build support with Composer dependencies included in the built application image.

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

`GOOGLE_OAUTH_ENCRYPTION_KEY` is used to encrypt the OAuth token bundle before it is stored in the database.

The OAuth callback URL is derived from `WEBSITE_HOSTNAME` and ends in `/token.php`.

## Database

Create the application tables using:

- `sql/default.sql` for stored heart-rate data.
- `sql/google-oauth.sql` for encrypted OAuth credentials.

The `data_raw` table retains its historical `fbdate`, `fbuser`, and `fbjsondata` column names for compatibility. Newly retrieved Google Health heart-rate data is mapped into the application's established stored document format, allowing existing display and export functionality to work with both migrated historical data and newly fetched data.

The unique `(fbdate, fbuser)` key allows refreshing an existing day to update the stored document rather than create a duplicate row.

## Docker deployment

The included `Dockerfile` uses a multi-stage build:

1. A PHP 8.6 FPM Alpine base provides the runtime and MySQL extensions.
2. The build stage installs Composer dependencies from `composer.json` and `composer.lock`.
3. The application files and generated `vendor/` directory are copied into the final runtime image.

The supplied `docker-compose.yml` is an example deployment using PHP-FPM, Caddy and MySQL. Adapt paths, image names, networking and database exposure to suit your environment.

Use `.env.fitbit.sample` as a template for the environment variables required by the example Compose deployment. Do not commit your real `.env.fitbit` file or production credentials.

## Non-Docker deployment

For a conventional PHP deployment:

1. Clone or copy the application source to the server.
2. Install the Composer dependencies represented by `composer.lock` so that `vendor/` is available to the application.
3. Create the database tables from the SQL files in `sql/`.
4. Configure the required environment variables.
5. Configure the HTTPS virtual host so `WEBSITE_HOSTNAME` corresponds to the externally reachable application hostname.
6. Open the application and use **Connect Google Health** to complete OAuth authorisation.

## Using the application

After connecting Google Health:

- **View Heart Rate Data** opens the stored daily data view.
- **Refresh** fetches and stores the selected day again.
- **Fetch and Next** fetches the selected day and advances to the following day.
- **Export to Excel** exports the stored intraday readings for the selected date.
- **Reconnect Google Health** starts a new OAuth authorisation flow.
- **Log Out** removes the OAuth credentials stored by this installation and clears the application session. It does not delete historical heart-rate data already stored in `data_raw`.

## Content Security Policy

The application serves its browser assets locally and sends a restrictive Content Security Policy from `config.php`.

If CSP troubleshooting is required, `config.php` contains commented reporting-mode lines that can be used while investigating violations.

## Security and privacy

Heart-rate information is sensitive health data. The application stores retrieved heart-rate data in the configured database and stores the Google OAuth token bundle encrypted.

Protect the application host, database, environment file and `GOOGLE_OAUTH_ENCRYPTION_KEY` as sensitive infrastructure. Review `SECURITY.md` for project security information.

## Demo

A demo is available at <https://<scrubbed-url-domain>/fitbit>.

## Licence and history

The project is licensed under the Apache License 2.0. It was originally derived from Fitbit intraday heart-rate work and has since been migrated to use Google Health while retaining compatibility with the existing application's stored data.
