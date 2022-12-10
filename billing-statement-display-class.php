<? // billing-statement-display-class.php
require_once "billing-fns.php";
require_once "billing-statement-class.php";
//assumed require_once "db-fns.php";

class BillingStatementDisplay {
	private $clientid;
	private $billingStatement;
	private $amountDue;
	
	function __construct($clientidOrBillingStatement) {
		if(is_object($clientidOrBillingStatement)) {
			$this->billingStatement = $clientidOrBillingStatement;
			$this->clientid = $clientidOrBillingStatement->getClientid();
		}
		else {
			$this->clientid = $clientidOrBillingStatement;
			$this->billingStatement = new BillingStatement($clientidOrBillingStatement);
		}
	}
	
	function getBillingStatementContents($literal=false, $showOnlyCountableItems=false, $includePayNowLink=false, $packageptr=null, $excludePriorUnpaid=false) {
		ob_start();
		ob_implicit_flush(0);
		$bs = $this->billingStatement;
		$this->displayBillingInvoice($bs->firstDay, $bs->lookahead, true, $literal, $showOnlyCountableItems, $includePayNowLink, $packageptr, $excludePriorUnpaid=false);
		//else displayPrepaymentInvoice($clientid, $firstDay, $lookahead);
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}	
	
	function displayBillingInvoice($firstDay, $lookahead, $firstInvoicePrinted=true, $literal=false, $showOnlyCountableItems=false, $includePayNowLink=false, $packageptr=null, $excludePriorUnpaid=false) {
	//prepayment-fns.php(206): displayPrepaymentInvoice($clientid, $firstDay, $lookahead);
	//prepayment-fns.php(509): function displayPrepaymentInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true) {
	//prepayment-invoice-print.php(41): displayPrepaymentInvoice($id, $firstDay, $lookahead, $first);
	//prepayment-invoice-view.php(51): displayPrepaymentInvoice($id, $firstDay, $lookahead);
		$clientid = $this->clientid;
		$client = getClient($clientid);
		$invoice = $this->billingStatement;
		if(!$invoice->populated) $invoice->populateBillingInvoice($firstDay, $lookahead, $literal, $showOnlyCountableItems, $packageptr, $excludePriorUnpaid);
		
	//if(mattOnlyTEST()) echo "<b>clientid: [[".print_r($invoiceOrClientId,1)."]]</b><hr>";
		global $invoicePayment; // TBD - investigate this
		if(is_string($invoicePayment = getInvoicePaymentData($clientid))) return;
		
	//if(mattOnlyTEST()) {echo "[[{$_REQUEST['packageptr']}]]<p>";print_r($invoice); exit;}
		// This may be called in a SESSION or outside of it (cronjob)
		if($firstInvoicePrinted) echo invoicePageStyle();
		
	//	<body 'style=font-size:12px;padding:10px;'>
		//$previousInvoices = getPriorUnpaidInvoices($invoice);
		$amountDue = $invoice->calculateAmountDue();  // = amount due AFTER $invoicePayment if any
		$includePayNowLink = $includePayNowLink && !is_array($includePayNowLink) ? array('note'=>$standardMessageSubject, 'amount'=>$amountDue) : $includePayNowLink;
		
		$this->dumpReturnSlip($invoice, $client, $includePayNowLink, $amountDue);
		$statementTitle = $_SESSION['preferences']['statementTitle'] ? $_SESSION['preferences']['statementTitle'] : 'STATEMENT';
		echo "<p align=center><b>$statementTitle</b><p>";
	if(0 && mattOnlyTEST()) {global $credits; echo "ZOOM2 CREDITS: $credits<hr>";}
		//echo "<p align=center><b>STATEMENT</b><p>";
		$this->dumpAccountSummary($client, $showOnlyCountableItems); // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
		echo "<p>";
		//dumpInvoiceCredits($client['clientid']);
	//echo "CREDITS: $ 	$".$invoice->decrementingCredits."<p>";
		$this->dumpPriorUnpaidBillables($showOnlyCountableItems);
	//if(mattOnlyTEST()) print_r($invoice); exit;	
		$this->dumpCurrentBillables(); // Invoice #, Invoice Date, Items, Subtotal
		$this->dumpRecentPayments(); // Invoice #, Invoice Date, Items, Subtotal
		//dumpCurrentPastInvoiceSummaries($invoiceid); // Invoice #, Invoice Date, Items, Subtotal
		dumpMessage($invoice);  // should we add a message field to invoice?
		$this->dumpBillingStatementFooter();
	}
	
