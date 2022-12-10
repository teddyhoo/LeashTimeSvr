<? //client-print.php
// invoked from client-view.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "zip-lookup.php";
require_once "client-fns.php";
include_once "vet-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "custom-field-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

if(userRole() == 'p') {
	locked('p-');
	$noContactInfo = $_SESSION['preferences']['suppresscontactinfo'];
	$noEmergencyContactInfo = $_SESSION['preferences']['suppressEmergencyContactinfo'];
}
else locked('vc');

extract($_REQUEST);



$client = getClient($id);

$vet = $client['vetptr'] ? getVet($client['vetptr']) : '';

$clinic = $client['clinicptr'] ? getClinic($client['clinicptr']) : '';

$pets = getActiveClientPets($id);


$contacts = $noEmergencyContactInfo ? array() : getClientContacts($id);

$data = array_merge($client);
$othername = safeValue(trim("{$client['fname2']} {$client['lname2']}"));
if($othername) $data['othername'] = $othername;
if($client['defaultproviderptr']) {
	$data['provider'] = getProviderShortNames();
	$data['provider'] = $data['provider'][$client['defaultproviderptr']];
}

if($_SESSION['secureKeyEnabled']) {
	$keys = getClientKeys($id);
	$keyData = array();
	if($client['nokeyrequired']) $keyData[] = 'No Key Required.';
	if($keys) {
		$key = $keys[0];
		$providerNames = getProviderShortNames();
		$keyData[] = 'Number: '.sprintf("%04d", $key['keyid']);
		$keyData[] = 'Key Hook: '.$key['bin'];
		if($key['locklocation']) $keyData[] = 'Lock location: '.$key['locklocation'];
		if($key['description']) $keyData[] = 'Description: '.$key['description'];
		$keyData[] = 'Copies: '.$key['copies'];
		$keyData[] = keyComment($key);
	}
	$data['keydata'] = join('<br>', $keyData);
}

$phones = explode(',', 'officephone,cellphone,pager,homephone');

if($vet) {
	$oneLineAddress = getOneLineAddress($vet);
	foreach($phones as $fld) if($phone = $vet[$fld]) break;
	$data['vet'] =
		fullname($vet)
		.($phone ? ' - '.$phone : '')
		.($oneLineAddress ? ' - '.$oneLineAddress : '');
}
else if(!$infoOnly) $data['vet'] = 'No vet specified.';

if($clinic) {
	$oneLineAddress = getOneLineAddress($clinic);
	foreach($phones as $fld) if($phone = $clinic[$fld]) break;
	$data['clinic'] =
		$clinic['clinicname']
		.($phone ? ' - '.$phone : '')
		.($oneLineAddress ? ' - '.$oneLineAddress : '');
}
else if(!$infoOnly) $data['clinic'] = 'No vet clinic specified.';

if($pets) {
	foreach($pets as $pet) {
		$pet = array_map('safeValue', $pet);
		$sex = $pet['sex'] == 'm' ? 'Male ' : ($pet['sex'] == 'f' ? 'Female ' : '');
		$fixed = $pet['fixed'] ? 'fixed' : 'not fixed';
		$name = $pet['name'];
		//pet-view.php?id={$pet['petid']}
		$descr = "<b>$name</b> - $sex{$pet['type']} ($fixed)";
		$more = trim("{$pet['color']} {$pet['breed']}");
		if($more) $descr .= "<br>$more";
		$more = petAge($pet);
		if($more) $descr .= "<br>Age: $more";
		$more = $pet['description'];
		if($more) $descr .= "<br>$more";
		$more = htmlLineEnds($pet['notes']);
		if($more) $descr .= "<br>Note: $more";
		if($_SESSION['custom_pet_fields_enabled']) {
			ob_start();
			ob_implicit_flush(0);
			echo "<table id='petcustom'>";
			//function dumpPetCustomFieldRows($pet, $visitSheetOnly=false, $oneColumn=0, $hideEmptyNonBooleans=false, $props=null) 
			//dumpPetCustomFieldRows($pet, $visitSheetOnly=false, $oneColumn=0);
			dumpPetCustomFieldRows($pet, $visitSheetOnly=false, $oneColumn=0, $hideEmptyNonBooleans=false, $props=array('rowstyle'=>'border-bottom: solid gray 1px;'));
			echo "</table>";
			$descr .= ob_get_contents();
			//echo 'XXX: '.ob_get_contents();exit;
			ob_end_clean();
		}
		$data['pets'][] = $descr;
	}
	$data['pets'] = join('<p> ', $data['pets']);
}
if($contacts) {
	$types = array('emergency'=>'emergency Contact','neighbor'=>'Trusted Neighbor');
	foreach($contacts as $contact) {
		if(!$contact['type']) continue; // should not happen
		$descr = '';
		$more = trim($contact['name']);
		if($more) $descr .= $more;
		$more = trim($contact['location']);
		if($more) $descr .= "<br>$more";
		foreach(array('cellphone','homephone','workphone') as $k) {
			$more = trim(strippedPhoneNumber(($contact[$k])));
			if($more) $descr .= "<br>(".$k[0].")$more";
		}
		$more = trim($contact['note']);
		if($more) $descr .= "<br>$more";
		$data[$contact['type']] = $descr;
	}
}
$data['status'] = $client['active'] ? 'Active' : 'Inactive';
if($client['prospect']) $data['status'] .= " Prospect";


