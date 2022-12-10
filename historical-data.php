<?
// historical-data.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "client-fns.php";
require_once "time-framer-mouse.php";

// Determine access privs
$locked = locked('o-');

$max_rows = 100;

extract($_REQUEST);

//$activeProviderSelections = array_merge(array('--Select a Client--' => '', '--Unassigned--' => -1), getActiveProviderSelections());

$dateSearch = "SELECT date FROM tblhistoricaldata ".($client ? "WHERE clientptr = $client" : "");
$firstDate = fetchRow0Col0("$dateSearch ORDER BY date ASC LIMIT 1"); 
$lastDate = fetchRow0Col0("$dateSearch ORDER BY date DESC LIMIT 1"); 


$appts = array();
if($client) $clientName = getOneClientsDetails($client);
else if($lastclient) $clientName = getOneClientsDetails($lastclient);
if($clientName) $clientName = $clientName['clientname'];
if(true) {
	$found = getClientAppointmentCountAndQuery(dbDate($starting), dbDate($ending), $sort, $client, $offset, $max_rows);
	list($numFound, $query, $numApptsFound, $totalRevs) = explode('|', $found);
	$appts = $numFound ? fetchAssociations($query) : array();
//echo $query.'<p>';
//for($i=0;$i<20;$i++)  echo print_r($appts[$i], 1).'<br>';exit;
	
	if($appts) {
		$appt = current($appts);
		$firstDateShown = $appt['date'];
		$lastDateShown = $dateFirst;
		foreach($appts as $appt) {
			if(strcmp($firstDateShown, $appt['date']) > 0) $firstDateShown = $appt['date'];
			if(strcmp($lastDateShown, $appt['date']) < 0) $lastDateShown = $appt['date'];
		}
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
}
$searchResults = ($numApptsFound ? number_format($numApptsFound) : 'No')." visit".($numApptsFound == 1 ? '' : 's')." found.  ";
if($numApptsFound) $searchResults .= "Total Revenue: ".dollarAmount($totalRevs)."  ";
$searchResults = "<div style='display:inline;border:solid black 1px;background:palegreen'>$searchResults</div>";
$dataRowsDisplayed = min($numFound - $offset, $max_rows);
if($numFound > $max_rows) $searchResults .= " $dataRowsDisplayed rows shown. ";
if($numFound > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('offset'));
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).">Show Next ".min($numFound - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numFound - $numFound % $max_rows).">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
}  

$breadcrumbs = $client ? "<a href='client-edit.php?id=$client&tab=services'>$clientName's Services</a>" : "";


$pageTitle = "Historical Appointments".($client ? " for $clientName" : "");

include "frame.html";
// ***************************************************************************
makeTimeFramer('timeFramer', 'narrow');
echo ($client ? "$clientName's " : "")."Historical Appointments range from ".shortNaturalDate(strtotime($firstDate))." to ".shortNaturalDate(strtotime($lastDate))."<p>";

?>
<form name='clienthistoryform'>
<p style='font-weight:bold;font-size:1.2em;'>
<?   ?>
</p>
<table width=100%><tr>
<td width=15% style='width:15%;font-weight:bold;font-size:1.2em;'></b></td><td width=15%>
<? $url = str_replace('list','cal',$_SERVER['REQUEST_URI']);
   //echoButton('', 'Calendar View', "document.location.href=\"$url\""); ?>
</td>
</tr>
</table>
<p>
<? 
echoButton('', 'Show', 'checkAndSubmit()');
echo " ";
$clientSelections = array('All Clients'=>0);
if($client) $clientSelections[$clientName] = $client;
else if($lastclient) $clientSelections[$clientName] = $lastclient;
selectElement('Client:', "client", $client, $clientSelections);
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
  ?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

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
historicalAppointmentTable($appts, $client);

if($dataRowsDisplayed < 5) { ?>
<div style='height:100px;'></div>
<?
}
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
setPrettynames('client','Client','starting','Starting Date','ending', 'Ending Date');	

function checkAndSubmit() {
  if(MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var client = document.clienthistoryform.client.value;
		var lastclient = '<?= $lastclient ? $lastclient : $client ?>';
		var starting = document.clienthistoryform.starting.value;
		var ending = document.clienthistoryform.ending.value;
		//var summaryOnly = document.getElementById('summaryOnly').checked;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		//summaryOnly = summaryOnly ? '&summaryOnly=1' : '';
    document.location.href='historical-data.php?client='+client+starting+ending+'&lastclient='+lastclient;//+summaryOnly;
	}
}


<?
dumpPopCalendarJS();
dumpTimeFramerJS('timeFramer');

?>

</script>

<?
include "js-refresh.php";

// ***************************************************************************

include "frame-end.html";

function dateSort($a, $b) {
	global $clients;
	$result = strcmp($a['time24'], $b['time24']);
	if(!$result) {
		$result = strcmp($clients[$a['clientptr']], $clients[$b['clientptr']]);
	}
	if(!$result) {
		$a = isset($a['appointmentid']) ? '1' : 2;
		$b = isset($b['appointmentid']) ? '1' : 2;
		$result = strcmp($a, $b);
	}
	return $result;
}

