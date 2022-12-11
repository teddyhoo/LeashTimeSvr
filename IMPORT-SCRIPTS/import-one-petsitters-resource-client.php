<?// import-one-petsitters-resource-client.php

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
E-Mail Distribution Enabled=pref|YesNo|optOutMassEmail
Locked Out=--pref|YesNo|optOutMassEmail
User Name=username
Password=--password
Preferred Pet Sitter=fn|handlePreferredpetsitter
"
);

foreach(explodePairPerLine($fieldMap, $sepr='=') as $field => $disposition)
	$arr["$field:"] = $disposition;
$fieldMap = $arr;


$nullStrings = "
";
foreach(explodePairPerLine($nullStrings, $sepr=':') as $field => $disposition)
	$arr["$field:"] = $disposition;
$nullStrings = $arr;

function importClients($showUnhandled=false) {
	extract($_REQUEST);
	ob_start();
	ob_implicit_flush(0);

	if($file) $strm = fopen("/var/data/clientimports/$file", 'r');
	else $strm = fopen("data://text/plain,$clientdata" , 'rb');
	while(!$done && getLine($strm)) {
		if(strpos($line , 'Client Info') === 0) {
			if($client) {
				$clientId = saveClient($client);
				saveClientPrefs($clientId, $clientPrefs);
			}
			$client == array();
			$clientPrefs == array();
			continue;
		}
		foreach($fieldMap as $k => $disp) {
			if(strpos($line , $k) === 0) {
				if(strpos($disp, '--') === 0) continue;
				$v = trim(substr($line, strlen($k)));
				if(strpos($disp, 'pref|') === 0) {
					$disp = explode('|', $disp);
					$v = $disp[1] == 'YesNo' ? ($v == 'Yes' ? '1' : '0') : $v;
					$clientPrefs[$k] = $v;
				}
				else $client[$disp] = $v;
			}
		}
	}
}
	
function importClient($showUnhandled=false) {
	global $line, $rawLine, $lineNum, $client, $clientid, $stack, $lastLine, $box, $boxCounts, $econtact, $pet, $lastPattern;
	//Alarm Code In,Alarm Code Out
	extract($_REQUEST);
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
				addOfficeNote($note['subject'], $note['body']);
				$officeNotesLines[] = "{$note['subject']}: {$note['body']}";
			}
			if($officeNotesLines) $client['officenotes'] = join("\n", $officeNotesLines);
			//echo "Client: ".print_r(fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid"), 1)."<p>";
			//echo "Vet: ".print_r(fetchFirstAssoc("SELECT * FROM tblclinic WHERE clinicid = '{$client['clinicptr']}'"), 1)."<p>";
			//foreach(fetchAssociations("SELECT * FROM tblpet WHERE ownerptr = $clientid") as $pet)
				//echo print_r($pet, 1)."<p>";
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

		else if(lineStartsWith($pat = 'basicinfo>Name:')) $pet['name'] = getPetName(lineVal($pat));
		else if(lineStartsWith($pat = 'basicinfo>Key Info:')) appendField('client>keydescription', '', lineVal($pat));
		else if(lineStartsWith($pat = 'ownerinfo>Name:')) ; // no-op
		else if(lineStartsWith($pat = 'emergency-contact>Name:')) $econtact['name'] = lineVal($pat); 
		else if(lineStartsWith($pat = 'ownerinfo>Phone#:')) ; // no-op
		else if(lineStartsWith($pat = 'emergency-contact>Notes:')) appendField('note', 'Notes:', lineVal($pat));
		else if(lineStartsWith($pat = 'Name:')) getFnameLname(lineVal($pat));
		else if(lineStartsWith($pat = 'Alt Name:')) getFnameLname(lineVal($pat), 'fname2', 'lname2');
		else if(lineStartsWith($pat = 'Address:')) {$stack[count($stack)-1]['street1'] = lineVal($pat);}
		else if($lastPattern == 'Address:') {getCityStateZip($line); $lastPattern = '';}
		else if(lineStartsWith($pat = 'Normal Minder:')) $client['defaultproviderptr'] = findSitter(lineVal($pat));
		else if(lineStartsWith($pat = 'Normal Walker:')) $client['defaultproviderptr'] = findSitter(lineVal($pat));
		else if(lineStartsWith($pat = '2nd Walker:')) appendField('note', '2nd Walker:', lineVal($pat));
		else if(lineStartsWith($pat = 'Vet Practice:')) handleVet(lineVal($pat), 'name');
		else if(lineStartsWith($pat = 'Vet Phone Number:')) handleVet(lineVal('Vet Phone Number:'), 'number');
		else if(lineStartsWith($pat = 'Sex:')) {
			$val = strtoupper(lineVal($pat)); 
			$pet['sex'] = !$val ? null : ($val[0] == 'M' ? 'm' : ($val[0] == 'F' ? 'f' : null));
		}
		else if(!handleMappedFields() && $showUnhandled) echo "<font color=gray>Unhandled: ".strip_tags($line).'</font><br>'; 
		$lastLine = $line;
	}
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
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