<? // item-note-fns.php

/* 
CREATE TABLE IF NOT EXISTS `relitemnote` (
  `noteid` int(11) NOT NULL auto_increment,
  `itemtable` varchar(20) NOT NULL,
  `itemptr` int(11) NOT NULL,
  `note` text NOT NULL,
  `priornoteptr` int(11) NOT NULL COMMENT 'for multi-notes',
  `authorid` int(11) NOT NULL COMMENT 'userid',
  `date` datetime NOT NULL,
  `subject` VARCHAR( 80 ) NULL,
  PRIMARY KEY  (`noteid`),
  UNIQUE KEY `itemtable` (`itemtable`,`itemptr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=14 ;
*/

function itemNoteIsEnabled() {
	static $enabled, $enabledSet;
	if(!$enabledSet) {
		$enabled = in_array('relitemnote', fetchCol0("SHOW TABLES"));
		$enabledSet = 1;
	}
	return $enabled;
}

function getItemNotesForList($listOfPairs) { // $index == -1 : last note
	foreach($listOfPairs as $pair) {
		if(count($pair) == 3) $results[] = getItemNote($pair[0], $pair[1], $pair[2]);
		else if(count($pair) == 2) $results[] = getItemNote($pair[0], $pair[1]);
	}
	return $results;
}

