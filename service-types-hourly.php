<?
// service-types-hourly.php  -- NOT STANDARD
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "service-fns.php";
require_once "preference-fns.php";

locked('o-');

$idsToDelete = array();
if($_POST) {
  extract($_POST);
  $deletions = isset($deletions) ? explode(',', $deletions) : null;
  $dbFaults = array();
  for($i=1; $i <= $lastvisible; $i++) {
		if($deletions && in_array("delete_$i", $deletions)) {
			if(isset($_POST["servicetypeid_$i"]))
			  $idsToDelete[] = $_POST["servicetypeid_$i"];
	  }
		else {
			$service = array();
			if(isset($_POST["servicetypeid_$i"])) {
				$service['servicetypeid'] = $_POST["servicetypeid_$i"];
				if(staffOnlyTEST()) $oldType = $oldServiceTypes[$service['servicetypeid']];
			}
			else {
				$service['label'] = $_POST["label_$i"];
				$oldType = null;
			}
			$service['defaultcharge'] = $_POST["defaultcharge_$i"];
			$service['defaultrate'] = 0;
			$service['taxable'] = isset($_POST["taxable_$i"]) && $_POST["taxable_$i"] ? 1 : 0;
			$service['active'] = isset($_POST["active_$i"]) && $_POST["active_$i"] ? 1 : 0;
			if(!$service['active'] && $oldType && $oldType['active']) $deactivatedServices[] = $service['servicetypeid'];
			$service['descr'] = $_POST["descr_$i"];
			$service['hours'] = $_POST["hours_$i"];
			$service['hoursexclusive'] = $_POST["exclusive_$i"] ? 1 : 0;
			
			if($serviceTypeId = $service['servicetypeid']) {
				$serv2 = fetchFirstAssoc("SELECT * FROM tblservicetype WHERE servicetypeid = {$service['servicetypeid']}", 1);
				if($mods = changedFields($service, $serv2))
					logChange($service['servicetypeid'], 'tblservicetype', 'm', "Changes to {$serv2['label']}: $mods");				
				updateTable('tblservicetype', $service, "servicetypeid = $serviceTypeId", 1);
			}
			else {
				$newServiceTypeID = insertTable('tblservicetype', $service, 1);
				logChange($newServiceTypeID, 'tblservicetype', 'm', "New Service type added: [{$service['label']}]");
			}
			
			if(mysql_error()) $dbFaults[] = mysql_error();
			$serviceHours[$serviceTypeId] = $service['hours'];
		}
	}
	if($idsToDelete) {
		foreach($idsToDelete as $id) {
			doQuery("DELETE FROM tblpreference WHERE property LIKE 'client_service_%' AND value LIKE '%|$id|%'");
			if(mysql_error()) $dbFaults[] = mysql_error();

		}
		$_SESSION['preferences'] = fetchPreferences();
		foreach($idsToDelete as $doomedID) {
			$doomed = fetchFirstAssoc("SELECT * FROM tblservicetype WHERE servicetypeid = $doomedID", 1);
			logChange($service['servicetypeid'], 'tblservicetype', 'd', "Service [{$doomed['label']}] deleted.");
		}
		$idsToDelete = join(',', $idsToDelete);
		doQuery("DELETE FROM tblservicetype WHERE servicetypeid IN ($idsToDelete)");
		if(mysql_error()) $dbFaults[] = mysql_error();
	}
	getServiceNamesById('refresh');

	foreach(fetchCol0("SELECT providerid FROM tblprovider") as $prov) {
		$rate = getProviderPreference($prov, 'hourlyRate');
		$travel = getProviderPreference($prov, 'travelAllowance');
		foreach($serviceHours as $servicetypeid => $hours) {
			$hourFrac = (strtotime("1/1/1970 $hours") - strtotime("1/1/1970")) / 3600;
			replaceTable('relproviderrate',
				array('providerptr'=>$prov,
							'servicetypeptr'=>$servicetypeid,
							'rate'=>((int)($rate * 100 * $hourFrac)) / 100 + $travel,
							'ispercentage'=>0,
							'note'=>'hourly'), 1);
		}
	}
	
	// refresh sitter rates
	
	if($dbFaults) {
		$dbFaults = 'DB Problems:<p>'.join('<p>', $dbFaults);
		echo $dbFaults;
		exit;
	}
	
	$remainhere = true;
	$_SESSION['frame_message'] = "Your changes have been saved.";
	
	if($deactivatedServices) { // find out if any of the newly deactivated services are exposed to clients
		require_once "client-services-fns.php";
		foreach((array)getClientServiceFields() as $field) 
			if(in_array($field[1], $deactivatedServices))
				$goto = 'client-services-editor.php';
		if($goto) {
			globalRedirect($goto);
			exit;
		}
	}
	
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	if(!$remainhere) {
		header("Location: $mein_host$this_dir/index.php");
		exit;
	}
}
?>
<?

