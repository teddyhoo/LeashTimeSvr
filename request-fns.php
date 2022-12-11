<?
//request-fns.php

/*
<form method='POST' name='prospectinforequest' action='http://iwmr.info/petbiz/prospect-request.php'>
<input type='hidden' id='pbid' name='pbid' value='1'> <!-- Please insert your LeashTime biz ID where it says '1'. -->
<input type='hidden' id='goback' name='goback' value='http://iwmr.info/petbiz/dogslife/index.html'> <!-- Please supply the address to return to after the message is sent. -->
<table>
<tr><td><label for='fname'>First Name:</label></td><td><input id='fname' name='fname' maxlength=45></td></tr>
<tr><td><label for='lname'>Last Name:</label></td><td><input id='lname' name='lname' maxlength=45></td></tr>
<tr><td><label for='phone'>Phone:</label></td><td><input id='phone' name='phone' maxlength=45></td></tr>
<tr><td><label for='whentocall'>Best time for us to call:</label></td><td><input id='whentocall' name='whentocall' maxlength=45></td></tr>
<tr><td><label for='email'>Email:</label></td><td><input id='email' name='email' maxlength=60></td></tr>
<tr><td><label for='address'>Address:</label></td><td><textarea id='address' name='address' rows=4 cols=20></textarea></td></tr>
<tr><td colspan=2><label for='pets'>Tell us about your pets (names, kind):</label></td></tr>
<tr><td colspan=2><textarea id='pets' name='pets' rows=3 cols=40></textarea></td></tr>
<tr><td colspan=2><label for='note'>How can we help you?</label></td></tr>
<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=40></textarea></td></tr>
<tr><td colspan=2><input type=button value='Send Request' onClick='checkAndSend()'></td></tr>
</table>

*/
require_once "common/db_fns.php";
//$newVersion = dbTEST('dogslife');

function getRequestFields() {
	static $requestFields = array('requestid','fname','lname','phone','whentocall','email',
																	'address','street1', 'street2', 'city','state','zip',
																	'pets','note','officenotes','extrafields','clientptr',
																	'providerptr', 'resolved', 'requesttype', 'scope');
	return $requestFields;
}


$requestTypes = array('cancel'=>'Cancellation', 'change'=>'Change', 'uncancel'=>'Un-Cancel', 'Prospect'=>'Prospect', 
											'Profile'=>'Profile change', 'General'=>'General', 'Schedule'=>'Schedule', 
											'NotificationResponse'=>'Notification Response', 'SystemNotification'=>'System Notification',
											'CCPayment'=>'Credit Card Payment',
											'CCSupplied'=>'Credit Card Supplied', 'ACHSupplied'=>'E-checking (ACH) Info Supplied', 
											'CCSupplyFailure'=>'Credit Card Supply Failure', 'ACHSupplyFailure'=>'E-checking (ACH) Info Supply Failure', 
											'CCPaymentFailure'=>'Credit Card Payment by Client Failure', 
											'Reminder'=>'Reminder', 'BillingReminder'=>'Billing Reminder', 'BizSetup'=>"New Business Setup Data",
											'TimeOff'=>'Time Off', 'UnassignedVisitOffer'=>'Unassigned Visit Offer', 'ICInvoice'=>'IC Invoice',
											'Spam'=>'Prospect Spam', 'ValuePackRefills'=>'Value Pack Refills','schedulechange'=>'Schedule Change'); 
// LeashTime Customers only
$ltcustomersOnlyRequestTypes = 
	array('BugReport'=>'Bug Report', 'Comment'=>'Comment', 'Discontinue'=>'Discontinue Service', 'Extension'=>'Extend Trial');
if($db == 'leashtimecustomers') foreach($ltcustomersOnlyRequestTypes as $k=>$v) $requestTypes[$k] = $v;

$requestTypes['VisitReport'] = 'Visit Report';

