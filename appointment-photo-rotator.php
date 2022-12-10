<? // appointment-photo-rotator.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "remote-file-storage-fns.php";
require_once "preference-fns.php";
require_once "gui-fns.php";

locked('o-');

$id = $_REQUEST['id'];

$filecacheid = getAppointmentProperty($id, 'visitphotocacheid');
if(!$filecacheid) $error = "No photo found for visit #$id";
else {
	$originalMaxDims = 'box_400_400';
	$thumbMaxDims = 'box_100_100';
	$cache = getCachedFileEntry($filecacheid);
	if(!$cache || !($file = getCachedFileAndUpdateExpiration($cache))) {
		// dump image not available
		$file = 'art/photo-unavailable.jpg';
		$error = "No photo cached for visit #$id";
	}
	else {
		if($_GET['original']) { // 90, 180, 270
			$maxdims = $originalMaxDims;
			dumpResizedVersion($file, $outName=null, $maxdims, $cacheResizedVersion=false);
			exit;
		}
		if($_GET['tiny']) { // 90, 180, 270
			//$maxdims = 'box_36_36';
			$maxdims = $thumbMaxDims;
			dumpResizedVersion($file, $outName=null, $maxdims, $cacheResizedVersion=false);
			exit;
		}
		else if($_GET['dumprotated']) { // 90, 180, 270
			$maxdims = $thumbMaxDims;
			dumpResizedVersion($file, $outName=null, $maxdims, $cacheResizedVersion=false, $rotation=$_GET['dumprotated']);
			exit;
		}
		else if($_GET['showrotated']) { // 90, 180, 270
			$maxdims = $originalMaxDims;
			dumpResizedVersion($file, $outName=null, $maxdims, $cacheResizedVersion=false, $rotation=$_GET['showrotated']);
			exit;
		}
		else if($_POST) { // 90, 180, 270
			if($_POST['saverotation']) {
				echo "Save rotated: {$_POST['saverotation']}. ".print_r($_POST, 1);
				$filecacheid = getAppointmentProperty($id, 'visitphotocacheid');
				// fetch original image
				// rotate it
				// save it as a temp file
				// push it to storage
				// refresh local cache
				rotateAndSaveImage($filecacheid, $_POST['saverotation']);
				if(in_array($_POST['saverotation'], array(90, 270)))
					$updateChange = 'rightangle';
				else $updateChange = 'same';
			}
			else ; // no-op
			echo "<script>if(window.opener) window.opener.update('photo', '$updateChange');
						window.close();</script>";
		}
	}
}
if($error) {
	echo $error;
	exit;
}

require "frame-bannerless.php";
$form = "<form name='saveit' method='POST'>
					<input type='hidden' name='id' value='$id'>
					<input type='hidden' name='saverotation' id='saverotation' value='0'>"
				.echoButton('', 'Save', $onClick='save()', $class='', $downClass='', $noEcho=true, $title='Save it as oriented below')
				."</form>";
echo "<h2>Rotate this visit photo $tiny </h2>$form";
?>
<table>
<tr><td colspan=4 style='text-align:center' width=600>
<img id='original' src='<?= "appointment-photo-rotator.php?id=$id&original=1" ?>'>
</td></tr>
<tr>
<td><img title='Original orientation' onclick='pick(0)' style='text-align:center' src='<?= "appointment-photo-rotator.php?id=$id&tiny=1"?>' ?></td>
<td><img onclick='pick(90)' style='text-align:center' src='<?= "appointment-photo-rotator.php?id=$id&dumprotated=90" ?>'></td>
<td><img onclick='pick(180)' style='text-align:center' src='<?= "appointment-photo-rotator.php?id=$id&dumprotated=180" ?>'></td>
<td><img onclick='pick(270)' style='text-align:center' src='<?= "appointment-photo-rotator.php?id=$id&dumprotated=270" ?>'></td>
</tr>
</table>
<script>
var id = <?= $id ?>;
function pick(rotation) {
	document.getElementById('saverotation').value = rotation;
	if(rotation)
		document.getElementById('original').src="appointment-photo-rotator.php?id="+id+"&showrotated="+rotation;
	else document.getElementById('original').src="appointment-photo-rotator.php?id="+id+"&original=1";
}
function save() {
	document.saveit.submit();
}
</script>
