<?
// unassigned-visits-board-sitter.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "preference-fns.php";
require_once "unassigned-visits-board-fns.php";

refreshPreferences();

if(userRole() == 'o') locked('o-');
else locked('p-');

extract($_REQUEST);

cleanUpUVB();		


if($_POST) {
	$i = 0;
  foreach($_POST as $key => $value) {
  	if(strpos($key, 'cb')===0) {
			$i++;
  		$value = substr($key, 2);
  		$displayvalue = uvbShortDescr(substr($key, 2));
  		$extraFields .= "<hidden key=\"$key\">$value</hidden>";
  		$extraFields .= "<extra key=\"x-label-Item $i\"><![CDATA[$displayvalue]]></extra>";
		}
	}
	$extraFields .= "<extra key=\"x-label-Requestor\"><![CDATA[{$_SESSION["fullname"]}]]></extra>";
  if($extraFields) $request['extrafields'] = "<extrafields>$extraFields</extrafields>";
	$request['note'] = $note;
	$request['resolved'] = 0;
	$request['requesttype'] = 'UnassignedVisitOffer';
	$request['subject'] = 'Unassigned Visit Offer from '.$_SESSION["fullname"];
	$request['providerptr'] = $_SESSION["providerid"];
	require_once "request-fns.php";
	saveNewClientRequest($request, $notify=true);
	$_SESSION['frame_message'] = "Your offer to work has been submitted.";
}

$sortByClient = getPreference('unassignedBoardSitterSort') == 'client';
$details = explode(',', fetchPreference('unassignedboarddetails'));
if($sortByClient) {
	$includeDateField =  'date|Date||';
	$details[] = 'date';
}

$alldetails = explodePairsLine(
	"{$includeDateField}timeofday|Time of Day||client|Client Name||street1|Street||city|City, ZIP||locale|Neighborhood||service|Service Type"
	.'||pets|Pets||pettypes|Pet Type(s)||visitnote|Visit Notes');
foreach($details as $detail) if(strpos($detail, 'c_') === 0) $chosenLocaleField = $detail;

if(!$chosenLocaleField) unset($alldetails['locale']);
else {
	$chosenLocaleField = substr($chosenLocaleField, strlen('c_'));
	$localeLabel = fetchPreference($chosenLocaleField);
	if(!$localeLabel) unset($alldetails['locale']);
	else {
		$alldetails['locale'] = substr($localeLabel, 0, strpos($localeLabel, '|'));
	}
}
$columns['cb'] = '';
foreach($alldetails as $detail => $label) {
	if($detail == 'pettypes' && !$columns['pets']) $columns['pets'] = $alldetails['pets'];
	else if(in_array($detail, $details) || $detail == 'locale') $columns[$detail] = $label;
	if($detail == 'client') $showClientNames = 1;
}

unset($columns['pettypes']);  // merged into pets
unset($columns['visitnote']);  // separate line
if($sortByClient) unset($columns['client']);
$colcount = count($columns);


