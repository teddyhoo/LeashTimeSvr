<? // invoice-fns.php
/*
An invoice shows what was owed on a particular date.
It includes:
	A total balance due on the invoice date.
	Any past due invoices with amounts due on the invoice date.
	Itemized new billables with amounts due on the invoice date.
*/

/*
reset:

DELETE FROM relinvoiceitem;
DELETE FROM relpastdueinvoice;
DELETE FROM tblbillable;
DELETE FROM tblinvoice;
DELETE FROM relbillablepayment;
DELETE FROM tblcredit;



DELETE FROM tblbillable WHERE itemtable = 'tblservicepackage';

// find all monthly packages
$mps = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly");
// find all completed appts which are not monthly and which are unbilled
$appts = fetchAssociations("SELECT tblappointment.clientptr, appointmentid, tblappointment.charge+ifnull(adjustment,0) as charge
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
					WHERE completed AND itemptr IS NULL AND packageptr NOT IN (".join(',', $mps).")");
// create billables for these appointments
foreach($appts as $appt)
	doQuery("INSERT INTO tblbillable SET clientptr={$appt['clientptr']}, itemptr={$appt['appointmentid']}, itemtable='tblappointment',
						charge='{$appt['charge']}', itemdate='{$appt['date']}', billabledate='{$appt['date']}');

*/
require_once "credit-fns.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "tax-fns.php";

/*require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";


$preferences = $_SESSION['preferences'];
//$invoiceid = createCustomerInvoice(8, '2009-4-19');
displayLatestInvoice(8);
*/
/* Invoice structure:
CREATE TABLE IF NOT EXISTS `tblinvoice` (
  `invoiceid` int(10) unsigned NOT NULL auto_increment,   
  `date` date NOT NULL default '0000-00-00',  // invoice creation date
  `asofdate` date NOT NULL,										// latest billable itemdate 
  `clientptr` int(10) unsigned NOT NULL default '0',
  `notification` varchar(45) NOT NULL default '' COMMENT 'mail|email',
  `subtotal` float(6,2) NOT NULL,							// sum of billable charges
  `pastbalancedue` float(6,2) NOT NULL,				// balance due on prior invoies
  `balancedue` float(6,2) NOT NULL,						// entire balance due on invoice.  updated as credits/payments received
  `creditsapplied` float(6,2) default NULL COMMENT 'credits applied at the time of invoice generation',
  `paidinfull` date default NULL,							// date on which invoice is all paid up
  `lastsent` date default NULL,								// date invoice last sent
  PRIMARY KEY  (`invoiceid`)
*/


