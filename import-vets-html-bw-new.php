<? // import-vets-html-bw-new.php
set_time_limit(15);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

extract($_GET);
$file = "/var/data/clientimports/$file";
$strm = fopen($file, 'r');


$clinicMap = array();



//echo "FILE: $file ($strm)";
$stage = null;
while(getLine($strm)) {
	if(!$stage && (strpos($line, '</table') !== FALSE)) {
		$stage = "1";
		echo "Stage 1!<br>";
	}		
	else if($stage == "1" && strpos($line, '<table')  !== FALSE) {
		$stage = "2";
		echo "Stage 2!<br>";
		getRow($strm); // eat the 1st row
	}
	else if($stage == "2") {
		if(strpos($line, '</table')) break;
		if(!($clinic = readClinic($strm))) break;
		else {
			$created = createClinic($clinic);
			if(!is_array($created)) echo "A clinic with BW ID [{$clinic['clinicbwid']}] already exists.<br>";
			else if($TEST) echo 'Found '.print_r($clinic, 1).'<br>';
			else echo 'Added '.print_r($clinic, 1).'<br>';
		}
	}
}
echo "Read $lineNum lines.";
fclose($strm);

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



// ==========================================================================
function readClinic($strm) {
	global $line, $test, $col, $lineNum;
	$item = array();
	while(getLine($strm) 
					&& !(strpos($line, '<tr') !== FALSE /*&& strpos($line, 'onMouseOver') !== FALSE*/)  
					&& strpos($line, '</table') === FALSE) ;  // skip TR
	if(strpos($line, '<tr') === FALSE) return null;
	//while(!($td = nextTDStripped($strm))) ;
	// employeeid,fullname,nickname,role,"v_eml_cstmr.cfm?cstmr_eml=salexan828@aol.com&",phone,cell,lastaccess
	nextTDStripped($strm); // edit
	nextTDStripped($strm); // copy
	nextTDStripped($strm); // client link
	$clinic = array();
	$clinic['clinicbwid'] = nextTDStripped($strm);
	$clinic['clinicname'] = nextTDStripped($strm);
	$clinic['contact'] = nextTDStripped($strm);
	$clinic['phone'] = nextTDStripped($strm);
	$clinic['otherphone'] = nextTDStripped($strm);
	$clinic['specialty'] = nextTDStripped($strm);
	$clinic['active'] = nextTDStripped($strm);
	nextTDStripped($strm); // delete
	while(getLine($strm) && strpos($line, '</tr') === FALSE) ;  // eat the rest
	return $clinic;
}

function createClinic($clinic) {
	global $clinicMap, $TEST;
	$preexisting = fetchFirstAssoc("SELECT * FROM tblclinic WHERE clinicname = '{$clinic['vetname']}' LIMIT 1");
	if($preexisting) return $preexisting['clinicid'];
	if($TEST) return array(1);
	if($clinic['contact']) $notes = "Contact: {$clinic['contact']}";
	if($clinic['specialty']) $notes = $notes ? "$notes\nSpecialty: {$clinic['specialty']}" : "Specialty: {$clinic['specialty']}";
	if($clinic['otherphone']) $notes = $notes ? "$notes\nOther phone: {$clinic['otherphone']}" : "Other phone: {$clinic['otherphone']}";
	$clinicRecord = array('clinicname'=>$clinic['clinicname'], 'notes'=>$notes, 'officephone'=>$clinic['phone']);
  $id = insertTable('tblclinic', $clinicRecord, 1);
	if($id && $clinic['clinicbwid']) $clinicMap[$clinic['clinicbwid']] = $id;
  $clinic['clinicid'] = $id;  
  return $clinic;
}

function htmlize($s) {return str_replace("\n", '<br>', $s);}


function getRow($strm) {
	global  $line, $lineNum; 
	while(getLine($strm)) {
		if(strpos($line, '<tr>') !== FALSE || strpos($line, '</table') !== FALSE) break;  // skip shim
	}
	if(strpos($line, '</table') !== FALSE) return -1;
	$row = array();
	while(strpos($line, '</tr>') === FALSE)
		$row[] = nextTDStripped($strm);
//echo "[[".print_r($row, 1).']]<p>';		
	return $row;
}

function getLine($strm) {
	global $line, $col, $lineNum, $stripSlashesFromLine;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
//echo "LINE: [$lineNum] ".$line."<br>\n";	
	$line = trim($line);
	if($stripSlashesFromLine) $line = stripslashes($line);
//if(strpos($line, 'Phone:') !== FALSE) {echo "[$lineNum] $line";;}
	if(strpos($line, '<tr') !== FALSE) $col = 0;
	if(strpos($line, '<td') !== FALSE) $col++;
	return true;
}

function nextTDStripped($strm) {
	global $line, $lineNum;
	$td = '';
	do {
		if(!getLine($strm)) return FALSE;
		if($td) $spacer = "\n"; else $spacer = '';
		$td .= $spacer.$line;
	} while (strpos($line, '</td>') === FALSE && strpos($line, '</tr>') === FALSE);
	return trim(strip_tags(str_replace('&nbsp;', ' ', $td)));
}
	
	
function nextLineStripped($strm) {
	global $line;
	if(!getLine($strm)) return FALSE;
	$line = trim($line);
	return strip_tags($line);
}

