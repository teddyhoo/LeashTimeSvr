<? // daily-notes.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "chatter-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";
require_once "js-gui-fns.php";

// Determine access privs
if(userRole() == 'd') $locked = locked('d-');
else $locked = locked('o-');

extract(extractVars('sort,day', $_REQUEST)); // sort:time|prov
ensureChatterNoteTableExists();
$services = getAllServiceNamesById();
$pnames = getProviderShortNames("ORDER BY name");
$pnames[0] = 'Unassigned';
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
if($roDispatcher) {
	$visitEditScript = "appointment-view.php?id=";
}
else $visitEditScript = "appointment-edit.php";

function showNote($note, $showProvider=true) {
	global $pnames, $services, $visitEditScript;
	$appt = $note['appointment'];
	echo "<div class='notelooks'>{$note['note']}</div><p>";
	$authName = $note['author']['displayname'];
	if($showProvider) echo "&mdash; $authName at ";
	echo date('g:i a', strtotime($note['datetime']))."<p>";
	$clientLink = fauxLink($appt['client'], "parent.location.href=\"client-edit.php?id={$appt['clientptr']}\"", 1, 'Edit client in main window');
	$visitLink = fauxLink($services[$appt['servicecode']], "openConsoleWindow(\"visiteditor\", \"$visitEditScript?id={$appt['appointmentid']}\", 600,500)", 1, 'Edit Visit');
//print_r($note);
	
	echo "<table width=75%><tr>\n";
	echo "<td>Visit$noteid: $clientLink</td><td>$visitLink</td><td>{$appt['pets']}</td><td>{$appt['timeofday']}</td>\n";
	echo "</tr></table>\n";
}

function getAppointments($day, $withNames=true) {
	// get only modified appointments for today
	$joins = ''.
	$extraFields = '';
	if($withNames) {
		$extraFields .= ", CONCAT_WS(' ',tblclient.fname, tblclient.lname) as client, "
										."tblclient.zip as zip, IFNULL(nickname, CONCAT_WS(' ',tblprovider.fname, tblprovider.lname)) as provider";
		$joins .= " LEFT JOIN tblclient ON clientid = tblappointment.clientptr LEFT JOIN tblprovider ON providerid = providerptr";
	}
	return fetchAssociationsKeyedBy(
			"SELECT tblappointment.*, IFNULL(tblappointment.modified, tblappointment.starttime) as usedate $extraFields FROM tblappointment $joins 
			 WHERE date = '$day' ORDER BY usedate DESC", 'appointmentid'); // filter blank notes later
}


$appts = getAppointments(dbDate($day), $withNames=true);
$chatternotes = array();
if($appts) {
	$chatternotes = fetchAssociations($sql = 
		"SELECT * FROM tblchatternote 
		WHERE visitptr IN (".join(',', array_keys($appts)).") AND providerptr IS NOT NULL
		ORDER BY noteid, datetime", 1);
		
	foreach($chatternotes as $i => $note) {
		$apptid = $note['visitptr'];
		$chatternotes[$i]['appointment'] = $appts[$apptid];
		$chatterVisitPtrs[$apptid] = 1;
	}
}
	
// merge appt notes in with chatternotes where available
foreach($appts as $apptid => $appt) {
	if(!$chatterVisitPtrs[$apptid] && trim($appt['note']) && $appt['modified']) {
		$chatternote = array('datetime'=>$appt['modified'],'note'=>trim($appt['note']),
			'providerptr'=>$appt['providerptr'],'authortable'=>'tblprovider', 'appointment'=>$appt);
		//identify the likely author of the note
		if($appt['modifiedby'] != $appt['providerptr']) {
			$modifiedby = getChatterUser($appt['modifiedby']);
			$rights = $modifiedby['rights'];
			$chatternote['authortable'] = 
				strpos($rights, 'p-') === 0 ? 'tblprovider' : (
				strpos($rights, 'c-') === 0 ? 'tblclient' :  // very unlikely, probably an error
				'tbluser');
			$mods = array('providerptr'=>null, 'adminptr'=>null);
			if($chatternote['authortable'] == 'tblprovider')
				$mods['providerptr'] = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE userid = {$appt['modifiedby']} LIMIT 1");
			else $mods['providerptr'] = null;
			if($chatternote['authortable'] == 'tbluser')
				$mods['adminptr'] = $appt['modifiedby'];
			foreach($mods as $k => $v) $chatternote[$k] = ($v ? $v : sqlVal('null'));
		}
		$chatternotes[] = $chatternote;
		}
}

foreach($chatternotes as $i => $note) {
	$chatternotes[$i]['author'] = getAuthor($note);
	$nameSuffix = $chatternotes[$i]['author']['type'] == 'sitter' ? '' : " ({$chatternotes[$i]['author']['type']})";
	$chatternotes[$i]['author']['displayname'] = 
		$chatternotes[$i]['author']['shortname'] ? $chatternotes[$i]['author']['shortname']
		: $chatternotes[$i]['author']['name'].$nameSuffix;
}

function byNoteId($a, $b) { return $a['noteid'] < $b['noteid'] ? -1 : ($a['noteid'] > $b['noteid'] ? 1 : 0); }
function byDateTime($a, $b) { return ($x = strcmp($a['datetime'], $b['datetime'])) ? $x : byNoteId($a, $b); }
function byProvider($a, $b) { return ($x = strcmp($a['author']['displayname'], $b['author']['displayname'])) ? $x : byDateTime($a, $b); }
usort($chatternotes, 'byDateTime'); // the default
if($sort == 'prov') usort($chatternotes, 'byProvider');

require "frame-bannerless.php";
$sortButton = $sort == 'prov' ? '' : 'prov';
$sortLabel = $sort == 'prov' ? 'Sort by Time' : 'Sort by Sitter';
$sortButton = echoButton('', $sortLabel, "document.location.href=\"daily-notes.php?day=$day&sort=$sortButton\"", null, null, 1);

//$fontFamily = 'Impact, Charcoal, sans-serif';
$fontFamily = '"Palatino Linotype", "Book Antiqua", Palatino, serif';
?>
<h2>Sitter Notes: <?= date('l', strtotime($day)).' '.shortNaturalDate(strtotime($day))."  $sortButton"; ?></h2>
<style>.notelooks {font-size:1.7em; color:darkgreen; text-align: center; font-family:<?= $fontFamily ?>;}</style>
<form name='incompleteform'>
<? 
calendarSet('Date:', 'day', shortDate(strtotime($day)), null, null, true, null, 'changeDate()');
?>
</form>
<?

if(!$chatternotes) echo "<div class='tiplooks fontSize1_2em'>No Notes to display</div>";

foreach($chatternotes as $note) {
	if($sort == 'prov' && $sittername != $note['author']['displayname']) {
		if($sittername) echo "<hr>";
		$sittername = $note['author']['displayname'];
		echo "<h3>$sittername</h3><hr>";
	}
	showNote($note, $showProvider=($sort != 'prov')) ;
	echo "<hr>";
}

?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
<?
dumpPopCalendarJS();
?>
setPrettynames('appointmentdate', 'Date');
function changeDate() {
  if(!MM_validateForm(
		  'day', '', 'R',
		  'day', '', 'isDate')) return;
	document.location.href='daily-notes.php?sort=<?= $sort ?>&day='+document.getElementById('day').value;
}
</script>