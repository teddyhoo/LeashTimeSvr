<? // appointment-status-diagnostics.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "provider-fns.php";

if(!staffOnlyTEST()) {
	echo "You must be logged in to DEV to use this page.";
	exit;
}
?>
<style>
.fauxlink {
	color:blue;
	text-decoration: underline;
	cursor: pointer;
}
</style>
<h2>Visit Status Changes for Visits from 7 days ago forward</h2>
You must be logged in to DEV to use this page.
<p>
<?
$firstDate = date('Y-m-d', strtotime("-7 days"));

function clientLink($a) {
	return fauxLink($a['clientname'], 
		"openConsoleWindow(\"clientapptdetails\", \"appointment-status-diagnostics.php?client={$a['clientptr']}\",1000,800)", 1);
}

function visitLink($id, $label) {
	return fauxLink($label, 
		"openConsoleWindow(\"appointmenteditor\", \"appointment-edit.php?id=$id\",530,530)", 1, "View the CURRENT VERSION of this visit");
}

if(!$_REQUEST['client']) {
	$countSql = "SELECT tblappointment.clientptr, CONCAT_WS(' ', c.fname, c.lname) as clientname, count(*) as num
	FROM tblchangelog 
	LEFT JOIN tblappointment ON appointmentid = itemptr
	LEFT JOIN tblclient c ON clientid = clientptr
	WHERE itemtable = 'tblappointment' AND date >= '$firstDate' 
	GROUP BY tblappointment.clientptr
	ORDER BY c.lname, c.fname";  //

	echo "Click on a client to see appointment details, dumbass.  :-)<p>";

	echo "<table border=1 bordercolor=black cellspacing=0><tr><th>Client<th># Changes";
	foreach(fetchAssociations($countSql, 1) as $a) {
			echo "<tr><td>".clientLink($a)."<td>{$a['num']}";
	}
}

else {
	$clientSql = "SELECT tblchangelog.user, tblchangelog.note as lognote, time, tblappointment.*, CONCAT_WS(' ', c.fname, c.lname) as clientname ,
			if(providerptr IS NULL, 'Unassigned', CONCAT_WS(' ', p.fname, p.lname)) as providername,
			label
	FROM tblchangelog 
	LEFT JOIN tblappointment ON appointmentid = itemptr
	LEFT JOIN tblclient c ON clientid = clientptr
	LEFT JOIN tblprovider p ON providerid = providerptr
	LEFT JOIN tblservicetype ON servicetypeid = servicecode
	WHERE itemtable = 'tblappointment' AND date >= '$firstDate'  and clientptr={$_REQUEST['client']}
	ORDER BY c.lname, c.fname, appointmentid, date";  //

$appts = fetchAssociations($clientSql, 1);
$users = array();
foreach($appts as $a) $users[] = $a['user'];
if($users) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$usernames = fetchAssociationsKeyedBy("SELECT userid, loginid, rights, email FROM tbluser WHERE userid IN (".join(',', $users).")", 'userid');
	list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
	include "common/init_db_petbiz.php";
}
else $usernames = array();

$providerUserNames = fetchKeyValuePairs("SELECT userid, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE userid IN (".join(',', $users).")");
foreach($usernames as $u) {
	$role = substr($u['rights'], 0, 1);
	if($role == 'o') $userfullnames[$u['userid']] = "Manager: {$u['email']}";
	else if($role == 'p') $userfullnames[$u['userid']] = "Sitter: {$providerUserNames[$u['userid']]}";
}


echo "<table width=98% border=1 bordercolor=black cellspacing=0><tr><th>Visit #<th>Change Time<th>User<th>Change Note<th>Visit Detail";

foreach($appts as $a) {
	if($clientname != $a['clientname']) {
		$clientname = $a['clientname'];
		echo "<tr><td colspan=4><b>Client: $clientname</b>";
	}
	$date = date('m/d/Y', strtotime($a['date']));
	$user = fauxLink($usernames[$a['user']]['loginid'], "#", 1, $userfullnames[$a['user']]);
	echo "<tr><td>{$a['appointmentid']}<td>{$a['time']}<td>$user<td>{$a['lognote']}<td>".visitLink($a['appointmentid'], "$date - {$a['timeofday']} - {$a['label']} - {$a['providername']}");
}
}
?>
<script language='javascript'>

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		if(w) w.focus();
	}
}

</script>
