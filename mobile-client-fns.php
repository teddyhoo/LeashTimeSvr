<? // mobile-client-fns.php

function dumpMonth($date=null, $start=null, $end=null) {
	if(!$date && !$start && !$end) $date = date('Y-m-d');
	if($date) {
		if(!$start) $start = date('Y-m-1', strtotime($date));
		if(!$end) $end = date('Y-m-t', strtotime($date));
	}
	else if($start && !$end)
		$end = date('Y-m-d', strtotime('+28 days', strtotime($start)));
	else if($end && !$start)
		$start = date('Y-m-d', strtotime('-28 days', strtotime($end)));
	
	
	dumpCalendarLooks(100, 'lightblue');
	echo "<table class='previewcalendar'  style='border-collapse:collapse;'>";

	$month = '';
	$dayN = 0;
	// allow for appts before start...
	for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
		$dow = date('w', strtotime($day));
		if($month != date('F Y', strtotime($day))) {
			if($dow && $month) {  // finish prior month, if any
				for($i=$dow; $i < 7; $i++) echo "<td>&nbsp;</td>";
				echo "</tr>";
			}
			$month = date('F Y', strtotime($day));
			echoMonthBar($month, $day);
			echo "<tr>";
			for($i=0; $i < $dow; $i++) echo "<td>&nbsp;</td>";
		}
		if(!$dow) echo "</tr><tr>";
		echoDayBox($day, $_SESSION['clientid']);
		$dayN++;
	}
	if($dow && $month) {  // finish prior month, if any
		for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
		echo "</tr>";
	}

	echo "</table>";
	global $swipeWorks;
	echo "<div class='tiplooks' style='width:100%;height:200px;background:white;text-align:center;'>";
	if($swipeWorks) echo "Swipe left or right to change the month.";
	echo "</div>";
}

