<?// import-clients-and-pets-by-vet-pops.php

/*
UNDER CONSTRUCTION 11/28/2010

This script parses a Bluewave Sitter Detail Report, .

Sample: S:\clientimports\waggingtail\Wagging-Tails-Sitter-1.htm
https://leashtime.com/import-clients-and-pets-by-vet-pops.php?file=ksrpetcare/rpt_clientsByVet.aspx.htm

*/
set_time_limit(5);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
require_once "item-note-fns.php";
locked('o-');
extract($_REQUEST);

if($_SESSION['preferences']['petTypes']) foreach(explode('|', $_SESSION['preferences']['petTypes']) as $type) $petTypes[strtolower($type)] = $type;

if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db</h3>";
else echo "<h2 style='color:orange;'>DB: $db</h2>";
if(!$go) echoButton('', 'Process These Pets', 'go()');
echo "<hr>";
echo "<div id='pettypes'></div>";

$strm = fopen("/var/data/clientimports/$file", 'r');
while(getLine($strm)) {
//echo "<font color=green>".htmlentities($line)."</font><br>";
	if(strpos($line, ($pat = 'admin/vets.aspx?id=')) !== FALSE
	   || strpos($line, ($pat = 'vets.aspx?id=')) !== FALSE) {
		$vetLabel = getContents($line, $pat);
		if($vetLabel) {
			$clinics = fetchAssociations("SELECT * FROM tblclinic WHERE clinicname = '".mysql_real_escape_string($vetLabel)."'");
			if(!$clinics) echo "<font color=red>Clinic [$vetLabel] not found.<br></font>";
			else if(count($clinics) > 1)  echo "<font color=red>Clinic name [$vetLabel] refers to ".count($clinics)." clinics.<br></font>";
			if(count($clinics) == 1) {
				$currentClinic = $clinics[0];
				echo "<p>Found clinic [$vetLabel] [{$currentClinic['clinicid']}]<br>";
			}
			else $currentClinic = null;
		}
	}
	if(strpos($line, ($pat = 'admin/clients_addedit.aspx')) !== FALSE
		 || strpos($line, ($pat = 'clients_addedit.aspx')) !== FALSE) {
		$clientLabel = getContents($line, $pat);
		if($clientLabel) {
			$clients = fetchAssociations("SELECT * FROM tblclient WHERE CONCAT_WS(' ', fname, lname) = '".mysql_real_escape_string($clientLabel)."'");
			if(!$clients) echo "<font color=red>Client [$clientLabel] not found.<br></font>";
			else if(count($clients) > 1) echo "<font color=red>Client name [$clientLabel] refers to ".count($clients)." clients.<br></font>";
			$currentClient = count($clients) == 1 ? $clients[0] : null;
		}
		if($go && $currentClinic && $currentClient) {
			$officenotes = fetchRow0Col0("SELECT officenotes FROM tblclient WHERE clientid = {$currentClient['clientid']} LIMIT 1");
			$cclinics = explode(', ', trim($officenotes));
			if(!$cclinics[0]) unset($cclinics[0]);
			if(!in_array($currentClinic['clinicname'], $cclinics)) {
				$cclinics[] = $currentClinic['clinicname'];
				$officenotes = join(",\n", $cclinics);
			}
			if(itemNoteIsEnabled()) 
				updateNote(
						array('itemptr'=>$currentClient['clientid'], 
									'itemtable'=>'client-office',
									'priornoteptr'=>'0',
									'authorid'=>'0',
									'subject'=>"Associated Vet Clinics",
									'date'=>date('Y-m-d H:i:s')),
						$officenotes);
			updateTable('tblclient', 
										array('clinicptr'=>$currentClinic['clinicid'], 'officenotes' => $officenotes), 
														"clientid = {$currentClient['clientid']}", 1);
			$updated = $go && $currentClinic ? "<font color=green>UPDATED => {$currentClinic['clinicid']}</font>" : '';
		}			
	}
	if(strpos($line, ($pat = 'admin/pets.aspx?')) !== FALSE
		 || strpos($line, ($pat = 'pets.aspx?')) !== FALSE) {
		$currentPet = array('name'=>getContents($line, $pat), 'ownerptr'=>$currentClient['clientid']);
		$ignored = $currentClient ? '' : "<font color=red> (ignored)</font>";
		echo "Found pet [{$currentPet['name']}]$ignored<br>";
		if($ignored) $currentPet = null;
	}
	else if(strpos($line, ($pat = '</td><td style="width: 100px;">')) !== FALSE
					|| strpos($line, ($pat = '</td><td style="width:100px;">')) !== FALSE) {
		if($currentPet) {
			$petType = getContents($line, $pat);
			$petType = strip_tags($petType);
			$foundPetTypes[$petType] = $petType;
			$petType = strtolower($petType);
			$currentPet['type'] = $petTypes[$petType] ? $petTypes[$petType] : $petType;
			$petType = $currentPet['type'];
			$created = "";
			if($go) {
				if(fetchAssociations(
					"SELECT name 
						FROM tblpet 
						WHERE ownerptr = {$currentPet['ownerptr']}
							AND name = '".mysql_real_escape_string($currentPet['name'])."'"))
				  $created = "<font color=red>ALREADY EXISTS</font>";
				else {
					insertTable('tblpet', $currentPet, 1);
					$created = "<font color=green>CREATED</font>";
				}
			}
			echo "... and it is a $petType $created<br>";
		}
	}
	//else echo "<font color=gray>".htmlentities($line)."</font><br>";
}

/*
$clinicNames = fetchCol0("SELECT clinicname FROM tblclinic");
foreach($vets as $i => $vet) {
	if(!$vet['name']) continue;
	if(in_array($vet['name'], $clinicNames)) {
		echo "<font color=red>Clinic {$vet['name']} already exists.<br></font>";
		continue;
	}
	$clinic = array('clinicname'=>$vet['name'],
									'officephone'=>$vet['phone1'],
									'cellphone'=>$vet['phone2'],
									'street1'=>$vet['street1'],
									'street2'=>$vet['street2'],
									'city'=>$vet['city'],
									'state'=>$vet['state'],
									'zip'=>$vet['zip']);
	insertTable('tblclinic', $clinic, 1);
	if(!mysql_error()) {
		echo "Vet #".mysql_insert_id()." [{$vet['name']}] added.<br>";
		$n++;
	}
}
echo "$n vets added.";
*/

function getTextAreaContents($line) {
	if(($start = strpos($line, '<textarea')) !== FALSE) {
		$start = strpos($line, '>', $start)+1;
		if($end = strpos($line, '</textarea', $start))
			return substr($line, $start, $end-$start);
		else return substr($line, $start);
	}
	else if($end = strpos($line, '</textarea') === FALSE)
		return "\n".substr($line, $start, $end-$start);
	else return "\n".$line;
}

function getContents($line, $after) {
	if(($start = strpos($line, $after)) !== FALSE) {
		$start = strpos($line, '>', $start)+1;
		if($end = strpos($line, '</', $start))
			return substr($line, $start, $end-$start);
		else return substr($line, $start);
	}
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
	global $line, $col, $lineNum;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$line = trim($line);
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
	return trim(strip_tags($td));
}

function getValue($line, $after=null) {
	//$pattern = '/value=".*"/gi';
	
	if($after) {
		$linestart = strpos($line, $after)+strlen($after);
		$lineend = strpos($line, '>', $linestart);
		$line = substr($line, $linestart, $lineend-$linestart);
		//echo "<p style='color:red;'>[$linestart] [$lineend] [$line]</p>";
	}
	
	$start = strpos($line, 'value="');
	if($start === FALSE) return;
	$start += strlen('value="');
	$end = strpos($line, '"', $start);
	return substr($line, $start, $end-$start);
}
	
	
	
function nextLineStripped($strm) {
	global $line;
	if(!getLine($strm)) return FALSE;
	$line = trim($line);
	return strip_tags($line);
}

?>
<script language='javascript'>
function go() {
	document.location.href='import-clients-and-pets-by-vet-pops.php?go=1&file=<?= $file ?>';
}

<?
if($foundPetTypes) echo "document.getElementById('pettypes').innerHTML = '<b>Found: </b>".join(', ', $foundPetTypes)."</b><p><b>Saved List: </b>".join(', ', (array)$petTypes)."';";
?>
</script>
