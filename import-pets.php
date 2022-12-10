<?// import-pets.php

// http://iwmr.info/petbizdev/import-pets.php?map=map-bluewave-pets.csv&file=woofies/pets-bluewave.txt
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";
require_once "preference-fns.php";

extract($_REQUEST);

$map = "/var/data/clientimports/$map";
$file = "/var/data/clientimports/$file";

$mapLines = file($map, FILE_IGNORE_NEW_LINES);

$originalFields = explode(',', trim($mapLines[0]));
$conversions = explode(',', trim($mapLines[1]));


echo "originalFields: ".count($originalFields).'<br>conversions: '.count($conversions).'<br>';

for($i=0;$i<max(count($originalFields), count($conversions));$i++) {
  echo ($i >= count($originalFields) ? "<i>[no value]</i>" : $originalFields[$i])." ==> ";
  echo ($i >= count($conversions) ? "<i>[no value]</i>" : $conversions[$i])."<br>";
}

echo "<hr>";
echo "Data lines: ".count(file($file)).'<p>';
$strm = fopen($file, 'r');


$delimiter = strpos($file, '.xls') ? "\t" : ',';

function lineIsEmpty($line, $delimiter) {
	foreach(explode($delimiter, $line) as $cell) 
		if($cell) return false;
	return true;
}


for($line0 = trim(fgets($strm)); lineIsEmpty($line0, $delimiter); $line0 = trim(fgets($strm)))  echo '*chomp* '; // eat empty lines at top and consume first line (field labels)
echo "<br>$line0<p>";

$n = 1;

$tables = fetchCol0("SHOW TABLES");
$clinicMapIsPresent = in_array('tempClinicMap', $tables);


//while($line = trim(fgets($strm))) echo "$line<br>";


$petTypes = array();
$rawPetTypes = explode(',',$rawPetTypes);
for($i=0;$i < count($rawPetTypes)-1; $i+=2)
	$petTypes[$rawPetTypes[$i]] = $rawPetTypes[$i+1];

//$petTypes = array('B1'=>'Bird', 'FI'=>'Fish', 'HA'=>'Hamster', 'CT'=>'Cat', 'RA'=>'Rabbit', 'DS'=>'Dog', 'GE'=>'Gerbil', 
//									'RE'=>'Reptile','RA'=>'Rat');

if(!$petTypes) echo "<font color=red>No Pet Types defined</font><br>";
else {
	if(!$TEST) setPreference('petTypes', join('|', array_values($petTypes)));
	else echo "Will set Pet Types: ".join('|', array_values($petTypes))."<p>";
}

if($ignorePetsForAllClientsButThese) {
	$tempClientMap = fetchKeyValuePairs("SELECT externalptr, clientptr FROM tempClientMap");
	$mapIds = explode(',', $ignorePetsForAllClientsButThese);
	$ignorePetsForAllClientsButThese = array();
	foreach($mapIds as $mapId) {
		$ignorePetsForAllClientsButThese[] = $tempClientMap[$mapId];
	}
}

while($row = fgetcsv($strm, 0, $delimiter)) {
	$n++;
	//$line = trim($line);
	if(!$row) {echo "Empty Line #$n<br>";continue;}
	//$row = explode(',', $line);
	$pet = array('active'=>1);
	$customFields = array();
	$notes = array();
	foreach($row as $i => $field) {
		$field = trim($field);
		if(!$field) continue;
		$conv = $conversions[$i];
		if($conv == 'x') continue;
		if($conv == 'notes/-') {
			$notes[] = $originalFields[$i].': '.$field;
		}
		else if($conv == 'officenotes/-') {
			$officenotes[] = $originalFields[$i].': '.$field;
		}
		//convert_bluewave_provider	convert_bluewave_alternate_provider	convert_bluewave_status	convert_bluewave_level
		else if(strpos($conv, 'convert_') === 0) 
			$pet = call_user_func_array($conv, array($field, &$pet));
		else if(strpos($conv, 'petcustom') === 0) {
			$customFields[$conv] = $field;
		}
			
		else $pet[$conv] = $field;
	}
	if($notes) $pet['notes'] = join("\n",$notes);
	$owner = $pet['ownerptr'] 
		? fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$pet['ownerptr']} LIMIT 1")
		: "Owner unknown";
	$petIgnored = true;
	if(!isset($pet['name'])) echo "<p><font color=red>Bad row: ($n)</font>".print_r($pet);
	else if(!$TEST){
		if($pet['ownerptr'] && $ignorePetsForAllClientsButThese 
				&& !in_array($pet['ownerptr'], $ignorePetsForAllClientsButThese))
			echo "IGNORING #$petId {$pet['name']} owned by $owner<br>";
		else {
			$petId = insertTable('tblpet', $pet, 1);
			savePetCustomFields($petId, $customFields, $number='', $pairsOnly=true);
			echo "<p>Created PET #$petId {$pet['name']} owned by $owner<br>";
		$petIgnored = false;
		}
	}
	else {
		if($pet['ownerptr'] && $ignorePetsForAllClientsButThese 
				&& !in_array($pet['ownerptr'], $ignorePetsForAllClientsButThese))
			echo "<font color=blue>IGNORING #$petId {$pet['name']} owned by $owner</font><br>";
		else echo "<p>Found PET {$pet['name']} owned by $owner<br>";
	}
}

