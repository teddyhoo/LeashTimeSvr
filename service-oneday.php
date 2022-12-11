<?
//service-oneday.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "client-fns.php";
require_once "pet-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";
require_once "invoice-fns.php";
require_once "appointment-fns.php";

require_once "gui-fns.php";
include "weekday-grid.php";
include "petpick-grid.php";
include "time-framer-mouse.php";

$locked = locked('o-');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract($_REQUEST);

$destination = null;
$conflicts = null;
$errors = array();
if($_POST) {
	$viewSurcharges = !isset($_POST['viewSurcharges']) || $_POST['viewSurcharges']; // always view after conflicts
	if($action == 'quit') { // from conflicts page
		$popCalendar = "openConsoleWindow(\"viewcalendar\", \"calendar-package-nr.php?packageid=$packageid$notify\", 900, 700)";
	}  
	
	else if(isset($killAppointments)) {
		foreach($_POST as $key => $label) {
			if(strpos($key, 'appt_') !== FALSE)
				$apptids[] = substr($key, 5);
		}
		deleteAppointments("appointmentid IN (".join(',', $apptids).")");
		$destination = "client-edit.php?id={$_POST['client']}&tab=services";
	}
	else if(isset($packageid)) {
		$notify= '';
		require_once "discount-fns.php";
		if($discount == -1) $scheduleDiscount = -1;
		else if($discount) {
			if(strpos($discount, '|')) $discount = substr($discount, 0, strpos($discount, '|'));
			$currentDiscount = getCurrentClientDiscount($_POST['client']);
			if($discount != $currentDiscount['discountptr'])
				$scheduleDiscount = 
					array('clientptr'=>$_POST['client'], 'discountptr'=>$discount, 'start'=>date('Y-m-d'), 'memberid'=>$memberid);
			else $scheduleDiscount = $currentDiscount;
		}		
		
		$packageProviders = array();
		if($packageid) {
			$oldPackage = getNonrecurringPackage($packageid);
			if(!$oldPackage['current']) {
				$currentPackage = findCurrentPackageVersion($packageid, $_POST['client'], false);
				$errors[] = 
					"This version of the package is no longer current, so changes to it cannot be saved.<br>"
					. "Please note the changes you tried to make and then <a href='service-oneday.php?packageid=$currentPackage'>Edit the Current Version</a>";
			}
			else {
				$package = saveNonrepeatingPackage($packageid);
				$packageid = $package ? $package['packageid'] : $packageid;
				$_SESSION['clientEditNotifyToken'] = time();
				$notify= "&notifytime=".$_SESSION['clientEditNotifyToken']."&notifyschedule=$packageid";
				if(!$conflicts) {
					if(!$viewSurcharges) $destination = "client-edit.php?id={$_POST['client']}&tab=services$notify";
					else $popCalendar = "openConsoleWindow(\"viewcalendar\", \"calendar-package-nr.php?packageid=$packageid$notify&primaryProvider=$primaryProvider\", 900, 700)";
				}
					
				foreach(getServicesForPackage($packageid, 0) as $serv) $packageProviders[] = $serv['providerptr'];
			}
		}
		else {
			$newPackageId = saveNewNonrepeatingPackage();
			if(strtotime($startdate) <= strtotime(date('Y-m-d')))  {
				//require_once "invoice-fns.php";
				//createBillableForNonrecurringPackage(getPackage($newPackageId, 'N'));
			}
			$notify= "&notifytime=".time()."&notifynewschedule=$newPackageId";
			if(!$viewSurcharges) $destination = "client-edit.php?id={$_POST['client']}&tab=services$notify";
			else $popCalendar = "openConsoleWindow(\"viewcalendar\", \"calendar-package-nr.php?packageid=$newPackageId$notify\", 900, 700)";
			foreach(getServicesForPackage($newPackageId, 0) as $serv) $packageProviders[] = $serv['providerptr'];
		}
		
		if($_POST['notes']) {
			$appts = getAllScheduledAppointments($packageid ? $packageid : $newPackageId);
			if($appts) {
				$appt = current($appts);
				$mod = array('note'=>$_POST['notes']);
				updateTable('tblappointment', $mod, "appointmentid = {$appt['appointmentid']}", 1);
			}
		}
			
		
		
		if(!$errors) {
			// for all providers associated with the old and new schedules, generate memos
			$versions = array();
			if($oldPackage) $versions[] = $oldPackage['packageid']; 
			if($packageid) $versions[] = $packageid;
			if($newPackageId) $versions[] = $newPackageId;
			$provs = fetchCol0("SELECT providerptr FROM tblservice WHERE packageptr IN (".join(',', $versions).")");
			foreach(array_unique($provs) as $provptr) 
				if($provptr) {
					require_once "provider-memo-fns.php";
					makeClientScheduleChangeMemo($provptr, $_POST['client'], ($newPackageId ? $newPackageId : $packageid));
				}
		}
		$activeAppts = getPackageAppointments(($newPackageId ? $newPackageId : $packageid), $_POST['client'], "canceled IS NULL");
		applyScheduleDiscountWhereNecessary($activeAppts);
	}
	if(!isset($killAppointments) && $destination) {
		$servNames = getServiceNamesById();
		$reasons = explodePairsLine('timeoff|sitter time off||conflict|exclusive service type conflict');
		foreach($misassignedAppts as $id => $reason) {
			$unass = getAppointment($id);
			$badApples[] = shortestDate(strtotime($unass['date']), $noYear=1)
									." {$unass['timeofday']} {$servNames[$unass['servicecode']]}"
									." - {$reasons[$reason]}";
		}
		if($badApples) $badApples = "The following visits could not be assigned: ".join(", ", $badApples);
		}
		//$misassignedAppts[$apptId] = $providerUnassigned; // 'timeoff' or 'conflict'
		//$missing = array_intersect(providersOffThisDay($startdate), array_unique($packageProviders));
	}
	if($destination && /*$missing*/$badApples) {
		$providerShortNames = getProviderShortNames();
		foreach($missing as $p) $missingNames[] = $providerShortNames[$p];
		$missingNames = join(', ', $missingNames);
		/*if(count($missing) == 1)
			$message = "$missingNames is unavailable that day, so the visit(s) just scheduled for this sitter are currently unassigned.";
		else $message = 
			"Since the following sitters are unavailable that day, the visit(s) just scheduled for them are currently unassigned: $missingNames";
			*/
		$message = $badApples;
		$destination .= "&warn=".urlencode($message);
	}
			
	if($destination) {
		$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
		header("Location: $mein_host$this_dir/$destination");
		exit;
	}
}
if(mysqli_error()) exit;
if($newPackageId) $packageid = $newPackageId;
$client = isset($client) ? $client : '';
if($packageid) { // existing service package
  $package = getNonrecurringPackage($packageid);
	$services = getPackageServices($packageid);
	$client = $package['clientptr'];
	//echo '['.print_r($package,1).']';exit;
}
else { // new service
  // if $client is set, do not allow $client to be modified
	$services = array();
}
if($client) {
	$clientDetails = getClient($client);
	$pageTitle .= ': '.fullname($clientDetails);
}
$pageTitle = ($packageid ? '' : 'New ')."One-Day Schedule";

