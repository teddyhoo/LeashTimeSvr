<?// import-clients-csv-petrax.php

// https://LEASHTIME.COM/import-clients-csv-petrax.php?file=tlcpetsitter/TLC_client_list_9.16.11- recordOnly.csv&outlook=0
// nodups
// newcustomfields

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";

extract($_REQUEST);

$file = "/var/data/clientimports/$file";



echo "<hr>";

$delimiter = strpos($file, '.xls') ? "\t" : ',';
$strm = fopen($file, 'r');
if($outlook) {
	//$line0 = trim(fgetcsv($strm, 0, $delimiter)); 
	$row = fgetcsv($strm, 0, $delimiter);
	print_r($row);
	$dataHeaders = array_map('trim', $row);// consume first line (field labels)
	$flippedHeaders = array_flip($dataHeaders);
}
else {
	// eat first four rows
	for($i=0;$i<4;$i++)
		echo join(' ', fgetcsv($strm, 0, $delimiter)).'<p>';
}
function getCSVRow($strm) {
	return $_POST['mutiline'] ? mygetcsv($strm) : fgetcsv($strm);
}

function mygetcsv($strm) {  // handles EOLS inside quotes, as long as quotes balance
	global $delimiter;
	$quoteCount = 0;
	$totalCSV = array();
	do {
		$line = fgets($strm);
		$oldQuoteCount = $quoteCount;
		for($i=0; $i < strlen($line); $i++) if($line[$i] == '"') $quoteCount++;
		if($totalCSV) { // last line was incomplete
			$nextDelim = strpos($line, $delimiter);
			// if delims on this line
			if($nextDelim !== false) {
				$nextQuote = strpos($line, '"');
				// if quotes close on this
				if($nextQuote !== false) {
					// tack the line up to the quote onto the last cell
					$totalCSV[count($totalCSV)-1] .= "\n".substr($line, 0,$nextQuote);
					// consume the line up to the position after the quote and (NOT) the following delim, if any
					$line = substr($line, min($nextQuote+1, strlen($line)-1));
				}
				else  {
					// consume the whole line
					$totalCSV[count($totalCSV)-1] .= "\n".$line;
					$line = '';
				}
			}
		}
		if($line) {
			$sstrm = fopen("data://text/plain,$line" , 'r');
			$csv = fgetcsv($sstrm, 0, $delimiter);
			if($csv) $csv = array_map('trim', $csv);
			if(!$totalCSV) $totalCSV = $csv;
			else {
				$totalCSV[count($totalCSV)-1] .= "\n".substr($csv[0], 0, strlen($csv[0]));
				for($i=1; $i < count($csv); $i++) $totalCSV[] = $csv[$i];
			}
		}
	}
	while($quoteCount % 2 == 1);
	return $totalCSV;
}


