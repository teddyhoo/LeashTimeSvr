<? // prepayment-history-viewer.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "prepayment-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);

$invoices = fetchAssociations(
	"SELECT datetime, msgid, subject, correspaddr, ifnull(transcribed, 'email') as transcribed
	 FROM tblmessage
	 WHERE correspid = $client AND inbound = 0 AND correstable = 'tblclient' 
						AND (subject like '%$prepaidInvoiceTag%'
									OR subject like '%invoice%'
									OR tags like '%$prepaidInvoiceTag%'
									OR tags like 'billing'
									or body LIKE '%invoice%') 
	 ORDER BY datetime DESC");  // should really be [ tags like '%$billingInvoiceTag% ]


$client = getOneClientsDetails($client, array('email'));


$windowTitle = 'Recent Prepayment Invoices';
$extraBodyStyle = 'padding:10px;background:white;';
require "frame-bannerless.php";

if($error) {
	echo $error;
	exit;
}

$creditWindow = 60;
$recentCredits = fetchAssociations(
	"SELECT * FROM tblcredit 
		WHERE clientptr = {$client['clientid']} 
			AND issuedate > DATE_SUB(NOW(), INTERVAL $creditWindow day)
		ORDER BY issuedate DESC", 1);

echo "<h2>{$client['clientname']}'s Recent Billing History</h2>";
echo "<p align=center>";
fauxLink("View {$client['clientname']}'s History", "window.opener.location.href=\"client-edit.php?id={$client['clientid']}&tab=account\";window.close();");
echo "</p>";
$columns = explodePairsLine(/*'msgid|Message #||*/'datetime|Date||subject|Subject||transcribed|Notes');
$colClasses = array('subtotal'=>'dollaramountcell');
$rows = array();

foreach($invoices as $invoice) {
	$row = $invoice;
	$row['transcribed'] = $invoice['transcribed'] == 'mail' ? 'Printed and mailed' : ($invoice['transcribed'] == 'email' ? 'Emailed' : '');
	$row['subject'] = fauxLink($invoice['subject'], "viewPrepaymentInvoice({$row['msgid']})", 1, 'View this invoice');
	$row['datetime'] = shortDateAndTime(strtotime($invoice['datetime']));
	$rows[] = $row;
}

$credits = fetchAssociations("SELECT * FROM tblcredit WHERE payment = 1 AND voided IS NULL AND clientptr = {$client['clientid']}");
foreach($credits as $credit) {
	$row = $credit;
	$row['transcribed'] = truncatedLabel($credit['reason'], 40);
	$row['subject'] = 
		fauxLink('Payment - '.dollarAmount($credit['amount']), "openConsoleWindow(\"paydetail\", \"payment-edit.php?id={$credit['creditid']}\",600,220)",1, 'Show details');
	$row['datetime'] = shortDate(strtotime($credit['issuedate']));
	$rows[] = $row;
}


$refunds = fetchAssociations("SELECT * FROM tblrefund WHERE clientptr = {$client['clientid']}");
foreach($refunds as $refund) {
	$row = $refund;
	$row['transcribed'] = truncatedLabel($refund['reason'], 40);
	$row['subject'] = 
		fauxLink('Refund - '.dollarAmount($refund['amount']), "openConsoleWindow(\"paydetail\", \"refund-edit.php?id={$refund['refundid']}\",600,220)",1, 'Show details');
	$row['datetime'] = shortDate(strtotime($refund['issuedate']));
	$rows[] = $row;
}


$misccharges = fetchAssociations("SELECT * FROM tblothercharge WHERE clientptr = {$client['clientid']}");
foreach($misccharges as $charge) {
	$row = $charge;
	$row['transcribed'] = truncatedLabel($charge['reason'], 40);
	$row['subject'] = 
		fauxLink('Misc Charge - '.dollarAmount($charge['amount']), "openConsoleWindow(\"paydetail\", \"charge-edit.php?id={$charge['chargeid']}\",600,220)",1, 'Show details');
	$row['datetime'] = shortDate(strtotime($charge['issuedate']));
	$rows[] = $row;
}

	$ccerrors = fetchAssociations("SELECT * FROM tblcreditcarderror WHERE clientptr = {$client['clientid']}");
foreach($ccerrors as $error) {
	$row = $error;
	
	
	// guess the gatway.  shit.
	if(strpos($error['response'], '<?xml') === 0) $gateway = 'Solveras';
	else if(strpos($error['response'], '|') !== FALSE) $gateway = 'Authorize.net';
	else $gateway = 'SAGE';
	
	
	//$parts = explode('|', $error['response']);
	
	if($gateway = getGatewayObject($gateway))
		$message = $gateway->ccLastMessage($error['response']);
	$row['transcribed'] = truncatedLabel(($message ? $message : $parts[3]), 240);
	
	
	//print_r($gateway);exit;
	
	$row['subject'] = 'Payment Failed: '.dollarAmount($parts[9]);
	$row['datetime'] = shortDate(strtotime($error['time']));
	$rows[] = $row;
}
	

usort($rows, 'dateTimeSort');

foreach($rows as $row) {
	$rowClass = $rowClass != 'futuretask' ? 'futuretask' : 'futuretaskEVEN';
	$rowClasses[] = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
}


function dateTimeSort($a, $b) {
	$a = strtotime($a['datetime']);
	$b = strtotime($b['datetime']);
	return 0 - strcmp($a, $b);
}

if(!$recentCredits) echo "<span class='fontSize1_2em'>No payments/credits in last $creditWindow days.</span>";
else {
	foreach($recentCredits as $i => $credit) {
		$recentCredits[$i]['date'] = shortDate(strtotime($credit['issuedate']));
		$recentCredits[$i]['dollars'] = dollarAmount($credit['amount']);
	}
	$creditcols = explodePairsLine('date|Date||dollars|Amount||reason|Note');
	echo "<h3>Payments/credits in last $creditWindow days</h3>";
	tableFrom($creditcols, $recentCredits, 'WIDTH=100% ',null,null,null,null,$colSorts,$rowClasses, $colClasses, 'sortInvoices');
}
echo "<hr><h3>Communication</h3>";
//tableFrom($columns, $data, $attributes, $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses)
tableFrom($columns, $rows, 'WIDTH=100% ',null,null,null,null,$colSorts,$rowClasses, $colClasses, 'sortInvoices');

?>
<script language='javascript'>
function viewPrepaymentInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'comm-view.php?id='+invoiceid, 800, 800);
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

