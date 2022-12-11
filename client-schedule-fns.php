<? 
// client-schedule-fns.php
require_once "appointment-fns.php";
require_once "key-fns.php";

function getClientAppointmentCountAndQuery($starting, $ending, $sort=null, $client, $offset, $max_rows, $arrivalTimes=null) {
	if($sort) {
		$extraFields = "";
		$joinClause = "";
		
		$sort_key = substr($sort, 0, strpos($sort, '_'));
		$sort_dir = substr($sort, strpos($sort, '_')+1);
		if($sort_key == 'name') {
			$orderClause = "ORDER BY name";
			$extraFields = ", ifnull(nickname, CONCAT_WS(' ', fname, lname)) as name";
			$joinClause = " LEFT JOIN tblprovider ON providerid = providerptr";
		}
		else if($sort_key == 'date') 
			$orderClause = "ORDER BY tblappointment.date $sort_dir, starttime ASC";
		else if($sort_key == 'time') 
			$orderClause = "ORDER BY starttime $sort_dir, tblappointment.date ASC";
		else if($sort_key == 'service') {
			$orderClause = "ORDER BY label $sort_dir, tblappointment.date ASC, starttime ASC";
			$extraFields = ", label";
			$joinClause = " JOIN tblservicetype ON servicetypeid = servicecode";
		}
		else $orderClause = "ORDER BY $sort_key $sort_dir";
		if($arrivalTimes) {
			$extraFields .= ", tblgeotrack.date as arrived";
			$joinClause .= " LEFT JOIN tblgeotrack ON appointmentptr = appointmentid AND event = 'arrived'";
		}
	}
	else $orderClause = 'ORDER BY tblappointment.date ASC, starttime ASC';
	$startingPhrase = $starting ? "AND tblappointment.date >= '$starting'" : '';
	$endingPhrase = $ending ? "AND tblappointment.date <= '$ending'" : '';
	$sql = "SELECT appointmentid, recurringpackage, packageptr, clientptr, providerptr, serviceptr, tblappointment.date, timeofday, starttime, endtime, canceled, completed, servicecode, highpriority, modified,
	        pets, rate+(if(bonus is null,0,bonus)) as rate, charge+(if(adjustment is null,0,adjustment)) as charge, canceled, pendingchange, note $extraFields
					FROM tblappointment $joinClause WHERE clientptr = $client $startingPhrase $endingPhrase $orderClause";
	$numFound = fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE clientptr = $client $startingPhrase $endingPhrase");
	
	if($offset) {
		$offset = min($offset, $numFound - 1);
	}
	else $offset = 0;
	$limitClause = $numFound > $max_rows ? "LIMIT $max_rows OFFSET $offset" : '';

  return "$numFound|$sql $limitClause";
}
	

