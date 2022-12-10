<?
//service-repeatingV2.php
// This version introduces multi-week recurring schedules.

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "zip-lookup.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "preference-fns.php";
require_once "service-fns.php";

require_once "gui-fns.php";
include "weekday-grid.php";
include "petpick-grid.php";
include "time-framer-mouse.php";

// Determine access privs
$locked = locked('o-');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

	//requestIsJSON() ... getJSONRequestInput()
if(requestIsJSON()) $INPUT_ARRAY = getJSONRequestInput();
else $INPUT_ARRAY = $_REQUEST;

extract($INPUT_ARRAY);


if(isset($convert) && $convert) {
	doQuery("UPDATE tblrecurringpackage SET monthly=0 WHERE packageid=$packageid LIMIT 1");
}

$destination = null;
$conflicts = null;
$errors = array();
if($_REQUEST['KILL'] && mattOnlyTEST()) { // DEV only
	$clientid = fetchRow0Col0("SELECT clientptr FROM tblrecurringpackage WHERE packageid={$_REQUEST['KILL']} LIMIT 1", 1);
	updateTable('tblrecurringpackage', array('current'=>0), "packageid={$_REQUEST['KILL']}", 1);
	globalRedirect("client-edit.php?id=$clientid&tab=services");
	exit;
}
if($save) {
	$_POST = $INPUT_ARRAY; // to make the transition to JSON easier
//if(mattOnlyTEST()) logError("NEW SCHEDULE SAVE START: [packageid: $packageid]");
	
	if($packageid) {
		$oldPackage = getRecurringPackage($packageid);
		if(!$oldPackage['current']) {
			$currentPackage = fetchRow0Col0("SELECT packageid FROM tblrecurringpackage WHERE current = 1 AND clientptr = {$_POST['client']} LIMIT 1");
			$errors[] = 
				"This version of the package is no longer current, so changes to it cannot be saved.<br>"
				. "Please note the changes you tried to make and then <a href='service-repeatingV2.php?packageid=$currentPackage'>Edit the Current Version</a>";
		}
		else {
			if($hidepackage) {
				updateTable('tblrecurringpackage', array('current'=>0), "packageid = $packageid", 1);
				$destination = "client-edit.php?id={$_POST['client']}&tab=services";
			}
			else {
				$package = saveRepeatingPackage($packageid);
				$packageid = $package ? $package['packageid'] : $packageid;
				$_SESSION['clientEditNotifyToken'] = time();
				$notify= "&notifytime={$_SESSION['clientEditNotifyToken']}&notifyschedule=$packageid";			
				if(!$conflicts)
					$destination = "client-edit.php?id={$_POST['client']}&tab=services$notify";
			}
		}
	}
	else {
//if(mattOnlyTEST()) logError("REALLY NEW SCHEDULE SAVE START");
		if($newPackageId = saveNewRepeatingPackage())
			$package = getRecurringPackage($newPackageId); // for the sake of setClientPreference, below
		$_SESSION['clientEditNotifyToken'] = time();
		$notify= "&notifytime={$_SESSION['clientEditNotifyToken']}&notifyschedule=$newPackageId";			
		$destination = "client-edit.php?id={$_POST['client']}&tab=services$notify";
	}
	if(!$errors) {
		// for all providers associated with the old and new schedules, generate memos
		$versions = array();
		if($oldPackage) $versions[] = $oldPackage['packageid']; 
		if($packageid) $versions[] = $packageid;
		if($newPackageId) $versions[] = $newPackageId;
		$provs = fetchCol0("SELECT providerptr FROM tblservice WHERE packageptr IN (".join(',', $versions).")");
		foreach(array_unique($provs) as $provptr) {
			if($provptr) {
				require_once "provider-memo-fns.php";
				makeClientScheduleChangeMemo($provptr, $_POST['client'], ($newPackageId ? $newPackageId : $packageid));
			}
		}
	}


	if(mysql_error()) $errors[] = sqlErrorMessage();
	
	if($errors)
		$payload = array('status'=>'error', 'errors'=>$errors);
	else {
		$packageid = $newPackageId ? $newPackageId : $packageid;
		setClientPreference($package['clientptr'], "justsaved_$packageid", 1);
		$destination = $destination ? $destination 
										: "service-repeatingV2.php?packageid=$packageid";
		$payload = array('status'=>'success', 'destination'=>globalURL($destination));
	}
//if(mattOnlyTEST()) logError("NEW SCHEDULE RESULT: ".json_encode($payload));
	echo json_encode($payload);
	exit;
}

