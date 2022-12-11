<? // import-petsitclick-pets.php
// METHOD: Show All Pets.  Select All.  View Source.  Save File
// All pets are on one line

set_time_limit(5);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
require_once "pet-fns.php";
locked('o-');
extract($_REQUEST);

$action = $_REQUEST['go'] ? 'Updating' : "Analyzing (use 'go' to update)";
if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db</h3>";
else echo "<h2 style='color:orange;'>$action DB: $db</h2>";

if(!$_REQUEST['go']) {
	echoButton('', 'Go!', "document.location.href=\"import-petsitclick-pets.php?go=1&file=$file\"");
	echo "<p>";
}

$strm = fopen("/var/data/clientimports/$file", 'r');
while(getLine($strm)) {
//echo "<font color=green>".htmlentities($line)."</font><br>";
	if(strpos($line, ($pat = 'CUSTOMER_NAME_1">')) !== FALSE) {
		//$start = startAfterPat($line, $pat);
		//$len = strpos($line, '<', $start) - $start;
		//$name = html_entity_decode(substr($line, startAfterPat($line, $pat), $len));
		$chunks = explode("</td>", $line);
		if(count($chunks) > 3) {
			//print_r($chunks);
			for($i=3; $i < count($chunks); $i += 13) {
				// each pet has 13 chunks
				$name = trim(htmlspecialchars_decode(strip_tags($chunks[$i+2])));
				if(0+($id = findClientNamed($name))) {
					$pet = array(
						'name'=>trim(htmlspecialchars_decode(strip_tags($chunks[$i+4]))),
						'type'=>trim(htmlspecialchars_decode(strip_tags($chunks[$i+6]))),
						'breed'=>trim(htmlspecialchars_decode(strip_tags($chunks[$i+8])))
						);
					$existingpets = getClientPetNames($id, $inactiveAlso=1);
					if($existingpets) echo "Found that $name! (".print_r($id, 1).") already has pets: ".print_r($existingpets, 1)." <font color=red>{$pet['name']} ignored</font><br>";
					else {
						if(!$_REQUEST['go']) echo "[".print_r($pet, 1)."] Found $name! ".print_r($id, 1)."<br>";
						else {
							$pet['ownerptr'] = $id;
							insertTable('tblpet', $pet, 1);
							echo "[".print_r($pet, 1)."] added to client $name! ".print_r($id, 1)."<br>";
						}
					}
				}
				else {
					echo "<font color=red>$id</font><br>";
					continue;
				}
				//echo "$i: ".strip_tags($chunk)."<br>";
			}
		}
	}
}

function startAfterPat($line, $pat) {
	return strpos($line, $pat)+strlen($pat);
}

function findClientNamed($name) {
	$mname = mysqli_real_escape_string($name);
	$found = fetchCol0("SELECT clientid FROM tblclient WHERE CONCAT_WS(' ', fname, lname) = '$mname'");
	if(count($found) > 1) return "<font color=red>Multiple clients named $name</font>";
	else if(!$found) return "<font color=red>Found no client named $name</font>";
	return $found[0];
}

function findSitterNamed($name) {
	$mname = mysqli_real_escape_string($name);
	$found = fetchCol0("SELECT providerid FROM tblprovider WHERE CONCAT_WS(' ', fname, lname) = '$mname'");
	if(count($found) > 1) return "<font color=red>Multiple providers named $name</font>";
	else if(!$found) return "<font color=red>Found no provider named $name</font>";
	return $found[0];
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