function getItemNote($itemtable, $itemptr, $index=0) { // $index == -1 : last note
	if(!$index) 
		return fetchFirstAssoc(
			"SELECT * 
				FROM relitemnote 
				WHERE itemtable = '$itemtable'
					AND itemptr = $itemptr
					AND priornoteptr = 0
				LIMIT 1");
	$notes = getItemNoteList($itemtable, $itemptr);
	
	return $notes ? null :
					($index == -1 ? $notes[count($notes)-1] : $notes[$index]);
}

function getItemNoteList($itemtable, $itemptr) {
	$notes = fetchAssociationsKeyedBy(
			"SELECT * 
				FROM relitemnote 
				WHERE itemtable = '$itemtable'
					AND itemptr = $itemptr", 'priornoteptr');
	while($notes) {
		$sorted[] = $kill = $notes[$lastIndex];
		unset($notes[$lastIndex]);
		$lastIndex = $kill['noteid'];
	}
	return $sorted;
}

function appendNote($oldNoteOrNoteId, $note) {
	$oldNote = getNoteById($oldNoteOrNoteId) ;
	// find last note in series, if note exists
	if($oldNote) $oldNote = getItemNote($oldNote['itemtable'], $oldNote['itemptr'], -1);
	
	return addItemNoteAfterNote($oldNote, $note);
}

function insertNoteBefore($oldNoteOrNoteId, $note) {
	$oldNote = getNoteById($oldNoteOrNoteId) ;
	$priornoteptr = $oldNote['priornoteptr'];
	updateTable('relitemnote', array('priornoteptr'=>-999), "noteid = {$oldNote['noteid']}", 1);
	$newnoteptr = addItemNoteAfterNote($priornoteptr, $note);
	updateTable('relitemnote', array('priornoteptr'=>$newnoteptr), "noteid = {$oldNote['noteid']}", 1);
	return $newnoteptr;
}

function updateNote($itemnoteOrId, $note) {
	if(!is_array($itemnoteOrId)) { 
		$itemnoteOrId = getNoteById($itemnoteOrId);
	}
	$itemnoteOrId['note'] = $note;
	$itemnoteOrId['date'] = date('Y-m-d H:i:s');;
	$itemnoteOrId['authorid'] = $_SESSION['auth_user_id'];
	if(!$itemnoteOrId['priornoteptr']) $itemnoteOrId['priornoteptr']  = '0';
	replaceTable('relitemnote', $itemnoteOrId, 1);
}
	
function deleteNote($oldNoteOrNoteId, $entireChain=false) {
	if(is_array($oldNoteOrNoteId)) {
		$oldNote = getItemNote($oldNoteOrNoteId['itemtable'], $oldNoteOrNoteId['itemptr'], $oldNoteOrNoteId['index']);
	}
	else {
		$oldNote = getNoteById($oldNoteOrNoteId) ;
	}
	if($entireChain) {
		deleteTable('relitemnote', "itemtable = '{$oldNoteOrNoteId['itemtable']}' AND itemptr = {$oldNoteOrNoteId['itemptr']}", 1);
	}
	else if($oldNote) {
		deleteTable('relitemnote', "noteid = {$oldNote['noteid']}", 1);
		if($priornoteptr = $oldNote['priornoteptr']) 
			updateTable('relitemnote', 
									array('priornoteptr'=>$priornoteptr), 
									"priornoteptr = {$oldNote['noteid']}", 1);
	}
}
	
function addItemNoteAfterNote($oldNoteOrNoteId, $note) {
	// if(is_array($oldNoteOrNoteId) this note may or may not exist in the database
	$oldNote = getNoteById($oldNoteOrNoteId) ;
	$note = is_array($note) ? $note : array('note'=>$note);
	$note['itemtable'] = $oldNote['itemtable'];
	$note['itemptr'] = $oldNote['itemptr'];
	$note['priornoteptr'] = $oldNote['noteid'];
	$note['date'] = date('Y-m-d H:i:s');
	$note['authorid'] = $_SESSION['auth_user_id'];
	return insertTable('relitemnote', $note, 1);
}
	
function getNoteById($oldNoteOrNoteId) {
	return !$oldNoteOrNoteId ? null : (
				 is_array($oldNoteOrNoteId)  ? $oldNoteOrNoteId  : 
				 fetchFirstAssoc("SELECT * FROM relitemnote WHERE noteid = $oldNoteOrNoteId LIMIT 1"));
}

function getAuthorNames($userids) {
	if(!$userids) return;
	$names = fetchAssociationsKeyedBy("SELECT userid, CONCAT_WS(' ', fname, lname) as name, nickname FROM tblprovider WHERE userid IN (".join(',', $userids).")", 'userid');
	$managers = array_diff($userids, array_keys($names ));
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	if($managers) $managers = fetchKeyValuePairs("SELECT userid, CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid IN (".join(',', $managers).")");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
	foreach($managers as $userid => $name) $names[$userid] = $name ? $name : "unknown";
	return $names;
}

function displayableNote($note, $subject, $editLink=null) {
	$note = str_replace("\r", "", $note);
	$note = str_replace("\n\n", "<p>", $note);
	$note = str_replace("\n", "<br>", $note);
	$note = ($subject ? "Subject: $subject<p>" : '').$note; 
	if($editLink) $note = echoButton('', 'Edit', "edit($editLink)", null, null, 1)." $note"; //fauxLink('<b>Edit</b>', "edit($editLink)", true)."<p>$note";
	return "$note";
}

function noteSubject($note) {
	$subject = $note['subject'] ? $note['subject'] : $note['note'];
	return truncatedLabel($subject, 60);
}

function expandableNoteRows($notes, $itemtable, $itemptr, $printable, $targetid) {
	foreach((array)$notes as $note) $authors[] = $note['authorid'];
	$authors = getAuthorNames($authors);
	//$separator = "\n<tr><td class='notesepr' colspan=3></td></tr>";
	$initialNewNoteDisplay = count($notes) ? 'display:none' : '';
	echo "\n<tr class='headerrow'><td class='notetd' style='$initialNewNoteDisplay' id='displaynote_-1' colspan=3>";
	if(!$notes) logbookItemEditor(-1, $itemtable, $itemptr);
	echo "</td></tr>";	
	foreach((array)$notes as $i => $note) {
		$subject = noteSubject($note);
		//$editButton = fauxLink('Edit', "edit({$note['noteid']})", true);
		$author = $authors[$note['authorid']];
		if(is_array($author)) {
			$title = $author['nickname'] ? "title='Sitter: ".safeValue($author['name'])."'" : 'Sitter';
			$author = $author['nickname'] ? $author['nickname'] : $author['name'];
		}
		else $title = "title='Manager'";
		//if($i > 0)  echo "<tr><td colspan=3><hr></td></tr>";
		$noteid = $note['noteid'];
		if($printable) $checkbox = "<input type='checkbox' id='sel_$noteid' name='sel_$noteid' onclick='if(event.stopPropagation) event.stopPropagation(); else event.cancelBubble = true;'> ";
		$displayNow = $noteid == $targetid ? '' : "style='display:none'";
		$date = shortDate(strtotime($note['date'])).' '.str_replace('XXX', '&nbsp;', date('g:iXXXa', strtotime($note['date'])));
		echo "\n<tr class='headerrow' onclick='showRow($noteid)'>"
				 ."<td class='notedate'>$checkbox$date</td>"
				 ."<td class='authorid' $title >"
				 .$author
				 ."</td><td class='subjecttd' id='subject_$noteid'>$subject</td></tr>"
				 ."\n<tr><td class='notetd' $displayNow id='displaynote_$noteid' colspan=3>"
				 .displayableNote($note['note'], $note['subject'], $noteid)
				 ."</td><tr>";
	}
}

function itemSummary($summaryid, $summaryitemtable, $summarycount, $summarytitle, $logOpenFunction=viewOfficeNotesLog) { // return a summary table for use in the client editor
	if(!$logOpenFunction) $logOpenFunction = 'viewOfficeNotesLog';
	fauxLink($summarytitle, "$logOpenFunction($summaryid)");
	if($summaryid) 
		$noteItems = array_reverse(fetchAssociations("SELECT * FROM relitemnote
																		WHERE itemtable = '$summaryitemtable'
																			AND itemptr = $summaryid
																			ORDER BY date DESC
																			LIMIT $summarycount"));
	if($noteItems) {
		echo "<table style='width:100%;background:white;margin-top:3px;'>\n";
		foreach($noteItems as $note) {
			$time = strtotime($note['date']);
			echo "<tr style='cursor:pointer;border: solid black 1px;' onclick='$logOpenFunction($summaryid, {$note['noteid']})'>"
				."<td>".shortestDate($time).date(' H:i', $time)."<td>".noteSubject($note)."</td></tr>\n";
		}
		echo "</table>";
	}
}

function logbookItemEditor($editid, $itemtable, $itemptr) {
	if($editid == -1) 
		$note = array('priornoteptr'=>
							fetchRow0Col0("SELECT max(noteid) FROM relitemnote WHERE itemtable = '$itemtable' AND itemptr = $itemptr"));
	else $note = fetchFirstAssoc("SELECT * FROM relitemnote WHERE noteid = $editid LIMIT 1");
	echo "<form name='note_editor' method='POST'>";
	hiddenElement('priornoteptr', $note['priornoteptr']);
	hiddenElement('editid', $editid);
	hiddenElement('itemtable', $itemtable);
	hiddenElement('itemptr', $itemptr);
	echoButton('', 'Save', 'saveEditor()');
	echo " ";
	echoButton('quitbutton', 'Quit', 'retireEditor()');
	echo " ";
	if($editid != -1) echoButton('', 'Delete', "deleteNote($editid)", 'HotButton', 'HotButtonDown');
	echo "<p>Subject: ";
	countdownInput(80, 'subject', $note['subject'], $inputClass='VeryLongInput', $onBlur=null, $position='underinput');
	echo "<p><textarea class='fontSize1_2em' cols=80 rows=10 id='savenote' name='savenote'>{$note['note']}</textarea>";
	echo "</form>";
}

function printItemNotes($noteids) {
	$notes = fetchAssociations("SELECT * FROM relitemnote WHERE noteid IN ($noteids)");
	foreach((array)$notes as $note) $authors[] = $note['authorid'];
	$authors = getAuthorNames($authors);

	foreach($notes as $i => $note) {
		echo "Date: ".shortDateAndTime(strtotime($note['date']))."<br>";
		echo "Author: {$authors[$note['authorid']]}";
		echo $note['subject'] ? '<br>' : '<p>';
		echo displayableNote($note['note'], $note['subject']);
		if($i < count($notes) -1) echo "<hr>";
	}
}
/*
  `noteid` int(11) NOT NULL auto_increment,
  `itemtable` varchar(20) NOT NULL,
  `itemptr` int(11) NOT NULL,
  `note` text NOT NULL,
  `priornoteptr` int(11) NOT NULL COMMENT 'for multi-notes',
  `authorid` int(11) NOT NULL COMMENT 'userid',
  `date` datetime NOT NULL,

*/