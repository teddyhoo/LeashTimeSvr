<? // solveras-client-cc-pay.php
/* require a userid and a clientid to determine identity of the client.
args:
rcip - client userid|clientid
amount - edited already by client
note - (optional) description field
sitters - (optional) csv of sitter id's.  if not supplied, sitters for last 90 days will appear in gratuity menu

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

//print_r($_REQUEST);
require_once "common/init_session.php";

$startingURL = $_SESSION['client-cc-pay-url'];
$startingSolverasURL = $_SESSION['solveras-client-cc-pay-url'];

if($solverasCallback) { // included by solveras-callback.php
//echo "START: $startingURL  SOLV: $startingSolverasURL<hr>";
	handleSolverasCallback($op, $auth, $tokenId);
}

else if($_REQUEST['rcip']) {  // STEP 1: OPEN CARD ENTRY FORM
	//preserve solveras error
	$oldSolverasError = $_SESSION['solveras_error'];
	session_unset();
	session_destroy();
	unset($_SESSION);
	bootUpSession();
	if($oldSolverasError) $_SESSION['solveras_error'] = $oldSolverasError;
	$_SESSION['client-cc-pay-url'] = $startingURL;
	$_SESSION['solveras-client-cc-pay-url'] = $_SERVER["REQUEST_URI"];
	list($userid, $clientid) = explode('|', $_REQUEST['rcip']);
	// validate the user/client
	require_once "common/init_db_common.php";
	$bizid = fetchRow0Col0("SELECT bizptr FROM tbluser WHERE userid = $userid LIMIT 1", 1);
	$_SESSION["bizptr"]	= $bizid;
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1", 1);
	$_SESSION['db'] = $db = $biz['db'];
	$_SESSION['dbuser'] = $dbuser = $biz['dbuser'];
	$_SESSION['dbhost'] = $dbhost = $biz['dbhost'];
	$_SESSION['dbpass'] = $dbpass = $biz['dbpass'];
	reconnectPetBizDB($db, $dbhost, $dbuser, $dbpass, $force=true);
	$clientuserid = fetchRow0Col0("SELECT userid FROM tblclient WHERE clientid = $clientid LIMIT 1", 1);
	if($clientuserid != $userid) {
		echo "Bad client credentials.";
		exit;
	}
	$_SESSION['clientid'] = $clientid;
	$_SESSION['rights'] = 'T-'; // T == TEMPCLIENT
	$_SESSION['temp_auth_user_id'] = $userid;
	$_SESSION['auth_user_id'] = 99; // to allow "locked()" to work far enough to check rights
	$_SESSION['amount'] = $_REQUEST['amount'];
	$_SESSION['total'] = $_REQUEST['amount'];
	$_SESSION['preferences']  = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
	$_SESSION["uidirectory"] = "bizfiles/biz_$bizid/clientui/";
	if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$bizid/";
	$suppressMenu = 1;
	foreach($_REQUEST as $k => $v) {
		if(strpos($k, 'sitter_') === 0) {
			$_SESSION['tips'][substr($k, strlen('sitter_'))] = $v;
			$_SESSION['totalTips'] += $v;
			$_SESSION['total'] += $v;
		}
	}
	// now build the solveras credit card entry form
	require_once "js-gui-fns.php";
	require_once "preference-fns.php";
	require_once "client-fns.php";
	require_once "cc-processing-fns.php";
	
	$auth = merchantInfoSupplied();
	if($auth['ccGateway'] == 'Solveras') require_once "solveras-gateway-class.php";
	else if($auth['ccGateway'] = 'TransFirstV1') require_once "transfirst-nmi-txp-gateway-class.php";
	else {
		echo "ERROR: Wrong Gateway";
		exit;
	}
	if(!$auth) {
		echo "ERROR: Incomplete merchant authorization.";
		exit;
	}
	$gateWayObject = getGatewayObject($auth['ccGateway']);

	$client = array_merge(getClient($clientid), getOneClientsDetails($clientid));

	$formURL = $gateWayObject->startClientPayRequest($auth, $client, $op = "clientpayment", $_SESSION['total']); // was clientcc

	if(is_array($formURL)) {
		echo "<font color='red'>{$formURL[0]}: {$formURL[1]}<p>";
	}

	$lockChecked =  true; // splashblock not called by frame-client.html ?!?!
	include "frame-client.html";

//echo "START: $startingURL  SOLV: $startingSolverasURL<hr>";
	
	if($_SESSION['solveras_error'])
		echo "<font color='red'>{$_SESSION['solveras_error']}</font><p>";
	$_SESSION['solveras_error'] = '';
?>
To make the following payment, please use the form below.  This credit card information will <u>not</u> be saved in your profile.<p>
<style>
.paymentsummary {border:solid lightblue 4px; font-size:1.1em;position:relative;left:50px;}
.paymentsummary th {text-align:right;font-weight:normal;padding-left:20px;}
.paymentsummary td {text-align:right;font-weight:bold;padding-right:20px;}
.bigger {font-size:1.2em;}
.highlight {background:yellow;}
</style>
<table class='paymentsummary'>
<tr><td colspan=2>Pay to: <?= $_SESSION['preferences']['bizName'] ?></td></tr>
<tr><th>Payment:</td><td><?= dollarAmount($_SESSION['amount']) ?></td></tr>
<? if(!$_SESSION['preferences']['suppressPayNowGratuity']) { ?>
<tr><th>+ gratuities:</td><td><?= $_SESSION['totalTips'] ? dollarAmount($_SESSION['totalTips']) : '--'?></td></tr>
<? } ?>
<tr><td class='bigger'>Total:</td><td class='bigger highlight'><?= dollarAmount($_SESSION['total']) ?></td></tr>
<tr><td colspan=2 style='font-size:0.8em;text-align:center'>
<? echoButton('', 'Revise Amounts', "document.location.href=\"$startingURL\""); ?></td></tr>
</table>
<p>
<?

	$allCards = getAllCardTypes();
	$cards = explode(',', getPreference('ccAcceptedList'));
	foreach($allCards as $card)
		if(in_array($card['label'], $cards))
			$cardimgs[] = "<img src='art/{$card['img']}'>";
	if($cardimgs)
		echo " We accept ".join(' ', $cardimgs);

	echo "<p>";
	$formDisplay = !$cc['expires'] || $expired ? 'inline' : 'none';
	if($cc['expires']) {
		echoButton('', 'Drop Current Credit Card', 'dropCC()');
		echo " ";
		echoButton('replaceCCButton', 'Replace Current Credit Card', 'replaceCC()');
	}
	else echoButton('makepaymentbutton', 'Make Payment', 'makePayment()');
	echo " ";
	//$action = 'parent.$.fn.colorbox.close();';
	$action = "document.location.href=\"$startingURL\"";
	echoButton('', "Quit", $action);
	echo '<p>';

	echo "\n<form method='POST' name='cceditor' action='$formURL'>
	<table style='display:$formDisplay' id='ccformtable'>";


	$redstar = "<font color='red'>* </font>";
	inputRow($redstar.'Card Number:', 'billing-cc-number', null, null, 'emailInput', null, null, 'warnIfCCFormatInvalid(this)');

	//inputRow($redstar.'Company: <span class="tiplooks">Visa, Mastercard, etc.</span>', 'company', $cc['company'], null, 'emailInput');

	selectRow($redstar.'Company:',
			'company', $cc['company'], getAcceptedCreditCardTypeOptions('--Select Card Type--'));

	$expirationParts = $cc['billing-cc-exp'] ? explode('/', expirationDate($cc['x_exp_date'])) : array(null, null);
	$months = explodePairsLine('--| ||01|1||02|2||03|3||04|4||05|5||06|6||07|7||08|8||09|9||10|10||11|11||12|12');
	$months['--'] = '';
	$years = array('--'=>'');
	for($i=date('Y');$i<date('Y')+13;$i++) $years[$i] = $i;
	$expirationEls = selectElement('', 'expmonth', $expirationParts[0], $months, null, null, null, true).' '.
										selectElement('', 'expyear', $expirationParts[1], $years, null, null, null, true);
	labelRow($redstar.'Expiration', '', $expirationEls, null, null, null,  null, $rawValue=true);
	hiddenElement('billing-cc-exp', '');
	inputRow($redstar.'Card Verification Number:', 'billing-cvv');

	echo "<tr><td>&nbsp;</td></tr>\n";
	echo "<tr><td style='font-size:1.2em;'>Billing Information</td><td>";
	echoButton('', 'Use My Home Address', 'useHomeAddressSolveras()');
	echo "</td></tr>\n";

	inputRow($redstar.'Name on Card (First):', 'billing-first-name', null, null, 'emailInput');//$client['fname']
	inputRow($redstar.'Name on Card (Last):', 'billing-last-name', null, null, 'emailInput'); //$client['lname']
	//inputRow('Company (optional):', 'x_company', '', null, 'emailInput');
	inputRow($redstar.'Address:', 'billing-address1',null , null, 'emailInput'); // $cc['x_address']
	$onBlur= function_exists('dumpZipLookupJS') ? "lookUpZip(this.value, \"x_\")" : '';
	//$allowAutoCompleteOnce = true;
	inputRow($redstar.'ZIP:', 'billing-postal', null, null, 'emailInput', null,  null, $onBlur); //$cc['x_zip']
	inputRow($redstar.'City:', 'billing-city', null, null, 'emailInput'); // $cc['x_city']
	inputRow($redstar.'State:', 'billing-state', null, null, 'emailInput'); // $cc['x_state']
	inputRow($redstar.'Country:', 'billing-country', 'USA', null, 'emailInput'); // ($cc['x_country'] ? $cc['x_country'] : 'USA')
	inputRow($redstar.'Phone:', 'billing-phone', null, null, 'emailInput'); // $cc['x_phone']
	labelRow('<font color="red">* = required fields.</font>', '','');

	hiddenElement('h_address', $client['street1'].($client['street2'] ? " {$client['street2']}" : ''));
	hiddenElement('h_city', $client['city']);
	hiddenElement('h_state', $client['state']);
	hiddenElement('h_zip', $client['zip']);
	hiddenElement('h_phone', primaryPhoneNumber($client));
	hiddenElement('action', '');
	echo "</table></form></div>";
	//echo "</div>";

}	

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

function disablePayButtons() {
	$('#makepaymentbutton').attr('disabled', true);  // use prop if a switch is made to jquery 1.1.* (light box did not work there 10/3/2013)
}

function enablePayButtons() {
	$('#makepaymentbutton').attr('disabled', false);
}



function makePayment() {
  if(MM_validateFormArgs(ccFormArgsToTest())) {
		disablePayButtons();
		document.getElementById('billing-cc-exp').value = ''+document.getElementById('expmonth').value+'/'+document.getElementById('expyear').value;
		document.cceditor.submit();
	}
}

function useHomeAddressSolveras() {
	var src = makeArray('h_address', 'h_city', 'h_state', 'h_zip', 'h_phone');
	var dest = makeArray('billing-address1', 'billing-city', 'billing-state', 'billing-postal', 'billing-phone');
	for(var i=0;i<dest.length;i++)
		document.getElementById(dest[i]).value = document.getElementById(src[i]).value;
}

function ccFormArgsToTest() {
	var args;
	var guess = guessCreditCardCompany(document.getElementById('billing-cc-number').value);
	document.getElementById('billing-cc-number').value = numbersOnly(document.getElementById('billing-cc-number').value);
	var ccCompanyTest = guess == elementValue(document.getElementById('company')) ? '' : 'The wrong credit card company is selected';
	if(true/*!document.getElementById('ccid').value*/)
	  args = [
		  'billing-cc-number', '', 'R',
		  'billing-cc-number', '', 'validCC',
		  //'x_card_code', '', 'R',
		  'billing-cvv', '3', 'MINLENGTH',
		  'billing-cvv', '4', 'MAXLENGTH'
		  ];
	else args = [];
	var extraArgs = [
		  'expmonth', '', 'R',
		  'expyear', '', 'R',
		  'company', '', 'R',
		  ccCompanyTest, '', 'MESSAGE'];
	setPrettynames(
					'billing-cc-number', 'Credit Card Number', 'billing-cvv', 'Credit Card Verification Number',
					'expmonth','Expiration Month', 'expyear', 'Expiration Year', 'company', 'Credit Card Company');
		  
	for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];
	if(true /*!document.getElementById('useclientinfo').checked*/) {
		setPrettynames('billing-first-name','First Name','billing-last-name','Last Name', 'billing-address1', 'Address', 'billing-city', 'City',
										'billing-state', 'State', 'billing-postal', 'ZIP', 'billing-country', 'Country', 'billing-phone', 'Phone');

		extraArgs = [
				'billing-first-name', '', 'R',
				'billing-last-name', '', 'R',
				'billing-address1', '', 'R',
				'billing-city', '', 'R',
				'billing-state', '', 'R',
				'billing-postal', '', 'R',
				'billing-country', '', 'R',
				'billing-phone', '', 'R'
				];
		for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];
	}
	
	
	
	return args;
