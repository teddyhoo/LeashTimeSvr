<? // year-over-year-fns.php
/* report on one week, year over year.
gather a starting date and compare the seven days from there to the same date range in the previous year.
compare: uncanceled vists, revenues, pay to sitters
offer: breakdowns by type, by sitter
offer charts: visits and revenue
*/

//function visitCountsMonthly

function unpackOptions($options) {
	if(!$options || is_array($options))
		return $options;
	foreach(explode(',', $options) as $piece) {
		$pair = explode('=', $piece);
		$pairs[$pair[0]] = $pair[1];
	}
	return $pairs;
}

function yearToDateMonthlyRevenue($lastYear=false, $baseYear=null, $options=null) {
	// options may be option1=1,option2=zeebax,...
	$options = unpackOptions($options);
	$Yvalue = $baseYear ? $baseYear : 'Y';
	$start = date("$Yvalue-01-01");
	$days = date('z'); // day of the year, 0 through 365.  add 1 to that.
	if($options['wholeMonths']) {
		// if today is the last day of the month, do nothing
		// else set days to the z-1 for the first day of this month
		if(date('j') < date('t')) $days = date('z', strtotime($start))-1;
	}
	if($baseYear && $baseYear < date('Y')) {
		$start = "$baseYear-01-01";
		$days = 366;
	}
	$result = collectVisits($start, $days, $lastYear, $returnHandle=true);
  while($appt = mysqli_fetch_array($result, MYSQL_ASSOC)) {
  	$monthIndex = (int)substr($appt['date'], 5, 2) - 1;
  	$revs[$monthIndex] += $appt['charge']+$appt['adjstment'];
  }

	$result = collectSurcharges($start, $days, $lastYear, $returnHandle=true);
  while($surch = mysqli_fetch_array($result, MYSQL_ASSOC)) {
  	$monthIndex = (int)substr($surch['date'], 5, 2) - 1;
  	$revs[$monthIndex] += $surch['charge'];
  }
 	$result = collectMiscCharges($start, $days, $lastYear, $returnHandle=true);
  while($misc = mysqli_fetch_array($result, MYSQL_ASSOC)) {
   	$monthIndex = (int)substr($misc['issuedate'], 5, 2) - 1;
		$revs[$monthIndex] += $misc['amount'];
	}
 	$result = collectMonthlyCharges($start, $days, $lastYear, $returnHandle=true);
  while($mnth = mysqli_fetch_array($result, MYSQL_ASSOC)) {
   	$monthIndex = (int)substr($mnth['itemdate'], 5, 2) - 1;
		$revs[$monthIndex] += $mnth['charge']-$mnth['tax'];
	}
  return $revs;
}

function completeSeriesDataKeys(&$a, &$b) {
	$akeys = array_keys($a);
	$bkeys = array_keys($b);
	foreach(array_diff($akeys, $bkeys) as $k) $b[$k] = 0;
	ksort($b);
	foreach(array_diff($bkeys, $akeys) as $k) $a[$k] = 0;
	ksort($a);
}

function yearToDateMonthlyVisitCounts($lastYear=false, $baseYear=null, $options=null) {
	// options may be option1=1,option2=zeebax,...
	$options = unpackOptions($options);
	$Yvalue = $baseYear ? $baseYear : 'Y';
	$start = date("$Yvalue-01-01");
	$days = date('z'); // day of the year, 0 through 365.  add 1 to that.
	if($options['wholeMonths']) {
		// if today is the last day of the month, do nothing
		// else set days to the z-1 for the first day of this month
		if(date('j') < date('t')) $days = date('z', strtotime($start))-1;
	}
	if($baseYear && $baseYear < date('Y')) {
		$start = "$baseYear-01-01";
		$days = 366;
	}
	$result = collectVisits($start, $days, $lastYear, $returnHandle=true);
  while($appt = mysqli_fetch_array($result, MYSQL_ASSOC)) {
  	$monthIndex = (int)substr($appt['date'], 5, 2) - 1;
  	$counts[$monthIndex] += 1;
  }
  
  return $counts;
}

