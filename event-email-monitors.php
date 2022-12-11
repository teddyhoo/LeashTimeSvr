<? // event-email-monitors.php

// Combine entry editor and entire list on one page

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "time-framer-mouse.php";
require_once "event-email-fns.php";  // for $eventTypeMenu
include "weekday-grid.php";

// Determine access privs
$locked = locked('o-');
extract(extractVars('userptr', $_REQUEST));

$saved = false;

if($_GET['initialize']) {
	// find first owner
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require_once "common/init_db_common.php";
	$owner = fetchFirstAssoc("SELECT * FROM tbluser WHERE bizptr = {$_SESSION["bizptr"]} AND active = 1 ORDER BY userid LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	$userptr = $owner['userid'];
	$oldEntries = fetchAssociations("SELECT * FROM relstaffnotification WHERE userptr = $userptr");
	if($oldEntries) {
		$message = "{$owner['fname']} {$owner['lname']}  ({$owner['userid']} {$owner['loginid']}) already set up.";
	}
	else {
		deleteTable('relstaffnotification', "userptr = $userptr", 1);
		$row = array('daysofweek'=>'Every Day', 'timeofday'=>'12:00 am-11:59 pm', 'eventtypes'=>'i,r,c,e,t,k',	'email'=>$owner["email"], 'userptr'=>$userptr);
		insertTable('relstaffnotification', $row, 1);
	}	
		logChange($userptr, 'eventmonitor', 'm', "{$owner['lname']}  ({$owner['userid']} {$owner['loginid']}) initialized.");
		$message = "{$owner['lname']}  ({$owner['userid']} {$owner['loginid']}) initialized.";
	}
}

if($_POST) {
	$oldEntries = fetchAssociations("SELECT * FROM relstaffnotification WHERE userptr = $userptr");
	$numNewEntries = 0;
	deleteTable('relstaffnotification', "userptr = $userptr", 1);
	$events = array();
	foreach($_POST as $key => $val)
		if(strpos($key, 'event_') === 0 && $val) $events[] = substr($key, strlen('event_'));
	$events = join(',', $events);
	if($events)	foreach($_POST as $key => $val) {
		if(strpos($key, 'daysofweek_') === 0 && $val) {
			$i = substr($key, strlen('daysofweek_'));
			$row = array('daysofweek'=>$val, 'timeofday'=>$_POST["timeofday_$i"], 'eventtypes'=>$events, 'email'=>$_POST["email"], 'userptr'=>$userptr);
			$numNewEntries += 1;
			insertTable('relstaffnotification', $row, 1);
}	
		};
	}
	logChange($userptr, 'eventmonitor', 'm', "[user: $userptr] before: ".count($oldEntries)." entries. after: $numNewEntries entries.");
	$saved = true;
}

