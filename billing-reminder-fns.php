<? // billing-reminder-fns.php


function clearBillingReminders() {
	deleteTable('tblclientrequest', "requesttype = 'BillingReminder'");
}

function findUpcomingScheduleStarts() {
	global $histories;
	// find packageptrs of all non-recurring schedule appts N days ahead
	/*if($_SESSION['preferences']) $lookahead = $_SESSION['preferences']['billingReminderLookaheadDays'];
	else */
	$lookahead = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'billingReminderLookaheadDays' LIMIT 1");
	$lookahead = $lookahead ? $lookahead : 3;
	$targetDate  = date('Y-m-d', strtotime("+ $lookahead days"));
	$today = date('Y-m-d');
	// find all packages represented on target day
	$packageClients = fetchKeyValuePairs($sql = "SELECT packageptr, clientptr
														FROM tblappointment 
														WHERE canceled IS NULL AND  recurringpackage = 0 AND date = '$targetDate'");
	$endCandidates = fetchKeyValuePairs($sql = "SELECT packageptr, clientptr
														FROM tblappointment 
														WHERE canceled IS NULL AND  recurringpackage = 0 AND date = '$today'");
	foreach($endCandidates as $k => $v) $packageClients[$k] = $v;
	if(!$packageClients) return array();
	//foreach($packageids as $packageid => $clientptr) {
		//$currentPacks[findCurrentPackageVersion($packageid, $clientptr, false)] = $clientptr;
	
	// find histories for all target day client packages
	foreach(array_unique($packageClients) as $clientptr)
		$histories[$clientptr] = findPackageHistories($clientptr, 'N', 'currentOnly');
		
//print_r($sql);
//print_r($packageClients);
//echo '<p>'.join(', ', array_unique($packageClients)).'<p>';
/*foreach($histories as $clientptr=>$hes) {
	echo "<p><u>$clientptr:</u>";
	foreach($hes as $curr => $h) echo "<br><b>$curr</b>: ".join(', ', $h);
}*/
	
//print_r($packageClients);echo "<hr>";foreach($histories[47] as $current => $h) echo "$current: ".print_r($h, 1)."<p>";
	
	
	if($packageClients) {
		$currentPackageIds = array();
		foreach($histories as $clientptr => $collection)
			$currentPackageIds = array_merge($currentPackageIds, array_keys($collection)) ;
		$noReminders = fetchCol0(
			"SELECT packageid 
				FROM tblservicepackage 
				WHERE billingreminders = 0 AND packageid IN (".join(',', $currentPackageIds).")");
	}
//echo "Today: 	$today target: $targetDate";
	// find all target day client packages whose first appts fall on target day
	foreach($packageClients as $packid => $clientptr) {
		foreach($histories[$clientptr] as $currentVersion => $history) {
			if(!in_array($currentVersion, $noReminders) && in_array($packid, $history)) {
//print_r($packageClients);
//print_r("<br>$packid");
//echo "Client: $clientptr package: $currentVersion<p>";
				$history = join(',', $history);
				$dates = fetchCol0("SELECT date 
																FROM tblappointment
																WHERE canceled IS NULL AND packageptr IN ($history)
																ORDER BY date ASC");
	
//echo "<br>(".join(', ', $dates).")";
				$qualifier = $dates[0] == $targetDate ? "starting" : ($dates[count($dates)-1] == $today ? 'ending' : '');
				if($dates[0] == $targetDate || $dates[count($dates)-1] == $today) {
					$type = $dates[0] == $targetDate ? 'starting' : 'ending';
					$starters[$currentVersion] = 
						array('packageptr'=>$currentVersion, 'clientptr'=>$clientptr, 'gendate'=>$today, 'type'=>$qualifier, 'lookahead'=>$lookahead);
				}
			}
		}
	}
//echo "<br>".print_r($starters, 1);
	return array_values((array)$starters);
}

function generateBillingReminderRequests() {
	$packs = findUpcomingScheduleStarts();
	foreach($packs as $packageOfNote)
		saveNewBillingReminderRequest($packageOfNote);
	return count($packs);
}

function saveNewBillingReminderRequest($packageOfNote) {
  $request['resolved'] = 0;
  $request['requesttype'] = 'BillingReminder';
  $request['clientptr'] = $packageOfNote['clientptr'];
	$extraFields[] = "<hidden key=\"packageptr\">{$packageOfNote['packageptr']}</hidden>";
	$extraFields[] = "<hidden key=\"clientptr\">{$packageOfNote['clientptr']}</hidden>";
	$extraFields[] = "<hidden key=\"gendate\">{$packageOfNote['gendate']}</hidden>";
	$extraFields[] = "<hidden key=\"type\">{$packageOfNote['type']}</hidden>";
	$extraFields[] = "<hidden key=\"lookahead\">{$packageOfNote['lookahead']}</hidden>";
	if($extraFields) $request['extrafields'] = "<extrafields>".join('', $extraFields)."</extrafields>";
  saveNewClientRequest($request);
}


function billableNRScheduleRequestEditor($request) {
	require_once "client-flag-fns.php";

	$details = getHiddenExtraFields($request);
	$clientptr = $details['clientptr'];
	$clientDetails = getOneClientsDetails($clientptr, array('email'));
	$packageptr = $details['packageptr'];
	$packageptr = findCurrentPackageVersion($packageptr, $clientptr, false);
	if(!$packageptr) 
		$error = "No schedule information found:  Schedule deleted.";
	if($packageptr && !($appts = array_values(getAllScheduledAppointments($packageptr, $where='canceled IS NULL'))))
		$error = "No uncanceled visits found.";
	else {
		$firstVisitDate = shortNaturalDate(strtotime($dates[0] = $appts[0]['date']));
		$lastVisitDate = shortNaturalDate(strtotime($dates[1] = $appts[count($appts)-1]['date']));
	}
	$prettyGenDate = shortNaturalDate(strtotime($details['gendate']));
	if($_SESSION["flags_enabled"]) { 
		$flags = clientBillingFlagPanel($clientptr, $officeOnly=false, $noEdit=1, $contentsOnlyForBillingFlagPanel=true, $onClick='', $omitClickToEnter=true);
		if($_SESSION['preferences']['showClientFlagsInRequests']) // non-billing flags
			$flags .= clientFlagPanel($clientptr, $officeOnly=false, $noEdit=true, $contentsOnly=true);
	}
	
	echo "<h2 style='font-size:1.5em'>{$clientDetails['clientname']} $flags</h2>";
	if(staffOnlyTEST() || $_SESSION['preferences']['billingreminderaccountbalance']) {

		require_once "invoice-fns.php";		
		$accountBalance = getAccountBalance($clientptr, /*includeCredits=*/true, /*allBillables*/false);
		$accountBalance = $accountBalance == 0 ? 'PAID' : ($accountBalance < 0 ? dollarAmount(abs($accountBalance)).'cr' : dollarAmount($accountBalance));
		echo "Account Balance: $accountBalance<p>";
	
	}
	if($error) {
		echo "<span class='fontSize1_6em'>$error</span><p>";
	}
	else {
		require_once "service-fns.php";
		$package = getPackage($packageptr, 'N');
		$schedEditor = $package['irregular'] ? "service-irregular.php" : "service-nonrepeating.php";
		$history = findPackageIdHistory($packageptr, $clientptr, false);
		$history[] = $packageptr;
		$history = join(',', $history);
		$surcharges = fetchAssociations("SELECT * FROM tblsurcharge WHERE canceled IS NULL AND packageptr IN ($history) ORDER BY date, starttime");
		if($lastVisitDate == $prettyGenDate)
			$lastVisitDate = "<span style='font-weight:bold'>$lastVisitDate</span>";
		else if($firstVisitDate == shortNaturalDate(strtotime("+ {$details['lookahead']}", strtotime($details['gendate']))))
			$firstVisitDate = "<span style='font-weight:bold'>$firstVisitDate</span>";
		$dateRange = $appts[0]['date'] == $appts[count($appts)-1]['date'] ? $firstVisitDate : "$firstVisitDate - $lastVisitDate";
		$packageLink = fauxLink("$dateRange", "window.opener.location.href=\"$schedEditor?packageid=$packageptr\"", 1);
		$numVisits = count($appts) == 1 ? count($appts).' visit' : count($appts).' visits';
		$charge = $package['packageprice'];
		
		if((staffOnlyTEST() || mattOnlyTEST() || dbTEST('dogonfitness')) && $_SESSION['preferences']['betaBilling2Enabled']) {
			require_once "billing-statement-class.php";
			
			$package = fetchFirstAssoc("SELECT * from tblservicepackage WHERE packageid = $packageptr LIMIT 1", 1);
			$firstDay = $package['startdate'];
			$lastDay = $package['enddate'];
			$lookahead = round((strtotime($lastDay) - strtotime($firstDay)) / 86400); // 24 * 60 * 60

			$invoice = new BillingStatement($clientptr);
	//$statementUrl = "billing-statement-view.php?id=$clientptr&literal=1"
	//									."&invoiceby=email&email={$clientDetails['email']}&packageptr=$packageptr&excludePriorUnpaid=";
			
			$invoice->populateBillingInvoice($firstDay, $lookahead, $literal=1, $showOnlyCountableItems=null, $packageptr, $excludePriorUnpaid=false);
			$chargeWithPriorUnpaids = $invoice->calculateAmountDue($excludedPayment=0);
			$priorUnpaidsFigured = true;
		}

		$printablecharge = dollarAmount($charge);
		if(FALSE && staffOnlyTEST()) {
			$figures = calculateFinalPrice($appts, $surcharges);
			$charge = $figures['total'];
			$printablecharge = '<u>'.dollarAmount($charge).'</u>';
			$totaltitle = "title='{$figures['breakdown']}'";
		}
		echo "<table><tr><td>Schedule: $packageLink</td><td>($numVisits)</td><td $totaltitle>$printablecharge</td></tr></table><p>";
		$columns = explodePairsLine('date|Date||timeofday|Time||service|Service||provider|Sitter');
		$providerNames = fetchKeyValuePairs("SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name from tblprovider $filter");
		getAllServiceNamesById();
		$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
		$targetPage = $roDispatcher ? "client-request-appointment.php?updateList=&operation=change" : "appointment-edit.php?updateList=";
		foreach($appts as $i => $appt) {
			$appts[$i]['provider'] = 
				!$appt['providerptr'] ? 'Unassigned' : (
				$providerNames[$appt['providerptr']] ? $providerNames[$appt['providerptr']] 
				: 'Unknown');
			$svc = $_SESSION['allservicenames'][$appt['servicecode']] 
													? $_SESSION['allservicenames'][$appt['servicecode']]
													: 'Unknown';
			$popUpEditor = 
				(adequateRights('ea') || $roDispatcher)
					? "openConsoleWindow(\"editappt\", \"$targetPage&id={$appt['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})"
					: "openConsoleWindow(\"editappt\", \"appointment-view.php?id={$appt['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})";
			$appts[$i]['service'] = fauxLink($svc, $popUpEditor, 1);								
			$appts[$i]['date'] = shortDateAndDay(strtotime($appt['date']));
			$rowClasses[] = $i % 2 ? 'futuretask' : 'futuretaskEVEN';
		}
		tableFrom($columns, $appts, "WIDTH=90% style='margin-bottom:10px;'", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);

		$confirmationUrl = "notify-schedule.php?packageid=$packageptr&clientid=$clientptr&newPackage=0&offerConfirmationLink=1&ignorePreferences=1";
		echoButton('', 'Send Confirmation', 'sendConfirmation()');			
		echo " ";
		if($_SESSION['preferences']['betaBilling2Enabled'])	
			$statementUrl = "billing-statement-view.php?id=$clientptr&literal=1"
												."&invoiceby=email&email={$clientDetails['email']}&packageptr=$packageptr&excludePriorUnpaid=";
		else if(/* staffOnlyTEST() && */$_SESSION['preferences']['betaBillingEnabled'])											
			$statementUrl = "billing-invoice-view.php?id=$clientptr&firstDay={$dates[0]}&lastDay={$dates[1]}"
												."&invoiceby=email&email={$clientDetails['email']}&packageptr=$packageptr&literal=1";
		else $statementUrl = "prepayment-invoice-view.php?id=$clientptr&firstDay={$dates[0]}&lastDay={$dates[1]}"
											."&invoiceby=email&email={$clientDetails['email']}&scope=$packageptr&excludePriorUnpaidBillables=1";
		
		echoButton('', 'Send Statement', "sendStatement()");

		if($_SESSION['preferences']['enableEZScheduleEmailButton'] || staffOnlyTEST()) {
		echo " ";
		echoButton('', 'Send Schedule', "sendSchedule($clientptr, $packageptr)");
		}

		echo " ";
		echoButton('', 'Send Email', "sendEmail($clientptr)");

		if(!$_SESSION['preferences']['homeSafeSuppressed'] /*||  staffOnlyTEST() || $_SESSION['preferences']['homeSafeEnabled'] */) {
			$hiddenFields = getHiddenExtraFields($request);
			$requestTime = strtotime($request['received']);
			if($hiddenFields['type'] != 'starting') {
				echo " ";
				echoButton('', 'Send Home Safe Request', "sendHomeSafeRequest({$request['requestid']})");
			}
		}

		echo "<p>";
		require_once "cc-processing-fns.php";
		$paymentSource = getPrimaryPaySourceTypeAndId($clientptr);
		if($paymentSource) {
			//echo "<form name='chargeform' method='POST'>";
			$payLabel = 
				$paymentSource['acctid'] ? '[ACH]' 
				: "[{$paymentSource['company']} - ".($paymentSource['autopay'] ? 'autopay' : 'no autopay')."]";
			echoButton('', 'Charge', "chargeClientCard()"); // set action to 
			echo " client's $payLabel";
			echo " ";
			labeledInput('amount', 'amount', $charge, '', 'dollarinput');
			
			if($charge && $priorUnpaidsFigured) {
				$chargeDollar = number_format($charge,2);
				echo " &#9664; "
						.'Base: '.fauxLink(dollarAmount($charge), "document.getElementById(\"amount\").value=\"$chargeDollar\"", 1, 'Use this amount');
				if($chargeWithPriorUnpaids != $charge)
					echo " + prior = "
						.fauxLink(dollarAmount($chargeWithPriorUnpaids), "document.getElementById(\"amount\").value=$chargeWithPriorUnpaids", 1, 'Use this amount')
						;
					}
		}
	}
	$lastPayment = fetchFirstAssoc("SELECT amount, issuedate 
																FROM tblcredit 
																WHERE voided IS NULL AND payment = 1 AND clientptr = $clientptr
																ORDER BY issuedate DESC LIMIT 1");
	$lastPayment = 
		$lastPayment ? dollarAmount($lastPayment['amount'])." on ".shortNaturalDate(strtotime($lastPayment['issuedate'])) : '--';
	hiddenElement('billingreminder', 1);
	hiddenElement('redirecturl', '');
	echo "<br>Last Payment: $lastPayment";
	echo "<p>Office Notes:";
	echo "<p><textarea id='officenotes' name='officenotes' rows=4 cols=80 class='sortableListCell'>".$request['officenotes']."</textarea>";
	echo "<script language='javascript' src='common.js'></script>";
	if($_SESSION['preferences']['betaBilling2Enabled'])	
		$confirmExcludePriors = "	excludePriors = confirm(\"Click OK to include any prior unpaid items.\") ? 0 : 1 ;";
	echo "<script language='javascript'>
function chargeClientCard() {
		saveAndRedirect('payment-edit.php?client=$clientptr&amount='+document.getElementById(\"amount\").value);
		//document.requesteditor.operation.value = 'chargeClientCard';
		//document.requesteditor.submit();
}

function sendConfirmation() {
		saveAndRedirect('$confirmationUrl');
}


function sendSchedule(clientptr, packageptr) {
		var url = 'comm-visits-composer.php?client='+clientptr+'&scheduleid='+packageptr+'&offer=0';

		if(openConsoleWindow) {
			openConsoleWindow('bremail', url, 650,600);
			return;
		}
		saveAndRedirect(url);
}

function sendStatement() {
		var excludePriors = 1;
		$confirmExcludePriors
		saveAndRedirect('$statementUrl'+excludePriors);
}

function sendEmail(clientid) {
		if(openConsoleWindow) {
			openConsoleWindow('bremail', 'comm-composer.php?client='+clientid, 650,600);
			return;
		}
		var url = 'comm-composer.php?client='+clientid;
		saveAndRedirect(url);
}

function sendHomeSafeRequest(requestid) {
		var url = 'comm-home-safe-composer.php?requestid='+requestid;
		if(openConsoleWindow) {
			openConsoleWindow('homesafe', url, 650,600);
			return;
		}
		saveAndRedirect(url);
}

function saveAndRedirect(url) {
		document.requesteditor.operation.value = 'saveAndRedirect';
		document.requesteditor.redirecturl.value = url;
		document.requesteditor.submit();
}
	
</script>";
}


function calculateFinalPrice($appts, $surcharges) {
	if($appts) {
		require_once "tax-fns.php";
		foreach($appts as $appt) {
			if(!$appt['canceled'] && !$recurring) {
				$priceInformation['services'] += $appt['charge'] + $appt['adjustment'];
				$discount = fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = {$appt['appointmentid']} LIMIT 1");
				$priceInformation['discounts'] += $discount;
				$appt['charge'] = $appt['charge'] - $discount;
				$priceInformation['tax'] += figureTaxForAppointment($appt, ($recurring ? 'R' : 'N'));
				//echo $appt['appointmentid'].': '.print_r($priceInformation, 1).'<br>';
			}
			$apptIds[] = $appt['appointmentid'];
		}
	}
	if($surcharges) {
		$taxRate = getPreference('taxRate') ? getPreference('taxRate') : 0;
		foreach($surcharges as $surcharge) {
			$priceInformation['surcharges'] += $surcharge['charge'];
			$priceInformation['tax'] += $taxRate / 100 * $surcharge['charge'];
		}
	}
	$priceInformation['total'] = $priceInformation['services'] + $priceInformation['surcharges'] - $priceInformation['discounts'];

	$per = $package['monthly'] ? '(per Month)' : ($recurring ? '(per Week)' : '');

	$includeTaxLine = staffOnlyTEST() || dbTEST('tonkapetsitters');

	$bottomLine = $priceInformation['total'];
	if($includeTaxLine) {
		$bottomLine = $bottomLine + $priceInformation['tax'];
	}


	$breakdown = "Services: $per ".dollarAmount($priceInformation['services'])
											.($surcharges ? ' + Surcharges: '.dollarAmount($priceInformation['surcharges']) : '')
											.($priceInformation['discounts'] ? ' - Discounts: '.dollarAmount(0 - $priceInformation['discounts']) : '')
											.($includeTaxLine ? ' + Tax: '.dollarAmount($priceInformation['tax']) : '')
											.' = Total: '.dollarAmount($bottomLine);
	return array('total'=>$bottomLine, 'breakdown'=>$breakdown);
}