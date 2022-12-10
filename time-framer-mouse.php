<?
/*
* time-framer-mouse.php
* Used to create a reusable popup div for selecting a time frame in the form: "hh:mm-hh:mm" (24 hour time).
* Resulting time frame may be rendered differently (12hr or 12hr format or named time frames) in launching widget.
*
* Usage:
* 1. Create the div with makeTimeFramer($id), specifying the DIV's id
* 2. Create one or more form elements or HTML objects with innerHTML whose values are to receive time frame.
* 3. To populate each of these target elements, pass the id (not name) attribute 
*    of the target element into showTimeFramer(event, targetId)
* 4. After time frame is selected and "Done" is pressed, the chosen time frame will replace the value of the target element.
* 5. Time frame may or may not enforce that start time falls before end time.
* 6. Call dumpTimeFramerJS($id) to insert supporting JS
*
* Script vars:
*   timeFrameId = the id of the reusable timeFrame widget
*   timeFrameElementId = set when showTimeFramer is called: the id of the target element
*   nullTimeFrameLabel = the string to which the target element's value will be set when no timeframe is selected
*   defaultTimeFrames = an array of up to seven time frames ('Label' => 'timeframe') externally set by client preference
*   defaultTimeFrameLength = minutes in default length time frame (120)
*   timeStyle = 12hr or 24hr
*
* CSS Classes:
*   timeFrame = looks for the timeFrame div
*   timeframeinput = looks for the timeFrame inputs
*
*/
require_once "timeframe-fns.php";


$timeStyle = '12hr';
$defaultTimeFrameLength = 120;
/*  -- moved to timeframe-fns.php: 
$defaultTimeFrames = isset($defaultTimeFrames) && $defaultTimeFrames ? $defaultTimeFrames
                       : array('Morning'=>'07:00-09:00','Late Morning'=>'09:00-11:00',
                              'Midday'=>'11:00-13:00', 'Afternoon'=>'13:00-15:00',
                              'Late Afternoon'=>'15:00-17:00', 'Evening'=>'17:00-19:00',
                              'Night'=>'19:00-21:00');
*/                              
                              
                              
                              

/* Time  	---  	---
a 	Lowercase Ante meridiem and Post meridiem 	am or pm
A 	Uppercase Ante meridiem and Post meridiem 	AM or PM
B 	Swatch Internet time 	000 through 999
g 	12-hour format of an hour without leading zeros 	1 through 12
G 	24-hour format of an hour without leading zeros 	0 through 23
h 	12-hour format of an hour with leading zeros 	01 through 12
H 	24-hour format of an hour with leading zeros 	00 through 23
i 	Minutes with leading zeros 	00 to 59
*/

function formattedTime($timestr) {  // 24hr format
	global $timeStyle;
	if($timeStyle == '12hr') return date("g:i a", strtotime("12/12/2008 $timestr"));
	else return date("H:i", strtotime("12/12/2008 $timestr"));
}

function formattedTimeFrame($framestr) { 
	$times = explode('-',$framestr);
	return formattedTime(trim($times[0])).'-'.formattedTime(trim($times[1]));
}

function time24($timestr) {
	return strtotime("12/12/2008 $timestr");
}
                              
