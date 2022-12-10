<? // reports-errors.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

$locked = locked('o-');
if(!staffOnlyTEST()) {echo "This page is for LeashTime Staff only.";exit;}

extract(extractVars('sort,start,end,reverse,prune', $_REQUEST));

if($prune) {
	deleteTable('tblerrorlog',
		$cond="(message LIKE 'Mail Transport fail%'
		OR message LIKE 'tblqueuedemail:%'
		OR message LIKE '... [%'
		OR message LIKE 'Mail queue processing started%'
		OR message LIKE 'Queue halt after authentication error%'
		OR message LIKE 'Mail queue processing was disabled%')
		AND `time` <= '".date('Y-m-d 23:59:59', strtotime($prune))."'",
		1);
	$pruned = mysql_affected_rows();
	doQuery(" OPTIMIZE TABLE `tblerrorlog`");
	//echo $cond;
}



if($reverse) $sort = "time ASC";
$sorts = $sort ? explode('_', $sort) : '';

$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
if($start) $filter[] = "time >= '".date('Y-m-d', strtotime($start))."'";
if($end) $filter[] = "time <= '".date('Y-m-d 23:59:59', strtotime($end))."'";
if(!$start && !$end) "TO_DAYS(NOW()) - TO_DAYS(time) < 2";
$filter = $filter ? "WHERE ".join(' AND ',$filter) : '';
$limit = $_REQUEST['limit'] ? $_REQUEST['limit'] : '5000';


$result = doQuery($SQL = "SELECT time, message, body FROM tblerrorlog
	LEFT JOIN tbltextbag ON textbagid = IF(message LIKE '%|tbag:%', SUBSTRING_INDEX(message, ':', -1), -99)
	$filter $orderBy LIMIT $limit");
//echo fetchRow0Col0("SELECT IF('526|tbag:1' LIKE '%|tbag:%', SUBSTRING_INDEX('526|tbag:1', ':', -1), -999)");exit;

if($result) while($line = mysql_fetch_assoc($result)) {
	if(strpos($line['message'], '|tbag:')) $line['message'] = $line['body']; // from tbltextbag
	$row = $line;
	$line['time'] = date('m/d/Y H:i', strtotime($line['time']));
	//$row['user'] = $line['userptr'];
	$rowClass =	$rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';
	$rowClasses[] =	$rowClass;

	$rows[] = $row;
}
$columns = explodePairsLine('time|Time||message|Message'); //||user|User');
$columnSorts = array('time'=>null, 'message'=>null /*, 'user'=>null*/);

//$windowTitle = 'Error Log';
include 'frame-bannerless.php';
?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
$end = $end ? $end : shortDate(time());
$endClause = "time <= '".date('Y-m-d 23:59:59', strtotime($end))."'";
calendarSet('Start:', 'start', $start, null, null, true, 'end');
calendarSet('End:', 'end', $end, null, null, true);
hiddenElement('reverse', '');
echoButton('', 'Latest', "go(null)");
echo " ";
echoButton('', 'Earliest', "go(\"reverse\")");

$logSize = 
	round(fetchRow0Col0(
	"SELECT (data_length+index_length)/power(1024,1) tablesize
		FROM information_schema.tables
		WHERE table_schema='$db' and table_name='tblerrorlog';")).' KB';
$logEntries = fetchRow0Col0("SELECT count(*) FROM tblerrorlog");
$mailEntries = fetchRow0Col0($emailSQL = "SELECT count(*) FROM `tblerrorlog`
	WHERE (message LIKE 'Mail Transport fail%'
	OR message LIKE 'tblqueuedemail:%'
	OR message LIKE '... [%'
	OR message LIKE 'Mail queue processing started%')");
$oldEmailEntries = fetchRow0Col0("$emailSQL AND $endClause");
$mailEntries = "$mailEntries mail errors.  $oldEmailEntries before $end. $logEntries rows total.";
$logEntries = "<span title='$mailEntries'><u>$logEntries rows</u></span>";
echo " Log size: $logSize / $logEntries ";
fauxLink('prune', 'pruneLog()');

echo "<p>";
if($pruned) echo "$pruned rows deleted from error log.<p>";
if($rows) tableFrom($columns, $rows, "", $class='biztable', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);//, 'sortClick'

include "refresh.inc";
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function pruneLog() {
	var date = document.getElementById('end').value;
	if(confirm('Prune all email errors before '+date+'?')) {
		document.location.href="reports-errors.php?prune="+date;
	}
}

function go(reverse) {
	var reverse = reverse && typeof reverse != undefined ? '&reverse=1' : '';
	document.getElementById('reverse').value=1;
	var dates = "<?= "&limit=$limit" ?>&start="+escape(document.getElementById("start").value)+"&end="+escape(document.getElementById("end").value);
	document.location.href="reports-errors.php?"+dates+reverse;
}
<? dumpPopCalendarJS(); ?>
</script>
