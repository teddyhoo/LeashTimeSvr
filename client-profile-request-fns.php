<?
//client-profile-request-fns.php
// must be loaded after session initialization!

require_once "field-utils.php";
require_once "zip-lookup.php";
require_once "client-fns.php";
require_once "provider-fns.php";
include_once "vet-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "service-fns.php";
require_once "js-gui-fns.php";
require_once "custom-field-fns.php";

$rawBasicFields = getRawBasicFieldString();  
	               
$homeFields = getHomeFieldString();
	        
function getHomeFieldString() {
	return 'leashloc,Leash Location,foodloc,Food Location,parkinginfo,Parking Info,garagegatecode,Garage / Gate Code,directions,Directions to Home,'.
'alarmcompany,Alarm Company,alarmcophone,Alarm Company Phone,alarminfo,Alarm Info';
}

function getRawBasicFieldString() {
	return 'fname,First Name,lname,Last Name,fname2,Alt First Name,lname2,Alt Last Name,email,Email,email2,Alt Email,'.
                  'cellphone,Cell Phone,homephone,Home Phone,workphone,Work Phone,cellphone2,Alt Phone,'.
	               'fax,FAX,pager,Pager,clinicname,Veterinary Clinic,vetname,Veterinarian,notes,Notes'; 
}

//$customFields = getCustomFields('activeOnly');

function nameOf(&$arr) {
	$name = $arr['fname'].' '.$arr['lname'];
	if(isset($arr['clinicname'])) {
	  $name = "{$arr['clinicname']}".(trim($name) ? " ($name)" : '');
	}
	return $name;
}

function getClientFieldMaxArray(&$fieldMaxArray, $dbTable=null) {
	// acquire field lengths for dbTable and stash them in fieldMaxArray
	if(!staffOnlyTEST()) return $fieldMaxArray;
	if(!$dbTable) return $fieldMaxArray;
	$cols = fetchAssociations("DESC $dbTable;");
	foreach($cols as $col) {
		if(strpos($col['Type'], 'varchar(') === 0)
			$fieldMaxArray[$dbTable][$col['Field']] = substr($col['Type'], strlen('varchar('), strpos($col['Type'], ')')-strlen('varchar('));
	}
	//if(mattOnlyTEST()) echo "<hr><pre>".print_r($fieldMaxArray, 1)."</pre>";
	return $fieldMaxArray;
}

function deltaInputRow($label, $name, $value=null, $value0=null, $labelClass=null, $inputClass=null, $dbTable=null, $rowId=null,  $rowStyle=null, $onBlur=null) {
	static $fieldMaxArray;
	if(!$fieldMaxArray) $fieldMaxArray = array();
	if(!$fieldMaxArray[$dbTable]) $fieldMaxArray = getClientFieldMaxArray($fieldMaxArray, $dbTable);

//if(mattOnlyTEST() && $dbTable == 'tblcontact') echo "<hr><b><pre>".print_r($fieldMaxArray, 1);
	$maxLen = $fieldMaxArray[$dbTable][$name] ? "maxlength = {$fieldMaxArray[$dbTable][$name]}" : '';
	// KLUDGE START
	if($dbTable == 'tblcontact' && !$maxLen) {
		$contactDbFieldName = substr($name, 0, strpos($name, '_')); // name_emergency, name_neighbor, location_emergency, location_neighbor, 
		$maxLen = $fieldMaxArray[$dbTable][$contactDbFieldName] ? "maxlength = {$fieldMaxArray[$dbTable][$contactDbFieldName]}" : '';
		
	}
	// KLUDGE END
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	//$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$rowStyle = "style = 'height:2.2em;'";
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$value = safeValue($value);
//if(staffOnlyTEST() || dbTEST('mobilemutts')) $value0 = htmlDiff("$value0", "$value");
	$value0 = htmlDiff("$value0", "$value");
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td><td><input $inputClass id='$name' name='$name' value='$value' $onBlur autocomplete='off' $maxLen></td>
  <td id='orig_$name' class='storedValue'>$value0</td></tr>\n";
}

function deltaDocumentFileRow($label, $name, $value=null, $value0=null, $labelClass=null, $inputClass=null, $dbTable=null, $rowId=null,  $rowStyle=null, $onBlur=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	//$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$rowStyle = "style = 'height:2.2em;'";
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td><td>&nbsp;</td>
  <td id='orig_$name' class='storedValue'>$value0</td></tr>\n";
}

//$displayFn($label.':', $field_N, $val, $val0)
function deltaLabelRow($label, $name, $value=null, $value0=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	//$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$rowStyle = "style = 'height:2.2em;'";
	if(!$rawValue) $value = safeValue($value);
// NOT NECESSARY SINCE IT WILL NOT CHANGE == if(staffOnlyTEST() || dbTEST('mobilemutts')) $value0 = htmlDiff("$value0", "$value");
	$labelEl = $name ? "<label for='$name'>$label</label>" : $label;
	echo "<tr $rowId $rowStyle>
  <td $labelClass>$labelEl</td><td>$value</td>
  <td id='orig_$name' class='storedValue'>$value0</td></tr>\n";
}

function deltaTextRow($label, $name, $value=null, $value0=null, $rows=3, $cols=37, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null) {
	$labelClass = $labelClass ? "class=$labelClass" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id=$rowId" : '';
	$rowStyle = $rowStyle ? "id=$rowStyle" : '';
	$maxlength = $maxlength ? "maxlength = $maxlength" : '';
	$value = safeValue($value);
//if(staffOnlyTEST() || dbTEST('mobilemutts')) $value0 = htmlDiff("$value0", "$value");
	$value0 = htmlDiff("$value0", "$value");
	echo "<tr $rowId $rowStyle>
	<td colspan=2 $inputClass><label $labelClass for='$name'>$label</label><br>
	<textarea rows=$rows cols=$cols id='$name' name='$name' $maxlength>$value</textarea></td>
	<td class='storedValue'>".htmlVersion($value0)."<span id='orig_$name' style='display:none'>$value0</span></td></tr>\n";
}