function makeSimplifiedTimeFramer($id, $clientList=TRUE) {
	global $defaultTimeFrames;
	
	$timeframes = getTimeframeMenuChoices($clientList);
	if(!$timeframes) $timeframes = $defaultTimeFrames;
	$pixelsWide = 110;
	$width = "width:$pixelsWide".'px;';
  echo "<div id='$id' class='timeFrame' style='visibility:hidden;position:absolute;$width;font-size:1.1em;text-align:center'><form id='timeframer' name='timeframer'>\n<input type=hidden name=originaldate id=originaldate>"; //
  hiddenElement($id."_simple", 1);
	$links = array();
	foreach($timeframes as $label => $time) {
		$frame = formattedTimeFrame($time);
		if($displayTimeFrames) $label .= " $frame";
		//echo "<a href=# onClick='setTime(\"$frame\")' title='$frame'>$label</a>";
		$links[] = frameLink($label, $frame);
	}
	if($displayTimeFrames) {
		echo "<table><tr><td>";
		echo join($links, '</td></tr><tr><td>');
		echo "</td></tr></table>";
	}
	else echo join($links, '<br>');
  
  /*foreach($timeframes as $label => $time) {
		$frame = formattedTimeFrame($time);
		//echo "<a href=# onClick='setTime(\"$frame\")' title='$frame'>$label</a>";
		frameLink($label, $frame);
		echo "<br>";
	}*/
	echo "<br><div id='frametimemeaning'style='text-align:center'>&nbsp;</div><br>";
  echoButton('','Cancel','hideTimeFramer()');
  echo "\n</form></div>";
}

function makeTimeFramer($id, $narrow=false, $noNameLinks=false, $clearButton=false, $extraStyle='', $displayTimeFrames=false) {
	global $defaultTimeFrames;	
	$pixelsWide = $narrow ? 240 : 420;
	if(strpos("$extraStyle", "width:") === FALSE)
		$width = "width:$pixelsWide".'px;';
  echo "<div id='$id' class='timeFrame' style='z-index:999;visibility:hidden;position:absolute;$width;$extraStyle'><form id='timeframer' name='timeframer'>\n<input type=hidden name=originaldate id=originaldate>"; //
  $colon = '<b>:</b>';
	//$width = "width=".($pixelsWide-5);
	//if(mattOnlyTEST()) 	$width = "width=''";
  echo "<table $width border=0><tr>";
  echo "<td class='timeframerlabel'>Start time: ";  hourSelect('hour0'); echo $colon; minuteSelect('minute0');
  if($narrow) echo "<tr>";
  echo "<td class='timeframerlabel'> End time: ";  hourSelect('hour1'); echo $colon; minuteSelect('minute1');
  echo "\n</table>\n";
  echoButton('','Done','setTime(null)');
  echo " ";
  echoButton('','Cancel','hideTimeFramer()');
  echo " ";
  if($clearButton) echoButton('','Clear','clearTime()');
  echo "<p align=center>\n";
  //flippyTable($defaultTimeFrames);
  if(!$noNameLinks) {
		$timeframes = fetchTimeframes();
		if(!$timeframes) $timeframes = $defaultTimeFrames;
		//$first = true;
		$links = array();
		foreach($timeframes as $label => $time) {
			$frame = formattedTimeFrame($time);
			if($displayTimeFrames) $label .= " $frame";
			//echo "<a href=# onClick='setTime(\"$frame\")' title='$frame'>$label</a>";
			$links[] = frameLink($label, $frame);
		}
		if($displayTimeFrames) {
			echo "<table><tr><td>";
			echo join($links, '</td></tr><tr><td>');
			echo "</td></tr></table>";
		}
		else echo join($links, ' - ');
		
		echo "<br><div id='frametimemeaning'style='text-align:center'>&nbsp;</div>";
	}
  echo "\n</form></div>";
}

function frameLink($label, $frame) {
	return "<a class='timeFramerLinkLabel' style='cursor:pointer;text-decoration:underline;' onClick='setTime(\"$frame\")' 
	onMouseOver='document.getElementById(\"frametimemeaning\").innerHTML=\"$frame\"'
	onMouseOut='document.getElementById(\"frametimemeaning\").innerHTML=\"&nbsp;\"'>$label</a>";
}
	

function flippyTable($defaultTimeFrames) {
	echo "<table><tr>";
  foreach($defaultTimeFrames as $label => $time) {
		$frame = formattedTimeFrame($time);
	  echo "<td width=95><a href=# onClick='setTime(\"$frame\")' 
		onMouseOver='this.innerHTML=\"<b>$frame</b>\";return true;'
		onMouseOut='this.innerHTML=\"$label\";return true;'>$label</a>";
	}
	echo "</table>";
}
	

