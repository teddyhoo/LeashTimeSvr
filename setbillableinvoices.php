<? // setbillableinvoices.php

require_once "common/init_session.php";
//require_once "common/init_db_petbiz.php";

foreach(array('doggiewalkerdotcom','dogslife','fetch210','petaholics','petbiz','petcentric','woofies') as $dbname) {
	$db = $dbname;
	require_once "common/init_db_petbiz.php";
	echo "$db<p>";
	$result = doQuery("SELECT * FROM relinvoiceitem");
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		doQuery("UPDATE tblbillable SET invoiceptr={$row['invoiceptr']} WHERE billableid = {$row['billableptr']}");
	}
}