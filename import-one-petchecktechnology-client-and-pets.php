<?// import-one-petchecktechnology-client-and-pets.php

/*

This script parses the text obtained by Ctrl-A, Ctrl-V on a single client's account review in BettWalka.

Sample: S:\clientimports\BettaWalkaClientSample.txt
https://leashtime.com/import-one-bettawalka-client-and-pets.php?file=xxxxx

BEFORE USING THIS SCRIPT:

See https://bdw.bettawalka.com/customfields.do (Business > Administration > Custom Fields) and adjust initializePCTPetCustomFields accordingly.

*/
require_once "gui-fns.php";
require_once "custom-field-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "item-note-fns.php";
require_once "client-fns.php";
require_once "field-utils.php";

/*

<form action="https://www.petchecktechnology.com/wms2.0/customers/detail/863" method="post" accept-charset="utf-8" id="customerform"><input name="user_id" id="user_id" value="863" type="hidden">
<h3 class="text" style="display:inline;">Bonnie Klein</h3> <span id="summary_edit"><a href="javascript:void(0)" onclick="showEditCustomer('summary');">Edit</a> | <a href="javascript:void(0)" onclick="archiveCustomer('1');">Activate</a> | <a href="javascript:void(0);" onclick="contentShowHref('https://www.petchecktechnology.com/wms2.0/user/new_password/863/customer');">Set New Password</a></span>

<p class="strong divide">Default Service Type</p>
<p class="text">30 minute Walk (3-5 per week)</p>

<p class="strong divide">House Alarm</p>
<p><span class="text"></span><span class="input"><input name="house_alarm_code" value="" type="text"></span></p>

<p class="strong divide">Notes</p>
<p><span class="text">Garage code - 2210</span><span class="input"><textarea name="notes" rows="10" style="width:600px;">Garage code - 2210</textarea></span></p>
	
<p class="strong divide">Key Notes</p>
<p><span class="text"></span><span class="input"><textarea name="key_info" rows="10" style="width:600px;"></textarea></span></p>

<p class="strong">Mobile Phone</p>
<p><span class="text">(571) 247-1678</span><span class="input"><input name="phone_mobile" value="(571) 247-1678" type="text"></span></p>

<p class="strong divide">Home Phone</p>
<p><span class="text">(703) 910-4660</span><span class="input"><input name="phone_home" value="(703) 910-4660" type="text"></span></p>

<p class="strong divide">Work Phone</p>
<p><span class="text"></span><span class="input"><input name="phone_work" value="" type="text"></span></p>

<p class="strong divide">Email</p>
<p><span class="text">BGKtoby@gmail.com</span><span class="input"><input name="email" value="BGKtoby@gmail.com" type="text"></span></p>
	
<h4 class="text">Toby</h4>
<label>Breed:</label>
<div class="data"><span class="text">Dog</span><span class="input"><input name="type" value="Dog" type="text"></span></div>
<label>Color:</label>
<div class="data"><span class="text"></span><span class="input"><input name="color" value="Gray" type="text"></span></div>
<label>Birthday:</label>
<div class="data"><span class="text">03/07/2012</span><span class="input"><input id="dp1406247584173" name="birthday" value="03/07/2012" class="date datepicker hasDatepicker" type="text"></span></div>
<label>Animal Hospital:</label>
<div class="data"><span class="text"></span><span class="input"><input name="animal_hospital" value="" type="text"></span></div>
<label>Vet Name:</label>
<div class="data"><span class="text">BURKE ANIMAL CLINIC / DR. MARSH</span><span class="input"><input name="vet_name" value="BURKE ANIMAL CLINIC / DR. MARSH" type="text"></span></div>
<label>Vet Address:</label>
<div class="data">
<span class="text">6307 LEE CHAPEL ROAD</span><span class="input small gray"><input name="vet_address" value="6307 LEE CHAPEL ROAD" type="text">Street Address</span>	
<p class="input small gray"><input name="vet_address2" value="" type="text">Street Address 2</p>
<p><span class="text">BURKE, VA 22015</span></p><p class="input"><input name="vet_city" value="BURKE" type="text"></p><p class="input small gray">City</p><p class="input"><select name="vet_state">

<span class="input" style="display:none;"><input name="name" value="Toby" type="text"></span>
<label>Vet Phone:</label>
<div class="data"><span class="text"><p>703-569-9600</p></span><span class="input"><input name="vet_phone" value="703-569-9600" type="text"></span></div>
<label>Rabies Vaccination Date:</label>
<div class="data"><span class="text"><p>03/07/2012</p></span><span class="input"><input id="dp1406247584174" name="rabies_expiration" value="03/07/2012" class="date datepicker hasDatepicker" type="text"></span></div>
<label>Medication Info:</label>
<div class="data"><span class="text">N/A</span><span class="input small gray"><textarea name="medicine_info">N/A</textarea></span></div>
<label>Collar/Leash Info:</label>
<div class="data"><span class="text">Wears collar. Leash by door on cabinet</span><span class="input small gray"><textarea name="collar_info">Wears collar. Leash by door on cabinet</textarea></span></div>
<label>Feeding Info:</label>
<div class="data"><span class="text"></span><span class="input small gray"><textarea name="feeding_instructions"></textarea></span></div>
<label>Pet Notes:</label>
<div class="data"><span class="text"></span><span class="input small gray"><textarea name="notes"></textarea></span></div>

*/
$petCustomFields = initializePCTPetCustomFields();

