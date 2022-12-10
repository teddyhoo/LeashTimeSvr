<? // reports-email-outbound.php

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
extract(extractVars('start,end,limit,find_sitters,find_clients,find_managers,find_dispatchers,print,providers,sort'
										.',recipname,mgrname,email,subject,body,go,csv', $_REQUEST));

$pageTitle = "Outbound Email Report <font color=red></font>";


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
	echoButton('showMessages', 'Find Messages', 'find()');
	echo " ";
	labeledSelect('Limit:', 'limit', $limit, array('500 messages'=>500, '1000 messages'=>1000, 'All messages'=>0));
	echo " ";
	echoButton('showMessages', 'Spreadsheet', 'find("csv")');
	//labeledCheckBox('Login Failures Only', 'failuresonly', $failuresonly, null, null, null, 1);
	hiddenElement('go', '1');
	hiddenElement('csv', '');
?>
	</form>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start,end','Starting Date');
	function find(csv) {
		if(MM_validateForm(
						'start', '', 'isDate',
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

	if($start) $ands[] = "datetime >= '".date('Y-m-d', strtotime($start))."'";
	if($end) $ands[] = "datetime <= '".date('Y-m-d', strtotime($end))." 23:59:59'";
	if($find_sitters) $roles[] = 'tblprovider';
	if($find_clients) $roles[] = 'tblclient';
	if($find_clients) $roles[] = 'tbluser';	
	//if($roles) $ands[] = "correstable IN ('".join("','", $roles)."')";
	if($email) $ands[] = "correspaddr LIKE '%$email%'";
	if($body) $ands[] = "body LIKE '%$body%'";
	if($subject) $ands[] = "subject LIKE '%$subject%'";
	if($find_managers && $mgrname) $ands[] = "mgrname LIKE '%$mgrname%'";
//echo "[$find_managers] [$mgrname]";	
	$ands = join(' AND ', $ands);
	if($ands) $where[] = "($ands)";
	//if($mgrclause) $where[] = $mgrclause;
	$where = "WHERE inbound = 0 AND transcribed IS NULL ".($where ? "AND ".join(' OR ', $where) : '');
	$limit = $limit ? "LIMIT $limit" : '';
	$sql = "SELECT tblmessage.* FROM tblmessage $where ORDER BY datetime DESC $limit";
	$result = doQuery($sql);
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if($row['correstable'] == 'tblprovider') $providers[] = $row['correspid'];
		else if($row['correstable'] == 'tblclient') $clients[] = $row['correspid'];
		else if($row['correstable'] == 'tbluser') $users[] = $row['correspid'];
	}
	$providers = $providers ? getProviderShortNames("WHERE providerid IN (".join(',', array_unique($providers)).")") : array();
	$clients = $clients ? fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid IN (".join(',', array_unique($clients)).")") : array();
	if($users) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$users = fetchAssociationsKeyedBy("SELECT userid, CONCAT_WS(' ', fname, lname) as name, rights FROM tbluser WHERE userid IN (".join(',', array_unique($users)).")", 'userid');
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	}
	else $users = array();

	$result = doQuery($sql);
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$table = $row['correstable'];
		$name = $table == 'tblprovider' ? $providers[$row['correspid']] : (
						$table == 'tblclient' ? $clients[$row['correspid']] : (
						$table == 'tblclientrequest' ? clientRequestFullName($row['correspid']) : (
						$users[$row['correspid']]['name']
						)));
		if($recipname && strpos($name, $recipname) == FALSE) continue;
		$row['name'] = "$name ({$row['correspaddr']})";
		if($table == 'tblclientrequest') $row['role'] = 'prospect';
		else {
			$rights = $users[$row['correspid']]['rights'];
			$row['role'] = $table == 'tblprovider' ? 'sitter' : (
								$table == 'tblclient' ? 'client' : (
								strpos($rights, 'o') === 0 ? 'manager' : (
								strpos($rights, 'd') === 0 ? 'dispatcher' : '?')));
							}
		$row['subject'] = $row['subject'] ? $row['subject'] : '--no subject--';
		$row['subject'] = !$csv ? emailLink($row['subject'], $row['msgid']) : $row['subject'];
		$row['sorttime'] = $row['datetime'];
		$row['datetime'] = shortDateAndTime(strtotime($row['datetime']));
		$rows[] = $row;
	}
//echo ">>>";print_r($allPayments);	
	
	if($rows) {
		$columns = explodePairsLine('datetime|Date / time||name|Name||role|Role||subject|Subject||mgrname|Manager');
		//$columnSorts = array('displaytime'=>1,'person'=>1,'role'=>1,'success'=>1);
		if(!$csv) {
			echo "<span class='tiplooks'>".count($rows)." message(s) found.</span>";
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
	else echo "No messages found.";
		
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}

function clientRequestFullName($id) {
	return fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclientrequest WHERE requestid = $id LIMIT 1", 1);
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