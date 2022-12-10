<? // import-clinics-precise.php
// file = an HTML file from https://printersrowdogwalk.precisessl.com/clients/vets
// example: X:\clientimports\0 Precise Petcare\Vets [Edit View]   Printers Row Dog Walk Admin.htm

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$file = "/var/data/clientimports/{$_GET['file']}";

$strm = fopen($file, 'r');

$tokens = array(
	'title="Select ', // start of clinic
	'value="', // city
	'value="', // name
	'<textarea name="v_address"',
	'value="', // phone
	'<textarea name="v_notes"');
$fields = array('', 'city', 'clinicname', 'fulladdress', 'officephone', 'notes');
	
$stage = 0;
while(!feof($strm) && $line = fgets($strm)) {
	//echo "$stage<br>";
	if($stage == count($tokens)) {
		if($clinic) $clinics[] = $clinic;
		$stage = 0;
	}
	if($stage == 0) {
		if(strpos($line, $tokens[$stage])) {
//echo "$stage: ".htmlentities($line)."<br>";	
			$clinic = array();
			$stage += 1;
		}
		else {
//echo "$stage: {$tokens[$stage]}<br>";	
			$line = '';
			continue;
		}
	}
	else {
		if($oldLine) {
			//$start = strlen($oldLine);
			$oldLine .= $line; 
//echo "OLDLINE+ ".htmlentities($oldLine)."<hr>";			
			$end = strpos($oldLine, '<', $start);
			if($end) {
				$clinic[$fields[$stage]] = substr($oldLine, $start, $end - $start);
//echo "$stage START $start END $end<hr>".htmlentities($oldLine,1)."<hr>";			
				$oldLine = ''; 
				$stage += 1;
			}
		}
		else if(($found = strpos($line, $tokens[$stage])) !== FALSE) {
			if($tokens[$stage] == 'value="') {
//echo "$stage: {$tokens[$stage]}<br>".htmlentities($line)."<hr>";	
				$found += strlen($tokens[$stage]);
				$clinic[$fields[$stage]] = substr($line, $found, strpos($line, '"', $found)-$found);
//echo "$stage: {$tokens[$stage]}<br>".print_r($clinic, 1)."<hr>";	
				$stage += 1;
			}
			else if(strpos($tokens[$stage], '<textarea name="') !== FALSE) {
				$start = strpos($line, '>', $found)+1;
				$end = strpos($line, '<', $start);
				if($end) {
					$clinic[$fields[$stage]] = substr($line, $start, $end - $start);
					$oldLine = ''; 
					$stage += 1;
				}
				else $oldLine = $line; 
			}
				
		}
	}
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

foreach($clinics as $i => $clinic) {
	$addr = trim(''.$clinic['fulladdress']);
	if(!$addr) continue;
	$addr = explode("\r", $addr);
	$origCity = $clinic['city'];
	if(count($addr) == 2) getCityStateZip($addr[1], $clinics[$i]);
	if($origCity) $clinics[$i]['city'] = $origCity;
	$clinics[$i]['street1'] = $addr[0];
	unset($clinics[$i]['fulladdress']);
	insertTable('tblclinic', $clinics[$i], 1);
	echo "Added clinic: {$clinics[$i]['clinicname']}<br>";
}

//print_r($clinics);