<? // billing-fns.php
require_once "credit-fns.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "tax-fns.php";

$billingInvoiceTag = 'billing';
$standardInvoiceMessage = "Hi #RECIPIENT#,<p>Here is your latest invoice.<p>Sincerely,<p>#BIZNAME#";
$standardMessageSubject = "Your Invoice";
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email'");
if($template) {
	$standardInvoiceMessage = $template['body'];
	$standardMessageSubject = $template['subject'];
}
function getBillingInvoiceTag() {return 'billing';}  // in case of successive load variable erasure

function getPaidInvoiceSubjectAndMessage($templateid=null) {  // in case of successive load variable erasure
	global $standardPaidInvoiceMessage, $standardPaidMessageSubject;
	if(!$standardPaidInvoiceMessage || !$standardPaidMessageSubject) {
		$standardPaidInvoiceMessage = "Hi #RECIPIENT#,<p>We have charged you for the following invoice.<p>Sincerely,<p>#BIZNAME#";
		$standardPaidMessageSubject = "Thank you for your payment";
		if($templateid) 
			$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE templateid = $templateid");
		else 
			$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Paid Invoice Email'");
		if($template) {
			$standardPaidInvoiceMessage = $template['body'];
			$standardPaidMessageSubject = $template['subject'];
		}
	}
	return array('subject'=>$standardPaidMessageSubject, 'message'=>$standardPaidInvoiceMessage);
}
//===========================================================================================================
//===========================================================================================================
//===========================================================================================================
// Billing Page Fns Fns
// (see: Individual Invoice Fns below)
function isClientRecurring($clientid) {
	$sql = "SELECT IF(monthly=1,'monthly','recurring')
					FROM tblrecurringpackage
					WHERE clientptr = $clientid AND current = 1 AND (cancellationdate IS NULL OR cancellationdate > CURDATE())";
	return fetchRow0Col0($sql);
}

function findCurrentRecurringClients($monthly) {
	$monthly = $monthly ? 'monthly = 1' : 'monthly = 0';
	$sql = "SELECT clientptr, 1 
					FROM tblrecurringpackage
					WHERE $monthly AND current = 1 AND (cancellationdate IS NULL OR cancellationdate > CURDATE())
					ORDER by packageid ASC"; // current = 1 AND 
	$pairs =  fetchKeyValuePairs(tzAdjustedSql($sql));
	return array_keys($pairs);
}

