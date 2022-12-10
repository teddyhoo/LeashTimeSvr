<? // client-cc-payNEW.php
/* require a userid and a clientid to determine identity of the client.
args:
rcip - client userid|clientid
amount
note - (optional) description field
sitters - (optional) 
					csv of sitter id's. OR...
					if negative int, find active sitters who served in the last (0-sitters) days.
					if not supplied, sitters for last 90 days will appear in gratuity menu

post args:
action - paywithcardonfile | paywithsuppliedcard
rcip
amount
note - (optional) description field
card fields - (optional)  (used only when action = paywithsuppliedcard)
sitter_1..sitter_3 - (optional) for gratuities
gratuity_1..gratuity_3 - (optional) for gratuities

*/
require_once "js-gui-fns.php";
require_once "preference-fns.php";
require_once "client-fns.php";

if($_REQUEST['success']) {
	$finalMessage = "Thank you for your payment!";
	$suppressMenu = true;
	include "frame-client.html";
	echo "<span class='fontSize1_3em'>$finalMessage</span><p><img src='art/spacer.gif' width=1 height=300>";
	include "frame-end.html";
	exit;
}

$THIS_SCRIPT_NAME = 'client-cc-payNEW.php';
$rcip = explode('|', $_REQUEST['rcip']);
$userid = $rcip[0];
$clientid = $rcip[1];
if(!$clientid || !$userid) $error = "Insufficient information to continue.";
if(!$error) {
	require_once "common/init_session.php";
	$startingDB = $_SESSION['db'];
	//if(mattOnlyTEST()) echo "STARTING DB: [$startingDB] ";//.print_r($_SESSION, 1);
	if($_REQUEST['restart']) {
			session_unset();
		  session_destroy();
			echo "BANG!";
			exit;
	}
	
	require_once "common/init_db_common.php";
	$user = fetchFirstAssoc(
		"SELECT userid, rights, bizptr, tblpetbiz.*
			FROM tbluser 
			LEFT JOIN tblpetbiz ON bizid = bizptr 
			WHERE userid = $userid 
			LIMIT 1");
	if(!$user) $error = "Unknown user.";
	else if(mattOnlyTEST() && $startingDB) { // there is a live LeashTime Session
		//echo "DB: [$startingDB] userDB: [{$user['db']}] userid: [$userid] auth_user_id: [{$_SESSION["auth_user_id"]}]";
		if($startingDB != $user['db'] || $userid != $_SESSION["auth_user_id"])
			$error = "This page may not be used in this login session.";
	}
}
if(!$error) {
	reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass'], 1);
	setLocalTimeZone($zoneLabel=null);
	$_SESSION["uidirectory"] = "bizfiles/biz_{$user['bizptr']}/clientui/";
	$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid AND userid = $userid LIMIT 1");
	if(!$client) $error = "Unknown client.";
}

if(!$error) {	
	require_once "cc-processing-fns.php";
	$merchantInfo = merchantInfoSupplied();
	if(!$merchantInfo) $error = 'Incomplete merchant authorization.';
	$gatewayName = $merchantInfo['ccGateway'];
	if(gatewayIsNMI($gatewayName)) $_SESSION['client-cc-pay-url'] = $_SERVER["REQUEST_URI"];
	if($_POST['pay']) {
	}
}

//print_r($_REQUEST);
//echo $error;
if(!$error) {
	extract(extractVars('note,amount,x_card_num,company,expmonth,expyear,x_card_code,x_first_name,x_last_name'
											.',x_company,x_address,x_zip,x_city,x_state,x_country,x_phone', $_REQUEST));

	// amount may be messed up by some webmail clients
	$amount = $amount ? floatval(str_replace(",","","$amount")) : $amount;
	$ZERODUEOPTION = getPreference('enableGratuitySoliciation') || dbTEST('dogwalkingdc'); // mattOnlyTEST(); // 1
	$GRATUITYONLY = !$amount && $ZERODUEOPTION;
//if(mattOnlyTEST()) echo "	[$ZERODUEOPTION] / [$GRATUITYONLY]<hr>"; 
	// AJAX call to see if login is necessary: client-cc-pay.php?rcip=XX|YY&checklogin=1
	function passwordChecked() {
		if(!$_SESSION['cardonfilepasswordcheck']) return;
		if(time() - $_SESSION['cardonfilepasswordcheck'] > 300) return;
		return $_SESSION['cardonfilepasswordcheck'];
	}
	
	if($_GET['checklogin']) {
		echo 'log:'.passwordChecked();
		exit;
	}

	// AJAX call to login:client-cc-pay.php?login=1&rcip=XX|YY>&loginid="+loginid+'&value='+password,
	if($_GET['login']) {
		require "common/init_db_common.php";
		require_once "login-fns.php";
		$user = login($_GET['loginid'], $_GET['password']);
		$result = $user['userid'] == $userid ? 1 : 0;
		if($result) {
			$_SESSION['cardonfilepasswordcheck'] = time();
			if(!$_SESSION['rights']) $_SESSION['rights'] = 'c-temp'; // to allow CC fetching
			$_SESSION["bizptr"]	= $user['bizptr']; // useful for access to logo
		}
		echo 'log:'.$result;
		exit;
	}



	if($_POST['action'] == 'adhocpay') {
		require_once "cc-processing-fns.php";
		$expiration = $_POST['expmonth'].'/1/'.$_POST['expyear'];
		$merchantInfo = merchantInfoSupplied(); 
		if(!$merchantInfo) $error = 'Merchant info is missing.';
		else {
			$adHocCard = array('clientptr'=>$clientid, 'x_exp_date'=>$expiration, 'adhoc'=>1, 'gateway'=>$merchantInfo['ccGateway']);
			$fields = explode(',', 'x_card_num,x_card_code,company,x_first_name,x_last_name,x_company,x_address,x_city,x_state,x_zip,x_country,x_phone');
			$_POST['x_state'] = strtoupper("".$_POST['x_state']); // for the sake of TransactionExpress
			foreach($fields as $fld) if($_POST[$fld])
				$adHocCard[$fld] = $_POST[$fld];
			$adHocCard['ccid'] = saveNewAdHocCC($adHocCard); // saves only: ccid,last4,x_exp_date,company,clientptr,created,modified,createdby,modifiedby,gateway
			$companyAndLast4 = fetchFirstAssoc("SELECT company, last4 FROM tblcreditcardadhoc WHERE ccid = {$adHocCard['ccid']} LIMIT 1"); // to get company, last4
			foreach($companyAndLast4 as $k => $v) $adHocCard[$k] = $v;
		}
	}
}
if(!$error && ($_POST['action'] == 'paywithcardonfile' || $_POST['action'] == 'adhocpay')) {
	if(!$adHocCard && !passwordChecked()) {
		echo "Cannot complete transaction.  Login has expired.";
		exit;
	}
	else {
		
// **************************************************	
		require_once "credit-fns.php";
		require_once "invoice-fns.php";
		require_once "gratuity-fns.php";
		require_once "cc-processing-fns.php";

				$newPaymentId = null;
		/*if(mattOnlyTEST()) {
		print_r($_REQUEST);
		exit;
		}*/
		$gratuities = array();
		foreach($_POST as $k => $v) {
			if(strpos($k, 'sitter_') === 0) {
				$i = substr($k, strlen('sitter_'));
				if($_POST["gratuity_$i"]) 
					$gratuities[$v] = $_POST["gratuity_$i"];
			}
		}
	//echo 'Gratuities: '.print_r($gratuities,1);
	//echo "<p>Amount: {$_POST['amount']} + Gratuities: ".array_sum($gratuities)." = ".($_POST['amount']+array_sum($gratuities));
	//exit;
		// $adHocCard will (and should) be null if $_POST['action'] == 'paywithcardonfile'
		$totalAmount = $amount+array_sum($gratuities);
		$success = payElectronically($clientid, $adHocCard, $totalAmount, $note, null, null, array_sum($gratuities), $noLoginPayment=true);
		$newPaymentId = $latestPaymentId; //$latestPaymentId is globally set in payElectronically
		if(is_array($success)) {
			if($success['FAILURE']) $error = $success['FAILURE'];
			else $error = 'ERROR: '.ccLastMessage($success);
		}
		else {
			$successMessage = "Transaction # $success approved.";
			if($_SESSION['rights'] == 'c-temp') unset($_SESSION['rights']);
		}
		// 			$gratuity = array('paymentptr'=>$paymentptr, 'tipnote'=>$data['tipnote'], 'clientptr'=>$clientptr,
		//									'issuedate'=>$issuedate, 'amount'=>$value, 'providerptr'=>$data["gratuityProvider_$index"]);
		if($newPaymentId) foreach($gratuities as $providerptr => $gratuityAmount) {
			$gratuity = array('paymentptr'=>$newPaymentId, 'tipnote'=>$tipNoteIfAny, 'clientptr'=>$clientid,
												'issuedate'=>date('Y-m-d H:i:s'), 'amount'=>$gratuityAmount, 'providerptr'=>$providerptr);
			insertTable('tblgratuity', $gratuity, 1);
			makeGratuityNoticeMemo($gratuity);
		}
	// **************************************************	
		if($error && !$adHocCard) {
			$error = "We are sorry, but payment could not be made with the credit card information on file: $error";
		}
		else if($error && $adHocCard) {
			$error = "We are sorry, but payment could not be made with this card: $error";
		}
		else {
			$okToNameAmount = 1;
			$finalMessage = "Thank you for your payment!";
			require_once "request-fns.php";
			$request = array();
			$client = getOneClientsDetails($clientid);
			$request['clientptr'] = $clientid;
			$request['subject'] = "Client Payment from: ".$client['clientname'];
			$request['requesttype'] = 'CCPayment';
			$request['note'] = "Client {$client['clientname']} has made a credit card payment"
													.($okToNameAmount ? " of ".dollarAmount($totalAmount) : '')
													.".";
	//echo print_r(upcomingHolidays(),1).'<p>'.$msg;exit;		
			//global $db; echo date('m/d/Y H:i:s')." (local time) Generated Holiday Request for $db\n";
			saveNewClientRequest($request, $notify=true);
		}
	}
}

if(in_array($_POST['action'], array('paywithcardonfile', 'adhocpay'))) {
}


if(TRUE) { // mattOnlyTEST() $finalMessage && dbTEST('tonkapetsitters')...$_SERVER['REMOTE_ADDR'] == '68.225.89.173'
	// redirect for safety
	if($finalMessage) globalRedirect("client-cc-pay.php?success=1");
}


$suppressMenu =  true;
include "frame-client.html";


if($error) echo "<span class='warning fontSize1_3em'>$error</span><img src='art/spacer.gif' width:1 height:300>";
else if($finalMessage) echo "<span class='fontSize1_3em'>$finalMessage</span><img src='art/spacer.gif' width:1 height:300>";
if($error || $finalMessage) {
	include "frame-end.html";
	exit;
}

?>
<style>
.h3 {font-size: 1.15em; font-weight:bold;}
.bigLabel {font-size: 1.0em;font-weight:bold;}
.bigInput {font-size: 1.5em;font-weight:bold;width:120px;}
.gratuityInput {font-size: 1.2em;font-weight:bold;width:120px;}
</style>
<div style='font-size:1.2em;'>
<?
foreach(getOneClientsDetails($clientid) as $k=>$v) $client[$k] = $v;
?>
<form name='ccpayment' method='POST'>
<input type='hidden' name='action' id='action' value=''>
<table width=100% border=1>
<tr><td> <!-- CARD CHOICE -->
<?

$cc = getCCData();

if($cc['descr']) {
	echo "<div style='background:#FFFACD;height:65px;padding:5px;margin:5px;'>";
	$expired = $cc['expires'] && strtotime($cc['expires']) <= strtotime('now');
	if(!$expired) echo "<span class='h3'>Pay with Your Credit Card</span>:<br> ";
	else echo "<h3>Your credit card on record has expired: </h3>";
	echo " {$cc['descr']} ";
	if(!$expired) {
		echo "<div style='float:right;'>";
		echoButton('onfilepay', "Pay", 'payWithCardOnFile()');
		echo "</div>";
	}
	echo "</div>";
}
else echo "<h3>You have no credit card on record with us.</h3>";

if(TRUE /* strpos($gatewayName, 'Authorize.net') === 0 || strpos($gatewayName, 'SAGE') === 0 || mattOnlyTEST() || dbTEST('tonkapetsitters') */) {
	

	if($cc['descr']) echo "<p class=center>or</p>";
	echo "<div style='background:#FFFACD;padding:5px;margin:5px;>";
	$formDisplay = 'inline';
	
	// Strategy: set a non SESSION-dependent one-use token for this form,
	// When an effort is made to make payment (Non NMI only?), deny if:
	//  a. param 'clientCCPayClickToken' is not null
	//  b  and if a token  with value is not found.
	// Denied or not, consume the token
	// QUESTIONS: Non-NMI only?  Who to test this on (an Authorize.net biz)?
	/*
	require_once "response-token-fns.php";
	$clickToken = generateToken($clientid, 'tblclient', $_SESSION["bizptr"], 'none', $userid, $appendToken=false, $expires=null, $useonce=1);
	hiddenElement('clientCCPayClickToken', $clickToken);
	
	elsewhere...
	
	if($_REQUEST['clientCCPayClickToken']) {
		require_once "response-token-fns.php";
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		include "common/init_db_common.php";
		$found = findTokenRow($token);
		consumeTokenRow($token);
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		if(!$found) ...
	}
	
	*/	
	
	
	if(gatewayIsNMI($gatewayName)) { //$gatewayName == 'Solveras'
		$what = $cc['descr'] ? 'another' : 'a';
		echo "<span class='h3'><b>Pay With $what Credit Card:</b></span>";
		echo "<div style='float:right;'>";
		echoButton('adhocpay', "Pay", 'adHocPay()');
		echo "</div>";
	}
	else {
		require_once "cc-processing-fns.php";
				$ccAcceptedList = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'ccGateway' LIMIT 1");

		$gatewayObject = getGatewayObject($gatewayName);
		$gatewayValidationTests = $gatewayObject->ccValidationTests();
		$gatewayValidationExtraArgs = $gatewayObject->ccValidationExtraArgs();
		
		
		echo "<span class='h3'><b>Pay With This Credit Card:</b></span>";
		echo "<div style='float:right;'>";
		echoButton('adhocpay', "Pay", 'adHocPay()');
		echo "</div>";

		echo "<br>";
		//echoButton('replaceCCButton', 'Pay With This Credit Card:', 'replaceCC()');

		echo " ";
		//echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
		echo '<p>';


		echo "<table style='display:$formDisplay' id='ccformtable'>";
		$redstar = "<font color='red'>* </font>";
		inputRow($redstar.'Card Number:', 'x_card_num', $x_card_num, null, 'emailInput', null, null, 'warnIfCCFormatInvalid(this)');

		//inputRow($redstar.'Company: <span class="tiplooks">Visa, Mastercard, etc.</span>', 'company', $cc['company'], null, 'emailInput');

		$ccAcceptedList = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'ccAcceptedList' LIMIT 1");
		selectRow($redstar.'Company:',
				'company', $company, getAcceptedCreditCardTypeOptions('--Select Card Type--', $ccAcceptedList));

		$months = explodePairsLine('--| ||01|1||02|2||03|3||04|4||05|5||06|6||07|7||08|8||09|9||10|10||11|11||12|12');
		$months['--'] = '';
		$years = array('--'=>'');
		for($i=date('Y');$i<date('Y')+13;$i++) $years[$i] = $i;
		$expirationEls = selectElement('', 'expmonth', $expmonth, $months, null, null, null, true).' '.
											selectElement('', 'expyear', $expyear, $years, null, null, null, true);
		labelRow($redstar.'Expiration', '', $expirationEls, null, null, null,  null, $rawValue=true);
		inputRow($redstar.'Card Verification Number:', 'x_card_code');

		echo "<tr><td>&nbsp;</td></tr>\n";
		echo "<tr><td style='font-size:1.2em;'>Billing Information</td><td>";
		echoButton('', 'Use My Home Address', 'useHomeAddress()');
		echo "</td></tr>\n";


		inputRow($redstar.'Name on Card (First):', 'x_first_name', ($x_first_name ? $x_first_name : $client['fname']), null, 'emailInput');
		inputRow($redstar.'Name on Card (Last):', 'x_last_name', ($x_last_name ? $x_last_name : $client['lname']), null, 'emailInput');
		inputRow('Company (optional):', 'x_company', $x_company, null, 'emailInput');
		inputRow($redstar.'Address:', 'x_address', $x_address, null, 'emailInput');
		$onBlur= function_exists('dumpZipLookupJS') ? "lookUpZip(this.value, \"x_\")" : '';
		//$allowAutoCompleteOnce = true;
		inputRow($redstar.'City:', 'x_city', $x_city, null, 'emailInput');
		inputRow($redstar.'State:', 'x_state', $x_state, null, 'emailInput');
		inputRow($redstar.'ZIP:', 'x_zip', $x_zip, null, 'emailInput', null,  null, $onBlur);
		$countries = explodePairsLine('AU|Australia||US|USA||CA|Canada||NZ|New Zealand||UK|United Kingdom');
		$thisCountry = $countries[getI18Property('country')] ? $countries[getI18Property('country')] : 'USA';

		inputRow($redstar.'Country:', 'x_country', ($x_country ? $x_country : $thisCountry), null, 'emailInput');
		inputRow($redstar.'Phone:', 'x_phone', $x_phone, null, 'emailInput');
		labelRow('<font color="red">* = required fields.</font>', '','');

		hiddenElement('h_address', $client['street1'].($client['street2'] ? " {$client['street2']}" : ''));
		hiddenElement('h_city', $client['city']);
		hiddenElement('h_state', $client['state']);
		hiddenElement('h_zip', $client['zip']);
		hiddenElement('h_phone', primaryPhoneNumber($client));
		hiddenElement('ccAction', '');
		//echo "</div>";
		hiddenElement('rcip', $_REQUEST['rcip']);
		echo "</table>";
	}
	echo "</div>";
} // END Authorize.net only
?>
</td>  <!-- END CARD CHOICE -->

<!-- PAYMENT INFO -->
<td style='vertical-align:top;border-left: solid darkgrey 1px;padding:5px;width:45%;'> <!-- PAYMENT INFO -->
<table width=100%>
<?
$currencyMark = getCurrencyMark();
labelRow('Account:', '', $client['clientname'], null, 'fontSize1_2em');
echo "<tr><td style='padding-top:10px;'>&nbsp</td><td width=100%></td><tr>";
labelRow('Description:', 'note', $note);
echo "<tr><td colspan=2 style='padding-top:5px;'>&nbsp</td><tr>";
$formattedAmount = number_format($amount, 2);
$formattedAmountNoComma = number_format($amount, 2, '.', '');
if($GRATUITYONLY) {
	echo "<tr><td style='font-weight:bold;text-align:center;' colspan=2>Thank you for letting us care for your pets.</td></tr>";
	hiddenElement('amount', 0);
}
else {
	labelRow("Amount Due:", '', "$currencyMark $formattedAmount", '', 'boldfont', null, null, 'raw');
	labelRow(" ", '', "");
	currencyRow("Pay:", 'amount', $formattedAmountNoComma, 'bigLabel', 'bigInput', $rowId=null,  $rowStyle=null, 
							$onBlur='calcTotal()', null, "<span style='font-size:1.5em'>".getCurrencyMark()."</span>");
}
$suppressPayNowGratuity = fetchPreference('suppressPayNowGratuity');
$sitterOptions = $suppressPayNowGratuity && !$GRATUITYONLY ? array() : recentSitterOptions($clientid, $_REQUEST['sitters']);
if(count($sitterOptions) > 1) {
	if($GRATUITYONLY) {
		echo "<tr><td>&nbsp;</td></tr>";
		labelRow('To leave a Gratuity:', '', '', 'bigLabel');		
	}
	else labelRow('+ Gratuity:', '', '', 'bigLabel');
	$prependClass = $labelClass ? "class='$labelClass'" : '';
	$inputCellPrepend = "<span $prependClass>$currencyMark </span>";
	for($i=1; $i <= count($sitterOptions)-1; $i++) 
		echo "<tr><td>"
			.labeledSelect('', "sitter_$i", $_REQUEST["sitter_$i"], $sitterOptions, $labelClass=null, $inputClass='inline', $onChange='toggleGratuities()', $noEcho=true)
			."</td><td>"
			.labeledInput($currencyMark, "gratuity_$i", $_REQUEST["gratuity_$i"], $labelClass='bigLabel', $inputClass='gratuityInput', $onBlur='calcTotal()', $maxlength=null, $noEcho=true)
			."</td></tr>\n";
	$formattedTotal = number_format($amount+$gratuity, 2);
	labelRow("Pay Total:", 'total', "$currencyMark ".($formattedTotal), 'bigLabel', 'bigInput', $rowId=null,  $rowStyle=null, $rawValue=true);
}

?>
</table>
</td><!-- END PAYMENT INFO -->
</table>
</form>
</div>
<?
$loginForm = <<<LOGIN
<span class='fontSize1_2em'>
<form method=POST action='$THIS_SCRIPT_NAME'>
<table width=300>
<tr><td>Username: </td><td><input id='loginid' class='emailInput'></td></tr>
<tr><td>Password: </td><td><input id='password' type='password' class='emailInput'></td></tr>
<tr><td><input type='button' value='Proceed' class='Button' onClick=\'login()\'></td></tr>
</table>
</form>
</span>
LOGIN;
$loginForm = str_replace("\r", "", str_replace("\n", "", $loginForm));
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
setPrettynames('amount','Pay amount',
								'gratuity_1','a gratuity amount','gratuity_2','a gratuity amount','gratuity_3','a gratuity amount',
								'sitter_1','a sitter name','sitter_2','a sitter name','sitter_3','a sitter name'
								);
setPrettynames(
				'x_card_num', 'Credit Card Number', 'x_card_code', 'Credit Card Verification Number',
				'x_exp_date','Expiration','expmonth','Expiration Month', 'expyear', 'Expiration Year', 'company', 'Credit Card Company',
				'x_first_name','First Name','x_last_name','Last Name', 'x_address', 'Address', 'x_city', 'City',
				'x_state', 'State', 'x_zip', 'ZIP', 'x_country', 'Country', 'x_phone', 'Phone');
								
function adHocPay() {	
<? if(gatewayIsNMI($gatewayName)) { /* $gatewayName == 'Solveras' */ ?>
	// enter adhoc cc validation tests here
	var paySlipTests = paySlipValidationTests();
	var tests = [];
	for(var i=0; i < paySlipTests.length; i++)
		tests[tests.length] = paySlipTests[i];
	if(!MM_validateFormArgs(tests)) return;
	disablePayButtons();
	var amt = document.getElementById('amount').value;
	var note = '<?= urlencode($note) ?>';
	var grats = [];
	for(var i=1; i<= 3; i++) {
		var tip, sel = document.getElementById('sitter_'+i);
		if(sel && sel.selectedIndex && (tip = document.getElementById('gratuity_'+i).value))
			grats[grats.length] = "sitter_"+sel.options[sel.selectedIndex].value+"="+tip;
	}
	var args = 'action=adhocpay&rcip=<?= $_REQUEST['rcip'] ?>&amount='+amt+'&note='+note+'&'+grats.join('&');
	document.location.href='solveras-client-cc-pay.php?'+args;
<? }
	 else {
?>
	var guess = guessCreditCardCompany(document.getElementById('x_card_num').value);
	document.getElementById('x_card_num').value = numbersOnly(document.getElementById('x_card_num').value);
	var ccCompanyTest = guess == elementValue(document.getElementById('company')) ? '' : 'The wrong credit card company is selected';
	<?= $gatewayValidationTests ?>

	var tests = [
		'x_card_num', '', 'R',
		'x_card_num', '', 'validCC',
		//'x_card_code', '', 'R',
		'x_card_code', '3', 'MINLENGTH',
		'x_card_code', '4', 'MAXLENGTH',
		'x_exp_date', '', 'R',
		'expmonth', '', 'R',
		'expyear', '', 'R',
		'company', '', 'R',
		ccCompanyTest, '', 'MESSAGE',
		'x_first_name', '', 'R',
		'x_last_name', '', 'R',
		'x_address', '', 'R',
		'x_city', '', 'R',
		'x_state', '', 'R',
		'x_zip', '', 'R',
		'x_country', '', 'R',
		'x_phone', '', 'R'<?= $gatewayValidationExtraArgs ?>
		];

	// enter adhoc cc validation tests here
	var paySlipTests = paySlipValidationTests();
	for(var i=0; i < paySlipTests.length; i++)
		tests[tests.length] = paySlipTests[i];
	if(!MM_validateFormArgs(tests)) return;
	disablePayButtons();
	document.getElementById('action').value = 'adhocpay';
	document.ccpayment.submit();
<? } ?>
}

function warnIfCCFormatInvalid(el) {
	if(!ccNumLooksValid(el.value)) {
		alert("Credit card number is invalid.");
		return;
	}
}

function ccNumLooksValid(str) {
  var objRegExp;
  str = str.replace(/[ -]+/g, '');
  objRegExp = /^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})$/;
	return objRegExp.test(str) ? str : false;
}


			
function guessCreditCardCompany(str) {
/*
		* Visa:  All Visa card numbers start with a 4. New cards have 16 digits. Old cards have 13.
    * MasterCard:  All MasterCard numbers start with the numbers 51 through 55. All have 16 digits.
    * American Express: ^3[47][0-9]{13}$ American Express card numbers start with 34 or 37 and have 15 digits.
    * Diners Club: ^3(?:0[0-5]|[68][0-9])[0-9]{11}$ Diners Club card numbers begin with 300 through 305, 36 or 38. All have 14 digits. There are Diners Club cards that begin with 5 and have 16 digits. These are a joint venture between Diners Club and MasterCard, and should be processed like a MasterCard.
    * Discover: ^6(?:011|5[0-9]{2})[0-9]{12}$ Discover card numbers begin with 6011 or 65. All have 16 digits.
    * JCB: ^(?:2131|1800|35\d{3})\d{11}$ JCB cards beginning with 2131 or 1800 have 15 digits. JCB cards beginning with 35 have 16 digits. 	
 */
  var objRegExp;
  str = numbersOnly(str);
  
  objRegExp = /^4[0-9]{12}(?:[0-9]{3})?$/;
 	if(objRegExp.test(str)) return 'Visa';
  objRegExp = /^5[1-5][0-9]{14}$/;
 	if(objRegExp.test(str)) return 'MasterCard';
  objRegExp = /^3[47][0-9]{13}$/;
 	if(objRegExp.test(str)) return 'American Express';
  objRegExp = /^3(?:0[0-5]|[68][0-9])[0-9]{11}$/;
 	if(objRegExp.test(str)) return 'Diners Club';
  objRegExp = /^6(?:011|5[0-9]{2})[0-9]{12}$/;
 	if(objRegExp.test(str)) return 'Discover';
  objRegExp = /^(?:2131|1800|35\d{3})\d{11}$/;
 	if(objRegExp.test(str)) return 'JCB';
  return '';
}

