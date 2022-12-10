<?// import-clients-details-bluewave.php

/*

This script parses a Bluewave Client Details Report, a file starting with a WAG, followed by sections for each of the clients in the WAG.

Sample: petaholics/Client-Detail-Report-Bluewave-Petaholics-1.htm

http://iwmr.info/petbizdev/import-clients-details-bluewave.php?file=petaholics/Client-Detail-Report-Bluewave-Petaholics-1.htm

OPTION: privatealarm=1


*/
set_time_limit(120);
/* KEYS:
clientname,street1,zip,state,city,bluewaveid,bluewaveKey,bluewaveSpouse,homephone,bluewaveAltPhone,workphone,cellphone,email,email2,
bluewaveSitter,bluewaveStatus,bluewaveAltSitter,bluewaveUserName,bluewaveReferral,notes,bluewaveCrossStreet,
bluewaveNeighborhood,emergencyContact1,notes_emergencyContact1,phone_emergencyContact1,emergencyContact2,
alarm,notes_emergencyContact2,phone_emergencyContact2,emergencyContact3,notes_emergencyContact3,phone_emergencyContact3,

pets,bluewaveLeashCarrier,bluewaveCleaningSupply,bluewaveFood,bluewaveEmptyBags,bluewaveWaste,
bluewaveCatBox,bluewaveHomeRemarks1,bluewaveTrash,bluewaveMaintenance,bluewaveLights,bluewaveBlinds,
bluewaveHouseKeeper,bluewaveYardman,bluewaveParking,bluewaveMailNewspaper,bluewaveServiceProviders,
bluewaveOthersWithAccess,bluewavePlants,bluewave_sched_Morning,bluewave_sched_TV,bluewave_sched_Cooktop/Oven:,
bluewave_sched_Midday,bluewave_sched_VCR,bluewave_sched_Microwave:,bluewave_sched_Evening,bluewave_sched_Stereo,
bluewave_sched_Refridgerator:,bluewave_sched_Late,bluewave_sched_Phone,bluewave_sched_Coffee:
*/

require "gui-fns.php";
require "client-fns.php";
require "pet-fns.php";
require "custom-field-fns.php";
require_once "field-utils.php";

$conversionMap = "clientname|convertClientName||bluewaveSitter|convertBluewaveSitter||bluewaveStatus|convertBluewaveStatus"
													."||bluewaveAltSitter|convertBluewaveAltSitter||bluewaveNeighborhood|convertBluewaveDirections"
													."||email|convertBluewaveEmail_Cd||bluewaveKey|convertBluewaveKey"
													."||emergencyContact1|convertEmergencyContact||emergencyContact2|convertEmergencyContact||emergencyContact3|convertEmergencyContact";
$conversionMap = 	explodePairsLine($conversionMap);

$simpleMap = "bluewaveLeashCarrier|leashloc||bluewaveFood|foodloc";
$simpleMap = 	explodePairsLine($simpleMap);

$customTranslationMap = "bluewaveReferral|custom5||bluewaveSpouse|custom6||bluewaveAltPhone|custom1||bluewaveCleaningSupply|custom7"
													."||bluewaveEmptyBags|custom8||bluewaveWaste|custom9||bluewaveCatBox|custom10"
													."||bluewaveHomeRemarks1|custom11||bluewaveTrash|custom12||bluewaveMaintenance|custom13"
													."||bluewaveLights|custom14||bluewaveBlinds|custom15||bluewaveHouseKeeper|custom16"
													."||bluewaveYardman|custom17||bluewaveParking|custom18||bluewaveMailNewspaper|custom19"
													."||bluewaveServiceProviders|custom20||bluewaveOthersWithAccess|custom21||bluewavePlants|custom22"
													."||bluewave_sched_TV|custom23||bluewave_sched_Cooktop/Oven:|custom24||bluewave_sched_VCR|custom25"
													."||bluewave_sched_Microwave:|custom26||bluewave_sched_Stereo|custom27||bluewave_sched_Refridgerator:|custom28"
													."||bluewave_sched_Phone|custom29||bluewave_sched_Coffee:|custom30||bluewaveAlternate|custom33"
													;

$customTranslationMap .= "||Disposition|petcustom1||Bites|petcustom2||Shots|petcustom3||Medical|petcustom4||Routine|petcustom5"
													."||Vet|petcustom6||Tag/Chip|petcustom7||ER Max|petcustom8||Food|petcustom9||Other|petcustom10||Remarks|petcustom12";

$customTranslationMap = 	explodePairsLine($customTranslationMap);								

if($_REQUEST['keys']) {
	echo "<u>Additional client details:</u><p>".join('<br>', array_keys($conversionMap));
	echo "<p><u>Additional client details:</u><p>".join('<br>', array_keys($customTranslationMap));
	exit;
}

$straightTransferKeys = "street1,street2,city,state,zip,homephone,workphone,cellphone,email2,notes";
$straightTransferKeys = explode(',', $straightTransferKeys);

$noOpKeys = "bluewaveid,bluewaveUserName,notes_emergencyContact1,phone_emergencyContact1"
						.",notes_emergencyContact2,phone_emergencyContact2,notes_emergencyContact3,phone_emergencyContact3"
						.",bluewave_sched_Morning,bluewave_sched_Midday,bluewave_sched_Evening,bluewave_sched_Late,bluewaveCrossStreet";
