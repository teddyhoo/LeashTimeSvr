<?  // rates-check.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "pet-fns.php";

$allServiceNames = getAllServiceNamesById();

if($_REQUEST['explain']) {
	function explainRate($provider, $service, $pets, $allPets, $totalCharge, $providerRates=null, $standardRates=null, $visit=null) {
		global $allServiceNames;
		$explanation = serviceRateExplanation($provider, $service, $pets, $allPets, $totalCharge, $providerRates, $standardRates);
		$ex = "{$explanation['baseCharge']}<p>"
					."{$explanation['extraPetChargePerPet']}<p>"
					."{$explanation['extraPets']}<p>"
					."{$explanation['totalCharge']}<hr>"
					."{$explanation['rateApplied']}<p>";
		if($explanation['rawNumExtraPets']) {
			$ex .= "{$explanation['standardExtraPetRate']}<p>";
			if($explanation['customExtraPetRate']) $ex .= "{$explanation['customExtraPetRate']}<p>";
		}
		foreach((array)$explanation['rateFinal'] as $line) $ex .= "$line<p>";
		echo "Client: {$visit['client']}<br>Sitter: {$visit['provider']}<br>
		Date: ".date('m/d/Y', strtotime($visit['date']))." {$visit['timeofday']}<br>
		Service: {$allServiceNames[$visit['servicecode']]}<p> ";		
		echo "Rate Calculation:<p>$ex";
	}
	
	
	$visit = getAppointment($_REQUEST['explain'], true);
	$allPets = getClientPetNames($visit['clientptr']);
	explainRate($visit['providerptr'], $visit['servicecode'], $visit['pets'], $allPets, $visit['charge'], $providerRates, $standardRates, $visit);
	exit;
}


$start = $_REQUEST['start'] ? date('Y-m-d', strtotime($_REQUEST['start'])) : '';
$start = $start ? "WHERE date >= '$start'" : '';

