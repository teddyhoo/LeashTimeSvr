<? // billing-fns-pu.php -- new prior unpaid functionality

function dynamicPaymentLink($clientid, $section=null) {
	return fauxLink('Pay', "openPaymentWindow($clientid)", 1, "Record a payment for this invoice statement.", "paylink_{$clientid}_$section");
}



function findBillingTotalsWithPriorUnpaids($firstDay, $lookahead, $clientids, $recurringOrMonthly, $literal) {
	
	// THE VALUE PRODUCED FOR NON-LITERAL/NO PRIOR UNPAID CHARGES IS NOT RELIABLE
	
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
//if(mattOnlyTEST()) print_r($prepayments[1018]);			
minilog("sumAppointments: #TIME# secs.");
// ·	All surcharges in timeframe.
	$allSurcharges = (array)sumSurcharges($prepayments, $inTimeFrameFilter, 'returnSurchargeIds');
minilog("sumSurcharges: #TIME# secs.");

	// ·	All misc charges in timeframe.
	sumCharges($prepayments, "o.clientptr $clientTest AND issuedate >= '$firstDayDB' AND issuedate <= '$lookaheadLastDay'");
minilog("sumCharges: #TIME# secs.");
	
$monthlyZeroTEST = TRUE;//staffOnlyTEST();  //TRUE;

// THIS IS A NO-OP! 
//if($monthly) sumMonthlyBillables($prepayments, $clientTest, $firstDayDB, $lookaheadLastDay);

//if(mattOnlyTEST()) print_r($prepayments[1018]);
// markPrepayementsWithMonthlyBillables sets monthlyBillablesFound=1
if($monthlyZeroTEST && $monthly) $monthlyBillables = markPrepayementsWithMonthlyBillables($prepayments, $clientTest, $firstDayDB, $lookaheadLastDay);
if(TRUE || staffOnlyTEST()) {
	//print_r($monthlyBillables);
	foreach((array)$monthlyBillables as $clientptr => $mbTotals) {
		$prepayments[$clientptr]['prepayment'] += $mbTotals['prepayment'];
		$prepayments[$clientptr]['paid'] += $mbTotals['paid'];
		$prepayments[$clientptr]['owedprior'] += $mbTotals['owedprior'];
		$prepayments[$clientptr]['paidprior'] += $mbTotals['paidprior'];  // amount paid toward items prior to start date
	}
}
//if(mattOnlyTEST()) print_r($prepayments[1018]);
	
	
minilog("sumMonthlyBillables: #TIME# secs.");
	
	foreach($prepayments as $i => $prepayment) 
		if($prepayment['prepayment'] == 0) $prepayments[$i]['noliterals'] = 1;
//echo "STOP 0 (930): {$prepayments[930]['prepayment']}<p>";

// START PRIOR UNPAID
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
//if(mattOnlyTEST()) print_r($prepayments[21153]);
//if(mattOnlyTEST()) print_r($billableClientidsFound);

//if(mattOnlyTEST()) echo  "client 905 prepayment: {$prepayments[905]['prepayment']}";
//if(mattOnlyTEST()) echo  "<br>client 905 billablesByType: ".print_r($billablesByType['tblappointment'], 1);
//echo "STOP 3 (930): {$prepayments[930]['prepayment']}<p>";echo "ALL APPTS: ".ad($allAppts)."<p>"; 
	$allAppts = array_unique(array_merge($allAppts, (array)$billablesByType['tblappointment']));
//echo "ALL APPTS(2): ".ad($allAppts)."<p>";		

	$allSurcharges = array_unique(array_merge($allSurcharges, (array)$billablesByType['tblsurcharge']));
// END PRIOR UNPAID 


	if(!$literal) {
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

			//if(mattOnlyTEST()) echo "priorClients: ".print_r($priorClients,1)." pp[{$priorClients[0]}] = ".print_r($prepayments[$priorClients[0]], 1)."<br>";
//echo "STOP 4 (930): sumAllVisitsAndSurchargesForNRPackages($prepayments, $firstDayDB, $lookaheadLastDay, $clientids, ".ad($allAppts).", ".ad($allSurcharges).")<p>";	
//echo "STOP 4 (930): {$prepayments[930]['prepayment']}<p>";	
		}
	}

/* // START PRIOR UNPAID
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
// END PRIOR UNPAID  */


		
	foreach($prepayments as $aClientId => $pp)
		if(!$pp['clientid']) $prepayments[$aClientId]['clientid'] = $aClientId;

//echo "OIK! - ".print_r($prepayments, 1);
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
	
	
	
function STAB1_findBillingTotalsWithPriorUnpaids($firstDay, $lookahead, $clientids, $recurringOrMonthly, $literal) {
	
	/* Each $prepayment entry will include:
			'prepayment' -- (possibly discounted and taxed) total charge for client
			'paid' -- total amount paid on all items for client
			'owedprior' -- (possibly discounted and taxed) total charge for client BEFORE $firstDay
			'paidprior' -- total amount paid toward items prior to start date
			'taxprior' -- total tax to be collected on items before start date
	*/
	
	
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
	
	$inTimeFrameFilter = "primtable.clientptr $clientTest  AND primtable.date <= '$lookaheadLastDay'"; // removed AND primtable.date >= '$firstDayDB'
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
	// in this new scheme (Sept 2015) we will NOT set a start date on the filter, but we WILL tally all charges before the start date
	// NOTE: 'prepayment' includes discount!
	$allAppts = (array)sumAppointments($prepayments, $inTimeFrameFilter, 'returnapptids', $tallyBeforeDate=$firstDayDB);
minilog("sumAppointments: #TIME# secs.");
// ·	All surcharges in timeframe.
	$allSurcharges = (array)sumSurcharges($prepayments, $inTimeFrameFilter, 'returnSurchargeIds', $tallyBeforeDate=$firstDayDB);
minilog("sumSurcharges: #TIME# secs.");

	// ·	All misc charges in timeframe.
	sumCharges($prepayments, "o.clientptr $clientTest AND issuedate <= '$lastDayDB'", $tallyBeforeDate=$firstDayDB); // removed AND issuedate >= '$firstDayDB' 
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

	if(!$literal) {				
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
		if($prepayments[$i] && $priorUnpaidClientIDs && in_array($i, $priorUnpaidClientIDs)) { 
			$prepayments[$i]['priorUnpaidForLiteralCase'] = true;//echo "===".print_r($prepayments[$i], 1).'<br>';
		}
	}
	return $prepayments;
}