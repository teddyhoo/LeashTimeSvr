<? // google-cal-prefs.php
// Edit email prefs for one user at a time
// params: id - clientid
set_include_path(get_include_path().':/var/www/prod/ZendGdata-1.11.6/library:');
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
require_once "google-cal-fns.php";
require_once "encryption.php";

// Determine access privs
$locked = locked('o-');


if($_REQUEST['testsittergoogleuser']) {
	$sitterUserId = $_REQUEST['testsittergoogleuser'];
	$myProvider = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE userid = $sitterUserId");
	if($myProvider) $user = getGoogleCreds($_SESSION['auth_user_id']);
	else {
		echo "Not one of your sitters.";
		exit;
	}
	$result = testUser($user);
	echo $result ? "ERROR: ".$result : "Found {$user['username']}'s calendar.";
	//print_r($_REQUEST);
	exit;
}

if($_REQUEST['testgoogleuser']) {
	if($_REQUEST['testgoogleuser'] == 1) $user = getGoogleCreds($_SESSION['auth_user_id']);
	else $user = array('username'=>$_REQUEST['testgoogleuser'], 'password'=>$_REQUEST['password']);
	$result = testUser($user);
	echo $result ? "ERROR: ".$result : "Found {$user['username']}'s calendar.";
	//print_r($_REQUEST);
	exit;
}

if($_POST) {
	extract(extractVars('pushUnassignedToGoogleCalendar,googleusername,removeInfo,allowSittersToUseGoogleCalendar', $_POST));
	// STEP 1: Drop info
	if($_POST['action'] == 'requestPermission') {
		$token = getGoogleCalCredsTokenForGoogleUser($googleusername);
}
		saveGoogleCreds($_SESSION['auth_user_id'], $googleusername, $token); // may be NULL
		if(!$token) { // don't invalidate previously set token for calendar
			$googleDevClientID = $installationSettings['googleDevClientID'];
			$scope = urlencode("https://www.googleapis.com/auth/calendar"); // email%20profile
			$args[] = "scope=$scope";
			$args[] = "client_id=$googleDevClientID";
			$args[] = "access_type=offline";
			$args[] = "state=mgr|{$_SESSION['auth_user_id']}";
			$args[] = "redirect_uri=".urlencode("https://leashtime.com/oauth2callback.php");
			$args[] = "response_type=code";
			$args[] = "approval_prompt=force";
			$args[] = "login_hint=$googleusername";
			$url = "https://accounts.google.com/o/oauth2/auth?".join('&', $args);
			header("Location: $url");
			exit;
		}
		else $message = 'Found that permission has already been granted.';
	}
	else if($removeInfo) {
		dropGoogleCreds($_SESSION['auth_user_id']);
		$message = "Calendar credentials cleared.  You will receieve no more updates to your calendar.";
	}
	if($pushUnassignedToGoogleCalendar) {
		if(!($error = testUser(getGoogleCreds($_SESSION['auth_user_id'])))) {
			saveGoogleCreds($_SESSION['auth_user_id'], $googleusername, $googlepassword);
			setUserPreference($_SESSION['auth_user_id'], 'pushUnassignedToGoogleCalendar', $pushUnassignedToGoogleCalendar);
		}
	}
	if(!$allowSittersToUseGoogleCalendar) setPreference('googleCalendarEnabledSitters', null);
	else {
		setPreference('allowSittersToUseGoogleCalendar', $allowSittersToUseGoogleCalendar);
		foreach($_POST as $key => $val)
			if(strpos($key, 'user_') !== FALSE) $chosen[] = substr($key, strlen('user_'));
		setPreference('googleCalendarEnabledSitters', ($chosen ? join(',', $chosen) : null));
	}
	if(!$error) $message = "Preferences saved.";
}


$pageTitle = "Google Calendar Preferences";
$breadcrumbs = "<a href='comm-prefs.php' title='Communication Preferences'>Communication Preferences</a> - <a href='preference-list.php' title='Business Preferences'>Business Preferences</a> ";
include "frame.html";
if($error) echo "<p class='warning'>$error</p>";
else if($message) echo "<p class='pagenote'>$message</p>";
echoButton('', 'Save', 'save()');
$googleCreds = getGoogleCreds($_SESSION['auth_user_id']);
if($googleCreds['username'] || $googleCreds['password'])
	echoButton('', 'Remove Google Credentials', 'removeCreds()', 'HotButton', 'HotButtonDown', 0, 'Stop receiving updates to your Google calendar.');
?>
<form name='propertyeditor' method='POST'>
<table>
<?
$currentSetting = getUserPreference($_SESSION['auth_user_id'], 'pushUnassignedToGoogleCalendar');
selectRow('Put Unassigned visits on my Google Calendar', 'pushUnassignedToGoogleCalendar', $currentSetting, array('yes'=>1, 'no'=>0), 'unassignedToggled(this)');

