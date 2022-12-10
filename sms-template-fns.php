<? // sms-template-fns.php

function ensureSMSTemplatesTableExists() {
	doQuery(
	"CREATE TABLE IF NOT EXISTS `tblsmstemplate` (
	  `templateid` int(11) NOT NULL AUTO_INCREMENT,
	  `label` varchar(255) NOT NULL,
	  `body` text CHARACTER SET utf8,
	  `targettype` varchar(10) NOT NULL COMMENT 'client/provider/prospect/staff',
	  `active` tinyint(1) NOT NULL DEFAULT '1',
	  `extratokens` varchar(255) NOT NULL,
	  PRIMARY KEY (`templateid`),
	  UNIQUE KEY `label` (`label`,`targettype`)
	) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=102 ;", 1);
	
}

function preprocessTemplateMessage($message, $target, $template) {
	// NOTE: getClientSchedule, figureClientPrice, ccDescription are still in email-template-fetch.com
	if(strposAny($message, array('#LOGINID#', '#TEMPPASSWORD#')))
		$creds = templateLoginCreds($target);
	if(strpos($message, '#CREDITCARD#') !== FALSE)
		$cc = ccDescription($target);
	if($target['clientid'] && strpos($message, '#PETS#') !== FALSE) {
		require_once "pet-fns.php";
		if($_REQUEST['appointment']) $petnames = getAppointmentPetNames($_REQUEST['appointment'], $petnames=null, $englishList=true);
		else $petnames = getClientPetNames($target['clientid'], false, true);
	}
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
			
	$merges = array(
			'#RECIPIENT#' => "{$target['fname']} {$target['lname']}",
			'#FIRSTNAME#' => $target['fname'],
			'#LASTNAME#' => $target['lname'],
			// #EMBEDDEDLOGOSRC# is handled as an attachment in comm-composer and email-swiftmailer-fns.php
			'#BIZNAME#' => $_SESSION['preferences']['shortBizName'],
			'#BIZID#' => $_SESSION["bizptr"],
			'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
			'#BIZLOGINPAGE#' => "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}",
			'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
			'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
			'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
			'#CREDITCARD#' => $cc,
			'#LOGINID#' => $creds['loginid'],
			'#TEMPPASSWORD#' => $creds['temppassword'],
			'#PETS#' => $petnames,
			'#EMAIL#' => $target['email'],
			'#DATE#' => longDayAndDate(strtotime($target['received'])), // prospect request
			'#ADMINONLY#' => '', // for MANAGERS/DISPATCHERS
			'##Provider##' => $corrName,
			'##BizName##' => $_SESSION['bizname']			
			);
	if($_REQUEST['clientrequest']) {
		require_once "request-fns.php";
		$request = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = '{$_REQUEST['clientrequest']}' LIMIT 1", 1);
		$merges['#REQUESTTYPE#'] = $requestTypes[$request['requesttype']];
		$merges['#DATE#'] = longDayAndDate(strtotime($request['received']));

// #######################################################

		if(in_array($request['requesttype'], array('cancel', 'uncancel'))) {
			//day_2012-03-05 	sole_136405
			$requestSummary = "You requested that we {$request['requesttype']} ";
			$scope = $request['scope'];
			if(strpos($scope, 'day_') === 0) 
				$requestSummary .= "all visits on ".longDayAndDate(strtotime(substr($scope, strlen('day_'))));
			else {
				$appt = 
					fetchFirstAssoc("SELECT date, timeofday FROM tblappointment WHERE appointmentid = ".substr($scope, strlen('sole_'))." LIMIT 1", 1);
				$requestSummary .= "a visit on ".longDayAndDate(strtotime($appt['date']))." at {$appt['timeofday']}";
			}
		}

		if($request['requesttype'] == 'change') {
			//day_2012-03-05 	sole_136405
			$requestSummary = "You requested that we change ";
			$scope = $request['scope'];
			if(strpos($scope, 'day_') === 0) 
				$requestSummary .= "all visits on ".longDayAndDate(strtotime(substr($scope, strlen('day_'))));
			else {
				$appt = 
					fetchFirstAssoc("SELECT date, timeofday FROM tblappointment WHERE appointmentid = ".substr($scope, strlen('sole_'))." LIMIT 1", 1);
				$requestSummary .= "a visit on ".longDayAndDate(strtotime($appt['date']))." at {$appt['timeofday']}";
			}
			$requestSummary .= ".<p>You wrote:<p>".(trim($request['note']) ? "<i>".trim($request['note'])."</i>" : "<i>You gave us no instructions.</i>");
		}

// #######################################################

		
		$merges['#REQUESTSUMMARY#'] = $requestSummary;
	}

	$message = mailMerge($message, $merges);

	if(FALSE && strpos($message, '#SCHEDULE#') !== FALSE)
		$message = mergeUpcomingSchedule($message, $target);
	return $message;
}

if(!function_exists('mailMerge')) {
function mailMerge($message, $values) {
	if($message)
		foreach($values as $key => $sub) 
			if($key) $message = str_replace($key, $sub, $message);
	return $message;
}
}

if(!function_exists('strposAny')) {
	function strposAny($str, $list) {
		foreach((array)$list as $candidate)
			if(strpos($str, $candidate) !== FALSE)
				return true;
	}
}

function templateLoginCreds($target) {
	if(!$target['userid']) return array('loginid'=>'NO LOGIN ID FOUND FOR USER', 'temppassword'=>'NO TEMP PASSWORD FOUND FOR USER');
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$creds = fetchFirstAssoc("SELECT loginid, temppassword, userid FROM tbluser WHERE userid = {$target['userid']} LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
	return $creds;
}


function getSMSTemplates($type) {
	return fetchKeyValuePairs("SELECT label, templateid FROM tblsmstemplate WHERE targettype = '$type' ORDER BY label", 1);
}