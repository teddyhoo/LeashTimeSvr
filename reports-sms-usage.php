<? // reports-sms-usage.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "sms-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
locked('o-');

function statsSince($date) {
$filter = "datetime > '$date'";
	$comms = getSMSCommsFor(-1, $filter, $clientflg=false, $totalMsg=false);
	foreach($comms as $comm) {
		$correstypes = explodePairsLine('tblclient|c||tblprovider|s||tbluser,m');
		$correstype = $correstypes[$comm['correstable']];
		$comm['sender'] .= " [$correstype]";
		if($comm['inbound']) {
			$stats['inboundmessages'] += 1;
			$stats['inboundsegments'] += $comm['numsegments'];
			$stats['inboundsenders'][$comm['sender']] += 1;
			$stats['inboundsendersegments'][$comm['sender']] += $comm['numsegments'];
		}
		else {
			$stats['outboundmessages'] += 1;
			$stats['outboundsegments'] += $comm['numsegments'];
			$stats['outboundsenders'][$comm['sender']] += 1;
			$stats['outboundsendersegments'][$comm['sender']] += $comm['numsegments'];
		}
	}
//echo "<pre>";print_r($stats);exit;
	return $stats;
}

function statsTable($stats) {
	echo "<table border=1 bordercolor=gray>";
	echo "<tr><th>&nbsp;</th><th>Messages</th><th>Segments</th></tr>";
	echo "<tr><th>Inbound</th><td>{$stats['inboundmessages']}</td><td>{$stats['inboundsegments']}</td></tr>";
	echo "<tr><th>Outbound</th><td>{$stats['outboundmessages']}</td><td>{$stats['outboundsegments']}</td></tr>";
	$totalmessages = $stats['inboundmessages'] + $stats['outboundmessages'];
	$totalsegments = $stats['inboundsegments'] + $stats['outboundsegments'];
	
	echo "<tr><th>TOTAL</th><td>$totalmessages</td><td>$totalsegments</td></tr>";
	echo "</table>";
	
	$allSenders = array_unique(array_merge(array_keys((array)$stats['inboundsenders']), array_keys((array)$stats['outboundsenders'])));
	sort($allSenders);
	echo "<h3>Correspondents</h3>";
	echo "<table border=1 bordercolor=gray>";
	echo "<tr><td>&nbsp;</td><th colspan=2>Inbound</th><th colspan=2>Outbound</th></tr>";
	echo "<tr><td>&nbsp;</td><th>Messages</th><th>Segments</th><th>Messages</th><th>Segments</th></tr>";
	foreach($allSenders as $sender) {
		echo "<tr><td>$sender</td><td>{$stats['inboundsenders'][$sender]}</td><td>{$stats['inboundsendersegments'][$sender]}</td>";
		echo "<td>{$stats['outboundsenders'][$sender]}</td><td>{$stats['outboundsendersegments'][$sender]}</td></tr>";
	}
	echo "</table>";
}
	
$breadcrumbs = "<a href='reports.php'>Reports</a>";	

$ratesInfo = fauxLink('Rates Information', 'showRatesInfo()', 1, "References to industry per-message rates");

$breadcrumbs .= "- ".$ratesInfo;

require "frame.html";
echo "<h2>SMS (Text) Usage</h2>";
?>
<div id='ratesinfo' style='background:palegreen;display:none;padding:10px;'>
<h2>Rate Information</h2>
(as of 8/31/2019)<p>
Industry Standard per-message rates (outside of expensive phone plans):
<p>
<h3>Verizon Wireless</h3>
$0.20/message applies to messages sent and received in the Nationwide Rate and Coverage Area<br>
$0.25/message sent and $0.20/message received for International Text Messages<br>
Source: <a href='https://www.verizonwireless.com/support/text-messaging-legal/' target='_blank'>Verizon Wireless Legal</a>
<h3>AT&T</h3>
Domestic Pay-Per-Use Charges:<br>
Text/Instant Messaging $0.20 per message<br>
Picture/Video Messages $0.30 per message.<p>
International messages sent from the U.S. <br>
$0.25 for Text Messages<br>
$0.50 for Picture/Video Messages<p>
Source: <a href='https://www.att.com/shop/wireless/features/messaging-1000-sku5050225.html' target='_blank'>Messaging Phone Features</a>
</div>
<?
if(!tableExists('tblmessagemetadata')) {
	echo "{$_SESSION['preferences']['bizName']} ".(staffOnlyTEST() ? "($db)" : '')." is not equipped for text messaging.";
	echo "<p><a href='".globalURL('')."'>Home</a>";
	exit;
}

$forDate = $_REQUEST['date'];
$thirtyDaysBack = date('Y-m-d 00:00:00', ($forDate ? strtotime("-30 days", strtotime($forDate)) : strtotime("-30 days")));



echo "<h3>Last 30 Days (starting ".shortDate(strtotime($thirtyDaysBack)).")</h3>";
statsTable(statsSince($thirtyDaysBack));

$thisMonth = $forDate ? date('Y-m-01 00:00:00', strtotime($forDate)) : date('Y-m-01 00:00:00');
echo "<hr><h3>This Month (starting ".shortDate(strtotime($thisMonth)).")</h3>";
statsTable(statsSince($thisMonth));
reportOnSitterAndManagerTextableNumbers();
require "frame-end.html";

function reportOnSitterAndManagerTextableNumbers() {
	$sitters = fetchAssociations("SELECT * FROM tblprovider WHERE active = 1 ORDER by lname, fname");
	foreach($sitters as $sitter) {
		$prime = primaryTextPhoneNumber($sitter);
		if($prime) $ok[] = "{$sitter['fname']} {$sitter['lname']}";
		else $nogood[] = "{$sitter['fname']} {$sitter['lname']}";
	}
	
	// managers
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$users = fetchAssociations(
		"SELECT * 
			FROM tbluser 
			WHERE bizptr = {$_SESSION["bizptr"]} AND active = 1 AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
			ORDER BY lname, fname");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	echo "<p style='font-weight:bold'>Managers with textable phone numbers</p>";
	foreach($users as $user)
		echo "({$user['loginid']}) {$user['fname']} {$user['lname']} "
			.(findSMSPhoneNumberFor($user) ? '&#x2714;' /* check */ : '<font color=red>&#x2715;</font>')
			.'<br>';
	
	if(!$ok) echo "<p style='font-weight:bold'>There are no text-enabled active sitters.</p>";
	else echo "<p style='font-weight:bold'>The following sitters have text-enabled primary phones:</p>".join('<br>', $ok);
	if(!$nogood) echo "<p style='font-weight:bold'>All active sitters have text-enabled phones.</p>";
	else echo "<p style='font-weight:bold'>The following sitters do not have text-enabled primary phones:</p>".join('<br>', $nogood);
}
?>
<script>
function showRatesInfo() {
	$('#ratesinfo').toggle();
}
</script>
/*
$thirtyDaysBack = date('Y-m-d 00:00:00', strtotime("-30 days"));
echo "<h3>Last 30 Days (starting ".shortDate(strtotime("-30 days")).")</h3>";
statsTable(statsSince($thirtyDaysBack));

$thisMonth = date('Y-m-01 00:00:00');
echo "<hr><h3>This Month (starting ".shortDate(strtotime($thisMonth)).")</h3>";
statsTable(statsSince($thisMonth));
require "frame-end.html";
*/