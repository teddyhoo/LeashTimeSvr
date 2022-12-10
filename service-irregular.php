<?
//service-irregular.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
//require_once "zip-lookup.php";
require_once "client-fns.php";
//require_once "key-fns.php";
require_once "pet-fns.php";
//require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";
require_once "invoice-fns.php";

require_once "gui-fns.php";
include "weekday-grid.php";
include "petpick-grid.php";
include "time-framer-mouse.php";

set_time_limit(1 * 60);

$locked = locked('o-');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract($_REQUEST);

//pageTimeOn(); // sets $page_start_time

// Determine access privs

// schedule is deletable if it has no appointments
// - in the past
// - that have billables
$allowDeleteButton = false;
$deletableAppointments = null;
$deletableSurcharges = null;
if($packageid) {
	$allowDeleteButton = true;
	$oldPackage = getNonrecurringPackage($packageid);
	$packageHistory = findPackageIdHistory($packageid, $oldPackage['clientptr'], false);
	$history = join(',', $packageHistory);
	$appts = fetchAssociations(
		"SELECT appointmentid, CONCAT_WS(' ', tblappointment.date, starttime) as dt, billableid, paid
			FROM tblappointment
			LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
			WHERE packageptr IN ($history)");
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') 	{print_r($appts);exit;}
	$now = time();
	foreach($appts as $appt) {
		if(strtotime($appt['dt']) < $now || $appt['billableid']) $allowDeleteButton = false;
		else $deletableAppointments[] = $appt['appointmentid'];
	}
	$surcharges = fetchAssociations(
		"SELECT surchargeid, CONCAT_WS(' ', tblsurcharge.date, ifnull(starttime, '00:00:00')) as dt, billableid, paid
			FROM tblsurcharge
			LEFT JOIN tblbillable ON itemptr = surchargeid AND itemtable = 'tblsurcharge'
			WHERE packageptr IN ($history)");
	foreach($surcharges as $surcharge) {
		if(strtotime($surcharge['dt']) < $now || $surcharge['billableid']) $allowDeleteButton = false;
		else $deletableSurcharges[] = $surcharge['surchargeid'];
	}
}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') 	{print_r($deletableSurcharges);exit;}


$destination = null;
$conflicts = null;
$errors = array();
if($_POST) {
	if($action == 'delete') {
		if(!$allowDeleteButton) {
			$errors[] = "This package is no longer deletable";
		}
		else {
			if($deletableAppointments) 
				$deletedAppts = deleteAppointments("appointmentid IN (".join(',', $deletableAppointments).")");
			if($deletableSurcharges) deleteTable('tblsurcharge', "surchargeid IN (".join(',', $deletableSurcharges).")", 1);
			deleteTable('tblservicepackage', "packageid IN ($history)", 1);
			$destination = "client-edit.php?id={$_POST['client']}&tab=services";
			logChange($packageid, 'tblservicepackage', 'd', "Deleted CLIENT[{$oldPackage['clientptr']}] EZ[{$oldPackage['startdate']},{$oldPackage['enddate']}] IDS: $history [{$deletedAppts} visits]");
		}
	}
	else if(isset($killAppointments)) { // from nonrecurring-confict-resolution-form.php
		foreach($_POST as $key => $label) {
			if(strpos($key, 'appt_') !== FALSE)
				$apptids[] = substr($key, 5);
		}
		require_once "appointment-fns.php";
		deleteAppointments("appointmentid IN (".join(',', $apptids).")");
		$destination = "client-edit.php?id={$_POST['client']}&tab=services";
	}
	else if(isset($packageid)) {
//screenLogPageTime("postAppointmentChange() run time: ");
//bagScreenLog('ez-editCANDIDATE.php');
		
		if($packageid) {
//screenLogPageTime("up to START getNonrecurringPackage($packageid) [$newpackageid] run time: ");
			$oldPackage = getNonrecurringPackage($packageid);
			if(!$oldPackage['current']) {
				$currentPackage = findCurrentPackageVersion($packageid, $_POST['client'], false);
				$errors[] =
					"This version of the package is no longer current, so changes to it cannot be saved.<br>"
					. "Please note the changes you tried to make and then <a href='service-irregular.php?packageid=$currentPackage'>Edit the Current Version</a>";
			}
			else {
				// recalculate package price
				$_POST['packageprice'] = calculateNonRecurringPackagePrice($packageid, $client);

//screenLogPageTime("pre saveIrregularPackage($packageid) [$newpackageid] run time: ");
				$package = saveIrregularPackage($packageid);
//screenLogPageTime("POST saveIrregularPackage($packageid) [$newpackageid] run time: ");
				$_SESSION['clientEditNotifyToken'] = time();
				$notify= "&notifytime=".$_SESSION['clientEditNotifyToken']."&notifyschedule=$packageid";
				$packageid = $package ? $package['packageid'] : $packageid;
				if(!$conflicts)
					$destination = $action == 'stay' ? '' : "client-edit.php?id={$_POST['client']}&tab=services$notify";
			}
		}
		else {
			$packageid = saveNewIrregularPackage();
			$_SESSION['clientEditNotifyToken'] = time();
			$notify= "&notifytime=".$_SESSION['clientEditNotifyToken']."&notifyschedule=$packageid";
			$destination = $action == 'stay' ? '' : "client-edit.php?id={$_POST['client']}&tab=services$notify";
		}
		if(!$errors) {
			// for all providers associated with current schedule, generate memos
//screenLogPageTime("pre findPackageIdHistory($packageid) [$newpackageid] run time: ");
			//$packageHistory = findPackageIdHistory($packageid, $_POST['client'], false);
//screenLogPageTime("POST findPackageIdHistory($packageid) [$newpackageid] run time: ");
			$packageHistory[] = $packageid;
			$provs = fetchCol0("SELECT DISTINCT providerptr FROM tblappointment WHERE packageptr IN (".join(',', $packageHistory).")");
//screenLog("PackageID: $packageid History: [".print_r($packageHistory, 1)."]");

			foreach($provs as $provptr)
				if($provptr) {
					require_once "provider-memo-fns.php";
					makeClientScheduleChangeMemo($provptr, $_POST['client'], $packageHistory);
				}
//screenLogPageTime("POST makeClientScheduleChangeMemo: ");
		}

	}
	if(!$editVisitsOnLoad && !$stickAround && $destination) {
		$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
		header("Location: $mein_host$this_dir/$destination");
		exit;
	}
}
if(mysql_error()) exit;

$client = isset($client) ? $client : '';
if($packageid) { // existing service package
  $package = getNonrecurringPackage($packageid);
	$client = $package['clientptr'];
	//echo '['.print_r($package,1).']';exit;
}

$pageTitle = ($packageid ? '' : 'New ').($oldPackage['irregular'] == 2 ? "Meeting" : "EZ Schedule");

if($client) {
	$clientDetails = getClient($client);
	$clientDisplay = fullname($clientDetails);
if(staffOnlyTEST() || dbTEST('dogwalkingnetwork')) {
	$clickAction = "openConsoleWindow(\"clientview\", \"client-view.php?id=$client&infoOnly=1\", 700, 600)";
	$clientDisplay = fauxLink(fullname($clientDetails), $clickAction, 1, 'Client snapshot');
}
	$pageTitle .= ': '.$clientDisplay;
	require_once "client-flag-fns.php";
	$pageTitle .= ' '.clientFlagPanel($client, $officeOnly=false, $noEdit=true, $contentsOnly=true);
}


if($packageMustExist) {
	if(!$oldPackage) $errors[] = "This schedule no longer exists.";
	if(!$oldPackage['current']) {
screenLogPageTime("pre findCurrentPackageVersion($packageid) run time: ");
		$currentVersion = findCurrentPackageVersion($packageid, $client, $recurring=false);
screenLogPageTime("POST findCurrentPackageVersion($packageid) run time: ");
		if(!$currentVersion) $errors[] = "This schedule no longer exists.";
		else {
			globalRedirect("service-irregular.php?packageMustExist=1&packageid=$packageid");
			exit;
		}
	}
}

//screenLogPageTime("up to frame load: ");
include "frame.html";
// ***************************************************************************

if($conflicts) {
	if(clientAcceptsEmail($client, array('autoEmailScheduleChanges'=>true))) {
		echo <<<HTML
<script language='javascript' src='common.js'></script>
<script language='javascript'>
var url ="notify-schedule.php?packageid=$packageid&clientid=$client&newPackage=0&offerConfirmationLink=1";
openConsoleWindow('notificationcomposer', url, 600, 600);
notifyUserOfScheduleChange($packageid, 'silentDenial');
</script>
HTML;
	}

	include "nonrecurring-confict-resolution-form.php";
	include "frame-end.html";
	exit();
}
if($errors) {
	echo "<font color='red'>WARNING:<ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
	if($packageMustExist && !$oldPackage) {
		include "frame-end.html";
		exit();
	}
}

if($package['cancellationdate']) {
	dumpCancellationNotice($package);
	echo "<p>";
}

if($client) {
	$clientWidget = ""; //"Client: <b>".fullname($clientDetails).'</b> ';
}
else {
  $clientWidget =
    "Client: <div id='clientDiv' onClick='pickClient(\"clientDiv\")' '
      style='font-size:1.0em;font-weight:bold;padding:2px;padding-left:3px;padding-right:3px;display:inline;cursor:pointer;border: solid #808080 1px;width:140px;'>
      <img src='art/spacer.gif' width=100 height=12></div>";
}
?>
<form name='nonrecurringserviceform' method=POST>
<?	hiddenElement('client', $client);
		hiddenElement('action', '');
		hiddenElement('packageid', $packageid);
		hiddenElement('editVisitsOnLoad', '');
		hiddenElement('stickAround', '');
		?>

<? if($clientWidget) {
		echo $clientWidget;// echoClientSelect("client",array('Select Provider'=>0)) ?>
<img src='art/spacer.gif' width=20 height=0>
<?
}
//if(!$client) echo "<div id='hider' style='display:none;'>";
?>
<table width=99%><tr><td>
<?
$showCalWidgets = $packageid ? 'none' : 'inline';
echo "<div id ='calWidgets' style='display:$showCalWidgets;'>";
echoButton('', 'Save & Hide Date Range', 'showDates(-1)');
echo ' ';
calendarSet('Start Date:', 'startdate', $package['startdate'], null, null, true, 'enddate');
calendarSet('End Date:', 'enddate', $package['enddate'], null, null, true, null);
//hiddenElement('oldstartdate', $package['startdate']);
//hiddenElement('oldenddate', $package['enddate']);
echo "<br></div>";
echo ' ';
if(!$roDispatcher) echoButton('showDatesButton', 'Edit Date Range', 'showDates(1)');
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173')  echoButton('xx', 'Edit Schedule Settings', 'showDates(1)');
?>
<style>
input {font-size:1em;}
select {font-size:1em;margin-left:3px;}
</style>
<?
//labeledInput('Package Price:', 'packageprice', $package['packageprice'], null, 'dollarinput');
hiddenElement('packageprice', $package['packageprice']);
//labeledInput('How to Contact:', 'howtocontact', $package['howtocontact'], null, 'VeryLongInput');
echo " ";
$prepaid = $package ? $package['prepaid'] : $_SESSION['preferences']['schedulesPrepaidByDefault'];
labeledCheckbox('Statement', 'prepaid', $prepaid, null, null, null, 'boxfirst');
echo " ";
$markstartfinish = $_SESSION['preferences']['markStartFinish'];
labeledCheckbox('Mark Start/Finish', 'markStartFinish', $markstartfinish, null, null, null, 'boxfirst');
echo " ";
$sendBillingReminders = $packageid ? $package['billingreminders'] : $_SESSION['preferences']['sendBillingReminders'];
labeledCheckbox('Send Billing Reminders', 'billingreminders', $sendBillingReminders, null, null, null, 'boxfirst');


echoButton('notesbutton', 'Notes', 'showNotes()');
echo ' ';
$arg = $package['packageid'] ? "packageid={$package['packageid']}" : "clientid=$client";
echoButton('', 'View All Notes',
			"$.fn.colorbox({href:\"service-notes-ajax.php?$arg\", width:\"750\", height:\"570\", scrolling: true, opacity: \"0.3\"});");
if(staffOnlyTEST()) {
	echo ' ';
	echoButton('', 'UVB', 'unassignedVisitsBoardEntry("hide")', '', '', 0, 'Post this schedule on the Unassigned Visits Board');
}

?>
</td>
<td align=right>
<?
if($_SESSION['staffuser']) {
	echo "<img src='art/quickstartbutton.jpg' title='Open the EZ Schedule Quick Start Guide' onClick='openConsoleWindow(\"quickstart\", \"help/EZScheduleQuickGuide2.pdf\", 600, 600);'>";
}
?>
</td>
</tr>
</table>
<?
//$activeProviderSelections = getActiveProviderSelections(null, $clientDetails['zip']);
$activeProviderSelections = availableProviderSelectElementOptions($clientDetails, null, 'Select One');

function providerLine() {
	global $package, $packageid, $preempt, $primaryProvider, $clientDetails, $activeProviderSelections;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($activeProviderSelections);	echo "[$primaryProvider]";}
	$preempt = !$packageid ? 0 : $package['preemptrecurringappts'];
	//labeledCheckbox('This schedule preempts any repeating appointments for the client:', 'preemptrecurringappts', $preempt);
	hiddenElement('preemptrecurringappts', $preempt);
	//helpButton("Click here for an explanation",
			 //"alert('If this box is checked, then any regular weekly or monthly appointments\\n that may occur during this schedule\\&#39;s period will be canceled.')");

	//echo "&nbsp;&nbsp;";
	$primaryProvider = $primaryProvider ? $primaryProvider : $clientDetails['defaultproviderptr'];
	//packageProviderSelectElement($activeProviderSelections, $clientDetails, $primaryProvider);
	selectElement('Primary Sitter', 'primaryProvider', $primaryProvider, $activeProviderSelections, $onChange="setPrimaryProvider(this, true)");
	if(staffOnlyTEST() || dbTEST('comfycreatures') || dbTEST('careypet')) {
		echo "<img src='art/spacer.gif' width=10 height=1>";
		$stos = sittersWithTimeOff($package['startdate'], $package['enddate'], $package['clientptr']);
		if($stos) {
			$pnames = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) as nm FROM tblprovider ORDER BY nm");
			foreach($pnames as $provid => $name) {
				if(!in_array($provid, array_keys($stos))) continue;
				$times =  join(', ', $stos[$provid]);
				$label = "$name: $times";
				$maxLen = 80;
				if(strlen($label) > $maxLen) $label = substr($label, 0 , $maxLen).'...';
				$options[$label] = $provid;
			}
			selectElement('Sitter Time Off', 'sittersOff', $noValue, $options, $onChange="openTimeOffCalendar(this, \"{$package['startdate']}\")");
		}
	}
}

