<? //visit-sheet-simple.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "vet-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "appointment-fns.php";
require_once "visit-sheet-fns.php";
require_once "visit-sheet-simple-fns.php";
require_once "key-fns.php";
require_once "custom-field-fns.php";


locked('vc');

extract($_REQUEST);

$mapId = isset($mapId) ? $mapId : 'singlemap';

$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

$client = array_merge(getClient($id), getOneClientsDetails($id));
$appointments = getDayAppointments($id, $date);

foreach($appointments as $appt) {
	if($appt['note']) $notes[$appt['timeofday']] = $appt['note'];
}


//$googleAddress = "{$client['street1']} {$client['zip']}";
$googleAddress = "{$client['street1']}, {$client['city']} {$client['state']}";

$vet = $client['vetptr'] ? getVet($client['vetptr']) : array('nothing');

$clinic = $vet['clinicptr'] ? getClinic($vet['clinicptr']) : (
					$client['clinicptr'] ? getClinic($client['clinicptr']) : array('nothing'));

$pets = getActiveClientPets($id);


$secureMode = $_SESSION['preferences']['secureClientInfo'];

$labels = explodePairsLine('cellphone|Cell Phone||homephone|Home Phone||workphone|Work Phone');



$keys = getClientKeys($id);
$keyLabel = '';
if($keys) {
	$useKeyDescriptions = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'];
	if($useKeyDescriptions) $keyDescription = $keys[0]['description'];
	$keyLabel = sprintf("%04d", $keys[0]['keyid']);
	if(!$keyDescription && isset($provider))  {// find his copy of the key
//echo "PROV: $provider<p>";print_r($keys);exit;  
		foreach($keys[0] as $k => $possessor) {
			if(strpos($k, 'possessor') !== 0) continue;
			if($possessor == $provider) {
				$keyLabel .= '-'.sprintf("%02d", substr($k, 9 /* strlen('possessor') */));
				break;
			}
		}
	}
	//$keyIcon = "<img width=15 height=15 src='art/green-key.gif'>";
	//$keyLabel = $keyDescription ? "$keyIcon $keyDescription" : $keyIcon."#$keyLabel";
	$keys[0]['label'] = "#$keyLabel";
}
if($secureMode) {
	$keys[0]['label'] = '';
}


//foreach(getClientContacts($id) as $contact) $contacts[$contact['type']] = $contact;

$providerNames = isset($providerNames) ? $providerNames : getProviderShortNames();


$noContactInfo = $suppressContactInfoForEveryOne || ($_SESSION['preferences']['suppresscontactinfo'] && userRole() == 'p');
if($noContactInfo) {
	foreach(explode(',', 'email,email2,homephone,cellphone,cellphone2,workphone,homephone,fax,pager') as $x)
		unset($client[$x]);
}

// if($suppressContactInfoForEveryOne) still allow emergency contact info if it is not 
// if emergency contact info separately suppressed
if($_SESSION['preferences']['suppressEmergencyContactinfo']) {
	if(userRole() == 'p' || $suppressContactInfoForEveryOne) 
		$noEmergencycontacts = true;
}
if($noEmergencycontacts) $contacts = array();
else {
	$rawcontacts = getClientContacts($id);
	foreach($rawcontacts as $contact) $contacts[$contact['type']] = $contact;
}
//else {
//	$rawcontacts = getClientContacts($id);
//	foreach($rawcontacts as $contact) $contacts[$contact['type']] = $contact;
//}



if(!$secureMode && $keys && $keys[0]['bin']) {
	$client['key'] = "$keyLabel Hook: {$keys[0]['bin']}";
}

$data = array_merge($client);



