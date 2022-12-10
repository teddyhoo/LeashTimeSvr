<? // reports-email-archived.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "archive-fns.php";
require_once "preference-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
// 
extract(extractVars('start,end,limit,find_sitters,find_clients,find_managers,find_dispatchers,print,providers,sort'
										.',recipname,mgrname,email,subject,body,go,csv,clientid,providerid,userid,origclientid,origproviderid,group', $_REQUEST));
if(!array_key_exists('limit', $_REQUEST)) $limit = 100;
//clientid,providerid
if($clientid > 0) {
	$person = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = $clientid LIMIT 1");
	$origperson = $person;
	$find_clients = 1;
}
else if($providerid > 0) {
	$person = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE providerid = $providerid LIMIT 1");
	$origperson = $person;
	$find_sitters = 1;
}
else if($userid > 0) {
	$mgrs = getManagers(array($user), $ltStaffAlso=false);
	$person = $mgrs[$userid];
	$origperson = $person;
	$find_users = 1;
}
else if($group == 'clients') {
	$find_clients = 1;
}
else if($group == 'providers') {
	$find_sitters = 1;
}

if($origclientid && !$clientid)
	$origperson = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = $origclientid LIMIT 1");
if($origproviderid && !$providerid)
	$origperson = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE providerid = $origproviderid LIMIT 1");
if($origproviderid && !$userid)
	$origperson = "{$mgr['fname']} {$mgr['lname']}";

$target = $clientid ? "client {$person['name']}" : (
					$providerid ? "sitter {$person['name']}" : (
					$userid ? "manager {$person['name']}" : (
					$find_clients ? "All Clients" : (
					$find_sitters ? "All Sitters" : "Everyone"))));
	
$pageTitle = "Archived Message Report for $target";

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";
	if($clientid || $origclientid) $breadcrumbs .= " - <a href='client-edit.php?id=".($clientid ? $clientid : $origclientid)."'>{$origperson['name']} Client Profile</a>";
	if($providerid || $origproviderid) $breadcrumbs .= " - <a href='provider-edit.php?id=".($providerid ? $providerid : $origproviderid)."'>{$origperson['name']} Sitter Profile</a>";
	include "frame.html";
	// ***************************************************************************
	$breadcrumbs = "Show messages for: "; 
	if(!$clientid && $origclientid) $breadcrumbs .= " - ".fauxLink("{$origperson['name']} Only", "switchTo(\"clientid\", $origclientid)", 'noecho');
	if(!$providerid && $origproviderid) $breadcrumbs .= " - ".fauxLink("{$origperson['name']} Only", "switchTo(\"providerid\", $origproviderid)", 'noecho');
	if($group != 'clients') $breadcrumbs .= " - ".fauxLink('All Clients', "switchTo(\"group\", \"clients\")", 'noecho');
	if($group != 'providers') $breadcrumbs .= " - ".fauxLink('All Sitters', "switchTo(\"group\", \"providers\")", 'noecho');
	if($group || $person) $breadcrumbs .= " - ".fauxLink('Everyone', "switchTo(\"group\", \"\")", 'noecho');
	$latestArchiveDate = lastArchivedMessageDateTime();
	if($latestArchiveDate) $latestArchiveDate = "Most recent archived message: ".shortDate(strtotime($latestArchiveDate));
	echo "<table style='border-width:0px;width:100%;'><tr><td>$breadcrumbs</td><td style='text-align:right'>$latestArchiveDate</td></tr></table>";
?>
	<p>
	<form name='reportform' action='reports-email-archived.php' method='POST'>