echo "<hr>";

// BLUEWAVE CONVERSION FUNCTIONS
	
function convert_bluewave_pettype($type, &$pet) {
	global $petTypes;
	$pet['type'] = $petTypes[$type];
	return $pet;
}
	
function convert_bluewave_sex($sex, &$pet) {
	$sex = strtolower($sex);
	if(in_array($sex, array('m','n'))) {
		$pet['sex'] = 'm';
		$pet['fixed'] = $sex == 'n' ? 1 : 0;
	}
	else if(in_array($sex, array('f','s'))) {
		$pet['sex'] = 'f';
		$pet['fixed'] = $sex == 's' ? 1 : 0;
	}
	else $pet['sex'] = null;
	return $pet;
}
	
function convert_bluewave_dob($dob, &$pet) {
	if($dob) $pet['dob'] = date('Ymd', strtotime($dob));
	return $pet;
}
	
function convert_bluewave_status($status, &$pet) {
	if($status != 'A') $pet['active'] = 0; 
	return $pet;
}	

function convert_bluewave_level($level, &$pet) {
	if($level != 'C') $pet['prospect'] = 1; 
	return $pet;
}	

function convert_bluewave_biting($bites, &$pet) {
	global $notes;
	if($bites != 'N') $notes[] = 'Animal bites.'; 
	return $pet;
}	

function convert_bluewave_shots($shots, &$pet) {
	global $notes;
	if($shots == 'N') $notes[] = 'No vaccinations.'; 
	return $pet;
}		

function convert_bluewave_er_limit($limit, &$pet) {
	global $notes;
	if(!is_numeric($limit)) $notes[] = "Emergency Room expense limit: $limit"; 
	return $pet;
}		

function convert_bluewave_owner($owner, &$pet) {
	if(!$owner) {
		echo "<font color=red>No owner ID supplied for pet.</font><br>";
		return;
	}
	if(!($clientid = fetchRow0Col0("SELECT clientptr FROM tempClientMap WHERE externalptr = '$owner' LIMIT 1"))) {
		echo "<font color=red>No client found with bluewave ID: $owner.</font><br>";
		return;
	}
	$pet['ownerptr'] = $clientid; 
	return $pet;
}		

function convert_bluewave_vetclinic($clinic, &$pet) {
	// decide what to do with vt_fclty,	vet_nm,	vet_phn, vet_cd
	global $clinicMapIsPresent;
	if(!$clinic) return $pet;
	if(!$clinicMapIsPresent) {
		echo "No clinic table has been established.  Vet Clinic data ignore for pet: ".print_r($pet, 1).".<br>";
		return;
	}

	if(!($clinicPtr = fetchRow0Col0("SELECT clinicptr FROM tempClinicMap WHERE externalptr = $clinic LIMIT 1"))) {
		echo "No clinic found with bluewave ID: $clinic.<br>";
		return $pet;
	}
	updateTable('tblclient', array('clinicptr'=>$clinicPtr), "clientid = '{$pet['ownerptr']}'", 1);
	$pet['vetptr'] = $clinicPtr;
	return $pet;
}		
	