<? // solveras-ach-edit.php
/* Params
client - id of client: edit current cc
ach (optional): edit cc for client

Allow only one active ach per client.
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
if($auth['ccGateway'] != 'Solveras') {
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
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($_POST);	exit;}
	$action = $_POST['action'];
	if($action == 'drop') dropACH($_POST['acctid']);  // window will close
	else if($action == 'replace') {
		dropACH($_POST['acctid']);
		header("Location: ".globalURL("ach-edit.php?client={$_POST['clientptr']}&updateopener=1"));
		exit;
	}
	else {
		updateTable('tblecheckacct', array('autopay'=>($_POST['autopay'] ? 1 : '0')), "acctid = {$_POST['acctid']}", 1);
	}
	if(!$error) {
		$safeACH = getClearACH($_POST['clientptr']);
		$safeACH = "{$safeACH['bank']}|{$safeACH['acctnum']}|{$safeACH['accttype']}|{$safeACH['autopay']}";
		echo "<script language='javascript'>window.close();window.opener.update('achinfo', '$safeACH');</script>";
		exit;
	}
}
else if($safeACH = getClearACH($_REQUEST['client'])) {  // may be overridden in POST below
	$safeACH = "{$safeACH['bank']}|{$safeACH['acctnum']}|{$safeACH['accttype']}|{$safeACH['autopay']}";
}




if($_SESSION['solveras_error'])
	echo "<font color=red>{$_SESSION['solveras_error']}</font><p>";
$_SESSION['solveras_error'] = '';



$loginNeeded = is_array($expiration = expireCCAuthorization());//ccLogin($password);
if($loginNeeded) {
	$args = array();
	if($_REQUEST['client']) $args[] = "client={$_REQUEST['client']}";
	if($_REQUEST['ach']) $args[] = "ach={$_REQUEST['ach']}";
	$backlink = "ach-edit.php?".join('&', $args);
	include "cc-login.php";
	exit;
}

extract($_REQUEST);
$error = null;

$clientDetails = getOneClientsDetails($client, array('addressparts', 'lname', 'fname', 'phone'));

$achToShow = ($ach = getActiveClientACH($client));

if(!$achToShow['acctid']) {
	$gateWayObject = getGatewayObject('Solveras');
	$formURL = $gateWayObject->startAuthRequest($auth, $clientDetails, $op = "mgrach_{$clientDetails['clientid']}");
	
	if(is_array($formURL)) {
		echo "<font color='red'>{$formURL[0]}: {$formURL[1]}<p>";
	}
	else $formAction = "action='$formURL'";
}
//if(mattOnlyTEST()) $formAction = "action='testmonths.php'";

$windowTitle = "{$clientDetails['clientname']}'s E-checking (ACH) Info";;
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";
if($error) {
	echo "<font color='red'>$error</font>";
}

// 'acctid,x_card_num,last4,x_exp_date,company,active,clientptr');

echo "<form method='POST' name='acheditor' $formAction>";
echo "<table>";
echo "<tr><td>";
echoButton('', 'Save', 'saveACH()');
echo "</td><td style='text-align:right;'>";
if($achToShow['acctid']) {
	echoButton('', 'Drop this Account', 'dropAccount(0)');
	echo " ";
	echoButton('', 'Replace This Account', 'dropAccount(1)');
}
echo "</td><tr>";
hiddenElement('action', '');
hiddenElement('clientptr', $client); // if(!$safeACH) 
//hiddenElement('active', $ach['active']);
$redstar = "<font color='red'>* </font>";
if(!$ach) {
	//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
//hiddenElement('billing-cc-number', '');
//hiddenElement('billing-cc-exp', '');
	
	hiddenElement('acctid', '');
	inputRow($redstar.'Account Name:', 'billing-account-name', '', null, 'emailInput');
	inputRow($redstar.'Account Number: ', 'billing-account-number', $achToShow['acctnum'], null, 'emailInput');
	//inputRow($redstar.'Bank Name:', 'billing-account-name', '', null, 'emailInput');
	inputRow($redstar.'Bank Routing Number:', 'billing-routing-number', '', null, 'emailInput');
	selectRow('Account Type:', 'billing-account-type', $value=null, array(''=>'', 'checking'=>'checking', 'savings'=>'savings'));
	selectRow('Bank Customer Type:', 'billing-entity-type', $value=null, array(''=>'', 'personal'=>'personal', 'business'=>'business'));
	echo "<tr>\n";
	checkboxRow('AutoPay authorized:', 'fax', ($achToShow ? $achToShow['autopay'] : true));

	//  <td><label for="x_exp_date">Expiration (MM/YY):</label></td><td><input class="standardInput" id="x_exp_date" name="x_exp_date" value="02/09"></td></tr>
	echo "<tr><td style='font-size:1.5em;font-weight:bold;text-align:center;padding-top:15px;'>Billing Information</td>";
	echo "<td style='padding-top:15px;padding-left:5px;'>";
	labeledCheckbox('Use customer info', 'useclientinfo', $achToShow['useclientinfo'], null, null, 'useHomeAddressSolveras(this)', $boxFirst=false);
	echo "</td></tr>\n";
	//inputRow($redstar.'Name on Card (First):', 'billing-first-name', $achToShow['x_first_name'], null, 'emailInput');
	//inputRow($redstar.'Name on Card (Last):', 'billing-last-name', $achToShow['x_last_name'], null, 'emailInput');
	inputRow('Company (optional):', 'x_company', $achToShow['billing-company'], null, 'emailInput');
	inputRow($redstar.'Address:', 'billing-address1', $achToShow['x_address'], null, 'emailInput');
	$onBlur= function_exists('dumpZipLookupJS') ? "lookUpZip(this.value, \"x_\")" : '';
	//$allowAutoCompleteOnce = true;
	inputRow($redstar.'ZIP:', 'billing-postal', $achToShow['x_zip'], null, 'emailInput', null,  null, $onBlur);
	inputRow($redstar.'City:', 'billing-city', $achToShow['x_city'], null, 'emailInput');
	inputRow($redstar.'State:', 'billing-state', $achToShow['x_state'], null, 'emailInput');
	inputRow($redstar.'Country:', 'billing-country', ($achToShow['x_country'] ? $achToShow['x_country'] : 'USA'), null, 'emailInput');
	inputRow($redstar.'Phone:', 'billing-phone', $achToShow['x_phone'], null, 'emailInput');
	labelRow('<font color="red">* = required fields.</font>', '','');
}
else {
	hiddenElement('acctid', $achToShow['acctid']);
	hiddenElement('last4', $achToShow['last4']);
	labelRow('Account Name:', '', $achToShow['acctname']);
	if($achToShow['vaultid']) labelRow('Vault ID:', '', $achToShow['vaultid']);
	labelRow('Account Number:', '', $achToShow['acctnum']);
	labelRow('Account Type:', '', $achToShow['accttype']);
	labelRow('Bank Routing Number:', '', $achToShow['abacode']);;
	labelRow('Bank Customer Type:', '', $achToShow['acctentitytype']);;
	checkboxRow('AutoPay authorized:', 'autopay', ($achToShow ? $achToShow['autopay'] : true));
	echo "<tr><td style='font-size:1.5em;font-weight:bold;text-align:center;padding-top:15px;'>Billing Information</td>";
	labelRow('Address:', '', "{$achToShow['x_address']}");
	labelRow('', '', "{$achToShow['x_city']}, {$achToShow['x_state']} {$achToShow['x_zip']}");
	labelRow('Phone:', '', "{$achToShow['x_phone']}");
}
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
<?= $updateopener ? "window.opener.update('ach', '$safeACH');" : '' ?>

setPrettynames('company','Company','x_card_num','Card Number', 'expmonth', 'Expiration Month', 'expyear', 'Expiration Year','x_card_code','Card Verification Number');

function useHomeAddressSolveras() {
	var src = makeArray('h_address', 'h_city', 'h_state', 'h_zip', 'h_phone');
	var dest = makeArray('billing-address1', 'billing-city', 'billing-state', 'billing-postal', 'billing-phone');
	for(var i=0;i<dest.length;i++)
		if(document.getElementById(dest[i]))
			document.getElementById(dest[i]).value = document.getElementById(src[i]).value;
}

// THESE FUNCTIONS SUPERSEDE THEIR ALREADY-DEFINED COUNTERPARTS IN client-own-account.php
function saveACH() {
  if(document.getElementById('billing-account-name') && MM_validateFormArgs(achFormArgsToTest())) {
		document.getElementById('action').value='create';
		document.acheditor.submit();
	}
	else document.acheditor.submit(); // update autopay only
}

// ===============================================================================
function achFormArgsToTest() {
	var args;
	args = [
		'billing-account-name', '', 'R',
		'billing-account-number', '', 'R',
		'billing-routing-number', '', 'R'
		];

	setPrettynames(
					'billing-account-number', 'Account Number', 'billing-account-name', 'Account Name',
					'billing-routing-number', 'Bank Routing Number');
		  

	extraArgs = [
			'billing-address1', '', 'R',
			'billing-city', '', 'R',
			'billing-state', '', 'R',
			'billing-postal', '', 'R',
			'billing-country', '', 'R',
			'billing-phone', '', 'R'
			];
	for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];

	setPrettynames('billing-address1', 'Address', 'billing-city', 'City',
										'billing-state', 'State', 'billing-postal', 'ZIP', 'billing-country', 'Country', 'billing-phone', 'Phone');
	return args;
}


function dropAccount(replace) {
	if(!confirm('Are you sure you want to drop this account'+(replace ? ' and replace it?' : '?')))
		return;
	document.getElementById('action').value = (replace ? 'replace' : 'drop');
	document.acheditor.action = 'solveras-ach-edit.php'; // will be set differently for Solveras
	document.acheditor.submit();

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

useHomeAddressSolveras();
<?
//echo "BANG!";exit;

dumpZipLookupJS();
?>
</script>
