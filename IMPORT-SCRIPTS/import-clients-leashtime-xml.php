<?// import-clients-leashtime-xml.php

// import the clients exported from LeashTime to a multi-sheet XML
// data generated by reports-clients-export-xml.php

// https://LEASHTIME.COM/import-clients-leashtime-xml.php?file=

// Libre Office: export as Microsft Excel 2003 XML

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";
require_once "field-utils.php";
require_once "export-fns.php";
require_once "client-flag-fns.php";


locked('o-');

if(!staffOnlyTEST()) {
	echo "STAFF ONLY";
	exit;	
}
extract($_REQUEST);
set_time_limit(60);

$file = "/var/data/clientimports/$file";

//clientid	lname	fname	email	homephone	cellphone	workphone	fax	pager	
//lname2	fname2	alternate	email2	cellphone2	street1	street2	city	state	zip	
// mailstreet1	mailstreet2	mailcity	mailstate	mailzip	
// active	prospect	birthday	vetptr	vetname	vetphone	clinicid	clinicname	clinicphone	
// defaultproviderptr	sittername	tblclient.notes	officenotes	tblclient.directions	alarmcompany	
// alarminfo	alarmcophone	setupdate	activationdate	deactivationdate	
// emergencyname	emergencylocation	emergencyhomephone	emergencyworkphone	emergencycellphone	emergencynote	emergencyhaskey	
// neighborname	neighborlocation	neighborhomephone	neighborworkphone	neighborcellphone	neighbornote	neighborhaskey	
// leashloc	foodloc	parkinginfo	garagegatecode	emergencycarepermission	nokeyrequired	referralname	
// mailtohome	"Pet Feeding Instructions"	"Pet Supply Location (food, treats, litter, etc)"	"Cleaning Supply Location and Towels For Muddy Paws"	
// "Pet's Fear & Favorite Hiding Places"	"Medication List and Instructions"	"Overnight Pet Sitting - Where should pet sitter sleep?"	"Overnight Pet Sitting - Where is your pet to sleep?"	"What rooms are off limits to your pet?"	"Overnight Pet Sitting - What appliances/facilities may your pet sitter use? (i.e. TV, dishwasher, microwave, shower, etc)"	"Location Of Fuse Box"	"Location Of Main Water Shut-Off"


echo "<hr>";

$strm = fopen($file, 'r');

if(!$strm) exit;


$newClientIds = array(); // newId => oldId
$newPetIds = array(); // newId => oldId
$customClientFieldMap = array(); // oldName => newCustomFieldName(in tblpreference)
$flagMap = array(); // oldName => newClientFlagName(in tblpreference)
$customPetFieldMap = array(); // oldName => newPetCustomFieldName(in tblpreference)
$basicClientFields = getClientSQLFields();
foreach($basicClientFields as $i => $field) {
	if(strpos($field, ' as ')) $basicClientFields[$i] = substr($field, strrpos($field, ' ')+1);
}
$mostBasicClientFields = array_merge($basicClientFields);
foreach(fetchAssociations("DESC tblclient") as $i => $field) {
	$basicClientFields[] = $field['Field'];
}
foreach(explode(',', 'vetname,vetphone,clinicid,clinicname,clinicphone,sittername,tblclient.notes,tblclient.directions,'
											.'emergencyname,emergencylocation,emergencyhomephone,emergencyworkphone,emergencycellphone,'
											.'emergencynote,emergencyhaskey,neighborname,neighborlocation,neighborhomephone,neighborworkphone,'
											.'neighborcellphone,neighbornote,neighborhaskey,referralname') as $i => $field)
	$basicClientFields[] = $field;


$dbCustomClientFields = getCustomFields($activeOnly=false, $visitSheetOnly=false, $fieldNames=null, $clientVisibleOnly=false);
$clientCustomFieldKeysByName = array();
$booleanClientCustomFields = array();

foreach(fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'billing_flag_%'") as $k=>$v) {
	$keynum = explode('_', $k);
	$parts = explode('|', $v);
	$lookup = $parts[1] ? $parts[1] : $keynum[2];
	$billingFlagLookup[$lookup] = $k;
}

foreach(getBizFlagList() as $i => $flag) $dbBizFlagNames[trim($flag['title'])] = $i;