function deltaCheckboxRow($label, $name, $value=null, $value0=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null) {
  // DON'T USE ONCHANGE FOR IE6
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onChange = $onChange ? "onChange='$onChange'" : '';
	$checked = $value ? 'CHECKED' : '';
	$checked0 = $value0 ? 'Yes' : 'No';
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td><td><input type='checkbox' $inputClass id='$name' name='$name' $checked $onChange autocomplete='off'></td>
  <td id='orig_$name'  class='storedValue'>$checked0</td></tr>\n";
}


function deltaContactRows($contact, $type, $contact0, $showSuppliedClientFieldsOnly=false) {
	global $contactFields;
	$typeLabel = $type == 'neighbor' ? 'Trusted Neighbor' :
	             ($type == 'emergency' ? 'Emergency Contact' : '??');
	echo "<tr><td colspan=2>$typeLabel</td></tr>\n";
	
	hiddenElement("contactid_$type", $contact['contactid']);
	echo "<span id='orig_contactid_$type' style='display:none'>{$contact['contactid']}</span>";
//print_r($contact);	
	foreach($contactFields as $field => $label) {
		$val = $contact[$field];
		$val0 = $contact0[$field];
		$field_N = $field."_$type";
//echo "<br>VAL [$field_N]: $val";		
		if($field == 'haskey') {
			$displayFn = !array_key_exists($field, $contact) && $showSuppliedClientFieldsOnly ? 'deltaLabelRow' : 'deltaCheckboxRow';
			if($displayFn == 'deltaLabelRow') $val = $val0 = ($val0 ? 'yes' : 'no');
			$displayFn($label, $field_N, $val, $val0);
		}
		else if($field == 'note') {
			$displayFn = !array_key_exists($field, $contact) && $showSuppliedClientFieldsOnly ? 'deltaLabelRow' : 'deltaTextRow';
			if($displayFn == 'deltaLabelRow') $val = $val0;
			$displayFn($label.':', $field_N, $val, $val0, $rows=1, $cols=60, null, null, null, null, 60);
		}
		else if($field == 'location') {
			$displayFn = !array_key_exists($field, $contact) && $showSuppliedClientFieldsOnly ? 'deltaLabelRow' : 'deltaInputRow';
			if($displayFn == 'deltaLabelRow') $val = $val0;
			//, $labelClass=null, $inputClass='standardInput', $dbTable='tblclient'
			$displayFn($label.':', $field_N, $val, $val0, '', 'streetInput', $dbTable='tblcontact');
		}
//phoneRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $groupname=null) {
		else if($field == 'name') {
//if(mattOnlyTEST()) echo "<tr><td>[$field_N] [$val] [$val0]".print_r($contact0,1);			
			$displayFn = !array_key_exists($field, $contact) && $showSuppliedClientFieldsOnly ? 'deltaLabelRow' : 'deltaInputRow';
			if($displayFn == 'deltaLabelRow') $val = $val0;
			$displayFn($label.':', $field_N, $val, $val0, '', 'standardInput', $dbTable='tblcontact');
		}
		else {
			if(!$firstPhoneDone) {
				$firstPhoneDone = true;
				$textDirections = "<br>(T) means a phone can accept Text messages.";
				deltaLabelRow('', '', "Mark circle for primary phone number$textDirections", '', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
			}
			
			$displayFn = !array_key_exists($field, $contact) && $showSuppliedClientFieldsOnly ? 'deltaLabelRow' : 'deltaPhoneRow';
			$displayFn($label.':', $field_N, $val, $val0, null, null, null, null, "primaryphone_contact_$type");
		}
	}
}

function deltaPhoneRow($label, $name, $value=null, $value0=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $groupname=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass phone'" : "class='standardInput phone'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$groupname = $groupname ? $groupname : 'primaryphone';
	$analyzedINPUTNumber = analyzePhoneNumber($value);
	$selected = $analyzedINPUTNumber['primary'] ? 'CHECKED' : '';
	$value = $analyzedINPUTNumber['number']; //strpos($value, '*') === 0 ? substr($value, 1) : $value;
	$value = safeValue($value);
	
	$analyzedSTOREDNumber = analyzePhoneNumber($value0);
//if(mattOnlyTEST) echo "<tr><td>".print_r();
	$storedValue = $analyzedSTOREDNumber['number'];
$version = 2;
if($version == 2) {
	$textIcon = !$analyzedSTOREDNumber['number'] ? '' : ($analyzedSTOREDNumber['text'] ? 'yes' : 'no');
	$textIcon = !$textIcon ? '' : "<img height=15 width=15 src='art/SMS-$textIcon.gif' style='margin-right:5px'>";
	$analyzedSTOREDNumber['text'] ? '<img height=10 src="art/SMS-yes.gif">' : '<img src="art/SMS-no.gif">';
	$storedValue = "$textIcon$storedValue</b>";
	$title[] = $analyzedSTOREDNumber['text'] ? "Phone can accept text messages" : "Phone does not accept text messages.";;
	if($analyzedSTOREDNumber['primary']) {
		$primaryClass = " boldfont";
		$title[] = "Primary contact number";
	}
}
// #####
else {
	if($analyzedSTOREDNumber['text']) {
		$storedValue = "[T] $storedValue</b>";
		$title[] = "Phone can accept text messages";
	}
	if($analyzedSTOREDNumber['primary']) {
		$style = "style='font-weight:bold'";
		//$storedValue = "<b>$storedValue</b>";
		$title[] = "Primary contact number";
	}
}
// #####
	if($title) $title = "title='".join('. ', $title)."'";
	$smsButton = $analyzedINPUTNumber['text'] ? 'SMS-yes.gif' : 'SMS-no.gif';
	global $phoneRowHints; // in gui-fns.php
	$smsButtonTitle = $phoneRowHints[$analyzedINPUTNumber['text']];
	$smsToggle = "<img id='smsimg_{$groupname}_$name' height=15 width=15 src='art/$smsButton' style='cursor:pointer;' onClick='selectTextMessageTarget(\"$name\", \"$groupname\")' title=\"$smsButtonTitle\">";
	hiddenElement("sms_{$groupname}_$name", ($analyzedINPUTNumber['text'] ? 1 : '0'));
	hiddenElement("orig_sms_{$groupname}_$name", ($analyzedSTOREDNumber['text'] ? 1 : '0'));
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td>
  <td><input type='radio' name='$groupname' id='$groupname"."_$name' value='$name' $selected>&nbsp;$smsToggle
  		<input $inputClass id='$name' name='$name' value='$value' $onBlur autocomplete='off' section='$groupname'></td>
  <td id='orig_$name' $style class='storedValue$primaryClass' $title>$storedValue</td></tr>\n";
}

function originalPetsDiv($pets, $client) {
	global $petFields, $petTypes, $numExtraPets;
	echo "<div style='display:none;'>";
	$checked = $client['emergencycarepermission'] ? 'Yes' : 'No';
	echo "<span id ='orig_emergencycarepermission'>$checked</span>";

	$clientvisibleonly= userRole() == 'c';

	for($number=1; $number <= count($pets)+$numExtraPets; $number++) {
		$pet = $number > count($pets) ? array('active'=>1, 'fixed'=>1) : $pets[$number-1];
		foreach($petFields as $field => $label) {
			$field_N = $field."_$number";
			$val = $pet[$field];
			if($field == 'dob' && $val) $val = shortDate(strtotime($val));
			else if($field == 'active' || $field == 'fixed') $val = $val ?  'Yes' : 'No';
			echo "<span id ='orig_$field_N'>$val</span>";
		}
		
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "XXXXX";print_r(getCustomFields('active', 'visitsheetonly', getPetCustomFieldNames(), 'clientvisibleonly')); exit;}
		if($customFields = getCustomFields('active', 'visitsheetonly', getPetCustomFieldNames(), $clientvisibleonly)) {
			$petCustomFields = getPetCustomFields($pet['petid'], 'raw');
			foreach($customFields as $key => $descr) {
				$val = $petCustomFields[$key];
				if($descr[2] == 'boolean') $val = $val ?  'Yes' : 'No';
				$prettyVal = htmlVersion($val);
				echo "<span style='display:none;' id ='orig_pet$number"."_$key'>$val</span><span>$prettyVal</span>";
			}
		}
		
		
		
		
	}
	echo "</div>";
}

