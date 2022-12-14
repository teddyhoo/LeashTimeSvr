<? // visit-sheet-simple-fns.php

function orderByTypeAndName($a, $b) {
	$x = strcmp((string)$a['type'], (string)$b['type']);
	return $x ? $x : strcmp((string)$a['name'], (string)$b['name']);
}

function dumpFieldsSimple($fields, $thisdata=null, $force=false) {
	$thisdata = (array)$thisdata;
	
	$primaryPhoneField = primaryPhoneField($thisdata);
	
	foreach($fields as $field => $label) 
		if($force || isset($thisdata[$field])) {
			$val = $thisdata[$field];
			$raw = true; //in_array($field, array('homeaddress', 'mailaddress', 'pets', 'emergency','neighbor', 'keydata'));
			//$raw = in_array($field, array('homeaddress', 'mailaddress', 'pets', 'emergency','neighbor'));
			if(strpos($field, 'phone')) $val = strippedPhoneNumber($val);
			if($field == $primaryPhoneField) {
				$raw = true;
				$val = "<u>$val</u>";
			}
			labelRow($label.':', '', $val, 'label','','','',$raw);
		}
}

function dumpSomeCustomFieldRows($clientCustomFields, $petCustomFieldDescriptions) {
	//$clientCustomFields = getClientCustomFields($client['clientid']);
	foreach($petCustomFieldDescriptions as $key => $descr) {
		if(!isset($clientCustomFields[$key])) continue;  
		if(!$descr[3]) continue;  // visitSheetOnly
		if($descr[2] == 'oneline')
			customLabelRow($descr[0].':', '', $clientCustomFields[$key], 'label','sortableListCell');
		else if($descr[2] == 'text')
			customLabelRow($descr[0].':', '', htmlVersion($clientCustomFields[$key]), 'label','sortableListCell','','','raw');
		else if($descr[2] == 'boolean') 
			customLabelRow($descr[0].':', '', ($clientCustomFields[$key] ? 'Yes' : 'No'), 'label','sortableListCell');
	}
}


function dumpPetPicture($pet) {
	if(!$pet['photo']) return;
	$boxSize = array(110, 110);
	$includedPetId = $pet['petid'];
	$dimensionsonly = 1;
	require "pet-photo.php"; // sets $width, $height, given the $dimensionsonly flag
	$dims = dimensionsScaledToFitInside($width, $height, $boxSize);
	$src = "pet-photo.php?id={$pet['petid']}";
	$src = globalURL($src);
	echo "<img src='$src' width={$dims[0]} height={$dims[1]}";
}


function appointmentsTableSimple($appointments) {
	$providerNames = getProviderNames();
	$columns = explodePairsLine('timeofday|Time||service|Service||pets|Pets||provider|Provider');
  $timeofday = null;
	$services = array();
	$packs = array();
  foreach($appointments as $appt) {
		$clientptr = $appt['clientptr'];
		$packageIds[] = $appt['packageptr'];
		$packs[$appt['packageptr']] = $appt['recurringpackage'];
		$services[] = $appt['serviceptr'];
		$row = array('timeofday'=>$appt['timeofday']);
		$row['service'] = $_SESSION['servicenames'][$appt['servicecode']];
		$row['pets'] = 	$appt['pets'];
		$row['provider'] = 	$providerNames[$appt['providerptr']];
		$rows[] = $row;
		$rowClasses[] = 'topborder';
		if($appt['note']) {
			$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=".(count($columns))."><b>Note:<b> ".htmlVersion($appt['note'])."</td></tr>");
			$rowClasses[] = null;
		}
		
	}
	require_once "service-fns.php";
	foreach((array)$packs as $packageptr => $recurring) {
		$currPack = findCurrentPackageVersion($packageptr, $clientptr, $recurring);
		if(!$currPack) continue;
		$currPacks[$currPack] = getPackage($currPack, ($recurring ? 'R' : 'N'));
	}
	
	foreach((array)$currPacks as $currPack) {
		if($currPack['notes']) $notes[] = cleanseString(trim($currPack['notes']));
	}
	if($notes) $notes = withHTMLBreaks("<b>Notes:<br></b>".join("\n\n", $notes));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($appointments); }
	$style = $notes ? "width=50%'" : '';
	echo "<table width=100%>";
	echo "<tr><td $style>";
  tableFrom($columns, $rows, '', 'jobstable', null, null, null, null, $rowClasses);
	echo "</td><td>$notes</td><tr>";
	
	echo "</table>";
}


