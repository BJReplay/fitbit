<?php
include_once("config.php");

// Get Date or set current date
$date = date("Y-m-d");
if (isset($_GET['date'])) {
	$date = $_GET['date'];
}


// Get session vars from cookies if possible
if (isset($_COOKIE['fb_client_id']) && $_COOKIE['fb_client_id'] != '') {
	$fb_client_id = $_COOKIE['fb_client_id'];
	$_SESSION['fb_client_id'] = $fb_client_id;
}

if (isset($_COOKIE['fb_access_token']) && $_COOKIE['fb_access_token'] != '') {
	$fb_access_token = base64_decode($_COOKIE['fb_access_token']);
	$_SESSION['fb_access_token'] = $fb_access_token;
}

// VALIDATION
// Step 1 - Date Validation
if (!validateDate("$date 00:00:00")) {
	exit("Date is not valid. Please use YYYY-MM-DD format.");
} else {
	$curr_date = new DateTime($date);
	$next_date = new DateTime($date);
	$prev_date = new DateTime($date);
	$next_date->add(new DateInterval('P1D'));
	$prev_date->sub(new DateInterval('P1D'));
	$next = $next_date->format("Y-m-d");
	$prev = $prev_date->format("Y-m-d");
}

if (isset($_GET['fb_client_id']) && trim($_GET['fb_client_id']) != "") {
	
	// Register Client ID in the Session
	$fb_client_id = safe(trim($_GET['fb_client_id']));
	$_SESSION['fb_client_id'] = $fb_client_id;
}

// Step 2 - Do not proceed if Client ID is not set or available
if (!isset($_SESSION['fb_client_id']) || $_SESSION['fb_client_id'] == '') {
	exit("Client ID not available. Please <a href='index.php'>try again</a>");
} else {
	$fb_client_id = $_SESSION['fb_client_id'];
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
		<title>Fitbit Heart Rate Data for <?php echo $fb_client_id; ?></title>

		<!-- Bootstrap -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

		<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
		<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
		<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->

		<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
		<!-- Include all compiled plugins (below), or include individual files as needed -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>

		<script src='https://code.highcharts.com/10/highcharts.js'></script>
		<script src='https://code.highcharts.com/10/modules/exporting.js'></script>

		<!-- date picker -->
		<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/css/datepicker3.css">
		<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
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

<div style='height:60px;'>
</div>

<!-- Primary data -->
<div class="container-fluid">

	<div class="well" id="setdate">

		<form action='data.php' method=get id='form'>

		<div class="row">

			<div class="col-md-1 pull-left text-left">
				<a class="btn btn-success" href='data.php?date=<?php echo $prev; ?>'>&lt; Previous Day</a>
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
				<a class="btn btn-success" href='getdata.php?date=<?php echo $date; ?>'>Refresh (Sync with Fitbit)</a>
				<a class="btn btn-success" href='data.php?date=<?php echo $next; ?>'>Next Day &gt;</a>
			</div> <!-- /col-md-3 -->


		</div>  <!-- /row -->
		</form>

	</div> <!-- /well -->




	<?php

	// Query the data. Get the raw data and process it
	$q = "select id, fbjsondata from `data_raw` where fbdate='$date' and fbuser='$fb_client_id'";
	$result = mysqli_query($config_conn, $q);

	$seriesarr = array();

	if (mysqli_num_rows($result) > 0) {

		$series_raw = mysqli_fetch_row($result);
		$series = objectToArray(json_decode($series_raw[1]));

		if (!is_null($series)) {
			foreach ($series['activities-heart-intraday']['dataset'] as $key=>$value) {
				$time = $value['time'];
				$hb = $value['value'];
	
				$timearr = explode(":",$time);
				$datearr = explode("-",$date);
				$seriesarr[] = "[Date.UTC($datearr[0],$datearr[1]-1,$datearr[2],$timearr[0],$timearr[1],$timearr[2]), $hb]";
			}
		}
	}


	// Print the data / Show Chart
	?>

	<div id='charts'></div>

	<script>
	Highcharts.chart('charts', {

		title: {
			text: 'Fitbit Heart Rate Data for <?php echo $fb_client_id; ?>'
		},

		chart: {
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
				pointStart: Date.UTC(1970, 01, 01, 0, 0, 0)
			}
		},

		series: [{
			name: 'Heart Rate',
			showInLegend: false,
			data: [
				<?php
				echo implode(",",$seriesarr);
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

		<?php
		if (mysqli_num_rows($result) > 0) {
			if (!is_null($series)) {
				$hbsummary = $series['activities-heart'][0]['value'];
				$rhr = isset($hbsummary['restingHeartRate']) ? $hbsummary['restingHeartRate'] : "Unknown";
			}
		}
		else {
			$rhr = "Unknown";
		}
		?>
		
		<div class="col-md-3">
			<h4>Resting Heart Rate: <?php echo $rhr; ?></h4>
		</div>

		<div class="col-md-6 pull-right">
			<a class="btn btn-primary pull-right" href="download.php?date=<?php echo $date; ?>">Export to Excel</a>
			<h4>Heart Rate Detailed Data</h4> 
		</div>
		<!-- /headers -->


		<div class="clear"><!-- --></div>


		<!-- Table with Heart Rate Summary -->

		<div class="col-md-5 pull-left">

			<table class="table table-bordered table-striped">
			<thead>
				<th>Name</th>
				<th>Minutes Spent</th>
				<th>Calories</th>
				<th>HB Range</th>
			</thead>

			<?php
			if (mysqli_num_rows($result) > 0) {
				if (!is_null($series)) {
					$i=0;
					$color = array('active','success','warning','danger','active','active','active','active');
					foreach ($hbsummary['heartRateZones'] as $hbsummarydata1) {
		
						echo "<tr class='$color[$i]'>";
						echo "<td>".$hbsummarydata1['name']."</td>\n";
						echo "<td>".$hbsummarydata1['minutes']."</td>\n";
						echo "<td>".$hbsummarydata1['caloriesOut']."</td>\n";
						echo "<td>".$hbsummarydata1['min'].'-'.$hbsummarydata1['max']."</td>\n";
						echo "</tr>";
						$i++;
					}
				}
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
			if (mysqli_num_rows($result) == '0') {
				echo "<tr><td colspan=2 style='height:200px;'>No data available</td></tr>\n";
			}
			if (mysqli_num_rows($result) > 0) {
				if (!is_null($series)) {
					foreach ($series['activities-heart-intraday']['dataset'] as $key=>$value) {
						echo "<tr><td>$date $value[time]</td><td>$value[value]</td></tr>\n";
					}
				}
			}

			?>

			</table>
		</div> <!-- /col-md-6 detailed -->

		<div style='clear:both;'><!-- --></div>

		<hr>
	
</div> <!-- /container -->

<script>
// Date Picker Configuration
$('#datepicker').datepicker({
	autoclose: true,
	format: "yyyy-mm-dd"
});

$('#datepicker').on('show', function(e){
	if ( e.date ) {
		$(this).data('stickyDate', e.date);
	}
	else {
		$(this).data('stickyDate', null);
	}
});

$('#datepicker').on('hide', function(e){
	var stickyDate = $(this).data('stickyDate');

	if ( !e.date && stickyDate ) {
		$(this).datepicker('setDate', stickyDate);
		$(this).data('stickyDate', null);
	}

	$('#form').submit();
});
</script>

</body>
</html>
