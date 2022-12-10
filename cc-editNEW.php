<? // cc-editNEW.php
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

$auth = merchantInfoSupplied();
$failure = false;
if(!$auth) {
	$failure = "Merchant information not found.";
}
else {
//	'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");
	if(gatewayIsNMI($auth['ccGateway'])) { //$auth['ccGateway'] == 'Solveras'
		include "solveras-cc-edit.php";
		exit;
	}

	$auxiliaryWindow = true; // prevent login from appearing here if session times out

	// Verify login information here
	if(userRole() != 'd') locked('o-');
	if(!adequateRights('*cm')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
		$failure = "Insufficient Access Rights";
	}
}
if($failure) {
	$windowTitle = $failure;
	require "frame-bannerless.php";	
	echo "<h2>$windowTitle</h2>";
	exit;
}
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
if($_POST) {
	// MODES: drop, replace, new, modify
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') print_r(join(',', array_keys($_POST)));	
	$postcard = $_POST;
	$hideCCNumber = $_POST['ccid'] ? true : false;
	if($action == 'drop') dropCC($ccid);  // window will close
	else if($action == 'replace') {
		dropCC($ccid);
		header("Location: ".globalURL("cc-edit.php?client=$clientptr&updateopener=1"));
		exit;
	}
	else { // new or modify
		$_POST['x_exp_date'] = $_POST['expmonth'].'/1/'.$_POST['expyear'];
		if($ccid) deleteTable('tblcreditcardinfo', "ccptr = $ccid", 1);
if(FALSE && staffOnlyTEST()) {
		$ccToCheck = $_POST;
		if($ccid) { // if card already exists, retrieve the card num to authenticate
			$tempcc = getActiveClientCC($clientptr);
			$ccToCheck['x_card_num'] = $tempcc['x_card_num'];
		}
		$authorization = authorizeCC($ccToCheck);
		if(is_array($authorization)) {
			/* global $gatewayObject; */
			$cause = isset($authorization['FAILURE']) ? $authorization['FAILURE'] : $gatewayObject->ccLastMessage($authorization);
			$error =  "Authorization failed: ".$cause;
			$postcard = $ccToCheck;
		}
}
		if(!$error) {
			$data = $_POST;
			$data['gateway'] = $_SESSION['preferences']['ccGateway'];
			if($data['x_state']) $data['x_state'] = strtoupper($data['x_state']); // for TransactionExpress
			if($ccid) saveCC($data);
			else {
				$_POST['ccid'] = saveNewCC($data);
				$data['ccid'] = $_POST['ccid'];
			}
			if(!$_POST['useclientinfo']) saveCCInfo($data);
		}
	}
	if(!$error) {
		$safeCC = getClearCC($_POST['clientptr']);
		$safeCC['x_exp_date'] = shortExpirationDate($safeCC['x_exp_date']);
		$safeCC = join('|', $safeCC);
		echo "<script language='javascript'>window.close();window.opener.update('creditcard', '$safeCC');</script>";
		exit;
	}
}

$clientDetails = getOneClientsDetails($client, array('addressparts', 'lname', 'fname', 'phone'));
$windowTitle = "{$clientDetails['clientname']}'s Credit Card";;
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";
if($error) {
	echo "<font color='red'>$error</font>";
}
if($postcard) $cardToShow = $postcard;
else $cardToShow = ($cc = getActiveClientCC($client));
// 'ccid,x_card_num,last4,x_exp_date,company,active,clientptr');
echo "<form method='POST' name='cceditor'>";
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
	inputRow($redstar.'Card Number:', 'x_card_num', $cardToShow['x_card_num'], null, 'emailInput', null, null, 'checkCC(this)');
}
else {
	hiddenElement('ccid', $cardToShow['ccid']);
	hiddenElement('last4', $cardToShow['last4']);
	labelRow('Card Number:', '', '************'.$cardToShow['last4']);
}
inputRow($redstar.'Company: <span class="tiplooks">Visa, Mastercard, etc.</span>', 'company', $cardToShow['company'], null, 'emailInput');
//inputRow('Expiration (MM/YY):', 'x_exp_date', expirationDate($cc['x_exp_date']));
$expirationParts = $cc['x_exp_date'] 
										? explode('/', expirationDate($cardToShow['x_exp_date'])) 
										: ($postcard ? array($postcard['expmonth'], $postcard['expyear']) : array(null, null));
