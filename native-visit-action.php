<? // native-visit-action.php
require_once "common/init_session.php";
require_once "native-sitter-api.php";
require_once "preference-fns.php";

extract(extractVars('loginid,password,coords,datetime', $_REQUEST));


if($_GET['test']) {
	$loginid = 'dlifebri';
	$password = 'QVX992DISABLED';
	//$coords = '[{"appointmentptr":"152316","date":"2014-10-24 18:32:12","lat":"38.9012","lon":"-77.2653","accuracy":"30","event":"arrived","speed":"3","heading":"23","error":"?" }]';
	$coords = '[{"appointmentptr":"164656","date":"2015-11-24 17:52:12","lat":"38.9012","lon":"-77.2653","accuracy":"30","event":"completed","speed":"3","heading":"23","error":"?" }]';
	$datetime = date('Y-m-d H:i:s');
}





/* 
Process an arrived|complete notification from the native client:

REQUIRED
loginid, password,coords,datetime
coords may be a single coord or an array with a single coord in it:
The supplied coordinate MUST have appointmentptr,lat,lon,event,accuracy
The supplied coordinate MAY include speed, heading, error
event must be one of complete, arrived (not mv)

*/

if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
	echo $userOrFailure;
	exit;
}
$user = $userOrFailure;
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);

logScriptCallForThisDB();

//print_r($_POST);
//echo "=========\ncoords:\n$coords=========\nDecoded:\n";
if(!$coords) {
	$errors[] = "no coords supplied";
}

if(!$datetime) {
	$errors[] = "no datetime supplied";
}
else $datetime = date("Y-m-d H:i:s", strtotime($datetime));

$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE userid = '{$user['userid']}' LIMIT 1");
if(!$provider)  $errors[] = "unknown sitter[$loginid]";
else $_SESSION["fullname"] = "{$provider['fname']}";

