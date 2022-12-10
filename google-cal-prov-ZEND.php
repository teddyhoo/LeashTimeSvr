<? // google-cal-prov-ZEND.php
// Edit email prefs for logged in provider
// params: id - clientid

set_include_path(get_include_path().':/var/www/prod/ZendGdata-1.11.6/library:');
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
require_once "google-cal-fns.php";





// Determine access privs
$locked = locked('p-');


if($_REQUEST['testgoogleuser']) {
	if($_REQUEST['testgoogleuser'] == 1) $user = getGoogleCreds($_SESSION['auth_user_id']);
	else $user = array('username'=>$_REQUEST['testgoogleuser'], 'password'=>$_REQUEST['password']);
	$result = testUser($user);
	echo $result ? "ERROR: ".$result : "Found {$user['username']}'s calendar.";
	//print_r($_REQUEST);
	exit;
}

if($_POST) {
	extract(extractVars('googleusername,googlepassword,removeInfo', $_POST));
	if($removeInfo) {
		dropGoogleCreds($_SESSION['auth_user_id']);
		$message = "Calendar credentials cleared.  You will receieve no more updates to your calendar.";
	}
	else {
		if(!$googlepassword) {
			$user = getGoogleCreds($_SESSION['auth_user_id']);
			$googlepassword = $user['password'];
		}
		$user = array('username'=>$googleusername, 'password'=>$googlepassword);
		if($error = testUser($user)) {
			if(strpos($error, 'BadAuthentication') && !strcmp($googlepassword, strtoupper($googlepassword)))
				$error .= " (is the Caps Lock On?)";
			$error = "ERROR: ".$error;
		}
		else {
			saveGoogleCreds($_SESSION['auth_user_id'], $googleusername, $googlepassword);
			$message = "Found {$user['username']}'s calendar.";
		}
	}
}


$pageTitle = "Google Calendar Preferences";
include "frame.html";

echo "This functionality is temporarily unavailable.";
include "frame-end.html";
exit;


if($error) echo "<p class='warning'>$error</p>";
else if($message) echo "<p class='pagenote'>$message</p>";
echoButton('', 'Save', 'save()');
echo "<img src='art/spacer.gif' width=10>";
$googleCreds = getGoogleCreds($_SESSION['auth_user_id']);
if($googleCreds['username'] || $googleCreds['password'])
	echoButton('', 'Remove Google Credentials', 'removeCreds()', 'HotButton', 'HotButtonDown', 0, 'Stop receiving updates to your Google calendar.');
?>
<p>Please supply your Google login name and password if you would like your pet care assignments to appear on your Google Calendar.</p>
<p>Your password is not viewable by your employer.</p>
<form name='propertyeditor' method='POST'>
<table>
<?

inputRow('Google user name', 'googleusername', $googleCreds['username'], '', 'emailInput');
passwordRow('Google password <i> (only if not set and/or changing user name)</i>', 'googlepassword');
hiddenElement('saveduser', $googleCreds['username']);
hiddenElement('passwordset', $googleCreds['password'] ? 1 : 0);
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
function save() {
	document.propertyeditor.submit();
}

function removeCreds() {
	document.getElementById('removeInfo').value=1;
	if(!confirm('Remove these credentials?')) return;
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
	ajaxGetAndCallWith('google-cal-prov.php?testgoogleuser='+username+password, 
		function(val, text) {alert(text);}, 'feh');
}

</script>


<?


// ***************************************************************************
include "frame-end.html";
