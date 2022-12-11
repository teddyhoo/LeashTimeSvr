<?
/* js-gui-fns.php
* Javascript GUI Functions for:
* - Shrinkable vertical page sections
* - Tab views
* - Wizards
* - form building
*/


/* Shrinkable vertical page section GUI
* Usage:
* 1. Start a containing table for the sections.
* 2. startAShrinkSection($title, $name, $hidden)  - to start a section
* 3. Output section content
* 4. endAShrinkSection()
* ... repeat steps 2 - 4 for each section
* 5. Close containing table
* 6. dumpShrinkToggleJS() - called inside a script block
*
* Globals: 
* - $upImage, $downImage - control indicators for "shrink" and "expand", respectively
* - $upTooltip, $downTooltip - tooltips for $upImage and $downImage
*
* CSS classes:
* - shrinkBanner (applied to a tr)
*/

require_once "gui-fns.php";

$upImage = 'art/up.gif';
$downImage = 'art/down.gif';
$upTooltip = 'Compress (hide) this section.';
$downTooltip = 'Expand this section.';

// NOTE: the contents of this shrinkable div should be a single block, such as a div or a table, to avoid display problems
function startAShrinkSection($title, $name, $hidden=false, $extraStyle='') {
	global $upImage, $downImage, $upTooltip, $downTooltip;
	$src = $hidden ? $downImage : $upImage;
	$initialDisplay = $hidden ? 'none' : 'inline';
	$tooltip = $hidden ? $downTooltip : $upTooltip;
	$hint = "<span id='$name"."_hint'>".($hidden ? '(show) ' : '(hide) ')."</span>";
	//$extraStyle = $extraStyle ? "style='$extraStyle'" : '';
	echo "<tr class='shrinkBanner'>
	  <td onClick=\"toggleShrinkDiv('$name');\">
	  
	  <table width='100%'>
	  <tr><td id='section_title_$name' style='border-width:0px;;text-align:left;$extraStyle'>$title</td><td style='vertical-align:top;border-width:0px;font-size:0.8em;text-align:right;$extraStyle'>$hint<img title='$tooltip' id='$name"."_img' src='$src'></td></table>\n
	  
	  </td>\n</tr>".
	  "\n<tr><td id='$name' style='display:$initialDisplay'>";
//	  <td onClick=\"toggleShrinkDiv('$name');\"><img title='$tooltip' align=right id='$name"."_img' src='$src'>$title\n</td>\n</tr>".

}	  
function endAShrinkSection() {
  echo "\n</td>\n</tr>".
  "\n<tr><td style='font-size:1px;height:2px;padding:0px;'><hr color=white></td></tr>\n"; // add a row for mandatory space at the end of a section
}

function dumpShrinkToggleJS() {
	global $upImage, $downImage, $upTooltip, $downTooltip;
  echo <<<FUNC
// #### SHRINK TAB FNS ########
function toggleShrinkDiv(divname) {
	var img = document.getElementById(divname+"_img");
	var div = document.getElementById(divname);
  if(img.src.indexOf('$upImage') >= 0) {  // shrink
    document.getElementById(divname+'_hint').innerHTML = '(show) ';
    img.src = '$downImage';
    img.title = '$downTooltip';
    div.style.display = 'none';
  }
  else {
    document.getElementById(divname+'_hint').innerHTML = '(hide) ';
    img.src = '$upImage';
    img.title = '$upTooltip';
    div.style.display = 'inline';
	}
}
function hideShrinkDiv(divname) {
	var img = document.getElementById(divname+"_img");
	var div = document.getElementById(divname);
	document.getElementById(divname+'_hint').innerHTML = '(show) ';
	img.src = '$downImage';
	img.title = '$downTooltip';
	div.style.display = 'none';
}
function showShrinkDiv(divname) {
	var img = document.getElementById(divname+"_img");
	var div = document.getElementById(divname);
	document.getElementById(divname+'_hint').innerHTML = '(hide) ';
	img.src = '$upImage';
	img.title = '$upTooltip';
	div.style.display = 'inline';
}
// #### END SHRINK TAB FNS ########


FUNC;
}






/* Tab GUI
* Usage:
* 1. startTabBox($width, $labelAndIds, $selected, $tabwidth) - to start the tab view (a table)
* 2. startTabPage($id, $hidden, $labelAndIds) - to start a tab page
* 3. Output tab page content
* 4. endTabPage()
* ... repeat steps 2 - 4 for each tab
* 5. endTabBox()
* 6. dumpClickTabJS() - called inside a script block
*
* Globals: none
*
* CSS classes:
* - tabbox (applied to a table)
* - tabrow (applied to the tr containing the tabs)
* - tabcellOn (applied to the td of the selected tab)
* - tabcellOff (applied to the td of the unselected tabs)
*/

