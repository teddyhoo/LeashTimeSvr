<? // mobile-visit-action.php  New version
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
require_once "preference-fns.php";
require_once "appointment-client-notification-fns.php";

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

if(//$_SESSION['preferences']['clientArrivalCompletionRealTimeNotification'] && 
	(($notifyClientArrivalDetails = $operation == 'arrived' &&	getClientPreference($source['clientptr'], 'notifyClientArrivalDetails'))
		|| ($notifyClientCompletionDetails = $operation == 'complete' &&	getClientPreference($source['clientptr'], 'notifyClientCompletionDetails')))) {
	require_once "appointment-client-notification-fns.php";
}

$earlyArrivalMarkingLimit =
	fetchRow0Col0("SELECT value FROM tblpreference where property= 'earlyArrivalMarkingLimit' LIMIT 1", 1);
$earlyArrivalMarkingLimit = $earlyArrivalMarkingLimit ? $earlyArrivalMarkingLimit : 240;
$lateCompletionMarkingLimit = 120;
$times = timesForApptStartAndEnd($source); // in appointment-fns.php
$timeNow = time();
// rules: if event is more than 2 hours late, do not send notification
//				if event is more than 4 hours early, do not send notification
$doNOTNotify = (($times['start'] - $timeNow > $earlyArrivalMarkingLimit * 60)
		|| ($timeNow - $times['end'] > $lateCompletionMarkingLimit * 60));