$dbCustomPetFields = getCustomFields($activeOnly=false, $visitSheetOnly=false, getPetCustomFieldNames(), $clientVisibleOnly=false);
$booleanPetCustomFields = array();
$basicPetFieldNames = explode(',', 'petid,ownerptr,clientname,type,active,name,breed,sex,color,fixed,dob,description,notes,birthday');
echo $file."<p>";
$tagPrefix = 'unset';

// Find tag prefix
while(!feof($strm)) {
	$s = fgets($strm);
	//echo htmlentities($s)."<br>";
	if(strpos($s, '<ss:Workbook') !== FALSE || strpos($s, '<Workbook') !== FALSE)
		break;
}
if(strpos($s, '<ss:Workbook') !== FALSE) $tagPrefix = 'ss:';
else if(strpos($s, '<Workbook') !== FALSE) $tagPrefix = '';

$workbookTag = strpos($s, '<ss:Workbook') ? 'ss:Workbook' : 'Workbook'; // $workbookTag, $rowTag, $cellTag
$worksheetTag = strpos($s, '<ss:Worksheet') ? 'ss:Worksheet' : 'Worksheet';
$rowTag = strpos($s, '<ss:Row') ? 'ss:Row' : 'Row';
$cellTag = strpos($s, '<ss:Cell') ? 'ss:Cell' : 'Cell';

$tagPrefix = ''; // KLUDGE

foreach(explode(',', 'tagPrefix,workbookTag,rowTag,cellTag') as $fld)
	echo "<br>$fld: [{$$fld}]";
// ####### REWIND #####################
rewind($strm);

//@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
//while($row = myfgetcsv($strm)) {$n += 1; if($last != $lastSectionStarted) echo "<b>$lastSectionStarted</b><br>";$last = $lastSectionStarted; echo "($n) ".join(', ', $row).'<hr>';}
//exit;
//@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

$dataHeaders = myfgetcsv($strm);
//https://leashtime.com/import-clients-leashtime-xml.php?file=jordanspetcare/ClientsAndPets.xls
echo "headers: ".print_r($dataHeaders, 1);echo "<hr>";
if($lastSectionStarted != 'Active Clients') {
	echo "<hr>Did not find Active Clients sheet.  Stopping.";
	$stop = true;
	exit;
}

function ignoreCol($colhdr) {
	$ignorecols = explode(',', (string)$_REQUEST['ignorecols']);
	return in_array($colhdr, $ignorecols);
}

// First confirm that all client custom fields and flags represented in the import file exist in this DB
foreach($dataHeaders as $colhdr) {
	if($colhdr == 'hook') continue;
	if(ignoreCol($colhdr)) {echo "IGNORING column $colhdr<br>"; continue;}
	else if(strpos($colhdr, 'flag/') === 0) {
		if(!array_key_exists(substr($colhdr, strlen('flag/')), (array)$dbBizFlagNames)) {
			echo "<hr>Expected client flag [$colhdr] not found in $db.  Stopping.<br>";
			$stop = true;
		//print_r($dbBizFlagNames);
			exit;
		}
		else if(substr($colhdr, strlen('flag/')) == 0) $flagMap[$colhdr] = $dbBizFlagNames[substr($colhdr, strlen('flag/'))];
	}
	else if(strpos($colhdr, 'billingflag/') === 0) {
		$flagNum = substr($colhdr, strlen('billingflag/'));  // may be a number or a label
		if($found = $billingFlagLookup[$flagNum]) ;// OK;
		else if($flagNum < 1 || $flagNum > $maxBillingFlags) {
			echo "<hr>Expected client billing flag [$colhdr] not found in $db.  Stopping.<br>";
			$stop = true;
		//print_r($dbBizFlagNames);
			exit;
		}
	}
	else if(!in_array($colhdr, $basicClientFields)) {	
		foreach($dbCustomClientFields as $prefKey => $field) {
			if(strpos($colhdr, 'Light')===0) echo "[$colhdr]==[".htmlentities($field[0])."]<br>";
			if($colhdr === htmlentities($field[0])) break;
		}
		if($colhdr !== htmlentities($field[0])) {
			echo "<hr>Expected custom client field [$colhdr] not found in $db.  Stopping.<br>"
							.print_r($dbCustomClientFields,1)."<hr>".print_r($dataHeaders,1);
			$stop = true;
			exit;
		}
		else {
			$clientCustomFieldKeysByName[$colhdr] = $prefKey;
			if($field[2] == 'boolean') $booleanClientCustomFields[] = $colhdr;
		}
	}
}
//echo "<hr>".print_r($clientCustomFieldKeysByName, 1)."<hr>";
$clientDataHeaders = $dataHeaders;
//echo "<hr>clientDataHeaders: ".print_r($clientDataHeaders, 1)."<hr>";
//print_r($booleanClientCustomFields);//clientCustomFieldKeysByName dbCustomClientFields booleanClientCustomFields
// Next confirm that all pet custom fields represented in the import file exist in this DB
while($lastSectionStarted != 'Pets') $dataHeaders = myfgetcsv($strm);
echo "LAST SECTION: $lastSectionStarted<p>".print_r($dataHeaders,1);