function changedFields($serv1, $serv2) {
	foreach(array_keys($serv1) as $k)
		if($serv1[$k] != $serv2[$k])
			$changes[] = $k;
	return join(', ', (array)$changes);
}

function deleteButton($key) {
	return "<img width=20 height=20 id='delete_$key' title='Mark this service to be deleted when you save changes.' src='art/delete.gif' style='cursor:pointer;' onClick='deleteService(this)'>";
}

function smileyButton() {
	$msg = 'This service has been used before and can&#39;t be deleted, but it can be marked Inactive.';
	return "<img title='$msg' src='art/smiley.gif' style='cursor:pointer;' onClick='alert(\"$msg\")'>";
}

function hoursCell($i, $val, $exclusive) {
	$s = "<span id='hoursdisplay_$i' onclick='editHours(\"$i\")' style='text-decoration:underline;'>$val</span>";
	$s .= "<span id='hourseditor_$i' style='display:none;'>";
	$s .= labeledInput('', "hours_$i", $val, null,'hourinput',"updateHours(\"$i\")", null, true);
	$s .= labeledCheckbox('Exclusive:', "exclusive_$i", $exclusive, null, null, null, null, true);
	$s .=  " <img title='Hide again' src='art/up-black.gif' width=12 height=20 onclick='hideHoursEditor(\"$i\")'>";
	$s .=  "</span>";
	return $s;
}


$types = getStandardRates();
$activeTypes = fetchCol0("SELECT DISTINCT servicecode FROM tblappointment");
$currencyMark = getCurrencyMark();