function paySlipValidationTests() {
	<? if($GRATUITYONLY) { ?>
	var tests = [];
	<? } else { ?>
	var tests = ['amount', '', 'R','amount', '', 'UNSIGNEDFLOAT','amount', '1', 'MIN'];
	<? } ?>
	var sittersSelected = [], dupSitters=false, sitter;
	for(var i=1;document.getElementById('sitter_'+i);i++) {
		if((sitter = document.getElementById('sitter_'+i).value) > 0) {
			for(var j=0; j < sittersSelected.length; j++)
				if(sittersSelected[j] == sitter)
					dupSitters = true;
			sittersSelected[sittersSelected.length] = sitter;
			tests[tests.length] = "gratuity_"+i;
			tests[tests.length] = 'sitter_'+i;
			tests[tests.length] = "RIFF";
			tests[tests.length] = "gratuity_"+i;
			tests[tests.length] = "";
			tests[tests.length] = "UNSIGNEDFLOAT";
			tests[tests.length] = "gratuity_"+i;
			tests[tests.length] = "1";
			tests[tests.length] = "MIN";
		}
	}
	if(dupSitters) {
		tests[tests.length] = "a sitter many not be chosen more than once for a gratuity.";
		tests[tests.length] = "";
		tests[tests.length] = "MESSAGE";		
	}
	return tests;
}

