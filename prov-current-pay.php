<?
// prov-current-pay.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "prov-schedule-fns.php";

// Determine access privs
$locked = locked('vh');

$maintenanceBlock = false; //dbTEST('houstonsbestpetsitters');
if($maintenanceBlock) {
	include "frame.html";
	echo "<h2>This page closed for maintenance.</h2>";
	include "frame-end.html";
	exit;
}

$max_rows = 25;

extract($_REQUEST);
//$through = date('Y-m-d');

$id = $_SESSION["providerid"];
$allowInvoiceSubmission = TRUE; //!usingMobileSitterApp() || dbTEST('dogslife');
$useLightBox = !usingMobileSitterApp();
if(userRole() == 'p' && $_SESSION["providerid"] != $id) {
  echo "<h2>Insufficient rights to view this page.<h2>";
  exit;
}

$provider = getProviderShortNames("WHERE providerid = $id");
$provider = $provider[$id];
$invoiceable = $_SESSION['preferences']['sittersCanSendICInvoices'] || (dbTEST('tonkapetsitters') && $_SESSION['providerid'] == 55);
$strictDateRange = $invoiceable;

if($submitIt) {
	$invoiceable = false;
	$invoiceable = false;
	$extraFields = 
		"<extra key=\"x-label-Requestor\"><![CDATA[{$_SESSION["fullname"]}]]></extra>"
		."<extra key=\"x-label-Starting\"><![CDATA[{$starting}]]></extra>"
		."<hidden key=\"providerptr\">$id</hidden>"
		."<extra key=\"x-label-Ending\"><![CDATA[{$ending}]]></extra>";
  $request['extrafields'] = "<extrafields>$extraFields</extrafields>";
  
  $noJavascript = true; // prevent javascript from being dumped as part of the request
  
	ob_start();
	ob_implicit_flush(0);
  include "provider-payables-inc.php";
	$invoiceContents .= ob_get_contents();
	ob_end_clean();
	$request['note'] = 
		str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r", "", $note)))
		."<hr>$invoiceContents";
	$request['resolved'] = 0;
	$request['requesttype'] = 'ICInvoice';
	$request['subject'] = 'IC Invoice from '.$_SESSION["fullname"]." #";
	$request['providerptr'] = $_SESSION["providerid"];
	require_once "request-fns.php";
	saveNewClientRequest($request, $notify=true, $appendRequestIDToSubject=true);
	if(usingMobileSitterApp()) {
		$windowTitle = "Invoice Submitted";
		require "mobile-frame-bannerless.php";
	}
	else {
		echo '<link rel="stylesheet" href="pet.css" type="text/css" />';
	}
	echo '<h2>Your Invoice has been submitted.</h2>';
	$closeAction = $useLightBox ? 'parent.$.fn.colorbox.close();' : "document.location.href=\"prov-current-pay.php?starting=$starting&ending=$ending\"";
	echoButton('', "Done", $closeAction);
	//echo "<script language='javascript'>parent.$.fn.colorbox.close();</script>";
	exit;
}

else if($setupInvoice) {
	$invoiceable = false;
	if(usingMobileSitterApp()) {
		$windowTitle = "Submit Invoice ";
		$extraHeadContent = '<link media="only screen" href="mobile-sitter.css" type= "text/css" rel="stylesheet" / >';
		require "mobile-frame-bannerless.php";
	}
	else {
		echo '<link rel="stylesheet" href="style.css" type="text/css" />';
		echo '<link rel="stylesheet" href="pet.css" type="text/css" />';
	}
	echo "<form name='invoiceform' method='POST'><table><tr>";
	hiddenElement('starting', $starting);
	hiddenElement('ending', $ending);
	hiddenElement('submitIt', 1);
	echo "<td>";
	echoButton('', 'Submit Invoice', 'document.invoiceform.submit();', 'Button', 'ButtonDown');
	echo "</td><td align=right>";
	$quitAction = $useLightBox ? "parent.$.fn.colorbox.close();" : "document.location.href=\"prov-current-pay.php?starting=$starting&ending=$ending\"";
	echoButton('', "Quit", $quitAction);
	echo "</td></tr>";
	$noteCols = usingMobileSitterApp() ? 35 : 75;
if(staffOnlyTEST()) {
	$startDB = date('Y-m-d', strtotime($starting));
	$endDB = date('Y-m-d', strtotime($ending));
	$incompleteVisitsThisPeriod = 
		fetchRow0Col0(
			"SELECT COUNT(*) 
				FROM tblappointment
				WHERE date >= '$startDB' AND date <= '$endDB'
					AND providerptr = {$_SESSION["providerid"]}
					AND canceled IS NULL
					AND completed IS NULL", 1);
	if($incompleteVisitsThisPeriod) echo "<p class='warning'><b>WARNING</b> $incompleteVisitsThisPeriod of your visits in this period are not marked complete.</p>";
}
	textRow('Note:', 'note', $note, $rows=5, $noteCols, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
	echo "</table>";
	echo "</form>";
}
else if(usingMobileSitterApp()) {
	$pageIsPrivate = true;	
	include "mobile-frame.php";
	echo "<style>.dateInput {width:80px;}</style>";

}
else {
	
	$endFrame = 1;
	include "frame.html";
}
// ***************************************************************************
if(userRole() == 'p' && $_SESSION['preferences']['sittersPaidHourly']) $suppressCols = 'amount';
$includeCalendarWidgets = $invoiceable;

if($invoiceable && $allowInvoiceSubmission) {
	$extraButtons = 
		"<img src='art/spacer.gif' width=20 height=1>"
		.echoButton('', 'Submit Invoice...', "setupInvoice()", '', '', 1, 'Click here to submit your pay request along with a note.');
}
if($invoiceable) echo "<form name='bobo'>";
include "provider-payables-inc.php";
if($invoiceable) echo "</form>";
// ***************************************************************************
if($invoiceable) { ?>
<img src='art/spacer.gif' width=1 height=300>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
setPrettynames('starting', 'Starting date', 'ending', 'Ending date');
function showVisitsInRange() {
	var url;
  if(url = getSubmitURL()) {
				document.location.href=url;
	}
	
}

function setupInvoice() {
	var url;
  if(url = getSubmitURL()) {
<? if($allowInvoiceSubmission && usingMobileSitterApp()) { ?>
		document.location.href=url+"&setupInvoice=1";
<? } else { ?>
		$.fn.colorbox({href:url+"&setupInvoice=1", width:"750", height:"600", iframe: "true", scrolling: true, opacity: "0.3"});
<? }  ?>
	}
}

function getSubmitURL() {
	if(MM_validateForm(
		  'starting', '', 'isDate',
		  'ending', '', 'isDate',
		  'starting', 'NOT', 'isFutureDate',
		  'ending', 'NOT', 'isFutureDate')) {
		return 'prov-current-pay.php?starting='+
													escape(document.getElementById('starting').value)+
													'&ending='+escape(document.getElementById('ending').value);
	}
}

</script>

<? }

if($endFrame) include "frame-end.html";
?>