// packageid or client will always be there, except for testing purposes
$client = isset($client) ? $client : '';
if($packageid) { // existing service package
  $package = getRecurringPackage($packageid);
  if($package['monthly'])
    $packageTypeSwitch = "This client already has a Monthly package.\\nDo you want to convert it to a Weekly package?";
	$services = getPackageServices($packageid);
	$client = $package['clientptr'];
	//echo '['.print_r($services,1).']';exit;
}
else { // new service
  // if $client is set, do not allow $client to be modified
	$services = array();
}


$pageTitle = ($packageid ? '' : 'New ')."Ongoing Per-visit Schedule";

if($client) {
	$clientDetails = getClient($client);
	$pageTitle .= ': '.fullname($clientDetails);
	require_once "client-flag-fns.php";
	$pageTitle .= ' '.clientFlagPanel($client, $officeOnly=false, $noEdit=true, $contentsOnly=true);
}



include "frame.html";
if($packageid && $_SESSION["staffuser"]) echo "<div style='position:absolute;left:300px;top:0px;'>
											<a href='service-recurring-retrofit.php?packageid=$packageid'>Retrofit</a></div>";
// ***************************************************************************
//print_r($conflicts);
$justSaved = $package ? getClientPreference($package['clientptr'], "justsaved_$packageid") : null;
if($justSaved) setClientPreference($package['clientptr'], "justsaved_$packageid", null);
if($justSaved && $packageid)
	$conflicts = findConflicts($client, $packageid, $recurring=1,
														$package['startdate'], 
														($package['onedaypackage'] ? $package['startdate'] : $package['enddate']));

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
	echo "<h2>Your Changes Have Been Saved</h2><hr>\n";
	// make sure $clientid is set
	$clientid = $clientid ? $clientid : ($package['clientptr'] ? $package['clientptr'] : $client);
	include "recurring-confict-resolution-form.php";
	include "frame-end.html";
	exit();
}
if($errors) {
	echo "<font color='red'>WARNING:<ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
}

if(isset($packageTypeSwitch)) {
	$url = $_SERVER['REQUEST_URI'];
	$otherurl = str_replace('repeating','monthly', $url);
?>
<script language='javascript'>
if(confirm('<?= $packageTypeSwitch ?>'))
	document.location.href='<?= $url.'&convert=1' ?>';
else 
	document.location.href='<?= $otherurl.'&convert=1' ?>';
</script>
<?
	include "frame-end.html";
	exit();
}


$allPetNames = $client ? getClientPetNames($client) : '';

$serviceTypes = getStandardRates();
$serviceSelections = array_merge(array(''=>0), getServiceSelections());
//$activeProviderSelections = getActiveProviderSelections(null, $clientDetails['zip']);
$activeProviderSelections = availableProviderSelectElementOptions($clientDetails, null, 'Select a Sitter for All Services');
makePetPicker('petpickerbox',getActiveClientPets($client), $petpickerOptionPrefix, 'narrow');
makeWeekdayGrid('weekdays');
makeTimeFramer('timeFramer');

if($package['cancellationdate']) {
	dumpCancellationNotice($package);
	echo "<p>";
}

if($client) {
	$clientWidget = ""; //$clientWidget = "Client: <b>".fullname($clientDetails).'</b> ';
}
else {
  $clientWidget =
    "Client: <div id='clientDiv' onClick='pickClient(\"clientDiv\")' ' 
      style='font-size:1.0em;font-weight:bold;padding:2px;padding-left:3px;padding-right:3px;display:inline;cursor:pointer;border: solid #808080 1px;width:140px;'>
      <img src='art/spacer.gif' width=100 height=12></div>";
}
?>
<style>
input {font-size:1em;}
select {font-size:1em;margin-left:3px;}
</style>
<form name='recurringserviceform' method=POST>
<? 
hiddenElement('client', $client); 
hiddenElement('packageid', $packageid); 
?>

