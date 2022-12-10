<? //client-own-edit-segmented.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "client-profile-request-fns.php";
require_once "request-fns.php";

require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "client-fns.php";
include_once "vet-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "custom-field-fns.php";

$segmentedClientEditableProfile = $_SESSION['preferences']['segmentedClientEditableProfile'];
set_time_limit(5 * 60);

$locked = locked('c-');//locked('o-'); 

if(userRole() != 'c') {
	echo "This page is for clients only.  You are not logged in as a client.";
	exit;
}

extract($_REQUEST);

$id = $_SESSION["clientid"];
$client = getClient($id);
$error = null;

$clientPetContext = $segmentedClientEditableProfile ? 'client' : null;
$extraHeadContent = "
	<style>
	.profileTile {text-align:center;font-size:1.2em;font-weight:bold;display:block;width:85%;border:solid gray 1px;padding:5px;cursor:pointer;}
	</style>
	<style>
	td {vertical-align:top;}
	.labelcell {
		font-size: 1.05em;
		padding-bottom: 4px;
		border-collapse: collapse;
		vertical-align: top;
		background: lightgrey;
		font-weight: bold;
		width: 160px;
	}
	</style>
	";



if($mobileclient)	include "mobile-frame-client.php";
else include "frame-client.html";
	// ***************************************************************************
	echo "<h2>My Profile</h2>";
	echo "<table border=0 bordercolor=green>";
	echo "<tr><td style='vertical-align:top;width:30%'>";
	profileTile('client');
	$pets = fetchAssociations("SELECT * FROM tblpet WHERE ownerptr = $id ORDER BY active DESC, name");
	foreach($pets as $pet) profileTile($pet);
	profileTile('pet_new');
	echo "</td><td style='vertical-align:top;padding-top:0px;'>";
	showProfile($client);
	echo "</td><tr></table>";
	// ***************************************************************************
if($mobileclient)	include "mobile-frame-client-end.php";
else include "frame-end.html";

function profileTile($clientOrPet) {
	global $clientColor, $livePetColor, $deadPetColor, $addAPetColor;
	$clientColor = 'palegreen';
	$livePetColor = 'palegreen';
	$deadPetColor = '';
	$addAPetColor = 'yellow';
	$color ="background:$livePetColor";
	if($clientOrPet == 'pet_new') $color = "background:$addAPetColor";
	$label = 
		$clientOrPet == 'client' ? 'Edit My Profile' : (
		$clientOrPet == 'pet_new' ? 'Add a Pet' : "Edit {$clientOrPet['name']}&apos;s Profile");
	if(is_array($clientOrPet) && $clientOrPet['petid']) {
		if(!$clientOrPet['active']) {
			$clientOrPet['name'] = "&dagger; {$clientOrPet['name']}";
			$color ='';
			$divClass ="inactivePet";
		}
		$clientOrPet = "pet_{$clientOrPet['petid']}";
	}
	if(FALSE && $clientOrPet == 'client') $separator = "<img src='art/spacer.gif' width=10 height=1>";
	else $separator = "<img src='art/spacer.gif' height=10>";
																																									//display:block;width:225px;
//style='margin:7px;margin-top:0px;margin-left:0px;font-size:1.2em;font-weight:bold;display:block;width:95%;$color;border:solid gray 1px;padding:5px;cursor:pointer;'
																																									
	echo <<<TILE
<div class=' profileTile$divClass' style='$color;'
	onclick="document.location.href='client-own-edit.php?clientPetContext=$clientOrPet'">$label</div>$separator
TILE;
}


