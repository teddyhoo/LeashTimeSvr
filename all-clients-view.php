<? // all-clients-view.php
set_time_limit(5 * 60);

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-');

$where = isset($_REQUEST['active']) ? "WHERE active={$_REQUEST['active']}" : '';

$clientids = fetchCol0("SELECT clientid FROM tblclient $where ORDER BY lname, fname");

if($clientids && ($num = count($clientids)) > 20) {
	if(!$_REQUEST['goahead']) {
		$active = isset($_REQUEST['active']) ? "&active={$_REQUEST['active']}" : '';
		echo "Displaying $num clients will take some time.  Click <a href='all-clients-view.php?goahead=1$active'>Proceed</a> to continue.";
		exit;
	}
}

foreach($clientids as $id) {
	include "client-view.php";
	$noScriptOrStyle = true;
	echo "\n\n<hr>\n\n";
}	