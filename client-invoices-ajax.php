<? // client-invoices-ajax.php 

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-gui-fns.php";
require_once "credit-fns.php";
require_once "refund-fns.php";
require_once "gratuity-fns.php";
require_once "js-gui-fns.php";

extract($_REQUEST);

if(userRole() == 'c') {
	$locked = locked('c-');
	$client = $_SESSION['clientid'];
	$clientMode = 1;
}
else $locked = locked('o-');

$_SESSION['hidevoidedcredits'] = $hidevoids;

//$currentInvoice = currentInvoice($client);

$credits = 0;
foreach(getClientCredits($client, 1) as $credit)
	$payments += $credit['amountleft'];
	
if(!$clientMode) {
	echoButton('searchForInvoicesButton', 'Show', "searchForInvoices()");
	echo " ";
	calendarSet('Starting:', 'invoiceStart', $starting, null, null, true, 'ending');
	echo "&nbsp;";
	calendarSet('ending:', 'invoiceEnd', $ending);
}
//echo "<span style='font-size:1.2em'> Current Balance: ".balDueDisplay($currentInvoice['balancedue']-$payments).'</span>';

echo "<p>";
$timeConstraint = '';
if($starting && strtotime($starting)) $timeConstraint = "AND date >= '".date('Y-m-d', strtotime($starting))."'";
if($ending && strtotime($ending)) $timeConstraint .= " AND date <= '".date('Y-m-d 23:59:59', strtotime($ending))."'";
$sort = $sort ? str_replace('_', ' ', $sort) : '';
$orderby = $sort ? "ORDER BY $sort" : '';
$sql = "SELECT * FROM tblinvoice WHERE clientptr = $client $timeConstraint $orderby";
$accountBalance = getAccountBalance($client, $includeCredits=true, $allBillables=false);
//echo $sql;
//echo "<h3>Invoices</h3>";
if((date('Y-m-d') < '2014-06-08') || in_array(staffOnlyTEST(), array(173,357)) || $_SESSION['preferences']['invoicingEnabled'])
	$invoiceButton = echoButton('', 'New Invoice', "editInvoice($client)", null, null, true);
?>
<table class='shrinkBanner' style='width:100%;'><tr><td style='border-width: 0px;'>Invoices</td><td style='border-width: 0px;text-align:right;'>
<?
  if(!$clientMode) {
		echo $invoiceButton;
		echo "&nbsp;Account Balance: ".balDueDisplay($accountBalance);
	}
?>
</td>
</tr></table>
<?
invoiceListTable(fetchAssociations($sql), 0, 1);

if(staffOnlyTEST() && dbTEST('themonsterminders')) {
	echo "<p>(matt, this is in client-invoices-ajax)<p>";
	foreach(array('2015-12-20', '2016-01-03', '2016-01-17') as $date) {
		$url = "https://leashtime.com/invoice-edit.php?client=$client&asOfDate=$date";
		fauxLink(date('m/d/Y', strtotime($date)), "openConsoleWindow(\"specialinvoice\", \"$url\",700,700)");
		echo "<p>";
	}
}
		

