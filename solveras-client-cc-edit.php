<? // solveras-client-cc-edit.php


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "preference-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";

$locked = locked('c-');
$cgw = $_SESSION['preferences']['ccGateway'];
if($cgw == 'Solveras') require_once "solveras-gateway-class.php";
else if($cgw == 'TransFirstV1') require_once "transfirst-nmi-txp-gateway-class.php";
?>
<div style='font-size:1.2em;'>
<?
if(!getPreference('offerClientUIAccountPage')) {
	echo "Your rights to view this page are insufficient.  <a href='index.php'>Home</a>";
	exit;
}

$auth = merchantInfoSupplied();
//	'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");
if(!gatewayIsNMI($cgw)) {
	echo "ERROR: Wrong Gateway";
	exit;
}
if(!$auth) {
	echo "ERROR: Incomplete merchant authorization.";
	exit;
}
$gateWayObject = getGatewayObject($_SESSION['preferences']['ccGateway']);

$client = array_merge(getClient($_SESSION['clientid']), getOneClientsDetails($_SESSION['clientid']));
$cc = getCCData();

$formURL = $gateWayObject->startAuthRequest($auth, $client, $op = "clientcc");

if(is_array($formURL)) {
	echo "<font color='red'>{$formURL[0]}: {$formURL[1]}<p>";
}

if($_SESSION['solveras_error'])
	echo "<font color='red'>{$_SESSION['solveras_error']}</font><p>";
$_SESSION['solveras_error'] = '';

if(!$cc['expires'])
	echo $cc[0].'<p>Please use the form below to enter a credit card.<p>';
else {
	$expired = strtotime($cc['expires']) <= strtotime('now');
	$yourCard = "<h3>Your credit card:</h3> {$cc['descr']}";
	$yourCard .= ($expired ? '<p>has expired.' 
											: '<p>(Autopay is '.($cc['autopay'] ? '' : 'not').' authorized.)');
	//$yourCard = "<div title='Vault ID: {$cc['vaultid']}'>$yourCard</div>";
		if(!$cc['primarypaysource'] && !$expired) 
			$yourCard .= ' '.echoButton('', 'Bill This Card',
						"ajaxGetAndCallWith(\"cc-primary-set.php?id=0&choice=CC\", refresh, \"primary!\")",
						null, null, 'noecho', 'Click to make this your primary payment method.').'<p>';
	
	echo $yourCard;
}

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
else echoButton('', 'Save Credit Card', 'createCC()');
echo " ";

$quitCommand = 'parent.$.fn.colorbox.close();';

if($_REQUEST['iframe']) {
	$formAction = 'action="client-own-account.php?iframe=1"';
	$quitCommand = 'parent.lightBoxIFrameClose()';
}
echoButton('', "Quit", $quitCommand);
echo '<p>';

echo "<form method='POST' name='cceditor' action='$formURL'>
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
checkboxRow('I authorize AutoPay:', 'fax', ($cc['descr'] ? $cc['autopay'] : true));

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
inputRow($redstar.'City:', 'billing-city', null, null, 'emailInput'); // $cc['x_city']
inputRow($redstar.'State:', 'billing-state', null, null, 'emailInput'); // $cc['x_state']
inputRow($redstar.'ZIP:', 'billing-postal', null, null, 'emailInput', null,  null, $onBlur); //$cc['x_zip']
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

function getCCData() {
	$cc = fetchFirstAssoc(
			"SELECT tblcreditcard.company, tblcreditcard.last4, tblcreditcard.x_exp_date, tblcreditcard.autopay, gateway, vaultid,
								primarypaysource, tblcreditcardinfo.*
				FROM tblcreditcard 
				LEFT JOIN tblcreditcardinfo ON ccptr = ccid
				WHERE clientptr = {$_SESSION['clientid']} AND active");
	return $cc 
			? array('descr'=>"{$cc['company']} ************{$cc['last4']} Expires: ".shortExpirationDate($cc['x_exp_date']),
							'expires'=>$cc['x_exp_date'], 'autopay'=>$cc['autopay'], 'vaultid'=>$cc['vaultid'], 'primarypaysource'=>$cc['primarypaysource'])
			: array("No active credit card on record.");
}
?>

<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<? if($_REQUEST['iframe']) { 
	// handle in the case of an iframe lightbox
	// after any post, client-own-account.php (ORIG or NEW) will close the lightbox
?>
<script language='javascript' src='client-cc-ach-entry.js'></script>
<? }
?>
<script>
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
}

function useHomeAddressSolveras() {
	var src = makeArray('h_address', 'h_city', 'h_state', 'h_zip', 'h_phone');
	var dest = makeArray('billing-address1', 'billing-city', 'billing-state', 'billing-postal', 'billing-phone');
	for(var i=0;i<dest.length;i++)
		document.getElementById(dest[i]).value = document.getElementById(src[i]).value;
}

// THESE FUNCTIONS SUPERSEDE THEIR ALREADY-DEFINED COUNTERPARTS IN client-own-account.php or client-cc-ach-entry.js
function createCC() {
  if(MM_validateFormArgs(ccFormArgsToTest())) {
		document.getElementById('action').value='create';
		document.getElementById('billing-cc-exp').value = ''+document.getElementById('expmonth').value+'/'+document.getElementById('expyear').value;
		document.cceditor.submit();
	}
}

function replaceCC() {
	if(document.getElementById('ccformtable').style.display=='none') {
		document.getElementById('ccformtable').style.display='inline';
		document.getElementById('replaceCCButton').value='Save New Credit Card';
		document.getElementById('replaceCCButton').onclick=replaceCC;
	}
	else if(MM_validateFormArgs(ccFormArgsToTest())) {
		document.getElementById('action').value='replace';
		document.getElementById('billing-cc-exp').value = ''+document.getElementById('expmonth').value+'/'+document.getElementById('expyear').value;
		document.cceditor.submit();
	}
}
// ===============================================================================


</script>