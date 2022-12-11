<?
/* appointment-edit.php
*
* Mode 1 Parameters: 
* id - id of appointment to be edited
*
* Mode 2 Parameters: 
* date - date of appointment to be created
* clientptr - clientptr of appointment to be created
* providerptr (optional) - providerptr of appointment to be created
* packageptr - packageptr of appointment to be created
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "service-fns.php";
require_once "petpick-grid.php";
require_once "time-framer-mouse.php";
require_once "pet-fns.php";
require_once "discount-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('ea');
extract($_REQUEST);

if(!isset($id) && !$packageptr) $error = "Appointment ID not specified.";

$windowTitle = $id ? 'Edit Visit' : 'Add a Visit';
require "frame-bannerless.php";


if($_POST) {
	require_once "provider-fns.php";
	require_once "provider-memo-fns.php";
	$postReturn = '';
	if($deleteAction && $appointmentid) {
		// confirm again that visit can be deleted
		$billableInvolved = fetchFirstAssoc(
			"SELECT * 
				FROM tblbillable 
				WHERE superseded = 0
					AND (paid > 0 OR invoiceptr IS NOT NULL)
					AND itemptr = $appointmentid AND itemtable = 'tblappointment'
				LIMIT 1", 1);
		$incomplete = !fetchRow0Col0("SELECT completed FROM tblappointment WHERE appointmentid = $appointmentid");
		if(staffOnlyTEST() && $incomplete && !$billableInvolved && !hasPaidPayables($appointmentid)) {
			$postReturn = fetchRow0Col0("SELECT providerptr FROM tblappointment WHERE appointmentid = $appointmentid");
			deleteAppointment($appointmentid);
			$updateList = $updateList ? "'$updateList'" : 'null';
		}
		else $error = "Visit can not be deleted.";
	}
	else if($appointmentid) {
		$oldAndNew = updateAppointment(); // array('oldAppointment'=>$oldAppt, 'newAppointment'=>$appt);
		$postReturn = $oldAndNew['additionalSectionsToRedisplay'];
		$postReturn[] = $oldAndNew['newAppointment']['providerptr'];
		if($oldAndNew['oldAppointment']) $postReturn[] = $oldAndNew['oldAppointment']['providerptr'];
		foreach($postReturn as $i => $p) if($p <= 0) $postReturn[$i] = '0';
		$snafus = array('timeoff'=>'MISASSIGNED', 'conflict'=>'EXCLUSIVECONFLICT', 'inactive'=>'INACTIVESITTER');
		if($misassignedAppts) $postReturn[] = $snafus[current($misassignedAppts)];
		$postReturn = join(',', array_unique($postReturn));
		// provider notification
		if($oldproviderptr && $oldproviderptr != $providerptr)
		  makeClientVisitChangeMemo($oldproviderptr, $clientptr, $appointmentid);
		makeClientVisitChangeMemo($providerptr, $clientptr, $appointmentid);

		if($oldTotalCharge != (int)$charge+(int)$adjustment) {
			if($payableid && (int)$providerpaid == 0) deleteTable('tblpayable', "payableid = $payableid", 1);
			//if($oldTotalCharge 
		//if(!((int)($billpaid + $providerpaid))) 
		}
		$cancelcomp = isset($cancelcomp) ? $cancelcomp : 0;
	//echo "OLDCANCELCOMP: [$oldCancelcomp]  CANCELCOMP:  [$cancelcomp]";exit;
		if($oldCancelcomp && !$cancelcomp) {
			doQuery("DELETE FROM tblothercomp WHERE compid=$oldCancelcomp");
			doQuery("DELETE FROM tblpayable WHERE itemtable = 'tblothercomp' AND itemptr=$oldCancelcomp");
		}
		else if (!$oldCancelcomp && $cancelcomp) {
			$amount = ($rate ? $rate : 0) + ($bonus ? $bonus : 0);
			$amount = $amount ? $amount : '0.0';
			insertTable('tblothercomp', array('appointmentptr'=>$appointmentid, 'amount'=>$amount, 'comptype'=>'cancelcomp', 
																				'providerptr'=>$providerptr, 'date'=>$date), 1);
		}
		$updateList = $updateList ? "'$updateList'" : 'null';
	}
	else { // create new appointment
		// create a mock service
		$task = array('packageptr'=>$packageptr, 'daysofweek'=>$daysofweek, 'timeofday'=>$timeofday, 'servicecode'=>$servicecode, 
									'pets'=>$pets, 'charge'=>$charge, 'adjustment'=>$adjustment, 'rate'=>$rate, 'bonus'=>$bonus, 'recurring'=>false,
									'clientptr'=>$clientptr, 'providerptr'=>($providerptr ? $providerptr : '0'), 'surchargenote'=>$surchargenote, 'serviceid'=>'0');
		$appt = createAppointment(false, null, $task, strtotime($date));
		$appointmentid = $appt['appointmentid'];
		
		if($_SESSION['surchargesenabled']) {
			require_once "surcharge-fns.php";
			updateAppointmentAutoSurcharges($appointmentid);
			if($appt['completed']) {
				$surchargesCompleted = markAppointmentSurchargesComplete($_POST['appointmentid']);
				foreach($surchargesCompleted as $surcharge) $additionalSectionsToRedisplay[] = $surcharge['providerptr'];
			}
		}
	}
	
	
	
	$closeWhenDone = $error ? '' : 'window.close();';
	$preshrink = $error ? '' : 'window.resizeTo(10,10);';
	
	if($notifyclient) {
		$status = $_POST['cancellation'] == 1 ? 'canceled' : ($_POST['cancellation'] == 2 ? 'completed' : 'incomplete');
		$statuschange = "";
		if($oldstatus != $status) {
			$statuschange = $oldstatus == 'canceled' 
										? 'REACTIVATED'
										: ($status == 'canceled'
											? 'CANCELED'
											: ($status == 'completed' ? 'OTHER' : 'OTHER'));
			if($statuschange == 'OTHER') $notifyclient = "";
			else if($statuschange == 'CANCELED') $notifyclient = clientAcceptsEmail($clientptr, array('autoEmailApptCancellations'=>true));
			else if($statuschange == 'REACTIVATED') $notifyclient = clientAcceptsEmail($clientptr, array('autoEmailApptReactivations'=>true));
											
			$statuschange = $notifyclient ? "&apptstatus=$statuschange" : '';
		}
		else $notifyclient = true; //clientAcceptsEmail($clientptr, array('autoEmailApptChanges'=>true));
		if($notifyclient) echo 
			"<script language='javascript' src='common.js'></script>"
			."<script language='javascript'>$preshrink if(window.opener.update) window.opener.update('appointments', null);"
				. "var url ='notify-appointment.php?appointmentid=$appointmentid&clientid=$clientptr"
				. "&packageid=$packageptr&offerConfirmationLink=1$statuschange';openConsoleWindow('notificationcomposer', url, 600, 600);"
				. "$closeWhenDone;</script>";
		else {
			echo "<script language='javascript'>$preshrink if(window.opener.update) window.opener.update('appointments', '$postReturn');$closeWhenDone</script>";
		}
	}
	else {
		echo "<script language='javascript'> $preshrink if(window.opener.update) window.opener.update('appointments', '$postReturn');$closeWhenDone;</script>";
	}
	if($closeWhenDone) exit;
}


if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

if($id) $source = getAppointment($id, true, true, true);
else {
	$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientptr LIMIT 1");
	$source =array('date'=>$date, 'packageptr'=>$packageptr, 'providerptr'=>$providerptr, 'recurring'=>false, 
									'clientptr'=>$clientptr, 'client'=>$clientName);
}

//print_r($source);	
if(!$source) {
	echo "<h2><font color=red>ERROR</font></h2>";
	echo "Visit #$id was not found for this business.";
	exit;
}

$package = getPackage($source['packageptr']);
$packageCode = !$package ? 'UNK' : ($package['monthly'] ? 'MON' :
							 ($package['onedaypackage'] ? 'ONE' :
							 ($package['irregular'] == 1 ? 'IRREG' :
							 ($package['irregular'] == 2 ? 'MEETING' :
							 ($package['enddate'] ? 'NONREC' : 'REC')))));
$source['packageCode']	= $packageCode;           
$packageLabels = array('UNK'=>'Unknown', 'MON'=>'Fixed Price Monthly','ONE'=>'One Day','NONREC'=>'Short Term','REC'=>'Regular Recurring','IRREG'=>'EZ Schedule', 'MEETING'=>'Meeting');
$packageType = $packageLabels[$packageCode];
$source['packageType']	= $packageType;           
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<h2><?= $windowTitle ?><?= $source['highpriority'] ? '<font color=red>(High Priority)</font>' : '' ?></h2>
<?
$allPetNames = getClientPetNames($source['clientptr']);

makePetPicker('petpickerbox',getActiveClientPets($source['clientptr']), $petpickerOptionPrefix, 'narrow');
makeTimeFramer('timeFramer', 'narrow');

if($id) {
	$otherComp = getOtherCompForAppointment($id);
	if($otherComp) $source['cancelcomp'] = $otherComp['compid'];
}
displayAppointmentEditor($source, $updateList);

echo "<p>";
echoButton('', "Update Visit", "checkAndSubmit()");
echo " ";
//$appointmentUpdatesAccepted = clientAcceptsEmail(($clientptr ? $clientptr : $source['clientptr']), array('autoEmailApptChanges'=>true));

echoButton('', "Update Visit & Notify Client", "checkAndSubmit(\"notify\")");
echo " ";
echoButton('', "Quit", 'window.close()');
$billableInvolved = !$id ? null : fetchFirstAssoc(
	"SELECT * 
		FROM tblbillable 
		WHERE superseded = 0
			AND (paid > 0 OR invoiceptr IS NOT NULL)
			AND itemptr = $id AND itemtable = 'tblappointment'
		LIMIT 1", 1);
if(staffOnlyTEST() && $id && !$source['completed'] && !$billableInvolved && !hasPaidPayables($id)) {
	echo " ";
	echoButton('', "Delete Visit", 'deleteVisit()', 'HotButton', 'HotButtonDown');
}
$cpref = getMultipleClientPreferences($source['clientptr'], 'autoEmailApptCancellations,autoEmailApptReactivations,autoEmailApptChanges');
foreach($cpref as $k => $v) $cpref[$k] = $v ? '<font color=green>notify</font>' : '<font color=red>do not notify</font>';
echo "<p>Preference on Visit - <b>Cancellation: {$cpref['autoEmailApptCancellations']},  Reactivation: {$cpref['autoEmailApptReactivations']},  Changes: {$cpref['autoEmailApptChanges']}</b>"; 
//echo " ";
//echoButton('', "Delete Visit", 'deleteAppointment()', 'HotButton', 'HotButtonDown');
?>
</div>
<?
if($id) {
	echo "<div style='float:right;display:inline;' id='historylink'>";
	//echoButton('', "History", 'showHistory()');
	if(staffOnlyTEST()) {echo "<a href='appt-analysis.php?id=$id' target=analysis>Analyze</a> - ";}
	if(staffOnlyTEST() &&
			$msgid = fetchRow0Col0("SELECT value 
											FROM tblappointmentprop 
											WHERE appointmentptr = $id AND property = 'report'
											LIMIT 1", 1)) {
		fauxLink('Visit Report', "showVisitReport($msgid)");	
		echo " - ";
	}
	fauxLink('History', "showHistory($id)");
	echo "</div>";
}
?>
<div style='display:none;' id='history'></div>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>  
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script language='javascript'>
<? 
dumpPetGridJS('petpickerbox',$allPetNames);
dumpTimeFramerJS('timeFramer');
?>

var clientCharges = <?= getClientChargesJSArray($source['clientptr']) ?>;
var standardRates = <?= getStandardRateDollarsJSArray() ?>;
var providerRates = <?= getAllActiveProviderRateDollarsJSArray() ?>;
var standardCharges = <?= getStandardChargeDollarsJSArray() ?>;
var extraPetCharges = <?= getServicRateDollarsJSArray(getStandardExtraPetChargeDollars()); ?>;
var extraPetRates = <?= getServicRateDollarsJSArray(getStandardExtraPetRatesValues()); ?>;

function showVisitReport(id) {
	openConsoleWindow('visitreport', 'comm-view.php?id='+id,700,400);
}

function showHistory(id) {
	ajaxGet('appointment-history-ajax.php?id='+id, 'history');
	document.getElementById('history').style.display='block';
	document.getElementById('historylink').style.display='none';
}

function lookUpExtraPetCharge(service, client) {
	//if(client) {
	//	var rate = lookUpServiceRate(service, clientCharges);
	//	if(rate != -1) return rate[0];
	//}
	rate = lookUpServiceRate(service, extraPetCharges);
	return rate[0];
}

function lookUpExtraPetProviderRate(service, provider, extrapetcharge) {
	//if(provider) {
	//}
	var rate = lookUpServiceRate(service, extraPetRates);
	return rate[1] == 0 /* flat rate */
					? rate[0]
					: rate[0] / 100 * extrapetcharge;			/* percentage */
}

