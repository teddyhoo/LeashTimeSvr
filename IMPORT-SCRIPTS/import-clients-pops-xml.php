
<?// import-clients-pops-xml.php

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


// Cusotm example: https://leashtime.com/import-clients.php?nocheck=1&file=dogcampla/clients-with-map.csv&custom=1&customfields=custom1|import|text,custom9|uid|oneline
$starttime = microtime(1);

// POPS CONVERSION FUNCTIONS
function convert_pops_status($status, &$client) {
	$client['active'] = $status == 'Active' ? 1 : 0;
	$client['activeHasBeenSet'] = true; 	
	return $client;
}

function OLDconvert_pops_email($email, &$client) {
	global $notes;
	$email = explode(', ', $email);
	if($email) $client['email'] = $email[0];
	if(count($email) > 1) {
		$client['email2'] = $email[1];
		$notes[] = 'Multimple emails: '.join(', ', $email);
	}
	return $client;
}

function convert_pops_email($trimval, &$client) { // handles multiple emails
	$trimVal = trim((string)$trimVal);
	if(!$trimval) return $client;
	foreach(decompose($trimval, ",") as $x) {
		foreach(decompose($x, ";") as $x1) {
			if($x1)	{
				$emails[] = $x1;
			}
		}
	}
	foreach($emails as $email) {
		if(!isEmailValid($email)) {
			echo "<br><font color=red>BAD EMAIL: $email</font>";
			$client['notes'][] = "BAD EMAIL: $email";
		}
		else if(!$client['email']) $client['email'] = $email;
		else if(!$client['email2']) $client['email2'] = $email;
		else $client['notes'][] = "Other email: $email";
	}
	return $client;
}

