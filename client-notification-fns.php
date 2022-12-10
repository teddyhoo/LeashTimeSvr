<? //client-notification-fns.php

require_once "comm-fns.php";

function sendWeeklyClientSchedules($starting, $delayed=false) {
	global $prefs, $scriptPrefs;
	$prefs = fetchKeyValuePairs("SELECT * FROM tblpreference");
	$scriptPrefs = $prefs;  // used by email-fns
	// if today is the right day of week, send weekly schedules to clients registered to receive them
	if($prefs['scheduleDay'] != date('l')) return;
	// find clients to send to
	if($prefs['autoEmailClientSchedule']) { // find all clients where autoEmailClientSchedule !== '0'
		$decliners = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property = 'autoEmailClientSchedule' AND value = 0");
		$clients = fetchAssociations(
									"SELECT fname, lname, email, clientid, CONCAT_WS(' ', fname, lname) as clientname
										FROM tblclient 
										WHERE active = 1 and email IS NOT NULL AND clientid NOT IN ("
										.join(',', $decliners).")");
	}
	else $clients = fetchAssociations(
			"SELECT fname, lname, email, clientid, CONCAT_WS(' ', fname, lname) as clientname
				FROM tblclient 
				LEFT JOIN tblclientpref ON clientptr = clientid
				WHERE active = 1 and email IS NOT NULL AND property = 'autoEmailClientSchedule' AND value = '1'");

	foreach($clients as $person) {
		sendClientSchedule($person, $starting, $delayed);
	}
}
		
function sendClientSchedule($person, $starting, $delayed=false) {
	global $prefs, $userRole, $displayOnly;
	$prefs = $prefs ? $prefs : (isset($_SESSION['preferences']) ? $_SESSION['preferences'] : array());
//echo "[".isset($_SESSION['preferences'])."] ".print_r($prefs, 1);exit;			
	$client = $person['clientid'];
	$span = "the week starting ".shortDate(strtotime($starting));
	
	$windowTitle = 'Client Schedule';
	$extraBodyStyle = 'background:white';
	$extraHeadContent = "<base href='https://{$_SERVER["HTTP_HOST"]}/'>
  <link rel='stylesheet' href='https://{$_SERVER["HTTP_HOST"]}/style.css' type='text/css' /> 
  <link rel='stylesheet' href='https://{$_SERVER["HTTP_HOST"]}/pet.css' type='text/css' />";
	$max_rows = -1;
	$ending = date('Y-m-d', strtotime('+6 days', strtotime($starting)));
	$displayOnly = true;
	$userRole = 'c';
	ob_start();
	ob_implicit_flush(0);
	require "frame-bannerless.php";
	echo getClientSchedule($client, $starting, $ending);
	$body = ob_get_contents();
	ob_end_clean();
	$bizHomePage = $prefs['bizHomePage'];
	$bizHomePage = $bizHomePage ? ": <a href='$bizHomePage'>$bizHomePage</a>." : '.';
	$body = "Dear ".$person['clientname'].",<p>Here is your weekly schedule.  To make changes, please give us a call or login to our website$bizHomePage"
					."<p>Kind regards,<p>{$prefs['bizName']}<p>"
					.$body;
//echo $body;exit;					
	if($delayed) return enqueueEmailNotification($person, "Your schedule for $span", $body, null, null, true);
	else return notifyByEmail($person, "Your schedule for $span", $body, null, null, true);
}


// NEED TO REFACTOR THE FOLLOWING (TAKEN FROM email-broadcast.php)
function getClientSchedule($clientid, $start, $end) {
	global 	$userRole, $suppressNoVisits;

	$appts = fetchAssociationsKeyedBy("SELECT * FROM tblappointment WHERE clientptr = $clientid AND date >= '$start' AND date <= '$end'", 'appointmentid');
	$price = figureClientPrice($appts, $clientid, $start, $end);
	foreach($appts as $appt) if(!$appt['canceled']) $uncanceledAppts[] = $appt;
	$appts = $uncanceledAppts;
	require_once "appointment-calendar-fns.php";
	ob_start();
	ob_implicit_flush(0);
	echo '<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
	<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" />
<style>
body {background:white;font-size:9pt;padding-left:5px;padding-top:5px;}
</style>'."\n";
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

function logoIMG($attributes='') {
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	return $headerBizLogo ? "<img src='https://leashtime.com/$headerBizLogo' $attributes>" :'';
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

