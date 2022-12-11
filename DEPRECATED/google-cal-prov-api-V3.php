<? // google-cal-prov-api-V3.php
// Edit email prefs for logged in provider
// params: id - clientid

set_include_path(get_include_path().':/var/www/prod/ZendGdata-1.11.6/library:');
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
require_once "google-cal-fns.php";





// Determine access privs
$locked = locked('p-');


if($_REQUEST['testgoogleuser']) { // TBD
	if($_REQUEST['testgoogleuser'] == 1) $user = getGoogleCreds($_SESSION['auth_user_id']);
	else $user = array('username'=>$_REQUEST['testgoogleuser'], 'password'=>$_REQUEST['password']);
	$result = testUser($user);
	echo $result ? "ERROR: ".$result : "Found {$user['username']}'s calendar.";
	//print_r($_REQUEST);
	exit;
}

if($_GET['error']) { //oauthcallback.php
	// STEP 2: process request failure
	$error = $_GET['error'] == 'access_denied' ? 'Access denied.' : "Google error: {$_GET['error']}";
}
else if($_GET['state']) { //oauthcallback.php
	// process request success.  
	//	Ensure user id matches logged in user id and save 'password' (actually a code)
	$state = explode('|', $_GET['state']);
	if(count($state) == 2 && $state[0] == 'sitter') {
		$userid = $state[1];
		if($_SESSION['auth_user_id'] != $userid)
			$error = "User ID mismatch.";
		else if($_GET['code']) {
			// STEP 2: process request success.  
			//$user = getGoogleCreds($_SESSION['auth_user_id']);
			//saveGoogleCreds($_SESSION['auth_user_id'], $user['username'], $_GET['code']);
			setGoogleAccessToken($_SESSION['auth_user_id'], "CODE:".$_GET['code']);
			$message = "Permission granted.  Thank you!";
		}
		/*else if($_GET['refresh_token']) {
			// STEP 2: process request success.  
			$user = getGoogleCreds($_SESSION['auth_user_id']);
			saveGoogleCreds($_SESSION['auth_user_id'], $user['username'], $_GET['code']);
			$message = "Permission granted.  Thank you!";
		}
		else if($_GET['code']) {
			// STEP 3: obtain refresh token
			$googleDevClientID = $installationSettings['googleDevClientID'];
			$googleDevClientSecret = $installationSettings['googleDevClientSecret'];
			$args['code'] = $_GET['code'];
			$args['client_id'] = $googleDevClientID;
			$args['client_secret'] = $googleDevClientSecret;
			$args['redirect_uri'] = "https://leashtime.com/oauth2callback.php";
			$args['grant_type'] = "authorization_code";
			
			//Returns empty string $jsonResult = httpRequest($host='accounts.google.com', $port=443, $method='POST', $path='/o/oauth2/token', $args);
			// Error: Empty reply from server $jsonResult = sendPOSTviaCurl('accounts.google.com/o/oauth2/token', 443, $args);
			// Error: BAD REQUEST $jsonResult = sendPOSTviaCurl('https://accounts.google.com/o/oauth2/token', 443, $args);
			// Error: sending plain HTTP rather than SSL $jsonResult = sendPOSTviaCurl('leashtime.com/login-page.php', 443, $args);
			// WORKS! $jsonResult = sendPOSTviaCurl('https://leashtime.com/login-page.php', 443, $args);
			$jsonResult = sendPOSTviaCurl('accounts.google.com/o/oauth2/token', 443, $args);
			echo "\nJSON:\n".print_r($jsonResult, 1);
		}*/
	}
	else $error = "Bad state returned. ".print_r($state,1);
}

