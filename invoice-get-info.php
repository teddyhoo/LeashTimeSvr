<?
/* invoice-get-info.php
*
* Parameters: 
* id - id of client
* asOfDate
*
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "invoice-gui-fns.php";
require_once "gratuity-fns.php";
require_once "cc-processing-fns.php";

// Verify login information here
locked('o-');

$id = $_REQUEST['id'];
$asOfDate = $_REQUEST['asOfDate'];
$throughDateInt = strtotime("- 1 day", strtotime($asOfDate));

$uninvoicedCharges = dollarAmount(getUninvoicedCharges($id, date('Y-m-d', $throughDateInt)));
$incompleteJobCount = countAllClientIncompleteJobs($id, $asOfDate);

$acctBal = getAccountBalance($id, true, false);
$acctBal = $acctBal ? dollarAmount($acctBal) : 0;
$invoice = fetchFirstAssoc("SELECT * FROM tblinvoice WHERE clientptr = $id ORDER BY invoiceid DESC LIMIT 1");
//print_r($invoice);
if($invoice) {
	$invoiceid = $invoice['invoiceid'];
	$invoiceLabel = invoiceIdDisplay($invoiceid);
	$paid = $invoice['paidinfull'] ? 1 : 0;
	$throughdate = shortDate(strtotime($invoice['asofdate']));
	$currinv = $invoice['subtotal'];
	$currinv = $currinv ? dollarAmount($currinv) : 0;
	$amountdue = $invoice['origbalancedue'] -  ($invoice['ccpayment'] ? $invoice['ccpayment'] : 0);
	$amountdue = $amountdue ? dollarAmount($amountdue) : 0;
}
$totalAcctBal = dollarAmount(getInvoicedAccountBalanceTotal());
echo "acountbalance|$acctBal|invoice|$invoiceid|invoicelabel|$invoiceLabel|paid|$paid|throughdate|$throughdate|"
			."currinv|$currinv|amountdue|$amountdue|uninvoiced|$uninvoicedCharges|incompleteJobs|$incompleteJobCount|"
			."totalacctbal|$totalAcctBal";

//$cols = array_flip(explode(',', 'cb,clientname,acountbalance,invoice,throughdate,currinv,amountdue,uninvoiced,incompleteJobs'));