function findBillingTotals($firstDay, $lookahead, $clientids, $recurringOrMonthly, $literal) {
//screenLog("findBillingTotals($firstDay, $lookahead, $clientids, $recurringOrMonthly, $literal)");
	global $allClientsPetNames, $standardCharges, $allClientCharges, $allTaxRates, $standardTaxRate;
	//$availableCredits[$clientid] = getUnusedClientCreditTotal($clientid);
	$recurring = $recurringOrMonthly;
	$monthly = $recurringOrMonthly == 'monthly';
	$firstDayInt = strtotime($firstDay ? $firstDay : date('Y-m-d'));
	$firstDayDB = date('Y-m-d', $firstDayInt);
	$lookaheadLastDayInt = strtotime("+ $lookahead days", $firstDayInt);
	$lookaheadLastDay = date('Y-m-d', $lookaheadLastDayInt);
	$clientTest = " IN (".join(',', $clientids).")";
	
	// handle visits
	
	$inTimeFrameFilter = "primtable.clientptr $clientTest AND primtable.date >= '$firstDayDB' AND primtable.date <= '$lookaheadLastDay'";
	$preTimeFrameFilter = "primtable.clientptr $clientTest AND primtable.date < '$firstDayDB'";
	
	$standardTaxRate = $_SESSION['preferences']['taxRate'] ? $_SESSION['preferences']['taxRate'] : '';
	foreach($clientids as $clientptr)
		$allTaxRates[$clientptr] = getClientTaxRates($clientptr);

	$prepayments = fetchAssociationsKeyedBy(
		"SELECT clientid, lname, fname, CONCAT_WS(' ', fname, lname) as clientname, CONCAT_WS(', ', lname, fname) as sortname	, email
			FROM tblclient
			WHERE clientid $clientTest
			ORDER BY lname, fname", 'clientid');
			
	require_once "service-fns.php";
	require_once "pet-fns.php";
	$allClientsPetNames = getPetNamesForClients($clientids);
	$standardCharges = getStandardCharges();
	$allClientCharges = getAllClientsCharges($clientids);
	// For this timeframe
	// ·	All non-canceled visits in timeframe.
minilogResetUTime();
	$allAppts = (array)sumAppointments($prepayments, $inTimeFrameFilter, 'returnapptids');
minilog("sumAppointments: #TIME# secs.");
// ·	All surcharges in timeframe.
	$allSurcharges = (array)sumSurcharges($prepayments, $inTimeFrameFilter, 'returnSurchargeIds');
minilog("sumSurcharges: #TIME# secs.");

	// ·	All misc charges in timeframe.
	sumCharges($prepayments, "o.clientptr $clientTest AND issuedate >= '$firstDayDB' AND issuedate <= '$lastDayDB'");
minilog("sumCharges: #TIME# secs.");
	
$monthlyZeroTEST = TRUE;//staffOnlyTEST();  //TRUE;

// THIS IS A NO-OP! 
//if($monthly) sumMonthlyBillables($prepayments, $clientTest, $firstDayDB, $lookaheadLastDay);

// markPrepayementsWithMonthlyBillables sets monthlyBillablesFound=1
if($monthlyZeroTEST && $monthly) markPrepayementsWithMonthlyBillables($prepayments, $clientTest, $firstDayDB, $lookaheadLastDay);
//if(mattOnlyTEST()) print_r($prepayments[903]);
	
	
minilog("sumMonthlyBillables: #TIME# secs.");
	
	foreach($prepayments as $i => $prepayment) 
		if($prepayment['prepayment'] == 0) $prepayments[$i]['noliterals'] = 1;
//echo "STOP 0 (930): {$prepayments[930]['prepayment']}<p>";
	if($literal && staffOnlyTEST()) {
		// if literal, then for each client find if there is any anything owed for the period prior to the timeframe
		$sql = 
				"SELECT DISTINCT primtable.clientptr
					FROM tblappointment primtable
					WHERE primtable.canceled IS NULL AND primtable.completed IS NULL AND $preTimeFrameFilter";
		$priorUnpaidClientIDsForLiteralCase = fetchCol0($sql);
		$sql = 
				"SELECT DISTINCT primtable.clientptr
					FROM tblsurcharge primtable
					WHERE primtable.canceled IS NULL AND primtable.completed IS NULL AND $preTimeFrameFilter";
		foreach(fetchCol0($sql) as $cid) $priorUnpaidClientIDsForLiteralCase[] = $cid;
		
		$sql = 
				"SELECT DISTINCT primtable.clientptr
					FROM tblbillable primtable
					WHERE superseded = 0 
					AND primtable.clientptr $clientTest 
					AND paid < primtable.charge
					AND monthyear IS NULL AND itemdate < '$firstDayDB'";
		foreach(fetchCol0($sql) as $cid) $priorUnpaidClientIDsForLiteralCase[] = $cid;
		$priorUnpaidClientIDsForLiteralCase = array_unique($priorUnpaidClientIDsForLiteralCase);
		//print_r($priorUnpaidClientIDsForLiteralCase);echo "<hr>";

	}
	else if(!$literal) {
		
		// ·	If not literal, any incomplete visits before timeframe 
		$priorAppts = (array)sumAppointments($prepayments, "primtable.completed IS NULL AND $preTimeFrameFilter", 'returnapptids', $firstDayDB);
minilog("sumAppointments (prior): #TIME# secs.");

		$allAppts = array_merge($allAppts, $priorAppts);
//echo "STOP 1 (930): {$prepayments[930]['prepayment']}<p>";	

		//·	If not literal, any incomplete surcharges before timeframe
		$priorSurch = (array)sumSurcharges($prepayments, "primtable.completed IS NULL AND $preTimeFrameFilter", 'returnSurchargeIds', $firstDayDB);
minilog("sumCharges (prior): #TIME# secs.");
		$allSurcharges = array_merge($allSurcharges, $priorSurch);
//echo "STOP 2 (930): {$prepayments[930]['prepayment']}<p>";	

		//·	If not literal, unpaid portion of billables before timeframe. 
		$billableClientidsFound = array();
		$billablesByType = (array)sumUnpaidBillables($prepayments, $clientTest,	$firstDayDB, $lookaheadLastDay, $billableClientidsFound);
minilog("sumUnpaidBillables: #TIME# secs.");

//if(mattOnlyTEST()) echo  "client 905 prepayment: {$prepayments[905]['prepayment']}";
//if(mattOnlyTEST()) echo  "<br>client 905 billablesByType: ".print_r($billablesByType['tblappointment'], 1);
//echo "STOP 3 (930): {$prepayments[930]['prepayment']}<p>";echo "ALL APPTS: ".ad($allAppts)."<p>"; 
		$allAppts = array_unique(array_merge($allAppts, (array)$billablesByType['tblappointment']));
//echo "ALL APPTS(2): ".ad($allAppts)."<p>";		

		$allSurcharges = array_unique(array_merge($allSurcharges, (array)$billablesByType['tblsurcharge']));
				
		if($recurring) {
			// No additional constraints
		}
		else if(!$recurring) {
//print_r($prepayments[33]);
			
			//·	If not literal, include ALL visits in any current NR schedule whose date range overlaps timeframe
			$priorBillableClientidsFound = array();
			$subsequentBillableClientidsFound = array();
$SAVASTIME = microtime(1);
			sumAllVisitsAndSurchargesForNRPackages($prepayments, $firstDayDB, $lookaheadLastDay, $clientids, $allAppts, $allSurcharges, 
																							$priorBillableClientidsFound, $subsequentBillableClientidsFound);
minilog("sumAllVisitsAndSurchargesForNRPackages: #TIME# secs.", 0, $SAVASTIME);
			$priorClients = array();
			if($priorAppts) $priorClients = 
				fetchCol0("SELECT distinct clientptr FROM tblappointment WHERE appointmentid IN (".join(',', $priorAppts).")");
//if(mattOnlyTEST()) echo "priorClients (app): ".print_r($priorClients,1).'<br>';
			if($priorSurch) $priorClients = array_merge($priorClients, 
				fetchCol0("SELECT distinct clientptr FROM tblappointment WHERE appointmentid IN (".join(',', $priorSurch).")"));
//if(mattOnlyTEST()) echo "priorClients (surch): ".print_r($priorClients,1).'<br>';
			if($billablesByType) $priorClients = array_merge($priorClients, $billableClientidsFound);
			if($priorBillableClientidsFound) $priorClients = array_merge($priorClients, $priorBillableClientidsFound);
//if(mattOnlyTEST()) echo "priorClients (bill): ".print_r($priorClients,1).'<br>';
			foreach($priorClients as $priorclientid)
				if($prepayments[$priorclientid])
					$prepayments[$priorclientid]['includesPriors'] = 1;
//if(mattOnlyTEST()) echo "subsequentBillableClientidsFound: ".print_r($subsequentBillableClientidsFound, 1);
			foreach($subsequentBillableClientidsFound as $subsclientid)
				if($prepayments[$subsclientid])
					$prepayments[$subsclientid]['includesSubsequents'] = 1;
			foreach($prepayments as $aClientId => $pp)
				if(!$pp['clientid']) $prepayments[$aClientId]['clientid'] = $aClientId;

			//if(mattOnlyTEST()) echo "priorClients: ".print_r($priorClients,1)." pp[{$priorClients[0]}] = ".print_r($prepayments[$priorClients[0]], 1)."<br>";
//echo "STOP 4 (930): sumAllVisitsAndSurchargesForNRPackages($prepayments, $firstDayDB, $lookaheadLastDay, $clientids, ".ad($allAppts).", ".ad($allSurcharges).")<p>";	
//echo "STOP 4 (930): {$prepayments[930]['prepayment']}<p>";	
		}
	}

	foreach($prepayments as $i => $unused) {
		if($prepayments[$i]['prepayment'] == 0 
			&& $prepayments[$i]['noliterals'] // !mattOnlyTEST() && // 
			&& (!$monthlyZeroTEST || !$prepayments[$i]['monthlyBillablesFound'])
			)
			unset($prepayments[$i]);
		if($prepayments[$i] && $priorUnpaidClientIDsForLiteralCase && in_array($i, $priorUnpaidClientIDsForLiteralCase)) { 
			$prepayments[$i]['priorUnpaidForLiteralCase'] = true;//echo "===".print_r($prepayments[$i], 1).'<br>';
		}
	}
	return $prepayments;
}


function ad($arr) {return "(".join(',', (array)$arr).")";} // ".ad()."

function markPrepayementsWithMonthlyBillables(&$prepayments, $clientTest, $firstDayDB, $lookaheadLastDay) {
	$firstMonth = date('Y-m', strtotime($firstDayDB)).'-01';
	$lastMonth = date('Y-m', strtotime($lookaheadLastDay)).'-01';
	$result = doQuery($sql = 
		"SELECT itemptr, charge, paid, primtable.clientptr, billableid
			FROM tblbillable primtable
			WHERE superseded = 0 
				AND clientptr $clientTest
				AND monthyear IS NOT NULL
				AND monthyear >= '$firstMonth'
				AND monthyear <= '$lastMonth'");
  if(!($result = doQuery($sql))) return null;
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$clientptr = $row['clientptr'];
		$prepayments[$clientptr]['monthlyBillablesFound'] = 1;
		
		// collect this to be added later
		$billables[$clientptr]['prepayment'] += $row['charge'] - $row['paid'];
		$billables[$clientptr]['paid'] += $row['paid'];
		$billables[$clientptr]['owedprior'] += ($row['charge'] - $row['paid']);
		$billables[$clientptr]['paidprior'] += $row['paid'];  // amount paid toward items prior to start date
	}
	mysql_free_result($result);
	return $billables;
}

function sumMonthlyBillables($prepayments, $clientTest, $firstDayDB, $lookaheadLastDay) {
	$firstMonth = date('Y-m', strtotime($firstDayDB)).'-1';
	$lastMonth = date('Y-m', strtotime($lookaheadLastDay)).'-1';
	$result = doQuery($sql = 
		"SELECT itemptr, charge, primtable.clientptr
			FROM tblbillable primtable
			WHERE superseded = 0 
				AND clientptr $clientTest
				AND monthyear >= '$firstMonth'
				AND monthyear <= '$lastMonth'");
  if(!($result = doQuery($sql))) return null;
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$clientptr = $row['clientptr'];
		$prepayments[$clientptr]['prepayment'] += $row['charge'];  // full amount is used for in-range monthlies // - $row['paid'];
		$prepayments[$clientptr]['paid'] += $row['paid'];
		$prepayments[$clientptr]['monthlyBillablesFound'] = 1;
		$billables[] = $itemptr;
	}
	mysql_free_result($result);
	return $billables;
}

function sumAllVisitsAndSurchargesForNRPackages(&$prepayments, $start, $end, &$clientids, &$allAppts, &$allSurcharges, &$priorClientidsFound, &$subsequentClientidsFound) {
	global $allTaxRates, $taxRates, $standardTaxRate; // 7134, 7129, 7131, 7132
	$timings = array();
//sort($allAppts);if(mattOnlyTEST()) print_r($allAppts);
	$packageFilter = "current = 1 AND ((startdate >= '$start' AND startdate <= '$end') OR
										('$start' >= startdate AND '$start'  <= enddate))";
	$packageFilter = "$packageFilter AND clientptr IN (".join(',', $clientids).")"; // cancellationdate?
$sqlTime = microtime(1);
	$currentNRIds = fetchKeyValuePairs($sql = "SELECT packageid, clientptr FROM tblservicepackage WHERE $packageFilter");
$timings['SAVASFNR current package id query:'] += microtime(1) - $sqlTime;
//if(mattOnlyTEST()) {echo "$sql<hr>".print_r($currentNRIds,1)."<hr>";}
	foreach($currentNRIds as $currpack => $clientptr) {
		$priorClientFound = false;
		$subsequentClientFound = false;
$sqlTime = microtime(1);
		$history = findPackageIdHistory($currpack, $clientptr, !'recurring');
$timings['SAVASFNR history:'] += microtime(1) - $sqlTime;

//========
$timings['SAVASFNR total # of packages:'] = $totalpackageCount;
$totalpackageCount += count($history);
$timings['SAVASFNR total # of packages:'] = $totalpackageCount;
$timings['SAVASFNR avg # of packages:'] = $totalpackageCount/count($currentNRIds);
//========

$sqlTime = microtime(1);
		// added primtable.clientptr = $clientptr after creating clientptrindex to tblappointment
		$sql = 
					"SELECT date, appointmentid, servicecode, paid, primtable.charge + ifnull(adjustment,0) - ifnull(d.amount, 0) as charge, primtable.clientptr
						FROM tblappointment primtable
						LEFT JOIN relapptdiscount d ON appointmentptr = appointmentid
						LEFT JOIN tblbillable ON superseded = 0 AND itemptr = appointmentid AND itemtable = 'tblappointment'
						WHERE primtable.clientptr = $clientptr 
							AND packageptr IN (".join(',', $history).")
							AND canceled IS NULL" ;
		if(FALSE && mattOnlyTEST()) {
			$sql = str_replace('+ ifnull(adjustment,0) - ifnull(d.amount, 0)', '', $sql);
			$timings['SAVASFNR appts (NO ADJUSTMENT) TEST'] = 0;
		}
		if(FALSE && mattOnlyTEST()) {
			$sql = str_replace('LEFT JOIN relapptdiscount d ON appointmentptr = appointmentid', '', $sql);
			$sql = str_replace('- ifnull(d.amount, 0) ', '', $sql);
			$timings['SAVASFNR appts (NO DISCOUNTS) TEST'] = 0;
		}
		if(FALSE && mattOnlyTEST()) {
			$sql = str_replace("LEFT JOIN tblbillable ON superseded = 0 AND itemptr = appointmentid AND itemtable = 'tblappointment'", '', $sql);
			$sql = str_replace('paid', '0 as paid', $sql);
			$timings['SAVASFNR appts (NO BILLABLES) TEST'] = 0;
		}
		
		// on this path, we do no tblbillable join, but we collect billable.paid in separate steps
		// efficiency depends on tblbillable's 
		$noBillableJoinPath = FALSE && mattOnlyTEST();  
		if($noBillableJoinPath) {
			$sql = str_replace("LEFT JOIN tblbillable ON superseded = 0 AND itemptr = appointmentid AND itemtable = 'tblappointment'", '', $sql);
			$sql = str_replace('paid', '0 as paid', $sql);
			$timings['SAVASFNR appts (NO BILLABLES JOIN PATH)'] = 0;
		}
		
		$result = doQuery($sql);
$timings['SAVASFNR appts:'] += microtime(1) - $sqlTime;
		
		if($noBillableJoinPath) {
			$visitids = array();
			while($row = mysql_fetch_array($result, MYSQL_ASSOC))
				$visitids[] = $row['appointmentid'];
$sqlTime = microtime(1);
			$paidvisits = !$visitids ? array()
				: fetchKeyValuePairs(
					"SELECT itemptr, paid 
						FROM tblbillable 
						WHERE itemtable = 'tblappointment' 
							AND itemptr IN (".join(',', $visitids).")
							AND superseded = 0") ;
$timings['SAVASFNR billables paid:'] += microtime(1) - $sqlTime;
			$result = doQuery($sql); // to refetch visits
		}
		
		
		
		if(!$result) return null;  // ????
		while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			if(in_array($row['appointmentid'], $allAppts)) continue;
			$taxRate = $allTaxRates[$clientptr][$row['servicecode']];
			if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs		

			$tax = round($taxRate * $row['charge']) / 100;
			$prepayments[$clientptr]['prepayment'] += $row['charge'] + $tax;
			$prepayments[$clientptr]['paid'] += $row['paid'];
			if($noBillableJoinPath && $paidvisits) $prepayments[$clientptr]['paid'] += $paidvisits[$row['appointmentid']];
//if(mattOnlyTEST() && $clientptr == 1344) echo "END: $end ".print_r($row,1).'<br>';			
			if(strcmp($row['date'], $start) < 0 ) $priorClientFound = true;
			else if(strcmp($end, $row['date']) < 0 ) $subsequentClientFound = true;
		}
		mysql_free_result($result);
		
$sqlTime = microtime(1);
		// added primtable.clientptr = $clientptr after creating clientptrindex to tblappointment
		$result = doQuery($sql = 
			"SELECT surchargeid, primtable.date, a.servicecode, paid, primtable.charge, primtable.clientptr
				FROM tblsurcharge primtable
				LEFT JOIN tblappointment a ON appointmentid = appointmentptr
				LEFT JOIN tblbillable ON superseded = 0 AND itemptr = surchargeid AND itemtable = 'tblsurcharge'
				WHERE primtable.clientptr = $clientptr 
					AND primtable.canceled IS NULL 
					AND primtable.packageptr IN (".join(',', $history).")");
		if(!$result) return null;
$timings['SAVASFNR surch:'] += microtime(1) - $sqlTime;
		while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			if(in_array($row['surchargeid'], $allSurcharges)) continue;
			$clientptr = $row['clientptr'];
			$taxRate = $row['servicecode']
					? $allTaxRates[$clientptr][$row['servicecode']]
					: $standardTaxRate; //  <= should the default be zero?
			if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs		
			$tax = round($taxRate * $row['charge']) / 100;
//if(mattOnlyTEST() && $clientptr == 1268) echo "FOUND tax: [{$row['surchargeid']}] service: {$row['servicecode']} std tax: [$standardTaxRate] charge: \${$row['charge']}]   $tax<br>";
			$prepayments[$clientptr]['prepayment'] += $row['charge'] + $tax;
			$prepayments[$clientptr]['paid'] += $row['paid'];
			if(strcmp($row['date'], $start) < 0 ) $priorClientFound = true;
			else if(strcmp($end, $row['date']) < 0 ) $subsequentClientFound = true;
		}
		mysql_free_result($result);
		if($priorClientFound) $priorClientidsFound[] = $clientptr;
		if($subsequentClientFound) $subsequentClientidsFound[] = $clientptr;
	}
global $minilog;
foreach($timings as $k => $sum) $minilog[] = "$k $sum sec.<br>";
//if(mattOnlyTEST()) echo "subsequentClientidsFound: ".print_r($subsequentClientidsFound, 1);
}

function minilogResetUTime() {
	if(!staffOnlyTEST()) return;
	global $utime;
	$utime = microtime(1);
}

function minilog($message, $resetUTime=1, $suppliedTime=null) {
	if(TRUE || !staffOnlyTEST()) return;
	global $minilog, $utime;
	$useThisTime = $suppliedTime ? $suppliedTime : $utime;
	$message = str_replace('#TIME#', microtime(1) - $useThisTime, $message);
	$minilog[] = "$message<br>"; 
	if(!$suppliedTime && $resetUTime) $utime = microtime(1);
}


