<?php
// Get data from Fitbit Website through its API

include_once("config.php");


// Get session vars from cookies if possible
if (isset($_COOKIE['fb_client_id']) && $_COOKIE['fb_client_id'] != '') {
	$fb_client_id = $_COOKIE['fb_client_id'];
	$_SESSION['fb_client_id'] = $fb_client_id;
}

if (isset($_COOKIE['fb_access_token']) && $_COOKIE['fb_access_token'] != '') {
	$fb_access_token = base64_decode($_COOKIE['fb_access_token'], true);
	if ($fb_access_token !== false && $fb_access_token !== '') {
		$_SESSION['fb_access_token'] = $fb_access_token;
	} else {
		unset($_SESSION['fb_access_token']);
	}
}

// VALIDATE THE VARIABLES
// Step 1 - Validation of Date Availability
if (isset($_GET['date'])) {
	$date = $_GET['date'];
} else {
	exit("No date information is provided. Please provide date in YYYY-MM-DD format.");
}

// Step 2 - Validation of Date Format
if (!validateDate($date . ' 00:00:00')) {
	exit("Date is not in a valid format. Please use YYYY-MM-DD Format. Sorry.");
}

// Step 2a - Validation of Next Date Availability
if (isset($_GET['next'])) {
	$tempnext = $_GET['next'];
	if (validateDate($tempnext . ' 00:00:00')) {
		$next = $_GET['next'];
	} else {
		$next = $date;
	}
} else {
	$next = $date;
}

// Step 3 - Validation of Access Token
if (!isset($_SESSION['fb_access_token']) || trim($_SESSION['fb_access_token']) == "") {
	exit("No access token available. Please <a href='./index.php'>try again</a>.");
} else {
	$access_token = trim($_SESSION['fb_access_token']);
}

// Step 4 - Validation of Client ID
if (!isset($_SESSION['fb_client_id']) || trim($_SESSION['fb_client_id']) == "") {
	exit("No access token available. Please <a href='./index.php'>try again</a>.");
} else {
	$fb_client_id = trim($_SESSION['fb_client_id']);
}

// GET DATA FROM FITBIT
// Step 1 - Prepare URL
//$url = "$config_api_url/$date/1d/1sec/time/00:00/23:50.json";
$url = "$config_api_url/$date/1d/1sec.json";
$headers = array("Authorization: Bearer $access_token");

// Step 2 - Get from Fitbit
$data = curl($url, $headers);

if (!is_string($data) || $data === '' || $data === 'No Content') {
	error_log(sprintf('Fitbit returned an empty response for %s.', $date));
	http_response_code(502);
	exit('Fitbit returned an empty response.');
}

try {
	$decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
	if (!is_array($decoded)) {
		throw new UnexpectedValueException('Fitbit returned JSON that is not an object.');
	}
} catch (JsonException | UnexpectedValueException $exception) {
	error_log(sprintf('Invalid Fitbit response for %s: %s', $date, $exception->getMessage()));
	http_response_code(502);
	exit('Fitbit returned an invalid response.');
}

$user = trim($fb_client_id);
try {
	mysqli_begin_transaction($config_conn);
	$delete = mysqli_prepare($config_conn, 'DELETE FROM data_raw WHERE fbuser = ? AND fbdate = ?');
	mysqli_stmt_bind_param($delete, 'ss', $user, $date);
	mysqli_stmt_execute($delete);
	mysqli_stmt_close($delete);

	$insert = mysqli_prepare($config_conn, 'INSERT INTO data_raw (fbuser, fbdate, fbjsondata) VALUES (?, ?, ?)');
	mysqli_stmt_bind_param($insert, 'sss', $user, $date, $data);
	mysqli_stmt_execute($insert);
	mysqli_stmt_close($insert);
	mysqli_commit($config_conn);
} catch (mysqli_sql_exception $exception) {
	mysqli_rollback($config_conn);
	error_log(sprintf('Unable to store Fitbit data for %s: %s', $date, $exception->getMessage()));
	http_response_code(500);
	exit('There was an error storing the Fitbit data.');
}

mysqli_close($config_conn);
header('Location: data.php?date=' . rawurlencode($next) . '&rand=' . random_int(0, PHP_INT_MAX));
exit;
