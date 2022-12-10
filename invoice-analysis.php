<? // invoice-analysis.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-gui-fns.php";
require_once "gui-fns.php";
require_once "surcharge-fns.php";


$auxiliaryWindow = true; // prevent login from appearing here if session times out

$locked = locked('o-');
extract($_REQUEST);

$billables = fetchAssociationsKeyedBy(
	"SELECT itemptr, billableptr, relinvoiceitem.charge as icharge,  tblbillable.*, prepaidamount
	   FROM relinvoiceitem 
	   LEFT JOIN tblbillable ON billableid = billableptr 
     WHERE relinvoiceitem.invoiceptr = $id", 'billableptr');
     
foreach($billables as $b) if($b['itemtable'] == 'tblappointment') $apptids[$b['itemptr']] = $b;
foreach($billables as $b) if($b['itemtable'] == 'tblsurcharge') $surchargeids[$b['itemptr']] = $b;
foreach($billables as $b) if($b['itemtable'] == 'tblothercharge') $chargeids[$b['itemptr']] = $b;
foreach($billables as $b) if($b['itemtable'] == 'tblrecurringpackage') $packageids[$b['itemptr']] = $b;
foreach($billables as $i => $b) if((float)$b['prepaidamount'] == 0.0) $billables[$i]['prepaidamount'] = null;

if($apptids) $appts = fetchAssociationsKeyedBy(
	"SELECT tblappointment.*
			FROM `tblappointment`
			WHERE appointmentid IN (".join(',', array_keys($apptids)).")", 'appointmentid');

if($surchargeids) $surcharges = fetchAssociationsKeyedBy(
	"SELECT tblsurcharge.*
			FROM `tblsurcharge`
			WHERE surchargeid IN (".join(',', array_keys($surchargeids)).")", 'surchargeid');

if($chargeids) $charges = fetchAssociationsKeyedBy(
	"SELECT tblothercharge.*
			FROM `tblothercharge`
			WHERE chargeid IN (".join(',', array_keys($chargeids)).")", 'chargeid');

if($packageids) $packs = fetchAssociationsKeyedBy(
	"SELECT tblrecurringpackage.*
			FROM `tblrecurringpackage`
			WHERE packageid IN (".join(',', array_keys($packageids)).")", 'packageid');


$serviceNames = $_SESSION['allservicenames'];
$surchargeTypes = getSurchargeTypesById(1);

echo count($billables)." invoice items found.";

foreach($billables as $billable) {
	if($billable['itemtable'] != 'tblappointment') continue;
	$item = $appts[$billable['itemptr']];
	$discount = fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = {$item['appointmentid']} LIMIT 1");
	$row = array('billableid'=>$billable['billableptr'], 'item'=>$item['appointmentid'],
								'date'=>$item['date'], 'type'=>'visit', 'icharge'=>dollarAmount($billable['icharge']),
								'service'=>$serviceNames[$item['servicecode']],
								'charge'=>dollarAmount($item['charge']+$item['adjustment'] - $discount),
								'prepaid'=>dollarAmount($billable['prepaidamount']));
	if($row['charge'] != $row['icharge']) $row['charge'] = "<font color=red>{$row['charge']}</font>";
	$rows[] = $row;
}

foreach($billables as $billable) {
	if($billable['itemtable'] != 'tblsurcharge') continue;
	$item = $surcharges[$billable['itemptr']];
	$row = array('billableid'=>$billable['billableptr'], 'item'=>$item['surchargeid'],
								'date'=>$item['date'], 'type'=>'surcharge', 'icharge'=>dollarAmount($billable['icharge']),
								'service'=>$surchargeTypes[$item['surchargecode']],
								'charge'=>dollarAmount($item['charge']),
								'prepaid'=>dollarAmount($billable['prepaidamount']));
	if($row['charge'] != $row['icharge']) $row['charge'] = "<font color=red>{$row['charge']}</font>";
	$rows[] = $row;
}

foreach($billables as $billable) {
	if($billable['itemtable'] != 'tblothercharge') continue;
	$item = $charges[$billable['itemptr']];
	$row = array('billableid'=>$billable['billableptr'], 'item'=>$item['chargeid'],
								'date'=>$item['issuedate'], 'type'=>'misc', 'icharge'=>dollarAmount($billable['icharge']),
								'service'=>$item['note'],
								'charge'=>dollarAmount($item['amount']),
								'prepaid'=>dollarAmount($billable['prepaidamount']));
	if($row['charge'] != $row['icharge']) $row['charge'] = "<font color=red>{$row['charge']}</font>";
	$rows[] = $row;
}

foreach($billables as $billable) {
	if($billable['itemtable'] != 'tblrecurringpackage') continue;
	$item = $packs[$billable['itemptr']];
	$row = array('billableid'=>$billable['billableptr'], 'item'=>$item['packageid'],
								'date'=>$item['monthyear'], 'type'=>'recurring', 'icharge'=>dollarAmount($billable['icharge']),
								'service'=>$item['monthyear'],
								'charge'=>dollarAmount($item['totalprice']),
								'prepaid'=>dollarAmount($billable['prepaidamount']));
	if($row['charge'] != $row['icharge']) $row['charge'] = "<font color=red>{$row['charge']}</font>";
	$rows[] = $row;
}

foreach($billables as $billable) {
	if(in_array($billable['itemtable'], array('tblappointment','tblsurcharge','tblothercharge','tblrecurringpackage'))) continue;
	$item = null;
	$row = array('billableid'=>$billable['billableptr'], 'item'=>$billable['itemptr'],
								'date'=>$item['monthyear'], 'type'=>'unknown', 'icharge'=>$billable['icharge'],
								'service'=>'Unknown billable type',
								'charge'=>$item['totalprice']);
	if($row['charge'] != $row['icharge']) $row['charge'] = "<font color=red>{$row['charge']}</font>";
	$rows[] = $row;
}


$columns = explodePairsLine('billableid|Billable||date|Date||type|Type||item|Item||icharge|Invoiced||prepaid|Prepaid||charge|Current Charge||service|Service');
tableFrom($columns, $rows, 'WIDTH=100% border=1 class="futuretask"',null,$headerClasses,null,null,null,$rowClasses, $colClasses);