<? if($clientWidget) {
		echo $clientWidget;// echoClientSelect("client",array('Select Provider'=>0)) ?> 
<img src='art/spacer.gif' width=20 height=0>
<?
}
//if(!$client) echo "<div id='hider' style='display:none;'>";
?>
<? 
if($packageid && !(mattOnlyTEST() && $package['cancellationdate'])) {
	echo "Start Date: ".shortDate(strtotime($package['startdate']));
	hiddenElement('startdate', shortDate(strtotime($package['startdate'])));
}
else
	calendarSet('Start Date:', 'startdate', $package['startdate']);
echo " ";
$prepaid = $package ? $package['prepaid'] : $_SESSION['preferences']['schedulesPrepaidByDefault'];
labeledCheckbox('Statement:', 'prepaid', $prepaid);

$effectivedate = effectiveDate($package);
hiddenElement('oldeffectivedate', $effectivedate);



if($packageid) {
	echo " ";
	calendarSet('Changes effective:','effectivedate', shortDate()); //($effectivedate ? $effectivedate : shortDate()));
	echo " <span class='tiplooks fontSize1_2em' id='effectivedateweek'></span>";
}
else hiddenElement('effectivedate', $effectivedate);

if(1 && mattOnlyTEST()) {
	//echoButton('', 'BOOP!', "updateEffectiveDateWeek()", 'BigButton', 'BigButtonDown');
	echoButton('', 'Go 2 ORIG', "document.location.href=\"service-repeatingORIGINAL.php?packageid=$packageid\"", 'BigButton', 'BigButtonDown');
	echo " ";
	if($packageid) 
		echoButton('', 'KILL', "if(confirm(\"KILL?\")) document.location.href=\"\?KILL=$packageid\"",
								'HotButton', 'HotButtonDown', null, 'Just set current to null -- DEV ONLY');
}

?>
<p></p>
<?
//packageProviderSelectElement($activeProviderSelections, $client, $clientDetails['defaultproviderptr']);
selectElement('Schedule Sitter', 'primaryProvider', 0/*$clientDetails['defaultproviderptr']*/, $activeProviderSelections, $onChange="setPrimaryProvider(this, true)");
unset($activeProviderSelections[current(array_keys($activeProviderSelections))]); // added back in by serviceLine
//packageProviderSelectElement($activeProviderSelections, $clientDetails, $clientDetails['defaultproviderptr'], true /*notabs*/);
//	selectElement('Primary Sitter', 'primaryProvider', $choice, $options, $onChange="setPrimaryProvider(this, $noTabs)");
echo ' '; 
$arg = $package['packageid'] ? "packageid={$package['packageid']}" : "clientid=$client";
echoButton('', 'View All Notes', 
			"$.fn.colorbox({href:\"service-notes-ajax.php?$arg\", width:\"750\", height:\"570\", scrolling: true, opacity: \"0.3\"});");
$numberNames = explodePairsLine('3|Three||4|Four||5|Five||6|Six||7|Seven||8|Eight');
$options = explodePairsLine('Every Week|1||Every Other Week|2||Every Three Weeks|3||Every Four Weeks|4');
for($w=3; $w <= multiWeeksMax(); $w++)
	$options["Every {$numberNames[$w]} Weeks"] = $w;
$weeksValue = $package['weeks'] ? $package['weeks'] : 1;
selectElement(" Schedule repeats:", 'weeks', $weeksValue, $options, $onChange='weeksChanged(this)');
$weeksNotice = "";
if($packageid && $weeksValue == 1) {  
	// Create a notice that will be shown ONCE, the first time a manager chooses a "weeks" value greater that "1"
	$weekStart = $package['startdate'];
	$dayIndex = date('N', strtotime($weekStart));
	if($dayIndex < 7) $weekStart = date('Y-m-d', strtotime("- $dayIndex days", strtotime($weekStart)));
	$todayLine =
		date('Y-m-d') < $package['startdate'] ? "" : "Today is Week #".(weekNumber(date('Y-m-d'), $package)+1).".\\n\\n";
	$weeksNotice = 
		"Week 1 for this schedule began on ".longerDayAndDate(strtotime($weekStart)).".\\n\\n"
		."The Week Number for every ensuing week is based on this, and never changes.\\n\\n"
		.$todayLine
		//.date('Y-m-d')." => ".str_replace("\n", "<br>", print_r($package, 1))
		."The Week Number for the Effective Date appears next to it.";
}

