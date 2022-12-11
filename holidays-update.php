<? // holidays-update.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "holidays-future.php";

$year = $_REQUEST['year'];
$update = $_REQUEST['update'];
if(!$year) {
	echo "Please specify a year.";
	exit;
}

foreach($holidays as $label => $dates) {
	foreach($dates as $date)
		if(strpos($date, $year) === 0) {
			$pairs[$label] = $date;
			if($update) 
				updateTable('tblsurchargetype', 
											array('date'=>$date),
											"label = '".mysqli_real_escape_string($label)."'", 
											1);
		}
}
if($update) {
	include "frame-bannerless.php";
	echo "<style>.message {color:darkgreen;font-size:1.2em;font-weight:bold;}</style>";
	echo "<center><span clas='message'>Your Standard business holidays have been updated.</span><h2>Standard Business Holidays for $year</h2>";
}
if($_REQUEST['offer']) {
	require_once "gui-fns.php";
	include "frame-bannerless.php";
	echo "<div class='fontSize1_3em' style='background:white; border: solid black 1px;width:600px;margin-bottom:20px;'>
	Click the button below to set the $year dates associated with the following holidays.<p>
	This will NOT alter any existing visits or surcharges.  It will affect only visits and surcharges created 
	after you update these holidays.<p>";
	echoButton(null, "Update $year Holidays", "document.location.href=\"holidays-update.php?year=$year&update=1\"");
	echo "</div>";
}
echo "<table border=1 bordercolor=black bgcolor=white>";
foreach($pairs as $label => $date)
	echo "<tr><td>$label</td><td>".date('F j', strtotime($date))."</td></tr>";
echo "</table>";
if($update) {
	echo "\n<p style='font-size:1.5em'>Your Own Holidays (unchanged)</p>";
	echo "\n<table border=1 bordercolor=black bgcolor=white>";
	foreach(fetchKeyValuePairs("SELECT label, date FROM tblsurchargetype ORDER BY label") as $label => $date)
		if(!$pairs[$label] && $date)
			echo "\n<tr><td>$label</td><td>".date('F j', strtotime($date))."</td></tr>";
	echo "\n</table>\n</center>";
}
