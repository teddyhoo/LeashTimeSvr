<? // changelog.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
$locked = locked('o-');
if(!staffOnlyTEST()) {
	echo "For LeashTime Staff Only";
	exit;
}

if($_POST) {
	if($_POST['itemptr']) $where[] = "itemptr = {$_POST['itemptr']}";
	if($_POST['itemtable']) $where[] = "itemtable = '{$_POST['itemtable']}'";
	$entries = fetchAssociations($sql = "SELECT * FROM tblchangelog WHERE ".join(' AND ', $where)." ORDER BY time desc");
	foreach($entries as $i => $entry) {
		//$entries[$i]['note'] = "<pre>{$entry['note']}</pre>";
	}
}


$pageTitle = "Change Log"; //"Home";


require "frame.html";
?>
<style>#log td {font-size:1.2em;}</style>
<table>
<form name='logform' method="POST">
<?
echoButton('', 'Search', 'search()');
inputRow('Table: ', 'itemtable', $_POST['itemtable'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
inputRow('Item ID: ', 'itemptr', $_POST['itemptr'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
?>
</form><p>
<?
if($entries) quickTable($entries, $extra='id="log" border=1 bordercolor=black');
?>

</table>
<script language='javascript' src='check-from.js'></script>
<script language='javascript'>
function search() {
	document.logform.submit();
}
</script>
<?
include "frame-end.html";