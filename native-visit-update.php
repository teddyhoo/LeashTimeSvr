<? // native-visit-update.php
// accept updates to a visit: note, mood/status buttons
//echo "BANG! [{$_REQUEST['loginid']}][{$_REQUEST['password']}]";exit;
require_once "common/init_session.php";
require_once "native-sitter-api.php";
require_once 'appointment-client-notification-fns.php';
require_once "preference-fns.php";

//foreach(explode(',', 'loginid,password,coords,datetime,note,buttons,appointmentptr,appointmentid') as $k) $ps[] = "$k ({$_REQUEST[$k]})";
//echo "REQUEST: ".join(' ', $ps);exit;
//echo "REQUEST: ".print_r($_REQUEST,1);exit;

extract(extractVars('loginid,password,datetime,note,buttons,appointmentptr,appointmentid,version,photosent', $_REQUEST));

if($_GET['ttest']) {
	$loginid = 'madtest';
	$password = 'QVX992DISABLED';
	//https://leashtime.com/native-visit-update.php?test=1
	$datetime = date('Y-m-d H:i:s');
	$appointmentptr = 65113;
	$note = "Mikey likey";
	$buttons = '{"happy":"yes","peed":"1","pooped":"no"}';
}

if($_GET['test']) {
	$loginid = 'dlifebri';
	$password = 'QVX992DISABLED';
	//https://leashtime.com/native-visit-update.php?test=1
	$datetime = date('Y-m-d H:i:s');
	$appointmentptr = 154407;
	$note = "Mikey likey";
	$buttons = '{"happy":"yes","peed":"1","pooped":"no"}';
}

if($_GET['test99']) {
	$loginid = 'ppc.jody';
	$password = 'jody';
	//https://leashtime.com/native-visit-update.php?test99=1
	$datetime = date('Y-m-d H:i:s');
	$appointmentptr = 68142;
	$note = "Test by Matt ";
	$buttons = '{"happy":"yes","peed":"1","pooped":"no"}';
}

if($_GET['test100']) {
	/*loginid=dlifebri&
password=pass&
datetime=2016-01-12 21:03:46&
appointmentptr=166636&
note=[VISIT: 21:03 PM] [MGR NOTE] &
buttons={"pee":"no",
"poo":"no",
"play":"no",
"happy":"no",
"hungry":"no",
"angry":"no",
"shy":"no",
"sad":"no",
"sick":"no",
"cat":"no",
"litter":"no"}&
appointmentid=166636*/
	$loginid = 'dlifebri';
	$password = 'pass';
	//https://leashtime.com/native-visit-update.php?test99=1
	$datetime = '2016-01-12 21:03:46';
	$appointmentptr = 166636;
	$note = "[VISIT: 21:03 PM] [MGR NOTE] ";
	$buttons = '{"pee":"no",
"poo":"no",
"play":"no",
"happy":"no",
"hungry":"no",
"angry":"no",
"shy":"no",
"sad":"no",
"sick":"no",
"cat":"no",
"litter":"no"}';

}
/* 

REQUIRED
loginid, password,datetime,(note | at least one button=yes), appointmentptr
coords may be a single coord or an array with a single coord in it:
The supplied coordinate MUST have appointmentptr,lat,lon,event,accuracy
The supplied coordinate MAY include speed, heading, error
event must be one of complete, arrived (not mv)

*/

// SLOP
if(!$appointmentptr) $appointmentptr = $appointmentid;
if(!$appointmentptr) $errors[] = "appointmentptr not supplied [{$_REQUEST['appointmentptr']}]";

if($buttons) {
	$buttonsJSON = $buttons;
	$buttons = json_decode($buttonsJSON, $assoc=true);
	if($buttons === null) {
		$errors[] = "bad JSON supplied for buttons: $buttonsJSON";
		echo "ERROR:".join('|', $errors);
		//logLongError("native-visit-update ($loginid):".join('|', $errors));
		//exit;
	}
}
else {
	// QUESTION: Is this an error condition, or should we proceed?
	$buttons = array();
}
//echo "BUTTONS JSON: ".print_r($buttonsJSON);
//echo "\nBUTTONS: ".print_r($buttons,1)."\n";
foreach($buttons as $k => $v) $buttons[$k] = $v && $v != 'no' ? 1 : 0;
$note = trim((string)$note);
if(!$note) {
	foreach($buttons as $k => $v) if($v) $numButtons += 1;
	// QUESTION: Is this an error condition, or should we proceed?
	if(!$numButtons) {
		$errors[] = "no note or buttons supplied buttons: [$buttonsJSON] note: [{$_REQUEST['note']}]";
		//echo "ERROR:".join('|', $errors);
		//logLongError("native-visit-update ($loginid):".join('|', $errors));
		//exit;
	}
}

