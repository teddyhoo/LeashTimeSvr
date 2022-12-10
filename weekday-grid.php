<?
/*
* weekday-grid.php
* Used to create a reusable popup div for selecting days of the week as a csv list.
* Usage:
* 1. Create the div with makeWeekdayGrid($id), specifying the DIV's id
* 2. Create one or more form elements or HTML objects with innerHTML whose values are to receive the days list.
* 3. To populate each of these target elements, pass the id (not name) attribute 
*    of the target element into showWeekdayGrid(event, targetId)
* 4. After days are selected and "Done" is pressed, the chosen days will replace the value of the target element.
*
* Script vars:
*   weekdayGridId = the id of the reusable weekday grid
*   daysElementId = set when showWeekdayGrid is called: the id of the target element
*   nullWeekdaysLabel = the string to which the target element's value will be set when no days are selected
*
* CSS Classes:
*   weekdaygrid = looks for the weekday grid div
*   weekdaygridOff =  looks of a day cell when the day is not selected
*   weekdaygridOn =  looks of a day cell when the day is selected
*
*/


function makeWeekdayGrid($id) {
  echo "<div id='$id' class='weekdaygrid' style='visibility:hidden;position:absolute;'><table>";
  echo "<tr><td colspan=3 style='padding-bottom:3px;'><a href=# onClick='setAllDays(true)'>Choose All</a></td>";
  echo " <td colspan=4 style='padding-bottom:3px;text-align:right;'><a href=# onClick='setAllDays(false)'>Clear All</a></td></tr>\n<tr>";
  //setAllDays(on)
  foreach(array('M','Tu','W','Th','F','Sa','Su') as $day) {
    $class = 'weekdaygridOff';
    echo "\n<td id='day_$day' class='$class' onClick='toggleWeekday(this)'>$day</td>";
  }
  echo "</tr>\n<tr>
         <td colspan=3><input type=button value='Done' onClick='saveWeekDays()'></td>
         <td>&nbsp;</td>
         <td colspan=3><input type=button value='Cancel' onClick='hideWeekdayGrid()'></td>
         </tr></table></div>";
}

function dumpWeekDayGridJS($id) {
	echo <<<FUNC

var weekdayGridId = '$id';
var daysElementId = '';
var nullWeekdaysLabel = '';
var everyDayLabel = 'Every Day';
var weekdaysLabel = 'Weekdays';
var weekendsLabel = 'Weekends';

function countWeekDaysSelected(el) {
	if(el == null) return 0;  // TBD - alter to handle non-recurring
	var val;
	if(el.type) val = el.value;
	else val = el.innerHTML;
	if(val == everyDayLabel) return 7;
	else if(val == weekdaysLabel) return 5;
	else if(val == weekendsLabel) return 2;
	else if(val == nullWeekdaysLabel || !val) return 0;
	else return val.split(',').length;
}
	
	
function toggleWeekday(el) {
	var cl = el.className;
	cl = (cl == 'weekdaygridOn') ? 'weekdaygridOff' : 'weekdaygridOn';
	el.className = cl;
}

function setAllDays(on) {
	var cl = !on ? 'weekdaygridOff' : 'weekdaygridOn';
	var days = ['M','Tu','W','Th','F','Sa','Su'];
	for(var i=0;i<7;i++)
	  document.getElementById('day_'+days[i]).className = cl;
}

function showWeekdayGridInContentDiv(e, elId) {
	showWeekdayGrid(e, elId, getAbsolutePosition(document.getElementById('ContentDiv')));
}
	
function showWeekdayGrid(e, elId, offset) {
  if(!offset) offset = {x: 0, y: 0};
	daysElementId = elId;
	var el = document.getElementById(elId);
	if(!el) {
		alert("Element with id ["+elId+" not found.");
		return;
	}
	else if(el.type) val = el.value;
	else val = el.innerHTML;
	val = val ? val : '';
	if(val == weekdaysLabel) val = 'M,Tu,W,Th,F';
	else if(val == weekendsLabel) val = 'Sa,Su';
	else if(val == everyDayLabel) val = 'M,Tu,W,Th,F,Sa,Su';

	//val = document.getElementById(elId).value;
	var days = ['M','Tu','W','Th','F','Sa','Su'];
	for(var i=0;i<7;i++)
	  document.getElementById('day_'+days[i]).className = 
	    (val.indexOf(days[i]) > -1) ? 'weekdaygridOn' : 'weekdaygridOff';
	var coords = mouseCoords(e || window.event);
	coords.x -= offset.x;
	coords.y -= offset.y;
	document.getElementById(weekdayGridId).style.left=coords.x-16+'px';
	document.getElementById(weekdayGridId).style.top=coords.y-20+'px';
	document.getElementById(weekdayGridId).style.visibility = 'visible';
}


	

function hideWeekdayGrid() {
	document.getElementById(weekdayGridId).style.visibility = 'hidden';
}

function saveWeekDays() {
	hideWeekdayGrid();
	var days = ['M','Tu','W','Th','F','Sa','Su'];
	var val = '';
	var dayCount = 0;
	for(var i=0;i<7;i++)
	  if(document.getElementById('day_'+days[i]).className == 'weekdaygridOn') {
	    val += (val ? ',' : '') + days[i];
	    dayCount++;
		}
	    
	if(!val) val = nullWeekdaysLabel;
	else if(dayCount == 7) val = everyDayLabel;
	else if(val == 'M,Tu,W,Th,F') val = weekdaysLabel;
	else if(val == 'Sa,Su') val = weekendsLabel;
	
	
	var el = document.getElementById(daysElementId);

	if(!el) {
		alert("Element with id ["+daysElementId+" not found.");
		return;
	}
	else if(el.type) el.value = val;
	else el.innerHTML = val;
	
	if(window.daysOfWeekUpdated) window.daysOfWeekUpdated(el);
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

FUNC;
}

?>