function hourSelect($id) {
	$startChanged = ($id == 'hour0') ? 'startHourChanged(this);' : '';
  echo "\n<select class='timeframerlabel' id='$id' onChange='$startChanged tabNext(this)'>\n";
  echo "<option value=-1>--";
  for($i=1;$i<=12;$i++) echo "<option value=$i>$i";
  echo "\n</select>\n";
}

function minuteSelect($id) {
	global $timeStyle;
	$onChange= $id == 'minute0' ? "onChange='autoSetEndTime(event, this)'" : '';
	//echo "<table><tr><td>";
  echo "\n<select class='timeframerlabel' id='$id' $onChange>\n";
  echo "<option value=-1>--";
  if($_SESSION['preferences']['fiveMinuteIntervals']) {
		$max = 55;
		$interval = 5;
	}
	else {
		$max = 45;
		$interval = 15;
	}
  for($i=0;$i<=$max;$i+=$interval) echo "<option value=$i>".($i == 0 ? '00' : sprintf('%02d',$i));
  echo "<option value=59>59";
  echo "\n</select>";
	echo "</td><td>";
  if($timeStyle == '12hr') {
    $suffix = substr($id, -1) ? '1' : '0';  // 0 or 1
		echoAMPMButton($suffix, 'am', 'am');
    echo "<td style='padding-left:3px'>";
		echoAMPMButton($suffix, 'pm', 'am');
    echo "</td>";
		
		//echo "\n";
	}
}

function echoAMPMButton($suffix, $label, $actual) {
	$lcLabel = strtolower($label);
	if($lcLabel == $actual) $class = 'timeframerAMPM_On';
	else $class = 'timeframerAMPM_Off';
	echo "<div id='timeframer.$suffix"."_$lcLabel' class='$class' onClick='pressAMPM(this)'>$label</div>";
}

function dumpTimeFramerJS($id) {
	global $defaultTimeFrameLength, $timeStyle;
	$testmode = $_SERVER['REMOTE_ADDR'] == '68.225.89.173' ? 1 : '0';
	$suppressLongTimeFrameWarning = $_SESSION['preferences']['suppressLongTimeFrameWarning'] ? 'true' : 'false';;
	echo <<<FUNC
//************** JS script for TimeFramer **************
var testMode = $testmode;
var timeFrameId = '$id';
var timeFrameElementId = '';
var nullTimeFrameLabel = '--Select Time Frame--';
var defaultTimeFrameLength = "$defaultTimeFrameLength";
var timeStyle = "$timeStyle";
var sameDayTimeFramesOnly = false;

/*function selectedHour(id) {
	return document.getElementById(id).options[document.getElementById(id).selectedIndex].value;
}
*/

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}
	
function onTimeChange(el) {
	alert('hey');
}

function autoSetEndTime(event, el) {
	var hours = document.getElementById('hour0').value;
	var minutes = document.getElementById('minute0').value;
  if(validTimeParts(hours, minutes) &&
      !(document.getElementById('hour1').value > -1 || document.getElementById('minute1').value > -1)) {
		  if(timeStyle == '12hr' && document.getElementById('timeframer.0_pm').className == 'timeframerAMPM_On')
		    hours = parseInt(hours) + 12;
			// set hour1:minute1 X hours later
			var date = new Date();
			date.setHours(hours);
			date.setMinutes(minutes);
			date.setTime(date.getTime()+(defaultTimeFrameLength*60*1000));
			hours = date.getHours();
			var pm = hours >= 12;
			if(timeStyle == '12hr') {
				document.getElementById('timeframer.1_am').className = !pm ? 'timeframerAMPM_On' : 'timeframerAMPM_Off';
				document.getElementById('timeframer.1_pm').className = pm ? 'timeframerAMPM_On' : 'timeframerAMPM_Off';
				hours = pm ? hours - 12 : hours;
			}
			document.getElementById('hour1').value = hours;
			document.getElementById('minute1').value = minutes;
			document.getElementById('hour1').style.backgroundColor = 'white';
			document.getElementById('minute1').style.backgroundColor = 'white';
	}
}	

