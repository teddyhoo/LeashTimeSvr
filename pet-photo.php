<?
// pet-photo.php
// may be called by GET or 
// may be included by another script (look for $includedPetId)
// params (*=optional): $id, *$version
// included vars (*=optional): 
// *$includedPetId - overrides $id
// *$dimensionsonly - sets vars $width, $height
// *$localPathOnly - set $localPathOnly to the local path after ensuring availability
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Determine access privs
$locked = locked('vp');

extract($_GET);
if($includedPetId) $id = $includedPetId;

if(userRole() == 'c') {
  $owner = fetchRow0Col0("SELECT ownerptr FROM tblpet WHERE petid = $id LIMIT 1");
  if($owner !== $_SESSION["clientid"]) exit;
}

$versions = array(
	'fullsize' => '',
	'display' => 'display/',
	'client' => 'fromClient/');
$version = !$version ? '' : $versions[$version];

if(!$id) $file = 'art/nopetphoto.jpg';
else if($version != 'fromClient/' && 
				!($photo = fetchRow0Col0("SELECT photo FROM tblpet WHERE petid = $id LIMIT 1"))) $file = 'art/nopetphoto.jpg';
else {
	if(FALSE && mattOnlyTEST() && $photo) {
		$dirName = dirname($photo);
		$basename = basename($photo);
		$file = "$dirName/$version$basename";
		//echo $file;exit;
	}
	else {
	$file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpeg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.png";
	}
	
	
	if(!file_exists($file)) {
		require_once "remote-file-storage-fns.php";
		if(remoteCacheAvailable()) {
			getPetPhotoFileCacheParameters();
			if($cache = fetchFirstAssoc(
				"SELECT * FROM tblfilecache 
					WHERE existsremotely AND localpath IN 
						('{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpg',
						 '{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpeg',
						 '{$_SESSION['bizfiledirectory']}photos/pets/$version$id.png')
					LIMIT 1")) {
}
}
				getCachedFileAndUpdateExpiration($cache);
				$file = $cache['localpath'];
			}
		}
	}
	
	if(!file_exists($file)) $file = 'art/photo-unavailable.jpg';
	
}

if($localPathOnly) {
	$localPathOnly = $file;
}
else if($dimensionsonly) {
	list($width, $height) = getimagesize($file);
	if(!$includedPetId) {
		echo json_encode(array('width'=>$width,'height'=>$height));
		exit;
	}
}
else {
	$ctypes = array('jpeg'=>'jpeg', 'jpg'=>'jpeg', 'png'=>'png');
	$extension = substr($file, strrpos($file, '.')+1);
	} else
	header("Content-Type: image/{$ctypes[$extension]}");
	header("Pragma: public"); // required
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false); // required for certain browsers
	header("Content-Transfer-Encoding: binary");
	//header("Content-Length: ".strlen($out));

	readfile($file);
}