$petFieldMap =
trim("
Breed=breed
Color:=color
Birthday=handleBirthday
Animal Hospital=clinicname
Vet Name=vetname
Vet Phone=officephone
Vet Address=vetaddress
Street Address 2=vetcitystatezip
Rabies Vaccination Date=*{$petCustomFields[0]}
Medication Info=*{$petCustomFields[1]}
Collar/Leash Info=*{$petCustomFields[2]}
Feeding Info=*{$petCustomFields[3]}
Pet Notes=+notes
"
);

$arr = array();
foreach(explodePairPerLine($petFieldMap, $sepr='=') as $field => $disposition)
	$arr["$field"] = $disposition;
$petFieldMap = $arr;



$fieldMap = // <p class="strong">Address</p> next line: <p><span class="text">1853 Faversham Way</span>
trim("
Default Service Type=+notes
House Alarm=alarminfo
Key Notes=+notes
Notes=+notes
Mobile Phone=cellphone
Home Phone=homephone
Work Phone=workphone
Email=email
Street Address 2=citystatezip
Street Address=street2
Address=street1
"
);
$arr = array();
foreach(explodePairPerLine($fieldMap, $sepr='=') as $field => $disposition)
	$arr["$field"] = $disposition;
$fieldMap = $arr;



function importClient($showUnhandled=false) {
	global $line, $rawLine, $lineNum, $client, $clientid, $pet, $pets;
	//Alarm Code In,Alarm Code Out
	extract($_REQUEST);
	ob_start();
	ob_implicit_flush(0);

	if($file) $strm = fopen("/var/data/clientimports/$file", 'r');
	else $strm = fopen("data://text/plain,$clientdata" , 'rb');
	$client = array();
	
	$activeSetting = -1;

	while(!$done && getLine($strm)) {
//if($lastPattern == 'Address:') echo "($box) CityStateZip: [$line]<br>";		
//echo "<font color=blue>$lineNum</font><br>";
//echo "<font color=green>".htmlentities($line)."</font><br>";
		if(!$client && strpos($line, '<form action="https://www.petchecktechnology.com/wms2.0/customers/detail/') === 0) {
			$start = strlen('<form action="https://www.petchecktechnology.com/wms2.0/customers/detail/');
			//echo "Starting client #".substr($line, $start, strpos($line, '"', $start) - $start).'<br>';
		}
		else if(strpos($line, "radio on inactive") !== FALSE) $client['active'] = 0;
		else if(strpos($line, "radio on active") !== FALSE) $client['active'] = 1;
		else if(strpos($line, '<h3 class="text" style="display:inline;">') === 0) {
			$clientStarted = true;
			handleFnameSpaceLname(firstElementContents($line, 'h3'), $client);
		}
		else if(strpos($line, '<h4 class="text">') === 0) {
			if($pet) $pets[] = $pet;
			$pet = array('name'=>str_replace(',', '/', strip_tags($line)));
		}
		else if(strpos($line, "payment_info_edit")) {
			if($pet) $pets[] = $pet;
			$done =  true;
		}
		else if($pet) processPetField($line, $strm);
		else if($clientStarted) processClientField($line, $strm);
	}
	
	if($client['email'] && !isEmailValid($client['email'])) {
		$client['notes'][] = "Bad email address: [{$client['email']}]";
		unset($client['email']);
	}
	if($client['notes']) $client['notes'] = join("\n", (array)$client['notes']);
	
	if(!$client['fname'] || !$client['lname']) 	echo "<font color=red>Client name is incomplete: [{$client['fname']}] [{$client['lname']}].  Aborting.</font>";
	else {
		// process vets/clinics in pets
		$clientVetSet = false;
		foreach((array)$pets as $pet) { 
			$vetAndClinic = handleVet($pet['vet']); // creates vets and clinics
			if($vetAndClinic && !$clientVetSet) {
	//echo "<p>CLIENT: ".print_r($client,1);print_r($vetAndClinic);	
				$clientVetSet = 1;
				foreach($vetAndClinic as $k=>$v) $client[$k] = $v;
			}
		}
		$clientptr = saveNewClient($client);

		foreach((array)$pets as $pet) {
			$pet['ownerptr'] = $clientptr;
			savePet($pet);
		}

		echo "Client {$client['fname']} {$client['lname']}  added with pets: ";
		$pets = fetchCol0("SELECT CONCAT_WS(' ', name, CONCAT_WS('', '(', type, ')')) FROM tblpet WHERE ownerptr = $clientptr");
		echo $pets ? join(', ', $pets) : 'none';
	}
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function handleVet($vetInfo) {
	if(!$vetInfo) return;
	$extras = array();
	if($vetInfo['officephone']) $extras['officephone'] = $vetInfo['officephone'];
	if($vetInfo['address']) $extras['street1'] = $vetInfo['address'];
	if($vetInfo['city']) $extras['city'] = $vetInfo['city'];
	if($vetInfo['state']) $extras['state'] = $vetInfo['state'];
	if($vetInfo['zip']) $extras['zip'] = $vetInfo['zip'];
	
	if($vetInfo['clinicname'] && 
			!($clinicId = fetchRow0Col0("SELECT * FROM tblclinic WHERE clinicname = '".mres($vetInfo['clinicname'])."'"))) {
		$clinic = array_merge($extras, array('clinicname'=>$vetInfo['clinicname']));
		$clinic['clinicid'] = ($clinicId = insertTable('tblclinic', $clinic, 1));
		;
	}
	
	if($vetInfo['vetname'] && 
			!($vetId = fetchRow0Col0("SELECT * FROM tblclinic WHERE CONCAT_WS(' ', fname, lname) = '".mres($vetInfo['vetname'])."'"))) {
		$vet = array_merge($extras);
		handleFnameSpaceLname($vetInfo['vetname'], $vet);
		if($clinicId) $vet['clinicptr'] = $clinicId;
		$vetId = insertTable('tblvet', $vet, 1);
	}
	
	if($clinic) $result['clinicptr'] = $clinicId;
	if($vet) $result['vetptr'] = $vetId;
	return $result;
}




function processPetField($line, $strm) {
	global $pet, $petFieldMap;
//echo "<hr>".htmlentities($line)."<br>";			
	foreach($petFieldMap as $k => $v) {
		if(strpos($line, ">$k")) {
//echo "<br><b>$k</b>";			
//if($v == 'vetcitystatezip') echo "CITY[$content]";				
			if($v[0] == '+') { $add = true; $v = substr($v, 1); }
			if($v[0] == '*') { $custom = true; $v = substr($v, 1); }
			if($v == 'vetaddress') getLine($strm);
			$content = nextLineContents($strm);
			if($add && strpos($line, '<span') !== FALSE) {  // handle multiline notes
				while(strpos($line, '</span') === FALSE)
					$content .= "\n".nextLineContents($strm);
			}
			
			if(!$content) continue;
			if($v == 'handleBirthday') {
				if(strtotime($content))	$pet['dob'] = $content;
				else $pet['notes'][] = "Birthday: $content";
			}
			else if($custom) $pet['custom'][$v] = $content;
			else if($v == 'clinicname') {
				$pet['vet'] = $pet['vet'] ? $pet['vet'] : array();
				$pet['vet']['clinicname'] = $content;
				$pet['notes'][] = "Clinic name: $content";
			}
			else if($v == 'vetname') {
				$pet['vet'] = $pet['vet'] ? $pet['vet'] : array();
				$pet['vet']['vetname'] = $content;
				$pet['notes'][] = "Vet name: $content";
			}
			else if($v == 'officephone') {
				$pet['vet'] = $pet['vet'] ? $pet['vet'] : array();
				$pet['vet']['officephone'] = $content;
				$pet['notes'][] = "Vet phone: $content";
			}
			if($v == 'vetaddress') {
				$pet['vet'] = $pet['vet'] ? $pet['vet'] : array();
				$pet['vet']['address'] = $content;
				$pet['notes'][] = "Vet address: $content";
			}
			else if($v == 'vetcitystatezip') {
				getCityStateZip($content, $pet['vet']);
				$pet['notes'][] = "Vet city: $content";
			}
			else if($add) $pet[$v][] = "$k: $content";
			else $pet[$v] = $content;
//echo "$k: $content<br>";			
			break;
		}
	}
}


function processClientField($line, $strm) {
	global $client, $fieldMap;
//echo "<hr>".htmlentities($line);
	foreach($fieldMap as $k => $v) {
		if(strpos($line, ">$k")) {
//echo "[[$v]] ";			
			if($v[0] == '+') { $add = true; $v = substr($v, 1); }
			$content = nextLineContents($strm);
//if($k == 'Notes') echo "[$k:$v]($content)";				
			if(!$content) continue;
			if($add) $client[$v][] = "$k: $content";
			else if($v == 'citystatezip') getCityStateZip($content, $client);
			else $client[$v] = $content;
			break;
		}
	}
//echo '<br>'.print_r(	$client, 1);
}

function getCityStateZip($str, &$destination) { // assumes ZIP, not ZIP+4
	$str = str_replace('  ', ' ', $str);
	$parts = explode(' ', $str);
	if($parts) {
		if($parts[count($parts)-1] == 'image') array_pop($parts);
		if($parts && preg_match('/^\d{5}([\-]\d{4})?$/', $parts[count($parts)-1])) $destination['zip'] = array_pop($parts);
		if($parts && strlen($parts[count($parts)-1]) == 2) $destination['state'] = array_pop($parts);
//echo "[[".print_r($parts, 1)."]]<br>";		
		if($parts) {
			$city = trim(join(' ', $parts));
			if($city && strrpos($city, ',') == strlen($city)-1)
				$city = substr($city, 0, strlen($city)-1);
			$destination['city'] = $city;
		}
	}
}

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(' ', trim($str)));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
}

