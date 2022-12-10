<? // native-sitter-location.php
require_once "common/init_session.php";
require_once "native-sitter-api.php";

// Stephanie Lichner
//header("Content-type: application/json");
//echo json_encode(array('ted'=>"[{$_SERVER['REQUEST_METHOD']}] ".print_r($_REQUEST,1)));
//exit;

if(FALSE && !$_POST) {
//echo "GET! ".print_r($_REQUEST,1);
	
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	
	echo json_encode(fetchAssociations(
		"SELECT date,lat,lon,speed,heading,accuracy,null as appointmentptr,'mv' as event,error, 893 as userptr 
			FROM `tblgeotrack` 
			WHERE date LIKE '2011-04-06%'
			 ORDER BY userptr, date"));
	exit;
}



extract(extractVars('loginid,password,appointmentid,coords', $_POST));
//echo "POST [loginid:$loginid] [password:$password] [appointmentid:$appointmentid] [coords:...]\n";

//logError(-999.'|TED|'.print_r($_REQUEST));


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
$coords = json_decode($coords, $assoc=true);
//echo print_r($coords,1)."\n=========================";

if($coords && !(is_array($coords))) logError("Bad value for coords (native-sitter-location.php) user: {$user['userid']}");
else foreach((array)$coords as $coord) {
	if(!$coord['event'] ||
			!$coord['accuracy']
			) {
		echo "event is NULL"; 
		exit;
	}
	$coord['heading'] = $coord['heading'] ? : '0';
	$coord['speed'] = $coord['speed'] ? : '0';
	$coord['userptr'] = $_SESSION['auth_user_id'];
	insertTable('tblgeotrack', $coord, 1);
}

echo "OK";

if(!$DEBUG_NO_QUIT) endRequestSession();