function decompose($str, $delim) {
	return array_map('trim', explode($delim, $str));
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

if($TEST) echo "Will set Referral Categories: ".join(', ', array_values($referralTypes))."<p>";


extract($_REQUEST);

if($custom) $mapFile = "/var/data/clientimports/$file";
else $mapFile = "/var/data/clientimports/$map";  // map is a request param here
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
if(!$_REQUEST['file']) {echo "No File supplied."; exit;}

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

$initializePOPsCustomFields = TRUE;
if($initializePOPsCustomFields) {
	$existingProperties = fetchCol0("SELECT value FROM tblpreference WHERE property LIKE 'custom%'");
$existingProperties = array('long-time client|1|boolean|1','needs a confirmation on first visit|1|boolean|1','needs a confirmation daily|1|oneline|0','specific referral source|1|oneline|0');
//print_r($existingProperties);exit;// Array ( [0] => long-time client|1|boolean|1 [1] => needs a confirmation on first visit|1|boolean|1 [2] => needs a confirmation daily|1|oneline|0 [3] => specific referral source|1|oneline|0 ) 
	
	deleteTable('tblpreference', "property LIKE 'custom%' OR property LIKE 'petcustom%'", 1);
	doQuery(
		"INSERT INTO `tblpreference` (`property`, `value`) VALUES
			('custom1', 'Trash Inside|1|oneline|0'),
			('custom2', 'Trash Outside|1|oneline|0'),
			('custom3', 'Breaker Box Location|1|oneline|0'),
			('custom4', 'Water Shutoff Location|1|oneline|0'),
			('custom5', 'Thermostat|1|oneline|0'),
			('custom6', 'Waste Disposal|1|oneline|0'),
			('custom7', 'Cleaning Supplies|1|oneline|0'),
			('custom8', 'Flashlight|1|oneline|0|1|oneline|0'),
			('custom9', 'Emergency Work Authorized|1|oneline|0'),
			('custom10', 'Emergency Vet Treatment Authorized|1|oneline|0'),
			('custom11', 'Trash Instructions|1|text|0'),
			('custom12', 'Mail and News|1|text|0'),
			('custom13', 'Parking|1|text|0'),
			('custom14', 'Lights and Blinds|1|text|0'),
			('custom15', 'Plants|1|text|0')
			"
			);
	$customCount = fetchRow0Col0("SELECT count(*) FROM tblpreference WHERE property LIKE 'custom%'")+1;
	foreach($existingProperties as $val) {
		insertTable('tblpreference', array('property'=>"custom$customCount",'value'=>$val), 1);
		$customCount++;
	}
//Aggressive	AggressionDetails	Medications	FoodLocation	LitterBoxLocation	LeashLocation	CarrierLocation	VaccinationsCurrent	VetHasCC
//custom1	custom2	custom3	custom4	custom5	custom6	custom7	custom7	custom8
	
	doQuery(
		"INSERT INTO `tblpreference` (`property`, `value`) VALUES
			('petcustom1', 'VaccinationsCurrent|1|boolean|0'),
			('petcustom2', 'Vet Has CC On File|1|boolean|0|1|oneline|0'),
			('petcustom3', 'Aggressive|1|boolean|0'),
			('petcustom4', 'Aggression Details|1|text|0'),
			('petcustom5', 'Medications|1|text|0'),
			('petcustom6', 'Food Location|1|text|0'),
			('petcustom7', 'Litter Box Location|1|text|0'),
			('petcustom8', 'Leash Location|1|text|0'),
			('petcustom9', 'Carrier Location|1|text|0')
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

//print_r($providersByEmployeeId);exit;

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
		//$conv = $conversions[$i];
		$conv = $map[$dataHeaders[$i]];
		if($conv == 'x') continue;
		if(strpos($conv, '|')) { // contact field
			$parts = explode('|', $conv);
			if($parts[0] == 'contact') {
				if($parts[2] == 'haskey' && $field == 'False') continue;
				if($parts[1] == 'emergency') $emergencyContact[$parts[2]] = $field;
				else if($parts[1] == 'neighbor') $neighborContact[$parts[2]] = $field;
				else if($parts[1] == 'emergency3') $emergencyContact3[$parts[2]] = $field;
				else if($parts[1] == 'other') $otherContact[$parts[2]] = $field;
			}
			if($parts[0] == 'key') {
				$key[$parts[1]] = $field;
			}
		}
		else if($conv == 'alarminfo/-') {
			$alarminfo[] = $dataHeaders[$i].': '.$field;
		}
		else if($conv == 'notes/-') {
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
		$mapID = $client['mapID'];
		if(!$client['activeHasBeenSet']) $client['active'] = 1;  // see convert_setClientActive
		if(!$TEST) {
			if($client['zip']) {
				if(strlen($client['zip']) < 5)
					$client['zip'] = sprintf("%05d", $client['zip']);
			}
			if($refreshData && isset($clientMap[$mapID]) && strpos($mapFile, 'bluewave')) {
				$client['clientid'] = $clientMap[$mapID];
				refreshBWClient($client);
				echo "<p>Refreshed CLIENT #{$clientMap[$mapID]} {$client['fname']} {$client['lname']}<br>";
			}
//else if($client['lname'] == 'Driver') {print_r($client);exit;} else {continue;
			else {
				saveNewClient($client);
				$newClientId = mysqli_insert_id();
				$clientsCreated++;
				if($mapID) $clientMap[$mapID] = $newClientId;
				echo "<p>Created CLIENT #$newClientId {$client['fname']} {$client['lname']}<br>";
			}
		}
		else {
			$clientsFound++;
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
					if(!$TEST) echo "<font color=red>Bad custom field [$field].  Could not populate this field with [$val] for {$client['lname']} [{$client['mapID']}]</font>";
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
		// PROCESS PET ROW
		foreach($row as $i => $field) {
			if(!$field) continue;
			$conv = $petmap[$dataHeaders[$i]];
			if($conv == 'x') continue;
			else if(strpos($conv, 'convert_') === 0) // conversion must return $client
				$client = call_user_func_array($conv, array($field, &$pet));
			else if(strpos($conv, 'petcustom') === 0) {
				if(strpos($_SESSION['preferences'][$conv], '|boolean|') !== FALSE)
					$custom[$conv] = $field == "False" ? '0' : '1';
				else $custom[$conv] = str_replace('#EOL#', "\n", str_replace('\n', "\n", $field));
			}
			else $pet[$conv] = $field;
		}
		unset($pet[0]);
		unset($pet[null]);
		unset($pet['']);
		unset($pet[' ']);
		if(!(isset($pet['name']))) echo "<p><font color=red>Bad row: $n ".print_r($pet,1).'</font>';
		else { 	// CREATE OR UPDATE CLIENT ROW
			if($pet['ignore']) continue;
			if(!$TEST) {
					$pet['fixed'] = $pet['fixed'] ? $pet['fixed'] : '0';
					$pet['active'] = $pet['active'] ? $pet['active'] : '0';
echo "<p>PET RAW: ".print_r($pet, 1);					
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
						if(!$TEST) echo "<font color=red>Bad custom pet field [$field].  Could not populate this field with [$val] for {$client['lname']} [{$client['mapID']}]</font>";
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

function convert_pops_clientid($popsid, &$pet) {
	global $clientMap;
	if($clientMap[$popsid]) $pet['ownerptr'] = $clientMap[$popsid];
	return $pet;
}

function convert_pops_Female($val, &$pet) {
	$pet['sex'] = $val == 'True' ? 'f' : 'm';
	return $pet;
}

function convert_pops_inactivePet($val, &$pet) {
	$pet['active'] = $val == 'True' ? '0' : '1';
	return $pet;
}

function convert_pops_Neutered($val, &$pet) {
	$pet['fixed'] = ($val == 'True');
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
	
function initializeBluewaveSitters() {
	global $bluewaveSitters;
	if(!$bluewaveSitters) {
		$bluewaveSitters = fetchAssociationsKeyedBy("SELECT employeeid, fname, lname, providerid FROM tblprovider WHERE employeeid IS NOT NULL", 'employeeid');
		if(!$bluewaveSitters) $bluewaveSitters = -1;
	}
	return $bluewaveSitters ? 0 : -1;
}	
	
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

// always put this function name in instead of 'active' in the conversions line unless there is some other custom method
function convert_setClientActive($status, &$client) {
	$client['active'] = $status; 
	$client['activeHasBeenSet'] = true; 
	return $client;
}	

function convert_bluewave_level($level, &$client) {
	if($level != 'C') $client['prospect'] = 1; 
	return $client;
}	
	
function convert_bluewave_referral($type, &$client) {
	global $referralTypes;
	if($val = $referralTypes[$type]) $client['custom']['custom5'] = $val;
	return $client;
}
	
function convert_bluewave_referral_comment($comment, &$client) {
	if($comment) 
		$client['custom']['custom5'] = $client['custom']['custom5'].': '.decodeComment($comment);
	return $client;
}

function decodeComment($str) {
	return str_replace('#EOL#', "\n", $str);
}


function myfgetcsv($strm, $pos, $delimiter) {
	global $petSectionHasBegun;
	if($delimiter) return fgetcsv($strm, $pos, $delimiter);
	// else it is XML
	global $leftover;
	for($s = "$leftover"; !feof($strm) && ($start = strpos($s, '<ss:Row>')) === FALSE; ) 
		$s .= fgets($strm);
	if(!$s) return null;
//echo "<p>[[[".htmlentities($s).']]] ('.(strpos($s, '</ss:Row>') === FALSE).')<p>';		
	while(!feof($strm) && (strpos($s, '</ss:Row>') === FALSE))
		$s .= fgets($strm);
	if(strpos($s, '</ss:Row>') === FALSE) echo "INCOMPLETE ROW:<br>$s<br>";
	else {
		$end = strpos($s, '</ss:Row>');
		$endSheet = strpos($s, '</ss:Worksheet>');
		if($endSheet !== FALSE  && $endSheet < $end) 
			// if sheet ends before the row ends, then row is a pet
			$petSectionHasBegun = true;
		$leftover = substr($s,  $end+strlen('</ss:Row>'));
//echo htmlentities($leftover).'</br>';		
		$row = array_map('trim', array_map('strip_tags', explode('</ss:Cell>', substr($s, $start+strlen('<ss:Row>'),$end-$start))));
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
	$client['defaultproviderptr'] = $providersByEmployeeId[$str];
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

function convert_pops_Inactive($str, &$client) {
	$client['active'] = $str == 'False' ? '1' : '0';
	$client['activeHasBeenSet'] = true; 	
//echo "<br>{$client['fname']} {$client['lname']} is active: [Inactive:$str] == [{$client['active']}]<br>";	
	return $client;
}

function convert_pops_DateCreated($str, &$client) {
	global $notes;
	if($str)
		$notes[] = "Added in Power Pet Sitter: $str";
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