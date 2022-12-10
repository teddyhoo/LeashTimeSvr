<? // reports-revenue-collected-by-service.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "projections.php";
require_once "service-fns.php";
require_once "credit-fns.php";
require_once "reports-archive-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType,csv', $_REQUEST));

		
$pageTitle = "Revenue Collected by Service Report";

$allowArchiving = staffOnlyTEST();

if(!$print && !$csv) {
	$extraHeadContent = '<style>.leftpaddollaramountcell {
  font-size: 1.05em; 
  padding-bottom: 4px; 
  padding-left: 24px; 
  border-collapse: collapse;
	text-align: right;
	vertical-align: top;

}
</style>';

	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	//if($allowArchiving) $breadcrumbs .= " - <a href='reports-archive.php?type=tax-collected&label=Tax Collected'>Tax Collected Reports Archive</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<span>A Report for payments in the stated period.</span><p>
	The period specified period cannot extend past today.<p>
	<form name='reportform' method='POST'>
	<table style='display:inline'>
	<tr><td colspan=2>
<?
	calendarSet('For the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	hiddenElement('csv', '');
?>
	</td>
	<td><? echoButton('', 'Generate Report', 'genReport()'); ?></td></tr>

	</table> 
<?
	
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	//echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	//echoButton('', 'Download Spreadsheet', "genCSV()");
?>
	</form>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start','Starting Date','end','Ending Date');
	function genReport() {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', 'NOT', 'isFutureDate',
				'end', 'NOT', 'isFutureDate')) document.reportform.submit();
	}
	function genCSV() {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			document.getElementById('csv').value=1;
		  document.reportform.submit();
			document.getElementById('csv').value=0;
		}
	}
	function spawnPrinter() {
		//document.location.href='reports-revenue.php?print=1&start=$pstart&end=$pend&reportType=$reportType'>
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', 'NOT', 'isFutureDate',
				'end', 'NOT', 'isFutureDate')) {
			var start = escape(document.getElementById('start').value);
			var end = escape(document.getElementById('end').value);
			var reportType = null;
			var types = document.getElementsByName('reportType');
			for(var i=0; i < types.length; i++)
				if(types[i].checked) reportType = types[i].value;
			openConsoleWindow('reportprinter', 'reports-tax-collected.php?print=1&start='+start+'&end='+end, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Revenue-Collected-By-Service.csv ");
	dumpCSVRow('Tax Collected By Client');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Revenue Collected by Service Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Revenue Collected by Service Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}

function dateCmp($a, $b) { return strcmp($a['date'], $b['date']); }

if($start && $end) {
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	$creditIDs = fetchCol0("SELECT creditid FROM tblcredit WHERE issuedate >= '$pstart 00:00:00' AND issuedate <= '$pend 23:59:59'");

	if(!$creditIDs) {
		echo "No payments found.";
	}
	else {
		// find all billables these credits have been applied to
		$relbills = fetchAssociations(
			$sql = "SELECT r.* , itemtable, itemptr, tax
				FROM relbillablepayment r
				LEFT JOIN tblbillable ON billableid = billableptr
				WHERE paymentptr IN (".join(',', $creditIDs).")", 1);
		foreach($relbills as $b) {
			if($b['itemtable'] == 'tblappointment') {
				$label = fetchRow0Col0(
					"SELECT label 
						FROM tblappointment 
						LEFT JOIN tblservicetype ON servicetypeid = servicecode
						WHERE appointmentid = {$b['itemptr']}
						LIMIT 1", 1);
				$breakdown[$label] += $b['amount'];
			}
			else if($b['itemtable'] == 'tblsurcharge') {
				$label = fetchRow0Col0(
					"SELECT label 
						FROM tblsurcharge 
						LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
						WHERE surchargeid = {$b['itemptr']}
						LIMIT 1", 1);
				$breakdown[$label = "(surcharge) ".$label] += $b['amount'];
			}
			else if($b['itemtable'] == 'tblothercharge')
				$breakdown[$label = "(Miscellaneous)"] += $b['amount'];
			else if($b['itemtable'] == 'tblrecurringpackage') 
				$breakdown[$label = '(Monthly)'] += $b['amount'];
			$billableids[$b['billableptr']] = array('label'=>$label, 'tax'=>$b['tax']);

		}
		foreach($billableids as $billableid=>$detail) {
			$label = $detail['label'];
			$breakdown[$label] -= $detail['tax'];
			if($detail['tax'] && $detail['tax'] > 0) $tax[$label] += $detail['tax'];
		}
		ksort($breakdown);
		foreach($breakdown as $k=>$v)
			$rows[] = array('Service'=>$k, 'Revenue'=>dollarAmount($v), 'Tax'=>dollarAmount($tax[$k]));
	//echo print_r($rows, 1)."<p>";			
		//quickTable($rows);
	}

	ob_start();
	ob_implicit_flush(0);
	if(!$rows) echo "<p>No information to report.";
	else {
		$columns = array('Service'=>'Service', 'Revenue'=>'Revenue', 'Tax'=>'Tax');
		if(!$tax) unset($columns['Tax']);
		$colClasses = array('Revenue'=>'leftpaddollaramountcell', 'Tax'=>'leftpaddollaramountcell');
		$headerClass = array('Service'=>'sortableListHeader', 'Revenue'=>'dollaramountheader_right', 'Tax'=>'dollaramountheader_right');
		tableFrom($columns, $rows, '', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses, $sortClickAction=null);
	}

	$body = ob_get_contents();
	ob_end_clean();
	
	echo $body;
}
if(!$print & !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}
else if(!$csv) {
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
}