<?// import-petsitters-resource-sitters.php

/*

This script parses the text obtained by Ctrl-A, Ctrl-V on a single client's account review in BettWalka.

Sample: S:\clientimports\BettaWalkaClientSample.txt
https://leashtime.com/import-one-bettawalka-client-and-pets.php?file=xxxxx

BEFORE USING THIS SCRIPT:

See https://bdw.bettawalka.com/customfields.do (Business > Administration > Custom Fields) and adjust initializeBettaWalkaPetCustomFields accordingly.

*/
require_once "gui-fns.php";
require_once "custom-field-fns.php";
require_once "provider-fns.php";
require_once "custom-field-fns.php";


function decompose($str, $delim) {
	return array_map('trim', explode($delim, $str));
}


function processEmails($trimval, &$prov) { // handles multiple emails
	if(!$trimval) return;
	foreach(decompose($trimval, "\n") as $x) {
		foreach(decompose($x, ",") as $x1) {
			foreach(decompose($x1, "&") as $x2) {
				foreach(decompose($x1, ";") as $x3) {
					if($x3)	{
						$emails[] = $x3;
					}
				}
			}
		}
	}
	foreach($emails as $email) {
		if(!isEmailValid($email))
			$badEmails[] = $email;
		else if(!$prov['email']) $prov['email'] = $email;
		else if(!$prov['email2']) $prov['email2'] = $email;
		else $prov['notes'][] = "Other email: $email";
	}
	return $badEmails;
}

$fieldMap = 
trim("
First Name=fname
Last Name=lname
Address=street1
City=city
State=state
Zip Code=zip
Home Phone=homephone
Work Phone=workphone
Cell Phone=cellphone
E-Mail Address=email
E-Mail Distribution Enabled=pref|YesNoNot|optOutMassEmail
Start Date=date|hiredate
Rate=+notes
End Date=date|terminationdate
Enabled=yesNo|active
Notes=+notes
"
);


foreach(explodePairPerLine($fieldMap, $sepr='=') as $field => $disposition)
	$arr[$field] = $disposition;
$fieldMap = $arr;

function importSitters($showUnhandled=false) {
	global $fieldMap, $line;
	extract($_REQUEST);
	ob_start();
	ob_implicit_flush(0);

	if($file) $strm = fopen("/var/data/clientimports/$file", 'r');
	else $strm = fopen("data://text/plain,$sitterdata" , 'rb');
	while(getLine($strm)) {
//echo "<hr>$line";		
		if(strpos($line , 'Pet Sitter Info') === 0) {
			if($provider) {
				foreach($provider as $k=>$v)
					if($v && is_array($v))
						$provider[$k] = join("\n", $v);
				$providerId = saveNewProviderWithData($provider);
			}
			$provider = array();
			continue;
		}
//echo "<hr>daMap: ".print_r($daMap, 1);			
		foreach($fieldMap as $k => $disp) {
//echo "<hr>petMode [$petMode] Disp: $disp";		
//if($disp == 'fname' || $disp == 'lname') echo "<hr>Pet: ".print_r($pet, 1)." Client: [".print_r($client, 1).']';
			if(strpos($line , $k) === 0) {
				if(strpos($disp, '--') === 0) continue;
				$v = trim(substr($line, strlen($k)));
				if(!$v) continue;
				if(strpos($disp, '+') === 0) {
					$provider[substr($disp, 1)][$k] = "$k: $v";
				}
				else if(strpos($disp, 'date|') === 0) {
					$disp = explode('|', $disp);
					$provider[$disp[1]] = date('Y-m-d', strtotime($v));
				}
				else if(strpos($disp, 'yesNo|') === 0) {
					$disp = explode('|', $disp);
					$provider[$disp[1]] = $v == 'Yes' ? '1' : '0';
				}
				else if($disp == 'email') processEmails($v, $provider);
				else $provider[$disp] = $v;
			}
		}
	}
	if($provider) {
		foreach($provider as $k=>$v)
			if($v && is_array($v))
				$provider[$k] = join("\n", $v);
		$providerId = saveNewProviderWithData($provider);
	}
}
	
function getLine($strm) {
	global $line, $lineNum, $rawLine;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$rawLine = $line;
	$line = trim($line);
	return true;
}


function findSitter($name) {
	$providerid = fetchRow0Col0($sql = "SELECT providerid 
															FROM tblprovider 
															WHERE CONCAT_WS(' ', fname, lname) = '".mres($name)."' LIMIT 1");
	if(!$providerid) {
		redline("Could not find sitter: [$name]");
		//redline($sql);
	}
	//else redline("Found [$name]=$providerid");
	return $providerid;
}
