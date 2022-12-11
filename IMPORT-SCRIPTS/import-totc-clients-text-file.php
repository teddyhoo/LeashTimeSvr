<? // import-totc-clients-text-file.php
/* file is a txt cobining many client/pet profiles in loose format:
PET:                            WINSTON (older, mixed breed )

ADDRESS:          901 15th Street South, APT 325

CLIENT:        Daniel Greenspan

WALK TIME:        2-4

WINSTON ON: Thursday, August 12th
	             Friday, August 13th      

COMMENTS:           He is a very sweet dog. He does pull a bit so, client has suggested walker use Halti collar. He may not need it. This is the 
...
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";

locked('z-');

extract($_REQUEST);

$file = "/var/data/clientimports/$file";

$strm = fopen($file, 'r');

initCustFields();
ensureCustFields();
initIgnorableHeaders();
getLine($strm);
$unknown = 0;
while($section = getSection($strm)) {
	$allSections[] = $section['key'];
	if(in_array($section['key'], array('PET','PETS','PET PROFILE'))) {
		$message = $client ? checkClient($client) : '';
		if($message) {
			$unknown += 1;
			echo "<font color=red>ERROR: $message</font><p>";
			$client['lname'] = "UNKNOWN";
			$client['fname'] = "Person$unknown";
		}
		if($client) {
			createClient($client);
			echo "{$client['fname']} {$client['lname']} created.<p>"; // 
		}
		$client = array('pets' =>handlePetNames($section['value'] ), 'petLine'=>$section['value'], 'active'=>1);
		echo "<hr>";
		echo "<ul>";foreach($client['pets'] as $pet) echo "<li>{$pet['name']} - {$pet['description']}";echo "</ul>";
		$numPets += count($client['pets']);
		$numClients++;
	}
	else if($section['key'] == 'CLIENT') handleFnameSpaceLname($section['value'], $client);
	else if($section['key'] == 'ADDRESS') echo "<font color=darkgreen>".print_r(handleAddress($section['value'], $client), 1)."</font><p>";
	else handleSection($section['key'], $section['value'], $client);
	
	//$client[$section['key']][] = $section['value'];
	echo "<span style='font-weight:bold;color:blue'>{$section['key']}</span><br>"
				.str_replace("\n", '<br>', str_replace("\n\n", '<p>', str_replace("\r", '', $section['value'])))//.$section['value']
				."<p>";
}


$uniqueSections = array();
foreach($allSections as $k) $uniqueSections[$k]++;
echo "<hr><hr>$numClients profiles, $numPets pets, ".count($allSections)." sections (".count($uniqueSections)." unique), $n lines - Bad profiles: $badProfiles - Custom Fields: ".count($custFields)."<p>";
ksort($uniqueSections);
foreach($uniqueSections as $k =>$v) {
	if($custFieldLookup[$k]) $k = "<font color=green>$k (=> {$custFieldLookup[$k]})</font>";
	echo "$k: $v<br>";
}
echo "<hr>Bad Profiles:<p>".join('<br>', $badProfileList);
echo "<hr>Custom Fields:<p>".join('<br>', $custFields);

// ************************************************************************

function createClient(&$clientData) {
	global $custFieldsByLabel;
	require_once "custom-field-fns.php";
	require_once "client-fns.php";
	if($clientData['notes']) $clientData['notes'] = join("\n", $clientData['notes']);
/*print_r($clientData);*/
	$clientptr = saveNewClient($clientData);
	foreach((array)$clientData['custom'] as $k => $v)
		if(is_array($v))
			$customFields[$custFieldsByLabel[$k]] = join("\n", $v);
	saveClientCustomFields($clientptr, $customFields, $pairsOnly=true);
	foreach((array)$clientData['pets'] as $pet) {
		$pet['ownerptr'] = $clientptr;
		insertTable('tblpet', $pet, 1);
	}

}


function checkClient($client) {
	global $badProfiles, $badProfileList;
	if(!$client['lname'] || !$client['fname']) {
		$badProfiles++;
		$badProfileList[] = $client['petLine'];
		return "NO CLIENT: ".print_r($client, 1);
	}
}

function getLine($strm) {
	global $line, $leadingSpace, $n;
	if(($line = fgets($strm)) !== false) {
		$line = cleanseString($line);
		$leadingSpace = strlen($line) && in_array($line[0], array("\t", " "));;
		$line = trim($line);
	}
//echo "[LINE] $line".($line === false ? 'FALSE' : '')."<br>";
	$n++;
	return $line;
}

