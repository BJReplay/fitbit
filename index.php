<?php
include_once("config.php");

$fb_client_id = '';

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

// If FB Client ID is provided, save it in session and skip authentication
if (isset($_GET['fb_client_id']) && trim($_GET['fb_client_id']) != "") {

	// Register Client ID in the Session
	$fb_client_id = safe(trim($_GET['fb_client_id']));
	$_SESSION['fb_client_id'] = $fb_client_id;

	// If FB Client ID is provided, save it in session and try to authenticate	
} elseif (isset($_POST['client_id']) && trim($_POST['client_id']) != "") {

	// Register Client ID in the Session
	$fb_client_id = safe(trim($_POST['client_id']));
	$_SESSION['fb_client_id'] = $fb_client_id;
	setcookie("fb_client_id", $fb_client_id, time() + $config_expires_sec, "/", $config_base_host, true, true);
	// Redirect to Fitbit for OAuth2
	header("Location: $config_oauth_url?response_type=token&scope=$config_scope&redirect_url=$config_redirect_uri&expires_in=$config_expires_sec&client_id=$fb_client_id");
	exit;
}

if (isset($_SESSION['fb_client_id'])) {
	$fb_client_id = $_SESSION['fb_client_id'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<title>Fitbit Heart Rate Intraday Data</title>
	<link rel="stylesheet" href="/assets/css/bootstrap-3.3.7.min.css">
	<link rel="stylesheet" href="/assets/css/fitbit.css">

	<script src="/assets/js/jquery-1.12.4.min.js" defer></script>
	<script src="/assets/js/bootstrap-3.3.7.min.js" defer></script>
	<link rel="icon" href="/assets/favicon32.ico" type="image/x-icon" />
</head>

<body>

	<!-- header -->
	<nav class="navbar navbar-inverse navbar-fixed-top">
		<div class="container">
			<div class="navbar-header">
				<a class="navbar-brand" href="./"><strong>Fitbit Heart Rate Intraday Data</strong></a>
			</div>
		</div>
	</nav> <!-- /header -->

	<div class="navbar-spacer" aria-hidden="true"></div>

	<div class="container">

		<div class="row">

			<div class="col-md-3 bg-info">

				<form action="index.php" method="post">
					<div class="form-group">
						<label for="exampleInputEmail1">Fitbit Client ID</label>
						<input type="text" class="form-control" name="client_id" id="client_id" placeholder="Enter Fitbit Client ID" value="<?php echo htmlspecialchars($fb_client_id, ENT_QUOTES, 'UTF-8'); ?>" autofocus>
					</div>
					<button type="submit" class="btn btn-primary">Connect with Fitbit</button>
				</form>

				<div>
					<P>&nbsp;</P>
					<span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span>
					<a href='https://youtu.be/_H5fFtsrfeU' target=_blank><strong>How to get Client ID?</strong></a>
				</div>

				<hr>

				<div class="well">
					<h4><span class="label label-default">Configuration Used</span></h4>
					<dl>
						<dt>Scope</dt>
						<dd><?php echo $config_scope; ?><BR><BR></dd>

						<dt>Redirect URL</dt>
						<dd><?php echo $config_redirect_uri; ?><BR><BR></dd>

						<dt>Expires in</dt>
						<dd><?php echo $config_expires_sec; ?> Seconds</dd>
					</dl>
				</div> <!-- /well -->

			</div> <!-- /col-md-3 client id form -->


			<div class="col-md-9 ">
				<h4>About Fitbit Intraday Heart Rate Service</h4>
				<P>
					Through the Fitbit App, you can view heartrate information only in 5-minute intervals. However, your tracker is capturing your heartrate every few seconds.
				<P>
					The service available on this website allows you to connect to the Fitbit website and get your Intraday Heart Rate Data. You can:
				<ul>
					<li> Get detailed heartrate data from Fitbit Website
					<li> View the data on a chart and export it to an image
					<li> Export the data in Excel format
				</ul>
				<P>More information on why this service was originally created is available at
				<ul>
					<li> <a href="https://exain.wordpress.com/2017/07/26/fitbit-intraday-heartbeat-tracking-with-code">https://exain.wordpress.com/2017/07/26/fitbit-intraday-heartbeat-tracking-with-code</a>
				</ul>
				</P>

				<P>
					The code is open source and you can setup a service for yourself on your server. Download it from GitHub at
				<ul>
					<li> <a href="https://github.com/BJReplay/fitbit">https://github.com/BJReplay/fitbit</a>
				</ul>
				</p>

				<hr>

				<h4>Privacy Information</h4>
				<p>
					The heartrate information is sensitive and not something you would want to disclose to anyone. Fitbit also prevents third party developers from accessing the heartrate data. By using this service, you
				<ul>
					<li> Setup a special developer account at Fitbit and generate your own credentials.
					<li> Do not provide any identifiable information except a 'Client ID', which cannot be linked to your account, and you can also delete it from the Fitbit Website.
					<li> Authenticate only through your Fitbit credentials.
					<li> Save the heartrate data along with your Client ID on this website, also to enable historical data review.
					<li> Have an option to setup your own service on your server without sharing data with anyone.
					<li> If you authenticate through your Fitbit credentials, your heartrate data will not be shared with anyone else.
					<li> You may remove your Client ID at any time from the Fitbit Website.
					<li> Only you have access to your heartrate data.
					<li> You may remove access at any time from your Fitbit account.
					<li> Any reference to Fitbit in this website is only for informational purposes and not an endorsement of any kind.
					<li> Any reference to Fitbit in this website may also be taken to refer to Google Health where Fitbit is now a part of Google Health.	
				</ul>
				</P>
			</div> <!-- /col-md-9 about -->

		</div> <!-- /row -->


		<hr>
		Based on an example &copy; 2017 Vivek Kapoor, <a href='https://exain.wordpress.com'>https://exain.wordpress.com</a>

	</div> <!-- /container -->

</body>

</html>