<? // twilio-gateway-class.php
require "twilio-twilio-php-a876df2/Services/Twilio.php";
class TwilioGateway
{
    // property declaration
    private $errorCodes;
    private $accountId;
    private $accountToken;
    private $senderNumber;
    
    function showMe() {echo "ac: $this->accountId tok: $this->accountToken";}

 function __construct($accountId, $accountToken, $senderNumber) {
	 require_once "encryption.php";
	 $this->accountId = $accountId;
	 $this->accountToken = lt_decrypt($accountToken);
	 $this->senderNumber = $senderNumber;
   $errorCodesRaw = array(
		array('30001', 'Queue overflow', 'You tried to send too many messages too quickly and your message queue overflowed. Try sending your message again after waiting some time.'),
		array('30002', 'Account suspended', 'Your account was suspended between the time of message send and delivery. Please contact Twilio.'),
		array('30003', 'Unreachable destination handset', 'The destination handset you are trying to reach is switched off or otherwise unavailable.'),
		array('30004', 'Message blocked', 'The destination number you are trying to reach is blocked from receiving this message (e.g. due to blacklisting).'),
		array('30005', 'Unknown destination handset', 'The destination number you are trying to reach is unknown and may no longer exist.'),
		array('30006', 'Landline or unreachable carrier', 'The destination number is unable to receive this message. Potential reasons could include trying to reach a landline or, in the case of short codes, an unreachable carrier.'),
		array('30007', 'Carrier violation', 'Your message content was flagged as going against carrier guidelines.'),
		array('30008', 'Unknown error', 'The error does not fit into any of the above categories.'));
	 foreach($errorCodesRaw as $code) 
		 $this->errorCodes[$code[0]] = array('code'=>$code[0], 'label'=>$code[1], 'description'=>$code[2]);
 }
 
 function setAccountToken($token) {
	 $this->accountToken = $token;
 }
 
 
 // A URL that Twilio will POST to each time your message status changes to one of the following: 
 // queued, failed, sent, delivered, or undelivered. 
 // Twilio will POST the MessageSid along with the other standard request parameters as well as 
 // MessageStatus and ErrorCode. 
 // If this parameter passed in addition to a MessagingServiceSid, 
 // Twilio will override the Status Callback URL of the Messaging Service. 
 // Non-relative URLs must contain a valid hostname (underscores are not allowed).
 
 function sendSMS($toNumber, $payload, $fromNumber=null, $media=null, $statusCallbackURL=null) {
	 $client = new Services_Twilio($this->accountId, $this->accountToken);
	 if(!$fromNumber) $fromNumber = $this->senderNumber;
	 // Services\Twilio\Rest\Messages.php
	 // function sendMessage($from, $to, $body = null, $mediaUrls = null, $params = array())
	 // $params may include StatusCallback (a URL that can accept SmsSid and SmsStatus)
	 // $mediaUrls is an array of URLs
	 if($media && !is_array($media)) $media = array($media);
	 try {
		 $message = $client->account->messages->sendMessage(
				$fromNumber, // From a valid Twilio number e.g., '9991231234'
				$toNumber, // Text this number e.g., '8881231234'
				$this->gsmify($payload),
				$media,
				($params = $statusCallbackURL ? array('StatusCallback'=>$statusCallbackURL) : null));
		 return $message;
	 }
	 catch (Exception $e) {
		// log error here, maybe
		return $e->getInfo();
	 }
 }
 
 function errorIndicatesBadNumber($error) {
	 //https://www.twilio.com/docs/errors/21211
	 $fatals = array(
		 'Invalid phone number' => "errors/21211",
		 'phone number cannot be reached' => "errors/21214",
		 'Geo Permission configuration is not permitting call' => "errors/21215",
		 'Call blocked by Twilio blacklist' => "errors/21216",
		 'phone number not verified' => "errors/21219");
	 foreach($fatals as $explanation => $pattern)
		 if(strpos($error, $pattern)) 
		 		return $explanation;
 }
 