$othername = safeValue(trim("{$client['fname2']} {$client['lname2']}"));
if($othername) $data['othername'] = $othername;
if($client['defaultproviderptr']) {

	$data['provider'] = $providerNames[$client['defaultproviderptr']];
}
$data['clientCustomFields'] = getClientCustomFields($data['clientid']);
if($vet) $data['vet'] = fullname($vet).' - '.$vet['officephone'];
if($clinic) $data['clinic'] = $clinic['clinicname'].' - '.$clinic['officephone'];
if($pets) {
	$petCustomFieldDescriptions = getCustomFields($activeOnly=true, $visitSheetOnly=1, getPetCustomFieldNames());
	$petCustomFieldDescriptions = displayOrderCustomFields($petCustomFieldDescriptions, 'petcustom');

	foreach($pets as $pet) {
		$pet = array_map('safeValue', $pet);
		$pet['sex'] = $pet['sex'] == 'm' ? 'Male ' : ($pet['sex'] == 'f' ? 'Female ' : 'Unspecified');
		$pet['fixed'] = $pet['fixed'] ? 'Yes' : 'No';
		$data['pets'][] = $pet;
		$petCustomFields[$pet['petid']] = getPetCustomFields($pet['petid']);
	}
	/*$data['pets'] = 'Urgent Veterinary Care has<br>'.
	     ($data['emergencycarepermission'] ? '' : "<span style='font-weight: bold;'><u>NOT</u></span> ").'been authorized.<p>'.
	     join('<p> ', $data['pets']);*/
}
if($contacts) {
	$types = array('emergency'=>'emergency Contact','neighbor'=>'Trusted Neighbor');
	foreach($contacts as $contact) {
		if(!$contact['type']) continue; // should not happen
		$descr = '';
		$more = trim($contact['name']);
		if($more) $descr .= $more;
		$descr .= '<br>'.($contact['haskey'] ? 'Has key to house' : 'Does not have key to house');
		$more = trim($contact['location']);
		if($more) $descr .= "<br>$more";
		foreach(array('cellphone','homephone','workphone') as $k) {
			$more = trim($contact[$k]);
			if($more) $descr .= "<br>(".$k[0].")$more";
		}
		$more = trim($contact['note']);
		if($more) $descr .= "<br>$more";
		$data[$contact['type']] = $descr;
	}
}
$data['status'] = $client['active'] ? 'Active' : 'Inactive';
if($client['prospect']) $data['status'] .= " Prospect";

$add = addressFields($client);
if($add) $data['homeaddress'] = $add;
$add = addressFields($client,'mail');
if($add) $data['mailaddress'] = $add;

if($id && $_SESSION["flags_enabled"]) {
	require_once "client-flag-fns.php";
	$flagPanel = clientFlagPanel($id, $officeOnly=true);
}
//if(mattOnlyTEST()) print_r($_SESSION['preferences']);

$wagPrimaryNameMode = getUserPreference($_SESSION['auth_user_id'], 'provuisched_client');
if(!$wagPrimaryNameMode) $wagPrimaryNameMode = 'fullname';

if(strpos($wagPrimaryNameMode, 'pets') !== FALSE) {
	require_once "pet-fns.php";
	$allPets = array(); 
	foreach(getPetNamesForClients(array($id), $inactiveAlso=false) as $cnid => $val)
		$clientPets = $val;

	$displayPets = $clientPets;
}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($clients);exit;; }	
$clientlabel = $wagPrimaryNameMode == 'fullname' ? $client['clientname'] : (
				 $wagPrimaryNameMode == 'name/pets' ? "{$client['lname']} ($displayPets)" : (
				 $wagPrimaryNameMode == 'pets' ? $displayPets : (
				 $wagPrimaryNameMode == 'pets/name' ? "$displayPets ({$client['lname']}) " : (
				 $wagPrimaryNameMode == 'fullname/pets' ? "{$client['clientname']} ($displayPets)" :  
				'???'))));


?>
<html>
<head><title>Visit Sheet</title>

<?if(!isset($suppressVisitSheetPrintLink)) echo "<script language='javascript' src='visit-sheet.js'></script>"; ?>

