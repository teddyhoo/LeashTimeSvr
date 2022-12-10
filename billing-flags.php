<? // billing-flags.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-flag-fns.php";
require_once "preference-fns.php";

// Allow client billing flags to be set en masse

$locked = locked('o-');

$_SESSION['user_notice'] = "<h2>Reminder</h2><span style='font-size:1.2em;'>This page is in <b>BETA TEST</b>.<p>Use with <b>EXTREME CAUTION.</b>";

$billingFlags = getBillingFlagList();
if($_GET['show']) {
	$flagid = $_GET['show'];
	$result = doQuery(
			"SELECT clientid, fname, lname, active
			 FROM tblclientpref
			 LEFT JOIN tblclient ON clientid = clientptr
			 WHERE property LIKE 'billing_flag_$flagid'
			 			AND active = 1
			 ORDER BY lname, fname");
	echo "<div style='background:palegreen;padding:10px;font-size:1.1em;'><h2>Active Clients with Billing flag <img src='{$billingFlags[$flagid]['src']}'></h2>";
	echo "Currently: ".mysql_num_rows($result)." clients.<p>";
  while($client = mysql_fetch_array($result, MYSQL_ASSOC))
  	echo "<a href='#c_{$client['clientid']}'>{$client['fname']} {$client['lname']}</a><br>";
  echo "</div>";
	exit;
}

if($_POST) {
	foreach($_POST as $k => $v) {
		if(strpos($k, 'c_') === FALSE) continue;
		$clientid = substr($k, strlen('c_'));
		$clientFlags = getClientBillingFlags($clientid);
		$clientFlagIDs = array_keys($clientFlags);
		
		// wipe billing flags for client $i
		//deleteTable('tblclientpref', "clientptr=$clientid AND property LIKE 'billing_flag_%'", 1);
		for($i=1; $i <= $maxBillingFlags; $i++) {
			if($_POST["c{$clientid}_$i"] && !$clientFlags[$i])
				setClientPreference($clientid, "billing_flag_$i", '|');
			else if(!$_POST["c{$clientid}_$i"] && $clientFlags[$i])
				setClientPreference($clientid, "billing_flag_$i", null);
		}
	}
	$_SESSION['frame_message'] = 'Changes saved.';
}

$pageTitle = "Billing Flag Assignment";
$breadcrumbs = "<a href='client-flags.php'>Client Flags</a>";
$extraHeadContent = '<style>#clientsAndFlags td {padding:7px;border-bottom:solid black 1px}</style>';
include "frame.html";
// ***************************************************************************
echo "<span class=tiplooks'>Click a Billing Flag to find clients with that flag.</span><br>";
echo "<table width=99%><tr>";
foreach($billingFlags as $flag) {
	$title = $flag['title'] ? safeValue($flag['title']) : '<span style="font-style:italic;color:gray;font-size:0.8em">No label supplied</span>';
	echo "<td class='fontSize1_1em' 
					onClick=
						\"\$.ajax({url:'billing-flags.php?show={$flag['flagid']}',
										success: function(data) {\$('#report').html(data);}});\"
					><img src='{$flag['src']}'> $title</td>";
}
echo "</tr></table><hr>";

$clients = fetchAssociationsKeyedBy(
	"SELECT clientid, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname
		FROM tblclient
		WHERE active = 1
		ORDER BY sortname", 'clientid');
echoButton('', 'Save All Flag Assignments', 'document.allFlagsForm.submit();');
echo "<form name='allFlagsForm' method='POST'>";
echo "<table><tr><td valign=top>"; // Clients and Report table
echo "<table id='clientsAndFlags'>"; // Clients table
foreach($clients as $clientid => $client) {
	$clientFlags = getClientBillingFlags($clientid);
	$clientFlagIDs = array_keys($clientFlags);
	echo "<tr><td>{$client['name']}<input type='hidden' name='c_$clientid'><a name='c_$clientid'></a></td><td>";
	foreach($billingFlags as $i => $bflag) {
		$title = safeValue($clientFlags[$i]['note'] ? $clientFlags[$i]['note'] : $billingFlags[$i]['title']);
		$checked = in_array($i, (array)$clientFlagIDs) ? 'CHECKED' : '';
		$boxid = "c{$clientid}_$i";
		echo "</td><td><input type='checkbox' id='$boxid' name='$boxid' $checked> <label for='$boxid'><img src='{$bflag['src']}' title='$title'></label>";
	}
	echo "</td></tr>";
}
echo "</table>"; // // END Clients table
echo "</td><td valign=top id='report'>";

echo "</td></tr></table>";  // END Clients and Report table
echo "</form>";

// ***************************************************************************
include "frame-end.html";