function dumpCalendarLooks($rowHeight, $descriptionColor) {
	global $appDayColor, $isTablet;
	if(!$isTablet) $smallerFontForPhone = "font-size:0.7em;";

	echo <<<LOOKS
<style>
.previewcalendar { background:white;width:100%;border:solid black 0px;margin:0px; $smallerFontForPhone}
.previewcalendar td {border-top:solid black 1px;width:14.29%;}

.dayeditor { background:white;width:100%;border:solid black 0px;margin:0px; font-size:1.2em; $smallerFontForPhone}
.dayeditor td {border-top:solid black 1px;}
.tabletbutton {font-size: 0.8em;}
.phonebutton  {font-size: 0.8em;}


.appday {border:solid black 1px;background:$appDayColor;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}
.clientappcomplete {background: lightgreen; }
.apptable td {border-collapse:collapse;}
.apptable td {border:solid black 0px}
.empty {border:solid black 1px;background:white;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

.month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

.dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
.daytop {padding:0px;margin:0px;width:100%}
.daynumber {display:inline;font-size:1.5em;font-weight:bold;text-align:right;width:50px;}
.addtimeoffplus {clear:right;float:right;padding-right:5px;}
.apptcontrols {cursor:pointer;float:left;margin-right:3px;height:10px;width:10px; border:solid darkgray 1px;}
.monthlink {font-size:0.75em;padding-left:20px;padding-right:20px;display:inline;}
.today {color:red;}

.daybardiv {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;}
.prevnextdaylink {font-size:0.75em;padding-left:20px;padding-right:20px;vertical-align:bottom;border-top:solid black 0px;padding-top:0px;}
.daybartable td {border-top:solid black 0px;}
.arrow {text-decoration:none;font-size:1em;}
</style>
LOOKS;
}

function echoMonthBar($month, $day) {
	$days = explode(',', 'Sun,Mon,Tue,Wed,Thu,Fri,Sat');
	$baseLink = "client-schedule-mobile.php?date=";
	$prevMonthStart = date('Y-m-d', strtotime("-1 month", strtotime($day)));
	$prev = date('F Y', strtotime($prevMonthStart));
	$prev = "<div class='monthlink'><a href='$baseLink$prevMonthStart'>$prev</a></div>";
	$nextMonthStart = date('Y-m-d', strtotime("+1 month", strtotime($day)));
	$next = date('F Y', strtotime($nextMonthStart));
	$next = "<div class='monthlink'><a href='$baseLink$nextMonthStart'>$next</a></div>";
	echo "<tr><td class='month' colspan=7>$prev$month$next</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function echoDayBox($day, $clientid) {
	$dom = date('j', strtotime($day));
	$clientid = $_SESSION['clientid'];
	$content = dayBoxVisits($day, $clientid);
	//$class = $content ? 'appday' : 'empty';
	$class = 'appday';
	$onclick = $content ? "editDay(\"$day\")" : "alert(\"No visits scheduled ".date('F j', strtotime($day))."\")";
	$today = $day == date('Y-m-d') ? 'today' : '';
	echo "<td class='$class' style='position:relative' id='box_$day' valign='top' onclick='$onclick'>
		<div class='daytop'>
			<div class='daynumber $today'>$dom $rbutton</div>
		</div>
		";
	if($class == 'empty') ;
	else {
		echo "<table class='apptable'>";
		echo "<tr><td style='text-align:left;color:black'>$content</td></tr>";
		echo "</table>";
	}
	echo "</td>";
}

function dayBoxVisits($day, $clientid) {
	$checkmark = "&#x2713;";
	$crossout = "<span style='color:red'>&#x2573;</span>";
	$change = "<span style='color:blue;font-size:1.2em;'>&#x25A8;</span>";
	$pencil = "<span style='color:green;font-size:1.1em;'>&#x270E;</span>";
	$appts = fetchAssociations(
		"SELECT * FROM tblappointment 
		 WHERE clientptr = $clientid
		 	AND date = '$day'
		 	AND canceled IS NULL
		 ORDER BY starttime, endtime", 1);
	if(!$appts) return '';
	$visits = array();
	$completed = 0;
	$countsOnly = $_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'];
	$now = date("h:i:s");
	foreach($appts as $appt) {
		$req = $appt['pendingchange'] < 0 ? $crossout : ($appt['pendingchange'] > 0? $change : '');
		$note = $appt['note'] ? " $pencil" : '';
		
		$futurity = appointmentFuturity($appt);
		if($futurity == -1) $someShouldBeComplete = 1;
		if($req) $changes[] = $req;
		if($appt['completed']) {
			$completed += 1;
			if(!$countsOnly) $visits[] = "<span class='clientappcomplete'>&#x2713; ".briefTimeOfDay($appt)."</span>$note $req";
		}
		else {
			if(!$countsOnly) $visits[] = briefTimeOfDay($appt)."$note $req";
		}
	}
	if($changes) $changes = ' '.join(' ', array_unique($changes));
	if($countsOnly) 
		$visits[] = count($appts)." visit".(count($appts) == 1 ? '' : "s").". $changes"
								.($someShouldBeComplete || $completed 
									? ($completed ? "<br><span class='clientappcomplete'>$checkmark $completed marked complete.</span>"
																: "<br>".(count($appts) == 1 ? "Not" : "None")." marked complete.")
									: '');
	return join("<hr>", $visits);
}

// ############################################
function dumpDayEditorVisits($day, $clientid) {
	global $isTablet;
	$crossout = "<span style='color:red'>&#x2573;</span>";
	$change = "<span style='color:blue;font-size:1.2em;'>&#x25A8;</span>";
	$pencil = "<span style='color:green;font-size:1.1em;'>&#x270E;</span>";
	$providerNames = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) as name from tblprovider");
	$noTimeFrames = $_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'];
	$appts = fetchAssociations(
		"SELECT * FROM tblappointment 
		 WHERE clientptr = $clientid
		 	AND date = '$day'
		 	AND canceled IS NULL
		 ORDER BY starttime, endtime", 1);
	$visits = array();
	$buttonclass = $isTablet ? 'tabletbutton' : 'phonebutton';
	if(!$isTablet) $smallerFontForPhone = "font-size:0.7em;";

	$someButtons = false;
	foreach($appts as $appt)
		if(!($appt['completed'] || strcmp($appt['date'], date('Y-m-d')) < 0) )
			$someButtons =  true;
	foreach($appts as $appt) {
		$futurity = appointmentFuturity($appt);
		$shouldBeComplete = $futurity == -1;
		$cols = $noTimeFrames ? 1 : 2;
		if($someButtons) $cols += 2;
		$tod = str_replace(' ', '&nbsp;', $appt['timeofday']);
		$tod = $noTimeFrames ? '' : "<td style='width:100px'>$tod</td>";
		$service = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$appt['servicecode']} LIMIT 1");
		if($appt['completed']) {
			$service = "<span class='clientappcomplete'>&#x2713; $service (Completed)</span>";
		}
		$service = "$tod<td style='width:100%'>$service</td>"; //
		// if we are reporting arrivals/completions, do so here
		$pendingChange = $appt['pendingchange'];
		$buttons = '';
		if(!($appt['completed'] || strcmp($appt['date'], date('Y-m-d')) < 0) ) {
			$buttons = "<td style='padding-left:0px;padding-right:0px;width:0px;'><input type='button' class='$buttonclass' value='Change' onclick='changeVisit({$appt['appointmentid']})'></td>"
									//."<img src='art/spacer.gif' width=20 height=1>"
									."<td style='padding-left:0px;padding-right:0px;width:0px;'>"
									.($pendingChange < 0 
												? ''
												: "<input class='$buttonclass' type='button' value='Cancel' onclick='changeVisit({$appt['appointmentid']}, 1)'>"
										)
									."</td>";
		}
		else if($someButtons) $buttons = "<td colspan=2>&nbsp;</td>";
		$service .= $buttons;
		//$service .= "<tr><td colspan=$cols style='border-top:solid black 0px;'>{$providerNames[$appt['providerptr']]}</td></tr>";
		$service .= "<tr><td colspan=$cols style='border-top:solid black 0px;'>Pets: {$appt['pets']}</td></tr>";
		$note = $appt['note'];
		if($note) $note = "$pencil $note";
		if($pendingChange) {
			$req = $pendingChange < 0 ? 'Cancellation' : 'Change';
			$icon = $pendingChange < 0 ? $crossout : $change;
			$note = $note ? array($note) : array();
			$note[] = "$icon <span style=\"color:red;\">$req pending</span>";
			$note = join('<br>', $note);
		}
		if($note) $service .= "<tr><td colspan=$cols style='border-top:solid black 0px;font-size:1.2em;'>$note</td></tr>";
		$visits[] = $service;
	}
	dumpCalendarLooks(100, 'lightblue');
	echo "<table class='dayeditor'  border=0 bordercolor=black>";
	echoDayBar($day, $cols);
	if($visits) echo join("<tr>", $visits);
	else echo "<tr><td style='height:150px;text-align:center;font-size:2.0em;color:darkgreen;'>"
				."No visits scheduled for ".date('F j', strtotime($day)).".</td></tr>";	 
	echo "</table>";
	global $swipeWorks;
	echo "<div class='tiplooks' style='border-top:solid black 1px;width:100%;height:300px;background:white;text-align:center;'>";
	if($swipeWorks) echo "Swipe left or right to change the day.";
	echo "</div>";
}

function echoDayBar($day, $colspan) {
	$baseLink = "mobile-client-edit-day.php?date=";
	$today = "<td style='vertical-align=bottom;'><table cellpadding=0 cellmargin=0 style='margin:0px;padding:0px;'>"
						."<tr><td style='font-size:0.65em;'>".date('l', strtotime($day))."</td></tr><td>"
						.date('M j', strtotime($day))
						."</td></tr></table>"
						."</td>";
	$calendar = "<td style='text-align:left;vertical-align:top;padding:0px;'><a style='text-decoration:none;' href='client-schedule-mobile.php?date=$day'>&#x25A6;</a></td>";
	$prevDay = date('Y-m-d', strtotime("-1 day", strtotime($day)));
	$prev = date('M j', strtotime($prevDay));
	$prevWeek = date('Y-m-d', strtotime("-1 week", strtotime($day)));
	$prev = "<td class='prevnextdaylink arrow'><a class='arrow' href='$baseLink$prevWeek'>&#x25B2;</a></td><td class='prevnextdaylink'><a href='$baseLink$prevDay'>$prev</a></td>";
	$nextDay = date('Y-m-d', strtotime("+1 day", strtotime($day)));
	$nextWeek = date('Y-m-d', strtotime("+1 week", strtotime($day)));
	$next = date('M j', strtotime($nextDay));
	$next = "<td class='prevnextdaylink'><a href='$baseLink$nextDay'>$next</a></td><td class='prevnextdaylink arrow'><a class='arrow' href='$baseLink$nextWeek'>&#x25BC;</a></td>";
	echo "<tr><td class='daybardiv' colspan=$colspan><table class='daybartable' style='border-collapse:collapse;' align=center border=0><tr>$calendar$prev$today$next</table></tr>\n<tr>";
	echo "</tr>\n";
}

function swipeHEADContent() {
	return <<<SWIPE
	<meta name="viewport" content="minimum-scale=1.0, maximum-scale=1.0,
	width=device-width, user-scalable=no">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<script type="text/javascript" src="modernizr-latest.js"></script>
	<script type="text/javascript" src="vanillaswipe.js"></script>
SWIPE;
}
