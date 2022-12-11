<?
// provider-payment.php
// modes:
// csv = the report format to dump to a file in bizfiledirectory



require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "provider-fns.php";
require "pay-fns.php";
require "js-gui-fns.php";
set_time_limit(5 * 60);

// Determine access privs
//$locked = locked('o-');
$locked = locked('+o-,+d-,#pa');

$maintenanceBlock = false; // dbTEST('houstonsbestpetsitters') && !mattOnlyTEST();
if($maintenanceBlock) {
	include "frame.html";
	echo "<h2>This page closed for maintenance.</h2>";
	include "frame-end.html";
	exit;
}



/*else if(userRole() == 'd') {
	$locked = locked('d-#pa');
	$readOnly = !strpos($_SESSION['rights'], '#pp');*/	
$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#pp');

$csvfilename = "{$_SESSION["bizfiledirectory"]}payrollspreadsheet{$_SESSION["bizptr"]}.csv";
$thisScriptName = substr($_SERVER["SCRIPT_NAME"], 1); // provider-payment.php

$pageTitle = "Sitter Payment";

$startDate = $_REQUEST['startDate'] ? date('Y-m-d', strtotime($_REQUEST['startDate'])) : null;
$throughDate = $_REQUEST['throughDate'] ? date('Y-m-d', strtotime($_REQUEST['throughDate'])) : null;
$payDate = $_REQUEST['payDate'] ? date('Y-m-d', strtotime($_REQUEST['payDate'])) : null;


$csv = $_REQUEST['csv']; // set only when generating csv by ajax

if($_REQUEST['breakLogJam']) {
	unset($_SESSION['generatePayables_in_progress']);
	globalRedirect('provider-payment.php');
	exit;
}

if($_REQUEST['lastspreadsheet']) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=payrollspreadsheet.csv ");
	readfile($csvfilename);
	exit;
}


//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') screenLog(print_r($_REQUEST,1));

if($_POST) {
	if("{$_POST['provider_payment_token']}" != "{$_SESSION['provider_payment_token']}") {
		header("Location: ".globalURL("$thisScriptName?startDate=$startDate&throughDate=$throughDate"));
		exit;
	}
	unset($_SESSION['provider_payment_token']);
	if($_POST['action'] == 'payProviders') {
		$providerNames = getProviderCompositeNames();
		$paidProviders = array();
		foreach($_POST as $key => $value) {
			if(strpos($key, 'pay_') === 0) {
				$prov = substr($key, 4);
				$paidProviders[$prov] = $_POST["linetotal_$prov"];
				payProvider($prov, $_POST["linetotal_$prov"], 'regular', $throughDate, $_POST["note_$prov"], $_POST["check_$prov"], $startDate, $_POST["adjust_$prov"], $payDate);
			}
		}
		//header("Location: ".globalURL("index.php"));
		include "frame.html";
		echo '<style>.payreport td {font-size:1.1em;} .heading {font-size:1.1em;font-weight:bold;}</style>';
		if(!$paidProviders) echo "<span class='heading'>No providers were paid.</span>";
		else {
			echo "<span class='heading'>The following providers were paid:</span<p><table class='payreport' width='33%'>";
			foreach($paidProviders as $providerid => $value)
				echo "<tr><td>{$providerNames[$providerid]}</td><td>".dollarAmount($value)."</td><tr>";
			$pids = join(',', array_keys($paidProviders));
			logChange(-999, 'tblproviderpayment', 'c', count($paidProviders)." sitter payments:".$pids); // added 11/10/2017
		}
		echo "</table>";
		include "frame-end.html";
		exit;
	}
}

$_SESSION['provider_payment_token'] = microtime(1);

$providerNames = getProviderShortNames(''); //WHERE active
if(dbTEST('dogslife,dogonfitness')) $providerNames = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(', ', lname, fname) FROM tblprovider ORDER BY lname, fname");
$activeProviders = fetchCol0('SELECT providerid FROM tblprovider WHERE active');
//asort($providerNames);
uasort($providerNames, 'nameSort');

function nameSort($a, $b) { return strcmp(strtoupper($a), strtoupper($b)); }

if($throughDate) generatePayables($throughDate, null, true);

