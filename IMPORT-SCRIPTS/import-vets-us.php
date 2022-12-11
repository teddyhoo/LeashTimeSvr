<?// import-vets-us.php

// data from Data-Lists.com purchased 5 Nov 2011 (see receipt at bottom)
// nodups
// newcustomfields

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "custom-field-fns.php";

extract($_REQUEST);
set_time_limit(500);
//$file = "/var/data/clientimports/$file";

echo "HAMSTRUNG";exit;

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
	if(skipRow($row)) {
		echo "<p><font color=red>Skipped Line #$n</font><br>";
		$row = null;
	}
	// HANDLE CONTINUATIONS OF INCOMPLETE LINES
	else {
		$rowsHandled++;
		if(handleVetRow($row)) $vetsadded++;
	}
	$lastRow = $row;
	//echo "ROW: ".print_r($row,1)."<P>";
}

echo "<hr>";
echo "rowsHandled: $rowsHandled<p>vetsadded: $vetsadded<p>";

// CONVERSION FUNCTIONS

function skipRow($row) {
	if(!$row || (count($row) == 1 && !$row[0])) {
		return true;
	}
}

function handleVetRow ($row) {
// "id","biz_name","biz_info","cat_primary","cat_sub","biz_phone","biz_phone_ext",
//"e_address","e_city","e_state","e_postal","e_zip_full","e_country","loc_county",
// "loc_area_code","loc_FIPS","loc_MSA","loc_PMSA","loc_TZ","loc_DST",
//"loc_LAT_centroid","loc_LAT_poly","loc_LONG_centroid","loc_LONG_poly",
//"web_url","web_meta_title","web_meta_desc","web_meta_keys"
	global $dataHeaders;
	$clinic = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'id') $clinic['clinicid'] = $trimVal;
		else if($label == 'biz_name') handleBizName($trimVal, $clinic);
		else if($label == 'cat_primary') $clinic['category'] = $trimVal;
		else if($label == 'cat_sub') $clinic['subcategory'] = $trimVal;
		else if($label == 'biz_phone') $clinic['officephone'] = $trimVal;
		else if($label == 'e_address') $clinic['street1'] = $trimVal;
		else if($label == 'e_city') $clinic['city'] = $trimVal;
		else if($label == 'e_state') $clinic['state'] = $trimVal;
		else if($label == 'e_postal') $clinic['zip'] = $trimVal;
		else if($label == 'e_zip_full') $clinic['fullzip'] = $trimVal;
		else if($label == 'loc_LAT_poly') $clinic['lat'] = $trimVal;
		else if($label == 'loc_LONG_poly') $clinic['lon'] = $trimVal;
		else if($label == 'web_url') $clinic['url'] = $trimVal;
		else if($label == 'web_meta_title') $clinic['webmetatitle'] = $trimVal;
		else if($label == 'web_meta_desc') $clinic['webmetadescription'] = $trimVal;
		else if($label == 'web_meta_keys') $clinic['webmetakeys'] = $trimVal;

	}
	$exists = fetchCol0(
		"SELECT clinicid FROM vetclinic_us 
			WHERE street1 = '".mysqli_real_escape_string($clinic['street1'])."' 
			AND zip = '".mysqli_real_escape_string($client['zip'])."'");
	$clinicid = insertTable('vetclinic_us', $clinic, 1);
	echo "Added clinic [$clinicid] {$clinic['clinicname']} ({$clinic['fname']} {$clinic['lname']} {$clinic['creds']}).<p>";
	if($exists) {
		echo "- Dups? {$clinic['clinicname']}: $clinicid".join(', ', $exists)."<p>";
	}
}
	
function handleBizName($val, &$clinic) {
	if($break = strpos($val, " DVM - ")) {
		$clinic['creds'] = 'DVM';
		$name = substr($val, 0, $break);
		if(strpos($name, ",")) handleLnameCommaFname($name, $clinic);
		else handleFnameSpaceLname($name, $clinic);
		$clinic['clinicname'] = substr($val, $break+strlen(" DVM - "));
	}
	else if($break = strpos($val, " - ")) {
		$name = substr($val, 0, $break);
		if(strpos($name, ",")) handleLnameCommaFname($name, $clinic);
		else handleFnameSpaceLname($name, $clinic);
		$clinic['clinicname'] = substr($val, $break+strlen(" - "));
	}
	else if($break = strpos($val, " DVM")) {
		$clinic['creds'] = ' DVM';
		$name = substr($val, 0, $break);
		if(strpos($name, ",")) handleLnameCommaFname($name, $clinic);
		else handleFnameSpaceLname($name, $clinic);
		$clinic['clinicname'] = $val;
	}
	else $clinic['clinicname'] = $val;
}

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(' ', $str));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
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



/*
2Checkout.com Order Number : 4567030467

Distributed By
 Data-Lists.com ( www.data-lists.com )

Contents of your purchase :
Product ID : 3
 Vendor Product ID : usvd
 Product Name : U.S. Veterinarian Database - 29,472 Unique Records
 Quantity : 1
 Handling Fee : 0.00

Total : 39.00 ( USD )

Billing Information
 Edward  Hooban
 IP: 68.225.89.173     IP Location: Fairfax ( United States )
 ted@leashtime.com
 703-996-3084 
 22085 Chelsy Paige Sq
 Ashburn, VA 20148
 United States ( USA )




2Checkout.com Inc. and Data-Lists.com thank you for your business.

Customer Service:
Main Number: 1.614.921.2450
Toll-free in U.S. & Canada: 1.877.294.0273
United Kingdom: 0.871.284.4844

2Checkout.com Inc.
1785 O'Brien Rd
Columbus, Ohio 43228, USA
Contact Us: http://www.2checkout.com/community/help



*/