function deltaAddressTable($label, $prefix, $client, $client0, $showSuppliedClientFieldsOnly=false) {
	$raw = explode(',', 'street1,Address,street2,Address 2,city,City,state,State,zip,ZIP');
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	$i18Props = getI18NProperties() ? getI18NProperties() : array();
	foreach(array('state'=>'statelabel', 'zip'=>'zipcodelabel') as $k => $i18)
		if($i18Props[$i18]) $fields[$k] = $i18Props[$i18];
	echo "<tr><td>$label</td></tr>";
	foreach(array('zip','street1','street2','city','state') as $base) {
		$key = $prefix.$base;
		$displayFn = !array_key_exists($key, $client) && $showSuppliedClientFieldsOnly ? 'deltaLabelRow' : 'deltaInputRow';
		if($base != 'state') $displayFn($fields[$base].':', $key, $client[$key], $client0[$key], '', 'streetInput');
		else $displayFn($fields[$base].':', $key, $client[$key], $client0[$key]);
	}
}

function buildProfileRequest($arr) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {foreach($arr as $key => $val) {echo "$key: $val\n<br>\n";} exit;}
	foreach($arr as $key => $val) {
		if(in_array($key, array('MAX_FILE_SIZE', 'clientid', 'continueEditing', 'rd', 'pets_visible'))) continue;
		if(strpos($key, 'primaryphone') === 0) continue;
		//if(strpos($key, 'petid_') === 0) continue;
		//if(strpos($key, 'name_') === 0 && !trim($val)) continue;
		if($key  == 'checkboxes') {
			if($val) foreach(explodePairsLine($val) as $cbname => $cbval) $request[$cbname] = $cbval;
		}
		else $request[$key] = $val;
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') exit;
/* $_FILES:
Array ( [photo_1] => 
  Array ( [name] => 100_0246.jpg [type] => image/jpeg [tmp_name] => C:\WINDOWS\TEMP\php1F.tmp 
  	[error] => 0 [size] => 745672 ) 
  [photo_2] => Array ( [name] => [type] => [tmp_name] => [error] => 4 [size] => 0 ) [photo_3] => Array ( [name] => [type] => [tmp_name] => [error] => 4 [size] => 0 ) [photo_4] => Array ( [name] => [type] => [tmp_name] => [error] => 4 [size] => 0 ) [photo_5] => Array ( [name] => [type] => [tmp_name] => [error] => 4 [size] => 0 ) [photo_6] => Array ( [name] => [type] => [tmp_name] => [error] => 4 [size] => 0 ) [photo_7] => Array ( [name] => [type] => [tmp_name] => [error] => 4 [size] => 0 ) ) 
*/
	if($_FILES) foreach($_FILES as $param => $file)
	  if($file['error'] == 0) $request[$param] = substr($param, strpos($param, '_')+1);
	
	return $request;
}

