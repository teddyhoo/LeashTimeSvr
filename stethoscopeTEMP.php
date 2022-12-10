<?php

if($_REQUEST['say']) { // UNUSED. must live elsewhere until this server is public
			// currently: http://admw.info/twiliosays/twiliosays.php?say=
	header('Content-Type: text/xml');
	echo str_replace('XXX', $_REQUEST['say'],
		'<?xml version="1.0" encoding="UTF-8"?>'
		.'<Response><Say voice="alice">Leash Times server is not responding.  XXX</Say></Response>');
//<Play>http://demo.twilio.com/docs/classic.mp3</Play>
	exit;
}

function cronRun() {
	$t0 = microtime(1);
//echo date('Y-m-d H:i', $t0);
	$pollingMinutes = fetchPref('pollingminutes');
	$lastRun = fetchPref('lastrun');
//echo "\n".date('Y-m-d H:i', $t0)." pollingMinutes: $pollingMinutes lastrun: $lastRun (".strtotime("+ $pollingMinutes minutes", strtotime($lastRun)).") < (".time().")\n";
	if($lastRun && strtotime("+ $pollingMinutes MINUTES", strtotime($lastRun)) > time()) return;
//echo "\n".date('Y-m-d H:i', $t0)."\n";
	if($snoozeuntil = fetchPref('snoozeuntil')) {
	  if(strtotime($snoozeuntil) <= time())
	    deleteTable('tblpreference', "property='snoozeuntil'", 1);
	  else return;
	}
	replaceTable('tblpreference', array('property'=>'lastrun', 'value'=>date('Y-m-d H:i:s')), 1);
	$result = testHeartbeat('https://leashtime.com/heartbeat.php', fetchPref('heartbeattimeout'));
	if(is_array($result)) {
		voiceAlert($result[0]); // twiliosays script prefixes "Leash Times server is not responding."
		smsAlert("LeashTime is not responding. ".$result[0]);
		//logChange(-999, 'heartbeat', 'f', $result[0]);
		// set snoozeuntil at this point, to avoid annoying recipients every 10 minutes
		if($snoozeafter = fetchPref('snoozeafteralertminutes'))
		  replaceTable('tblpreference', 
				array('property'=>'snoozeuntil', 
					'value'=>date('Y-m-d H:i:s', strtotime("+ $snoozeafter MINUTES"))
				), 1);
	}
	else if(strpos($result, "TEST|") === 0) {
		$testSpec = explode("|", $result); // array("TEST", voice|text, $numbers, $message)
		replaceTable('tblpreference', array('property'=>'debug', 'value'=>print_r($result, 1)), 1);
		$message = $testSpec[3] ? $testSpec[3] : "this is a test of the leash time heartbeat service";
		$numbers = $testSpec[2];
		$contactType = $testSpec[1];
		if($numbers) {
			if($contactType == 'voice') voiceAlert($message, $numbers));
			else if($contactType == 'text') sendSMS($message, $numbers);
		}
	}
	else {
	  // $result is the reported number of seconds the LT server took to do its work
	  $stethoscopetime = microtime(1) - $t0;  // runtime of this script up to now
	  // if either $result or $stethoscopetime are too great ... do something
	}
}

function testHeartbeat($url, $timeoutseconds=10) {
	// return an array with error message or page content
	if(is_array($heartbeat = getPageOrCode($url))) {
		// heartbeat not found
		$noheartbeat = "error code ".($heartbeat[0] ? $heartbeat[0] : "zero");
		// test tester connectivity
		$tests = array('https://www.google.com/?gws_rd=ssl', 'http://java.com/en/');
		$connectionURL = rand(0, count($tests)-1);
		$connectionURL = $tests[$connectionURL];
		$connectionTest = getPageOrCode($connectionURL);
		if(is_array($connectionTest)) return array("connectivity problem"); // no-op; connectivity is at fault
		else return array($noheartbeat);
	}
	else return $heartbeat;
}
	
function getPageOrCode($url, $timeoutseconds=10) {
	$handle = curl_init($url);
	curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($handle,  CURLOPT_CONNECTTIMEOUT, $timeoutseconds);
	/* Get the HTML or whatever is linked in $url. */
	$response = curl_exec($handle);
	/* Check for 404 (file not found). */
	$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
	curl_close($handle);
	if(strpos("$httpCode", '2') === 0) { // 2xx is OK
		return $response;
	}
	else return array($httpCode);
}

function fetchPref($property) {
	return fetchRow0Col0("SELECT value FROM tblpreference WHERE property = '$property'");
}

function twilioAccount($key=null) {
	static $account;
	if(!$account) {
	  $account = array(
		'senderNumber'=>'(703) 997-6447', // trial (703) 997-0768
		'accountId' => 'AC270ac0651eb355f83a0eb83ca55a565c', //'AC270ac0651eb355f83a0eb83ca55a565c'; // trial
		'accountToken' => 'fb1c401d84386b1c19dbbede70a5b665', //'fb1c401d84386b1c19dbbede70a5b665'); // trial
		// Twilio REST API version
		'version' => "2010-04-01"
		);
	 }
	if($key)  return $account[$key];
	else return $account;
}


function voiceAlert($message, $voicenumbers=null) {
	$service = voiceService();
	$senderNumber = twilioAccount('senderNumber');
	$voicenumbers = $voicenumbers ? $voicenumbers : fetchPref('voicenumbers');
	$voicenumbers = explode(',', "$voicenumbers");
	$xmlURL = fetchPref('twiliosaysurl');
	$xmlURL .= urlencode($message);
	//globalURL('stethoscope.php?say='
	if($voicenumbers) 
	  foreach($voicenumbers as $voicenumber) 
	    if($voicenumber = trim($voicenumber))
	      $service->account->calls->create(
		$senderNumber, $voicenumber, $xmlURL, 
		array(/*"SendDigits" => "1234#",*/ "Method" => "GET"));
}

function voiceService() {
	require_once "init-db-steth.php";
	require "twilio-gateway-class.php";
	return new Services_Twilio(
			twilioAccount('accountId'), 
			twilioAccount('accountToken'), 
			twilioAccount('version'));
}

function getSMSGatewayObject($gatewayName='Twilio') {
	if($gatewayName == 'Twilio')  {
		require_once "twilio-gateway-class.php";
		$gateway = new TwilioGateway(
					twilioAccount('accountId'), 
					twilioAccount('accountToken'), 
					twilioAccount('senderNumber'));
	}
	return $gateway;
}

function smsAlert($payload) {
	$smsnumbers = fetchPref('smsnumbers');
	$smsnumbers = explode(',', "$smsnumbers");
	sendSMS($payload, $smsnumbers);
}

function sendSMS($payload, $recipients) {
	//	$lgPhoneNumber = '571-295-1387'; // trial LG Phone
	//	$pantechPhoneNumber = '703-203-0617'; // trial LG Phone 
	$gateway = getSMSGatewayObject($gatewayName='Twilio');
	try {
		foreach($recipients as $number) 
		  if($number = trim($number))
			$message = $gateway->sendSMS($number, $payload);
	}
	catch (Exception $e) {
		echo "<a href='{$e->getInfo()}'>{$e->getInfo()}</a>";
	} 
	//echo '<pre>'.print_r($message,1).'</pre>'; exit; 
}


//$recipients = array('7039819948'); //   // paul  5857282127 // matt
//sendSMS('hey paul, did you get this text?', $recipients);