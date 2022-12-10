<? // prospect-lt-referred.php
require_once "common/init_session.php";
require_once "gui-fns.php";
$bizid = 68; // LeashTime Customers
if(!$bizid) { echo "No business ID supplied."; exit; }
else {
	
	
	$aHunnertPercent = '98%';

	$antiSpamElements = 
	"<input type='text' style='display:none' id='address3' name='address3' value=''> 
<input type='hidden' id='modelnum' name='modelnum' value=''> 
";
//echo "ANTISPAM: [$antiSpamElements]";	print_r($_GET['version'] == 'asv');
	require_once('common/init_db_common.php');
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizid' LIMIT 1");
	if(!$biz)  { echo "No business found for ID supplied."; exit; }
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);
	$defaultProspectGreeting = <<<GREETING
	Get In Touch with LeashTime
	<p>
	All we ask is that you enter a phone number or email address where we can reach you.
GREETING;
	$prospectGreeting = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormGreeting' LIMIT 1");
	$prospectGreeting = $prospectGreeting ? $prospectGreeting : $defaultProspectGreeting;
	
	$smallScreenIsNeeded = isMobileUserAgent() || $_GET['mob'];
	//initializeMeetingStuff($smallScreenIsNeeded);
	//$optionalFieldNames = 'whentocall,whenserviceneeded,referralsimple,referralcodes';
	//foreach(explode(',', $optionalFieldNames) as $f) $optionalFields[$f] = 0;
	//$optionalFieldsToInclude = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormOptionalFields' LIMIT 1", 1);
	//foreach(explode(',', $optionalFieldsToInclude) as $f) $optionalFields[$f] = 1;
	setupGlobals($yuck);
	$template = prospectTemplate($_GET['templateid']);
	
	//if(!$template)  { echo "Template [{$_GET['templateid']}] not found."; exit; }
	$bizHomePage = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizHomePage' LIMIT 1");
	$prospectFormGoBackURL = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormGoBackURL' LIMIT 1");
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	$form = str_replace('#BIZID#', $bizid, $template);
	$form = str_replace('#HOMEPAGE#', ($prospectFormGoBackURL ? $prospectFormGoBackURL : $bizHomePage), $form);
	$bizLink = $bizHomePage ? "<a href='$bizHomePage'>$bizName</a>" : $bizName;
	$form = str_replace('#BIZNAME#', $bizLink, $form);
	
	$_SESSION["uidirectory"] = "bizfiles/biz_$bizid/clientui/";
	if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$bizid/";
	$suppressMenu = 1;
	
	// responsive for mobile
	if($smallScreenIsNeeded) {
		$smallBannerCSS = getSmallBannerCSS();
		$extraHeadContent = <<<MOBILESTYLE
		<style>
	body {background-image: none;}
	.Sheet 
	{
	  width: 100%;
	}
	div.Header 
	{
	  width: 100%;
	  height: 100px;
	}
	

	div.Header   div
	{
	  $smallBannerCSS
		background-repeat: no-repeat;
		background-position: center center;
	}
	.prospectform {font-size:30pt;}
	.ahunnert {width:$aHunnertPercent;background:#f5f5f5;}
		</style>
MOBILESTYLE;
	}
	else $extraHeadContent = <<<COMPUTERSTYLE
		<style>
	.prospectform {font-size:1.1em;}
		</style>
COMPUTERSTYLE;
	
	include "frame-client.html";
	echo "<div class='prospectform'>";
	echo $form;
	echo "</div>";
	$noCommentButton = true;
	include "frame-end.html";

}

function getSmallBannerCSS() {
	$headerBizLogo = $_SESSION["uidirectory"];
	if(file_exists($_SESSION["uidirectory"].'../logo.jpg')) $headerBizLogo .= '../logo.jpg';
	else if(file_exists($_SESSION["uidirectory"].'../logo.gif')) $headerBizLogo .= '../logo.gif';
	else if(file_exists($_SESSION["uidirectory"].'../logo.png')) $headerBizLogo .= '../logo.png';
	else $headerBizLogo = '';
//if(mattOnlyTEST()) {echo $headerBizLogo;	exit;}

//echo "BANG!BANG!fuckingBANG!  $headerBizLogo".print_r($_SESSION,1);exit;	
	
	if($headerBizLogo) {
		//$dimensions = imageDimensionsScaledToFit($headerBizLogo, 386, 56);
		return "background-image: url('$headerBizLogo');";
						//background-size: {$dimensions[0]}px {$dimensions[1]}px;";
	}
}