function mres($s) { return mysql_real_escape_string($s); }

function redline($s){$s = is_array($s) ? print_r($s, 1) : $s; echo "<font color=red>[$s]</font></br>";}


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


function savePet($pet) {
//echo "PET[".print_r($pet, 1)."]";	
	global $client;
	if($pet['notes']) $pet['notes'] = join("\n", (array)$pet['notes']);
	$pet['type'] = guessTypeByBreed($pet['breed']);
	$petId = insertTable('tblpet', saveablePet($pet, 1), 1);
	savePetCustomFields($petId, $pet['custom'], $number=null, $pairsOnly=true);
}

function firstElementContents($line, $tag) {
	$el = substr($line, 0, strpos($line, "</$tag>")+strlen("</$tag>"));
	return strip_tags($el);
}

function nextLineContents($strm) {
	global $line;
	getLine($strm);
	$localLine = $line;
	$start = strpos($localLine, '<')+1;
	$stop = min(strpos($localLine, '>'), strpos($localLine, ' '));
	$tag = substr($localLine, $start, $stop-$start);
//echo "TAG[$tag]";
	if(strpos($localLine, "<$tag") !== FALSE) 
		while(strpos($localLine, "</$tag") === FALSE) { // handle multiline notes
			getLine($strm);
			$localLine .= "\n$line";
	}
	$el = substr($localLine, 0, strpos($localLine, "</$tag>")+strlen("</$tag>"));
	$contents = trim(strip_tags($el));
	// KLUDGE
	$halfLength = strlen($contents)/2;
	if(substr($contents, 0, $halfLength) == substr($contents, $halfLength, $halfLength))
		$contents = substr($contents, 0, $halfLength);
	return $contents;
}