// $idsAndLabels - assoc array (tab id => tab label)
// $selected - initally selected tab id
function startTabBox($widthOrSpecificClass, $idsAndLabels, $selected, $tabwidth=0) {
	$widthOrSpecificClass = "$widthOrSpecificClass";
	if(is_numeric($widthOrSpecificClass[0])) $width = "width='$widthOrSpecificClass'";
	else $specificClass = $widthOrSpecificClass;
	echo "\n<table $width class='tabbox $specificClass'>";
	makeTabRow($idsAndLabels, $selected, $tabwidth);
}

function endTabBox() {
	echo "\n</table> <!-- tab box -->";
}

function makeTabRow($idsAndLabels, $selected, $tabwidth=0) {
	if($tabwidth && is_array($tabwidth)) // specifies min tabwidths for some cols
		if(isset($tabwidth['##default##'])) $defaultwidth = $tabwidth['##default##'].'px';
		else $defaultwidth = (100 / (count($idsAndLabels) - count($tabwidth)) ).'%';
	else $defaultwidth = $tabwidth ? $tabwidth.'px' : (100 / count($idsAndLabels)).'%';
  echo "\n<tr><td class='tabrow'><table width=100%><tr>"; // cellspacing=0  // this calculation is suspect ==> DIV by zero
  foreach($idsAndLabels as $id => $label) {
		$width = is_array($tabwidth) && isset($tabwidth[$id]) ? $tabwidth[$id].'px' : $defaultwidth;
		$tabclass = $id == $selected ? 'tabcellOn' : 'tabcellOff';
    echo "\n<td style='width:$width;' name='tabcell' id='$id' class='$tabclass' onClick='clickTab(\"$id\");'
           onMouseOver=\"this.style.fontWeight='bold';\"
           onMouseOut=\"this.style.fontWeight=(this.className=='tabcellOn' ? 'bold' : 'normal');\">$label</td>";  // *ugh*
	}
  echo "\n<td>&nbsp;</td></table></td></tr>";
}

// $id - tab id, $labelAndIds - unused at present
function startTabPage($id, $hidden=true, &$idsAndLabels) {
  $initialDisplay = $hidden ? 'none' : 'inline';
  $cols = count($idsAndLabels)+1;
  echo "\n<tr id='tabpage_$id' style='display:$initialDisplay;'>\n<td class='tabpage'>\n";
}

function startFixedHeightTabPage($id, $selectedId, &$idsAndLabels, $height) {
  $initialDisplay = $id != $selectedId ? 'none' : 'inline';
  $cols = count($idsAndLabels)+1;
  echo "\n<tr id='tabpage_$id' style='display:$initialDisplay;'>\n<td class='tabpage' style='height: $height"."px;'>\n";
}

function endTabPage($id, &$labelAndIds, $saveButton=null, $saveAndAddButton=null, $quitButton=null, $showNavButtons=true) {
	if($showNavButtons) 
		echoTabNavButtons($id, $labelAndIds, $saveButton, $saveAndAddButton, $quitButton);
  echo "\n</td>\n</tr>\n";
}

function endTabPageSansNav() {
  echo "\n</td>\n</tr>\n";
}

function echoTabNavButtons($id, &$labelAndIds, $saveButton=null, $saveAndAddButton=null, $quitButton=null) {
	$tabIds = array_keys($labelAndIds);
	$position = array_search($id, $tabIds);
	echo "<center><table><tr>";
	if($position > 0) {
		echo "<td>";
		echoButton('', '<< Back', "clickTab(\"{$tabIds[$position-1]}\")", 'tabNavButton', 'tabNavButtonDown');
		echo "</td><td>";
	}
	if($saveButton) echo "<td>$saveButton</td>";
	if($saveAndAddButton) echo "<td>$saveAndAddButton</td>";
	if($quitButton) echo "<td>$quitButton</td>";
	if($position < count($tabIds)-1) {
		echo "<td>";
		echoButton('', 'Next >>', "clickTab(\"{$tabIds[$position+1]}\")", 'tabNavButton', 'tabNavButtonDown');
		echo "</td><td>";
	}
	echo "</tr></table>";
}