function bigScreenForm() {
	global $antiSpamElements, $meetingHTML, $optionalFields;
	
	$spacer = "<img src='art/spacer.gif' width=1 height=100>";
	if($_REQUEST['refid']) $refInput = "<input type='hidden' name='x-oneline-ReferrerId' value='{$_REQUEST['refid']}'>";
	$formTemplate = "
<form method='POST' name='prospectinforequest' action='https://{$_SERVER["HTTP_HOST"]}/prospect-request.php'>
$refInput
<input type='hidden' id='pbid' name='pbid' value='68'>
<input type='hidden' id='goback' name='goback' value='#HOMEPAGE#'>
$antiSpamElements
<table><tr><td valign=top>
<table>
<tr><td><label for='fname'>Your Business Name:</label></td><td><input id='fname' name='fname' maxlength=45></td></tr>
<tr><td><label for='lname'>Your Name:</label></td><td><input id='lname' name='lname' maxlength=45></td></tr>
<tr><td><label for='email'>Email:</label></td><td><input id='email' name='email' maxlength=60></td></tr>
<tr><td colspan=2><label for='note'>Your Message</label></td></tr>
<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=40></textarea></td></tr>
<tr><td colspan=2><input class='Button' id='sendbutton' type=button value='Send' onClick='checkAndSend()'></td></tr>
</table>
</td>
</tr>
<tr><td colspan=2 id='requirednote' style='display:none;text-align:left;' class='warning'>* Required field.</td></tr>
</table>
</form>
$spacer";

	return $formTemplate;
}

function smallScreenForm() {
	global $antiSpamElements, $meetingHTML, $optionalFields;
	$spacer = "<img src='art/spacer.gif' width=1 height=100>";
	if($_REQUEST['refid']) $refInput = "<input type='hidden' name='x-oneline-ReferrerId' value='{$_REQUEST['refid']}'>";
	$formTemplate =  "
</center>
<div style='padding-left:0.5em;'>
<form method='POST' name='prospectinforequest' action='https://{$_SERVER["HTTP_HOST"]}/prospect-request.php'>
$refInput
<input type='hidden' id='pbid' name='pbid' value='68'>
<input type='hidden' id='goback' name='goback' value='#HOMEPAGE#'>
$antiSpamElements
<label for='fname'>Your Business Name:</label>
<br><input id='fname' name='fname' class='ahunnert' maxlength=45>
<p>
<label for='lname'>Your Name:</label>
<br><input id='lname' name='lname' class='ahunnert' maxlength=45>
<label for='email'>Your Email:</label>
<br><input id='email' name='email' class='ahunnert' maxlength=60>
<p>
<label for='note'>Your Message</label>
<br><td colspan=2><textarea  class='ahunnert' style='font-size:100%;' id='note' name='note' rows=4></textarea>
<p>
<input class='Button' type=button id='sendbutton' value='Send' onClick='checkAndSend()'>
</form>
</div>
<div id='requirednote' style='display:none;text-align:left;' class='warning'>* Required field.</div>
$spacer";
	return $formTemplate;
}


function prospectTemplate($templateid) {
	
	global $smallScreenIsNeeded, $prospectGreeting, $meetingHTML, $meetingScriptIncludes, $formValidationFragment, $phoneNumbersDigitsOnly, $bizid, $antiSpamElements;
	$spacer = null;
	
	$form = $smallScreenIsNeeded ? smallScreenForm() : bigScreenForm();

	$templateid = $templateid ? $templateid : 'simple';
	
	$phoneNumbersDigitsOnly = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'phoneNumbersDigitsOnly' LIMIT 1");
if($phoneNumbersDigitsOnly)
		$phoneFieldRestriction = <<<RESTRICT
$('.phone').keypress(function(e) {
	var keyCode = (e.keyCode ? e.keyCode : e.which);
	var allowed = {8:1, 9:1, 13:1};
	var ctrls ={97:1, 118:1, 120:1, 121:1, 122:1};
	//alert(keyCode+': '+(typeof allowed[keyCode] == 'undefined'));
	if(!((keyCode > 47 && keyCode < 58 && !e.shiftKey) || 
				typeof allowed[keyCode] != 'undefined' 
				|| (e.ctrlKey && typeof ctrls[keyCode] != 'undefined'))) {
		e.preventDefault();
	}
});
RESTRICT;

$phoneManipulation = 
<<<PHONE
$('.phone').keyup(function() {
	if(!$(this).val().match(/^\d+(-\d+)*$/))
		return;
  var foo = $(this).val().split('-').join(''); // remove hyphens
	foo = foo.match(new RegExp('.{1,4}$|.{1,3}', 'g')).join('-');
  $(this).val(foo);
});

$phoneFieldRestriction
PHONE;

return "
<center>
$prospectGreeting
<p>
Thanks for your interest in #BIZNAME#!
<p>
      
<!-- prospect-request-form.html -->
$form

$spacer
$meetingScriptIncludes
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function validEmail(src) {
  var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
  return regex.test(src);

}

$phoneManipulation

$formValidationFragment 


</script>
";	
}


//if($_REQUEST['require']) { // eventually, gather required fields from prefs
//$requiredFields = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormRequiredFields' LIMIT 1");

