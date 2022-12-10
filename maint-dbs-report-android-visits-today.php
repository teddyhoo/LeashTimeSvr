<? // maint-dbs-report-android-visits-today.php
require_once "common/init_session.php";

if(userRole() != 'z') {
	echo "<h2>You must be logged in to the dashboard.</h2>";
	exit;
}
	

function processBusiness() {
	global $totalAndroidVisits, $totalAndroidVisitsWithMV, $today, $tables, $biz, $bizName, $bizptr, $goldstars;
	if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz'] || !in_array($bizptr, $goldstars)) return;
	require_once "preference-fns.php";
	if($ids = fetchCol0(
		"SELECT appointmentid, value
			FROM tblappointment
			LEFT JOIN tblappointmentprop ON appointmentptr = appointmentid AND property = 'native'
			WHERE date = '2018-10-10' AND value = 'AND'", 1)) { // if reported Android visits
		$totalAndroidVisits += count($ids);
		foreach($ids as $id) {
			$tracks = fetchRow0Col0("SELECT COUNT(*) FROM tblgeotrack WHERE appointmentptr = $id", 1);
			if($tracks > 2) {
				$lines[] = "<a target='_blank' href='https://leashtime.com/visit-map.php?id=$id&showcoords=1'>$id</a><br>";
				$totalAndroidVisitsWithMV += 1;
				$androidVisitsWithMV += 1;
			}
		}
		$androidVisitsWithMV = $androidVisitsWithMV ? $androidVisitsWithMV : 'none';
		echo "<p><b>$bizName</b> (> 2 tracks: $androidVisitsWithMV) (total Android visits: ".count($ids).")<br>";
		echo join("", (array)$lines);
	}
}

function postProcess() {
	global $totalAndroidVisits, $totalAndroidVisitsWithMV;
	echo "<a name='stats'><h2>Stats</h2></a>";
	echo "Total Android visits: $totalAndroidVisits<br>Total Android visits with more than 2 points: $totalAndroidVisitsWithMV";
}
?>
<h2>Android Visits Today With Two or More Tracks</h2>
<a href='#stats'>See stats at bottom.</a><p>
To use this page:<ol>
<li>Go to the dashboard in a separate window.
<li>For one of the businesses shown below:<ol>
	<li>Login to that business in the dashboard window.
	<li>In this window, click on a visit link.  This will open a new tab showing the visit coords, with a link to the visit map.
	</ol>
<li>To examine another business, exit to the dashboard and repeat step 2.
</ol>

<?
$today = date('Y-m-d');
require_once "maint-dbs-report.inc.php";
?>
