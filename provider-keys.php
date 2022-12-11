<?
//provider-keys.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";

require_once "key-fns.php";

// Determine access privs
locked('+ka,+ki');
$right = keyManagementRight();

extract($_REQUEST);

}				

if(($right == 'ka' || $right == 'ki') && $_POST && isset($operation)) {
	if($operation == 'checkout') {
		$key = explode('-',$checkoutkey);
		transferKey((int)$key[0], (int)$key[1], $providerptr);
	}
	else if($operation == 'checkin') {
		$destination = $operation == 'lost' ? 'missing' : $safe;
		foreach($_POST as $key => $unused)
			if(strpos($key, 'key_') === 0) {
				$key = explode('-',substr($key, strlen('key_')));
}				
				transferKey((int)$key[0], (int)$key[1], $destination);
			}
	}
	else if($operation == 'transferKeys') {
		foreach($_POST as $key => $destination)
			if(strpos($key, 'transferKey_') === 0 && $destination) {
				$key = explode('-',substr($key, strlen('transferKey_')));
				transferKey((int)$key[0], (int)$key[1], $destination);
			}
	}
}

$pageTitle = $right == 'ka' ? "Sitter Keys" : $_SESSION["shortname"]."'s Keys";
include "frame.html";
// ***************************************************************************

