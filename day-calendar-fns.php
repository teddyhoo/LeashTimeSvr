<?
// day-calendar-fns.php


/* styles used: daycalendartable, daycalendardaterow, daycalendarsubrow
*/

function drawDayCalendar($objects, $timesOfDay, $timeOfDayKey, $dateKey, $objectDisplayFn, $narrowObjectDisplayFn=null, $subSectionKey=null, $sortSubsections=true, $suppressDateRows=false, $simpleSubsectionSpacers=false, $omitRevenue=false) {
// $objects are sorted by $dateKey, $timeOfDayKey, and (if supplied), by $subSectionKey
// then they are displayed in a table whose columns are the $timesOfDay ($key => $label)
// days are separated by a cross-table row displaying the date
// within each day, the day's objects are displayed in a one-column table where each day is
// rendered (usually as a table) by $objectDisplayFn
// if $subSectionKey is defined, as the $subSectionKey of the rendered object changes from its
// predecessor's $subSectionKey, a subsection header is rendered before the object.
// $subSectionKey will usually be the provider's name
  global $TODKey, $DATEKey, $SUBSECTIONKey, $TODKeys, $SORTSUBSECTIONS;
  $TODKeys = array_keys($timesOfDay);
  $TODKey = $timeOfDayKey;
  $DATEKey = $dateKey;
  $SUBSECTIONKey = $subSectionKey;
  $SORTSUBSECTIONS = $sortSubsections;
  usort($objects, 'dayCalendarSort');

 
  if($subSectionKey) $appointmentCounts = getAppointmentCounts($objects);
  
	// If HomePage WAG view, tally daily revenue 
	if($showRevenue = !$omitRevenue && basename($_SERVER['SCRIPT_NAME']) == 'wag.php' && !isset($_REQUEST['providers'])) {
		$dailyTally = array();
		foreach($objects as $obj)
			$dailyTally[$obj[$DATEKey]] += $obj['charge'];
	}
  
    
  $todKeys = array_keys($timesOfDay);
  $lastDay = null;
  $lastTOD = null;
  $lastSUB = -999;
	echo "\n<table id='calendarview' class='daycalendartable'>\n<tr>";
	
	if(count($timesOfDay) > 4 && $narrowObjectDisplayFn 
			&& $objectDisplayFn != 'dumpOneLineWAGAppointment') 
			$objectDisplayFn = $narrowObjectDisplayFn;
  foreach($objects as $obj) {
		$newDay = false;
		if($lastDay != $obj[$DATEKey]) {
		  $newDay = true;
			if($lastDay) {
				echo "</table></td>"; // for TOD array
				if($lastTOD) {
					$lastTODIndex = array_search($lastTOD, $todKeys);
					for($i=$lastTODIndex+1;$i<count($todKeys);$i++) echo "<td class=$todcellclass>&nbsp;</td>";  // dump remaining (empty) TOD cells
				}
				echo "</tr>\n";  // end row for day
			}
			$lastDay = $obj[$DATEKey];
			if(!$suppressDateRows) {
				$label = longestDayAndDate(strtotime($obj[$dateKey]));
				if($obj['firstdayoff']) {
					$label = longestDayAndDate(strtotime($obj[$dateKey])).
					"<span style='font-weight:normal;'> (to ".longestDayAndDate(strtotime($obj['lastdayoff'])).')</span>';
					$label = "<span style='color:red;'>SITTER TIME OFF</span> $label";
				}
				
				// If HomePage WAG view, show daily revenue tally
				if($showRevenue) {
					$label .= " - today's revenue: ".dollarAmount($dailyTally[$obj[$dateKey]]);
				}
				$dateCount++;
				$shrinkWidget = "<img id='day-shrink-$dateCount' src='art/up-black.gif' width=12 height=12 title='Minimize or maximize this day.'>";

				$label = "<table width=100%><tr><td style='text-align:center;font-weight:bold'>$label</a></td>
																<td style='text-align:right;width:12px;'>$shrinkWidget</td></tr></table>";
				echo "\n<tr><td class=daycalendardaterow colspan=".count($timesOfDay)."  onClick='toggleDate(\"dateappointments_$dateCount\")'>$label</td>";
				echo "</tr>\n<tr id='dateappointments_$dateCount"."_headers'>";
			}
			
			$lastSUB = -999;
			$lastTOD = null;
			if(!$obj['firstdayoff']) {
				$widthStyle = "style='width: ".round(100/count($timesOfDay))."%'";
				foreach($timesOfDay as $k => $v) echo "<th class=daycalendartodheader $widthStyle>$v</th>";
				echo "</tr><tr id='dateappointments_$dateCount"."_row'>\n";
			}
			$todIndex = array_search($obj[$TODKey], $todKeys);
			for($i=0;$i<$todIndex;$i++) echo "<td>&nbsp;</td>";  // dump (empty) TOD cells preceding this cell
			//debug("#### lastTOD: $lastTOD [$todIndex]");
		}
		if($lastTOD != $obj[$TODKey]) {
			if($lastTOD) echo "</table></td>";// end col for TOD
			if($lastTOD && !$newDay) {
				$lastTODIndex = array_search($lastTOD, $todKeys);
			  for($i=$lastTODIndex+1; $i < count($todKeys) && $todKeys[$i] != $obj[$TODKey]; $i++) 
			    echo "<td>&nbsp;</td>";  // empty TOD cells
			}
			$todcellclass = $objectDisplayFn != 'dumpOneLineWAGAppointment' || array_search($obj[$TODKey], $todKeys) == 0
						? "'daycalendartodcellFIRST'" : "'daycalendartodcell'";
			//$extraStyle = $objectDisplayFn != 'dumpOneLineWAGAppointment' ? '' : "style='border-spacing: 0px;'";
			//array_search($obj[$TODKey], $todKeys) == 0 ? 'daycalendartodcellFIRST' : 'daycalendartodcell';
			echo /*$extraStyle*/"<td class=$todcellclass ><table class='daycalendartodcelltable' style='border-collapse:collapse;'>";  // start col for TOD
			$lastSUB = -999;
		}
		$lastTOD = $obj[$TODKey];
		
		if($SUBSECTIONKey) {
			global $providerColors;
			if($providerColors && $subSectionKey == 'provider') $subsectionLabelColorCSS = "background:{$providerColors[$obj['providerptr']]};";
			if($lastSUB != $obj[$subSectionKey]) {
				$totalSubsectionCount++;
				$subsectionPrefix = $SUBSECTIONKey."_".($totalSubsectionCount ? $totalSubsectionCount : '0').'_';
				$subsectionLabel = $obj[$subSectionKey] ? $obj[$subSectionKey] : "<span style='font-style:italic;color:red;'>Unassigned</span>";
				$shrinkWidget = "<img id='prov-shrink-$totalSubsectionCount' src='art/up-black.gif' width=12 height=12 title=\"Minimize or maximize this sitter's appointments.\">";
				if($simpleSubsectionSpacers) {
					$subsectionLabel = "<table width=100% style='margin:0px;'><tr><td>&nbsp;</td></tr></table>";
					echo "\n<tr><td style='border-width:0px;' >$subsectionLabel </td></tr>";
				}
				else {
					$subsectionLabel = "<table width=100% style='margin:0px;'><tr>
																<td class=daycalendarsubrow style='$subsectionLabelColorCSS'>$subsectionLabel ({$appointmentCounts[$totalSubsectionCount]})</td>
																<td class=daycalendarsubrow style='text-align:right;$subsectionLabelColorCSS'>$shrinkWidget</td></tr></table>";
					echo "\n<tr><td style='border: solid black 1px;' onClick='toggleSubsection(\"$subsectionPrefix\")'>$subsectionLabel </td></tr>";
				}
				$lastSUB = $obj[$subSectionKey];
				$sectionAppointmentCount = 0;
			}
			$sectionAppointmentCount++;
			$tileRowID = $subsectionPrefix."_$sectionAppointmentCount";
		}
		$tileRowID = $tileRowID ? "id='$tileRowID'" : '';
		
		if(!$obj['firstdayoff']) {
		
			echo "\n<tr $tileRowID>";
				// supply empty TOD cells if necessary
			if(!$simpleSubsectionSpacers)	echo "\n<td class='daycalendarobjectcell'>";
			call_user_func_array($objectDisplayFn, array($obj));
			if(!$simpleSubsectionSpacers)	echo "</td>\n";
			echo "</tr>";
		}
	}
	// close
	if($lastDay) {
		echo "</table></td>"; // for TOD array
		if(!$lastTOD) $lastTOD = $todKeys[0];
		if($lastTOD) {
			$lastTODIndex = array_search($lastTOD, $todKeys);
			for($i=$lastTODIndex+1;$i<count($todKeys);$i++) echo "<td class=$todcellclass>&nbsp;</td>";  // dump remaining (empty) TOD cells
		}
		echo "</tr>\n";  // end row for day
	}
  echo "</table>";
}

