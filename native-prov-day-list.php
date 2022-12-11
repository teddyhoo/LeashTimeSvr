<? // native-prov-day-list.php
require_once "common/init_session.php";
require_once "native-sitter-api.php";
require_once "key-fns.php";
require_once "client-flag-fns.php";
require_once "google-map-utils.php";

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
        
        //https://leashtime.com/native-prov-day-list.php?loginid=dlifebri&password=QVX992&date=2014-12-13
*/

extract(extractVars('loginid,password,date', $_REQUEST));
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

$provid = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");


if(!$provid) echo "ERROR: No provider found for user {$user['userid']}<p>";


$date = date('Y-m-d', ($_REQUEST['date'] ? strtotime($_REQUEST['date']) : time()));

require_once "appointment-fns.php";

$visits = daysVisits($provid, $date, $keys=null, $json=false);
$allBusinessFlags = getBusinessFlags();
$clients = array();
foreach($visits as $visit) $clients[$visit['clientptr']] = null;
foreach(getProviderKeys($provid) as $provKey) $providerKeys[$provKey['clientptr']] = $provKey;
foreach($clients as $clientid => $unused) {
	$clients[$clientid] = populateClient($clientid);
	if($provKey = $providerKeys[$clientid]) {
		$clients[$clientid]['hasKey'] = 'Yes';
	}
	foreach(getClientFlags($clientid, $officeOnly=true) as $cflag) {
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
$output['preferences'] = $preferences;

$output['flags'] = getBusinessFlags($usedBusinessFlagIds);
$output['clients'] = $clients;
$output['visits'] = $visits;


if(!$_REQUEST['debug']) header("Content-type: application/json");

echo json_encode($output);
if($_REQUEST['debug']) echo "<hr><pre>".print_r($output, 1)."</pre>";

if(!$DEBUG_NO_QUIT) endRequestSession();


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

	$client = fetchFirstAssoc(
		"SELECT
				clientid,
				CONCAT_WS(' ', fname, lname) as clientname,
				CONCAT_WS(', ', lname, fname) as sortname,
				lname, fname,
				lname2, fname2,
				street1, street2, city, state, zip,
				email, email2,
				cellphone, cellphone2, workphone, homephone,
				garagegatecode,
				alarmcompany, alarmcophone, alarminfo,
				clinicptr, vetptr,
				leashloc, parkinginfo, foodloc, nokeyrequired
			FROM tblclient
			WHERE clientid = '$clientid'
			LIMIT 1", 1);
	
	$clientKey = fetchFirstAssoc("SELECT keyid, description FROM tblkey WHERE clientptr = '$clientid' LIMIT 1");
	if($clientKey) {
		$client['keyid'] = $clientKey['keyid'];
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
	// phone number
			$client['clinicphone'] = $rawclinic['officephone'];
			$rawclinic = array_map('trim', $rawclinic);
			if($rawclinic['city'] && $rawclinic['state'] ) $addr = "{$rawclinic['street1']}, {$rawclinic['city']}, {$rawclinic['state']}";
			else if($rawclinic['street1'] && $rawclinic['zip'])	$addr = trim("{$rawclinic['street1']} {$rawclinic['zip']}");
			$coords = getLatLon($addr);
		// clinic lat
		// clinic lon
			if($coords) {
				$client['cliniclat'] = $coords['lat'];
				$client['cliniclon'] = $coords['lon'];
			}
		}
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
			$client['vetphone'] = $rawvet['officephone'];
	// phone number
			$rawvet = array_map('trim', $rawvet);
			if($rawvet['city'] && $rawvet['state'] ) $addr = "{$rawvet['street1']}, {$rawvet['city']}, {$rawvet['state']}";
			else if($rawvet['street1'] && $rawvet['zip'])	$addr = trim("{$rawvet['street1']} {$rawvet['zip']}");
			$coords = getLatLon($addr);
		// clinic lat
		// clinic lon
			if($coords) {
				$client['vetlat'] = $coords['lat'];
				$client['vetlon'] = $coords['lon'];
			}
		}
	}
	
	// client custom fields [all w/sitter rights] 	
	
	if(($fields = customClientFields()) != -999) {
		$sequence = 1;
		foreach($fields as $fkey => $field) {
			$fieldvalue = getClientCustomField($client['clientid'], $fkey);
			$client[$fkey] = array(
					'label'=>$field[0], 
					'value'=>$fieldvalue,
					'type'=>$field[2],
					'sequence'=>$sequence );
			$sequence += 1;
		}
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
		$p['color'] = $pet['color'];
		$p['fixed'] = $pet['fixed'] ? 'Yes' : 'No';
		if($pet['dob']) $p['birthday'] = shortNaturalDate(strtotime($pet['dob']));
		$p['description'] = $pet['description'];
		$p['notes'] = $pet['notes'];
		if(($fields = customPetFields()) != -999) {
			$sequence = 1;
			foreach($fields as $fkey => $field) {
				$fieldvalue = getPetCustomField($pet['petid'], $fkey);
				$p[$fkey] = array(
											'label'=>$field[0], 
											'value'=>$fieldvalue,
											'type'=>$field[2],
											'sequence'=>$sequence);
				$sequence += 1;
			}
		}
		$client['pets'][] = $p;
 }	
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
	
function customPetFields() {
	require_once "custom-field-fns.php";
	static $fields;
	if(!$fields) $fields = displayOrderCustomFields(getCustomFields($activeOnly=true, $visitSheetOnly=true, getPetCustomFieldNames()), 'petcustom');
	if(!$fields) $fields = -999;
	return $fields;
}

function customClientFields() {
	require_once "custom-field-fns.php";
	static $fields;
	if(!$fields) $fields = displayOrderCustomFields(getCustomFields($activeOnly=true, $visitSheetOnly=true), 'custom');
	if(!$fields) $fields = -999;
	return $fields;
}
