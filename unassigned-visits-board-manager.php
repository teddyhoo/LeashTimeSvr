<?
// unassigned-visits-board-manager.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "preference-fns.php";
require_once "unassigned-visits-board-fns.php";

locked('#as');

extract($_REQUEST);

setupUVBTableIfNecessary();

$today = date('Y-m-d');

cleanUpUVB();		

if($_POST) {
	$sortBy = $sortBy ? $sortBy : 'date';
	if($_POST['newid']) $checkedUVBIDs[] = $_POST['newid'];
	foreach($_POST as $key => $val) {
		if(strpos($key, 'detail_') === 0) {
			$val = substr($key, strlen('detail_'));
			if($val == 'locale') $val = $_POST['locale'];
			if($val) $details[] = $val;
		}
		if(strpos($key, 'cb') === 0) {
			$id = substr($key, strlen('cb'));
			if((int)$id < 0) {
				$uvb = array('uvbid'=>(0-$id));
				$where = "uvbid = ".(0-$id);
				$checkedUVBIDs[] = 0-$id;
			}
			else {
				$uvb = array('appointmentptr'=>$id);
				$uvb['clientptr'] = fetchRow0Col0("SELECT clientptr FROM tblappointment WHERE appointmentid = $id LIMIT 1");
				$where = "appointmentptr = $id";
				$checkedAppts[] = $id;
			}
			screenLog("$key => $val");			
			$olduvb = fetchFirstAssoc($sql = "SELECT * FROM tblunassignedboard WHERE ".current(array_keys($uvb))." = ".current($uvb)." LIMIT 1", 1);
			////Five walks for a client with 3 dogs on the east side of town.  Last visit is 10/13
			if($olduvb) {
				$olduvb['modified'] = date('Y-m-d H:i:s');
				$olduvb['modifiedby'] = $_SESSION["auth_user_id"];
				$olduvb['uvbnote'] = $_POST["uvbnote$id"];
				updateTable('tblunassignedboard', $olduvb, $where);
			}
			else {
				$uvb['created'] = date('Y-m-d H:i:s');
				$uvb['createdby'] = $_SESSION["auth_user_id"];
				$uvb['uvbnote'] = $_POST["uvbnote$id"];
				insertTable('tblunassignedboard', $uvb);
			}
		}
	}
	// delete unchecked visits from uvb
	$checkedUVBIDs = "appointmentptr IS NULL".($checkedUVBIDs ? " AND uvbid NOT IN (".join(',', $checkedUVBIDs).")" : '');
	deleteTable('tblunassignedboard', $checkedUVBIDs);
	$checkedAppts = "appointmentptr IS NOT NULL".($checkedAppts ? " AND appointmentptr NOT IN (".join(',', $checkedAppts).")" : '');
	deleteTable('tblunassignedboard', $checkedAppts);
}
	
	setPreference('unassignedboarddetails', join(',', (array)$details));
	setPreference('unassignedBoardSitterSort', $sortBy);
	if(dbTEST('tonkatest')) setPreference('uvbpastclientsonly', $uvbpastclientsonly);
	$_SESSION['frame_message'] = 'Changes saved.';
	
	
}

