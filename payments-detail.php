<? // payments-detail.php
// prov - provider id. 
// start, end
// OR
// paymentid - payment id

//https://leashtime.com/payments-detail.php?prov=6&start=2010-01-01&end=2010-07-08

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Determine access privs
$locked = locked('vh');

extract($_REQUEST);

$where = $paymentid ? "paymentid = $paymentid" : "providerptr = $prov AND paymentdate >= '$start' AND paymentdate <= '$end'";

$sql = "SELECT tblproviderpayment.*,
				CONCAT_WS(' ', fname, lname) as provider, CONCAT_WS(' ', lname, fname) as sortname
				FROM tblproviderpayment 
				LEFT JOIN tblprovider ON providerid = providerptr
				WHERE $where $filter
				$sorts";

$result = doQuery($sql);

$aggregateView = 1;

while($payment = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$id = $payment['paymentid'];
	require "payment-detail.php";
	echo "<p>";
}