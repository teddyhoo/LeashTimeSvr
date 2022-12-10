<?
// pet-photo-sessionless.php
//require_once "common/init_session.php";
//require_once "common/init_db_petbiz.php";
require_once "common/init_session.php";
require_once "native-sitter-api.php";

extract(extractVars('loginid,password,id,version', $_REQUEST));
if(is_string($userOrFailure = requestSessionAuthentication($loginid, $password))) {
	echo $userOrFailure;
	endRequestSession();
	exit;
}

$user = $userOrFailure;
if(strpos($user['rights'], 'p') !== 0) {
	echo "S";
	endRequestSession();
	exit;
}

$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
logScriptCallForThisDB();



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
else {
	$file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpeg";
	if(!file_exists($file)) $file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.png";
	
	if(!file_exists($file)) {
		require_once "remote-file-storage-fns.php";
		if(remoteCacheAvailable()) {
			getPetPhotoFileCacheParameters();
			if($cache = fetchFirstAssoc(
				"SELECT * FROM tblfilecache 
					WHERE localpath IN 
						('{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpg',
						 '{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpeg',
						 '{$_SESSION['bizfiledirectory']}photos/pets/$version$id.png')
					LIMIT 1")) {
//if(mattOnlyTEST()) {print_r($cache);exit;}
				getCachedFileAndUpdateExpiration($cache);
				$file = $cache['localpath'];
			}
		}
	}
	
	if(!file_exists($file)) $file = 'art/photo-unavailable.jpg';
}
endRequestSession();
$ctypes = array('jpg'=>'jpeg', 'png'=>'png');
$extension = substr($file, strrpos($file, '.')+1);
//if(mattOnlyTEST()) {echo "$file: Content-Type: image/{$ctypes[$extension]}";} else
header("Content-Type: image/{$ctypes[$extension]}");
header("Pragma: public"); // required
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private",false); // required for certain browsers
header("Content-Transfer-Encoding: binary");
//header("Content-Length: ".strlen($out));

readfile($file);
