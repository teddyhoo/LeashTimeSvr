<? // native-prov-multiday-list.php
require_once "common/init_session.php";
require_once "native-sitter-api.php";
require_once "key-fns.php";
require_once "client-flag-fns.php";
require_once "field-utils.php"; // for canonicalUSPhoneNumber
//require_once('GoogleMapAPIv3.php');
require_once "google-map-utils.php";

$t0 = microtime(1);

/*authenticate loginid/password

    on failure, return a single character:
        P - bad password
        U - unknown user
        I - inactive user
        F - No Business Found
        B - Business Inactive
        M - Missing Organization
        O - Organization inactive
        R - rights are missing or mismatched
        C - No cookie
        L - account locked
        S - not a sitter
        
        //https://leashtime.com/native-prov-multiday-list.php?loginid=dlifebri&password=QVX992DISABLED&start=2014-12-13&end=2014-12-18
*/

extract(extractVars('loginid,password,start,end,notprov,clientdocs', $_REQUEST));
//extract(extractVars('loginid,password,date', $_GET));


if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
	echo $userOrFailure;
	exit;
}
	
$user = $userOrFailure;
if(strpos($user['rights'], 'p') !== 0) {
	echo "S";
	exit;
}
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
$scripStart = microtime(1);
timing("Auth");


logScriptCallForThisDB();



$provid = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");


if(!$provid) echo "ERROR: No provider found for user {$user['userid']}<p>";
if(!$start || !strtotime($start)) echo "ERROR: Bad start parameter [$start]<p>";
if(!$end || !strtotime($end)) echo "ERROR: Bad end parameter [$end]<p>";
$start = date('Y-m-d', strtotime($start));
$end = date('Y-m-d', strtotime($end));

require_once "appointment-fns.php";

//$visits = daysVisits($provid, $date, $keys=null, $json=false);
$searchFor = $notprov ? $notprov : $provid;
$JSONTEST = true; //mattOnlyTEST() ? false : true;
$visits = multidaysVisits($searchFor, $start, $end, $keys=null, $json=FALSE);

}

if(TRUE || dbTEST('careypet')) {
// for visits with no note, get package notes
// turn this on and test when quiet
if($visits && fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mobileDetailedListVisit' LIMIT 1", 1)) {
	timing("Preference fetch");
	$displayNoteLength = 65;
	require_once "service-fns.php";
	$packs = array();
/*	foreach($visits as $appt) 
		if(!$appt['note']) 
			$packs[$appt['packageptr']] = $appt;
*/
	$ignoreNotes = array("[START]","[FINISH]", "[START][FINISH]");
	foreach($visits as $i => $appt) {
		
		// clear note if it is only "[START]","[FINISH]", or "[START][FINISH]"
		$visits[$i]['note'] = 
			$appt['note'] && in_array(strtoupper(str_replace("\r", '', str_replace("\n", '', $appt['note']))), $ignoreNotes)
				? '' 
				: $appt['note'];
		
		
		if(!$visits[$i]['note']) 
			$packs[$appt['packageptr']] = $appt;
	}

	$packnotes = array();
	foreach((array)$packs as $packageptr => $appt) {
		$curr = findCurrentPackageVersion($packageptr, $appt['clientptr'], $appt['recurringpackage']);
}
		$latest[$packageptr] = $curr;
		if($curr && !isset($packnotes[$curr])) {
			$table = $appt['recurringpackage'] ? 'tblrecurringpackage' : 'tblservicepackage';
			$packnotes[$curr] = fetchRow0Col0("SELECT notes FROM $table WHERE packageid = $curr LIMIT 1");
		}
	}

	foreach($visits as $i => $appt) {
		if(!$appt['note']) 
			$visits[$i]['note'] = $packnotes[$latest[$appt['packageptr']]];
		unset($visits[$i]['packageptr']);
		unset($visits[$i]['recurringpackage']);
	}
	timing("Pack notes fetch");
}
}
} // if TRUE


$allBusinessFlags = getBusinessFlags();
timing("getBusinessFlags");
$clients = array();
foreach($visits as $visit) $clients[$visit['clientptr']] = null;
foreach(getProviderKeys($provid) as $provKey) $providerKeys[$provKey['clientptr']] = $provKey;
timing("getProviderKeys");
foreach($clients as $clientid => $unused) {
	$clients[$clientid] = populateClient($clientid);
	timing("populateClient(total)", 'cumulative');
	if($provKey = $providerKeys[$clientid]) {
		$clients[$clientid]['hasKey'] = 'Yes';
	}
	foreach(getClientFlags($clientid, $officeOnly=true) as $cflag) {
		timing("getClientFlags(total)", 'cumulative');
		unset($cflag['officeOnly']);
		unset($cflag['src']);
		unset($cflag['title']);
		$cflag['note'] = $cflag['note'] ? $cflag['note'] : "";
		$clients[$clientid]['flags'][] = $cflag;
		$usedBusinessFlagIds[] = $cflag['flagid'];
	}

}


