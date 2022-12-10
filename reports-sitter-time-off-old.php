<?
// reports-sitter-time-off-old.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if(userRole() != 'o' || !staffOnlyTEST()) {
	echo "Must be logged in as staff in a customer database.";
	exit;
}

$newSitterTimeOff = in_array('tbltimeoffinstance', fetchCol0("SHOW TABLES"));

echo "<h2>{$_SESSION['bizname']}</h2>";

if(!$newSitterTimeOff) echo "<b>New Sitter Time Off Tables Not Installed.</b><p>";
else $newTimesOff = fetchAssociationsIntoHierarchy("SELECT * FROM tbltimeoffinstance ORDER BY timeofday", 
													array('providerptr', 'date'));
													
													
echo "<table border=1 bordercolor=black><tr><th>Sitter<th>Old Start<th>Old End<th>Old Time of Day";
if($newSitterTimeOff) echo "<th>Pattern ID<th>New day<th>New Time of Day</tr>";
$sql = "SELECT tbltimeoff.*, CONCAT_WS(' ', fname, lname) as name
					FROM tbltimeoff
					LEFT JOIN tblprovider ON providerid = providerptr
					ORDER BY name, firstdayoff, timeofday";
$result = doQuery($sql, 1);
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$color = $color ? '' : "style='background:palegreen'";
	for($date = strtotime($row['firstdayoff']); $date <= strtotime($row['lastdayoff']); $date = strtotime("+1 day", $date)) {
		$prettydate = date('m/d/Y', $date);
		$tod = $row['timeofday'] ? $row['timeofday'] : 'All Day';
		$counterparts = $newTimesOff[$row['providerptr']][date('Y-m-d', $date)];
		$fdo = date('m/d/Y', strtotime($row['firstdayoff']));
		$ldo = date('m/d/Y', strtotime($row['lastdayoff']));
		if(!$counterparts) echo "<tr $color><td>{$row['name']}<td>$fdo<td>$ldo<td>$tod";
		else foreach($counterparts as $new) {
			$pid = $new['patternptr'] ? $new['patternptr'] : '';
//if($row['timeofday'] != $new['timeofday']) echo '<tr><td colspan=7>'.print_r($row, 1).'<p>'.print_r($new, 1);
			echo "<tr $color><td>{$row['name']}<td>$fdo<td>$ldo<td>$tod";
			echo "<td>$pid<td>".date('m/d/Y', strtotime($new['date']))."<td>".($new ? ($new['timeofday'] ? $new['timeofday'] : 'All Day') : '');
		}
	}
}
echo "</table>";
