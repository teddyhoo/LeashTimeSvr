<? // import-sitters-petsitconnect.php
set_time_limit(5);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "provider-fns.php";
locked('o-');
extract($_REQUEST);

if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db</h3>";
else echo "<h2 style='color:orange;'>DB: $db</h2>";
if(!$go) echoButton('', 'Process These Sitters', 'go()');
echo "<hr>";

$strm = fopen("/var/data/clientimports/$file", 'r');
echo "<table><tr><td>employeeid<td>name<td>homephone<td>cellphone<td>email";
while(getLine($strm)) {
//echo "<font color=green>".htmlentities($line)."</font><br>";
	if(strpos($line, ($pat = 'profile.php?id=')) !== FALSE && strip_tags($line)) {
		if($prov && $prov['employeeid']) {
			$provs = $prov;
			//echo htmlize(print_r($prov, 1));
			$color = $prov['exists'] ? "style='color:green'" : '';
			echo "<tr><td $color>{$prov['employeeid']}<td>{$prov['name']}<td>{$prov['homephone']}<td>{$prov['cellphone']}<td>{$prov['email']}";
			if($go) {
				$p = $prov['exists'];
				if(!$p) {
					// create provider
					saveNewProviderWithData($prov);
					echo "<td>CREATED";
				}
				else {
					echo "<tr><td>Updated: ";
					foreach(explode(',', 'employeeid,homephone,cellphone,email') as $key)
						if(!$p[$key] && $prov[$key]) {
							updateTable('tblprovider', array($key=>$prov[$key]), "providerid = {$p['providerid']}", 1);
							echo "$key=>{$prov[$key]} ";
						}
				}
					
			}
		}
		$prov = array('employeeid'=>trim(getAfter($line, "id=", $terminator="&")), 'name'=>trim(strip_tags($line)));
		handleFnameSpaceLname($prov['name'], $prov);
		if($p = findProviderByName($prov['name'])) $prov['exists'] = $p;
	}
	else if(strpos($line, ($pat = 'mailto')) !== FALSE) $prov['email'] = trim(strip_tags($line));
	else if(strpos($line, ($pat = 'Delete</a></div></td>')) !== FALSE) {
		$prov['homephone'] = trim(strip_tags(substr($line, strpos($line, $pat)+strlen($pat))));
	}
	else if($lastLineWasDelete) $prov['cellphone'] = trim(strip_tags($line));
	$lastLineWasDelete = strpos($line, 'Delete</a></div></td>') !== FALSE;
}
echo "</table>";

function findProviderByName($nn) {
	return fetchFirstAssoc("SELECT * FROM tblprovider WHERE CONCAT_WS(' ', fname, lname) = '".mysqli_real_escape_string($nn ? $nn : '')."' LIMIT 1");
}



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

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(' ', $str));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
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

function getAfter($line, $after, $terminator) {
	$linestart = strpos($line, $after)+strlen($after);
	$lineend = strpos($line, $terminator, $linestart);
	return substr($line, $linestart, $lineend-$linestart);
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
	document.location.href='import-sitters-petsitconnect.php?go=1&file=<?= $file ?>';
}

<?
if($foundPetTypes) echo "document.getElementById('pettypes').innerHTML = '<b>Found: </b>".join(', ', $foundPetTypes)."</b><p><b>Saved List: </b>".join(', ', (array)$petTypes)."';";
?>
</script>