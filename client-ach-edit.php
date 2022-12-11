<? // client-ach-edit.php


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "preference-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";

$locked = locked('c-');
extract($_REQUEST);
?>
<div style='font-size:1.2em;'>
<?
if(!getPreference('offerClientUIAccountPage')) {
	echo "Your rights to view this page are insufficient.  <a href='index.php'>Home</a>";
	exit;
}

$auth = merchantInfoSupplied();
//	'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");
if(!$auth) {
	echo "ERROR: Incomplete merchant authorization.";
	exit;
}
if(!achEnabled()) {
	echo "ACH not supported for {$auth['ccGateway']} gateway.";
	exit;
}

if($_POST) {
	$destinationURL = "client-own-account.php";
	// MODES: drop, replace, new, modify
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') print_r(join(',', array_keys($_POST)));	
	$postedACH = $_POST;
	if($action == 'drop') {
		dropACH($acctid);  // window will close
		$doNotSaveACH =  true;
	}
	else if($action == 'replaceACH') {  // Save New Card has been clicked after "Replace ACH" was clicked.
		dropACH($acctid);
		//header("Location: ".globalURL("$destinationURL?replaceACH=1"));
		//exit;
	}
	if(!$doNotSaveACH) { // new or modify
		if($acctid) deleteTable('tblecheckacctinfo', "acctptr = $acctid", 1);

		if(!$error) {
			$_POST['clientptr'] = $_SESSION['clientid'];
			if($acctid) saveACH($_POST);
			else {
				$_POST['x_state'] = strtoupper((string)$_POST['x_state']);  // TransactionExpress does not like "Pa" or "tx"				
				$_POST['x_zip'] = join(explode('-', $_POST['x_zip']));
				$_POST['acctid'] = saveNewACH($_POST);
				$request= array('resolved' => 0, 'requesttype' => 'ACHSupplied', 'clientptr'=>$_SESSION['clientid'], 'note'=>"E-Checking (ACH) Info supplied");
				require_once "request-fns.php";
				saveNewClientRequest($request, $_SESSION['clientid']);
			}

			saveACHInfo($_POST);
		}
	}
	if(!$error) {
		header("Location: ".globalURL($destinationURL));
		exit;
	}
}




$gateWayObject = getGatewayObject($auth['Solveras']);

$client = array_merge(getClient($_SESSION['clientid']), getOneClientsDetails($_SESSION['clientid']));
$ach = getACHData();
$achToShow = getActiveClientACH($_SESSION['clientid']);
if(!$achToShow)
	echo '<p>Please use the form below to enter E-checking Information.<p>';
else {
	echo "<h3>$ach ";
	if(!$achToShow['primarypaysource']) 
		echoButton('', 'Bill This Account',
					"ajaxGetAndCallWith(\"cc-primary-set.php?id=0&choice=ACH\", refresh, \"primary!\")");
	echo "</h3>";

}


$formDisplay = $achToShow ? 'none' : 'inline';
if($achToShow) {
	echoButton('', 'Drop Current Account', 'dropACH()');
	echo " ";
	echoButton('replaceCCButton', 'Replace Current Account', 'replaceACH()');
}
else echoButton('', 'Save Account', 'createACH()');
echo " ";
echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
echo '<p>';

if($achToShow) {
	$displayACH = getClearACH($_SESSION['clientid']);
	echo "<div style='padding-left:15px'>Current Account:<p>";
	if($_SESSION['preferences']['ccGateway'] != $displayACH['gateway']) 
		echo "<font color=red>This account information must be re-entered before it will work.</font><p>";
	
	echo "Account Customer Name: {$displayACH['acctname']}<br>";
	echo "Account Number: {$displayACH['acctnum']}<br>";
	echo "Bank routing number: {$displayACH['abacode']}<br>";
	if($displayACH['accttype']) echo "Account Type: {$displayACH['accttype']}<br>";
	if($displayACH['acctentitytype']) echo "Bank Customer Type: {$displayACH['acctentitytype']}<br>";
	echo "AutoPay authorized: ".($displayACH['autopay'] ? 'yes' : 'no')."<br>";
	echo "<p>Contact:<p>";
	if($achToShow['Company']) echo "{$achToShow['x_company']}<br>";
	echo "{$achToShow['x_address']}<br>";
	echo "{$achToShow['x_city']}, {$achToShow['x_state']} {$achToShow['x_zip']}<br>";
	echo "{$achToShow['x_country']}<br>";
	echo "Phone: {$achToShow['x_phone']}";
	echo "</div>";
}
echo "<form method='POST' name='acheditor' action='client-ach-edit.php'>
<table style='display:$formDisplay' id='achformtable'>";


$redstar = "<font color='red'>* </font>";
hiddenElement('acctid', '');
inputRow($redstar.'Account Customer Name:', 'acctname', '', null, 'emailInput');
inputRow($redstar.'Account Number: ', 'acctnum', null, null, 'emailInput');
//inputRow($redstar.'Bank Name:', 'billing-account-name', '', null, 'emailInput');
inputRow($redstar.'Bank Routing Number:', 'abacode', '', null, 'emailInput');
selectRow('Account Type:', 'accttype', $value=null, array(''=>'', 'checking'=>'checking', 'savings'=>'savings'));
selectRow('Bank Customer Type:', 'acctentitytype', $value=null, array(''=>'', 'personal'=>'personal', 'business'=>'business'));
echo "<tr>\n";
checkboxRow('I authorize AutoPay:', 'autopay', ($achToShow ? $achToShow['autopay'] : true));