//selectElement($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null)
echo " ";
$showWeeks = 12;
fauxLink("Week Chart", "showWeekChart($showWeeks)", 0, "Show the calendar dates for each week over the next $showWeeks weeks", 'weekchartlink');
//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)echo "<p>";

recurringServiceTableV2($package, $services, $activeProviderSelections, $serviceSelections, true);


//<tr><td colspan=5>&nbsp;</td><td colspan=2 style='padding-right:2px;text-align:right;font-weight:bold;'>Weekly Adjustment </td>
//<td><input size=2 id='weeklyadjustment' name='weeklyadjustment' onBlur='displayTotals();' value='< ?= $package['weeklyadjustment'] ? >'></td><td colspan=2>&nbsp;</td></tr>
hiddenElement('weeklyadjustment', $package['weeklyadjustment']);
$cancelDisplay = $package['packageid'] && !$package['cancellationdate'] ? "style=\"{$_SESSION['tableRowDisplayMode']}\"" : 'style="display:none;"';
$notesButtonStyle = "style='display:".(!$package['notes'] ? $_SESSION['tableRowDisplayMode']  : 'none').";'";
$notesTextRowStyle = "style='display:".($package['notes'] ? $_SESSION['tableRowDisplayMode']  : 'none').";'";
?>
<tr id='notesbuttonrow' <?= $notesButtonStyle ?>><td colspan=9 style='padding-top:20px;'><? echoButton('', 'Notes', 'showNotes()') ?></td></tr>
<tr id='noteslabelrow' <?= $notesTextRowStyle ?>><td colspan=9>Notes:</td></tr>
<tr id='notestextrow' <?= $notesTextRowStyle ?>><td colspan=9><textarea id='notes' name='notes' cols=60 rows=3><?= $package['notes'] ?></textarea></tr>
<tr><td colspan=9>&nbsp;</td></tr>
<tr name='CancellationDetails' <?= $cancelDisplay ?>>
  <td colspan=4><? calendarSet('Suspend Service On:', 'suspenddate', $package['suspenddate']) ?>
  </tr>
<tr name='CancellationDetails' <?= $cancelDisplay ?>>
  <td colspan=4><? calendarSet('Resume Service On:', 'resumedate', $package['resumedate']) ?>
  <td colspan=5 style='display:none'>&nbsp;&nbsp;&nbsp;&nbsp;<? labeledInput('Reason:', 'cancellationreason', $package['cancellationreason'], null, 'emailInput') ?>
<tr name='CancellationDetails' <?= $cancelDisplay ?>>
  <td colspan=5><? calendarSet('Cancel Schedule On:', 'cancellationdate', $package['cancellationdate']) ?>
  <? hiddenElement('hidepackage', ''); // set by hidePackage() in service-fns.php ?>
  </tr>
</tr>
<tr><td colspan=9 align=center><? 

$buttonLabel = $packageid ? 'Save Changes' : 'Save New Schedule';
if($roDispatcher && $packageid) {
	$note = urlencode("Changes to ".fullname($clientDetails)."'s recurring schedule:");
	echoButton('', 'Make Schedule Change Request', "openConsoleWindow(\"requestedit\", \"client-own-request.php?pop=1&id=$client&note=$note\", 600, 600);"); 
}
else if(!$roDispatcher) echoButton('',$buttonLabel, 'checkAndSubmitMultiWeekSchedule(0)');

$dest = $client ? "client-edit.php?id=$client&tab=services" : "index.php";
echoButton('','Quit', 'document.location.href="'.$dest.'"');
echoButton('','Preview Visits', 'previewPackage()'); 
if($package['packageid']) dumpStaffAnalysisLink($package['packageid']);
echo " ";
scheduleHistoryLink($package['packageid']);

?></td</tr>