function getAppointmentCounts($objects) {
	// return the number of appointments in each provider subsection
	
	$lastProvider = -999;
	$lastTOD = -999;
	$lastDate = -999;
	foreach($objects as $appt) {
		if($appt['providerptr'] != $lastProvider || $appt['TODColumn'] != $lastTOD || $appt['date'] != $lastDate) {
			$n++;
			$counts[$n] = 0;
			$lastProvider = $appt['providerptr'];
			$lastTOD = $appt['TODColumn'];
			$lastDate = $appt['date'];
		}
//if(mattOnlyTEST() && $n == 1) print_r($appt);	
		$counts[$n] += 1;
	}
	return $counts;
}

function debug($str) {
	global $debugstr ;
	$debugstr .= "<p>$str";	
}

function dumpProviderAppointment($appt) {
	global $providerKeys, $clientKeys, $unrequiredClientKeys, $userRole, $displayOnly;
	if(!$userRole) $userRole = userRole();
	
	if($appt['timeoff']) {
		$tod = $appt['timeofday'] ? $appt['timeofday'] : 'All Day';
		echo <<<APPT
<table class='$class' style='width:100%'>
<tr><td valign='top'>{$appt['primaryname']}</td><td align=right valign='top'>{$tod}</td></tr>
APPT;
		if($appt['note']) {
			$notecolor = $_SESSION['preferences']['showVisitNotesInBlack'] ? 'black' : 'red';
			echo "<tr><td colspan=2 style='color:$notecolor;'>Note: {$appt['note']}</td></tr>\n";
		}
		echo "</table>";
	return;
	}
	
	$rate = dollarAmount($appt['rate']+$appt['bonus']);
	$charge = dollarAmount($appt['charge']+$appt['adjustment']);
	$class = appointmentCalendarDisplayClass($appt);
	$timeOfDay = $appt['timeofday'];
	if($userRole == 'c' && $_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI']) {
		$timeOfDay = '';
	}
	echo <<<APPT
	
<table class='$class' style='width:100%'>
<tr><td valign='top'>{$appt['primaryname']}</td><td align=right valign='top'>{$appt['service']}</td></tr>
<tr><td valign='top'>$timeOfDay</td><td align=right valign='top'>{$appt['pets']}</td></tr>
APPT;


//echo "<tr><td>".print_r($appt, 1)."";exit;

  if($appt['canceled'] && !$appt['pendingchange']) {
  	echo "\n<tr><td colspan=2 style='color:red;text-align:center;'>Canceled</td></tr>\n";
		multiChangeCheckBoxRow($appt); // iff enableclientuimultidaycancel
	}
  else {
		if(($userRole == 'o')) echo "\n<tr><td>Charge: $charge</td><td align=right>Rate: $rate</td></tr>\n";
		if(($userRole == 'p')) echo "\n<tr><td>&nbsp;</td><td align=right>Pay: $rate</td></tr>\n";
		if($appt['clientptr'] && !isset($clientKeys[$appt['clientptr']])) {
			$keys = getClientKeys($appt['clientptr']);
			if($keys) foreach($keys as $key)
				$clientKeys[$appt['clientptr']][] = $key['keyid'];
			$unrequiredClientKeys[$appt['clientptr']] = 
				fetchRow0Col0("SELECT nokeyrequired FROM tblclient WHERE clientid = {$appt['clientptr']} LIMIT 1");
		}

		if($appt['providerptr'] && !isset($providerKeys[$appt['providerptr']])) {
			$keys = getProviderKeys($appt['providerptr']);
			if($keys) foreach($keys as $key)
				$providerKeys[$appt['providerptr']][] = $key['keyid'];
		}
		if($unrequiredClientKeys[$appt['clientptr']]) $noKey = false;
		else {
			$cKeys = $clientKeys[$appt['clientptr']] ? $clientKeys[$appt['clientptr']] : array();
			$pKeys = $providerKeys[$appt['providerptr']] ? $providerKeys[$appt['providerptr']] : array();
			$noKey = array_diff($cKeys, $pKeys) == $cKeys;
		}
//echo 	"<tr><td style='text-align:center;color:red;' colspan=2>".print_r($appt)."</td></tr>";

		multiChangeCheckBoxRow($appt); // iff enableclientuimultidaycancel
	}
//echo "<tr><td colspan=2>".print_r($appt , 1);		
	require_once "appointment-client-notification-fns.php"; //isVisitReportClientViewable($appointmentid)

	// DO NOT KNOW WHY THE FOLLOWING LINE IS NECESSARY. $SELECTTESTMODE seems to get reset.
	$SELECTTESTMODE = $_SESSION['preferences']['enableclientuimultidaycancel'];
	
	
	if($userRole == 'c' && !$displayOnly) {
		// offer CANCEL and CHANGE buttons as appropriate
		if(!$appt['completed'] && appointmentFuturity($appt) >= 0) {
			echo "<tr><td style='text-align:left;'>";
			if($SELECTTESTMODE) {
				// no-op.  see bove
			}
			else {
				if($appt['pendingchange'] == 0 && !$appt['canceled'])
					echoButton('', 'Cancel', "cancelAppt({$appt['appointmentid']})");
				else if($appt['pendingchange'] < 0 || (!$appt['pendingchange'] && $appt['canceled']) )
					echoButton('', 'UnCancel', "uncancelAppt({$appt['appointmentid']})");
				echo "</td>\n";
				echo "<td style='text-align:right;'>";
				if($appt['pendingchange'] == 0 && !$appt['canceled'] && !$_SESSION['preferences']['suppressChangeButtonOnVisits'])
					echoButton('', 'Change', "changeAppt({$appt['appointmentid']})");
			}
			echo "</td></tr>\n";
		}
		else if((($showVR = isVisitReportClientViewable($appt['appointmentid']))) || $appt['completed'] || $appt['arrived']) {
			require_once "preference-fns.php";
			$showCompletion = $appt['completed']  && getClientPreference($appt['clientptr'], 'showClientCompletionDetails');
			$showArrival = $appt['arrived']  && getClientPreference($appt['clientptr'], 'showClientArrivalDetails');
			if($showCompletion || $showArrival) { 
				if($showArrival) $notation[] = "Arrived: ".shortDateAndTime(strtotime($appt['arrived']));
				if($showCompletion) $notation[] = "Marked complete: ".shortDateAndTime(strtotime($appt['completed']));
				$notation = join("\n", $notation);
				if($showVR) $onclick = "onclick=\"showVisitReport({$appt['appointmentid']})\"";
				echo "<td style='text-align:right;'><img src='art/smiley.gif' $onclick title='$notation'></td>";
			}
		}
		
	}
	else if($appt['origprovider']) {
		$noKey = $noKey && !$displayOnly ? ' - '.noKeyLink($appt['clientptr']) : ''; 
		echo "<tr><td colspan=2 style='text-align:center;'>Reassigned from: {$appt['origprovider']} $noKey</td></tr>\n";
	}
	else if($noKey && !$displayOnly) {
		$noKey = noKeyLink($appt['clientptr']); 
		echo "<tr><td colspan=2 style='text-align:center;'>$noKey</td></tr>\n";
	}
	$notecolor = $_SESSION['preferences']['showVisitNotesInBlack'] ? 'black' : 'red';
	if(dbTEST('wisconsinpetcare') && userRole() != 'c'/*dbTEST('wisconsinpetcare') || $_SESSION['showpackagenotesincalendar']*/) { // dbTEST('wisconsinpetcare')
		if(!$appt['note']) {
			require_once "service-fns.php";
			static $packageNotes;
			if(!array_key_exists($appt['packageptr'], (array)$packageNotes)) {
				$packageptr = findCurrentPackageVersion($appt['packageptr'], $appt['clientptr'], $appt['recurringpackage']);
				$packageTable = $appt['recurringpackage'] ? 'tblrecurringpackage' : 'tblservicepackage';
				$packageNotes[$appt['packageptr']] = fetchRow0Col0("SELECT notes FROM $packageTable WHERE packageid=$packageptr LIMIT 1");
			}
			$appt['note'] = $packageNotes[$appt['packageptr']];
		}
	}
	if($appt['note']) echo "<tr><td colspan=2 style='color:$notecolor;'>Note: {$appt['note']}</td></tr>\n";
	if($_SESSION['preferences']['showClientPaidVisits'] && userRole() == 'c') {
		$paidInFull = fetchFirstAssoc(
			"SELECT * FROM tblbillable 
			WHERE itemptr = {$appt['appointmentid']} 
				AND superseded = 0 LIMIT 1", 1);
		if($paidInFull['paid'] < $paidInFull['charge']) $paidInFull = null;
		if($paidInFull) echo "<tr><td style='color:darkgreen;font-weight:bold' title='Paid in full.'>Paid in full.</td>";
	}
  echo "</table>";
}

function multiChangeCheckBoxRow(&$appt) {
	$suppressCheckBox = !in_array(userRole(), array('c'));

	$SELECTTESTMODE = $_SESSION['preferences']['enableclientuimultidaycancel'];

	if(!$SELECTTESTMODE && $appt['pendingchange']) {
		echo "<tr>";
		echo "<td style='text-align:center;color:red;' colspan=2>";
		echo $appt['pendingchange'] < 0 ? 'Cancellation' : 'Change';
		echo " Pending</td>";
		echo "</tr>";
	}
	else if($SELECTTESTMODE) {
		if($_SESSION['preferences']['warnOfLateScheduling'] 
				&& ($warningDays = $_SESSION['preferences']['lastSchedulingDays'])
				&& ($warningDays = 0 + $warningDays)) {
			$daysAhead = (strtotime($appt['date']) - time()) / (60 * 60 *24);
			$extraInputClass = $daysAhead < 0 ? "visitIsInThePast" : (
												 $daysAhead <= $warningDays ? "visitIsSoon" : "");
		}
		echo "<tr>";
		echo "<td style='color:red;' colspan=2>";
		if(!$suppressCheckBox) labeledCheckbox('', "visit_{$appt['appointmentid']}", $value=null, $labelClass=null, $inputClass="visitcheckbox $extraInputClass", $onClick="visitCheckBoxClicked(this)", $boxFirst=true, $noEcho=false, $title='Select this visit for modification.');
		if($appt['pendingchange']) {
			echo "<div style='display:inline;float:right;'>";
			echo ($appt['pendingchange'] < 0 ? 'Cancellation' : 'Change')." Pending";
			echo "</div>";
		}
		echo "</td></tr>";
	}
}

function dumpOneLineWAGAppointment($appt) {
	//tinycheck.gif 9x10
	global $providerKeys, $clientKeys, $unrequiredClientKeys, $providerNames, $nonRecurringRanges, $nonRecStartAndFinishAppts,
					$lastDayVisits, $providerColors;
	$providerName = $providerNames[$appt['providerptr']] ? $providerNames[$appt['providerptr']] : '<font color=red>Unassigned</font>';
	if($providerColors) $providerColorCSS = "background:{$providerColors[$appt['providerptr']]};";
	$class = appointmentCalendarDisplayClass($appt);
	$check = $appt['timeoff'] ? '' : (
					 $appt['completed'] ? "<img src='art/tinycheck.gif'>"
					 : "<img src='art/spacer.gif' width=9 height=10>");
									//: ($appt['canceled'] ? "<img src='art/tinyx.gif'>" : "<img src='art/spacer.gif' width=9 height=10>");
	if($appt['recurring'] || $appt['onedaypackage']) $firstOrLast = '';
	else {
		$firstOrLastState = $nonRecurringRanges[$appt['packageptr']]['start'] == $appt['date'] ? 'first'
										: ($nonRecurringRanges[$appt['packageptr']]['end'] == $appt['date'] ? 'last' : '');
		$firstOrLast = $firstOrLastState == 'first' ? '<b><font color=red>S</font></b>'
										: ($firstOrLastState == 'last' ? '<b><font color=red>F</font></b>' : '');
		if($firstOrLastState == 'first') {
			if($nonRecStartAndFinishAppts[$appt['currentpackageptr']]['first']) $firstOrLast = '';
			else $nonRecStartAndFinishAppts[$appt['currentpackageptr']]['first'] = 1;
		}
		if($firstOrLastState == 'last') {
			require_once "service-fns.php";
			if(!$lastDayVisits[$appt['currentpackageptr']]) {
				$pack = $appt['currentpackageptr'] ? $appt['currentpackageptr'] : $appt['packageptr'];
				$allAppts = fetchAllAppointmentsForNRPackage($pack, $appt['clientptr']);
				$lastDayVisits[$appt['currentpackageptr']] = !$allAppts ? 1 : $allAppts[count($allAppts)-1]['appointmentid'];
			}
			if($lastDayVisits[$appt['currentpackageptr']] != $appt['appointmentid']) $firstOrLast = '';
			else $nonRecStartAndFinishAppts[$appt['currentpackageptr']]['last'] = $appt['appointmentid'];
		}
		
		
	}
	$charge = "$firstOrLast{$appt['chargelink']}"; // 'charge' includes 'adjustment'
	$tinyClock = tinyClockLink($appt);
	$noKey = getNoKey($appt);
	$noKey = $noKey ? ' '.noKeyIconLink($appt['clientptr'], '', 1, 9) : '';
	$noteButton = tinyNoteLink($appt);
	if($appt['timeoff'])
		$timeDisplay = $appt['timeofday'] ? $appt['timeofday'] : 'All Day';
	else if($appt['starttime']) 
		$timeDisplay = $appt['starttime'] == $appt['endtime'] ? substr(date('g:ia', strtotime($appt['starttime'])), 0, -1) : '';

	if($timeDisplay) $timeDisplay = "<font color='red'> $timeDisplay</font>";
	
	if($appt['appointmentid'] && appointmentFuturity($appt) >= 0) {
		if($appt['canceled']) {
			$cancelArg = 0;
			$imgsrc = 'tinyundeletebutton.gif';
			$bTitle = 'Uncancel this visit.';
		}
		else {
			$cancelArg = 1;
			$imgsrc = 'tinyxbutton.gif';
			$bTitle = 'Cancel this visit.';
		}
		$cancelUncancelButton = "<div class='tinyButton' onClick='cancelAppt({$appt['appointmentid']}, $cancelArg)'>".
															"<img title='$bTitle' border=0 bordercolor=darkgray src='art/$imgsrc'></div>";
	}
	else $cancelUncancelButton = null;

if(TRUE || mattOnlyTEST()) {
	$style = array();
	if($appt['canceled']) $style[] = 'text-decoration:line-through;';
	if($appt['highpriority']) {
		$highPriority = "class='highprioritytask'";
	}
	$style = "style='".join(' ', $style)."'";
}
else {
	if($appt['canceled']) $strikeout = "style='text-decoration:line-through;'";
	if($appt['highpriority']) $highPriority = "class='highprioritytask'";
}
	echo <<<APPT
<tr $strikeout $style $highPriority><td style='padding-top:4px;'>$check{$appt['primaryname']}$tinyClock$noKey$noteButton $timeDisplay</td>
<td style='padding-top:4px;$providerColorCSS'>$providerName</td>
<td class='chargeCell'>$charge&nbsp;$cancelUncancelButton</td> </tr>
APPT;
}

function tinyClockLink($row) {
	$charge = $row['canceled'] ? "<span style='font-size:0.8em;'>CANCELED</span>" : dollarAmount($row['charge']);
	return "<img src='art/tinyclock.gif' title='{$row['timeofday']}'  class='tinyButton'
	       onClick='apptEd({$row['appointmentid']})'>";
}

function tinyNoteLink($row) {
	if(!$row['note']) return '';
	$targetPage = 'appointment-edit.php';
	return " <img src='art/tinynote.gif' title='{$row['note']}'  style='cursor:pointer;display:inline;'
	       onClick='openConsoleWindow(\"editappt\", \"$targetPage?updateList=&id={$row['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})'>";
}	
	
