<? // client-own-edit-json.php
// $clientPetContext: null (all) or 'client' or 'pet_{petid}' or 'pet_new'
require_once "js-gui-fns.php";
require_once "preference-fns.php";
require_once "contact-fns.php";
require_once "client-profile-request-fns.php";

if(userRole() != 'c') {
	echo "This page is for clients only.  You are not logged in as a client.";
	exit;
}
else if(userRole() == 'o') locked('o-');


$_SESSION["preferences"] = fetchPreferences();

extract($_REQUEST);
// $clientPetContext is set in the line above

$id = $_SESSION["clientid"];

$client = getClient($id);

$payload['clientid'] =$client['clientid'];
$payload['vetname'] = $client['vetptr'] ? nameOf(getVet($client['vetptr'])) : '';
$payload['clinicname'] = $client['clinicptr'] ? nameOf(getClinic($client['clinicptr'])) : '';
$payload['booleanFields'] = array('active', 'prospect');
if(!$clientPetContext || ($clientPetContext == 'client')) { // START CLIENT PROFILE EDIT PART 1
	// BASIC SECTION
	$raw = explode(',', "$rawBasicFields");
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	$noContactInfo = $_SESSION['preferences']['suppresscontactinfo'] && userRole() == 'p';
	if($noContactInfo) {
		foreach(explode(',', 'email,email2,homephone,cellphone,cellphone2,workphone,homephone,fax,pager') as $x)
			unset($fields[$x]);
	}

	foreach($fields as $key => $label) {
		$payload['fieldLabels'][$key] = $label;
		$val = isset($client[$key]) ? $client[$key] : '';
		//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
		$payload[$key] =$val;
	}

	addAddress($payload, '', $client);
	addAddress($payload, 'mail', $client);
} // // END CLIENT PROFILE EDIT PART 1

require_once "custom-field-fns.php";
$customFieldDescriptions = getCustomFields($activeOnly=true, $visitSheetOnly, null, $clientVisibleOnly);
$customFieldDescriptions = displayOrderCustomFields($customFieldDescriptions, 'custom');

if(!$names = getPetCustomFieldNames()) return;
$customPetFieldDescriptions = getCustomFields($activeOnly=true, $visitSheetOnly, $names, $clientVisibleOnly);
$customPetFieldDescriptions = displayOrderCustomFields($customPetFieldDescriptions, 'petcustom');

if(!$clientPetContext || strpos($clientPetContext, 'pet_') === 0) { // START PET PROFILE EDIT
	$payload['pets'] = array();
	
	foreach(getClientPets($payload['clientid']) as $pet) {
		if(!$pet['active']) continue;
		$payload['pets'][] = petSection($pet);
	}
} // END PET PROFILE EDIT
if(!$clientPetContext || ($clientPetContext == 'client')) { // START CLIENT PROFILE EDIT PART 2

	$raw = explode(',', $homeFields);
	$fields = array();
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	foreach($fields as $key => $label) {
		$payload['fieldLabels'][$key] = $label;
		$val = isset($client[$key]) ? $client[$key] : '';
		if(in_array($key, array('active', 'prospect'))) {
			checkboxRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
		}
		$payload[$key] = $val;
	}

	$contacts = getKeyedClientContacts($id);
	if(isset($contacts['emergency'])) $payload['contacts']['emergency'] = getContact($contacts['emergency']);
	if(isset($contacts['neighbor'])) $payload['contacts']['neighbor'] = getContact($contacts['neighbor']);

	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<tr><td>CUSTOM: ".print_r(getCustomFields('active', !'visitsheetonly', null, 'clientvisibleonly'),1); }
	$clientvisibleonly = userRole() == 'c';
	$visitsheetonly = userRole() == 'p';
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<tr><td>CUSTOM: visitsheetonly: [$visitsheetonly] clientvisibleonly: [$clientvisibleonly]"; }
	$payload['custom'] = customClientFields($payload, $visitSheetOnly=true, $clientVisibleOnly=true) ;
	
}// END CLIENT PROFILE EDIT PART 2
$payload['booleanFields'] = array_unique($payload['booleanFields']);

