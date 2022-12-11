<? // billing-statement-class.php
require_once "billing-fns.php";
require_once "pet-fns.php";
//assumed require_once "db-fns.php";

class BillingStatement
{
	private $standardTaxRate;
	private $standardCharges;
	private $providers;
	private $origbalancedue;
	private $currentPaymentsAndCredits;
	private $surchargeNames;
	private $taxRates;
	private $totalDiscount;
	private $totalDiscountAmount;
	private $allPets;

	public $clientid;
	public $lineitems;
	public $currentPostDiscountPreTaxSubtotal;
	public $currentTax;
	public $priorTax;
	public $date;
	public $populated;
	public $priorDiscount;
	public $tax;
	public $credits;
	public $creditApplied;
	public $creditUnappliedToUnpaidItems;
	public $currentDiscount;
	public $firstDay;
	public $lookahead;
	public $priorunpaiditems;
	public $allItemsSoFar;
	
	private $rand;

	
	function __construct($clientid) {
		$this->rand = rand(1,100000);
		$this->clientid = $clientid;
		$this->taxRates = getClientTaxRates($clientid);
		$this->allPets = getClientPetNames($clientid);

		$this->standardCharges = getStandardCharges();
		$this->standardTaxRate = $_SESSION['preferences']['taxRate'];
		$this->surchargeNames = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
		$this->serviceNames = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
		$this->allItemsSoFar = array();
		$this->origbalancedue = 0;
		$this->tax = 0;
		$this->creditApplied = 0;
		$this->currentPaymentsAndCredits = 0;
		$this->creditUnappliedToUnpaidItems = 0;
		$this->providers = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) FROM tblprovider");
		$this->lineitems = array();
		$this->totalDiscount = array();
		$this->date = shortDate();

	}

	public function getClientid() {
		return $this->clientid;
	}
	
	function getAppointmentRows($timeAndClientFilter) {  // billables keyed by billableid, packageBillables: packageid=>billableid
		static $monthlyExclusion;
		if(!$monthlyExclusion) {
if(FALSE && mattOnlyTEST()) $MONTHLYEXCEPTION = "OR billable.charge > 0";		
			$monthlyScheduleIds = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly = 1");
			if(!$monthlyScheduleIds) $monthlyExclusion = "AND 1=1";
			else $monthlyExclusion = "AND (recurringpackage = 0 $MONTHLYEXCEPTION OR packageptr NOT IN (".join(',', $monthlyScheduleIds)."))";
		}
		$result = doQuery($sql = 
			"SELECT appointmentid, servicecode, ifnull(paid, 0) as paid, tax,
							primtable.charge + ifnull(adjustment,0) as charge, 
							ifnull(d.amount, 0) as discount, discountptr, primtable.providerptr,
							primtable.clientptr, date, starttime, timeofday, billableid, billable.charge as bcharge
				FROM tblappointment primtable
				LEFT JOIN relapptdiscount d ON appointmentptr = appointmentid
				LEFT JOIN tblbillable billable ON superseded = 0 AND itemptr = appointmentid AND itemtable = 'tblappointment'
				WHERE canceled IS NULL AND $timeAndClientFilter $monthlyExclusion");
}
				
		if(!($result = doQuery($sql))) {
			return null;
		}

		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			if($this->prepareLineItemForAppt($row)) $this->lineitems[] = $row;		
		}
		
		mysqli_free_result($result);
	}

	function getSurchargeRows($timeAndClientFilter) {  // billables keyed by billableid, packageBillables: packageid=>billableid
}
		$result = doQuery($sql = 
			"SELECT surchargeid, surchargecode, a.servicecode, paid, tax, billableid, primtable.charge, primtable.clientptr, primtable.providerptr, 
				primtable.date, primtable.starttime, primtable.timeofday, billable.charge as bcharge
				FROM tblsurcharge primtable
				LEFT JOIN tblappointment a ON appointmentid = appointmentptr
				LEFT JOIN tblbillable billable ON superseded = 0 AND itemptr = surchargeid AND itemtable = 'tblsurcharge'
				WHERE primtable.canceled IS NULL AND $timeAndClientFilter");
		if(!($result = doQuery($sql))) return null;
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			if($this->prepareLineItemForSurcharge($row)) $this->lineitems[] = $row;
		}
		mysqli_free_result($result);
	}

	function getChargeRows($timeAndClientFilter) {  // billables keyed by billableid, packageBillables: packageid=>billableid
		$result = doQuery($sql = 
			"SELECT chargeid, issuedate as date, amount as charge, o.clientptr, ifnull(paid, 0) as paid, reason, billableid
				FROM tblothercharge o
				LEFT JOIN tblbillable ON NOT superseded AND itemptr = chargeid AND itemtable = 'tblothercharge'
				WHERE $timeAndClientFilter");
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			if(isset($this->allItemsSoFar['tblothercharge'][$row['chargeid']])) continue;
			$this->allItemsSoFar['tblothercharge'][$row['chargeid']] = $row;
			$clientptr = $row['clientptr'];
			$row['service'] = 'Misc Charge: '.$row['reason'];
			//$taxRate = $this->standardTaxRate; //  <= should the default be zero?
			//$tax += ($row['tax'] = round($taxRate * $row['charge']) / 100);
	if(FALSE && staffOnlyTEST()) echo "<p>Tax(3): ".(round($taxRate * $row['charge']) / 100);
			$this->origbalancedue += $row['charge']; // + $row['tax'];
			$this->creditApplied += $row['paid'];
			$row['charge'] = $row['charge'];
			$row['sortdate'] = $row['date'];
			$row['date'] = shortDate(strtotime($row['date']));
			
			$this->lineitems[] = $row;
		}
		mysqli_free_result($result);
	}

	function getMonthlyBillableRows($firstDayDB, $lookaheadLastDay, $alternativeFilter=null) {
		$clientid = $this->clientid;
		if($alternativeFilter) $filter = $alternativeFilter;
		else {
			$firstMonth = date('Y-m', strtotime($firstDayDB)).'-1';
			$lastMonth = date('Y-m', strtotime($lookaheadLastDay)).'-1';
			$filter = "clientptr = $clientid
									AND monthyear >= '$firstMonth'
									AND monthyear <= '$lastMonth'";
		}
		$result = doQuery($sql = 
			"SELECT billableid, itemptr, charge, monthyear, primtable.clientptr, paid, tax, 1 as monthly
				FROM tblbillable primtable
				WHERE superseded = 0 
					AND $filter");
		if(!($result = doQuery($sql))) return null;
	//echo "$sql<p>";  
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	//echo print_r($row,1)."<br>";		
			if(isset($this->allItemsSoFar['tblrucurringpackage'][$row['billableid']])) continue;
			$this->allItemsSoFar['tblrucurringpackage'][$row['billableid']] = $row;
			$clientptr = $row['clientptr'];

			$row['service'] = 'Fixed Price Monthly Schedule: '.date('F Y', strtotime($row['monthyear']));
			$this->tax += $row['tax'];
			$this->origbalancedue += $row['charge']; // - $row['tax'];
			$this->creditApplied += $row['paid'];
			$row['sortdate'] = $row['monthyear'];
			$row['date'] = shortDate(strtotime($row['monthyear']));
			$this->lineitems[] = $row;
			$lastDOM = date('Y-m-t', strtotime($row['monthyear']));
			$appts = fetchAssociations(
				"SELECT * 
				FROM tblappointment
				WHERE canceled IS NULL AND clientptr = $clientid AND recurringpackage = 1
					AND date >= '{$row['monthyear']}' AND date <= '$lastDOM'");
			foreach($appts as $i =>$appt) {
				$appt['monthlyvisit'] = $row['monthyear'];
				$appt['charge'] = null;
				$appt['service'] = $_SESSION['servicenames'][$appt['servicecode']];
				$appt['sortdate'] = $row['sortdate'].'.'.$appt['date'];
				$appt['date'] = shortDate(strtotime($appt['date']));
				$this->lineitems[] = $appt;
			}
		}
		mysqli_free_result($result);
	}

	function prepareLineItemForSurcharge(&$row) {
 }
		if(isset($this->allItemsSoFar['tblsurcharge'][$row['surchargeid']])) return false;
		$this->allItemsSoFar['tblsurcharge'][$row['surchargeid']] = $row;
 }
		$clientptr = $row['clientptr'];

		$row['service'] = 'Surcharge: '.$this->surchargeNames[$row['surchargecode']];


