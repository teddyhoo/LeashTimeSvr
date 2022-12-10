<? // diag-find-noncurrent-only.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";


$counts = fetchKeyValuePairs("SELECT clientptr, count(*) as num FROM tblrecurringpackage GROUP BY clientptr");
$names = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) as clientname
															FROM tblclient 
															WHERE clientid IN (".join(',', array_keys($counts)).")
															ORDER BY lname, fname");

$currentcounts = fetchKeyValuePairs("SELECT clientptr, count(*) as num FROM tblrecurringpackage WHERE current GROUP BY clientptr");

echo "Recurring clients without current schedules:<p>";

$ids = array_diff_key($counts, $currentcounts);

if($ids) foreach($ids as $client => $count) echo "{$names[$client]} [$client]: $count<br>";
else echo "-- None --<br>";


echo "<p>Client Name [ClientId]: number of recurring schedule versions<p>";

foreach($names as $client => $name) {
	$mult = $currentcounts[$client] > 1 ? '<font color=red>Multiple Current Recurring Schedules</font>' : ''; 
	echo "$name [$client]: {$counts[$client]} $mult<br>";
}
