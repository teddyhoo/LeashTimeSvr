<? // make-billables.php

include 'xdiagnostics.php';
echo "<hr>";
//exit;

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// find all monthly packages
$mps = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly");
if(!$mps) $mps = array(-99);
// find all completed appts which are not monthly and which are unbilled
$appts = fetchAssociations("SELECT tblappointment.clientptr, appointmentid, tblappointment.charge+ifnull(adjustment,0) as charge, date
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
					WHERE completed AND itemptr IS NULL AND packageptr NOT IN (".join(',', $mps).")");

// create billables for these appointments
$today = date('Y-m-d');
foreach($appts as $appt) {
	doQuery("INSERT INTO tblbillable SET clientptr={$appt['clientptr']}, itemptr={$appt['appointmentid']}, itemtable='tblappointment',
						charge='{$appt['charge']}', itemdate='{$appt['date']}', billabledate='$today'");
}
echo "Added ".count($appts)." billables.<p>";

$billableids = fetchCol0("SELECT billableid
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
					WHERE canceled IS NOT NULL AND (paid IS NULL OR paid = 0.00) AND itemptr IS NOT NULL AND packageptr NOT IN (".join(',', $mps).")");

// delete billables for deleted appointments
if($billableids) doQuery("DELETE FROM tblbillable WHERE billableid IN (".join(',', $billableids).")");
echo "Deleted ".count($billableids)." billables.<p>";
