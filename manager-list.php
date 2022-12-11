<? // manager-list.php
require_once "client-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_common.php";
//require_once "common/init_db_petbiz.php";

$locked = locked('o-');

$loggedInMgr = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = '{$_SESSION["auth_user_id"]}' LIMIT 1");
// this page is open only to LT Staff, owners, and owners/dispatchers with #md permission
if(!$loggedInMgr['isowner'] && !strpos($_SESSION['rights'], '#md') && !staffOnlyTEST()) {
	$pageTitle = "WARNING";
	$_SESSION['frame_message'] = "This page is available only to the business owner.";
	include "frame.html";
	include "frame-end.html";
}

$emailEditFeature = TRUE || staffOnlyTEST();


if($_REQUEST['edit']) echoEditor(); // exits
else if($_REQUEST['save']) $sendPassword = saveChanges();
else if($_REQUEST['sendemail']) getComposerContents(); // exits

require "common/init_db_common.php";


$managers = fetchAssociations(
	"SELECT *, CONCAT_WS(' ', fname, lname) as name
		FROM tbluser
		WHERE bizptr = {$_SESSION["bizptr"]}
			AND ltstaffuserid = 0
			AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
		ORDER BY SUBSTRING(rights FROM 1 FOR 1) DESC, lname ASC, fname ASC");

$pageTitle = "Business Administrators";
$extraHeadContent = "<style>.staffOnly {background:yellow;}</style>";
// #########################
include "frame.html";
?>
<style>
.mgrs tr :not(:first-child) { padding-left:15px; }
.strike {text-decoration:line-through; padding-left:0px;} 
</style>
<div style="float: right; padding: 10px;  padding-top: 0px; background:lightblue; width:300px;height:35px;cursor:pointer;"
	onclick="this.style.height=null;$('#therest').css('display','inline');">
<h3>Need a new Dispatcher?</h3><span id="therest" style="display:none;">Please contact support and tell us:
<ol style="list-style-position: inside;padding-left:10px;"><li>the person&apos;s full name
		<li>the person&apos;s email address
		<li>a description of what that person should be able to do
</ol>
<b>Even better,</b> if you want the new person to be just like another dispatcher, tell us the name of that dispatcher.</span>
</div>
<p>This page is available only to managers designated as owners and staff with specific permission.  While you can enable, disable, and 
set the temporary password of other managers and dispatchers, owners cannot edit other owners&apos; permissions.
<p>
Click on a user name to edit that user&apos;s access.
<p>
<?
$columns = explodePairsLine('name|Name||loginid|User Name||rightsnote|Rights');
fauxLink('Hide Inactive Staff', 'toggleInactiveStaff()', $noEcho=false, $title="Toggles display of inactive staff members", $id='toggleinactive');
echo "<h2>Managers</h2>";
$rowClasses = array();
$mgrPrefs = getManagerPrefs($_SESSION['bizptr'], $managers, 'suppressRevenueDisplay,managerNickname,frameLayout,showrateinclientschedulelist');
foreach($managers as $i => $mgr) {
	if($mgr['rights'][0] != 'o') continue;
	if(mattOnlyTEST()) $commLink = managerCommsLink($mgr['userid']);
	$mgr['name'] = "$commLink<span title='user id: {$mgr['userid']}'>".str_replace(' ', '&nbsp;', $mgr['name'])."</span>";
	$mgr['name'] .= $mgrPrefs[$mgr['userid']]['suppressRevenueDisplay'] ? "<br><span class='warning'>Revenue Display Suppressed</span>" : '';
	
	$userid = staffOnlyTEST() ? " ({$mgr['userid']})" : '';
	$mgr['loginid'] = $mgr['isowner'] && ($mgr['userid'] != $loggedInMgr['userid'] ) && !staffOnlyTEST() 
		? $mgr['loginid'] 
		: fauxLink($mgr['loginid'].$userid, "editMgr({$mgr['userid']})", 1, 'Control access for this manager.');
	$mgr['rightsnote'] = mgrRightsSummary($mgr);
	$class = $mgr['active'] ? 'futuretask' : 'canceledtask';
	if($mgr['isowner'] && staffOnlyTEST()) $class = "warning $class";
	if($i % 2 == 0) $class .= 'EVEN';
	$rowClasses[] = $class;
	$owners[] = $mgr;
}
tableFrom($columns, $owners, "width=95%", $class='mgrs', $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);

echo "<h2>Dispatchers / Admins</h2>";
$rowClasses = array();
foreach($managers as $i => $mgr) {
	if($mgr['rights'][0] == 'o') continue;
	if(mattOnlyTEST()) $commLink = managerCommsLink($mgr['userid']);
	$mgr['name'] = "$commLink<span title='user id: {$mgr['userid']}'>".str_replace(' ', '&nbsp;', $mgr['name'])."</span>";
	$mgr['name'] .= $mgrPrefs[$mgr['userid']]['suppressRevenueDisplay'] ? "<br><span class='warning'>Revenue Display Suppressed</span>" : '';
	$userid = staffOnlyTEST() ? " ({$mgr['userid']})" : '';
	$mgr['loginid'] = FALSE && $mgr['userid'] == $loggedInMgr['userid'] 
		? $mgr['loginid'] 
		: fauxLink($mgr['loginid'].$userid, "editMgr({$mgr['userid']})", 1, 'Control access for this dispatcher.');
	$mgr['rightsnote'] = rightsSummary($mgr['rights']);

	$class = $mgr['active'] ? 'futuretask' : 'canceledtask';
	if($i % 2 == 0) $class .= 'EVEN';
	$rowClasses[] = $class;
	$dispatchers[] = $mgr;
}
tableFrom($columns, $dispatchers, "width=95%", $class='mgrs', $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);

?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function editMgr(userid) {
	$.fn.colorbox({href:"manager-list.php?edit="+userid, width:"<?= $emailEditFeature ? 600 : 520 ?>", height:"400", scrolling: true, opacity: "0.3"});
}

function toggleInactiveStaff(el) {
	if($('#toggleinactive').html() == 'Hide Inactive Staff') {
		hideInactiveStaff(1);
		$('#toggleinactive').html('Show Inactive Staff');
	}
	else {
		hideInactiveStaff(0);
		$('#toggleinactive').html('Hide Inactive Staff');
	}
}
function hideInactiveStaff(hide) {
	if(hide) $('.canceledtask, .canceledtaskEVEN').hide();
	else $('.canceledtask, .canceledtaskEVEN').show();
}
<? if($sendPassword) { 
		$url = "manager-list.php?sendemail=$sendPassword";
?>
var url = '<?= $url ?>';
openConsoleWindow('passwordsender', url,600,500);
<? } ?>

function checkAndSubmit() {
<? if($emailEditFeature) { ?>
	if(!MM_validateForm('email', '', 'R', 'email', '', 'isEmail')) return;
<? } ?>
	document.userform.submit();
}

</script>
<?

include "frame-end.html";


function rightsSummary($rights) {
//	require_once "survey-fns.php";

	global $surveysAreEnabled;
	static $allRights;
	$allRights = $allRights ? $allRights :
		 array_merge(array('*cm'=>'Manage Credit Cards', '*cc'=>'Charge Credit Cards'),
									fetchKeyValuePairs("SELECT `key`, label FROM tblrights WHERE `key` LIKE '#%'"));
	$out = array();
	$rights = explode(',', substr($rights, 2));
	foreach($allRights as $consider => $label) {
		if($consider == '#rs' && !$surveysAreEnabled) continue;
		$label = str_replace(' ', '&nbsp;', $label);
		$out[] = in_array($consider, $rights) ?  $label : "<span class='strike'>$label</span>";
	}
	/*foreach(explode(',', substr($rights, 2)) as $right) {
		// ignore rights not listed in allRights
		if(!array_key_exists($right, $allRights)) continue;
		$out[] = $allRights[$right] ?  $allRights[$right] : "<span style='text-decoration:line-through;color:red;'>{$allRights[$right]}</span>";
	}*/
	return join(', ', $out);
}

function mgrRightsSummary($mgr) {
	static $mgrRights;
	$mgrRights = $mgrRights ? $mgrRights : array('*cm'=>'Manage Credit Cards', '*cc'=>'Charge Credit Cards');
	$out = array();
	if($mgr['isowner']) $out[] = '<b>Owner</b>';
	$rights = explode(',', substr($mgr['rights'], 2));
	foreach($mgrRights as $consider => $label) {
		$label = str_replace(' ', '&nbsp;', $label);
		$out[] = in_array($consider, $rights) ?  $label : "<span class='strike'>$label</span>";
	}
	//foreach(explode(',', substr($mgr['rights'], 2)) as $right)
	//	if($mgrRights[$right]) $out[] = $mgrRights[$right];
	return join(', ', $out);
}

function echoEditor() {
	global $dbhost, $db, $dbuser, $dbpass;
	$mgr = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = '{$_REQUEST["edit"]}' LIMIT 1");
	if($mgr['bizptr'] != $_SESSION["bizptr"]) $error = "Invalid request.";
	else if(!in_array($mgr['rights'][0], array('o', 'd'))) $error = "Invalid request: {$mgr['rights'][0]}.";
	if($error) {
		echo $error;
		exit;
	}
	
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$mgr["bizptr"]}' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);	
	$suppressRevenueDisplay = getUserPreference($mgr['userid'], 'suppressRevenueDisplay', $decrypted=false, $skipDefault=false);
	$showrateinclientschedulelist = getUserPreference($mgr['userid'], 'showrateinclientschedulelist', $decrypted=false, $skipDefault=false);
	$frameLayout = getUserPreference($mgr['userid'], 'frameLayout', $decrypted=false, $skipDefault=false);
	$emailFromLabel = getUserPreference($mgr['userid'], 'managerNickname', $decrypted=false, $skipDefault=true);
	$emailFromLabel = $emailFromLabel ? $emailFromLabel : trim("{$mgr['fname']} {$mgr['lname']}");
	$type = $mgr['rights'][0] == 'o' ? 'Manager' : 'Dispatcher';
	$requestCoordinator = getUserPreference($mgr['userid'], 'requestcoordinator', $decrypted=false, $skipDefault=true);

	echo "<h2>$type {$mgr['fname']} {$mgr['lname']}</h2>";
	echo "<style>
.formy tr :not(:first-child) { padding-left:15px; }
.formy td  {  font-size:1.1em; }
</style>
";
echo "<form name='userform' method='POST' action='manager-list.php'><table class='formy'>";
	labelRow('User name:', 'name', $mgr['loginid']);
	global $emailEditFeature;
	
	if($emailEditFeature) 	inputRow("User's Private Email:", 'email', $mgr['email'], null, 'emailInput');
	else labelRow("User's Private Email:", 'email', $mgr['email']);
	hiddenElement('save', $_REQUEST["edit"]);
	// don't allow self-deactivation
	$disallowDeactivateMessage = 
		staffOnlyTEST() ? '' : (
		$mgr['userid'] == $_SESSION['auth_user_id']
			? "Your current login identity is Active and you cannot deactivate your own login." : (
		$mgr['isowner'] ? "You cannot activate/deactivate designated business owners." : ''));
	if($disallowDeactivateMessage) {
		hiddenElement('active', $mgr['active']);
		echo "<tr><td colspan=2 class='tiplooks'>$disallowDeactivateMessage.";
	}
	else checkboxRow('Active:', 'active', $mgr['active']);
	checkboxRow('Suppress&nbsp;display&nbsp;of Revenue figures:', 'suppressRevenueDisplay', $suppressRevenueDisplay);
	checkboxRow('Show&nbsp;Rates&nbsp;in&nbsp;Client&nbsp;Schedules:', 'showrateinclientschedulelist', $showrateinclientschedulelist);
	inputRow('"From" name in email composers :', 'managerNickname', $emailFromLabel);
	inputRow('Temp password:', 'temppassword', $mgr['temppassword']);
	$passwordAlreadyClear = $mgr['password'] ? '' : " <span style=\"fontSize: 0.8em\">(ALREADY CLEAR)</span>";
	checkboxRow("Clear permanent	password$passwordAlreadyClear:", 'clearpassword', 0);
	

	echo "<tr><td colspan=2 class='tiplooks'>If the person&apos;s password is forgotten, assign a temporary password and email the user name and temporary password to the person.</td></tr>";
	
if($_SESSION['preferences']['enableOverdueVisitManagerSMS']) {
	$managerTextPhone = getUserPreference($mgr['userid'], "managerTextPhone");;
	//echo "<tr><td>Manager phone:</td><td>$managerTextPhone</td>";
	inputRow('Manager phone for text messages:', 'managerTextPhone', $managerTextPhone);
}

	if($_SESSION['preferences']['enablerequestassignments']) {
		checkboxRow('Can assign Office Tasks (Client Requests):', 'requestcoordinator', $requestCoordinator);
	}
	
if(staffOnlyTEST()) {
	if($mgr['isowner']) {
		hiddenElement('managedispatchers', strpos($mgr['rights'], '#md'));
		echo "<tr><td colspan=2 class='tiplooks'>This owner has access to ADMIN > Managers / Dispatchers.  (This note is Staff Only)";
	}
	else checkboxRow('Can access this page (Staff Only):', 'managedispatchers', strpos($mgr['rights'], '#md'));
}

if(staffOnlyTEST() || dbTEST('dogslife'))
	checkboxRow('Use the full window to display LeashTime:', 'frameLayout', $frameLayout, 'staffOnly', 'staffOnly');
		//"||frameLayout|Use the full window to display LeashTime|custom_boolean|fullScreen-pref-edit.php";


	echo "</table>";
	echoButton('', 'Save', 'checkAndSubmit()');//document.userform.submit()
	echo "</form><p>";
	showHistory($mgr['userid']);
	exit;
}