$months = explodePairsLine('--| ||01|1||02|2||03|3||04|4||05|5||06|6||07|7||08|8||09|9||10|10||11|11||12|12');
$months['--'] = '';
$years = array('--'=>'');
for($i=date('Y');$i<date('Y')+13;$i++) $years[$i] = $i;
$expirationEls = selectElement('', 'expmonth', $expirationParts[0], $months, null, null, null, true).' '.
									selectElement('', 'expyear', $expirationParts[1], $years, null, null, null, true);
labelRow($redstar.'Expiration', '', $expirationEls, null, null, null,  null, $rawValue=true);
if(!$cc && !$hideCCNumber) inputRow('Card Verification Number:', 'x_card_code', $cardToShow['x_card_code']);
echo "<tr>\n";
checkboxRow('AutoPay authorized:', 'autopay', ($cardToShow ? $cardToShow['autopay'] : true));

//  <td><label for="x_exp_date">Expiration (MM/YY):</label></td><td><input class="standardInput" id="x_exp_date" name="x_exp_date" value="02/09"></td></tr>
echo "<tr><td style='font-size:1.5em;font-weight:bold;text-align:center;padding-top:15px;'>Billing Information</td>";
echo "<td style='padding-top:15px;padding-left:5px;'>";
//labeledCheckbox('Use customer info', 'useclientinfo', $cardToShow['useclientinfo'], null, null, 'useCustInfoClicked(this)', $boxFirst=false);
echoButton('', 'Use customer info', 'fillInCustInfo(this)');
hiddenElement('useclientinfo', '0');
echo "</td></tr>\n";
inputRow($redstar.'Name on Card (First):', 'x_first_name', $cardToShow['x_first_name'], null, 'emailInput');
inputRow($redstar.'Name on Card (Last):', 'x_last_name', $cardToShow['x_last_name'], null, 'emailInput');
inputRow('Company (optional):', 'x_company', $cardToShow['x_company'], null, 'emailInput');
inputRow($redstar.'Address:', 'x_address', $cardToShow['x_address'], null, 'emailInput');
$onBlur= function_exists('dumpZipLookupJS') ? "lookUpZip(this.value, \"x_\")" : '';
//$allowAutoCompleteOnce = true;
inputRow($redstar.'ZIP:', 'x_zip', $cardToShow['x_zip'], null, 'emailInput', null,  null, $onBlur);
inputRow($redstar.'City:', 'x_city', $cardToShow['x_city'], null, 'emailInput');
inputRow($redstar.'State:', 'x_state', $cardToShow['x_state'], null, 'emailInput');
inputRow($redstar.'Country:', 'x_country', ($cardToShow['x_country'] ? $cardToShow['x_country'] : 'USA'), null, 'emailInput');
inputRow($redstar.'Phone:', 'x_phone', $cardToShow['x_phone'], null, 'emailInput');
labelRow('<font color="red">* = required fields.</font>', '','');

echo "</table>";
echo "</form>";

foreach(explode(',', 'lname,fname,zip,city,state') as $fieldName) {
	echo "\n";
	hiddenElement($fieldName, $clientDetails[$fieldName]);
}
foreach(explode(',', 'homephone,cellphone,workphone,cellphone2') as $fieldName) {
	if($clientDetails[$fieldName]) {
		echo "\n";
		$phone = $clientDetails[$fieldName];
		if(substr($phone, 0, 1) == '*') $phone = substr($phone, 1);
		hiddenElement('phone', $phone);
		break;
	}
}
$address = $clientDetails['street1'] ? $clientDetails['street1'] : '';
if($clientDetails['street2']) 
	$address = $address ? "$address ".$clientDetails['street2'] : $clientDetails['street2'];
echo "\n";
hiddenElement('address', $address);
echo "\n";

