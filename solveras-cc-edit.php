<? // solveras-cc-edit.php
/* Params
client - id of client: edit current cc
cc (optional): edit cc for client

Allow only one active cc per client.
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "cc-processing-fns.php";
require_once "zip-lookup.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out



// Verify login information here
locked('o-');
$failure = false;
if(!adequateRights('*cm')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
	$failure = "Insufficient Access Rights";
}
if($failure) {
	$windowTitle = 'Insufficient Access Rights';
	require "frame-bannerless.php";	
	echo "<h2>$windowTitle</h2>";
	exit;
}

$auth = merchantInfoSupplied();
//	'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");
if(!gatewayIsNMI($auth['ccGateway'])) {
	echo "ERROR: Wrong Gateway";
	exit;
}
if(!$auth) {
	echo "ERROR: Incomplete merchant authorization.";
	exit;
}

if($_POST) {
	// MODES: drop, replace, new, modify
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') print_r(join(',', array_keys($_POST)));	exit;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') print_r($_POST);	exit;
	$action = $_POST['action'];
	if($action == 'drop') dropCC($_POST['ccid']);  // window will close
	else if($action == 'replace') {
		dropCC($_POST['ccid']);
		header("Location: ".globalURL("cc-edit.php?client={$_POST['clientptr']}&updateopener=1"));
		exit;
	}
	else {
		updateTable('tblcreditcard', array('autopay'=>($_POST['autopay'] ? 1 : '0')), "ccid = {$_POST['ccid']}", 1);
	}
	if(!$error) {
		$safeCC = getClearCC($_POST['clientptr']);
		$safeCC['x_exp_date'] = shortExpirationDate($safeCC['x_exp_date']);
		$safeCC = join('|', $safeCC);
		echo "<script language='javascript'>window.close();window.opener.update('creditcard', '$safeCC');</script>";
		exit;
	}
}
else if($safeCC = getClearCC($_REQUEST['client'])) {  // may be overridden in POST below
	$safeCC['x_exp_date'] = shortExpirationDate($safeCC['x_exp_date']);
	$safeCC = join('|', $safeCC);
}




if($_SESSION['solveras_error'])
	echo "<font color=red>{$_SESSION['solveras_error']}</font><p>";
$_SESSION['solveras_error'] = '';



$loginNeeded = is_array($expiration = expireCCAuthorization());//ccLogin($password);
if($loginNeeded) {
	$args = array();
	if($_REQUEST['client']) $args[] = "client={$_REQUEST['client']}";
	if($_REQUEST['cc']) $args[] = "cc={$_REQUEST['cc']}";
	$backlink = "cc-edit.php?".join('&', $args);
	include "cc-login.php";
	exit;
}

extract($_REQUEST);
$error = null;

$clientDetails = getOneClientsDetails($client, array('addressparts', 'lname', 'fname', 'phone'));

$cardToShow = ($cc = getActiveClientCC($client));

if(!$cardToShow['ccid']) {
	$gateWayObject = getGatewayObject($auth['ccGateway']);
	$formURL = $gateWayObject->startAuthRequest($auth, $clientDetails, $op = "mgrcc_{$clientDetails['clientid']}");
	if(is_array($formURL)) {
		echo "<font color='red'>{$formURL[0]}: {$formURL[1]}<p>";
	}
	else $formAction = "action='$formURL'";
}

$windowTitle = "{$clientDetails['clientname']}'s Credit Card";;
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";
if($error) {
	echo "<font color='red'> $error</font>";
}

// 'ccid,x_card_num,last4,x_exp_date,company,active,clientptr');

echo "<form method='POST' name='cceditor' $formAction>";
echo "<table>";
echo "<tr><td>";
echoButton('', 'Save', 'saveCC()');
echo "</td><td style='text-align:right;'>";
if($cardToShow['ccid']) {
	echoButton('', 'Drop this Card', 'dropCard(0)');
	echo " ";
	echoButton('', 'Replace This Card', 'dropCard(1)');
}
echo "</td><tr>";
hiddenElement('action', '');
hiddenElement('clientptr', $client);
hiddenElement('active', $cc['active']);
$redstar = "<font color='red'>* </font>";
if(!$cc && !$hideCCNumber) {
	//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
	hiddenElement('ccid', '');
	inputRow($redstar.'Card Number:', 'billing-cc-number', '', null, 'emailInput', null, null, 'checkCC(this)');
	inputRow($redstar.'Company: <span class="tiplooks">Visa, Mastercard, etc.</span>', 'company', $cardToShow['company'], null, 'emailInput');
	$expirationParts = $cc['billing-cc-exp'] 
											? explode('/', expirationDate($cardToShow['x_exp_date'])) 
											: ($postcard ? array($postcard['expmonth'], $postcard['expyear']) : array(null, null));
	$months = explodePairsLine('--| ||01|1||02|2||03|3||04|4||05|5||06|6||07|7||08|8||09|9||10|10||11|11||12|12');
	$months['--'] = '';
	$years = array('--'=>'');
	for($i=date('Y');$i<date('Y')+13;$i++) $years[$i] = $i;
	$expirationEls = selectElement('', 'expmonth', $expirationParts[0], $months, null, null, null, true).' '.
										selectElement('', 'expyear', $expirationParts[1], $years, null, null, null, true);
	labelRow($redstar.'Expiration', '', $expirationEls, null, null, null,  null, $rawValue=true);
	//inputRow('Expiration (MM/YY):', 'x_exp_date', expirationDate($cc['x_exp_date']));
	hiddenElement('billing-cc-exp', '');
	if(!$cc && !$hideCCNumber) inputRow('Card Verification Number:', 'billing-cvv', null);
	echo "<tr>\n";
	checkboxRow('AutoPay authorized:', 'fax', ($cardToShow ? $cardToShow['autopay'] : true));

	//  <td><label for="x_exp_date">Expiration (MM/YY):</label></td><td><input class="standardInput" id="x_exp_date" name="x_exp_date" value="02/09"></td></tr>
	echo "<tr><td style='font-size:1.5em;font-weight:bold;text-align:center;padding-top:15px;'>Billing Information</td>";
	echo "<td style='padding-top:15px;padding-left:5px;'>";
	labeledCheckbox('Use customer info', 'useclientinfo', $cardToShow['useclientinfo'], null, null, 'useHomeAddressSolveras(this)', $boxFirst=false);
	echo "</td></tr>\n";
	inputRow($redstar.'Name on Card (First):', 'billing-first-name', $cardToShow['x_first_name'], null, 'emailInput');
	inputRow($redstar.'Name on Card (Last):', 'billing-last-name', $cardToShow['x_last_name'], null, 'emailInput');
	inputRow('Company (optional):', 'x_company', $cardToShow['billing-company'], null, 'emailInput');
	inputRow($redstar.'Address:', 'billing-address1', $cardToShow['x_address'], null, 'emailInput');
	$onBlur= "updateBillingPostal(this.value, \"billing-\")";
	//$allowAutoCompleteOnce = true;
	inputRow($redstar.'ZIP:', 'billing-zip', $cardToShow['x_zip'], null, 'emailInput', null,  null, $onBlur);
	inputRow($redstar.'City:', 'billing-city', $cardToShow['x_city'], null, 'emailInput');
	inputRow($redstar.'State:', 'billing-state', $cardToShow['x_state'], null, 'emailInput');
	inputRow($redstar.'Country:', 'billing-country', ($cardToShow['x_country'] ? $cardToShow['x_country'] : 'USA'), null, 'emailInput');
	inputRow($redstar.'Phone:', 'billing-phone', $cardToShow['x_phone'], null, 'emailInput');
	labelRow('<font color="red">* = required fields.</font>', '','');
}
else {
	hiddenElement('ccid', $cardToShow['ccid']);
	hiddenElement('last4', $cardToShow['last4']);
	labelRow('Card Number:', '', '************'.$cardToShow['last4']);
	if($cardToShow['vaultid']) labelRow('Vault ID:', '', $cardToShow['vaultid']);
	labelRow('Company:', '', $cardToShow['company']);
	labelRow('Expiration', '', date('m/Y', strtotime($cardToShow['x_exp_date'])), null, null, null,  null, $rawValue=true);
	checkboxRow('AutoPay authorized:', 'autopay', ($cardToShow ? $cardToShow['autopay'] : true));
	echo "<tr><td style='font-size:1.5em;font-weight:bold;text-align:center;padding-top:15px;'>Billing Information</td>";
	labelRow('Name:', '', "{$cardToShow['x_first_name']} {$cardToShow['x_last_name']}");
	labelRow('Address:', '', "{$cardToShow['x_address']}");
	labelRow('', '', "{$cardToShow['x_city']}, {$cardToShow['x_state']} {$cardToShow['x_zip']}");
	labelRow('Phone:', '', "{$cardToShow['x_phone']}");
}
hiddenElement('billing-postal', $clientDetails['zip']);
hiddenElement('h_address', $clientDetails['street1'].($clientDetails['street2'] ? " {$client['street2']}" : ''));
hiddenElement('h_city', $clientDetails['city']);
hiddenElement('h_state', $clientDetails['state']);
hiddenElement('h_zip', $clientDetails['zip']);
hiddenElement('h_phone', primaryPhoneNumber($clientDetails));

echo "</table>";
echo "</form>";

?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>
<?= $updateopener ? "window.opener.update('creditcard', '$safeCC');" : '' ?>

setPrettynames('company','Company','x_card_num','Card Number', 'expmonth', 'Expiration Month', 'expyear', 'Expiration Year','x_card_code','Card Verification Number');

function updateBillingPostal(zipValue, prefix) {
	document.getElementById('billing-postal').value = zipValue;
	<? if(function_exists('dumpZipLookupJS')) { ?>
	lookUpZip(zipValue, prefix);
	<? } ?>
}

function useHomeAddressSolveras() {
	var src = makeArray('h_address', 'h_city', 'h_state', 'h_zip', 'h_phone');
	var dest = makeArray('billing-address1', 'billing-city', 'billing-state', 'billing-postal', 'billing-phone');
	for(var i=0;i<dest.length;i++)
		if(document.getElementById(dest[i]))
			document.getElementById(dest[i]).value = document.getElementById(src[i]).value;
}

// THESE FUNCTIONS SUPERSEDE THEIR ALREADY-DEFINED COUNTERPARTS IN client-own-account.php
function saveCC() {
  if(document.getElementById('billing-cc-exp') && MM_validateFormArgs(ccFormArgsToTest())) {
		document.getElementById('action').value='create';
		document.getElementById('billing-cc-exp').value = ''+document.getElementById('expmonth').value+'/'+document.getElementById('expyear').value;
		document.cceditor.submit();
	}
	else document.cceditor.submit(); // update autopay only
}

// ===============================================================================

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


function dropCard(replace) {
	if(!confirm('Are you sure you want to drop this card'+(replace ? ' and replace it?' : '?')))
		return;
	document.getElementById('action').value = (replace ? 'replace' : 'drop');
	document.cceditor.setAttribute('action', 'solveras-cc-edit.php');
	// DID NOT WORK IN IE: document.cceditor.action = 'solveras-cc-edit.php'; // will be set differently for Solveras
	document.cceditor.submit();

}

function checkCC(el) {
	var ccnum = ccNumLooksValid(el.value);
	if(!ccnum) {
		alert("Credit card number is invalid.");
		return;
	}
	el.value = ccnum;
	if(document.getElementById('company').value) return;
	var v = el.value.replace(/[^0-9]+/g, '');
	var guess = guessCreditCardCompany(v);
	if(guess && confirm('Is this a '+guess+'?'))
		document.getElementById('company').value = guess;
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

function ccNumLooksValid(str) {
  var objRegExp;
  str = str.replace(/[ -]+/g, '');
	if(str == '3411111111111111') return str;
  objRegExp = /^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})$/;
	return objRegExp.test(str) ? str : false;
}

function supplyLocationInfo(cityState,addressGroupId) {
	var cityState = cityState.split('|');
	if(cityState[0] && cityState[1]) {
		var city = document.getElementById(addressGroupId+'city');
		var state = document.getElementById(addressGroupId+'state');
		var needConfirmation = false;
		needConfirmation = needConfirmation || (city.value.length > 0 && (city.value.toUpperCase() != cityState[0].toUpperCase()));
		needConfirmation = needConfirmation || (state.value.length > 0 && (state.value.toUpperCase() != cityState[1].toUpperCase()));
		if(!needConfirmation || confirm("Overwrite city and state with "+cityState[0]+", "+cityState[1]+"?")) {
		  if(city.value.toUpperCase() != cityState[0].toUpperCase()) city.value = cityState[0];
		  if(state.value.toUpperCase() != cityState[1].toUpperCase()) state.value = cityState[1];
		}
	}
}

function useCustInfoClicked(el) {
	var clientFields = 'fname,lname,nuthin,address,city,state,zip,country,phone'.split(',');
	var relevantFields = "x_first_name,x_last_name,x_company,x_address,x_city,x_state,x_zip,x_country,x_phone".split(',');
	for(var i=0;i< relevantFields.length;i++) {
		document.getElementById(relevantFields[i]).disabled=el.checked;
		if(el.checked && document.getElementById(clientFields[i]))
			document.getElementById(relevantFields[i]).value = document.getElementById(clientFields[i]).value;
	}
}

useHomeAddressSolveras();
//useCustInfoClicked(document.getElementById('useclientinfo'));
<?
//echo "BANG!";exit;

dumpZipLookupJS();
?>
</script>
