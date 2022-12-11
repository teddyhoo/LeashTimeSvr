<? 
// prov-schedule-fns.php
require_once "appointment-fns.php";
require_once "key-fns.php";
require_once "service-fns.php";

function getProviderAppointmentCountAndQuery($starting, $ending, $sort=null, $provider, $offset, $max_rows, $filterANDPhrase='', $joinPhrase='', $additionalCols='') {
	// $filterANDPhrase - e.g., "AND canceled IS NULL";
	$providerClause = !$provider ? '1=1' : 
										(is_array($provider) ? "appt.providerptr IN (".join(',', $provider).")" : "providerptr = $provider");
	$providerClause = str_replace('-1', '0', $providerClause);
	if($sort) {
		$extraFields = "";
		$joinClause = "";
		$sort_key = substr($sort, 0, strpos($sort, '_'));
		$sort_dir = substr($sort, strpos($sort, '_')+1);
		if($sort_key == 'name') 
			$orderClause = "ORDER BY lname $sort_dir, fname $sort_dir";
		else if($sort_key == 'date') {
			$orderClause = "ORDER BY date $sort_dir, starttime ASC, endtime ASC"; //, lname ASC, fname ASC";
			//$extraFields = ", lname, fname";
		}
		else if($sort_key == 'time') 
			$orderClause = "ORDER BY starttime $sort_dir, date ASC";
		else if($sort_key == 'service') {
			$orderClause = "ORDER BY label $sort_dir, date ASC, starttime ASC";
			$extraFields = ", label";
			$joinClause = "JOIN tblservicetype ON servicetypeid = servicecode";
		}
		else $orderClause = "ORDER BY $sort_key $sort_dir";
	}
	if($joinPhrase) $joinClause .= $joinPhrase;
	if($additionalCols) $extraFields .= ", $additionalCols";
	else $orderClause = 'ORDER BY date ASC, starttime ASC';
	$startingPhrase = $starting ? "AND date >= '$starting'" : '';
	$endingPhrase = $ending ? "AND date <= '$ending'" : '';
	$sql = "SELECT appointmentid, appt.clientptr, appt.providerptr, serviceptr, appt.packageptr, date, appt.timeofday, starttime, endtime, canceled, completed, appt.servicecode, highpriority,
	        appt.pets, appt.rate+(if(appt.bonus is null,0,appt.bonus)) as rate, appt.charge+(if(appt.adjustment is null,0,appt.adjustment)) as charge, canceled, pendingchange, 
	        recurringpackage, note $extraFields
					FROM tblappointment appt $joinClause WHERE $providerClause $startingPhrase $endingPhrase $filterANDPhrase $orderClause";
//if(mattOnlyTEST() && $provider==53) echo "$filterANDPhrase<hr>$sql<hr>".print_r(fetchAssociations($sql), 1);
					
	$numFound = fetchRow0Col0("SELECT count(*) FROM tblappointment appt WHERE $providerClause $startingPhrase $endingPhrase $filterANDPhrase");
	if($offset) {
		$offset = min($offset, $numFound - 1);
	}
	else $offset = 0;
	$limitClause = $max_rows == -1 ? '' : ($numFound > $max_rows ? "LIMIT $max_rows OFFSET $offset" : '');

  return "$numFound|$sql $limitClause";
}
	

