<? // export-clients.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
$loginIds = fetchKeyValuePairs("SELECT userid, loginid FROM tbluser WHERE bizptr = {$_SESSION["bizptr"]}");

require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "export-fns.php";

$locked = locked('o-');

$status = $_REQUEST['status'];
$status = $status == 'active' ? "AND active = 1" : ($status == 'inactive' ? "AND active = 0" : '1=1');

//clientCSV(659, $_REQUEST['fields']);exit;

$clientids = $_REQUEST['ids'] ? explode(',', $_REQUEST['ids']) : (
						$_REQUEST['filteredlist'] && $_SESSION['clientListIDString'] ? explode(',', $_SESSION['clientListIDString']) 
						: fetchCol0("SELECT clientid FROM tblclient WHERE $status"));
unset($_SESSION['clientListIDString']);

$exportBillingFlagsGlobal = $_SESSION['preferences']['betaBillingEnabled'];

$columns = getClientColumns($_REQUEST['fields'], $withCustomFieldsIfIndicated=1, $withFlags=1, $withBillingFlags=$exportBillingFlagsGlobal);
header("Cache-Control: no-store, no-cache");
header("Pragma:");
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=Clients.csv ");

if(FALSE && mattOnlyTEST()) {
	foreach($columns as $col)
	if(strpos($col,'phone') !== FALSE)
		$columns[] = "{$col}_raw";
}

$includeVisitCounts = $_SESSION['preferences']['enableFirstLastCompletedInExports'];
if($includeVisitCounts) $columns = array_merge($columns, array('completed visits', 'first visit', 'last visit'));

echo join(',', array_map('csv', $columns))."\n";
foreach($clientids as $clientptr)
	echo clientCSV($clientptr, $_REQUEST['fields'])."\n";
