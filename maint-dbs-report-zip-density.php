<? // maint-dbs-report-zip-density.php

function processBusinessZIP() {
	global $date, $biz, $db, $bizptr, $globalZips, $bizCount;
	$globalZips = $globalZips ? $globalZips : array();
	if($db != 'dogslife')
		if(!$biz['activebiz'] || $biz['test']) return;
	$sql = "SELECT zip,city,state,count(*) AS clients
						FROM tblclient
						WHERE zip IS NOT NULL
						GROUP BY zip";
	foreach(fetchAssociationsKeyedBy($sql, 'zip') as $zip => $row) {
		$found = 1;
		if(!$globalZips[$zip]) $globalZips[$zip] = $row;
		else {
			if($row['city'] && $globalZips[$zip]['city'] != $row['city']) {
				if(!$globalZips[$zip]['city']) $globalZips[$zip]['city'] = $row['city'];
				else {
					$parts = array_map('trim', array_map('strtoupper',  explode('/', $globalZips[$zip]['city'])));
					if(!in_array(trim(strtoupper($row['city'])), $parts)) $globalZips[$zip]['city'] .= '/'.$row['city'];
				}
			}
			$globalZips[$zip]['clients'] += $row['clients'];
		}
	}
	if($found) $bizCount += 1;
}

function processBusiness() {
	global $mode;
	if($mode == 'zip') processBusinessZIP();
	else processBusinessCityState();
}

function processBusinessCityState() {
	global $date, $biz, $db, $bizptr, $globalZips, $bizCount, $mode;
	$globalZips = $globalZips ? $globalZips : array();
	if($db != 'dogslife')
		if(!$biz['activebiz'] || $biz['test']) return;
	$sql = "SELECT CONCAT_WS('|', TRIM(UPPER(city)),TRIM(UPPER(state))) as zip, city,state,count(*) AS clients
						FROM tblclient
						WHERE zip IS NOT NULL
						GROUP BY zip";
	foreach(fetchAssociationsKeyedBy($sql, 'zip') as $zip => $row) {
		$found = 1;
		if(!$globalZips[$zip]) $globalZips[$zip] = $row;
		else {
			if($row['city'] && $globalZips[$zip]['city'] != $row['city']) {
				if(!$globalZips[$zip]['city']) $globalZips[$zip]['city'] = $row['city'];
				else {
					$parts = array_map('trim', array_map('strtoupper',  explode('/', $globalZips[$zip]['city'])));
					if(!in_array(trim(strtoupper($row['city'])), $parts)) $globalZips[$zip]['city'] .= '/'.$row['city'];
				}
			}
			$globalZips[$zip]['clients'] += $row['clients'];
		}
	}
	if($found) $bizCount += 1;
}

function cmpclients($a, $b) {
	return $a['clients'] > $b['clients'] ? -1 : (
				 $a['clients'] < $b['clients'] ? 1 : 0);
}

function postProcess() {
	global $mode;
	if($mode == 'zip') postProcessZIP();
	else postProcessCityState();
}

function postProcessCityState() {
	global $bizCount, $globalZips;
	$tmpZips = array();
	foreach($globalZips as $zip => $row) {
		$tmpZips[$z = trim("$zip")] = $row;
		$tmpZips[$z]['zip'] = $z;
		$tmpZips[$z]['city'] = trim($tmpZips[$z]['city']);
		$tmpZips[$z]['state'] = trim($tmpZips[$z]['state']);
	}
	foreach((array)$unsets as $z) unset($tmpZips[$z]);
	
	$globalZips = array_merge($tmpZips);

	echo "Total businesses surveyed: $bizCount unique City/States found: ".count($globalZips)."<p>";
	usort($globalZips, 'cmpclients');;
	foreach($globalZips as $zip => $row) {
		dumpCSVRow($row);
		echo "<br>";
		}
}

function postProcessZIP() {
	global $bizCount, $globalZips;
	$tmpZips = array();
	foreach($globalZips as $zip => $row) {
		$tmpZips[$z = trim("$zip")] = $row;
		$tmpZips[$z]['zip'] = $z;
		$tmpZips[$z]['city'] = trim($tmpZips[$z]['city']);
		$tmpZips[$z]['state'] = trim($tmpZips[$z]['state']);
		if(!isValidUSZIP($z)) $notValidZIPs += 1;
		else {
			$validUSZIPs += 1;
			if(strlen("$z") > 5) {
				$zipPlus4 += 1;
				$tmpZips[substr($z, 0, 5)]['clients'] += $tmpZips[$z]['clients'];
				$unsets[] = $z;
			}
		}
	}
	foreach((array)$unsets as $z) unset($tmpZips[$z]);
	
	$globalZips = array_merge($tmpZips);

	echo "Total businesses surveyed: $bizCount ZIP codes found: ".count($globalZips)."<p>";
	echo "Valid US ZIPs: $validUSZIPs including $zipPlus4 ZIP+4 mode ZIPs (ZIP+4 codes aggregarated)<hr>";
	usort($globalZips, 'cmpclients');;
	foreach($globalZips as $zip => $row) {
		dumpCSVRow($row);
		echo "<br>";
		}
}

function isValidUSZIP($zip_postal, $country_code="US") {
	$ZIPREG=array(
	 "US"=>"^\d{5}([\-]?\d{4})?$",
	 "UK"=>"^(GIR|[A-Z]\d[A-Z\d]??|[A-Z]{2}\d[A-Z\d]??)[ ]??(\d[A-Z]{2})$",
	 "DE"=>"\b((?:0[1-46-9]\d{3})|(?:[1-357-9]\d{4})|(?:[4][0-24-9]\d{3})|(?:[6][013-9]\d{3}))\b",
	 "CA"=>"^([ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ])\ {0,1}(\d[ABCEGHJKLMNPRSTVWXYZ]\d)$",
	 "FR"=>"^(F-)?((2[A|B])|[0-9]{2})[0-9]{3}$",
	 "IT"=>"^(V-|I-)?[0-9]{5}$",
	 "AU"=>"^(0[289][0-9]{2})|([1345689][0-9]{3})|(2[0-8][0-9]{2})|(290[0-9])|(291[0-4])|(7[0-4][0-9]{2})|(7[8-9][0-9]{2})$",
	 "NL"=>"^[1-9][0-9]{3}\s?([a-zA-Z]{2})?$",
	 "ES"=>"^([1-9]{2}|[0-9][1-9]|[1-9][0-9])[0-9]{3}$",
	 "DK"=>"^([D-d][K-k])?( |-)?[1-9]{1}[0-9]{3}$",
	 "SE"=>"^(s-|S-){0,1}[0-9]{3}\s?[0-9]{2}$",
	 "BE"=>"^[1-9]{1}[0-9]{3}$"
	);

	if ($ZIPREG[$country_code]) return preg_match("/".$ZIPREG[$country_code]."/i",$zip_postal);
}

//$z= "94598-2214"; echo "$z is valid: ".isValidUSZIP($z);exit;

$mode = $_REQUEST['mode']; //'zip';
require_once "maint-dbs-report.inc.php";