<? // ach-editDEV.php
/* Params
client - id of client: edit current cc
ach (optional): edit cc for client

Allow only one active cc per client.

10/3/2012 - THIS SCRIPT IS USED ONLY AS A GATEWAY TO solveras-ach-edit.php.  We support ACH only through Solveras
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "cc-processing-fns.php";
require_once "zip-lookup.php";

$backlinkScript = "ach-editDEV.php";

$auth = merchantInfoSupplied();
//	'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");

if(!$auth) {
	echo "Gateway info not fully supplied.";
	exit;
}

if($auth['ccGateway'] == 'Solveras') {
	include "solveras-ach-edit.php";
	exit;
}

else if(!achEnabled()) {
	echo "ACH not supported for {$auth['ccGateway']} gateway.";
	exit;
}


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
$loginNeeded = is_array($expiration = expireCCAuthorization());//ccLogin($password);
if($loginNeeded) {
	$args = array();
	if($_REQUEST['client']) $args[] = "client={$_REQUEST['client']}";
	if($_REQUEST['cc']) $args[] = "cc={$_REQUEST['cc']}"; // ??
	$backlink = "$backlinkScript?".join('&', $args);
	include "cc-login.php";
	exit;
}

extract($_REQUEST);
$error = null;
if($_POST) {
	// MODES: drop, replace, new, modify
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') print_r(join(',', array_keys($_POST)));	
	$postedACH = $_POST;
	if($action == 'drop') dropACH($acctid);  // window will close
	else if($action == 'replace') {
		dropACH($acctid);
		header("Location: ".globalURL("$backlinkScript?client=$clientptr&updateopener=1"));
		exit;
	}
	else { // new or modify
		//if($acctid) deleteTable('tblecheckacctinfo', "acctptr = $acctid", 1);

		if(!$error) {
//print_r($_POST);			
			
			if($acctid) {
				saveACH($_POST);
				//saveACHInfo($_POST);
			}
			else {
				$_POST['acctid'] = saveNewACH($_POST);
				$_POST['x_state'] = strtoupper((string)$_POST['x_state']);  // TransactionExpress does not like "Pa" or "tx"
				$_POST['x_zip'] = join(explode('-', $_POST['x_zip']));
				
				/*if(!$_POST['useclientinfo'])*/ saveACHInfo($_POST);
			}
		}
	}
	if(!$error) {
		$safeACH = getClearACH($_POST['clientptr']);
		if($safeACH) $safeACH = join('|', $safeACH);
		echo "<script language='javascript'>window.close();window.opener.update('ach', '$safeACH');</script>";
		exit;
	}
}

$clientDetails = getOneClientsDetails($client, array('addressparts', 'lname', 'fname', 'phone'));
$windowTitle = "{$clientDetails['clientname']}'s E-checking (ACH) Info";;
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";
echo "<!-- DEV -->";

