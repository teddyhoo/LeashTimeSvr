<? // pet-photo-rotator.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

locked('#ec');

extract($_REQUEST);
require "frame-bannerless.php";

if(array_key_exists('clockwise', $_GET))
	if(!rotatePetPhoto($id, $clockwise)) {
		$error = "Could not rotate photo.";
		$file = "{$_SESSION['bizfiledirectory']}photos/pets/display/$id.jpg";
		if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/display/$id.jpeg";
		if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/display/$id.png";
		if(!file_exists($file)) $error .= "  Display version could not be rotated either.";
		else {
			if(rotatePhoto($file, $clockwise))
		 		$error .= "  But display version was rotated.";
			else 
		 		$error .= "  Display version rotation failed also.";
		}
	}

function rotatorLink($id, $clockwise) {
	$counterclockwiseChar = '&#8634;';
	$clockwiseChar = '&#8635;';
	echo "<span style='corsor:pointer' onclick='document.location.href=\"?id=$id&clockwise=$clockwise\"'>"
				.($clockwise ? $clockwiseChar : $counterclockwiseChar)
				."</span>";
}
?>
<h2>Rotate:  <? rotatorLink($id, $clockwise=1); ?> <? rotatorLink($id, $clockwise=0, $ignoreMissingOriginal=true); ?></h2>
WARNING: Rotation is lossy.  Avoid unnecessary rotation.
<?
if($error) echo "<p>$error</p>";
?>
<img src="https://leashtime.com/pet-photo.php?id=<?= $id ?>&version=display">