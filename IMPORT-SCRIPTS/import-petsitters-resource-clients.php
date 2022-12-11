<?// import-petsitters-resource-clients.php

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
require_once "custom-field-fns.php";

function handlePreferredpetsitter(&$client, $val) {
	if(!trim($val) || $val == 'Please Select One...') return;
	// else lookup sitter and set defaultprovider
}
/*
function quoteless($str) {
	return substr($str, 1, strlen($str)-2);
}

function panelFieldRow($line) { // return a key-value pair
	$start = strpos($line, "(")+1;
	$line = substr($line, strpos($line, ")") - $start);
	$els = explode(',', $line);
	return array(quoteless($els[1])=>quoteless($els[3]));
}

function panelListRow($line) { // return a key-value pair
	$start = strpos($line, "(")+1;
	$line = substr($line, strpos($line, ")") - $start);
	$els = explode(',', $line);
	return array(quoteless($els[1])=>quoteless($els[4]));
}*/

function decompose($str, $delim) {
	return array_map('trim', explode($delim, $str));
}


function processEmails($trimval, &$client) { // handles multiple emails
	if(!$trimval) return;
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
	foreach($emails as $email) {
		if(!isEmailValid($email))
			$badEmails[] = $email;
		else if(!$client['email']) $client['email'] = $email;
		else if(!$client['email2']) $client['email2'] = $email;
		else $client['notes'][] = "Other email: $email";
	}
	return $badEmails;
}
	
$_SESSION['preferences'] = fetchPreferences();
$prcustomFields = explodePairsLine('Security Question|oneline||Security Answer|oneline');

