<? // twilio-status-callback.php

require "common/init_db_common.php";

// find parameters and log them

//insertTable('tbltextbag', array('referringtable'=>'twiliocallback', 'body'=>print_r($_REQUEST, 1)), 1);

/*Array
(
    [bizptr] => 3
    [SmsSid] => SMc25ecc9dcd9a41778381da8a70ca18a5
    [SmsStatus] => delivered
    [MessageStatus] => delivered
    [To] => +17032030617
    [MessageSid] => SMc25ecc9dcd9a41778381da8a70ca18a5
    [AccountSid] => AC270ac0651eb355f83a0eb83ca55a565c
    [From] => +17039976447
    [ApiVersion] => 2010-04-01
)

*/

$smsptr = $_REQUEST['SmsSid'];
if(!$smsptr) {
	// log error
	logError("twilio-status-callback.php: no SmsSid found.");
	exit;
}
$petBizID = $_REQUEST['bizptr'];
if(!$petBizID) {
	// log error
	logError("twilio-status-callback.php: no petBizID found for SmsSid [$smsptr].");
	exit;
}
$petBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $petBizID LIMIT 1", 1);

if(!$petBiz) {
	// log error
	logError("twilio-status-callback.php: no petBiz found for [$petBizID].");
	exit;
}

reconnectPetBizDB($petBiz['db'], $petBiz['dbhost'], $petBiz['dbuser'], $petBiz['dbpass']);

setLocalTimeZone($petBiz['timeZone']);

$updates = array('status'=>$_REQUEST['SmsStatus'], 'dateupdated' => date('Y-m-d H:i:s'));

updateTable('tblmessagemetadata', $updates, "externalid = '$smsptr'", 1);

if($metadataID = fetchRow0Col0("SELECT metadataid FROM tblmessagemetadata WHERE externalid = '$smsptr' LIMIT 1", 1)) {
	require_once "sms-fns.php";
	markBadNumbersIfNecessary($_REQUEST['SmsStatus'], $metadataID, $_REQUEST['To']);
}
else ; // guess I should log an error
