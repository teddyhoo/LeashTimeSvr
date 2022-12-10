<? // pet-photos.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "remote-file-storage-fns.php";

set_time_limit(5 * 60);

function checkFileCache($file) {
	return fetchRow0Col0("SELECT filecacheid FROM tblfilecache WHERE localPath = '$file' LIMIT 1");
}

if($_REQUEST['swapdisplay']) {
	// ensure photo does not exist
	$petid = $_REQUEST['swapdisplay'];
	$pet = fetchFirstAssoc("SELECT * FROM tblpet WHERE petid = $petid");
	$hasCache = remoteCacheAvailable();
	if(($hasCache && (!checkFileCache($pet['photo']) || checkAWSError($pet['photo']))) 
			|| (!$hasCache && !file_exists($pet['photo']))) {
		// get display version
		$photoName = "{$_SESSION['bizfiledirectory']}photos/pets/$petid.jpg";
		$displayVer = "{$_SESSION['bizfiledirectory']}photos/pets/display/$petid.jpeg";
		if(!file_exists($displayVer)) $displayVer = "{$_SESSION['bizfiledirectory']}photos/pets/display/$petid.jpg";
		
		copy($displayVer, $photoName);
		updateTable('tblpet', array('photo'=>$photoName), "petid = $petid", 1);
		if($hasCache) {
			$cacheid = cacheFile($photoName, $photoName, $overwrite=true);
			if($cacheid) {
				$cache = fetchFirstAssoc("SELECT * FROM tblfilecache WHERE filecacheid = $cacheid LIMIT 1");
				$toUpdate = array('existsremotely' => (saveCachedFileRemotely($cacheid) ? '1' : '0'));
				if($toUpdate['existsremotely']) {
					updateTable('tblfilecache', $toUpdate, "filecacheid = '$cacheid'", 1);
					echo "{$cache['localpath']} saved remotely.<br>";
					$saved += 1;
				}
				else {
					echo "FAILED TO SAVE $f.<br>";
					$failed += 1;
				}
			}
		}
		else echo "swapped in display image for {$pet['name']} ({$pet['petid']})";
	}
	else echo "photo already exists for {$pet['name']} ({$pet['petid']})";
	exit;
}
// show all pet photos by name
$petphotos = fetchAssociations(
"SELECT petid, name, photo, CONCAT_WS(' ', fname, lname) as client, c.active as clientactive, c.clientid
FROM tblpet
LEFT JOIN tblclient c ON clientid = ownerptr
WHERE photo IS NOT NULL
ORDER BY lname, fname, name");
//print_r($petphotos);
require_once "frame-bannerless.php";
echo "<h2>{$_SESSION['preferences']['bizName']} ({$_SESSION['bizptr']})</h1>";
echo count($petphotos)." pet photos found<P>";
?>
<table><tr><td>
<?
foreach($petphotos as $photo) {
	if($photo['client'] != $lastClient) {
		$lastClient = $photo['client'];
		echo "<p><a target='other' href='client-edit.php?id={$photo['clientid']}'>$lastClient</a><br>";
	}
	fauxLink("{$photo['name']} ({$photo['petid']})", "showPet({$photo['petid']})", 0, $photo['photo']);
	echo "<br>";
}
echo "<br>";
foreach($petphotos as $photo) {
	$displayVer = "{$_SESSION['bizfiledirectory']}photos/pets/display/{$photo['petid']}.jpeg";
	if(!file_exists($displayVer)) $displayVer = "{$_SESSION['bizfiledirectory']}photos/pets/display/{$photo['petid']}.jpg";
	$fixLink = !file_exists($displayVer) ? '' :
		" <a href='pet-photos.php?swapdisplay={$photo['petid']}' target='swappho'>Swap in display version</a>";
	if(!remoteCacheAvailable()) {
		$photoName = "".$photo['photo'];
		$error = file_exists($photoName) ? '' : "File [$photoName] does not exist.";
	}
	else $error = !checkFileCache($photo['photo']) ? "File not cached: {$photo['photo']}" : checkAWSErrorFAST($photo['photo']);
	if($error) {
		$errorCount += 1;
		if($photo['client'] != $lastClient) {
			$lastClient = $photo['client'];
			echo "<p><a target='other' href='client-edit.php?id={$photo['clientid']}'>$lastClient</a> ";
		}
		fauxLink("{$photo['name']} ({$photo['petid']})", "showPet({$photo['petid']})", 0, $photo['photo']);
		echo " $error $fixLink<br>";
		echo "<br>";
	}
}
$photoCount = count($petphotos);
echo "<hr>Pet photos: $photoCount<br>Errors: $errorCount";
?>
<td valign=top><img  id='screen'><br><img  id='displayversion'></td></table>
<script language='javascript'>
function showPet(id) {
	document.getElementById('screen').src='pet-photo.php?id='+id;
	document.getElementById('displayversion').src='pet-photo.php?version=display&id='+id;
}	
</script>
	
	
	