function invoiceStatementNoticeSection($client, $starting=null, $ending=null) {
	$startCondition = $starting ? "AND SUBSTR(datetime, 1, 10) >= '".date('Y-m-d', strtotime($starting))."'" : '';
	$endCondition = $ending ? "AND SUBSTR(datetime, 1, 10) <= '".date('Y-m-d', strtotime($ending))."'" : '';
	$hideStatements = in_array(userRole(), array('o', 'd')) ? '' : "AND hidefromcorresp = 0";
	$statementSubjectTest = fetchRow0Col0("SELECT subject FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email'");
	$statementSubjectTest = $statementSubjectTest ? " OR subject LIKE '%".mysql_real_escape_string($statementSubjectTest)."%' " : '';
	$statements = fetchAssociations(
		"SELECT datetime, msgid, subject, correspaddr, ifnull(transcribed, 'email') as transcribed
		 FROM tblmessage
		 WHERE correspid = $client AND inbound = 0 AND correstable = 'tblclient'
				$startCondition $endCondition
				AND (subject like 'prepayment'
										OR subject like 'invoice' $statementSubjectTest
										OR tags like 'prepayment'
										OR tags like 'billing') 
				$hideStatements
		 ORDER BY datetime DESC");  // should really be [ tags like '%$billingInvoiceTag% ]
		 
	$invoiceStatementColumns = staffOnlyTEST() ? 7 : 4;
	?>
	<p>
	<table style='width:100%;'><tr class='shrinkBanner'><td style='border-width: 0px;' colspan=<?= $invoiceStatementColumns ?>>Invoice Statement Notices</td></tr>
	<?
	$hideableFlag = in_array(userRole(), array('o', 'd')) ? '&statement=1' : '';
	if(!$statements) echo "<tr style=><td style='tipLooks' colspan=3>None found.</td></tr>";
	else {
		$otherCols = staffOnlyTEST() ? "<th style='color:blue'>Prior Unpaid</th><th style='color:blue'>First Date</th><th style='color:blue'>Last Date</th>"
																	: "<th width='50%'>&nbsp;</th>";
		echo "<tr class='sortableListHeader'><th>Date</th><th>Sent</th><th>Subject</th>$otherCols</tr>";
		foreach($statements as $msg) {
			$row = $invoice;
			$row['transcribed'] = $msg['transcribed'] == 'mail' ? 'Printed and mailed' : ($msg['transcribed'] == 'email' ? 'Emailed' : '');
			$row['subject'] = fauxLink($msg['subject'], 
				"openConsoleWindow(\"invoiceview\", \"comm-view.php?id={$msg['msgid']}$hideableFlag\", 800, 800);", 
				1, 'View this invoice statement');
			$row['datetime'] = shortDate(strtotime($msg['datetime'])); // AndTime
			echo "<tr class='sortableListCell'><td>{$row['datetime']}</td><td>{$row['transcribed']}</td><td>{$row['subject']}</td>";
			if(staffOnlyTEST()) {
				$analysis = analyzeInvoiceStatementMessage($msg['msgid']);
				echo "<td>{$analysis['priorunpaid']}</td><td>{$analysis['firstitemdate']}</td><td>{$analysis['lastitemdate']}</td>";
			}
			echo "</tr>";
		}
	}
	echo "</table>";
}

function analyzeInvoiceStatementMessage($msgid) {
	$message = fetchFirstAssoc("SELECT * FROM tblmessage WHERE msgid = $msgid");
	$body = $message['body'];
	$version = findInvoiceVersion($message);
	if($version == 0) {
		$lines = explode("\n", $body);
//if(mattOnlyTEST()) print_r(collectRows($lines));
		$pat = 'Prior Unpaid Charges</label></td>';
		foreach($lines as $i => $line) {
			if($start = strpos($line, $pat)) {
				$invoice['priorunpaid'] = strip_tags(substr($line, $start+strlen($pat)));
			}
			if($futuretask) {
				$end = strpos($line, '</td><td')+strlen('</td>');
				$itemdate = strtotime(strip_tags(substr($line, 0, $end)));  // works for 06/03/2014 and 03.06.2014
				if($firstitemdate) $firstitemdate = min($itemdate, $firstitemdate);
				else $firstitemdate = $itemdate;
				if($lastitemdate) $lastitemdate = max($itemdate, $lastitemdate);
				else $lastitemdate = $itemdate;
				$futuretask = false;
			}
			$futuretask = strpos($line, 'futuretask');
		}
		$invoice['firstitemdate'] = date('m/d/Y', $firstitemdate);
		$invoice['lastitemdate'] = date('m/d/Y', $lastitemdate);
	}
	return $invoice;
}

function collectRows(&$lines) { // unfinished.  try to collect every tr in the page of lines as a separate element.  include rows of subtables.
	$rowOpen = false;
	foreach($lines as $line) {
		while(true) {
			if(!$rowOpen && ($start = strpos($line, '<tr')) !== FALSE) {
				$rowOpen = $start;
				$line = substr($line, $start);
			}
			if(($end = strpos($line, '</tr')) === FALSE) {
				if($rowOpen && ($openstart = strpos($line, '<tr')) !== FALSE) { // subtable row
					$row .= substr($line, $end, $openstart - $end);
					$rows[] = $row;
					$row = '';
					$line = substr($line, $end+strlen('</tr'));
					$rowOpen = false;
				}
				else $row .= $line;
				break;
			}
			else {
				$row .= substr($line, 0, $end);
				$rows[] = $row;
				$row = '';
				$line = substr($line, $end+strlen('</tr'));
				$rowOpen = false;
			}
		}
	}
	return $rows;
}

function findInvoiceVersion($message) {
	return strpos($message['body'], "<td colspan=2 style='font-weight:bold'>Statement") ? 0 : 0;
}


invoiceStatementNoticeSection($client, $starting, $ending);



$sort = $sort ? explode(' ', $sort) : array();
if($sort && $sort[0] == 'date') $sort[0] = 'issuedate,created';
$sort = $sort ? join(' ', $sort) : 'issuedate,created';
$orderby = $sort ? "ORDER BY $sort" : '';
$timeConstraint = '';
if($starting && strtotime($starting)) $timeConstraint = "AND c.issuedate >= '".date('Y-m-d', strtotime($starting))."'";
if($ending && strtotime($ending)) $timeConstraint .= " AND c.issuedate <= '".date('Y-m-d 23:59:59', strtotime($ending))."'";
$hideBookkeepingCredits = userRole() == 'c' ? "AND bookkeeping <> 1" : '';
$hideVoidedCredits = $_SESSION['hidevoidedcredits'] ? "AND voided IS NULL" : '';
$hideSystemCredits = 
	staffOnlyTEST() ? '' 
	: " AND (payment = 1 OR c.reason IS NULL OR (c.reason NOT LIKE '%billable%' AND c.reason NOT LIKE '%(v: %b: %'))";

$sql = "SELECT c.*, c.amount-c.amountused as amountleft, refundid as refundptr 
					FROM tblcredit c
					LEFT JOIN tblrefund ON paymentptr = creditid
					WHERE c.clientptr = $client $timeConstraint $hideBookkeepingCredits $hideVoidedCredits $hideSystemCredits
					$orderby";
//echo "<h3>Credits and Payments</h3>";
?>
<p>
<table class='shrinkBanner' style='width:100%;'><tr><td style='border-width: 0px;'>Credits and Payments</td>
<td style='font-size:1em;border-width:0px;text-align:right;'>
<?
if(!$clientMode) {
	if(staffOnlyTEST()) labeledCheckbox('Hide voided credits/payments.', 'hidevoids', $value=$_SESSION['hidevoidedcredits'], $labelClass=null, $inputClass=null, $onClick='toggleHideVoid()', $boxFirst=false, $noEcho=false, $title=null);
	if(staffOnlyTEST() || dbTEST('doggiewalkerdotcom')) echoButton('', 'Raw History', "document.location.href= \"account-history-by-month.php?client=$client&start=\"+escape(document.getElementById(\"invoiceStart\").value)+\"&end=\"+escape(document.getElementById(\"invoiceEnd\").value)");
	if(staffOnlyTEST() || dbTEST('pawlosophy,doggiewalkerdotcom')) echoButton('', 'Payment History', "openConsoleWindow(\"paymenthistory\", \"payment-history.php?id=$client\", 700, 600)");
	echo " ";
	echoButton('', 'Make Payment', "addCredit($client, 1)");
	if(FALSE || staffOnlyTEST()) {
		echo " ";
		fauxLink("<img src='art/birthday-gift.jpg'>", "addPayment2Stage($client, 1)", 0, "Make dedicated payment"); //payment-edit-2stage.php
	}
	echo " ";
	echoButton('searchForInvoicesButton', 'Issue Credit', "addCredit($client, 0)");
}
?></td></tr></table>
<?
creditListTable(fetchAssociations($sql), 1);

if($clientMode) exit;
// ####################### END CLIENT MODE #################################
?>
<p>
<table class='shrinkBanner' style='width:100%;'><tr><td style='border-width: 0px;'>Refunds</td>
<td style='font-size:1em;border-width:0px;text-align:right;'>
<?
echoButton('', 'Issue Refund', "addRefund($client, 1)");
?></td></tr></table>
<?
$timeConstraint = '';
if($starting && strtotime($starting)) $timeConstraint = "AND issuedate >= '".date('Y-m-d', strtotime($starting))."'";
if($ending && strtotime($ending)) $timeConstraint .= " AND issuedate <= '".date('Y-m-d 23:59:59', strtotime($ending))."'";
$sql = "SELECT tblrefund.* FROM tblrefund WHERE clientptr = $client $timeConstraint";  //  $orderby

refundListTable(fetchAssociations($sql), 1);

?>
<p>
<table class='shrinkBanner' style='width:100%;'><tr><td style='border-width: 0px;'>Gratuities</td>
<td style='font-size:1em;border-width:0px;text-align:right;'>
<?
if(FALSE) echoButton('', 'Issue Gratuity', "addGratuity($client, 1)");
?></td></tr></table>
<?
$timeConstraint = '';
if($starting && strtotime($starting)) $timeConstraint = "AND issuedate >= '".date('Y-m-d', strtotime($starting))."'";
if($ending && strtotime($ending)) $timeConstraint .= " AND issuedate <= '".date('Y-m-d 23:59:59', strtotime($ending))."'";

$sql = "SELECT * FROM tblgratuity WHERE clientptr = $client $timeConstraint ORDER BY clientptr, issuedate ASC";  //  $orderby
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($sql); }

