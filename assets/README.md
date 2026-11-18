# Third-party browser assets

These files are stored locally so that the application does not execute
JavaScript or load stylesheets from third-party CDNs.

## Dependencies

- jQuery 1.12.4
  - assets/js/jquery-1.12.4.min.js

- Bootstrap 3.3.7
  - assets/css/bootstrap-3.3.7.min.css
  - assets/js/bootstrap-3.3.7.min.js
  - assets/fonts/glyphicons-halflings-regular.*

- bootstrap-datepicker 1.3.0
  - assets/css/bootstrap-datepicker-1.3.0.min.css
  - assets/js/bootstrap-datepicker-1.3.0.min.js

- Highcharts
  - assets/js/highcharts.js
  - assets/js/exporting.js

The Highcharts core and exporting module must be taken from the same release.

Do not replace these files with floating or unverified CDN content.
Dependency upgrades must be reviewed and tested separately from the
self-hosting migration.
