<? // agreement-fns.php

function getCurrentServiceAgreement() {
	$agreements = getServiceAgreements();
	if($agreements) return current($agreements);
	$agreement = getCurrentCorporateAgreement();
	$agreement['agreementid'] = 0 - $agreement['agreementid'];
	return $agreement;
}

function getCurrentCorporateAgreement() {
	global $dbhost, $db, $dbuser, $dbpass;
	require_once "org-fns.php";
	if($org = getOrganization()) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		reconnectPetBizDB($org['db'], $org['dbhost'], $org['dbuser'], $org['dbpass']);
		$agreement = fetchFirstAssoc("SELECT * FROM tblserviceagreement ORDER BY date DESC LIMIT 1");
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		return $agreement;
	}
}

function getServiceAgreement($version, $withoutTerms=1) {  // assumes login as biz owner/dispatcher
	$fields = $withoutTerms ? 'agreementid,label,date,html' : '*';
	if($version > 0) 
		return fetchFirstAssoc("SELECT $fields FROM tblserviceagreement WHERE agreementid = $version LIMIT 1");
	else return getCorporateAgreementVersion(0-$version);
}

function getCorporateAgreementVersion($version) {
	global $dbhost, $db, $dbuser, $dbpass;
	require_once "org-fns.php";
//echo "[[$version - ".getOrganization()."]]";	
	if($org = getOrganization()) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		reconnectPetBizDB($org['db'], $org['dbhost'], $org['dbuser'], $org['dbpass'], 'force');
		$agreement = fetchFirstAssoc("SELECT * FROM tblserviceagreement WHERE agreementid = $version LIMIT 1");
//print_r(array($dbhost, $db));exit;

		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		return $agreement;
	}
}

function getServiceAgreements() { // descending date order
		return fetchAssociationsKeyedBy("SELECT * FROM tblserviceagreement ORDER BY date DESC", 'agreementid');
}

function agreementHistory($clientUserid) { // returns array(datetime=>version, ...)
	$pairs = fetchKeyValuePairs(
		"SELECT property, value 
			FROM tbluserpref 
			WHERE userptr = $clientUserid AND property LIKE 'agreement_%'
			ORDER BY property", 1);
	foreach($pairs as $key => $value) {
		$dtime = substr($key, strlen('agreement_'));
		$agreements[$dtime] = $value;
	}
	return (array)$agreements;
}

function clientAgreementSigned($clientUserid) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$agreement = fetchFirstAssoc("SELECT agreementptr, agreementdate FROM tbluser WHERE userid = $clientUserid LIMIT 1");	
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	if($agreement['agreementptr'] == 0) return null;
	foreach(getServiceAgreement($agreement['agreementptr']) as $key => $val)
		$agreement[$key] = $val;
	return $agreement;
}
	
function htmlizeAgreementText($rawText) {
	$rawText = str_replace("\r", '', $rawText);
	$rawText = str_replace("\n\n", '<p>', $rawText);
	$rawText = str_replace("\n", '<br>', $rawText);
	return $rawText;
}

function filterString($str) {
	$str = str_replace("\r", '', $str);
	$str = str_replace("“", '"', $str);
	$str = str_replace("”", '"', $str);
	$str = str_replace("’", "'", $str);
	$str = str_replace(chr(226).chr(128).chr(156), '"', $str);
	$str = str_replace(chr(226).chr(128).chr(157), '"', $str);
	$str = str_replace(chr(226).chr(128).chr(153), "'", $str);
	return $str;
}

function countSignatures($version) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$count = fetchRow0Col0(
		"SELECT COUNT(agreementptr) 
			FROM tbluser 
			WHERE bizptr = {$_SESSION["bizptr"]} AND agreementptr = $version", 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $count;
}