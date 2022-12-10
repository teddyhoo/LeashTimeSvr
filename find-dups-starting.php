<? // find-dups-starting.php

// https://leashtime.com/find-dups-starting.php?date=2015-11-30
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$sql =
"SELECT CONCAT_WS('|',date,timeofday,clientptr,providerptr,servicecode) as thumb, count(*) as num
	FROM tblappointment
	WHERE recurringpackage = 1 AND date >= '{$_GET['date']}'
	GROUP by thumb
	ORDER BY thumb";
echo "<table>";
foreach(fetchAssociations($sql) as $row)
	if($row['num'] > 1) {
		$orig += 1;
		$dups += $row['num'];
		echo "<tr><td>{$row['thumb']}<td>{$row['num']}</tr>";
	}
echo "</table>";
echo "Should be $orig, found $dups";