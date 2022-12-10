<? // duplicate-board-clients.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Duplicate all active clients for A Leg Up pet services, appending " BOARD" to client last names

if(TRUE || !dbTEST('aleguppetservices')) {echo "WRONG DB!"; exit;}
set_time_limit(300);
foreach(fetchCol0("SELECT clientid FROM tblclient WHERE active") as $clientid) {
	$n+= 1;
	duplicateClient($clientid);
}
echo "<p>$n clients created.";
	

function duplicateClient($clientid) {
	$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid");
	$origclient = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid");
	unset($client['clientid']);
	unset($client['userid']);
	$client['emergencycontactptr'] = duplicateContact($clientid, $client['emergencycontactptr']);
	$client['trustedneighborptr'] = duplicateContact($clientid, $client['trustedneighborptr']);
	$client['training'] = 1;
	$client['lname'] = $client['lname'].' BOARD';
	$newclientid = insertTable('tblclient', $client, 1);
	duplicatePets($clientid, $newclientid);
	foreach(fetchAssociations("SELECT * FROM relclientcustomfield WHERE clientptr = $clientid") as $custField) {
		$custField['clientptr'] = $newclientid;
		insertTable('relclientcustomfield', $custField, 1);
	}
	foreach(fetchAssociations("SELECT * FROM relclientcharge WHERE clientptr = $clientid") as $custField) {
		$custField['clientptr'] = $newclientid;
		insertTable('relclientcharge', $custField, 1);
	}
	// skip discounts
	foreach(fetchAssociations("SELECT * FROM tblclientpref WHERE clientptr = $clientid") as $custField) {
		$custField['clientptr'] = $newclientid;
		insertTable('tblclientpref', $custField, 1);
	}
	foreach(fetchAssociations("SELECT * FROM tblkey WHERE clientptr = $clientid") as $custField) {
		unset($custField['keyid']);
		$custField['clientptr'] = $newclientid;
		insertTable('tblkey', $custField, 1);
	}
	$newclient = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $newclientid");
	
	echo "Duplicated: {$origclient['fname']} {$origclient['lname']} =>  {$newclient['fname']} {$newclient['lname']}<br>";
}
	
function duplicatePets($clientid, $newclientid) {
	$pets = fetchAssociationsKeyedBy("SELECT * FROM tblpet WHERE ownerptr = $clientid", 'petid');
	foreach($pets as $petid => $pet) {
		$pet['ownerptr'] = $newclientid;
		unset($pet['petid']);
		$newPetId = insertTable('tblpet', $pet, 1);
		foreach(fetchAssociations("SELECT * FROM relpetcustomfield WHERE petptr = $petid") as $custField) {
			$custField['petptr'] = $newPetId;
			insertTable('relpetcustomfield', $custField, 1);
		}
	}
}
	
function duplicateContact($clientid, $contactptr) {
	if(!$contactptr) return null;
	$contact = fetchFirstAssoc("SELECT * FROM tblcontact WHERE contactid = $contactptr");
	$contact['clientptr'] = $clientid;
	unset($contact['contactid']);
	return insertTable('tblcontact', $contact, 1);
}