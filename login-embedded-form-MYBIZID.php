<? // login-embedded-form-MYBIZID.php

$popup = $_GET['popup'];

if($popup) {
	$behavior = 'If login is successful, LeashTime is opened up in a new window and this form is replaced with the message:

	You are logged in.  Logout
	
"Logout" is a link that will end the LeashTime session (but will not close the LeashTime window or change its contents).
:Logout" also restores the login form.';
	$success = "		document.location.href = '#OUTFILENAME#?success=1';
		// open LeashTime in a new window
		var w = window.open('','LeashTime',
			'toolbar=1,location=0,directories=0,status=1,resizable=yes,menubar=1,scrollbars=yes,width='+800+',height='+900);
		//if(!w) document.write('<b>Pop up window blocked.</b>');
		w.document.location.href='https://leashtime.com';
		if(w) w.focus();";

}
else {
	$behavior = 'If login is successful, LeashTime is opened up in the top window that contains this form.';
	$success = "		top.document.location.href = 'https://leashtime.com';";
}








$output = <<<'OUTPUT'
<!-- #OUTFILENAME#

The LeashTime External Login Form -- iframe version

Generated for BIZNAME.

For access to LeashTime from an an iframe on your own website.

INSTRUCTIONS:
1. Use this form as the source file of an iframe on your website's home page (or on some other page).  
	Example:
	
<iframe src="#OUTFILENAME#" width=400 height=230></iframe>

2. Ensure that the "bizid" field is set to your LeashTime business ID.
3. The default name of this file is "#OUTFILENAME#".
   If you change the name of this file, make sure to change all occurrences of 
   "#OUTFILENAME#" to the new name of this file.

BEHAVIOR:

This page offers a form that will let a user login to LeashTime.

When the Login button is clicked, the form first checks to make sure username and password have 
been supplied, and reports an error if either is missing.

If both are supplied, the form is posted to LeashTime.

#BEHAVIOR#

If login fails, one of several messages may be displayed:

	'The username or password entered was incorrect.'
	'The username or password entered was incorrect. (Is it possible that your Caps Lock is on?)'
	'This account is locked.  <font color=red>Please contact support.</font>'
	'Error: Login failed.'

The last message should never come up.  If it does, please notify support.
	 
-->	 

<head>
<script language="JavaScript" src="https://leashtime.com/check-form.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script language="JavaScript">
if(document.leashtimelogin) document.leashtimelogin.user_name.focus();

setPrettynames('user_name','Username','user_pass','Password');

function autoSubmit(event) {
  if(event.keyCode == 13) submitIfValid();
}

function logout(){
    $.ajax({
		    type: 'GET',
		    url: 'https://leashtime.com/logout.php',
		    crossDomain: true,
		    dataType: 'json',
		    success: function(responseData, textStatus, jqXHR) {
		       document.location.href='#OUTFILENAME#?message=Logged+out.';
		    },
		    error: function (responseData, textStatus, errorThrown) {
		        alert('POST failed.');   // Object.getOwnPropertyNames(obj)
		        var p = '';
		        for (var prop in responseData) {
						  p += "responseData." + prop + " = " + responseData[prop] + "\n";
						}
		        alert(p);
		        alert('ERROR THROWN: '+errorThrown);
		    }
});
}

function submitIfValid(){
  if(MM_validateForm('user_name','','R','user_pass','','R')) {
		var params = 'json=1&user_name='+document.getElementById('user_name').value
    													+'&user_pass='+document.getElementById('user_pass').value
    													+'&bizid='+document.getElementById('bizid').value;
    //alert(params);
		$.ajax({
				type: 'POST',
				url: 'https://leashtime.com/login.php',
				crossDomain: true,
				data: params,
				dataType: 'json',
				success: function(responseData, textStatus, jqXHR) {
						//alert(responseData.status);
						afterlogin(null, responseData);
				},
				error: function (responseData, textStatus, errorThrown) {
						alert('POST failed.');   // Object.getOwnPropertyNames(obj)
						var p = '';
						for (var prop in responseData) {
							p += "responseData." + prop + " = " + responseData[prop] + "\n";
						}
						alert(p);
						alert('ERROR THROWN: '+errorThrown);
				}
		});
	}
}

function afterlogin(argument, responsejson) {
	var errormessage;
	var result = typeof responsejson == 'String' ? JSON.parse(responsejson) : responsejson;
	// possible results:
	// {"status":"failed","message":"bad login"}
	// {"status":"failed","message":"bad login","hint":"caps lock"}
	// {"status":"failed","message":"account locked"}
	// {"status":"ok"}
	if(result.status == 'ok') {
		#SUCCESS#
	}
	else {
		if(result.message == "bad login") {
			resultmessage = 'The username or password entered was incorrect.';
			if(result.hint == "caps lock") resultmessage += '<br>(Is it possible that your Caps Lock is on?)';
		}
		else if(result.message == "account locked") {
			resultmessage = "This account is locked.  <font color=red>Please contact support.</font>";
		}
		else {
			resultmessage = "Error: Login failed.";
		}
		if(result.params) resultmessage += '<br>'+result.params;
		document.location.href = '#OUTFILENAME#?message='+encodeURIComponent(resultmessage).replace(/%20/gi,"+");
	}
}

</script>
</head>
<body bgcolor=LemonChiffon>

<?php if($_REQUEST['message']) echo $_REQUEST['message'].'<p>';
	 else if($_REQUEST['success']) {
		 echo "You are logged in.";
		 echo "<p><a href=# onclick='logout()'>Logout</a>";
		 exit;
	 }
?>
<form name='leashtimelogin'>
<input type="hidden" id="bizid" name="bizid" value="MYBIZID">
<input type="hidden" id="json" name="json" value="1">

<table style="width:390px" >
<tr>
				<td>Username</td>
				<td align="left"><input type="text" id="user_name" name="user_name" size="25" maxlength="65"  autocomplete='off'/></td>
</tr>
<tr>
				<td>Password</td>
				<td align="left"><input type="password" id="user_pass" name="user_pass" size="25" maxlength="30" onKeyup='autoSubmit(event)'  autocomplete='off'/></td>
</tr>
<tr>
				<td>&nbsp;</td>
				<td align="left">
					 <input type='button' value="Login" onclick="submitIfValid()">
				</td>
</tr>
<tr>
		<td colspan="2" align="center"><br /><hr />Click <a target='_blank' href="https://leashtime.com/temp-password-assignment.php?bizid=MYBIZID"><b>here</b></a> if you have forgotten your password.</td>
</tr>
</table>

</form>

OUTPUT;

require_once "common/init_session.php";
require_once "common/init_db_common.php";
$bizid = $_SESSION["bizptr"] ? $_SESSION["bizptr"] : $_GET['bizid'];
if(!$bizid) {
	echo "A business ID must be supplied.";
	exit;
}
if($_SESSION["bizptr"]) $bizname = $_SESSION['preferences']['bizName'];
if(!$bizname) $bizname = $_SESSION["bizname"];
if(!$bizname) $bizname = 'BUSINESS NAME NOT FOUND.';

$outfilename = $popup ? "login-embedded-form-pop-$bizid.php" : "login-embedded-form-replace-$bizid.php";
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=$outfilename ");
$output = str_replace('BIZNAME', $bizname, $output);
$output = str_replace('MYBIZID', $bizid, $output);
$output = str_replace('#SUCCESS#', $success, $output);
$output = str_replace('#BEHAVIOR#', $behavior, $output);
$output = str_replace('#OUTFILENAME#', $outfilename, $output);
echo $output;
