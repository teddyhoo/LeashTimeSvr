<? //reminder-fns.php

/*
tblreminder
CREATE TABLE IF NOT EXISTS `tblreminder` (
  `reminderid` int(11) NOT NULL auto_increment,
  `userid` int(1) NOT NULL COMMENT 'null if for all managers',
  `remindercode` int(11) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `sendon` varchar(16) NOT NULL COMMENT 'int,dow,date,datetime',
  `clientptr` int(11) NOT NULL,
  `providerptr` int(11) NOT NULL,
  `edited` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `lastsent` DATETIME NULL;
  PRIMARY KEY  (`reminderid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

clientptr (NULL) relevant client
providerptr (NULL) relevant provider
message (embedded #SITTERLOOP#, #ENDSITTERLOOP#, #CLIENTLOOP#, #ENDCLIENTLOOP#, #SITTER#, #CLIENT#)

CREATE TABLE IF NOT EXISTS `tblremindertype` (
  `remindertypeid` int(11) NOT NULL auto_increment,
  `label` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sendon` varchar(16) NOT NULL,
  `userid` int(11) NOT NULL COMMENT 'null if for all managers',
  `restriction` varchar(10) default NULL COMMENT 'client, sitter, or null',
  `standard` int(11) NOT NULL,
  PRIMARY KEY  (`remindertypeid`),
  UNIQUE KEY `label` (`label`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

cron job
find all reminders
for each tblreminder row
	if(today = senddate or <<today is adjusted dayofmonth>>)
		if(remindercatptr)
			lump reminder into category in process list
		else add reminder to process list
		if(senddate) drop entry from tblreminder

for each process list item
	if(reminder[reminderid]) // single
		queue up email with subject/note
	else
		get category from first reminder
		based on category, create a message
		queue up email with subject/message

*/

