<? // cc-usage-report.php
// use this script by hand to modify all LT biz databases
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');

$databases = fetchCol0("SHOW DATABASES");

$from = $_REQUEST['from'];
if($from) $from = date('Y-m-d', strtotime($from));
$to = $_REQUEST['to'];
if($to) $from = date('Y-m-d', strtotime($to));

echo "<style>.test {color:darkgrey} td {text-align:right;</style><table border=1>";

$sql = "SELECT count(*) as count, SUM(amount) as amount FROM tblcredit WHERE externalreference LIKE 'CC:%' AND sourcereference LIKE 'CC:%'";
if($from) $sql .= " AND issuedate >= '$from'";
if($to) $sql .= " AND issuedate >= '$to'";

foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	$rowclass = '';
	if($biz['test']) {
		$rowclass = 'class=test';
		//$skipped[] = $db;
		//continue;
	}
	$numrows++;
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	$bizptr = $biz['bizid'];
	$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		echo "Not able to connect: invalid database username and/or password.\n";
	}
	$lnk1 = mysqli_select_db($db);
	if(mysqli_error()) echo mysqli_error();
	$tables = fetchCol0("SHOW TABLES");
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	$data = fetchFirstAssoc($sql);
	if($data['count']) echo "<tr $rowclass><td>$db<td>{$data['count']}<td>{$data['amount']}</tr>";
	$totals['count'] += $data['count'];
	$totals['amount'] += $data['amount'];
}
echo "<tr><td><b>Totals</b><td>{$totals['count']}<td>{$totals['amount']}</tr>";
echo "<tr><td>Mean<td>".sprintf('%.2f', ($totals['count']/$numrows))."<td>".sprintf('%.2f', ($totals['amount']/$numrows))."</tr>";
echo "</table>";
//echo "Test DBs skipped: ".join(', ', $skipped);;