<? //include "googleMap.php"; ?>
</head>
<body onload="onLoad();"  onunload="GUnload()" 
	style='font-family: Arial, Helvetica, sans-serif; font-size: 8pt;background-image:none;background-color:white;'>

<? 
  if(!isset($suppressVisitSheetPrintLink)) echo " <a href='javascript:window.print()'>Print this Visit Sheet</a> ";
?>
<hr><hr>
<table width=100%>
<!-- tr><td><font><?= $_SESSION['preferences']['bizName'] ?></font></td></tr -->
</table>

<table border=1 bordercolor=black width=100%>
<!-- tr><td bgcolor="#CBE5E9">Client <?= " <span 'style='font-size:0.8em;font-weight:normal;'>$id</span>" ?></td></tr -->
<tr>
	<td colspan=2> <? // name, address, phones ?>
  <table width=100%>
  <tr bgcolor="#CBE5E9"><td colspan=2><font size=+1><b>Client <?= $clientlabel.$flagPanel ?></b></font></td></tr>
  <? if(strpos($wagPrimaryNameMode, 'fullname') === FALSE) { ?>
  <tr><td colspan=2>Name: <?= $client['clientname'] ?></td></tr>
  <? } ?>
  <tr><td colspan=2>
  	<?= !$data['othername'] ? '' 
  			: "Alt: ".$data['othername'].($data['cellphone2'] ? " <span style='font-weight:normal'>({$data['cellphone2']})</span>" : '')
  	?>
  </td></tr>
  <tr><td colspan=2><?= $data['homeaddress'] ?></td></tr>
<?
$includeAltPhoneHere = $data['othername'] ? '' : "||cellphone2|Alt. Phone";
$fields = "homephone|Home Phone||cellphone|Cell Phone||workphone|Work Phone||fax|FAX||pager|Pager{$includeAltPhoneHere}||email|Email||email2|Alt Email";
if($data['key']) $fields = 'key|Key||'.$fields;
$fields = explodePairsLine($fields);
dumpFieldsSimple($fields, $data);
?>
	</table>
	</td></tr>
<? if($data['directions']) { ?>	
	<tr>
	<td >DIRECTIONS<br><? // Directions ?>
			 <?= $data['directions'] ?>
	</td>
</tr>
<? } ?>
<tr bgcolor="#CBE5E9"><td colspan=2><b><?= $clientlabel ?> Visits for <?= longestDayAndDate(strtotime($date)); ?></b></td></tr>
<tr><td>
  <table>
<?
if(true) appointmentsTableSimple($appointments);
	//foreach($notes as $time => $note) 
	//	labelRow($time, '', htmlVersion($note), 'label', $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
else echo "<tr><td><i>No Visit Notes.</td></tr>";

?>
	</table>
</td></tr>
</table>

<table border=1 width=100% bordercolor=black>
<tr><td bgcolor="#CBE5E9"><b>EMERGENCY</b></td></tr>
<tr>
	<td> <? // emergency ?>
	<? $contact = $contacts['emergency']; ?>
  <table>
  <tr><td colspan=2><b>Emergency Contact</b></td></tr>
	<?
	$fields = explodePairsLine('name|Name||homephone|Home Phone||cellphone|Cell Phone||workphone|Work Phone||location|Location');