//foreach(getUnpaidPayables($throughDate) as $payable) {
//	$due[$payable['providerptr']] += $payable['amount'] - $payable['paid'];
//}
$handle = getUnpaidPayablesDBResult($throughDate, null, $startDate);
$mileageCompensation = array();
$aggregate = array();

while($payable = mysqli_fetch_assoc($handle)) {
	$due[$payable['providerptr']] += $payable['amount'] - $payable['paid'];
	if($_SESSION['preferences']['sittersPaidHourly'] && $payable['itemtable'] == 'tblappointment') {
		if(!$travelAllowance) 
			$travelAllowance = 
				fetchKeyValuePairs("SELECT providerptr, value FROM tblproviderpref WHERE property = 'travelAllowance'");
		$mileageCompensation[$payable['providerptr']] += $travelAllowance[$payable['providerptr']];
	}
	if($csv) classifyPayable($payable, $aggregate);
}


foreach($mileageCompensation as $provid => $comp) {
	//$due[$provid] += $comp;
}

$negatories = getNegativePayments($throughDate, null, $startDate);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog(print_r($due[0],1));screenLog(print_r($negatories,1));}
foreach($negatories as $negPayment) {
	$due[$negPayment['providerptr']] = $due[$negPayment['providerptr']] - ($negPayment['amount'] - $negPayment['paid']);
	if($csv) $aggregate[$negPayment['providerptr']]['neg'] += $negPayment['amount'] - $negPayment['paid'];
}

if($csv) { // dump report to a file: $csvfilename
	$taxidColumn = dbTEST('canineadventurebaltimore,canineadventure') ? 'taxid|Tax ID||' : '';
	$sitters = fetchAssociationsKeyedBy("SELECT providerid, lname, taxid, fname, nickname, CONCAT_WS(' ', fname, lname) as name FROM tblprovider ORDER BY lname, fname", 'providerid');
	if($csv == 'standard') {
		$filename = 'Payroll-Report-Aggregate.csv';
		$cols = explodePairsLine("lname|Sitter Last Name||fname|Sitter First Name||nickname|Nickname||{$taxidColumn}totaldue|Total Due||visitcount|Visits||visittotal|Visit Total||surchargetotal|Surcharges||gratuitytotal|Gratuities||other|Ad Hoc Comp||neg|Negative Comp");
	}
	else if($csv == 'bytype') {
		$filename = 'Payroll-Report-By-Service.csv';
		$cols = explodePairsLine("lname|Sitter Last Name||fname|Sitter First Name||nickname|Nickname||{$taxidColumn}lineitem|Line Item||visitcount|Visits||visittotal|Visit Total");
		$servicenames = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype ORDER BY label");
	}
	echo "starting dump...<br>";
	//header("Content-Type: text/csv");
	//header("Content-Disposition: inline; filename=$filename ");
	ob_start();
	ob_implicit_flush(0);
	dumpCSVRow("Payroll Report");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	if($startDate) $range = shortDate(strtotime($startDate));
	$range .= " - ".shortDate(strtotime($throughDate));
	dumpCSVRow("Period: $range");
	dumpCSVRow($cols);
	$row =  array();
	if($csv == 'standard') foreach($sitters as $providerid => $prov) {
		if($aggregate[$providerid]) {
			$row = $prov;
			$row['visitcount'] = array_sum($aggregate[$providerid]['visitcount']);
			foreach($aggregate[$providerid] as $k => $v)
				if(is_numeric($k)) 
					$row['visittotal'] += $v;
			$row['surchargetotal'] = $aggregate[$providerid]['surcharge'];
			$row['gratuitytotal'] = $aggregate[$providerid]['gratuity'];
			$row['other'] = $aggregate[$providerid]['other'];
			$row['neg'] = $aggregate[$providerid]['neg'];
			$row['totaldue'] = $row['visittotal'] + $row['surchargetotal'] + $row['gratuitytotal'] + $row['other'] + $row['neg'];
			dumpCSVRow($row, array_keys($cols));
		}
	}
	else if($csv == 'bytype') foreach($sitters as $providerid => $prov) {
		if($aggregate[$providerid]) {
			$fixedLabels = explodePairsLine('surcharge|Surcharges||gratuity|Gratuities||neg|Negative Compensation||other|Ad Hoc Compensation');
			foreach($aggregate[$providerid] as $k => $v) {
				$row = $prov;
				if(is_array($v)) continue; // visitcounts
				$row['visittotal'] += $v;
				if(is_numeric($k)) {
					$row['lineitem'] = $servicenames[$k];
					$row['visitcount'] = $aggregate[$providerid]['visitcount'][$k];
				}
				else $row['lineitem'] = $fixedLabels[$k];
				dumpCSVRow($row, array_keys($cols));
			}
		}
	}
	$out = ob_get_contents();
	ob_end_clean();
	if(file_exists($csvfilename)) 
		unlink($csvfilename);
	echo "about to put to...$csvfilename<br>";
	file_put_contents($csvfilename, $out);
	echo "finished putting to...$csvfilename<br>";
	exit;
}