// KLUDGE -- fix dataheaders
$firstHeader = $dataHeaders[0];
if(strpos($firstHeader, "False") === 0 && strrpos($firstHeader, "petid") == strlen($firstHeader) - 5) $dataHeaders[0] = 'petid';

$petDataHeaders = $dataHeaders;
foreach($dataHeaders as $colhdr) {
	if(!in_array($colhdr, $basicPetFieldNames)) {
		foreach($dbCustomPetFields as $fieldKey => $field)
			if($colhdr === htmlentities($field[0])) break;
		if($colhdr !== htmlentities($field[0])) {
			echo "<hr>Expected custom pet field [$colhdr] not found in $db.  Stopping.<br>";
			$stop = true;
			//exit;
		}
		else {
			$petCustomFieldKeysByName[$colhdr] = $fieldKey;
			if($field[2] == 'boolean') $booleanPetCustomFields[] = $colhdr;
		}
	}
}
if($stop) echo "STOPPING: [$stop]<p>";
if($stop) exit;
//echo "dataHeaders: ".count($dataHeaders).':<br>'.join(',', $dataHeaders).'<hr>';

//print_r($dataHeaders);echo "<hr>";exit;

echo "<a href='#PETSECTION'>JUMP TO PETS</a><p>";


$n = 1;

//while($line = trim(fgets($strm))) echo "$line<br>";

$thisdb = $db;

$_SESSION['preferences'] = fetchPreferences();
$customFields = getCustomFields('activeOnly');

//print_r($_SESSION['preferences']);exit;
$vetptrs = fetchCol0("SELECT vetid FROM tblvet");
$clinicptrs = fetchCol0("SELECT clinicid FROM tblclinic");

// ####### REWIND #####################
$rewindSuccess = rewind($strm);
echo "REWOUND: [$rewindSuccess]<p>";
$leftover = '';
while(($row = myfgetcsv($strm)) || !feof($strm)) {
	if($currentSection != $lastSectionStarted) {
		echo "<hr>STARTED $lastSectionStarted<hr>";
		$currentSection = $lastSectionStarted;
		$dataHeaders = $row;
		echo "rewindSuccess [$rewindSuccess]<br>HEADERS: (".count($dataHeaders).")<br>".print_r($dataHeaders,1).'<p>';
		continue;
	}
	//else echo print_r(array_combine($dataHeaders, $row),1).'<p>';
	//else echo print_r($row,1).'<br>';
	
	$n++;
	if($row && $dataHeaders[0] == 'clientid' && ($lastSectionStarted == 'Active Clients' || !$_REQUEST['noinactive']))
		$client = handleClientRow($row);
	else if($row && $dataHeaders[0] == 'petid') {
//if(!$done) {echo "oldNewClients:<br>".print_r($oldNewClients, 1);$done=true;}
if(!$done) {echo "<a name='PETSECTION'<hr></a><p>";$done=true;}
		$pet = handlePetRow($row);
		//echo "Added {$client['fname']} {$client['lname']}<br>";
	// HANDLE EMPTY LINES
	//if(skipRow($row)) {echo "<p><font color=red>Skipped Line #$n</font><br>";continue;}
	// HANDLE CONTINUATIONS OF INCOMPLETE LINES
	//else handleRow($row);
	}	
}
if($_REQUEST['transferusers']) transferUserNames($userNames);
else showTransferInstructions($userNames);

echo "<hr><hr>";
print_r($oldNewClients);


