<? // import-pops-keyaudit.php

// 		<td>Client</td><td>Key Location</td><td>Primary</td><td>Secondary</td><td>Alternate 1</td><td>Alternate 2</td><td>Alternate 3</td>
//	</tr><tr class="data_items">
//		<td style="width:200px;"><a href="clients_addedit.aspx?id=103798">Aaron Segall</a></td><td style="width:350px;">&nbsp;</td><td style="width:200px;"><a href="staff_addedit.aspx?id=9366">Teresa Spittler</a></td><td style="width:200px;"><a href="staff_addedit.aspx?id=8680">Alex Graff</a></td><td style="width:200px;"><a href="staff_addedit.aspx?id=9962">Jennifer Kreps</a></td><td style="width:200px;"><a href="staff_addedit.aspx?id=11530">Stefanie Buzzetta</a></td><td style="width:200px;"><a>N/A</a></td>
set_time_limit(5);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
require_once "item-note-fns.php";
locked('o-');
extract($_REQUEST);

$action = $_REQUEST['go'] ? 'Updating' : "Analyzing (use 'go' to update)";
if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db</h3>";
else echo "<h2 style='color:orange;'>$action DB: $db</h2>";

if(!$_REQUEST['go']) {
	echoButton('', 'Go!', "document.location.href=\"import-pops-keyaudit.php?go=1&file=$file\"");
	echo "<p>";
}

$strm = fopen("/var/data/clientimports/$file", 'r');
while(getLine($strm)) {
//echo "<font color=green>".htmlentities($line)."</font><br>";
	if(strpos($line, ($pat = '<td style="width:200px;"><a href="clients_addedit.aspx?id=')) !== FALSE) {
		$chunks = explode('</td>', $line);
		$keyDesc = array();
		foreach(($chunks = explode('</td>', $line)) as $i => $chunk) {
			if($i == count($chunks) - 2) break;
			else if($i == 1) continue;
			$name = strip_tags($chunk.'</td>');
			if($i == 0) $keyDesc['client'] = $name;
			else if($name == 'N/A') continue;
			else $keyDesc[$i-1] = $name;
		}
		$client = findClientNamed($keyDesc['client']);
		if(!is_numeric($client)) {
			echo $client.'<p>';
			continue;
		}
		else {
			$key = fetchFirstAssoc("SELECT keyid, clientptr FROM tblkey WHERE clientptr = $client LIMIT 1");
			echo "Found client {$keyDesc['client']} ($client) (key: {$key['keyid']})";
		}
		if(!$key) $key = array();
		$poss = 0;
		for($i=1; $keyDesc[$i]; $i++) {
			$sitter = findSitterNamed($keyDesc[$i]);
			if(!is_numeric($sitter)) echo $sitter;
			else {
				echo " {$keyDesc[$i]} ($sitter)";
				$poss += 1;
				$key["possessor$poss"] = $sitter;
			}
		}
		$key['copies'] = $poss;
		$key['clientptr'] = $client;
		$poss++;
		$poss = max(1, $poss);
		for(; $poss <= 10; $poss++) $key["possessor$poss"] = sqlVal('null');
		if($_REQUEST['go']) {
			if($key['keyid']) updateTable('tblkey', $key, "keyid = {$key['keyid']}", 1);
			else insertTable('tblkey', $key, 1);
		}
		echo '<p>';
	}
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
	$found = fetchCol0($sql ="SELECT providerid FROM tblprovider WHERE CONCAT_WS(' ', fname, lname) = '$mname'");
//if(trim($name)) { echo 	"$sql<br>[$name] => ($mname)<br>[".print_r($found,1)."]" ;exit;}
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