if($due) ksort($due);
  
$columns = explodePairsLine('paynow|&nbsp;||provider|Sitter||amount|Pay||checknumber|Check/Transaction #'.
                              '||adjustment|Bonus||checkamount|Check Amount||note|Note');
$colClasses = array('amount'=>'dollaramountcell','checkamount'=>'dollaramountcell');
//if($due) foreach($due as $prov => $amount) {
$allNames = array(0 => 'Unassigned');
foreach($providerNames as $prov => $name) $allNames[$prov] = $name;
$providerNames = $allNames;
//$providerNames = array_merge(array(0 => 'Unassigned'),$providerNames);
foreach($providerNames as $prov => $name) {
	$exploreEvenIfZero = isset($due[$prov]);
	$amount = isset($due[$prov]) ? $due[$prov] : 0;
	if(!$amount && !in_array($prov, $activeProviders)) continue;
	/*if(!$amount) {
		$amount = sprintf("%.2f",$amount);
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td>&nbsp;</td><td class='sortableListCell'>".providerLink($prov)."</td><td class='dollaramountcell'>$amount</td><td colspan=4>&nbsp;</td></tr>");
    continue;
	}*/
	$disabled = $amount ? '' : 'DISABLED';
	$amount = sprintf("%.2f",(abs($amount) < .01 ? 0 : $amount));
	$row = array();
  $row['paynow'] = "<input type='checkbox' id='pay_$prov' name='pay_$prov' $disabled>";
  $row['provider'] = providerLink($prov);
  $row['amount'] = (!$due[$prov] && !$exploreEvenIfZero) ? '0.00' : amountLink("<div style='display:inline;' id='amount_$prov'>$amount</div>", $prov, $throughDate, $startDate);
  $row['checknumber'] = "<input size=18 id='check_$prov' name='check_$prov' maxlength=45 $disabled autocomplete='off'>";
  $row['adjustment'] = "<input size=4 id='adjust_$prov' name='adjust_$prov' onChange='updateCheckAmount($prov)' $disabled autocomplete='off'>";
  $amounts[] = $amount;
  $adjustmentIds[] = "adjust_$prov";
  $row['checkamount'] = "<div id='checkamount_$prov'>$amount</div>";
  $lineTotalIds[] = "linetotal_$prov";
  $row['note'] = "<input size=30 id='note_$prov' name='note_$prov' maxlength=45 $disabled autocomplete='off'>";
  $rows[] = $row;
  $prettynames[] = "adjust_$prov";
  $prettynames[] = "Adjustment #".(count($rows));
}
$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=4>&nbsp;</td><th class='dollaramountcell'>Total:</th>".
	                  "<td id='payroll' class='dollaramountcell'></td></tr>");

function dumpCSVRow($row, $cols=null) {
	if(!$row) echo "\n";
	if(is_array($row)) {
		if($cols) {
			$nrow = array();
			if(is_string($cols)) $cols = explode(',', $cols);
			foreach($cols as $k) $nrow[] = $row[$k];
			$row = $nrow;
		}
		echo join(',', array_map('csv',$row))."\n";
	}
	else echo csv($row)."\n";
}

function csv($val) {
	//if(mattOnlyTEST() && $val && is_array($val)) $val = print_r($val, 1);
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}


