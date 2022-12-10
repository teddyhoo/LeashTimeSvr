<? // invoice-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract($_REQUEST);
$asOfDate = $asOfDate ? $asOfDate : date('Y-m-d');

$lockedPreview = $autopayview;
if(!$lockedPreview && $_POST) {
	$billableIds = array();
	$cannedVisitIds = array();
	foreach(array_keys($_POST) as $key) {
		if(strpos($key, 'item_') === 0  && substr($key, strlen('item_')))
			$billableIds[] = substr($key, strlen('item_'));
		else if(strpos($key, 'canceled_') === 0)
			$cannedVisitIds[] = substr($key, strlen('canceled_'));
	}
	
	$id = createCustomerInvoice($client, $billableIds, $cannedVisitIds, null, date('Y-m-d', strtotime($asOfDate)));
	//$id = createCustomerInvoice($client, $billableIds, $cannedVisitIds, null, $asOfDate);
	echo "<script language='javascript'>";
	if($_POST['invoiceby'] == 'mail') echo "document.location.href='invoice-print.php?ids=$id';";
	else echo "document.location.href='invoice-email.php?id=$id&email={$_POST['$email']}';";
	echo "if(window.opener.update) window.opener.update('invoices', $id);";
	echo "</script>";
	exit;
}

$error = "";
if(!isset($client)) $error = "Client ID not specified.";


$windowTitle = 'Review Invoice';
$extraBodyStyle = 'padding:10px;background:white;';
require "frame-bannerless.php";


if($error) {
	echo $error;
	exit;
}

require_once "js-gui-fns.php";
$numIncomplete = countAllClientIncompleteJobs($client, $asOfDate) +
								countAllClientIncompleteSurcharges($client, $asOfDate);
if($numIncomplete) {

	showIncompleteAppointmentsSection($client, $numIncomplete, $asOfDate);
}




$prefs = fetchFirstAssoc("SELECT invoiceby, email FROM tblclient WHERE clientid = $client LIMIT 1");
$sendOptions = array("Create & Email This Invoice", "Create & Print This Invoice");
if($autopayview) $sendOptions = array();
else if($prefs['invoiceby'] == 'mail') {
	$sendOptions = array_reverse($sendOptions);
}

$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#gi');

echo "<table><tr>";
if($readOnly) echo "<td><h2>Invoices cannot be created.</h2><h3>This page is informational only.</h3></td>";
else foreach($sendOptions as $index => $label) {
	echo "<td>";
	if($index == 0) echoButton('', $label, "checkAndSubmit(this)", 'BigButton', 'BigButtonDown');
	else {
		echo " or ";
		echoButton('', $label, "checkAndSubmit(this)");
	}
	if(!$prefs['email'] && strpos(strtoupper($label), 'EMAIL'))
		echo "<br>(but no email address on record)";
	echo "</td>";
}
echo "<td>";
echo "<img src='art/spacer.gif' width=40 height=1>";
echoButton('', 'View Details', "viewDetails($client, \"$asOfDate\")");
echo "</td>";

echo "</tr></table>";
echo "<p>The selected items will be included in this invoice.<p>Deselect any items you wish to exclude from this invoice (you will be able to include them in future invoices).<p>";
echo "\n<form name='invoiceeditor' method='POST'>\n";
hiddenElement('client', $client);
hiddenElement('invoiceby', '');
hiddenElement('email', $prefs['email']);
editClientInvoice($client, $asOfDate);
echo "</form>\n";


//$numIncomplete = countAllClientIncompleteJobs($client);
function showIncompleteAppointmentsSection($client, $numIncomplete, $asOfDate) {

	$clientDetails = getOneClientsDetails($client);
	echo "<table style='width:100%;background:palegreen;'>";
	startAShrinkSection("Incomplete Visits (Total: $numIncomplete)", 'incomplete', 0/*in_array('incomplete', $shrink)*/);
	echo "<span style='font-size:1.1em;color:red;'>$numIncomplete visits for client {$clientDetails['clientname']}  have not been marked complete and are not included in the invoice list.<p>
				Please consult the list below to review them.<p></span>";
	?>
	<form name='incompleteform'>
	<? 
	if(!$lockedPreview) {
		calendarSet('Starting:', 'incompletestart', null, null, null, true, 'incompleteend'); // date('m/d/Y', strtotime("-7 days"))
		calendarSet('ending:', 'incompleteend', $asOfDate);
		echo " ";
		echoButton('', 'Show Incomplete', 'showIncomplete()');
		echo " ";
		echoButton('', 'Show All Incomplete', 'showAllIncomplete()');
	}
	else {
		hiddenElement('incompletestart', null);
		hiddenElement('incompleteend', $asOfDate);
		echo "Ending: $asOfDate";
	}
	echo "<div id='incomplete_list'></div>";
	echo "</form>";
	endAShrinkSection();
	echo "</table>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='incomplete-appts.js'></script>";
}
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

