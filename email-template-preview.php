<? // email-template-preview.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
//require_once "email-template-fns.php";
require_once "preference-fns.php";
//require_once "client-fns.php";

$locked = locked('o-');

if($_REQUEST['cache']) $body = fetchPreference('emailtemplatepreview');
else $body = $_REQUEST['body'];
$subject = $_REQUEST['subject'];
$type = $_REQUEST['type'];

if($type == 'provider') 
		$target = array('fname'=>'Fred', 'lname'=>'Walker', 'nickname'=>'Freddie',
									'loginid'=>'freddiew1', 'pass'=>'tangle4');
else 
		$target = array('fname'=>'Joe', 'lname'=>'Client', 
									'cc'=>'Visa #### #### #### 5423 Exp: 12/2019', 'loginid'=>'joe231', 'pass'=>'tangle4'
									);
									
$schedule = <<<SCHEDULE
<style>
 .previewcalendar { background:white;width:100%;border:solid black 2px;margin:5px; }

 .previewcalendar td {border:solid black 1px;width:14.29%;}
 .appday {border:solid black 1px;background:;width:14.29%;vertical-align:top;height:<?= 100 ?>px;}
 .apptable td {border:solid black 0px;}
 .empty {border:solid black 1px;background:white;width:14.29%;vertical-align:top;height:<?= 100 ?>px;}

 .month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

 .dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
 .daynumber {font-size:1.5em;font-weight:bold;text-align:right;width:25px;}
 .apptcontrols {cursor:pointer;float:left;margin-right:3px;height:10px;width:10px; border:solid darkgray 1px;}
 .hiddentable {width:100%;border:solid black 0px;}
 .hiddentable td {border:solid black 0px;}
</style><div style='width:95%'><b>Service Charges: </b>$&nbsp;0.00<br><b>Total Charges: </b>$&nbsp;0.00<br><table style='font-size:1.4em;width:100%;text-align:center;'>
<span id='undeletableAppointments' style='display:none'></span><span id='undeletableSurcharges' style='display:none'></span><table class='previewcalendar'  border=1 bordercolor=black><tr><td class='month' colspan=7>September</td></tr>
<tr><td class='dow'>Sunday</td><td class='dow'>Monday</td><td class='dow'>Tuesday</td><td class='dow'>Wednesday</td><td class='dow'>Thursday</td><td class='dow'>Friday</td><td class='dow'>Saturday</td></tr>
<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td class='appday' id='box_2010-09-03' valign='top'><div class='daynumber'>3</div><table class='apptable'><tr><td style='text-align:left;color:blue'>2 visits</td><td style='text-align:right'></td></tr><tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>9a-11a<br>Dog Walk<br>Andy</td></tr>
<tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>3p-5p<br>Dog Walk <br>Becky</td></tr>
</table></td><td class='empty' id='box_2010-09-04' valign='top'><div class='daynumber'>4</div></td></tr><tr><td class='empty' id='box_2010-09-05' valign='top'><div class='daynumber'>5</div></td><td class='appday' id='box_2010-09-06' valign='top'><div class='daynumber'>6</div><table class='apptable'><tr><td style='text-align:left;color:blue'>2 visits</td><td style='text-align:right'></td></tr><tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>9a-11a<br>Dog Walk<br>Andy</td></tr>
<tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>3p-5p<br>Dog Walk <br>Becky</td></tr>
</table></td><td class='appday' id='box_2010-09-07' valign='top'><div class='daynumber'>7</div><table class='apptable'><tr><td style='text-align:left;color:blue'>2 visits</td><td style='text-align:right'></td></tr><tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>9a-11a<br>Dog Walk<br>Andy</td></tr>
<tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>3p-5p<br>Dog Walk <br>Becky</td></tr>
</table></td><td class='appday' id='box_2010-09-08' valign='top'><div class='daynumber'>8</div><table class='apptable'><tr><td style='text-align:left;color:blue'>2 visits</td><td style='text-align:right'></td></tr><tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>9a-11a<br>Dog Walk<br>Andy</td></tr>
<tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>3p-5p<br>Dog Walk <br>Becky</td></tr>
</table></td><td class='appday' id='box_2010-09-09' valign='top'><div class='daynumber'>9</div><table class='apptable'><tr><td style='text-align:left;color:blue'>2 visits</td><td style='text-align:right'></td></tr><tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>9a-11a<br>Dog Walk<br>Andy</td></tr>
<tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>3p-5p<br>Dog Walk <br>Becky</td></tr>
</table></td><td class='appday' id='box_2010-09-10' valign='top'><div class='daynumber'>10</div><table class='apptable'><tr><td style='text-align:left;color:blue'>2 visits</td><td style='text-align:right'></td></tr><tr><td colspan=2><hr></td></tr><tr><td class='completedtask' style='border: solid black 0px' colspan=2>9a-11a<br>Dog Walk<br>Andy</td></tr>
<tr><td colspan=2><hr></td></tr><tr><td class='noncompletedtask' style='border: solid black 0px' colspan=2>3p-5p<br>Dog Walk <br>Becky</td></tr>
</table></td><td>&nbsp;</td></tr></table></div>
SCHEDULE;
									
