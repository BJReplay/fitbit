<?php

declare(strict_types=1);

include_once 'config.php';
require_once __DIR__ . '/google-health.php';
require_once __DIR__ . '/stored-heart-rate.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (isset($_GET['next'])) {
	$date = $_GET['next'];
}
if (!is_string($date) || !validateDate($date . ' 00:00:00')) {
	exit('Date is not valid. Please use YYYY-MM-DD format.');
}

$currDate = new DateTimeImmutable($date);
$next = $currDate->add(new DateInterval('P1D'))->format('Y-m-d');
$prev = $currDate->sub(new DateInterval('P1D'))->format('Y-m-d');
$googleSub = $_SESSION['google_user_sub'] ?? null;
if (!is_string($googleSub) || $googleSub === '') {
	http_response_code(401);
	exit('You are not signed in. <a href="oauth-start.php">Sign in with Google</a>');
}
try {
	$healthUserId = googleHealthCurrentUserId($config_conn, $config_redirect_uri, $googleSub);
} catch (JsonException | InvalidArgumentException | RuntimeException | UnexpectedValueException | mysqli_sql_exception $exception) {
	error_log('Unable to resolve Google Health identity: ' . $exception->getMessage());
	http_response_code(401);
	exit('Google Health is not connected. <a href="oauth-start.php">Connect Google Health</a>');
}

$fitbitCssVersion = (string) filemtime(__DIR__ . '/assets/css/fitbit.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#222222">
	<title>Heart Rate - <?php echo htmlspecialchars($date, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
	<link rel="manifest" href="/manifest.webmanifest">
	<link rel="stylesheet" href="/assets/css/bootstrap-3.3.7.min.css">
	<link rel="stylesheet" href="/assets/css/bootstrap-datepicker-1.10.1.min.css">
	<link rel="stylesheet" href="/assets/css/highcharts-13.1.1-local.css">
	<link rel="stylesheet" href="/assets/css/highcharts-palette-13.1.1-local.css">
	<link rel="stylesheet" href="/assets/css/fitbit.css?v=<?php echo rawurlencode($fitbitCssVersion); ?>">
	<script src="/assets/js/jquery-3.7.1.slim.min.js"></script>
	<script src="/assets/js/bootstrap-3.3.7.min.js"></script>
	<script src="/assets/js/highcharts-13.1.1-local.js"></script>
	<script src="/assets/js/modules/series-label-13.1.1-local.js"></script>
	<script src="/assets/js/modules/accessibility-13.1.1-local.js"></script>
	<script src="/assets/js/bootstrap-datepicker-1.10.1.min.js"></script>
	<link rel="icon" href="/assets/favicon32.ico" type="image/x-icon">
</head>
<body>
	<nav class="navbar navbar-inverse navbar-fixed-top"><div class="container-fluid"><div class="navbar-header"><a class="navbar-brand" href="./"><strong>Heart Rate</strong></a></div></div></nav>
	<div class="navbar-spacer-small" aria-hidden="true"></div>
	<div class="container-fluid">
		<div class="well" id="setdate">
			<form action="mobiledata.php" method="get" id="form">
				<div class="mobile-nav">
					<a class="btn btn-success nav-button" title="Previous Day" aria-label="Previous Day" href="mobiledata.php?date=<?php echo $prev; ?>">&#x25c4;</a>
					<div class="input-group date date-picker" id="datepicker">
						<input type="text" class="form-control text-center" readonly name="date" aria-label="Date" value="<?php echo htmlspecialchars($date, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
						<div class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></div>
					</div>
					<a class="btn btn-success nav-button" title="Refresh" aria-label="Refresh" href="getdata.php?date=<?php echo $date; ?>&amp;return=mobiledata.php">&#x21bb;</a>
					<a class="btn btn-success nav-button" title="Next Day" aria-label="Next Day" href="mobiledata.php?date=<?php echo $next; ?>">&#x25ba;</a>
				</div>
			</form>
		</div>

		<?php
		$seriesarr = array();
		$dataset = array();
		$heartRateZones = array();
		$rhr = 'Unknown';
		try {
			$document = loadStoredHeartRateDocument($config_conn, $healthUserId, $date);
			if ($document !== null) {
				$dataset = storedHeartRateDataset($document);
				$hbsummary = storedHeartRateSummary($document);
				$heartRateZones = $hbsummary['heartRateZones'] ?? array();
				if (!is_array($heartRateZones)) {
					$heartRateZones = array();
				}
				if (isset($hbsummary['restingHeartRate'])) {
					$rhr = (string) $hbsummary['restingHeartRate'];
				}
			}
		} catch (JsonException | UnexpectedValueException | mysqli_sql_exception $exception) {
			error_log(sprintf('Unable to load heart-rate data for %s: %s', $date, $exception->getMessage()));
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
		?>
		<div id="charts"></div>
		<script type="text/javascript" nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
			Highcharts.chart('charts', {
				title: { text: 'Heart Rate' },
				chart: { styledMode: true, zoomType: 'x', height: 320 },
				subtitle: { text: '<?php echo $date; ?>' },
				xAxis: { type: 'datetime', dateTimeLabelFormats: { hour: '%I %p', minute: '%I:%M %p' }, title: { text: null } },
				yAxis: { title: { text: 'BPM' } },
				legend: { enabled: false },
				series: [{ name: 'Heart Rate', showInLegend: false, data: [<?php echo implode(',', $seriesarr); ?>] }]
			});
		</script>
		<hr>
		<section aria-labelledby="summary-heading">
			<div class="summary-heading"><h4 id="summary-heading">Heart Rate Summary</h4><div class="summary-rhr">Resting: <?php echo htmlspecialchars($rhr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> BPM</div></div>
			<div class="table-responsive"><table class="table table-bordered table-striped">
				<thead><tr><th>Zone</th><th class="text-center">Minutes</th><th class="text-center">BPM</th></tr></thead><tbody>
				<?php
				$colors = array('active', 'success', 'warning', 'danger');
				if ($heartRateZones === array()) {
					echo '<tr><td colspan="3" class="no-data">No summary available</td></tr>' . "\n";
				}
				foreach ($heartRateZones as $i => $zone) {
					if (!is_array($zone)) {
						continue;
					}
					$class = $colors[$i % count($colors)];
					$name = htmlspecialchars((string) ($zone['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
					$minutes = htmlspecialchars((string) ($zone['minutes'] ?? 'N/A'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
					$range = htmlspecialchars((string) ($zone['min'] ?? '') . '-' . (string) ($zone['max'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
					echo "<tr class='$class'><td>$name</td><td class='text-center'>$minutes</td><td class='text-center'>$range</td></tr>\n";
				}
				?>
				</tbody></table></div>
		</section>
	</div>
	<script type="text/javascript" nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
		$('#datepicker').datepicker({ autoclose: true, format: 'yyyy-mm-dd' });
		$('#datepicker').on('show', function(e) { $(this).data('stickyDate', e.date || null); });
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
