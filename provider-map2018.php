<? // provider-map2018.php
/*
Show a map for a given sitter showing:
sitter address (optional)
client addresses on a give day (info: address, appt times)
sitter coordinates for that day
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "appointment-fns.php";
require_once "js-gui-fns.php";
require_once "google-map-utils.php";

$locked = locked('+o-,+p-');
tallyPage('provider-map.php');

$isProvider = userRole() == 'p';
if($isProvider) $id = $_SESSION['providerid'];

$allowUnassigned = TRUE; //staffOnlyTEST() || dbTEST('doggiewalkerdotcom');

$allowAllVisits =  TRUE; //staffOnlyTEST() || dbTEST('pppvb') || $_SESSION['preferences']['enableMultiSitterMaps'];
$allowRightNowVisits = TRUE; //staffOnlyTEST() || dbTEST('pppvb') || $_SESSION['preferences']['enableMultiSitterMaps'];
$allowMultiSitterChoice = staffOnlyTEST() || $_SESSION['preferences']['enableMultiSitterChoiceMaps'];

$elsewhereThreshold = 300; // feet

$IMGHOST = "https://{$_SERVER["HTTP_HOST"]}/";

function googlePinFileNames($root='art') {
	static $fnames;
	if($fnames) return $fnames;
	$colors = explode(',', 'red,yellow,orange,paleblue,green,blue,purple,darkgreen,pink,brown');
	$letters = explode(',', 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z');
	$colorOffset = $letterOffset = 0;
	$alphabetCycles = 0;
	while(count($fnames) < 26 * count($colors)) {
		$fnames[] = "$root/googlemapmarkers/{$colors[$colorOffset]}_Marker{$letters[$letterOffset]}.png";
		$colorOffset += 1;
		$letterOffset += 1;
		if($colorOffset == count($colors)) $colorOffset = 0;
		if($letterOffset == 26) {
			$letterOffset = 0;
			$alphabetCycles += 1;
			$colorOffset = $alphabetCycles;
		}
	}
	return $fnames;
}


//$IMGHOST = "";

extract(extractVars('id,date,showhome,showsitters,sitterids', $_REQUEST));
$date  = $date ? $date : date('Y-m-d');
$date = date('Y-m-d', strtotime($date));
if($id >= 0) {
	$prov = getProvider($id);
	if($prov['userid']) {
		$appts = fetchAssociations("SELECT * FROM tblappointment WHERE providerptr = $id AND date = '$date' AND canceled IS NULL ORDER BY starttime");
		$ids = array();
		foreach($appts as $appt) $ids[] = $appt['clientptr'];
	}
	else if($prov) 
		$_SESSION['frame_message'] = "{$prov['fname']} {$prov['lname']} has no login and so cannot report visits, from a mobile device or otherwise.";
	else /* no $prov */ if($allowUnassigned) {
		$appts = fetchAssociations("SELECT * FROM tblappointment WHERE providerptr = $id AND canceled IS NULL AND date = '$date' ORDER BY starttime");
		$ids = array();
		foreach($appts as $appt) $ids[] = $appt['clientptr'];
	}
	if($id == 0) {
		$appts = fetchAssociations(tzAdjustedSql("SELECT * FROM tblappointment WHERE canceled IS NULL AND date = '$date' $rightNow ORDER BY starttime"));
		foreach($appts as $appt) $apptProviders[$appt['providerptr']] = 0;
	}
}
else {
	$multisitters = true;
	if($id == -2) $rightNow = "AND starttime <= CURTIME() AND CURTIME() <= endtime";
	$sitterids = !$sitterids ? '' : (is_array($sitterids) ? join(',', $sitterids) : $sitterids);
	$sitteridArray = explode(',', $sitterids);
	if($id == -3) $sitterFilter = "AND providerptr IN ($sitterids)";
	$appts = fetchAssociations(tzAdjustedSql("SELECT * FROM tblappointment WHERE canceled IS NULL AND date = '$date' $rightNow $sitterFilter ORDER BY starttime"));
	foreach($appts as $appt) $apptProviders[$appt['providerptr']] = 0;
	$providerNames = array();
	if($apptProviders) {
		$providerNames = fetchKeyValuePairs(
			"SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name
				FROM tblprovider
				WHERE providerid IN (".join(',', array_keys((array)$apptProviders)).")
				ORDER BY name");
		$allPins = googlePinFileNames(globalURL('art'));
		foreach(array_keys($providerNames) as $i => $pid) {
			$providerPins[$pid] = $allPins[$i];
		}
		if(array_key_exists(0,$apptProviders)) {
			$providerNames[0] = 'Unassigned ';
			$providerPins[0] = $allPins[$i+1];
		}
		$providerUsers = fetchCol0(
			"SELECT userid 
				FROM tblprovider 
				WHERE providerid IN (".join(',', array_keys($apptProviders)).")", 1);
	}
	$ids = array();
	foreach($appts as $appt) $ids[] = $appt['clientptr'];
}
// ============================
if(userRole() != 'p') {
	$breadcrumbs = "<a href='provider-list.php'>Sitters</a>";
	if($id > 0) {
		$starting = "&starting=".date('Y-m-d', strtotime($date));
		$shortName = $id == -1 ? 'All Visits' : providerShortName($prov);
		$breadcrumbs .= " - <a href='provider-edit.php?id=$id'>$shortName</a>";
		$breadcrumbs .= " - <a href='prov-schedule-cal.php?provider=$id$starting'>$shortName's Schedule</a>";
	}
}

if($allowMultiSitterChoice) {
	$sitterChoices = fetchKeyValuePairs(
		"SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) name, providerid
			FROM tblprovider
			WHERE active=1
			ORDER by name", 1);
	$sitterChoices = array_merge(array('-Unassigned-'=>0), $sitterChoices);

	$sitterRows = array_chunk($sitterChoices, 4, $preserve_keys=true);

	$sitterChoiceHTML = 
		"<table id='sitterchoiceHTML' style='display:none;background-color:LemonChiffon;' align='center'><td><td colspan=2>"
		.echoButton('', 'Map These Sitters', 'mapSitters()',  $class='', $downClass='', 1, 'Map these sitters.')
		.' '.labeledCheckbox('All Sitters', 'allsitters', $value=(count($sitterChoices) == count($sitteridArray)), $labelClass=null, $inputClass=null, $onClick='selectAllSitterIds(this)', $boxFirst=true, $noEcho=true, $title='Select all no none')
		."</td></tr>";
	foreach($sitterRows as $row) {
		$sitterChoiceHTML .= "<tr>";
		foreach($row as $label => $choiceid) {
			$checked = in_array($choiceid, (array)$sitteridArray) ? 'CHECKED' : '';
			$safeLabel = safeValue($label);
			$label = "<label for='sitter_$choiceid'> $label";
			$sitterChoiceHTML .=  "<td><input label = '$safeLabel' id='sitter_$choiceid' type='checkbox' name='sitterids[]' value='$choiceid' class='' onchange='fillChosenSitterLabel()' $checked> $label</td>";
		}
		$sitterChoiceHTML .=  "</tr>";
	}
	$sitterChoiceHTML .= "</table>";
}



if(!$appts && !$_SESSION['frame_message']) {
	if($prov['providerid'] > 0) 
		$_SESSION['frame_message'] = "{$prov['fname']} {$prov['lname']} has no visits on this day.";
	else if($multisitters) 
		$_SESSION['frame_message'] = "No visits found for ".($id == -2 ? 'the present time' : 'today.');
	else 
		$_SESSION['frame_message'] = "No Unassigned visits found for today.";
}
if(usingMobileSitterApp()) include "mobile-frame.php";
else include "frame.html";

if($prov)
	$pageTitle = "{$prov['fname']} {$prov['lname']}'s Visits on ".longestDayAndDate(strtotime($date));
else if($id == -1) $pageTitle = "All Visits on ".longestDayAndDate(strtotime($date));
else if($id == -2) $pageTitle = "Visits right now ".longestDayAndDate(strtotime($date));
else if($id == -3) $pageTitle = "Selected Sitters ".longestDayAndDate(strtotime($date));
else $pageTitle = "Unassigned Visits on ".longestDayAndDate(strtotime($date));
echo "<h2>$pageTitle ";
echo "<form name='pmap' style='display:inline;font-size:12px;'>";

if(TRUE || $prov['userid'])
	calendarSet(' ', 'date', $date, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, 
							$onChange="go()", $onFocus=null, $firstDayName=null);
else hiddenElement('date', shortDate(strtotime($date)));
$activeProviders = array();
if($allowMultiSitterChoice) $activeProviders['-- Choose --'] = -4;
if($allowUnassigned) $activeProviders['-- Unassigned Visits --'] = 0;
if($allowAllVisits) $activeProviders['-- All Visits --'] = -1;
if($allowRightNowVisits) $activeProviders['-- Visits right now --'] = -2;
if($allowMultiSitterChoice)  $activeProviders['-- Select Sitters --'] = -3;
foreach(array_flip(getProviderShortNames('where active = 1 ORDER BY name')) as $label=>$pid)
	$activeProviders[$label] = $pid;
if($id == -3) $id = -4;
if(userRole() != 'p') selectElement(' ', 'id', $id, $activeProviders, "go()");
else hiddenElement('id', $id);
							
if(staffOnlyTEST()) {echo " ";fauxLink('Filter (staff only)', 'offerChooser()');}
echo $sitterChoiceHTML;
echo "</form>";
echo "</h2>";
if(!$appts) {
	dumpControlJavascript();
	if(!usingMobileSitterApp()) {
		echo "<img src='art/spacer.gif' width=1 height=300>";
		include "frame-end.html";
	}
	exit;
}

$visitScope = $id == 0 ? 'this day' : 'shown';

if($multisitters || !$id) { // unassigned, all visits, visits right now
	$pindims = "width=10 height=17";
	$options = explodePairsLine("None|0||<img src='art/pin-blue.png' $pindims> Working on visits $visitScope|1||<img src='art/pin-blue.png' $pindims><img src='art/pin-lightblue.png' $pindims> All|2");
	$radios = radioButtonSet('showsitters', $value=($showsitters ? $showsitters : '0'), $options, $onClick='go()', $labelClass=null, $inputClass=null, $rawLabel=true);
	$radios = join("<img src='art/spacer.gif' width=20 height=1>", $radios);
	echo "<div style='background:lemonchiffon;display:inline;border:solid grey 1px;padding-bottom:3px;padding-top:10px;padding-left:5px;padding-right:5px;'>Show sitter homes: $radios</div><img src='art/spacer.gif' width=80 height=1>";
}
if(!$id) echo "<p>";

if($multisitters) {
	echo "<div onclick='$(\"#legend\").toggle()' style='width:90%;display:block;cursor:pointer;font-size:1.2em;padding:5px;'>
		".echoButton('null', 'Map Key', "$(\"#legend\").toggle()", 1, 2); // border:solid #C0FFFF 3px;
	echo "<table id='legend' style='display:none;margin-top:10px;width:100%;'><tr>";
	$numcols = 6;
	$tdwidth = round(100/$numcols);
	foreach($providerNames as $id => $name) {
		if($col == $numcols) {$col = 0; echo "</tr>"; if($printed < count($providerNames)) echo "<tr>";}
		$col += 1;
		$printed += 1;
		if($id && $name/* && staffOnlyTEST()*/) {
			$name = fauxLink($name, "openConsoleWindow(\"provdetails\",\"provider-day-detail.php?prov=$id&date=$date\", 500,400);event.stopPropagation()", 1, "Sitter snapshot");
		}
		echo "<td style='width:$tdwidth%'><img src='{$providerPins[$id]}'}> $name</td>";
	}
	echo "</tr></table>";
	echo "</div>";
}
else if($isProvider) {
?>
<img src='art/pin-blue.png'>- Your Home 
<img src='art/spacer.gif' height=1 width=10>
<img src='art/googlemapmarkers/green.png'>- Client Visit(s) complete
<img src='art/spacer.gif' height=1 width=10>
<img src='art/googlemapmarkers/yellow.png'>- Client Visit(s) overdue
<img src='art/spacer.gif' height=1 width=10>
<img src='art/googlemapmarkers/paleblue.png'>- Client Visit(s) not yet due
<? 
}  
else {
?>
<img src='art/pin-sunset.png'>- Client Home
<img src='art/spacer.gif' height=1 width=10>
<img src='art/pin-sunset-question.png'>- Client Home w/ Incomplete Visits
<img src='art/spacer.gif' height=1 width=10>
<? if($prov > 0) { ?>
<img src='art/pin-blue.png'>- Sitter Home 
<? if(userRole() != 'p') { ?>
<img src='art/spacer.gif' height=1 width=10>
<img src='art/pin-green.png'> - Reporting location 
<? 
	}} 
}  
$clients = getClientDetails($ids, array('addressparts','fname', 'lname'));
foreach($clients as $i => $client) {
	$add = array();
	foreach(array('street1','street2','city','state','zip') as $f) {
		$add[$f] = $client[$f];
		$clients[$i]['address'] = htmlFormattedAddress($add);
	}
}
$nextdate = date('Y-m-d', strtotime("+1 day", strtotime($date)));
$tracks = array();
$excludeEvents = TRUE ? "AND event != 'mv'" : '';
$excludeZeros = TRUE ? "AND NOT (lat = 0.0 AND lon = 0.0)" : '';
if($prov) $tracks = fetchAssociations($sql = "SELECT * FROM tblgeotrack 
														WHERE userptr = {$prov['userid']} 
															AND date >= '$date' AND date < '$nextdate' 
															$excludeEvents $excludeZeros
															ORDER BY date");
else if($providerUsers) {
	foreach($providerUsers as $i => $p) if(!$p) $providerUsers[$i] = '0';
	$tracks = fetchAssociations($sql = "SELECT * FROM tblgeotrack 
															WHERE userptr IN (".join(',', $providerUsers).")
																AND date >= '$date' AND date < '$nextdate' 
																$excludeEvents $excludeZeros
																ORDER BY date");	
}

//print_r($clients);exit;
// =========================================================
// copied from googleMap.php
$mapId = 'singlemap';
?>

<style>
.maplabel {color:black;font-size:12px;}
.visitTable {margin-left: 1px;}
.visitTable td {border: solid darkgrey 1px;}
.elsewhere {color: blue;font-weight:bold;}
.whoknowswhere {color: blue;font-weight:bold;font-style:italic;}
</style>

<?    
if($tracks) $tracks = clusterTracks($tracks, $map);  // sets up $appointmentTracks
}


$label = "<span class=maplabel>$googleAddress</span>";

$markers = array();
if($prov) {
	$prov['address'] = personsHTMLAddress($prov);
	if($addr = googleAddress($prov)) {
		$marker = getLatLon($addr);
		$marker['address'] = $prov['address'];
		$marker['googleaddress'] = $addr;
		$marker['icon'] = 'provider';
		$marker['zIndex'] = 999;
		$marker['hovertext'] = str_replace("'", "&apos;", "{$prov['fname']} {$prov['lname']}'s home");
		$marker['infotext'] = str_replace("'", "&apos;", "{$prov['fname']} {$prov['lname']}'s home<br>{$marker['address']}");
		$markers[] = $marker;
	}

	else if($prov['providerid']) $notes[] = "No home address was found for {$prov['fname']} {$prov['lname']}.";
}
else { // show working and idle sitter homes
	//$showsitters = mattOnlyTEST() ? 2 : 0; // 1 = working, 2 = all
	if($showsitters) {
		//$workingprovids = array_keys($apptProviders);
		$allsitterids = fetchCol0("SELECT providerid FROM tblprovider WHERE active = 1", 1);
		foreach($allsitterids as $provid) {
			$workingToday = array_key_exists($provid, $apptProviders);
			if($showsitters == 1 && !$workingToday) continue;
			$aprov = fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid = $provid LIMIT 1", 1);
			$aprov['address'] = personsHTMLAddress($aprov);
			if($addr = googleAddress($aprov)) {
				$marker = getLatLon($addr);
				$marker['address'] = $aprov['address'];
				$marker['googleaddress'] = $addr;
				$marker['icon'] = ($workingToday ? 'provider' : 'providernotworking') ;
				$marker['zIndex'] = 999;
				$marker['hovertext'] = str_replace("'", "&apos;", "{$aprov['fname']} {$aprov['lname']}'s home");
				if(!$workingToday) $marker['hovertext'] .= " (not working on any visit $visitScope)";
				$marker['infotext'] = str_replace("'", "&apos;", "{$marker['hovertext']}<br>{$marker['address']}");
				$markers[] = $marker;
			}
			
		}
	}
}


//echo 'Prov: '.googleAddress($prov).'<br>';
//$addresses = array(googleAddress($prov));
$showVisitMaps = TRUE || $_SESSION['preferences']['enableNativeSitterAppAccess'];
$visitMapColumnHeader = $showVisitMaps ? '<th></th>' : '';
foreach($clients as $clientid => $client) {
//if(mattOnlyTest()) echo "<p>".print_r($appointmentTracks, 1)."<p>tracks:".print_r($tracks, 1);
//print_r($client);	
	$html = "";
	$html .= "{$client['clientname']}'s home<div style='display:block;padding-left:10px;'>{$client['address']}</div>";
	if($isProvider) {
		$root = globalURL('art/googlemapmarkers');
		$checkmark = '&#10003;';
		$html .= "<br>Visits:<table class='visitTable'><tr><th>Scheduled</th><th>Status</th</tr>";
		foreach($appts as $i => $appt) if($appt['clientptr'] == $client['clientid']) {
			if($appt['completed']) $status = "$checkmark ".date('h:i a', strtotime($appt['completed']));
			else if(appointmentFuturity($appt) == -1 && !$appt['completed']) $status = "* overdue";
			else {
				$status = "--";
				}
			if(1 || !$visitpins[$appt['clientptr']]) {
				$char = chr($i+65);
				$color = $appt['completed'] ? 'green' : ($status == "* overdue" ? 'yellow' : 'paleblue');
				$visitpins[$appt['clientptr']] = "$root/{$color}_Marker$char.png";
			}
			$html .= "<tr><td>{$appt['timeofday']}</td><td>$status </td></tr>";
		}
		$html .= "</table>";
	}
	else { // manager
		$html .= "<br>Visits:<table class='visitTable'><th>Scheduled</th><th>Arrived</th><th>Completed</th>$visitMapColumnHeader</tr>";
		$overdue = false;
		$someIncomplete = false;
		foreach($appts as $appt) if($appt['clientptr'] == $client['clientid']) {
			if(appointmentFuturity($appt) == -1 && !$appt['completed']) $overdue = true;
			else if(!$appt['completed']) $someIncomplete = true;
	
	
			$arrival = $appointmentTracks[$appt['appointmentid']]['arrived'];
			if(!$arrival && $prov) {
				$arrival = fetchFirstAssoc($sql = "SELECT * FROM tblgeotrack 
																		WHERE userptr = {$prov['userid']} 
																			AND date >= '$date' AND date < '$nextdate' 
																			AND appointmentptr = {$appt['appointmentid']}
																			AND event = 'arrived'");
			}			
//if(mattOnlyTEST() && $appt['clientptr'] == 3276) echo "<br>ARR [{$appt['appointmentid']}]: ".print_r($arrival,1);
			$arrived = $arrival ? date('g:i a', strtotime($arrival['date'])) : '';


			$arrivalDelta = "";
			if(!$arrival['clientdeltafeet']) 
				$arrivalDelta = "class='whoknowswhere' title='Mobile App not used.'";
			else if($arrival['clientdeltafeet']) {
				if($arrival['clientdeltafeet'] == '-') 
					$arrivalDelta = "class='whoknowswhere' title='No location data available.'";
				else {
					if($arrival['clientdeltafeet'] > $elsewhereThreshold) {
						$arrivalDelta = convertFeet($arrival['clientdeltafeet']);
						$deltaerror = $arrival['clientdeltaerror'];
						$arrivalDelta = "class='elsewhere' title='Arrival occurred $arrivalDelta from client home{$deltaerror}.'";
					}
				}
			}
			
			$completion = $appointmentTracks[$appt['appointmentid']]['completed'];
			$completed = $appt['completed'] ? date('g:i a', strtotime($appt['completed'])) : '';
			$completionDelta = "";
			if(!$completion['clientdeltafeet']) 
				$completionDelta = "class='whoknowswhere' title='Mobile App not used.'";
			else if($completion['clientdeltafeet']) {
				if($completion['clientdeltafeet'] == '-') 
					$completionDelta = "class='whoknowswhere' title='No location data available.'";
				else {
					if($completion['clientdeltafeet'] > $elsewhereThreshold) {
						$completionDelta = convertFeet($completion['clientdeltafeet']);
						$deltaerror = $completion['clientdeltaerror'];
						$completionDelta = "class='elsewhere' title='Completion marked $completionDelta from client home{$deltaerror}.'";
					}
				}
			}

			$mapLink = $showVisitMaps ? "<td><a href='visit-map.php?id={$appt['appointmentid']}'>Map</a></td>" : "";
			$timeofdayTip = $multisitters ? "title=\"".safeValue($providerNames[$appt['providerptr']])."\"" : '';
			$timeOfDayContent = $appt['timeofday'];
			if(TRUE || staffOnlyTEST() || dbTEST('careypet')) $timeOfDayContent = fauxLink($timeOfDayContent, "openAppointment({$appt['appointmentid']})", 1, "Open this visit by ".safeValue($providerNames[$appt['providerptr']]));
			if(TRUE || staffOnlyTEST()) {
				$provPin = $providerPins[$appt['providerptr']] ? $providerPins[$appt['providerptr']] : "{$IMGHOST}art/pin-blue.png";
				$sitterPin = 
					" <img title='Click to see a sitter snapshot for ".safeValue($providerNames[$appt['providerptr']])."'
					  onclick='openConsoleWindow(\"provdetails\",\"provider-day-detail.php?prov={$appt['providerptr']}&date=$date\", 500,400);'
					  src='$provPin'}
					  height=15
					  width=10>";
			}
			$html .= "<tr><td $timeofdayTip>$timeOfDayContent$sitterPin</td><td $arrivalDelta>$arrived</td><td $completionDelta>$completed</td>$mapLink</tr>";

			if($multisitters && !array_key_exists('sitterptr', $client))
				$client['sitterptr'] = $appt['providerptr'];
			// if a visit for this client is unassigned, the sitterptr for the client should be zero
			// to ensure that the pin is marked unassigned
			if(!$appt['providerptr']) $client['sitterptr'] = 0;

			//if($appt['completed']) $html .= " <font color='green'>Marked complete: </font>".date('g:i: a', strtotime($appt['completed']));
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { $html .= "[{$appt['date']} {$appt['endtime']}]"; }		
		}
		$html .= "</table>";
		$html .= "<p>Key:<div style='padding-left:5px;margin-top:-14px;font-size:0.85em'><span class='elsewhere'>Blue time</span> = Recorded Away from the client's home";
		$html .= "<br><span class='whoknowswhere'>Blue italics = </span>No Location info Available</div>";
	}
	
	
	
	
	if($addr = googleAddress($client)) {
		$icon = $multisitters ? $providerPins[$client['sitterptr']] : (
									$visitpins ? $visitpins[$clientid] : (
									$overdue ? 'overdue' : (
									$someIncomplete ? 'someIncomplete' : (
									'client'))));  // 'pin-sunset-question.png' : 'pin-sunset.png';
		$marker = getLatLon($addr);
		$marker['address'] = $client['address'];
		$marker['googleaddress'] = $addr;
		$marker['icon'] = $icon;
		$marker['zIndex'] = 100;
		$marker['hovertext'] = str_replace("'", "&apos;", "{$client['fname']} {$client['lname']}'s home");
		$marker['infotext'] = $html; //str_replace("'", "&apos;", "{$client['fname']} {$client['lname']}'s home<br>{$marker['address']}");
//if(mattOnlyTEST() && $client['clientid'] == 2118) echo "<hr><hr>$html<hr><hr>";
		$markers[] = $marker;
	}
		//$overdueicon;
		//$addresses[] = $addr;
	else $notes[] = "No home address was found for {$client['clientname']}.";
//echo 'Client: '.googleAddress($client).'<br>';
}
if(mattOnlyTEST()) echo "tracks: ".print_r(count($tracks), 1);

?>
<a title='Learn how to use this map.' onclick='$.fn.colorbox({href:"help/sitter-maps.php<?= $isProvider ? '?role=p' : '' ?>", iframe: true, width:"600", height:"600", scrolling: true, opacity: "0.3"});'>
<img src='art/help.jpg' style='padding-left:40px;height:35px;width:35px;border: 0px;cursor:pointer;' >
</a>
<?

if(!$isProvider) foreach($tracks as $track) {
	if($track['error']) continue;
}
	$title = ($br = strpos($track['time'], '<br>')) ? substr($track['time'], 0, $br).'...' : $track['time'];
	
	$marker = array('lat'=>$track['lat'], 'lon'=>$track['lon']);
	//$marker['address'] = $client['address'];
	//$marker['googleaddress'] = $addr;
	$marker['icon'] = $track['event']; //'reporting';
	$marker['zIndex'] = 3;
	$marker['hovertext'] = str_replace("'", "&apos;", $title);
	$marker['infotext'] = str_replace("'", "&apos;", $title);
	$markers[] = $marker;
}
}
if($notes) {
	echo "<p>Note:<ul>";
	foreach($notes as $note) echo "<li>$note\n";
	echo "</ul>";
}

function clusterTracks($tracks, $map) {
	global $appointmentTracks;
	$radius = 20;
	$newTracks = array();
	$clientHomes = array();
	foreach($tracks as $i => $track) {
		$added = false;
		$permaTrack = &$track;
		foreach($newTracks as $i => $newTrack) {
			if(distance($track,$newTrack,$unit='ft') < $radius) {
				$newTracks[$i]['time'][] = $track['date'];
				$permaTrack = &$newTracks[$i];
				$added = true;
				break;
			}
		}
		if($track['appointmentptr']) {
			$appt = fetchFirstAssoc($sql = "SELECT CONCAT_WS(' ', fname, lname) as name, pets, timeofday, clientptr, street1, zip 
																FROM tblappointment
																LEFT JOIN tblclient ON clientid = clientptr
																WHERE appointmentid = {$track['appointmentptr']} LIMIT 1");
			if(!isset($clientHomes[$appt['clientptr']])) {
				$googleAdd = googleAddress($appt);
				/*if($googleAdd) $clientHomes[$appt['clientptr']] = 
					fetchFirstAssoc("SELECT lat, lon FROM geocodes WHERE address = '"
						.mysqli_real_escape_string($googleAdd)."' LIMIT 1");*/

//if(TRUE || !isset($clientHomes[$appt['clientptr']])) 
				if($googleAdd) $clientHomes[$appt['clientptr']] = getLatLon($googleAdd);
			

			}
			
			if(($homeLoc = $clientHomes[$appt['clientptr']])
					&& !$track['error']
					&& (($delta = distance($track,$homeLoc,$unit='ft')) > $radius)) {


				$permaTrack['clientdeltafeet'] = $delta;
//if(mattOnlyTEST() && $track['appointmentptr']== 224382) 
//	echo "<hr>".print_r($track,1)."<p>({$track['lat']},{$track['lon']},{$homeLoc['lat']},{$homeLoc['lon']}";
			}
//if(mattOnlyTEST() && $delta) echo "({$track['appointmentptr']}) track: [{$track['event']} lat: {$track['lat']} lon: {$track['lon']}] homeLoc: ".print_r($homeLoc, 1)." delta: $delta<br><hr>"; //.print_r($appointmentTracks,1);
			else if(!$homeLoc) $permaTrack['clientdeltafeet'] = '-';
			else if($track['error']) $permaTrack['clientdeltafeet'] = '-';
			$permaTrack['visits'][$track['date']] = " - {$appt['name']} ({$appt['pets']}) {$appt['timeofday']} {$track['event']}";

			$track['clientdeltafeet'] = $permaTrack['clientdeltafeet'];
			if(array_key_exists('accuracy', $track))
				$track['clientdeltaerror'] = " +/- ".convertMeters($track['accuracy'], $preciseAlso=true);
		}
		if(!$added) {
			$track['time'] = array($track['date']);
			$newTracks[] = $track;
		}
		$appointmentTracks[$track['appointmentptr']][$track['event']] = $track;
//if(mattOnlyTEST() && $track['appointmentptr']== 224382) echo "<p>".print_r($appointmentTracks[$track['appointmentptr']], 1);
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($newTracks); }
	foreach($newTracks as $i => $track) {
		sort($track['time']);
		foreach($track['time'] as $j => $t) 
			$newTracks[$i]['time'][$j] = date('h:i a', strtotime($t)).$track['visits'][$t];
		$newTracks[$i]['time'] = join("<br>", $newTracks[$i]['time']);
	}
	return $newTracks;
				
}

function dumpControlJavascript() {
	
	$scriptName = substr($_SERVER["SCRIPT_NAME"], 1);
	echo <<<JS
<script src='popcalendar.js' language='javascript'></script>
<script src='check-form.js' language='javascript'></script>
<script language='javascript'>

function mapSitters() {
	let selectedsitters = [];
	$( "input:[name='sitterids[]']:checked" ).each(function(index, el) { if(el.name == 'sitterids[]') { selectedsitters.push(el.value); }});
	if(selectedsitters.length == 0) {
		alert('Please choose at least one sitter.');
		return;
	}
	$('#id').val(-3);
	go(1);
}

function go(ignoreid) {
	let pleaseignoreid = typeof ignoreid == 'undefined' ? false : ignoreid;
	var id = document.getElementById('id').value;
//if(id == -33)	alert('BANG!');
	if(!pleaseignoreid && id == -3) {
		$('#sitterchoiceHTML').toggle();
		$('#id').val(-4);
		return;
	}
	if(!MM_validateForm('date', '', 'R', 'date', '', 'isDate'))
		return;
	var date = document.getElementById('date').value;
	var showsitters = '';
	if(document.getElementById('showsitters_0')) {
		showsitters = 
			document.getElementById('showsitters_0').checked ? '' : (
			document.getElementById('showsitters_1').checked ? '1' : (
			document.getElementById('showsitters_2').checked ? '2' : ''));
$DEBUG
		if(showsitters != '') showsitters = "&showsitters="+showsitters;
	}
	let selectedsitters = [];
	$( "input:[name='sitterids[]']:checked" ).each(function(index, el) { if(el.name = 'sitterids') selectedsitters.push(el.value); });
	selectedsitters = selectedsitters.length > 0 ? "&sitterids="+selectedsitters.join(',') : '';
	if(id == -4) {
		if(selectedsitters.length > 0) id = -3; // if sitters previously selected and no mode is set
		else return; // no-op
	}
	document.location.href="$scriptName?id="+id+"&date="+escape(date)+showsitters+selectedsitters;
}

function selectAllSitterIds(box) {
	//alert(box.id+':'+box.checked);
	$( "input[name='sitterids[]']" ).each(function(index, el) { $(el).prop( "checked", box.checked);});
}


JS;

	dumpPopCalendarJS();
	echo <<<JS
init();
</script>
JS;
}

$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
$editScript = $roDispatcher ? "appointment-view.php?id=" : "appointment-edit.php?id=";


}

?>

<div id="map" style='height:700px; width:700px;'></div>

<script language='javascript'>
var map;
var nonTargetMarkerLabels = []; // to make pins removable
var nonTargetMarkers = []; // to make pins removable
var nonTargetMarkerIcons = []; // to make pins removable
function initMap() {
	var options = {mapTypeIds: ["ROADMAP"]};//new google.maps.MapTypeControlOptions;
	//options.mapTypeIds = {ROADMAP};
	map = new google.maps.Map(document.getElementById('map'), {
		mapTypeControlOptions: options
		//center: new google.maps.LatLng(-33.863276, 151.207977),
		//zoom: 12
	});
	
	var infoWindow = new google.maps.InfoWindow;
	var markers = /* JSON.parse('*/<?= json_encode($markers)  ?>/*') */;
	var chooserHTML;
	
	//var markers = JSON.parse('[{"lat":"38.8815","lon":"-77.1741","address":"250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046",
	//"googleaddress":"250 S Maple Ave, Falls Church, VA","icon":"client","zIndex":999,"hovertext":"Elroy Krum's home","infotext":"Elroy Krum's home<br>250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046"},{"lat":"38.8362","lon":"-77.1089","address":"4713 West Braddock Road #10, Alexandria, VA","googleaddress":"4713 West Braddock Road #10, Alexandria, VA","icon":"provider","zIndex":0,"hovertext":"Brian Martinez&apos;s home","infotext":"Brian Martinez&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>4713 West Braddock Road #10<br>Alexandria, VA 22311<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=42\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8975","lon":"-77.1297","address":"5015 23rd Road N, ARLINGTON, VA","googleaddress":"5015 23rd Road N, ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"John Masters&apos;s home","infotext":"John Masters&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>5015 23rd Road N<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=17\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8932","lon":"-77.1135","address":"1810 N. Taylor St., ARLINGTON, VA","googleaddress":"1810 N. Taylor St., ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"Josh Odmark&apos;s home","infotext":"Josh Odmark&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>1810 N. Taylor St.<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=20\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8834","lon":"-77.1765","address":"40 James St, 3b, Falls Church, VA","googleaddress":"40 James St, 3b, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Cam Stull&apos;s home","infotext":"Cam Stull&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>40 James St, 3b<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=27\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8854","lon":"-77.1715","address":"228 Governers Ct, Falls Church, VA","googleaddress":"228 Governers Ct, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Elizabeth Tanner&apos;s home","infotext":"Elizabeth Tanner&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>228 Governers Ct<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=22\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"}]');	
	var icons = {
		client: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-sunset.png"
						},
		provider: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-blue.png"
						},
		providernotworking: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-lightblue.png"
						},
		reddot: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-reddot.png"
						},
		completion: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-green.png"
						},
		overdue: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-pulsating.gif"
						},
		someIncomplete: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-sunset-question.png"
						},
		arrived: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-green.png"
						},
		completed: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-green.png"
						}
		}

	var bounds  = new google.maps.LatLngBounds();
	for(var i = 0; i < markers.length; i++) {
		markerinfo = markers[i];
//alert(	icons[markerinfo.icon].url); break;	
		var point = new google.maps.LatLng(
		                  parseFloat(markerinfo.lat),
		                  parseFloat(markerinfo.lon));
		var icon = typeof icons[markerinfo.icon] == 'undefined' ? markerinfo.icon : icons[markerinfo.icon];
		var marker = new google.maps.Marker({
                map: map,
                position: point,
                icon: icon,
                zIndex: markerinfo.zIndex,
                title: markerinfo.hovertext.replace('&apos;', "'")
              });
              
		marker.bubble = new google.maps.InfoWindow({content:markerinfo.infotext});
		marker.addListener('click', function() {
                this.bubble.open(map, this);
							});
              
		if(markerinfo.icon != 'focus') { // ALWAYS TRUE
			nonTargetMarkers.push(marker);
			nonTargetMarkerLabels.push(markerinfo.hovertext.trim());
			let url = typeof icon == 'string' ? icon : icon.url;
			nonTargetMarkerIcons.push("<img src='"+url+"' style='vertical-align:middle;'>");
		}
		
		bounds.extend(point);
	}
	map.fitBounds(bounds);       // auto-zoom
	map.panToBounds(bounds);     // auto-center
	
}
</script>

