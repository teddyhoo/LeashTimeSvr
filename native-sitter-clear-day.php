<? // native-sitter-clear-day.php?loginid=dlifebri&password=QVX992&date=2014-12-18
require_once "common/init_session.php";
require_once "native-sitter-api.php";

extract(extractVars('loginid,password,date', $_REQUEST));


if($_GET['test']) {
	$loginid = 'dlifebri';
	$password = 'QVX992';
	$coords = '[{"appointmentptr":"152316","date":"2014-10-24 18:32:12","lat":"38.9012","lon":"-77.2653","accuracy":"30","event":"arrived","speed":"3","heading":"23","error":"?" }]';
	$datetime = date('Y-m-d H:i:s');
}





/* 
Clear all arrived|complete events for a sitter on a given day.:

REQUIRED
loginid, password,date

*/

if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
	echo $userOrFailure;
	exit;
}
$user = $userOrFailure;
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);

//print_r($_POST);
//echo "=========\ncoords:\n$coords=========\nDecoded:\n";
if(!$date) {
	$errors[] = "no datetime supplied";
}
else $date = date("Y-m-d", strtotime($date));

$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE userid = '{$user['userid']}' LIMIT 1");
if(!$provider)  $errors[] = "unknown sitter[$loginid]";
else $_SESSION["fullname"] = "{$provider['fname']}";

if(!$errors) {
	$apptids = fetchCol0("SELECT appointmentid FROM tblappointment 
		WHERE providerptr = {$provider['providerid']}
		AND date = '$date'
		AND canceled IS NULL");
	foreach($apptids as $apptid) {
		$mods = withModificationFields(array('completed'=>$null));
//print_r($coord);exit;		
		updateTable('tblappointment', $mods, "appointmentid = $apptid", 1);
		logAppointmentStatusChange(array('appointmentid'=>$apptid, 'completed' => 1), "native-sitter-clear-day.php");
		deleteTable('tblgeotrack', "appointmentptr = $apptid", 1);
//$DEBUG = mattOnlyTEST() ? "alert(parent);" : '';		
	}
	
}
endRequestSession();
if($errors) {
	echo "ERROR:".join('|', $errors);
	logLongError("native-visit-action ($loginid):".join('|', $errors));
}
else echo "OK";