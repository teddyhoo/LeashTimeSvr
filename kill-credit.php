<? // kill-credit.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

$locked = locked('o-');
extract(extractVars('credit,go', $_REQUEST));

$cred = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $credit LIMIT 1");
$applications = fetchKeyValuePairs("SELECT billableptr, amount FROM relbillablepayment WHERE paymentptr = $credit");

if($applications) $billables = fetchAssociations("SELECT * FROM tblbillable WHERE billableid IN (".join(',', array_keys($applications)).")");


if($go) {
	if($go != $_SESSION['kill-credit-token']) {
		echo "Cannot execute.<p>";
		$go = false;
	}
	unset($_SESSION['kill-credit-token']);
	if($go) {
		foreach((array)$billables as $b) {
			$bid = $b['billableid'];
			doQuery("UPDATE tblbillable SET paid = paid - {$applications[$bid]} WHERE billableid = $bid", 1);
		}
		echo "Updated ".count($billables)." rows in tblbillable.<p>";
		doQuery("DELETE FROM relbillablepayment WHERE paymentptr = $credit", 1);
		echo "Deleted ".mysql_affected_rows()." rows from relbillablepayment.<p>";
		doQuery("DELETE FROM tblcredit WHERE creditid = $credit", 1);
		echo "Deleted ".mysql_affected_rows()." rows from tblcredit.<p>";
	}
}


print_r($cred);
echo "<p>";
foreach((array)$billables as $b) {
	$paid = $b['paid'];
	$unpay = $applications[$b['billableid']];
	echo $b['billableid'].': ['.$b['charge']."] $paid - $unpay = ".($paid - $unpay)." [{$applications[$b['billableid']]}]".'<br>';
}
echo "<p>";
print_r(fetchAssociations("SELECT * FROM relbillablepayment WHERE paymentptr = $credit"));
echo "<p>";
print_r($applications);
	// SELECT creditid, concat_ws( ' ', fname, lname ) FROM tblcredit LEFT JOIN tblclient ON clientid = clientptr WHERE creditid =158
	// delete FROM `tblcredit` WHERE creditid = 
	
	// oct 18 YDS backup
$time = time();
$_SESSION['kill-credit-token'] = $time;
echo "<p>";
echo "Do it? <a href='kill-credit.php?credit=$credit&go=$time'>Yes</a>";