// CALLED ONLY IN prov-notification-fns.php
function providerScheduleTable($rows, $suppressColumns=null, $noSort=false, $updateList=null, $noLinks=false, $forceDateRow=false, $providerView=false) {
	global $prefs;
	// $updateList - element id to be updated after appointment edit
	$providerView = $providerView || !$_SESSION || userRole() == 'p' || !userRole();
  if($providerView) {
		//$checkCols = "markComplete| ||";
  	$columnDataLine = 'client|Client||phone|Phone||address|Address||date|Date||time|Time Window||service|Service||rate|Pay';
	}
	else {
/*
		setPreference('provuisched_hidephone', ($_POST['provsched_hidephone'] ? 0 : 1));
		setPreference('provuisched_hideaddress', ($_POST['provsched_hideaddress'] ? 0 : 1));
		setPreference('provuisched_start', $_POST['provsched_start']);
		setPreference('provuisched_hidepay', ($_POST['provsched_hidepay'] ? 0 : 1));
*/
		$columnDataLine = 'client|Client||phone|Phone||address|Address||date|Date||time|Time Window||service|Service||charge| ||buttons| ';
	}
	
	
	$prefs = $prefs ? $prefs : ($_SESSION['preferences'] ? $_SESSION['preferences'] : fetchKeyValuePairs("SELECT property, value FROM tblpreference"));
	if($prefs['provuisched_start'] == 'starttime')
		$columnDataLine = str_replace('time', 'start', str_replace('Time Window', 'Start', $columnDataLine));

  $columns = explodePairsLine($columnDataLine);
	$noContactInfo = $prefs['suppresscontactinfo'];
	if($prefs['provuisched_hidephone']) $suppressColumns[] = 'phone';
	if($prefs['provuisched_hideaddress']) $suppressColumns[] = 'address';
	if($prefs['provuisched_hiderate']) $suppressColumns[] = 'rate';
	if($noContactInfo) $suppressColumns = array_merge($suppressColumns, array('phone'));
	
  if($suppressColumns) foreach($suppressColumns as $col) unset($columns[$col]);
	$cols = count($columns);
  
  $sortableColsString = 'client|date|time|service';
  foreach(explode('|', $sortableColsString) as $col) $sortableCols[$col] = null;
  if($noSort) $sortableCols = array();
  
  $colClasses = array('rate'=>'dollaramountcell','charge'=>'revenuecell');
  $rowClasses = array();
  
  foreach((array)$rows as $row) {
		if($row['clientptr']) $ids[] = $row['clientptr'];
		$allDates[] = $row['date'];
	}
	$allDates = $allDates ? array_unique($allDates) : array();
	
  $clients = getClientDetails($ids, array('address', 'zip', 'phone', 'email', 'nokeyrequired', 'lname' /*, 'pets'*/));
  
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($ids);}
  
  
  $keyClients = array();
  if($rows) {
		$provider = $rows[0]['providerptr'];
		if($provider) { // ignore unassigned appts
			$timeOff = getProviderTimeOff($provider);
			foreach(getProviderKeys($provider) as $key) $keyClients[] = $key['clientptr'];
		}
	}

	$lastDate = null;
	$n = -1;
  foreach((array)$rows as $row) {
		$n++; // $rows indices may be appointment ids
		if($row['date'] != $lastDate) {
			if($suppressColumns && in_array('date', $suppressColumns) && ($forceDateRow || (count($allDates) > 1) || $row['date'] != $starting)) {
				$rowClasses[count($data)] = 'daycalendardaterow';
				$data[] = array('#CUSTOM_ROW#'=> 
					"<tr><td class='daycalendardaterow' colspan=$cols>".longDayAndDate(strtotime($row['date']))."</td></tr>\n");
			}
			else 
				$data[] = array('#CUSTOM_ROW#'=> 
					"<tr $extraClass><td colspan=$cols style='padding-top:0px;padding-bottom:5px;border-bottom:solid darkgrey 1px;'></td></tr>\n");

				
			$lastDate = $row['date'];
		}
		$futurity = appointmentFuturity($row);
		$tr = array();
		if($row['clientptr']) { // may be a time off
			$noKey = !$row['surchargeid'] && ($clients[$row['clientptr']]['nokeyrequired'] ? false : !in_array($row['clientptr'], $keyClients));
			$tr['client'] = clientLink($row['clientptr'], $clients, (userRole() == 'p' ? $row['date'] : null), $row);

			if($noKey) $tr['client'] .= 
					$noLinks ? noKeyIcon($row['clientptr'])
					:	noKeyIconLink($row['clientptr'], $back=null, $popup=0, $imgsize=15);


			$tr['phone'] = $clients[$row['clientptr']]['phone'];
			/*$tr['address'] = $clients[$row['clientptr']]['address'];
			if($tr['address'] == ", ,") $tr['address'] = '';
			else $tr['address'] = truncatedLabel($tr['address'], 24);*/

			$tr['address'] = addressLink($clients[$row['clientptr']]);	
		}		
		$tr['date'] = shortDate(strtotime($row['date']));
		
		
		/* REMOVE LATER */
		if(isProviderOffThisDayWithRows($provider, $row['date'], $timeOff))
		  $tr['date'] = "<font color='red'>{$tr['date']}</font>";
		/* REMOVE LATER */
		
		$tr['start'] = substr($row['timeofday'], 0, strpos($row['timeofday'], '-'));


		if($row['clientptr']) {
			$tr['time'] = $row['timeofday'];
			$tr['service'] = $noLinks ? serviceName($row) : serviceLink($row, $futurity, $updateList);
		}
		else {
			$tr['time'] = "<span style='color:red'>".($row['timeofday'] ? $row['timeofday'] : 'All Day')."</span>";
			$tr['service'] = "<span style='color:red'>TIME OFF: {$tr['time']}</span>";
		}

		$canceledLooks = $noLinks ? 'color:red;font-weight:bold;text-decoration:underline;' 
																: 'font-size:0.8em;color:red;font-weight:bold;text-decoration:underline;';
		$tr['rate'] = $row['canceled'] ? "<span style='$canceledLooks'>CANCELED</span>" : $row['rate'];
		
		$tr['charge'] = $row['canceled'] ? "<span style='$canceledLooks'>CANCELED</span>" : $row['charge'];
		
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
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {			
//		$cancelUncancelButton = "<img id='can_{$row['appointmentid']}' style='cursor:pointer;display:inline' onClick='$cancelAction' title='$bTitle' height=18 width=18 border=1 bordercolor=darkgray src='art/$imgsrc'>";
//} else															
		$cancelUncancelButton = "<div style='cursor:pointer;display:inline' onClick='$cancelAction'>".
															"<img title='$bTitle' height=18 width=18 border=1 bordercolor=darkgray src='art/$imgsrc'></div>";
		$tr['buttons'] = $cancelUncancelButton;
		$tr['#ROW_EXTRAS#'] = "id='apptrow{$row['appointmentid']}'";
		
		$data[] = $tr;
		
		if($row['canceled']) $rowClass = 'canceledtask';
		else if($row['completed']) $rowClass = 'completedtask';
		else if($futurity == -1) {
			if(!$row['completed']) $rowClass = 'noncompletedtask';
		}
		else if($row['highpriority']) $rowClass = 'highprioritytask';
		else $rowClass = null;
		if(!$rowClass && !($n & 1)) $rowClass = 'futuretaskEVEN'; // if even
		else if(!$rowClass) $rowClass = 'futuretask';
		else if($rowClass && !($n & 1)) $rowClass = $rowClass.'EVEN'; // if even

		if($row['highpriority'] && $rowClass && strpos($rowClass, 'completed') !== 0 && strpos($rowClass, 'canceledtask') !== 0) 
			$rowClass .= ' highprioritytask';
//if($_SESSION['staffuser']) $row['note'] = print_r($row, 1);


		if($rowClass) $rowClasses[count($data)-1] = $rowClass;
//echo "$n: ($rowClass) ".($n & 1)."<br>";		
		
		$extraClass = $rowClass ? "class='$rowClass'" : '';
		
		$editRow = array('#CUSTOM_ROW#'=> 
			"<tr $extraClass style='display:none'><td id='editor_{$row['appointmentid']}' colspan=$cols style='padding-top:0px;padding-bottom:5px;'></td></tr>\n");
		$data[] = $editRow;
				
		$row2 = null;
		//if($noKey) $noKey = $noLinks ? '<font color=red>NO KEY</font>' : noKeyLink($row['clientptr']);
		
		if($row['origprovider']) {			
			$noKey = $noKey ? " - $noKey" : '';
			global $scriptPrefs; // allow for use from the daily cron job where there is no $_SESSION
			// TBD I suspect global $prefs and global $scriptPrefs are redundant.  investigate.
			//$prefs = $scriptPrefs ? $scriptPrefs : $_SESSION['preferences'];
			if(!$providerView || !$prefs['hideReassignedFromNoteFromProviders'])
				$row2 = "(Reassigned from: {$row['origprovider']} )";//$noKey
		}
		//else if($noKey) {
		//	$row2 = $noKey;
		//}
		if($row['pendingchange']) $row2 .= '<font color=red>'.($row['pendingchange'] < 0 ? 'Cancellation' : 'Change').' Pending</font>';
		if($row['note']) {
			$notecolor = $_SESSION['preferences']['showVisitNotesInBlack'] ? 'black' : 'red';
			$row2 .= "<font color='$notecolor'>  Note: {$row['note']}</font>";
		}
		
		if($row2) 
			$data[] = array('#CUSTOM_ROW#'=> 
			  "<tr $extraClass><td colspan=$cols style='padding-top:0px;padding-bottom:5px;'>$row2</td></tr>\n");
		
		
//print_r($row);exit;
	}
  tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $sortableCols, $rowClasses, $colClasses);
}

