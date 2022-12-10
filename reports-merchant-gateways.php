<? // reports-merchant-gateways.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "preference-fns.php";
locked('o-');
if(!staffOnlyTEST()) {
	echo "This report is for LT Staff Only.";
	exit;
}

$databases = fetchCol0("SHOW DATABASES");
$bizzes = fetchAssociationsKeyedBy("SELECT *, IFNULL(bizname, db) as label FROM tblpetbiz WHERE activebiz=1", 'label');
$allBizzesLeashTimeFirst[] = $bizzes['LeashTime Customers'];
unset($bizzes['LeashTime Customers']);
foreach($bizzes as $biz) $allBizzesLeashTimeFirst[] = $biz;


foreach($allBizzesLeashTimeFirst as $bizCount => $biz) {
	if($bizCount == count($allBizzesLeashTimeFirst)-1) $lastBiz = true;
	if(!in_array($biz['db'], $databases)) {
		//echo "<br><font color=gray>DB: {$biz['db']} not found.<br></font>";
		continue;
	}
	if(!$biz['activebiz']) continue;
	if($biz['test']) continue;
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);

	if(!($ccGateway = fetchPreference('ccGateway'))) continue;
	
	$gateway = array('label'=>$biz['label'], 'set'=>fetchPreference('x_login'), 'ach'=>fetchPreference('gatewayOfferACH'));
	
	$gateways[$ccGateway][] = $gateway;
	$ready[fetchPreference('ccGateway')] += $gateway['set'] ? 1 : 0;
}
ksort($gateways);
foreach($gateways as $gatewaygroup) {
	$totalusers += count($gatewaygroup);
	foreach($gatewaygroup as $b) $totalsetupusers += $b['set'] ? 1 : 0;
}

// ***************************************************************************
$breadcrumbs = "<a href='reports.php'>Reports</a>";	
$pageTitle = "Merchant Gateway Users";
include "frame.html";
echo "Businesses using Merchant Gateways: $totalusers<p>";
echo "Businesses with Merchant ID set: $totalsetupusers<p>";
echo "<p class='tiplooks'>Test businesses and inacive businesses excluded.</p>";
echo "<table class='fontSize1_1em' border=1 bordercolor=black><tr><th>Business<th>Set Up<th>ACH</tr>";
foreach($gateways as $gateway => $gatewaygroup) {
	if($gateway == 'TransFirstV1') $gateway .= " (Solveras / TXP)";
	if($gateway == 'TransFirstTransactionExpress') $gateway .= " (Pure TXP)";
	echo "<tr><td colspan=2 class='fontSize1_2em' style='font-weight:bold;'>$gateway</td></tr>";
	foreach($gatewaygroup as $b) 
		echo "<tr><td>{$b['label']}<td>"
						.($b['set'] ? 'yes' : '<font color=red>no</font>')
						."<td>".($b['ach'] ? '<b>yes</b>' : 'no')
						.'</tr>';
}
echo "</table>";

	
include "frame-end.html";