function petsUpdated(petsDivId) {
	updateAppointmentVals();
}
	


function notifyClientOfChange(appointmentid) { // UNUSED
	var url ="notify-appointment.php?appointmentid="+appointmentid
		+"&clientid="+document.getElementById('clientptr').value
		+"&packageid="+document.getElementById('packageptr').value
		+"&offerConfirmationLink=1";
			openConsoleWindow('notificationcomposer', url, 600, 600);
}

function updateAppointmentVals() {
	var service = document.getElementById('servicecode').value;
	var provider = document.getElementById('providerptr').value;
	var client = document.getElementById('clientptr').value;
	if(service == 0) {
		document.getElementById('rate').value = '';
		document.getElementById('div_rate').innerHTML = '';
		document.getElementById('charge').value = '';
		document.getElementById('div_charge').innerHTML = '';
	}
	else {
		// look up rate and charge
		var charge = lookUpClientServiceCharge(service, client);		
		var rate = lookUpProviderServiceRate(service, provider, charge);
		
var allPets = '<?= addslashes($allPetNames) ?>'.split(',');
		var pets = document.getElementById('div_pets').innerHTML;
		var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
		var extraPetNote = '';
		var chargewarning = '';
		var oldChargeParsed = parseFloat(document.getElementById('oldCharge').value).toFixed(2);
		if(numPets > 1) {
			var extrapetcharge = lookUpExtraPetCharge(service, client);
			if(extrapetcharge) extraPetNote = 'extra pet charge: $'+lookUpExtraPetCharge(service, client)+' X '+(numPets - 1)+' extra pets.';
			charge += (numPets - 1) * extrapetcharge;
			rate += (numPets - 1) * lookUpExtraPetProviderRate(service, provider, extrapetcharge);
		}
		
		// set values at rate_number and charge_number
		if(extraPetNote) extraPetNote = "(includes "+extraPetNote+")";
		document.getElementById('extrapetchargediv').innerHTML = extraPetNote;
		var displayRateAmount = parseFloat(rate).toFixed(2);
		document.getElementById('rate').value = displayRateAmount;
		if(displayRateAmount !=  document.getElementById('oldRate').value)
			displayRateAmount += " (Saved rate is $ "+document.getElementById('oldRate').value+")";
		document.getElementById('div_rate').innerHTML = displayRateAmount;
		document.getElementById('charge').value = parseFloat(charge).toFixed(2);
<?  ?>
		if(document.getElementById('charge').value != oldChargeParsed)
			chargewarning = "(Saved charge is $"+oldChargeParsed+")";
		if(document.getElementById('chargewarning')) document.getElementById('chargewarning').innerHTML = chargewarning;
		document.getElementById('div_charge').innerHTML = parseFloat(charge).toFixed(2);
	}
}

