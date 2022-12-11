<?// import-bettawalka-keys.php

// https://LEASHTIME.COM/import-bettawalka-keys.php?file=agvpetsitting/KeyInfo.csv


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
//require_once "client-fns.php";
//require_once "key-fns.php";
//require_once "contact-fns.php";
//require_once "custom-field-fns.php";

extract($_REQUEST);

$file = "/var/data/clientimports/$file";



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
// TRUNCATE testtest.relclientcustomfield;TRUNCATE testtest.tblclient;TRUNCATE testtest.tblpet;
function handleRow($row) {
	global $thisdb, $dataHeaders, $lastClient;
	handleBettaWalkaKey($row);
}
	
	
function handleBettaWalkaKey($row) {
// Account	Client	Key Code

	global $dataHeaders;
	
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Account') {
			$name = mysqli_real_escape_string($trimVal);
			$client = fetchFirstAssoc(
				"SELECT * FROM tblclient 
					WHERE CONCAT_WS(' ', fname, lname) = '$name'");
			if(!$client) {
				echo "<font color=red>Client [$trimVal] not found.</font><br>";
				return;
			}
		}
		else if($label == 'Key Code') $descr = $trimVal;
	}
	if($client) {
		insertTable('tblkey', array('description'=>$descr, 'clientptr'=>$client['clientid']), 1);
		//updateTable('tblclient', array('garagegatecode'=>$descr), "clientid = {$client['clientid']}");
		echo "Added client {$client['fname']} {$client['lname']}'s key: $descr<p>";
	}
}

function getCSVRow($strm) {
	return $_POST['multiline'] ? mygetcsv($strm) : fgetcsv($strm);
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
	if($thisdb == 'nannydolittle') {
		if($row[0] == '#') return true;
	}
}
	

