# Third-party browser assets

These files are stored locally so that the application does not execute
JavaScript or load stylesheets from third-party CDNs.

This also enables the tight Content Security Policy in place in the config.php CSP.

## Dependencies

- jQuery 3.7.1
  - assets/js/jquery-3.7.1.slim.min.js

- Bootstrap 3.3.7
  - assets/css/bootstrap-3.3.7.min.css
  - assets/js/bootstrap-3.3.7.min.js
  - assets/fonts/glyphicons-halflings-regular.*

- bootstrap-datepicker 1.10.1
  - assets/css/bootstrap-datepicker-1.10.1.min.css
  - assets/js/bootstrap-datepicker-1.10.1.min.js

- Highcharts
  - assets/js/highcharts-13.1.1.js
  - assets/js/modules/accessibility-13.1.1.js
  - assets/js/modules/exporting-13.1.1.js
  - assets/js/modules/export-data-13.1.1.js
  - assets/js/modules/series-labels-13.1.1.js

The Highcharts core and modules must be taken from the same release.

In order to cache-bust CDNs (such as CloudFlare that will attempt to serve the Highcharts modules if you place this
service behind the CLoudFlare proxy), the Highcharts modules have all been renamed with
the specific version of highcharts at the time this version was created.

Do not replace these files with floating or unverified CDN content.
Dependency upgrades must be reviewed and tested separately from the
self-hosting migration.