function classifyPayable(&$payable, &$aggregate) {
	// $payable = * FROM tblpayable
	static $ptrfields;
	if(!$ptrfields) 
		$ptrfields = explodePairsLine('tblappointment|appointmentid||tblgratuity|gratuityid||tblothercomp|compid||tblsurcharge|surchargeid');
	$table = $payable['itemtable'];
	$item = fetchFirstAssoc("SELECT * FROM $table WHERE {$ptrfields[$table]} = {$payable['itemptr']} LIMIT 1");
	$due = $payable['amount'] - $payable['paid'];
	if($item['appointmentid']) {
		$aggregate[$payable['providerptr']][$item['servicecode']] += $due;
		$aggregate[$payable['providerptr']]['visitcount'][$item['servicecode']] += 1;
	}
	else if($item['surchargeid']) $aggregate[$payable['providerptr']]['surcharge' /*$item['surchargecode']*/] += $due;
	else if($item['gratuityid']) $aggregate[$payable['providerptr']]['gratuity'] += $due;
	else if($item['compid']) $aggregate[$payable['providerptr']]['other'] += $due;
}
  
function providerLink($prov) {
	global $providerNames;
	return fauxLink($providerNames[$prov], "goToPayHistory($prov)",1,'Go to Pay History page for this provider.');
}

function amountLink($amount, $prov, $through, $startDate=null) {
	$amount = max($amount, 0.00);
	return fauxLink("$amount", "openConsoleWindow(\"paydetail\", \"provider-payables.php?id=$prov&through=$through&starting=$startDate\",700,600)",1, 'Show details');
}
$extraHeadContent = "<style>.greenButton {color: white; background: green;}
.greenButtonDown {color: white; background: darkgreen;} 
.hidden {display:none;}</style\n";
if(mattOnlyTEST()) $breadcrumbs = "<a href='?breakLogJam=1'>Break Logjam</a>";
include "frame.html";
// ***************************************************************************
echo "<form name='payform' method='POST'>";
hiddenElement('provider_payment_token', $_SESSION['provider_payment_token']);
hiddenElement('num_providers', count($due));
hiddenElement('action', '');
$countFilter = "date <= '$throughDate'".($startDate ? " AND date >= '$startDate'" : '');
if($throughDate && $numIncomplete = countAllProviderIncompleteJobs(null, $countFilter)) {
?>
<p align=center>

<?
$dateRange = "incompleteend=$throughDate";
$dateRange .= $startDate ? "&incompletestart=$startDate" : "&showIncomplete=days60";
$dateRange = "showIncomplete=1&$dateRange";
$incompleteLink =	fauxLink('here', '$(document).ready(function(){$.fn.colorbox({href:"incomplete-appts-lightbox.php?'.$dateRange.'", width:"750", height:"470", scrolling: true, opacity: "0.3", iframe: "true"});});',
						true);
?>

<span style='font-weight:bold;font-size:1.1em;'>There are <span id='incompletecount'><?= $numIncomplete ?></span> outstanding incomplete visits in this time range.
  Please Click <?= $incompleteLink ?> to review them.
</span>
<p>
<?
}
if($startDate && staffOnlyTEST()) {
	$activeProviderPtrs = fetchCol0("SELECT providerid FROM tblprovider WHERE active = 1", 1);
	if($activeProviderPtrs) $activeProviderPtrs = " AND providerptr IN (".join(',', $activeProviderPtrs).")";
	$priorPayables = 
		fetchRow0Col0("SELECT sum(amount - paid) FROM tblpayable WHERE date < '$startDate' $activeProviderPtrs");
	if($priorPayables) {
		$amt = dollarAmount($priorPayables);
		$priorLink =	fauxLink('here', "document.location.href=\"$thisScriptName?throughDate=$throughDate\"", true);
?>
<p align=center><span style='font-weight:bold;font-size:1.1em;'>There are an additional <?= $amt ?> in unpaid payables prior to this time range.
  Please Click <?= $priorLink ?> to review all payables through <?= shortDate(strtotime($throughDate)) ?>.
</span>
</p>
<?
	}
}

