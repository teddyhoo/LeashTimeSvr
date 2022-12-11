<?// import-clients-csv-leashtime.php

// import the clients exported from LeashTime to a CSV

// https://LEASHTIME.COM/import-clients-csv-adhoc.php?file=nannydolittle/Clients-active.csv


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";

locked('o-');

extract($_REQUEST);

$file = "/var/data/clientimports/$file";

//clientid	lname	fname	email	homephone	cellphone	workphone	fax	pager	
//lname2	fname2	alternate	email2	cellphone2	street1	street2	city	state	zip	
// mailstreet1	mailstreet2	mailcity	mailstate	mailzip	
// active	prospect	birthday	vetptr	vetname	vetphone	clinicid	clinicname	clinicphone	
// defaultproviderptr	sittername	tblclient.notes	officenotes	tblclient.directions	alarmcompany	
// alarminfo	alarmcophone	setupdate	activationdate	deactivationdate	
// emergencyname	emergencylocation	emergencyhomephone	emergencyworkphone	emergencycellphone	emergencynote	emergencyhaskey	
// neighborname	neighborlocation	neighborhomephone	neighborworkphone	neighborcellphone	neighbornote	neighborhaskey	
// leashloc	foodloc	parkinginfo	garagegatecode	emergencycarepermission	nokeyrequired	referralname	
// mailtohome	"Pet Feeding Instructions"	"Pet Supply Location (food, treats, litter, etc)"	"Cleaning Supply Location and Towels For Muddy Paws"	
// "Pet's Fear & Favorite Hiding Places"	"Medication List and Instructions"	"Overnight Pet Sitting - Where should pet sitter sleep?"	"Overnight Pet Sitting - Where is your pet to sleep?"	"What rooms are off limits to your pet?"	"Overnight Pet Sitting - What appliances/facilities may your pet sitter use? (i.e. TV, dishwasher, microwave, shower, etc)"	"Location Of Fuse Box"	"Location Of Main Water Shut-Off"


echo "<hr>";

$delimiter = strpos($file, '.xls') ? "\t" : ',';
$strm = fopen($file, 'r');
//$line0 = trim(fgetcsv($strm, 0, $delimiter)); 
$row = fgetcsv($strm, 0, $delimiter);
print_r($row);
$dataHeaders = array_map('trim', $row);// consume first line (field labels)

function getCSVRow($strm) {
	return $_POST['mutiline'] ? mygetcsv($strm) : fgetcsv($strm);
}

function mygetcsv($strm) {  // handles EOLS inside quotes, as long as quotes balance
	global $delimiter;
	$quoteCount = 0;
	$totalCSV = array();
	do {
		$line = fgets($strm);
		for($i=0; $i < strlen($line); $i++) if($line[$i] == '"') $quoteCount++;
		$sstrm = fopen("data://text/plain,$line" , 'r');
		$csv = fgetcsv($sstrm, 0, $delimiter);
		if(!$totalCSV) $totalCSV = $csv;
		else {
			$totalCSV[count($totalCSV)-1] .= "\n".substr($csv[0], 0, strlen($csv[0])-1);
			for($i=1; $i < count($csv); $i++) $totalCSV[] = $csv[$i];
		}
	}
	while($quoteCount % 2 == 1);
	return $totalCSV;
}

echo $file."<p>";
echo "dataHeaders: ".count($dataHeaders).':<br>'.join(',', $dataHeaders).'<hr>';
//echo "map: ".print_r($map,1);exit;

//print_r($strm);
$n = 1;

//while($line = trim(fgets($strm))) echo "$line<br>";

$thisdb = $db;

$clientMap = array();  // mapID => clientid

$_SESSION['preferences'] = fetchPreferences();

$customFields = getCustomFields('activeOnly');

//print_r($_SESSION['preferences']);exit;
$vetptrs = fetchCol0("SELECT vetid FROM tblvet");
$clinicptrs = fetchCol0("SELECT clinicid FROM tblclinic");
$incompleteRow = null;
while($row = getCSVRow($strm, 0, $delimiter)) {
	$n++;
	// HANDLE EMPTY LINES
	if(skipRow($row)) {echo "<p><font color=red>Skipped Line #$n</font><br>";continue;}
	// HANDLE CONTINUATIONS OF INCOMPLETE LINES
	else handleRow($row);
	
}

