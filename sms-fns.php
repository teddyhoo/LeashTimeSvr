<? // sms-fns.php

function getSMSGatewayObject($gatewayName) {
	if($gatewayName == 'Twilio')  {
		require_once "twilio-gateway-class.php";
		$gatewayObject = new TwilioGateway();
	}
	return $gatewayObject;
}

function smsPermittedByLeashTime($prefs) {
	// REMEMBER TO replace $_SESSION["preferences"]['enableSMS'] in 
	// preference-list.php 
	// provider-comms-list.php
	// homepage-owner.php
	// user-preference-list.php
	// with a call to this function
	return $prefs['enableSMS'] && !$prefs['leashTimeDisabledSMS'];// !leashTimeDisabledSMS will eventaually replace enableSMS
}

function smsEnabled($fromLeashTimeAccount=false) {
	require_once "preference-fns.php";
	if($_SESSION['preferences']) $prefs = $_SESSION['preferences'];
	else {
		$prefs = fetchPreferences();
	}
	if(!smsPermittedByLeashTime($prefs)) return false;
	if($fromLeashTimeAccount) {
		$settings = ensureInstallationSettings();
		return 
				smsTurnedOn($prefs) && // by customer
				$settings['twilioAccountID'] &&
				$settings['twilioGatewayAccountToken'] &&
				$settings['twilioPhoneNumber'] &&
				in_array('tblmessagemetadata', fetchCol0("SHOW TABLES", 1));
	}
	
	else return 
			smsTurnedOn($prefs) && // by customer
			$prefs['smsGateway'] &&
			$prefs['smsGatewayAccountId'] &&
			$prefs['smsGatewayAccountToken'] &&
			in_array('tblmessagemetadata', fetchCol0("SHOW TABLES", 1));
}

function smsTurnedOn($prefs) {
	// return true if SMS is turned on by the manager or should be turned on
	if(!$prefs['smsTurnedOn'] && $prefs['resumeSMSDate'] && strcmp($prefs['resumeSMSDate'], date('Y-m-d')) <= 0) {
		setPreference('resumeSMSDate', null);
		setPreference('smsTurnedOn', 1);
	}
	return $prefs['smsTurnedOn'];
}

function messageSubjectFromSMSBody($body) {
	$subject = trim(str_replace("\n", ' ', str_replace("\n\n", ' ', "$body")));
	$maxSubLength = 80;
	if(strlen($subject) > $maxSubLength)
		$subject = substr($subject, 0, $maxSubLength-3).'...';
	$subject = $subject ? $subject : 'empty message';
	return $subject;
}

function newInboundSmsMetaDataRecord($array, $gateway='Twilio') {
	if($gateway != 'Twilio') exit;
	return array(
		//'msgptr' => $msgptr,
		'externalid' => $array['SmsMessageSid'],
		'status' => 'received',
		'datecreated' => date('Y-m-d H:i:s'),
		//'dateupdated' => $object->date_updated,
		'datesent' => date('Y-m-d H:i:s'),
		'type' => 'sms',
		'gateway' => $gateway,
		'gatewayaccount' => $array['AccountSid'],
		'tophone' => $array['To'],
		'fromphone' => $array['From'],
		'fromcity' => $array['FromCity'],
		'fromstate' => $array['FromState'],
		'fromcountry' => $array['FromCountry'],
		'fromZip' => $array['FromZip'],
		'nummedia' => $array['NumMedia'],
		'numsegments' => $array['NumSegments'],
		'price' => estimateInboundTwilioMessagePrice($array['NumSegments']),
		'priceunit' => 'USD',
		'apiversion' => $array['ApiVersion'],
		'direction' => 'inbound'
	);
}	
	
	/*
https://support.twilio.com/hc/en-us/articles/223134347-What-do-the-SMS-statuses-mean-

status 	count(*)

delivered 	41
queued 	4
received 	18
sent 	49
undelivered 	340
*/