$noOpKeys = explode(',', $noOpKeys);



extract($_REQUEST);
$mode = $TEST ? '(Testing)' : '(Importing)';
if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db $mode</h3>";
else echo "<h2 style='color:orange;'>DB: $db $mode</h2>";
if(!in_array('tempClientMap', fetchCol0("SHOW TABLES"))) echo "<h3 style='color:red;'>WARNING: No Bluewave clients have yet been imported.</h3>";
echo "<hr>";


$f = $file;
$file = "/var/data/clientimports/$file";
if(strpos($file, '.zip')) {
	$goZippy = 1;
	echo "Opening ZIP: $file<p>";
	$zip = zip_open($file);
	if(!$zip) {
		echo "Could not open $file.";
		exit;
	}
}

do {
	if($goZippy) {
		$entry = zip_read($zip);
		if(!$entry) break;
		echo "Opened entry [".zip_entry_name($entry)."]<p>";
		$strm = fopen("data://text/plain,".zip_entry_read($entry, zip_entry_filesize($entry)) , 'r');
		zip_entry_close($entry);
		echo "Closed entry [".zip_entry_name($entry)."]<p>";
	}
	else $strm = fopen($file, 'r');



while(getLine($strm)) {
if($line && !$done) {for($ii=0;$ii<strlen($line);$ii++)echo ord($line[$ii]).',';echo "<br>";$done=1;}
	$linecount++;
	if(!$bizName && strpos($line, 'w_str_frcst') !== FALSE) {
		$bizName = nextTDStripped($strm);
		echo "<h2 style='color:green;'>Source: $bizName</h2><hr>";
	}
	$newClients = array();
	if($line == '<div align="left" class="text">') {
		$details = startNewClient($strm);
		$namesFound[] = $details['clientname'];
		$newClients[$details['bluewaveid']] = ($newClient = convertClient($details));
		if(!$details['bluewaveid']) {
			echo "NO BW ID!<p>";
			print_r($details);
			echo "<hr>";
			print_r($newClient);
			echo "<p>";
		}
		$clientid = fetchRow0Col0("SELECT clientptr FROM tempClientMap WHERE externalptr = {$details['bluewaveid']} LIMIT 1");
		$client = null;
		if(FALSE && $clientid) {
			//$ltClientsByBWIds[$details['bluewaveid']] = getClient($client);
			echo "<h4 style='color:red;'>Client [{$newClient['fname']} {$newClient['lname']}] ($clientid) (BW: {$details['bluewaveid']}) already found in LeashTime.</h4>";
			//$client = getClient($clientid);
		}
		else {
			$fname = mysql_real_escape_string($newClient['fname']);
			$lname = mysql_real_escape_string($newClient['lname']);
			if($clientid) {
				$matches = fetchAssociations("SELECT * FROM tblclient WHERE clientid = $clientid");
				$how = 'by BW ID';
			}
			else {
				$matches = fetchAssociations("SELECT * FROM tblclient WHERE fname = '$fname' AND lname = '$lname'");
				$how = 'by name';
			}
			if(count($matches) > 1)
				echo "<h4 style='color:pink;'>Client [{$newClient['fname']} {$newClient['lname']}]"
							." (BW: {$details['bluewaveid']}) found ($how) but there are ".count($matches)." clients with that name in LeashTime.</h4>";
			else if(count($matches) == 0) {
				echo "<h4 style='color:lightblue;'>Client [{$newClient['fname']} {$newClient['lname']}]"
							." (BW: {$details['bluewaveid']}) found but there are no clients with that name in LeashTime.</h4>";
				$client = array('dummy');
			}
			else if(count($matches) == 1) {
				$client = $matches[0];
				echo "<h4 style='color:red;'>Client [{$newClient['fname']} {$newClient['lname']}] ({$client['clientid']})"
							." found ($how) (BW: {$details['bluewaveid']}) in LeashTime.</h4>";
			}
		}
		if($client) {
			dumpComparison($client, $newClient, !$TEST);
			$namesProcessed[] = $details['clientname'];
		}

		echo "<p>";
	}
}
echo "<p>";
echo "Read lines: ($linecount)<p>";
foreach($newClients as $bwid => $newClient) {
	echo "<p>";
	dumpClient($newClient);
}
	

fclose($strm);
echo "<hr>Found: (".count($namesFound).")<p>";
foreach((array)$namesFound as $name) echo "$name<br>";
echo "<hr>Processed: (".count($namesProcessed).")<p>";
foreach((array)$namesProcessed as $name) echo "$name<br>";

} while($goZippy);  // close do;

function applyModifications(&$client, &$newClient) {
	// DO NOT OVERWRITE AN EXISTING PET DOB
}



