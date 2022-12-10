<?
// duplicate-e-payments.php
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

echo "<h2>E-payments registered in Duplicate for $db</h2>";

foreach(fetchCol0(
	"SELECT externalreference 
		FROM tblcredit 
		WHERE externalreference LIKE 'ACH:%' OR externalreference LIKE 'CC:%'") as $transid)
	$allids[$transid] += 1;
	
foreach($allids as $id => $count) if($count >= 2) $dups[] = $id;

if($_REQUEST['start']) $startClause = "AND issuedate >= '".date('Y-m-d 00:00:00', strtotime($_REQUEST['start']))."'";

foreach((array)$dups as $dup) {
	$group = fetchAssociations($sql = 
		"SELECT c.*, CONCAT_WS(', ', lname, fname) as client
			FROM tblcredit c
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE externalreference = '$dup' $startClause");
	//echo "$sql<hr>";
	$clients[$group[0]['client']][] = $group;
}
require_once "invoice-fns.php";
//print_r($clients);
foreach((array)$clients as $client => $groups) {
	if(!$client) continue;
	$accountBalance = getAccountBalance($groups[0][0]['clientptr'], /*includeCredits=*/true, /*allBillables*/false);
	echo "<hr>$client balance: <b>\$$accountBalance</b>";
	
	foreach($groups as $group) {
		echo "  {$group[0]['issuedate']}  [{$group[0]['externalreference']}]<br>";
		$disp = null;
		$minused = 999999;
		$used = null;
		foreach($group as $credit) {
			$voided = $credit['voided'] ? ' VOIDED' : ' ';
			$disp[] = "({$credit['creditid']}, \${$credit['amount']}, used: {$credit['amountused']}$voided)";
			$minused = min($credit['amountused'], $minused);
			$used[$credit['amountused']] = $credit;
		}
		echo join(', ', $disp);
		$doomed = $used[$minused];
		$voided = $doomed['voided'] ? ' VOIDED' : ' ';
		echo "<br>will delete: ({$doomed['creditid']}, \${$doomed['amount']}, used: {$doomed['amountused']}$voided)";
		$totalExcess += $doomed['amount'];
		$doomedCount += 1;
		if($go) {
			sanitize($doomed['creditid']);
			echo "DELETED credit ID ({$doomed['creditid']}): ".mysql_affected_rows()." row.";
			$totalDeletions += mysql_affected_rows();
			if(mysql_affected_rows()) $totalDeletedAmount += $doomed['amount'];
		}
	}
}
echo "<hr><hr>Credits to be deleted: $doomedCount.  Excess payments total \$".number_format($totalExcess,2);
if($go) echo "<p>Deleted $totalDeletions payments totalling \$".number_format($totalDeletedAmount,2);

function sanitize($creditid) {
	require_once "credit-fns.php";
	$credit = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $creditid");
	voidCredit($credit['creditid']);
	deleteCredit($credit['creditid'], $reason='DUP created 11/23/2015');
 	payOffClientBillables($credit['clientptr']);
}