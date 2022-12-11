<? // visit-performance-fns.php

function applyPerformanceFilter($filter, $performance) {
	// $performance contains values from evaluatePerFormance, but also the icon generating fns
		/* FILTER
		arrivals: '' | arrivedonly | unarrivedonly
		arrivaltimes: '' | arrived_early, arrived_ontime, arrived_laggy, arrived_late
		completions: '' | completedonly | incompleteonly
		completiontimes: '' | completed_early, completed_ontime, completed_laggy, completed_late
		shortvisitsonly: '' | shortvisitsonly
		locations: '' | away | nodata
		*/
	if(!$filter) return;
	if($filter['arrivals'] == 'arrivedonly' && !$performance['arrived']) return 'BLOCKED_arrivedonly';
	else if($filter['arrivals'] == 'unarrivedonly' && $performance['arrived']) return 'BLOCKED_unarrivedonly';
	
	if($filter['arrivaltimes'] && $arrivaltimes = explode(',', "{$filter['arrivaltimes']}"))
		if(!in_array($performance['arrivalcat'], $arrivaltimes)) {
			//print_r($performance);exit;
			return "BLOCKED_arrivalcat_{$performance['arrivalcat']}";
		}

	if($filter['completions'] == 'completedonly' && !$performance['completed']) return "BLOCKED_completedonly";
	else if($filter['completions'] == 'incompleteonly' && $performance['completed']) return "BLOCKED_incompleteonly";
//echo ">>>".print_r($performance,1);exit;


	if($filter['completiontimes'] && $completiontimes = explode(',', "{$filter['completiontimes']}"))
		if(!in_array($performance['completioncat'], $completiontimes)) return "BLOCKED_completioncat_{$performance['completioncat']}";

	if($filter['shortvisitsonly'] && !$performance['shortvisit']) return "BLOCKED_shortvisit";

	if($filter['locations'] && $locations = explode(',', "{$filter['locations']}")) {
		if(!in_array($performance['location'], $locations)) 
			return "BLOCKED_location_{$performance['location']}.[{$filter['locations']}]".print_r($locations,1);
	}
}

