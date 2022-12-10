<? // account-reset.php
/*
Show details of all payments since start.
If void, void all credits/ids
*/
require_once "common/init_session.php";

require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";

extract(extractVars('id,since,void', $_REQUEST));
$client = getOneClientsDetails($id, array());
$locked = locked('o-');
if($since) $since = date('Y-m-d', strtotime($since));

$allCreditsSince = fetchAssociations($sql = "SELECT * FROM tblcredit WHERE clientptr=$id AND issuedate >= '$since' ORDER BY issuedate");
//echo "$sql<p>";
foreach($allCreditsSince as $credit) {
	if($credit['voided']) continue;
	$credit['issuedate'] = shortDate(strtotime($credit['issuedate']));
	if($credit['payment']) $payments[] = $credit;
	else $credits[] = $credit;
}
foreach((array)$payments as $i => $payment) {
	$creditid = $payment['creditid'];
	$rbps = fetchKeyValuePairs("SELECT billableptr, amount FROM relbillablepayment WHERE paymentptr = $creditid");
	$payments[$i]['paid'] = array_sum((array)$rbps);
	$grats = fetchRow0Col0("SELECT COUNT(*) FROM tblgratuity WHERE paymentptr = $creditid");
	$payments[$i]['grats'] = $grats;
	$payments[$i]['cb'] = "<input type='checkbox' id='cb_$creditid' name='cb_$creditid'>";
}
foreach((array)$credits as $i => $credit) {
	$creditid = $credit['creditid'];
	$rbps = fetchKeyValuePairs("SELECT billableptr, amount FROM relbillablepayment WHERE paymentptr = $creditid");
	$credit[$i]['paid'] = array_sum((array)$rbps);
	$credits[$i]['cb'] = "<input type='checkbox' id='cb_$creditid' name='cb_$creditid'>";
}
require_once "frame-bannerless.php";
if($void) {
	require_once "credit-fns.php";
	require_once "invoice-fns.php";
	$previously = "(previously) ";
	echo "VOID: $void<p>";
	$ids = explode(',', $void);
	foreach($ids as $voidCredit) {
		$voidreason = "Account cleanup";
		$credit = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $voidCredit");
		$type = $credit['payment'] ? 'Payment' : 'Credit';
		if($credit['clientptr']) {
			voidCredit($voidCredit, $voidreason, $hide=1);
			$successMessage = "$type #$voidCredit has been voided.";
		}
		else {
			$successMessage = "$type #$voidCredi could not be voided.";
		}
		echo "$successMessage<br>";
	}
}
echo "<h2>{$previously}Unvoided Payments by {$client['clientname']} since $since</h2>";
echoButton('voidb', 'VOID Selected', $onClick='voidSelected()', $class='HotButton', $downClass='HotButtonDown', $noEcho=false, $title=null);
echo "<p>{$previously}Unvoided Payments<p>";
$columns = 'cb| ||creditid|ID||grats|G||issuedate|Date||amount|Amount||amountused|Used||paid|(paid)||externalreference|Transaction||sourcereference|Source||reason|Note';
$columns = explodePairsLine($columns);
echo "<form name='voidables' method='POST'>";
tableFrom($columns, $payments, $attributes="BORDER=1");

echo "<p>{$previously}Unvoided Credits<p>";
$columns = 'cb| ||creditid|ID||issuedate|Date||amount|Amount||amountused|Used||paid|(paid)||externalreference|Transaction||sourcereference|Source||reason|Note';
$columns = explodePairsLine($columns);
tableFrom($columns, $credits, $attributes="BORDER=1");

echo "</form>";
?>
<script language='javascript'>
function voidSelected() {
	var sels = getSelections('Please select at least one.');
	if(sels.length == 0) return;
	document.location.href='account-reset.php?id=<?= $id ?>&since=<?= $since ?>&void='+sels;
}

function getSelections(emptyMsg) {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') != -1 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert(emptyMsg);
		return;
	}
	return sels;
}

</script>