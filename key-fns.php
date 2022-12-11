<?
// key-fns.php
require_once "gui-fns.php";
require_once "common/db_fns.php";
require "key-safe-fns.php";

// RIGHTS: ki - key individual, ka - key administration

$maxKeyCopies = getMaxKeyCopies();

function getMaxKeyCopies() {
	return 10; // 5/24/2021 - investigated making this settable.  Not really feasible while copies are represented in possessor columns of tblkey. mL
}

function isSecureKeyEnabled() {
	return $_SESSION['secureKeyEnabled'];
}

function keyManagementRight() {
	if(isSecureKeyEnabled()) {  // prevent access by non-payers
		if(userRole() == 'o' || userRole() == 'd') return 'ka';
		else return strpos($_SESSION['rights'], 'ka') ? 'ka' : (strpos($_SESSION['rights'], 'ki') ? 'ki' : '');
	}
	return '';
}
	

//$safes = array('safe1' =>	'-- Key Safe --', 'safe2' =>	'-- Key Safe 2 --');
$safes = getKeySafes();

$keyFields = array();
$raw = explode(',', 'keyid,Key ID,locklocation,Lock Location,description,Description,bin,Key Hook,copies,Copies');
for($i=0;$i < count($raw) - 1; $i+=2) $keyFields[$raw[$i]] = $raw[$i+1];


function getKeyFields() {
	global $keyFields;
	if($keyFields) return $keyFields;
	$keyFields = array();
	$raw = explode(',', 'keyid,Key ID,locklocation,Lock Location,description,Description,bin,Key Hook,copies,Copies');
	for($i=0;$i < count($raw) - 1; $i+=2) $keyFields[$raw[$i]] = $raw[$i+1];
	return $keyFields;
}
	

$keyTableFields = array_keys($keyFields);
for($i=1;$i<=$maxKeyCopies;$i++) $keyTableFields[] = "possessor$i";
$keyTableFields[] = "clientptr";

function getProviderKeys($id) {
	global $maxKeyCopies;
	if(!$id) return array();
	for($i = 1; $i <= getMaxKeyCopies(); $i++) $tests[] = "possessor$i = '$id'";
	return fetchAssociations("SELECT * FROM tblkey WHERE ".join(' OR ', (array)$tests));
}

