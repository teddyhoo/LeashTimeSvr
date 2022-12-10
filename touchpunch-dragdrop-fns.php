<?
/* touchpunch-dragdrop-fns.php
* Used to build a set of three tables among which appointments can be dragged and dropped.
* State is maintained in a database table called reassignments.
*
* Usage: 
*   Call echoAppointmentReassignmentForm() to set up the tables.
*   Include appointment-dragdrop.js
* Tested in FF3 and IE6
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";

$sourceTaskColor = 'yellow';
$jobTableWidth = '95%';

function dropOldReassignments() {
	$today = date('Y-m-d');
	$ids = fetchCol0("SELECT appointmentptr FROM `relreassignment` 
							LEFT JOIN tblappointment ON appointmentptr = appointmentid
							WHERE date IS NULL OR date < '$today'");
	if($ids) deleteTable("relreassignment", "appointmentptr in (".join(',', $ids).")", 1);
}

function echoAppointmentReassignmentForm($primaryProvider=null, $dateRange=null, $tableAttrs=null) {
	$dateRange = !$dateRange ? array(date('Y-m-d'), date('Y-m-d')) : $dateRange;
	echo "<div id='debug'></div>";
	echo "<div id='icon' style='position:absolute;display:none;z-index:-1;'><img src='art/drag-appt.gif'></div>";
	echo <<<FORM
	<form name='reassignmentform'>
	<input type=hidden name='primaryProv' value='$primaryProvider'>
	<table width=100%><tr><td>
FORM;
  //calendarSet('Date', 'date', date('Y-m-d', $date), $labelClass=null, $inputClass=null, $includeArrowWidgets=true);
	calendarSet('Starting:', 'starting', $dateRange[0], null, null, true, 'ending');
	echo "&nbsp;";
	calendarSet('ending:', 'ending', $dateRange[0]);
  
	echoButton('', 'Refresh', 'refreshAppointmentReassignmentTables()');
	echo "<td align='right'>";
	echoButton('', 'Make All Reassignments', 'executeAll()'); echo " ";
	echoButton('', 'Cancel All Reassignments', 'cancelAll()');
	echo "</td><tr></table>\n";
//calendarSet($label, $name, $value=null, $labelClass=null, $inputClass=null, $includeArrowWidgets=true)
	echo <<<FORM
	<p>
	<table border=0 bordercolor=red $tableAttrs>
	<tr><td colspan=2 style='text-align:center;font-weight:bold;' id='workingdate'></td></tr>
	<tr>
	<td valign=top style='padding:0px;padding-botton:2px;margin:0px;margin-botton:2px;'><div id='appointments' class='ui-droppable' style='border:solid black 1px;height:500px;overflow:auto;'></div></td>
	<td valign=top style='padding-top:0px;'><div id='allappointments' class='ui-droppable' style='border:solid black 1px;height:500px;overflow:auto;'></div></td>
	</tr>
	<tr><td colspan=2 id='reassigned' style='padding-top:10px;vertical-align:top;'></td></tr></table>
	</form>
FORM;
}

// ********************************
function getAppointmentsFor($prov, $dateRange) {  // "appointments" zone
	global $jobTableWidth;
	$start = date('Y-m-d', strtotime($dateRange[0]));
	$end = date('Y-m-d', strtotime($dateRange[1]));
	$zone = 'appointments';
	$providerptr = $prov['providerptr'];
	if($providerptr) {
	  $provFilter = $providerptr == -1 ? "providerptr=0" : 
	              ($providerptr == -2 ? "1=1" :
	              "providerptr=$providerptr");
    $sql = "SELECT * from tblappointment where $provFilter and date >= '$start' and date <= '$end'
          and appointmentid not in (select appointmentptr from relreassignment) AND canceled IS NULL order by date, starttime";
    $appts = fetchAssociations($sql);

//echo $sql.'<p>'.print_r($appts, 1);exit;
	}
	else $appts = array();
	collectClientDetails($appts);
  $additions = array('Click Here'=>0,'-- All Sitters --'=>-2, '-- Unassigned --'=>-1); 
  $wagButton = !$providerptr ? '' : wagButton($providerptr);
  echo "\n<table class='taskTable' style='padding-bottom:10px;vertical-align:top;border: solid red 0px;'>
         <tr><th class='taskTableProviderCell' colspan=1>Reassign jobs from: ".
         echoProviderSelect('allproviders', $providerptr, $additions, $exclude)." $wagButton</th></tr>";/*." on ".date("M d",strtotime($date))*/
  if($providerptr) {
		if($providerptr > 0) $label = "{$prov['name']}'s Schedule for";
		else if($providerptr == -1) $label = "Unassigned Jobs for";
		else if($providerptr == -2) $label = "All Jobs for";
		$dateRangeLabel = dateRangeLabel($start, $end);
    echo "<tr><td colspan=3 style='text-align:center;'>$label $dateRangeLabel</td></tr>\n";
    $lastDate = '';
    if(empty($appts)) echo "<tr><td colspan=3 style='text-align:center;font-style:italic;'>None</td></tr>\n";
    else foreach($appts as $appt) {
			if($appt['date'] != $lastDate) echo "<tr><td class='daybanner'>".date('F j', strtotime($appt['date']))."</td></tr>";
			$lastDate = $appt['date'];
      displayAppointmentTile($appt, null, $zone);
    }
	}
  echo "\n</table>\n";
}