function dumpBriefWAGAppointment($appt) {
	global /*$providerKeys, $clientKeys, $unrequiredClientKeys,*/ $showWagReassignmentSource;
	$rate = dollarAmount($appt['rate']);
	$charge = dollarAmount($appt['charge']);
	$class = appointmentCalendarDisplayClass($appt);
	if($appt['highpriority']) { //  && mattOnlyTEST()
		$class = "$class highprioritytask";
	}

	echo <<<APPT
<table class='$class' style='width:100%'>
<tr><td>{$appt['primaryname']}</td><td align=right>{$appt['service']}</td></tr>
APPT;


//echo "<tr><td>".print_r($appt, 1)."";exit;

  if($appt['canceled'] && !$appt['pendingchange']) echo "
<tr><td colspan=2 style='color:red;text-align:center;'>Canceled</td></tr>\n";

	$noKey = getNoKey($appt);
//echo 	"<tr><td style='text-align:center;color:red;' colspan=2>".print_r($appt)."</td></tr>";
	if($appt['pendingchange']) {
			echo "<tr><td style='text-align:center;color:red;' colspan=2>";
			echo $appt['pendingchange'] < 0 ? 'Cancellation' : 'Change';
			echo " Pending</td></tr>";
	}
//echo "<tr><td colspan=2>".print_r($appt , 1);		
  $noKey = $noKey ? noKeyIconLink($appt['clientptr'], '', 1) : '';
	if($appt['appointmentid'] && appointmentFuturity($appt) >= 0) {
		if($appt['canceled']) {
			$cancelArg = 0;
			$imgsrc = 'undelete.gif';
			$bTitle = 'Uncancel this visit.';
		}
		else {
			$cancelArg = 1;
			$imgsrc = 'delete.gif';
			$bTitle = 'Cancel this visit.';
		}
		$cancelUncancelButton = "<div style='cursor:pointer;display:inline' onClick='cancelAppt({$appt['appointmentid']}, $cancelArg)'>".
															"<img title='$bTitle' height=13 width=13 border=1 bordercolor=darkgray src='art/$imgsrc'></div>";
	}
	else $cancelUncancelButton = null;
	
	if($appt['note'])
		$noteButton = "<div style='cursor:pointer;display:inline' onClick='showNote(\"note_{$appt['appointmentid']}\")'>".
															"<img title='Show note' height=15 width=15 src='art/note.gif'></div>";	
	
	if($appt['providerptr']) {
		$textMessageButton = false && "<div style='cursor:pointer;display:inline' onClick='openComposer({$appt['providerptr']})'>".
															"<img title='Text message to {$appt['provider']}' height=15 width=15 src='art/text-message.gif'></div>";	
		$emailButton = "<div style='cursor:pointer;display:inline' onClick='openComposer({$appt['providerptr']})'>".
															"<img title='Email message to {$appt['provider']}' height=15 width=15 src='art/email-message.gif'></div>";	
  }
  $tod = $appt['timeofday'];
	if($appt['timeoff']) {
		$tod = $appt['timeofday'] ? $appt['timeofday'] : 'All Day';
	}
	else $tod = briefTimeOfDay($appt); 
	echo "<tr><td>{$tod}</td><td colspan=2 style='text-align:right;'>";

	if($noKey || $cancelUncancelButton || $noteButton || $appt['providerptr']) {
		foreach(array($noKey, $noteButton, $textMessageButton, $emailButton, $cancelUncancelButton) as $button)
			if($button) echo $button.' ';
	}
	echo "</td></tr>\n";
	if($noteButton)
		echo "<tr style='display:none;' id='note_{$appt['appointmentid']}'><td colspan=2 style='text-align:center;'>{$appt['note']}</td></tr>\n";
	if($showWagReassignmentSource && $appt['origprovider'])
			echo "<tr><td colspan=2 style='text-align:center;'>Reassigned from: {$appt['origprovider']}</td></tr>\n";
			
  echo "</table>";
}

