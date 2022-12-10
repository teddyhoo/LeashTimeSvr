<? // twilio-sms-received.php

require "common/init_db_common.php";

// find parameters and log them

//insertTable('tbltextbag', array('referringtable'=>'twiliocallback', 'body'=>print_r($_REQUEST, 1)), 1);


/*ArrayArray
(
    [ToCountry] => US
    [ToState] => VA
    [SmsMessageSid] => SM6aaaf17dc2cee2597a47479e628ffae2
    [NumMedia] => 0
    [ToCity] => ARLINGTON
    [FromZip] => 20171
    [SmsSid] => SM6aaaf17dc2cee2597a47479e628ffae2
    [FromState] => VA
    [SmsStatus] => received
    [FromCity] => ARLINGTON
    [Body] => Yeah what
    [FromCountry] => US
    [To] => +17039976447
    [ToZip] => 22211
    [NumSegments] => 1
    [MessageSid] => SM6aaaf17dc2cee2597a47479e628ffae2
    [AccountSid] => AC270ac0651eb355f83a0eb83ca55a565c
    [From] => +17032030617
    [ApiVersion] => 2010-04-01
)

(in petcentral)
CREATE TABLE IF NOT EXISTS `tbllatestsms` (
  `phone` varchar(20) CHARACTER SET latin1 NOT NULL,
  `petbizptr` int(11) NOT NULL,
  `ownertable` varchar(30) CHARACTER SET latin1 NOT NULL,
  `ownerptr` int(11) NOT NULL,
  `datetime` datetime NOT NULL,
  PRIMARY KEY (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
*/

if($_REQUEST['test']) {
	    $_REQUEST[ToCountry] = 'US';
	    $_REQUEST[ToState] = 'VA';
	    $_REQUEST[SmsMessageSid] = 'SM6aaaf17dc2cee2597a47479e628ffae2';
	    $_REQUEST[NumMedia] = '0';
	    $_REQUEST[ToCity] = 'ARLINGTON';
	    $_REQUEST[FromZip] = '20171';
	    $_REQUEST[SmsSid] = 'SM6aaaf17dc2cee2597a47479e628ffae2';
	    $_REQUEST[FromState] = 'VA';
	    $_REQUEST[SmsStatus] = 'received';
	    $_REQUEST[FromCity] = 'ARLINGTON';
	    $_REQUEST[Body] = 'Yeah what';
	    $_REQUEST[FromCountry] = 'US';
	    $_REQUEST[To] = '+17039976447';
	    $_REQUEST[ToZip] = '22211';
	    $_REQUEST[NumSegments] = '1';
	    $_REQUEST[MessageSid] = 'SM6aaaf17dc2cee2597a47479e628ffae2';
	    $_REQUEST[AccountSid] = 'AC270ac0651eb355f83a0eb83ca55a565c';
	    $_REQUEST[From] = '+17032030617';
	    $_REQUEST[ApiVersion] = '2010-04-01';
}

$phone = $_REQUEST['From'];
if(!$phone) {
	// log error
	logError("twilio-sms-received.php: no phone found.");
	exit;
}
$originator = fetchFirstAssoc("SELECT * FROM tbllatestsms WHERE phone = '$phone' LIMIT 1");
if(!$originator) {
	// log error
	logError("twilio-sms-received.php: no petBizID found for phone [$phone].");
	exit;
}
$petBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$originator['petbizptr']} LIMIT 1", 1);

if(!$petBiz) {
	// log error
	logError("twilio-sms-received.php: no petBiz found for [{$originator['petbizptr']}].");
	exit;
}

reconnectPetBizDB($petBiz['db'], $petBiz['dbhost'], $petBiz['dbuser'], $petBiz['dbpass']);

// Create an SMS messsage and metadata!
require_once "comm-fns.php";
require_once "sms-fns.php";
setLocalTimeZone($petBiz['timeZone']);

saveInboundSMSMessage($_REQUEST, $originator);