// ##########################
function versaProviderScheduleTable($providerid, $rows, $suppressColumns=null, $noSort=false, $updateList=null, $noLinks=false, $forceDateRow=false, $providerView=false, $columnDataLine=null, $emailVersion=false, $printable=false) {
	// $updateList - element id to be updated after appointment edit
	$providerView = $providerView || !$_SESSION || userRole() == 'p' || !userRole();
	$includeVisitReportColumn = staffOnlyTEST() || $_SESSION['preferences']['homepagevisitreporticonsenabled']; // $_SESSION["staffuser"] == '8909' = jodymaint
  if($providerView) {
		//$checkCols = "markComplete| ||";
  	$columnDataLine = $columnDataLine ? $columnDataLine
										: 'client|Client||phone|Phone||address|Address||date|Date||time|Time Window||service|Service||rate|Pay';
	}
	else {
		$columnDataLine = $columnDataLine ? $columnDataLine
										: 'client|Client||phone|Phone||address|Address||date|Date||time|Time Window||service|Service||charge| ||buttons| ';
		if($includeVisitReportColumn) $columnDataLine .= '||visitreport| ';
	}
  $columns = explodePairsLine($columnDataLine);
  if($suppressColumns) foreach($suppressColumns as $col) unset($columns[$col]);
  
  global $enableDragging;
  if($enableDragging) {
		$newCols = explodePairsLine('drag| ');
		foreach($columns as $i => $col) $newCols[$i] = $col;
		$columns = $newCols;
	}
	$cols = count($columns);
	$canceledLooks = $noLinks ? 'color:red;font-weight:bold;text-decoration:underline;' 
															: 'font-size:0.8em;color:red;font-weight:bold;text-decoration:underline;';
  
  $sortableColsString = 'client|date|time|service';
  foreach(explode('|', $sortableColsString) as $col) $sortableCols[$col] = null;
  if($noSort) $sortableCols = array();
  
  $colClasses = array('rate'=>'dollaramountcell','charge'=>'revenuecell');
  $rowClasses = array();
  
  foreach((array)$rows as $row) {
		if($row['clientptr']) $ids[] = $row['clientptr'];  // may be a time off
		$allDates[] = $row['date'];
	}
	$allDates = $allDates ? array_unique($allDates) : array();
	
  $clients = getClientDetails($ids, array('address', 'zip', 'phone', 'email', 'nokeyrequired', 'fullname', 'lname' /*, 'pets'*/));
  
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($ids);}
  
  
  $keyClients = array();
  if($rows) {
		$provider = $rows[0]['providerptr'];
		if($provider) { // ignore unassigned appts
			$timeOff = getProviderTimeOff($provider);
			foreach(getProviderKeys($provider) as $key) $keyClients[] = $key['clientptr'];
		}
	}

	$lastDate = null;
	$n = -1;
	
	$userRole = userRole();
	
	foreach((array)$rows as $appt) if($appt['packageptr']) $packs[$appt['packageptr']] = $appt;

	foreach((array)$packs as $id => $appt) {
		$curr = findCurrentPackageVersion($id, $appt['clientptr'], $appt['recurringpackage']);

		$latest[$id] = $curr;
		if($curr && !isset($packnotes[$curr])) {
			$table = $appt['recurringpackage'] ? 'tblrecurringpackage' : 'tblservicepackage';
			$packnotes[$curr] = fetchRow0Col0("SELECT notes FROM $table WHERE packageid = $curr LIMIT 1");
		}
	}
	
  foreach((array)$rows as $row) {
		$n++; // $rows indices may be appointment ids
		if($row['date'] != $lastDate) {
			if($suppressColumns && in_array('date', $suppressColumns) && ($forceDateRow || count($allDates) > 0)) {
				$rowClasses[count($data)] = 'daycalendardaterow';
				$data[] = array('#CUSTOM_ROW#'=> 
					"<tr><td class='daycalendardaterow' colspan=$cols>".longDayAndDate(strtotime($row['date']))."</td></tr>\n");
			}
			else 
				$data[] = array('#CUSTOM_ROW#'=> 
					"<tr $extraClass><td colspan=$cols style='padding-top:0px;padding-bottom:5px;border-bottom:solid darkgrey 1px;'></td></tr>\n");

				
			$lastDate = $row['date'];
		}
		$futurity = appointmentFuturity($row);
		$tr = array();
		
		if($includeVisitReportColumn) 
			$tr['visitreport'] = 
				visitReportStatusIcon(
					$row['appointmentid'], 
					"onclick='openConsoleWindow(\"visitreport\", \"visit-report.php?id={$row['appointmentid']}\",600,600)'");
		
		if($enableDragging && $row['appointmentid']) 
			$tr['drag'] = "<image height=15 width=12 src='art/drag.gif' id='{$row['appointmentid']}' class='dragme'>";
		if($row['clientptr']) { // may be a time off
			$noKey = !$row['surchargeid'] && ($clients[$row['clientptr']]['nokeyrequired'] ? false : !in_array($row['clientptr'], $keyClients));
			$tr['client'] = clientLink($row['clientptr'], $clients, (userRole() == 'p' ? $row['date'] : null), $row, $noLinks);
			if($row['canceled'] && !in_array('rate', array_keys($columns)) && !in_array('charge', array_keys($columns)))
				$tr['client'] = "<span style='$canceledLooks'>CANCELED</span> {$tr['client']}";
			if($row['providerptr'] != $providerid) {
				if(!$providerNames) $providerNames = getProviderShortNames();
				$tr['client'] = "<div class='othersittertag'>{$providerNames[$row['providerptr']]}</div> {$tr['client']}";
			}

			if($noKey) $tr['client'] .= 
					$noLinks ? noKeyIcon($row['clientptr'])
					:	noKeyIconLink($row['clientptr'], $back=null, $popup=0, $imgsize=15);


			$tr['phone'] = truncatedLabel($clients[$row['clientptr']]['phone'], 15);
			/*$tr['address'] = $clients[$row['clientptr']]['address'];
			if($tr['address'] == ", ,") $tr['address'] = '';
			else $tr['address'] = truncatedLabel($tr['address'], 24);*/

			$tr['address'] = addressLink($clients[$row['clientptr']], $noLinks, $printable);	
		}
		$tr['date'] = shortDate(strtotime($row['date']));
		if(isset($columns['starttime']) && $row['timeofday']) {
			$tr['starttime'] = substr($row['timeofday'], 0, strpos($row['timeofday'], '-'));
			$tr['starttime'] = "<span title='{$row['timeofday']}'>{$tr['starttime']}</span>";
		}
		if(isProviderOffThisDayWithRows($provider, $row['date'], $timeOff))
		  $tr['date'] = "<font color='red'>{$tr['date']}</font>";
		if($row['clientptr']) {
			$tr['time'] = $row['timeofday'];
			$tr['service'] = $noLinks ? serviceName($row) : serviceLink($row, $futurity, $updateList);
		}
		else {
			$tr['time'] = "<span style='color:red'>".($row['timeofday'] ? $row['timeofday'] : 'All Day')."</span>";
			$tr['service'] = "<span style='color:red'>TIME OFF</span>";
			$onclickedit = "openConsoleWindow(\"timeoffcalendar\", \"timeoff-sitter-calendar.php?&editable=1&provid={$row['providerptr']}&month={$row['date']}&open={$row['timeoffid']}\",850,700)";
			$tr['service'] .= " <img src='art/clock20.gif' width=10 height=10 title='Edit Time Off' onclick='$onclickedit'>";
		}
		//$tr['pets'] = petsLink($clientptr, $clients);
		
		$tr['rate'] = $row['canceled'] ? "<span style='$canceledLooks'>CANCELED</span>" : (
									$row['providerptr'] != $providerid ? '' : $row['rate']);
		
		$tr['charge'] = $row['canceled'] ? "<span style='$canceledLooks'>CANCELED</span>" : $row['charge'];
		
		
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
if(0 && mattOnlyTEST()) {			
		$cancelUncancelButton = "<img id='can_{$row['appointmentid']}' style='cursor:pointer;display:inline' onClick='$cancelAction' title='$bTitle' height=18 width=18 border=1 bordercolor=darkgray src='art/$imgsrc'>";
} else															
		$cancelUncancelButton = "<div style='cursor:pointer;display:inline' onClick='$cancelAction'>".
															"<img title='$bTitle' height=18 width=18 border=1 bordercolor=darkgray src='art/$imgsrc'></div>";
		if($row['clientptr']) $tr['buttons'] = $cancelUncancelButton;
		$tr['#ROW_EXTRAS#'] = "id='apptrow{$row['appointmentid']}'";
		
		$data[] = $tr;
		
		if($row['canceled']) $rowClass = 'canceledtask';
		else if($row['completed']) $rowClass = 'completedtask';
		else if($futurity == -1) {
			if(!$row['completed']) $rowClass = 'noncompletedtask';
		}
		else if($row['highpriority']) $rowClass = 'highprioritytask';
		else $rowClass = null;
		if(!$rowClass && !($n & 1)) $rowClass = 'futuretaskEVEN'; // if even
		else if(!$rowClass) $rowClass = 'futuretask';
		else if($rowClass && !($n & 1)) $rowClass = $rowClass.'EVEN'; // if even

		if($row['highpriority'] && $rowClass && strpos($rowClass, 'completed') !== 0 && strpos($rowClass, 'canceledtask') !== 0) 
			$rowClass .= ' highprioritytask';
//if($_SESSION['staffuser']) $row['note'] = print_r($row, 1);

		if($rowClass) $rowClasses[count($data)-1] = $rowClass;
//echo "$n: ($rowClass) ".($n & 1)."<br>";		
		
		$extraClass = $rowClass ? "class='$rowClass'" : '';
		
		if(!$emailVersion) {
			$editRow = array('#CUSTOM_ROW#'=> 
				"<tr $extraClass style='display:none'><td id='editor_{$row['appointmentid']}' colspan=$cols style='padding-top:0px;padding-bottom:5px;'></td></tr>\n");
			$data[] = $editRow;
		}
				
		$row2 = null;
		//if($noKey) $noKey = $noLinks ? '<font color=red>NO KEY</font>' : noKeyLink($row['clientptr']);
		
		if($row['origprovider'] && (!$providerView || !$prefs['hideReassignedFromNoteFromProviders'])) {			
			$noKey = $noKey ? " - $noKey" : '';
			if(!$providerView || !$prefs['hideReassignedFromNoteFromProviders'])
				$row2 = "(Reassigned from: {$row['origprovider']} )".(mattOnlyTEST() ? "[[$providerView]]" : '' );//$noKey
		}
		//else if($noKey) {
		//	$row2 = $noKey;
		//}
		if($row['pendingchange']) $row2 .= '<font color=red>'.($row['pendingchange'] < 0 ? 'Cancellation' : 'Change').' Pending</font>';
		$ignoreNotes = array("[START]","[FINISH]", "[START][FINISH]");

		$note = 
			$row['note'] && !in_array(strtoupper(str_replace("\r", '', str_replace("\n", '', $row['note']))), $ignoreNotes)
				? $row['note'] :
				($row['appointmentid'] 
					? ($row['note'] ? "{$row['note']}\n" : '').addPackageNotes($packnotes[$latest[$row['packageptr']]]) 
					: '');
///if(mattOnlyTEST() && $row['appointmentid'] == 91455) $note .= " >>>".print_r($packnotes, 1);
		if($note ) {
			$notecolor = $_SESSION['preferences']['showVisitNotesInBlack'] ? 'black' : 'red';
			$row2 .= "<font color='$notecolor'>  Note: $note</font>";
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
	}
	if($emailVersion) {
		$sortableCols = $rowClasses = $colClasses = null;
	}
  tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $sortableCols, $rowClasses, $colClasses);
}

