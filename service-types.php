<?
// service-types.php
require_once "common/init_session.php";

if(staffOnlyTEST()) {
require_once "common/init_db_petbiz.php";
if($_REQUEST['changelog']) {
	$changes = fetchAssociations("SELECT * FROM tblchangelog WHERE itemptr = -999 AND itemtable = 'tblservicetype' ORDER BY `time` DESC");
	if(!$changes) {
		echo "No changes made since implementation (24 May 2018)";
		exit;
	}
	foreach($changes as $change) $mgrids[] = $change['user'];
	$mgrids = array_unique($mgrids);
	$mgrs = getManagers($ids=$mgrids, $ltStaffAlso=true);
	foreach($mgrids as $id) if(!$mgrs[$id]) $mgrs[$id] = array('name'=>'Unknown');
	echo "<table border=1>";
	foreach($changes as $change) {
		$date = shortDate(strtotime($change['time']));
		$time = date('H:i a', strtotime($change['time']));
		echo "<tr><td>$date</td><td>$time</td><td>{$change['operation']}</td><td>{$mgrs[$change['user']]['name']}</td><td>{$change['note']}</td></tr>";
	}
	exit;
}
}
	require_once "gui-fns.php";
	$breadcrumbs = 
		fauxLink('Client Service List', 'document.location.href="client-services-editor.php"', 1)
		.' <img src="art/help.jpg" width=20 height=20 onclick="showHelp()">';
	if($_SESSION['preferences']['enableclientservicepettypefilter']) $breadcrumbs .= ' - '
		.fauxLink('Pet Services', 'document.location.href="pet-services.php"', 1, "Staff Only");
	if(staffOnlyTEST()) $breadcrumbs .= ' - '
		.fauxLink('Change Log', '$.fn.colorbox({href:"service-types.php?changelog=1", width:"550", height:"400", scrolling: true, opacity: "0.3"});', 1, "Staff Only");
	$extraHeadContent = '<script language="javascript">function showHelp() {
	$.fn.colorbox({html:"'.addSlashes(helpString()).'", width:"550", height:"400", scrolling: true, opacity: "0.3"});
}</script>';

if($_SESSION['preferences']['sittersPaidHourly'])
	require_once "service-types-hourly.php";
else require_once "service-types-standard.php";

function helpString() {
	$help = <<<HELP
<h2 style='text-align:center'>The Service List and the Client Service List</h2>
<span style='font-size:1.2em'><p>This page (<b>Service List</b>) lists all of the services that your business offers.</p>
<p>To offer these services to your clients in the LeashTime Client User Interface, you must set up the <b>Client Service List</b>, which reduces and simplifies the list of Services to a form suitable to your clients.
<p>If no services are specified in the Client Service List, then the Service pulldown menu the client sees will be empty.</p>
<p>
<a href='client-services-editor.php'>Click here to go to the <b>Client Service List.</a> 
(Note: all unsaved changes on this page will be lost.)</span>
HELP;
	return trim(str_replace("\r", "", str_replace("\n", "", $help)));
}