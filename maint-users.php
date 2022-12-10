<? // maint-users.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');
extract(extractVars('sort,bizid,bizname,role,status,namePat,bizPat,lookupUserID', $_REQUEST));
$sorts = $sort ? explode('_', $sort) : '';
$roles = explodePairsLine('o|Owner||c|Client||p|Sitter||z|Leashime Maintainer');

$orderBy = !$sorts ? "ORDER BY loginid ASC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
//if($bizid) $filter[] = "bizptr = $bizid";
if($bizname) $filter[] = "bizname = '$bizname'";
if(strpos($role, 'role_') === 0) $role = substr($role, strlen('role_'));
if($role) $filter[] = "rights like '$role%'";
if(strpos($status, 'status_') === 0) $status = substr($status, strlen('status_'));
if($status) $filter[] = "active = ".($status == 'active' ? 1 : '0');
//echo $filter;
	
$safeBizPat = mysql_real_escape_string((string)$bizPat);
if(mattOnlyTEST()) ;
else if($safeBizPat) {
	$filter[] = "bizptr != 0";
	$bizMatch = $bizid ? "WHERE bizid = $bizid" : ($bizPat ? "WHERE bizname LIKE '%$safeBizPat%' OR db LIKE '%$safeBizPat%'" : '');
	$bizzes = fetchAssociationsKeyedBy("SELECT * from tblpetbiz $bizMatch", 'bizid');
	foreach($bizzes as $biz) $bizIds[] =  $biz['bizid'];
	if($bizIds) $filter[] = "bizptr IN (".join(',', $bizIds).")";
}
else $filter[] = "bizptr != 0";
$safeNamePat = mysql_real_escape_string((string)$namePat);
if($namePat) $filter[] = "(loginid LIKE '%$safeNamePat%' 
OR email LIKE '%$safeNamePat%' 
OR lname LIKE '%$safeNamePat%' 
OR fname  LIKE '%$safeNamePat%')";

if($lookupUserID) $filter[] = "userid = '$lookupUserID'";

$filter = $filter ? "WHERE ".join(' AND ',$filter) : '';

if($namePat || $bizPat || $lookupUserID) $users = fetchAssociations($sql = "SELECT tbluser.*, bizname, left(rights,1) as role FROM tbluser LEFT JOIN tblpetbiz ON bizptr = bizid $filter $orderBy");
if(mattOnlyTEST()) echo $sql;
function bizLink($name, $id) {
	return fauxLink($name, "document.location.href=\"maint-edit-biz.php?id=$id\"", 1);
}

function userLink($name, $id) {
	return fauxLink($name, "openConsoleWindow(\"logineditor\", \"maint-edit-user.php?userid=$id\", 600,400)", 1);
}


$dbs = fetchCol0("SHOW databases");
foreach((array)$users as $user)
	if(in_array($user['role'], array('c','p')))
		$bizUsers[$user['bizptr']][$user['role']][] = $user['userid'];
foreach((array)$bizUsers as $bizid => $busers) {
	$biz = $bizzes[$bizid];
//print_r($bizUsers);
//echo $biz['db'].' '.$biz['host'].' '.$biz['dbuser'].' '.$biz['dbpass'].'<br>';
	if(!in_array($biz['db'], $dbs)) continue;
	reconnectPetBizDB($biz['db'], $biz['host'], $biz['dbuser'], $biz['dbpass'], $force=true);
	if($busers['c']) foreach(fetchKeyValuePairs("SELECT userid, email FROM  tblclient WHERE userid IN (".join(',', $busers['c']).")") as $id => $email)
		$emails[$id] = $email;
	if($busers['p']) foreach(fetchKeyValuePairs("SELECT userid, email FROM  tblprovider WHERE userid IN (".join(',', $busers['p']).")") as $id => $email)
		$emails[$id] = $email;
}
include "common/init_db_common.php";

foreach((array)$users as $user) {
	$row = $user;
	$row['fname'] = $row['fname'];
	$row['lname'] = $row['lname'];
	$row['loginid'] = userLink($user['loginid'], $user['userid']);
	$row['bizname'] = bizLink($row['bizname'], $row['bizptr']);
	$row['active'] = $row['active'] ? 'active' : 'INACTIVE';
	$row['role'] = $roles[$row['role']];
	if(!$row['email']) $row['email'] = $emails[$row['userid']];
	$rowClass =	$rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';
	$rowClasses[] =	$rowClass;
			
	$rows[] = $row;
}

$columns = explodePairsLine('fname|Name||lname| |userid|User ID||loginid|Login ID||role|Role||bizname|Biz Name||email|Email||active|Status||rights|Rights');
$columnSorts = array('userid'=>null, 'bizname'=>null, 'active'=>null, 'loginid'=>null, 'role'=>null, 'email'=>null);

$windowTitle = 'System Users';
include 'frame-maintenance.php';
?>
<style>
.biztable td {padding-left:10px;}
</style>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function update() {refresh();}
</script>
<form name='usersearch' method='POST'>
<?
labeledInput('User ID is:', 'lookupUserID', $lookupUserID, null, null, 'filter()');
echo "<br>";
labeledInput('Biz Name contains:', 'bizPat', $bizPat, null, null, 'filter()');
echo "<br>";
labeledInput('User Name/Email/Login ID contains:', 'namePat', $namePat, null, null, 'filter()');
echo "<br>";
//labeledCheckbox($label, $name, $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho
$radios = radioButtonSet('role', $role, array('All'=>0,'Owner'=>'o', 'Sitter'=>'p', 'Dispatcher'=>'d', 'Client'=>'c'),$onClick='filterOnRole(this)', $labelClass=null, $inputClass=null);
echo "<b>Show role:</b> ";
foreach($radios as $radio) echo $radio;
$radios = radioButtonSet('status', $status, array('All'=>0,'Active'=>'active', 'Inactive'=>'inactive'),$onClick='filterOnStatus(this)', $labelClass=null, $inputClass=null);
echo "<img src='art/spacer.gif' height=1 width=10><b>Show status:</b> ";
foreach($radios as $radio) echo $radio;
?>

</form>
<p>
<?
if(!$namePat && !$bizPat && !$lookupUserID) echo "Supply business name pattern or a user name pattern or both";
else if(!$users)  echo "No matches found.";
else {
	echo "Showing ".count($rows)." users.<br>";
	tableFrom($columns, $rows, "", $class='biztable', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);//, 'sortClick'
}
include "refresh.inc";
?>
<script language='javascript'>
function filter() {
	var bizPat = document.getElementById('bizPat').value;
	var namePat = document.getElementById('namePat').value;
	var lookupUserID = document.getElementById('lookupUserID').value;
	var url="/maint-users.php?namePat="+namePat+"&bizPat="+bizPat+"&lookupUserID="+lookupUserID;
	var els = document.usersearch.elements;
	for(var i=0;i<els.length;i++)
		if(els[i].type='radio' && els[i].checked) url+="&"+els[i].name+"="+els[i].value;
	document.location.href=url;
}
function filterOnRole(el) {
	var bizPat = document.getElementById('bizPat').value;
	var namePat = document.getElementById('namePat').value;
	document.location.href="/maint-users.php?namePat="+namePat+"&bizPat="+bizPat+"&sort=<?= $sort ?>&status=<?= $status ?>&role="+el.id;
}
function filterOnStatus(el) {
	var bizPat = document.getElementById('bizPat').value;
	var namePat = document.getElementById('namePat').value;
	document.location.href="/maint-users.php?namePat="+namePat+"&bizPat="+bizPat+"&sort=<?= $sort ?>&role=<?= $role ?>&status="+el.id;
}
</script>