function lookUpProviderServiceRate(service, provider, charge) {
	if(provider) {
		for(var i=0;i<providerRates.length;i+=2)
		  if(providerRates[i] == provider) { 
				var rate = lookUpServiceRate(service, providerRates[i+1]);
		    rate =  rate != -1 ? rate : lookUpServiceRate(service, standardRates);
		  }
		if(!rate || rate == -1) rate = lookUpServiceRate(service, standardRates);
	}
	else rate = lookUpServiceRate(service, standardRates);
	return rate[1] == 0 /* flat rate */
					? rate[0]
					: rate[0] / 100 * charge;			/* percentage */
}

function lookUpClientServiceCharge(service, client) {
	if(client) {
		var rate = lookUpServiceRate(service, clientCharges);
		if(rate != -1) return rate[0];
	}
	rate = lookUpServiceRate(service, standardCharges);
	return rate[0];
}

function lookUpServiceRate(service, rates) {  // return [value, ispercentage]
	for(var i=0;i<rates.length;i+=3)  // servicetype,value,ispercentage
	  if(rates[i] == service)
	    return [rates[i+1],rates[i+2]];
	return -1;
}

function confirmAndClose() {
	if(true || confirm("Ok to close without saving changes?")) window.close();
}

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
	var selectedDiscount = el.selectedIndex == 0 ? -1 : el.options[el.selectedIndex].value.split('|');
	var displayMode = selectedDiscount == -1 || selectedDiscount[1] == 0
										? 'none'
										: 'inline';
	document.getElementById('memberidrow').style.display = displayMode;
	var equalsCurrent = document.getElementById('currentdiscount').value == selectedDiscount[0];
	var rowDisplayMode = "<?= $_SESSION['tableRowDisplayMode'] ?>";
	if(document.getElementById('currentdiscountrow')) 
		document.getElementById('currentdiscountrow').style.display = (equalsCurrent ? rowDisplayMode : 'none');
	if(document.getElementById('newdiscountrow'))
		document.getElementById('newdiscountrow').style.display = (!equalsCurrent ? rowDisplayMode : 'none');
	
}

