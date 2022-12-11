<? // timeoff-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "time-framer-mouse.php";
require_once "provider-fns.php";
// id = id of tbltimeoffinstance


/*
Installation:

1. Define tables below in all databases.

2. call convertAllOldTimesOffToNew() for all databases

3. Code changes:
- timeoff-sitter-calendar.php - replace with timeoff-sitter-calendar-NEW.php
- provider-fns.php
- - eliminate all functions that call if(useNEWTimeOffFunctionaliy())
- - change all "function NEW" to "function "



CREATE TABLE IF NOT EXISTS `relwipedappointment` (
  `providerptr` int(11) NOT NULL,
  `appointmentptr` int(11) NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY  (`appointmentptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `tbltimeoffinstance` (
  `patternptr` int(10) unsigned NOT NULL default '0',
  `date` date NOT NULL default '0000-00-00',
  `timeofday` varchar(45) default NULL,
  `providerptr` int(10) unsigned NOT NULL default '0',
  `timeoffid` int(10) unsigned NOT NULL auto_increment,
  `note` text,
  `created` datetime default NULL,
  `createdby` int(11) default NULL,
  `modified` datetime default NULL,
  `modifiedby` int(11) default NULL,
  UNIQUE KEY `Index_2` (`timeoffid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


CREATE TABLE IF NOT EXISTS `tbltimeoffpattern` (
  `patternid` int(10) unsigned NOT NULL auto_increment,
  `date` date NOT NULL default '0000-00-00',
  `until` date NOT NULL default '0000-00-00',
  `timeofday` varchar(45) default NULL,
  `providerptr` int(10) unsigned NOT NULL default '0',
  `note` text,
  `pattern` varchar(20) default NULL,
  `created` datetime default NULL,
  `createdby` int(11) default NULL,
  `modified` datetime default NULL,
  `modifiedby` int(11) default NULL,
  UNIQUE KEY `Index_2` (`patternid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

*/



$auxiliaryWindow = true; // prevent login from appearing here if session times out
extract(extractVars('id,date,prov,timeofday,note,delete,provsummary,preview', $_REQUEST));

$blackOutsEnabled = TRUE; //fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableTimeoffBlackouts'", 1); //dbTEST('dogslife');

if($prov == 'blackoutId') $prov = getTimeOffBlackoutId();
if(userRole() == 'p') {
	$locked = locked('p-');
	$prov = $_SESSION["providerid"];
	$editable = $_SESSION['preferences']['offerTimeOffProviderUI'];
}
else {
	if(userRole() == 'd') locked('#es');
	else $locked = locked('o-');
	$editable = true;
	$pastEditingAllowed = true;
}

if($preview) { // fetch unassignment preview for timeoff args
	require_once "gui-fns.php";
	$sitter = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$_GET['prov']} LIMIT 1", 1);
	$_GET['tod'] = $_GET['tod'] == 'undefined' ? '' : $_GET['tod'];
	$appts = previewTimeOffUnassignments(simulatedTimeOff($_GET));
	require_once "frame-bannerless.php";
	echoButton('', 'Done', 'window.close();');
	echo "<p>";
	if(!$appts) echo "<span class='tiplooks'>No visits will be unassigned if this time off is saved.</span>";
	else {
		echo "The following visits assigned to $sitter will be unassigned if this time off is saved<p><table>";
		foreach($appts as $appt) {
			if($adate != $appt['date']) echo "<tr><td colspan=3 style='border-top: solid gray 1px'>".shortDate(strtotime($appt['date']))."</td></tr>";
			$adate = $appt['date'];
			$rowclass = $appt['canceled'] ? 'canceledtask' : '';
			if(userRole() != 'p') $appt['label'] = fauxLink($appt['label'], "editVisit({$appt['appointmentid']})", 1, 'Edit his visit');
			echo "<tr  class='$rowclass'><td>{$appt['client']}</td><td>{$appt['timeofday']}</td><td>{$appt['label']}</td>";
		}
		echo "</table>";
		echo "<script language='javascript' src='common.js'></script>";
		$editor = adequateRights('#ev') ? 'appointment-edit.php' : 'appointment-view.php';
		echo "<script language='javascript'>
					function update(aspect, returnHTML) {
						document.location.href=\"timeoff-edit.php?{$_SERVER["QUERY_STRING"]}\";
						window.opener.update('daySummary', '');
					}
					function editVisit(apptid) {
						var url = '{$editor}?id=';
						openConsoleWindow('appt', url+apptid,530,550)
					}
					</script>";
	}
	exit;
}