function bracketDates($start, $days=null, $lastYear=false) {
	// start is the first day, end is the day after the last day
	global $globalDays;
	$start = date('Y-m-d', strtotime($start));
	if($lastYear)
		$start = ((int)substr($start, 0, 4) - 1).substr($start, 4);
	$days = $days ? $days : $globalDays;
	$end = date('Y-m-d', strtotime("+ $days DAYS", strtotime($start)));
	return array($start, $end);
}

function compileStats($yearKey, $start, $days, $lastYear) {
	global $stats, $clientZIPs; //array('thisyear'=>
	//								array(visits, charge, rate,
	//										sitters(name=>visitcount...), 
	//										services(label=>visitcount), 
	//										sitterrev(name=>subtotal)
	//										sitterpay(name=>subtotal)
	$clientZIPs = fetchKeyValuePairs("SELECT clientid, zip FROM tblclient");
	//$visits = collectVisits($start, $days=null, $lastYear=false, $returnHandle=false)
	$visitResult = collectVisits($start, $days, $lastYear, $returnHandle=true);
	//foreach($visits as $appt) {
	while($appt = leashtime_next_assoc($visitResult)) {
		$charge = $appt['charge']+$appt['adjustment'];
		$rate = $appt['rate']+$appt['bonus'];
		$stats[$yearKey]['visits'] += 1;
		$stats[$yearKey]['visitscharge'] += $charge;
		$stats[$yearKey]['charge'] += $charge;
		$stats[$yearKey]['rate'] += $rate;
		$stats[$yearKey]['sitters'][$appt['providerptr']] += 1;
		$stats[$yearKey]['services'][$appt['servicecode']] += 1;
		$stats[$yearKey]['servicescharge'][$appt['servicecode']] += $charge;
		$stats[$yearKey]['servicesrate'][$appt['servicecode']] += $rate;
		$stats[$yearKey]['sitterrev'][$appt['providerptr']] += $charge;
		$stats[$yearKey]['sitterpay'][$appt['providerptr']] += $rate;
		
		$stats[$yearKey]['zips'][$clientZIPs[$appt['clientptr']]] += 1;
		$stats[$yearKey]['zipsrev'][$clientZIPs[$appt['clientptr']]] += $charge;
		$stats[$yearKey]['zipspay'][$clientZIPs[$appt['clientptr']]] += $rate;
		
		$month = date('Y-m-01', strtotime($appt['date']));
		if($appt['recurringpackage']) $stats[$yearKey]['recurring'][$month][$appt['clientptr']] = 1;
		else {
			$stats[$yearKey]['nonrecurring'][$month][$appt['clientptr']] = 1;
			global $showPackageCount;
if($showPackageCount) {
			if(!$nrCurrentPacks[$appt['clientptr']])
				$nrCurrentPacks[$appt['clientptr']]
					= findPackageHistories($appt['clientptr'], $RorNorNull='N', $currentOnly=true);
			foreach($nrCurrentPacks[$appt['clientptr']] as $currID => $history) {
				if(in_array($appt['packageptr'], $history)) {
					$stats[$yearKey]['nonrecurringpacks'][$month][$currID] = 1;
					$stats[$yearKey]['nonrecurringpacks']['total'][$currID] = 1;
				}
			}
}
		}
	}
	
	if(mattOnlyTEST()) {
		$visitResult = collectVisits($start, $days, $lastYear, $returnHandle=true, $canceledAlso=true);
		//foreach($visits as $appt) {
		while($appt = leashtime_next_assoc($visitResult)) {
			$month = date('Y-m-01', strtotime($appt['date']));
			if($appt['recurringpackage']) $stats[$yearKey]['recurring'][$month][$appt['clientptr']] = 1;
			else $stats[$yearKey]['nonrecurring'][$month][$appt['clientptr']] = 1;
		}
	}
	
	foreach((array)($stats[$yearKey]['recurring']) as $month => $clients)
		foreach($clients as $clientptr => $flag)
			if($flag) $stats[$yearKey]['recurring']['total'][$clientptr] = 1;
	foreach((array)($stats[$yearKey]['nonrecurring']) as $month => $clients)
		foreach($clients as $clientptr => $flag)
			if($flag) $stats[$yearKey]['nonrecurring']['total'][$clientptr] = 1;
	
	$surcharges = collectSurcharges($start, $days, $lastYear);
	foreach($surcharges as $surch) {
		$charge = $surch['charge'];
		$rate = $surch['rate'];
		$stats[$yearKey]['surcharges'] += 1;
		$stats[$yearKey]['surchargescharge'] += $charge;
		$stats[$yearKey]['surchargesrate'] += $rate;
		$stats[$yearKey]['charge'] += $charge;
		$stats[$yearKey]['rate'] += $rate;
		$stats[$yearKey]['sitterrev'][$appt['providerptr']] += $charge;
		$stats[$yearKey]['sitterpay'][$appt['providerptr']] += $rate;
		$stats[$yearKey]['zipsrev'][$clientZIPs[$surch['clientptr']]] += $charge;
		$stats[$yearKey]['zipspay'][$clientZIPs[$surch['clientptr']]] += $rate;
	}
	$miscCharges = collectMiscCharges($start, $days, $lastYear);
	foreach($miscCharges as $misc) {
		$charge = $misc['amount'];
		$stats[$yearKey]['misccharges'] += 1;
		$stats[$yearKey]['misccargescharge'] += $charge;
		$stats[$yearKey]['charge'] += $charge;
		$stats[$yearKey]['zipsrev'][$clientZIPs[$misc['clientptr']]] += $charge;
	}
	$miscCharges = collectMonthlyCharges($start, $days, $lastYear);
	foreach($miscCharges as $month) {
		$charge = $month['charge']-$month['tax'];
		$stats[$yearKey]['monthlycharges'] += 1;
		$stats[$yearKey]['monthlychargescharge'] += $charge;
		$stats[$yearKey]['charge'] += $charge;
		$stats[$yearKey]['zipsrev'][$clientZIPs[$month['clientptr']]] += $charge;
	}
}

