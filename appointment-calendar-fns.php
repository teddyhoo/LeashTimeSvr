<? // appointment-calendar-fns.php
// functions to display a set of appointments in a matrix calendar

require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";
require_once "appointment-fns.php";
require_once "discount-fns.php";

$appDayColor = 'white';//'#B7FFDB';
$selectionOn = '#FF3300';
$selectionOff = $appDayColor;

function dumpCalendarLooks($rowHeight, $descriptionColor) {
	global $appDayColor;
	echo <<<LOOKS
<style>
 .previewcalendar { background:white;width:100%;border:solid black 2px;margin:5px; }

 .previewcalendar td {border:solid black 1px;width:14.29%;}
 .appday {border:solid black 1px;background:$appDayColor;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}
 .apptable td {border:solid black 0px;}
 .empty {border:solid black 1px;background:white;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

 .month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

 .dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
 .daynumber {font-size:1.5em;font-weight:bold;text-align:right;width:25px;}
 .apptcontrols {cursor:pointer;float:left;margin-right:3px;height:10px;width:10px; border:solid darkgray 1px;}
 .hiddentable {width:100%;border:solid black 0px;}
 .hiddentable td {border:solid black 0px;}
 .hiddentable td {border:solid black 0px;}
 .italicized {font-style:italic;}
	</style>
LOOKS;
}

function irregularPackageTable($packageid, $clientid, $primaryProvider) {
	$history = findPackageIdHistory($packageid, $clientid, false);
	$history[] = $packageid;
//echo "$clientid $packageid: ".count($history);exit;	
	$history = join(',', $history);
//echo $history;exit;	
	$appts = fetchAssociations("SELECT * FROM tblappointment WHERE packageptr IN ($history) ORDER BY date, starttime");
	
//echo "SELECT * FROM tblappointment WHERE packageptr IN ($history) ORDER BY date, starttime";exit;	
//echo count($appts);exit;	
	require_once "surcharge-fns.php";
	$surcharges = $_SESSION['surchargesenabled'] ? getPackageSurcharges($packageid, $clientid) : array();
	$packageDetails = fetchFirstAssoc(
		"SELECT startdate, enddate, clientptr, defaultproviderptr , packageid
			FROM tblservicepackage 
			JOIN tblclient ON clientid = clientptr
			WHERE packageid = $packageid");
	$packageDetails['primaryProvider'] = $primaryProvider;
	appointmentTable($appts, $packageDetails, $editable=true, $allowSurchargeEdit=true, $showStats=true, $includeApptLinks=true, $surcharges);
}

// ################################################################################################################################

function appointmentTable($appts, $packageDetails = null, $editable=false, $allowSurchargeEdit=true, $showStats=true, $includeApptLinks=true, $surcharges=null, $otherItems=null) {  // appts are ordered by date/starttime
//if(!mattOnlyTEST()) return appointmentTableOLD($appts, $packageDetails, $editable, $allowSurchargeEdit, $showStats, $includeApptLinks, $surcharges);
	global $undeletableAppointments, $undeletableSurcharges, $discounts;
	if(!$appts && !$editable) return;
	$dayLength = 24 * 60 * 60;
	$scheduleStartsOn = "Schedule starts on";
	$scheduleEndsOn = "and ends on";
	
//if(staffOnlyTEST()) echo "[[".print_r($packageDetails,1).']]';	
	if(!$packageDetails && $appts) {
		foreach($appts as $appt) {
			if(!$firstAppt) $firstAppt = $appt;
			$clientptr = $appt['clientptr'];
			$representedPackages[$appt['packageptr']] = $appt['recurringpackage']; 
			$lastAppt = $appt;
		}
		foreach($representedPackages as $packageid => $recurringpackage)
			$currentPackages[] = findCurrentPackageVersion($packageid, $clientptr, $recurringpackage);
		$currentPackages = array_unique($currentPackages);
		if(count($representedPackages) == 1 && !$recurringpackage) {
			$packageDetails = getPackage($currentPackages[0]);
//if(mattOnlyTEST()) echo "<hr>".print_r($packageDetails	, 1);	
			$start = $packageDetails['startdate'];
			$end = (int)date('Y', strtotime($packageDetails['enddate'])) > 2000 
							? $packageDetails['enddate'] 
							: $packageDetails['startdate'];
		}
		else {
			$start = $firstAppt['date'];
			$end = $lastAppt['date'];
			$scheduleStartsOn = "Visits shown from";
			$scheduleEndsOn = "to";
		}
	}
	else if($packageDetails) {
		
		$clientptr = $packageDetails['clientptr'];
		$start = $packageDetails['startdate'];
		$end = (int)date('Y', strtotime($packageDetails['enddate'])) > 2000 
						? $packageDetails['enddate'] 
						: $packageDetails['startdate'];
	}
	
	/*if(!$packageDetails) {
		$packageDetails = current($appts);
		$packid = findCurrentPackageVersion($packageDetails['packageptr'], $packageDetails['clientptr'], $packageDetails['recurringpackage']);
		$packageDetails = getPackage($packid);
	}
	*/
//if(mattOnlyTEST()) {print_r($appts);exit;}
	$numAppointments = count($appts);
	$visitDays = array();
	//$scheduleDays = (int)((strtotime("$end 12:00:00") - strtotime("$start 12:00:00")) / $dayLength) + 1;
	$scheduleDays = abs(date_diff(date_create($end), date_create($start))->days)+1;



//if(mattOnlyTEST()) echo "x: ".floor(((strtotime("$end 12:00:00") - strtotime("$start 12:00:00")) / $dayLength))."scheduleDays: $scheduleDays - start: $start, end: $end (dayLength: $dayLength)";	
	$dayNum = 1;
	$time = null;
	$price = 0;
	$apptProviders = array();

	$allScheduleApptsAndSurcharges = array_merge($appts, (array)$surcharges);
	if($allScheduleApptsAndSurcharges) usort($allScheduleApptsAndSurcharges, 'dateSort');
//if(mattOnlyTEST()) {echo "<pre>".print_r($allScheduleApptsAndSurcharges,1)."</pre>";exit;}	
	foreach($allScheduleApptsAndSurcharges as $appt) {
		$itsAVisit = $appt['appointmentid'];
		if($time != strtotime("{$appt['date']} 03:00:00")) {
			$time = $time ? $time : strtotime("$start 03:00:00");
			$apptTime = strtotime("{$appt['date']}  03:00:00");
			if($itsAVisit) $visitDays[$apptTime] = 1;
			if(($jump = (round(($apptTime - $time) / $dayLength)) - 1) > 1) {
				for($i=1;$i<$jump;$i++) $apptdays[] = null;
			}
			$dayNum += $jump;
			$time = $apptTime;
			$dayNum++;
		}
		$apptdays[$dayNum-1][] = $appt;
		if($itsAVisit) {
			if(!$appt['canceled']) $price += $appt['charge']+$appt['adjustment'];
			if($appt['providerptr']) $apptProviders[$appt['providerptr']] ++;
			$apptIds[] = $appt['appointmentid'];
		}
		else {
			$surchargeIds[] = $appt['surchargeid'];
			if(!$appt['canceled']) $price += $appt['charge'];
		}
	}
	$discounts = getAppointmentDiscounts($apptIds, 1);
	foreach($discounts as $discount) $price -= $discount['amount'];
	$noVisitDays = $scheduleDays - count($visitDays);
//if(mattOnlyTEST()) { echo "$noVisitDays = $scheduleDays - ".print_r($visitDays,1);exit;}	
	if($apptdays) foreach($apptdays as $i => $apptsAndSurcharges) {
		if($apptsAndSurcharges) usort($apptsAndSurcharges, 'dateSort');
		$apptdays[$i] = $apptsAndSurcharges;
	}
	
// now we need to sort all $apptdays

//foreach($apptdays as $i => $day) echo "$i: ".print_r($day,1).'<p>';

// non-css styling is for GMail, etc
?>
<table style='font-size:1.4em;width:100%;text-align:center;'>
<?

if($showStats)  {
	$prettyStart = longDayAndDate(strtotime($start));
	$prettyEnd = longDayAndDate(strtotime($end));
	static $currencyMark;
	if(!$currencyMark) $currencyMark = getCurrencyMark();
	$prettyPrice = number_format($price, 2);
	$scheduleDays .= ' day'.($scheduleDays == 1 ? '' : 's');
	if($editable && (staffOnlyTEST() || dbTEST('careypet')) ) {
		$staffOnlyNote = ": StaffOnly appointment-calendar-fns.php";
		$wagURL = "wag.php?starting=$start&ending=$end";
		$wagButton = "<img src='art/wag.gif' title='Week at a Glance$staffOnlyNote' 
										onclick=\"openConsoleWindow('wag', '$wagURL',750,700)\" 
										style='cursor:pointer;margin-left:20px' width=17 height=17>";
	}

	if($editable && $packageDetails['packageid'] && 
			($_SESSION['preferences']['enableEZScheduleEmailButton'] || staffOnlyTEST())) {
		$url = "comm-visits-composer.php?client={$packageDetails['clientptr']}&scheduleid={$packageDetails['packageid']}&offer=0"; // client=47
		$staffOnlyNote = !staffOnlyTEST() ? 'Business Option'
								: 'Staff Only or Bus Opt.  appointment-calendar-fns.php';
		$emailButton = "<img src='art/email-message-trimmed.gif' title='Email this schedule: $staffOnlyNote' 
										onclick=\"openConsoleWindow('emailschedule', '$url',750,700)\" 
										style='cursor:pointer;margin-left:20px' width=25 height=17>";
	}

	echo <<<STATS
<tr><td colspan=3>
$scheduleStartsOn: <b>$prettyStart</b> $scheduleEndsOn <b>$prettyEnd</b> ($scheduleDays) $wagButton $emailButton
</td></tr>
<tr><td>Visits: $numAppointments</td><td>Days without visits: $noVisitDays</td><td>Price: $currencyMark$prettyPrice<td></tr>
</table>
STATS;
}
$previousUndeletableAppointments = is_array($undeletableAppointments) ? $undeletableAppointments : array(); // allow for pre-determined non-deletables
$undeletableAppointments = array();
$undeletableSurcharges = array();
if(true || $editable) {
	// Provider either: the commonest provider in existing package appts, the client's default provider, or a past provider
	if($packageDetails['primaryProvider']) $provider = $packageDetails['primaryProvider'];
	else if($apptProviders) {
		asort($apptProviders);
		$provider = $apptProviders[count($apptProviders)-1];
	}
	else $provider = $packageDetails['defaultproviderptr'];
	if(!$provider) {
		$pastProviders = fetchCol0(
			"SELECT DISTINCT providerptr 
			 FROM tblappointment 
			 LEFT JOIN tblprovider ON providerptr = providerid
			 WHERE clientptr = $clientptr AND canceled IS NULL and tblprovider.active = 1");
		if($pastProviders) $provider = $pastProviders[0];
	}
	$apptIds = array();
	foreach($appts as $appt) $apptIds[] = $appt['appointmentid'];
	$apptIds = join(',', $apptIds);
	$apptIds = $apptIds ? $apptIds  : '0';
	
	// a visit/surcharge is NOT deleteable if it has a billable AND (the billable is partially paid OR the item is completed)
	
	$undeletableAppointments = array_merge($previousUndeletableAppointments, fetchCol0(
			"SELECT appointmentid, payableid
				FROM tblappointment 
				LEFT JOIN tblpayable ON itemptr = appointmentid AND itemtable = 'tblappointment'
				WHERE appointmentid IN ($apptIds) AND payableid IS NOT NULL"));
	$undeletableAppointments = array_merge($undeletableAppointments, 
		fetchCol0(
			"SELECT appointmentid, billableid
				FROM tblappointment 
				LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
				WHERE appointmentid IN ($apptIds) AND billableid IS NOT NULL AND (completed IS NOT NULL OR paid > 0)"));
	if($surchargeIds) {
		$surchargeIds = join(',', $surchargeIds);
		$undeletableSurcharges = fetchCol0(
			"SELECT surchargeid, payableid
				FROM tblsurcharge 
				LEFT JOIN tblpayable ON itemptr = surchargeid AND itemtable = 'tblsurcharge'
				WHERE surchargeid IN ($surchargeIds) AND payableid IS NOT NULL");
		$undeletableSurcharges = array_merge($undeletableSurcharges, 
			fetchCol0(
				"SELECT surchargeid, billableid
					FROM tblsurcharge 
					LEFT JOIN tblbillable ON itemptr = surchargeid AND itemtable = 'tblsurcharge'
					WHERE surchargeid IN ($surchargeIds) AND billableid IS NOT NULL AND (completed IS NOT NULL OR paid > 0)"));
	}
	else $undeletableSurcharges = array();
}
echo "<span id='undeletableAppointments' style='display:none'>".join(',', $undeletableAppointments)."</span>";
echo "<span id='undeletableSurcharges' style='display:none'>".join(',', $undeletableSurcharges)."</span>";
echo "<table class='previewcalendar'  border=1 bordercolor=black>";

$start = date('Y-m-d',strtotime($start));
$end = date('Y-m-d',strtotime($end));
$month = '';
$dayN = 0;
// allow for appts before start...
for($i=0; $i < count($apptdays); $i++)
	if($apptdays[$i] && strcmp($apptdays[$i][0]['date'], $start) < 0)
		$dayN++;
for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
	$dow = date('w', strtotime($day));
	if($month != date('F Y', strtotime($day))) {
		if($dow && $month) {  // finish prior month, if any
			for($i=$dow; $i < 7; $i++) echo "<td>&nbsp;</td>";
			echo "</tr>";
		}
		$month = date('F Y', strtotime($day));
		echoMonthBar($month);
		echo "<tr>";
		for($i=0; $i < $dow; $i++) echo "<td>&nbsp;</td>";
	}
	if(!$dow) echo "</tr><tr>";
//if(mattOnlyTEST()) {echo "start[$start] apptdays[0][0][date] = {$apptdays[0][0]['date']} dayN: $dayN: <pre>".print_r($apptdays,1)."</pre>";exit;}	
	
	if($editable) echoEditableDayBox($day, $apptdays[$dayN], $clientptr, $provider, $packageDetails['packageid'], $includeApptLinks);
	else echoDayBox($day, $apptdays[$dayN], $includeApptLinks, $packageDetails, $allowSurchargeEdit, $otherItems);
	$dayN++;
}
if($dow && $month) {  // finish prior month, if any
	for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
	echo "</tr>";
}

echo "</table>";
//if(mattOnlyTEST()) exit;
return true;
} //appointmentTable

function clientVisitList($appts, $forceVisitTimeInclusion=false) {
	global 	$userRole;
	if(!$_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'] || $userRole != 'c')
		require_once "appointment-calendar-fns.php";

	$sitters = getProviderShortNames();
	ob_start();
	ob_implicit_flush(0);
	foreach((array)$appts as $appt) {
		$row = $appt;
		$row['date'] = shortDateAndDay(strtotime($appt['date']));
		if(!$forceVisitTimeInclusion && $_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'] && $userRole == 'c')
			$row['timeofday'] = timeOfDayBracket($appt);
		$row['service'] = $_SESSION['allservicenames'][$appt['servicecode']];
		$row['sitter'] = $appt['providerptr'] ? $sitters[$appt['providerptr']] : '&nbsp;';
		$rows[] = $row;
	}
	$columns = explodePairsLine('date|Date||timeofday|Time||service|Service||sitter|Sitter');
	if(providerNamesCompletelySuppressed()) unset($columns['sitter']);
	tableFrom($columns, $rows, 'WIDTH=95% BORDER=1 bgcolor=white');
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}



function appointmentTableOLD($appts, $packageDetails = null, $editable=false, $allowSurchargeEdit=true, $showStats=true, $includeApptLinks=true, $surcharges=null) {  // appts are ordered by date/starttime
	global $undeletableAppointments, $undeletableSurcharges, $discounts;
	if(!$appts && !$editable) return;
	$dayLength = 24 * 60 * 60;
	$scheduleStartsOn = "Schedule starts on";
	$scheduleEndsOn = "and ends on";
	
//if(staffOnlyTEST()) echo "[[".print_r($packageDetails,1).']]';	
	if(!$packageDetails && $appts) {
		foreach($appts as $appt) {
			if(!$firstAppt) $firstAppt = $appt;
			$clientptr = $appt['clientptr'];
			$representedPackages[$appt['packageptr']] = $appt['recurringpackage']; 
			$lastAppt = $appt;
		}
		foreach($representedPackages as $packageid => $recurringpackage)
			$currentPackages[] = findCurrentPackageVersion($packageid, $clientptr, $recurringpackage);
		$currentPackages = array_unique($currentPackages);
		if(count($representedPackages) == 1 && !$recurringpackage) {
			$packageDetails = getPackage($currentPackages[0]);
//if(mattOnlyTEST()) echo "<hr>".print_r($packageDetails	, 1);	
			$start = $packageDetails['startdate'];
			$end = (int)date('Y', strtotime($packageDetails['enddate'])) > 2000 
							? $packageDetails['enddate'] 
							: $packageDetails['startdate'];
		}
		else {
			$start = $firstAppt['date'];
			$end = $lastAppt['date'];
			$scheduleStartsOn = "Visits shown from";
			$scheduleEndsOn = "to";
		}
	}
	else if($packageDetails) {
		
		$clientptr = $packageDetails['clientptr'];
		$start = $packageDetails['startdate'];
		$end = (int)date('Y', strtotime($packageDetails['enddate'])) > 2000 
						? $packageDetails['enddate'] 
						: $packageDetails['startdate'];
	}
	
	/*if(!$packageDetails) {
		$packageDetails = current($appts);
		$packid = findCurrentPackageVersion($packageDetails['packageptr'], $packageDetails['clientptr'], $packageDetails['recurringpackage']);
		$packageDetails = getPackage($packid);
	}
	*/
//if(mattOnlyTEST()) {print_r($appts);exit;}
	$numAppointments = count($appts);
	$visitDays = 0;
	$scheduleDays = (int)((strtotime("$end 12:00:00") - strtotime("$start 12:00:00")) / $dayLength) + 1;
//if(mattOnlyTEST()) echo "x: ".floor(((strtotime("$end 12:00:00") - strtotime("$start 12:00:00")) / $dayLength))."scheduleDays: $scheduleDays - start: $start, end: $end (dayLength: $dayLength)";	
	$dayNum = 1;
	$time = null;
	$price = 0;
	$apptProviders = array();
//if(mattOnlyTEST()) {echo "<pre>".print_r($appts,1)."</pre>";exit;}	

	foreach($appts as $appt) {
		if($time != strtotime("{$appt['date']} 03:00:00")) {
			$time = $time ? $time : strtotime("$start 03:00:00");
			$apptTime = strtotime("{$appt['date']}  03:00:00");
			$visitDays++;
			if(($jump = (round(($apptTime - $time) / $dayLength)) - 1) > 1) {
				for($i=1;$i<$jump;$i++) $apptdays[] = null;
			}
			$dayNum += $jump;
			$time = $apptTime;
			$dayNum++;
		}
		$apptdays[$dayNum-1][] = $appt;
		if(!$appt['canceled']) $price += $appt['charge']+$appt['adjustment'];
		if($appt['providerptr']) $apptProviders[$appt['providerptr']] ++;
		$apptIds[] = $appt['appointmentid'];
	}
	$discounts = getAppointmentDiscounts($apptIds, 1);
	foreach($discounts as $discount) $price -= $discount['amount'];
	$noVisitDays = $scheduleDays - $visitDays;
	$time = null;
	$dayNum = 1;
	if($surcharges) foreach($surcharges as $surcharge) {
		if($time != strtotime($surcharge['date'])) {
			$time = $time ? $time : strtotime($start);
			$surchTime = strtotime("{$surcharge['date']}  03:00:00");
			if(($jump = (($surchTime - $time) / $dayLength) - 1) > 1) {
			}
			$dayNum += $jump;
			$time = $surchTime;
			$dayNum++;
		}
		$apptdays[$dayNum-1][] = $surcharge;
		$surchargeIds[] = $surcharge['surchargeid'];
		if(!$surcharge['canceled']) $price += $surcharge['charge'];
	}
	if($apptdays) foreach($apptdays as $i => $apptsAndSurcharges) {
		if($apptsAndSurcharges) usort($apptsAndSurcharges, 'dateSort');
		$apptdays[$i] = $apptsAndSurcharges;
	}
	
// now we need to sort all $apptdays

//foreach($apptdays as $i => $day) echo "$i: ".print_r($day,1).'<p>';

// non-css styling is for GMail, etc
?>
<table style='font-size:1.4em;width:100%;text-align:center;'>
<?
// function: appointmentTableOLD
if($showStats)  {
	$prettyStart = longDayAndDate(strtotime($start));
	$prettyEnd = longDayAndDate(strtotime($end));
	static $currencyMark;
	if(!$currencyMark) $currencyMark = getCurrencyMark();
	$prettyPrice = number_format($price, 2);
	$scheduleDays .= ' day'.($scheduleDays == 1 ? '' : 's');
	echo <<<STATS
<tr><td colspan=3>
$scheduleStartsOn: <b>$prettyStart</b> $scheduleEndsOn <b>$prettyEnd</b> ($scheduleDays)
</td></tr>
<tr><td>Visits: $numAppointments</td><td>Days without visits: $noVisitDays</td><td>Price: $currencyMark$prettyPrice<td></tr>
</table>
STATS;
}
$previousUndeletableAppointments = is_array($undeletableAppointments) ? $undeletableAppointments : array(); // allow for pre-determined non-deletables
$undeletableAppointments = array();
$undeletableSurcharges = array();
if(true || $editable) {
	// Provider either: the commonest provider in existing package appts, the client's default provider, or a past provider
	if($packageDetails['primaryProvider']) $provider = $packageDetails['primaryProvider'];
	else if($apptProviders) {
		asort($apptProviders);
		$provider = $apptProviders[count($apptProviders)-1];
	}
	else $provider = $packageDetails['defaultproviderptr'];
	if(!$provider) {
		$pastProviders = fetchCol0(
			"SELECT DISTINCT providerptr 
			 FROM tblappointment 
			 LEFT JOIN tblprovider ON providerptr = providerid
			 WHERE clientptr = $clientptr AND canceled IS NULL and tblprovider.active = 1");
		if($pastProviders) $provider = $pastProviders[0];
	}
	$apptIds = array();
	foreach($appts as $appt) $apptIds[] = $appt['appointmentid'];
	$apptIds = join(',', $apptIds);
	$apptIds = $apptIds ? $apptIds  : '0';
	$undeletableAppointments = array_merge($previousUndeletableAppointments, fetchCol0(
			"SELECT appointmentid, payableid
				FROM tblappointment 
				LEFT JOIN tblpayable ON itemptr = appointmentid AND itemtable = 'tblappointment'
				WHERE appointmentid IN ($apptIds) AND payableid IS NOT NULL"));
	$undeletableAppointments = array_merge($undeletableAppointments, 
		fetchCol0(
			"SELECT appointmentid, billableid
				FROM tblappointment 
				LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
				WHERE appointmentid IN ($apptIds) AND billableid IS NOT NULL AND (completed IS NOT NULL OR paid > 0)"));
	if($surchargeIds) {
		$surchargeIds = join(',', $surchargeIds);
		$undeletableSurcharges = fetchCol0(
			"SELECT surchargeid, payableid
				FROM tblsurcharge 
				LEFT JOIN tblpayable ON itemptr = surchargeid AND itemtable = 'tblsurcharge'
				WHERE surchargeid IN ($surchargeIds) AND payableid IS NOT NULL");
		$undeletableSurcharges = array_merge($undeletableSurcharges, 
			fetchCol0(
				"SELECT surchargeid, billableid
					FROM tblsurcharge 
					LEFT JOIN tblbillable ON itemptr = surchargeid AND itemtable = 'tblsurcharge'
					WHERE surchargeid IN ($surchargeIds) AND billableid IS NOT NULL AND (completed IS NOT NULL OR paid > 0)"));
	}
	else $undeletableSurcharges = array();
}
echo "<span id='undeletableAppointments' style='display:none'>".join(',', $undeletableAppointments)."</span>";
echo "<span id='undeletableSurcharges' style='display:none'>".join(',', $undeletableSurcharges)."</span>";
echo "<table class='previewcalendar'  border=1 bordercolor=black>";

$start = date('Y-m-d',strtotime($start));
$end = date('Y-m-d',strtotime($end));
$month = '';
$dayN = 0;
// allow for appts before start...
for($i=0; $i < count($apptdays); $i++)
	if($apptdays[$i] && strcmp($apptdays[$i][0]['date'], $start) < 0)
		$dayN++;
for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
	$dow = date('w', strtotime($day));
	if($month != date('F Y', strtotime($day))) {
		if($dow && $month) {  // finish prior month, if any
			for($i=$dow; $i < 7; $i++) echo "<td>&nbsp;</td>";
			echo "</tr>";
		}
		$month = date('F Y', strtotime($day));
		echoMonthBar($month);
		echo "<tr>";
		for($i=0; $i < $dow; $i++) echo "<td>&nbsp;</td>";
	}
	if(!$dow) echo "</tr><tr>";
//if(mattOnlyTEST()) {echo "start[$start] apptdays[0][0][date] = {$apptdays[0][0]['date']} dayN: $dayN: <pre>".print_r($apptdays,1)."</pre>";exit;}	
	
	if($editable) echoEditableDayBox($day, $apptdays[$dayN], $clientptr, $provider, $packageDetails['packageid'], $includeApptLinks);
	else echoDayBox($day, $apptdays[$dayN], $includeApptLinks, $packageDetails, $allowSurchargeEdit);
	$dayN++;
}
if($dow && $month) {  // finish prior month, if any
	for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
	echo "</tr>";
}

echo "</table>";
return true;
} //appointmentTableOLD

// ################################################################################################################################

function dateSort($a, $b) {
	$result = strcmp($a['starttime'], $b['starttime']);
	if(!$result) {
		$a = isset($a['appointmentid']) ? '1' : 2;
		$b = isset($b['appointmentid']) ? '1' : 2;
		$result = strcmp($a, $b);
	}
	return $result;
}
	
function echoMonthBar($month) {
	$days = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
	echo "<tr><td class='month' colspan=7>$month</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function echoDayBox($day, $appts, $includeApptLinks, &$packageDetails, $allowSurchargeEdit, $otherItems) {
	global $suppressNoVisits;
	$dom = date('j', strtotime($day));
	$class = $appts && $appts[0] ? 'appday' : 'empty';
	$visitCount = 0;
	if($appts) foreach($appts as $appt) if($allowSurchargeEdit || $appt['appointmentid']) $visitCount++;
	echo "<td class='$class' id='box_$day' valign='top'><div class='daynumber'>$dom</div>";
	if($class == 'empty') {if(!$suppressNoVisits) echo "<span style='color:red'>No visits.</span>";}
	else {
		echo "<table class='apptable'>";
		$newSurchargeLink = $allowSurchargeEdit 
			? newSurchargeLink($day, $packageDetails['clientptr'], $packageDetails['providerptr'], $packageDetails['packageid']) 
			: '';
		echo "<tr><td style='text-align:left;color:blue'>$visitCount visit".($visitCount == 1 ? '' : 's')."</td><td style='text-align:right'>"
					.$newSurchargeLink
					."</td></tr>";
		if($appts) foreach($appts as $appt) {
			dumpAppointmentTile($appt, $allowSurchargeEdit, $includeApptLinks);
			/*if($appt['surchargeid'] && !$allowSurchargeEdit) continue; 

			$class = $appt['completed'] 
								? 'completedtask' 
								: ($appt['canceled'] ? 'canceledtask' : 'noncompletedtask');
			$class .= ' '.($appt['surchargeid'] ? 'surcharge selectable' : 'appointment selectable');
			echo "<tr><td colspan=2><hr></td></tr>";
			if($appt['appointmentid']) {
				$apptDisplay = $includeApptLinks  ? appointmentLink($appt) : appointmentDisplay($appt);
				$tdid = "appt_{$appt['appointmentid']}";
			}
			else {
				$apptDisplay = surchargeLink($appt);
				$tdid = "surch_{$appt['surchargeid']}";
			}
			echo "<tr><td id='$tdid' class='$class' style='border: solid black 0px' colspan=2>$apptDisplay</td></tr>\n";*/
		}
		if($otherItems) {
			$clientptr = $packageDetails['clientptr'] ? $packageDetails['clientptr'] : $appts[0]['clientptr'];
			$otherItems = otherItemsForTheDay($clientptr, $day, $appts);
			if($otherItems)
				echo "<tr><td colspan=2><hr>Other visits:</td></tr>";
			foreach($otherItems as $item)
				dumpAppointmentTile($item, $allowSurchargeEdit, $includeApptLinks, 'italicized');
		}
		echo "</table>";
	}
	echo "</td>";
}

function dumpAppointmentTile($appt, $allowSurchargeEdit, $includeApptLinks, $otherClass=null) {
	if($appt['surchargeid'] && !$allowSurchargeEdit) return; 

	$class = $appt['completed'] 
						? 'completedtask' 
						: ($appt['canceled'] ? 'canceledtask' : 'noncompletedtask');
	$class .= ' '.($appt['surchargeid'] ? 'surcharge selectable' : 'appointment selectable');
	if($otherClass) $class .= " $otherClass";
	echo "<tr><td colspan=2><hr></td></tr>";
	if($appt['appointmentid']) {
		$apptDisplay = $includeApptLinks  ? appointmentLink($appt) : appointmentDisplay($appt);
		$tdid = "appt_{$appt['appointmentid']}";
	}
	else {
		$apptDisplay = surchargeLink($appt);
		$tdid = "surch_{$appt['surchargeid']}";
	}
	echo "<tr><td id='$tdid' class='$class' style='border: solid black 0px' colspan=2>$apptDisplay</td></tr>\n";
}

function otherItemsForTheDay($clientptr, $day, $appts) {
	// find appointments for client on day, excluding those in $appts
	$apptids = array();
	foreach($appts as $appt) if($appt['appointmentid']) $apptids[] = $appt['appointmentid'];
	$exclude = $apptids ? "AND appointmentid NOT IN (".join(',', $apptids).")" : '';
	return fetchAssociations(
		"SELECT * 
			FROM tblappointment 
			WHERE date = '$day' AND clientptr = $clientptr AND canceled IS NULL $exclude
			ORDER BY starttime");
}

function echoEditableDayBox($day, $appts, $client, $provider, $packageid, $includeApptLinks) {
	global $suppressNoVisits;	
	$dom = date('j', strtotime($day));
	$class = $appts && $appts[0] ? 'appday' : 'empty';
	$pasteLink = "<a id='dom_$day' onclick='pasteHere(\"$day\")' style='display:none;'>Paste Here</a>";
	$noSelections = "<span id='nosel_$day' style='display:none;'>No selections.</span>";
	echo "<td class='$class'  id='box_$day'><table class='hiddentable' valign='top'><tr>";
	echo "<td style='text-align:left;width:100%;' onmouseover='readyToPaste(\"$day\")' onmouseout='return showPasteLink(\"$day\", 0)'>$pasteLink$noSelections</td>";
	$domStyle = date('Y-m-d', strtotime($day)) == date('Y-m-d') ? "style='color:red;'" : "";
	echo "<td class='daynumber' $domStyle>$dom</td>";
	echo "</tr></table>";
	$count = $class == 'empty'
		? ($suppressNoVisits ? '' : "<span>No visits.</span>")
		: "<span style='color:blue'>".count($appts)." visit".(count($appts) == 1 ? '' : 's').".</span>";
	echo "<table class='apptable'>";
	
	echo "<tr><td style='text-align:left'>$count</td><td style='text-align:right'>".newBillableLink($day, $client, $provider, $packageid)."</td></tr>";
	if(false) {
		echo "<tr><td style='text-align:left'>$count</td><td style='text-align:right'>".newAppointmentLink($day, $client, $provider, $packageid)."</td></tr>";
		echo "<tr><td style='text-align:left'>&nbsp;</td><td style='text-align:right'>".newSurchargeLink($day, $client, $provider, $packageid)."</td></tr>";
	}
	if($appts) foreach($appts as $appt) {
		$id = $appt['appointmentid'] ? $appt['appointmentid'] : $appt['surchargeid'];
		$el_id = $appt['appointmentid'] ? "appt_$id" : "surcharge_$id";
		$onClick="onclick=toggleSelection(\"$el_id\")";
		$class = $appt['completed'] 
							? 'completedtask' 
							: ($appt['canceled'] ? 'canceledtask' : 'noncompletedtask');
		echo "<tr><td colspan=2><hr></td></tr>";
		
		if($appt['appointmentid'])
			$apptDisplay = $includeApptLinks  
				? billableLink($appt) // appointmentLink($appt)
				: appointmentDisplay($appt);
		else $apptDisplay = billableLink($appt); // surchargeLink($appt)
		
		echo "<tr><td id='appttd_$id' colspan=2 class='$class' style='border: solid black 1px;background-image:url(\"./art/lightningheadprofile.gif\");background-position:bottom right;background-repeat:no-repeat;' $onClick>"
						.$apptDisplay;
		hiddenElement($el_id, isset($_REQUEST[$el_id]) && $_REQUEST[$el_id] ? 1 : 0);
		echo "</td></tr>\n";
		
		//echo "<input id='$el_id' name='$el_id' value='".(isset($_REQUEST[$el_id]) && $_REQUEST[$el_id] ? 1 : 0)."' style='display:none;'>";

	
	}
//echo "<tr><td colspan=2>+++ ".print_r($_REQUEST, 1);
	echo "</table>";
	echo "</td>";
}

function surchargeLink($surcharge)  {
	global $providerNames;
	$providerNames = $providerNames ? $providerNames : getProviderShortNames();
	$pname = $surcharge['providerptr'] ? $providerNames[$surcharge['providerptr']] : 'Unassigned' ;
	$surchargesByType = getSurchargeTypesById();
	$title = "Sitter: $pname";
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	if($roDispatcher) {
		//$editScript = "client-request-appointment.php?operation=change&id={$appt['appointmentid']}";
	}
	else $editScript = "surcharge-edit.php?id={$surcharge['surchargeid']}";
	$chargeLabel = 'sur: '.dollarAmount($surcharge['charge'])
									."<br>{$surchargesByType[$surcharge['surchargecode']]}<br>$pname";
	if($roDispatcher)
		return 
			fauxLink($chargeLabel,
										"if(typeof cacheSelections == \"function\") cacheSelections();openConsoleWindow(\"editappt\", \"$editScript\",530,450 )",
										'noEcho',
										$title);
	else
		return 
			"<div style='border:dashed green 2px;padding:2px;margin:-1px;'>"
			.deleteButton($surcharge, 1).' '.fauxLink($chargeLabel,
										"if(typeof cacheSelections == \"function\") cacheSelections();openConsoleWindow(\"editappt\", \"$editScript\",530,450 )",
										'noEcho',
										$title)
			.' '.cancelUncancelButton($surcharge, 1, $roDispatcher)
			."</div>";
}

/*function briefTimeOfDay($appt) { // moved to appointment-fns.php
	$tod = explode('-', $appt['timeofday']);
	$start = explode(':', $tod[0]);
	$start = ''+$start[0].(substr($start[1], 0, 1) == '00' ? '' : ":".substr($start[1], 0, 2)).(strpos($start[1], 'a') ? 'a' : 'p');
	$end = explode(':', $tod[1]);
	$end = ''+$end[0].(substr($end[1], 0, 1) == '00' ? '' : ":".substr($end[1], 0, 2)).(strpos($end[1], 'a') ? 'a' : 'p');
	return $start.'-'.$end;
	
} */

function appointmentLink($appt)  {
	global $providerNames, $discounts, $userRole, $showModifiedTag; // $userRole may or may not be set.  Overrides logged in $userRole
	$providerNames = $providerNames ? $providerNames : getProviderShortNames();
	$pname = $appt['providerptr'] ? $providerNames[$appt['providerptr']] : 'Unassigned' ;
	$servicesByType = $_SESSION['servicenames'];
	$title = "Sitter: $pname - Pets: {$appt['pets']}{$appt['title']}";
//if(mattOnlyTEST()) $title .= " ".safeValue(print_r($appt, 1));
	$unassignedWarning = !$appt['providerptr'] ? "<span style='color:red'>UNASSIGNED</span>" : '';
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

	if($roDispatcher) {
		$editScript = "client-request-appointment.php?operation=change&id={$appt['appointmentid']}";
	}
	else $editScript = "appointment-edit.php?id={$appt['appointmentid']}";
//echo print_r($discounts, 1);exit;	
	$discountTag = $discounts[$appt['appointmentid']]
		? "<b title='Discount: {$discounts[$appt['appointmentid']]['label']}'>[D]</b> "
		: '';
	$modifiedTag = $showModifiedTag && $appt['custom']
		? "<b title='Modified after creation'>[M]</b> "
		: '';
	if(!$_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'] || $userRole != 'c')
		$tod = briefTimeOfDay($appt).'<br>';
	else $tod = '';
	$serviceLabel = htmlentities($servicesByType[$appt['servicecode']]);	
	return 
		deleteButton($appt).' '.$discountTag.$modifiedTag.fauxLink("$tod{$serviceLabel}<br>$pname",
									"if(typeof cacheSelections == \"function\") cacheSelections();openConsoleWindow(\"editappt\", \"$editScript\",{$_SESSION['dims']['appointment-edit']} )",
									'noEcho',
									$title).' '.cancelUncancelButton($appt, null, $roDispatcher).$unassignedWarning;
}

function billableLink($appt) {
	global $providerNames, $discounts, $userRole; // $userRole may or may not be set.  Overrides logged in $userRole
	$providerNames = $providerNames ? $providerNames : getProviderShortNames();
	$pname = $appt['providerptr'] ? $providerNames[$appt['providerptr']] : 'Unassigned' ;
	$labels = $appt['appointmentid'] ? $_SESSION['servicenames'] : getSurchargeTypesById();
	$title = "Sitter: $pname";
	if($appt['appointmentid']) $title .= "- Pets: {$appt['pets']}";
	$unassignedWarning = !$appt['providerptr'] ? "<span style='color:red'>UNASSIGNED</span>" : '';
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

	if($roDispatcher) {
		$editScript = $appt['appointmentid'] 
			? "client-request-appointment.php?operation=change&id={$appt['appointmentid']}"
			: '';
	}
	else {
		if($appt['appointmentid']) {
			$id = $appt['appointmentid'];
			$objtype = 'visit';
		}
		else {
			$id = $appt['surchargeid'];
			$objtype = 'surcharge';
		}
			
		$editScript = "editBillable($id, \"$objtype\")";
	}
	if(!$_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'] || $userRole != 'c')
		$tod = briefTimeOfDay($appt).'<br>';
	else $tod = '';
	if($appt['appointmentid']) {
		$discountTag = $discounts[$appt['appointmentid']]
			? "<b title='Discount: {$discounts[$appt['appointmentid']]['label']}'>[D]</b> "
			: '';
		$serviceLabel = htmlentities($labels[$appt['servicecode']]);
//if(mattOnlyTEST()) $serviceLabel = print_r($labels,1);	
		return 
			deleteButton($appt).' '.$discountTag.fauxLink("$tod{$serviceLabel}<br>$pname",
										"if(typeof cacheSelections == \"function\") cacheSelections();$editScript",
										'noEcho',
										$title).' '.cancelUncancelButton($appt, null, $roDispatcher).$unassignedWarning;
	}
	else {
		$chargeLabel = 'sur: '.dollarAmount($appt['charge'])
										."<br>{$labels[$appt['surchargecode']]}<br>$pname";
		if($roDispatcher)
			return 
				fauxLink($chargeLabel,
											"if(typeof cacheSelections == \"function\") cacheSelections();openConsoleWindow(\"editappt\", \"$editScript\",530,450 )",
											'noEcho',
											$title);
		else
			return 
				"<div style='border:dashed green 2px;padding:2px;margin:-1px;'>"
				.deleteButton($appt, 1).' '.fauxLink($chargeLabel,
											"if(typeof cacheSelections == \"function\") cacheSelections();$editScript",
											'noEcho',
											$title)
				.' '.cancelUncancelButton($appt, 1, $roDispatcher)
				."</div>";
	}
}

function timeOfDayBracket($apptOrSurcharge) {
	if(TRUE || !dbTEST('dogslife')) return '';
	if(!$apptOrSurcharge['starttime']) return null;
	require_once "preference-fns.php";
	$timesOfDayRaw = getPreference('appointmentCalendarColumns');
	if(!$timesOfDayRaw) $timesOfDayRaw = 'Morning,07:00:00,Midday,11:00:00,Afternoon,15:00:00,Evening,19:00:00';
	$timesOfDayRaw = explode(',',$timesOfDayRaw);
	for($i=0;$i < count($timesOfDayRaw)-1; $i+=2) $timesOfDay[$timesOfDayRaw[$i+1]] = $timesOfDayRaw[$i];
	$timeStarts = array_keys($timesOfDay);
	$tod = null;
	for($i=0;$i < count($timeStarts); $i++) {
		if($i == count($timeStarts)-1  // last bracket
				|| $apptOrSurcharge['starttime'] < $timeStarts[$i+1]) {
			$tod = $timesOfDay[$timeStarts[$i]];
			break;
		}
	}
	return $tod;
}



function appointmentDisplay($appt)  {
	global $providerNames, $userRole; // $userRole may or may not be set.  Overrides logged in $userRole
	$providerNames = $providerNames ? $providerNames : getProviderShortNames();
	$pname = $appt['providerptr'] ? $providerNames[$appt['providerptr']] : 'Unassigned' ;
	$servicesByType = $_SESSION['servicenames'];
	$title = "Sitter: $pname - Pets: {$appt['pets']}{$appt['title']}";
	if(!$_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'] || $userRole != 'c')
		$tod = briefTimeOfDay($appt).'<br>';
	else $tod = timeOfDayBracket($appt).'<br>';
	$serviceLabel = htmlentities($servicesByType[$appt['servicecode']]);
	return "$tod{$serviceLabel}<br>$pname";
}
	
function newAppointmentLink($day, $client, $provider, $packageid)  {
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	if($roDispatcher) return '';
	$day = date('Y-m-d', strtotime($day));
	$providerptr = $provider && is_array($provider) ? $provider['providerptr'] : $provider;
	$provArg = "var prov = document.getElementById(\"primaryProvider\"); prov = (prov && prov.selectedIndex) ? prov.options[prov.selectedIndex].value : null;prov = prov ? prov : \"$provider\";";
	return fauxLink("Add Visit",
									"$provArg"."openConsoleWindow(\"editappt\", \"appointment-edit.php?date=$day&packageptr=$packageid&clientptr=$client&providerptr=\"+prov,{$_SESSION['dims']['appointment-edit']} )",
									'noEcho',
									"Create a new visit on this day.");
	/*return fauxLink("Add Visit",
									"$provArg"."openConsoleWindow(\"editappt\", \"appointment-edit.php?date=$day&packageptr=$packageid&clientptr=$client&providerptr=$providerptr\",{$_SESSION['dims']['appointment-edit']} )",
									'noEcho',
									"Create a new visit on this day.");*/
}
	
function newBillableLink($day, $client, $provider, $packageid)  {
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	if($roDispatcher) return '';
	$day = date('Y-m-d', strtotime($day));
	$providerptr = $provider && is_array($provider) ? $provider['providerptr'] : $provider;
	$provArg = "var prov = document.getElementById(\"primaryProvider\"); prov = (prov && prov.selectedIndex) ? prov.options[prov.selectedIndex].value : null;prov = prov ? prov : \"$providerptr\";";
	return "<img src='art/ez-add.gif' style='cursor:pointer' title='Add a visit or surcharge to his day.' 
						onclick='$provArg"."addBillable(\"$day\", $packageid, $client, prov)'>";
	
}
	
function newSurchargeLink($day, $client, $provider, $packageid)  {
	if(!$_SESSION['surchargesenabled']) return;

	$noWay = userRole() == 'c' || userRole() == 'p' || (userRole() == 'd' && !strpos($_SESSION['rights'], '#ev'));
	if($roDispatcher) return '';
	$day = date('Y-m-d', strtotime($day));
	return fauxLink("Add Surcharge",
									"openConsoleWindow(\"editappt\", \"surcharge-edit.php?date=$day&packageptr=$packageid&clientptr=$client&providerptr=$provider\",530,450 )",
									'noEcho',
									"Create a new surcharge on this day.");
}
	
function deleteButton($appt, $surcharge=null) {
	global $undeletableAppointments, $undeletableSurcharges;
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	if($roDispatcher) return '';
	if($appt['completed']) return '';
	if(!$surcharge && $undeletableAppointments && in_array($appt['appointmentid'], $undeletableAppointments)) {
		$imgsrc = 'smiley.gif';
		$bTitle = 'This visit cannot be deleted.';
		$onClick="alert(\"Sorry, but this visit is now associated with client or sitter payments, or is a current recurring visit.\\nIt can no longer be deleted.\")";
		$style = "style='border:solid black 0px;'";
	}
	else if($surcharge && $undeletableSurcharges && in_array($appt['appointmentid'], $undeletableSurcharges)) {
		$imgsrc = 'smiley.gif';
		$bTitle = 'This surcharge cannot be deleted.';
		$onClick="alert(\"Sorry, but this surcharge is now associated with client or sitter payments.\\nIt can no longer be deleted.\")";
		$style = "style='border:solid black 0px;'";
	}
	else {
		$imgsrc = 'darkx.gif';
		if($surcharge) {
			$bTitle = 'Delete this surcharge entirely.';
			$onClick="deleteSurcharge({$appt['surchargeid']})";
		}
		else {
			$bTitle = 'Delete this visit entirely.';
			$onClick="deleteAppt({$appt['appointmentid']})";
		}
	}
	return "<img class='apptcontrols' $style onClick='$onClick' title='$bTitle' src='art/$imgsrc'>";
}

function cancelUncancelButton($appt, $surcharge=null, $requestOnly='0') {
	$requestOnly = $requestOnly ? '1' : '0';
	$bTitle = $surcharge ? 'surcharge' : 'visit';
	if($appt['canceled']) {
		$cancelArg = 0;
		$imgsrc = 'undelete.gif';
		$bTitle = "Uncancel this $bTitle.";
	}
	else {
		$cancelArg = 1;
		$imgsrc = 'delete.gif';
		$bTitle = "Cancel this $bTitle.";
	}
	$operation = $surcharge ? 'cancelSurcharge' : 'cancelAppt';
	$victim = $surcharge ? $appt['surchargeid'] : $appt['appointmentid'];
	return "<img class='apptcontrols' onClick='$operation($victim, $cancelArg, $requestOnly)'
														title='$bTitle' height=10 width=10 border=1 bordercolor=darkgray src='art/$imgsrc'>";
}
