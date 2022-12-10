<? // reports-clients-export-excel.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
$loginIds = fetchKeyValuePairs("SELECT userid, loginid FROM tbluser WHERE bizptr = {$_SESSION["bizptr"]}");
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "export-fns.php";

set_time_limit(300);
$t0 = time();

$locked = locked('o-');

$exportBillingFlagsGlobal = $_SESSION['preferences']['betaBillingEnabled'];

dumpWorkbook();

function addClientWorksheet(&$workbook, $name, $columns, &$clientids, $useColumnsInRows=false) {
	if($name != null) $workbook->addASheet($name);
	$workbook->addARow($columns);
if(!$columns || !is_array($columns)) echo "Bad [$name][columns]: ==> [[".print_r($columns,1)."]]";
	foreach($clientids as $i => $clientid) {
		$row = clientOutput($clientid, 'full', $status=null, $target='raw');

if(!$row || !is_array($row)) echo "Bad [$name][$i]: ==> $row";
//echo "[[[".print_r($row,1)."]]]";exit;	
		$workbook->addARow($row, $columns=null, $rowNumber=null, $worksheet=null);
	}
}

function addPetWorksheet(&$workbook, $name, $columns, &$petids, $useColumnsInRows=false) {
	if($name != null) $workbook->addASheet($name);
	$workbook->addARow($columns);
	foreach($petids as $i => $petid) {
		$row = petOutput($petid, 'full', $status=null, $target='raw');
if(!$row || !is_array($row)) echo "Bad [$name][$i]: ==> $row";
		$workbook->addARow($row, $columns=null, $rowNumber=null, $worksheet=null);
	}
}

function dumpWorkbook() {
	global $exportBillingFlagsGlobal;

	require_once "spreadsheet-dumper.php";

	$workbookName = 'ClientsAndPets';
	$firstSheetName = 'Active Clients';
	$workbook = newSpreadsheetDumper($workbookName, $firstSheetName);

	$name = 'Active Clients';
//echo "$name<hr>\n";	
	$status = "active = 1";
	if($_GET['clientids']) $status .= " AND clientid IN({$_GET['clientids']})";
	$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE $status");
	$columns = getClientColumns('full', $withCustomFieldsIfIndicated=1, $withFlags=1, $withBillingFlags=$exportBillingFlagsGlobal);
	addClientWorksheet($workbook, $name=null, $columns, $clientids);
	
	$name = 'Inactive Clients';
//echo "$name<hr>\n";	
	$status = "active = 0";
	if($_GET['clientids']) $status .= " AND clientid IN({$_GET['clientids']})";
	$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE $status");
	addClientWorksheet($workbook, $name, $columns, $clientids);

	$name = 'Pets';
//echo "$name<hr>\n";	
	$status = "1=1";
	if($_GET['clientids']) $status = "ownerptr IN({$_GET['clientids']})";
	$petids = fetchCol0("SELECT petid FROM tblpet WHERE $status");
	$rows = array();
	$columns = getPetColumns(null, $withCustomFieldsIfIndicated=1);
	addPetWorksheet($workbook, $name, $columns, $petids);

	$workbook->setActiveSheetIndex(0);


	$workbook->dumpToStandardOutput('Excel', "$workbookName.xls", $contentType=null);

}
	
	