$gatewayObject = getGatewayObject($_SESSION['preferences']['ccGateway']);
$gatewayValidationTests = $gatewayObject->ccValidationTests();
$gatewayValidationExtraArgs = $gatewayObject->ccValidationExtraArgs();

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>
<?= $updateopener ? "window.opener.update('creditcard', '');" : '' ?>

setPrettynames('company','Company','x_card_num','Card Number', 'expmonth', 'Expiration Month', 'expyear', 'Expiration Year','x_card_code','Card Verification Number');

function saveCC() {
	var args;
	<?= $gatewayValidationTests ?>
	if(!document.getElementById('ccid').value)
	  args = [
		  'x_card_num', '', 'R',
		  'x_card_num', '', 'validCC'

		  //'x_card_code', '', 'R',
		  //'x_card_code', '3', 'MINLENGTH',
		  //'x_card_code', '4', 'MAXLENGTH'
		  ];
	else args = [];
	var extraArgs = [
		  'x_exp_date', '', 'R',
		  'expmonth', '', 'R',
		  'expyear', '', 'R',
		  'company', '', 'R'<?= $gatewayValidationExtraArgs ?>]
	for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];
	if(!document.getElementById('useclientinfo').checked) {
		setPrettynames('x_first_name','First Name','x_last_name','Last Name', 'x_address', 'Address', 'x_city', 'City',
										'x_state', 'State', 'x_zip', 'ZIP', 'x_country', 'Country', 'x_phone', 'Phone');

		extraArgs = [
				'x_first_name', '', 'R',
				'x_last_name', '', 'R',
				'x_address', '', 'R',
				'x_city', '', 'R',
				'x_state', '', 'R',
				'x_zip', '', 'R',
				'x_country', '', 'R',
				'x_phone', '', 'R'
				];
		for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];
	}
/*
5491132285827692
*/
  if(MM_validateFormArgs(args)) {
		document.cceditor.submit();
	}
}

function dropCard(replace) {
	if(!confirm('Are you sure you want to drop this card'+(replace ? ' and replace it?' : '?')))
		return;
	document.getElementById('action').value = (replace ? 'replace' : 'drop');
	document.cceditor.submit();

}


// takes the form field value and returns true on valid number
function valid_credit_card(value) {
	// accept only digits, dashes or spaces
	if (/[^0-9-\s]+/.test(value)) return false;

	// The Luhn Algorithm. It's so pretty.
	var nCheck = 0, nDigit = 0, bEven = false;
	value = value.replace(/\D/g, "");

	for (var n = value.length - 1; n >= 0; n--) {
	var cDigit = value.charAt(n),
	nDigit = parseInt(cDigit, 10);

	if (bEven) {
	if ((nDigit *= 2) > 9) nDigit -= 9;
	}

	nCheck += nDigit;
	bEven = !bEven;
	}

	return (nCheck % 10) == 0 ? value : false;
} 

function checkCC(el) {
	var ccnum = valid_credit_card(el.value); //ccNumLooksValid(el.value);
	if(!ccnum) ccnum = ccNumLooksValid(el.value);  // wtf do I know?
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

function fillInCustInfo(el) {
	var clientFields = 'fname,lname,nuthin,address,city,state,zip,country,phone'.split(',');
	var relevantFields = "x_first_name,x_last_name,x_company,x_address,x_city,x_state,x_zip,x_country,x_phone".split(',');
	for(var i=0;i< relevantFields.length;i++) {
		if(document.getElementById(clientFields[i]))
			document.getElementById(relevantFields[i]).value = document.getElementById(clientFields[i]).value;
	}
}

/*
function useCustInfoClicked(el) {
	var clientFields = 'fname,lname,nuthin,address,city,state,zip,country,phone'.split(',');
	var relevantFields = "x_first_name,x_last_name,x_company,x_address,x_city,x_state,x_zip,x_country,x_phone".split(',');
	for(var i=0;i< relevantFields.length;i++) {
		document.getElementById(relevantFields[i]).disabled=el.checked;
		if(el.checked && document.getElementById(clientFields[i]))
			document.getElementById(relevantFields[i]).value = document.getElementById(clientFields[i]).value;
	}
}

useCustInfoClicked(document.getElementById('useclientinfo'));
*/
<?
dumpZipLookupJS();
?>
</script>
