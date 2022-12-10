<? // mobile-visit-action-NEW.php
/* mobile-visit-action.php (copied from client-request-appointment.php)
*
* Parameters: 
* id - id of appointment to be edited
* operation - cancel, change, uncancel, complete
* 
* $_POST mode: execute request or notify if too late to cancel/change
* $_GET mode: set up request window
* - in $_GET mode, if "complete", offer choice or "arrive or complete" choice to user
*/

require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "appointment-fns.php";
include "service-fns.php";
include "request-fns.php";
require_once "gui-fns.php";
// Verify login information here
extract(extractVars('id,scope,phone,note,operation,lat,lon,speed,heading,accuracy,geoerror,update', $_REQUEST));

if(userRole() == 'p') {
	locked('p-');
	$activeClients = getActiveClientIdsForProvider($_SESSION["providerid"]);
}
else if(userRole() == 'c') { // ????
	locked('c-');
	$activeClients = array($_SESSION["clientid"]);
}
else $activeClients = fetchCol0("SELECT clientid FROM tblclient WHERE active = 1");
$operationLabel = $operation == 'cancel' ? 'Cancel' : (
									$operation == 'change' ? 'Change' : (
									$operation == 'uncancel' ? 'Un-cancel' : (
									$operation == 'complete' ? 'Complete' : '')));

if(!isset($id)) $error = "Visit ID not specified.";
else {
	$source = getAppointment($id, 1);
	if(!in_array($source['clientptr'], $activeClients) && $source['providerptr'] != $_SESSION["providerid"]) {
		echo "<h2>You have insufficient rights to view this page.</h2>";
		exit;
	}
	$package = getPackage($source['packageptr']);
	$packageType = $package['monthly'] ? 'Monthly Recurring' :
	               ($package['onedaypackage'] ? 'One Day' :
	               ($package['enddate'] ? 'Nonrecurring' : 'Weekly Recurring'));
  $source['packageType']	= $packageType;
}

function recordPosition($id, $event) {
	global $lat, $lon, $speed, $now, $heading, $geoerror, $accuracy;
	if(in_array('tblgeotrack', fetchCol0("SHOW TABLES"))) {
		if($lat) {
			//lat,lon,speed,heading
			insertTable('tblgeotrack', 
				array('userptr'=>$_SESSION['auth_user_id'], 
							'date'=>$now, 
							'lat'=>($lat ? $lat : '0'),
							'lon'=>($lon ? $lon : '0'),
							'speed'=>($speed && is_numeric($speed) ? $speed : '0'),
							'heading'=>($heading && is_numeric($heading) ? $heading : '0'),
							'accuracy'=>($accuracy && is_numeric($accuracy) ? $accuracy : '0'),
							'appointmentptr'=>$id,
							'event'=>$event,
							'error'=>($geoerror ? $geoerror : '0')), 1);
		}
		else {
			// 0: Error retrieving location  1: User denied  2: Browser cannot determine location  3:  Time out
			$userAgent = $_SERVER["HTTP_USER_AGENT"];
			$browser = '';
			$os = '';
			$matches = array('Firefox'=>'', 
											'MSIE 6.0'=>'Internet Explorer v6', 'MSIE 7.0'=>'Internet Explorer v7', 'MSIE 8.0'=>'Internet Explorer v8', 
											'MSIE 9.0'=>'Internet Explorer v8','Chrome'=>'', 'Safari'=>'');
			foreach($matches as $key=>$prettyName) 
				if($start = strpos($userAgent, $key)) {
					if(strpos($key, 'MSIE') === 0) $browser = $key;
					else  $browser = substr($userAgent, $start);
					break;
				}
			$matches = array('Windows'=>'Windows', 
												'iPod'=>'iPod', 'iPad'=>'iPad', 'iPhone'=>'iPhone', 
												'Android'=>'Android', 'Linux'=>'Linux', 'Mac'=>'Mac');
			foreach($matches as $key=>$prettyName) 
				if($start = strpos($userAgent, $key)) {
					$os = $key;
					break;
				}
			if(!$os || !$browser) $agent = $userAgent;
			else $agent = "os|$os||browser|$browser";
			if(!isset($geoerror)) $geoerror = 'notsupported';
			insertTable('tblerrorlog', array('time'=>$now, 'message'=>"geoerror|$geoerror||visit|$id||$agent"), 1);
		}
	}
}

