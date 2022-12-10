<?
// provider-time-off2.php
// for population of the provider editor time off tab
require_once "provider-fns.php";
require_once "js-gui-fns.php";
require_once "common/init_session.php";
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if(userRole() == 'p') $locked = locked('p-');
else $locked = locked('o-');

extract($_REQUEST);

if(userRole() == 'p') $id = $_SESSION["providerid"];
$timeOffRows = getProviderTimeOff($id, $showpasttimeoff);
$oldTimeOffRows = array();
$today = date('Y-m-d');
foreach($timeOffRows as $r => $row)
	if(strcmp($row['lastdayoff'], $today) == -1) {
		$oldTimeOffRows[] = $row;
		unset($timeOffRows[$r]);
	}
	
$timeOffRows = array_values($timeOffRows);

$extraEntries = 5;
echo "<table>";
foreach($oldTimeOffRows as $row) {
	echo "<tr><td>&nbsp</td><td>Starting: ".shortDate(strtotime($row['firstdayoff']));
	echo "</td><td>ending: ".shortDate(strtotime($row['lastdayoff']));
	echo "</td></tr>";
}

$numRows = count($timeOffRows) + $extraEntries;

$numVisible = max(1, count($timeOffRows));
for($i=1; $i <= $numRows; $i++) {
	$timeOff = $i <= count($timeOffRows) ? $timeOffRows[$i-1] : null;
	$timeOffId = '';
	if($i <= count($timeOffRows)) {
		$dates = array(shortDate(strtotime($timeOff['firstdayoff'])), shortDate(strtotime($timeOff['lastdayoff'])));
		$timeOffId = $timeOff['timeoffid'];
	}
	else $dates = array('','');
	$rowStyle = ($i > $numVisible) ? "style='display:none;'" : "style=''";
	echo "<tr $rowStyle id='timeoffrow_$i'>";
	echo "<td style='width:22px;padding-right:0px;cursor:pointer;' title='Delete this line.'>".
	      "<img src='art/delete.gif' height=22 width=22 border=0 onClick=\"deleteLine($i)\"></td>\n<td>";
	hiddenElement("timeoff_$i", $timeOffId);
	      
	
	calendarSet('Starting: ', "firstdayoff_$i", $dates[0], null, null, false, 'lastdayoff_$i');
	echo "</td><td>";
	calendarSet('ending: ', "lastdayoff_$i", $dates[1], null, null, false);
	echo "</td><td style='padding-left:10px;'>Hours: </td><td>";
	$tod = $timeOff['timeofday'] ? $timeOff['timeofday'] : '';
	buttonDiv("div_timeofday_$i", "timeofday_$i", "showTimeFramerInContentDiv(event, \"div_timeofday_$i\")",
			$tod, $tod, 
			'background:white;width:100px;margin-left:3px;');
	echo "</td><td> (leave blank for all day)</td></tr>";
	
	
	
	$rowStyle = ($i != $numVisible ) ? "style='display:none;'" : "style=''";
	
	echo "<tr $rowStyle id='addanotherrow_$i'>";
	if($i == $numRows) 
		echo "<td class='tiplooks' colspan=3>To add more time off periods, please save this sitter and return to this page.</td>";
	else {
		echo "<td colspan=3>";
		$next = $i+1;
		echoButton('', 'Add Another', "addAnother($next)");
		echo "</td>";
	}
	echo "</tr>";
}
echo "</table>";


function buttonDiv($divid, $formelementid, $onClick, $label, $value='', $extraStyle=null, $title=null) {
	$title = $title ? "title = '$title'" : '';
	$class = $class ? "class = '$class'" : '';
	echo 
	  "\n<div id='$divid' style='cursor:pointer;border: solid darkgrey 1px;height:15px;padding-left: 2px;overflow:hidden;$extraStyle' 
	onClick='$onClick' $title>$label</div>".
	  hiddenElement($formelementid, $value);
}	

