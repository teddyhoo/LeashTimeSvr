<?  // diag-dw-checkup.php
$csv = $_REQUEST['csv'];
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "pet-fns.php";
require_once "provider-fns.php";



$csv = $_REQUEST['csv'];
$starting = $_REQUEST['starting'];
if(!$starting) $starting = '2009-12-01';
$starting = date('Y-m-d', strtotime($starting));
$sort = $_REQUEST['sort'];
$sort = $sort ? $sort : 'date';



$sql =
	"SELECT tblappointment.*, concat(fname, ' ', lname) as pname 
	FROM `tblappointment`
	LEFT JOIN tblprovider ON providerid = providerptr
	WHERE to_days($sort) >= to_days('$starting') AND canceled IS NULL
	ORDER BY $sort";  //  AND to_days(created) < to_days('2010-04-20')// created like '2010-02-21%' OR created like '2010-02-22%'";
	
$q = doQuery($sql);

$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");

if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=dw-checkup.csv ");
}



?>
Created Starting (YYYY-mm-dd): <input id=starting value='<?= $starting ?>'> 
<style>.but {background:palegreen;cursor:pointer;}</style>
<a class=but onclick="document.location.href='diag-dw-checkup.php?sort=date&starting='+escape(document.getElementById('starting').value)">By Date</a> - 
<a class=but onclick="document.location.href='diag-dw-checkup.php?sort=created&starting='+escape(document.getElementById('starting').value)">By Creation Date</a>

<?
echo "<p>Sorted by $sort<p>";
//print_r($appts);
$n=0;
if(!$csv) echo "<table><tr><th>appt id<th>service<th>client<th>correct<th>incorrect<th>provider<th>date<th>created";
else echo "appt id,service,client,correct,incorrect,provider,date,created\n";
$diffs = array();
while($appt = mysqli_fetch_assoc($q)) {
	if(!isset($clientPets[$appt['clientptr']])) $clientPets[$appt['clientptr']] = getClientPetNames($appt['clientptr']);
	$clientPets = $clientPets[$appt['clientptr']];
	if(!isset($allRates[$appt['providerptr']])) $allRates[$appt['providerptr']] = getProviderRates($appt['providerptr']);
	$providerRates = $allRates[$appt['providerptr']];
	$numPets = count(explode(',', (string)$appt['pets']));
	if($numPets < 2) continue;
	$rate = calculateServiceRate($appt['providerptr'], $appt['servicecode'], $appt['pets'], $clientPets[$appt['clientptr']], $appt['charge'], $providerRates, $standardRates);
	if(true || $rate != $appt['rate']) {
		$diffs[$appt['pname']] += $rate - $appt['rate'];
		$n++;
		$serviceName = $_SESSION['servicenames'][$appt['servicecode']] ? $_SESSION['servicenames'][$appt['servicecode']] : 'unknown';
		
		if(!$csv) echo "<tr><td>[$n] <a target=ziggy href='appointment-edit.php?id={$appt['appointmentid']}'>{$appt['appointmentid']}</a>"
			."<td>\"$serviceName\"<td>{$clientNames[$appt['clientptr']]}<td>Correct: <font color=green>$rate</font> <td>Incorrect: <font color=red>{$appt['rate']}</font><td>{$appt['pname']}<td>{$appt['date']} "
			."<td style='background:lightgrey'>{$appt['created']}";
		//<font color=gray>".print_r($appt, 1).'</font><br>';
		else echo "{$appt['appointmentid']},\"$serviceName\",{$clientNames[$appt['clientptr']]},$rate,{$appt['rate']},{$appt['pname']},{$appt['date']},{$appt['created']}\n";
	}
}
if(!$csv) echo "</table><hr>";
foreach($diffs as $pname => $d) {
	if($csv) echo "$pname,".($d > 0 ? "underpaid" : "overpaid").",\$".abs($d)."\n";
	else echo "$pname: ".($d > 0 ? " underpaid " : " overpaid ")."\$".abs($d)."<br>";
}