$result = doQuery(
	"SELECT *, CONCAT(c.fname, ' ', c.lname) as client, CONCAT(s.fname, ' ', s.lname) as sitter 
		FROM tblappointment 
			LEFT JOIN tblclient c ON clientid = clientptr 
			LEFT JOIN tblprovider s ON providerid = providerptr
		$start
		ORDER BY date");
$standardRates = getStandardRates();
if($csv = $_REQUEST['csv']) {
	header("Content-Type: text/csv");
	header("Content-Disposition: attachment; filename=PayrateDiscrepancies.csv ");
	dumpCSVRow('Payrate Discrepancies');
	dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
	dumpCSVRow(explode(',', "Saved,Current,Diff,Created,Sitter,Pets,Date,Time,Service,Client"));
}
else {
?>
<link rel="stylesheet" href="pet.css" type="text/css" /> 

<style>.dol {text-align:right;} .correct{background:palegreen}</style>
<?
echo "<table border=1 bordercolor=black><tr><th>Saved<th>Current<th>Diff<th>Created<th>Sitter<th>Pets<th>Date<th>Time<th>Service<th>Client";
}
while($appt = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$appt['created'] = date('n/j/Y H:i', strtotime($appt['created']));
	$providerptr = $appt['providerptr'];
	$clientid = $appt['clientptr'];
	$providerRates[$providerptr] = $clientCharges[$providerptr] 
			? $providerRates[$providerptr] 
			: getProviderRates($providerptr);
	$allpets[$clientid] = $allpets[$clientid] ? $allpets[$clientid] : getClientPetNames($clientid);
	//$correctCharge = calculateServiceCharge($clientid, $appt['servicecode'], $appt['pets'], $allpets, $clientCharges[$clientid], $standardCharges);
	$correctRate = NEWcalculateServiceRate(
			$providerptr, 
			$appt['servicecode'], 
			$appt['pets'], 
			$allpets[$clientid], 
			$appt['charge']/*+$appt['adjustment']*/, 
			$providerRates[$providerptr], 
			$standardRates);
			
	if($correctRate != $appt['rate']) {
		$x++;
		$numPets = count(explode(',', ($appt['pets'] == 'All Pets' ? $allpets[$clientid] : $appt['pets'])));
		$totalDiff += ($diff = $appt['rate']-$correctRate);
		$sitters[$appt['sitter']] += $diff;
		$type = $appt['recurringpackage'] ? '<font color=red>[R]</font>' : 
		        ($appt['serviceptr'] ? '<font color=blue>[N]</font>' : '');
		if($csv) {
			dumpCSVRow(array($appt['rate'], $correctRate, $diff, $appt['created'], $appt['sitter'], $numPets, 
												$appt['date'], $appt['timeofday'], strip_tags($type)." ".$allservicenames[$appt['servicecode']], $appt['client']));
		}
		else {
			if(!$details[$appt['servicecode']][$appt['client']][$appt['sitter']])
				$details[$appt['servicecode']][$appt['client']][$appt['sitter']] = 
					serviceDetails($appt['servicecode'], $appt['clientptr'], $appt['providerptr']);
			$detail = $details[$appt['servicecode']][$appt['client']][$appt['sitter']];
			$sitterRate = !$detail['provider'] ? 'DEFAULT'
											: "{$detail['provider']['rate']} ".($detail['provider']['ispercentage'] ? '%' : '');
			$clientCharge = !$detail['client'] ? 'DEFAULT'
											: $detail['client']['charge'];
			$detailDescr = "{$allservicenames[$appt['servicecode']]}\n".
								"Charge: {$detail['standard']['defaultcharge']} Rate: {$detail['standard']['defaultrate']}".($pct = $detail['standard']['ispercentage'] ? '%' : '')
								."  extraCharge: {$detail['standard']['extrapetcharge']} extrarate: {$detail['standard']['extrapetrate']}$pct\n"
								.'SITTER: '.(!$appt['sitter'] ? 'Unassigned' : ($appt['sitter']."\n"
													."Rate: $sitterRate.\n"))
								."CLIENT: {$appt['client']}\n"
								."Charge: $clientCharge";
			$detailDescr = str_replace("\n", '\n', $detailDescr);
			if(strpos($allservicenames[$appt['servicecode']], '(inact') === FALSE) $inactive++;
			if($appt['recurringpackage']) $recurringpackage++;
			$service = fauxLink($allservicenames[$appt['servicecode']], 
				"openConsoleWindow(\"editappt\", \"appointment-edit.php?updateList=appointments&amp;id={$appt['appointmentid']}\",530,550)",
				1,
				null);
			$service .= " ".fauxLink('[detail]', "alert(\"$detailDescr\")", 1);
			$correctRate = "<a href='rates-check.php?explain={$appt['appointmentid']}' target='explanation'>$correctRate</a>";
			echo "<tr><td class=dol>{$appt['rate']}<td class='dol correct'>$correctRate<td class=dol>$diff<td>{$appt['created']}<td>{$appt['sitter']}
						<td>[$numPets]<td>{$appt['date']}
						<td>{$appt['timeofday']}<td>{$type} {$service}<td>{$appt['client']}";
		}
		//echo "Correct: {$correctRate} Incorrect: {$appt['rate']}  - {$appt['client']} {$appt['date']} {$appt['date']} {$appt['timeofday']}<br>";
	}
}
//print_r($details);
if($csv) exit;

echo "<tr><th>Saved<th>Current<th>Diff<th>Created<th>Sitter<th>Pets<th>Date<th>Time<th>Service<th>Client";
echo "<tr><td colspan=2>Saved - current<td>$totalDiff<td>".count($sitters)." sitters<td>$x visits";
echo "<tr><td colspan=2>Inactive<td>$inactive<td>Recurring<td>$recurringpackage";
foreach((array)$sitters as $sitter => $d) echo "<tr><td><td><td><td>$sitter<td>$d";
echo "<tr><td><td><td><td><td>".array_sum($sitters);
echo "<table>";

function dumpCSVRow($row) {
	if(!$row) echo "\n";
	if(is_array($row)) echo join(',', array_map('csv',$row))."\n";
	else echo csv($row)."\n";
}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}
?>
<script language='javascript' src='common.js'></script>