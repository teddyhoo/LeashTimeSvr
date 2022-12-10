<? // email-send-address-pref-edit.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require "preference-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
$failure = false;

if($failure) {
	$windowTitle = 'Insufficient Access Rights';
	require "frame-bannerless.php";	
	echo "<h2>$windowTitle</h2>";
	exit;
}
extract(extractVars('emailFromAddress,useDefaults', $_REQUEST));

$emailSettings = parse_ini_file('email-swiftmailer-properties.txt', true);




if($_POST) {
	setPreference('emailFromAddress',  $emailFromAddress);
	if(!$emailFromAddress) {
		setPreference('emailHost',  null);
		setPreference('smtpPort',  null);
		setPreference('smtpAuthentication',  null);
		setPreference('emailUser',  null);
		setPreference('emailPassword',  null);
		setPreference('smtpSecureConnection',  null);
	}
	else if($useDefaults) {
		$domain = substr($emailFromAddress, strpos($emailFromAddress, '@')+1);
		$vals = $emailSettings[$domain];
		setPreference('emailHost',  $vals['smtp_host'] );
		setPreference('smtpPort',  $vals['smtp_port'] );
		setPreference('smtpAuthentication',  $vals['smtp_auth'] );
		if($username = $vals['smtp_user_name']) {
			$username = $username == 'emailAddress' ? $emailFromAddress : substr($emailFromAddress, 0, strpos($emailFromAddress, '@'));
			setPreference('emailUser',  $username);
		}
		setPreference('smtpSecureConnection',  $vals['smpt_secure_connection'] );
		
		// ################ TEMPORARILY
		if($username = $vals['inbox_user_name']) {
			$username = $username == 'emailAddress' ? $emailFromAddress : substr($emailFromAddress, 0, strpos($emailFromAddress, '@'));
			setPreference('inboxUserName',  $username);
		}
		setPreference('inboxPassword',  getPreference('emailPassword'));
		setPreference('inboxType',  $vals['inbox_type']);
		setPreference('inboxSSL',  $vals['inbox_ssl']);
		setPreference('inboxHost',  $vals['inbox_host']);
		setPreference('inboxPort',  $vals['inbox_port']);
		// ################ TEMPORARILY
}
	echo "<script language='javascript'>window.opener.updateProperty(\"emailFromAddress\", null);window.close();</script>";
	exit;
}

$windowTitle = "Property Outbound Email Sender's Address";;
require "frame-bannerless.php";

?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<form name='propertyeditor' method='POST'>
<?
//labeledInput($label, $name, $value=null, $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=null)
labeledInput('', 'emailFromAddress', $_SESSION['preferences']['emailFromAddress'], null, 'VeryLongInput', 'showSuggestions()');
echo "<p>";
echo "\n<div style='display:none;' id='suggframe'>";
labeledCheckbox("Do you want to use these suggested settings?", 'useDefaults', true);
echo "<div id='suggestedsettings' style='padding:10px;'></div></div>\n";
echoButton('', 'Save', 'saveForm()');
echo " \n";
echoButton('', "Quit", 'window.close()');
echo "\n</form>\n";

$labels = explodePairsLine("smtp_host|SMTP Host||smtp_port|SMTP Port"
														."||smtp_ssl|SSL||smtp_user_name|SMTP username" // ||smtp_auth|Use SMTP Authentication
														."||smpt_secure_connection|Use Secure Connection||note|Note");
//smtp_user_name = emailWithoutDomain
foreach($emailSettings as $domain => $settings) {
	$html =  array();
	foreach($settings as $key => $val) {
		if(!$labels[$key]) continue;
		if($val == 1 || $val === true || $val == 'true') $val = 'yes';
		else if(false/*$val == 0 || $val === false || $val == 'false'*/) $val = 'no';
		$html[] = "{$labels[$key]}: $val";
	}
	$descr[$domain] = join('<br>', $html);
}
?>
<script language='javascript' src='check-form.js'></script>

<script language='javascript'>

var domains = {
<? 
$n = 1;
foreach((array)$descr as $domain => $html) {
		$n++;
		echo "'$domain':'$html'";
		if($n <= count($descr)) echo ",\n";
}
?>
};

function saveForm() {
	if(!MM_validateForm('emailFromAddress','','isEmail')	) return;
	var suggframe = document.getElementById('suggframe');
	var wasOpen = suggframe.style.display != 'none';
	showSuggestions();
	if(!wasOpen && suggframe.style.display != 'none') return;
	document.propertyeditor.submit();
}

function showSuggestions() {
	var dom = document.getElementById('emailFromAddress').value;
	var email = dom;
	if(dom.indexOf('@') > 0) {
		var ename = dom.substring(0, dom.indexOf('@'));
		dom = dom.substring(dom.indexOf('@')+1);
	}
	var suggframe = document.getElementById('suggframe');
	if(domains[dom] /*&& suggframe.style.display == 'none'*/) {
		var text = domains[dom];
		if(text.indexOf('WithoutDomain') > 0) text = text.replace('emailWithoutDomain', ename);
		else text = text.replace('emailAddress', email);
		
		suggframe.style.display = 'block';
		document.getElementById('useDefaults').checked = true;
		document.getElementById('suggestedsettings').innerHTML = text;
	}
	else if(!domains[dom]) {
		suggframe.style.display = 'none';
		document.getElementById('useDefaults').checked = false;
	}

}
</script>
