<?
// timeframe-fns.php

// timeframes are stored in tblpreference
//format: label|timeframe

require_once "preference-fns.php";
require_once "gui-fns.php";

$defaultTimeFrames = isset($defaultTimeFrames) && $defaultTimeFrames ? $defaultTimeFrames
                       : array('Morning'=>'07:00-09:00','Late Morning'=>'09:00-11:00',
                              'Midday'=>'11:00-13:00', 'Afternoon'=>'13:00-15:00',
                              'Late Afternoon'=>'15:00-17:00', 'Evening'=>'17:00-19:00',
                              'Night'=>'19:00-21:00');

function getTimeframeOrder() {
	$names = array();
	for($i=1;isset($_SESSION['preferences']["timeframe_$i"]); $i++) {
		$field = explode('|', $_SESSION['preferences']["timeframe_$i"]);
		$names[$i] = $field[0];
	}
	return $names;	
}

function reorderTimeframes($order) {
	$timeframes = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE LEFT(property, 10) = 'timeframe_'");
echo join(', ', 	$order).'<p>'.print_r($timeframes, 1).'<p>';

//print_r($timeframes );	
	deleteTable('tblpreference', "LEFT(property, 10) = 'timeframe_'", 1);
	foreach($order as $menuorder => $oldPosition) {
echo "timeframe_".($menuorder+1).': '.$timeframes["timeframe_$oldPosition"].'<br>';	
		setPreference("timeframe_".($menuorder+1), $timeframes["timeframe_$oldPosition"]);
	}
	$_SESSION['preferences'] = fetchPreferences(1);
}

function fetchTimeframes() {
	global $defaultTimeFrames;
	$timeframes = array();
	$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE LEFT(property, 10) = 'timeframe_'"); // ORDER BY property

	$ordered = array();
	foreach($prefs as $key => $field) {
		$key = explode('_', $key);
		$ordered[$key[1]] = $field;
	}
	ksort($ordered);
	foreach($ordered as $key => $field) {
		$field = explode('|', $field);
		$timeframes[$field[0]] = $field[1];
	}
	if(!$timeframes) $timeframes = $defaultTimeFrames;
	return $timeframes;	
}

function getTimeframeMenuChoices($clientList=null) {
	global $defaultTimeFrames;
	$fields = array();
	for($i=1;isset($_SESSION['preferences']["timeframe_$i"]); $i++) {
		if($clientList && $_SESSION['preferences']["hide_timeframe_$i"]) 
			continue;
		$field = explode('|', $_SESSION['preferences']["timeframe_$i"]);
		if($field[1])
			$fields[$field[0]] = $field[1];
	}
	return $fields ? $fields : $defaultTimeFrames;
}

function getTimeframes() {
	$fields = array();
	for($i=1;isset($_SESSION['preferences']["timeframe_$i"]); $i++) {
		$field = explode('|', $_SESSION['preferences']["timeframe_$i"]);
		if($field[1])
			$fields["timeframe_$i"] = $field;
	}
	return $fields;
}

	
function saveTimeframes() {
	deleteTable('tblpreference', "LEFT(property, 10) = 'timeframe_'", 1);
	$services = getTimeframes();
	$n = 0;
	foreach($_POST as $key => $val) {
		if(strpos($key, 'label_') === 0) {
			$i = substr($key, strlen('label_'));
			if(!$_POST["timeframe_$i"] || !$val) continue;
			$timeframe = isset($_POST["timeframe_$i"]) && $_POST["timeframe_$i"] ? $_POST["timeframe_$i"] : 0;
			$pref = stripslashes($val). '|'.$timeframe;
		  $n++;
			setPreference("timeframe_$n", $pref);
			setPreference("hide_timeframe_$n", $_POST["hide_timeframe_$n"]);

		}
	}
	$_SESSION['preferences'] = fetchPreferences();
}

function time24ToTime12($time24) {
	return date('g:i a', strtotime("10/9/2009 $time24"));
}

function range24ToRange12($range24) {
	$times = explode('-', $range24);
	return time24ToTime12($times[0]).'-'.time24ToTime12($times[1]);
}


function timeframeEditor() {
	global $defaultTimeFrames; // set in time-framer-mouse.php 
$CLIENTHIDE = staffOnlyTEST();	
	$fields = getTimeframes();
	if(!$fields) {
		$fields = array();
		$n=1;
		foreach($defaultTimeFrames as $name => $frame) 
			$fields["timeframe_".$n++] = array($name, range24ToRange12($frame));
	}
	echo "<table class='sortableListCell'>";
	$hideFromClientCell = $CLIENTHIDE ? "<th>Hide from Client</th>" : '';
	echo "<tr><th>&nbsp;</th><th>Label</th><th>Time of Day</th>$hideFromClientCell</tr>";
if($_SESSION['staffuser']) { 
		$defaultframe = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'defaultTimeFrame' LIMIT 1");
}		
	for($i=1; $i<=count($fields)+10; $i++) {
		echo "<tr><td>Time Frame #".($i)."</td><td>";
		labeledInput('', "label_$i", $fields["timeframe_$i"][0], null, 'shortLabelInput', '', 14);
		echo "</td><td style='width:120px'>";
		buttonDiv("div_timeofday_$i", "timeframe_$i", "showTimeFramerInContentDiv(event, \"div_timeofday_$i\")",
							$fields["timeframe_$i"][1], $fields["timeframe_$i"][1], 'background:white;');

		echo "</td>";
if($CLIENTHIDE) { 
		$hideKey = "hide_timeframe_$i";
		$hideValue = $_SESSION['preferences'][$hideKey];
		echo "<td style='text-align:center'>".labeledCheckbox('', $hideKey, $hideValue, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=true, $title=null)."</td>";
}		

if($_SESSION['staffuser']) { 
		$defclass = $defaultframe == $fields["timeframe_$i"][1] ? 'defaultboxon' : 'defaultboxoff';
		echo "<td class='$defclass' id='default_$i' onclick='defaultClicked($i)'>default</td>";
}		

		echo "</tr>";
	}
	echo "</table>";
}

