<? // mmd-fns.php
// Mobile Manager Dashboard functions

function getSitters($activeOnly=1) {
	require_once('google-map-utils.php');
	$activeOnly = $activeOnly ? "WHERE active = 1" : "";
	$sitters = fetchAssociations(
		"SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', s.fname, s.lname)) as sitter, s.fname, s.lname, active,
				street1, city, state, zip
		 FROM tblprovider s $activeOnly", 1);
	if(!$sitters) return null;
	foreach($sitters as $providerptr => $provider) {
		$addr = googleAddress($provider);
		$latLon = getLatLon($addr);
		foreach((array)$latLon as $k => $v)
			$sitters[$providerptr][$k] = $v;
	}
	return $sitters;
}

function getSitterVisits($start, $end, $includeUnassigned=0) {
	
	require_once('google-map-utils.php');
	
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	$sitterclause = $includeUnassigned ? '' : "AND providerptr != 0";
	$visits = fetchAssociations(
		"SELECT appointmentid, timeofday, servicecode, label as service, IFNULL(hours, '00:00') as hours, 
						if(completed IS NOT NULL, 'completed', 
							if(canceled IS NOT NULL, 'canceled', 'incomplete')) as status, completed,
						clientptr as clientid, CONCAT_WS(' ', c.fname, c.lname) as client,
						providerptr as sitterid, IFNULL(nickname, CONCAT_WS(' ', s.fname, s.lname)) as sitter
		 FROM tblappointment
		 LEFT JOIN tblprovider s ON providerid = providerptr
		 LEFT JOIN tblclient c ON clientid = clientptr
		 LEFT JOIN tblservicetype ON servicetypeid = servicecode
		 WHERE date >= '$start' AND date <= '$end' $sitterclause AND canceled IS NULL", 1);
	if(!$visits) return null;
	
	foreach($visits as $i => $v) {
		$clients[] = $v['clientid'];
		$visits[$i]['arrived'] = 
			fetchRow0Col0(
				"SELECT date 
					FROM tblgeotrack 
					WHERE appointmentptr = {$v['appointmentid']}
						AND event = 'arrived'");
		if(!$visits[$i]['arrived']) unset($visits[$i]['arrived']);
	}
	
	$clients = fetchAssociationsKeyedBy(
			"SELECT clientid, street1, city, state, zip FROM tblclient WHERE clientid IN (".join(',', array_unique($clients)).")", 
			'clientid', 1);
	foreach($clients as $clientid => $client) {
		$addr = googleAddress($client);
		$latLon = getLatLon($addr);
		foreach((array)$latLon as $k => $v)
			$clients[$clientid][$k] = $v;
		$clients[$clientid]['clientdisplay'] = getClientDisplayName($clientid);
	}
	
	
	foreach($visits as $vid => $visit) {
		$addInfo = $clients[$visit['clientid']];
		if($addInfo) foreach($addInfo as $k => $v)
			$visits[$vid][$k] = $v;
//echo "<br>$vid: ".print_r($visit, 1);
	}
	return $visits;
}

function getClientDisplayName($clientid) {
	static $wagPrimaryNameMode, $clientDisplayNames;
	
	if($clientDisplayNames[$clientid]) return $clientDisplayNames[$clientid];
	
	if(!$wagPrimaryNameMode) {
		require_once('preference-fns.php');
		$props = getUserPreferences($_SESSION['auth_user_id']);
		$wagPrimaryNameMode = $props['provsched_client'];
	}
	if(!$wagPrimaryNameMode) $wagPrimaryNameMode = 'fullname';
	
	if(strpos($wagPrimaryNameMode, 'pets') !== FALSE) {
		require_once "pet-fns.php";
		//if($db != $lastDb) $allPets = array(); // if we are in a different database since last call to clientLink, clear allPets
		//$lastDb = $db;
		$displayPets = getClientPetNames($clientid, $inactiveAlso=false, $englishList=false);
	}
	$client = fetchFirstAssoc(
		"SELECT fname, lname, CONCAT_WS(' ', fname, lname) as clientname
			FROM tblclient
			WHERE clientid = $clientid
			LIMIT 1", 1);
	$label = $wagPrimaryNameMode == 'fullname' ? $client['clientname'] : (
					 $wagPrimaryNameMode == 'name/pets' ? "{$client['lname']} ($displayPets)" : (
					 $wagPrimaryNameMode == 'pets' ? $displayPets : (
					 $wagPrimaryNameMode == 'pets/name' ? "$displayPets ({$client['lname']})" : (
					 $wagPrimaryNameMode == 'fullname/pets' ? "{$client['clientname']} ($displayPets)" :  
					'???'))));
	return $label;
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
			
	if(!$client) return;
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
	require_once "custom-field-fns.php";
	if($fields = displayOrderCustomFields(getCustomFields($activeOnly=true, $visitSheetOnly=false), 'custom')) {
}		
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
		if($fields = displayOrderCustomFields(getCustomFields($activeOnly=true, $visitSheetOnly=false, getPetCustomFieldNames()), 'petcustom')) {
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
	$client['displayname'] = getClientDisplayName($clientid);
	return $client;
}

function canonicalPhoneNumber($value) {
	// assume all phone numbers are US phone numbers
	// modify this function later for non-US use of the iOS app
	require_once "field-utils.php";
	return canonicalUSPhoneNumber($value);
}

function getLatLonForArray($arr) {
	// find location and add it to local list
	require_once('google-map-utils.php');
	$addr = is_string($arr) ? $arr : googleAddress($arr);
	return getLatLon($addr);
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
}	
			}
		}
	return $fieldvalue;
}



function getVisits($start, $end, $withtimeoff=1, $sortby='time', $sitterids=null, $clientids=null) {
	
	$visits = array();
	
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	$sitterclause = !$sitterids ? '' : "AND providerptr IN ($sitterids)";
	$clientclause = !$clientids ? '' : "AND clientptr IN ($clientids)";
	$visitids = fetchCol0($sql = 
		"SELECT appointmentid
		 FROM tblappointment
		 WHERE date >= '$start' AND date <= '$end' $sitterclause $clientclause
		 ORDER BY date ASC, starttime ASC", 1);
		 
	foreach($visitids as $id) {
		$visits[] = visitDetails($id, $keys=null);
	}
	if($withtimeoff) {

		$timeoffs = fetchAssociations(tzAdjustedSql(
			"SELECT tof.*, CONCAT_WS(' ', p.fname, p.lname) as sitter, CONCAT_WS(', ', p.lname, p.fname) as sittersort, 'TIME OFF' as service
				FROM tbltimeoffinstance tof
					LEFT JOIN tblprovider p ON providerid = providerptr
				WHERE date >= '$start' AND date <= '$end' $sitterclause
				ORDER BY date ASC
				"), 1);
		foreach($timeoffs as $to) $visits[] = $to;
	}
	$sortby = $sortby ? $sortby : 'time';
	$sorts = array('time'=>'timecmp', 'sitter'=>'sittercmp', 'client'=>'clientcmp');
	usort($visits, $sorts[$sortby]);
		 	
	return $visits;
}

function timecmp($a, $b) { // sort by time first, then client sortname
	$notEqual = strictlytimecmp($a, $b);
	return 
		$notEqual ? $notEqual : clientcmp($a, $b);
}

function strictlytimecmp($a, $b) { // sort by time
	$aa = $a['date'].' '.($a['timeofday'] ? substr($a['timeofday'], 0, strpos($a['timeofday'], '-')) : '00:00:00');
	$bb = $b['date'].' '.($b['timeofday'] ? substr($b['timeofday'], 0, strpos($b['timeofday'], '-')) : '00:00:00');
	return strtotime($aa) - strtotime($bb);
}

function sittercmp($a, $b) { // sort by sitter sortname first, then time
	$notEqual = strcmp($a['sittersort'], $b['sittersort']);
	return  $notEqual ? $notEqual : strictlytimecmp($a, $b); 
}

function clientcmp($a, $b) { // sort by client sortname first, then time
	$notEqual = strcmp($a['clientsort'], $b['clientsort']);
	return  $notEqual ? $notEqual : strictlytimecmp($a, $b); 
}


function visitDetails($visitid, $keys=null) {
	require_once('google-map-utils.php');
	static $allKeys, $allServices, $apptCols;
	if(!$apptCols)
		foreach(fetchAssociations("DESC tblappointment") as $col) {
			$key = $col['Field'] == 'date' ? 'tblappointment.date' : $col['Field'];
			$apptCols[$key] = 1;
		}

	if(!$allKeys)
		$allKeys = array(
			'appointmentid',
			'service', // * servicetype label
			'completed', // YYYY-mm-dd H:i:s
			'arrived',  // YYYY-mm-dd H:i:s
			'canceled', // YYYY-mm-dd H:i:s
			'timeofday', // 11:00 am-2:00 pm
			'providerptr',
			'sitter', // * sitter name
			'sittersort', // * sitter lname, fname
			'packageptr', 'recurringpackage',
			'pets',
			'petNames', // list of pet names if ALL PETS
			'totalRate', // rate+bonus
			'tblappointment.date', // 2014-03-22
			'month3Date', // Mar 22
			'longDayAndDate', // Saturday, March 22
			'shortDate', // 03/22/2014
			'shortDateAndDay', // 03/22/2014 Sat
			'shortNaturalDate', // 3/22/2014
			// otherdate_{format} -- see http://php.net/manual/en/function.date.php
			'clientptr',
			'clientname', // "Joe Smith"
			'clientsort', // * client lname, fname
			'starttime', // H:i:s
			'endtime', // H:i:s
			'endDateTime', // YYYY-mm-dd H:i:s
			'status', // completed, canceled, future, late
			'highpriority', // 1|0|null
			'note',
			'pendingchange', // 1|0|null
			'lat',
			'lon',
			'buttons',
			'clientdisplay'
			);
	$keys = $keys ? $keys : $allKeys;
	$requestedKeys = $keys;
	
	// some keys require other keys
	foreach($keys as $key) {
		if($key == 'service') $requiredKeys[] = 'servicecode';
		if($key == 'sitter') $requiredKeys[] = 'providerptr';
		if($key == 'petNames') {
			$requiredKeys[] = 'pets';
			$requiredKeys[] = 'clientptr';
		}
		if($key == 'totalRate') {
			$requiredKeys[] = 'rate';
			$requiredKeys[] = 'bonus';
		}
		if(strpos(strtoupper($key), 'DATE') !== FALSE) $requiredKeys[] = 'tblappointment.date';
		if($key == 'clientname') $requiredKeys[] = 'clientptr';
		if($key == 'endDateTime') {
			$requiredKeys[] = 'tblappointment.date';
			$requiredKeys[] = 'starttime';
			$requiredKeys[] = 'endtime';
		}
		if($key == 'status') {
			$requiredKeys[] = 'completed';
			$requiredKeys[] = 'canceled';
			$requiredKeys[] = 'tblappointment.date';
			$requiredKeys[] = 'starttime';
			$requiredKeys[] = 'endtime';
		}
	}
	$requiredKeys = array_unique((array)$requiredKeys);
	foreach($requiredKeys as $key)
		if(!in_array($key, $keys))
			$keys[] = $key;
	
	$cols = array();
	$joins = array();
	foreach($keys as $key) {
		if($apptCols[$key]) $cols[] = $key;
		else if($key == 'arrived') {
			$cols[] = "g.date as arrived";
			$joins[] = "LEFT JOIN tblgeotrack g ON appointmentptr = appointmentid AND event = 'arrived'";
		}
		else if($key == 'service') {
			$cols[] = "label as service";
			$joins[] = "LEFT JOIN tblservicetype ON servicetypeid = servicecode";
		}
		else if($key == 'sitter') {
			$cols[] = "CONCAT_WS(' ', p.fname, p.lname) as sitter, CONCAT_WS(', ', p.lname, p.fname) as sittersort";
			$joins[] = "LEFT JOIN tblprovider p ON providerid = providerptr";
		}
		else if($key == 'totalRate') {
			$cols[] = "rate+IFNULL(bonus,0) as totalRate";
		}
		else if($key == 'clientname') {
			$cols[] = "CONCAT_WS(' ', c.fname, c.lname) as clientname, CONCAT_WS(', ', c.lname, c.fname) as clientsort";
			$joins[] = "LEFT JOIN tblclient c ON clientid = clientptr";
		}
	}
	$sql = "SELECT ".join(', ', $cols)." FROM tblappointment\n".join("\n", $joins)."\nWHERE appointmentid = $visitid LIMIT 1";
	$visit = fetchFirstAssoc($sql,1);
	// handle petnames
	if(in_array('petNames', $keys)) {
		if($visit['pets'] == 'All Pets') {
			$petNames = fetchCol0("SELECT name FROM tblpet WHERE ownerptr = {$visit['clientptr']} AND active=1 ORDER BY name");
			$visit['petNames'] = join(', ', $petNames);
		}
		else $visit['petNames'] = $visit['pets'];
	}
	
	if(in_array('buttons', $keys)) {
		$visit['buttons'] = fetchAssociations($sql = "SELECT property, value FROM tblappointmentprop WHERE appointmentptr = $visitid", 1);
		//echo "SQL: $sql<hr><pre>".print_r($visit['buttons'])."</pre><hr>";
		foreach($visit['buttons'] as $k => $unused)
			if(strpos($k, 'button_') !== 0)
				unset($visit['buttons'][$k]);
		if(!$visit['buttons']) unset($visit['buttons']);
	}
	
	foreach(array('month3Date', // Mar 22
								'longDayAndDate', // Saturday, March 22
								'shortDate', // 03/22/2014
								'shortDateAndDay', // 03/22/2014 Sat
								'shortNaturalDate', // 3/22/2014
								) as $key) {
		if(in_array($key, $keys)) {
			$val = array_map($key, array(strtotime($visit['date'])));
			// fix for non-US businesses
			if($key == 'shortDate') $val[0] = date('m/d/Y', strtotime($visit['date']));
			if($key == 'shortNaturalDate') $val[0] = date('n/j/Y', strtotime($visit['date']));
			$visit[$key] = $val[0];
		}
	}
	$now = date('Y-m-d H:i:s');
	if(strcmp($visit['endtime'], $visit['starttime']) < 0)
		$apptEndDate = date('Y-m-d', strtotime("+1 day", strtotime($visit['date'])));
	else $apptEndDate = $visit['date'];
	$endDateTime = "$apptEndDate {$visit['endtime']}";
	
	if(in_array('sitter', $keys) && !$visit['sitter']) $visit['sitter'] = 'Unassigned';
	if(in_array('endDateTime', $keys)) $visit['endDateTime'] = $endDateTime;
	if(in_array('status', $keys))
		$visit['status'] = 
			$visit['completed'] ? 'completed' : (
			$visit['canceled'] ? 'canceled' : (
			$visit['arrived'] ? 'arrived' : (
			strcmp($endDateTime, $now) < 0 ? 'late' : 'future')));
			
	if(in_array('lat', $keys) || in_array('lon', $keys)) {
		addGeoCoordsTo($visit);
	}

	$visit['clientdisplay'] = getClientDisplayName($visit['clientptr']);
	
	//print_r($requestedKeys);exit;
	
	// discard unrequested fields
	foreach($visit as $key => $v) {
		if($key == 'date' && in_array('tblappointment.date', $requestedKeys))
			; // leave it alone
		else if(!in_array($key, $requestedKeys))
			unset($visit[$key]);
		}
	//$visit['appointmentid'] = $visitid;
			
	return $visit;
}

function addGeoCoordsTo(&$appt) {
	static $coords;
	// initialize coords array
	if(!$coords) $coords = array();
	
	if(!$coords[$appt['clientptr']]) {
		// find location and add it to local list
		$person = fetchFirstAssoc("SELECT street1, city, state, zip FROM tblclient WHERE clientid = {$appt['clientptr']} LIMIT 1");
		if($person['city'] && $person['state'] ) $addr = trim("{$person['street1']}, {$person['city']}, {$person['state']}");
		else if($person['street1'] && $person['zip'])	$addr = trim("{$person['street1']} {$person['zip']}");
		if(!$addr) {
			// if insufficiant address info, use -9999, -9999
			$coords[$appt['clientptr']]['lat'] = -9999;
			$coords[$appt['clientptr']]['lon'] = -9999;
		}
		else {
			require_once('google-map-utils.php');
if(mattOnlyTEST()) {if(!trim($addr)) {echo "BAD ".print_r($person,1);exit;}}			
			$loc = getLatLon($addr);
			$coords[$appt['clientptr']]['lat'] = $loc['lat'];
			$coords[$appt['clientptr']]['lon'] = $loc['lon'];
		}
	}
	// copy location to $appt
	$appt['lat'] = $coords[$appt['clientptr']]['lat'];
	$appt['lon'] = $coords[$appt['clientptr']]['lon'];
}

function getEnvironment($timeframes, $servicetypes, $surchargetypes) {
	if($timeframes) {
		require_once "timeframe-fns.php";
		foreach(getTimeframes() as $tf) {
			$times = explode('-', $tf[1]);
			$entry = array('label'=>$tf[0]);
			$entry['start'] = $times[0];
			$entry['end'] = $times[1];
			$entry['startmil'] = date('H:i', strtotime("12/1/2018 {$times[0]}"));
			$entry['endmil'] = date('H:i', strtotime("12/1/2018 {$times[1]}"));
			$json['timeframes'][] = $entry;
		}
	}

	if($servicetypes) {
		require_once "service-fns.php";
		$activeServices = fetchAssociationsKeyedBy("SELECT * FROM tblservicetype WHERE active = 1", 'servicetypeid');
		$json['servicetypes'] = $activeServices;
		
		/*require_once "client-services-fns.php";
		$fields = getClientServiceFields();
		for($i=1; $i<=count($fields)+10; $i++) {
			if($fields["client_service_$i"][1] && array_key_exists($fields["client_service_$i"][1], $activeServices)) {
				$clientServices[$fields["client_service_$i"][0]] = $activeServices[$fields["client_service_$i"][1]];
			}
		}

		$baseRate = $_SESSION['preferences']['taxRate'];
		foreach($clientServices as $label => $servicetype) {
			$servicetypeid = $servicetype['servicetypeid'];
			$taxRate = $serviceType['taxable'] ? $baseRate : 0;
			$thisServiceType = array(
				'label'=>$label,
				'servicetypeid'=>$servicetypeid,
				'description'=>$servicetype['description'],
				'charge' => (float)($servicetype['defaultcharge']),
				'extrapetcharge' => (float)($servicetype['extrapetcharge']),
				'taxrate' => (float)$taxRate);
			$json['servicetypes'][] = $thisServiceType;
		}*/
	}


	if($surchargetypes) {
		$activeSurchargeTypes = fetchAssociationsKeyedBy("SELECT * FROM tblsurchargetype WHERE active = 1", 'surchargetypeid');
		foreach($activeSurchargeTypes as $id => $stype) {
			$surch = array('surchargetypeid'=>$id, 'charge'=>(float)$stype['defaultcharge']);
			$surch['label'] = $stype['label'];
			$surch['description'] = $stype['description'];
			$surch['automatic'] = $stype['automatic'] ? 1 : 0;
			$surch['pervisit'] = $stype['pervisit'] ? 1 : 0;
			if($stype['filterspec']) {
				$parts = explode('_', $stype['filterspec']);
				$surch['type'] = $parts[0];
				if($surch['type'] == 'weekend') {
					$surch['saturday'] = strpos($parts[1], 'Sa') !== FALSE;
					$surch['sunday'] = strpos($parts[1], 'Su') !== FALSE;
				}
				else if($parts[1]) $surch['time'] = $parts[1];
			}
			else if($stype['date']) {
				$surch['type'] = 'holiday';
				$surch['date'] = $stype['date'];
			}
			else $surch['type'] = 'other';

			$json['surchargetypes'][] = $surch;
		}
	}
	return $json;
}