function getNoKey($appt) {
	global $clientKeys, $unrequiredClientKeys, $providerKeys;
	$noKey = false;
	if($appt['clientptr'] && !isset($clientKeys[$appt['clientptr']])) {
		$keys = getClientKeys($appt['clientptr']);
		$clientKeys[$appt['clientptr']] = array();
		if($keys) foreach($keys as $key)
			$clientKeys[$appt['clientptr']][] = $key['keyid'];
		$unrequiredClientKeys[$appt['clientptr']] = 
			fetchRow0Col0("SELECT nokeyrequired FROM tblclient WHERE clientid = {$appt['clientptr']} LIMIT 1");
	}

	if($appt['providerptr'] && !isset($providerKeys[$appt['providerptr']])) {
		$keys = getProviderKeys($appt['providerptr']);
		if($keys) foreach($keys as $key)
			$providerKeys[$appt['providerptr']][] = $key['keyid'];
	}
	if($unrequiredClientKeys[$appt['clientptr']]) $noKey = false;
	else if(!$clientKeys[$appt['clientptr']]) $noKey = false;
	else {
		$cKeys = $clientKeys[$appt['clientptr']] ? $clientKeys[$appt['clientptr']] : array();
		$pKeys = $providerKeys[$appt['providerptr']] ? $providerKeys[$appt['providerptr']] : array();
		$noKey = array_diff($cKeys, $pKeys) == $cKeys;
	}
	return $noKey;
}

