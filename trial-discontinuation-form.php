<?
// trial-discontinuation-form.php
// https://leashtime.com/trial-discontinuation-form.php?token=68a1ecc
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "request-fns.php";

/*
4 modes:
GET (with 'generate' param, for acquiring a URL)
GET (with 'token' param, for offering the form)
include (with $trialDiscontinuationBizId, for acquiring a URL)
POST for generating a request and other actions
*/
// lets us know if they are logged in

//$prestifabulator = 10473;
// https://leashtime.com/trial-discontinuation-form.php?generate=3
if(($id = $_REQUEST['generate']) || ($id = $trialDiscontinuationBizId)) { // $trialDiscontinuationBizId from client-edit.php
/*	if(!$_SESSION['staffuser']) locked('z-');
	echo "https://leashtime.com/trial-discontinuation-form.php?token="
	.sprintf('%x', ($prestifabulator *($prestifabulator + intval($_REQUEST['generate']))));*/
	echo getTrialDiscontinuationFormURL($id);  // client-fns.php
	exit;
}
//extractVars('discontinue,extension,token,hadenoughtime,continue,othersystem,whatothersystem,comments', $_POST);
extract($_POST);
$token = $token ? $token : $_GET['token'];
//$decimaltoken = intval($token, 16);
//$bizid = $decimaltoken / $prestifabulator - $prestifabulator;
$bizid = getBizIDFromTrialDiscontinuationToken($token); // client-fns.php
if(!$bizid) $error = 'This link was not valid.'; /*********/

$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizid'");



$userid = $_SESSION["auth_user_id"]; 
$loginid = $_SESSION["auth_login_id"];
$rights = $_SESSION["rights"];
$sessionbizptr = $_SESSION["bizptr"];
$username = $_SESSION["auth_username"];

//echo "[$userid] [$loginid] [$rights] [$sessionbizptr] [$username] [] []<p>";

if($impersonator = $_SESSION["impersonator"]) {
	$impersonator = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = $impersonator");
	$impersonator = "{$impersonator['fname']} {$impersonator['lname']} ({$impersonator['loginid']}) [{$impersonator['userid']}]";
}
if($staffuser = $_SESSION["staffuser"]) {
	$staffuser = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = $staffuser"); // lname, fname, loginid, userid
	$staffuser = "{$staffuser['fname']} {$staffuser['lname']} ({$staffuser['loginid']}) [{$staffuser['userid']}]";
}

if($userid) {
	$roles = explodePairsLine('o|manager||d|dispatcher||c|client||p|provider||z|staff');
	$role = $roles[$rights[0]];
	if($_SESSION['preferences']) {
		$loggedIn['bizname'] = $_SESSION['preferences']['bizName'];
		if(!$username) {
			reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
			$username = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbl{$role} WHERE userid = $userid LIMIT 1");
			require_once "common/init_db_common.php";
		}
	}

	$sessionDescription = array("SESSION INFO");
	$authUser = "$username, $role of {$_SESSION['bizname']} (biz id: $sessionbizptr) ($loginid) [$userid]";
	if($staffuser) $sessionDescription[] = "Staffer $staffuser logged in to {$_SESSION['bizname']} (biz id: $sessionbizptr)";
	if($impersonator) $sessionDescription[] = "impersonating $authUser";
	else if(!$staffuser) $sessionDescription[] = $authUser;
	$sessionDescription = join("<br>", $sessionDescription);
}
else $sessionDescription = "Submitter was not logged in to LeashTime.";
$message[] = "$sessionDescription<hr>";


$xtra = array();
$xtra[] = extra('token', $token);
$now = date('F j, Y H:i:s');
$xtra[] = extra('Time', $now);
if($userid) {
	$xtra[] = extra('SESSION', 'yes');
	$xtra[] = extra('auth_user_id', $userid);
	$xtra[] = extra('loginid', $loginid);
	$xtra[] = extra('rights', $rights);
	$xtra[] = extra('bizptr', $sessionbizptr);
	$xtra[] = extra('auth_username', $username);
	$xtra[] = extra('staffuser', $staffuser);
	$xtra[] = extra('impersonator', $impersonator);
	$xtra[] = extra('role', $role);
	$xtra[] = extra('logged in bizname', $loggedIn['bizname']);
	$xtra[] = extra('username', $username);
}
else $xtra[] = extra('SESSION', 'no');