function getComposerContents() {
	$emailUser = fetchFirstAssoc($sql = "SELECT * FROM tbluser WHERE userid = '{$_REQUEST['sendemail']}' LIMIT 1");
	if($emailUser['bizptr'] != $_SESSION["bizptr"]) $error = "Invalid request.";
	else if(!in_array($emailUser['rights'][0], array('o', 'd'))) $error = "Invalid request: {$emailUser['rights'][0]}.";
	$lname = $emailUser['lname'];
	$fname = $emailUser['fname'];
	if(!trim("$fname$lname")) $fname = $emailUser['loginid'];
	//$email = $emailUser['email'];
	$_REQUEST['email'] = $emailUser['email'];
	$subject = 'LeashTime Info';
	$messageBody = "User name: {$emailUser['loginid']}\n{$emailUser['temppassword']}";
	list($db1, $dbhost1, $dbuser1, $dbpass1) = array_values(fetchFirstAssoc("SELECT db, dbhost, dbuser, dbpass FROM tblpetbiz WHERE bizid = {$_SESSION["bizptr"]} LIMIT 1"));
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	require 'comm-composer.php';
	exit;
}

function booleanVal($val) {
	return $val ? 1 : '0';
}

function stringVal($val) {
	return $val ? $val : sqlVal("''");
}

