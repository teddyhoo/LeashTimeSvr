<? // clientkey.php
// biz, key
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "key-fns.php";

if(usingMobileSitterApp()) locked('p-');
else locked('o-');

if($_REQUEST['biz'] !== $_SESSION["bizptr"]) $errors[] =  "Bad biz arg";
if(!$errors && (!($key = $_REQUEST['key']) || !($key = trim(substr($key, 0, strpos($key, '-')))))) $errors[] =  "Bad key arg";
if(!$errors && !($client = fetchRow0Col0("SELECT clientptr FROM tblkey WHERE keyid = $key LIMIT 1")))
	$errors[] =  "Key not found";
if($errors) 
	echo 'Errors:<ul><li>'.join('<li>', $errors).'</ul>';
else if(usingMobileSitterApp())
	globalRedirect("visit-sheet-mobile.php?id=$client");
else if(adequateRights('ka') && $_REQUEST['mode'] == 'k' && $_SESSION['secureKeyEnabled']) {
	require_once "key-fns.php";
	$keyId = $_REQUEST['key'];
	$key = getKey($key);
	if($key) {
		$parts = explode('-', $keyId);
		if(count($parts) == 2) {
			$copy = (int)($parts[1]);
			$loc = $key["possessor$copy"];
			if($loc && strpos("$loc", 'safe') !== FALSE) $destination = "key-check-out.php?keyid=$keyId";
			else if($loc) $destination = "key-check-in.php?keyid=$keyId";
			else $destination = "key-edit.php?id=$keyId";
			globalRedirect($destination);
			exit;
		}
	}
	
	if(!$destination) globalRedirect("client-edit.php?id=$client");
}
else if(userRole() == 'o' 
				|| (userRole() == 'd' && adequateRights('#ec'))) {// dispatcher can edit clients
	globalRedirect("client-edit.php?id=$client");
}
else 
	globalRedirect("client-view.php?id=$client");
