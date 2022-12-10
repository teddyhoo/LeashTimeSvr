<? // client-flag-picker.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-flag-fns.php";
require_once "preference-fns.php";

$locked = locked('o-');

$omitBillingFlags=$_REQUEST['omitbillingflags'];

if($_POST) {
	deleteTable('tblclientpref', "clientptr = {$_POST['clientptr']} AND property LIKE 'flag_%'", 1);
	if($_POST['includeBillingFlags']) deleteTable('tblclientpref', "clientptr = {$_POST['clientptr']} AND property LIKE 'billing_flag_%'", 1);
	$n = 1;
	$savedClientFlags =  array();
	$savedBillingFlags = array();
	foreach($_POST as $key => $val)
		if(strpos($key, "bizFlag_") === 0) {
			$flagNum = substr($key, strlen("bizFlag_"));
			setClientPreference($_POST['clientptr'], "flag_$n", 
													"$flagNum|".$_POST['flagnote_'.$flagNum]);
			$savedClientFlags[] = $flagNum;
			$n++;
		}
	if($_POST['includeBillingFlags']) {
		for($i=1; $i <= $maxBillingFlags; $i++) {
			if($_POST[($key ="billingFlag_$i")]) {
				setClientPreference($_POST['clientptr'], "billing_flag_$i", 
														($_POST['billflagnote_'.$i] ? $_POST['billflagnote_'.$i] : '|'));
				$savedBillingFlags[] = $i;
			}
		}
	}
	logChange($_POST['clientptr'], 'flags', 'm', "Client:".join(',', $savedClientFlags)." Billing:".join(',', $savedBillingFlags));
				
	$flagPanel = clientFlagPanel($_POST['clientptr'], $officeOnly=false, $noEdit=false, $contentOnly = true, $onClick=null,
		$includeBillingFlags=!$omitBillingFlags);
	echo "<script language='javascript'>if(parent.update) parent.update('flags', \"$flagPanel\"); parent.$.fn.colorbox.close();</script>";
	exit;
}
include "frame-bannerless.php";

if($_REQUEST['withname']) {
	$clientname = fetchRow0Col0(
		"SELECT CONCAT_WS(' ', fname, lname) 
			FROM tblclient 
			WHERE clientid = {$_REQUEST['clientptr']} LIMIT 1", 1);
	echo "<h2>$clientname</h2>";
}
clientFlagPicker($_REQUEST['clientptr'], $omitBillingFlags);