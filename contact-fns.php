<?
// contact-fns.php

$contactFields= array();
$raw = explode(',', 'name,Name,location,Location,homephone,Home Phone,workphone,Work Phone,cellphone,Cell Phone,haskey,Has key to the house,note,Note');
for($i=0;$i < count($raw) - 1; $i+=2) $contactFields[$raw[$i]] = $raw[$i+1];

function getContactFields() {
	$contactFields= array();
	$raw = explode(',', 'name,Name,location,Location,homephone,Home Phone,workphone,Work Phone,cellphone,Cell Phone,haskey,Has key to the house,note,Note');
	for($i=0;$i < count($raw) - 1; $i+=2) $contactFields[$raw[$i]] = $raw[$i+1];
	return $contactFields;
}


function getClientContacts($id, $type=null) {
	if(!$id) return array();
	$typeClause = $type ? "AND type = '$type'" : '';
	return fetchAssociations("SELECT * FROM tblcontact WHERE clientptr = $id $typeClause ORDER BY name");
}

function getKeyedClientContacts($id) {  // Assumes one contact of each type per client
	if(!$id) return array();
	return fetchAssociationsKeyedBy("SELECT * FROM tblcontact WHERE clientptr = $id", 'type');
}


function contactTable($contact, $type) {
	global $contactFields;
	$typeLabel = $type == 'neighbor' ? 'Trusted Neighbor' :
	             ($type == 'emergency' ? 'Emergency Contact (other than you)' : '??');
	echo "<table width=100%>\n<tr><td>$typeLabel</td></tr>\n";
	
	hiddenElement("contactid_$type", $contact['contactid']);
//print_r($contact);	
	foreach($contactFields as $field => $label) {
		$val = $contact[$field];
		$field_N = $field."_$type";
//echo "<br>VAL [$field_N]: $val";		
		if($field == 'haskey') checkboxRow($label, $field_N, $val);
		else if($field == 'note') textRow($label.':', $field_N, $val, $rows=1, $cols=30, null, null, null, null); //, 60);
		else if($field == 'location') inputRow($label.':', $field_N, $val, '', 'streetInput');
//phoneRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $groupname=null) {
		else if($field == 'name') inputRow($label.':', $field_N, $val);
		else phoneRow($label.':', $field_N, $val, null, null, null, null, "primaryphone_contact_$type");
	}
	echo "</table>\n";
}

function saveClientContacts($clientId) {
	if($_POST["contactid_emergency"] || $_POST["name_emergency"])
		saveClientContact('emergency', $clientId);
	if($_POST["contactid_neighbor"] || $_POST["name_neighbor"])
		saveClientContact('neighbor', $clientId);
}

function saveClientContact($type, $clientId, $contactData=null) {
	global $contactFields;
	$fromPost = !$contactData;
	$contactData = $contactData ? $contactData : $_POST;
	$contactId = $contactData["contactid_$type"];
  $contact = array('clientptr'=>$clientId);
  $fieldNames = array_keys($contactFields);
  $primaryPhoneField = '';
	$primaryPhoneKey = "primaryphone_contact_$type";
	
	foreach($contactData as $key => $val) {
		if(strpos($key, "sms_$primaryPhoneKey"."_") === 0 && $val) {
			$phoneKey = substr($key, strlen("sms_$primaryPhoneKey"."_"));
			$contactData[$phoneKey] = 'T'.$contactData[$phoneKey];
		}
	}
	
  if(isset($contactData[$primaryPhoneKey]) && $contactData[$primaryPhoneKey] && isset($contactData[$contactData[$primaryPhoneKey]]))
    $primaryPhoneField = $contactData[$primaryPhoneKey];
        
  $suffix = $fromPost ? "_$type" : '';
  foreach($fieldNames as $field) {
		$field_N = $field.$suffix;
	  $contact[$field] = $contactData[$field_N];
		if($field_N == $primaryPhoneField) 
		  $contact[$field] = '*'.$contact[$field];
	}
	if(!trim($contact['name'])) $contact['name'] = 'unnamed';
  $contact['type'] = $type;
  $contact['haskey'] = $contact['haskey'] ? 1 : 0;
	
  if(!$contactId) {
		return insertTable('tblcontact', $contact, 1);
	}
	else {
	  return updateTable('tblcontact', $contact, "contactid=$contactId", 1);
	}
}

function saveContactChanges($changes, $clientId, $contactId) {
	if(!$contactId) {
		//		create new contact as necessary
		if(!trim($changes['name'])) $changes['name'] = 'unnamed';
		$changes['clientptr'] = $clientId;
		$contactId = insertTable('tblcontact', $changes, 1);
	}
	else 
		updateTable('tblcontact', $changes, "contactid = $contactId", 1);
}