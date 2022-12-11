<? // eula-fns.php

function getBizEULA($biz=null) {  // get EULA signed (or to be signed) by company manager
	//require include "common/init_db_common.php";
	if($biz) $eula = array('eulaversion'=>$biz['eulaversion'], 'eulasigned'=>$biz['eulasigned'], 'eulasigner'=>$biz['eulasigner']);
	else $eula = fetchFirstAssoc("SELECT eulaversion, eulasigned, eulasigner FROM tblpetbiz WHERE bizid = {$_SESSION['bizptr']} LIMIT 1");
	}
	if(!$eula['eulaversion']) $eula = getCurrentEULA();
	else {
		$signed = $eula['eulasigned'];
		$signer = $eula['eulasigner'];
		$eula = getCurrentEULA($eula['eulaversion']);
		$eula['eulasigned']  = $signed;
		$eula['eulasigner']  = $signerd;
	}
	if($eula['htmlurl']) $eula['terms'] = file_get_contents("agreements/{$eula['htmlurl']}");
//print_r($eula);	
	return $eula;
}

function getCurrentEULA($version=null) {
	//require include "common/init_db_common.php";
	$orgptr = $_SESSION['orgptr'] ? $_SESSION['orgptr'] : '0 OR orgptr IS NULL';
	$version = $version ? "AND eulaid = $version" : "";
	return fetchFirstAssoc("SELECT * FROM tbleula 
														WHERE orgptr = $orgptr $version ORDER BY eulaid DESC LIMIT 1");

}

function signEULA($eulaversion) {
	updateTable('tblpetbiz', array('eulaversion'=>$eulaversion, 'eulasigned'=>sqlVal('NOW()'), 'eulasigner'=>$_SESSION['auth_user_id']), "bizid = {$_SESSION['bizptr']}", 1);
}

function htmlizeEULA($rawText) {
	$rawText = str_replace("\n\n", '<p>', $rawText);
	$rawText = str_replace("\n", '<br>', $rawText);
	return $rawText;
}

function filterString($str) {
	$str = str_replace("“", '"', $str);
	$str = str_replace("”", '"', $str);
	$str = str_replace("’", "'", $str);
	return $str;
}
