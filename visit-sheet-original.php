<? //visit-sheet-original.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "vet-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "appointment-fns.php";
require_once "service-fns.php";
require_once "visit-sheet-fns.php";
require_once "key-fns.php";
require_once "custom-field-fns.php";


locked('vc');

$gooleMapBroken =  TRUE;

extract($_REQUEST);

$mapId = isset($mapId) ? $mapId : 'singlemap';

$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

$client = getClient($id);
$appointments = getDayAppointments($id, $date);
//$googleAddress = "{$client['street1']} {$client['zip']}";
$googleAddress = "{$client['street1']}, {$client['city']} {$client['state']}";

$vet = $client['vetptr'] ? getVet($client['vetptr']) : '';

$clinic = $client['clinicptr'] ? getClinic($client['clinicptr']) : '';

$pets = getActiveClientPets($id);


$secureMode = $_SESSION['preferences']['secureClientInfo'];

if($secureMode || !$_SESSION['secureKeyEnabled']) $keyLabel = "&nbsp;";
else {
	$keys = getClientKeys($id);
	$keyLabel = '';
	if($keys) {
		$keyLabel = sprintf("%04d", $keys[0]['keyid']);
		if(isset($provider))  {// find his copy of the key
	//echo "PROV: $provider<p>";print_r($keys);exit;  
			foreach($keys[0] as $k => $possessor) {
				if(strpos($k, 'possessor') !== 0) continue;
				if($possessor == $provider) {
					$keyLabel .= '-'.sprintf("%02d", substr($k, 9 /* strlen('possessor') */));
					break;
				}
			}
		}

		$keyLabel = "Key # $keyLabel";
	}
}



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
$contacts = $noEmergencycontacts ? array() : getClientContacts($id);


//if(!($suppressContactInfoForEveryOne || ($_SESSION['preferences']['suppressEmergencyContactinfo'] && userRole() == 'p'))
//	$contacts = getClientContacts($id);

if(!$secureMode && $keys && $keys[0]['bin']) {
	$client['key'] = "$keyLabel Hook: {$keys[0]['bin']}";
}
$data = array_merge($client);


$othername = safeValue(trim("{$client['fname2']} {$client['lname2']}"));
if($othername) $data['othername'] = $othername;
if($client['defaultproviderptr']) {

	$data['provider'] = $providerNames[$client['defaultproviderptr']];
}
if($vet) $data['vet'] = fullname($vet).' - '.$vet['officephone'];
if($clinic) $data['clinic'] = $clinic['clinicname'].' - '.$clinic['officephone'];
if($pets) {
	foreach($pets as $pet) {
		$pet = array_map('safeValue', $pet);
		$sex = $pet['sex'] == 'm' ? 'Male ' : ($pet['sex'] == 'f' ? 'Female ' : '');
		$fixed = $pet['fixed'] ? 'fixed' : 'not fixed';
		$descr = "<b>{$pet['name']}</b> - $sex{$pet['type']} ($fixed)";
		$more = trim("{$pet['color']} {$pet['breed']}");
		if($more) $descr .= "<br>$more";
		$more = petAge($pet);
		if($more) $descr .= "<br>Age: $more";
		$more = withHTMLBreaks($pet['description']);
		if($more) $descr .= "<br>$more";
		$more = withHTMLBreaks($pet['notes']);
		if($more) $descr .= "<br>Note: $more";
		if($_SESSION['custom_pet_fields_enabled']) {
			ob_start();
			ob_implicit_flush(0);
			echo "<table>";
			dumpPetCustomFieldRows($pet, $visitSheetOnly=true);
			echo "</table>";
			$descr .= ob_get_contents();
			//echo 'XXX: '.ob_get_contents();exit;
			ob_end_clean();
		}
		$data['pets'][] = $descr;
	}

	if(dbTEST('poochydoos')) $emergencycarepermission = '';
	else $emergencycarepermission = 
		($data['emergencycarepermission'] ? '' : "<span style='font-weight: bold;'><u>NOT</u></span> ").'been authorized.<p>';

	$data['pets'] = 'Urgent Veterinary Care has<br>'.
	     $emergencycarepermission.join('<p> ', $data['pets']);


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

if($data['notes']) $data['notes'] = withHTMLBreaks($data['notes']);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo $data['packagenotes'];exit;}


