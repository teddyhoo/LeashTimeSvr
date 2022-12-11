<? // import-pops-clients-by-vet.php
//This script parses a Bluewave Sitter Detail Report, line by line.

// populates tempClientMap, tempClinicMap
// creates pets
// associates vets with clients

/* 
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE
REDUNDANT - IGNORE



given rpt_clientsByVet.aspx.htm:
 1) find tr class="data_items"
 2) read next line
 	a) vet id = first, client id = second
 	b) if new vet find LT clinic where clinic name is the same
 	c) if new client, find LT client where "fname lname" == second td contents
 	d) if count(found vets) > 1 or if count(found vets) == 0 ERROR, continue
 3) read next line
 	pet name is between ">" and "<"
 4) read next line
 	a) pet type is between [100px;">] and "<"
 	b) find LT pet type where uppercase(LTtype) = found pet type
 5) if(client not yet in tempClientMap) set client's vet clinic, create pet for client
 6) if(new client) echo client name, https://www.powerpetsitter.net/admin/pets.aspx?clientid=107602
 */
 
set_time_limit(5);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";

locked('o-');
extract($_REQUEST);

foreach(explode('|', $_SESSION['preferences']['petTypes']) as $type) $petTypes[strtolower($type)] = $type;

doQuery("CREATE TABLE IF NOT EXISTS `tempClientMap` (
  `externalptr` int(11) NOT NULL,
  `clientptr` int(11) NOT NULL,
  PRIMARY KEY  (`externalptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
");

doQuery("CREATE TABLE IF NOT EXISTS `tempClinicMap` (
  `externalptr` int(11) NOT NULL,
  `clinicptr` int(11) NOT NULL,
  PRIMARY KEY  (`externalptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
");

if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db</h3>";
else echo "<h2 style='color:orange;'>DB: $db</h2>";
echo "<hr>";

$strm = fopen("/var/data/clientimports/$file", 'r');
while(getLine($strm)) {
	if(strpos($line, 'tr class="data_items"') !== FALSE
			&& (!$error
					|| )
		) {
		$pet = array();
		$state = 1;
		$error = 0;
	}
	else if($error) {
		echo "Skipping line...";
		continue;
	}
	else if($state == 1) {
		$state = 2;
		$thisVet = getIdAndName('vets.aspx?id=');
		if($thisVet['id'] != $lastVetId) {
			echo "<font color=blue><b>Vet:</b> {$thisVet['name']}</font><br>";	
			$currentLTClinic = fetchAssociations($sql = "SELECT * FROM tblclinic WHERE clinicname = '{$thisVet['name']}'", 1);
			if(count($currentLTClinic) > 1) {
				echo "Ambiguous vet: [{$thisVet['name']}<br>";
				$error = 1;
			}
			else if(count($currentLTClinic) == 0) {
				//echo "[$sql]<br>";
				echo "Unknown vet: {$thisVet['name']}<br>";
				$error = 1;
			}
			else $currentLTClinic = $currentLTClinic[0];

		}
		$lastVetId = $thisVet['id'];
		
		$thisClient = getIdAndName('clients_addedit.aspx?id=');
		if($thisClient['id'] != $lastClientId) {
			echo "<font color=blue><b>Client:</b> {$thisClient['name']}</font><br>";	
			$currentLTClient = fetchAssociations("SELECT * FROM tblclient WHERE CONCAT_WS(' ', fname, lname) = '{$thisClient['name']}'", 1);
			if(count($currentLTClient) > 1) {
				echo "Ambiguous client: {$thisClient['name']}<br>";
				$error = 1;
			}
			else if(count($currentLTClient) == 0) {
				echo "Unknown client: {$thisClient['name']}<br>";
				$error = 1;
			}
			else $currentLTClient = $currentLTClient[0];
			$lastClientId = $thisClient['id'];
		}
		$pet['ownerptr'] = $currentLTClient['clientid'];
	}
	else if($state == 2) {
		$state = 3;
		$start = strpos($line, ">")+1;
		$end = strpos($line, "<", $start);
		$pet['name'] = substr($line, $start, $end-$start);
//echo "<font color=blue>[$start][$end]</font><br>";	
	}
	else if($state == 3) {
		$state = 4;
		$start = strpos($line, '100px;">')+strlen('100px;">');
		$end = strpos($line, "<", $start);
		$type = substr($line, $start, $end-$start);
		$pet['type'] = $petTypes[strtolower($type)] ? $petTypes[strtolower($type)] : $type;

		echo "Found pet {$pet['name']} ({$pet['type']})";
		
		$petNames = fetchCol0($sql = "SELECT name FROM tblpet WHERE ownerptr = {$currentLTClient['clientid']}");
		if(in_array($pet['name'], $petNames))
			echo " but he already exists (associated with [{$thisClient['name']}])";
		else {
			echo " and associated him with client [{$thisClient['name']}]";
			insertTable('tblpet', $pet, 1);
		}
		
		$existingMappedClinic = fetchRow0Col0("SELECT clinicptr FROM tempClinicMap WHERE externalptr = $lastVetId LIMIT 1");
		if(!$existingMappedClinic) {
			insertTable('tempClinicMap', array('externalptr'=>$lastVetId, 'clinicptr'=>$currentLTClinic['clinicid']), 1);
		}
		$existingMappedClient = fetchRow0Col0("SELECT clientptr FROM tempClientMap WHERE externalptr = $lastClientId LIMIT 1");
		if(!$existingMappedClient) {
			updateTable('tblclient', array('clinicptr'=>$currentLTClinic['clinicid']), "clientid = {$currentLTClient['clientid']}", 1);
			insertTable('tempClientMap', array('externalptr'=>$lastClientId, 'clientptr'=>$currentLTClient['clientid']), 1);
			echo " linked to Vet: [{$currentLTClinic['clinicname']}]<br>";
		}
		echo " already linked to Vet: [{$currentLTClinic['clinicname']}]<br>";
		
	}
}

function getIdAndName($url) {
	global $line;
	$start = strpos($line, $url)+strlen($url);
	$end = strpos($line, '"', $start);
	$id = substr($line, $start, $end-$start);
	$start = $end+2;
	$end = strpos($line, '<', $start);
	$name = substr($line, $start, $end-$start);
//echo "<font color=red>[$id] [$name]</font><br>";	
	return array('id'=>$id, 'name'=>$name);
}
	

function getLine($strm) {
	global $line, $col, $lineNum;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$line = trim($line);
//if(strpos($line, 'Phone:') !== FALSE) {echo "[$lineNum] $line";;}
	if(strpos($line, '<tr') !== FALSE) $col = 0;
	if(strpos($line, '<td') !== FALSE) $col++;
	return true;
}

