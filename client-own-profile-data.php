<?
// client-own-profile-data.php
// returns a JSON packet for the Pet Owner Portal
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "client-schedule-fns.php";
require_once "pet-fns.php";
require_once "preference-fns.php";
require_once "google-map-utils.php";

// Determine access privs
$locked = locked('c-');

extract($_REQUEST);

$client = populateClient($_SESSION["clientid"]);

if($client) {
	header("Content-type: application/json");
	echo json_encode($client);
	exit;
}

function populateClient($clientid) {
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
			$coords = getLatLonForArray($rawclinic);
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
			$coords = getLatLonForArray($rawvet);
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
		// 0-label, 1-active, 2-type, 3-showOnVisitSheet, 4-clientvisible
//if(mattOnlyTEST()) {print_r($fields);exit;}		
		$fieldCounter = 0;
		foreach($fields as $fkey => $field) {
			if(!$field[1]) continue;
			$fieldvalue = getClientCustomField($client['clientid'], $fkey);
			$fieldCounter += 1;
			$finalkey = "custom$fieldCounter";
			$client[$finalkey] = customFieldAsArray($field, $fieldvalue);
			$client[$finalkey]['serverkey'] = $fkey;
		}
	}
	
	// hasKey Y|N, key ID etc taken car of elsewhere
	
	$pets = fetchAssociations("SELECT * FROM tblpet WHERE ownerptr = $clientid AND active = 1 ORDER BY name", 1);
	foreach($pets as $pet) {
		$p = array();
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
				$fieldCounter += 1;
				$finalkey = "petcustom$fieldCounter";
				$p[$finalkey] = customFieldAsArray($field, $fieldvalue);
				$p[$finalkey]['serverkey'] = $fkey;
			}
		}
		$client['pets'][] = $p;
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
			$fname = fetchRow0Col0("SELECT remotepath FROM tblremotefile WHERE remotefileid = '$fieldvalue' LIMIT 1",1);
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




if(!$json) echo "No info requested.";
else if($test) print_r($json);
else {
	header("Content-type: application/json");
	echo json_encode($json);
}

function fetchClientVisits($start, $end, $includecanceledvisits=null, $includenotes=null, $client=null) {
	$clientid = $client ? $client : $_SESSION["clientid"];
	$rows = array();
	if($clientid && $clientid != -1) $filter[] = "a.clientptr = $clientid";
	if($includecanceledvisits) $filter[] = "a.canceled IS NULL";
	$filter = $filter ? "AND ".join(' AND ', $filter) : '';
	
	if($includenotes) $includenotes = " note,";
	//$formattedFields = explodePairsLine('charge|charge||adjustment|adjustment||rate|rate||bonus|bonus');
	//if(!$csv) foreach($formattedFields as $field) 
	//	$formattedFields[$field] = "IF($field IS NULL, $field, CONCAT_WS(' ', '".getCurrencyMark()."', FORMAT($field, 2))) as formatted$field";
	//$formattedFields = join(', ', $formattedFields);
	//					tblappointment.modified as apptmodified,
	//					tblappointment.created as apptcreated,

	$sql = "SELECT a.date, a.starttime, a.endtime, a.timeofday, a.appointmentid, a.providerptr, a.servicecode,
					a.charge, a.adjustment, a.rate, a.bonus, $includenotes
					IF(recurringpackage, 
						IF(monthly = 1,'fixed price', 'ongoing'),
						'short term') as packagetype,
					IF(completed IS NOT NULL, 'completed', IF(canceled IS NOT NULL, 'CANCELED', 'INCOMPLETE')) AS status,hours,
					IF(hours IS NULL, hours,  FORMAT(TIME_TO_SEC(CONCAT(hours, ':00')) / 3600, 3)) as formattedhours,
					IFNULL(arrivaltrack.date, null) as arrived,
					IFNULL(completiontrack.date, null) as completed,
					label as servicelabel,
					tax,
					vr.value as visitreport
					FROM tblappointment a
					LEFT JOIN tblclient ON clientid = clientptr
					LEFT JOIN tblprovider ON providerid = providerptr
					LEFT JOIN tblservicetype ON servicetypeid = servicecode					
					LEFT JOIN tblrecurringpackage ON packageid = packageptr					
					LEFT JOIN tblgeotrack arrivaltrack ON arrivaltrack.appointmentptr = appointmentid AND arrivaltrack.event = 'arrived'				
					LEFT JOIN tblgeotrack completiontrack ON completiontrack.appointmentptr = appointmentid AND completiontrack.event = 'completed'			
					LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment' AND superseded = 0
					LEFT JOIN tblappointmentprop vr ON vr.appointmentptr = appointmentid AND property = 'reportIsPublic'
					WHERE a.date >= '$start' AND a.date <= '$end' $filter
					ORDER BY date, starttime";
//if(mattOnlyTEST()) {echo $sql;exit;}					

// ADD: sitter, servicelabel, clientservicelabel


	$rows = fetchAssociations($sql, 1);
	if($rows) {
		require_once "client-services-fns.php";
		require_once "provider-fns.php";
		// Find client services that refer uniquely to a service type
		$allCS = getClientServices();
		$clientservicemenu = array();
		foreach($allCS as $label => $stype)
			$stypes[$stype] += 1;
		foreach($allCS as $label => $stype)
			if($stypes[$stype] == 1)
				$clientservicemenu[$stype] = $label;
		foreach($rows as $i => $row) {
			$rows[$i]['clientservicelabel'] = $clientservicemenu[$row['servicecode']];
			$rows[$i]['sitter'] = 
				$row['providerptr'] ? getDisplayableProviderName($row['providerptr'])
				: 'unassigned';
		}
	}
	
	return $rows;
}

function customPetFields() {
	require_once "custom-field-fns.php";
	static $fields;
	if(!$fields) $fields = displayOrderCustomFields(getCustomFields($activeOnly=true, $visitSheetOnly=false, getPetCustomFieldNames()), 'petcustom');
	if(!$fields) $fields = -999;
	return $fields;
}

function customClientFields() {
	require_once "custom-field-fns.php";
	static $fields;
	if(!$fields) $fields = displayOrderCustomFields(getCustomFields($activeOnly=true, $visitSheetOnly=false), 'custom');
	if(!$fields) $fields = -999;
	return $fields;
}

function customFieldAsArray($field, $fieldvalue) {
	$fieldvalue = nativeCustomFieldValue($field, $fieldvalue);
	$clientVisibleFieldsModEnabled = $_SESSION['preferences']['enableClientVisibleCustomFields'];
	$hidden = !$field[3] || ($clientVisibleFieldsModEnabled && !$field[4]);
	return array('label'=>$field[0], 'value'=>$fieldvalue, 'type'=>$field[2], 'hidden'=>$hidden);
}	

function canonicalPhoneNumber($value) {
	// assume all phone numbers are US phone numbers
	// modify this function later for non-US use of the iOS app
	return canonicalUSPhoneNumber($value);
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
