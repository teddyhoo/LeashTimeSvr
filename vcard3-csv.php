<? // vcard3-csv.php
// ignore PHOTO
extract($_GET);
$file = '/var/data/clientimports/dogcampla/DCLAvCards.vcf';
$badUIDs = 
"16849468-716B-11DC-9AD7-000A95C4DF50\:ABPerson
1543615D-716B-11DC-9AD7-000A95C4DF50\:ABPerson
400C9960-BD56-4E62-A94F-8CFCB777941A\:ABPerson
60F7E7C5-95A9-45EC-9720-0D3F7624E0AB\:ABPerson
480608EE-01D2-4846-BAC9-1FC18B446CA0\:ABPerson
A9127520-BC2C-4FA6-866D-B811E8798D35\:ABPerson
8ACF0814-5F58-474C-9549-9458EAA95D4A\:ABPerson
170BDDF4-716B-11DC-9AD7-000A95C4DF50\:ABPerson
FF9C98FA-3188-4A8E-9865-12E2EE8F936F\:ABPerson
158C8000-716B-11DC-9AD7-000A95C4DF50\:ABPerson
DA809BFD-E597-43FF-982B-BEB605A5BE77\:ABPerson
655EB014-1FCD-4B57-BAC5-13E875752D9A\:ABPerson
172E030C-716B-11DC-9AD7-000A95C4DF50\:ABPerson
E9FAC90B-6B78-4FA8-9E87-43BBF2883FA4\:ABPerson
D4794FD2-0F28-4492-9D9B-995D38883370\:ABPerson";
$badUIDs = array_map('trim', explode("\n", $badUIDs));

$strm = fopen($file, 'r');

while($rawline = fgets($strm)) {
	$line =trim($rawline);
	if($line == 'BEGIN:VCARD') {$vc = array(); $cards++;  continue;}
	else if($line == 'END:VCARD') {if($vc) $vcards[] = $vc; continue;}
	else if($line == 'VERSION:3.0') continue;
	else if(strpos($line, 'PHOTO') === 0) continue;
	else if(strpos($line, 'PHOTO') === 0) continue;
	else if(strpos($rawline, ' ') === 0) continue;
	$colon = strpos($line, ':');
	$key = substr($line, 0, $colon);
	$val = substr($line, $colon+1);
	$keyParts = explode(';', $key);
	$whole = array();
	foreach($keyParts as $i => $part) {
		if($i == 0) {
			$primary = $part;
			$whole[] = $primary;
		}
		if(strpos($part, '=')) {
			$part = explode('=', $part);
			if($part[0] == 'value') continue;
			else if($part[0] == 'type') {
				if($part[1] == 'INTERNET') continue;
				else if($part[1] == 'pref') {
				  if(strpos($key, 'TEL') !== FALSE) {/*echo "<font color=red>$key: $val</font><br>";*/$val = "*$val";}
				  $part[1] = '';
				}
			}
			if($part[1]) $whole[] = $part[1];
		}
		$wholekey = join('|', $whole);
		$vc[$wholekey] = $val;
		if(strpos($primary, 'item') === 0) 
			$allKeys[$wholekey][] = $val;
		else $allKeys[$wholekey] = 0;
	}
}
//echo "Cards: $cards<p>";

$cols = array();
foreach($vcards as $vc) {
	$row = array('bad'=>'');
	$name = explode(';', $vc['N']);
	$row['lname'] = $name[0];
	$row['fname'] = $name[1];
	$row['uid'] = $vc['X-ABUID'];
	$row['notes'] = $vc['NOTE'];
	$row['birthday'] = $vc['BDAY'];
	foreach($vc as $key => $val) {
		if(strpos($key, '.ADR')) {
			if(strpos($key, 'HOME')) getAddressFields($val, $row, '');
			else if(strpos($key, 'WORK')) getAddressFields($val, $row, 'work');
			else getAddressFields($val, $row, '');
		}
	}
	$row['pets'] = processPets($vc['NICKNAME'], $row, $vc);
	$row['import'] = processItems($vc);
	$row['import'] = array_merge($row['import'], processTel($vc, $row));
	$row['import'] = join("#EOL#", $row['import']);
	foreach($vc as $key => $val) {
		if(strpos($key, 'item1.EMAIL') === 0) $row['email'] = $val;
		else if(strpos($key, 'item2.EMAIL') === 0) $row['email2'] = $val;
	}
	foreach($row as $key => $i) if(!isset($cols[$key])) $cols[$key]=count($cols);
	$rows[] = $row;
}