function wagButton($provs, $label=null) {
	return echoButton('', $label ? $label : 'Week View', "openWAG(\"$provs\")", '', '', 1);
}

function collectClientDetails($appts) {
	global $clientDetails;
	$ids = array();
	foreach($appts as $appt) $ids[] = $appt['clientptr'];
	$clientDetails = getClientDetails($ids, array('address'));
}

function getProvisionalAppointmentsFor($prov, $dateRange, $exclude=null) {  // "allappointments" zone
	global $jobTableWidth;
	$start = date('Y-m-d', strtotime($dateRange[0]));
	$end = date('Y-m-d', strtotime($dateRange[1]));
	$zone = 'allappointments';
	$providerptr = $prov['providerptr'];
	$provFilter = $providerptr == -1 ? "providerptr=0" : "providerptr=$providerptr";
	if($providerptr && ($providerptr != $exclude)) {
    $sql = "SELECT * from tblappointment where $provFilter and date >= '$start' and date <= '$end' AND canceled IS NULL order by date, starttime";
    $appts = fetchAssociations($sql);
	}
	else $appts = array();
  $sql = "SELECT tblappointment.*, relreassignment.providerptr as assignee
  from relreassignment
  join tblappointment on tblappointment.appointmentid = appointmentptr
  where relreassignment.$provFilter and date >= '$start' and date <= '$end' AND canceled IS NULL order by date, starttime";
  $appts = array_merge($appts, fetchAssociations($sql));
  usort($appts, 'dateTimesInOrder');
	collectClientDetails($appts);
  $additions = array('Click Here'=>0,'-- Unassigned --'=>-1); 
  $exclusions = array($exclude);
	$wagButton = !$providerptr ? '' : wagButton($providerptr);

  echo "\n<table id='{$zone}_table' class='taskTable' style='padding-bottom:10px;vertical-align:top;'><tr><th class='taskTableProviderCell' colspan=3>Reassign jobs to: ".
  							echoProviderSelect('otherproviders', $providerptr, $additions, $exclusions).
         " $wagButton</th></tr>"; 
  if($providerptr) {
		if($providerptr > 0) $label = "{$prov['name']}'s Schedule for";
		else if($providerptr == -1) $label = "Unassigned Jobs for";
		else if($providerptr == -2) $label = "All Jobs for";
		$dateRangeLabel = dateRangeLabel($start, $end);

		$timeoff = timeOffDescriptions($providerptr, $start, $end);
//if($_SESSION['staffuser']) {echo "start: $start end: $end ";print_r($timeoff);}
		$timeoff = $timeoff ? "<p><div style='text-align:left;display:block;background:pink;padding:3px;'><b>Time Off</b><p>".join('<br>', $timeoff).'</div>' : '';
    echo "<tr><td colspan=3 style='text-align:center;'><span style='font-size:1.2em'>$label $dateRangeLabel</span>$timeoff</td></tr>\n";         
    $lastDate = '';
    if(empty($appts)) echo "<tr><td colspan=3 style='text-align:center;font-style:italic;'>None</td></tr>\n";
    else foreach($appts as $appt) {
		  $notDraggable = $appt['assignee'] && ($appt['providerptr'] != $exclude);
			if($appt['date'] != $lastDate) echo "<tr><td class='daybanner'>".date('F j', strtotime($appt['date']))."</td></tr>";
			$lastDate = $appt['date'];
      displayAppointmentTile($appt, $providerptr, $zone, $notDraggable);
    }
  }
  echo "\n</table>\n";
}