function showTransferInstructions($userNames) {
	global $db;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	if(!$userNames) {
		echo "No user names to transfer.";
		return;
	}
	foreach($userNames as $loginid => $clientid) $labels[$loginid] = "$loginid client: @$clientid";
	if($userNames) echo "<hr>Logins were not transferred for any of these clients:<ul><li>".join('<li>', $labels)."</ul>";
	
	require "common/init_db_common.php";
	$foundUsers = fetchAssociationsKeyedBy("SELECT * FROM tbluser WHERE loginid IN ('".join("','", array_keys($userNames))."')", 'loginid');
	if($foundUsers) $bizdbs = fetchCol0("SELECT DISTINCT db FROM tblpetbiz WHERE bizid IN (SELECT bizptr FROM tbluser WHERE loginid IN ('".join("','", array_keys($userNames))."'))", 1);
	if(count($bizdbs) > 1)
		echo "<font color=red>WARNING: THESE USERS BELONG TO DIFFERENT DATABASES: ".join(', ', $bizdbs)."<p>";

	echo "<hr>In petcentral:<br>";
	foreach($userNames as $loginid => $clientid) {
		$user = $foundUsers[$loginid];
		if(!$user) echo "user [$loginid] not found.<br>";
		echo "UPDATE tbluser SET bizptr = {$_SESSION["bizptr"]} WHERE loginid = '$loginid' LIMIT 1;<br>";
	}
	echo "<hr>In $db1:<br>";
	foreach($userNames as $loginid => $clientid) {
		$user = $foundUsers[$loginid];
		if(!$user) echo "user [$loginid] not found.<br>";
		echo "UPDATE tblclient SET userid = {$user['userid']} WHERE clientid = '$clientid' LIMIT 1;<br>";
	}
}


//====================================
function handlePetRow($row) {
	// import pets
	global $thisdb, $dataHeaders, $oldNewClients, $petCustomFieldKeysByName, $booleanPetCustomFields;
	static $standardFields, $ignoreFields;
	$standardFields = $standardFields ? $standardFields : explode(',', 'active,type,name,breed,sex,color,fixed,dob,description,notes');
	$ignoreFields = $ignoreFields ? $ignoreFields : explode(',', 'petid,clientname,birthday');
	$pet = array();
	foreach($dataHeaders as $i => $label) {
//echo "$i: [$label] [{$row[$i]}]<p>";
		$trimval = html_entity_decode(trim("".$row[$i]));
		if(!$trimval) continue;
		if($label == 'clientname') $clientname = $trimval;
		else if($label == 'ownerptr') {
			$oldOwnerptr = $trimval;
			$pet['ownerptr'] = $oldNewClients[$trimval];
		}
		else if(in_array($label, $ignoreFields)) ; // ignore
		else if(in_array($label, $standardFields)) $pet[$label] = $trimval;
		else {
			if(in_array($label, $booleanPetCustomFields)) {
//echo "<br>[$label]=>[{$petCustomFieldKeysByName[$label]}]=>[$trimval]";
				$trimval = booleanFromYN($trimval);
//echo "$trimval";
			}
			if(!ignoreCol($field))
				$custom[$petCustomFieldKeysByName[$label]] = $trimval;
		}
	}
//if($pet['name'] == 'Cali') {echo "CALI CUSTOM: ".print_r($custom, 1)."<hr>".print_r($petCustomFieldKeysByName, 1)."<hr>";exit;}
	$pet['active'] = booleanFromYN($pet['active']);//exit;
	
	if(!$pet['ownerptr'])
		echo "<p style='color:#2F4F4F'>ORPHAN PET: [$petid] (unknown owner: [$oldOwnerptr] $clientname) {$pet['name']} ({$pet['type']}) {$pet['breed']} {$pet['sex']} ";
	
	else {	
		$petid = insertTable('tblpet', $pet, 1);
		if($custom) savePetCustomFields($petid, $custom, null, $pairsOnly=true);
		echo "<p>CREATED $clientname's PET: [$petid] {$pet['name']} ({$pet['type']}) {$pet['breed']} {$pet['sex']} ";
	}
	return $pet;

}