/*
$cols = array_keys($cols);
header("Content-Type: text/csv");
header("Content-Disposition: inline; filename=vcards.csv ");
dumpCSVRow($cols);
foreach($rows as $row)
	dumpCSVRow($row, $cols);//echo print_r($row,1).'<br>';

*/


function processPets($petstring, &$row, &$vc) {
	global $badUIDs;
	if(in_array($vc['X-ABUID'], $badUIDs)) $row['bad'] = 'x';
	if(!trim($petstring)) $row['bad'] = 'x';
	if(strpos($petstring, ' and ')) $pets = array_map('trim', explode(' and ', $petstring));
	if(strpos($petstring, ' & ')) $pets = array_map('trim', explode(' & ', $petstring));
	if(strpos($petstring, '/')) $pets = array_map('trim', explode('/', $petstring));
	if(strpos($petstring, '\,')) $pets = array_map('trim', explode('\,', $petstring));
	if(!$pets) $pets = array(str_replace(',& ',',', $petstring));
	return join(',', $pets);
}

function processTel(&$vc, &$row) {
	$items = array();
	foreach($vc as $key => $val) {
		if(strpos($key, 'TEL') !== 0) continue;
//if(strpos($val, '3')) echo print_r($vc,1)."<p>";		
		$type = strpos($key, 'HOME') ? 'homephone' :(
			strpos($key, 'CELL') ? 'cellphone' :(
			strpos($key, 'WORK') ? 'workphone' : 'other'));
		if($val[0] == '*') $row[$type] = $val;
		else $items[] = "$type: $val";
	}
	return $items;
}

function processItems($vc) {
	$items = array();
	foreach($vc as $key => $val) {
		if(strpos($key, 'item') !== 0) continue;
		if(strpos($key, 'X-ABLabel')) continue;
		if(strpos($key, 'ADR')) continue;
		$prefix = substr($key, 0, strpos($key, '.')+1);
		$preferred = strpos($key, 'type=pref');
		$label = $vc[$prefix."X-ABLabel"].($preferred ? ' (preferred)' : '');
		$items[] = "$label: $val";
	}
	return $items;
}

function getAddressFields($addr, &$row, $prefix) {
	$addr = explode(';', $addr);
	$row[$prefix.'street1'] = $addr[2];
	$row[$prefix.'street2'] = $addr[1];
	$row[$prefix.'city'] = $addr[3];
	$row[$prefix.'state'] = $addr[4];
	$row[$prefix.'zip'] = $addr[5];
	$row[$prefix.'country'] = $addr[6];	
}

function dumpCSVRow($row, $cols=null) {
	if(!$row) echo "\n";
	if(is_array($row)) {
		if($cols) {
			$nrow = array();
			if(is_string($cols)) $cols = explode(',', $cols);
			foreach($cols as $k) $nrow[] = $row[$k];
			$row = $nrow;
		}
		echo join(',', array_map('csv',$row))."\n";
	}
	else echo csv($row)."\n";
}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}
//exit;

echo "<hr>";

foreach($allKeys as $key => $val)	if(strpos($key, 'ABLabel')) foreach($val as $v) $ablabels[$v] = 0;
foreach($allKeys as $key => $val)	if(strpos($key, '.')) $itemkeys[substr($key, strpos($key, '.')+1)] = 0;
foreach($allKeys as $key => $val) 
	if(!strpos($key, '.')) echo "$key".($val===999 ? ": <font color=green>".join(', ', array_unique($val))."</font>" : '')."<br>";