function visitReportStatusIcon($apptid, $extraAttributes=null) {
	if($apptid) { // ignore surcharges and other non-appointments
		$imageDir = "newvisitreporticons/";
		$vrimage = 'visit-report-unsubmitted.jpg';
		$aprops = getAppointmentProperties($apptid, $properties='reportIsPublic,visitreportreceived,visitphotoreceived,visitphotouploadfail');
		$finalPhotoFail = $aprops['visitphotouploadfail'] && 
				(!$aprops['visitphotoreceived'] || 
								strcmp($aprops['visitphotoreceived'], $aprops['visitphotouploadfail']) < 0);
		if($received = $aprops['visitreportreceived']) {
			$vrimage = 'visit-report-submitted.jpg';
			if($finalPhotoFail) {
				$vrimage = 'visit-report-submitted-photo-failed';
				$photoStatus = " (NO photo - upload failed)";
			}
			else if($aprops['visitphotoreceived']) {
				$vrimage = 'visit-report-submitted-with-photo.jpg';
				$photoStatus = " (with photo)";
			}
			else {
				$vrimage = 'visit-report-submitted.jpg';
				$photoStatus = " (NO photo)";
			}
			$vrtitle = "Report received$photoStatus: ".shortDateAndTime(strtotime($received));
		}
		else $vrtitle = 'Report not yet received.';

		if($sent = $aprops['reportIsPublic']) {
			$vrimage = 'visit-report-sent.jpg';
			if($finalPhotoFail) $vrimage = 'visit-report-sent-photo-failed.jpg';
			else if($aprops['visitphotoreceived']) $vrimage = 'visit-report-sent-with-photo.jpg';
			$vrtitle .= "\n and sent: ".shortDateAndTime(strtotime($sent));
		}
//if(mattOnlyTEST() && $apptid) echo "[[[$apptid: <img height=20 width=20 src='art/$imageDir$vrimage' title= \"$vrtitle\"  $extraAttributes>";	
		return "<img height=20 width=20 src='art/$imageDir$vrimage' title= \"$vrtitle\"  $extraAttributes>";
	}
}	