if($client) {
	$clientDetails = getClient($client);
	$pageTitle .= ': '.fullname($clientDetails);
	require_once "client-flag-fns.php";
	$pageTitle .= ' '.clientFlagPanel($client, $officeOnly=false, $noEdit=true, $contentsOnly=true);
}

include "frame.html";
// ***************************************************************************
if($popCalendar) {
	echo "<script language='javascript' src='common.js'></script><script language='javascript'>$popCalendar</script>";
	echo "<span class='pagenote' style='font-size:1.5em'>Your changes have been saved.  Please review the calendar and click the Done button.</span><p>";
}

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
}

$allPetNames = $client ? getClientPetNames($client) : '';

$serviceTypes = getStandardRates();
$serviceSelections = array_merge(array(''=>0), getServiceSelections());
//$activeProviderSelections = getActiveProviderSelections(null, $clientDetails['zip']);
$activeProviderSelections = availableProviderSelectElementOptions($clientDetails, null, 'Primary Sitter');
makePetPicker('petpickerbox',getActiveClientPets($client), $petpickerOptionPrefix, 'narrow');
makeWeekdayGrid('weekdays');
makeTimeFramer('timeFramer');

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
<? 
hiddenElement('client', $client);
hiddenElement('packageid', $packageid); 
hiddenElement('viewSurcharges', ''); 
?>

<? if($clientWidget) {
		echo $clientWidget;// echoClientSelect("client",array('Select Sitter'=>0)) ?> 
<img src='art/spacer.gif' width=20 height=0>
<?
}
//if(!$client) echo "<div id='hider' style='display:none;'>";
?>
<? 
calendarSet('Service Date:', 'startdate', $package['startdate'], null, null, true);
echo " ";
$prepaid = $package ? $package['prepaid'] : $_SESSION['preferences']['schedulesPrepaidByDefault'];
labeledCheckbox('Statement:', 'prepaid', $prepaid);
echo ' '; 
$arg = $package['packageid'] ? "packageid={$package['packageid']}" : "clientid=$client";
echoButton('', 'View All Notes', 
			"$.fn.colorbox({href:\"service-notes-ajax.php?$arg\", width:\"750\", height:\"570\", scrolling: true, opacity: \"0.3\"});"); 
