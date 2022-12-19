<?
// client-fns.php
//require_once "common/init_db_petbiz.php";
require_once "field-utils-2.php";

$pfields = 'clientid,fname,lname,fname2,lname2,active,prospect,'.
             'street1,street2,city,state,zip,'.  
             'mailstreet1,mailstreet2,mailcity,mailstate,mailzip,'.  
             'cellphone,homephone,workphone,fax,pager,email,clinicptr,vetptr,notes,officenotes,'.
//             'defaultproviderptr,directions,alarmcompany,alarmpassword,armalarm,disarmalarm,alrmlocation,'.
             'defaultproviderptr,directions,alarmcompany,alarminfo,'.
             'creditcard,cardnumber,cardlast4,cardcode,'.
             'setupdate,activationdate,deactivationdate,emergencycontactptr,trustedneighborptr,birthday,'.
             'leashloc,foodloc,parkinginfo,garagegatecode,alarmcophone,emergencycarepermission,'.
             /*'custom1,custom2,custom3,custom4,custom5,*/'nokeyrequired,cellphone2,email2,invoiceby,referralcode,referralnote,mailtohome';
$clientFields = explode(',', $pfields);

/*
function getUsableRequestFields() {
	require_once "gui-fns.php";
	$rfields = 'fname2|Alt First Name||lname2|Alt Last Name' // fname,lname,street1,street2,city,state,zip
						.'||mailstreet1|Mailing Address||mailstreet2|Mailing Address||mailcity|Mailing City||mailstate|Mailing State||mailzip|Mailing ZIP/Postal code'
            .'||cellphone|Cell Phone||workphone|Work Phone||fax|FAX||pager|Pager||cellphone2|Alt Phone||email2|Alt Email' // ||clinicptr||vetptr||notes|Notes
            .'||directions|Directions||alarmcompany|Alarm Company' // homephone||
            .'||birthday|Birthday||leashloc|Leash Location||foodloc|Food Location||parkinginfo|Parking Info||emergencycarepermission|Emergency Permission'
            .'||referralcode|Referral Code||referralnote|Referral Note';
  return explodePairsLine($rfields);
}*/
             
             
function getClient($id) {
  return fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid='$id' LIMIT 1");
}

function clientLabelForSitters(&$clientOrClientptr) {
	$client = is_array($clientOrClientptr) ? $clientOrClientptr 
						: fetchFirstAssoc("SELECT clientid, fname, lname FROM tblclient WHERE clientid = '$clientOrClientptr' LIMIT 1", 1);
	require_once "preference-fns-2.php";
	$nameStyle = fetchPreference('provuisched_client');
	if(!$nameStyle || $nameStyle == 'fullname') 
		return "{$client['fname']} {$client['lname']}";
	else {
		require_once "pet-fns.php";
		$pets = getClientPetNames($client['clientid'], $inactiveAlso=false, $englishList=false);
		$pets = $pets ? $pets : 'no pets';
		return 
			$nameStyle == 'name/pets' ? "{$client['lname']}\n($pets)" : (
			$nameStyle == 'pets/name' ? "$pets\n({$client['lname']})" : (
			$nameStyle == 'fullname/pets' ? "{$client['fname']} {$client['lname']}\n($pets)" : '??'));
	}
}


function saveNewClient($outData=null) { // use $_POST
  $outData = $outData ? $outData : array_merge($_POST);
  preprocessClient($outData);
  unset($outData['clientid']);
  if($_SESSION['trainingMode']) $outData['training'] = 1;
  return insertTable('tblclient', $outData, 1);
}

function saveClientPreferences($clientId, $data) {
	return null;
	$preferenceFields = '';  // dropped autoEmailCreditReceipts
	$booleans = explode(',', '');
	foreach(explode(',', $preferenceFields) as $property) {
		$value = $data[$property];
		if(in_array($property, $booleans)) $value = $value ? '1' : '0'; 
		setClientPreference($clientId, $property, $value);
	}
}

function saveClient($outData = null) { // use $_POST
  if(!$outData) $outData = array_merge($_POST);
  $clientid = $outData['clientid'];
  preprocessClient($outData);
  
  unset($outData['clientid']);
//print_r($outData);exit;  
  return updateTable('tblclient', $outData, "clientid=$clientid", 1);

}

