## What is Fitbit Intraday Heart Rate Data?
Fitbit Trackers which have Heart Rate sensors in them capture the [Heart Rate **continuously**](https://help.fitbit.com/articles/en_US/Help_article/1565#How). However, Fitbit's App provides the Heart Rate information in 5-minute intervals.

This code allows you to download the detailed data from Fitbit's API and see it on a chart.

## Why was this created?
This was inspired by https://github.com/technotablet/fitbit by my need to track my SVT that hasn't responded to two rounds of ablation, and now, post COVID, is problematic.
It was modified from the original to deal with problems retrieving data that appear to be caused by changes in the Fitbit API, so only displays the chart, but includes a refresh button, as well as navigation buttons.

## Demo Version
You can use https://exain.com/fitbit service to see a demo.

## How to Install
* You require PHP with MySQL Support and a publicly accessible server. **https** access is preferred.
* Download/Clone the code on your machine
* Create the database. The schema is available at `sql/default.sql`
* Provide the following values in `config.php`
  * Database Details (`$host`, `$user`, `$pass`, `$dbname`)
  * `$config_redirect_uri` - this will be the URL that is publicly accessible and hosted on your setup (e.g. `https://yourdomain.com/fitbit/token.php`
* Access the service through your URL