function notifyStaffOfClientRequest($request) {
	global $mein_host;
	require_once "event-email-fns.php";
	$extraFields = getExtraFields($request);
	$eventType =  $extraFields['eventtype'] ? $extraFields['eventtype'] : 'r';
	$additionalFields = array('note'=>'Note', 'pets'=>'Pets', 'address'=>'Address', 'email'=>'Email', 'phone'=>'Phone', 'whentocall'=>'When to call', 'received'=>'Received');
	$request['received'] = shortDateAndTime('now', 'mil');
	$clientDetails = !$request['clientptr'] ? array() : getOneClientsDetails($request['clientptr'], array('phone','address', 'email'));
	$name = $request['clientptr'] ? $clientDetails['clientname'] : "{$request['fname']} {$request['lname']}";
	if(!$request['address']) {
		if($request['street1'] || $request['street2'] || $request['city'] || $request['state'] || $request['zip']) {
			$addr = array($request['street1'], $request['street2'], $request['city'], $request['state'], $request['zip']);
			$request['address'] = oneLineAddress($addr);
		}
		else if($clientDetails) $request['address'] = $clientDetails['address'];
	}
	$request['phone'] = $request['phone'] 
		? $request['phone'] 
		: ($clientDetails ? $clientDetails['phone'] : '');
	$request['email'] = $request['email'] ? $request['email'] : $clientDetails['email'];
	if($request['email']) $request['email'] = "<a href='mailto:{$request['email']}'>{$request['email']}</a>";
	$label = requestLabel($request);
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	$urlBase = globalURL('index.php');
	$requestLink = "<a href='$urlBase'>request</a>";
	
	$providerphrase = $_SESSION["providerid"] ? " (submitted by {$_SESSION["shortname"]})" : '';
	
	if($request['requesttype'] == 'Spam') {
		$spam = 1;
		$subject = "SPAM Prospective client: $name";
		$msgBody = "$subject $requestLink";
		foreach($request as $key => $value) 
			if(strpos($key, 'x-')===0) {
				if(strpos($key, 'x-std-')===0) ;  // e.g., 'x-std-cellphone2' 
				if(strpos($key, 'x-custom-')===0) ; // e.g., 'x-custom-1' 
				$additionalFields[$key] = substr($key, strrpos($key, '-')+1);
			}
	}
	else if($request['requesttype'] == 'Prospect') {
		if(array_key_exists('i', getEventTypeMenu())) $eventType =  'i';  // introduced 'i' later

		$subject = "Prospective client: $name";
		$requestLink = "<a href='$urlBase'>request</a>";
		$msgBody = "$subject $requestLink";
		foreach($request as $key => $value) 
			if(strpos($key, 'x-')===0) {
				if(strpos($key, 'x-std-')===0) ;  // e.g., 'x-std-cellphone2' 
				if(strpos($key, 'x-pet-')===0) ;  // e.g., 'x-pet-name-2', 'x-pet-type-2'  
				if(strpos($key, 'x-custom-')===0) ; // e.g., 'x-custom-1' 
				$additionalFields[$key] = substr($key, strrpos($key, '-')+1);
				if(mattOnlyTEST()) $additionalFields[$key] = clientProfileField(substr($key, strrpos($key, '-')+1));
			}
	}
	else if($request['requesttype'] == 'VisitReport') {
		$eventType =  'k';  // Sitter-Client Email
		$cname = $clientDetails['clientname'];
		$pname = $extraFields['x-providername'];
		require_once "pet-fns.php";
		$apptpets = fetchRow0Col0("SELECT pets FROM tblappointment WHERE appointmentid = {$extraFields['x-appointmentptr']} LIMIT 1");
		if(in_array($apptpets, array(null, 'All Pets')))
			$apptpets = getClientPetNames($request['clientptr'], $inactiveAlso=false, $englishList=false);
		if($apptpets) {
			$apptpets = " (".truncatedLabel($apptpets, 20).")";
		}
		if($extraFields['x-messageptr'])
			$subject = "Visit Report sent by $pname to $cname$apptpets";
		else 
			$subject = "Review/Approve Visit Report by $pname to $cname$apptpets";
		$requestLink = "<a href='$urlBase'>request</a>";
		$msgBody = "$subject $requestLink";
	}
	else if($request['requesttype'] == 'General') {
		$type = $extraFields['x-subject'] ? $extraFields['x-subject'] : "General";
		$subject = "$type request from: $name$providerphrase";
		if($extraFields) {
			foreach($extraFields as $key => $value) {
				$keyParts = explode('-', $key);
				if(count($keyParts) < 3) continue;
				list($ignore, $ftype, $label) = $keyParts;
				if(strpos($label, 'meetingdate') === 0 || strpos($label, 'meetingtime') === 0) {
					if(strpos($label, 'meetingtime') === 0) continue;
					$n = substr($label, -1);
					if(!$extraFields["x-oneline-meetingdate$n"]) continue;
					$mdate = shortDateAndDay(strtotime($extraFields["x-oneline-meetingdate$n"]));
					$mtime = $extraFields["x-oneline-meetingtime$n"];
					$meetingPhrase[] = "$mdate $mtime";
				}
			}
		}
		$meetingPhrase = $meetingPhrase ? "<p>Meeting requested at ".join(' or ', $meetingPhrase) : '';
	
		$requestLink = "<a href='$urlBase'>$type request</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase.$meetingPhrase;
	}
	else if($request['requesttype'] == 'CCSupplied') {
		$subject = "Credit card has been supplied by: $name$providerphrase";
		$requestLink = "<a href='$urlBase'>Credit card supplied</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'CCPayment') {
		$subject = "Credit card payment has been received from: $name";
		$requestLink = "<a href='$urlBase'>Credit card payment</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails);
	}
	else if($request['requesttype'] == 'ACHSupplied') {
		$subject = "E-checking (ACH) Info has been supplied by: $name$providerphrase";
		$requestLink = "<a href='$urlBase'>E-checking (ACH) Info supplied</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'CCSupplyFailure') {
		$subject = "An attempt to Enter a Credit Card by $name$providerphrase has failed";
		$requestLink = "<a href='$urlBase'>Credit card entry failure</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'CCPaymentFailure') {
		$subject = "An attempt to Pay by Ad-hoc  Credit Card by $name$providerphrase has failed";
		$requestLink = "<a href='$urlBase'>Credit card payment failure</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'ACHSupplyFailure') {
		$subject = "An attempt to Enter ACH Info by $name$providerphrase has failed";
		$requestLink = "<a href='$urlBase'>E-checking (ACH) Info entry failure</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'BillingReminder') {
		$hiddenFields = getHiddenExtraFields($request);
		if($hiddenFields['type'] == 'starting') {
			$sDate = shortNaturalDate(strtotime("+ {$hiddenFields['lookahead']} days"), 'noYear');
			$spec = "Schedule Starting $sDate";
		}
		else $spec = "Schedule Ending Today";
		$subject = "A Billing reminder for $name$providerphrase $spec";
		$requestLink = "<a href='$urlBase'>Billing reminder: $spec</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'BizSetup') {
		require_once "gui-fns.php";
		$subject = "Business Setup Data for {$request['fname']}";
		$requestLink = "<a href='$urlBase'>$subject</a>";
		$msgBody = "$subject:<p>".prettyXML($request['note']);
	}
	else if($request['requesttype'] == 'cancel') {
		$subject = "Request $label from: $name$providerphrase";
		$requestLink = "<a href='$urlBase'>Request $label</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'uncancel') {
		$subject = "Request $label from: $name$providerphrase";
		$requestLink = "<a href='$urlBase'>Request $label</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'change') {
		$subject = "Request $label from: $name$providerphrase";
		$requestLink = "<a href='$urlBase'>Request $label</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
	}
	else if($request['requesttype'] == 'schedulechange') {
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>Request $label</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
		require_once "request-safety.php";
		$msgBody .= "<p>".scheduleChangeDetail($request);
	}
	else if($request['requesttype'] == 'Profile') {
		$subject = "$label request from: $name$providerphrase";
		$requestLink = "<a href='$urlBase'>$label request</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
		$changes = fetchKeyValuePairs("SELECT field, value from tblclientprofilerequest WHERE requestptr = {$request['requestid']}");
		foreach($changes as $change => $value) {
			// exclude version and pet id's
			if($change != 'version' && !($changes['version'] <= 2 && strpos($change, 'petid_') === 0))
				$changekeys[] = clientProfileField($change);
		}
		$msgBody .= "<p>".count($changekeys)." fields changed: <ul><li>".join('<li>', $changekeys)."</ul>";
	}
	else if($request['requesttype'] == 'Schedule') {
		$subject = "Schedule request from: $name$providerphrase";
		$requestLink = "<a href='$urlBase'>Schedule request</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails).$providerphrase;
if($_SESSION['preferences']['enableschedulenoticeoptions']) {
		require_once "client-sched-request-fns.php";
		//$style = $_SESSION['preferences']['schedulenoticestyle'];  // detailed|summary|datesonly
		$style = fetchPreference('schedulenoticestyle');  // Detailed|Summary|Dates Only
		$msgBody .= '<p>'.describeRequestedSchedule($request, $style); 
		unset($request['note']);
}
else {
		$schedule = $request['note'];
		unset($request['note']);
		$parts = explode("\n", $schedule);
		if(isset($parts[2])) $request['note'] = urldecode($parts[2]);
		else unset($request['note']);
		$parts = explode("|", $parts[0]);
		$msgBody .= "<p>Dates: {$parts[0]} to {$parts[1]}";
}
	}
	else if($request['requesttype'] == 'NotificationResponse') {
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>{$request['subject']}</a>";
		$msgBody = "$requestLink from: ".clientLinkForNotification($clientDetails);
	}
	else if(in_array($request['requesttype'], array('SystemNotification', 'ValuePackRefills'))) {
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>$subject</a>";
		$additionalFields = array();
		$msgBody = "$requestLink:<p>{$request['note']}";
		if($extraFields['odappts']) {
			require_once "sms-fns.php";
			require_once "preference-fns.php";
//if($_SESSION['preferences']['enableOverdueArrivalEventType']) $eventType =  'v';
if(fetchPreference('enableOverdueArrivalEventType')) $eventType =  'v';
//if(dbTEST('dogslife,tonkatest,sarahrichpetsitting')) $eventType =  'v';
			// assumed: require_once "stale-appointment-fns.php";
			if(smsEnabled('forLeashTime')) {
				//$smsBody = generateStaleVisitsSMSBody(explode(',', "".$extraFields['odappts']));
				$overdueApptIds = explode(',', "".$extraFields['odappts']);
				$smsBodiesForStaff = generateSeparateStaleVisitsSMSBodies($overdueApptIds);
				if(fetchPreference('enableOverdueVisitSitterSMS')) {
					require_once "comm-fns.php";
					foreach(generateSeparateStaleVisitsSitterSMSPackets($overdueApptIds) as $packet)
						if(is_string($error= notifyByLeashTimeSMS(getProvider($packet['providerptr']), $packet['body'], $media=null)))
							logError("Sitter overdue SMS failed: $error");
				}
			}
		}
	}
	else if(in_array($request['requesttype'], array('Discontinue', 'Extension'))) {
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>{$request['subject']}</a>";
		$additionalFields = array();
		$msgBody = $request['note'];
	}
	else if(in_array($request['requesttype'], array('BugReport', 'Comment'))) {
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>{$request['subject']}</a>";
		$additionalFields = array();
		$msgBody = $request['note'];
	}
	else if(in_array($request['requesttype'], array('UnassignedVisitOffer'))) {
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>{$request['subject']}</a>";
		$additionalFields = array();
		$msgBody = "$requestLink from: {$extraFields['x-label-Requestor']}";
		ob_start();
		ob_implicit_flush(0);
		echo "<table>";
		labelRow('Received:', '', shortDateAndTime(strtotime($request['received'])));
		displayExtraFields($request);
		echo "</table>";
		$msgBody .= ob_get_contents();
		ob_end_clean();
	}
	else if(in_array($request['requesttype'], array('ICInvoice'))) {
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>{$request['subject']}</a>";
		$additionalFields = array();
		$msgBody = "$requestLink from: {$extraFields['x-label-Requestor']}";
		ob_start();
		ob_implicit_flush(0);
		echo "<table>";
		labelRow('Received:', '', shortDateAndTime(strtotime($request['received'])));
		//displayExtraFields($request);
		echo "</table>";
		$msgBody .= ob_get_contents().$request['note'];
		ob_end_clean();
	}
	else if(in_array($request['requesttype'], array('TimeOff'))) {
		$eventType =  't';
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>{$request['subject']}</a>";
		$additionalFields = array();
		$msgBody = "$requestLink from: {$extraFields['x-label-Requestor']}";
		ob_start();
		ob_implicit_flush(0);
		echo "<table>";
		labelRow('Received:', '', shortDateAndTime(strtotime($request['received'])));
		displayExtraFields($request);
		echo "</table>";
		$msgBody .= ob_get_contents();
		ob_end_clean();
	}
	else if(in_array($request['requesttype'], array('Reminder'))) {
		$subject = $request['subject'];
		$requestLink = "<a href='$urlBase'>{$request['subject']}</a>";
		$additionalFields = array();
		$msgBody = $request['note'];
		$hiddenFields = getHiddenExtraFields($request);
		$remHeader[] = "<b>Reminder:</b> $subject";
		if($hiddenFields['clientptr']
			 && $client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = {$hiddenFields['clientptr']}")) {
			$remHeader[] = "<b>Client:</b> {$client['fname']} {$client['lname']}";
			if($client['email']) $remHeader[] = "<b>Email:</b> {$client['email']}";
			require_once "field-utils.php";
			if($phone = primaryPhoneNumber($client)) {
				$phonefield = primaryPhoneField($client);
				$phonefield = $phonefield ? "({$phonefield[0]}) " : '';
				$remHeader[] = "<b>Phone:</b> $phonefield$phone";
			}
			$msgBody = join('<br>', $remHeader)."<p>$msgBody";
		}
		
	}
	if($request['scope']) {
		$status = 
			$request['requesttype'] == 'cancel' ? 1 : (
			$request['requesttype'] == 'uncancel' ? -1 : 0);
		if(mattOnlyTEST() || dbTEST('poochydoos')) 
			$msgBody .= '<p><div style="border:solid black 1px;padding:5px;">'
								.decribeVisitsInScope($request, $status) // canceledonly -1 or uncanceledonly 1 or 0
								.'</div>';
		else if(strpos($request['scope'], 'day_') === 0)
			$msgBody .= " on ".longestDayAndDate(strtotime(substr($request['scope'], strlen('day_'))));
		else {
			require_once "appointment-fns.php";
			$appt = getAppointment(substr($request['scope'], strlen('sole_')), $withNames=true);
			$msgBody .= ": <br>Visit: ".shortDateAndDay(strtotime($appt['date']))." ".$appt['timeofday']." ".$_SESSION['servicenames'][$appt['servicecode']]." ".$appt['provider'];
		}
	}

	foreach($additionalFields as $key => $label) {
		$val = $request[$key];
}
		if(mattOnlyTEST() && $val && $key == 'x-std-referralcode') {
			require_once "referral-fns.php";
			$val = join(' > ', getReferralPath($val));
		}
		if($request[$key]) $msgBody .= "<p>$label:<br>{$val}";
	}

	global $db;
	if($db == 'leashtimecustomers') $msgBody .= "<p>{$_SERVER['REMOTE_ADDR']}";
	require_once "comm-fns.php";