echo "<tr><td>&nbsp;</td></tr>\n";
echo "<tr><td style='font-size:1.2em;'>Billing Information</td><td>";
echoButton('', 'Use My Home Address', 'useHomeAddress()');
echo "</td></tr>\n";

inputRow('Company (optional):', 'x_company', null, null, 'emailInput');
inputRow($redstar.'Address:', 'x_address', null, null, 'emailInput');
$onBlur= function_exists('dumpZipLookupJS') ? "lookUpZip(this.value, \"x_\")" : '';
//$allowAutoCompleteOnce = true;
inputRow($redstar.'City:', 'x_city', null, null, 'emailInput');
inputRow($redstar.'State:', 'x_state', null, null, 'emailInput');
inputRow($redstar.'ZIP:', 'x_zip', null, null, 'emailInput', null,  null, $onBlur);
inputRow($redstar.'Country:', 'x_country', ($achToShow['x_country'] ? $achToShow['x_country'] : 'USA'), null, 'emailInput');
inputRow($redstar.'Phone:', 'x_phone', null, null, 'emailInput');
labelRow('<font color="red">* = required fields.</font>', '','');


hiddenElement('h_address', $client['street1'].($client['street2'] ? " {$client['street2']}" : ''));
hiddenElement('h_city', $client['city']);
hiddenElement('h_state', $client['state']);
hiddenElement('h_zip', $client['zip']);
hiddenElement('h_phone', primaryPhoneNumber($client));
hiddenElement('action', '');
echo "</table></form></div>";
//echo "</div>";

function getACHData() {
	$ach = fetchFirstAssoc(
			"SELECT acctid, tblecheckacct.last4,  primarypaysource, vaultid, tblecheckacctinfo.* 
				FROM tblecheckacct 
				LEFT JOIN tblecheckacctinfo ON acctptr = acctid
				WHERE clientptr = {$_SESSION['clientid']} AND active");
	$style = $ach['primarypaysource'] ? "style='font-weight:bold;'" : '';
	$descr = $ach 
			? "E-check: <span $style>************{$ach['last4']}</span>"
			: "No active bank account on record.";
	//if($ach['vaultid']) $descr = "<div style='display:inline;' title='Vault ID: {$cc['vaultid']}'>$descr</div>"; 
	return $descr;
}
?>

<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

function useHomeAddress() {
	var src = makeArray('h_address', 'h_city', 'h_state', 'h_zip', 'h_phone');
	var dest = makeArray('x_address', 'x_city', 'x_state', 'x_zip', 'x_phone');
	for(var i=0;i<dest.length;i++)
		document.getElementById(dest[i]).value = document.getElementById(src[i]).value;
}

// THESE FUNCTIONS SUPERSEDE THEIR ALREADY-DEFINED COUNTERPARTS IN client-own-account.php
function createACH() {
  if(MM_validateFormArgs(achFormArgsToTest())) {
		document.getElementById('action').value='createACH';
		document.acheditor.submit();
	}
}

function dropAccount(replace) {
	if(!confirm('Are you sure you want to drop this account'+(replace ? ' and replace it?' : '?')))
		return;
	document.getElementById('action').value = (replace ? 'createACH' : 'dropACH');
	document.acheditor.action = '<?= $backlinkScript ?>'; // will be set differently for Solveras
	document.acheditor.submit();
}


function replaceACH() {
//alert(document.getElementById('achformtable').style.display);	
	if(document.getElementById('achformtable').style.display=='none') {
		document.getElementById('achformtable').style.display='inline';
		document.getElementById('replaceCCButton').value='Save New Account';
		document.getElementById('replaceCCButton').onclick=replaceACH;
	}
	else if(MM_validateFormArgs(achFormArgsToTest())) {
		document.getElementById('action').value='replaceACH';
		document.acheditor.submit();
	}
}
// ===============================================================================

// TXP constraints:
// name alpha only no punct or num
// acctnum numeric 10-17 digits -- industry standard
// abacode numeric 9 digits -- industry standard


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
	else {
	  var abaRegExp = /^((0[0-9])|(1[0-2])|(2[1-9])|(3[0-2])|(6[1-9])|(7[0-2])|80)([0-9]{7})$/;
		if(!abaRegExp.test(val)) abacodemessage = prettyName('abacode')+' must be a valid bank routing number';
	}
	
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

<?  ?>	
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
	
<? if(mattOnlyTEST()) echo "alert(extraArgs);"; ?>	
	// IE Kludge
	/*if(statemessage) {
		args[args.length] = statemessage;
		args[args.length] = '';
		args[args.length] = 'MESSAGE';
	}*/

	return args;
}

function isDashed10DigitPhoneNumber(val) {
	var regex = /^([0-9]{3}-)[0-9]{3}-[0-9]{4}$/ 
	return regex.test(val);
	
	/*if(val.length != 12) return false;
	for(var i=0;i<12;i++) {
		if(i==3 || i==7) {
			if(val.charAt(i) != "-") return false;
		}
		else if(val.charAt(i) < "0" || val.charAt(i) > "9") return false;
	}
	return true;*/
	
}

function ZIPCodeError(val) {
	var zipregex = /^[0-9]{5}$/
	var zipPlus4regex = /^([0-9]{5}-)[0-9]{4}$/
	return zipregex.test(val) || zipPlus4regex.test(val) ? '' : 'ZIP Code must be in the form XXXXX or XXXXX-XXXX';
}
</script>