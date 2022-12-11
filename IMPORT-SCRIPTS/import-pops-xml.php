<?// import-pops-xml.php

// import the staff, pets, clients, and vets exported from PoPs to a multi-sheet XML
// see: x:\clientimports\luckypawspetservices\Lucky-Paws_6387.xls

// https://LEASHTIME.COM/import-pops-xml.php?file=luckypawspetservices/Lucky-Paws_6387.xls

/*
SHEETS: Clients, Staff, Services, Pets, Veterinarians, Appointments, Visits, VisitServices

CLIENTS
BusinessID	ClientID	EmailAddress	FirstName	LastName	AlternateLastName	DayTimePhone	EveningPhone	EmergencyContact	EmergencyPhone	EmergencyKey	EmergencyContact2	EmergencyPhone2	EmergencyKey2	EmergencyContact3	EmergencyPhone3	EmergencyKey3	OtherContact	OtherPhone	OtherKey	AddressStreet	AddressSuite	AddressCity	AddressState	AddressZIPCode	SpecialDirections	BillingName	BillingAddressStreet	BillingAddressSuite	BillingAddressCity	BillingAddressState	BillingAddressZIPCode	PaymentMethod	CreditBalance	DiscountPercentage	PrimaryStaffID	SecondaryStaffID	Notes	NoteAddedBy	DateNoteAdded	Inactive	Username	SpouseOther	CellPhone	ReferredBy	KeyLocation	DisarmAlarm	ArmAlarm	AlarmPassword	AlarmCompany	AlarmCompanyPhone	AlarmLocation	GateCode	TrashInside	TrashOutside	BreakerBoxLocation	WaterShutoffLocation	Thermostat	WasteDisposal	CleaningSupplies	Flashlight	InstrTrash	InstrMailNews	InstrParking	InstrLightsBlinds	InstrPlants	EmergencyWorkPermission	EmergencyVetPermission	Alt1StaffID	Alt2StaffID	Alt3StaffID	ReferralChoice	ReferralSubChoice	ReferralChoiceInfo	PartnerDiscountCodeID	PartnerDiscountType	PartnerDiscountMemberID	AdminNote	AdminNoteAddedBy	DateAdminNoteAdded	DateCreated	ReferralThirdChoice	ClassificationID

STAFF
BusinessID	StaffID	DisplayName	EmailAddress	FirstName	LastName	DayTimePhone	EveningPhone	EmergencyContact	EmergencyPhone	AddressStreet	AddressSuite	AddressCity	AddressState	AddressZIPCode	DateOfHire	Inactive

PETS
BusinessID	PetID	ClientID	PetType	Name	DateOfBirth	Neutered	Female	Aggressive	AggressionDetails	Medications	FoodLocation	LitterBoxLocation	LeashLocation	CarrierLocation	VaccinationsCurrent	VetHasCC	Note	VeterinarianID	VeterinarianID2	Breed	Color	inactivePet

VETS
BusinessID	VetID	Name	AddressStreet	AddressSuite	AddressCity	AddressState	AddressZIPCode	Phone	AlternatePhone

*/


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
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
set_time_limit(13);

$file = "/var/data/clientimports/$file";

echo "<hr>";

$strm = fopen($file, 'r');


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

// ####### REWIND #####################
rewind($strm);

//@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
//while($row = myfgetcsv($strm)) {$n += 1; if($last != $lastSectionStarted) echo "<b>$lastSectionStarted</b><br>";$last = $lastSectionStarted; echo "($n) ".join(', ', $row).'<hr>';}
//exit;
//@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

$dataHeaders = myfgetcsv($strm);
//https://leashtime.com/import-clients-leashtime-xml.php?file=jordanspetcare/ClientsAndPets.xls
print_r($dataHeaders);echo "<hr>";
if($lastSectionStarted != 'Clients') {
	echo "Did not find Clients sheet.  Stopping.";
	$stop = true;
	exit;
}
if($stop) exit;
//echo "dataHeaders: ".count($dataHeaders).':<br>'.join(',', $dataHeaders).'<hr>';

//print_r($dataHeaders);echo "<hr>";exit;