//if(mattOnlyTEST()) echo "XXXX".print_r($contacts, 1);
	dumpFieldsSimple($fields, $contact);
	?>
  <tr><td colspan=2><?= $contact['haskey'] ? 'Has key to house' : 'Does not have key to house' ?></td></tr>
  <? if($contact['note']) { ?>
		<tr><td colspan=2><b>Note</b></td></tr>
		<tr><td colspan=2><?= htmlVersion($contact['note']) ?></td></tr>
  <? } ?>
	</table>
	</td>
	<tr><td style='width:50%;border-left:solid black 1px;'> <? // trusted neighbor ?>
	<? $contact = $contacts['neighbor']; ?>
  <table class='borderless'>
  <tr><td class='label' colspan=2><b>Trusted Neighbor</b></td></tr>
	<?
	$fields = explodePairsLine('name|Name||homephone|Home Phone||cellphone|Cell Phone||workphone|Work Phone||location|Location');
	
	dumpFieldsSimple($fields, $contact);
	?>
  <tr><td colspan=2><?= $contact['haskey'] ? 'Has key to house' : ($contact ? 'Does not have key to house' : '')?></td></tr>
  <? if($contact['note']) { ?>
		<tr><td colspan=2><b>Note</b></td></tr>
		<tr><td colspan=2><?= htmlVersion($contact['note']) ?></td></tr>
  <? } ?>
	</table>
	</td>
</tr>
<tr><td bgcolor="#CBE5E9" colspan=2><b>VETERINARIAN</b></td></tr>
<tr>
	<td width='50%'>
  <table class='borderless'>
	<?
//echo "<tr><td>".print_r($vet,1);	
//echo "<tr><td>".print_r($clinic,1);	
	$fields = explodePairsLine('clinicname|Clinic||officephone|Office Phone||cellphone|Cell Phone||homephone|Home Phone||pager|Pager');
	dumpFieldsSimple($fields, $clinic);
  ?>
  <tr><td colspan=2><?= addressFields($clinic) ?></td></tr>
  <? if($clinic['notes']) { ?>
		<tr><td class='label' colspan=2>Note</td></tr>
		<tr><td colspan=2><?= htmlVersion($clinic['notes']) ?></td></tr>
  <? } ?>
  <? if($clinic['afterhours']) { ?>
		<tr><td class='label' colspan=2>After Hours</td></tr>
		<tr><td colspan=2><?= htmlVersion($clinic['afterhours']) ?></td></tr>
  <? } ?>
	</table>
	</td></tr>
	<tr><td width='50%'>
  <table class='borderless'>
	<?
	$vet['vetname'] = "{$vet['fname']} {$vet['lname']}";
	$fields = explodePairsLine('vetname|Veterinarian||officephone|Office Phone||cellphone|Cell Phone||homephone|Home Phone||pager|Pager');
	dumpFieldsSimple($fields, $vet);
	if($vet['clinicptr'] != $clinic['clinicid']) { 
	?>
  <tr><td colspan=2><?= addressFields($vet) ?></td></tr>
  <? if($vet['notes']) { ?>
		<tr><td class='label' colspan=2>Note</td></tr>
		<tr><td colspan=2><?= htmlVersion($vet['notes']) ?></td></tr>
  <? } ?>
  <? if($clinic['afterhours']) { ?>
		<tr><td class='label' colspan=2>After Hours</td></tr>
		<tr><td colspan=2><?= htmlVersion($vet['afterhours']) ?></td></tr>
  <? } ?>
  <? if($clinic['directions']) { ?>
		<tr><td class='label' colspan=2>Directions</td></tr>
		<tr><td colspan=2><?= htmlVersion($vet['directions']) ?></td></tr>
  <? }} ?>
  <? if($clinic['directions']) { ?>
		<tr><td class='label' colspan=2>Directions</td></tr>
		<tr><td colspan=2><?= htmlVersion($clinic['directions']) ?></td></tr>
  <? } ?>
	</table>
	</td>
