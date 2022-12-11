<?// import-one-bettawalka-client-and-pets.php

/*

This script parses the text obtained by Ctrl-A, Ctrl-V on a single client's account review in BettWalka.

Sample: S:\clientimports\BettaWalkaClientSample.txt
https://leashtime.com/import-one-bettawalka-client-and-pets.php?file=xxxxx

BEFORE USING THIS SCRIPT:

See https://bdw.bettawalka.com/customfields.do (Business > Administration > Custom Fields) and adjust initializeBettaWalkaPetCustomFields accordingly.

*/
require_once "gui-fns.php";
require_once "custom-field-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "item-note-fns.php";
require_once "client-fns.php";

$fieldMap = 
trim("
D.O.B.=dob
Phone#=homephone
Phone=primaryphone
Home phone=homephone
Alt Phone=cellphone2
Alt work phone=altworkphone
Work Phone=workphone
Email=email
Alt Email=email2
Neighborhood=+directions
Alarm Code In=+client>alarminfo
Alarm Code Out=+client>alarminfo
Relationship=+note
Notes=+notes
Office Notes=+officenotes
Alarm Password (if needed)=+client>alarminfo
Alarm Instructions=+client>alarminfo
Referred By=*custom2
Referred Comment=*custom3
Breed=breed
Breed Notes=+notes
Color=color
2nd Minder=*custom1
2nd Walker=*custom1
Usual time slot=clientCustomMatch
Address Notes=+client>directions
Key Info==+client>keyinfo
Neighborhood=+client>directions
Biter?=*petcustom2
Medical Concerns or Allergies=*petcustom3
Allow into Dog Park?=*petcustom4
Dog Socalization=*petcustom5
Human Socialization=*petcustom6
Personality & Quirks:=*petcustom7
General Comments=+notes
Fears?=*petcustom8
Training Needs?=*petcustom9
Favorite Games:=*petcustom10
Favorite Games=+notes
AM Feeding=*petcustom11
Medication Schedule and Routine=*petcustom12
MidDay Feeding=*petcustom13
PM Feeding=*petcustom14
Preferred Care Routine=*petcustom15
Medication=*petcustom16
Food=+client>foodloc
Treats=+client>foodloc
Leash=client>leashloc
Bags=*custom4
Litter Box=*custom5
Cleaning Supplies=*custom6
Bed/Crate=*custom7
Pet Carrier=*custom8
Toys=*custom9
Other Supplies=*custom10
Disposition and Personality=+description
Favorite Past Times=+notes
Fears and Hiding Spots=+notes
Door Dasher?=+notes
Litter Up-Keep=+notes
General Nature/Personality=+description
Cage Care=+notes
Quirks?=+notes
Type of Animal/Description=+description
Cage/Space Care=+notes
Fears? Quirks? Cute Habits?=+notes
General Personality/Disposition=+notes
Type of Bird=+description
Personality & Disposition=+notes
Cage care instructions=+notes
Feelings about humans?=+notes
Quirks, Habits, etc=+notes
"
);

foreach(explodePairPerLine($fieldMap, $sepr='=') as $field => $disposition)
	$arr["$field:"] = $disposition;
$fieldMap = $arr;


$nullStrings = "
Disposition and Personality:Tell us all about your feline!
Favorite Past Times:What Does Your Cat LOVE to do?
Fears and Hiding Spots:What scares your cat?
Favorite Games:How do we entertain your cat?
Door Dasher?:Does your cat try to escape?
Litter Up-Keep:What do we do with the litter? How often for a complete change?
General Nature/Personality:Tell us all about your reptile!
Cage Care:Tell us all about how you care for your reptile's cage
Quirks?:Anything we should look out for?
Type of Animal/Description:What kind of small monster are you?
Cage/Space Care:How do we care for your monster's space?
Fears? Quirks? Cute Habits?:What makes your small monster unique?
General Personality/Disposition:-Empty-
Type of Bird:-Empty-
Personality & Disposition:-Empty-
Cage care instructions:-Empty-
Feelings about humans?:-Empty-
Quirks, Habits, etc:-Empty-
Spayed/Neutered:-Empty-
";
foreach(explodePairPerLine($nullStrings, $sepr=':') as $field => $disposition)
	$arr["$field:"] = $disposition;
