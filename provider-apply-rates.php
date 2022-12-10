<? // provider-apply-rates.php
/*
Provider Rates Updates

All appointments on/after the effective date are updated.

All services in current nonrecurring packages that END on/after the effective date are updated.
All services in current recurring packages with no cancellation date are updated.

Whenever appointments are created for ANY recurring or non-recurring schedule, the current provider rate is used.

So, whenever a schedule change causes appointments to be deleted and recreated, the new appointments will reflect the current rate.

This may cause confusion from time to time, when the rate on the schedule service rate differs from the generated visit rate.
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "service-fns.php";
require_once "js-gui-fns.php";
require_once "pet-fns.php";

// Determine access privs
$locked = locked('o-');
$id = $_REQUEST['id'];
$provider = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname, IF(nickname, '', CONCAT('(',nickname,')'))) as fullname
															FROM tblprovider
															WHERE providerid = $id
															LIMIT 1");
															
$allClientsPets = array();
function getPetsForClient($clientid) {
	global $allClientsPets;
	if(!isset($allClientsPets[$clientid])) 
		$allClientsPets[$clientid] = getClientPetNames($clientid);
	return $allClientsPets[$clientid];
}
															
if($_POST && $_POST['action'] == 'apply') {
	$id = $_POST['id'];
	$dbDate = date('Y-m-d', strtotime($_POST['effectiveDate']));
	$providerRates = getProviderRates($id);
	$standardRates = getStandardRates();
//echo "provider [$id]	Rates:<br>".print_r($providerRates,1);
//echo "<p>Standard	Rates:<br>".print_r($standardRates,1);
//exit;
	// modify all services associated with provider
	// find all current non-recurring packages starting on or after effective date
	$qualifyingPackageIds = fetchCol0("SELECT packageid FROM tblservicepackage WHERE current = 1 AND enddate >= '$dbDate'");
	$qualifyingPackageIds = array_merge($qualifyingPackageIds,
			fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE current = 1 AND cancellationdate IS NULL"));
	$qualifyingPackageIds = join(',', $qualifyingPackageIds);
	if($qualifyingPackageIds) {
		$packagesAltered = array();
		$services = fetchAssociations("SELECT serviceid, charge as totalcharge, servicecode, packageptr, rate, clientptr
																		FROM tblservice 
																		WHERE providerptr = $id AND packageptr IN ($qualifyingPackageIds)");
		foreach($services as $service) {
			$clientPets = getPetsForClient($service['clientptr']);
			//$newRate = calculateServiceRate($id, $service['servicecode'], $service['pets'], $clientPets, $service['totalcharge'], $providerRates, $standardRates);
			$newRate = NEWcalculateServiceRate($id, $service['servicecode'], $service['pets'], $clientPets, $service['totalcharge'], $providerRates, $standardRates);
			if($newRate == $service['rate']) continue;
			$packagesAltered[] = $service['packageptr'];
			updateTable('tblservice', array('rate'=>$newRate), "serviceid = {$service['serviceid']}", 1);
			logChange($service['serviceid'], 'tblservice', 'c', "rate|{$service['rate']}|$newRate");
			
		}
	}
	// modify designated appointments 
	$apptids = array();
	foreach($_POST as $key => $val) 
		if($val && strpos($key, 'appt_') === 0) $apptids[] = substr($key, strlen('appt_'));
	if($apptids) {
		$numAppts = count($apptids);
		$apptids = join(',', $apptids);
		$appts = fetchAssociations(
			"SELECT appointmentid, charge as totalcharge, rate, servicecode, pets, clientptr 
					FROM tblappointment WHERE appointmentid IN ($apptids)");
		foreach($appts as $appt) {
			$clientPets = getPetsForClient($appt['clientptr']);
			//$newRate = calculateServiceRate($id, $appt['servicecode'], $appt['pets'], $clientPets, $appt['totalcharge'], $providerRates, $standardRates);
			$newRate = NEWcalculateServiceRate($id, $appt['servicecode'], $appt['pets'], $clientPets, $appt['totalcharge'], $providerRates, $standardRates);
			updateTable('tblappointment', withModificationFields(array('rate'=>$newRate)), "appointmentid = {$appt['appointmentid']}", 1);
			// CANCELLATION status is unchanged, so no discount action is necessary
			logChange($appt['appointmentid'], 'tblappointment', 'c', "rate|{$appt['rate']}|$newRate");
		}
	}
	$message = "Changed the rates of ".(0+$numAppts)." visit(s), and services in ".count($packagesAltered)." service package(s)";
}

$effectiveDate = $_REQUEST['effectiveDate'];
if($effectiveDate) {
	if(!$standardRates) {
		$providerRates = getProviderRates($id);
		$standardRates = getStandardRates();
	}
	$dbDate = date('Y-m-d', strtotime($effectiveDate));
	$sql = "SELECT tblappointment.*, paid
					FROM tblappointment
					LEFT JOIN tblpayable ON itemptr = appointmentid AND itemtable = 'tblappointment'
					WHERE tblappointment.providerptr = $id AND tblappointment.date >= '$dbDate'
					ORDER BY tblappointment.date, starttime";
	$allAppointments = fetchAssociations($sql);
	$untouchables = array();
	foreach($allAppointments as $i => $appt) {
		if($appt['paid'] > 0) {
			$untouchables[] = $appt;
			unset($allAppointments[$i]);
		}
		$clientids[] = $appt['clientptr'];
	}
}
if($clientids) $clients = getClientDetails(array_unique($clientids));

$windowTitle = "Apply Pay Rates for Sitter: {$provider['fullname']}";
$extraBodyStyle = 'background:white;';
require "frame-bannerless.php";
// ***************************************************************************
if($message) echo "<p style='color:green'>$message</p><hr>";
getAllServiceNamesById(1);
//print_r($_SESSION['allservicenames']);
$columns = explodePairsLine('cb|&nbsp;||date|Date||client|Client||timeofday|Time of Day||service|Service||rate|Rate||newRate|New Rate');
$colClasses = array('rate'=>'dollaramountheader', 'newRate'=>'dollaramountheader');
?>
<h2><?= $windowTitle ?></h2>
<form method='POST' name='applyratesform'>
<?
hiddenElement('action', '');
hiddenElement('id', $id);
calendarSet('Make rate changes effective:', 'effectiveDate', $effectiveDate, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='');
echo " ";
echoButton('', 'Show Visits', 'changeDate()');
if(!$effectiveDate) {
	echoButton('', 'Cancel', 'window.close()0');
}
else {
	echo "<hr>";
	echo "<center>";
	echoButton('','Apply Rates', 'applyRates()', 'BigButton', 'BigButtonDown');
	echo " ";
	echoButton('', 'Cancel', 'window.close()');
	echo "</center>";
	if($untouchables) {
		$rows = array();
		$total = 0;
		$newTotal = 0;
		foreach($untouchables as $appt) {
			$clientPets = getPetsForClient($appt['clientptr']);
			//$newRate = calculateServiceRate($id, $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge'], $providerRates, $standardRates);
			$newRate = NEWcalculateServiceRate($id, $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge'], $providerRates, $standardRates);
			if($newRate == $appt['rate']) continue;
			$row = array();
			$row['date'] = shortDate(strtotime($appt['date']));
			$row['client'] = $clients[$appt['clientptr']]['clientname'];
			$row['timeofday'] = $appt['timeofday'];
			$row['service'] = $_SESSION['allservicenames'][$appt['servicecode']];
			$row['rate'] = dollarAmount($appt['rate']);
			$total += number_format($appt['rate'], 2, '.', '');
			$newTotal += number_format($newRate, 2, '.', '');
			$row['newRate'] = dollarAmount($newRate);
			$rows[] = $row;
		}
		$rows[] = array('service'=>'<b>Totals:</b>', 'rate'=>dollarAmount($total), 'newRate'=>dollarAmount($newTotal));
		if($rows) {
?>
<h3>Cannot Modify Sitter Pay for the Following Visits</h3>
<?
			tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses);
		}


}
$eligibleAppointments = array();
foreach($allAppointments as $appt) {
	$clientPets = getPetsForClient($appt['clientptr']);
	//$newRate = calculateServiceRate($id, $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge'], $providerRates, $standardRates);
	$appt['newRate'] = NEWcalculateServiceRate($id, $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge'], $providerRates, $standardRates);
	if($appt['newRate'] != $appt['rate']) $eligibleAppointments[] = $appt;
}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo count($allAppointments); }
?>
<h3><?= count($eligibleAppointments); ?> eligible visits found. Sitter Pay Will Be Modified for the Following Selected Visits</h3>
<?
	if(count($eligibleAppointments) == 0) {
		echo "No Visits Were Found To Modify Sitter Pay";
	}
	else {
		
		$rows = array();
		$total = 0;
		$newTotal = 0;
		foreach($eligibleAppointments as $appt) {
			//$newRate = calculateServiceRate($id, $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge'], $providerRates, $standardRates);
			$newRate = $appt['newRate'];
			//$clientPets = getPetsForClient($appt['clientptr']);
			//$newRate = NEWcalculateServiceRate($id, $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge'], $providerRates, $standardRates);
			//if($newRate == $appt['rate']) continue;
			$row = array();
			$row['cb'] = labeledCheckBox('', "appt_{$appt['appointmentid']}", 1, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=true);
			$row['date'] = shortDate(strtotime($appt['date']));
			$row['client'] = $clients[$appt['clientptr']]['clientname'];
			$row['timeofday'] = $appt['timeofday'];
			$row['service'] = $_SESSION['allservicenames'][$appt['servicecode']];
			$row['service'] = $row['service'] ? $row['service'] : $appt['servicecode'];
			$row['rate'] = dollarAmount($appt['rate']);
			$total += $appt['rate'];
			$newTotal += $newRate;
			$row['newRate'] = dollarAmount($newRate);
			$rows[] = $row;
		}
		if($rows) $rows[] = array('service'=>'<b>Totals:</b>', 'rate'=>dollarAmount($total), 'newRate'=>dollarAmount($newTotal));
		if(!$rows) echo "No Visits Were Found To Modify Sitter Pay";
		else {
			fauxLink('Select All Visits', 'selectAll(1)');
			echo " - ";
			fauxLink('De-select All Visits', 'selectAll(0)');
			tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses);
		}
	}
}
?>
</form>
<?= "<a href='javascript:window.print()'>Print this page</a> " ?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('effectiveDate', 'Effective Date');
function selectAll(on) {
	var boxes = document.getElementsByTagName('input');
	for(var i=0;i<boxes.length;i++)
		if(boxes.item(i).type == 'checkbox') boxes.item(i).checked = on ? true : false;
}

function changeDate() {
	if(MM_validateForm(
		'effectiveDate', '', 'R',
		'effectiveDate', '', 'isDate'
		)) {
		document.applyratesform.submit();
		//window.close();
	}
}

function applyRates() {
	var numVisits, numChosen;
	numVisits = 0;
	numChosen = 0;
	var boxes = document.getElementsByTagName('input');
	for(var i=0;i<boxes.length;i++)
		if(boxes.item(i).type == 'checkbox') {
			numVisits++;
			if(boxes.item(i).checked) numChosen++;
		}
	if(numVisits > 0 && numChosen == 0 && 
		!confirm('You have not selected any visits to update.\nOnly future visits (as yet unscheduled)\nwill be affected by this change.\nContinue?'))
		return;
	document.getElementById('action').value='apply';
	document.applyratesform.submit();
}

<? dumpPopCalendarJS(); ?>
</script>