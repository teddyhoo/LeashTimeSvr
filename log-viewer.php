<? // log-viewer.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

// Determine access privs
$locked = locked('o-');

extract(extractVars('start,end,pattern,descending', $_REQUEST)); // table,
if(($start && strlen($start) > 10) || ($end && strlen($end) > 10)) $ditch  = "bad dates";
if($pattern && 
	(strpos(strtoupper($pattern), 'SELECT') !== NULL
	|| strpos(strtoupper($pattern), 'UNION') !== NULL))
	$ditch  = "pattern";
if(!isset($_REQUEST['start'])) $start = date('Y-m-d');
if(!isset($_REQUEST['descending'])) $descending = 1;
$descending = $descending ? 'CHECKED' : '';
echo "<form name=search method=post>";
//echoButton('searchLogButton', 'Show', "searchLog()");
echoButton('searchLogButton', 'Show', "document.search.submit()");
calendarSet('Starting:', 'start', $start, null, null, true, 'end');
echo " <input id='starttime' name='starttime' autocomplete='off'><p>";
calendarSet('end:', 'end', $end);
echo " <input id='endtime' name='endtime' autocomplete='off'><p>Pattern: <input id='pattern' name='pattern' autocomplete='off'>
<p>Reverse chron: <input type=checkbox id='descending' name='descending' $descending><hr>";
echo "</form>";
$filters = array();
if($start) {
	if($starttime) $start.= " $starttime";
	$filters[] = "time >= '$start'";
}
if($end) {
	if($endtime) $end.= " $endtime";
	$filters[] = "time >= '$end'";
}
if($pattern) {
	$dbpattern = str_replace('*'  , '%', $pattern);
	$filters[] = "message like '$dbpattern'";
}
$filters = $filters ? "WHERE ".join(' AND ', $filters) : '';
$sql = "SELECT * FROM tblerrorlog $filters";
if($descending) $sql .= ' ORDER BY time DESC';
$rows = fetchAssociations($sql);
if(!$rows) echo "None found";
else {
	echo "<table>";
	foreach(fetchAssociations($sql) as $row) echo "<tr><td>{$row['time']}<td>{$row['message']}";
	echo "</table>";
}
?>
<script language='javascript' src='popcalendar.js'></script>