
<?// import-clients-precise-xml.php

/*
PRE-REQUISITES (BW):
import providers (import-providers.php)
import vets (import-vets-html.php)
import referrals (actually, gather a csv -- import-referrals.php)
paste referrals in $rawReferralTypes

$refreshData - if true, clients are refreshed if they exist and only new clients are created


set up custom fields
INSERT INTO `tblpreference` (`property`, `value`) VALUES
('custom1', 'Other Phone #1|1|oneline|0'),
('custom2', 'Other Phone #2|1|oneline|0'),
('custom3', 'Other Phone #3|1|oneline|0'),
('custom4', 'Alternate Sitter|1|oneline|0'),
('custom5', 'Customer Referral|1|oneline|0'),
('custom6', 'Alternate Customer Name|1|oneline|0')

Login to DEV
*/

require_once "field-utils.php";
$TEST = 0;
$nocheck = 1;
$namespace = ''; //ss:
// https://leashtime.com/import-clients-precise-xml.php?file=printersrowdogwalkers/precise-clients.xml
// Cusotm example: https://leashtime.com/import-clients.php?nocheck=1&file=dogcampla/clients-with-map.csv&custom=1&customfields=custom1|import|text,custom9|uid|oneline
$starttime = microtime(1);

// POPS CONVERSION FUNCTIONS
function convert_primaryphone($trimval, &$client) {
	if(!$trimval) return;
	$prefix = strtolower(substr($trimval, 0, min(4, strlen($trimval))));
	if($client["{$prefix}phone"]) $client["{$prefix}phone"] = "*{$client["{$prefix}phone"]}";
	return $client;
}

function convert_neighbor($trimval, &$target) {
	global $neighborContact;
	$neighborContact['name'] = $trimval;
	return $target;
}

function convert_emergency($trimval, &$target) {
	global $emergencyContact;
	$emergencyContact['name'] = $trimval;
	return $target;
}

function convert_dob($trimval, &$target) {
	if(!$trimval) return;
	$time = strtotime(trim(substr($trimval, 0, strpos($trimVal, ' ('))));
	if($time) $target['dob'] = date('Y-m-d', $time);
	return $target;
}


function convert_defaultsitter($trimval, &$target) {
	global $officenotes;
	if(!$trimval || $trimval == 'N/A') return;
	$provid = findSitterByName($trimval);
	if(!$provid) $provid = findSitterByNickname($trimval);
	if($provid) $target['defaultproviderptr'] = $provid;
	else $officenotes[] = 'Primary Sitter not found: '.$trimval;
	return $target;
}

function convert_creationdate($trimval, &$target) { // TBD
	if(!$trimval) return;
	if($time = strtotime($trimval))
		$target['setupdate'] = date('Y-m-d', $time);
	return $target;
}

function convert_useskeys($trimval, &$target) {
	$target['nokeyrequired'] = $trimval == 'Yes' ? 0 : 1;
	return $target;
}

function convert_clinic($trimval, &$target) {
	$clinicname = explode("\n", $trimval);
	$clinicname = $clinicname[0];
	$clinicid = fetchRow0Col0("SELECT clinicid FROM tblclinic WHERE clinicname = '".mysqli_real_escape_string($clinicname)."' LIMIT 1");
	if($clinicid) $target['clinicptr'] = $clinicid;
	else echo "Clinic [$clinicname] not found for {$target['fname']} {$target['lname']}.<br>";
	return $target;
}


function convert_active($trimval, &$target) {
	$target['active'] = $trimval == 'Yes' ? 1 : 0;
	$target['activeHasBeenSet'] = true; 
	return $target;
}

function convert_street($trimval, &$target) {
	$trimval = str_replace("<br />", "\n", str_replace("<br>", "\n", $trimval));
	$parts = explode("\n", $trimval);
	$target['street1'] = $parts[0];
	if($parts[1]) $target['street2'] = $parts[1];
	return $target;
}


function convert_email2($trimval, &$client) { // handles multiple emails
	return convert_email($trimval, $client, 'email2');
}

function convert_email($trimval, &$client, $oneField='email') { // handles multiple emails
	$trimVal = trim((string)$trimVal);
	if(!$trimval) return $client;
	foreach(decomposeEmails($trimval) as $x) {
		$emails[] = $x;
	}
	foreach($emails as $email) {
		if(!isEmailValid($email)) {
			echo "<br><font color=red>BAD EMAIL: $email</font>";
			$client['notes'][] = "BAD EMAIL: $email";
		}
		else if(!$client[$oneField]) $client[$oneField] = $email;
		else $client['notes'][] = "Other email: $email";
	}
	return $client;
}

function decomposeEmails($trimval) { // handles multiple emails
	if(!$trimval) return;
	$trimval = str_replace("<br />", "\n", str_replace("<br>", "\n", $trimval));
	foreach(decompose($trimval, "\n") as $x) {
		foreach(decompose($x, ",") as $x1) {
			foreach(decompose($x1, "&") as $x2) {
				foreach(decompose($x1, ";") as $x3) {
					if($x3)	{
						$emails[] = $x3;
					}
				}
			}
		}
	}
	return $emails;
}


