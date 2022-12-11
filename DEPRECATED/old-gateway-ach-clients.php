<? // old-gateway-ach-clients.php


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "preference-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";

$locked = locked('c-');
extract($_REQUEST);

if($activeOnly) 	$otherargs['activeOnly'] = "activeOnly=1";
if($hideCurrentGateway)	$otherargs['hideCurrentGateway'] = "hideCurrentGateway=1";
// find the most recent ACH account for each client with an ACH account
// show the gateway that account was entered for
// show the client as inactive if applicaable


	
	
$latestClientACH = fetchAssociationsKeyedBy(
	"SELECT tblecheckacct.*, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname, fname, lname, email,
				tblclient.active as activeclient
		FROM tblecheckacct
		LEFT JOIN tblclient ON clientid = clientptr
		ORDER by acctid ASC", 'sortname');
		
if(TRUE || mattOnlyTEST()) foreach($latestClientACH as $sortname=>$row) {
	$primary = getPrimaryPaySource($row['clientptr']);
	if($primary['ccid']) unset($latestClientACH[$sortname]);
}
		
ksort($latestClientACH);

if($emailList) {
	mailChimpView($latestClientACH, $activeOnly, $hideCurrentGateway);
	exit;
}
else {
	echo "First two links below filter your ACH clients.  Last link opens a MailChimp-style mailing list.<hr>";
	if($activeOnly) 
		echo "<a href='old-gateway-ach-clients.php?{$otherargs['hideCurrentGateway']}'>Show Inactive Clients Also</a>";
	else echo "<a href='old-gateway-ach-clients.php?activeOnly=1&{$otherargs['hideCurrentGateway']}'>Show Only Active Clients</a>";

	echo " - ";

	if($hideCurrentGateway)
		echo "<a href='old-gateway-ach-clients.php?{$otherargs['activeOnly']}'>Show Current Gateway Also</a>";
	else
		echo "<a href='old-gateway-ach-clients.php?hideCurrentGateway=1&{$otherargs['activeOnly']}'>Show Only Old Gateways And Inactive Bank Accounts</a>";

	$allOtherArgs = join('&', (array)$otherargs);
	echo " - ";
		echo "<a target='emailList' href='old-gateway-ach-clients.php?emailList=1&$allOtherArgs'>Email List of Clients Currently Shown</a><p>";
	tableView($latestClientACH, $activeOnly, $hideCurrentGateway);
}

function mailChimpView($latestClientACH, $activeOnly, $hideCurrentGateway) {
	foreach($latestClientACH as $name => $ach) {
		$gateway = $ach['gateway'];
		$gatewayCurrent = $ach['gateway'] == $_SESSION['preferences']['ccGateway'];
		$gateway = $gatewayCurrent ? $gateway : "<span style='color:red;font-weight:bold;'>$gateway</span>";
		$inactiveclient = $ach['activeclient'] ? '' : " (inactive)";
		$inactiveaccount = $ach['active'] ? '' : " (inactive ACH account)";
		if($activeOnly && $inactiveclient) continue;
		if($hideCurrentGateway) {
			if(!$gatewayCurrent || $inactiveaccount) ;
			else continue;
		}
		echo "{$ach['email']}\t{$ach['fname']}\t{$ach['lname']}<br>";
	}
}
function tableView($latestClientACH, $activeOnly, $hideCurrentGateway) {
	echo "<table>";
	foreach($latestClientACH as $name => $ach) {
		$gateway = $ach['gateway'];
		$gatewayCurrent = $ach['gateway'] == $_SESSION['preferences']['ccGateway'];
		$gateway = $gatewayCurrent ? $gateway : "<span style='color:red;font-weight:bold;'>$gateway</span>";
		$inactiveclient = $ach['activeclient'] ? '' : " (inactive)";
		$inactiveaccount = $ach['active'] ? '' : " (inactive ACH account)";
		if($activeOnly && $inactiveclient) continue;
		if($hideCurrentGateway) {
			if(!$gatewayCurrent || $inactiveaccount) ;
			else continue;
		}
		echo "<tr><td>{$ach['name']}$inactiveclient<td>$gateway$inactiveaccount<td>{$ach['email']}";
	}
	echo "</table>";
}
