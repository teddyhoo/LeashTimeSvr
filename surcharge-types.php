<?
// surcharge-types.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "surcharge-fns.php";
require_once "holidays-future.php";
locked('o-');

$idsToDelete = array();

if($_GET['reset']) {
	reinitializeNationalHolidays(getI18Property('country'));
	globalRedirect('surcharge-types.php');
	exit;
}

if($_POST) {
  extract($_POST);
  //$deletions = isset($deletions) ? explode(',', $deletions) : null;
  $dbFaults = array();
	saveSurcharges('holiday_');
	saveSurcharges('other_');
	if($idsToDelete) {
		$idsToDelete = join(',', $idsToDelete);
		doQuery("DELETE FROM tblsurchargetype WHERE surchargetypeid IN ($idsToDelete)");
		if(mysql_error()) $dbFaults[] = mysql_error();
	}
	getSurchargeTypesById('refresh');
	
	if($dbFaults) {
		$dbFaults = 'DB Problems:<p>'.join('<p>', $dbFaults);
		echo $dbFaults;
		exit;
	}
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	
	
$remainhere = true;	
	
	if(!$remainhere) {
		header("Location: $mein_host$this_dir/index.php");
		exit;
	}
}
?>
<?


$activeTypes = getPermanentSurchargeTypeIds(); // how best to find "used" surcharge types?  mark the type 'dirty'
																																				 // when a surcharge is created for it, or seach through all surcharges?
																																				 


$pageTitle = 'Surcharge List';
include "frame.html";
?>
<style>
.dollarcell {
  font-size: 1.05em; 
  border-collapse: collapse;
	text-align: right;
	width:50px;
}
.descr {
  font-size: 1.05em; 
  border-collapse: collapse;
	width:220px;
}
.hiddenrow {
	display: none;
}
.visiblerow {
	display: <?= $_SESSION['tableRowDisplayMode'] ?>;
}
.deletedrow {
	background: pink;
}
</style>
<?
// ***************************************************************************
echo "<form name='surchargetypeseditor' method='POST'>\n";
hiddenElement("holiday_deletions", '');
hiddenElement("other_deletions", '');
hiddenElement("remainhere", '');


echo "<p align=right>";
if(mattOnlyTEST()) {
	echoButton('', 'Reset Holidays', 'if(confirm("Reset holidays?")) document.location.href="surcharge-types.php?reset=1"');
	echo "&nbsp;&nbsp;";
}
echoButton('', 'Edit Menu Order', 'openConsoleWindow("surchargeSorderEdit", "surcharges-order-edit.php",400,700)');
echo "&nbsp;&nbsp;";
echoButton('', 'Save Changes', 'saveChanges()');
echo "<p>";

$oldTypeNames = array();;

echo "<h3>Holiday Surcharges</h3>";
getHolidaySurcharges();
$holiday_lastvisible = count($oldTypeNames);
tableFrom($columns, $data, 'width=100% id=holiday_table', null, null, null, null, null, $rowClasses);

echoButton('holiday_addsurchargebutton', 'Add Another Holiday Surcharge', 'addAnotherSurcharge("holiday")');

echo "\n<div id='holiday_aintnomore' class='tiplooks' style='display:none;font-size:1.05em;'>To add more surcharges, please save your changes and return to this page.</div>";

$n = 0;
foreach($types as $key => $surcharge) {
	$n++;
	$prefix = $surcharge['date'] ? 'holiday_' : 'other_';
	hiddenElement($prefix."surchargetypeid_$n", $key);
	hiddenElement($prefix."label_$n", $surcharge['label']);
}

echo "<h3>Other Surcharges</h3>";
$rowClasses = array();
getOtherSurcharges();
$other_lastvisible = count($oldTypeNames)-$holiday_lastvisible;
tableFrom($columns, $data, 'width=100% id=other_table', null, null, null, null, null, $rowClasses);

echoButton('other_addsurchargebutton', 'Add Another Surcharge', 'addAnotherSurcharge(null)');

echo "\n<div id='other_aintnomore' class='tiplooks' style='display:none;font-size:1.05em;'>To add more surcharges, please save your changes and return to this page.</div>";

