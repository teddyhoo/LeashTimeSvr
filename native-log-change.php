<? // native-log-change.php
// log a change from the native sitter app
/*
parameters:
loginid
password
type -- for visit, "tblappointment" is recommended
id -- for visit, this would be the visit ID (appointmentid)
operation -- one character.  'e' (for eent) is recommended
note - string, 255 chars max

https://leashtime.com/native-log-change.php?loginid=dlifebri&password=pass&type=tblappointment&id=206128&operation=e&note=This+is+a+note+from+the+native+sitter+app
*/

require_once "common/init_session.php";
require_once "native-sitter-api.php";
require_once 'appointment-client-notification-fns.php';
require_once "preference-fns.php";


extract(extractVars('loginid,password,type,id,operation,note', $_REQUEST));


if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
	echo $userOrFailure;
	exit;
}
$user = $userOrFailure;
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
$operation = $operation ? "$operation " : 'e';
logChange($id, $type, $operation, $note);
$result = array('status'=>'OK');
if(!$_REQUEST['debug']) header("Content-type: application/json");

echo json_encode($result);