</table>

<?
//if(!$client) echo "</div>"; // hider

$allRawNames = "client,Client,startdate,Start Date,suspenddate,Suspend Date,resumedate,Resume Date,cancellationdate,Cancellation Date,effectivedate,Changes effective:";
if($serviceLineFields) $allRawNames .= ",$serviceLineFields";
$prettyNames = "'".join("','",explode(',',$allRawNames))."'";


?>



<div style='height:100px;'></div>
<!-- script language='javascript' src='check-form.js'>/* for mdy() */</script -->
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
<? dumpHistoryLinkJSFrag(); ?>

setPrettynames(<?= $prettyNames ?>);	

var serviceLineConstraints = <?= $serviceLineConstraints.']]' ?>;
var saving = false;

<? $startDateConstraint0 = $_SESSION['staffuser'] ? '' : "'startdate', 'NOT', 'isPastDate',"; ?>
var newServiceConstraints = <?= $packageid ? '[]' : 
  "[$startDateConstraint0
		'suspenddate', 'NOT', 'isPastDate',
		'resumedate', 'NOT', 'isPastDate']" ?>;


function checkAndSubmitMultiWeekSchedule(anotherBool) {
	if(saving) {
		alert('Changes are being saved.  Please wait...');
		return;
	}
	if(checkForm(anotherBool)) {
		//var x = $('.BlockContent-body');alert(x.length);
		//alert($('div').toArray());
		saving = true;
		$('.BlockContent-body').busy("busy");
		//busyImage(); // common.js
    submitMultiWeekScheduleForm();
	}
}

function submitMultiWeekScheduleForm() {
	//var obj = $('form').serializeJSON();
	var obj = constructJSONPayload();
	//alert(JSON.stringify(obj)); //return;
	//return;
	$.ajax({
			type: 'POST',
			url: 'service-repeatingV2.php',
	    dataType: 'json', // comment this out to see script errors in the console
			data: JSON.stringify(obj),
	    contentType: 'application/json',
	    processData: false,
			success: function(data) {
				// status: error
				if(data['status'] == 'error') {
					let errorSummary = 
						"ERRORS:\n"
						+data.join("\n");
					alert(errorSummary);
				}
				// status: success
				else { 
					//alert("SUCCESS: "+JSON.stringify(data));
					//alert("SUCCESS: "+JSON.stringify(data['destination']));
					document.location.href=data['destination'];
				}
			},
			failure: function(data) {
					alert(JSON.stringify(data))
			}
	});
}

function constructJSONPayload() {
	var payload = {
		save: 1,
		client: $('#client').val(), 
		packageid: $('#packageid').val(), 
		prepaid: ($('#prepaid').is(':checked') ? 1 : 0),
		monthly: ($('#monthly')[0] ? 1 : 0),
		weeks: $('#weeks').val(),
		firstsunday: '',
		startdate: $('#startdate').val(),
		effectivedate: $('#effectivedate').val(),
		notes: $('#notes').val(),
		suspenddate: $('#suspenddate').val(),
		resumedate: $('#resumedate').val(),
		cancellationdate: $('#cancellationdate').val(),
		cancellationreason: $('#cancellationreason').val(),
		services: []
		};
	var servicefields = ['daysofweek', 'providerptr', 'timeofday', 'servicecode' ,'pets', 'charge', 'adjustment', 'rate' ,'bonus'];
	let lines = [];
	for(var w = 0; w < payload.weeks; w++) {
		let visibleCount = $("#"+w+"_services_visible").val();
		for(var i=1; i <= visibleCount; i++) {
			// ignore incomplete services
			let ok = true, line = {week: ""+w};
			for(var f=0; f < servicefields.length; f++) {
				let fld = servicefields[f];
				let v = $("#"+w+"_"+fld+"_"+i).val();
				if(v == '' && !(fld == 'adjustment' || fld == 'bonus' || fld == 'pets')) ok = false;
				else line[fld] = v;
			}
			//alert("[["+ok+"]] "+JSON.stringify(line));
			if(ok) lines.push(line);
		}
		if(lines.length > 0) payload.services = lines;
	}
	//alert(JSON.stringify(payload));
	return payload;
	/* format:
	{	client: "",
		prepaid: "",
		monthly: "",
		weeks: "",
		firstsunday: "",
		startdate: "",
		effectivedate: "",
		services: [
			{week: "", daysofweek:"", providerptr: "", timeofday: "", servicecode: "", pets: "", charge: "", adjustment: "", 
				rate: "", bonus: ""}
			{week: "", daysofweek:"", providerptr: "", timeofday: "", servicecode: "", pets: "", charge: "", adjustment: "", 
				rate: "", bonus: ""}
		]
		...
	}
	*/
	
}
	
