<? // native-api-visit-tracks.php
// REQUIRED PARAMETERS: loginid,password,appointmentptr
// RETURNS a JSON array
// if error, JSON array has the form of {"ERROR","Explanation of error.}
// otherwise, JSON array has the form of {{track1},{track2},...{trackN}}
// a track has these fields: date, lat, lon, speed, heading, accuracy, appointmentptr, event 

require_once "common/init_session.php";
require_once "native-sitter-api.php";

// REQUIRED PARAMETERS

extract(extractVars('loginid,password,appointmentptr', $_REQUEST));

if($_GET['test']) {
	$loginid = 'dlifebri';
	$password = 'QVX992';
	$appointmentptr = '156796';
}



if(!$appointmentptr) {
	echo json_encode(array('ERROR'=>"NO appointment ID."));
	exit;
}

if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
	echo json_encode(array('ERROR'=>$userOrFailure));
	exit;
}

$user = $userOrFailure;

$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $appointmentptr LIMIT 1", 1);

$userRole = $user['rights'][0];
if($userRole == 'p') {
	$provid = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");
	if($appt['providerptr'] != $provid) {
		echo json_encode(array('ERROR'=>"NOT A VISIT BY SITTER $provid"));
		exit;
	}
}
if($userRole == 'c') {
	$clientid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE userid = {$user['userid']} LIMIT 1");
	if($appt['clientptr'] != $clientid) {
		echo json_encode(array('ERROR'=>"NOT A VISIT TO CLIENT $clientid"));
		exit;
	}
}

$tracks = fetchAssociations("SELECT * FROM tblgeotrack 
														WHERE appointmentptr = $appointmentptr
														AND !(lat = 0 AND lon = 0)
														ORDER BY date");
// coordinates MUST have appointmentptr,lat,lon,event,accuracy
// coordinates MAY include speed, heading, error
$points = array();
foreach($tracks as $track) {
	if($track['error']) continue;
	if($track['event'] == 'arrived') {
		$hasArrived = true;
	}
	else if($track['event'] == 'completed') {
		$hasCompleted = true;
	}
	else if($track['event'] == 'arrived_completed') { // never
		$hasArrived = true;
		$hasCompleted = true;
	}
	else if($track['event'] == 'mv') {
		if(!$hasArrived) continue;
	}
	$point = 
		array(
			'date'=>$track['date'],
			'lat'=>$track['lat'],
			'lon'=>$track['lon'],
			'speed'=>$track['speed'],
			'heading'=>$track['heading'],
			'accuracy'=>$track['accuracy'],
			'appointmentptr'=>$track['appointmentptr'],
			'event'=>$track['event']
		);
	$points[] = $point;
	if($hasCompleted) break;
}
echo json_encode($points);
