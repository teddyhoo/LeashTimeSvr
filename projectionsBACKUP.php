<? // projections.php
$tempApptTable = '';
$tempSurchargeTable = '';

$tempSurchargeTableSchema = <<<SURTABLE
CREATE TABLE IF NOT EXISTS `tblsurcharge_XXX` (
  `surchargeid` int(10) unsigned NOT NULL auto_increment,
  `packageptr` int(10) unsigned NOT NULL default '0',
  `surchargecode` int(10) unsigned NOT NULL default '0',
  `appointmentptr` int(10) unsigned NOT NULL default '0' COMMENT '0 when non-specific',
  `completed` datetime default NULL,
  `canceled` datetime default NULL,
  `date` date NOT NULL default '0000-00-00',
  `timeofday` varchar(45) default NULL,
  `charge` float(5,2) NOT NULL default '0.00' COMMENT 'includes tax',
  `rate` float(5,2) NOT NULL default '0.00',
  `providerptr` int(10) unsigned NOT NULL default '0',
  `clientptr` int(10) unsigned NOT NULL default '0',
  `custom` tinyint(1) default NULL COMMENT 'Modified since creation',
  `automatic` tinyint(1) default NULL COMMENT 'Generated automatically',
  `starttime` time default '00:00:00',
  `endtime` time default '00:00:00',
  `note` text,
  `created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL,
  PRIMARY KEY  (`surchargeid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1592 ;
SURTABLE;

$tempApptTableSchema = <<<TABLE
CREATE TABLE IF NOT EXISTS `tempappointment_XXX` (
  `appointmentid` int(10) unsigned NOT NULL auto_increment,
  `birthmark` varchar(20) default NULL COMMENT 'timeofday_servicecode',
  `serviceptr` int(10) unsigned NOT NULL default '0',
  `packageptr` int(10) unsigned NOT NULL default '0',
  `recurringpackage` tinyint(1) NOT NULL default '0',
  `completed` datetime default NULL,
  `timeofday` varchar(45) NOT NULL default '0',
  `providerptr` int(10) unsigned NOT NULL default '0',
  `servicecode` int(10) unsigned NOT NULL default '0',
  `pets` varchar(45) NOT NULL default '',
  `charge` float(5,2) NOT NULL default '0.00',
  `adjustment` float(5,2) default NULL,
  `rate` float(5,2) NOT NULL default '0.00',
  `bonus` float(5,2) default NULL,
  `surchargenote` varchar(40) default NULL,
  `date` date NOT NULL default '0000-00-00',
  `clientptr` int(10) unsigned NOT NULL default '0',
  `canceled` datetime default NULL,
  `custom` tinyint(1) default NULL COMMENT 'Modified since creation',
  `starttime` time NOT NULL default '00:00:00',
  `endtime` time NOT NULL default '00:00:00',
  `highpriority` tinyint(1) default NULL,
  `note` text,
  `cancellationreason` varchar(100) default NULL,
  `pendingchange` int(11) default NULL COMMENT 'Negative for cancel.  (abs) = requestid',
  `created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL,
  PRIMARY KEY  (`appointmentid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
TABLE;



function fetchOneDayRevenues($date) {
	$date = date('Y-m-d', strtotime($date));
	$appts = fetchCol0("SELECT appointmentid FROM tblappointment WHERE canceled IS NULL AND date = '$date'");
	if($appts) $rev = fetchRow0Col0("SELECT sum(charge+ifnull(adjustment, 0))
												FROM tblappointment
												WHERE appointmentid IN(".join(',', $appts).")");
	$rev += fetchRow0Col0("SELECT sum(amount)
												FROM tblothercharge
												WHERE issuedate = '$date'");
	$rev -= fetchRow0Col0("SELECT sum(amount)
												FROM tblcredit
												WHERE issuedate = '$date'");
	if($appts) $rev -= fetchRow0Col0("SELECT sum(amount)
												FROM relapptdiscount
												WHERE appointmentptr IN(".join(',', $appts).")");
	return array('visits'=>count($appts), 'revenue'=>$rev);
}

function fetchOneMonthRevenues($date, $lastDay=null) {
	$start = date('Y-m-1', strtotime($date));
	$end = $lastDay ? $lastDay : date('Y-m-t', strtotime($date));
	if(strtotime($end) >= strtotime(date('Y-m-d'))) $end = date('Y-m-d');
	return fetchRevenuesInRange($start, $end);
}

function fetchRevenuesInRange($start, $end) {
	$appts = fetchCol0("SELECT appointmentid FROM tblappointment WHERE canceled IS NULL AND date >= '$start' AND date <= '$end'");
	if($appts) $rev = fetchRow0Col0("SELECT sum(charge+ifnull(adjustment, 0))
												FROM tblappointment
												WHERE appointmentid IN(".join(',', $appts).")");
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog("Visit Rev: $rev"); }
	$charges = fetchRow0Col0("SELECT sum(amount)
												FROM tblothercharge
												WHERE issuedate >= '$start' AND issuedate <= '$end'");
	$rev += $charges;
	//$excludeSystemCredits = "AND reason NOT LIKE '%created. (v:%'";
	$creds = fetchRow0Col0("SELECT sum(amount)
												FROM tblcredit
												WHERE payment != 1 AND issuedate >= '$start' AND issuedate <= '$end' $excludeSystemCredits");
	$rev -= $creds;
	if($appts) $discounts = fetchRow0Col0("SELECT sum(amount)
												FROM relapptdiscount
												WHERE appointmentptr IN(".join(',', $appts).")");
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { 
//	screenLog("Visit Discounts: $discounts"); 
//	screenLog("Credits: $creds"); 
//	screenLog("Charges: $charges"); 
//}
	$rev -= $discounts;
	return array('visits'=>count($appts), 'revenue'=>$rev, 'start'=>$start, 'end'=>$end);
}

function dumpRevenuesDrillDownCSVInRange($start, $end) {
	echo "From: $start to $end\n\n";
	echo "Type,ID,Date,Client,Amount,Charge,Adjustment,Discount\n";
	$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");
	$appts = fetchAssociationsKeyedBy("SELECT appointmentid, charge, adjustment, date, tblappointment.clientptr, amount as discount
												FROM tblappointment
												LEFT JOIN relapptdiscount ON appointmentptr = appointmentid
												WHERE canceled IS NULL AND date >= '$start' AND date <= '$end'
												ORDER BY date, tblappointment.clientptr", 'appointmentid');
	foreach($appts as $appt) {
		$discount = $appt['discount'] ? 0 - $appt['discount'] : '';
		echo "VISIT,{$appt['appointmentid']},{$appt['date']},\"{$clients[$appt['clientptr']]}\","
					.($appt['charge']+$appt['adjustment']+$discount)
					.",{$appt['charge']},{$appt['adjustment']},$discount\n";
	}
	
	$charges = fetchAssociationsKeyedBy("SELECT chargeid, amount, clientptr, issuedate as date
												FROM tblothercharge
												WHERE issuedate >= '$start' AND issuedate <= '$end'
												ORDER BY date, clientptr", 'chargeid');
	foreach($charges as $chg) 
		echo "CHARGE,{$chg['chargeid']},{$chg['date']},\"{$clients[$chg['clientptr']]}\",{$chg['amount']}\n";
	
	//$excludeSystemCredits = "AND reason NOT LIKE '%created. (v:%'";
	$credits = fetchAssociationsKeyedBy("SELECT creditid, amount, clientptr, issuedate as date
												FROM tblcredit
												WHERE payment != 1 AND issuedate >= '$start' AND issuedate <= '$end' $excludeSystemCredits
												ORDER BY date, clientptr", 'creditid');
	foreach($credits as $cred) {
		$date = date('Y-m-d', strtotime($cred['date']));
		echo "CREDIT,{$cred['creditid']},$date,\"{$clients[$cred['clientptr']]}\",-{$cred['amount']}\n";
	}
	
	echo "\n\nSummary: ".count($appts).' visits. '.count($charges).' charges. '.count($credits).' credits.';
}

function createProjectionApptTables() {
	global $tempApptTable, $tempApptTableSchema, $tempSurchargeTable, $tempSurchargeTableSchema;
	if($tempApptTable) return;
	$done = false;
	while (!$done) {
		$time = time();
		$schema = str_replace('XXX', $time, $tempApptTableSchema);
		$done = doQuery($schema);
	}
	$tempApptTable = "tempappointment_$time";
	$done = false;
	while (!$done) {
		$time = time();
		$schema = str_replace('XXX', $time, $tempSurchargeTableSchema);
		$done = doQuery($schema);
	}
	$tempSurchargeTable = "tempsurcharge_$time";
}

function dropProjectionApptTable() {
	global $tempApptTable;
	$table = $tempApptTable;
	$tempApptTable = null;
	if($table) return doQuery("DROP TABLE IF EXISTS $table");
}

function dropProjectionSurchTable() {
	global $tempSurchargeTable;
	$table = $tempSurchargeTable;
	$tempSurchargeTable = null;
	if($table) return doQuery("DROP TABLE IF EXISTS $table");
}

function rolloverProjections($lastDay) {
	global $biz, $tempApptTable; // set in cron-recurring-schedule-rollover.php
	createProjectionApptTables();
	$bizdb = $biz ? $biz['db'] : 'unspecified database';
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
	$schedules = fetchAssociations(tzAdjustedSql("SELECT * FROM tblrecurringpackage WHERE current = 1 
																			AND (cancellationdate IS NULL || cancellationdate > CURDATE())
																			AND startdate <= CURDATE()"));
	$histories = findPackageHistories(null, 'R');
	$window = $prefs['recurringScheduleWindow'] + 1;
	$firstDay = date('Y-m-d', strtotime("+ $window days"));
	$lastDay = date('Y-m-d', strtotime($lastDay));
	$today = date('Y-m-d');
	$createdAppts = 0;
	
	foreach($schedules as $schedule) {
		$packageid = $schedule['packageid'];
		$ids = isset($histories[$packageid]) ? join(',',$histories[$packageid]) : $packageid;
		// Collect all appointments for all versions of schedule for the next N days.  Include canceled appointments.
		
		$sql = "SELECT birthmark, date, timeofday, $tempApptTable.clientptr, servicecode FROM $tempApptTable 
							WHERE date >= '$firstDay' AND date <= '$lastDay' AND packageptr IN ($ids)";
		if($appts = fetchAssociations($sql))
			$apptSignatures = array_map('getAppointmentSignature', $appts);
//if($schedule['packageid'] == 430) {print_r($apptSignatures);exit;}		
		/*$existingAppointmentInterval[] = 
			fetchRow0Col0("SELECT date FROM tblappointment WHERE  packageptr IN ($ids) ORDER BY date ASC LIMIT 1");
		$existingAppointmentInterval[] = 
			fetchRow0Col0("SELECT date FROM tblappointment WHERE  packageptr IN ($ids) ORDER BY date DESC LIMIT 1");*/
		$createdAppts += count(
				createScheduleAppointments($schedule, getPackageServices($packageid), true, 
																		$existingAppointmentInterval, null, null, $apptSignatures)
			);
	}		
	logChange(0, 'tblrecurringpackage', 'c', "ROLLOVER FINISHED: $createdAppts created in $bizdb.");
	return $createdAppts;
}

function refunds($start, $end, $byZips=null, $byCities=null) {
	global $refunds, $revenues, $actualRevenues, $totalRevenue, $totalActualRevenue, $zips, $cityStates;
	$clientFilter = $clients ? "AND clientptr IN ".join(',',$clients) : '';
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$fields = "amount, issuedate as date";
	if($byZips || $byCities) $fields .= ",clientptr";
	$sql = "SELECT $fields
					FROM tblrefund
					WHERE 
						issuedate >= '$start' AND issuedate <= '$end' 
						$clientFilter";
  $refunds = array();
  if($result = doQuery($sql)) {
		while($refund = mysql_fetch_assoc($result)) {
		 $monthYear = substr($refund['date'], 0, 7);
		 if($byZips) $refunds[$monthYear][$zips[$refund['clientptr']]] = $refund['amount'];
		 if($byCities) $refunds[$monthYear][$cityStates[$refund['clientptr']]] = $refund['amount'];
		 else $refunds[$monthYear][] = $refund['amount'];
		 $totalActualRevenue -= $refund['amount'];
		 $totalRevenue -= $refund['amount'];
if(FALSE && mattOnlyTEST()) {
		//print_r($actualRevenues);
		$revenues[$monthYear]['*refunds*'] = 0-$refund['amount'];
		$actualRevenues[$monthYear]['*refunds*'] = 0-$refund['amount'];
}
	 }
 }
}
	
function refundsForClients($start, $end) {
	global $refunds, $revenues, $actualRevenues, $totalRevenue, $totalActualRevenue, $zips;
	$clientFilter = $clients ? "AND clientptr IN ".join(',',$clients) : '';
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$fields = "amount, issuedate as date, clientptr";
	$sql = "SELECT $fields
					FROM tblrefund
					WHERE 
						issuedate >= '$start' AND issuedate <= '$end' 
						$clientFilter";
  $refunds = array();
  if($result = doQuery($sql)) {
		while($refund = mysql_fetch_assoc($result)) {
		 $monthYear = substr($refund['date'], 0, 7);
		 $refunds[$monthYear][$refund['clientptr']] = $refund['amount'];
		 $totalActualRevenue -= $refund['amount'];
		 $totalRevenue -= $refund['amount'];
	 }
 }
}
	

function revenuesAndCommissions($start, $end) {
	global $tempApptTable, $projectionStartTime, $projectionEndTime, $fixedPriceMonthlyLabel, $includeRevenueFromSurcharges;
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
	$window = $prefs['recurringScheduleWindow'] + 1;
	$firstProjectionDay = date('Y-m-d', strtotime("+ $window days"));
	//echo "firstProjectionDay: $firstProjectionDay<p>";
	if($end >= $firstProjectionDay) {
		$projectionStartTime = strtotime($firstProjectionDay);
		$projectionEndTime = strtotime("$end 11:59:59");
//if(mattOnlyTEST()) echo "ABOUT TO rolloverProjections.<p>";
		rolloverProjections($projectionEnd);
	}
	
	$monthlyPackages = join(',', fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly"));
	$ORDERbyDATE = "ORDER BY date";
	// Monthlies
	if($monthlyPackages) {
/*if(FALSE && !staffOnlyTEST()) { // suppress monthlies
		revenueForFixedPriceMonthlies($start, $end);
		$sql = "SELECT appointmentid, date, '$fixedPriceMonthlyLabel' as servicename, rate, bonus 
						FROM tblappointment
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' 
							AND packageptr IN ($monthlyPackages)
						$ORDERbyDATE";
		revenueAndCommissionsForAppts($sql, true);	// omit visit revenues		
		if($end >= $firstProjectionDay) {
			$sql = "SELECT appointmentid, date, '$fixedPriceMonthlyLabel' as servicename, rate, bonus FROM $tempApptTable
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' 
							AND packageptr IN ($monthlyPackages)
						$ORDERbyDATE";
			revenueAndCommissionsForAppts($sql, true);	// omit visit revenues	
		}
}*/
	}
	$sql = "SELECT appointmentid, date, servicecode, tblappointment.charge, adjustment, 
						IF(tblbillable.billableid IS NULL, 
									tblappointment.charge+IFNULL(adjustment,0), 
									tblbillable.charge - IFNULL(tblbillable.tax, 0)) as pretaxcharge,
						rate, bonus, paid, tax as billabletax
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND superseded = 0 AND itemtable = 'tblappointment'
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '')
						." $ORDERbyDATE";
						
//if(mattOnlyTEST()) {$ass = fetchAssociations($sql);echo "<pre>";foreach($ass as $a) if($a['servicecode'] == 52) echo "\n".print_r($a,1);}
	revenueAndCommissionsForAppts($sql, false);			
	if($includeRevenueFromSurcharges) {
		$sql = "SELECT date, surchargecode, tblsurcharge.charge, rate, paid, tax as billabletax 
						FROM tblsurcharge
						LEFT JOIN tblbillable ON itemptr = surchargeid AND superseded = 0 AND itemtable = 'tblsurcharge'
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' $ORDERbyDATE";
		revenueAndCommissionsForSurcharges($sql, false);			
	}
		
	$sql = "SELECT chargeid, amount, tblothercharge.clientptr, issuedate as date, paid, tax as billabletax
					FROM tblothercharge
					LEFT JOIN tblbillable ON itemptr = chargeid AND superseded = 0 AND itemtable = 'tblothercharge'
					WHERE issuedate >= '$start' AND issuedate <= '$end'
					ORDER BY date, tblothercharge.clientptr";
	revenueForMiscCharges($sql);
	if($end >= $firstProjectionDay) {
		$sql = "SELECT date, servicecode, charge, adjustment, rate, bonus FROM $tempApptTable
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '')
						." $ORDERbyDATE";
		revenueAndCommissionsForAppts($sql, false);
	}
	
}

function monthlyRevenuesAndCommissionsByClient($start, $end, $projections=true) {
	global $tempApptTable, $projectionStartTime, $projectionEndTime, $fixedPriceMonthlyLabel, $figureTaxes, $includeRevenueFromSurcharges;
	global $revenues, $actualRevenues, $totalActualRevenue;
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	
	/*$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
				
	if($projections) {
		$window = $prefs['recurringScheduleWindow'] + 1;
		$firstProjectionDay = date('Y-m-d', strtotime("+ $window days"));
		//echo "firstProjectionDay: $firstProjectionDay<p>";
		if($end >= $firstProjectionDay) {
			$projectionStartTime = strtotime($firstProjectionDay);
			$projectionEndTime = strtotime("$end 11:59:59");
			rolloverProjections($projectionEnd);
		}
	}
	$t0 = microtime(1);
	// Monthlies
	estimatePerVisitRevenueForFixedPriceMonthlies();
	$monthlyPackages = join(',', fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly=1"));*/

	/*$sql = "SELECT packageptr, recurringpackage, tblappointment.clientptr, providerptr, date, servicecode, 
					tblappointment.charge, adjustment, rate, bonus, paid, tax as billabletax 
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND superseded = 0 AND itemtable = 'tblappointment'
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');
	$t0 = microtime(1);
	revenueAndCommissionsForApptsByDateClientAndProvider($sql, false, $figureTaxes);			
	$t0 = microtime(1);
	*/
						
				
				
	// find all actual billables
	$sql = "SELECT monthyear as date, itemptr, charge-tax as charge, clientptr
					FROM tblbillable
					WHERE itemtable = 'tblrecurringpackage'
						AND monthyear >= '$start' AND monthyear <= '$end' ";
	foreach(fetchAssociations($sql) as $b) {
		$revenues
			[date('Y-m', strtotime($b['date']))][$b['clientptr']][] = $b['charge'];
		$latestBillables[$b['clientptr']] = date('Y-m', strtotime($b['date']));
	}

if(1) {
	// find all current recurring packages
	$currentPackages = fetchAssociationsKeyedBy("SELECT * FROM tblrecurringpackage WHERE current = 1 AND monthly = 1", 'clientptr');
	// starting with next month, check each current package
	// if the package is active (not canceled before and not suspended)
	// add it to revenue
	$ym = date('Y-m', strtotime("+1 month", strtotime(date('Y-m-01'))));
	$endym = date('Y-m', strtotime($end));
	for(; $ym <= $endym; $ym = date('Y-m', strtotime("+1 month", strtotime("$ym-01")))) {
//print_r("($clientptr) $ym ==> $endym <br>");		
		foreach($currentPackages as $clientptr => $pack) {
			if(!$revenues[$ym][$clientptr]) {
				// no need to check whether client is active
				if($pack['startdate'] && $pack['startdate'] > "$ym-01") continue;
				if($pack['cancellationdate'] && $pack['cancellationdate'] < "$ym-01") continue;
				if($pack['suspenddate'] && $pack['suspenddate'] < "$ym-01" && $pack['resumedate'] > "$ym-01") continue;
//print_r("($clientptr) $ym ==> $endym ".print_r($pack, 1).'<br>');		
				$revenues[$ym][$clientptr] = $pack['totalprice'];
			}
		}
	}
}
//foreach($revenues as $ym => $client) if($client=487) echo "$ym [$client] ";
	convertRevenueAndCommissionsForApptsByClient();

}

function revenuesAndCommissionsByClient($start, $end, $projections=true) {
	global $tempApptTable, $projectionStartTime, $projectionEndTime, $fixedPriceMonthlyLabel, $figureTaxes, $includeRevenueFromSurcharges;
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
				
	if($projections) {
		$window = $prefs['recurringScheduleWindow'] + 1;
		$firstProjectionDay = date('Y-m-d', strtotime("+ $window days"));
		//echo "firstProjectionDay: $firstProjectionDay<p>";
		if($end >= $firstProjectionDay) {
			$projectionStartTime = strtotime($firstProjectionDay);
			$projectionEndTime = strtotime("$end 11:59:59");
			rolloverProjections($projectionEnd);
		}
	}
	$t0 = microtime(1);
	// Monthlies
	if(FALSE && !staffOnlyTEST()) { // suppress monthlies
		estimatePerVisitRevenueForFixedPriceMonthlies();
		screenLog("estimatePerVisitRevenueForFixedPriceMonthlies: ".((microtime(1)-$t0)).' sec');
	}
	$monthlyPackages = join(',', fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly"));

	// allow for discounts by IFNULL(tblbillable.charge, tblappointment.charge)
	/* IF(tblbillable.billableid IS NULL, 
			tblappointment.charge+IFNULL(adjustment,0), 
			tblbillable.charge - IFNULL(tblbillable.tax, 0)) as pretaxcharge
	*/
	$sql = "SELECT packageptr, recurringpackage, tblappointment.clientptr, providerptr, date, servicecode, 
					tblappointment.charge, adjustment, 
					IF(tblbillable.billableid IS NULL, 
								tblappointment.charge+IFNULL(adjustment,0), 
								tblbillable.charge - IFNULL(tblbillable.tax, 0)) as pretaxcharge,
					rate, bonus, paid, tax as billabletax 
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND superseded = 0 AND itemtable = 'tblappointment'
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');
	$t0 = microtime(1);
	revenueAndCommissionsForApptsByDateClientAndProvider($sql, false, $figureTaxes);			
	screenLog("revenueAndCommissionsForApptsByDateClientAndProvider: ".((microtime(1)-$t0)).' sec');

	if($includeRevenueFromSurcharges) {
		$sql = "SELECT date, surchargecode, tblsurcharge.charge, rate, paid, packageptr, tblsurcharge.clientptr, providerptr, tax as billabletax 
						FROM tblsurcharge
						LEFT JOIN tblbillable ON itemptr = surchargeid AND superseded = 0 AND itemtable = 'tblsurcharge'
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' ";
		$t0 = microtime(1);
		revenueAndCommissionsForSurchargesByDateClientAndProvider($sql, false);
		screenLog("revenueAndCommissionsForSurchargesByDateClientAndProvider: ".((microtime(1)-$t0)).' sec');
	}
		
	$sql = "SELECT chargeid, amount, tblothercharge.clientptr, issuedate as date, tax as billabletax, paid
					FROM tblothercharge
					LEFT JOIN tblbillable ON itemptr = chargeid AND superseded = 0 AND itemtable = 'tblothercharge'
					WHERE issuedate >= '$start' AND issuedate <= '$end'
					ORDER BY date, tblothercharge.clientptr";
	$t0 = microtime(1);
	revenueAndCommissionsForMiscChargesByDateAndClient($sql);
	screenLog("revenueAndCommissionsForMiscChargesByDateAndClient: ".((microtime(1)-$t0)).' sec');
		
	
	if($projections && $end >= $firstProjectionDay) {
		$sql = "SELECT packageptr, recurringpackage, clientptr, providerptr, date, servicecode, charge, adjustment, rate, bonus 
					FROM $tempApptTable
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');

		$t0 = microtime(1);
		revenueAndCommissionsForApptsByDateClientAndProvider($sql, false, $figureTaxes);
		screenLog("revenueAndCommissionsForApptsByDateClientAndProvider: ".((microtime(1)-$t0)).' sec');
	}
	$t0 = microtime(1);
	convertRevenueAndCommissionsForApptsByClient();
	screenLog("convertRevenueAndCommissionsForApptsByClient: ".((microtime(1)-$t0)).' sec');
}

function revenuesAndCommissionsByReferralCat($start, $end) {
	global $tempApptTable, $projectionStartTime, $projectionEndTime, $fixedPriceMonthlyLabel, $figureTaxes, $includeRevenueFromSurcharges;
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
	$window = $prefs['recurringScheduleWindow'] + 1;
	$firstProjectionDay = date('Y-m-d', strtotime("+ $window days"));
	//echo "firstProjectionDay: $firstProjectionDay<p>";
	if($end >= $firstProjectionDay) {
		$projectionStartTime = strtotime($firstProjectionDay);
		$projectionEndTime = strtotime("$end 11:59:59");
		rolloverProjections($projectionEnd);
	}
	$monthlyPackages = join(',', fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly"));
	// Monthlies
	
if(FALSE && !staffOnlyTEST()) estimatePerVisitRevenueForFixedPriceMonthlies(); // suppress monthlies

	$sql = "SELECT packageptr, recurringpackage, tblappointment.clientptr, providerptr, date, servicecode, 
					tblappointment.charge, adjustment, 
					IF(tblbillable.billableid IS NULL, 
								tblappointment.charge+IFNULL(adjustment,0), 
								tblbillable.charge - IFNULL(tblbillable.tax, 0)) as pretaxcharge,
					rate, bonus, paid, tax as billabletax 
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND superseded = 0 AND itemtable = 'tblappointment'
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');
	revenueAndCommissionsForApptsByDateClientAndProvider($sql, false);			
	
	if($includeRevenueFromSurcharges) {
		$sql = "SELECT date, surchargecode, tblsurcharge.charge, rate, paid, packageptr, tblsurcharge.clientptr, providerptr, tax as billabletax 
						FROM tblsurcharge
						LEFT JOIN tblbillable ON itemptr = surchargeid AND superseded = 0 AND itemtable = 'tblsurcharge'
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' ";
		revenueAndCommissionsForSurchargesByDateClientAndProvider($sql, false);
	}
		
	$sql = "SELECT chargeid, amount, tblothercharge.clientptr, issuedate as date, tax as billabletax, paid
					FROM tblothercharge
					LEFT JOIN tblbillable ON itemptr = chargeid AND superseded = 0 AND itemtable = 'tblothercharge'
					WHERE issuedate >= '$start' AND issuedate <= '$end'
					ORDER BY date, tblothercharge.clientptr";
	revenueAndCommissionsForMiscChargesByDateAndClient($sql);
		

	if($end >= $firstProjectionDay) {
		$sql = "SELECT packageptr, recurringpackage, clientptr, providerptr, date, servicecode, charge, adjustment, rate, bonus 
					FROM $tempApptTable
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');
		revenueAndCommissionsForApptsByDateClientAndProvider($sql, false, $figureTaxes);
	}
	
	convertRevenueAndCommissionsForApptsByReferral();
}

//ZIP tables
function revenuesAndCommissionsByZIP($start, $end) {
	global $tempApptTable, $projectionStartTime, $projectionEndTime, $fixedPriceMonthlyLabel, $includeRevenueFromSurcharges;
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
	$window = $prefs['recurringScheduleWindow'] + 1;
	$firstProjectionDay = date('Y-m-d', strtotime("+ $window days"));
	//echo "firstProjectionDay: $firstProjectionDay<p>";
	if($end >= $firstProjectionDay) {
		$projectionStartTime = strtotime($firstProjectionDay);
		$projectionEndTime = strtotime("$end 11:59:59");
		rolloverProjections($projectionEnd);
	}
	
	$monthlyPackages = join(',', fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly"));
	// Monthlies
	if($monthlyPackages) {
if(FALSE && !staffOnlyTEST()) { // suppress monthlies
		revenueForFixedPriceMonthlies_ZIP($start, $end);
		$sql = "SELECT date, tblappointment.clientptr, rate, bonus 
						FROM tblappointment
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' 
							AND packageptr IN ($monthlyPackages)";
		revenueAndCommissionsForAppts_ZIP($sql, true);	// omit visit revenues	

		if($end >= $firstProjectionDay) {
			$sql = "SELECT date, $tempApptTable.clientptr, rate, bonus FROM $tempApptTable
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' 
							AND packageptr IN ($monthlyPackages)";
			revenueAndCommissionsForAppts_ZIP($sql, true);	// omit visit revenues	
		}
}
	}
	$sql = "SELECT date, tblappointment.clientptr, tblappointment.charge, adjustment, 
					IF(tblbillable.billableid IS NULL, 
								tblappointment.charge+IFNULL(adjustment,0), 
								tblbillable.charge - IFNULL(tblbillable.tax, 0)) as pretaxcharge,
					rate, bonus, paid, tax as billabletax 
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND superseded = 0 AND itemtable = 'tblappointment'
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');
	revenueAndCommissionsForAppts_ZIP($sql, false);			
	
if(TRUE || staffOnlyTEST()) { 
	if($includeRevenueFromSurcharges) {
		$sql = "SELECT date, surchargecode, tblsurcharge.charge, rate, paid, packageptr, tblsurcharge.clientptr, providerptr, tax as billabletax 
						FROM tblsurcharge
						LEFT JOIN tblbillable ON itemptr = surchargeid AND superseded = 0 AND itemtable = 'tblsurcharge'
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' ";
		revenueAndCommissionsForAppts_ZIP($sql, false);  // this works for surcharges as well
	}
		
	$sql = "SELECT chargeid, amount as charge, tblothercharge.clientptr, issuedate as date, tax as billabletax, paid
					FROM tblothercharge
					LEFT JOIN tblbillable ON itemptr = chargeid AND superseded = 0 AND itemtable = 'tblothercharge'
					WHERE issuedate >= '$start' AND issuedate <= '$end'
					ORDER BY date, tblothercharge.clientptr";
	revenueAndCommissionsForAppts_ZIP($sql, false);
		
}
	
	if($end >= $firstProjectionDay) {
		$sql = "SELECT date, $tempApptTable.clientptr, charge, adjustment, rate, bonus FROM $tempApptTable
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');
		revenueAndCommissionsForAppts_ZIP($sql, false);
	}
}

function getClientZips() {
	return fetchKeyValuePairs("SELECT clientid, zip FROM tblclient");
}

function revenueAndCommissionsForAppts_ZIP($sql, $omitRevenues) {
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $zips;
	$zips = $zips ? $zips : getClientZips();
//echo $sql.'<p>'.print_r(fetchAssociations($sql), 1)	;
	$result = doQuery($sql);
  while($appt = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$zip = $zips[$appt['clientptr']];
		$totalCommission += ($comm = $appt['rate']+$appt['bonus']);
		$commissions[substr($appt['date'], 0, 7)][$zip] += $comm;
		if(!$omitRevenues) {
			$rev = $appt['pretaxcharge'];// $appt['charge']+$appt['adjustment'];
			$rev -= fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = '{$appt['appointmentid']}' LIMIT 1");
			$totalRevenue += $rev;
			$untaxedPaidRev = max(0, $appt['paid'] - $appt['billabletax']);
			$totalActualRevenue += $untaxedPaidRev;
			$revenues[substr($appt['date'], 0, 7)][$zip] += $rev;
			$actualRevenues[substr($appt['date'], 0, 7)][$zip] += $untaxedPaidRev;
		}
	}
}

$fixedPriceMonthlyLabel = "Fixed-Price Monthly Contract";
$revenues = array();  // $monthYear => array($servicetype => $revenue)
$commissions = array();  // $monthYear => array($servicetype => $commission)

function revenueForFixedPriceMonthlies($start, $end) {
	//Report the whole month revenue even if cancelled part-way through
	//For historical cancellations, I think that's pretty easy
	//For suspend / resume, if it crosses months, then report both months. If it's several months, then leave the full months in between out.
 
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $fixedPriceMonthlyLabel, $visitCounts;
	$start = date('Y-m-01',strtotime($start));
	$end = date('Y-m-t',strtotime($end));
	$packages = fetchAssociations(
								"SELECT totalprice, startdate, suspenddate, resumedate, cancellationdate, clientptr 
											FROM tblrecurringpackage 
											WHERE current = 1 and monthly = 1");
	$thisMonthYear = date('Y-m-01');
	for($date = $start; $date < $end; $date = date('Y-m-01',strtotime("+ 1 month", strtotime($date)))) {
		$monthEnd = date('Y-m-t',strtotime($date));
		foreach($packages as $pck) {
			$pkStartMonth = date('Y-m-01',strtotime($pck['startdate']));
			$pkCancelMonth = $pck['cancellationdate'] ? date('Y-m-01',strtotime($pck['cancellationdate'])) : null;
			
			if($date < $pkStartMonth) {
				//if(mattOnlyTEST()) echo "{$pck['clientptr']} [$pkStartMonth]<br>";
				continue;
			}
			if($pck['suspenddate'] && $date > $pck['suspenddate'] && $date < $pck['resumedate']) {
				//if(mattOnlyTEST()) echo "{$pck['clientptr']} [$pkStartMonth]<br>";
				continue;
			}
			if($pkCancelMonth && $date > $pkCancelMonth) {
				//if(mattOnlyTEST()) echo "{$pck['clientptr']} [$pkCancelMonth]<br>";
				continue;
			}
			if((TRUE || staffOnlyTEST()) && $pkStartMonth < $thisMonthYear) { // KEEP THIS!
				// If a recurring sched went fixedpricemonthly after its start date, consider only months where there were billables for it
				$blblid = fetchRow0Col0(
					"SELECT billableid FROM tblbillable 
						WHERE clientptr = {$pck['clientptr']} AND monthyear = '$date' AND superseded = 0 LIMIT 1");
				if(!$blblid) {
					continue;
				}
			}
			$totalRevenue += ($rev = $pck['totalprice']);
			$revenues[$monthYear = substr($date, 0, 7)][$fixedPriceMonthlyLabel] += $rev;
			$visitCounts[$fixedPriceMonthlyLabel][$monthYear]+=1;

			//$totalActualRevenue += ($actrev = $appt['paid']);
			//$totalCommission += ($comm = $appt['rate']+$appt['bonus']);
		}
	}
	$actualRevs = fetchKeyValuePairs($sql =  // NEED TO EXTRACT TAX HERE
		"SELECT monthyear, sum(paid)-sum(tax) 
			FROM tblbillable 
			WHERE monthyear IS NOT NULL AND superseded = 0 AND monthyear >= '$start' AND monthyear < '$end'
			GROUP BY monthyear");
//if(mattOnlyTEST()) echo $sql;			
	foreach($actualRevs as $monthyear => $actrev) {
		$actrev = max(0, $actrev);
		$totalActualRevenue += $actrev;
		$actualRevenues[substr($monthyear, 0, 7)][$fixedPriceMonthlyLabel] += $actrev;
	}
}

function estimatePerVisitRevenueForFixedPriceMonthlies() {
	//For each monthly, get the package price, estimate the number of visits per month and calc the average rev per visit
	global $monthlyPerVisitEstimates;
	static $dowCounts = array(
											'Every Day'=>7,
											'Weekends'=>2,
											'Weekdays'=>5);
	$weeksPerMonth = 4.4;
	
	$start = date('Y-m-1',strtotime($start));
	$end = date('Y-m-t',strtotime($end));
	$packages = fetchAssociations(
								"SELECT packageid, totalprice, startdate, suspenddate, resumedate, cancellationdate 
											FROM tblrecurringpackage 
											WHERE current = 1 and monthly = 1");
	foreach($packages as $pck) {
		$serviceXDays = 0;
		foreach(fetchCol0("SELECT daysofweek from tblservice WHERE packageptr = {$pck['packageid']}") as $dow) {
			$dowCount += $dowCounts[$dow] ? $dowCounts[$dow] : count(explode(',',$dow));
			$serviceXDays += $dowCount * $weeksPerMonth;
		}
		$monthlyPerVisitEstimates[$pck['packageid']] = $serviceXDays ? $pck['totalprice'] / $serviceXDays : 0;
	}	
}

function revenueForFixedPriceMonthlies_ZIP($start, $end) {
	//Report the whole month revenue even if cancelled part-way through
	//For historical cancellations, I think that's pretty easy
	//For suspend / resume, if it crosses months, then report both months. If it's several months, then leave the full months in between out.
 
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $zips;
	$start = date('Y-m-1',strtotime($start));
	$end = date('Y-m-t',strtotime($end));
	$packages = fetchAssociations(
								"SELECT totalprice, startdate, suspenddate, resumedate, cancellationdate 
											FROM tblrecurringpackage 
											WHERE current = 1 and monthly = 1");
	for($date = $start; $date < $end; $date = date('Y-m-1',strtotime("+ 1 month", strtotime($date)))) {
		$monthEnd = date('Y-m-t',strtotime($date));
		foreach($packages as $pck) {
			$pkStartMonth = date('Y-m-1',strtotime($pck['startdate']));
			$pkCancelMonth = $pck['cancellationdate'] ? date('Y-m-1',strtotime($pck['cancellationdate'])) : null;
			
			if($date < $pkStartMonth) continue;
			if($pck['suspenddate'] && $date > $pck['suspenddate'] && $date < $pck['resumedate']) continue;
			if($pkCancelMonth && $date > $pkCancelMonth) continue;
			$totalRevenue += ($rev = $pck['totalprice']);
			$revenues[substr($date, 0, 7)][$fixedPriceMonthlyLabel] += $rev;
			//$totalActualRevenue += ($actrev = $appt['paid']);
			//$totalCommission += ($comm = $appt['rate']+$appt['bonus']);
		}
	}
	$zips = $zips ? $zips : getClientZips();
	$result = doQuery(
		"SELECT monthyear, charge, clientptr, tax as billabletax, paid
			FROM tblbillable 
			WHERE monthyear IS NOT NULL AND superseded = 0 AND monthyear >= '$start' AND monthyear < '$end'", 1);
  while($actrev = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$untaxedPaidRev = max(0, $actrev['paid'] - $actrev['billabletax']);
		$totalActualRevenue += $untaxedPaidRev;
		$actualRevenues[substr($actrev['monthyear'], 0, 7)][$zips[$actrev['clientptr']]] += $untaxedPaidRev;
	}
}

function revenueAndCommissionsForAppts($sql, $omitRevenues) {
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $visitCounts;
	static $serviceNames;
	if(!$serviceNames) $serviceNames = getAllServiceNamesById($refresh=1, $noInactiveLabel=true, $setGlobalVar=false) ;
//echo $sql.'<p>'.print_r(fetchAssociations($sql), 1)	;
	$result = doQuery($sql);
  while($appt = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$serviceName = isset($appt['servicename']) ? $appt['servicename'] : $serviceNames[$appt['servicecode']];
		$totalCommission += ($comm = $appt['rate']+$appt['bonus']);
		$commissions[$monthYear = substr($appt['date'], 0, 7)][$serviceName] += $comm;
		if(!$omitRevenues) {
			$rev = $appt['pretaxcharge']; // $appt['charge']+$appt['adjustment'];
			$rev -= fetchRow0Col0($discsql = // billables are already discounted, so don't include them here
				"SELECT amount 
				 FROM relapptdiscount 
				 LEFT JOIN tblbillable ON itemtable = 'tblappointment' AND itemptr = '{$appt['appointmentid']}' AND superseded = 0
				 WHERE appointmentptr = '{$appt['appointmentid']}' AND billableid IS NULL LIMIT 1"); 
//if(mattOnlyTEST()) echo "$discsql<hr>";				 
			$totalRevenue += $rev;
			$untaxedPaidRev = max(0, $appt['paid'] - $appt['billabletax']);
//if(mattOnlyTEST() && $appt['paid'] < ($appt['charge']+$appt['adjustment']) && $appt['servicecode'] == 52) print_r($appt);
			$totalActualRevenue += $untaxedPaidRev;
			$revenues[substr($appt['date'], 0, 7)][$serviceName] += $rev;
			$actualRevenues[substr($appt['date'], 0, 7)][$serviceName] += $untaxedPaidRev;
			$visitCounts[$serviceName][$monthYear]+=1;

		}
	}
}

function revenueAndCommissionsForSurcharges($sql, $omitRevenues) {
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $visitCounts;
	static $surchargeNames;
	if(!$surchargeNames) $surchargeNames = 
		fetchKeyValuePairs("SELECT surchargetypeid, CONCAT('Surcharge: ', label) FROM tblsurchargetype");
//echo $sql.'<p>'.print_r(fetchAssociations($sql), 1)	;
	$result = doQuery($sql);
  while($surch = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$surchargeName = $surchargeNames[$surch['surchargecode']];
		$totalCommission += ($comm = $surch['rate']);
		$commissions[$monthYear = substr($surch['date'], 0, 7)][$surchargeName] += $comm;
		if(!$omitRevenues) {
			$untaxedPaidRev = max(0, $surch['paid'] - $surch['billabletax']);
			$totalRevenue += ($rev = $surch['charge']);
			$totalActualRevenue += $untaxedPaidRev;
			$revenues[$monthYear][$surchargeName] += $rev;
			$actualRevenues[$monthYear][$surchargeName] += $untaxedPaidRev;
			$visitCounts[$surchargeName][$monthYear]+=1;
		}
	}
}

function revenueAndCommissionsForSurchargesByDateClientAndProvider($sql, $omitRevenues, $includeTaxes=true) {
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $visitCounts;
	static $surchargeNames;
	if(!$surchargeNames) $surchargeNames = 
		fetchKeyValuePairs("SELECT surchargetypeid, CONCAT('Surcharge: ', label) FROM tblsurchargetype");
//echo $sql.'<p>'.print_r(fetchAssociations($sql), 1)	;
	$result = doQuery($sql);
  while($surch = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$surchargeName = $surchargeNames[$surch['surchargecode']];
		$totalCommission += ($comm = $surch['rate']);
		$commissions[$monthYear = substr($surch['date'], 0, 7)][$surchargeName] += $comm;
		if(!$omitRevenues) {
			$client = $surch['clientptr'];
			$provider = $surch['providerptr'];
			$untaxedPaidRev = max(0, $surch['paid'] - $surch['billabletax']);
			$totalRevenue += ($rev = $surch['charge']);
			$totalActualRevenue += $untaxedPaidRev;
			
			$revenues[$monthYear][$surchargeName] += $rev;
			$actualRevenues[$monthYear][$surchargeName] += $untaxedPaidRev;
			$visitCounts[$surchargeName][$monthYear]+=1;
			
			$revenues[$monthYear][$client][$provider] += $rev;
			$actualRevenues[$monthYear][$client][$provider] += $untaxedPaidRev;
			if($includeTaxes)
				$taxes[$monthYear][$client][$provider] += figureTaxForAppointmentInSet($surch, $clientTaxRates);
			
		}
	}
}

function revenueAndCommissionsForMiscChargesByDateAndClient($sql, $includeTaxes=true) {
	global $revenues, $actualRevenues, $totalActualRevenue, $totalRevenue;
//echo $sql.'<p>'.print_r(fetchAssociations($sql), 1)	;
	$result = doQuery($sql);
  while($charge = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$monthYear = substr($charge['issuedate'], 0, 7);
		$client = $charge['clientptr'];
		$untaxedPaidRev = max(0, $charge['paid'] - $charge['billabletax']);
		
		$totalRevenue += ($rev = $charge['amount']);
		$totalActualRevenue += $untaxedPaidRev;

		$revenues[$monthYear][$client] += $rev;
		$actualRevenues[$monthYear][$client] += $untaxedPaidRev;
		if($includeTaxes)
			;//$taxes[$monthYear][$client] += figureTaxForAppointmentInSet($surch, $clientTaxRates);
			
	}
}

function revenueForMiscCharges($sql, $includeTaxes=true) {
	global $revenues, $actualRevenues, $totalActualRevenue, $totalRevenue;
//echo $sql.'<p>'.print_r(fetchAssociations($sql), 1)	;
	$miscChargeLabel = 'Miscellaneous Charges';
	$result = doQuery($sql);
  while($charge = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$monthYear = substr($charge['date'], 0, 7);
		$client = $charge['clientptr'];
		$untaxedPaidRev = max(0, $charge['paid'] - $charge['billabletax']);
		
		$totalRevenue += ($rev = $charge['amount']);
		$totalActualRevenue += $untaxedPaidRev;
if($rev != $actrev) screenLog("{$charge['date']} @{$charge['clientptr']} amt: {$charge['amount']} - paid: {$charge['paid']} = ".($charge['amount'] - $charge['paid']));
		$revenues[$monthYear][$miscChargeLabel] += $rev;
		$actualRevenues[$monthYear][$miscChargeLabel] += $untaxedPaidRev;
		$visitCounts[$miscChargeLabel][$monthYear]+=1;
		if($includeTaxes)
			;//$taxes[$monthYear][$client] += figureTaxForAppointmentInSet($surch, $clientTaxRates);
	}
}

function convertRevenueAndCommissionsForApptsByClient() { //
	global $revenues, $commissions, $taxes;
	
	$reorg_commissions = array();
	$reorg_revenues = array();
	$reorg_taxes = array();

	foreach($commissions as $date => $clients)
		foreach($clients as $client => $providers)
			foreach((array)$providers as $provider => $comm)
				$reorg_commissions[$client] += $comm;
	$commissions = $reorg_commissions;
	
	foreach($revenues as $date => $clients)
		foreach($clients as $client => $providers)
			foreach((array)$providers as $provider => $rev)
				$reorg_revenues[$client] += $rev;
	$revenues = $reorg_revenues;
	
	if($taxes) 
	foreach($taxes as $date => $clients)
		foreach($clients as $client => $providers)
			foreach((array)$providers as $provider => $tax)
				$reorg_taxes[$client] += $tax;
	$taxes = $reorg_taxes;
}
	
function convertRevenueAndCommissionsForApptsByReferral() { //
	global $revenues, $commissions;
	require_once "referral-fns.php";
	$paths = getReferralCategoryPaths(getReferralCategories($_SESSION['preferences']['masterPreferencesKey']));
	$clientReferrals = fetchKeyValuePairs("SELECT clientid, referralcode FROM tblclient");
	global $refNotesAsCategories;
	if($refNotesAsCategories) {
		//print_r($paths);	
		$clientReferralNotes = fetchKeyValuePairs("SELECT clientid, referralnote FROM tblclient");
	}
	$reorg_commissions = array();
	$reorg_revenues = array();

	foreach($commissions as $date => $clients) {
		//echo "[".print_r($clients,1)."]<br>";
		foreach($clients as $client => $providers)
			foreach((array)$providers as $provider => $comm) {
				$refCode = $clientReferrals[$client] ? $clientReferrals[$client] : 0;
				$path = $paths[$refCode] ? $paths[$refCode] : array(0);
				foreach($path as $groupCode) 
					$reorg_commissions[$groupCode] += $comm;
				if($refNotesAsCategories && trim("".$clientReferralNotes[$client])) 
					$reorg_commissions["$groupCode||||{$clientReferralNotes[$client]}"] += $comm;
			}
	}
	$commissions = $reorg_commissions;
	
	foreach($revenues as $date => $clients)
		foreach($clients as $client => $providers)
			foreach((array)$providers as $provider => $rev) {
				$refCode = $clientReferrals[$client];
				$path = $paths[$refCode] ? $paths[$refCode] : array(0);
//if($refCode == 18) echo "$path: ".print_r($path, 1)."<br>";
				foreach($path as $groupCode) {
					$reorg_revenues[$groupCode] += $rev;
				if($refNotesAsCategories && trim("".$clientReferralNotes[$client])) 
					$reorg_revenues["$groupCode||||{$clientReferralNotes[$client]}"] += $rev;
//if($refCode == 18) echo "(18) + $rev = {$reorg_revenues[$groupCode]}<br>";
				}
			}
	$revenues = $reorg_revenues;
	//if(mattOnlyTEST()) print_r($revenues);	
}
	
$clientVisits = array();
$providerVisits = array();	

function revenueAndCommissionsForApptsByDateClientAndProvider($sql, $omitRevenues, $includeTaxes=true) {
	require_once "tax-fns.php";
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $taxes, $actualTaxes, $clientTaxRates,
		$monthlyPerVisitEstimates, $clientVisits, $providerVisits;
	//echo $sql.'<p>'.print_r(fetchAssociations($sql), 1)	;
	$result = doQuery($sql);
	
  while($appt = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$client = $appt['clientptr'];
		$clientVisits[$client] += 1;
		$provider = $appt['providerptr'];
		$providerVisits[$provider] != 1;
		$totalCommission += ($comm = $appt['rate']+$appt['bonus']);
		$commissions[substr($appt['date'], 0, 7)][$client][$provider] += $comm;
		if(!$omitRevenues) {
			$rev = isset($monthlyPerVisitEstimates[$appt['packageptr']]) 
				? $monthlyPerVisitEstimates[$appt['packageptr']] : 
				$appt['pretaxcharge']; //$appt['charge']+$appt['adjustment'];
			$rev -= fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = '{$appt['appointmentid']}' LIMIT 1");
			$totalRevenue += $rev;
			$untaxedPaidRev = max(0, $appt['paid'] - $appt['billabletax']);
			$totalActualRevenue += $untaxedPaidRev;
			$revenues[substr($appt['date'], 0, 7)][$client][$provider] += $rev;
			$actualRevenues[substr($appt['date'], 0, 7)][$client][$provider] += $untaxedPaidRev;
			if($includeTaxes)
				$taxes[substr($appt['date'], 0, 7)][$client][$provider] += figureTaxForAppointmentInSet($appt, $clientTaxRates);
		}
	}
}


// ========================================================================================================
function commissionsAndRevenuesByMonthCSV($start, $end, $subCategory, $addRefundSubcat=true, $emptyLabel=null, $sortSubCategories=false) {
  $rows = commissionsAndRevenuesByMonthData($start, $end, $subCategory, $addRefundSubcat, $emptyLabel, $sortSubCategories);
	$columns = explodePairsLine('month|Month||category|Service Type||actualRev|Actual Revenue||revenue|Projected Revenue||commission|Commission||net|Projected Net');
	dumpCSVRow($columns);
	$month = 'All Months';
	foreach($rows as $i => $row) {
		if($row['#PRIMARY#']) {
			$month = $row['category'];
			$row['category'] = 'All Service Types';
		}
		unset($row['#ROW_EXTRAS#']);
		unset($row['#PRIMARY#']);
		$row =  array_merge(array('month'=>$month), $row);
		dumpCSVRow($row);
	}
}
	
function commissionsAndRevenuesByMonthTable($start, $end, $subCategory, $addRefundSubcat=true, $emptyLabel=null, $sortSubCategories=false, $csv=false) {
//echo "CSV [$csv]";exit;	
	if($csv) return commissionsAndRevenuesByMonthCSV($start, $end, $subCategory, $addRefundSubcat, $emptyLabel, $sortSubCategories);
  $rows = commissionsAndRevenuesByMonthData($start, $end, $subCategory, $addRefundSubcat, $emptyLabel, $sortSubCategories);
	$columns = explodePairsLine('category| ||actualRev|Actual Revenue||revenue|Projected Revenue||commission|Commission||net|Projected Net');
	$colClasses = array('actualRev' => 'dollaramountcell', 'revenue' => 'dollaramountcell', 'commission' => 'dollaramountcell', 'net' => 'dollaramountcell'); 
	$headerClass = array('revenue' => 'dollaramountheader', 'actualRev' => 'dollaramountheader', 'commission' => 'dollaramountheader', 'net' => 'dollaramountheader'); //'dollaramountheader'
	
	foreach($rows as $i => $row) {
		foreach(array('revenue', 'actualRev', 'commission', 'net') as $key) {
			if($row['category'] == 'Refunds' &&
					in_array($key, array('commission', 'net')))
				$row[$key] = '-';
			else $row[$key] = dollarAmount($row[$key]);
		}
		$rows[$i] = $row;
	}	
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
}
	
function commissionsAndRevenuesByMonthData($start, $end, $subCategory, $addRefundSubcat=true, $emptyLabel=null, $sortSubCategories=false) {
	global $refunds, $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $visitCounts;
	$rowCat = "All $subCategory, Date Range: ".shortDate(strtotime($start)).' to '.shortDate(strtotime($end));
	$row = array('category'=>$rowCat,
								'actualRev' => $totalActualRevenue, 
								'revenue' => $totalRevenue, 
								'commission' => $totalCommission, 
								'net' => $totalRevenue - $totalCommission);
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
//if(mattOnlyTEST()) { print_r(join('<br>', array_keys($visitCounts)));}
//if(mattOnlyTEST()) {print_r (array_keys($revenues));exit;}
	ksort($revenues);
	foreach($revenues as $monthYear => $revenue) {
		$commission = $commissions[$monthYear];
		$actualRevenue = $actualRevenues[$monthYear];
		if(FALSE && !staffOnlyTEST()) $monthRefunds = $refunds[$monthYear] ? array_sum($refunds[$monthYear]) : 0; // suppress monthlies
		// Month Summary
		$row = array('category' => date('F Y', strtotime("$monthYear-01")));
		$row['actualRev'] = array_sum($actualRevenue) - $monthRefunds;
		$row['revenue'] = array_sum($revenue) - $monthRefunds;
		$row['commission'] = array_sum((array)$commission);
		$row['net'] = array_sum($revenue) - array_sum((array)$commission) - $monthRefunds;
		$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
		$row['#PRIMARY#'] = 1;
		$rows[] = $row;
		
if(FALSE && !staffOnlyTEST()) {	
		if($addRefundSubcat) {  // Refund Line
			$row = array('category' => 'Refunds');
			$row['actualRev'] = $monthRefunds;
			$row['revenue'] = $monthRefunds;
			$rows[] = $row;
		}
}
		// Service Lines
		if($sortSubCategories) ksort($revenue);
		foreach($revenue as $service => $amount) {
			if(FALSE && !staffOnlyTEST()) $refund = $addRefundSubcat ? 0 : ($refunds[$monthYear][$service] ? $refunds[$monthYear][$service] : 0);
			$count = is_array($visitCounts[$service]) ? " (count: ".$visitCounts[$service][$monthYear].")" : '';
			$row = array('category' => trim($service) ? $service.$count : $emptyLabel.$count);
			$row['actualRev'] = $actualRevenue[$service] - $refund;
			$row['revenue'] = $amount - $refund;
			$row['commission'] = $commission[$service];
			$row['net'] = $amount - $commission[$service] - $refund;
			$rows[] = $row;
		}
	}
	return $rows;
}
	
function commissionsAndRevenuesByClientTable($start, $end, $showCommissions=false) {
	$data = commissionsAndRevenuesByClientTableData($start, $end, $showCommissions);
	$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient", 'clientid');
	
	$columns = explodePairsLine('client|Client||services|Services||revenue|Revenue');
	if($showCommissions) $columns['commission'] = 'Commission';
	$colClasses = array('revenue' => 'dollaramountcell', 'commission' => 'dollaramountcell'); 
	$headerClass = array('revenue' => 'dollaramountheader', 'commission' => 'dollaramountheader'); //'dollaramountheader'
	$row = $data['totals'];
	$row['revenue'] = dollarAmount($row['revenue']);;
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	$clientOrder = fetchCol0("SELECT clientid FROM tblclient ORDER BY lname, fname");
	foreach((array)$data['rows'] as $i => $row) {
		$row['revenue'] = dollarAmount($row['revenue']);
		$row['commission'] = dollarAmount($row['commission']);
		$rowClasses[] = 'futuretask';
		$rows[] = $row;
	}
	tableFrom($columns, $rows, 'width=75%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses, $sortClickAction=null);
}

function commissionsAndRevenuesByClientCSVRows($start, $end, $showCommissions=false, $showClientDetails=false) {
	$data = commissionsAndRevenuesByClientTableData($start, $end, $showCommissions);
	$columns = explodePairsLine('client|Client||services|Services||revenue|Revenue');
	if($showCommissions) $columns['commission'] = 'Commission';
	if($showClientDetails) {
		$addedFields = explodePairsLine('petnames|Pets||homephone|Home Phone||cellphone|Cell Phone||workphone|Work Phone||cellphone2|Alt Phone||street1|Address||street2|Address 2||city|City||state|State||zip|ZIP');
		foreach($addedFields as $k=>$v)
			$columns[$k] = $v;
	}
	dumpCSVRow($columns);
	dumpCSVRow($data['totals']);
	if($showClientDetails && $data['rows']) {
		require_once "field-utils.php";
		foreach($data['rows'] as $row) $ids[] = $row['clientid'];
		$details = getClientDetails($ids, $additionalFields=explode(',', 'addressparts,phone,activepets'), $sorted=false);
	}
	$englishList = true;
	foreach($data['rows'] as $row) {
		if($showClientDetails) $row = array_merge($row, $details[$row['clientid']]);
		if($names = $row['pets']) {
			if(!$englishList || count($names) == 1) $row['petnames'] = join(', ', $names);
			else if(count($names) > 1) {
				$lastName = array_pop($names);
				$row['petnames'] = join(', ', $names)." and $lastName";
			}
		}
		dumpCSVRow($row, array_keys($columns));
	}
}

function commissionsAndRevenuesByClientTableData($start, $end, $showCommissions=false) {
	global $refunds, $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $clientVisits;
	//$providerNames = getProviderNames();
	//$providerNames[0] = 'Unassigned';
	$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient", 'clientid');
	
	$totals = array('client'=>'Total',
								'services'=>array_sum($clientVisits),
								'revenue' => $totalRevenue, 
								'commission' => $totalCommission);
	if(!$showCommissions) unset($totals['commission']);
								 
	$data['totals'] = $totals;
	$clientOrder = fetchCol0("SELECT clientid FROM tblclient ORDER BY lname, fname");
	foreach($clientOrder as $client) {
		if(!isset($revenues[$client])) continue;
		$revenue = $revenues[$client];
		// Service Row
		$commission = $commissions[$service];
		$actualRevenue = $actualRevenues[$service];
		$row = array('clientid' =>$client, 'client' =>$clientNames[$client]);
		$row['services'] = $clientVisits[$client];
		$row['revenue'] = $revenue;
		if($showCommissions) $row['commission'] = $commission;
		$data['rows'][] = $row;
	}
	return $data;
}

function dumpCSVRow($row, $cols=null) {
	if(!$row) echo "\n";
	if(is_array($row)) {
		if($cols) {
			$nrow = array();
			if(is_string($cols)) $cols = explode(',', $cols);
			foreach($cols as $k) $nrow[] = $row[$k];
			$row = $nrow;
		}
		echo join(',', array_map('csv',$row))."\n";
	}
	else echo csv($row)."\n";
}


function csv($val) {
	if(mattOnlyTEST() && $val && is_array($val)) $val = print_r($val, 1);
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}

function OLDtaxesByClientTable($start, $end, $showCommissions=false, $csv=false) {
	global $refunds, $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $clientVisits, 
					$taxes, $taxRatesByClient, $baseRate;
	//$providerNames = getProviderNames();
	//$providerNames[0] = 'Unassigned';
	$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient", 'clientid');
	
	$columns = explodePairsLine('client|Client||services|Services||revenue|Revenue||taxrate|Tax Rate||tax|Tax');
	if($showCommissions) $columns['commission'] = 'Commission';
	$colClasses = array('revenue' => 'dollaramountcell', 'commission' => 'dollaramountcell', 'tax' => 'dollaramountcell'); 
	$headerClass = array('revenue' => 'dollaramountheader', 'commission' => 'dollaramountheader'); //'dollaramountheader'
	$row = array('client'=>'Total',
								'revenue' => dollarAmount($totalRevenue), 
								'commission' => dollarAmount($totalCommission),
								 'services'=>array_sum($clientVisits),
								 'tax'=>dollarAmount(array_sum($taxes)));
	$row['category'] = 'All Services, Date Range: '.shortDate(strtotime($start)).' to '.shortDate(strtotime($end));
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	$clientOrder = fetchCol0("SELECT clientid FROM tblclient ORDER BY lname, fname");
	foreach($clientOrder as $client) {
		if(!isset($revenues[$client])) continue;
		// Service Row
		$row = array('client' => $clientNames[$client]);
		$row['revenue'] = dollarAmount($revenues[$client]);
		$row['tax'] = dollarAmount($taxes[$client]);
		$row['taxrate'] = $taxRatesByClient[$client] ? $taxRatesByClient[$client].' %' : $baseRate.' %';
		$row['services'] = $clientVisits[$client];
		$rowClasses[] = 'futuretask';
		//$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
		$rows[] = $row;
	}
	tableFrom($columns, $rows, 'width=75%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses, $sortClickAction=null);
}

function taxesByClientTable($start, $end, $showCommissions=false, $csv=false) {
	if($csv) return taxesByClientCSV($start, $end, $showCommissions);
	$rows = taxesByClientData($start, $end, $showCommissions);
	$columns = explodePairsLine('client|Client||services|Services||revenue|Revenue||taxrate|Tax Rate||tax|Tax');
	if($showCommissions) $columns['commission'] = 'Commission';
	$colClasses = array('revenue' => 'dollaramountcell', 'commission' => 'dollaramountcell', 'tax' => 'dollaramountcell'); 
	$headerClass = array('revenue' => 'dollaramountheader', 'commission' => 'dollaramountheader'); //'dollaramountheader'
	
	foreach($rows as $i => $row) {
		$totalRevenue += $row['revenue'];
		$totalCommission += $row['commission'];
		$clientVisits += $row['services'];
		$taxes += $row['tax'];
	}
	foreach($rows as $i => $row) {
		foreach(array('revenue', 'tax') as $key) {
			$rows[$i][$key] = dollarAmount($row[$key]);
		}
	}
	
	tableFrom($columns, $rows, 'width=75%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses, $sortClickAction=null);
}

function taxesByClientCSV($start, $end, $showCommissions=false) {
	$rows = taxesByClientData($start, $end, $showCommissions);
	$columns = explodePairsLine('client|Client||services|Services||revenue|Revenue||taxrate|Tax Rate||tax|Tax');
	dumpCSVRow($columns);
	foreach($rows as $i => $row) {
		$row['revenue'] = sprintf("%.2f", $row['revenue']);
		$row['tax'] = sprintf("%.2f", $row['tax']);
		$row['taxrate'] = sprintf("%.4f", $row['taxrate']);
		unset($row['#ROW_EXTRAS#']);
		dumpCSVRow($row);
	}
}


function taxesByClientData($start, $end, $showCommissions=false) {
	global $refunds, $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $clientVisits, 
					$taxes, $taxRatesByClient, $baseRate;
	//$providerNames = getProviderNames();
	//$providerNames[0] = 'Unassigned';
	$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient", 'clientid');
	
	$row = array('client'=>'Total',
							 	'services'=>array_sum($clientVisits),
								'revenue' => $totalRevenue, 
							 	'taxrate' => '',
							 	'tax' => array_sum($taxes),
								'commission' => $totalCommission
								 );
  if(!$showCommissions) unset($row['commission']);							 
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	$clientOrder = fetchCol0("SELECT clientid FROM tblclient ORDER BY lname, fname");
	foreach($clientOrder as $client) {
		if(!isset($revenues[$client])) continue;
		// Service Row
		$row = array('client' => $clientNames[$client]);
		$row['services'] = $clientVisits[$client];
		$row['revenue'] = $revenues[$client];
		$row['taxrate'] = $taxRatesByClient[$client] ? $taxRatesByClient[$client].' %' : $baseRate.' %';
		$row['tax'] = $taxes[$client];
		$rows[] = $row;
	}
	return $rows;
}

function commissionsAndRevenuesByReferralCatCSV($start, $end, $showCommissions=false) {
	$rows = commissionsAndRevenuesByReferralCatData($start, $end, $showCommissions);
	$columns = explodePairsLine('client|Referral Category||services|Services||revenue|Revenue||percentage|% of Total Revenue');
	dumpCSVRow($columns);
	foreach($rows as $i => $row) {
		unset($row['#ROW_EXTRAS#']);
		dumpCSVRow($row);
	}
}
function commissionsAndRevenuesByReferralCatTable($start, $end, $showCommissions=false, $csv=false) {
	if($csv) return commissionsAndRevenuesByReferralCatCSV($start, $end, $showCommissions);
	$rows = commissionsAndRevenuesByReferralCatData($start, $end, $showCommissions);
	$columns = explodePairsLine('client|Referral Category||services|Services||revenue|Revenue||percentage|% of Total Revenue');
	if($showCommissions) $columns['commission'] = 'Commission';
	$colClasses = array('revenue' => 'dollaramountcell', 'commission' => 'dollaramountcell'); 
	$headerClass = array('revenue' => 'dollaramountheader', 'commission' => 'dollaramountheader'); //'dollaramountheader'
	foreach($rows as $i => $row) {
		foreach(array('revenue') as $key) {
			if($row[$key] || !$row[$key] == '-')
				$rows[$i][$key] = dollarAmount($row[$key]);
		}
	}
	tableFrom($columns, $rows, 'width=75%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses, $sortClickAction=null);
}

function commissionsAndRevenuesByReferralCatData($start, $end, $showCommissions=false) {
	global $refunds, $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $clientVisits;
	//$providerNames = getProviderNames();
	//$providerNames[0] = 'Unassigned';
	$clientReferrals = fetchKeyValuePairs("SELECT clientid, referralcode FROM tblclient");
	$referralCats = getReferralCategories($_SESSION['preferences']['masterPreferencesKey']);
	$catLabels = getReferralCategoryPathLabels($referralCats);
	global $refNotesAsCategories;
	//if($refNotesAsCategories) echo "<hr>".print_r($catLabels,1);
	if($refNotesAsCategories) foreach($revenues as $key => $val) {
		$keyParts = explode('||||', $key);
		if(count($keyParts) == 1) continue;
		$catLabels[$key] = "{$catLabels[$keyParts[0]]} > {$keyParts[1]}";
	}
	asort($catLabels);
	$paths = getReferralCategoryPaths($referralCats);
	$catVisits = array();
	foreach($clientVisits as $client => $num) {
		$path = $paths[$clientReferrals[$client]] ? $paths[$clientReferrals[$client]] : array(0);
		foreach($path as $stage) {
			$catVisits[$stage] += $num;
		}
	}
	$row = array( 'client' =>'Total',
								 'services'=>array_sum($clientVisits),
								'revenue' => $totalRevenue, 
								'commission' => $totalCommission);
	if(!$showCommissions) unset($row['commission']);
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	$catOrder = array_keys($catLabels);
	foreach($catOrder as $cat) {
		if(!isset($revenues[$cat])) continue;
		$revenue = $revenues[$cat];
		// Service Row
		$actualRevenue = $actualRevenues[$service];
		$row = array('client' => $catLabels[$cat]);
		$row['services'] = $catVisits[$cat];
		$row['revenue'] = $revenue;
		$percentage = round($revenue/$totalRevenue*100);
		if(!$percentage) $percentage = '0.'.round($revenue/$totalRevenue*10000);
		$row['percentage'] = $percentage.'%';
		if($showCommissions) $row['commission'] = $commissions[$service];
		$rowClasses[] = 'futuretask';
		$rows[] = $row;
	}
	return $rows;
}

function commissionsAndRevenuesByServiceTable($start, $end, $csv=false) {
	if($csv) return commissionsAndRevenuesByServiceCSV($start, $end);
	$rows = commissionsAndRevenuesByServiceData($start, $end);
	$columns = explodePairsLine('category| ||actualRev|Actual Revenue||revenue|Projected Revenue||commission|Commission||net|Projected Net');
	$colClasses = array('actualRev' => 'dollaramountcell', 'revenue' => 'dollaramountcell', 'commission' => 'dollaramountcell', 'net' => 'dollaramountcell'); 
	$headerClass = array('revenue' => 'dollaramountheader', 'actualRev' => 'dollaramountheader', 'commission' => 'dollaramountheader', 'net' => 'dollaramountheader'); //'dollaramountheader'
	foreach($rows as $i => $row) {
		foreach(array('actualRev', 'revenue', 'commission', 'net') as $key) {
			if($row[$key] || !$row[$key] == '-')
				$rows[$i][$key] = dollarAmount($row[$key]);
		}
	}
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
}

function commissionsAndRevenuesByServiceCSV($start, $end) {
	$rows = commissionsAndRevenuesByServiceData($start, $end);
	$columns = explodePairsLine('month|Month||category|Service Type||actualRev|Actual Revenue||revenue|Projected Revenue||commission|Commission||net|Projected Net');
	dumpCSVRow($columns);
	$month = 'All Months';
	foreach($rows as $i => $row) {
		if($row['#PRIMARY#']) {
			$month = 'All Months';
			$service = $row['category'];
		}
		else  {
			$month = $row['category'];
			$row['category'] = $service;
		}
		unset($row['#ROW_EXTRAS#']);
		unset($row['#PRIMARY#']);
		$row =  array_merge(array('month'=>$month), $row);
		dumpCSVRow($row);
	}
	
	
}


function commissionsAndRevenuesByServiceData($start, $end) {
	global $refunds, $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $visitCounts;
	$revenues0 = $revenues;
	$commissions0 = $commissions;
	$actualRevenues0 = $actualRevenues;
	$commissions = array();
	$revenues = array();
	$actualRevenues = array();
	foreach($revenues0 as $monthYear => $revenue) {
		foreach($revenue as $service => $amount) {
			$revenues[$service][$monthYear] += $amount;
			$commissions[$service][$monthYear] += $commissions0[$monthYear][$service];
			$actualRevenues[$service][$monthYear] += $actualRevenues0[$monthYear][$service];
		}
	}
	$rowCat = 'All Services, Date Range: '.shortDate(strtotime($start)).' to '.shortDate(strtotime($end));
	$row = array('category' => $rowCat,
								'actualRev' => $totalActualRevenue, 
								'revenue' => $totalRevenue, 
								'commission' => $totalCommission, 
								'net' => $totalRevenue - $totalCommission);
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
if(FALSE && !staffOnlyTEST()) {	
	// Refunds Row
	$totalRefunds = 0;
	if($refunds) foreach($refunds as $amounts) $totalRefunds += array_sum($amounts);
	$row = array('category' => 'Refunds');
	$row['actualRev'] = $totalRefunds;
	$row['revenue'] = $totalRefunds;
	$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
	$row['#PRIMARY#'] = 1;
	$rows[] = $row;
	foreach((array)$refunds as $monthYear => $monthRefunds) {
		// Month Summary
		$row = array('category' => date('F Y', strtotime("$monthYear-01")));
		$row['actualRev'] = $monthRefunds ? "(".dollarAmount(array_sum($monthRefunds)).")" : '-';
		$row['revenue'] = '-';
		$row['commission'] = '-';
		$row['net'] = '-';
		$rows[] = $row;
	}
}
	ksort($revenues);
	foreach($revenues as $service => $revenue) 
		if(strpos($service, 'Surcharge:') !== 0) $tempRevenues[$service] = $revenue;
	foreach($revenues as $service => $revenue) 
		if(strpos($service, 'Surcharge:') === 0) $tempRevenues[$service] = $revenue;
	$revenues = $tempRevenues;
	
	foreach($revenues as $service => $revenue) {
		// Service Row
		$commission = $commissions[$service];
		$actualRevenue = $actualRevenues[$service];
		$row = array('category' => $service.(is_array($visitCounts[$service]) ? " (count: ".array_sum($visitCounts[$service]).")" : ''));
		$row['actualRev'] = array_sum($actualRevenue);
		$row['revenue'] = array_sum($revenue);
		$row['commission'] = array_sum($commission);
		$row['net'] = array_sum($revenue) - array_sum($commission);
		$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
		$row['#PRIMARY#'] = 1;
		$rows[] = $row;
		foreach($revenue as $monthYear => $amount) {
		// Month Row
			$row = array('category' => date('F Y', strtotime("$monthYear-01"))." (count: ".$visitCounts[$service][$monthYear].")");
			$row['revenue'] = $amount;
			$row['actualRev'] = $actualRevenue[$monthYear];
			$row['commission'] = $commission[$monthYear];
			$row['net'] = $amount - $commission[$monthYear];
			$rows[] = $row;
		}
	}
	return $rows;
}

function commissionsAndRevenuesByZIPTable($start, $end, $emptyLabel='No ZIP Code supplied', $csv=false) {
	if($csv) return commissionsAndRevenuesByZIPCSV($start, $end, $emptyLabel);
	$rows = commissionsAndRevenuesByZIPData($start, $end, $emptyLabel);
	$columns = explodePairsLine('category| ||actualRev|Actual Revenue||revenue|Projected Revenue||commission|Commission||net|Projected Net');
	$colClasses = array('revenue' => 'dollaramountcell', 'commission' => 'dollaramountcell', 'net' => 'dollaramountcell'); 
	$headerClass = array('revenue' => 'dollaramountheader', 'actualRev' => 'dollaramountheader', 'commission' => 'dollaramountheader', 'net' => 'dollaramountheader'); //'dollaramountheader'
	foreach($rows as $i => $row) {
		foreach(array('actualRev', 'revenue', 'commission', 'net') as $key) {
			if($row[$key] || !$row[$key] == '-')
				$rows[$i][$key] = dollarAmount($row[$key]);
		}
	}
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
}

function commissionsAndRevenuesByZIPCSV($start, $end, $emptyLabel) {
  $rows = commissionsAndRevenuesByZIPData($start, $end, $emptyLabel);
	$columns = explodePairsLine('month|Month||category|ZIP Code||actualRev|Actual Revenue||revenue|Projected Revenue||commission|Commission||net|Projected Net');
	dumpCSVRow($columns);
	$month = 'All Months';
	foreach($rows as $i => $row) {
		if($row['#PRIMARY#']) {
			$month = 'All Months';
			$service = $row['category'];
		}
		else  {
			$month = $row['category'];
			$row['category'] = $service;
		}
		unset($row['#ROW_EXTRAS#']);
		unset($row['#PRIMARY#']);
		$row =  array_merge(array('month'=>$month), $row);
		dumpCSVRow($row);
	}
}
	


function commissionsAndRevenuesByZIPData($start, $end, $emptyLabel='No ZIP Code supplied') {
	global $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $refunds;
	$revenues0 = $revenues;
	$commissions0 = $commissions;
	$actualRevenues0 = $actualRevenues;
	$commissions = array();
	$revenues = array();
//print_r($actualRevenues);	
	$actualRevenues = array();
	foreach($revenues0 as $monthYear => $revenue) {
		foreach($revenue as $secondaryKey => $amount) {
			$revenues[$secondaryKey][$monthYear] += $amount;
			$commissions[$secondaryKey][$monthYear] += $commissions0[$monthYear][$secondaryKey];
//echo "AR[$secondaryKey][$monthYear]	= AR0[$monthYear][$secondaryKey]<br>";
			$actualRevenues[$secondaryKey][$monthYear] += $actualRevenues0[$monthYear][$secondaryKey];
		}
	}
	$rowCat = 'All ZIP Codes, Date Range: '.shortDate(strtotime($start)).' to '.shortDate(strtotime($end));
	$row = array('category' => $rowCat,
								'actualRev' => $totalActualRevenue, 
								'revenue' => $totalRevenue, 
								'commission' => $totalCommission, 
								'net' => $totalRevenue - $totalCommission);
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	
if(FALSE && !staffOnlyTEST()) {	
	// Refunds Row
	$totalRefunds = 0;
	if($refunds) foreach($refunds as $amounts) $totalRefunds += array_sum($amounts);
	$row = array('category' => 'Refunds');
	$row['actualRev'] = $totalRefunds;
	$row['revenue'] = $totalRefunds;
	$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
	$row['#PRIMARY#'] = 1;
	$rows[] = $row;
	foreach($refunds as $monthYear => $monthRefunds) {
		// Month Summary
		$row = array('category' => date('F Y', strtotime("$monthYear-01")));
		$row['actualRev'] = $monthRefunds ? "(".dollarAmount(array_sum($monthRefunds)).")" : '-';
		$row['revenue'] = '-';
		$row['commission'] = '-';
		$row['net'] = '-';
		$rows[] = $row;
	}
}	
	
	
	ksort($revenues);
//echo "All: ".print_r($actualRevenues,1).'<br>';	
	foreach($revenues as $secondaryKey => $revenue) {
		$commission = $commissions[$secondaryKey];
		$actualRevenue = $actualRevenues[$secondaryKey];
//echo "$secondaryKey: ".print_r($actualRevenues[$secondaryKey],1).'<br>';	
		$refund = 0;
		foreach($refunds as $monthYear => $allZipRefunds)
			$refund += $allZipRefunds[$secondaryKey] ? $allZipRefunds[$secondaryKey] : 0;
		$row = array('category' => trim($secondaryKey) ? $secondaryKey : $emptyLabel);
		$row['actualRev'] = array_sum($actualRevenue) - $refund;
		$row['revenue'] = array_sum($revenue) - $refund;
		$row['commission'] = array_sum($commission) - $refund;
		$row['net'] = array_sum($revenue) - array_sum($commission);
		$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
		$row['#PRIMARY#'] = 1;
		$rows[] = $row;
		foreach($revenue as $monthYear => $amount) {
//echo "secondaryKey[monthYear]: {refund[$monthYear][$secondaryKey]]}<br>";			
			$refund = $refunds[$monthYear][$secondaryKey] ? $refunds[$monthYear][$secondaryKey] : 0;
			$row = array('category' => date('F Y', strtotime("$monthYear-01")));
			$row['actualRev'] = $actualRevenue[$monthYear] - $refund;
			$row['revenue'] = $amount - $refund;
			$row['commission'] = $commission[$monthYear];
			$row['net'] = $amount - $commission[$monthYear] - $refund;
			$rows[] = $row;
		}
	}
	return $rows;
}

//City / State Reporting
function revenuesAndCommissionsByCityState($start, $end) {
	global $tempApptTable, $projectionStartTime, $projectionEndTime, $fixedPriceMonthlyLabel, $includeRevenueFromSurcharges;
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
	$window = $prefs['recurringScheduleWindow'] + 1;
	$firstProjectionDay = date('Y-m-d', strtotime("+ $window days"));
	//echo "firstProjectionDay: $firstProjectionDay<p>";
	if($end >= $firstProjectionDay) {
		$projectionStartTime = strtotime($firstProjectionDay);
		$projectionEndTime = strtotime("$end 11:59:59");
		rolloverProjections($projectionEnd);
	}
	
	$monthlyPackages = join(',', fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE monthly"));
	// Monthlies
	if($monthlyPackages) {
		revenueForFixedPriceMonthlies_CityState($start, $end);
		$sql = "SELECT date, tblappointment.clientptr, rate, bonus 
						FROM tblappointment
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' 
							AND packageptr IN ($monthlyPackages)";
		revenueAndCommissionsForAppts_CityState($sql, true);	// omit visit revenues		

		if($end >= $firstProjectionDay) {
			$sql = "SELECT date, $tempApptTable.clientptr, rate, bonus FROM $tempApptTable
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' 
							AND packageptr IN ($monthlyPackages)";
			revenueAndCommissionsForAppts_CityState($sql, true);	// omit visit revenues	
		}
	}
	$sql = "SELECT date, tblappointment.clientptr, tblappointment.charge, adjustment, 
					IF(tblbillable.billableid IS NULL, 
								tblappointment.charge+IFNULL(adjustment,0), 
								tblbillable.charge - IFNULL(tblbillable.tax, 0)) as pretaxcharge,
					rate, bonus, paid, tax as billabletax 
					FROM tblappointment
					LEFT JOIN tblbillable ON itemptr = appointmentid AND superseded = 0 AND itemtable = 'tblappointment'
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');
	revenueAndCommissionsForAppts_CityState($sql, false);			
	
if(TRUE || staffOnlyTEST()) { 
	if($includeRevenueFromSurcharges) {
		$sql = "SELECT date, surchargecode, tblsurcharge.charge, rate, paid, packageptr, tblsurcharge.clientptr, providerptr, tax as billabletax 
						FROM tblsurcharge
						LEFT JOIN tblbillable ON itemptr = surchargeid AND superseded = 0 AND itemtable = 'tblsurcharge'
						WHERE 
							canceled IS NULL AND
							date >= '$start' AND date <= '$end' ";
		revenueAndCommissionsForAppts_CityState($sql, false);  // this works for surcharges as well
	}
		
	$sql = "SELECT chargeid, amount as charge, tblothercharge.clientptr, issuedate as date, tax as billabletax, paid
					FROM tblothercharge
					LEFT JOIN tblbillable ON itemptr = chargeid AND superseded = 0 AND itemtable = 'tblothercharge'
					WHERE issuedate >= '$start' AND issuedate <= '$end'
					ORDER BY date, tblothercharge.clientptr";
	revenueAndCommissionsForAppts_CityState($sql, false);
		
}
	
	if($end >= $firstProjectionDay) {
		$sql = "SELECT date, $tempApptTable.clientptr, charge, adjustment, rate, bonus FROM $tempApptTable
					WHERE 
						canceled IS NULL AND
						date >= '$start' AND date <= '$end' "
						.($monthlyPackages ? "AND packageptr NOT IN ($monthlyPackages)" : '');
		revenueAndCommissionsForAppts_CityState($sql, false);
	}
	
}

function getClientCityStates() {
	return fetchKeyValuePairs("SELECT clientid, UCASE(CONCAT_WS(', ', city, state)) FROM tblclient");
}

function revenueAndCommissionsForAppts_CityState($sql, $omitRevenues) {
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $cityStates;
	$cityStates = $cityStates ? $cityStates : getClientCityStates();
//print_r($cityStates);	
//echo $sql.'<p>'.print_r(fetchAssociations($sql), 1)	;
	$result = doQuery($sql);
  while($appt = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$cityState = $cityStates[$appt['clientptr']];
		$totalCommission += ($comm = $appt['rate']+$appt['bonus']);
		$commissions[substr($appt['date'], 0, 7)][$cityState] += $comm;
		if(!$omitRevenues) {
			$untaxedPaidRev = max(0, $appt['paid'] - $appt['billabletax']);
			$rev = $appt['pretaxcharge']; //$appt['charge']+$appt['adjustment'];
			$rev -= fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = '{$appt['appointmentid']}' LIMIT 1");
			$totalRevenue += $rev;
			$totalActualRevenue += $untaxedPaidRev;
			$revenues[substr($appt['date'], 0, 7)][$cityState] += $rev;
			$actualRevenues[substr($appt['date'], 0, 7)][$cityState] += $untaxedPaidRev;
		}
	}
}

function revenueForFixedPriceMonthlies_CityState($start, $end) {
	//Report the whole month revenue even if cancelled part-way through
	//For historical cancellations, I think that's pretty easy
	//For suspend / resume, if it crosses months, then report both months. If it's several months, then leave the full months in between out.
 
	global $revenues, $commissions, $actualRevenues, $totalActualRevenue, $totalRevenue, $totalCommission, $cityStates;
	$start = date('Y-m-1',strtotime($start));
	$end = date('Y-m-t',strtotime($end));
	$packages = fetchAssociations(
								"SELECT totalprice, startdate, suspenddate, resumedate, cancellationdate 
											FROM tblrecurringpackage 
											WHERE current = 1 and monthly = 1");
	for($date = $start; $date < $end; $date = date('Y-m-1',strtotime("+ 1 month", strtotime($date)))) {
		$monthEnd = date('Y-m-t',strtotime($date));
		foreach($packages as $pck) {
			$pkStartMonth = date('Y-m-1',strtotime($pck['startdate']));
			$pkCancelMonth = $pck['cancellationdate'] ? date('Y-m-1',strtotime($pck['cancellationdate'])) : null;
			
			if($date < $pkStartMonth) continue;
			if($pck['suspenddate'] && $date > $pck['suspenddate'] && $date < $pck['resumedate']) continue;
			if($pkCancelMonth && $date > $pkCancelMonth) continue;
			$totalRevenue += ($rev = $pck['totalprice']);
			$revenues[substr($date, 0, 7)][$fixedPriceMonthlyLabel] += $rev;
			//$totalActualRevenue += ($actrev = $appt['paid']);
			//$totalCommission += ($comm = $appt['rate']+$appt['bonus']);
		}
	}
	$cityStates = $cityStates ? $cityStates : getClientCityStates();
	$result = doQuery(
		"SELECT monthyear, charge, clientptr, tax as billabletax, paid
			FROM tblbillable 
			WHERE monthyear IS NOT NULL AND superseded = 0 AND monthyear >= '$start' AND monthyear < '$end'", 1);
  while($actrev = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$untaxedPaidRev = max(0, $actrev['paid'] - $actrev['billabletax']);
		$totalActualRevenue += $untaxedPaidRev;
		$actualRevenues[substr($actrev['monthyear'], 0, 7)][$cityStates[$actrev['clientptr']]] += $untaxedPaidRev;
	}
}


function commissionsAndRevenuesByCityStateTable($start, $end, $emptyLabel='No City supplied', $csv=false) {
	if($csv) return commissionsAndRevenuesByCityStateCSV($start, $end, $emptyLabel);
	$rows = commissionsAndRevenuesByCityStateData($start, $end, $emptyLabel);
	$columns = explodePairsLine('category| ||actualRev|Actual Revenue||revenue|Projected Revenue||commission|Commission||net|Projected Net');
	$colClasses = array('revenue' => 'dollaramountcell', 'commission' => 'dollaramountcell', 'net' => 'dollaramountcell'); 
	$headerClass = array('revenue' => 'dollaramountheader', 'actualRev' => 'dollaramountheader', 'commission' => 'dollaramountheader', 'net' => 'dollaramountheader'); //'dollaramountheader'
	foreach($rows as $i => $row) {
		foreach(array('actualRev', 'revenue', 'commission', 'net') as $key) {
			if($row[$key] || !$row[$key] == '-')
				$rows[$i][$key] = dollarAmount($row[$key]);
		}
	}
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
}

function commissionsAndRevenuesByCityStateCSV($start, $end, $emptyLabel) {
  $rows = commissionsAndRevenuesByCityStateData($start, $end, $emptyLabel);
	$columns = explodePairsLine('month|Month||category|City/State||actualRev|Actual Revenue||revenue|Projected Revenue||commission|Commission||net|Projected Net');
	dumpCSVRow($columns);
	$month = 'All Months';
	foreach($rows as $i => $row) {
		if($row['#PRIMARY#']) {
			$month = 'All Months';
			$service = $row['category'];
		}
		else  {
			$month = $row['category'];
			$row['category'] = $service;
		}
		unset($row['#ROW_EXTRAS#']);
		unset($row['#PRIMARY#']);
		$row =  array_merge(array('month'=>$month), $row);
		dumpCSVRow($row);
	}
}
	
function commissionsAndRevenuesByCityStateData($start, $end, $emptyLabel='No City supplied') {
	global $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $refunds;
	$revenues0 = $revenues;
	$commissions0 = $commissions;
	$actualRevenues0 = $actualRevenues;
	$commissions = array();
	$revenues = array();
	$actualRevenues = array();
	foreach($revenues0 as $monthYear => $revenue) {
		foreach($revenue as $secondaryKey => $amount) {
			$revenues[$secondaryKey][$monthYear] += $amount;
			$commissions[$secondaryKey][$monthYear] += $commissions0[$monthYear][$secondaryKey];
//echo "AR[$secondaryKey][$monthYear]	= AR0[$monthYear][$secondaryKey]<br>";
			$actualRevenues[$secondaryKey][$monthYear] += $actualRevenues0[$monthYear][$secondaryKey];
		}
	}
	$rowCat = 'All Cities, Date Range: '.shortDate(strtotime($start)).' to '.shortDate(strtotime($end));
	$row = array('category' => $rowCat,
								'actualRev' => $totalActualRevenue, 
								'revenue' => $totalRevenue, 
								'commission' => $totalCommission, 
								'net' => $totalRevenue - $totalCommission);
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	
	// Refunds Row
	$totalRefunds = 0;
//echo "REFUNDS: ".print_r($refunds,1);	exit;
	if($refunds) foreach($refunds as $amounts) $totalRefunds += array_sum($amounts);
	$row = array('category' => 'Refunds');
	$row['actualRev'] = $totalRefunds;
	$row['revenue'] = $totalRefunds;
	$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
	$row['#PRIMARY#'] = 1;
	$rows[] = $row;
	foreach($refunds as $monthYear => $monthRefunds) {
		// Month Summary
		$row = array('category' => date('F Y', strtotime("$monthYear-01")));
		$row['actualRev'] = $monthRefunds ? "(".dollarAmount(array_sum($monthRefunds)).")" : '-';
		$row['revenue'] = '-';
		$row['commission'] = '-';
		$row['net'] = '-';
		$rows[] = $row;
	}
	ksort($revenues);
//echo "All: ".print_r($actualRevenues,1).'<br>';	
	foreach($revenues as $secondaryKey => $revenue) {
		$commission = $commissions[$secondaryKey];
		$actualRevenue = $actualRevenues[$secondaryKey];
//echo "$secondaryKey: ".print_r($actualRevenues[$secondaryKey],1).'<br>';	
		$refund = 0;
		foreach($refunds as $monthYear => $allCityRefunds)
			$refund += $allCityRefunds[$secondaryKey] ? $allCityRefunds[$secondaryKey] : 0;
		$row = array('category' => trim($secondaryKey) ? $secondaryKey : $emptyLabel);
		$row['actualRev'] = array_sum($actualRevenue) - $refund;
		$row['revenue'] = array_sum($revenue) - $refund;
		$row['commission'] = array_sum($commission) - $refund;
		$row['net'] = array_sum($revenue) - array_sum($commission);
		$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
		$row['#PRIMARY#'] = 1;
		$rows[] = $row;
		foreach($revenue as $monthYear => $amount) {
//echo "secondaryKey[monthYear]: {refund[$monthYear][$secondaryKey]]}<br>";			
			$refund = $refunds[$monthYear][$secondaryKey] ? $refunds[$monthYear][$secondaryKey] : 0;
			$row = array('category' => date('F Y', strtotime("$monthYear-01")));
			$row['actualRev'] = $actualRevenue[$monthYear] - $refund;
			$row['revenue'] = $amount - $refund;
			$row['commission'] = $commission[$monthYear];
			$row['net'] = $amount - $commission[$monthYear] - $refund;
			$rows[] = $row;
		}
	}
	return $rows;
}



/*	
//==============================
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "gui-fns.php";
//createProjectionApptTable();
//echo 'Created '.rolloverProjections('2010-02-04').' projected appointments.';

$start = '9/1/2009';
$end = '3/31/2010';
revenuesAndCommissions($start, $end);  //$revenues, $commissions;
echo "New table: $tempApptTable<p>";

//commissionsAndRevenuesByMonthTable($start, $end);
commissionsAndRevenuesByServiceTable($start, $end);
*/
/*echo "Total Revenue: \$$totalRevenue  Total Commission: \$$totalCommission"; 

echo "<p>Revenues";
foreach($revenues as $month => $revenue) {
	echo "<p>$month";
	foreach($revenue as $service => $amount) 
		echo "<br>... $service: $amount";
}


echo "<p>Commissions";
foreach($commissions as $month => $commission) {
	echo "<p>$month";
	foreach($commission as $service => $amount) 
		echo "<br>... $service: $amount";
}

*/
/*
echo "<p>".(dropProjectionApptTable() ? "Success!" : "Failure!");
*/