inputRow('Google user name', 'googleusername', $googleCreds['username']);
$grantLink = fauxLink('Grant Calendar Permission', 'grantPermission()', 1, 'Grant LeashTime permission to manage visit events on your calendar.');
if($googleCreds['password']) { ?>
<tr><td colspan=2 style='font-style:italics'>(You granted Calendar access permission to LeashTime previously.)</td></tr>
<? } ?>
<tr><td colspan=2 style='font-style:italics'><?= $grantLink ?></td></tr>
<?
hiddenElement('saveduser', $googleCreds['username']);
hiddenElement('passwordset', $googleCreds['password'] ? 1 : 0);
hiddenElement('removeInfo', 0);
hiddenElement('action', 0);
labelRow('', '', 
					fauxLink('Test Login Info Shown', 'testLogin()', true, 'Check to make sure a calendar is available for this Google user.', 'testlogin'),
					null, null, null, null, 'raw');
labelRow('', '', 
					fauxLink('Test Currently Saved Login Info', 'testLogin(1)', true, 'Check to make sure a calendar is available for the currently saved Google user login info.', 'testsavedlogin'),
					null, null, null, null, 'raw');
$currentSetting = getPreference('allowSittersToUseGoogleCalendar');
selectRow('Allow sitters to view visits on their Google Calendars', 'allowSittersToUseGoogleCalendar', $currentSetting, array('yes'=>1, 'no'=>0), 'allowSittersChanged(this)');
$showSitters = $currentSetting ? 'inline' : 'none';
?>
</table>
<style>
.disabled {font-style:italic; color: gray;}
</style>
<div id='sitterSection' style='display:<?= $showSitters ?>'>
<h3 style='font-size:1.5em;fontweight:bold;'>Sitters to Receive Calendar Updates</h3>
<?
$sitters = fetchAssociations(
	"SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name, userid 
		FROM tblprovider 
		WHERE active
		ORDER BY name");
$googleEnabledSitters = getPreference('googleCalendarEnabledSitters');
foreach($sitters as $sitter) if($sitter['userid']) $anyUserids = 1;
if($anyUserids) {
	fauxLink('Select All', "selectAll(1)", 0, 'Allow all of the sitters shown to receive Google Calendar updates.');
	echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1 />";
	fauxLink('Deselect All', "selectAll(0)", 0, 'Allow none of the sitters shown to receive Google Calendar updates.');
}
if($googleEnabledSitters) $googleEnabledSitters = explode(',', $googleEnabledSitters);
$googleCreds = fetchKeyValuePairs("SELECT userptr, value FROM tbluserpref WHERE property = 'googlecreds'");
foreach($sitters as $i => $sitter) {
	$enabled = in_array($sitter['userid'], (array)$googleEnabledSitters);
	$creds = $googleCreds[$sitter['userid']];
	if($creds) $creds =  lt_decrypt($creds);	

	if(!$sitter['userid']) $sitters[$i]['calendarURL'] = "<span class='disabled'>No login for sitter.</span>";
	else {
		$testLink = !$creds ? '' : fauxLink('Test', "testSitterLogin({$sitter['userid']})", 1, 'Check to make sure a calendar is available for this sitter.');
		$sitters[$i]['calendarURL'] = $creds ?  $testLink.' '.substr($creds, 0, strpos($creds, '#*SEPR*#')) : 'No Calendar credentials supplied by sitter.';
		$sitters[$i]['cb'] = labeledCheckbox('', "user_{$sitter['userid']}", $enabled, '', '', '', '', 'noecho');
		$sitters[$i]['name'] = "<label for='user_{$sitter['userid']}'>{$sitter['name']}</label>";
	}		
}
$columns = explodePairsLine('cb| ||name|Sitter||calendarURL|Google Calendar');
tableFrom($columns, $sitters, $attributes=null, $class=null);
?>
</div>
</form>
<?
echoButton('', 'Save', 'save()');
if(!$show) { ?>
<p><img src='art/spacer.gif' width=1 height=300>
<?
}
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function save() {
	document.propertyeditor.submit();
}

function allowSittersChanged(el) {
	var allow = el.options[el.selectedIndex].value;
	document.getElementById('sitterSection').style.display = allow == 1 ? 'inline' : 'none';
}

function selectAll(onoff) {
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox')
			els[i].checked = onoff;
}

function testLogin(saved) {
	var username = saved ? 1 : document.getElementById('googleusername').value;
	var password = document.getElementById('googlepassword').value;
	if(!(saved || (username && password))) {
		alert('You must supply a user name and password first.');
		return;
	}
	password = username == 1 ? '' : "&password="+password;
	ajaxGetAndCallWith('google-cal-prefs.php?testgoogleuser='+username+password, 
		function(val, text) {alert(text);}, 'feh');
}

function testSitterLogin(userid) {
	ajaxGetAndCallWith('google-cal-prefs.php?testsittergoogleuser='+userid, 
		function(val, text) {alert(text);}, 'feh');
}

function removeCreds() {
	document.getElementById('removeInfo').value=1;
	if(!confirm('Remove these credentials?')) return;
	document.propertyeditor.submit();
}

function grantPermission() {
	var username = document.getElementById('googleusername').value;
	if(!username) {
		alert('You must supply a Google user name.');
		return;
	}
	document.getElementById('action').value = 'requestPermission';
	document.propertyeditor.submit();
}

</script>


<?


// ***************************************************************************
include "frame-end.html";