function nextLineContentsORIG($strm) {
	global $line;
	getLine($strm);
	$start = strpos($line, '<')+1;
	$stop = min(strpos($line, '>'), strpos($line, ' '));
	$tag = substr($line, $start, $stop-$start);
	$el = substr($line, 0, strpos($line, "</$tag>")+strlen("</$tag>"));
	$el = substr($line, $start-1, strpos($line, "</$tag>")+strlen("</$tag>"));
	$contents = trim(strip_tags($el));
	// KLUDGE
	$halfLength = strlen($contents)/2;
	if(substr($contents, 0, $halfLength) == substr($contents, $halfLength, $halfLength))
		$contents = substr($contents, 0, $halfLength);
	return $contents;
}

function nextLineContentsMultiLine($strm) {
}






function getLine($strm) {
	global $line, $lineNum, $rawLine;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$rawLine = $line;
	$line = trim($line);
	return true;
}

function initializePCTPetCustomFields() {
	require_once "preference-fns.php";
	//setPreference('petTypes', 'Dog|Cat|Reptile|Small Animal|Bird');
	//$firstN = 1;
	$firstN = 6;
	$fields = array(
					'Rabies Vaccination Date|1|oneline|1',
					'Medication Info|1|text|1',
					'Collar/Leash Info|1|text|1',
					'Feeding Info|1|text|1',
					);
	foreach($fields as $i => $val) {
		if(!$_SESSION['preferences']["petcustom".($i+$firstN)]) 
			setPreference("petcustom".($i+$firstN), $val);
			$custFields[] = "petcustom".($i+$firstN);
		}
	return $custFields;
}

