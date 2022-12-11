<? // prospect-request-form-custom-templated.php
require_once "common/init_session.php";
require_once "gui-fns.php";

/* This new version will consult the business's preferences to determine:
   - how to greet the prospect "prospectFormGreeting"
   - whether to include meeting fields "suppressMeetingFieldsInProspectForm"
   - whether to employ anti-spam functionality "enforceProspectSpamDetection"
   - simple address or separate fields "prospectFormSimpleAddress"
   - which fields are required "prospectFormRequiredFields" ==> CSV
   - whether to include "How did you hear about us?" question and others "prospectFormOptionalFields"
   
   This version will be responsive.
   
   if
   
   */


if(!($bizid = $_GET['bizid'])) { echo "No business ID supplied."; exit; }
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
	Please fill in as much (or as little) of the following fields to ask us a question, 
	pass on a comment, request a service, or to just say hello!
	<p>
	All we ask is that you enter a phone number or email address where we can reach you.
GREETING;
	$prospectGreeting = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormGreeting' LIMIT 1");
	$prospectGreeting = $prospectGreeting ? $prospectGreeting : $defaultProspectGreeting;
	
	$smallScreenIsNeeded = isMobileUserAgent() || $_GET['mob'];
	initializeMeetingStuff($smallScreenIsNeeded);
	$optionalFieldNames = 'whentocall,whenserviceneeded,referralsimple,referralcodes,pastclient';
	foreach(explode(',', $optionalFieldNames) as $f) $optionalFields[$f] = 0;
	$optionalFieldsToInclude = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormOptionalFields' LIMIT 1", 1);
	foreach(explode(',', $optionalFieldsToInclude) as $f) $optionalFields[$f] = 1;
	$template = prospectTemplate($_GET['templateid']);
	
	if(!$template)  { echo "Template [{$_GET['templateid']}] not found."; exit; }
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
}

//echo "BANG!BANG!fuckingBANG!  $headerBizLogo".print_r($_SESSION,1);exit;	
	
	if($headerBizLogo) {
		//$dimensions = imageDimensionsScaledToFit($headerBizLogo, 386, 56);
		return "background-image: url('$headerBizLogo');";
						//background-size: {$dimensions[0]}px {$dimensions[1]}px;";
	}
}

function referralOptions($cats, $level=0) {
	global $referralOptions;
	if($level==0) {
		$referralOptions = "\n<option value=''>Choose One</option>";
		$cats = referralCategoryTree($cats);
	}
	foreach($cats as $cat => $subcats) {
		$label = explode('|', $cat);
		$dashes = '';
		for($i=0;$i<$level;$i++) $dashes .= "&nbsp;&nbsp;&nbsp;";
		$style = $subcats ? 'style="color:#555555;font-style:italic;"' : 'style="color:black"';
		$val = $subcats ? "-1" : $label[0];
		$referralOptions .= "\n<option $style value='$val'>$dashes{$label[1]}</option>";
		referralOptions($subcats, $level+1);
	}
}