if(!dbTEST('tonkapetsitters') || !$row['billableid']) {
		// Calculate tax ONLY if there is no billable
		$taxRate = $row['servicecode']
				? $this->taxRates[$row['servicecode']]
				: $this->standardTaxRate; //  <= should the default be zero?
		if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs
		$row['tax'] = $row['tax'] = round($taxRate * $row['charge']) / 100;
}

		$this->tax += $row['tax'];
	//if(mattOnlyTEST() && $row['clientptr'] == 1268) echo "FOUND tax: [{$row['surchargeid']}] service: {$row['servicecode']} std tax: [$standardTaxRate] charge: \${$row['charge']}]   {$row['tax']}<br>";
		$this->origbalancedue += $row['charge'] + $row['tax'];
		$this->creditApplied += $row['paid'];
		$row['provider'] = $this->providers[$row['providerptr']];
		//$row['charge'] = dollars($row['charge']);
		$row['sortdate'] = $row['date'].' '.($row['starttime']+1); // +1 to make it show after visit
		$row['date'] = shortDate(strtotime($row['date']));
		//$this->lineitems[] = $row;
		return true;
	}

	function prepareLineItemForAppt(&$row) {
		//global $allItemsSoFar, $standardCharges, $providers, $taxRates, $origbalancedue, $creditApplied, $tax, $totalDiscount;
	

		if(isset($this->allItemsSoFar['tblappointment'][$row['appointmentid']])) return false;
		
		$this->allItemsSoFar['tblappointment'][$row['appointmentid']] = $row;
		$clientptr = $row['clientptr'];
		if($row['discount'] > 0) {
			$this->totalDiscount['amount'] += $row['discount'];
			if($row['discountptr']) $this->totalDiscount['discounts'][] = $row['discountptr'];
		}
		
if(!dbTEST('tonkapetsitters') || !$row['billableid']) {
		// Calculate tax ONLY if there is no billable
		$taxRate = $this->taxRates[$row['servicecode']];
		if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs		
		$row['tax'] = round($taxRate * ($row['charge'] - $row['discount'])) / 100;
}
//if(dbTEST('tonkapetsitters')) echo "{$row['date']} \$ {$row['tax']}<br>";
		
		$this->tax += $row['tax'];
		
		if(FALSE && staffOnlyTEST()) 
			echo "<p>Tax(4)-{$row['appointmentid']}: [{$taxRates[$row['servicecode']]} * ".($row['charge'] - $row['discount'])."]".(round($taxRates[$row['servicecode']] * ($row['charge'] - $row['discount'])) / 100);

		$row['service'] = $this->serviceNames[$row['servicecode']]; // TBD fetch this instead into an instance attribute
		if($pets = $row['pets']) {
			require_once "client-fns.php";
			$extraCharge = $this->standardCharges[$appt['servicecode']]['extrapetcharge'];
			if($extraCharge && $extraCharge > 0) {
				if($pets == 'All Pets') $pets = $allPets;
				$extraPets = max(0, count(explode(',', $pets))-1);
				if($extraPets) $appt['service'] .= " (incl. charge for $extraPets add'l pet".($extraPets == 1 ? '' : 's').")";
			}
		}
		$row['provider'] = $this->providers[$row['providerptr']];
		$this->origbalancedue += $row['charge'] + $row['tax'];
		$this->creditApplied += $row['paid'];
		if($row['billableid'] && $row['paid'] < $row['charge']) $row['countablecharge'] = $row['charge'] - $row['paid'];

		$row['sortdate'] = $row['date'].' '.$row['starttime'];
		$row['date'] = shortDate(strtotime($row['date']));
		return true;
	}

	function getRowsForVisitsAndSurchargesInNRPackages($start, $end) {
		$clientptr = $this->clientid;
		$packageFilter = "current = 1 AND ((startdate >= '$start' AND startdate <= '$end') OR
											('$start' >= startdate AND '$start'  <= enddate))";

		$packageFilter = "$packageFilter AND clientptr = $clientptr"; // cancellationdate?
		$currentNRIds = fetchCol0($sql = "SELECT packageid FROM tblservicepackage WHERE $packageFilter");
	

		foreach($currentNRIds as $currpack) {
			$history = findPackageIdHistory($currpack, $clientptr, !'recurring');
	
			$result = doQuery($sql = 
				"SELECT appointmentid, servicecode, paid, primtable.charge + ifnull(adjustment,0) as charge, tax,
						ifnull(d.amount, 0) as discount, discountptr, primtable.clientptr, date, starttime, timeofday, primtable.providerptr
					FROM tblappointment primtable
					LEFT JOIN relapptdiscount d ON appointmentptr = appointmentid
					LEFT JOIN tblbillable ON superseded = 0 AND itemptr = appointmentid AND itemtable = 'tblappointment'
					WHERE canceled IS NULL AND packageptr IN (".join(',', $history).")");
			if(!($result = doQuery($sql))) return null;

}


			while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
//if(mattOnlyTEST() && $row['date'] == '2015-10-16') echo "<hr>(10/16 1) IN getRowsForVisitsAndSurchargesInNRPackages<br>".print_r($this->lineitems,1);
//if(mattOnlyTEST() && $row['date'] == '2015-10-16') echo "<hr>YINK!<br>";
				if($this->prepareLineItemForAppt($row)) $this->lineitems[] = $row;
//if(mattOnlyTEST() && $row['date'] == '10/16/2015') echo "<hr>YOINK!<br>";
//if(mattOnlyTEST() && $row['date'] == '10/16/2015') echo "<hr>(10/16 2) IN getRowsForVisitsAndSurchargesInNRPackages<br>".print_r($this->lineitems,1);
	//else if(mattOnlyTEST()) echo "No line for: ".print_r($row, 1)."<hr>";
			}
			mysqli_free_result($result);


			$result = doQuery($sql = 
				"SELECT surchargeid, surchargecode, a.servicecode, paid, tax, billableid, primtable.charge, primtable.clientptr, primtable.providerptr, 
					primtable.date, primtable.starttime, primtable.timeofday
					FROM tblsurcharge primtable
					LEFT JOIN tblappointment a ON appointmentid = appointmentptr
					LEFT JOIN tblbillable ON superseded = 0 AND itemptr = surchargeid AND itemtable = 'tblsurcharge'
					WHERE primtable.canceled IS NULL AND primtable.packageptr IN (".join(',', $history).")");
			if(!($result = doQuery($sql))) return null;
			while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
				if($this->prepareLineItemForSurcharge($row)) $this->lineitems[] = $row;
			}
			mysqli_free_result($result);
		}
	}

	function getUnpaidBillableRows($firstDayDB, $lookaheadLastDay) {
		$clientptr = $this->clientid;
		$firstMonth = date('Y-m', strtotime($firstDayDB)).'-01';
		
		$this->getAppointmentRows("primtable.clientptr = $clientptr AND primtable.date < '$firstDayDB' AND primtable.completed IS NULL AND primtable.canceled IS NULL");
		$this->getSurchargeRows("primtable.clientptr = $clientptr AND primtable.date < '$firstDayDB' AND primtable.completed IS NULL AND primtable.canceled IS NULL");
		
		$result = doQuery($sql = 
			"SELECT billableid, itemptr, itemtable, primtable.charge, paid, tax 
				FROM tblbillable primtable
				WHERE superseded = 0 
				AND primtable.clientptr = $clientptr 
				AND (paid < primtable.charge)
				AND ((monthyear IS NOT NULL AND monthyear < '$firstMonth') 
							OR itemdate < '$firstDayDB')"); //  OR itemtable = 'tblothercharge' -- dropped 2014-04-02
	
		if(!($result = doQuery($sql))) return null;
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			if($row['itemtable'] == 'tblappointment') $appts[] = $row['itemptr'];
			else if($row['itemtable'] == 'tblsurcharge') $surcharges[] = $row['itemptr'];
			else if($row['itemtable'] == 'tblothercharge') $charges[] = $row['itemptr'];
			else if($row['itemtable'] == 'tblrecurringpackage') $monthlies[] = $row['billableid'];
		}
	

		mysqli_free_result($result);
//echo "$sql<br>appts: ".print_r($appts,1).'<hr>';
		
		if($appts) $this->getAppointmentRows("appointmentid IN (".join(',', $appts).")");
		
		if($surcharges) $this->getSurchargeRows("surchargeid IN (".join(',', $surcharges).")");
		if($charges) $this->getChargeRows("chargeid IN (".join(',', $charges).")");
		if($monthlies) $this->getMonthlyBillableRows(null, null, "billableid IN (".join(',', $monthlies).")");
		return $this->lineitems;
	}

	function getBillingInvoiceCurrentLineItems($firstDayDB, $lookaheadLastDay, $literal, $packageptr=null) {
		$clientid = $this->clientid;
		//global $origbalancedue, $lineitems, $taxRates, $allItemsSoFar, $surchargeNames, $currentCharges;
		$sql = "SELECT * 
						FROM tblrecurringpackage
						WHERE clientptr = $clientid  AND (cancellationdate IS NULL OR cancellationdate > CURDATE())"; // current = 1 AND 
		$recurring = fetchFirstAssoc(tzAdjustedSql($sql));

		//$this->allItemsSoFar = array(); // TBD necessary to reinitialize?
		// handle visits

		$inTimeFrameFilter = "primtable.clientptr = $clientid AND primtable.date >= '$firstDayDB' AND primtable.date <= '$lookaheadLastDay'";
		if($packageptr) {
			$package = getPackage($packageptr);
			$recurringVisitsOnly = isset($package['enddate']);
			if($history = findPackageIdHistory($packageptr, $clientid, ($recurringVisitsOnly ? 0 : 1)))
				$inTimeFrameFilter .= "AND primtable.packageptr IN (".join(',',$history).")";
		}


	//Find sum all client's NR package prices that are prepaid and that begin in the next $lookahead days

		// For this timeframe
		// �	All non-canceled visits in timeframe.
		
		$this->getAppointmentRows($inTimeFrameFilter);
		$this->getSurchargeRows($inTimeFrameFilter);
		$this->getChargeRows("o.clientptr = $clientid AND issuedate >= '$firstDayDB' AND issuedate <= '$lookaheadLastDay'");
		$this->getMonthlyBillableRows($firstDayDB, $lookaheadLastDay);
	


		if(!$literal) {
			$this->getRowsForVisitsAndSurchargesInNRPackages($firstDayDB, $lookaheadLastDay, $clientid);
		}



		$currentCharges = $this->origbalancedue;
		usort($this->lineitems, 'dateSort');
		$this->stripeLineItems();

		return $this->lineitems;
	}

	function stripeLineItems() {
		$stripe = 'grey';
		for($i=0; $i < count((array)$this->lineitems); $i++) {
			if($this->lineitems[$i]['charge']) $stripe = $stripe == 'white' ? 'grey' : 'white';
			$this->lineitems[$i]['stripe'] = $stripe;
		}
	}
	
	function calculateAmountDue($excludedPayment=0) {
		// $credits == getUnusedClientCreditTotal($clientid)
		// $creditApplied == credit paid toward lineitems shown in the invoice
	
		return $this->origbalancedue - $this->creditApplied /*+ $this->tax*/ - $this->credits - $this->totalDiscountAmount + $excludedPayment; // + priorUnpaidItemTotal($invoice);
	}
	
	function priorUnpaidItemTotal($showOnlyCountableItems=false) {
		if($this->priorunpaiditems) 
			foreach($this->priorunpaiditems as $item) {
				// TED wrote on 6/10/2014 that the prior total line should match the prior section total
				// so I removed the 'paid' deduction
				if($showOnlyCountableItems) $total += $item['countablecharge']; // -$item['paid']
				else $total += $item['charge']; // -$item['paid']
		}
		return $total;
	}



	function populateBillingInvoice($firstDay, $lookahead, $literal=false, $showOnlyCountableItems=false, $packageptr=null, $excludePriorUnpaid=false) {
		global $suppressPriorUnpaidCreditMarkers, $decrementingCredits;

		$clientid = $this->clientid;
		if($packageptr) $literal = true;

		$firstDayInt = strtotime($firstDay ? $firstDay : date('Y-m-d'));
		$firstDayDB = date('Y-m-d', $firstDayInt);
		$lookaheadLastDayInt = strtotime("+ $lookahead days", $firstDayInt);
		$lookaheadLastDay = date('Y-m-d', $lookaheadLastDayInt);	


		$currentLineItems = $this->getBillingInvoiceCurrentLineItems($firstDayDB, $lookaheadLastDay, $literal, $packageptr);

		$this->firstDay = $firstDay;
		$this->lookahead = $lookahead;
		$this->currentTax = $this->tax;
		$this->currentDiscount = $this->totalDiscount;
		$this->totalDiscountAmount =  0+$this->currentDiscount['amount'];
		// added code to subtract $this->totalDiscountAmount on 3/20/2017
		$this->currentPostDiscountPreTaxSubtotal = $this->origbalancedue - $this->totalDiscountAmount - $this->tax;

		//$credits = min(getUnusedClientCreditTotal($clientid), $origbalancedue);	
		$this->credits = getUnusedClientCreditTotal($clientid);
		$localCreditTotal = $this->credits;

		if(!$literal || !$excludePriorUnpaid) {
			$this->totalDiscount = array();
			$this->lineitems = array();
		}

		//�	If not literal, unpaid portion of billables before timeframe. 
		if(!$excludePriorUnpaid) {
			$this->getUnpaidBillableRows($firstDayDB, $lookaheadLastDay);
		}
		
		if(!$literal || !$excludePriorUnpaid) {
			if($suppressPriorUnpaidCreditMarkers) { //  this is a global flag that MAY not be convenient to make into an property of this class
				$decrementingCredits = $this->credits; // global, decremented below
				foreach($this->lineitems as $i => $lineitem) {
					if($charge = suppressiblePriorUnpaidCharge($lineitem)) { // modifies $decrementingCredits
						$this->creditUnappliedToUnpaidItems += $charge;
						unset($this->lineitems[$i]);
					}
				}
			}
			$this->priorDiscount = $this->totalDiscount;
			$this->totalDiscountAmount +=  $this->priorDiscount['amount']; // Added her on 2/22/2014, when  removed from the line after getAppointmentRows
			$this->priorTax = $this->tax - $this->currentTax;
			$this->priorPostDiscountPreTaxSubtotal = 
				$this->origbalancedue 
				- $this->currentPostDiscountPreTaxSubtotal
				- $this->tax;
			// Consume avail credits (projected) prior to start date
			foreach($this->lineitems as $lineItem) {
				if(//strcmp($lineItem['date'], $firstDayDB) < 0) 
						$lineItem['paid'] < $lineItem['charge'] 
						&& $localCreditTotal > 0  
						&& $localCreditTotal >= $lineItem['charge']) {
					$localCreditTotal -= $lineItem['charge'];
				}
			}
			usort($this->lineitems, 'dateSort');
			$this->stripeLineItems();
			$this->priorunpaiditems = $this->lineitems;
			$this->lineitems = $currentLineItems; // restore lineitems, which was cleared for the !$literal case
		}

	}
	}			
		foreach($this->lineitems as $lineItem) {

			$this->currentPaymentsAndCredits += $lineItem['paid'];
		}

	// $availableCredit = sum of credit amount - credit amount used;
	// $paymentsAndCredits = $availableCredit - total(all unpaid visits prior to start date)
	// $amountDue = subtotal - paid - availablecredit

	// add in monthly visit lines in both current prior. mask charges on monthly visits
	// show no canceled visits at all

		$this->tax = round($this->tax * 100) / 100.0;
		$this->populated = true;
		return $this;
	}

// ##########################################
// ##########################################
}