if($error) {
	echo "<font color='red'>$error</font>";
}
if($postedACH) $achToShow = $postedACH;
else {
	$ach = getClearACH($client, $primaryToo=true);
	if($ach && primaryPaySourceProblem($ach)) 
		echo "<font color=red>This ACH Info is not valid for your gateway {$_SESSION['preferences']['ccGateway']}</font><p>";

	if($ach) $achToShow = array_merge($ach,
									(array)fetchFirstAssoc($sql = "SELECT * FROM tblecheckacctinfo WHERE acctptr = {$ach['acctid']} LIMIT 1"));
}
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
	inputRow($redstar.'Account Customer Name:', 'acctname', '', null, 'emailInput');
	inputRow($redstar.'Account Number: ', 'acctnum', $achToShow['acctnum'], null, 'emailInput');
	//inputRow($redstar.'Bank Name:', 'acctname', '', null, 'emailInput');
	inputRow($redstar.'Bank Routing Number:', 'abacode', '', null, 'emailInput');
	selectRow('Account Type:', 'accttype', $value=null, array(''=>'', 'checking'=>'checking', 'savings'=>'savings'));
	selectRow('Bank Customer Type:', 'acctentitytype', $value=null, array(''=>'', 'personal'=>'personal', 'business'=>'business'));
	echo "<tr>\n";
	checkboxRow('AutoPay authorized:', 'fax', ($achToShow ? $achToShow['autopay'] : true));

	echo "<tr><td style='font-size:1.5em;font-weight:bold;text-align:center;padding-top:15px;'>Billing Information</td>";
	echo "<td style='padding-top:15px;padding-left:5px;'>";
	labeledCheckbox('Use customer info', 'useclientinfo', $achToShow['useclientinfo'], null, null, 'useHomeAddressSolveras(this)', $boxFirst=false);
	echo "</td></tr>\n";
	inputRow('Company (optional):', 'x_company', $achToShow['x_company'], null, 'emailInput');
	inputRow($redstar.'Address:', 'x_address', $achToShow['x_address'], null, 'emailInput');
	$onBlur= function_exists('dumpZipLookupJS') ? "lookUpZip(this.value, \"x_\")" : '';
	//$allowAutoCompleteOnce = true;
	inputRow($redstar.'ZIP:', 'x_zip', $achToShow['x_zip'], null, 'emailInput', null,  null, $onBlur);
	inputRow($redstar.'City:', 'x_city', $achToShow['x_city'], null, 'emailInput');
	inputRow($redstar.'State:', 'x_state', $achToShow['x_state'], null, 'emailInput');
	inputRow($redstar.'Country:', 'x_country', ($achToShow['x_country'] ? $achToShow['x_country'] : 'USA'), null, 'emailInput');
	inputRow($redstar.'Phone:', 'x_phone', $achToShow['x_phone'], null, 'emailInput');
	labelRow('<font color="red">* = required fields.</font>', '','');
}
else {
	hiddenElement('acctid', $achToShow['acctid']);
	hiddenElement('last4', $achToShow['last4']);
	labelRow('Account Name:', '', $achToShow['acctname']);
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
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>
<?= $updateopener ? "if(window.opener) {window.opener.update('achinfo', '');window.close();} else document.write('Changes saved.');" : '' ?>


// ********************************************
function saveACH() {
  if(document.getElementById('acctname') && MM_validateFormArgs(achFormArgsToTest())) {
		document.getElementById('action').value='create';
		document.acheditor.submit();
	}
	else if(document.getElementById('acctname')) return;
	else document.acheditor.submit(); // update autopay only
}

// ===============================================================================
setPrettynames(
				'acctnum', 'Account Number', 'acctname', 'Account Name',
				'abacode', 'Bank Routing Number');
setPrettynames('x_address', 'Address', 'x_city', 'City',
									'x_state', 'State', 'x_zip', 'ZIP', 'x_country', 'Country', 'x_phone', 'Phone');


function achFormArgsToTest() {
	var args;
	var acctnummessage = '';
	var val = document.getElementById('acctnum').value;
	if(!isUnsignedInt(val) || val.length < 4 || val.length > 17)
		acctnummessage = prettyName('acctnum')+' must be a number between 4 and 17 digits long';
	
	var abacodemessage = '';
	val = document.getElementById('abacode').value;
	if(!isUnsignedInt(val) || val.length != 9)
		abacodemessage = prettyName('abacode')+' must be a number exactly 9 digits long';
	else { // yes it is 9 digits, but are the first two digits right?
<? if(TRUE || mattOnlyTEST()) { // test with 13-19 as a prefix ?>	
	  var abaRegExp = /^((0[0-9])|(1[0-2])|(2[1-9])|(3[0-2])|(6[1-9])|(7[0-2])|80)([0-9]{7})$/;
		if(!abaRegExp.test(val)) abacodemessage = prettyName('abacode')+' must be a valid bank routing number';
<? } ?>
	}
		
/*
<? // http://regexlib.com/REDetails.aspx?regexp_id=2057 ?>

function validRoutingNumber($pat) {
	$objRegExp = '/^((0[0-9])|(1[0-2])|(2[1-9])|(3[0-2])|(6[1-9])|(7[0-2])|80)([0-9]{7})$/';
	return preg_match($objRegExp, $pat);
}



^((0[0-9])|(1[0-2])|(2[1-9])|(3[0-2])|(6[1-9])|(7[0-2])|80)([0-9]{7})$
*/
	
	args = [
		'acctname', '', 'R',
		'acctname', '', 'NONEMPTY',
		'acctname', '', 'ALPHASPACESONLY',
		'acctnum', '', 'R',
		acctnummessage, '', 'MESSAGE',
		'abacode', '', 'R',
		abacodemessage, '', 'MESSAGE'
		];
		
	// TXP constraints:
	// name alpha only no punct or num
	// acctnum numeric 10-17 digits -- industry standard
	// abacode numeric 9 digits -- industry standard
		
	var phonemessage = null;
	val = document.getElementById('x_phone').value;
	if(!isDashed10DigitPhoneNumber(val)) phonemessage = "Phone number must be in the form XXX-XXX-XXXX.";

	var statemessage = null;
	val = document.getElementById('x_state').value;
	if(!alphaOnly(val) || val.length != 2) statemessage = "State must be a two-letter abbreviation.";
	
	val = document.getElementById('x_zip').value;
	var zipcodemessage = ZIPCodeError(val);
	
	extraArgs = [
			'x_address', '', 'R',
			'x_city', '', 'R',
			'x_state', '', 'R',
			statemessage, '', 'MESSAGE',
			'x_zip', '', 'R',
			zipcodemessage, '', 'MESSAGE',
			'x_country', '', 'R',
			'x_phone', '', 'R',
			phonemessage, '', 'MESSAGE'
			];
	for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];


	return args;
}

function isDashed10DigitPhoneNumber(val) {
	var regex = /^([0-9]{3}-)[0-9]{3}-[0-9]{4}$/
	return regex.test(val);
}

function ZIPCodeError(val) {
	var zipregex = /^[0-9]{5}$/
	var zipPlus4regex = /^([0-9]{5}-)[0-9]{4}$/
	return zipregex.test(val) || zipPlus4regex.test(val) ? '' : 'ZIP Code must be in the form XXXXX or XXXXX-XXXX';
}
	


function dropAccount(replace) {
	if(!confirm('Are you sure you want to drop this account'+(replace ? ' and replace it?' : '?')))
		return;
	document.getElementById('action').value = (replace ? 'replace' : 'drop');
	document.acheditor.action = '<?= $backlinkScript ?>'; // will be set differently for Solveras
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

// ********************************************



function useHomeAddress() {
	var src = makeArray('h_address', 'h_city', 'h_state', 'h_zip', 'h_phone');
	var dest = makeArray('x_address', 'x_city', 'x_state', 'x_zip', 'x_phone');
	for(var i=0;i<dest.length;i++)
		if(document.getElementById(dest[i]))
			document.getElementById(dest[i]).value = document.getElementById(src[i]).value;
}

useHomeAddress();
<?
dumpZipLookupJS();
?>
</script>
