<? // maint-log.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');
extract(extractVars('sort,bizdb,start,end,reverse', $_REQUEST));
if($reverse) $sort = "time ASC";
$sorts = $sort ? explode('_', $sort) : '';

$logins = fetchKeyValuePairs("SELECT userid, loginid FROM tbluser");
$dbs = fetchKeyValuePairs("SELECT db, db FROM tblpetbiz ORDER BY db");
$dbs = array_merge(array('Pick a biz'=>''), $dbs);
$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
if($bizdb) {
	if($start) $filter[] = "time >= '".date('Y-m-d', strtotime($start))."'";
	if($end) $filter[] = "time <= '".date('Y-m-d 23:59:59', strtotime($end))."'";
	if(!$start && !$end) "TO_DAYS(NOW()) - TO_DAYS(time) < 2";
	$filter = $filter ? "WHERE ".join(' AND ',$filter) : '';
	$limit = $_REQUEST['limit'] ? $_REQUEST['limit'] : '5000';
	$result = doQuery("SELECT time, message FROM $bizdb.tblerrorlog $filter $orderBy LIMIT $limit");
}

function userLink($name, $id) {
	return fauxLink($name, "openConsoleWindow(\"logineditor\", \"maint-edit-user.php?userid=$id\", 600,400)", 1);
}



if($result) while($line = mysql_fetch_assoc($result)) {
	$row = $line;
	$line['time'] = date('m/d/Y H:i', strtotime($line['time']));
	//$row['user'] = $line['userptr'];
	$rowClass =	$rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';
	$rowClasses[] =	$rowClass;

	$rows[] = $row;
}
$columns = explodePairsLine('time|Time||message|Message'); //||user|User');
$columnSorts = array('time'=>null, 'message'=>null /*, 'user'=>null*/);

$windowTitle = 'Error Log';
include 'frame-maintenance.php';
?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
selectElement('Business:', 'bizdb', $bizdb, $dbs, "");
calendarSet('Starting:', 'start', $start, null, null, true, 'end');
calendarSet('Starting:', 'end', $end, null, null, true);
hiddenElement('reverse', '');
echoButton('', 'Latest', "go(null)");
echo " ";
echoButton('', 'Earliest', "go(\"reverse\")");
echo "<p>";
if($rows) tableFrom($columns, $rows, "", $class='biztable', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);//, 'sortClick'

include "refresh.inc";
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function go(reverse) {
	var reverse = reverse && typeof reverse != undefined ? '&reverse=1' : '';
	document.getElementById('reverse').value=1;
	var dates = "<?= "&limit=$limit" ?>&start="+escape(document.getElementById("start").value)+"&end="+escape(document.getElementById("end").value);
	document.location.href="maint-log.php?bizdb="+document.getElementById("bizdb").options[document.getElementById("bizdb").selectedIndex].value+dates+reverse;
}
<? dumpPopCalendarJS(); ?>
</script>