if($operation == 'arrived') {
	/* ISSUES:
1. If there is no location info supplied, recordPosition does not record event in tblgeotrack
2. BUT... mobile-visit-action.php provides lat=-999 when no loc is supplied, so event is ALWAYS recorded
3. HOWEVER, we do not check for uniqueness of event+appointmentptr, so dubbaclick errors are possible
3.1 dubbaclick errors for arrived/completed can lead to annoying repeat notifications, and possible summary reporting problems
3.2 we cannot simply forbid duplicate event+appointmentptr, because for some future uses of tblgeotrack we may want to allow (e.g., dogwalk route mapping, other random sitter location reporting)
4. Solution:
4.1 add index (not PRIMARY or UNIQUE) to tblgeotrack
4.2 add params 'checkunique' and 'overwrite' param to recordPosition.  
4.2.1 When checkunique=true, check for pre-existence of event+appointmentptr. return null (meaning "already there")
4.2.2 When checkunique=true AND overwrite=true, replace duplicate record with new record
4.2.2 When checkunique=true AND overwrite=false, quit without recording
4.2.3 When checkunique=false, simply record locationa and event (as now)
4.c when recording arrival/completion, use recordPosition(checkunique=true,overwrite=false)
	
	*/
	if(strlen((string)$lat) == 0) {
		$lat = -999;
		$geoerror = $geoerror ? $geoerror : 'No loc supplied';
	}
	recordPosition($id, 'arrived');
	echo "MSG|ARRIVED|".date('g:i a', strtotime($now));
	if($notifyClientArrivalDetails) {
		if($doNOTNotify) 
			logError("visit [{$source['appointmentid']}] on [{$source['date']}] login: [{$_SESSION["auth_login_id"]}] event [$operation] MS webapp NO NOTE SENT.");
		else notifyClient($source, $operation, $note);
	}

	setAppointmentProperty($id, $operation."_recd", $now);
	$agent = strtoupper($_SERVER["HTTP_USER_AGENT"]);
	$platform = 
		strpos($agent, 'IPHONE') !== FALSE ? 'wIOS' : (
		strpos($agent, 'ANDROID') !== FALSE ? 'wAND' : 'wU');
	setAppointmentProperty($id, 'native', $platform);
	
	exit;
}
if(!$error && $_POST) {
	if($operation == 'complete') {
		$oldNote = getAppointmentProperty($id, 'oldnote');
		$oldNote = $oldNote ? $oldNote : fetchRow0Col0("SELECT note FROM tblappointment WHERE appointmentid = $id LIMIT 1", 1);
		if($oldNote && trim($oldNote)) setAppointmentProperty($id, 'oldnote', $oldNote);
		$mods = withModificationFields(array('completed'=>$now, 'canceled'=>null));
		$note = trim($note);
		if($note) $mods['note'] = $note;
	  updateTable('tblappointment', $mods, "appointmentid = $id", 1);
		if($_SESSION['surchargesenabled']) markAppointmentSurchargesComplete($id);
		
	if($note && $_SESSION['preferences']['enableSitterNotesChatterMods']) {
		require_once "chatter-fns.php";
		/*TEMP*/ ensureChatterNoteTableExists();
		addVisitChatterNote($id, $note, $_SESSION["providerid"], $authortable='tblprovider', $visibility=2, $replyTo=null);
	}
		
		require_once "invoice-fns.php";
		createBillablesForNonMonthlyAppts(array($id));
		logAppointmentStatusChange(array('appointmentid'=>$id, 'completed' => 1), "Mobile visit complete - provider.");
		$update = $update ? "if(parent.update) parent.update('visit',$id);"  
											: "parent.document.location.href='appointment-view-mobile.php?id=$id';";
//$DEBUG = mattOnlyTEST() ? "alert(parent);" : '';		

		if(strlen((string)$lat) == 0) {
			$lat = -999;
			$geoerror = $geoerror ? $geoerror : 'No loc supplied';
		}
		
		recordPosition($id, 'completed');								
		if($notifyClientCompletionDetails) {
			if($doNOTNotify) 
				logError("visit [{$source['appointmentid']}] on [{$source['date']}] login: [{$_SESSION["auth_login_id"]}] event [$operation] MS webapp NO NOTE SENT");
			else notifyClient($source, $operation, $note);
		}

		setAppointmentProperty($id, $operation."_recd", $now);
		$agent = strtoupper($_SERVER["HTTP_USER_AGENT"]);
		$platform = 
			strpos($agent, 'IPHONE') !== FALSE ? 'wIOS' : (
			strpos($agent, 'ANDROID') !== FALSE ? 'wAND' : 'wU');
		setAppointmentProperty($id, 'native', $platform);
		
		
		
		echo "<script language='javascript'>$update</script>";
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
$isBigger = isIPad() ||  $mobileSitterStyleSheet != "mobile-sitter.css";
if(isIPad()) $extraBodyStyle = 'font-size:1.2em';

require "mobile-frame-bannerless.php";
?>
<style>
.visit {background-color:#FFB463;}
<? if(isIPad()) echo "input {font-size:1.2em;}\ntextarea {font-size:1.2em;}\ntable {font-size:1.2em;}"; ?>
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

// $mobileVisitActionJavascript is useful when upgrading mobile-visit-action.js
//$mobileVisitActionJavascript =	loginidsOnlyTEST('shinego,koxford,joshslade,testbenball,dlifebeth,tablet3,sgnote2,apple5') ? "mobile-visit-actionV2.js" : "mobile-visit-action.js";
$mobileVisitActionJavascript =
	$_SESSION['preferences']['enableAccurateVisitLocationv2'] 
	  || loginidsOnlyTEST('shinego,koxford,joshslade,testbenball,dlifebeth,tablet3,sgnote2,apple5,ghwang,kschlachter') 
	? "mobile-visit-actionV2.js" 
	: "mobile-visit-action.js";

if($operation == 'complete' && !$source['completed'] && !$source['canceled'] && !$source['arrived'] && date('Y-m-d') == $source['date']
		&& $_SESSION['preferences']['mobileOfferArrivedButton']
		&& 	!($arrived = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = $id AND event = 'arrived' LIMIT 1"))) {
	//echo "<script type='text/javascript' src='$mobileVisitActionJavascript'></script>";
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
	$shortPets = truncatedLabel($source['pets'], 95);
	$displayNote = truncatedLabel(str_replace("\n", " - ", str_replace("\r", "", "{$source['note']}")), 90);
	$extraNoteStyle = strlen($displayNote) > 70 ? "style='font-size: 0.8em'" : '';
	echo "\n</td></tr>
				<tr><td>&nbsp;</td></tr><tr><td align=right valign=middle>$compButton</td>
											<td colspan=2 valign=middle>My work here is done</td></tr>"
				."<tr><td colspan=4 class='visit'>{$source['client']} ($shortPets)</td></tr>"
				."<tr><td colspan=4 class='visit'>{$source['timeofday']} (".shortDateAndDay(strtotime($source['date'])).")</td></tr>"
				."<tr><td colspan=4 class='visit'>".fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$source['servicecode']} LIMIT 1")."</td></tr>"
				.($source['note'] ? "<tr><td colspan=4 class='visit' $extraNoteStyle>$displayNote</td></tr>" : '')
				."</table><p>";
				// DISPLAY THE NOTE (above) -- look for "DISPLAY THE NOTE" below
	$noteHasBeenDisplayed = true;
}

$heading = $operation == 'complete' ? '' : "$operationLabel...";
echo "\n<table width='100%' id='actiontable' $initialStyle><tr>
<td style='font-size:0.9em;font-weight:bold;'>$heading</td>
<td style='text-align:right'>";
$operationLabel = $operation == 'complete' ? 'Mark Complete' : "$operationLabel Visit";
echoButton('executeButton',$operationLabel, "execute(\"$operation\")");
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
$pets = !$appt['pets'] ? '<i>No Pets</i>' : (truncatedLabel(
				$appt['pets'] == 'All Pets' ? getClientPetNames($appt['clientptr']) : (
				$appt['pets']), 60));

$dfonts = array('full'=>'font-size:1.5em;', 'smaller'=>'font-size:1.2em;');
if(isIPhone()) 
	$dfonts = array('full'=>'font-size:1.1em;', 'smaller'=>'font-size:0.9em;');
$visitSummary = 
		"<span style='{$dfonts['full']}'>{$source['client']} ($pets)</span><br>"
			."<span style='{$dfonts['full']}'>{$source['timeofday']} </span>"
			."<span style='{$dfonts['smaller']}'>".shortNaturalDate(strtotime($source['date']))."</span>";
//if($operation == 'complete' && dbTEST('sarahrichpetsitting,jordanspetcare,dogslife,tonkatest')) // 
//	$visitSummary .= "<br><a href='javascript:void(0)' onclick='execute(\"$operation\");'>Mark Complete</a>"; // alert(window.parent.parent);document.scrollTo(0,0)
	
//$operationLabel = $operation == 'complete' ? 'Mark Complete' : "$operationLabel Visit";
//echoButton('executeButton',$operationLabel, "execute(\"$operation\")");
	
	
	
$noteLabel = 
	$operation == 'change' 
	? 'How would you like to change this visit?' : (
	$operation == 'complete' ?  
		$visitSummary
	: 'Note:');
$cols = $isBigger ? 43 : 30;
$rows = $isBigger ? 8 : 4;
$sizeCSS = isIOSDevice() ? "style='resize:none;width:275px;max-width:275px;height:75px;max-height:75px'" 
													: "rows=$rows cols=$cols";  // does not work on intended platform, IOS
													
													
if($operation == 'complete' && dbTEST('sarahrichpetsitting,jordanspetcare,dogslife,tonkatest,mobilemutts,mobilemuttsnorth')) // 
	$extraMarkComplete .= "<br><a href='javascript:void(0)' onclick='execute(\"$operation\");'>Mark Complete</a>"; // alert(window.parent.parent);document.scrollTo(0,0)
													
													
echo "<p id='notediv' $initialStyle>$noteLabel<br><textarea $sizeCSS  id='note' name='note'></textarea>$extraMarkComplete</p>";

if($operation == 'complete' && $appt['note']) {
	$displayNote = str_replace("\n", "<br>", $appt['note']);
	$note = "".str_replace("\n\n", "<p>", $displayNote);
	$shortLimit = 40;
	/*if(FALSE && staffOnlyTEST() && strlen($note) > $shortLimit) {
		echo "<span id='shortnote' onclick='this.style.display=\"none\"; document.getElementById(\"longnote\").style.display=\"inline\";' style='font-size:1.5em;'>".truncatedLabel($note,$shortLimit)."</span>";
		echo "<span id='longnote' onclick='this.style.display=\"none\"; document.getElementById(\"shortnote\").style.display=\"inline\";' style='font-size:1.5em;display:none;'>$note</span>";
		echo "<br>";
	}*/
	if(TRUE || dbTEST('themonsterminders') || $noteHasBeenDisplayed) /* do not show note */ ; 
	// Dropped note display 8/26/2018.  Let's see if anyone complains.
	else echo "<br><span style='font-size:1.5em;'>$note</span>"; // DISPLAY THE NOTE
}
echo "</form>";
//if(dbTEST('sarahrichpetsitting,dogslife,tonkatest')) echo "<a href='javascript:void(0)' onclick='window.scrollTo(0, 0);'>Back to Top</a>"; // alert(window.parent.parent);document.scrollTo(0,0)

?>

<script language='javascript' src='check-form.js'></script>
<?=  $shouldGetGeoCoords ? "<script language='javascript' src='$mobileVisitActionJavascript'></script>" : ''; ?>
<script language='javascript'>
var arrivedDone = false; // avoid double-tapping
var completedDone = false; // avoid double-tapping

function execute($operation) {
	if(document.getElementById('executeButton').disabled) return; // if taps are queued before execute's first command runs
	document.getElementById('executeButton').disabled = true;
	document.apprequest.submit();
}
<?=  $shouldGetGeoCoords ? "getCoords();" : '';  ?>


if(parent.resetCountdown) parent.resetCountdown();
</script>
</body>
</html>