function visitStatusIconPanelForPerformance($apptOrApptId, &$performance, $buttonSize=20) {
	// see performancePacket($apptOrApptId) below	
	ob_start();
	ob_implicit_flush(0);
	$icon = arrivalIcon($apptOrApptId, $performance);
	echo "<img src='{$icon['image']}' title='{$icon['title']}' width=$buttonSize height=$buttonSize>&nbsp;";
	$icon = completionIcon($apptOrApptId, $performance);
	echo "<img src='{$icon['image']}' title='{$icon['title']}'width=$buttonSize height=$buttonSize>";
	if(TRUE || mattOnlyTEST() && $performance['duration']) {
		echo " ".round($performance['duration']/60)." ";
	}
	if($icon = durationIcon($apptOrApptId, $performance))
		echo "&nbsp;<img src='{$icon['image']}' title='{$icon['title']}' width=$buttonSize height=$buttonSize>";
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function visitStatusIconPanel($apptOrApptId, $buttonSize=20) {
	$performance = evaluatePerformance($apptOrApptId);
	$icon = arrivalIcon($apptOrApptId, $performance);
	ob_start();
	ob_implicit_flush(0);
	echo "<img src='{$icon['image']}' title='{$icon['title']}' width=$buttonSize height=$buttonSize>&nbsp;";
	$icon = completionIcon($apptOrApptId, $performance);
	echo "<img src='{$icon['image']}' title='{$icon['title']}'width=$buttonSize height=$buttonSize>";
	if($icon = durationIcon($apptOrApptId, $performance))
		echo "&nbsp;<img src='{$icon['image']}' title='{$icon['title']}' width=$buttonSize height=$buttonSize>";
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function visitShortDateTimeString($timeOrDate) {
	$time = $timeOrDate == (integer)$timeOrDate ? $timeOrDate : strtotime($timeOrDate);
	return date('g:i a', $time).' '
					.date('D', $time).' '
					.shortestDate($time, $noYear=true);
}

function durationIcon($apptOrApptId, &$performance=null) {
	if(!$performance['duration']) return null;
	if(!$apptOrApptId) return 'No apptid supplied';
	$apptId = is_array($apptOrApptId) ? $apptOrApptId['appointmentid'] : $apptOrApptId;
	$serviceTypeHours = fetchRow0Col0(
		"SELECT hours 
			FROM tblappointment
			LEFT JOIN tblservicetype on servicetypeid = servicecode
			WHERE appointmentid = $apptId
			LIMIT 1", 1);
	if($serviceTypeHours) {
		$serviceTypeHours = explode(':', $serviceTypeHours);
		$serviceTypeSeconds = $serviceTypeHours[0] * 3600 + $serviceTypeHours[1] * 60;
		if($serviceTypeSeconds - $performance['duration'] > 60) { // allow a minute's grace
			$durationMinutes = round($performance['duration'] / 60);
			$serviceTypeMinutes = round($serviceTypeSeconds/60);
			$performance['shortvisit'] = $serviceTypeMinutes - $durationMinutes;
			return 
				array(
				'image'=>'art/completionicons/timewarning.png',
				'title'=>"Visit duration was short: $durationMinutes minutes instead of $serviceTypeMinutes minutes."
				//."\n".print_r($performance, 1)
				);
		}
	}
}

function arrivalIcon($apptOrApptId, &$performance) {
	if(!$apptOrApptId) return 'No apptid supplied';
	$appt = is_array($apptOrApptId) ? $apptOrApptId 
						: fetchFirstAssoc("SELECT date, timeofday, starttime, endtime FROM tblappointment WHERE appointmentid = $apptOrApptId LIMIT 1", 1);
	if(!$performance)
		//$performance = getOrInitNotePerformance($apptOrApptId);
		$performance = evaluatePerformance($apptOrApptId);
	$not = !$performance['arrived'] ? '-not' : '';
}
	$gracePeriodSeconds = $_SESSION['preferences']['visitsStaleAfterMinutes'];
	$gracePeriodSeconds = ($gracePeriodSeconds ? $gracePeriodSeconds : 15) * 60;
	
	$frame = appointmentTimeFrameTimes($appt);	// 'starttime', 'endtime', 'framedurationseconds']

	$futurity = (time() < $frame['starttime']) ? 'future' : (
							time() > $frame['starttime'] && time() <= $frame['endtime'] ? 'current' : 'past');
//if($futurity == 'current') {echo "FUTURITY: $futurity TIME: ".time()." FRAME STARTTIME: {$frame['starttime']}<p>".print_r($frame, 1);exit;}							
	$arrived = strtotime($performance['arrived']);
	if(!$arrived) {
//echo "TIME: ".time().".   {$frame['endtime']} + $gracePeriodSeconds = ".($frame['endtime'] + $gracePeriodSeconds).'<hr>';		
		$image =
			$futurity == 'future' ? null : (
			$futurity == 'current' ? 'art/arrivalicons/arrival-not-current.png' : (
			$frame['endtime'] + $gracePeriodSeconds < time() ? 'art/arrivalicons/arrival-not-late.png' :
			'art/arrivalicons/arrival-not-laggy.png'));
		$title =
			!$image ? "Not marked arrived and not due to start yet." : (
			strpos($image, 'late') ? "Not marked arrived and the visit is definitely overdue." : (
			strpos($image, 'current') ? "Not marked arrived, but there is still time." : (
			"Not marked arrived and the visit is overdue. [$image]")));
		$arrivalcat = $futurity == 'current' ? 'ontime' : (
							$futurity == 'future' ? 'early' : (
							$frame['endtime'] + $gracePeriodSeconds < time() ? 'late' : 'laggy'));
		$performance['arrivalcat'] = "arrived_$arrivalcat";
	}
		
	else {
		$elsewhereThreshold = 300; // feet.  this threshold is from provider and visit maps as a literal.
		$where = $performance['arrivaldelta'] == -1 ? 'nodata' : (
							$performance['arrivaldelta'] > $elsewhereThreshold ? 'away' : 'client');
		if($where != 'client') $performance['location'] = $where;
		//$timing = $arrived < $frame['startime'] ? 'early' : (
		//					$frame['endtime'] + $gracePeriodSeconds > time() ? 'laggy' : (
		//					$frame['endtime'] > time() ? 'late' : 'ontime'));
//echo "TIME: ".time().".   {$frame['endtime']} + $gracePeriodSeconds = ".($frame['endtime'] + $gracePeriodSeconds).'<hr>';		
//print_r($performance);
		$timing = !$performance['startlag'] ? 'ontime' : (
							$performance['startlag'] < 0 ? 'early' : (
							$performance['startlag'] <= $gracePeriodSeconds ? 'laggy' : 'late'));
		$arrivalcat = !$performance['startlag'] ? 'ontime' : (
							$performance['startlag'] < 0 ? 'early' : (
							$performance['startlag'] <= $gracePeriodSeconds ? 'laggy' : 'late'));
		$performance['arrivalcat'] = "arrived_$arrivalcat";
		
		$image = "art/arrivalicons/arrival-$timing-$where.png";
		$arrivalTimeNote = visitShortDateTimeString($arrived);
		$timing = $timing == 'ontime' ? ' on time' : (
							$timing == 'laggy' ? ' a little late' : " $timing");
		$title[] = "Sitter marked arrival$timing at $arrivalTimeNote";
		$arrivalDelta = $where == 'nodata' ? 'an unknown distance (NO LOCATION)' : number_format(round($performance['arrivaldelta'])).' feet';
		$title[] = "\n";
		$title[] = in_array($where, array('nodata', 'away')) ? "$arrivalDelta away from" : 'at';
		$title[] = "the home of the client.";
		if($performance['duration']) {
			$title[] = "\n";
			$duration = round($performance['duration'] / 60);
			$title[] = "The visit lasted $duration minutes.";
		}
		$title = join(' ', $title);
	}
	
	return array('image'=>$image, 'title'=>$title);	
}
	
function completionIcon($apptOrApptId, &$performance=null) {
	if(!$apptOrApptId) return 'No apptid supplied';
	$appt = is_array($apptOrApptId) ? $apptOrApptId 
						: fetchFirstAssoc("SELECT date, timeofday, starttime, endtime FROM tblappointment WHERE appointmentid = $apptOrApptId LIMIT 1", 1);
	if(!$performance)
		//$performance = getOrInitNotePerformance($apptOrApptId);
		$performance = evaluatePerformance($apptOrApptId);
	$not = !$performance['completed'] ? '-not' : '';

	$gracePeriodSeconds = $_SESSION['preferences']['visitsStaleAfterMinutes'];
	$gracePeriodSeconds = ($gracePeriodSeconds ? $gracePeriodSeconds : 15) * 60;
	
	$frame = appointmentTimeFrameTimes($appt);	// 'starttime', 'endtime', 'framedurationseconds']

	$futurity = time() < $frame['starttime'] ? 'future' : (
							time() > $frame['starttime'] && time() <= $frame['endtime'] ? 'current' : 'past');
							
	$completed = strtotime($performance['completed']);
	if(!$completed && !$performance['completedNonmobile']) {
//echo "TIME: ".time().".   {$frame['endtime']} + $gracePeriodSeconds = ".($frame['endtime'] + $gracePeriodSeconds).'<hr>';		
		$image =
			$futurity == 'future' ? null : (
			$futurity == 'current' ? 'art/completionicons/completed-not-current.png' : (
			$frame['endtime'] + $gracePeriodSeconds < time() ? 'art/completionicons/completed-not-late.png' :
			'art/completionicons/completed-not-laggy.png'));
		$title =
			!$image ? "Not marked complete and not due to start yet." : (
			strpos($image, 'late') ? "Not marked complete and the visit is definitely overdue." : (
			strpos($image, 'current') ? "Not marked complete, but there is still time." : (
			"Not marked complete and the visit is overdue.")));
			
		$completioncat = $futurity == 'current' ? 'ontime' : (
							$futurity == 'future' ? 'early' : (
							$frame['endtime'] + $gracePeriodSeconds < time() ? 'late' : 'laggy'));
		$performance['completioncat'] = "completed_$completioncat";
	}
	else if($performance['completedNonmobile']) {
		$timing = 'ontime';
		$completioncat = 'ontime';
		$performance['completioncat'] = "completed_$completioncat";
		$where = 'nodata';
		$image = "art/completionicons/completed-$timing-$where.png";
		$completionTimeNote = visitShortDateTimeString($performance['completedNonmobile']);
		$timing = $timing == 'ontime' ? ' on time' : (
							$timing == 'laggy' ? ' a little late' : " $timing");
		$title[] = "Completion marked (non-mobile)$timing at $completionTimeNote";
		$title = join(' ', $title);
	}			
	else {
		$elsewhereThreshold = 300; // feet.  this threshold is from provider and visit maps as a literal.
		$where = $performance['completiondelta'] == -1 ? 'nodata' : (
							$performance['completiondelta'] > $elsewhereThreshold ? 'away' : 'client');
		if($where != 'client') $performance['location'] = $where;
		//$timing = $arrived < $frame['startime'] ? 'early' : (
		//					$frame['endtime'] + $gracePeriodSeconds > time() ? 'laggy' : (
		//					$frame['endtime'] > time() ? 'late' : 'ontime'));
//print_r($performance);
		$timing = !$performance['endlag'] ? 'ontime' : (
							$performance['endlag'] < 0 ? 'early' : (
							$performance['endlag'] <= $gracePeriodSeconds ? 'laggy' : 'late'));
		$completioncat = !$performance['endlag'] ? 'ontime' : (
							$performance['endlag'] < 0 ? 'early' : (
							$performance['endlag'] <= $gracePeriodSeconds ? 'laggy' : 'late'));
		$performance['completioncat'] = "completed_$completioncat";
		$image = "art/completionicons/completed-$timing-$where.png";
		$completionTimeNote = visitShortDateTimeString($completed);
		$timing = $timing == 'ontime' ? ' on time' : (
							$timing == 'laggy' ? ' a little late' : " $timing");
		$title[] = "Sitter marked completion$timing at $completionTimeNote";
		$completionDelta = $where == 'nodata' ? 'an unknown distance (NO LOCATION)' : number_format(round($performance['completiondelta'])).' feet';
		$title[] = "\n";
		$title[] = in_array($where, array('nodata', 'away')) ? "$completionDelta away from" : 'at';
		$title[] = "the home of the client.";
		if($performance['duration']) {
			$title[] = "\n";
			$duration = round($performance['duration'] / 60);
			$title[] = "The visit lasted $duration minutes.";
		}
		$title = join(' ', $title);
	}
	
	return array('image'=>$image, 'title'=>$title);	
}

function performanceAttributes() {
	return explode(',', 'arrived,completed,duration,startlag,endlag,arrivaldelta,completiondelta');
}
	

function performanceNote($apptOrApptId) {
	// return an assoc array describing aspects of the visit's performance
	// this is intended to speed up appt description in lists
	require_once "preference-fns.php";
	if(!$apptOrApptId) return 'No apptid supplied';
	$apptId = is_array($apptOrApptId) ? $apptOrApptId['appointmentid'] : $apptOrApptId;
	$perf = getAppointmentProperty($apptId, 'performance');
	if(!$perf) return null;
	static $attributes;
	if(!$attributes) $attributes = performanceAttributes();
	$perf = explode('|', $perf);
	foreach($attributes as $i => $perf)
		$note[$attributes[$i]] = $perf[$i];
	return $note;
}

function notePerformance($apptOrApptId, $attribute, $value, $initialize=false) {
	require_once "preference-fns.php";
	if(!$apptOrApptId) return 'No apptid supplied';
	static $attributes;
	if(!$attributes) $attributes = performanceAttributes();
	$apptId = is_array($apptOrApptId) ? $apptOrApptId['appointmentid'] : $apptOrApptId;
	if($initialize) {
		$note = array_fill (0, count($attributes), '');
	}
	else {
		$perf = performanceNote($apptId);
		$perf[$attribute] = $value;
		foreach($attributes as $i => $attr)
			$note[$i] = $perf[$attr];
	}
	setAppointmentProperty($apptId, 'performance', join('|', $note));
	return $note;
}

function getOrInitNotePerformance($apptOrApptId) { // only for current/past visits
	if(!$apptOrApptId) return 'No apptid supplied';
	$times = is_array($apptOrApptId) ? $apptOrApptId 
						: fetchFirstAssoc("SELECT date, starttime FROM tblappointment WHERE appointmentid = $apptOrApptId LIMIT 1", 1);
	$startStamp = "{$times['date']} {$times['starttime']}";
	if(strtotime($startStamp) > time()) return;
	$apptId = is_array($apptOrApptId) ? $apptOrApptId['appointmentid'] : $apptOrApptId;
	$note = performanceNote($apptId);
	if(!$note) 
		$note = notePerformance($apptId, null, null, $initialize=true);
	return $note;
}

function performancePacket($apptOrApptId) { // for API use
	$appt = is_array($apptOrApptId) ? $apptOrApptId 
						: fetchFirstAssoc("SELECT appointmentid, clientptr, date, timeofday, starttime, endtime FROM tblappointment WHERE appointmentid = $apptOrApptId LIMIT 1", 1);
	$performance = evaluatePerformance($appt);
	$performance['arrival'] = arrivalIcon($appt, $performance);
	$performance['completion'] = completionIcon($appt, $performance);
	$performance['shortvisit'] = durationIcon($appt, $performance);
	return $performance;
}

function evaluatePerformance($apptOrApptId) {
	// arrived,completed,duration,startlag,endlag,arrivaldelta,completiondelta
	$appt = is_array($apptOrApptId) ? $apptOrApptId 
						: fetchFirstAssoc("SELECT appointmentid, clientptr, date, timeofday, starttime, endtime, completed FROM tblappointment WHERE appointmentid = $apptOrApptId LIMIT 1", 1);
	$frame = appointmentTimeFrameTimes($appt);	// 'starttime', 'endtime', 'framedurationseconds']
	$apptid = $appt['appointmentid'];
	$events = fetchAssociationsKeyedBy("SELECT * FROM tblgeotrack WHERE appointmentptr = $apptid AND event != 'mv'", 'event', 1);
	// arrived or completed
	if($events['arrived']) $performance['arrived'] = $events['arrived']['date'];
	if($events['completed']) $performance['completed'] = $events['completed']['date'];
	else if($appt['completed']) $performance['completedNonmobile'] = $appt['completed'];
	if($performance['arrived'] && $performance['completed']) 
		$performance['duration'] = strtotime($performance['completed']) - strtotime($performance['arrived']);
//if($appt['appointmentid'] == 971262)  echo "DURATION: {$performance['duration']} FRAME: ".print_r($frame, 1).' => '.date('Y-m-d H:i', $frame['starttime']).'<br>ARR: '.strtotime($performance['arrived']).'=>'.$performance['arrived'].'<hr>';	
//echo "ARR: {$performance['arrived']} COMP: {$performance['completed']}	==> {$performance['duration']}<hr>";
	// startlag,endlag,arrivaldelta,completiondelta
	if($arrived = $performance['arrived'] ? strtotime($performance['arrived']) : time()) {
		$performance['startlag'] = // negative if it was early start, positive if after the window, or zero if in time frame
			$arrived > $frame['endtime'] ? $arrived - $frame['endtime'] : (
			$arrived < $frame['starttime'] ? $arrived - $frame['starttime'] : 0);
	}
	if($completed = $performance['completed'] ? strtotime($performance['completed']) : time()) {
		$performance['endlag'] = // negative if it was early start, positive if after the window, or zero if in time frame
			$completed > $frame['endtime'] ? $completed - $frame['endtime'] : (
			$completed < $frame['starttime'] ? $completed - $frame['starttime'] : 0);
	}
	
	//if($arrived && $completed)
	//	$performance['duration'] = $completed -  $arrived;
		
	
	if($events) {
		require_once "google-map-utils.php";
		$clientAddress = fetchFirstAssoc( // street2, 
			"SELECT street1, city, state,  zip 
				FROM tblclient
				WHERE clientid = {$appt['clientptr']}
				LIMIT 1", 1);
		if(!$clientAddress
			 || !($clientAddress = googleAddress($clientAddress))
			 || !($clientGeocode = getLatLon($clientAddress))
			) {
			$performance['arrivaldelta'] = -1; // no client address available
			$performance['completiondelta'] = -1; // no client address available
		}
		else {
			if($events['arrived']['lat'] == -999) $performance['arrivaldelta'] = -1;
			else if($events['arrived']['lat']) {
//if($apptid == 709336) echo "clientGeocode: [".print_r($clientGeocode, 1)."]<br>\narrived: [".print_r($events['arrived'], 1)."]<hr>\nevents:<br>\n".print_r($events, 1);
				$performance['arrivaldelta'] = distance($clientGeocode, $events['arrived'], $unitsToReturn='ft');
//if($apptid == 709336) echo "<hr>delta: [{$performance['arrivaldelta']}]";
			}
			if($events['completed']['lat'] == -999) $performance['completiondelta'] = -1;
			else if($events['completed']['lat']) 
				$performance['completiondelta'] = distance($clientGeocode, $events['completed'], $unitsToReturn='ft');
		}
	}
}
	return $performance;
}

function performanceFilterHelp() {
	$dir = "art/arrivalicons";
	$cheatsheet = cheatSheet();
	return <<<HTML
<h2>Using the Visit Performance Filter</h2>
<table class='helpContent'><tr><td style='vertical-align: top;width=50%;'>
On the <b>Visit Performance (Arrivals/Completions)</b> page you will notice a magnifying glass (<img src='art/magnifier.gif'> icon.  
Clicking this icon opens the Visit Performance Filter, which helps you focus on visits that may be problematic.
<p>
Visit attributes are grouped into boxes.  If you hover your mouse over an option in one of these boxes, you will see which
aspect of a vist it represents.  The bar at right shows the "hover" text.
<p>
In the Arrived/Unarrived, Completed/Incomplete, or Early/Ontime/Slightly Late/Very Late
boxes, if you choose ALL of the options it is the same as choosing none of them.  When you choose
all of the options in one of these boxes, the focus of the filter becomes wider, but when only some of the options in a box
are chosen in any of the boxes, the focus of the filter becomes narrower.  So, the more boxes you use, the fewer visits you will see when you apply 
the filter.
<p>
As you select options, you will see the description of the filter change.  When you click the [Apply Filter] button, only visits
matching ALL of the filter criteria will be listed on the Visit Performance page.
<p>
On the Visit Performance page, you can click the crossed out magnifier (<img src='art/magnifier-crossed.gif'>) icon to 
<b>clear the filter</b> and view all of the visits.
</td>
<td style='vertical-align:top'>
<div style='width:200px;padding:3px;background:lightblue;'>$cheatsheet</div>
</td>
</tr>
</table>
HTML;
}

function cheatSheet() {
	$dir = "art/arrivalicons";
	return <<<CHEAT
<img src='$dir/arrived.png'> Include visits marked arrived<br>
<img src='$dir/notarrived.png'> Include visits NOT marked arrived<br>
<img src='$dir/complete.png'> Include visits marked complete<br>
<img src='$dir/notcomplete.png'> Include visits NOT marked complete<br>
<img src='$dir/earlycolor.png'> Include early visits<br>
<img src='$dir/arrivecolor.png'> <img src='$dir/completecolor.png'>  Include on time visits<br>
<img src='$dir/laggycolor.png'> Include slightly late visits<br>
<img src='$dir/latecolor.png'> Include definitely late visits<br>
<img src='art/completionicons/timewarning.png' width=21> Include ONLY visits that were too short.<br>
<img src='$dir/away.png'> Include visits marked AWAY from client home.<br>
<img src='$dir/nodata.png'> Include visits LACKING location info<br>
CHEAT;
}

function visitPerformanceHelp() {
	$dir = "art/arrivalicons";
	return <<<HTML
<style>
.icons, .colors {padding-top:15px;}
.icons td {text-align:center;padding-left:30px;}
.icons th {text-align:center;padding-left:30px;}
.colors td {text-align:center;padding-left:30px;font-weight:bold;}
</style>
<h2>Performance Report Help</h2>
<div class='fontSize1_1em'>
You may notice two or three icons in the Report Column. 
These are provided to help you gauge visit execution quickly and help you spot potential problems sooner.  You can use
<p>
If you hover your mouse over them, each one will show exactly what it means, but here is a guide to help you interpret them.
<p>
<h3>The Icons</h3>
<ul>
<li>The first icon indicates visit <b>Arrival</b> status.
<li>The second icon indicates visit <b>Completion</b> status.
<li>The third icon (a clock, when present) indicates a visit was <b>Shorter</b> than planned.
</ul>
<table class='icons'>
<tr>
		<th colspan=2>Marked Arrived</th>
		<th colspan=2>Marked Complete</th>
		<th>Short Visit</th>
</tr>
<tr>
		<td colspan=2><img src='$dir/arrived.png' title='Sitter marked visit arrival.'></td>
		<td colspan=2><img src='$dir/complete.png' title='Sitter marked visit complete.'></td>
		<td><img src='art/completionicons/timewarning.png' title='Short Visit.' width=20 height=20></td>
</tr>


<tr>
		<th style='padding-top:15px' colspan=2>NOT Marked Arrived</th>
		<th style='padding-top:15px' colspan=2>NOT Marked Complete</th>
</tr>
<tr>
		<td colspan=2><img src='$dir/notarrived.png' title='Sitter has NOT marked visit arrival.'></td>
		<td colspan=2><img src='$dir/notcomplete.png' title='Sitter has NOT marked visit complete.'></td>
</tr>

<tr>
		<td class='colors'><img src='$dir/earlycolor.png' title='Sitter arrived early.'><br>Early</td>
		<td class='colors'><img src='$dir/arrivecolor.png' title='On time for arrival, or still possible to arrive on time.'>
					<img src='$dir/completecolor.png' title='On time for completion, or still possible to complete on time.'><br>On Time</td>
				</td>
		<td class='colors'><img src='$dir/laggycolor.png' title='Sitter is a bit late.'><br>A Bit Late</td>
		<td class='colors'><img src='$dir/latecolor.png' title='Sitter is very late.'><br>Very Late</td>
</tr>

<tr>
		<td colspan=2 class='colors'><img src='$dir/nodata.png' title='No Location Data was received.'><br>No Location Data</td>
		<td colspan=2 class='colors'><img src='$dir/away.png' title='Visit was marked AWAY from client home.'><br>Marked AWAY</td>
</tr>
</table>
<p>
The Short Visit icon (<img src='art/completionicons/timewarning.png' title='Short Visit.' width=20 height=20>) will only appear
for service types (see ADMIN > Service List) that have the Hours value supplied, and only for visits marked both arrived and complete.
<p>So if a "Dog Walk" service type is listed as a 20 minute service and the actual visit duration was only 15 minutes, 
you will see the clock.<p>The clock will not appear
unless Hours is not set for the service type or if either arrival or completion is missing.
<p>
"A Bit Late" (<img src='$dir/laggycolor.png' title='Sitter is a bit late.'>) may sound kind of fuzzy, 
but it ties in to your <b>Overdue Visit Notification Preferences</b> 
(see ADMIN > Preferences > [ General Business ] Overdue Visit Notification Preferences).
<p>
One of the fields in there is "Grace period after end time of visit", or the amount of time after the ending of a visit&apos;s
stated time frame LeashTime will wait before reporting a visit as Overdue.  If you see an arrival or completion icon in orange
that means the visit&apos;s time frame is past, but the grace period is not yet over.
<h3>The Visit Filter</h3>
You can use the visit filter (<img src='art/magnifier.gif'>) to zero in on problematic visits. In the filter window there is
a Help button (<img src='art/help.jpg' height=21>) that explains how to use filters.
<h3>Usage Hints</h3>
<ol>
<li>Hover the mouse over an icon to read details.
<li>If you are looking for potential problems can usually ignore round BLUE and GREEN icons.
<li>Icons with corners indicate missing/problematic location info. ("If it's a square, was he really there?")
<li>RED icons deserve immediate attention.
<li>ORANGE icons are likely to need attention pretty soon.
</ol>
</div>


HTML;
}