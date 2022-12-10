<?
// client-own-account.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "invoice-fns.php";
require_once "client-fns.php";
require_once "preference-fns.php";
require_once "cc-processing-fns.php";

// Determine access privs
$locked = locked('c-');

if(!getPreference('offerClientUIAccountPage')) {
	echo "Client Access to the Account page is not enabled for this company.  <a href='index.php'>Home</a> <a href='login-page.php?logout=1'>Logout</a>";
	exit;
}

$max_rows = 100;

extract($_REQUEST);

$client = $_SESSION["clientid"];

if($agreementOnly) {
	require_once "agreement-fns.php";
	$clientAgreement = clientAgreementSigned($_SESSION["auth_user_id"]);
	$agreement = getServiceAgreement($clientAgreement['agreementptr'], 0);
	$agreementTerms = filterString($agreement['terms']);
	$agDate = shortNaturalDate(strtotime($clientAgreement['agreementdate']))." ".date('h:i a', strtotime($clientAgreement['agreementdate']));
?>
<h2>Service Agreement</h2>
Agreement signed: <?= $agDate ?>
<p>
<div style='background:#eeeeee;border: solid black 1px;padding:5px;overflow:auto;height:400px;'>
<?= $agreement['html'] ? $agreementTerms : htmlizeAgreementText($agreementTerms) ?>
<p>
<span style='font-style:italic;font-size:80%'>Agreement version dated: <?= shortDateAndTime(strtotime($clientAgreement['date'])) ?></span>
</div>
<?
	exit;
}

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "POST: ".($_POST ? 1 : '0')." ROLE: $role ";print_r($_REQUEST);		exit;}
	


