<?php
session_start();

$cspNonce = base64_encode(random_bytes(18));

$csp = implode('; ', [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "frame-ancestors 'none'",
    "form-action 'self'",
    "script-src 'self' 'nonce-{$cspNonce}' 'report-sha256'",
    "style-src 'self'",
    "img-src 'self' data:",
    "font-src 'self'",
    "connect-src 'self'",
    "manifest-src 'self'",
    "upgrade-insecure-requests",
    "report-uri https://sheppo.report-uri.com/r/d/csp/wizard; report-to wizard",
]);

header("Content-Security-Policy-Report-Only: {$csp}");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Reporting-Endpoints: default="https://sheppo.report-uri.com/a/d/g", wizard="https://sheppo.report-uri.com/a/d/g/wizard"');
header('Report-To: {"group":"default","max_age":31536000,"endpoints":[{"url":"https://sheppo.report-uri.com/a/d/g"}],"include_subdomains":true},{"group":"wizard","max_age":31536000,"endpoints":[{"url":"https://sheppo.report-uri.com/a/d/g/wizard"}],"include_subdomains":true}');
header('NEL: {"report_to":"default","max_age":31536000,"include_subdomains":true}');
include_once("functions.php");

//below  will give the whole connectionstring in a single string
$conn = getenv("MYSQLCONNSTR_localdb");

//Let's split it and decorate it in an array
$conarr2 = explode(";", $conn);
$conarr = array();
foreach ($conarr2 as $key => $value) {
    $k = substr($value, 0, strpos($value, '='));
    $conarr[$k] = substr($value, strpos($value, '=') + 1);
}

//$host = $conarr["Server"]; 
//$user = $conarr["User Id"]; 
//$pass = $conarr["Password"];
//$dbname = $conarr["Database"];

$host = $conarr["Data Source"];
$user = $conarr["User Id"];
$pass = $conarr["Password"];
$dbname = $conarr["Database"];

// Other Configuration Parameters

// Replace the Redirect URL with your setup
$config_base_host = getenv("WEBSITE_HOSTNAME");
$config_redirect_uri = "https://" . $config_base_host . "/token.php";

$config_oauth_url = "https://www.fitbit.com/oauth2/authorize";
$config_scope = "heartrate profile"; // only getting heartrate and profile data
$config_expires_sec = 30 * 24 * 60 * 60; // 30 Days
$config_api_url = "https://api.fitbit.com/1/user/-/activities/heart/date"; // please see getdata.php /$date/1d/1sec/time/00:00/23:59.json";

// Connecting to Database
//$config_conn = mysqli_init();
//$config_conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
//$config_conn->ssl_set(NULL, NULL, "/etc/ssl/certs/ca-certificates.crt", NULL, NULL);
//$config_conn->real_connect("$host","$user","$pass","$dbname");
//$mysqli->close();
$config_conn = mysqli_connect("$host", "$user", "$pass", "$dbname");

if (!$config_conn) {
    exit("No MySQL Connection Established.<BR><BR>" . mysqli_connect_error());
}
