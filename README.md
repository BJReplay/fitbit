# What is Fitbit Intraday Heart Rate Data?

Fitbit Trackers which have Heart Rate sensors in them capture the [Heart Rate **continuously**](https://support.google.com/googlehealth/answer/14237938). However, the Google Health App only provides the Heart Rate information in 5-minute intervals.

This code allows you to download the detailed data from Fitbit's API and see it on a chart, in resolution as high as one reading each 3 seconds.  For each five-minute reading in the Google Health app, this code will download and display as many as 100 separate heart rate readings.

## Why was this created?

This was inspired by <https://github.com/technotablet/fitbit> by my need to track my SVT that hasn't responded to two rounds of ablation, and now, post COVID, is problematic.

My cardiologist is happy to use the high-resolution data from my Fitbit instead of requiring me to book in to wear a 24-hour Holter Monitor.  Whilst a Holter Monitor provides a more detailed heart rhythm analysis that a Fitbit can, the Fitbit can allow my cardiologist to easily review my SVT, and review my disease status.

It was modified from the original to deal with problems retrieving data that appear to be caused by changes in the Fitbit API, so not only displays the chart, but includes a refresh button, as well as navigation buttons.

Freshly updated post the great PHP CVE event of October 2026, it now includes a `Fetch and Next >` button to quickly fetch a backlog of data, without waiting for the freshly fetched data to scroll, and has unpinned from Highcharts 10 to use the latest version of Highcharts.

It includes a Dockerfile in the infra folder for php:fpm-alpine if you want to run this on a Docker instance.

## Demo Version

You can use <https://exain.com/fitbit> service to see a demo.

## How to Install

* You require PHP with MySQL Support and a publicly accessible server. **https** access is preferred.
* Download/Clone the code on your machine
* Create the database. The schema is available at `sql/default.sql`
* Provide the following values in `config.php`
  * Database Details (`$host`, `$user`, `$pass`, `$dbname`)
  * `$config_redirect_uri` - this will be the URL that is publicly accessible and hosted on your setup (e.g. `https://yourdomain.com/fitbit/token.php`
* Access the service through your URL