function collectVisits($start, $days=null, $lastYear=false, $returnHandle=false, $canceledAlso=false) {
	list($start, $end) = bracketDates($start, $days, $lastYear);
	$canceledAlso = $canceledAlso ? '1=1' : 'canceled IS NULL';
	$sql =
		"SELECT tblappointment.*, 
						CONCAT_WS(' ',tblclient.fname, tblclient.lname) as client,
						tblclient.zip as zip,
						CONCAT_WS(' ',tblprovider.fname, tblprovider.lname) as provider,
						label as service
				FROM tblappointment 
					LEFT JOIN tblclient ON clientid = tblappointment.clientptr
					LEFT JOIN tblprovider ON providerid = providerptr
					LEFT JOIN tblservicetype ON servicetypeid = servicecode
				WHERE $canceledAlso AND date >= '$start' AND date < '$end'
				ORDER BY date";
	if($returnHandle) return doQuery($sql);
	return fetchAssociations($sql);
}

function collectSurcharges($start, $days=null, $lastYear=false, $returnHandle=false) {
	list($start, $end) = bracketDates($start, $days, $lastYear);
	$sql =
		"SELECT c.*, label
				FROM tblsurcharge c
					LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
				WHERE canceled IS NULL AND c.date >= '$start' AND c.date < '$end'
				ORDER BY c.date";
	if($returnHandle) return doQuery($sql);
	return fetchAssociations($sql);
}

function collectMiscCharges($start, $days=null, $lastYear=false, $returnHandle=false) {
	list($start, $end) = bracketDates($start, $days, $lastYear);
	$sql = 
		"SELECT *
				FROM tblothercharge
				WHERE issuedate >= '$start' AND issuedate < '$end'
				ORDER BY issuedate";
	if($returnHandle) return doQuery($sql);
	return fetchAssociations($sql);
}

function collectMonthlyCharges($start, $days=null, $lastYear=false, $returnHandle=false) {
	list($start, $end) = bracketDates($start, $days, $lastYear);
	
	$sql = 
		"SELECT *
				FROM tblbillable
				WHERE itemdate >= '$start' AND itemdate < '$end' AND monthyear IS NOT NULL AND superseded = 0
				ORDER BY itemdate";
	if($returnHandle) return doQuery($sql);
	return fetchAssociations($sql);
}