echo "<a href='#PETSECTION'>JUMP TO PETS</a><p>";
echo "<a href='#VETSECTION'>JUMP TO CLINICS</a><p>";


$n = 1;

//while($line = trim(fgets($strm))) echo "$line<br>";

$_SESSION['preferences'] = fetchPreferences();
$customFields = getCustomFields('activeOnly');

//print_r($_SESSION['preferences']);exit;
// ####### REWIND #####################
$rewindSuccess = rewind($strm);
$leftover = '';
while($row = myfgetcsv($strm)) {
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
	if($row && $lastSectionStarted == 'Clients')
		$client = handleClientRow($row);
	else if($row && $lastSectionStarted == 'Staff')
		$sitter = handleStaffRow($row);
	else if($row && $lastSectionStarted == 'Pets') {
		if(!$donePet) {echo "<a name='PETSECTION'<hr></a><p>";$donePet=true;}
		$pet = handlePetRow($row);
	}	
	else if($row && $lastSectionStarted == 'Veterinarians') {
		if(!$doneVet) {echo "<a name='VETSECTION'<hr></a><p>";$doneVet=true;}
		$vet = handleVetRow($row);
	}	
}

//TBD given the following three arrays, after staff creation assign primary sitter and note secondary and alt staff for each client
//$defaultProviderPOPSIds, $secondaryProviderPOPSIds, $alternateStaffByClient, $clinicsByPOPSid, $oldNewClients;

foreach($petVets as $petid => $popsVetIds) {
	$clinicNames = array();
	$petNotes = array();
	foreach($popsVetIds as $popsVetId)
		$clinicNames[] = fetchRow0Col0("SELECT clinicname FROM tblclinic WHERE clinicid = {$clinicsByPOPSid[$popsVetId]}", 1);
	$petNotes = fetchRow0Col0("SELECT notes FROM tblpet WHERE petid = $petid", 1);
	$petNotes = ($petNotes ? "$petNotes\n" : '')."Veterinary clinics: ".join(', ', $clinicNames);
	updateTable('tblpet', array('notes'=>$petNotes), "petid=$petid", 1);
}
foreach($clientVets as $clientid => $popsVetIds) {
	$popsVetIds = array_merge(array_unique($popsVetIds));
	// set clinicptr to first vet
//echo "<p>Client: [$clientid] (".print_r($popsVetIds,1).")";
	updateTable('tblclient', array('clinicptr'=>$clinicsByPOPSid[$popsVetIds[0]]), "clientid=$clientid", 1);
	// add to Notes other Vet names
	for($i=1; $i<count($popsVetIds); $i++) {
		$clinic = fetchFirstAssoc("SELECT clinicid, clinicname FROM tblclinic WHERE clinicid = {$clinicsByPOPSid[$popsVetIds[$i]]}", 1);
		$clinicNames[$clinic['clinicid']] = $clinic['clinicname'];
	}
		
	if(count($clinicNames) > 1) {
		$notes = fetchRow0Col0("SELECT notes FROM tblclient WHERE clientid = $clientid", 1);
		$notes = ($notes ? "$notes\n" : '')."Other veterinary clinics: ".join(', ', $clinicNames);
		updateTable('tblclient', array('notes'=>$notes), "clientid=$clientid", 1);
	}
}


echo "<hr><hr>";
print_r($oldNewClients);


//====================================
function handleStaffRow() {
	
	// TBD: write
	
	global $dataHeaders;
	//BusinessID	StaffID	DisplayName	EmailAddress	FirstName	LastName	DayTimePhone	EveningPhone	EmergencyContact	EmergencyPhone	AddressStreet	AddressSuite	AddressCity	AddressState	AddressZIPCode	DateOfHire	Inactive
	$prov = array();
	foreach($dataHeaders as $i => $label) {
//echo "$i: [$label] [{$row[$i]}]<p>";
		$trimval = html_entity_decode(trim("".$row[$i]));
		if(!$trimval) continue;
	}
}