function dumpWAGAppointment($appt) {
	global $providerKeys, $clientKeys, $unrequiredClientKeys;
	$rate = dollarAmount($appt['rate']);
	$charge = dollarAmount($appt['charge']);
	$class = appointmentCalendarDisplayClass($appt);
	echo <<<APPT
<table class='$class' style='width:100%'>
<tr><td>{$appt['primaryname']}</td><td align=right>{$appt['service']}</td></tr>
<tr><td colspan=2>{$appt['timeofday']}</td></tr>
APPT;


//echo "<tr><td>".print_r($appt, 1)."";exit;

  if($appt['canceled'] && !$appt['pendingchange']) echo "
<tr><td colspan=2 style='color:red;text-align:center;'>Canceled</td></tr>\n";

	if($appt['clientptr'] && !isset($clientKeys[$appt['clientptr']])) {
		$keys = getClientKeys($appt['clientptr']);
		if($keys) foreach($keys as $key)
			$clientKeys[$appt['clientptr']][] = $key['keyid'];
		$unrequiredClientKeys[$appt['clientptr']] = 
			fetchRow0Col0("SELECT nokeyrequired FROM tblclient WHERE clientid = {$appt['clientptr']} LIMIT 1");
	}

	if($appt['providerptr'] && !isset($providerKeys[$appt['providerptr']])) {
		$keys = getProviderKeys($appt['providerptr']);
		if($keys) foreach($keys as $key)
			$providerKeys[$appt['providerptr']][] = $key['keyid'];
	}
	if($unrequiredClientKeys[$appt['clientptr']]) $noKey = false;
	else {
		$cKeys = $clientKeys[$appt['clientptr']] ? $clientKeys[$appt['clientptr']] : array();
		$pKeys = $providerKeys[$appt['providerptr']] ? $providerKeys[$appt['providerptr']] : array();
		$noKey = array_diff($cKeys, $pKeys) == $cKeys;
	}
//echo 	"<tr><td style='text-align:center;color:red;' colspan=2>".print_r($appt)."</td></tr>";
	if($appt['pendingchange']) {
			echo "<tr><td style='text-align:center;color:red;' colspan=2>";
			echo $appt['pendingchange'] < 0 ? 'Cancellation' : 'Change';
			echo " Pending</td></tr>";
	}
//echo "<tr><td colspan=2>".print_r($appt , 1);		
  $noKey = $noKey ? noKeyIconLink($appt['clientptr'], '', 1) : '';
	if($appt['origprovider']) {
		echo "<tr><td colspan=2 style='text-align:center;'>Reassigned from: {$appt['origprovider']}</td></tr>\n";
	}
	if($appt['appointmentid'] && appointmentFuturity($appt) >= 0) {
		if($appt['canceled']) {
			$cancelArg = 0;
			$imgsrc = 'undelete.gif';
			$bTitle = 'Uncancel this visit.';
		}
		else {
			$cancelArg = 1;
			$imgsrc = 'delete.gif';
			$bTitle = 'Cancel this visit.';
		}
		$cancelUncancelButton = "<div style='cursor:pointer;display:inline' onClick='cancelAppt({$appt['appointmentid']}, $cancelArg)'>".
															"<img title='$bTitle' height=15 width=15 border=1 bordercolor=darkgray src='art/$imgsrc'></div>";
	}
	else $cancelUncancelButton = null;
	
	if($appt['note'])
		$noteButton = "<div style='cursor:pointer;display:inline' onClick='showNote(\"note_{$appt['appointmentid']}\")'>".
															"<img title='Show note' height=15 width=15 src='art/note.gif'></div>";	
	
	if($appt['providerptr']) {
		$textMessageButton = "<div style='cursor:pointer;display:inline' onClick='openComposer({$appt['providerptr']})'>".
															"<img title='Text message to {$appt['provider']}' height=15 width=15 src='art/text-message.gif'></div>";	
		$emailButton = "<div style='cursor:pointer;display:inline' onClick='openComposer({$appt['providerptr']})'>".
															"<img title='Email message to {$appt['provider']}' height=15 width=15 src='art/email-message.gif'></div>";	
  }
	
	if($noKey || $cancelUncancelButton || $noteButton || $appt['providerptr']) {
		echo "<tr><td colspan=2 style='text-align:left;'>";
		foreach(array($noKey, $noteButton, $textMessageButton, $emailButton) as $button)
			if($button) echo $button.' ';

		echo "<td align='right'>".($cancelUncancelButton ? $cancelUncancelButton : '')."</td></tr>\n";
		if($noKey) {
		}
		if($noteButton)
			echo "<tr style='display:none;' id='note_{$appt['appointmentid']}'><td colspan=2 style='text-align:center;'>{$appt['note']}</td></tr>\n";
	}
  echo "</table>";
}