function sumAppointments(&$prepayments, $timeAndClientFilter, $returnApptIds, $tallyBeforeDate=null) {
	// timeAndClientFilter is usually:
	//   "primtable.clientptr IN (...) AND primtable.date >= '$firstDayDB' AND primtable.date <= '$lookaheadLastDay'"
	//  OR
	//   "primtable.completed IS NULL AND primtable.clientptr IN (...) AND primtable.date < '$firstDayDB'"

	global $allTaxRates, $standardTaxRate;
	static $monthlyExclusion;
	
	if(!$monthlyExclusion) {
		$monthlyScheduleIds = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly =1");
		if(!$monthlyScheduleIds) $monthlyExclusion = "AND 1=1";
		else $monthlyExclusion = "AND (recurringpackage = 0 OR packageptr NOT IN (".join(',', $monthlyScheduleIds)."))";
	}
	$result = doQuery($sql = 
		"SELECT appointmentid, date, servicecode, paid, primtable.charge + ifnull(adjustment,0) - ifnull(d.amount, 0) as charge, primtable.clientptr
			FROM tblappointment primtable
			LEFT JOIN relapptdiscount d ON appointmentptr = appointmentid
			LEFT JOIN tblbillable ON superseded = 0 AND itemptr = appointmentid AND itemtable = 'tblappointment'
			WHERE canceled IS NULL AND $timeAndClientFilter $monthlyExclusion");
  if(!($result = doQuery($sql))) {
		return null;
	}
//if(mattOnlyTEST()) echo "<p>".print_r($prepayments[1018], 1);
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$clientptr = $row['clientptr'];
		$taxRate = $allTaxRates[$clientptr][$row['servicecode']];
		if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs		
		
		$tax = round($taxRate * $row['charge']) / 100;
		$prepayments[$clientptr]['prepayment'] += $row['charge'] + $tax;
		$prepayments[$clientptr]['paid'] += $row['paid'];
		if($tallyBeforeDate && strcmp($row['date'], $tallyBeforeDate) < 0) {
			$prepayments[$clientptr]['owedprior'] += ($row['charge'] + $tax);
			$prepayments[$clientptr]['paidprior'] += $row['paid'];  // amount paid toward items prior to start date
			$prepayments[$clientptr]['taxprior'] += $tax;  // amount paid toward items prior to start date
		}
		if($returnApptIds) $appts[] = $row['appointmentid'];
	}

	mysql_free_result($result);
	if($returnApptIds) return $appts;
}

function sumSurcharges(&$prepayments, $timeAndClientFilter, $returnSurchargeIds, $tallyBeforeDate=null) {
	global $allTaxRates, $standardTaxRate;
	$result = doQuery($sql = 
		"SELECT surchargeid, primtable.date, a.servicecode, surchargeid, a.servicecode, paid, primtable.charge, primtable.clientptr
			FROM tblsurcharge primtable
			LEFT JOIN tblappointment a ON appointmentid = appointmentptr
			LEFT JOIN tblbillable ON superseded = 0 AND itemptr = surchargeid AND itemtable = 'tblsurcharge'
			WHERE primtable.canceled IS NULL AND $timeAndClientFilter");
  if(!($result = doQuery($sql))) return null;
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$clientptr = $row['clientptr'];
		$taxRate = $row['servicecode']
				? $allTaxRates[$clientptr][$row['servicecode']]
				: $standardTaxRate; //  <= should the default be zero?
		if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs		
		$tax = round($taxRate * $row['charge']) / 100;
//if(mattOnlyTEST() && $clientptr == 37) echo "CLIENT: $clientptr surchargeid: $surchargeid TAXRATE: $taxRate TAX: $tax NOTAXBEFORE {$row['date']}:[".noTaxBefore($row['date'])."]<br>";		
		$prepayments[$clientptr]['prepayment'] += $row['charge'] + $tax;
		$prepayments[$clientptr]['paid'] += $row['paid'];
		if($tallyBeforeDate && strcmp($row['date'], $tallyBeforeDate) < 0) {
			$prepayments[$clientptr]['owedprior'] += ($row['charge'] + $tax);
			$prepayments[$clientptr]['paidprior'] += $row['paid'];  // amount paid toward items prior to start date
			$prepayments[$clientptr]['taxprior'] += $tax;  // amount paid toward items prior to start date
		}
		if($returnSurchargeIds) $surcharges[] = $row['surchargeid'];
	}
	mysql_free_result($result);
	if($returnSurchargeIds) return $surcharges;
}

function sumCharges(&$prepayments, $timeAndClientFilter, $tallyBeforeDate=null) {
	global $standardTaxRate;
	$result = doQuery($sql = 
		"SELECT amount as charge, o.clientptr, paid
			FROM tblothercharge o
			LEFT JOIN tblbillable ON superseded = 0 AND itemptr = chargeid AND itemtable = 'tblothercharge'
			WHERE $timeAndClientFilter");
  if(!($result = doQuery($sql))) return null;
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$clientptr = $row['clientptr'];
		$tax = 0; //round($standardTaxRate * $row['charge']) / 100; // ... orshould this be zero?
		$prepayments[$clientptr]['prepayment'] += $row['charge'] + $tax;
		$prepayments[$clientptr]['paid'] += $row['paid'];
		if($tallyBeforeDate && strcmp($row['date'], $tallyBeforeDate) < 0) {
			$prepayments[$clientptr]['owedprior'] += ($row['charge'] + $tax);
			$prepayments[$clientptr]['paidprior'] += $row['paid'];  // amount paid toward items prior to start date
			$prepayments[$clientptr]['taxprior'] += $tax;  // amount paid toward items prior to start date
		}
	}
	mysql_free_result($result);
}

function sumUnpaidBillables(&$prepayments, $clientTest, $firstDayDB, $lookaheadLastDay, &$clientidsFound) {
	$firstMonth = date('Y-m', strtotime($firstDayDB)).'-01';
	$result = doQuery($sql = 
		"SELECT itemptr, itemtable, primtable.charge, paid, primtable.clientptr 
			FROM tblbillable primtable
			WHERE superseded = 0 
			AND primtable.clientptr $clientTest 
			AND paid < primtable.charge
			AND ((monthyear IS NOT NULL AND monthyear < '$firstMonth') 
						OR (monthyear IS NULL AND itemdate < '$firstDayDB'))");
//if(mattOnlyTEST()) echo "$sql<p>";
//if(mattOnlyTEST() && strpos($clientTest, '1225')) print_r($sql);
  if(!($result = doQuery($sql))) return null;
  
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$clientptr = $row['clientptr'];
		$clientidsFound[] = $clientptr;
		$prepayments[$clientptr]['prepayment'] += $row['charge'] - $row['paid'];
		$prepayments[$clientptr]['paid'] += $row['paid'];
		$prepayments[$clientptr]['owedprior'] += ($row['charge'] - $row['paid']);
		$prepayments[$clientptr]['paidprior'] += $row['paid'];  // amount paid toward items prior to start date
		$billables[$row['itemtable']][] = $row['itemptr'];
//if(staffOnlyTEST() && $clientptr == 20164) echo print_r(fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = {$row['itemptr']}"), 1).'<p>';
		
//if($clientptr ==33) 	echo "(client {$row['clientptr']}) {$row['billableid']} [{$row['itemtable']} {$row['itemptr']} {$row['date']} {$row['timeofday']}] {$row['charge']} - {$row['paid']}<p>";//.print_r($allSurcharges,1);
	}
	mysql_free_result($result);
	return $billables;
}
//===========================================================================================================
//===========================================================================================================
//===========================================================================================================
// Individual Invoice Fns

function getAppointmentRows($timeAndClientFilter) {  // billables keyed by billableid, packageBillables: packageid=>billableid
	global $lineitems;
	static $monthlyExclusion;
	if(!$monthlyExclusion) {
		$monthlyScheduleIds = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly = 1");
		if(!$monthlyScheduleIds) $monthlyExclusion = "AND 1=1";
		else $monthlyExclusion = "AND (recurringpackage = 0 OR packageptr NOT IN (".join(',', $monthlyScheduleIds)."))";
	}
	$result = doQuery($sql = 
		"SELECT appointmentid, servicecode, ifnull(paid, 0) as paid, 
						primtable.charge + ifnull(adjustment,0) as charge, 
						ifnull(d.amount, 0) as discount, discountptr, primtable.providerptr,
						primtable.clientptr, date, starttime, timeofday, billableid, billable.charge as bcharge
			FROM tblappointment primtable
			LEFT JOIN relapptdiscount d ON appointmentptr = appointmentid
			LEFT JOIN tblbillable billable ON superseded = 0 AND itemptr = appointmentid AND itemtable = 'tblappointment'
			WHERE canceled IS NULL AND $timeAndClientFilter $monthlyExclusion");
	if(!($result = doQuery($sql))) {
		return null;
	}
		
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if(prepareLineItemForAppt($row)) $lineitems[] = $row;		
	}
	mysql_free_result($result);
}
	
function getSurchargeRows($timeAndClientFilter) {  // billables keyed by billableid, packageBillables: packageid=>billableid
	global $lineitems;
	$result = doQuery($sql = 
		"SELECT surchargeid, surchargecode, a.servicecode, paid, primtable.charge, primtable.clientptr, primtable.providerptr, 
			primtable.date, primtable.starttime, primtable.timeofday, billable.charge as bcharge
			FROM tblsurcharge primtable
			LEFT JOIN tblappointment a ON appointmentid = appointmentptr
			LEFT JOIN tblbillable billable ON superseded = 0 AND itemptr = surchargeid AND itemtable = 'tblsurcharge'
			WHERE primtable.canceled IS NULL AND $timeAndClientFilter");
  if(!($result = doQuery($sql))) return null;
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if(prepareLineItemForSurcharge($row)) $lineitems[] = $row;
	}
	mysql_free_result($result);
}

function getChargeRows($timeAndClientFilter) {  // billables keyed by billableid, packageBillables: packageid=>billableid
	global $lineitems, $standardTaxRate, $providers, $allItemsSoFar, $origbalancedue, $creditApplied;
	$result = doQuery($sql = 
		"SELECT chargeid, issuedate as date, amount as charge, o.clientptr, ifnull(paid, 0) as paid, reason, billableid
			FROM tblothercharge o
			LEFT JOIN tblbillable ON NOT superseded AND itemptr = chargeid AND itemtable = 'tblothercharge'
			WHERE $timeAndClientFilter");
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if(isset($allItemsSoFar['tblothercharge'][$row['chargeid']])) continue;
		$allItemsSoFar['tblothercharge'][$row['chargeid']] = $row;
		$clientptr = $row['clientptr'];
		$row['service'] = 'Misc Charge: '.$row['reason'];
		$taxRate = $standardTaxRate; //  <= should the default be zero?
		if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs		
		$tax += ($row['tax'] = round($taxRate * $row['charge']) / 100);
		
if(FALSE && staffOnlyTEST()) echo "<p>Tax(3): ".(round($taxRate * $row['charge']) / 100);
		$origbalancedue += $row['charge'] + $row['tax'];
		$creditApplied += $row['paid'];
		$row['charge'] = $row['charge'];
		$row['sortdate'] = $row['date'];
		$row['date'] = shortDate(strtotime($row['date']));
//if(mattOnlyTEST()) echo print_r($row, 1).'<br>';		
		$lineitems[] = $row;
	}
	mysql_free_result($result);
}

function getMonthlyBillableRows($clientid, $firstDayDB, $lookaheadLastDay, $alternativeFilter=null) {
	global $lineitems, $allItemsSoFar, $origbalancedue, $tax, $creditApplied;
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
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
//echo print_r($row,1)."<br>";		
		if(isset($allItemsSoFar['tblrucurringpackage'][$row['billableid']])) continue;
		$allItemsSoFar['tblrucurringpackage'][$row['billableid']] = $row;
		$clientptr = $row['clientptr'];
		
		$row['service'] = 'Fixed Price Monthly Schedule: '.date('F Y', strtotime($row['monthyear']));
		$tax += $row['tax'];
		$origbalancedue += $row['charge']; // - $row['tax'];
		$creditApplied += $row['paid'];
		$row['sortdate'] = $row['monthyear'];
		$row['date'] = shortDate(strtotime($row['monthyear']));
		$lineitems[] = $row;
		$lastDOM = date('Y-m-t', strtotime($row['monthyear']));
		$appts = fetchAssociations(
			"SELECT * 
			FROM tblappointment
			WHERE canceled IS NULL AND clientptr = $clientid AND recurringpackage = 1
				AND date >= '{$row['monthyear']}' AND date <= '$lastDOM'");
		foreach($appts as $i =>$appt) {
			$appt['monthlyvisit'] = $row['monthyear'];
			$appt['charge'] = null;
			$appt['service'] = getBillingServiceName($appt['servicecode']); //$_SESSION['servicenames'][$appt['servicecode']];
			$appt['sortdate'] = $row['sortdate'].'.'.$appt['date'];
			$appt['date'] = shortDate(strtotime($appt['date']));
			$lineitems[] = $appt;
		}
	}
	mysql_free_result($result);
}

function getBillingServiceName($servicecode) {
	static $serviceNames;
	$serviceNames = $serviceNames ? $serviceNames : fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	return $serviceNames[$servicecode];
}