function dumpClickTabJS() {
	$debug = dbTEST('dogslife');
	//$alert = $debug ? "alert(tabid+\" = \"+selectedTab);" : '';
  echo <<<FUNC
var selectedTab;
function clickTab(tabid) {
	selectedTab = tabid;
	var pageid = "tabpage_"+pageid;
  var notByName = false;
  var els = document.getElementsByName('tabcell');
  if(els.length == 0) {
		notByName = true;
		els = document.getElementsByTagName('td');
	}
  for(i=0;i<els.length;i++) {
		if(notByName && (els[i].className.indexOf('abcell') <= 0)) {continue;}
		var page = document.getElementById("tabpage_"+els[i].id);
		if(!page) alert("tabpage_"+els[i].id);
    if(els[i].id == tabid) {
			page.style.display = 'inline';
			els[i].className = 'tabcellOn';
			els[i].style.fontWeight='bold';
		}
    else {
			page.style.display = 'none';
			els[i].className = 'tabcellOff';
			els[i].style.fontWeight='normal';
    }
  }
}
FUNC;
}

/* Wizard GUI
* Simple HTML Wizard Builder with automatic Previous, Next, and Finish capability
* "Finish" is available on final input page only.
* All nav buttons are always enabled.  No conditional enabling/disabling is implemented.
* A post-Finish page is available, but not mandatory.
* Usage:
* 1. Start a containing table for the wizard.
* 2. startWizardPage($pageid, $idsAndLabels)  - to start a section
* 3. Output wizard page content
* 4. endWizardPage($pageid, $idsAndLabels, $finishAction) - to end a wizard page.  $finishAction is the optional onClick code for the Finish button.
* ... repeat steps 2 - 4 for each section
* 5. (optional) startPostFinishPage($pageid, $label) - to start defining the post-Finish page
* 6. (optional) Output wizard post-finish page content
* 7. (optional) endPostFinishPage() - to complete the post-Finish page
* 5. Close containing table
* 6. dumpWizardNavJS() - called inside a script block
*
* CSS classes:
* - wizardtitle (applied to a span)
* - wizardpage (applied to a td)
* 
*/
function startWizardPage($pageid, &$idsAndLabels) {
	$pageNum = 0;
	foreach($idsAndLabels as $id => $label) {
		if($id == $pageid) break;
		$pageNum++;
	}
  $initialDisplay = $idsAndLabels && ($pageNum > 0) ? 'none' : 'inline';
  $label = $idsAndLabels[$pageid] ? "<span class='wizardtitle'>{$idsAndLabels[$pageid]}</span><p>" : "";
  echo "\n<tr name='wizardpage' id='wizard_$id' style='display:$initialDisplay;'>\n<td class='wizardpage'>$label\n";
}

function endWizardPage($pageid, &$idsAndLabels, $finishAction=null) {
	$pageNum = 0;
	$previousId = null;
	$nextId = null;
	$finish = null;
	foreach($idsAndLabels as $id => $label) {
		if($id == $pageid) {
			if($pageNum + 1 == count($idsAndLabels))
				$finish = true;
			else {
				current($idsAndLabels);
				$nextId = each($idsAndLabels);
				$nextId = $nextId[0];
			}
			break;
		};
	  $previousId = $id;
		$pageNum++;
	}
	echo "\n<p align= center>\n";
	$space = '';
	if($previousId) {
		inputButton("Previous", "goToWizardPage(\"$previousId\")", "previous_$pageid");
		$space = "\n";
	}
	if($nextId) {
		echo $space;
		inputButton("Next", "goToWizardPage(\"$nextId\")", "next_$pageid");
		$space = "\n";
	}
	if($finish) {
		echo $space;
		inputButton("Finish", $finishAction, "finish_$pageid");
  }
	echo "\n</tr>\n";
}

function startPostFinishPage($pageid, $label) {
  $label = $label ? "<span class='wizardtitle'>$label</span><p>" : "";
  echo "\n<tr name='wizardpage' id='wizard_$pageid' style='display:none;'>\n<td class='wizardpage'>$label\n";
}

function endPostFinishPage() {
	echo "\n</tr>\n";
}

function dumpWizardNavJS() {
  echo <<<FUNC
function goToWizardPage(wizid) {
	var pageid = "wizard_"+wizid;
  var notByName = false;
  var els = document.getElementsByName('wizardpage');
  if(els.length == 0) {
		notByName = true;
		els = document.getElementsByTagName('tr');
	}
  for(i=0;i<els.length;i++) {
		if(notByName && (els[i].name != 'wizardpage')) {continue;}
		var page = els[i];
    if(page.id == pageid) {
			page.style.display = 'inline';
		}
    else {
			page.style.display = 'none';
    }
  }
}
FUNC;
}

/* PopUp Calendar GUI
* 
*/


function calendarRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $timeAlso=null, $jqueryVersion=false) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? $inputClass : 'standardInput';
	//$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	//$timeAlso = $timeAlso ? ' H:i' : '';
	$timeValue = strtotime($value);
	$value = $timeValue ? ($timeAlso ? shortDateAndTime($timeValue, 'mil') : shortDate($timeValue)) : $value;
	$inputIsTheCalendarWidget = $jqueryVersion ? "  calendarwidget" : ''; //hasDatepicker
	$inputClass = $inputClass ? "class=\"$inputClass$inputIsTheCalendarWidget\"" : "class=\"dateInput$inputIsTheCalendarWidget\"";
	echo "<tr $rowId $rowStyle>
    <td $labelClass><label for='$name'>$label</label>$TIME</td><td><input $inputClass id='$name' name='$name' value='$value' $onBlur autocomplete='off'> ";

  if(!$jqueryVersion) makeCalendarWidget($name, null, $jqueryVersion);
  //makeCalendarWidget($name); 
  echo " ";
  makePrevDayWidget($name);
  makeNextDayWidget($name);
  echo "</td></tr>\n";
}

function calendarSet($label, $name, $value=null, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null, $jqueryVersion=false) {
	$onFocus = $onFocus ? "onFocus='$onFocus'" : "onFocus='this.select();'";
	$secondDayAction = $secondDayName ? "updateSecondDate(\"$secondDayName\", null, document.getElementById(\"$name\"))" : '';   
	$onChange = $onChange && $secondDayAction ? "$onChange;$secondDayAction" :
						($secondDayAction ? $secondDayAction : $onChange);
	$onChange = $onChange ? "onChange='$onChange'" : "";
	$value = $value ? shortDate(strtotime($value)) : '';
	$firstDayName = $firstDayName ? "firstday='$firstDayName'" : '';
	// $jqueryVersion allows us to use Datepicker, and omit the calendar widget
	$inputIsTheCalendarWidget = $jqueryVersion ? " calendarwidget" : '';
	$inputClass = $inputClass ? "class='$inputClass$inputIsTheCalendarWidget'" : "class='dateInput$inputIsTheCalendarWidget'";
	echo "<label for='$name'>$label</label> <input $inputClass id='$name' name='$name' $firstDayName value='$value' $onChange $onFocus autocomplete='off'> ";
  if(!$jqueryVersion) makeCalendarWidget($name, null, $jqueryVersion);
  if($includeArrowWidgets) {
    echo "&nbsp;";
    makePrevDayWidget($name);
    makeNextDayWidget($name, $secondDayName);
	}
}

function makeCalendarWidget($dateInputId, $src=null, $jqueryVersion=false) {
	$src = $src ? $src : 'art/popcalendar.gif';
	$onclick = $jqueryVersion ? '' : "onclick='dateButtonAction(this,document.getElementById(\"$dateInputId\"),\"1\",\"15\",\"2005\")'";
	echo "<img class='calendarwidget' src='$src' dateinputid='$dateInputId' $onclick>";
}	 

function makeNextDayWidget($dateInputId, $secondDayName=null, $src=null) {
	$src = $src ? $src : 'art/next_day.gif';
	if(!$secondDayName) $secondDayName = '';
	echo "<img class='calendarnextdaywidget' src='$src' onclick='nextDay(\"$dateInputId\", \"$secondDayName\")'>";
}	 

function makePrevDayWidget($dateInputId, $src=null) {
	$src = $src ? $src : 'art/prev_day.gif';
	echo "<img class='calendarprevdaywidget' src='$src' onclick='prevDay(\"$dateInputId\")'>";
}	 

