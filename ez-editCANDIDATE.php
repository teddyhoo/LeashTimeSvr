<?
/* ez-edit.php
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
$objtype = $objtype ? $objtype  : 'visit';
if(!isset($id) && !$packageptr) $error = "Appointment ID not specified.";
$thingToAdd = $objtype == 'surcharge' ? 'Surcharge' : 'Visit';
$windowTitle = $id ? "Edit $thingToAdd." : "Add a $thingToAdd.";

$extraHeadContent = '<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
 <link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
 <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';
require "frame-bannerless.php";

if($_POST) {
	if(isset($_POST['appointmentid'])) postAppointmentChange();
	else postSurchargeChange();
}

function postSurchargeChange() {
	global $surchargeid;
	extract($_REQUEST);
	require_once "provider-fns.php";
	require_once "invoice-fns.php";
	require_once "provider-memo-fns.php";
	$postReturn = '';
	if($action == 'delete') {
		if($undeletable) $error = "This surcharge is no longer deletable.";
		else dropSurcharges($id);
	}
	else if($surchargeid) {
		$source = fetchFirstAssoc("SELECT * FROM tblsurcharge WHERE surchargeid = $surchargeid");
		$surcharge = array('note' =>$note, 'surchargecode'=>$surchargecode, 'charge'=>$charge, 'rate'=>$rate, 'providerptr'=> $providerptr);
		if(!$automatic) {
			$surcharge['surchargecode'] = $surchargecode;
			$surcharge['providerptr'] = $providerptr;
		}
		$surcharge['canceled'] = $cancellation == 1 ? ($source['canceled'] ? $source['canceled'] : date("Y-m-d H:i")) : null;
		$surcharge['completed'] = $cancellation == 2 ? ($source['completed'] ? $source['completed'] : date("Y-m-d H:i")) : null;

		updateTable('tblsurcharge', $surcharge, "surchargeid = $surchargeid", 1);
		if($source['canceled'] && !$surcharge['canceled'] || !$source['canceled'] && $surcharge['canceled']) 
			$logChanges[] = ($surcharge['canceled'] ? 'canceled' : 'uncanceled');
		else if($surcharge['completed'] && !$source['completed']) 
			$logChanges[] = 'marked complete';
		else if(!$surcharge['completed'] && $source['completed']) 'marked incomplete';
		if($surcharge['note'] != $source['note']) 
			$logChanges[] = "note changed: $note";
		if($logChanges)
			logChange($surchargeid, 'tblsurcharge', $note='EZ editor: ',join(',', (array)$logChanges));
		
		// if completion has changed
		if($surcharge['completed'] != $source['completed'] || $surcharge['charge'] != $source['charge']) 
			supersedeSurchargeBillable($surchargeid);
		if($surcharge['completed'] && !$source['completed'])
			createSurchargeBillable($surchargeid);
		if($surcharge['canceled'] && !$source['canceled'])
			supersedeSurchargeBillable($surchargeid);
		$postReturn = array($providerptr);
		if($source) $postReturn[] = $source['providerptr'];
		foreach($postReturn as $i => $p) if($p == -1) $postReturn[$i] = '0';
		$postReturn = join(',', array_unique($postReturn));

		if($oldTotalCharge != (int)$charge) {
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
			$amount = ($rate ? $rate : 0);
			insertTable('tblothercomp', array('appointmentptr'=>$surchargeid, 'amount'=>$amount, 'comptype'=>'cancelsurcharge', 
																				'providerptr'=>$providerptr, 'date'=>$date), 1);
		}
		$updateList = $updateList ? "'$updateList'" : 'null';
	}
	else { // create new surcharge
		$canceled = null;
		$completed = $cancellation == 2 ? date("Y-m-d H:i:a") : null;
//if(mattOnlyTEST()) { echo print_r($_POST, 1)."<hr>$completed"	;exit;}
		$newSurchargeId = createSurcharge($clientptr, $packageptr, $surchargecode, $date, $automatic, $providerptr, $appt, $note, $completed);
		require_once "invoice-fns.php";
		if($completed) createSurchargeBillable($newSurchargeId);
	}
	
	if(!$error) {
		$closeWhenDone = 'parent.$.fn.colorbox.close();';
		echo "<script language='javascript'>if(parent.update) parent.update('appointments', '$postReturn');$closeWhenDone</script>";
		exit;
	}
}

function postAppointmentChange() {
	global $appointmentid, $scheduleDiscount;  // set when applying a discount to a package: array(clientptr,discountptr)

	extract($_REQUEST);
	require_once "provider-fns.php";
	require_once "provider-memo-fns.php";
	$postReturn = '';
	if($appointmentid) {
		$oldAndNew = updateAppointment(); // array('oldAppointment'=>$oldAppt, 'newAppointment'=>$appt);
		$postReturn = $oldAndNew['additionalSectionsToRedisplay'];
		$postReturn[] = $oldAndNew['newAppointment']['providerptr'];
		if($oldAndNew['oldAppointment']) $postReturn[] = $oldAndNew['oldAppointment']['providerptr'];
		foreach($postReturn as $i => $p) if($p <= 0) $postReturn[$i] = '0';
		$postReturn = join(',', array_unique($postReturn));
		// provider notification
		if($oldproviderptr && $oldproviderptr != $providerptr)
		  makeClientVisitChangeMemo($oldproviderptr, $clientptr, $appointmentid);
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
		else if (!$oldCancelcomp && $cancelcomp) {
			$amount = ($rate ? $rate : 0) + ($bonus ? $bonus : 0);
			insertTable('tblothercomp', array('appointmentptr'=>$appointmentid, 'amount'=>$amount, 'comptype'=>'cancelcomp', 
																				'providerptr'=>$providerptr, 'date'=>$date), 1);
		}
		$updateList = $updateList ? "'$updateList'" : 'null';
	}
	else { // create new appointment
		// create a mock service
		$_SESSION['lastAddedEZPackageAndServiceCode'] = array($packageptr, $servicecode);
		$task = array('packageptr'=>$packageptr, 'daysofweek'=>$daysofweek, 'timeofday'=>$timeofday, 'servicecode'=>$servicecode, 
									'pets'=>$pets, 'charge'=>$charge, 'adjustment'=>$adjustment, 'rate'=>$rate, 'bonus'=>$bonus, 'recurring'=>false,
									'clientptr'=>$clientptr, 'providerptr'=>($providerptr ? $providerptr : '0'), 'surchargenote'=>$surchargenote, 'serviceid'=>'0',
									'note'=>$note);
		$scheduleDiscount = -1;  // discount applied below
		
		$task['canceled'] = $_POST['cancellation'] == 1 ? date("Y-m-d H:i") : null;
		$task['completed'] = $_POST['cancellation'] == 2 ? date("Y-m-d H:i") : null;
		$task['date'] = $_POST['date']; // for conflict detection

//if(mattOnlyTEST()) {echo print_r($task, 1)."<p>{$_POST['date']}<p>[".detectVisitCollision($task, $task['providerptr']).']';exit;}
		$appt = createAppointment(false, null, $task, strtotime($date));
		$appointmentid = $appt['appointmentid'];
		
		if($_SESSION['surchargesenabled']) {
			require_once "surcharge-fns.php";
			updateAppointmentAutoSurcharges($appointmentid);
			if($appt['completed']) {
				$surchargesCompleted = markAppointmentSurchargesComplete($appointmentid); // ???
				foreach($surchargesCompleted as $surcharge) $additionalSectionsToRedisplay[] = $surcharge['providerptr'];
			}
		}
		
		if($discount = $_POST['discount']) {
			$discount = explode('|', $discount );
			$discount = $discount[0];
	//echo $memberid;exit;		
			if($discount && $discount != -1) {
	//$error = "New discount: $discount	Old: $currentDiscount	";
				$scheduleDiscount = 
					array('clientptr'=>$clientptr, 'discountptr'=>$discount, 'start'=>date('Y-m-d'), 'memberid'=>$_POST['memberid']);
				$numDiscountedAppts = applyScheduleDiscountWhereNecessary((string)$appointmentid);
				if($numDiscountedAppts == 0) $error = "Your changes were saved, but discount [$discount] could not be applied to: $appointmentid";
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo  "DISCOUNT: $discount<p>"; echo $error;}
			}
		}		
	}
	
	
	
	
	if($copies == 'allfuture' || strpos($copies, 'futureskip') === 0) $copies .= '_'.$date;
	if($copies == 'template') {
		$copies = templateFromForm($_POST);
		$aTemplate = describeEZVisitTemplate($_POST);
		if($aTemplate) replaceTable('tblclientpref', array('clientptr'=>$clientptr, 'property'=>'ezvisittemplate', 'value'=>$aTemplate), 1);
	}
	if($copies) copyAppointments($appointmentid, $packageptr, $copies, $clientptr, $providerptr); //copyAppointments
	
	
	$closeWhenDone = $error ? '' : 'parent.$.fn.colorbox.close();';
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { $_SESSION['user_notice'] = 'ERROR: '.print_r($error, 1);}
	global $misassignedAppts; // built by createAppointment, updateAppointment
		
	if($misassignedAppts) {
		$appts = fetchAssociationsKeyedBy(
			"SELECT appointmentid, date, timeofday, label, CONCAT_WS(' ', fname, lname) as clientname 
				FROM tblappointment
				LEFT JOIN tblservicetype ON servicetypeid = servicecode
				LEFT JOIN tblclient ON clientid = clientptr
				WHERE appointmentid IN (".join(',', array_keys($misassignedAppts)).")
				ORDER BY date, starttime", 'appointmentid');
		foreach($appts as $apptid => $appt) {
			$date = date('F j', strtotime($appt['date']));
			$labels[] = "<b>$date</b> {$appt['timeofday']} {$appt['label']} for {$appt['clientname']}";
			if($misassignedAppts[$apptid] == 'timeoff')
				$timeofflabels[] = "<b>$date</b> {$appt['timeofday']} {$appt['label']} for {$appt['clientname']}";
			else if($misassignedAppts[$apptid] == 'conflict')
				$conflictlabels[] = "<b>$date</b> {$appt['timeofday']} {$appt['label']} for {$appt['clientname']}";
			else if($misassignedAppts[$apptid] == 'inactive')
				$inactivelabels[] = "<b>$date</b> {$appt['timeofday']} {$appt['label']} for {$appt['clientname']}";
		}
		if($error) $error = "<p style='color:red'>$error</p>";
		$message = "<span style='font-size:1.5em'>{$error}";
		if($timeofflabels) 
			$message .= "Because of scheduled time off, no sitter was assigned for the following visit".(count($timeofflabels) == 1 ? '' : 's').":"
									."<ul><li>".join('<li>', $timeofflabels)."</ul>";
		if($conflictlabels) 
			$message .= "Because of exclusive service conflicts, no sitter was assigned for the following visit".(count($conflictlabels) == 1 ? '' : 's').":"
									."<ul><li>".join('<li>', $conflictlabels)."</ul>";
		if($inactivelabels) 
			$message .= "Because of exclusive service conflicts, no sitter was assigned for the following visit".(count($inactivelabels) == 1 ? '' : 's').":"
									."<ul><li>".join('<li>', $inactivelabels)."</ul>";
		$message .= "</span>";
		$_SESSION['user_notice'] = $message;
	}
	else if($error) $_SESSION['user_notice'] = "<p style='color:red;font-size:1.5em'>$error</p>";
	
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
		else $notifyclient = clientAcceptsEmail($clientptr, array('autoEmailApptChanges'=>true));
		if($notifyclient) echo 
			"<script language='javascript' src='common.js'></script>"
			."<script language='javascript'>if(parent.update) parent.update('appointments', null);"
				. "var url ='notify-appointment.php?appointmentid=$appointmentid&clientid=$clientptr"
				. "&packageid=$packageptr&offerConfirmationLink=1$statuschange';openConsoleWindow('notificationcomposer', url, 600, 600);"
				. "$closeWhenDone;</script>";
		else echo "<script language='javascript'>if(parent.update) parent.update('appointments', '$postReturn');parent.$.fn.colorbox.close();</script>";
	}
	else echo "<script language='javascript'>if(parent.update) parent.update('appointments', '$postReturn');$closeWhenDone;</script>";
	if($closeWhenDone) {
		exit;
	}

}  // END postAppointmentChange


if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

$providerMenuDefault = FALSE && mattOnlyTEST() ? '0' : $providerptr;
if($objtype == 'surcharge') {
	require_once "surcharge-fns.php";
	$source = $id 
		? getSurcharge($id, true, true, true) 
		: array('date'=>$date, 'packageptr'=>$packageptr, 'providerptr'=>$providerMenuDefault, 'clientptr'=>$clientptr);
}
else {
	if($id) $source = getAppointment($id, true, true, true);
	else {
		$lastPackage = $_SESSION['lastAddedEZPackageAndServiceCode'][0];
		if($lastPackage != $packageptr) {
			$history = findPackageIdHistory($packageptr, $clientptr, 0);  // <== NON-RECURRING ONLY FOR NOW
			if(!in_array($lastPackage, $history)) unset($_SESSION['lastAddedEZPackageAndServiceCode']);
		}
		if($_SESSION['lastAddedEZPackageAndServiceCode']) $servicecode = $_SESSION['lastAddedEZPackageAndServiceCode'][1];
		$source = array('date'=>$date, 'packageptr'=>$packageptr, 'providerptr'=>$providerMenuDefault, 'recurring'=>false, 
										'clientptr'=>$clientptr, 'client'=>$clientName, 'servicecode'=>$servicecode);
	}
}
if(!$source['client']) {
	$source['client'] = getOneClientsDetails($source['clientptr']);
	$source['client'] = $source['client']['clientname'];
}

//print_r($source);	
if(!$source) {
	echo "<h2><font color=red>ERROR</font></h2>";
	echo "$thingToAdd #$id was not found for this business.";
	exit;
}

$package = getPackage($source['packageptr']);
$packageCode = $package['monthly'] ? 'MON' :
							 ($package['onedaypackage'] ? 'ONE' :
							 ($package['irregular'] == 1 ? 'IRREG' :
							 ($package['irregular'] == 2 ? 'MEETING' :
							 ($package['enddate'] ? 'NONREC' : 'REC'))));
$source['packageCode']	= $packageCode;           
$packageLabels = array('MON'=>'Fixed Price Monthly','ONE'=>'One Day','NONREC'=>'Short Term','REC'=>'Regular Recurring','IRREG'=>'EZ Schedule', 'MEETING'=>'Meeting');
$packageType = $packageLabels[$packageCode];
$source['packageType']	= $packageType;

if(!$id) {
	$switchTo = "ez-edit.php?date=$date&packageptr=$packageptr&clientptr=$clientptr&providerptr=$providerptr&objtype=";
	$instead = $objtype == 'surcharge' ? 'visit' : 'surcharge';
	$switchTo .= $instead;
	$switchTo = '<br>'.fauxLink("Add a $instead instead", "document.location.href=\"$switchTo\"", 1);
	if(staffOnlyTEST() && strcmp($date, date('Y-m-d')) < 0) $switchTo .= '<p><span class="warning">THIS VISIT DATE IS IN THE PAST</span>';//
}
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<table style='width:100%'>
<tr style='vertical-align:top'>
<td><?= ezDayDisplay($source['date'], 'float:left;') ?></td>
<td id='addoredit' class='h2' style='text-align:left'><?= $windowTitle ?>
			<?= $source['highpriority'] ? '<span style="color:red;font-size:12px;">(High Priority)</span>' : '' ?></span>
			<span style="font-size:10px;"><?= $switchTo ?></span>
</td>
</tr>
</table>
<?
$allPetNames = getClientPetNames($source['clientptr']);

makePetPicker('petpickerbox',getActiveClientPets($source['clientptr']), $petpickerOptionPrefix, 'narrow');
makeTimeFramer('timeFramer', 'narrow');
if($objtype == 'visit') {
	if($id) {
		$otherComp = getOtherCompForAppointment($id);
		if($otherComp) $source['cancelcomp'] = $otherComp['compid'];
	}
	$cpref = getMultipleClientPreferences($source['clientptr'], 'autoEmailApptCancellations,autoEmailApptReactivations,autoEmailApptChanges');
	foreach($cpref as $k => $v) $cpref[$k] = $v ? '<font color=green>notify</font>' : '<font color=red>do not notify</font>';
	$notificationPrefs = "<p>Preference on Visit - <b>Cancellation: {$cpref['autoEmailApptCancellations']},  Reactivation: {$cpref['autoEmailApptReactivations']},  Changes: {$cpref['autoEmailApptChanges']}</b>"; 
	displayEZVisitEditor($source, $updateList, $notificationPrefs);
}
else {
	displayEZSurchargeEditor($source, $updateList, $notificationPrefs);
}
?>
</div>
<?
if($id) {
	if(staffOnlyTEST()) {
	// totalCharge should include additional pet charge, if any
	// $provider is a providerid
	// $service is the service type code
	// $providerRates are the custom rates for provider
	// $standardRates are the standard default rates for
		
		$rateEx = serviceRateExplanation($source['providerptr'], $source['servicecode'], $source['pets'],	$allPetNames, $source['charge']);
		foreach($rateEx as $k=>$v) {
			if(is_array($v)) foreach($v as $line) $explanation[] = $line;
			else if(!is_int($v)) $explanation[] = $v;
		}
		$explanation = join("<br>", $explanation); // .'<hr>'.print_r($rateEx, 1);
		echo "<div id='rateEx' style='display:none'><h2>Saved Rate Explanation</h2>$explanation</div>";
	}
	echo "<div style='float:right;display:inline;' id='historylink'>";
	//echoButton('', "History", 'showHistory()');
	if(staffOnlyTEST()) {
		$thingy = $objtype  == 'visit' ? 'appt' : 'surcharge';
		echo "<a href='$thingy-analysis.php?id={$_REQUEST['id']}' target=analysis>Analyze</a> - ";
	}
	if(staffOnlyTEST()) {
		fauxLink('Explain Rate', "alert(document.getElementById(\"rateEx\").innerHTML);$.fn.colorbox({html:document.getElementById(\"rateEx\").innerHTML, width:\"410\", height:\"410\", scrolling: true, opacity: \"0.3\"});");
		echo " - ";
	}
	$surchargeArg = $objtype  != 'visit' ? 1 : '0';
	fauxLink('History', "showHistory($id, $surchargeArg)");
	echo "</div>";
}
?>
<div style='display:none;' id='history'></div>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
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
		document.getElementById('rate').value = parseFloat(rate).toFixed(2);
		document.getElementById('div_rate').innerHTML = parseFloat(rate).toFixed(2);
		document.getElementById('charge').value = parseFloat(charge).toFixed(2);
		document.getElementById('div_charge').innerHTML = parseFloat(charge).toFixed(2);
	}
	
	
	if(document.getElementById('copies').value == 'template') 
		$('#templaterow').css('display', '<?= $_SESSION['tableRowDisplayMode'] ?>');
	else $('#templaterow').css('display', 'none');
	
}

function clearLine(el) {
	if(!el.selectedIndex) {
		var n = el.id.split('_')[1];
		document.getElementById('selected_'+n).checked = false;
		document.getElementById('excluded_'+n).checked = false;
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
	if(true || confirm("Ok to close without saving changes?")) parent.$.fn.colorbox.close();
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

var surcharges = new Array(<?= $surchargeRates ?>);


function updateSurchargeVals() {
	var surcharge = document.getElementById('surchargecode').value;
	if(surcharge == 0) {
		document.getElementById('rate').value = '';
		document.getElementById('div_rate').innerHTML = '';
		document.getElementById('charge').value = '';
		document.getElementById('div_charge').innerHTML = '';
	}
	else {
		// look up rate and charge
		for(var i=0;i<surcharges.length;i+=3)  // surchargetype,charge,rate
			if(surcharges[i] == surcharge) {
				var charge = surcharges[i+1];
				var rate = surcharges[i+2];
			}

		// set values at rate_number and charge_number
		document.getElementById('rate').value = parseFloat(rate).toFixed(2);
		document.getElementById('div_rate').innerHTML = parseFloat(rate).toFixed(2);
		document.getElementById('charge').value = parseFloat(charge).toFixed(2);
		document.getElementById('div_charge').innerHTML = parseFloat(charge).toFixed(2);
	}
}

setPrettynames('servicecode','Service Type','bonus','Bonus','adjustment','Adjustment','timeofday','Time Of Day',
								'surchargecode', 'Surcharge Type', 'providerptr', 'Sitter');
								
function identifyEZTemplateErrors() {
	var copiesEl = document.getElementById('copies');
	if(copiesEl.options[copiesEl.selectedIndex].value != 'template') return;
	var tf, tfCount = 0, daysChecked = 0, message = [];
	for(var n = 1; (tf = document.getElementById('timeframe_'+n)); n++)
		if(tf.selectedIndex && document.getElementById('selected_'+n).checked)
			tfCount++;
	if(tfCount == 0) message[message.length] = ['When you copy to a template, at least one service must be marked to copy.'];
	var days = 'M T W Th F Sa Su'.split(' ');
	for(var n = 0; n < 7; n++)
		if(document.getElementById('day_'+days[n]).checked)
			daysChecked ++;
	if(daysChecked == 0) message[message.length] = ['When you copy to a template, at least one day must be checked.'];
	if(message.length > 0) return message.join("\n- ");
}

function showHistory(id, surcharge) {
	if(surcharge == 1) ajaxGet('surcharge-history-ajax.php?id='+id, 'history');
	else ajaxGet('appointment-history-ajax.php?id='+id, 'history');
	document.getElementById('history').style.display='block';
	document.getElementById('historylink').style.display='none';
}

	
function checkAndSubmit(notifyclient) {
<? if($objtype != 'surcharge') { ?>
	setButtonDivElements();
	var discountMessage = '', discount = document.getElementById('discount');
	discount = discount.options[discount.selectedIndex].value;
	if(discount != -1 && discount.split('|')[1] != 0 && !jstrim(document.getElementById('memberid').value))
		discountMessage = 'Member ID must be supplied for this discount.';
	var ezTemplateMessage = identifyEZTemplateErrors();
	
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
		badAdjustment, '', 'MESSAGE',
		//,'Both Canceled and Completed are selected.  Please pick only one.', '', (conflict ? 'MESSAGE' : 'ZXZCX')
		ezTemplateMessage, '', 'MESSAGE'
		)) {
		document.getElementById('notifyclient').value = notifyclient ? 1 : 0;
		freezeApptEditor(false);
		document.appteditor.submit();
	}
<? } 
else { ?>
	if(MM_validateForm(
		'providerptr', '', 'R',
		'surchargecode', '', 'R'
		)) {
		freezeApptEditor(false);
		document.appteditor.submit();
	}

<? } ?>
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

if(document.getElementById('discount')) discountChanged(document.getElementById('discount'));
if(document.getElementById('servicecode')) updateAppointmentVals();
</script>
</body>
</html>