function checkForm(anotherBool) {
	for(var i=0; i < <?= multiWeeksMax() ?>; i++) setButtonDivElements(i+'_');
	var args = ['client', '', 'R',
		  'startdate', '', 'R',
		  'startdate', '', 'isDate',
		  'weeklyadjustment', '', 'FLOAT',
		  'suspenddate', '', 'isDate',
		  'resumedate', '', 'isDate',
		  'cancellationdate', '', 'isDate',
		  'effectivedate', '', 'isDate',
		  'suspenddate', 'resumedate', 'datesInOrder',
		  'suspenddate', 'resumedate', 'inseparable',
		  'resumedate', 'suspenddate', 'inseparable',
		  //'suspenddate', 'NOT', 'isPastDate',  // allow past dates for suspension/resumption, but this is a no-op
		  //'resumedate', 'NOT', 'isPastDate',
		  'cancellationdate', 'NOT', 'isPastDate'];
		  
	for(var i=0;i<newServiceConstraints.length;i++) 
		args[args.length] = newServiceConstraints[i];

	$noServices = true;
	for(var i=0;i<serviceLineConstraints.length;i++) {
		var servicetype = serviceLineConstraints[i][0];
		if(document.getElementById(servicetype).value > 0) {
			for(var j = 1; j < serviceLineConstraints[i].length;j++)
			  args[args.length] = serviceLineConstraints[i][j];
			$noServices = false;
		}
	}
	if($noServices) {
		args[args.length] = "No <?= $serviceLineLabel ?> has been fully specified.";
		args[args.length] = '';
		args[args.length] = 'MESSAGE';
	}
	
	var ok = MM_validateFormArgs(args);
	var effectivedate = document.getElementById('effectivedate').value;
	
	if(ok && effectivedate) {
	// check that effectivedate < $SESSION['preferences']['recurringScheduleWindow'] days in the future
		var oldeffectivedate = document.getElementById('oldeffectivedate').value;
		if(effectivedate != oldeffectivedate 
				&& isPastDate(effectivedate)) {
			var remedy = oldeffectivedate ? "revert Effective Date to "+oldeffectivedate : 'make changes effective immediately';
			if(confirm("The Effective Date you supplied is in the past.\n\nOK to "+remedy))
				document.getElementById('effectivedate').value = oldeffectivedate;
			else ok = false;
		}
		else {
			var daysahead = ((makeADate(effectivedate).getTime()-new Date())) / (3600 * 1000 *24);
			var limit = <?= ($limit = $_SESSION['preferences']['recurringScheduleWindow'] ? $_SESSION['preferences']['recurringScheduleWindow'] : 30) ?>;
			if(daysahead > limit) {
				var lastDay = '<?= longDate(strtotime("+$limit days")) ?>';
				alert("Effective date may not be later than "+lastDay+" ("+limit+" days from now)");
				ok = false;
			}
		}
		var cancellationdate = document.getElementById('cancellationdate').value;
		if(cancellationdate && !datesInOrder(effectivedate, cancellationdate))
			document.getElementById('effectivedate').value = cancellationdate; //'';
	}
	return ok;
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

function previewPackage() {
	if(!checkForm()) return;	
	setButtonDivElements('');
	var DataToSend = formArguments(document.recurringserviceform);
	var wide=800;
	var high=500;
	var clientName= '<?= fullname($clientDetails) ?>';
	url = "package-preview.php?"+DataToSend+'&clientName='+escape(clientName);
	var w = window.open("",'packagepreview',
		'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	w.document.location.href=url;
	if(w) w.focus();
}

function daysOfWeekUpdated(el) {  // called from weekday-grid
	displayTotals();
}

function displayTotals() {
	for(var w=0; w < <?= multiWeeksMax(); ?>; w++)
		displayWeekTotals(w);
}

function displayWeekTotals(week) {
  var totals = totalLineRatesAndCharges('',week+'_');
  var weeklyadjustment = parseFloat(document.getElementById('weeklyadjustment').value); // UNUSED
  totals[1] += isNaN(weeklyadjustment) ? 0 : weeklyadjustment;
	document.getElementById(week+'_weeklytotals').innerHTML =
	  'Total Weekly Charge: '+parseFloat(totals[1]).toFixed(2)+
	  '<img src="art/spacer.gif" width=20 height=1>Total Weekly Rate: '+parseFloat(totals[0]).toFixed(2);
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

function toggleCharges(elId) {
	var el = document.getElementById(elId);
	var onstate = '<?= $_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block' ?>';
	var newstate = document.getElementById(elId+'_charge').style.display == 'none' ? onstate : 'none';
	document.getElementById(elId+'_charge').style.display = newstate;
	document.getElementById(elId+'_adj').style.display = newstate;
	document.getElementById(elId+'_rate').style.display = newstate;
	document.getElementById(elId+'_bonus').style.display = newstate;
	document.getElementById(elId+'_surcharge').style.display = newstate;
	var anyOn = onstate == newstate;
	if(!anyOn) {
		var id;
		var tds = document.getElementsByTagName('td');
		for(var i=0; i< tds.length; i++)
			if((id = tds[i].id) != null &&
					id.indexOf('_charge') > 0 &&
					id.indexOf('_charge') == id.length - '_charge'.length &&
					tds[i].style.display != 'none' &&
					tds[i].style.display != null &&
					tds[i].style.display != ''
					)
				anyOn = true;
	}

	newstate = anyOn ? onstate : newstate;
	document.getElementById('Charge_header').style.display = newstate;
	document.getElementById('Adjust_header').style.display = newstate;
	document.getElementById('Rate_header').style.display = newstate;
	document.getElementById('Bonus_header').style.display = newstate;
}


function clientPicked(clientid,clientname,provider,target) {
	document.getElementById(target).innerHTML = clientname;
	document.getElementById('client').value = clientid;
	var sel = document.getElementById('providerptr_1');
	for(var i=0;i<sel.options.length;i++)
	  if(parseInt(sel.options[i].value) == parseInt(provider)) {
		  break;
	  }
	for(var p=1; sel = document.getElementById('providerptr_'+p) != null; p++) {
	  document.getElementById('providerptr_'+p).value = provider;
	}
  var xh = getxmlHttp();
  xh.open("GET",'get-client-rates-ajax.php?client='+clientid,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { setClientChargesAndDisplayTotals(xh.responseText); } }
  xh.send(null);

  updatePets();
}

function showNotes() {
	document.getElementById('notesbuttonrow').style.display = 'none';
	document.getElementById('noteslabelrow').style.display = '<?= $_SESSION['tableRowDisplayMode'] ?>';
	document.getElementById('notestextrow').style.display = '<?= $_SESSION['tableRowDisplayMode'] ?>';
	
}

<? if(mattOnlyTEST()) { ?>
<? } ?>

<? dumpServiceRateJSV2();
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

<? 
//$allPetNames = array();
dumpWeekDayGridJS('weekdays');
dumpPetGridJS('petpickerbox',$allPetNames);
dumpTimeFramerJS('timeFramer');

?>
nullPetsLabel = '';
nullWeekdaysLabel = '';
nullTimeFrameLabel = '';
displayTotals();

weeksChanged(document.getElementById('weeks'));
updateEffectiveDateWeek();
$('#effectivedate').change(updateEffectiveDateWeek);
document.getElementById('effectivedate').addEventListener("change", updateEffectiveDateWeek);
//document.getElementById('effectivedate').onchange = 'updateEffectiveDateWeek()';
//alert(document.getElementById('effectivedate').onchange);
</script>
<img src='art/spacer.gif' width=1 height=200>
<?


// ***************************************************************************

include "frame-end.html";
?>
