<? // invoices.php -- show current invoices and invoices past due
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "invoice-gui-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);


$pastDueDays = $_SESSION['preferences']['pastDueDays'];
$pastDueDays = strlen(''.$pastDueDays) == '0' ? 30 : $pastDueDays;

$billDays = array();
if(is_numeric($_SESSION['preferences']['bimonthlyBillOn1'])) $billDays[] = $_SESSION['preferences']['bimonthlyBillOn1'];
if(is_numeric($_SESSION['preferences']['bimonthlyBillOn2'])) $billDays[] = $_SESSION['preferences']['bimonthlyBillOn2'];
sort($billDays);
$billDays = array_reverse($billDays);
$billDay = 1;  // in case no bill days specified
foreach($billDays as $billDay)
	if(date('j') >= $billDay) break; // date('j') = day of month

$billDay = min($billDay, date('t'));


if($asOfDate) $throughDateInt = strtotime($asOfDate);
if(!$throughDateInt) {
	$throughDateInt = strtotime("- 1 day", strtotime(date("m/$billDay/Y")));
	$asOfDate = date('Y-m-d', $throughDateInt);
}

$pageTitle = "Client Invoices";

include "frame.html";
// ***************************************************************************
/*
Sections:
Current Invoices
	Invoice Previews 
		- if there are uninvoiced billables
		- lists NOT INVOICED for notification
		- links to invoice-create.php
	Invoice
		- last invoice if no uninvoiced billables
		- lists as of date (= date of last billable)
		- links to invoice viewer
		- has email and print links

Invoices Past Due 15 Days
Invoices Past Due 30 Days
Invoices Past Due 45 Days
	- all invoices

*/
?>
<style>
.bluebar {width:99.85%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;
					text-align:center;font-size:1.1em;padding-top:5px;margin-bottom:2px;}
</style>
<?
//echoButton('', 'Print Selected Invoices', 'printSelectedInvoices()');
//echoButton('', 'Email Selected Invoices', 'emailSelectedInvoices()');
echo "<span style='font-size:1.1em'>";
echo "Current Invoices through ";
calendarSet('', 'asOfDate', $asOfDate);
echo " ";
echoButton('', 'Show', "changeAsOfDate({$invoice['clientptr']})");
echo " ";
labeledCheckbox('Show all clients', 'showAllClients', $showAllClients, null, null, "changeAsOfDate({$invoice['clientptr']})");

echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>
<span style='background:palegreen'>Last Billing Day was ".longDate(strtotime(date("m/$billDay/Y"))).
"</span>";

$uninvoicedCharges = getUninvoicedCharges(null, date('Y-m-d', $throughDateInt));
$totalUninvoiced = dollarAmount(array_sum($uninvoicedCharges));
$totalAcctBal = dollarAmount(getInvoicedAccountBalanceTotal());
$incompleteJobCounts = countAllIncompleteJobsByClient(date('Y-m-d', $throughDateInt));
echo "<p><div style='padding: 3px;border: solid black 1px;'><b>Total Accounts Due: $totalAcctBal
			<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>Total Uninvoiced Services: $totalUninvoiced (through ".longDate(strtotime($asOfDate)).")</div>";
echo "</span>";
$showAllClients = isset($showAllClients) ? $showAllClients : false;;

$clientDetails = getClientDetails(fetchCol0("SELECT clientid FROM tblclient"), array('invoiceby'), 'sorted');
/*$previewsAndInvoices = array();

foreach(array_keys($clientDetails) as $clientid) {
	$preview = createInvoicePreview($clientid);
	if($preview) $previewsAndInvoices[$clientid] = $preview;
}
$ignoreIds = $previewsAndInvoices ? "WHERE clientptr NOT IN (".join(',', array_keys($previewsAndInvoices)).")" : '';

$invoices = fetchAssociationsKeyedBy("SELECT * FROM tblinvoice $ignoreIds ORDER BY date", 'clientptr');
foreach($invoices as $clientid => $invoice) $previewsAndInvoices[$clientid] = $invoice;

$onlyIds = $previewsAndInvoices ? "WHERE clientptr IN (".join(',', array_keys($previewsAndInvoices)).")" : '';
$lastInvoiceIds = fetchKeyValuePairs("SELECT clientptr, invoiceid FROM tblinvoice $onlyIds ORDER BY date ASC");
*/

// for each client collect:
//	last invoice, if any, or array(clientid)
$invoices = fetchAssociationsKeyedBy("SELECT * FROM tblinvoice $ignoreIds ORDER BY date, invoiceid", 'clientptr');
foreach(array_keys($clientDetails) as $clientid)
	if(!isset($invoices[$clientid])) $invoices[$clientid] = array('clientptr'=>$clientid);

$nullChoice = 'email';

//echo "<h3 style='text-align:center;'>Invoices to Mail</h3>";
echo "<p><div class='bluebar'>Invoices to Mail</div></p>";
echo "<div style='position:relative;float:right';>";
echoButton('','Generate & Mail Invoices to Selected Clients','printSelectedInvoices()');
echo "</div>";
echo fauxLink('Select All', "selectAll(\"invoicestomail\", 1)", 'Select all current invoices for printing.');
echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>";
echo fauxLink('Deselect All', "selectAll(\"invoicestomail\", 0)", 'Clear all current invoice selections.');
echo "<p>";
$sortedInvoices = array();
foreach($clientDetails as $clientid => $client) {
	$clientInvoice = $invoices[$clientid];
	if(($showAllClients
			  || (($clientInvoice && $clientInvoice['balancedue'] && $clientInvoice['balancedue'] > 0) 
				 		|| ($uninvoicedCharges[$clientid] && $uninvoicedCharges[$clientid] > 0)
				 		|| $incompleteJobCounts[$clientid] > 0))
		 && ($client['invoiceby'] == 'mail' || (!$client['invoiceby'] && $nullChoice == 'mail'))) {
		$sortedInvoices[$clientid] = $clientInvoice;
	}
}