	function dumpBillingStatementFooter() {
		if($_SESSION['preferences']['statementFooter']) echo $_SESSION['preferences']['statementFooter'];
		echo "<br><span style='color:white;font-size:6pt'>format version: 2.0</span>";
	}
	
	
	function invoicePageStyle() {
		// for now, return the standard style in billing-fns.php
		return invoicePageStyle();
	}

	function dumpReturnSlip($invoice, $client, $includePayNowLink=null, $amountDue) {
		echo "<table width='95%' border=0 bordercolor=red>";
		echo "<tr><td style='padding-bottom:8px'>";
		$this->dumpBusinessLogoDiv($amountDue,  null, null, $client['clientid']);
		echo "</td><td align=right>";	
		$this->dumpInvoiceHeader($invoice, $client, $includePayNowLink); // customer #, customer invoice #, invoice date, Amount Due
		echo "</td></tr>";
		echo "<tr><td>";
		dumpClientAddress($client); // mailing address or home address if no mailing address
		echo "</td><td align=right>";
		//dumpInvoiceBarcode(invoiceIdDisplay($invoice['invoiceid']));
		echo "</td></tr></table>";
		if(!$_SESSION['preferences']['suppressDetachHereLine']) 
			echo "<p align=center>Please detach here and return with payment.<p><hr>";
	}

	function dumpBusinessLogoDiv($amountDue, $html=null, $preview=false, $clientid=null) {
		// for now, return the standard fn in billing-fns.php
		return dumpBusinessLogoDiv($amountDue, $html, $preview, $clientid);
	}

	function dumpInvoiceHeader($invoice, $client, $includePayNowLink=null) {  // customer #, customer invoice #, invoice date, Amount Due
		$amountDue = $invoice->calculateAmountDue(); // = amount due AFTER $invoicePayment if any
		echo "<table width=290>";
		//echo "<tr><td colspan=2 style='font-weight:bold'>Statement</td><tr>";
		//labelRow('Customer Number:', '', $client['clientid'], '', 'rightAlignedTD');
		labelRow('Invoice Date:', '', shortDate(strtotime($invoice->date)), '', 'rightAlignedTD');
		//$amountDue = $origbalancedue - $creditApplied + $tax - $credits;
		//$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
		//echo "<tr><td colspan=2>origbalancedue [$origbalancedue] - creditApplied[$creditApplied] + tax[$tax] - credits[$credits] + priorUnpaidItemTotal[".priorUnpaidItemTotal($invoice)."]";	
		//$amountDue = $amountDue - $tax;
		//global $invoicePayment;
		
		
		//$finalBalanceDue = $amountDue;// - $invoicePayment['amount'];
		//$finalBalanceDue = $finalBalanceDue < 0 ?  dollars(abs($finalBalanceDue)).'cr' : dollars($finalBalanceDue);
		//labelRow("<img height=16 width=20 src='https://{$_SERVER["HTTP_HOST"]}/art/redarrowright.png'>Amount Due:", '', $finalBalanceDue, $labelClass='fontSize1_8em', 'rightAlignedTD fontSize1_8em', '', 'border: solid black 1px;', 'raw');
		echo $this->bigAmountDueLabelRow($amountDue);
		
		if($includePayNowLink) {
			$payNowLink = payNowLink($client, $includePayNowLink);
		}
		echo "<tr><td id='paynowcell' colspan=2 style='text-align:right;'>$payNowLink</td></tr>";
		echo "</table>";
	}
	