function handleClientRow($row) {
	// import client, create contacts, vets, and clinics as necessary, and map OLDclientId => clientId for later pest associations
	global $thisdb, $dataHeaders, $lastClient, $vetptrs, $clinicptrs, $oldNewClients, $flagMap, $userNames,
					$clientCustomFieldKeysByName, $clientFields, $portUserIds, $booleanClientCustomFields, $billingFlagLookup; // from client-fns.php
	$client = array();
//echo "<p>";
//print_r($row);exit;
	foreach($dataHeaders as $i => $label) {
//echo "$i: [$label] [{$row[$i]}]...";
		$trimval = html_entity_decode(trim("".$row[$i]));
		if(!$trimval) continue;
		
		if($label == 'clientid') $oldClientId = $trimval;
		else if($label == 'clinicid') ; // ignore
		else if($label == 'userid') $username = $trimval;
		else if($label == 'referralname') $referralname = $trimval;
		else if(strpos($label, 'flag/') === 0) 
			$flags['flag_'.(count($flags)+1)] = $flagMap[$label].'|'.($trimval == substr($label, strlen('flag/')) ? '' : $trimval);
		else if(strpos($label, 'billingflag/') === 0) {
			$bfn = substr($label, strlen('billingflag/'));
			$found = $billingFlagLookup[$bfn];
			$billingflags[$found] = ($trimval = 'yes' ? '|' : $trimval);
		}
		else if(in_array($label, $clientFields)) $client[$label] = $trimval;
		else if(strpos($label, '.')) $client[substr($label, strpos($label, '.')+1)] = $trimval;
		else if(strpos($label, 'emergency') === 0) 
			$emergencycontact[substr($label, strlen('emergency'))] = $trimval;
		else if(strpos($label, 'neighbor') === 0) 
			$neighborcontact[substr($label, strlen('neighbor'))] = $trimval;
		else if($label == 'vetname') $vetname = $trimval;
		else if($label == 'vetphone') $vetphone = $trimval;
		else if($label == 'clinicname') $clinicname = $trimval;
		else if($label == 'clinicphone') $clinicphone = $trimval;
		else if($label == 'keyid') {
			if($SESSION['preferences']['mobileKeyDescriptionForKeyId']) {
				$key = array('description'=>$trimval);
			}
			/* else skip.  key ids will collide between databases */
		}
		else if($label == 'hook') $key['bin'] = $trimval;
		else if($label == 'sittername') $client['defaultproviderptr'] = findSitterByName($client['sittername']);// ignore
		else if(in_array($label, array('alternate', 'sittername'))) ;// ignore
		else {
echo "Custom [$label]: $trimval<br>";;	
			if(in_array($label, $booleanClientCustomFields)) {
//echo "<br>[$label]=>[{$clientCustomFieldKeysByName[$label]}]=>[$trimval]";
				$trimval = booleanFromYN($trimval);
//echo "$trimval";
			}
			if(!ignoreCol($label)) $custom[$clientCustomFieldKeysByName[$label]] = $trimval;
		}
		//else $client[$label] = $trimval;
//print_r($client);echo "<p>";
	}
//echo "Custom: ".print_r($custom, 1);exit;	
	
	if($vetname) {
		if(!$vetptr = findVetByName($vetname)) {
			$vet = array();
			handleFnameSpaceLname($vetname, $vet);
			if($vetphone) $vet['officephone'] = $vetphone;
			$vetptr = insertTable('tblvet', $vet, 1);
		}
		$client['vetptr'] = $vetptr;
	}
	
	if($clinicname) {
		if(!$clinicptr = findClinicByName($clinicname)) {
			$clinic = array('clinicname'=>$clinicname);
			handleFnameSpaceLname($clinicname, $clinic);
			if($clinicphone) $clinic['officephone'] = $clinicphone;
			$clinicptr = insertTable('tblclinic', $clinic, 1);
		}
		$client['clinicptr'] = $clinicptr;
	}
	if($referralname) $client['officenotes'] .= ($client['officenotes'] ? "\n" : '')."Referral: $referralname";
	
	unset($client['alternate']);//exit;	
	$client['active'] = booleanFromYN($client['active']);//exit;	
	$client['prospect'] = booleanFromYN($client['prospect']);//exit;	
	$client['emergencycarepermission'] = booleanFromYN($client['emergencycarepermission']);//exit;	
	$client['nokeyrequired'] = booleanFromYN($client['nokeyrequired']);//exit;
	$newClientId = saveNewClient($client);
	$oldNewClients[$oldClientId] = $newClientId;
	
	echo "<p>CREATED CLIENT: [$newClientId] {$client['fname']} {$client['lname']}";
	$lastClient = $newClientId;
	
	//echo "CUSTOM: ".print_r($custom,1).'<br>';
	foreach((array)$custom as $field => $val)
		if(!ignoreCol($field))
			replaceTable("relclientcustomfield", 
				array('clientptr'=>$newClientId, 'fieldname'=>$field, 'value'=>$val), 1);
	foreach((array)$flags as $prop => $val)
			setClientPreference($newClientId, $prop, $val);
	foreach((array)$billingflags as $prop => $val)
			setClientPreference($newClientId, $prop, $val);
	if($emergencycontact) saveClientContact('emergency', $newClientId, $emergencycontact);
	if($neighborcontact) saveClientContact('neighbor', $newClientId, $neighborcontact);
	if($key) {
		$key['clientptr'] = $newClientId;
		require_once "key-fns.php";
		$keyId = insertTable('tblkey', $key, 1);
		logKeyChange(mysql_insert_id(), $key, $newClientId, true);
	}
	// if($username) then we need 
	//  to find that user in petcentral
	//  ensure that user is a client
	//  get the userid for that user
	//  get the old bizId for this user
	//  set the bizId to this business's business Id
	//  connect to the old business
	//  find the old client with that userid and clear that client's user id
	// 	connect back to this db
	//  update the userid on the new client
	if($username) {
		$userNames[$username] = $newClientId;
	}
	
	return $client;	
}