function addPackageNotes($str) {
 if(!in_array(userRole(), array('o','d')) // always on for sitters
				|| $_SESSION['preferences']['showPackageNotesInVisit'])
	return $str;
}

function visitCountDisplay($total, $completed, $unreported, $omitRevenue) {
	$plural = $total == 1 ? '' : 's';
	$total = $total ? $total : 'No';
	$visits = "($total visit$plural)".($omitRevenue ? '' : ':');
	/*$visits = ($upgradeTEST || $total) 
		? "($total visit$plural)".($omitRevenue ? '' : ':') 
		: '';*/
if(TRUE || dbTEST('themonsterminders')) {
	if($completed) $visits .= " <span class='completedtask'>Completed: $completed</span>";
	if($unreported) $visits .= " <span class='noncompletedtask'>Unreported: $unreported</span>";
}

	return $visits;
}



function addressLink($client, $noLinks=false, $printable=false) {
	$label = $client['address'];
	$fulladr = urlencode($client['address'].' '.$client['zip']);
	if($label == ", ,") $label = '';
	else $label = $printable ? $label : truncatedLabel($label, 24);
	if($noLinks) return $label;
	return "<a href='http://maps.google.com/maps?t=m&q=$fulladr'>$label</a>";  //http://mapki.com/wiki/Google_Map_Parameters
}

function providerCalendarTable($rows, $suppressDateRows=false, $appointmentDisplayFn='dumpProviderAppointment', $allProviders=false, $simpleProviderSpacers=false, $omitRevenue=false) {
	global $timesOfDay;
	
	foreach($rows as $i => $row)
		if($row['timeofday'] && !$row['starttime']) {
			$rows[$i]['starttime'] = date('H:i', strtotime(substr($row['timeofday'], 0, strpos($row['timeofday'], '-'))));
		}
	
	require_once "preference-fns.php";
	
	
	$timesOfDayRaw = getPreference('appointmentCalendarColumns');
//	if(mattOnlyTEST() || !$timesOfDayRaw) $timesOfDayRaw = 'Morning,07:00:00,Midday,11:00:00,Afternoon,15:00:00,Evening,19:00:00';
	if(!$timesOfDayRaw) $timesOfDayRaw = 'Morning,07:00:00,Midday,11:00:00,Afternoon,15:00:00,Evening,19:00:00';
}
	$timesOfDayRaw = explode(',',$timesOfDayRaw);
	$timesOfDay = array();
	for($i=0;$i < count($timesOfDayRaw)-1; $i+=2) $timesOfDay[$timesOfDayRaw[$i+1]] = $timesOfDayRaw[$i];
}


	$displayedDateRange = array();
	if($rows && !$allProviders) {
		$provider = $rows[0]['providerptr'];
		$displayedDateRange = array($rows[0]['date'], $rows[0]['date']);
		foreach($rows as $row) {
			$displayedDateRange[0] = min($displayedDateRange[0], $row['date']);
			$displayedDateRange[1] = max($displayedDateRange[1], $row['date']);
		}
		/*$timeOffRows = getProviderTimeOff($provider, true, 
		   "('{$displayedDateRange[0]}' BETWEEN firstdayoff AND lastdayoff OR
		      '{$displayedDateRange[1]}' BETWEEN firstdayoff AND lastdayoff OR
		      firstdayoff BETWEEN '{$displayedDateRange[0]}' AND '{$displayedDateRange[1]}' OR
		      lastdayoff BETWEEN '{$displayedDateRange[0]}' AND '{$displayedDateRange[1]}')");*/
		/*$timeOffRows = getProviderTimeOffInRange($provider, $displayedDateRange);
		foreach($timeOffRows as $timeOff) {
			for($d = max($timeOff['firstdayoff'], $displayedDateRange[0]);
			    $d <= min($timeOff['lastdayoff'], $displayedDateRange[1]);
			    $d = date('Y-m-d', strtotime("+1 day", strtotime($d)))) {
				$to = array('firstdayoff'=>$timeOff['firstdayoff'], 'lastdayoff'=>$timeOff['lastdayoff'], 'date'=>$d);
				$rows[] = $to;
			}
		}*/
	}

  foreach($rows as $row) if($row['clientptr']) $ids[] = $row['clientptr'];
  $clients = getClientDetails($ids, array('address', 'phone', 'email', 'lname'  /*, 'pets'*/));
	$timeStarts = array_keys($timesOfDay);
	foreach($rows as $r => $row) {
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
		if($row['timeoff']) {
			$rows[$r]['primaryname'] = "<span style='color:red'>TIME OFF</span>";
			$onclickedit = "openConsoleWindow(\"timeoffcalendar\", \"timeoff-sitter-calendar.php?&editable=1&provid={$row['providerptr']}&month={$row['date']}&open={$row['timeoffid']}\",850,700)";
			$rows[$r]['primaryname'] .= " <img src='art/clock20.gif' width=10 height=10 title='Edit Time Off' onclick='$onclickedit'>";
		}
		else 
			{$rows[$r]['primaryname'] = clientLink($row['clientptr'], $clients, null, $row);
			$rows[$r]['service'] = serviceLink($row, appointmentFuturity($row));
			$rows[$r]['chargelink'] = chargeLink($row, appointmentFuturity($row), null, $omitRevenue);
		}
	}
	
	
	