if(!isSecureKeyEnabled()) {
	echo "Insufficient rights to use Key Management.";
	include "frame-end.html";
	exit;
}
?>
<form name='providerkeysform' method='POST'>
<?
hiddenElement('checkoutkey','');
$providerNames = getProviderShortNames();
if($right == 'ka') {
	$providerActivity = fetchKeyValuePairs("SELECT providerid, active FROM tblprovider");
	$providerLabels = $providerNames;
	asort($providerLabels);
	if(TRUE) {
		//$providerLabels = array_flip($providerLabels);
		foreach($providerLabels as $id => $label) {
			if($providerActivity[$id]) $active[$label] = $id;
			else $inactive[$label] = $id;
		}
		$providerLabels = array();
		if($inactive) {
			if($active) $providerLabels['Active Sitters'] = $active;
			$providerLabels['Inactive Sitters'] = $inactive;
		}
		else $providerLabels = $active;
	} 
	$providerLabels = array_merge(array('--'=>0), $providerLabels);
	//print_r($providerNames);
	labeledSelect('Select a sitter: ', 'providerptr', $providerptr, $providerLabels, null, null, 'showProviderKeys(this)');
	/*$safeset = fetchKeyValuePairs(
		"SELECT property, value
			FROM tblpreference 
			WHERE property LIKE 'safe%' AND value LIKE '%|1'
			ORDER BY property");
	*/

	if(adequateRights('ka')) {
		//$safeOptions['--'] = 0;
		//foreach(array_flip($safes) as $k=>$v) $safeOptions[$k] = $v;
		$safeOptions = array_merge(array('--'=>0), array_flip(getKeySafes($constrainedByRole=true)));
		echo "&nbsp;OR&nbsp;";
		labeledSelect('Select a safe: ', 'safeid', $safeid, $safeOptions, null, null, 'showProviderKeys(this)');
		//echo "<span class='tiplooks'> (beta)</span>";
	}
}
else {
	$providerptr = $_SESSION["providerid"];
	hiddenElement('providerptr', $providerptr);
}
if($safeid) {
	showSafeKeysTable($safeid);	
}
else if($providerptr) {
	hiddenElement('operation', '');
  $clientAppts = getNextAppointmentDatePerClientForProvider($providerptr);
  $clientIds = array_keys($clientAppts);
  //$clientIds = getActiveClientIdsForProvider($providerptr);
  $clients = getClientDetails($clientIds, array('sortname'));
  //$providerKeys = getProviderKeys($providerptr);
  $keyClientIds = array();
  $rows = array();
	$columns = explodePairsLine('cb|&nbsp;||key|Key ID||bin|Key Hook||client|Client||transferTo|Transfer To||copies|Copies');

	if($right != 'ka') unset($columns['transferTo']);
	
	unset($providerLabels[$providerptr]);
	foreach(getProviderKeys($providerptr) as $key) {
		$keyClientIds[] = $key['clientptr'];
		if(!isset($clients[$key['clientptr']])) {
		  $clients[$key['clientptr']] = getOneClientsDetails($key['clientptr']);
		}
		$row = array();
		$keyLabel = formattedProviderKeyId($key, $providerptr);
	  $row['cb'] = "<input type='checkbox' id='key_$keyLabel' name='key_$keyLabel'>";
	  $description = $key['description'] = $key['description'] ? '&nbsp;('.truncatedLabel($key['description'], 10).')' : '';
	  $row['key'] = keyLink($key, $providerptr).$description;
	  $row['bin'] = "<span style='padding:0px;' id={$key['bin']}>{$key['bin']}</span>";
	  $row['client'] = clientLink($key['clientptr']);
	  //function labeledSelect($label, $name, $value=null, $options=null, $labelClass=null, $inputClass=null, $onChange=null, $noEcho=false) 

	  $row['transferTo'] = labeledSelect('', "transferKey_$keyLabel", null, $providerLabels, null, null, null, true);
	  $row['copies'] = keyComment($key);
	  $rows[] = $row;
	}
	if(!$rows) echo "<p class='fontSize1_8em'>{$providerNames[$providerptr]} has no keys.</p>";
	else {
		$rows[] = array('transferTo'=>echoButton('','Transfer Keys to Designated Sitters', 'transferKeys()', null, null, true));

		echo "<p><table width=100%><tr><td>";
		fauxLink('Select All', 'checkAllKeys(true)');
		echo "</td><td>";
		fauxLink('Un-Select All', 'checkAllKeys(false)');
		echo "</td><td>";
		echoButton('','Report Selected Keys Lost', 'reportMissing()');
		echo "</td><td>";
		echoButton('','Check In Selected Keys', 'checkIn()');
		/*if(shouldEnforceAdminOnly()) {
			$adminOnlyKeySafes = getAdminOnlyKeySafes();
			foreach($safes as $safeKey => $label)
				if(!$adminOnlyKeySafes[$safeKey])
					$safeOptions[$safeKey] = $label;
		}
		else $safeOptions = $safes;
		
		if($safeOptions) $safeOptions = array_merge(array(0=>'-- Choose a Safe --'), $safeOptions);*/
		
		$safeOptions = array_merge(array('--'=>0), array_flip(getKeySafes($constrainedByRole=true)));


		selectElement(' to safe: ', 'safe', '', $safeOptions);

		echo "</td><tr></table><p>";
		echo "Generated: ".shortDateAndTime(time())."<p>";
		if(TRUE || mattOnlyTEST()) {
			$columnSorts = array('key'=>null, 'bin'=>null, 'client'=>null);
			$sortClickAction = 'sortKeys';
		}
//NEWtable From($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null)
		
		tableFrom($columns, $rows, 'width=100%', $class='providerkeystable', $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses=null, $sortClickAction);
	}
	foreach(array_keys($columns) as $i => $k) $providerkeysheadings[] = "$k:$i";
	$providerkeysheadings = join(', ', $providerkeysheadings);		
	echo "</form><p>";
	$missingClients = array_diff($clientIds, $keyClientIds);
	if($missingClients) {
		// identify clients where no key is required
		$keylessClients = fetchCol0("SELECT clientid FROM tblclient WHERE nokeyrequired = 1");
		$missingClients = array_diff($missingClients, $keylessClients);
	}
	if($missingClients) {
		if(shouldEnforceAdminOnly()) {
			$adminOnlyKeySafes = getAdminOnlyKeySafes();
			foreach($safes as $key => $label)
				if(!$adminOnlyKeySafes[$key])
					$availableSafes[$key] = $label;
		}
		else $availableSafes = $safes;

		echo "<h3>{$providerNames[$providerptr]} does not have keys for the following clients, but may need them:</h3>";
		$rows = array();
		$missingClientKeys = getClientGroupKeys($missingClients);
		foreach($missingClients as $client) {
			$availableKeys = array();
			$key = $missingClientKeys[$client];
			$bin = '';
			if($key) foreach($key as $field => $val) {
				if(strpos($field, 'possessor') === 0 && strpos($val, 'safe') === 0) {
					$keyidAndCopy = formattedKeyId($key['keyid'], substr($field, strlen('possessor')));
					$label = $keyidAndCopy.' - '.$safes[$val];
					if(!$availableSafes[$val]) {
						$label .= " (admin only)";
						$keyidAndCopy = "-1";
					}
					$availableKeys[$label] = $keyidAndCopy;
				}
				$bin = $key['bin'];
			}
			$action = null;
			if(!$availableKeys) $keyCell = missingKeyLink($client, $missingClientKeys);
			else {
//selectElement($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null) 
				$keyCell = selectElement('Available:', "key$client", '', $availableKeys, 'copyPicked(this)', null, null, 1);
				$action = echoButton('','Check Out', "checkOut(\"key$client\", $providerptr)", null, null, 1);
			}

			$rows[] =  
				array('client'=>clientLink($client), 
							'key'=>	$keyCell, 'action'=>$action, 'bin' => $bin, 'dateNeeded'=>shortDate(strtotime($clientAppts[$client])) );
		}
		$columns = explodePairsLine('client|Client||key|Key||bin|Key Hook||dateNeeded|Needed On||action|| ');
		usort($rows, 'sortByDateNeeded');
		tableFrom($columns, $rows, 'width=80%', $class='missingclientskeystable');
	}
}