<script async defer
src="https://maps.googleapis.com/maps/api/js?key=<?= $googleMapAPIKey ?>&callback=initMap">
</script>



<script src='common.js' language='javascript'></script>
<script language="javascript">
function openAppointment(id) {
	openConsoleWindow('editappt', '<?= $editScript ?>'+id,530,550)
}
function update(aspect, returnedText) {
	if(aspect == 'appointments') refresh();		
}

function toggle(i) {
	var marker = nonTargetMarkers[i];
//alert(marker.getMap());	
	marker.setMap(marker.getMap() ? null : map); // map is global
	$('#allboxes').prop( "checked", false);
}

function offerChooser() {
	$.fn.colorbox({html:getChooserHTML(), width:"300", height:"500", scrolling: true, opacity: "0.3"});
}

function selectAll(el) {
	var on = el.checked;
	for (var i=0; i < nonTargetMarkers.length; i++) {
		var marker = nonTargetMarkers[i];
		$('input').prop( "checked", on);
		marker.setMap(on ? map : null); // map is global
	}
}

function getChooserHTML() {
	var checkedCount = 0;
	var chooserHTML = '';
	for (var i=0; i < nonTargetMarkers.length; i++) {
		let marker = nonTargetMarkers[i];
		let key = nonTargetMarkerLabels[i];
		var CHECKED = marker.getMap() != null ? 'CHECKED' : '';
		checkedCount += CHECKED ? 1 : 0;
		chooserHTML += "<li><input id='cb_"+i+"' type='checkbox' "+CHECKED+" onclick='toggle("+i+")'> "
			+"<label for='cb_"+i+"'>"+nonTargetMarkerIcons[i]+' '+nonTargetMarkerLabels[i]+"</label>";
	}
	chooserHTML += "</ul></form>";
	CHECKED = checkedCount == nonTargetMarkers.length ? 'CHECKED' : '';
	
	chooserHTML = 
		"Show only the following:<form><ul>"+
		"<li><input id='allboxes' type='checkbox' "+CHECKED+" onclick='selectAll(this)'>"+
		"<label for='allboxes'>All Pins</label>"+		chooserHTML;
	return chooserHTML;
}


</script>
<?
include "js-refresh.php";

dumpControlJavascript();

if(!usingMobileSitterApp()) include "frame-end.html";