$now = date('Y-m-d H:i:s');

if($operation == 'arrived') {
	if(strlen((string)$lat) == 0) {
		$lat = -999;
		$geoerror = $geoerror ? $geoerror : 'No loc supplied';
	}
	recordPosition($id, 'arrived');
	echo "MSG|ARRIVED|".date('g:i a', strtotime($now));
	exit;
}
if(!$error && $_POST) {
	if($operation == 'complete') {
		$mods = withModificationFields(array('completed'=>$now, 'canceled'=>null));
		if(trim($note)) $mods['note'] = trim($note);
	  updateTable('tblappointment', $mods, "appointmentid = $id", 1);
		if($_SESSION['surchargesenabled']) markAppointmentSurchargesComplete($id);
		require_once "invoice-fns.php";
		createBillablesForNonMonthlyAppts(array($id));
		logAppointmentStatusChange(array('appointmentid'=>$id, 'completed' => 1), "Mobile visit complete - provider.");
		$update = $update ? "if(parent.update) parent.update('visit',$id);"  
											: "parent.document.location.href='appointment-view-mobile.php?id=$id';";
//$DEBUG = mattOnlyTEST() ? "alert(parent);" : '';											
		echo "<script language='javascript'>$update</script>";
		recordPosition($id, 'completed');						
		exit;
	}
	if($_SESSION["providerid"]) {
		$request['providerptr'] = $_SESSION["providerid"];
		$request['clientptr'] = $source["clientptr"];
	}
	else if(userRole() == 'd') $request['clientptr'] = $source["clientptr"];
	else $request['clientptr'] = $_SESSION["clientid"];
	$request['scope'] = $scope;  // sole_apptId or day_apptDate
	$request['phone'] = $phone; //primaryPhoneNumber($data)
	$request['note'] = $note;
	$request['requesttype'] = $operation;
	if($operation == 'cancel') { // $id, $operation, $scope, 
	}
	else if($operation == 'uncancel') {
	}
	else if($operation == 'change') {
	}
	$request['requestid'] = saveNewClientRequest($request);
	setPendingChangeNotice($request);
	echo "<script language='javascript'>parent.$.fn.colorbox.close();parent.alert('Your request has been submitted.');</script>";
	exit;
}