function handleAddress($val, &$client) {
	$addr = array_map('trim', explode("\n", $val));
	$client['directions'] = $val;
	if(!$addr || !$addr[0]) return;
	// use only first line
	getCityStateZip($addr[0], $client);
	if(!$client['state'] && $addr[1]) {
		getCityStateZip($addr[1], $client);
		if($client['state']) $client['street1'] = $addr[0];
	}
	//if(count($addr) > 1) $client['street2'] = $addr[1];
	//if($addr) $client['street1'] = $addr[0];
	return "**** [{$client['street1']}] [{$client['street2']}] [{$client['city']}] [{$client['state']}] [{$client['zip']}]";
}

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(' ', $str));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
}

function getCityStateZip($str, &$destination) { // assumes ZIP, not ZIP+4
	$str = str_replace('  ', ' ', $str);
	//$parts = explode(' ', $str);
	foreach(decompose($str, ' ') as $x1) {
		if(strrpos($x1, ',') == strlen($x1)-1) $parts[] = $x1;
		else foreach(decompose($x1, ',') as $x2)
				$parts[] = $x2;
	}

//echo "[[".print_r($parts, 1)."]]<br>";		
	if($parts) {
		if($parts && preg_match('/^\d{5}([\-]\d{4})?$/', $parts[count($parts)-1])) $destination['zip'] = array_pop($parts);
		$stateCandidate = $parts[count($parts)-1];
		if($parts && strlen($stateCandidate) == 2 && !streetAbbrev($stateCandidate)) $destination['state'] = array_pop($parts);
//echo "[[{$destination['state']}]]";
		if(!$destination['state']) return;
		if($parts) {
			//$city = trim(join(' ', $parts));
			$city = array_pop($parts);
			if($city && strrpos($city, ',') == strlen($city)-1)
				$city = substr($city, 0, strlen($city)-1);
			if($city == 'Church') $city = array_pop($parts).' Church';
			$destination['city'] = $city;
			if($city) $destination['street1'] = trim(join(' ', $parts));
			if(strrpos($destination['street1'], ',') == strlen($destination['street1'])-1) 
				$destination['street1'] = substr($destination['street1'], 0, strlen($destination['street1'])-1);
		}
	}
}

function streetAbbrev($stateCandidate) {
	return in_array(strtolower($stateCandidate), array('st','dr','av','ln'));
}

function handlePetNames($str) {
	foreach(decompose($str, "\n") as $x00)
		foreach(decompose($x00, '+') as $x0)
			foreach(decompose($x0, ') and ') as $x1)
				foreach(decompose($x1, '&') as $x2)
					foreach(decompose($x2, ') ') as $x3)
						//foreach(decompose($x3, '/') as $x4)
						$petnames[] = $x3;
	foreach($petnames as $petname) {
		$pet = array();
		if($descr = strpos($petname, '(')) {
			$pet['name'] = trim(substr($petname, 0, $descr));
			$pet['description'] = trim(substr($petname, $descr));
			if(strpos($pet['description'], '(') === 0) $pet['description'] = trim(substr($pet['description'], 1));
			if(strrpos($pet['description'], ')') === strlen($pet['description'])-1) 
				$pet['description'] = trim(substr($pet['description'], 0, -1));
		}
		else $pet['name'] = $petname;
		$pets[] = $pet;
	}
			
	return $pets;
}

function decompose($str, $delim) {
	return array_map('trim', explode($delim, $str));
}

function getSection($strm) {
	global $line;
	$key = sectionStart($line);
	if(!$key) return false;
	$section = array(substr($line, strlen($key)+1));
	while(getLine($strm) !== false && !sectionStart($line))
		$section[] = $line;
	return array('key'=>trim($key), 'value'=>trim(join("\n", $section)));
}
	
function sectionStart($line) {
	global $leadingSpace, $ignoreHeaders;
	if($leadingSpace || strpos($line, ':') === false) return null;
	$key = trim(strtoupper(substr($line, 0, strpos($line, ':'))));
//if(strpos($key, 'PARK ADDRESS')) {echo "[$key]".' = ['.print_r(in_array($key, $ignoreHeaders)	,1).']. '.print_r($ignoreHeaders, 1);exit;}
	return in_array($key, $ignoreHeaders) || strlen($key) > 40 ? null : $key;
}
//ADDY IS A SWEET BUT CAUTIOUS PUP. THE COMMANDS SHE KNOWS

function initIgnorableHeaders() {
	global $ignoreHeaders;
	if(!$ignoreHeaders)
		$ignoreHeaders = explode("\n", <<<HEADERS
* PARK ADDRESS
.WALK
11
ALSO
BENTLEY
BOB
ENTRANCE TO HOME THROUGH GARARGE
GIBBON
MADDY
MON-FRI 11
PLEASE NOTE
PM
RANGER
SKYE
TEDDY
TIME
TUESDAYS ONLY 12
VOLLEY
MAJOR
MINOR
TO ARM HIT
TO DISARM HIT
WINSTON ON
HEADERS
);
	foreach($ignoreHeaders as $i => $v) $ignoreHeaders[$i] = trim($v); 
	return $ignoreHeaders;
}

