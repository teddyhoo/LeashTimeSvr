<?
// pet-photo-public.php
// may be called by GET or 
// params (*=optional): $nugget
// included vars (*=optional): 
// *$includedPetId - overrides $id
// *$dimensionsonly - sets vars $width, $height

require_once "encryption.php";
$nugget = json_decode(lt_decrypt($_GET['nugget']), 'ASSOC');
$id =  $nugget['id'];
$bizid =  $nugget['bizid'];
$bizfiledirectory = $nugget['bizfiledirectory'];
$localBiz = null;
$versions = array(
	'fullsize' => '',
	'display' => 'display/',
	'client' => 'fromClient/');
$version = !$version ? '' : $versions[$version];
//print_r($nugget);exit;
if(!$id) $file = 'art/nopetphoto.jpg';
else if($version != 'fromClient/') {

	require_once "common/init_session.php";
	require_once "common/init_db_common.php";
	$localBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1", 1);
	
	reconnectPetBizDB($localBiz['db'], $localBiz['dbhost'], $localBiz['dbuser'], $localBiz['dbpass'], 1);
	if(!$localBiz) $file = 'art/photo-unavailable.jpg';
	else if(!($found = $file = fetchRow0Col0("SELECT photo FROM tblpet WHERE petid = $id LIMIT 1"))) 
			$file = 'art/nopetphoto.jpg';		
}
if($found) {
	if(FALSE && mattOnlyTEST() && $photo) {
		$dirName = dirname($photo);
		$basename = basename($photo);
		$file = "$dirName/$version$basename";
		//echo $file;exit;
	}
	else {
	$file = "{$bizfiledirectory}photos/pets/$version$id.jpg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpeg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.png";
	}
	
	if(!file_exists($file)) {
		require_once "common/init_session.php";
		require_once "common/init_db_common.php";
		$localBiz = $localBiz ? $localBiz : fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1", 1);
		if(!$localBiz) $file = 'art/photo-unavailable.jpg';
		else {
			if($db != $localBiz['db'])
				reconnectPetBizDB($localBiz['db'], $localBiz['dbhost'], $localBiz['dbuser'], $localBiz['dbpass'], 1);
			require_once "remote-file-storage-fns.php";
			if(remoteCacheAvailable()) {
				getPetPhotoFileCacheParameters();
				if($cache = fetchFirstAssoc(
					"SELECT * FROM tblfilecache 
						WHERE existsremotely AND localpath IN 
							('{$bizfiledirectory}photos/pets/$version$id.jpg',
							 '{$bizfiledirectory}photos/pets/$version$id.jpeg',
							 '{$bizfiledirectory}photos/pets/$version$id.png')
						LIMIT 1")) {
	}
	}
					getCachedFileAndUpdateExpiration($cache);
					$file = $cache['localpath'];
				}
			}
		}
	}
	
	if(!file_exists($file)) $file = 'art/photo-unavailable.jpg';
	
}

$file = globalURL($file);
//print_r($file);exit;

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