function preprocessClient(&$client) {
	global $clientFields;
	foreach($client as $key => $val) {
		if($key == 'zip' && $client['zip_unprotected']) continue;
		else if($key == 'zip_unprotected' && $val) $client['zip'] = $val;
		else if(strpos($key, "sms_primaryphone_") === 0 && $val) {
			$phoneKey = substr($key, strlen("sms_primaryphone_"));
//echo "$key => $phoneKey => {$client[$phoneKey]}";exit;
			$client[$phoneKey] = 'T'.$client[$phoneKey];
		}
	}
	//print_r($client);exit;
  if(isset($client['primaryphone']) && $client['primaryphone'] && isset($client[$client['primaryphone']]))
    $client[$client['primaryphone']] = '*'.$client[$client['primaryphone']];
  $booleans = array('active', 'prospect', 'emergencycarepermission', 'nokeyrequired', 'mailtohome');
  foreach($booleans as $key)
    $client[$key] = isset($client[$key]) && $client[$key] ? 1 : 0;
  //if($_SESSION['preferences']['emergencycarepermission']) // 
  // 	$client['emergencycarepermission'] = 1;
  }
  foreach(array('setupdate','activationdate','deactivationdate') as $date)
    if($client[$date]) $client[$date] = date("Y-m-d",strtotime($client[$date]));
  //preprocessCustomFields($client);
//print_r($clientFields);exit;  
  foreach($client as $field => $unused)
    if(!in_array($field, $clientFields)) 
      unset($client[$field]);
  if(!$client['clientid']) $client['setupdate'] = date('Y-m-d');
}

