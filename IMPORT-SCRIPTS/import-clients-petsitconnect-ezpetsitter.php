<?// import-clients-petsitconnect-ezpetsitter.php

// https://LEASHTIME.COM/import-clients-petsitconnect-ezpetsitter.php?file=barnyardsandbackyards/client_pets_2334.csv
// Ritareimers@cats90210.com / freedom

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";
require_once "field-utils.php";

extract($_REQUEST);

$file = "/var/data/clientimports/$file";

set_time_limit(300);

echo "<hr>";

$delimiter = strpos($file, '.xls') ? "\t" : ',';
$strm = fopen($file, 'r');
//$line0 = trim(fgetcsv($strm, 0, $delimiter)); 
$row = fgetcsv($strm, 0, $delimiter);
print_r($row);
$dataHeaders = array_map('trim', $row);// consume first line (field labels)

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

$incompleteRow = null;
while($row = getCSVRow($strm, 0, $delimiter)) {
	$n++;
	// HANDLE EMPTY LINES
	if(skipRow($row)) {echo "<p><font color=red>Skipped Line #$n</font><br>";continue;}
	// HANDLE CONTINUATIONS OF INCOMPLETE LINES
	else handleRow($row);
	
}

echo "<hr>";
foreach((array)$exceptions as $msg) echo "$msg<br>";

// CONVERSION FUNCTIONS
// FName	LName	Addr	City	State	Zip	Phone	Phone2	EvePhone	Email	VetName	VetPhone	ContactName	ContactPhone	Contact2Name	Contact2Phone
// PrimarySitter	SecondarySitter	KeyKeptWith	Pet1Name	Pet1Species	Pet1Breed	Pet1Color	Pet1Sex	Pet2Name	Pet2Species	Pet2Breed	Pet2Color	Pet2Sex	Pet3Name	Pet3Species	Pet3Breed	Pet3Color	Pet3Sex	Pet4Name	Pet4Species	Pet4Breed	Pet4Color	Pet4Sex	Pet5Name	Pet5Species	Pet5Breed	Pet5Color	Pet5Sex

function handleRow($row) {
	global $dataHeaders, $skipExisting, $exceptions;
	if($skipExisting) {
		$fname = mysql_real_escape_string(trim($row[array_search('FName', $dataHeaders)]));
		$lname = mysql_real_escape_string(trim($row[array_search('LName', $dataHeaders)]));
		$ex = fetchFirstAssoc("SELECT clientid FROM tblclient WHERE fname = '$fname' AND lname = '$lname' LIMIT 1");
		if($ex) {
				$exceptions[] = "<font color=red>Skipped client $fname $lname, who is already known.</font>";
				echo $exceptions[count($exceptions)-1]."<p>";
			return;
		}
	}
	//return;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'FName') $client['fname'] = $trimVal;
		else if($label == 'LName') $client['lname'] = $trimVal;
		else if($label == 'Addr') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Phone') $client['workphone'] = $trimVal;
		else if($label == 'Phone2') $client['cellphone'] = $trimVal;
		else if($label == 'EvePhone') $client['homephone'] = $trimVal;
		else if($label == 'VetName' && $trimVal) {
			$clinic = findClinicByName($trimVal);
			if(!$clinic) {
				$clinic = array('clinicname'=>$trimVal, 'officephone'=>trim($row[$i+1]));
				$clinic = insertTable('tblclinic', $clinic, 1);
			}
			$client['clinicptr'] = $clinic;
		}
		else if($label == 'ContactName' && $trimVal) 
			$client['emergency'] = array('name'=>$trimVal, 'homephone'=>trim($row[$i+1]));
		else if($label == 'Contact2Name' && $trimVal) 
			$client['neighbor'] = array('name'=>$trimVal, 'homephone'=>trim($row[$i+1]));
		
		else if($label == 'PrimarySitter' && $trimVal) {
			$prov = findProviderByName($trimVal);
			if(!$prov) {
				$prov = array('active'=>1);
				handleFnameSpaceLname($trimVal, $prov);
				$prov = insertTable('tblprovider', $prov, 1);
			}
			$client['defaultproviderptr'] = $prov;
		}
		else if($label == 'SecondarySitter' && $trimVal) $client['notes'] = "Secondary Sitter: $trimVal";
		else if($label == 'KeyKeptWith' && $trimVal) $key = $trimVal;
		else if(strpos($label, 'Pet') === 0 && strpos($label, 'Name') && $trimVal) {
			// Pet1Name	Pet1Species	Pet1Breed	Pet1Color	Pet1Sex
			$pets[] = array('name'=>$trimVal, 'type'=>trim($row[$i+1]), 'breed'=>trim($row[$i+2]),
										'color'=>trim($row[$i+3]), 'sex'=>trim($row[$i+4]));
		}
	}
	
	
	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	

	if(!$client['fname'] || !$client['lname']) {
		$exceptions[] = "<font color=red>Bad row (fname, lname): $rowCount</font>";
		echo $exceptions[count($exceptions)]."<p>";
		return;
	}
	else if(!$client['fname']) !$client['fname'] = '-unknown-';
	else if(!$client['lname']) !$client['lname'] = '-unknown-';
	
	$clientptr = saveNewClient($client);

	if($client['emergency']) saveClientContact('emergency', $clientptr, $client['emergency']);
	if($client['neighbor']) saveClientContact('neighbor', $clientptr, $client['neighbor']);
	if($key) {
		$poss = findProviderByName($key);
		$key = array('clientptr'=>$clientptr, 'possessor1'=>$poss);
		insertTable('tblkey', $key, 1);
	}
	
	foreach((array)$pets as $pet) {
		$petNames[] = $pet['name'];
		$pet['ownerptr'] = $clientptr;
		insertTable('tblpet', $pet, 1);
	}
	
	echo "Added client {$client['fname']} {$client['lname']} associated with pets [".join(' - ', (array)$petNames)."]";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";
}

function findVetByName($nm) {
	return fetchRow0Col0("SELECT vetid FROM tblvet WHERE CONCAT_WS(' ', fname, lname)  = '".mysql_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findClinicByName($nm) {
	return fetchRow0Col0("SELECT clinicid FROM tblclinic WHERE clinicname = '".mysql_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findProviderByName($nm) {
	return fetchRow0Col0("SELECT providerid FROM tblprovider WHERE CONCAT_WS(' ', fname, lname) = '".mysql_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(' ', $str));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
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



function skipRow($row) {
	global $thisdb, $dataHeaders;
}
	
