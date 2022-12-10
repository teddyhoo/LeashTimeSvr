<? // archive-fns.php

function setUpMessageArchive() {
	doQuery("CREATE TABLE IF NOT EXISTS `tblmessagearchive` (
	  `msgid` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `inbound` tinyint(1) DEFAULT NULL,
	  `correspid` int(10) unsigned NOT NULL DEFAULT '0',
	  `correstable` varchar(45) NOT NULL DEFAULT '',
	  `mgrname` varchar(45) NOT NULL DEFAULT '',
	  `subject` varchar(80) NOT NULL DEFAULT '',
	  `body` mediumblob NOT NULL,
	  `datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
	  `transcribed` varchar(45) DEFAULT NULL,
	  `tags` varchar(100) DEFAULT NULL,
	  `correspaddr` text NOT NULL,
	  `originatortable` varchar(45) DEFAULT NULL,
	  `originatorid` int(11) DEFAULT NULL,
	  `hidefromcorresp` tinyint(1) NOT NULL DEFAULT '0',
	  PRIMARY KEY (`msgid`),
	  KEY `correspindex` (`correspid`),
	  KEY `originatorindex` (`originatorid`,`originatortable`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");
	//	  `body` mediumblob NOT NULL,
	//	  `body` mediumtext NOT NULL,

}

function safeDeleteMessagesBefore($date, $maxCount=null) {
	// THIS CHECKS THAT ALL MESSAGES ARE ARCHIVED SUCCESSFULLY BEFORE DELETION
	setUpMessageArchive();
	echo fetchRow0Col0("SELECT count(*) FROM tblmessagearchive")." already in archive.<p>";
	$date = date('Y-m-d', strtotime($date)).' 00:00:00';
	echo fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE datetime < '$date'")." visits to delete.<p>";
	//replaceTable('tblpreference', array('property'=>'archivedmessagethresholddate', 'value'=>$date), 1);
	$result = doQuery("SELECT * FROM tblmessage WHERE datetime < '$date' ORDER BY msgid");
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		//if($origBody = fetchRow0Col0("SELECT body FROM tblmessagearchive WHERE msgid = {$row['msgid']} LIMIT 1", 1)) { // }
		if($origBody = $row['body']) {
			$restoredBody = fetchArchiveMessageBody($row['msgid']);
			if($restoredBody == $origBody) {
				$deleted += 1;
				// delete the message from tblmessage here
				deleteTable('tblmessage', "msgid = {$row['msgid']}",1);				
				if($maxCount == $deleted) break;
			}
			else {
				echo "[{$row['msgid']}] body mismatch<br>";
				$errors +=1;
			}
		}
	}
	//if($deleted) doQuery("ALTER TABLE `tblmessage` ENGINE=InnoDB", 1);
	return array('errors'=>$errors, 'deleted'=>$deleted);
}

function archiveMessagesBefore($date, $maxCount=null, $deleteOriginals=false) {
	require_once "preference-fns.php";
	// run this from a cron
	setUpMessageArchive();
	$date = date('Y-m-d', strtotime($date)).' 00:00:00';
	//echo fetchRow0Col0("SELECT count(*) FROM tblmessagearchive")." already in archive.<p>";
	//echo fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE datetime < '$date'")." visits to archive.<p>";
	replaceTable('tblpreference', array('property'=>'archivedmessagethresholddate', 'value'=>$date), 1);
	$result = doQuery("SELECT * FROM tblmessage WHERE datetime < '$date' ORDER BY msgid");
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if(!fetchRow0Col0("SELECT msgid FROM tblmessagearchive WHERE msgid = {$row['msgid']} LIMIT 1", 1)) {
			if(archiveMessage($row)) {
				$added += 1;
				// delete the message from tblmessage here
				if($deleteOriginals) {
					deleteTable('tblmessage', "msgid = {$row['msgid']}",1);
				}
				$latest = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'latestarchivedmessagedate' LIMIT 1", 1);
				$latest = ($latest && strtotime($latest) > strtotime($row['datetime'])) ? $latest : $row['datetime'];
				setPreference('latestarchivedmessagedate', $latest);
				//replaceTable('tblpreference', array('property'=>'latestarchivedmessagedate', 'value'=>$row['datetime']), 1);
				
				if($maxCount == $added) break;
			}
			else $errors +=1;
		}
	}
	doQuery("OPTIMIZE TABLE `tblmessage`");

	return array('errors'=>$errors, 'added'=>$added, 'lastdate'=>$row['datetime']);
}

function lastArchivedMessageDateTime() {
	return $_SESSION['preferences']['latestarchivedmessagedate'];
}

function fetchArchiveMessageBody($msgid) {
	return fetchRow0Col0("SELECT UNCOMPRESS(body) FROM tblmessagearchive WHERE msgid = $msgid LIMIT 1", 1);
}

function archiveMessage($row) {
	$origBody = $row['body'];
	$row['body'] = sqlVal("COMPRESS('".mysql_real_escape_string((string)$row['body'])."')");
	foreach(explode(',', 'correstable,mgrname,subject,correspaddr') as $f) //body,
		if(!$row[$f])	$row[$f] = sqlVal("''");
	insertTable('tblmessagearchive', $row, 1);
	$error1 = mysql_error();
	$restoredBody = fetchArchiveMessageBody($row['msgid']);
	$error2 = mysql_error();
	//TEST START
	//$blob = fetchRow0Col0("SELECT body FROM tblmessagearchive WHERE msgid = {$row['msgid']} LIMIT 1", 1);
	//$restoredBody = gzuncompress($blob);
	//echo "ORIG:<br>$origBody<hr>COMPRESSED: (".strlen($row['body'])." chars)<br>{$row['body']}<hr>FETCHED: (".strlen($blob)." chars)<br>$blob<hr>RESTORED:<br>$restoredBody<hr><hr>";exit;
	//echo "ORIG:<br>$origBody<hr>COMPRESSED: (".strlen($row['body'])." chars)<br>{$row['body']}<hr>RESTORED:<br>$restoredBody<hr><hr>";exit;
//for($i=0;$i<strlen($row['body']); $i++)
//	if(substr($blob, $i+1, 1) != substr($row['body'], $i, 1)) echo "Mismatch at character [$i]<br>";
	if($error1 || $error2 || ($origBody != $restoredBody)) {
		if($error1) logLongError("msgarchive1 [{$row['msgid']}] $error1");
		if($error2) logLongError("msgarchive2 [{$row['msgid']}] $error1");
		if($origBody != $restoredBody) logLongError("msgarchive mismatch: {$row['msgid']}");
		echo "Failed to successfully archive message ID: {$row['msgid']}"
		.($error1 ? "msgarchive1 [{$row['msgid']}] $error1" : '')
		.($error1 ? "msgarchive2 [{$row['msgid']}] $error2" : '')
		.($origBody != $restoredBody ? "msgarchive mismatch: {$row['msgid']}" : '')
		."<br>";
		if(!$origBody) echo "-- Empty original.<br>";
		else {
			echo "Orig: ".strlen($origBody)." bytes  restored: ".strlen($restoredBody)." bytes<br>";
			//echo "<div style='background:yellow'>$origBody</div><div style='background:pink'>$restoredString</div>";
		}
		return 0;
	}
	return 1;
}

// LOGINS table

function setUpLoginsArchive() {
	doQuery("CREATE TABLE IF NOT EXISTS `tblloginarchive` (
  `LoginID` varchar(65) NOT NULL,
  `Success` tinyint(1) NOT NULL DEFAULT '0',
  `FailureCause` char(1) NOT NULL DEFAULT '',
  `RemoteAddress` varchar(20) NOT NULL DEFAULT '',
  `browser` varchar(255) DEFAULT NULL,
  `extbizid` varchar(10) DEFAULT NULL,
  `extloginpage` varchar(255) DEFAULT NULL,
  `UserUpdatePtr` int(11) DEFAULT '0',
  `LastUpdateDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `note` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Failures: Unknown,Password,InactiveUser,RightsMissing,FoundN';
");

}

function archiveLoginsBefore($date, $maxCount=null, $deleteOriginals=false) {//Before
	// run this from a cron
	setUpLoginsArchive();
	echo fetchRow0Col0("SELECT count(*) FROM tblloginarchive")." already in archive.<p>";
	$date = date('Y-m-d 00:00:00', strtotime($date));
	echo fetchRow0Col0("SELECT count(*) FROM tbllogin WHERE LastUpdateDate < '$date'")." login rows to archive.<p>";
	$result = doQuery("SELECT * FROM tbllogin WHERE LastUpdateDate < '$date' ORDER BY LastUpdateDate");
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			if(archiveLogin($row)) {
				$added += 1;
				//replaceTable('tblpreference', array('property'=>'latestarchivedmessagedate', 'value'=>$row['datetime']), 1);
				if($maxCount == $added) break;
			}
			else $errors +=1;
		// delete the message from tblmessage here
		if($deleteOriginals) ; // .....
	}
	return array('errors'=>$errors, 'added'=>$added, 'lastdate'=>$row['datetime']);
}