function extra($label, $val) {
	return "<extra key='x-label-$label'><![CDATA[$val]]></extra>";
}
//print_r($_POST);
require_once "client-flag-fns.php";
$TRIALFLAG = 1;
$GOLDSTARFLAG = 2;
if($discontinue) {
	$message[] = "DISCONTINUE service for {$biz['bizname']} [$bizid]";
	$message[] = "client ".($hadenoughtime == 'yes' ? 'had' : 'did not have').' enough time to try LeashTime.';
	$message[] = "client is ".($othersystem== 'yes' ? '' : 'not').' going with '
								.($whatothersystem ? $whatothersystem : 'an unspecified system');
	$message[] = '&nbsp;<br>Comments: '.($comments ? '' : 'none');
	if($comments) $message[] = $comments;
	$message = join("<br>", $message);
	
	$message .= "<p>ACTIONS TAKEN:<p>";
	if(!$biz['activebiz']) {
		$message .= "Business #$bizid ({$biz['bizname']} - db: {$biz['db']} was already inactive.";
		$xtra[] = extra('wasalreadyinactive', 1);
	}
	else {
		updateTable('tblpetbiz', array('activebiz'=>0), "bizid=$bizid", 1);
		$message .= "Business #$bizid ({$biz['bizname']} - db: {$biz['db']}) deactivated at $now.";
		$xtra[] = extra('deactivated', $now);
	}
	
	$xtra[] = extra('Request type', 'DISCONTINUE');
	$xtra[] = extra('hadenoughtime', $hadenoughtime);
	$xtra[] = extra('othersystem', $othersystem);
	$xtra[] = extra('whatothersystem', $whatothersystem);
	$xtra[] = extra('comments', $comments);

	// login to LeashTime Customers
	$ltcustomers = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers'");
	reconnectPetBizDB($ltcustomers['db'], $ltcustomers['dbhost'], $ltcustomers['dbuser'], $ltcustomers['dbpass']);
	$ltcust = fetchFirstAssoc("SELECT * FROM tblclient WHERE garagegatecode = $bizid LIMIT 1");
	if(!$ltcust) {
		$message .= "<br>NO LEASHTIME CUSTOMER WAS FOUND FOR BUSINESS ID $bizid";
		$xtra[] = extra('client', 'CLIENT FOR BUSINESS ID $bizid NOT FOUND');
	}
	else {
		$clientid = $ltcust['clientid']; //1|
		$goldStar = fetchRow0Col0(
			"SELECT property 
				FROM tblclientpref 
				WHERE clientptr = $clientid AND property LIKE 'flag_%' AND value LIKE '2|%'", 1);
		$FORMERCLIENTFLAG = 21;
		$DEADLEADFLAG = 8;
		
		$xtra[] = extra('client', "{$ltcust['fname']} {$ltcust['lname']} ({$ltcust['clientid']})");
		// remove the trial flag
		deleteTable('tblclientpref', "clientptr = $clientid AND property LIKE 'flag_%' 
																		AND (value LIKE '$TRIALFLAG|%' OR value LIKE '$GOLDSTARFLAG|%')", 1);
		// add the dead lead flag, with date
		$deadLeadFlag = fetchRow0Col0(
			"SELECT property 
				FROM tblclientpref 
				WHERE clientptr = $clientid AND property LIKE 'flag_%' AND (value LIKE '$DEADLEADFLAG|%' OR value LIKE '$FORMERCLIENTFLAG|%')", 1);
		deleteTable('tblclientpref', "clientptr = $clientid AND property LIKE 'flag_%' AND (value LIKE '$DEADLEADFLAG|%' OR value LIKE '$FORMERCLIENTFLAG|%')", 1);
		if($deadLeadFlag) {
			$message .= "<br>This client was already marked as a Dead Lead or Former Client.";
			$xtra[] = extra('client', 'This client was already marked as a Dead Lead or Former Client.');
		}
		// http://dev.mysql.com/doc/refman/5.0/en/string-functions.html#function_substring-index
		$remainingflags = fetchKeyValuePairs("SELECT SUBSTRING_INDEX(property, '_', -1), value 
				FROM tblclientpref 
				WHERE clientptr = $clientid AND property LIKE 'flag_%'", 1);
		if($remainingflags) ksort($remainingflags);
		// Delete remaining flags for client
		deleteTable('tblclientpref', "clientptr = $clientid AND property LIKE 'flag_%'", 1);
		
		// Add them back in
		$i=1;
		foreach($remainingflags as $val) {
			insertTable('tblclientpref', array('clientptr'=>$clientid, 'property'=>"flag_$i", 'value'=>$val), 1);
			$i++;
		}
		
		$DEADFLAG = $goldStar ? $FORMERCLIENTFLAG : $DEADLEADFLAG;
		insertTable('tblclientpref', array('clientptr'=>$clientid, 'property'=>"flag_$i", 'value'=>"$DEADFLAG|Discontinued $now"), 1);

		$message .= "<br>Flags updated.";
		$xtra[] = extra('Flags updated', '');
		$message .= "<br>Current Flags<br>".clientFlagPanel($clientid, $officeOnly=false, $noEdit=true, $contentsOnly=false, $onClick=null, $includeBillingFlags=false);
		$flags = getClientFlags($clientid, $officeOnly);
		foreach($flags as $flag)
			$flagdescr[] = "{$flag['title']} (flag #{$flag['flagid']}) {$flag['note']}";
		$flagdescr = join("<br>", $flagdescr);
		$xtra[] = extra('current flags', $flagdescr);

		
		// send email to support
		$xtra = "<extrafields>".join('', $xtra)."</extrafields>";
		$request = array(
			'subject'=>"{$biz['bizname']} (@$clientid) [$bizid] has discontinued using LeashTime.",
			'note'=>$message,
			'requesttype'=>'Discontinue',
			'extrafields'=>$xtra,
			'clientptr'=>$clientid);
		saveNewClientRequest($request);
	}
}
else if($extension) {// Extension
	$message[] = "EXTEND Trial for {$biz['bizname']} [$bizid]";
	$message[] = "client ".(!$hadenoughtime ? 'did not have' : 'had').' enough time to try LeashTime.';
	$message[] = "client is ".((!$othersystem && !$whatothersystem) ? 'not' : '').' going with '
								.($whatothersystem ? $whatothersystem : 'an unspecified system');
	$message[] = '&nbsp;<br>Comments: '.($comments ? '' : 'none');
	if($comments) $message[] = $comments;
	$message = join("<br>", $message);
	$xtra[] = extra('Request type', 'EXTEND');
	$xtra[] = extra('hadenoughtime', $hadenoughtime);
	$xtra[] = extra('othersystem', $othersystem);
	$xtra[] = extra('whatothersystem', $whatothersystem);
	$xtra[] = extra('comments', $comments);
	// login to LeashTime Customers
	$ltcustomers = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers'");
	reconnectPetBizDB($ltcustomers['db'], $ltcustomers['dbhost'], $ltcustomers['dbuser'], $ltcustomers['dbpass']);
	$ltcust = fetchFirstAssoc("SELECT * FROM tblclient WHERE garagegatecode = $bizid LIMIT 1");
	if(!$ltcust) {
		$message .= "<br>NO LEASHTIME CUSTOMER WAS FOUND FOR BUSINESS ID $bizid";
		$xtra[] = extra('client', 'CLIENT FOR BUSINESS ID $bizid NOT FOUND');
	}
	else {
		$clientid = $ltcust['clientid']; //1|
		$xtra[] = extra('client', "{$ltcust['fname']} {$ltcust['lname']} ({$ltcust['clientid']})");
		$message .= "&nbsp;<br>Current Flags<br>".clientFlagPanel($clientid, $officeOnly=false, $noEdit=true, $contentsOnly=false, $onClick=null, $includeBillingFlags=false);
		$flags = getClientFlags($clientid, $officeOnly);
		foreach($flags as $flag)
			$flagdescr[] = "{$flag['title']} (flag #{$flag['flagid']}) {$flag['note']}";
		$flagdescr = join("<br>", $flagdescr);
		$xtra[] = extra('current flags', $flagdescr);
		// send email to support
		$xtra = "<extrafields>".join('', $xtra)."</extrafields>";
		$request = array(
			'subject'=>"{$biz['bizname']} (@$bizid) has requested a LeashTime Trial extension.",
			'note'=>$message,
			'requesttype'=>'Extension',
			'extrafields'=>$xtra,
			'clientptr'=>$clientid);
		saveNewClientRequest($request);
	}
}
//$extraBodyStyle = 'background-image:none;';
require_once "frame-bannerless.php";
?>
<div style='background:white;position:relative;left:30px;top:30px;border:solid gray 1px;width:750px;padding:5px;padding-left:10px;padding-bottom:10px;';>
<h2>Thanks for Trying LeashTime</h2>
<img src='art/lightning-smile-small.jpg' style='float:right;'>
<?
if($discontinue) {
?>
<style>p {font-size:1.1em;}</style>
<p>You have closed down your LeashTime Trial.  If you change your mind or wish to use LeashTime in the future, please feel free to contact us at
<p><a href='mailto:support@leashtime.com'>support@leashtime.com</a>
<p>Best of luck to you from the LeashTime Team!
<?
}
else if($extension) {
?>
<style>p {font-size:1.1em;}</style>
<p>We have received your request to extend your LeashTime Trial.<p>We will contact you shortly.  In the meanwhile, if you have any questions, please write to us at
<p><a href='mailto:support@leashtime.com'>support@leashtime.com</a>
<p>Best of luck to you!
<?
}
else { // form
?>
<style>p {font-size:1.1em;}</style>
<p>We appreciate the time you have taken to try out LeashTime with <?= $biz['bizname'] ?>.  Use this form to discontinue your LeashTime trial or to request a trial extension.
<form name='responseform' method='POST'>
<?
hiddenElement('discontinue', '');
hiddenElement('extension', '');
hiddenElement('token', $token);
?>
<table>
<?
$options = array('yes'=>'yes', 'no'=>'no');
radioButtonRow('Have you had enough time to try LeashTime?', 'hadenoughtime', $value='yes', $options);
labelRow('Would you like more time to try LeashTime?', '', "<input type='button' value='Yes!' onclick='requestExtension()' style='color:black;background:lightgreen;font-weight:bold;'> <i>(Enter a comment below, if you like.)</i>", null, null, null,  null, $rawValue=true);
echo "<tr><td colspan=2 style='border-bottom:solid gray 1px;'>&nbsp;</td</tr>";
radioButtonRow('Have you chosen to use another system instead <br>of LeashTime? ', 'othersystem', $value='no', $options);
inputRow('If yes, what system?', 'whatothersystem', $value=null, $labelClass=null, $inputClass='emailInput', $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
?>
<tr><td colspan=2><input type='button' value='Discontinue Trial' onclick='requestDiscontinue()' style='color:black;background:red;font-weight:bold;'>
<?
textRow('Comments', 'comments', $value=null, $rows=3, $cols=80, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
?>
</table>
</form>
</div>
<script language='javascript'>
function requestDiscontinue() {
	if(!confirm('This will end your LeashTime trial.  Are you sure?')) return;
	document.getElementById('discontinue').value=1;
	document.responseform.submit();
}
function requestExtension() {
	document.getElementById('extension').value=1;
	document.responseform.submit();
}
</script>
<?
}
/*
biz_flag_1 	1|art/flag-danger.jpg|Trial period
biz_flag_2 	1|art/flag-yellow-star.jpg|Live
biz_flag_3 	1|art/flag-dog-stewie.jpg|Prospect
biz_flag_4 	1|art/flag-dollar.jpg|Free User
biz_flag_5 	1|art/flag-asterisk.jpg|Require features
biz_flag_6 	1|art/flag-alarm-red.jpg|WARNING
biz_flag_7 	1|art/flag-gorilla.jpg|APSE
biz_flag_8 	1|art/flag-parking-no.jpg|Dead Lead
biz_flag_9 	1|art/flag-zblue-6.jpg|Six-Figure Pet Sitting Acad...
biz_flag_10 	1|art/flag-zblue-0.jpg|Pay by credit card
biz_flag_11 	1|art/flag-zblue-1.jpg|Pay by echeck
biz_flag_12 	1|art/flag-zblue-2.jpg|Paypal
biz_flag_13 	1|art/flag-zblue-3.jpg|Regular check
biz_flag_14 	1|bizfiles/biz_68/flags/flag-wtf.jpg|wtf
biz_flag_15 	1|art/flag-guinea-pig.jpg|2 month trial
biz_flag_16 	1|art/flag-beachball.jpg|trial on hold
biz_flag_17 	1|art/flag-cat-tuxedo.jpg|ask Jody - MN business */
