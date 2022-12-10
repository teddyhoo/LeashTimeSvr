<? //client-cc-edit2.php


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "preference-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";
$locked = locked('c-');

?>
<div style='font-size:1.2em;'>
<?
if(!getPreference('offerClientUIAccountPage')) {
	echo "Your rights to view this page are insufficient.  <a href='index.php'>Home</a>";
	exit;
}
$client = array_merge(getClient($_SESSION['clientid']), getOneClientsDetails($_SESSION['clientid']));
$cc = getCCData();
if(!$cc['expires'])
	echo $cc[0].'<p>Please use the form below to enter a credit card.<p>';
else {
	$expired = strtotime($cc['expires']) <= strtotime('now');
	echo "<h3>Your credit card:</h3> {$cc['descr']}"
					.($expired ? '<p>has expired.' 
											: '<p>(Autopay is '.($cc['autopay'] ? '' : 'not').' authorized.)');
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
echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
echo '<p>';
echo "<form method='POST' name='cceditor'><table style='display:$formDisplay' id='ccformtable'>";
$redstar = "<font color='red'>* </font>";
inputRow($redstar.'Card Number:', 'x_card_num', null, null, 'emailInput', null, null, 'warnIfCCFormatInvalid(this)');

//inputRow($redstar.'Company: <span class="tiplooks">Visa, Mastercard, etc.</span>', 'company', $cc['company'], null, 'emailInput');

selectRow($redstar.'Company:',
		'company', $cc['company'], getAcceptedCreditCardTypeOptions('--Select Card Type--'));

$expirationParts = $cc['x_exp_date'] ? explode('/', expirationDate($cc['x_exp_date'])) : array(null, null);
$months = explodePairsLine('--| ||01|1||02|2||03|3||04|4||05|5||06|6||07|7||08|8||09|9||10|10||11|11||12|12');
$months['--'] = '';
$years = array('--'=>'');
for($i=date('Y');$i<date('Y')+13;$i++) $years[$i] = $i;
$expirationEls = selectElement('', 'expmonth', $expirationParts[0], $months, null, null, null, true).' '.
									selectElement('', 'expyear', $expirationParts[1], $years, null, null, null, true);
labelRow($redstar.'Expiration', '', $expirationEls, null, null, null,  null, $rawValue=true);
inputRow('Card Verification Number:', 'x_card_code');
checkboxRow('I authorize AutoPay:', 'autopay', ($cc['descr'] ? $cc['autopay'] : true));

echo "<tr><td>&nbsp;</td></tr>\n";
echo "<tr><td style='font-size:1.2em;'>Billing Information</td><td>";
echoButton('', 'Use My Home Address', 'useHomeAddress()');
echo "</td></tr>\n";

inputRow($redstar.'Name on Card (First):', 'x_first_name', $client['fname'], null, 'emailInput');
inputRow($redstar.'Name on Card (Last):', 'x_last_name', $client['lname'], null, 'emailInput');
inputRow('Company (optional):', 'x_company', '', null, 'emailInput');
inputRow($redstar.'Address:', 'x_address', $cc['x_address'], null, 'emailInput');
$onBlur= function_exists('dumpZipLookupJS') ? "lookUpZip(this.value, \"x_\")" : '';
//$allowAutoCompleteOnce = true;
inputRow($redstar.'ZIP:', 'x_zip', $cc['x_zip'], null, 'emailInput', null,  null, $onBlur);
inputRow($redstar.'City:', 'x_city', $cc['x_city'], null, 'emailInput');
inputRow($redstar.'State:', 'x_state', $cc['x_state'], null, 'emailInput');
inputRow($redstar.'Country:', 'x_country', ($cc['x_country'] ? $cc['x_country'] : 'USA'), null, 'emailInput');
inputRow($redstar.'Phone:', 'x_phone', $cc['x_phone'], null, 'emailInput');
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
			"SELECT tblcreditcard.company, tblcreditcard.last4, tblcreditcard.x_exp_date, tblcreditcard.autopay, tblcreditcardinfo.* 
				FROM tblcreditcard 
				LEFT JOIN tblcreditcardinfo ON ccptr = ccid
				WHERE clientptr = {$_SESSION['clientid']} AND active");
	return $cc 
			? array('descr'=>"{$cc['company']} ************{$cc['last4']} Expires: ".shortExpirationDate($cc['x_exp_date']),
							'expires'=>$cc['x_exp_date'], 'autopay'=>$cc['autopay'])
			: array("No active credit card on record.");
}
