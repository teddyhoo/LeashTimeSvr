<? // comm-composer-fns.php

function preprocessMessage($message, $target, $template=null, $noHTMLConversion=false) {
	if(strposAny($message, array('#LOGINID#', '#TEMPPASSWORD#')) && $target) {
		if(!$target['userid'] && ($target['clientid'] || $target['providerid'])) {
			$type = $target['clientid'] ? 'client' : 'provider';
			$targetid =  $target['clientid'] ? $target['clientid'] : $target['providerid'];
			$target['userid'] = fetchRow0Col0("SELECT userid FROM tbl{$type} WHERE {$type}id = $targetid LIMIT 1");
		}
		$creds = loginCreds($target);
	}
	if(strpos($message, '#CREDITCARD#') !== FALSE && $target)
		$cc = ccDescription($target);
	if(strpos($message, '#EPAYMENT#') !== FALSE && $target)
		$epayment = ePayDescription($target);
	if(strpos($message, '#PETS#') !== FALSE && $target) {
		require_once "pet-fns.php";
		$petnames = $target['clientid'] ? getClientPetNames($target['clientid'], false, true) : /* prospect */ 'your pets';
	}
	if($_SESSION['auth_user_id']) $managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
	$localPrefs = $_SESSION['preferences'] ? $_SESSION['preferences'] : fetchKeyValuePairs("SELECT * FROM tblpreference", 1);
	$message = mailMerge($message, 
		array(
			'#RECIPIENT#' => "{$target['fname']} {$target['lname']}",
			'#FIRSTNAME#' => $target['fname'],
			'#LASTNAME#' => $target['lname'],
			'#CLIENTID#' => $target['clientid'],
			'#LOGO#' => logoIMG(),
			'#BIZNAME#' => $localPrefs['shortBizName'],
			'#BIZID#' => $_SESSION["bizptr"],
			'#BIZHOMEPAGE#' => $localPrefs['bizHomePage'],
			'#BIZLOGINPAGE#' => "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}",
			'#BIZEMAIL#' => $localPrefs['bizEmail'],
			'#BIZPHONE#' => $localPrefs['bizPhone'],
			'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
			'#CREDITCARD#' => $cc,
			'#EPAYMENT#' => $epayment,
			'#LOGINID#' => $creds['loginid'],
			'#TEMPPASSWORD#' => $creds['temppassword'],
			'#PETS#' => $petnames,
			'#EMAIL#' => $target['email'],
			
			'##FullName##' => "{$target['fname']} {$target['lname']}",
			'##FirstName##' => $target['fname'],
			'##LastName##' => $target['lname'],
			'##Provider##' => "{$target['fname']} {$target['lname']}",
			'##BizName##' => $_SESSION['bizname']			
			));
	//$message = str_replace('#SENDER#', $signature, $message);
	//echo $body.'<p>';
	//$hasHtml = strpos($message, '<') !== FALSE;
	if(!$noHTMLConversion) { // yuck
		$message = str_replace("\r", "", $message);
		if(strpos($message, '<p>') === FALSE) $message = str_replace("\n\n", "<p>", $message);
		if(strpos($message, '<br>') === FALSE) $message = str_replace("\n", "<br>", $message);
	}
	if($template['label'] == '#STANDARD - Upcoming Schedule' && $target)
		$message = mergeUpcomingSchedule($message, $target);
	return $message;
}	

if(!function_exists('strposAny')) {
	function strposAny($str, $list) {
		foreach((array)$list as $candidate)
			if(strpos($str, $candidate) !== FALSE)
				return true;
	}
}

function ccDescription($target) {
	require_once 'cc-processing-fns.php';
	if(!$target['clientid']) return "ERROR: CREDITCARD info available only for clients.";
	$cc = getClearCC($target['clientid']);
	if(!$cc) return "No credit card on record.";
	return $cc['company'].' **** **** **** '.$cc['last4'].' Exp: '.expirationDate($cc['x_exp_date']);
}