$n = 0;
foreach($types as $key => $surcharge) {
	$n++;
	$prefix = $surcharge['date'] ? 'holiday_' : 'other_';
	hiddenElement($prefix."surchargetypeid_$n", $key);
	hiddenElement($prefix."label_$n", $surcharge['label']);
}


$oldTypeNames = join('|',$oldTypeNames);
$n = 0;
hiddenElement("holiday_lastvisible", '');//$holiday_lastvisible
hiddenElement("other_lastvisible", '');//$other_lastvisible
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
var oldTypeNames = '<?= safeValue($oldTypeNames) ?>'.split('|');
var holiday_lastvisible = <?= $holiday_lastvisible ?>;
var other_lastvisible = <?= $other_lastvisible ?>;

function editSurchargeLabel(surchargetypeid) {
	$.fn.colorbox({href:"surcharge-label-edit.php?id="+surchargetypeid, iframe: true, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}



function setHoliday(holidayInputName, selectEl) {
	var val;
	if(val = selectEl.options[selectEl.selectedIndex].value)
		document.getElementById(holidayInputName).value = val;
	selectEl.parentNode.style.display = 'none';
}

function toggleHolidayPicker(holidayInputName) {
	var span = document.getElementById(holidayInputName+'_picker');
	span.style.display = span.style.display == 'none' ? 'inline' : 'none';
}

function update() { // called by display-order-edit.php
	if(confirm("The ordering of the Surcharges menu has changed.\nSave changes on this page and redisplay the list?")) {
		document.surchargetypeseditor.remainhere.value = 1;
		saveChanges();
	}
}

function saveChanges() {
	if(validateForm()) {
		document.surchargetypeseditor.holiday_deletions.value=holiday_deletions.join(',');
		document.surchargetypeseditor.other_deletions.value=other_deletions.join(',');
		document.surchargetypeseditor.holiday_lastvisible.value = holiday_lastvisible;
		document.surchargetypeseditor.other_lastvisible.value = other_lastvisible;
		for(var i=1; el = document.getElementById('holiday_date_'+i); i++) {
			if(el.parentNode.parentNode.className == 'hiddenrow') continue;
			var date = el.value;
			if(date != '-1') el.value = validMonthYear(date);
		}
		for(var i=1; el = document.getElementById('other_date_'+i); i++) {
			if(el.parentNode.parentNode.className == 'hiddenrow') continue;
			var date = el.value;
			if(date != '-1') el.value = validMonthYear(date);
		}
		for(var i=1; el = document.getElementById('other_spec1_'+i); i++) {
			if(el.parentNode.parentNode.className == 'hiddenrow') continue;
			var filter = document.getElementById('other_filter_'+i);
			filter = filter ? filter.value : '';
			if(filter != 'before' && filter != 'after') continue;
			var time = el.value;
			el.value = validTime(time);
		}
		document.surchargetypeseditor.submit();
	}
}

function validateForm() {
	var allProblems = new Array();
	// collect each pre-existing Surcharge type that is not marked for deletion
	// collect each visible new Surcharge type that is not marked for deletion
	var el;
	var prefixes = new Array('holiday_', 'other_');
	for(var p=0;p<2;p++) {
		var prefix = prefixes[p];
		var deletions = prefix == 'holiday_' ? holiday_deletions : other_deletions;
		for(var i=1; el = document.getElementById(prefix+'defaultcharge_'+i); i++) {
			var deleted = false;
			if(el.parentNode.parentNode.className == 'hiddenrow') continue;
			for(var n=0;n<deletions.length;n++)
				if(deletions[n] == prefix+'delete_'+i) {
					deleted = true;
					break;
				}
			if(!deleted) {
				var problems = lineProblems(i, prefix);
				if(problems.length > 0) for(var p=0;p<problems.length;p++) allProblems[allProblems.length] = problems[p];
			}
		}
	}
	if(allProblems.length > 0) {
	  allProblems = allProblems.join('\n');
	  alert("Your changes could not be saved because:\n\n"+allProblems);
	  return false;
	}
	return true;
}

function lineProblems(i, prefix) {
	var problems = new Array();
	// if surcharge i is new make sure the name is not already in use
	var el = document.getElementById(prefix+'label_'+i);
//if(!el) alert('missing: '+prefix+'label_'+i);	
	var label = jstrim(document.getElementById(prefix+'label_'+i).value);
	if(!document.getElementById(prefix+'surchargetypeid_'+i)) {
		if(label.length == 0) problems[problems.length] = "Each surcharge must have a label.";
		else for(var n=0;n<oldTypeNames.length;n++)
		  if(oldTypeNames[n] == label.toUpperCase()) {
				problems[0] = "The name ["+label+"] is already in use.";
				break;
			}
  }
  var useLabel = (label.length == 0) ? "Each surcharge" : "["+label+"]";
	// make sure price is a float
	var price = jstrim(el = document.getElementById(prefix+'defaultcharge_'+i).value);
	if(price.length == 0) problems[problems.length] = useLabel+" must have a price.";
	else if(!isUnsignedFloat(price)) problems[problems.length] = useLabel+"'s price must be a number.";
	// if ispercentage make sure rate is a float or percentage and that rate <= 100
	var rate = jstrim(document.getElementById(prefix+'defaultrate_'+i).value);
	if(rate.length == 0) problems[problems.length] = useLabel+" must have a pay rate.";
	if(!isUnsignedFloat(price)) problems[problems.length] = useLabel+"'s rate must be a number.";
	
//el = document.getElementById(prefix+'date_'+i);
//if(!el) alert('missing: '+prefix+'date_'+i);	
	var date = jstrim(document.getElementById(prefix+'date_'+i).value);
	var filter = document.getElementById(prefix+'filter_'+i);
	if(date != -1) {
		// allow m/d, m.d, m-d
		if(!validMonthYear(date)) problems[problems.length] = useLabel+"'s date (M/D) must be a valid date for this year or next year.";
	}
	else if(filter && filter.value == 'before') {
		var time = document.getElementById(prefix+'spec1_'+i).value
		if(!time && !document.getElementById(prefix+'active_'+i).checked) ;
		else {
			time = validTime(time);
//alert('before: '+time);			
			if(!time) problems[problems.length] = useLabel+"'s time must be a valid time of day.";
			else if(time > '12:00') problems[problems.length] = useLabel+"'s time must fall between midnight and noon.";
		}
	}
	else if(filter && filter.value == 'after') {
		var time = document.getElementById(prefix+'spec1_'+i).value
		if(!time && !document.getElementById(prefix+'active_'+i).checked) ;
		else {
			time = validTime(time);
//alert('after: '+time);			
			if(!time) problems[problems.length] = useLabel+"'s time must be a valid time of day.";
			else if(time < '12:00') problems[problems.length] = useLabel+"'s time must fall between noon and midnight.";
		}
	}
	return problems;
}

function validMonthYear(monthyear) {
	monthyear = jstrim(monthyear);
	var format;
	var usregex = /^(0?[1-9]|1?[012])[- /](0?[1-9]|[12][0-9]|3[01])$/;
	var worldregex = /^(0?[1-9]|[12][0-9]|3[01])[.](0?[1-9]|1?[012])$/;
  if(usregex.test(monthyear)) format = 'US';
  else if(worldregex.test(monthyear)) format = 'WORLD';
  else return null; //doesn't match pattern, bad date
	
  var md = monthyear.split('-');
  if(md.length < 2) md = monthyear.split('-');
  if(md.length < 2) md = monthyear.split(' ');
  if(md.length < 2) md = monthyear.split('/');
  if(md.length < 2) md = monthyear.split('.');
  if(format == 'WORLD') {
		var holder = md[0];
		md[0] = md[1];
		md[1] = holder;
	}
  var thisYear = <?= date('Y') ?>;
  if(isValidDate(md[1],md[0],thisYear)) return ''+md[0]+'/'+md[1]+'/'+thisYear;
  if(isValidDate(md[1],md[0],thisYear+1)) return ''+md[0]+'/'+md[1]+'/'+(thisYear+1);
	return null;
}

function isValidDate(Day,Mn,Yr){
	var DateVal = Mn + "/" + Day + "/" + Yr;
	var dt = new Date(DateVal);
	if(dt.getDate()!=Day) return(false);
	else if(dt.getMonth()!=Mn-1) return(false);
	else if(dt.getFullYear()!=Yr) return(false);
	return(true);
}


function validTime(time) {
	time = jstrim(time);
	var regex = /^((([0]?[1-9]|1[0-2])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?( )?(AM|am|aM|Am|PM|pm|pM|Pm))|(([0]?[0-9]|1[0-9]|2[0-3])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?))$/
  if(!regex.test(time))
    return null; //doesn't match pattern, bad date
  var parts = time.split(' ');
  parts = parts[0].split(':');
  if(time.toUpperCase().indexOf('P') > -1) parts[0] = parseInt(parts[0])+12;
  if((''+parts[0]).length == 1) parts[0] = '0'+parts[0];
  var end = parts[1].toUpperCase().indexOf('P');
  if(end > -1) parts[1] = parts[1].substring(0, end);
  end = parts[1].toUpperCase().indexOf('A');
  if(end > -1) parts[1] = parts[1].substring(0, end);
  return ''+parts[0]+':'+parts[1];
}


function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function addAnotherSurcharge(holiday) {
	
	var prefix = holiday ? 'holiday_' : 'other_';
	var root = document.getElementById(prefix+'table');
	var els = root.getElementsByTagName('tr');
	for(i=0;i<els.length;i++)
		if(els[i].className == 'hiddenrow') {
			els[i].className = 'visiblerow';
			if(holiday) holiday_lastvisible += 1;
			else other_lastvisible += 1;
			// if no more hidden elements
			if(i+1 == els.length) {
				document.getElementById(prefix+'addsurchargebutton').style.display='none';
				document.getElementById(prefix+'aintnomore').style.display='inline';
			}
			return;
		}
}

var holiday_deletions = new Array();
var other_deletions = new Array();

function deleteSurcharge(img, holiday) {
	var row = img.parentNode.parentNode;
	var dels = holiday ? holiday_deletions : other_deletions;
	if(row.className != 'deletedrow') {
		row.className = 'deletedrow';
		dels[dels.length] = img.id;
		alert('This surcharge will be deleted when you save changes.\n\nClick here again to retain this surcharge type.');
	}
	else {
		row.className = 'visiblerow';
		for(var i=0;i<dels.length;i++)
		  if(dels[i] == img.id) dels[i] = 0;
	}
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

</script>
<p><img src='art/spacer.gif' height=300>
<?
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
// ***************************************************************************
include "frame-end.html";

function deleteButton($key, $prefix) {
	$holiday = $prefix == 'holiday_' ? 1 : '0';
	return "<img width=20 height=20 id='".$prefix."delete_$key' title='Mark this surcharge type to be deleted when you save changes.' src='art/delete.gif' style='cursor:pointer;' onClick='deleteSurcharge(this, $holiday)'>";
}

function smileyButton() {
	$msg = "This surcharge is permanent or has been used before and can&#39;t be deleted, but it can be marked Inactive.";
	return "<img title='$msg' src='art/smiley.gif' style='cursor:pointer;' onClick='alert(\"$msg\")'>";
}
function getOtherSurcharges() {
	global $rowClasses, $columns, $data, $oldTypeNames, $types, $activeTypes, $numtypes;
	// ===== NON DATE-BASED SURCHARGES =====					
	$columns = $columns = explodePairsLine('delete|Delete||label|Surcharge||spec1| ||spec2| ||defaultcharge|Price||defaultrate|Pay Rate||date| ||filter| '
																					.'||automatic|Automatic||pervisit|Per Visit||active|Active'); // ||descr|Description
	$types = getSurchargeTypes('date IS NULL');

	$n = 0;
	$data = array();
//print_r($types);	
	// first surcharges with filterspecs
	foreach($types as $key => $surcharge) {
		$n++;
		$oldTypeNames[] =  strtoupper($surcharge['label']);
		$data["old_$key"]['delete'] = in_array($key, $activeTypes) ? smileyButton() : deleteButton($n,'other_');
		//$data["old_$key"]['label'] = $surcharge['label'];
		
		if(staffOnlyTEST()) {
			$data["old_$key"]['label'] = fauxLink($surcharge['label'], "editSurchargeLabel({$surcharge['surchargetypeid']})", 1, "Click to change label for surcharge type #{$surcharge['surchargetypeid']} (Staff Only).");;
		}
		else {
			$data["old_$key"]['label'] = $surcharge['label'];
		}

		
		$data["old_$key"]['defaultcharge'] = "<input class='dollarcell' id='other_defaultcharge_$n' name='other_defaultcharge_$n' value='{$surcharge['defaultcharge']}' autocomplete='off'>";
		$data["old_$key"]['defaultrate'] = "<input class='dollarcell' id='other_defaultrate_$n' name='other_defaultrate_$n' value='{$surcharge['defaultrate']}' autocomplete='off'>";

		$checked = $surcharge['active'] ? 'CHECKED' : '';
		$data["old_$key"]['active'] = "<input type='checkbox' id='other_active_$n' name='other_active_$n' $checked ' title='Surcharge type is active'>";

		if($surcharge['filterspec']) {
			$checked = $surcharge['automatic'] ? 'CHECKED' : '';
			$data["old_$key"]['automatic'] = "<input type='checkbox' id='other_automatic_$n' name='other_automatic_$n' $checked ' title='Surcharge type is applied automatically'>";
		}
		$checked = $surcharge['pervisit'] ? 'CHECKED' : '';
		$data["old_$key"]['pervisit'] = "<input type='checkbox' id='other_pervisit_$n' name='other_pervisit_$n' $checked ' title='Surcharge may be applied to multiple visits per day'>";

		$data["old_$key"]['date'] = "<input id='other_date_$n' name='other_date_$n' value='-1' type='hidden'>";
		if($filterSpec = explode('_', $surcharge['filterspec'])) {
			$data["old_$key"]['filter'] = "<input id='other_filter_$n' name='other_filter_$n' value='{$filterSpec[0]}' type='hidden'>";
			if($filterSpec[0] == 'weekend') {
				$checked = strpos($filterSpec[1], 'Sa') !== false ? 'CHECKED' : '';
				$data["old_$key"]['spec1'] = "<input type='checkbox' id='other_spec1_$n' name='other_spec1_$n' $checked ' title='Surcharge will be applied to Saturday visits'> Sat";
				$checked = strpos($filterSpec[1], 'Su') !== false ? 'CHECKED' : '';
				$data["old_$key"]['spec2'] = "<input type='checkbox' id='other_spec2_$n' name='other_spec2_$n' $checked ' title='Surcharge will be applied to Sunday visits'> Sun";
			}
			else if($filterSpec[0] == 'after') {
				$data["old_$key"]['spec1'] = "After:";
				$data["old_$key"]['spec2'] = "<input id='other_spec1_$n' name='other_spec1_$n' value='{$filterSpec[1]}' size=8 title='Surcharge will be applied to visits after'>";
			}
			else if($filterSpec[0] == 'before') {
				$data["old_$key"]['spec1'] = "Before:";
				$data["old_$key"]['spec2'] = "<input id='other_spec1_$n' name='other_spec1_$n' value='{$filterSpec[1]}' size=8 title='Surcharge will be applied to visits before'>";
			}
			else if($filterSpec[0] == 'anytime') {
				$data["old_$key"]['spec1'] = "Any time";
			}
	}
		//$decription = safeValue($surcharge['descr']);
		//$data["old_$key"]['descr'] = "<input maxlength=80 class='descr' id='descr_$n' name='descr_$n' value='$decription' autocomplete='off'>";
	}
	$numtypes = $n + 11;
	for($n++; $n < $numtypes; $n++) {
		$data["new_$n"]['delete'] = deleteButton($n,'other_');

		$data["new_$n"]['label'] = "<input maxlength=45 class='descr' id='other_label_$n' name='other_label_$n' value='' autocomplete='off'>";
		$data["new_$n"]['defaultcharge'] = "<input class='dollarcell' id='other_defaultcharge_$n' name='other_defaultcharge_$n' value='' autocomplete='off'>";
		$data["new_$n"]['defaultrate'] = "<input class='dollarcell' id='other_defaultrate_$n' name='other_defaultrate_$n' value='' autocomplete='off'>";
		$checked = 'CHECKED';
		$data["new_$n"]['active'] = "<input type='checkbox' id='other_active_$n' name='other_active_$n' $checked title='Surcharge type is active'>";
		$data["new_$n"]['automatic'] = "&nbsp;";
		$data["new_$n"]['pervisit'] = "&nbsp;";
		$data["new_$n"]['date'] = "<input id='other_date_$n' name='other_date_$n' value='-1' type='hidden'>";
		//$data["new_$n"]['descr'] = "<input maxlength=80 class='descr' id='descr_$n' name='descr_$n' value='' autocomplete='off'>";
		$rowClasses[$n-1] = 'hiddenrow';
	}
	return $data;
}
function getHolidaySurcharges() {
	global $rowClasses, $columns, $data, $oldTypeNames, $types, $holiday_lastvisible, $activeTypes;
	// ===== DATE-BASED SURCHARGES =====					
	$types = getSurchargeTypes('date IS NOT NULL');

	$columns = $columns = explodePairsLine('delete|Delete||label|Holiday||date|Date||defaultcharge|Price||defaultrate|Pay Rate'
																					.'||automatic|Automatic||pervisit|Per Visit||active|Active'); // ||descr|Description
	$n = 0;
	$data = array();
	foreach($types as $key => $surcharge) {
		$n++;
		$oldTypeNames[] =  strtoupper($surcharge['label']);
		$data["old_$key"]['delete'] = in_array($key, $activeTypes) ? smileyButton() : deleteButton($n,'holiday_');

		if(staffOnlyTEST()) {
			$data["old_$key"]['label'] = fauxLink($surcharge['label'], "editSurchargeLabel({$surcharge['surchargetypeid']})", 1, "Click to change label for surcharge type #{$surcharge['surchargetypeid']} (Staff Only).");;
		}
		else {
			$data["old_$key"]['label'] = $surcharge['label'];
		}


		$data["old_$key"]['defaultcharge'] = "<input class='dollarcell' id='holiday_defaultcharge_$n' name='holiday_defaultcharge_$n' value='{$surcharge['defaultcharge']}' autocomplete='off'>";
		$data["old_$key"]['defaultrate'] = "<input class='dollarcell' id='holiday_defaultrate_$n' name='holiday_defaultrate_$n' value='{$surcharge['defaultrate']}' autocomplete='off'>";

		$checked = $surcharge['active'] ? 'CHECKED' : '';
		$data["old_$key"]['active'] = "<input type='checkbox' id='holiday_active_$n' name='holiday_active_$n' $checked ' title='Surcharge type is active'>";

		$checked = $surcharge['automatic'] ? 'CHECKED' : '';
		$data["old_$key"]['automatic'] = "<input type='checkbox' id='holiday_automatic_$n' name='holiday_automatic_$n' $checked ' title='Surcharge type is applied automatically'>";

		$checked = $surcharge['pervisit'] ? 'CHECKED' : '';
		$data["old_$key"]['pervisit'] = "<input type='checkbox' id='holiday_pervisit_$n' name='holiday_pervisit_$n' $checked ' title='Surcharge may be applied to multiple visits per day'>";
		$calendarDate =  shortNaturalDate(strtotime($surcharge['date']), $noYear=true);
		
		$data["old_$key"]['date'] = 
			isStandardHoliday($surcharge['label'])
				?	"<img src='art/magnifier.gif' onclick=\"toggleHolidayPicker('holiday_date_$n')\" height=15 title='Select the date for this holiday in a given year.'>"
					.yearPicker($surcharge['label'], "holiday_date_$n").' '
				: '';
		$countryDateFormat = strpos(shortDate(time()), '/') ? 'Month/Day' : 'Day.Month';
		$data["old_$key"]['date'] .= "<input size=8 id='holiday_date_$n' value='$calendarDate' name='holiday_date_$n' ' title='$countryDateFormat to apply the surcharge'>";
		if(isStandardHoliday($surcharge['label']) && 00 && mattOnlyTEST()) {
			$chosenYear = $surcharge['date'] ? date('Y', strtotime($surcharge['date'])) : '';
			$data["old_$key"]['date'] .= " <span id='{$holidayEl}_year' class='tiplooks'>$chosenYear</span>";
		}


		//$decription = safeValue($surcharge['descr']);
		//$data["old_$key"]['descr'] = "<input maxlength=80 class='descr' id='descr_$n' name='descr_$n' value='$decription' autocomplete='off'>";
	}
	$numtypes = $n + 11;
	for($n++; $n < $numtypes; $n++) {
		$data["new_$n"]['delete'] = deleteButton($n,'holiday_');

		$data["new_$n"]['label'] = "<input maxlength=45 class='descr' id='holiday_label_$n' name='holiday_label_$n' value='' autocomplete='off'>";
		$data["new_$n"]['defaultcharge'] = "<input class='dollarcell' id='holiday_defaultcharge_$n' name='holiday_defaultcharge_$n' value='' autocomplete='off'>";
		$data["new_$n"]['defaultrate'] = "<input class='dollarcell' id='holiday_defaultrate_$n' name='holiday_defaultrate_$n' value='' autocomplete='off'>";
		$checked = 'CHECKED';
		$data["new_$n"]['active'] = "<input type='checkbox' id='holiday_active_$n' name='holiday_active_$n' $checked title='Surcharge type is active'>";
		$data["new_$n"]['automatic'] = "<input type='checkbox' id='holiday_automatic_$n' name='holiday_automatic_$n' $checked title='Surcharge type is applied automatically'>";
		$data["new_$n"]['pervisit'] = "<input type='checkbox' id='holiday_pervisit_$n' name='holiday_pervisit_$n' $checked title='Surcharge may be applied to multiple visits per day'>";
		$data["new_$n"]['date'] = "<input id='holiday_date_$n' name='holiday_date_$n' ' title='Month/Day to apply the surcharge'>";
		//$data["new_$n"]['descr'] = "<input maxlength=80 class='descr' id='descr_$n' name='descr_$n' value='' autocomplete='off'>";
		$rowClasses[$n-1] = 'hiddenrow';
	}
	return $data;
}

function saveSurcharges($prefix) {
	global $idsToDelete;
	$lastMenuOrder = fetchRow0Col0("SELECT max(menuorder) FROM tblsurchargetype");
	$lastvisible = $_POST[$prefix.'lastvisible'];
//print_r($_POST);
//print_r("$prefix: $lastvisible".'<p>');
	$deletions = explode(',', $_POST[$prefix.'deletions']);
	for($i=1;$i <= $lastvisible; $i++) {
		if($deletions && in_array($prefix."delete_$i", $deletions)) {
			if(isset($_POST[$prefix."surchargetypeid_$i"]))
			  $idsToDelete[] = $_POST[$prefix."surchargetypeid_$i"];
	  }
		else {
			$surcharge = array();
			if(isset($_POST[$prefix."surchargetypeid_$i"])) 
				$surcharge['surchargetypeid'] = $_POST[$prefix."surchargetypeid_$i"];
			else {
				$surcharge['label'] = $_POST[$prefix."label_$i"];
				$lastMenuOrder++;
				$surcharge['menuorder'] = $lastMenuOrder;
			}
				
			$surcharge['defaultcharge'] = $_POST[$prefix."defaultcharge_$i"];
			$rate = $_POST[$prefix."defaultrate_$i"];
			if(strpos($rate,'%')) $rate = substr($rate, strpos($rate,'%'));
			$surcharge['defaultrate'] = $rate;
			$surcharge['automatic'] = isset($_POST[$prefix."automatic_$i"]) && $_POST[$prefix."automatic_$i"] ? 1 : 0;
			$surcharge['pervisit'] = isset($_POST[$prefix."pervisit_$i"]) && $_POST[$prefix."pervisit_$i"] ? 1 : 0;
			
			if($_POST[$prefix."date_$i"] != -1)
				$surcharge['date'] = date('Y-m-d', strtotime($_POST[$prefix."date_$i"]));
			if($filter = $_POST[$prefix."filter_$i"]) {
				if($filter == 'weekend') $filter .= '_'.($_POST[$prefix."spec1_$i"] ? 'Sa' : '').($_POST[$prefix."spec2_$i"] ? 'Su' : '');
//echo "($filter-{$_POST[$prefix."spec1_$i"]})";				
//print_r($_POST);exit;
				if(in_array($filter, array('after', 'before'))) $filter .= '_'.$_POST[$prefix."spec1_$i"];
//echo "[$filter]";				
				$surcharge['filterspec'] = $filter;
			}
			$surcharge['active'] = isset($_POST[$prefix."active_$i"]) && $_POST[$prefix."active_$i"] ? 1 : 0;
			$surcharge['descr'] = $_POST[$prefix."descr_$i"];
			if(!$surcharge['surchargetypeid']) insertTable('tblsurchargetype', $surcharge, 1);
			else updateTable('tblsurchargetype', $surcharge, "surchargetypeid = {$surcharge['surchargetypeid']}", 1);
			if(mysql_error()) $dbFaults[] = mysql_error();
		}
	}
}

?>