function prepareLineItemForSurcharge(&$row) {
	global $allItemsSoFar, $surchargeNames, $taxRates, $origbalancedue, $creditApplied, $tax, $standardTaxRate, $providers;
	if(isset($allItemsSoFar['tblsurcharge'][$row['surchargeid']])) return false;
	$allItemsSoFar['tblsurcharge'][$row['surchargeid']] = $row;
	$clientptr = $row['clientptr'];
	
	$row['service'] = 'Surcharge: '.$surchargeNames[$row['surchargecode']];

	$taxRate = $row['servicecode']
			? $taxRates[$row['servicecode']]
			: $standardTaxRate; //  <= should the default be zero?
	if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs		
	$tax += ($row['tax'] = round($taxRate * $row['charge']) / 100);
//if(mattOnlyTEST() && $row['clientptr'] == 1268) echo "FOUND tax: [{$row['surchargeid']}] service: {$row['servicecode']} std tax: [$standardTaxRate] charge: \${$row['charge']}]   {$row['tax']}<br>";
	$origbalancedue += $row['charge'] + $row['tax'];
	$creditApplied += $row['paid'];
	$row['provider'] = $providers[$row['providerptr']];
	//$row['charge'] = dollars($row['charge']);
	$row['sortdate'] = $row['date'].' '.($row['starttime']+1); // +1 to make it show after visit
	$row['date'] = shortDate(strtotime($row['date']));
	$lineitems[] = $row;
	return true;
}

function prepareLineItemForAppt(&$row) {
	global $allItemsSoFar, $standardCharges, $providers, $taxRates, $origbalancedue, $creditApplied, $tax, $totalDiscount;
//if(mattOnlyTEST()) echo print_r($row['appointmentid'],1)."";	
	
	if(isset($allItemsSoFar['tblappointment'][$row['appointmentid']])) return false;
//if(mattOnlyTEST()) echo "/<b>".print_r($row['appointmentid'],1)."</b><hr>";	
	$allItemsSoFar['tblappointment'][$row['appointmentid']] = $row;
	$clientptr = $row['clientptr'];
	if($row['discount'] > 0) {
		$totalDiscount['amount'] += $row['discount'];
		if($row['discountptr']) $totalDiscount['discounts'][] = $row['discountptr'];
	}
	$taxRate = $taxRates[$row['servicecode']];
	if(noTaxBefore($row['date'])) $taxRate = 0; // consults "No Taxation Before" in LeashTime Staff Only prefs		

	$tax += ($row['tax'] = round($taxRate * ($row['charge'] - $row['discount'])) / 100);
if(FALSE && staffOnlyTEST()) echo "<p>Tax(4)-{$row['appointmentid']}: [{$taxRates[$row['servicecode']]} * ".($row['charge'] - $row['discount'])."]".(round($taxRates[$row['servicecode']] * ($row['charge'] - $row['discount'])) / 100);
	
	$row['service'] = getBillingServiceName($row['servicecode']); //$_SESSION['servicenames'][$row['servicecode']];
	if($pets = $row['pets']) {
		require_once "client-fns.php";
		$standardCharges = !$standardCharges ? getStandardCharges() : $standardCharges;
		$extraCharge = $standardCharges[$appt['servicecode']]['extrapetcharge'];
		if($extraCharge && $extraCharge > 0) {
			if($pets == 'All Pets') $pets = $allPets;
			$extraPets = max(0, count(explode(',', $pets))-1);
			if($extraPets) $appt['service'] .= " (incl. charge for $extraPets add'l pet".($extraPets == 1 ? '' : 's').")";
		}
	}
	$row['provider'] = $providers[$row['providerptr']];
	$origbalancedue += $row['charge'] + $row['tax'];
	$creditApplied += $row['paid'];
	if($row['billableid'] && $row['paid'] < $row['charge']) $row['countablecharge'] = $row['charge'] - $row['paid'];

	$row['sortdate'] = $row['date'].' '.$row['starttime'];
	$row['date'] = shortDate(strtotime($row['date']));
	return true;
}

function getRowsForVisitsAndSurchargesInNRPackages($start, $end, $clientptr) {
	global $lineitems;
	$packageFilter = "current = 1 AND ((startdate >= '$start' AND startdate <= '$end') OR
										('$start' >= startdate AND '$start'  <= enddate))";
	
	$packageFilter = "$packageFilter AND clientptr = $clientptr"; // cancellationdate?
	$currentNRIds = fetchCol0($sql = "SELECT packageid FROM tblservicepackage WHERE $packageFilter");
//if(mattOnlyTEST()) echo "(2) currentNRIds: ".print_r($currentNRIds, 1)."<hr>";
	
	foreach($currentNRIds as $currpack) {
		$history = findPackageIdHistory($currpack, $clientptr, !'recurring');
//if(mattOnlyTEST()) echo "-- history: ".print_r($history, 1)."<hr>";
		$result = doQuery($sql = 
			"SELECT appointmentid, servicecode, paid, primtable.charge + ifnull(adjustment,0) as charge, 
					ifnull(d.amount, 0) as discount, discountptr, primtable.clientptr, date, starttime, timeofday, primtable.providerptr
				FROM tblappointment primtable
				LEFT JOIN relapptdiscount d ON appointmentptr = appointmentid
				LEFT JOIN tblbillable ON superseded = 0 AND itemptr = appointmentid AND itemtable = 'tblappointment'
				WHERE canceled IS NULL AND packageptr IN (".join(',', $history).")");
		if(!($result = doQuery($sql))) return null;
		
		while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			if(prepareLineItemForAppt($row)) $lineitems[] = $row;
//else if(mattOnlyTEST()) echo "No line for: ".print_r($row, 1)."<hr>";
		}
		mysql_free_result($result);
		
		
		$result = doQuery($sql = 
			"SELECT surchargeid, surchargecode, a.servicecode, paid, primtable.charge, primtable.clientptr, primtable.providerptr, 
				primtable.date, primtable.starttime, primtable.timeofday
				FROM tblsurcharge primtable
				LEFT JOIN tblappointment a ON appointmentid = appointmentptr
				LEFT JOIN tblbillable ON superseded = 0 AND itemptr = surchargeid AND itemtable = 'tblsurcharge'
				WHERE primtable.canceled IS NULL AND primtable.packageptr IN (".join(',', $history).")");
		if(!($result = doQuery($sql))) return null;
		while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			if(prepareLineItemForSurcharge($row)) $lineitems[] = $row;
		}
		mysql_free_result($result);
	}
}

function getUnpaidBillableRows($clientptr, $firstDayDB, $lookaheadLastDay) {
	global $lineitems;
	$firstMonth = date('Y-m', strtotime($firstDayDB)).'-01';
	$result = doQuery($sql = 
		"SELECT billableid, itemptr, itemtable, primtable.charge, paid, tax 
			FROM tblbillable primtable
			WHERE superseded = 0 
			AND primtable.clientptr = $clientptr 
			AND (paid < primtable.charge)
			AND ((monthyear IS NOT NULL AND monthyear < '$firstMonth') 
						OR itemdate < '$firstDayDB')"); //  OR itemtable = 'tblothercharge' -- dropped 2014-04-02
//if(mattOnlyTEST()) echo "$sql<p>";
  if(!($result = doQuery($sql))) return null;
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		if($row['itemtable'] == 'tblappointment') $appts[] = $row['itemptr'];
		else if($row['itemtable'] == 'tblsurcharge') $surcharges[] = $row['itemptr'];
		else if($row['itemtable'] == 'tblothercharge') $charges[] = $row['itemptr'];
		else if($row['itemtable'] == 'tblrecurringpackage') $monthlies[] = $row['billableid'];
	}
//if(mattOnlyTEST()) echo print_r($appts,1)."<p>";
	
	mysql_free_result($result);
	if($appts) getAppointmentRows("appointmentid IN (".join(',', $appts).")");
//if(mattOnlyTEST()) echo print_r($lineitems,1)."<p>";	
	if($surcharges) getSurchargeRows("surchargeid IN (".join(',', $surcharges).")");
	if($charges) getChargeRows("chargeid IN (".join(',', $charges).")");
	if($monthlies) getMonthlyBillableRows($clientptr, null, null, "billableid IN (".join(',', $monthlies).")");
	return $lineitems;
}


function getBillingInvoiceCurrentLineItems($clientid, $firstDayDB, $lookaheadLastDay, $literal, $packageptr=null) {
	global $origbalancedue, $lineitems, $taxRates, $allItemsSoFar, $surchargeNames, $currentCharges;
	$sql = "SELECT * 
					FROM tblrecurringpackage
					WHERE clientptr = $clientid  AND (cancellationdate IS NULL OR cancellationdate > CURDATE())"; // current = 1 AND 
	$recurring = fetchFirstAssoc(tzAdjustedSql($sql));

	$allItemsSoFar = array();
	$surchargeNames = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	// handle visits
	
	$inTimeFrameFilter = "primtable.clientptr = $clientid AND primtable.date >= '$firstDayDB' AND primtable.date <= '$lookaheadLastDay'";
	if($packageptr) {
		$package = getPackage($packageptr);
		$recurringVisitsOnly = isset($package['enddate']);
		if($history = findPackageIdHistory($packageptr, $package['clientptr'], ($recurringVisitsOnly ? 0 : 1)))
			$inTimeFrameFilter .= "AND primtable.packageptr IN (".join(',',$history).")";
	}
	
	$standardTaxRate = $_SESSION['preferences']['taxRate'] ? $_SESSION['preferences']['taxRate'] : '';
	$taxRates = getClientTaxRates($clientid);
		
	$lineitems = array();
	$origbalancedue = 0;
//Find sum all client's NR package prices that are prepaid and that begin in the next $lookahead days

	// For this timeframe
	// ·	All non-canceled visits in timeframe.
	getAppointmentRows($inTimeFrameFilter);
	getSurchargeRows($inTimeFrameFilter);
	getChargeRows("o.clientptr = $clientid AND issuedate >= '$firstDayDB' AND issuedate <= '$lookaheadLastDay'");
	getMonthlyBillableRows($clientid, $firstDayDB, $lookaheadLastDay);
//if(mattOnlyTEST()) echo "(1)currentNRIds: ".print_r($currentNRIds, 1)."<hr>";
	if(!$currentNRIds && !$literal) getRowsForVisitsAndSurchargesInNRPackages($firstDayDB, $lookaheadLastDay, $clientid);
	$currentCharges = $origbalancedue;
	usort($lineitems, 'dateSort');
	stripeLineItems();
	
	return $lineitems;
}

function getBillingInvoice($clientid, $firstDay, $lookahead, $literal=false, $showOnlyCountableItems=false, $packageptr=null) {
	global $credits, $origbalancedue, $tax, $providers, $lineitems, $currentPaymentsAndCredits, $creditUnappliedToUnpaidItems, $creditApplied, 
			$totalDiscount, $currentDiscount, $priorDiscount, $totalDiscountAmount, $standardTaxRate, $taxRates, $allPets, 
			$suppressPriorUnpaidCreditMarkers;
	
	require_once "pet-fns.php";
	$allPets = getClientPetNames($clientid); // used globally in prepareLineItemForAppt
	if($packageptr) $literal = true;
	$providers = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) FROM tblprovider");
	$tax = 0;
	$currentPaymentsAndCredits = 0;
	$creditUnappliedToUnpaidItems = 0;
	$creditApplied = 0;
	$firstDayInt = strtotime($firstDay ? $firstDay : date('Y-m-d'));
	$firstDayDB = date('Y-m-d', $firstDayInt);
	$lookaheadLastDayInt = strtotime("+ $lookahead days", $firstDayInt);
	$lookaheadLastDay = date('Y-m-d', $lookaheadLastDayInt);
	
	$standardTaxRate = $_SESSION['preferences']['taxRate'] ? $_SESSION['preferences']['taxRate'] : '';
	$taxRates = getClientTaxRates($clientid);
	
	
	$lineitems = array();
	$totalDiscount = array();
	$invoice = array(
			'clientptr'=>$clientid,
			'date'=>shortDate(), 
			'lineitems' => getBillingInvoiceCurrentLineItems($clientid, $firstDayDB, $lookaheadLastDay, $literal, $packageptr),
			'currentPostDiscountPreTaxSubtotal' => $origbalancedue - $tax,
			'currentTax' => $tax,
			'firstDay'=>$firstDay, 
			'lookahead'=>$lookahead
			);
