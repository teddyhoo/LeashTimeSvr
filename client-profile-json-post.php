<? // client-profile-json-post.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-profile-request-fns.php";

if(userRole() == 'c') $locked = locked('c-', $noForward=true, $exitIfLocked=false);
else $locked = locked('o-', $noForward=true, $exitIfLocked=false);
if($locked) {
	$error = 'Locked.';
	if(!userRole()) $error .= " Not logged in.";
	else if(userRole() != 'c') $error = " Not logged in.as client.";
	header("Content-type: application/json");
	echo json_encode(array('error'=>$error));
	exit;
}

// action = fetch|update|submit|drop|custom|fetchcurrent
//  "custom" action returns custom field descriptions only (can be used instead of asking for customdetails (see below) in every fetch

// modes: form POST or JSON POST
// in JSON mode, parameters below are fields in the JSON object

// parameters
// profileptr = *optional*
// details = *optional* array of field/value pairs
// options = *optional*  {"fulldetails":"true"} - fulldetails = detail values returned as full arrays rather than strings
// options = *optional*  {"customdetails":"true"} - customdetails = include descriptions of the custom fields, in preferred display order

if(userRole() != 'c') {
	$error = "Operation disallowed: Code ".userRole();
	logChange(-99999, 'clientScheduler', 'm', "JSON schedule error: $error");
}

$clientptr = userRole() == 'c' ? $_SESSION['clientid'] : 47; // really SHOULD be userRole c

//echo "[[".file_get_contents('php://input')."]]";exit;
if($_POST['action'] == 'update' && $_FILES) { // PHOTO UPLOAD
//print_r(apache_request_headers()); echo "\n\n"; print_r($_FILES); echo "\n\n"; print_r($_POST); exit;

//echo "[[".print_r($_FILES, 1)."]]";		exit;

	/*
	Expectations:
	1. Each pet section in the client UI will be represented in the json in an array labeled "pets".
	2. Each pet will be a dictionary with 
		  sequence (a number representing its position in the UI list)
		  petid (the pet's ID in LT or null for new pets)
		  dropphoto - *optional* boolean indicating photo should be dropped from pet profile 
		  a set of properties to be changed
		  
	3. Assuming the photo will be uploaded using the normal URL-encoded multipart post mechanism, 
			the name of the form element for the posted photo file will be "photo_{$sequence}"
			when a pet photo is uploaded, the file will be stashed in "fromClient/temp{$clientptr}/{$pet['sequence']}.$extension"
	4. When a client wishes to "revert" an uploaded file, the file stashed earlier will be deleted.
	*/

	// upload a new photos
	require_once "pet-fns.php";
	// IGNORE $_POST['sequence'];
	foreach($_FILES as $key => $file) {
		$sequence = explode('_', $key);
		if($sequence[0] != 'photo') continue;
		$sequence = $sequence[1];
		
		if($uploadResult = uploadClientPhotoNumber($sequence, "temp{$clientptr}"))
			$uploadErrors[] = $uploadResult;
		else {
			$extension = strtolower(substr($_FILES["photo_$sequence"]['name'], strrpos($_FILES["photo_$sequence"]['name'], '.')+1));
			$photoName = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/temp{$clientptr}_$sequence.$extension";
			$details = array(array("field"=>"photoupload_$sequence", "value"=> globalURL($photoName)));
			$result['changes'] += updateTempClientProfile(null, $details, $clientptr);
			$result['uploadedphotos'][] = array("sequence"=>"$sequence", "url"=> globalURL($photoName));
		}
	}
		
	if($uploadErrors) 
		$error = "Failed to upload images: ".join("\n", $uploadErrors);
}