<?
	if(!$end) $end = shortDate(strtotime(getPreference('latestarchivedmessagedate')));
	//if(!$start) $start = shortDate();
	
	
	
	//if(!($find_sitters || $find_clients || $find_managers || $find_dispatchers)) {
		//$find_sitters = 1;
	//}
	//$find_sitters = $find_clients = $find_managers = $find_dispatchers = 1;
	calendarSet('Between:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and:', 'end', $end);
	echo " ";
	echoButton('showMessages', 'Find Messages', 'find()');
	echo " ";
	labeledSelect('Limit:', 'limit', $limit, array('100 messages'=>100, '500 messages'=>500, '1000 messages'=>1000, '5000 messages'=>5000));
	echo " ";
	//echoButton('showMessages', 'Spreadsheet', 'find("csv")');
	//labeledCheckBox('Login Failures Only', 'failuresonly', $failuresonly, null, null, null, 1);
	hiddenElement('go', '1');
	hiddenElement('csv', '');
	hiddenElement('origclientid', ($clientid ? $clientid : $origclientid));
	hiddenElement('origproviderid', ($providerid ? $providerid : $origproviderid));
	hiddenElement('clientid', $clientid);
	hiddenElement('providerid', $providerid);
	hiddenElement('group', $group);
?>
	</form>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start,end','Starting Date');
	
	function switchTo(mode, value) {
		document.getElementById('group').value = '';
		document.getElementById('clientid').value = '';
		document.getElementById('providerid').value = '';
		if(mode == 'group') document.getElementById('group').value = value;
		else if(mode == 'clientid') document.getElementById('clientid').value = value;
		else if(mode == 'providerid') document.getElementById('providerid').value = value;
		find();
	}
	
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

function viewRequestLink($subject, $msgid, $title) {
	return fauxLink($subject, "openConsoleWindow(\"viewrequest\", \"request-edit.php?id=$msgid\",610,500)", 1, $title);
}