function saveChanges() {
	$saveuser = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname, CONCAT('[user name: ', loginid, ']')) as name FROM tbluser WHERE userid = '{$_REQUEST["save"]}' LIMIT 1");
	if($saveuser['bizptr'] != $_SESSION["bizptr"]) $error = "Invalid request.";
	else if(!in_array($saveuser['rights'][0], array('o', 'd'))) $error = "Invalid request: {$saveuser['rights'][0]}.";
	if(!$error)	{
		$mods = array('active'=>($_REQUEST['active'] ? 1 : '0'), 'temppassword'=>$_REQUEST['temppassword']);
		if($_REQUEST['clearpassword']) {
			$mods['password'] = sqlVal('NULL');
			$changeDescr[] = 'password cleared';
		}
		if($mods['active'] != $saveuser['active']) $changeDescr[] = ($mods['active'] ? 'activated' : 'deactivated');
		if($mods['temppassword'] != $saveuser['temppassword']) $changeDescr[] = 'temppassword:'.($saveuser['temppassword'] ? 'set' : 'changed');
		if(array_key_exists('email', $_REQUEST)) {
			$mods['email'] = $_REQUEST['email'];
			if($mods['email'] != $saveuser['email']) $changeDescr[] = 'email:'.($saveuser['email'] ? 'set' : 'changed');
		}
		
	if(staffOnlyTEST() && !$saveuser['isowner']) { // never set an owner's #md
		$managedispatchers = $_REQUEST['managedispatchers'];
		list($role, $rights) = explode('-', $saveuser['rights']);
		$rights = explode(',', $rights);
		if(($mdindex = array_search("#md", $rights)) !== FALSE) {
			if(!$managedispatchers) {
				unset($rights[$mdindex]);
				$mods['rights'] = "$role-".join(',', $rights);
			}
		}
		else { // #md not currently set
			if($managedispatchers) {
				$rights[] = '#md';
				$mods['rights'] = "$role-".join(',', $rights);
			}
		}
	}		
		updateTable('tbluser', $mods, "userid = {$_REQUEST["save"]}");
		$error = mysqli_error();
		if(!$error) {
			$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$saveuser["bizptr"]}' LIMIT 1");
			reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
			$savedPrefs = getUserPreferences($saveuser['userid'], array('suppressRevenueDisplay','managerNickname,showrateinclientschedulelist,managerTextPhone'));
			setUserPreference($saveuser['userid'], 'showrateinclientschedulelist', booleanVal($_REQUEST["showrateinclientschedulelist"]));
			setUserPreference($saveuser['userid'], 'suppressRevenueDisplay', booleanVal($_REQUEST["suppressRevenueDisplay"]));
			setUserPreference($saveuser['userid'], 'managerNickname', stringVal($_REQUEST["managerNickname"]));
			if($_SESSION['preferences']['enablerequestassignments']) {
				setUserPreference($saveuser['userid'], 'requestcoordinator', booleanVal($_REQUEST["requestcoordinator"]));
			}
			if(array_key_exists('managerTextPhone', $_REQUEST)) {
				setUserPreference($saveuser['userid'], 'managerTextPhone', ($_REQUEST["managerTextPhone"] ? $_REQUEST["managerTextPhone"] : null));
			}
			
			if(staffOnlyTEST()) {
				setUserPreference($saveuser['userid'], 'frameLayout', $_REQUEST["frameLayout"]);
				if($_REQUEST['frameLayout'] != $savedPrefs['frameLayout']) 
					$changeDescr[] = 'frameLayout:'.$_REQUEST["frameLayout"];
			}
			if($_REQUEST['suppressRevenueDisplay'] != $savedPrefs['suppressRevenueDisplay']) 
				$changeDescr[] = 'suppressRevenueDisplay:'.($_REQUEST["suppressRevenueDisplay"] ? 'yes' : 'no');
			if($_REQUEST['showrateinclientschedulelist'] != $savedPrefs['showrateinclientschedulelist']) 
				$changeDescr[] = 'showrateinclientschedulelist:'.($_REQUEST["showrateinclientschedulelist"] ? 'yes' : 'no');
			if($_REQUEST['managerNickname'] != $savedPrefs['managerNickname']) 
				$changeDescr[] = 'From name:'.$_REQUEST["managerNickname"];
				
			if(array_key_exists('managerTextPhone', $_REQUEST)) {
				if($_REQUEST['managerTextPhone'] != $savedPrefs['managerTextPhone']) 
					$changeDescr[] = 'Mgr phone:'.$_REQUEST["managerTextPhone"];
				
			}
				
			logChange($saveuser['userid'], 'tbluser', 'm', $note=($changeDescr ? join('|', $changeDescr) : 'no change'));
			
			require "common/init_db_common.php";
			$error = mysqli_error();
		}
	}
	if($error) $_SESSION['frame_message'] = "<span style=color:red'>ERROR: $error</span>";
	else {
		$_SESSION['frame_message'] = "Changes were saved for {$saveuser['name']}.";
		if($_REQUEST['temppassword'] && $saveuser['temppassword'] != $_REQUEST['temppassword'])
			return $_REQUEST["save"];
	}
}

