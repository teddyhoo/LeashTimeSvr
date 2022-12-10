<?
/* email-usage-overview.php
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
$date1 = "2015-01-01";
$date2 = "2015-05-05";

$result = doQuery("SELECT datetime FROM tblmessage where datetime >= '$date1 00:00:00' AND datetime <= '$date2 23:59:59' ORDER BY datetime");

while($row = mysql_fetch_row($result)) {
	$date = substr($row[0], 0, strlen($date1));
	$counts[$date] += 1;
}

$unit = "#";
echo "<h2>$db emails by day: $date1 - $date2</h2>";
echo "# = 5 emails";
echo "<table>";
foreach($counts as $date => $count) {
	echo "<tr><td>$date<td>$count<td>";
	for($i=0; $i<$count; $i+=5) echo $unit;
}