function convertClient(&$details) {
	global $conversionMap, $simpleMap, $customTranslationMap, $straightTransferKeys, $noOpKeys;
	$client = array();
	foreach($details as $k => $v) {
		if(!$v || !trim($v)) continue;
		if(in_array($k, $noOpKeys)) ; // ignore
		else if(in_array($k, $straightTransferKeys)) $client[$k] = $details[$k];
		else if(in_array($k, array_keys($simpleMap))) $client[$simpleMap[$k]] = $details[$k];
		else if(in_array($k, array_keys($customTranslationMap))) $client['custom'][$customTranslationMap[$k]] = $details[$k];
		else if(in_array($k, array_keys($conversionMap)))
			$client = call_user_func_array($conversionMap[$k], array($k, $details, $client));
		else if($k == 'pets') convertPets($details, $client);
		else if($k == 'alarm') $client['alarmInfo'] = convertAlarm($details['alarm'], $client);
		else echo "PROBLEM: field [$k] not handled by converter for ".print_r($details,1).'<br>';
	}
	return $client;
}


function convertAlarm($alarm, &$client) {
	//Array ( [co] => [phone] => [in] => [out] => [password] => [instr] => ) 
	if($alarm['co']) $client['alarmcompany'] = $alarm['co'];
	if($alarm['phone']) $client['alarmcophone'] = $alarm['phone'];
	$fields = array('in'=>'In:', 'out'=>'Out:', 'password'=>'Pasword:', 'instr'=>'Instructions:\n');
	foreach($fields as $k => $label) 
		if($alarm[$k]) $descr[] = "$label {$alarm[$k]}";
	if($_REQUEST['privatealarm']) {
		$parts = array();
		if($alarm['co']) $parts[] = "Alarm Company: {$alarm['co']}";
		if($alarm['phone']) $parts[] = "Alarm Co. Phone: {$alarm['phone']}";
		foreach((array)$descr as $part) $parts[] = $part;
		$client['custom']['custom32'] = join("\n", $parts);
//if($client['custom']['custom32']) {print_r($client);exit;}		
	}
	else if($descr) $client['alarminfo'] = join("\n", $descr);
}

function convertPets(&$details, &$client) {
	global $customTranslationMap;
	global $custPetFields;
	if(!$custPetFields) $custPetFields = getCustomFields('active', !'visitsheetonly', getPetCustomFieldNames());
	foreach($details['pets'] as $pet) {
		
		if($pet['age']) {
			$pet['dob'] = date('m/d/Y', strtotime("- {$pet['age']} years"));  // dob is a varchar!
			unset($pet['age']);
		}
		if($sexStr = strtoupper($pet['sex'])) {
			$pet['sex'] = strpos($sexStr, 'FEMALE') !== FALSE ? 'f' : 'm';
			$pet['fixed'] = strpos($sexStr, 'SPAYED') !== FALSE || strpos($sexStr, 'NEUT') !== FALSE ? '1' : '0';
		}
		$notes = array();
		//if($pet['Remarks']) $pet['notes'] = $pet['Remarks'];
		unset($pet['Remarks']);
//echo "{$pet['name']} BITES: [$val] [{$pet[$customTranslationMap['Bites']]}]<p>";	
//echo "{$pet['name']} SHOTS: [$val] [{$pet[$customTranslationMap['Shots']]}]<p>";	
		foreach($customTranslationMap as $k => $custField) {
			if($custField && $pet[$custField]) { // ignore remarks
				$val = $custPetFields[$custField][2] == 'boolean' 
								? ($pet[$custField] == 'Y' ? 1 : '0') 
								: $pet[$custField];
				$pet['customFields'][$custField] = $val;
			}
			unset($pet[$custField]);
		}
		$client['pets'][] = $pet;
	}
}

function convertBluewaveEmail_Cd($field, &$details, &$client) {
	$parts = explode('/', $details[$field]);
	$client['email'] = trim($parts[0]);
	return $client;
}

function convertBluewaveDirections($field, &$details, &$client) {
	$client['directions'] = join("\n\n", array($details['bluewaveNeighborhood'], $details['bluewaveCrossStreet']));
	return $client;
}

function convertBluewaveStatus($field, &$details, &$client) {
	$status = explode(' / ', $details[$field]);
	$client['active'] = $status[0] == 'AC';
	$client['prospect'] = $status[1] == 'P';
	return $client;
}

function convertBluewaveSitter($field, &$details, &$client) {
	$client['defaultproviderptr'] = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE CONCAT_WS(', ', lname, fname) = '".mysql_real_escape_string($details[$field])."' LIMIT 1");
	return $client;
}

function convertBluewaveAltSitter($field, &$details, &$client) {
	$client['custom']['custom4'] = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE employeeid = '{$details[$field]}' LIMIT 1");
	return $client;
}

function convertClientName($field, &$details, &$client) {
	$name = explode(', ', $details[$field]);
	$client['lname'] = $name[0];
	$client['fname'] = $name[1];
	return $client;
}

function convertBluewaveKey($field, &$details, &$client) {
	$client['keyDescription'] = $details[$field];
	return $client;
}


function convertEmergencyContact($field, &$details, &$client) {
	$num = substr($field, -1);
	if($num == 3) {
		foreach(array('emergencyContact3', 'notes_emergencyContact3', 'phone_emergencyContact3') as $k) 
			if($details[$k]) $parts[] = $details[$k];
		if($parts) $client['custom']['custom31'] = join("\n", $parts);
		return $client;
	}
	if($num == 1) $targetKey = 'emergency';
	else if($num == 2) $targetKey = 'neighbor';
	$client['contacts'][$targetKey] = array(
		'type'=>$targetKey,
		'name'=>$details[$field],
		'homephone'=>$details["phone_emergencyContact$num"],
		'note'=>$details["notes_emergencyContact$num"]);
	return $client;
}

