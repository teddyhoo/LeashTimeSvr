<? // visit-updater.php
// a tool for LT staff to update visits without opening them
// to effect price changes and whatnot

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "client-fns.php";
require_once "pet-fns.php";
require_once "service-fns.php";
require_once "gui-fns.php";

locked('o-');

if(!staffOnlyTEST()) { echo "LT Staff only"; exit;}





$extraBodyStyle = "padding:20px;";
require "frame-bannerless.php";


$filter = "date > '2016-01-25' AND date <= '2016-02-05' ORDER BY date, starttime";

$apptids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE $filter");

$allserviceTypes = fetchAssociationsKeyedBy("SELECT * FROM tblservicetype ORDER BY label", 'servicetypeid');
foreach($apptids as $apptid) {
	$appt = getAppointment($apptid, true, true, true);
	$row = array('cb'=>"<input type=checkbox id='cb_{$appt['servicecode']}'>");
	foreach(explode(',','date,starttime,client,charge,provider,rate') as $f) {
		$row[$f] = $appt[$f];
		if($f == 'charge') {
			$row['service'] = "[{$appt['servicecode']}] ".$allserviceTypes[$appt['servicecode']]['label'];
			if($appt['billpaid'])
				$row[$f] .= ' (paid)';
		}
		if($f == 'rate' && $appt['providerpaid']) $row[$f] .= ' (paid)';
	}
	$row['mod'] = touchAppointment($appt['appointmentid']);
	$petCount = $source['pets'] == 'All Pets' ? $allClientPetCounts[$appt['clientptr']] : count(explode(',', $appt['pets']));
	if($petCount > 1) $row['service'] .= " ($petCount pets)";
	
	$row['service'] = fauxLink($row['service'], "openConsoleWindow(\"apptdetail\", \"appointment-edit.php?id=$apptid\", 600,600)", 1, 2);
	
	
	$rows[] = $row;
	
	$futurity = appointmentFuturity($row);

	if($appt['canceled']) $rowClass = 'canceledtask';
	else if($appt['completed']) $rowClass = 'completedtask';
	else if($futurity == -1) {
		if(!$appt['completed']) $rowClass = 'noncompletedtask';
	}
	//else if($row['highpriority']) $rowClass = 'highprioritytask';
	else $rowClass = null;
	if(!$rowClass && !($n & 1)) $rowClass = 'futuretaskEVEN'; // if even
	else if(!$rowClass) $rowClass = 'futuretask';
	else if($rowClass && !($n & 1)) $rowClass = $rowClass.'EVEN'; // if even
	
	$rowClasses[] = $rowClass;
}
echo "<h2>Visit Updater</h2>Filter: [$filter]<p>";

if(!$rows) echo "No visits found.";
else {
	foreach(array_keys($rows[0]) as $col) $columns[$col] = $col;
	tableFrom($columns, $rows, $attributes='border=1', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
}

$rows = array();
$serviceTypes = fetchAssociations("SELECT * FROM tblservicetype WHERE active = 1 ORDER BY label");
foreach($serviceTypes as $type) {
	$row = array();
	foreach(explode(',', 'servicetypeid,label,defaultcharge,defaultrate') as $f) {
		$row[$f] = $type[$f];
		if($f == 'defaultrate' && $type['ispercentage']) $row[$f] .= '%';
	}
	$rows[] = $row;
}
?>

<hr>
<h3>Active Service Types</h3>
<?
quickTable($rows, $extra="border=1", $style=null, $repeatHeaders=0);



function touchAppointment($appointmentid, $go=false) {
	global $scheduleDiscount, $allClientPetCounts, $allClientPets;
	
	$source = getAppointment($appointmentid);
	$oldAppt = getAppointment($appointmentid);
	if(!$allClientPetCounts[$source['clientptr']]) {
		$allClientPets[$source['clientptr']] = getClientPetNames($source['clientptr']);
		$allClientPetCounts[$source['clientptr']] = count(explode(',', $allClientPets[$source['clientptr']]));
	}
	$allPets = $allClientPets[$source['clientptr']];
	$currentCharges = getStandardCharges();
	$clientCustomCharges = getClientCharges($source['clientptr']);
//if($clientCustomCharges) echo fetchRow0Col0("SELECT CONCAT(fname,' ',lname) FROM tblclient WHERE clientid={$source['clientptr']}").': '.print_r($clientCustomCharges,1).'<br>';
	//foreach($clientCustomCharges as $code => $chrg) $currentCharges[$code] = $chrg;
	$currentCharge = $clientCustomCharges[$source['servicecode']] 
										? (float)($clientCustomCharges[$source['servicecode']]['charge'])
										: (float)($currentCharges[$source['servicecode']]['defaultcharge']);
	if($currentCharges[$source['servicecode']]['extrapetcharge']) {
		$petCount = $source['pets'] == 'All Pets' ? $allClientPetCounts[$source['clientptr']] : count(explode(',', $source['pets']));
		$currentCharge += ($petCount - 1) * $currentCharges[$source['servicecode']]['extrapetcharge'];
	}
	
	if($source['charge'] != $currentCharge) $appt['charge'] = $currentCharge;

//if($appointmentid == 213460) {print_r($source);echo "<p>pets: $allPets<p>Charge: {$appt['charge']}<p>";}
	$currentRate = calculateServiceRate($source['providerptr'], $source['servicecode'], $source['pets'], 
								$allPets, $currentCharge);
								
	if($source['rate'] != $currentRate) $appt['rate'] = $currentRate;
								
	if($appt) {
		foreach($appt as $k=>$v) $desc[] = "$k: {$oldAppt[$k]}=>$v";
		$desc = join(', ', $desc);
return $desc; // for TEST
		//if($go) updateTable('tblappointment', withModificationFields($appt), "appointmentid=$appointmentid", 1);
		logChange($appointmentid, 'tblappointment', 'm', "Visit updater. $desc");
		if($currentDiscount = getAppointmentDiscount($appointmentid)) {
				resetAppointmentDiscountValue($appointmentid, $currentCharge+$source['adjustment']);
		}		

		require_once "invoice-fns.php";
		recreateAppointmentBillable($appointmentid);
		// update nonrecurring package price
		if(!$oldAppt['recurringpackage']) {
			$packageid = findCurrentPackageVersion($oldAppt['packageptr'], $oldAppt['clientptr'], false);
			$price = calculateNonRecurringPackagePrice($packageid, $oldAppt['clientptr']);
			updateTable('tblservicepackage', array('packageprice'=>$price), "packageid = $packageid");
		}
		return array('oldAppointment'=>$oldAppt, 'newAppointment'=>$appt);
	}
}
?>
<script language='javascript' src='common.js'></script>