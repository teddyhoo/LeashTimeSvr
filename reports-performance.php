<?  // reports-performance.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "prov-schedule-fns.php";
require_once "client-fns.php";
require_once "pet-fns.php";
require_once "preference-fns.php";
require_once "google-map-utils.php";

// Determine access privs
$locked = locked('d-');

$max_rows = 100;
extract($_REQUEST);


$showPerformanceIcons = staffOnlyTEST() || $_SESSION['preferences']['enableVisitPerformanceIcons'];
if(mattOnlyTEST()) $showPerformanceIcons = true;
if($showPerformanceIcons) {
	$performanceFilter = $performanceFilter ? json_decode($performanceFilter, 'assoc') : $performanceFilter;
}

if(!$starting && !$ending) {
	if($_SESSION['reports-performance-starting']) {
		$starting = $_SESSION['reports-performance-starting'];
		$ending = $_SESSION['reports-performance-ending'];
	}
	else if($thisweek) {
		$starting = shortDate();
		$ending = shortDate(strtotime("+ 6 days"));
	}
	else {
		$starting = shortDate();
		$ending = shortDate();
	}
}
$_SESSION['reports-performance-starting'] = $starting;
$_SESSION['reports-performance-ending'] = $ending;

$allProviderSelections = array_merge(array('--Select a Sitter--' => '-2', '--All Sitters--' => 0, '--Unassigned--' => -1), getActiveProviderSelections());
if($inactiveSitters = getProviderSelections($availabilityDate=null, $zip=null, $status='inactive'))
	$allProviderSelections['Inactive Sitters'] = $inactiveSitters;

$appts = array();
if($provider == -1) $providerName = 'Unassigned';
else if($provider)
	$providerName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $provider");