function ePayDescription($target) {
	require_once 'cc-processing-fns.php';
	if(!$target['clientid']) return "ERROR: E-Payment info available only for clients.";
	$source = getClearPrimaryPaySource($target['clientid']);
	if(!$source) return "No e-payment source on record.";
	if($source['acctnum']) return "E-checking account: {$source['acctnum']}";
	else if($source['last4']) 	
		return $source['company'].' **** **** **** '.$source['last4'].' Exp: '.expirationDate($source['x_exp_date']);
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

function mergeUpcomingSchedule($message, $target) {
	$start = date('Y-m-d', strtotime($_POST['start']));
	$end = date('Y-m-d', strtotime($_POST['end']));
	$schedule = getClientSchedule($target['clientid'],$start, $end);
	$message = str_replace("\r", "", $message);
	$message = str_replace("\n\n", "<p>", $message);
	$message = str_replace("\n", "<br>", $message);
	$message = str_replace('#SCHEDULE#', $schedule, $message);
	return $message;
}

function getClientSchedule($clientid, $start, $end) {
	global 	$userRole, $suppressNoVisits;

	$appts = fetchAssociationsKeyedBy("SELECT * FROM tblappointment WHERE clientptr = $clientid AND date >= '$start' AND date <= '$end'", 'appointmentid');
	$price = figureClientPrice($appts, $clientid, $start, $end);
	foreach($appts as $appt) if(!$appt['canceled']) $uncanceledAppts[] = $appt;
	$appts = $uncanceledAppts;
	require_once "appointment-calendar-fns.php";
	ob_start();
	ob_implicit_flush(0);
	echo "<link rel=\"stylesheet\" href=\"https://{$_SERVER["HTTP_HOST"]}/style.css\" type=\"text/css\" /> 
	<link rel=\"stylesheet\" href=\"https://{$_SERVER["HTTP_HOST"]}/pet.css\" type=\"text/css\" />
<style>
body {background:white;font-size:9pt;padding-left:5px;padding-top:5px;}
</style>"."\n";
	echo logoIMG("align='center'");
	$schedule['startdate'] = $start;
	$schedule['enddate'] = date('Y-m-t', strtotime($end));
	$userRole = 'c';
	$suppressNoVisits = 1;
	dumpCalendarLooks(100, 'lightblue');
	echo "<div style='width:95%'>";
	echo "<b>Price: </b>".dollarAmount($price);	
	if(getClientPreference($clientid, 'sendScheduleAsList')) { // FALSE && mattOnlyTEST() && 
		//require_once "appointment-calendar-fns.php";
		echo clientVisitList($appts, $forceVisitTimeInclusion=false); // moved to appointment-calendar-fns.php
	}
	else appointmentTable($appts, $schedule, $editable=false, $allowSurchargeEdit=false, $showStats=false, $includeApptLinks=false, $surcharges=null);
	echo "</div>";
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function logoIMG($attributes='') {
	$bizFileDirectory = $_SESSION["bizfiledirectory"];
	if(!$bizFileDirectory && $_SESSION["bizptr"]) {  // e.g., during temporary payment session
		$bizFileDirectory = "bizfiles/biz_{$_SESSION["bizptr"]}/";
	}
	$headerBizLogo = getHeaderBizLogo($bizFileDirectory);
	return $headerBizLogo ? "<img src='https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo' $attributes>" :'';
}	

function logoIMGSrc($attributes='') {
	$bizFileDirectory = $_SESSION["bizfiledirectory"];
	if(!$bizFileDirectory && $_SESSION["bizptr"]) {  // e.g., during temporary payment session
		$bizFileDirectory = "bizfiles/biz_{$_SESSION["bizptr"]}/";
	}
	$headerBizLogo = getHeaderBizLogo($bizFileDirectory);
	return $headerBizLogo ? "https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo" :'';
}	

function figureClientPrice($appts, $clientid, $start, $end) {
	require_once "tax-fns.php";
	$clientTaxRates = getClientTaxRates($clientid);
	foreach($appts as $apptid => $appt) if(!$appt['canceled']) $uncanceled[] = $apptid;
	$discounts = array();
	if($uncanceled) $discounts = fetchAssociationsKeyedBy("SELECT * FROM relapptdiscount WHERE appointmentptr IN (".join(',', $uncanceled).")", 'appointmentptr');
	foreach($appts as $apptid => $appt) {
		if($appt['canceled']) continue;
//echo "($apptid) ch: {$appt['charge']} adj: {$appt['adjustment']}  discounts: ".print_r($discounts[$apptid],1)."<br>";	
		$discount = $discounts[$apptid] ? $discounts[$apptid]['amount'] : 0;
		$charge = $appt['charge']+$appt['adjustment']-$discount;
		$tax = round($charge * $clientTaxRates[$appt['servicecode']]) / 100;
		$sum += $charge + $tax;
	}
	$surchargesum = fetchRow0Col0("SELECT sum(charge) FROM tblsurcharge WHERE clientptr = $clientid AND canceled IS NULL AND date >= '$start' AND date <= '$end'");
	return $sum +$surchargesum;
}