function getProfileChangeRequests($request) {
	// $request is a tblclientrequest with requesttype Profile
	return fetchKeyValuePairs("SELECT field, value FROM tblclientprofilerequest 
				WHERE clientptr = {$request['clientptr']} AND requestptr = {$request['requestid']}");
}

function getProfileChangeRequestsForRequestID($requestid, $clientptr) {
	// $request is a tblclientrequest with requesttype Profile
	return fetchKeyValuePairs("SELECT field, value FROM tblclientprofilerequest 
				WHERE clientptr = $clientptr AND requestptr = $requestid");
}

function displayPetByPetIdDescriptionWithPhoto($pets, $petId, $theseFieldsOnly=null, $heading=null, $petChanges=null) {
	global $petFields;

	if($heading) echo "<tr><td colspan=2 class='storedValue' style='font-weight:bold';>$heading</td></tr>";
	foreach($pets as $petIndex => $pet) {
		if($pet['petid'] == $petId) {
			$petIndex = $petIndex + 1;
			break;
		}
	}
	// display description in 3 column widths, with photo occupying the third width
	if($petIndex > count($pets)) {
		echo "<tr><td colspan=3>This is a new pet.</td></tr><tr><td colspan=3><hr></td></tr>";
		return;
	}
	$SHOWDIFFS =  TRUE; //staffOnlyTEST() || dbTEST('mobilemutts');
	foreach($petFields as $key=>$label)  {
		if($theseFieldsOnly && !in_array($key, $theseFieldsOnly)) continue;
		$val = $pet[$key];
		if($key == 'sex') $val = $val == 'm' ? 'Male' : ($val == 'f' ? 'Female' : 'Unspecified');
		else if($key == 'fixed' || $key == 'active') $val = $val ? 'Yes' : 'No';
		else if($key == 'dob') $val = $val ? shortDate(strtotime($val)) : '';
		else if($petChanges && $SHOWDIFFS && array_key_exists($key, $petChanges)) $val = htmlDiff("$val", $petChanges[$key]);
		
		echo "<tr><td class='storedValue' style='border-top: 1px solid black'>$label:</td><td class='storedValue' style='border-top: 1px solid black'>$val</td>";
		if($key == 'name' && $pet['photo']) {
			$boxSize = array(200, 200);
			$photo = $pet['photo'];
			if(!file_exists($photo)) $photo = 'art/photo-unavailable.jpg';
			$dims = photoDimsToFitInside($photo, $boxSize);
			echo "<td rowspan=10 class='storedValue'><img src='pet-photo.php?version=display&id={$pet['petid']}' width={$dims[0]} height={$dims[1]}></td>";
		}
		echo "</tr>";
	}
	$customFields = getCustomFields('active', 'visitsheetonly', getPetCustomFieldNames(), 'clientvisibleonly');
	$petCustomFields = getPetCustomFields($pet['petid']);
	foreach($customFields as $key=>$descr)  {
		if($theseFieldsOnly && !in_array($key, $theseFieldsOnly)) continue;
		$val = 
			$descr[2] == 'boolean' ? ($petCustomFields[$key] ? 'yes' : 'no') : (
			$descr[2] == 'boolean' ? safeValue($petCustomFields[$key]) :  // WTF?!
			$petCustomFields[$key]);
		if($petChanges && $SHOWDIFFS) $val = htmlDiff("$val", $petChanges[$key]);
		
		echo "<tr><td class='storedValue' style='border-top: 1px solid black'>{$descr[0]}:</td>
				<td class='storedValue' style='border-top: 1px solid black'>$val</td>";
	}
	echo "<tr><td colspan=3><hr></td></tr>";
}

function showProfileChangeDisplayTable($source, $noExternalCSS=false) {
	global $rawBasicFields, $petFields, $homeFields;
	$rawBasicFields = getRawBasicFieldString();  
	$homeFields = getHomeFieldString(); // sometimes gets unset!
	
	$sectionCSS = 
		$noExternalCSS ? "style='border:solid black 2px;font-size:1.1em;background:lightblue;font-weight:bold;margin:15px;'" : "class='sectionHead'";
	
	$showCurrentValues = false;
	
	echo "<tr><td id='profilechanges' colspan=2 style='border: solid black 1px;background-color:white;'><table width=100%>";

	//if($_SESSION["auth_login_id"] != 'matt') return;

	$client = getClient($source['clientptr']);
	$changes = getProfileChangeRequests($source);
	$currentValHeader = $showCurrentValues ? "<th>Current Value</th>" : "";
	echo "<table style=''><tr><th colspan=2 width=315>&nbsp;</th>$currentValHeader</tr>";

	// BASIC SECTION
	$raw = explode(',', "$rawBasicFields");
	$fields = array();
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	$sectionChange = false;
	foreach($changes as $key => $val)
		if(isset($fields[$key]) || isset($fields["mail$key"])) $sectionChange = $key;
//if(mattOnlyTEST()) print_r($changes);
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td $sectionCSS colspan=3>Client Profile Changes</td><tr>";
		foreach($changes as $key => $val) {
			$label = $fields[$key];
			if(!array_key_exists($key, $fields)) continue;
			$cval = isset($client[$key]) ? $client[$key] : '';
			if($key == 'email') labelRow($label.':', $key);
			else if($key == 'notes') labelRow($label.':', $key, $val, $cval, 3, 60);
			else if(strpos($key, 'phone')) labelRow($label.':', $key, phoneDisplay($val));
			else if(mattOnlyTEST() && $key == 'vetname' || $key == 'clinicname') {
				labelRow($label.':', $key, $val);
			}
			else labelRow($label.':', $key, $val);

		}
	}

	// ADDRESS SECTION
	$raw = explode(',', 'street1,Address,street2,Address 2,city,City,state,State,zip,ZIP');
	$fields = array();
	for($i=0;$i < count($raw) - 1; $i+=2) {
		$fields[$raw[$i]] = $raw[$i+1];
		$fields["mail".$raw[$i]] = $raw[$i+1];
	}
	$sectionChange = false;
	foreach($changes as $key => $val)
		if(isset($fields[$key])) $sectionChange = $key;
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td $sectionCSS colspan=3>Addresses</td><tr>";

		$show = false;
		addressTable('Home Address', '', $changes, $client, true);
		echo "<tr><td>&nbsp;</td><tr>";
		addressTable('Mailing Address', 'mail', $changes, $client, true);
	}

	// PETS SECTION
	require_once "pet-fns.php";
	$sectionChange = false;
	$prefixes = 'XXname_,type_,sex_,breed_,color_,fixed_,dob_,description_,active_,notes_,dropphoto_,photo_';
	$customPetFields = getCustomFields('active', !'visitsheetonly', getPetCustomFieldNames(), !'clientvisibleonly');

	$pets = getClientPets($client['clientid']);
	$newPets = array();
	$petsById = array();
	foreach($pets as $p) $petsById[$p['petid']] =  $p;
	$petIdsByIndex = array();
	foreach($changes as $key => $val) {
		if(strpos($key, 'petid_') === 0) {
			if($val !== NULL) {
				$petIdsByIndex[nameSuffix($key)] = $val;
			}
		}
	}


//if(mattOnlyTEST()) print_r($petIdsByIndex);


	foreach($changes as $key => $val) {
		if(strpos($key, 'petid_') === 0) continue;
		$petIndex = nameSuffix($key);
		if(strpos($key, '_')
				&& (strpos($key, '_petcustom') || strpos($prefixes, substr($key, 0, strpos($key, '_')+1)))
				&& is_numeric(nameSuffix($key)))
			$sectionChange = $key;
	}

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<tr><td colspan=2>CHANGES: ".print_r($changes, 1). "[{$sectionChange}]"; }
	if($sectionChange || isset($changes['emergencycarepermission'])) {
		uksort($changes, 'petSort');
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr $sectionCSS><td colspan=3>Pets</td><tr>";
		if(isset($changes['emergencycarepermission']))
			booleanRow('Emergency Care Permission:',$changes['emergencycarepermission']);

		$lastPetIndex = 0;


		foreach($changes as $key => $val) {
			if(strpos($key, 'petid_') === 0) continue;
			$petIndex = nameSuffix($key);
			if(!is_numeric($petIndex)) continue;
			$thisPetId = $petIdsByIndex[$petIndex];
			$field = fieldNameBase($key);  //substr($key, 0, strpos($key, '_'));
			$changedFieldNames[] = $field;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($changedFieldNames); }
		//	show old values for previous section, if any, and start new pet section if index is not same as last
			$isNewPet = !$thisPetId;

			if($isNewPet) {
				if($newPets[$petIndex]) $pet = $newPets[$petIndex];
				else $newPets[$petIndex] = ($pet = array('name'=> 'New Pet'));
			}
			else $pet = $petsById[$thisPetId];

			//$pet =  $thisPetId ? $petsById[$thisPetId] : array('name'=> 'New Pet');
//if(mattOnlyTEST()) echo "<tr><td>[$thisPetId] ".print_r($petsById, 1);
			// $pet =!$isNewPet ? $pets[$petIndex-1] : array('name'=> 'New Pet');
//if($pet['name']	== 'New Pet' && mattOnlyTEST()) $pet['name'] = "{$pet['name']} ($petIndex)";
			if($petIndex != $lastPetIndex/*$thisPetId != $lastPetId*/) {
				//if($lastPetId) displayPetByPetIdDescriptionWithNoPhoto($pets, $lastPetId, $changedFieldNames, $heading='Current Pet State');
				$lastPetIndex = $petIndex;
				$lastPetId = $thisPetId;
				echo "<tr style='border-top:solid lightgrey 1px'><td colspan=3 style='padding-top:10px;text-align:center;text-decoration:underline;'>".($pet['name'] ? $pet['name'] : 'Unnamed Pet')."</td></tr>";
				if($isNewPet && !trim((string)$changes["name_$petIndex"])) inputRow('Name:', "name_$petIndex", "NO NAME SUPPLIED");
				//if(mattOnlyTEST()) {echo "<tr><td colspan=2>".print_r($changes, 1);}
			}
			$label = $field == 'dropphoto' ? 'Drop Photo': $petFields[$field];
			$customFieldType = null;
			if(!$label) {
				$label = substr($key, strpos($key, '_')+1);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($customPetFields); }
				$customField = $customPetFields[$label];
				$label = $customField[0];
				$customFieldType = $customField[2];
			}
			$sexes = array(0=>'none','m'=>'Male','f'=>'Female');
			if($field == 'sex') labelRow("$label:", $key, $sexes[$val], '');
			else if($customFieldType == 'boolean' || $field == 'fixed' || $field == 'active' || $field == 'dropphoto') booleanRow("$label:", $key, $val);
			else if($customFieldType == 'text' || $field == 'notes') {
				labelRow($label.':', $key, $val, $rows=1, $cols=60, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=3);
			}
			else if($field == 'photo') {
				echo "<tr><td valign=top>New Photo:</td><td colspan=2>Photo Supplied</td></tr>";
			}
			else labelRow($label.':', $key, $val);
		}
		//if($thisPetId) displayPetByPetIdDescriptionWithNoPhoto($pets, $lastPetId, $changedFieldNames, $heading='Current Pet State');
	}

	// HOME SECTION
	$raw = explode(',', $homeFields);
	$fields = array();
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	$sectionChange = false;
	foreach($changes as $key => $val)
		if(isset($fields[$key])) $sectionChange = $key;
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td $sectionCSS colspan=3>Home Info</td><tr>";
		foreach($changes as $key => $val) {
			$label = $fields[$key];
			if(!array_key_exists($key, $fields)) continue;
			$cval = isset($client[$key]) ? $client[$key] : '';
			if($key == 'directions') labelRow($label.':', $key, $val, $cval, 3, 60);
			else if(strpos($key, 'phone')) {
				labelRow($label.':', $key, phoneDisplay($val));
			}
			else labelRow($label.':', $key, $val, $cval, $labelClass=null, $inputClass='standardInput', $dbTable='tblclient');
		}
	}

	// EMERGENCY SECTION
	$sectionChange = false;
	$prefixes = 'name_,location_,homephone_,workphone_,cellphone_,haskey_,note_';
	foreach($changes as $key => $val)
		if(strpos($key, '_')
				&& strpos($prefixes, substr($key, 0, strpos($key, '_')+1)) !== FALSE
				&& !is_numeric(nameSuffix($key)))
			$sectionChange = $key;
	if($sectionChange ) {
		$contacts = getKeyedClientContacts($client['clientid']);
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td $sectionCSS colspan=3>Emergency Contacts</td><tr>";
		$contact = isset($contacts['emergency']) ? $contacts['emergency'] : array();
		$contactChanges = contactChanges($changes, '_emergency');
		if($contactChanges) contactRows($contactChanges, 'emergency', $contact, true);
		$contact = isset($contacts['neighbor']) ? $contacts['neighbor'] : array();
		$contactChanges = contactChanges($changes, '_neighbor');
		if($contactChanges) contactRows($contactChanges, 'neighbor', $contact, true);
	}

	// CUSTOM FIELDS
	$customFields = getCustomFields('active', 'visitsheetonly',null, 'clientvisibleonly');
	$sectionChange = array_intersect(array_keys($customFields), array_keys($changes));
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td $sectionCSS colspan=3>Custom Fields</td><tr>";
		$clientCustomFields = getClientCustomFields($client['clientid']);
