<?// import-vet-clinics.php

// http://iwmr.info/petbizdev/import-vet-clinics.php?map=map-bluewave-clinics.csv&file=woofies/vet-clinics-bluewave.csv

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "vet-fns.php";
require_once "key-fns.php";

extract($_REQUEST);

$map = "/var/data/clientimports/$map";
$file = "/var/data/clientimports/$file";


$mapLines = file($map, FILE_IGNORE_NEW_LINES);

$originalFields = explode(',', trim($mapLines[0]));
$conversions = explode(',', trim($mapLines[1]));


echo "originalFields: ".count($originalFields).'<br>conversions: '.count($conversions).'<br>';

for($i=0;$i<max(count($originalFields), count($conversions));$i++) {
  echo ($i >= count($originalFields) ? "<i>[no value]</i>" : $originalFields[$i])." ==> ";
  echo ($i >= count($conversions) ? "<i>[no value]</i>" : $conversions[$i])."<br>";
}

echo "<hr>";

$strm = fopen($file, 'r');
$line0 = trim(fgets($strm)); // consume first line (field labels)
print_r($line0);
$n = 1;

//while($line = trim(fgets($strm))) echo "$line<br>";

$clinicMap = array();
$delimiter = strpos($file, '.xls') ? "\t" : ',';
while($row = fgetcsv($strm, 0, $delimiter)) {
	$n++;
	//$line = trim($line);
	if(!$row) {echo "Empty Line #$n<br>";continue;}
	//$row = explode(',', $line);
	$clinic = array();

	foreach($row as $i => $field) {
		if(!$field) continue;
		$conv = $conversions[$i];
		if($conv == 'x') continue;		
		$clinic[$conv] = $field;
	}
	if(!isset($clinic['clinicname'])) echo "<p><font color=red>Bad row: $n</font>";
	else if($TEST) echo "<p>Found CLINIC #$clinicId {$clinic['clinicname']}<br>";
	else {
		$mapID = $clinic['mapID'];
		unset($clinic['mapID']);
		$clinicId = insertTable('tblclinic', $clinic, 1);
		if($mapID) $clinicMap[$mapID] = $clinicId;
		echo "<p>Created CLINIC #$clinicId {$clinic['clinicname']}<br>";
	}
}

echo "<hr>";
if($clinicMap) {
	$tableSql = "CREATE TABLE IF NOT EXISTS `tempClinicMap` (
  `externalptr` int(11) NOT NULL,
  `clinicptr` int(11) NOT NULL,
  PRIMARY KEY  (`externalptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

	doQuery($tableSql);
	doQuery('DELETE FROM tempClinicMap');
	echo "External ID,ClinicID<br>";
	foreach($clinicMap as $mapID => $clinicid) {
		insertTable('tempClinicMap', array('externalptr'=>$mapID,'clinicptr'=>$clinicid), 1);
		echo "$mapID,$clinicid<br>";
	}
}