function showSafeKeysTable($safeid) {
	global $providerLabels, $safes, $clients, $providerkeysheadings;
	$safeLabel = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = '$safeid' LIMIT 1");
	$safeLabel = substr($safeLabel, 0, strpos($safeLabel, '|'));
	
	hiddenElement('operation', '');
  $keyClientIds = array();
  $rows = array();
	$columns = explodePairsLine('cb|&nbsp;||key|Key ID||bin|Key Hook||client|Client||transferTo|Transfer To||copies|Copies');
	
	if(mattOnlyTEST()) {
		$columnSorts = array('key'=>null, 'bin'=>null, 'client'=>null);
		$sortClickAction = 'sortKeys';
	}

	//if($right != 'ka') unset($columns['transferTo']);
	
	foreach(getProviderKeys($safeid) as $key) {
		$keyClientIds[] = $key['clientptr'];
		if(!isset($clients[$key['clientptr']])) {
		  $clients[$key['clientptr']] = getOneClientsDetails($key['clientptr'], array('sortname', 'fullname'));
		}
		$row = array();
		$keyLabel = formattedProviderKeyId($key, $safeid);
	  $row['cb'] = "<input type='checkbox' id='key_$keyLabel' name='key_$keyLabel'>";
	  $description = $key['description'] = $key['description'] ? '&nbsp;('.truncatedLabel($key['description'], 10).')' : '';
	  $row['key'] = keyLink($key, $safeid).$description;
	  $row['bin'] = $key['bin'];
	  $row['client'] = clientLink($key['clientptr']);
	  $row['transferTo'] = labeledSelect('', "transferKey_$keyLabel", null, $providerLabels, null, null, null, true);
	  $row['copies'] = keyComment($key);
	  $rows[$clients[$key['clientptr']]['sortname']] = $row;
	}
	if(!$rows) echo '<p>'.$safeLabel.' has no keys.';
	else {
		ksort($rows);
		$rows[] = array('transferTo'=>echoButton('','Transfer Keys to Designated Sitters', 'transferKeys()', null, null, true));

		echo "<p><table width=100%><tr><td>";
		fauxLink('Select All', 'checkAllKeys(true)');
		echo "</td><td>";
		fauxLink('Un-Select All', 'checkAllKeys(false)');
		echo "</td><td>";
		echoButton('','Report Selected Keys Lost', 'reportMissing()');
		echo "</td><td>";
		echoButton('','Check In Selected Keys', 'checkIn()');
		
		/*
		if(shouldEnforceAdminOnly()) {
			$adminOnlyKeySafes = getAdminOnlyKeySafes();
			foreach($safes as $safeKey => $label)
				if(!$adminOnlyKeySafes[$safeKey])
					$safeOptions[$safeKey] = $label;
		}
		else $safeOptions = $safes;
		$safeOptions = array_flip($safeOptions);
		*/
		
		$safeOptions = array_merge(array('--'=>0), array_flip(getKeySafes($constrainedByRole=true)));

		
		selectElement(' to safe: ', 'safe', '', $safeOptions);

		echo "</td><tr></table><p>";
		//tableFrom($columns, $rows, 'width=100%', $class='safekeystable');
 		tableFrom($columns, $rows, 'width=100%', $class='safekeystable', $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses=null, $sortClickAction);
		foreach(array_keys($columns) as $i => $k) $providerkeysheadings[] = "$k:$i";
		$providerkeysheadings = join(', ', $providerkeysheadings);
	}
	echo "</form><p>";
}

function sortByDateNeeded($a, $b) {return strcmp($a['dateNeeded'], $b['dateNeeded']);}

function missingKeyLink($client, $missingClientKeys) {
	global $right;
	$keyLabel = $missingClientKeys[$client];
	if($keyLabel) {
		$keyid = $keyLabel['keyid'];
		$url = "key-edit.php?id=$keyid";
		$keyLabel = 'Key #'.sprintf("%04d", $keyid).' is unavailable.';
	}
	else {
		$url = "client-edit.php?id=$client&tab=home";
		$keyLabel = "No Key Registered";
	}
	if($right == 'ka') 
		return "<a title=\"View this client's keys.\" href='$url'>$keyLabel</a>";
	else return $keyLabel;
}


function keyLink($key, $provider) {
	$label = formattedProviderKeyId($key, $provider);
	return "<a title='Edit this key.' href='key-edit.php?id={$key['keyid']}' id='$label'>$label</a>";
}