echo "<hr>";

// CONVERSION FUNCTIONS
//clientid	lname	fname	email	homephone	cellphone	workphone	fax	pager	
//lname2	fname2	alternate	email2	cellphone2	street1	street2	city	state	zip	
// mailstreet1	mailstreet2	mailcity	mailstate	mailzip	
// active	prospect	birthday	vetptr	vetname	vetphone	clinicid	clinicname	clinicphone	
// defaultproviderptr	sittername	tblclient.notes	officenotes	tblclient.directions	alarmcompany	
// alarminfo	alarmcophone	setupdate	activationdate	deactivationdate	
// emergencyname	emergencylocation	emergencyhomephone	emergencyworkphone	emergencycellphone	emergencynote	emergencyhaskey	
// neighborname	neighborlocation	neighborhomephone	neighborworkphone	neighborcellphone	neighbornote	neighborhaskey	
// leashloc	foodloc	parkinginfo	garagegatecode	emergencycarepermission	nokeyrequired	referralname	
// mailtohome	"Pet Feeding Instructions"	"Pet Supply Location (food, treats, litter, etc)"	"Cleaning Supply Location and Towels For Muddy Paws"	
// "Pet's Fear & Favorite Hiding Places"	"Medication List and Instructions"	"Overnight Pet Sitting - Where should pet sitter sleep?"	"Overnight Pet Sitting - Where is your pet to sleep?"	"What rooms are off limits to your pet?"	"Overnight Pet Sitting - What appliances/facilities may your pet sitter use? (i.e. TV, dishwasher, microwave, shower, etc)"	"Location Of Fuse Box"	"Location Of Main Water Shut-Off"

$ignore = 'clientid,birthday,vetname,vetphone,clinicname,clinicphone,defaultproviderptr,sittername';

function booleanFromYN($val) { return $val == 'yes' ? '1' : '0'; }

function handleRow($row) {
	global $thisdb, $dataHeaders, $lastClient, $vetptrs, $clinicptrs;
	foreach($dataHeaders as $i => $label) {
//echo "$i: [$label] [{$row[$i]}]<p>";
		if(!$row[$i]) continue;
		if($label == 'clientid') continue;
		else if($label == 'alternate') getFnameLname($row[$i], $client, $fnameKey='fname2', $lnameKey='lname2');
		else if($label == 'active') $client['active'] = booleanFromYN($row[$i]);
		else if($label == 'vetptr' && in_array($row[$i], $vetptrs)) $client['vetptr'] = $row[$i];
		else if($label == 'clinicid' && in_array($row[$i], $clinicptrs)) $client['clinicptr'] = $row[$i];
		else if($label == 'tblclient.notes') $client['notes'] = $row[$i];
		else if($label == 'officenotes') $client['officenotes'][] = $row[$i];
		else if($label == 'tblclient.directions') $client['directions'] = $row[$i];
		else if($label == 'alarmcompany') $client['alarmcompany'] = $row[$i];
		else if($label == 'alarminfo') $client['alarminfo'] = $row[$i];
		else if($label == 'alarmcophone') $client['alarmcophone'] = $row[$i];
		else if($label == 'setupdate') $client['setupdate'] = date('Y-m-d', strtotime($row[$i]));
		else if($label == 'activationdate') $client['activationdate'] = date('Y-m-d', strtotime($row[$i]));
		else if($label == 'deactivationdate') $client['deactivationdate'] = date('Y-m-d', strtotime($row[$i]));
		else if($label == 'emergencycarepermission') $client['emergencycarepermission'] = booleanFromYN($row[$i]);
		else if(strpos($label, 'emergency') === 0) 
			$client['emergencycontact'][substr($label, strlen('emergency'))] = $row[$i];
		else if(strpos($label, 'neighbor') === 0) 
			$client['neighbor'][substr($label, strlen('neighbor'))] = $row[$i];
		else if($label == 'leashloc') $client['leashloc'] = $row[$i];
		else if($label == 'foodloc') $client['foodloc'] = $row[$i];
		else if($label == 'parkinginfo') $client['parkinginfo'] = $row[$i];
		else if($label == 'garagegatecode') $client['garagegatecode'] = $row[$i];
		else if($label == 'nokeyrequired') $client['nokeyrequired'] = booleanFromYN($row[$i]);
		else if($label == 'referralname') $client['referralname'] = $row[$i];
		else if($label == 'mailtohome') $client['mailtohome'] = booleanFromYN($row[$i]);
		else if(strpos($label, 'custom') === 0) $client['custom'][$label] = $row[$i];
		else $client[$label] = $row[$i];
//print_r($client);echo "<p>";
		
	}
//exit;	
	saveNewClient($client);
	echo "<p>CREATED CLIENT: {$client['fname']} {$client['lname']}";
	$newClientId = mysqli_insert_id();
	$lastClient = $newClientId;
	foreach((array)$client['custom'] as $field => $val)
		replaceTable("relclientcustomfield", 
			array('clientptr'=>$newClientId, 'fieldname'=>$field, 'value'=>$val), 1);
	if($client['emergencycontact']) saveClientContact('emergency', $newClientId, $client['emergencycontact']);
	if($client['neighbor']) saveClientContact('neighbor', $newClientId, $client['neighbor']);
}