if($coords) {
	$coordsJSON = $coords;
	$coord = json_decode($coordsJSON, $assoc=true);
	if(!$coord['lat']) $coord = $coord[0];
	if(!$coord['appointmentptr']) $coord['appointmentptr'] = $coord['appointmentid'];
	
	foreach(explode(',', 'lat,lon,event,accuracy,appointmentptr') as $fld)
		if(!$coord[$fld]) $missing[] = $fld;
	if($missing) $errors[] = "invalid coords:missing ".join(',', $missing).", $coordsJSON";
	
	if(!$errors) {
		$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = '{$coord['appointmentptr']}' LIMIT 1");
		if(!$appt /*&& $apptid != -999*/) $errors[] = "unknown visit[{$coord['appointmentptr']}]";
		else if($appt['providerptr'] != $provider['providerid']) $errors[] = "visit [{$appt['appointmentid']}] is not assigned to {$provider['fname']}";
	}
	if(!$errors) {
		$coord['heading'] = $coord['heading'] ? : '0';
		$coord['speed'] = $coord['speed'] ? : '0';
		$coord['userptr'] = $_SESSION['auth_user_id'];
		if($coord['event'] == 'complete') $coord['event'] = 'completed';
		if($coord['event'] == 'arrive') $coord['event'] = 'arrived';
		if($coord['event'] == 'arrived' || $coord['event'] == 'completed') {
			setAppointmentProperty($coord['appointmentptr'], $coord['event']."_recd", date('Y-m-d H:i:s'));
			$agent = strtoupper($_SERVER["HTTP_USER_AGENT"]);
			if(FALSE && dbtest('dogslife')) logError("native action agent: $agent");
			if(strpos($agent, 'LEASHTIME') !== FALSE) {
				$platform = 
					strpos($agent, 'IOS') !== FALSE ? 'IOS' : (
					strpos($agent, 'ANDROID') !== FALSE ? 'AND' : 'U');
				setAppointmentProperty($coord['appointmentptr'], 'native', $platform);
			}
		}
		if($coord['event'] == 'unarrive') $coord['event'] = 'unarrived';
		$datetime = $coord['date'] ? $coord['date'] : $datetime; // use date recorded in coord in pref to datetime supplied
		if(/*dbTEST('dogslife') && */$coord['event'] == 'arrived') {
			// wipe any appt events that happen to be in the db
			deleteTable('tblgeotrack', "appointmentptr = {$coord['appointmentptr']} AND date < '{$datetime}'", 1);
			// if any appt events deleted, log the fact
			if($deleted = mysql_affected_rows())
				logChange($coord['appointmentptr'], 'tblgeotrack', 'd', "arrived again.deleted $deleted coords");
		}
		$coord['lat'] = $coord['lat'] ? $coord['lat'] : '0';
		$coord['lon'] = $coord['lon'] ? $coord['lon'] : '0';
		/*if($coord['lat'] && $coord['lon']) */
		recordPosition($apptid, $coord, $datetime);
		if($coord['event'] == 'completed' && !$appt['completed']) { // do not re-complete appt if completed
			$mods = withModificationFields(array('completed'=>$datetime, 'canceled'=>null));
//print_r($coord);exit;		
			updateTable('tblappointment', $mods, "appointmentid = {$appt['appointmentid']}", 1);
			if($_SESSION['surchargesenabled']) markAppointmentSurchargesComplete($appt['appointmentid']);
			require_once "invoice-fns.php";
			createBillablesForNonMonthlyAppts(array($appt['appointmentid']));
			logAppointmentStatusChange(array('appointmentid'=>$appt['appointmentid'], 'completed' => 1), "Mobile visit complete - provider.");
	//$DEBUG = mattOnlyTEST() ? "alert(parent);" : '';		
		}
		else if($coord['event'] == 'unarrived') {
			logChange($coord['appointmentptr'], 'tblappointment', 'm', "unarrived lat: {$coord['lat']} lon: {$coord['lon']}");
			deleteTable('tblgeotrack', "appointmentptr = {$coord['appointmentptr']}", 1);
		}
		require_once "preference-fns.php";
		require_once "appointment-client-notification-fns.php";
		if(TRUE || fetchPreference('clientArrivalCompletionRealTimeNotification')) { // always true in native sitter app
			$operation = $coord['event'] == 'complete' ? 'completed' : $coord['event'];  // geotrack expects 'completed', not 'complete'
			
			$notifyClientCompletionDetails = 
				$operation == 'arrived' &&	getClientPreference($appt['clientptr'], 'notifyClientArrivalDetails')
				|| ($operation == 'completed' &&	allowNativeClientCompletionNotification($appt['clientptr']));
			if($notifyClientCompletionDetails) {
			//function notifyClient($source, $event, $note) 
//if(mattOnlyTEST()) echo "BANG!";			
				// earlyArrivalMarkingLimit - minutes default: 240 minutes
				$earlyArrivalMarkingLimit =
					fetchRow0Col0("SELECT value FROM tblpreference where property= 'earlyArrivalMarkingLimit' LIMIT 1", 1);
				$earlyArrivalMarkingLimit = $earlyArrivalMarkingLimit ? $earlyArrivalMarkingLimit : 240;
				$lateCompletionMarkingLimit = 120;
				require_once "appointment-fns.php";
				$times = timesForApptStartAndEnd($appt);
				$now = time();
				// rules: if event is more than 2 hours late, do not send notification
				//				if event is more than 4 hours early, do not send notification
				if(($times['start'] - $now > $earlyArrivalMarkingLimit * 60)
						|| ($now - $times['end'] > $lateCompletionMarkingLimit * 60)) {
				
				//if($appt['date'] != date('Y-m-d')) { 
					// $endTime = 
					
					logError("visit [{$appt['appointmentid']}] on [{$appt['date']}] login: [$loginid] event [$operation] iOS app");
				}
				else notifyClient($appt, $operation, '');
			}
		}
	}
}

function allowNativeClientCompletionNotification($clientptr) {
	if(dbTEST('careypet')) {
		return false;
		//if(!fetchPreference('notifyClientCompletionDetailsNATIVE')) return false;
		//else return getClientPreference($clientptr, 'notifyClientCompletionDetails');
	}
	else return getClientPreference($clientptr, 'notifyClientCompletionDetails');
}

endRequestSession();
if($errors) {
	echo "ERROR:".join('|', $errors);
	logLongError("native-visit-action ($loginid):".join('|', $errors));
}
else echo "OK";