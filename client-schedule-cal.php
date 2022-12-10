<?
// client-schedule-cal.php
// for inclusion in the client editor services tab
require_once "client-schedule-fns.php";
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "day-calendar-fns.php";
require_once "pet-fns.php";

// Determine access privs
//$locked = locked('o-');


extract($_REQUEST);

if($_SESSION['clientid']) $client = $_SESSION['clientid'];  // discourage snooping

$max_rows = $limit = -1 ? 9999 : 100;

$appts = array();

$_SESSION['clientScheduleDateRange'] = dbDate($starting).'|'.dbDate($ending);

$found = getClientAppointmentCountAndQuery(dbDate($starting), dbDate($ending), 'date_ASC', $client, $offset, $max_rows, 'includearrivaltimes');
$numFound = 0+substr($found, 0, strpos($found, '|'));
$query = substr($found, strpos($found, '|')+1);

//$appts = $numFound ? fetchAssociations($query) : array();
$appts = $numFound ? fetchAssociationsKeyedBy($query, 'appointmentid') : array();
$appts = array_values($appts);

$originalServiceProviders = originalServiceProviders($appts);

foreach($appts as $key => $appt) {
	if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
		if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
			$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];
	if($appt['canceled']) $canceledCount++;
}

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
	
$searchResults = ($numFound ? $numFound : 'No')." visit".($numFound == 1 ? '' : 's')." found in date range.  ";
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

if(!(isset($displayOnly) && $displayOnly)) {
?>
<form name='clientschedform'>
<table><tr><td valign=top><?
hiddenElement('client', $client);

if(userRole() != 'c') echo "<img width=24 height=15 src='art/email-message-trimmed.gif' title='Email a calendar for these dates.' style='cursor:pointer;margin-left:5px;;margin-right:10px;' onclick='emailCalendar()'>";
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
echo "&nbsp;";
calendarSet('ending:', 'ending', $ending);
echo " ";
echoButton('showAppointments', 'Show', 'searchForAppointments(true)');

echo "<p id='pending-requests' style='text-align:right;font-size:1.5em'></p>";  // updated by JSON ajax-pending-requests.php

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
if(userRole() != 'c' && !$omitRevenue) echo " Revenue: ".dollarAmount($revenue)." Commission: ".dollarAmount($commission);
?>
</td>
<?
	if(userRole() == 'c') {
		echo "<td align=right width=205>";
		echoPetPhotosDiv($_SESSION['clientid']);
		echo "</td>";
	}
?>
</tr></table>
</form>
<?
}

$SELECTTESTMODE = $_SESSION['preferences']['enableclientuimultidaycancel'];

if($SELECTTESTMODE) {
	$extraSearchResultsContent .= "<table><tr><td>"
		.echoButton('', "Cancel Selected Visits", "generateScheduleChangeRequest(\"cancel\")", null, null, 'noEcho', 'Click here to cancel selected visits.')
		."</td><td>"
		.echoButton('', "UnCancel Selected Visits", "generateScheduleChangeRequest(\"uncancel\")", null, null, 'noEcho', 'Click to uncancel selected visits.')
		."</td><td>"
		.echoButton('', "Change Selected Visits", "generateScheduleChangeRequest(\"change\")", null, null, 'noEcho', 'Click here to make changes to selected visits.')
		."</td></tr>"
		."</table>";
	
} else

if($_SESSION['preferences']['offerSimpleMultiDayChangeCancelRequestForm']) 
	$extraSearchResultsContent .= "&nbsp;&nbsp;".
		echoButton('', "Cancel/Change Multiple Visits", "document.location.href=\"client-own-multiday-change-request.php\"", null, null, 'noEcho', 'Click here when you want to change multiple visits.');


// $extraSearchResultsContent may be a "Cancel/Change Multiple Visits" button
echo "<table><tr><td style='padding-right:5px;'>$searchResults$extraSearchResultsContent</td>";  

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
clientCalendarTable($appts);