$homeFields = explode("\n", "Hidden Key Location
Fenced In Yard
Electronic Fence
Fresh Litter
Dirty Litter
Medicine
Vacuum Cleaner
Electrical Panel
Water Shut Off
Gas Shut Off
Lights And Timers
Gates And Locks
Trash Pickup Day
House Key ID
Miles To House");
foreach($homeFields as $fld) $prcustomFields[trim($fld)] = 'oneline';

//echo print_r($prcustomFields, 1)."<br>BANG!";exit;


$allCustFields = array();
foreach(getCustomFields() as $key => $desc) $allCustFields[$desc[0]] = $key;
$numCustFields = count($allCustFields);
foreach($prcustomFields as $nm=>$type) {
	if(!in_array($nm, array_keys($allCustFields))) {
		$numCustFields += 1;
		setPreference("custom$numCustFields", "$nm|1|$type|0|0");
		$_SESSION['preferences'] = fetchPreferences();
	}
	$ltCustomFieldKeys[$nm] = fetchRow0Col0("SELECT property FROM tblpreference WHERE property LIKE 'custom%' AND value LIKE '$nm|%'", 1);
}

$allCustFields = array();
generatePRcustomPetFields();
foreach(getCustomFields(true, false, getPetCustomFieldNames()) as $key => $desc) $allCustFields[$desc[0]] = $key;
$numCustFields = count($allCustFields);
//print_r($allCustFields);
foreach($prcustomPetFields as $nm=>$type) {
	if(!in_array($nm, array_keys($allCustFields))) {
		$numCustFields += 1;
		setPreference("petcustom$numCustFields", "$nm|1|$type|0|0");
		$_SESSION['preferences'] = fetchPreferences();
	}
	$ltCustomPetFieldKeys[$nm] = fetchRow0Col0("SELECT property FROM tblpreference WHERE property LIKE 'petcustom%' AND value LIKE '$nm|%'", 1);
}


$fieldMap = 
trim("
First Name=fname
Last Name=lname
Address=street1
City=city
State=state
ZIP Code=zip
Phone Number=homephone
Cell Number=cellphone
Fax Number=fax
Email Address=email
E-Mail Distribution Enabled=pref|YesNoNot|optOutMassEmail
Locked Out=--pref|YesNo|optOutMassEmail
User Name=username
Password=--password
Preferred Pet Sitter=--fn|handlePreferredpetsitter
Security Question=cust
Security Answer=cust
Garage Code=garagegatecode
Security Key Pad Location=+alarminfo
Security Code=+alarminfo
Security Password=+alarminfo
Hidden Key Location=cust
Fenced In Yard=cust
Electronic Fence=cust
Fresh Litter=cust
Dirty Litter=cust
Leashes=leashloc
Medicine=cust
Vacuum Cleaner=cust
Electrical Panel=cust
Water Shut Off=cust
Gas Shut Off=cust
Lights And Timers=cust
Gates And Locks=cust
Trash Pickup Day=cust
House Key ID=--cust
Miles To House=cust
"
);

foreach(explodePairPerLine($fieldMap, $sepr='=') as $field => $disposition)
	$arr[$field] = $disposition;
$fieldMap = $arr;

$nullStrings = "
";
foreach(explodePairPerLine($nullStrings, $sepr=':') as $field => $disposition)
	$arr["$field:"] = $disposition;
$nullStrings = $arr;

function importClients($showUnhandled=false) {
	global $fieldMap, $petFieldMap, $line, $ltCustomFieldKeys, $ltCustomPetFieldKeys;
	extract($_REQUEST);
	ob_start();
	ob_implicit_flush(0);

	if($file) $strm = fopen("/var/data/clientimports/$file", 'r');
	else $strm = fopen("data://text/plain,$clientdata" , 'rb');
	$clientPrefs = array();
	$custFields = array();
	$clientNameParts = array();
	$pet = null;
	while(getLine($strm)) {
//echo "<hr>$line";		
		if(strpos($line , 'Client Pet Info') !== FALSE) {
			$petMode = true;
//echo "<hr>Client Pet Info -- petMode [$petMode] Disp: $disp  Client: ";//.print_r($client, 1);			
			if($pet && $client['clientid']) {
				foreach($pet as $k=>$v) {
					if($v && is_array($v)) {
						$parts = array();
						foreach($v as $label=>$val) $parts[] = "$label: $val";
						$pet[$k] = join("\n", $parts);
					}
				}
				$pet['ownerptr'] = $client['clientid'];
				$petId = insertTable('tblpet', $pet, 1);
				savePetCustomFields($petId, $custFields, '', $pairsOnly=true);
			}
			$client = null;
			$pet = array('active'=>1);
			$custFields = array();
			$clientNameParts = array();
		}
		else if(strpos($line , 'Client Info') === 0) {
			if($client) {
				$clientId = saveNewClient($client);
				foreach($clientPrefs as $k=>$v)
					setClientPreference($clientId, $k, $v);
				saveClientCustomFields($clientId, $custFields);
			}
			$client = array('active'=>1);
			$clientPrefs = array();
			$custFields = array();
			$clientNameParts = array();

			continue;
		}
		else if(strpos($line , 'Client Household Info') === 0) {
			$householdMode =  true;
			if($client['clientid']) {
				foreach($client as $k=>$v) {
					if($v && is_array($v)) {
						$parts = array();
						foreach($v as $label=>$val) $parts[] = "$label: $val";
						$client[$k] = join("\n", $parts);
					}
				}
				$clientId = $client['clientid'];
				updateTable('tblclient', $client, "clientid=$clientId", 1);
//echo "<hr>".print_r($custFields, 1);				
				saveClientCustomFields($clientId, $custFields);
			}
			$client = null;
			$clientPrefs = array();
			$custFields = array();
			$clientNameParts = array();
		}
		$daMap = $petMode ? $petFieldMap : $fieldMap;
//echo "<hr>daMap: ".print_r($daMap, 1);			
		foreach($daMap as $k => $disp) {
//echo "<hr>petMode [$petMode] Disp: $disp";		
//if($disp == 'fname' || $disp == 'lname') echo "<hr>Pet: ".print_r($pet, 1)." Client: [".print_r($client, 1).']';
			if(strpos($line , $k) === 0) {
				if(strpos($disp, '--') === 0) continue;
				$v = trim(substr($line, strlen($k)));				
				if(strpos($disp, '+') === 0) {
					if(!$v) continue;
					if($petMode) $pet[substr($disp, 1)][$k] = $v;
					else $client[substr($disp, 1)][$k] = $v;
				}
				else if(strpos($disp, 'fn|') === 0) {
					$disp = explode('|', $disp);
					if($petMode) $pet = call_user_func_array($disp[1], array(&$pet, $v));
					else $client = call_user_func_array($disp[0], array(&$client, $v));
				}
					
				else if(strpos($disp, 'pref|') === 0) {
					$disp = explode('|', $disp);
					$v = $disp[1] == 'YesNo' ? ($v == 'Yes' ? '1' : null) : 
					     ($disp[1] == 'YesNoNot' ? ($v == null ? '1' : 'Yes') : $v);
					$clientPrefs[$disp[2]] = $v;
				}
				else if(strpos($disp, 'cust') === 0) {
					if($v == 'Please Select One...') $v = '';
					if($petMode) {
						$custFields[$ltCustomPetFieldKeys[$k]] = $v;
					}
					else $custFields[$ltCustomFieldKeys[$k]] = $v;
				}
				else if($disp == 'email') processEmails($v, $client);
				else if(($householdMode || $petMode) && ($disp == 'fname' || $disp == 'lname')) {
					$clientNameParts[$disp] = mysqli_real_escape_string($v);
					if(count($clientNameParts) == 2) {
						$clientNameParts = "{$clientNameParts['fname']} {$clientNameParts['lname']}";
						$client = fetchAssociations(
								"SELECT clientid FROM tblclient 
									WHERE CONCAT_WS(' ', fname, lname) = '$clientNameParts'", 1);
						if(count($client) == 0) echo "No client named [$clientNameParts] found.  Ignoring.<br>";
						if(count($client) > 1) echo "Multiple clients named [$clientNameParts] found.  Ignoring.<br>";
						else $client = $client[0];
//echo "<hr>Pet: ".print_r($pet, 1)." Client: [".print_r($client, 1).']';
					}
				}
				else if($petMode) $pet[$disp] = $v;
				else $client[$disp] = $v;
			}
		}
	}
	if($petMode && $pet && $client['clientid']) {
		$pet['ownerptr'] = $client['clientid'];
		$petId = insertTable('tblpet', $pet, 1);
		savePetCustomFields($petId, $custFields, '', $pairsOnly=true);
	}
	else if(!$petMode && $client) {
		foreach($client as $k=>$v) {
			if($v && is_array($v)) {
				$parts = array();
				foreach($v as $label=>$val) $parts[] = "$label: $val";
				$client[$k] = join("\n", $parts);
			}
		}
		if($householdMode && ($clientId = $client['clientid']))
			updateTable('tblclient', $client, "clientid=$clientId", 1);
		else {
			$clientId = saveNewClient($client);
		}	
		
		foreach($clientPrefs as $k=>$v)
			setClientPreference($clientId, $k, $v);
		saveClientCustomFields($clientId, $custFields);
	}
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
	$disposition = $fieldMap[$key];
	$val = lineVal($key);
	if(in_array($key, array('Email:','Alt Email:', 'D.O.B.:')))
		$val = trim(str_replace('image', '', $val));
	if(valIsNull($key, $val)) $val = null;
	if(!$val) return;
//echo "$key<br>";	
	$boxCounts[$box]+= 1;
	if($disposition[0] == '+') { $add = true; $disposition = substr($disposition, 1); }
	if($disposition[0] == '*') { 
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
	return true;
}

function handleVet($nameOrNumber, $whichOne) {
//echo "VET: 	[$nameOrNumber], $whichOne<p>";
	global $client;
	if(!$nameOrNumber) return;
	if($whichOne == 'name') {
		$vetid = fetchRow0Col0("SELECT * FROM tblclinic WHERE clinicname = '".mres($nameOrNumber)."'");
		if(!$vetid) $vetid = insertTable('tblclinic', array('clinicname'=>$nameOrNumber), 1);
		if(!$client['clinicptr']) $client['clinicptr'] = $vetid;
		$client['clinics'][$nameOrNumber] = 1;
	}
	else updateTable('tblclinic', array('officephone'=>$nameOrNumber), 
												"clinicid = {$client['clinicptr']} AND officephone IS NULL", 1);
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
	if($box) {// finish processing box
		if($box == 'contact') {
			$doppl = fetchFirstAssoc("SELECT * FROM tblclient WHERE fname = '".mres($client['fname'])."'
																AND lname = '".mres($client['lname'])."'
																AND homephone = '".mres($client['homephone'])."'");
			if($doppl && !$override) {
				echo "<font color=red>Client {$client['fname']} {$client['lname']} already exisits.<br></font>";
				return 'quit';
			}
			if(!$doppl || $override) {
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

function handleSex(&$pet, $v) {
	$pet['sex'] = strpos($v, 'M') === 0 ? 'm' : (
								strpos($v, 'F') === 0 ? 'f' : '');
	return $pet;
}


function generatePRcustomPetFields() {
	global $prcustomPetFields, $petFieldMap;
	$petFieldMap = "First Name=fname
	Last Name=lname
	Pet's Name=name
	Sex=fn|handleSex
	Species=type
	Breed=breed
	Age=--
	Birthday=dob
	Description=description
	Identifying Marks=+notes
	License Number=cust
	Tags On Collar=cust
	Electronic Tag=cust
	Veterinarian=cust
	Type Of Food=cust
	Type Of Treats=cust
	Where Eats=cust
	Where Food Stored=cust
	Where Treats Stored=cust
	When Eats=cust
	Rabbies Vacination=cust
	Other Vacinations=cust
	Illnesses=cust
	Symptoms=cust
	Type Of Medication=cust
	Dosage=cust
	Reactions=cust
	How Administered=cust
	What If Missed=cust
	Fears=cust
	Behavior Problems=cust
	Behavior On Leash=cust
	Where Sleeps=cust
	History Of Biting=cust.
	Socialized=cust
	Hiding Places=cust
	Food Aggressive=cust
	Potty Habits=cust";
	
	foreach(explodePairPerLine($petFieldMap, $sepr='=') as $field => $disposition)
		$arr[trim($field)] = trim($disposition);
	$petFieldMap = $arr;
	
	foreach($petFieldMap as $label => $type) {
		if($type == 'cust') {
			if(in_array($label, explode(',','Rabbies Vacination,Rabies Vacination,History Of Biting,Socialized,Food Aggressive')))
				$type = 'boolean';
			else $type = 'oneline';
			$prcustomPetFields[trim($label)] = $type;
		}
	}
}