function billingCron($force=false) {
	// runs once a day
	global $preferences;
	if(!$preferences && isset($_SESSION)) $preferences = $_SESSION['preferences'];  // for testing in a session
	
	
	/* As of 2009-08-24, all appointment billables (except for monthly packages) are generated when an appt is completed
	if($force || date("j") == $preferences['bimonthlyBillOn1'] || date("j") == $preferences['bimonthlyBillOn2']) {
		$clientids = fetchCol0("SELECT clientid FROM tblclient"); // both active and inactive -- the inactive may owe you money
		if($clientids) {
			$yesterday = date('Y-m-d', strtotime("-1 day"));
			//foreach($clientids as $clientid) createRegularBillables($clientid, $yesterday);
		}
	}
	*/
	$monthlyBillOn = $preferences['monthlyBillOn'] ? $preferences['monthlyBillOn'] : 0;
	if($force 
		|| date("j") == $monthlyBillOn    // today is the monthly bill date
		|| (date("j") == date("t") && $monthlyBillOn > date("t")) ) {  // today is the last day of the month and the monthly bill date is greater
		// find current unbilled monthly contracts and whether they are postpaid
		$packages = fetchAssociationsKeyedBy(
				"SELECT rpack.*, monthyear
					FROM tblrecurringpackage rpack
					LEFT JOIN tblbillable tbill ON itemptr = packageid AND tbill.clientptr = rpack.clientptr AND itemtable = 'tblrecurringpackage'
					WHERE current = 1 AND monthly = 1
					ORDER BY monthyear", 'clientptr');
		foreach($packages as $package) createMonthlyBillable($package);
	}
}

function getInvoice($invoiceid) {
	return fetchFirstAssoc("SELECT * FROM tblinvoice WHERE invoiceid = $invoiceid");
}

function lastInvoiceDatesByClient() {
	return fetchKeyValuePairs("SELECT clientptr, date FROM tblinvoice ORDER BY date", 'clientptr');
}

function currentInvoice($clientid) {
	return fetchFirstAssoc("SELECT * FROM tblinvoice WHERE clientptr = $clientid ORDER BY invoiceid DESC LIMIT 1");
}

function getUninvoicedCharges($client=null, $asOfDate) {
	//$minusCredits = $minusCredits ? "- paid" : '';
	$asOfDate = $asOfDate ? "AND itemdate <= '".date('Y-m-d', strtotime($asOfDate))."'" : '';
	$filter = !$client ? "" : (is_numeric($client) ? "AND tblbillable.clientptr = $client" : $client);
	$sql = "SELECT tblbillable.clientptr, sum(tblbillable.charge - IFNULL(tblbillable.paid, 0)) as total
					FROM tblbillable
					WHERE invoiceptr IS NULL AND superseded = 0 $filter $asOfDate
					GROUP BY clientptr";

	if(!$client || !is_numeric($client)) return fetchKeyValuePairs($sql, 1);
	else {
		$row = fetchFirstAssoc($sql);
		return $row ? $row['total'] : $row;
	}
}

function getAllBillableCharges($client, $minusCredits='') {
	$minusCredits = $minusCredits ? "- paid" : '';
	return fetchRow0Col0("SELECT sum(charge $minusCredits) FROM tblbillable WHERE clientptr = $client");
}


function getInvoicedAccountBalanceTotal() {
	return fetchRow0Col0("SELECT sum(charge - paid) FROM tblbillable WHERE superseded = 0"); // invoiceptr != 0 AND 
	//return fetchRow0Col0("SELECT sum(balancedue) FROM tblinvoice");
	
}

//getAccountBalance($client, $includeCredits=true, $allBillables=false)
function getAccountBalance($client, $includeCredits=false, $allBillables=false) {
	//$filter = $allBillables ? "clientptr = $client" : "tblbillable.clientptr = $client AND invoiceptr IS NOT NULL";
	$filter = "clientptr = $client AND superseded = 0";
	//$balanceDue = fetchRow0Col0("SELECT sum(charge - paid) FROM tblbillable WHERE $filter");
	$billables = fetchAssociations("SELECT * FROM tblbillable WHERE $filter AND charge - paid > 0");
//if(staffOnlyTEST()) foreach($billables as $b) echo print_r($b, 1).'<p>';	
	$balanceDue = 0;
	if($billables) {
		foreach($billables as $billable) $balanceDue += $billable['charge'] - $billable['paid'];  // includes tax
	}
	

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {  echo "Billables: $$balanceDue<br>";}
	if($includeCredits) {
		$balanceDue -= fetchRow0Col0("SELECT sum(amount - amountused) FROM tblcredit WHERE clientptr = $client");
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {  echo "- credits: $".print_r(fetchAssociations("SELECT creditid, payment, amount, amountused FROM tblcredit WHERE clientptr = $client", 1))."<br>";}
	
	
	return $balanceDue;
}

function getInvoiceAccountBalance($client, $includeCredits=false, $allBillables=false) {
	//$filter = $allBillables ? "clientptr = $client" : "tblbillable.clientptr = $client AND invoiceptr IS NOT NULL";
	//$balanceDue = fetchRow0Col0("SELECT sum(charge - paid) FROM tblbillable WHERE $filter");
	
	if(TRUE || staffOnlyTEST()) { // published 7/21/2015 (after getInvoiceAccountBalance fix)
		$filter = $allBillables ? "1=1" : "1=1 AND invoiceptr IS NOT NULL";
		global $balanceDueCache; // it is hardly slower to get sum(charge - paid) for ALL clients than for 1 client
		$allBillablesIndex = $allBillables ? 'allBillables' : 'withinvoices';
		if(!$balanceDueCache[$allBillablesIndex]) {
			$balanceDueCache[$allBillablesIndex] = fetchKeyValuePairs("SELECT clientptr, sum(charge - paid) FROM tblbillable WHERE $filter AND superseded = 0 GROUP BY clientptr");
			//screenLog("balanceDueCache[$allBillablesIndex]: ".print_r($balanceDueCache[$allBillablesIndex],1));
		}
		$balanceDue = $balanceDueCache[$allBillablesIndex][$client];
	}
	else { // OLD version -- scales poorly and very slow
		$filter = $allBillables ? "clientptr = $client" : "tblbillable.clientptr = $client AND invoiceptr IS NOT NULL";
		$billables = fetchAssociations("SELECT * FROM tblbillable WHERE $filter AND superseded = 0 AND charge - paid > 0");
	//if($client == 320) print_r($billables);	
		$balanceDue = 0;
		if($billables) {
			foreach($billables as $billable) $balanceDue += $billable['charge'] - $billable['paid'];  // includes tax
		}
	}
	
	
//if(mattOnlyTEST() && $client == 1290) echo "balanceDue [$balanceDue] + tax [$tax]<p>";
	if($includeCredits) {
		$balanceDue -= fetchRow0Col0("SELECT sum(amount - amountused) FROM tblcredit WHERE clientptr = $client");
	}
	return $balanceDue;
}

/*function getAccountBalance($client, $includeCredits=false, $allBillables=false) {
	$filter = $allBillables ? "clientptr = $client" : "tblbillable.clientptr = $client AND invoiceptr IS NOT NULL";
	//$balanceDue = fetchRow0Col0("SELECT sum(charge - paid) FROM tblbillable WHERE $filter");
	$billables = fetchAssociations("SELECT * FROM tblbillable WHERE $filter AND charge - paid > 0");
//if($client == 320) print_r($billables);	
	$balanceDue = 0;
	if($billables) {
		foreach($billables as $billable) $balanceDue += $billable['charge'] - $billable['paid'];  // includes tax
	}
	if($includeCredits) {
		$balanceDue -= fetchRow0Col0("SELECT sum(amount - amountused) FROM tblcredit WHERE clientptr = $client");
	}
	return $balanceDue;
}*/


function getAccountBalanceIncludingBillablesAsOf($client, $asOfDate, $allBillables=false) {
	$filter = $allBillables ? "clientptr = $client" : "tblbillable.clientptr = $client AND invoiceptr IS NOT NULL";
	//$balanceDue = fetchRow0Col0("SELECT sum(charge - paid) FROM tblbillable WHERE $filter");
	$billables = fetchAssociations("SELECT * FROM tblbillable WHERE $filter AND itemdate <= '".date('Y-m-d', strtotime($asOfDate))."'");
	$balanceDue = 0;
	if($billables) {
		foreach($billables as $billable) $balanceDue += $billable['charge'] - $billable['paid']; // includes tax
	}
	$balanceDue -= fetchRow0Col0("SELECT sum(amount - amountused) FROM tblcredit WHERE clientptr = $client");
	return $balanceDue;
}






// INVOICE CREATION FUNCTIONS
function createCustomerInvoiceAsOf($clientid, $asOfDate, $ccPayment=null) {
	$conditions = "AND itemdate <= '".date('Y-m-d', strtotime($asOfDate))."'";
	$onlyTheseBillables = array();
	foreach(($billables = getUninvoicedBillables($clientid, $conditions)) as $billable) 
		$onlyTheseBillables[] = $billable['billableid'];
	return createCustomerInvoice($clientid, $onlyTheseBillables, null, $ccPayment, $asOfDate);
}	
	
	
function createCustomerInvoice($clientid, $onlyTheseBillables=null, $onlyTheseCanceledVisits=null, $ccPayment=null, $asOfDate=null) {
	// this function is called when the user has chosen which billables to include in the invoice
	//createInvoicePreview($clientid, $onlyTheseBillables=null, $forceCreation=false, $asOfDate=null, $ccPayment=null)
	$asOfDate = $asOfDate ? date('Y-m-d', strtotime($asOfDate)) : null;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo $asOfDate;exit; }
	$invoice = createInvoicePreview($clientid, $onlyTheseBillables, 'forcecreation', $asOfDate, $ccPayment);
	// find prior invoices with unpaid balances [array(invoiceid=>balance, ...)] and
	$unpaidInvoiceIds = fetchCol0("SELECT invoiceid FROM tblinvoice WHERE clientptr = $clientid AND paidinfull IS NULL");
	$invoice['lastsent'] = date('Y-m-d');
	$invoice['date'] = date('Y-m-d H:i:s');
	
	$dueDateChoice = $_SESSION['preferences']['statementsDueOnPastDueDays'];
	$dateDue = $_SESSION['preferences']['pastDueDays'];
	if(!$dateDue) $dateDue = "0";
	if(!$dueDateChoice || $dueDateChoice == 'Upon Receipt') $dateDue = "0";
	$invoice['duedate'] = date('Y-m-d', strtotime("+ $dateDue days")) ;  // duedate MUST be a date

	$onlyTheseBillables = 
		$onlyTheseBillables 
		? "AND billableid IN (".join(',', $onlyTheseBillables).")" 
		: ($asOfDate ? "AND itemdate <= '$asOfDate'" : '');
	$billables = getUninvoicedBillables($clientid, $onlyTheseBillables); // generate billables before invoice to avoid incorrect exclusions hbased on asofdate

	$invoiceid = insertTable('tblinvoice', $invoice, 1);
	foreach($unpaidInvoiceIds as $oldInvoice) 
		insertTable('relpastdueinvoice', array('currinvoiceptr'=>$invoiceid, 'oldinvoiceptr'=>$oldInvoice), 1);
		// for each new billable, create an invoice item
	
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { logChange(999, 'test1', 'm', $note=print_r($billables,1)."[client>>$clientid][$onlyTheseBillables]");}	
	foreach($billables as $billable) {
		insertTable('relinvoiceitem', 
								array('invoiceptr'=>$invoiceid, 'billableptr'=>$billable['billableid'],
											'charge'=>$billable['owed'], 'prepaidamount'=>$billable['paid'], 'clientptr'=>$clientid,
											'description'=>invoiceItemDescriptionForBillable($billable)),1);
		updateTable('tblbillable', array('invoiceptr'=>$invoiceid), "billableid = {$billable['billableid']}", 1);
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { logChange(999, 'test2', 'm', $note=print_r($billables,1));}	
	if($onlyTheseCanceledVisits) foreach($onlyTheseCanceledVisits as $visitptr) 
		insertTable('relinvoicecan', 
								array('invoiceptr'=>$invoiceid, 'visitptr'=>$visitptr),1);
	// renderInvoice(invoiceid, invoiceitems, oldInvoices, past balance due)
	
	foreach(fetchClientCreditsSinceLastInvoice($clientid) as $credit)
		if(!$credit['bookkeeping'])
			insertTable('relinvoicecredit', array('invoiceptr'=>$invoiceid, 'creditptr'=>$credit['creditid']), 1);
		
	require_once "refund-fns.php";
//if($_SESSION['staffuser']) 
	foreach(fetchClientRefundsSinceLastInvoice($clientid) as $refund)
		insertTable('relinvoicerefund', array('invoiceptr'=>$invoiceid, 'refundptr'=>$refund['refundid']), 1);
	
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { logChange(999, 'test3', 'm', $note=print_r($billables,1));}	

	//clearInvoicePreviewEmailAttempt($clientid);
	deleteTable('tblemailedinvoicepreview', "clientptr = $clientid", 1);
	return $invoiceid;
}

	
	
function createInvoicePreview($clientid, $onlyTheseBillables=null, $forceCreation=false, $asOfDate=null, $ccPayment=null) {
	global $lastInvoiceDatesByClient;
	
	if(!isset($lastInvoiceDatesByClient)) $lastInvoiceDatesByClient = lastInvoiceDatesByClient();
	/*
To build a preview of an invoice (or collect lineitems for an invoice):
1. Add up unpaid invoices and calculate Previous balance
2. Use all uninvoiced billables to calculate subtotal
3. Consume credits to pay billables and calculate payments and credits
4. Find all uninvoiced billables
5. Collect all credits/payments between last two billing dates
*/
	//$ccPayment format: amount|transactionId|ccid|company|last4|acctid (alt)


	// find prior invoices with unpaid balances [array(invoiceid=>balance, ...)] and
	$unpaidInvoiceIds = fetchCol0("SELECT invoiceid FROM tblinvoice WHERE clientptr = $clientid AND paidinfull IS NULL");
	$priorInvoiceBalances = array();
	foreach($unpaidInvoiceIds as $oldInvoice) {
		$priorInvoiceBalances[$oldInvoice] = getUnpaidInvoiceBalance($oldInvoice);
	}
	$accountBalance = array_sum($priorInvoiceBalances);	
	$pbdField = dbTEST('leashtimecustomers,themonsterminders') ? 'subtotal' : 'origbalancedue';
	$pastBalanceDue = fetchRow0Col0("SELECT $pbdField FROM tblinvoice WHERE clientptr = $clientid ORDER BY date DESC, invoiceid DESC LIMIT 1");
	if(!$pastBalanceDue) $pastBalanceDue = 0;
	// create new billables
	//$billables = createBillables($clientid, $asOfDate, $monthly); billables are created by the cron job or when schedules are saved
	$conditions = array();
	if($onlyTheseBillables) $conditions[] = "billableid IN (".join(',', $onlyTheseBillables).")";
	if($asOfDate) $conditions[] = "itemdate <= '".date('Y-m-d', strtotime($asOfDate))."'";
	$onlyTheseBillables = $conditions ? "AND ".join(' AND ', $conditions) : '';

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "[$onlyTheseBillables]";}	
	$billables = getUninvoicedBillables($clientid, $onlyTheseBillables); // Include paid billables for subtotal.  -- AND charge > paid 
}	

	
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { foreach($billables as $b) echo print_r($b, 1).'<p>';exit;}	
//foreach($billables as $b) echo print_r($b,1).'<p>';exit;	
	$subtotal = 0;
	$actualSubtotal = 0;  // for testing
	$lastDate = '1970-01-01';
	$unpaidSubtotal = 0;
	$tax = figureTaxForBillables($billables);
//foreach($billables as $b) echo print_r($b,1).'<br>';	
//echo "TAX: $tax";exit;	
//foreach($billables as $b) echo print_r($b, 1).'<br>';
if($_SESSION['staffuser'] && $_REQUEST['billables']==1) echo "<table><tr><td>Billable<td>Date<td>Item<td>Charge<td>Paid";
	foreach($billables as $billable) {
		$subtotal += $billable['charge'];
if($_SESSION['staffuser'] && $_REQUEST['billables']==1) echo "<tr><td>{$billable['billableid']}<td>{$billable['itemdate']}<td>{$billable['itemptr']}<td>{$billable['charge']}<td>{$billable['paid']}\n";
		$unpaidSubtotal += $billable['charge'] - $billable['paid'];
		$lastDate = date('Y-m-d', max(strtotime($lastDate), strtotime($billable['itemdate'])));
	}
	if(!$asOfDate) $asOfDate = date('Y-m-d');
	$unpaidSubtotal -= $tax;
	if($subtotal == 0 && !$forceCreation) return null;
	//$creditsApplied = consumeClientCredits($clientid, $balanceDue); // credits are now applied upon credit creation and billable creation
	$creditsApplied = 0;
	$creditsApplied = getTotalClientCreditsSinceLastInvoice($clientid, 'excludesystemcredits');
	if($ccPayment && dbTEST('leashtimecustomers,themonsterminders')) {
		$ccPaymentArray = explode('|', $ccPayment);
		$creditsApplied -= $ccPaymentArray[0];
	}
	
	$discountInfo = getDiscountInfoFromBillables($billables); // array('label'=>, 'amount'=>)
	//if($discountInfo)  $unpaidSubtotal += $discountInfo['amount'];  Already applied
	
//echo "unpaidSubtotal $unpaidSubtotal accountBalance: $accountBalance";
	//$origBalanceDue = $unpaidSubtotal + $pastBalanceDue + $tax;
	$balanceDue = max(0, $unpaidSubtotal + $accountBalance + $tax - getUnusedClientCreditTotal($clientid));
	$origBalanceDue = max(0, $unpaidSubtotal + $accountBalance + $tax - getUnusedClientCreditTotal($clientid));
//if(staffOnlyTEST()) echo "unpaidSubtotal: [".$unpaidSubtotal."]<br>subtotal: [".$subtotal."]<br>origBalanceDue: [".$origBalanceDue."]<br>";
	
if(TRUE && $_SESSION['staffuser']) { 
	echo "priorInvoiceBalances = ".print_r($priorInvoiceBalances,1)."<p>";
	echo "$origBalanceDue(origBalanceDue) = $unpaidSubtotal(unpaidSubtotal) + $accountBalance(accountBalance) + $tax(tax) - max(0, getUnusedClientCreditTotal()[".getUnusedClientCreditTotal($clientid)."])<p>";
	}	
if($_SESSION['staffuser'] && $_REQUEST['billables']==1) echo "</table>";
	
	// $balanceDue -= $creditsApplied;  -- credits were applied as billables and credits were created
	$subTotalToSave = dbTEST('leashtimecustomers,themonsterminders') ? $subtotal :  $unpaidSubtotal;
	$invoice = array(
			'asofdate'=>date('Y-m-d', strtotime($asOfDate)),
			'clientptr'=>$clientid, 
			'subtotal'=> (!$subTotalToSave ? '0.0' : $subTotalToSave), //(!$subtotal ? '0.0' : $subtotal),
			'tax'=>(!$tax ? '0.0' : $tax),
			'pastbalancedue'=>"$pastBalanceDue", 
			'origbalancedue'=>($origBalanceDue ? $origBalanceDue : '0.0'), 
			'balancedue'=>($balanceDue ? $balanceDue : '0.0'), 
			'creditsapplied'=>$creditsApplied,
			'ccpayment'=>$ccPayment);
	if($_SESSION['discountsenabled']) {
		$invoice['discountlabel'] = $discountInfo ? $discountInfo['label'] : '';  // discount is folded into subtotal, and listed as a lineitem
		$invoice['discountamount'] = $discountInfo ? $discountInfo['amount'] : '';  // discount is folded into subtotal, and listed as a lineitem
	}
			
//echo "UNPAID: $ $unpaidSubtotal pastBalanceDue: $ $pastBalanceDue";exit;
//print_r($invoice);exit;			
	if(!$origBalanceDue) $invoice['paidinfull'] = date('Y-m-d');
	//$invoiceid = insertTable('tblinvoice', $invoice, 1);
	//foreach($unpaidInvoiceIds as $oldInvoice) 
		//insertTable('relpastdueinvoice', array('currinvoiceptr'=>$invoiceid, 'oldinvoiceptr'=>$oldInvoice), 1);
		// for each new billable, create an invoice item
	//foreach($billables as $billable) 
		//insertTable('relinvoiceitem', 
								//array('invoiceptr'=>$invoiceid, 'billableptr'=>$billable['billableid'], 'charge'=>$billable['charge'], 'clientptr'=>$clientid),1);
	return $invoice;
}

