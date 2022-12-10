<? // invoices2.php -- show current invoices and invoices past due
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
<span style='background:palegreen'>Last Billing Day was ".longDate(strtotime(date("Y-m-$billDay"))).
"</span>";

$clientDetails = getClientDetails(fetchCol0("SELECT clientid FROM tblclient"), array('invoiceby'), 'sorted');


$uninvoicedCharges = getUninvoicedCharges(null, date('Y-m-d', $throughDateInt));
$totalUninvoiced = dollarAmount(array_sum($uninvoicedCharges));
$totalAcctBal = dollarAmount(getInvoicedAccountBalanceTotal());
$incompleteJobCounts = countAllIncompleteJobsByClient(date('Y-m-d', $throughDateInt));
echo "<p><div style='padding: 3px;border: solid black 1px;'><b>Total Accounts Due: $totalAcctBal
			<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>Total Uninvoiced Services: $totalUninvoiced (through ".longDate(strtotime($asOfDate)).")</div>";
echo "</span><p>";
$showAllClients = isset($showAllClients) ? $showAllClients : false;;

// for each client collect:
//	last invoice, if any, or array(clientid)
$invoices = fetchAssociationsKeyedBy("SELECT * FROM tblinvoice $ignoreIds ORDER BY date, invoiceid", 'clientptr');
foreach(array_keys($clientDetails) as $clientid)
	if(!isset($invoices[$clientid])) $invoices[$clientid] = array('clientptr'=>$clientid);





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
		document.location.href='invoices2.php?asOfDate='+document.getElementById('asOfDate').value+'&showAllClients='+showAllClients;
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
  w.document.location.href=url;
  if(w) w.focus();
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

function getActiveTab() {
  var els = document.getElementsByName('tabcell');
  
  var notByName = false;
  if(els.length == 0) {
		notByName = true;
		els = document.getElementsByTagName('td');
	}
  for(i=0;i<els.length;i++) {
		if(notByName && (els[i].name != 'tabcell')) {continue;}
		if(els[i].className == 'tabcellOn') return els[i];
	}
}

function clickTab(tabid) {
	var pageid = "tabpage_"+pageid;
  var notByName = false;
  var els = document.getElementsByName('tabcell');
  if(els.length == 0) {
		notByName = true;
		els = document.getElementsByTagName('td');
	}
  for(i=0;i<els.length;i++) {
		if(notByName && (els[i].name != 'tabcell')) {continue;}
		var page = document.getElementById("tabpage_"+els[i].id);
		if(!page) alert("tabpage_"+els[i].id);
    if(els[i].id == tabid) {
			page.style.display = 'inline';
			els[i].className = 'tabcellOn';
			els[i].style.fontWeight='bold';
		}
    else {
			page.style.display = 'none';
			els[i].className = 'tabcellOff';
			els[i].style.fontWeight='normal';
    }
  }
}

</script>