foreach($customFieldDescriptions as $i => $descr) {
	$customFieldDescriptions[$i] = 
	array(
		'label'=>$descr[0],
		'active'=>$descr[1],
		'type'=>$descr[2],
		'sitterVisible'=>$descr[3],
		'clientVisible'=>$descr[4]);
}
$payload['clientCustomFieldDescriptions'] = $customFieldDescriptions;

foreach($customPetFieldDescriptions as $i => $descr) {
	$customFieldDescriptions[$i] = 
	array(
		'label'=>$descr[0],
		'active'=>$descr[1],
		'type'=>$descr[2],
		'sitterVisible'=>$descr[3],
		'clientVisible'=>$descr[4]);
}
$payload['petCustomFieldDescriptions'] = $customPetFieldDescriptions;

echo json_encode($payload);

// *********************************************************
function addAddress(&$parent, $prefix, $source) {
	global $payload;
	$raw = explode(',', 'street1,Address,street2,Address 2,city,City,state,State,zip,ZIP');  
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	echo "<tr><td>$label</td></tr>";
	foreach($fields as $base => $label) {
		$key = $prefix.$base;
		$payload['fieldLabels'][$key] = $label;
		$parent[$key] = $source[$key];
	}
}

function petSection($inpet) {
	global $payload, $petFields;
	$payload['booleanFields'][] = 'active';
	$payload['booleanFields'][] = 'fixed';
	$sexes =  array('m'=>'Mail', 'f'=>'Female');
	$pet = array('petid'=>$inpet['petid']);
	foreach($petFields as $field => $label) {
		$payload['fieldLabels'][$field] = $label;
    $val = $inpet[$field];
    if($field == 'sex') $val = $sexes[$val];
		$pet[$field] = $val		;
	}
	if($inpet['photo']) $pet['photo'] = "pet-photo.php?id={$inpet['petid']}&version=display";

	if($_SESSION['custom_pet_fields_enabled']) {
		$pet['custom'] = customPetFields($pet, $visitSheetOnly=true, $clientVisibleOnly=true);
	}
	return $pet;
}

function customPetFields(&$pet, $visitSheetOnly=true, $clientVisibleOnly=true) {
	global $payload,$customPetFieldDescriptions;
	require_once "custom-field-fns.php";
	$petValues = getPetCustomFields($pet['petid'], 'raw');
	$customFields = array();
	foreach($customPetFieldDescriptions as $name => $descr) {
		$payload['fieldLabels'][$name] = $descr[0];
		$customFields[$name] = $petValues[$name];
		if($descr[2] == 'boolean') $payload['booleanFields'][] = $name;
	}
	return $customFields;
}

function customClientFields(&$client, $visitSheetOnly=true, $clientVisibleOnly=true) {
	global $payload,$customFieldDescriptions;
//print_r($cFields);	
	$cValues = getClientCustomFields($client['clientid'], 'raw');
	$customFields = array();
	foreach($customFieldDescriptions as $name => $descr) {
		$payload['fieldLabels'][$name] = $descr[0];
		$customFields[$name] = $cValues[$name];
		if($descr[2] == 'boolean') $payload['booleanFields'][] = $name;
	}
	return $customFields;
}

function petAge($petOrDate) {
	$bday = is_array($petOrDate) ? $petOrDate['dob'] : $petOrDate;
	if(!$bday) return '';
	$bday = date('Y-m-d', strtotime($bday));	
	$delta = strtotime(date('Y-m-d')) - strtotime($bday);
	$days = $delta/(24 * 60 * 60);
	if($days > 365) {
    list($Y,$m,$d)    = explode("-",$bday);
    return( date("md") < $m.$d ? date("Y")-$Y-1 : date("Y")-$Y ).' years';
	}
	return floor($days / 7).' weeks.';
}

function getContact(&$contact) {
	global $contactFields, $payload;
	$payload['fieldLabels']['neighbor'] = 'Trusted Neighbor';
	$payload['fieldLabels']['emergency'] = 'Emergency Contact';
	$payload['booleanFields'][] = 'haskey';	
	$typeLabel = $payload['fieldLabels'][$type];
	return $contact;
}