//print_r($changes);
		foreach($customFields as $key => $descr) {
//echo "Key $key ({$descr[0]} - {$descr[2]}): [{$changes[$key]}] isset: (".array_key_exists($key, $changes).")<br>";
			if(!array_key_exists($key, $changes)) continue; // && $descr[2] != 'boolean'
			if($descr[2] == 'oneline')
				labelRow($descr[0].':', $key, $changes[$key], $clientCustomFields[$key], 'streetInput');
			else if($descr[2] == 'text')
				labelRow($descr[0].':', $key, $changes[$key], $clientCustomFields[$key], 3, 60);
			else if($descr[2] == 'boolean')
				booleanRow($descr[0].':', $key, $changes[$key]);
		}
	}
	echo "</table>";
	if($source['resolution']) echo "Resolution: request ".$source['resolution'];
	echo "</td></tr>";

}

function nameSuffix($name) {
	if(strpos($name, '_petcustom') !== false) {
		return substr($name, strlen('pet'), strpos($name, '_')-strlen('pet'));
	};
	if(strpos($name, '_') === false) return null;
	return substr($name, strpos($name, '_')+1);
}

function fieldNameBase($name) {
	if(strpos($name, '_petcustom') !== false) {
		return substr($name, strpos($name, '_')+1);
	};
	if(strpos($name, '_') === false) return null;
	return substr($name, 0, strpos($name, '_'));
}


