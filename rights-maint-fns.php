<? // rights-maint-fns.php

function getGlobalRights($dispatcherOnly=false) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$filter = $dispatcherOnly ? "WHERE dispatcheronly=1 OR `key` like '*%'" : ''; //
	$rights = fetchAssociationsKeyedBy("SELECT * FROM tblrights $filter ORDER BY sequence", 'key');
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $rights;
}

function getDispatcherLevelRights() {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$rights = fetchAssociationsKeyedBy("SELECT * FROM tblrights WHERE dispatcheronly = 1", 'key');
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $rights;
}

function rightsTable($rights, $suffix='', $dispatcherOnly=false) {
	$roles = explodePairsLine('o|Owner||c|Client||p|Provider||z|Leashtime Maintainer||x|Corporate Overseer');
	$s = "<table>\n";
	foreach(getGlobalRights($dispatcherOnly) as $right) {
		$s .= "<tr><td>";
		$s .= labeledCheckbox($right['label'], 'right_'.$right['key']."_$suffix", in_array($right['key'], $rights), $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=true);
		$s .= "</td><td style='vertical-align:bottom;padding-left:7px;'>{$right['description']}</td></tr>";
	}
	$s .= "</table>\n";
	return $s;
}

function updateUserRights($userid, $changes) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	updateTable('tbluser', $changes, "userid = $userid", 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
}

/*

*cc	0	Charge Client Credit Cards	User can charge client credit cards
*cm	0	Manage Client Credit Cards	User can view and edit client credit card information.
qk	0	Use Quick Client Editor	User can use the Quick Client Editor as an alternative to the normal Client Editor.
ki	0	Manage Own Key Set	User (a sitter) can manage (check in, check out, etc.) his own keys.
ka	0	Manage All Keys	User can manage (check in, check out, etc.) all provider keys and keys in keysafes.
rq	0	Make Client Request	User may make a client request on behalf of a client.
va	0	View Visits	User may view visit details (not edit).
vp	0	View Pets	User may view and change client pet photos.
vc	0	View Clients	User may view client details (not edit).
vh	0	View Pay History	User may view and edit Pay History for all sitters.
#ev	1	Change Visits and Schedules	Dispatcher can create and modify visits and schedules.
#ec	1	Edit Clients	Dispatcher can edit client information.
#es	1	Edit Sitters	Dispatcher can edit sitter information.
#vr	1	View Reports	Dispatcher can view reports.
#gi	1	Generate Invoices	Dispatcher can generate invoices and process payments.
#pa	1	Payroll Access	Dispatcher can view sitter payment information.
#pp	1	Payroll Processing	Dispatcher can process sitter payment information.
#km	1	Key Management	Dispatcher can manage key information.


*/