else if(!$error) {
	if($_REQUEST['TEST']) $json = '{"action":"update", "profileptr":"", "details":[{"field":"garagegatecode","value":"29"]}';
	else $json = file_get_contents('php://input');
	
	// Convert all dates in json to db-friendly format
	if($json) $toDo = json_decode($json, 'assoc');
	else $toDo = $_REQUEST;
	if(!$toDo) echo "Nothing toDo!";
	if($toDo['action'] == 'update' || $toDo['action'] == 'submit') {
		$result['changes'] = updateTempClientProfile($toDo['profileptr'], $toDo['details'], $clientptr);
	}
	else if($toDo['action'] == 'fetch') {
		$result['profile'] = fetchTempClientProfile($clientptr, $toDo['options']); // fulldetails, customdescriptions
//echo "BOING! [[".print_r($result, 1)."]]";
	}
	else if($toDo['action'] == 'fetchcurrent') {
		require_once "field-utils.php";
		$result['profile'] = fetchCurrentClientProfile($clientptr);
//echo "BOING! [[".print_r($result, 1)."]]";
	}
	else if($toDo['action'] == 'custom') {
		$result['profile'] = array();
		addCustomDescriptions($result['profile']);
//echo "BOING! [[".print_r($result, 1)."]]";
	}
	if($toDo['action'] == 'submit')
	  // build and submit profilechangerequest
	  $result = createClientRequestFromTempProfile($clientptr);
	else if($toDo['action'] == 'drop')
		if(!dropTempClientProfile($clientptr))
			$error = "Could not drop temp profile for $clientptr";
}
if(FALSE && $_REQUEST['redirect']) {
	globalRedirect($_REQUEST['redirect']);
	exit;
}
header("Content-type: application/json");
if($error) echo json_encode(array('error'=>$error));
else {
	$result['success'] = true;
	echo json_encode($result);
}

// /////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////////////////////////////////////////////////////
// CODE TO RETURN THE CURRENT PROFILE
// /////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////////////////////////////////////////////////////