function fieldOrder($a, $b) {
	$petFieldOrder = array('name','type','sex','breed','color','fixed','dob','description','active','notes');
	//global $petFieldOrder;
	if(strpos($a, 'etcustom')) $ai = 1000 + substr($a, strlen('petcustom'));
	else $ai = array_search(substr($a, 0 , strpos($a, '_')), $petFieldOrder);
	if(strpos($b, 'etcustom')) $bi = 1000 + substr($b, strlen('petcustom'));
	else $bi = array_search(substr($b, 0 , strpos($b, '_')), $petFieldOrder);
	return $ai < $bi ? -1 : ($ai > $bi ? 1 : 0);
}

function petSort($a, $b) {
	$asfx = nameSuffix($a);//substr($a, strpos($a, '_')+1);
	$asfx = is_numeric($asfx) ? (int)$asfx : 999;
	$bsfx = nameSuffix($b);//substr($b, strpos($b, '_')+1);
	$bsfx = is_numeric($bsfx) ? (int)$bsfx : 99;
	return $asfx < $bsfx ? -1 : ($asfx > $bsfx ? 1 :
	  ($asfx == 999 ? 0 : fieldOrder($a, $b)));
}

function contactChanges($changes, $suffix) {
	$fields = array();
	foreach($changes as $key => $val)
		if(strpos($key, $suffix))
		  $fields[substr($key, 0, strpos($key, $suffix))] = $val;
	return $fields;
}

function phoneDisplay($val) {	
	$analyzedINPUTNumber = analyzePhoneNumber($val);
	$selected = $analyzedINPUTNumber['primary'] ? 'CHECKED' : '';
	$val = $analyzedINPUTNumber['number'];
	$val = safeValue($val);
	$textIcon = !$analyzedINPUTNumber['number'] ? '' : ($analyzedINPUTNumber['text'] ? 'yes' : 'no');
	$textIcon = !$textIcon ? '' : "<img height=15 width=15 src='art/SMS-$textIcon.gif' style='margin-right:5px'>";
	$displayValue = "$textIcon$val</b>";
	$title[] = $analyzedSTOREDNumber['text'] ? "Phone can accept text messages" : "Phone does not accept text messages.";;
	if($analyzedINPUTNumber['primary']) {
		$primaryClass = " boldfont";
		$title[] = "Primary contact number";
	}
	$spanStart = $selected ? "<span style='font-weight:bold;' title='Primary number'>" : "";
	return "$spanStart$displayValue</span>";
}