	function bigAmountDueLabelRow($amountDue) {
		ob_start();
		ob_implicit_flush(0);
		$finalBalanceDue = $amountDue;
		$finalBalanceDue = $finalBalanceDue < 0 ?  dollars(abs($finalBalanceDue)).'cr' : dollars($finalBalanceDue);
		labelRow("<img height=16 width=20 src='https://{$_SERVER["HTTP_HOST"]}/art/redarrowright.png'>Amount Due:", '', $finalBalanceDue, $labelClass='fontSize1_8em', 'rightAlignedTD fontSize1_8em', '', 'border: solid black 1px;', 'raw');
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}		
	
	function dumpAccountSummary($client, $showOnlyCountableItems=false, $paymentData=null) {  // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
		echo "<table width='95%'>";
		echo "<tr><td colspan=2>";
		dumpSectionBar('Account Summary', "Customer Number: {$client['clientid']}");
		echo "</td>";
		echo "<tr><td style='text-align:left;vertical-align:top;'>";
		dumpClientAddress($client);
		echo "</td><td align=right>";
		$this->dumpBalances($showOnlyCountableItems);
		echo "</td></tr>";
		echo "</table>";
		
	}
	
	function dumpBalances($showOnlyCountableItems=false) {  // what was $showOnlyCountableItems for??
	
		//global $origbalancedue, $credits, $tax, $currentPaymentsAndCredits, $creditUnappliedToUnpaidItems, $creditApplied, 
		//$currentCharges, $currentDiscount, $priorDiscount, $totalDiscountAmount;
		
		$invoice = $this->billingStatement;
		$clientid = $this->clientid;
	//if(staffOnlyTEST()) echo "credits $credits	+ creditApplied $creditApplied<p>";
		echo "<table width=60%>";
		//$currentCharges = $currentCharges-$currentDiscount['amount'];  // problem: includes TAX
		$currentCharges = $invoice->currentPostDiscountPreTaxSubtotal;
if(FALSE && staffOnlyTEST()) 		labelRow('BLAH', '', ($invoice->origbalancedue)." - tax ".($invoice->tax), $labelClass=null, 'rightAlignedTD', '', '', 'raw');

		labelRow('Current Charges', '', dollars($currentCharges), $labelClass=null, 'rightAlignedTD', '', '', 'raw');
		$taxLabel = $_SESSION['preferences']['taxLabel'] ? $_SESSION['preferences']['taxLabel'] : 'Tax';
		labelRow($taxLabel, '', dollars($invoice->tax), $labelClass=null, 'rightAlignedTD', '', '', 'raw');
		//$unusedCredits = fetchRow0Col0("SELECT sum(amount - ifnull(paid,0)) FROM tblcredit WHERE clientptr = $clientid");


			// 'currentPostDiscountPreTaxSubtotal', 'currentTax', 'priorTax', 'priorPostDiscountPreTaxSubtotal'
		$priorCharges = $invoice->priorUnpaidItemTotal($showOnlyCountableItems) - $invoice->priorDiscount['amount']; // does NOT include tax

		labelRow('Prior Unpaid Charges', '', dollars($priorCharges), $labelClass=null, 'rightAlignedTD', '', '', 'raw');
	//if(staffOnlyTEST()) screenLog("creditApplied: $creditApplied + credits: $credits");
		//$creditValue = $creditApplied+$credits;

	// DEFINITION:
	// $currentPaymentsAndCredits in getBillingInvoice, the sum of all credits applied to lineitems
	// $creditUnappliedToUnpaidItems in getBillingInvoice, the sum of all credits applied to partially or completely unpaid lineitems
	// $credits =  in getBillingInvoice, getUnusedClientCreditTotal($clientid);
	// $creditApplied = credits applied (distributed accrual)


		$creditValue = /*$currentPaymentsAndCredits +*/  $invoice->creditApplied +  $invoice->credits - $invoice->creditUnappliedToUnpaidItems;
	if(mattOnlyTEST()) echo "ZOOM2 	creditApplied: $creditApplied + credits: $credits - creditUnappliedToUnpaidItems: $creditUnappliedToUnpaidItems = creditValue: $creditValue<p>";
		//if($showOnlyCountableItems) $creditValue -= priorUnpaidItemTotal($invoice) - priorUnpaidItemTotal($invoice, true);
		labelRow('Payments & Credits', '', dollars($creditValue), $labelClass=null, 'rightAlignedTD', '', '', 'raw');
		//labelRow('Amount Due', '', dollars(max(0, $origbalancedue - $credits + $tax)), $labelClass=null, 'bigger-right', '', 'border: solid black 1px;');
	//print_r($tax);
		//$amountDue = $origbalancedue - $creditApplied + $tax - $credits - $totalDiscountAmount; // + priorUnpaidItemTotal($invoice);
	//if(staffOnlyTEST()) echo ("creditApplied: $creditApplied + credits: $credits");
		//$amountDue = $currentCharges + $priorCharges + $tax - $credits;
		if(FALSE && mattOnlyTEST()) {		
			echo "<tr><td>currentPaymentsAndCredits: $currentPaymentsAndCredits + credits: $credits";
			echo "<tr><td>origbalancedue: $origbalancedue - creditApplied: $creditApplied  - credits: $credits - totalDiscountAmount: $totalDiscountAmount";
		}
		global $invoicePayment;
		$amountDue = $invoice->calculateAmountDue($invoicePayment['amount']);  // = amount due BEFORE $invoicePayment if any

		$important = array('bigger-right', '', 'border: solid black 1px;');
		$emphasis = !$invoicePayment ? $important : array('right', null, null);


		$amountDueDisplay = $amountDue < 0 ?  dollars(abs($amountDue)).'cr' : dollars($amountDue);
		labelRow('Amount Due', '', $amountDueDisplay, $labelClass='bigger-left', $emphasis[0], $emphasis[1], $emphasis[2], 'raw');

		if(!$invoicePayment) $finalBalanceDue = $amountDue;
		else {
			$finalBalanceDue = $amountDue - $invoicePayment['amount'];
			labelRow("<b>Paid electronically</b>", '', dollars($invoicePayment['total']), 'ccpayment', 'right', null, null, 'raw');
			if($invoicePayment['gratuity'])
				labelRow("<b>including gratuity</b>", '', dollars($invoicePayment['gratuity']), 'ccpayment', 'right', null, null, 'raw');
			if($invoicePayment['type'] == 'CC') labelRow("{$invoicePayment['label']}", '', "Thank You!", 'ccpayment', 'right');
			else if($invoicePayment['type'] == 'ACH') labelRow("Bank Account {$invoicePayment['label']}", '', "Thank You!", 'ccpayment', 'right');
			labelRow('Balance Due after payment', 'finalBalanceDue', dollars($finalBalanceDue), $labelClass=null, $important[0], $important[1], $important[2], 'raw');
		}

		if($finalBalanceDue) {
			$dateDue = $_SESSION['preferences']['pastDueDays'];
			if(!$dateDue) $dateDue = "0";


			$dueDateChoice = $_SESSION['preferences']['statementsDueOnPastDueDays'];
			if($dueDateChoice != 'Suppress') {
			$dateDue = (!$dueDateChoice || $dueDateChoice == 'Upon Receipt')
				? 'Upon Receipt'
				: shortDate(strtotime("+ $dateDue days")) ;
				//if(!$dateDue) $dateDue = "0";
				//$dateDue =   $dateDue != "0" ? shortDate(strtotime("+ $dateDue days")) : 'Upon Receipt';

				labelRow('Date Due', '', $dateDue, $labelClass=null, 'bigger-right', '', 'border: solid black 1px;');
			}
		}
		echo "</table>";
	}

