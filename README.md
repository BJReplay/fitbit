# What is Fitbit Intraday Heart Rate Data?

Fitbit Trackers which have Heart Rate sensors in them capture the [Heart Rate **continuously**](https://support.google.com/googlehealth/answer/14237938). However, the Google Health App only provides the Heart Rate information in 5-minute intervals.

This code allows you to download the detailed data from Google Health's API and see it on a chart, in resolution as high as one reading each 3 seconds.  For each five-minute reading in the Google Health app, this code will download and display as many as 100 separate heart rate readings.

## Why was this created?

This was inspired by <https://github.com/technotablet/fitbit> by my need to track my SVT that hasn't responded to two rounds of ablation, and now, post COVID, is problematic.

My cardiologist is happy to use the high-resolution data from my Fitbit instead of requiring me to book in to wear a 24-hour Holter Monitor.  Whilst a Holter Monitor provides a more detailed heart rhythm analysis that a Fitbit can, the Fitbit can allow my cardiologist to easily review my SVT, and review my disease status.

It was modified from the original to deal with problems retrieving data that appear to be caused by changes in the Fitbit API, and has now been modified to fetch data from the Google Health API which has replaced the Fitbit API which has reached end of life, so not only displays the chart, but includes a refresh button, as well as navigation buttons.

Freshly updated post the great PHP CVE event of October 2026, it now includes a `Fetch and Next >` button to quickly fetch a backlog of data, without waiting for the freshly fetched data to scroll, and has pinned and included Highcharts 13.1.1 to use the latest version of Highcharts as of November 2026.

All assets are now included with the application, allowing for a very tight CSP, so the risk of accidental data exfiltration is reduced (the application doesn't rely on unknown third party assets - you can inspect all assets utilised in this repo)

It includes a Dockerfile php:fpm-alpine if you want to run this on a Docker instance, and a sample docker-compose.yml and .env.fitbit to get you up and running.

## Demo Version

You can use <https://exain.com/fitbit> service to see a demo.

## How to Install

* You require PHP with MySQL Support and a publicly accessible server. **https** access is required.
* Download/Clone the code on your machine
* Create the database. The schema is available in `sql/default.sql` and `sql/google-oauth.sql`.
* Use the `.env.fitbit.sample` to set up your `.env.fitbit`
  * Database Details
  * Google oAuth Details
  * Website Details
* Access the service through your URL
* There is a CSP set up for enforcement by default; if you have issues getting the service running, swap to reporting mode by commenting out the enforcement CSP header and uncommenting the reporting header in config.php