function estimateInboundTwilioMessagePrice($units) {
	return $units * .0075;
}
/*	Array
	(
	    [FromCity] => ARLINGTON
	    [FromState] => VA
	    [FromZip] => 20171
	    [FromCountry] => US
	    [ToCountry] => US
	    [ToState] => VA
	    [SmsMessageSid] => SM6aaaf17dc2cee2597a47479e628ffae2
	    [NumMedia] => 0
	    [ToCity] => ARLINGTON
	    [SmsSid] => SM6aaaf17dc2cee2597a47479e628ffae2
	    [SmsStatus] => received
	    [Body] => Yeah what
	    [To] => +17039976447
	    [ToZip] => 22211
	    [NumSegments] => 1
	    [MessageSid] => SM6aaaf17dc2cee2597a47479e628ffae2
	    [AccountSid] => AC270ac0651eb355f83a0eb83ca55a565c
	    [From] => +17032030617
	    [ApiVersion] => 2010-04-01
)
*/	
	
function newSmsMetaDataRecord($msgptr, $object, $gateway='Twilio', $clientrequestptr=null) {
//logLongError(json_encode($object));
	//echo "<hr><pre>".print_r($object)."</pre><hr>";
	if($gateway != 'Twilio') exit;
	return array(
		'msgptr' => $msgptr,
		'externalid' => $object->sid,
		'status' => $object->status,
		'datecreated' => date('Y-m-d H:i:s', strtotime($object->date_created)),
		'dateupdated' => $object->date_updated,
		'datesent' => $object->date_sent,
		'type' => 'sms',
		'gateway' => $gateway,
		'gatewayaccount' => $object->account_sid,
		'tophone' => $object->to,
		'fromphone' => $object->from,
		'nummedia' => $object->num_media,
		'numsegments' => $object->num_segments,
		'errorcode' => $object->error_code,
		'errormessage' => $object->error_message,
		'price' => ($object->price ? $object->price : '0.0'),
		'priceunit' => $object->price_unit,
		'apiversion' => $object->api_version,
		'direction' => $object->direction,
		'uri' => $object->uri,
		'subresourceuri' => $object->subresource_uris->media,
		'clientrequestptr' => $clientrequestptr
	);
}

function messageMetadataTableExists() {
	// make this return TRUE when table is fully incorporated into all LT databases
	$tables = fetchCol0("SHOW tables");
	return in_array('tblmessagemetadata', $tables);
}

function metaDataForMessage($msgptr) {
	if(messageMetadataTableExists())
		return fetchFirstAssoc("SELECT * FROM tblmessagemetadata WHERE msgptr = $msgptr", 1);
}

