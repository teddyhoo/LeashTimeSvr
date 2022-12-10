<? // invoices-recent.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "invoice-gui-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);

$invoices = fetchAssociations(
	"SELECT invoiceid, lastsent, notification, subtotal, origbalancedue, clientptr, date, paidinfull
	 FROM tblinvoice
	 WHERE clientptr = $client
	 ORDER BY date DESC, invoiceid DESC
	 LIMIT 5");
	 
$credits = fetchAssociations(
	"SELECT *, issuedate as date, amount-amountused as amountleft 
	 FROM tblcredit 
	 WHERE clientptr = $client 
	 ORDER BY date DESC");	 
	 
	 
	 
$client = getOneClientsDetails($client, array('email'));


$windowTitle = 'Recent Invoices';
$extraBodyStyle = 'padding:10px;background:white;';
require "frame-bannerless.php";

if($error) {
	echo $error;
	exit;
}
echo "<h2>{$client['clientname']}'s Recent Invoices</h2>";
echo "<p align=center>";
fauxLink("View {$client['clientname']}'s History", "window.opener.location.href=\"client-edit.php?id={$client['clientid']}&tab=account\";window.close();");
echo "</p>";
$columns = explodePairsLine($columns.'invoiceid|Invoice #||notification|Last Sent / Received||subtotal|Curr Inv||overdue|Overdue / Note');
$colClasses = array('subtotal'=>'dollaramountcell');

$pastDueDays = $_SESSION['preferences']['pastDueDays'];
$pastDueDays = strlen(''.$pastDueDays) == '0' ? 30 : $pastDueDays;

$invoices = array_merge($invoices, $credits);
function cmpDates($a, $b) {return strcmp($a['date'], $b['date']); }
usort($invoices, 'cmpDates');


foreach($invoices as $invoice) {
	$row = array();
	if($invoice['invoiceid']) {
		$row['invoiceid'] = invoiceLink($invoice, $client['email'], 1).($invoice['paidinfull'] ? ' PAID' : ' UNPAID');
		$row['notification'] = $invoice['notification'] ? $invoice['notification'].' '.shortDate(strtotime($invoice['lastsent'])) : '';
		$row['subtotal'] = $invoice['invoiceid'] ? dollarAmount($invoice['subtotal']) : '-';
		$overdue = $invoice['paidinfull'] ? '' : overDue($invoice);
		$row['overdue'] = $overdue ? "$overdue days" : '';;
		$rowClass = strpos($rowClass, 'EVEN') ? 'futuretask' : 'futuretaskEVEN';
		$rowClasses[] = $rowClass;
	}
	else {
		$credit = $invoice;
		$row['invoiceid'] = $credit['payment'] ? 'Payment' : 'Credit';
		$row['notification'] = shortDate(strtotime($credit['date']));
		$row['subtotal'] = dollarAmount($credit['amount']);
		$row['overdue'] = $credit['reason'];
		$rowClass = strpos($rowClass, 'EVEN') ? 'payment' : 'paymentEVEN';
		$rowClasses[] = $rowClass;
	}
	$rows[] = $row;
}
//tableFrom($columns, $data, $attributes, $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses)
tableFrom($columns, $rows, 'WIDTH=100% ',null,null,null,null,$colSorts,$rowClasses, $colClasses, 'sortInvoices');

function overDue($invoice) {
	$today = strtotime(date('Y-m-d')) / (24 * 60 * 60);
	$invoiceDay = strtotime($invoice['date'])  / (24 * 60 * 60);
	foreach(array(45, 30, 15) as $limit)
		if($today - $invoiceDay > $pastDueDays + $limit) return $limit;
	return 0;
}	

?>
<script language='javascript'>
function viewInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'invoice-view.php?id='+invoiceid+'&email='+email, 800, 800);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}
</script>