if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
	echo $userOrFailure;
	exit;
}
$user = $userOrFailure;
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);

logScriptCallForThisDB();
//logError("TED TEST:$note");
//if(dbTEST('tonkatest')) logError("JODY TEST:$note");

//print_r($_POST);
//echo "=========\ncoords:\n$coords=========\nDecoded:\n";
if(!$datetime) {
	$errors[] = "no datetime supplied";
}
else {
	if(strtotime($datetime)) $datetime = date("Y-m-d H:i:s", strtotime($datetime));
}


$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE userid = '{$user['userid']}' LIMIT 1");
if(!$provider)  $errors[] = "unknown sitter[$loginid]";
else $_SESSION["fullname"] = "{$provider['fname']}";

if(!$errors) {
		$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = '$appointmentptr' LIMIT 1");
		if(!$appt /*&& $apptid != -999*/) $errors[] = "unknown visit[$appointmentptr]";
		else if($appt['providerptr'] != $provider['providerid']) $errors[] = "visit [{$appt['appointmentid']}] is not assigned to {$provider['fname']}";
	}
if(!$errors) {
	if($datetime) setAppointmentProperty($appt['appointmentid'], "visitreportdatetime", $datetime);	
	setAppointmentProperty($appt['appointmentid'], "visitreportreceived", date("Y-m-d H:i:s"));	
	$newVisitNote = extractSitterNote($note); // Handle Ted's formatted note
	if($newVisitNote) {
		$oldNote = getAppointmentProperty($appointmentptr, 'oldnote');
		$oldNote = $oldNote ? $oldNote : $appt['note'];
		if($oldNote && trim($oldNote)) setAppointmentProperty($appointmentptr, 'oldnote', $oldNote);
	}	
	if($newVisitNote) $mods = withModificationFields(array('note'=>$newVisitNote));
	
	if($mods) updateTable('tblappointment', $mods, "appointmentid = {$appt['appointmentid']}", 1);
	
if($newVisitNote && $_SESSION['preferences']['enableSitterNotesChatterMods']) {
	require_once "chatter-fns.php";
	/*TEMP*/ ensureChatterNoteTableExists();
	addVisitChatterNote($appointmentptr, $newVisitNote, $provider['providerid'], $authortable='tblprovider', $visibility=2, $replyTo=null);
}
	
	
	require_once "preference-fns.php";
	require_once 'request-fns.php';
//print_r($buttons);
	foreach($buttons as $k=>$v)
		setAppointmentProperty($appt['appointmentid'], "button_$k", $v);
		
	if($version) setAppointmentProperty($appt['appointmentid'], "apprequestversion", $version);	
	if($photosent) setAppointmentProperty($appt['appointmentid'], "photosent", $photosent);	
		
	$allPrefs = fetchPreferences();
	$checkPrefs = "enhancedVisitReportArrivalTime|enhancedVisitReportCompletionTime"
	."|enhancedVisitReportVisitNote|enhancedVisitReportMoodButtons"
	."|enhancedVisitReportPetPhoto|enhancedVisitReportRouteMap";
	$sendEnhancedVisitReport = false;
	foreach(explode('|', $checkPrefs) as $key) if($allPrefs[$key]) $sendEnhancedVisitReport = true;


	if($sendEnhancedVisitReport) {
		if(clientShouldBeNotified($appt['clientptr'])) {			
			// check for probable duplicates
			if(TRUE || dbTEST('dogslife')) {
				// the MGR note gets folded into the note first time through, screwing up subsequent comparison.
				$hashNote = $note && strpos($note, "[MGR NOTE]") ? substr($note, strpos($note, "[MGR NOTE]")) : $note;
				$hash = sha1($jsonHashFodder = json_encode(createVisitReportRequest($appt, $buttonsJSON, $hashNote, $sent=null)));
				$stopItsADup =
					$hash == getClientPreference($appt['clientptr'], 'lastVisitReportHash');
				setClientPreference($appt['clientptr'], 'lastVisitReportHash', $hash);
				if(dbTEST('itsadogslifeny')) { // for Robyn Seaman
					logLongError("VR: $jsonHashFodder / $hash");
				}
				if($stopItsADup) logLongError("native-visit-update ($loginid): Dup VR: $hash");
			}
	
			
			if(!$stopItsADup) { // do not stop, even if it is a dupicate // !$stopItsADup
				if(getProviderPreference($provider['providerid'], 'sitterReportsToClientViaServerAfterApproval')) {
					// CREATE A NEW REQUEST OF TYPE EnhancedVisitReport (sent=false)
					$request = createVisitReportRequest($appt, $buttonsJSON, $note, $sent=null); // SEE: appointment-client-notification-fns.php
					saveNewGenericRequest($request, $appt['clientptr']);
					// DO NOT NOTIFY CLIENT
					$visitreportsubmissiontype = 'approvalrequired';
				}
				else {
					$visitreportsubmissiontype = 'directtoclient';
					setClientPreference($appt['clientptr'], 'lastVisitReport',
						json_encode(createVisitReportRequest($appt, $buttonsJSON, $note, $sent=null)));
					
					setVisitReportPublic($appointmentptr, true);

					$_SESSION["providerfullname"] = "{$provider['fname']} {$provider['lname']}";

					$sendImmediately = 'immediately';// !dbTEST('dogslife,tonkapetsitters');  //$allPrefs['delayVisitReportEmailSending'] ? null : 'immediately';
					//$sendImmediately = mattOnlyTEST() ? null : 'immediately';
					if($sendImmediately) {// old style
						$messageptr = sendEnhancedVisitReportEmail($appt['appointmentid'], $sendImmediately); // SEE: appointment-client-notification-fns.php
					}
					if(is_array($messageptr)) { // ERROR
						if($ALTEmailAddressTEST) {  //TBD - get rid of this test after beta test -- see also appointment-client-notification-fns.php
							// generate a System Notification
							$subjectAndMessage = visitReportErrorNotification($messageptr); // $messageptr is actually an array here
							//logError('Whoop: '.print_r($subjectAndMessage, 1));
							saveNewSystemNotificationRequest(
									$subjectAndMessage['subject'], 
									$subjectAndMessage['message'], 
									$extraFields = null);
						}
						$errors[] = $messageptr[0];
					}
					if(!$errors) { // starting with version 2
						if($version) setAppointmentProperty($appt['appointmentid'], "apprequestversion", $version);	
						if($photosent) setAppointmentProperty($appt['appointmentid'], "photosent", $photosent);
						if($arrived) registerVisitTrack($arrived);
						if($completed) registerVisitTrack($arrived);
					}
					if(!$errors 
						//&& getClientPreference($appt['clientptr'], 'visitreportGeneratesClientRequest')
						&& !getProviderPreference($appt['providerptr'], 'visitreport_NO_ClientRequest')
						) {
						// CREATE A NEW REQUEST OF TYPE EnhancedVisitReport (sent=true)
	//if(dbTEST('tonkatest')) logError("about to createVisitReportRequest: [$appointmentptr]");		
						$request = createVisitReportRequest($appt, $buttonsJSON, $note, $messageptr, 
																									$sentby="{$provider['fname']} {$provider['lname']}");
	//if(dbTEST('tonkatest')) logError("POST createVisitReportRequest: [$appointmentptr]");		
						require_once 'request-fns.php';
						$requestPtr=saveNewGenericRequest($request, $appt['clientptr']);
					}
					
					if(!$sendImmediately) // new style: when sent the message will refer back (via tags) to the request, if any
						orderDelayedVisitReportEmail($appt['appointmentid'], $delaySeconds=180, $requestPtr); // SEE: appointment-client-notification-fns.php				
				}
				setAppointmentProperty($appt['appointmentid'], 'reportsubmissiontype', $visitreportsubmissiontype);
				setAppointmentProperty($appt['appointmentid'], 'reportsubmissiondate', date('Y-m-d H:i:s'));
			}
		}
	}
}

endRequestSession();

if($errors) {
	echo "ERROR:".join('|', $errors);
	logLongError("native-visit-update ($loginid):".join('|', $errors));
}
else {
	if(!$version) echo "OK";
	else {
		// return diagnostic info to native app
		$result['received'] = $datetime;
		
		$visitphotocacheid = getAppointmentProperty($appt['appointmentid'], "visitphotocacheid");
		$result['photoreceived'] = $visitphotocacheid ? 1 : 0;
		
		$visitmapcacheid = getAppointmentProperty($appt['appointmentid'], "visitmapcacheid");
		$result['photoreceived'] = $visitmapcacheid ? 1 : 0;
		
		$result['status'] = 
			$visit['completed'] ? 'completed' : (
			$visit['canceled'] ? 'canceled' : (
			$visit['arrived'] ? 'arrived' : null));
			
		echo json_encode($result);

	}
}

//if(dbTEST('dogslife')) logError("NVU: ".($errors ? join('|', $errors) : 'OK'));