//drawDayCalendar($objects, $timesOfDay, $timeOfDayKey, $dateKey, $objectDisplayFn, $narrowObjectDisplayFn=null, $subSectionKey=null, $sortSubsections=true, $suppressDateRows=false) 
	foreach($timesOfDay as $label) $todLabels[$label] = $label;
//print_r($rows);	exit;

	$subsectionKey = $allProviders ? 'provider' : null;

	drawDayCalendar($rows, $todLabels, 'TODColumn', 'date', $appointmentDisplayFn, 'dumpNarrowProviderAppointment', $subsectionKey, true, $suppressDateRows, $simpleProviderSpacers, $omitRevenue);
}

function providerColors($provids) {
	$allColors = '#21FFFC,#FF4729,#EDFF29,#4EFD2B,#FD2BFF,#FDE26F,#8683F7,#FF9329,#FFC0C0,#A7FFA7,#CFA9FF,#56BE0A,#179F9D,#ED5F43,#8F9C00';
	$allColors = explode(',', $allColors);
	// find all active provider IDs
	$allProviderIDs = fetchCol0("SELECT providerid FROM tblprovider WHERE active = 1 ORDER BY providerid");
	if(count($allProviderIDs) <= count($allColors)) {

		// if there are enough colors assign each active provider a "permanent" color for the session
		foreach($allProviderIDs as $i => $prov)
			$sessionProviderColors[$prov] = $allColors[$i];
		foreach($provids as $prov)
			$providerColors[$prov] = $sessionProviderColors[$prov];
	}
	else {
		$provids = array_merge($provids);
		foreach($provids as $i => $prov)
			$providerColors[$prov] = $allColors[$i % count($allColors)];
	
	}
	$providerColors[0] = '#FFFFFF';
	return $providerColors;
}

function providerCalendarTable90($rows, $suppressDateRows=false, $appointmentDisplayFn='dumpProviderAppointment', $allProviders=false, $simpleProviderSpacers=false, $omitRevenue=false, $groupByTimesOfDay=true) {
	// providerCalendarTable rotated 90 degrees
	// $rows is a list of visits
	// count the days and show that many columns
	// table has a headers row (dates)
	// if $groupByTimesOfDay, there is a Time of day column and one row per time of day
	// otherwise there is just one visits row
	// each visits cell is a list of visits sorted by starttime and sitter
	// each sitter has a distinct color
	global $timesOfDay;
	
	foreach($rows as $i => $row)
		if($row['timeofday'] && !$row['starttime']) {
			$rows[$i]['starttime'] = date('H:i', strtotime(substr($row['timeofday'], 0, strpos($row['timeofday'], '-'))));
		}
	
	if($groupByTimesOfDay) {
		require_once "preference-fns.php";
		$timesOfDayRaw = getPreference('appointmentCalendarColumns');
	
		if(!$timesOfDayRaw) $timesOfDayRaw = 'Morning,07:00:00,Midday,11:00:00,Afternoon,15:00:00,Evening,19:00:00';
		$timesOfDayRaw = explode(',',$timesOfDayRaw);
		for($i=0;$i < count($timesOfDayRaw)-1; $i+=2) $timesOfDay[$timesOfDayRaw[$i+1]] = $timesOfDayRaw[$i];
	}

	$displayedDateRange = array();
	if($rows && !$allProviders) {
		$provider = $rows[0]['providerptr'];
		$displayedDateRange = array($rows[0]['date'], $rows[0]['date']);
		foreach($rows as $row) {
			$displayedDateRange[0] = min($displayedDateRange[0], $row['date']);
			$displayedDateRange[1] = max($displayedDateRange[1], $row['date']);
		}
		/*$timeOffRows = getProviderTimeOff($provider, true, 
		   "('{$displayedDateRange[0]}' BETWEEN firstdayoff AND lastdayoff OR
		      '{$displayedDateRange[1]}' BETWEEN firstdayoff AND lastdayoff OR
		      firstdayoff BETWEEN '{$displayedDateRange[0]}' AND '{$displayedDateRange[1]}' OR
		      lastdayoff BETWEEN '{$displayedDateRange[0]}' AND '{$displayedDateRange[1]}')");*/
		/*$timeOffRows = getProviderTimeOffInRange($provider, $displayedDateRange);
		foreach($timeOffRows as $timeOff) {
			for($d = max($timeOff['firstdayoff'], $displayedDateRange[0]);
			    $d <= min($timeOff['lastdayoff'], $displayedDateRange[1]);
			    $d = date('Y-m-d', strtotime("+1 day", strtotime($d)))) {
				$to = array('firstdayoff'=>$timeOff['firstdayoff'], 'lastdayoff'=>$timeOff['lastdayoff'], 'date'=>$d);
				$rows[] = $to;
			}
		}*/
	}

  foreach($rows as $row) if($row['clientptr']) $ids[] = $row['clientptr'];
  $clients = getClientDetails($ids, array('address', 'phone', 'email', 'lname'  /*, 'pets'*/));
	$timeStarts = array_keys($timesOfDay);
	foreach($rows as $r => $row) {
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
		if($row['timeoff']) {
			$rows[$r]['primaryname'] = "<span style='color:red'>TIME OFF</span";
		}
		else 
			{$rows[$r]['primaryname'] = clientLink($row['clientptr'], $clients, null, $row);
			$rows[$r]['service'] = serviceLink($row, appointmentFuturity($row));
			$rows[$r]['chargelink'] = chargeLink($row, appointmentFuturity($row), null, $omitRevenue);
		}
	}
	
	
	
//drawDayCalendar($objects, $timesOfDay, $timeOfDayKey, $dateKey, $objectDisplayFn, $narrowObjectDisplayFn=null, $subSectionKey=null, $sortSubsections=true, $suppressDateRows=false) 
	foreach($timesOfDay as $label) $todLabels[$label] = $label;
//print_r($rows);	exit;

	$subsectionKey = $allProviders ? 'provider' : null;

	drawDayCalendar($rows, $todLabels, 'TODColumn', 'date', $appointmentDisplayFn, 'dumpNarrowProviderAppointment', $subsectionKey, true, $suppressDateRows, $simpleProviderSpacers, $omitRevenue);
}