function referralBlock($smallDisplay=false) {
	require_once "referral-fns.php";
	require_once "gui-fns.php";
	$cats = getReferralCategories();
//print_r($cats);	
//echo "BANG!";exit;	
	if(!$cats) return;
	referralOptions($cats);
	global $referralOptions;
	ob_start();
	ob_implicit_flush(0);
	if($smallDisplay) {
		echo "<label for='x-std-referralcode'>How did you hear about us?</label>
		<br>";
		selectElement($label, "x-std-referralcode", $value=null, $referralOptions, $onChange="checkReferral(this)", $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
		echo "<br>
		<label for='x-std-referralnote'>Referral note:</label>
		<br>
		<input id='x-std-referralnote' name='x-std-referralnote' class='ahunnert' maxlength=45>
		<p>";
	}
	else {
		echo "<tr><td><label for='x-std-referralcode'>How did you hear about us?</label></td><td>";
		selectElement($label, "x-std-referralcode", $value=null, $referralOptions, $onChange="checkReferral(this)", $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
		echo "</td></tr><tr><td><label for='x-std-referralnote'>Referral note:</label></td><td><input id='x-std-referralnote' name='x-std-referralnote' maxlength=45></td></tr>";
	}
	$block = ob_get_contents();
	ob_end_clean();
	return $block;
	
}


function bigScreenForm() {
	global $antiSpamElements, $meetingHTML, $optionalFields;
	//whentocall,whenserviceneeded,referralsimple,referralcodes
	$yesNoRadios = radioButtonSet('x-oneline-Past client', $value=null, array('yes'=>'yes', 'no'=>'no'), $onClick=null, $labelClass=null, $inputClass=null, $rawLabel=false);
	$yesNoRadios = join(' ', $yesNoRadios);
	$optionalFrags = array(
		'whentocall'=>
			"<tr><td><label for='whentocall'>Best time for us to call:</label></td><td><input id='whentocall' name='whentocall' maxlength=256></td></tr>",
		'whenserviceneeded'=>
			"<tr><td><label for='x-oneline-When service is needed'>When do you need service?</label></td></tr><tr><td colspan=2><input id='x-oneline-When service is needed' name='x-oneline-When service is needed' size=45 maxlength=256></td></tr>",
		'referralsimple'=>
			"<tr><td><label for='x-oneline-Referral'>How did you hear about us?</label></td><td><input id='x-oneline-Referral' name='x-oneline-Referral' maxlength=256></td></tr>",
		'referralcodes'=>
			referralBlock($smallDisplay=false),
		'pastclient'=>
			"<tr><td><label for='x-oneline-Past client'>Have we served you and your pets in the past?</label></td><td>$yesNoRadios</td></tr>"
			
			
		);
	
	$spacer = "<img src='art/spacer.gif' width=1 height=100>";
	$simpleAddress = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormSimpleAddress' LIMIT 1");
	$addressHTML =
		$simpleAddress ? 
			"<tr><td><label for='address'>Address:</label></td><td><textarea id='address' name='address' rows=4 cols=20></textarea></td></tr>"
		: <<<MULTILINE
<tr><td><label for='street1'>Address:</label></td><td><input id='street1' name='street1' maxlength=60></td></tr>
<tr><td><label for='street2'>Address 2:</label></td><td><input id='street2' name='street2' maxlength=60></td></tr>
<tr><td><label for='city'>City:</label></td><td><input id='city' name='city' maxlength=60></td></tr>
<tr><td><label for='state'>State:</label></td><td><input id='state' name='state' maxlength=60></td></tr>
<tr><td><label for='zip'>ZIP:</label></td><td><input id='zip' name='zip' maxlength=60></td></tr>
MULTILINE;
	if($_REQUEST['refid']) $refInput = "<input type='hidden' name='x-oneline-ReferrerId' value='{$_REQUEST['refid']}'>";
		
	$formTemplate = "
<form method='POST' name='prospectinforequest' action='https://{$_SERVER["HTTP_HOST"]}/prospect-request.php'>
$refInput
<input type='hidden' id='pbid' name='pbid' value='#BIZID#'> <!-- Please insert your LeashTime biz ID where it says '1'. -->
<input type='hidden' id='goback' name='goback' value='#HOMEPAGE#'> <!-- Please supply the address to return to after the message is sent. -->
$antiSpamElements
<table><tr><td valign=top>
<table>
<tr><td><label for='fname'>First Name:</label></td><td><input id='fname' name='fname' maxlength=45></td></tr>
<tr><td><label for='lname'>Last Name:</label></td><td><input id='lname' name='lname' maxlength=45></td></tr>
<tr><td><label for='phone'>Phone:</label></td><td><input id='phone' name='phone' maxlength=45 class='phone'></td></tr>
#WHENTOCALL#
<tr><td><label for='email'>Email:</label></td><td><input id='email' name='email' maxlength=60></td></tr>
$addressHTML
#PASTCLIENT#
<tr><td colspan=2><label for='pets'>Tell us about your pets (names, kind):</label></td></tr>
<tr><td colspan=2><textarea id='pets' name='pets' rows=3 cols=40></textarea></td></tr>
#WHENSERVICENEEDED#
<tr><td colspan=2><label for='note'>How can we help you?</label></td></tr>
<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=40></textarea></td></tr>
$meetingHTML
#REFERRALSIMPLE#
#REFERRALCODES#
<tr><td colspan=2><input class='Button' id='sendbutton' type=button value='Send Request' onClick='checkAndSend()'></td></tr>
</table>
</td>
</tr>
<tr><td colspan=2 id='requirednote' style='display:none;text-align:left;' class='warning'>* Required field.</td></tr>
</table>
</form>
$spacer";

	foreach($optionalFields as $k => $v) {
		$v = $v ? $optionalFrags[$k] : '';
		$formTemplate = str_replace(strtoupper("#$k#"), $v, $formTemplate);
	}
	return $formTemplate;
}

function smallScreenForm() {
	global $antiSpamElements, $meetingHTML, $optionalFields;
	//whentocall,whenserviceneeded,referralsimple,referralcodes
	$optionalFrags = array(
		'whentocall'=>
			"<label for='whentocall'>Best time for us to call:</label><br><input id='whentocall' name='whentocall' class='ahunnert' maxlength=60>
<p>",
		'whenserviceneeded'=>
			"<label for='x-oneline-When service is needed'>When do you need service?</label><br><input id='x-oneline-When service is needed' name='x-oneline-When service is needed' class='ahunnert' maxlength=60>
<p>",
		'referralsimple'=>
			"<label for='x-oneline-Referral'>How did you hear about us?</label><br><input id='x-oneline-Referral' name='x-oneline-Referral' class='ahunnert' maxlength=60>
<p>",
		'referralcodes'=>
			referralBlock($smallDisplay=true));
	$spacer = "<img src='art/spacer.gif' width=1 height=100>";
	$simpleAddress = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormSimpleAddress' LIMIT 1");
	$addressHTML =
		$simpleAddress ? 
			"<label for='address'>Address:</label><br><textarea id='address' name='address' rows=4 cols=20></textarea>"
		: <<<MULTILINE
<label for='street1'>Address:</label><br><input id='street1' name='street1' class='ahunnert' maxlength=60>
<br><label for='street2'>Address 2:</label><br><input id='street2' name='street2' class='ahunnert' maxlength=60>
<br><label for='city'>City:</label><br><input id='city' name='city' class='ahunnert' maxlength=60>
<br><label for='state'>State:</label><br><input id='state' name='state' class='ahunnert' maxlength=60>
<br><label for='zip'>ZIP:</label><br><input id='zip' name='zip' class='ahunnert' maxlength=60>
MULTILINE;
	if($_REQUEST['refid']) $refInput = "<input type='hidden' name='x-oneline-ReferrerId' value='{$_REQUEST['refid']}'>";
	$formTemplate =  "
</center>
<div style='padding-left:0.5em;'>
<form method='POST' name='prospectinforequest' action='https://{$_SERVER["HTTP_HOST"]}/prospect-request.php'>
$refInput
<input type='hidden' id='pbid' name='pbid' value='#BIZID#'> <!-- Please insert your LeashTime biz ID where it says '1'. -->
<input type='hidden' id='goback' name='goback' value='#HOMEPAGE#'> <!-- Please supply the address to return to after the message is sent. -->
$antiSpamElements
<label for='fname'>First Name:</label>
<br><input id='fname' name='fname' class='ahunnert' maxlength=45>
<p>
<label for='lname'>Last Name:</label>
<br><input id='lname' name='lname' class='ahunnert' maxlength=45>
<p>
<label for='phone'>Phone:</label>
<br><input id='phone' name='phone' class='phone ahunnert' maxlength=45>
<p>
#WHENTOCALL#
<label for='email'>Email:</label>
<br><input id='email' name='email' class='ahunnert' maxlength=60>
<p>
$addressHTML
<p>Tell us about your pets (names, kind):</label>
<br><textarea class='ahunnert' style='font-size:100%;' id='pets' name='pets' rows=4 ></textarea>
<p>
#WHENSERVICENEEDED#
<label for='note'>How can we help you?</label>
<br><td colspan=2><textarea  class='ahunnert' style='font-size:100%;' id='note' name='note' rows=4></textarea>
<p>
$meetingHTML
#REFERRALSIMPLE#
#REFERRALCODES#
<p>
<input class='Button' type=button id='sendbutton' value='Send Request' onClick='checkAndSend()'>
</form>
</div>
<div id='requirednote' style='display:none;text-align:left;' class='warning'>* Required field.</div>
$spacer";

	foreach($optionalFields as $k => $v) {
		$v = $v ? $optionalFrags[$k] : '';
		$formTemplate = str_replace(strtoupper("#$k#"), $v, $formTemplate);
	}
	return $formTemplate;
}


function prospectTemplate($templateid) {
	
	global $smallScreenIsNeeded, $prospectGreeting, $meetingHTML, $meetingScriptIncludes, $meetingScriptFragment, $phoneNumbersDigitsOnly, $bizid, $antiSpamElements;
	$spacer = "<img src='art/spacer.gif' width=1 height=100>";
	$suppressMeetingFieldsInProspectForm = 
		fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'suppressMeetingFieldsInProspectForm' LIMIT 1");
	
	if($suppressMeetingFieldsInProspectForm) {
		$meetingHTML=null; 
		//$meetingScriptIncludes=null; 
		//$meetingScriptFragment=null; 
		$spacer = null;
	}
	
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
<script language='javascript'>
function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function validEmail(src) {
  var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
  return regex.test(src);

}

$phoneManipulation

/*function checkAndSend() {
	
	var emailAddress = ''+(document.prospectinforequest.email.value);
	if(jstrim(document.prospectinforequest.phone.value) == ''
			&& jstrim(emailAddress) == '')
     alert('Please supply an email address or phone number where we can reach you.');
	else if(jstrim(emailAddress) != '' && !validEmail(emailAddress)) {
		if(validEmail(jstrim(emailAddress)))
    	alert('The email address must not have leading or trailing spaces..');
    else
    	alert('Please supply a valid email address.');
	}
  else {
		if(document.getElementById('modelnum')) document.getElementById('modelnum').value = '$bizid';
		document.prospectinforequest.submit();
	}
} */

$meetingScriptFragment 

function checkReferral(el) {
	if(el.options[el.selectedIndex].value == -1) {
		el.selectedIndex = 0;
		alert('Please select a more specific option.');
	}
	var referralnote = document.getElementById('x-std-referralnote');
	if(referralnote) referralnote.disabled = el.selectedIndex == 0;
}

if(document.getElementById('x-std-referralnote')) document.getElementById('x-std-referralnote').title = 'A referral option must be chosen to enter a note here.';
if(document.getElementById('x-std-referralcode')) checkReferral(document.getElementById('x-std-referralcode'));

</script>
";	
}

function initializeMeetingStuff($smallScreenIsNeeded) {
	global $meetingHTML, $meetingScriptIncludes, $meetingScriptFragment, $bizid, $aHunnertPercent;
;


$meetingHTML = <<<HTML
<tr><td colspan=2>Would you like to schedule a meeting with us? <input name='meet' id='yesmeet' type='radio' onChange='toggleMeeting(this)'> <label for='yesmeet'>yes</label> <input id='nomeet' name='meet' type='radio' onclick='toggleMeeting(this)' CHECKED> <label for='nomeet'>no</label></td></tr>
<tr id='meetingdatetr0' style='display:none'><td colspan=2>Please tell us when is convenient for you:</td></tr>
<tr id='meetingdatetr1' style='display:none'>
<td>
Date: <input DISABLED class="dateInput" id="meetingdate1" name="x-oneline-meetingdate1" autocomplete="off" size=12 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
			<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
					onclick='dateButtonAction(this,document.getElementById("meetingdate1"),"1","15","2005")'>
</td>
<td style='padding-left:5px;'>
 at what time? <input DISABLED id="meetingtime1" name="x-oneline-meetingtime1" size=10>
</td>
</tr>
<tr id='meetingdatetr2' style='display:none'>
<td>
Date: <input DISABLED class="dateInput" id="meetingdate2" name="x-oneline-meetingdate2" autocomplete="off" size=12 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
			<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
					onclick='dateButtonAction(this,document.getElementById("meetingdate2"),"1","15","2005")'>
</td>
<td style='padding-left:5px;'>
 at what time? <input DISABLED id="meetingtime2" name="x-oneline-meetingtime2" size=10>
</td>
</tr>
<tr id='meetingdatetr3' style='display:none'>
<td>
Date: <input DISABLED class="dateInput" id="meetingdate3" name="x-oneline-meetingdate3" autocomplete="off" size=12 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
			<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
					onclick='dateButtonAction(this,document.getElementById("meetingdate3"),"1","15","2005")'>
</td>
<td style='padding-left:5px;'>
 at what time? <input DISABLED id="meetingtime3" name="x-oneline-meetingtime3" size=10>
</td>
</tr>
HTML;
$initiaDisplay = 'none';
$calendarWidget = "<span 
					onclick='dateButtonAction(this,document.getElementById(\"meetingdate1\"),\"1\",\"15\",\"2005\")'>&#128197;</span>";
$calendarIconDims = "width='48px' height='36px'";
$calendarWidget = "<img src=\"https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif\" $calendarIconDims
					onclick='dateButtonAction(this,document.getElementById(\"meetingdate2\"),\"1\",\"15\",\"2005\")'>";

if($smallScreenIsNeeded) $meetingHTML = <<<ONECOL
Would you like to meet with us?<br><input name='meet' id='yesmeet' type='radio' onChange='toggleMeeting(this)'> <label for='yesmeet'>yes</label> <input id='nomeet' name='meet' type='radio' onclick='toggleMeeting(this)' checked> <label for='nomeet'>no</label>
<div id='meetingdatetr0' style='display:none;'>Please tell us when:</div>

<div id='meetingdatetr1' style='display:$initiaDisplay'>
Date: $calendarWidget
<br>
<input DISABLED class="ahunnert" id="meetingdate1" name="x-oneline-meetingdate1" autocomplete="off" 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
<br>
... at what time?
<br>
<input DISABLED id="meetingtime1" name="x-oneline-meetingtime1" class='ahunnert'>
</div>

<div id='meetingdatetr2' style='display:$initiaDisplay'>
Date: $calendarWidget
<br>
<input DISABLED class="ahunnert" id="meetingdate2" name="x-oneline-meetingdate2" autocomplete="off" size=12 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
<br>
... at what time? 
<br>
<input DISABLED id="meetingtime2" name="x-oneline-meetingtime2" class='ahunnert'>
</div>

<div id='meetingdatetr3' style='display:$initiaDisplay'>
Date: $calendarWidget

<br>
<input DISABLED class="ahunnert" id="meetingdate3" name="x-oneline-meetingdate3" autocomplete="off" size=12 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
			
<br>
... at what time? 
<br>
<input DISABLED id="meetingtime3" name="x-oneline-meetingtime3" class='ahunnert'>
</div>

ONECOL;

$meetingScriptIncludes = <<<HTML2
<script language='javascript' src='https://{$_SERVER["HTTP_HOST"]}/popcalendar.js'></script>
<script language='javascript' src='https://{$_SERVER["HTTP_HOST"]}/check-form.js'></script>
HTML2;

//if($_REQUEST['require']) { // eventually, gather required fields from prefs
$requiredFields = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormRequiredFields' LIMIT 1");
//$requiredFields = 'lname,fname';
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


list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
require('common/init_db_common.php');
$petbiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$_GET['bizid']}' LIMIT 1");
$_SESSION["i18nfile"] = getI18NPropertyFile($petbiz["country"]);
$localDateFormat = getI18Property('popupcalendarformat', $default='mm/dd/yyyy');
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);

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

$meetingScriptFragment = <<<HTML3
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
	
	var yesmeet = document.getElementById("yesmeet") && document.getElementById("yesmeet").checked;
	
  if(yesmeet &&
  		!(jstrim(document.getElementById("meetingdate1").value) 
  		|| jstrim(document.getElementById("meetingdate2").value) 
  		|| jstrim(document.getElementById("meetingdate3").value))) {
		 args[args.length] = 'At least one meeting date is required if you want a meeting.';
		 args[args.length] = '';
		 args[args.length] = 'MESSAGE';
	}
  if(yesmeet) {
		args[args.length] = 'meetingdate1';
		args[args.length] = '';
		args[args.length] = 'isDate';
		args[args.length] = 'meetingdate1';
		args[args.length] = 'NOT';
		args[args.length] = 'isPastDate';
		args[args.length] = 'meetingdate2';
		args[args.length] = '';
		args[args.length] = 'isDate';
		args[args.length] = 'meetingdate2';
		args[args.length] = 'NOT';
		args[args.length] = 'isPastDate';
		args[args.length] = 'meetingdate3';
		args[args.length] = '';
		args[args.length] = 'isDate';
		args[args.length] = 'meetingdate3';
		args[args.length] = 'NOT';
		args[args.length] = 'isPastDate';
		args[args.length] = 'meetingtime1';
		args[args.length] = 'meetingdate1';
		args[args.length] = 'RIFF';
		args[args.length] = 'meetingtime2';
		args[args.length] = 'meetingdate2';
		args[args.length] = 'RIFF';
		args[args.length] = 'meetingtime3';
		args[args.length] = 'meetingdate3';
		args[args.length] = 'RIFF';
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

function toggleMeeting(el) {
	var rowShow = navigator.appName.indexOf('Internet Explorer') >= 0 ? 'block' : 'table-row';
	var display = el.id == 'yesmeet' ? rowShow : 'none';
	var disabled = el.id == 'nomeet';
	
	var cn = document.getElementById("meetingdate1").className;
	
	document.getElementById("meetingdatetr0").style.display = display;
	document.getElementById("meetingdatetr1").style.display = display;
	document.getElementById("meetingdatetr2").style.display = display;
	document.getElementById("meetingdatetr3").style.display = display;
	document.getElementById("meetingdate1").disabled = disabled;
	document.getElementById("meetingtime1").disabled = disabled;
	document.getElementById("meetingdate2").disabled = disabled;
	document.getElementById("meetingtime2").disabled = disabled;
	document.getElementById("meetingdate3").disabled = disabled;
	document.getElementById("meetingtime3").disabled = disabled;
}

var localDateFormat = '$localDateFormat';

function dateButtonAction(ctl, date, month, day, year) {
  var datePosition = getAbsolutePosition(document.getElementById(date.id));
  var offset = addOffsets(getTabPageOffset(date), getContainerOffset(date, 'Sheet'), getContainerOffset(date, 'contentLayout'));
  showCalendar(ctl, date, localDateFormat,"en",1,datePosition.x-offset.x, datePosition.y-offset.y);
}

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

if(document.getElementById("nomeet")) document.getElementById("nomeet").checked = true;
if(document.getElementById("yestmeet")) document.getElementById("yestmeet").checked = false;

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

//toggleMeeting(document.getElementById('nomeet'));

//  END Javascript for the Meeting section
HTML3;

}