setPrettynames('servicecode','Service Type','bonus','Bonus','adjustment','Adjustment','timeofday','Time Of Day');	
	
function deleteVisit() {
	if(!confirm('Are you sure you want to delete this visit?')) return;
	document.getElementById('deleteAction').value=1;
	document.appteditor.submit();
}
	
function checkAndSubmit(notifyclient) {
	setButtonDivElements();
	var discountMessage = '', discount = document.getElementById('discount');
	discount = discount.options[discount.selectedIndex].value;
	if(discount != -1 && discount.split('|')[1] != 0 && !jstrim(document.getElementById('memberid').value))
		discountMessage = 'Member ID must be supplied for this discount.';
	var badAdjustment = '';
	var adj = ''+document.getElementById('adjustment').value;
	var chg = ''+document.getElementById('charge').value;
	if(isFloat(adj) && isFloat(chg) && parseFloat(chg) + parseFloat(adj) < 0)
		badAdjustment = 'Charge + Adjustment must be greater than zero';
	//var conflict = document.getElementById('cancellation_1').checked && document.appteditor.completed.checked;
	if(MM_validateForm(
		//'providerptr', '', 'R',
		'servicecode', '', 'R',
		'timeofday', '', 'R',
		'adjustment', '', 'FLOAT',
		'bonus', '', 'FLOAT',
		discountMessage, '', 'MESSAGE',
		badAdjustment, '', 'MESSAGE'
		//,'Both Canceled and Completed are selected.  Please pick only one.', '', (conflict ? 'MESSAGE' : 'ZXZCX')
		)) {
	 var proceed = true;
<? if($source['completed']) { 
			$lightBox = <<<LIGHTBOX
This visit was marked completed before but is now marked incomplete.
<center><p><input type='button' value='Mark Completed Again & Save' onclick='postLightBox(1, X_NOTIFY)'></center>
<center><p><input type='button' value='Leave Incomplete & Save' onclick='postLightBox(0, X_NOTIFY)'></center>
<center><p><input type='button' value='Do Nothing' onclick='parent.$.fn.colorbox.close()'></center>
LIGHTBOX;
			$lightBox = str_replace("\n", " ", str_replace("\r", "", $lightBox));
?>
		var lightBox = "<?= $lightBox ?>";
		lightBox = lightBox.replace(/X_NOTIFY/g, (notifyclient ? "1" : "0"));
		// this visit was marked complete when the editor was opened
		if(document.getElementById('cancellation_0').checked) {
			$(document).ready(function(){$.fn.colorbox(
				{html:lightBox, 
				width:"310", height:"210", scrolling: true, opacity: "0.3"});});
			proceed = false;
		}


<? } ?>
		if(proceed) {
			document.getElementById('notifyclient').value = notifyclient ? 1 : 0;
			freezeApptEditor(false);
			document.appteditor.submit();
		}
	}
}

function postLightBox(markComplete, notifyclient) {
	if(markComplete) document.getElementById('cancellation_2').checked = true;
	document.getElementById('notifyclient').value = notifyclient ? 1 : 0;
	freezeApptEditor(false);
	document.appteditor.submit();
}

function setButtonDivElements() {
	var names = ['timeofday','pets'];
	for(var n=0; n < names.length; n++) 
		document.getElementById(names[n]).value = 
			document.getElementById('div_'+names[n]).innerHTML;
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

discountChanged(document.getElementById('discount'));
updateAppointmentVals();
</script>
</body>
</html>
