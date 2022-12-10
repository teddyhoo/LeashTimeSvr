<? // prospect-request-form-custom.php
require_once "common/init_session.php";
require_once "gui-fns.php";

//if($_GET['bizid'] == 448) { echo ""; exit; } // deny Houston's Best
$bizid = $_GET['bizid'];
if("".(int)"$bizid" != $bizid) $bizid = 0; // against injection attacks 7/22/2020
if(!$bizid) { echo "No business ID supplied."; exit; }
else {
	require_once('common/init_db_common.php');
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizid' LIMIT 1");
	if(!$biz)  { echo "No business found for ID supplied."; exit; }
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);
	$enforceProspectSpamDetection = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enforceProspectSpamDetection'");
	$useFlexibleProspectForm = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'prospectFormFlexibleOptionSelected'");
	$phoneNumbersDigitsOnly = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'phoneNumbersDigitsOnly'");
	require 'common/init_db_common.php';
	
	if(!$_GET['standard'] && $useFlexibleProspectForm) require_once "prospect-request-form-custom-templated.php"; 
	else if($enforceProspectSpamDetection) require_once "prospect-request-form-customNEW.php";
	else require_once "prospect-request-form-customSTANDARD.php";
}