function disablePayButtons() {
	$('#onfilepay').attr('disabled', true);  // use prop if a switch is made to jquery 1.1.* (light box did not work there 10/3/2013)
	$('#adhocpay').attr('disabled', true);
}

function enablePayButtons() {
	$('#onfilepay').attr('disabled', false);
	$('#adhocpay').attr('disabled', false);
}

function payWithCardOnFile() {
	if(!MM_validateFormArgs(paySlipValidationTests())) return;
	disablePayButtons();
	$.ajax({url:" <?= $THIS_SCRIPT_NAME ?>?checklogin=1&rcip=<?= $_REQUEST['rcip'] ?>",
					success: function(data) {
							var result = data.split(':');
							//alert(data);
							if(result[1] == '0' || result[1] == '') {
								if(confirm('You must first provide a username and password. Continue?'))
									return login('start');
								else {
									alert('All right. No payment has been made.');
									enablePayButtons();
									return;
								}
							}
							else attemptPaymentWithCardOnFile();
						}
					}
		);
}

function login(mode) {
	disablePayButtons();
	if(mode == 'start')
		$.fn.colorbox({html:"<?= $loginForm ?>", width:"400", height:"300", scrolling: true, opacity: "0.3"});
	else {
		var loginid = escape(document.getElementById('loginid').value);
		var password = escape(document.getElementById('password').value);
		$.fn.colorbox.close();
		$.ajax({url:"<?= $THIS_SCRIPT_NAME ?>?login=1&rcip=<?= $_REQUEST['rcip'] ?>&loginid="+loginid+'&password='+password,
						success: function(data) {
								var result = data.split(':');
								if(result[1] == '0' || result[1] == '') {
									if(confirm('That did not check out. Try again?'))
										return login('start');
									else {
										alert('All right. No payment has been made.');
										enablePayButtons();
										return;
									}
								}
								else attemptPaymentWithCardOnFile();
							}
						}
			);
	}
	
}