setPrettynames('asOfDate','As-Of Date');

<? dumpPopCalendarJS(); ?>

function toggleContainer(el) {
	if(el.getAttribute('container') && !el.checked) {
		var container = getElementsByAttribute(document, 'input', 'containerid', el.getAttribute('container'))[0];
		if(container) container.checked=false;
	}
	else if(el.getAttribute('containerid')) {
		var cbs = getElementsByAttribute(document, 'input', 'container', el.getAttribute('containerid'));
		for(var i=0; i < cbs.length; i++)  cbs[i].checked=el.checked;
	}
}	

/* This script and many more are available free online at
   The JavaScript Source :: http://javascript.internet.com 
   Copyright Robert Nyman, http://www.robertnyman.com
   Free to use if this text is included */

function getElementsByAttribute(oElm, strTagName, strAttributeName, strAttributeValue){
    var arrElements = (strTagName == "*" && document.all)? document.all : oElm.getElementsByTagName(strTagName);
    var arrReturnElements = new Array();
    var oAttributeValue = (typeof strAttributeValue != "undefined")? new RegExp("(^|\\s)" + strAttributeValue + "(\\s|$)") : null;
    var oCurrent;
    var oAttribute;
    for(var i=0; i<arrElements.length; i++){
        oCurrent = arrElements[i];
        oAttribute = oCurrent.getAttribute(strAttributeName);
        if(typeof oAttribute == "string" && oAttribute.length > 0){
            if(typeof strAttributeValue == "undefined" || (oAttributeValue && oAttributeValue.test(oAttribute))){
                arrReturnElements.push(oCurrent);
            }
        }
    }
    return arrReturnElements;
}

function openChargeWindow(client) {
	var error = null;
	var asOfDate = document.getElementById('asOfDate').value;
	if(!asOfDate) error = prettyName('asOfDate')+' must be supplied first.\n';
	if(!validateUSDate(asOfDate)) error = prettyName('asOfDate')+' must contain a date in the form MM/DD/YYYY.\n';
	if(!isPastDate(asOfDate)) error = prettyName('asOfDate')+' must be a date before today.\n';
	if(error) {
		alert(error);
		return;
	}
	asOfDate = escape(asOfDate);
	var url = 'charge-edit.php?lastday='+asOfDate+'&reason=&';
	openConsoleWindow('editcredit', url+'client='+client, 600, 240);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}


function changeAsOfDate(clientptr) {
	if(MM_validateForm('asOfDate','','isDate'))
		document.location.href='invoice-edit.php?client='+clientptr+'&asOfDate='+escape(document.getElementById('asOfDate').value);
}

function checkAndSubmit(buttonEl) {
	var els = document.getElementsByTagName('INPUT');
	var checked = 0;
	document.getElementById('invoiceby').value = buttonEl.value.toUpperCase().indexOf('EMAIL') == -1 ? 'mail' : 'email';
	for(var i = 0; i < els.length; i++)
		if(els[i].type == 'checkbox' && els[i].checked) checked++;
	if(checked == 0) {
		if(document.invoiceeditor.pastbalancedue.value == 0 
			&& !confirm('You are about to create an invoice with no past balance due and no new charges.\nContinue?'))
				return;
		else if(invoiceeditor.pastbalancedue.value > 0 
			&& !confirm('You are about to create an invoice with no new charges.\nContinue?'))
				return;
	}
	document.invoiceeditor.submit();
}

<?
if($numIncomplete) {
	dumpShrinkToggleJS(); 
	echo <<<JS

incompleteClient = $client;
showIncomplete();
	
JS;
}
?>
function checkAllLineItems(on) {
	var inputs = document.getElementsByTagName('input');
	for(var i = 0; i < inputs.length; i++)
		if(inputs[i].type == 'checkbox' 
				&& (inputs[i].name.indexOf('item_') == 0
						|| inputs[i].name.indexOf('canceled_') == 0))
			inputs[i].checked = on ? true : false;
}
	

function update() {
	window.refresh();
	if(window.opener.update) window.opener.update();
}

function viewDetails(client, asOfDate) {
	openConsoleWindow('editcredit', 'invoice-detail-viewer.php?client='+client+'&asOfDate='+asOfDate, 800, 440);
}

function updateTotals() {
// TBD: Gather all selected billable IDS, send an AJAX request, 
// receive values for:
// 'thisInvoiceTD', 'taxTD', 'totalAccountBalanceDueTD', 'subtotalTD'
// and update these fields
// Q: what if credits have been consumed since windows have opened?
// A: send back refresh command with ids to preselect
}

function selectAllIncomplete(onoff) {
	var inputs;
	inputs = document.getElementsByTagName('input');
	for(var i=0; i < inputs.length; i++)
		if(inputs[i].type=='checkbox' && inputs[i].id.indexOf("appt_") == 0)
			inputs[i].checked = (onoff ? 1 : 0);
}
</script>
<?
include "refresh.inc";
?>