// ******************
function showProfile($client) {
	global $clientColor, $livePetColor, $deadPetColor, $addAPetColor;
	
	global $data;
	$vet = $client['vetptr'] ? getVet($client['vetptr']) : '';

	$clinic = $client['clinicptr'] ? getClinic($client['clinicptr']) : '';

	$pets = getActiveClientPets($client['clientid']);

	$keys = getClientKeys($id);

	$contacts = getClientContacts($id);

	$data = array_merge($client);
	$othername = safeValue(trim("{$client['fname2']} {$client['lname2']}"));
	if($othername) $data['othername'] = $othername;
	if($client['defaultproviderptr']) {
		$data['provider'] = getProviderShortNames();
		$data['provider'] = $data['provider'][$client['defaultproviderptr']];
	}
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

	$phones = explode(',', 'officephone,cellphone,pager,homephone');

	if($vet) {
		$addressLink = addressLink($vet);
		foreach($phones as $fld) if($phone = $vet[$fld]) break;
		$data['vet'] =
			fauxLink(fullname($vet), "viewClinic({$vet['vetid']}, 1)", 1, "View vet details")
			.($phone ? ' - '.$phone : '')
			.($addressLink ? ' - '.$addressLink : '');
	}

	if($clinic) {
		$addressLink = addressLink($clinic);
		foreach($phones as $fld) if($phone = $clinic[$fld]) break;
		$data['clinic'] =
			fauxLink($clinic['clinicname'], "viewClinic({$clinic['clinicid']}, 0)", 1, "View clinic details")
			.($phone ? ' - '.$phone : '')
			.($addressLink ? ' - '.$addressLink : '');
	}

	if($pets) {
		foreach($pets as $pet) {
			$pet = array_map('safeValue', $pet);
			$sex = $pet['sex'] == 'm' ? 'Male ' : ($pet['sex'] == 'f' ? 'Female ' : '');
			$fixed = $pet['fixed'] ? 'fixed' : 'not fixed';
			$name = $pet['photo'] ? fauxLink($pet['name'], "openConsoleWindow(\"petview\", \"pet-photo.php?id={$pet['petid']}&version=fullsize\", 320, 380)", 1, 'View Photo') : $pet['name'];
			//pet-view.php?id={$pet['petid']}
			$descr = "<b>$name</b> - $sex{$pet['type']} ($fixed)";
			$more = trim("{$pet['color']} {$pet['breed']}");
			if($more) $descr .= "<br>$more";
			$more = petAge($pet);
			if($more) $descr .= "<br>Age: $more";
			$more = $pet['description'];
			if($more) $descr .= "<br>$more";
			$more = $pet['notes'];
			if($more) $descr .= "<br>Note: $more";
			if($_SESSION['custom_pet_fields_enabled']) {
				ob_start();
				ob_implicit_flush(0);
				echo "<table>";
				dumpPetCustomFieldRows($pet, $visitSheetOnly=false);
				echo "</table>";
				$descr .= ob_get_contents();
				//echo 'XXX: '.ob_get_contents();exit;
				ob_end_clean();
			}
			$background = $pet['active'] ? $livePetColor : $deadPetColor;
			$data['pets'][] = "<div style='background:$background'>$descr</div>";
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
			if($contact['haskey']) $descr .= "<br>Has a key to client's house.";
			$more = trim($contact['note']);
			if($more) $descr .= "<br>$more";
			$data[$contact['type']] = $descr;
		}
	}
	$data['status'] = $client['active'] ? 'Active' : 'Inactive';
	if($client['prospect']) $data['status'] .= " Prospect";


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


	$add = addressFields($client);
	if($add) $data['homeaddress'] = $add;
	$add = addressFields($client,'mail');
	if($add) $data['mailaddress'] = $add;


	//echo "[{$_SESSION['flags_enabled']}]  [{$_SESSION['staffuser']}]";
	if($id && $_SESSION['flags_enabled']) {
		require_once "client-flag-fns.php";
		$flagPanel = clientFlagPanel($id, $officeOnly=false, $noEdit=true);
	}

	?>
	<span class='h2'><?= fullname($client) ?></span><br>


	<p>
	<table style="width:100%" border=0 bordercolor=red>
	<tr><td width=90%><table width=100% border=0 bordercolor=blue>
	<?
	$fields = explodePairsLine('othername|Spouse Name||email|Email||cellphone|Cell Phone||homephone|Home Phone||workphone|Work Phone||'.
										'othername|Alt Name||email2|Alt Email||cellphone2|Alt Phone||'.
									 'fax|FAX||pager|Pager||pets|Pets||vet|Veterinarian||clinic|Veterinary Clinic||'.
									 'emergency|Emergency Contact||neighbor|Trusted Neighbor');
	dumpFields($fields);


	$fields = explodePairsLine('homeaddress|Home Address||mailaddress|Mailing Address||directions|Directions to Home||'.
									 'leashloc|Leash Location||foodloc|Food Location||parkinginfo|Parking Info||garagegatecode|Garage/Gate Code||'.
									 //'alarmcompany|Alarm Company||alarmcophone|Alarm Company Phone||alarmpassword|Alarm Password||armalarm|Arm||disarmalarm|Disarm||alrmlocation|Alarm Location');
									 'alarmcompany|Alarm Company||alarmcophone|Alarm Company Phone||alarminfo|Alarm Info');

	if($noContactInfo) {
		foreach(explode(',', 'mailaddress') as $key) unset($fields[$key]);
	}
	dumpFields($fields);
	?>
	</table></td></tr>
	<tr><td colspan=2><table width=100%>

	<?
	$fields = explodePairsLine('notes|Notes');
	dumpFields($fields);

	echo "<tr><td width=33%>&nbsp;</td><td>&nbsp;</td></tr>";

	dumpCustomFieldRows($data, $visitSheetOnly=1, $oneColumn=0, $hideEmptyNonBooleans=true);
	?>

	</table>
	<p>
	<?
	dumpPetThumbnails($pets);

	echo "</table>";

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

	</script>
<?	
}
}

function addressLink($source) {
	$addr = array();
	foreach(array('street1','street2', 'city', 'state', 'zip') as $k) $addr[] = $source[$k];
	return oneLineAddress($addr);
}