	function dumpPriorUnpaidBillables($showOnlyCountableItems=false) { // Invoice #, Invoice Date, Items, Subtotal
	// $showOnlyCountableItems is deprecated
		global $priorDiscount, $suppressPriorUnpaidCreditMarkers;
		echo "<table width='95%'>";
		echo "<tr><td colspan=2>";
		dumpSectionBar("Prior Unpaid Charges", "");
		echo "</td></tr><tr><td>";
		$lineItems = (array)$this->billingStatement->priorunpaiditems;
//echo count($lineItems);		
		$finalLineItems = array();
		$columns = explodePairsLine('date|Date||timeofday|Time of Day||service|Service||provider|Sitter||charge|Charge');
		if($_SESSION['preferences']['suppressInvoiceTimeOfDay']) unset($columns['timeofday']);
		if($_SESSION['preferences']['suppressInvoiceSitterName']) unset($columns['provider']);
		$numCols = count($columns);
		foreach($lineItems as $index => $lineItem) {
	//if(mattOnlyTEST()) { echo "showOnlyCountableItems: [$showOnlyCountableItems] countablecharge: [{$lineItem['countablecharge']}]"; }
			if($showOnlyCountableItems && !$lineItem['countablecharge']) continue;
			if(!$suppressPriorUnpaidCreditMarkers) markLineItemCovered($lineItem);
			$subtotal += (float)($lineItem['charge']);
	//if($lineItem['appointmentid'] == 134728) print_r($lineItem);		
			if($lineItem['discountptr']) $lineItem['service'] = "[D] ".$lineItem['service'];		
			$lineItem['charge'] = dollarAmount($lineItem['charge']);
			$finalLineItems[] = $lineItem;
			$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
		}

		if(!$finalLineItems) {
			echo "<center>No Prior Unpaid Charges Found.</center></td></tr></table>";
			return;
		}
		if($this->billingStatement->priorDiscount) {
			$rowClass =$rowClasses[count($rowClasses)-1] == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
			$discounts = join(', ', fetchCol0("SELECT label FROM tbldiscount WHERE discountid IN (".join(',', array_unique($this->billingStatement->priorDiscount['discounts'])).")"));
			$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																							." style=''><b>Discount Applied: </b>$discounts</td><td class='rightAlignedTD'>("
																							.dollarAmount($this->billingStatement->priorDiscount['amount'])
																							.")</td><tr>");
			$subtotal -= $this->billingStatement->priorDiscount['amount'];
		}

