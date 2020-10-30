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
	$access_token = $_GET['access_token'];
	setcookie("fb_access_token", base64_encode($access_token), time()+(60 * 60 * 24 * 30), "/", "bsheartrate.azurewebsites.net", true, true); 
	session_start();
	$_SESSION['fb_access_token'] = $access_token;
	header("Location: data.php"); // redirect to data review page
	exit;

}
?>
