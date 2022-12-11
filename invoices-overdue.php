<? //invoices-overdue.php
if($_REQUEST['bizid']) {
	require_once "common/init_session.php";
	require_once "common/init_db_common.php";
	require_once "gui-fns.php";
	locked('o-');
	//recentLoginTable();
	//function recentLoginTable() {
		$intervalStart = date('Y-m-d', strtotime("-30 days"))." 00:00:00";
		$bizId = $_REQUEST['bizid'];
		$allStaff = fetchAssociationsKeyedBy("SELECT *, UCASE(loginid) as uloginid FROM tbluser WHERE bizptr = $bizId AND substring(rights,1,1) IN ('o','d','p') AND ltstaffuserid = 0", 'uloginid', 1);
		foreach($allStaff as $user) $allLoginIds[] = $user['loginid'];
		$result = doQuery($sql = "SELECT loginid, UCASE(loginid) as uloginid, success, failurecause, remoteaddress, browser, LastUpdateDate as time
		FROM tbllogin
		WHERE loginid IN ('".join("','", $allLoginIds)."') AND LastUpdateDate > '$intervalStart'
		ORDER BY LastUpdateDate DESC"); //  LIMIT 30
	
		echo "<p><table><tr><td><table class=biztable border=1>";
		$failures = explodePairsLine("0|Ok||L|Locked out||P|Bad password||U|Unknown user||I|Inactive User||R|RightsMissingOrMismatched||F|No Business found||B|Business inactive||M|Missing organization||O|Organization inactive||C|No cookie||D|Logins disabled for this role");
	
		$roles = explodePairsLine("p|P||o|O||c|C||d|D");
		$titles = explodePairsLine("p|P = Sitter||o|O = Owner / Manager||c|C = Client||d|D = Dispatcher");
		echo join(' - ', $titles);
		if($result) while($line = mysqli_fetch_assoc($result)) {
			$loginid = $line['loginid'];
			$time = date('D m/d/Y H:i:s', strtotime($line['time']));
			$color = $line['failurecause'] ? "style='background:pink'" : '';
			$failure = $line['failurecause'] ? $line['failurecause'] : '0';
			$failure = $failures[$failure];
			if(!$failure) $failure = "??$failure??";
			$line['rights'] = $allStaff[$line['uloginid']]['rights'];
			$role = $roles[$line['rights'] ? substr($line['rights'], 0, 1) : ''];
			//$title = $titles[$line['rights'] ? $titles[substr($line['rights'], 0, 1)] : ''];
			unset($line['remoteaddress']);
			unset($line['browser']);
			echo "<tr><td $color>$time<td $color>$role<td $color>$loginid<td $color>$failure<td $color>{$line['remoteaddress']}<td class='browsertoggle' $color>{$line['browser']}";
		//print_r($line);	
		}
		if(!$role) echo "<tr><td>Nothing found: $sql";
		echo "</table></td><td valign=top id='detail'></td></tr></table>";
	//}
	exit;
}


$listInvoices=1;

$tab = 'overdue';
include "invoices-top.php";
$showLTCustomerLogins = dbTEST('leashtimecustomers');

// for each client collect:
//	last invoice, if any, or array(clientid)
$clientIds = array();

$unpaid = "paidinfull IS NULL AND";

echo "<p><div class='bluebar'>Invoices Past Due</div></p>";
//echo "<h3>Invoices Past Due 15 Days</h3>";

// today - date > N
// today - date between N and m

$sql = "SELECT * FROM tblinvoice
				WHERE $unpaid TO_DAYS(CURDATE()) - TO_DAYS(date) BETWEEN ".($pastDueDays)." AND ".($pastDueDays + 14);
$invoices = fetchAssociations(tzAdjustedSql($sql));
invoiceListTable($invoices, $throughDateInt, null, 'past0');

echo "<p><div class='bluebar'>Invoices Past Due 15 Days</div></p>";
$sql = "SELECT * FROM tblinvoice
				WHERE $unpaid TO_DAYS(CURDATE()) - TO_DAYS(date) BETWEEN ".($pastDueDays + 15)." AND ".($pastDueDays + 29);
$invoices = fetchAssociations(tzAdjustedSql($sql));
invoiceListTable($invoices, $throughDateInt, null, 'past15');

echo "<p><div class='bluebar'>Invoices Past Due 30 Days</div></p>";
//echo "<h3>Invoices Past Due 30 Days</h3>";
$sql = "SELECT * FROM tblinvoice
				WHERE $unpaid TO_DAYS(date) BETWEEN TO_DAYS(CURDATE()) - ".($pastDueDays + 30)." AND TO_DAYS(CURDATE()) - ".($pastDueDays + 44);
$invoices = fetchAssociations(tzAdjustedSql($sql));
invoiceListTable($invoices, $throughDateInt, null, 'past30');

echo "<p><div class='bluebar'>Invoices Past Due 45 Days</div></p>";
//echo "<h3>Invoices Past Due 45 Days</h3>";

$sql = "SELECT * FROM tblinvoice
				WHERE $unpaid TO_DAYS(date) <= TO_DAYS(CURDATE()) - ".($pastDueDays + 45);
$invoices = fetchAssociations(tzAdjustedSql($sql));
invoiceListTable($invoices, $throughDateInt, null, 'past45');


if($showLTCustomerLogins) {
?>
<script language='javascript'>
function showLogins(bizid) {
	var url = 'invoices-overdue.php?bizid='+bizid;
	$.fn.colorbox({href:url, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}
</script>
<?
}

// ***************************************************************************
include "invoices-bottom.php";

