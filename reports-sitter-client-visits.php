<? // reports-sitter-client-visits.php

// Invoked from provider-edit.php (Staff Onlye)
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "gui-fns.php";

$locked = locked('o-#vr');

$prov = $_REQUEST['prov'];
$csv = $_REQUEST['csv'];

if($csv) {
	$extraField = ", label";
	$extrajoin = "LEFT JOIN tblservicetype ON servicetypeid = servicecode";
}

$sql = "SELECT a.*, CONCAT_WS(',', lname, fname) as sortname, CONCAT_WS(' ', fname, lname) as dispname, street1, city, state, zip $extraField
				FROM tblappointment a 
				LEFT JOIN tblclient ON clientid = clientptr $extrajoin
				WHERE providerptr = $prov AND completed IS NOT NULL
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
	$provider = fetchFirstAssoc(
		"SELECT nickname, CONCAT_WS(' ', fname, lname) as fullname 
			FROM tblprovider 
			WHERE providerid = $prov");
	$providerName = $provider['nickname'] ? $provider['nickname'] : $provider['fullname'];
	$providerName = preg_replace("/^[\W0-9_]/", "", str_replace(' ', '_', $providerName));
	header("Content-Disposition: inline; filename=Sitter-Visits-$providerName.csv ");
	$columns = explodePairsLine('date|Date||timeofday|Time||dispname|Client||label|Service||street1|Street||city|City||state|State||zip|Zip');
	dumpCSVRow($columns, $cols=null);
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$clientptr = $row['clientptr'];
		$row['date'] = shortDate(strtotime($row['date']));
		dumpCSVRow($row, array_keys($columns));
	}
}
else if(!$csv) {
	$total['name'] = '<b>Total</b>';
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$clientptr = $row['clientptr'];
		$date = shortDate(strtotime($row['date']));

		if(!$total['first']) $total['first'] = $date;
		$total['last'] = $date;
		$total['count'] += 1;

		$clients[$clientptr]['name'] = $row['dispname'];
		$clients[$clientptr]['sortname'] = $row['sortname'];
		if(!$clients[$clientptr]['first']) $clients[$clientptr]['first'] = $date;
		$clients[$clientptr]['last'] = $date;
		$clients[$clientptr]['count'] += 1;
	}

	function compClients($a, $b) {return strcmp($a['sortname'], $b['sortname']);}
	function compCounts($a, $b) {return $a['count'] > $b['count'] ? -1 : (
																			 $a['count'] < $b['count'] ? 1 : 0);}
																			 
	function compFirsts($a, $b) {$aFirst = strtotime($a['first']); $bFirst = strtotime($b['first']);
																return $aFirst < $bFirst ? -1 : (
																			 $aFirst > $bFirst ? 1 : 0);}
	function compLasts($a, $b) {$aFirst = strtotime($a['first']); $bFirst = strtotime($b['first']);
																return $aFirst > $bFirst ? -1 : (
																			 $aFirst < $bFirst ? 1 : 0);}
	$sort = "{$_GET['sort']}";
	if($sort[0] == '-') {
		$reverse = 1;
		$sort = substr($sort, 1);
	}
	if(!$_GET['sort'] || $sort == 'name') usort($clients, 'compClients');
	else if($sort == 'count') usort($clients, 'compCounts');
	else if($sort == 'first') usort($clients, 'compFirsts');
	else if($sort == 'last') usort($clients, 'compLasts');
	if($reverse) $clients = array_reverse($clients);
	$clients = array_merge(array($total), $clients);

	require_once "frame-bannerless.php";

	echo "<h2>Clients Served</h2>";
	
	fauxLink('Download Details Spreadsheet', "parent.location.href=\"reports-sitter-client-visits.php?prov=$prov&csv=1\";", false, 'Dump all visits to a spreadsheet');
	
	echo "<p>";
	
	$SORTA = staffOnlyTEST();
	
	$sort = !$_GET['sort'] || $_GET['sort'] == 'name' ? '-name' : 'name';
	$clientsLink = fauxLink('Client', "document.location.href=\"?prov=$prov&sort=$sort\"", 1, 'Sort by client name');
	$sort = $_GET['sort'] == 'count' ? '-count' : 'count';
	$visitsLink = fauxLink('# Visits', "document.location.href=\"?prov=$prov&sort=$sort\"", 1, 'Sort by visits');
	$sort = $_GET['sort'] == 'first' ? '-first' : 'first';
	$firstsLink = fauxLink('First Visit', "document.location.href=\"?prov=$prov&sort=$sort\"", 1, 'Sort by first visit');
	$sort = $_GET['sort'] == 'last' ? '-last' : 'last';
	$lastsLink = fauxLink('Last Visit', "document.location.href=\"?prov=$prov&sort=$sort\"", 1, 'Sort by last visit');
	if(!$SORTA) $visitsLink = strip_tags($visitsLink);
	if(!$SORTA) $firstsLink = strip_tags($firstsLink);
	if(!$SORTA) $lastsLink = strip_tags($lastsLink);
	if(!$SORTA) $clientsLink = strip_tags($lastsLink);
	$columns = explodePairsLine("name|$clientsLink||count|$visitsLink||first|$firstsLink||last|$lastsLink");

	tableFrom($columns, $clients, $attributes='BORDER = 1 BORDERCOLOR=gray', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}