$unassignedVisits = fetchAssociationsKeyedBy($sql = 
	"SELECT tblappointment.*, uvbnote, uvbid, CONCAT_WS(' ', fname, lname) as client, label as service
		FROM tblappointment 
		LEFT JOIN tblunassignedboard ON appointmentptr = appointmentid
		LEFT JOIN tblclient ON clientid = tblappointment.clientptr
		LEFT JOIN tblservicetype ON servicetypeid = servicecode
		WHERE providerptr = 0 AND date >= '$today' AND canceled IS NULL AND completed IS NULL", 'appointmentid');
$unTethered = fetchAssociations(
	"SELECT *
		FROM tblunassignedboard
		WHERE appointmentptr IS NULL", 'uvbid');

foreach($unTethered as $uvbid => $uvb) $unassignedVisits[] = $uvb;


foreach($unassignedVisits as $i => $uv) {
	if(!$uv['uvbdate'] && $uv['date']) $unassignedVisits[$i]['uvbdate'] = $uv['date'];
	//if($uv['uvbdate']) $uv['date'] = $uv['uvbdate'];
}

function cmpdate($a, $b) {
	$a = "{$a['uvbdate']} ".($a['starttime'] ? $a['starttime'] : "00:00");
	$b = "{$b['uvbdate']} ".($b['starttime'] ? $b['starttime'] : "00:00");
	return strcmp($a, $b);
}

uasort($unassignedVisits, 'cmpdate');
//print_r($unassignedVisits);

foreach($unassignedVisits as $u) {
	$rowClass = $rowClass == 'futuretask' ?  'futuretaskEVEN' : 'futuretask';
	$name = 'uvbnote'.($id = $u['appointmentid'] ? $u['appointmentid'] : 0-$u['uvbid']);
	$onBlur = "onBlur = 'noteBlur($id)'";
	$note = "<input class='VeryLongInput' style='width:700px' id='$name' name='$name' value='".safeValue($u['uvbnote'])."' origvalue='".safeValue($u['uvbnote'])."' $onBlur $maxlength  autocomplete='off'>";
	$date = shortDate(strtotime($u['uvbdate'] ? $u['uvbdate'] : $u['date']));
	
	$dateval = $u['date'] ? $u['date'] : $u['uvbdate'];
	if($dateval != $lastDate) {
		$dateLabel = 
			$dateval == date('Y-m-d') ? "TODAY (".longDayAndDate().")" : (
			$dateval == date('Y-m-d', strtotime("+1 day")) ? "TOMORROW (".longDayAndDate(strtotime($dateval)).")" : (
				longDayAndDate(strtotime($dateval))));
		$lastDate = $dateval;
		$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='datestyle'><td style='font-size:1.4em;padding-top:20px;' colspan=5>$dateLabel</td>"
											."<td style='text-align:right;font-size:0.5em;'>".echoButton('', 'Add a Listing', "addAListing(\"$dateval\")", null,null,'noEcho',"Add a listing that does not relate to a single unassiged visit.")
											."</tr>");
		$rowClasses[] = 'datestyle';
	}
					
	
	$checked = $u['uvbid'] ? 'CHECKED' : '';
	$cb = "<input class='uvcheckbox' type='checkbox' name='cb$id' id='cb$id' $checked>";
	// build 3 rows:
	// row 1: [checkbox] date | timeofday | client | service | pets..
	// row 2 (optional): visit note
	// row 3: uvb11bnote input
	//
	// or
	//
	// row 1: [checkbox] date time | uvb11bnote input
	if($u['appointmentid']) 
		$row = array('cb'=>$cb, 'date'=>shortDate(strtotime($date)), 'timeofday'=>$u['timeofday'], 'client'=>$u['client'], 'service'=>$u['service'], 'pets'=>$u['pets']);
	else if($u['packageptr']) {
		//$packageDescription = createPackageDescription($u['packageptr']);
		//$row = array('#CUSTOM_ROW#'=>"<tr class ='$rowClass checkboxline'><td>$cb</td><td>$date</td><td colspan=4>$packageDescription</td></tr>");
		$row = packageDescriptionAsVisit($u['packageptr']);
		$u['note'] = $row['note'];
		$row['date'] = shortDate(strtotime($row['date']));;
		$row['cb'] = $cb;
//print_r($u);		
//$STOP = true;
	}
	else $row = array('#CUSTOM_ROW#'=>"<tr class ='$rowClass checkboxline'><td>$cb</td><td>$date</td><td colspan=4>{$u['uvbtod']}</td></tr>");
	$rows[] = $row;
	$rowClasses[] = "$rowClass checkboxline";
	if($u['note']) {
		$itemType = $u['appointmentid'] ? 'Visit' : ($u['packageptr'] ? 'Schedule' : 'Miscellaneous');
		$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='$rowClass'><td style='font-size:1.2em;' colspan=6><b>$itemType Note:</b> {$u['note']}</td></tr>");
		$rowClasses[] = $rowClass;
	}
	$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='$rowClass bottomline'><td style='font-size:1.2em;' colspan=6><b>Note:</b> $note</td></tr>");
	$rowClasses[] = $rowClass;
	//if($STOP && mattOnlyTEST()) break;
}

// #####################################################

$pageTitle = "Unassigned Visits Bulletin Board";
include "frame.html";
?>
<style>
.datestyle {text-align:center;color:lightblue;font-size:2em;font-weight:bold;}
.checkboxline td {padding-top:15px;}
.bottomline {}
.bottomline td {padding-bottom: 15px;}
</style>
<?
echo "<form name='mainform' method='POST'>";
hiddenElement('newid', '');
echo "<span class='fontSize1_3em boldfont'>";
echoButton('', 'Publish Unassigned Visits', 'publish()'); // DON'T WORK: , $class='fontSize1_3em Button ', $downClass='ButtonDown fontSize1_3em'
echo "</span>";
?>
&nbsp;&nbsp;All checked visits will be published to sitters.  
<div style='width:700px;background:lightblue;padding:7px;margin-top:7px;'>
<span class='fontSize1_3em boldfont'>Include these details:</span><span> 
<? echoButton('', 'View the Board as it looks right now', 'previewBoard()'); ?>
</span>
<br>
<?
echo 'Arrange Sitter view by: '; // <img src="art/spacer.gif" width=25>
$sortVal = getPreference('unassignedBoardSitterSort') ?  getPreference('unassignedBoardSitterSort') : 'date';
echo join(' ', radioButtonSet('sortBy', $sortVal, array('Date'=>'date', 'Client'=>'client')));
?>
<br>
<? if(TRUE) {
	labeledCheckbox('Show sitters visits only for their past/future clients',
		'uvbpastclientsonly', getPreference('uvbpastclientsonly'), $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null);
}
?>
<table style='width:700px;'>
<?
$details = explode(',', $_SESSION['preferences']['unassignedboarddetails']);

foreach($details as $d) if(strpos($d, 'c_') !== FALSE) $chosenLocale = $d;
$alldetails = explodePairsLine(
	'timeofday|Time of Day||client|Client Name||pets|Pet Names'
	.'||service|Service Type||street1|Street||pettypes|Pet Type(s)'
	.'||visitnote|Visit Notes||city|City, ZIP');
$locales = fetchKeyValuePairs(
	"SELECT property, value 
		FROM tblpreference 
		WHERE property LIKE 'custom%'
			AND value LIKE '%1|oneline|1%' 
		ORDER BY value");// (active, oneline, visitsheet visible) label|active|type|visitsheets|clientvisible
foreach($locales as $i => $val) $locales[$i] = substr($val, 0 , strpos($val, '|'));
if($locales) {
	$localeOptions = array(''=>'');
	foreach($locales as $prop => $locale) $localeOptions[$locale] = "c_$prop";
	$alldetails['locale'] = 
					selectElement('', 'locale', $chosenLocale, $localeOptions, 
								$onChange='$("#detail_locale").attr("checked", (this.selectedIndex == 0 ? false : true));',//
								$labelClass=null, $inputClass=null, $noEcho=true, $optExtras=null, $title='Display the custom client field (if any) you use to indicate a client&apos;s neighborhood');
}

$i = 0;
foreach($alldetails as $detail => $label) {
	if($i == 3) { echo "</tr>"; $i = 0; }
	if($i == 0) echo "<tr>";
	$i++;
	if($detail == 'locale') {
		$onclick = "onclick='if(!this.checked) $(\"#locale\")[0].selectedIndex = 0'";
		$checked = $chosenLocale ? 'CHECKED' : '';
		$label = "Extra (locale, maybe): $label";
		$title = 'If you have a neighborhood Client Custom Field (or similar) you can include it here.';
	}
	else {
		$label = "<label for='detail_$detail'>$label</label>";
		$onclick = '';
		$checked = (in_array($detail, $details) ? 'CHECKED' : '');
		$title = '';
	}
	echo "<td title='$title'><input type='checkbox' name='detail_$detail' id='detail_$detail' $checked $onclick> $label</td>";
}
echo ($i == 4 ? '' : "</tr>");
?>
</table>
</div>
<p>
<?
if(TRUE || mattOnlyTEST()) {
	fauxLink('Select All Visits', 'selectAll(1)');
	echo " - ";
	fauxLink('De-select All Visits', 'selectAll(0)');
	echo "<p>\n";
}

$columns = explodePairsLine('cb| ||date|Date||timeofday|Time of Day||client|Client||service|Service||pets|Pets');
tableFrom($columns, $rows, 'width=95%', null, null, null, null, null, $rowClasses, $colClasses);
?>
</form>
<script language='javascript'>

function selectAll(state) {
	$('.uvcheckbox').prop('checked', (state == 1));
}


function publish(newid) {
	if(newid != undefined) document.mainform.newid.value = newid;
	document.mainform.submit();
}

function noteBlur(id) {
	var $notefield = $('#uvbnote'+id);
	if($notefield.val() == $notefield.attr('checked')) return;
	$('#cb'+id).attr('checked', true);
}

function addAListing(date) {
	$.fn.colorbox({href:"unassigned-visits-board-add-listing.php?date="+date, iframe:true, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}

function previewBoard() {
	$.fn.colorbox({href:"unassigned-visits-board-sitter.php", iframe:true, width:"820", height:"750", scrolling: true, opacity: "0.3"});
}

</script>
<?

function createPackageDescription($packageptr) {
	/* return an EZ Schedule description that fits nicely on one line in N-2 columns (checkbox & date)
			e.g.  15 visits ending 10/21/2012 (Dog Walk 15, Play Time, Pet Taxi) Client: John Doe Pets: Spunky, Chewie
	*/
	require_once "service-fns.php";
	require_once "pet-fns.php";
	$package = getCurrentNRPackage($packageptr);
	$details = explode(',', $_SESSION['preferences']['unassignedboarddetails']);
	$appts = fetchAllAppointmentsForNRPackage($package, $clientptr=null);
	$servTypes = $_SESSION['servicenames'];
	$services = array();
	$pets = array();
	foreach($appts as $i => $appt) {
		if($appt['canceled'] || $appt['providerptr']) unset($appts[$i]);
		else {
			$services[$servTypes[$appt['servicecode']]] = null;
			foreach(explode(',', (string)$appt['pets']) as $pet)
				$pets[$pet] = 1;
		}
	}
	$services = " (".join(', ', array_keys($services)).")";
	unset($pets['']);
	if(isset($pets['All Pets'])) 
		$pets = getClientPetNames($package['clientptr'], $inactiveAlso=false, $englishList=true);
	else if($pets) $pets = join(', ', array_keys($pets));
	if($pets) $pets = ' Pets: '.$pets;
	
	$client = 
		' Client: '
		.fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$package['clientptr']} LIMIT 1");
	
	return count($appts)." unassigned visits ending ".shortDate(strtotime($package['enddate']))." ".$services.$client.$pets;
}

function packageDescriptionAsVisit($packageptr) {
	/* return an EZ Schedule description that will fit in like a visit
			e.g.  15 visits ending 10/21/2012 (Dog Walk 15, Play Time, Pet Taxi) Client: John Doe Pets: Spunky, Chewie
	*/
	require_once "service-fns.php";
	require_once "pet-fns.php";
	$package = getPackage($packageptr);
	if(!$package) {
		return array(
			'client'=>'unknown',
			'note'=>'This package no longer exists.',
			'date'=>'0000-00-00',
			'timeofday'=>'unknown',
			'service'=>'',
			'pets'=>'',
			'locale'=>'');
	}
	$package = getCurrentNRPackage($packageptr);
	$details = explode(',', $_SESSION['preferences']['unassignedboarddetails']);
	$appts = fetchAllAppointmentsForNRPackage($package, $clientptr=null);
	foreach($appts as $i => $appt) {
		if($appt['canceled'] || $appt['providerptr']) unset($appts[$i]);
		else {
			$tods[] = $appt['timeofday'];
			$startDate = $startDate ? $startDate : $appt['date'];
			$endDate = $appt['date'];
		}
	}
	$tods = $tods ? array_unique($tods) : array();
	$services = array();
	$pets = array();
	if($appts) {
		$servTypes = $_SESSION['servicenames'];
		foreach($appts as $appt) $services[$servTypes[$appt['servicecode']]] = null;
		$services = " (".join(', ', array_keys($services)).")";
	}
	$client = 
		fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$package['clientptr']} LIMIT 1");
	foreach(getActiveClientPets($package['clientptr']) as $pet) {
		//$pettype = $pet['type'] ? " ({$pet['type']})" : '';"{$pet['name']}$pettype"
		$clientPets[$pet['name']] = $pet['type'];
	}
	foreach($appts as $appt) {
		foreach(explode(',', (string)$appt['pets']) as $pet) {
			if($pet == 'All Pets') $showAllPets = 1;
			$pets[$pet] = 1;
		}
	}
	if($showAllPets) $pets = $clientPets;
	if($pets) 
		foreach(array_keys($pets) as $name) {
			$label = null;
			$label[] = $name;
			//$label[] = $clientPets[$name] ? "({$clientPets[$name]})" : '';
			$petLabels[] = $label ? join(' ', $label) : '(unknown)';
		}
	else $petLabels[] = '(unknown)';
	$petLabels = ' '.join(', ', $petLabels);
	
	return array(
		'client'=>$client,
		'note'=>count($appts)." unassigned visits starting ".shortNaturalDate(strtotime($startDate),1)." and ending ".shortNaturalDate(strtotime($endDate),1)
							/*.($package['notes'] ? "\n".$package['notes'] : '')*/,
		'date'=>$package['startdate'],
		'timeofday'=>(count($tods) == 1 ? $tod[0] : 'various'), //print_r($tods,1),
		'service'=>$services,
		'pets'=>$petLabels,
		'locale'=>$locale);
}




include "frame-end.html";