if($provsummary) { // fetch summary for provider $provsummary and $date
	require_once "appointment-fns.php"; // for function briefTimeOfDay($appt) 
	require_once "pet-fns.php";

	if(in_array(userRole(), array('o', 'd')) && $_SESSION['preferences']['enableTimeoffPreview'] && $provsummary != -999) {
		if(strtotime(date('Y-m-d')) > strtotime($date)) echo "This date is in the past.";
		else fauxLink('Preview Visit Unassignments', "previewUnassignedVisits()", null, 'Show what visits will be unassigned if this time off is saved.', 'previewLink');
	}
	echo "<h2 class='fontSize1_2em'>Visits on ".month3Date(strtotime($date))."</h2>";
	$visits = fetchAssociations($sql = 
		"SELECT timeofday, clientptr, lname, pets, canceled
		 FROM tblappointment
		 LEFT JOIN tblclient ON clientid = clientptr
		 WHERE providerptr = $provsummary AND date = '$date'
		 ORDER BY starttime");
	if(!$visits) echo "<span class='tiplooks'>None found.</span><p>";
	else {
		foreach($visits as $visit) $clientIds[] = $visit['clientptr'];
		echo "<table style='background:white;width:150px' border=1 bordercolor=black>";
		foreach($visits as $visit) {
			$rowclass = $visit['canceled'] ? 'canceledtask' : '';
			echo "<tr class='$rowclass'><td>".briefTimeOfDay($visit)."</td><td>{$visit['lname']}"
						.($visit['pets'] ? " ({$visit['pets']})" : '')
						.'</td></tr>';
		}
		echo "</table>";
	}
	exit;
}
	

}
if($_POST) {
	}
	require_once "request-fns.php";
	if($delete) {
		$xfaction = 'Deleted';
		$firstDayToDelete = fetchRow0Col0("SELECT date FROM tbltimeoffinstance WHERE timeoffid = $id");
		$patternrow = getPatternForInstance($id);
}
		if($delete == 'all' ||
				($delete == 'following' && $firstDayToDelete == $patternrow['date'])) {
			$xfaction .= ' Time Off Group';
			$changedObj = $patternrow;
			$patternDescription = patternDescription($patternrow, $full=true);
			if($patternrow['patternid']) {
				deleteTable('tbltimeoffinstance', "patternptr = {$patternrow['patternid']}", 1);
				deleteTable('tbltimeoffpattern', "patternid = {$patternrow['patternid']}", 1);
				$descr = changeLogStart($patternrow).'|'.patternDescription($patternrow);
				logChange($patternrow['patternid'], 'tbltimeoffpattern', 'd', $descr);
			}
		}
		else if($delete ==  'following'){
			$xfaction .= ' Time Off Group starting '.date('m/d/Y', $firstDayToDelete);
			$changedObj = $patternrow;
			$patternDescription = patternDescription($patternrow, $full=true);
			if($patternrow['patternid']) {
				deleteTable('tbltimeoffinstance', "patternptr = {$patternrow['patternid']} AND date >= '$firstDayToDelete'", 1);
				//deleteTable('tbltimeoffpattern', "patternid = {$patternrow['patternid']}", 1);
				$descr = changeLogStart($patternrow).'|'.patternDescription($patternrow);
				logChange($patternrow['patternid'], 'tbltimeoffpattern', 'd', $descr."|starting$firstDayToDelete");
			}
		}
		else {
			$changedObj = fetchFirstAssoc("SELECT * FROM tbltimeoffinstance WHERE timeoffid = $id LIMIT 1", 1);
			deleteTable('tbltimeoffinstance', "timeoffid = $id", 1);
			logChange($id, 'tbltimeoffinstance', 'd', changeLogStart($changedObj));
			}
	}
	else if($id) {
		$xfaction = 'Updated';
		$changedObj = fetchFirstAssoc("SELECT * FROM tbltimeoffinstance WHERE timeoffid = $id LIMIT 1", 1);
		if($changedObj['timeofday'] != $timeofday) $delta[] = 'timeofday';
		if($changedObj['note'] != $note) $delta[] = 'note';
		$delta = join(', ', (array)$delta);
		//updateTable('tbltimeoffinstance', timeStamp(array('timeofday'=>$timeofday, 'note'=>$note), 0), "timeoffid = $id", 0);
		$updateResult = updateTimeOff($id, $timeofday, $note);
		if(is_array($updateResult)) {
			$noCalendarUpdate = !$added['primaryTO'];
			$expl = '<p>You cannot schedule time off during the BLACKOUT periods shown.<br>Please contact your manager if you have questions.<br>';
			$errorHTML = "<h2>DECLINED</h2>$expl<ul><li>{$updateResult['error']}</ul>"; //join('<li>', (array)$added['errors']).'</ul>';
			$errorHTML = str_replace("\n", "", $errorHTML);
		}
		else {
			$changedObj = fetchFirstAssoc("SELECT * FROM tbltimeoffinstance WHERE timeoffid = $id LIMIT 1", 1);
			logChange($id, 'tbltimeoffinstance', 'm', changeLogStart($changedObj).'|'.$delta);
		}
	}
	else {
		$xfaction = 'Added';
		//$id = addTimeOff($_POST, $prov);
		$added = addTimeOff($_POST, $prov);
		if($id = $added['primaryTO']) {
			$changedObj = fetchFirstAssoc("SELECT * FROM tbltimeoffinstance WHERE timeoffid = $id LIMIT 1", 1);
			$descr = changeLogStart($changedObj);
	//echo "post: ".print_r($changedObj, 1)."<P>[$id] $descr<p>".print_r($changedObj, 1);exit;		
			if($changedObj['patternptr']) $descr .= '|'.patternDescription($patternrow = getPatternForInstance($id));
			logChange($id, 'tbltimeoffinstance', 'c', $descr);
		}
		if($added['errors']) {
			$noCalendarUpdate = !$added['primaryTO'];
			$expl = '<p>You cannot schedule time off during the BLACKOUT periods shown.<br>Please contact your manager if you have questions.<br>';
			$errorHTML = "<h2>DECLINED</h2>$expl<ul><li>".join('<li>', $added['errors']); //join('<li>', (array)$added['errors']).'</ul>';
			$errorHTML = str_replace("\n", "", $errorHTML);
		}
	}
	
	if($changedObj['providerptr']) unwipeAppointments($changedObj['providerptr']);
	
	if(!$delete && $changedObj) {
		if($changedObj['providerptr'] == getTimeOffBlackoutId()) $pname = 'BLACKOUT';
		else $pname = fetchRow0Col0("SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider WHERE providerid = {$changedObj['providerptr']}");
		$oldUnassignedAppts = getUnassignedAppointmentIDsDuringTimeOff($changedObj['providerptr']);  // collect unassigned appts which may be reassigned
		$unassignedAppointments = applyProviderTimeOffToAppointments($changedObj['providerptr'], $oldUnassignedAppts, $date);
		if(userRole() == 'p') $unassignedAppointments = array();
		if($unassignedAppointments) {
			require "frame-bannerless.php";
			$apptList = appointmentsUnassignedFrom($changedObj['providerptr']);  // date and appointment id
			// for now, we will provide a link to the reassignment page for the first appointment date.
			// later we'll find a way to zero in on the appointments unassigned from this user
//print_r($apptList);	exit;	
			$starting = "&date=$date"; // {$apptList[0]['date']}
			echo "<script type=\"text/javascript\" src=\"jquery_1.3.2_jquery.min.js\"></script>
						<script language='javascript'>
							function goGrandpa(url) {
								if(parent && parent.opener) {parent.opener.location.href=url;parent.opener.focus();parent.$.fn.colorbox.close();}
							}
							$.ready($(window).unload(function() {if(parent.refresh) parent.refresh();}));
						</script>";
			echo "<h2 class='fontSize1_2em'>Newly Unassigned Visits</h2>
						<p align=center><span class='fontSize1_1em'>$unassignedAppointments of {$pname}'s appointments "
						.($unassignedAppointments == 1 ? 'was' : 'were')
						." just unassigned because of the sitter's time off. Please go to "
						."<span class='fontSize1_2em'>"
						."<a href='javascript:goGrandpa(\"job-reassignment.php?fromprov=-1$starting\")'>"
						."<p align=center>Job Reassignment</a></span><p align=center>to assign them to other sitters.<p>"
						.echoButton('', 'Close', 'parent.$.fn.colorbox.close();if(parent.refresh) parent.refresh();', null, null, 'noecho', 'Just close this dialog')
						."</span>";
		}
	}
	
	$changedObj = $changedObj ? $changedObj : (
								$patternrow ? $patternrow : (
								$id ? fetchFirstAssoc("SELECT * FROM tbltimeoffinstance WHERE timeoffid = $id LIMIT 1") :
								null));
								
	if($changedObj && userRole() == 'p') { // in rare cases, (doubleclick?) $changedObj may be null
		//  generate request
		$sitterName = fetchRow0Col0(
				"SELECT CONCAT_WS(' ', fname, lname) as name 
					FROM tblprovider 
				WHERE providerid = {$changedObj['providerptr']} LIMIT 1");
		$extrafields = "<extrafields>";
		$extrafields .= "<extra key='x-label-Sitter'><![CDATA[$sitterName]]></extra>"
										."<hidden key='providerid'>{$changedObj['providerptr']}</hidden>";
		if($delete == 'all') {
			$subject = $xfaction;
			$extrafields .= "<extra key='x-label-$xfaction'>";
			$extrafields .= referenceLink($patternDescription, null, $changedObj['date']);
			$extrafields .= "</extra>";
		}
		else  {
			//mattOnlyTEST() || dbTEST('queeniespets,tonkatest')
			if($_SESSION['preferences']['enableDeleteTimeOffLink']) if($xfaction == 'Added' || $xfaction == 'Updated') {
				$reverseActionObjectId = $xfaction == 'Added' ? $changedObj['patternptr'] : $changedObj['timeoffid'];
				$actionKey = $xfaction == 'Added' ? 'added_pattern' : 'updated_instance';
				// ... but, added singleton timeoffs have no pattern (patternptr=0), so
				if($reverseActionObjectId == 0) {
					$actionKey = 'updated_instance';
					$reverseActionObjectId = $changedObj['timeoffid'];
				}
				$extrafields .= "<hidden key='$actionKey'>$reverseActionObjectId</hidden>";
			}
			$subject = "$xfaction Time Off";
			$extrafields .= "<extra key='x-label-$xfaction Time Off'>";
			if($patternrow['patternid']) $timeoffLabel = patternDescription($patternrow, trule);
			else $timeoffLabel = longestDayAndDate(strtotime($changedObj['date']))
														.' '.($changedObj['timeofday'] ? "{$changedObj['timeofday']}" : "All Day");
			$extrafields .= referenceLink($timeoffLabel, $id, $changedObj['date']);
			$extrafields .= "</extra>";
			if($changedObj['note']) {
				$extrafields .= "<extra key='x-multiline-Note'><![CDATA[";
				$extrafields .= str_replace("\n", "<br>", str_replace("\n\n", "<p>", $changedObj['note']));
				$extrafields .= "]]></extra>";
			}
			if($delta) {
				$extrafields .= "<extra key='x-label-Changed fields'>";
				$extrafields .= $delta;
				$extrafields .= "</extra>";
			}
		}
		$extrafields .= "<extra key='x-label-Requestor'><![CDATA[";
		$extrafields .= $_SESSION["fullname"] ? $_SESSION["fullname"] : $_SESSION["auth_username"];
		$extrafields .= "]]></extra>";

		$extrafields .= "</extrafields>";

	//echo htmlentities($extrafields);exit;

		$request = array('extrafields'=>$extrafields, 'subject'=>"$subject for $sitterName");
		saveNewTimeoffRequest($request, ($notify=userRole() == 'p'));
	}
	if(!$unassignedAppointments) {
		echo "<script language='javascript'>\n";
		if($errorHTML) {
			if(!$noCalendarUpdate) {
				$_SESSION['timeoffcalendarwarning'] = $errorHTML;
				echo	"if(parent && parent.refresh) parent.refresh();\n";
			}
			else echo "document.write('$errorHTML');\n";// alert('$errorHTML'); 
		}
		else echo	"if(parent && parent.refresh) parent.refresh();\n";
		echo "</script>";
	}
	exit;
}