function sendReminders($onceADay=0) {
	if(!in_array('tblreminder', fetchCol0("SHOW TABLES"))) return;
	$remindersLastSent = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'remindersLastSent'");
	if($onceADay && $remindersLastSent && ($today = date('Y-m-d')) == date('Y-m-d', strtotime($remindersLastSent))) return;
	$hardcodedSendTime = '04:30:00';
	if(strcmp(date('H:i:s'), $hardcodedSendTime) <= 0) return;
	//require_once "request-fns.php";
	require_once "gui-fns.php";
	global $reminderIdsToBeSent;
	
	$managers = getReminderManagers();

	$datetime = date('Y-m-d H:i:s');
	$date = date('Y-m-d');
	$dow = date('l');
	$dom = date('j');
	$monthDay = date('m_d');
	$reminderTypes = 
		fetchAssociationsKeyedBy("SELECT * FROM tblremindertype", 'remindertypeid');
	//if($onceADay)
	$reminders = fetchAssociations(
			"SELECT * 
				FROM tblreminder 
				WHERE (TO_DAYS(sendon) IS NOT NULL AND TO_DAYS(sendon) <= TO_DAYS('$date'))
					OR sendon = '$dow'
					OR sendon = '$dom'
					OR sendon = 'ann$monthDay'");

	foreach($reminders as $i => $reminder) {



		$lastSent = $reminder['lastsent'];
		if($lastSent && date('Y-m-d', strtotime($lastSent)) == date('Y-m-d')) continue;
		/***TED INSERT****/
		echo "REMINDER: $reminder ON $lastSent";
		/*****************/
		//else updateTable('tblreminder', array('lastsent'=>date('Y-m-d H:i:s')), "reminderid = {$reminder['reminderid']}", 1);
		if($reminder['remindercode']) $batches[$reminder['remindercode']][] = $reminder;
		else { // HANDLE ONE-OFF REMINDERS
 			$subject = $reminder['subject'];
 			$message = $reminder['message'];
 			$extraFields = array();
 			if(!$reminder['userid']) {  // public reminder
				$request = array(
					'requesttype'=>'Reminder',
					'note'=>$reminder['message'],
					'street1'=>$reminder['subject'],
					'subject'=>$reminder['subject']);
				$scheduledFor = $reminder['sendon'];
				if(strlen($scheduledFor) < 10 && is_numeric($scheduledFor)) $scheduledFor = "Day of month $scheduledFor";
				$extraFields[] = "<extra key=\"x-oneline-Scheduled for\"><![CDATA[$scheduledFor]]></extra>";
				$extraFields[] = "<hidden key=\"sendon\">{$reminder['sendon']}</hidden>";
				$extraFields[] = "<hidden key=\"clientptr\">{$reminder['clientptr']}</hidden>";
				$extraFields[] = "<hidden key=\"providerptr\">{$reminder['providerptr']}</hidden>";
				$extraFields[] = "<hidden key=\"remindercode\">{$reminder['remindercode']}</hidden>";
				if($reminder['clientptr']) {
					$request['clientptr'] = $reminder['clientptr'];
					$clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$reminder['clientptr']} LIMIT 1");
					$extraFields[] = "<extra key=\"x-oneline-Client\"><![CDATA[$clientname]]></extra>";
				}
				if($reminder['providerptr']) {
					$providername = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$reminder['providerptr']} LIMIT 1");
					$extraFields[] = "<extra key=\"x-oneline-Sitter\"><![CDATA[$providername]]></extra>";
				}
				$extraFields[] = "<extra key=\"x-oneline-Reminder edited\"><![CDATA[{$reminder['edited']}]]></extra>";
				if($extraFields) 
					$request['extrafields'] = "<extrafields>".join('', $extraFields)."</extrafields>";
				//saveNewClientRequest($request, $notify=true);
				//if(isOneTimeReminder($reminder)) // request has been saved. reminder is no longer needed
					//deleteTable('tblreminder', "reminderid={$reminder['reminderid']}",1);
			}
			else { // private reminder
				//$remHeader[] = "<b>Reminder:</b> {$reminder['subject']}";
				$remHeader = array("<b>Reminder:</b> {$reminder['subject']}");
				if($reminder['clientptr']
					 && $client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = {$reminder['clientptr']}")) {
					$remHeader[] = "<b>Client:</b> {$client['fname']} {$client['lname']}";
					if($client['email']) $remHeader[] = "<b>Email:</b> {$client['email']}";
					require_once "field-utils-2.php";
					if($phone = primaryPhoneNumber($client)) {
						$phonefield = primaryPhoneField($client);
						$phonefield = $phonefield ? "({$phonefield[0]}) " : '';
						$remHeader[] = "<b>Phone:</b> $phonefield$phone";
					}
					$reminder['message'] = join('<br>', $remHeader)."<p>{$reminder['message']}";
				}

				
				$msgid = enqueueEmailNotification($managers[$reminder['userid']], $reminder['subject'], $reminder['message'], $cc=null, $mgrname=null, $html=true, $originator=null);
				echo "REMINDER MSG ID: $msgid";
				//if(!is_array($msgid) && isOneTimeReminder($reminder)) // reminder message is in the mail queue. reminder is no longer needed
				//	deleteTable('tblreminder', "reminderid={$reminder['reminderid']}",1);
				//else logError("reminder failure: ".($msgid ? $msgid[0] : 'unknown'));
			}
		} // END - HANDLE ONE-OFF REMINDERS
	}
	foreach((array)$batches as $remindercode => $reminders) { // HANDLE GROUP REMINDERS
		$reminderType = fetchFirstAssoc("SELECT * FROM tblremindertype WHERE remindertypeid = $remindercode LIMIT 1");
		$persons = array();
		$clientptrs = array();
		$providerptrs = array();
		foreach($reminders as $reminder) {
			if($reminder['clientptr']) $persons[] = "<client>{$reminder['clientptr']}</client>";
			if($reminder['providerptr']) $persons[] = "<provider>{$reminder['providerptr']}</provider>";
		}
		if($persons) $persons = "<!-- <persons>".join('', $persons)."</persons> -->";
		$message = $reminderType['message'];
		if(($start = strpos($message, '#NAMELOOP#')) !== FALSE 
					&& ($end = strpos($message, '#ENDNAMELOOP#')) !== FALSE) {
			$part1 = substr($message, 0, $start);
			$part2 = substr($message, $end+strlen('#ENDNAMELOOP#'));
			$start = $start+strlen('#NAMELOOP#');
			$stamp = substr($message, $start, $end-$start);
			$listText = '';
			$reminderCluster = array();
			foreach($reminders as $reminder) {
				if($reminder['clientptr']) {
					$personType = 'client';
					$personId = $reminder['clientptr'];
					$fromWhere = "tblclient WHERE clientid = {$reminder['clientptr']}";
					$clientptrs[] = $reminder['clientptr'];
				}
				else {
					$personType = 'provider';
					$personId = $reminder['providerptr'];
					$fromWhere = "tblprovider WHERE providerid = {$reminder['providerptr']}";
					
					$providerptrs[] = $reminder['providerptr'];
				}
				$name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM $fromWhere LIMIT 1");
				$allNames[$personType][$personId] = $name;
				$listText .= str_replace('#NAME#', $name, $stamp);
				$reminderCluster[] = $reminder['reminderid'];
			}
			$message = "$part1$listText$part2\n$persons";
			echo "REMINDER MESSAGE: $message";
		}
		else $message .= "\n$persons";
		if(isOneTimeReminder($reminder)) deleteTable('tblreminder', "reminderid={$reminder['reminderid']}",1);
		
		if(!$reminderType['userid']) {
			$request = array(
				'clientptr'=>(count($clientptrs) == 1 ? $clientptrs[0] : '0'),
				'requesttype'=>'Reminder',
				'note'=>$message,
				'street1'=>$reminderType['subject'],
				'subject'=>$reminderType['subject']);
			$extraFields[] = "<extra key=\"x-oneline-Group\"><![CDATA[{$reminderType['label']}]]></extra>";
			$scheduledFor = $reminderType['sendon'];
			if(strlen($scheduledFor) < 10 && is_numeric($scheduledFor)) $scheduledFor = "Day of month $scheduledFor";
			$extraFields[] = "<extra key=\"x-oneline-Scheduled for\"><![CDATA[$scheduledFor]]></extra>";
			if(count($clientptrs) == 1) 
				$extraFields[] = 
					"<extra key=\"x-oneline-Client\"><![CDATA[{$allNames['client'][$clientptrs[0]]}]]></extra>";
			if(count($providerptrs) == 1)
				$extraFields[] = 
					"<extra key=\"x-oneline-Sitter\"><![CDATA[{$allNames['provider'][$providerptrs[0]]}]]></extra>";
			if($extraFields) $request['extrafields'] = "<extrafields>".join('', $extraFields)."</extrafields>";

			//saveNewClientRequest($request, $notify=true);
		}
		else {
			$msgid = enqueueEmailNotification($managers[$reminderType['userid']], $reminderType['subject'], $message, $cc=null, $mgrname=null, $html=true, $originator=null);
			if(!is_array($msgid)) 
				foreach($reminderCluster as $reminderid) 
					$reminderIdsToBeSent[$msgid] = join(',', $reminderCluster);
			else logError("reminder failure: ".($msgid ? $msgid[0] : 'unknown'));
		}
	} // END IF IT IS A GROUP REMINDER
	//replaceTable('tblpreference', array('property'=>'remindersLastSent', 'value'=>date('Y-m-d H:i:s')), 1);
	global $remindedDB, $db;
	$remindedDB = $db;
}