//, $currentTax, $priorTax			
	$currentDiscount = $totalDiscount;
	$totalDiscountAmount =  $currentDiscount['amount'];
//if(mattOnlyTEST()) print_r($currentDiscount);
	//added: 3/20/2017:
	$invoice['currentPostDiscountPreTaxSubtotal'] -= 0+$totalDiscountAmount;
	
	
	
	
	//$credits = min(getUnusedClientCreditTotal($clientid), $origbalancedue);	
	$credits = getUnusedClientCreditTotal($clientid);
	$localCreditTotal = $credits;

	if(!$literal) {
		$totalDiscount = array();
		$lineitems = array();
		$preTimeFrameFilter = "primtable.clientptr = $clientid AND primtable.date < '$firstDay'";

		$filter = "(billable.charge IS NULL OR billable.charge > paid) AND $preTimeFrameFilter";
		// ·	If not literal, any incomplete visits before timeframe   bcharge IS NULL OR bcharge > paid
		$filter = "primtable.charge+ifnull(primtable.adjustment,0) > 0 AND (billable.charge IS NULL OR billable.charge > paid) AND $preTimeFrameFilter";
		getAppointmentRows($filter); //primtable.completed IS NULL
		//  ### 2/22/2014 $priorDiscount should have no value at this point ### $totalDiscountAmount +=  $priorDiscount['amount'];
		//·	If not literal, any incomplete surcharges before timeframe
		$filter = "primtable.charge > 0 AND (billable.charge IS NULL OR billable.charge > paid) AND $preTimeFrameFilter";
		getSurchargeRows($filter); //primtable.completed IS NULL
if($suppressPriorUnpaidCreditMarkers) { //  && mattOnlyTEST()
	global $decrementingCredits;
	$decrementingCredits = $credits;
	$creditsToAddBack = 0;
	foreach($lineitems as $i => $lineitem)
		if($charge = suppressiblePriorUnpaidCharge($lineitem)) {
			$creditUnappliedToUnpaidItems += $charge;
			unset($lineitems[$i]);
		}
	//$credits += $creditsToAddBack;
}
		//·	If not literal, unpaid portion of billables before timeframe. 
		getUnpaidBillableRows($clientid, $firstDayDB, $lookaheadLastDay);
		$priorDiscount = $totalDiscount;
		$totalDiscountAmount +=  $priorDiscount['amount']; // Added her on 2/22/2014, when  removed from the line after getAppointmentRows
		$invoice['priorTax'] = $tax - $invoice['currentTax'];
		$invoice['priorPostDiscountPreTaxSubtotal'] = 
			$origbalancedue 
			- $invoice['currentPostDiscountPreTaxSubtotal']
			- $tax;			
		
		// Consume avail credits (projected) prior to start date
		foreach($lineitems as $lineItem) {
			if(//strcmp($lineItem['date'], $firstDayDB) < 0) 
					$lineItem['paid'] < $lineItem['charge'] 
					&& $localCreditTotal > 0  
					&& $localCreditTotal >= $lineItem['charge']) {
				$localCreditTotal -= $lineItem['charge'];
			}
		}
		
		if($showOnlyCountableItems)
			foreach($lineitems as $i => $item) 
				if(strpos($item['service'], '[C]') === 0)  // This is a no-op.  lineitems are not yet marked [C]
					unset($lineitems[$i]);
		usort($lineitems, 'dateSort');
		stripeLineItems();
		$invoice['priorunpaiditems'] = $lineitems;
	}
//if(mattOnlyTEST()) {echo print_r($invoice, 1).'<p>';}

		// Consume avail credits (projected) in current date range
//if(mattOnlyTEST()) {echo "[$currentPaymentsAndCredits] items: "; foreach($invoice['lineitems'] as $lineItem) echo "{$lineItem['appointmentid']}, ";}			
		foreach($invoice['lineitems'] as $lineItem) {
			
			
//if(mattOnlyTEST()) echo "<hr>item: [{$lineItem['paid']}] [$localCreditTotal]".print_r($lineItem, 1);
			if(FALSE && $lineItem['paid'] < $lineItem['charge']  // DISABLED 2013-11-05 at Ted's request
					&& $localCreditTotal > 0  
					&& $localCreditTotal >= $lineItem['charge']) {
				$localCreditTotal -= $lineItem['charge'];
				$currentPaymentsAndCredits += $lineItem['charge'];
				$creditUnappliedToUnpaidItems += $lineItem['charge'];
//if(mattOnlyTEST()) echo "<hr>LINEITEM:<br>".print_r($lineItem, 1)."<hr>creditAppliedToUnpaidItems: $creditUnappliedToUnpaidItems<p>";
			}
			else $currentPaymentsAndCredits += $lineItem['paid'];
		}

// $availableCredit = sum of credit amount - credit amount used;
// $paymentsAndCredits = $availableCredit - total(all unpaid visits prior to start date)
// $amountDue = subtotal - paid - availablecredit

// add in monthly visit lines in both current prior. mask charges on monthly visits
// show no canceled visits at all