// PERSISTENT PROFILE REQUEST FUNCTIONS (temporary, in the sense that these profiles exist only until
//   a profile change request is generated or the profile is deleted)
// At any given time, there is at most one TempClientProfile for a client.

function ensureTempClientProfile($clientptr) {
	$profileid = fetchRow0Col0("SELECT profileid FROM tbltempclientprofile WHERE clientptr = $clientptr LIMIT 1", 1);
	if(!$profileid) 
		$profileid = insertTable('tbltempclientprofile', 
			array('clientptr'=>$clientptr, 'created'=>date('Y-m-d H:i:s'), 'createdby'=>$_SESSION["auth_user_id"]),
			1); 
	return $profileid;
}

function fetchTempClientProfile($clientptr, $options=null) {
	$fulldetails = $options['fulldetails'];
	$includeCustomDescriptions = $options['customdescriptions'];
	$profile = fetchFirstAssoc("SELECT * FROM tbltempclientprofile WHERE clientptr = $clientptr LIMIT 1", 1);
	if($profile) {
		foreach(fetchAssociations("SELECT * FROM tbltempclientprofiledetail WHERE profileptr = {$profile['profileid']}", 1) 
							as $detail) {
			$key = $detail['field'];
			$val = $detail['value'];
			$parts = explode('_', $key);
			if(count($parts) == 2) {
				if(is_numeric($parts[1]) && $parts[1] == (int)$parts[1]) {// pet field
					$sequence = $parts[1];
					if(!$profile['details']['pets'][$sequence]) $profile['details']['pets'][$sequence]['sequence'] = $sequence;
					$profile['details']['pets'][$sequence][$parts[0]] = $fulldetails ? $detail : $val;
				}
				else if(strpos($parts[1], 'petcustom') === 0) { // pet custom fields.  e.g., pet3_petcustom1
					$sequence = substr($parts[0], strlen('pet'));
					if(!$profile['details']['pets'][$sequence]) $profile['details']['pets'][$sequence]['sequence'] = $sequence;
					$profile['details']['pets'][$sequence][$parts[1]] = $fulldetails ? $detail : $val;
				}
				else { //'name_,location_,homephone_,workphone_,cellphone_,haskey_,note_' .. emergency|neighbor
					$contactType = $parts[1];
					$contactType = in_array($contactType, array('emergency', 'neighbor')) ? $contactType : null;
					if($contactType)
						$profile['details'][$contactType][$parts[0]] = $fulldetails ? $detail : $val;
//echo "BANG! ".print_r($profile['details'], 1);				
				}
			}
			else $profile['details'][$key] = $fulldetails ? $detail : $val;
		}
		if($includeCustomDescriptions) addCustomDescriptions($profile);
	}
	return $profile;
}

function addCustomDescriptions(&$profile) {
			$profile['customfielddescriptions'] = profileCustomFieldDescriptions($pet=null);
			$profile['custompetfielddescriptions'] = profileCustomFieldDescriptions($pet=true);
}

function profileCustomFieldDescriptions($pet=null) {
	$prefix = $pet ? 'petcustom' : 'custom';
	if($pet) $fieldNames = getPetCustomFieldNames();
	$customFields = getCustomFields($activeOnly=true, $visitSheetOnly=true, $fieldNames, $clientVisibleOnly=true);
	$customFields = displayOrderCustomFields($customFields, $prefix);
	foreach($customFields as $id => $description)
		$descriptions[] = array('serverkey'=>$id, 'label'=>$description[0], 'type'=>$description[2]);
	return $descriptions;
}

function booleanRow($label, $val) {
	labelRow($label, '', ($val ? 'yes' : 'no'));
}

function contactRows($contact, $type, $contact0, $showSuppliedClientFieldsOnly=false) {
	require "contact-fns.php";
	global $contactFields;
	$contactFields = getContactFields();
	$typeLabel = $type == 'neighbor' ? 'Trusted Neighbor' :
	             ($type == 'emergency' ? 'Emergency Contact' : '??');
	echo "<tr><td colspan=2>$typeLabel</td></tr>\n";
	
	hiddenElement("contactid_$type", $contact['contactid']);
	echo "<span id='orig_contactid_$type' style='display:none'>{$contact['contactid']}</span>";
//print_r($contact);	
	foreach((array)$contactFields as $field => $label) {
		$val = $contact[$field];
		$val0 = $contact0[$field];
		$field_N = $field."_$type";
//echo "<br>VAL [$field_N]: $val";		
		if($field == 'haskey') {
			booleanRow($label, $field_N, $val, $val0);
		}
		else if($field == 'note') {
			labelRow($label.':', $field_N, $val, $val0, $rows=1, $cols=60, null, null, null, null, 60);
		}
		else if($field == 'location') {
			labelRow($label.':', $field_N, $val, $val0, $rows=1, $cols=60, null, null, null, null, 60);
		}
//phoneRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $groupname=null) {
		else if($field == 'name') {
		labelRow($label.':', $field_N, $val, $val0, '', 'standardInput', $dbTable='tblcontact');
		}
		else {
			labelRow($label.':', $key, phoneDisplay($val));
		}
	}
}



