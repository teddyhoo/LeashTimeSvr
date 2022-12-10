<?
/* surcharge-edit.php
*
* Mode 1 Parameters: 
* id - id of surcharge to be edited
*
* Mode 2 Parameters: 
* date - date of surcharge to be created
* clientptr - clientptr of surcharge to be created
* providerptr (optional) - providerptr of surcharge to be created
* packageptr - packageptr of surcharge to be created
*
* Mode 3 Parameters: 
* appointmentptr - appointmentptr of surcharge to be created
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "surcharge-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('ea');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
extract($_REQUEST);
if($appointmentptr) {
	$appt = fetchFirstAssoc("SELECT appointmentid,clientptr,packageptr,date,providerptr,timeofday,starttime,endtime 
														FROM tblappointment 
														WHERE appointmentid = $appointmentptr");
	extract($appt);
	$appt['appointmentptr'] = $appointmentptr;
}
$source = $id 
	? fetchFirstAssoc("SELECT * FROM tblsurcharge WHERE surchargeid = $id LIMIT 1")
	: ($appt
		? $appt
		: array('date'=>$date, 'packageptr'=>$packageptr, 'providerptr'=>$providerptr, 'clientptr'=>$clientptr));

$undeletable = !$id
		|| fetchCol0(
						"SELECT payableid FROM tblpayable
							WHERE itemptr = $id AND itemtable = 'tblsurcharge'")
		|| fetchCol0(
						"SELECT billableid FROM tblbillable
							WHERE itemptr = $id AND itemtable = 'tblsurcharge'");
if($_POST) {
	require_once "provider-fns.php";
	require_once "invoice-fns.php";
	require_once "provider-memo-fns.php";
	$postReturn = '';
	if($action == 'delete') {
		if($undeletable) $error = "This surcharge is no longer deletable.";
		else dropSurcharges($id);
	}
	else if($surchargeid) {
		if($action == 'updateamount') {
			$charge = $surchargeTypeCharge;
			$rate = $surchargeTypeRate;
		}
		$surcharge = withModificationFields(array('note' =>$note, 'surchargecode'=>$surchargecode, 'charge'=>$charge, 'rate'=>$rate, 'providerptr'=> $providerptr));
		if(!$automatic) {
			$surcharge['surchargecode'] = $surchargecode;
			$surcharge['providerptr'] = $providerptr;
		}
		$surcharge['canceled'] = $cancellation == 1 ? ($source['canceled'] ? $source['canceled'] : date("Y-m-d H:i")) : null;
		$surcharge['completed'] = $cancellation == 2 ? ($source['completed'] ? $source['completed'] : date("Y-m-d H:i")) : null;

		updateTable('tblsurcharge', $surcharge, "surchargeid = $surchargeid", 1);
		
		logSurchargeChanges($source, $surcharge);
/*		// if completion has changed
		if($surcharge['completed'] != $source['completed']) 
			supersedeSurchargeBillable($surchargeid);
		else if($surcharge['canceled'] && !$source['canceled'])
			supersedeSurchargeBillable($surchargeid);
		else if($surcharge['charge'] != $source['charge']) {
//if(mattOnlyTEST()) logChange($surcharge['charge'], 'tblsurcharge', 'm', "charge {$source['charge']} => {$surcharge['charge']}");			
			supersedeSurchargeBillable($surchargeid);
		}
*/		
		if(($surcharge['completed'] != $source['completed'])
				|| ($surcharge['canceled'] && !$source['canceled'])
				|| ($surcharge['charge'] != $source['charge'])
			)	{
			$billableSuperseded = true;
			supersedeSurchargeBillable($surchargeid);
		}
		if($billableSuperseded && $surcharge['completed'])
			createSurchargeBillable($surchargeid);
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
		//$canceled = $cancellation == 1 ? date("Y-m-d H:i:a");
		$canceled = null;
		$completed = $cancellation == 2 ? date("Y-m-d H:i:a") : null;
		$newSurchargeId = createSurcharge($clientptr, $packageptr, $surchargecode, $date, $automatic, $providerptr, $appt, $note, $completed);
		require_once "invoice-fns.php";
		if($completed) createSurchargeBillable($newSurchargeId);
	}
	
	if(!$error) {
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('appointments', '$postReturn');window.close();</script>";
		exit;
	}
}