function attemptPaymentWithCardOnFile() {
	//alert('Ok! logged in!');
	//return;

	document.getElementById('action').value = 'paywithcardonfile';
	document.ccpayment.submit();
}

function calcTotal() {
	var currencyMark = '<?= $currencyMark ?>';
	//alert(parseFloat($('#amount')[0].value)+parseFloat($('#gratuity')[0].value));
	
	var total = <?= $GRATUITYONLY ? "0;" : "Math.round((numVal('amount'))*100)/100;" ?>
	for(var i=1;document.getElementById('gratuity_'+i);i++) {
		total = Math.round((total + Math.round((numVal('gratuity_'+i))*100)/100)*100)/100;
	}
	
	$('#total').html(currencyMark+' '+formatMoney(total));
}

function formatMoney(n){
		var  
    c = isNaN(c = Math.abs(c)) ? 2 : c, 
    d = d == undefined ? "." : d, 
    t = t == undefined ? "," : t, 
    s = n < 0 ? "-" : "", 
    i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + "", 
    j = (j = i.length) > 3 ? j % 3 : 0;
   return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
 };

function numVal(id) {
	var val = $('#'+id)[0].value;
	return !val || isNaN(val) ? 0 : parseFloat(val);
}

function useHomeAddress() {
	var keys = 'address,city,state,zip,phone'.split(',');
	for(var i=0;i<keys.length;i++)
		document.getElementById('x_'+keys[i]).value = document.getElementById('h_'+keys[i]).value;
}