function startHourChanged(el) {
	if(el.value && (document.timeframer.originaldate.value == nullTimeFrameLabel)) {
		var hours = new Array(12,1,2,3,4,5);
		for(var i=0;i<hours.length;i++) 
		  if(el.value == hours[i])
		    setAMPM(0,'pm');
		hours = new Array(6,7,8,9,10,11);
		for(var i=0;i<hours.length;i++) 
		  if(el.value == hours[i])
		    setAMPM(0,'am');
	}
}

function keyDownCheck(event, el) {
	processAandP(event, el);
	var x = intOnly(event) || navOnly(event);
	//alert(x);
	return x;
}

function navOnly(event) {
  return(event.keyCode == 8 ||
  (event.keyCode == 35) ||
  (event.keyCode == 36) ||
  (event.keyCode == 37) ||
  (event.keyCode == 39) ||
  (event.keyCode == 9) ||
  (event.keyCode == 16) ||
  (event.keyCode == 17));
}


function intOnly(event) {
  var val = 0;
  if(event.keyCode > 95 && event.keyCode < 106) val = event.keyCode - 96;
  else val = event.keyCode - 48;
  if(val < 0 || val > 9) return false;
  return true;
}

function processAandP(event, el) {
  if(event.keyCode == 80) {// P
    setAMPM(el.id.substr(el.id.length-1), 'pm');
    tabNext(el);
	}
  else if(event.keyCode == 65) {// A
    setAMPM(el.id.substr(el.id.length-1), 'am');
    tabNext(el);
	}
  return false;
}

function setAMPM(startEnd, ampm) {
	var el = document.getElementById('timeframer.'+startEnd+'_am');
	el.className = ampm == 'am' ? 'timeframerAMPM_On' : 'timeframerAMPM_Off';
	el = document.getElementById('timeframer.'+startEnd+'_pm');
	el.className = ampm == 'pm' ? 'timeframerAMPM_On' : 'timeframerAMPM_Off';
}

function pressAMPM(el) {
	if(el.className == 'timeframerAMPM_On') return;
	el.className = 'timeframerAMPM_On';
	var other = el.id.substr(el.id.length-3) == '_am' ? '_pm' : '_am';
	other = document.getElementById(el.id.substr(0, el.id.length-3)+other);
	other.className = 'timeframerAMPM_Off';
}

function showTimeFramerInContentDiv(e, elId) {
	showTimeFramer(e, elId, getAbsolutePosition(document.getElementById('ContentDiv')));
}
	
function showTimeFramer(e, elId, offset, anchorToElement) {
  if(!offset) offset = {x: 0, y: 0};
	timeFrameElementId = elId;
	var el = document.getElementById(elId);
	if(!el) {
		alert("Element with id ["+elId+" not found.");
		return;
	}
	else if(el.type) val = el.value;
	else val = el.innerHTML;
	val = val ? val : '';
  // set vals in timeframer
	updateTimeFramer(val);

	var coords = mouseCoords(e || window.event);
	if(typeof anchorToElement != 'undefined' && anchorToElement) {
		if(anchorToElement = document.getElementById(anchorToElement))
			coords = getAbsolutePosition(anchorToElement);
	}
	coords.x -= offset.x;
	coords.y -= offset.y;
	document.getElementById(timeFrameId).style.left=coords.x-16+'px';
	document.getElementById(timeFrameId).style.top=coords.y-20+'px';
	document.getElementById(timeFrameId).style.visibility = 'visible';
//alert(document.getElementById(timeFrameId).style.visibility+' '+	document.getElementById(timeFrameId).style.left+" "+document.getElementById(timeFrameId).style.top);	
	if(document.getElementById('hour0')) document.getElementById('hour0').focus();
}