function dumpComparison(&$client, &$newClient, $update=false) {
	if($newClient['custom']['custom6'])  $name = getFnameLname($newClient['custom']['custom6']);
	else if($newClient['custom']['custom1'])  $name = getFnameLname($newClient['custom']['custom1']);
	if($name) {
		$newClient['lname2'] = $name['lname'];
		$newClient['fname2'] = $name['fname'];
	}
	if(!$client['clientid']) {
		if($update) {
			$clientid = saveNewClient($newClient);
			$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid LIMIT 1");
			echo "<h4 style='color:red;'>CREATED Client ($clientid) [{$newClient['fname']} {$newClient['lname']}].</h4>";
		}
		else "<h4 style='color:red;'>WILL CREATE Client [{$newClient['fname']} {$newClient['lname']}].</h4>";
	}
	foreach($newClient as $k =>$val) {
		if(in_array($k, array('pets', 'custom', 'contacts'))) continue;
		if($val != $client[$k]) {
			echo "<hr><b><u>$k</u> old value: </b><br>";
			echo "<font color=lightyellow>".($client[$k] ? $client[$k] : "<i>Previously blank</i>")."</font><br>";
			$updated = '';
			$action = !$client[$k] ? 'ADDED' : ($k =='notes' ? 'APPENDED' : 'IGNORED');
			if($action == 'ADDED' || $action == 'APPENDED') $updated = "<span style='color:#99FF00;'>$action</span>" ;
			else if($action == 'IGNORED') $updated = "<span style='color:red;'>IGNORED</span>";
			if($action == 'APPENDED') $val = "{$client[$k]}\n$val";
			if($update && $action != 'IGNORED') {
				if($k == 'keyDescription') {
					if($key = fetchRow0Col0("SELECT keyid FROM tblkey WHERE clientptr = {$client['clientid']} LIMIT 1"))
						updateTable('tblkey', array('description'=>$val), "clientptr={$client['clientid']}", 1);
					else insertTable('tblkey', array('description'=>$val, 'clientptr'=>$client['clientid']), 1);
				}
				else updateTable('tblclient', array($k=>$val), "clientid={$client['clientid']}", 1);
			}
			//echo "<b>$updated "."New value: </b><br>";
			echo "<font color=lightgreen>$val</font><br>";
			
		}
	}
	echo "<hr>";
	dumpPetsComparison($client, $newClient, $update);
//if($client['clientid'] == 219) {print_r($newClient);exit;}	
	dumpCustomComparison($client, $newClient, $update);
	dumpContacts($client, $newClient, $update);
}

function getFnameLname($str, $fnameKey='fname', $lnameKey='lname') {
	$parts = explode(' ', $str);
	if(count($parts) == 1)
		$name[$fnameKey] = array_pop($parts);
	else if(count($parts)) {
		$name[$lnameKey] = array_pop($parts);
		if($parts) $name[$fnameKey] = join(' ', $parts);
	}
	return $name;
}




function dumpContacts($client, $newClient, $update) {
	if(!$update || !$newClient['contacts']) return;
	deleteTable('tblcontact', "clientptr = {$client['clientid']}", 1);
	foreach($newClient['contacts'] as $contact) {
		$contact['clientptr'] = $client['clientid'];
//echo "CONTACT: ";print_r($contact);exit;			
		insertTable('tblcontact', $contact, 1);
	}
//echo "CONTACTS: ";print_r($newClient['contacts']);exit;	
}

function dumpCustomComparison(&$client, &$newClient, $update=false) {
	$oldCustom = getClientCustomFields($client['clientid']); 
	$oldKeys = array_keys($oldCustom);
	$newKeys = array_keys($newClient['custom']);
	if(array_diff($oldKeys, $newKeys)) echo  "Custom fields: (".join(', ', array_diff($oldKeys, $newKeys)).") missing in new version.<br>";
	if(array_diff($newKeys, $oldKeys)) echo  "Custom fields: (".join(', ', array_diff($newKeys, $oldKeys)).") missing in old version.<br>";
	foreach($newClient['custom'] as $k => $v) {
		if($v != $oldCustom[$k]) {
			echo "<hr><b><u>$k</u> old value: </b><br>";
			echo "<font color=lightyellow>".($oldCustom[$k] ? $oldCustom[$k] : "<i>Previously blank</i>")."</font><br>";
			
			if($update) {
				if(!$oldCustom[$k]) $updated = "<span style='color:#99FF00;'>UPDATED</span>";
				else if($oldCustom[$k]) $updated = "<span style='color:red;'>IGNORED</span>";
				if(!$oldCustom[$k]) {
					replaceTable('relclientcustomfield', array('fieldname'=>$k, 'value'=>cleanseString($v), 'clientptr'=>$client['clientid']), 1);
					echo "<b>New value: </b><br>";
					echo "<font color=lightgreen>$v</font><br>";
				}
			}
			
		}
	}
}

