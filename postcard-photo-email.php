<? // postcard-photo-email.php
/*
bid - LT bizid
root - greetings (if no attachment) or basename of postcard attachment, sans extension
dims - widthxheight
*/

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "postcard-fns.php";
require_once "gui-fns.php";
$lockChecked = true; // this image is available to all
$dims = $_GET['dims'] == null ? null : explode('x', $_GET['dims']);
if(!$_GET['bid'] || !$_GET['root']) dumpImage('postcard-missing.jpg', $dims); // EXIT
$biz = fetchFirstAssoc(
	"SELECT db, dbhost, dbuser, dbpass, activebiz, lockout 
	FROM tblpetbiz WHERE bizid = {$_GET['bid']} LIMIT 1", 1);
if(!$biz || !$biz['activebiz'] || $biz['lockout']) dumpImage('postcard-missing.jpg', $dims); // EXIT
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 'force');



$root = $_GET['root'];
if($root == 'greetings') dumpImage(greetingsImage(), $dims); // EXIT
else $root = "%/$root%";

$postcard =  fetchFirstAssoc("SELECT attachment, expiration FROM tblpostcard WHERE attachment LIKE '$root' LIMIT 1", 1);
//print_r($postcard);exit;
extract((array)$postcard);
if($attachment) {
	//$attachment = "{$_SESSION['bizfiledirectory']}photos/postcards/$attachment";
	if(file_exists($attachment)) {		
		if(attachmentType($attachment) == 'VIDEO') {
			if(!$dims) dumpVideo($attachment);
			else dumpImage('postcard-video.jpg', $dims);
		}
		else makeDisplayImage($attachment, $dims, $dumpToSTDOUT=true);
	}
	else if($expiration && strcmp(date('Y-m-d H:i:s'), $expiration) >= 0)
		dumpImage('postcard-expired.jpg', $dims);
	else if($expiration && strcmp(date('Y-m-d H:i:s'), $expiration) >= 0)
		dumpImage('postcard-missing.jpg', $dims);
}
//else if($_SESSION['bannerLogo']) makeDisplayImage($_SESSION['bannerLogo'], $dims, $dumpToSTDOUT=true);
else dumpImage(greetingsImage(), $dims);
