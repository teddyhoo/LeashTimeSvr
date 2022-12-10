<? // client-change-history.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

locked('o-');
if(!(staffOnlyTEST() || 
	fetchRow0Col0($sql = "SELECT value FROM tbluserpref WHERE userptr = {$_SESSION['auth_user_id']} 
									AND property = 'clientChangeHistoryEnabled' LIMIT 1", 1))) {
	echo "For LeashTime Staff Use Only<br>$sql";
	exit;
}
extract($_REQUEST);

$client = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $id", 1)
					." (id: $id)";
$clientUserID = fetchRow0Col0("SELECT userid FROM tblclient WHERE clientid = $id", 1)
					." (id: $id)";

$ids = fetchCol0("SELECT packageid FROM tblservicepackage WHERE clientptr = $id", 1);
if($ids) $nrpackclause = "OR (itemtable = 'tblservicepackage' AND itemptr IN (".join(',', $ids)."))";
$ids = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE clientptr = $id", 1);
if($ids) $recpackclause = "OR (itemtable = 'tblrecurringpackage' AND itemptr IN (".join(',', $ids)."))";
$ids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE clientptr = $id", 1);
if($ids) $apptclause = "OR (itemtable = 'tblappointment' AND itemptr IN (".join(',', $ids)."))";
$ezDeleteClause = "OR note LIKE 'Deleted CLIENT[$id] EZ[%'";

$creditCards = fetchKeyValuePairs("SELECT ccid, CONCAT(company, ' ****', last4) FROM tblcreditcard WHERE clientptr = $id");
if($creditCards) $ccclause = "OR (itemtable = 'tblcreditcard' AND itemptr IN (".join(',', array_keys($creditCards)).")) ";

$echeckaccts = fetchKeyValuePairs("SELECT acctid, CONCAT(acctnum, ' ****', last4) FROM tblecheckacct WHERE clientptr = $id");
if($echeckaccts) $echeckclause = "OR (itemtable = 'tblecheckacct' AND itemptr IN (".join(',', array_keys($echeckaccts)).")) ";


$clientMods = fetchAssociations($sql =
	"SELECT * 
		FROM tblchangelog 
		WHERE 
			(itemtable = 'tblclient' AND itemptr = $id)
			$ccclause
			$echeckclause
			$nrpackclause
			$recpackclause
			$apptclause
			$ezDeleteClause
			OR (itemtable = 'clientScheduler'  AND itemptr = $id)
			ORDER BY `time`
			", 1);
			//OR note LIKE '%client: [$id]%'
foreach($clientMods as $i => $mod) {
	$users[$mod['user']] = 0;
	if($mod['itemtable'] == 'tblcreditcard' || $mod['itemtable'] == 'tblecheckacct') {
		$label = $creditCards[$mod['itemptr']];
		if($mod['operation'] == 'c') $label .= ' created';
		$clientMods[$i]['note'] = "$label {$mod['note']}";
	}
	else if($mod['itemtable'] == 'tblappointment') {
			$url = "appointment-edit.php?id={$mod['itemptr']}";
			if(staffOnlyTEST()) 
				$clientMods[$i]['itemptr'] = 
					fauxLink($mod['itemptr'], "openConsoleWindow(\"appteditor\", \"$url\",500,500)", 1, "Edit the visit");
	}
}

//echo "$sql<p".count($clientMods);

require_once "common/init_db_common.php";

$users = fetchKeyValuePairs(
	"SELECT userid, CONCAT_WS(' ', fname, lname) 
		FROM tbluser 
		WHERE userid IN (".join(',', array_keys($users)).")", 1);
foreach($users as $id => $user)
	if($id == $clientUserID) $users[$id] = $client;
		
$cols = explode(',', 'time,itemtable,note,operation,user,itemptr');
foreach($clientMods as $i => $mod) {
	$clientMods[$i]['user'] = $users[$mod['user']];
	$rowClasses[] = 'futuretask';
}

if(!$csv) {
	$windowTitle = "Change History for $client";
	$extraBodyStyle = 'padding:10px;';
	require "frame-bannerless.php";
	echo "<h2>$windowTitle</h2>";
	echo "<form method='POST' name='reportform'>";
	echoButton('', 'Download Spreadsheet', "genCSV()");
	hiddenElement('csv', '');
	echo "</form><p>";
	foreach($cols as $col) $columns[$col] = $col;
	tableFrom($columns, $clientMods, "bgcolor=white border=1 bordercolor=black", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
?>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start','Starting Date','end','Ending Date');
	function genCSV() {
		if(1 || MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			document.getElementById('csv').value=1;
		  document.reportform.submit();
			document.getElementById('csv').value=0;
		}
	}
	</script>
<?
}
else {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-History-$client.csv ");
	dumpCSVRow("Client History: $client");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow($cols);
	foreach($clientMods as $i => $mod) dumpCSVRow($mod, $cols);
}

	function dumpCSVRow($row, $cols=null) {
		if(!$row) echo "\n";
		if(is_array($row)) {
			if($cols) {
				$nrow = array();
				if(is_string($cols)) $cols = explode(',', $cols);
				foreach($cols as $k) $nrow[] = $row[$k];
				$row = $nrow;
			}
			echo join(',', array_map('csv',$row))."\n";
		}
		else echo csv($row)."\n";
	}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}