invoiceListTable($sortedInvoices, $throughDateInt, null, 'invoicestomail', $uninvoicedCharges);

//echo "<h3 style='text-align:center;'>Invoices to Email</h3>";
echo "<p><div class='bluebar'>Invoices to Email</div></p>";

echo "<div style='position:relative;float:right';>";
echoButton('','Generate & Email Invoices to Selected Clients','emailSelectedInvoices()');
echo "</div>";
echo fauxLink('Select All', "selectAll(\"invoicestoEmail\", 1)", 'Select all current invoices for printing.');
echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>";
echo fauxLink('Deselect All', "selectAll(\"invoicestoEmail\", 0)", 'Clear all current invoice selections.');
echo "<p>";
$sortedInvoices = array();
foreach($clientDetails as $clientid => $client) {
	$clientInvoice = $invoices[$clientid];
	if(($showAllClients
			  || (($clientInvoice && $clientInvoice['balancedue'] && $clientInvoice['balancedue'] > 0) 
				 		|| ($uninvoicedCharges[$clientid] && $uninvoicedCharges[$clientid] > 0)
				 		|| $incompleteJobCounts[$clientid] > 0))
		 && ($client['invoiceby'] == 'email' || (!$client['invoiceby'] && $nullChoice == 'email'))) {
		$sortedInvoices[$clientid] = $clientInvoice;
	}
}

invoiceListTable($sortedInvoices, $throughDateInt, null, 'invoicestoEmail', $uninvoicedCharges, 'checkEmail');

$unpaid = "paidinfull IS NULL AND";

echo "<p><div class='bluebar'>Invoices Past Due 15 Days</div></p>";
//echo "<h3>Invoices Past Due 15 Days</h3>";

// today - date > N
// today - date between N and m

$sql = "SELECT * FROM tblinvoice
				WHERE $unpaid TO_DAYS(CURDATE()) - TO_DAYS(date) BETWEEN ".($pastDueDays + 15)." AND ".($pastDueDays + 29);
$invoices = fetchAssociations(tzAdjustedSql($sql));
invoiceListTable($invoices, $throughDateInt, null, 'past15');

echo "<p><div class='bluebar'>Invoices Past Due 30 Days</div></p>";
//echo "<h3>Invoices Past Due 30 Days</h3>";
$sql = "SELECT * FROM tblinvoice
				WHERE $unpaid TO_DAYS(date) BETWEEN TO_DAYS(CURDATE()) - ".($pastDueDays + 30)." AND TO_DAYS(CURDATE()) - ".($pastDueDays + 44);
$invoices = fetchAssociations(tzAdjustedSql($sql));
invoiceListTable($invoices, $throughDateInt, null, 'past30');

echo "<p><div class='bluebar'>Invoices Past Due 45 Days</div></p>";
//echo "<h3>Invoices Past Due 45 Days</h3>";

$sql = "SELECT * FROM tblinvoice
				WHERE $unpaid TO_DAYS(date) <= TO_DAYS(CURDATE()) - ".($pastDueDays + 45);
$invoices = fetchAssociations(tzAdjustedSql($sql));
invoiceListTable($invoices, $throughDateInt, null, 'past45');

// ***************************************************************************
include "frame-end.html";
echo date('H:i:s');
include "refresh.inc";				

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
<? dumpPopCalendarJS(); ?>

function changeAsOfDate() {
	var showAllClients = document.getElementById('showAllClients').checked ? 1 : 0;
	if(MM_validateForm('asOfDate','','isDate'))
		document.location.href='invoices.php?asOfDate='+document.getElementById('asOfDate').value+'&showAllClients='+showAllClients;
}


function viewInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'invoice-view.php?id='+invoiceid+'&email='+email, 800, 800);
}

function payInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'invoice-payment.php?invoiceid='+invoiceid, 600, 400);
}

function editInvoice(clientptr) {
	openConsoleWindow('invoiceview', 'invoice-edit.php?client='+clientptr+'&asOfDate='+'<?= date('Y-m-d', $throughDateInt) ?>', 800, 800);
}

function viewClient(clientid) {
	openConsoleWindow('clientview', 'client-view.php?id='+clientid, 700, 500);
}

function viewRecent(clientid) {
	openConsoleWindow('recentview', 'invoices-recent.php?client='+clientid, 700, 500);
}



function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function selectAll(group, onoff) {
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf(group+'_') == 0)
			els[i].checked = onoff;
}

function printSelectedInvoices() {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') != -1 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert('Please select one or more invoices to print.');
		return;
	}
	openConsoleWindow('invoiceprint', 'invoice-generate.php?clients='+sels+'&target=mail&asOfDate=<?= date('Y-m-d', strtotime($asOfDate)) ?>', 700, 500);
}

function emailSelectedInvoices() {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') != -1 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert('Please select one or more invoices to print.');
		return;
	}
	ajaxGetAndCallWith('invoice-generate.php?clients='+sels+'&target=email&asOfDate=<?= date('Y-m-d', strtotime($asOfDate)) ?>', reportEmailSuccess, null);
}

function reportEmailSuccess(argument, txt) {
	alert(txt);
	update();
}

function update(target, aspect) {
	refresh();
}


</script>