function skipRow($row) {
	global $thisdb, $dataHeaders;
}
	
function appendValue($row, $field, $nospace=null) {
	global $thisdb, $dataHeaders, $lastClient;
	if($thisdb == 'nannydolittle') {
		if(!$lastClient) return;
		if(!($val = $row[array_search($field, $dataHeaders)])) return;
		$map = array('LAST'=>'lname', 'FIRST'=>'fname', 'HOME'=>'homephone', 'CELL'=>'cellphone', 'WORK'=>'workphone');
		$sepr = $nospace ? '' : ' ';
		if($target = $map[$field]) {
			$val = val($val);
			$vals = array($target=>sqlVal("CONCAT_WS('$sepr', $target, $val)"));
			updateTable('tblclient', $vals, "clientid = $lastClient", 1);
		}
		$map = array('SOURCE'=>'custom1', 'REASON'=>'custom3');
		if($target = $map[$field]) {
			$val = val($val);
			$vals = array('value'=>sqlVal("CONCAT_WS('$sepr', value, $val)"));
			updateTable('relclientcustomfield', $vals, "clientptr = $lastClient AND fieldname = '$target'", 1);
		}
	}
}

function handleAddress($val, &$client) {
	$addr = array_map('trim', explode("\n", $val));
	if(!$addr) return;
	getCityStateZip(array_pop($addr), $client);
	if(count($addr) > 1) $client['street2'] = $addr[1];
	if($addr) $client['street1'] = $addr[0];
}

function handleLnameCommaFname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(',', $str));
	if(count($parts)) {
		$destination[$lnameKey] = $parts[0];
		unset($parts[0]);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
}

function getFnameLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = explode(' ', $str);
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if($parts) $destination[$fnameKey] = join(' ', $parts);
	}
}



function handlePetNames($str) {
	foreach(decompose($str, ' and ', $bag) as $x0)
		foreach(decompose($x0, ',', $bag) as $x1)
			foreach(decompose($x1, '&', $bag) as $x2)
				$petnames[] = $x2;
	return $petnames;
}

function decompose($str, $delim, $bag) {
	return array_map('trim', explode($delim, $str));
}



function getCityStateZip($str, &$destination) {
	$parts = explode(' ', $str);
	if($parts) {
		if($parts[count($array)-1] == 'image') array_pop($parts);
		if($parts && is_numeric(count($array)-1)) $destination['zip'] = array_pop($parts);
		if($parts && strlen(count($array)-1) == 2) $destination['state'] = array_pop($parts);
		if($parts) $destination['city'] = join(' ', $parts);
	}
}
// AD HOCS