function updateTimeFramer(timeframe) {
	var od = document.timeframer ? document.timeframer.originaldate : document.getElementById('originaldate');
	od.value = timeframe;
	if(document.getElementById(timeFrameId+'_simple')) return;
	if(isNaN(parseInt(timeframe))) timeframe = nullTimeFrameLabel;
	var hour0, hour1, minute0, minute1, amclass0, amclass1, pmclass0, pmclass1;
  if(timeframe == nullTimeFrameLabel || timeframe == '') {
		hour0 = '';
		minute0 = '';
		hour1 = '';
		minute1 = '';
		ampm0 = 'am'
		ampm1 = 'am'
	}
	else {
		if(timeframe.indexOf('-') == -1 || timeframe.indexOf(':') == -1) timeframe = '12:00 pm-2:00 pm';
	  var times = timeframe.split("-");
	  var start = timeParts(jstrim(times[0])); // {H, M, ampm} or {H, M}
	  hour0 = start[0];
	  minute0 = start[1];
	  if(start.length == 3) ampm0 = start[2];
	  	
	  var end = timeParts(jstrim(times[1])); // {H, M, ampm} or {H, M}
	  hour1 = end[0];
	  minute1 = end[1];
	  if(end.length == 3) ampm1 = end[2];
	}
	if(timeStyle == '12hr') {
		setAMPM(0, ampm0);
		setAMPM(1, ampm1);
	}
	setSelectedValue('hour0', hour0);
	setSelectedValue('minute0', minute0);
	setSelectedValue('hour1', hour1);
	setSelectedValue('minute1', minute1);

}

function setSelectedValue(id, val) {
	var sel = document.getElementById(id);
	for(var i=0;i<sel.options.length;i++) {
		sel.options[i].selected = parseInt(sel.options[i].value) == parseInt(val);
	}
}

function timeParts(time) {
	var parts = time.split(' ');
	var ampm = parts.length == 2 ? parts[1] : '';
	parts = parts[0].split(':');
	if(ampm) parts[2] = ampm.toLowerCase();
	return parts;
}

	

function hideTimeFramer() {
	document.getElementById(timeFrameId).style.visibility = 'hidden';
}

function clearTime() {
	var el = document.getElementById(timeFrameElementId);
	if(!el) {
		alert("Element with id ["+timeFrameElementId+" not found.");
		return;
	}
	else if(el.type) el.value = '';
	else el.innerHTML = '';
	hideTimeFramer();
}

function setTime(useThisTime) {
	var val = useThisTime;
	if(!val) {
		val = validateTimeFrame();
		if(val.substr(0,5).toLowerCase() == 'error') {
			//alert(val);
			return;
		}
	}
	
	var el = document.getElementById(timeFrameElementId);
	
	if(!document.getElementById(timeFrameElementId+'_simple')) {
		// Check for doubtful timeframes
		var times = val.split("-");
		var start = timeParts(jstrim(times[0])); // {H, M, ampm} or {H, M}
		if(start[2] == 'pm' && start[0] < 12) start[0] = parseInt(start[0]) + 12;
		else if(start[2] == 'am' && start[0] == 12) start[0] = 0;
		var startDate = new Date();
		startDate.setHours(start[0]);
		startDate.setMinutes(start[1]);

		var end = timeParts(jstrim(times[1])); // {H, M, ampm} or {H, M}
		if(end[2] == 'pm' && end[0] < 12) end[0] = parseInt(end[0]) + 12;
		else if(end[2] == 'am' && end[0] == 12) end[0] = 0;
		var endDate = new Date();
		endDate.setHours(end[0]);
		endDate.setMinutes(end[1]);

		var tomorrow = false;
		if(endDate < startDate) {
			tomorrow = true;
			endDate.setTime(endDate.getTime()+(24*60*60*1000))
		}
		if(sameDayTimeFramesOnly && tomorrow) { // sameDayTimeFramesOnly may be set after dumpTimeFramerJS is called
			alert('Both times must be on the same day and \\nthe end cannot be earlier than the start.');
			return;
		}
		var duration = (endDate.getTime() - startDate.getTime()) / (60*60*1000);
	//alert(endDate+" "+startDate+" "+duration);  
		var concerns = new Array();
		// if end is on next day, confirm
		if(tomorrow) concerns[concerns.length] = "ends the day after it starts";
		// if duration > 4, confirm
		if(duration > 4 && ($suppressLongTimeFrameWarning == false)) 
			concerns[concerns.length] = "is longer than 4 hours";
		if(concerns.length > 0
			 && !confirm("Are you sure about this?  This timeframe "+concerns.join(' and ')+'.'))
			return;
	}
	hideTimeFramer();
	  		
	if(!el) {
		alert("Element with id ["+timeFrameElementId+" not found.");
		return;
	}
	else if(el.type) el.value = val;
	else el.innerHTML = val;
	
	//if(testMode) alert(el.id+': ('+val+') '+el.value);
}