if($id && $_SESSION["flags_enabled"]) {
	require_once "client-flag-fns.php";
	$flagPanel = clientFlagPanel($id, $officeOnly=true);
}

?>
<!DOCTYPE html>
<html>
<head><title></title>
<style>
.maplabel {color:black;font-size:12px;}
</style>
<link rel="stylesheet" href="<?= $cssPrefix /* set in visit-sheets.php */?>style.css" type="text/css" /> 
<link rel="stylesheet" href="<?= $cssPrefix ?>pet.css" type="text/css" /> 

<?if(!isset($suppressVisitSheetPrintLink)) echo "<script language='javascript' src='visit-sheet.js'></script>"; ?>
<? if(!$gooleMapBroken) include "googleMap.php"; ?>
</head>
<body onload="onLoad();"  onunload="GUnload()" style='background-image:none;'>


<style>
.topline td {font-size: 18px;}
td {vertical-align:top;}
.labelcell {
  font-size: 1.08em; 
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
  //background: lightgrey;
  border-top: solid black 1px;
  font-weight: bold;
}
.dataCell {
  font-size: 1.08em; 
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
  border-top: solid black 1px;
}
.jobstable {background: white;}
.jobstablecell {
		font-size: 1.05em; 
		padding-bottom: 4px; 
		border-collapse: collapse;
		vertical-align: top;
		border-top: solid black 1px;
	}
.dateRow {background: yellow;font-weight:bold;text-align:center;border:solid black 1px;}

</style>
<table class='topline' width=100%><tr>
<td>Client: <?= fullname($client)." [{$client['clientid']}]" ?></td>
<td><?= $keyLabel ?><?= $flagPanel ?></td>
<td align=right><?= date('F j, Y', strtotime($date)) ?></td>
</table>
<? 
  if(!isset($suppressVisitSheetPrintLink)) echo " <a href='javascript:window.print()'>Print this Visit Sheet</a> ";
?>
<p>
<table width=100% border=0 bordercolor=red>
<?
?>
<tr><td width=50%><table width=100%>
<?
echo "<tr><td colspan=2>";
reconnectPetBizDB();

appointmentsTable($appointments);
echo "</td></tr>";
$fields = explodePairsLine('othername|Alt Name||email2|Alt Email||cellphone2|Alt Phone||email|Email||key|Key||cellphone|Cell Phone||homephone|Home Phone||workphone|Work Phone|'.
	               'fax|FAX||pager|Pager||provider|Primary Sitter||pets|Pets||vet|Veterinarian||clinic|Veterinary Clinic||'.
	               'emergency|Emergency Contact||neighbor|Trusted Neighbor');
	               
dumpFields($fields, $oneCol=0, $data);
}
?>
</table></td>
<td><table width=100%>
<?
echo "<tr><td colspan=2>";
if(!$gooleMapBroken) $map->printMap();
echo "</td></tr>";

$fields = 'homeaddress|Home Address||mailaddress|Mailing Address||directions|Directions to Home||'.
								 'leashloc|Leash Location||foodloc|Food Location||parkinginfo|Parking Info';
if(!$secureMode) $fields .= '||garagegatecode|Garage/Gate Code';
$fields = explodePairsLine($fields);

dumpFields($fields, $oneCol=0, $data);


if(!$secureMode) dumpAlarmTable();
else if(!$_SESSION['preferences']['secureClientInfoNoAlarmDetailsAtAll']) {
  //$fields = explodePairsLine('companyandphone|Company||alarmpassword|Password||armalarm|Arm||disarmalarm|Disarm||alrmlocation|Location');
  $fields = explodePairsLine('companyandphone|Company||alarminfo|Alarm Info');
	$anyalarm = '';
  foreach(array_keys($fields) as $field) $anyalarm .= isset($data[$field]) ? $data[$field] : '';
  if($anyalarm)
		echo "<tr><td colspan=2><hr>Please consult summary list for Alarm info.<hr></td></tr>";
}

$fields = explodePairsLine('notes|Client Notes');

dumpFields($fields, $oneCol=0, $data);

echo "<tr><td width=33%>&nbsp;</td><td>&nbsp;</td></tr>";

dumpCustomFieldRows($data, true);
?>
</table></td></tr>
<tr><td colspan=2>
<?
dumpPetThumbnails($pets);