if($go) {
	//'start,end,find_sitters,find_clients,find_managers,find_dispatchers,print,providers,sort'
	//										.',recipname,email,subject,body', $_REQUEST));

	$providers = getProviderShortNames();;
	if($start) $ands[] = "datetime >= '".date('Y-m-d 00:00:00', strtotime($start))."'";
	if($end) $ands[] = "datetime <= '".date('Y-m-d', strtotime($end))." 23:59:59'";
	$dateFilter = $ands;
	if($find_sitters) $roles[] = 'tblprovider';
	if($find_clients) $roles[] = 'tblclient';
	if($find_clients || $find_users) $roles[] = 'tbluser';	
	if($roles) $ands[] = "correstable IN ('".join("','", $roles)."')";
	if($clientid) $ands[] = "correspid = '$clientid'";
	if($providerid) $ands[] = "correspid = '$providerid'";
	if($userid) $ands[] = "correspid = '$userid'";
	if($email) $ands[] = "correspaddr LIKE '%$email%'";
	if($body) $ands[] = "body LIKE '%$body%'";
	if($subject) $ands[] = "subject LIKE '%$subject%'";
	if($find_managers && $mgrname) $ands[] = "mgrname LIKE '%$mgrname%'";
//echo "[$find_managers] [$mgrname]";	
	$ands = join(' AND ', $ands);
	if($ands) $where[] = "($ands)";
	//if($mgrclause) $where[] = $mgrclause;
	$where = "WHERE ".($where ? join(' OR ', $where) : ''); //"AND ".inbound = 0 AND transcribed IS NULL 
	$limitClause = $limit ? "LIMIT $limit" : '';
	$totalfound =  fetchRow0Col0("SELECT count(*) FROM tblmessagearchive $where");
	$sql = "SELECT tblmessagearchive.* FROM tblmessagearchive $where ORDER BY datetime ASC $limitClause";
//echo $sql;	
	$result = doQuery($sql);
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$rowCount += 1;
		if($row['correstable'] == 'tblclient') $clients[] = $row['correspid'];
		else if($row['correstable'] == 'tbluser') $users[] = $row['correspid'];
	}
	$clients = $clients ? fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid IN (".join(',', array_unique($clients)).")") : array();
	if($users) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$users = fetchAssociationsKeyedBy("SELECT userid, CONCAT_WS(' ', fname, lname) as name, rights FROM tbluser WHERE userid IN (".join(',', array_unique($users)).")", 'userid');
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	}
	else $users = array();

	if($rowCount > 0) mysql_data_seek($result,0);
	//$result = doQuery($sql);
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
	
	// REQUESTS
	require_once "request-fns.php";
	
	// DO NOT SHOW REQUESTS AFTER THE END DATE SUPPLIED OR AFTER THE THRESHOLD FOR THE ARCHIVE,
	// WHICHEVER IS EARLIER

	$requestFilter = array();
	$requestFilter[] = $providerid ? "providerptr = $providerid" : (
									$clientid ? "clientptr = $clientid" : "1=1");
	if($start) $dateFilter[] = "datetime >= '".date('Y-m-d 00:00:00', strtotime($start))."'";
	$threshold = $_SESSION['preferences']['archivedmessagethresholddate'];
	if($end) {
		$endTime = $threshold ? min(strtotime($end), strtotime($threshold)) : strtotime($end);
		$dateFilter[] = "datetime <= '".date('Y-m-d', $endTime)." 23:59:59'";
	}
	else if($threshold)	$dateFilter[] = "datetime <= '".date('Y-m-d', strtotime($threshold))." 23:59:59'";

									
	if($dateFilter) $requestFilter[] = str_replace('datetime', 'received', join(" AND ", $dateFilter));
	$requestFilter = join(" AND ", $requestFilter);
	foreach(($requestsFound = fetchAssociations("SELECT * FROM tblclientrequest WHERE $requestFilter")) as $req) {
		$sender = join(' ',array($req['fname'], $req['lname']));
		if(!trim($sender)) $sender = $person['name'];
		if($req['requesttype'] == 'Reminder') $subject = "Reminder: {$req['street1']}";
		else $subject = "Request: {$requestTypes[$req['requesttype']]}";
		$title = 'View this request.';
		if($clientid && $req['providerptr']) {
			require_once "provider-fns.php";
			$pname = $providers[$req['providerptr']];
			$title = "View this request. (Submitted by $pname)";
			$subject = "Request: {$requestTypes[$req['requesttype']]} (*)";
		}
		else if(!$clientid) {
			require_once "client-fns.php";
			$client = getClient($req['clientptr']);
			$title = "View this request. (For client: {$client['fname']} {$client['lname']})";
			$subject = "Request: {$requestTypes[$req['requesttype']]} (*)";
		}
		$rows[] = array('datetime'=>shortDateAndTime(strtotime($req['received'])),
									'sorttime'=>$req['received'],
									'subject'=>viewRequestLink($subject, $req['requestid'], $title),
									'sortsubject'=>$subject,
									'name' =>$sender,
									'listid'=>count($rows));
		/*if($totalMsg) {
			$comms[count($comms)-1]['body'] = "https://{$_SERVER["HTTP_HOST"]}/request-edit.php?id={$req['requestid']}&updateList=";
			$comms[count($comms)-1]['type'] = "request";
		}*/
	}
	// END REQUESTS
	
	
	
	function sortBySortttime($a, $b) {return strcmp($a['sorttime'], $b['sorttime']); }
	if($rows) usort($rows, 'sortBySortttime');
	
	$totalfound += count($requestsFound);
	$totalfound =  $totalfound ? $totalfound : 'None';
	$totalshownInt =  $limit ? min($totalfound, $limit) : $totalfound;
	$totalshown =  $totalshownInt ? $totalshownInt : 'None';

//echo ">>>";print_r($allPayments);	
	
	if($rows) {
		$rows = array_slice ($rows , 0, $totalshownInt);
		$columns = explodePairsLine('datetime|Date / time||name|Name||role|Role||subject|Subject||mgrname|Manager');
		if($group || $person) unset($columns['role']);
		//$columnSorts = array('displaytime'=>1,'person'=>1,'role'=>1,'success'=>1);
		if(!$csv) {
			echo "<span class='tiplooks'>$totalfound message(s) found. $totalshown shown.</span>";
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