<? // native-client-visit-report-list.php
require_once "common/init_session.php";
require_once "native-sitter-api.php";

/*authenticate loginid/password

    on failure, return a single character:
        P - bad password
        U - unknown user
        I - inactive user
        F - No Business Found
        B - Business Inactive
        M - Missing Organization
        O - Organization inactive
        R - rights are missing or mismatched
        C - No cookie
        L - account locked
        S - not a sitter
        
        //https://leashtime.com/native-prov-multiday-list.php?loginid=dlifebri&password=QVX992DISABLED&start=2014-12-13&end=2014-12-18
*/

extract(extractVars('loginid,password,start,end,clientid', $_REQUEST));
//extract(extractVars('loginid,password,date', $_GET));

if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
	echo $userOrFailure;
	exit;
}

$user = $userOrFailure;
if(strpos($user['rights'], 'p') !== 0) {
	echo "S";
	exit;
}


$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);

$provid = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");
if(!$provid) $errors[] = "ERROR: No provider found for user {$user['userid']}";
if(!$clientid) $errors[] = "ERROR: No client id supplied.";
else {
	$clientid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE clientid = $clientid LIMIT 1");
	if(!$clientid) $errors[] = "ERROR: No client found for client id [$clientid]";
}

if(!$start || !strtotime($start)) $errors[] = "ERROR: Bad start parameter [$start]";
if(!$end || !strtotime($end)) $errors[] = "ERROR: Bad end parameter [$end]";
if($errors) {
	echo join("\n", $errors); 
	exit;
}
$start = date('Y-m-d', strtotime($start));
$end = date('Y-m-d', strtotime($end));

clientVisitReports($clientid, $start, $end);