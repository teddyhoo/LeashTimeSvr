<? // reports-clients-export-xml2.php

require_once "common/init_session.php";
require "common/init_db_common.php";
$loginIds = fetchKeyValuePairs("SELECT userid, loginid FROM tbluser WHERE bizptr = {$_SESSION["bizptr"]}");
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "export-fns.php";

set_time_limit(300);
$t0 = time();

$locked = locked('o-');

$exportBillingFlagsGlobal = $_SESSION['preferences']['betaBillingEnabled'];

$workbook = array('name'=>'ClientsAndPets.fods',
	'sheets'=>array(
							array('generator'=>'dumpWorksheetForActiveClients'),
							array('generator'=>'dumpWorksheetForInactiveClients'),
							array('generator'=>'dumpWorksheetForPets')));

require_once "reports-dump-workbook-xml.php";

function dumpWorksheetForActiveClients() {
	global $exportBillingFlagsGlobal;
	startWorksheet($sheet = array('name'=>'Active Clients'));
	$status = "active = 1";
	if($_GET['clientids']) $status .= " AND clientid IN({$_GET['clientids']})";
	$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE $status");
	$columns = getClientColumns('full', $withCustomFieldsIfIndicated=1, $withFlags=1, $withBillingFlags=$exportBillingFlagsGlobal);
	dumpRowsForClients($columns, $clientids);
	endWorksheet($sheet);
}

function dumpWorksheetForInactiveClients() {
	global $exportBillingFlagsGlobal;
	startWorksheet($sheet = array('name'=>'Inactive Clients'));
	$status = "active = 0";
	if($_GET['clientids']) $status .= " AND clientid IN({$_GET['clientids']})";
	$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE $status");
	$columns = getClientColumns('full', $withCustomFieldsIfIndicated=1, $withFlags=1, $withBillingFlags=$exportBillingFlagsGlobal);
	dumpRowsForClients($columns, $clientids);
	endWorksheet($sheet);
}


function dumpRowsForClients($columns, $clientids) {
	// this function was added because A Leg Up's big client base broke it memory-wise
	dumpRow(array_map('htmlentities', $columns));
	foreach($clientids as $clientid) {
	//echo "ROW: ".print_r($row,1)."\n";	
		$row = clientOutput($clientid, 'full', $status=null, $target='xml');
		dumpRow($row);
	}
}

function dumpWorksheetForPets() {
	startWorksheet($sheet = array('name'=>'Pets'));
	$status = "1=1";
	if($_GET['clientids']) $status = "ownerptr IN({$_GET['clientids']})";
	$petids = fetchCol0("SELECT petid FROM tblpet WHERE $status");
	$rows = array();
	$columns = getPetColumns(null, $withCustomFieldsIfIndicated=1);
	dumpRow(array_map('htmlentities', $columns));
	foreach($petids as $petid) {
		$rows = petOutput($petid, 'full', $status=null, $target='xml');
		dumpRow($row);
	}
	endWorksheet($sheet);
}
