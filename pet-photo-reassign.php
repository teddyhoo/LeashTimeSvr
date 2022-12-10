<? // pet-photo-reassign.php
// v - visit id
// petid - chosen pet id
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";
require_once "preference-fns.php";
require_once "remote-file-storage-fns.php";

$locked = locked('o-');

$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#ec');


extract(extractVars('petid,v', $_REQUEST));

if($_POST) {
	if(!$v) $error = 'visit not specified';
	if(!$petid) $error = 'petid not specified';
	if(!$error) {
		if(!fetchRow0Col0("SELECT petid FROM tblpet WHERE petid = $petid LIMIT 1")) $error = 'pet not found';
		if(!fetchRow0Col0("SELECT appointmentid FROM tblappointment WHERE appointmentid = $v LIMIT 1")) $error = 'visit not found';
	}
	if(!$error) {
		$filecacheid = getAppointmentProperty($v, 'visitphotocacheid');
		if(!$filecacheid) $error = 'cached file id not found';
	}
	if(!$error) {
		$visitphotofile = getCachedFileAndUpdateExpiration($filecacheid);
		if(!$visitphotofile) $error = 'local visit photo file not found';
	}
	if(!$error) {
		$extension = strtolower(substr($visitphotofile, strrpos($visitphotofile, '.')+1));
		$finalPath = "{$_SESSION['bizfiledirectory']}photos/pets/$petid.$extension";
		foreach(glob("{$_SESSION['bizfiledirectory']}photos/pets/$petid.*") as $oldFile)
			unlink($oldFile);
		ensureDirectory(dirname($finalPath));
		copy($visitphotofile, $finalPath);
		makeDisplayImage($finalPath);
		outboardFile($finalPath);
		updateTable('tblpet', array('photo'=>$finalPath), "petid=$petid", 1);
		echo "<script language='javascript'>window.close();</script>";
	}
}

	

$client = 
	fetchFirstAssoc("SELECT clientid, CONCAT_WS(' ', fname, lname) as name
									FROM tblappointment
									LEFT JOIN tblclient ON clientid = clientptr
									WHERE appointmentid=$v LIMIT 1", 1);


$newPhotoURL = "appointment-photo.php?id=$v&maxdims=width,300,height,300";

$windowTitle = 'Update Pet Photo';
$extraHeadContent = '<script type="text/javascript" src="jquery-1.7.1.min.js"></script>';
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";

if($error) echo "<p class='warning'>$error</p>";

$pets = getActiveClientPets($client['clientid'], $cols='name,petid');

if(!$pets) {
	echo "{$client['name']} has no active pets";
	exit;
}
/*
foreach($pets as $pet) {
	$oldPhotoURL = "pet-photo.php?id={$pet['petid']}&version=display";
	echo "<table style='display:inline;border: solid gray 1px;'>
					<tr><th>{$pet['name']}</th></tr>
					<tr><td><img src='$oldPhotoURL'></td></tr>
				</table>";
}
*/
?>
<form name='assignform' method='POST'>
<?
hiddenElement('v', $v);
foreach($pets as $pet) {
	if(!$firstPetId) $firstPetId = $pet['petid'];
	$options[$pet['name']] = $pet['petid'];
}


if(TRUE) {
	echo "<table style=''borderwidth=0>";
	echo "<tr><td colspan=2><table style=''borderwidth=0>";
	radioButtonRow('First choose a pet...', 'petid', $value=null, $options, $onClick='showPhoto(this)', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=6, $nonBreakingSpaceLabels=true);
	echo "</table></td></tr>";
	echo "<tr id='photorow' style='display:none;'><td id='petphoto' style='padding-right:30px'></td>
				<td style='vertical-align:top;padding-right:30px'>And change its photo to: "
				.echoButton('', 'Set Pet Photo', 'assignPhoto()', $class='', $downClass='', 1, 'This will change the pet&apos;s photo')
				."<p><img src='$newPhotoURL'></td></tr></table>";
}
else { // OLD
	$radios = radioButtonSet('petid', $value=null, $options, $onClick='showPhoto(this)', $labelClass=null, $inputClass=null, $rawLabel=false);
	echo "<table style=''borderwidth=0><tr><td style='vertical-align:top;padding-right:30px'>Choose a pet:<p>";
	foreach($radios as $radio) echo "$radio<p>";
	echo "</td><td id='petphoto' style='padding-right:30px'></td>
				<td style='vertical-align:top;padding-right:30px'>... and then change its photo to: "
				.echoButton('', 'Set Pet Photo', 'assignPhoto()', $class='', $downClass='', 1, 'This will change the pet&apos;s photo')
				."<p><img src='$newPhotoURL'></td></tr></table>";
}
?>
</form>
<script language='javascript'>

<? if(count($pets) == 1) { ?>
	showPhoto('petid_<?= $firstPetId ?>')
<? } ?>

function assignPhoto() {
	var chosen;
	for(var i=0;i<document.assignform.elements.length;i++) {
		var el = document.assignform.elements[i];
		if(el.name == 'petid' && el.checked)
			chosen = el;
	}
	if(!chosen) {
		alert('Please choose a pet first.');
		return;
	}
	if(!confirm("Replace this pet's current photo with the visit photo shown?"))
		return;
	document.assignform.submit();
}

function showPhoto(el) {
	if(typeof el == 'string') {
		el = document.getElementById(el);
		el.checked = true;
	}
	$('#photorow').show(400);
	var src = "pet-photo.php?id="+el.value+"&version=display";
	document.getElementById('petphoto').innerHTML = "<img src='"+src+"'>";
	//alert(el.value);
}
</script>