		$subtotalDollars = dollars($subtotal);
		$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr><td colspan=$numCols style='text-align:right;font-weight:bold'>Subtotal: $subtotalDollars</td><tr>");
	//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
		tableFrom($columns, $finalLineItems, 'WIDTH=100% ',null,null,null,null,null,$rowClasses, array('charge'=>'rightAlignedTD'));
		echo "</td></tr></table>";
		//print_r($invoice['priorunpaiditems']);
	}

	function dumpCurrentBillables() { // Invoice #, Invoice Date, Items, Subtotal
		global $currentDiscount;
		echo "<table width='95%'>";
		echo "<tr><td colspan=2>";
		dumpSectionBar("Current Charges", "");
		echo "</td></tr><tr><td>";
		$lineItems = $this->billingStatement->lineitems;
		if(!$lineItems) {
			echo "<center>No Current Charges Found.</center></td></tr></table>";
			return;
		}

		$finalLineItems = array();
		$columns = explodePairsLine('date|Date||timeofday|Time of Day||service|Service||provider|Sitter||charge|Charge');
		if($_SESSION['preferences']['suppressInvoiceTimeOfDay']) unset($columns['timeofday']);
		else $todBlankCell = "<td>&nbsp;</td>";
		if($_SESSION['preferences']['suppressInvoiceSitterName']) unset($columns['provider']);
		else $provBlankCell = "<td>&nbsp;</td>";
		$appointmentsStarted = $lineItems && isset($lineItems[0]['servicecode']);
		if(!$appointmentsStarted) {
			if(!$_SESSION['preferences']['suppressInvoiceTimeOfDay']) $columns['timeofday'] = '&nbsp;';
			if(!$_SESSION['preferences']['suppressInvoiceSitterName'])$columns['provider'] = '&nbsp;';
		}
		//Date	Time of Day	Service	Walker	Charge
		$numCols = count($columns);

	//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) 
		$rowClasses = array();
		foreach($lineItems as $index => $lineItem) {
	//echo print_r($lineItem,1)."<br>";
	//if(mattOnlyTEST()) echo "{$lineItem['charge']} {$lineItem['service']}<br>";
			$subtotal += $lineItem['charge'];
	//if(mattOnlyTEST()) if(!($lineItem['servicecode'] || $lineItem['surchargecode']))print_r($lineItem);			
			//markLineItemCovered($lineItem);	 // DISABLED 2013-11-05 at Ted's request

			$lineItem['charge'] = dollarAmount($lineItem['charge']);
			if($lineItem['discountptr']) $lineItem['service'] = "[D] ".$lineItem['service'];		
			if($lineItem['servicecode'] || $lineItem['surchargecode']) {
				if(!$appointmentsStarted && $lineItem['recurring']) {
					$appointmentsStarted = true;
					$line = "<tr><th class='sortableListHeader'>Date</th><th class='sortableListHeader'>Time of Day</th>".
										"<th class='sortableListHeader'>Service</th><th class='sortableListHeader'>Walker</th<th class='sortableListHeader'>Charge</th>";
					$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
					$rowClasses[] = null;
				}
				$lineItem['charge'] = $lineItem['charge']; //$lineItem['paid'].
				$finalLineItems[] = $lineItem;
				$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';

			}
			else { // package

				$description = ($lineItem['monthly'] ? 'Fixed Price Monthly Schedule: '.date('F Y', strtotime($lineItem['monthyear'])) 
													: 'Miscellaneous Charge'); //.print_r($lineItem, 1)
				//$description .= ' prepaid';
				if($lineItem['reason']) $description = 'Misc Charge: '.$lineItem['reason'];
				
				if($lineItem['cancellationdate']) $description .= ' Canceled: '.shortNaturalDate(strtotime($lineItem['cancellationdate']));
				$rowClasses[] = null;
				$rowClass = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
				$line = "<tr class='$rowClass'>";
				$date = isset($lineItem['monthyear']) 
					? shortDate(strtotime($lineItem['monthyear']))
					: ($lineItem['enddate'] ? $lineItem['startdate']."-".$lineItem['enddate'] : (
					$lineItem['startdate'] ? $lineItem['startdate'] : $lineItem['date']));
				$line .= "<td class='sortableListCell'>$date</td>$todBlankCell".
									"<td class='sortableListCell' style=''>$description</td>$provBlankCell<td class='rightAlignedTD'>{$lineItem['charge']}</td></tr>";
	//print_r($line);exit;
				$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
			}
		}

		if($this->billingStatement->currentDiscount) {
			$rowClass =$rowClasses[count($rowClasses)-1] == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
			$discounts = join(', ', fetchCol0("SELECT label FROM tbldiscount WHERE discountid IN (".join(',', array_unique($this->billingStatement->currentDiscount['discounts'])).")"));
			$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																							." style=''><b>Discount Applied: </b>$discounts</td><td class='rightAlignedTD'>("
																							.dollarAmount($this->billingStatement->currentDiscount['amount'])
																							.")</td><tr>");
			$subtotal -= $this->billingStatement->currentDiscount['amount'];
		}

		$subtotalDollars = dollars($subtotal);
		$finalLineItems[] = array(
			'#CUSTOM_ROW#'=> "<tr><td colspan=$numCols style='text-align:right;font-weight:bold'>Subtotal: $subtotalDollars</td></tr>",
			);
		$finalLineItems[] = array(
			'#CUSTOM_ROW#'=> "<tr><td colspan=$numCols align=right><table>".$this->bigAmountDueLabelRow($this->billingStatement->calculateAmountDue())."</table>"
			);

		global $invoicePayment;
		if($_SESSION['preferences']['includeInvoiceGratuityLine'] && !$invoicePayment) {
			/*$gratuityLine = "<tr><td colspan=".($numCols-1)
																						." style='text-align:left;vertical-align:top;'>
																						<b>If you would like to add a gratuity, please write in an amount here.<br>
																						Thanks for your continued business.</b></td>
																						<td class='rightAlignedTD' style='border-bottom:solid #000000 1px;'>"
																						."$________</td><tr>";*/
			$gratuityLine = "<tr><td colspan=".($numCols-1)
																						." style='text-align:left;vertical-align:top;'>
																						<b>If you would like to add a gratuity, please let us know how much you want to add.<br>
																						Thanks for your continued business.</b></td></td><tr>";
			$finalLineItems[] = array('#CUSTOM_ROW#'=> $gratuityLine);
		}

	//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) 
		tableFrom($columns, $finalLineItems, 'WIDTH=100%',null,null,null,null,null,$rowClasses, array('charge'=>'rightAlignedTD'));
		echo "</td></tr></table>";
	}

	function dumpRecentPayments() {
//if(mattOnlyTEST()) print_r($this->billingStatement->allItemsSoFar);	
		$billableids = array_keys((array)$this->billingStatement->allItemsSoFar['tblrucurringpackage']);
		foreach($this->billingStatement->allItemsSoFar as $table=>$items) {
			if($table == 'tblrucurringpackage') continue;
			$billableids = array_merge($billableids,
					fetchCol0(
						"SELECT billableid 
							FROM tblbillable 
							WHERE superseded = 0 AND itemtable = '$table' AND itemptr IN (".join(',', array_keys($items)).")"));
		}
//if(mattOnlyTEST()) print_r($billableids);	
		$excludingRepayments = "AND (tblcredit.reason IS NULL OR tblcredit.reason NOT LIKE '%(v: %')";
		if($billableids) $localCredits = fetchAssociationsKeyedBy($sql =
			"SELECT tblcredit.*, tblrefund.amount as refundamount
				FROM relbillablepayment
				LEFT JOIN tblcredit on creditid = paymentptr
				LEFT JOIN tblrefund ON tblrefund.paymentptr = creditid
				WHERE billableptr IN (".join(',', $billableids).") $excludingRepayments
				AND creditid IS NOT NULL
				ORDER BY issuedate", 'creditid');
		// find gratuities and refunds
//if(mattOnlyTEST()) print_r($localCredits);	
		if($localCredits) {
			$details = fetchAssociationsKeyedBy(
			"SELECT tblcredit.*, refundid, sum(tblgratuity.amount) as gratuity, tblrefund.amount as refundamount
				FROM tblcredit
				LEFT JOIN tblrefund ON tblrefund.paymentptr = creditid
				LEFT JOIN tblgratuity ON tblgratuity.paymentptr = creditid
				WHERE creditid IN (".join(',', array_keys($localCredits)).")
				GROUP BY creditid", 'creditid');
			foreach($details as $creditid => $refundGratuity) {
				$localCredits[$creditid]['refundid'] = $refundGratuity['refundid'];
				$localCredits[$creditid]['gratuity'] = $refundGratuity['gratuity'];
			}
		}

		$exclusion = $localCredits ? "AND creditid NOT IN (".join(',', array_keys($localCredits)).")" : "";

		$localCredits = array_merge((array)$localCredits,
			fetchAssociations($sql = 
					"SELECT tblcredit.*, refundid, sum(tblgratuity.amount) as gratuity, tblrefund.amount as refundamount
						FROM tblcredit
						LEFT JOIN tblrefund ON tblrefund.paymentptr = creditid
						LEFT JOIN tblgratuity ON tblgratuity.paymentptr = creditid
						WHERE voided IS NULL AND tblcredit.clientptr = {$this->clientid} AND amountused != tblcredit.amount $exclusion $excludingRepayments
						GROUP BY creditid
						ORDER BY issuedate"));
	
	//if(mattOnlyTEST()) echo "<p>$sql<p>";	
	//if(mattOnlyTEST()) echo "<p>".print_r($localCredits, 1)."<p>";	

		echo "<div style='width:95%'>\n";
		dumpSectionBar("Recent Payments and Credits", '');
		dumpInvoiceCreditTable($localCredits, $this->billingStatement->firstDay);
		echo "</div>";
	}
}

