<? // import-referrals.php
set_time_limit(15);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

extract($_GET);
$file = "/var/data/clientimports/$file";
$strm = fopen($file, 'r');
if($debug) echo "FILE: $file ($strm)<p>";
$stage = null;
while(getLine($strm)) {
	if(!$stage && (strpos($line, '</table') === 0)) {
		$stage = "1";
		if($debug) echo "Stage 1!<br>";
	}		
	else if($stage == "1" && strpos($line, '<table') === 0) {
		$stage = "2";
		if($debug) echo "Stage 2!<br>";
		getRow($strm); // eat the 2nd row
	}
	else if($stage == "2") {
		if(strpos($line, '</table')) break;
		if(!($ref = readReferral($strm))) break;
		else {
			if($x) echo ",";
			echo "{$ref['code']},{$ref['label']}";
			$x=1;
		}
	}
}

fclose($strm);




// ==========================================================================
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

function readReferral($strm) {
	global $line, $test, $col, $lineNum;
	$item = array();
	while(getLine($strm) 
					&& !(strpos($line, '<tr') !== FALSE && strpos($line, 'bgcolor') !== FALSE)  
					&& strpos($line, '</table') === FALSE) ;  // skip TR
	if(strpos($line, '<tr') === FALSE) return null;
	//while(!($td = nextTDStripped($strm))) ;
	// employeeid,fullname,nickname,role,"v_eml_cstmr.cfm?cstmr_eml=salexan828@aol.com&",phone,cell,lastaccess
	nextTDStripped($strm); // edit
	nextTDStripped($strm); // list
	$ref['code'] = nextTDStripped($strm);
	$ref['label'] = nextTDStripped($strm);
	nextTDStripped($strm); // delete
	return $ref;
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