function profileDetailsFields() {
	return <<<FIELDS
<b>pet fields (for pet #3)</b>
-- name_3
-- active_3
-- type_3
-- breed_3
-- birthday_3
-- sex_3
-- fixed_3
-- color_3
-- description_3
-- notes_3
-- pet3_petcustom1
-- pet3_petcustom2
-- pet3_petcustom3
...
<b>contact fields</b>
-- name_emergency
-- location_emergency
-- homephone_emergency
-- workphone_emergency
-- cellphone_emergency
-- haskey_emergency
-- note_emergency

-- name_neighbor
-- location_neighbor
-- homephone_neighbor
-- workphone_neighbor
-- cellphone_neighbor
-- haskey_neighbor
-- note_neighbor

FIELDS;
}

function updateTempClientProfileDetail($profileptr, $field, $value, $clientptr=null) {
	return updateTempClientProfile($profileptr, array(array('field'=>$field, 'value'=>$value)), $clientptr);
}

function updateTempClientProfile($profileptr, $details, $clientptr=null) {	
	if(!$profileptr && !$clientptr) {
		echo "BAD CALL TO updateTempClientProfile!";
		return null;
	}
	else if(!$profileptr) {
		$profileptr = ensureTempClientProfile($clientptr);
		if(!$profileptr) {		echo "FAILED TO CREATE TempClientProfile!";
			return null;
		}
	}
	
	$deets =  array();
	updateTable('tbltempclientprofile', withModificationFields($deets), "profileid = $profileptr", 1);
	$clientptr = $clientptr ? $clientptr : fetchRow0Col0("SELECT clientptr FROM tbltempclientprofile WHERE profileid = $profileptr LIMIT 1", 1);
	$mods = 0;
	foreach((array)$details as $detail) {
		if($sequence = $detail['revertphoto']) {
			if(deleteTable('tbltempclientprofiledetail', 
						"profileptr = $profileptr AND field = 'photoupload_$sequence'",
						1))
				$mods += 1;
			if(deleteTable('tbltempclientprofiledetail', 
						"profileptr = $profileptr AND field = 'dropphoto_$sequence'",
						1))
				$mods += 1;
			foreach(glob("{$_SESSION['bizfiledirectory']}photos/pets/fromClient/temp{$clientptr}_$sequence.*") as $f)
				unlink($f);
		}
		else if($detail['value'] && is_array($detail['value']) && $detail['value']['revert']) {
			if(deleteTable('tbltempclientprofiledetail', 
						"profileptr = $profileptr AND field = '{$detail['field']}'",
						1))
				$mods += 1;
		}
		else if(replaceTable('tbltempclientprofiledetail',
									array('profileptr'=>$profileptr, 
													'field'=>$detail['field'], 
													'value'=>$detail['value'], 
													'clientptr'=>$clientptr,
													'cached'=>date('Y-m-d H:i:s')),
									1))
			$mods += 1;
	}
	return $mods;
}

function dropTempClientProfile($clientptr=null, $profileptr=null) {
	if(!$profileptr && !$clientptr) {
		echo "BAD CALL TO dropTempClientProfile!";
		return null;
	}
	else if(!$profileptr) {
		$profileptr = fetchRow0Col0("SELECT profileid FROM tbltempclientprofile WHERE clientptr = $clientptr LIMIT 1", 1);
		if(!$profileptr) {		echo "FAILED TO CREATE TempClientProfile!";
			return null;
		}
	}
	$clientptr = $clientptr ? $clientptr : fetchRow0Col0("SELECT clientptr FROM tbltempclientprofile WHERE profileptr = $profileptr LIMIT 1", 1);
	
	deleteTable('tbltempclientprofiledetail', "profileptr = $profileptr", 1);
	deleteTable('tbltempclientprofile', "profileid = $profileptr", 1);
	foreach(glob("{$_SESSION['bizfiledirectory']}photos/pets/fromClient/temp{$clientptr}_*") as $f)
		unlink($f);
	return true;
}

function createProfileChangeRequestFromJSON($clientptr=null) {
	/*
	Expectations:
	1. Each pet section in the client UI will be represented in the json in an array labeled "pets".
	2. Each pet will be a dictionary with 
		  sequence (a number representing its position in the UI list)
		  petid (the pet's ID in LT or null for new pets)
		  dropphoto - *optional* boolean indicating photo should be dropped from pet profile 
		  a set of properties to be changed
		  
	3. Assuming the photo will be uploaded using the normal URL-encoded multipart post mechanism, 
			the name of the form element for the posted photo file will be "photo_{$sequence}"
			when a pet photo is uploaded, the file will be stashed in "fromClient/temp{$clientptr}/{$pet['sequence']}.$extension"
	4. When a client wishes to "revert" an uploaded file, the file stashed earlier will be deleted.
	*/
	$request['clientptr'] = $clientptr ? $clientptr : $_SESSION["clientid"];
	$request['requesttype'] = 'Profile';
	$requestId = saveNewClientRequest($request, false);
	logChange($clientptr, 'tblclient', 'm', "Profile request created from JSON: $requestId");

//if(mattOnlyTEST()) { exit; }			
	
	$profile = fetchTempClientProfile($clientptr);
	$details = $profile['details'];
	if($details) {
		$currentProfile = fetchCurrentClientProfile($clientptr);
		$currentPetsInSequence = $currentProfile['pets'];
		foreach((array)($details['pets']) as $petChange) {
			insertTable('tblclientprofilerequest', 
										($mod = array('requestptr'=>$requestId, 
													'field'=>"petid_{$petChange['sequence']}", 
													'value'=>$currentPetsInSequence[$petChange['sequence']]['petid'], 
													'clientptr'=>$clientptr)), "1");
			$mods[] = $mod;
		}
		$changes = fetchKeyValuePairs("SELECT field, value FROM tbltempclientprofiledetail WHERE profileptr = {$profile['profileid']}", 1);
		if($changes) foreach($changes as $key => $val) {
	//if($db == 'dogslife') {
			require_once "field-utils.php";
	//if(mattOnlyTEST()) {echo "$val<br>"; }		
			if(!mattOnlyTEST()) 
				$val = cleanseString((string)$val);
	//}			

			insertTable('tblclientprofilerequest',
									array('clientptr'=>$clientptr, 'requestptr'=>$requestId, 'field'=>$key, 'value'=>$val), 1);
		}
	}
//return array('success'=>true, 'testresult'=>$mods);;	
	$request['requestid'] = $requestId;
	notifyStaffOfClientRequest($request);
	return $requestId;
}
?>