gratuityListTable(fetchAssociations($sql), 1);
?>
<p>
<table class='shrinkBanner' style='width:100%;'><tr><td style='border-width: 0px;'>Miscellaneous Charges</td>
<td style='font-size:1em;border-width:0px;text-align:right;'>
<?
echoButton('', 'Create Miscellaneous Charge', "addMiscellaneousCharge($client, 1)");
?>

</td></tr></table>
<?
$timeConstraint = '';
if($starting && strtotime($starting)) $timeConstraint = "AND issuedate >= '".date('Y-m-d', strtotime($starting))."'";
if($ending && strtotime($ending)) $timeConstraint .= " AND issuedate <= '".date('Y-m-d 23:59:59', strtotime($ending))."'";

$sql = "SELECT * FROM tblothercharge WHERE clientptr = $client $timeConstraint ORDER BY clientptr, issuedate ASC";  //  $orderby
miscellanousChargeTable(fetchAssociations($sql), 1);

function balDueDisplay($val) {
	$color = 'green';
	$credit = '';
	if($val > 0) {
		$color = 'red';
	}
	else if($val < 0) {
		$credit = 'cr';
	}
	if($val) $val = dollarAmount(abs($val)).$credit;
	else $val = 'PAID';
	return "<span id='invoicesaccountbalance' class='accountbalancedisplay' style='color:$color;font-weight:bold;background:white;'> $val </span>";
}