function dumpPetsComparison(&$client, &$newClient, $update=false) {
	global $custPetFields;
//print_r($custPetFields);exit;
	$oldPets = array(); $newPets = array();
	foreach(getClientPets($client['clientid']) as $pet) $oldPets[$pet['name']] = $pet;
	foreach((array)$newClient['pets'] as $pet) $newPets[] = $pet['name'];
//echo "<font color=red>New Pets: [".print_r($newClient['pets'], 1)."]</font><br>";	
	if(array_diff($oldPets, $newPets)) echo  "Pets: (".join(', ', array_diff(array_keys($oldPets), $newPets)).") missing in new version.<br>";
	if(array_diff($newPets, array_keys($oldPets))) echo  "Pets: (".join(', ', array_diff($newPets, array_keys($oldPets))).") missing in old version.<br>";
	foreach((array)$newClient['pets'] as $pet) {
		$petDelta = array();		
		if(!$oldPets[$pet['name']]) {
			// insert new pet here
			if($update) {
				$pet['ownerptr'] = $client['clientid'];
				$petId = insertTable('tblpet', saveablePet($pet), 1);
				if($petId) echo "<span style='color:#99FF00;'>ADDED PET: {$pet['name']} ($petId)</span";
			}
		}
		else $petId = $oldPets[$pet['name']]['petid'];
		foreach($pet as $k =>$val) {
			if($k == 'customFields') continue;
			
			if($val != $oldPets[$pet['name']][$k]) {
				$petDelta[$pet['name']][$k] = array($oldPets[$pet['name']][$k], $val);
			}
		}
		if($petDelta) {
			foreach($petDelta as $name =>$vals) {
				echo "<p><font color=yellow>Pet [$name]:</font>"; 
				foreach($vals as $k =>$change) {
					echo "<hr><b><u>$k</u> old value: </b><br>";
					echo "<font color=lightyellow>".($change[0] ? $change[0] : "<i>Previously blank</i>")."</font><br>";
					$action = !$change[0] ? 'ADDED' : ($k =='notes' ? 'APPENDED' : 'IGNORED');
					$updated = '';
					if($action == 'ADDED' || $action == 'APPENDED') $updated = "<span style='color:#99FF00;'>$action</span>" ;
					else if($action == 'IGNORED') $updated = "<span style='color:red;'>IGNORED</span>";
					$val = $change[1];
					if($action == 'APPENDED') $val = "{$change[0]}\n$val";
					if($update && $action != 'IGNORED') updateTable('tblpet', array($k=>$val), "petid=$petId", 1);
					echo "<b>$updated New value: </b><br>";
					echo "<font color=lightgreen>$val</font><br>";
				}
			}
		}
		if($pet['customFields']) {
			foreach($pet['customFields'] as $k =>$val) {
				if($update) replaceTable('relpetcustomfield', array('petptr'=>$petId, 'fieldname'=>$k, 'value'=>cleanseString($val)), 1);
//echo "**UPDATE  ** [".print_r(array('petptr'=>$petId, 'fieldname'=>$k, 'value'=>cleanseString($val)), 1).']<p>';
				$pf = $custPetFields[$k][0];
				echo "<span style='color:#99FF00;'>ADDED $pf($k): </span> $val<p>" ; 
			}
		}
	}
}


function dumpClient(&$client) {
	//echo join(',', array_keys($client)).'<br>';
	foreach($client as $k => $v) {
		if($k != 'pets') echo "<b>$k:</b> ".print_r(htmlize($v), 1)."<br>";
		else {
			echo "<b>$k:</b> ";
			foreach($v as $pet) {
				echo "<div style='padding-left:30px;border:solid black 1px;width:500px'>";
					foreach($pet as $pk => $pv)
						echo "<b>$pk:</b> ".print_r(htmlize($pv), 1)."<br>";
				echo "</div>";
			}
		}
	}
	echo '<hr><p>';
}

function htmlize($s) {return str_replace("\n", '<br>', $s);}

