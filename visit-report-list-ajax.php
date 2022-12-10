<? // visit-report-list-ajax.php?start=2018-01-15&end
//visit-report-list.php?start=2018-01-15&end
// grant access to logged in client or manager
// Usage:
// as logged-in client, start and end 
// as logged-in manager, clientid indicates which client's reports are to be returned
// params
// start, end (both optional when there is a clientid) dates define the range of reports returned
// receivedonly - when supplied and not null/zero, omit visits where no report elements have been received
// submittedonly - when supplied and not null/zero, include only reports that have been submitted
// publishedonly - when supplied and not null/zero, include only reports that have been published (sent to user)
// fullreports - for each report returned, include the report data packet

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "appointment-client-notification-fns.php";

extract($_REQUEST);

if(userRole() == 'c') {
	$locked = locked('c-', $noForward=true, $exitIfLocked=false);
	$clientptr = $_SESSION['clientid'];
	$clientMode = 1;
}
else {
	$locked = locked('o-', $noForward=true, $exitIfLocked=false);
	$clientptr = $_REQUEST['clientid'];
}

if($locked) $results = array('error'=>"session not active"); 
else {
	$results = visitReportList($start, $end, $clientptr, $fullreports);
	if(!$results) $results = array('error'=>"no reports found");
	else if(!$results['error']) {
		// return results in sequence, no visit id indexes
		//print_r($results);
		$results = array_merge($results);
	}
	
if($_GET['TEST']) {
	require_once "frame-bannerless.php";
	echo "<h2>Visits $start to $end</h2>";
?>
Click on JSON Data to see the underlying the data dictionary.
<p>
Click on Visit Report to see the data integrated into the visit report template (petowner/visit-reports/CareReport.html).
<p>
All links open in a new target tab, which is reused.  Drag the target detail tab out of the browser window to view the list and the details side by side.
<p>
To test in a sessionless environment, either drag a link to a private/incognito browser window where you are not logged in or logout from LeashTime in another regular tab and then open the links as usual.
<?
	function pdt($date) {return $date ? date('n/j H:i', strtotime($date)) : 'no';}
	require "appointment-fns.php";
	echo "<table border=1 bordercolor=gray><tr><th>JSON Data<th>Visit Report</tr>";
	foreach($results as $appt) {
		$details = getAppointment($appt['appointmentid'], $withNames=true);
		$nugget = explode('=', $appt['externalurl']);
		$data = visitReportDataForApptId($appt['appointmentid']);
		$arr = pdt($data['ARRIVED']);
		$comp = pdt($data['COMPLETED']);
		$sitter = $details['provider'] ? $details['provider'] : 'unassigned';
		if($lastDate != $details['date'])
			echo "<tr><th colspan=2>{$details['date']}";
		$lastDate = $details['date'];
		echo "<tr>";
		echo "<td><a target='insp' href='visit-report-data.php?nugget={$nugget[1]}'>({$appt['status']}) ARR: [$arr] COMPL: [$comp]</a>";
		echo "<td><a target='insp' href='visit-report-ext.php?nugget={$nugget[1]}'>({$appt['appointmentid']}) {$details['client']} [$sitter] {$appt['visittimeframe']}</a>";
	}
	echo "</table>";
	exit;
}
	
header("Content-type: application/json");
echo json_encode($results);
}

/*
if($error) ; // return error
else if($locked) $error = array('error'=>"session not active");
else if(!$finalReports) $error = array('error'=>"no reports found");
echo json_encode($finalReports ? $finalReports : $error);
*/		