function archiveLoginsAfter($date, $maxCount=null, $deleteOriginals=false) { // 2010-12-02 11:43:36
	// run this from a cron
	setUpLoginsArchive();
	echo fetchRow0Col0("SELECT count(*) FROM tblloginarchive")." already in archive.<p>";
	$date = date('Y-m-d H:i:s', strtotime($date));
	echo fetchRow0Col0("SELECT count(*) FROM tbllogin WHERE LastUpdateDate >= '$date'")." login rows to archive.<p>";
	$result = doQuery("SELECT * FROM tbllogin WHERE LastUpdateDate > '$date' ORDER BY LastUpdateDate");
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			if(archiveLogin($row)) {
				$added += 1;
				//replaceTable('tblpreference', array('property'=>'latestarchivedmessagedate', 'value'=>$row['datetime']), 1);
				if($maxCount == $added) break;
			}
			else $errors +=1;
		// delete the message from tblmessage here
		if($deleteOriginals) ; // .....
	}
	return array('errors'=>$errors, 'added'=>$added, 'lastdate'=>$row['datetime']);
}

function archiveLogin($row) {  // compression is not worthwhile for such short values
	foreach(explode(',', 'LoginID,FailureCause,RemoteAddress,Success,browser,extloginpage') as $f) //body,
		if(!$row[$f])	$row[$f] = sqlVal("''");
	insertTable('tblloginarchive', $row, 1);
	return 1;
}