function handleVetRow($row) {
	//BusinessID	VetID	Name	AddressStreet	AddressSuite	AddressCity	AddressState	AddressZIPCode	Phone	AlternatePhone
	global $dataHeaders, $clinicsByPOPSid;
	$vet = array();
	foreach($dataHeaders as $i => $label) {
//echo "$i: [$label] [{$row[$i]}]<p>";
		$trimval = html_entity_decode(trim("".$row[$i]));
		if(!$trimval) continue;
		if($label == 'VetID') $popsID = $trimval;
		else if($label == 'Name') $vet['clinicname'] = $trimval;
		else if($label == 'AddressStreet') $vet['street1'] = $trimval;
		else if($label == 'AddressSuite') $vet['street2'] = $trimval;
		else if($label == 'AddressCity') $vet['city'] = $trimval;
		else if($label == 'AddressState') $vet['state'] = $trimval;
		else if($label == 'AddressZIPCode') $vet['zip'] = $trimval;
		else if($label == 'Phone') $vet['officephone'] = $trimval;
		else if($label == 'AlternatePhone') $vet['cellphone'] = $trimval;
	}
	$clinicid = insertTable('tblclinic', $vet, 1);
	$clinicsByPOPSid[$popsID] = $clinicid;
	echo "<p>CREATED clinic {$vet['clinicname']}";
	return $vet;
}


function handlePetRow($row) {
	// BusinessID	PetID	ClientID	PetType	Name	DateOfBirth	Neutered	Female	Aggressive	AggressionDetails	
	// Medications	FoodLocation	LitterBoxLocation	LeashLocation	CarrierLocation	VaccinationsCurrent	VetHasCC	
	// Note	VeterinarianID	VeterinarianID2	Breed	Color	inactivePet

	// import pets
	global $dataHeaders, $oldNewClients, $petVets, $clientVets, $petColLabels;
	$pet = array();
	setCustomPetFieldLabels(); // sets $petColLabels
	foreach($dataHeaders as $i => $label) {
//echo "$i: [$label] [{$row[$i]}]<p>";
		$trimval = html_entity_decode(trim("".$row[$i]));
		if(!$trimval) continue;
		if($label == 'ClientID') $pet['ownerptr'] = $oldNewClients[$trimval];
		else if($label == 'BusinessID') ; //no-op
		else if($label == 'PetType') $pet['type'] = $trimval;
		else if($label == 'Name') $pet['name'] = $trimval;
		else if($label == 'DateOfBirth' && $trimval != '1/1/1900') $pet['dob'] = $trimval;
		else if($label == 'Neutered') $pet['fixed'] = booleanFromTrueFalse($trimval);
		else if($label == 'Female') $pet['sex'] = booleanFromTrueFalse($trimval) ? 'f' : 'm';
		else if($label == 'Note') $notes[] = $trimval;
		else if(in_array($label, array('VeterinarianID','VeterinarianID2'))) {
			$vets[] = $trimval;
			$clientVets[$pet['ownerptr']][] = $trimval;
		}
		else if($label == 'Breed') $pet['breed'] = $trimval;
		else if($label == 'Color') $pet['color'] = $trimval;
		else if($label == 'inactivePet') $pet['active'] = booleanFromTrueFalse($trimval) ? '0' : '1';
		else if(in_array($label, array_keys($petColLabels))) {
			$custFieldDescription = identifyCustomField($label, 'pet');
			if($custFieldDescription) {
				// determine type of field
				$type = $custFieldDescription['desc'][2];
				if($type == 'boolean') $trimval = booleanFromTrueFalse($trimval);
				$custom[$custFieldDescription['name']] = $trimval;
			}
			else {
				$notes[] = "$label: $trimval";
			}
		}
	}
	
	if(!$pet['ownerptr'])
		echo "<p style='color:#2F4F4F'>ORPHAN PET: [$petid] (unknown owner: [$oldOwnerptr] $clientname) {$pet['name']} ({$pet['type']}) {$pet['breed']} {$pet['sex']} ";
	
	else {
		if(!$pet['name']) $pet['name'] = 'UNNAMED';
		if($notes) $pet['notes'] = join("\n", $notes);
		$petid = insertTable('tblpet', $pet, 1);
		if($vets) $petVets[$petid] = $vets;
		//if($custom) savePetCustomFields($petid, $custom, null, $pairsOnly=true);
		$clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$pet['ownerptr']} LIMIT 1", 1);
		echo "<p>CREATED $clientname's PET: [$petid] {$pet['name']} ({$pet['type']}) {$pet['breed']} {$pet['sex']} ";
	}
	foreach((array)$custom as $field => $val)
		replaceTable("relpetcustomfield", 
			array('petptr'=>$petid, 'fieldname'=>$field, 'value'=>$val), 1);
	
	return $pet;

}





