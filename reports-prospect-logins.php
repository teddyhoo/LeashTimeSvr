<? // reports-prospect-logins.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-flag-fns.php";
locked('o-');

include "frame.html";
if(!dbTEST('leashtimecustomers')) {
	echo "For LeashTime Use Only";
	exit;
}
$lastMonths = 2;

if($_POST) {
	foreach($_POST as $key => $unused)
		if(strpos($key, 'biz_') === 0) {
			$vals =  explode('_', $key);
			$reportBizzes[$vals[1]] = array('bizid'=>$vals[1], 'mgr'=>$vals[2]);
			$bizzesByMgr[strtoupper($vals[2])] = $vals[1];
		}
}

echo "<h2>Prospect Logins</h2>";
echoButton('', 'Report', 'genReport()'); echo " (only logins in the last six months are considered)";
?>
<table width=100%>
<tr><td valign='top'>
<form name='report' method='POST'>
<table>
<?
$months = $months ? $_REQUEST['months'] : '6';
checkboxRow('Show only prospects', 'hideNonProspects', $value=$_POST['hideNonProspects'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, 'hideShow()');
checkboxRow('Hide Dead Leads', 'hideDeadLeads', $value=$_POST['hideDeadLeads'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, 'hideShow()');
labeledInput('Look back months:', 'months', $months, $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=null, $noEcho=false);

$allBizClients = fetchAssociationsKeyedBy(
	"SELECT clientid, garagegatecode as bizptr, prospect, active
		FROM tblclient WHERE 1=1 
		ORDER BY bizptr DESC", 'clientid');

foreach($allBizClients as $clientid => $clientBiz) {
	$flags[$clientBiz['bizptr']] = clientFlagPanel($clientid, $officeOnly=false, $noEdit=true, $contentsOnly=false);
	foreach(getClientFlags($clientid) as $flag) $clientsFlagIds[$clientBiz['bizptr']] = $flag['flagid'];
	$inactiveClients[$clientBiz['bizptr']] = !$clientBiz['active'];
	$prospectClients[$clientBiz['bizptr']] = $clientBiz['prospect'];
	$clientIdsByBizId[$clientBiz['bizptr']] = $clientid;
}

//if(mattOnlyTEST()) {print_r($clientIdsByBizId[558]);exit;}

require "common/init_db_common.php";
$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE test = 0 AND activebiz = 1 ORDER BY bizid DESC", 'bizid');
foreach($bizzes as $bizid => $biz) $bizzes[$bizid]['prospect'] = $prospectClients[$bizid];

//echo print_r($bizzes[479], 1).'<p>'.print_r($bizzes[478], 1).'<p>==>'.bizComp($bizzes[479], $bizzes[478]);exit;
function bizComp(&$a, &$b) {  // WHY DOESN'T THIS WORK?!
//if($a['bizid'] == 479) {echo print_r($a, 1).'<p>'.print_r($b, 1);exit;}
	return (!$b['prospect'] && $a['prospect']) ? 1 : (
					$a['bizid'] < $b['bizid'] ? 1 : (
					$a['bizid'] > $b['bizid'] ? -1 : 0));
}

function getHomePageURL($biz) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	if(!mysql_error())
		$url = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizHomePage' LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $url;
}

if(!uasort($bizzes, 'bizComp')) echo "BANG!";

$mgrs = fetchAssociationsKeyedBy(
		"SELECT * 
			FROM tbluser
			WHERE isowner = 1
			ORDER BY userid DESC", 'bizptr');  // Last in trumps others, so sort high -> low
foreach($mgrs as $mgr) $mgrLoginIDs[] = $mgr['loginid'];

if($reportBizzes) {
	foreach($reportBizzes as $bizid =>$biz) {
		$rptmgrs[$biz['mgr']] = fetchRow0Col0("SELECT loginid FROM tbluser WHERE userid = '{$biz['mgr']}' LIMIT 1", 1);
		$bizIdsByLoginid[strtoupper($rptmgrs[$biz['mgr']])] = $biz['bizid'];
		$reportBizIds[] = $bizid;
		$reportBizzes[$bizid]['detail'] = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1");
	}
}

$lastLogins = fetchKeyValuePairs("SELECT UPPER(loginid), LastUpdateDate FROM tbllogin WHERE loginid IN ('".join("', '", $mgrLoginIDs)."') ORDER BY LastUpdateDate");
//print_r($lastLogins);
//if(mattOnlyTEST()) echo "FRESHPRINTS: {$lastLogins['FRESHPRINTS']}";
foreach($bizzes as $bizid => $biz) {
	$mgr = $mgrs[$bizid];
	$name = "biz_{$bizid}_{$mgr['userid']}";
	$title = safeValue("({$bizid}) {$mgr['fname']} {$mgr['lname']} <{$mgr['email']}> (login: {$mgr['loginid']})");
	$flagPanel = array_key_exists($bizid, $flags) 
		? $flags[$bizid] : (
		$inactiveClients[$bizid] ?  '<span style="font-style:italic">inactive LT Cust</span>'
		:'<span class="warning" title="No LT customer found with garage code $bizid">NO LEASHTIME CUSTOMER</span>');
	$checked = $bizzesByMgr[strtoupper($mgr['userid'])] ? 'CHECKED' : '';
//print_r($biz);exit;	
	$prospectBox = $biz['prospect'] ? '[P] ' : '';
	$class = array();
	if($biz['prospect']) $class['prospect'] = 1;
	foreach((array)$clientsFlagIds[$bizid] as $flagId) {
		if($flagId == 8) $class['deadlead'] = 1;
		if($flagId == 3) $class['prospect'] = 1;
	}
	if(!$class['prospect']) $class['nonprospect'] = 1;
	$class = join(' ', array_keys($class));	
	$lastLoginDate = $lastLogins[strtoupper($mgr['loginid'])];
	if('12/31/1969' == shortDate(strtotime($lastLoginDate))) $lastLoginDate = "<span style='color:red' title='Never logged in'><b>NEVER</b></span> [".strtoupper($mgr['loginid'])."]";
	else if($lastLoginDate < date('Y-m-d H:i:s', strtotime("-14 days")))
		$lastLoginDate = "<span style='color:red' title='Last owner login date'>".shortDate(strtotime($lastLoginDate))."</span>";	
	else $lastLoginDate = "<span title='Last owner login date'>".shortDate(strtotime($lastLoginDate))."</span>";
//if(mattOnlyTEST()) echo print_r(	$biz, 1);
	$url = getHomePageURL($biz);
	$homelink = $url ? "<a target='bizhomepage' href='$url'>$url</a>" : 'NO WEB PAGE';
//if(mattOnlyTEST()) {echo "getHomePageURL($bizid)): $url";exit;}

	$clientlink = $clientIdsByBizId[$bizid] 
					? "<a target='leashtimeclient' href='client-edit.php?id={$clientIdsByBizId[$bizid]}'>{$biz['bizname']}</a>"
							.($inactiveClients[$bizid] ? '(inactive)' : '')
					: "<span title='no LeashTime Client found with garage code $bizid.'>($bizid) {$biz['bizname']}</span>";
	echo "<tr class='$class'>
	  <td title='$title' colspan=2><input type='checkbox' $inputClass id='$name' name='$name' $checked $onChange> 
	   <label for='$name'>$clientlink {$flagPanel}</label> $lastLoginDate $homelink</td></tr>\n";
}

?>
</table></form></td><td id='detail' valign='top'>
<?
$mgrCsv = strtoupper(join("','", (array)$rptmgrs));
$start = date('Y-m-d H:i:s', strtotime("-$months months"));
$sql=
	"SELECT TRIM(UPPER(loginid)) as loginid, LastUpdateDate
		FROM tbllogin
		WHERE LastUpdateDate >= '$start'
			AND UPPER(loginid) IN ('$mgrCsv')
			ORDER BY LastUpdateDate DESC";
//echo $sql;
$result = doQuery($sql);
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$date = date('m/d/Y', strtotime($row['LastUpdateDate']));
	$bizid = $bizIdsByLoginid[$row['loginid']];
//echo "({$row['loginid']}) $bizid: ".print_r($row, 1)."<hr>";
	if(!$logins[$bizid]) $logins[$bizid][$date] = 1;
	else if($logins[$bizid][$date]) continue;
	else {
		$lastDate = current(array_keys($logins[$bizid]));
		if(strtotime($date) < strtotime("-$lastMonths month", strtotime($lastDate))) continue;
		else $logins[$bizid][$date] = 1;
	}
}

//echo 'bizIdsByLoginid: '.print_r($bizIdsByLoginid, 1)."<hr>logins: ".print_r($logins,1);
foreach((array)$bizzesByMgr as $mgr => $bizid) {
	$dates = $logins[$bizid];
	//$mgr = $reportBizzes[$bizid]['mgr'];
	$biz = $reportBizzes[$bizid]['detail'];
	echo "<hr><b>{$biz['bizname']}</b><br>Activated: ".date('m/d/Y', strtotime($biz['activated']))."<br>Last $lastMonths months of logins by {$rptmgrs[$mgr]}<br>";
	$first = 1;
	if(!$dates) echo "<i>none</i><br>";
	foreach((array)$dates as $date=>$one) {
		echo $first ? "<b>$date</b><br>" : "$date<br>";
		$first = false;
	}
}

	
?>
</td></tr></table>
<script language='javascript' src='check-form.js'></script>

<script language='javascript'>
function genReport() {
	var checked, els = document.report.elements;
	for(var i=0; i< els.length; i++)
		if(els[i].checked) {
			checked = true;
			break;
		}
	checked = !checked ? 'Please select at least one business' : false;
	if(MM_validateForm(checked, '', 'MESSAGE'))
		document.report.submit();
}


function hideShow() {
	var tableRowShow = '<?= $_SESSION['tableRowDisplayMode'] ?>';
	var hideNonProspects = $('#hideNonProspects')[0].checked;
	var hideDeadLeads = $('#hideDeadLeads')[0].checked;
	$('.nonprospect').css('display', (hideNonProspects ? 'none' : tableRowShow));
	$('.deadlead').css('display', (hideDeadLeads ? 'none' : tableRowShow));
	// if deadlead selected, hide all deadleads, even those that are propects
}

hideShow();
</script>
<?
include "frame-end.html";
