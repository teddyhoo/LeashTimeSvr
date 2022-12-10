<?
// unrecorded-e-payments.php
// find all payments with identical transaction ID's

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

//if(mattOnlyTEST()) {}
// Determine access privs
$locked = locked('o-');

$go = $_GET['go'];
if($go && $go != $db) {
	echo "WRONG DATABASE!";
	exit;
}

echo "<h2>E-payments not registered in Credits for $db</h2>";


$allTransactions = fetchAssociations(
	"SELECT * FROM tblchangelog WHERE note LIKE 'Approved%' AND time > '2014-11-05'");
	
foreach($allTransactions as $i => $trans) {
	$parts = explode('|', $trans['note']);
	$allTransactions[$i]['transid'] = $parts[1];
	$parts = explode('-', $parts[0]);
	$allTransactions[$i]['amount'] = $parts[1];
}

foreach($allTransactions as $i => $trans) {
	$credit = fetchFirstAssoc("SELECT * FROM tblcredit WHERE issuedate >= '2015-11-05 00:00:00' AND externalreference LIKE '%{$trans['transid']}' LIMIT 1"); 
	if(!$credit) {
		$tbl = strpos($trans['itemtable'], 'cc') !== FALSE ? 'tblcreditcard' : 'tblecheckacct';
		$idfield = $tbl == 'tblcreditcard' ? 'ccid' : 'acctid';
		$trans['client'] = fetchRow0Col0($sql =
			"SELECT CONCAT_WS(', ', lname, fname) 
				FROM $tbl
				LEFT JOIN tblclient ON clientid = clientptr
				WHERE $idfield = {$trans['itemptr']}
				LIMIT 1");
		$missing[] = $trans;
	}
}
foreach($missing as $trans) echo "{$trans['time']} {$trans['client']} {$trans['amount']}<br>";




exit;




foreach(fetchCol0(
	"SELECT externalreference 
		FROM tblcredit 
		WHERE externalreference LIKE 'ACH:%' OR externalreference LIKE 'CC:%'") as $transid)
	$allids[$transid] += 1;
	
//foreach($allids as $id => $count) {
	

foreach((array)$dups as $dup) {
	$group = fetchAssociations(
		"SELECT c.*, CONCAT_WS(', ', lname, fname) as client
			FROM tblcredit c
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE externalreference = '$dup'");
	$clients[$group[0]['client']][] = $group;
}
require_once "invoice-fns.php";

foreach((array)$clients as $client => $groups) {
	$accountBalance = getAccountBalance($groups[0][0]['clientptr'], /*includeCredits=*/true, /*allBillables*/false);
	echo "<hr>$client balance: <b>\$$accountBalance</b>";
	
	foreach($groups as $group) {
		echo "  {$group[0]['issuedate']}  [{$group[0]['externalreference']}]<br>";
		$disp = null;
		$min = 999999;
		foreach($group as $credit) {
			$disp[] = "({$credit['creditid']}, \${$credit['amount']}, used: {$credit['amountused']})";
			$minused = min($credit['amountused'], $min);
			$used[$credit['amountused']] = $credit;
		}
		echo join(', ', $disp);
		$doomed = $used[$minused];
		echo "<br>will delete: ({$doomed['creditid']}, \${$doomed['amount']}, used: {$doomed['amountused']})";
		$totalExcess += $doomed['amount'];
		if($go) {
			sanitize($doomed['creditid']);
			echo "DELETED credit ID ({$doomed['creditid']}): ".mysql_affected_rows()." row.";
			$totalDeletions += mysql_affected_rows();
			if(mysql_affected_rows()) $totalDeletedAmount += $doomed['amount'];
		}
	}
}
echo "<hr><hr>Excess payments total \$".number_format($totalExcess,2);
if($go) echo "<p>Deleted $totalDeletions payments totalling \$".number_format($totalDeletedAmount,2);

function sanitize($creditid) {
	require_once "credit-fns.php";
	$credit = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $creditid");
	voidCredit($credit['creditid']);
	deleteCredit($credit['creditid'], $reason='DUP created 11/23/2015');
 	payOffClientBillables($credit['clientptr']);
}