function dateRangeLabel($start, $end) {
	if($start == $end || !$end) return  month3Date(strtotime($start));
	return  month3Date(strtotime($start)).' - '.month3Date(strtotime($end));
}


function getReassignmentsFrom($prov, $dateRange) {
	global $jobTableWidth;
	$start = date('Y-m-d', strtotime($dateRange[0]));
	$end = date('Y-m-d', strtotime($dateRange[1]));
	$zone = 'reassigned';
	$providerptr = $prov['providerptr'];	
	$provFilter = $providerptr == -1 ? "tblappointment.providerptr=0" : 
	              ($providerptr == -2 ? "1=1" :
	              "tblappointment.providerptr=$providerptr");
  $sql = "SELECT tblappointment.*, relreassignment.providerptr as assignee
  FROM relreassignment
  JOIN tblappointment on tblappointment.appointmentid = appointmentptr
  WHERE $provFilter and date >= '$start' and date <= '$end' AND canceled IS NULL order by assignee, date, starttime";
  $appts = fetchAssociations($sql);
  
  
	collectClientDetails($appts);
  $pLabel = $prov == -1 ? "Previously unassigned jobs:" :
            ($prov == -2 ? "Jobs Reassigned from All Users" :
            "Jobs Reassigned from: {$prov['name']}");
  if($prov) {          
    echo "\n<table class='taskTable'><tr><th class='taskTableProviderCell' colspan=3>$pLabel</th></tr>";
    $assignee = null;
    $lastDate = '';
    if(empty($appts)) echo "<tr><td colspan=3 style='text-align:center;font-style:italic;'>None</td></tr>\n";
    else foreach($appts as $appt) {
		  if($appt['assignee'] != $assignee) {
				$assigneeName = lookupProviderName($appt['assignee']);
			  echo "<tr><td colspan=3 style='font-style:italic;'>Assigned to $assigneeName</td></tr>\n";
			  $assignee = $appt['assignee'];
		  }
		  $notDraggable = $appt['assignee'] && ($appt['providerptr'] != $providerptr);
			if($appt['date'] != $lastDate) echo "<tr><td class='daybanner'>".date('F j', strtotime($appt['date']))."</td></tr>";
			$lastDate = $appt['date'];
      displayAppointmentTile($appt, null, $zone, $notDraggable);
    }
    echo "\n</table>\n";
	}
}

function echoProviderSelect($selectname, $selectedProvider, $additions=null, $omissions=null) {
	global $activeProviderSelections;
	$providers = $additions ? $additions : array();
	$omissions = $omissions ? $omissions : array();
	$activeProviderSelections = getActiveProviderSelectionsAsFlatList();
	foreach($activeProviderSelections as $name => $id)
		if(!in_array($id, $omissions)) $providers[$name] = $id;
	$s = "<select name='$selectname' onchange='refreshAppointmentReassignmentTables()'>\n";
	foreach($providers as $label => $v) {
		$selected = $selectedProvider == $v  ? "SELECTED" : "";
		$s .= "<option value='$v' $selected>$label\n";
	}
	return $s."</select>\n";
}

function reassignAppointmentTo($apptid, $prov, $origprov) {
	$appt = fetchFirstAssoc("SELECT appointmentid, date, timeofday, servicecode, providerptr FROM tblappointment  WHERE appointmentid = $apptid LIMIT 1");
	require_once "provider-fns.php";
	require_once "appointment-fns.php";
//echo "providerIsOff($prov, {$appt['date']}, {$appt['timeofday']}): ".providerIsOff($prov, $appt['date'], $appt['timeofday']);
	
	if($prov && providerIsOff($prov, $appt['date'], $appt['timeofday'])
			|| detectVisitCollision($appt, $prov))
		return;
	if($origprov == -2) $origprov = $appt['providerptr'];
	if($prov == -1) $prov = 0;
	if($origprov == -1) $origprov = 0;
	$sql = "REPLACE relreassignment (appointmentptr, providerptr, origproviderptr) values ($apptid, $prov, $origprov)";
	return doQuery($sql);
}

function cancelReassignment($apptid) {
	$sql = "DELETE FROM relreassignment WHERE appointmentptr = $apptid";
	doQuery($sql);
}