echo "<style>.fineprint {font: 0.8em normal } .required {color:red} .heavy {font-weight: bold; font-size: 1.2em;'}</style>";
echo "<span class='heavy'>Period from ";
calendarSet("<span class='fineprint'>(optional)</span>", 'startDate', $_REQUEST['startDate'], null, null, true, null, '');
calendarSet(" through <span class='fineprint required'>(required)</span>", 'throughDate', $_REQUEST['throughDate'], null, null, true, null); //, 'calculatePay()'
echo "</span>";

echo ' ';
echoButton('calcPayButton', 'Calculate Pay', 'calculatePay()');
echo ' ';
if(0 && mattOnlyTEST()) {
	echo "<img src='art/spreadsheet-32x32.png' style='cursor:pointer;' title='Create a spreadsheet.' onclick='chooseSpreadsheetStyle()'>";
}
else echoButton('dumpcsvbutton', 'Download Spreadsheet...', 'chooseSpreadsheetStyle()', null, null, $noEcho=false, 'Create a spreadsheet.');
//echoButton('blooog', 'Test', "alert(\"nyah nyah\")");
echo "<div id='valueschangeddiv' style='display:none;color:red;'>* Pay values for the sitters below may have changed."
			."<br>Please click <b>Calculate Pay</b> button to refresh the payments due to sitters *</div>";
echo "<div id='pleasewait' style='display:none;'>&nbsp;<p>Please wait while Sitter Payments are calculated...</div>";
echoButton('viewspreadsheetbutton', 'View Spreadsheet', $onClick='viewSpreadsheet()', $class='greenButton', $downClass='greenButtonDown', $noEcho=false, $title='View the report you generated.');
if(TRUE /* 2/2/2021 */) echo " <img src='art/printer20.gif' onclick='printThisPage(this)' style='cursor:pointer' title='Print this page.'>";
echo "<hr>";
if($throughDate) {
	echo '<p>';
	if($due) {
		fauxLink('Select All', 'checkAllProviders(true)');
		echo "<img src='art/spacer.gif' width=20 height=1>";
		fauxLink('Un-Select All', 'checkAllProviders(false)');
		echo "<img src='art/spacer.gif' width=20 height=1>";
		if(!$readOnly) echoButton('', 'Pay Selected Sitters', 'payProviders()');
//if(staffOnlyTEST()) {
		$payDate = $payDate ? shortDate(strtotime($payDate)) : shortDate();
		echo "<img src='art/spacer.gif' width=7 height=1>";
		if(!$readOnly) calendarSet('Pay Date:', 'payDate', $payDate);
//}
		echo "<img src='art/spacer.gif' width=20 height=1>";
	}
	if(staffOnlyTEST()) {
		echoButton('', 'Print Selected', 'printDetails()');
		echo "<img src='art/spacer.gif' width=20 height=1>";
	}

	if(!$readOnly) echoButton('', 'Make an Ad-Hoc Payment', 'adHocPayment()');

	tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, null, null, $colClasses);
	if($lineTotalIds) foreach($lineTotalIds as $id) hiddenElement($id);
	echo "</form>";

	if(count($rows) < 5) { ?>
	<div style='height:100px;'></div>
	<?
	}
}
else 
	echo "<div style='height:150px;'></div></form>";

?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('throughDate','Through Date', 'startDate', 'Period from');
<? if(isset($prettynames)) echo "setPrettynames('".join("','",$prettynames)."')"; ?>

for(var i=0;i<document.payform.elements.length;i++) {
	var el = document.payform.elements[i];
	if(el.name && (el.name.indexOf('pay_') == 0)) {
		el.onclick=updatePayroll;
	}
}

function goToPayHistory(prov) {
	if(confirm('If you go to the Pay History page for this sitter,\nany changes you made on this page will be lost.\nProceed?'))
	  document.location.href='provider-edit.php?tab=history&id='+prov;
}

function printThisPage(link) {
	link.style.display="none";
	window.print();
	link.style.display="inline";
}


function adHocPayment() {
	
	openConsoleWindow('adhocpayment', 'provider-adhoc-payment-payable.php',500,300);
}

var test = 0;
function calculatePay() {
		document.getElementById('action').value='calculatePay';
		if(MM_validateForm('throughDate', '', 'R',
												'throughDate', '', 'isDate',
												'throughDate', 'NOT', 'isFutureDate',
												'startDate', '', 'isDate',
												'startDate', 'NOT', 'isFutureDate')) {
			lockForm();
			//document.getElementById('pleasewait').style.display='block';
			//document.getElementById('calcPayButton').disabled=true;
			//document.getElementById('pleasewait').style.display='block';
			document.payform.submit();
		}
}

