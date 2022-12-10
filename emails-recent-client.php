<? // emails-recent-client.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "comm-fns.php";
require "gui-fns.php";
require "client-fns.php";

// Determine access privs
$locked = locked('o-');

$pageTitle = "Recent Client Emails";

include "frame.html";

$emails = fetchAssociations(
	"SELECT * FROM tblmessage 
		WHERE transcribed IS NULL and correstable = 'tblclient'
		ORDER BY datetime DESC LIMIT 200");

if($emails)  {
	foreach($emails as $email)
		$clientids[] = $email['correspid'];
	$clientids = array_unique($clientids);
	$clientDetails = getClientDetails($clientids);
}

foreach($emails as $i => $email) {
	$rowClasses[] = $class = $class == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';
	if($email['inbound']) {
		$emails[$i]['from'] = $email['originator'] ? $clientDetails[$email['originatorid']]['clientname'] : $clientDetails[$email['correspid']]['clientname'];
		$emails[$i]['to'] = 'Management';
	}
	else {
		$emails[$i]['from'] = $email['originatorid'] ? $clientDetails[$email['originatorid']]['clientname'] :  $email['mgrname'];
		$emails[$i]['to'] = $email['correspid'] ? $clientDetails[$email['correspid']]['clientname'] :  'Management';
	}
	
	$emails[$i]['subject'] = fauxLink($email['subject'], "openConsoleWindow(\"messagecomposer\", \"comm-view.php?id={$email['msgid']}&section=\", 800, 600);", 1);
	$emails[$i]['sortdate'] = date('Y-m-d H:i', strtotime($email['datetime']));
	$emails[$i]['datetime'] = shortDateAndTime(strtotime($email['datetime']), 'mil');
	if(strpos($email['correspaddr'], '|')) {
		$adds = array();
		$parts = explode('|', $email['correspaddr']);
		foreach($parts as $labelList) {
			$labelList = explode(':', $labelList);
			if(trim($labelList[1])) $adds[] = $labelList[1];
		}
		$emails[$i]['correspaddr'] = join(',', $adds);
	}
}

$columns = explodePairsLine('datetime|Date||from|From||to|To||correspaddr|Email||subject|Subject');
tableFrom($columns, $emails, 'width=100%', null, null, null, null, null, $rowClasses);
?>
<script language='javascript' src='common.js'></script>
<?
include "frame-end.html";
