<? // itinerary-fns2018.php

function providerItinerary(&$stops, &$clientDetails, $warning) {
	global $reordering, $noconstraints, $provider, $startingaddress, $firstappointmentstart, $generate, $change, $editable;
	$stopLists = array_values($stops);
	$stopKeys = array_keys($stops);
	if(isset($_SESSION)) {
		$secureMode = $_SESSION['preferences']['secureClientInfo'];
		$serviceNames = $_SESSION['servicenames'];
	}
	else {
		$secureMode = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'secureClientInfo'");
		$serviceNames = getServiceNamesById();
	}
	
	$providerNames = getProviderNames();
	$markerCol = $generate ? 'marker|Stop||' : '';
	$arrowCol = $editable ? "arrow|&nbsp;||" : '';
	$columns = explodePairsLine("$arrowCol$markerCol"."client|Client||service|Service||address|Address");
  $rollover = "onMouseOver='highlightRow(this,1)' onMouseOut='highlightRow(this,0)'";
	$rows = array();
	$stopIndex = 0;
	$lastAddress = null;
	$lastMarker = null;
	foreach($stops as $stopKey => $stopAppts) {
		$appt = $stopAppts[0];
		$row = array();
//echo 		"stop_$stopKey: {$_POST["stop_$stopKey"]}<br>";
		$checked = (!$generate && !$change) || $_POST["stop_$stopKey"] ? 'CHECKED' : '';
		$row[0] = 	$secureMode ? $clientDetails[$appt['clientptr']]['clientid'] : $clientDetails[$appt['clientptr']]['clientname'];
		$row[0] = labeledCheckbox($row[0], "stop_$stopKey", $checked, null, null, null, 'boxFirst', 'noEcho');
		//"<input type='checkbox' id='stop_$stopKey' name='stop_$stopKey' $checked> ";
		$row[1] = 	$appt['timeofday'];
		$row[2] = 	$clientDetails[$appt['clientptr']]['address'];
		$arrow = upArrow($stopIndex, $stopLists);
		
		if(!$generate || $row[2] == $lastAddress) $marker = '';
		else {
			if(!$lastMarker) $marker = $firstappointmentstart ? 'A' : 'B';
			else $marker = chr(ord($lastMarker)+1);
			$lastMarker = $marker;
			"<b>$marker</b>";
		}
		$markerTD = $generate ? "<td class='topline'>$marker</td>" : '';
		$lastAddress = $row[2];
		$arrowCell = $editable ? "<td class='topline'>$arrow</td>" : '';
		$rows[] = array('#CUSTOM_ROW#'=>"<tr id='$stopKey"."_top' $rollover>$arrowCell$markerTD<td class='topline'>{$row[0]}</td><td class='topline'>{$row[1]}</td><td class='topline'>{$row[2]}</td></tr>");
		$firstAppt = true;
		foreach($stopAppts as $index => $appt) {
			$row = array();
			if($firstAppt) $row['arrow'] =  downArrow($stopIndex, $stopLists);
			else $firstAppt = false;
			$row['client'] = '&nbsp';
			$row['service'] = $serviceNames[$appt['servicecode']];
			$row['address'] = 	$appt['pets'];
			$row['#ROW_EXTRAS#'] = "id='$stopKey"."_$index' $rollover";
			$rows[] = $row;
		}
		$stopIndex++;
	}
	
	echo "<style>.joblistcolheader  {font-size: 1.05em;
  padding-bottom: 5px; 
  border-collapse: collapse;
  text-align:left;}
  .topline { border-top: solid black 1px;}
  .heading { font-size:2em; }
 </style>\n";
 	if($editable) {
		echo "<form name='itinerary' method='POST'>";

		if($warning) echo "<span style='color:red;font-weight:bold;'>WARNING:</span> $warning<p>";
		
		echo "You can reorder your appointments as you like using the Up and Down arrows.  Click the <b>Generate Directions</b> button to show turn-by-turn directions.<p>";

		echoButton('', 'Generate Directions', 'generateDirections()');

		echo ' ';
		if(!$startingaddress) {
			$providerDetails = getProviderDetails(array($provider), array('googleaddress'));
			$startingaddress = $providerDetails[$provider]['googleaddress'];
		}
		labeledInput("Starting address: ", 'startingaddress', $startingaddress, null, 'emailInput');
		labeledCheckbox("Start from first appointment", 'firstappointmentstart', $firstappointmentstart, null, null, 'firstAppointmentCheck()')  ;
		echo "<p>";

		labeledCheckbox("Don't enforce appointment time order", 'noconstraints', $noconstraints, null, null, 'document.itinerary.submit()')  ;

		if((userRole() == 'o' || userRole() == 'd')&& $provider['email']) echoButton('', "Send to Sitter",  'sendToProvider()');
		if($generate) echoButton('', "Print this page",  'javascript:window.print()', 'HotButton', 'HotButtonDown');

		echo "<p>";
	}
	
	echo "<p><div style='border: solid black 1px'>";
  //tableFrom($columns, $rows, 'WIDTH=100%', 'jobstable', null, null, null, null, null, array('keylabel' => 'keylabel'));
  
  
  
  
  tableFrom($columns, $rows, "WIDTH=100% style='background:white;'", 'jobstable', 'joblistcolheader');
	echo "</div>";
	return $stops;
}

function stopsCanSwitch(&$stopA, &$stopB, $echo=0) {
	global $noconstraints;
	if($noconstraints) return true;
if($echo)echo "<p>COMPARE: A: {$stopA['starttime']}	- {$stopA['endtime']} with B: {$stopB['starttime']}	- {$stopB['endtime']}";
	
	return (strcmp($stopA['starttime'], $stopB['starttime']) <= 0 &&
	        strcmp($stopA['endtime'], $stopB['starttime']) >= 0) ||
	       (strcmp($stopB['starttime'], $stopA['starttime']) <= 0 &&
	        strcmp($stopB['endtime'], $stopA['starttime']) >= 0);
}	        

function upArrow($stopIndex, &$stops) {
	//echo "<p>INDEX: $stopIndex STOPS: ".print_r($stops,1);
	if($stopIndex == 0 || count($stops) == 1) return '&nbsp;';
	if(stopsCanSwitch($stops[$stopIndex][0], $stops[$stopIndex-1][0]))
	  return "<img style='cursor:pointer;' src='art/sort_up.gif' 
	            onClick='move(-1, $stopIndex)'>";
	else return '&nbsp;';
}	  

function downArrow($stopIndex, &$stops) {
	if($stopIndex + 1 == count($stops)) return '&nbsp;';
	if(stopsCanSwitch($stops[$stopIndex][0], $stops[$stopIndex+1][0]))
	  return "<img style='cursor:pointer;' src='art/sort_down.gif' 
	            onClick='move(1, $stopIndex)'>";
	else return '&nbsp;';
}