//if(mattOnlyTest()) logError("[SPAM: $spam] notifyStaff($eventType, $subject,...)");	
	if(!$spam) {
		notifyStaff($eventType, $subject, $msgBody);
		if($smsBodiesForStaff) {
			require_once "preference-fns.php";
			foreach($smsBodiesForStaff as $smsBody)
				if(fetchPreference('enableOverdueVisitManagerSMS'))
					notifyStaffBySMS($eventType, $smsBody);
		}
	}
}

function decribeVisitsInScope($request, $status=null) {
	// $status = -1 or 1 or 0
	// -1: canceled only
	// 1: uncanceled only
	// 0: all
	if((mattOnlyTEST() || dbTEST('poochydoos')) && $request['scope']) {
		$scope = explode('_', $request['scope']);
		$label .= $scope[0] == 'sole' ? ' of Appointment' : " of Day's Appointments";
		if($scope[0] == 'sole') {
			$apptids = array($scope[1]);
		}
		else if($scope[0] == 'day') {
			$date = $scope[1];
			if($status) $statusFilter = "AND canceled IS ".($status == -1 ? 'NOT NULL' : 'NULL');
			$apptids = fetchCol0(
				"SELECT appointmentid 
				FROM tblappointment 
				WHERE clientptr = {$request['clientptr']}
					AND date = '$date' $statusFilter", 1);
		}
		$servicelabels = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype", 1);
		require_once "pet-fns.php";
		$allPets = getClientPetNames($request['clientptr'], $inactiveAlso=false, $englishList=false);
		$firstappt = getAppointment($apptids[0], $withNames=true);
		$desc[] = "Client: {$firstappt['client']} ($allPets)";
		$desc[] = "Visit Info:";
		if(!$apptids) $desc[] = "No visits found!";
		else {
			$desc[] = "Date: ".shortDate(strtotime($firstappt['date']));
			foreach($apptids as $apptid) {
				$appt = getAppointment($apptid, $withNames=true);
				$service = $servicelabels[$appt['servicecode']];
				$pets = $appt['pets'];
				$appts[] = "{$appt['timeofday']} $service ($pets) {$appt['provider']}";
			}
			$appts = join('<br>', $appts);
			$desc[] = $appts;
		}
		$desc = join('<p>', $desc);
	}
	return $desc;
}



function anyVisitsInScopeMarkedComplete($source) {
	
	$scope = explode('_', $source['scope']);
	$soleScope = $scope[0] == 'sole';
	if($soleScope) $where = "WHERE appointmentid = {$scope[1]}";
	else if($scope[0] == 'day') 
		$where = "WHERE clientptr = {$source['clientptr']} AND date = '{$scope[1]}'";
	return fetchRow0Col0("SELECT appointmentid FROM tblappointment $where AND completed IS NOT NULL LIMIT 1");
}
	

function visitRequestMemo($source) {
	// preserve request details
	require_once "appointment-fns.php";
	$scope = explode('_', $source['scope']);
	$soleScope = $scope[0] == 'sole';
	if($soleScope) $where = "WHERE appointmentid = {$scope[1]}";
	else if($scope[0] == 'day') 
		$where = "WHERE clientptr = {$source['clientptr']} AND date = '{$scope[1]}'";
	$appts = fetchAssociations(
		"SELECT *, CONCAT_WS(' ',tblprovider.fname, tblprovider.lname) as provider 
		FROM tblappointment 
		LEFT JOIN tblprovider ON providerid = providerptr
		$where ORDER BY date, starttime");
	$date = shortDateAndDay(strtotime($appts[0]['date']));
	$labels = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	//$verb = strtoupper($source['requesttype'][0]).substr($source['requesttype'], 1);
	foreach($appts as $appt) {
		$charge = dollarAmount($appt['charge']+$appt['adjustment']);
		$list[] = "<tr><td>{$appt['provider']}</td><td>$date</td><td>{$appt['timeofday']}</td><td>{$labels[$appt['servicecode']]}</td><td>{$appt['pets']}</td><td>$charge</td></tr>";
	}
	return "<table width=95%>".join("\n", $list)."</table>";
}

function showCancellationTable($source, $uncancel=null, $noButtons=false) {  // moved here from request-edit.php to accommodate notifications
	require_once "client-fns.php";
	require_once "gui-fns.php";
	require_once "client-profile-request-fns.php";
	require_once "client-sched-request-fns.php";
	require_once "client-services-fns.php";
	require_once "client-schedule-fns.php";
	
	$scope = explode('_', $source['scope']);
	$soleScope = $scope[0] == 'sole';
	if($soleScope) $where = "WHERE appointmentid = {$scope[1]}";
	else if($scope[0] == 'day') 
		$where = "WHERE clientptr = {$source['clientptr']} AND date = '{$scope[1]}'";
	$appts = fetchAssociations("SELECT * FROM tblappointment $where");
	echo "<tr><td id='cancelappts' colspan=2 style='border: solid black 1px;'>";
	if($appts) clientScheduleTable($appts, array('buttons'));
	else echo "The visit".($soleScope ? '' : 's')." originally referred to no longer exist".($soleScope ? 's' : '').".";
	
	if($noButtons || $source['resolution']) 
		echo "Resolution: request ".($source['resolution'] ? $source['resolution'] : 'declined');
	else if($appts) {
		if($uncancel)
			echoButton('','Un-Cancel Visit'.($scope[0] == 'sole' ? '' : 's'), "cancelAppointments({$source['requestid']})");
		else
			echoButton('','Cancel Visit'.($scope[0] == 'sole' ? '' : 's'), "cancelAppointments({$source['requestid']})");
		echo " ";
		echoButton('','Decline Request', "declineOrHonorRequest({$source['requestid']}, 0)");
	}
	echo "</td></tr>";
}



function displayExtraFields($source, $displayOnly=false) {
	$extraFields = getExtraFields($source);
	if($extraFields) {
		foreach($extraFields as $key => $value) {
			$keyParts = explode('-', $key);
			if(count($keyParts) < 3) continue;
			list($ignore, $ftype, $label) = $keyParts;
			if(strpos($label, 'meetingdate') === 0 || strpos($label, 'meetingtime') === 0) {
				if(strpos($label, 'meetingtime') === 0) continue;
				$n = substr($label, -1);
				if(!$extraFields["x-oneline-meetingdate$n"]) continue;
				$mdate = shortDateAndDay(strtotime($extraFields["x-oneline-meetingdate$n"]));
				$mtime = $extraFields["x-oneline-meetingtime$n"];
				$meetingButton = $displayOnly ? '' 
										: echoButton('', 'Arrange Meeting', "arrangeMeeting(\"{$source['clientptr']}\", \"".urlencode($mdate)."\", \"".urlencode($mtime)."\")", $class='', 
											$downClass='', $noEcho=true, 'You can change date and time later.');
				labelRow("Meeting requested:", '', $meetingButton." $mdate at $mtime",
					$labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
			}
			else if(strpos($key, 'x-pet-')===0) { // x-pet-name-1, x-pet-type-1, x-pet-name-2, x-pet-type-2,
				if($petsHandledAlready) continue;
				$petsHandledAlready = 1;
				$prospectpets = getProspectPets($extraFields);
				foreach($prospectpets as $i => $ppet) {
					$value = $ppet['name'];
					$value .= $ppet['type'] ? " ({$ppet['type']})" : " (type unspecified)";
					$value .= $ppet['sex'] ? " ({$ppet['sex']})" : "";
					labelRow('Pet to add:', '', $value);
				}
			}
			else if(strpos($key, 'x-std-')===0) {
				require_once "client-fns.php";
				$allowedFields = getUsableRequestFields();
				$label = $allowedFields[$fld = substr($key, strlen('x-std-'))];
				if(!$label) $label = "UNKNOWN FIELD [$fld]";
				if($fld == 'emergencycarepermission') $value = $value ? 'yes' : 'no';
				if($fld == 'referralcode') {
					require_once "referral-fns.php";
					$value = $value ? join(' > ', getReferralPath($value)) : "--";
				}
				labelRow($label.':', '', $value, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, ($rawValue = TRUE || $ftype == 'label'));
			}  // e.g., 'x-std-cellphone2' 
			else if(strpos($key, 'x-custom-')===0) ; // e.g., 'x-custom-1' 
			
			else if(in_array($ftype, array('select', 'oneline', 'radio', 'label')))
				labelRow($label.':', '', $value, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, ($rawValue = TRUE || $ftype == 'label'));
			else if($ftype == 'checkbox')
				labelRow($label.':', '', ($value ? 'yes' : 'no'));
			else if($ftype == 'multiline')
				echo "<tr><td valign=top>$label:</td><td>".str_replace("\n", '<br>', $value)."</td></tr>";
		}
	}
}	

function getProspectPets($requestOrExtraFields) {
	$extraFields = $requestOrExtraFields['requestid'] ? getExtraFields($requestOrExtraFields) 
									: $requestOrExtraFields;
	$prospectpets = array();
	foreach($extraFields as $pk => $pv) {
		if(strpos($pk, 'x-pet-')===FALSE) continue;
		$parts = explode('-', $pk);
		$ppfield = $parts[2];
		$ppnum = $parts[3];
		$prospectpets["p$ppnum"][$ppfield] = $pv;
	}
	foreach($prospectpets as $i => $ppet) {
		$anything = '';
		foreach($ppet as $pv) $anything .= $pv;
		if(!$anything) {
			unset($prospectpets[$i]);
			continue;
		}
		$prospectpets[$i]['active'] = 1;
		if(!$ppet['name']) $prospectpets[$i]['name'] = 'UNNAMED';
	}
	return $prospectpets;
}


function getStandardClientExtraFields($request) {
	$fields = array();
	$allowedFields = getUsableRequestFields();
	foreach(getExtraFields($request) as $key => $value) {
		if(strpos($key, 'x-std-') !== 0) continue;
		$fld = substr($key, strlen('x-std-'));
		$fields[$fld] = $value;
	}
	return $fields;
}


function clientProfileField($key) {
	static $labels;
	if(!$labels) {
		$rawlabels = "name_|Pet Name||dob|Pet Birthday||lname|Last Name||fname|First Name||lname2|Alt Last Name||fname2|Alt First Name"
									."||email|Email||email2|Alt Email||clinicname|Veterinary Clinic||vetname,Veterinarian"
			            ."||notes,Notes|garagegatecode|Garage/Gate Code||alarmcophone|Alarm Company Phone||alarminfo|Alarm Info"
									.'||street1|Address||street2|Address 2||city|City||state|State||zip|ZIP/Postal code'
									.'||mailstreet1|Mailing Address||mailstreet2|Mailing Address||mailcity|Mailing City||mailstate|Mailing State||mailzip|Mailing ZIP/Postal code'
									.'||homephone|Home Phone||cellphone|Cell Phone||workphone|Work Phone||fax|FAX||pager|Pager||cellphone2|Alt Phone' // ||clinicptr||vetptr||notes|Notes
									.'||directions|Directions to Home||alarmcompany|Alarm Company' // homephone||
									.'||birthday|Birthday||leashloc|Leash Location||foodloc|Food Location||parkinginfo|Parking Info||emergencycarepermission|Emergency Permission'
									.'||referralcode|Referral Code||referralnote|Referral Note';
		foreach(explode('||', $rawlabels) as $pair) {
			$pair = explode('|', $pair);
			$labels[$pair[0]] = $pair[1];
		}
		// get pet custom fields and client custom fields
		foreach(fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE '%custom%'") as $k=>$v) {
			$parts = explode('|', $v);
			$labels[$k] = $parts[0];
		}
	}
	if(strpos($key, '_petcustom')) {
		$key = substr($key, strpos($key, '_petcustom')+1);
	}
	return $labels[$key] ? $labels[$key] : $key;
}

function clientLinkForNotification($client) {
	global $mein_host;
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	$urlBase = "$mein_host$this_dir";
	$url = "client-edit.php?id={$client['clientid']}&tab=services";
	return "<a href='$urlBase/$url'>{$client['clientname']}</a>";
}
	
function requestShortLabel($request) {
	global $requestTypes;
	if($request['requesttype'] == 'TimeOff') {
		$hiddenFields = getExtraFields($request);
//if(mattOnlyTest()) screenLog(print_r($hiddenFields, 1));		
		return "{$requestTypes[$request['requesttype']]}: {$hiddenFields['x-label-Sitter']}";
	}
	if(in_array($request['requesttype'], array('UnassignedVisitOffer', 'ICInvoice'))) {
		$extraFields = getExtraFields($request);
		return "{$requestTypes[$request['requesttype']]}: {$extraFields['x-label-Requestor']}";
	}
	if(in_array($request['requesttype'], array('BugReport', 'Comment'))) {
		$hiddenFields = getExtraFields($request);
//if(mattOnlyTest()) screenLog(print_r($hiddenFields, 1));		
		return "{$hiddenFields['x-label-Subject']}";
	}
	if($request['requesttype'] == 'BillingReminder') {
		$hiddenFields = getHiddenExtraFields($request);
		$requestTime = strtotime($request['received']);
		if($hiddenFields['type'] == 'starting') {
			$sDate = strtotime("+ {$hiddenFields['lookahead']} days", $requestTime);
			$sDate = date('D ', $sDate).shortestDate($sDate, 'noYear');
			$label = "Start Schedule on $sDate";
		}
		else {
			$sDate = date('D ', $requestTime).shortestDate($requestTime, 'noYear');
			$label = "END schedule on $sDate";
		}
		return $label;
	}
	if($request['requesttype'] == 'SystemNotification' && $request['extrafields']) {
		if(is_array($request['extrafields'])) return $extraFields['subject'];
		else if($extraFields = getExtraFields($request)) return $extraFields['subject'];
		return $request['extrafields'];
	}
	if($request['requesttype'] == 'General' && $request['extrafields']) {
		if(is_array($request['extrafields'])) return $extraFields['x-subject'];
		else if($extraFields = getExtraFields($request) && $extraFields['x-subject']) return $extraFields['x-subject'];
		return $requestTypes[$request['requesttype']];
	}
	if($request['requesttype'] == 'schedulechange') {
		$hiddenFields = getHiddenExtraFields($request);
		$types = explodePairsLine('change|Change||cancel|Cancel||uncancel|Uncancel');
		$changeType = $types[$hiddenFields["changetype"]];
		return $requestTypes[$request['requesttype']].': '.$changeType;
	}
	return $requestTypes[$request['requesttype']];
}
	
function requestLabel($request) {
	global $requestTypes;
	$label = $requestTypes[$request['requesttype']];
	if($request['scope']) {
		$scope = explode('_', $request['scope']);
		$label .= $scope[0] == 'sole' ? ' of Appointment' : " of Day's Appointments";
	}
	return $label;
}
	

function setPendingChangeNotice($request) {
	if($request['requesttype'] == 'schedulechange') 
		return setPendingMultiChangeNotice($request);
	$scope = explode('_',$request['scope']);
	if($scope[0] == 'sole') $where = "appointmentid = {$scope[1]}";
	else if($scope[0] == 'day') 
		$where = "clientptr = {$request['clientptr']} AND date = '{$scope[1]}'";
	$pendingChange = $request['requesttype'] == 'cancel' ? 0 - $request['requestid'] : $request['requestid'];
	$mods = withModificationFields(array('pendingchange'=>$pendingChange));
	updateTable('tblappointment', $mods, $where, 1);
	// completion and charge are unchanged, so no discount action is necessary									
}

function setPendingMultiChangeNotice($request) {
	// for schedulechange requests
	$hidden = getHiddenExtraFields($request);
	foreach(json_decode($hidden['visitsjson'], 'assoc') as $visit)
		$visitids[] = $visit['id'];
		
	$where = "appointmentid IN (".join(',', $visitids).")";
	$changetype = $hidden['changetype'];
	$pendingChange = $changetype == 'cancel' ? 0 - $request['requestid'] : $request['requestid'];
	$mods = withModificationFields(array('pendingchange'=>$pendingChange));
	updateTable('tblappointment', $mods, $where, 1);
	// completion and charge are unchanged, so no discount action is necessary									
}

function saveNewSystemNotificationRequest($subject, $note, $extraFields = null, $clientptr=null) {
	$request = array();
	if($clientptr) $request['clientptr'] = $clientptr;
	$request['extrafields'] = $extraFields ? $extraFields  : array();
	$request['extrafields']['subject'] = $subject;
  foreach($request['extrafields'] as $key => $value) 
  	$extraFieldsString .= "<extra key=\"$key\"><![CDATA[$value]]></extra>";
  $request['extrafields'] = "<extrafields>$extraFieldsString</extrafields>";
	
	//$request['extrafields'] = $subject;
	$request['subject'] = $subject;
	$request['requesttype'] = 'SystemNotification';
	$request['note'] = $note;
	$reqId = saveNewClientRequest($request, $notify=true);
  return $reqId;
}

function saveNewClientRequest($request, $notify=true, $appendRequestIDToSubject=false) {
	$requestFields = getRequestFields(); // to avoid problem where globals are nulled on repeated loadings
	if(!array_key_exists('resolved', $request)) $request['resolved'] = 0;
  $now = date('Y-m-d H:i:s');
  $request['received'] = $now;
  $origRequest = $request; // to preserve 'subject'
  foreach($request as $field => $v)
		if(!in_array($field, $requestFields)) unset($request[$field]);
  $request['received'] = $now;
  $reqId = insertTable('tblclientrequest', $request, 1);
  if($appendRequestIDToSubject) $origRequest['subject'] .= $reqId;
	if($notify) notifyStaffOfClientRequest($origRequest);  
  return $reqId;
}

function saveNewTimeoffRequest($request, $notify=true) {
	$requestFields = getRequestFields(); // to avoid problem where globals are nulled on repeated loadings
  $request['requesttype'] = 'TimeOff';
	$request['resolved'] = 0;
  $now = date('Y-m-d H:i:s');
  $origRequest = $request; // to preserve 'subject'
  foreach($request as $field => $v)
		if(!in_array($field, $requestFields)) unset($request[$field]);
  $request['received'] = $now;
  $reqId = insertTable('tblclientrequest', $request, 1);
	if($notify) notifyStaffOfClientRequest($origRequest);  
  return $reqId;
}

function saveNewBugReportCommentRequest($request, $notify=true) {
	$requestFields = getRequestFields(); // to avoid problem where globals are nulled on repeated loadings
  //$request['requesttype'] = 'TimeOff';
	$fullname = $_SESSION["fullname"] ? $_SESSION["fullname"] : ($_SESSION["clientname"] ? $_SESSION["clientname"] : $_SESSION["auth_username"]);
	$request['subject'] = ($request['requesttype'] == 'Comment' ? 'Comment' : 'Bug Report')." from $fullname";
	$request['resolved'] = 0;
  $now = date('Y-m-d H:i:s');
	//if($notify) notifyStaffOfClientRequest($request);  
  //foreach($request as $field => $v)
	//	if(!in_array($field, $requestFields)) unset($request[$field]);
  $request['received'] = $now;
  if(!(dbTEST('leashtimecustomers') && staffOnlyTEST())) // clientptr already supplied
  	$request['clientptr'] = fetchRow0Col0("SELECT clientid FROM tblclient WHERE garagegatecode = {$_SESSION['bizptr']}");
  
  if(TRUE) {  // 3/1/2021 replaced OLD call to insertTable with saveNewClientRequest, which does the staff notification
  	require_once "client-fns.php";
  	return saveNewClientRequest($request, $notify=true);
  
  }
  return insertTable('tblclientrequest', $request, 1);
}

function saveNewProspectRequest($request) {
	// SPAM FILTERING
	// if enforceProspectSpamDetection, modelnum must be present or all will be spam
	$prefs = fetchPreferences();
	/*if((($prefs['enforceProspectSpamDetection'] && !$request['modelnum'])) 
			|| (isset($request['modelnum']) && $request['modelnum'] != $request['pbid'])
			|| (array_key_exists('address3', $_POST) && $_POST['address3'])
			|| (!$_POST['phone'] && !$_POST['email'] && !$_POST['x-std-workphone'] && !$_POST['x-std-cellphone'])) {*/
	if($_POST['address3'] // address3 is a honeypot -- it ALWAYS means spam
		|| (!$_POST['phone'] && !$_POST['email'] && !$_POST['x-std-workphone'] && !$_POST['x-std-cellphone']) // No contact info
		|| ($request['modelnum'] && $request['modelnum'] != $request['pbid']) // modelnum is a checked honeypot, set just before form submission
		|| (!$request['modelnum'] && $prefs['enforceProspectSpamDetection']) // modelnum is REQUIRED when spam detection is on
		|| (/*mattOnlyTEST() && */$request['fname'] && ($request['lname'] == $request['fname']) && $prefs['enforceProspectSpamDetection']) // for queenies pets
		) {
		$request['resolved'] = 1;
		// if(SOME TEST) $request['resolved'] = 0; // maybe add a "Show Spam Requests on Home page." preference
		$request['requesttype'] = 'Spam';
		$request['officenotes'] = "Originating IP address [{$_SERVER['REMOTE_ADDR']}].\n\n{$_SERVER['HTTP_USER_AGENT']}";
	}
	else {	
		$request['resolved'] = 0;
		$request['requesttype'] = 'Prospect';
	}
	if($request['requesttype'] == 'Spam') {
		if($prefs['autoDeleteSpam']) {
			logChange(99, 'spam', 'd', "IP[{$_SERVER['REMOTE_ADDR']}] spam intercepted. {$_SERVER['HTTP_USER_AGENT']}");
			return;
		}
		else logChange(99, 'spam', 'c', "IP[{$_SERVER['REMOTE_ADDR']}] spam created. {$_SERVER['HTTP_USER_AGENT']}");
	}
		
}
  foreach($request as $key => $value) 
  	if(strpos($key, 'x-')===0)
  		$extraFields .= "<extra key=\"$key\"><![CDATA[$value]]></extra>";
  		
  // include the form's referrer
	$extraFields .= "<extra key=\"form_referer\"><![CDATA[{$_SERVER['HTTP_REFERER']}]]></extra>";
	$extraFields .= "<extra key=\"form_user_agent\"><![CDATA[{$_SERVER['HTTP_USER_AGENT']}]]></extra>";
  
  
  if($extraFields) $request['extrafields'] = "<extrafields>$extraFields</extrafields>";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($request);exit;}  
  saveNewClientRequest($request);
}

function saveNewGenericRequest($request, $clientptr=null) {
  $request['resolved'] = 0;
  $request['requesttype'] = $request['requesttype'] ? $request['requesttype'] : 'General';
  $request['clientptr'] = $_SESSION["clientid"] ? $_SESSION["clientid"] : $clientptr;
  foreach($request as $key => $value) 
  	if(strpos($key, 'x-')===0)
  		$extraFields .= "<extra key=\"$key\"><![CDATA[$value]]></extra>";
  if($extraFields) $request['extrafields'] = "<extrafields>$extraFields</extrafields>";
  return saveNewClientRequest($request);
}

function updateClientRequest($request) { // requires requestid
	$oldRequest = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$request['requestid']} LIMIT 1");
  $strippedDownFields = array('requestid', 'officenotes','clientptr', 'resolved');
  foreach($request as $field => $v)
    if(!in_array($field, $strippedDownFields)) unset($request[$field]);
  if($request['resolved']) {
		$request['resolved'] = 1;
	}
	else {
		$request['resolved'] = 0;
		$request['resolution'] = sqlVal("null");
	}
	$newStatus = $request['resolved'] ? 'resolved' : 'unresolved';
	$statusChange = 
		$request['resolved'] == $oldRequest['resolved'] ? "still $newStatus" : $newStatus;
  if(isset($request['clientptr']) && !$request['clientptr']) unset($request['clientptr']); // never unset clientptr in db
 //if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($request);exit; }
 	updateTable('tblclientrequest', $request, "requestid = {$request['requestid']}", 1);
  $request = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$request['requestid']} LIMIT 1");
  if($request['resolved']) {
		if(in_array($request['requesttype'], array('cancel', 'uncancel', 'change'))) {
			$statusChange .= ". {$request['requesttype']} executed.";
			$apptChanges = array('pendingchange'=>null);
			$scope = explode('_', $request['scope']);
			if($scope[0] == 'sole') $condition = "appointmentid  = {$scope[1]}";
			else if($scope[0] == 'day') {
				$condition = "clientptr = {$request['clientptr']} AND date = '{$scope[1]}'";
			}
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($condition); exit;}  
			updateTable('tblappointment', $apptChanges, $condition, 1);
		}
		else if(in_array($request['requesttype'], array('schedulechange'))) {
			$hidden = getHiddenExtraFields($request);
			$apptChanges = array('pendingchange'=>null);
			foreach(json_decode($hidden['visitsjson'], 'assoc') as $visit)
				$visitids[] = $visit['id'];
			$where = "appointmentid IN (".join(',', $visitids).")";
			$statusChange .= ". {$request['requesttype']} - ".count($visitids)." changes no longer pending.";
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($condition); exit;}  
			updateTable('tblappointment', $apptChanges, $where, 1);
		}
	}
	logChange($request['requestid'], 'tblclientrequest', 'm', $statusChange);
}

