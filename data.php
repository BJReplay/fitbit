<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-health.php';

/*
 * Get the requested date, or use today's date.
 */
$date = $_GET['date'] ?? date('Y-m-d');

if (isset($_GET['next'])) {
    $date = $_GET['next'];
}

if (
    !is_string($date) ||
    !validateDate($date . ' 00:00:00')
) {
    exit(
        'Date is not valid. ' .
        'Please use YYYY-MM-DD format.'
    );
}

$currDate = new DateTimeImmutable(
    $date
);

$next = $currDate
    ->add(
        new DateInterval('P1D')
    )
    ->format('Y-m-d');

$prev = $currDate
    ->sub(
        new DateInterval('P1D')
    )
    ->format('Y-m-d');

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
        'Unable to resolve Google Health identity: ' .
        $exception->getMessage()
    );

    http_response_code(401);

    exit(
        'Google Health is not connected. ' .
        '<a href="oauth-start.php">Connect Google Health</a>'
    );
}

// GET DATA & SHOW THE CHART
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<title>Fitbit Heart Rate Data for <?php	echo htmlspecialchars($healthUserId,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'	);?></title>

	<link rel="stylesheet" href="/assets/css/bootstrap-3.3.7.min.css">
	<link rel="stylesheet" href="/assets/css/bootstrap-datepicker-1.10.1.min.css">
	<link rel="stylesheet" href="/assets/css/highcharts-13.1.1-local.css">
	<link rel="stylesheet" href="/assets/css/highcharts-palette-13.1.1-local.css">
	<link rel="stylesheet" href="/assets/css/fitbit.css">

	<script src="/assets/js/jquery-3.7.1.slim.min.js"></script>

	<script src="/assets/js/bootstrap-3.3.7.min.js"></script>

	<script src="/assets/js/highcharts-13.1.1-local.js"></script>
	<script src="/assets/js/modules/series-label-13.1.1-local.js"></script>
	<script src="/assets/js/modules/exporting-13.1.1-local.js"></script>
	<script src="/assets/js/modules/export-data-13.1.1-local.js"></script>
	<script src="/assets/js/modules/accessibility-13.1.1-local.js"></script>

	<script src="/assets/js/bootstrap-datepicker-1.10.1.min.js"></script>

	<link rel="icon" href="/assets/favicon32.ico" type="image/x-icon" />
</head>

<body>

	<!-- Header -->
	<nav class="navbar navbar-inverse navbar-fixed-top">
		<div class="container">
			<div class="navbar-header">
				<a class="navbar-brand" href="./"><strong>Fitbit Heart Rate Intraday Data</strong></a>
			</div>
		</div>
	</nav>

	<div class="navbar-spacer-small" aria-hidden="true"></div>

	<!-- Primary data -->
	<div class="container-fluid">

		<div class="well" id="setdate">

			<form action='data.php' method=get id='form'>

				<div class="row">

					<div class="col-md-1 pull-left text-left">
						<a class="btn btn-success" title="Previous Day" href='data.php?date=<?php echo $prev; ?>'> &#x25c4; </a>
					</div> <!-- /col-md-1 -->

					<!-- Column for Showing Date Picker -->
					<div class="col-md-2">

						<div class="input-group date" id="datepicker">
							<input type=text class="form-control" readonly name=date placeholder='yyyy-mm-dd' value='<?php echo $date; ?>'>
							<div class="input-group-addon">
								<span class="glyphicon glyphicon-th"></span>
							</div>
						</div>

					</div><!-- /col-md-2 -->

					<div class="col-md-3 pull-right text-right">
						<a class="btn btn-success" title="Fetch and Next" href='getdata.php?date=<?php echo $date; ?>&next=<?php echo $next; ?>'>&#x21bb; &amp; &#x25ba;</a>
						<a class="btn btn-success" title="Refresh" href='getdata.php?date=<?php echo $date; ?>'>&#x21bb;</a>
						<a class="btn btn-success" title="Next Day" href='data.php?date=<?php echo $next; ?>'> &#x25ba; </a>
					</div> <!-- /col-md-3 -->


				</div> <!-- /row -->
			</form>

		</div> <!-- /well -->




		<?php

		// Query the data. Get the raw data and process it
		$seriesarr = array();
		$dataset = array();
		$heartRateZones = array();
		$rhr = "Unknown";

		try {
			$statement = mysqli_prepare($config_conn, 'SELECT fbjsondata FROM data_raw WHERE fbdate = ? AND fbuser = ? LIMIT 1');
			mysqli_stmt_bind_param($statement, 'ss', $date, $healthUserId);
			mysqli_stmt_execute($statement);
			$json = '';
			mysqli_stmt_bind_result($statement, $json);

			if (mysqli_stmt_fetch($statement)) {
				 if ($json === '') {
        			throw new UnexpectedValueException('Stored Fitbit JSON is empty.');
    			}
				$series = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
				if (!is_array($series)) {
					throw new UnexpectedValueException('Stored Fitbit JSON is not an object.');
				}
				$dataset = $series['activities-heart-intraday']['dataset'] ?? array();
				$hbsummary = $series['activities-heart'][0]['value'] ?? array();
				if (!is_array($dataset)) {
					throw new UnexpectedValueException('The intraday dataset is not an array.');
				}
				if (!is_array($hbsummary)) {
					$hbsummary = array();
				}
				$heartRateZones = $hbsummary['heartRateZones'] ?? array();
				if (!is_array($heartRateZones)) {
					$heartRateZones = array();
				}
				if (isset($hbsummary['restingHeartRate'])) {
					$rhr = (string) $hbsummary['restingHeartRate'];
				}
			}
			mysqli_stmt_close($statement);
		} catch (JsonException | UnexpectedValueException | mysqli_sql_exception $exception) {
			error_log(sprintf('Unable to load Fitbit data for %s: %s', $date, $exception->getMessage()));
			$dataset = array();
			$heartRateZones = array();
		}

		$datearr = explode('-', $date);
		foreach ($dataset as $value) {
			$time = $value['time'] ?? null;
			$hb = $value['value'] ?? null;
			if (!is_string($time) || !is_numeric($hb)) {
				continue;
			}
			$timearr = explode(':', $time);
			if (count($timearr) !== 3 || count($datearr) !== 3) {
				continue;
			}
			$seriesarr[] = sprintf('[Date.UTC(%d,%d,%d,%d,%d,%d), %d]', (int) $datearr[0], (int) $datearr[1] - 1, (int) $datearr[2], (int) $timearr[0], (int) $timearr[1], (int) $timearr[2], (int) $hb);
		}
		// Print the data / Show Chart
		?>

		<div id='charts'></div>

		<script type="text/javascript" nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
			Highcharts.chart('charts', {

				title: {
					text: 'Fitbit Heart Rate Data for <?php	echo htmlspecialchars($healthUserId,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'	);?>'
				},

				chart: {
					styledMode: true,
					zoomType: 'x',
					height: (7 / 16 * 100) + '%' // 16:9 ratio
				},
				subtitle: {
					text: '<?php echo $date; ?>'
				},
				xAxis: {
					type: 'datetime',
					dateTimeLabelFormats: { // don't display the dummy year
						hour: '%I %p',
						minute: '%I:%M %p'
					},
					title: {
						text: 'Time'
					}
				},
				yAxis: {
					title: {
						text: 'Heart Rate'
					}
				},
				legend: {
					layout: 'vertical',
					align: 'right',
					verticalAlign: 'middle'
				},

				plotOptions: {
					series: {
						pointStart: Date.UTC(1970, 1, 1, 0, 0, 0)
					}
				},

				series: [{
					name: 'Heart Rate',
					showInLegend: false,
					data: [
						<?php
						echo implode(",", $seriesarr);
						?>
					]
				}]

			});
		</script>
		<!-- /charts -->

		<hr>

		<!-- Other data including detailed table and summary -->
		<div class="row">

			<!-- headers for tables shown later -->
			<div class="col-md-3 pull-left">
				<h4>Heart Rate Summary</h4>
			</div>


			<div class="col-md-3">
				<h4>Resting Heart Rate: <?php echo $rhr; ?></h4>
			</div>

			<div class="col-md-6 pull-right">
				<a class="btn btn-primary pull-right" href="download.php?date=<?php echo $date; ?>">Export to Excel</a>
				<h4>Heart Rate Detailed Data</h4>
			</div>
			<!-- /headers -->


			<div class="clear-both" aria-hidden="true"></div>


			<!-- Table with Heart Rate Summary -->

			<div class="col-md-5 pull-left">

				<table class="table table-bordered table-striped">
					<thead>
						<th>Name</th>
						<th>Minutes Spent</th>
						<th>HB Range</th>
					</thead>

					<?php
					$colors = array('active', 'success', 'warning', 'danger');
					foreach ($heartRateZones as $i => $zone) {
						if (!is_array($zone)) {
							continue;
						}
						$class = $colors[$i % count($colors)];
						$name = htmlspecialchars((string) ($zone['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
						$minutes = htmlspecialchars((string) ($zone['minutes'] ?? 'N/A'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
						$range = htmlspecialchars((string) ($zone['min'] ?? '') . '-' . (string) ($zone['max'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
						echo "<tr class='$class'><td>$name</td><td>$minutes</td><td>$calories</td><td>$range</td></tr>\n";
					}
					?>
				</table>

			</div> <!-- /col-md-5 summary -->


			<!-- Table with Heart Rate Detailed Data -->
			<div class="col-md-6 pull-right pre-scrollable">

				<table class="table table-bordered" id='hrdata'>
					<thead>
						<th>Date &amp; Time</th>
						<th>Heart Rate</th>
					</thead>


					<?php
					if ($dataset === array()) {
						echo '<tr><td colspan="2" class="no-data">No data available</td></tr>' . "\n";
					}
					foreach ($dataset as $value) {
						if (!is_array($value)) {
							continue;
						}
						$time = htmlspecialchars((string) ($value['time'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
						$heartRate = htmlspecialchars((string) ($value['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
						echo "<tr><td>$date $time</td><td>$heartRate</td></tr>\n";
					}
					?>

				</table>
			</div> <!-- /col-md-6 detailed -->

			<div class="clear-both" aria-hidden="true"></div>

			<hr>

		</div> <!-- /container -->

		<script type="text/javascript" nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
			// Date Picker Configuration
			$('#datepicker').datepicker({
				autoclose: true,
				format: "yyyy-mm-dd"
			});

			$('#datepicker').on('show', function(e) {
				if (e.date) {
					$(this).data('stickyDate', e.date);
				} else {
					$(this).data('stickyDate', null);
				}
			});

			$('#datepicker').on('hide', function(e) {
				var stickyDate = $(this).data('stickyDate');

				if (!e.date && stickyDate) {
					$(this).datepicker('setDate', stickyDate);
					$(this).data('stickyDate', null);
				}

				$('#form').submit();
			});
		</script>

</body>

</html>