function ORIGmygetcsv($strm) {  // handles EOLS inside quotes, as long as quotes balance
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
if($outlook) echo "dataHeaders: ".count($dataHeaders).':<br>'.join(',', $dataHeaders).'<hr>';
//echo "map: ".print_r($map,1);exit;

//print_r($strm);
$n = 1;

//while($line = trim(fgets($strm))) echo "$line<br>";

$thisdb = $db;

$clientMap = array();  // mapID => clientid

$_SESSION['preferences'] = fetchPreferences();

$customFields = getCustomFields('activeOnly');
$sitters = fetchCol0("SELECT providerid FROM tblprovider WHERE active=1");
if(count($sitters)==1) $soleSitter = $sitters[0];

//print_r($_SESSION['preferences']);exit;

$incompleteRow = null;
while($row = getCSVRow($strm, 0, $delimiter)) {
	$n++;
	// HANDLE EMPTY LINES
	if(skipRow($row)) {
		echo "<p><font color=red>Skipped Line #$n</font><br>";
		$row = null;
	}
	// HANDLE CONTINUATIONS OF INCOMPLETE LINES
	else {
		$rowsHandled++;
		if(!$outlook && handlePetraxClientSection($row)) $clientsAdded++;
		else if($outlook && handleRowPetraxOutlook($row)) $clientsAdded++;
		//else if(!$outlook && handlePetraxClientListRecordOnlyRow($row)) $clientsAdded++;
	}
	$lastRow = $row;
	//echo "ROW: ".print_r($row,1)."<P>";
}

echo "<hr>";
echo "rowsHandled: $rowsHandled<p>clientsAdded: $clientsAdded<p>";

// CONVERSION FUNCTIONS

function skipRow($row) {
	if(!$row || (count($row) == 1 && !$row[0])) {
		return true;
	}
}

function handlePetraxClientSection($row) {
	/*
"Berns, Lisa & Jonathan",,,,,,,,,,,,,,,,
"Client ID:","300",,,,,,,,"Home:",,,"(314) 994-9792",,,"Pager:",
"Address:","22 Deer Creek Woods Drive",,,,,,,,"Business:",,,"(314) 880-3576",,,"Other:","(314) 621-8363"
,"Ladue, MO 63124",,,,,,,,"Mobile:",,,"(314) 973-5116",,,,
,,,,,,,,,,,,,,,,
"Veterinarian",,,,,,,,,,,,,,,,
"Clinic:","Clark Animal Hospital",,,,,,,,,,,,,,"Phone:","(314) 966-2733"
"Contact:",,,,,,,,,,,,,,,"City:","Kirkwood"
,,,,,,,,,,,,,,,,
"Pet Name",,,,"Type",,,"Breed",,,,,,,,,
"Smoky",,,,"Cat",,,"DSH",,,,,,,,,
"Spark",,,,"Cat",,,"DSH",,,,,,,,,
,,,,,,,,,,,,,,,,
	*/
	if(!$row[0]) return;
	global $dataHeaders, $delimiter, $strm, $soleSitter;
	$client = array('active'=>1, 'mailtohome'=>1);
	// Row 0
	handleLnameCommaFname(trim($row[0]), $client);
	if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	if(!$client['lname']) $client['lname'] = 'UNKNOWN';
	if($soleSitter) $client['defaultproviderptr'] = $soleSitter;
	// Row 1
	$row = getCSVRow($strm, 0, $delimiter);
	$dataHeaders = explodePairsLine('clientid|1||homephone|12||pager|15');
	foreach($dataHeaders as $field=> $i)
		$client[$field] = trim($row[$i]);
	// Row 2
	$row = getCSVRow($strm, 0, $delimiter);
	$dataHeaders = explodePairsLine('street1|1||workphone|12||cellphone2|15');
	foreach($dataHeaders as $field=> $i)
		$client[$field] = trim($row[$i]);
	// Row 3
	$row = getCSVRow($strm, 0, $delimiter);
	$dataHeaders = explodePairsLine('citystatezip|1||cellphone|12');
	foreach($dataHeaders as $field=> $i) {
		if($field == 'citystatezip') getCityStateZip($row[$i], $client);
		else $client[$field] = trim($row[$i]);
	}
	// Row 4 BLANK
	$row = getCSVRow($strm, 0, $delimiter);
	// Row 5 Veterinarian
	$row = getCSVRow($strm, 0, $delimiter);
	// Row 6 Clinic info
	$clinic = null;
	$row = getCSVRow($strm, 0, $delimiter);
	$dataHeaders = explodePairsLine('clinicname|1||officephone|16');
	if(rowAtHeader($row, 'clinicname')) 
		$clinic = array('clinicname'=>rowAtHeader($row, 'clinicname'), 'officephone'=>rowAtHeader($row, 'officephone'));
//echo "CLINIC: ".print_r(findClinicByName($clinic['clinicname']),1).'<hr>';exit;
	// Row 7
	$dataHeaders = explodePairsLine('contact|1||city|15');
	$row = getCSVRow($strm, 0, $delimiter);
	if($clinic) {
		handleFnameSpaceLname(rowAtHeader($row, 'contact'), $clinic);
		$clinic['city'] = rowAtHeader($row, 'city');
	}
	// Row 8 BLANK
	$row = getCSVRow($strm, 0, $delimiter);
	// Row 9 Pet Name...
	$row = getCSVRow($strm, 0, $delimiter);
	// Pets
	$row = getCSVRow($strm, 0, $delimiter);
	$dataHeaders = explodePairsLine('name|0||type|4||breed|7');
	while($row[0]) {
		//"Smoky",,,,"Cat",,,"DSH",,,,,,,,,
		$pets[] = array('name'=>rowAtHeader($row, 'name'), 'type'=>rowAtHeader($row, 'type'), 'breed'=>rowAtHeader($row, 'breed'));
		$row = getCSVRow($strm, 0, $delimiter);
	}
	if($clinic) {
		if(!($clinicid = findClinicByName($clinic['clinicname'])))
			$clinicid = insertTable('tblclinic', $clinic, 1);
		$client['clinicptr'] = $clinicid;
	}
	$ownerptr = saveNewClient($client);
	echo "Added client [$ownerptr] {$client['fname']} {$client['lname']} [Clinic: $clinicid].<p>";
	
	foreach((array)$pets as $pet) { // process pets
		$pet['ownerptr'] = $ownerptr;
		insertTable('tblpet', $pet, 1);
		echo "...Added {$pet['type']} {$pet['name']}.<p>";
	}
}


function rowAtHeader($row, $header) {
	global $dataHeaders;
	return trim($row[$dataHeaders[$header]]);
}


function handlePetraxClientListRecordOnlyRow($row) {
	// first row in file says: "{boz name},Client List"
	// client rows:
	//"Aguirre, Roman",Client ID:,699,Home:,(480) 248-7511,Pager:,,Address:,1122 E Lynx Way,Business:,,Other:,,"Chandler, AZ 85249",Mobile:,(714) 227-4135,,Veterinarian,Clinic:,Ocotillo Animal Clinic & Pet Resort,Phone:,(480) 899-7443,Contact:,,City:,Chandler,Pet Name,Type,Breed
	//pet rows:
	//Bonnie,Dog,Pekingese,,,,,,,,,,,,,,,,,,,,,,,,,,
	//Maxie,Dog,Chihuahua,,,,,,,,,,,,,,,,,,,,,,,,,,
	//Sachi,Dog,Shih-Tzu,,,,,,,,,,,,,,,,,,,,,,,,,,
	global $lastRow, $ownerptr, $ignoreClient;
	static $dataHeaders;
	if(!$dataHeaders) {
		$dataHeaders = explode(',', 'fullname,0,clientid,0,homephone,0,pager,0,street1,0,workphone,0,otherphone,citystatezipORstreet2,0,cellphone'
																.'citystatezipOrEmpty,0vetlabel,0cliniclabel,clinicname,0,clinicphone,0,vetcontact,0,vetcity');
	}
	if(!$lastRow) { // process client
		$ignoreClient = false;
		$client = array('active'=>1);
		foreach($dataHeaders as $i => $label) {
			$trimVal = trim($row[$i]);
			if(!$trimVal) continue;
			if($label == 'fullname') handleLnameCommaFname($trimVal, $client);
			else if($label == 'clientid') {$client['notes'] = "Petrax ID: $trimVal"; $petraxid = $trimVal;}
			else if($label == 'homephone') $client['homephone'] = $trimVal;
			else if($label == 'pager') $client['pager'] = $trimVal;
			else if($label == 'street1') $client['street1'] = $trimVal;
			else if($label == 'workphone') $client['workphone'] = $trimVal;
			else if($label == 'otherphone') $client['cellphone2'] = $trimVal;
			else if($label == 'citystatezipORstreet2') handleAddress($trimVal, $client);
			else if($label == 'cellphone') $client['cellphone'] = $trimVal;
			else if($label == 'citystatezipOrEmpty') {
				if(!$client['state']) {
					$client['street2'] = $client['city'];  // citystatezipORstreet2 was actually street2
					handleAddress($trimVal, $client);
				}
			}
			else if($label == 'clinicname') $client['clinic']['clinicname'] = $trimVal;
			else if($label == 'clinicphone') $client['clinic']['officephone'] = $trimVal;
			else if($label == 'vetcontact') $client['clinic']['notes'] = "Contact: $trimVal";
			else if($label == 'vetcity') $client['clinic']['city'] = $trimVal;
		}
		if($client['clinic']) {
			if(!($clinicid = findClinicByName($client['clinic']['clinicname'])))
				$clinicid = insertTable('tblclinic', $client['clinic'], 1);
			$client['clinicptr'] = $clinicid;
			unset($client['clinic']);
		}
		$exists = fetchRow0Col0("SELECT notes FROM tblclient WHERE fname = '".mysqli_real_escape_string($client['fname'])."' 
			AND lname = '".mysqli_real_escape_string($client['lname'])."'");
		if($exists && $exists == "Petrax ID: $petraxid") {
			echo "Client [$ownerptr] {$client['fname']} {$client['lname']} already exists.<p>";
		}
		else {
			if(!$client['fname']) $client['fname'] = '{no first name}';
			if(!$client['lname']) $client['lname'] = '{no last name}';
			$ownerptr = saveNewClient($client);
			echo "Added client [$ownerptr] {$client['fname']} {$client['lname']}.<p>";
		}
	}
	else { // process pet
		insertTable('tblpet', array('name'=>$row[0],'type'=>$row[1], 'breed'=>$row[2], 'ownerptr'=>$ownerptr), 1);
		echo "...Added {$row[1]} {$row[0]}.<p>";
	}
}
	
// TRUNCATE testtest.relclientcustomfield;TRUNCATE testtest.tblclient;TRUNCATE testtest.tblpet;
function handleRowPetraxLimited($row) {  // NEW VERSION (from TLC)
	//Client ID	Name	Subdivision	Zip	Home Phone	Mobile	Mobile 2	Email	Balance	Status

	global $thisdb, $dataHeaders, $flippedHeaders, $lastClient;
	global $dataHeaders, $customFields;
	if((!$customFields && !($customFields = getCustomFields(true))) || $_REQUEST['newcustomfields']) {
		// **** deleteTable('tblpreference', "property LIKE 'custom%'", 1);
		if(in_array('Neighborhood', $dataHeaders)) {
			$customFields = array('Neighborhood','Referred By','Other Phone');
			foreach($customFields as $i => $label) {
				//label|active|onelineORtextORboolean|visitsheet|clientvisible
				setPreference("custom".($i+1+20 /** */), "$label|1|oneline|1|0");
			}
		}
		else if(in_array('Subdivision', $dataHeaders)) {
			$customFields = array('Subdivision');
			foreach($customFields as $i => $label) {
				//label|active|onelineORtextORboolean|visitsheet|clientvisible
				setPreference("custom".($i+1+20 /** */), "$label|1|oneline|1|0");
			}
		}
	}
	if($_REQUEST['nodups']) {
		$fullname = mysqli_real_escape_string(trim($row[$flippedHeaders['Name']]));
		if($cid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE CONCAT_WS(', ', lname, fname) = '$fullname' LIMIT 1")) {
			echo "<font color=red>Duplicate: [$fname $lname] ($cid) ignored.</font><p>";
			return;
		}
	}
	
	$client = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Title') ;
		else if($label == 'Name') handleLnameCommaFname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		else if($label == 'Client ID') $client['officenotes'] = "Client ID: $trimVal";
		else if($label == 'Subdivision') $client['custom']['custom1'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Mobile') $client['cellphone'] = $trimVal;
		else if($label == 'Mobile2') $client['cellphone2'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Status') $client['active'] = $trimVal == 'Active';
	} //trainingmode
	$ownerptr = saveNewClient($client);
	if($client['custom']) 
		saveClientCustomFields($ownerptr, $client['custom'], $pairsOnly=true);
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(' - ', (array)$petNames)."]<p>";
	return true;
}

function handleRowPetraxOutlook($row) {
	global $thisdb, $dataHeaders, $flippedHeaders, $lastClient;
	global $dataHeaders, $customFields;
	if(!$customFields && !($customFields = getCustomFields(true)) || $_REQUEST['newcustomfields']) {
		// **** deleteTable('tblpreference', "property LIKE 'custom%'", 1);
		$customFields = array('Neighborhood','Referred By','Other Phone');
		foreach($customFields as $i => $label) {
			//label|active|onelineORtextORboolean|visitsheet|clientvisible
			setPreference("custom".($i+1+20 /** */), "$label|1|oneline|1|0");
		}
	}
	if($_REQUEST['nodups']) {
		$fname = mysqli_real_escape_string(trim($row[$flippedHeaders['First Name']]));
		$lname = mysqli_real_escape_string(trim($row[$flippedHeaders['Last Name']]));
		if($cid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE fname = '$fname' AND lname = '$lname' LIMIT 1")) {
			echo "<font color=red>Duplicate: [$fname $lname] ($cid) ignored.</font><p>";
			return;
		}
	}
	
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Title') ;
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Home Street') $client['street1'] = $trimVal;
		else if($label == 'Home Street 2') $client['street2'] = $trimVal;
		else if($label == 'Home Street 3') $client['custom']['custom1'] = $trimVal; // ** custom1
		else if($label == 'Home City') $client['city'] = $trimVal;
		else if($label == 'Home State') $client['state'] = $trimVal;
		else if($label == 'Home Postal Code') $client['zip'] = $trimVal;
		else if($label == "Assistant's Phone") $client['cellphone2'] = $trimVal;
		else if($label == 'Car Phone') $client['cellphone2'] = $trimVal;
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Business Phone') $client['workphone'] = $trimVal;
		else if($label == 'Mobile Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Other Phone') $client['custom']['custom3'] = $trimVal; // ** custom3
		else if($label == 'Pager') $client['pager'] = $trimVal;
		else if($label == 'Children') $petNames = handlePetNames($row[$i]);
		//else if($label == 'E-mail Address') $client['email'] = $trimVal;
		//else if($label == 'E-mail 2 Address') $client['email2'] = $trimVal;
		else if($label == 'E-mail Address') $badEmails = processEmails($trimVal, $client);
		else if($label == 'E-mail Address') $badEmails = processEmails($trimVal, $client);
		
		else if($label == 'Notes') parseNotes($trimVal, $client);
		else if($label == 'Referred By') $client['custom']['custom2'] = $trimVal; // ** custom2
	} //trainingmode
	if($badEmails) $client['notes'][] .= "Invalid emails disallowed: ".join(', ', $badEmails);	
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	foreach((array)$petNames as $name) insertTable('tblpet', array('name'=>$name, 'ownerptr'=>$ownerptr), 1);
	if($client['emergency']) saveClientContact('emergency', $ownerptr, $client['emergency']);
	if($client['neighbor']) saveClientContact('neighbor', $ownerptr, $client['neighbor']);
	if($client['custom']) 
		saveClientCustomFields($ownerptr, $client['custom'], $pairsOnly=true);
	if($badEmails) $noteBadEmail = " BAD EMAILS";
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(' - ', (array)$petNames)."]$noteBadEmail<p>";
	return true;
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


function parseNotes($val, &$client) {
	if(!$val) return null;
	// format: work order notes -blankline - emergency contact - blankline - trusted neighbor
	$sstrm = fopen("data://text/plain,$val" , 'r');
	while(($line = fgets($sstrm)) !== false) {
		$line = trim($line);
		if($line == 'EMERGENCY CONTACT:') {
			$contact = 'emergency';
			$client[$contact] = array();
		}
		else if($line == 'TRUSTED NEIGHBOR:') {
			$contact = 'neighbor';
			$client[$contact] = array();
		}
		else if($fieldVal = noteField('Name: ', $line)) $client[$contact]['name'] = $fieldVal;
		else if($fieldVal = noteField('Location: ', $line)) $client[$contact]['location'] = $fieldVal;
		else if($fieldVal = noteField('Number: ', $line)) $client[$contact]['homephone'] = $fieldVal;
		else if($fieldVal = noteField('Number 2: ', $line)) $client[$contact]['cellphone'] = $fieldVal;
		else if($fieldVal = noteField('Number 3: ', $line)) $client[$contact]['workphone'] = $fieldVal;
		else $notes .= ($notes ? "\n" : '').$line;
//echo $n.' '.htmlentities($line).'<br>';
//$n++;
	}
	if($notes) $client['notes'][] = $notes;
}
	
function noteField($field, $line) {
	if(strpos($line, $field) === 0) return substr($line, strlen($field));
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

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(' ', $str));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
}



function handlePetNames($str) {
	foreach(decompose($str, '+') as $x0)
		foreach(decompose($x0, ' and ') as $x1)
			foreach(decompose($x1, ',') as $x2)
				foreach(decompose($x2, '&') as $x3)
					$petnames[] = $x3;
	return $petnames;
}

function decompose($str, $delim) {
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
// AD HOCS ==============================================================

function handleCrestviewPets($row) {
	global $dataHeaders, $customFields, $multilines;
	if(!$customFields && !($customFields = getCustomFields(true))) {
		$customFields = array();
		foreach($customFields as $i => $label) {
			//label|active|onelineORtextORboolean|visitsheet|clientvisible
			setPreference("custom".($i+1), "$label|1|oneline|1|0");
		}
	}
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Client Number') {
			if($trimVal) {
				$multilines[$clientnum = $trimVal] += 1;
				$clientpart = $multilines[$clientnum];
			}
			else $clientpart = 1;
		}
		if(!$trimVal) continue;
		if($label == 'Nickname') ;
		else if($label == 'Email Address') {
			if($clientpart == 1) $client['email'] = $trimVal;
			else if($clientpart == 2) $client['email2'] = $trimVal;
			else $client['notes'][] = 'email: '.$trimVal;
		}
		else if($label == 'First Name' && !$client['fname']) $client['fname'] = $trimVal;
		else if($label == 'Middle Name' && !$client['middlename']) {
			$client['middlename'] = $trimVal;
			$client['fname'] .= " $trimVal";
		}
		else if($label == 'Last Name' && !$client['lname']) $client['lname'] = $trimVal;
		else if($label == 'Home Phone') {
			if($clientpart == 1) $client['homephone'] = $trimVal;
			else $client['notes'][] = 'home: '.$trimVal;
		}
		else if($label == 'Business Phone') {
			if($clientpart == 1) $client['workphone'] = $trimVal;
			else $client['notes'][] = 'work: '.$trimVal;
		}
		else if($label == 'Mobile Phone') {
			if($clientpart == 1) $client['cellphone'] = $trimVal;
			else $client['notes'][] = 'work: '.$trimVal;
		}
		else if($label == 'Business Fax') $client['fax'] = $trimVal;
		else if($label == 'Home Address') $client['street1'] = $trimVal;
	}
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}.<p>";
}
		
		
		