function decompose($str, $delim) {
	return array_map('trim', explode($delim, $str));
}

function findSitterByName($nm) {
	return fetchRow0Col0("SELECT providerid FROM tblprovider WHERE CONCAT_WS(' ', fname, lname) = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findSitterByNickname($nn) {
	return fetchRow0Col0("SELECT providerid FROM tblprovider WHERE nickname = '".mysqli_real_escape_string($nn ? $nn : '')."' LIMIT 1");
}



// https://LEASHTIME.COM/import-clients.php?allowshortfields=1&map=map-act-clients.csv&file=asyouwish/AsYouWishContacts1.20.11.csv

/*

http://iwmr.info/petbizdev/import-clients.php?map=map-bluewave-clients.csv&file=petaholics/Petaholics-customer-dataORIG.xls

*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";

//$rawReferralTypes = '?,Unknown,C,Client,F,Friend,G,Vet,N,Advertisement,U,Website/websearch,X,Unknown,Z,Petsitter,H,Adopt-a-Hwy Sign,D,Direct Mail,E,Email,P,PSI,Y,Yellow Pages Directory,A,?';

//$rawReferralTypes = '?,Unknown,H,Unknown,5,AAA,4,Apparel/Promotional Giveaway,J,Business Card/Business Card Magnet,A,Car Magnet/Decal,C,Client,3,Corporate Partner,B,Craigslist,E,Direct Email,D,Direct Mail,K,Door Hanger/Brochure/Flyer,V,Google,L,Groomer,I,Industry Event,1,Networking Group,O,Online Yellow Pages,Q,Other Online Directory,U,Other Online Directory,N,Paid Print Ad,Z,Pet Sitter,P,Pet Sitters International/Petsit.com,2,Pet Store,7,PETCO,W,Printed White Pages,Y,Printed Yellow Pages,R,Radio,F,Respond.com,8,Systino Referral,T,Television,S,Trainer,G,Vet,M,Word Of Mouth,X,Yahoo!';
$rawReferralTypes = explode(',',$rawReferralTypes);
for($i=0;$i < count($rawReferralTypes)-1; $i+=2)
	$referralTypes[$rawReferralTypes[$i]] = $rawReferralTypes[$i+1];

if($TEST) echo "Will set Referral Categories: ".join(', ', array_values((array)$referralTypes))."<p>";


extract($_REQUEST);

if($custom) $mapFile = "/var/data/clientimports/$map";
else $mapFile = "/var/data/clientimports/map-precise-clients.csv";
$file = "/var/data/clientimports/$file";


$mapLines = file($mapFile, FILE_IGNORE_NEW_LINES);


echo "<a href='#PETSECTION'>JUMP TO PETS</a><p>";

$clientFieldMapFields = array_map('trim', explode(',', trim($mapLines[0])));
$conversions = array_map('trim', explode(',', trim($mapLines[1])));
$map = array_combine($clientFieldMapFields, $conversions);
echo "clientFieldMapFields: ".count($clientFieldMapFields).'<br>conversions: '.count($conversions).'<br>';
for($i=0;$i<max(count($clientFieldMapFields), count($conversions));$i++) {
  echo ($i >= count($clientFieldMapFields) ? "<i>[no value]</i>" : $clientFieldMapFields[$i])." ==> ";
  echo ($i >= count($conversions) ? "<i>[no value]</i>" : $conversions[$i])."<br>";
}

$petFieldMapFields = array_map('trim', explode(',', trim($mapLines[2])));
$fullSlot = false;
while(!$fullSlot) if($fullSlot = array_pop($petFieldMapFields)) $petFieldMapFields[] = $fullSlot;
$petconversions = array_map('trim', explode(',', trim($mapLines[3])));
$fullSlot = false;
while(!$fullSlot) if($fullSlot = array_pop($petconversions)) $petconversions[] = $fullSlot;
$petmap = array_combine($petFieldMapFields, $petconversions);
echo "<p>petFieldMapFields: ".count($petFieldMapFields).'<br>petconversions: '.count($petconversions).'<br>';
for($i=0;$i<max(count($petFieldMapFields), count($petconversions));$i++) {
  echo ($i >= count($petFieldMapFields) ? "<i>[no value]</i>" : $petFieldMapFields[$i])." ==> ";
  echo ($i >= count($petconversions) ? "<i>[no value]</i>" : $petconversions[$i])."<br>";
}

echo "<hr>";

$delimiter = strpos($file, '.xls') ? "\t" : ',';
$strm = fopen($file, 'r');
$line0 = fgets($strm);
if(strpos($line0, '<?xml ') === 0) $delimiter = null;
rewind($strm);
//$line0 = trim(fgetcsv($strm, 0, $delimiter)); 
//echo join(',',myfgetcsv($strm, 0, $delimiter)); exit;
$dataHeaders = array_map('trim', myfgetcsv($strm, 0, $delimiter));// consume first line (field labels)
$fullSlot = false;
while(!$fullSlot) if($fullSlot = array_pop($dataHeaders)) $dataHeaders[] = $fullSlot;
if($custom) myfgetcsv($strm, 0, $delimiter);  // eat the second line if custom
//print_r($dataHeaders);
echo "dataHeaders: ".count($dataHeaders).':<br>'.join(',', $dataHeaders).'<hr>';
//echo "map: ".print_r($map,1);exit;

//print_r($line0);
$n = 1;

if($refreshData) {
	$tables = fetchCol0("SHOW tables");
	if(!in_array('tempClientMap', $tables)) 
		echo "There are no exisiting clients to refresh.";
	else 
		$clientMap = fetchKeyValuePairs("SELECT externalptr, clientptr FROM tempClientMap");
}
$clientMap = array();  // mapID => clientid

$initializeCustomFields = FALSE;
if($initializeCustomFields) {
	$existingProperties = fetchCol0("SELECT value FROM tblpreference WHERE property LIKE 'custom%'");
//$existingProperties = array('long-time client|1|boolean|1','needs a confirmation on first visit|1|boolean|1','needs a confirmation daily|1|oneline|0','specific referral source|1|oneline|0');
//print_r($existingProperties);exit;// Array ( [0] => long-time client|1|boolean|1 [1] => needs a confirmation on first visit|1|boolean|1 [2] => needs a confirmation daily|1|oneline|0 [3] => specific referral source|1|oneline|0 ) 
	
	deleteTable('tblpreference', "property LIKE 'custom%' OR property LIKE 'petcustom%'", 1);
	doQuery(
		"INSERT INTO `tblpreference` (`property`, `value`) VALUES
			('custom1', 'House Checklist|1|text|0'),
			('custom2', 'Regular Visit Checklist|1|text|0'),
			('custom3', 'Overnights/Vacation Checklist|1|text|0'),
			('custom4', 'Trash/Recycling|1|text|0'),
			('custom5', 'Pet Waste Disposal|1|text|0'),
			('custom6', 'Cleaning Supplies|1|text|0'),
			('custom7', 'Flashlights/Headlamps|1|oneline|0'),
			('custom8', 'Supplies|1|text|0|1|oneline|0'),
			('custom9', 'Sitters and Pet(s) sleep|1|oneline|0'),
			('custom10', 'House Notes|1|text|0'),
			('custom11', 'Walking Route/Trails|1|text|0'),
			('custom12', 'Other Notes|1|text|0'),
			('custom13', 'Custom Prices|1|text|0'),
			('custom14', 'Referral/Other|1|text|0')
			"
			);
	$customCount = fetchRow0Col0("SELECT count(*) FROM tblpreference WHERE property LIKE 'custom%'")+1;
	foreach((array)$existingProperties as $val) {
		insertTable('tblpreference', array('property'=>"custom$customCount",'value'=>$val), 1);
		$customCount++;
	}
//Aggressive	AggressionDetails	Medications	FoodLocation	LitterBoxLocation	LeashLocation	CarrierLocation	VaccinationsCurrent	VetHasCC
//custom1	custom2	custom3	custom4	custom5	custom6	custom7	custom7	custom8
	
	doQuery(
		"INSERT INTO `tblpreference` (`property`, `value`) VALUES
			('petcustom1', 'Weight|1|oneline|0'),
			('petcustom2', 'My Pet Stays|1|oneline|0'),
			('petcustom3', 'Personality|1|text|0'),
			('petcustom4', 'Pet Routine|1|text|0'),
			('petcustom5', 'Training Commands|1|text|0'),
			('petcustom6', 'Feeding/Brand of Food|1|text|0'),
			('petcustom7', 'Vaccinations|1|text|0'),
			('petcustom8', 'Medical Concerns|1|text|0'),
			('petcustom9', 'Medication/Health Notes|1|text|0'),
			('petcustom10', 'Pet&apos;s Vet|1|text|0'),
			('petcustom11', 'Office Notes|1|text|0')
			"
			);
			
	$_SESSION['preferences'] = fetchPreferences();
}
else if($custom && $customfields) {
	foreach(explode(',', $customfields) as $field) {
		$field = explode('|', $field);
		$type = isset($field[2]) ? $field[2] : 'text' ;
		doQuery(
			"INSERT INTO `tblpreference` (`property`, `value`) VALUES
			('{$field[0]}', '{$field[1]}|1|$type|1')");
	}
	$_SESSION['preferences'] = fetchPreferences();
}


$customFields = getCustomFields('activeOnly');

//print_r($_SESSION['preferences']);exit;

$incompleteRow = null;
$clientsFound = 0;
$clientsCreated = 0;

$providersByEmployeeId = fetchKeyValuePairs("SELECT employeeid, providerid FROM tblprovider");

echo "<b>Setup time".(microtime(1)-$starttime).' sec.</b><br>';

$clientstarttime = microtime(1);
while($row = myfgetcsv($strm, 0, $delimiter)) {
	if($petSectionHasBegun) break;
//if($n) echo "<b>processed row: $n in ".(microtime(1)-$clientstarttime).' sec.  Total time: '.(microtime(1)-$starttime).' sec</b><br>';
//$clientstarttime = microtime(1);
	$n++;
//echo '<hr><hr>'.print_r(array_combine($dataHeaders, $row), 1).'<hr>'.htmlentities($leftover).'<hr>';if($n > 30) exit;	
	// HANDLE EMPTY LINES
	if(!$row) {echo "<font color=red>Empty Line #$n</font><br>";continue;}
	// HANDLE CONTINUATIONS OF INCOMPLETE LINES
	else if($incompleteRow) {
		$incompleteRow[count($incompleteRow)-1] = $incompleteRow[count($incompleteRow)-1]."\n".$row[0];
		for($rawRowLength = count($row); $rawRowLength > 0; $rawRowLength--)
			if($row[$rawRowLength-1]) break;

		for($i=1;$i<$rawRowLength;$i++) $incompleteRow[] = $row[$i];
		$row = $incompleteRow;
	}
	
	if(!$nocheck) {
		// problem: when a field contains a CR the rest of the line is included as empty cells
		// count the number of cells up to the last non-empty cell
		for($rowLength = count($row); $rowLength > 0; $rowLength--)
			if($row[$rowLength-1]) break;

		if($rowLength < ($fieldcount = count($clientFieldMapFields))) {
			echo "<font color=red>Bad row: $n [$rowLength / $fieldcount fields]</font><br>";
			if($nocorrection) continue;
			if(!$allowshortfields) {
				$incompleteRow = array();
				for($i=0;$i<$rowLength;$i++) $incompleteRow[] = $row[$i];
				continue;
			}
		}
		else {
			if($incompleteRow) echo "<font color=lightgreen>CORRECTED ROW: ".join(', ',	$incompleteRow).'</font><br>';
			$incompleteRow = null;
		}
	}
	//$row = explode(',', $line);
	$client = array();
	$emergencyContact = array();
	$neighborContact = array();
	$key = array();
	$notes = array();
	$officenotes = array();
	$alarminfo = array();
	// PROCESS CLIENT ROW
	foreach($row as $i => $field) {
		if(!$field) continue;
		$field = html_entity_decode($field);
		//$conv = $conversions[$i];
		$conv = $map[$dataHeaders[$i]];
		if($conv == 'x') continue;
		if($conv == 'alarminfo/-') {
			$alarminfo[] = $dataHeaders[$i].': '.$field;
		}
		else if($conv == 'notes/-') {
			$notes[] = $dataHeaders[$i].': '.$field;
		}
		else if($conv == 'notes/na') {
			if($field != 'N/A')
				$notes[] = $dataHeaders[$i].': '.$field;
		}
		else if($conv == 'officenotes/-') {
			$officenotes[] = $dataHeaders[$i].': '.$field;
		}
		//convert_bluewave_provider	convert_bluewave_alternate_provider	convert_bluewave_status	convert_bluewave_level
		else if(strpos($conv, 'convert_') === 0) // conversion must return $client
			$client = call_user_func_array($conv, array($field, &$client));
			
//if($conv == 'convert_bluewave_provider') echo ">>>>prov: ".print_r($client, 1)."<br>";
			
		else if(strpos($conv, 'custom') === 0) 
			$client['custom'][$conv] = str_replace('#EOL#', "\n", str_replace('\n', "\n", $field));
			

		else $client[$conv] = $field;
	}
//echo "<hr>".print_r($client,1)."<hr>";	
	if($alarminfo) $client['alarminfo'] = join("\n",$alarminfo);
	if($notes || $client['notes']) {
		$notes = array_merge((array)$notes, (array)$client['notes']);
		$client['notes'] = join("\n",$notes);
	}
	if($officenotes) $client['officenotes'] = join("\n",$officenotes);
	if(!(isset($client['lname']) || isset($client['fname']))) echo "<p><font color=red>Bad row: $n ".print_r($client,1).'</font>';
	else { 	// CREATE OR UPDATE CLIENT ROW
		if($client['ignore']) continue;
		if(!$client['activeHasBeenSet']) $client['active'] = 1;  // see convert_setClientActive
		if(!$TEST) {
			if($client['zip']) {
				if(strlen($client['zip']) < 5)
					$client['zip'] = sprintf("%05d", $client['zip']);
			}
			saveNewClient($client);
			$newClientId = mysqli_insert_id();
			$clientsByName["{$client['lname']}, {$client['fname']}"] = $newClientId;
			$clientsCreated++;
			echo "<p>Created CLIENT #$newClientId {$client['fname']} {$client['lname']}<br>";
		}
		else {
			$clientsFound++;
			$clientsByName["{$client['fname']} {$client['lname']}"] = $clientsFound;
			echo "<p>Found CLIENT {$client['fname']} {$client['lname']}<br>";
		}
		if($key) {
			$key['copies'] = 1;
			$key['possessor1'] = 'client';
			if(!$TEST && $newClientId) {
				$keyId = saveClientKey($newClientId, $key);
				echo "<p>Created KEY #$keyId<br>";
			}
			else echo "<p>Found KEY for {$client['fname']} {$client['lname']}<br>";
		}
		//saveClientPets($newClientId);
		if($emergencyContact) {
			if(!$TEST) {
				$contactId = saveClientContact('emergency', $newClientId, $emergencyContact);
				echo "<p>Created Emergency Contact #$contactId {$emergencyContact['name']}<br>";
			}
			else echo "<p>Found Emergency Contact named {$emergencyContact['name']} for {$client['fname']} {$client['lname']}<br>";
		}
		if($neighborContact) {
			if(!$TEST) {
				$contactId = saveClientContact('neighbor', $newClientId, $neighborContact);
				echo "<p>Created Trusted Neighbor #$contactId {$neighborContact['name']}<br>";
			}
			else echo "<p>Found Trusted Neighbor named {$neighborContact['name']} for {$client['fname']} {$client['lname']}<br>";
		}
		if($otherContact) {
			if(!$TEST) {
				$contactId = saveOtherContact('Other contact', $newClientId, $otherContact);
				echo "<p>Noted Other contact {$otherContact['name']}<br>";
			}
			else echo "<p>Found Other contact named {$otherContact['name']} for {$client['fname']} {$client['lname']}<br>";
		}
		if($client['custom']) {
			foreach($client['custom'] as $field => $val) {
				$val = mysqli_real_escape_string($val);
				if(!$customFields[$field]) {
					if(!$TEST) echo "<font color=red>Bad custom field [$field].  Could not populate this field with [$val] for {$client['lname']}</font>";
					else {echo "<font color=orange>Unregistered custom field [$field].</font><br>";}
				}
				else if(!$TEST) doQuery("REPLACE relclientcustomfield (clientptr, fieldname, value) VALUES ($newClientId, '$field', '$val')");
			}
			if($TEST) echo "Found ".count($client['custom'])." custom fields for {$client['fname']} {$client['lname']}<br>";
			else echo "Added custom fields for $newClientId<br>";
		}
	}
}

// import pets now
if($petSectionHasBegun) {
	echo "<hr><hr><hr><a name=PETSECTION>PETS</a><hr><hr><hr>";
	require_once "pet-fns.php";
	$clientCount = $n;
	$n = 0;
	// the first row is Headers
	$dataHeaders = array_map('trim', $row);// consume first line (field labels)
	$fullSlot = false;
	while(!$fullSlot) if($fullSlot = array_pop($dataHeaders)) $dataHeaders[] = $fullSlot;
	$customFields = getCustomFields('activeOnly', false, getPetCustomFieldNames());

	while($row = myfgetcsv($strm, 0, $delimiter)) {
		$n++;
	//echo '<hr><hr>'.print_r(array_combine($dataHeaders, $row), 1).'<hr>'.htmlentities($leftover).'<hr>';if($n > 30) exit;	
		// HANDLE EMPTY LINES
		if(!$row) {echo "<font color=red>Empty Line #$n</font><br>";continue;}
		// HANDLE CONTINUATIONS OF INCOMPLETE LINES
		else if($incompleteRow) {
			$incompleteRow[count($incompleteRow)-1] = $incompleteRow[count($incompleteRow)-1]."\n".$row[0];
			for($rawRowLength = count($row); $rawRowLength > 0; $rawRowLength--)
				if($row[$rawRowLength-1]) break;

			for($i=1;$i<$rawRowLength;$i++) $incompleteRow[] = $row[$i];
			$row = $incompleteRow;
		}

		if(!$nocheck) {
			// problem: when a field contains a CR the rest of the line is included as empty cells
			// count the number of cells up to the last non-empty cell
			for($rowLength = count($row); $rowLength > 0; $rowLength--)
				if($row[$rowLength-1]) break;

			if($rowLength < ($fieldcount = count($petFieldMapFields))) {
				echo "<font color=red>Bad row: $n [$rowLength / $fieldcount fields]</font><br>";
				if($nocorrection) continue;
				if(!$allowshortfields) {
					$incompleteRow = array();
					for($i=0;$i<$rowLength;$i++) $incompleteRow[] = $row[$i];
					continue;
				}
			}
			else {
				if($incompleteRow) echo "<font color=lightgreen>CORRECTED ROW: ".join(', ',	$incompleteRow).'</font><br>';
				$incompleteRow = null;
			}
		}
		//$row = explode(',', $line);
		$pet = array();
		$custom = array();
		$notes = array();
		// PROCESS PET ROW
		foreach($row as $i => $field) {
			if(!$field) continue;
			$conv = $petmap[$dataHeaders[$i]];
			$field = html_entity_decode($field);
			if($conv == 'x') continue;
			else if($conv == 'notes/-') {
				$notes[] = $dataHeaders[$i].': '.$field;
			}
			else if(strpos($conv, 'convert_') === 0) // conversion must return $client
				$client = call_user_func_array($conv, array($field, &$pet));
			else if(strpos($conv, 'petcustom') === 0) {
				if(strpos($_SESSION['preferences'][$conv], '|boolean|') !== FALSE)
					$custom[$conv] = $field == "False" ? '0' : '1';
				else $custom[$conv] = str_replace('#EOL#', "\n", str_replace('\n', "\n", $field));
			}
			else $pet[$conv] = $field;
		}
		if(!(isset($pet['name']))) echo "<p><font color=red>Bad row: $n ".print_r($pet,1).'</font>';
		else { 	// CREATE OR UPDATE CLIENT ROW
			if($pet['ignore']) continue;
			if($notes) $pet['notes'] = join("\n",$notes);
			if(!$TEST) {
					$pet['fixed'] = $pet['fixed'] ? $pet['fixed'] : '0';
					$pet['active'] = $pet['active'] ? $pet['active'] : '0';
					unset($pet['activeHasBeenSet']);
					$newPetId = insertTable('tblpet', $pet, 1);
					$petsCreated++;
					echo "<p>Created PET #$newPetId {$pet['name']} (owner: {$pet['ownerptr']})<br>";
			}
			else {
				$petsFound++;
				echo "<p>Found PET {$pet['name']}<br>";
			}
			if($custom) {
				foreach($custom as $field => $val) {
					$val = mysqli_real_escape_string($val);
					if(!$customFields[$field]) {
						if(!$TEST) echo "<font color=red>Bad custom pet field [$field].  Could not populate this field with [$val] for {$client['lname']}</font>";
						else {echo "<font color=orange>Unregistered pet custom field [$field].</font><br>";}
					}
					else if(!$TEST) doQuery("REPLACE relpetcustomfield (petptr, fieldname, value) VALUES ($newPetId, '$field', '$val')");
				}
				if($TEST) echo "Found ".count($client['custom'])." custom fields for pet {$pet['name']}<br>";
				else echo "Added custom fields for $newPetId<br>";
			}
		}
	} // pet loop
} // pets

function convert_precise_clientid($trimval, &$pet) {
	global $clientsByName;
	if($clientsByName[$trimval]) $pet['ownerptr'] = $clientsByName[$trimval];
	else echo "Owner [$trimval] not found in ".print_r($clientsByName,1)."<p>";
	return $pet;
}

function convert_sex($trimval, &$pet) {
	if(strpos($trimval, 'ale')) $pet['sex'] = $trimval[0];
	return $pet;
}

function convert_fixed($trimval, &$pet) {
	if($trimval == 'Yes') $pet['fixed'] = 1;
	return $pet;
}
	


function convert_pops_dob($dob, &$pet) {
	if($dob) $pet['dob'] = date('Y-m-d', strtotime($dob));;
	return $pet;
}

if($clientsCreated) echo "Created $clientsCreated clients.<hr>";
else echo "Found $clientsFound clients.<hr>";
if($clientMap) {
	$tableSql = "CREATE TABLE IF NOT EXISTS `tempClientMap` (
  `externalptr` int(11) NOT NULL,
  `clientptr` int(11) NOT NULL,
  PRIMARY KEY  (`externalptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

	doQuery($tableSql);
	if(!$refreshData) doQuery('DELETE FROM tempClientMap');
	echo "External ID,ClientID<br>";
	foreach($clientMap as $mapID => $clientid) {
		insertTable('tempClientMap', array('externalptr'=>$mapID,'clientptr'=>$clientid), 1);
		echo "$mapID,$clientid<br>";
	}
}
echo "<hr>";

function refreshBWClient($client) { // BLUEWAVE
	// clear relevant fields
	// mapID,lname,fname,street1,street2,city,state,zip,homephone,workphone,cellphone,
	// custom1,custom2,custom3,officenotes/-,custom6,convert_bluewave_directions,convert_bluewave_referral,
	// convert_bluewave_referral_comment,convert_bluewave_directions,email,email2,convert_bluewave_provider,
	// convert_bluewave_alternate_provider,convert_bluewave_status,convert_bluewave_level
	static $relevantFields, $zeroFields;
	if(!$zeroFields) {
		$relevantFields = 
			explode(',', 'lname,fname,street1,street2,city,state,zip,homephone,workphone,cellphone,officenotes,notes,directions'
		.								',defaultproviderptr,email,email2,active,prospect,vetptr,clinicptr');
		$zeroFields = explode(',', 'defaultproviderptr,active,prospect,vetptr,clinicptr');
		$customFields = "'".join("','", fetchCol0("SELECT property FROM tblpreference WHERE property LIKE 'custom%'"))."'";
	}
	foreach($relevantFields as $field) 
		if(!$client[$field])
			$client[$field] = in_array($field, $zeroFields) ? '0' : '';
	deleteTable('relclientcustomfield', "clientptr = {$client['clientid']} AND fieldname IN ($customFields)", 1);
	deleteTable('tblcontact', "clientptr = {$client['clientid']}", 1);
	saveClient($client);
}

// BLUEWAVE CONVERSION FUNCTIONS
	
function convert_petNames($petNames, &$client) {
	$client['petNames'] = explode(',', $petNames);
	return $client;
}
	
function convert_actvet($actvet, &$client) {
	if(!$actvet) return;
	if(strpos($actvet, 'Dr.') !== FALSE) $actvet = trim(substr($actvet, strlen('Dr.')));
	$quotedactvet = val($actvet);
	$clinic = fetchRow0Col0("SELECT clinicid FROM tblclinic WHERE clinicname = $quotedactvet LIMIT 1");
	if($clinic) $client['clinicptr'] = $clinic;
	else {
		$vet = fetchRow0Col0(
			"SELECT vetid 
				FROM tblvet 
				WHERE $quotedactvet = if(fname = '?', lname, CONCAT_WS(' ', fname, lname)) LIMIT 1");
		if(!$vet) echo "<font color=red>{$client['fname']} {$client['lname']} has an unknown vet ($actvet).</font>";
		else $client['vetptr'] = $vet;
	}
	return $client;
}
	
function convert_bluewave_provider($bwsitter, &$client) {
	global $bluewaveSitters;
	if(initializeBluewaveSitters() == -1) {
		echo "<font color=red>Failed to associate provider $bwsitter with Bluewave client {$client['fname']} {$client['lname']} [{$client['mapID']}]</font>";
		return null;
	}
	$sitter = $bluewaveSitters[$bwsitter];
	if(!$sitter)
		echo "<font color=red>{$client['fname']} {$client['lname']} has an unknown provider ($bwsitter).</font>";
	else $client['defaultproviderptr'] = $sitter['providerid'];
	return $client;
}
	
function convert_bluewave_alternate_provider($bwsitter, &$client) {
	global $bluewaveSitters;
	if(initializeBluewaveSitters() == -1) {
		echo "<font color=re>Failed to associate alternate provider $bwsitter with Bluewave client {$client['fname']} {$client['lname']} [{$client['mapID']}]</font>";
		return null;
	}
	$sitter = $bluewaveSitters[$bwsitter];
	if($sitter)
		$client['custom']['custom4'] = $sitter['fname'].' '.$sitter['lname'];
	return $client;
}
	
	
function convert_bluewave_directions($directions, &$client) {
	if($directions) $client['directions'] .= decodeComment($directions)."\n\n"; 
	return $client;
}	

function convert_bluewave_status($status, &$client) {
	//$client = convert_PS_name($client); // pooper scooper custom
	return convert_setClientActive($status == 'AC' ? 1 : 0, $client);
}	

function convert_PS_name(&$client) { // pooper scooper custom
	// in this one-off, clients from a second BW db were being added to an exisiting biz.
	// to avoid clientid collisions, we incremented the mapID for each
	// a similar set of changes was necessary in the history importer
	// some client names were mis-supplied as lname="last,first" or lname="last, first"
	// also some clients (bizzes) lacked fnames
	if($client['fname'] == '') {
		$lastname = (string)$client['lname'];
		if(($comma = strpos($lastname, ',')) !== FALSE) {
			$client['lname'] = trim(substr($lastname, 0, $comma));
			$client['fname'] = trim(substr($lastname, $comma+1));
		}
		else $client['fname'] = '.';
	}
	$client['lname'] = "{$client['lname']}SC";
	$client['mapID'] = $client['mapID']+1400;
	return $client;
}

function decodeComment($str) {
	return str_replace('#EOL#', "\n", $str);
}


function myfgetcsv($strm, $pos, $delimiter) {
	global $petSectionHasBegun, $namespace;
	if($delimiter) return fgetcsv($strm, $pos, $delimiter);
	// else it is XML
	global $leftover;
	for($s = "$leftover"; !feof($strm) && ($start = strpos($s, "<{$namespace}Row")) === FALSE; ) 
		$s .= fgets($strm);
	$rowTagLength = strpos($s, ">", $start)+1-$start;
	if(!$s) return null;
//echo "<p>".htmlentities("</{$namespace}Row>");exit;

//if(strpos($s, "Oswego Anaimal Hospital")){
//echo "<p>\n[[[".$s.']]]\n <hr>\n('.substr($s, 0, (strpos($s, "</{$namespace}Row>")+strlen("</{$namespace}Row>"))).')<p>';echo "\n BONK!";exit;}

	while(!feof($strm) && (strpos($s, "</{$namespace}Row>") === FALSE))
		$s .= fgets($strm);
		
//echo "<p>[[[".$s.']]] ('.(strpos($s, "</{$namespace}Row>")).')<p>';
	if(strpos($s, "</{$namespace}Row>") === FALSE) {echo "INCOMPLETE ROW:<hr>$s<br>";}
	else {
		$end = strpos($s, "</{$namespace}Row>");
		$endSheet = strpos($s, "</{$namespace}Worksheet>");
		if($endSheet !== FALSE  && $endSheet < $end) 
			// if sheet ends before the row ends, then row is a pet
			$petSectionHasBegun = true;
		$leftover = substr($s,  $end+strlen("</{$namespace}Row>"));
//echo htmlentities($leftover).'</br>';		
//if(strpos($s, 'Erica ')) echo "<hr>".htmlentities(substr($s, $start+$rowTagLength,$end-$start));
		$s = str_replace("<{$namespace}Cell ss:StyleID=\"s17\"/>", "<{$namespace}Cell ss:StyleID=\"s17\"></{$namespace}Cell>", $s); // handle empty cells
		$row = array_map('trim', array_map('strip_tags', explode("</{$namespace}Cell>", substr($s, $start+$rowTagLength,$end-$start))));
//echo "<hr>".print_r($row,1)."<hr>";
		$fullSlot = false;
		if($row) while(!$fullSlot) if($fullSlot = array_pop($row)) $row[] = $fullSlot;
		return $row;
	}
}


function convert_pops_AlternateLastName($str, &$client) {
	handleFnameSpaceLname($str, $client, $fnameKey='fname', $lnameKey='lname');
	return $client;
}

function convert_pops_PrimaryStaffID($str, &$client) {
	global $providersByEmployeeId;
	$client['defaultprovider'] = $providersByEmployeeId[$str];
	return $client;
}

function convert_pops_SecondaryStaffID($str, &$client) {
	global $providersByEmployeeId, $notes;
	$providerid = $providersByEmployeeId[$str];
	if($providerid) {
		$name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $providerid LIMIT 1");
		$notes[] = 'Secondary Sitter: '.$name;
	}
	return $client;
}

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(' ', $str));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
}

function saveOtherContact($label, $newClientId, $otherContact) {
	if($otherContact['name']) $noteToAdd[] = $otherContact['name'];
	if($otherContact["homephone"]) $noteToAdd[] = "ph:{$otherContact['homephone']}";
	if($noteToAdd)
		$noteToAdd[] = "has".($otherContact['haskey'] ? '' : ' no')." key";
	if(!$noteToAdd) return;
	$noteToAdd = "\n".mysqli_real_escape_string(join(' ', $noteToAdd));
	updateTable('tblclient', array('notes'=>sqlVal("CONCAT(notes, '$noteToAdd')")), "clientid = $newClientId", 1);
}

/*
Notes on Precise Pet Care:

Vets: https://printersrowdogwalk.precisessl.com/clients/vets

Strategy:
Grab vets HTML.  Import separately.
Grab clients XLSX.
Grab pets XLSX.
Copy pets sheet to second sheet after clients.


Vets HTML

function nextStrong($strm or $string) {
    grab next <strong>...</strong>
    with contents, return string after ": "
}
start: [title="Select ]
find [value="XXX"]
city = XXX
find [value="XXX"]
name = XXX
find [<textarea name="v_address"]
address = textarea contents (multiline)
find [value="XXX"]
phone = XXX
find [<textarea name="v_notes"]
notes = textarea contents (multiline)



Clients section:
ID - ignore
Name - fname
Last Name - lname
Pets - ignore
Cell Phone - cellphone
Work Phone - workphone
Home Phone - homephone
Primary Phone - Cell/Work/Home: modify other field
Primary Email Address(es) - multiline, email, notes
Status - ignore
Active - Yes (No?)
Address - multiline.  street1\nstreet2
City
State/Zip - state
Zip - zip
Directions - directions
Desired Time Frame - officenotes
Notes to Manager - officenotes
Notes to Sitter - officenotes
Visit Notes from Sitter - officenotes
Primary Sitter - N/A (only examples were N/A)
Secondary Sitter - N/A (only examples were N/A)
Creation Date - ignore
Date of First Service - Notes
Alternate Contact & Phone - ?
Emergency Contact & Phone - ?
Others With Home Access - officenotes
Alternate Email Address(es) - multiline, email2, notes
Uses Keys - Yes = !nokeyrequired
Key/Door Code - gragegatecode
Alarm Company & Phone Number - alarmcompany
Location of Alarm Panel - +alarminfo
Alarm Codes - +alarminfo
Instructions to Arm/Disarm - +alarminfo
Home Access/Parking Notes - parkinginfo
House Checklist - custom1
Regular Visit Checklist - custom2
Overnights/Vacation Checklist - custom3
Trash/Recycling - custom4
Pet Waste Disposal - custom5
Cleaning Supplies - custom6
Flashlights/Headlamps - custom7
Pet Carriers - leashloc
Supplies - custom8
Sitters and Pet(s) sleep - custom9
House Notes - custom10
Walking Route/Trails - custom11
Other Notes - custom12
Vet - multiline: 1: name - match to vets input earlier
IGNORE New Vet City    New Vet Name    New Vet Address    New Vet Phone Number    Nearest Vet    Discount    Auto Invoicing    Automatic Surcharges    Additional Pet Pricing
Custom Prices - custom13
IGNORE: Effective Date for Prices    Balance    Total Income    Income/Month
Referral    Referral/Other - custom14

Pets Section
Name - name
Owner - look up owner ptr
Animal - type
Breed/Type - breed
Born/Age - dob <trim(substr($trimval, 0, strpos($trimVal, ' (')))>
Checklist - +notes
Photo - ignore
Active - Yes
Color/Markings - color   
Gender - sex   
Spayed/Neutered - fixed   
Weight - petcustom1
My Pet Stays - petcustom2
Personality -petcustom3   
Pet Routine - petcustom4  
Training Commands - petcustom5   
Feeding/Brand of Food - petcustom6   
Vaccinations - petcustom7   
Medical Concerns - petcustom8   
Medication/Health Notes - petcustom9   
Pet's Vet - petcustom10   
IGNORE New Vet City    New Vet Name    New Vet Address    New Vet Phone Number    Attached Files
Office Notes - petcustom11 PRIVATE    Attached Files














*/