if($provider != -2) {
// getProviderAppointmentCountAndQuery($starting, $ending, $sort=null, $provider, $offset, $max_rows, $filterANDPhrase='', $joinPhrase='', $additionalCols='') {
	$max_rows = $csv ? -1 : $max_rows;
	$found = getProviderAppointmentCountAndQuery(dbDate($starting), dbDate($ending), $sort, $provider, $offset, $max_rows, "AND canceled IS NULL");
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "$found";exit;}	
	$numFound = 0+substr($found, 0, strpos($found, '|'));
	$query = substr($found, strpos($found, '|')+1);
	$appts = $numFound ? fetchAssociationsKeyedBy($query, 'appointmentid') : array();
	
	if($appts) {
		$appt = current($appts);
		$firstDateShown = $appt['date'];
		$lastDateShown = $dateFirst;
		foreach($appts as $appt) {
			if(strcmp($firstDateShown, $appt['date']) > 0) $firstDateShown = $appt['date'];
			if(strcmp($lastDateShown, $appt['date']) < 0) $lastDateShown = $appt['date'];
		}
	}

	if($appts) {
		foreach($appts as $key => $appt)
			$apptClientids[] = $appt['clientptr'];
		$apptClientids = array_unique($apptClientids);
		$clients = fetchAssociationsKeyedBy("SELECT * FROM tblclient WHERE clientid IN (".join(',', $apptClientids).")", 'clientid');
		foreach($clients as $clientid => $client) {
			$googleAddr = googleAddress($client);
			if($googleAddr) $clientHomes[$clientid] = getLatLon($googleAddr);
		}
	}
	else {
		$apptClientids = array();
		$clientHomes = array();
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

$searchResults = ($numFound ? $numFound : 'No')." appointment".($numFound == 1 ? '' : 's')." found.  ";
$dataRowsDisplayed = min($numFound - $offset, $max_rows);
if($numFound > $max_rows) $searchResults .= "$dataRowsDisplayed appointments shown. ";
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

if($appts) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$usernames = fetchKeyValuePairs(
		"SELECT userid, CONCAT_WS(' ', fname, lname)
			FROM tbluser
			WHERE bizptr = {$_SESSION['bizptr']}
				AND (rights LIKE 'o-%' OR rights LIKE 'd-%')", 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	$serviceLabels = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	$providersbyuserid = fetchAssociationsKeyedBy(
			"SELECT userid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name, providerid
				FROM tblprovider", 'providerid');
	foreach($providersbyuserid as $providerid => $prov) {
		$usernames[$prov['userid']] = $prov['name'];
		$providerNames[$providerid] = $prov['name'];
	}



if(FALSE /*!(staffOnlyTEST() || dbTEST('followyournosehere'))*/) { // Abandoned due to memory overuse with large datasets
	$sql = "SELECT * FROM tblgeotrack WHERE appointmentptr IN (".join(',', array_keys($appts)).")";
//if(mattOnlyTEST()) echo "BOINK geotracks:".fetchRow0Col0("SELECT count(*) FROM tblgeotrack WHERE appointmentptr IN (".join(',', array_keys($appts)).")",1)."<hr>";
	$events = fetchAssociationsIntoHierarchy($sql, array('appointmentptr', 'event'));
}
//if(mattOnlyTEST()) {print_r(fetchAssociations($sql));exit;}
	$clientNames = fetchKeyValuePairs(
		"SELECT clientid, CONCAT_WS(', ', lname, fname) 
			FROM tblclient
			WHERE clientid IN (".join(',', $apptClientids).")");
	foreach($clientNames as $id => $unused)
		$petNamesByClient[$id] = petNamesCommaList((array)getClientPetNames($id), $maxLength=40);
	foreach($appts as $apptid => $appt) {
		
			if(TRUE /*staffOnlyTEST() || dbTEST('followyournosehere')*/) {// Introduced to reduce memory usage with large datasets
				$sql = "SELECT * FROM tblgeotrack WHERE appointmentptr = $apptid AND event in ('arrived', 'completed')";
			//if(mattOnlyTEST()) echo "BOINK geotracks:".fetchRow0Col0("SELECT count(*) FROM tblgeotrack WHERE appointmentptr IN (".join(',', array_keys($appts)).")",1)."<hr>";
				$events = fetchAssociationsIntoHierarchy($sql, array('appointmentptr', 'event'));
				$sql = "SELECT count(*) FROM tblgeotrack WHERE appointmentptr = $apptid"; //  AND event = 'mv'
				$movements = fetchRow0Col0($sql);
			}
		
		
		
		$appts[$apptid]['arrived'] = $events[$apptid]['arrived'][0]['date'];
		$appts[$apptid]['arrivedname'] = $usernames[$events[$apptid]['arrived'][0]['userptr']];
		if($events[$apptid]['arrived'][0])
			$appts[$apptid]['deltaArrived'] = deltaInMeters($events[$apptid]['arrived'][0], $appt['clientptr']);
//if(mattOnlyTEST())		{print_r($events[$apptid]['arrived'][0]); echo "delta: [{$appts[$apptid]['deltaArrived']}]"; exit;}
		if($events[$apptid]['completed'][0])
			$appts[$apptid]['deltaCompleted'] = deltaInMeters($events[$apptid]['completed'][0], $appt['clientptr']);
		$appts[$apptid]['eventcount'] = $movements;
//if(mattOnlyTEST()) {echo print_r($movements,1);exit;}
		$appts[$apptid]['accuracyArrived'] = round($events[$apptid]['arrived'][0]['accuracy']);
		$appts[$apptid]['accuracyCompleted'] = round($events[$apptid]['completed'][0]['accuracy']);
		$appts[$apptid]['client'] = $clientNames[$appt['clientptr']];
		$pets = $appt['pets'] == 'All Pets' ? $petNamesByClient[$appt['clientptr']] : $appt['pets'];
		$pets = $pets ? $pets : 'no pets';
		$appts[$apptid]['client'] .= "<br>($pets)";
		$appts[$apptid]['provider'] = $providerNames[$appt['providerptr']];
		$appts[$apptid]['service'] = $serviceLabels[$appt['servicecode']];
		if(staffOnlyTEST() && !$csv) $appts[$apptid]['service'] = 
			fauxLink($appts[$apptid]['service'], "openConsoleWindow(\"zzz\", \"appointment-edit.php?id=$apptid\", 600, 700);", 1, 'Open visit editor');
		if($events[$apptid]['completed'][0]['date']) { // don't clear info unnecessarily
			$appts[$apptid]['completedmobile'] = 1;
			$appts[$apptid]['completed'] = $events[$apptid]['completed'][0]['date'];
			$appts[$apptid]['completedname'] = $usernames[$events[$apptid]['completed'][0]['userptr']];
		}
		if($appts[$apptid]['arrived'] && $appts[$apptid]['completed']) {
			$appts[$apptid]['actualduration'] = strtotime($appts[$apptid]['completed']) - strtotime($appts[$apptid]['arrived']);
			$appts[$apptid]['actualduration'] = gmdate('H:i', $appts[$apptid]['actualduration'])/*." [{$appts[$apptid]['arrived']}] [{$appts[$apptid]['completed']}] (".(strtotime($appts[$apptid]['completed'])-strtotime($appts[$apptid]['arrived'])).")"*/;
		}
		else $appts[$apptid]['actualduration'] = '--';
			
//if($appts[$apptid]['arrived']) {echo print_r($appts[$apptid],1).'<hr>'.print_r($events,1).'<hr>'.print_r($usernames,1);exit;}
	}
	sortAgain($appts);
}

function deltaInMeters($track, $clientptr) {
	global $map, $clientHomes;
//if(mattOnlyTEST()) echo "$clientptr: {$clientHomes[$clientptr]} <br>".print_r($clientHomes, 1);echo "<br";; 		
	if(($homeLoc = $clientHomes[$clientptr])
			&& !$track['error']) {
		//$delta = round($map->geoGetDistance($track['lat'],$track['lon'],$homeLoc['lat'],$homeLoc['lon'],$unit='K')*1000);	
		$delta = round(haversineGreatCircleDistance($track['lat'],$track['lon'],$homeLoc['lat'],$homeLoc['lon']));
		return $delta ? $delta : '0';
	}
	return -1;
}


/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversineGreatCircleDistance(
  $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}


function sortAgain(&$rows) {
	global $sort;
	$parts = explode('_', ($sort ? $sort : 'date_ASC'));
	if($parts[0] == 'date') usort($rows, 'dateTimesInOrder');
	else if($parts[0] == 'time')  usort($rows, 'timeWindowsInOrder');
	else if($parts[0] == 'service')  usort($rows, 'servicesInOrder');
	else if($parts[0] == 'client')  usort($rows, 'clientsInOrder');
	if(strtoupper($parts[1]) == "DESC") {
		$rev = array_reverse($rows);
		foreach($rows as $i=>$v) $rows[$i] = $rev[$i];
	}
}

function clientsInOrder($a, $b) {
	global $clientNames;
	if(!$clientNames) $clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(', ', lname, fname) FROM tblclient");
	if($clientNames[$a['clientptr']] < $clientNames[$b['clientptr']]) return -1;
	else if($clientNames[$a['clientptr']] > $clientNames[$b['clientptr']]) return 1;
	else return dateTimesInOrder($a, $b);
}
	
function servicesInOrder($a, $b) {
	if(serviceName($a) < serviceName($b)) return -1;
	else if(serviceName($a) > serviceName($b)) return 1;
	else return dateTimesInOrder($a, $b);
}
	
function dateTimesInOrder($a, $b) {
	if($a['date'] < $b['date']) return -1;
	if($a['date'] > $b['date']) return 1;
	if($a['date'] == $b['date']) {
		if($a['starttime'] < $b['starttime']) return -1;
		if($a['starttime'] > $b['starttime']) return 1;
		return 0;
	}
}

function timeWindowsInOrder($a, $b) {
	if($a['starttime'] < $b['starttime']) return -1;
	else if($a['starttime'] > $b['starttime']) return 1;
	else {
		if($a['date'] < $b['date']) return -1;
		if($a['date'] > $b['date']) return 1;
		return 0;
	}
}

$pageTitle = "Visit Performance (Arrivals/Completions)";
if($csv) {
	$columns = 'date| ||';
	$collabels = 'date|Date||';
}
$columns = explodePairsLine($columns."timeofday| ||client| ||service| ||provider| ||arrivedtime| ||completedtime| "); // ||completedname| 
$collabels = explodePairsLine($collabels."timeofday|Time||client|Client||service|Service||provider|Sitter||arrivedtime|Arrived||completedtime|Completed"); // ||completedname|Marked Complete By

if($provider) {
	unset($columns['provider']);
	unset($collabels['provider']);
}
if(TRUE || staffOnlyTEST()) {
	$columns['actualduration'] = '';
	$collabels['actualduration'] = 'Visit Time';
}



if($showPerformanceIcons && !$csv) {
/***/if(TRUE) { // !mattOnlyTEST()
	$retainArrivalsAndCompletions = true; // mattOnlyTEST();
	if(!$retainArrivalsAndCompletions) {
		unset($columns['arrivedtime']);
		unset($columns['completedtime']);
		unset($collabels['arrivedtime']);
		unset($collabels['completedtime']);
	}
	unset($columns['actualduration']);
	unset($collabels['actualduration']);
/***/}	
	//$columns['performanceicons'] = ' ';
	//$collabels['performanceicons'] = ' ';
	require_once "visit-performance-fns.php";
}

$DEV = true; //mattOnlyTEST();

if($csv) {
	if($DEV) {
		$collabels['deltaArrived'] = 'Arrival Distance';
		$columns['deltaArrived'] = '';
	}
	$collabels['accuracyArrived'] = 'Arrival accuracy';
	$columns['accuracyArrived'] = '';
	if($DEV) {
		$collabels['deltaCompleted'] = 'Completion Distance';
		$columns['deltaCompleted'] = '';
	}
	$collabels['accuracyCompleted'] = 'Completion accuracy';
	$columns['accuracyCompleted'] = '';
	function dumpCSVRow($row, $cols=null) {
		if(!$row) echo "\n";
		if(is_array($row)) {
			if($cols) {
				$nrow = array();
				if(is_string($cols)) $cols = explode(',', $cols);
				foreach($cols as $k) 
					$nrow[] = $k == 'date' ? shortDate(strtotime($row[$k])) : $row[$k];
				$row = $nrow;
			}
			echo join(',', array_map('csv',$row))."\n";
		}
		else echo csv($row)."\n";
	}

	
	function csv($val) {
	  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
	  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
	  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
		return "\"$val\"";
	}

	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Sitter-performance.csv ");
	dumpCSVRow("Sitter Performance".($providerName ? " for $providerName" : ''));
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $starting - $ending");
	if($DEV) dumpCSVRow("Distance (from client home) is expressed in meters.");
	dumpCSVRow("Accuracy (reported by GPS device) is expressed in meters.  Higher values indicate greater possible errors.");
	dumpCSVRow("Accuracy depends on signal strength and the signal source integrity (LAN, Cell towers, Satellites).");
	dumpCSVRow("");

	if($appts) {
		dumpCSVRow($collabels);
//if(mattOnlyTEST()) echo "<pre>".print_r($appts,1)."</pre>";		
		
		foreach($appts as $appt) {
			$appt['arrivedtime'] = $appt['arrived'] ? date('h:i a', strtotime($appt['arrived'])) : '--';
			if(!$appt['accuracyArrived']) $appt['accuracyArrived'] = '--';
			if(!$appt['accuracyCompleted']) $appt['accuracyCompleted'] = '--';
			
			if($DEV) if($appt['deltaArrived'] < 0) $appt['deltaArrived'] = '--';
			if($DEV) if($appt['deltaCompleted'] < 0) $appt['deltaCompleted'] = '--';
			
			if($appt['completed'] && strcmp(substr($appt['completed'], 0, 10), substr($appt['date'], 0, 10)) != 0)
				$dateDisplay = ' ('.shortestDate(strtotime($appt['completed'])).')';
			else $dateDisplay = '';
			$appt['completedtime'] = $appt['completed'] ? date('h:i a', strtotime($appt['completed'])).$dateDisplay : '--';
			if(!$appt['completedmobile']) $appt['completedtime'] = "Not reported by mobile - {$appt['completedtime']}";
			dumpCSVRow($appt, array_keys($columns));
		}
	}
	else echo "No visits found.";
	exit;
}
else {
	$showVisitReports = TRUE || $_SESSION['preferences']['enableNativeSitterAppAccess'];
	if($showVisitReports) {
		$columns['visitreport'] = '';
		$collabels['visitreport'] = 'Report';
		require_once "appointment-fns.php";
	}
}

$breadcrumbs = "<a href='reports.php'>Reports</a>";
if($provider) $breadcrumbs .= " - <a href='provider-edit.php?id=$provider'>$providerName</a>";



include "frame.html";
// ***************************************************************************
//print_r($appts);
?>
<form name='performanceform' method='POST'>
<p>
<? 
selectElement('Sitter:', "provider", $provider, $allProviderSelections);
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
echo " ";
echoButton('', 'Show', 'checkAndSubmit()');
echo " ";
$url = $_SERVER['REQUEST_URI'];
if(strpos($url, '?')) $url .= '&';
else $url .= '?';
echoButton('', 'Download Spreadsheeet', "checkAndSubmit(\"csv\")");
hiddenElement('performanceFilter', $_REQUEST['performanceFilter']);
if($showPerformanceIcons) echo " <img src='art/help.jpg' onclick='showHelp()' height='20' width='20'>";
?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

if($showPerformanceIcons) {
	$filterDescription = 
		$performanceFilter ? "Filter:\n".safeValue(str_replace('<br>', "\n", $performanceFilter['description'])) 
		: 'Filter visits by performance, etc.';
	$filterIcons= "<img id=\"filterimg\" src=\"art/magnifier.gif\" onclick=\"editFilter()\" title=\"$filterDescription\" style=\"cursor:pointer;\">";
	$displayClearFilter = $_REQUEST['performanceFilter'] ? 'inline' : 'none';
	$filterIcons .=  "&nbsp;<img id=\"clearfilterimg\" src=\"art/magnifier-crossed.gif\" onclick=\"clearFilter()\" title=\"Clear the advanced search filter\" style=\"cursor:pointer;display:$displayClearFilter\">";
	$filterIcons = "<td class='pagingButton' style='width:48px;'>$filterIcons</td>";
}


echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
              $filterIcons
             </tr></table></td>
        <td>";

echo "</tr></table>";

//   DATE ROW
// time  client  sitter  arrived  completed (marked by)
//print_r($appts);
if($appts) {
	//$columns = explodePairsLine("timeofday|Time||client|Client||service|Service||provider|Sitter||arrivedtime|Arrived||completedtime|Completed||completedname|Marked Complete By");
	foreach($appts as $appt) {
//if(dbTEST('tonkapetsitters')) print_r($appts);		
		if($lastDate != $appt['date']) {
			$rowClasses[count($data)] = 'daycalendardaterow';
			$rows[] = array('#CUSTOM_ROW#'=> 
				"<tr><td class='daycalendardaterow' colspan=".count($columns).">".longDayAndDate(strtotime($appt['date']))."</td></tr>\n");
			$rows[] = array('#CUSTOM_ROW#'=> 
				"<tr><th>".join('</th><th>', $collabels)."</th></tr>\n");
		}
		$lastDate = $appt['date'];
		if($showVisitReports) $appt['timeofday'] = briefTimeOfDay($appt);
		
		$props = fetchKeyValuePairs("SELECT property, value FROM tblappointmentprop WHERE appointmentptr = {$appt['appointmentid']}");
		
		$appt['arrivedtime'] = $appt['arrived'] ? date('h:i a', strtotime($appt['arrived'])) : '--';
		if(staffOnlyTEST() && $props['arrived_recd']) $appt['arrivedtime'] .= "<br><i title='Time received (Staff Only)' style='color:purple'>".date('H:i:s', strtotime($props['arrived_recd']))."</i>";
		
		if($appt['completed'] && strcmp(substr($appt['completed'], 0, 10), substr($appt['date'], 0, 10)) != 0)
			$dateDisplay = ' ('.shortestDate(strtotime($appt['completed'])).')';
		else $dateDisplay = '';
		$appt['completedtime'] = $appt['completed'] ? date('h:i a', strtotime($appt['completed'])).$dateDisplay : '--';
		
		if(!$appt['completedmobile']) $appt['completedtime'] = "<span style='font-style:italic;' title='Not reported by mobile'>{$appt['completedtime']}</span>";
		if(staffOnlyTEST() && $props['completed_recd']) $appt['completedtime'] .= "<br><i title='Time received (Staff Only)' style='color:purple'>".date('H:i:s', strtotime($props['completed_recd']))."</i>";
		if($showVisitReports) {
			if(TRUE || mattOnlyTEST()) 
				$appt['visitreport'] = 
					visitReportStatusIcon(
						$appt['appointmentid'],
						"onclick='openConsoleWindow(\"visitreport\", \"visit-report.php?id={$appt['appointmentid']}\",600,600)'");
			if(staffOnlyTEST()) { //  && !mattOnlyTEST()
				$eventCount = $appt['eventcount'] ? $appt['eventcount'] : '0';
				$platform = getAppointmentProperty($appt['appointmentid'], 'native');
				if($platform) $platform .= ' ';
				$globe = '&#127760;';
				$globe = "<span onclick='popMap({$appt['appointmentid']})' style='cursor:pointer' title='Show map'>$globe</span>";
				$appt['visitreport'] .= " ($platform$globe&nbsp;$eventCount)";
			}
		}
		if($showPerformanceIcons) {
			
			$performanceEvaluation = evaluatePerformance($appt);

			if(TRUE || mattOnlyTEST()) $appt['visitreport'] = visitStatusIconPanelForPerformance($appt['appointmentid'], $performanceEvaluation, 15).' '.$appt['visitreport'];
			else $appt['visitreport'] = visitStatusIconPanel($appt['appointmentid'], 15).' '.$appt['visitreport'];
//if(mattOnlyTEST() && !$performanceEvaluation) {print_r($appt);exit; }
//if(mattOnlyTEST() && $performanceEvaluation) {print_r($performanceEvaluation);exit; }
			if($performanceFilter) {
				$blockRow = applyPerformanceFilter($performanceFilter, $performanceEvaluation);
//if(mattOnlyTEST() && $appt['appointmentid'] == 929098) {echo "{$appt['appointmentid']}: ".print_r($performanceEvaluation, 1);exit;}	
//if($blockRow && $appt['appointmentid'] == 900127) echo "$blockRow<br>".print_r($performanceEvaluation,1)."<br>".print_r($performanceFilter,1)."<p>";
//if($blockRow) {echo print_r($performanceEvaluation,1)."<hr>";exit;}
				}
//if(mattOnlyTEST() && $appt['appointmentid'] == 211600) {echo "{$appt['appointmentid']}: ".print_r($performanceEvaluation, 1);exit;}	
				//
//if(mattOnlyTEST()) {echo "{$appt['appointmentid']}: ".print_r($performanceEvaluation, 1);exit;}				
		}
		
		
		
		
		if(!$blockRow) $rows[] = $appt;
		//echo print_r($appt,1)."<p>";
	}
	if(TRUE) {
		if($showVisitReports) echo 
			"<div style='float:right;font-size:1.2em;'>"
			.fauxLink('NEW Icons!', 'openConsoleWindow("newicons", "newvisitreporticons.html", 520, 330)', 1)
			.'</div>';
	}
	// eventcount
	tableFrom($columns, $rows, "style=margin-left:1px;width:98%", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
}


if($showPerformanceIcons) {
	require_once "visit-performance-fns.php";
	echo "<div style='display:none' id='helptext'>".visitPerformanceHelp()."</div>";
}

?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
setPrettynames('provider','Provider','starting','Starting Date','ending', 'Ending Date');	

function popMap(id) {
	openConsoleWindow('popmap', "visit-map.php?id="+id,850,500);
}

function showHelp(el) {$.fn.colorbox({html:$('#helptext').html(), width:"580", height:"600", scrolling: true, opacity: "0.3"});}


function checkAndSubmit(csv) {
  if(MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var provider = document.performanceform.provider.value;
		var starting = document.performanceform.starting.value;
		var ending = document.performanceform.ending.value;
		var filter = document.performanceform.performanceFilter.value;
		csv = typeof csv == 'undefined' || csv == '' || csv == null ? '' : "&csv=1";
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		if(filter) filter = '&performanceFilter='+filter;
//alert(csv);		
    if(csv != '') document.location.href='reports-performance.php?provider='+provider+starting+ending+csv;
    else document.performanceform.submit();
	}
}

<?
dumpPopCalendarJS();

if($showPerformanceIcons) { ?>
function editFilter() {
	// open the visit filter in a light box, passing value of in hidden input performanceFilter
	$.fn.colorbox({href:"performance-filter.php?filter="+document.getElementById('performanceFilter').value
									, width:"700px", height:"500px", iframe: "true", scrolling: true, opacity: "0.3"});
	// the filter will be initialized using that
	// on [Apply Filter] the filter JSON will be plunked back into hidden input performanceFilter via update(, aspect, jsonString)
	// and lightbox will be closed
}

function update(aspect, jsonObj) {
	if(jsonObj == null) return; // update has NOT come from this page, but from the feedback sender
	document.getElementById('filterimg').title = 'Filter:\n'+jsonObj.description;
	document.getElementById('performanceFilter').value = JSON.stringify(jsonObj);
	document.getElementById('clearfilterimg').style.display = 'inline';
	checkAndSubmit(null);
}

function clearFilter() {
	document.getElementById('performanceFilter').value = '';
	document.getElementById('clearfilterimg').style.display = 'none';
	checkAndSubmit(null);
}

<? } ?>

</script>
<img src='art/spacer.gif' width=1 height=160>
<?
include "frame-end.html";
?>
