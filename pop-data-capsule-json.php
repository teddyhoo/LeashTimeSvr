<? // pop-data-capsule-json.php
// return a JSON object with information about the logged in client and the business
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";

if(userRole() == 'c') $locked = locked('c-', $noForward=true, $exitIfLocked=false);
else $locked = locked('o-', $noForward=true, $exitIfLocked=false);
if($locked) {
	$error = 'Locked.';
	if(!userRole()) $error .= " Not logged in.";
	else if(userRole() != 'c') $error = " Not logged in.as client.";
	header("Content-type: application/json");
	echo json_encode(array('error'=>$error));
	exit;
}

$bizFileDirectory = $_SESSION["bizfiledirectory"];
if(!$bizFileDirectory && $_SESSION["bizptr"]) {  // e.g., during temporary payment session
	$bizFileDirectory = "bizfiles/biz_{$_SESSION["bizptr"]}/";
}
$headerBizLogo = getHeaderBizLogo($bizFileDirectory);
$headerBizLogo = $headerBizLogo ? globalURL($headerBizLogo) : null;


$business = array(
	'bizName'=>getPreference('bizName'),
	'bizName'=>getPreference('bizName'),
	'shortBizName'=>getPreference('shortBizName'),
	'logo'=>$headerBizLogo,
	'bizPhone'=>getPreference('bizPhone'),
	'bizEmail'=>getPreference('bizEmail'),
	'bizAddress'=>getPreference('bizAddressJSON') ? json_decode(getPreference('bizAddressJSON'), 'association') : null,
	'bizHomePage'=>getPreference('bizHomePage'),
	'facebook'=>getPreference('facebookAddress'),
	'linkedinaddress'=>getPreference('linkedinaddress'),
	//'linkedinaddress'=>getPreference('linkedinaddress'),
	'twitteraddress'=>getPreference('twitteraddress'),
	'instagraminaddress'=>getPreference('instagraminaddress'));
	
$clientRecord = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as fullname, CONCAT_WS(' ', fname2, lname2) as fullname2 FROM tblclient WHERE clientid = {$_SESSION['clientid']} LIMIT 1", 1);
foreach(explode(',', 'fullname,fname,lname,fullname2,fname2,lname2') as $fld)
	$client[$fld] = $clientRecord[$fld];
	
$payload['success'] =  true;
$payload = array_merge($payload, array('business'=>$business, 'client'=>$client));

//print_r($_SESSION);


header("Content-type: application/json");
echo json_encode($payload);
exit;
