<?
// incomplete-appts-handle.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "invoice-fns.php";
require_once "appointment-fns.php";
require_once "surcharge-fns.php";

// Determine access privs
$locked = locked('o-');


extract(extractVars('ids,surcharges,operation', $_REQUEST));

if($operation == 'complete') {
	$note = sqlVal("CONCAT_WS(' ', note,'  Marked complete by manager')");
	$mods = withModificationFields(array('completed'=>date('Y-m-d H:i:s'), 'note'=>$note, 'canceled'=>null));
  if($ids) {
		updateTable('tblappointment', $mods, "appointmentid IN ($ids)", 1);
  	if($_SESSION['surchargesenabled']) markAppointmentSurchargesComplete($ids);
	}
  
  if($surcharges) markSurchargesComplete($surcharges, $isFilter=false);
	//setAppointmentDiscounts(explode(',', $ids), true);  // discount value should not change
	if($ids) foreach(explode(',', $ids) as $id)
		logAppointmentStatusChange(array('appointmentid'=>$id, 'completed' => 1), "Mult visits complete - manager.");

	createBillablesForNonMonthlyAppts($ids);
	echo countAllProviderIncompleteJobs();
}
else if($operation == 'countincomplete') {
  echo countAllProviderIncompleteJobs();
}
else if($operation == 'sendReminders') {
  echo sendCompletionReminders($ids, $surcharges);
}
  
function sendCompletionReminders($ids, $surcharges) {
	require_once "comm-fns.php";
	require_once "comm-composer-fns.php";
	require_once "service-fns.php";
	if($ids) 
		$all = fetchAssociations("SELECT * FROM tblappointment WHERE appointmentid IN($ids)");
	if($surcharges) 
		$all = array_merge($all, fetchAssociations("SELECT * FROM tblsurcharge WHERE surchargeid IN($surcharges)"));
	usort($all, 'dateSort');
	foreach($all as $item) $clusters[$item['providerptr']][] = $item;
	foreach($clusters as $prov => $items) {
		if($error = sendCompletionReminderMessageFailure($prov, $items)) $errors[] = $error;
		else $successes++;
	}
	if($errors) $errors = "Errors:\n".join("\n", $errors)."\n";
	if(!$successes) echo "|$errors"."No reminders will be sent.";
	else {
		$sitters = $successes == 1 ? "one sitter" : "$successes sitters";
		echo "|$errors"."A reminder will be sent to $sitters in the next few minutes.";
	}
}
function dateSort($a, $b) { return strcmp($a['date'].$a['starttime'], $b['date'].$b['starttime']); }

function sendCompletionReminderMessageFailure($prov, $items) {
	$prov = getProvider($prov);
	if(!$prov['email']) return "No email address found for {$prov['fname']} {$prov['lname']}";
	static $surchargeNames, $clientNames, $serviceNames;
	if(!$clientNames) $clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");
	if(!$surchargeNames) $surchargeNames = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	if(!$serviceNames) $serviceNames = getAllServiceNamesById($refresh=1, $noInactiveLabel=true, $setGlobalVar=false);
	$looks = "style='padding-left:5px;'";
	foreach($items as $item) {
		$pets = $item['pets'] ? " ({$item['pets']})" : '';
		$service = $item['surchargeid'] ? "Surcharge: ".$surchargeNames[$item['surchargecode']] : $serviceNames[$item['servicecode']];
		$visits[] = "<tr><td>".shortDate(strtotime($item['date']))."</td><td $looks>{$item['timeofday']}</td>"
								."<td $looks>{$clientNames[$item['clientptr']]}$pets</td>"
								."<td $looks>$service</td></tr>";
	}
	$visits = "<table border=1 bordercolor=darkgrey><tr><th>Date</th><th>Time</th><th>Client</th><th>Service</th></tr>"
							.join(',', $visits)."</table>";
	
	if($template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Visit Completion Reminder' LIMIT 1")) {
		$subject = $template['subject'];
		$template = $template['body'];
	}
	else {
		$subject = "Reminder to mark visits complete";
		$template = "Dear #FIRSTNAME#,\n\nPlease mark the following visits and surcharges complete or canceled, as appropriate:"
							."\n\n#VISITS#\n\nThank you,\n\n#BIZNAME#";
	}
	$message = preprocessMessage($template, $prov, $template=null);
	$message = str_replace('#VISITS#', $visits, $message);
	enqueueEmailNotification($prov, $subject, $message, $cc=null, null, $html=true);
}