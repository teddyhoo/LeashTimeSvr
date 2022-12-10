<? // reports-duplicate-visits.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,go', $_REQUEST));

		
$pageTitle = "Duplicate Visits";
if($start) $pageTitle .= " from ".shortDate(strtotime($start))." to ".shortDate(strtotime($end));

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
?>

	<form name='reportform' method='POST'>
<?
	if(!$start) $start = shortDate();
	if(!($find_sitters || $find_clients || $find_managers || $find_dispatchers)) {
		//$find_sitters = 1;
	}
	$find_sitters = $find_clients = $find_managers = $find_dispatchers = 1;
	calendarSet('Between:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and:', 'end', $end);
	echo " ";
	echoButton('showMessages', 'Find Duplicates', 'find()');
	echo " ";
	//echoButton('showMessages', 'Spreadsheet', 'find("csv")');
	//labeledCheckBox('Login Failures Only', 'failuresonly', $failuresonly, null, null, null, 1);
	hiddenElement('go', '1');
	hiddenElement('csv', '');
?>
	</form>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start','Starting Date','end','Ending Date');
	function find(csv) {
		if(MM_validateForm(
						'start', '', 'R',
						'start', '', 'isDate',
						'end', '', 'R',
						'end', '', 'isDate')) {
				if(csv) document.getElementById('csv').value = 1;
				document.reportform.submit();
				document.getElementById('csv').value = 0;
			}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
if($go) {
	//'start,end,find_sitters,find_clients,find_managers,find_dispatchers,print,providers,sort'
	//										.',recipname,email,subject,body', $_REQUEST));

	$sql = "SELECT appointmentid, clientptr, providerptr, date, timeofday, servicecode 
					FROM tblappointment
					WHERE date >= '".date('Y-m-d', strtotime($start))."' 
						AND date <= '".date('Y-m-d', strtotime($end))."'
					ORDER BY clientptr, date, timeofday, servicecode";
	$result = doQuery($sql);
	$lastRow = null;
	$dups = array();
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if($row['clientptr'] == $lastRow['clientptr'] 
				&& $row['date'] == $lastRow['date'] 
				&& $row['timeofday'] == $lastRow['timeofday'] 
				&& $row['servicecode'] == $lastRow['servicecode']) {
			$dups[$row['clientptr']][$row['appointmentid']] = $row;
			if(!array_key_exists($lastRow['appointmentid'], $dups[$row['clientptr']]))
				$dups[$row['clientptr']][$lastRow['appointmentid']] = $lastRow;
			$clients[] = $row['clientptr'];
			$providers[] = $row['providerptr'];
		}		
		$lastRow = $row;
	}
	$providers = $providers ? getProviderShortNames("WHERE providerid IN (".join(',', array_unique($providers)).")") : array();
	$clients = $clients ? fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid IN (".join(',', array_unique($clients)).")") : array();

	$columns = explodePairsLine('timeofday|Time of Day||service|Service||sitter|Sitter');
	$lastRow = null;
	foreach($dups as $clientptr => $appts) {
		$date = null;
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><th colspan=3 style='background:lightblue'>{$clients[$clientptr]}</th></tr>");
		foreach($appts as $appt) {
			if($date != $appt['date']) {
				$date = $appt['date'];
				$showdate = longDayAndDate(strtotime($appt['date']));
				$rows[] = array('#CUSTOM_ROW#'=>"<tr><th colspan=3>$showdate</th></tr>");
			}
			$appt['service'] = $_SESSION['servicenames'][$appt['servicecode']];
			$appt['sitter'] = $providers[$appt['providerptr']];
			$rows[] = $appt;
		}
	}
	
	if($rows) {
		if(!$csv) {
			echo "<span class='tiplooks'>".count($rows)." duplicate(s) found.</span>";
			tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
		}
		else {
			header("Content-Disposition: attachment; filename=OutboundEmail.csv ");
			dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
			dumpCSVRow($columns);
			foreach($rows as $row) {
				dumpCSVRow($row, array_keys($columns));
			}
		}
	}
	else echo "No duplicates found.";
		
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}

function emailLink($subject, $id) {
	return fauxLink($subject, "openEmail($id)", $noEcho=true, $title=null, $id=null, $class=null, $style=null);
}

function rowSort($a, $b) {
	global $sortKey;
	return strcmp(strtoupper($a[$sortKey]), strtoupper($b[$sortKey]));
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

if(!$csv){

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function openEmail(id) {
	openConsoleWindow("viewemail", "comm-view.php?id="+id,610,600);
}

function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	//var providers = document.getElementById('providers');
	//providers = providers.options[providers.selectedIndex].value;
	document.location.href='reports-logins.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end; //+'&providers='+providers
}

</script>
<? } ?>