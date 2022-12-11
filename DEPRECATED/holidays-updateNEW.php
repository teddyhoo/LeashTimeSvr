<? // holidays-update.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "holidays-future.php";
require_once "gui-fns.php";

$year = $_REQUEST['year'];
$update = $_REQUEST['update'];
if(!$year) {
	echo "Please specify a year.";
	exit;
}

function holidaysFor($year, $country=null, $update=false, $shortDates=false) {
	if($country) {
		$savedI18nfile = $_SESSION['i18nfile'];
		$_SESSION['i18nfile'] = getI18NPropertyFile($country);
	}
	$i18n = getI18NProperties();
	$holidays = $i18n['Holidays'];
	//echo "<b>Country: ".print_r($country,1)." - is_array: [".is_array($holidays)."] - ".print_r($holidays,1)."</b> ";
	foreach($holidays as $label => $dates) {
		$dates = explode(',', $dates);
		foreach($dates as $date) {
			if(strpos($date, $year) === 0) {
				if($shortDates) $date = month3Date(strtotime($date));
				$pairs[$label] = $date;
				if($update) 
					updateTable('tblsurchargetype', 
												array('date'=>$date),
												"label = '".mysqli_real_escape_string($label)."'", 
												1);
			}
		}
	}
	if($country) {
		$_SESSION['i18nfile'] = $savedI18nfile;
	}
	return $pairs;
}

$pairs = holidaysFor($year, $country=null, $update);  // for the current country

if($update) {
	include "frame-bannerless.php";
	echo "<style>.message {color:darkgreen;font-size:1.2em;font-weight:bold;}</style>";
	echo "<center><span clas='message'>Your Standard business holidays have been updated.</span><h2>Standard Business Holidays for $year</h2>";
}

if($_REQUEST['all']) {
	echo "<style>.hols td {background:white;font-size:0.8em}</style><table id=ALLCOUNTRIES border=0 bgcolor=white><tr>";
	foreach(explodePairsLine('US|US||CA|Canada||AU|Australia||UK|UK') as $country=>$label) {
		$pairs = holidaysFor($year, $country, $update=false, $shortDates=true);  // for the current country
		echo "<td valign=top><b>$label Holidays</b>";
		echo "<table class='hols' border=1 bordercolor=black>";
		foreach($pairs as $label => $date)
			echo "<tr><td>$label</td><td>".$date."</td></tr>";
		echo "</table>";
		echo "</td>";
	}
	echo "</tr></table>";
}
else {
	echo "<TABLE border=1 bordercolor=black bgcolor=white>";
	foreach($pairs as $label => $date)
		echo "<tr><td>$label</td><td>".date('F j', strtotime($date))."</td></tr>";
	echo "</table>";
}
if($update) {
	echo "\n<p style='font-size:1.5em'>Your Own Holidays (unchanged)</p>";
	echo "\n<table border=1 bordercolor=black bgcolor=white>";
	foreach(fetchKeyValuePairs("SELECT label, date FROM tblsurchargetype ORDER BY label") as $label => $date)
		if(!$pairs[$label] && $date)
			echo "\n<tr><td>$label</td><td>".date('F j', strtotime($date))."</td></tr>";
	echo "\n</table>\n</center>";
}