if(!function_exists('dumpFields')) {
function dumpFields($fields) {
	global $data;
	$primaryPhoneField = primaryPhoneField($data);
	foreach($fields as $field => $label)
		if(isset($data[$field])) {
			$val = $data[$field];
			if($val && $field == 'notes') $val = str_replace("\n", '<br>', $val);
			$raw = in_array($field, array('homeaddress', 'mailaddress', 'pets', 'emergency','neighbor', 'keydata', 'vet', 'clinic', 'notes'));
			if(strpos($field, 'phone')) $val = strippedPhoneNumber($val);
			if($field == $primaryPhoneField) {
				$raw = true;
				$val = "<b>$val</b>";
			}
			labelRow($label.':', '', $val, 'labelcell','sortableListCell','','',$raw);
		}
}

function addressFields($arr, $prefix='') {
	foreach(array('street1','street2','city','state','zip') as $f)
	  $add[$f] = $arr["$prefix$f"];

	return htmlFormattedAddress($add);
}
}


$add = addressFields($client);
if($add) $data['homeaddress'] = $add;
$add = addressFields($client,'mail');
if($add) $data['mailaddress'] = $add;

require "frame-bannerless.php";
//echo "[{$_SESSION['flags_enabled']}]  [{$_SESSION['staffuser']}]";
if($id && $_SESSION['flags_enabled']) {
	require_once "client-flag-fns.php";
	$flagPanel = clientFlagPanel($id, $officeOnly=false, $noEdit=true);
}

?>
<h2>Client: <?= fullname($client).($client['prospect'] ? ' (Prospect)' : '').(!$client['active'] ? ' <font color=red>(Inactive)</font> ' : '').$flagPanel ?></h2>

<? if(!(isset($noScriptOrStyle) && $noScriptOrStyle)) { ?>
<link rel="stylesheet" href="style.css" type="text/css" />
<link rel="stylesheet" href="pet.css" type="text/css" />
<style>
td {vertical-align:top;font-size: 1.3em;}
#petcustom td {vertical-align:top;font-size: 1.0em;}
.labelcell {
  font-size: 1.2em;
  padding-bottom: 4px;
  border-collapse: collapse;
  vertical-align: top;
  background: lightgrey;
  font-weight: bold;
}
</style>
<? } ?>
<?
	fauxLink('&#128424;', "window.print()", 0, 'Print this page.', null, 'fontSize1_4em');
	//echo " <a href='javascript:window.print()'>Print this page</a> ";
  echoButton('', 'Close', 'window.close()', 'closeButton', 'closeButtonDown');

?>
<p>
<table class='thistable' width=100% border=0 bordercolor=blue>
<?
$fields = explodePairsLine('othername|Spouse Name||email|Email||status|Status||cellphone|Cell Phone||homephone|Home Phone||workphone|Work Phone||'.
									'othername|Alt Name||email2|Alt Email||cellphone2|Alt Phone||'.
	               'fax|FAX||pager|Pager||provider|Primary Sitter||pets|Pets||vet|Veterinarian||clinic|Veterinary Clinic||'.
	               'emergency|Emergency Contact||neighbor|Trusted Neighbor');
if($noContactInfo) {
	foreach(explode(',', 'email,email2,cellphone,cellphone2,homephone,workphone') as $key) unset($fields[$key]);
}
dumpFields($fields);
?>
<?
$fields = explodePairsLine('homeaddress|Home Address||mailaddress|Mailing Address||keydata|Keys||directions|Directions to Home||'.
								 'leashloc|Leash Location||foodloc|Food Location||parkinginfo|Parking Info||garagegatecode|Garage/Gate Code||'.
	               //'alarmcompany|Alarm Company||alarmcophone|Alarm Company Phone||alarmpassword|Alarm Password||armalarm|Arm||disarmalarm|Disarm||alrmlocation|Alarm Location');
	               'alarmcompany|Alarm Company||alarmcophone|Alarm Company Phone||alarminfo|Alarm Info');
if(!$_SESSION['secureKeyEnabled']) {
	unset($fields['mod_securekey']);
}
if($noContactInfo) {
	foreach(explode(',', 'mailaddress') as $key) unset($fields[$key]);
}
dumpFields($fields);
?>

<?
$fields = explodePairsLine('notes|Notes||officenotes|Office Notes');
if(userRole() == 'p') unset($fields['officenotes']);

dumpFields($fields);

//dumpCustomFieldRows($data);
//function dumpCustomFieldRows($client, $visitSheetOnly=false, $oneColumn=0, $hideEmptyNonBooleans=false)
dumpCustomFieldRows($data, $visitSheetOnly=false, $oneColumn=0);
?>

</table>
<p>
<?
dumpPetThumbnails($pets);

echo "</table>";

function htmlLineEnds($str) {
	return str_replace("\n",'<br>', str_replace("\n\n",'<p>', str_replace("\r",'', $str)));
}

function getOneLineAddress($source) {
	$addr = array();
	foreach(array('street1','street2', 'city', 'state', 'zip') as $k) $addr[] = $source[$k];
	$addr = oneLineAddress($addr);
	if($addr)
		return fauxLink('(Map)', "openConsoleWindow(\"clinicmap\", \"https://maps.google.com/maps?hl=en&q=$addr\", 700, 700)",
							1, 'Map this address').' '.$addr;
}

 if(!(isset($noScriptOrStyle) && $noScriptOrStyle)) { ?>

<script language='javascript'>
function editClient(id, tab) {
	var baseWindow = window.opener;
	while(baseWindow.opener) baseWindow = baseWindow.opener;
	baseWindow.location.href='client-edit.php?id='+id+'&tab='+tab;
	<?= !(isset($noclose) && $noclose) ? 'window.close();' : '' ?>;
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function viewClinic(id, vet) {
	if(vet) {
		openConsoleWindow('clinic', 'viewVet.php?id='+id,700,500);
	}
	else {
		openConsoleWindow('clinic', 'viewClinic.php?id='+id,700,500);
	}
}
</script>

<? }
if(userRole() == 'p' && $banner) require "frame-end.html";

?>