$windowTitle = $id ? 'Edit Surcharge' : 'Add a Surcharge';
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
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
$source['client'] = getOneClientsDetails($source['clientptr']);
$source['client'] = $source['client']['clientname'];
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<h2><?= $windowTitle ?></h2>
<?

/*if($id) {
	$otherComp = getOtherCompForAppointment($id);
	if($otherComp) $source['cancelcomp'] = $otherComp['compid'];
} */
displaySurchargeEditor($source, $updateList);

echo "<p>";
if(!$roDispatcher) echoButton('', "Update Surcharge", "checkAndSubmit()");
echo " ";
echoButton('', "Quit", 'window.close()');
//echo " ";
if(!$undeletable) echoButton('', "Delete Surcharge", "deleteSurcharge($id)", 'HotButton', 'HotButtonDown');
if(staffOnlyTEST()) {echo "&nbsp;<a href='surcharge-analysis.php?id=$id' target=analysis>Analyze</a>";}

foreach(fetchAssociations("SELECT * FROM tblsurchargetype") as $type) {
	$surchargeRates[] = $type['surchargetypeid'];
	$surchargeRates[] = $type['defaultcharge'];
	$surchargeRates[] = $type['defaultrate'];
}
$surchargeRates = join(',', $surchargeRates);
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

var surcharges = new Array(<?= $surchargeRates ?>);

function confirmAndClose() {
	if(true || confirm("Ok to close without saving changes?")) window.close();
}

function deleteSurcharge(id) {
	if(!confirm("This will delete the surcharge entirely.  Continue?")) return;
	document.getElementById('action').value = "delete";
	document.appteditor.submit();
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

setPrettynames('surchargecode','Surcharge Type','bonus','Bonus','adjustment','Adjustment','timeofday','Time Of Day');	
	
function checkAndSubmit(notifyclient) {
	//var conflict = document.getElementById('cancellation_1').checked && document.appteditor.completed.checked;
	if(MM_validateForm(
		'providerptr', '', 'R',
		'surchargecode', '', 'R'
		)) {
<? if(staffOnlyTEST()) { ?>
		var oldTotalCharge = document.getElementById('oldTotalCharge').value;
		var oldTotalRate = document.getElementById('oldTotalRate').value;
		var surchargeTypeCharge = document.getElementById('surchargeTypeCharge').value;
		var surchargeTypeRate = document.getElementById('surchargeTypeRate').value;
		var decision = false;
		var isCanceled = document.getElementById('cancellation_1') && document.getElementById('cancellation_1').checked;
		if(!isCanceled && 
				(surchargeTypeCharge != oldTotalCharge || surchargeTypeRate != oldTotalRate)) {
			decision = confirm("[STAFF]This surcharge\n  (charge: "+oldTotalCharge+", rate: "+oldTotalRate+")"
				+"\n\ndiffers from the curent values\n  (charge: "+surchargeTypeCharge+", rate: "+surchargeTypeRate+")."
				+"\n\nClick OK if you want to update to the current values"
				+" or Cancel to leave the values as they are.");
			if(decision == true) document.getElementById('action').value = 'updateamount';
		}
<? } ?>
		freezeApptEditor(false);
		document.appteditor.submit();
	}
}

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

function lookUpServiceRate(service, rates) {  // return [value, ispercentage]
	for(var i=0;i<rates.length;i+=3)  // servicetype,value,ispercentage
	  if(rates[i] == service)
	    return [rates[i+1],rates[i+2]];
	return -1;
}


</script>
</body>
</html>