function clientLink($client) {
	global $clients;
	//return "<a title='View this client.' href='client-view.php?id=$client'>{$clients[$client]['clientname']}</a>";
	$active = fetchRow0Col0("SELECT active FROM tblclient WHERE clientid = $client LIMIT 1", 1);
	$labelClass = $active ? null : 'italicized';
	$active = !$active ? " <i>(inactive)</i>" : '';
	$sortName = $clients[$client]['sortname'] ? $clients[$client]['sortname'] : 
		fetchRow0Col0("SELECT CONCAT_WS(', ', lname, fname) FROM tblclient WHERE clientid = $client LIMIT 1", 1);
	$sortName = str_replace("'", "0", $sortName);
	//if($sortName[0] == 'O' && mattOnlyTEST()) echo "<hr>$sortName<hr>";
	$link = fauxLink($clients[$client]['clientname'].$active, 
	         "openConsoleWindow(\"viewclient\", \"client-view.php?id=$client\",700,500)",1, 'View this client',
	         $sortName, $labelClass);
	return $link;
}
?>
<p><img src='art/spacer.gif' height=300>
<?
// ***************************************************************************
include "frame-end.html";
	if(mattOnlyTEST()) print_r($providerkeysheadings);	

?>

<script language='javascript'>
var providerkeysheadings = {<?= $providerkeysheadings ?>} // indexes of all columns
function sortKeys(heading, dir) {
	// find the table for this heading: key, bin, client
	var table = $('.providerkeystable').eq(0);

	var rows = table.find('tr:gt(0)').toArray().sort(comparer(providerkeysheadings[heading]));
	for (var i = 0; i < rows.length; i++){table.append(rows[i])}
}

function comparer(index) {
    return function(a, b) {
        var valA = getCellValue(a, index), valB = getCellValue(b, index)
<?// if(mattOnlyTEST()) echo "alert(valA+': '+valB);"; ?>
        return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.localeCompare(valB)
    }
}
function getCellValue(row, index){ 
	var val = $(row).children('td').eq(index).children().eq(0).attr('id'); 
	return typeof val == 'undefined' ? '' : val; 
}

function copyPicked(el) {
	if(el.options[el.selectedIndex].value == -1)
		alert('Only an admin can check out this copy of the key.');
}

function showProviderKeys(el) {
	var other = el.id == 'providerptr' ? 'safeid' : 'providerptr';
	if(document.getElementById(other)) document.getElementById(other).selectedIndex = 0;
	document.providerkeysform.submit();
}

function showSafeKeys() {
	document.providerkeysform.submit();
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

function checkAllKeys(state) {
	var el = document.providerkeysform.elements;
	for(var i=0;i<el.length;i++)
	  if(el[i].name.indexOf('key_') > -1) el[i].checked = (state ? true : false);
	updatePayroll();
}

function reportMissing() {
	var choices = countSelections();
	if(choices[0] == 0) {
		alert("You must first select at least one key.");
		return;
	}
	else if(choices[1] == 0) {
		if(!confirm("Are you sure you want to report all of these keys lost?"))
		  return;
	}
	document.providerkeysform.operation.value = 'lost';
	document.providerkeysform.submit();
}

function checkIn() {
	var choices = countSelections();
	if(choices[0] == 0) {
		alert("You must first select at least one key.");
		return;
	}
	var safeEl = document.providerkeysform.safe;
	if(safeEl.options[safeEl.selectedIndex].value == 0) {
		alert('You must first select a destination Key Safe');
		return;
	}
	document.providerkeysform.operation.value = 'checkin';
	document.providerkeysform.submit();
}

function checkOut(selName, prov) {
	var sel = document.getElementById(selName);
	var key = sel.options[sel.selectedIndex].value;
	if(!key) {
		alert('Cannot checkout key: ['+key+']');
		return;
	}
	else if(key == -1) {
		alert('Only an admin can check out this copy of the key.');
		return;
	}
	document.providerkeysform.checkoutkey.value = key;
	document.providerkeysform.operation.value = 'checkout';
	document.providerkeysform.submit();
}

function transferKeys() {
	var choices = countTransfers();
	if(choices == 0) {
		alert("You must first designate at least one key for transfer.");
		return;
	}
	document.providerkeysform.operation.value = 'transferKeys';
	document.providerkeysform.submit();
}

function countTransfers() {
	var selected = 0;
	for(var i=0;i<document.providerkeysform.elements.length;i++) {
		var el = document.providerkeysform.elements[i];
		if(el.name && (el.name.indexOf('transferKey_') == 0) && el.value != 0)  {selected++;}
	}
	return selected;
}

function countSelections() {
	var selected = 0;
	var unselected = 0;
	for(var i=0;i<document.providerkeysform.elements.length;i++) {
		var el = document.providerkeysform.elements[i];
		if(el.name && (el.name.indexOf('key_') == 0)) {
		  if(el.checked) selected++;
		  else unselected++;
		}
	}
	return [selected, unselected];
}



</script>