function chooseSpreadsheetStyle() {
	if(MM_validateForm('throughDate', '', 'R',
											'throughDate', '', 'isDate',
											'throughDate', 'NOT', 'isFutureDate',
											'startDate', '', 'isDate',
											'startDate', 'NOT', 'isFutureDate')) {
<? $lightBox = <<<LIGHTBOX
Please choose which report to generate:
<center><p><input type='button' value='Standard' onclick='generateSpreadsheet(\"standard\")'><br>(One line per sitter)</center>
<center><p><input type='button' value='Broken down by Line Item Type' onclick='generateSpreadsheet(\"bytype\")'><br>(One line per sitter per service type)</center>
<center><p><input type='button' value='Cancel' onclick='parent.$.fn.colorbox.close()'></center>
LIGHTBOX;
			$lightBox = str_replace("\n", " ", str_replace("\r", "", $lightBox));
?>
		var lightBox = "<?= $lightBox ?>";
		$(document).ready(function(){$.fn.colorbox(
			{html:lightBox,	width:"310", height:"280", scrolling: true, opacity: "0.3"});});
	}
}


function generateSpreadsheet(reportStyle) {
	if(MM_validateForm('throughDate', '', 'R',
											'throughDate', '', 'isDate',
											'throughDate', 'NOT', 'isFutureDate',
											'startDate', '', 'isDate',
											'startDate', 'NOT', 'isFutureDate')) {
		lockForm();
		//document.getElementById('dumpcsvbutton').disabled=true;
		var throughDate = document.getElementById('throughDate').value;
		var startDate = document.getElementById('startDate').value;
		var url = '<?= $thisScriptName ?>?csv='+reportStyle+'&throughDate='+throughDate+'&startDate='+startDate+'&reportStyle='+reportStyle;
		$.ajax({url:url,
						success: function(data) {
											unlockForm(); 
											//$('#viewspreadsheetbutton').show();
											$.fn.colorbox.close();
											viewSpreadsheet();}});
	}
}

function viewSpreadsheet() { // OBSELETE
	//var w = openConsoleWindow('tempwin', '<?= $thisScriptName ?>?lastspreadsheet=1',10,10);
	//setTimeout(function(){ w.close();}, 5000);
	var url = '<?= $thisScriptName ?>?lastspreadsheet=1';
	<? $agent = strtolower($_SESSION['userAgent']);
			if(strpos($agent, 'safari') && !strpos($agent, 'chrome') && strpos($agent, 'mac'))
				echo "window.open(url)";
			else echo "document.location.href = url";
	?>
}

function unlockForm() {
	$(':button').attr('disabled', null);	
//test += 1; document.getElementById('pleasewait').innerHTML = "TEST: "+test+' =>'+document.getElementById('calcPayButton').disabled;
	document.getElementById('pleasewait').style.display='none';
	$('#viewspreadsheetbutton').addClass('hidden');
	$('.BlockContent-body').busy("hide");
}

	

function lockForm() {
	$(':button').attr('disabled', 'disabled');	
//test += 1; document.getElementById('pleasewait').innerHTML = "TEST: "+test+' =>'+document.getElementById('calcPayButton').disabled;
	document.getElementById('pleasewait').style.display='block';
	$('.BlockContent-body').busy("busy");
	$('#viewspreadsheetbutton').hide();
}

function printDetails() {
	var ids = new Array();
	var message = '';
	if(document.getElementById('num_providers').value == 0) message = 'There are no sitters to pay.';
	else {
		for(var i=0;i<document.payform.elements.length;i++) {
			var el = document.payform.elements[i];
		  if(el.name && (el.name.indexOf('pay_') == 0) && el.checked) {
				ids[ids.length] = el.name.substring('pay_'.length);
			}
		}
	}
	if(ids.length == 0) message = 'You must first choose at least one sitter.';
  if(MM_validateForm('throughDate', '', 'R',
  										'throughDate', '', 'isDate',
  										'startDate', '', 'isDate',
  										'throughDate', 'NOT', 'isFutureDate', 										
  										message, '', 'MESSAGE')) {
		var through = document.getElementById('throughDate').value;
		var startDate = document.getElementById('startDate').value;
		openConsoleWindow("paydetail", "provider-payables-print.php?ids="+ids.join(',')
			+"&through="+through+"&starting="+startDate,700,600);
	}
}
	

