<?
//service-nonrepeating.php

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

require_once "gui-fns.php";
include "weekday-grid.php";
include "petpick-grid.php";
include "time-framer-mouse.php";

$locked = locked('o-');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract($_REQUEST);


// Determine access privs




$destination = null;
$conflicts = null;
$errors = array();
if($_POST) {
	$viewSurcharges = !isset($_POST['viewSurcharges']) || $_POST['viewSurcharges']; // always view after conflicts
//echo 	"[$viewSurcharges]";exit;
	if($action == 'quit') { // from conflicts page
		$popCalendar = "openConsoleWindow(\"viewcalendar\", \"calendar-package-nr.php?packageid=$packageid$notify\", 900, 700)";
	}  
	
	else if(isset($killAppointments)) {// from conflicts page
		foreach($_POST as $key => $label) {
			if(strpos($key, 'appt_') !== FALSE)
				$apptids[] = substr($key, 5);
		}
		require_once "appointment-fns.php";
		deleteAppointments("appointmentid IN (".join(',', $apptids).")");
		//$destination = "client-edit.php?id={$_POST['client']}&tab=services";
		$popCalendar = "openConsoleWindow(\"viewcalendar\", \"calendar-package-nr.php?packageid=$packageid$notify\", 900, 700)";
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
		
		if($packageid) {
			$oldPackage = getNonrecurringPackage($packageid);
			if(!$oldPackage['current']) {
				$currentPackage = findCurrentPackageVersion($packageid, $_POST['client'], false);
				$errors[] = 
					"This version of the package is no longer current, so changes to it cannot be saved.<br>"
					. "Please note the changes you tried to make and then <a href='service-nonrepeating.php?packageid=$currentPackage'>Edit the Current Version</a>";
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

			}
		}
		else {
			$newPackageId = saveNewNonrepeatingPackage();
			$notify= "&notifytime=".time()."&notifynewschedule=$newPackageId";
			if(!$viewSurcharges) $destination = "client-edit.php?id={$_POST['client']}&tab=services$notify";
			else $popCalendar = "openConsoleWindow(\"viewcalendar\", \"calendar-package-nr.php?packageid=$newPackageId$notify\", 900, 700)";
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
$pageTitle = ($packageid ? '' : 'New ')."Pro Schedule";

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
	
	if(false && clientAcceptsEmail($client, array('autoEmailScheduleChanges'=>true))) {
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
calendarSet('Start Date:', 'startdate', $package['startdate'], null, null, true, 'enddate', 'displayTotals()');
//calendarSet($label, $name, $value=null, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null) {
calendarSet('End Date:', 'enddate', $package['enddate'], null, null, true, null, 'displayTotals()', '', 'startdate');
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
labeledCheckbox('Statement:', 'prepaid', $prepaid, null, null, null, 'boxfirst');

echo " ";
$markstartfinish = $_SESSION['preferences']['markStartFinish'];
labeledCheckbox('Mark Start/Finish', 'markStartFinish', $markstartfinish, null, null, null, 'boxfirst');
if(staffOnlyTEST()) { 
echo " ";
$sendBillingReminders = $packageid ? $package['billingreminders'] : $_SESSION['preferences']['sendBillingReminders'];
labeledCheckbox('Send Billing Reminders', 'billingreminders', $sendBillingReminders, null, null, null, 'boxfirst');
}


echo ' '; 
$arg = $package['packageid'] ? "packageid={$package['packageid']}" : "clientid=$client";
echoButton('', 'View All Notes', 
			"$.fn.colorbox({href:\"service-notes-ajax.php?$arg\", width:\"750\", height:\"570\", scrolling: true, opacity: \"0.3\"});"); 
echo "<p>";
dumpServiceDiscountEditor($clientDetails);
echo "<br>";


echo "<p>";
//packageProviderSelectElement($activeProviderSelections, $clientDetails, $clientDetails['defaultproviderptr']);
selectElement('Primary Sitter', 'primaryProvider', $clientDetails['defaultproviderptr'], $activeProviderSelections, $onChange="setPrimaryProvider(this, false)");
unset($activeProviderSelections[current(array_keys($activeProviderSelections))]); // added back in by serviceLine
echo "<p>";


nonRecurringServiceTabs($services, $activeProviderSelections, $serviceSelections);
//serviceTable($services, $activeProviderSelections, $serviceSelections,false);


$preempt = !$packageid ? 0 : $package['preemptrecurringappts'];
labeledCheckbox('This schedule preempts any repeating appointments for the client:', 'preemptrecurringappts', $preempt);
helpButton("Click here for an explanation", 
     "alert('If this box is checked, then any regular weekly or monthly appointments\\n that may occur during this schedule\\&#39;s period will be canceled.')");
?>
<p>
<div id='scheduleTotals' style='display:inline;'></div>
<table width='100%'>
<tr><td colspan=9 align=center><? 

if($roDispatcher && $packageid) {
	$note = urlencode("Changes to ".fullname($clientDetails)."'s Pro schedule starting ".shortDate(strtotime($package['startdate'])).":");
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
  "['startdate', 'NOT', 'isPastDate',
		'suspenddate', 'NOT', 'isPastDate',
		'resumedate', 'NOT', 'isPastDate']" ?>;

function scheduleIsIncomplete(preview) {  // 0 = no problems, -1 = error
	var saveAction = preview ? 'Proceed' : 'Save';
	var cannotAction = preview ? 'Cannot Proceed' : 'Package not saved';
	var frm = document.nonrecurringserviceform;
	days = intervalLength(frm.startdate.value, frm.enddate.value);
	var firstCount=0, betweenCount=0, lastCount=0;
	var prefixes = ['first_','between_', 'last_'];
	for(var i=0;i<frm.elements.length;i++)
		if(frm.elements[i].type == 'select-one' && frm.elements[i].value != 0) {
			var name =frm.elements[i].name;
			if(name.indexOf('first_servicecode_') != -1) firstCount++;
			else if(name.indexOf('between_servicecode_') != -1) betweenCount++;
			else if(name.indexOf('last_servicecode_') != -1) lastCount++;
		}
	//alert('days: '+days+' first: '+firstCount+' between: '+betweenCount+' last: '+lastCount);
	var status = 0;
	if(firstCount == 0) {
		// report an error
		alert("No services have been defined for the first day.");
		return 1;
	}
	
	// else...
	// Case 1: days > 2
	if(days > 2) {
		if(betweenCount+lastCount == 0) {
			// offer user to apply first day's schedule to other days
			if(!confirm("Services have been defined only for the first day.\nApply these services to all succeeding days?"))
			  status = 1;
			else {
				status = 0;
				copyServices("first_", "between_");
				copyServices("first_", "last_");
			}
		}
		else if((betweenCount == 0) || (lastCount == 0)) {
			// if one tab's contents are empty, report an error
			var missingTab = betweenCount == 0 ? 'Days in between' : 'the Last Day';
			alert("No services have been specified for "+missingTab+'.\n'+cannotAction+".\nPlease define "+missingTab+" Services.");
			status = 1;
		}
	}
	// Case 2: days == 2
	else if(days == 2) {
		if(betweenCount > 0 && (lastCount == 0)) {
			alert("For a two-day schedule, only First and Last day services are considered.\nYou have defined In Between day services instead.  "+cannotAction+".\nPlease define Last Day Services before proceeding.");
			status = 1;
		}
		else if(betweenCount > 0) {
			// warn that between days services will be ignored
			if(confirm("This schedule is only two days long but services have been defined\nfor days in between.  These will be ignored. "+saveAction+" anyway?"))
			  status = 0;
			else status = 1;
		}
		if(status == 0 && betweenCount+lastCount == 0) {
			// offer user to apply first day's schedule to last day
			if(!confirm("Services have been defined only for the first day.\nApply these services to the second day?"))
			  status = 1;
			else {
				status = 0;
				copyServices("first_", "last_");
			}
		}
	}
	
	// Case 3: days == 1
	else if(days == 1) {
		if(betweenCount > 0 || (lastCount > 0)) {
			if(confirm("This schedule is only one day long but services have been defined\nfor days in between.  These will be ignored. "+saveAction+" anyway?"))
			  status = 0;
			else status = 1;
		}
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
		saving = true;
    document.nonrecurringserviceform.submit();
	}
}
	
function checkForm(viewSurcharges, preview) {
	
	//if(scheduleIsIncomplete()) return;
	//return;
	
	setButtonDivElements('first_');
	setButtonDivElements('between_');
	setButtonDivElements('last_');
	var args = ['client', '', 'R',
		  'startdate', '', 'R',
		  'startdate', '', 'isDate',
		  'enddate', '', 'R',
		  'enddate', '', 'isDate',
		  'startdate', 'enddate', 'datesInOrder',
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
	if (!MM_validateFormArgs(args) || scheduleIsIncomplete(preview)) return false
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
	if(viewSurcharges) document.getElementById('viewSurcharges').value = 1;
	return true;
	
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

function previewPackage() {
	if(!checkForm(null, true)) return;		
	setButtonDivElements('first_');
	setButtonDivElements('between_');
	setButtonDivElements('last_');
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

function intervalLength(startdate, enddate) {
	var arr = mdy(startdate);
	startdate = new Date(arr[2], arr[0]-1, arr[1]);
	arr= mdy(enddate);
	enddate = new Date(arr[2], arr[0]-1, arr[1]);
	var one_day=1000*60*60*24;
	return Math.round((enddate.getTime()-startdate.getTime())/one_day)+1;
}	


function displayTotals() {
	var startdate = document.getElementById('startdate').value;
	var enddate = document.getElementById('enddate').value;
	if(!validateUSDate(startdate) || !validateUSDate(enddate) ||
	    !datesInOrder(startdate,enddate)) {// see: check-form.js
		document.getElementById('scheduleTotals').innerHTML = '';
		return;
	}
	var days = intervalLength(startdate, enddate);
  var totals = totalLineRatesAndCharges(1,'first_');
  var moreTotals = [0,0];
	if(days > 1) moreTotals = totalLineRatesAndCharges(1,'last_');
  totals[0] += moreTotals[0];
  totals[1] += moreTotals[1];
	//moreTotals = (days > 2) ? totalLineRatesAndCharges(days-2,'between_') : [0,0];
	moreTotals = (days > 2) ? betweenDayTotals(startdate, enddate) : [0,0];
  totals[0] += moreTotals[0];
  totals[1] += moreTotals[1];
//alert(moreTotals);  
	document.getElementById('scheduleTotals').innerHTML =
	  'Total Charge: '+parseFloat(totals[1]).toFixed(2)+
	  '<img src="art/spacer.gif" width=20 height=1>Total Rate: '+parseFloat(totals[0]).toFixed(2);
	document.getElementById('packageprice').value = parseFloat(totals[1]).toFixed(2);
}

function betweenDayTotals(startdate, enddate) {
	var dayCounts = dowCount(startdate, enddate);
//alert(startdate+','+enddate+':'+dayCounts);	
//alert(intervalLength(startdate, enddate)+' days: '+dayCounts);  
	var totals = [0, 0];
	
	for(var i=1;i <= document.getElementById("between_services_visible").value; i++) { // for each service
		var daysOfWeek = document.getElementById('between_div_daysofweek_'+i).innerHTML;
		var numWeekDays = 0;
		for(var d=0; d<7; d++) {
			if(daysOfWeekIncludes(daysOfWeek, d)) numWeekDays = dayCounts[d];
			else continue;
			var linetotal = lineRateAndCharge(i, numWeekDays, "between_");
			totals[0] += linetotal[0];
			totals[1] += linetotal[1];
		}
	}
	return totals;
}

function dowCount(startdate, enddate) {
	// return an array with the counts of each day of the week in the supplied interval (0 = Sunday)
	// ignore first and last days
	var days = [0,0,0,0,0,0,0];
	var arr = mdy(startdate);
	var firstday = new Date(arr[2], arr[0]-1, arr[1]);
	var numdays = intervalLength(startdate, enddate); // all days
	var day = firstday.getDay();  // 0 - 6 of the first day
	for(var i = 1; i < numdays-1; i++) {  // ignore first and last days
		day++;
		if(day > 6) day = 0;
		days[day] ++; // ignore first and last days
	}
	return days;
}
	
function daysOfWeekIncludes(daysOfWeek, dayNumber) { // dayNumber: 0-6, 0=Sunday
	if(daysOfWeek == 'Every Day') return true;
	else if(daysOfWeek == 'Weekends') return dayNumber == 0 || dayNumber == 6;
	else if(daysOfWeek == 'Weekdays') return dayNumber > 0 && dayNumber < 6;
	else {
		var allDays = ['Su','M','Tu','W','Th','F','Sa'];
		var darray = daysOfWeek.split(',');
		for(var i=0; i < darray.length; i++) 
			if(allDays[dayNumber] == darray[i]) return true;
	}
}
			
function discountChanged(el) {
	var displayMode = el.selectedIndex == 0
											|| el.options[el.selectedIndex].value.split('|')[1] == 0
										? 'none'
										: 'inline';
	document.getElementById('memberidrow').style.display = displayMode;
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
