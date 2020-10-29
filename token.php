<?php

// Step 1 - Fitbit provides data in "#" which PHP cannot easily interpret
// So doing some Javascript magic and redirecting it in a way that PHP can interpret it
if (!isset($_GET['hash'])) {

?>
<html>
<body>

<script>
	var hashdata = window.location.hash;
	var data = hashdata.substr(1);
	location.href = 'token.php?hash=true&' + data;
</script>

</body>
</html>

<?
} else {

	// Step 2 - Now the data is in query string. Initialize Session and process it
	session_start();

	$access_token = $_GET['access_token'];
	$_SESSION['fb_access_token'] = $access_token;
	setcookie("fb_access_token", base64_encode($access_token), time()+$config_expires_sec, "/", "bsheartrate.azurewebsites.net", 1); 
	header("Location: data.php"); // redirect to data review page
	exit;

}
?>