echo "<p>";
dumpServiceDiscountEditor($clientDetails);
echo "<p>";
?>
<style>
input {font-size:1em;}
select {font-size:1em;margin-left:3px;}
</style>
<?
//labeledInput('Total Price:', 'packageprice', $package['packageprice'], null, 'dollarinput');
hiddenElement('packageprice', $package['packageprice']);
//labeledInput('How to Contact:', 'howtocontact', $package['howtocontact'], null, 'VeryLongInput');

//packageProviderSelectElement($activeProviderSelections, $clientDetails, $clientDetails['defaultproviderptr']);
selectElement('Primary Sitter', 'primaryProvider', $clientDetails['defaultproviderptr'], $activeProviderSelections, $onChange="setPrimaryProvider(this, false)");
unset($activeProviderSelections[current(array_keys($activeProviderSelections))]); // added back in by serviceLine
echo "<p>";

oneDayRecurringServiceTable($services, $activeProviderSelections, $serviceSelections);
//serviceTable($services, $activeProviderSelections, $serviceSelections,false);

$notesButtonStyle = "style='display:".(!$package['notes'] ? $_SESSION['tableRowDisplayMode']  : 'none').";'";
$notesTextRowStyle = "style='display:".($package['notes'] ? $_SESSION['tableRowDisplayMode']  : 'none').";'";
?>
<table>
<tr id='notesbuttonrow' <?= $notesButtonStyle ?>><td colspan=9><? echoButton('', 'Notes', 'showNotes()') ?></td></tr>
<tr id='noteslabelrow' <?= $notesTextRowStyle ?>><td colspan=9>Notes:</td></tr>
<tr id='notestextrow' <?= $notesTextRowStyle ?>><td colspan=9><textarea id='notes' name='notes' cols=60 rows=3><?= $package['notes'] ?></textarea></tr>
</table>
<?

$preempt = !$packageid ? 1 : $package['preemptrecurringappts'];
labeledCheckbox('This schedule preempts any repeating appointments for the client:', 'preemptrecurringappts', $preempt);
helpButton("Click here for an explanation", 
     "alert('If this box is checked, then any regular weekly or monthly appointments\\n that may occur on this day will be canceled.')");

?>
<p>
<div id='scheduleTotals' style='display:inline;'></div>
<table width='100%'>
<tr><td colspan=9 align=center><? 

if($roDispatcher && $packageid) {
	$note = urlencode("Changes to ".fullname($clientDetails)."'s One Day schedule on ".shortDate(strtotime($package['startdate'])).":");
	echoButton('', 'Make Schedule Change Request', "openConsoleWindow(\"requestedit\", \"client-own-request.php?pop=1&id=$client&note=$note\", 600, 600);"); 
}
else if(!$roDispatcher) {
	$buttonLabel = $packageid ? 'Save Changes' : 'Save Schedule';
	echoButton('',$buttonLabel, 'checkAndSubmit(0)');
	echoButton('','Save & Add Surcharges', 'checkAndSubmit(1)');
}

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

$allRawNames = "client,Client,startdate,Start Date,suspenddate,Suspend Date,resumedate,Resume Date,cancellationdate,Cancellation Date";
if($serviceLineFields) $allRawNames .= ",$serviceLineFields";
$prettyNames = "'".join("','",explode(',',$allRawNames))."'";


?>



<div style='height:100px;'></div>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
<? dumpHistoryLinkJSFrag(); ?>

setPrettynames(<?= $prettyNames ?>);	

var serviceLineConstraints = <?= $serviceLineConstraints.']]' ?>;

var newServiceConstraints = <?= $packageid ? '[]' : 
  "[//'startdate', 'NOT', 'isPastDate',
		'suspenddate', 'NOT', 'isPastDate',
		'resumedate', 'NOT', 'isPastDate']" ?>;

function scheduleIsIncomplete() {  // 0 = no problems, -1 = error
	var frm = document.nonrecurringserviceform;
	var firstCount=0;
	var prefixes = ['first_'];
	for(var i=0;i<frm.elements.length;i++)
		if(frm.elements[i].type == 'select-one' && frm.elements[i].value != 0) {
			var name =frm.elements[i].name;
			if(name.indexOf('first_servicecode_') != -1) firstCount++;
		}
	var status = 0;
	if(firstCount == 0) {
		// report an error
		alert("No services have been defined for the first day.");
		return 1;
	}
	return status;
}