if($userptr) {
	
	$entries = fetchAssociations("SELECT * 
															FROM relstaffnotification 
															WHERE userptr = $userptr ORDER BY daysofweek, timeofday");
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$user = fetchFirstAssoc("SELECT * FROM tbluser  WHERE userid = $userptr LIMIT 1");
	list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
	include "common/init_db_petbiz.php";
	
	$userEmail = strpos($user['rights'], 'o-') === 0 || strpos($user['rights'], 'd-') === 0
							? $user['email']
							: fetchRow0Col0("SELECT email FROM tblprovider WHERE userid = $userptr LIMIT 1");
}
else $entries = array();

$pageTitle = "Event Notification Preferences (Event Email Monitors)";
$breadcrumbs = "<a href='comm-prefs.php'>Communication Preferences</a> ";

// ***************************************************************************
include "frame.html";
if($message) echo "<p class='tiplooks fontSize1_1em'>$message</p>";
?>
Use this page to schedule which staff members to notify by email when certain system events occur.
<p>
<?
if(!in_array('relstaffnotification', fetchCol0("SHOW TABLES"))) {
	echo "<font color=red>This site is not yet set up to process Event Notifications.</font>";
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
	include "frame-end.html";
	exit;
}


$providers = fetchAssociationsKeyedBy("SELECT *, CONCAT_WS(' ', fname, lname) as name, userid
																FROM tblprovider
																WHERE active = 1 and userid IS NOT NULL
																ORDER BY lname, fname", 'userid');

$inactiveProviders = fetchAssociationsKeyedBy("SELECT *, CONCAT_WS(' ', fname, lname) as name, userid
																FROM tblprovider
																WHERE active = 0 and userid IS NOT NULL
																ORDER BY lname, fname", 'userid');



list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
include "common/init_db_common.php";
$allProviderLoginIdsByUserId = fetchKeyValuePairs("SELECT userid, loginid FROM tbluser WHERE active = 1 AND bizptr = {$_SESSION['bizptr']}  AND rights LIKE 'p-%'");

$staffUserConstraint = $_SESSION['staffuser'] ? '' : "AND ltstaffuserid = 0";

$managers = fetchAssociationsKeyedBy("SELECT CONCAT(loginid, ' (', CONCAT_WS(' ', fname, lname), ')') as label, userid, email, ltstaffuserid
																FROM tbluser 
																WHERE active = 1 
																	AND bizptr = {$_SESSION['bizptr']} 
																	AND rights LIKE 'o-%'
																ORDER BY lname, fname, loginid", 'label');
$inactiveManagers = fetchAssociationsKeyedBy("SELECT CONCAT(loginid, ' (', CONCAT_WS(' ', fname, lname), ')') as label, userid, email, ltstaffuserid
																FROM tbluser 
																WHERE active = 0 
																	AND bizptr = {$_SESSION['bizptr']} 
																	AND rights LIKE 'o-%'
																ORDER BY lname, fname, loginid", 'label');

$dispatchers = fetchAssociationsKeyedBy("SELECT CONCAT(loginid, ' (', CONCAT_WS(' ', fname, lname), ')') as label, userid, email, ltstaffuserid
																FROM tbluser 
																WHERE active = 1 
																	AND bizptr = {$_SESSION['bizptr']} 
																	AND rights LIKE 'd-%'
																ORDER BY lname, fname, loginid", 'label');
$inactiveDispatchers = fetchAssociationsKeyedBy("SELECT CONCAT(loginid, ' (', CONCAT_WS(' ', fname, lname), ')') as label, userid, email, ltstaffuserid
																FROM tbluser 
																WHERE active = 0 
																	AND bizptr = {$_SESSION['bizptr']} 
																	AND rights LIKE 'd-%'
																ORDER BY lname, fname, loginid", 'label');
																
$allProviderIds = array_merge($providers, $inactiveProviders);
$missingProviders = array();
if($allProviderIds)
	$missingProviders = fetchAssociationsKeyedBy("SELECT CONCAT(loginid, ' (', IF(CONCAT_WS(' ', fname, lname) = ' ', '-no name-', CONCAT_WS(' ', fname, lname)), ')') as label, userid, email, ltstaffuserid
																FROM tbluser 
																WHERE bizptr = {$_SESSION['bizptr']} 
																	AND rights LIKE 'p-%'
																	AND userid NOT IN (".join(',', array_keys($allProviderIds)).")
																ORDER BY lname, fname, loginid", 'label');

list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
include "common/init_db_petbiz.php";
?>
<form method='POST' name='entryeditor'>
<?
$options = "\n<OPTGROUP label='Managers'>";
foreach($managers as $optLabel => $manager) {
	if($manager['ltstaffuserid'] && !$_SESSION['staffuser']) continue;
	$listedManagerUserIds[] = $manager['userid'];
	$managerLabels[$manager['userid']] = "$optLabel (Manager)";
	$optValue = $manager['userid'];
	$checked = $optValue == $userptr ? 'SELECTED' : '';
	$options .= "\n\t<option value='$optValue' $checked>{$manager['label']}</option>\n";
}
$options .= "\n</OPTGROUP>";
$options .= "\n<OPTGROUP label='Dispatchers'>";
foreach($dispatchers as $optLabel => $dispatcher) {
	$listedManagerUserIds[] = $dispatcher['userid'];
	$managerLabels[$dispatcher['userid']] = "$optLabel (Dispatcher)";
	$optValue = $dispatcher['userid'];
	$checked = $optValue == $userptr ? 'SELECTED' : '';
	$options .= "\n\t<option value='$optValue' $checked>{$dispatcher['label']}</option>\n";
}
$options .= "\n</OPTGROUP>";
$options .= "\n<OPTGROUP	label='Sitters'>";
$providerLabels = array();
foreach($providers as $prov) {
	$checked = $prov['userid'] == $userptr ? 'SELECTED' : '';
	$optValue = safeValue($prov['userid']);
	$optLabel = "{$allProviderLoginIdsByUserId[$prov['userid']]} - {$prov['nickname']} - ({$prov['name']})";
	$providersLabels[$prov['userid']] = "{$prov['name']} (Sitter)";
	$options .= "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
}
$options .= "\n</OPTGROUP>";
if($inactiveManagers) {
	$options .= "\n<OPTGROUP	label='Inactive Managers'>";
	foreach($inactiveManagers as $optLabel => $person) {
		$checked = $person['userid'] == $userptr ? 'SELECTED' : '';
		$optValue = safeValue($person['userid']);
		$managerLabels[$person['userid']] = "<i>{$person['label']} (Manager)</i>";
		$options .= "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
	}
}
if($inactiveDispatchers) {
	$options .= "\n<OPTGROUP	label='Inactive Dispatchers'>";
	foreach($inactiveDispatchers as $optLabel => $person) {
		$checked = $person['userid'] == $userptr ? 'SELECTED' : '';
		$optValue = safeValue($person['userid']);
		$managerLabels[$person['userid']] = "<i>{$person['label']} (Dispatcher)</i>";
		$options .= "\n\t<option value='$optValue' $checked title='inactive.'>$optLabel</option>\n";
	}
}
if($inactiveProviders) {
	$options .= "\n<OPTGROUP	label='Inactive Sitters'>";
	$providerLabels = array();
	foreach($inactiveProviders as $prov) {

		$checked = $prov['userid'] == $userptr ? 'SELECTED' : '';
		$optValue = safeValue($prov['userid']);
		$optLabel = "<i>{$allProviderLoginIdsByUserId[$prov['userid']]} - {$prov['nickname']} - ({$prov['name']})</i>";
		$providersLabels[$prov['userid']] = "<i>{$prov['name']} (Sitter)</i>";
		$options .= "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
	}
}

if($missingProviders) 
	foreach($missingProviders as $prov)
		$providersLabels[$prov['userid']] = "<i>{$prov['label']} (Sitter)</i>";


$options .= "\n</OPTGROUP>";
$noSelection = !$userptr ? 'SELECTED' : '';
$options = "<option value='' $noSelection>-- Select a Staff Member --</option>".$options;
	
	
// ************************	
selectElement('Active Staff:', 'userptr', $userptr, $options, $onChange='selectStaff(this)');
if($userptr) {																
echo " ";

// entry editor
$controlButtons = "<div style='float:right;'>"
									. echoButton('saveButton', 'Save', 'checkAndSubmit()', null, null, 1)
									. " "
									. echoButton('cancelButton', 'Cancel', 'document.location.href="event-email-monitors.php"', null, null, 1)
									. "</div>";
$userName = $providersLabels[$userptr] ? $providersLabels[$userptr] : $managerLabels[$userptr];
if($saved) echo "<p style='text-align:center;color:green;'>$userName's notification preferences have been saved.</p>";
echo "<p><table align='center' style='border:solid black 1px;'>
<tr>
<td colspan=2 style='text-align:center;font-size:1.2em;font-weight:bold;'>$userName $controlButtons</td></tr><tr><td valign='TOP'><table>";
$constraints = array();
$prettyNames = array();
foreach($entries as $number => $line) {
	$number++;
	echoLine($number, $line);
	$constraints[] = "'daysofweek_$number', 'timeofday_$number', 'inseparable'";
	$prettyNames[] = "'daysofweek_$number', 'Days of Week #$number', 'timeofday_$number', 'Time of Day #$number'";
}
for($number=count($entries)+1; $number < count($entries)+6; $number++) {
	echoLine($number, array());
	$constraints[] = "'daysofweek_$number', 'timeofday_$number', 'inseparable'";
	$prettyNames[] = "'daysofweek_$number', 'Days of Week #$number', 'timeofday_$number', 'Time of Day #$number'";
}
$constraints = join(', ', $constraints);
$prettyNames = join(', ', $prettyNames);

echo "<tr><td colspan=2>";
$email = $entries ? $entries[0]['email'] : $userEmail;
labeledInput('Email:', 'email', $email, null, 'emailInput');
echo "</td></tr>";
echo "</table></td><td style='vertical-align:top;padding-left:20px;padding-top:5px;'><b>Event Types</b><br>";
$events = $entries ? $entries[0]['eventtypes'] : '';
$events = explode(',', $events);
foreach(getEventTypeMenu() as $val => $label) {
	labeledCheckbox($label, 'event_'.$val, in_array($val, $events), null, null, null, true);
	echo "<br>";
}
echo "</td></tr></table>";
echo "</form>";
} // if userptr
// schedule of Event Notifications

$prefs = fetchAssociations("SELECT * FROM relstaffnotification ORDER BY daysofweek, timeofday");
$days = array(array(),array(),array(),array(),array(),array(),array());
foreach($prefs as $pref)
	for($d=0;$d<7;$d++)
		if(daysOfWeekIncludes($pref['daysofweek'], $d))
			$days[$d][] = $pref;

$rows = array();
$rows[] = array('#CUSTOM_ROW#'=>"<tr><td style='font-weight:bold;font-size:1.2em;text-align:center;' class='sortableListCell' colspan=4>Schedule</td></tr>");
$daysOfWeek = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');

foreach($daysOfWeek as $d => $day) {
	$rows[] = array('#CUSTOM_ROW#'=>"<tr><td style='background:yellow;font-weight:bold;text-align:center;' class='sortableListCell' colspan=4>$day</td></tr>");
	if(!$days[$d]) 
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td style='text-align:center;' class='sortableListCell' colspan=4>No notifications scheduled.</td></tr>");
	else {
		usort($days[$d], 'timeSort');
		foreach($days[$d] as $pref) {
			$userptr = $pref['userptr'];
			//if(!in_array($userptr, $listedManagerUserIds)) continue;
			$row = array();
			$label = $managerLabels[$userptr] ? $managerLabels[$userptr] : ($providersLabels[$userptr] ? $providersLabels[$userptr] : "user: $userptr");
			$row['staff'] = fauxLink("$label", "document.location.href=\"event-email-monitors.php?userptr=$userptr\"",1);
			$row['daysofweek'] = "(".$pref['daysofweek'].")";
			$row['timeofday'] = $pref['timeofday'];
			$events = array();
			foreach(explode(',', $pref['eventtypes']) as $key) $events[] = $eventTypeMenu[$key];
			$row['events'] = join(', ', $events);
			$rows[] = $row;
		}
	}
}
$columns = explodePairsLine('staff| ||daysofweek| ||timeofday| ||events| ');
tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');

function timeSort($a, $b) {
	$t1 = strtotime(substr($a['timeofday'], 0, strpos($a['timeofday'], '-')));
	$t2 = strtotime(substr($b['timeofday'], 0, strpos($b['timeofday'], '-')));
	if($t1 == $t2) return 0;
	return $t1 < $t2 ? -1 : 1;
}

function daysOfWeekIncludes($daysOfWeek, $dayNumber) { // dayNumber: 0-6, 0=Sunday
	if($daysOfWeek == 'Every Day') return true;
	else if($daysOfWeek == 'Weekends') return $dayNumber == 0 || $dayNumber == 6;
	else if($daysOfWeek == 'Weekdays') return $dayNumber > 0 && $dayNumber < 6;
	else {
		$allDays = array('Su','M','Tu','W','Th','F','Sa');
		$darray = explode(',', $daysOfWeek);
		for($i=0; $i < count($darray); $i++) 
			if($allDays[$dayNumber] == $darray[$i]) return true;
	}
}

// end schedule of Event Notifications
function echoLine($number, $line) {
	global $defaultTimeFrame;
	echo "<tr>";
	echo "\n<td style='padding:2px;width:100px;'>Days of week: ";
	buttonDiv("div_daysofweek_$number","daysofweek_$number","showWeekdayGridInContentDiv(event, \"div_daysofweek_$number\")",
					($line['daysofweek'] ? $line['daysofweek'] : ''));
	echo "</td>";
	echo "\n<td style='padding:2px;width:110px;'>Time of Day: ";
	buttonDiv("div_timeofday_$number", "timeofday_$number", "showTimeFramerInContentDiv(event, \"div_timeofday_$number\")",
	          ($line['timeofday'] ? $line['timeofday'] : $defaultTimeFrame));
	echo "</td>";
	
	echo "<td style='width:22px;padding-right:0px;cursor:pointer;vertical-align:bottom;' title='Clear this line.'>".
	      "<img src='art/delete.gif' height=15 width=15 border=0 onClick=\"clearLine($number)\"></td>\n<td>";
	
	echo "</tr>";
}

function buttonDiv($divid, $formelementid, $onClick, $label, $value='', $extraStyle=null) {
	$class = $class ? "class = '$class'" : '';
	echo 
	  "\n<div id='$divid' style='cursor:pointer;border: solid darkgrey 1px;height:15px;padding-left: 2px;overflow:hidden;$extraStyle' 
	onClick='$onClick'>$label</div>".
	  hiddenElement($formelementid, $value);
}	

makeTimeFramer('timeFramer', 'narrow');
makeWeekdayGrid('weekdays');
?>

<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('userptr', 'Active Staff', 'email', 'Email' <?= $prettyNames ? ", $prettyNames" : '' ?>);
function selectStaff(sel) {
	document.location.href='event-email-monitors.php?userptr='+sel.options[sel.selectedIndex].value;
}

function checkAndSubmit() {
	setButtonDivElements();
	//var conflict = document.getElementById('cancellation_1').checked && document.appteditor.completed.checked;
	var badTimes = checkForBadTimes();
	if(MM_validateForm(
		'userptr', '', 'R',
		'email', '', 'R',
		'email', '', 'isEmail' <?= $constraints ? ", $constraints" : '' ?>,
		badTimes, '', 'MESSAGE'
		)) {
		document.entryeditor.submit();
	}
}

function mouseCoords(ev){  // for weekday widgets
	if(ev.pageX || ev.pageY){
		return {x:ev.pageX, y:ev.pageY};
	}
	return {
		x:ev.clientX + document.body.scrollLeft - document.body.clientLeft,
		y:ev.clientY + document.body.scrollTop  - document.body.clientTop
	};
}

function checkForBadTimes() {
	var els = document.getElementsByTagName('input');
	for(var n=0; n < els.length; n++) {
		var id = els[n].id;
		if(id.indexOf('timeofday') == 0 || id.indexOf('daysofweek') == 0)
			if(els[n].value  
					 && els[n].value.indexOf('pm') != -1
					 && els[n].value.indexOf('pm') < els[n].value.indexOf('am'))
					return "No time of day may start in the PM and end in the AM (the next day)";
	}
}

function setButtonDivElements() {
	var els = document.getElementsByTagName('input');
	for(var n=0; n < els.length; n++) {
		var id = els[n].id;
		if(id.indexOf('timeofday') == 0 || id.indexOf('daysofweek') == 0)
			els[n].value = 
				document.getElementById('div_'+id).innerHTML;
	}
}

function clearLine(number) {
	document.getElementById('div_daysofweek_'+number).innerHTML = '';
	document.getElementById('div_timeofday_'+number).innerHTML = '';
}
<? 
dumpTimeFramerJS('timeFramer');
dumpWeekDayGridJS('weekdays');

?>
</script>

<?
// ***************************************************************************
echo "<br><img src='art/spacer.gif' width=1 height=300>";
include "frame-end.html";
?>
