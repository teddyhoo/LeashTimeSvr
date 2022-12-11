<? // email-address-masking-fns.php
//require_once "common/init_session.php";
//require_once "common/init_db_petbiz.php";

$emailMask = 'X_OUT_';

function getEmailMaskingStats($db) {
	global $emailMask;
	$sql = "SELECT count(*) FROM $db.tblclient WHERE email IS NOT NULL AND email != ''";
	$results['clientEmails'] = fetchRow0Col0($sql, 1);
	$sql = "SELECT count(*) FROM $db.tblclient WHERE email IS NOT NULL AND email LIKE '$emailMask%'";
	$results['clientEmailsMasked'] = fetchRow0Col0($sql, 1);
	$sql = "SELECT count(*) FROM $db.tblclient WHERE email IS NOT NULL AND email NOT LIKE '$emailMask%'";
	$results['clientEmailsUnmasked'] = fetchRow0Col0($sql, 1);
	
	$sql = "SELECT count(*) FROM $db.tblclient WHERE email2 IS NOT NULL AND email2 != ''";
	$results['clientEmail2s'] = fetchRow0Col0($sql, 1);
	$sql = "SELECT count(*) FROM $db.tblclient WHERE email2 IS NOT NULL AND email2 LIKE '$emailMask%'";
	$results['clientEmail2sMasked'] = fetchRow0Col0($sql, 1);
	$sql = "SELECT count(*) FROM $db.tblclient WHERE email2 IS NOT NULL AND email2 NOT LIKE '$emailMask%'";
	$results['clientEmail2sUnmasked'] = fetchRow0Col0($sql, 1);

	$sql = "SELECT count(*) FROM $db.tblprovider WHERE email IS NOT NULL AND email != ''";
	$results['providerEmails'] = fetchRow0Col0($sql, 1);
	$sql = "SELECT count(*) FROM $db.tblprovider WHERE email IS NOT NULL AND email LIKE '$emailMask%'";
	$results['providerEmailsMasked'] = fetchRow0Col0($sql, 1);
	$sql = "SELECT count(*) FROM $db.tblprovider WHERE email IS NOT NULL AND email NOT LIKE '$emailMask%'";
	$results['providerEmailsUnmasked'] = fetchRow0Col0($sql, 1);
	
	return $results;
}

function maskAllEmails($db, $target) {
	global $emailMask;
	if($target != 'provider') {
		$sql = "UPDATE $db.tblclient SET email = CONCAT('$emailMask', email) 
							WHERE email IS NOT NULL AND email != '' AND email NOT LIKE '$emailMask%'";
		doQuery($sql, 1);
		$affected = mysqli_affected_rows();
		$sql = "UPDATE $db.tblclient SET email2 = CONCAT('$emailMask', email2) 
							WHERE email2 IS NOT NULL AND email2 != '' AND email2 NOT LIKE '$emailMask%'";
		doQuery($sql, 1);
		$affected += mysqli_affected_rows();
	}
	if($target != 'client') {
		$sql = "UPDATE $db.tblprovider SET email = CONCAT('$emailMask', email) 
							WHERE email IS NOT NULL AND email != '' AND email NOT LIKE '$emailMask%'";
		doQuery($sql, 1);
		$affected += mysqli_affected_rows();
	}
	
	return $affected;
}

function unmaskAllEmails($db, $target) {
	global $emailMask;
	$start = strlen($emailMask)+1;  // 1-based
	if($target != 'provider') {
		$sql = "UPDATE $db.tblclient SET email = substr(email, $start) 
							WHERE email LIKE '$emailMask%'";
		doQuery($sql, 1);
		$affected = mysqli_affected_rows();
		$sql = "UPDATE $db.tblclient SET email2 = substr(email2, $start)
							WHERE email2 LIKE '$emailMask%'";
		doQuery($sql, 1);
		$affected += mysqli_affected_rows();
	}
	if($target != 'client') {
		$sql = "UPDATE $db.tblprovider SET email = substr(email, $start) 
							WHERE email LIKE '$emailMask%'";
		doQuery($sql, 1);
		$affected += mysqli_affected_rows();
	}
	
	return $affected;
}
	
	
	