function isOneTimeReminder($reminder) {
	// consider if(strtotime($reminder['sendon'])) instead  // (int)$reminder['sendon'] > 31
	// if(strtotime($reminder['sendon'])) return true; // strtotime('Friday') returns non-zero
	if(strpos($reminder['sendon'], '-')) return true;
}

function reminderCleanup() {
	global $remindedDB, $db, $reminderIdsToBeSent;
	if($remindedDB != $db) return;
	if(!in_array('tblreminder', fetchCol0("SHOW TABLES"))) return;
	if(count($reminderIdsToBeSent) == 0) return;
	$unsentMessages = fetchCol0(
			"SELECT emailid FROM tblqueuedemail 
			  WHERE emailid IN (".join(',', array_keys($reminderIdsToBeSent)).")");
	foreach($reminderIdsToBeSent as $msgid => $reminderids)
		if(!in_array($msgid, $unsentMessages)) 
			foreach(explode(',', $reminderids) as $reminderid) $sent[] = $reminderid;
	if($sent) {
		$reminders = fetchAssociationsKeyedBy(
					"SELECT * 
						FROM tblreminder 
						WHERE reminderid IN(".join(',', $sent).")", 'reminderid');
		$sent = array();
		foreach($reminders as $id => $reminder)
			if(isOneTimeReminder($reminder)) $sent[] = $id;
		deleteTable('tblreminder', "reminderid IN (".join(',', $sent).")");
	}
}

function getReminderManagers() {
	global $dbhost, $db, $dbuser, $dbpass, $biz;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	if(!$biz) $biz = fetchFirstAssoc("SELECT bizid FROM tblpetbiz WHERE db = '$db1' LIMIT 1");
	$bizptr = $biz['bizid'];
	$managers = fetchAssociationsKeyedBy(
			"SELECT userid, email, fname, lname, ltstaffuserid
				FROM tbluser 
				WHERE bizptr = $bizptr AND (rights LIKE 'd-%' OR rights LIKE 'o-%')", 'userid');
				
	// Get "real" email for staff users
	foreach($managers as $manager) 
		if($manager['ltstaffuserid']) $staff[$manager['userid']] = $manager['ltstaffuserid'];
	if($staff) {
		$realEmails = fetchKeyValuePairs("SELECT userid, email FROM tbluser WHERE userid IN (".join(',', $staff).")");
	//print_r($realEmails);		
		foreach($staff as $userid => $staffid) 
			$managers[$userid]['email'] = $realEmails[$staffid];
	}
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, $force=1);
	return $managers;
}	
 /*
			$reminderType = $reminderTypes[$reminder['remindercode']];
 			$subject = $reminderType['subject'];
 			$message = $reminderType['message'];
 			// now, merge in 
*/

function getReminders($person) {
	$client = $person['clientptr'];
	if(!$client) $provider = $person['provider'];

	if($client) {
		$filter = "AND clientptr = $client";
		$person = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = $client LIMIT 1");
	} 
	else if($provider) {
		$filter = "AND providerptr = $provider";
		$person = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE providerid = $provider LIMIT 1");
	}

	$privateTest = staffOnlyTEST() ? "1=1" : "userid = {$_SESSION['auth_user_id']}";
	return fetchAssociations(
		"SELECT * FROM tblreminder 
		WHERE (userid = 0 OR $privateTest) $filter ORDER BY subject");
}