function showHistory($userid) {
	$heading = "<u>History</u><p>";
	$history = fetchAssociations(
		"SELECT * FROM tblchangelog 
			WHERE itemptr = $userid
				AND itemtable = 'tbluser'
			ORDER BY time DESC", 1);
	if(!$history) {
		 echo "{$heading}No changes found.";
		 return;
	}
	require "common/init_db_common.php";
	foreach($history as $item) $actualmgrs[] = $item['user'];
	$names[0] = "System (user request)";
	$mgrs = fetchAssociationsKeyedBy("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tbluser WHERE userid IN (".join(',', array_unique($actualmgrs)).")", 'userid', 1);
	foreach($mgrs as $userid => $mgr) {
		if(trim($mgr['name'])) $names[$userid] = trim($mgr['name']);
		else if($mgr['ltstaffuserid']) 
			$names[$userid] = 
				fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = {$mgr['ltstaffuserid']} LIMIT 1");
		else $names[$userid] = "user {$userid}";
		if($mgr['ltstaffuserid']) $names[$userid] = "[LT]".$names[$userid];
	}
	foreach($history as $item) {
		$item['username'] = $names[$item['user']];
		$item['changes'] = join('<br>', explode('|', $item['note']));
		$rows[] = $item;
	}
	$columns = explodePairsLine('time|Date/Time||username|User||changes|Changes');
	echo $heading;
	tableFrom($columns, $rows, $attributes="border=1", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}

function getManagerPrefs($bizptr, $managers, $prefKeys) {
	global $surveysAreEnabled;
	$prefKeys = is_string($prefKeys) ? explode(',', $prefKeys) : (array)$prefKeys;
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizptr' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 'force');
	
	require_once "survey-fns.php";
	$surveysAreEnabled = surveysAreEnabled();
	if(mattOnlyTEST()) echo "surveysAreEnabled: [$surveysAreEnabled]";
	
	foreach($managers as $mgr)
		foreach($prefKeys as $key)
			$prefs[$mgr['userid']][$key] = getUserPreference($mgr['userid'], $key, $decrypted=false, $skipDefault=false);
	require "common/init_db_common.php";
	return $prefs;
}

function managerCommsLink($id) {
	return fauxLink('&#9993;', "document.location.href=\"manager-comms.php?id=$id\"", 1, "View messages to this manager", null, $class='fauxlinknoline');
}