function setupGlobals($yuck) {
	global $smallScreenIsNeeded, $prospectGreeting, $formValidationFragment, $phoneNumbersDigitsOnly, $bizid, $antiSpamElements;
	$preSubmitTEST = TRUE; // dbTEST('dogslife');
	$preSubmitBehavior = $preSubmitTEST ? 'if(true) preSubmitPreparation(); else ' : '';
	$preSubmitPreparationFunction = !$preSubmitTEST ? '' :
	"function preSubmitPreparation() {
		document.getElementById('sendbutton').disabled = true;
		$.fn.colorbox({html:'<h2>Please wait...</h2>', width:'400', height:'300', scrolling: true, opacity: '0.3'});
		setTimeout(submitAfterWait, 500); // wait 100 milliseconds
	}

	function submitAfterWait() {
			if(document.getElementById('modelnum')) document.getElementById('modelnum').value = '$bizid';
			document.prospectinforequest.submit();
	}";

	$requiredFields = 'lname,fname,email,note';
	if($requiredFields) {
		$requiredFieldsRaw = 	$requiredFields;
		$requiredFields = explode(',', $requiredFieldsRaw);
		$allowed = explodePairsLine('fname|First Name||lname|Last Name||phone|Phone||email|Email||address|Address||'
																.'pets|Pets||note|Note||street1|Address||street2|Address 2||city|City||state|State||zip|ZIP');
		foreach($requiredFields as $fld) {
			if($allowed[$fld]) {
				$requiredFieldJavaScript .= "args[args.length] = '$fld';\n";
				$requiredFieldJavaScript .= "args[args.length] = '';\n";
				$requiredFieldJavaScript .= "args[args.length] = 'R'\n";

				if($fld == 'email') {
					$requiredFieldJavaScript .= "args[args.length] = '$fld';\n";
					$requiredFieldJavaScript .= "args[args.length] = '';\n";
					$requiredFieldJavaScript .= "args[args.length] = 'isEmail'\n";
				}

				$prettyNames[] = "'$fld', '{$allowed[$fld]}'";
			}
			else $badNames[]= $fld;
		}
	//echo "requiredFieldJavaScript: ".print_r($requiredFieldJavaScript, 1);exit;
		if($prettyNames)
			$requiredFieldJavaScript .= "setPrettynames(".join(', ', $prettyNames).")\n"; 
		foreach($requiredFields as $f) $requiredFieldProps[] = "$f: 1";
		$requiredFieldJavaScript .= "var requiredFields = {".join(', ', $requiredFieldProps)."}\n";
		if($badNames) 
			$badNames = "document.write(\"<!-- <p style='color:red'>Warning: required fields are unknown: ".join(', ', $badNames)."</p> -->\");";
	}
	else $requiredFieldJavaScript = "var requiredFields = [];";
	
	$formValidationFragment = <<<HTML3
	//  Javascript for the Meeting section
	function validEmail(src) {
		var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
		return regex.test(src);
	}

	function checkAndSend() {
		setPrettynames('meetingdate', 'Meeting Date', 'meetingtime', 'Meeting Time');
		var mincontactmsg, args;
		var args = new Array();	
		$requiredFieldJavaScript
		if(requiredFields['phoneOrEmail'] && !validEmail(document.prospectinforequest.email.value) &&
			 jstrim(document.prospectinforequest.phone.value) == '') {
			 args[args.length] = 'Please supply an email address or phone number where we can reach you.';
			 args[args.length] = '';
			 args[args.length] = 'MESSAGE';
		}
		if(MM_validateFormArgs(args)) {
			$preSubmitBehavior
			if(true) {
				if(document.getElementById('modelnum')) document.getElementById('modelnum').value = '$bizid';
				document.prospectinforequest.submit();
			}
		}
	}

	$preSubmitPreparationFunction


	function getAbsolutePosition(element) {
			var r = { x: element.offsetLeft, y: element.offsetTop };
			if (element.offsetParent) {
				var tmp = getAbsolutePosition(element.offsetParent);
				r.x += tmp.x;
				r.y += tmp.y;
			}
			return r;
	}

	function addOffsets() {
		var r = {x: 0, y: 0};
		for(var i=0; i < addOffsets.arguments.length; i++) {
			r.x += addOffsets.arguments[i].x;
			r.y += addOffsets.arguments[i].y;
		}
		return r;
	}

	function getTabPageOffset(element) {
		// since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
		while(element.offsetParent) {
			var parent = element.offsetParent;
			if(parent.id && parent.id.indexOf('tabpage_') == 0) return {x: parent.offsetLeft, y: parent.offsetTop-23}; // 23 for tab height
			element = parent;
		}
		return {x: 0, y: 0};
	}

	function getContainerOffset(element, containerClassName) {
		// since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
		while(element.offsetParent) {
			var parent = element.offsetParent;
			if(parent.className == containerClassName) return {x: parent.offsetLeft, y: parent.offsetTop};
			element = parent;
		}
		return {x: 0, y: 0};
	}

	$badNames

	var reqfieldnames = '$requiredFieldsRaw';
	if(reqfieldnames != '') {
		reqfieldnames = reqfieldnames.split(',');	
		for(var i = 0; i < reqfieldnames.length; i++) {
			var labels = document.getElementsByTagName("label");
			for(var l = 0; l < labels.length; l++) {
				if(labels[l].getAttribute('for') == reqfieldnames[i])
					labels[l].innerHTML = "<span class='warning' title='required field'>* </span>"+labels[l].innerHTML;
			}
		}
		document.getElementById("requirednote").style.display='block';
	}
HTML3;
}