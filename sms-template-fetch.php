<? // sms-template-fetch.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "sms-template-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";

$locked = locked('o-');
$template = fetchFirstAssoc("SELECT * FROM tblsmstemplate WHERE templateid = {$_REQUEST['id']} LIMIT 1");

$body .= $template['body'];

if($_REQUEST['client']) $target = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = {$_REQUEST['client']} LIMIT 1");
else if($_REQUEST['provider']) $target = fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid = {$_REQUEST['provider']} LIMIT 1");
else if($_REQUEST['prospect']) $target = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = '{$_REQUEST['prospect']}' LIMIT 1");
else if($_REQUEST['user']) {
	$mgrs = getManagers();
	$target = $mgrs[$_REQUEST['user']];
}
if($target) $body = preprocessTemplateMessage($body, $target, $template);


echo json_encode(array('body'=>$body));

// MOVED to email-template-fns.php: function preprocessTemplateMessage($message, $target, $template) {

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
	appointmentTable($appts, $schedule, $editable=false, $allowSurchargeEdit=false, $showStats=false, $includeApptLinks=false, $surcharges=null);
	echo "</div>";
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
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

function ccDescription($target) {
	require_once 'cc-processing-fns.php';
	if(!$target['clientid']) return "ERROR: CREDITCARD info available only for clients.";
	$cc = getClearCC($target['clientid']);
	if(!$cc) return "No credit card on record.";
	return $cc['company'].' **** **** **** '.$cc['last4'].' Exp: '.expirationDate($cc['x_exp_date']);
}

/*function loginCreds($target) {
	if(!$target['userid']) return array('loginid'=>'NO LOGIN ID FOUND FOR USER', 'temppassword'=>'NO TEMP PASSWORD FOUND FOR USER');
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$creds = fetchFirstAssoc("SELECT loginid, temppassword, userid FROM tbluser WHERE userid = {$target['userid']} LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
	return $creds;
}*/