//function tableFrom($columns, $data=null, 
//                     $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', 
//                     $columnSorts=null, $rowClasses=null, $colClasses=null) {
function clientScheduleTable($rows, $suppressColumns=null, $useInPlaceEditors=false) { // ||phone|Client Phone||address|Client Address
	global $SHOW_RATE_AS_WELL;
	$timeWindowColumn = userRole() == 'c' && $_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'] ? '' : '||time|Time Window';
  $columnDataLine = "name|Sitter||date|Date$timeWindowColumn||service|Service||charge|Charge||rate|Rate||pets|Pets||buttons| ";
  if(userRole() == 'c') $columnDataLine = "date|Date$timeWindowColumn||service|Service||pets|Pets||name|Sitter";
  $columns = explodePairsLine($columnDataLine);
  $cols = count($columns);
  if($suppressColumns) foreach($suppressColumns as $col) unset($columns[$col]);
  if(!$SHOW_RATE_AS_WELL) unset($columns['rate']);
  $sortableColsString = 'name|date|time|service';
  foreach(explode('|', $sortableColsString) as $col) $sortableCols[$col] = null;
  $colClasses = array('rate'=>'dollaramountcell','charge'=>'dollaramountcell');
  $rowClasses = array();
  
  if($rows)
		$client = getOneClientsDetails($rows[0]['clientptr'], array(/*'address', 'phone',*/ 'email' , 'pets', 'nokeyrequired'));
  $providerNames = getProviderShortNames();
  
  $keyProviders = array();
  if($rows) foreach(getClientKeys($rows[0]['clientptr']) as $key) foreach($key as $field => $val) if(strpos($field, 'possessor')=== 0) $keyProviders[] = $val;

	$lastDate = null;
  foreach($rows as $i => $row) {
		if($row['date'] != $lastDate) {
			$lastDate = $row['date'];
			$data[] = array('#CUSTOM_ROW#'=> 
				"<tr $extraClass><td colspan=$cols style='padding-top:0px;padding-bottom:5px;border-bottom:solid darkgrey 1px;'></td></tr>\n");
		}
		$futurity = appointmentFuturity($row);
		$tr = array();
		$tr['date'] = shortDateAndDay(strtotime($row['date']));
		
		//amount, issuedate as datetime, payment
		if(isset($row['payment'])) {
			$tr['pets'] = "<span style='color:brown;font-weight:bold;'>".($row['payment'] ? 'PAYMENT' : 'CREDIT')."</span>";
			$tr['charge'] = "<span style='color:brown;font-weight:bold;'>".dollarAmount($row['amount'])."</span>";
			$tr['date'] = "<span style='color:brown;font-weight:bold;'>".shortDateAndDay(strtotime($row['date']))."</span>";
			if(!($i & 1)) $rowClass = 'paymenttaskEVEN';
			else $rowClass = 'paymenttask'; // if even
			$data[] = $tr;
			$rowClasses[count($data)-1] = $rowClass;
		}
		else {
			$tr['name'] = providerLink($row['providerptr'], $providerNames);
	/*		$tr['phone'] = $client['phone'];
			$tr['address'] = $client['address'];
			if($tr['address'] == ", ,") $tr['address'] = '';
			else $tr['address'] = truncatedLabel($tr['address'], 24);
			*/
			$tr['time'] = $row['timeofday'];
			$tr['service'] = serviceLink($row, $futurity, !$useInPlaceEditors);
if($row['modified'] && mattOnlyTEST()) $tr['service'] .= "  <span title='modified.  see client schedule fns'>&#9733;</span>";			
			$tr['pets'] = strip_tags($row['pets']);//petsLink($clientptr, $clients);
			$tr['rate'] = $row['rate'];
			$tr['charge'] = dollarAmount($row['charge']);
			if($_SESSION['preferences']['showZeroRate'] && $row['rate'] == 0 && $row['bonus'] == 0)
				$tr['charge'] .= " <span style='font-weight:bold;font-variant:small-caps;color:red;'>zero</span"; 
		
		
			if($row['canceled']) {
				$cancelArg = 0;
				$imgsrc = 'undelete.gif';
				$bTitle = 'Uncancel this visit.';
			}
			else {
				$cancelArg = 1;
				$imgsrc = 'delete.gif';
				$bTitle = 'Cancel this visit.';
			}
			$cancelAction = $row['appointmentid'] 
				? "cancelAppt({$row['appointmentid']}, $cancelArg)"
				: "cancelAppt({$row['surchargeid']}, $cancelArg, \"surcharge\")";
			$cancelUncancelButton = "<div style='cursor:pointer;display:inline' onClick='$cancelAction'>".
																"<img title='$bTitle' height=18 width=18 border=1 bordercolor=darkgray src='art/$imgsrc'></div>";
			$tr['buttons'] = $cancelUncancelButton;
			if($row['appointmentid']) $tr['#ROW_EXTRAS#'] = "id='apptrow{$row['appointmentid']}'";
			$data[] = $tr;
			

			if($row['canceled']) $rowClass = 'canceledtask';
			else if($row['completed']) $rowClass = 'completedtask';
			else if($futurity == -1) {
				if(!$row['completed']) $rowClass = 'noncompletedtask';
			}
			/**/else if($row['highpriority']) $rowClass = 'highprioritytask';
			else $rowClass = null;
			if(!$rowClass && !($i & 1)) $rowClass = 'futuretaskEVEN'; // if even
			else if(!$rowClass) $rowClass = 'futuretask';
			else if($rowClass && !($i & 1)) $rowClass = $rowClass.'EVEN'; // if even
//if(staffOnlyTEST()) $row['note'] = print_r($row, 1);
			if($row['highpriority'] && $rowClass && strpos($rowClass, 'completed') !== 0 && strpos($rowClass, 'canceledtask') !== 0) 
				$rowClass .= ' highprioritytask';

			if($rowClass) $rowClasses[count($data)-1] = $rowClass;
			$extraClass = $rowClass ? "class='$rowClass'" : '';
			$cols = count($columns);
			
			if($useInPlaceEditors) {
				$editRow = array('#CUSTOM_ROW#'=> 
					"<tr $extraClass style='display:none'><td id='editor_{$row['appointmentid']}' colspan=$cols style='padding-top:0px;padding-bottom:5px;'></td></tr>\n");
				$data[] = $editRow;
			}
			
			$keyRequired = !$row['surchargeid'] && $client['nokeyrequired'] == 0;
			$row2 = '';
			if(userRole() == 'c') ;// no second row for clients
			else if($row['origprovider']) {			
				$noKey = in_array($row['providerptr'], $keyProviders) ? '' : ($keyRequired ? ' - '.noKeyLink($row['clientptr']) : '');
				$row2 = "(Reassigned from: {$row['origprovider']} $noKey)";
			}
			else if($keyRequired && !in_array($row['providerptr'], $keyProviders)) {
				$noKey = noKeyLink($row['clientptr']);
				$row2 = "$noKey";
			}
			if($row['note']) {
				$notecolor = $_SESSION['preferences']['showVisitNotesInBlack'] ? 'black' : 'red';
				$row2 .= "<font color='$notecolor'>  Note: {$row['note']}</font>";
			}
			if($row['appointmentid'] && $_SESSION['preferences']['includeOriginalNotesInVisitLists']) { //show oldnote
				$oldnote = fetchRow0Col0(
					"SELECT value 
						FROM tblappointmentprop 
						WHERE appointmentptr = {$row['appointmentid']} AND property = 'oldnote' LIMIT 1", 1);
				if($oldnote)
					$row2 .= "<font color='blue'>  Orig Note: $oldnote</font>";
			}
			
			if($row2)
				$data[] = array('#CUSTOM_ROW#'=> 
					"<tr $extraClass><td colspan=$cols style='padding-top:0px;padding-bottom:5px;'>$row2</td></tr>\n");
		}
		//print_r($row);exit;
	}
//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
  echo "<div style='background:white; border: solid black 1px;'>";
  tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $sortableCols, $rowClasses, $colClasses, 'sortAppointments');
  echo "</div>";
}

