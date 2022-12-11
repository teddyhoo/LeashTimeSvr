<? // import-providers-mapped.php
// lname	fname	email	workphone	homephone	cellphone	convert_pops_emergency_contact		street1	street2	city	state	zip	notes/-	hiredate	notes/-

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";

extract($_REQUEST);

$map = "/var/data/clientimports/$map";  // map-powerpetsitter-providers.csv
$file = "/var/data/clientimports/$file"; //fetch210/2009-01-19/Staff Export January 18 2010.xls
$allInactive = $inactive;

$mapLines = file($map, FILE_IGNORE_NEW_LINES);

$originalFields = array_map('trim', explode(',', trim($mapLines[0])));
$conversions = array_map('trim', explode(',', trim($mapLines[1])));
$map = array_combine($originalFields, $conversions);


echo "originalFields: ".count($originalFields).'<br>conversions: '.count($conversions).'<br>';

for($i=0;$i<max(count($originalFields), count($conversions));$i++) {
  echo ($i >= count($originalFields) ? "<i>[no value]</i>" : $originalFields[$i])." ==> ";
  echo ($i >= count($conversions) ? "<i>[no value]</i>" : $conversions[$i])."<br>";
}

echo "<hr>";
if(!$_REQUEST['file']) {echo "No file supplied.";exit;}

$delimiter = strpos($file, '.xls') ? "\t" : ',';
$strm = fopen($file, 'r');
//$line0 = trim(fgetcsv($strm, 0, $delimiter)); 
$dataHeaders = array_map('trim', fgetcsv($strm, 0, $delimiter));// consume first line (field labels)


echo "dataHeaders: ".count($dataHeaders).':<br>'.join(',', $dataHeaders).'<hr>';
//echo "map: ".print_r($map,1);exit;

//print_r($line0);
$n = 1;

//while($line = trim(fgets($strm))) echo "$line<br>";

$provMap = array();  // mapID => clientid
$customFields = getCustomFields('activeOnly');

//print_r($_SESSION['preferences']);exit;

$incompleteRow = null;
while($row = fgetcsv($strm, 0, $delimiter)) {
echo "<font color=red>".print_r($row, 1)."</font><br>";	
	$n++;
	//$line = trim($line);
	if(!$row || (count($row) == 1 && !$row[0])) {echo "Empty Line #$n<br>";continue;}
	//$row = explode(',', $line);
	$prov = array();
	$notes = array();
	foreach($row as $i => $field) {
		if(!$field) continue;
		//$conv = $conversions[$i];
		$conv = $map[$dataHeaders[$i]];
echo "$conv: $field	<br>";	
		if(strpos($conv, 'ignore-') === 0) {continue;}
		else if($conv == 'notes/-') {
			$notes[] = $dataHeaders[$i].': '.$field;
		}
		//convert_bluewave_provider	convert_bluewave_alternate_provider	convert_bluewave_status	convert_bluewave_level
		else if(strpos($conv, 'convert_') === 0) // conversion must return $prov
			$prov = call_user_func_array($conv, array($field, $prov, $row));
		else if(strpos($conv, 'custom') === 0) 
			$prov['custom'][$conv] = $field;
		else {$prov[$conv] = $field;}
	}
	if($notes) $prov['notes'] = join("\n",$notes);
	if(!(isset($prov['lname']) || isset($prov['fname']))) echo "Bad row: $n ".print_r($prov,1).'<br>';
	else {
		$mapID = $prov['mapID'];
		if(!$prov['activeHasBeenSet']) $prov['active'] = ($allInactive ? '0' : '1');  // see convert_setClientActive
		saveNewProviderWithData($prov);
		$newClientId = mysql_insert_id();
		if($mapID) $provMap[$mapID] = $newClientId;
		echo "<p>Created PROVIDER #$newClientId {$prov['fname']} {$prov['lname']}<br>";
		if($key) {
			$key['copies'] = 1;
			$key['possessor1'] = 'client';
			$keyId = saveClientKey($newClientId, $key);
			echo "<p>Created KEY #$keyId<br>";
		}
		//saveClientPets($newClientId);
		if($emergencyContact) {
			$contactId = saveClientContact('emergency', $newClientId, $emergencyContact);
			echo "<p>Created Emergency Contact #$contactId {$emergencyContact['name']}<br>";
		}
		if($neighborContact) {
			$contactId = saveClientContact('neighbor', $newClientId, $neighborContact);
			echo "<p>Created Trusted Neighbor #$contactId {$neighborContact['name']}<br>";
		}
		if($prov['custom']) {
			foreach($prov['custom'] as $field => $val) {
				$val = mysql_real_escape_string($val);
				if(!$customFields[$field])
					echo "Bad custom field [$field].  Could not populate this field with [$val] for {$prov['lname']} [{$prov['mapID']}]";
				else doQuery("REPLACE relclientcustomfield (clientptr, fieldname, value) VALUES ($newClientId, '$field', '$val')");
			}
			echo "Added custom fields for $newClientId<br>";
		}
		
	}
}

echo "<hr>";
if($provMap) {
	$tableSql = "CREATE TABLE IF NOT EXISTS `tempClientMap` (
  `externalptr` int(11) NOT NULL,
  `clientptr` int(11) NOT NULL,
  PRIMARY KEY  (`externalptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

	doQuery($tableSql);
	doQuery('DELETE FROM tempClientMap');
	echo "External ID,ClientID<br>";
	foreach($provMap as $mapID => $provid) {
		insertTable('tempClientMap', array('externalptr'=>$mapID,'clientptr'=>$provid), 1);
		echo "$mapID,$provid<br>";
	}
}
echo "<hr>";

// BLUEWAVE CONVERSION FUNCTIONS
	
function convert_pops_emergency_contact($contactName, &$prov, &$row) {
	global $conversions;
	$contact = array();
	if($contactName) $contact[] = $contactName;
	
	if($phone = $row[array_search('ignore-emergency_contact_phone', $conversions)])
		$contact[] = $phone;
	$prov['emergencycontact'] = join("\n", $contact);
	return $prov;
}
	
function decodeComment($str) {
	return str_replace('#EOL#', "\n", $str);
}