//if($_SESSION['auth_user_id'] == 3421) {echo print_r($_REQUEST, 1); exit;}
if($ccAction && ($_POST || $ccAction == 'dropACH'|| $ccAction == 'dropCC')) { // changing form action in IE9 is busted, so drop was reworked
//if($_SESSION['auth_user_id'] == 3421) {print_r($_POST);exit;}
	$_SESSION['ccTimeout'] = time() + $ccExpirationMinutes * 60;
	$oldCC = fetchRow0Col0(
			"SELECT ccid
				FROM tblcreditcard 
				WHERE clientptr = $client AND active = 1");
	if(achEnabled()) $oldACH = fetchRow0Col0(
			"SELECT acctid
				FROM tblecheckacct 
				WHERE clientptr = $client AND active = 1");
	if($ccAction != 'create') $ccid = null;
	if($ccAction == 'dropCC' || $ccAction == 'replace') {
		dropCC($oldCC);
		setCreditCardIsRequiredIfNecessary();
	}
	if($ccAction == 'dropACH' || $ccAction == 'replaceACH') {
		dropACH($oldACH);
		setCreditCardIsRequiredIfNecessary();
	}
	if($ccAction == 'replace' || $ccAction == 'create') {
		$_POST['ccid'] = null;
		$_POST['clientptr'] = $client;
		$_POST['x_exp_date'] = $_POST['expmonth'].'/1/'.$_POST['expyear'];
		if($ccid) deleteTable('tblcreditcardinfo', "ccptr = $ccid", 1);
		$data = $_POST;
		$data['gateway'] = $_SESSION['preferences']['ccGateway'];
		if($ccid) saveCC($data);
		else {
			$_POST['ccid'] = saveNewCC($data);
			$data['ccid'] = $_POST['ccid'];
		}
		if(!$_POST['useclientinfo']) saveCCInfo($data);
		setCreditCardIsRequiredIfNecessary();
		require_once "request-fns.php";
		$request= array('resolved' => 0, 'requesttype' => 'CCSupplied', 'clientptr'=>$client, 'note'=>"Credit card supplied");
		//array('requestid','fname','lname','phone','whentocall','email','address','street1', 'street2', 'city','state','zip','pets','note',
		// 'officenotes','clientptr','providerptr', 'resolved', 'requesttype', 'scope')
		saveNewClientRequest($request, $client);
	}
if($_REQUEST['iframe']) echo "<script>parent.document.location.href='client-own-account.php';</script>";
else globalRedirect('client-own-account.php');
}
//4640182041093324

$accountBalance = getAccountBalance($client, /*includeCredits=*/true, /*allBillables*/false);
$accountBalance = $accountBalance == 0 ? 'Nothing' : ($accountBalance < 0 ? dollarAmount(abs($accountBalance)).'cr' : dollarAmount($accountBalance));

if($mobileclient) {
	$extraHeadContent = '  <link rel="stylesheet" href="pet.css" type="text/css" /> 
';
	include "mobile-frame-client.php";
	echo "<h2>Account</h2>";
	
}
else {
	$pageTitle = "Your Account";
	if($_SESSION["responsiveClient"]) {
		$pageTitle = "<i class=\"fa fa-credit-card\"></i> Account";
		$extraHeadContent = "<style>body {font-size:1.2em;} .tiplooks {font-size:14pt;}</style>";
		include "frame-client-responsive.html";
		$frameEndURL = "frame-client-responsive-end.html";
	}
	else if(userRole() == 'c') {
		$extraHeadContent = <<<COLOR
		<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
		<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
		<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
COLOR;
		include "frame-client.html";
		$frameEndURL = "frame-end.html";
	}
}
// ***************************************************************************
?>
<table width='100%'><tr>
<? 
$balancePSADisplay = array();
if(!$_SESSION['preferences']['hideAccountBalanceFromClient']) $balancePSADisplay[] = "What You Owe: $accountBalance";
if($_SESSION['preferences']['showClientPSA']) {
	require_once "agreement-fns.php";
	$agreement = clientAgreementSigned($_SESSION["auth_user_id"]);
	if($agreement) {
		$agDate = shortNaturalDate(strtotime($agreement['agreementdate']))." ".date('h:i a', strtotime($agreement['agreementdate']));
		$balancePSADisplay[] = 
			"<span style='font-size:0.8em;'>"
			.fauxLink('Your Service Agreement', "viewAgreement()", 1, "Click to view the agreement")." (signed $agDate)"
			."</span>";
	}
}
$balancePSADisplay = join('<p>', $balancePSADisplay);
?>
<td style='font-size:1.2em;font-weight:bold;vertical-align:top;'><?= $balancePSADisplay ?></td>
<? if(($ePaymentNotice = ePaymentInfoRequiredNotice()) && mattOnlyTEST()) { ?>
<td style='text-align:right;vertical-align:top;'><?= $ePaymentNotice ?></td>
<? } ?>
<td style='text-align:right;vertical-align:top;'>
<?
	$ccData = getCCData();
	if(strpos($ccData, '#MISSING#') === 0) {
		$missing = 1;
		$ccData = substr($ccData, strlen('#MISSING#'));
	}
	if($_SESSION['preferences']['ccGateway'] 
			&& $_SESSION['preferences']['ccAcceptedList'] 
			&& !$_SESSION['preferences']['suppressClientCreditCardEntry']) {
		echo $ccData;
		echo ' ';
		$label = $missing ? "Supply a Card" : 'Change';
		$ccEditor = //in_array($_SESSION['preferences']['ccGateway'], array('Solveras', 'TransFirstV1')) 
			gatewayIsNMI($_SESSION['preferences']['ccGateway']) ? 'solveras-client-cc-edit.php' : 'client-cc-edit.php';
		echoButton('ccbutton', $label, 
			"editPaymentSource(\"".$ccEditor."\");"
			//'$.fn.colorbox({href:"'.$ccEditor.'", width:"480", height:"665", scrolling: true, opacity: "0.3"});'
			);
	}

	if(achEnabled()
			&& !$_SESSION['preferences']['suppressClientCheckingAccountEntry']) {
		$achData = getACHData();
		if(strpos($achData, '#MISSING#') === 0) {
			$missing = 1;
			$achData = substr($achData, strlen('#MISSING#'));
		}
		echo '<br>'.$achData;
		echo ' ';
		$label = $missing ? "Supply an E-Check Acct" : 'Change';
		$achEditor = $_SESSION['preferences']['ccGateway'] == 'Solveras' ? 'solveras-client-ach-edit.php' : 'client-ach-edit.php';
		echoButton('achbutton', $label, 
			"editPaymentSource(\"".$achEditor."\");"
			//'$.fn.colorbox({href:"'.$achEditor.'", width:"480", height:"665", scrolling: true, opacity: "0.3"});'
			);
	}
?></td>
</tr></table>
<?
hiddenElement('client', $client);
hiddenElement('invoiceStart', date('Y-m-d', strtotime("-18 months")));
hiddenElement('invoiceEnd', date('Y-m-d'));
historySection();


// ***************************************************************************
echo "<br><img src='art/spacer.gif' width=1 height=300>";
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
<? 
if($_SESSION['client-own-account-cc-time']) {
	echo "$(document).ready(function(){document.getElementById('ccbutton').click();});";
	unset($_SESSION['client-own-account-cc-time']); 
}
else if($_SESSION['client-own-account-ach-time']) {
	echo "$(document).ready(function(){document.getElementById('achbutton').click();});";
	unset($_SESSION['client-own-account-ach-time']); 
}

	?>
function viewAgreement() {
	<?= $_SESSION["responsiveClient"] 
		? "lightBoxIFrame(\"client-own-account.php?agreementOnly=1\", 380, 770);" 
		: "$.fn.colorbox({href:\"client-own-account.php?agreementOnly=1\", width:\"750\", height:\"770\", scrolling: true, opacity: \"0.3\"});"
	?>
}

function editPaymentSource(url) {
	<?= $_SESSION["responsiveClient"] 
		? "lightBoxIFrame(url+'?iframe=1', 400, 770);" 
		: "$.fn.colorbox({href:url, width:\"480\", height:\"665\", scrolling: true, opacity: \"0.3\"});" 
	?>
}

searchForInvoices();

<? //if($replaceACH) echo '$.fn.colorbox({href:"'.$achEditor.'", width:"480", height:"665", scrolling: true, opacity: "0.3"});\n'; ?>

</script>
<span style='font-size:8px'>Version 1</span>
<?
include $frameEndURL;

// ##################################### 4640182033333509
function historySection() {
	global $initializationJavascript;
	$initializationJavascript .= "setPrettynames('invoiceStart','Starting Date','invoiceEnd', 'Ending Date');\n";
//if($_SESSION['auth_user_id'] == 3421) {$DEBUG = "alert(document.cceditor.elements.length);";}	
  echo <<<JSCODE
<div id='clientinvoices'></div>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='client-cc-ach-entry.js'></script>
<script language='javascript'>
	
function searchForInvoices() {
	searchForInvoicesWithSort('');
}

function searchForInvoicesWithSort(sort) {
  if(true || MM_validateForm(
		  'invoiceStart', '', 'isDate',
		  'invoiceEnd', '', 'isDate')) {
		var client = document.getElementById('client').value;
		var starting = document.getElementById('invoiceStart').value;
		var ending = document.getElementById('invoiceEnd').value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		if(sort) sort = '&sort='+sort;
		var url = 'client-invoices-ajax.php';
    ajaxGet(url+'?client='+client+starting+ending+sort, 'clientinvoices')
	}
}

function sortInvoices(field, dir) {
	searchForInvoicesWithSort(field+'_'+dir);
}

function viewInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'invoice-view.php?id='+invoiceid+'&email='+email, 800, 800);
}



</script>

JSCODE;

}
function primaryFlag() {
	return
		"<div style='display:inline;border: solid black 1px;background:yellow;font-weight:bold;font-variant:small-caps;padding-left:2px;padding-right:2px;'>Primary</div> ";
}

