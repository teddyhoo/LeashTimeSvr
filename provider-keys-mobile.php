<?
//provider-keys-mobile.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";

require_once "key-fns.php";

// Determine access privs
locked('p-');
$right = keyManagementRight();

extract($_REQUEST);

$pageIsPrivate = 1;
// ***************************************************************************
include "mobile-frame.php";

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
$providerptr = $_SESSION["providerid"];
if($providerptr) {
	hiddenElement('operation', '');
  $clientAppts = getNextAppointmentDatePerClientForProvider($providerptr);
  $clientIds = array_keys($clientAppts);
  //$clientIds = getActiveClientIdsForProvider($providerptr);
  $clients = getClientDetails($clientIds);
  $keyClientIds = array();
	foreach(getProviderKeys($providerptr) as $key) $keyClientIds[] = $key['clientptr'];
  //$providerKeys = getProviderKeys($providerptr);

	$missingClients = array_diff($clientIds, $keyClientIds);
	if($missingClients) {
		// identify clients where no key is required
		$keylessClients = fetchCol0("SELECT clientid FROM tblclient WHERE nokeyrequired = 1");
		$missingClients = array_diff($missingClients, $keylessClients);
	}

	if($missingClients) {
		echo "<p style='text-align:right;'>";
		fauxLink("<span class='warning'>Keys you will soon need</span>", "document.location.href=\"#needed\"");
		echo " (click)";
		echo "</p>";
	}
	echo "<h3 style=text-align:center;'>Your Key Ring</h3>";
  $rows = array();
	$columns = explodePairsLine('key|Key ID||client|Client');//||copies|Copies

	unset($providerLabels[$providerptr]);
	$useKeyDescriptions = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'];
	
	foreach(getProviderKeys($providerptr) as $key) {
		if(!isset($clients[$key['clientptr']])) {
		  $clients[$key['clientptr']] = getOneClientsDetails($key['clientptr']);
		}
		$row = array();
		$row['key'] = $useKeyDescriptions ? $key['description']
								: formattedProviderKeyId($key, $providerptr);
	  //$row['bin'] = $key['bin'];
	  $row['client'] = $clients[$key['clientptr']]['clientname']; //clientLink($key['clientptr']);
	  //function labeledSelect($label, $name, $value=null, $options=null, $labelClass=null, $inputClass=null, $onChange=null, $noEcho=false) {

	  $row['copies'] = keyComment($key);
	  $rows[] = $row;
	}
	if(!$rows) echo '<p>'.array_search($providerptr, $providerNames).' has no keys.';
	else {
		/*$rows[] = array('transferTo'=>echoButton('','Transfer Keys to Designated Sitters', 'transferKeys()', null, null, true));

		echo "<p><table width=100%><tr><td>";
		fauxLink('Select All', 'checkAllKeys(true)');
		echo "</td><td>";
		fauxLink('Un-Select All', 'checkAllKeys(false)');
		echo "</td><td>";
		echoButton('','Report Selected Keys Lost', 'reportMissing()');
		echo "</td><td>";
		echoButton('','Check In Selected Keys', 'checkIn()');
		selectElement(' to safe: ', 'safe', '', array_flip($safes));

		echo "</td><tr></table><p>";*/
		tableFrom($columns, $rows, 'width=100%');
  }
	echo "</form><p>";
	

	if($missingClients) {
		echo "<a name='needed'></a><h3 class='warning'>You do not have these keys which you will need soon:</h3>";
		$rows = array();
		$missingClientKeys = getClientGroupKeys($missingClients);
		$useKeyDescriptions = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'];
		foreach($missingClients as $client) {
			$availableKeys = array();
			$key = $missingClientKeys[$client];
			if(!isset($clients[$client])) {
				$clients[$client] = getOneClientsDetails($client);
			}
//echo print_r($clients[$client],1)."<p>";			
			/*$bin = '';
			if($key) foreach($key as $field => $val) {
				if(strpos($field, 'possessor') === 0 && strpos($val, 'safe') === 0) {
					$keyidAndCopy = formattedKeyId($key['keyid'], substr($field, strlen('possessor')));
					$label = $keyidAndCopy.' - '.$safes[$val];
					$availableKeys[$label] = $keyidAndCopy;
				}
				$bin = $key['bin'];
			}
			$action = null;
			if(!$availableKeys) $keyCell = missingKeyLink($client, $missingClientKeys);
			else {
				$keyCell = selectElement('Available:', "key$client", '', $availableKeys, null, null, null, 1);
				$action = echoButton('','Check Out', "checkOut(\"key$client\", $providerptr)", null, null, 1);
			}*/
			$keyCell = $key ? ($useKeyDescriptions ? "{$key['description']}" : "#{$key['keyid']}") : '<i>Key unknown</i>';

			$rows[] =  
				array('client'=>$clients[$client]['clientname'], //clientLink($client), 
							'key'=>	$keyCell, /*'action'=>$action, 'bin' => $bin,*/ 'dateNeeded'=>shortDate(strtotime($clientAppts[$client])) );
		}
		$columns = explodePairsLine('client|Client||key|Key||dateNeeded|Needed On');//||bin|Key Hook||action|| 
		tableFrom($columns, $rows, 'width=100%');
	}
}

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
	return "<a title='Edit this key.' href='key-edit.php?id={$key['keyid']}'>$label</a>";
}

function clientLink($client) {
	global $clients;
	//return "<a title='View this client.' href='client-view.php?id=$client'>{$clients[$client]['clientname']}</a>";
	return fauxLink($clients[$client]['clientname'], 
	         "openConsoleWindow(\"viewclient\", \"client-view.php?id=$client\",700,500)",1, 'View this client');
}
?>

<script language='javascript'>
function showProviderKeys() {
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
