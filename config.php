<?php
session_start();

include_once("functions.php");

// Database Details
$host = "dbhost"; // MySQL Hostname
$user = "dbuser"; // MySQL Username
$pass = "dbpassword"; // MySQL Password
$dbname = "dbname"; // MySQL DB Name

//below  will give the whole connectionstring in a single string
$conn = getenv("MYSQLCONNSTR_localdb"); 

//Let's split it and decorate it in an array
$conarr2 = explode(";",$conn); 
$conarr = array();
foreach($conarr2 as $key=>$value){
    $k = substr($value,0,strpos($value,'='));
    $conarr[$k] = substr($value,strpos($value,'=')+1);
}

// Other Configuration Parameters

// Replace the Redirect URL with your setup
$config_redirect_uri = "https://exain.com/fitbit/token.php";


$config_oauth_url = "https://www.fitbit.com/oauth2/authorize";
$config_scope = "heartrate profile"; // only getting heartrate and profile data
$config_expires_sec = 30 * 24 * 60 * 60; // 30 Days
$config_api_url = "https://api.fitbit.com/1/user/-/activities/heart/date"; // please see getdata.php /$date/1d/1sec/time/00:00/23:59.json";


// Connecting to Database
//$config_conn = mysqli_connect("$host","$user","$pass","$dbname");
$config_conn = mysqli_connect("$connarr['Data Source']","$connarr['User Id']","$connarr['Password']","bsheartrate");

if (!$config_conn) { exit("No MySQL Connection Established.<BR><BR>" . mysqli_error($config_conn)); }


?>