function clientDisplayName($clientptr, $displayMode=null) {
	global $wagPrimaryNameMode;
	$displayMode = $displayMode ? $displayMode : $wagPrimaryNameMode;
	if(strpos($displayMode, 'pets') !== FALSE) {
		require_once "pet-fns.php";
		$clientPets = getClientPetNames($clientptr, $inactiveAlso=false, $englishList=false);
		$displayPets = $clientPets ? $clientPets : "no pets";
	}
	if(strpos($displayMode, 'name') !== FALSE) 
		$client = fetchFirstAssoc(
			"SELECT lname, fname, CONCAT_WS(' ', fname, lname) as clientname 
				FROM tblclient
				WHERE clientid = $clientptr LIMIT 1", 1);
		
	$label = $displayMode == 'fullname' ? $client['clientname'] : (
					 $displayMode == 'name/pets' ? "{$client['lname']} ($displayPets)" : (
					 $displayMode == 'pets' ? $displayPets : (
					 $displayMode == 'pets/name' ? "$displayPets ({$client['lname']}) " : (
					 $displayMode == 'fullname/pets' ? "{$client['clientname']} ($displayPets)" :  
					'???'))));
	return $label;

}

function clientLink($clientptr, $clients, $includeVisitSheetLinkForDate=false, $row=null, $noLinks=false) {
	global $wagPrimaryNameMode, $db;
	static $lastDb, $allPets;
//if(mattOnlyTEST())	{echo "<hr>$lastDb/$db($clientptr)<br>".print_r(array_keys($clients),1).'<br>'.print_r($allPets,1);}
	if(strpos($wagPrimaryNameMode, 'pets') !== FALSE) {
		require_once "pet-fns.php";
		if($db != $lastDb) $allPets = array(); // if we are in a different database since last call to clientLink, clear allPets
		$lastDb = $db;
		foreach(array_keys($clients) as $id)
			if(!in_array($id, $allPets))
				$clientsToFind[] = $id;
		if($clientsToFind) 
			foreach(getPetNamesForClients($clientsToFind, $inactiveAlso=false) as $id => $val)
				$allPets[$id] = $val;
		/*
		if($db != $lastDb) {
			$allPets = getPetNamesForClients(array_keys($clients), $inactiveAlso=false);
			$lastDb = $db;
		}
		*/
		$clientPets = $allPets[$clientptr];
		$rowPets = trim($row['pets']);
		$displayPets = $rowPets == 'All Pets' ? $clientPets : ($rowPets ? $rowPets : "no pets");
	}
	
	if(!$wagPrimaryNameMode) $wagPrimaryNameMode = 'fullname';
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($clients);exit;; }	
	$label = $wagPrimaryNameMode == 'fullname' ? $clients[$clientptr]['clientname'] : (
					 $wagPrimaryNameMode == 'name/pets' ? "{$clients[$clientptr]['lname']} ($displayPets)" : (
					 $wagPrimaryNameMode == 'pets' ? $displayPets : (
					 $wagPrimaryNameMode == 'pets/name' ? "$displayPets ({$clients[$clientptr]['lname']}) " : (
					 $wagPrimaryNameMode == 'fullname/pets' ? "{$clients[$clientptr]['clientname']} ($displayPets)" :  
					'???'))));
	if($noLinks) return $label;
	return "<a class='fauxlink'
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id=$clientptr\",700,500)'>$label</a> ".
	       (!$includeVisitSheetLinkForDate ? '' : visitSheetLink($clientptr, $includeVisitSheetLinkForDate));
}

function visitSheetLink($client, $visitSheetDate) {
	$visitSheetDate = date('Y-m-d', strtotime($visitSheetDate));
	return " <img src='art/tinyprinter.gif' onClick='printVisitSheet($client, \"$visitSheetDate\")' style='cursor:pointer' title='View/print a visit sheet'>";
}
	
function serviceName($row) {
	global $db, $maxServiceNameLength;
	static $names, $surchargenames, $lastDb;
	if($row['servicecode']) {
		if($lastDb != $db) $names = getAllServiceNamesById($refresh=true, $noInactiveLabel=true, $setGlobalVar=false);	
		return $maxlen ? truncatedLabel($names[$row['servicecode']], $maxServiceNameLength) : $names[$row['servicecode']];
	}
	else if($row['surchargecode']) {
		require_once "surcharge-fns.php";
		if($lastDb != $db) $surchargenames = getSurchargeTypesById();
		$label = $maxlen ? truncatedLabel($surchargenames[$row['surchargecode']], $maxServiceNameLength) : $surchargenames[$row['surchargecode']];
		return 'Surcharge: '.$label;
	}
	$lastDb = $db;
}

