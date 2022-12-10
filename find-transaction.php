<? // find-transaction.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$locked = locked('o-');
extract(extractVars('pattern', $_REQUEST));

if($pattern) {
	$credits = fetchAssociations(
		"SELECT tblcredit.*, CONCAT_WS(' ', fname, lname) as client
		FROM tblcredit
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE externalreference LIKE '%$pattern%'");
	$refunds = fetchAssociations(
		"SELECT tblrefund.*, CONCAT_WS(' ', fname, lname) as client
		FROM tblrefund
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE externalreference LIKE '%$pattern%'");
}

require "frame-bannerless.php";

echo "<h2>Find a transaction</h2>";

echo "<form method='POST' name='findform'>";
labeledInput('Transaction ID: ', 'pattern', $pattern);
echo " ";
echoButton('', 'Go', 'findTransaction()');
echo "</form>";

if(!$credits) {
	echo "No credit transactions found.";
}
else {
	
	foreach($credits as $i => $credit) {
		$credits[$i]['date'] = date('m/d/Y', strtotime($credit['issuedate']));
		$issueDate = date('Y-m-d', strtotime($credit['issuedate']));
		$credits[$i]['client'] = fauxLink($credit['client'], "parent.location.href=\"client-edit.php?id={$credit['clientptr']}&tab=account&invoiceStart=$issueDate \"", 1);
		$credits[$i]['dispAmount'] = dollarAmount($credit['amount']);
	}
	echo "<h2 style='font-size:14px'>Credits</h2>";
	$columns = explodePairsLine('date|Date||externalreference|Transaction||client|Client||dispAmount|Amount');
	tableFrom($columns, $credits, $attributes='WIDTH=100%');
}
if(!$refunds) {
	echo "No refund transactions found.";
}
else {
	
	foreach($refunds as $i => $refund) {
		$refunds[$i]['date'] = date('m/d/Y', strtotime($refund['issuedate']));
		$issueDate = date('Y-m-d', strtotime($refund['issuedate']));
		$refunds[$i]['client'] = fauxLink($refund['client'], "parent.location.href=\"client-edit.php?id={$refund['clientptr']}&tab=account&invoiceStart=$issueDate \"", 1);
		$refunds[$i]['dispAmount'] = dollarAmount($refund['amount']);
	}
	echo "<h2 style='font-size:14px'>Refunds</h2>";
	$columns = explodePairsLine('date|Date||externalreference|Transaction||client|Client||dispAmount|Amount');
	tableFrom($columns, $refunds, $attributes='WIDTH=100%');
}
?>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function findTransaction() {
	if(MM_validateForm('pattern', '', 'R'))
		document.findform.submit();
}
</script>