function clientCalendarTable($rows) {
	global $timesOfDay;
	require_once "preference-fns.php";
	$timesOfDayRaw = getPreference('appointmentCalendarColumns');
	if(!$timesOfDayRaw) $timesOfDayRaw = 'Morning,07:00:00,Midday,11:00:00,Afternoon,15:00:00,Evening,19:00:00';
	$timesOfDayRaw = explode(',',$timesOfDayRaw);
	$timesOfDay = array();
	for($i=0;$i < count($timesOfDayRaw)-1; $i+=2) $timesOfDay[$timesOfDayRaw[$i+1]] = $timesOfDayRaw[$i];

  foreach($rows as $row) $ids[] = $row['clientptr'];
  //$clients = getClientDetails($ids, array('address', 'phone', 'email' /*, 'pets'*/));
  $providers = getProviderShortNames();
	$timeStarts = array_keys($timesOfDay);
	foreach($rows as $r => $row) {
//print_r($timesOfDay);	echo "<p>";
		$row['starttime'] = $row['starttime'].':00';  // added this line because of https://leashtime.com/support/admin/admin_ticket.php?track=PTN-SPX-RETR&Refresh=63389
		$tod = null;
		for($i=0;$i < count($timeStarts); $i++) {
		  if($i+1 == count($timeStarts)) 
		    $tod = $timesOfDay[$timeStarts[$i]];
		  else if($i==0 && $row['starttime'] < $timeStarts[$i]) 
		    $tod = $timesOfDay[$timeStarts[$i]];
		  else if($row['starttime'] < $timeStarts[$i+1])
				$tod = $timesOfDay[$timeStarts[$i]];
			if($tod) {
				$rows[$r]['TODColumn'] = $tod;
				break;
			}
		}
		$rows[$r]['primaryname'] = providerLink($row['providerptr'], $providers);
		if(!$row['providerptr'] && userRole() != 'c') 
			$rows[$r]['primaryname'] = "<span style='color:red;font-style:italic;'>{$rows[$r]['primaryname']}</span>";
		$rows[$r]['service'] = serviceLink($row, appointmentFuturity($row), 'noInPlaceEditor');
	}
}
}
	