function clientKeysMissingForDaysAhead($daysAhead, $prov=null, $withKeyNumbers=null) {
	$providerFilter = $prov ? "AND providerptr = $prov" : '';
	$clients = 
	  fetchCol0(tzAdjustedSql("SELECT distinct clientptr FROM tblappointment
					WHERE canceled IS NULL AND completed IS NULL $providerFilter
					AND date >=  CURDATE() AND date <= FROM_DAYS(TO_DAYS(CURDATE())+$daysAhead)"));	
	$clientsCovered = array();
	foreach(getProviderKeys($prov) as $key) $clientsCovered[] = $key['clientptr'];
	$clientsNeeded = array_diff($clients, array_unique($clientsCovered));
	// eliminate clients where no key is required
	if($clientsNeeded) 
		$clientsNeeded = fetchCol0("SELECT clientid FROM tblclient WHERE nokeyrequired = 0 AND clientid IN (".join(',', $clientsNeeded).")");
	if(!$withKeyNumbers) return $clientsNeeded;
	return fetchAssociationsKeyedBy("SELECT clientptr, keyid, bin FROM tblkey WHERE clientptr IN (".join(',', $clientsNeeded).")", 'clientptr');
}

function keyProviders($key) {
	$possessors = array();
	foreach($key as $field => $val) 
		if($val && strpos($field, 'possessor') === 0 && is_numeric($val)) 
			$possessors[] = $key[$field];
	return $possessors;
}

function keyCopyLocationLabels($key) {
	if(is_integer($key)) $key = getKey($key);
	$labels = array_flip(keyPossessorOptions(array('keyid'=>0)));
	$possessors = array();
	foreach($key as $field => $val) 
		if($val && strpos($field, 'possessor') === 0) 
			$possessors[formattedKeyId($key['keyid'], substr($field, strlen('possessor')))] = $labels[$key[$field]];
	return $possessors;
}

function providersWhoNeedKeyForDaysAhead($key, $daysAhead) {
	foreach($key as $field => $val) 
		if($val && strpos($field, 'possessor') === 0 && is_numeric($val)) 
			$possessors[] = $key[$field];
	if(!$possessors) return array();
	$possessors = join(',',$possessors);
	$result = 
	  mysqli_query(tzAdjustedSql("SELECT distinct providerptr, date,
	  			CONCAT_WS(' ', tblprovider.fname, tblprovider.lname) as provider,
	  			CONCAT_WS('', tblprovider.lname, ',', tblprovider.fname) as providersortname
	  			FROM tblappointment
	  			LEFT JOIN tblprovider ON providerid = providerptr
					WHERE canceled IS NULL AND completed IS NULL AND clientptr = {$key['clientptr']}
						AND date >=  CURDATE() AND date <= FROM_DAYS(TO_DAYS(CURDATE())+$daysAhead)
						AND providerptr NOT IN ($possessors)
					ORDER BY date"));	
	$appts = array();
	while($appt = mysqli_fetch_array($result, MYSQL_ASSOC)) $appts[$appt['providerptr']] = $appt;
	return array_values($appts);
}

function allClientKeysMissingForDaysAhead($daysAhead) {
	$result = 
	  doQuery(tzAdjustedSql("SELECT distinct clientptr, providerptr, date, 
	  			CONCAT_WS(' ',tblclient.fname, tblclient.lname) as client,
	  			CONCAT_WS('',tblclient.lname, ',', tblclient.fname) as clientsortname,
	  			IFNULL(nickname, CONCAT_WS(' ', tblprovider.fname, tblprovider.lname)) as providerdescription
	  			FROM tblappointment
	  			LEFT JOIN tblclient ON clientid = clientptr
	  			LEFT JOIN tblprovider ON providerid = providerptr
					WHERE canceled IS NULL AND completed IS NULL
					AND date >=  CURDATE() AND date <= FROM_DAYS(TO_DAYS(CURDATE())+$daysAhead)
					ORDER BY date DESC"));
					
	// pick first day for each provider-client
	$appts = array();
	while($appt = mysqli_fetch_array($result, MYSQL_ASSOC)) $appts[$appt['providerptr'].'.'.$appt['clientptr']] = $appt;
	$appts = array_values($appts);
	
	
	// identify clients where no key is required
	$keylessClients = fetchCol0("SELECT clientid FROM tblclient WHERE nokeyrequired = 1");
	
	$clientsCovered = array();
	$provs = array();
	foreach($appts as $appt) {
		$provs[] = $appt['providerptr'];
		$clients[$appt['providerptr']][] = $appt['clientptr'];
	}
	foreach($provs as $prov) {
		foreach(getProviderKeys($prov) as $key) $clientsCovered[$prov][] = $key['clientptr'];
		$clientsCovered[$prov] = $clientsCovered[$prov] ? $clientsCovered[$prov] : array();
		$missing[$prov] = array_diff(array_unique($clients[$prov]), array_unique($clientsCovered[$prov]));
		$missing[$prov] = array_diff($missing[$prov], $keylessClients);
	}
	foreach($appts as $i => $appt)
	  if(!in_array($appt['clientptr'], $missing[$appt['providerptr']]))
	    unset($appts[$i]);
	return $appts;
}

function noKeyLink($client, $back=null, $popup=0) {
	if($_SESSION['secureKeyEnabled']) {
		$back = $back ? "&back=$back" : '';
		$popup = $popup ? "&popup=$popup" : '';
		if(!adequateRights('ka')) return "<span style='color:red;' title='Key is needed.'>NO&nbsp;KEY</span>";
    return"<a href='key-edit.php?client=$client$back' style='color:red;' title='Issue a key to the sitter'>NO&nbsp;KEY</a>";
	}
  else return '';
}

function noKeyIcon($client, $imgsize=15) {
	if($_SESSION['secureKeyEnabled']) {
		$keys = getClientKeys($client);
		if(!$keys) $title = 'There is NO KEY on record for this client.';
		else $title = htmlentities('NO KEY. Copies: '.keyComment($keys[0]));
		return "<img width=$imgsize height=$imgsize src='https://{$_SERVER["HTTP_HOST"]}/art/no-key.gif' title='$title'>";
	}
	else return '';
}

function noKeyIconLink($client, $back=null, $popup=0, $imgsize=15) {
	if($_SESSION['secureKeyEnabled']) {
		
		if(!adequateRights('ka')) {
			$keys = getClientKeys($client);
			if(!$keys) $title = 'There is NO KEY on record for this client.';
			else $title = htmlentities('NO KEY. Copies: '.keyComment($keys[0]));
			return "<img width=$imgsize height=$imgsize src='art/no-key.gif' title='$title'>";
		}

		$back = $back ? "&back=$back" : '';
		$popup = $popup ? "&popup=$popup" : '';
		if($popup) $action = "openConsoleWindow(\"keyeditor\", \"key-edit.php?client=$client$back$popup\", 600, 500)";
		else $action = "document.location.href=\"key-edit.php?client=$client$back\"";
		if(!adequateRights('ka')) $action = '';
		return fauxLink("<img width=$imgsize height=$imgsize src='art/no-key.gif'>", $action, 
											1, 'NO KEY. Click to issue a key to the sitter');
	}
	else return '';
}



function getClientKeys($id) {
	if(!$id) return array();
	return fetchAssociations("SELECT * FROM tblkey WHERE clientptr = $id");
}

function getClientGroupKeys($ids) {
	if(!$ids) return array();
	$ids = join(',',$ids);
	return fetchAssociationsKeyedBy("SELECT * FROM tblkey WHERE clientptr IN ($ids)", 'clientptr');
}

function getKey($id) {
	if(!$id) return array();
	return fetchFirstAssoc("SELECT * FROM tblkey WHERE keyid = '$id' LIMIT 1");
}

function getKeysById($ids) {
	if(!$ids) return array();
	$ids = join(',', $ids);
	return fetchAssociationsKeyedBy("SELECT * FROM tblkey WHERE keyid IN ($ids)", 'keyid');
}

function keyTable($key, $keyLabelHTML='') {
	global $keyFields, $maxKeyCopies;
	
  echo "<table width=80%>\n<tr><td colspan=2>Keys</td></tr>";  // Alarm
	// I will assume that there is only one key (with multiple copies) per client
	foreach($keyFields as $field => $label) {
		if($field == 'keyid') {
			$keyId = isset($key['keyid']) ? sprintf("%04d", $key['keyid']) : '';
			if(!$keyLabelHTML)	labelRow($label, '', ($keyId ? $keyId : 'No Key'));
			else labelRow($label, '', str_replace('##KEYID##', $keyId, $keyLabelHTML), null, null, null,  null, true);
			hiddenElement('keyid', $keyId);
		}
		else if($field == 'copies') {
			$options = array();
			for($i=0;$i<=$maxKeyCopies;$i++) $options[$i] = $i;
			selectRow($label, $field, $key['copies'], $options, 'showKeyCopies(this)');
		}
		else if (in_array($field, array('description','locklocation'))) {
			// inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
			// 1234567890A234567890B234567890C234567890
			if($field == 'locklocation') $label = str_replace(' ', '&nbsp;', $label);
			if(mattOnlyTEST() || dbTEST('carolinapetcare'))
				inputTextBoxRow($label, $field, $rows=1, $cols=40, $maxlength=null, $key[$field], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur='ensureOneKey()', $extraContent=null, $inputCellPrepend=null);
			else inputRow($label.':', $field, $key[$field], null, 'Input40Chars', null, null, 'ensureOneKey()');
		}
		else {
			// inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
			inputRow($label.':', $field, $key[$field], null, null, null, null, 'ensureOneKey()');
		}
	}
	
	// Added value
	if($_SESSION['secureKeyEnabled']) {
	  $options = keyPossessorOptions($key);	  
	  $numCopies = isset($key['copies']) ? $key['copies'] : 0;
	  for($i=1;$i<=$maxKeyCopies;$i++) {
		  $displayMode = $i <= $numCopies ? $_SESSION['tableRowDisplayMode'] : 'none';
      selectRow("Copy #$i", "possessor$i", $key["possessor$i"], $options, '','standardInputRight','',"row_possessor_$i", "display:$displayMode;");	
	  }
	}
	echo "</table>\n";
}

function keyTableForEditor($key) {  // will include print buttons
	global $keyFields, $maxKeyCopies;
	$client = getOneClientsDetails($key['clientptr'], array('pets'));
	hiddenElement('clientptr', $key['clientptr']);
	hiddenElement('keyid', $key['keyid']);
	hiddenElement('originalbin', $key['bin']);
	$clientLabel = $client['clientname'];
	$clientLabel = fauxLink($clientLabel, "openConsoleWindow(\"viewclient\", \"client-view.php?id={$key['clientptr']}\",700,500)", 1, 'Open the client detail viewer.');
	if($client['pets']) $clientLabel .= " (".petNamesCommaList($client['pets'], 40).")";
  echo "<table width=80% border=0%>\n<tr><td colspan=2>Client: $clientLabel</td></tr>";  // Alarm
	// I will assume that there is only one key (with multiple copies) per client
	foreach($keyFields as $field => $label) {
		if($field == 'keyid') {
			$keyId = isset($key['keyid']) ? sprintf("%04d", $key['keyid']) : '';
			labelRow($label, '', ($keyId ? $keyId : 'No Key'));
		}
		else if($field == 'copies') {
			$options = array();
			for($i=0;$i<=$maxKeyCopies;$i++) $options[$i] = $i;
			
			selectRow($label, $field, $key['copies'], $options, 'showKeyCopies(this)');
		}
		else if (in_array($field, array('description','locklocation'))) {
			if($field == 'locklocation') $label = str_replace(' ', '&nbsp;', $label); 
			// inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
			// 1234567890A234567890B234567890C234567890
			inputRow($label.':', $field, $key[$field], null, 'Input40Chars', null, null, 'ensureOneKey()');
		}
		else {
			// inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
			inputRow($label.':', $field, $key[$field], null, null, null, null, 'ensureOneKey()');
		}
	}
	
	// Added value
	if($_SESSION['secureKeyEnabled']) {
		$adminOnlyKeySafes = getAdminOnlyKeySafes();
	  $options = keyPossessorOptions($key);
	  $numCopies = isset($key['copies']) ? $key['copies'] : 0;
	  for($i=1;$i<=$maxKeyCopies;$i++) {
		  $displayMode = $i <= $numCopies ? $_SESSION['tableRowDisplayMode'] : 'none';
			$extraTD = "<td><img style='cursor:pointer;' src='art/barcodebutton.gif' onClick='printKeyLabel({$key['keyid']}, $i )' title='Print key label'>
											<img src='art/spacer.gif' height=1 width=3>";
			if(!locationIsASafe($key["possessor$i"]))
					$extraTD .= "	<img style='cursor:pointer;' src='art/request-checkout.gif' onClick='requestCheckin({$key['keyid']}, $i)' 
												title='Email key check-in request.'>";		  
//selectRow($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null) {
			
			// COPY MAY NOT BE ACCESSIBLE IF ADIN ONLY SAFE
//labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)		

			if($adminOnlyKeySafes[$key["possessor$i"]] && shouldEnforceAdminOnly()) {
				labelRow("Copy #$i", "Xpossessor$i", "(admin access only) ".$adminOnlyKeySafes[$key["possessor$i"]],'standardInputRight','',"Xrow_possessor_$i", "display:$displayMode;", $extraTD);
				hiddenElement("possessor$i", $key["possessor$i"]);
			}
      else selectRow("Copy #$i", "possessor$i", $key["possessor$i"], $options, '','standardInputRight','',"row_possessor_$i", "display:$displayMode;", $extraTD);	
	  }
	}
	
if($key['clientptr'] && $_SESSION['preferences']['enableKeyOfficeNotes']) { // need to add this to key-edit.php as well.
		$officeOnlyKeyNotes = getClientPreference($key['clientptr'], 'officeonlykeynotes');
		textRow("Office Only Key Notes", 'officeonlykeynotes', $officeOnlyKeyNotes, $rows=3, $cols=46, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
}		
	
	echo "</table>\n";
}

function keyPossessorOptions($key) {
	global $safes;
	
	if(shouldEnforceAdminOnly()) {
		$adminOnlyKeySafes = getAdminOnlyKeySafes();
		foreach($safes as $safeKey => $label)
			if(!$adminOnlyKeySafes[$safeKey])
				$safeOptions[$safeKey] = $label;
	}
	else $safeOptions = $safes;
	
  $fixedOptions = array_merge(array('client' => '--Client--'),$safeOptions, array('missing' => '--Missing--'));

  foreach($fixedOptions as $val => $label) {
		$options[$label] = $val;
	}
	$provs = fetchAssociations("SELECT providerid, active, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name 
	                            FROM tblprovider ORDER BY name");
	                            
	$keyProviders = keyProviders($key);
	
  foreach($provs as $id => $prov) {
		if(!$prov['active'] && !in_array($prov['providerid'], $keyProviders)) continue;
		$label = $prov['active'] ? $prov['name'] : "* {$prov['name']}";
		$options[$label] = $prov['providerid'];
	}
	return $options;
}

//$raw = explode(',', 'keyid,Key ID,locklocation,Lock Location,description,Description,bin,Key Hook,copies,Copies');

function saveClientKey($clientId, $keyData=null) {
	global $maxKeyCopies; //$keyFields, 
	$keyData = $keyData ? $keyData : $_POST;
  $key = array('clientptr'=>$clientId);
  $fieldNames = array_keys(getKeyFields());
  unset($fieldNames['keyid']);
	if(array_key_exists('officeonlykeynotes', $keyData)) {
		require_once "preference-fns.php";
		$officeonlykeynotes = $keyData['officeonlykeynotes'] ? $keyData['officeonlykeynotes'] : null;
		setClientPreference($clientId, 'officeonlykeynotes', $officeonlykeynotes);
		//setClientPreference($clientId, 'officeonlykeynotes', $keyData['officeonlykeynotes']);
	}
  foreach($fieldNames as $field)
	  $key[$field] = $keyData[$field];
	for($i=1;$i <= $maxKeyCopies; $i++)
		$key["possessor$i"] = $i <= $key['copies'] ? $keyData["possessor$i"] : null;
  if($key['copies']	 && !$key['keyid']) {
		$keyId = insertTable('tblkey', $key, 1);
		logKeyChange(mysqli_insert_id(), $key, $clientId, true);
		return $keyId;
	}
	else if($key['keyid']) {
		logKeyChange($keyData['keyid'], $key, $clientId);
	  return updateTable('tblkey', $key, "keyid={$keyData['keyid']}", 1);
	}
}

function saveKey($key) {  // $key may be a copy of $_POST
	global $keyTableFields, $maxKeyCopies;
  $keyId = $key['keyid'];
  $clientptr = $key['clientptr'];
	if(array_key_exists('officeonlykeynotes', $key)) {
		require_once "preference-fns.php";
		$officeonlykeynotes = $key['officeonlykeynotes'] ? $key['officeonlykeynotes'] : null;
		setClientPreference($clientptr, 'officeonlykeynotes', $officeonlykeynotes);
	}
  foreach($key as $field => $val)
    if(!in_array($field, $keyTableFields))
	    unset($key[$field]);
	for($i=1;$i <= $maxKeyCopies; $i++)
		$key["possessor$i"] = $i <= $key['copies'] ? $key["possessor$i"] : null;


  if($key['copies']	 && !$keyId) {
		insertTable('tblkey', $key, 1);
		$keyId = mysqli_insert_id();
		logKeyChange($keyId, $key, $clientptr, true);
	}
	else if($keyId) {
		logKeyChange($keyId, $key, $clientptr);
	  updateTable('tblkey', $key, "keyid=$keyId", 1);
	}
	return $keyId;
}

function logKeyChange($keyid, $key, $clientptr, $isNew=null) {
	global $keyFields, $maxKeyCopies;
  $fieldNames = array_keys($keyFields);
  unset($fieldNames['keyid']);
	if($isNew) {
		$change = 'Key|added';
    foreach($fieldNames as $field)
		  $change .= "|$field|{$key[$field]}";
	  for($i=1;$i <= $maxKeyCopies; $i++)
		  if($i <= $key['copies']) 
		    $change .= "|possessor$i|{$key['possessor$i']}";
		  
	}
	else {
		$oldKey = getKey($keyid);
		$change = '';
    foreach($fieldNames as $field)
      if($oldKey[$field] != $key[$field])
		    $change .= ($change ? '|' : '')."$field|{$key[$field]}";
	  for($i=1;$i <= $maxKeyCopies; $i++) {
			$field = "possessor$i";
		  if($i > $key['copies'] && ($key['copies'] < $oldKey['copies']) && $oldKey[$field]) 
		    $change .= ($change ? '|' : '')."$field|dropped";
      else if($oldKey[$field] != $key[$field])
		    $change .= ($change ? '|' : '')."$field|{$key[$field]}";
		}
	}

	if($change) 
	  insertTable(
			'tblkeylog', 
			array('keyptr'=>$keyid, 'clientptr'=>$clientptr, 'modification'=>$change, 
			'datetime'=> date("Y-m-d H:i:s")), 1);
}

function identifyKey($identifier) {
	$splitter = null;
	$seps = '.-_/\\, ';
	for($i=0;$i<strlen($seps) && !$splitter;$i++)
	  if(strpos($identifier, $seps[$i]))
				$splitter = $seps[$i];
	$parts = array($identifier);
	if($splitter) $parts = explode($splitter, $identifier);
  else if(is_numeric($identifier)) {
		if(strlen($identifier) >4) {
			$parts[0] = substr($identifier, 0, 4);
			$parts[1] = substr($identifier, 4);
		}
	}
	$keyptr = $parts[0];
	return getKeyData($keyptr, $parts);
}
	
function getKeyData($keyptr, $parts=null) {
	global $maxKeyCopies;
	$key = getKey($keyptr);
	$keyData = null;
	if(!$key) {
		$error = "<u>$keyptr</u> is an unknown Key ID.";
		$keyid = null;
	}
	else {
		$client = getOneClientsDetails($key['clientptr'], array('activepets'));
		$clientName = $client['clientname'];
		$keyData = array('key' => $key, 'client' => $client);
		$copyNumber = count($parts) > 1 ? (int)$parts[1] : '';
		if($copyNumber >= 1 && $copyNumber <= $maxKeyCopies) {
		  $copyNumber = intval($copyNumber);
		  $keyData['copyNumber'] = $copyNumber;
			$keyData['copyField'] = "possessor$copyNumber";
		  if($copyNumber && !$key["possessor$copyNumber"]) {
		    $keyData['badCopyMessage'] = "There is no record of this copy of $clientName's key.";
			}
		  else {
				$keyData['possessor'] = $key[$keyData['copyField']];
				$keyData['possessorname'] = lookUpKeyPossessor($keyData['possessor']);
//print_r(			$keyData	) ;exit;
			}
	  }
	  else $keyData['badCopyMessage'] = "This is an unidentified copy of $clientName's key.";
	}
	return $keyData ? $keyData : $error;
}
	
	
function lookUpKeyPossessor($val) {
	global $safes;  //replace with a SESSION var
	if(!is_numeric($val)) return $safes[$val];  // look this up in a table
	else {
		$name = getProviderCompositeNames("WHERE providerid = $val LIMIT 1") ;
		return $name[$val];
	}
}

function locationIsASafe($val) {
	global $safes;  //replace with a SESSION var
	return array_key_exists($val, $safes);  // look this up in a table
}

function formattedKeyId($keyid, $copy) {
	return sprintf("%04d", $keyid).'-'.sprintf("%02d", $copy);
}

function formattedProviderKeyId($key, $provider) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($key);exit; }
	for($i=1; isset($key["possessor$i"]); $i++)
	  if($key["possessor$i"] == $provider)
			$copy = $i;
	return sprintf("%04d", $key['keyid']).'-'.sprintf("%02d", $copy);
}

function transferKey($keyid, $copy, $destination) {
	$key = getKey($keyid);
	if(!$key || !$key["possessor$copy"]) return false;
	$key["possessor$copy"] = $destination;
	logKeyChange($key['keyid'], $key, $key['clientptr']);
	updateTable('tblkey', $key, "keyid={$key['keyid']}", 1);
	return true;
}

function keyComment($key) {
	global $safes, $providerNames;
	$locs = array();
	for($i=1; isset($key["possessor$i"]); $i++)
	  if($key["possessor$i"]) $locs[$key["possessor$i"]]++;
	ksort($locs); // client, safe, providers
	$s = array();
	$providerCopies = array();
	foreach($locs as $loc => $n) {
		if($loc == 'client') $s[] = 'Client has '.nKeys($locs['client']);
		else if($loc == 'missing') $s[] = 'Missing: '.nKeys($locs['missing']);
		else if(isset($safes[$loc])) $s[] = "{$safes[$loc]} has ".nKeys($locs[$loc]);
		else $providerCopies[$loc] = $n;
	}
	if($providerCopies) foreach($providerCopies as $prov => $num) 
	  $s[] = $providerNames[$prov].'-'.$num;
	$s = $s ? join(', ',$s) : '';
	$s = str_replace('-- ','', $s);
	return $s; 
	
}


function keyHistory($keyid) {
	$keyid = strpos($keyid, '-') ? substr($keyid, 0, strpos($keyid, '-')) : $keyid;
	return fetchAssociations("SELECT * FROM tblkeylog WHERE keyptr = $keyid ORDER BY datetime");
}

function keyHistoryTable($keyid) {
	$table = "<table>\n";
	$history = keyHistory($keyid);
	$holders = fetchKeyValuePairs("SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name FROM tblprovider");
	foreach(getKeySafes() as $k => $v) $holders[$k] = $v;
	$holders['client'] = 'client';
	if(!$history) return "No history found for key $keyid.";
	foreach($history as $line) {
		$parts = explode('|', $line['modification']);
		if($parts[0] == 'Key' && $alreadyregistered) continue;  // necessary because of early junk in doggywalker.com log
		$holder = $holders[$parts[1]];
		if(!$holder && strpos($parts[1], 'safe') === 0) $holder = "Safe #".substr($parts[1], strlen('safe'));
		if(count($parts) == 2 && strpos($parts[0], 'possessor') === 0) // transfer
			$mod = "Copy #".substr($parts[0], strlen('possessor'))
			.($parts[1] == 'missing' ? " reported missing." : " transferred to $holder .");
		else {
			$labels = array('bin'=>'Key hook', 'locklocation'=>'lock location');
			$mod = '';
			for($i=0; $i < count($parts); $i+=2) {
				$k = $parts[$i];
				if($k == 'keyid') continue;
				if($k == 'Key') {
					$alreadyregistered = true;
					$mod .= 'Key registered.<br>';
				}
				else {
					$label = ($possessor = strpos($k, 'possessor') === 0) 
					? "copy #".substr($k, strlen('possessor'))
					: (isset($labels[$k]) ? $labels[$k] : $k);
					$val = $possessor ? $holders[$parts[$i+1]] : $parts[$i+1];
					if($val)  $mod .= "$label = $val.<br>";
					else $mod .= "$label {$parts[$i+1]}.<br>";
				}
			}
		}
		$table .= "<tr><td valign=top>".shortDateAndTime(strtotime($line['datetime']), 'mil').
							"</td><td>$mod</td></tr>\n";
	}
	return "$table</table>\n";
}

function keyLogSection($start, $end) {
	$events = fetchAssociations(
		"SELECT log.*, CONCAT_WS(' ', fname, lname) as client
			FROM tblkeylog log
			LEFT JOIN tblkey k ON k.clientptr = log.clientptr
			LEFT JOIN tblclient ON clientid = log.clientptr
			WHERE datetime >= '$start 00:00:00' 
				AND datetime <= '$end 23:59:59' 
			ORDER BY datetime");
	if(!$events) return "No log entries found.";
	$holders = fetchKeyValuePairs("SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name FROM tblprovider");
	foreach(getKeySafes() as $k => $v) $holders[$k] = $v;
	$holders['client'] = 'client';
	foreach($events as $line) {
		$parts = explode('|', $line['modification']);
		if($parts[0] == 'Key' && $alreadyregistered) continue;  // necessary because of early junk in doggywalker.com log
		$holder = $holders[$parts[1]];
		if(!$holder && strpos($parts[1], 'safe') === 0) $holder = "Safe #".substr($parts[1], strlen('safe'));
		if(count($parts) == 2 && strpos($parts[0], 'possessor') === 0) // transfer
			$mod = "Copy #".substr($parts[0], strlen('possessor'))
			.($parts[1] == 'missing' ? " reported missing." : " transferred to $holder .");
		else {
			$labels = array('bin'=>'Key hook', 'locklocation'=>'lock location');
			$mod = '';
			for($i=0; $i < count($parts); $i+=2) {
				$k = $parts[$i];
				if($k == 'keyid') continue;
				if($k == 'Key') {
					$alreadyregistered = true;
					$mod .= 'Key registered.<br>';
				}
				else {
					$label = ($possessor = strpos($k, 'possessor') === 0) 
					? "copy #".substr($k, strlen('possessor'))
					: (isset($labels[$k]) ? $labels[$k] : $k);
					$val = $possessor ? $holders[$parts[$i+1]] : $parts[$i+1];
					if($val)  $mod .= "$label = $val.<br>";
				}
			}
		}
		$rows[] = array('time'=>shortDateAndTime(strtotime($line['datetime'])),'keyid'=>$line['keyptr'], 'mod'=>$mod, 'client'=>$line['client'], 'clientptr'=>$line['clientptr']);
	}
	return $rows;
}

function nKeys($n) {
	if($n == 0) $n = 'no';
	return $n == 1 ? "one copy"
	        : "$n copies";
}