</tr>
<tr><td bgcolor="#CBE5E9"><b>HOME</b></td></tr>
<tr>
<?  if($_SESSION['secureKeyEnabled']) { ?>
	<td> <? // key ?>
  <table class='borderless'>
	<? $key = $keys[0];
	if($data['nokeyrequired']) echo "<tr><td colspan=2>No key required.</td></tr>";
	else {
//echo "<tr><td>TEST: ".print_r($keys, 1);		
		$fields = explodePairsLine('label|Key||locklocation|Lock location||description|Description||bin|Hook||copies|Copies');
		dumpFieldsSimple($fields, $key);
	}
	?>
	</table>
	</td>
<? } ?>
<? if(!$secureMode) { ?>
	<tr><td> 
  <table>
	<tr><td class='label' colspan=2>ALARM</td></tr>
	<? 
	$fields = explodePairsLine('alarmcompany|Alarm Company||alarmcophone|Phone');
	dumpFieldsSimple($fields, $data);
	
?>
	<tr><td colspan=2><?= htmlVersion($data['alarminfo']) ?></td></tr>
	</table>
	</td>
</tr>
<?
}
?>
	<tr><td>
  <table>
	<? 
		$fields = explodePairsLine('leashloc|Leash / Pet Carrier Location||foodloc|Food Location');
		dumpFieldsSimple($fields, $data, 'force');
	?>
	</table>
	</td>
	<tr><td>
  <table width=100%>
	<? 
		$fields = 'parkinginfo|Parking Info';
		if(!$secureMode) $fields .= '||garagegatecode|Garage/Gate Code';
		$fields = explodePairsLine($fields);
		
		dumpFieldsSimple($fields, $data, 'force');
	?>
	</table>
	</td>
</tr>
<?


if($data['clientCustomFields']) {
	$fields = getCustomFields(true);
	$fields = displayOrderCustomFields($fields, 'custom');

	?>
<tr><td bgcolor="#CBE5E9"><b>CUSTOM FIELDS</b></td></tr>
	
<tr>
	<td width='100%' colspan=2> <? // key ?>
  <table>
<? 
dumpSomeCustomFieldRows($data['clientCustomFields'], $fields); ?>
	</table>
	</td>
</tr>
<? } ?>

<tr><td bgcolor="#CBE5E9"><b>Client Notes</b></td></tr>
<tr><td><?= htmlVersion($data['notes']); ?></td></tr>

<tr><td class='sectionBar'><tr><td bgcolor="#CBE5E9"><b>Pets</b></td></tr>
</table>

<? 
	if(!$data['pets']) $data['pets'] = array();
	usort($data['pets'], 'orderByTypeAndName');
	foreach($data['pets'] as $pet) {
		if($pet['color']) $pet['breedcolor'][] = $pet['color'];
		if($pet['breed']) $pet['breedcolor'][] = $pet['breed'];
		if($pet['breedcolor']) $pet['breedcolor'] = join(' ', $pet['breedcolor']);
		if($pet['dob'] && $pet['dob'] != '12/31/1969') $pet['dob'] = longDate(strtotime($pet['dob']));
?>
<table border=1 bordercolor=gray>
<tr><td colspan=2 bgcolor=palegreen><b><i><?= $pet['name'].($pet['type'] ? " ({$pet['type']})" : '') ?></i></b></td></tr>
<tr>
	<td width='50%'>
  <table class='borderless'>
	<? 
		$fields = explodePairsLine('breedcolor|Breed and color||sex|Sex||fixed|Spay/Neuter||dob|Birthday||description|Description');
		dumpFieldsSimple($fields, $pet);
	?>
	</table>
	</td>
	<td width='50%'>
	<? dumpPetPicture($pet) ?>
	</td>
</tr>
<tr><td class='topborder' style='font-weight:bold;' colspan=2 ><?= $pet['name'] ?> Notes</td></tr>
<tr><td colspan=2><?= $pet['notes'] ? htmlVersion($pet['notes']) : '<i>None</i>' ?></td></tr>
<? // Custom Pet Fields ?>
<tr>
	<td colspan=2> <? // key ?>
  <table class='borderless'>
<? dumpSomeCustomFieldRows($petCustomFields[$pet['petid']], $petCustomFieldDescriptions); ?>
	</table>
	</td>
</tr>
<? } ?>
</table>

<?