if($id) {
	$timeOff = fetchFirstAssoc(
		"SELECT t.*, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as pname
			FROM tbltimeoffinstance t
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE timeoffid = $id LIMIT 1");
	$patternrow = getPatternForInstance($id);
//print_r($patternrow);			
	$prov = $timeOff['providerptr'];
	if($prov == getTimeOffBlackoutId()) $pname = 'BLACKOUT';
	else $pname = $timeOff['pname'];
	$date = $timeOff['date'];
	$pastEditingAllowed = $pastEditingAllowed || ($editable && $date >= date('Y-m-d'));
}
else if($prov) {
	if($prov == getTimeOffBlackoutId()) $pname = 'BLACKOUT';
	else $pname = fetchRow0Col0("SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider WHERE providerid = $prov LIMIT 1");
	$pastEditingAllowed = $pastEditingAllowed || ($editable && $date >= date('Y-m-d'));
}
else {
}



$extraHeadContent = <<<HEADSTUFF
	<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>

HEADSTUFF;
// =================================================================
require "frame-bannerless.php";
?>
<style>
.narrowlabel {width:90px;}
.widevalue {width:100%;}
.weekdaytable {border-collapse:true;display:inline;cursor:pointer;}
.weekdaytable td {max-width:30px;padding-left:3px;padding-right:3px;border: solid darkgrey 1px;background:white;}
.day {font-weight:normal;}
.chosenday {font-weight:bold;color:blue;}
.disabledday {font-weight:normal;color:gray;background:lightgrey;}

.oneDayCalendarPage {
		border:solid black 2px;
		background:white;
		width:90px;
 }
.oneDayCalendarPage td {padding-top:0px;padding-bottom:0px;}
.monthline {font-size:8pt;}
.domline {font-size:16pt;font-weight:bold;text-align:center;}
.dowline {font-size:8pt;;text-align:center;}

</style>
<?
makeTimeFramer('timeFramer', 'narrow');

function getPatternForInstance($id) {
	return fetchFirstAssoc(
		"SELECT tbltimeoffpattern.*
			FROM tbltimeoffinstance
			LEFT JOIN tbltimeoffpattern ON patternid = patternptr
			WHERE timeoffid = $id LIMIT 1");
}

function changeLogStart($changedObj) {
	return $changedObj['providerptr']
				.'|'.$changedObj['date']
				.'|'.($changedObj['timeofday'] ? "{$changedObj['timeofday']}" : "[All Day]");
}

	//var ocwOption = "openConsoleWindow(\"timeoffcalendar\", \"timeoff-sitter-calendar.php?&amp;editable=1&amp;month=$month&amp;open=$timeoffid\",850,700)";
function referenceLink($label, $timeoffid, $month) {
	$month = date('Y-m-01', strtotime($month));
	$url = "timeoff-sitter-calendar.php?editable=1&month=$month&open=$timeoffid";
	$code = "openConsoleWindow(\"timeoffcalendar\", \"timeoff-sitter-calendar.php?&amp;editable=1&amp;month=$month&amp;open=$timeoffid\",850,700)";
	$link = fauxLink($label, $code, 1, 'Click to edit.');
	
  /*
  $code = "var w = window.open(\"\",\"timeoffcalendar\","
  				."\"toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width=850,height=700\");"
  				."w.document.location.href=\"$url\";if(w) w.focus();";
	$link = "<a href='javascript:$code' title='Click to edit.'>$label</a>";
	*/
	return "<![CDATA[$link]]>";
}

function buttonDiv($divid, $formelementid, $onClick, $label, $value='', $extraStyle=null, $title=null) {
	$title = $title ? "title = '$title'" : '';
	$class = $class ? "class = '$class'" : '';
	echo 
	  "<div id='$divid' style='display:inline;cursor:pointer;border: solid darkgrey 1px;height:15px;padding-left: 2px;overflow:hidden;$extraStyle' 
	onClick='$onClick' $title>$label</div>".
	  hiddenElement($formelementid, $value, ''); //until_dependent
}

function weekdayChooser() {
	$days = explode(',', 'Su,M,Tu,W,Th,F,Sa,x');
	echo "<table class='weekdaytable'><tr>";
	$eventtype = 'onclick'; // onclick onmouseup
	//if(strpos(strtoupper($_SERVER["HTTP_USER_AGENT"]), 'FIREFOX') !== FALSE) $eventtype = 'onmouseup';
	foreach($days as $i => $day) {
		$style = $day == 'x' ? "style='color:red'" : '';
		echo "<td w='$i' $eventtype='dayClicked(this)' $style>$day</td>";
	}
	echo "</tr></table>";
	hiddenElement('chosendays', '-1');
}

function updateTimeOff($id, $timeofday, $note) {
	$timeOff = fetchFirstAssoc("SELECT * FROM tbltimeoffinstance WHERE timeoffid = $id LIMIT 1", 1);
	$todIsTheSame = $timeOff['timeofday'] == $timeofday;
	$timeOff['timeofday'] = $timeofday;
	if(!$todIsTheSame && $collision = applicableBlackoutCollision($timeOff)) {
		$blackoutDesc = shortNaturalDate(strtotime($collision['date']), $noYear=true)
										.($collision['timeofday'] ? " ".$collision['timeofday'] : ' all day');
		$timeOffDesc = shortNaturalDate(strtotime($timeOff['date']), $noYear=true)
										.($timeOff['timeofday'] ? " ".$timeOff['timeofday'] : ' all day');
		return array(
			'blackout'=>$blackoutDesc,
			'timeoff'=>$timeOffDesc,
			'error'=>"Requested time off ($timeOffDesc) conflicts with BLACKOUT $blackoutDesc");
	}
	return updateTable('tbltimeoffinstance', timeStamp(array('timeofday'=>$timeofday, 'note'=>$note), 0), "timeoffid = $id", 0);
}

function addTimeOff($args, $provOverride=null) {
	// return array('primaryTO'=>anId, 'errors'=>array("Requested time off...", "Requested time off...", ))
	extract($args);
	if($provOverride) $prov = $provOverride;
	$timeOff = 	timeStamp(array('date'=>$date, 'timeofday'=>$timeofday, 'note'=>$note, 'providerptr'=>$prov), 1);
	if($until) {
		$until = date('Y-m-d', strtotime($until));
		$newPattern = $timeOff;
		$newPattern['until'] = $until;
		$newPattern['pattern'] = $monthlyselect ? $monthlyselect : $chosendays;
		// save pattern
		$timeOff['patternptr'] = insertTable('tbltimeoffpattern', $newPattern, 1);
//echo "<p>[{$timeOff['date']}] [$until] []: ".print_r($newPattern, 1);exit;
	}
	
	$results = array();
	// save initial time off
	//$newPrimaryTO = insertTable('tbltimeoffinstance', $timeOff, 1);
	$newPrimaryTO = insertBlackoutSafeTimeOff($timeOff);
	if(is_array($newPrimaryTO)) {
		$results['errors'][] = "<span style=\"color:red;\">No time off added:</span> {$newPrimaryTO['error']}";
	}
	else $results['primaryTO'] = $newPrimaryTO;
	
	if($newPattern && !$results['errors']) {
		$pattern = $newPattern['pattern'];
		if(strpos($pattern, 'days_') === 0) $days = explode(',', substr($pattern, strlen('days_')));
		// save repeats
		$datetime = strtotime($timeOff['date']);
		$dow = date('D', $datetime);
		$dom = date('j', $datetime);
		$day = date('Y-m-d', strtotime('+1 day', strtotime($timeOff['date'])));
//echo "[$day] [$until] $pattern<p>";	exit;
		for( ; $until && $day <= $until; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
			$thismonth = date('m', strtotime($day));
			$monthstarttime = strtotime('-1 day', strtotime(date('Y-m-01', strtotime($day))));

			$timeOff['date'] = $day;
			// if pattern matches...
			if($days && in_array(date('w', strtotime($day)), $days)) {
				$added = insertBlackoutSafeTimeOff($timeOff);
				if(is_array($added))
					$results['errors'][] = $added['error'];
				//insertTable('tbltimeoffinstance', $timeOff);
			}
			else if(!$days) {
}
				// pattern = dom | everyday | every | 1st | 2nd | 3rd | 4th | last  
				if($pattern == 'everyday'
					|| ($pattern == 'dom' && $dom == date('j', strtotime($day)))
					|| ($pattern == 'every' && $dow == date('D', strtotime($day)))
					|| ($pattern == '1st' && $day == date('Y-m-d', strtotime("first $dow", $monthstarttime)))
					|| ($pattern == '2nd' && $day == date('Y-m-d', strtotime("second $dow", $monthstarttime)))
					|| ($pattern == '3rd' && $day == date('Y-m-d', strtotime("third $dow", $monthstarttime)))
					|| ($pattern == '4th' && $day == date('Y-m-d', strtotime("fourth $dow", $monthstarttime)))
					|| ($pattern == 'last' 
							&& ((date('m', strtotime("fifth $dow", $monthstarttime)) == $thismonth
										&& $day == date('Y-m-d', strtotime("fifth $dow", $monthstarttime)))
									|| $day == date('Y-m-d', strtotime("fourth $dow", $monthstarttime)))
							)
				) {
						//insertTable('tbltimeoffinstance', $timeOff, 1);
						$added = insertBlackoutSafeTimeOff($timeOff);
						if(is_array($added))
							$results['errors'][] = $added['error'];
					}
			}
//echo "fourth $dow ".date('Y-m-d', strtotime("fourth $dow", $monthstarttime)).'<br>';
		}
//exit;
	}	
	return $results;
}

function simulatedTimeOff($args, $provOverride=null) {
	// return array of timeOff instances to be created by $args
	extract($args);
	if($provOverride) $prov = $provOverride;
	$timeOff = 	timeStamp(array('date'=>$date, 'timeofday'=>$timeofday, 'note'=>$note, 'providerptr'=>$prov), 1);
	if($until) {
		$until = date('Y-m-d', strtotime($until));
		$newPattern = $timeOff;
		$newPattern['until'] = $until;
		$newPattern['pattern'] = $monthlyselect ? $monthlyselect : $chosendays;
		// save pattern
		$timeOff['patternptr'] = insertTable('tbltimeoffpattern', $newPattern, 1);
//echo "<p>[{$timeOff['date']}] [$until] []: ".print_r($newPattern, 1);exit;
	}
	
	$results = array();
	// save initial time off
	//$newPrimaryTO = insertTable('tbltimeoffinstance', $timeOff, 1);
	if(!applicableBlackoutCollision($timeOff)) $results[] = $timeOff;
	
	if($newPattern && !$results['errors']) {
		$pattern = $newPattern['pattern'];
		if(strpos($pattern, 'days_') === 0) $days = explode(',', substr($pattern, strlen('days_')));
		// save repeats
		$datetime = strtotime($timeOff['date']);
		$dow = date('D', $datetime);
		$dom = date('j', $datetime);
		$day = date('Y-m-d', strtotime('+1 day', strtotime($timeOff['date'])));
//echo "[$day] [$until] $pattern<p>";	exit;
		for( ; $until && $day <= $until; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
			$thismonth = date('m', strtotime($day));
			$monthstarttime = strtotime('-1 day', strtotime(date('Y-m-01', strtotime($day))));

			$timeOff['date'] = $day;
			// if pattern matches...
			if($days && in_array(date('w', strtotime($day)), $days)) {
				if(!applicableBlackoutCollision($timeOff)) $results[] = $timeOff;
			}
			else if(!$days) {
}
				// pattern = dom | everyday | every | 1st | 2nd | 3rd | 4th | last  
				if($pattern == 'everyday'
					|| ($pattern == 'dom' && $dom == date('j', strtotime($day)))
					|| ($pattern == 'every' && $dow == date('D', strtotime($day)))
					|| ($pattern == '1st' && $day == date('Y-m-d', strtotime("first $dow", $monthstarttime)))
					|| ($pattern == '2nd' && $day == date('Y-m-d', strtotime("second $dow", $monthstarttime)))
					|| ($pattern == '3rd' && $day == date('Y-m-d', strtotime("third $dow", $monthstarttime)))
					|| ($pattern == '4th' && $day == date('Y-m-d', strtotime("fourth $dow", $monthstarttime)))
					|| ($pattern == 'last' 
							&& ((date('m', strtotime("fifth $dow", $monthstarttime)) == $thismonth
										&& $day == date('Y-m-d', strtotime("fifth $dow", $monthstarttime)))
									|| $day == date('Y-m-d', strtotime("fourth $dow", $monthstarttime)))
							)
				) {
						//insertTable('tbltimeoffinstance', $timeOff, 1);
						if(!applicableBlackoutCollision($timeOff)) $results[] = $timeOff;
					}
			}
//echo "fourth $dow ".date('Y-m-d', strtotime("fourth $dow", $monthstarttime)).'<br>';
		}
//exit;
	}	
	return $results;
}