function invalidFlag() {
	return
		"<div title='Please supply valid E-checking information.' style='display:inline;border: solid black 1px;background:red;color:yellow;font-weight:bold;font-variant:small-caps;padding-left:2px;padding-right:2px;'>Invalid</div> ";
}

function ePaymentInfoRequiredNotice() {
	if(!$_SESSION["creditCardIsRequired"]) return;
	if(achEnabled()) $orBankAccount = " or bank account";
	return "<span style='font-size:1.5em;color:red;'>A valid credit card$orBankAccount<br>is required to access your account</span>";
}
	

function getCCData() {
	global $primaryflag;
	$cc = fetchFirstAssoc(
			"SELECT ccid, tblcreditcard.company, tblcreditcard.last4, primarypaysource,
					tblcreditcard.x_exp_date, vaultid, tblcreditcardinfo.* 
				FROM tblcreditcard 
				LEFT JOIN tblcreditcardinfo ON ccptr = ccid
				WHERE clientptr = {$_SESSION['clientid']} AND active");
	//$style = $cc['primarypaysource'] ? "style='font-weight:bold;'" : '';
	if($cc['primarypaysource']) $pflag = primaryFlag();

	$descr = $cc 
			? "$pflag<span $style>{$cc['company']} ************{$cc['last4']} Exp: ".shortExpirationDate($cc['x_exp_date']).'</span>'
			: '#MISSING#'.($_SESSION["creditCardIsRequired"] && !mattOnlyTEST()
					? "<span style='font-size:1.5em;color:red;'>A valid credit card$orBankAccount is required to access your account</span>"
					: "No active credit card on record.");
	//if($cc['vaultid']) $descr = "<div style='display:inline;' title='Vault ID: {$cc['vaultid']}'>$descr</div>";
	return $descr;
}

function getACHData() {
	$ach = fetchFirstAssoc(
			"SELECT acctid, tblecheckacct.last4,  primarypaysource, vaultid,  gateway, tblecheckacctinfo.* 
				FROM tblecheckacct 
				LEFT JOIN tblecheckacctinfo ON acctptr = acctid
				WHERE clientptr = {$_SESSION['clientid']} AND active");
	//$style = $ach['primarypaysource'] ? "style='font-weight:bold;'" : '';
	if($ach['primarypaysource']) $pflag = primaryFlag();
	$descr = $ach 
			? "{$pflag}E-check: <span $style>************{$ach['last4']}</span>"
			: "#MISSING#No active bank account on record.";
	//if($ach['vaultid']) $descr = "<div style='display:inline;' title='Vault ID: {$cc['vaultid']}'>$descr</div>"; 
if($ach && $_SESSION['preferences']['ccGateway'] != $ach['gateway']) $descr .= ' '.invalidFlag();
	return $descr;
}

include "refresh.inc";
?>