function appointmentCalendarDisplayClass($appt) {
	$futurity = appointmentFuturity($appt);
	if($appt['canceled']) $calClass = 'daycalendarappointmentcanceled';
	else if($appt['completed']) $calClass = 'daycalendarappointmentcomplete';
	else if($futurity == -1) {
		/*if($appt['completed']) $calClass = 'daycalendarappointmentcomplete';
		else */$calClass = 'daycalendarappointmentnoncompleted';
	}
	else {
		if($appt['highpriority'])$calClass = 'daycalendarappointmenthighpriority' ;
		else $calClass = 'daycalendarappointment';
	}
	return $calClass;
}
	

function dumpNarrowProviderAppointment($appt) {
	$rate = dollarAmount($appt['rate']);
	$charge = dollarAmount($appt['charge']);
	$class = appointmentCalendarDisplayClass($appt);
	echo <<<APPT
	
<table class='$class' style='width:100%'>
<tr><td>{$appt['primaryname']}</td></tr>
<tr><td>{$appt['timeofday']}</td></tr>
<tr><td align=right>{$appt['service']}</td></tr>
<tr><td>Rate: $rate</td></tr>
<tr><td align=right>Charge: $charge</td></tr>
APPT;
	if($_SESSION['preferences']['showClientPaidVisits'] && userRole() == 'c') {
		$paidInFull = fetchFirstAssoc(
			"SELECT * FROM tblbillable 
			WHERE itemptr = {$appt['appointmentid']} 
				AND superseded = 0 LIMIT 1", 1);
		if($paidInFull['paid'] < $paidInFull['charge']) $paidInFull = null;
		if($paidInFull) echo "<tr><td style='color:darkgreen;font-weight:bold' title='Paid in full.'>Paid in full.</td>";
	}
	echo "</table>";
}

function dayCalendarSort($a, $b) {
  global $TODKey, $DATEKey, $SUBSECTIONKey, $TODKeys, $SORTSUBSECTIONS, $TIMESOFDAY;
	if($a[$DATEKey] < $b[$DATEKey]) return -1;
	else if($a[$DATEKey] > $b[$DATEKey]) return 1;
	else {
		$aTODIndex = array_search($a[$TODKey], $TODKeys);
		$bTODIndex = array_search($b[$TODKey], $TODKeys);
    if($aTODIndex < $bTODIndex) return -1;
    else if($aTODIndex > $bTODIndex) return 1;
    else if(!($SUBSECTIONKey && $SORTSUBSECTIONS)) return timeSort($a, $b);
    else {
      if($a[$SUBSECTIONKey] < $b[$SUBSECTIONKey]) return -1;
      else if($a[$SUBSECTIONKey] > $b[$SUBSECTIONKey]) return 1;
      else return timeSort($a, $b);
		}
	}
}

function timeSort($a, $b) {
	if($a['starttime'] < $b['starttime']) return -1;
	else if($a['starttime'] > $b['starttime']) return 1;
	else return 0;
}
