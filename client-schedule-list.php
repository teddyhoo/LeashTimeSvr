<?
// client-schedule-list.php
// for inclusion in the client editor services tab
require_once "client-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";

// Determine access privs
//$locked = locked('o-');

extract($_REQUEST);

if($_SESSION['clientid']) $client = $_SESSION['clientid'];  // discourage snooping

$max_rows = $limit = -1 ? 9999 : 100;

$appts = array();

$_SESSION['clientScheduleDateRange'] = dbDate($starting).'|'.dbDate($ending);


$found = getClientAppointmentCountAndQuery(dbDate($starting), dbDate($ending), $sort, $client, $offset, $max_rows);
$numFound = 0+substr($found, 0, strpos($found, '|'));
$query = substr($found, strpos($found, '|')+1);
//$appts = $numFound ? fetchAssociations($query) : array();
$appts = $numFound ? fetchAssociationsKeyedBy($query, 'appointmentid') : array(); // JOIN WITH tblgeotrack can create dups
$appts = array_values($appts);
$originalServiceProviders = originalServiceProviders($appts);

$firstDateShown = $starting;
if($appts) foreach($appts as $key => $appt) {
	if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
		if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
			$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];
	$firstDateShown = $starting;
	$lastDateShown = $starting;
	//foreach($appts as $appt) {
		if(strcmp($firstDateShown, $appt['date']) > 0) $firstDateShown = $appt['date'];
		if(strcmp($lastDateShown, $appt['date']) < 0) $lastDateShown = $appt['date'];
	//}
	if($appt['canceled']) $canceledCount++;
}

		
$surcharges = array();
$date0 = dbDate($starting);
$date1 = dbDate($ending);
if($_SESSION['surchargesenabled'])
		$surcharges = fetchAssociations($sql =
		"SELECT * 
			FROM tblsurcharge 
			WHERE date >= '$date0' AND 
						date <= '$date1' AND 
						clientptr = $client");
$rows = array_merge($appts, $surcharges);

$nextButton = false;
$prevButton = false;
$firstPageButton = false;
$lastPageButton = false;
if($numFound > $max_rows) {
	if($offset > 0) {
		$prevButton = true;
		$firstPageButton = true;
	}
	if($numFound - $offset > $max_rows) {
		$nextButton = true;
		$lastPageButton = true;
	}
}
	
$searchResults = ($numFound ? $numFound : 'No')." visit".($numFound == 1 ? '' : 's')." found.  ";
if($canceledCount) $searchResults .= $canceledCount.($canceledCount == 1 ? ' is' : ' are')." canceled.  ";
$dataRowsDisplayed = min($numFound - $offset, $max_rows);
if($numFound > $max_rows) $searchResults .= "$dataRowsDisplayed visits shown. ";
if($numFound > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('offset'));
	$pagingAction =   isset($targetdiv) ? "ajaxGet(\"$baseUrl"."offset=XXX\", \"$targetdiv\")" : "document.location.href=\"$baseUrl"."offset=XXX\"";
	if($prevButton) {
		$prevButton = fauxLink("Show Previous $max_rows", str_replace('XXX', $offset - $max_rows, $pagingAction), 1);
		$firstPageButton = fauxLink("Show First Page", str_replace('XXX', 0, $pagingAction), 1);
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = fauxLink("Show Next ".min($numFound - $offset, $max_rows), str_replace('XXX', min($numFound - $offset, $max_rows), $pagingAction), 1);
		$lastPageButton = fauxLink("Show Last Page", str_replace('XXX', ($numFound - $numFound % $max_rows), $pagingAction), 1);
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
}  

?>
<form name='clientschedform'>
<?
hiddenElement('client', $client);
require_once "invoice-fns.php";
$accountBal = getAccountBalance($client, $includeCredits=true, $allBillables=false);
$accountBal = $accountBal ? dollarAmount(abs($accountBal)).($accountBal < 0 ? 'cr' : '') : 'PAID';
hiddenElement('clientscheduleaccountbalance', $accountBal);

if(userRole() != 'c') {
	echo "<img width=24 height=15 src='art/email-message-trimmed.gif' title='Email a calendar for these dates.' style='cursor:pointer;margin-left:5px;;margin-right:10px;' onclick='emailCalendar()'>";
	// if user has invoicing rights
	if(adequateRights('#ac')) {
		echo "<!-- look in client-schedule-list.php -->";
		if($_SESSION['preferences']['betaBilling2Enabled']) 
			echo "<img width=24 height=15 src='art/email-invoice.gif' bordercolor=red border=1 title='Email a BETA 2 invoice for these dates.' style='cursor:pointer;margin-left:5px;;margin-right:10px;' onclick='viewBillingStatement()'>";
		else {
			echo "<img width=24 height=15 src='art/email-invoice.gif' title='Email an invoice for these dates.' style='cursor:pointer;margin-left:5px;;margin-right:10px;' onclick='viewServicesInvoice()'>";
			if(staffOnlyTEST() || $_SESSION['preferences']['betaBillingEnabled']) 
				echo "<img width=24 height=15 src='art/email-invoice.gif' bordercolor=red border=1 title='Email a BETA invoice for these dates.' style='cursor:pointer;margin-left:5px;;margin-right:10px;' onclick='viewBillingServicesInvoice()'>";
		}
	}
}
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
echo "&nbsp;";
calendarSet('ending:', 'ending', $ending);
echo " ";
echoButton('showAppointments', 'Show', 'searchForAppointments(false)');

if(userRole() != 'c') { 
	$firstDayThisMonthInt = strtotime(date('Y-m').'-1');
	foreach(array(
								'Last Week'=>'Last Week',
								'This Week'=>'This Week',
								'Next Week'=>'Next Week', 
								date("M", strtotime("- 1 month", $firstDayThisMonthInt))=>'Last Month',
								date("M")=>'This Month',
								date("M", strtotime("+ 1 month", $firstDayThisMonthInt))=>'Next Month') 
					as $label => $val) {
		if($subseqentLink) echo " - ";
		else echo " ";
		$subseqentLink = 1;
		fauxLink($label, "showInterval(\"$val\")");
	}
}

$revenue = 0;
$commission = 0;
foreach($appts as $appt) {
	if(!$appt['canceled']) {
		$revenue += $appt['charge'] + $appt['adjustment'];
		$commission += $appt['rate'] + $appt['bonus'];
//echo "{$appt['date']} Rate: {$appt['rate']} + Bonus: {$appt['bonus']} = $commission<p>";
	}
}
if($surcharges) foreach($surcharges as $surcharge) {
	if(!$surcharge['canceled']) {
		$revenue += $surcharge['charge'];
		$commission += $surcharge['rate'] + $appt['bonus'];
	}
}
require_once "preference-fns.php";
$omitRevenue = getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay');  // && !$omitRevenue
if(userRole() != 'c' && !$omitRevenue) $revsAndCommissions = " Revenue: ".dollarAmount($revenue)." Commission: ".dollarAmount($commission);
?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults $revsAndCommissions</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "</tr></table>";
?>

<p>
<?
$sort_key = substr($sort, 0, strpos($sort, '_'));
$showPayments = userRole() != 'c' && $_SESSION['preferences']['includePaymentsInVisitList'];
if($showPayments && (!$sort_key || $sort_key == 'date' || $sort_key == 'time')) {
		$startingPhrase = $starting ? "AND issuedate >= '".dbDate($starting)."'" : '';
		$endingPhrase = $ending ? "AND issuedate <= '".dbDate($ending)."'" : '';
		
		$hideSystemCredits = 
			staffOnlyTEST() ? '' 
			: " AND (payment = 1 OR reason IS NULL OR (reason NOT LIKE '%billable%' AND reason NOT LIKE '%(v: %b: %'))";
		
		$payments = fetchAssociations(
			"SELECT amount,  CONCAT(issuedate, ' 00:00:00') as datetime, issuedate as date, payment, clientptr 
				FROM tblcredit 
					WHERE  clientptr = $client $startingPhrase $endingPhrase $hideSystemCredits");
		$rows = array_merge($rows, $payments);
}

foreach($rows as $i => $row) $rows[$i]['datetime'] = $row['date'].' '.$row['starttime'];
usort($rows, 'datetimeSort');
$sort_dir = substr($sort, strpos($sort, '_')+1);
if(strtoupper($sort_dir) == 'DESC') $rows = array_reverse($rows);

//print_r($rows);
$suppressedCols = $omitRevenue ? array('charge') : null;
if(getUserPreference($_SESSION['auth_user_id'], 'showrateinclientschedulelist')
		&& adequateRights('#pa')) $SHOW_RATE_AS_WELL = true;  // global for this script

clientScheduleTable($rows, $suppressedCols, 'useInPlaceEditors');

function datetimeSort($a, $b) {
	$result = strcmp($a['datetime'], $b['datetime']);
	if(!$result) {
		$a = isset($a['appointmentid']) ? '1' : 2;
		$b = isset($b['appointmentid']) ? '1' : 2;
		$result = strcmp($a, $b);
	}
	return $result;
}


/*
List View		 	

Starting:   ending:   Revenue: $ 60.00 Commission: $ 24.00
3 appointments found. 	
			
	

Array ( [appointmentid] => 36326 [clientptr] => 4 [providerptr] => 36 [serviceptr] => 3548 [date] => 2009-09-22 [timeofday] => 1:00 pm-3:00 pm [starttime] => 13:00:00 [endtime] => 15:00:00 [canceled] => [completed] => [servicecode] => 1 [highpriority] => [pets] => Theo [rate] => 8.00 [charge] => 20.00 [pendingchange] => [note] => [datetime] => 2009-09-22 13:00:00 )

Array ( [appointmentid] => 36327 [clientptr] => 4 [providerptr] => 36 [serviceptr] => 3548 [date] => 2009-09-24 [timeofday] => 1:00 pm-3:00 pm [starttime] => 13:00:00 [endtime] => 15:00:00 [canceled] => [completed] => [servicecode] => 1 [highpriority] => [pets] => Theo [rate] => 8.00 [charge] => 20.00 [pendingchange] => [note] => [datetime] => 2009-09-24 13:00:00 )

Array ( [appointmentid] => 36328 [clientptr] => 4 [providerptr] => 36 [serviceptr] => 3548 [date] => 2009-09-29 [timeofday] => 1:00 pm-3:00 pm [starttime] => 13:00:00 [endtime] => 15:00:00 [canceled] => [completed] => [servicecode] => 1 [highpriority] => [pets] => Theo [rate] => 8.00 [charge] => 20.00 [pendingchange] => [note] => [datetime] => 2009-09-29 13:00:00 )

Array ( [amount] => 180.00 [datetime] => 2009-09-21 00:00:00 [date] => 2009-09-21 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 180.00 [datetime] => 2009-09-24 00:00:00 [date] => 2009-09-24 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 180.00 [datetime] => 2009-09-24 00:00:00 [date] => 2009-09-24 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 180.00 [datetime] => 2009-09-24 00:00:00 [date] => 2009-09-24 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 180.00 [datetime] => 2009-09-24 00:00:00 [date] => 2009-09-24 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 180.00 [datetime] => 2009-09-24 00:00:00 [date] => 2009-09-24 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 200.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 1000.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 160.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 5.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 5.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 5.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

Array ( [amount] => 5.00 [datetime] => 2009-09-25 00:00:00 [date] => 2009-09-25 [payment] => 1 [clientptr] => 4 )

*/