 function explainError($error) {
	 //https://www.twilio.com/docs/errors/21211
	 if(strpos($error, "errors/21211")) return "Invalid phone number.";
	 return $error;
 }
 
 public function stringIsGSM($string) {
	 for($i = 0; $i < strlen($string); $i++) {
     if(!in_array(ord($string[$i]), $this->gsmKeyCodes())) return false;}
	 return true;	 
 }
 
 public function gsmify($string) {
	 static $replacements;
	 if(!$replacements) $replacements =
	 		array(9=>32);
	 $out = '';
	 for($i = 0; $i < strlen($string); $i++)
		 $out .= ($replacements[ord($string[$i])] ? chr($replacements[ord($string[$i])]) : $string[$i]);
	 return $out;	 
 }
 
 public function nonGSMCharsInString($string) {
	 $arr = array();
	 for($i = 0; $i < strlen($string); $i++) {
     if(!in_array(ord($string[$i]), $this->gsmKeyCodes())) $arr[] = "(".ord($string[$i]).":{$string[$i]})";}
	 return array_unique($arr);	 
 }
 
 protected function gsmKeyCodes() {
	 static $keycodes;
	 if(!$keycodes) 
	 	$keycodes = array(   
        0x0040, 0x0394, 0x0020, 0x0030, 0x00a1, 0x0050, 0x00bf, 0x0070,
        0x00a3, 0x005f, 0x0021, 0x0031, 0x0041, 0x0051, 0x0061, 0x0071,
        0x0024, 0x03a6, 0x0022, 0x0032, 0x0042, 0x0052, 0x0062, 0x0072,
        0x00a5, 0x0393, 0x0023, 0x0033, 0x0043, 0x0053, 0x0063, 0x0073,
        0x00e8, 0x039b, 0x00a4, 0x0034, 0x0035, 0x0044, 0x0054, 0x0064, 0x0074,
        0x00e9, 0x03a9, 0x0025, 0x0045, 0x0045, 0x0055, 0x0065, 0x0075,
        0x00f9, 0x03a0, 0x0026, 0x0036, 0x0046, 0x0056, 0x0066, 0x0076,
        0x00ec, 0x03a8, 0x0027, 0x0037, 0x0047, 0x0057, 0x0067, 0x0077, 
        0x00f2, 0x03a3, 0x0028, 0x0038, 0x0048, 0x0058, 0x0068, 0x0078,
        0x00c7, 0x0398, 0x0029, 0x0039, 0x0049, 0x0059, 0x0069, 0x0079,
        0x000a, 0x039e, 0x002a, 0x003a, 0x004a, 0x005a, 0x006a, 0x007a,
        0x00d8, 0x001b, 0x002b, 0x003b, 0x004b, 0x00c4, 0x006b, 0x00e4,
        0x00f8, 0x00c6, 0x002c, 0x003c, 0x004c, 0x00d6, 0x006c, 0x00f6,
        0x000d, 0x00e6, 0x002d, 0x003d, 0x004d, 0x00d1, 0x006d, 0x00f1,
        0x00c5, 0x00df, 0x002e, 0x003e, 0x004e, 0x00dc, 0x006e, 0x00fc,
        0x00e5, 0x00c9, 0x002f, 0x003f, 0x004f, 0x00a7, 0x006f, 0x00e0 );
	 return $keycodes;
 }
 
	function getSMSById($id) {
		// Use the REST API Client to make requests to the Twilio REST API
		//use Twilio\Rest\Client; // must be done at start of page
		require_once "twilio-php-master/Twilio/autoload.php";
		$client = new Client($this->accountId, $this->accountToken);
		//$client = new Services_Twilio($this->accountId, $this->accountToken);
		try{$sms = $client->messages("SM800f449d0399ed014aae2bcc0cc2f2ec")->fetch();}
		catch(Exception $e) {}
    return $sms;
 }

}