/*
5491132285827692
*/
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
</script>
<?
}
require "frame-end.html";

function handleSolverasCallback($op, $auth, $tokenId) {
	global $startingURL, $startingSolverasURL;
	$clientid = $_SESSION['clientid'];
	$_SESSION['auth_user_id'] = $_SESSION['temp_auth_user_id'];
	$_SESSION['rights'] = 'c-temp';  // solveras-callback checked lock for "T" set up for a client

	$responseArray = getResponseArray($auth, $tokenId);
	$merchantInfo = merchantInfoSupplied();
	if(!$responseArray) $_SESSION['solveras_error'] = "Error: No response from Solveras: ".date('Y-m-d H:i:s');
	else if($responseArray['result'] == 2) { // Declined - use result-text
		$_SESSION['solveras_error'] = "Declined: ".$responseArray['result-text'];
	}
	else if($responseArray['result'] == 3) { // Error - use result-text
		$_SESSION['solveras_error'] = "Error: ".$responseArray['result-text'];
	}
	else {
		// PAYMENT HAS BEEN MADE
		/*
		RESP: Array
		(
		    [result] => 1
		    [result-text] => Approved
		    [result-code] => 100
		    [action-type] => sale
		    [first-name] => Edward
		    [last-name] => Hooban
		    [address1] => 2124 Robin Way Ct
		    [city] => Vienna
		    [state] => VA
		    [postal] => 22182
		    [country] => US
		    [phone] => 703-242-1964
		    [email] => matt@leashtime.com
		    [company] => Visa
		    [cc-number] => 474166******4169
		    [cc-exp] => 0915
		)

		*/
		// ##############################################################################
		$fieldsRaw = explode(',', 'x_card_num,cc-number,company,company,x_first_name,first-name,x_last_name,last-name,x_address,address1,x_city,city,x_state,state,x_zip,postal,x_country,country,x_phone,phone');
		for($i=0; $i < count($fieldsRaw); $i+=2) $fields[$fieldsRaw[$i]] = $fieldsRaw[$i+1];
		$expiration = '20'.substr($responseArray['cc-exp'], 2).'-'.substr($responseArray['cc-exp'], 0, 2).'-'.'01';
		
		
		$adHocCard = array(
			'clientptr'=>$clientid, 
			'x_exp_date'=>$expiration, 
			'adhoc'=>1, 
			'gateway'=>$merchantInfo['ccGateway'],
			);
		foreach($fields as $fld => $adHocFld) 
			if($responseArray[$adHocFld]) $adHocCard[$fld] = $responseArray[$adHocFld];
		$adHocCard['ccid'] = saveNewAdHocCC($adHocCard); // saves only: ccid,last4,x_exp_date,company,clientptr,created,modified,createdby,modifiedby,gateway
		$last4 = fetchRow0Col0("SELECT last4 FROM tblcreditcardadhoc WHERE ccid = {$adHocCard['ccid']} LIMIT 1"); // to get company, last4
		$adHocCard['last4'] = $v;
	}
	if(!$adHocCard) $adHocCard = array('clientptr'=>$clientid, 'adhoc'=>1, 'ccid'=>-99, 'gateway'=>$merchantInfo['ccGateway']); // for gateway error reporting
	global $gatewayObject;
	$gatewayObject = getGatewayObject($merchantInfo['ccGateway']);
//echo print_r($responseArray, 1).'<hr>'.print_r($adHocCard, 1).'<hr>';
	$success = $gatewayObject->distillXmlResponseAndLogCCTransactionError($responseArray['responseXml'], $adHocCard);
	require_once "credit-fns.php";
	$transactionid = applyAndRegisterEPaymentAttempt($success, $clientid, $adHocCard, $_SESSION['total'], 'Client payment with ad-hoc card', $sendReceipt=null, $dontApplyPayment=false, $_SESSION['totalTips'], $noLoginPayment=true);

	global $latestPaymentId;
	$newPaymentId = $latestPaymentId; //$latestPaymentId is globally set in applyEPayment (from applyAndRegisterEPaymentAttempt)
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
	if($newPaymentId && $_SESSION['tips'])  {
		require_once "gratuity-fns.php";
		foreach($_SESSION['tips'] as $providerptr => $gratuityAmount) {
			$gratuity = array('paymentptr'=>$newPaymentId, 'tipnote'=>$tipNoteIfAny, 'clientptr'=>$clientid,
											'issuedate'=>date('Y-m-d H:i:s'), 'amount'=>$gratuityAmount, 'providerptr'=>$providerptr);
			insertTable('tblgratuity', $gratuity, 1);
			makeGratuityNoticeMemo($gratuity);

		}
	}
// **************************************************	
//echo "ERROR: $error<hr>START: $startingSolverasURL<hr>card: ".print_r($adHocCard, 1);exit;	
	$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('bizHomePage', 'bizName')", 1);
	if($error && $adHocCard) {
		if($startingSolverasURL) {
			$_SESSION['solveras_error'] = $error;
			globalRedirect($startingSolverasURL); // this will reset the session
		}
		else {
			echo "No Starting page found!";
			session_unset();
			session_destroy();
			unset($_SESSION);
		}
		exit;
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
		$request['note'] = "Client {$client['clientname']} has made a credit card payment (transaction: $transactionid)"
												.($okToNameAmount ? " of ".dollarAmount($_SESSION['total']) : '')
												.".";
//echo print_r(upcomingHolidays(),1).'<p>'.$msg;exit;		
		//global $db; echo date('m/d/Y H:i:s')." (local time) Generated Holiday Request for $db\n";
		saveNewClientRequest($request, $notify=true);
		
		$suppressMenu = 1;
		$lockChecked =  true; // splashblock not called by frame-client.html ?!?!
		$thankYou = "<div class='fontSize1_1em'>Thank you for your payment.<p>Return to <a href='{$prefs['bizHomePage']}'>{$prefs['bizName']}</a></div>";
		
		if(TRUE) { // mattOnlyTEST() may not be available...
			// redirect for safety
			if($finalMessage) globalRedirect("client-cc-pay.php?success=1");
		}
		
		include "frame-client.html";
		echo $thankYou;
		require "frame-end.html";
	}
	
	session_unset();
	session_destroy();
	unset($_SESSION);
		
}

exit;