$preferences =array();
$providersScheduleRetrospectionLimit =
	fetchRow0Col0("SELECT value FROM tblpreference where property= 'providersScheduleRetrospectionLimit' LIMIT 1", 1);
if($providersScheduleRetrospectionLimit) {
	$earliestDateAllowed = date('Y-m-d', strtotime("-$providersScheduleRetrospectionLimit days", strtotime(date('Y-m-d'))));
	$preferences['earliestDateAllowed'] = $earliestDateAllowed;
}
$earlyArrivalMarkingLimit =
	fetchRow0Col0("SELECT value FROM tblpreference where property= 'earlyArrivalMarkingLimit' LIMIT 1", 1);
if(!$earlyArrivalMarkingLimit) $earlyArrivalMarkingLimit = 9999;
$preferences['earlyArrivalMarkingLimit'] = $earlyArrivalMarkingLimit;
$preferences['allowConcurrentArrivals'] = 
	fetchRow0Col0("SELECT value FROM tblpreference where property= 'allowConcurrentArrivals' LIMIT 1", 1);
$preferences['allowConcurrentArrivals'] = $preferences['allowConcurrentArrivals'] ? '1' : '0';


timing("Misc preferences");
	

$output['preferences'] = $preferences;

$output['flags'] = $allBusinessFlags;
$output['clients'] = $clients;
$output['visits'] = $visits;

// KLUDGE Start
if(FALSE && strpos($_SESSION['userAgent'], 'LEASHTIME IOS') !== FALSE) {  // LEASHTIME IOS 11 / ver: 3.63 / build:6
	foreach($output as $k => $v)
		if(is_string($v)) $output[$k] = str_replace("\n", "  ", $v);
}

if(mattOnlyTEST() /*&& strpos($_SESSION['userAgent'], 'LEASHTIME IOS') !== FALSE*/) {
	recursiveReplace("\n", '  ', $output, $level=0);
}

function recursiveReplace($pattern, $replacement, &$target, $level=0) { // clients = level 0, client = level 1
	foreach($target as $k => $v) {
		//echo "[level $level] $k<br>";
		if(is_array($v) && $v)
			recursiveReplace($pattern, $replacement, $target[$k], $level+1);
		else if($level == 2 && $k == 'notes') continue; // ignore client notes
		else if(is_string($v))
			$target[$k] = str_replace("\n", "  ", $v);
	}
}
		
// KLUDGE End


}
if(mattOnlyTEST()) $times['TOTAL BEFORE json_encode'] = "$prefix: _".(microtime(1)-$scripStart)."_ sec.";
if($times) $output['times'] = $times;

if(!$_REQUEST['debug']) header("Content-type: application/json");

echo json_encode($output);

if($_REQUEST['debug']) echo 'TOTAL INCLUDING json_encode '.(microtime(1)-$scripStart)."_ sec.";

if($_REQUEST['debug']) echo "<hr><pre>".print_r($output, 1)."</pre>";

if(!$DEBUG_NO_QUIT) endRequestSession();

function canonicalPhoneNumber($value) {
	// assume all phone numbers are US phone numbers
	// modify this function later for non-US use of the iOS app
	return canonicalUSPhoneNumber($value);
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
		if($showkeydescriptionnotkeyid == 'Yes' && $_SESSION["isiphone"]) $client['keyid'] = $clientKey['description'];  // iPhone only
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
	$fieldCounter = 1;
	
	if($_SESSION['preferences']['enableLastVisitNote']) {
		$lastNote = lastVisitNote($clientid); // appointment-fns.php // appointmentid, note, providerptr, completed,  sitter
		if(is_array($lastNote)) {
			$completed = $lastNote['mobilecomplete'] 
				? longestDayAndDateAndTime(strtotime($lastNote['mobilecomplete']))
				: longestDayAndDate(strtotime($lastNote['completed']));
			$note = $lastNote['note'] ? $lastNote['note'] : 'No note';
			$sitter = $lastNote['sitter'] ? "Sitter: {$lastNote['sitter']}" : '';
			$payload = array("$completed", $sitter, $note);
			$payload = join(' * ', $payload); // &#9899; = medium black circle
		}
		else $payload = $lastNote;
		$client["custom$fieldCounter"] = array('label'=>'Last Visit', 'value'=>$payload);
		$fieldCounter += 1;
	}

	
	
	
	if(($fields = customClientFields($fields)) != -999) {
}		
		//$fieldCounter = 0;
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
				$fieldvalue = nativeCustomFieldValue($field, $fieldvalue);
				if(!$fieldvalue && $field[2] == 'boolean') $fieldvalue = "0";
				$fieldCounter += 1;
				if(TRUE) $finalkey = "petcustom$fieldCounter";
				$p[$finalkey] = array('label'=>$field[0], 'value'=>$fieldvalue);
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
}	
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
		
		
	