echo '<html><head><link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /></head><body>'."\n";
echo preprocessMessage($body, $target, $template, $schedule);


function preprocessMessage($message, $target, $template, $schedule) {
	if(strposAny($message, array('#LOGINID#', '#TEMPPASSWORD#'))) {
		$creds = loginCreds($target);
	}
	if(strpos($message, '#CREDITCARD#') !== FALSE)
		$cc = $target['cc'];
	if($target['clientid'] && strpos($message, '#PETS#') !== FALSE) {
		require_once "pet-fns.php";
		$petnames = getClientPetNames($target['clientid'], false, true);
	}

	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
	$message = mailMerge($message, 
		array(
			'#RECIPIENT#' => "{$target['fname']} {$target['lname']}",
			'#FIRSTNAME#' => $target['fname'],
			'#LASTNAME#' => $target['lname'],
			'#LOGO#' => logoIMG(),
			'#BIZNAME#' => $_SESSION['preferences']['shortBizName'],
			'#BIZID#' => $_SESSION["bizptr"],
			'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
			'#BIZLOGINPAGE#' => "http://leashtime.com/login-page.php?bizid={$_SESSION['bizptr']}",
			'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
			'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
			'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
			'#EMAIL#' => ($target['email'] ? $target['email'] : 'NO EMAIL ADDRESS'),
			'#CREDITCARD#' => $cc,
			'#LOGINID#' => $creds['loginid'],
			'#TEMPPASSWORD#' => $creds['temppassword'],
			'#PETS#' => $petnames,
			'#LASTVISIT_TILE#' => fancyVisitTile(date('Y-m-d'), '11:00 am-1:30 pm', 'Short Dog Walk', $id='LASTVISIT_TILE', $extraDivStyle=''),
			
			'##FullName##' => "{$target['fname']} {$target['lname']}",
			'##FirstName##' => $target['fname'],
			'##LastName##' => $target['lname'],
			'##Provider##' => "{$target['fname']} {$target['lname']}",
			'##BizName##' => $_SESSION['bizname']			
			));
	$hasHtml = strpos($message, '<') !== FALSE;
	if(!$_REQUEST['noprep']) {
		$message = str_replace("\r", "", $message);
		$message = str_replace("\n\n", "<p>", $message);
		$message = str_replace("\n", "<br>", $message);
	}
	$message = str_replace("#SCHEDULE#", "$schedule", $message);
	return $message;
}	



function loginCreds($target) {
	if(!$target['userid']) return array('loginid'=>'NO LOGIN ID FOUND FOR USER', 'temppassword'=>'NO TEMP PASSWORD FOUND FOR USER');
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$creds = fetchFirstAssoc("SELECT loginid, temppassword, userid FROM tbluser WHERE userid = {$target['userid']} LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
	return $creds;
}

function logoIMG($attributes='') {
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	return $headerBizLogo ? "<img src='https://leashtime.com/$headerBizLogo' $attributes>" :'';
}	


//if(!function_exists('mailMerge')) {
function mailMerge($message, $values) {
	if($message)
		foreach($values as $key => $sub) 
			if($key) $message = str_replace($key, $sub, $message);
	return $message;
}
//}

function strposAny($str, $list) {
	foreach((array)$list as $candidate)
		if(strpos($str, $candidate) !== FALSE)
			return true;
}