if($_POST) {
	extract(extractVars('googleusername,removeInfo', $_POST));
	// STEP 1: save google name and request perms
	if($_POST['action'] == 'requestPermission') { 
		saveGoogleCreds($_SESSION['auth_user_id'], $googleusername, null);
		$googleDevClientID = $installationSettings['googleDevClientID'];
		$scope = urlencode("https://www.googleapis.com/auth/calendar"); // email%20profile
		$args[] = "scope=$scope";
		$args[] = "client_id=$googleDevClientID";
		$args[] = "access_type=offline";
		$args[] = "state=sitter|{$_SESSION['auth_user_id']}";
		$args[] = "redirect_uri=".urlencode("https://leashtime.com/oauth2callback.php");
		$args[] = "response_type=code";
		$args[] = "approval_prompt=force";
		$args[] = "login_hint=$googleusername";
		$url = "https://accounts.google.com/o/oauth2/auth?".join('&', $args);
		header("Location: $url");
		exit;
	}
	// STEP 1: Drop info
	else if($removeInfo) {
		dropGoogleCreds($_SESSION['auth_user_id']);
		$message = "Calendar credentials cleared.  You will receieve no more updates to your calendar.";
	}
	// STEP 1: Test saved info
	else {
		$user = getGoogleCreds($_SESSION['auth_user_id']);
		if($error = testUser($user)) {
			if(strpos($error, 'BadAuthentication') && !strcmp($googlepassword, strtoupper($googlepassword)))
				$error .= " (is the Caps Lock On?)";
			$error = "ERROR: ".$error;
		}
		else {
			$message = "Found {$user['username']}'s calendar.";
		}
	}
}


$pageTitle = "Google Calendar Preferences";
$extraHeadContent = "<style>#instructions li {color: darkblue;} p, .readable {font-size:1.1em;}</style>";
include "frame.html";
if($error) echo "<p class='readable warning'>$error</p>";
else if($message) echo "<p class='readable pagenote'>$message</p>";
//echoButton('', 'Save', 'save()');
echo "<img src='art/spacer.gif' width=10>";
$googleCreds = getGoogleCreds($_SESSION['auth_user_id']);
if($googleCreds['username'] || $googleCreds['password'])
	echoButton('', 'Remove Google Credentials', 'removeCreds()', 'HotButton', 'HotButtonDown', 0, 'Stop receiving updates to your Google calendar.');
?>
<p>If you would like your pet care assignments to appear on your Google Calendar, please 
<ol class='readable' id='instructions'><li>Supply your Google login name below
<li>Click the Grant Calendar Permission link.
<li>You will be taken to a Google page where you may be asked to login before being asked if you want to grant LeashTime permission to put visits on your Calendar 
<li>Please click the "Accept" button and you will be returned to this page
</ol>
<p>Your Google password is not viewable by your employer.</p>
<form name='propertyeditor' method='POST'>
<table class='readable'>
<?

inputRow('1. Google user name', 'googleusername', $googleCreds['username'], '', 'emailInput');
//passwordRow('Google password <i> (only if not set and/or changing user name)</i>', 'googlepassword');
$grantLink = fauxLink('Grant Calendar Permission', 'grantPermission()', 1, 'Grant LeashTime permission to manage visit events on your calendar.');
if($googleCreds['password']) { ?>
<tr><td colspan=2 style='font-style:italics'>(You granted Calendar access permission to LeashTime previously.)</td></tr>
<? } ?>
<tr><td colspan=2 style='font-style:italics'>2. <?= $grantLink ?></td></tr>
<?
hiddenElement('saveduser', $googleCreds['username']);
hiddenElement('action',  0);
hiddenElement('removeInfo', 0);
/*labelRow('', '', 
					fauxLink('Test Login Info Shown', 'testLogin()', true, 'Check to make sure a calendar is available for this Google user.', 'testlogin'),
					null, null, null, null, 'raw');*/
if($googleCreds['username'] || $googleCreds['password'])
	labelRow('', '', 
					fauxLink('Test Currently Saved Login Info', 'testLogin(1)', true, 'Check to make sure a calendar is available for the currently saved Google user login info.', 'testsavedlogin'),
					null, null, null, null, 'raw');
?>
</table>
</form>

<p><img src='art/spacer.gif' width=1 height=300>

<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function grantPermission() {
	var username = document.getElementById('googleusername').value;
	if(!username) {
		alert('You must supply a Google user name.');
		return;
	}
	document.getElementById('action').value = 'requestPermission';
	document.propertyeditor.submit();
}

function removeCreds() {
	document.getElementById('removeInfo').value=1;
	if(!confirm('Remove these credentials?')) return;
	document.propertyeditor.submit();
}

function testLogin(saved) {
	var username = saved ? 1 : document.getElementById('googleusername').value;
	if(!(saved || username)) {
		alert('You must supply a user name.');
		return;
	}
	password = username == 1 ? '' : "&password="+password;
	ajaxGetAndCallWith('google-cal-prov.php?testgoogleuser='+username+password, 
		function(val, text) {alert(text);}, 'feh');
}

</script>


<?


// ***************************************************************************
include "frame-end.html";