$today = date('Y-m-d');
$unassignedVisits = fetchAssociationsKeyedBy($sql = 
	"SELECT tblappointment.*, uvbnote, uvbid, uvbtod, CONCAT_WS(' ', fname, lname) as client, 
			street1, CONCAT(IFNULL(city, ''), ' ', IFNULL(zip, '')) as city, label as service, 
			clientid as clientptr
		FROM tblunassignedboard 
		LEFT JOIN tblappointment ON appointmentid = appointmentptr
		LEFT JOIN tblclient ON clientid = tblappointment.clientptr
		LEFT JOIN tblservicetype ON servicetypeid = servicecode
		WHERE providerptr = 0 AND date >= '$today'", 'appointmentid');
		

/*		
$toDelete = fetchCol0(
	"SELECT uvbid
		FROM tblunassignedboard
		LEFT JOIN tblappointment ON appointmentid = appointmentptr
		WHERE providerptr > 0 OR date < '$today'");
		
if($toDelete) deleteTable('tblunassignedboard', "uvbid IN (".join(',', $toDelete).")");
*/
cleanUpUVB();		

$unTethered = fetchAssociations(
	"SELECT *
		FROM tblunassignedboard
		WHERE appointmentptr IS NULL", 'uvbid');
foreach($unTethered as $uvbid => $uvb) {
	if($uvb['packageptr']) {
			//$pkg = fetchFirstAssoc("SELECT clientptr FROM tblservicepackage WHERE packageid = {$uvb['packageptr']}", 1);
			$uvb['clientptr'] = 
				fetchRow0Col0("SELECT clientptr FROM tblservicepackage WHERE packageid = {$uvb['packageptr']} LIMIT 1", 1);
	}
	$unassignedVisits[] = $uvb;
}

foreach($unassignedVisits as $i => $u)
	//if(mattOnlyTEST() && !$u['appointmentid']) echo "BANG! ". print_r($u, 1)."\n";
	if(!$u['appointmentid'] && $u['packageptr']) {
		$pkg = fetchFirstAssoc("SELECT clientptr FROM tblservicepackage WHERE packageid = {$u['packageptr']}", 1);
		$unassignedVisits[$i]['clientptr'] = 
			fetchRow0Col0("SELECT clientptr FROM tblservicepackage WHERE packageid = {$u['packageptr']} LIMIT 1", 1);
		}




$allClients = array();
foreach($unassignedVisits as $i => $uv) {
	if($uv['uvbdate']) $uv['date'] = $uv['uvbdate'];
	if($uv['date']) $uv['uvbdate'] = $uv['date'];
	$allClients[] = $uv['clientptr'] ? $uv['clientptr'] : 0;
	$allClients = array_unique($allClients);
}
if($allClients) $allClients = getClientDetails($allClients, array('sortname'), $sorted=true);

function cmpdate($a, $b) {
	$a = "{$a['uvbdate']} ".($a['starttime'] ? $a['starttime'] : "00:00");
	$b = "{$b['uvbdate']} ".($b['starttime'] ? $b['starttime'] : "00:00");
	return strcmp($a, $b);
}

function cmpclient($a, $b) { 
	global $allClients; 
	$r = strcmp(''.$allClients[$a['clientptr']]['sortname'], ''.$allClients[$b['clientptr']]['sortname']);
	return $r == 0 ? cmpdate($a, $b) : $r;
}
//print_r($allClients);exit;

foreach($unassignedVisits as $i =>$u) 
	$unassignedVisits[$i]['uvbdate'] = $u['uvbdate'] ? $u['uvbdate'] : $u['date'];
	

uasort($unassignedVisits, ($sortByClient ? 'cmpclient' : 'cmpdate'));

//foreach($unassignedVisits as $u) echo "[{$u['appointmentptr']}] ".print_r($allClients[$u['clientptr']],1)."<p>";exit;

//print_r($unassignedVisits);exit;
$clientNumber = 0;

$lastClient = -999;
$dns = doNotServeClientIds($_SESSION["providerid"]);
if($_SESSION["providerid"] && (staffOnlyTEST() || dbTEST('tonkatest'))) {
	$sql = "SELECT DISTINCT clientptr
					FROM tblappointment
					WHERE providerptr = {$_SESSION["providerid"]} AND canceled IS NULL";
	$pastClientsServedOrFutureClients = fetchCol0($sql);
//foreach($pastClientsServedOrFutureClients as $pp) echo ', '.fetchRow0Col0("SELECT CONCAT(fname, ' ', lname) FROM tblclient WHERE clientid = $pp", 1);	
}
foreach($unassignedVisits as $u) {
	if(staffOnlyTEST() || dbTEST('tonkatest')) {
//echo "[[{$u['clientptr']}]]<br>".join(',', $dns)."<hr>";
		if($dns && in_array($u['clientptr'], $dns))
			continue;
		// enforce uvbpastclientsonly OR mark past clients
		if($_SESSION["providerid"] && getPreference('uvbpastclientsonly') && $u['clientptr'] && !in_array($u['clientptr'], $pastClientsServedOrFutureClients))
			continue;
		else if(!getPreference('uvbpastclientsonly')) $associatedClient = in_array($u['clientptr'], $pastClientsServedOrFutureClients);
	}
	$rowClass = $rowClass == 'futuretask' ?  'futuretaskEVEN' : 'futuretask';
	$name = 'uvbnote'.($id = $u['uvbid']);
	$note = safeValue($u['uvbnote']);
	$date = $u['date'] ? $u['date'] : $u['uvbdate'];
	$dateval = $u['date'] ? $u['date'] : $u['uvbdate'];
	if($sortByClient && $lastClient != $u['clientptr']) {
		if($lastClient != -999) $clientNumber += 1;
		$clientLabel =  
			$allClients[$u['clientptr']]['clientname'] ? ($showClientNames ? $allClients[$u['clientptr']]['clientname'] : "Client #$clientNumber")
			: "General";
		$lastClient = $u['clientptr'];
		$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='datestyle'><td style='font-size:1.4em;' colspan=$colcount>$clientLabel</td></tr>");
		$rowClasses[] = 'datestyle';
	}
	else if(!$sortByClient && $dateval != $lastDate) {
		$dateLabel = 
			$dateval == date('Y-m-d') ? "TODAY (".longDayAndDate().")" : (
			$dateval == date('Y-m-d', strtotime("+1 day")) ? "TOMORROW (".longDayAndDate(strtotime($dateval)).")" : (
				longDayAndDate(strtotime($dateval))));
		$lastDate = $dateval;
		$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='datestyle'><td style='font-size:1.4em;' colspan=$colcount>$dateLabel</td></tr>");
		$rowClasses[] = 'datestyle';
		if(staffOnlyTEST() || dbTEST('tonkatest')) {
			$timesOff = getProviderTimeOff($_SESSION["providerid"], $showpasttimeoff=false, $where="date = '$dateval'");
			if($timesOff) {
				$timesOffText = array();
				foreach($timesOff as $to) 
					$timesOffText[] = "<b>TIME OFF</b> ("
						.($to['timeofday'] ? $to['timeofday'] : "All Day")
						.") {$to['note']}";
				$timesOffText = join('<br>', $timesOffText);
				$rows[] = array('#CUSTOM_ROW#'=>"\n<tr><td style='font-size:1.1em;color:red;' colspan=$colcount>$timesOffText</td></tr>\n");
				$rowClasses[] = '';
			}
			
		}
	}
	
	$cb = "<input type='checkbox' name='cb$id' id='cb$id' date='".shortDate(strtotime($u['date']))."'>";
	// build 3 rows:
	// row 1: date {select visits fields}
	// row 2 (optional): visit note
	// row 3: uvb11bnote input
	//
	// or
	//
	// row 1: [checkbox] date time | uvb11bnote input
	//$columns
	if($u['appointmentid']) {		
		$pets = array();
		$showPetNames = in_array('pets', $details);
		if($columns['pets']) {
		$petdisplay = array();
			$allpets = fetchAssociationsKeyedBy("SELECT * FROM tblpet WHERE active = 1 AND ownerptr = {$u['clientptr']} ORDER BY name", 'name');
			if($u['pets'] == 'All Pets') $pets = $allpets;
			else if($u['pets']) {
				$clientPets = fetchAssociations("SELECT * FROM tblpet WHERE active = 1 AND ownerptr = {$u['clientptr']}", 1);
				$nameTypes = array();
				foreach($clientPets as $pet) $nameTypes[$pet['name']][$pet['type']] += 1; // array('buddy'=>array('dog'=>1, 'cat=>1), 'caspar'=>array('cat'=>1))
				$visitPetNames = array_map('trim', explode(',', $u['pets']));
				foreach($visitPetNames as $pn) {
					$ptype = count($nameTypes[$pn]) == 1 ? current(array_keys($nameTypes[$pn])) : '?';
					$pets[] = array('name'=>$pn, 'type'=>$ptype);
				}
			}
			else $pets = array();
//screenLog(print_r($allpets,1));	
//screenLog("PETS: ".$columns['pets']." TYPES: ".$details['pettypes']."  ALL: ".fetchPreference('unassignedboarddetails'));
			foreach($pets as $pet) {
				$ptype = $pet['type'] ? $pet['type'] : '?';
				$petdisplay[] = 
					$showPetNames && in_array('pettypes', $details) ? "{$pet['name']} ($ptype)" : (
					$showPetNames ? $pet['name'] : $ptype);
				}
			$u['pets'] = count($pets) ? "[".count($pets)."] ".join(', ', (array)$petdisplay) : '<i>No pets</i>';
		}
		if($chosenLocaleField) $u['locale'] = 
			fetchRow0Col0("SELECT value FROM relclientcustomfield WHERE clientptr = {$u['clientptr']} AND fieldname = '$chosenLocaleField' LIMIT 1");
	}
	else if($u['packageptr']) {
		//$u['packageDescription'] = createPackageDescription($u['packageptr']);
		$u = packageDescriptionAsVisit($u['packageptr']);
	}
	else {
		$u['timeofday'] =  $u['uvbtod'];
	}
	
	$finalRowClass = array($rowClass);
	if($associatedClient) $finalRowClass[] = 'bold';
	if($finalRowClass) $finalRowClass  = join(' ', $finalRowClass);
	
	$displayDate = shortDate(strtotime($date));
	if($u['packageDescription']) {
		$columnCount = count($columns)-2;
		$row = array('#CUSTOM_ROW#'=>"<tr class ='$finalRowClass'><td>$cb</td><td>$displayDate</td><td colspan=$columnCount>{$u['packageDescription']} </td></tr>");
	}
	else {
		$row = $u;
		$row[cb] = $cb;
		$row[date] = $displayDate;
	}
	
	$rows[] = $row;
	$rowClasses[] = $finalRowClass;
	$columnCount = count($columns)-1;
	$isSchedule = !$u['appointmentid'];
	
	if($u['note'] && ($isSchedule || in_array('visitnote', $details))) {		
		$rowLabel = $u['appointmentid'] ? 'Visit Note:'  : ' Schedule:';
//$rowLabel .= " ".print_r($u, 1);		
		$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='$rowClass'><td>&nbsp;</td><td style='font-size:1.2em;' colspan=$columnCount><b>$rowLabel</b> {$u['note']}</td></tr>");
		$rowClasses[] = $finalRowClass;
	}
	if($note) {
		$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='$rowClass'><td>&nbsp;</td><td style='font-size:1.2em;' colspan=$columnCount><b>Description:</b> $note</td></tr>");
		$rowClasses[] = $finalRowClass;
	}
}



$pageTitle = "Available Unassigned Visits";
include "frame.html";
?>
<style>
.datestyle {text-align:center;color:lightblue;font-size:2em;font-weight:bold;}
</style>
<?
if(!$rows) {
	echo "<span class='tiplooks fontSize1_2em'>There are no advertised unassigned visits at the moment.</span>";
}
else {
?>
&nbsp;&nbsp;Check off the jobs you are willing to take on.
<p>
<?
	echo "<form name='mainform' method='POST'>";
	echoButton('', 'I Am Interested!', 'makeOffer()');
	echo "<img src='art/spacer.gif' width=15 height=1>";
	//labeledInput('Note:', 'note', null, null, 'VeryLongInput');
	echo "<label for='note'>Note:</label> <input class='VeryLongInput' id='note' name='note' onkeydown='"
		."var key;"
		."if(window.event) key = window.event.keyCode; /* IE */ "
    ."else key = e.which; /* Firefox & others */"
		."if(key == 13) return false'  autocomplete='off'><p><hr>";

	//$columns = explodePairsLine('cb| ||date|Date||timeofday|Time of Day||client|Client||service|Service||pets|Pets');
	tableFrom($columns, $rows, 'width=95%', null, null, null, null, null, $rowClasses, $colClasses);
?>
</form>
<script language='javascript'>
function makeOffer() {
	<?= userRole() != 'p' ? "alert('Only providers can make offers.');return;" : '' ?>
	var sels;
	if((sels = getSelections('You must choose at least one entry first.')).length == 0) return;
<? if(FALSE || (mattOnlyTEST() || dbTEST('goldcoastpetsau,tonkatest'))) echo "confirmOffer(sels);return;"; else echo "document.mainform.submit();"; ?>
	
}

function confirmOffer(sels) {
	sels = sels.split(',');
	var table = "<table border=1 bordercolor=gray>";
	for(var i=0; i < sels.length; i++) {
<? if(FALSE && mattOnlyTEST()) { ?>alert(document.getElementById(sels[i]).parentNode.parentNode.innerHTML);<? } ?>
		let cbdate = $('#'+sels[i]).attr('date');
		table += "<tr><td>"+cbdate+"</td>"+document.getElementById(sels[i]).parentNode.parentNode.innerHTML.replace(/input/g, 'ignore')+"</li>";
	}
	table += "</table>";
	var buttons = "<input type='button' value='Ok' onclick='document.mainform.submit()'>&nbsp;&nbsp;"
								+"<input type='button' value='Cancel' onclick='$.fn.colorbox.close();'>";
	var note;
	if(note = document.getElementById('note').value)
		table = "<b>Note:</b> "+note.replace(/\n/g, '<br>')
					+'<p>'+table;
	table = "<h2>You are about to offer to take on the following "+buttons+"</h2>"+table;
	$.fn.colorbox({html:table, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}

function getSelections(emptyMsg) {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('cb') == 0 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert(emptyMsg);
		return;
	}
	return sels;
}


</script>
<?
}

include "frame-end.html";

/*function createPackageDescription($packageptr) {
	//return an EZ Schedule description that fits nicely on one line in N-2 columns (checkbox & date)
	//e.g.  15 visits ending 10/21/2012 (Dog Walk 15, Play Time, Pet Taxi) Client: John Doe Pets: Spunky, Chewie
	require_once "service-fns.php";
	require_once "pet-fns.php";
	$package = getCurrentNRPackage($packageptr);
	$details = explode(',', $_SESSION['preferences']['unassignedboarddetails']);
	$appts = fetchAllAppointmentsForNRPackage($package, $clientptr=null);
	if(in_array('service', $details) && $appts) {
		$servTypes = $_SESSION['servicenames'];
		foreach($appts as $appt) $services[$servTypes[$appt['servicecode']]] = null;
		$services = " (".join(', ', array_keys($services)).")";
	}
	if(in_array('client', $details)) 
		$client = 
			fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$package['clientptr']} LIMIT 1");
	if(in_array('pets', $details) || in_array('pettypes', $details)) {
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
				if(in_array('pets', $details)) $label[] = $name;
				if(in_array('pettypes', $details)) $label[] = $clientPets[$name] ? "({$clientPets[$name]})" : '';
				$petLabels[] = $label ? join(' ', $label) : '(unknown)';
			}
		else $petLabels[] = '(unknown)';
		$petLabels = ' '.join(', ', $petLabels);
	}
	
	
	return $client.' '.count($appts)." visits ending ".shortDate(strtotime($package['enddate']))." ".$services.$petLabels;
}
*/

function uvbShortDescr($uvbid, $withLinks='') {
	$uv = fetchFirstAssoc(
		"SELECT appt.*, uvbdate, ifnull(uvbtod, timeofday) as timeofday, 
						appointmentptr, uv.packageptr as uvpackageptr, uv.clientptr, uvbnote, CONCAT_WS(' ', fname, lname) as client
			FROM tblunassignedboard uv
			LEFT JOIN tblappointment appt ON appointmentid = appointmentptr
			LEFT JOIN tblclient client ON clientid = appt.clientptr
			WHERE uvbid = $uvbid LIMIT 1");

	if($uv['uvpackageptr']) {
		$uv = packageDescriptionAsVisit($uv['uvpackageptr'], $fullManagerDetails=1);
		$uv['service'] = "Schedule: " .$uv['service'];
		if($uv['timeofday'] == 'various') $uv['timeofday'] = '(various times)';
	}
	else if($uv['appointmentptr'])  $uv['service'] = $_SESSION['servicenames'][$uv['servicecode']];
	else {
		$uv['timeofday'] =  $uv['uvbtod'];
		$uv['date'] = $uv['uvbdate'];
	}
	
	return 
		"<span id='$uvbid' style='display:none'></span>"
		.$uv['client'] ?
			shortDate(strtotime($uv['date']))." "
				 .$uv['timeofday']." "
				 .$uv['client']." "
				 .$uv['service']." "
				 .$uv['pets']
		: shortDate(strtotime($uv['date']))." "
				 .$uv['timeofday']." "
				 .$uv['uvbnote'];
}				 
		
				 

function packageDescriptionAsVisit($packageptr, $fullManagerDetails=false) {
	/* return an EZ Schedule description that will fit in like a visit
			e.g.  15 visits ending 10/21/2012 (Dog Walk 15, Play Time, Pet Taxi) Client: John Doe Pets: Spunky, Chewie
	*/
	global $sortByClient, $chosenLocaleField;
	require_once "service-fns.php";
	require_once "pet-fns.php";
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
	if(($fullManagerDetails || in_array('service', $details)) && $appts) {
		$servTypes = $_SESSION['servicenames'];
		foreach($appts as $appt) $services[$servTypes[$appt['servicecode']]] = null;
		$services = " (".join(', ', array_keys($services)).")";
	}
	if(($fullManagerDetails || in_array('client', $details))) 
		$client = 
			fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$package['clientptr']} LIMIT 1");
	if(($fullManagerDetails || in_array('pets', $details) || in_array('pettypes', $details))) {
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
				if($fullManagerDetails || in_array('pets', $details)) $label[] = $name;
				if(!$fullManagerDetails && in_array('pettypes', $details)) $label[] = $clientPets[$name] ? "({$clientPets[$name]})" : '';
				$petLabels[] = $label ? join(' ', $label) : '(unknown)';
			}
		else $petLabels[] = '(unknown)';
		$petLabels = ' '.join(', ', $petLabels);
	}
	if($chosenLocaleField) $locale = 
		fetchRow0Col0("SELECT value FROM relclientcustomfield WHERE clientptr = {$package['clientptr']} AND fieldname = '$chosenLocaleField' LIMIT 1");
	
	return array(
		'client'=>$client,
		'clientptr'=>$package['clientptr'],
		'note'=>count($appts)." unassigned visits starting ".shortNaturalDate(strtotime($startDate),1)." and ending ".shortNaturalDate(strtotime($endDate),1)
							/*.($package['notes'] ? "\n".$package['notes'] : '')*/,
		'date'=>$startDate,
		'timeofday'=>(count($tods) == 1 ? $tod[0] : 'various'), //print_r($tods,1),
		'service'=>$services,
		'pets'=>$petLabels,
		'locale'=>$locale);
}


