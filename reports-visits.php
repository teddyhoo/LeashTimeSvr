<? // reports-visits.php
// https://leashtime.com/reports-visits.php?option=visits&client=1602&uncanceled=1

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "invoice-fns.php";
require_once "field-utils.php";

$locked = locked('o-#vr');
extract(extractVars('option,start,end,client,uncanceled', $_REQUEST));
$start = $start ? date('Y-m-d', strtotime($start)) : $start;
$end = $end ? date('Y-m-d', strtotime($end)) : $end;
$allColumns = explodePairsLine("date|date||timeofday|timeofday||clientid|clientid||client|CONCAT_WS(' ', c.fname, c.lname) as client"
														."||sitter|CONCAT_WS(' ', p.fname, p.lname) as sitter"
														.'||service|label as service||totalcharge|charge+ifnull(adjustment,0) as totalcharge'
														.'||pay|rate+ifnull(bonus,0) as pay||starttime|starttime'
														."||status|if(canceled, 'canceled', if(completed, 'complete', 'incomplete')) as status");
$allJoins = explodePairsLine('client|tblclient c ON clientid = clientptr||sitter|tblprovider p ON providerid = providerptr'
															.'||service|tblservicetype ON servicetypeid = servicecode');
if($option == 'visits') {
	foreach(explode(',', 'date,timeofday,client,clientid,service,totalcharge,sitter,pay,status,starttime') as $col)
		$cols[$col] = $allColumns[$col];
	foreach(explode(',', 'client,service,sitter') as $tbl)
		$joins[] = $allJoins[$tbl];
	$result = doQuery($sql = "SELECT ".join(',', $cols)." FROM tblappointment ".joins($joins)
										." WHERE 1=1"
										.($start ? " AND date >= '$start'" : '')
										.($end ? " AND date <= '$end'" : '')
										.($uncanceled ? " AND canceled IS NULL" : '')
										.($client ? " AND clientid = $client" : ''), 1);
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Visits-Report.csv ");
//echo "$sql\n";
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
	dumpCSVRow("");
	dumpCSVRow(array_keys($cols));
	while($row = mysql_fetch_array($result, MYSQL_ASSOC))
		dumpCSVRow($row);
	exit;
}
	
	
function joins($joins) {
	if(!$joins) return;
	return "LEFT JOIN ".join(" LEFT JOIN ", $joins);
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


