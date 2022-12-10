<? // reports-custom-pricing.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "js-gui-fns.php";

$locked = locked('o-#vr');
$csv = $_GET['csv'];
$pageTitle = "Client Custom Charges";


$sql = "SELECT clientptr as ID, CONCAT_WS(', ', lname, fname) as client,
						label as service,
						cust.charge, 
						if(cust.extrapetcharge= -1.0, '--', cust.extrapetcharge) as 'extra pet',
						if(cust.taxrate= -1.0, '--', cust.taxrate) as 'taxrate', 
						tblclient.active
					FROM relclientcharge cust
					LEFT JOIN tblclient ON clientid = cust.clientptr
					LEFT JOIN tblservicetype ON servicetypeid = servicetypeptr
					ORDER BY client, label";
$data = fetchAssociations($sql);

if(!$data || !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
	if(!$data) echo "<p>No custom client charges found";
	echoButton('', 'Download Spreadsheet', "genCSV()");
	foreach($data as $row) {
		if(!$row['active']) continue;
		if($row['ID'] == $lastId) $row['client'] = '';
		else $lastId = $row['ID'];
		$rows[] = $row;
	}
	$columns = explodePairsLine('ID|ID||client|Client||service|Service||charge|Charge||extra pet|Extra Pet||taxrate|Tax Rate');
	$numCols = count($columns);
	$colClasses = array('charge' => 'dollaramountcell','extrapetcharge' => 'dollaramountcell','taxrate' => 'dollaramountcell'); 
	$headerClass = array('charge' => 'dollaramountheader','extrapetcharge' => 'dollaramountheader','taxrate' => 'dollaramountheader'); //'dollaramountheader'

	if($rows)
		tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);
?>	
		<script language='javascript'>
		setPrettynames('start','Starting Date','end','Ending Date');
		function genCSV() {
			document.location.href='reports-custom-pricing.php?csv=1';
		}
		</script>
<?
// ***************************************************************************
	include "frame-end.html";
}
else { // CSV

	function dumpCSVRow($row) {
		if(!$row) echo "\n";
		if(is_array($row)) echo join(',', array_map('csv',$row))."\n";
		else echo csv($row)."\n";
	}

	function csv($val) {
		$val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
		$val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
		$val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
		return "\"$val\"";
	}

	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Custom-Charges.csv ");
	$columns = explodePairsLine('ID|ID||client|Client||service|Service||charge|Charge||extra pet|Extra Pet||taxrate|Tax Rate');
	dumpCSVRow($columns);
	foreach($data as $row) {
		unset($row['active']);
		dumpCSVRow($row);
	}
}
?>
