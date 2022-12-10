<? // reminders.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "reminder-fns.php";
require_once "js-gui-fns.php";

// Determine access privs
if(userRole() == 'd') $locked = locked('d-');
else $locked = locked('o-');

$client = $_REQUEST['client'];
if(!$client) $provider = $_REQUEST['provider'];

if($client) {
	$filter = "AND clientptr = $client";
	$person = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = $client LIMIT 1");
} 
else if($provider) {
	$filter = "AND providerptr = $provider";
	$person = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE providerid = $provider LIMIT 1");
}

$privateTest = staffOnlyTEST() ? "1=1" : "userid = {$_SESSION['auth_user_id']}";
$reminders = fetchAssociations(
	"SELECT * FROM tblreminder 
	WHERE (userid = 0 OR $privateTest) $filter ORDER BY subject");
$reminderTypes = fetchAssociationsKeyedBy("SELECT * FROM tblremindertype ORDER BY label", 'remindertypeid');
foreach($reminders as $reminder) {
	if($reminder['clientptr']) $clientptrs[$reminder['clientptr']] = 1;
	if($reminder['providerptr']) $providerptrs[$reminder['providerptr']] = 1;
}
if($clientptrs) $clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid IN ("
																.join(',', array_keys($clientptrs)).")");																
if($providerptrs) $providers = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE providerid IN ("
																.join(',', array_keys($providerptrs)).")");																

foreach($reminders as $reminder) {
	$rcode = $reminder['remindercode'];
	if(isOneTimeReminder($reminder)) $onetimers[] = $reminder;
	else $repeaters[] = $reminder;
	if($groupCounts[$reminder['remindercode']]) $groups[$reminder['remindercode']][] = $reminder;
	$groupCounts[$reminder['remindercode']] ++;
}

if($onetimers) usort($onetimers, 'sendonSort');
function sendonSort($a, $b) { return strcmp($a['sendon'], $b['sendon']); }

function getManagerName($userid) {
	static $ownerNames;
	if($ownerNames) return $ownerNames[$userid];
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$ownerNames = fetchKeyValuePairs(
		"SELECT userid, CONCAT_WS(' ', fname, lname) as name
			FROM tbluser 
			WHERE bizptr = {$_SESSION['bizptr']}");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $ownerNames[$userid];
}

function groupRemindersTable() {
	global $reminderTypes, $groupCounts;
	if(!$reminderTypes) {
		echo "No group reminders found.";
		return;
	}
	foreach($reminderTypes as $reminderType) {
		$sendon = $reminderType['sendon'];
		$sendannually = strpos($sendon, 'ann') === 0 ? '2020-'.str_replace('_', '-', substr($sendon, strlen('ann'))) : null;
		$sendon = 
			isOneTimeReminder($reminderType) ? shortDate(strtotime($reminderType['sendon'])) : (
			(int)$reminderType['sendon']  ? "Day {$reminderType['sendon']}" : (
			$sendannually ? "Annually on ".shortestDate(strtotime($sendannually), 'no year') :(
			"{$reminderType['sendon']}s")));
		$label = fauxLink($reminderType['label'], "openReminderType(\"{$reminderType['remindertypeid']}\")", 1);
		if(staffOnlyTEST() && $reminderType['userid'] && $reminderType['userid'] != $_SESSION['auth_user_id']) {
			$title = "private to ".safeValue(getManagerName($reminderType['userid']));
			$label = "<span title='$title' style='font-variant:small-caps;'>[private]</span> $label";
		}
		$restriction = $reminderType['restriction'] ? $reminderType['restriction'] : 'clients and sitters';
		$count = $groupCounts[$reminderType['remindertypeid']] ? $groupCounts[$reminderType['remindertypeid']] : '0';
		$restriction .= " ($count)";
		$rows[] = array('private'=>($reminderType['userid'] ? 'yes' : 'no'), 'subject'=>$label, 'sendon'=>$sendon, 'restriction'=>$restriction);
	}
	$columns = explodePairsLine('subject|Group||sendon|Scheduled||restriction|Restriction (count)||private|Private');
	tableFrom($columns, $rows, 'width=75%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
}


function repeatingRemindersTable() {
	global $repeaters, $reminderTypes, $clients, $providers;
	if(!$repeaters) {
		echo "No repeating reminders found.";
		return;
	}
	foreach($repeaters as $reminder) {
		if(!is_array($reminder)) {
			$group = $reminder;
			$reminder = $groups[$reminder][0];
			continue;
		}
		else $group = null;
		$sendon = $reminder['sendon'];
		$sendannually = strpos($sendon, 'ann') === 0 ? '2020-'.str_replace('_', '-', substr($sendon, strlen('ann'))) : null;

		$sendon = 
			$sendannually ? "Annually on ".shortestDate(strtotime($sendannually), 'no year') : (
			(int)$reminder['sendon'] ? "Day {$reminder['sendon']}" : 
			"{$reminder['sendon']}s");
		$subject = fauxLink($reminder['subject'], "openReminder(\"{$reminder['reminderid']}\")", 1);
		if(staffOnlyTEST() && $reminder['userid'] && $reminder['userid'] != $_SESSION['auth_user_id']) {
			$title = "private to ".safeValue(getManagerName($reminder['userid']));
			$subject = "<span title='$title' style='font-variant:small-caps;'>[private]</span> $subject";		
		}

		$reminderType = $reminderTypes[$reminder['remindercode']];
		if($reminderType) 
			$group = $reminderType['label'].($reminderType['restriction'] ? "({$reminderType['restriction']})" : "");
		else $group = '-- None --';
		$person = $reminder['clientptr'] ? 'Client '.$clients[$reminder['clientptr']] : (
							$reminder['providerptr'] ? 'Sitter '.$providers[$reminder['providerptr']] : '--');
		$rows[] = array('private'=>($reminder['userid'] ? 'yes' : 'no'), 'subject'=>$subject, 'sendon'=>$sendon, 'group'=>$group, 'person'=>$person);
	}
	$columns = explodePairsLine('subject|Subject||sendon|Scheduled||group|Group||private|Private||person|Person');
	tableFrom($columns, $rows, 'width=75%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
}

function oneTimeRemindersTable() {
	global $onetimers, $reminderTypes, $clients, $providers;
	if(!$onetimers) {
		echo "No one-time reminders found.";
		return;
	}
	foreach($onetimers as $reminder) {
		$sendon = shortDate(strtotime($reminder['sendon']));
		$subject = fauxLink($reminder['subject'], "openReminder(\"{$reminder['reminderid']}\")", 1);
		if(staffOnlyTEST() && $reminder['userid'] && $reminder['userid'] != $_SESSION['auth_user_id']) {
			$title = "private to ".safeValue(getManagerName($reminder['userid']));
			$subject = "<span title='$title' style='font-variant:small-caps;'>[private]</span> $subject";		
		}
		$reminderType = $reminderTypes[$reminder['remindercode']];
		if($reminderType) 
			$group = $reminderType['label'].($reminderType['restriction'] ? "({$reminderType['restriction']})" : "");
		else $group = '-- None --';
		$person = $reminder['clientptr'] ? 'Client '.$clients[$reminder['clientptr']] : (
							$reminder['providerptr'] ? 'Sitter '.$providers[$reminder['providerptr']] : '--');
		$rows[] = array('private'=>($reminder['userid'] ? 'yes' : 'no'), 'subject'=>$subject, 'sendon'=>$sendon, 'group'=>$group, 'person'=>$person);
	}
	$columns = explodePairsLine('subject|Subject||sendon|Scheduled||group|Group||private|Private||person|Person');
	tableFrom($columns, $rows, 'width=75%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
}

function pastRemindersTable() {
	global $person;
	$pattern = $person['clientid'] ? "<client>{$person['clientid']}</client>" : "<provider>{$person['providerid']}</provider>";
	$requests = fetchAssociations($sql = "SELECT * FROM tblclientrequest WHERE requesttype = 'Reminder' AND note LIKE '%$pattern%' ORDER BY received DESC");
	foreach($requests as $request) {
		$received = shortDate(strtotime($request['received']));
		$resolution = $request['resolution'] ? $request['resolution'] : 'unresolved';
		$subject = fauxLink($request['street1'], "openRequest(\"{$request['requestid']}\")", 1);
		$rows[] = array('subject'=>$subject, 'received'=>$received, 'resolution'=>$resolution);
	}
	if($requests) {
		$columns = explodePairsLine('received|Date||subject|Subject||resolution|Resolution');
		tableFrom($columns, $rows, 'width=70%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
	}
	else echo "No past reminders found.";
}

if($person) {
	$pageTitle = "{$person['name']}'s Reminders";
	$commTabBreadCrumb = $client ? "client-edit.php?tab=communication&id=$client" : "provider-edit.php?tab=communication&id=$provider";
	$commTabBreadCrumb = "<a href='$commTabBreadCrumb'>{$person['name']}'s Comm Tab</a>";
	$breadcrumbs = "$commTabBreadCrumb - <a href='reminders.php'>System Reminders</a>";
}
else $pageTitle = "System Reminders";

// ***************************************************************************
include "frame.html";
echoButton('', 'Add a Reminder', "openReminder(0)");
echo " ";
if(!$person) echoButton('', 'Add a Group Reminder', "openReminderType(0)");
echo "<table width=100%>\n<span style='font-size:1.1em'>";
if(!$person) {
	startAShrinkSection('Group Reminders', 'group', $hidden=false);;
	groupRemindersTable();
	endAShrinkSection();
}
startAShrinkSection('Repeating Reminders', 'repeating', $hidden=false);;
repeatingRemindersTable();
endAShrinkSection();
startAShrinkSection('One-Time Reminders', 'onetime', $hidden=false);;
oneTimeRemindersTable();
endAShrinkSection();
if($person) {
	startAShrinkSection('Past Reminders', 'past', $hidden=false);;
	pastRemindersTable();
	endAShrinkSection();
}
echo "</table>";
echo "<br><img src='art/spacer.gif' width=1 height=300>";
?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

<? 
dumpShrinkToggleJS();
?>
function update() {
	document.location.href='reminders.php<?= $person['clientid'] ? "?client={$person['clientid']}" : (
																						$person['providerid'] ? "?provider={$person['providerid']}" : '')?>';
}

function openReminder(id) {
	var person = '<?= $client ? "&client=$client" : ($provider ? "&provider=$provider" : '') ?>';
	$.fn.colorbox({href:"reminder-edit.php?id="+id+person, width:"690", height:"420", scrolling: true, opacity: "0.3", iframe: "true"});
}

function openRequest(id) {
	openConsoleWindow("viewrequest", "request-edit.php?id="+id+"&updateList=requests",610,600);
}

function openReminderType(id) {
	$.fn.colorbox({href:"reminder-type-edit.php?id="+id, width:"690", height:"500", scrolling: true, opacity: "0.3", iframe: "true"});
}
</script>
<?
include "frame-end.html";
?>