// ***********************************************************
function miscellanousChargeTable($charges, $oneClient=false) {
	if(!$charges) {
		echo "No charges found.";
		return;
	}
	$clientIds = array();
	foreach($charges as $charge) $clientIds[] = $charge['clientptr'];
	$clients = getClientDetails($clientIds);
	$columns = explodePairsLine('issuedate|Date||amount|Amount||note|Note');
	$colSorts = $oneClient ? array('date'=>null) : array();
	if($oneClient) {
		unset($columns['client']);
	}
	
	$providers = getProviderShortNames();
	$lastClient = 0;
	$lastIssuedate = 0;
	$lastPaymentptr = 0;
	$rows = array();
	foreach($charges as $charge) {
		$row = array(
											'issuedate' => shortDate(strtotime($charge['issuedate'])),
											'note' => $charge['reason'],
											'amount' => chargeLink($charge));
		$lastClient = $charge['clientptr'];
		$lastIssuedate = $charge['issuedate'];
	//$row['client'] = fauxLink($clients[$tipGroup['clientptr']]['clientname'], "viewClient({$tipGroup['clientptr']})", 'View this client', 1);
		$rows[] = $row;
	}
	//$colClasses['amount'] = 'amountcolumn';
	$colClasses = array('amount'=>'dollaramountheader');
	//echo "<style>.amountcolumn {width: 150px;}</style>\n";
	tableFrom($columns, $rows, 'WIDTH=100% ',null,null,null,null,$colSorts,null, $colClasses, 'sortInvoices');
}

function chargeLink($charge) {
	$time = strtotime($charge['issuedate']);
	return fauxLink(dollarAmount($charge['amount']), 
		"editCharge({$charge['chargeid']})", 1, "View this miscellaneous charge.");
}