function handleSection($key, $value, &$client) {
	global $custFields, $custFieldLookup;
//echo "$key: [".print_r($custFieldLookup[$key], 1).']<br>';
	if(in_array(($custField = $custFieldLookup[$key]), $custFields))
		$client['custom'][$custField][] = $value;
	else if(strpos($custField, '*') === 0) {
		$prefix = substr($custField, 1) == $key ? '' : "$key: ";
		$client[strtolower(substr($custField, 1))][] = "$prefix$value";
	}
}


function initCustFields() {
	global $custFields, $custFieldLookup;
	if(!$custFields) {
		$custFieldStrs = explode("\n", <<<CUSTFIELDS
ALARM
ASSIGNMENT,ASSIGNMENT DATE,ASSIGNMENT DATES,DATE,DATES
BAGS,BAGGIES
CAT CARE,CATS
DISPOSAL OF WASTE,WASTE,BAGGIES & DISPOSAL OF WASTE
DOG CARE
ENTRANCE
FEEDING/WATER,FEEDING,FOOD,FOOD/TREATS,TREAT,TREATS,WATER
HOME
IGUANA CARE
INSTRUCTIONS,INSTRUCTIONS FROM CLIENT,INSTRUCTIONS/PLAYTIME,INSTRUCTION SHEET,SHEET
KEY,KEYS,BUILDING
LEASH,LEASH/BAGS,PET CARRIER
LIGHTS/RADIO,LIGHTS
LITTER BOX,LITTER
MAIL/LIGHTS/TV/NEWSPAPER,MAIL,MAIL AND LIGHTS,MAIL AND TV,MAIL/NEWSPAPER,NEWSPAPER/MAIL
MANIFEST
MEDICATION,MEDS,MEDICINE
OTHER,RESTRICTIONS,SLEEPING,ALSO,CHRISTMAS GIFTS,DOORS & GATES
*NOTES,NOTE,COMMENTS,NOTES
PARKING,* PARKING
PAY
PERSONALITY,PERSOALITY,PERSONALITIES,PERSONALITIES/WALK
PLANTS
PLAY TIME,PLAYTIME
CLEANING SUPPLIES,CLEAN UP,CLEANING SUPPLIES,SUPPLIES
TOWELS,TOWEL,TOWEL/LINEN
TRASH
VET
WALK,WALK TIME,WALKING,WALKS,WALK DAYS
CUSTFIELDS
);
		foreach($custFieldStrs as $line) {
			$set = explode(',', trim($line));
			$sets[$set[0]] = $set;
			if(strpos($set[0], '*') !== 0) $custFields[] = $set[0];
		}
		foreach($sets as $key => $vals) {
			foreach($vals as $val) 
				$custFieldLookup[$val] = $key;
		}
	return $custFields;
	}
}

function ensureCustFields() {
	global $custFieldsByLabel, $custFields;
	require_once "preference-fns.php";
	foreach($custFields as $fieldname) {
		if(!($customN = fetchCol0("SELECT property FROM tblpreference WHERE value LIKE '$fieldname|'", 1))) {
			$maxFieldNum += 1;
			$customN = "custom$maxFieldNum";
			setPreference($customN, "$fieldname|1|text|1");
		}
		$custFieldsByLabel[$fieldname] = $customN;
	}
}

	
function OLDensureCustFields($fieldscsv, $pet=null) {
	global $custFieldsByLabel;
	if($pet) $PREFIX = 'pet';
	$rowCount++;
	if(!$custFieldsByLabel) {
		require_once "preference-fns.php";
		require_once "custom-field-fns.php";
		if(strpos($fieldscsv, '|')) {
			$types = explodePairsLine($fieldscsv);
			$customFields = array_keys($types);
		}
		else $customFields = explode(',',$fieldscsv);
		$allCustFields = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property LIKE '{$PREFIX}custom%'");
		foreach($allCustFields as $key => $descr)
			$definedFields[substr($descr, 0 , strpos($descr, '|'))] = substr($key, strlen("{$PREFIX}custom"));
		if($definedFields) $maxFieldNum = max($definedFields);
		foreach($customFields as $fieldname) {
			if(!$definedFields[$fieldname]) {
				$definedFields[$fieldname] = ($maxFieldNum += 1);
				$type = $types[$fieldname] ? $types[$fieldname] : 'oneline';
				setPreference("{$PREFIX}custom$maxFieldNum", "$fieldname|1|$type|1");
			}
		}
		$allCustFields = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property LIKE '{$PREFIX}custom%'");
		foreach($allCustFields as $key => $descr)
			$custFieldsByLabel[substr($descr, 0 , strpos($descr, '|'))] = $key;
		echo "FIELDS BY LABEL: ".print_r($custFieldsByLabel, 1).'<p>';
	}
}
	
