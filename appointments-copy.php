<? //appointments-copy.php  ?client=XXX&packageid=XXX&target="+day+"&sels="+sels

// appointments-copy.php?client=421&packageid=1703&sels=38957&target=2009-10-14

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "preference-fns.php"; // for surcharges (findApplicableSurcharges)

locked('o-');
extract(extractVars('client,packageid,target,sels', $_REQUEST));

copyAppointments($sels, $packageid, $target, $client);

if($misassignedAppts) {
	$appts = fetchAssociationsKeyedBy(
		"SELECT appointmentid, date, timeofday, label, CONCAT_WS(' ', fname, lname) as clientname 
			FROM tblappointment
			LEFT JOIN tblservicetype ON servicetypeid = servicecode
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE appointmentid IN (".join(',', array_keys($misassignedAppts)).")
			ORDER BY date, starttime", 'appointmentid');
	foreach($appts as $apptid => $appt) {
		$date = date('F j', strtotime($appt['date']));
		if($misassignedAppts[$apptid] == 'timeoff')
			$timeofflabels[] = "<b>$date</b> {$appt['timeofday']} {$appt['label']} for {$appt['clientname']}";
		else if($misassignedAppts[$apptid] == 'conflict')
			$conflictlabels[] = "<b>$date</b> {$appt['timeofday']} {$appt['label']} for {$appt['clientname']}";
		else if($misassignedAppts[$apptid] == 'inactive')
			$inactivelabels[] = "<b>$date</b> {$appt['timeofday']} {$appt['label']} for {$appt['clientname']}";

	}
	if($error) $error = "<p style='color:red'>$error</p>";
	$message = "<span style='font-size:1.5em'>{$error}";
	if($timeofflabels) 
		$message .= "Because of scheduled time off, no sitter was assigned for the following visit".(count($timeofflabels) == 1 ? '' : 's').":"
								."<ul><li>".join('<li>', $timeofflabels)."</ul>";
	if($conflictlabels) 
		$message .= "Because of exclusive service conflicts, no sitter was assigned for the following visit".(count($conflictlabels) == 1 ? '' : 's').":"
								."<ul><li>".join('<li>', $conflictlabels)."</ul>";
	if($inactivelabels) 
		$message .= "Because the sitter is now inactive, no sitter was assigned for the following visit".(count($inactivelabels) == 1 ? '' : 's').":"
								."<ul><li>".join('<li>', $inactivelabels)."</ul>";
	$message .= "</span>";
	$_SESSION['user_notice'] = $message;
}
