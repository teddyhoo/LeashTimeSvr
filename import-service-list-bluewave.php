<?// import-service-list-bluewave.php

/*

This script parses a Bluewave Item List page.

Sample: woofies/w_itm_lst.cfm.htm

http://iwmr.info/petbizdev/import-service-list-bluewave.php?file=woofies/w_itm_lst.cfm.htm



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

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "gui-fns.php";
include "client-services-fns.php";

$locked = locked('o-');

extract($_REQUEST);

if($auto) $file = "{$_SESSION['bizptr']}/itemlist.htm";

$clientServiceSelections = getClientServices(); // clientServiceLabel=>serviceTypeId

$serviceTypes = 
 fetchAssociationsKeyedBy("SELECT * FROM tblservicetype ORDER BY active desc, menuorder, label", 'servicetypeid');
foreach($serviceTypes as $type) $serviceTypesByName[$type['label']] = $type;

if($_POST && $action == 'save') {
	$clientServiceCount = count($clientServiceSelections);
	foreach($_POST as $key => $val)	{
		if($val && strpos($key, 'cb_') === 0) {
			$n = substr($key, 3);
			if(isset($serviceTypesByName[$internal = stripslashes($_POST["internal_$n"])])) {
				$errors[] = "Service [{$_POST["internal_$n"]}] already exists.";
				continue;  // don't duplicate service labels!
			}
			if(isset($clientServiceSelections[$external = stripslashes($_POST["external_$n"])])) {
				$errors[] = "Client Service [{$_POST["external_$n"]}] already exists.";
				continue;  // don't duplicate client service labels!
			}
			$service = array(
				'label'=>$internal,
				'defaultrate'=>(is_numeric($_POST["compfactor_$n"]) ? $_POST["compfactor_$n"] : '0.0'),
				'ispercentage'=>$_POST["percentage_$n"] ? 1 : '0', 
				'defaultcharge'=>(is_numeric($_POST["charge_$n"]) ? $_POST["charge_$n"] : '0.0'), 
				'taxable'=>$_POST["taxable_$n"] ? 1 : '0', 
				'active'=>1);
			$serviceId = insertTable('tblservicetype', $service, 1);
			//clientServiceLabel, serviceTypeId, description
			$pref = $external
								. '|'.$serviceId.'|'
		        		. $service['label'];
		  $clientServiceCount++;
			setPreference("client_service_$clientServiceCount", $pref);
			$messages[] = "Service [{$_POST["internal_$n"]}] created.";
		}
	}
	if($messages) $messages[] = "View <a href='service-types.php'>Service List</a>";
	$stopAfterMessages = true;
}

include "frame-bannerless.php";
echo "<h2>Bluewave Item List</h2>";

if($errors) {
	echo "<font color=red><ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
}

if($messages) {
	echo "<font color=green><p>";
	foreach($messages as $message) echo "$message<br>";
	echo "</font>";
}

if($stopAfterMessages) exit;

if(!$file && !$pagehtml) { ?>
<form method='POST'>
<input type=submit><br>
<span style='font:bold 1.1em Arial;'>Paste the Item List HTML here:</span><br>
<textarea cols=120 rows=20 name='pagehtml'></textarea>
</form>
<?
exit;
}

function serviceLabelFlag($label) {
	global $serviceTypesByName;
	if($serviceTypesByName[$label])
		return "<span style='color:yellow;background:black;font-weight:bold;' title='A service called [$label] already exists.'>@@@</span>";
}

function clientServiceLabelFlag($label) {
	global $clientServiceSelections;
	if($clientServiceSelections[$label])
		return "<span style='color:yellow;background:black;font-weight:bold;' title='A client service label [$label] already exists.'>@@@</span>";
}


if($file || $pagehtml) {

	if($file) {
		$file = "/var/data/clientimports/$file";
		$strm = fopen($file, 'r');
	}
	else if($_POST['pagehtml']) {
		$stripSlashesFromLine = true;
		$strm = fopen('data://text/plain,' . $_POST['pagehtml'], 'r');
	}



	while(getLine($strm)) {
//$n++;if(strlen($line)) echo "LINE $n: ".strlen($line)."[$line]$started<br>\n";
		if(!$started && trim($line) == '<th><div align="center">Taxable</div></th>') {
//echo "BANG!";exit;			
			getLine($strm); // eat the /tr
			$started = "STARTED";
		}
//  WHY DOESN'T THE STRING STREAM WORK?  EOLS?

if(strpos($line, 'Records Total')) break;
		if($started) {
			if(!($item = getItem($strm))) break;
			else $items[$item['code']] = $item;
		}
	}

	fclose($strm);

	$columns = explodePairsLine('cb|&nbsp;||code|Code||internal|Manager Label||'
															.'charge|Price||percentage|%||compfactor|Rate||taxable|Taxable||external|Client Label');
	foreach($items as $item) {
		$n++;
		$percentage = $item['compmethod'] == 'Percentage' ? 'checked' : '';
		$taxable = $item['taxable'] ? 'checked' : '';
		$charge = strpos($item['charge'], '$') === 0 ? substr($item['charge'], 1) : $item['charge'];
		$rate = strpos($item['compfactor'], '$') === 0 ? substr($item['compfactor'], 1) : $item['compfactor'];
		if(is_numeric($rate)) $rate = sprintf('%.2f', $rate);
		$rows[] =
			array('#CUSTOM_ROW#'=>
			"<tr id='row_$n' style=''><td><input type=checkbox name='cb_$n' id='cb_$n' onclick='boxclicked(\"row_$n\", \"cb_$n\")'><td><label for='cb_$n'>{$item['code']}</label>
			<td>"
			.serviceLabelFlag($item['internal'])
			."<input name='internal_$n' id='internal_$n' value='{$item['internal']}' size=35>
			<td><input name='charge_$n' id='charge_$n' value='$charge' size=7 style='text-align:right'>
			<td><input type=checkbox name='percentage_$n' id='percentage_$n' $percentage>
			<td><input name='compfactor_$n' id='compfactor_$n' value='$rate' size=7 style='text-align:right'>
			<td><input type=checkbox name='taxable_$n' id='taxable_$n' $taxable>"
			.clientServiceLabelFlag($item['external'])
			."<td><input name='external_$n' id='external_$n' value='{$item['external']}' size=35>");
	}
	fauxLink('Select All', 'selectAll(1)');
	echo ' ';
	fauxLink('Clear All', 'selectAll(0)');
	echo ' ';

	echoButton('', 'Create Service Types', 'createServiceTypes()');
	echo ' from the selected items.';
	echo "<form method='POST' name='createform'>";
	tableFrom($columns, $rows, '',null,null,null,null,null,$rowClasses);
	hiddenElement('action', 'save');
	echo "</form>";
	foreach($serviceTypesByName as $label => $x) $existingServiceLabels[] = '"'.$label.'"';
	$existingServiceLabels = join(', ', $existingServiceLabels);
	
	foreach($clientServiceSelections as $label => $x) $existingClientServiceLabels[] = '"'.$label.'"';
	$existingClientServiceLabels = join(', ', $existingClientServiceLabels);
}
?>
<script language='javascript'>

var existingServiceLabels = [<?= $existingServiceLabels ?>];
var existingClientServiceLabels = [<?= $existingClientServiceLabels ?>];

function boxclicked(row, cb) {
	document.getElementById(row).style.backgroundColor = document.getElementById(cb).checked ? 'palegreen' : 'white';
}

function createServiceTypes() {
	var numSelected = 0;
	var errors = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('cb_') == 0 && els[i].checked) {
			var n = els[i].id.substring(3);
			var internal = document.getElementById('internal_'+n).value;
			var external = document.getElementById('external_'+n).value;
			numSelected++;
			for(var x = 0; x < existingServiceLabels.length; x++)
				if(internal ==  existingServiceLabels[x]) errors.push("A service with the label ["+internal+"] already exists.");
			for(var x = 0; x < existingClientServiceLabels.length; x++)
				if(external ==  existingClientServiceLabels[x]) errors.push("A client service with the label ["+external+"] already exists.");
		}
	if(!numSelected) errors.push("You must select at least one item.");
	if(errors.length > 0) {
		alert("ERROR\n- "+errors.join("\n- "));
		return;
	}
	document.createform.submit();
}

function selectAll(onoff) {
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('cb_') == 0) {
			els[i].checked = onoff;
			var n = els[i].id.substring(3);
			boxclicked('row_'+n, els[i].id);
	}
}


</script>
<?	

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
	$item['internal'] = trim(nextTDStripped($strm));
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
	global $line, $col, $lineNum, $stripSlashesFromLine;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$line = trim($line);
	if($stripSlashesFromLine) $line = stripslashes($line);
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
	return trim(strip_tags(str_replace('&nbsp;', ' ', $td)));
}
	
	
function nextLineStripped($strm) {
	global $line;
	if(!getLine($strm)) return FALSE;
	$line = trim($line);
	return strip_tags($line);
}