//$windowTitle = "$operationLabel Visit: ($packageType Package)";
$extraBodyStyle = '';
require "mobile-frame-bannerless.php";
?>
<style>
.visit {background-color:#FFB463;}
</style>
<?

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

//print_r($source);exit;
if($operation != 'complete') {
	$tooLateToCancel = $_SESSION['preferences']['cancellationDeadlineHours'];
	if($tooLateToCancel && time() + (60 * 60 * $tooLateToCancel) > strtotime($source['date'].' '.$source['starttime']))
		echo "<h3><font color=red>{$_SESSION['preferences']['cancellationDeadlineWarning']}</font></h3>";
}

if($operation == 'complete' && !$source['completed'] && !$source['canceled'] && !$source['arrived'] && date('Y-m-d') == $source['date']
		&& $_SESSION['preferences']['mobileOfferArrivedButton']
		&& 	!($arrived = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = $id AND event = 'arrived' LIMIT 1"))) {
	echo '<script type="text/javascript" src="mobile-visit-action.js"></script>';
	$arrivalButton = "<img id='arrivedbutton' src='art/arrivedbutton.gif' onclick='arrived($id);'>";
	$compButton = 
		"<img id='compbuttonbutton' src='art/accepted_70X29.png' 
				onclick='document.getElementById(\"actiontable\").style.display=\"block\";"
									."document.getElementById(\"notediv\").style.display=\"block\";"
									."document.getElementById(\"comparrive\").style.display=\"none\";'>";
	$initialStyle = "style='display:none;'";
	echo "\n<table width='100%' id='comparrive'>
				<tr><td valign=middle align=right>$arrivalButton</td><td valign=middle>Just Arrived</td>\n<td valign=middle align=right>";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
	echo "\n</td></tr>
				<tr><td>&nbsp;</td></tr><tr><td align=right valign=middle>$compButton</td>
											<td colspan=2 valign=middle>My work here is done</td></tr>"
				."<tr><td colspan=4 class='visit'>{$source['client']} ({$source['pets']})</td></tr>"
				."<tr><td colspan=4 class='visit'>{$source['timeofday']} (".shortDateAndDay(strtotime($source['date'])).")</td></tr>"
				."<tr><td colspan=4 class='visit'>".fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$source['servicecode']} LIMIT 1")."</td></tr>"
				.($source['note'] ? "<tr><td colspan=4 class='visit'>".truncatedLabel(str_replace("\n", "<br>", str_replace("\r", "", ($source['note']))), 90)."</td></tr>" : '')
				."</table><p>";
	
}

$heading = $operation == 'complete' ? '' : "$operationLabel...";
echo "\n<table width='100%' id='actiontable' $initialStyle><tr>
<td style='font-size:0.9em;font-weight:bold;'>$heading</td>
<td style='text-align:right'>";
$operationLabel = $operation == 'complete' ? 'Mark Complete' : "$operationLabel Visit";
echoButton('',$operationLabel, "execute(\"$operation\")");
echo "<img src='art/spacer.gif' style='width:10px;height:1px;'>";
echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
?>
</td>
</tr>
</table>
<form name='apprequest' method='POST'>
<?
$shouldGetGeoCoords = true;  // base this on manager and (maybe?) user pref
if($shouldGetGeoCoords) echo "<span id='pleasewait' style='color:darkgreen;font-size:1.1em;font-weight:bold;'>Finding location...</span><br>";
hiddenElement('id', $id);
hiddenElement('operation', $operation);
if($operation != 'complete') { 
	labeledRadioButton('This visit only.', 'scope', "sole_$id", "sole_$id");
	echo "<br>";
	labeledRadioButton("All of this day's visits for {$source['client']}.", "scope", "day_{$source['date']}");
	echo "<p>";
	labeledInput("Best Phone Number to call:", 'phone', '');
}
else {
	//echo "[$lat,$lon,$speed,$heading]<p>";
	hiddenElement('lat', $lat);
	hiddenElement('lon', $lon);
	hiddenElement('speed', $speed);
	hiddenElement('heading', $heading);
	hiddenElement('accuracy', $accuracy);
	hiddenElement('geoerror', $geoerror);
}
$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $id LIMIT 1", 1);
if($appt['pets'] == 'All Pets') require_once "pet-fns.php";
$pets = !$appt['pets'] ? '<i>No Pets</i>' : (
				$appt['pets'] == 'All Pets' ? getClientPetNames($appt['clientptr']) : (
				$appt['pets']));
$visitSummary = 
		"<span style='font-size:1.5em;'>{$source['client']} ($pets)</span><br>"
			."<span style='font-size:1.5em;'>{$source['timeofday']} </span>"
			."<span style='font-size:1.2em;'>".shortNaturalDate(strtotime($source['date']))."</span>";

$noteLabel = 
	$operation == 'change' 
	? 'How would you like to change this visit?' : (
	$operation == 'complete' ?  
		$visitSummary
	: 'Note:');
echo "<p id='notediv' $initialStyle>$noteLabel<br><textarea rows=4 cols=30 id='note' name='note'></textarea></p?";
if($operation == 'complete' && $appt['note']) {
	$note = str_replace("\n\n", "<p>", str_replace("\n", "<br>", $appt['note']));
	echo "<br><span style='font-size:1.5em;'>$note</span>";
}
echo "</form>";

?>

<script language='javascript' src='check-form.js'></script>
<?=  $shouldGetGeoCoords ? "<script language='javascript' src='mobile-visit-action.js'></script>" : ''; ?>
<script language='javascript'>

function execute($operation) {
	document.apprequest.submit();
}
<?=  $shouldGetGeoCoords ? "getCoords();" : '';  ?>


if(parent.resetCountdown) parent.resetCountdown();
</script>
</body>
</html>
