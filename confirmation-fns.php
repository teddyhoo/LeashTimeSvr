<?  // confirmation-fns.php

/* tblconfirmation has
confid
respondentptr
respondenttable tblclient | tblprovider
msgptr - into tblmessage
token -from petcentral>tblresponsetoken.  Used when confirming from confirm.php..
resolution - pending,received,canceled
resolutiondate - datetime
due - datetime
expiration - datetime (same as token's)
note
responsemsgptr
resolvedby - userid if not anonymous
*/

// messsage with responseURL (from generateResponseURL($bizptr, $respondent, $redirecturl, $systemlogin)) has been sent
function saveNewConfirmation($msgptr, $respondentptr, $respondenttable, $token, $due, $expiration, $msgsection=null) {
	foreach(explode(',','msgptr,respondentptr,respondenttable,token,due,expiration') as $k) $row[$k] = $$k;
	$row['due'] = dbDatetimeOrNull($row['due']);
	$row['expiration'] = dbDatetimeOrNull($row['expiration']);  // not really necessary since it comes from tblresponsetoken
	$row['resolution'] = 'pending';
	if($msgsection) $row['msgsection'] = $msgsection;
	return insertTable('tblconfirmation', $row, 1);
}

function resendConfirmationRequest($msgid, $immediately) {
	$msg = fetchFirstAssoc("SELECT * from tblmessage WHERE msgid = $msgid LIMIT 1");
	$subject = "Resending: {$msg['subject']}";
	if($immediately) {
		$msg['mgrname'] = null;
		$msg['msgid'] = null;
		$msg['subject'] = $subject;
		if($error = sendEmail($msg['correspaddr'], $msg['subject'], $msg['body'], null, true))
			return "Mail error:<p>$error<br>(recipients: {$msg['correspaddr']})";
		else {
			$msg['mgrname'] = null;
			$msg['msgid'] = null;
			return $msgptr = saveOutboundMessage($msg);
		}
	}
	else {
		$idfield = $msg['correstable'] == 'tblclient' ? 'clientid' : 'providerid';
		$person = fetchFirstAssoc("SELECT * FROM {$msg['correstable']} WHERE $idfield = {$msg['correspid']} LIMIT 1");
		$result = enqueueEmailNotification($person, $msg['subject'], $msg['body'], null, $_SESSION['auth_login_id'], 'html');
		if(is_array($result)) {
			$error = $result ? $result[0] : 'Unknown error';
			return "Error:<p>$error<br>(recipients: {$msg['correspaddr']})";
		}
	}
}

function sendOverdueConfirmationsEmails() {
	if(!in_array('relstaffnotification', fetchCol0("SHOW TABLES"))) return;
	require_once "event-email-fns.php";
	if(!staffToNotify('o')) return;
	$scriptPrefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference"); // in preference-fns.php
	$sql = "SELECT confid FROM tblconfirmation WHERE overduenoted = 0 AND resolution = 'pending' and NOW() >= due AND NOW() <= expiration";
	foreach(fetchCol0($sql) as $confid) {
		if(notifyStaffOfConfirmationChange($confid, $alternateFilter=null))
				updateTable('tblconfirmation', array('overduenoted'=>1), "confid = $confid", 1);
	}
}

