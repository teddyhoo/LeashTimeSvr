<?
// sitter-photo.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$ctypes = array('jpg'=>'jpeg', 'png'=>'png');


if(!$_SESSION || !$_SESSION["bizptr"] || ($_GET['bid'] && $_SESSION["bizptr"] != $_GET['bid'])) {
	if(!$_SESSION) {
		bootUpSession();
		$tempConnection = true;
	}
	if($_GET['bid']) {
		require "common/init_db_common.php";
		if(($biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$_GET['bid']}' LIMIT 1"))
			&& $biz['activebiz']) {
			// && // check for lockout? {
			reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
			$bizFileDirectory = "bizfiles/biz_{$biz["bizid"]}/";
		}
	}
	else {
		header("Content-Type: image/{$ctypes['jpg']}");
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers
		header("Content-Transfer-Encoding: binary");
		//header("Content-Length: ".strlen($out));

		readfile('art/nositterphoto.jpg');
		exit;
	}
}
else $bizFileDirectory = $_SESSION["bizfiledirectory"];

// Determine access privs
//$locked = locked('vp');  // ALLOW ANYONE WITH THE TOKEN TO SEE IT

$token = $_GET['token'];
$version = $_GET['version'];

if($token)
	$id = fetchRow0Col0(
		"SELECT providerptr 
			FROM tblproviderpref 
			WHERE property = 'phototoken' AND value = '$token'
			LIMIT 1", 1);
			
if($tempConnection) {
	session_unset();
	session_destroy();
}

$versions = array(
	'fullsize' => '',
	'display' => 'display/',
	'client' => 'fromClient/');
$version = !$version ? '' : $versions[$version];

if(!$id) $file = 'art/nositterphoto.jpg';
else {
	$file = "{$bizFileDirectory}photos/sitters/$version$id.jpg";
	if(!file_exists($file)) $file = "{$bizFileDirectory}photos/sitters/$version$id.jpeg";
	if(!file_exists($file)) $file = "{$bizFileDirectory}photos/sitters/$version$id.png";
	if(!file_exists($file)) $file = 'art/sitter-photo-unavailable.jpg';
}

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
