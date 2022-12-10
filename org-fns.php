<? // org-fns.php

function getOrganization() {
	global $dbhost, $db, $dbuser, $dbpass;
	if(!$_SESSION["orgptr"]) return null;
	
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$org = fetchFirstAssoc("SELECT * FROM tblbizorg WHERE orgid = {$_SESSION["orgptr"]} LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $org;
}