function unassignAppoint($apptid) {
	$sql = "DELETE FROM relreassignment where appointmentptr = $apptid";
	doQuery($sql);
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
	
function lookupProviderName($prov) {
	global $providerNames;
	if(!isset($providerNames)) 
	  $providerNames = array_flip(getActiveProviderSelectionsAsFlatList());
	if(isset($providerNames[$prov])) return $providerNames[$prov];
	$list = array(0 => 'Unassigned', -2 => 'All Sitters');
	if(isset($list[$prov])) return $list[$prov];
	return $prov;
}

function displayAppointmentTile($appt, $prov=null, $zone, $provisionalButNotMovable=false) {
	global $clientDetails, $sourceTaskColor;
	$apptid = $appt['appointmentid'];
  $movable = !$provisionalButNotMovable && (($zone != 'allappointments') || isset($appt['assignee']));
  $originalOwner = lookupProviderName($appt['providerptr']); 
	if($movable) ; // handled by webkit
	else if($provisionalButNotMovable) 
	  $onMouseDown = "onMouseDown='return cancelReassignment($apptid, \"$originalOwner\")'";
	else $onMouseDown = "";
	$style = 'vertical-align:top;';
	$style = "style=".($movable ? "'background:$sourceTaskColor;$style'" 
	                            : ($provisionalButNotMovable ? "'background:lightgrey;$style'"
	                                                         :"'$style'"));
	$styleRight = 'vertical-align:top;text-align:right;';
	$styleRight = "style=".($movable  ? "'$styleRight'" : "'$styleRight'");
	$time = $appt['timeofday']; //date("g:i a", strtotime($appt['date']));
	$client = $clientDetails[$appt['clientptr']];
	$service = getServiceName($appt['servicecode']);
	$divClass = $movable ? 'ui-draggable' : ''; 
	echo "<tr $onMouseDown $style>
	<td class='task'><div class='$divClass' id='dap_$apptid' zone='$zone' z-index:999><table style='border:outset #888855 1px;width:100%;'><tr>
	  <td>{$client['clientname']}<br>{$client['address']}</td><td $styleRight>$service<br>$time</td>
	</tr></table></div></td></tr>";
}

function clearAllReassignments() {
	doQuery("DELETE FROM relreassignment");
}

function executeAllReassignments() {
	require_once "provider-memo-fns.php";
	$assignments = fetchAssociations("SELECT * FROM relreassignment");
	$providers = array();
	$changes = array();
	$apptIds = array();
	foreach($assignments as $assignment) {
		if($assignment['providerptr']) $providers[] = $assignment['providerptr'];
		$apptIds[] = $assignment['appointmentptr'];
	}
	$providerRates = getMultipleProviderRates($providers);
	$appointments = fetchAssociationsKeyedBy("SELECT appointmentid, servicecode, charge, providerptr, clientptr FROM tblappointment WHERE appointmentid IN(".join(',', $apptIds).")", 'appointmentid');
	$standardRates = getStandardRates();
	foreach($standardRates as $index => $rate) $standardRates[$index]['rate'] = $standardRates[$index]['defaultrate'];
	foreach($assignments as $assignment) {
		$appointment = $appointments[$assignment['appointmentptr']];
		if(!$appointment) continue;
		$change = array('providerptr'=>$assignment['providerptr'], 'rate'=>getRate($assignment, $providerRates, $standardRates, $appointments));
		updateTable('tblappointment', $change, "appointmentid ={$assignment['appointmentptr']}", 1);
		if($_SESSION['surchargesenabled']) {
			require_once "surcharge-fns.php";
			updateAppointmentAutoSurcharges($assignment['appointmentptr']);
		}
		// completion and charge are unchanged, so no discount action is necessary
		makeClientVisitReassignmentMemo($appointment['providerptr'], $appointment['clientptr'], $appointment['appointmentid']);
		makeClientVisitReassignmentMemo($assignment['providerptr'], $appointment['clientptr'], $appointment['appointmentid']);
	}
	clearAllReassignments();
}

function getRate(&$assignment, &$providerRates, &$standardRates, &$appointments) {
	$provider = $assignment['providerptr'];
	$appointment = $appointments[$assignment['appointmentptr']];
	$rateDesc = $provider && isset($providerRates[$provider][$appointment['servicecode']])
							? $providerRates[$provider][$appointment['servicecode']]
							: $standardRates[$appointment['servicecode']];
	return $rateDesc['ispercentage']
					? $rateDesc['rate'] / 100 * $appointment['charge']
					: $rateDesc['rate'];
}
?>