function guessTypeByBreed($str) {
	$rabbits = 'rabbit';
	$birds = 'bird';
	$turtles = 'turtle';
	$cats = 'cat,tuxedo,siamese,kitten,kitty,tabby,pek,dsh,dsc,shorthair,short hair,dsh,dlh,maine,coon,persian,Chartreaux,Chartreux';
	$dogs = 'golden,corgi,bernard,beagle,jack,russell,terrier,cairn,hound,collie,dachshund,pit,bull,mutt,mix,chihuahua,shepherd,weim,corg,dane,pomer'
					.',schnauzer,tzu,lab,oodle,mastiff,pug,poo,havanese,pit,lab,boxer,retriev,bull,orki,spaniel,britn,chow,ridge,wheaton,russel,king'
					.',eskimo,rott,hound,bijon,bishon,maltese,westie,sheltie,dog,cavachon,shiba,inu,pup,cavalier,aussie,coton,terrior,oodle,sheherd,shep,border,wemer'
					.'visla,wire,Catahoula,ibizan,dach,rhod,ridgeb';
	if($match = likeOneOf($str, explode(',', $cats))) return 'Cat';
	if($match = likeOneOf($str, explode(',', $dogs))) return 'Dog';
	if($match = likeOneOf($str, explode(',', $rabbits))) return 'Rabbit';
	if($match = likeOneOf($str, explode(',', $birds))) return 'Bird';
	if($match = likeOneOf($str, explode(',', $turtles))) return 'Turtle';
}

function likeOneOf($str, $arr) {
	$str = strtoupper($str);
	foreach($arr as $pat)
		if(strpos($str, strtoupper($pat)) !== FALSE)
			return $pat;
}