function dumpJQueryDatePickerJS() {
	$localDateFormat = getI18Property('popupcalendarformat', $default='mm/dd/yyyy');
	$mattTEST = mattOnlyTest() ? 'true' : 'false';

	echo <<<JQFUNC
// *******************************************
// JQueryDatePickerJS support
// relies on https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css
// and https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js
var localDateFormat = '$localDateFormat';

function initializeCalendarImageWidgets() {//if($mattTEST) return;
	//console.log($('.calendarwidget').attr('id'));
	\$(".calendarwidget").datepicker(
		{
        format: localDateFormat,
        //autoclose: true
     });

}

function dateButtonAction(ctl, date, month, day, year) {
}

function invalidYearInDate(strValue) {
  strValue = ""+strValue;
  var parts;
  parts = strValue.split('/');
  if(parts.length < 3) parts = strValue.split('.');
  if(parts.length < 3) return null;
  if((""+parts[2]).length < 4)
  	return "Please enter the full year.";
  return false;
}

function isValidateUSDate( strValue ) {
/************************************************
DESCRIPTION: Validates that a string contains only
    valid dates with 2 digit month, 2 digit day,
    4 digit year. Date separator can be ., -, or /.
    Uses combination of regular expressions and
    string parsing to validate date.
    Ex. mm/dd/yyyy or mm-dd-yyyy or mm.dd.yyyy

PARAMETERS:
   strValue - String to be tested for validity

RETURNS:
   True if valid, otherwise false.

REMARKS:
   Avoids some of the limitations of the Date.parse()
   method such as the date separator character.
*************************************************/
  var arrayDate = mdy(strValue);
  //check to see if in correct format
  if(!arrayDate)
    return false; //doesn't match pattern, bad date
  else{
    var intDay = arrayDate[1];
    var intYear = arrayDate[2];
    var intMonth = arrayDate[0];

	//check for valid month
	if(intMonth > 12 || intMonth < 1) {
		return false;
	}

    //create a lookup for months not equal to Feb.
    //var arrayLookup = { 1 : 31,3 : 31, 4 : 30,5 : 31,6 : 30,7 : 31, 8 : 31,9 : 30,10 : 31,11 : 30,12 : 31}
    var arrayLookup = new Array(13);
    arrayLookup[0] = 99 ;
    arrayLookup[1] = 31;
    arrayLookup[3] = 31;
    arrayLookup[4] = 30;
    arrayLookup[5] = 31;
    arrayLookup[6] = 30;
    arrayLookup[7] = 31 ;
    arrayLookup[8] = 31;
    arrayLookup[9] = 30;
    arrayLookup[10] = 31;
    arrayLookup[11] = 30;
    arrayLookup[12] = 31;

    //check if month value and day value agree
    if(arrayLookup[arrayDate[0]] != null) {
      if(intDay <= arrayLookup[arrayDate[0]] && intDay != 0)
        return true; //found in lookup table, good date
    }

    //check for February
	var booLeapYear = (intYear % 4 == 0 && (intYear % 100 != 0 || intYear % 400 == 0));
    if( ((booLeapYear && intDay <= 29) || (!booLeapYear && intDay <=28)) && intDay !=0)
      return true; //Feb. had valid number of days
  }
  return false; //any other values, bad date
}

function nextDay(elId, secondElId) {
	var dateEl = document.getElementById(elId);
	var val = dateFromElement(dateEl);
	var goNext = true;
	if(!val) {
		var firstday = dateEl.getAttribute('firstday');
		if(firstday && document.getElementById(firstday)) {
			val = dateFromElement(document.getElementById(firstday));
			if(val) goNext = false;
		}
	}
	var usformat = !dateEl.value ? false : dateEl.value.indexOf('/') > -1;
	val.setTime(val.getTime()+(1000 * 60 * 60 * 24));
	dateEl.value = 
		usformat ? (val.getUTCMonth()+1)+'/'+val.getUTCDate()+'/'+val.getUTCFullYear()
		: val.getUTCDate()+'.'+(val.getUTCMonth()+1)+'.'+val.getUTCFullYear();
	if(secondElId) updateSecondDate(secondElId, val, dateEl);
	if(dateEl.attributes['onchange']) eval(dateEl.attributes['onchange'].value);
	//if(dateEl['onchange']) eval(dateEl['onchange']);
	//if(dateEl.attributes['onchange']) eval(dateEl.attributes['onchange'].value);
	//if(dateEl.attributes['onchange']) dateEl.dispatchEvent(new Event('change'));
	dateEl.dispatchEvent(new Event('change'));
}

function prevDay(elId) {
	var dateEl = document.getElementById(elId);
	var val = dateEl.value;
	if(!val) return;
	var usformat = val.indexOf('/') != -1;
	val = dateFromElement(dateEl);
	val.setHours(6); // avoids problem at start of DST
	val.setTime(val.getTime()-(1000 * 60 * 60 * 24));
	dateEl.value = 
		usformat ? (val.getUTCMonth()+1)+'/'+val.getUTCDate()+'/'+val.getUTCFullYear()
		: val.getUTCDate()+'.'+(val.getUTCMonth()+1)+'.'+val.getUTCFullYear();
	//if(dateEl['onchange']) eval(dateEl['onchange']);
	//if(dateEl.attributes['onchange']) eval(dateEl.attributes['onchange'].value);
	//if(dateEl.attributes['onchange']) dateEl.dispatchEvent(new Event('change'));
	dateEl.dispatchEvent(new Event('change'));
}

function dateFromElement(el) {
	dateval = el.value;
	if(!dateval) return;
	if(!isValidateUSDate(dateval)) {
		var wrongYear = invalidYearInDate(dateval) ? ". Please enter the full year." : "";
		alert("Invalid date ["+dateval+"]"+wrongYear);
		el.value = '';
		return;
	}
	dateval = mdy(dateval);
	//dateval = new Date(dateval);
	dateval = new Date(dateval[2], dateval[0]-1, dateval[1]);
	return dateval;
}

function utcDateFromElement(el) {
	dateval = el.value;
	if(!dateval) return;
	if(!isValidateUSDate(dateval)) {
		var wrongYear = invalidYearInDate(dateval) ? ". Please enter the full year." : "";
		alert("Invalid date ["+dateval+"]"+wrongYear);
		el.value = '';
		return;
	}
	dateval = mdy(dateval);
	//dateval = new Date(dateval);
	dateval = new Date(dateval[2], dateval[0]-1, dateval[1]);
	dateval.setUTCMonth(dateval.getMonth());
	dateval.setUTCMDate(dateval.getDate());
	dateval.setUTCFullYear(dateval.getFullYear());
	return dateval;
}

function updateSecondDate(secondElId, dateval, firstEl) {
	if(!dateval) dateval = dateFromElement(firstEl);
	//var val2 = document.getElementById(secondElId).value;
	//if(!val2) return;
	//val2 = getDateForString(val2, "mm/dd/yyyy");
	//if(!val2) return;
	//if(dateval > val2) document.getElementById(secondElId).value = firstEl.value;
	var val2 = document.getElementById(secondElId);
	if(!val2) return;
	val2 = val2.value;
	if(val2) {
		val2 = getDateForString(val2, "$localDateFormat");
		if(!val2) return;
	}
	if(!val2 || dateval > val2) document.getElementById(secondElId).value = firstEl.value;
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

	function getDateForString(datestr, format) {
				dateFormat = format;
				formatChar = ' ';
				aFormat = dateFormat.split(formatChar);
				if (aFormat.length < 3) {
					formatChar = '/';
					aFormat = dateFormat.split(formatChar);
					if (aFormat.length < 3) {
						formatChar = '.';
						aFormat = dateFormat.split(formatChar);
						if (aFormat.length < 3) {
							formatChar = '-';
							aFormat = dateFormat.split(formatChar);
							if (aFormat.length < 3) {
								formatChar = '';					// invalid date format

							}
						}
					}
				}

				tokensChanged = 0;
				if (formatChar != "") {
					aData =	datestr.split(formatChar);			// use user's date

					for (i=0; i<3; i++) {
						if ((aFormat[i] == "d") || (aFormat[i] == "dd")) {
							dateSelected = parseInt(aData[i], 10);
							tokensChanged++;
						} else if ((aFormat[i] == "m") || (aFormat[i] == "mm")) {
							monthSelected = parseInt(aData[i], 10) - 1;
							tokensChanged++;
						} else if (aFormat[i] == "yyyy") {
							yearSelected = parseInt(aData[i], 10);
							tokensChanged++;
						} else if (aFormat[i] == "mmm") {
							for (j=0; j<12; j++) {
								if (aData[i] == monthName[language][j]) {
									monthSelected=j;
									tokensChanged++;
								}
							}
						} else if (aFormat[i] == "mmmm") {
							for (j=0; j<12; j++) {
								if (aData[i] == monthName2[language][j]) {
									monthSelected = j;
									tokensChanged++;
								}
							}
						}
					}
				}

				if ((tokensChanged != 3) || isNaN(dateSelected) || isNaN(monthSelected) || isNaN(yearSelected)) {
				  return null;
				}
				else {
 					var dd = new Date();
					dd.setFullYear(yearSelected);
					dd.setMonth(monthSelected,dateSelected);
					//dd.setDate(dateSelected);
					return dd;
				}
 }


JQFUNC;
}

function dumpPopCalendarJS() {
	$localDateFormat = getI18Property('popupcalendarformat', $default='mm/dd/yyyy');

echo "// did you remember to include popcalendar.js?\n\nvar TESTMODE = '".($_SERVER['REMOTE_ADDR'] == '68.225.89.173' ? 1 : 0)."';";
	echo <<<FUNC
// *******************************************
// PopCalendar support
var localDateFormat = '$localDateFormat';

function dateButtonAction(ctl, date, month, day, year) {
  var datePosition = getAbsolutePosition(document.getElementById(date.id));
  //var contentPosition = getAbsolutePosition(document.getElementById('ContentDiv'));
  var offset = addOffsets(getTabPageOffset(date), getContainerOffset(date, 'Sheet'), getContainerOffset(date, 'contentLayout'));
  //showCalendar(ctl, date, "mm/dd/yyyy","en",1,datePosition.x-contentPosition.x, datePosition.y-contentPosition.y);
  showCalendar(ctl, date, "$localDateFormat","en",1,datePosition.x-offset.x, datePosition.y-offset.y);
}

function addOffsets() {
	var r = {x: 0, y: 0};
	for(var i=0; i < addOffsets.arguments.length; i++) {
		r.x += addOffsets.arguments[i].x;
		r.y += addOffsets.arguments[i].y;
	}
	return r;
}

function dateButtonAction2(ctl, date, month, day, year) {
  var contentPosition = getAbsolutePosition(document.getElementById('ContentDiv'));
  showCalendar(ctl, date, "$localDateFormat","en",1,contentPosition.x, contentPosition.y);
}

function getAbsolutePosition(element) {
    var r = { x: element.offsetLeft, y: element.offsetTop };
    if (element.offsetParent) {
      var tmp = getAbsolutePosition(element.offsetParent);
      r.x += tmp.x;
      r.y += tmp.y;
    }
    return r;
}

function getTabPageOffset(element) {
	// since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
	while(element.offsetParent) {
		var parent = element.offsetParent;
    if(parent.id && parent.id.indexOf('tabpage_') == 0) return {x: parent.offsetLeft, y: parent.offsetTop-23}; // 23 for tab height
    element = parent;
	}
	return {x: 0, y: 0};
}

function getContainerOffset(element, containerClassName) {
	// since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
	while(element.offsetParent) {
		var parent = element.offsetParent;
    if(parent.className == containerClassName) return {x: parent.offsetLeft, y: parent.offsetTop};
    element = parent;
	}
	return {x: 0, y: 0};
}

function invalidYearInDate(strValue) {
  strValue = ""+strValue;
  var parts;
  parts = strValue.split('/');
  if(parts.length < 3) parts = strValue.split('.');
  if(parts.length < 3) return null;
  if((""+parts[2]).length < 4)
  	return "Please enter the full year.";
  return false;
}

function isValidateUSDate( strValue ) {
/************************************************
DESCRIPTION: Validates that a string contains only
    valid dates with 2 digit month, 2 digit day,
    4 digit year. Date separator can be ., -, or /.
    Uses combination of regular expressions and
    string parsing to validate date.
    Ex. mm/dd/yyyy or mm-dd-yyyy or mm.dd.yyyy

PARAMETERS:
   strValue - String to be tested for validity

RETURNS:
   True if valid, otherwise false.

REMARKS:
   Avoids some of the limitations of the Date.parse()
   method such as the date separator character.
*************************************************/
  var arrayDate = mdy(strValue);
  //check to see if in correct format
  if(!arrayDate)
    return false; //doesn't match pattern, bad date
  else{
    var intDay = arrayDate[1];
    var intYear = arrayDate[2];
    var intMonth = arrayDate[0];

	//check for valid month
	if(intMonth > 12 || intMonth < 1) {
		return false;
	}

    //create a lookup for months not equal to Feb.
    //var arrayLookup = { 1 : 31,3 : 31, 4 : 30,5 : 31,6 : 30,7 : 31, 8 : 31,9 : 30,10 : 31,11 : 30,12 : 31}
    var arrayLookup = new Array(13);
    arrayLookup[0] = 99 ;
    arrayLookup[1] = 31;
    arrayLookup[3] = 31;
    arrayLookup[4] = 30;
    arrayLookup[5] = 31;
    arrayLookup[6] = 30;
    arrayLookup[7] = 31 ;
    arrayLookup[8] = 31;
    arrayLookup[9] = 30;
    arrayLookup[10] = 31;
    arrayLookup[11] = 30;
    arrayLookup[12] = 31;

    //check if month value and day value agree
    if(arrayLookup[arrayDate[0]] != null) {
      if(intDay <= arrayLookup[arrayDate[0]] && intDay != 0)
        return true; //found in lookup table, good date
    }

    //check for February
	var booLeapYear = (intYear % 4 == 0 && (intYear % 100 != 0 || intYear % 400 == 0));
    if( ((booLeapYear && intDay <= 29) || (!booLeapYear && intDay <=28)) && intDay !=0)
      return true; //Feb. had valid number of days
  }
  return false; //any other values, bad date
}

function nextDay(elId, secondElId) {
	var dateEl = document.getElementById(elId);
	var val = dateFromElement(dateEl);
	var goNext = true;
	if(!val) {
		var firstday = dateEl.getAttribute('firstday');
		if(firstday && document.getElementById(firstday)) {
			val = dateFromElement(document.getElementById(firstday));
			if(val) goNext = false;
		}
	}
	var usformat = !dateEl.value ? false : dateEl.value.indexOf('/') > -1;
	val.setTime(val.getTime()+(1000 * 60 * 60 * 24));
	dateEl.value = 
		usformat ? (val.getUTCMonth()+1)+'/'+val.getUTCDate()+'/'+val.getUTCFullYear()
		: val.getUTCDate()+'.'+(val.getUTCMonth()+1)+'.'+val.getUTCFullYear();
	if(secondElId) updateSecondDate(secondElId, val, dateEl);
	if(dateEl.attributes['onchange']) eval(dateEl.attributes['onchange'].value);
	//if(dateEl['onchange']) eval(dateEl['onchange']);
	//if(dateEl.attributes['onchange']) eval(dateEl.attributes['onchange'].value);
	//if(dateEl.attributes['onchange']) dateEl.dispatchEvent(new Event('change'));
	dateEl.dispatchEvent(new Event('change'));
}

function prevDay(elId) {
	var dateEl = document.getElementById(elId);
	var val = dateEl.value;
	if(!val) return;
	var usformat = val.indexOf('/') != -1;
	val = dateFromElement(dateEl);
	val.setHours(6); // avoids problem at start of DST
	val.setTime(val.getTime()-(1000 * 60 * 60 * 24));
	dateEl.value = 
		usformat ? (val.getUTCMonth()+1)+'/'+val.getUTCDate()+'/'+val.getUTCFullYear()
		: val.getUTCDate()+'.'+(val.getUTCMonth()+1)+'.'+val.getUTCFullYear();
	//if(dateEl['onchange']) eval(dateEl['onchange']);
	//if(dateEl.attributes['onchange']) eval(dateEl.attributes['onchange'].value);
	//if(dateEl.attributes['onchange']) dateEl.dispatchEvent(new Event('change'));
	dateEl.dispatchEvent(new Event('change'));
}

function dateFromElement(el) {
	dateval = el.value;
	if(!dateval) return;
	if(!isValidateUSDate(dateval)) {
		var wrongYear = invalidYearInDate(dateval) ? ". Please enter the full year." : "";
		alert("Invalid date ["+dateval+"]"+wrongYear);
		el.value = '';
		return;
	}
	dateval = mdy(dateval);
	//dateval = new Date(dateval);
	dateval = new Date(dateval[2], dateval[0]-1, dateval[1]);
	return dateval;
}

function utcDateFromElement(el) {
	dateval = el.value;
	if(!dateval) return;
	if(!isValidateUSDate(dateval)) {
		var wrongYear = invalidYearInDate(dateval) ? ". Please enter the full year." : "";
		alert("Invalid date ["+dateval+"]"+wrongYear);
		el.value = '';
		return;
	}
	dateval = mdy(dateval);
	//dateval = new Date(dateval);
	dateval = new Date(dateval[2], dateval[0]-1, dateval[1]);
	dateval.setUTCMonth(dateval.getMonth());
	dateval.setUTCMDate(dateval.getDate());
	dateval.setUTCFullYear(dateval.getFullYear());
	return dateval;
}

function updateSecondDate(secondElId, dateval, firstEl) {
	if(!dateval) dateval = dateFromElement(firstEl);
	//var val2 = document.getElementById(secondElId).value;
	//if(!val2) return;
	//val2 = getDateForString(val2, "mm/dd/yyyy");
	//if(!val2) return;
	//if(dateval > val2) document.getElementById(secondElId).value = firstEl.value;
	var val2 = document.getElementById(secondElId);
	if(!val2) return;
	val2 = val2.value;
	if(val2) {
		val2 = getDateForString(val2, "$localDateFormat");
		if(!val2) return;
	}
	if(!val2 || dateval > val2) document.getElementById(secondElId).value = firstEl.value;
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




FUNC;
}


function pagingBox($content, $name='pagingBox') {
	echo "<table class='pagingBox'>\n";
	echo "<tr><td class='pagingBoxUp' onClick='pb_pageUp(\"$name\")'><img src='art/sort_up.gif' width=20 height=15></td></tr>\n";
	echo "<tr><td id='pagingBox'>$content</td></tr>\n";
	echo "<tr><td class='pagingBoxDown' onClick='pb_pageDown(\"$name\")'><img src='art/sort_down.gif' width=20 height=15></td></tr>\n";
	echo "</table\n";
}

function dumpPagingBoxJS($includescripttags) {
	if($includescripttags) echo "<script language='javascript'>";
	echo <<<PBJS
function pb_pageUp(id) {
	var td = $("#"+id).children();
	td.scrollTop(Math.max(0, td.scrollTop()-td.height()));
}

function pb_pageDown(id) {
	var td = $("#"+id).children();
	td.scrollTop(Math.min(td[0].scrollHeight, td.scrollTop()+td.height()));
}

PBJS;
	if($includescripttags) echo "</script>";
}

function dumpPagingBoxStyle() {
	echo <<<PBSTYLE
<style>
.pagingBox {border:solid black 0px;}
.pagingBoxUp {border:solid black 1px;background:lightblue;text-align:center;}
.pagingBoxDown {border:solid black 1px;background:lightblue;text-align:center;}
</style>
PBSTYLE;
}

?>