function countAllClientIncompleteJobs($client=null, $asOfDate=null) {
	$asOfDate = $asOfDate ? "AND date <= '".date('Y-m-d', strtotime($asOfDate))."'" : '';
	$filter = $client ? "AND clientptr = $client" : '';
	return fetchRow0Col0(tzAdjustedSql(
		"SELECT count(*) 
			FROM tblappointment
			WHERE completed is null AND canceled is null $filter 
				AND (date < CURDATE() OR (date = CURDATE() AND starttime < CURTIME())) $asOfDate"));
}

function countAllClientIncompleteSurcharges($client=null, $asOfDate=null) {
	$asOfDate = $asOfDate ? "AND date <= '".date('Y-m-d', strtotime($asOfDate))."'" : '';
	$filter = $client ? "AND clientptr = $client" : '';
	return fetchRow0Col0(tzAdjustedSql(
		"SELECT count(*) 
			FROM tblsurcharge
			WHERE completed is null AND canceled is null $filter 
				AND (date < CURDATE() OR (date = CURDATE() AND starttime < CURTIME())) $asOfDate"));
}


function countAllIncompleteJobsByClient($asOfDate=null) {
	$asOfDate = $asOfDate ? "AND date <= '".date('Y-m-d', strtotime($asOfDate))."'" : '';
	return fetchKeyValuePairs(tzAdjustedSql(
		"SELECT clientptr, count(*) 
			FROM tblappointment
			WHERE completed is null AND canceled is null $filter 
				AND (date < CURDATE() OR (date = CURDATE() AND starttime < CURTIME())) $asOfDate
			GROUP BY clientptr"));
}


    
function getClientCharges($id, $ignoreNegativeCharges=true) {
	if(!$id) return array();
	if($ignoreNegativeCharges) $filter = "AND charge >= 0";
  return fetchAssociationsKeyedBy("SELECT * FROM relclientcharge WHERE clientptr=$id $filter", 'servicetypeptr');
}

function getAllClientsCharges($ids, $ignoreNegativeCharges=true) {
	if(!$ids) return array();
	if($ignoreNegativeCharges) $filter = "AND charge >= 0";
  return fetchAssociationsIntoHierarchy("SELECT * FROM relclientcharge WHERE clientptr IN (".join(',', $ids).") $filter", 
  																				array('clientptr', 'servicetypeptr'));
}

function getStandardCharges() {
	return fetchAssociationsKeyedBy("SELECT * FROM tblservicetype ORDER BY label", 'servicetypeid');
}

function deactivateClient($clientid) {
	$mods = withModificationFields(array('current'=> 0));
	updateTable('tblrecurringpackage', $mods, "clientptr = $clientid", 1);
	updateTable('tblservicepackage', $mods, "clientptr = $clientid", 1);
	updateTable('tblservice', $mods, "clientptr = $clientid", 1);
	updateTable('tblclient', array('deactivationdate'=>date('Y-m-d')), "clientid = $clientid", 1);
	logChange($clientid, 'tblclient', 'm', 'Deactivated');
	require_once "appointment-fns-2.php";
	deleteAppointments(tzAdjustedSql("completed IS NULL AND clientptr = $clientid
	         AND (date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME()))"));
	if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		dropAutoSurchargesWhere(tzAdjustedSql("completed IS NULL AND clientptr = $clientid
	         AND (date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME()))"));
	}
}

function setClientCharges($id) {
	doQuery("DELETE FROM relclientcharge WHERE clientptr=$id");
}
	foreach($_POST as $key => $val) {
		$val = trim($val);
		$servType = strpos($key, 'servicecharge_') === 0 ? substr($key, strlen('servicecharge_')) : null;
		if($servType && (strlen($val) > 0 || strlen(trim($_POST['servicetax_'.$servType])) > 0) ) {
			$servTax = trim($_POST['servicetax_'.$servType]);
//echo "$key: [val: $val] [servicetax_$servType = {$_POST['servicetax_'.$servType]}]<p>";			
			$servTax = strlen($servTax) ? $servTax  : '-1';
			if($servTax == 0) $servTax = '0.0';
			$charge = strlen($val) ? $val  : '-1';
			if($charge == 0) $charge = '0.0';
			insertTable('relclientcharge', 
			          array('clientptr'=>$id, 
			                'servicetypeptr'=>$servType,
			                'charge'=>$charge,
			                'taxrate'=>$servTax), 1);
		}
	}
//exit;	
}

function getClientLoginCreds($client) {
	global $dbhost, $db, $dbuser, $dbpass;
	$userid = is_array($client) ? $client['userid'] : fetchRow0Col0("SELECT userid FROM tblclient WHERE clientid = $client LIMIT 1");
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass, 1);
	include "common/init_db_common.php";
	$creds = fetchFirstAssoc("SELECT loginid, temppassword FROM tbluser WHERE userid = $userid LIMIT 1");	
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	return $creds;
}
	

function getOneClientsDetails($id, $additionalFields=null) {
	if(!$id) return array();
	$all = getClientDetails(array($id), $additionalFields);
	return $all ? $all[$id] : null;
}

function getClientDetails($ids, $additionalFields=null, $sorted=false) {
	$additionalFields = $additionalFields ? $additionalFields : array();
	if(!$ids) return array();
	$joinPhrase = '';
	$phrases = ", CONCAT_WS(' ', tblclient.fname, tblclient.lname) as clientname";
	foreach($additionalFields as $field) {
		if($field == 'address')
			$phrases .= ", CONCAT_WS(', ', street1, street2, city) as address";
		else if($field == 'fullname')
			$phrases .= ", lname, fname";
		else if($field == 'sortname')
			$phrases .= ", CONCAT_WS(', ', lname, fname) as sortname";
		else if($field == 'addressparts')
			$phrases .= ", street1, street2, city, state, zip";
		else if($field == 'googleaddress')
			//$phrases .= ", CONCAT_WS(', ', street1, street2, city, state, zip) as googleaddress";
			$phrases .= ", CONCAT_WS(', ', street1, street2, city, IFNULL(state, zip)) as googleaddress";
		else if($field == 'phone')
			$phrases .= ", homephone, cellphone, workphone, cellphone2";
		else if($field == 'dagger') 
			$distinguishDeceasedPets = true; // used later, to distinguish deceased pets
		else if($field == 'pets') {
			$phrases .= ", p.name, p.active as activepet";
			$joinPhrase = "LEFT JOIN tblpet p ON ownerptr = clientid";
		}
		else if($field == 'activepets') {
			$phrases .= ", p.name";
			$joinPhrase = "LEFT JOIN tblpet p ON p.ownerptr = clientid AND p.active = 1";
		}
		else $phrases .= ", $field";
  }
  if($sorted) {
		$phrases .= ", lname, fname";
		$sorted = 'ORDER BY lname, fname';
	}
	else $sorted = '';
	if(!array_intersect(array('pets', 'activepets'), $additionalFields))
	  $details = fetchAssociationsKeyedBy("SELECT clientid $phrases
														FROM tblclient WHERE clientid IN (".join(',', $ids).") $sorted", 'clientid');
  else 														
	  $details = fetchAssociations("SELECT clientid $phrases
														FROM tblclient $joinPhrase WHERE clientid IN (".join(',', $ids).") $sorted", 'clientid');
														
	foreach($details as $key => $detail) {
		if(in_array('phone', $additionalFields))
			$details[$key]['phone'] = primaryPhoneNumber($detail);
	}
	if(!array_intersect(array('pets', 'activepets'), $additionalFields)) return $details;
	else {
		$clients = array();
	  foreach($details as $key => $detail) {
			$petname = $distinguishDeceasedPets && !$detail['activepet'] ? "&dagger;{$detail['name']}" : $detail['name'];
			if(isset($clients[$detail['clientid']]))
			  $clients[$detail['clientid']]['pets'][] = $petname;
			else {
				$clients[$detail['clientid']] = $detail;
				if($detail['name']) $clients[$detail['clientid']]['pets'][] = $petname;
			}
		}
		return $clients;
	}
}									

function clientAcceptsEmail($clientOrClientId, $preferenceFields, $allowNullEmail=false) {
	// return true if client has an amail address and accepts emails 
	// of the kind named in preferenceFields [ array(key=>permissableValue,  key=>permissableValue,  ) ]
	$client = is_array($clientOrClientId) ? $clientOrClientId : fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = '$clientOrClientId' LIMIT 1");
	$clientid = $client['clientid'];

	if(!$client['email'] && !$allowNullEmail) return false;
	if($preferenceFields) {
		$field = current(array_keys($preferenceFields));
		$finding = getClientPreference($clientid, $field);
		if($preferenceFields[$field] != $finding)
			return false;
		//$recipientPrefs = getClientPreferences($clientid, array_keys($preferenceFields));
		//foreach($preferenceFields as $property => $goal)
		//	if($recipientPrefs[$property] != $goal)
		//		return false;
	}
	return true;
}

//SELECT * FROM `tblclient` WHERE `defaultproviderptr` IS NOT NULL AND `defaultproviderptr` IS NOT IN ()
function wipeClient($id) { // use with EXTREME caution
	wipeAllPets($id);

	$tables = explode(',', 'relapptdiscount,relclientcharge,relclientcustomfield,relclientdiscount,relinvoiceitem,tblappointment,tblbillable,'
	.'tblclientpref,tblclientprofilerequest,tblclientrequest,tblcontact,tblcredit,tblcreditcard,tblcreditcarderror,'
	.'tblgratuity,tblhistoricaldata,tblinvoice,tblkey,tblkeylog,tblothercharge,tblpayment,tblprovidermemo,tblrecurringpackage,'
	.'tblrefund,tblservice,tblservicepackage,tblsurcharge,tempClientMap');
	
	$allTables = fetchCol0("SHOW TABLES");
	
	foreach($tables as $table) if(in_array($table, $allTables)) deleteTable($table, "clientptr = $id", 1);
 	deleteTable('tblclient', "clientid=$id", 1);
	deleteTable("tblmessage", "(correspid=$id AND correstable='tblclient') OR (originatorid=$id AND originatortable='tblclient')", 1);
	deleteTable("tblconfirmation", "respondentptr = $id AND respondenttable = 'tblclient'", 1);
	deleteTable("tblproviderpref", "property='donotserve_$id'", 1);
}

function wipeAllPets($clientid) { // use with EXTREME caution
	$petids = fetchCol0("SELECT petid FROM tblpet WHERE ownerptr = $clientid");
	foreach($petids as $petid) wipePet($petid);
}

function wipePet($petid) { // use with EXTREME caution
 	deleteTable('tblpet', "petid=$petid", 1);
 	deleteTable('relpetcustomfield', "petptr=$petid", 1);
}

function getTrialDiscontinuationFormURL($bizid) {
	$prestifabulator = 10473;
	// https://leashtime.com/trial-discontinuation-form.php?generate=3
	if(!$bizid) return null;
	if(!$_SESSION['staffuser']) locked('z-');
	return "https://leashtime.com/trial-discontinuation-form.php?token="
		.sprintf('%x', ($prestifabulator *($prestifabulator + intval($bizid))));
}

function getBizIDFromTrialDiscontinuationToken($token) {
	$prestifabulator = 10473;
	$decimaltoken = intval($token, 16);
	$bizid = $decimaltoken / $prestifabulator - $prestifabulator;
	return $bizid;
}
