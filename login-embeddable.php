<? // login-embeddable.php

$bizid = $_REQUEST['bizid'];
if(!$bizid) {
	echo "No bizid supplied.";
	exit;
}

$source = <<<RAWHTML

<!--
The LeashTime External Login Form

For access to LeashTime from your own website.

INSTRUCTIONS:
1. Include this form in a page on your own website.
2. Ensure that the "failuredestination" field is set to the URL of the page in which you include this form.
	 (Upon login success, the client is logged into LeashTime.  Upon failure, Leashtime redirects to the page specified,
	  with the parameter "loginfailed=1".)
	 
	 For example, if the page in which include this form is http://mydogbiz.com/client-login.html,
	 then when a user tries to login unsuccessfully, LeashTime will redirect to
	 http://mydogbiz.com/client-login.html?loginfailed=1
	 
	 It is the web developer's responsibility to display any failure message predicated on the loginfailed parameter.
3. If you wish to allow users to set up their own logins, edit the SELFSTART section below.
	 
-->	 



<form name='leashtimelogin' method="POST" action="https://{$_SERVER["HTTP_HOST"]}/login-external.php">
<input type="hidden" name="bizid" value="##BIZID##">
<input type="hidden" id="jsuseragent" name="jsuseragent" value="">
<input type="hidden" id="clienttime" name="clienttime" value="">
<input type="hidden" name="failuredestination" value="http://ANY-OLD-DOMAIN.COM/MYLOGINPAGE.HTML">

<table style="width:390px" >
<tr>
				<td>Username</td>
				<td align="left"><input type="text" name="user_name" size="25" maxlength="65"  autocomplete='off'/></td>
</tr>
<tr>
				<td>Password</td>
				<td align="left"><input type="password" name="user_pass" size="25" maxlength="30" onKeyup='autoSubmit(event)'  autocomplete='off'/></td>
</tr>
<tr>
				<td>&nbsp;</td>
				<td align="left">
					 <input type='button' value="Login" onclick="submitIfValid()">
				</td>
</tr>
<tr>
		<td colspan="2" align="center"><br /><hr />Click <a href="https://{$_SERVER["HTTP_HOST"]}/temp-password-assignment.php?bizid=##BIZID##"><b>here</b></a> if you have forgotten your password.</td>
</tr>

<!-- Include this section if you want to offer the SELFSTART link 
<tr>
		<td colspan="2" align="center"><br />Don&quot;t have a username yet? Click here to
		<a href="https://{$_SERVER["HTTP_HOST"]}/login-creds-application.php?b=##BIZID##"><b>set one up</b></a>.</td>
</tr>
!-- End SELFSTART link section -->
	
</table>

</form>

<SCRIPT LANGUAGE="JavaScript" SRC="https://{$_SERVER["HTTP_HOST"]}/check-form.js"></SCRIPT>
<SCRIPT LANGUAGE="JavaScript">
document.leashtimelogin.user_name.focus();

setPrettynames('user_name','Username','user_pass','Password');

function autoSubmit(event) {
  if(event.keyCode == 13) submitIfValid();
}
function submitIfValid(){
	document.getElementById('jsuseragent').value = navigator.userAgent;
	var d = new Date().getTime();
	document.getElementById('clienttime').value=d/1000; // used in mobile sitter app to calculate counter timeout
  if(MM_validateForm('user_name','','R','user_pass','','R'))
    document.leashtimelogin.submit();
}
</SCRIPT>
RAWHTML;

$source = str_replace('##BIZID##', $bizid, $source);

if(!$_GET['preview']) {
	header("Content-Type: text/plain");
	$d = date('Y.m.d-H.i');
	$r = $_REQUEST['recent'] ? 'recent-'.$_REQUEST['recent'] : 'all';
	$disp = $_GET['save'] ? 'attachment' : 'inline';
	header("Content-Disposition: $disp; filename=login-form.htm ");
}
echo $source;