function getClientAppointmentCountAndQuery($starting, $ending, $sort=null, $client, $offset, $max_rows, $filterANDPhrase='', $joinPhrase='', $additionalCols='') {
	// $filterANDPhrase - e.g., "AND canceled IS NULL";
	$clientClause = !$client ? '1=1' : 
										(is_array($client) ? "appt.clientptr IN (".join(',', $client).")" : "clientptr = $client");
	$clientClause = str_replace('-1', '0', $clientClause);
	if($sort) {
		$extraFields = "";
		$joinClause = "";
		$sort_key = substr($sort, 0, strpos($sort, '_'));
		$sort_dir = substr($sort, strpos($sort, '_')+1);
		if($sort_key == 'clientname') 
			$orderClause = "ORDER BY clientname $sort_dir";
		if($sort_key == 'sittername') 
			$orderClause = "ORDER BY sittername $sort_dir";
		else if($sort_key == 'date') 
			$orderClause = "ORDER BY date $sort_dir, time24 ASC";
		else if($sort_key == 'time') 
			$orderClause = "ORDER BY time24 $sort_dir, date ASC";
		else if($sort_key == 'amount') 
			$orderClause = "ORDER BY amount $sort_dir";
		else if($sort_key == 'workorderid') 
			$orderClause = "ORDER BY workorderid $sort_dir";
		else if($sort_key == 'appointmentid') 
			$orderClause = "ORDER BY appointmentid $sort_dir";
		else if($sort_key == 'service') {
			$orderClause = "ORDER BY servicelabel $sort_dir, date ASC, starttime ASC";
		}
		else $orderClause = "ORDER BY $sort_key $sort_dir";
	}
	
//	echo "[$sort_key] [$sort_dir] [$orderClause]<p>";	

	if($joinPhrase) $joinClause .= $joinPhrase;
	if($additionalCols) $extraFields .= ", $additionalCols";
	else if(!$orderClause) $orderClause = 'ORDER BY date ASC, time24 ASC';
	$startingPhrase = $starting ? "AND date >= '$starting'" : '';
	$endingPhrase = $ending ? "AND date <= '$ending'" : '';
	$includeClientName = $client ? '' : "clientname, ";
	$visitsOnly = fetchFirstAssoc("SELECT count(*) as num, sum(amount) as sum FROM tblhistoricaldata appt WHERE $clientClause $startingPhrase $endingPhrase $filterANDPhrase AND servicepaymentcode <> 'PSPMT'");

	$sql = "SELECT $includeClientName appointmentid, appt.clientptr, appt.clientptr, servicepaymentcode, appt.workorderid, date, appt.time, time24, a1a3code, s1code, originalclientid,
	        ifnull(servicelabel, servicepaymentcode) as servicelabel, sittername, amount, if(servicepaymentcode = 'PSPMT',1,0) as payment, note $extraFields
					FROM tblhistoricaldata appt $joinClause WHERE $clientClause $startingPhrase $endingPhrase $filterANDPhrase $orderClause";
	$numFound = fetchRow0Col0("SELECT count(*) FROM tblhistoricaldata appt WHERE $clientClause $startingPhrase $endingPhrase $filterANDPhrase");

	if($offset) {
		$offset = min($offset, $numFound - 1);
	}
	else $offset = 0;
	$limitClause = $numFound > $max_rows ? "LIMIT $max_rows OFFSET $offset" : '';
//echo "$sql $limitClause";exit;
//echo "[$sort_key] [$sort_dir] [$orderClause]<p>";exit;	

  return "$numFound|$sql $limitClause|{$visitsOnly['num']}|{$visitsOnly['sum']}";
}


function historicalAppointmentTable(&$data, $client=0) {
	$columns = "date|Date||time|Time||servicelabel|Service||amount|Amount||blank|&nbsp;||sittername|Sitter||workorderid|Work Order #||appointmentid|Visit #";
	if(!$client) $columns = "clientname|Client||".$columns;
	$columns = explodePairsLine($columns);
  $sortableColsString = 'clientname|sittername|date|time|servicelabel|amount|workorderid|appointmentid';
  foreach(explode('|', $sortableColsString) as $col) $sortableCols[$col] = null;
	$numCols = count($columns);
	foreach($data as $row) {
		if($row['payment']) $row['servicelabel'] = 'Payment';
		else if($row['servicelabel']) $row['servicelabel'] = $row['servicelabel'];
		else $row['servicelabel'] = $row['servicepaymentcode'];
		$rowClasses[] = ($rowClass= $row['payment'] ? 'paymenttask' : 'futuretask');
		$row['date'] = shortDate(strtotime($row['date']));
		$row['amount'] = dollarAmount($row['amount']);
		$row['blank'] = '&nbsp;';
		$rows[] = $row;
		if($row['note']) {
			$rows[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td style='padding-left:10px;' colspan=".($numCols).">Note: ".trim($row['note'])."</td></tr>");
			$rowClasses[] = $rowClass;
		}
	}
	tableFrom($columns, $rows, 'WIDTH=100% ',null,$headerClasses,null,null,$sortableCols,$rowClasses, array('amount'=>'rightAlignedTD'));
}
?>
