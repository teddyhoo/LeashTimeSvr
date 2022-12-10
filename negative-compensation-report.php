<? // negative-compensation-report.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "gui-fns.php";

/*
id
through
starting
*/

$locked = locked('+o-,+d-,#pa');

extract(extractVars('id,through,starting', $_GET));

$date = date('Y-m-d', strtotime($through));
$provider = $id ? "AND providerptr = $id" : '';
$starting = $firstDay ? "AND date >= '$starting'" : '';
$prettyStart = $starting ? "from ".longerDayAndDate(strtotime($starting)).'<br>' : '';
$negs = fetchAssociations($sql = "SELECT * FROM tblnegativecomp WHERE date <= '$date' $provider $firstDay ORDER BY date");


$provName = fetchRow0Col0("SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider WHERE providerid = $id");
$windowTitle = $provName."&apos;s Negative Compensation";
$customStyles = ".dateRow {background: lightblue;font-weight:bold;text-align:center;}";
require "frame-bannerless.php";
echo "<h2 align=center>$provName's Negative Compensation $prettyStart through: ".longerDayAndDate(strtotime($through))."</h2>";

if(!$negs) echo "No negative compensation found.";

$columns = explodePairsLine('origamount|Neg Amount||amountapplied|Amount Applied||reason|Reason');
foreach($negs as $i => $neg) {
	if($neg['date'] != $lastDate) {
		$lastDate = $neg['date'];
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td class='dateRow' colspan=".(count($columns)).">".longerDayAndDate(strtotime($lastDate))."</td></tr>");
	}
	$link = dollarAmount($neg['amount']);
	
	$neg['origamount'] =  
		staffOnlyTEST() 
		? fauxLink($link, "openConsoleWindow(\"negcompeditor\", \"neg-compensation-edit.php?id={$neg['negcompid']}\",530,450)", 1)
		: $link;
	$neg['amountapplied'] =  dollarAmount($neg['paid']);
	$rows[] = $neg;
}
tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, null, null, $colClasses);
?>
<script language='javascript' src='common.js'></script>