function validTimeParts(hour, minute) {
	var hourMin = timeStyle == '24hr' ? 0 : 1;
	var hourMax = timeStyle == '24hr' ? 23 : 12;
	if(!hour || !minute) return false;
	x = parseInt(hour);
	if(isNaN(x) || (x < hourMin) || (x > hourMax)) return false;
	x = parseInt(minute);
	if(isNaN(x) || (x < 0) || (x > 59)) return false;
  return true;
}

function validateTimeFrame() {
	var hourMin = timeStyle == '24hr' ? 0 : 1;
	var hourMax = timeStyle == '24hr' ? 23 : 12;
	var error;
	var x;
	var frame = '';
	if(jstrim(document.getElementById('hour0').value) == '' ||
	   jstrim(document.getElementById('minute0').value) == '' ||
	   jstrim(document.getElementById('hour1').value) == '' ||
	   jstrim(document.getElementById('minute1').value) == '') 
			 return 'Error: Starting and Ending Times must be complete.';
			 
  var fields = new Array('hour0', 'minute0', 'hour1', 'minute1');
  var x;
  for(var i=0;i<fields.length;i++) {
    var x = parseInt(document.getElementById(fields[i]).value);
    if(isNaN(x) || x == -1) {
      error = 'All fields must be filled in.';
		}
		else {
			if(i==0) frame += x;
			else if(i==1) {
		    frame += ':'+(x < 10 ? '0'+x : ''+x);
		    if(timeStyle == '12hr') frame += ' '+(document.getElementById('timeframer.0_am').className == 'timeframerAMPM_On' ? 'am' : 'pm');
	    } 
	    else if(i==2) frame += '-'+x;
	    else  {
				frame += ':'+(x < 10 ? '0'+x : ''+x);
		    if(timeStyle == '12hr') frame += ' '+(document.getElementById('timeframer.1_am').className == 'timeframerAMPM_On' ? 'am' : 'pm');
	    }
		}
	}

	if(error) return 'Error: '+error;
	else return frame;
}
		

function getAbsolutePosition(element) {
    var r = { x: element.offsetLeft, y: element.offsetTop };
    if (element.offsetParent) {
      var tmp = getAbsolutePosition(element.offsetParent);
      r.x += tmp.x;
      r.y += tmp.y;
    }
    return r;
};

function getElementIndex(obj) {
	var theform = obj.form;
	for (var i=0; i<theform.elements.length; i++) {
		if (obj.id == theform.elements[i].id) {
			return i;
			}
		}
	return -1;
	}

function tabNext(obj) {
	var theform = obj.form;
	var i = getElementIndex(obj);
	var j=i+1;
	if (j >= theform.elements.length) { j=0; }
	if (i == -1) { return; }
	while (j != i) {
		if ((theform.elements[j].type!="hidden") && 
		    (theform.elements[j].id != theform.elements[i].id) && 
			(!theform.elements[j].disabled)) {
			theform.elements[j].focus();
			if(theform.elements[j].select) theform.elements[j].select();
			break;
			}
		j++;
		if (j >= theform.elements.length) { j=0; }
		}
}



//************** END JS script forTimeFramer **************

FUNC;
}

?>