function currentConfirmationStats($respondentTable=null) {
	$respondentTable = $respondentTable ? $respondentTable : 'tblclient';
	$pairs = fetchKeyValuePairs( 
		"SELECT resolution, count(*)
		 FROM tblconfirmation
		 WHERE respondenttable = '$respondentTable'
		 	AND 
		 	 (resolution = 'pending' AND expiration > NOW() AND NOW() >= due 
		 	  OR
		 	  resolution = 'received' AND FROM_DAYS(TO_DAYS(expiration)+7) > NOW()
		 	  OR
		 	  resolution = 'declined' AND FROM_DAYS(TO_DAYS(expiration)+7) > NOW())
		 	  GROUP BY resolution");
	foreach(array('pending','received','declined') as $status)
		if(!$pairs[$status]) $pairs[$status] = 0;
	// 'pending' is really overdue
	if($pairs['pending']) $pairs['overdue'] = $pairs['pending'];
	return $pairs;
}

function dbDatetimeOrNull($date) {
	return !$date ? null : date('Y-m-d h:i:s', strtotime($date));
}

function cancelConfirmation($confid, $cancellationreason) {
	// $confid may be a csv
	$resolvedby = isset($_SESSION) ? $_SESSION['auth_user_id'] : '0';
	$row = array('resolvedby'=>$resolvedby, 'resolution'=>'canceled', 'resolutiondate'=>date('Y-m-d H:i:s', time()), 'note'=>$cancellationreason);
	updateTable('tblconfirmation', $row, "confid IN ($confid)", 1);
}

function confirm($confid, $note=null, $notify=null) {
	// $confid may be a csv
	$resolvedby = isset($_SESSION) ? $_SESSION['auth_user_id'] : '0';
	$row = array('resolvedby'=>$resolvedby, 'resolution'=>'received', 'resolutiondate'=>date('Y-m-d H:i:s', time()), 'note'=>$note);
	updateTable('tblconfirmation', $row, "confid IN ($confid)", 1);
	if($notify) notifyStaffOfConfirmationChange($confid);
}

function confirmWithToken($token, $note=null, $notify=null) {
	$resolvedby = isset($_SESSION) ? $_SESSION['auth_user_id'] : '0';
	$row = array('resolvedby'=>$resolvedby, 'resolution'=>'received', 'resolutiondate'=>date('Y-m-d H:i:s', time()), 'note'=>$note);
	updateTable('tblconfirmation', $row, "token = '$token'", 1);
	if($notify) notifyStaffOfConfirmationChange(null, "token = '$token'");
}

function decline($confid, $responseMsgPtr, $notify=null) {
	// $confid may be a csv
	$resolvedby = isset($_SESSION) ? $_SESSION['auth_user_id'] : '0';
	$row = array('resolvedby'=>$resolvedby, 'resolution'=>'declined', 'resolutiondate'=>date('Y-m-d H:i:s', time()), 'responsemsgptr'=>$responseMsgPtr);
	updateTable('tblconfirmation', $row, "confid IN ($confid)", 1);
	if($notify) notifyStaffOfConfirmationChange($confid);
}

function declineWithToken($token, $responseMsgPtr, $notify=null) {
	$resolvedby = isset($_SESSION) ? $_SESSION['auth_user_id'] : '0';
	$row = array('resolvedby'=>$resolvedby, 'resolution'=>'declined', 'resolutiondate'=>date('Y-m-d H:i:s', time()), 'responsemsgptr'=>$responseMsgPtr);
	updateTable('tblconfirmation', $row, "token = '$token'", 1);
	if($notify) notifyStaffOfConfirmationChange(null, "token = '$token'");
}

function includeOriginalMessageInConfirmationNote() {
	return /*mattOnlyTEST() ||*/ dbTEST('pawlosophy');
}

function notifyStaffOfConfirmationChange($confid, $alternateFilter=null) {
	require_once "event-email-fns.php";
	require_once "field-utils.php";
	$includeMessage = includeOriginalMessageInConfirmationNote();
	$conf = confirmationStatus($confid, $includeDetail=true, $alternateFilter, $includeMessage);
	if(!$confid) $confid = $conf['confid'];
	$msg = fetchFirstAssoc("SELECT subject, datetime FROM tblmessage WHERE msgid = {$conf['msgptr']} LIMIT 1");
	$confSubject = $msg['subject'];
	$corresp = $conf['correspondent'];
	$role = $corresp['clientid'] ? 'Client' : 'Provider';
	$eventType = 'c';
	if($conf['status'] == 'received') {
		$subject = "$role Confirmation received from {$corresp['name']}";
		$msgBody = "$role ".correspondentLink($corresp)." confirmed the $confSubject.  Received: ".shortDateAndTime('now', 'mil').".";
	}
	else if($conf['status'] == 'declined') {
		$subject = "$role Confirmation declined by {$corresp['name']}";
		$msgBody = "$role ".correspondentLink($corresp)." declined the $confSubject.  Received: ".shortDateAndTime('now', 'mil').".";
		if($conf['detail']) $msgBody .= "<p>Detail: {$conf['detail']}";
	}
	else {
		$subject = "$role Confirmation for {$corresp['name']} status: {$conf['status']}";
		$msgBody = "$role ".correspondentLink($corresp)." confirmation of $confSubject: [{$conf['status']}].  Received: ".shortDateAndTime('now', 'mil').".";
		if($conf['status'] == 'overdue') 	$eventType = 'o';
		$detailKey = $conf['status'] == 'expired' 
				? 'Confirmation Expired' 
				: ($conf['status'] == 'overdue' 
						? 'Confirmation Due by'
						: ($conf['status'] == 'canceled'
								? 'Cancellation Note'
								: 'Detail'));

		
		if($conf['detail']) $msgBody .= "<p>$detailKey: {$conf['detail']}";
	}
	$email = emailLink($corresp);
	$phone = primaryPhoneNumber($conf);
	$phone = $phone ? $phone : 'No phone number found.';
	$msgBody .= "<p>Email: $email<p>Phone: $phone";
	if($includeMessage && (trim($conf['subject']) || trim($conf['body']))) {
		$msgBody .= "<hr>Original message:<p>";
		if(trim($conf['subject'])) $msgBody .= "Subject: ".trim($conf['subject']);
		if(trim($conf['body'])) $msgBody .= "<p>".trim($conf['body']);
	}
	require_once "comm-fns.php";
	return notifyStaff($eventType, $subject, $msgBody);
}


function correspondentLink($corresp) {
	$role = $corresp['clientid'] ? 'client' : 'provider';
	if($role == 'client') $url = "client-edit.php?id={$corresp['id']}&tab=services";
	else $url = "prov-schedule-cal.php?provider={$corresp['id']}";
	return "<a href='".globalURL($url)."'>{$corresp['name']}</a>";
}
	
function emailLink($corresp) {
	return "<a href='mailto:{$corresp['email']}'>{$corresp['email']}</a>";
}
	

// return pending | overdue | expired | received | canceled  
function confirmationStatus($confOrConfid, $includeDetail=true, $alternateFilter=null, $includeMessage=false) {
	if($confOrConfid && is_array(confOrConfid)) $row = $confOrConfid;
	else {
		$filter = $alternateFilter ? $alternateFilter : "confid = $confOrConfid";
		$row = fetchFirstAssoc(
			"SELECT * 
			 FROM tblconfirmation
			 WHERE 99=99 && $filter LIMIT 1");
	}
	$status = 
			$row['resolution'] != 'pending'
				? $row['resolution']
				: (time() >= strtotime($row['expiration'])
						? 'expired'
						: (time() >= strtotime($row['due']) 
								? 'overdue'
								: 'pending'));	
	if($includeDetail) {
		$detail = 
			$status == 'expired' 
				? shortDateAndTime(strtotime($row['expiration']), 'mil') 
				: ($status == 'overdue' 
						? shortDateAndTime(strtotime($row['due']), 'mil')
						: ($status == 'canceled'
								? $row['note']
								: ''));
		$idfield = $row['respondenttable'] == 'tblclient' ? 'clientid' : 'providerid';
		$correspondent = fetchFirstAssoc("SELECT $idfield, $idfield as id, CONCAT_WS(' ', fname, lname) as name, email, homephone, cellphone, workphone 
																			FROM {$row['respondenttable']} WHERE $idfield = {$row['respondentptr']} LIMIT 1");
		$status = array_merge($row, array('status'=>$status, 'detail'=>$detail, 'resolutiondate'=>$row['resolutiondate'], 'correspondent'=>$correspondent));
	}
	if($includeMessage && $row['msgptr']) {
		$msg = fetchFirstAssoc("SELECT subject, body FROM tblmessage WHERE msgid = {$row['msgptr']} LIMIT 1", 1);
		if($msg) $status = array_merge($status, $msg);
	}
	return $status;
}

function linkConfirmationToMessage($msgptr, $confid=null, $body=null) {
	if(!$confid) {
		$start = strpos($body, 'confirmationid=')+strlen('confirmationid=');
		for(;$start < strlen($body) && ctype_digit($body[$start]);$start++)
			$confid .= $body[$start];
	}
//logError("linkConfirmationToMessage confid: [$confid] msgptr: [$msgptr]");

	updateTable('tblconfirmation', array('msgptr'=>$msgptr), "confid = $confid", 1);
//logError(mysqli_error() ? mysqli_error() : 'No Error (update)');
	deleteTable('tblqueuedconf', "confid = $confid", 1);  // used to hold confids for queued messages until messages are sent
//logError(mysqli_error() ? mysqli_error() : 'No Error (delete)');
}
