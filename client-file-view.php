<? //client-file-view.php
require_once "common/init_session.php";

if($_REQUEST['zumzum']) {
	require_once "encryption.php";
	$zumzum = lt_decrypt($_REQUEST['zumzum']);
	$zumzum = json_decode($zumzum, true);
	$id = $zumzum['id'];
	// non-session native access
	// https://leashtime.com/client-file-view.php?id=289&loginid=bball1&password=bball1
	require_once "native-sitter-api.php";
	$nativeAccess = true;
	if(is_string($userOrFailure = requestSessionAuthentication($zumzum['loginid'], $zumzum['password']))) {
		echo $userOrFailure;
		exit;
	}
	$user = $userOrFailure;
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
}
else {
	// https://leashtime.com/client-file-view.php?id=289
	require_once "common/init_db_petbiz.php";
	$id = $_REQUEST['id'];
}

require_once "remote-file-storage-fns.php";
require_once 'aws-autoloader.php';
$auxiliaryWindow = true; // prevent login from appearing here if session times out

if(!$nativeAccess) {
	if(userRole() == 'p') locked('p-');
	else if(userRole() == 'c') locked('c-');
	else locked('#ec');
}

$validRemoteFileId = is_numeric($id) && $id > 0 && $id == round($id);

if(!$validRemoteFileId) {
	require_once "frame-bannerless.php";
	echo "<h2>File Not Found</h2>No file could be found for this identifer.";
	exit;
}

$entry = fetchFirstAssoc("SELECT * FROM tblremotefile WHERE remotefileid = $id LIMIT 1");
}

if(userRole() == 'c') {
	require_once "office-files-fns.php";
	$clientsOwnDoc = $entry['ownerptr'] == $_SESSION["clientid"] && $entry['ownertable'] == 'tblclient';
	if(!$clientsOwnDoc) {
		$officeDoc = $entry['ownerptr'] == getOfficeOwnerPtr() && $entry['ownertable'] == 'office';
		if($officeDoc) {
			// enforce office visibility designation
			require_once 'preference-fns.php';
			$_SESSION["preferences"] = fetchPreferences(); // to get the latest status of office docs
			$visFiles = getOfficeFiles($audience='Clients', $visibility='visible');  //visibility=all(or null) | hidden | visible
			//print_r($visFiles);exit;
			$officeDoc = $visFiles[$id] ? 1 : 0;
		}
	}
	if(!($officeDoc || $clientsOwnDoc)) {
		$table = $entry['ownertable'] ? substr($entry['ownertable'], 3, 1) : '?';
		echo "This document is not available.  Ref #R{$entry['remotefileid']}$table{$entry['ownerptr']}/C{$_SESSION["clientid"]}";
		exit;
	}
}

}

returnToBrowser($entry['remotepath'], $_GET['download']);