function surchargeLink($row, $futurity, $updateList=null) {
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	$serviceName = serviceName($row);
	if($roDispatcher || userRole() == 'p') return $serviceName;
	$surchargeTitle = "Charge: ".dollarAmount($row['charge'] );
	return "<a class='fauxlink'
	       onClick='openConsoleWindow(\"editappt\", \"surcharge-edit.php?updateList=$updateList&id={$row['surchargeid']}\",530,450)' 
	       title='$surchargeTitle'>$serviceName</a>";
}

function serviceLink($row, $futurity, $updateList=null) {
	if($row['surchargecode']) return surchargeLink($row, $futurity, $updateList);
	$serviceName= serviceName($row);
	$petsTitle = $row['pets'] 
	  ? htmlentities("Pets: {$row['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	//$targetPage = true || $futurity == -1 ? 'appointment-view.php' : 'appointment-edit.php';
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	$targetPage = $roDispatcher ? "client-request-appointment.php?updateList=$updateList&operation=change" : "appointment-edit.php?updateList=$updateList";  // 
	$inPlaceEditor = "quickEdit({$row['appointmentid']})";
	$popUpEditor = 
		(adequateRights('ea') || $roDispatcher)
			? "openConsoleWindow(\"editappt\", \"$targetPage&id={$row['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})"
			: "openConsoleWindow(\"editappt\", \"appointment-view.php?updateList=$updateList&id={$row['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})";
	
	if(adequateRights('ea') && !$roDispatcher)
		$mainEditor = $inPlaceEditor;
	else
		$mainEditor = "openConsoleWindow(\"editappt\", \"appointment-view.php?updateList=$updateList&id={$row['appointmentid']}\",530,450)";
		
	$indicatorIconDimensions = 'height:16px;width:16px;';
	if(!$row['completed'] /* && $futurity == -1*/ && $row['appointmentid']) { //  && 
		$arrival = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = {$row['appointmentid']} AND event = 'arrived' LIMIT 1", 1);
		if($arrival) {
			$arrival = date('g:i a', strtotime($arrival));
			$arrivalNote = "Arrived: $arrival";
			$statusIndicator = " <IMG SRC='art/arrivedbutton.gif' style='cursor:pointer;height:16px;width:16px;vertical-align:middle;' title=\"Arrived: $arrival\">";
		}
	}
	else if($row['completed'] && $_SESSION['preferences']['showCompletionIndicatorsInSitterVisitLists']) {
		$arrival = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = {$row['appointmentid']} AND event = 'arrived' LIMIT 1", 1);
		if($arrival) {
			$arrival = date('g:i a', strtotime($arrival));
			$arrivalNote = "Arrived: $arrival";
		}
		$rawCompletionDate = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = {$row['appointmentid']} AND event = 'completed' LIMIT 1", 1);
		$rawCompletionDate = $rawCompletionDate ? $rawCompletionDate : $row['completed'];
		$indicatorAction = '';
		if($rawCompletionDate) {
			$completionDate = date('g:i a', strtotime($rawCompletionDate)); //shortDateAndTime(strtotime($arrival));
			if(date('Y-m-d', strtotime($rawCompletionDate)) != $row['date']) $completionDate .= " (".shortNaturalDate(strtotime($rawCompletionDate), 'noYear').")";
			//$completionDate = shortDateAndTime(strtotime($completionDate));
			$completionTitle = ($arrivalNote ? "$arrivalNote. " : '')."Completed: $completionDate. ";
			$img = 'art/accepted_29_trimmed.png';
			if(mattOnlyTEST()  || dbTEST('pawlosophy,dogslife,tonkatest') ) {
				require_once "preference-fns.php";
				$reported = getAppointmentProperty($row['appointmentid'], 'reportIsPublic');
				if($reported) {
					$reported = date('g:i a', strtotime($reported)); //shortDateAndTime(strtotime($arrival));
					if(date('Y-m-d', strtotime($reported)) != $row['date']) $reported = " (".shortNaturalDate(strtotime($reported), 'noYear').")";
					$completionTitle .= "Reported: $reported";
					$img = 'art/visit_report_sent_29_trimmed.png';
					$indicatorIconDimensions = 'height:16px;width:20px;';

				}
				else if(mattOnlyTEST()  || dbTEST('pawlosophy,dogslife,tonkatest')) $indicatorAction = "onclick=\"markVisitReported({$row['appointmentid']}, 'visitstatus_{$row['appointmentid']}')\"";
			}
			$statusIndicator = " <IMG $indicatorAction id=\"visitstatus_{$row['appointmentid']}\" SRC='$img' style='cursor:pointer;{$indicatorIconDimensions}vertical-align:middle;margin-left:5px;' title=\"$completionTitle\">";
		}
	}
	return "<a class='fauxlink'
	       onClick='$mainEditor' 
	       title='$petsTitle'>$serviceName</a>"
	       . (!adequateRights('ea') ? "" : " <a class='fauxlink' onClick='$popUpEditor'><img src='art/magnifier.gif' width=12 height=12></a>")
	       .$statusIndicator;
}


function chargeLink($row, $futurity, $updateList=null, $omitRevenue=false) {
	$service = $_SESSION['servicenames'][$row['servicecode']].' - '.$row['pets'];
	$targetPage = /*$futurity == -1*/false ? 'appointment-view.php' : 'appointment-edit.php';
	$charge = $row['canceled'] ? "<span style='font-size:0.8em;'>CANCELED</span>" : (
						$omitRevenue ? 'Edit' : dollarAmount($row['charge'], $cents=true, $nullRepresentation='', $nbsp=''));
	return "<a class='fauxlink'
	       onClick='apptEd({$row['appointmentid']})' 
	       title='$service'>$charge</a>";
}