function insertBlackoutSafeTimeOff($timeOff) {
	if($collision = applicableBlackoutCollision($timeOff)) {
		$blackoutDesc = shortNaturalDate(strtotime($collision['date']), $noYear=true)
										.($collision['timeofday'] ? " ".$collision['timeofday'] : ' all day');
		$timeOffDesc = shortNaturalDate(strtotime($timeOff['date']), $noYear=true)
										.($timeOff['timeofday'] ? " ".$timeOff['timeofday'] : ' all day');
										
		return array(
			'blackout'=>$blackoutDesc,
			'timeoff'=>$timeOffDesc,
			'error'=>"Requested time off ($timeOffDesc) conflicts with BLACKOUT $blackoutDesc");
	}
	else return insertTable('tbltimeoffinstance', $timeOff);
	
}

function applicableBlackoutCollision($timeOff) {
	if($timeOff['providerptr'] == getTimeOffBlackoutId()) 
		return;
	if(shouldEnforceBlackouts()) 
		return timeoffCollisionWithBlackout($timeOff);
}

function shouldEnforceBlackouts() {
	global $blackOutsEnabled;
	return $blackOutsEnabled && userRole() == 'p';
}

function timeStamp($arr, $creation=1) {
	$arr[$creation ? 'created' : 'modified'] = date('Y-m-d H:i:s');
	$arr[$creation ? 'createdby' : 'modifiedby'] = $_SESSION["auth_user_id"];
	return $arr;
}


