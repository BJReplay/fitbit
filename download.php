<?php
include_once("config.php");

$date = $_GET['date'] ?? date("Y-m-d");
if (!validateDate("$date 00:00:00")) {
	exit("Date is not valid. Please use YYYY-MM-DD format.");
}
if (!isset($_SESSION['fb_client_id']) || $_SESSION['fb_client_id'] == '') {
	exit("Client ID not available. Please <a href='index.php'>try again</a>");
}
$fb_client_id = $_SESSION['fb_client_id'];
$safeFilenameClientId = preg_replace('/[^A-Za-z0-9._-]/', '_', $fb_client_id);
$filename = "Fitbit-Intraday-Heartrate-$safeFilenameClientId-$date.xls";
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Type: application/vnd.ms-excel");

$dataset = array();
try {
	$statement = mysqli_prepare($config_conn, 'SELECT fbjsondata FROM data_raw WHERE fbdate = ? AND fbuser = ? LIMIT 1');
	mysqli_stmt_bind_param($statement, 'ss', $date, $fb_client_id);
	mysqli_stmt_execute($statement);
	mysqli_stmt_bind_result($statement, $json);
	if (mysqli_stmt_fetch($statement)) {
		$series = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($series)) {
			throw new UnexpectedValueException('Stored Fitbit JSON is not an object.');
		}
		$dataset = $series['activities-heart-intraday']['dataset'] ?? array();
		if (!is_array($dataset)) {
			throw new UnexpectedValueException('The intraday dataset is not an array.');
		}
	}
	mysqli_stmt_close($statement);
} catch (JsonException | UnexpectedValueException | mysqli_sql_exception $exception) {
	error_log(sprintf('Unable to export Fitbit data for %s: %s', $date, $exception->getMessage()));
	$dataset = array();
}
?>
<table class="table table-bordered" id='hrdata'>
	<thead>
		<th>Date &amp; Time</th>
		<th>Heart Rate</th>
	</thead>
	<?php
	if ($dataset === array()) {
		echo '<tr><td colspan="2">No data available</td></tr>' . "\n";
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