function payProviders() {
	var numToPay = 0;
	var message = '';
	if(document.getElementById('num_providers').value == 0) message = 'There are no sitters to pay.';
	else {
		for(var i=0;i<document.payform.elements.length;i++) {
			var el = document.payform.elements[i];
		  if(el.name && (el.name.indexOf('pay_') == 0) && el.checked) {
				numToPay++;
			}
		}
	}
	if(numToPay == 0) message = 'You must first choose some sitters to pay.';
	

  if(MM_validateForm('throughDate', '', 'R',
  										'throughDate', '', 'isDate',
  										'throughDate', 'NOT', 'isFutureDate',
  										'payDate', '', 'isDate',  										
  										message, '', 'MESSAGE'
<? if($adjustmentIds) 
			foreach($adjustmentIds as $ii => $adj) {
				$min = mattOnlyTEST() ? 0-$amounts[$ii] : '0';
				echo ",\n '$adj', '', 'FLOAT',\n '$adj', $min, 'MIN'";
			}
?>							
  										)) {
<?  ?>												
		lockForm();
		document.getElementById('action').value='payProviders';
		document.payform.submit();
	}
}

function setThroughDate() {
  if(MM_validateForm('throughDate', '', 'R',
  										'throughDate', '', 'isDate',
  										'throughDate', 'NOT', 'isFutureDate')) {
		document.location.href='<?= $thisScriptName ?>?throughDate='+document.getElementById('throughDate').value;
	}
}

function update(target, value) {  // called from provider-payables
	if(target == 'incompletecount') {
		document.getElementById('incompletecount').innerHTML = value;
	}
	document.getElementById('valueschangeddiv').style.display='inline';
}

function updateCheckAmount(prov) {
	var el = document.getElementById('adjust_'+prov);
	el.value = el.value.replace(/^\s\s*/, '').replace(/\s\s*$/, '');  // trim
	if(!isFloat(el.value)) {
		alert('Only numbers are allowed here.');
		return;
	}
	var adj = isFloat(el.value) ? parseFloat(el.value) : 0;
<? if(!mattOnlyTEST()) { ?>	
	if(adj < 0) {
		alert('Negative values are not allowed here.');
		return;
	}
<? } ?>	
	payel = document.getElementById('amount_'+prov);
	var payAmount = isFloat(payel.innerHTML) ? parseFloat(payel.innerHTML) : 0;
	if(adj > (payAmount / 2)) alert('Warning: this bonus is more than half the pay amount.');

	el = document.getElementById('checkamount_'+prov);
	el.innerHTML = (parseFloat(document.getElementById('amount_'+prov).innerHTML)+adj).toFixed(2);
	document.getElementById('pay_'+prov).checked = true;
	updatePayroll();
}

function updatePayroll() {
	var payroll = 0;
	for(var i=0;i<document.payform.elements.length;i++) {
		var el = document.payform.elements[i];
		if(el.name && (el.name.indexOf('pay_') == 0)) {
			if(el.checked) {
			  var prov = el.name.substring(4);
			  var checkamount = parseFloat(document.getElementById('checkamount_'+prov).innerHTML);
				document.getElementById('linetotal_'+prov).value = checkamount;
			  payroll += checkamount;
			}
		}
	}
	document.getElementById('payroll').innerHTML = payroll.toFixed(2);
}

function checkAllProviders(state) {
	var el = document.payform.elements;
	for(var i=0;i<el.length;i++)
	  if(el[i].name.indexOf('pay_') > -1) el[i].checked = (state ? true : false);
	updatePayroll();
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
  return w;
}




<? dumpPopCalendarJS(); ?>
$('#viewspreadsheetbutton').hide();
</script>
<img src='art/spacer.gif' width=1 height=200>
<?

// ***************************************************************************
include "frame-end.html";
?>