//drawDayCalendar($objects, $timesOfDay, $timeOfDayKey, $dateKey, $objectDisplayFn, $subSectionKey=null, $sortSubsections=true) {
	foreach($timesOfDay as $label) $todLabels[$label] = $label;
	drawDayCalendar($rows, $todLabels, 'TODColumn', 'date', 'dumpProviderAppointment', 'dumpNarrowProviderAppointment');
}

function providerLink($providerptr, $providerNames) {
	/*return "<a href=#
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id=$clientptr\",700,500)'>
	       {$providerNames[$providerptr]}</a> ";*/
	return isset($providerNames[$providerptr]) ? $providerNames[$providerptr] : 'Unassigned';
}

$serviceNames = null;
function serviceLink($appt, $futurity, $noInPlaceEditor=false) {
	require_once "service-fns.php";
	if($appt['surchargecode']) return surchargeLink($appt, $futurity, $updateList);
	
	global $userRole, $serviceNames;
	if(!$serviceNames) $serviceNames = isset($_SESSION['servicenames']) ? $_SESSION['servicenames'] : getServiceNamesById();;
//echo "[".isset($_SESSION)."] ".print_r(getServiceNamesById(), 1);exit;	
	if(!$userRole) $userRole = userRole();
	$petsTitle = $appt['pets'] 
	  ? htmlentities("Pets: {$appt['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	//$targetPage = true || $futurity == -1 ? 'appointment-view.php' : 'appointment-edit.php';
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	$targetPage = $roDispatcher 
				? "client-request-appointment.php?updateList=appointmentst&operation=change" 
				: 'appointment-edit.php?updateList=appointments';
	if($userRole == 'c') return $serviceNames[$appt['servicecode']];
	else {
		$popUpEditor = "openConsoleWindow(\"editappt\", \"$targetPage&id={$appt['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})";
		$noInPlaceEditor = $noInPlaceEditor || $roDispatcher;
		$inPlaceEditor = "quickEdit({$appt['appointmentid']})";
		$mainAction = $noInPlaceEditor ? $popUpEditor : $inPlaceEditor;
		return fauxLink(serviceName($appt), $mainAction, 1, $petsTitle)
	       . ($noInPlaceEditor ? "" : " <a class='fauxlink' onClick='$popUpEditor'><img src='art/magnifier.gif' width=12 height=12></a>");
	}
}

function serviceName($row) {
	if($row['servicecode']) {
		require_once "service-fns.php";
		$names = getAllServiceNamesById();	
		return $names[$row['servicecode']];
	}
	else if($row['surchargecode']) {
		require_once "surcharge-fns.php";
		$names = getSurchargeTypesById();
		return 'Surcharge: '.$names[$row['surchargecode']];
	}
}

function surchargeLink($row, $futurity, $updateList=null) {
	$serviceName = serviceName($row);
	if(userRole() == 'c') return $serviceName;
	
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	if($roDispatcher) return $serviceName;
	$surchargeTitle = "Charge: ".dollarAmount($row['charge'] );
	return "<a class='fauxlink'
	       onClick='openConsoleWindow(\"editappt\", \"surcharge-edit.php?updateList=$updateList&id={$row['surchargeid']}\",530,450)' 
	       title='$surchargeTitle'>$serviceName</a>";
}