function transferUserNames($userNames) {
	if(!($userNames)) {
		echo "No user names to transfer.";
		return;
	}
	global $dbhost, $db, $dbuser, $dbpass;
	// transfer usernames to new clients
	// we need 
	//  to find each user in petcentral, collect error otherwise
	//  ensure that user is a client, collect error otherwise
	//  get the userid for that user
	//  get the old bizId for this user.  If more than one old Biz ID is found, return errors.
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$foundUsers = fetchAssociationsKeyedBy("SELECT * FROM tbluser WHERE loginid IN ('".join("','", array_keys($userNames))."')", 'loginid');
	foreach($userNames as $loginid => $clientid) {
		$user = $foundUsers[$loginid];
		if(!$user) $errors[] = "No user found with username: [$loginid]";
		else if(strpos($user['rights'], 'c-') !== 0) $errors[] = "User [$loginid] is not a client (biz: {$user['bizptr']}).";
		else $clientUserIds[$clientid] = $user['userid'];
		$userBizzes[$user['bizptr']][] = $user['userid'];
	}
	if(count($userBizzes) > 1){
		$errors[] = "The login ID's for these clients come from ".count($userBizzes)." different databases.  No logins transferred.";
		echo "<hr><p style='color:red;'>".join('<br>', $errors)."</p>";
		return;
	}
	if($errors) echo "<hr><p style='color:red;'>".join('<br>', $errors)."</p>";
	$errors = array();
	
	// TESTING STOPS HERE
	//return;
	//echo "<hr>ClientUserIDs: ".print_r($clientUserIds, 1)."<hr>";
	echo "<hr>ClientUserIDs: ".print_r(array('bizptr'=>$_SESSION["bizptr"]), 1)."<br>"."userid IN (".join(',', $clientUserIds).")<hr>";
	//  set the bizId to this business's business Id
	$result = updateTable('tbluser', array('bizptr'=>$_SESSION["bizptr"]), "userid IN (".join(',', $clientUserIds).")", 1);
	$messages[] = "Moved ".(mysql_affected_rows() ? mysql_affected_rows() : "NO")." users to {$_SESSION["bizname"]}.";
	//  connect to the old business
	$oldBizIds = array_keys($userBizzes);
	$oldBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$oldBizIds[0]} LIMIT 1", 1);
	reconnectPetBizDB($oldBiz['db'], $oldBiz['dbhost'], $oldBiz['dbuser'], $oldBiz['dbpass'], $force=1);
	//  find the old client with that userid and clear that client's user id
	$result = updateTable('tblclient', array('userid'=>null), "userid IN (".join(',', $clientUserIds).")", 1);
	$messages[] = "Cleared userids for ".(mysql_affected_rows() ? mysql_affected_rows() : "NO")." users in old db ({$oldBiz["bizname"]}).";
	// 	connect back to this db
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, $force=1);
	//  update the userid on the new client
	foreach($clientUserIds as $clientid => $userid) {
		if(updateTable('tblclient', array('userid'=>$userid), "clientid = $clientid", 1)) {
			$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid LIMIT 1");
			$messages[] = "Associated userid [$userid] with {$client['fname']} {$client['lname']} (@$clientid) in {$_SESSION["bizname"]}.";
		}
	}
	echo "<hr><p style='color:green;'>".join('<br>', $messages)."</p>";
	
}

