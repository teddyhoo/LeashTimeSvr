<? // client-analysis-fns.php
require_once 'client-fns.php';
require_once 'provider-fns.php';
require_once 'key-fns.php';
require_once 'contact-fns.php';
require_once 'pet-fns.php';

function rules() {
	$rules = <<<RULES
Client last name and first name are expected.
At least one email address is expected.
At least one of (pager, homephone, workphone, cellphone, spouse phone) is expected.
Login ID is expected.
Vet and/or clinic is expected.
Active default provider is expected.
Address street1, city, state, ZIP code are expected.
ZIP code expected to agree with city, state.
At least 1 Pet expected.
Pet name, type, and sex expected.
At least 1 Key expected.
If Alarm info is present, alarmpassword is expected.
At least 1 Emergency contact is expected.
Each Emergency contact expected to have a name.
Each Emergency contact expected to have  at least 1 phone number.
RULES;
	$rules = explode("\n", $rules);
	$out = "<ol>";
	foreach($rules as $rule) $out .= "<li>$rule";
	$out .= "</ol>";
	return $out;
}

function clientProblems($clientid, $exclude=null) {
	global $providerNames, $providers;
	//$providerNames = $providerNames ? $providerNames : getProviderShortNames();
	$fields = explode(',','fname,lname,email,phone,login,vet,address,pets,key,alarm,contact,provider');
	if($exclude) $fields = array_diff($fields, $exclude);
	$client = getClient($clientid);
	$problems = array();
	foreach($fields as $field) {
		if(in_array($field, array('lname', 'fname')))
			if(!$client[$field]) $problems[] = ($field[0] =='f' ? 'First' : 'Last').' name missing.';
		if($field == 'email') 
			if(noneFound($client, array('email','email2'))) $problems[] = 'No email address supplied.';
		if($field == 'phone') 
			if(noneFound($client, array('pager', 'homephone', 'workphone', 'cellphone','cellphone2'))) $problems[] = 'No phone or pager number supplied.';
		if($field == 'login') 
			if(!$client['userid']) $problems[] = 'No system login.';
		if($field == 'vet') 
			if(noneFound($client, array('vetptr','clinicptr'))) $problems[] = 'No vet or vet clinic supplied.';
		if($field == 'provider') {
			if(!$client['providerptr']) $problems[] = 'No default sitter designated.';
			else {
				$prov = isset($providers[$client['providerptr']]) ? $providers[$client['providerptr']] : 0;
				if(!$prov) {
					$prov = getProvider($client['providerptr']);
					$providers[$client['providerptr']] = $prov ? $prov : -1;
				}
				if($prov == -1) $problems[] = "Unknown sitter ({$client['providerptr']}).";
				else if(!$prov['active']) $problems[] = "Sitter inactive: {$prov['fname']} {$prov['lname']}.";
			}
		}
		if($field == 'address') {
			$missingFields = missingFields($client, array('street1', 'city', 'state', 'zip'));
			if($missingFields) $problems[] = "Missing home address fields: ".join(', ', $missingFields).".";
			if($client['zip'] && $client['city'] && $client['city'])
				if($badZip = badZip($client)) $problems[] = $badZip;
		}
		if($field == 'pets') {
			$pets = getClientPets($clientid);
			if(!$pets) $problems[] = "No pets associated with client.";
			else {
				$n = 1;
				foreach($pets as $pet) {
					$missingFields = missingFields($pet, array('name', 'type', 'sex'));
					if($missingFields) {
						$name = $pet['name'] ? $pet['name'] : "#$n";
						$problems[] = "Pet: $name is missing details: ".join(', ',$missingFields);
					}
					$n++;
				}
			}
		}
		if($field == 'key') {
			$keys = getClientKeys($clientid);
			if(!$keys) $problems[] = "No keys associated with client.";
			else {}
		}
		if($field == 'alarm') {
			$problem = badAlarm($client);
			if($problem) $problems[] = $problem;
		}
		if($field == 'contact') {
			$contactProblems = badContacts($client);
			if($contactProblems) $problems = array_merge($problems, $contactProblems);
		}
	}
	return $problems;
	// check for
	// fname, lname, email, phone, login, vet/clinic
	// street1, city, state, zip (zip city == city)
	// pet defined
	// key defined
	// req. alarm fields if any supplied
	// emergency or neighbor.  name and phone for each supplied contact
}


function badContacts($client) {
	$contacts = getClientContacts($clientid);
	$problems = array();
	if(!$contacts) $problems = array("No emergency contacts supplied.");
	else foreach($contacts as $contact) {
		$type = $contact['type'];
		if(!$contact['name'])  $problems[] = "$type contact has no name.";
		if(noneFound($contact, $array('homephone', 'workphone', 'cellphone'))) $problems[] = "$type contact has no phone number.";
	}
	return $problems;
}

function badAlarm($client) {
	$allFields = array('alarmcompany', 'alarmpassword', 'armalarm', 'disarmalarm', 'alrmlocation');
	if(noneFound($client, $allFields)) return '';
	$reqFields = array('alarmpassword');
	if($missingFields = missingFields($client, $reqFields))
		return "Missing alarm fields: ".join(', ',$missingFields);
}

function badZip($client) {
	$cityState = lookUpZip($client['zip']);
	if($cityState == -1) return "Bad ZIP code: {$client['zip']}";
	else
		if($cityState[0] != strtoupper($client['city']) || $cityState[1] != strtoupper($client['state']))
			return "ZIP code mismatch: {$client['zip']} is associated with {$cityState[0]}, {$cityState[1]}, ".
							"not {$client['city']}, {$client['state']}";
}

function lookUpZip($zip) {
	global $zips, $db, $dbhost, $dbuser, $dbpass;
	$cityState = isset($zips[$zip]) ? $zips[$zip] : null;
	if(!$cityState) {
		list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
		include "common/init_db_common.php";
		$cityState = fetchRow0Col0("SELECT CONCAT_WS('|',city,state) FROM zipcodes2 WHERE zipcode = '$zip' LIMIT 1");
		list($db, $dbhost, $dbuser, $dbpass) = array($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
		include "common/init_db_petbiz.php";
		$cityState = $cityState ? explode('|', strtoupper($cityState)) : -1;
		$zips[$client['zip']] = $cityState;
	}
	return $cityState;	
}



function anyFound($arr, $keys) {
	foreach($keys as $key) if(isset($arr[$key]) && $arr[$key]) return true;
	return true;
}

function noneFound($arr, $keys) {
	foreach($keys as $key) if(isset($arr[$key]) && $arr[$key]) return false;
	return true;
}

function missingFields($arr, $keys) {
	$missing = array();
	foreach($keys as $key) if(!isset($arr[$key]) || !$arr[$key]) $missing[] = $key;
	return $missing;
}