function finishNewClient($strm, &$client) {
	global $line, $test, $col, $lineNum;
	
	while(getLine($strm)) if(strpos($line, '<table') !== FALSE) break;  // skip first TD
	
	nextLineStripped($strm); // eat a line
	
	$schedPairs = 'Morning|Morning||Mid-day|Midday||Evening|Evening||Late|Late||TV|TV||Cooktop/Oven|CooktopOven'
						.'||VCR/DVD|VCR||Stereo|Stereo||Microwave|Microwave||Refridgerator|Refrigerator||Phone|Phone||Coffee|Coffee';
	foreach(explode('||', $schedPairs) as $pair) {
		$pair = explode('|', $pair);
		$schedLabels[$pair[0]] = $pair[1];
	}
	
	while(getLine($strm)) {
		$remarksNum = 1;
		if(strpos($line, '</table') !== FALSE) break;
		if(strpos($line, '<tr') !== FALSE) $col = 0;
		if(strpos($line, '<td>') !== FALSE) $col++;
//if(strpos($line, 'Phone:')) echo "[$lineNum][$col] $line<br>";
//if(strpos($line, 'Remarks:') !== FALSE) echo "[$lineNum][$col] $line<br>";		
		if(strpos($line, 'Leash/Carrier:')) $client['bluewaveLeashCarrier'] = nextTDStripped($strm);
		else if(strpos($line, 'Cleaning Supply:')) $client['bluewaveCleaningSupply'] = nextTDStripped($strm);
		else if(strpos($line, 'Food:')) $client['bluewaveFood'] = nextTDStripped($strm);
		else if(strpos($line, 'Empty Bags:')) $client['bluewaveEmptyBags'] = nextTDStripped($strm);
		else if(strpos($line, 'Waste:')) $client['bluewaveWaste'] = nextTDStripped($strm);
		else if(strpos($line, 'Cat Box:')) $client['bluewaveCatBox'] = nextTDStripped($strm);
		else if(strpos($line, 'Remarks:')) {
			$client['bluewaveHomeRemarks'.$remarksNum] = nextTDStripped($strm);
			$remarksNum++;
			}
		else if(strpos($line, 'Trash/Recycle:')) $client['bluewaveTrash'] = nextTDStripped($strm);
		else if(strpos($line, 'Maintenance:')) $client['bluewaveMaintenance'] = nextTDStripped($strm);
		else if(strpos($line, 'Lights:')) $client['bluewaveLights'] = nextTDStripped($strm);
		else if(strpos($line, 'Blinds:')) $client['bluewaveBlinds'] = nextTDStripped($strm);
		else if(strpos($line, 'Housekeeper:')) $client['bluewaveHouseKeeper'] = nextTDStripped($strm);
		else if(strpos($line, 'Yardman:')) $client['bluewaveYardman'] = nextTDStripped($strm);
		else if(strpos($line, 'Parking:')) $client['bluewaveParking'] = nextTDStripped($strm);
		else if(strpos($line, 'Mail/Newspaper:')) $client['bluewaveMailNewspaper'] = nextTDStripped($strm);
		else if(strpos($line, 'Service Providers:')) $client['bluewaveServiceProviders'] = nextTDStripped($strm);
		else if(strpos($line, 'Others w/ Access:')) $client['bluewaveOthersWithAccess'] = nextTDStripped($strm);
		else if(strpos($line, 'Plants:')) $client['bluewavePlants'] = nextTDStripped($strm);
		else foreach($schedLabels as $label => $key) {
			if(strpos($line, $label)) {
				$nextTd = str_replace("\n", ' ', nextTDStripped($strm));
				if(in_array(substr($nextTd,0,-1), array_keys($schedLabels))) {
					$client['bluewave_sched_'.$key] = '';
					$client['bluewave_sched_'.$nextTd] = nextTDStripped($strm);
				}
				else $client['bluewave_sched_'.$key] = $nextTd;
			}
		}
	}
}

function readPets($strm) {
	global  $line, $lineNum; 
//echo "<font color=green>[$lineNum]a $line</font><br>";	
	while(getLine($strm)) if(strpos($line, '</tr>') !== FALSE) break;  // skip first TR
//echo "<font color=green>[$lineNum]b $line</font><br>";	

	while(getLine($strm)) {
		if(strpos($line, '<tr') === FALSE && strpos($line, '</table') === FALSE) continue;
		if(strpos($line, '</table') !== FALSE) break;
//echo "<font color=green>[$lineNum]c $line</font><br>";	
		$pets[] = readPet($strm);
	}
	
	return $pets;
}

function readPet($strm) {
	global  $line, $lineNum, $customTranslationMap; 
	while(getLine($strm)) if(strpos($line, '</td>') !== FALSE) break;  // skip shim
//echo "<font color=green>$line</font>";	
	$pet['name'] = nextTDStripped($strm);
echo "<font color=green>$lineNum: [{$pet['name']}] $line</font>";	
	$pet['type'] = nextTDStripped($strm);
	$pet['breed'] = nextTDStripped($strm);
	$pet['age'] = nextTDStripped($strm);
	$pet['sex'] = nextTDStripped($strm);
	if($val = trim(nextTDStripped($strm))) $pet[$customTranslationMap['Disposition']] = $val;
	$pet['color'] = nextTDStripped($strm);
	if($val = trim(nextTDStripped($strm))) $pet[$customTranslationMap['Bites']] = $val;
	if($val = trim(nextTDStripped($strm))) $pet[$customTranslationMap['Shots']] = $val;
	do {
		$row = getRow($strm);
		$content = $row[2];
		$key = trim(substr($content, 0, ($end = strpos($content, ':'))));
		if($key == 'Vet') {  // Tag/Chip: may NOT hae a following space
			$pet[$customTranslationMap['Vet']] = substr($content, $end+2, ($start = strpos($content, 'Tag/Chip:'))-($end+2));
//echo "<font color=red>".($end+2)."LEN: [".(($start = strpos($content, 'Tag/Chip:'))-($end+2))."]<br>$content<br>[{$pet[$customTranslationMap['Vet']]}]<br></font>";			
			$end = $start + strlen('Tag/Chip:');
			$pet[$customTranslationMap['Tag/Chip']] = substr($content, $end, ($start = strpos($content, 'ER Max: '))-$end);
			$start = $start + strlen('ER Max: ');
			$pet[$customTranslationMap['ER Max']] = substr($content, $start);
		}
		else $pet[$customTranslationMap[$key] ? $customTranslationMap[$key] : $key] = substr($content, $end+2);
	} while(strpos($content, 'Vet:') !== 0);
	//$pet['notes'] = join("\n", $pet['details']);
	//unset($pet['details']);
	return $pet;
}

