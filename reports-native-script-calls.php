<? // reports-native-script-calls.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$locked = locked('o-');
extract($_POST);

//	logChange($_SESSION['auth_user_id'], $script, $operation='x', $note="{$_SESSION['auth_login_id']}||{$_SERVER["HTTP_USER_AGENT"]}");
$scripts = fetchCol0($sql =
	"SELECT DISTINCT itemtable FROM tblchangelog WHERE operation = 'n' ORDER BY itemtable");
$scriptOptions['All Scripts'] = '';
foreach($scripts as $nm) $scriptOptions[$nm] = $nm;
$sitterFilter = $provider ? "AND user = $provider" : '';
$scriptFilter = $script ? "AND itemtable = '$script'" : '';
$rows = fetchAssociations($sql =
	"SELECT * FROM tblchangelog WHERE operation = 'n' $sitterFilter $scriptFilter ORDER BY time DESC LIMIT 1000 ");
require "frame.html";
$sitterUserids = fetchKeyValuePairs(
	"SELECT userid,  CONCAT_WS(' ', fname, lname)
		FROM tblprovider 
		WHERE active =1 AND userid IS NOT NULL
		ORDER BY lname, fname", 1);
list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
require "common/init_db_common.php";
$sitterLoginids = fetchKeyValuePairs(
	"SELECT userid, loginid FROM tbluser WHERE userid IN (".join(',', array_keys($sitterUserids)).")", 1);
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);
$sitterOptions['All Sitters'] = 0;
foreach($sitterUserids as $userid => $name) $sitterOptions["$name ({$sitterLoginids[$userid]})"] = $userid;
?>
<form name='searchform' method='POST'>
<?
selectElement('Sitter', 'provider', $provider, $sitterOptions, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
echo " ";
selectElement('Script', 'script', $script, $scriptOptions, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
echo " ";
echoButton('', 'Go', 'go()');
?>
</form>
<script language='javascript'>
function go() {document.searchform.submit();}
</script>
<?
if($rows) {
	$sitterUserids = array();
	$columns = explodePairsLine("time|Time||loginid|Login||script|Script||agent|Agent");
	foreach($rows as $i => $row) {
		$note = explode('||', $row['note']);
		$rows[$i]['loginid'] = $note[0];
		$rows[$i]['agent'] = $note[1];
		$rows[$i]['script'] = $row['itemtable'];
		if(!$sitterUserids[$row['loginid']]) $sitterUserids[$row['loginid']] = $row['user'];
	}
	tableFrom($columns, $rows, $attributes='border=1', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}
require "frame-end.html";