$nullStrings = $arr;

function importClient($showUnhandled=false) {
	global $line, $rawLine, $lineNum, $client, $clientid, $stack, $lastLine, $box, $boxCounts, $econtact, $pet, $lastPattern;
	//Alarm Code In,Alarm Code Out
	extract($_REQUEST);
	initializeBettaWalkaPetCustomFields();
	ob_start();
	ob_implicit_flush(0);

	if($file) $strm = fopen("/var/data/clientimports/$file", 'r');
	else $strm = fopen("data://text/plain,$clientdata" , 'rb');
	$client = array();
	$econtact = array();
	$stack[] = &$client;
	
	$activeSetting = -1;

	while(!$done && getLine($strm)) {
 //echo "($box) [$lastPattern]<br>";		
//if($lastPattern == 'Address:') echo "($box) CityStateZip: [$line]<br>";		
		//echo "<font color=green>".htmlentities($line)."</font><br>";
		if($line == 'Contact Info') startBox('contact');
		else if(strpos($line, "\tClient") === 0) startPet();
		else if(strpos($line, "Active Accounts") === 0) $activeSetting = 1;
		else if(strpos($line, "Inactive Accounts") === 0) $activeSetting = 0;

		else if($line == 'Make Inactive') $client['active'] = 1;
		else if($line == 'Basic Info') startBox('basicinfo');
		else if($line == 'Emergency Info' && startBox('emergency-contact') == 'quit') break;
		else if($line == 'Notes') startBox('notes'); // different for customer and pet
		else if($lastLine == 'Notes') appendField('notes', '', lineVal(' '));
		else if($line == 'Financial Info') startBox('financial');
		else if($line == 'Referral') startBox('referral');
		else if($line == 'PPS Contact Info') startBox('ppscontact');
		else if($line == 'PPS Emergency Info') startBox('ppsemergency'); //keydescription

		else if($line == 'Owner Info') startBox('ownerinfo'); // ignore
		else if($line == 'Private Notes') startBox('privatenotes'); // add an officenote, subject: pet name
		else if($lastLine == 'Private Notes') $client['officenotesArray'][] = array('subject'=>$pet['name'], 'body'=> lineVal(' '));
		else if($line == 'Vet and Health Info') startBox('vetinfo'); 
		else if($line == 'Dog Care') startBox('dogcare');
		else if($line == 'Feeding & Medication Routine') startBox('feedandmeds');
		else if($line == 'Supply Locations') startBox('supplylocs');
		else if($line == 'PPS Pet Info') startBox('ppspetinfo');
		else if($line == 'Cat Care') startBox('catcare');
		else if($line == 'Reptile Care') startBox('reptilecare');
		else if($line == 'Small Animal Care') startBox('smallanimalcare');
		else if($line == 'Bird Care') startBox('birdcare');
		else if($line == 'Cat Care') startBox('catcare');
		else if($line == "Date\tType\tAmount\tBalance\tBy\tNotes\t_") {
			if($stack[count($stack)-1] !== $client) savePet(array_pop($stack));
			saveClientCustomFields($clientid, $client, $pairsOnly=true);
			if($client['keydescription']) {
				require_once "key-fns.php";
				saveClientKey($clientid, array('description'=>$client['keydescription'], 'copies'=>1));
			}
			if(count($client['clinics']) > 1) 
				appendField('notes', 'Clinics:', join(', ', $client['clinics']));
			foreach((array)$client['officenotesArray'] as $note) {
				//addOfficeNote($note['subject'], $note['body']);
				$officeNotesLines[] = "{$note['subject']}: {$note['body']}";
			}
			if($officeNotesLines) $client['officenotes'] = 
				($client['officenotes'] ? "{$client['officenotes']}\n" : "").join("\n", $officeNotesLines);
			//echo "Client: ".print_r(fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid"), 1)."<p>";
			//echo "Vet: ".print_r(fetchFirstAssoc("SELECT * FROM tblclinic WHERE clinicid = '{$client['clinicptr']}'"), 1)."<p>";
			//foreach(fetchAssociations("SELECT * FROM tblpet WHERE ownerptr = $clientid") as $pet)
				//echo print_r($pet, 1)."<p>";
				
			if($client['altworkphone']) {
				$altWorkPhone = canonicalphone($client['altworkphone']);
					foreach(explode(',', 'workphone,cellphone,cellphone2') as $k) {
						if(!$client[$k]) {
							$altWorkPhoneKeyField = $k;
							break;
						}
					}
					if($altWorkPhoneKeyField) $client[$altWorkPhoneKeyField] = "{$client['altWorkPhoneKeyField']}";
					else $client['notes'] .= "\nAlt work phone: {$client['altWorkPhoneKeyField']}";
				}

				if($client['primaryphone']) {
//echo print_r($client,1)."<p>";					
					$primaryPhone = canonicalphone($client['primaryphone']);
					foreach($client as $k=>$v) {
						if($k != 'primaryphone' && strpos($k, 'phone') !== FALSE) // ignore 'primaryphone'
							if(canonicalphone($v) == $primaryPhone)
								$primaryKeyField = $k;
					}

					if(!$primaryKeyField) {
						foreach(explode(',', 'homephone,cellphone,cellphone2,workphone') as $k) {
							if(!$client[$k]) {
								$primaryKeyField = $k;
								break;
							}
						}
					}
					if($primaryKeyField) $client[$primaryKeyField] = "*{$client['primaryphone']}";
					else $client['notes'] .= "\nPrimary Phone: {$client['primaryphone']}";
				}

//echo "BANG! ".print_r($client,1);exit;
				preprocessClient($client);
				$client['clientid'] = $clientid;
				$client['mailtohome'] = 1;
				if($activeSetting != -1) $client['active'] = $activeSetting;
				saveClient($client);
				$client = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientid");
				echo "Client $client added with pets: ";
				$pets = fetchCol0("SELECT CONCAT_WS(' ', name, CONCAT_WS('', '(', type, ')')) FROM tblpet WHERE ownerptr = $clientid");
				echo $pets ? join(', ', $pets) : 'none';
				$done = true;
		}

		else if(lineStartsWith($pat = 'basicinfo>Name:')) $pet['name'] = getPetName(lineVal($pat, false));
		else if(lineStartsWith($pat = 'basicinfo>Key Info:')) appendField('client>keydescription', '', lineVal($pat));
		else if(lineStartsWith($pat = 'ownerinfo>Name:')) ; // no-op
		else if(lineStartsWith($pat = 'emergency-contact>Name:')) $econtact['name'] = lineVal($pat); 
		else if(lineStartsWith($pat = 'ownerinfo>Phone#:')) ; // no-op
		else if(lineStartsWith($pat = 'emergency-contact>Notes:')) appendField('note', 'Notes:', lineVal($pat));
		else if(lineStartsWith($pat = 'emergency-contact>Relationship:')) appendField('note', 'Relationship:', lineVal($pat));
		//else if(lineStartsWith($pat = 'notes>How did you hear about us?:')) appendField('note', 'How did you hear about us?', lineVal($pat));
		else if(lineStartsWith($pat = 'How did you hear about us?:')) {
			appendField('notes', 'How did you hear about us?', lineVal($pat));
		}
		else if(lineStartsWith($pat = 'Name:')) getFnameLname(lineVal($pat, false));
		else if(lineStartsWith($pat = 'Alt Name:')) getFnameLname(lineVal($pat), 'fname2', 'lname2');
		else if(lineStartsWith($pat = 'Address:')) {$stack[count($stack)-1]['street1'] = lineVal($pat);}
		else if($lastPattern == 'Address:') {getCityStateZip($line); $lastPattern = '';}
		else if(lineStartsWith($pat = 'Normal Minder:')) $client['defaultproviderptr'] = findSitter(lineVal($pat));
		else if(lineStartsWith($pat = 'Normal Walker:')) $client['defaultproviderptr'] = findSitter(lineVal($pat));
		else if(lineStartsWith($pat = '2nd Walker:')) appendField('note', '2nd Walker:', lineVal($pat));
		else if(lineStartsWith($pat = 'Vet Name:')) handleVet(lineVal($pat), 'name');
		else if(lineStartsWith($pat = 'Vet Address:')) handleVet(lineVal($pat), 'address');
		else if(lineStartsWith($pat = 'Vet Phone Number:')) handleVet(lineVal('Vet Phone Number:'), 'number');
		else if(lineStartsWith($pat = 'Sex:')) {
			$val = strtoupper(lineVal($pat)); 
			$pet['sex'] = !$val ? null : ($val[0] == 'M' ? 'm' : ($val[0] == 'F' ? 'f' : null));
		}
		else if(!handleMappedFields() && $showUnhandled) echo "<font color=gray>Unhandled: ".strip_tags($line).'</font><br>'; 
		$lastLine = $line;
	}
	$out = ob_get_contents();
	if(!$out) echo "<font color=red>No action taken!  Are you in Accounts?!<p>";	
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function canonicalphone($phone) {
	$phone = $phone ? $phone : "";
	$junk = "()-.\"' ";
	for($i=0;$i<strlen($junk); $i++)
		$phone = str_replace($junk[$i], '', $phone);
	return $phone;
}

function valIsNull($key, $val) {
	global $nullStrings;
	return $val == $nullStrings[$key];
}

function redline($s){$s = is_array($s) ? print_r($s, 1) : $s; echo "<font color=red>[$s]</font></br>";}

function mres($s) { return mysqli_real_escape_string($s); }

function handleMappedFields() {
	global $line, $client, $pet, $stack, $lastPattern, $fieldMap, $box, $boxCounts;
	if(!lineStartsWithOneOf(array_keys($fieldMap))) return;
	$key = $lastPattern;
//echo "KEY: 	[$key]".print_r($stack,1)."<p>";
	$disposition = $fieldMap[$key];
//echo "KEY: 	[$key] DISP: $disposition<p>";
	$val = lineVal($key);
	if(in_array($key, array('Email:','Alt Email:', 'D.O.B.:')))
		$val = trim(str_replace('image', '', $val));
	if(valIsNull($key, $val)) $val = null;
	if(!$val) return;
//echo "$key<br>";	
	$boxCounts[$box]+= 1;
	if($disposition[0] == '+') { $add = true; $disposition = substr($disposition, 1); }
	else if($disposition == 'clientCustomMatch') {
		foreach($_SESSION['preferences'] as $k => $name)
			if(strpos($k, 'custom') === 0) $customFields[substr($name, 0, strpos($name, '|')).':'] = substr($k, strlen('custom'));
//echo "KEY: [$key] -- DISP: $disposition<br>".print_r($customFields,1)."<br>".$customFields[$key]."<hr>";
		$disposition = $customFields[$key];
		$client["custom$disposition"] = $val;
	}
	else if($disposition[0] == '*') { 
		$disposition = substr($disposition, 1);
		if(strpos($disposition, 'pet') === 0) $pet[$disposition] = $val;
		else $client[$disposition] = $val;
	}
	else if(strpos($disposition, '>')) {
		list($obj, $disposition) = explode('>', $disposition);
		if($obj == 'client') $obj =  &$client;
	}
	else $obj = &$stack[count($stack)-1];
	
	if($add) $val = $obj[$disposition]."\n$key $val";
	$obj[$disposition] = $val;
	if($disposition == 'breed' && ($type = guessTypeByBreed($val))) 
		$obj['type'] = $type;
	return true;
}

function handleVet($datum, $whichOne) {
	global $client;
	if(!$datum) return;
	$mods = array();
	if($whichOne == 'address') $mods['street1'] = $datum;
	else if($whichOne == 'number') $mods['officephone'] = $datum;
	if($whichOne == 'name') {
		$vetid = fetchRow0Col0("SELECT * FROM tblclinic WHERE clinicname = '".mres($datum)."'");
		if(!$vetid) $vetid = insertTable('tblclinic', array('clinicname'=>$datum), 1);
		if(!$client['clinicptr']) $client['clinicptr'] = $vetid;
		$client['clinics'][$datum] = 1;
	}
	else {
		if(!$client['clinicptr']) {
			$client['clinicptr'] = insertTable('tblclinic', array('clinicname'=>"0 NOT SUPPLIED"), 1);
		}
		updateTable('tblclinic', $mods, 
												"clinicid = {$client['clinicptr']} AND officephone IS NULL", 1);
	}
}

function addOfficeNote($subject, $body) {
	if(!$body) return;
	global $clientid;
	insertTable('relitemnote', 
							array('note'=>$body,
								'subject'=>$subject,
								'date'=>date('Y-m-d H:i:s'),
								'authorid'=>'0',
								'priornoteptr'=>'0',
								'itemtable'=>'client-office',
								'itemptr'=>$clientid),
							1);   //priornoteptr, itemtable, itemptr
//echo "** SUBJ: $subject BODY: $body<br>";								
}

function appendField($key, $label, $addendum) {
	global $stack, $client;
	if(!$addendum) return;
	if(strpos($key, '>')) {
		list($dest, $key) = explode('>', $key);
		if($dest == 'client') $dest = &$client;
	}
	else $dest = &$stack[count($stack)-1];
	if(strpos($dest[$key], "$label $addendum") === FALSE) {  // don't mindlessly duplicate
		$label = $label ? $label : '';
		$dest[$key] .= "\n$label $addendum";
	}
}

function startBox($label) {
	global $box, $stack, $client, $clientid, $override, $econtact, $pet;
//echo "START: '$label'<p>";				
	if($box) {// finish processing box
//echo "box == '$box'<p>";				
		if($box == 'contact') {
			$doppl = fetchFirstAssoc("SELECT * FROM tblclient WHERE fname = '".mres($client['fname'])."'
																AND lname = '".mres($client['lname'])."'
																AND homephone = '".mres($client['homephone'])."'");
			if($doppl && !$override) {
				echo "<font color=red>Client {$client['fname']} {$client['lname']} already exisits.<br></font>";
				return 'quit';
			}
//echo "DOPPL: ".print_r($doppl, 1).'<p>';				
			if(!$doppl || $override) {
//echo "SAVE New client: ".print_r($client, 1).'<p>';				
				$clientid = saveNewClient($client);
				$client['clientid'] = $clientid;
			}
		}
		else if($box == 'emergency-contact') {
			saveClientContact('emergency', $clientid, array_pop($stack));
		}
		else if($box == 'catcare') {
			savePet(array_pop($stack));
		}
		else if($label == 'basicinfo' && $pet) {
			savePet(array_pop($stack));
			$pet = null;
		}
	} 
	if($label == 'emergency-contact') $stack[] = &$econtact;
	else if($label == 'basicinfo') {
		$pet = array('ownerptr'=>$clientid);
		$stack[] = &$pet;
	}  // pet
	$box = $label;
}

function savePet($pet) {
	global $client, $boxCounts;
	$addrFields = explode(',', 'street1,street2,city,state,zip');
	foreach($addrFields as $k) $clientAddress .= $client[$k];
	foreach($addrFields as $k) $petAddress .= $pet[$k];
	$clientAddress = trim($clientAddr);
	if(trim($petAddress && !trim($clientAddress))) {
		foreach($addrFields as $k) $mods[$k] .= $pet[$k];
		updateTable('tblclient', $mods, "clientid = {$client['clientid']}", 1);
	}
//print_r($boxCounts);	
	if($boxCounts['Dog Care']) $pet['type'] = 'Dog';
	else if($boxCounts['dogcare']) $pet['type'] = 'Dog';
	else if($boxCounts['catcare']) $pet['type'] = 'Cat';
	else if($boxCounts['reptilecare']) $pet['type'] = 'Reptile';
	else if($boxCounts['smallanimalcare']) $pet['type'] = 'Small Animal';
	else if($boxCounts['birdcare']) $pet['type'] = 'Bird';
//print_r($boxCounts);	
	$petId = insertTable('tblpet', saveablePet($pet, 1));
//redline($pet);
	
	savePetCustomFields($petId, $pet, $number=null, $pairsOnly=true);
}

function getCityStateZip($str) {
	global $stack;
	$destination = &$stack[count($stack)-1];
	$parts = explode(' ', $str);
	if($parts) {
		if($parts[count($parts)-1] == 'image') array_pop($parts);
		if($parts && is_numeric(count($parts)-1)) $destination['zip'] = array_pop($parts);
		if($parts && strlen($parts[count($parts)-1]) == 2) $destination['state'] = array_pop($parts);
		if($parts) $destination['city'] = join(' ', $parts);
		//echo "[$str] ".print_r($destination, 1)."<p>";
	}
}

function getFnameLname($str, $fnameKey='fname', $lnameKey='lname') {
	global $stack;
	$destination = &$stack[count($stack)-1];
	$parts = explode(' ', $str);
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if($parts) $destination[$fnameKey] = join(' ', $parts);
	}
}

function getPetName($str) {
	global $stack;
	$destination = &$stack[count($stack)-1];
	$parts = explode(' ', $str);
	if(count($parts) > 1)  // last name may be empty
		array_pop($parts); // last name
	if($parts) return join(' ', $parts);
}

function lineStartsWithOneOf($patterns, $suffix='') {
	foreach($patterns as $pat)
		if(lineStartsWith($pat))
			return $pat;
}

function lineStartsWith($pattern) {
	global $line, $lineNum, $rawLine, $box, $lastPattern;
	if(strpos($pattern, '>')) {
		list($label, $pattern) = explode('>', $pattern);
		if($box != $label) return false;
	}
	if(strpos($line, $pattern) === 0) {
		$lastPattern = $pattern;
//echo "($box) LAST: [$lastPattern]<br>";		
		return true;
	}
}

function lineVal($pattern, $stripEmpty=true) {
	global $line, $box, $lineNum, $rawLine;
	if(strpos($pattern, '>')) {
		list($label, $pattern) = explode('>', $pattern);
		if($box != $label) return false;
	}
	$val = substr($pattern, -1) == ':' ? substr($line, strlen($pattern)) : $line;
	if($stripEmpty) {
		$emptyPattern = $stripEmpty == true ? '-Empty-' : $stripEmpty;
		$val = str_replace($emptyPattern, '', $val);
	}
	return trim($val);
}

function startPet() {
	global $pet, $clientid, $line;
	if(!$clientid) saveClient();
	$pet = array('ownerptr'=>$clientid);
}

function getLine($strm) {
	global $line, $lineNum, $rawLine;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$rawLine = $line;
	$line = trim($line);
	return true;
}


function initializeBettaWalkaPetCustomFields() {
	require_once "preference-fns.php";
	setPreference('petTypes', 'Dog|Cat|Reptile|Small Animal|Bird');
	if(!$_SESSION['preferences']['petcustom1']) 
		foreach(array(/*
					'petcustom1'=>'D.O.B.|1|oneline|1',
					'petcustom2'=>'Biter?|1|boolean|1',
					'petcustom3'=>'Spayed/Neutered|1|boolean|1',
					'petcustom4'=>'Alarm/Door Code|1|oneline|1',
					'petcustom5'=>'Walk with other dogs?|1|boolean|1',*/
					) as $key => $val)
		setPreference($key, $val);
		
	if(!$_SESSION['preferences']['custom1']) 
		foreach(array(/*
					'custom1'=>'2nd Minder|1|oneline|1',
					'custom2'=>'Referred By|1|oneline|1',
					'custom3'=>'Referred Comment|1|text|1',
					'custom4'=>'Bags|1|text|1',
					'custom5'=>'Litter Box|1|text|1',
					'custom6'=>'Cleaning Supplies|1|text|1',
					'custom7'=>'Bed/Crate|1|text|1',
					'custom8'=>'Pet Carrier|1|text|1',
					'custom9'=>'Toys|1|text|1',
					'custom10'=>'Other Supplies|1|text|1'*/)  as $key => $val)
		setPreference($key, $val);
		
		
		
}

function initializeBettaWalkaPetCustomFieldsOLD1() {
	require_once "preference-fns.php";
	setPreference('petTypes', 'Dog|Cat|Reptile|Small Animal|Bird');
	if(!$_SESSION['preferences']['petcustom1']) 
		foreach(array(
					'petcustom1'=>'D.O.B.|1|oneline|1',
					'petcustom2'=>'Biter?|1|boolean|1',
					'petcustom3'=>'Medical Concerns or Allergies|1|text|1',
					'petcustom4'=>'Allow into Dog Park?|1|oneline|1',
					'petcustom5'=>'Dog Socalization|1|text|1',
					'petcustom6'=>'Human Socialization|1|text|1',
					'petcustom7'=>'Personality & Quirks|1|text|1',
					'petcustom8'=>'Fears?|1|text|1',
					'petcustom9'=>'Training Needs?|1|text|1',
					'petcustom10'=>'Favorite Games|1|text|1',
					'petcustom11'=>'AM Feeding|1|text|1',
					'petcustom12'=>'Medication Schedule and Routine|1|text|1',
					'petcustom13'=>'MidDay Feeding|1|text|1',
					'petcustom14'=>'PM Feeding|1|text|1',
					'petcustom15'=>'Preferred Care Routine|1|text|1',
					'petcustom16'=>'Medication|1|text|1') as $key => $val)
		setPreference($key, $val);
		
	if(!$_SESSION['preferences']['custom1']) 
		foreach(array(
					'custom1'=>'2nd Minder|1|oneline|1',
					'custom2'=>'Referred By|1|oneline|1',
					'custom3'=>'Referred Comment|1|text|1',
					'custom4'=>'Bags|1|text|1',
					'custom5'=>'Litter Box|1|text|1',
					'custom6'=>'Cleaning Supplies|1|text|1',
					'custom7'=>'Bed/Crate|1|text|1',
					'custom8'=>'Pet Carrier|1|text|1',
					'custom9'=>'Toys|1|text|1',
					'custom10'=>'Other Supplies|1|text|1')  as $key => $val)
		setPreference($key, $val);
		
		
		
}


function findSitter($name) {
	$providerid = fetchRow0Col0($sql = "SELECT providerid 
															FROM tblprovider 
															WHERE CONCAT_WS(' ', fname, lname) = '".mres($name)."' LIMIT 1");
	if(!$providerid) {
		redline("Could not find sitter: [$name]");
		//redline($sql);
	}
	//else redline("Found [$name]=$providerid");
	return $providerid;
}

function likeOneOf($str, $arr) {
	$str = strtoupper($str);
	foreach($arr as $pat)
		if(strpos($str, strtoupper($pat)) !== FALSE)
			return $pat;
}

function guessTypeByBreed($str) {
	$rabbits = 'rabbit';
	$birds = 'bird';
	$turtles = 'turtle';
	$cats = 'cat,tuxedo,siamese,kitten,kitty,tabby,pek,dsh,dsc,shorthair,short hair,dsh,dlh,maine,coon,persian,Chartreaux,Chartreux';
	$dogs = 'golden,bernard,beagle,jack,russell,terrier,cairn,hound,collie,dachshund,pit,bull,mutt,mix,chihuahua,shepherd,weim,corg,dane,pomer'
					.',schnauzer,tzu,lab,oodle,mastiff,pug,poo,havanese,pit,lab,boxer,retriev,bull,orki,spaniel,britn,chow,ridge,wheaton,russel,king'
					.',eskimo,rott,hound,bijon,bishon,maltese,westie,sheltie,dog,cavachon,shiba,inu,pup,cavalier,aussie,coton,terrior,oodle,sheherd,shep,border,wemer'
					.'visla,wire,Catahoula,ibizan,dach,rhod,ridgeb';
	if($match = likeOneOf($str, explode(',', $cats))) return 'Cat';
	if($match = likeOneOf($str, explode(',', $dogs))) return 'Dog';
	if($match = likeOneOf($str, explode(',', $rabbits))) return 'Rabbit';
	if($match = likeOneOf($str, explode(',', $birds))) return 'Bird';
	if($match = likeOneOf($str, explode(',', $turtles))) return 'Turtle';
}

