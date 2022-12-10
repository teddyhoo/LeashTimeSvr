<? // pet-photos-outboard.php

// script to turn on pet photo outboarding

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "remote-file-storage-fns.php";


if(!adequateRights('o-') || !$_SESSION["auth_user_id"]) {echo "Must be logged in as a manager.";exit;}

set_time_limit(35 * 60);

function clearPetPhotosForInactiveClients() {
	$photos = fetchAssociations(
		"SELECT petid, photo, filecacheid, existslocally, existsremotely 
			FROM tblpet
			LEFT JOIN tblclient c ON clientid=ownerptr
			LEFT JOIN tblfilecache ON localpath = photo
			WHERE c.active = 0 AND photo IS NOT NULL");
	foreach($photos as $photo) {
		if($photo['filecacheid'] && file_exists($photo['photo'])) {
			if($photo['existsremotely']) {
				// dump the local copy
				unlink($photo['photo']);
				$cachedFile = array('existslocally'=>0);
				updateTable('tblfilecache', $cachedFile, "filecacheid = '{$photo['filecacheid']}'", 1);
				echo "<p>Dropped local copy of {$photo['photo']}";
			}
			else {
				// outboard it and then dump it
				$cachedFile = array('existslocally'=>0);
				$cachedFile['existsremotely'] = saveCachedFileRemotely($photo['filecacheid']) ?  '1' : '0';
				if($cachedFile['existsremotely']) {
					unlink($photo['photo']);
					updateTable('tblfilecache', $cachedFile, "filecacheid = '{$photo['filecacheid']}'", 1);
					echo "<p>Outboarded {$photo['photo']}";
				}
				else echo "<p>Failed to outboard {$photo['photo']}";
			}
		}
	}
	return $photos;
}

if($_POST) {
	$t0 = microtime(1);
	define_tblfilecache();
	getPetPhotoFileCacheParameters();
	$saved = 0;
	$failed = 0;
	foreach(($files = glob($_SESSION['bizfiledirectory'].'photos/pets/*')) as $f) {
		if(!$saved || $failed) echo "Found ".count($files)." files in photos/pets.<p>";
		if(!is_dir($f)) {
			if(!in_array(strtoupper(substr($f, strrpos($f, '.'))), array('.JPG', '.JPEG', '.PNG'))) {
				echo "<br>MISC FILE NOT SAVED: ".$f;
				continue;
			}
			//$sz = getimagesize($f);
			//echo basename($f).": {$sz[0]} X {$sz[1]}<br>";
			echo "Saving remotely: ".basename($f);
			echo ": ".($cacheid = cacheFile($f, $f, $overwrite=true));
			echo "<br>";
			if($cacheid) {
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
	}
	echo "Outboarded $saved files.  Failures: $failed.<p>";
	echo "Total time: ".number_format((microtime(1) - $t0))." seconds<br>";
}

echo "<h2>Outboard Photos for {$_SESSION['bizname']} ($db)</h2>\n";

echo "<a href='index.php'>Home</a><p>";

$not = !in_array('tblfilecache', fetchCol0("SHOW TABLES")) ? ' not' : '';;
echo "remote file storage is$not turned on for this business ({$_SESSION['bizfiledirectory']}).<p>";
$n = 0;
//echo "glob: ".count(glob($_SESSION['bizfiledirectory'].'photos/pets/*'))."<br>";
foreach(glob($_SESSION['bizfiledirectory'].'photos/pets/*') as $f) {
	if(!is_dir($f)) {
		if(!in_array(strtoupper(substr($f, strrpos($f, '.'))), array('.JPG', '.JPEG', '.PNG')))
			echo "<br>MISC: ".$f;
		$n++;
		$totalBytes += filesize($f);
		if(!$not && !fetchRow0Col0("SELECT filecacheid FROM tblfilecache WHERE localpath = '$f' LIMIT 1"))
			$missingFromCache[] = $f;
	}
}
echo "<p>$db $n files, ".number_format($totalBytes)." bytes.";
if($missingFromCache) echo "  Missing from cache: ".join(', ', $missingFromCache);
?>
<form name='outboard' method='POST'>
<input type=hidden name='go' value=1>
<? if($not) { ?>
<input type='button' value='Outboard Pet Photos' 
	onClick='if(confirm("Start outboarding?")) document.outboard.submit(); else alert("Canceled");'>
<? } 
	else {
		echo "<p>Pet photos are already outboarded!";
		echo "<br>tblfilecache size: ".(fetchRow0Col0("SELECT count(*) FROM tblfilecache"));
		echo "<br>present locally: ".(fetchRow0Col0("SELECT count(*) FROM tblfilecache WHERE existslocally = 1"));
		echo "<br>present remotely: ".(fetchRow0Col0("SELECT count(*) FROM tblfilecache WHERE existsremotely = 1"));
		if($missingFromCache) 
			echo "<br>missing from cache:<br>".join('<br>', $missingFromCache);
		$photosForInactiveBefore = clearPetPhotosForInactiveClients(); 
		//if($photosForInactiveBefore) echo "<p>Before: ".print_r($photosForInactiveBefore, 1);
	}
?>
</form>