function findVetByName($nm) {
	return fetchRow0Col0("SELECT vetid FROM tblvet WHERE CONCAT_WS(' ', fname, lname)  = '".mysql_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findSitterByName($nm) {
	return fetchRow0Col0("SELECT providerid FROM tblprovider WHERE CONCAT_WS(' ', fname, lname)  = '".mysql_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findClinicByName($nm) {
	return fetchRow0Col0("SELECT clinicid FROM tblclinic WHERE clinicname = '".mysql_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(' ', $str));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
}

function myfgetcsv($strm) {
	global $lastSectionStarted, $tagPrefix;

	global $leftover;
	
	global $workbookTag, $worksheetTag, $rowTag, $cellTag;
	
	//for($s = "$leftover"; !feof($strm) && ($start = strpos($s, "<{$tagPrefix}Row")) === FALSE; ) {
	for($s = "$leftover"; !feof($strm) && ($start = strpos($s, "<$rowTag")) === FALSE; ) {
		$s .= fgets($strm);
	}
	
	
	
	if(!$s) return null;
//echo "<p>[[[".htmlentities($s).']]] ('.(strpos($s, '</ss:Row>') === FALSE).')<p>';		
	//while(!feof($strm) && (strpos($s, "</{$tagPrefix}Row>") === FALSE))
	while(!feof($strm) && (strpos($s, "</$rowTag>") === FALSE))
		$s .= fgets($strm);
//echo "<p>Line length: ".strlen($s)."<br>";		
	//if(($end = strpos($s, "</{$tagPrefix}Row>")) === FALSE) echo "INCOMPLETE ROW:<br>$s<br>";
	if(($end = strpos($s, "</$rowTag>")) === FALSE) echo "INCOMPLETE ROW [section: $lastSectionStarted]<br>Check to see if the Row element is self-terminating:<br>$s<br>";
	else {
		//$endSheet = strpos($s, "</{$tagPrefix}Worksheet>");
		//$leftover = substr($s,  $end+strlen("</{$tagPrefix}Row>"));
		//if(($ws = strpos($s, "<{$tagPrefix}Worksheet ss:Name=\"")) !== FALSE 
		$endSheet = strpos($s, "</$worksheetTag>");
		$leftover = substr($s,  $end+strlen("</$rowTag>"));
		if(($ws = strpos($s, "<$worksheetTag ss:Name=\"")) !== FALSE 
				&& $ws < $end) {
			//$ws = $ws+strlen("<{$tagPrefix}Worksheet ss:Name=\"");
			$ws = $ws+strlen("<$worksheetTag ss:Name=\"");
			$wsend = strpos($s, '"', $ws);
			$lastSectionStarted = substr($s, $ws, $wsend - $ws);
			echo "<hr><hr>FOUND SHEET $lastSectionStarted<hr><hr>";
		}
//echo htmlentities($leftover).'</br>';		
//echo htmlentities($s).'<hr>';		
		$start = strpos($s, '>', $start)+1;
//echo '<font color=blue>'.print_r(array_map('strip_tags', explode("</$cellTag>", $leftover)),1).'</font><br>';
		//$row = array_map('trim', array_map('strip_tags', explode("</{$tagPrefix}Cell>", substr($s, $start,$end-$start))));
		$cells = explode("</$cellTag>", substr($s, $start,$end-$start));
//echo "<font color=blue>[$start]=>[$end] [".htmlentities(substr($s, $start,$end-$start))."</font><br>";
		$row = array();
		$totalSkipped = 0;
//echo "CELLS: ".print_r($cells, 1)."<p>";
//echo count($cells)." cells<br>";
		foreach($cells as $i => $cell) { // $i is zero-based
			// example ...<Cell ss:Index="6"><Data ss:Type="String">SChamplin</Data>
			// ... or:
			// <!--[CDATA[Bibbee]]-->
			$tag = substr($cell, strpos($cell, '<'), strpos($cell, '>')-strpos($cell, '<'));
			$skipped = 0;
			if($indStart = strpos($tag, 'Index="')) { // a cell or cells were skipped
				$indStart = $indStart+strlen('Index="');
				//echo "<br>CELL $cell ==> <br>[$indStart]TAG $tag<br>";
				$index = substr($tag, $indStart, strpos($tag, '"', $indStart)-$indStart);
				$skipped = $index - ($i + 1 + $totalSkipped);
				//echo "<br>index[$index] i [$i] SKIPPED: $skipped [".trim(strip_tags($cell))."] ";
				$totalSkipped += $skipped;
			}
			for($sk = 0; $sk < $skipped; $sk++) {
				$row[] = null;
			}
			
			if(($dataStart = strpos($cell, '<![CDATA[')) !== FALSE) {
				$dataStart += strlen('<![CDATA[');
				$dataEnd = strpos($cell, ']]>');
				$datum = substr($cell, $dataStart, $dataEnd-$dataStart);
			}
			else $datum = trim(strip_tags($cell));
//echo "cell [$dataStart][$dataEnd]: $datum<p>";
//echo "full cell: ".htmlentities($cell)."<p>";
			$row[] = $datum;
		}
		//$row = array_map('trim', array_map('strip_tags', explode("</$cellTag>", substr($s, $start,$end-$start))));
		$fullSlot = false;
//echo '<font color=darkgreen>'.print_r($row,1).'</font><br>';
		if($row) while(!$fullSlot && $row) if($fullSlot = array_pop($row)) $row[] = $fullSlot;
//echo print_r($row,1).'<hr>';		
//global $oink;
//$oink += 1;
//echo "<br><span style='color:red'>OINK [$oink] length: ".count($row).'</span>';		
		return $row;
	}
}


// ======================================
function OLDmyfgetcsv($strm) {
	global $petSectionHasBegun, $lastSectionStarted, $tagPrefix;

	global $leftover;
	
	for($s = "$leftover"; !feof($strm) && ($start = strpos($s, "<{$tagPrefix}Row")) === FALSE; ) {
		$s .= fgets($strm);
	}
	if(!$s) return null;
//echo "<p>[[[".htmlentities($s).']]] ('.(strpos($s, '</ss:Row>') === FALSE).')<p>';		
	while(!feof($strm) && (strpos($s, "</{$tagPrefix}Row>") === FALSE))
		$s .= fgets($strm);
	if(strpos($s, "</{$tagPrefix}Row>") === FALSE) echo "INCOMPLETE ROW:<br>$s<br>";
	else {
		if(($ws = strpos($s, "<{$tagPrefix}Worksheet ss:Name=\"")) !== FALSE) {
			$ws = $ws+strlen("<{$tagPrefix}Worksheet ss:Name=\"");
			$end = strpos($s, '"', $ws);
			$lastSectionStarted = substr($s, $ws, $end - $ws);
		}
		$end = strpos($s, "</{$tagPrefix}Row>");
		$endSheet = strpos($s, "</{$tagPrefix}Worksheet>");
		if($endSheet !== FALSE  && $endSheet < $end) 
			// if sheet ends before the row ends, then row is a pet
			$petSectionHasBegun = true;
		$leftover = substr($s,  $end+strlen("</{$tagPrefix}Row>"));
//echo htmlentities($leftover).'</br>';		
//echo htmlentities($s).'<hr>';		
		$start = strpos($s, '>', $start)+1;
		$row = array_map('trim', array_map('strip_tags', explode("</{$tagPrefix}Cell>", substr($s, $start,$end-$start))));
		$fullSlot = false;
		if($row) while(!$fullSlot) if($fullSlot = array_pop($row)) $row[] = $fullSlot;
//echo print_r($row,1).'<hr>';		
		return $row;
	}
}

function booleanFromYN($val) { return $val == 'yes' ? '1' : '0'; }

function skipRow($row) {
	global $thisdb, $dataHeaders;
}
	



