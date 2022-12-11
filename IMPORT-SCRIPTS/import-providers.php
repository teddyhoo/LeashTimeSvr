<? // import-providers.php
set_time_limit(15);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

extract($_GET);
$file = "/var/data/clientimports/$file";
$strm = fopen($file, 'r');
echo "FILE: $file ($strm)<p>";
$stage = null;
while(getLine($strm)) {
	if(!$stage && (strpos($line, '</table') === 0)) {
		$stage = "1";
		echo "Stage 1!<br>";
	}		
	else if($stage == "1" && strpos($line, '<table') === 0) {
		$stage = "2";
		$staffRoster = getRow($strm); // eat the first row strip_tags(getRow($strm))
		$active = strpos($staffRoster[0], 'Active') !== FALSE ? 1 : '0';
		echo "Stage 2! ".($active ? 'ACTIVE' : 'INACTIVE')." Sitters<br>";
		getRow($strm); // eat the 2nd row
	}
	else if($stage == "2") {
		if(strpos($line, '</table')) break;
		if(!($provider = readProvider($strm))) break;
		else {
			$created = createProvider($provider);
			if(!is_array($created)) echo "A provider with employee ID [{$provider['employeeid']}] already exists.<br>";
			else if($TEST) echo 'Found '.print_r($provider, 1).'<br>';
			else echo 'Added '.print_r($provider, 1).'<br>';
		}
	}
}

$section = null;
while(getLine($strm)) {
	if(!$section && strpos($line, '<table') === 0) {  // Staff Phone List table
		$section = "1";
		getRow($strm); // eat the first row
	}
	else if($section = "1") {
		if(strpos($line, '</table')) break;
		if(!($row = readProviderPhones($strm))) break;
		else {
			$cellphone = mysql_real_escape_string(stripslashes($row['cellphone']));
			$homephone = mysql_real_escape_string(stripslashes($row['homephone']));
			$homePhoneTest = $row['homephone'] ? "homephone = ".val($homephone)."" : "homephone IS NULL";
			$cellPhoneTest = $row['cellphone'] ? "cellphone = ".val($cellphone)."" : "cellphone IS NULL";
			$lname = mysql_real_escape_string(stripslashes($row['lname']));
			$fname = mysql_real_escape_string(stripslashes($row['fname']));
			$providerId = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE lname = '$lname' AND fname = '$fname' AND $homePhoneTest AND $cellPhoneTest LIMIT 1");
			if(!$providerId) echo "No provider named [{$row['fname']} {$row['lname']}] exists to add contact info: ".print_r($row, 1)."<br>";
			else {
				$newDetails = array('workphone'=>$row['workphone'], 'email'=>$row['email']);
				updateTable('tblprovider', $newDetails, "providerid=$providerId",1);
				echo 'Added contact info'.print_r($row, 1).'<br>';
			}
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

function createProvider($provider) {
	global $active, $TEST, $refreshData;
	$preexisting = fetchFirstAssoc("SELECT * FROM tblprovider WHERE employeeid = '{$provider['employeeid']}' LIMIT 1");
	//$preexisting = fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid = '{$provider['providerid']}' LIMIT 1");// How could this happen?
	if($preexisting && !$refreshData) return $preexisting['providerid'];  
	if($TEST) return array(1);
	if($preexisting && $refreshData) {
		updateTable('tblprovider', $provider, "providerid = {$preexisting['providerid']}", 1);
		return $provider;
	}
	// ELSE
	$loginid = $provider['loginid'];
	unset($provider['loginid']);
	$provider['active'] = $active;
  if(!isset($provider['dailyvisitsemail'])) $provider['dailyvisitsemail'] = $_SESSION['preferences']['scheduleDaily'];
  if(!isset($provider['weeklyvisitsemail'])) $provider['weeklyvisitsemail'] = ($_SESSION['preferences']['scheduleDay'] ? 1 : 0);
  $provider['lname'] = $provider['lname'] ? $provider['lname'] : 'unknown';
  $provider['fname'] = $provider['fname'] ? $provider['fname'] : 'unknown';
  $id = insertTable('tblprovider', $provider, 1);
  $provider['providerid'] = $id;
  //setLoginId($id, $loginid);
  
  return $provider;
}



function readProviderPhones($strm) {
	global $line, $test, $col, $lineNum;
	//while(getLine($strm) && strpos($line, '<tr') === FALSE) ;  // skip TR
	while(getLine($strm) 
					&& !(strpos($line, '<tr') !== FALSE && strpos($line, 'onMouseOver') !== FALSE)  
					&& strpos($line, '</table') === FALSE) ;  // skip TR
	// employeeid,fullname,nickname,role,"v_eml_cstmr.cfm?cstmr_eml=salexan828@aol.com&",phone,cell,lastaccess
	$phones = array();



	$names = explode(' ', nextTDStripped($strm));
	$phones['lname'] = substr($names[0], 0, max(0, strlen($names[0])-1));
	$phones['fname'] = $names[1];
	$phones['loginid'] = $names[2];
	if(strpos($phones['loginid'], '(') === 0) $phones['loginid'] = substr($phones['loginid'], 1, -1);

	$phones['homephone'] = nextTDStripped($strm); // home
	$phones['cellphone'] = nextTDStripped($strm); // cell
	$phones['workphone'] = nextTDStripped($strm);
	nextTDStripped($strm); // email link
	$phones['email'] = nextTDStripped($strm);
	while(getLine($strm) && strpos($line, '</tr') === FALSE) ;  // eat the rest
	return $phones;
}

function readProvider($strm) {
	global $line, $test, $col, $lineNum;
	$item = array();
	while(getLine($strm) 
					&& !(strpos($line, '<tr') !== FALSE && strpos($line, 'onMouseOver') !== FALSE)  
					&& strpos($line, '</table') === FALSE) ;  // skip TR
	if(strpos($line, '<tr') === FALSE) return null;
	//while(!($td = nextTDStripped($strm))) ;
	// employeeid,fullname,nickname,role,"v_eml_cstmr.cfm?cstmr_eml=salexan828@aol.com&",phone,cell,lastaccess
	$provider = array();
	$provider['employeeid'] = nextTDStripped($strm);
	$names = explode(' ', nextTDStripped($strm));
	$provider['lname'] = substr($names[0], 0, max(0, strlen($names[0])-1));
	$provider['fname'] = $names[1];
	$provider['loginid'] = $names[2];
	if(strpos($provider['loginid'], '(') === 0) $provider['loginid'] = substr($provider['loginid'], 1, -1);
	$provider['nickname'] = nextTDStripped($strm);
	nextTDStripped($strm); // role
	nextTDStripped($strm); // links
	$provider['homephone'] = nextTDStripped($strm);
	$provider['cellphone'] = nextTDStripped($strm);
	while(getLine($strm) && strpos($line, '</tr') === FALSE) ;  // eat the rest
	return $provider;
}

function getLine($strm) {
	global $line, $col, $lineNum, $stripSlashesFromLine;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
//echo "LINE: [$lineNum] ".$line."<br>\n";	
	$line = strippedLine($line);
//echo htmlentities($line)."<br>";	
	//if($stripSlashesFromLine) $line = stripslashes($line);
//if(strpos($line, 'Phone:') !== FALSE) {echo "[$lineNum] $line";;}
	if(strpos($line, '<tr') !== FALSE) $col = 0;
	if(strpos($line, '<td') !== FALSE) $col++;
	return true;
}

function strippedLine($line) {
	return trim(str_replace(chr(0), '', $line));
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