function handleQueeniesPets($row) {
}

function findVetByName($nm) {
	return fetchRow0Col0("SELECT vetid FROM tblvet WHERE CONCAT_WS(' ', fname, lname)  = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findClinicByName($nm) {
	return fetchRow0Col0("SELECT clinicid FROM tblclinic WHERE clinicname = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findProviderByNickname($nn) {
	return fetchRow0Col0("SELECT providerid FROM tblprovider WHERE nickname = '".mysqli_real_escape_string($nn ? $nn : '')."' LIMIT 1");
}

function handleRowCahills($row) {
	global $dataHeaders;
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		if(!$row[$i]) continue;
		if($label == '') ;
		else if($label == 'Client') handleLnameCommaFname($row[$i], $client);
		else if($label == 'Company Name') $petNames = handlePetNames($row[$i]);
		else if($label == 'Email') $client['email'] = $row[$i];
		else if($label == 'Phone Numbers') {
			foreach(array_map('trim', explode("\n", $row[$i])) as  $num) {
				if(strpos($num, 'Phone:') !== FALSE) $client['homephone'] = trim(substr($num, strlen('Phone:')));
				else if(strpos($num, 'Fax:') !== FALSE) $client['fax'] = trim(substr($num, strlen('Fax:')));
				else if(strpos($num, 'Mobile:') !== FALSE) $client['cellphone'] = trim(substr($num, strlen('Mobile:')));
			}
		}
		else if($label == 'Billing Address') handleAddress($row[$i], $client);
		else if($label == 'Note') $client['notes'] = $row[$i];
	}
	$ownerptr = saveNewClient($client);
	foreach($petNames as $name) insertTable('tblpet', array('name'=>$name, 'ownerptr'=>$ownerptr), 1);
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(' - ', $petNames)."]<p>";
}

function handleRowNannydolittle($row) {
	global $thisdb, $dataHeaders, $lastClient;
	// #	LAST 	FIRST	PET	TYPE	B-DAY	ADDRESS	CITY	ZIP	HOME 	CELL 	WORK 	SOURCE
	if(strpos($row[0], '-') === FALSE) { //non-primary row
		if($petname = $row[array_search('PET', $dataHeaders)]) {
			$pet = array('name'=>$row[array_search('PET', $dataHeaders)], 'type'=>$row[array_search('TYPE', $dataHeaders)]);
			if($dob = $row[array_search('B-DAY', $dataHeaders)]) 
				$pet['dob'] = date('Y-m-d', strtotime($dob));
			$pet['ownerptr'] = $lastClient;
			$petId = insertTable('tblpet', $pet, 1);
			echo "<br>... WITH PET: {$pet['name']}";
		}
		appendValue($row, 'LAST');
		appendValue($row, 'FIRST');
		appendValue($row, 'HOME');
		appendValue($row, 'CELL');
		appendValue($row, 'WORK');
		appendValue($row, 'SOURCE');
		appendValue($row, 'REASON');
		return;
	}
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		if(!$row[$i]) continue;
		if($label == '#') $custom2 = $row[$i];
		if($label == 'LAST') $client['lname'] = $row[$i];
		if($label == 'FIRST') $client['fname'] = $row[$i];
		if($label == 'PET' && $row[$i]) 
			$pet = array('name'=>$row[$i], 'type'=>$row[array_search('TYPE', $dataHeaders)]);
		if($label == 'B-DAY' && $pet) $pet['dob'] = date('Y-m-d', strtotime($row[$i]));
		if($label == 'ADDRESS') $client['street1'] = $row[$i];
		if($label == 'CITY') $client['city'] = $row[$i];
		$client['state'] = 'WA';
		if($label == 'ZIP') $client['zip'] = $row[$i];
		if($label == 'HOME') $client['homephone'] = $row[$i];
		if($label == 'CELL') $client['cellphone'] = $row[$i];
		if($label == 'WORK') $client['workphone'] = $row[$i];
		if($label == 'SOURCE') $source = $row[$i];
		if($label == 'REASON') $reason = $row[$i];
	}
	saveNewClient($client);
	echo "<p>CREATED CLIENT: {$client['fname']} {$client['lname']}";
	$newClientId = mysqli_insert_id();
	$lastClient = $newClientId;
	if($source) {
		replaceTable("relclientcustomfield", 
			array('clientptr'=>$newClientId, 'fieldname'=>'custom1', 'value'=>$source), 1);
		echo "<p>... SOURCE: $source";
	}
	if($reason) {
		replaceTable("relclientcustomfield", array('clientptr'=>$newClientId, 'fieldname'=>'custom3', 'value'=>$reason), 1);
		echo "<p>... REASON: $reason";
	}
	replaceTable("relclientcustomfield", array('clientptr'=>$newClientId, 'fieldname'=>'custom2', 'value'=>$row[0]), 1);
	if($pet) {
		$pet['ownerptr'] = $newClientId;
		$petId = insertTable('tblpet', $pet, 1);
		echo "<br>... WITH PET: {$pet['name']}";
	}
}