function fetchCurrentClientProfile($clientid) {
	// client address: street, city, state, zip
	// client email
	// client alt email
	// cell phone number
	// home phone number
	// work phone number
	// alt phone
	// garage/gate code
	// alarm company
	// alarm company phone
	// alarm info
	static $showkeydescriptionnotkeyid;
	if(!$showkeydescriptionnotkeyid) {
		$showkeydescriptionnotkeyid = 
			fetchRow0Col0("SELECT value FROM tblpreference where property= 'mobileKeyDescriptionForKeyId' LIMIT 1", 1);
		$showkeydescriptionnotkeyid = $showkeydescriptionnotkeyid ? 'Yes' : 'No';
	}

	// leashloc, flags, parking,foodloc,nokeyrequired, NO directions
	$suppresscontactinfo = fetchRow0Col0("SELECT value FROM tblpreference where property= 'suppresscontactinfo' LIMIT 1", 1);
	$contactInfoFields = $suppresscontactinfo ? '': 'email, email2, cellphone, cellphone2, workphone, homephone,';

	$client = fetchFirstAssoc(
		"SELECT
				clientid,
				CONCAT_WS(' ', fname, lname) as clientname,
				CONCAT_WS(', ', lname, fname) as sortname,
				lname, fname,
				lname2, fname2,
				street1, street2, city, state, zip,
				$contactInfoFields
				garagegatecode,
				alarmcompany, alarmcophone, alarminfo,
				clinicptr, vetptr, notes,
				leashloc, directions, parkinginfo, foodloc, nokeyrequired
			FROM tblclient
			WHERE clientid = '$clientid'
			LIMIT 1", 1);
			
	// iOS app needs a phone number in a particular format, with dashes
	// if number has 7, 10, or 11 digits (11 where there is a "1" prefix)
	// canonicalize the phone number

	$phoneFields = explode(',', 'cellphone,cellphone2,workphone,homephone,alarmcophone');
	foreach($phoneFields as $k) 
		$client[$k] = canonicalPhoneNumber($client[$k]);
	
	$clientKey = fetchFirstAssoc("SELECT keyid, description FROM tblkey WHERE clientptr = '$clientid' LIMIT 1");
	if($clientKey) {
		$client['keyid'] = sprintf("%04d", $clientKey['keyid']);
		$client['keydescription'] = $clientKey['description'];
		$client['showkeydescriptionnotkeyid'] = $showkeydescriptionnotkeyid;
	}
	else $client['keyid'] = 'NO KEY';
	
	// veterinary clinic
	if($client['clinicptr']) {
		$rawclinic = fetchFirstAssoc("SELECT * FROM tblclinic WHERE clinicid = {$client['clinicptr']} LIMIT 1", 1);
		if($rawclinic) {
			// address, city, state, zip
			$client['clinicname'] = $rawclinic['clinicname'];
			$client['clinicstreet1'] = $rawclinic['street1'];
			$client['clinicstreet2'] = $rawclinic['street2'];
			$client['cliniccity'] = $rawclinic['city'];
			$client['clinicstate'] = $rawclinic['state'];
			$client['cliniczip'] = $rawclinic['zip'];
			$client['clinicphone'] = canonicalPhoneNumber($rawclinic['officephone']);
			//$coords = getLatLonForArray($rawclinic);
		// clinic lat
		// clinic lon
			if($coords) {
				$client['cliniclat'] = $coords['lat'];
				$client['cliniclon'] = $coords['lon'];
			}
		}
	}
	
	$emergencyContacts = fetchAssociationsKeyedBy("SELECT * FROM tblcontact WHERE clientptr = $clientid", 'type');
	$noEmergencyContactInfo = $_SESSION['preferences']['suppressEmergencyContactinfo'];
	if($noEmergencyContactInfo) $emergencyContacts = array();
	
	foreach(array('emergency', 'neighbor') as $type) {
		$contact = $emergencyContacts[$type];
		foreach(explode(',', 'name,location,homephone,workphone,cellphone,note,haskey') as $field)
			$contact[$field] = $contact[$field] ? $contact[$field] : "";
		$contact['haskey'] = $contact['haskey'] ? "Yes" : "No";
		unset($contact['clientptr']);
		unset($contact['contactid']);
		unset($contact['type']);
		$client[$type] = array_merge($contact);
	}
		

	// veterinarian name
	// veterinarian clinic
	// veterinarian address, city, state, zip
	// veterinarian phone number
	// veterinarian lat
	// veterinarian lon
	if($client['vetptr']) {
		$rawvet = fetchFirstAssoc("SELECT * FROM tblvet WHERE vetid = {$client['vetptr']} LIMIT 1", 1);
		if($rawvet) {
			// address, city, state, zip
			$client['vetname'] = "{$rawvet['fname']} {$rawvet['lname']}";
			$client['vetstreet1'] = $rawvet['street1'];
			$client['vetstreet2'] = $rawvet['street2'];
			$client['vetcity'] = $rawvet['city'];
			$client['vetstate'] = $rawvet['state'];
			$client['vetzip'] = $rawvet['zip'];
			$client['vetphone'] = canonicalPhoneNumber($rawvet['officephone']);
			//$coords = getLatLonForArray($rawvet);
		// clinic lat
		// clinic lon
			if($coords) {
				$client['vetlat'] = $coords['lat'];
				$client['vetlon'] = $coords['lon'];
			}
		}
	}
	
	// client custom fields [all w/sitter rights] 	
	if(($fields = customClientFields($fields)) != -999) {
//if(mattOnlyTEST()) {print_r($fields);exit;}		
		$fieldCounter = 0;
		foreach($fields as $fkey => $field) {
			$fieldvalue = getClientCustomField($client['clientid'], $fkey);
			$fieldvalue = nativeCustomFieldValue($field, $fieldvalue);
			// override database custom field key for the app's sake
			$fieldCounter += 1;
			if(TRUE) $finalkey = "custom$fieldCounter";
			$client[$finalkey] = array('label'=>$field[0], 'value'=>$fieldvalue);
			$client[$finalkey]['serverkey'] = $fkey;
		}
	}
	
	// hasKey Y|N, key ID etc taken car of elsewhere
	
	$pets = fetchAssociations("SELECT * FROM tblpet WHERE ownerptr = $clientid AND active = 1 ORDER BY name", 1);
	foreach($pets as $i => $pet) {
		$p = array();
		$sequence = $i + 1;
		$p['sequence'] = $sequence;
		$p['petid'] = $pet['petid'];
		$p['name'] = $pet['name'];
		$p['type'] = $pet['type'];
		$p['breed'] = $pet['breed'];
		$p['sex'] = $pet['sex'];
		$p['color'] = $pet['color'];
		$p['fixed'] = $pet['fixed'] ? 'Yes' : 'No';
		if($pet['dob']) $p['birthday'] = shortNaturalDate(strtotime($pet['dob']));
		$p['description'] = $pet['description'];
		$p['notes'] = $pet['notes'];
		if(($fields = customPetFields()) && $fields != -999) {
			$fieldCounter = 0;
			foreach($fields as $fkey => $field) {
				$fieldvalue = getPetCustomField($pet['petid'], $fkey);
				$fieldvalue = nativeCustomFieldValue($field, $fieldvalue);
				if(!$fieldvalue && $field[2] == 'boolean') $fieldvalue = "0";
				$fieldCounter += 1;
				if(TRUE) $finalkey = "petcustom$fieldCounter";
				$p[$finalkey] = array('label'=>$field[0], 'value'=>$fieldvalue);
				$p[$finalkey]['serverkey'] = $fkey;
			}
		}
		$client['pets'][$sequence] = $p;
	// pet name
	// pet type
	// breed
	// color
	// fixed
	// birthday
	// description
	// pet notes
	// pet custom fields [all w/sitter rights]
	}
	return $client;
}

function nativeCustomFieldValue($field, $fieldvalue) {
	global $clientdocs, $loginid, $password;
	if(!$fieldvalue && $field[2] == 'boolean') $fieldvalue = "0";
		if($fieldvalue && $field[2] == 'file') {
			require_once "remote-file-storage-fns.php";
			require_once 'aws-autoloader.php';
			require_once 'encryption.php';

			$remoteFileId = $fieldvalue;
			if(!($validRemoteFileId = is_numeric($remoteFileId) && $remoteFileId > 0 && $remoteFileId == round($remoteFileId))) {
				//$label = 'File missing: ['.truncatedLabel($custValue, 10).']';
				$fname = null;
			}
			else $fname = fetchRow0Col0("SELECT remotepath FROM tblremotefile WHERE remotefileid = '$fieldvalue' LIMIT 1",1);
			if($fname) {
				$fileDescription = remoteObjectDescription(absoluteRemotePath($fname));
				$zumzum = urlencode(lt_encrypt(json_encode(array('id'=>$fieldvalue, 'loginid'=>$loginid, 'password'=>$password))));

				$json = array(
					'label'=>basename($fname), 
					'url' => globalURL("client-file-view.php?zumzum=$zumzum"),
					'mimetype'=>$fileDescription['ContentType']
					);
				if($clientdocs == 'complete') $fieldvalue = $json; //json_encode();
				else $fieldvalue = $json['label'];
//if(mattOnlyTEST()) {echo print_r($clientdocs,1).": ".print_r($fieldvalue,1);exit;}	
			}
		}
	return $fieldvalue;
}

function getLatLonForArray($arr) {
	global $dbhost, $db, $dbuser, $dbpass;
	// find location and add it to local list
	if(is_string($arr)) $addr = $arr;
	else {
		if($arr) $arr = array_map('trim', $arr);
		if($arr['city'] && $arr['state'] ) $addr = "{$arr['street1']}, {$arr['city']}, {$arr['state']}";
		else if($arr['street1'] && $arr['zip'])	$addr = trim("{$arr['street1']} {$arr['zip']}");
	}
	return getLatLon($addr);
}

/*function getLatLonForArray($arr) {
	global $dbhost, $db, $dbuser, $dbpass;
	// find location and add it to local list
	if(is_string($arr)) $addr = $arr;
	else if($arr['city'] && $arr['state'] ) $addr = trim("{$arr['street1']}, {$arr['city']}, {$arr['state']}");
	else if($arr['street1'] && $arr['zip'])	$addr = trim("{$arr['street1']} {$arr['zip']}");
	static $map;
	if(!$map) {
		$map = new GoogleMapAPI('xyz');
		$map->setDSN("mysql://$dbuser:$dbpass@$dbhost/$db");
		$map->_db_cache_table = 'geocodes';
	}
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	$loc = $map->getGeocode($addr);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1); 
	return $loc;
}*/

function customPetFields() {
	require_once "custom-field-fns.php";
	static $fields;
	$visitSheetOnly = !fetchPreference('showAllCustomFieldsInMobileSitterApps');
	if(!$fields) $fields = displayOrderCustomFields(getCustomFields($activeOnly=true, $visitSheetOnly, getPetCustomFieldNames()), 'petcustom');
	if(!$fields) $fields = -999;
	return $fields;
}

function customClientFields() {
	require_once "custom-field-fns.php";
	static $fields;
	$visitSheetOnly = !fetchPreference('showAllCustomFieldsInMobileSitterApps');
	if(!$fields) $fields = displayOrderCustomFields(getCustomFields($activeOnly=true, $visitSheetOnly), 'custom');
	if(!$fields) $fields = -999;
	return $fields;
}

function customFieldsReorderedForNative($fields, $prefix) { // UNUSED
	$displayOrderedFields = displayOrderCustomFields($fields, $prefix);
	foreach($displayOrderedFields as $k=>$v) {
		$i = $i+1;
		$out["$prefix$i"] = $v;
	}
	return $out;
}
		
function canonicalPhoneNumber($value) {
	// assume all phone numbers are US phone numbers
	// modify this function later for non-US use of the iOS app
	return canonicalUSPhoneNumber($value);
}

function createClientRequestFromTempProfile($clientptr) {
		/*
		Expectations:
		1. Each pet section in the client UI will be represented in the json in an array labeled "pets".
		2. Each pet will be a dictionary with 
			  sequence (a number representing its position in the UI list)
			  petid (the pet's ID in LT or null for new pets)
			  dropphoto - *optional* boolean indicating photo should be dropped from pet profile 
			  a set of properties to be changed
			  
		3. Assuming the photo will be uploaded using the normal URL-encoded multipart post mechanism, 
				the name of the form element for the posted photo file will be "photo_{$sequence}"
				when a pet photo is uploaded, the file will be stashed in "fromClient/temp{$clientptr}/{$pet['sequence']}.$extension"
		4. When a client wishes to "revert" an uploaded file, the file stashed earlier will be deleted.
		*/
	require_once "request-fns.php";
	require_once "custom-field-fns.php";
	$currentProfile = fetchCurrentClientProfile($clientptr);
	$currentPetsInSequence = $currentProfile['pets'];
	$changeList = fetchTempClientProfile($clientptr, $toDo['options']); // fulldetails, customdescriptions
	
	$changeList['details']['version'] = 2; // should Iadvance this as a hint the request came from JSON?
	// collect pet sequence numbers and connect them with petids
	if($details = $changeList['details'])
		foreach((array)($details['pets']) as $petChange)
			 $petIdsBySequence[$petChange['sequence']] = $currentPetsInSequence[$petChange['sequence']]['petid'];
//return array('success'=>true, 'testresult'=>$changeList);;	
			
	if($changeList) {
		$requestId = createProfileChangeRequestFromJSON($clientptr);
	}
	$TEST = true;
	if(!$TEST) {
		dropTempClientProfile($clientptr);
	}
	return array('success'=>true, 'requestid'=>$requestId);
}