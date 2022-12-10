<? // sitter-visit-coords.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$since = date('Y-m-d', strtotime('-14 days'));
$today = date('Y-m-d');
$prov = $_REQUEST['prov'];
$sittername = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = '$prov'");
$apptid = $_REQUEST['apptid'];

if($apptid) {
	function distance($lat1, $lon1, $lat2, $lon2, $unit) {
		/*::  Passed to function:                                                    :*/
		/*::    lat1, lon1 = Latitude and Longitude of point 1 (in decimal degrees)  :*/
		/*::    lat2, lon2 = Latitude and Longitude of point 2 (in decimal degrees)  :*/
		/*::    unit = the unit you desire for results                               :*/
		/*::           where: 'M' is statute miles (default)                         :*/
		/*::                  'K' is kilometers                                      :*/
		/*::                  'N' is nautical miles                                  :*/
		$theta = $lon1 - $lon2;
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;
		$unit = strtoupper($unit);

		if ($unit == "K") {
			return ($miles * 1.609344);
		} else if ($unit == "N") {
				return ($miles * 0.8684);
			} else {
					return $miles;
				}
	}
	
	function kilometersToFeet($k) { return number_format($k*3280.84, 9); }	
	
	$tracks = fetchAssociations("SELECT * FROM tblgeotrack 
															WHERE appointmentptr = $apptid
															ORDER BY date");
	if(!$tracks) echo "No GPS coordinates found.";
	else {
		$tracks[0]['Distance (feet)'] = 0;
		for($i=1; $i<count($tracks); $i++)
			$tracks[$i]['Distance'] = kilometersToFeet(distance($tracks[$i-1]['lat'], $tracks[$i-1]['lon'], $tracks[$i]['lat'], $tracks[$i]['lon'], 'K'));
		$columns = array_keys($tracks[0]);
		quickTable($tracks, $extra="border=1 id=\"visits_$apptid\"", $style=null, $repeatHeaders=0);
	}
}
else {
	$appts = fetchAssociationsKeyedBy(
			"SELECT a.*, CONCAT_WS(' ', fname, lname) as client, label as service
				FROM tblappointment a
				LEFT JOIN tblclient ON clientid = clientptr
				LEFT JOIN tblservicetype ON servicetypeid = servicecode
				WHERE providerptr = $prov AND date >= '$since' AND date <= '$today' ORDER BY date DESC", 'appointmentid');
	if(!$appts) {
		echo "No visits for $sittername since $since";
		exit;
	}
	$coordcounts = fetchKeyValuePairs(
			"SELECT appointmentptr, COUNT(*) 
				FROM tblgeotrack 
				WHERE appointmentptr IN (".join(',', array_keys($appts)).")
				GROUP BY appointmentptr", 1);
//print_r($coordcounts);				
	require "frame-bannerless.php";
	echo "<h2>Visits by $sittername since ".shortDate(strtotime($since))."</h2>";
	echo "<table border=1 bgcolor=white><tr><th>Date<th><th>Coordinate Count";
	foreach($appts as $id => $appt) {
		echo "\n<tr><td>".shortDate(strtotime($appt['date']))."
						<td>{$appt['timeofday']}<td>"
						.fauxLink($coordcounts[$id], "showpoints($id)", $noEcho=true, $title=null, 'someid', $class=null, $style=null) 
						."<td>{$appt['client']}<td>"
						.fauxLink($appt['service'], "openConsoleWindow(\"mappy\", \"visit-map.php?id=$id\",780,800)", $noEcho=true)
						."<tr><td colspan=5 id='v_$id' style='display:none;'>";
	}	
	echo "</table>";
?>
<script language='javascript' src='ajax_fns.js'></script>

<script>
function showpoints(id) {
	ajaxGet('sitter-visit-coords.php?apptid='+id, 'v_'+id);
	var display = document.getElementById('v_'+id).style.display;
	document.getElementById('v_'+id).style.display = (display == 'none' ? '' : 'none');
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

</script>
<?
}
