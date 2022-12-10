<?// import-item-list-bluewave.php

/*

This script parses a Bluewave Item List page.

Sample: woofies/w_itm_lst.cfm.htm.htm

http://iwmr.info/petbizdev/import-item-list-bluewave.php?file=woofies/w_itm_lst.cfm.htm.htm



		<tr bgcolor= "White" onMouseOver="this.style.backgroundColor='#FFcc00'" onMouseOut="this.style.backgroundColor='White'">
			<td class="navButts"><div align="center"><a href="w_itm_dtl.cfm?id=39" >&nbsp;Edit&nbsp;</a></div></td>
			<td><div align="left">ALB1 </div></td>
			<td><div align="left">Additional Litter Box </div></td>
			<td><div align="left">&nbsp;Additional LItter Box (100)&nbsp;</div></td>
			<td><div align="right">&nbsp;$2.00&nbsp;</div></td>
			<td><div align="right">&nbsp;
							
								Percentage
								
				&nbsp; </div></td>
			<td><div align="right">
				
&nbsp;      100.0000&nbsp;

			</div></td>
			
			<td><div align="right">&nbsp;601&nbsp;</div></td>
			<td><div align="center">&nbsp;A&nbsp;</div></td>
			<td align="center"> </td>
			<td align="center"></td>
			<td><div align="center"> </div></td>
		</tr>

*/
set_time_limit(5);

extract($_REQUEST);

$file = "/var/data/clientimports/$file";
$strm = fopen($file, 'r');


//echo "<hr>";

while(getLine($strm)) {
	if(!$started && $line == '<th><div align="center">Taxable</div></th>') {
		getLine($strm); // eat the /tr
		$started = true;
	}
	if(strpos($line, 'Records Total')) break;
	if($started) {
		if(!($item = getItem($strm))) break;
		else $items[$item['code']] = $item;
		//print_r($item);
		//echo '<hr><p>';
	}
}
$x = false;
foreach($items as $item) {
	if($x) echo ",";
	$x = true;
	echo "{$item['code']},{$item['internal']}";
}
	//echo "UPDATE tblhistoricaldata SET servicelabel='{$item['internal']}' WHERE servicepaymentcode = '{$item['code']}';<br>";

fclose($strm);

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

function getItem($strm) {
	global $line, $test, $col, $lineNum;
	$item = array();
	
	while(getLine($strm) && strpos($line, '<tr') === FALSE) ;  // skip TR
	if(strpos($line, '<tr') === FALSE) return null;
	while(!($td = trim(nextTDStripped($strm)))) ;
	// ignore Edit button
	//echo "TD: [$td]<br>";
	$item['code'] = trim(nextTDStripped($strm));
	$item['external'] = trim(nextTDStripped($strm));
	$item['internal'] = trim(str_replace('&nbsp;', ' ', nextTDStripped($strm)));
	$item['charge'] = trim(nextTDStripped($strm));
	$item['compmethod'] = trim(nextTDStripped($strm));
	$item['compfactor'] = trim(nextTDStripped($strm));
	$item['sort'] = trim(nextTDStripped($strm));
	$item['status'] = trim(nextTDStripped($strm));
	$item['visit'] = trim(nextTDStripped($strm));
	$item['poac'] = trim(nextTDStripped($strm));
	$item['taxable'] = trim(nextTDStripped($strm));
	return $item;
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
	
	
function nextLineStripped($strm) {
	global $line;
	if(!getLine($strm)) return FALSE;
	$line = trim($line);
	return strip_tags($line);
}
