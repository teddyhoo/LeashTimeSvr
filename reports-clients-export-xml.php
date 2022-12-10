<? // reports-clients-export-xml.php

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

function dumpWorksheet($name, $columns, $rows, $useColumnsInRows=false) {
	echo '<ss:Worksheet ss:Name="'.$name.'"><ss:Table>';
	dumpRow(array_map('htmlentities', $columns));
	foreach($rows as $row) {
//echo "ROW: ".print_r($row,1)."\n";	
		dumpRow($row, ($useColumnsInRows ? $columns : null));
	}
	echo '</ss:Table></ss:Worksheet>';
}

function dumpWorksheetForClients($name, $columns, $clientids, $useColumnsInRows=false) {
	// this function was added because A Leg Up's big client base broke it memory-wise
	echo '<ss:Worksheet ss:Name="'.$name.'"><ss:Table>';
	dumpRow(array_map('htmlentities', $columns));
	foreach($clientids as $clientid) {
//echo "ROW: ".print_r($row,1)."\n";	
	$row = clientOutput($clientid, 'full', $status=null, $target='xml');
	dumpRow($row, ($useColumnsInRows ? $columns : null));
	}
	echo '</ss:Table></ss:Worksheet>';
}

function dumpRow($row, $columns=null) {
	echo '<ss:Row>';
	$columns = $columns ? $columns : array_keys($row);
	foreach($columns as $col) dumpCell($row[$col]);
	echo '</ss:Row>';
}

function dumpCell($val) {
	//echo '<ss:Cell><ss:Data ss:Type="String">'.$val.'</ss:Data></ss:Cell>';
	echo "<ss:Cell><ss:Data ss:Type=\"String\"><![CDATA[{$val}]]></ss:Data></ss:Cell>";
}

function dumpWorkbook() {
$TEST = FALSE; // mattOnlyTEST(); //
	global $exportBillingFlagsGlobal;
if(!$TEST) {
	header("Cache-Control: no-store, no-cache");
	header("Pragma:");
	//header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
	header("Content-Type: application/vnd.ms-excel");
	header("Content-Disposition: attachment; filename=ClientsAndPets.xls ");
	echo '<?xml version="1.0"?><ss:Workbook xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
}
	$name = 'Active Clients';
	$status = "active = 1";
	if($_GET['clientids']) $status .= " AND clientid IN({$_GET['clientids']})";
	$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE $status");
	$columns = getClientColumns('full', $withCustomFieldsIfIndicated=1, $withFlags=1, $withBillingFlags=$exportBillingFlagsGlobal);
	
	global $includeVisitCounts;
	$includeVisitCounts = $_SESSION['preferences']['enableFirstLastCompletedInExports'];
	if($includeVisitCounts) $columns = array_merge($columns, array('completed visits', 'first visit', 'last visit'));
	
	dumpWorksheetForClients($name, $columns, $clientids);
	//$rows = array();
	//foreach($clientids as $clientptr)
	//	$rows[] = clientOutput($clientptr, 'full', $status=null, $target='xml');
//if(mattOnlyTEST())	{echo print_r($rows, 1)."\n\n";exit;	}
	//dumpWorksheet($name, $columns, $rows);
if($TEST) exit;
	$name = 'Inactive Clients';
	$status = "active = 0";
	if($_GET['clientids']) $status .= " AND clientid IN({$_GET['clientids']})";
	$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE $status");
	dumpWorksheetForClients($name, $columns, $clientids);
	//$rows = array();
	//foreach($clientids as $clientptr)
	//	$rows[] = clientOutput($clientptr, 'full', $status=null, $target='xml');
	//dumpWorksheet($name, $columns, $rows);
	
	$name = 'Pets';
	//$status = "active = 0";
	$status = "1=1";
	if($_GET['clientids']) $status = "ownerptr IN({$_GET['clientids']})";
	$petids = fetchCol0("SELECT petid FROM tblpet WHERE $status");
	$rows = array();
	$columns = getPetColumns(null, $withCustomFieldsIfIndicated=1);
	foreach($petids as $petid)
		$rows[] = petOutput($petid, 'full', $status=null, $target='xml');
//if(mattOnlyTEST())	{echo print_r($petids, 1)."\n\n";exit;	}
	dumpWorksheet($name, $columns, $rows);
	
	echo '</ss:Workbook>';
}
	
	
