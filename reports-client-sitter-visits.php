<? // reports-client-sitter-visits.php

// Invoked from provider-edit.php (Staff Only)
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "gui-fns.php";

$locked = locked('o-#vr');

$clientptr = $_REQUEST['clientptr'];
$csv = $_REQUEST['csv'];

if($csv) {
	$extraField = ", label";
	$extrajoin = "LEFT JOIN tblservicetype ON servicetypeid = servicecode";
}

$sql = "SELECT a.*, CONCAT_WS(',', lname, fname) as sortname, CONCAT_WS(' ', fname, lname) as dispname $extraField
				FROM tblappointment a 
				LEFT JOIN tblprovider ON providerid = providerptr $extrajoin
				WHERE clientptr = $clientptr AND completed IS NOT NULL
				ORDER BY date";

$result = doQuery($sql);

if($csv) {
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
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Sitter-Visits-Report-$clientptr.csv ");
	$columns = explodePairsLine('date|Date||timeofday|Time||dispname|Sitter||label|Service');
	dumpCSVRow($columns, $cols=null);
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$providerptr = $row['providerptr'];
		$row['date'] = shortDate(strtotime($row['date']));
		dumpCSVRow($row, array_keys($columns));
	}
}
else if(!$csv) {
	$providers[0]['name'] = '<b>Total</b>';
//print_r(fetchAssociations($sql));	
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$providerptr = $row['providerptr'];
		$date = shortDate(strtotime($row['date']));

		if(!$providers[0]['first']) $providers[0]['first'] = $date;
		$providers[0]['last'] = $date;
		$providers[0]['count'] += 1;

		$providers[$providerptr]['name'] = $row['dispname'];
		$providers[$providerptr]['sortname'] = $row['sortname'];
		if(!$providers[$providerptr]['first']) $providers[$providerptr]['first'] = $date;
		$providers[$providerptr]['last'] = $date;
		$providers[$providerptr]['count'] += 1;
	}

	function compProvs($a, $b) {return strcmp($a['sortname'], $b['sortname']);}

	usort($providers, 'compProvs');

	require_once "frame-bannerless.php";

	echo "<h2>Sitters Who Have Served</h2>";
	
	fauxLink('Download Spreadsheet', "parent.location.href=\"reports-client-sitter-visits.php?clientptr=$clientptr&csv=1\";", false, 'Dump all visits to a spreadsheet');
	
	echo "<p>";

	$columns = explodePairsLine('name|Sitter||count|# Visits||first|First Visit||last|Last Visit');

	tableFrom($columns, $providers, $attributes='BORDER = 1 BORDERCOLOR=gray', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}