function getDiscountInfoFromBillables($billables) {
	if(!$_SESSION['discountsenabled']) return null;
	require_once "discount-fns.php";
	if(!$billables) return;
	// collect appointmentids from billables
	$ids = array();
	foreach($billables as $billable)
		if($billable['itemtable'] == 'tblappointment')
			$ids[] = $billable['itemptr'];
	return aggregateDiscountInfo($ids);
}

function getCanceledVisits($client, $currentAsOf) {
	// ... since last invoice as-of date
	$lastInvoiceAsOf = fetchRow0Col0("SELECT asofdate FROM tblinvoice WHERE clientptr = $client ORDER BY asofdate DESC LIMIT 1");
	if(!$lastInvoiceAsOf) $lastInvoiceAsOf = date('Y-m-d', strtotime("-30 days"));
	$canned = fetchAssociationsKeyedBy(
		"SELECT * FROM tblappointment 
		 WHERE  clientptr = $client 
		 	AND canceled IS NOT NULL 
		 	AND date > '$lastInvoiceAsOf'
			AND date <= '$currentAsOf'", 'appointmentid');
	$monthlyPacks = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE clientptr = $client AND monthly =1");
	foreach($canned as $i => $appt)
		if(in_array($appt['packageptr'], $monthlyPacks))
			$canned[$i]['monthly'] = 1;
	return $canned;
}

function getCanceledVisitsForInvoice($invoiceptr, $client) {
	// ... since last invoice as-of date
	$lastInvoiceAsOf = fetchRow0Col0("SELECT asofdate FROM tblinvoice WHERE clientptr = $client ORDER BY asofdate DESC LIMIT 1");
	if(!$lastInvoiceAsOf) $lastInvoiceAsOf = date('Y-m-d', strtotime("-30 days"));
	$canned = fetchAssociationsKeyedBy(
		"SELECT tblappointment.* 
			FROM relinvoicecan
			LEFT JOIN tblappointment ON appointmentid = visitptr
		 	WHERE invoiceptr = $invoiceptr", 'appointmentid');
	if($canned) {		
		$client = current($canned);
		$client = $client['clientptr'];
		$monthlyPacks = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE clientptr = $client AND monthly =1");
		foreach($canned as $i => $appt)
			if(in_array($appt['packageptr'], $monthlyPacks))
				$canned[$i]['monthly'] = 1;
	}
	return $canned;
}

function getPriorUnpaidInvoices($invoice) {
	return fetchCol0(
		"SELECT oldinvoiceptr, balancedue, date
			FROM relpastdueinvoice 
			JOIN tblinvoice ON invoiceid = oldinvoiceptr
			WHERE currinvoiceptr = {$invoice['invoiceid']}
			ORDER BY DATE");
}

function getUnpaidInvoiceBalance($invoiceid, $includePriorInvoices=null) {
	// check balances of invoiceitems
	$allinvoices = array();
	if($includePriorInvoices) {
		$allinvoices = fetchCol0("SELECT oldinvoiceptr FROM relpastdueinvoice WHERE currinvoiceptr = $invoiceid");
	}
	$allinvoices[] = $invoiceid;
	$allinvoices = join(',', $allinvoices);
	$billables = fetchAssociations("SELECT * FROM tblbillable WHERE superseded = 0 AND invoiceptr IN ($allinvoices)");
	$sum = 0;
	foreach($billables as $billable) $sum += $billable['charge'] - $billable['paid'];  // includes tax
	return $sum;
}

function registerCustomerPayment($clientid, $amount) {
	//When a client pays, we cycle through client’s billables where charge > paid
	//   Augment billable’s paid amount until it equals charge, decrementing payment accordingly
	//   Repeat until payment is exhausted
	// for each billable for invoiceitem where clientptr = $clientid
	//    if charge - paid > 0 unpaidinvoices[] = invoiceptr
	// unpaidinvoices = array_unique(unpaidinvoices)
	// update tblinvoice set paidinfulldate = currdate()
	//		where paidinfulldate = null
	//				and clientptr = $clientid
	//				and invoiceid not in (unpaidinvoices)
}

function checkInvoicePaid($invoiceid) {
	//$invoicePaidInFull = fetchRow0Col0("SELECT paidinfull FROM tblinvoice where invoiceid = $invoiceid LIMIT 1");
	$invoicePaidInFull = false;  // checked before we got here
	if($invoicePaidInFull) return $invoicePaidInFull;
	else {
		/*$owed = fetchRow0Col0("SELECT sum(ifnull(tblbillable.charge,0) - ifnull(paid,0)) as owed 
																		FROM tblbillable
																		WHERE superseded = 0 AND invoiceptr = $invoiceid AND tblbillable.charge > 0");

		$owed += fetchRow0Col0("SELECT sum(balancedue)
														FROM tblinvoice
														LEFT JOIN relpastdueinvoice ON oldinvoiceptr = invoiceid
														WHERE currinvoiceptr = $invoiceid");

if(mattOnlyTEST()) */
		$owed = unpaidAmount($invoiceid);
		$predecessorID = fetchRow0Col0("SELECT oldinvoiceptr FROM relpastdueinvoice WHERE currinvoiceptr = $invoiceid ORDER BY oldinvoiceptr DESC LIMIT 1");
		if($predecessorID) 
			$owed += fetchRow0Col0("SELECT balancedue FROM tblinvoice WHERE invoiceid = $predecessorID LIMIT 1", 1);
		

																		
		$update = array('balancedue'=>$owed);
		if($owed <= 0)  $update['paidinfull'] = date('Y-m-d');
		updateTable('tblinvoice', $update, "invoiceid = $invoiceid", 1);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { 
		$successor = fetchRow0Col0("SELECT currinvoiceptr FROM relpastdueinvoice WHERE oldinvoiceptr = $invoiceid LIMIT 1");
		if($successor) checkInvoicePaid($successor);
//}
		return $owed ? null : date('Y-m-d');
	}
}

function unpaidAmount($invoiceid) {
	$inv = fetchFirstAssoc("SELECT * FROM tblinvoice WHERE invoiceid = $invoiceid");
	$billables = fetchAssociations(
		"SELECT b.* 
			FROM relinvoiceitem r
			LEFT JOIN tblbillable b ON billableid = billableptr 
			WHERE r.invoiceptr = $invoiceid");

	foreach($billables as $item) {
		echo "<br>"."{$item['charge']} - {$item['paid']} = ".($item['charge'] - $item['paid']);
		$due += $item['charge'] - $item['paid'];
	}
	return $due;
}

function getCurrentBillables($clientid, $monthly=false) {
	$monthlyConstraint = !$monthly ? '' : "AND itemtable = 'tblrecurringpackage'";
	return fetchAssociations("SELECT * FROM tblbillable 
														WHERE clientptr = $clientid AND superseded = 0 AND charge > paid $monthlyConstraint
														ORDER BY itemdate");
}

function getCurrentBillablesInvoicedFirst($clientid, $monthly=false) {
	$monthlyConstraint = !$monthly ? '' : "AND itemtable = 'tblrecurringpackage'";
	return fetchAssociations("SELECT tblbillable.*, if(invoiceptr IS NULL, 1, 0) as uninvoiced 
														FROM tblbillable
														WHERE tblbillable.clientptr = $clientid AND superseded = 0 AND tblbillable.charge > tblbillable.paid $monthlyConstraint
														ORDER BY uninvoiced asc, itemdate asc");
}

function test_msec() {$t=explode(" ", microtime());return $t[0];}

function getUninvoicedBillableTotals($clientid, $constraint='') {
	//	$subtotal += $billable['charge']; $unpaidSubtotal += $billable['charge'] - $billable['paid']; $asOfDate = date('Y-m-d', max(strtotime($asOfDate), strtotime($billable['itemdate'])));
	global $uninvoicedBillableTotals;
	if(!$uninvoicedBillableTotals) { //, sum(ifnull(bill.paid,0)) as paid
		$sql = "SELECT clientptr, sum(bill.charge) as subtotal, sum(bill.charge - ifnull(bill.paid,0)) as unpaidSubtotal, max(itemdate) as asOfDate
						FROM tblbillable bill
						WHERE (invoiceptr IS $NOT NULL) AND superseded = 0 $constraint
						GROUP BY clientptr";
		$uninvoicedBillableTotals = fetchAssociationsKeyedBy($sql, 'clientptr');
		///screenLog("uninvoicedBillableTotals: ".print_r($uninvoicedBillableTotals,1));
	}
	return $uninvoicedBillableTotals[$clientid];
}





function getUninvoicedBillables($clientid, $constraint='') {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { $NOT = 'NOT NULL OR invoiceptr IS '; }	
	$sql = "SELECT bill.*, if(bill.charge - ifnull(bill.paid > 0, 0) > 0, bill.charge - if(bill.paid > 0, bill.paid, 0), 0) as owed, 
					billableid as billableptr 
					FROM tblbillable bill
					WHERE bill.clientptr = $clientid AND (invoiceptr IS $NOT NULL) AND superseded = 0 $constraint
					ORDER BY itemdate asc";
													if(FALSE && $_SERVER['REMOTE_ADDR'] == '68.225.89.173') { 
														$sql = "SELECT tblbillable.*, tblbillable.charge - ifnull(tblbillable.paid, 0) as owed, billableid as billableptr,
																			if(itemtable = 'tblappointment', starttime, 0) as starttime 
																		FROM tblbillable
																		LEFT JOIN tblappointment ON itemtable = 'tblappointment' AND appointmentid = itemptr
																		WHERE tblbillable.clientptr = $clientid AND (invoiceptr IS $NOT NULL) AND superseded = 0 $constraint
																		ORDER BY itemdate asc, starttime asc"	;
														echo "$sql<p>";
													}
	$billables = fetchAssociations($sql);
if(mattOnlyTEST()) {print_r($sql.'<p>'); }
 }
//if($_SESSION['staffuser']) {echo "$sql<p>"; foreach($billables as $b) echo print_r($b, 1).'<br>'; }	
	$lastInvoiceDate = fetchRow0Col0("SELECT asofdate FROM tblinvoice WHERE clientptr = $clientid ORDER BY date DESC LIMIT 1");
	if($lastInvoiceDate) $lastInvoiceDate = date('Y-m-d', strtotime($lastInvoiceDate));

 }	
	if($lastInvoiceDate) {
		$allowAllMiscCharges = true; //dbTEST('leashtimecustomers');
		foreach($billables as $i => $b) {
			if($allowAllMiscCharges && $b['itemtable'] == 'tblothercharge') ; // NO-OP
			else if(strcmp($b['itemdate'], $lastInvoiceDate) <= 0 && (($b['charge'] - $b['paid']) < .01))
				unset($billables[$i]);
		}
	}
//if($_SESSION['staffuser']) { echo '<hr>'; foreach($billables as $b) echo print_r($b, 1).'<br>'; }	
//if($_SESSION['staffuser']) { echo '<hr>'; foreach($billables as $b) echo "{$b['itemdate']}: [".strcmp($b['itemdate'], $lastInvoiceDate)."] {$b['charge']} - {$b['paid']}: ".print_r($b['charge'] - $b['paid'], 1).'<br>'; }	
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { logChange(999, 'getUninvoicedBillables', 'm', $note=test_msec().': Invoice date'.$lastInvoiceDate);logChange(999, 'getUninvoicedBillables', 'm', $note=test_msec().':'.$sql);logChange(999, 'getUninvoicedBillables', 'm', $note=test_msec()."[".print_r($billables,1)."]");}	
	return $billables;
}

function createRegularBillables($clientid, $asOfDate) {  // Unused as ao 2009-08-24
	global $preferences;  // must be set before this call
	// find all non-monthly recurring, unbilled, completed, appointments
	$appts = fetchAssociations("SELECT tblappointment.*
														 FROM tblappointment 
														 LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
														 LEFT JOIN tblrecurringpackage ON packageid = packageptr
														 WHERE tblappointment.clientptr = $clientid
															 AND recurringpackage = 1
															 AND monthly = 0
															 AND completed IS NOT NULL
															 AND billableid IS NULL
															 AND date <= '$asOfDate'
														 ORDER BY date");
//														 LEFT JOIN tblservice ON serviceid = serviceptr

	// all nonrecurring appointments have been excluded.  find non-rec schedules to bill for.
	$nonRegularPackages = fetchAssociationsKeyedBy(
														"SELECT tblservicepackage.*
														FROM tblservicepackage
														LEFT JOIN tblbillable ON itemptr = packageid AND itemtable = 'tblservicepackage'
														WHERE current AND tblservicepackage.clientptr = $clientid 
															AND (prepaid OR enddate <= '$asOfDate')
															AND billableid IS NULL
														ORDER BY enddate", 'packageid');
	$newBillables = array();
	$credits = getClientCredits($clientid, 1);

	foreach($appts as $appt) {
		$tax = figureTaxForAppointment($appt);
		$charge = $appt['charge']+$appt['adjustment']+$tax;
		$newBillable = array('clientptr'=>$clientid, 'itemptr'=>$appt['appointmentid'], 'itemtable'=>'tblappointment', 
												'charge'=>$charge /*, 'paid'=>0*/, 'itemdate'=> $appt['date'], 'billabledate'=>date('Y-m-d'),
												'tax'=>$tax);
		// One would prefer to consume credits and then create a billable with 'paid' pre-determined,
		// but to associate a billable with one or more credits, we need to create the billable first
		// and then pay it off.
		$billableId = insertTable('tblbillable', $newBillable, 1);  // UNUSED
		$paid = consumeCredits($credits, $charge, $billableId);
		if($paid) updateTable('tblbillable', array('paid'=>$paid), "billableId = $billableId",1);

		$newBillable['billableid'] = $billableId;
		$newBillables[] = $newBillable;
	}
	foreach($nonRegularPackages as $pckg) {
		createBillableForNonrecurringPackageAndCredits($pckg, $credits);
	}
	return $newBillables;
}

function createBillableForNonrecurringPackage($pckg) { // Unused as af 2009-08-24
	// assume no billable has been created for it yet
	$credits = getClientCredits($pckg['clientptr'], 1);
	return createBillableForNonrecurringPackageAndCredits($pckg, $credits);
}

function createBillableForNonrecurringPackageAndCredits($pckg, $credits) {// Unused as af 2009-08-24
	// assume no billable has been created for it yet
	$charge = $pckg['packageprice'];

	// One would prefer to consume credits and then create a billable with 'paid' pre-determined,
	// but to associate a billable with one or more credits, we need to create the billable
	// and then pay it off.
	$itemdate = ($pckg['onedaypackage'] || $pckg['prepaid']) ? $pckg['startdate'] : $pckg['enddate'];
	$newBillable = array('clientptr'=>$pckg['clientptr'], 'itemptr'=>$pckg['packageid'], 'itemtable'=>'tblservicepackage', 
											'charge'=>($charge ? $charge : '0.0')/*, 'paid'=>$paid*/, 'itemdate'=> $itemdate, 'billabledate'=>date('Y-m-d'));
	$billableId = insertTable('tblbillable', $newBillable, 1);    // UNUSED
	$paid = consumeCredits($credits, $charge, $billableId);
	if($paid) updateTable('tblbillable', array('paid'=>$paid), "billableId = $billableId",1);

	$newBillable['billableid'] = $billableId;
	return $newBillable;
}

function createBillablesForNonMonthlyAppts($ids, $ignoreExistingBillables=false) {  
	// $ids is an array or csv string.  Figure out which can be billed.
	// $ignoreExistingBillables=true if the resulting billable is to be passed in to supersedeBilableObject
	if(!$ids) return null;
	if(is_array($ids)) $ids = join(',',$ids);
	
  $appointmentsToBill = 
  		fetchAssociationsKeyedBy("SELECT tblappointment.* 
  							 FROM tblappointment
								 LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
								 LEFT JOIN tblrecurringpackage ON packageid = packageptr
								 WHERE  recurringpackage = 1
									 AND monthly = 0
									 AND completed IS NOT NULL
									 ".($ignoreExistingBillables ? '' : 'AND (billableid IS NULL OR superseded = 1)')."
									 AND appointmentid IN ($ids)", 'appointmentid');
									 
	/*foreach(fetchAssociationsKeyedBy("SELECT tblappointment.* 
  							 FROM tblappointment
								 LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
								 LEFT JOIN tblservicepackage ON packageid = packageptr
								 WHERE  recurringpackage = 0
									 AND completed IS NOT NULL
									 ".($ignoreExistingBillables ? '' : 'AND (billableid IS NULL OR superseded = 1)')."
									 AND appointmentid IN ($ids)", 'appointmentid')	
						as $id => $appt)
			$appointmentsToBill[$id] = $appt;*/
							
  $appointmentsToBill = array_merge($appointmentsToBill, 
  		fetchAssociationsKeyedBy("SELECT tblappointment.* 
  							 FROM tblappointment
								 LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
								 LEFT JOIN tblservicepackage ON packageid = packageptr
								 WHERE  recurringpackage = 0
									 AND completed IS NOT NULL
									 ".($ignoreExistingBillables ? '' : 'AND (billableid IS NULL OR superseded = 1)')."
									 AND appointmentid IN ($ids)", 'appointmentid'));
									 
									 
	$newBillables = array();
  if($appointmentsToBill) {
		$apptIds = array();
		foreach($appointmentsToBill as $appt) {
			$clients[] = $appt['clientptr'];
			$apptIds[] = $appt['appointmentid'];
		}
		foreach(array_unique($clients) as $clientid)
			$credits[$clientid] = getClientCredits($clientid, 1);
		$discounts = $apptIds 
			? fetchKeyValuePairs("SELECT appointmentptr, amount FROM relapptdiscount WHERE appointmentptr IN (".join(',', $apptIds).")")
			: array();
		foreach($appointmentsToBill as $appt) {
			$newBillable = createApptBillableObject($appt, $discounts[$appt['appointmentid']]);
			// One would prefer to consume credits and then create a billable with 'paid' pre-determined,
			// but to associate a billable with one or more credits, we need to create the billable first
			// and then pay it off.
			$billableId = insertTable('tblbillable', $newBillable, 1);  // non-monthly appointments
			$newBillable['billableid'] = $billableId;
			$newBillables[] = $newBillable;
			if(!$ignoreExistingBillables) {
				$paid = consumeCredits($credits[$appt['clientptr']], $newBillable['charge'], $billableId);
				if($paid) updateTable('tblbillable', array('paid'=>$paid), "billableId = $billableId",1);
			}

			//$newBillable['billableid'] = $billableId;
			//$newBillables[] = $newBillable;
		}
	}
	return $ignoreExistingBillables 
		? $newBillables
		: count($appointmentsToBill);
}

function createApptBillableObject($appt, $discountAmount) {
	$appt['charge'] = $appt['charge'] - $discountAmount;
	$tax = figureTaxForAppointment($appt);
	$charge = $appt['charge']+$appt['adjustment']+$tax;
	$charge = $charge ? $charge : '0.0';
	$clientid = $appt['clientptr'];
	return array('clientptr'=>$clientid, 'itemptr'=>$appt['appointmentid'], 'itemtable'=>'tblappointment', 
											'charge'=>$charge, 'itemdate'=> $appt['date'], 'billabledate'=>date('Y-m-d'),
											'tax'=>($tax ? $tax : '0.0')); /*, 'paid'=>0*/
}

function createSurchargeBillableObject($surchargeOrSurchargeid) {
	global $scriptPrefs; // prob not necessary
	$surcharge = is_array($surchargeOrSurchargeid) ? $surchargeOrSurchargeid : getSurcharge($surchargeOrSurchargeid, false);
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences'] : $scriptPrefs;
	$taxRate = $prefs['taxRate'] ? $prefs['taxRate'] : 0;
	if($surcharge['appointmentptr']) {
		$appt = getAppointment($surcharge['appointmentptr'], false);
		require_once "tax-fns.php";
		$taxRate = getClientServiceTaxRate($surcharge['clientptr'], $appt['servicecode']);
	}
	$tax = $surcharge['charge'] * $taxRate / 100;	
	$tax = $tax ? $tax : '0';
	$newBillable = array('clientptr'=>$surcharge['clientptr'], 'itemptr'=>$surcharge['surchargeid'], 'itemtable'=>'tblsurcharge', 
											'charge'=>$surcharge['charge']+$tax, 'itemdate'=> $surcharge['date'], 'billabledate'=>date('Y-m-d'),
											'tax'=>($tax ? $tax : '0.0')); /*, 'paid'=>0*/
	if(!$newBillable['charge']) $newBillable['charge'] = '0.0';
	return $newBillable;
}

function createSurchargeBillable($surchargeOrSurchargeid) {
	// TBD: replace start of this method with call to createSurchargeBillableObject, above 
	global $scriptPrefs; // prob not necessary
	$surcharge = is_array($surchargeOrSurchargeid) ? $surchargeOrSurchargeid : getSurcharge($surchargeOrSurchargeid, false);
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences'] : $scriptPrefs;
	$taxRate = $prefs['taxRate'] ? $prefs['taxRate'] : 0;
	if($surcharge['appointmentptr']) {
		$appt = getAppointment($surcharge['appointmentptr'], false);
		require_once "tax-fns.php";
		$taxRate = getClientServiceTaxRate($surcharge['clientptr'], $appt['servicecode']);
	}
	$tax = $surcharge['charge'] * $taxRate / 100;	
	$tax = $tax ? $tax : '0';
	$newBillable = array('clientptr'=>$surcharge['clientptr'], 'itemptr'=>$surcharge['surchargeid'], 'itemtable'=>'tblsurcharge', 
											'charge'=>$surcharge['charge']+$tax, 'itemdate'=> $surcharge['date'], 'billabledate'=>date('Y-m-d'),
											'tax'=>($tax ? $tax : '0.0')); /*, 'paid'=>0*/
	if(!$newBillable['charge']) $newBillable['charge'] = '0.0';
	$billableId = insertTable('tblbillable', $newBillable, 1);  // surcharge billable
	// One would prefer to consume credits and then create a billable with 'paid' pre-determined,
	// but to associate a billable with one or more credits, we need to create the billable first
	// and then pay it off.
	$credits = getClientCredits($surcharge['clientptr'], 1);
	$paid = consumeCredits($credits, $newBillable['charge'], $billableId);
	// Since charge is brand new, we do not need to check to see if an invoice need to be marked paid
	if($paid) updateTable('tblbillable', array('paid'=>$paid), "billableId = $billableId",1);
	return $billableId;
}

function overrideMonthlyBillable($clientid, $monthYear=null, $makeNew=true) {
	$package = fetchFirstAssoc(
			"SELECT rpack.*, monthyear, billableid, tbill.charge as billablecharge
				FROM tblrecurringpackage rpack
				LEFT JOIN tblbillable tbill ON tbill.clientptr = rpack.clientptr 
																		AND superseded = 0 
																		AND itemtable = 'tblrecurringpackage' 
																		AND monthyear = '$monthYear'
				WHERE current AND monthly AND rpack.clientptr = $clientid
				LIMIT 1");
	if($package) {
		$newBillable = createMonthlyBillableObject($package, $monthYear);
		if(!$package['cancellationdate'] && $makeNew && $newBillable['charge'] == $package['billablecharge']) return;
		supersedeBillable($package['billableid']);  // credit or delete the billable if it exists
	}
	if($makeNew) createMonthlyBillable($package, $monthYear, 'override');
}
					
function OLDoverrideMonthlyBillable($packageid, $monthYear=null, $makeNew=true) {
		$package = fetchFirstAssoc(
				"SELECT rpack.*, monthyear, billableid
					FROM tblrecurringpackage rpack
					LEFT JOIN tblbillable tbill ON itemptr = packageid AND tbill.clientptr = rpack.clientptr AND superseded = 0 AND itemtable = 'tblrecurringpackage'
					WHERE current AND monthly AND packageid = $packageid AND monthyear = '$monthYear'
					LIMIT 1");
		if(!$package) $package = fetchFirstAssoc("SELECT * FROM tblrecurringpackage WHERE packageid = $packageid LIMIT 1");
		supersedeBillable($package['billableid']);  // credit or delete the billable if it exists
		if($makeNew) createMonthlyBillable($package, $monthYear, 'override');
}
					

function recreateAppointmentBillable($appointmentid) {
	// if new net charge equals old net charge, do nothing
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {
		$oldNetCharge = fetchRow0Col0(
			"SELECT charge
				FROM tblbillable
				WHERE itemptr = '$appointmentid' AND itemtable = 'tblappointment' AND superseded = 0 LIMIT 1");
		$discountAmount = fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = $appointmentid LIMIT 1");
		$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $appointmentid LIMIT 1");
		if($appt['completed']) {
			$newBillableObj = createApptBillableObject($appt, $discountAmount);
			if($oldNetCharge != $newBillableObj['charge']) {
				$newBillables = createBillablesForNonMonthlyAppts($appointmentid, 'ignoreexisting');  // no-op if completed IS NULL		
				if($newBillables) $newBillable = current($newBillables);
			}
		}
		if(!$appt['completed'] || $newBillable)
			supersedeAppointmentBillable($appointmentid, $newBillable);
	//}
	
	//$newBillables = createBillablesForNonMonthlyAppts($appointmentid, 'ignoreexisting');  // no-op if completed IS NULL
}

function supersedeAppointmentBillable($appointmentid, $replacement=null) {
	// credit or delete the billable if it exists prior to creating a new billable (elsewhere).
	if(!$appointmentid) return;
	$ignoreReplacement = $replacement ? "AND billableid != {$replacement['billableid']}" : '';
	$billable = fetchFirstAssoc(
		"SELECT *
			FROM tblbillable
			WHERE itemptr = '$appointmentid' AND itemtable = 'tblappointment' AND superseded = 0 $ignoreReplacement LIMIT 1");
	supersedeBillableObject($billable, $replacement);
}
		
function supersedeSurchargeBillable($surchargeid, $replacement=null) {
	// credit or delete the billable if it exists prior to creating a new billable (elsewhere).
	if(!$surchargeid) return;
	$billable = fetchFirstAssoc(
		"SELECT *
			FROM tblbillable
			WHERE itemptr = '$surchargeid' AND itemtable = 'tblsurcharge' AND superseded = 0 LIMIT 1");
//echo "About to supersede: ".print_r($billable, 1);exit;			
	supersedeBillableObject($billable, $replacement);
}
		
function supersedeChargeBillable($chargeid, $replacement=null) {
	// credit or delete the billable if it exists prior to creating a new billable (elsewhere).
	if(!$chargeid) return;
	$billable = fetchFirstAssoc(
		"SELECT *
			FROM tblbillable
			WHERE itemptr = '$chargeid' AND itemtable = 'tblothercharge' AND superseded = 0 LIMIT 1");
//echo "About to supersede: ".print_r($billable, 1);exit;			
	supersedeBillableObject($billable, $replacement);
}
		
function supersedeBillable($billableid, $replacement=null) {
	// credit or delete the billable if it exists prior to creating a new billable (elsewhere).
	if(!$billableid) return;
	$billable = fetchFirstAssoc("SELECT * FROM tblbillable WHERE billableid = $billableid LIMIT 1");
	supersedeBillableObject($billable, $replacement);
}
		
function supersedeBillableObject($billable, $replacement=null) {
	// credit or delete the billable if it exists prior to creating a new billable (elsewhere).
	if($billable) {
		if($billable['paid'] == 0.00 && !$billable['invoiceptr']) // if not paid or invoiced...
			deleteTable('tblbillable', "billableid = {$billable['billableid']}", 1);
		else { // else generate credit for amount of billable and pay off billable
			$client = $billable['clientptr'];
			$monthYear = $billable['monthyear'] ? $billable['monthyear'] : '';
			if($billable['itemtable'] == 'tblappointment') {
				$date = fetchRow0Col0("SELECT date FROM tblappointment WHERE appointmentid = {$billable['itemptr']} LIMIT 1");
				$date = shortNaturalDate(strtotime($date), 'noYear');
				if($replacement) $replacementNote = 'New billable created.';
				$reason = "Visit on $date changed. $replacementNote (v: {$billable['itemptr']} b: {$billable['billableid']}).";
			}
			else $reason = "Billable [{$billable['billableid']}] ({$billable['itemtable']} {$billable['itemptr']} $monthYear) superseded.";
			
			/* Scenario:
				A payment is voided.  That payment is associated with a set of billables, so those billables are unpaid.
				However, that payment was also formerly associated with superseded billables, with the replacement billables
				being paid off with new credits, which might be considered "children" of the original payment.
				When the payment is voided, these children should also be voided.
				
				Problem: when a bookkeeping credit is created, it may represent several partial payments.
				
				Example: a monthly contract may be paid off with three small payments.  When that billable is superseded, a single
				bookeeping credit is created.  The new credit conceptually has three parent payments.
				
				This suggests we need a new table (relparentpayment: parentptr, childptr) to represent the parent-payment relationship.
				
				Problem: when an item is changed and changed again, the resulting credit's parent may be a credit, not a payment.
				So, when creating a bookkeepping credit, we should examine the parent credit.  If the parent credit has a parent itself,
				the "grandparent" should be used as the new credit's parent.
				
				B1(payment1, payment2) 
					=> superseded by B2(credit1[parents:payment1, payment2])
					B2 => superseded by B3(credit1[parents:payment1, payment2 + ?])
			*/
			if(mattOnlyTEST()) { // BEGIN TRACK PAYMENT-CREDIT RELATIONSHIP
			$oldPayments = fetchAssociations(
				"SELECT paymentptr, billableptr, amount 
					FROM relbillablepayment 
					WHERE billableptr = {$billable['billableid']}");
			} // END TRACK PAYMENT-CREDIT RELATIONSHIP
			
			
			
			
			$credit = array('reason'=>$reason, 'amount'=>$billable['charge'], 'clientptr'=>$client, 'issuedate'=>date('Y-m-d H:i:s'), 'bookkeeping'=>1);
			insertTable('tblcredit', addCreationFields($credit), 1);
			$toupee = array($billable);
		}
	}
	if($replacement) {
		$toupee[] = $replacement;
		$client = $replacement['clientptr'];
	}
	if($toupee) payOffClientBillables($client, $toupee); // apply credit to superseded billable.
	if($billable) updateTable('tblbillable', array('superseded'=>1), "billableid = {$billable['billableid']}", 1);
}
		

function createValuePackBillable($vpid) {
	require_once "value-pack-fns.php";
	$valuepack = getValuePack($vpid);
	$newBillable = createValuePackBillableObject($valuepack);
	if(fetchRow0Col0(
			"SELECT billableid 
				FROM tblbillable 
				WHERE clientptr = {$valuepack['clientptr']}
					AND itemtable = 'tblvaluepack' AND itemptr = {$valuepack['vpid']}
					AND superseded = 0", 1))
			return;  // do not create duplicate monthly billable

	// One would prefer to consume credits and then create a billable with 'paid' pre-determined,
	// but to associate a billable with one or more credits, we need to create the billable first
	// and then pay it off.
	$billableId = insertTable('tblbillable', $newBillable, 1);  // valuepack billable
	$credits = getClientCredits($newBillable['clientptr'], 1);
	$paid = consumeCredits($credits, $newBillable['charge'], $billableId);
	if($paid) updateTable('tblbillable', array('paid'=>$paid), "billableId = $billableId",1);
	return $billableId;
}

function createValuePackBillableObject($valuepack) {
	// $valuepack represents a value pack
	global $preferences;  // must be set before this call if no session
	global $scriptPrefs;
							
	$clientid = $valuepack['clientptr'];
	
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences'] : (
						$preferences ? $preferences : $scriptPrefs);
	$baseRate = $prefs['taxRate'] ? $prefs['taxRate'] : 0;

	$tax = round($baseRate * $valuepack['price'])/100;
	$charge = $valuepack['price']+$tax;
	return array('clientptr'=>$clientid, 'itemptr'=>$valuepack['vpid'], 'itemtable'=>'tblvaluepack',
											'charge'=>($charge ? $charge : '0.0'), 
											'itemdate'=> date("Y-m-d"),
											'billabledate'=>date('Y-m-d'),
											'tax'=>($tax ? $tax : '0.0'));
}

function createMonthlyBillableObject($package, $monthYear=null) {
	// $package represents a package and may include its latest billable monthyear.
	// If monthYear is supplied, this is used instead of $package's monthyear
	// if override is false and a billable exists for $package's monthyear, no billable is created
	global $preferences;  // must be set before this call
	if(!$monthYear) {
		$iMonthStart = strtotime(date('Y-m').'-01');
		$iMonthYear = $package['prepaid'] 
			? strtotime("+1 month", $iMonthStart) 
			: strtotime("-1 month", $iMonthStart);
		$monthYear = date('Y-m-d', $iMonthYear);
	}
		
global $TEST;
	$billingDay = date("Y-m", strtotime($monthYear))."-".sprintf('%02d', $preferences['monthlyBillOn']);
	$itemdate = $package['prepaid'] 
							? date("Y-m-d", strtotime("-1 month", strtotime("-1 day", strtotime($billingDay) )))  // for prepaid set itemdate to day before monthly billdate of prev month
							: date("Y-m-t", strtotime($monthYear));
if($TEST) {
	echo "<hr>billingDay: $billingDay<br>monthYear: $monthYear<br>prepaid: {$package['prepaid']}<br>itemdate: $itemdate<hr>";
}
							
	$clientid = $package['clientptr'];
	if(!$TEST)
	$tax = figureMonthlyRecurringPackageTax($clientid, $package['packageid'], $charge);
	$charge = $package['totalprice']+$tax;
	return array('clientptr'=>$clientid, 'itemptr'=>$package['packageid'], 'itemtable'=>'tblrecurringpackage', 'monthyear'=>$monthYear,
											'charge'=>($charge ? $charge : '0.0') /*, 'paid'=>0*/, 'itemdate'=> $itemdate, 'billabledate'=>date('Y-m-d'),
											'tax'=>($tax ? $tax : '0.0'));
}

function createMonthlyBillable($package, $monthYear=null, $override=false) {
	$newBillable = createMonthlyBillableObject($package, $monthYear);
	if(fetchRow0Col0(
			"SELECT billableid 
				FROM tblbillable 
				WHERE clientptr = {$package['clientptr']} 
					AND monthyear = '{$newBillable['monthyear']}'
					AND superseded = 0"))
			return;  // do not create duplicate monthly billable
	if($package['cancellationdate'] && strcmp($package['cancellationdate'], $newBillable['monthyear']) <= 0)
		return;
	// One would prefer to consume credits and then create a billable with 'paid' pre-determined,
	// but to associate a billable with one or more credits, we need to create the billable first
	// and then pay it off.
	$billableId = insertTable('tblbillable', $newBillable, 1);  // monthly billable
	$credits = getClientCredits($newBillable['clientptr'], 1);
	$paid = consumeCredits($credits, $newBillable['charge'], $billableId);
	if($paid) updateTable('tblbillable', array('paid'=>$paid), "billableId = $billableId",1);
	return $billableId;

	// Case #1: prepaid monthly service:
	// When the service is created:
	// 	If service starts after next month do nothing
	// 	Else if service starts on 1st of next month
	//		Generate a MonthlyBillable (createMonthlyBillable) for next month immediately
	// 	Else if service starts middle of next month
	//		Generate a tblothercharge for remainder of next month
	// 	Else if service starts in the middle of this month
	// 		Generate a tblothercharge for remainder of this month
	//		If this month's invoice date (monthlyBillOn) is past
	//			Generate a MonthlyBillable (createMonthlyBillable) for next month immediately
	// 	Else Generate a MonthlyBillable (createMonthlyBillable) for next month immediately
	
	// Case #2: postpaid monthly service
	// If service starts after this month, no billable
	// If service starts this month
	//		If 
	
}

function getInvoiceItemDescription($item) {
	$xml = is_string($item) ? $item : $item['description'];
	if(!$xml) return;
	$obj = simplexml_load_string($xml);
	foreach($obj as $k => $v) $descr[$k] = ''.$v;
	foreach($obj->attributes() as $k => $v) $descr[$k] = ''.$v;
	return $descr;
}

function invoiceItemDescriptionForBillable($billable) {
//if(!$_SERVER['REMOTE_ADDR'] == '68.225.89.173') return;
	static $providerNames, $serviceNames, $surchargeTypes, $standardCharges;
	if(!$providerNames) {
		$providerNames = getProviderNames();
		$providerNames[0] = 'Unassigned';
	}
	if(!$serviceNames) {
		require_once "service-fns.php";
		$serviceNames = getAllServiceNamesById(true, true);
	}
	if(!$surchargeTypes) $surchargeTypes = getSurchargeTypesById($refreshList=1, $inactiveAlso=1);
	// types tblappointment, tblrecurringpackage, tblothercharge, tax?, tblsurcharge
	if($billable['itemtable'] == 'tblappointment') {
		$item = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = {$billable['itemptr']}");
		$charge = $item['charge']+$item['adjustment'];
		if($pets = $item['pets']) {
			require_once "client-fns.php";
			$standardCharges = !$standardCharges ? getStandardCharges() : $standardCharges;
			$extraCharge = $standardCharges[$item['servicecode']]['extrapetcharge'];
			if($extraCharge && $extraCharge > 0) {
				if($pets == 'All Pets') $pets = $allPets;
				$extraPets = max(0, count(explode(',', $pets))-1);
				if($extraPets) $item['service'] .= " (incl. charge for $extraPets add'l pet".($extraPets == 1 ? '' : 's').")";
			}
		}
		
		return "<ii type='tblappointment' date='{$item['date']}' timeofday='{$item['timeofday']}' charge='$charge'>"
						."<provider><![CDATA[{$providerNames[$item['providerptr']]}]]></provider>"
						."<service><![CDATA[{$serviceNames[$item['servicecode']]}]]></service></ii>";
	}
	else if($billable['itemtable'] == 'tblothercharge') {
		$item = fetchFirstAssoc("SELECT * FROM tblothercharge WHERE chargeid = {$billable['itemptr']}");
		return "<ii type='tblothercharge' date='{$item['issuedate']}' charge='{$item['amount']}'>"
						."<reason><![CDATA[{$item['reason']}]]></reason>"
						.($item['note'] ? "<note><![CDATA[{$item['note']}]]></note>" : '')
						."</ii>";
	}
	else if($billable['itemtable'] == 'tblsurcharge') {// e.g., Surcharge: Labor Day
		$item = fetchFirstAssoc("SELECT * FROM tblsurcharge WHERE surchargeid = {$billable['itemptr']}");
		return "<ii type='tblsurcharge' date='{$item['date']}' timeofday='{$item['timeofday']}' charge='{$item['charge']}'>"
						."<provider><![CDATA[{$providerNames[$item['providerptr']]}]]></provider>"
						."<service><![CDATA[Surcharge: {$surchargeTypes[$item['surchargecode']]}]]></service></ii>";
	}
	else if($billable['itemtable'] == 'tblrecurringpackage') {
		return "<ii type='tblrecurringpackage' date='{$billable['monthyear']}' charge='{$billable['charge']}'>"
						."<service>Monthly Prepaid</service></ii>";
	}
}

function invoiceMismatch($invoiceid) {
	$invoice = fetchFirstAssoc("SELECT subtotal, discountamount FROM tblinvoice WHERE invoiceid = $invoiceid LIMIT 1");
	$subtotal = $invoice['subtotal'];
	$discountamount = $invoice['discountamount'];
	$billables = fetchAssociations(
		"SELECT tblbillable.* 
			FROM relinvoiceitem
			LEFT JOIN tblbillable ON billableid = billableptr
			WHERE relinvoiceitem.invoiceptr = $invoiceid");
	foreach($billables as $b) {
		if($b['itemtable'] == 'tblappointment') {
			$apptid = $b['itemptr'];
			$apptids[] = $apptid;
			$thisInvoice += fetchRow0Col0(
					"SELECT charge+ifnull(adjustment,0) 
						FROM tblappointment 
						WHERE appointmentid = $apptid LIMIT 1");
		}
		else if($b['itemtable'] == 'tblsurcharge') 
			$thisInvoice += fetchRow0Col0(
					"SELECT charge 
						FROM tblsurcharge 
						WHERE surchargeid = {$b['itemptr']} LIMIT 1");
		else if($b['itemtable'] == 'tblothercharge') 
			$thisInvoice += fetchRow0Col0(
					"SELECT amount 
						FROM tblothercharge 
						WHERE chargeid = {$b['itemptr']} LIMIT 1");
		else if($b['itemtable'] == 'tblrecurringpackage') 
			$thisInvoice += fetchRow0Col0(
					"SELECT totalprice 
						FROM tblrecurringpackage 
						WHERE packageid = {$b['itemptr']} LIMIT 1");
	}
	$a = (int)(100*$thisInvoice+100*$discountamount) / 100;
	$b = (int)(100*$subtotal) / 100;
	//if(abs($a-$b) >= 2) echo "<font color=gray> $invoiceid: $a = $b</font> <br>";		//[".print_r($apptids, 1)."]
	return $a == $b ? null : array($a, $b);
}

function killInvoice($invoiceid, $thisdb) {
	global $db;
	if($thisdb != $db) {echo "Wrong db: $thisdb";exit;}
	$invoice = getInvoice($invoiceid);
	deleteTable('tblinvoice', "invoiceid = $invoiceid");
	deleteTable('relinvoiceitem', "invoiceptr = $invoiceid");
	deleteTable('relinvoicecan', "invoiceptr = $invoiceid");
	deleteTable('relinvoicecredit', "invoiceptr = $invoiceid");
	deleteTable('relinvoicerefund', "invoiceptr = $invoiceid");
	deleteTable('relpastdueinvoice', "currinvoiceptr = $invoiceid OR oldinvoiceptr = $invoiceid");
	updateTable('tblbillable', array('invoiceptr'=> sqlVal('NULL')), "invoiceptr = $invoiceid", 1);
}
	

/* Billing Notes:
To build a preview of an invoice (or collect lineitems for an invoice):
0. Add up unpaid invoices and calculate Previous balance
1. Create new billables:
	- find unbilled nonMonthly appts
	- find unbilled monthly service packages if monthly
2. Use all uninvoiced billables to calculate subtotal
3. Consume credits to pay billables and calculate payments and credits
4. Find all uninvoiced billables
5. Collect all credits/payments between last two billing dates
6. Find all service packages where  enddate > today and enddate < today + N days

On the Invoice Page:
For each client:
	If there is an invoice later than the most recent billing date, show that invoice
	else if there are any billables show a link to a preview display

To create an invoice,
	For each selected service package where  enddate > today and enddate < today + N days
		create a billable
	create an invoice item for each billable
	etc...
	
	
Nonrecurring services packages can be prepaid or postpaid.  When prepaid, a billable is created immediately
when the service is saved.  Also,  when a NR package is saved, if date < enddate any existing billable for the
old service is deleted and a new billable is created with the old billable's amountpaid (or the itemptr is changed 
to the new service's package).  Problem: what if the billable is partially paid, and what if
the new package price is less than the paid portion of the new NR package?  (Is a credit issued for the difference?)

When a NR package is postpaid, the billable is created by a cron job the day after the package's enddate.

On the Invoice Preview, all items have checkboxes (pre-selected) to indicate which ones the invoice will include.

Problem: when an item is NOT included on any invoice but is paid for (partially or completely) anyway by the FIFO rule, 
what does it mean to include it in a later invoice?  Should we apply credits/payments ONLY to invoiced billables?

Billing CRON
	runs once a day
	if today is a monthly billing day
		foreach($client) createMonthlyBillable($clientid, $asOfDate);
	
*/