function getRow($strm) {
	global  $line, $lineNum; 
	while(getLine($strm)) {
		if(strpos($line, '<tr>') !== FALSE || strpos($line, '</table') !== FALSE) break;  // skip shim
	}
	if(strpos($line, '</table') !== FALSE) return -1;
	$row = array();
	while(strpos($line, '</tr>') === FALSE)
		$row[] = nextTDStripped($strm);
//echo "[[".print_r($row, 1).']]<p>';		
	return $row;
}

function startNewClient($strm) {
	global $line, $test, $col, $lineNum;
	$client = array();
	
	/*while(getLine($strm)) if(strpos($line, '</td>') !== FALSE) break;  // skip first TD
	nextLineStripped($strm); // eat a line
	$client['clientname'] = nextLineStripped($strm); */
	while(!($td = trim(nextTDStripped($strm)))) ;
	//echo "TD: [$td]<br>";
	$client['clientname'] = $td;
	
	while(getLine($strm)) if($line == '</tr>') break;  // skip first TR
	while(getLine($strm)) if(strpos($line, '<td colspan="2" align="left" valign="top">') === 0) break; // start address
	$client['street1'] = substr($line, ($s = strpos($line, '>')+1), (strrpos($line, '<') - $s));
	while(getLine($strm)) {
		if($line == '</tr>') break;
		else if(!$line) continue;
		else {
			$line = strip_tags($line);
			if(strpos($line, '&nbsp;'))
				list($client['city'], $client['state'], $client['zip']) = explode('&nbsp;', $line);
			else $client['street1'] = $line;
		}
	}
	$state = '';
	while(getLine($strm)) {
		if(strpos($line, '<td>') !== FALSE) $col++;
//if(strpos($line, 'Phone:')) echo "[$lineNum][$col] $line<br>";
//if(strpos($line, 'Remarks:') !== FALSE) echo "[$lineNum][$col] $line<br>";		
		if(strpos($line, 'Pet Information')) {
			$client['pets'] = readPets($strm);
			finishNewClient($strm, $client);
			break;
		}
		if(strpos($line, '<strong>Id:')) $client['bluewaveid'] = nextLineStripped($strm);
		else if(strpos($line, '<strong>Key:')) $client['bluewaveKey'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Spouse/Ptnr')) $client['bluewaveSpouse'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Home:')) $client['homephone'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Alternate:')) $client['bluewaveAlternate'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Work:')) $client['workphone'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Cell:')) {$test=1;$client['cellphone'] = nextTDStripped($strm);$test=0;}
		else if(strpos($line, '<strong>Email/Cd')) $client['email'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Alt. Email:')) $client['email2'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Primary Str:')) $client['bluewaveSitter'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Status / Lvl:')) $client['bluewaveStatus'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Alternate Str:')) $client['bluewaveAltSitter'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Username:')) $client['bluewaveUserName'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Referral:')) $client['bluewaveReferral'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Remarks:') !== FALSE && $col == 2) $client['notes'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Cross Street:')) $client['bluewaveCrossStreet'] = nextTDStripped($strm);
		else if(strpos($line, '<strong>Neighborhood:')) $client['bluewaveNeighborhood'] = nextTDStripped($strm);
		else if(strpos($line, 'strong>Emerg Contact 1:')) {
			$client['emergencyContact1'] = nextTDStripped($strm);
			$state = 'emergencyContact1';
		}
		else if(strpos($line, 'strong>Emerg Contact 2:')) {
			$client['emergencyContact2'] = nextTDStripped($strm);
			$state = 'emergencyContact2';
		}
		else if(strpos($line, 'strong>Emerg Contact 3:')) {
			$client['emergencyContact3'] = nextTDStripped($strm);
			$state = 'emergencyContact3';
		}
		else if(strpos($line, 'Remarks:') && $col == 4) $client['notes_'.$state] = nextTDStripped($strm);
		else if(strpos($line, 'Phone:') && $col == 4) {
			if($state) $client['phone_'.$state] = nextTDStripped($strm);
			else $client['bluewaveAltPhone']  = nextTDStripped($strm);
		}
		else if(strpos($line, '<strong>Security Co.')) $client['alarm']['co'] = nextTDStripped($strm);
		else if(strpos($line, 'Phone:') && $col == 2) $client['alarm']['phone'] = nextTDStripped($strm);
		else if(strpos($line, 'In:')) $client['alarm']['in'] = nextTDStripped($strm);
		else if(strpos($line, 'Out:')) $client['alarm']['out'] = nextTDStripped($strm);
		else if(strpos($line, 'Password:')) $client['alarm']['password'] = nextTDStripped($strm);
		else if(strpos($line, 'Security Inst:')) $client['alarm']['instr'] = nextTDStripped($strm);
		
	}
	return $client;
}

function getLine($strm) {
	global $line, $col, $lineNum;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$line = trim($line);
	for($i==0;$i<strlen($line);$i++) if(ord($line[$i])>0) $cleanLine .= $line[$i];
	$line = $cleanLine;
//if(strpos($line, 'Phone:') !== FALSE) {echo "[$lineNum] $line";;}
	if(strpos($line, '<tr') !== FALSE) $col = 0;
	if(strpos($line, '<td') !== FALSE) $col++;
	return true;
}