ksort($ablabels);
echo "<p>Item Keys (item1.*, item2.*, etc):<br><font color=blue>";
foreach($itemkeys as $label => $u) echo "$label<br>";
echo "</font>";
echo "<p>ABLabels:<br><font color=green>";
foreach($ablabels as $label => $u) echo "$label<br>";
/*
BEGIN:VCARD
VERSION:3.0
N:Salas;Gilbert & Shelly;;;
FN:Gilbert & Shelly Salas
NICKNAME:Catfish\,Ben\, and Freak
ORG:DOG CAMP L.A.;DOG CAMP
TITLE:FILM PRODUCTION
item1.EMAIL;type=INTERNET;type=pref:shellystrazis@verizon.net
item1.X-ABLabel:E-Mail
TEL;type=WORK;type=pref:323 791-0078 G
TEL;type=HOME:323-668-2961
TEL;type=CELL:562 270-9654 S
item2.TEL:562 930-0095 S
item2.X-ABLabel:_$!<Other>!$_
item3.ADR;type=HOME;type=pref:;;3761 Valleybrink Rd;ATWATER;;;
item3.X-ABADR:us
NOTE:emergency contact-rudy salas 323 791-0074
CATEGORIES:Dog Camp LA
X-ABUID:160FD450-716B-11DC-9AD7-000A95C4DF50\:ABPerson
END:VCARD
*/
	
/*
N
FN
NICKNAME
ORG
item1.EMAIL
item1.X-ABLabel
TEL
TEL|WORK
TEL|CELL
item2.TEL
item2.X-ABLabel
item3.ADR
item3.ADR|HOME
item3.X-ABADR
item4.X-ABRELATEDNAMES
item4.X-ABLabel
CATEGORIES
X-ABUID
item1.TEL
item2.ADR
item2.ADR|WORK
item2.X-ABADR
TITLE
NOTE
TEL|HOME
item2.ADR|HOME
item3.TEL
item3.X-ABLabel
item4.ADR
item4.ADR|HOME
item4.X-ABADR
item1.ADR
item1.ADR|HOME
item1.X-ABADR
item3.X-ABRELATEDNAMES
X-AIM
X-AIM|WORK
item5.URL
item5.X-ABLabel
item6.X-ABRELATEDNAMES
item6.X-ABLabel
item7.X-ABRELATEDNAMES
item7.X-ABLabel
BDAY
item4.TEL
item5.ADR
item5.ADR|HOME
item5.X-ABADR
item6.ADR
item6.ADR|HOME
item6.X-ABADR
item8.X-ABRELATEDNAMES
item8.X-ABLabel
item9.X-ABRELATEDNAMES
item9.X-ABLabel
item2.X-ABRELATEDNAMES
item3.URL
item5.X-ABRELATEDNAMES
item3.ADR|WORK
item2.EMAIL
X-ABShowAs
item1.ADR|WORK
item5.TEL
TEL|WORK|FAX
item6.URL
item3.EMAIL
item6.TEL
item7.TEL
item8.ADR
item8.ADR|HOME
item8.X-ABADR
item10.X-ABRELATEDNAMES
item10.X-ABLabel
item5.ADR|WORK
item7.ADR
item7.ADR|HOME
item7.X-ABADR
item8.URL
item11.X-ABRELATEDNAMES
item11.X-ABLabel
item4.URL
item7.URL
item4.ADR|WORK
item6.X-AIM
TEL|PAGER
item2.URL
EMAIL
EMAIL|HOME
item9.ADR
item9.ADR|HOME
item9.X-ABADR
item4.X-AIM
item1.X-ABRELATEDNAMES
TEL|HOME|FAX	
*/