function dayDisplay($date, $extraStyle='') {
	$time = strtotime($date);
	$dow = date('l', $time);
	$dom = date('j', $time);
	$mon = date('F', $time);
	$year = date('Y', $time);
	if($extraStyle) $extraStyle = "style='$extraStyle'";
	return "<table class='oneDayCalendarPage' $extraStyle>
	<tr class='monthline'><td>$mon</td><td style='text-align:right'>$year</td></tr>
	<tr class='domline'><td colspan=2>$dom</td></tr>
	<tr class='dowline'><td colspan=2>$dow</td></tr>
</table>";
}

/*
CREATE TABLE IF NOT EXISTS `tbltimeoffpattern` (
  `patternid` int(10) unsigned NOT NULL  auto_increment,
  `date` date NOT NULL default '0000-00-00',
  `until` date NOT NULL default '0000-00-00',
  `timeofday` varchar(45) default NULL,
  `providerptr` int(10) unsigned NOT NULL default '0',
  `note` text,
  `pattern` varchar(20),
  `created` datetime default NULL,
  `createdby` int(11) default NULL,
  `modified` datetime default NULL,
  `modifiedby` int(11) default NULL,
  UNIQUE KEY `Index_2` (`patternid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=101 
*/

// ***************************************************************************
?>
<table border=0 width='100%'><tr><td><?= dayDisplay($date); ?></td>
<td class='h2'><?= $id ? '' : 'Add ' ?>Sitter Time Off<?= $pname ? ": $pname" : '<span id="headersitter"></span>' ?></td>
<td style='text-align:right'>
<?
if($pastEditingAllowed) {
	if($id) {
		echoButton('', 'Save Changes', 'saveTimeOff()');
	}
	else echoButton('', 'Save New Time Off', 'saveTimeOff()');
}
?>
</td>
</tr>
<tr><td colspan=3>
<?
if($pastEditingAllowed && $id) {
	echoButton('', 'Delete This Time Off', 'deleteTimeOff()', 'HotButton', 'HotButtonDown');
	$totalInstances = 
		$timeOff['patternptr'] ? fetchRow0Col0("SELECT count(*) FROM tbltimeoffinstance WHERE patternptr = {$timeOff['patternptr']}") 
		: null;
	if($totalInstances) {
		echo " ";
		echoButton('', "Delete This and Following Repeats", 'deleteTimeOff("following")', 'HotButton', 'HotButtonDown');
		echo " ";
		echoButton('', "Delete This and ALL Repeats (total: $totalInstances)", 'deleteTimeOff("all")', 'HotButton', 'HotButtonDown');
	}
}
?>
</td></tr></table>
<form name="timeoff_form" method="POST">
<table width=100% border=0> <? // MAJOR TABLE ?>
<tr>
<td valign=top style='margin-left:0px;padding-left:0px'>
<table style='margin-left:0px;padding-left:0px;margin-top:5px;'>
<? // LEFT PANEL
if($pname) {
	labelRow('Sitter:', '', $pname, 'narrowlabel');
	hiddenElement('id', $id);
	hiddenElement('prov', $prov);
}
else {
	$provideroptions = array('-- Select a Sitter --'=>0);
	if($blackOutsEnabled) $provideroptions['BLACKOUT'] = 'blackoutId';
	foreach(fetchKeyValuePairs(
		"SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as n, providerid
		FROM tblprovider
		WHERE active = 1
		ORDER BY n") as $label => $pid) $provideroptions[$label] = $pid;
	
	selectRow('Sitter', 'prov', $prov, $provideroptions, $onChange='$("#headersitter").html(": "+sitters[$("#prov").val()]);fetchDaySummary();');
}

//labelRow('Date:', '', shortDate(strtotime($date)), 'narrowlabel');
hiddenElement('delete', '');
hiddenElement('date', $date);
echo "<tr><td style='' class='narrowlabel'>Time of day:</td>";
echo "\n<td style='padding:4px;vertical-align:top;'>";
$checked = !$timeOff['timeofday'] ? 'CHECKED' : '';
echo "<input type='radio' name='tod' id='allday' value='' $checked onclick='todChanged(this)'> <label for='allday'>All Day</label> ";
$checked = !$checked ? 'CHECKED' : '';
echo "<input type='radio' name='tod' id='timeframe' value='' $checked onclick='todChanged(this)'>";
//$TEST = mattOnlyTEST() ? "alert(event.target.getAttribute(\"id\"));" : '';
$TimeFramerPositioningFix = "var event = eventFor(\"div_timeofday\");";
buttonDiv("div_timeofday", "timeofday", "$(\"#timeframe\").attr(\"checked\", true);$TimeFramerPositioningFix showTimeFramer(event, \"div_timeofday\")",
					($timeOff['timeofday'] ? $timeOff['timeofday'] : '<i>Set specific times</i>'));
echo "</td></tr>";

if(!$id) {
	$monthlySelectOptions = array('monthly...'=>null, 'same day of month'=>'dom', 'every day'=>'everyday');
	$prefix = '';
	$dow = date('l', strtotime($date));
	foreach(explode('|', 'every|1st|2nd|3rd|4th|last') as $opt) {
		$monthlySelectOptions["$prefix$opt $dow"] = $opt;
		$prefix = 'every ';
	}
	echo "<tr><td style='vertical-align:top;padding-top:11px;' class='narrowlabel'>Repeat</td>";
		echo "<td>";
		echo "<table><tr><td style='vertical-align:top;'>";
		calendarSet('Until' , 'until', $value=null, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='untilChanged()');
		echo '</td><td>';
		echo "<input class ='until_dependent' type='radio' name='pattern' id='monthlypattern' value='' CHECKED onclick='patternChanged(this)'>";
		selectElement('', 'monthlyselect', 'everyday', $monthlySelectOptions, $onChange='monthlyselectChanged()', $labelClass=null, $inputClass='until_dependent' );
		echo "<br>";
		echo "<br>";
		echo "<input class ='until_dependent' type='radio' name='pattern' id='dailypattern' value='' onclick='patternChanged(this)'>";
		weekdayChooser();
		echo "</td></tr></table>";
	echo "</td></tr>";
		
}
else {
	hiddenElement('until', ($patternrow['until'] ? shortdate(strtotime($patternrow['until'])) : ''));
	if($timeOff['patternptr']) {
		$opat = fetchFirstAssoc("SELECT * FROM tbltimeoffpattern WHERE patternid = {$timeOff['patternptr']} LIMIT 1");
		labelRow('Repeats:', $name='', "Repeats from ".patternDescription($opat));
	}
}
?>
</table>
</td>
<? // END LEFT PANEL ?>
<td id='daysummary' valign=top style='background:#FFE9C9;'><? // RIGHT PANEL ?>
</td>
</tr>
</table> <? // MAJOR TABLE ?>


<?
if(dbTEST('carolinapetcare') && userRole() != 'p') {
	$saveLabel = $pastEditingAllowed ? 'Save Changes' : 'Save New Time Off';
	$saveLink = " ".fauxLink($saveLabel, 'saveTimeOff()', $noEcho=true, $title='Save this time off');
}

textRow("Note:$saveLink", 'note', $timeOff['note'], $rows=10, $cols=70, $labelClass=null, $inputClass='fontSize1_3em');
?>
</table>
<?

if($id && userRole() == 'o' 
	&& (dbTEST($whoSeesThis='wisconsinpetcare,pawlosophy,crittersitterlinganore') 
				|| $_SESSION['preferences']['enableTimeOffLastChange']
				|| staffOnlyTEST())) {
	$creatorid = $timeOff['createdby'];
	$creator = fetchRow0Col0(
		"SELECT CONCAT_WS(' ', 'Sitter ', fname, lname, IF(nickname IS NOT NULL, CONCAT('(',nickname,')'), ''), CONCAT('(',userid,')')) FROM tblprovider WHERE userid = '$creatorid' LIMIT 1", 1);
	$modifierid = $timeOff['modifiedby'];
	if($modifierid) $modifier = fetchRow0Col0(
		"SELECT CONCAT_WS(' ', 'Sitter ', fname, lname, IF(nickname IS NOT NULL, CONCAT('(',nickname,')'), ''), CONCAT('user id: (',userid,')')) FROM tblprovider WHERE userid = '$modifierid' LIMIT 1", 1);
	require "common/init_db_common.php";
	if(!$creator) 	$creator = fetchRow0Col0(
		"SELECT CONCAT_WS(' ', 'Staffer ', fname, lname, CONCAT('(',userid,')')) FROM tbluser WHERE userid = '$creatorid' LIMIT 1", 1);
	if($modifierid && !$modifier) 	$modifier = fetchRow0Col0(
		"SELECT CONCAT_WS(' ', 'Staffer ', fname, lname, CONCAT('(',userid,')')) FROM tbluser WHERE userid = '$modifierid' LIMIT 1", 1);
	if(staffOnlyTEST()) echo "<p>(The note below is visible only to LT Staff and $whoSeesThis (and bizzes with enableTimeOffLastChange).)<p>";
	
	$created = $timeOff['created'] ? shortDateAndTime(strtotime($timeOff['created'])) : '';
	echo "<br>[{$timeOff['timeoffid']}] Created: $created by {$creator}";
	$modified = $timeOff['modified'] ? shortDateAndTime(strtotime($timeOff['modified'])) : '';
	if($modified) echo " Modified: $modified by {$modifier}";
}
?>
</form>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
<? 
$popDateTime = strtotime($date);
if(date('t', $popDateTime) == date('j', $popDateTime)) 
	$popDateTime = strtotime('+1 day', $popDateTime);
?>
var initialDate = 1; // used in popcalendar.js>popUpCalendar()
var initialMonth = <?= date('n', $popDateTime) - 1; ?>; // January = 0
var initialYear = <?= date('Y', $popDateTime); ?>; 

<? 
if($provideroptions) {
	foreach($provideroptions as $label => $val) $arr[] = "$val: \"".addslashes($label)."\"";
	echo "var sitters = {".join(', ',  $arr)."};\n";
}
?>
function update(aspect, value) {
	if(aspect == 'daySummary') fetchDaySummary();
}

function previewUnassignedVisits() {
	if(!validateTimeOff()) return;
	var args = 
		"prov="+$("#prov").val() 
		+ "&date="+$("#date").val()
		+ "&timeofday="+$("#timeofday").val()
		+ "&until="+$("#until").val()
		+ "&monthlyselect="+$("#monthlyselect").val()
		+ "&chosendays="+$("#chosendays").val()
		;
	//$.ajax({
	//url: "timeoff-edit.php?preview=1&"+args,
	//success: function(data){
	//	$("#doomed").html(data);
	//}
	//});
	openConsoleWindow("doomed", "timeoff-edit.php?preview=1&"+args, 500, 300);
}

function validateTimeOff() {
	<? if(!$id) { ?>
	var until = $('#until').get(0).value;
	var tdate = "<?= date('m/d/Y', strtotime($date)) ?>";
	var days = new Array();
	$('.chosenday').each(function(index, el) { days.push(el.getAttribute('w'));});
	var patternNeeded = validateUSDate(until) && isDateAfter(until, tdate) 
												&& !($("#monthlyselect option:selected").val() || 
												 ($('#dailypattern').attr('checked') && days));
	patternNeeded = patternNeeded ? 'Please specify a pattern to repeat until the date specified.' : '';
	var sitterRequired = $("#prov").val() == 0 ? 'Sitter is required.' : '';
	if(!MM_validateForm(
				//'prov', '', 'R',
				'until', '', 'isDate', 
				<? if(!$id) echo "'until', tdate, 'isDateAfter',\n"; ?>
				patternNeeded, '', 'MESSAGE',
				sitterRequired, '', 'MESSAGE')) {
		return;
	}
//alert(document.getElementById('prov').options[document.getElementById('prov').selectedIndex].value);return;
	if(days) $('#chosendays').val('days_'+days.join(','));
	<? } ?>
	
	var tod = $('#div_timeofday').html();
	if($('#allday').attr('checked')) tod = '';
	else if(tod.indexOf('<') != -1) {
		alert('Please specify a time of day.');
		return;
	}
	else {
		var times = tod.split('-');
		if(militaryTime(times[0]) > militaryTime(times[1])) {
			alert('The end time cannot be earlier than the start time.  Use 11:59 pm if necessary.');
			return;
		}
	}
	$('#timeofday').val(tod);
	return true;
}

function saveTimeOff() {
	<? if(!$id) { ?>
	var until = $('#until').get(0).value;
	var tdate = "<?= date('m/d/Y', strtotime($date)) ?>";
	var datelimit = "<?= date('m/d/Y', strtotime('+2 years')) ?>";
	var days = new Array();
	$('.chosenday').each(function(index, el) { days.push(el.getAttribute('w'));});
	var patternNeeded = validateUSDate(until) && isDateAfter(until, tdate) 
												&& !($("#monthlyselect option:selected").val() || 
												 ($('#dailypattern').attr('checked') && days));
	patternNeeded = patternNeeded ? 'Please specify a pattern to repeat until the date specified.' : '';
	var sitterRequired = $("#prov").val() == 0 ? 'Sitter is required.' : '';
	if(!MM_validateForm(
				//'prov', '', 'R',
				'until', '', 'isDate', 
				<? if(!$id) echo "'until', tdate, 'isDateAfter',\n'until', datelimit, 'isDateBefore',\n"; ?>
				patternNeeded, '', 'MESSAGE',
				sitterRequired, '', 'MESSAGE')) {
		return;
	}
//alert(document.getElementById('prov').options[document.getElementById('prov').selectedIndex].value);return;
	if(days) $('#chosendays').val('days_'+days.join(','));
	<? } ?>
	
	var tod = $('#div_timeofday').html();
	if($('#allday').attr('checked')) tod = '';
	else if(tod.indexOf('<') != -1) {
		alert('Please specify a time of day.');
		return;
	}
	else {
		var times = tod.split('-');
		if(militaryTime(times[0]) > militaryTime(times[1])) {
			alert('The end time cannot be earlier than the start time.  Use 11:59 pm if necessary.');
			return;
		}
	}
//alert(tod+': '+$('#timeofday').get(0));	
	$('#timeofday').val(tod);
//alert(tod+': '+$('#timeofday').attr('disabled'));	
	document.timeoff_form.submit();
}

function deleteTimeOff(all) {
	if(!confirm('Are you sure you want to Delete Time Off?')) return;
	$('#delete').val(all ? all : '1');
	document.timeoff_form.submit();
}

function monthlyselectChanged(el) {
	var monthlyselectionval = $("#monthlyselect option:selected").val();
	if(monthlyselectionval) $('#monthlypattern').click();
}

function patternChanged(el) {
	var monthlyselectionval = $("#monthlyselect option:selected").val();
	if(el.id == 'monthlypattern' && (true || monthlyselectionval)) {
		$('.weekdaytable td').removeClass('chosenday');
	}
	else {
	<?  ?>
		$('#monthlyselect')[0].selectedIndex = 0;
	}
}

function fetchDaySummary() {
	var prov = $('#prov').val();
	if(prov == 'blackoutId') $('#daysummary').html('');
	else if(prov == 0)$('#daysummary').html('');
	else
		$.ajax({
			url: "timeoff-edit.php?provsummary="+prov+"&date=<?= $date ?>",
			success: function(data){
				$('#daysummary').html(data);
			}
			});
}

function dayClicked(td) {
	var w = -1;
	if(td) {
		if($('#dailypattern').attr('disabled')) return;
		$('#dailypattern').attr( "checked", true);
<?  ?>	
		w = td.getAttribute('w');
		var className = td.className.indexOf('chosen') != -1 ? 'day' : 'chosenday';
		if(w != 7) td.className = className;
		else w = -1;
	}
<? " ?>	
	$('#chosenday').val(w); // php date 'w'
	if(w != -1) {
		//$('#dailypattern').click();
		$('#monthlyselect')[0].selectedIndex = 0;;
		}
	else $('.weekdaytable td').removeClass('chosenday');
}

setPrettynames('until', 'Repeat-until date, if supplied,', 'prov', 'Sitter');
function untilChanged() {
	var disable = 
		!MM_validateForm(
				'until', '', 'isDate' 
				<? if(!$id) echo ",\n'until', '".date('m/d/Y', strtotime($date))."', 'isDateAfter'\n"; ?>
			)
		|| !$('#until').val();
	$('.until_dependent').attr('disabled', disable);
	//$('#previewLink').css('display', (disable ? 'none' : 'inline'));
	if(disable) {
		$('.weekdaytable td').removeClass('chosenday').addClass('disabledday');//.attr('onclick', '');
	}
	else {		
		$('.weekdaytable td').removeClass('disabledday').addClass('day')
		;//.click(function() {dayClicked($(this).get(0));});
	}
		
}

function todChanged(el) {
	if(el.id == 'allday') {
		$('#div_timeofday').html('<i>Click Here</i>');
	}
	else $('#div_timeofday').click();
}


function mouseCoords(ev){  // for pets and weekday widgets
	var scrollTop = document.body.scrollTop;
	if(navigator.appName == "Microsoft Internet Explorer" && document.documentElement) 
		scrollTop = document.documentElement.scrollTop;
	if(ev.pageX || ev.pageY){
		return {x:ev.pageX, y:ev.pageY};
	}
	return {
		x:ev.clientX + document.body.scrollLeft - document.body.clientLeft,
		y:ev.clientY + scrollTop  - document.body.clientTop
	};
}


function eventFor(el) {
	if(typeof el == 'string') el = document.getElementById(el);
	var pos = getAbsolutePosition(el);
	if(document.createEvent) {
		var mevt = document.createEvent("MouseEvent");
		mevt.initMouseEvent("click",true,true,window,1,0,0,pos.x,pos.y,false,false,false,false,null,null);
	}
	else if(document.createEventObject) {
		var mevt = document.createEventObject();
		mevt.clientX = pos.x;
		mevt.clientY = pos.y;
	}
	return mevt;
}



<? dumpTimeFramerJS('timeFramer'); ?>
sameDayTimeFramesOnly = true;
<? dumpPopCalendarJS(); ?>

untilChanged();
fetchDaySummary();
<? if(!$pastEditingAllowed) { ?>
$("input").attr("disabled", true);
$("textarea").attr("disabled", true);
$("#div_timeofday").removeAttr("onclick");
<? } ?>
</script>