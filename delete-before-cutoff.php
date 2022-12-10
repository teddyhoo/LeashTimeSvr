<? // delete-before-cutoff.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-fns.php";
require_once "credit-fns.php";
require_once "js-gui-fns.php";
require_once "preference-fns.php";

locked('o-');
include "frame.html";

?>
<h1><font color=red>DELETE</font> from <?=  fetchPreference('bizName') ?></h1>
<form name='cutoffform' method='POST'>
<?
$cutoff = $_POST['cutoff'];
calendarSet('Delete Before Date', "cutoff", $cutoff);
echo "<img src='art/spacer.gif' width=20>";
echoButton('', 'Check', 'cutoffNow("check")');
hiddenElement('delete', '');
if($cutoff) {
	echo "<img src='art/spacer.gif' width=30>";
	echoButton('', 'Delete', 'cutoffNow(false)');
}
?>
</form>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('cutoff', 'Date');
function cutoffNow(check) {
	if(!MM_validateForm(
		'cutoff','','R', 
		'cutoff','','isDate')) return;
	if(!check && confirm('Sure you want to delete?')) {
		document.getElementById('delete').value=1;
	}
	document.cutoffform.submit();
}
<? dumpPopCalendarJS(); ?>
 </script>
<hr>

<?
if($cutoff) {
	$cutoff = dbDate($cutoff);
	echo "<h2>Delete Before: ".longDayAndDate(strtotime($cutoff))."</h2>";
	$appts = fetchCol0("SELECT appointmentid FROM tblappointment WHERE date < '$cutoff'");
	echo count($appts)." visits.";
	$surchargeids = fetchCol0("SELECT surchargeid FROM tblsurcharge WHERE date < '$cutoff'");
	echo '<br>'.count($surchargeids)." surcharges.";
	$chargeids = fetchCol0("SELECT chargeid FROM tblothercharge WHERE issuedate < '$cutoff'");
	echo '<br>'.count($chargeids)." miscellaneous charges.";

	$testappts = $appts ? "itemtable = 'tblappointment' AND itemptr IN (".join(',', $appts).")" : "1='no appts'";
	$testsurchargeids = $surchargeids ? "itemtable = 'tblsurcharge' AND itemptr IN (".join(',', $surchargeids).")" : "1='no surcharges'";
	$testchargeids = $chargeids ? "itemtable = 'tblothercharge' AND itemptr IN (".join(',', $chargeids).")" : "1='no misc charges'";


	$payables = fetchCol0("SELECT payableid FROM tblpayable 
													WHERE ($testappts)
														OR ($testsurchargeids)
														OR ($testchargeids)");
	echo "<br>".count($payables)." payables.";
	if($appts) $billables = fetchCol0("SELECT billableid FROM tblbillable WHERE itemtable = 'tblappointment' 
											AND itemptr IN (".join(',', $appts).")");
	echo "<br>".count($billables)." visit billables.";
	if($surchargeids) $surchargebillables = fetchCol0("SELECT billableid FROM tblbillable WHERE itemtable = 'tblsurcharge' 
											AND itemptr IN (".join(',', $surchargeids).")");
	echo "<br>".count($surchargebillables)." surcharge billables.";
	if($chargeids) $otherbillables = fetchCol0("SELECT billableid FROM tblbillable WHERE itemtable = 'tblothercharge' 
											AND itemptr IN (".join(',', $chargeids).")");
	echo "<br>".count($otherbillables)." misc charge billables.";

	$packageids = fetchCol0("SELECT packageid FROM tblservicepackage WHERE startdate < '$cutoff' AND (enddate IS NULL OR enddate < '$cutoff')");
	echo "<br>".count($packageids)." packages. "; //.join(', ', $packageids);
	
	
	$straddlers = fetchCol0("SELECT packageid FROM tblservicepackage WHERE startdate < '$cutoff' AND enddate >= '$cutoff'");
	$currentstraddlers = fetchCol0("SELECT packageid FROM tblservicepackage WHERE current=1 AND startdate < '$cutoff' AND enddate >= '$cutoff'");
	
	if($straddlers) {
		echo "<p>".count($straddlers)." packages (".count($currentstraddlers)." current packages) which straddle the cutoff date <font color=red>will not be deleted.</font>. ";
		if($currentstraddlers) {
			$currentstraddlers = fetchAssociations(
				"SELECT p.*, CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(' ', lname, fname) as sortname, IF(current=1,'current', 'old') as status
					FROM tblservicepackage p
					LEFT JOIN tblclient ON clientptr = clientid
					WHERE packageid IN (".join(', ', $currentstraddlers).")
					ORDER BY sortname ASC, p.startdate ASC");
			echo "<table border=1 bordercolor=black>";
			echo "<tr><th>Client<th>Package<th>Status<th>Start<th>End";
			foreach($currentstraddlers as $pack)
				echo "<tr><td>{$pack['client']}<td>{$pack['packageid']}<td>{$pack['status']}<td>{$pack['startdate']}<td>{$pack['enddate']}";
			echo "</table>";
		}
	}
	
	
	$creditids = fetchCol0("SELECT creditid FROM tblcredit WHERE issuedate < '$cutoff'");
	echo "<br>".count($creditids)." credits/payments.";
	$paymentids = fetchCol0("SELECT paymentid FROM tblproviderpayment WHERE paymentdate < '$cutoff'");
	echo "<br>".count($paymentids)."  sitter payments.";

	function doDelete($objects, $table, $condition, $message) {
		if($objects) {
			deleteTable($table, $condition, 1);
			echo '<br>'.mysql_affected_rows().$message;
		}
	}

	if($_POST['delete']) {
		echo "<hr>";
		doDelete($paymentids, 'tblproviderpayment', "paymentid IN (".join(',', $paymentids).")", " sitter payments deleted.");
		doDelete($creditids, 'tblcredit', "creditid IN (".join(',', $creditids).")", " credits/payments deleted.");
		doDelete($packageids, 'tblservicepackage', "packageid IN (".join(',', $packageids).")", " NR schedules deleted.");
		doDelete($payables, 'tblpayable', "itemtable = 'tblappointment' AND itemptr IN (".join(',', $appts).")", " payables deleted.");
		doDelete($appts, 'tblbillable', "itemtable = 'tblappointment' AND itemptr IN (".join(',', $appts).")", " visit billables deleted.");
		doDelete($surchargeids, 'tblbillable', "itemtable = 'tblsurcharge' AND itemptr IN (".join(',', $surchargeids).")", " surcharges billables deleted.");
		doDelete($chargeids, 'tblbillable', "itemtable = 'tblothercharge' AND itemptr IN (".join(',', $chargeids).")", " miscellaneous charge billables deleted.");
		doDelete($appts, 'tblappointment', "appointmentid IN (".join(',', $appts).")", " visits deleted.");
		doDelete($chargeids, 'tblothercharge', "chargeid IN (".join(',', $chargeids).")", " miscellaneous charges deleted.");
		doDelete($surchargeids, 'tblsurcharge', "surchargeid IN (".join(',', $surchargeids).")", " surcharges deleted.");


		$invoiceids = fetchCol0("SELECT invoiceid FROM tblinvoice WHERE date < '$cutoff'");

		doDelete($invoiceids, 'relinvoicecredit', "invoiceptr IN (".join(',', $invoiceids).")", " invoice credits deleted.");
		doDelete($invoiceids, 'relinvoiceitem', "invoiceptr IN (".join(',', $invoiceids).")", " invoice items deleted.");
		doDelete($invoiceids, 'tblinvoice', "invoiceid IN (".join(',', $invoiceids).")", " invoices deleted.");

		deleteTable('tblbillable', "itemtable = 'tblrecurringpackage' AND billabledate  < '$cutoff'", 1);
		echo '<br>'.mysql_affected_rows()." tblrecurringpackage billables deleted.";


	}
}
echo "<img src='art/spacer.gif' width=1 height=300>";
include "frame-end.html";