function presets() {
	$date = time();
	$firstVisit = strtotime(firstVisitDate());
	while($date > $firstVisit) {
		$quarterMonthYear = quarterMonthYearForDate($date);
		if($quarterMonthYear[1] != $lastQM[1]) {
//echo "qm: ".print_r($quarterMonthYear[1], 1)."==  ls: $lastMonth<br>";
			$presets[$quarterMonthYear[2]]['months'][] = $quarterMonthYear[1];
		}
		if($quarterMonthYear[0] != $lastQM[0]) {
			$presets[$quarterMonthYear[2]]['quarters'][] = $quarterMonthYear[0];
		}
		$lastQM = $quarterMonthYear;
		$date = strtotime("- 1 day", $date);
	}
	foreach($presets as $year => $list) 
		foreach($list as $k => $list) 
			$presets[$year][$k] = array_reverse($list);
	
	return $presets;
}

function presetsArray($presets) {
	$halfTEST = TRUE || dbTEST('bestinshowpetsitting') || staffOnlyTEST();
	echo "<style>.choicetable {font-size:1.2em;} .choicetable td {padding:5px;}</style>";
	echo "<table class='choicetable' style='display:none;' border=1 bordercolor=lightgrey>";
	$allMonths = explode(',', 'Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec');
	if($halfTEST) $allMonths = array_merge(array('x'), $allMonths);

	foreach($presets as $year => $list) {
		$yearLink = 
			TRUE || mattOnlyTEST() ?  fauxLink("$year", "genIntervalReport(\"Y$year\")", 1, "Show results based on $year", 3)
			: $year; 
		echo "<tr style='border-top: double'><td style='color:gray' rowspan=2>$yearLink</td>";
		$link = fauxLink("First Half", "genIntervalReport(\"{$year}H1\")", 1, "Show results based on the first half of $year.", 3);
		if($halfTEST) echo "<td>$link</td>";
		$quarters = array_pad(array(), 4, '<td>&nbsp;</td>');
		for($i=1; $i<=4;$i++)
			if(in_array("Q$i", $list['quarters'])) {
				$link = fauxLink("Q$i", "genIntervalReport(\"{$year}Q$i\")", 1, "Show results based on $year Q$i", 3);
				$quarters[$i] = "<td align=center colspan=3>$link</td>";
			}
		unset($quarters[0]);		
		foreach($quarters as $q) echo $q;
		echo "</tr>";
		echo "<tr>";
		$monthsrow = array_pad(array(), 12, '<td>&nbsp;</td>');
		if($halfTEST) {
			$monthsrow = array_reverse($monthsrow);
			$link = fauxLink("Second Half", "genIntervalReport(\"{$year}H2\")", 1, "Show results based on the second half of $year.", 3);
			array_push($monthsrow, "<td>$link</td>");
			$monthsrow = array_reverse($monthsrow);
			$zoop = array_merge($monthsrow);
		}
		foreach($allMonths as $i => $month) {
			if($i==0 && $halfTEST) continue;
			if(in_array($month, $list['months'])) {
				$link = fauxLink($month, "genIntervalReport(\"$year-$month\")", 1, "Show results based on $month $year", 3);
				$monthsrow[$i] = "<td align=center>$link</td>";
			}
		}
		foreach($monthsrow as $m) echo $m;
		echo "</tr>";
		
	}
		echo "</table>";
//if($halfTEST) print_r($zoop);
}
		
function quarterMonthYearForDate($time) {
	$m = date('m', $time);
	$q = $m < 4 ? 'Q1' : ($m < 7 ? 'Q2' : ($m < 10 ? 'Q3' : 'Q4'));
//echo "m: $m ==> $q<br>";
	return array($q, date('M', $time), date('Y', $time));
}

function firstVisitDate() {
	static $firstVisit;
	if(!$firstVisit) $firstVisit = 
		fetchRow0Col0("SELECT date FROM tblappointment WHERE canceled IS NULL ORDER BY date LIMIT 1");
	return $firstVisit;
}

/*




function activeQuartersToDate() {
	$date = firstVisitDate();
	$q = 

function inQuarter($date) {
	$time = $date ? strtotime($date) : time();
	$year = date('Y', $time);
	$monthyear = date('Y-m', $time);
	return 
*/