function priceBrackets() {
	$thresholds = explode("\n", 
		'100=$5
		350=$10
		600=$15
		850=$20
		1100=$25
		1350=$30
		1600=$35
		1850=$40
		2100=$45');
	foreach($thresholds as $line) {
		$pair = explode('=', trim($line));
		$prices[$pair[0]] = $pair[1];
		$upTos[$pair[0]] = $lastPrice;
		$lastPrice = $pair[1];
	}
	return $prices;
}

function smsCharge($numVisits) {
	$lastPrice = 0;
	foreach(priceBrackets() as $threshold => $price) {
		if($numVisits < $threshold) return $lastPrice;
		$lastPrice = $price;
	}
	return $price;
}

function approachingThreshold() {
	$margin = 10;
	$blockSize = 250;
	$prices = priceBrackets();
	foreach($prices as $threshold => $price) {
		$upTos[$threshold] = $lastPrice;
		$lastPrice = $price;
	}
	
	$freeThreshold = current(array_keys($prices));
	$blockPrice = $prices[$freeThreshold];
	$firstDayThisMonth = date('Y-m-01');
//$firstDayThisMonth = '2017-01-01';
	$count = countSMSMessagesSince($firstDayThisMonth);
	foreach($prices as $threshold => $price) {
		if($count <= $threshold) {
			if($threshold - $count < $margin) {
				// send this warning just once
				if($firstDayThisMonth == fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'thresholdWarning$threshold' LIMIT 1", 1))
					return;
				replaceTable('tblpreference', array('property'=>"thresholdWarning$threshold", 'value'=>$firstDayThisMonth), 1);
				//generate notice
				$notice['body'] = 
					"$count mobile messages have been sent or processed by LeashTime so far this month.<p>"
						."Once the number of messages exceeds $threshold, the Mobile Messaging Surcharge for this month will be $price.<p>"
						."To review or change your Mobile Messaging preferences, please go to ADMIN > Communication Preferences "
						."and click on the Mobile Notifications in the Staff Notifications box.<p>\n";
				$notice['body'] .= 
					"Each month, the first $freeThreshold messages are free.  After that the price is $blockPrice per block of $blockSize messages.";
						
				/*echo "<table>\n";
				foreach($upTos as $threshold => $price) {
					$price = $price ? $price : 'FREE';
					$notice['body'] .= "<tr><td>Up to $threshold messages</td><td>$price</td></tr>\n";
				}
				$notice['body'] .= "</table>";
				*/
				return $notice;
			}
		}
	}
}

function countSMSMessagesSince($daysOrDate, $toOrFrom=null, $outboundInboundOrBoth='both') {
	if(is_numeric($daysOrDate) && 
			round($daysOrDate) == $daysOrDate && 
			$daysOrDate > 0) 
		$date = date('Y-m-d 00:00:00', strtotime("-$daysOrDate days"));
	else $date = "$daysOrDate 00:00:00";
	if($outboundInboundOrBoth != 'both')
		$filter = array($outboundInboundOrBoth == 'inbound' ? "AND direction = 'inbound'" : "AND direction != 'inbound'");
	if($toOrFrom) $filter[] = "AND fromphone='$toOrFrom' OR tophone ='$toOrFrom'";
	if($filter) $filter = join(' ', $filter);
	$sql = "SELECT COUNT(*)
					 FROM tblmessagemetadata
					 WHERE datecreated > '$date' $filter";
	$num = fetchRow0Col0($sql, 1);
	//echo "Num: [$num]<p>$sql<hr>";
	return $num;
}

function enableSMS() {
	require_once "preference-fns.php";
	doQuery("CREATE TABLE IF NOT EXISTS `tblmessagemetadata` (
  `metadataid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `msgptr` int(10) unsigned NOT NULL,
  `status` varchar(45) DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `gateway` varchar(45) NOT NULL,
  `gatewayaccount` varchar(100) NOT NULL,
  `externalid` varchar(100) NOT NULL,
  `datecreated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `dateupdated` datetime DEFAULT NULL,
  `datesent` datetime DEFAULT NULL,
  `fromphone` varchar(100) NOT NULL,
  `tophone` varchar(100) NOT NULL,
  `nummedia` int(10) unsigned NOT NULL,
  `numsegments` int(10) unsigned NOT NULL,
  `errorcode` int(10) unsigned DEFAULT NULL,
  `errormessage` varchar(100) DEFAULT NULL,
  `direction` varchar(100) NOT NULL,
  `price` float(6,2) unsigned NOT NULL DEFAULT '0.00',
  `priceunit` varchar(10) DEFAULT 'usd',
  `apiversion` varchar(100) DEFAULT NULL,
  `fromcity` varchar(60) DEFAULT NULL,
  `fromstate` varchar(40) DEFAULT NULL,
  `fromzip` varchar(20) DEFAULT NULL,
  `fromcountry` varchar(10) DEFAULT NULL,
  `uri` varchar(255) DEFAULT NULL,
  `subresourceuri` text,
  `clientrequestptr` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`metadataid`),
  UNIQUE KEY `msgptr` (`msgptr`),
  KEY `externalidindex` (`externalid`),
  KEY `statusindex` (`status`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
");

	doQuery("CREATE TABLE IF NOT EXISTS `tblmessagemetadataresource` (
  `messageresourceid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `metadataptr` int(10) unsigned NOT NULL,
  `gatewayaccount` varchar(100) NOT NULL,
  `externalid` varchar(100) NOT NULL,
  `externalparentptr` varchar(100) NOT NULL,
  `datecreated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `dateupdated` datetime DEFAULT NULL,
  `contenttype` varchar(100) NOT NULL,
  `uri` varchar(255) DEFAULT NULL,
  `localurl` varchar(255) DEFAULT NULL,
  `localpath` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`messageresourceid`),
  UNIQUE KEY (`externalid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;");
	setPreference('enableSMS', 1);
	setPreference('smsGateway', 'Twilio');
	setPreference('enableOverdueVisitManagerSMS', 1);
	setPreference('enableOverdueVisitSitterSMS', 1);
	setPreference('enableSitterMemoSMS', 1);

}

function markBadNumbersIfNecessary($status, $metadataIDORmsg, $toPhone) {
	// given an SMS message, if it is failed or undelivered, mark the tophone invalid in the client/provider/manager records
	if(!in_array($status, array('failed', 'undelivered')))  return;
	else {
		require_once "field-utils.php";
		$msg = is_array($metadataIDORmsg) ? $metadataIDORmsg :
			fetchFirstAssoc(
				"SELECT m.* 
					FROM tblmessagemetadata
					LEFT JOIN tblmessage m ON msgptr = msgid
					WHERE metadataid = '$metadataID'");
		if(!$msg) {	
			// log error
			if($_SERVER["REQUEST_URI"] == "/twilio-status-callback.php") $logPrefix = "TWILIO CALLBACK: ";
			logError("{$logPrefix}no msg found to text-disable [$toPhone].");
			return;
		}
		$strippedPhone = numeralsOnly($toPhone);
		$strippedPhoneNoCountryCode = substr($strippedPhone, 1);
		$allStrippedVersions =  array($strippedPhone, $strippedPhoneNoCountryCode);
		$table = $msg['correstable'];
		$correspid = $msg['correspid'];
//logError("STATUS: [$status] (metadataID: $metadataID msg: {$msg['msgid']}) [phone $toPhone] $table/$correspid");
//STATUS: [undelivered] (metadataID: 468 msg: 10984) [phone +17032421964] tbluser/526
		if($table == 'tbluser') {
			$primary = fetchRow0Col0(
					"SELECT value 
						FROM tbluserpref 
						WHERE userptr = {$msg['correspid']} AND property = 'managerTextPhone' 
						LIMIT 1", 1);
//logError("STRIPPED[$strippedPhone]=PRIMARY[".numeralsOnly($primary)."] STATUS: [$status] (metadataID: $metadataID msg: {$msg['msgid']}) [phone $toPhone] $table/$correspid");
			if(in_array(numeralsOnly($primary), $allStrippedVersions)) {
				updateTable('tbluserpref', 
										array('value' => "INVALID$primary"), 
										"userptr = $correspid AND property = 'managerTextPhone'", 1);
				logChange($correspid, $table, 'm', $note="INVALIDATED managerTextPhone $primary");
			}
		}
		else {
			$idfield = substr($table, 3).'id';
			$phoneFields = "homephone, workphone, cellphone";
			if($table == 'tblclient') $phoneFields .= ", cellphone2";
			$corresp = fetchFirstAssoc("SELECT $phoneFields FROM $table WHERE $idfield = $correspid", 1);
			foreach(explode(',', 'homephone,workphone,cellphone,cellphone2') as $fld) {
				if(in_array(numeralsOnly($corresp[$fld]), $allStrippedVersions)) {
					if(strpos($corresp[$fld], '*T') === 0)
						$mods[$fld] = '*'.substr($corresp[$fld], 2); // remove the "T" in second spot
					else if(strpos($corresp[$fld], 'T') === 0)
						$mods[$fld] = substr($corresp[$fld], 1); // remove the "T" in first spot
				}
			}
			if($mods) {
				updateTable($table, $mods, "$idfield = $correspid", 1);
				$fields = join(', ', array_keys($mods));
				logChange($correspid, $table, 'm', $note="INVALIDATED $fields $primary");
			}
		}
	}
}

	