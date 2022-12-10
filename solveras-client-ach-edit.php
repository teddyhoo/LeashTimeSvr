<? // solveras-client-ach-edit.php


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "preference-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";
require_once "solveras-gateway-class.php";

$locked = locked('c-');

?>
<div style='font-size:1.2em;'>
<?
if(!getPreference('offerClientUIAccountPage')) {
	echo "Your rights to view this page are insufficient.  <a href='index.php'>Home</a>";
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
$gateWayObject = getGatewayObject('Solveras');

$client = array_merge(getClient($_SESSION['clientid']), getOneClientsDetails($_SESSION['clientid']));
$ach = getACHData();
$achToShow = getActiveClientACH($_SESSION['clientid']);

$formURL = $gateWayObject->startAuthRequest($auth, $client, $op = "clientach");

//if(mattOnlyTEST()) $formURL = 'testmonths.php';

if(is_array($formURL)) {
	echo "<font color='red'>{$formURL[0]}: {$formURL[1]}<p>";
}

if($_SESSION['solveras_error'])
	echo "<font color=red>{$_SESSION['solveras_error']}</font><p>";
$_SESSION['solveras_error'] = '';

if(!$achToShow['vaultid'])
	'<p>Please use the form below to enter E-checking Information.<p>';
else {
	echo "<h3>$ach ";
	if(!$achToShow['primarypaysource']) 
		echoButton('', 'Bill This Account',
					"ajaxGetAndCallWith(\"cc-primary-set.php?id=0&choice=ACH\", refresh, \"primary!\")");
	echo "</h3>";

}


$formDisplay = !$achToShow['vaultid'] ? 'inline' : 'none';
if($achToShow['vaultid']) {
	echoButton('', 'Drop Current Account', 'dropACH()');
	echo " ";
	echoButton('replaceCCButton', 'Replace Current Account', 'replaceACH()');
}
else echoButton('', 'Save Account', 'createACH()');
echo " ";
echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
echo '<p>';

echo "<form method='POST' name='acheditor' action='$formURL'>
<table style='display:$formDisplay' id='achformtable'>";


$redstar = "<font color='red'>* </font>";
hiddenElement('acctid', '');
inputRow($redstar.'Account Name:', 'billing-account-name', '', null, 'emailInput');
inputRow($redstar.'Account Number: ', 'billing-account-number', null, null, 'emailInput');
//inputRow($redstar.'Bank Name:', 'billing-account-name', '', null, 'emailInput');
inputRow($redstar.'Bank Routing Number:', 'billing-routing-number', '', null, 'emailInput');
selectRow('Account Type:', 'billing-account-type', $value=null, array(''=>'', 'checking'=>'checking', 'savings'=>'savings'));
selectRow('Bank Customer Type:', 'billing-entity-type', $value=null, array(''=>'', 'personal'=>'personal', 'business'=>'business'));
echo "<tr>\n";
checkboxRow('I authorize AutoPay:', 'fax', ($achToShow ? $achToShow['autopay'] : true));

echo "<tr><td>&nbsp;</td></tr>\n";
echo "<tr><td style='font-size:1.2em;'>Billing Information</td><td>";
echoButton('', 'Use My Home Address', 'useHomeAddressSolveras()');
echo "</td></tr>\n";

inputRow('Company (optional):', 'x_company', null, null, 'emailInput');
inputRow($redstar.'Address:', 'billing-address1', null, null, 'emailInput');
$onBlur= function_exists('dumpZipLookupJS') ? "lookUpZip(this.value, \"x_\")" : '';
//$allowAutoCompleteOnce = true;
inputRow($redstar.'City:', 'billing-city', null, null, 'emailInput');
inputRow($redstar.'State:', 'billing-state', null, null, 'emailInput');
inputRow($redstar.'ZIP:', 'billing-postal', null, null, 'emailInput', null,  null, $onBlur);
inputRow($redstar.'Country:', 'billing-country', ($achToShow['x_country'] ? $achToShow['x_country'] : 'USA'), null, 'emailInput');
inputRow($redstar.'Phone:', 'billing-phone', null, null, 'emailInput');
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

<script language='javascript'>

function useHomeAddressSolveras() {
	var src = makeArray('h_address', 'h_city', 'h_state', 'h_zip', 'h_phone');
	var dest = makeArray('billing-address1', 'billing-city', 'billing-state', 'billing-postal', 'billing-phone');
	for(var i=0;i<dest.length;i++)
		document.getElementById(dest[i]).value = document.getElementById(src[i]).value;
}

// THESE FUNCTIONS SUPERSEDE THEIR ALREADY-DEFINED COUNTERPARTS IN client-own-account.php
function createACH() {
  if(MM_validateFormArgs(achFormArgsToTest())) {
		document.getElementById('action').value='create';
		document.acheditor.submit();
	}
}

function replaceACH() {
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

</script>