var saving = false;
function checkAndSubmit(viewSurcharges) {
	if(saving) {
		alert('Changes are being saved.  Please wait...');
		return;
	}
	if(checkForm(viewSurcharges)) {
		if(true || !isPastDate(document.nonrecurringserviceform.startdate.value) ||
				confirm("You are about to create one or more appointments IN THE PAST.\n"+
								"These appointments will be automatically marked complete.\n"+
								"Choose Ok to continue or Cancel to make changes.")) {
			saving = true;
			if(viewSurcharges) document.getElementById('viewSurcharges').value = 1;
    	document.nonrecurringserviceform.submit();
		}
	}
}

function showNotes() {
	document.getElementById('notesbuttonrow').style.display = 'none';
	document.getElementById('noteslabelrow').style.display = '<?= $_SESSION['tableRowDisplayMode'] ?>';
	document.getElementById('notestextrow').style.display = '<?= $_SESSION['tableRowDisplayMode'] ?>';
	
}

	
function checkForm(viewSurcharges) {
	//if(scheduleIsIncomplete()) return;
	//return;
	
	setButtonDivElements('first_');
	var args = ['client', '', 'R',
		  'startdate', '', 'R',
		  'startdate', '', 'isDate',
		  'packageprice', '', 'R',
		  'packageprice', '', 'FLOAT',
		  'departuredate', '', 'isDate',
		  'returndate', '', 'isDate',
		  'cancellationdate', '', 'isDate',
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
	return MM_validateFormArgs(args) && !scheduleIsIncomplete();
}

/* UNUSED
function clientPicked(clientid,clientname,provider,target) {
	document.getElementById(target).innerHTML = clientname;
	document.getElementById('client').value = clientid;
	var sel = document.getElementById('first_providerptr_1');
	for(var i=0;i<sel.options.length;i++)
	  if(parseInt(sel.options[i].value) == parseInt(provider)) {
		  break;
	  }
	var prefixes = ['first_'];
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
*/



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
	setButtonDivElements('first_');
	var DataToSend = formArguments(document.nonrecurringserviceform);
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
	var startdate = document.getElementById('startdate').value;
	var enddate = startdate;
	/*if(!validateUSDate(startdate)) {// see: check-form.js
		document.getElementById('scheduleTotals').innerHTML = '';
		return;
	} */
  var totals = totalLineRatesAndCharges(1,'first_');
  var moreTotals = [0,0];
  
	document.getElementById('scheduleTotals').innerHTML =
	  'Total Charge: '+parseFloat(totals[1]).toFixed(2)+
	  '<img src="art/spacer.gif" width=20 height=1>Total Rate: '+parseFloat(totals[0]).toFixed(2);
	document.getElementById('packageprice').value = parseFloat(totals[1]).toFixed(2);
}

function editSurcharge(elId, review) {
	var el = document.getElementById(elId);
	var reason = el.value ? el.value : '';
	if(!review && reason) return; 
	var promptStr = review ? "Reason for adjustment and/or bonus.  Click Cancel to clear." : "Please supply a reason for this change.";
	promptStr += "\n'Cancel' will erase this reason."
	var answer = prompt(promptStr, reason);
	reason = answer; // allow reason to be cleared on review
	el.value = reason;
}

function toggleCharges(elId) {
	var prefix = elId.substring(0, elId.indexOf('_')+1);
	var onstate = '<?= $_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block' ?>';
	var newstate = document.getElementById(elId+'_charge').style.display == 'none' ? onstate : 'none';
	document.getElementById(elId+'_charge').style.display = newstate;
	document.getElementById(elId+'_adj').style.display = newstate;
	document.getElementById(elId+'_rate').style.display = newstate;
	document.getElementById(elId+'_bonus').style.display = newstate;
	document.getElementById(elId+'_surcharge').style.display = newstate;
	document.getElementById(prefix+'Charge_header').style.display = newstate;
	document.getElementById(prefix+'Adjust_header').style.display = newstate;
	document.getElementById(prefix+'Rate_header').style.display = newstate;
	document.getElementById(prefix+'Bonus_header').style.display = newstate;
}

<? dumpServiceRateJS();
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

function discountChanged(el) {
	var displayMode = el.selectedIndex == 0
											|| el.options[el.selectedIndex].value.split('|')[1] == 0
										? 'none'
										: 'inline';
	document.getElementById('memberidrow').style.display = displayMode;
}

<? 
//$allPetNames = array();
dumpWeekDayGridJS('weekdays');
dumpClickTabJS();
dumpPetGridJS('petpickerbox',$allPetNames);
dumpTimeFramerJS('timeFramer');

?>
nullPetsLabel = '';
nullWeekdaysLabel = '';
nullTimeFrameLabel = '';
displayTotals();


</script>
<img src='art/spacer.gif' width=1 height=200>
<?


// ***************************************************************************

include "frame-end.html";
?>
