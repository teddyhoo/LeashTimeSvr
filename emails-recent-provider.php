<? // emails-recent-provider.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "comm-fns.php";
require "gui-fns.php";
require "provider-fns.php";

// Determine access privs
$locked = locked('o-');

$pageTitle = "Recent Sitter Emails";

include "frame.html";

$oldsort = $_REQUEST['oldsort'] ? explode('_', $_REQUEST['oldsort']) : ''; // datetime_ASC
$sort = $_REQUEST['sort']; // datetime
if($sort[0] == null) {
	$sort = "datetime";
	$dir = 'DESC';
}
else if($sort == $oldsort[0]) {
	$dir = $oldsort[1] == 'ASC' ? 'DESC' : 'ASC';
}
else {
	$dir = 'ASC';
}
//print_r($oldsort);echo "DIR: $dir<hr>";	
//echo "<form><input type='hidden' id='oldsort' name='oldsort' value='$sort_$dir'></form>";
$emails = fetchAssociations(
	"SELECT * FROM tblmessage 
		WHERE transcribed IS NULL and correstable = 'tblprovider'
		ORDER BY datetime DESC LIMIT 200");

$providerNames = getProviderNames();

foreach($emails as $i => $email) {
	$rowClasses[] = $class = $class == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';
	if($email['inbound']) {
		$emails[$i]['from'] = $email['originator'] ? $providerNames[$email['originatorid']] : $providerNames[$email['correspid']];
		$emails[$i]['to'] = 'Management';
	}
	else {
		$emails[$i]['from'] = $email['originatorid'] ? $providerNames[$email['originatorid']] :  $email['mgrname'];
		$emails[$i]['to'] = $email['correspid'] ? $providerNames[$email['correspid']] :  'Management';
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

function sortCmp($a, $b) {
	global $sort, $dir;
	static $sorts;
	$sorts = $sorts ? $sorts : explodePairsLine('datetime|sortdate||from|from||to|to||correspaddr|correspaddr||subject|subject');
	$sortField = $sorts[$sort];
	$a = strtoupper(strip_tags($a[$sortField]));
	$b = strtoupper(strip_tags($b[$sortField]));
	if(0 && $sort == 'correspaddr') {
//echo "$sort: {$a}<hr>{$b}<hr><hr>\n";
		if(strpos($a, 'TO:') === 0) $a = substr($a, 3);
		if(strpos($b, 'TO:') === 0) $b = substr($b, 3);
	}
//echo "{$a}<hr>{$b}<hr><hr>\n";
	
	$result = strcmp($a, $b);
	if($dir == 'DESC') $result = 0-$result;
	return $result;
}

usort($emails, 'sortCmp');

$columns = explodePairsLine('datetime|Date||from|From||to|To||correspaddr|Email||subject|Subject');
if(TRUE || mattOnlyTEST())
	foreach($columns as $k => $v)
		$columns[$k] = fauxLink($v, "sortBy(\"$k\")", 1, 'Sort');
tableFrom($columns, $emails, 'width=100%', null, null, null, null, null, $rowClasses);
?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function sortBy(key) {
	document.location.href='?sort='+key+'&oldsort=<?= $sort.'_'.$dir ?>';
}
</script>
<?
include "frame-end.html";