function nextTDStripped($strm) {
	global $line, $lineNum;
	$td = '';
	do {
		if(!getLine($strm)) return FALSE;
		if($td) $spacer = "\n"; else $spacer = '';
		$td .= $spacer.$line;
	} while (strpos($line, '</td>') === FALSE && strpos($line, '</tr>') === FALSE);
	return trim(myStripTags($td));
}
	
	
function nextLineStripped($strm) {
	global $line;
	if(!getLine($strm)) return FALSE;
	$line = trim($line);
	return myStripTags($line);
}
	
function myStripTags($s) {
	return str_replace('&nbsp;', ' ', strip_tags($s));
}

/*

//print_r($line0);
$n = 1;

//while($line = trim(fgets($strm))) echo "$line<br>";

$clientMap = array();  // mapID => clientid
$customFields = getCustomFields('activeOnly');

//print_r($_SESSION['preferences']);exit;

$incompleteRow = null;
while($row = fgetcsv($strm, 0, $delimiter)) {
	$n++;
	//$line = trim($line);
	if(!$row) {echo "Empty Line #$n<br>";continue;}
	else if($incompleteRow) {
		$incompleteRow[count($incompleteRow)-1] = $incompleteRow[count($incompleteRow)-1]."\n".$row[0];
		if(count($row) > 1) {
			unset($row[0]);
			$incompleteRow = array_merge($incompleteRow, $row);
		}
		$row = $incompleteRow;
	}
	if(count($row) < 26) {
		$incompleteRow = $row;
		continue;
	}
	else $incompleteRow = null;
	//$row = explode(',', $line);
	$client = array();
	$emergencyContact = array();
	$neighborContact = array();
	$key = array();
	$notes = array();
	foreach($row as $i => $field) {
		if(!$field) continue;
		//$conv = $conversions[$i];
		$conv = $map[$dataHeaders[$i]];
		if($conv == 'x') continue;
		if(strpos($conv, '|')) { // contact field
			$parts = explode('|', $conv);
			if($parts[0] == 'contact') {
				if($parts[1] == 'emergency') $emergencyContact[$parts[2]] = $field;
				else if($parts[1] == 'neighbor') $neighborContact[$parts[2]] = $field;
			}
			if($parts[0] == 'key') {
				$key[$parts[1]] = $field;
			}
		}
		else if($conv == 'notes/-') {
			$notes[] = $dataHeaders[$i].': '.$field;
		}
		else if($conv == 'officenotes/-') {
			$officenotes[] = $dataHeaders[$i].': '.$field;
		}
		//convert_bluewave_provider	convert_bluewave_alternate_provider	convert_bluewave_status	convert_bluewave_level
		else if(strpos($conv, 'convert_') === 0) // conversion must return $client
			$client = call_user_func_array($conv, array($field, $client));
			
//if($conv == 'convert_bluewave_provider') echo ">>>>prov: ".print_r($client, 1)."<br>";
			
		else if(strpos($conv, 'custom') === 0) 
			$client['custom'][$conv] = $field;
			

		else $client[$conv] = $field;
	}
	if($notes) $client['notes'] = join("\n",$notes);
	if(!(isset($client['lname']) || isset($client['fname']))) echo "<p>Bad row: $n ".print_r($client,1);
	else {
		$mapID = $client['mapID'];
		if(!$client['activeHasBeenSet']) $client['active'] = 1;  // see convert_setClientActive
		saveNewClient($client);
		$newClientId = mysql_insert_id();
		if($mapID) $clientMap[$mapID] = $newClientId;
		echo "<p>Created CLIENT #$newClientId {$client['fname']} {$client['lname']}<br>";
		if($key) {
			$key['copies'] = 1;
			$key['possessor1'] = 'client';
			$keyId = saveClientKey($newClientId, $key);
			echo "<p>Created KEY #$keyId<br>";
		}
		//saveClientPets($newClientId);
		if($emergencyContact) {
			$contactId = saveClientContact('emergency', $newClientId, $emergencyContact);
			echo "<p>Created Emergency Contact #$contactId {$emergencyContact['name']}<br>";
		}
		if($neighborContact) {
			$contactId = saveClientContact('neighbor', $newClientId, $neighborContact);
			echo "<p>Created Trusted Neighbor #$contactId {$neighborContact['name']}<br>";
		}
		if($client['custom']) {
			foreach($client['custom'] as $field => $val) {
				$val = mysql_real_escape_string($val);
				if(!$customFields[$field])
					echo "Bad custom field [$field].  Could not populate this field with [$val] for {$client['lname']} [{$client['mapID']}]";
				else doQuery("REPLACE relclientcustomfield (clientptr, fieldname, value) VALUES ($newClientId, '$field', '$val')");
			}
			echo "Added custom fields for $newClientId<br>";
		}
		
	}
}

echo "<hr>";
if($clientMap) {
	$tableSql = "CREATE TABLE IF NOT EXISTS `tempClientMap` (
  `externalptr` int(11) NOT NULL,
  `clientptr` int(11) NOT NULL,
  PRIMARY KEY  (`externalptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

	doQuery($tableSql);
	doQuery('DELETE FROM tempClientMap');
	echo "External ID,ClientID<br>";
	foreach($clientMap as $mapID => $clientid) {
		insertTable('tempClientMap', array('externalptr'=>$mapID,'clientptr'=>$clientid), 1);
		echo "$mapID,$clientid<br>";
	}
}
echo "<hr>";
*/

// BLUEWAVE CONVERSION FUNCTIONS
	
