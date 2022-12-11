<?// import-one-bettawalka-sitter.php

/*

This script parses the text obtained by Ctrl-A, Ctrl-V on a single sitter's account review in BettWalka.

Sample: S:\clientimports\BettaWalkaSitterSample.txt
https://leashtime.com/import-one-bettawalka-sitter.php?file=xxxxx

*/
set_time_limit(5);
require_once "gui-fns.php";
require_once "provider-fns.php";
require_once "custom-field-fns.php";
require_once "pet-fns.php";
require_once "field-utils.php";
require_once "item-note-fns.php";

$fieldMap = 
trim("
Phone=homephone
Mobile Phone=cellphone
Work#=workphone
Email=email
Corp Email=+notes
Birthdate=+notes
Name=+emergencycontact
Phone#=+emergencycontact
Soc Security#=taxid
Std. Pay %=+notes
Username=loginid
"
);
foreach(explodePairPerLine($fieldMap, $sepr='=') as $field => $disposition)
	$arr["$field:"] = $disposition;
$fieldMap = $arr;


function importSitter($showUnhandled=false) {
	//Alarm Code In,Alarm Code Out
	extract($_REQUEST);
	global $line, $rawLine, $lineNum, $provider, $providerid, $stack, $lastLine;

	ob_start();
	ob_implicit_flush(0);
	if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db</h3>";
	else echo "<h2 style='color:orange;'>DB: $db</h2>";
	if($file) $strm = fopen("/var/data/clientimports/$file", 'r');
	else {
		//$sitterdata = str_replace("\r", '', $sitterdata);
//redline(strpos($sitterdata, "\r"));
//redline('Chars: '.strlen($sitterdata));
//redline('Lines: '.count(explode("\n", $sitterdata)));
//redline($sitterdata);exit;
		$strm = fopen("data://text/plain,$sitterdata" , 'rb');
	}
	$provider = array();
	$stack[] = &$provider;
	while(!$done && getLine($strm)) {
//echo "$lineNum: <font color=green>".htmlentities($rawLine)."</font><br>";
		if($line == 'Contact Info') startBox('contact');

		else if($line == 'Make Inactive') $provider['active'] = 1;
		else if($line == 'Emergency Info' && startBox('emergency-contact') == 'quit') break;
		else if($line == 'Notes') startBox('notes'); // different for customer and pet
		else if($lastLine == 'Notes') appendField('notes', '', lineVal(' '));
		else if($line == 'Financial Info') startBox('financial');
		else if($line == 'Login Info') startBox('login');
		else if($line == 'Mobile Messaging Info') startBox('sms');

		else if(lineStartsWith($pat = 'contact>Name:')) getFnameLname(lineVal($pat));
		else if(lineStartsWith($pat = 'Address:')) {$stack[count($stack)-1]['street1'] = lineVal($pat);}
		else if($lastPattern == 'Address:') {getCityStateZip($line); $lastPattern = '';}
		else if(lineStartsWith($pat = 'Status:')) {$provider['active'] = lineVal($pat) == 'Enabled' ? 1 : '0';}
		else if(lineStartsWith($pat = 'sms>Phone#:')) $provider['smsphone'] = lineVal($pat);
		else if(lineStartsWith($pat = 'sms>Email:')) $provider['smsemail'] = lineVal($pat);

		else if($line == 'Automatic Schedule Info') {
			if($provider['smsphone'] == $provider['cellphone']) 
				$provider['cellphone'] = phoneNumberAsTextEnabled($provider['cellphone']);
			if($provider['smsemail']) appendField('notes', 'Mobile Messaging Email:', $provider['smsemail']);
			saveProvider($provider);
			//echo "Sitter: ".print_r(fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid = $providerid"), 1)."<p>";
			$sitter = fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid = $providerid LIMIT 1", 1);
			echo "Added sitter: [{$sitter['fname']}] [{$sitter['lname']}].";
			$done = true;
		}

		else if(!handleMappedFields() && $showUnhandled) echo "<font color=gray>Unhandled: ".strip_tags($line).'</font><br>'; 
		$lastLine = $line;
	}
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function redline($s){$s = is_array($s) ? print_r($s, 1) : $s; echo "<font color=red>[$s]</font></br>";}
function mres($s) { return mysqli_real_escape_string($s); }
function handleMappedFields() {
	global $line, $provider, $pet, $stack, $lastPattern, $fieldMap, $box, $boxCounts;
	if(!lineStartsWithOneOf(array_keys($fieldMap))) return;
	$key = $lastPattern;
	$disposition = $fieldMap[$key];
	$val = lineVal($key);
	if(in_array($key, array('Email:','Alt Email:', 'D.O.B.:')))
		$val = trim(str_replace('image', '', $val));
	if(!$val) return;
//echo "$key<br>";	
	$boxCounts[$box]+= 1;
	if($disposition[0] == '+') { $add = true; $disposition = substr($disposition, 1); }
	if($disposition[0] == '*') { 
		$disposition = substr($disposition, 1);
		if(strpos($disposition, 'pet') === 0) $pet[$disposition] = $val;
		else $provider[$disposition] = $val;
	}
	else if(strpos($disposition, '>')) {
		list($obj, $disposition) = explode('>', $disposition);
		if($obj == 'provider') $obj =  &$provider;
	}
	else $obj = &$stack[count($stack)-1];
	
	if($add) $val = $obj[$disposition]."\n$key $val";
	$obj[$disposition] = $val;
	return true;
}

function appendField($key, $label, $addendum) {
	global $stack;
	if(strpos($pattern, '>')) {
		list($label, $key) = explode('>', $key);
		if($box != $label) return false;
		$dest = &$stack[count($stack)-2];
	}
	else $dest = &$stack[count($stack)-1];
	if(strpos($dest[$key], "$label: $addendum") === FALSE) {  // don't mindlessly duplicate
		$label = $label ? "$label " : '';
		$dest[$key] .= "\n$label$addendum";
	}
}

function startBox($label) {
	global $box, $stack, $provider, $providerid, $override, $pet;
	if($box) {// finish processing box
		if($box == 'contact') {
			$homephone[] = "homephone LIKE '%".mres($provider['homephone'])."'";
			if(!$provider['homephone']) $homephone[] = "homephone IS NULL";
			$homephone = "(".join(' OR ', $homephone).")";
			$cellphone[] = "cellphone LIKE '%".mres($provider['cellphone'])."'";
			if(!$provider['cellphone']) $cellphone[] = "cellphone IS NULL";
			$cellphone = "(".join(' OR ', $cellphone).")";
			$doppl = fetchFirstAssoc($sql = "SELECT * FROM tblprovider 
																				WHERE fname = '".mres($provider['fname'])."'
																					AND  lname = '".mres($provider['lname'])."'
																					AND $homephone
																					AND $cellphone");
			if($doppl && !$override) {
				echo "<font color=red>Sitter {$provider['fname']} {$provider['lname']} already exisits.<br></font>";
				return 'quit';
			}
			if(!$doppl || $override) {
				$providerid = saveNewProviderWithData($provider);
				$provider['providerid'] = $providerid;
			}
		}
	} 
	$box = $label;
}

function getCityStateZip($str) {
	global $stack;
	$destination = &$stack[count($stack)-1];
	$parts = explode(' ', $str);
	if($parts) {
		if($parts[count($parts)-1] == 'image') array_pop($parts);
		if($parts && is_numeric($parts[count($parts)-1])) $destination['zip'] = array_pop($parts);
		if($parts && strlen($parts[count($parts)-1]) == 2) $destination['state'] = array_pop($parts);
		if($parts) $destination['city'] = join(' ', $parts);
		//echo "[$str] ".print_r($destination, 1)."<p>";
	}
}

function getFnameLname($str, $fnameKey='fname', $lnameKey='lname') {
	global $stack;
	$destination = &$stack[count($stack)-1];
	$parts = explode(' ', $str);
	if(count($parts)) {
		$destination[$fnameKey] = $parts[0];
		unset($parts[0]);
		if($parts) $destination[$lnameKey] = join(' ', $parts);
	}
}

function lineStartsWithOneOf($patterns, $suffix='') {
	foreach($patterns as $pat)
		if(lineStartsWith($pat))
			return $pat;
}

function lineStartsWith($pattern) {
	global $line, $col, $lineNum, $rawLine, $box, $lastPattern;
	if(strpos($pattern, '>')) {
		list($label, $pattern) = explode('>', $pattern);
		if($box != $label) return false;
	}
	if(strpos($line, $pattern) === 0) {
		$lastPattern = $pattern;
		return true;
	}
}

function lineVal($pattern, $stripEmpty=true) {
	global $line, $box, $lineNum, $rawLine;
	if(strpos($pattern, '>')) {
		list($label, $pattern) = explode('>', $pattern);
		if($box != $label) return false;
	}
	$val = substr($pattern, -1) == ':' ? substr($line, strlen($pattern)) : $line;
	if($stripEmpty) {
		$emptyPattern = $stripEmpty == true ? '-Empty-' : $stripEmpty;
		$val = str_replace($emptyPattern, '', $val);
	}
	return trim($val);
}

function getLine($strm) {
	global $line, $lineNum, $rawLine;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$rawLine = $line;
	$line = trim($line);
	return true;
}


