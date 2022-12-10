<? //appointment-cancel.php
// id may be one id or id1,id2,...
// cancel: if false, then uncancel
// callers: calendar-package-irregular.php, client-edit.php, homepage_owner.php, prov-own-schedule-list.php
//          prov-schedule-list.php, wag.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "service-fns.php";
require_once "preference-fns.php";

locked('o-');
$result = cancelAppointments($_GET['id'], $_GET['cancel'], $additionalMods=null, $generateMemo=true, $initiator='cancel/uncancel button');
$appts = fetchAssociations("SELECT * FROM tblappointment WHERE appointmentid IN ({$_GET['id']}) ORDER BY date, starttime");
if(count($appts) == 1) $tooLateForNotification = (appointmentFuturity($appts[0]) < 0);
$client = getClient($appts[0]['clientptr']);
$clientPrefs = getMultipleClientPreferences($client['clientid'], 'autoEmailApptCancellations,autoEmailApptReactivations,confirmApptCancellations,confirmApptReactivations');
if(!$tooLateForNotification && (($clientPrefs['autoEmailApptCancellations'] && $_GET['cancel']) 
		|| ($clientPrefs['autoEmailApptReactivations'] && !$_GET['cancel'])) ) {
	require_once "comm-fns.php";
	$names = getServiceNamesById();
	$lastDate = null;

	foreach($appts as $i => $appt) {
		if($appt['date'] != $lastDate) $body .= "<tr><td class='daybanner' colspan=4>".date('F j', strtotime($appt['date']))."</td></tr>";
		$lastDate = $appt['date'];
		$body .= "<tr><td>{$names[$appt['servicecode']]}</td><td>{$appt['timeofday']}</td><td>{$appt['pets']}</td></tr>\n";
	}
	$style = "<style>.daybanner {background: yellow;font-weight:bold;text-align:center;}"
						."th {font-weight:bold;text-align:left;} </style>\n";
	$action = $_GET['cancel'] ? "cancellation" : "reactivation";
	$body = $style
					."Dear {$client['fname']} {$client['lname']},<p>"
					."This note is to confirm $action of the following visit".(count($appts) > 1 ? 's:' : ':')
					."<p>"
					."<table width=100%><tr><th>Service</th><th>Time</th><th>Pets</th></tr>\n"
					.$body
					."</table><p>" //##LINK##
					."Sincerely,<p>"
					.$_SESSION['preferences']['bizName'];
					
	/*if(($clientPrefs['confirmApptCancellations'] && $_GET['cancel']) 
		|| ($clientPrefs['confirmApptReactivations'] && !$_GET['cancel']) )
		$body = str_replace('##LINK##',
													confirmationRequest($recipient, 
																								"Please click this link to <a href='##ConfirmationURL##'>confirm</a> this change.<p>"),
													$body);
	else $body = str_replace('##LINK##', '', $body);*/
	
	enqueueEmailNotification($client, ($_GET['cancel'] ? "Visit Cancellation" : "Visit Reactivation"), $body, null, null, true);
//if($_GET['test'])	{echo $body;exit;}
}

echo $result;
