<?
/* appointment-editCANDIDATE.php
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
$windowTitle .=  "."; //" <font color=red>TEST CANDIDATE</font>";
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
		if($incomplete && !$billableInvolved && !hasPaidPayables($appointmentid)) { // staffOnlyTEST() && 
			$appt = getAppointment($appointmentid, true, true, true);
			deleteAppointment($appointmentid);
			$service = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$appt['servicecode']}", 1);
			$from = "[Detail Editor]";
			$packagetype = $appt['recurringpackage'] ? "[R]" : "[N]";
			logChange($appt['clientptr'], 'tblclient', 'x', 
				$note="Deleted appt {$appt['appointmentid']}  {$appt['date']} {$appt['timeofday']} $service {$appt['provider']} $packagetype $from");
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
		// if old appointment and new appointment's provider is not $providerptr
		if($providerptr == $oldAndNew['newAppointment']['providerptr'])
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
		else if (!$oldCancelcomp && $cancelcomp && $providerptr) {
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
		else echo "<script language='javascript'>$preshrink if(window.opener.update) window.opener.update('appointments', '$postReturn');$closeWhenDone</script>";
	}
	else echo "<script language='javascript'>$preshrink if(window.opener.update) window.opener.update('appointments', '$postReturn');$closeWhenDone;</script>";
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
if(/* staffOnlyTEST() && */$id && !$source['completed'] && !$billableInvolved && !hasPaidPayables($id)  /* && (!$source['recurringpackage'] || staffOnlyTEST()) */) {
	if($source['recurringpackage']) $recurringWarning = "This is a recurring visit!  Deletion may be undone!";
	echo " ";
	echoButton('', "Delete Visit", 'deleteVisit()', 'HotButton', 'HotButtonDown', 0, $recurringWarning);
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
	echo "<div style='float:right;display:inline;' id='historylinkdiv'>";
	if(staffOnlyTEST()) {echo "<a href='appt-analysis.php?id=$id' target=analysis>Analyze</a> - ";}
	if(staffOnlyTEST()) {
		fauxLink('Explain Rate', "$.fn.colorbox(
				{html:document.getElementById(\"rateEx\").innerHTML, 
				width:\"410\", height:\"410\", scrolling: true, opacity: \"0.3\"});");
		echo " - ";
	}

	fauxLink('History', "showHistory($id)", $noEcho=false, $title=null, $linkid='historylink');
	echo "</div>";
}

if($id && FALSE && tableExists('tblvaluepack')) {
	require_once "preference-fns.php";
	require_once "value-pack-fns.php";
	$vpptr = getAppointmentProperty($id, 'vpptr');
	if($vpptr) $vdisplay = $remaining = prepaidVisitsLeft($vpptr);
	else $vdisplay = 'V';
	// show this div if a) visit has a token or b) client has an eligible value pack
	if($vpptr || getClientValuePacks($source['clientptr']))
		echo "<div id='valuepackTokenDiv' onclick='valuPackLinker($id)' style='color:gray;background:yellow;width:25px;height:25px;position:absolute;top:0px;right:0px;font-weight:bold;font-size:1.5em;padding-left:3px;'>$vdisplay</div>";
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

function valuPackLinker(apptid, clientptr) {
	var url = apptid != null ? "value-pack-link.php?apptid="+apptid : "value-pack-link.php?clientptr="+clientptr;
	$.fn.colorbox(
					{href: url, iframe: true,	width:"550", height:"310", scrolling: true, opacity: "0.3"});
}

function updateValuePackToken(label) {
	$('#valuepackTokenDiv').html(label);
}
	
function showHistory(id) {
	ajaxGetAndCallWith('appointment-history-ajax.php?id='+id, scrollHistory, 0);
	//ajaxGet('appointment-history-ajax.php?id='+id, 'history');
	//document.getElementById('history').style.display='block';
	//document.getElementById('historylink').style.display='none';
}

function scrollHistory(arg, text) {
	document.getElementById('history').innerHTML=text;
	document.getElementById('history').style.display='block';
	document.getElementById('historylink').style.display='none';
	window.scrollTo(0, document.body.scrollHeight);
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
		var allPets = '<?= addslashes($allPetNames) ?>'.split(',');
		var pets = document.getElementById('div_pets').innerHTML;
		var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
		var numExtraPets = numPets - 1;
		var extraPetCharge = 0;
		var extrapetchargePerPet = lookUpExtraPetCharge(service, client);
		var extraPetNote = '';
		if(numExtraPets > 0 && extrapetchargePerPet > 0) {
			extrapetchargePerPet = lookUpExtraPetCharge(service);
			extraPetCharge = numExtraPets * extrapetchargePerPet;
			extraPetNote = 'extra pet charge: $'+extrapetchargePerPet+' X '+numExtraPets+' extra pets.';
		}
		
		var rate = NEWlookUpProviderServiceRate(service, provider, charge, pets, allPets);
		charge += extraPetCharge;

		// set values at rate_number and charge_number
		if(extraPetNote) extraPetNote = "(includes "+extraPetNote+")";
		document.getElementById('extrapetchargediv').innerHTML = extraPetNote;
		var displayRateAmount = parseFloat(rate).toFixed(2);
		document.getElementById('rate').value = displayRateAmount;
		if(displayRateAmount !=  document.getElementById('oldRate').value)
			displayRateAmount += " (Saved rate is $ "+document.getElementById('oldRate').value+")";
		document.getElementById('div_rate').innerHTML = displayRateAmount;
		document.getElementById('charge').value = parseFloat(charge).toFixed(2);
		document.getElementById('div_charge').innerHTML = parseFloat(charge).toFixed(2);
	}
}

function NEWlookUpProviderServiceRate(service, provider, charge, pets, allPets) {
	// charge is the raw service charge for this service type (and this client)
	var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
	var numExtraPets = numPets - 1;
	var standardRate = lookUpServiceRate(service, standardRates);
	var extrapetchargePerPet = lookUpExtraPetCharge(service);
	var extraPetCharge = numExtraPets * extrapetchargePerPet;
	var baseCharge = Number(charge);
	charge += extraPetCharge;
	var extraPetRate = lookUpServiceRate(service, extraPetRates);
	var extraPetRateDollars = standardRate[1] // if standard rate is a percentage
		? extraPetRate[0] / 100 * extrapetchargePerPet
		: extraPetRate[0];
	var extraPetRatePercent = standardRate[1] // if standard rate is a percentage
		? extraPetRate[0]
		: (extraPetCharge == 0 ? 0 : extraPetRateDollars / extrapetchargePerPet);
		
	var rate = -999;

	var customRate = -999;
	if(provider) {
		for(var i=0;i<providerRates.length;i+=2) {
		  if(providerRates[i] == provider) { 
				var customRate = lookUpServiceRate(service, providerRates[i+1]);
		  }
		}
	// IF custom sitter rate:
		if(customRate[0] >= 0) {
			// IF rate is percentage:
			if(customRate[1] == 1) {
				// IF extra pet rate percentage > custom rate
				if(extraPetRatePercent > customRate[0]) rate = customRate[0] / 100 * baseCharge + extraPetRateDollars * numExtraPets;
				else rate = customRate[0] / 100 * (0+baseCharge + extraPetCharge);
			}
			// ELSE flat rate
			else rate = customRate[0] + extraPetRateDollars * numExtraPets;
		}
	}
	// ELSE IF no custom sitter rate:
	if(rate == -999) {
		// IF standard rate is a percentage
		if(standardRate[1]) 
			rate = baseCharge * standardRate[0] / 100 + extraPetCharge * extraPetRatePercent / 100;
		else rate =standardRate[0] + extraPetRateDollars * numExtraPets;
	}
	return rate;
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
	var recurringWarning = "<?= $recurringWarning ?>" == "" ? "" : "\n"+"<?= $recurringWarning ?>";
	if(!confirm('Are you sure you want to delete this visit?'+recurringWarning)) return;
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