function toggleGratuities() {
	for(var i=1;document.getElementById('gratuity_'+i);i++) {
		var sitter = document.getElementById('sitter_'+i);
		var disabled = sitter.options[sitter.selectedIndex].value == 0;
		gratuity = document.getElementById('gratuity_'+i);
		gratuity.disabled = disabled;
		var emptyMessage = "<= start there";
		if(gratuity.disabled) gratuity.value = emptyMessage;
		else if(gratuity.value == emptyMessage) gratuity.value = "";
	}
}

toggleGratuities();
calcTotal();
</script>
<?
function recentSitterOptions($client, $sitters=null) {
	$sitters = findRecentSitters($client, $sitters);
	$options = array('--Choose a sitter--'=>0);
	foreach($sitters as $k => $v) $options[$k] = $v;
	return $options;
}

function findRecentSitters($client, $sitters=null) {
	$lookback = 90;
	if($sitters && 0+$sitters == $sitters && $sitters < 0) {
		$lookback = 0-$sitters;
		$sitters = null;
	}
	$start = date('Y-m-d', strtotime("- $lookback days"));
	if($sitters) $sitters = array_unique(explode(',',  $sitters));
	else $sitters = array_keys(fetchKeyValuePairs("SELECT providerptr FROM tblappointment WHERE clientptr = $client AND date >= '$start'"));
	if($sitters) $sitters = fetchKeyValuePairs(
		"SELECT CONCAT_WS(' ', fname, lname), providerid, lname, fname 
		FROM tblprovider 
		WHERE providerid IN (".join(',', $sitters).") AND active = 1
		ORDER BY lname, fname");
	require_once "provider-fns.php";
	$finalSitters = array();
	foreach($sitters as $provid => $prov)
		if(!in_array($client, doNotServeClientIds($prov)))
			$finalSitters[$provid] = $prov;
	return $finalSitters;
}

function getCCData() {
	global $clientid;
	$cc = fetchFirstAssoc(
			"SELECT tblcreditcard.company, tblcreditcard.last4, tblcreditcard.x_exp_date, tblcreditcard.autopay, tblcreditcardinfo.* 
				FROM tblcreditcard 
				LEFT JOIN tblcreditcardinfo ON ccptr = ccid
				WHERE clientptr = $clientid AND active=1 AND primarypaysource = 1");
	return $cc 
			? array('descr'=>"{$cc['company']} ************{$cc['last4']} Expires: ".shortExpirationDate($cc['x_exp_date']),
							'expires'=>$cc['x_exp_date'], 'autopay'=>$cc['autopay'])
			: array("No active credit card on record.");
}
include "frame-end.html";