function sittersWithTimeOff($startDate, $endDate, $clientptr) {
	$nonstarterProvids = fetchCol0(
			"SELECT providerptr
				FROM tblproviderpref 
				WHERE property = 'donotserve_$clientptr'");
	$where = "WHERE active = 1";
	if($nonstarterProvids) $where .= " AND providerid NOT IN (".join(',', $nonstarterProvids).")";
	$available = fetchCol0("SELECT providerid FROM tblprovider $where");
	for($date = $startDate; $date <= $endDate; $date = date('Y-m-d', strtotime("+ 1 day", strtotime($date)))) {
		$provids = providersOffThisDay($date);
		foreach($provids as $provid) {
			if(in_array($provid, $available)) {
				$times = timesOffThisDay($provid, $date, $completeRecords=false);
				foreach($times as $i=>$tm) if(!$tm) $times[$i] = 'all day';
				$timesByProvider[$provid][] = shortNaturalDate(strtotime($date), $noYear=true)." (".join(', ', $times).")";
			}
		}
	}
	return $timesByProvider;
}

if(!$packageid) providerLine();

echo " ";
//$editLabel = $packageid ? 'Edit Visits' : 'Create Package and Add Visits';
//echoButton('',$editLabel, 'editVisits()');
if(!$packageid) {
	echo "<p style='text-align:center;'>";
	echoButton('','Create Schedule and Add Visits', 'editVisits()', 'BigButton', 'BigButtonDown');
}

//echo "<p>";

function completionButtonHTML() {
	global $roDispatcher, $package, $packageid, $clientDetails, $client, $note, $allowDeleteButton;
	ob_start();
	ob_implicit_flush(0);
	if($roDispatcher && $packageid) {
		$note = urlencode("Changes to ".fullname($clientDetails)."'s EZ schedule starting ".shortDate(strtotime($package['startdate'])).":");
		echoButton('', 'Make Schedule Change Request', "openConsoleWindow(\"requestedit\", \"client-own-request.php?pop=1&id=$client&note=$note\", 600, 600);");
	}
	else if(!$roDispatcher) {
		$buttonLabel = $packageid ? 'Save Changes' : 'Save New Package';
		echoButton('',$buttonLabel, 'checkAndSubmit(0)');
	}
	$dest = $client ? "client-edit.php?id=$client&tab=services" : "index.php";
	echoButton('quitButton','Quit', 'document.location.href="'.$dest.'"');
	if($package['packageid']) dumpStaffAnalysisLink($package['packageid']);
if($allowDeleteButton) {
		echo "<img src='art/spacer.gif' width=20 height=1>";
		echoButton('deleteButton','Delete Schedule', 'deleteSchedule()', 'HotButton', 'HotButtonDown');
	}
	$html = ob_get_contents();
	ob_end_clean();
	return $html;
}

$saveButtonHTML = completionButtonHTML();
$notesTextRowStyle = "style='display:".($package['notes'] ? $_SESSION['tableRowDisplayMode']  : 'none').";'";
?>
<table width='100%'>

<tr id='noteslabelrow' <?= $notesTextRowStyle ?>>
<? if(staffOnlyTEST()) { ?>
<td colspan=8>Notes: <? echoButton('', 'Hide Notes', 'showNotes("hide")'); ?></td>
<? }
else {
?>
<td colspan=9>Notes: <? echoButton('', 'Hide Notes', 'showNotes()'); ?></td>
<?
}
?>
</tr>
<tr id='notestextrow' <?= $notesTextRowStyle ?>><td colspan=9><textarea id='notes' name='notes' cols=60 rows=3><?= $package['notes'] ?></textarea></tr>
<tr><td colspan=9 align=center><?  if(!$packageid || $roDispatcher) echo $saveButtonHTML;
?></td></tr>

</table><p>


<?
//screenLogPageTime("up to calendar-package-irregular-embedded.php: ");
//if(mattOnlyTEST()) {echo "CLIENTID: $clientid CLIENT: $client CLIENTDETAILS: ".print_r($clientDetails, 1);exit;}
// ###########################################################
// ###########################################################
if($packageid) include "calendar-package-irregular-embedded.php";
// ###########################################################
// ###########################################################
//screenLogPageTime("POST calendar-package-irregular-embedded.php: ");

if($packageid) providerLine();

scheduleHistoryLink($packageid);

if($packageid) {
	$visits = getAllScheduledAppointments($packageid, $where=' canceled IS NULL');
	$lastVisitId = $visits ? current(array_reverse(array_keys($visits))) : '-999';
	if(FALSE && mattOnlyTEST() && !$_SESSION['preferences']['homeSafeSuppressed']) {
		echo "<img src='art/spacer.gif' width=10 height=1>";
		echoButton('', 'Send Home Safe Request', "sendHomeSafeRequest($lastVisitId)");
	}
	/* JS
	function sendHomeSafeRequest(requestid) {
			var url = 'comm-home-safe-composer.php?requestid='+requestid;
			openConsoleWindow('homesafe', url, 650,600);
	}
	*/
}


$client = $client['clientid'];

$allRawNames = "client,Client,startdate,Start Date,enddate,End Date,suspenddate,Suspend Date,resumedate,Resume Date,cancellationdate,Cancellation Date";
if($serviceLineFields) $allRawNames .= ",$serviceLineFields";
$prettyNames = "'".join("','",explode(',',$allRawNames))."'";

$startDateForPackageId = fetchRow0Col0("SELECT startdate FROM tblservicepackage WHERE packageid ='$packageid' LIMIT 1", 1);
?>



<div style='height:100px;'></div>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>



<? dumpHistoryLinkJSFrag(); ?>

setPrettynames(<?= $prettyNames ?>);

var savedStartDate = "<?= 
	$oldPackage['startdate'] ? shortDate(strtotime($oldPackage['startdate'])) : (
	$startDateForPackageId ? shortDate(strtotime($startDateForPackageId)) : ''); // IT'S OK IF oldPackage IS NULL. ?>";

function checkAndSubmit() {
<? if(dbTEST('familypetsitters') || staffOnlyTEST()) { ?>
	//alert(document.getElementById('startdate').value);
	if(savedStartDate != document.getElementById('startdate').value) {
		// if startdate is has changed, is valid and is in the past...
		var start = document.getElementById('startdate').value;
		if(start && validateUSDate(start))
			if(isPastDate(start) && !confirm(start+" is in the past.  Continue?"))
				return;
		savedStartDate = document.getElementById('startdate').value;
	}
<? } ?>
	if(checkForm())
    document.nonrecurringserviceform.submit();
}

function saveSubmitAndStay() {	// Dates are being saved.
	if(checkForm()) {
		document.getElementById('action').value = 'stay';
    document.nonrecurringserviceform.submit();
	}
}

function checkForm() {
	//if(scheduleIsIncomplete()) return;
	//return;

	if(!MM_validateForm('client', '', 'R',
		  'startdate', '', 'R',
		  'startdate', '', 'isDate',
		  'enddate', '', 'R',
		  'enddate', '', 'isDate',
		  'startdate', 'enddate', 'datesInOrder',
		  'departuredate', '', 'isDate',
		  'returndate', '', 'isDate',
		  'cancellationdate', '', 'isDate',
		  'cancellationdate', 'NOT', 'isPastDate'))
		  return false;
		  
	if(document.getElementById('action').value == 'delete') return true;  // do not worry about extreme lengths

	var start = getMDYTime(document.getElementById('startdate').value);
	var end = getMDYTime(document.getElementById('enddate').value);
	var duration = Math.floor((end - start)/(24 * 60 * 60 * 1000));
	var maxDur = 180;
	if(duration > maxDur) {
		alert("Nonrepeating visit packages cannot last longer than "+maxDur+" days.\nThis package is "+duration+" days long."+
		       "\nYou can use a repeating package instead if service really will last longer than "+maxDur+" days,"+
		       "\nand set a cancellaton date to limit the schedule's duration.");
		return false;
	}
<? if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo "findOrphans();" ?>

	return true;

}

function openTimeOffCalendar(el, startdate) {
	var provid = el.options[el.selectedIndex].value;
	var args='provid='+provid+'&editable=1&date='+startdate;
	openConsoleWindow('timeoffcalendar', 'timeoff-sitter-calendar.php?'+args,750,650);

}

function showDates(show) {
<? if(dbTEST('familypetsitters') || staffOnlyTEST()) { ?>
	//alert(document.getElementById('startdate').value);
	if(savedStartDate != document.getElementById('startdate').value) {
		// if startdate is has changed, is valid and is in the past...
		var start = document.getElementById('startdate').value;
		if(start && validateUSDate(start))
			if(isPastDate(start) && !confirm(start+" is in the past.  Continue?"))
				return;
		savedStartDate = document.getElementById('startdate').value;
	}
<? } ?>

	if(show == -1) {
		saveSubmitAndStay();
		return;
	}

	document.getElementById('calWidgets').style.display = show ? 'inline' : 'none';
	document.getElementById('showDatesButton').style.display = show ? 'none' : 'inline';
}

function update(attribute, value) { // value = totalcharge,totalrate,numvisits
<? if(FALSE && mattOnlyTEST()) { ?>
$.fn.colorbox({html:value, width:"750", height:"470", scrolling: true, opacity: "0.3"});
<? } 
?>
	if(value == null) value = '';
	if(value.indexOf('MESSAGE:FAILURE-') == 0) alert(value.substring('MESSAGE:FAILURE-'.length));
	document.getElementById("stickAround").value =  1;
	checkAndSubmit();
}

function getMDYTime(date) {
	var datearr = mdy(date);
	time = new Date();
	time.setMonth(datearr[0]-1);
	time.setDate(datearr[1]);
	time.setFullYear(datearr[2]);
	return time.getTime();
}


function clientPicked(clientid,clientname,provider,target) {
	document.getElementById(target).innerHTML = clientname;
	document.getElementById('client').value = clientid;
	var sel = document.getElementById('first_providerptr_1');
	for(var i=0;i<sel.options.length;i++)
	  if(parseInt(sel.options[i].value) == parseInt(provider)) {
		  break;
	  }
	var prefixes = ['first_','between_','last_'];
	for(var i=0;i<prefixes.length;i++)
	  for(var p=1; sel = document.getElementById(prefixes[i]+'providerptr_'+p) != null; p++) {
	    document.getElementById(prefixes[i]+'providerptr_'+p).value = provider;
	  }
  var xh = getxmlHttp();
  xh.open("GET",'get-client-rates-ajax.php?client='+clientid,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { setClientChargesAndDisplayTotals(xh.responseText); } }
  xh.send(null);

  updatePets();
}




var clientCharges = <?= $client ? getClientChargesJSArray($client) : '[]' ?>;

function pickClient(targetId) {
	var wide=450;
	var high=500;
	url = 'client-picker.php?target='+targetId;
	var w = window.open("",'clientPicker',
		'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	w.document.location.href=url;
	if(w) w.focus();

}

function editVisits(noCheck) {
<? if(dbTEST('familypetsitters') || staffOnlyTEST()) { ?>
	//alert(document.getElementById('startdate').value);
	if(savedStartDate != document.getElementById('startdate').value) {
		// if startdate is has changed, is valid and is in the past...
		var start = document.getElementById('startdate').value;
		if(start && validateUSDate(start))
			if(isPastDate(start) && !confirm(start+" is in the past.  Continue?"))
				return;
		savedStartDate = document.getElementById('startdate').value;
	}
<? } ?>
	var packageid= document.getElementById("packageid").value;
	if(!noCheck) {
		document.getElementById("editVisitsOnLoad").value =  1;
		if(!checkAndSubmit()) return;
	}
	/*
	// create the package
	var wide=800;
	var high=500;
	var sel= document.getElementById("primaryProvider");
	var provider= sel.options[sel.selectedIndex].value;
	//var extraDoneAction = "&doneActionExtra="+escape("window.opener.document.getElementById('quitButton').click()");
	url = "calendar-package-irregular.php?packageid="+packageid+'&primary='+provider;//+extraDoneAction;
	var w = window.open("",'packagepreview',
		'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	w.document.location.href=url;
	if(w) w.focus();
	*/
}

function editSurcharge(elId, review) {
	var el = document.getElementById(elId);
	var reason = el.value ? el.value : '';
	if(!review && reason) return;
	var promptStr = review ? "Reason for adjustment and/or bonus:" : "Please supply a reason for this change.";
	promptStr += "\n'Cancel' will erase this reason."
	reason = prompt(promptStr, reason);
	el.value = reason;
}

function deleteSchedule() {
	if(!confirm("Delete this schedule and all associated visits?")) return
	document.getElementById('action').value = 'delete';
	checkAndSubmit();

}

<?
   dumpPopCalendarJS(); ?>

function mouseCoords(ev){  // for pets and weekday widgets
	if(ev.pageX || ev.pageY){
		return {x:ev.pageX, y:ev.pageY};
	}
	return {
		x:ev.clientX + document.body.scrollLeft - document.body.clientLeft,
		y:ev.clientY + document.body.scrollTop  - document.body.clientTop
	};
}


function showNotes(hide) {
	document.getElementById('notesbutton').style.display = hide ? 'inline' : 'none';
	document.getElementById('noteslabelrow').style.display = hide ? 'none' : '<?= $_SESSION['tableRowDisplayMode'] ?>';
	document.getElementById('notestextrow').style.display = hide ? 'none' : '<?= $_SESSION['tableRowDisplayMode'] ?>';

}

function unassignedVisitsBoardEntry(hide) {
	$.fn.colorbox({href:"unassigned-visits-board-nrsched-editor.php?id=<?= $packageid ?>", width:"750", height:"570", iframe: true, scrolling: true, opacity: "0.3"});
}

<?
if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { ?>
function findOrphans() {
	var start = document.getElementById('startdate').value;
	start = start.split('/');
	start = start[2]+'-'+(String)(start[0]/100).substring(2)+'-'+(String)(start[1]/100).substring(2);
	var end = document.getElementById('enddate').value;
	end = end.split('/');
	end = end[2]+'-'+(String)(end[0]/100).substring(2)+'-'+(String)(end[1]/100).substring(2);

	var orphans = new Array();
	$('.appday').each(function(index, element) {
		var day = element.id.substring('box_'.length);
		if(day < start || day > end) orphans[orphans.length] = day;
  });
  //alert(orphans.join(', '));
}

<?

}




if($editVisitsOnLoad) echo "editVisits('nocheck');\n" ;

// calculate totals
$charge = 0;
$rate = 0;
$numappts = 0;
if($packageid) {
	//$history = findPackageIdHistory($packageid, $client, false);
	//$history[] = $packageid;
	$packageHistory = $packageHistory ? $packageHistory : findPackageIdHistory($packageid, $client, false);
	$history = join(',', $packageHistory);
	$appts = fetchAssociations("SELECT * FROM tblappointment WHERE packageptr IN ($history) ORDER BY date, starttime");
	foreach($appts as $appt) {
		if($appt['canceled']) continue;
		$charge += $appt['charge']+$appt['adjustment'];
		$rate += $appt['rate']+$appt['bonus'];
	}
	$numappts = count($appts);
}

?>
showDates(<?= !$packageid ?>);
</script>
<?
if(!$packageid) echo "<script language='javascript' src='common.js'></script>";
include "js-refresh.php";


// ***************************************************************************

include "frame-end.html";
?>
