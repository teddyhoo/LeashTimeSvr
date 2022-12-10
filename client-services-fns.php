<?
// client-services-fns.php

// custom field preferences are stored in tblpreference
//format: label|servicecode|description

require_once "preference-fns.php";
require_once "gui-fns.php";

//$maxCustomFields = 5;
//for($i=1; $i<= $maxCustomFields; $i++) $customFieldNames[$i] = "custom$i";

function getClientServices($clientptr=null) { // (in display sequence) label => serviceTypeId
	$services = array();
	//$prefs = fetchCol0("SELECT value FROM tblpreference WHERE property LIKE 'client_service_%' ORDER BY property");
	//foreach($prefs as $field) {
	//	$field = explode('|', $field); //clientServiceLabel, serviceTypeId, description
	//	$services[$field[0]] = $field[1];
	//}
	$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'client_service_%'");
	$petTypeFilter = serviceTypesTailoredByPets($clientptr);
//if(mattOnlyTEST()) echo "FILTER: [".print_r($petTypeFilter, 1)."]";	
	for($i=1; $i <= count($prefs); $i++) {
		$field = explode('|', $prefs["client_service_$i"]); //clientServiceLabel, serviceTypeId, description
		if(!$petTypeFilter || in_array($field[1], $petTypeFilter))
			$services[$field[0]] = $field[1];
	}
	return $services;	
}

function serviceTypesTailoredByPets($clientptr, $petNames=null) {
	if(!$clientptr) return null;
	if(is_string($petNames)) $petNames = array_map('leashtime_real_escape_string', explode(', ', $petNames));
	if($petNames) $petNameFilter = "AND name IN ('".join("','", $petNames)."')";
	$pettypes = fetchCol0(
		"SELECT DISTINCT IFNULL(type, 'UNKNOWN_BOINK')
			FROM tblpet 
			WHERE active = 1 
				AND ownerptr = '$clientptr' 
				$petNameFilter", 1);
//if(mattOnlyTEST()) echo "PET TYPES: [".print_r($pettypes, 1)."]";	
	if(!$pettypes || in_array('UNKNOWN_BOINK', $pettypes)) return null;
	require_once "pet-service-fns.php";
	$servicesByPettype = getAllPetsTypesServices();
	$allServiceCodes = array();
	foreach($pettypes as $pettype) { // If ANY pet has no defined services, then filtering is pointless.
		if(!$servicesByPettype[$pettype])
			return null;
		else foreach($servicesByPettype[$pettype] as $code) $allServiceCodes[$code] = 1;
	}
	return array_keys($allServiceCodes);
}

function getClientServiceNameOrder() {
	$names = array();
	for($i=1;isset($_SESSION['preferences']["client_service_$i"]); $i++) {
		$field = explode('|', $_SESSION['preferences']["client_service_$i"]);
		$names[$i] = $field[0];
	}
	return $names;	
}

function reorderClientServices($order) {
	$services = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'client_service_%'");
echo join(', ', 	$order).'<p>'.print_r($services, 1).'<p>';

//print_r($services );	
	deleteTable('tblpreference', "property LIKE 'client_service_%'", 1);
	foreach($order as $menuorder => $oldPosition) {
echo "client_service_".($menuorder+1).': '.$services["client_service_$oldPosition"].'<br>';	
		setPreference("client_service_".($menuorder+1), $services["client_service_$oldPosition"]);
	}
	$_SESSION['preferences'] = fetchPreferences();
}
	

function getClientServiceFields() {
	$fields = array();
	for($i=1;isset($_SESSION['preferences']["client_service_$i"]); $i++) {
		$field = explode('|', $_SESSION['preferences']["client_service_$i"]);
		if($field[1])
			$fields["client_service_$i"] = $field;
	}
	return $fields;
}

	
function saveClientServiceFields() {
	deleteTable('tblpreference', "property LIKE 'client_service_%'", 1);
	$services = getServiceSelections();
	$n = 0;
	foreach($_POST as $key => $val) {
		if(strpos($key, 'label_') === 0) {
			$i = substr($key, strlen('label_'));
			if(!$_POST["servicecode_$i"]) continue;
			$code = isset($_POST["servicecode_$i"]) && $_POST["servicecode_$i"] ? $_POST["servicecode_$i"] : 0;
			$pref = stripslashes($val)
								. '|'.$code.'|'
		        		. array_search($code, $services);
		  $n++;
			setPreference("client_service_$n", $pref);
		}
	}
	$_SESSION['preferences'] = fetchPreferences();
}

function clientServiceFieldEditor() {
	global $maxCustomFields;
	$activeServiceNames = getServiceNamesById();
	$fieldLimits = array(6=>14, 5=>20, 4=>30);
	$serviceSelections = array_merge(array('Select a Service'=>''), getServiceSelections());
	$fields = getClientServiceFields();
	$daysToShow = $_SESSION['preferences']['clientScheduleMakerDays'] ? $_SESSION['preferences']['clientScheduleMakerDays'] : 6;
	$fieldLimit = $fieldLimits[$daysToShow] ? $fieldLimits[$daysToShow] : 30;
	echo "<table class='sortableListCell'>";
	$showHelp = "<img src='art/help.jpg' style='width:30px;height:30px;cursor:pointer;' onclick='showHelp()'>";
	echo "<tr><th>$showHelp</th><th>Label</th><th>Service</th></tr>";
	//for($i=1; $i<=$maxCustomFields; $i++) {
	for($i=1; $i<=count($fields)+10; $i++) {
		if($fields["client_service_$i"][1] && !array_key_exists($fields["client_service_$i"][1], $activeServiceNames)) {
			$rowClass = "class='highlightyellow'";
			$defectiveServices[] = $fields["client_service_$i"];
		}
		else $rowClass = '';
		echo "<tr $rowClass><td>Client Service #".($i)."</td><td>";
		labeledInput('', "label_$i", $fields["client_service_$i"][0], null, '', '', $fieldLimit);
		echo "</td><td>";
		selectElement('', "servicecode_$i", $fields["client_service_$i"][1], $serviceSelections);
		echo "</td></tr>";
	}
	echo "</table>";
	if($defectiveServices) {
		$_SESSION['user_notice'] = 
			"<h2>Please Update The Client Services List</h2><span class='fontSize1_3em'>"
			."The client services highlighted in <span class='highlightyellow'>yellow</span> on this page refer to service types that are now inactive.<p>"
			."Please select a new service for each marked client service<p><b>or</b><p>"
			."Clear the label of the marked service to remove it from the client service list.<p>"
			."Remember to click <b>Save Client Services</b> when you are done.</span>";
	}
}

