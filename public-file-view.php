<? //public-file-view.php
	// non-session native access
	// https://leashtime.com/public-file-view.php?nugget=...
require_once "common/init_db_common.php";

if($_REQUEST['nugget']) {
	require_once "encryption.php";
	$nugget = lt_decrypt($_REQUEST['nugget']);
	if(!$nugget) $error = "Corrupt nugget";
	else {
		$nugget = json_decode($nugget, true);
		if(!$nugget) $error = "Unreadable nugget";
		else {
			$bizptr = $nugget['bizptr'];
			$fileid = $nugget['fileid'];
			if(!($bizptr && $fileid)) $error = "Incomplete nugget".print_r($nugget,1);
			else {
				$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizptr AND activebiz = 1 LIMIT 1");
				if(!$biz) $error = "Mysterious nugget: $bizptr";
				else if($biz['lockout']) $error = "Nugget lock: $bizptr";
				reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
			}
		}
	}
	$id = $fileid;
}

if($error) {
	echo $error;
	exit;
}

require_once "remote-file-storage-fns.php";
require_once 'aws-autoloader.php';

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$validRemoteFileId = is_numeric($id) && $id > 0 && $id == round($id);

if(!$validRemoteFileId) {
	require_once "frame-bannerless.php";
	echo "<h2>File Not Found</h2>No file could be found for this identifer.";
	exit;
}

$entry = fetchFirstAssoc("SELECT * FROM tblremotefile WHERE remotefileid = $id LIMIT 1");

require_once "office-files-fns.php";
if(($fileDescription = getOfficeDoc($id)) && $fileDescription['hidden']) {
	echo "This page is unavailable at this time.";
	exit;
}

//if(mattOnlyTEST()) {echo "item: $id ".print_r($entry, 1); locked('p-'); exit;}
//echo "returnToBrowser({$entry['remotepath']}, {$_GET['download']}, $bizptr)";exit;
returnToBrowser($entry['remotepath'], $_GET['download'], $bizptr);