$columns = $columns = explodePairsLine("delete|Delete||label|Service||taxable|Taxable||defaultcharge|Price||active|Active||hours|Hours||descr|Description");
$oldTypeNames = array();;
$n = 0;
$data = array();
foreach($types as $key => $service) {
	$n++;
	$oldTypeNames[] =  strtoupper($service['label']);
	$data["old_$key"]['delete'] = in_array($key, $activeTypes) ? smileyButton() : deleteButton($n);
	if(staffOnlyTEST() /*|| dbTEST('walkingthedogsfairfax') */) {
		$data["old_$key"]['label'] = fauxLink($service['label'], "editServiceLabel({$service['servicetypeid']})", 1, "Click to change label for servce type #{$service['servicetypeid']} (Staff Only).");;
	}
	else {
		$data["old_$key"]['label'] = $service['label'];
	}
	$data["old_$key"]['defaultcharge'] = "<input class='dollarcell' id='defaultcharge_$n' name='defaultcharge_$n' value='{$service['defaultcharge']}' autocomplete='off'>";
	$checked = $service['taxable'] ? 'CHECKED' : '';
	$data["old_$key"]['taxable'] = "<input type='checkbox' id='taxable_$n' name='taxable_$n' $checked title='Service is taxable'>";
	$checked = $service['active'] ? 'CHECKED' : '';
	$data["old_$key"]['active'] = "<input type='checkbox' id='active_$n' name='active_$n' $checked ' title='Service is active'>";
	$data["old_$key"]['hours'] = hoursCell($n, ($service['hours'] ? $service['hours'] : '00:00'), $service['hoursexclusive']);
	$decription = safeValue($service['descr']);
	$data["old_$key"]['descr'] = "<input maxlength=80 class='descr' id='descr_$n' name='descr_$n' value='$decription' autocomplete='off'>";
	$rowClasses[$n-1] = $service['active'] ? '' : 'inactivetype';
}
$numtypes = $n + 11;
for($n++; $n < $numtypes; $n++) {
	$data["new_$n"]['delete'] = deleteButton($n);
	
	$data["new_$n"]['label'] = "<input maxlength=45 class='descr' id='label_$n' name='label_$n' value='' autocomplete='off'>";
	$data["new_$n"]['defaultcharge'] = "<input class='dollarcell' id='defaultcharge_$n' name='defaultcharge_$n' value='' autocomplete='off'>";
	$data["new_$n"]['extrapetcharge'] = "<input class='dollarcell' id='extrapetcharge_$n' name='extrapetcharge_$n' value='' autocomplete='off'>";
	$checked = $_SESSION['preferences']['newServiceTaxableDefault'] ? 'CHECKED' : '';
	$data["new_$n"]['taxable'] = "<input type='checkbox' id='taxable_$n' name='taxable_$n' $checked title='Service is taxable'>";
	$checked = 'CHECKED';
	$data["new_$n"]['active'] = "<input type='checkbox' id='active_$n' name='active_$n' $checked title='Service is active'>";
	$data["new_$n"]['hours'] = hoursCell($n, '00:00', false);
	$data["new_$n"]['descr'] = "<input maxlength=80 class='descr' id='descr_$n' name='descr_$n' value='' autocomplete='off'>";
	$rowClasses[$n-1] = 'hiddenrow';
}

$pageTitle = 'Service List';
include "frame.html";
?>
<style>
.hourinput {width:35px;}
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
echo "<form name='servicetypeseditor' method='POST'>\n";
hiddenElement("deletions", '');
hiddenElement("lastvisible", '');
hiddenElement("remainhere", '');
$lastVisible = count($oldTypeNames);
$oldTypeNames = join('|',$oldTypeNames);

$n = 0;
foreach($types as $key => $service) {
	$n++;
	hiddenElement("servicetypeid_$n", $key);
	hiddenElement("label_$n", $service['label']);
}

echo "<p align=right>";
if(staffOnlyTEST()) echoButton('', 'Import Service Types', 'document.location.href="service-type-reader.php"');
echo "&nbsp;&nbsp;";
echoButton('', 'Edit Menu Order', 'openConsoleWindow("serviceSorderEdit", "services-order-edit.php",400,700)');
echo "&nbsp;&nbsp;";
echoButton('', 'Save Changes', 'saveChanges()');
echo "<p>";
foreach($types as $type) if(!$type['active']) $inactiveTypes++;
if($inactiveTypes) {
	echo "$inactiveTypes service type".($inactiveTypes > 1 ? "s are " : " is ")." currently inactive.";
	echoButton('inactivebutton', 'Hide Inactive Types On this Page', 'toggleInactiveTypes(this)');
}


tableFrom($columns, $data, 'width=100%', null, null, null, null, null, $rowClasses);

echoButton('addservicebutton', 'Add Another Service', 'addAnotherService()');

echo "\n<div id='aintnomore' class='tiplooks' style='display:none;font-size:1.05em;'>To add more services, please save your changes and return to this page.</div>";

?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
var oldTypeNames = '<?= safeValue($oldTypeNames) ?>'.split('|');
var lastVisible = <?= $lastVisible ?>;

function toggleInactiveTypes(button) {
	$('#inactivebutton').val(button.value.indexOf('Hide') == 0 ? 'Show All Types On this Page' : 'Hide Inactive Types On this Page');
	$('.inactivetype').toggle();
}

function update() { // called by display-order-edit.php
	if(confirm("The ordering of the Services menu has changed.\nSave changes on this page and redisplay the list?")) {
		document.servicetypeseditor.remainhere.value = 1;
		saveChanges();
	}
}

