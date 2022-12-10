<? // chatter-fns.php

/*
tblchatternote
noteid
datetime
replytoid (null)
visitptr
visitdatestart
clientptr
providerptr (null)
adminptr (null)
authortable tblclient|tblprovider|tbluser
note (text)
visibility 0=officeonly|1=sitters|2=clients
officeonly (bool) trumps visibility

CREATE TABLE IF NOT EXISTS `tblchatternote` (
  `noteid` int(11) NOT NULL AUTO_INCREMENT,
  `datetime` datetime NOT NULL,
  `replytoid` int(11) DEFAULT NULL,
  `visitptr` int(11) DEFAULT NULL,
  `visitstarttime` datetime DEFAULT NULL,
  `clientptr` int(11) NOT NULL,
  `providerptr` int(11) DEFAULT NULL,
  `adminptr` int(11) DEFAULT NULL,
  `authortable` varchar(20) NOT NULL,
  `note` text NOT NULL,
  `visibility` tinyint(4) NOT NULL DEFAULT '2',
  `officeonly` tinyint(4) NOT NULL,
  PRIMARY KEY (`noteid`),
  KEY `clientindex` (`clientptr`),
  KEY `visitindex` (`visitptr`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;



*/

function ensureChatterNoteTableExists() {
	doQuery("CREATE TABLE IF NOT EXISTS `tblchatternote` (
  `noteid` int(11) NOT NULL AUTO_INCREMENT,
  `datetime` datetime NOT NULL,
  `replytoid` int(11) DEFAULT NULL,
  `visitptr` int(11) DEFAULT NULL,
  `visitstarttime` datetime DEFAULT NULL,
  `clientptr` int(11) NOT NULL,
  `providerptr` int(11) DEFAULT NULL,
  `adminptr` int(11) DEFAULT NULL,
  `authortable` varchar(20) NOT NULL,
  `note` text NOT NULL,
  `visibility` tinyint(4) NOT NULL DEFAULT '2',
  `officeonly` tinyint(4) NOT NULL,
  PRIMARY KEY (`noteid`),
  KEY `clientindex` (`clientptr`),
  KEY `visitindex` (`visitptr`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;");
}

function getDaysChatterNotes($date, $visibilityFilter='') { // not very efficient unless visitptr or clientptr is part of filter
	if($visibilityFilter) $visibilityFilter = "AND $visibilityFilter";
	return fetchAssociations(
		"SELECT * FROM tblchatternote 
		WHERE datetime LIKE '$date%' $visibilityFilter 
		ORDER BY datetime");
}

function getChatterNote($id, $metaOnly=false) {
	$fields = $metaOnly ? "noteid,datetime,replytoid,visitptr,visitstarttime,clientptr,providerptr,adminptr,
													authortable,note,visibility,officeonly" : '*';
	return fetchFirstAssoc("SELECT $fields FROM tblchatternote WHERE noteid = $id LIMIT 1");
}

function isOfficeOnly($noteOrId) {
	$note = is_array($noteOrId) ? $noteOrId : getChatterNote($noteOrId, $metaOnly=true);
	return $note['officeonly'] || ($note['visibility'] == 0);
}

function isSitterVisible($noteOrId) {
	$note = is_array($noteOrId) ? $noteOrId : getChatterNote($noteOrId, $metaOnly=true);
	return !$note['officeonly'] && ($note['visibility'] > 0);
}

function isClientVisible($noteOrId) {
	$note = is_array($noteOrId) ? $noteOrId : getChatterNote($noteOrId, $metaOnly=true);
	return !$note['officeonly'] && ($note['visibility'] == 2);
}

function getAuthor($noteOrId) {
	static $sitters, $mgrs, $clients;
	$note = is_array($noteOrId) ? $noteOrId : getChatterNote($noteOrId, $metaOnly=true);
	if($note['authortable'] == 'tblclient') {
		if(!$clients[$note['clientptr']])
			$clients[$note['clientptr']] = fetchFirstAssoc("SELECT  'client' as `type`, fname, CONCAT_WS(' ', fname, lname) as name FROM tblclient 
																WHERE clientid = {$note['clientptr']} LIMIT 1", 1);
		$author = $clients[$note['clientptr']];
	}
	else if($note['authortable'] == 'tblprovider') {
		if(!$sitters[$note['providerptr']])
			$sitters[$note['providerptr']] = fetchFirstAssoc(
				"SELECT 'sitter' as `type`, fname, 
						CONCAT_WS(' ', fname, lname) as name,
						IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as shortname
					FROM tblprovider 
					WHERE providerid = {$note['providerptr']} LIMIT 1", 1);
		$author = $sitters[$note['providerptr']];
	}
	else if($note['authortable'] == 'tbluser') {
		if(!$mgrs) {
			list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
			require "common/init_db_common.php";
			$mgrs[$note['adminptr']] = fetchFirstAssoc(
				"SELECT 'admin' as `type`, fname, CONCAT_WS(' ', fname, lname) as name
					FROM tbluser
					WHERE userid = {$note['adminptr']}", 1);
			reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		}
		$author = $mgrs[$note['adminptr']];
	}
	return $author;
}

function getChatterUser($userid) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$user = fetchFirstAssoc(
			"SELECT *
				FROM tbluser
				WHERE userid = $userid", 1);
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		return $user;
}

function addVisitChatterNote($apptid, $payload, $authorid, $authortable='tblprovider', $visibility=2, $replyTo=null) {
	if(!$payload) return;
	$appt = fetchFirstAssoc("SELECT clientptr, date, starttime FROM tblappointment WHERE appointmentid = $apptid LIMIT 1", 1);
	$note = array(
			'datetime'=>date('Y-m-d H:i:s'),
			'visitptr'=>$apptid,
			'visitstarttime'=>$appt['date'].' '.$appt['starttime'],
			'clientptr'=>$appt['clientptr'],
			'authortable'=>$authortable,
			'note'=>$payload,
			'visibility'=>$visibility);
	if($authortable == 'tblprovider') $note['providerptr'] = $authorid;
	else if($authortable == 'tbluser') $note['adminptr'] = $authorid;
	if($replyTo) $note['replytoid'] = $replyTo;
	return insertTable('tblchatternote', $note, 1);
}
		

function addClientChatterNote($clientptr, $payload, $authorid, $authortable='tblprovider', $visibility=2, $replyTo=null) {
	if(!$payload) return;
	$note = array(
			'datetime'=>date('Y-m-d H:i:s'),
			'clientptr'=>$appt['clientptr'],
			'authortable'=>$authortable,
			'note'=>$payload,
			'visibility'=>$visibility);
	if($authortable == 'tblprovider') $note['providerptr'] = $authorid;
	else if($authortable == 'tbluser') $note['adminptr'] = $authorid;
	if($replyTo) $note['replytoid'] = $replyTo;
	return insertTable('tblchatternote', $note, 1);
}
		
