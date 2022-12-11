<? // find-bad-billables.php

//find all miscalculated billable prices

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "tax-fns.php";


$start = '2010-04-01';

$r = doQuery("SELECT tblbillable.*, CONCAT_WS(' ', fname, lname) as name FROM tblbillable 
LEFT JOIN tblclient ON clientid = clientptr
WHERE superseded = 0 AND billabledate >= '$start' ORDER BY lname, fname, billabledate");

if(!$r) exit;
echo '<table border=1><tr><th>name<th>Visit Date<th>Time<th>Billabledate<th>Billable Charge<th>Correct Charge<th>Difference';
while($b = mysqli_fetch_array($r)) {
	if($b['itemtable'] != 'tblappointment') continue;
	$appt = getAppointment($b['itemptr']);
	$discount = fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = {$b['itemptr']}");
	$appt['charge'] = $appt['charge'] - $discount;
	$tax = figureTaxForAppointment($appt);
	$charge = $appt['charge']+$appt['adjustment']+$tax;
	
	if($b['charge'] != $charge)
		echo '<tr><td>'.$b['name']
					.'<td>'.$appt['date']
					.'<td>'.$appt['timeofday']
					.'<td>'.$b['billabledate']
					.'<td>'.$b['charge']
					.'<td>'.$charge
					.'<td>'.($b['charge']-$charge
		);

}

echo '</table>';