function getClientRequest($id) {
	return fetchFirstAssoc(
		"SELECT 
		   *, if(clientptr, '', CONCAT_WS(' ', fname, lname)) as clientname
     FROM tblclientrequest WHERE requestid = $id");
}

function getClientRequests($unresolvedOnly=null, $offset=0, $initialUnresolvedLimit=20, $filterParams=null) {
	$filter = $unresolvedOnly ? array("resolved = 0") : '';
	if(!$unresolvedOnly) {
		$limit = "LIMIT ".($offset + $initialUnresolvedLimit);
	}
	if($filterParams) {
		if($filterParams['requestStart'])
			$filter[] = "received >= '"
									.date('Y-m-d 00:00:00', strtotime($filterParams['requestStart']))
									."'";
		if($filterParams['requestEnd'])
			$filter[] = "received <= '"
									.date('Y-m-d 23:59:59', strtotime($filterParams['requestEnd']))
									."'";
		if($filterParams['showType']) $filter[] = "requestType = '{$filterParams['showType']}'";
		if($filterParams['client']) {
			$extraFields = ", tblclient.fname, tblclient.lname";
			$pattern = strpos($filterParams['client'], '*') === FALSE ? "*{$filterParams['client']}*" : $filterParams['client'];
			$pattern = mysqli_real_escape_string(str_replace('*', '%', $pattern));
			$filter[] = "(CONCAT_WS(' ', tblclientrequest.fname, tblclientrequest.lname) LIKE '$pattern'"
									."|| CONCAT_WS(' ', tblclient.fname, tblclient.lname) LIKE '$pattern')";
		}
	}
	$leftJoin = array("tblclient ON clientid = clientptr");
	if($assignedTo = $filterParams['assignedTo']) {
		$leftJoin[] = "tblclientrequestprop crp ON requestptr = requestid AND property = 'owner'";
		$extraFields = ", crp.value";
		if($assignedTo == -1) $filter[] = "crp.value IS NOT NULL";
		else if($assignedTo == -2) $filter[] = "crp.value IS NULL";
		else $filter[] = "crp.value = $assignedTo";
	}
	
	
	$leftJoin = $leftJoin ? 'LEFT JOIN '.join(' LEFT JOIN ', $leftJoin) : '';
	
	$suppressPayInfo = userRole() == 'd' && !adequateRights('#pa');
	if($suppressPayInfo) $filter[] = "requesttype != 'ICInvoice'";
	
	$filter = $filter ? "WHERE ".join(' AND ', $filter) : '';
	

	
	$sort = $filterParams['sort'] ? $filterParams['sort'] : "received DESC";
	$sort = str_replace('clientname', 'clientsortname', 
						str_replace('date', 'received', 
							str_replace('_', ' ', $sort)));
	if(strpos($sort, 'received') !== 0) $sort .= ", received DESC";
	$sql = 
		"SELECT 
		   tblclientrequest.*, if(clientptr, '', CONCAT_WS(' ', tblclientrequest.fname, tblclientrequest.lname)) as clientname 
		   , if(clientptr, CONCAT_WS(' ', tblclient.lname, ', ', tblclient.fname), 
		   								CONCAT_WS(' ', tblclientrequest.lname, ', ', tblclientrequest.fname)) as clientsortname
		   $extraFields
     FROM tblclientrequest $leftJoin $filter ORDER BY $sort $limit";
  $requests = fetchAssociations($sql);  
     
	return array_merge($requests);
}

// REQUEST COORDINATOR FUNCTIONS

function userIsACoordinator() {
	if(!$_SESSION['preferences']['enablerequestassignments']) return false;
	return
		$_SESSION["isowner"] ||
			staffOnlyTEST() || 
			fetchRow0Col0("SELECT value FROM tbluserpref 
												WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'requestcoordinator'");
}


function getAssignmentWidget($request, &$requestOwnerNames) {
	$blackstar = '&#9733;';
	$whitestar = '&#9734;';
	$whitecircle = '&#9675;';
	$owner = $requestOwnerNames[$request['requestid']];
	if($owner['userid'] == $_SESSION['auth_user_id']) {
		$icon = $blackstar;
		$color = 'red';
	}
	else if($owner) {
		$icon = $whitestar;
		$color = 'green';
	}
	else {
		$owner = array('name'=>'no one');
		$icon = $whitecircle;
		$color = 'black';
	}
	$title = "assigned to ".addslashes($owner['name']).'.';
	$reqid = $request['requestid'];
	$innerSpan = "<span style='display:none;font-size:0.72em;' id='r$reqid' onclick='requestMarkerClicked(\"$reqid\", event|| window.event);'>$title</span>";
	return "<span id='rm$reqid' style='cursor:pointer;color:$color;font-size:1.0em;font-weight:bold;' title='$title' "
						."onclick='requestMarkerClicked(\"$reqid\", event|| window.event);'>$icon$innerSpan</span> ";
}

function requestOwnerNames($requests) { // requestId =>(userid,name)
	if(!$requests) return array();
	foreach($requests as $req) $requestids[] = $req['requestid'];
	$ownerIds = fetchKeyValuePairs(
		"SELECT requestptr, value 
			FROM tblclientrequestprop 
			WHERE requestptr IN (".join(', ', $requestids).") AND property = 'owner'");
	if(!$ownerIds) return array();
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	
	$ownerNames = fetchAssociationsKeyedBy(
		"SELECT userid, CONCAT_WS(' ', fname, lname) as name, loginid
			FROM tbluser 
			WHERE userid IN (".join(',', $ownerIds).")", 'userid');
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	foreach($ownerIds as $requestid => $ownerid) {
		$nm = $ownerNames[$ownerIds[$requestid]]['name'];
		if(!trim($nm)) $nm = $ownerNames[$ownerIds[$requestid]]['loginid'];
		$ownerIds[$requestid] = array('userid'=>$ownerid, 'name'=>$nm);
	}
	return $ownerIds; // requestId =>(userid,name)
}

function requestOwnerPullDownMenu($request, $selectLabel=null, $noEcho=false) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	if(!staffOnlyTEST()) $NOLTSTAFF = " AND ltstaffuserid = 0";
	$managers = fetchAssociationsKeyedBy(
		"SELECT CONCAT_WS(' ', fname, lname) as name, userid, loginid
			FROM tbluser 
			WHERE bizptr = {$_SESSION['bizptr']} AND active=1
			AND (rights LIKE 'o-%' OR rights LIKE 'd-%') $NOLTSTAFF
			ORDER BY lname, fname", 'userid');
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	if($request['requestid']) 
		$ownerid = fetchRow0Col0(
			"SELECT value 
				FROM tblclientrequestprop 
				WHERE requestptr = {$request['requestid']}
				LIMIT 1");
	$allmanagers['- choose an admin -'] = 0;
	$allmanagers['- unassign -'] = -1;

	foreach($managers as $userid => $user) {
		$label = trim($user['name']) ? $user['name'] : "({$user['loginid']})";
		$allmanagers[$label] = $userid;
	}
	$s =  
		labeledSelect(($selectLabel ? $selectLabel : 'Assigned to:'), 'owner', $value=$ownerid, $options=$allmanagers, $labelClass=null, $inputClass=null, $onChange='assignRequest(this)', $noEchoYet=true)
		.hiddenElement('lastowner', $ownerid, $inputClass=null, $noEchoYet=true)
		.'<p>'.fauxLink('Assignment History', "showRequestHistory()", 1, "Show assignment history")
		.'<div id=requesthistorydiv></div>';
		
	if($noEcho) return $s;
	else echo $s;
}

function requestAssignmentHistory($reqid) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$ownerNames = fetchKeyValuePairs(
		"SELECT userid, CONCAT_WS(' ', fname, lname, CONCAT('(',loginid,')')) as name
			FROM tbluser 
			WHERE bizptr = {$_SESSION["bizptr"]}");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	$history = fetchAssociations("SELECT * FROM tblchangelog WHERE itemtable = 'requestassignment' AND itemptr = $reqid ORDER BY time");
	foreach($history as $item) 
		$rows[] = 
			array('time'=>shortDate(strtotime($item['time'])).' '.date('H:i a', strtotime($item['time'])),
			'assignedto'=>($ownerNames[$item['note']] ? $ownerNames[$item['note']] : 'Unassigned'), 'assignedby'=>$ownerNames[$item['user']]);
	return $rows;
}


// END REQUEST COORDINATOR FUNCTIONS
function separatePreferredSection(&$requests) {
	if(!$requests) return;
	global $PreferredRequestType;
	$PreferredRequestType = staffOnlyTEST() && dbTest('pawlosophy') ? 'Reminder' : null;
	if(!$PreferredRequestType) return;
	usort($requests, 'compareRequestTypes');
}

function compareRequestTypes($a, $b) {
	// assume time order is already imposed.  Place special requests first
	global $PreferredRequestType;
	global $PreferredRequestTypeWasFound;
	if($a['requesttype'] == $PreferredRequestType || $b['requesttype'] == $PreferredRequestType)
		$PreferredRequestTypeWasFound = true;
	if($a['requesttype'] != $PreferredRequestType && $b['requesttype'] == $PreferredRequestType)
		return 1;
	if($a['requesttype'] == $PreferredRequestType && $b['requesttype'] != $PreferredRequestType)
		return -1;
	else return 0-strcmp($a['received'], $b['received']);
}

function clientRequestSection($updateList, $unresolvedOnly=true, $offset=0, $initialUnresolvedLimit=20, $filterParams=null, $unresolvedcheckboxes=false) {
	global $columnSorts, $showCount;
	if($unresolvedcheckboxes) $cbcol = 'cb| ||';
	$columns = explodePairsLine("{$cbcol}clientname|Client||requesttype|Request||date|Date||address|Address||phone|Phone");
	$requests = getClientRequests($unresolvedOnly, $offset, $initialUnresolvedLimit, $filterParams);
//screenLogPageTime('Just did getClientRequests (in clientRequestSection())');
	foreach($requests as $request) 
	  if($request['clientptr']) $clientids[] = $request['clientptr'];
	
	if($requests && strpos($_SERVER["SCRIPT_FILENAME"], 'client-request-page.php') === FALSE) 
		separatePreferredSection($requests); // never on report page
//screenLogPageTime('Just did separatePreferredSection');

	
	$clientDetails = getClientDetails($clientids, array('phone','address'));
		
	$n = 0;
	
	if($_SESSION['preferences']['enablerequestassignments']) {
		$requestOwnerNames = requestOwnerNames($requests);
//screenLogPageTime('Just did requestOwnerNames');
	}	
	
	global $PreferredRequestTypeWasFound;
	global $PreferredRequestType;

	$data = array();
	foreach($requests as $n => $request) {
		if($PreferredRequestTypeWasFound) {
			if($lastRequestType == $PreferredRequestType && $request['requesttype'] != $PreferredRequestType) {
				// insert a break after PreferredRequestType section
				
				$rowClass = 'futuretask';
				//$rowClass = $rowClass.'EVEN';
				$rowClasses[$n] = $rowClass;
				//$n += 1;				
			}
			$lastRequestType = $request['requesttype'];
		}
		$requestid = $request['requestid'];
		$row = array('requestn' => $n);
		$row['clientname'] = $request['clientptr'] ? $clientDetails[$request['clientptr']]['clientname'] : $request['clientname'];
		
		if($request['requesttype'] == 'TimeOff') {
			$extraFields = getExtraFields($request);
			$sitter = $extraFields['x-label-Sitter'].'*';
		  $row['clientname'] = $sitter;
		  //if(adequateRights('#as')) 
		  //	$row['clientname'] = "<a class='fauxlink' title='Edit this sitter in the main window.' onClick='document.location.href=\"provider-edit.php?id=\"',700,500)'>{$row['clientname']}</a>";
		}
		if(in_array($request['requesttype'], array('UnassignedVisitOffer', 'ICInvoice'))) {
			$extraFields = getExtraFields($request);
			$sitter = $extraFields['x-label-Requestor'].'*';
		  $row['clientname'] = $sitter;
		  //if(adequateRights('#as')) 
		  //	$row['clientname'] = "<a class='fauxlink' title='Edit this sitter in the main window.' onClick='document.location.href=\"provider-edit.php?id=\"',700,500)'>{$row['clientname']}</a>";
		}
		else if($request['clientptr']) 
		  $row['clientname'] = "<a  class='fauxlink' title='Edit this client in the main window.' onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id={$request['clientptr']}\",700,500)'>{$row['clientname']}</a>";
		$label = requestShortLabel($request);//$request['requesttype'];
//echo  $row['requesttype']; exit;	
		if($request['resolution']) $label .= " ({$request['resolution']})";

		$title = 'View this request.';
		if($request['providerptr']) {
			require_once "provider-fns.php";
			$pname = getProvider($request['providerptr']);
			$pname = providerShortName($pname);
			$title = "View this request. (Submitted by $pname)";
			$label = "$label (*)";
		}
		$row['requesttype'] = 
			fauxLink($label, "openConsoleWindow(\"viewrequest\", \"request-edit.php?id={$requestid}&updateList=$updateList\",610,600)", 1, $title);

		$row['date'] = shortDateAndTime(strtotime($request['received']));
		$row['address'] = '';
		if($request['clientptr']) $row['address'] = $clientDetails[$request['clientptr']]['address'];
		else {
			if($row['address']) $row['address'] = $request['address'];
			else {
				$addr = array($request['street1'], $request['street2'], $request['city'], $request['state'], $request['zip']);
				$row['address'] = oneLineAddress($addr);
			}
		}
		$row['address'] = truncatedLabel($row['address'], 24);
		$row['phone'] = $request['clientptr'] ? $clientDetails[$request['clientptr']]['phone'] : $request['phone'];
		$rowClass = $request['resolved'] ? 'completedtask' : 'futuretask';
		if(!($n & 1)) $rowClass = $rowClass.'EVEN';
		$rowClasses[$n] = $rowClass;
		if($request['requesttype'] == 'Reminder') {
			if($request['clientptr']) 
				$clientLink = "<a  class='fauxlink' title='Edit this client in the main window.' onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id={$request['clientptr']}\",700,500)'>{$row['clientname']}</a>";
			else if(($extraFields = getExtraFields($request)) && $extraFields['x-oneline-Group']) {
				$clientLink = $extraFields['x-oneline-Group'];
				$clientLink = "<span title='This reminder concerns a group rather than a client or sitter.'>$clientLink</span>";
			}
			else $clientLink = "General";
			if($_SESSION['preferences']['enablerequestassignments']) {
				$clientLink = getAssignmentWidget($request, $requestOwnerNames).$clientLink;
			}
			
			$subject = truncatedLabel($request['street1'], 50); //$request['street1'];
			if($unresolvedcheckboxes) {
				if(!$request['resolved'])
					$cb = "<td><input type='checkbox' id='req_$requestid' name='req_$requestid'></td>";
				else $cb = "<td>&nbsp;</td>";
			}
			$row = array('#CUSTOM_ROW#'=>"<tr class='$rowClass'>$cb<td class='sortableListCell'>$clientLink</td><td class='sortableListCell'>{$row['requesttype']}</td><td class='sortableListCell'>{$row['date']}</td><td colspan=2 class='sortableListCell'>$subject</td></tr>");
		}
		
		if((!$request['resolved'] || ($request['requesttype'] == 'Spam')) && $unresolvedcheckboxes) {
			$spamAtt = $request['requesttype'] == 'Spam' ? 'spam=1' : '';
			$row['cb'] = "<input type='checkbox' id='req_$requestid' name='req_$requestid' $spamAtt>";
		}
		
		if($_SESSION['preferences']['enablerequestassignments']) {
			$row['clientname'] = getAssignmentWidget($request, $requestOwnerNames).$row['clientname'];
		}
		
		
		
		$data[] = $row;
//screenLogPageTime('Just finished requestOwnerNames');
	}
	
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	if(!$unresolvedOnly) {
		$totalCount = fetchRow0Col0("SELECT count(*) FROM tblclientrequest");
		if($totalCount > $offset+$initialUnresolvedLimit)
			echo "<!-- OFFSET".($offset+$initialUnresolvedLimit)."OFFSET -->";
	}
	
	// Flexibility Tweak // (staffOnlyTEST() || dbTEST('tonkapetsitters')) && 
	if($customColumns = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'requestlistcolumns' LIMIT 1", 1)/*$_SESSION['preferences']['requestlistcolumns']*/) {
		$allColumns = explodePairsLine("clientname|Client||requesttype|Request||date|Date||address|Address||phone|Phone||note|Request Note||officenotes|Office Notes||condnote|Note");
		$customColKeys = explode('|', $customColumns);
		$newColumns = array();
		if($columns['cb']) $newColumns['cb'] = $columns['cb'];
		foreach($customColKeys as $k) $newColumns[$k] = $allColumns[$k];
		$columns = $newColumns;
		$noteWidth = 
			count($columns) <= 4 ? 40 : 20;
		foreach($data as $row) {
			$n = $row['requestn'];
			if(in_array('note', $customColKeys)) $data[$n]['note'] = requestNote($requests[$n], $length=$noteWidth); // $requests[$n]['note'];
			if(in_array('officenotes', $customColKeys)) $data[$n]['officenotes'] = truncatedLabel($requests[$n]['officenotes'], $noteWidth); // $requests[$n]['officenotes'];
			if(in_array('condnote', $customColKeys)) 
				$data[$n]['condnote'] = 
					$requests[$n]['officenotes'] ? truncatedLabel($requests[$n]['officenotes'], $noteWidth) 
					: requestNote($requests[$n], $length=$noteWidth);
		}
	}
	
	
	
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	if($showCount) echo '<br>'.(count($data) ? count($data) : 'No')." requests shown.";
	if(mattOnlyTEST() && !$data) {
		if($unresolvedOnly) echo "There are no unresolved requests outstanding.";
		else echo "There are no requests to show.";
	}
	else 
		tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses);
}

function requestNote($request, $length=1000) {
	if($request['requesttype'] == 'Schedule') {
		$schedule = $request['note'];
		$parts = explode("\n", $schedule);
		if(isset($parts[2])) $note = urldecode($parts[2]);
		
	}
	else $note = strip_tags($request['note']);
	if(!$note || strlen($note) <= $length) return $note;
	return substr($note, 0, $length-3).'...';
}

function getAllExtraFieldAssociations($request) {
	if(!$request['extrafields']) return array();
	$xml = readXMLString($request['extrafields']);
	if(!$xml) {
		echo "<p>Request #{$request['requestid']}<p>".requestAsString($request)."<p>";
		return array();
	}
	foreach($xml->children() as $extra) {
		$type = $extra->getName();
		$attrs = $extra->attributes();
		$extrafields[(string)$attrs['key']] = array('type'=>$type, 'value'=>(string)$extra);
	}
	return $extrafields;
}

function getExtraFields($request) {
	if(!$request['extrafields'] || strpos($request['extrafields'], '<') === FALSE) return array();
	$xml = readXMLString($request['extrafields']);
	if(!$xml) {
		echo "<p>Request #{$request['requestid']}<p>".requestAsString($request)."<p>";
		return array();
	}
	foreach($xml->children() as $extra) {
		if($extra->getName() == 'hidden') continue;
		$attrs = $extra->attributes();
		$extrafields[(string)$attrs['key']] = (string)$extra;
	}
	return $extrafields;
}


function getHiddenExtraFields($request) {
	if(!$request['extrafields'] || strpos($request['extrafields'], '<') === FALSE) return;
	$xml = readXMLString($request['extrafields']);
	if(!$xml) {
		echo "<p>Request #{$request['requestid']}<p>".requestAsString($request)."<p>";
		return array();
	}
	foreach($xml->children() as $extra) {
		if($extra->getName() != 'hidden') continue;
		$attrs = $extra->attributes();
		$extrafields[(string)$attrs['key']] = (string)$extra;
	}
	return $extrafields;
}

function requestAsString($request) {
	return mattOnlyTEST() ? print_r($request, 1) : '';
}

function readXMLString($str) {
	//if(!mattOnlyTEST())
	//	return simplexml_load_string($str);
		
	libxml_use_internal_errors(true);

	$doc = simplexml_load_string($str);
	$xml = explode("\n", $str);

	if (!$doc) {
		if(mattOnlyTEST()) {
			$errors = libxml_get_errors();

			foreach ($errors as $error) {
				echo $error->message.' line: '.$error->line.' column: '.$error->column.'<br>';
			}
			echo "XML: <hr>".htmlentities($str)."<hr>";
		}
		else echo "<hr>Bad information supplied.  Please consult support.<hr>";

		libxml_clear_errors();
	}
	libxml_use_internal_errors(FALSE);
	return $doc;
	
}