function saveChanges() {
	if(validateForm()) {
		document.servicetypeseditor.deletions.value=deletions.join(',');
		document.servicetypeseditor.lastvisible.value = lastVisible;
		document.servicetypeseditor.submit();
	}
}

function editServiceLabel(servicetypeid) {
	$.fn.colorbox({href:"service-label-edit.php?id="+servicetypeid, iframe: true, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}



function validateForm() {
	var allProblems = new Array();
	// collect each pre-existing service that is not marked for deletion
	// collect each visible new service that is not marked for deletion
	var el;
	for(var i=1; el = document.getElementById('defaultcharge_'+i); i++) {
		var deleted = false;
		if(el.parentNode.parentNode.className == 'hiddenrow') continue;
		for(var n=0;n<deletions.length;n++)
		  if(deletions[n] == 'delete_'+i) {
				deleted = true;
				break;
			}
		if(!deleted) {
			var problems = lineProblems(i);
			if(problems.length > 0) for(var p=0;p<problems.length;p++) allProblems[allProblems.length] = problems[p];
		}
	}
	if(allProblems.length > 0) {
	  allProblems = allProblems.join('\n');
	  alert("Your changes could not be saved because:\n\n"+allProblems);
	  return false;
	}
	return true;
}

function lineProblems(i) {
	var problems = new Array();
	// if service i is new make sure the name is not already in use
	var label = jstrim(document.getElementById('label_'+i).value);
	if(!document.getElementById('servicetypeid_'+i)) {
		if(label.length == 0) problems[problems.length] = "Each service must have a label.";
		else for(var n=0;n<oldTypeNames.length;n++)
		  if(oldTypeNames[n] == label.toUpperCase()) {
				problems[0] = "The name ["+label+"] is already in use.";
				break;
			}
  }
  var useLabel = (label.length == 0) ? "Each service" : "["+label+"]";
	// make sure price is a float
	var price = jstrim(document.getElementById('defaultcharge_'+i).value);
	if(price.length == 0) problems[problems.length] = useLabel+" must have a price.";
	else if(!isUnsignedFloat(price)) problems[problems.length] = useLabel+"'s price must be a number.";
	return problems;
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function addAnotherService() {
	var els = document.getElementsByTagName('tr');
	for(i=0;i<els.length;i++)
		if(els[i].className == 'hiddenrow') {
			els[i].className = 'visiblerow';
			lastVisible += 1;
			// if no more hidden elements
			if(i+1 == els.length) {
				document.getElementById('addservicebutton').style.display='none';
				document.getElementById('aintnomore').style.display='inline';
			}
			return;
		}
}

var deletions = new Array();

function deleteService(img) {
	var row = img.parentNode.parentNode;
	if(row.className != 'deletedrow') {
		row.className = 'deletedrow';
		deletions[deletions.length] = img.id;
		alert('This service will be deleted when you save changes.\n\nClick here again to retain this service.');
	}
	else {
		row.className = 'visiblerow';
		for(var i=0;i<deletions.length;i++)
		  if(deletions[i] == img.id) deletions[i] = 0;
	}
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

function updateHours(i) {
	var hours = document.getElementById('hours_'+i).value;
	//var patt1 = /^([0-1]?\d|2[0-4]):([0-5]?\d)$/i;
	//if(!hours.match(patt1)) {
	if(!validTime(hours)) {
		alert('Hours must be expressed in HH:MM format. Allowed range = 0:00 - 23:59');
		return;
	}
	document.getElementById('hoursdisplay_'+i).innerHTML = hours;
}
function editHours(i) {
	document.getElementById('hoursdisplay_'+i).style.display = 'none';
	document.getElementById('hourseditor_'+i).style.display = 'inline';
}
function hideHoursEditor(i) {
	document.getElementById('hoursdisplay_'+i).style.display = 'inline';
	document.getElementById('hourseditor_'+i).style.display = 'none';
}
</script>





<p><img src='art/spacer.gif' height=300>
<?
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
// ***************************************************************************
include "frame-end.html";
?>