if(FALSE && staffOnlyTEST()) {	
	global $allItemsSoFar;
	echo "<p>APPOINTMENTS:<p>";
	foreach((array)$allItemsSoFar['tblappointment'] as $i => $billable) {
		if(!$billable['billableid']) 
			$billable = fetchFirstAssoc("SELECT * FROM tblbillable 
																		WHERE itemptr = {$billable['appointmentid']} AND superseded = 0 AND itemtable = 'tblappointment'");
		echo "{$billable['itemdate']} #{$billable['itemptr']} billable charge: \${$billable['charge']} paid: \${$billable['paid']}<br>";
		$paidTotal += $billable['paid'];
		if($billable['itemptr']) $itemptrs[] = $billable['itemptr'];
	}
	echo "<p>SURCHARGES:<p>";
	foreach((array)$allItemsSoFar['tblsurcharge'] as $i => $billable) {
		if(!$billable['billableid']) 
			$billable = fetchFirstAssoc("SELECT * FROM tblbillable 
																		WHERE itemptr = {$billable['surchargeid']} AND superseded = 0 AND itemtable = 'tblsurcharge'");
		echo "{$billable['itemdate']} #{$billable['itemptr']} billable charge: \${$billable['charge']} paid: \${$billable['paid']}<br>";
		$paidTotal += $billable['paid'];
	}
	echo "Total paid: \$$paidTotal<p>";
	
	if($itemptrs) print_r(fetchKeyValuePairs("SELECT itemptr, count(*) FROM tblbillable 
															WHERE superseded = 0 AND itemtable = 'tblappointment' AND itemptr IN (".join(',', $itemptrs).")
															GROUP BY itemptr"));
														
}
	
	$tax = round($tax * 100) / 100.0;
	return $invoice;
}

function dateSort($a, $b) {return strcmp($a['sortdate'], $b['sortdate']);}

function getBillingInvoiceContents($invoiceOrClientId, $firstDay, $lookahead, $literal=false, $showOnlyCountableItems=false, $includePayNowLink=false, $packageptr=null) {

	ob_start();
	ob_implicit_flush(0);
	displayBillingInvoice($invoiceOrClientId, $firstDay, $lookahead, true, $literal, $showOnlyCountableItems, $includePayNowLink, $packageptr);
	//else displayPrepaymentInvoice($clientid, $firstDay, $lookahead);
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;
}	

function invoicePageStyle() {
	return <<<STYLE
	
	<style>
	.right {text-align:right;  font-size: 1.05em;}
	.bigger-right {font-size:1.3em;text-align:right;}
	.bigger-left {font-size:1.3em;text-align:left;}
	.sortableListHeader {
		font-size: 1.05em;
		padding-bottom: 5px; 
		border-collapse: collapse;
	}

	.sortableListCell {
		font-size: 1.05em; 
		padding-bottom: 4px; 
		border-collapse: collapse;
		vertical-align: top;
	}
	</style>
STYLE;
}


function displayBillingInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true, $literal=false, $showOnlyCountableItems=false, $includePayNowLink=false, $packageptr=null) {
//prepayment-fns.php(206): displayPrepaymentInvoice($clientid, $firstDay, $lookahead);
//prepayment-fns.php(509): function displayPrepaymentInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true) {
//prepayment-invoice-print.php(41): displayPrepaymentInvoice($id, $firstDay, $lookahead, $first);
//prepayment-invoice-view.php(51): displayPrepaymentInvoice($id, $firstDay, $lookahead);

	if(is_array($invoiceOrClientId)) {
		$invoice = $invoiceOrClientId;
		$clientid = $invoice['clientptr'];
	}
	else {
		$invoice = getBillingInvoice($invoiceOrClientId, $firstDay, $lookahead, $literal, $showOnlyCountableItems, $packageptr);			
		$clientid = $invoiceOrClientId;
	}
	
//if(mattOnlyTEST()) echo "<b>clientid: [[".print_r($invoiceOrClientId,1)."]]</b><hr>";
	global $invoicePayment;
	if(is_string($invoicePayment = getInvoicePaymentData($clientid))) return;
	
//if(mattOnlyTEST()) {echo "[[{$_REQUEST['packageptr']}]]<p>";print_r($invoice); exit;}
	// This may be called in a SESSION or outside of it (cronjob)
	if($firstInvoicePrinted) echo invoicePageStyle();
	
//	<body 'style=font-size:12px;padding:10px;'>
	//$previousInvoices = getPriorUnpaidInvoices($invoice);
	$client = getClient($clientid);
	global $amountDue;
	$amountDue = calculateAmountDue();  // = amount due AFTER $invoicePayment if any
	$includePayNowLink = $includePayNowLink && !is_array($includePayNowLink) ? array('note'=>$standardMessageSubject, 'amount'=>$amountDue) : $includePayNowLink;
	
	dumpReturnSlip($invoice, $client, $includePayNowLink, $amountDue);
	$statementTitle = $_SESSION['preferences']['statementTitle'] ? $_SESSION['preferences']['statementTitle'] : 'STATEMENT';
	echo "<p align=center><b>$statementTitle</b><p>";
if(mattOnlyTEST()) {global $credits; echo "ZOOM2 CREDITS: $credits<hr>";}
	//echo "<p align=center><b>STATEMENT</b><p>";
	dumpAccountSummary($invoice, $client, $showOnlyCountableItems); // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<p>";
	//dumpInvoiceCredits($client['clientid']);
	global $credits, $decrementingCredits;
	$decrementingCredits = $credits;
//echo "CREDITS: $ 	$decrementingCredits<p>";
	dumpPriorUnpaidBillables($invoice, $clientid, $showOnlyCountableItems);
//if(mattOnlyTEST()) print_r($invoice); exit;	
	dumpCurrentBillables($invoice, $client['clientid']); // Invoice #, Invoice Date, Items, Subtotal
	dumpRecentPayments($invoice, $client['clientid']); // Invoice #, Invoice Date, Items, Subtotal
	//dumpCurrentPastInvoiceSummaries($invoiceid); // Invoice #, Invoice Date, Items, Subtotal
	dumpMessage($invoice);  // should we add a message field to invoice?
	dumpFooter();
}

function dumpReturnSlip($invoice, $client, $includePayNowLink=null, $amountDue) {
	echo "<table width='95%' border=0 bordercolor=red>";
	echo "<tr><td style='padding-bottom:8px'>";
	dumpBusinessLogoDiv($amountDue,  null, null, $client['clientid']);
	echo "</td><td align=right>";	
	dumpInvoiceHeader($invoice, $client, $includePayNowLink); // customer #, customer invoice #, invoice date, Amount Due
	echo "</td></tr>";
	echo "<tr><td>";
	dumpClientAddress($client); // mailing address or home address if no mailing address
	echo "</td><td align=right>";
	dumpInvoiceBarcode(invoiceIdDisplay($invoice['invoiceid']));
	echo "</td></tr></table>";
	if(!$_SESSION['preferences']['suppressDetachHereLine']) 
		echo "<p align=center>Please detach here and return with payment.<p><hr>";
}

function dumpInvoiceBarcode($invoiceDisplayId) {}	

function dumpAccountSummary($invoice, $client, $showOnlyCountableItems=false, $paymentData=null) {  // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	dumpSectionBar('Account Summary', "Customer Number: {$client['clientid']}");
	echo "</td>";
	echo "<tr><td style='text-align:left;vertical-align:top;'>";
	dumpClientAddress($client);
	echo "</td><td align=right>";
	dumpBalances($invoice, $client['clientid'], $showOnlyCountableItems);
	echo "</td></tr>";
	echo "</table>";
	
}

function stripeLineItems() {
	global $lineitems;
	$stripe = 'grey';
	for($i=0; $i < count((array)$lineitems); $i++) {
		if($lineitems[$i]['charge']) $stripe = $stripe == 'white' ? 'grey' : 'white';
		$lineitems[$i]['stripe'] = $stripe;
	}
}

function dumpRecentPayments($invoice, $clientid) {
	global $allItemsSoFar;
	$billableids = array_keys((array)$allItemsSoFar['tblrucurringpackage']);
//if(mattOnlyTEST()) {print_r($allItemsSoFar);exit;}	
	foreach($allItemsSoFar as $table=>$items) {
		if($table == 'tblrucurringpackage') continue;
		$billableids = array_merge($billableids,
				fetchCol0(
					"SELECT billableid 
						FROM tblbillable 
						WHERE superseded = 0 AND itemtable = '$table' AND itemptr IN (".join(',', array_keys($items)).")"));
	}
	$excludingRepayments = "AND (tblcredit.reason IS NULL OR tblcredit.reason NOT LIKE '%(v: %')";
	if($billableids) $credits = fetchAssociationsKeyedBy($sql =
		"SELECT tblcredit.*
			FROM relbillablepayment
			LEFT JOIN tblcredit on creditid = paymentptr
			WHERE billableptr IN (".join(',', $billableids).") $excludingRepayments
			ORDER BY issuedate", 'creditid');
	
	// find gratuities and refunds
	if($credits) {
		$details = fetchAssociationsKeyedBy(
		"SELECT tblcredit.*, refundid, sum(tblgratuity.amount) as gratuity, tblrefund.amount as refundamount
			FROM tblcredit
			LEFT JOIN tblrefund ON tblrefund.paymentptr = creditid
			LEFT JOIN tblgratuity ON tblgratuity.paymentptr = creditid
			WHERE creditid IN (".join(',', array_keys($credits)).")
			GROUP BY creditid", 'creditid');
		foreach($details as $creditid => $refundGratuity) {
			$credits[$creditid]['refundid'] = $refundGratuity['refundid'];
			$credits[$creditid]['gratuity'] = $refundGratuity['gratuity'];
			$credits[$creditid]['refundamount'] = $refundGratuity['refundamount'];
		}
	}
				
	$exclusion = $credits ? "AND creditid NOT IN (".join(',', array_keys($credits)).")" : "";
	
	$credits = array_merge((array)$credits,
		fetchAssociations($sql = 
				"SELECT tblcredit.*, refundid, sum(tblgratuity.amount) as gratuity, tblrefund.amount as refundamount
					FROM tblcredit
					LEFT JOIN tblrefund ON tblrefund.paymentptr = creditid
					LEFT JOIN tblgratuity ON tblgratuity.paymentptr = creditid
					WHERE voided IS NULL AND tblcredit.clientptr = $clientid AND amountused != tblcredit.amount $exclusion $excludingRepayments
					GROUP BY creditid
					ORDER BY issuedate"));
	
//if(mattOnlyTEST()) echo "<p>$sql<p>";	
//if(mattOnlyTEST()) echo "<p>".print_r($credits, 1)."<p>";	
	
	echo "<div style='width:95%'>\n";
	dumpSectionBar("Recent Payments and Credits", '');
	/*$credits = fetchAssociations(
		"SELECT * FROM tblcredit
		 WHERE clientptr = $clientid 
		  AND issuedate >= '{$invoice['firstDay']}' 
		 	AND reason NOT LIKE '%New billable created%'
		 ORDER BY issuedate"); */
		  //AND issuedate < FROM_DAYS(TO_DAYS('{$invoice['firstDay']}')+{$invoice['lookahead']})

	dumpInvoiceCreditTable($credits, $invoice['firstDay']);
	echo "</div>";
}

function dumpInvoiceCreditTable($credits, $firstDay) {
	if(!$credits) {
			echo "No payments or credits since ".shortDate(strtotime($firstDay)).".<p>";
			return;
	}
	echo "<style>.leftheader {font-size: 1.05em; padding-bottom: 5px; border-collapse: collapse; text-align: left;}</style>";

	foreach($credits as $credit) {
		
		if($credit['hide']) continue;
//if(mattOnlyTEST()) {echo "BANG! ".print_r($credit,1).'<br>';}
		$ccPrefix = 'CC: ';
		$achPrefix = 'ACH: ';
		if(strpos($credit['sourcereference'], $ccPrefix) === 0)
			$reason = "Payment via CC# **** **** **** ".substr($credit['sourcereference'], strlen($ccPrefix));
		else if(strpos($credit['sourcereference'], $achPrefix) === 0) {
			$num = substr($credit['sourcereference'], strlen($achPrefix));
			for($i=0; $i < strlen($num); $i++)
				$out[$i] = ($i < strlen($num)-4 ? '*' : $num[$i]);
			$num = $out ? join('', $out) : '';
			$reason = "Payment via ACH: $num";
		}
		else $reason = $credit['reason'];
		$refundAmount = $credit['refundamount'] ? dollarAmount($credit['refundamount']) : '';
		$refund = $credit['refundid'] ? " ($refundAmount refunded)" : '';
		$totalAmount = $credit['amount']+$credit['gratuity'];
		$lineItems[] = array('date'=>shortDate(strtotime($credit['issuedate'])), 'type'=> ($credit['payment'] ? 'payment' : 'credit'),
										'reason' => $reason, 'amount'=>dollars($totalAmount).$refund);
		if($credit['gratuity'])
		$lineItems[] = array('date'=>shortDate(strtotime($credit['issuedate'])), 'type'=> ($credit['payment'] ? 'payment' : 'credit'),
										'reason' => $reason, 'amount'=>"(".dollars($credit['gratuity']).") gratuity"/*.$refund*/);
		
	}
	
	$columns = explodePairsLine('date|Date||type|Type||reason|Reason||amount|Amount');
	//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	tableFrom($columns, $lineItems, "WIDTH=90%", null, 'leftheader', null, 'sortableListCell', null, array('amount'=>'dollaramountcell'));
	echo '<p>';
}





//===========================================================================================================


// ###########################################################



function markLineItemCovered(&$lineItem) {
	global $decrementingCredits;
	if($lineItem['paid'] < $lineItem['charge'] 
			&& $decrementingCredits > 0  
			&& $decrementingCredits >= $lineItem['charge']) {
		$decrementingCredits -= $lineItem['charge'];
		$lineItem['service'] = "[C] {$lineItem['service']}";
	}
}

function suppressiblePriorUnpaidCharge(&$lineItem) {
	global $decrementingCredits;
	if($lineItem['paid'] < $lineItem['charge'] 
			&& $decrementingCredits > 0  
			&& $decrementingCredits >= $lineItem['charge']) {
		$decrementingCredits -= $lineItem['charge'];
		return $lineItem['charge'];
	}
}

function dumpPriorUnpaidBillables($invoice, $clientid, $showOnlyCountableItems=false) { // Invoice #, Invoice Date, Items, Subtotal
// $showOnlyCountableItems is deprecated
	global $priorDiscount, $suppressPriorUnpaidCreditMarkers;
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	dumpSectionBar("Prior Unpaid Charges", "");
	echo "</td></tr><tr><td>";
	$lineItems = (array)$invoice['priorunpaiditems'];
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
  if($priorDiscount) {
		$rowClass =$rowClasses[count($rowClasses)-1] == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
		$discounts = join(', ', fetchCol0("SELECT label FROM tbldiscount WHERE discountid IN (".join(',', array_unique($priorDiscount['discounts'])).")"));
		$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																						." style=''><b>Discount Applied: </b>$discounts</td><td class='rightAlignedTD'>("
																						.dollarAmount($priorDiscount['amount'])
																						.")</td><tr>");
		$subtotal -= $priorDiscount['amount'];
	}
	
	$subtotalDollars = dollars($subtotal);
	$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr><td colspan=$numCols style='text-align:right;font-weight:bold'>Subtotal: $subtotalDollars</td><tr>");
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	tableFrom($columns, $finalLineItems, 'WIDTH=100% ',null,null,null,null,null,$rowClasses, array('charge'=>'rightAlignedTD'));
	echo "</td></tr></table>";
	//print_r($invoice['priorunpaiditems']);
}

function dumpCurrentBillables($invoice, $clientid) { // Invoice #, Invoice Date, Items, Subtotal
	global $currentDiscount;
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	dumpSectionBar("Current Charges", "");
	echo "</td></tr><tr><td>";
	$lineItems = $invoice['lineitems'];
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
	
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
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
	
  if($currentDiscount) {
		$rowClass =$rowClasses[count($rowClasses)-1] == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
		$discounts = join(', ', fetchCol0("SELECT label FROM tbldiscount WHERE discountid IN (".join(',', array_unique($currentDiscount['discounts'])).")"));
		$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																						." style=''><b>Discount Applied: </b>$discounts</td><td class='rightAlignedTD'>("
																						.dollarAmount($currentDiscount['amount'])
																						.")</td><tr>");
		$subtotal -= $currentDiscount['amount'];
	}
	
	$subtotalDollars = dollars($subtotal);
	$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr><td colspan=$numCols style='text-align:right;font-weight:bold'>Subtotal: $subtotalDollars</td><tr>");
	
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
	
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	tableFrom($columns, $finalLineItems, 'WIDTH=100%',null,null,null,null,null,$rowClasses, array('charge'=>'rightAlignedTD'));
	echo "</td></tr></table>";

}

function priorUnpaidItemTotal($invoice, $showOnlyCountableItems=false) {
	if($invoice['priorunpaiditems']) 
		foreach($invoice['priorunpaiditems'] as $item) {
			// TED wrote on 6/10/2014 that the prior total line should match the prior section total
			// so I removed the 'paid' deduction
			if($showOnlyCountableItems) $total += $item['countablecharge']; // -$item['paid']
			else $total += $item['charge']; // -$item['paid']
	}
	return $total;
}

function dumpBalances($invoice, $clientid, $showOnlyCountableItems=false) {  // what was $showOnlyCountableItems for??
	global $origbalancedue, $credits, $tax, $currentPaymentsAndCredits, $creditUnappliedToUnpaidItems, $creditApplied, $currentCharges, $currentDiscount, $priorDiscount, $totalDiscountAmount;
//if(staffOnlyTEST()) echo "credits $credits	+ creditApplied $creditApplied<p>";
	echo "<table width=60%>";
	//$currentCharges = $currentCharges-$currentDiscount['amount'];  // problem: includes TAX
	$currentCharges = $invoice['currentPostDiscountPreTaxSubtotal'];
	
	labelRow('Current Charges', '', dollars($currentCharges), $labelClass=null, 'rightAlignedTD', '', '', 'raw');
	$taxLabel = $_SESSION['preferences']['taxLabel'] ? $_SESSION['preferences']['taxLabel'] : 'Tax';
	labelRow($taxLabel, '', dollars($tax), $labelClass=null, 'rightAlignedTD', '', '', 'raw');
	//$unusedCredits = fetchRow0Col0("SELECT sum(amount - ifnull(paid,0)) FROM tblcredit WHERE clientptr = $clientid");
	
	
		// 'currentPostDiscountPreTaxSubtotal', 'currentTax', 'priorTax', 'priorPostDiscountPreTaxSubtotal'
	$priorCharges = priorUnpaidItemTotal($invoice, $showOnlyCountableItems) - $priorDiscount['amount']; // does NOT include tax

	labelRow('Prior Unpaid Charges', '', dollars($priorCharges), $labelClass=null, 'rightAlignedTD', '', '', 'raw');
//if(staffOnlyTEST()) screenLog("creditApplied: $creditApplied + credits: $credits");
	//$creditValue = $creditApplied+$credits;
	
// DEFINITION:
// $currentPaymentsAndCredits in getBillingInvoice, the sum of all credits applied to lineitems
// $creditUnappliedToUnpaidItems in getBillingInvoice, the sum of all credits applied to partially or completely unpaid lineitems
// $credits =  in getBillingInvoice, getUnusedClientCreditTotal($clientid);
// $creditApplied = credits applied (distributed accrual)
	
	
	$creditValue = /*$currentPaymentsAndCredits +*/  $creditApplied +  $credits - $creditUnappliedToUnpaidItems;
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
	$amountDue = calculateAmountDue($invoicePayment['amount']);  // = amount due BEFORE $invoicePayment if any

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
//labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)

function getInvoicePaymentData($clientid) {  // return string if error, array if payment, null if no payment
	global $invoicePaymentReference; // null or array('clientid'=>$x, 'paymentptr'=>$creditid);
	if(!$invoicePaymentReference) return;
	if($clientid != $invoicePaymentReference['clientid'])
		$error = "Invoice payment mismatch: expected clientid [$clientid] but found [{$invoicePaymentReference['clientid']}]";
	if(!($paymentptr = $invoicePaymentReference['paymentptr']))
		$error = "Payment pointer expected, but found: ".print_r($invoicePaymentReference, 1);
	else if(!($payment = fetchFirstAssoc(
		"SELECT tblcredit.*, sum(tblgratuity.amount) as gratuity
			FROM tblcredit
			LEFT JOIN tblgratuity ON tblgratuity.paymentptr = creditid
			WHERE creditid = $paymentptr
			GROUP BY creditid" , 1)))
		$error = "Payment #$paymentptr not found: ".print_r($invoicePaymentReference, 1);
		
	unset($invoicePaymentReference);
	if($error) {
		echo $error;
		return $error;
	}
	$transactionid = $payment['externalreference'];
	$type = trim(substr($transactionid, 0, strpos($transactionid, ':')));
	if($type == 'CC') {
		$itemTable = 'ccpayment';
		$selectPhrase = "SELECT company, last4 FROM tblcreditcard WHERE ccid = ";
	}
	else if($type == 'ACH') {
		$itemTable = 'achpayment';
		$selectPhrase = "SELECT last4 FROM tblecheckacct WHERE acctid = ";
	}
	$transactionid = trim(substr($transactionid, strpos($transactionid, ':')+1));
	if(!$transactionid) $error = "Transaction id not found for transaction [{$payment['externalreference']}]";
	else if(!($paymentSourceId = fetchRow0Col0(
							"SELECT itemptr FROM tblchangelog 
								WHERE itemtable = '$itemTable' AND note LIKE '%|$transactionid|%' LIMIT 1", 1)))
		$error = "$type id not found for transaction $paymentSourceId";
	else if(!($paymentSource = fetchFirstAssoc("$selectPhrase $paymentSourceId LIMIT 1", 1)))
		$error = "$type not found with id: $paymentSourceId";
	if($error) {
		echo $error;
		return $error;
	}
	$payment['type'] = $type;
	$payment['total'] = $payment['amount'] + $payment['gratuity'];
	$payment['label'] = trim("{$paymentSource['company']} {$paymentSource['last4']}");
	//$finalBalanceDue = 
	return $payment;
}







function dumpSectionBar($leftLabel, $rightLabel, $plusStyle='') {
	$background = 'lightblue';
	echo "<div style='width:100%;border:solid black 1px;font-weight:bold;background:$background;height:20px;$plusStyle'>";
	echo "<span style='float:left;'>$leftLabel</span><span style='float:right;'>$rightLabel</span>";
	echo "</div>";
}
	

function dumpClientAddress($client){ // mailing address or home address if no mailing address
	// if not "mail to home" try the mailing address, otherwise mail
	if(!$client['mailtohome']) 
		$address = getAddress($client, 'mail');
  // if still no address, try home
	if(!join('', (array)$address))
		$address = getAddress($client, '');
	echo "{$client['fname']} {$client['lname']}<br>";
	if(!join('', $address))
		echo "No Address On Record";
	else echo htmlFormattedAddress($address);
}

function dumpInvoiceHeader($invoice, $client, $includePayNowLink=null) {  // customer #, customer invoice #, invoice date, Amount Due
	$amountDue = calculateAmountDue(); // = amount due AFTER $invoicePayment if any
	echo "<table width=290>";
	//echo "<tr><td colspan=2 style='font-weight:bold'>Statement</td><tr>";
	//labelRow('Customer Number:', '', $client['clientid'], '', 'rightAlignedTD');
	labelRow('Invoice Date:', '', shortDate(strtotime($invoice['date'])), '', 'rightAlignedTD');
	//$amountDue = $origbalancedue - $creditApplied + $tax - $credits;
	//$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
	//echo "<tr><td colspan=2>origbalancedue [$origbalancedue] - creditApplied[$creditApplied] + tax[$tax] - credits[$credits] + priorUnpaidItemTotal[".priorUnpaidItemTotal($invoice)."]";	
	//$amountDue = $amountDue - $tax;
	//global $invoicePayment;
	$finalBalanceDue = $amountDue;// - $invoicePayment['amount'];
	$finalBalanceDue = $finalBalanceDue < 0 ?  dollars(abs($finalBalanceDue)).'cr' : dollars($finalBalanceDue);
	

	labelRow("<img height=16 width=20 src='https://{$_SERVER["HTTP_HOST"]}/art/redarrowright.png'>Amount Due:", '', $finalBalanceDue, $labelClass='fontSize1_8em', 'rightAlignedTD fontSize1_8em', '', 'border: solid black 1px;', 'raw');
	if($includePayNowLink) {
		$payNowLink = payNowLink($client, $includePayNowLink);
	}
	echo "<tr><td id='paynowcell' colspan=2 style='text-align:right;'>$payNowLink</td></tr>";
	echo "</table>";
}


function calculateAmountDue($excludedPayment=0) {
	// $credits == getUnusedClientCreditTotal($clientid)
	// $creditApplied == credit paid toward lineitems shown in the invoice
	global $origbalancedue, $credits, $tax, $creditApplied, $totalDiscountAmount, $currentPaymentsAndCredits;
//if(mattOnlyTEST()) echo "origbalancedue: $origbalancedue - creditApplied: $creditApplied  - credits: $credits - totalDiscountAmount: $totalDiscountAmount";
	return $origbalancedue - $creditApplied /*+ $tax*/ - $credits - $totalDiscountAmount + $excludedPayment; // + priorUnpaidItemTotal($invoice);
}



function payNowLink($client, $payNowInfo) {
		$payNowNote = $payNowInfo['note'];
		$amount = $payNowInfo['amount'];
		/* 1/8/2020 Upgraded to avoid trouble with the pipe character:
		$payNowLink = globalURL("client-cc-pay.php?rcip={$client['userid']}|{$client['clientid']}&amount=$amount&note=".urlencode($payNowNote));
		*/
		$rcip = urlencode("{$client['userid']}|{$client['clientid']}");
		$payNowLink = globalURL("client-cc-pay.php?rcip=$rcip&amount=$amount&note=".urlencode($payNowNote));
		
		$clickHere =  "<img style='text-align:right;' src='"
						.globalURL('art/payonlinebutton.png')
						."' alt='Click here to Pay Online' title='Click here to Pay Online with your credit card.'>";
		$payNowLink = "<a target='payleashtime' href='$payNowLink'>$clickHere</a>";
		return $payNowLink;
}

/*function dumpBusinessLogoDiv() {
	global $bizptr, $preferences, $mein_host;  // in absence of SESSION, $bizptr must be set to the business's id number
	if($_SESSION && isset($_SESSION["bizfiledirectory"]))
		$headerBizLogo = $_SESSION["bizfiledirectory"];
	else $headerBizLogo = "bizfiles/biz_$bizptr/";
	if($headerBizLogo) {
		if(file_exists($_SESSION["bizfiledirectory"].'logo.jpg')) $headerBizLogo .= 'logo.jpg';
		else if(file_exists($_SESSION["bizfiledirectory"].'logo.gif')) $headerBizLogo .= 'logo.gif';
		else $headerBizLogo = '';
		if($headerBizLogo) {
			$this_dir = $mein_host.substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
			$headerBizLogo = "<img src='$this_dir/$headerBizLogo' />";
		}
	}
	$headerBizLogo = $headerBizLogo 
		? $headerBizLogo . '<br>' . oneLineTextLogo($preferences)
		: textLogo($preferences);
	echo $headerBizLogo;
}
*/
function dumpBusinessLogoDiv($amountDue, $html=null, $preview=false, $clientid=null) {
	global $preferences;
	if(!$preferences) $preferences = $_SESSION['preferences'];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') print_r($preferences);	
	$headerBizLogo = getBizLogoImage(); 
	if(!$html) {
		$headerIndex =  $preview ? 'emailedInvoicePreviewHeader' : 'invoiceHeader';
		$html = $preferences[$headerIndex] //emailedInvoicePreviewHeader
			? $preferences[$headerIndex]
			: ($preview ? generateDefaultBusinessLogoDivContentsForInvoicePreview($headerBizLogo)
					:generateDefaultBusinessLogoDivContents($headerBizLogo));
	
	
		/*$html = $preferences['emailedInvoicePreviewHeader']
			? $preferences['emailedInvoicePreviewHeader']
			: ($preview ? generateDefaultBusinessLogoDivContentsForInvoicePreview($headerBizLogo)
					:generateDefaultBusinessLogoDivContents($headerBizLogo));*/
	}
	$html = str_replace('#LOGO#', $headerBizLogo, $html);
	$html = str_replace('#PHONE#', $preferences['bizPhone'], $html);
	$html = str_replace('#FAX#', $preferences['bizFax'], $html);
	$html = str_replace('#EMAIL#', $preferences['bizEmail'], $html);
	$html = str_replace('#HOMEPAGE#', $preferences['bizHomePage'], $html);
	$html = str_replace('#ADDRESS#', $preferences['bizAddress'], $html);
	$html = str_replace('#BIZNAME#', $preferences['bizName'], $html);
	$html = str_replace('#AMOUNTDUE#', $amountDue, $html);
	if($clientid) $html = str_replace('#CLIENTID#', $clientid, $html);
	
	
	if($preferences['bizAddressJSON']) // NEW bizAddress handling
		foreach(json_decode($preferences['bizAddressJSON']) as $k => $v)
			$html = str_replace("#".strtoupper($k)."#", $v, $html);
	else {
		$addressParts = explode(' | ', $preferences['bizAddress']);
		foreach(array('#STREET1#','#STREET2#','#CITY#','#STATE#','#ZIP#') as $i => $token) 
			$html = str_replace($token, $addressParts[$i], $html);
	}
	
	$html = str_replace("\n", '<br>', $html);
	echo $html;
}

function generateDefaultBusinessLogoDivContents($headerBizLogo=null, $raw=null) {
	global $preferences;
	$headerBizLogo = $headerBizLogo ? $headerBizLogo :  getBizLogoImage(); 
	$headerBizLogo = $headerBizLogo 
		? "#LOGO#\n" . oneLineTextLogo($preferences, $raw)
		: textLogo($preferences, $raw);
	return $headerBizLogo;
}

function generateDefaultBusinessLogoDivContentsForInvoicePreview($headerBizLogo=null, $raw=null) {
	global $preferences;
	$headerBizLogo = $headerBizLogo ? $headerBizLogo :  getBizLogoImage(); 
	$headerBizLogo = $headerBizLogo 
		? "#LOGO#\n" . oneLineTextLogo($preferences, $raw)
			."<hr><p align='center'>Please review the visits and charges below and let us know if there is anything that needs to be corrected.
We will charge your credit card within 48 hours unless we hear from you.</p>"
		: textLogo($preferences, $raw);
	return $headerBizLogo;
}

function getBizLogoImage() {
	global $bizptr;  // in absence of SESSION, $bizptr must be set to the business's id number
	if($_SESSION && isset($_SESSION["bizfiledirectory"]))
		$headerBizLogo = $_SESSION["bizfiledirectory"];
	else $headerBizLogo = "bizfiles/biz_$bizptr/";
	if($headerBizLogo) {
		$headerBizLogo = getHeaderBizLogo($headerBizLogo);
		if($headerBizLogo) {
			$imgSrc = globalURL($headerBizLogo);
			$headerBizLogo = "<img src='$imgSrc'>";
		}
	}
	return $headerBizLogo;
}




function oneLineTextLogo($preferences) {
	$parts = array();
	if($preferences['bizPhone']) $parts[] = "Phone: {$preferences['bizPhone']}";
	if($preferences['bizFax']) $parts[] = "FAX: {$preferences['bizFax']}";
	if($preferences['bizEmail']) $parts[] = "{$preferences['bizEmail']}";
	if($preferences['bizHomePage']) $parts[] = "{$preferences['bizHomePage']}";
	$logo =	join(' - ', $parts);
	if($preferences['bizAddress'] && trim(join('', explode('|', $preferences['bizAddress'])))) $logo = $preferences['bizAddress']."<br>$logo";
	return $logo;
}

function textLogo($preferences) {
	$logo =	"<div style='width:369;height:90'>{$preferences['bizName']}<br>{$preferences['bizAddress']}<br>Phone: {$preferences['bizPhone']}";
	if($preferences['bizFax']) $logo .=	" - FAX: {$preferences['bizFax']}";
	$logo .=	"<br>Email: {$preferences['bizEmail']}<br>{$preferences['bizHomePage']}</div>";
	return $logo;
}




function invoiceIdDisplay($invoiceid, $prefix="LT") {
	return $invoiceid? $prefix.sprintf("%04d", $invoiceid) : '(New)';
}


function dollars($amount) {
	$amount = $amount ? $amount : 0;
	//return '$ '.sprintf("%.2f",$amount);
	return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
}
	
function getAddress($client, $prefix) {
	foreach(array('street1','street2','city','state','zip') as $field) $address[$field] = $client["$prefix$field"];
	return $address;
}



function dumpCurrentPastInvoiceSummaries($invoiceid) {}// Invoice #, Date, Balance Due
function dumpMessage($invoice) {
	global $db;
	if($db == 'leashtimecustomers') return dumpLeashTimeCustomerStats($invoice);
}// should we add a message field to invoice?

function dumpLeashTimeCustomerStats($invoice) {
	require_once 'provider-fns.php';
	global $db;
	if($db != 'leashtimecustomers') return;
	if(!mattOnlyTEST()) return;
	
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	
	require_once "item-note-fns.php";
	if(itemNoteIsEnabled())
		foreach((array)$invoice->priorunpaiditems as $item) {
			$itemNote = getItemNote('tblothercharge', $item['chargeid'], $index=0);
		}
	if($itemNote) {
		dumpSectionBar("Activity in this period", "");
		echo $itemNote['note'];
	}
	/*$rows = getProviderVisitCountForMonth($date);
	foreach($rows as $row) $total += $row['visits'];
	$rows = array_merge(array(array('name'=>'<b>Total ('.count($rows).')</b>', 'visits'=>$total)), $rows);
	echo "</td></tr><tr><td>";
	echo "</td></tr></table>";
	*/
}



function dumpFooter() {
	if($_SESSION['preferences']['statementFooter']) echo $_SESSION['preferences']['statementFooter'];
}
	
function prepaymentClientLink($pp, $nameOnly=false, $sortable=false) {
//if(mattOnlyTEST()) echo "<hr>".print_r($pp, 1);	
	require_once "client-flag-fns.php";
	global $db;
	$printableName = $sortable && $db != 'leashtimecustomers' ? $pp['sortname'] : $pp['clientname'];
	if(!$nameOnly) $flagPanel = 
		clientBillingFlagPanel($pp['clientid'], $officeOnly=false, $noEdit=false, $contentsOnly=false, $onClick=null, $omitClickToEnter=false, $flagSize=15) ;
	return "<a href=#
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id={$pp['clientid']}\",700,500)'>\n$printableName"
	       .($nameOnly ? '</a>' 
	         : ("</a> $flagPanel"
						 .($pp['priorUnpaidForLiteralCase'] && $_SESSION['preferences']['betaBillingEnabled'] ? '<img src="art/arrow-prior-charges.gif" title="prior unpaid">' : '')
						 .($pp['includesPriors'] && $_SESSION['preferences']['betaBillingEnabled'] ? '<img src="art/arrow-prior-charges.gif" title="Includes charges prior to this period.">' : '')
						 .($pp['includesSubsequents'] && $_SESSION['preferences']['betaBillingEnabled'] ? ' <img src="art/arrow-subsequent-charges.gif" title="Includes charges past this period.">' : '')
						 )
					);
}

function billingPageFlagPanel($pp) {
	$flagPanel = 
		clientBillingFlagPanel($pp['clientid'], $officeOnly=false, $noEdit=false, $contentsOnly=false, $onClick=null, $omitClickToEnter=false, $flagSize=15) ;
	return $flagPanel
						.($pp['priorUnpaidForLiteralCase'] && $_SESSION['preferences']['betaBillingEnabled'] ? '<img src="art/arrow-prior-charges.gif" title="prior unpaid">' : '')
							 .($pp['includesPriors'] && $_SESSION['preferences']['betaBillingEnabled'] ? '<img src="art/arrow-prior-charges.gif" title="Includes charges prior to this period.">' : '')
						.($pp['includesSubsequents'] && $_SESSION['preferences']['betaBillingEnabled'] ? ' <img src="art/arrow-subsequent-charges.gif" title="Includes charges past this period.">' : '');
}

function ccStatus($cc) {
	global $ccStatus;
	if(!$ccStatus) {
		$ccStatus = array();
		$ccStatusRAW = <<<CCSTATUS
		No Credit Card or valid E-check acct. on file,nocc.gif,NO_CC
		Card expired: #CARD#,ccexpired.gif,CC_EXPIRED
		Autopay not enabled: #CARD#,ccnoautopay.gif,CC_NO_AUTOPAY
		Valid card on file: #CARD#,ccvalid.gif,CC_VALID
		E-check acct. on file: #CARD#,ccvalid.gif,ACH_VALID
CCSTATUS;
		foreach(explode("\n", $ccStatusRAW) as $line) {
			$set = explode(",", trim($line));
			$ccStatus[$set[2]] = $set;
		}
	}
//if(mattOnlyTEST() && $cc && !$cc['ccid'] && ($cc['clientptr'] == 1152)) {echo "[[".print_r($cc)."]]";exit;}
	if(!$cc) return $ccStatus['NO_CC'] ;
	else if($cc['acctid'] && primaryPaySourceProblem($cc['clientptr'])) return $ccStatus['NO_CC'];
	else if(!$cc['autopay']) return $ccStatus['CC_NO_AUTOPAY'];
	else if($cc['acctid']) return $ccStatus['ACH_VALID'];
	else if(strtotime(date('Y-m-t', strtotime($cc['x_exp_date']))) < strtotime(date('Y-m-d'))) return $ccStatus['CC_EXPIRED'];
	else return $ccStatus['CC_VALID'];
}

function ccStatusDisplay(&$prepayment) {
	global $clearCCs, $ccStatus;
	$clientid = $prepayment['clientid'];
	if(!$_SESSION['ccenabled']) return '';
	$cc = $clearCCs[$clientid];
//if(mattOnlyTEST()) echo print_r($prepayment, 1)." [$clientid]<p>";	
	$status = ccStatus($cc);
	if($cc) {
		$prepayment['autopay'] = $cc['autopay'];
		$cardLabel = $autopay = $cc['autopay'] ? ' [auto]' : '';
		$expiration = $cc['acctid'] ? '' : "Exp: ".shortExpirationDate($cc['x_exp_date']);
		$cardLabel = "{$cc['company']} ************{$cc['last4']} ".$expiration.$cardLabel;
	}
	$title = str_replace('#CARD#', $cardLabel, $status[0]);
	return "<img ccstatusimg='$status[2]' src='art/".$status[1]."' title='$title' />";
}

function ccStatusDisplayForClientId($clientid) {
	return ccStatusDisplayForCC(getClearCC($clientid));
}

function ccStatusDisplayForCC($cc) {
	if(!$_SESSION['ccenabled']) return '';
	$status = ccStatus($cc);
	if($cc) {
		$cardLabel = $autopay = $cc['autopay'] ? ' [auto]' : '';
		$expiration = $cc['acctid'] ? '' : "Exp: ".shortExpirationDate($cc['x_exp_date']);
		$cardLabel = "{$cc['company']} ************{$cc['last4']} ".$expiration.$cardLabel;
	}
	$title = str_replace('#CARD#', $cardLabel, $status[0]);
	return "<img src='art/".$status[1]."' title='$title' />";
}

function paymentLink($clientid, $amount, $section=null) {
	$url = "prepayment-invoice-payment.php?client=$clientid&amount=$amount";
//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) {
	return fauxLink('Pay', "openConsoleWindow(\"paymentwindow\", \"$url\",600,400)", 1, "Record a payment for this prepayment invoice.", "paylink_{$clientid}_$section");
}

function historyLink($clientid, $repeatCustomers, $starting=null, $ending=null, $section=null) {
	if($lastDate = statementLastSent($clientid, $repeatCustomers, $starting, $ending)) {
			$label = $lastDate ? "Last sent: ".shortDate(strtotime($lastDate)) : 'History'; //shortestDate
	}
	// return a link with an empty label if necessary, for later filling by ajax
	//Parse error: syntax error, unexpected T_STRING in /var/www/prod/billing-fns.php on line 2079 
	$class = $lastDate ? date('Y-m-d', strtotime($lastDate)) : '';
	return fauxLink($label, "viewRecent($clientid)", 1, "View recent prepayment invoices", "viewRecent_{$clientid}_$section", $class);
}

function historyLinkSimple($clientid, $repeatCustomers, $starting=null, $ending=null, $section=null) {
	if($lastDate = statementLastSent($clientid, $repeatCustomers, $starting, $ending)) {
			$label = $lastDate ? shortDate(strtotime($lastDate)) : 'History'; //shortestDate
	}
	// return a link with an empty label if necessary, for later filling by ajax
	//Parse error: syntax error, unexpected T_STRING in /var/www/prod/billing-fns.php on line 2079 
	$class = $lastDate ? date('Y-m-d', strtotime($lastDate)) : '';
	return fauxLink($label, "viewRecent($clientid)", 1, "View recent prepayment invoices", "viewRecent_{$clientid}_$section", $class, 'cursor:pointer;');
}

function statementLastSent($clientid, $repeatCustomers, $starting=null, $ending=null) {
	if(in_array($clientid, $repeatCustomers)) {
			$label = lastStatementSentDate($clientid, $starting, $ending);
			return $label ? shortDate(strtotime($label)) : null; //shortestDate
	}
}

function lastStatementSentDate($client, $starting=null, $ending=null) {
	$startCondition = $starting ? "AND SUBSTR(datetime, 1, 10) >= '".date('Y-m-d', strtotime($starting))."'" : '';
	$endCondition = $ending ? "AND SUBSTR(datetime, 1, 10) <= '".date('Y-m-d', strtotime($ending))."'" : '';
	//$hideStatements = in_array(userRole(), array('o', 'd')) ? '' : "AND hidefromcorresp = 0";
	$sql =
			"SELECT datetime
			 FROM tblmessage
			 WHERE correspid = $client AND inbound = 0 AND correstable = 'tblclient'
					$startCondition $endCondition
					AND ".statementSubjectPattern()." 
					$hideStatements
		 ORDER BY datetime DESC";
	return fetchRow0Col0($sql);  // should really be [ tags like '%$billingInvoiceTag% ]
}

function statementSubjectPattern() {
	return "(subject like 'prepayment'
											OR subject like '%statement%'
											OR subject like '%invoice%'
											OR tags like '%prepayment%'
											OR tags like '%billing%') ";
}
	

function chargeLog($message, $restart=false) {
	if($restart || !array_key_exists('billingchargelog', $_SESSION)) $_SESSION['billingchargelog'] = '';
	$_SESSION['billingchargelog'] .= $message;
	return $message;
}

function getChargeLog() {
	if($_SESSION['billingchargelog'] && substr($_SESSION['billingchargelog'], strlen('</table>')) != '</table>')
		$_SESSION['billingchargelog'] .= '</table>';
	return $_SESSION['billingchargelog'];
}