function handleClientRow($row) {
	//BusinessID	ClientID	EmailAddress	FirstName	LastName	AlternateLastName	DayTimePhone	EveningPhone	
	// EmergencyContact	EmergencyPhone	EmergencyKey	EmergencyContact2	EmergencyPhone2	EmergencyKey2	
	// EmergencyContact3	EmergencyPhone3	EmergencyKey3	OtherContact	OtherPhone	OtherKey	
	// AddressStreet	AddressSuite	AddressCity	AddressState	AddressZIPCode	
	// SpecialDirections	BillingName	BillingAddressStreet	BillingAddressSuite	BillingAddressCity	BillingAddressState	BillingAddressZIPCode	
	// PaymentMethod	CreditBalance	DiscountPercentage	PrimaryStaffID	SecondaryStaffID	Notes	NoteAddedBy	DateNoteAdded	
	// Inactive	Username	SpouseOther	CellPhone	ReferredBy	KeyLocation	
	// DisarmAlarm	ArmAlarm	AlarmPassword	AlarmCompany	AlarmCompanyPhone	AlarmLocation	
	// GateCode	TrashInside	TrashOutside	BreakerBoxLocation	WaterShutoffLocation	Thermostat	WasteDisposal	
	// CleaningSupplies	Flashlight	InstrTrash	InstrMailNews	InstrParking	InstrLightsBlinds	InstrPlants	EmergencyWorkPermission	
	// EmergencyVetPermission	Alt1StaffID	Alt2StaffID	Alt3StaffID	ReferralChoice	ReferralSubChoice	ReferralChoiceInfo	
	// PartnerDiscountCodeID	PartnerDiscountType	PartnerDiscountMemberID	
	// AdminNote	AdminNoteAddedBy	DateAdminNoteAdded	DateCreated	ReferralThirdChoice	ClassificationID
	global $dataHeaders, $oldNewClients;
	$client = array();
	global $colLabels;
	setCustomFieldLabels(); // sets global $colLabels
	foreach($dataHeaders as $i => $label) {
//echo "$i: [$label] [{$row[$i]}]<p>";
		$trimval = html_entity_decode(trim("".$row[$i]));
		if(!$trimval) continue;
		
		if($label == 'ClientID') $oldClientId = $trimval;
		else if(in_array($label, array('BusinessID', 'AdminNoteAddedBy', 'DateAdminNoteAdded', 'ClassificationID'))) ; // no-op : 
		else if($label == 'EmailAddress') $badEmails = processEmails($trimval, $client);
		else if($label == 'FirstName') $client['fname'] = $trimval;
		else if($label == 'LastName') $client['lname'] = $trimval;
		else if($label == 'AlternateLastName') $client['lname2'] = $trimval;
		else if($label == 'DayTimePhone') $client['workphone'] = $trimval;
		else if($label == 'EveningPhone') $client['homephone'] = $trimval;

		else if($label == 'EmergencyContact') $emergency['name']=$trimval;
		else if($label == 'EmergencyPhone') $emergency['homephone']=$trimval;
		else if($label == 'EmergencyKey') $emergency['haskey'] = booleanFromTrueFalse($trimval);
		
		else if($label == 'EmergencyContact2') $neighbor['name']=$trimval;
		else if($label == 'EmergencyPhone2') $neighbor['homephone']=$trimval;
		else if($label == 'EmergencyKey2') $neighbor['haskey']= booleanFromTrueFalse($trimval);
		
		else if(in_array($label, array('EmergencyContact3', 'EmergencyPhone3', 'EmergencyKey3')))
			$emergencyContact3 = "Emergency Contact 3: "
				.rowAtHeader($row, 'EmergencyContact3')
				.' - '.rowAtHeader($row, 'EmergencyPhone3')
				.' has key: '.(rowAtHeader($row, 'EmergencyKey3') == 'True' ? 'yes' : 'no');
		
		else if(in_array($label, array('OtherContact', 'OtherPhone', 'OtherKey')))
			$otherContact = "Other Contact: "
				.rowAtHeader($row, 'OtherContact')
				.' - '.rowAtHeader($row, 'OtherPhone')
				.' has key: '.(rowAtHeader($row, 'OtherKey') == 'True' ? 'yes' : 'no');
		
		else if($label == 'AddressStreet') $client['street1'] = $trimval;
		else if($label == 'AddressSuite') $client['street2'] = $trimval;
		else if($label == 'AddressCity') $client['city'] = $trimval;
		else if($label == 'AddressState') $client['state'] = $trimval;
		else if($label == 'AddressZIPCode') $client['zip'] = $trimval;
		else if($label == 'SpecialDirections') $client['directions'] = $trimval;
		
		else if($label == 'BillingName') $notes[]= "Billing Name: $trimval";
		else if($label == 'BillingAddressStreet') $client['mailstreet1'] = $trimval;
		else if($label == 'BillingAddressSuite') $client['mailstreet2'] = $trimval;
		else if($label == 'BillingAddressCity') $client['mailcity'] = $trimval;
		else if($label == 'BillingAddressState') $client['mailstate'] = $trimval;
		else if($label == 'BillingAddressZIPCode') $client['mailzip'] = $trimval;
		
		else if($label == 'PaymentMethod') ;  //no-op;
		else if($label == 'CreditBalance') ;  //no-op;
		else if($label == 'DiscountPercentage') ;  //no-op;
		else if($label == 'PrimaryStaffID') $defaultProvider = $trimval;
		else if($label == 'SecondaryStaffID') $secondaryProvider = $trimval;
		else if($label == 'Notes') $notes[]= "Notes: $trimval";
		else if($label == 'NoteAddedBy') ;  //no-op;
		else if($label == 'DateNoteAdded') ;  //no-op;
		else if($label == 'Inactive') $client['active'] = booleanFromTrueFalse($trimval) ? '0' : '1';
		else if($label == 'Username') $username = $trimval;
		else if($label == 'SpouseOther') handleFnameSpaceLname($trimval, $client, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname2');
		else if($label == 'CellPhone') $client['cellphone'] = $trimval;
		else if($label == 'KeyLocation') $officenotes[] = "KeyLocation: $trimval";
		else if($label == 'DisarmAlarm') $alarminfo[] = "Disarm: $trimval";
		else if($label == 'ArmAlarm') $alarminfo[] = "Arm: $trimval";
		else if($label == 'AlarmPassword') $alarminfo[] = "Password: $trimval";
		else if($label == 'AlarmCompany') $client['alarmcompany'] = $trimval;
		else if($label == 'AlarmCompanyPhone') $client['alarmcophone'] = $trimval;
		else if($label == 'AlarmLocation') $alarminfo[] = "Location: $trimval";
		else if($label == 'GateCode') $client['garagegatecode'] = $trimval;
		else if($label == 'DateCreated') $client['setupdate'] = date('Y-m-d', strtotime($trimval));
		else if(in_array($label, array('Alt1StaffID','Alt2StaffID','Alt3StaffID'))) 
			$altStaff[] = $trimval;
		
		else if(in_array($label, array_keys($colLabels))) {
			$custFieldDescription = identifyCustomField($label);
			if($custFieldDescription) {
				// determine type of field
				$type = $custFieldDescription['desc'][2];
				if($type == 'boolean') $trimval = booleanFromTrueFalse($trimval);
				$custom[$custFieldDescription['name']] = $trimval;
			}
			else {
				$notes[] = "$label: $trimval";
			}
		}
		
		// ##############################################################
		
//print_r($client);echo "<p>";
	}
//print_r($custom);exit;	
	
	
	$client['alarminfo'] = join("\n", (array)$alarminfo);
	foreach((array)$badEmails as $email)
		$notes[] = "Invalid email address: $email";
	if($emergencyContact3) $client['notes'][] = $emergencyContact3;
	if($otherContact) $client['notes'][] = $otherContact;
	$client['notes'] = join("\n", (array)$notes);
	$client['officenotes'] = join("\n", (array)$officenotes);
	// $username
	
	
	$newClientId = saveNewClient($client);
	
	if($emergency) saveClientContact('emergency', $newClientId, $emergency);
	if($neighbor) saveClientContact('neighbor', $newClientId, $neighbor);
	
	
	global $defaultProviderPOPSIds, $secondaryProviderPOPSIds, $alternateStaffByClient;
	if($defaultProvider) $defaultProviderPOPSIds[$newClientId] = $defaultProvider;
	if($secondaryProvider) $secondaryProviderPOPSIds[$newClientId] = $secondaryProvider;
	if($altStaff) $alternateStaffByClient[$newClientId] = $altStaff;
	
	$oldNewClients[$oldClientId] = $newClientId;
	
	echo "<p>CREATED CLIENT: [$newClientId] {$client['fname']} {$client['lname']}";
	foreach((array)$badEmails as $email)
		echo "<br>Invalid email address: $email";
	
	
	foreach((array)$custom as $field => $val)
		replaceTable("relclientcustomfield", 
			array('clientptr'=>$newClientId, 'fieldname'=>$field, 'value'=>$val), 1);
	
	return $client;	
}

function setCustomFieldLabels($rawKeyLabelPairs=null) {
	// Keys are col headers in XML sheet.  Labels are labels in LT for corresponding cust fields.
	global $colLabels;
	if(!$colLabels) {
		$raw = $rawKeyLabelPairs ? $rawKeyLabelPairs
			: "TrashInside|Trash (inside)||TrashOutside|Trash (outside)||BreakerBoxLocation|Breaker Box Location||"
								."WaterShutoffLocation|Water Shutoff Location||Thermostat|Thermostat||WasteDisposal|Waste Disposal||"
								."CleaningSupplies|Cleaning Supplies||Flashlight|Flashlight||InstrTrash|Trash Instructions||"
								."InstrMailNews|Mail/News Instructions||InstrParking|Parking Instructions||"
								."InstrLightsBlinds|Lights/Blinds Instructions||InstrPlants|Plant Care Instructions||"
								."EmergencyWorkPermission|Emergency Work Permission";
		$pairs = explode('||', $raw);
		foreach($pairs as $part) {
			$parts = explode('|', $part);
			$colLabels[$parts[0]] = $parts[1];
		}
	}
}

function setCustomPetFieldLabels($rawKeyLabelPairs=null) {
	// Keys are col headers in XML sheet.  Labels are labels in LT for corresponding cust fields.
	// Aggressive	AggressionDetails	
	// Medications	FoodLocation	LitterBoxLocation	LeashLocation	CarrierLocation	VaccinationsCurrent	VetHasCC	
	global $petColLabels;
	if(!$petColLabels) {
		$raw = $rawKeyLabelPairs ? $rawKeyLabelPairs
			: "Aggressive|Aggressive||AggressionDetails|Aggression Details||Medications|Medications||"
								."FoodLocation|Food Location||LitterBoxLocation|Litter Box Location||LeashLocation|Leash Location||"
								."VaccinationsCurrent|Vaccinations Current||VetHasCC|Vet Has CC";
		$pairs = explode('||', $raw);
		foreach($pairs as $part) {
			$parts = explode('|', $part);
			$petColLabels[$parts[0]] = $parts[1];
		}
	}
}

function identifyCustomField($col, $pet=false) {
	require_once "custom-field-fns.php";
	global $colLabels, $petColLabels;
	$customLabels = $pet ? $petColLabels : $colLabels;
	if(!$customLabels) {
		if($pet) setPetCustomFieldLabels();
		else setCustomFieldLabels();
	}
	$label = $customLabels[$col];
	if($label) {
		$fields = $pet ? getCustomFields($activeOnly=false, $visitSheetOnly=false, $fieldNames=getPetCustomFieldNames()) : getCustomFields();
		foreach($fields as $nm => $field) // format: label|active|onelineORtextORboolean|visitsheet|clientvisible
			if($field[0] == $label)
				return array('name'=>$nm, 'desc'=>$field);
	}
}

function processEmails($trimval, &$client) { // handles multiple emails
	if(!$trimval) return;
	foreach(decompose($trimval, "\n") as $x) {
		foreach(decompose($x, ",") as $x1) {
			foreach(decompose($x1, "&") as $x2) {
				foreach(decompose($x1, ";") as $x3) {
					if($x3)	{
						$emails[] = $x3;
					}
				}
			}
		}
	}
	foreach($emails as $email) {
		if(!isEmailValid($email))
			$badEmails[] = $email;
		else if(!$client['email']) $client['email'] = $email;
		else if(!$client['email2']) $client['email2'] = $email;
		else $client['notes'][] = "Other email: $email";
	}
	return $badEmails;
}
	
function decompose($str, $delim) {
	return array_map('trim', explode($delim, $str));
}

function findVetByName($nm) {
	return fetchRow0Col0("SELECT vetid FROM tblvet WHERE CONCAT_WS(' ', fname, lname)  = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findClinicByName($nm) {
	return fetchRow0Col0("SELECT clinicid FROM tblclinic WHERE clinicname = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function myfgetcsv($strm) {
	global $lastSectionStarted, $tagPrefix;

	global $leftover;
	
	for($s = "$leftover"; !feof($strm) && ($start = strpos($s, "<{$tagPrefix}Row")) === FALSE; ) {
		$s .= fgets($strm);
	}
	
	
	
	if(!$s) return null;
//echo "<p>[[[".htmlentities($s).']]] ('.(strpos($s, '</ss:Row>') === FALSE).')<p>';		
	while(!feof($strm) && (strpos($s, "</{$tagPrefix}Row>") === FALSE))
		$s .= fgets($strm);
	if(($end = strpos($s, "</{$tagPrefix}Row>")) === FALSE) echo "INCOMPLETE ROW:<br>$s<br>";
	else {
		$endSheet = strpos($s, "</{$tagPrefix}Worksheet>");
		$leftover = substr($s,  $end+strlen("</{$tagPrefix}Row>"));
		if(($ws = strpos($s, "<{$tagPrefix}Worksheet ss:Name=\"")) !== FALSE 
				&& $ws < $end) {
			$ws = $ws+strlen("<{$tagPrefix}Worksheet ss:Name=\"");
			$wsend = strpos($s, '"', $ws);
			$lastSectionStarted = substr($s, $ws, $wsend - $ws);
		}
//echo htmlentities($leftover).'</br>';		
//echo htmlentities($s).'<hr>';		
		$start = strpos($s, '>', $start)+1;
//echo '<font color=blue>'.print_r(array_map('strip_tags', explode("</{$tagPrefix}Cell>", $leftover)),1).'</font><br>';
		$row = array_map('trim', array_map('strip_tags', explode("</{$tagPrefix}Cell>", substr($s, $start,$end-$start))));
		$fullSlot = false;
//echo '<font color=darkgreen>'.print_r($row,1).'</font><br>';
		if($row) while(!$fullSlot && $row) if($fullSlot = array_pop($row)) $row[] = $fullSlot;
//echo print_r($row,1).'<hr>';		
		return $row;
	}
}



function booleanFromYN($val) { return $val == 'yes' ? '1' : '0'; }
function booleanFromTrueFalse($val) { return $val == 'True' ? '1' : '0'; }

function headerIndex($header) {
	global $dataHeaders;
	return array_search($header, $dataHeaders);
}

function rowAtHeader($row, $header) {
	return trim($row[ headerIndex($header)]);
}

function handleFnameSpaceLname($str, &$destination, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey=null) {
	if(!$singleNameDefaultKey) $singleNameDefaultKey = $lnameKey;
	$parts = array_map('trim', explode(' ', $str));
	if(count($parts)) {
		$destination[$lnameKey] = array_pop($parts);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
	if(!$destination[$singleNameDefaultKey]) {
		if($singleNameDefaultKey == $fnameKey) {
			$destination[$singleNameDefaultKey] = $destination[$lnameKey];
			$destination[$lnameKey] = null;
		}
		else if($singleNameDefaultKey == $lnameKey) {
			$destination[$singleNameDefaultKey] = $destination[$fnameKey];
			$destination[$fnameKey] = null;
		}
	}
}




