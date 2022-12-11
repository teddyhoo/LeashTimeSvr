<?// import-clients-csv-adhoc.php

// https://LEASHTIME.COM/import-clients-csv-adhoc.php?file=nannydolittle/Clients-active.csv
// https://LEASHTIME.COM/import-clients-csv-adhoc.php?file=nannydolittle/Clients-inactive.csv&inactive=1
// https://leashtime.com/import-clients-csv-adhoc.php?multiline=1&file=cahillscrittercare/Client list spreedsheet4-26-11.csv


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";
require_once "field-utils.php";

locked('o-');

extract($_REQUEST);

$file = "/var/data/clientimports/$file";

set_time_limit(300);

echo "<hr>";

$delimiter = strpos($file, '.xls') || strpos($file, '.iif') ? "\t" : ',';
$strm = fopen($file, 'r');
//$line0 = trim(fgetcsv($strm, 0, $delimiter)); 
$row = fgetcsv($strm, 0, $delimiter);
print_r($row);
$dataHeaders = array_map('trim', $row);// consume first line (field labels)

echo $file."<p>";
echo "dataHeaders: ".count($dataHeaders).':<br>'.join(',', $dataHeaders).'<hr>';
//echo "map: ".print_r($map,1);exit;

//print_r($strm);
$n = 1;

//while($line = trim(fgets($strm))) echo "$line<br>";

$thisdb = $db;

$clientMap = array();  // mapID => clientid

$_SESSION['preferences'] = fetchPreferences();

$customFields = getCustomFields('activeOnly');

//print_r($_SESSION['preferences']);exit;
$multiline = $_GET['multiline'];
$incompleteRow = null;
while($row = getCSVRow($strm, 0, $delimiter)) {
	$n++;
	echo "[$n] ";
	// HANDLE EMPTY LINES
	if(skipRow($row)) {echo "<p><font color=red>Skipped Line #$n</font><br>";continue;}
	// HANDLE CONTINUATIONS OF INCOMPLETE LINES
	else handleRow($row);
	
}

echo "<hr>";

// CONVERSION FUNCTIONS
// TRUNCATE testtest.relclientcustomfield;TRUNCATE testtest.tblclient;TRUNCATE testtest.tblpet;
function handleRow($row) {
	$empty = true;
	foreach($row as $x) if($x) $empty = false;
	if($empty) {echo "null row:".!$row;exit;}
	
	
	global $thisdb, $dataHeaders, $lastClient;
	
	if($thisdb == 'agvpetsitting') return handleBettaWalka($row);
	if($thisdb == 'wisconsinpetcare') return handleWisconsinPetcare($row);
	if($thisdb == 'nannydolittle') return handleRowNannydolittle($row);
	if($thisdb == 'cahillscrittercare') return handleRowCahills($row);
	if($thisdb == 'FIRSTRUNqueeniespets') return handleQueeniesPets($row);
	if($thisdb == 'queeniespets') return fixQueeniesPets($row);
	if($thisdb == 'crestviewpetcare') return handleCrestviewPets($row);
	if($thisdb == 'dogwalkingnetwork') return handleDogWalkingNetwork($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'goldcoastpetsau') return handleGoldCoastPets($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'petassist') return handlePetAssist($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'primordialpets') return handlePuppyUprising($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'bluedogpetcarema') return handleBlueDog($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'aleguppetservices') {
		if(in_array('pet_num', $dataHeaders))
			return handleALegUpPetsRow($row);
		else 
			return handleALegUpPetsCustRow($row);
	}
	if($thisdb == 'luckymuttsandmore') return handleLuckyMuttsQuickBooks($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'puppyuprising') return handlePuppyUprising($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'peakcitypuppy') return handlePeakCityPuppy2($row);  // FIRST IMPORT: handlePeakCityPuppy($row); apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'hart2hartpetcare') return handleHart2Hart($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'downwarddogpetcare') return handleDownwardDog($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'petcarersbendigo') return handlePetCarersBendigo($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'lovecitypets') return handleBettaWalkaExport($row, $ignore=array('Keesa Bigler', 'Charlotte Markward'));  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'happytailsofphilly') return handleQuickBooksFromHappyTailsOfPhilly($row);
	//if($thisdb == 'strutnmutt') return handleQuickBooksFromStrutNMutt($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'strutnmutt') return fixStrutNMutt($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'pawfectpetservices') return handleQuickBooksFromPawfectPetServices($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'pupecise') return handlePupecise($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'prancearound') return handlePrancearound($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'homepetz' && $_REQUEST['pets']) return TESTHomepetzPets($row);  // apparently output from Quickbooks with petnames combined with client names
	//if($thisdb == 'homepetz') return handleHomepetz($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'wholepetsaustin') return handleWholePetsAustin($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'fourpawsmetropolitan') return handleFourPawsMetropolitan($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'k9companionps') return handleK9companionps($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'comfycozypet') return handleComfyCozyPet($row);  // apparently output from Quickbooks with petnames combined with client names
	if($thisdb == 'mydesertdog') return handleMyDesertDog($row);
	if($thisdb == 'crittercaretakers') return handleGMailGoogleCSV($row);
	if($thisdb == 'barkbarkclub') return handleBettaWalka($row);
	if($thisdb == 'jordanspetcare') return handlJordansPetCare($row);
	if($thisdb == 'apassion4pets') return handlAPassion4PetsAKAHouseCallsPetSitting($row);
	if($thisdb == 'familypetsitters') return handleQuickBooksFromFamilyPetSitters($row);
	if($thisdb == 'fidofitnessandplay') return handleFidoFitnessAndPlay($row);
	if($thisdb == 'housebrokenny') return handleBettaWalkaExport($row, $ignore=null); // Acct Last, Acct First, Pet(s) First...
	if($thisdb == 'crittersittersinc') {
		//$delimiter = "\t";
		//return handleCritterSittersInc($row, $ignore=null); // Acct Last, Acct First, Pet(s) First...
		return handleQuickBooksFromCritterSittersInc($row, $ignore=null); // Acct Last, Acct First, Pet(s) First...
	}
	if($thisdb == 'raleighpets') return handleRaleighPets($row, $ignore=null); // Acct Last, Acct First, Pet(s) First...
	if($thisdb == 'furrygodmotheronline') {$multiline = 1; return handleFurryGodmotherConejo($row, $ignore=null);}
	if($thisdb == 'thepawsitter') {$multiline = 1; return handleThePawSitter($row, $ignore=null);}
	if($thisdb == 'greenpawschicago') {$multiline = 1; return handleGreenPaws($row, $ignore=null);}
	if($thisdb == 'barkngoodtime') {$multiline = 1; return handleBarknGoodTime($row, $ignore=null);}
	if($thisdb == 'prideygirlpetcare') {$multiline = 1; return handlePrideyGirl($row, $ignore=null);}
	if($thisdb == 'hansenhomeandpet') {$multiline = 1; return handleHansenHomeAndPet($row, $ignore=null);}
	if($thisdb == 'barpetservices') {$multiline = 1; return handleBARpetservices($row, $ignore=null);}
	if($thisdb == 'mypetsfriend') {$multiline = 1; return handleMyPetsFriend($row, $ignore=null);}
	if($thisdb == 'fivepawsdelco') {$multiline = 1; return handleFivePawsDelco($row, $ignore=null);}
	if($thisdb == 'doggyday') {$multiline = 1; return handleDoggyDay($row, $ignore=null);}
	if($thisdb == 'missjanespetsitting') {$multiline = 1; return handleMissJanesPetSitting($row, $ignore=null);}
	if($thisdb == 'doghousegirls') {$multiline = 1; return handleDogHouseGirls($row, $ignore=null);}
	if($thisdb == 'gooddogwalkingandsitting') {$multiline = 1; return handleGoodDogWalkingandSitting($row, $ignore=null);}
	if($thisdb == 'parthenonpups') {$multiline = 1; return handleParthenonPups($row, $ignore=null);}
	if($thisdb == 'pawsitivelypooches') {$multiline = 1; return handlePawsitivelyPooches($row, $ignore=null);}
	if($thisdb == 'thepetgurl') {$multiline = 1; return handleThePetGurl($row, $ignore=null);}
	if($thisdb == 'happytailsofsj') {$multiline = 1; return handleHappyTailsofSJ($row, $ignore=null);}
	if($thisdb == 'spoiledrottenpetsitting') {$multiline = 1; return handlePetSitClick($row, $extra=null);}
	if($thisdb == 'goodnessgraciousextraordinary') {$multiline = 1; return handlegoodnessgraciousextraordinary($row, $extra=null);}
	if($thisdb == 'happyfeetdogwalking') {$multiline = 1; return handlePetSitClick($row, $extra=null);} // 3/23/2015
	if($thisdb == 'canineadventure') {$multiline = 1; return handleCanineAdventure($row, $extra=null);} // 3/23/2015
	if($thisdb == 'sarahsits') {$multiline = 1; return handleSarahSits($row, $ignore=null);}
	if($thisdb == 'k9adventurefitness') {$multiline = 1; return handleK9AdventureFitness($row, $ignore=null);}
	if($thisdb == 'siriuspetcare') {$multiline = 1; return handleSiriusPetCare($row, $ignore=null);}
	if($thisdb == 'rufflifeny') {$multiline = 1; return handleRuffLifeNY($row, $ignore=null);}
	if($thisdb == 'happytailsindy') {$multiline = 1; return handlePetSitClick($row, $extra=null, $nameHandler='handleLnameCommaFname');}
	if($thisdb == 'wagsdogwalkers') {$multiline = 1; return handleBurkesDogCareWagsDogWalkers($row, $extra=null, $nameHandler='handleLnameCommaFname');}
	if($thisdb == 'pamperedpawspetsittingsvc') {$multiline = 1; return handlePamperedPawsPetSittingSvc($row, $extra=null, $nameHandler='handleLnameCommaFname');}
	if($thisdb == 'dogsofberkeley') return handleGMailGoogleCSV($row);
	if($thisdb == 'positivepawspetservices') return handlePositivePawsPetServices($row);
	if($thisdb == 'itsadogslifeny') return handleQuickBooksFromItsADogsLifeNY($row, null);
	if($thisdb == 'dogboys') return handleDogboys($row, null);
	if($thisdb == 'princespetcare') return handlePrincesPetCare($row, null);
	if($thisdb == 'runmydog') {
		if($_GET['keys']) return handleRunMyDogKeys($row); // Time To Pet hand-preparation required.  See below.
		else if($_GET['fix']) return FIXhandleRunMyDog($row);
		else return handleRunMyDog($row, null);
	}
	if($thisdb == 'arkangelspetcare') return handleArkangelsPetCare($row, null);
	if($thisdb == 'pawsomepetsitting') handlePawsomePets($row);
	if($thisdb == 'poopinagroupllc') handlePoopInAGroup($row);
	if($thisdb == 'thecomfycanine') handleTheComfyCanineGMailContacts($row);
	if($thisdb == 'atlasdoghouse') handleAtlasDogHouse($row); // Source: Square
	if($thisdb == 'dogwalkingdc') handleDogWalkingDCFromAtlasDogHouse($row); // Source: Square
	if($thisdb == 'naturesnanny') handleNaturesNanny($row); // Source: Square
	if($thisdb == 'dogcampla') handleDogCampLASitImport($row); 
	if($thisdb == 'creatureconcierge') handleCreatureConcierge($row); 
}

echo "<hr>$finalNote";
if($added) echo "<p>Added: $added";
if(function_exists('wrapUp')) wrapUp();


function handleCreatureConcierge($row) {
	static $rowCount;
	global $dataHeaders, $file;
	// First name,	Last name,	Email address - other,

	$rowCount++;
	$client =  array('active'=>1);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First name') $client['fname'] = $trimVal;
		else if($label == 'Last name') $client['lname'] = $trimVal;
		if($label == 'First name2') $client['fname2'] = $trimVal;
		else if($label == 'Last name2') $client['lname2'] = $trimVal;
		else if(strpos($label, 'Email')  !== FALSE)
			foreach(processEmails($trimVal, $client) as $email) $badEmails[] = $email;
	}
	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);
	$clientid = insertTable('tblclient', $client, 1);
	echo "Added client [$clientid] {$client['fname']} {$client['lname']} - {$client['email']}, {$client['email2']}<br>";
}
	
	
function handleDogCampLASitSITTERImport($row) {
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$provider =  array('active'=>1);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'FIRST NAME') $provider['fname'] = $trimVal;
		else if($label == 'LAST NAME') $provider['lname'] = $trimVal;
		else if($label == 'PHONE') $provider['cellphone'] = $trimVal;
		else if($label == 'EMAIL') 
			foreach(processEmails($trimVal, $provider) as $email) $badEmails[] = $email;
	}
	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $provider['notes'] = join("\n", $notes);
	$providerid = insertTable('tblprovider', $provider, 1);
	logChange($providerid, 'tblprovider', 'c', $note='.');
	echo "Added sitter [$providerid] {$provider['fname']} {$provider['lname']} - {$provider['cellphone']} - {$provider['email']}<br>";
}
	
	
	
	
function handleDogCampLASitImport($row) {
	//https://leashtime.com/import-clients-csv-adhoc.php?file=dogcampla/
	//Last Name	First Name	Pet 1	Pet 2	Pet 3+	Species	Street	Apt	City	State	Zip Code
	static $rowCount;
	global $dataHeaders, $file;
	if(in_array('PAY SCALE', $dataHeaders)) return handleDogCampLASitSITTERImport($row);
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Street') $client['street1'] = $trimVal;
		else if($label == 'Apt') $client['street2'] = "#$trimVal";
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip Code') $client['zip'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Species') {
			if(!in_array($trimVal, array('Mixed', 'Both')))
				$species = $trimVal;
		}
		else $petnames[] = $trimVal;
	}
	if($client['fname'] && $client['lname']) 
		$found = findClientByName($fullName = $client['fname'].' '.$client['lname']);
	if($found) {
		require_once "pet-fns.php";
		$foundpets = getClientPetNames($found, $inactiveAlso=false, $englishList=true);
		$address = join(', ', fetchFirstAssoc("SELECT street1, street2, city, state, ZIP FROM tblclient WHERE clientid = $found", 1));
		echo "FOUND client {$client['fname']} {$client['lname']} with $foundpets ($address)";
	}
	else {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
		foreach((array)$petnames as $petName) {
			$pet = array('name'=>$petName, 'ownerptr'=>$clientptr, 'active'=>1);
			if($species) $pet['type'] = $species;
			insertTable('tblpet', $pet, 1);
		}
		setClientPreference($clientptr, "flag_1", "19|");

		echo "Added client [$clientptr] {$client['fname']} {$client['lname']}";
		$addr = array($client['street1'] , $client['city'] , $client['state'] , $client['zip']);
		echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
		if($petnames) echo "<br><font color=red>Pets: ".join(', ', $petnames)." ($species)</font>";
	}
	echo "<br>";
}
	
function handleNaturesNanny($row) {
	//https://leashtime.com/import-clients-csv-adhoc.php?file=thecomfycanine/LTfile.CSV
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Customer') $customer = $trimVal;
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Street') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'ZIP')
			$client['zip'] = strlen($trimVal) == 4 ? "0$trimVal" : $trimVal;
		else if($label == 'Email')
			foreach(processEmails($trimVal, $client) as $email) $badEmails[] = $email;
	}
	if(!$client['fname']) $client['fname'] = $customer;
	if(!$client['lname']) $client['lname'] = 'UNKNOWN';
	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);
	$clientptr = saveNewClient($client);
	global $added;
	$added += 1;

	echo "Added client {$client['fname']} {$client['lname']}";
	$addr = array($client['street1'] , $client['city'] , $client['state'] , $client['zip']);
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	if($client['notes']) echo "<br><font color=red>{$client['notes']}</font>";
}

function handleTheComfyCanineGMailContacts($row) {
//https://leashtime.com/import-clients-csv-adhoc.php?file=thecomfycanine/contacts.csv
// First Name, Middle Name, Last Name, Title, Suffix, Initials, Web Page, Gender, Birthday
// Anniversary, Location, Language, Internet Free Busy, Notes, 
// E-mail Address, E-mail 2 Address, E-mail 3 Address, 
// Primary Phone, Home Phone, Home Phone 2, Mobile Phone, Pager, Home Fax, 
// Home Address, Home Street, Home Street 2, Home Street 3, Home Address PO Box, Home City, Home State, Home Postal Code, Home Country,
// Spouse, Children, 
// Manager's Name, Assistant's Name, Referred By, Company Main Phone, Business Phone, Business Phone 2, Business Fax, Assistant's Phone, 
// Company, Job Title, Department, Office Location, Organizational ID Number, Profession, Account, 
// Business Address, Business Street, Business Street 2, Business Street 3, Business Address PO Box, Business City, Business State, Business Postal Code, Business Country, 
// Other Phone, Other Fax, Other Address, Other Street, Other Street 2, Other Street 3, Other Address PO Box, Other City, Other State, Other Postal Code, Other Country, 
// Callback, Car Phone, ISDN, Radio Phone, TTY/TDD Phone, Telex, User 1, User 2, User 3, User 4, Keywords, Mileage, 
// Hobby, Billing Information, Directory Server, Sensitivity, Priority, Private, Categories

// Company and Job Title are used for pets.  Copy them to officenotes
// GOAL: Identify existing LT clients and copy First, Middle, and Last Names, email addresses, job title and company (pets), addresses,
// but NOT notes.
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	//$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	/*foreach($dataHeaders as $i => $label) 
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Customer') $customer = $trimVal;
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
	*/
	$fname = trim(getValueAtHeader($row, 'First Name'));
	$lname = trim(getValueAtHeader($row, 'Last Name'));
	$mname = trim(getValueAtHeader($row, 'Middle Name'));
	$dbfname = leashtime_real_escape_string($fname);
	$dblname = leashtime_real_escape_string($lname);
	$dbmname = leashtime_real_escape_string($mname);
	$lname = leashtime_real_escape_string(getValueAtHeader($row, 'Last Name'));
	$clients = fetchAssociations("SELECT * FROM tblclient WHERE (fname = '$dbfname' OR fname = '$dbmname') AND lname ='$dblname'", 1);
	if(count($clients) > 1) {
		global $ambiguousNames;
		echo "Ambiguous name;[$fname $lname]<br>";
		$ambiguousNames += 1;
	}
	else if($foundByName = count($clients) == 1) {
		$client = $clients[0];
		$summary[] = "[$lname, $fname $mname];found.";
		global $nameMatches;
		$nameMatches += 1;
		$allClients["$fname $lname"] = $client;
	}
	
	if(count($clients) > 0) $nameFound = 1;
	
	$hasPets = getValueAtHeader($row, 'Company') || getValueAtHeader($row, 'Job Title') ? 1 : 0;
	global $rowsWithPets;
	$rowsWithPets += $hasPets;
	
	if(count($clients) < 2) {
		if($email = trim(getValueAtHeader($row, 'E-mail Address'))) $emails[] = $email;;
		if($email = trim(getValueAtHeader($row, 'E-mail 2 Address'))) $emails[] = $email;;
		if($emails) {
			$clients = fetchAssociations("SELECT * FROM tblclient WHERE email IN ('".join("','", $emails)."')", 1);
			if(count($clients) > 1) {
				$summary[] = "(".join(", ", $emails).");Ambiguous emails";
				global $ambiguousEmails;
				$ambiguousEmails += 1;
				$client = null;
			}
			else if(count($clients) == 1) {
				$eclient = $clients[0];
				if($client && $client['fname'] == $eclient['fname'] && $client['lname'] == $eclient['lname'])
					$summary[] = "Found by email also.";
				else if($client) {
					$summary[] = "[*poss. match]; {$eclient['fname']} {$eclient['lname']} ==> Client $fname $lname";
					global $possMatches;
					$possMatches += 1;
				}
				else {
					$summary[] = "[$lname, $fname];found by EMAIL.";
					global $emailMatches;
					$emailMatches += 1;
				}

				$client = $client ? $client : $eclient;
			}
			$nameFound = $nameFound + count($clients);
		}
		
		if(!$client) {
			global $noMatches;
			$noMatches += 1;
		}
		if(!$client) return;
		
		// Handle names.
		/* If(MiddleName) { Middle name represents alt name
				// if 2 parts, use them as lname2, fname2
				// else use parts[0] as fname2, and copy lname => lname2
			 }
		*/
		if($lname) {
				$client['lname'] = $lname;
		}
		if($mname) {
			$parts = multiDecompose($mname, array('/', ' '), 'trim');
			if(count($parts) == 2) {
				$client['lname2'] = $parts[0];
				$client['fname2'] = $parts[1];
			}
			else {
				$client['fname2'] = $parts[0];
				$client['lname2'] = $client['lname'];
			}
		}
		if($fname) {
		//  if(NOT lastname and firstname has four parts AND parts[1] ) use them as fname, lname, fname2, lname2 (Kelly Conovrer Peter Cucchiara)
			$parts = multiDecompose($fname, array('/', ' '), 'trim');
			if(count($parts) == 4) {
				$client['fname'] = $parts[0];
				$client['lname'] = $parts[1];
				$client['fname2'] = $parts[2];
				$client['lname2'] = $parts[3];
			}
		// else if(firstname has two parts AND parts[1] IS NOT EMPTY) use them as fname, fname2
			else if(count($parts) == 2) { echo ($BANG = "*** BANG ** $fname<br>");
				$client['fname'] = $parts[0];
				if(trim($parts[1])) $client['fname2'] = $parts[1];
			}
			else $client['fname'] = $parts[0];
		}
		if($client['fname2'] && !trim($client['lname2'])) {
			$client['lname2'] = $client['lname'];
			//if($BANG) echo "==>fname2: [{$client['fname2']}] lname2: [{$client['lname2']}]<br>";
		}
		
		// PETS
		if($pet = trim(getValueAtHeader($row, 'Company'))) $pets[] = $pet;
		if($pet = trim(getValueAtHeader($row, 'Job Title'))) $pets[] = $pet;
		if($pets) {
			$notes[] = "PET INFO:";
			foreach($pets as $pet) $notes[] = $pet;
		}
		
		if($emails) {
			$badEmails = array();
			foreach($emails as $email)
				foreach(processEmails($email, $client) as $bademail) $badEmails[] = $bademail;
			if(strtoupper($client['email']) == strtoupper($client['email2'])) unset($client['email2']);
		}
		if($badEmails) $notes[] = "BAD EMAIL address(es): ".join(', ', $badEmails);
		
		$homeAddress = getValueAtHeader($row, 'Home Address');
		$businessAddress = getValueAtHeader($row, 'Business Address');
		$otherAddress = getValueAtHeader($row, 'Other Address');
		
		if($otherAddress) echo "<font color=red>BOOOOOOM</font>";

		$ltAddrFields = explode(',', "street1,street2,city,state,zip");
		$ltMailAddrFields = explode(',', "mailstreet1,mailstreet2,mailcity,mailstate,mailzip");
		
		$ltOtherAddrFields = explode(',', "otherstreet1,otherstreet2,othercity,otherstate,otherzip");
		
		$homeFields = explode(',', "Home Street,Home Street 2,Home City,Home State,Home Postal Code");
		$businessFields = explode(',', "Business Street,Business Street 2,Business City,Business State,Business Postal Code");
		$otherFields = explode(',', "Other Street,Other Street 2,Other City,Other State,Other Postal Code");
		
		if(!$homeAddress && $businessAddress) {
			foreach($businessFields as $i => $rowField) {
				if($trimVal = trim(getValueAtHeader($row, $rowField)))
					$client[$ltAddrFields[$i]] = $trimVal;
			}
		}
		if(!$homeAddress && $otherAddress) {
			foreach($otherFields as $i => $rowField) {
				if($trimVal = trim(getValueAtHeader($row, $rowField)))
					$client[$ltAddrFields[$i]] = $trimVal;
			}
		}
		else if($homeAddress) {
			foreach($homeFields as $i => $rowField) {
				if($trimVal = trim(getValueAtHeader($row, $rowField)))
					$client[$ltAddrFields[$i]] = $trimVal;
			}
		}
		if($homeAddress && $businessAddress) {
			foreach($businessFields as $i => $rowField) {
				if($trimVal = trim(getValueAtHeader($row, $rowField)))
					$client[$ltMailAddrFields[$i]] = $trimVal;
			}
		}
		
		
		if($otherAddress) {
					foreach($otherFields as $i => $rowField) {
						if($trimVal = trim(getValueAtHeader($row, $rowField)))
							$other[$ltOtherAddrFields[$i]] = $trimVal;
					}
		}
		
		
		
		if($client['street1']) {
			$addr = null;foreach($ltAddrFields as $fld) $addr[$fld] = $client[$fld];
			if(correctedAddress($addr))
				foreach($addr as $fld => $correctedValue)
					$client[$fld] = $correctedValue;
		}
		
		if($client['mailstreet1']) {
			$addr = null;foreach($ltMailAddrFields as $fld) $addr[$fld] = $client[$fld];
			if(correctedAddress($addr, 'mail'))
				foreach($addr as $fld => $correctedValue)
					$client[$fld] = $correctedValue;
		}
		
		if($other['otherstreet1']) {
			$addr = null;foreach($ltOtherAddrFields as $fld) $addr[$fld] = $other[$fld];
			if(correctedAddress($addr, 'other'))
				foreach($addr as $fld => $correctedValue)
					$other[$fld] = $correctedValue;
		}
		
		
		// phones
		$primaryPhone = canonicalUSPhoneNumber(getValueAtHeader($row, 'Primary Phone'));
		$homePhone = canonicalUSPhoneNumber(getValueAtHeader($row, 'Home Phone'));
		$homePhone2 = canonicalUSPhoneNumber(getValueAtHeader($row, 'Home Phone 2'));
		$mobilePhone = canonicalUSPhoneNumber(getValueAtHeader($row, 'Mobile Phone'));
		$businessPhone = canonicalUSPhoneNumber(getValueAtHeader($row, 'Business Phone'));
		$businessPhone2 = canonicalUSPhoneNumber(getValueAtHeader($row, 'Business Phone 2'));
		
		if($primaryPhone) $client['cellphone2'] = "*$primaryPhone";
		if($homePhone) $client['homephone'] = $homePhone;
		if($homePhone2) ; // no-op
		if($mobilePhone) $client['cellphone'] = $mobilePhone;
		if($businessPhone) $client['workphone'] = $businessPhone;
		if($businessPhone2) $client['cellphone2'] = $businessPhone2;

		
		if($notes) $client['notes'] = join("\n", $notes);
	
		
		$summary[] = "(@{$client['clientid']}) - fname: [{$client['fname']}] lname: [{$client['lname']}] fname2: [{$client['fname2']}] lname2: [{$client['lname2']}]";
		if($emails) $summary[] = "<br>email: [{$client['email']}]".($client['email2'] ? " email2: [{$client['email2']}]" : '');
		if($notes) $summary[] = "<br>NOTES: ".join(',', $notes);
		require_once "gui-fns.php";
		if($client['street1']) {
			$addr = null;foreach($ltAddrFields as $fld) $addr[$fld] = $client[$fld];
			$summary[] = "<br>HOME: ".oneLineAddress($addr);
		}
		if($client['mailstreet1']) {
			$addr = null;foreach($ltMailAddrFields as $fld) $addr[$fld] = $client[$fld];
			$summary[] = "<br>MAIL: ".oneLineAddress($addr);
		}
		if($other['otherstreet1']) {
			$addr = null;foreach($ltOtherAddrFields as $fld) $addr[$fld] = $other[$fld];
			$summary[] = "<br>OTHER: ".oneLineAddress($addr);
		}

		foreach(explode(',', 'homephone,cellphone,workphone,cellphone2') as $fld)
			if($client[$fld]) $phones[$fld] = "$fld: {$client[$fld]}";
		if($phones) 
			$summary[] = "<br>PHONES: ".join(' ', $phones);
		
		if($summary) echo '<hr>'.join(';', $summary).'<br>';
		replaceTable('tblclient', $client);
	}
				
if(!function_exists('wrapUp')) {
	function wrapUp() {
		global $ambiguousNames, $nameMatches, $emailMatches, $possMatches, $ambiguousEmails, $noMatches, $rowsWithPets;
		echo "<hr>";
		echo "ambiguous names: $ambiguousNames<br>";
		echo "ambiguous emails: $ambiguousEmails<br>";
		echo "name matches: $nameMatches<br>";
		echo "email matches: $emailMatches<br>";
		echo "poss matches (by email): $possMatches<br>";
		echo "no matches found: $noMatches<br>";
		echo "rows with pets: $rowsWithPets<br>";
	}
}

}

function correctedAddress(&$addr, $prefix = '') {
	require_once "zip-lookup.php";
	$zipField = $prefix."zip";
	$cityField = $prefix."city";
	$stateField = $prefix."state";
	if($addr[$zipField] && (!$addr[$stateField] || !$addr[$cityField] || strlen($addr[$zipField]) == 4)) {
		if(strlen($addr[$zipField]) == 4) $addr[$zipField] = "0{$addr[$zipField]}";
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$cityState = lookUpZip($addr[$zipField], $noEcho=true);
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'force');
		if(!$cityState) return;
		list($city, $state) = explode('|', $cityState);
		$city = $addr[$cityField] ? $addr[$cityField] : $city; // do NOT overwrite city
		//return "[[$city|$state|{$addr['zip']}]]";
		$addr[$cityField] = $city;
		$addr[$stateField] = $state;
		return true;
	}
}

function handleTheComfyCanineGMailContactsANALYSIS($row) {
//https://leashtime.com/import-clients-csv-adhoc.php?file=thecomfycanine/contacts.csv
// First Name, Middle Name, Last Name, Title, Suffix, Initials, Web Page, Gender, Birthday
// Anniversary, Location, Language, Internet Free Busy, Notes, 
// E-mail Address, E-mail 2 Address, E-mail 3 Address, 
// Primary Phone, Home Phone, Home Phone 2, Mobile Phone, Pager, Home Fax, 
// Home Address, Home Street, Home Street 2, Home Street 3, Home Address PO Box, Home City, Home State, Home Postal Code, Home Country,
// Spouse, Children, 
// Manager's Name, Assistant's Name, Referred By, Company Main Phone, Business Phone, Business Phone 2, Business Fax, Assistant's Phone, Company, Job Title, Department, Office Location, Organizational ID Number, Profession, Account, 
// Business Address, Business Street, Business Street 2, Business Street 3, Business Address PO Box, Business City, Business State, Business Postal Code, Business Country, 
// Other Phone, Other Fax, Other Address, Other Street, Other Street 2, Other Street 3, Other Address PO Box, Other City, Other State, Other Postal Code, Other Country, 
// Callback, Car Phone, ISDN, Radio Phone, TTY/TDD Phone, Telex, User 1, User 2, User 3, User 4, Keywords, Mileage, 
// Hobby, Billing Information, Directory Server, Sensitivity, Priority, Private, Categories

// Company and Job Title are used for pets.  Copy them to officenotes
// Put "Notes" in notes
	
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	/*foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Customer') $customer = $trimVal;
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
	*/
	$fname = trim(getValueAtHeader($row, 'First Name'));
	$lname = trim(getValueAtHeader($row, 'Last Name'));
	$mname = trim(getValueAtHeader($row, 'Middle Name'));
	$dbfname = leashtime_real_escape_string($fname);
	$dblname = leashtime_real_escape_string($lname);
	$dbmname = leashtime_real_escape_string($mname);
	$lname = leashtime_real_escape_string(getValueAtHeader($row, 'Last Name'));
	$clients = fetchAssociations("SELECT * FROM tblclient WHERE (fname = '$dbfname' OR fname = '$dbmname') AND lname ='$dblname'", 1);
	if(count($clients) > 1) {
		global $ambiguousNames;
		echo "Ambiguous name;[$fname $lname]<br>";
		$ambiguousNames += 1;
	}
	else if($foundByName = count($clients) == 1) {
		$client = $clients[0];
		$summary[] = "[$lname, $fname $mname];found.";
		global $nameMatches;
		$nameMatches += 1;
		$allClients["$fname $lname"] = $client;
	}
	
	if(count($clients) > 0) $nameFound = 1;
	
	$hasPets = getValueAtHeader($row, 'Company') || getValueAtHeader($row, 'Job Title') ? 1 : 0;
	global $rowsWithPets;
	$rowsWithPets += $hasPets;
	
	if(count($clients) < 2) {
		if($email = trim(getValueAtHeader($row, 'E-mail Address'))) $emails[] = $email;;
		if($email = trim(getValueAtHeader($row, 'E-mail 2 Address'))) $emails[] = $email;;
		if($emails) {
			$clients = fetchAssociations("SELECT * FROM tblclient WHERE email IN ('".join("','", $emails)."')", 1);
			if(count($clients) > 1) {
				$summary[] = "(".join(", ", $emails).");Ambiguous emails";
				global $ambiguousEmails;
				$ambiguousEmails += 1;
			}
			else if(count($clients) == 1) {
				$eclient = $clients[0];
				if($client && $client['fname'] == $eclient['fname'] && $client['lname'] == $eclient['lname'])
					$summary[] = "Found by email also.";
				else if($client) {
					$summary[] = "[*poss. match]; {$eclient['fname']} {$eclient['lname']} ==> Client $fname $lname";
					global $possMatches;
					$possMatches += 1;
				}

				else $summary[] = "[$lname, $fname];found by EMAIL.";
			}
			$nameFound = $nameFound + count($clients);
		}
		if($summary) echo join(';', $summary).'<br>';
		if($nameFound == 0) {
			global $noMatches;
			$noMatches += 1;
		}
	}
				
if(!function_exists('wrapUp')) {
	function wrapUp() {
		global $ambiguousNames, $nameMatches, $possMatches, $ambiguousEmails, $noMatches, $rowsWithPets;
		echo "<hr>";
		echo "ambiguous names: $ambiguousNames<br>";
		echo "ambiguous emails: $ambiguousEmails<br>";
		echo "name matches: $nameMatches<br>";
		echo "poss matches (by email: $possMatches<br>";
		echo "no matches found: $noMatches<br>";
		echo "rows with pets: $rowsWithPets<br>";
	}
}

}

function handleTheComfyCanineQB($row) {
	//https://leashtime.com/import-clients-csv-adhoc.php?file=thecomfycanine/LTfile.CSV
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Customer') $customer = $trimVal;
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Main Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Alt. Phone') $client['cellphone2'] = $trimVal;
		else if($label == 'Main Email') 
			foreach(processEmails($trimVal, $client) as $email) $badEmails[] = $email;
	}
	if(!$client['fname']) $client['fname'] = $customer;
	if(!$client['lname']) $client['lname'] = 'UNKNOWN';
	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);
	$clientptr = saveNewClient($client);
	global $added;
	$added += 1;

	$petNames = handlePetNames($customer);
	foreach((array)$petNames as $i => $petName) {
		$pet['name'] = trim($petName);
		$pet['ownerptr'] = $clientptr;
		$petid = insertTable('tblpet', $pet, 1);
	}
	echo "Added client {$client['fname']} {$client['lname']}";
	if($petNames) echo " with pets: ".join(', ', $petNames);

	
	foreach(explode(',','cellphone,cellphone2,email') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	if($client['notes']) echo "<br><font color=red>{$client['notes']}</font>";
}

function handlePoopInAGroup($row) {
	//Client	Client Status	Email	Phone	Address	Birthdate	Age	Completed Visits	Future Visits	
	//Payment on File?	Current Passes/Plans	Dependents	Guardian Name	Guardian Email	
	//First Completed Visit Date	First Name	Middle Name	Last Name	Pups Birthday	
	//Emergency Contact (Name/ Relationship)	Emergency Contact Phone Number (***-***-****)	
	//Current Vet Name/Address/Phone Number	Assigned Service Area

	//handleLnameCommaFnameWithSlashesAndAnds($str, &$destination, $fnameKey='fname', $lnameKey='lname') 
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Client') { // use First Name instead
			$petFullName = $trimVal;
			//$parts = explode(' ', $trimVal); 
			//array_pop($parts); discard last name
			//$petNames = join(' ', $parts);
		}
		//handleLnameCommaFnameWithSlashesAndAnds($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		else if($label == 'Client Status') {
			if($trimVal == 'Deleted') return;
			$client['active'] = $trimVal == 'Active';
		}
		else if($label == 'Email') 
			foreach(processEmails($trimVal, $client) as $email) $badEmails[] = $email;
		else if($label == 'Phone') $client['homephone'] = $trimVal;
		else if($label == 'Address') {
			if(is_numeric($trimVal) && (strval((int)$trimVal) == $trimVal))
				$client['zip'] == $trimVal;
			else {
				wrangleAddress($trimVal, $client);
				if($client['city'] == 'Denver/CO' || $client['city'] == 'Denver, CO') {
					$client['city'] == 'Denver';
					$client['state'] == 'CO';
				}
			}
		}
		else if($label == 'Birthdate') ; //no-op
		else if($label == 'Completed Visits') $notes[] = "Completed Visits: $trimVal";
		else if($label == 'Future Visits') $notes[] = "Future Visits: $trimVal";
		else if($label == 'Current Passes/Plans') $notes[] = "Current Passes/Plans: $trimVal";
		else if($label == 'Payment on File?') $notes[] = "Payment on File? $trimVal";
		else if($label == 'Dependents') ; //no-op
		else if($label == 'Guardian Name') handleFnameSpaceLnameWithAnds($trimVal, $client);
		else if($label == 'Guardian Email') {
			// option 1: foreach(processEmails($trimVal, $client) as $email) $badEmails[] = $email;
			$client['officenotes'] = "Guardian Email: $trimVal";// option 2: 
			// option 3: $notes[] = $trimVal;
		}
		else if($label == 'First Completed Visit Date') $notes[] = "First Completed Visit Date: $trimVal";
		else if($label == 'First Name') $petNames = handlePetNames($trimVal);
		else if($label == 'Middle Name') $notes[] = "Middle Name: $trimVal";
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Pups Birthday') $dob = $trimVal;
		
		else if($label == 'Emergency Contact (Name/ Relationship)') {
			$parts = array_reverse(handleDashOrSlashList($trimVal));
			$emergency['name'] = array_pop($parts);
			while($parts)	$emergency['note'][] = array_pop($parts);				
		}
		else if($label == 'Emergency Contact Phone Number (***-***-****)') $emergency['homephone'] = $trimVal;
		else if($label == 'Current Vet Name/Address/Phone Number') {
			$parts = array_reverse(explode('/', $trimVal));
			$clinic['clinicname'] = array_pop($parts);
			$parts = array_reverse($parts);
			foreach($parts as $part) {
				$part = trim($part);
				if(isAPhoneNumber($part)) $clinic['officephone'] = $part;
				else $clinic['notes'] = $part;
			}
		}
		else if($label == 'Assigned Service Area') $custom['custom1'] = $trimVal;
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	if(!$client['fname']) {
		//$client['fname'] = 'UNKNOWN';
		handleFnameSpaceLnameWithAnds($petFullName, $client);
		echo "NO CLIENT NAME FOUND.  USING FULL PET NAME: [$petFullName]<br>";
	}
	
	if($clinic) {
		if(!$clinic['clinicname']) $clinic['clinicname'] = 'Unknown Clinic';
		$clinicptr = findClinicByName($clinic['clinicname']);
		if(!$clinicptr) {
			$clinicptr = insertTable('tblclinic', 
				array('clinicname' => $clinic['clinicname'],
							'officephone' => $clinic['officephone'],
							'notes' => $clinic['notes']
							), 1);
		}
		$client['clinicptr'] = $clinicptr;
	}
	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}
	
	if($emergency) {
		if($emergency['note']) $emergency['note'] = join("\n", $emergency['note']);
		saveClientContact('emergency', $clientptr, $emergency);
	}
	
	if($custom) saveClientCustomFields($clientptr, $custom);
	
	foreach((array)$petNames as $i => $petName) {
		$pet['name'] = trim($petName);
		$pet['ownerptr'] = $clientptr;
		if($i == 0 && $dob) {
			if(strpos($dob, "'") === 0) $dob = substr($dob, 1);
			$pet['dob'] = trim($dob);
		}
		$petid = insertTable('tblpet', $pet, 1);
	}
	
	
	echo "Added client {$client['fname']} {$client['lname']}";
	if($petNames) echo " with pets: ".join(', ', $petNames);

	
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}
	
function handleDogWalkingDCFromAtlasDogHouse($row) {
	$customFieldDescr = 
"custom1//Waiver|1|boolean|1|1"
		;
	$customFieldDescr = explode("\n", $customFieldDescr);
	$existingCustomFields = getCustomFields();
	foreach($customFieldDescr as $line) {
		$pair = explode("//", trim($line));
		if(!$existingCustomFields[$pair[0]]) setPreference($pair[0], $pair[1]);
	}
	
	$customFieldDescr = 
"petcustom1//Rabies|1|boolean|1|1
petcustom2//Bordetella|1|oneline|1|1"
		;
	$customFieldDescr = explode("\n", $customFieldDescr);
	$existingCustomFields = getCustomFields(true, false, getPetCustomFieldNames());
	foreach($customFieldDescr as $line) {
		$pair = explode("//", trim($line));
		if(!$existingCustomFields[$pair[0]]) setPreference($pair[0], $pair[1]);
	}
	
	//Reference ID	First Name	Last Name	Email Address	Phone Number	Nickname	Company Name	
	//Street Address 1	Street Address 2	City	State	Postal Code	Birthday	Memo	Square Customer ID	
	//Creation Source	First Visit	Last Visit	Transaction Count	Total Spend	Email Unsubscribed	Instant Profile	
	//Rabies	Waiver 	Bordetella

	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Email Address') $client['email'] = $trimVal;
		else if($label == 'Company Name') $petNames = handlePetNames($trimVal);
		else if($label == 'Street Address 1') $client['street1'] = $trimVal;
		else if($label == 'Street Address 2') $client['street2'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Postal Code') $client['zip'] = $trimVal;
		else if($label == 'Phone Number') $client['cellphone'] = $trimVal;
		else if($label == 'Birthday') $birthday = $trimVal;
		else if($label == 'Memo') processAtlasDogHouseMemo($client, $trimVal);
		else if($label == 'First Visit') ;// no-op $client['notes'][] = "$label: $trimVal";
		else if($label == 'Last Visit') ;// no-op  $client['notes'][] = "$label: $trimVal";
		else if($label == 'Transaction Count') ;// no-op  $client['notes'][] = "$label: $trimVal";
		else if($label == 'Total Spend') ;// no-op  $client['notes'][] = "$label: $trimVal";
		else if($label == 'Waiver') $custom['custom17'] = $trimVal;
		else if($label == 'Rabies') $petcustom['petcustom29'] = $trimVal;
		else if($label == 'Bordetella') $petcustom['petcustom30'] = $trimVal;
		else if($label == 'Square Customer ID') $client['notes'][] = "$label: $trimVal";
	}
	if(count($petNames) != 1 && $birthday) {
		$client['notes'][] = "Birthday: $birthday";
		$birthday = 0;
	}
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	
	static $unknownCounter = 1;
	if(!$client['fname'] || !$client['lname']) {
		$unknownCounter += 1;
		if(!$client['fname']) $client['fname'] = "UNKNOWN$unknownCounter";
		if(!$client['lname']) $client['lname'] = "UNKNOWN$unknownCounter";
	}
	$clientptr = saveNewClient($client);

	if($custom) saveClientCustomFields($clientptr, $custom);
	if($emergency = $client['emergency']) {
		if($emergency['note']) $emergency['note'] = join("\n", $emergency['note']);
		saveClientContact('emergency', $clientptr, $emergency);
	}

	$petids = array();
	if($petNames) {
		foreach($petNames as $i => $nm) {
			if(!$nm) {
				unset($petNames[$i]);
				continue;
			}
			$petCount++;
			$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
			if($birthday && $i == 0) $pet['notes'] = "Birthday: $birthday";
			$petids[] = $petid = insertTable('tblpet', $pet, 1);
			if($petcustom) savePetCustomFields($petid, $petcustom, null, true);			
		}
	}
	$petNames = $petNames ? join(', ', $petNames) : "<font color=red>NO PETS</font>";
	echo "Added client ($clientptr) {$client['fname']} {$client['lname']} with pets [$petNames]";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}

function handleAtlasDogHouse($row) {
	$customFieldDescr = 
"custom1//Waiver|1|boolean|1|1"
		;
	$customFieldDescr = explode("\n", $customFieldDescr);
	$existingCustomFields = getCustomFields();
	foreach($customFieldDescr as $line) {
		$pair = explode("//", trim($line));
		if(!$existingCustomFields[$pair[0]]) setPreference($pair[0], $pair[1]);
	}
	
	$customFieldDescr = 
"petcustom1//Rabies|1|boolean|1|1
petcustom2//Bordetella|1|oneline|1|1"
		;
	$customFieldDescr = explode("\n", $customFieldDescr);
	$existingCustomFields = getCustomFields(true, false, getPetCustomFieldNames());
	foreach($customFieldDescr as $line) {
		$pair = explode("//", trim($line));
		if(!$existingCustomFields[$pair[0]]) setPreference($pair[0], $pair[1]);
	}
	
	//Reference ID	First Name	Last Name	Email Address	Phone Number	Nickname	Company Name	
	//Street Address 1	Street Address 2	City	State	Postal Code	Birthday	Memo	Square Customer ID	
	//Creation Source	First Visit	Last Visit	Transaction Count	Total Spend	Email Unsubscribed	Instant Profile	
	//Rabies	Waiver 	Bordetella

	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Email Address') $client['email'] = $trimVal;
		else if($label == 'Company Name') $petNames = handlePetNames($trimVal);
		else if($label == 'Street Address 1') $client['street1'] = $trimVal;
		else if($label == 'Street Address 2') $client['street2'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Postal Code') $client['zip'] = $trimVal;
		else if($label == 'Phone Number') $client['homephone'] = $trimVal;
		else if($label == 'Birthday') $birthday = $trimVal;
		else if($label == 'Memo') processAtlasDogHouseMemo($client, $trimVal);
		else if($label == 'First Visit') $client['notes'][] = "$label: $trimVal";
		else if($label == 'Last Visit') $client['notes'][] = "$label: $trimVal";
		else if($label == 'Transaction Count') $client['notes'][] = "$label: $trimVal";
		else if($label == 'Total Spend') $client['notes'][] = "$label: $trimVal";
		else if($label == 'Waiver') $custom['custom1'] = $trimVal;
		else if($label == 'Rabies') $petcustom['petcustom1'] = $trimVal;
		else if($label == 'Bordetella') $petcustom['petcustom2'] = $trimVal;
	}
	if(count($petNames) != 1 && $birthday) {
		$client['notes'][] = "Birthday: $birthday";
		$birthday = 0;
	}
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	
	static $unknownCounter = 1;
	if(!$client['fname'] || !$client['lname']) {
		$unknownCounter += 1;
		if(!$client['fname']) $client['fname'] = "UNKNOWN$unknownCounter";
		if(!$client['lname']) $client['lname'] = "UNKNOWN$unknownCounter";
	}
	$clientptr = saveNewClient($client);

	if($custom) saveClientCustomFields($clientptr, $custom);
	if($emergency = $client['emergency']) {
		if($emergency['note']) $emergency['note'] = join("\n", $emergency['note']);
		saveClientContact('emergency', $clientptr, $emergency);
	}

	$petids = array();
	if($petNames) {
		foreach($petNames as $i => $nm) {
			if(!$nm) {
				unset($petNames[$i]);
				continue;
			}
			$petCount++;
			$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
			if($birthday && $i == 0) $pet['notes'] = "Birthday: $birthday";
			$petids[] = $petid = insertTable('tblpet', $pet, 1);
			if($petcustom) savePetCustomFields($petid, $petcustom, null, true);			
		}
	}
	$petNames = $petNames ? join(', ', $petNames) : "<font color=red>NO PETS</font>";
	echo "Added client {$client['fname']} {$client['lname']} with pets [$petNames]";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}

function processAtlasDogHouseMemo(&$client, $memo) {
	if(!$memo) return;
	$lines = explode("\n", $memo);
	foreach($lines as $i => $line) {
		$person = processOneLinePersonWithPhone('Second owner', $line);
		if(!$person) $person = processOneLinePersonWithPhone('Owner 2:', $line);
		if(!$person) $person = processOneLinePersonWithPhone('2nd owner', $line);
		if($person) {
//if($person) {echo print_r($person, 1)."<hr>";exit;}
			
			if(is_array($person)) {
				handleFnameSpaceLname($person['name'], $client, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname');
				$client['cellphone2'] = $person['phone'];
			}
			else if($person == -1) {
				$i += 1;
				handleFnameSpaceLname(trim($lines[$i]), $client, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname');
				$i += 1;
				$line= trim($lines[$i]);
				if($line && strpos($line, '@')) {
					foreach(processEmails($line, $client) as $email) $badEmails[] = $email;
				}
				else if($line && is_numeric($line[0])) $client['cellphone2'] = $line;
				if($client['cellphone'] || $client['email2']) {
					$i += 1;
					$line= trim($lines[$i]);
					if($line && strpos($line, '@')) {
						foreach(processEmails($line, $client) as $email) $badEmails[] = $email;
					}
					else if($line && is_numeric($line[0])) $client['cellphone2'] = $line;
				}
				else $i -= 1;

			}
			if($person != -1) {
				$i += 1;
				$line= trim($lines[$i]);
				foreach(processEmails($line, $client) as $email) $badEmails[] = $email;
				if(!$client['email2']) $i -= 1;
			}
		}
		$person = processOneLinePersonWithPhone('Emergency contact', $line);
		if($person) {
			$emergency = array();
			if(is_array($person)) {
				$emergency['name'] = $person['name'];
				$emergency['homephone'] = $person['phone'];
			}
			else if($person == -1) {
				// look at next three lines
				for($j = 0; $j < 3; $j++) {
					$gotIt = false;
					$i += 1;
					$line= trim($lines[$i]);
					if($line && strpos($line, '@')) {
						foreach(processEmails($line, $emergency) as $email) $badEmails[] = $email;
						$gotIt = strpos($line, '@');
					}
					else if($gotIt = $line && is_numeric($line[0])) $emergency['homephone'] = $line;
					else if(!$emergency['name']) $gotIt = $emergency['name'] = $line;
					if(!$gotIt) break;
				}
			}
			if($person != -1) {
				$i += 1;
				$line= trim($lines[$i]);
				foreach(processEmails($line, $emergency) as $email) $badEmails[] = $email;
				if(!$emergency['email']) $i -= 1;
			}
			$client['emergency'] = $emergency;
		}
		else if($pair = propertyPair($line))  {
			if($pair[0] == 'Owner 2 First') $client['fname2'] = $pair[1];
			else if($pair[0] == 'Owner 2 Last') $client['lname2'] = $pair[1];
			else if($pair[0] == 'Phone 2') $client['cellphone2'] = $pair[1];
			else if($pair[0] == 'Email 2') $client['email2'] = $pair[1];
			else if($pair[0] == 'Lockbox') $client['garagegatecode'] = "Lockbox: {$pair[1]}";
			else if($pair[0] == 'Door code') $client['garagegatecode'] = "Door code: {$pair[1]}";
			else if($pair[0] == 'Alarm') $client['alarminfo'] = $pair[1];
			else if($pair[0] == 'Second #') $client['cellphone2'] = $pair[1];
		}
	}
	$client['notes'][] = $memo;
}

function processOneLinePersonWithPhone($prefix, $line) {
	// 0 - prefix not found
	// -1 - prefix only found
	// array otherwise
	$upLine = strtoupper($line);
	$upPrefix = strtoupper($prefix);
	if(strpos($upLine, $upPrefix) !== 0) return 0;
	$start = $line[strlen($prefix)] == ':' ? strlen($prefix)+1 : strlen($prefix);
	$line = trim(substr($line, $start));
	if(!$line) return -1;
	for($i=0; $i < strlen($line); $i++) {
		if(is_numeric($line[$i])) {
			$numstart = $i;
			break;
		}
	}
	if($numstart) return array(
		'name'=>trim(substr($line, 0, $numstart)),
		'phone'=>trim(substr($line, $numstart)));
	else return array('name'=>trim($line));
}

function propertyPair($line) {
	$arr = array_map('trim', explode(':', $line));
	if(count($arr) == 2) return $arr;
}

function handleArkangelsPetCare($row) {
	//Client	Key Location	Email	Home Phone	Work Phone	Cell Phone	Cell Phone 2	Street Address	Zip	
	// Primary	Backup	Backup 2	Key #1	Key #2	Key #3		RET TO NANCY
	//handleLnameCommaFnameWithSlashesAndAnds($str, &$destination, $fnameKey='fname', $lnameKey='lname') 
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Client') handleLnameCommaFnameWithSlashesAndAnds($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		else if($label == 'Key Location') $key['bin'] = $trimVal;
		else if($label == 'Email') 
			foreach(processEmails($trimVal, $client) as $email) $badEmails[] = $email;
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Work Phone') $client['workphone'] = $trimVal;
		else if($label == 'Cell Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Cell Phone 2') $client['cellphone2'] = $trimVal;
		else if($label == 'Street Address') $client['street1'] = $trimVal;
		else if($label == 'Zip') {
			$client['zip'] = $trimVal;
			if($cityState = findCityState($trimVal)) {
				$client['city'] = $cityState[0];
				$client['state'] = $cityState[1];
			}
		}
		//else if($label == 'Town') $client['city'] = ($address3 ? "$address3, $trimVal" : $trimVal);
		//else if($label == 'STATE') $client['state'] = $trimVal;
		else if($label == 'Primary') {
			if($primarySitter = createProviderIfNotFoundByName($trimVal)) //findSitterByName($trimVal))
				$client['defaultproviderptr'] = $primarySitter;
			else
				$notes[] = 'Primary Sitter: '.$trimVal;
		}
		else if($label == 'Backup') {
			if($sitter = createProviderIfNotFoundByName($trimVal)) //findSitterByName($trimVal))
				$preferredSitters[] = $sitter;
			else
				$notes[] = 'Backup Sitter: '.$trimVal;
		}
		else if($label == 'Backup 2') {
			if($sitter = createProviderIfNotFoundByName($trimVal)) //findSitterByName($trimVal))
				$preferredSitters[] = $sitter;
			else
				$notes[] = 'Backup Sitter: '.$trimVal;
		}
		else if(in_array($label ,explode(',', 'Key #1,Key #2,Key #3'))) {
			$keydescr[] = "$label: $trimVal";
		}
		else if($label == 'RET TO NANCY') $notes[] = 'RET TO NANCY: '.$trimVal;
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}
	
	if($preferredSitters) {
		require_once "provider-fns.php";
		savePreferredProviderIds($clientptr, $preferredSitters);
	}

	if($keydescr) {
		$key['description'] = join(', ', $keydescr);
		$key['copies'] = count($keydescr);
		for($i=1;$i<=$key['copies'];$i++) $key["possessor$i"] = 'client';
	}
	
	if($key) {
		$key['clientptr'] = $clientptr;
		$keyId = insertTable('tblkey', $key, 1);
		logKeyChange($keyId, $key, $clientptr, true);
	}
	
	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}

function createProviderIfNotFoundByName($trimVal) {
	if($provid = findSitterByName($trimVal)) return $provid;
	else {
		$prov = array('active'=>1);
		handleFnameSpaceLname($trimVal, $prov, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey=null);
		$provid = insertTable('tblprovider', $prov, 1);
		echo "-- Added new sitter: [] {$prov['fname']} {$prov['lname']}<br";
		return $provid;
	}
}
	
	
function handleRunMyDogKeys($row) {
	static $rowCount, $secondaryHeaders, $referralCats;
	global $dataHeaders, $file;
	require_once "key-fns.php";
	$rowCount++;
	// Name/Code	Client	With	Client's Pets	Address 1	Address 2	City	State	Zip
	define("CLIENTINDEX", 1);
	define("WITH", 2);

	$clientName = trim(substr($row[CLIENTINDEX], 0, strpos($row[CLIENTINDEX], '(')));
	$clientNames = array();
	handleFnameSpaceLnameWithAnds($clientName, $clientNames);
	foreach($clientNames as $k => $v)
		$where[] = "$k = '".mysqli_real_escape_string($v)."'";
	if(!$where) {
		echo "<p><font color=red>Client not found! [$trimVal]</font><p>";
		return;
	}
	$where  = join(' AND ', $where);
	$clientptrs = fetchCol0("SELECT clientid FROM tblclient WHERE $where");
	if(count($clientptrs) > 1) $error = "Multiple clients found for [$trimVal]";
	else if(count($clientptrs) == 0) $error = "No client found for [$trimVal] ".print_r($clientNames, 1);
	else {
		$clientptr = $clientptrs[0];
		$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientptr LIMIT 1", 1);
		$keys = getClientKeys($clientptr);
		if(count($keys) > 1) $error = "Multiple keys found for [$clientName]";
	}
	if($error) {
		echo "<p><font color=red>$error</font><p>";
		return;
	}
	$key = $keys[0];
	if(!$key) {
		$keyId = insertTable('tblkey', array('clientptr'=>$clientptr, 'copies'=>0, ), 1);
		logKeyChange(mysqli_insert_id(), $key, $clientptr, true);
		$keys = getClientKeys($clientptr);
		$key = $keys[0];
	}
	$keyId = $key['keyid'];
	if($row[WITH] == 'Office') $possessor = 'safe1';
	else if($row[WITH] == 'Client') $possessor = 'client';
	else $possessor = findSitterByName($row[WITH]);
	$key['copies'] +=1;
	$N = $key['copies'];
	updateTable('tblkey', array("copies"=> $key['copies'], "possessor$N"=>$possessor), "keyid = $keyId", 1);
	echo "Key for {$row[CLIENTINDEX]} held by [$possessor] added to system.<br>";
	logKeyChange($keyId, $key, $clientptr, true);
}

function FIXhandleRunMyDog($row) {
	static $rowCount, $secondaryHeaders, $referralCats;
	global $dataHeaders, $file;
	$rowCount++;
	if($rowCount == 1) { 
		// 	Concat first row after headers onto $dataHeaders keys
		$secondaryHeaders = $row;
		foreach(explode(',', 'Client Referral,Facebook,Google,Print Ad,Yahoo,Yelp,Other') as $i => $cat) 
			$referralCats[$cat] = $i+18;
		return;
	}
	
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
//echo "<hr>$label: ".print_r($trimVal, 1)." [{$referralCats[$trimVal]}]";
		$clientNames =  array();
		if($label == 'Full Name') {
	
			handleFnameSpaceLnameWithAnds($trimVal, $clientNames);
			foreach($clientNames as $k => $v)
				$where[] = "$k = '".mysqli_real_escape_string($v)."'";
			if(!$where) {
				echo "Client not found! [$trimVal]<p>";
				return;
			}
			$where  = join(' AND ', $where);
			$clientptrs = fetchCol0("SELECT clientid FROM tblclient WHERE $where");
			if(count($clientptrs) > 1) $error = "Multiple clients found for [$trimVal]";
			else if(count($clientptrs) == 0) $error = "No client found for [$trimVal] ".print_r($clientNames, 1);
			else {
				$clientptr = $clientptrs[0];
				$client = fetchFirstAssoc("SELECT clientid, notes FROM tblclient WHERE clientid = $clientptr LIMIT 1", 1);
						//fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientptr LIMIT 1", 1);
			}
			if($error) {
				echo "$error<p>";
				return;
			}
		}
		else if($label == 'Email') {
			if($secondaryHeaders[$i] == 'Primary Contact') processEmails($trimVal, $client); //$client['email'] = $trimVal;
			else /*'Emergency Contact'*/ $emergency['note'] = $trimVal;
		}
		else if($label == 'CC Email') processEmails($trimVal, $client); //$client['email2'] = $trimVal;
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	
	if($notes) {
		$notes = join("\n", $notes);
		if($client['notes']) $client['notes'] .= "\n$notes";	
		else $client['notes'] .= $notes;	
	}
	if($client['email'] || $client['notes']) {
		//updateTable('tblclient', $client, "clientid = $clientptr", 1 );
		//echo "Updated client ".print_r($client, 1)."<br>";
	}
	if($emergency['note']) {
		$contact = fetchFirstAssoc("SELECT contactid, note FROM tblcontact WHERE clientptr = $clientptr", 1);
		$contactid = $contact['contactid'];
		// shit!
		if(($POS = strpos((string)$contact['note'], 'Array')) !== FALSE)
			$contact['note'] = substr($contact['note'], 0, $POS);
		// shit! shit!
		if(($POS = strrpos((string)$contact['note'], $emergency['note'])) !== FALSE)
			$contact['note'] = substr($contact['note'], 0, $POS);
		//if($contact['note']) $contact['note'] .= "\n{$emergency['note']}";	
		//else $contact['note'] .= $emergency['note'];
		updateTable('tblcontact', $contact, "contactid = $contactid", 1 );
		echo "...Updated contact ".print_r($contact, 1)."<br>";
	}
}

function handleRunMyDog($row) {
	/*
	Concat first row after headers onto $dataHeaders keys
	*/
	static $rowCount, $secondaryHeaders, $referralCats;
	global $dataHeaders, $file;
	$rowCount++;
	if($rowCount == 1) { 
		// 	Concat first row after headers onto $dataHeaders keys
		$secondaryHeaders = $row;
		foreach(explode(',', 'Client Referral,Facebook,Google,Print Ad,Yahoo,Yelp,Other') as $i => $cat) 
			$referralCats[$cat] = $i+18;
		return;
	}
	
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
//echo "<hr>$label: ".print_r($trimVal, 1)." [{$referralCats[$trimVal]}]";
		
		if($label == 'Full Name') handleFnameSpaceLnameWithAnds($trimVal, $client);
		else if($label == 'Email') {
			if($secondaryHeaders[$i] == 'Primary Contact') processEmails($trimVal, $client); //$client['email'] = $trimVal;
			else /*'Emergency Contact'*/ $emergency['note'][] = $trimVal;
		}
		else if($label == 'CC Email') processEmails($trimVal, $client); //$client['email2'] = $trimVal;
		else if($label == 'Cell Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Daytime Phone') $client['workphone'] = $trimVal;
		else if($label == 'How did you find us?') $client['referralcode'] = $referralCats[$trimVal];
		else if($label == 'Access Instructions') $client['directions'] = convertFromHTML($trimVal);
		else if($label == 'Address 1') $client['street1'] = $trimVal;
		else if($label == 'Address 2') $client['street2'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip Code') $client['zip'] = $trimVal;
		else if($label == 'Name') $emergency['name'] = $trimVal;
		else if($label == 'Relationship') $emergency['note'][] = $trimVal;
		else if($label == 'Primary Phone') $emergency['note'][] = 'Primary Phone: '.$trimVal;
		else if($label == 'Secondary Phone') $emergency['note'][] = 'Secondary Phone: '.$trimVal;
		else if($label == 'Preferred Sitter') {
			if($primarySitter = findSitterByName($trimVal))
				$client['defaultproviderptr'] = $primarySitter;
			else
				$notes[] = 'Preferred Sitter: '.$trimVal;
		}
		else if($label == 'Secondary Sitter') $notes[] = 'Secondary Sitter: '.$trimVal;
		else if($label == 'Tertiary Sitter') $notes[] = 'Tertiary Sitter: '.$trimVal;
		else if($label == 'Private Note') $client['officenotes'] = convertFromHTML($trimVal);
		// ignore Discount	Credit	Default Tax Rate	
		else if($label == 'Client Added Date') $client['setupdate'] = date('Y-m-d', strtotime($trimVal));
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	
//if($client['lname'] == 'Mistick') echo "<hr>CLIENT: ".print_r($row, 1);

	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		updateTable('tblclient', array('setupdate' => date('Y-m-d', strtotime($client['setupdate']))), "clientid = $clientptr", 1);
		global $added;
		$added += 1;
	}
	if($emergency) {
		if($emergency['note']) $emergency['note'] = join("\n", $emergency['note']);
		saveClientContact('emergency', $clientptr, $emergency);
	}
	//if($custom) saveClientCustomFields($clientptr, $custom);

	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}


function handlePawsomePets($row) {
	/*
	Concat first row after headers onto $dataHeaders keys
	*/
	static $rowCount, $secondaryHeaders, $referralCats;
	global $dataHeaders, $file;
	$rowCount++;
	if($rowCount == 1) { 
		// 	Concat first row after headers onto $dataHeaders keys
		$secondaryHeaders = $row;
		foreach(explode(',', 'Client Referral,Facebook,Google,Print Ad,Yahoo,Yelp,Other') as $i => $cat) 
			$referralCats[$cat] = $i+18;
		return;
	}
	
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
//echo "<hr>$label: ".print_r($trimVal, 1)." [{$referralCats[$trimVal]}]";
		if(!$trimVal) continue;
		if($label == 'Full Name') handleLnameCommaFnameWithAnds($trimVal, $client);
		else if($label == 'Email') {
			if($secondaryHeaders[$i] == 'Primary Contact') processEmails($trimVal, $client); //$client['email'] = $trimVal;
			else /*'Emergency Contact'*/ $emergency['note'][] = $trimVal;
		}
		//else if($label == 'CC Email') processEmails($trimVal, $client); //$client['email2'] = $trimVal;
		else if($label == 'Home Phone') {
			if($secondaryHeaders[$i] == 'Primary Contact') $client['homephone'] = $trimVal;
			else /*'Emergency Contact'*/ $emergency['homephone']= $trimVal;

		}
		else if($label == 'Cell Phone') {
			if($secondaryHeaders[$i] == 'Primary Contact') $client['cellphone'] = $trimVal;
			else /*'Emergency Contact'*/ $emergency['cellphone'] = $trimVal;

		}
		else if($label == 'Work Phone') {
			if($secondaryHeaders[$i] == 'Primary Contact') $client['workphone'] = $trimVal;
			else /*'Emergency Contact'*/ $emergency['workphone'] = $trimVal;

		}
		else if($label == 'Alternate Contact')
			handleFnameSpaceLname($trimVal, $client, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname2');
		else if($label == 'Alternate Contact Email')
			processEmails($trimVal, $client); //$client['email2'] = $trimVal;
		else if($label == 'How did you find us?') $notes[] = "How you found us: $trimVal";
		else if($label == 'Access Instructions') $directions[] = convertFromHTML($trimVal);
		else if($label == 'Address 1') $client['street1'] = $trimVal;
		else if($label == 'Address 2') $client['street2'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip Code') $client['zip'] = $trimVal;
		else if($label == 'Mailing Address 1') $client['mailstreet1'] = $trimVal;
		else if($label == 'Mailing Address 2') $client['mailstreet2'] = $trimVal;
		else if($label == 'Mailing City') $client['mailcity'] = $trimVal;
		else if($label == 'Mailing State') $client['mailstate'] = $trimVal;
		else if($label == 'Mailing Zip Code') $client['mailzip'] = $trimVal;
		
		else if($label == 'Name') $emergency['name'] = $trimVal;
		else if($label == 'Relationship') $emergency['note'][] = $trimVal;
		else if($label == 'Location') $emergency['location'] = "Location: $trimVal";
		else if($label == 'Note') $emergency['note'][] = $trimVal;
		else if($label == 'Has Key?') $emergency['haskey'][] = $trimVal == 'Yes';
		
		else if($label == 'Neighbor Name') $neighbor['name'] = $trimVal;
		else if($label == 'Neighbor Location') $neighbor['note'][] = "Location: $trimVal";
		else if($label == 'Neighbor Home Phone') $neighbor['homephone'] = $trimVal;
		else if($label == 'Neighbor Cell Phone')$neighbor['cellphone'] = $trimVal;
		else if($label == 'Neighbor Work Phone') $neighbor['workphone'] = $trimVal;
		else if($label == 'Neighbor Note') $neighbor['note'][] = $trimVal;
		else if($label == 'Neighbor Has Key?') $neighbor['haskey'][] = $trimVal == 'Yes';
		
		else if($label == 'Preferred Sitter') {
			if($primarySitter = findSitterByName($trimVal))
				$client['defaultproviderptr'] = $primarySitter;
			else
				$notes[] = 'Preferred Sitter: '.$trimVal;
		}
		else if($label == 'Secondary Sitter') $notes[] = 'Secondary Sitter: '.$trimVal;
		else if($label == 'Tertiary Sitter') $notes[] = 'Tertiary Sitter: '.$trimVal;
		else if($label == 'Private Note') $client['officenotes'] = convertFromHTML($trimVal);
		// ignore Discount	Credit	Default Tax Rate	
		else if($label == 'Client Added Date') $client['setupdate'] = date('Y-m-d', strtotime($trimVal));
		else if($label == 'Key ID') $keydescr = $trimVal;
		else if($label == "Veterinarian Name") $clinic['clinicname'] = $trimVal;
		else if($label == 'Veterinarian Phone') $clinic['officephone'] = $trimVal;
		else if($label == 'Client Notes') {
			if($notes) {
				$notes = array_reverse($notes);
				$notes[]  = convertFromHTML($trimVal);
				$notes = array_reverse($notes);
			}
			else $notes[]  = $trimVal;
		}
		else if($label == 'Directions') $directions[] = convertFromHTML($trimVal);
		else if($label == 'Alarm Company') $client['alarmcompany'] = $trimVal;
		else if($label == 'Alarm Information') $client['alarminfo'] = "Alarm code: $trimVal";
			else if($label == 'Set-Up Date') $client['setupdate'] = date('Y-m-d', strtotime($trimVal));
		
		else if($label == 'Leash Location') $client['leashloc'] = $trimVal;
		else if($label == 'Food Location') $client['foodloc'] = $trimVal;
		else if($label == 'Parking Information') $client['parkinginfo'] = $trimVal;
		else if($label == 'Garage/Gate Code') $client['garagegatecode'] = $trimVal;
		else if($label == 'Emergency Care Permission?') $client['emergencycarepermission'] = $trimVal == 'Yes';
		else if($label == 'No Key Required?') $client['nokeyrequired'] = $trimVal == 'Yes';
		else if($label == 'Service Type') $notes[]  = $trimVal;
	}
	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	
	if($clinic) {
		if(!$clinic['clinicname']) $clinic['clinicname'] = 'Unknown Clinic';
		$clinicptr = findClinicByName($clinic['clinicname']);
		if(!$clinicptr) {
			$clinicptr = insertTable('tblclinic', 
				array('clinicname' => $clinic['clinicname'],
							'officephone' => $clinic['officephone']
							), 1);
		}
		$client['clinicptr'] = $clinicptr;
	}
	
	if($notes) $client['notes'] = join("\n", $notes);	
	if($directions) $client['directions'] = join("\n", $directions);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	
//if($client['lname'] == 'Mistick') echo "<hr>CLIENT: ".print_r($row, 1);

	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		updateTable('tblclient', array('setupdate' => date('Y-m-d', strtotime($client['setupdate']))), "clientid = $clientptr", 1);
		global $added;
		$added += 1;
	}
	if(count($emergency) > 1) { // ignore merely "hasKey:false
		if($emergency['note']) $emergency['note'] = join("\n", $emergency['note']);
		$newId = saveClientContact('emergency', $clientptr, $emergency);
		//$emergency2 = fetchFirstAssoc("SELECT * FROM tblcontact WHERE contactid = $newId");
	}
	if(count($neighbor) > 1) {
		if($neighbor['note']) $neighbor['note'] = join("\n", $neighbor['note']);
		$newId = saveClientContact('neighbor', $clientptr, $neighbor);
		//$neighbor2 = fetchFirstAssoc("SELECT * FROM tblcontact WHERE contactid = $newId");
	}
	if($keydescr) {
		$key['description'] = $keydescr;
		$key['copies'] = 1;  // == 1
		for($i=1;$i<=$key['copies'];$i++) $key["possessor$i"] = 'client';
	}
	
	if($key) {
		$key['clientptr'] = $clientptr;
		$keyId = insertTable('tblkey', $key, 1);
		logKeyChange($keyId, $key, $clientptr, true);
	}
	//if($custom) saveClientCustomFields($clientptr, $custom);

	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	if($emergency2) echo "  emergency: ".json_encode($emergency).'<br>  ==> '.json_encode($emergency2).'<br>';
	if($neighbor2) echo "  neighbor: ".json_encode($neighbor).'<br>  ==> '.json_encode($neighbor2).'<br>';
	if($key) echo "  key: ".json_encode($key).'<br>';
}

function convertFromHTML($str) {
	$str = str_replace('<br />', '', $str);
	return trim($str);
}

function handlePrincesPetCare($row) {
	// firstname	lastname	additionalowner	addr1	addr2	city	state	zip	work phone	home phone	fax	cell	email	howheard	howheardother	owner_notes	username	password
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$badEmails = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'First') $client['fname'] = $trimVal;
		else if($label == 'Surname') $client['lname'] = $trimVal;
		else if($label == 'Title') $custom['custom1'] = $trimVal;
		else if($label == 'Address 1') $client['street1'] = $trimVal;
		else if($label == 'Address 2') $client['street2'] = $trimVal;
		else if($label == 'Address 3') $address3 = $trimVal;
		else if($label == 'Town') $client['city'] = ($address3 ? "$address3, $trimVal" : $trimVal);
		else if($label == 'STATE') $client['state'] = $trimVal;
		else if($label == 'Postcode') $client['zip'] = $trimVal;
		else if($label == 'Email1' || $label == 'Email2') 
			foreach(processEmails($trimVal, $client) as $email) $badEmails[] = $email;
		else if($label == 'telephone') $client['homephone'] = $trimVal;
		else if($label == 'mobile 1') $client['cellphone'] = $trimVal;
		else if($label == 'mobile 2') $client['cellphone2'] = $trimVal;
		else if($label == 'work phone') $client['workphone'] = $trimVal;
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}
	if($custom) saveClientCustomFields($clientptr, $custom);

	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}

function handleDogboys($row) {
	// firstname	lastname	additionalowner	addr1	addr2	city	state	zip	work phone	home phone	fax	cell	email	howheard	howheardother	owner_notes	username	password
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$client['state'] = 'CO';
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'firstname') $client['fname'] = $trimVal;
		else if($label == 'lastname') $client['lname'] = $trimVal;
		else if($label == 'additionalowner') handleFnameSpaceLname($trimVal, $client, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname2');
		else if($label == 'addr1') $client['street1'] = $trimVal;
		else if($label == 'addr2') $client['street2'] = $trimVal;
		else if($label == 'city') $client['city'] = $trimVal;
		else if($label == 'state') $client['state'] = $trimVal;
		else if($label == 'zip') $client['zip'] = $trimVal;
		else if($label == 'work phone') $client['workphone'] = $trimVal;
		else if($label == 'home phone') $client['homephone'] = $trimVal;
		else if($label == 'fax') ; //
		else if($label == 'cell') $client['cellphone'] = $trimVal;
		else if($label == 'email') $badEmails = processEmails($trimVal, $client);
		else if($label == 'howheard' && $trimVal) $howheard = $trimVal;
		else if($label == 'howheardother') {
			if($howheard != 'Unknown' || $trimVal)
				$notes[] = "How Heard: $howheard - $trimVal";
		}
		else if($label == 'owner_notes' && $trimVal)
			$notes[] = "$trimVal";
		else if($label == 'username' && $trimVal)
			$notes[] = "orig username: $trimVal";
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}

	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}

function handlePositivePawsPetServices($row) {
	require_once "pet-fns.php";
//Client number	First Name	Last name	Address	City	State 	Zip	Email address	Name of spouse	Phone number	Phone Number 2	Phone number 3	Emergency Contact	E. C. Phone number	Pet type	Name of pet(s)	Breed	Age	Name of pet(s) 	Breed	Age	Name of pet(s)	Breed	Age	Name of pet(s) 	Breed	Age	Vet's name	Vet number	
	$customFieldDescr = 
"custom1//Vaccinations|1|oneline|1|1
custom2//Allergies/ Medical Issues|1|oneline|1|1
custom3//Spay or Neutered/ Microchipped|1|oneline|1|1
custom4//Medications|1|text|1|1
custom5//Food Brand|1|oneline|1|1
custom6//How Much?|1|oneline|1|1
custom7//How many times a day?|1|oneline|1|1
custom8//Food bowls located|1|oneline|1|1
custom9//Water bowls located|1|oneline|1|1
custom10//Favorite Treats|1|oneline|1|1
custom11//Treats x per day|1|oneline|1|1
custom12//When are Treats Given|1|oneline|1|1
custom13//Toy Box|1|oneline|1|1
custom14//Commands dog knows/Litterbox|1|text|1|1
custom15//Leash Behavior|1|oneline|1|1
custom16//Visits ( Vacation/per week/per day)|1|oneline|1|1
custom17//Favorite place for walk|1|oneline|1|1
custom18//Reaction to strangers/children/absence from home/brushing|1|text|1|1
custom19//Reactions to other dogs|1|oneline|1|1
custom20//Reaction to cats|1|oneline|1|1
custom21//Reaction to other animals|1|oneline|1|1
custom22//Water plants|1|oneline|1|1
custom23//Trash Take out|1|oneline|1|1
custom24//Take in Mail|1|oneline|1|1
custom25//Take in Newspaper|1|oneline|1|1";
	$customFieldDescr = explode("\n", $customFieldDescr);
	$existingCustomFields = getCustomFields();
	foreach($customFieldDescr as $line) {
		$pair = explode("//", trim($line));
		if(!$existingCustomFields[$pair[0]]) setPreference($pair[0], $pair[1]);
	}
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last name') $client['lname'] = $trimVal;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Email address') $badEmails = processEmails($trimVal, $client);
		else if($label == 'Name of spouse') $client['fname2'] = $trimVal;
		else if($label == 'Phone number') $client['homephone'] = $trimVal;
		else if($label == 'Phone number 2') $client['workphone'] = $trimVal;
		else if($label == 'Phone number 3') $client['cellphone'] = $trimVal;
		else if($label == 'Emergency Contact') $emergency['name']=$trimVal;
		else if($label == 'E. C. Phone number') $emergency['homephone']=$trimVal;
		else if($label == "Vet name") $clinic['clinicname']=$trimVal;
		else if($label == 'Vet number') $clinic['officephone']=$trimVal;
		else if($label == 'Where is the leash') $client['leashloc']=$trimVal;
		else if($label == 'notes') $client['notes'] = $trimVal;
		if($trimVal && ($property = fetchRow0Col0("SELECT property FROM tblpreference WHERE value LIKE '$label|%' LIMIT 1"))) {
			if(strpos($property, 'custom') === 0) $custom[$property] = $trimVal;
		}
	}
	if($clinic) {
		if(!$clinic['clinicname']) $clinic['clinicname'] = 'Unknown Clinic';
		$clinicptr = findClinicByName($clinic['clinicname']);
		if(!$clinicptr) {
			$clinicptr = insertTable('tblclinic', 
				array('clinicname' => $clinic['clinicname'],
							'officephone' => $clinic['officephone']
							), 1);
		}
		$client['clinicptr'] = $clinicptr;
	}
	
	if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	if(!$client['lname']) $client['lname'] = 'UNKNOWN';
	
	$clientptr = saveNewClient($client);
	global $added;
	$added += 1;
	
	if($custom) saveClientCustomFields($clientptr, $custom);

	for($i=1;$i<=4; $i++) {
		$pet = array();
		if($trimVal = trim(rowAtHeader($row, "petname$i"))) $pet['name'] = $trimVal;
		if($trimVal = trim(rowAtHeader($row, "Breed$i"))) $pet['breed'] = $trimVal;
		if($trimVal = trim(rowAtHeader($row, "Age$i"))) $pet['notes'][] = "Age: $trimVal";
		if($pet) {
			if(!$pet['name']) $pet['name'] = 'UNNAMED';
			$pet['ownerptr'] = $clientptr;
			$pet['active'] = 1;
			if($pet['notes']) $pet['notes'] = join("\n", $pet['notes']);
			$pet['type'] = guessTypeByBreed($pet['breed']);
			//echo "{$pet['breed']}: {$pet['type']}<br>";
			$pets[] = $pet;
		}
	}
	
	foreach((array)$pets as $pet) insertTable('tblpet', $pet, 1);
	
	echo "Added client {$client['fname']} {$client['lname']} with pets: "
				.getClientPetNames($clientptr, $inactiveAlso=false, $englishList=true)
				."<br>";

}

function handlePamperedPawsPetSittingSvc($row, $extra) {
//First Name	Last Name	Bill to 2	City	Zip	Phone	Alt. Phone	Email
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$client['state'] = 'CO';
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Email') $badEmails = processEmails($trimVal, $client);
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	
	// if client already exists, just add the pets
	$clientptr = fetchRow0Col0(
		"SELECT clientid FROM tblclient 
			WHERE lname = '".mysqli_real_escape_string($client['lname'])."' 
			AND fname = '".mysqli_real_escape_string($client['fname'])."' 
			AND street1 = '".mysqli_real_escape_string($client['street1'])."'", 1);
	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}
	
	
	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}
		


function handleRuffLifeNYPETS($row) {
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	//Owner	Last Name	Full Name	Pet Name	Animal	Breed	Color/Markings	Birthday_YYYYMMDD	Gender	Spayed_Neutered	Vaccinations_Current
	$ownerptr = findClientByName($fullName = rowAtHeader($row, 'Owner').' '.rowAtHeader($row, 'Last Name'));
	if(!$ownerptr) {
		echo "<font color=red>Client [$fullName] in row $rowCount not found.</font><br>";
		return;
	}
	$pet = array('ownerptr'=>$ownerptr, 'active'=>1);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Pet Name') $pet['name'] = $trimVal;
		else if($label == 'Animal') $pet['type'] = $trimVal;
		else if($label == 'Breed') $pet['breed'] = $trimVal;
		else if($label == 'Color/Markings') $pet['color'] = $trimVal;
		else if($label == 'Birthday_YYYYMMDD') ; // not supplied
		else if($label == 'Gender')  {
			$pet['sex'] = 
				strpos($trimVal, 'emale') ? 'f' : (
				strpos($trimVal, 'ale') !== FALSE ? 'm' : '');
		}
		else if($label == 'Spayed_Neutered') $pet['fixed'] = ($trimVal == 'yes' ? 1 : '0');
		else if($label == 'Vaccinations_Current') $pet['notes'] = 'Vaccinations Current';
	}
	insertTable('tblpet', $pet, 1);
	echo "Added {$pet['type']} named {$pet['name']} for client [$fullName].<br>";
}

function handleRuffLifeNY($row, $extra) {
	global $dataHeaders, $file;
	
	if(strpos(strtolower($file), 'pet') !== FALSE) return handleRuffLifeNYPETS($row);

//First Name	Last Name	Bill to 2	City	Zip	Phone	Alt. Phone	Email
	static $rowCount;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'), 'city'=>'Brooklyn', 'state'=>'NY');
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal ? $trimVal : 'UNKNOWN' ;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Cell Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Email') $badEmails = processEmails($trimVal, $client);
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "<font color=red>BAD ROW: $rowCount</font><br>"; /*return;*/}
	
	// if client already exists, just add the pets
	/*$clientptr = fetchRow0Col0(
		"SELECT clientid FROM tblclient 
			WHERE lname = '".mysqli_real_escape_string($client['lname'])."' 
			AND fname = '".mysqli_real_escape_string($client['fname'])."' 
			AND street1 = '".mysqli_real_escape_string($client['street1'])."'", 1);
	*/
	if(true /*!$clientptr*/) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}
	
	
	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}

function handleBurkesDogCareWagsDogWalkers($row, $extra) {
//First Name	Last Name	Address	City	State	Zip	Phone #	Other #	Email 	First Name 2	Last Name 2	Phone # 2	
// Vet 	Vet #	Emergency Contact	Emergency #	Pet Name 1	Breed 1	Color	DOB 1	Pet Name 2	Breed 2	DOB 2	Pet Name 3 	Breed 3	DOB 3
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$client['state'] = 'CO';
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'Apt') $client['street2'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Phone #') $client['homephone'] = $trimVal;
		else if($label == 'Other #') $client['cellphone'] = $trimVal;
		else if($label == 'Email') $badEmails = processEmails($trimVal, $client);
		else if($label == 'First Name 2') $client['fname2'] = $trimVal;
		else if($label == 'Last Name 2') $client['lname2'] = $trimVal;
		else if($label == 'Phone # 2') $client['cellphone'] = $trimVal;
		else if($label == 'Vet') $clinic['clinicname']=$trimVal;
		else if($label == 'Vet #') $clinic['officephone']=$trimVal;
		else if($label == 'Emergency Contact') $emergency['name']=$trimVal;
		else if($label == 'Emergency #') $emergency['homephone']=$trimVal;
		else if($label == 'Emergency Email') $emergency['note']="Email: $trimVal";
		else if($label == 'Neighbor') $neighbor['name']=$trimVal;
		else if($label == 'Neighbor Number') $neighbor['homephone']=$trimVal;
		else if($label == 'Neighbor Email') $neighbor['note']="Email: $trimVal";
	}
	for($i=1;$i<=3;$i++) {
		$pet = null;
		if($trimVal = trim(rowAtHeader($row, "Pet Name $i"))) $pet['name'] = $trimVal;
		if($trimVal = trim(rowAtHeader($row, "Breed $i"))) $pet['breed'] = $trimVal;
		if($i == 1 && ($trimVal = trim(rowAtHeader($row, "Color")))) $pet['color'] = $trimVal;
		if($trimVal = trim(rowAtHeader($row, "DOB $i"))) $pet['dob'] = $trimVal;
		if($pet) {
			if(!$pet['name']) $pet['name'] = 'UNNAMED';
			$pets[] = $pet;
		}
	}
	foreach(array('email') as $emailfield)
		if($client[$emailfield] && !isEmailValid( $client[$emailfield])) { // see field-utils.php
			$notes[] = "Bad email address: ".$client[$emailfield];
			unset($client[$emailfield]);
		}
	if($client['notes']) $client['notes'] = join("\n", $notes);
	if($clinic) {
		if(!$clinic['clinicname']) $clinic['clinicname'] = 'Unknown Clinic';
		$clinicptr = findClinicByName($clinic['clinicname']);
		if(!$clinicptr) {
			$clinicptr = insertTable('tblclinic', 
				array('clinicname' => $clinic['clinicname'],
							'officephone' => $clinic['officephone']
							), 1);
		}
		$client['clinicptr'] = $clinicptr;
	}
//print_r($pets);exit;
	if(!$client['lname']) $client['lname'] = 'UNKNOWN';
	$clientptr = saveNewClient($client);
	if($emergency) saveClientContact('emergency', $clientptr, $emergency);
	foreach((array)$pets as $pet) {
		$pet['ownerptr'] = $clientptr;
		if($pet['notes']) $pet['notes'] = join("\n", $pet['notes']);
		$pet['type'] = 'Dog';
		$petid = insertTable('tblpet', $pet, 1);
		$petNames[] = $pet['name'];
	}
	
	echo "Added client {$client['fname']} {$client['lname']}";
	if($petNames) echo " with pets: ".join(', ', $petNames);
	echo "<br>";
}

function handleSiriusPetCare($row, $extra) {
//First Name	Last Name	Bill to 2	City	Zip	Phone	Alt. Phone	Email

	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$client['state'] = 'CO';
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Bill to 2') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Phone') $client['homephone'] = $trimVal;
		else if($label == 'Alt. Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Email') $badEmails = processEmails($trimVal, $client);
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	
	// if client already exists, just add the pets
	$clientptr = fetchRow0Col0(
		"SELECT clientid FROM tblclient 
			WHERE lname = '".mysqli_real_escape_string($client['lname'])."' 
			AND fname = '".mysqli_real_escape_string($client['fname'])."' 
			AND street1 = '".mysqli_real_escape_string($client['street1'])."'", 1);
	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}
	
	
	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}
		


function handleK9AdventureFitness($row, $extra) {
//Dog Name	Owner Name	E-mail	Phone Number	Billing Address	City	State	Service
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Owner Name')  
			handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey='fname');		
		else if($label == 'E-mail') $badEmails = processEmails($trimVal, $client);
		else if($label == 'Phone Number') $client['homephone'] = $trimVal;
		else if($label == 'Billing Address') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') {
			$state = explode(' ', $trimVal);
			$client['state'] = $state[0];
			if($state[1]) $client['zip'] = $state[1];
		}
		else if($label == 'Dog Name') $petNames = handlePetNames($trimVal);
		else if($label == 'Service') $notes[] = "Service: $trimVal";;
	}

	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	
	// if client already exists, just add the pets
	$clientptr = fetchRow0Col0(
		"SELECT clientid FROM tblclient 
			WHERE lname = '".mysqli_real_escape_string($client['lname'])."' 
			AND fname = '".mysqli_real_escape_string($client['fname'])."' 
			AND street1 = '".mysqli_real_escape_string($client['street1'])."'", 1);
	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}
	
	if($petNames) {
		foreach($petNames as $i => $nm) {
			if(!$nm) {
				unset($petNames[$i]);
				continue;
			}
			$petCount++;
			$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1, 'type'=>'Dog');
			insertTable('tblpet', $pet, 1);
		}
	}
	
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(', ', $petNames)."]";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}
		



function handleSarahSits($row, $extra) {
	// Name,email,phone number
	require_once "preference-fns.php";
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Name')  
			handleLnameCommaFname($trimVal, $client, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey='fname');		
		else if($label == 'email') $badEmails = processEmails($trimVal, $client);
		if($label == 'phone number') $client['homephone'] = $trimVal;
	}
	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
	echo " -- ".($notes ? $notes : $client['email']);
	echo "<p>";
}
		
function handleCanineAdventure($row, $extra) {
	require_once "preference-fns.php";
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Notes') $notes[] = $trimVal;
		else if($label == 'Email') $badEmails = processEmails($trimVal, $client);
		else if($label == 'Phone') $client['homephone'] = $trimVal;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'Apt') $client['street2'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Pet Name') $petNames = handlePetNames($trimVal);
/*		else if($label == 'Home City') $client['city'] = $trimVal;
		else if($label == 'Home State') $client['state'] = $trimVal;
		else if($label == 'Home Postal Code') $client['zip'] = $trimVal;
*/	}
	foreach((array)$badEmails as $email) {
		$notes[] = "Invalid email address: $email";
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	if(!$client['lname']) {echo "BAD ROW: $rowCount<br>"; return;}
	
	// if client already exists, just add the pets
	$clientptr = fetchRow0Col0(
		"SELECT clientid FROM tblclient 
			WHERE lname = '".mysqli_real_escape_string($client['lname'])."' 
			AND fname = '".mysqli_real_escape_string($client['fname'])."' 
			AND street1 = '".mysqli_real_escape_string($client['street1'])."'", 1);
	
	if(!$clientptr) {
		$clientptr = saveNewClient($client);
		global $added;
		$added += 1;
	}
	
	if($petNames) {
		foreach($petNames as $i => $nm) {
			if(!$nm) {
				unset($petNames[$i]);
				continue;
			}
			$petCount++;
			$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
			insertTable('tblpet', $pet, 1);
		}
	}
	
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(', ', $petNames)."]";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	
}

function handlegoodnessgraciousextraordinary($row, $extra) { 
	require_once "preference-fns.php";
	static $rowCount;
	global $dataHeaders, $file;
	$rowCount++;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	$petNum = -1;
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'lnamefname')  
			handleLnameCommaFname($trimVal, $client, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey='fname');		
		if($label == 'Alt Name')  
			handleLnameCommaFname($trimVal, $client, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname2');		
		if($label == 'cellphone') $client['cellphone'] = $trimVal;
		if($label == 'cellphone2') $client['cellphone2'] = $trimVal;
		if($label == 'Alt Home phone') $notes[] = "Alt Home phone: $trimVal";
		if($label == 'Alt Work phone') $notes[] = "Alt Work phone: $trimVal";
		if($label == 'homephone') $client['homephone'] = $trimVal;
		if($label == 'workphone') $client['workphone'] = $trimVal;
		if($label == 'email') $client['email'] = $trimVal;
		if($label == 'email2') $client['email2'] = $trimVal;
		if($label == 'address') {
			$parts = explode(',', $trimVal);
			$client['street1'] = trim($parts[0]);
			$parts = array_reverse($parts);
			array_pop($parts);
			if($parts) {
				$parts = join(',', array_reverse($parts));
				getCityStateZip($parts, $client);
			}
		}
		if($label == 'Google Maps') $notes[] = "Google Maps: $trimVal";
		if($label == 'directions') $client['directions'] = $trimVal;
		if($label == 'Contact method') $notes[] = "Contact method: $trimVal";
		if($label == 'pet') {
			$petNum += 1;
			$pets[$petNum]['name'] = $trimVal;
		}
		if($label == 'type') $pets[$petNum]['type'] = $trimVal;
		if($label == 'AM Feeding') $pets[$petNum]['notes'][] = "AM Feeding: $trimVal";
		if($label == 'PM Feeding') $pets[$petNum]['notes'][] = "PM Feeding: $trimVal";
		if($label == 'Medication Instructions') $pets[$petNum]['notes'][] = "Medication Instructions: $trimVal";
		if($label == 'Other instructions') $pets[$petNum]['notes'][] = "Other Instructions: $trimVal";
		if($label == 'Pets on furniture') $notes[] = "Pets on furniture: $trimVal";
		if($label == 'Which furniture') $notes[] = "Which furniture: $trimVal";
		if($label == 'Veterinarian') $clinic['clinicname']=$trimVal;
		if($label == 'Vet Number') $clinic['officephone']=$trimVal;
		if($label == 'Emergency') $emergency['name']=$trimVal;
		if($label == 'Emergency Number') $emergency['homephone']=$trimVal;
		if($label == 'Emergency Email') $emergency['note']="Email: $trimVal";
		if($label == 'Neighbor') $neighbor['name']=$trimVal;
		if($label == 'Neighbor Number') $neighbor['homephone']=$trimVal;
		if($label == 'Neighbor Email') $neighbor['note']="Email: $trimVal";
		if($label == 'Where will you leave the key/What is the entry code ?') $client['alarminfo']=$trimVal;
		if($label == 'Allergies') $notes[] = "Allergies: $trimVal";
		if($label == 'Internet') $notes[] = "Internet: $trimVal";
		if($label == 'Internet instructions') $notes[] = "Internet instructions: $trimVal";
		if($label == 'Special instructions') $notes[] = "Special instructions: $trimVal";
		if($label == 'Pick up mail?') $notes[] = "Pick up mail? $trimVal";
		if($label == 'Cleaning Materials') $notes[] = "Cleaning Materials: $trimVal";
		if($label == 'Paper products') $notes[] = "Paper products: $trimVal";
		if($label == 'Expected visitors') $notes[] = "Expected visitors: $trimVal";
	}	
	foreach(array('email', 'email2') as $emailfield)
		if($client[$emailfield] && !isEmailValid( $client[$emailfield])) { // see field-utils.php
			$notes[] = "Bad email address: ".$client[$emailfield];
			unset($client[$emailfield]);
		}
	$client['notes'] = join("\n", $notes);
	if($clinic) {
		$clinicptr = findClinicByName($clinic['clinicname']);
		if(!$clinicptr) {
			$clinicptr = insertTable('tblclinic', 
				array('clinicname' => $clinic['clinicname'],
							'officephone' => $clinic['officephone']
							), 1);
		}
		$client['clinicptr'] = $clinicptr;
	}
//print_r($pets);exit;
	$clientptr = saveNewClient($client);
	if($emergency) saveClientContact('emergency', $clientptr, $emergency);
	if($neighbor) saveClientContact('neighbor', $clientptr, $neighbor);
	foreach((array)$pets as $pet) {
		$pet['ownerptr'] = $clientptr;
		if($pet['notes']) $pet['notes'] = join("\n", $pet['notes']);
		$petid = insertTable('tblpet', $pet, 1);
	}
	
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
	echo "<p>";

}

function handlePetSitClickPETS($row, $extra) { // PETS PetSitClick Pet Sit Click
//Customer_ID_View	Customer_Name	Pet_Name	Type	Breed	Color	Birthday	Gender	Spayed_Neutered	
//Vaccinations_Current	Vaccinations	Aggressive	Aggressive_Exp	Medications	Food_Instructions	
//   Litterbox	Leash_Location	Carrier_Location	
//  General_Notes	cust_pet_1	cust_pet_2	cust_pet_3	cust_pet_4	cust_pet_5	cust_pet_6	cust_pet_7	cust_pet_8	cust_pet_9	cust_pet_10																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																			
//https://leashtime.com/import-clients-csv-adhoc.php?file=happytailsofsj/Pet-Data.csv
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$clientName = findClientByName(rowAtHeader($row, 'Customer_Name'));
	$externalclientid = rowAtHeader($row, 'Customer_ID_View');
	$ownerptr = fetchRow0Col0("SELECT clientptr FROM tblclientpref WHERE property = 'externalclientid' AND value = '$externalclientid' LIMIT 1");
	if(!$ownerptr) {
		echo "No client with ID [$externalclientid] named [$clientName] found.";
		return;
	}

	$pet =  array('active'=>1, 'ownerptr'=>$ownerptr);
	$client = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($trimVal == '') continue;
		if($label == "Pet_Name") $pet['name'] = $trimVal;
		else if($label == "Type") $pet['type'] = $trimVal;
		else if($label == "Breed") $pet['breed'] = $trimVal;
		else if($label == "Color") $pet['color'] = $trimVal;
		else if($label == "Birthday") $pet['dob'] = $trimVal;
		else if($label == "Gender") {
			$pet['sex'] = 
				strpos($trimVal, 'emale') ? 'f' : (
				strpos($trimVal, 'ale') !== FALSE ? 'm' : '');
		}
		else if($label == "Spayed_Neutered") $pet['fixed'] = $trimVal == 'Yes' ? '1' : '0';
		else if($label == "Birthday") $pet['dob'] = $trimVal;
		else if($label == "General_Notes") $notes = $trimVal;
		
		else if($property = fetchRow0Col0("SELECT property FROM tblpreference WHERE value LIKE '$label|%' LIMIT 1")) {
			if(in_array($label, array('Vaccinations_Current', 'Aggressive'))) $trimVal = $trimVal == 'Yes' ? '1' : '0';
			if(strpos($property, 'petcustom') === 0) $petcustom[$property] = $trimVal;
			else if(strpos($property, 'custom') === 0) $custom[$property] = $trimVal;
		}
	}
	$petid = insertTable('tblpet', $pet, 1);
	$extras =$extras ? explode(',', $extras) : array();
	if(in_array('custpet', $extras) && $petcustom) savePetCustomFields($petid, $petcustom, $number= '');
	if(in_array('cust', $extras) && $custom) saveClientCustomFields($ownerptr, $custom);
	echo "Created  ".print_r($pet, 1)."<p>Pet custom: ".print_r($petcustom, 1)."<p>Client custom: ".print_r($custom, 1)."<hr>";
}

function handlePetSitClick($row, $extra=null, $nameHandler='handleFnameSpaceLname') { // PetSitClick Pet Sit Click "Customer Data"
//
// PREP: create as many cust_1..cust_15 fields as necessary AND pass in $extra= 'cust' or 'custpet' or 'cust,custpet'
//
//Customer_ID_View	Company_ID	Active	Customer_Type	Customer_Name	
// Address_line_1	Address_line_2	City	State_or_Province	Country	Postal_ZIP	Home_Phone	
// Work_Phone	Fax	Email_Address	Mobile_Phone	Notes	Contact_Name	Client_Since	
// Bill_Same	Billing_address_line_1	Billing_address_line_2	Bill_City	Bill_State_or_Province	Bill_Country	Bill_Postal_ZIP	
// Title	Work_address_same	Terms	Payment_type	PO_Number	Invoice_notes	Acquired_Customer	Employee_ID	Invoice_delivery	
// Created_by	Created_on	Last_Updated_by	
// cust_1	cust_2	cust_3	cust_4	cust_5	cust_6	cust_7	cust_8	cust_9	cust_10	cust_11	cust_12	cust_13	cust_14	cust_15																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																											
//https://leashtime.com/import-clients-csv-adhoc.php?file=happytailsofsj/Client-Data.csv
	require_once "preference-fns.php";
	static $rowCount;
	global $dataHeaders, $file;
	if(in_array('Pet_Name', $dataHeaders)) return handlePetSitClickPETS($row, $extra);
	$rowCount++;
	$setupdate = rowAtHeader($row, 'Client_Since') ? date('Y-m-d', strtotime(rowAtHeader($row, 'Client_Since'))) : date('Y-m-d');
	$client =  array('active'=>(rowAtHeader($row, 'Active') == 'Active' ? 1 : 0), 'setupdate'=>$setupdate);
	$nameHandler(rowAtHeader($row, 'Customer_Name'), $client, $fnameKey='fname', $lnameKey='lname');
	$client['street1'] = rowAtHeader($row, 'Address_line_1');
	$client['street2'] = rowAtHeader($row, 'Address_line_2');
	$client['city'] = rowAtHeader($row, 'City');
	$client['state'] = rowAtHeader($row, 'State_or_Province');
	$client['zip'] = rowAtHeader($row, 'Postal_ZIP');
	
	if($email = rowAtHeader($row, 'Email_Address'))	{
		$end = strpos($email, ' ') !== FALSE ? strpos($email, ' ') : strlen($email);
		$client['email'] = substr($email, 0, $end);
		if($space) $notes[] = "Email: $email";
	}

	$client['homephone'] = rowAtHeader($row, 'Home_Phone');
	$client['workphone'] = rowAtHeader($row, 'Work_Phone');
	$client['cellphone'] = rowAtHeader($row, 'Mobile_Phone');
	
	$client['mailstreet1'] = rowAtHeader($row, 'Billing_address_line_1');
	$client['mailstreet2'] = rowAtHeader($row, 'Billing_address_line_2');
	$client['mailcity'] = rowAtHeader($row, 'Bill_City');
	$client['mailstate'] = rowAtHeader($row, 'Bill_State_or_Province');
	$client['mailzip'] = rowAtHeader($row, 'Bill_Postal_ZIP');
	
	if($v = rowAtHeader($row, 'Notes')) $notes[] = $v;
	$noteFields = 'Terms,Payment_type,Invoice_delivery';
	if(!$extra)
		for($i=1;$i<=15;$i++) $noteFields .= ",cust_$i";
	foreach(explode(',', $noteFields) as $k)
		if($v = rowAtHeader($row, $k)) $notes[] = "$k: $v";
	if($notes) $client['notes'] = join("\n", $notes);
	
	if(findClientByName(rowAtHeader($row, 'Customer_Name'))) {
		echo "<font color=red>Skipping: {$client['fname']} {$client['lname']}</font><p>";
		return;
	}
	
	$clientptr = saveNewClient($client);
	setClientPreference($clientptr, 'externalclientid', rowAtHeader($row, 'Customer_ID_View'));
	if($extra == 'cust')
		for($i=1;$i<=15;$i++) 
			if($v = rowAtHeader($row, $k)) $custom[$k] = $v;
	if($custom) saveClientCustomFields($clientptr, $custom);
	
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
	echo "<p>";

}






function handleHappyTailsofSJPETS($row) { // PETS PetSitClick Pet Sit Click
//Customer_ID_View	Customer_Name	Pet_Name	Type	Breed	Color	Birthday	Gender	Spayed_Neutered	
//Vaccinations_Current	Vaccinations	Aggressive	Aggressive_Exp	Medications	Food_Instructions	
//   Litterbox	Leash_Location	Carrier_Location	
//  General_Notes	cust_pet_1	cust_pet_2	cust_pet_3	cust_pet_4	cust_pet_5	cust_pet_6	cust_pet_7	cust_pet_8	cust_pet_9	cust_pet_10																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																			
//https://leashtime.com/import-clients-csv-adhoc.php?file=happytailsofsj/Pet-Data.csv
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$ownerptr = ($clientName = findClientByName(rowAtHeader($row, 'Customer_Name')));
	if(!$ownerptr) {
		echo "No client named [$clientName] found.";
		return;
	}
	// petcustomfields: 
	$pet =  array('active'=>1, 'ownerptr'=>$ownerptr);
	$client = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($trimVal == '') continue;
		if($label == "Pet_Name") $pet['name'] = $trimVal;
		else if($label == "Type") $pet['type'] = $trimVal;
		else if($label == "Breed") $pet['breed'] = $trimVal;
		else if($label == "Color") $pet['color'] = $trimVal;
		else if($label == "Birthday") $pet['dob'] = $trimVal;
		else if($label == "Gender") {
			$pet['sex'] = 
				strpos($trimVal, 'emale') ? 'f' : (
				strpos($trimVal, 'ale') !== FALSE ? 'm' : '');
		}
		else if($label == "Spayed_Neutered") $pet['fixed'] = $trimVal == 'Yes' ? '1' : '0';
		else if($label == "Birthday") $pet['dob'] = $trimVal;
		else if($label == "General_Notes") $notes = $trimVal;
		
		else if($property = fetchRow0Col0("SELECT property FROM tblpreference WHERE value LIKE '$label|%' LIMIT 1")) {
			if(in_array($label, array('Vaccinations_Current', 'Aggressive'))) $trimVal = $trimVal == 'Yes' ? '1' : '0';
			if(strpos($property, 'petcustom') === 0) $petcustom[$property] = $trimVal;
			else if(strpos($property, 'custom') === 0) $custom[$property] = $trimVal;
		}
	}
	$petid = insertTable('tblpet', $pet, 1);
	if($petcustom) savePetCustomFields($petid, $petcustom, $number= '');
	if($custom) saveClientCustomFields($ownerptr, $custom);
	echo "Created  ".print_r($pet, 1)."<p>Pet custom: ".print_r($petcustom, 1)."<p>Client custom: ".print_r($custom, 1)."<hr>";
}


function handleHappyTailsofSJ($row, $ignore=null) { // PetSitClick Pet Sit Click
//Customer_Name Address_line_1	Address_line_2	City	State_or_Province	Country	Postal_ZIP	Home_Phone	Work_Phone
// Email_Address	Mobile_Phone	Notes	Contact_Name (pets -- ignore) Client_Since
// Billing_address_line_1	Billing_address_line_2	Bill_City	Bill_State_or_Province	Bill_Country	Bill_Postal_ZIP
// Terms	Payment_type Acquired_Customer Invoice_delivery Invoice_delivery
//https://leashtime.com/import-clients-csv-adhoc.php?file=happytailsofsj/Client-Data.csv
	static $rowCount;
	global $dataHeaders, $file;
	if(strpos($file, 'Pet')) return handleHappyTailsofSJPETS($row);
	$rowCount++;
	$setupdate = rowAtHeader($row, 'Client_Since') ? date('Y-m-d', strtotime(rowAtHeader($row, 'Client_Since'))) : date('Y-m-d');
	$client =  array('active'=>1, 'setupdate'=>$setupdate);
	handleFnameSpaceLname(rowAtHeader($row, 'Customer_Name'), $client, $fnameKey='fname', $lnameKey='lname');
	$client['street1'] = rowAtHeader($row, 'Address_line_1');
	$client['street2'] = rowAtHeader($row, 'Address_line_2');
	$client['city'] = rowAtHeader($row, 'City');
	$client['state'] = rowAtHeader($row, 'State_or_Province');
	$client['zip'] = rowAtHeader($row, 'Postal_ZIP');
	
	if($email = rowAtHeader($row, 'Email_Address'))	{
		$end = strpos($email, ' ') !== FALSE ? strpos($email, ' ') : strlen($email);
		$client['email'] = substr($email, 0, $end);
		if($space) $notes[] = "Email: $email";
	}

	$client['homephone'] = rowAtHeader($row, 'Home_Phone');
	$client['workphone'] = rowAtHeader($row, 'Work_Phone');
	$client['cellphone'] = rowAtHeader($row, 'Mobile_Phone');
	
	$client['mailstreet1'] = rowAtHeader($row, 'Billing_address_line_1');
	$client['mailstreet2'] = rowAtHeader($row, 'Billing_address_line_2');
	$client['mailcity'] = rowAtHeader($row, 'Bill_City');
	$client['mailstate'] = rowAtHeader($row, 'Bill_State_or_Province');
	$client['mailzip'] = rowAtHeader($row, 'Bill_Postal_ZIP');
	
	if($v = rowAtHeader($row, 'Notes')) $notes[] = $v;
	$noteFields = 'Terms,Payment_type,Invoice_delivery';
	for($i=1;$i<=12;$i++) $noteFields .= ",cust_$i";
	foreach(explode(',', $noteFields) as $k)
		if($v = rowAtHeader($row, $k)) $notes[] = "$k: $v";
	
	if($notes) $client['notes'] = join("\n", $notes);

	
	if(findClientByName(rowAtHeader($row, 'Customer_Name'))) {
		echo "<font color=red>Skipping: {$client['fname']} {$client['lname']}</font><p>";
		return;
	}
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
	echo "<p>";

}

function handleThePetGurl($row, $ignore=null) { // very poorly formatted data 
// Customer,	Name,	Alt Name,	Type of Service,	Address,	phone,	phone2,	Amount for Service,	Pet names,	Vet,	Comments																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																					
// I cleaned it up amd added columns Name,Alt Name, phone2
// do not touch Pet names,	Vet,	Comments
//https://leashtime.com/import-clients-csv-adhoc.php?file=thepetgurl/ThePetGurLClientList.csv
	static $rowCount;
	$rowCount++;
	global $dataHeaders, $file;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Name')  
			handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey='fname');		
		if($label == 'Alt Name')  
			handleFnameSpaceLname($trimVal, $client, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname2');		
		else if($label == 'Type of Service') $notes[] = "$label: $trimVal";
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'phone' || $label == 'phone2') {
			$primaryphone = $label == 'phone' ? '*' : '';
			if(strpos($trimVal, '(w') !== FALSE) $key = 'workphone';
			else if(strpos($trimVal, '(c') !== FALSE) $key = 'cellphone';
			else $key = 'homephone';
			$client[$key] = "$primaryphone$trimVal";
		}
		else if($label == 'Amount for Service') $notes[] = "$label: $trimVal";
	}
	if($notes) $client['notes'] = join("\n", $notes);
	$client['fname'] = $client['fname'] ? $client['fname'] : 'UNKNOWN';
	$client['lname'] = $client['lname'] ? $client['lname'] : 'UNKNOWN';
	
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']} ($clientptr)";
	echo "<p>";
}

function handlePawsitivelyPoochesPetInfo($row, $ignore=null) { // pawsitivelypooches pet info
//Pet's Name	Type of Animal	Markings/Breed	Birthdate	Sex	Feeding Schedule/Amounts	
// Medical Concerns	Medications	Favorite Games	Fears/Behavioral issues	
// Special Instructions	Wireless Network Name	Wireless Network Password	
// How did you hear about us?	Client Who Referred You	
// Would you like us to water any plants?	Your Name	Phone Number
//https://leashtime.com/import-clients-csv-adhoc.php?file=pawsitivelypooches/PP-Pet-Info-Form-Update.csv
	static $rowCount;
	$rowCount++;
	global $dataHeaders, $file;
	if(!($clientid = findClientByName($clientName = rowAtHeader($row, "Your Name")))) {
		echo "<font color=red>Client not found! [$clientName] [Pet: ".rowAtHeader($row, "Pet's Name")."]</font><p>";
		return;
	}

	$pet =  array('active'=>1, 'ownerptr'=>$clientid);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($trimVal == '') continue;
		if($label == "Pet's Name") $pet['name'] = $trimVal;
		else if($label == "Type of Animal") $pet['type'] = $trimVal;
		else if($label == "Markings/Breed") $pet['description'] = $trimVal;
		else if($label == "Birthdate") $pet['dob'] = $trimVal;
		else if($label == "Sex") {
			$pet['sex'] = 
				strpos($trimVal, 'emale') ? 'f' : (
				strpos($trimVal, 'ale') !== FALSE ? 'm' : '');
			$pet['fixed'] = 
				strpos($trimVal, 'payed') || strpos($trimVal, 'eutered') ? '1' : '0';
		}
		else if($property = fetchRow0Col0("SELECT property FROM tblpreference WHERE value LIKE '$label%' LIMIT 1")) {
			if(strpos($property, 'petcustom') === 0) $petcustom[$property] = $trimVal;
			else if(strpos($property, 'custom') === 0) $custom[$property] = $trimVal;
		}
	}
	if(!$pet['name']) {
		echo "<font color=red>Nameless pet ignored at row $rowCount</font><br>";
		return;
	}
	//echo "Will save ".print_r($pet, 1)."<p>Pet custom: ".print_r($petcustom, 1)."<p>Client custom: ".print_r($custom, 1)."<hr>";
	$petid = insertTable('tblpet', $pet, 1);
	savePetCustomFields($petid, $petcustom, $number= '');
	saveClientCustomFields($clientid, $custom);
	echo "Created  ".print_r($pet, 1)."<p>Pet custom: ".print_r($petcustom, 1)."<p>Client custom: ".print_r($custom, 1)."<hr>";
	
}

function handlePawsitivelyPooches($row, $ignore=null) { // pawsitivelypooches
//Name	Street	City	State	Zip Code	Agreement of Service	Pet Name	Primary Phone	Text Ok?	
// Secondary Phone	Text Ok?	Primary Email Address	Secondary Email Address	Preferred Method of Contact	
// Emergency Contact	Location of Extra Key/Garage Code	Alarm Code	Alarm Company	Alarm Phone Number
	static $rowCount;
	$rowCount++;
	global $dataHeaders, $file;
	if(strpos($file, 'Pet-Info')) return handlePawsitivelyPoochesPetInfo($row);
	
	
exit;	
	
	
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Name')  
			handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey='fname');		
		else if($label == 'Street') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip Code') $client['zip'] = $trimVal;
		//Agreement of Service
		//Pet Name
		else if($label == 'Primary Phone') {
			if($row[$i+1] == 'Yes') $client['cellphone'] = "*T$trimVal"; // Text Ok?
			else $client['homephone'] = "*T$trimVal";
		}
		else if($label == 'Secondary Phone') {
			$cellphonefield = $client['cellphone'] ? 'cellphone2' : 'cellphone';
			$homephonefield = $client['homephone'] ? 'cellphone2' : 'homephone';
			if($row[$i+1] == 'Yes') $client[$cellphonefield] = "T$trimVal"; // Text Ok?
			else $client[$homephonefield] = "$trimVal";
		}
		else if($label == 'Primary Email Address') $client['email'] = $trimVal;
		else if($label == 'Secondary Email Address') $client['email2'] = $trimVal;
		else if($label == 'Preferred Method of Contact') $custom['custom1'] = $trimVal;
		else if($label == 'Emergency Contact') $client['notes'] = "Emergency Contact: $trimVal";
		else if($label == 'Location of Extra Key/Garage Code') $client['directions'] = $trimVal;
		else if($label == 'Alarm Code') $client['alarminfo'] = "Alarm code: $trimVal";
		else if($label == 'Alarm Company') $client['alarmcompany'] = $trimVal;
		else if($label == 'Alarm Phone Number') $client['alarmcophone'] = $trimVal;
	}
	foreach(array('email', 'email2') as $emailfield)
		if($client[$emailfield] && !isEmailValid( $client[$emailfield])) { // see field-utils.php
			$badEmails[] = $client[$emailfield];
			unset($client[$emailfield]);
		}
	$client['fname'] = $client['fname'] ? $client['fname'] : 'UNKNOWN';
	$client['lname'] = $client['lname'] ? $client['lname'] : 'UNKNOWN';
	
	$clientptr = saveNewClient($client);
	foreach((array)$custom as $k=>$val)
		doQuery("REPLACE relclientcustomfield (clientptr, fieldname, value) VALUES ($clientptr, '$k', '"
							.mysqli_real_escape_string($val)."')", 1);
	
	echo "Added client {$client['fname']} {$client['lname']} ($clientptr)";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";	
}

function handleParthenonPups($row, $ignore=null) { // parthenonpups
//client_id	first_name	last_name	address1	city	state	zip	ice_name	ice_phone	email	mobile	status	
//client_since	notes	feeding_instr	alarm_code	alarm_instr
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'first_name') $client['fname'] = $trimVal;
		else if($label == 'last_name') $client['lname'] = $trimVal;
		else if($label == 'address1') $client['street1'] = $trimVal;
		else if($label == 'city') $client['city'] = $trimVal;
		else if($label == 'state') $client['state'] = $trimVal;
		else if($label == 'zip') $client['zip'] = $trimVal;
		else if($label == 'email') $client['email'] = $trimVal;
		else if($label == 'mobile') $client['cellphone'] = $trimVal;
		else if($label == 'status') $client['active'] = 1;
		else if($label == 'notes') $client['notes'] = $trimVal;
		else if($label == 'client_since') $client['notes'] .= "\n$trimVal";
	}
	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	
	$clientptr = saveNewClient($client);
	
	echo "Added client {$client['fname']} {$client['lname']} ($clientptr)";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";	
}

function handleGoodDogWalkingandSitting($row, $ignore=null) { // gooddogwalkingandsitting
	// First Name	Middle Name	Last Name	Notes	E-mail Address	E-mail 2 Address	E-mail 3 Address	
	// Home Phone	Mobile Phone	Home Address	Business Phone	Home Address	Other Phone
																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																															

	// 29 Jan 2014
	// From GoogleContacts spreadsheet
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Middle Name') $petNames = handlePetNames($trimVal);
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Notes') $notes[] = $trimVal;
		else if($label == 'E-mail Address') $client['email'] = $trimVal;
		else if($label == 'E-mail 2 Address') $client['email2'] = $trimVal;
		else if($label == 'E-mail 3 Address') ; // not applicable
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Mobile Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Other Phone') $client['cellphone2'] = $trimVal;
		else if($label == 'Business Phone') $client['workphone'] = $trimVal;

		else if($label == 'Home Address') handleAddress($trimVal, $client);
/*		else if($label == 'Home City') $client['city'] = $trimVal;
		else if($label == 'Home State') $client['state'] = $trimVal;
		else if($label == 'Home Postal Code') $client['zip'] = $trimVal;
*/	}
	foreach(array('email', 'email2') as $emailKey) {
		if($client[$emailKey] && !isEmailValid($client[$emailKey])) { // see field-utils.php
			$notes[] = "\nInvalid email address: {$client[$emailKey]}";
			unset($client[$emailKey]);
		}
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	$clientptr = saveNewClient($client);
	global $added;
	$added += 1;
	
	if($petNames) {
		foreach($petNames as $i => $nm) {
			if(!$nm) {
				unset($petNames[$i]);
				continue;
			}
			$petCount++;
			$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
			insertTable('tblpet', $pet, 1);
		}
	}
	
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(', ', $petNames)."]";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	
}



function handleDogHouseGirls($row) {
	//Customer	Street 1	Street2	City	State	Zip	Phone	Phone #2	Email	EMAIL #2
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>'1', 'setupdate'=>date('Y-m-d'), 'state'=>'NC');
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		else if($label == 'Customer') $petNames = handlePetNames($trimVal);
		else if($label == 'Street 1') {
			if(strpos($trimVal, '&')) {
				$names = decompose($trimVal, '&');
				handleFnameSpaceLname($names[0], $client, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey='fname');
				handleFnameSpaceLname($names[1], $client, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname2');
				if(!$client['lname']) $client['lname'] = $client['lname2'];
			}
			else handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		}
		else if($label == 'Street2') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Phone') $client['homephone'] = $trimVal;
		else if($label == 'Phone #2') $client['workphone'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'EMAIL #2') $client['email2'] = $trimVal;
	}
	foreach(array('email','email2') as $fld) {
		if($client[$fld] && !isEmailValid( $client[$fld])) { // see field-utils.php
			$notes[] = "Invalid email address: ".$client[$fld];
			unset($client[$fld]);
		}
	}
	if($notes) $client['notes'] = join("\n", $notes);
	
	if(!$client['lname']) $client['lname'] = 'UNKNOWN';
	if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	
	$clientptr = saveNewClient($client);
	
	if($petNames) foreach($petNames as $name) {
		if(!trim($name)) continue;
		$pet = array('name'=>$name, 'ownerptr'=>$clientptr);
		//$pet['type'] = guessTypeByBreed($name);
		insertTable('tblpet', $pet, 1);
	}

	echo "Added client {$client['fname']} {$client['lname']} ($clientptr)";
	if($notes) echo "<br>... but rejected these bad email addresses: [".join('], [', $notes).']';
	echo "<p>";
}


function handleMissJanesPetSitting($row) {
	// Last Name	First Name	Address 1	Address 2	City	State	Zip	Day Phone	Cell #1	Cell #2	Email
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>'1', 'setupdate'=>date('Y-m-d'), 'state'=>'NC');
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Address 1') $client['street1'] = $trimVal;
		else if($label == 'Address 2') $client['street2'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Day Phone') $client['workphone'] = $trimVal;
		else if($label == 'Cell #1') $client['cellphone'] = $trimVal;
		else if($label == 'Cell #2') $client['cellphone2'] = $trimVal;
		else if($label == 'Email') $client['email'] = strtolower($trimVal);
	}
	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	
	$clientptr = saveNewClient($client);
	
	echo "Added client {$client['fname']} {$client['lname']} ($clientptr)";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";
}
	


function capitalizeWords($str) {
	if(!isAllUpper($str)) {echo "oops: [$str]<br>";return $str;}
	$words = explode(' ', $str);
	foreach(explode(' ', $str) as $i => $word)
		$words[$i] = ucfirst(strtolower($word));
	return join(' ', $words);
}

function isAllUpper($str) { // return true if str has more than one letter and all ar CAPS
	$uppers = 0;
	for($i=0;$i<strlen($str);$i++) {
		$c = $str[$i];
		if($c >= 'a' && $c <= 'z') return false;
		if($c >= 'A' && $c <= 'Z') $uppers += 1;
	}
	return $uppers > 1;
}
	

function handlePeakCityPuppy2($row) {
	//"LAST","FIRST","EMAIL","PHONE","STREET","ZIP","CITY","DOGS","PRICE LOCK"

	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>'1', 'setupdate'=>date('Y-m-d'), 'state'=>'NC');
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'FIRST') $client['fname'] = capitalizeWords($trimVal);
		else if($label == 'LAST') $client['lname'] = capitalizeWords($trimVal);
		else if($label == 'EMAIL') $client['email'] = strtolower($trimVal);
		else if($label == 'STREET') $client['street1'] = capitalizeWords($trimVal);
		else if($label == 'CITY') $client['city'] = capitalizeWords($trimVal);
		else if($label == 'ZIP') $client['zip'] = $trimVal;
		else if($label == 'DOGS') {
			if(strpos($trimVal, '-')) $trimVal = substr($trimVal, strpos($trimVal, '-')+1);
			$petNames = handlePetNames(capitalizeWords($trimVal));
		}
		else if($label == 'PRICE LOCK') $client['notes'] = "Price lock: $trimVal";
	}
	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	
	$clientptr = saveNewClient($client);
	
	if($petNames) foreach($petNames as $name) {
		if(!trim($name)) continue;
		$pet = array('name'=>$name, 'ownerptr'=>$clientptr, 'type'=>'Dog');
		insertTable('tblpet', $pet, 1);
	}
	
	echo "Added client {$client['fname']} {$client['lname']} ($clientptr) with pets: [".join('] [', (array)$petNames).']';
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";
}
	
	
function handleDoggyDay($row) {
	// issues: 
	// - All CLients active
//print_r($row);	
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>'0', 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		//if($label == 'Customer' && !$client['lname']) 
		//	handleLnameCommaFname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		if($label == 'Forename') $client['fname'] = anglicize($trimVal);
		else if($label == 'Surname') $client['lname'] = anglicize($trimVal);
		else if($label == 'Diary Ref') $petNames = handlePetNames($trimVal);
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Address') handleAddress(anglicize($trimVal), $client);
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Mobile Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Area') $custom['custom2'] = anglicize($trimVal);
		else if($label == 'Payment Type') $custom['custom3'] = $trimVal;
		else if($label == 'Client Type') $custom['custom4'] = $trimVal;
		else if($label == 'Primary Sitter') $client['defaultproviderptr'] = findSitterByName($trimVal);
		else if($label == 'Active') {
			$client['active'] = (strtoupper($trimVal) == 'TRUE' ? 1 : '0');
		}
		else if($label == 'Alt Forename') $client['fname2'] = anglicize($trimVal);
		else if($label == 'Alt Surname') $client['lname2'] = anglicize($trimVal);
		else if($label == 'Alt Email') $client['email2'] = $trimVal;
		else if($label == 'Alt Home Phone') $client['cellphone2'] = $trimVal;
		else if($label == 'Alt Mobile Phone') {
			if($client['cellphone2']) $client['notes'][]  = "Alt Home Phone: ".$client['cellphone2'];
			$client['cellphone2'] = $trimVal;
		}
	}
	if(findClientByName("{$client['fname']} {$client['lname']}")) {
		echo "<font color=red>Client already exists: {$client['fname']} {$client['lname']}</font><p>";
		return;
	}
	
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);


	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	if($client['email2'] && !isEmailValid( $client['email2'])) { // see field-utils.php
		$badEmails[] = $client['email2'];
		unset($client['email2']);
	}
	
	$clientptr = saveNewClient($client);
	
	foreach((array)$custom as $k=>$val)
		doQuery("REPLACE relclientcustomfield (clientptr, fieldname, value) VALUES ($clientptr, '$k', '"
							.mysqli_real_escape_string($val)."')", 1);
	if($petNames) foreach($petNames as $name) {
		if(!trim($name)) continue;
		$pet = array('name'=>$name, 'ownerptr'=>$clientptr);
		$pet['type'] = guessTypeByBreed($name);
		insertTable('tblpet', $pet, 1);
	}
	
	echo "Added client {$client['fname']} {$client['lname']} ($clientptr) with pets: [".join('] [', (array)$petNames).']';
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}

function anglicize($trimVal) {
	if(!$trimVal) return;
	$trimVal =  str_replace('ô', 'o', $trimVal);
	$trimVal = str_replace('é', 'e', $trimVal);
	$trimVal = str_replace('É', 'E', $trimVal);
	$trimVal = str_replace('è', 'e', $trimVal);
	return $trimVal;
}

function handleFivePawsDelco($row) {
	// issues: 
	// - All CLients active
//print_r($row);	
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		//if($label == 'Customer' && !$client['lname']) 
		//	handleLnameCommaFname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		if($label == 'fname') $client['fname'] = $trimVal;
		else if($label == 'lname') $client['lname'] = $trimVal;
		else if($label == 'Alt. Contact') $client['fname2'] = $trimVal;
		else if($label == 'email') $client['email'] = $trimVal;
		else if($label == 'userid') $client['loginid'] = $trimVal;
		else if($label == 'homephone') $client['homephone'] = $trimVal;
		else if($label == 'cellphone') $client['cellphone'] = $trimVal;
		else if($label == 'workphone') $client['workphone'] = $trimVal;
		else if($label == 'lname2') $client['lname2'] = $trimVal;
		else if($label == 'fname2') $client['fname2'] = $trimVal;
		else if($label == 'email2') $client['email2'] = $trimVal;
		else if($label == 'cellphone2') $client['cellphone2'] = $trimVal;
		
		else if($label == 'street1') $client['street1'] = $trimVal;
		else if($label == 'street2') $client['street2'] = $trimVal;
		else if($label == 'city') $client['city'] = $trimVal;
		else if($label == 'state') $client['state'] = $trimVal;
		else if($label == 'zip') $client['zip'] = $trimVal;
		
		else if($label == 'mailstreet1') $client['mailstreet1'] = $trimVal;
		else if($label == 'mailstreet2') $client['mailstreet2'] = $trimVal;
		else if($label == 'mailcity') $client['mailcity'] = $trimVal;
		else if($label == 'mailstate') $client['mailstate'] = $trimVal;
		else if($label == 'mailzip') $client['mailzip'] = $trimVal;
		
		else if($label == 'login') $client[''] = $trimVal;
		else if($label == '') $client[''] = $trimVal;
		
		
		
	}

	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	if($client['email2'] && !isEmailValid( $client['email2'])) { // see field-utils.php
		$badEmails[] = $client['email2'];
		unset($client['email2']);
	}
	$clientptr = saveNewClient($client);
	
	for($i=1;$i<=3;$i++) {
		if($trimVal = rowAtHeader($row, "Pet Name #$i")) {
			$pet = array('active'=>1, 'ownerptr'=>$clientptr, 'name'=>$trimVal, 
										'type'=>rowAtHeader($row, "Pet Type #$i"), 
										'sex'=>rowAtHeader($row, "Sex #$i"), 
										'breed'=>rowAtHeader($row, "Breed #$i"));
			// add pet
			insertTable('tblpet', $pet, 1);
			$petNames[] = $trimVal;
		}
	}

	echo "Added client {$client['fname']} {$client['lname']} ({$client['clientid']}) with pets: [".join('] [', (array)$petNames).']';
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	require_once "system-login-fns.php";
	if($loginid = rowAtHeader($row, "userid")) {
		if(findSystemLoginWithLoginId($loginid, 'nullifnotfound'))
			echo "<br>... but the loginid [$loginid] was already in use.";
		else {
			$data = array('loginid'=>$loginid, 'temppassword'=>$loginid, 'active'=>1, 'rights'=>'c-', 'bizptr'=>$_SESSION["bizptr"]);
			$login = addSystemLogin($data, $clientOrProviderOnly=true);
			updateTable('tblclient', array('userid'=>$login['userid']), "clientid = $clientptr");
			echo "<br>... with system loginid [$loginid] ({$login['userid']}).";
		}
	}
	// DELETE FROM tbluser WHERE bizptr = 633 AND rights LIKE 'c-%';
	echo "<p>";

}




function handleMyPetsFriend($row) {
	// issues: 
	// - All CLients active
	// - Customer type = entry type -- put in Garage / Gate Code
//print_r($row);	
// MPF-Client-List.csv
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		//if($label == 'Customer' && !$client['lname']) 
		//	handleLnameCommaFname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'M.I.') $client['fname'] .= " $trimVal";
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Alt. Contact') $client['fname2'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Bill to 2') $client['street1'] = $trimVal;
		else if($label == 'Bill to 3') $client['city'] = $trimVal;
		else if($label == 'Bill to 4') $client['state'] = $trimVal;
		else if($label == 'Bill to 5') $client['zip'] = $trimVal;
		else if($label == 'Customer Type') $client['notes'][]  = "Referral: ".$trimVal;
		else if($label == 'Resale Num') $client['notes'][]  = "Referral source: ".$trimVal;
		else if($label == 'Job Type') $client['notes'][]  = "Job type: ".$trimVal;
		else if($label == 'Job Description') $petNames = handlePetNames($trimVal);
		else if($label == 'Note')  $client['notes'][] = $trimVal;
	}
	sortOutPhones($row, 'Phone,Alt. Phone,Fax', $client, 'Alt. Phone');
	if($x = rowAtHeader($row, "Start Date")) {
		$dates = array("Start Date: $x");
		if($x = rowAtHeader($row, "Projected End")) $dates[] = "Proj. End: $x";
		if($x = rowAtHeader($row, "End Date")) $dates[] = "End Date: $x";
		$client['notes'][] = join(' ', $dates);
	}
	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	if($client['notes'])
		$client['notes'] = join("\n", $client['notes']);
	$clientptr = saveNewClient($client);
	if($petNames) foreach($petNames as $name) {
		if(!trim($name)) continue;
		$parts = array_map('trim', explode('-', $name));
		$pet = array('name'=>$parts[0], 'ownerptr'=>$clientptr);
		if($parts[1]) {
			$pet['breed'] = $parts[1];
			$pet['type'] = guessTypeByBreed($parts[1]);
		}
		insertTable('tblpet', $pet, 1);
	}
		
	echo "Added client {$client['fname']} {$client['lname']} with pets: [".join('] [', $petNames).']';
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}




function handleBARpetservices($row) {
//FirstName	LastName	Email	Street	Street2	City	Province	Country	PostalCode	BusPhone	HomePhone	MobPhone	Fax
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'FirstName') $client['fname'] = $trimVal;
		else if($label == 'LastName') $client['lname'] = $trimVal;
		else if($label == 'Street') $client['street1'] = $trimVal;
		else if($label == 'Street2') $client['street2'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'Province') $client['state'] = $trimVal;
		else if($label == 'PostalCode') $client['zip'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'HomePhone') $client['homephone'] = $trimVal;
		else if($label == 'BusPhone')  $client['workphone'] = $trimVal;
		else if($label == 'MobPhone') $client['cellphone'] = $trimVal;
		else if($label == 'Other Phone') $client['cellphone2'] = $trimVal;
		else if($label == 'Other Fax') ; // unused

	}
	foreach(array('email') as $emailKey) {
		if($client[$emailKey] && !isEmailValid($client[$emailKey])) { // see field-utils.php
			$notes[] = "\nInvalid email address: {$client[$emailKey]}";
			$badEmail[] = $client[$emailKey];
			unset($client[$emailKey]);
		}
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	$clientptr = saveNewClient($client);
	global $added;
	$added += 1;
	
	echo "Added client {$client['fname']} {$client['lname']} with bad email [".join(', ', (array)$badEmail)."]";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	
}

	
function handleHansenHomeAndPet($row) {
	// issues: 
	// - All CLients active
	// - Customer type = entry type -- put in Garage / Gate Code
//print_r($row);	
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	if(trim($row[array_search('First Name', $dataHeaders)]) 
			&& trim($row[array_search('Last Name', $dataHeaders)])) {
		$client['lname'] = trim($row[array_search('Last Name', $dataHeaders)]);
		$client['fname'] = trim($row[array_search('First Name', $dataHeaders)]);
	}
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Customer' && !$client['lname']) 
			handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Bill to 2') {
			$client['street1'] = $trimVal;
		}
		else if($label == 'Bill to 3') {
			getCityStateZip($row[array_search('Bill to 3', $dataHeaders)], $client);
			//echo print_r($client, 1).'<p>';
		}
		else if($label == 'Customer Type') 
			$client['garagegatecode'] = $trimVal;
		else if($label == 'Alt. Contact') 
			$client['fname2'] = $trimVal;
		else if($label == 'Note') 
			$client['notes'][] = $trimVal;
		else if(in_array($label, explode(',','Ship to 1,Ship to 2,Ship to 3,Ship to 4,Ship to 5'))) 
			$client['notes'][] = $trimVal;
	}
	sortOutPhones($row, 'Phone,Fax,Alt. Phone', $client, 'Alt. Phone');

	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	if($client['notes'])
		$client['notes'] = join("\n", $client['notes']);
	$clientptr = saveNewClient($client);
	if($customFields) 
		
	echo "Added client {$client['fname']} {$client['lname']}";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}

function handlePrideyGirl($row, $ignore=null) {
	// First Name	Address Line 1	Home Street 2	Home City	Home State	Home Country	Home Postal Code	
	// Name Billed	Billing Address 1	Billing Address 2	Bill City	Bill State	Bill Country	Bill Postal	
	// Home Phone	Work Phone	Mobile Phone	Fax	E-mail Address	Contact Name
	// First Name = full name, sometimes composite
	// Billing and Contact fields are not supplied
	// 24 April 2014
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') {
			foreach(decompose($trimVal, "&") as $j => $nm) {
				if(strpos($trimVal, "CLOSED ACCOUNT")) {
					$client['active'] = 0;
					$nm = trim(substr($nm, 0 , strpos($trimVal, "CLOSED ACCOUNT")));
				}
				if($j == 0) handleFnameSpaceLname($nm, $client, $fnameKey='fname', $lnameKey='lname');
				else if($j == 1) handleFnameSpaceLname($nm, $client, $fnameKey='fname2', $lnameKey='lname2');
			}
		}
		else if($label == 'Address Line 1') $client['street1'] = $trimVal;
		else if($label == 'Home Street 2') $client['street2'] = $trimVal;
		else if($label == 'Home City') $client['city'] = $trimVal;
		else if($label == 'Home State') $client['state'] = $trimVal;
		else if($label == 'Home Postal Code') $client['zip'] = $trimVal;
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Work Phone 2') $client['workphone'] = $trimVal;
		else if($label == 'Mobile Phone') $client['cellphone'] = $trimVal;
		else if($label == 'E-mail Address') {
			if(!isEmailValid($trimVal)) // see field-utils.php
				$notes[] = "\nInvalid email address: $trimVal";
			else $client['email'] = $trimVal;
		}
	}
	if($notes) $client['notes'] = join("\n", $notes);	

	$clientptr = saveNewClient($client);
	global $added;
	$added += 1;
	echo "Added client {$client['fname']} {$client['lname']}";
	if($client['fname2']) echo " with {$client['fname2']} {$client['lname2']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br> -- ".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}
	
	
	
function handleBarknGoodTime($row, $ignore=null) {
	// First Name, Pets Name, Last Name, Notes, E-mail Address, E-mail 2 Address, E-mail 3 Address, 
 	// Home Phone, Home Phone 2, Mobile Phone, 
 	// Home Street, Home City, Home State, Home Postal Code, Other Phone, Other Fax																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																

	// 29 Jan 2014
	// From GoogleContacts spreadsheet
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Pets Name') $petNames = handlePetNames($trimVal);
		else if($label == 'Notes') $notes[] = $trimVal;
		else if($label == 'E-mail Address') $client['email'] = $trimVal;
		else if($label == 'E-mail 2 Address') $client['email2'] = $trimVal;
		else if($label == 'E-mail 3 Address') ; // not applicable
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Home Phone 2') ; // unused
		else if($label == 'Mobile Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Other Phone') $client['cellphone2'] = $trimVal;
		else if($label == 'Other Fax') ; // unused

		else if($label == 'Home Street') $client['street1'] = $trimVal;
		else if($label == 'Home City') $client['city'] = $trimVal;
		else if($label == 'Home State') $client['state'] = $trimVal;
		else if($label == 'Home Postal Code') $client['zip'] = $trimVal;
	}
	foreach(array('email', 'email2') as $emailKey) {
		if($client[$emailKey] && !isEmailValid($client[$emailKey])) { // see field-utils.php
			$notes[] = "\nInvalid email address: {$client[$emailKey]}";
			unset($client[$emailKey]);
		}
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	$clientptr = saveNewClient($client);
	global $added;
	$added += 1;
	
	if($petNames) {
		foreach($petNames as $i => $nm) {
			if(!$nm) {
				unset($petNames[$i]);
				continue;
			}
			$petCount++;
			$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
			insertTable('tblpet', $pet, 1);
		}
	}
	
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(', ', $petNames)."]";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	
}

function handleGreenPaws($row, $ignore=null) {
	// 29 Jan 2014
	// From GoogleDocs spreadsheet
	//Account	AccountStatus	AccountType	MainPhone	MainFax	Campaign	LocationName	LocationAddress	LocationPhone	LocationFax	
	// Contact	ContactWorkPhone	ContactHomePhone	ContactMobile	ContactFax	ContactEmail	
	// BusinessUnit	LocationCSZ	CountGroupBy1	CountGroupBy2	CountGroupBy3	CountGroupByGrand	
	// City	State	PostalCode	Zone	CreatedBy	NOTES	Price Overrides	Termination Reason	
	// Alarm & Access Code	Door Key Information	Residence Specifics
	// 
	/********
	  All clients are active
		Pets occur in lines where Contact where Contact != Account
		Use other contact fields, where supplied, as regular client fields.
		Add a Termination flag to hold Termination Reason
		AccountStatus "Active Recurring*" gets a Billing Flag #1; all others get Billing Flag #2
		set up client flags for VIP (1, gold star) , Non-Member Regular (2, person), Member (3, perseon checked), Consult (4, clock), 
			Cat Only (5, cat), CareOnCall (6, heart)
	*****/
	static $rowCount;
	$rowCount++;
	global $dataHeaders, $lastClient, $zoneFlags;
	if(!$zoneFlags) $zoneFlags = explodePairsLine('VIP|1||Non-Member Regular|2||Member|3||Consult|4||Cat Only|5||CareOnCall|6');
	if(!rowAtHeader($row, "Account")) return;
	if(!$lastClient || "{$lastClient['fname']} {$lastClient['lname']}" != trim(rowAtHeader($row, "Account"))) {
		// save last client
		if($lastClient) finalizeGreenPawsClient($lastClient);
		$client = array('active'=>1, 'setupdate'=>date('Y-m-d'));
	}
	else if($lastClient) $client = $lastClient;
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		else if($label == 'Account') {
			if(strpos($trimVal, ',')) handleLnameCommaFname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
			else handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
			$accountName = "{$client['fname']} {$client['lname']}";
		}
		else if($label == 'AccountStatus') {
			if(strpos($trimVal, 'Recurring'))  $client['billflag'] = 1;
			else $client['billflag'] = 2;
		}
		else if($label == 'MainPhone') $client['mainphone'] = $trimVal;
		else if($label == 'LocationName' && $trimVal != rowAtHeader($row, "LocationAddress"))
			$client['notes'][] = 'LocationName: '.$trimVal;
		else if($label == 'LocationPhone') $client['homephone'] = $trimVal;
		else if($label == 'LocationAddress') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'PostalCode') $client['zip'] = $trimVal;
		else if($label == 'Contact' && $trimVal != $accountName) {
			// Contact refers to either pet(s) or Emergency Contacts in this case
			//global $contactMap;
			//if(!$contactMap) $contactMap = explodePairsLine('ContactWorkPhone|workphone||ContactHomePhone|homephone||ContactMobile|cellphone||ContactEmail|note');
			//$contact = null;
			//foreach($contactMap as $key => $field) if(rowAtHeader($row, $key)) $contact = array('name'=> $trimVal);
			//if($contact) {
			//	foreach($contactMap as $key => $field) $contact[$field] = trim(rowAtHeader($row, $key));
			//	$client['contacts'][] = $contact;
			//}
			//else {
				$petNames = handlePetNames($trimVal);
				foreach($petNames as $nm) {
					if($start = strpos($nm, " {$client['lname']}"))
						$nm = substr($nm, 0, $start);
				}
				$client['petnames'][] = $nm;
			//}
		}
// phones: MainPhone(mainphone), LocationPhone(homephone), ContactWorkPhone(workphone)	ContactHomePhone(homephone or cellphone2)	ContactMobile(cellphone)
		else if(!$contact && $label == 'ContactWorkPhone') $client['workphone'] = $trimVal;
		else if(!$contact && $label == 'ContactMobile') $client['cellphone'] = $trimVal;
		else if(!$contact && $label == 'ContactHomePhone') {
			if($client['homephone']) $client['cellphone2'] = $trimVal;
			else $client['homephone'] = $trimVal;
		}
		else if(!$contact && $label == 'ContactEmail') {
			if(!$client['email']) $client['email'] = $trimVal;
			else if(!$client['email2']) $client['email2'] = $trimVal;
			else $client['notes'][] = "Additional email: $trimVal";
		}
		else if($label == 'Zone') $client['flags'][] = $zoneFlags[$trimVal];
		else if($label == 'NOTES') $client['notes'][] = $trimVal;
		else if($label == 'Price Overrides') $client['officenotes'][] = $trimVal;
		else if($label == 'Termination Reason') $client['termination'] = $trimVal;
		else if($label == 'Residence Specifics') $client['officenotes'][] = $trimVal;
	}
	$lastClient = $client;	
}

function finalizeGreenPawsClient($client) {
	$mainphone = $client['mainphone'];
	unset($client['mainphone']);
	foreach(explode(',', 'homephone,workphone,cellphone,cellphone2') as $k) {
		if($client[$k] == $mainphone) {
			$client[$k] = "*$mainphone";
			break;
		}
	}
	if($client[$k] != "*$mainphone") {
		$workphone = $client['workphone'];
		if(!$workphone) $client['workphone'] = "*$mainphone";
		else $client['notes'][] = "Main Phone: $mainphone";
	}
	
	$notes = $client['notes'];
	foreach(array('email', 'email2') as $emailKey) {
		if($client[$emailKey] && !isEmailValid($client[$emailKey])) { // see field-utils.php
			$notes[] = "\nInvalid email address: {$client[$emailKey]}";
			unset($client[$emailKey]);
		}
	}
	// UNUSED
	$contacts = $client['contacts'];
	unset($client['contacts']);
	if($contacts) { // handle excess contacts
		for($i=2; $i<count($contacts); $i++) {
			$con = $contacts[$i];
			$desc = "Contact: {$con['name']}";
			foreach($con as $k=>$v) if($k !='name') $desc .= " {$k[0]}: $v";
			$notes[] = $desc;
		}
	}
	
	if($notes) $client['notes'] = join("\n", array_unique($notes));	
	$officenotes = $client['officenotes'];
	if($officenotes) $client['officenotes'] = join("\n", array_unique($officenotes));	
	
	$termination = $client['termination'];
	unset($client['termination']);
	$flags = $client['flags'];
	unset($client['flags']);
	$billflag = $client['billflag'];
	unset($client['billflag']);
	$petNames = $client['petnames'];
	unset($client['petnames']);
	
	if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	

	$clientptr = saveNewClient($client);
	
	if($billflag) setClientPreference($clientptr, "billing_flag_$billflag", "|");
	
	if($contacts) {	// UNUSED

		foreach($contacts as $i => $contact) {
			if($i == 0) saveClientContact('emergency', $clientptr, $contact);
			else if($i == 1) saveClientContact('neighbor', $clientptr, $contact);
		}
	}
			
	if($petNames) {
		foreach($petNames as $i => $nm) {
			if(!$nm) {
				unset($petNames[$i]);
				continue;
			}
			$petCount++;
			$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
			insertTable('tblpet', $pet, 1);
		}
	}
	$flagon = 1;
	if($flags) foreach($flags as $flag) {
		setClientPreference($clientptr, "flag_$flagon", "$flag|");
		$flagon += 1;
	}
	if($termination) setClientPreference($clientptr, "flag_$flagon", "7|$termination"); // 7 = terminated flag


	echo "Added client <b>{$client['fname']} {$client['lname']}</b> with "
				.($petNames ? "pets: ".join(', ', $petNames) : "no pets").($contacts ? " and ".count($contacts)." CONTACTS." : '');
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	global $added;
	$added += 1;

}



function handleThePawSitter($row, $ignore=null) {
	//Salutation	First Name	Last Name	
	//Work Name	Business Street	Business City	Business State	Business Postal Code	Business Country	
	//Home Street	Home City	Home State	Home Postal Code	Home Country	Business Phone	Business Phone Extension	
	//Home Phone	Mobile Phone	Birthday	E-mail Address	E-mail 2 Address	E-mail 3 Address	Star Rating	Subscribed

	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Home Street') $client['street1'] = $trimVal;
		else if($label == 'Home City') $client['city'] = $trimVal;
		else if($label == 'Home State') $client['state'] = $trimVal;
		else if($label == 'Home Postal Code') $client['zip'] = $trimVal;
		else if($label == 'Business Phone') $client['workphone'] = $trimVal;
		else if($label == 'Business Phone Extension') $client['workphone'] .= " $trimVal";
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Mobile Phone') $client['cellphone'] = $trimVal;
		else if($label == 'E-mail Address') $client['email'] = $trimVal;
		else if($label == 'E-mail 2 Address') $client['email2'] = $trimVal;
		else if($label == 'E-mail 3 Address') ; // not applicable
	}
	foreach(array('email', 'email2') as $emailKey) {
		if($client[$emailKey] && !isEmailValid($client[$emailKey])) { // see field-utils.php
			$notes[] = "\nInvalid email address: {$client[$emailKey]}";
			unset($client[$emailKey]);
		}
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";

	echo "Added client {$client['fname']} {$client['lname']}";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}



function handleFurryGodmotherConejo($row, $ignore=null) {
	// This was output from Bento, I believe.
	//Date Created, Date Modified, First Name, Last Name, Notes, Email (Value), Vet, 
	// Address (Street), Address (City), Address (State), Address (Zip), Address (Country), Phone, 
	// Alternate Name, Pets, Contact daily by, Garbage Day, Feeding Schedule, Key, contact phone (Value), Fee Schedule, 
	// Referred by, Emergency shutoffs, Payments, Emergency Contacts, Promotional Use, Rabies, 
	// PhoneHome (Value), PhoneCell (Value), PhoneCell2 (Value), 
	// Pet Name, Pet Breed, Pet Color, Pet Birthday, 
	// Pet Name2, Pet Breed2, Pet Color2, Pet Birthday2, 
	// Pet Name3, Pet Breed3, Pet Color3, Pet Birthday3, 
	// Pet Name4, Pet Breed4, Pet Color4, Pet Birthday4, 
	// Pet Name5, Pet Breed5, Pet Color5, Pet Birthday5,
	// Pet Name6, Pet Breed6, Pet Color6, Pet Birthday6, 
	// Pet Name7, Pet Breed7, Pet Color7, Pet Birthday7, 
	// Pet Name8, Pet Breed8, Pet Color8, Pet Birthday8
	//
	// Notes: "Vet" is too loose to map to clinics (app. notes), Contact daily by (app. notes), 
	// Spelling assignment "Shih Tzu", "terrier"
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	require_once "custom-field-fns.php";
	require_once "preference-fns.php";
	//format: label|active|onelineORtextORboolean|visitsheet|clientvisible
	$customFieldDescr = 
"custom1//Contact daily by|1|oneline|1|1
custom2//Garbage Day|1|oneline|1|1
custom3//Feeding Schedule|1|text|1|1
custom4//Referred by|1|oneline|1|1
custom5//Key Notes|1|text|1|1
custom6//Fee Schedule|1|text|0|0
custom7//Emergency shutoffs|1|text|1|1
custom8//Emergency Contacts|1|text|1|1
custom9//Promotional Use|1|oneline|0|0
custom10//Rabies|1|oneline|0|0"
		;
	$customFieldDescr = explode("\n", $customFieldDescr);
	$existingCustomFields = getCustomFields();
	foreach($customFieldDescr as $line) {
		$pair = explode("//", trim($line));
		if(!$existingCustomFields[$pair[0]]) setPreference($pair[0], $pair[1]);
	}
	$client =  array('active'=>1);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Date Created') $client['setupdate'] = date('Y-m-d', strtotime($trimVal));
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Notes') $client['notes'] = $trimVal;
		else if($label == 'Email (Value)') $client['email'] = $trimVal;
		else if($label == 'Vet') $client['notes'] .= "\n\nVet: $trimVal";
		else if($label == 'Address (Street)') $client['street1'] = $trimVal;
		else if($label == 'Address (City)') $client['city'] = $trimVal;
		else if($label == 'Address (State)') $client['state'] = $trimVal;
		else if($label == 'Address (Zip)') $client['state'] = $trimVal;
		else if($label == 'Alternate Name')  handleFnameSpaceLname($trimVal, $client, $fnameKey='fname2', $lnameKey='lname2');
		else if($label == 'Contact daily by') $ccustFields['custom1'] = $trimVal;
		else if($label == 'Garbage Day') $ccustFields['custom2'] = $trimVal;
		else if($label == 'Feeding Schedule') $ccustFields['custom3'] = $trimVal;
		else if($label == 'Referred by') $ccustFields['custom4'] = $trimVal;
		else if($label == 'Key') $ccustFields['custom5'] = $trimVal;
		else if($label == 'Fee Schedule') $ccustFields['custom6'] = $trimVal;
		else if($label == 'Emergency shutoffs') $ccustFields['custom7'] = $trimVal;
		else if($label == 'Emergency Contacts') $ccustFields['custom8'] = $trimVal;
		else if($label == 'Promotional Use') $ccustFields['custom9'] = $trimVal;
		else if($label == 'Rabies') $ccustFields['custom10'] = $trimVal;
		else if($label == 'contact phone (Value)') $primaryPhone = "$trimVal";
		else if($label == 'PhoneHome (Value)') $client['homephone'] = ($primaryPhone == $trimVal ? '*' : '').$trimVal;
		else if($label == 'PhoneCell (Value)') $client['cellphone'] = ($primaryPhone == $trimVal ? '*' : '').$trimVal;
		else if($label == 'PhoneCell2 (Value)') $client['cellphone2'] = ($primaryPhone == $trimVal ? '*' : '').$trimVal;
	}
	$clientptr = saveNewClient($client);
	// handle $pets -- ['ownerptr']
	for($i=1; $i<9; $i++) {
		$suffix = $i > 1 ? $i : '';
		if(!rowAtHeader($row, "Pet Name$suffix")) continue;
		$pet = array('name'=>rowAtHeader($row, "Pet Name$suffix"), 'ownerptr'=>$clientptr);
		$petNames[] = $pet['name'];
		$breed = explode('(', rowAtHeader($row, "Pet Breed$suffix"));
		$pet['breed'] = $breed[0];
		$pet['type'] = guessTypeByBreed($pet['breed']);
		if($breed[1]) {
			$sex = strtolower($breed[1]);
			$pet['sex'] = $sex[0];
		}
		$pet['color'] = rowAtHeader($row, "Pet Color$suffix");
		$bday = rowAtHeader($row, "Pet Birthday$suffix");
		if($bday) {
			if(count(explode('/', $bday)) == 3 && strtotime($bday)) {
					$pet['dob'] = date('Y-m-d', strtotime($bday));
					//$pet['notes'] = "raw: [$bday]";
			}
			else $pet['notes'] = "born: $bday";
		}
		$petId = insertTable('tblpet', $pet, 1);
	}
	// handle $ccustFields
	if($ccustFields) 
		foreach($ccustFields as $k => $v) 
			insertTable('relclientcustomfield', array('clientptr'=>$clientptr, 'fieldname'=>$k, 'value'=>$v), 1);
	echo "<br>Added client {$client['fname']} {$client['lname']} with pets: ".join(', ', $petNames);
}


function handleRaleighPets($row, $ignore=null) {
	//First Name	Last Name	Notes	E-mail Address	E-mail 2 Address	Home Phone	Mobile Phone	
	// Home Address	Home Street	Home Address PO Box	Home City	Home State	Home Postal Code	
	// Business Phone	Business Address	Business City	Business State	Business Postal Code

	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'First Name') {
			if($trimVal == 'XXXMaryClayton') return;
			$names = camelCapsNameArray($trimVal);
			$client['lname'] = array_pop($names);
			if($names) $client['fname'] = join(' ', $names);
		}
		else if($label == 'Last Name') {
			// e.g., SpencerZiggyHipHop*Colleen
			// pet names end at asterisk, and after that are sitter names
			$parts = explode('*', $trimVal);
			$petNames = camelCapsNameArray($parts[0]);
			if($parts[1]) $notes[] = "Sitters: ".join(', ', camelCapsNameArray($parts[1]));
		}
		else if($label == 'Notes') $notes[] = $trimVal;
		else if($label == 'E-mail Address') $client['email'] = $trimVal;
		else if($label == 'E-mail 2 Address') $client['email2'] = $trimVal;
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Mobile Phone') $client['cellphone'] = $trimVal;
		else if($label == 'Home Street') $client['street1'] = $trimVal;
		else if($label == 'Home Address PO Box') $client['street2'] = $trimVal;
		else if($label == 'Home City') $client['city'] = $trimVal;
		else if($label == 'Home State') $client['state'] = $trimVal;
		else if($label == 'Home Postal Code') $client['zip'] = $trimVal;
		else if($label == 'Business Phone') $client['workphone'] = $trimVal;
		else if($label == 'Business Address') $client['street1'] = $trimVal;
		else if($label == 'Business City') $client['city'] = $trimVal;
		else if($label == 'Business State') $client['state'] = $trimVal;
		else if($label == 'Business Postal Code') $client['zip'] = $trimVal;
	}
	foreach(array('email', 'email2') as $emailKey) {
		if($client[$emailKey] && !isEmailValid($client[$emailKey])) { // see field-utils.php
			$notes[] = "\nInvalid email address: {$client[$emailKey]}";
			unset($client[$emailKey]);
		}
	}
	if($notes) $client['notes'] = join("\n", $notes);	
	
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
	foreach((array)$petNames as $i => $nm) {
		if(!$nm) {
			unset($petNames[$i]);
			continue;
		}
		$petCount++;
		$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
		insertTable('tblpet', $pet, 1);
	}
	$petNames = $petNames ? "pets ".join(', ', $petNames) : "no pets";

	echo "Added client {$client['fname']} {$client['lname']} ($petNames)";
	foreach(explode(',','street1,street2,city,state,zip') as $f) {$addr[]=$client[$f];}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
}

function camelCapsNameArray($name) {
	if(!$name) return array();
	for($i = 0; $i < strlen($name); $i++) {
		if(!$word) $word .= $name[$i];
		else if(ord($name[$i]) >= 65 /* "A" */ && ord($name[$i]) <= 90 /* "Z" */) {
			$words[] = $word;
			$word = $name[$i];
		}
		else $word .= $name[$i];
		if($i + 1 == strlen($name)) $words[] = $word;
	}
	return $words;
}

function handleQuickBooksFromCritterSittersInc($row, $ignore=null) {
	//CUST	NAME	REFNUM	TIMESTAMP	BADDR1(pets, usually)	BADDR2	BADDR3	BADDR4	BADDR5	SADDR1	SADDR2	SADDR3	SADDR4	SADDR5	PHONE1	PHONE2	FAXNUM	EMAIL	NOTE	CONT1	CONT2	CTYPE	TERMS	TAXABLE	SALESTAXCODE	LIMIT	RESALENUM	REP	TAXITEM	NOTEPAD	SALUTATION	COMPANYNAME	FIRSTNAME	MIDINIT	LASTNAME	CUSTFLD1	CUSTFLD2	CUSTFLD3	CUSTFLD4	CUSTFLD5	CUSTFLD6	CUSTFLD7	CUSTFLD8	CUSTFLD9	CUSTFLD10	CUSTFLD11	CUSTFLD12	CUSTFLD13	CUSTFLD14	CUSTFLD15	JOBDESC	JOBTYPE	JOBSTATUS	JOBSTART	JOBPROJEND	JOBEND	HIDDEN	DELCOUNT	PRICELEVEL
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	// THIS DATA FILE CONTAINS VISIT DATA AS WELL AS CLIENT DATA
	// NAME field in visit lines contains a colon.  Ignore such lines
	// When the Employee Headers line is encountered, change the $dataHeaders and
	// redirect to handleQuickBooksFromFamilyPetSittersEmployee
	// ==============================================================================
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	
	
//global $delimiter; echo "[[$delimiter]]<br>";print_r($row);exit;
	
	if(strpos(rowAtHeader($row, 'NAME'), ':')) {
		echo "Skipped visits line for ".substr($row[1], 0, strpos($row[1], ':')).'<br>';
		return;
	}
	else if(rowAtHeader($row, 'FIRSTNAME') && rowAtHeader($row, 'LASTNAME')) {
		$client['fname'] = rowAtHeader($row, 'FIRSTNAME');
		$client['lname'] = rowAtHeader($row, 'LASTNAME');
	}
	else handleLnameCommaFname(rowAtHeader($row, 'NAME'), $client, $fnameKey='fname', $lnameKey='lname');
	
	if(!$client['fname'] || !$client['lname'])
		handleFnameSpaceLname(rowAtHeader($row, 'BADDR1'), $client, $fnameKey='fname', $lnameKey='lname');
	
	
	if(rowAtHeader($row, 'BADDR4')) getCityStateZip(rowAtHeader($row, 'BADDR4'), $client);
	else if(rowAtHeader($row, 'BADDR3')) getCityStateZip(rowAtHeader($row, 'BADDR3'), $client);
	$client['street1'] = rowAtHeader($row, 'BADDR2');
	$cols = 'PHONE1,PHONE2,FAXNUM';
	sortOutPhones($row, $cols, $client, $altPhoneField='FAXNUM', $pats=null);
	
	processEmails(rowAtHeader($row, 'EMAIL'), $client);
	
	$client['notes'] = rowAtHeader($row, 'NOTEPAD');
	
	
	if(!"{$client['fname']}{$client['lname']}") {
		echo "<FONT color=red>No Client name supplied: $rowCount</FONT><P>";
		return;
	}
	else if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	
	if(findClientByName("{$client['fname']} {$client['lname']}")) {
		echo "<font color=red>Client already exists: {$client['fname']} {$client['lname']}</font><p>";
		return;
	}
	
	$clientptr = saveNewClient($client);
	
	//if($emergencyContact) saveClientContact('emergency', $clientptr, $emergencyContact);



	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
if($emergencyContact) echo join(' - ', $emergencyContact);
	echo "<p>";
}


function handleQuickBooksFromItsADogsLifeNY($row, $ignore=null) {
	//CUST	NAME	REFNUM	TIMESTAMP	BADDR1(pets, usually)	BADDR2	BADDR3	BADDR4	BADDR5	SADDR1	SADDR2	SADDR3	SADDR4	SADDR5	PHONE1	PHONE2	FAXNUM	EMAIL	NOTE	CONT1	CONT2	CTYPE	TERMS	TAXABLE	SALESTAXCODE	LIMIT	RESALENUM	REP	TAXITEM	NOTEPAD	SALUTATION	COMPANYNAME	FIRSTNAME	MIDINIT	LASTNAME	CUSTFLD1	CUSTFLD2	CUSTFLD3	CUSTFLD4	CUSTFLD5	CUSTFLD6	CUSTFLD7	CUSTFLD8	CUSTFLD9	CUSTFLD10	CUSTFLD11	CUSTFLD12	CUSTFLD13	CUSTFLD14	CUSTFLD15	JOBDESC	JOBTYPE	JOBSTATUS	JOBSTART	JOBPROJEND	JOBEND	HIDDEN	DELCOUNT	PRICELEVEL
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	// THIS DATA FILE CONTAINS VISIT DATA AS WELL AS CLIENT DATA
	// NAME field in visit lines contains a colon.  Ignore such lines
	// When the Employee Headers line is encountered, change the $dataHeaders and
	// redirect to handleQuickBooksFromFamilyPetSittersEmployee
	// ==============================================================================
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	
	
//global $delimiter; echo "[[$delimiter]]<br>";print_r($row);exit;
	
	if(rowAtHeader($row, 'FIRSTNAME') && rowAtHeader($row, 'LASTNAME')) {
		$client['fname'] = rowAtHeader($row, 'FIRSTNAME');
		$client['lname'] = rowAtHeader($row, 'LASTNAME');
	}
	
	$client['street1'] = rowAtHeader($row, 'BADDR1');
	$client['street2'] = rowAtHeader($row, 'BADDR2');
	if($cityState = rowAtHeader($row, 'BADDR3')) {
		$cityState = array_map('trim', explode(',', $cityState));
		$client['city'] = $cityState[0];
		$client['state'] = $cityState[1];
	}
	$client['zip'] = rowAtHeader($row, 'BADDR4');
	
	$shipAddr = array(
			rowAtHeader($row, 'SADDR1'),
			rowAtHeader($row, 'SADDR2'),
			rowAtHeader($row, 'SADDR3'),
			rowAtHeader($row, 'SADDR4'));
	if(trim(join('', $shipAddr))) {
		$saddr = array();
		foreach($shipAddr as $v) $saddr[] = $v;
		$client['notes'] = "Alt Address: ".join(', ', $saddr);
		
	}
	
	$cols = 'PHONE1,PHONE2,FAXNUM';
	sortOutPhones($row, $cols, $client, $altPhoneField='FAXNUM', $pats=null);
	
	processEmails(rowAtHeader($row, 'EMAIL'), $client);
	
	if(!"{$client['fname']}{$client['lname']}") {
		echo "<FONT color=red>No Client name supplied: $rowCount</FONT><P>";
		return;
	}
	else if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	
	if(findClientByName("{$client['fname']} {$client['lname']}")) {
		echo "<font color=red>Client already exists: {$client['fname']} {$client['lname']}</font><p>";
		return;
	}
	
	$clientptr = saveNewClient($client);
	$petNames = handlePetNames(rowAtHeader($row, 'NAME'));
	//if($emergencyContact) saveClientContact('emergency', $clientptr, $emergencyContact);
	foreach((array)$petNames as $i => $nm) {
		if(!$nm) {
			unset($petNames[$i]);
			continue;
		}
		$petCount++;
		$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
		insertTable('tblpet', $pet, 1);
	}
	$petNames = $petNames ? "pets ".join(', ', $petNames) : "no pets";
	echo "Added client {$client['fname']} {$client['lname']} with $petNames";
	foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
	echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	echo "<p>";
}



function nextAvailablePhone(&$client, &$order) {
	foreach($order as $key)
		if(!$client[$key])
			return $key;
	return 'notes';
}

function handleCritterSittersInc($row) {
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	if(rowAtHeader($row, 'Last Name') == "Weik") {
			echo "Ignoring client Weik..<p>";
			return;
	}
	if(!rowAtHeader($row, 'First Name') || !rowAtHeader($row, 'Last Name')) {
		if(!rowAtHeader($row, 'Address 1')) {
			echo "Bad row: no name info.<p>";
			return;
		}
		else handleFnameSpaceLname(rowAtHeader($row, 'Address 1'), $client, $fnameKey='fname', $lnameKey='lname');
	}
	else {
		$client['fname'] = rowAtHeader($row, 'First Name');
		$client['lname'] = rowAtHeader($row, 'Last Name');
	}
	$client['city'] = rowAtHeader($row, 'City');
	$client['state'] = rowAtHeader($row, 'State');
	$client['zip'] = rowAtHeader($row, 'Zip');
	$clientptr = saveNewClient($client);
	echo "Added client $clientptr: {$client['fname']} {$client['lname']}";
	echo "<p>";
}

function handleFidoFitnessAndPlay($row) {
	// issues: 
	// - All Clients active
//print_r($row);	
	if(!$row[1]) {echo "No row 1"; return;}
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		$upperCase = strtoupper($trimVal);
		if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Main Email') $client['email'] = $trimVal;
		else if($label == "Dog's Name") $petNames = handlePetNames($trimVal);
		else if($label == 'Main Phone') {
			$trimVal = "*$trimVal";
			if(strpos($upperCase, 'WORK')) $client['workphone'] = $trimVal; 
			else if(strpos($upperCase, 'HOME')) $client['homephone'] = $trimVal; 
			else if(strpos($upperCase, 'MOBILE') || strpos($upperCase, 'CELL') || strpos($upperCase, ' CEL')) $client['cellphone'] = $trimVal; 
			else $client['homephone'] = $trimVal;
		}
		else if($label == 'Bill to 2') $client['street1'] = $trimVal; 
		else if($label == 'Customer Type') $notes[] = $trimVal; 
		else if($label == 'Job Description') $notes[] = $trimVal; 
	}
	foreach(array('Alt. Phone', 'Fax') as $header) {
		if($phone = rowAtHeader($row, $header)) {
			$upperCase = strtoupper($phone);
			if(strpos($upperCase, 'HOME'))
				$order = array('homephone', 'workphone', 'cellphone', 'cellphone2');
			else if(strpos($upperCase, 'WORK'))
				$order = array('workphone', 'homephone', 'cellphone2', 'cellphone');
			else if(strpos($upperCase, 'MOBILE') || strpos($upperCase, 'CELL') || strpos($upperCase, ' CEL'))
				$order = array('cellphone', 'cellphone2', 'workphone', 'homephone');
			else 
				$order = array('homephone', 'workphone', 'cellphone', 'cellphone2');
			$key = nextAvailablePhone($client, $order);
			if($key == 'notes') $notes[] = "$header: $phone";
			else $client[$key] = $phone;
		}
	}
	if($addr = rowAtHeader($row, 'Bill to 4')) {
		getCityStateZip($addr, $client);
		$client['street2'] = rowAtHeader($row, 'Bill to 3');
	}
	else getCityStateZip(rowAtHeader($row, 'Bill to 3'), $client);
	$client['street1'] = rowAtHeader($row, 'Bill to 2');

	if($contact = rowAtHeader($row, 'Secondary Contact'))
		$notes[] = "Secondary Contact: $contact";
		
	if($notes)
		$client['notes'] = join("\n", $notes);

	$clientptr = saveNewClient($client);
	
	foreach((array)$petNames as $i => $nm) {
		if(!$nm) {
			unset($petNames[$i]);
			continue;
		}
		$petCount++;
		$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
		insertTable('tblpet', $pet, 1);
	}
	$petNames = $petNames ? "pets ".join(', ', $petNames) : "no pets";
	echo "Added client {$client['fname']} {$client['lname']} with pets $petNames";
	echo "<p>";

}

function handleQuickBooksFromFamilyPetSitters($row, $ignore=null) {
	//CUST	NAME	REFNUM	TIMESTAMP	BADDR1(pets, usually)	BADDR2	BADDR3	BADDR4	BADDR5	SADDR1	SADDR2	SADDR3	SADDR4	SADDR5	PHONE1	PHONE2	FAXNUM	EMAIL	NOTE	CONT1	CONT2	CTYPE	TERMS	TAXABLE	SALESTAXCODE	LIMIT	RESALENUM	REP	TAXITEM	NOTEPAD	SALUTATION	COMPANYNAME	FIRSTNAME	MIDINIT	LASTNAME	CUSTFLD1	CUSTFLD2	CUSTFLD3	CUSTFLD4	CUSTFLD5	CUSTFLD6	CUSTFLD7	CUSTFLD8	CUSTFLD9	CUSTFLD10	CUSTFLD11	CUSTFLD12	CUSTFLD13	CUSTFLD14	CUSTFLD15	JOBDESC	JOBTYPE	JOBSTATUS	JOBSTART	JOBPROJEND	JOBEND	HIDDEN	DELCOUNT	PRICELEVEL
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	// THIS DATA FILE CONTAINS EMPLOYEE DATA AS WELL AS CLIENT DATA
	// When the Employee Headers line is encountered, change the $dataHeaders and
	// redirect to handleQuickBooksFromFamilyPetSittersEmployee
	// ==============================================================================
	if($row[0] == '!EMP') {
		$dataHeaders = array_map('trim', $row);
		echo "<hr>Switching over to Employees<hr>";
		return;
	}
	else if($row[0] == 'EMP') return handleQuickBooksFromFamilyPetSittersSITTER($row, $ignore);
	// ==============================================================================
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d', rowAtHeader($row, 'TIMESTAMP')));
	if(strpos(strtoupper(rowAtHeader($row, 'BADDR4')), "North Olmsted") === 0) {
		getCityStateZip(rowAtHeader($row, 'BADDR4'), $client);
		$client['street1'] = rowAtHeader($row, 'BADDR3');
		handleFnameSpaceLname(rowAtHeader($row, 'BADDR2'), $client, $fnameKey='fname2', $lnameKey='lname2');

		//$petcol = explode('-', (array)rowAtHeader($row, 'BADDR1'));
	}
	else  {
		getCityStateZip(rowAtHeader($row, 'BADDR3'), $client);
		$client['street1'] = rowAtHeader($row, 'BADDR2');
	}

	$client['homephone'] = rowAtHeader($row, 'PHONE1');
	$client['workphone'] = rowAtHeader($row, 'PHONE2');
	if(rowAtHeader($row, 'FAXNUM')) $client['cellphone'] = rowAtHeader($row, 'FAXNUM');
	$client['email'] = rowAtHeader($row, 'EMAIL');
	$emergencyContact = array('name'=>rowAtHeader($row, 'CONT1'), 'homephone'=>rowAtHeader($row, 'CONT2'));
	
	$client['notes'] = rowAtHeader($row, 'TERMS');
	$client['active'] = rowAtHeader($row, 'HIDDEN') == 'Y' ? 0 : 1;
	
	// NAME IS TRICKY...
	if(strpos((string)rowAtHeader($row, 'NAME'), ','))
		handleLnameCommaFname(rowAtHeader($row, 'NAME'), $client, $fnameKey='fname', $lnameKey='lname');
	else if(rowAtHeader($row, 'FIRSTNAME') && rowAtHeader($row, 'LASTNAME')) {
		$client['fname'] = rowAtHeader($row, 'FIRSTNAME');
		$client['lname'] = rowAtHeader($row, 'LASTNAME');
	}
	else if(rowAtHeader($row, 'BADDR1'))
		handleFnameSpaceLname(rowAtHeader($row, 'BADDR1'), $client, $fnameKey='fname', $lnameKey='lname');
	
	if(!"{$client['fname']}{$client['lname']}") {
		echo "<FONT color=red>No Client name supplied: $rowCount</FONT><P>";
		return;
	}
	else if(!$client['fname']) $client['fname'] = 'UNKNOWN';
	
	if(findClientByName("{$client['fname']} {$client['lname']}")) {
		echo "<font color=red>Client already exists: {$client['fname']} {$client['lname']}</font><p>";
		return;
	}
	
	$clientptr = saveNewClient($client);
	
	if($emergencyContact) saveClientContact('emergency', $clientptr, $emergencyContact);



	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
if($emergencyContact) echo join(' - ', $emergencyContact);
	echo "<p>";
}


function handleQuickBooksFromFamilyPetSittersSITTER($row, $ignore=null) {
	require_once "provider-fns.php";
	//!EMP	NAME	REFNUM	TIMESTAMP	INIT	ADDR1	ADDR2	ADDR3	ADDR4	ADDR5	SSNO	PHONE1	PHONE2	EMAIL	NOTEPAD	FIRSTNAME	MIDINIT	LASTNAME	SALUTATION	CUSTFLD1	CUSTFLD2	CUSTFLD3	CUSTFLD4	CUSTFLD5	CUSTFLD6	CUSTFLD7	CUSTFLD8	CUSTFLD9	CUSTFLD10	CUSTFLD11	CUSTFLD12	CUSTFLD13	CUSTFLD14	CUSTFLD15	HIDDEN

	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$sitter =  array('active'=>(rowAtHeader($row, 'HIDDEN') == 'Y' ? 0 : 1));
	if(rowAtHeader($row, 'ADDR4')) {
		getCityStateZip(rowAtHeader($row, 'ADDR4'), $sitter);
		$sitter['street2'] = rowAtHeader($row, 'ADDR3');
		$sitter['street1'] = rowAtHeader($row, 'ADDR2');

		//$petcol = explode('-', (array)rowAtHeader($row, 'BADDR1'));
	}
	else  {
		getCityStateZip(rowAtHeader($row, 'ADDR3'), $sitter);
		$sitter['street1'] = rowAtHeader($row, 'ADDR2');
	}
	$sitter['homephone'] = rowAtHeader($row, 'PHONE1');
	$sitter['workphone'] = rowAtHeader($row, 'PHONE2');
	$sitter['email'] = rowAtHeader($row, 'EMAIL');
	$sitter['taxid'] = rowAtHeader($row, 'SSNO');
	$sitter['nickname'] = rowAtHeader($row, 'INIT');
	handleLnameCommaFname(rowAtHeader($row, 'NAME'), $sitter, $fnameKey='fname', $lnameKey='lname');
	
	$client['active'] = rowAtHeader($row, 'HIDDEN') == 'Y' ? 0 : 1;
	for($i=1;$i<15;$i++) if(rowAtHeader($row, "CUSTFLD$i")) 
		$sitter['notes'][] = rowAtHeader($row, "CUSTFLD$i");
	if($sitter['notes']) $sitter['notes'] = join("\n", $sitter['notes']);

	$providerid = insertTable('tblprovider', $sitter, 1);
	logChange($providerid, 'tblprovider', 'c', $note='.');




	echo "Added sitter {$sitter['fname']} {$sitter['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>';//.print_r($client, 1);
	echo "<p>";
}



function handlAPassion4PetsAKAHouseCallsPetSitting($row) {
	// issues: 
	// - All Clients active
//print_r($row);	
	if(!$row[1]) {echo "No row 1"; return;}
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'fname') $client['fname'] = $trimVal;
		else if($label == 'lname') $client['lname'] = $trimVal;
		else if($label == 'street1') $client['street1'] = $trimVal; 
		else if($label == 'city') $client['city'] = $trimVal; 
		else if($label == 'state') $client['state'] = $trimVal; 
		else if($label == 'zip') $client['zip'] = $trimVal;
		else if($label == 'homephone') $client['homephone'] = $trimVal; 
		else if($label == 'workphone') $client['workphone'] = $trimVal; 
		else if($label == 'cellphone') $client['cellphone'] = $trimVal; 
		else if($label == 'cellphone2') $client['cellphone2'] = $trimVal; 
	}

	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
	echo "<p>";

}



function handlJordansPetCare($row) {
	// issues: 
	// - All Clients active
//print_r($row);	
	if(!$row[1]) {echo "No row 1"; return;}
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Email') $badEmails = processEmails($trimVal, $client, $pats);
		else if($label == 'Acct First') $client['fname'] = $trimVal;
		else if($label == 'Acct Last') $client['lname'] = $trimVal;
		else if($label == 'Phone') $client['homephone'] = $trimVal; 
		else if($label == 'Address') $client['street1'] = $trimVal; 
		else if($label == 'City') $client['city'] = $trimVal; 
		else if($label == 'State') $client['state'] = $trimVal; 
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Pet(s) First') $petNames = handlePetNames($trimVal);
	}

	$clientptr = saveNewClient($client);
	foreach((array)$petNames as $i => $nm) {
		if(!$nm) {
			unset($petNames[$i]);
			continue;
		}
		$petCount++;
		$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
		insertTable('tblpet', $pet, 1);
	}
	$petNames = $petNames ? "pets ".join(', ', $petNames) : "no pets";
	echo "Added client {$client['fname']} {$client['lname']} with pets $petNames";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}

function handleComfyCozyPet($row) {
	// issues: 
	// - All Clients active
//print_r($row);	
	if(!$row[1]) {echo "No row 1"; return;}
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Email') $badEmails = processEmails($trimVal, $client, $pats);
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Phone') $client['homephone'] = $trimVal; 
		else if($label == 'Alt. Phone') $client['cellphone2'] = $trimVal; 
		else if($label == 'Street1') $client['street1'] = $trimVal; 
		else if($label == 'City') $client['city'] = $trimVal; 
		else if($label == 'State') $client['state'] = $trimVal; 
		else if($label == 'Zip') $client['zip'] = $trimVal; 
	}

	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}

function handleGMailGoogleCSV($row) {
	// issues: 
	// - All Clients active
//print_r($row);	
	if(!$row[1]) {echo "No row 1 =========================<p>"; return;}
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Given Name') $client['fname'] = $trimVal; 
		else if($label == 'Family Name') $client['lname'] = $trimVal; 
		else if($label == 'Notes') $petNames = handlePetNames($trimVal);
		else if($label == 'E-mail 1 - Value') $client['email'] = $trimVal; 
		else if($label == 'E-mail 2 - Value') $client['email2'] = $trimVal; 
		
		else if($label == 'Phone 1 - Type') $phone1type = strtoupper($trimVal); 
		else if($label == 'Phone 1 - Value') $phone1 = $trimVal; 
		else if($label == 'Phone 2 - Type') $phone2type = strtoupper($trimVal); 
		else if($label == 'Phone 2 - Value') $phone2 = $trimVal;
		
		else if($label == 'Address 1 - Street') $client['street1'] = $trimVal; 
		else if($label == 'Address 1 - PO Box') $client['street1'] = $trimVal; 
		else if($label == 'Address 1 - City') $client['city'] = $trimVal; 
		else if($label == 'Address 1 - Region') $client['state'] = $trimVal; 
		else if($label == 'Address 1 - Postal Code') $client['zip'] = $trimVal; 
	}
	
	if($email1 && !isEmailValid($email1)) { // see field-utils.php
		$badEmails[] = $email1;
		$email1 = null;
	}
	if($email2 && !isEmailValid($email2)) { // see field-utils.php
		$badEmails[] = $email2;
		$email2 = null;
	}
	if($phone1) {
		if(strpos($phone1type, 'WORK') !== FALSE) $phone1type = 'workphone';
		else if(strpos($phone1type, 'MOBILE') !== FALSE) $phone1type = 'cellphone';
		else if(strpos($phone1type, 'OTHER') !== FALSE) $phone1type = 'cellphone2';
		else $phone1type = 'homephone';
		$client[$phone1type] = "*$phone1";
	}
	if($phone2) {
		if(strpos($phone2type, 'WORK') !== FALSE) $phone2type = 'workphone';
		else if(strpos($phone2type, 'MOBILE') !== FALSE) $phone2type = 'cellphone';
		else if(strpos($phone2type, 'OTHER') !== FALSE) $phone2type = 'cellphone2';
		else $phone2type = 'homephone';
		$client[$phone2type] = "*$phone2";
	}

	$clientptr = saveNewClient($client);
	
	// check for null petnames
	foreach((array)$petNames as $i => $nm) {
		if(strpos($nm, '_x000D_') !== FALSE) $nm = substr($nm, 0, strpos($nm, '_x000D_'));
		if(!$nm) {
			unset($petNames[$i]);
			continue;
		}
		$petCount++;
		$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
		insertTable('tblpet', $pet, 1);
	}
	$petNames = $petNames ? "pets ".join(', ', $petNames) : "no pets";
	echo "Added client {$client['fname']} {$client['lname']} with $petNames";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}

function handleMyDesertDog($row) {
	// issues: 
	// - All Clients active
//print_r($row);	
	if(!$row[1]) {echo "No row 1"; return;}
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'email') $badEmails = processEmails($trimVal, $client, $pats);
		else if($label == 'name') handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		else if($label == 'homephone') $client['homephone'] = $trimVal; 
		else if($label == 'street1') $client['street1'] = $trimVal; 
		else if($label == 'street2') $client['street2'] = $trimVal; 
		else if($label == 'city') $client['city'] = $trimVal; 
		else if($label == 'state') $client['state'] = $trimVal; 
		else if($label == 'zip') $client['zip'] = $trimVal; 
	}

	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}





function handleK9companionps($row) {
	// issues: 
	// - All Clients active
//print_r($row);	
	if(!$row[1]) {echo "No row 1"; return;}
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Email') $badEmails = processEmails($trimVal, $client, $pats);
		else if($label == 'Billing Address') handleAddress($row[array_search($label, $dataHeaders)], $client);
		else if($label == 'First') $client['fname'] = $trimVal;
		else if($label == 'Last') $client['lname'] = $trimVal;
		else if($label == 'Phone Numbers') processPhones($trimVal, $client, 'PHONE:|homephone||MOBILE:|cellphone');
		else if($label == 'Pet Names') $petNames = handlePetNames($trimVal);
	}

	if($client['notes'])
		$client['notes'] = join("\n", $client['notes']);
	$clientptr = saveNewClient($client);
	// check for null petnames
	foreach((array)$petNames as $i => $nm) {
		if(!$nm) {
			unset($petNames[$i]);
			continue;
		}
		$petCount++;
		$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
		insertTable('tblpet', $pet, 1);
	}
	$petNames = $petNames ? "pets ".join(', ', $petNames) : "no pets";
	echo "Added client {$client['fname']} {$client['lname']} with pets $petNames";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}

function processPhones($trimval, &$client, $pats) {// handles multiple phones
	if(!$trimval) return;
	$n = 0;
	foreach(decompose($trimval, "\n") as $x) {
		foreach(decompose($x, ",") as $x1) {
			if($x1)	{
				$keys[] = "p$n";
				$phones["p$n"] = $x1;
				$n++;
			}
		}
	}
	$cols = array();
	if($phones) sortOutPhones($phones, $cols, $client, null, $pats);
}

function processEmails($trimval, &$client) { // handles multiple emails
	if(!$trimval) return array();
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
	$badEmails = array();
	foreach($emails as $email) {
		if(!isEmailValid($email))
			$badEmails[] = $email;
		else if(!$client['email']) $client['email'] = $email;
		else if(!$client['email2']) $client['email2'] = $email;
		else $client['notes'][] = "Other email: $email";
	}
	return $badEmails;
}
	
function handleFourPawsMetropolitan($row) {
	// issues: 
	// - All CLients active
	// - Customer type = entry type -- put in Garage / Gate Code
//print_r($row);	
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	if(trim($row[array_search('First Name', $dataHeaders)]) 
			&& trim($row[array_search('Last Name', $dataHeaders)])) {
		$client['lname'] = trim($row[array_search('Last Name', $dataHeaders)]);
		$client['fname'] = trim($row[array_search('First Name', $dataHeaders)]);
	}
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Customer' && !$client['lname']) 
			handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Bill to 2') {
			$client['street1'] = $trimVal;
		}
		else if($label == 'Bill to 3') {
			getCityStateZip($row[array_search('Bill to 3', $dataHeaders)], $client);
			//echo print_r($client, 1).'<p>';
		}
		else if($label == 'Customer Type') 
			$client['garagegatecode'] = $trimVal;
		else if($label == 'Alt. Contact') 
			$client['fname2'] = $trimVal;
		else if($label == 'Note') 
			$client['notes'][] = $trimVal;
	}
	sortOutPhones($row, 'Phone,Fax,Alt. Phone', $client, 'Alt. Phone');

	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	if($client['notes'])
		$client['notes'] = join("\n", $client['notes']);
	$clientptr = saveNewClient($client);
	if($customFields) 
		
	echo "Added client {$client['fname']} {$client['lname']}";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}



function handleWholePetsAustin($row) {
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>(rowAtHeader($row, 'Active') == 'yes' ? 1 : 0));
	handleLnameCommaFname(rowAtHeader($row, 'NAME'), $client, $fnameKey='fname', $lnameKey='lname');
	if($addr = rowAtHeader($row, 'ADDRESS'))	{
		$end = strpos($addr, ',') !== FALSE ? strpos($addr, ',') : strlen($addr);
		$client['street1'] = substr($addr, 0, $end);
	}
	$client['city'] = 'Austin';
	$client['state'] = 'TX';
	$client['zip'] = rowAtHeader($row, 'ZIP');
	$client['homephone'] = rowAtHeader($row, 'PHONE');
	if($email = rowAtHeader($row, 'EMAIL'))	{
		$end = strpos($email, ',') !== FALSE ? strpos($email, ',') : strlen($email);
		$client['email'] = substr($email, 0, $end);
	}
	$petNames = handlePetNames(rowAtHeader($row, 'PETS'));
	$allFields = getCustomFields();
	if(!$allFields['custom1'])
		foreach(explode(',', 'Start,Neighborhood,Heard about,Facebook') as $i => $nm)
			setPreference('custom'.($i+1), "$nm|1|oneline|0");
	$allFields = getCustomFields();
	$custFields['custom1'] = rowAtHeader($row, 'START');
	$custFields['custom2'] = rowAtHeader($row, 'NEIGHBORHOOD');
	$custFields['custom3'] = rowAtHeader($row, 'HEARD ABOUT');
	$custFields['custom4'] = rowAtHeader($row, 'facebook');
	$client['notes'] = rowAtHeader($row, 'NOTES');
	$key = rowAtHeader($row, 'KEY ON FILE') == 'yes' ? 'safe' : 'client';
	
	$clientptr = saveNewClient($client);
	foreach($custFields as $k => $v)
		doQuery("REPLACE relclientcustomfield (clientptr, fieldname, value) VALUES ($clientptr, '$k', '".mysqli_real_escape_string($v)."')", 1);
			
	// check for null petnames
	foreach($petNames as $i => $nm) {
		if(!$nm) {
			unset($petNames[$i]);
			continue;
		}
		$petCount++;
		$pet = array('ownerptr'=>$clientptr, 'name'=>$nm, 'active'=>1);
		insertTable('tblpet', $pet, 1);
	}
	echo "<font>Created ".rowAtHeader($row, 'NAME')." with ".join(', ', $petNames)."</font><br>";
}


function TESTHomepetzPets($row, $ignore=null) {
	global $customFields, $petFields;
	if(!$petFields) $petFields = getCustomFields(true, false, getPetCustomFieldNames());
	//label|active|onelineORtextORboolean|visitsheet|clientvisible
	
	$cname = mysqli_real_escape_string(rowAtHeader($row, 'Customer'));
	$client = fetchRow0Col0("SELECT clientid FROM tblclient WHERE CONCAT_WS(' ', fname, lname) = '$cname'");
	if(!$client) {
		echo "<font color=red>Could not find ".rowAtHeader($row, 'Customer')."</font><br>";
		return;
	}
	if($petname = mysqli_real_escape_string(rowAtHeader($row, 'Last Name'))) {
		if(!($petid = fetchRow0Col0("SELECT petid FROM tblpet WHERE ownerptr = $client AND name = '$petname' LIMIT 1"))) {
			echo "<font color=red>PET NOT FOUND: ($petname) for ".rowAtHeader($row, 'Customer')."</font><br>";
		}
		else echo "Found ($petname) for ".rowAtHeader($row, 'Customer')."<br>";
	}

}


function handleHomepetzPets($row, $ignore=null) {
	global $customFields, $petFields;
	if(!$petFields) $petFields = getCustomFields(true, false, getPetCustomFieldNames());
	//label|active|onelineORtextORboolean|visitsheet|clientvisible
	
	$cname = mysqli_real_escape_string(rowAtHeader($row, 'Customer'));
	$client = fetchRow0Col0("SELECT clientid FROM tblclient WHERE CONCAT_WS(' ', fname, lname) = '$cname'");
	if(!$client) {
		echo "<font color=red>Could not find ".rowAtHeader($row, 'Customer')."</font><br>";
		return;
	}
	if(rowAtHeader($row, 'Description')) $notes[] = rowAtHeader($row, 'Description');
	if(rowAtHeader($row, 'Notes')) $notes[] = rowAtHeader($row, 'Notes');
	if(rowAtHeader($row, 'Other Notes')) $notes[] = rowAtHeader($row, 'Other Notes');
	foreach($customFields as $custname => $fld) {
		$val = rowAtHeader($row, $fld[0]);
		if($fld[2] == 'boolean') {$pairs[$custname] = $val == 'true' ? 1 : '0';}
		else if($val) $pairs[$custname] = $val;
	}
	echo "PAIRS: ".rowAtHeader($row, 'Customer').': '.print_r($pairs, 1)."<br>";
	saveClientCustomFields($client, $pairs, $pairsOnly=true);
	// handle notes
	//if($notes)
	//	updateTable('tblclient', array('notes'=> join("\n", $notes)), "clientid = $client", 1);
	
	/*if($clinicname = rowAtHeader($row, 'Preferred Vet')) {
			$clinicptr = findClinicByName($clinicname);
			if(!$clinicptr)
				$clinicptr = insertTable('tblclinic', array('clinicname' => $clinicname), 1);
			$client['clinicptr'] = $clinicptr;
	}*/	
	$sexes = array('Male'=>'m', 'Female'=>'f');
	if($petname = mysqli_real_escape_string(rowAtHeader($row, 'Last Name'))) {
		if(!($petid = fetchRow0Col0("SELECT petid FROM tblpet WHERE ownerptr = $client AND name = '$petname' LIMIT 1"))) {
			$petNames[] = rowAtHeader($row, 'Last Name');
			$pet = array(
				'ownerptr'=>$client,
				'name'=>rowAtHeader($row, 'Last Name'),
				'type'=>rowAtHeader($row, 'Type'),
				'breed'=>rowAtHeader($row, 'Breed'),
				'sex'=>$sexes[rowAtHeader($row, 'Sex')],
				'description'=>rowAtHeader($row, 'Colour Description'));
			$petid = insertTable('tblpet', $pet, 1);
		}
	}
	if($petid) {
		$pairs = array();
		foreach($petFields as $custname => $fld) {
			if($fld[0] == 'AM Feed') $fld[0] = 'Morning Feed';
			if($fld[0] == 'PM Feed') $fld[0] = 'Evening Feed';
			$val = rowAtHeader($row, $fld[0]);
			if($fld[2] == 'boolean') {$pairs[$custname] = $val == 'true' ? 1 : '0';}
			else if($val) $pairs[$custname] = $val;
		}
		savePetCustomFields($petid, $pairs, null, $pairsOnly=true);
	}

	echo "<font>Updated ".rowAtHeader($row, 'Customer')." with ".join(', ', $petNames)."</font><br>";

}



function handleHomepetz($row, $ignore=null) {
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1);
	$client['fname'] = rowAtHeader($row, 'First Name');
	$client['lname'] = rowAtHeader($row, 'Last Name');
	$client['fname2'] = rowAtHeader($row, 'Alt First Name');
	$client['lname2'] = rowAtHeader($row, 'Alt Last Name');
	$client['email'] = rowAtHeader($row, 'Email 1');
	$client['homephone'] = rowAtHeader($row, 'Home Phone');
	$client['cellphone'] = rowAtHeader($row, 'Cell Phone');
	$client['cellphone2'] = rowAtHeader($row, 'Alt Phone');
	$client['street1'] = rowAtHeader($row, 'Address');
	$client['city'] = rowAtHeader($row, 'Address 2');
	if(rowAtHeader($row, 'Emergency Contact')) {
		$emergencyContact = array('name'=>rowAtHeader($row, 'Emergency Contact'), 'homephone'=>rowAtHeader($row, 'EC1 Phone'));
	}
	if(rowAtHeader($row, 'Emergency Contact 2')) {
		$neighbor = array('name'=>rowAtHeader($row, 'Emergency Contact 2'), 'homephone'=>rowAtHeader($row, 'EC2 Phone'));
	}
	$clientptr = saveNewClient($client);
	if($emergencyContact) saveClientContact('emergency', $clientptr, $emergencyContact);
	if($neighbor) saveClientContact('neighbor', $clientptr, $neighbor);
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
	echo "<p>";
}

function sortOutPhones(&$row, $cols, &$client, $altPhoneField=null, $pats=null) {
	// $altPhoneField - normally, this should be the fieldname to go to cell2
	// but when form is "*FieldName" use consider this field as a candidate for spillover to cellphone2
	require_once "gui-fns.php";
	global $dataHeaders;
	if($cols && !is_array($cols)) $cols = explode(',', $cols);
	if($altPhoneField && $num = rowAtHeader($row, $altPhoneField)) {
		$client['cellphone2'] = $num;
		unset($row[headerIndex($altPhoneField)]);
	}
	if(!$cols) foreach($row as $k => $v) $candidates[$k] = $v;
	else foreach($cols as $col)
		if(rowAtHeader($row, $col)) 
			$candidates[] = rowAtHeader($row, $col);
	if(!$pats) $pats = '(H)|homephone||(C)|cellphone||(W)|workphone||(H)|homephone||(O)|workphone||H|homephone||C|cellphone||W|workphone||O|workphone||UNUSED|cellphone2';
	$pats = explodePairsLine($pats);
	foreach((array)$candidates as $i => $num) {
		foreach($pats as $pat => $dest) {
			if(strpos(strtoupper($num), $pat) !== FALSE) {
				if(!$client[$dest]) {
					if(strpos(strtoupper($num), $pat) === 0) $num = substr($num, strlen($pat));
					$client[$dest] = $num;
				}
				else if(!$client['cellphone2']) $client['cellphone2'] = $num;
				else $extras[] = $num;
				unset($candidates[$i]);
				break;
			}
		}
	}
	// foreach remaining candidate, try shoehorning it
	foreach((array)$candidates as $i => $num) {
		foreach(explode(',', 'homephone,cellphone,workphone,cellphone2') as $fld)
			if(!$client[$fld]) {
				$client[$fld] = $num;
				unset($candidates[$i]);
				break;
			}
	}
	foreach((array)$extras as $extra)
		$client['notes'][] = $extra;
	foreach((array)$candidates as $extra)
		$client['notes'][] = $extra;
}

			

function handlePrancearound($row, $ignore=null) {
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1);
	handleLnameCommaFname(rowAtHeader($row, 'Client'), $client, $fnameKey='fname', $lnameKey='lname');
	if($email = rowAtHeader($row, 'Email'))	{
		$end = strpos($email, ' ') !== FALSE ? strpos($email, ' ') : strlen($email);
		$client['email'] = substr($email, 0, $end);
		if($space) $notes[] = "Email: $email";
	}
	$phones = explode("\n", str_replace("\r", '', rowAtHeader($row, 'Phone Numbers')));
	foreach($phones as $phone) {
		if(!$phone) continue;
		$fld = strpos($phone, "Mobile") === 0 ? 'cellphone' : 'homephone';
		$client[$fld] = substr($phone, strpos($phone, '('));
	}
	if($address = explode("\n", str_replace("\r", '', rowAtHeader($row, 'Billing Address')))) {
		$client['street1'] = $address[0];
		$add = array();
		getCityStateZip($address[1], $add);
		if(!$add['state']) {
			$cityState = explode(' ', $add['city']);
			$add['city'] = $cityState[0];
			$add['state'] = $cityState[1];
		}
		$client['city'] = $add['city'];
		$client['state'] = $add['state'];
			
	}
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
	echo "<p>";
	
}

function handlePupecise($row, $ignore=null) { // PetSitClick Pet Sit Click
//Customer_Name Address_line_1	Address_line_2	City	State_or_Province	Country	Postal_ZIP	Home_Phone	Work_Phone
// Email_Address	Mobile_Phone	Notes	Contact_Name (pets -- ignore) Client_Since
// Billing_address_line_1	Billing_address_line_2	Bill_City	Bill_State_or_Province	Bill_Country	Bill_Postal_ZIP
// Terms	Payment_type Acquired_Customer Invoice_delivery Invoice_delivery
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$setupdate = rowAtHeader($row, 'Client_Since') ? date('Y-m-d', strtotime(rowAtHeader($row, 'Client_Since'))) : date('Y-m-d');
	$client =  array('active'=>1, 'setupdate'=>$setupdate);
	handleFnameSpaceLname(rowAtHeader($row, 'Customer_Name'), $client, $fnameKey='fname', $lnameKey='lname');
	$client['street1'] = rowAtHeader($row, 'Address_line_1');
	$client['street2'] = rowAtHeader($row, 'Address_line_2');
	$client['city'] = rowAtHeader($row, 'City');
	$client['state'] = rowAtHeader($row, 'State_or_Province');
	$client['zip'] = rowAtHeader($row, 'Postal_ZIP');
	
	if($email = rowAtHeader($row, 'Email_Address'))	{
		$end = strpos($email, ' ') !== FALSE ? strpos($email, ' ') : strlen($email);
		$client['email'] = substr($email, 0, $end);
		if($space) $notes[] = "Email: $email";
	}

	$client['homephone'] = rowAtHeader($row, 'Home_Phone');
	$client['workphone'] = rowAtHeader($row, 'Work_Phone');
	$client['cellphone'] = rowAtHeader($row, 'Mobile_Phone');
	
	$client['mailstreet1'] = rowAtHeader($row, 'Billing_address_line_1');
	$client['mailstreet2'] = rowAtHeader($row, 'Billing_address_line_2');
	$client['mailcity'] = rowAtHeader($row, 'Bill_City');
	$client['mailstate'] = rowAtHeader($row, 'Bill_State_or_Province');
	$client['mailzip'] = rowAtHeader($row, 'Bill_Postal_ZIP');
	
	if($v = rowAtHeader($row, 'Notes')) $notes[] = $v;
	foreach(explode(',', 'Terms,Payment_type,Invoice_delivery,cust1,cust2') as $k)
		if($v = rowAtHeader($row, $k)) $notes[] = "$k: $v";
	
	if($notes) $client['notes'] = join("\n", $notes);

	
	if(findClientByName(rowAtHeader($row, 'Customer_Name'))) {
		echo "<font color=red>Skipping: {$client['fname']} {$client['lname']}</font><p>";
		return;
	}
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
	echo "<p>";

}


function handleQuickBooksFromPawfectPetServices($row, $ignore=null) {
//Customer		Alt. Phone		Contact		Phone		Alt. Contact		Street1		City		State		Zip		Email																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																													
																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																					
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	handleLnameCommaFname(rowAtHeader($row, 'Customer'), $client, $fnameKey='fname', $lnameKey='lname');
	if($phone = rowAtHeader($row, 'Phone')) {
		$uphone = strtoupper($phone);
		$phone = "*$phone";
		$primary = 1;
		if(strpos($uphone, 'cel') !== FALSE) $client['cellphone'] = $phone;
		else if(strpos($uphone, 'work') !== FALSE) $client['workphone'] = $phone;
		else $client['homephone'] = $phone;
	}
	if($ph = rowAtHeader($row, 'Alt. Phone')) {
		$uphone = strtoupper($ph);
		$ph = $primary ? $ph : "*$ph";
		$primary = 1;
		if(strpos($uphone, 'work') !== FALSE) $client['workphone'] = $ph;
		else if(strpos($uphone, 'cell') !== FALSE) {
			if(!$client['cellphone']) $client['cellphone'] = $ph;
			else if(!$client['cellphone2']) $client['cellphone2'] = $ph;
			else if(!$client['fax']) $client['fax'] = $ph;
		}
		else { // home
			if(!$client['homephone']) $client['homephone'] = $ph;
			else if(!$client['workphone']) $client['workphone'] = $ph;
			else if(!$client['cellphone']) $client['cellphone'] = $ph;
			else if(!$client['cellphone2']) $client['cellphone2'] = $ph;
			else if(!$client['fax']) $client['fax'] = $ph;
			else $notes[] = $ph;
		}
	}
	
	if($ph = rowAtHeader($row, 'Alt. Contact')) {
		$uphone = strtoupper($ph);
		$ph = $primary ? $ph : "*$ph";
		$primary = 1;
		if(strpos($uphone, 'work') !== FALSE) $client['workphone'] = $ph;
		else if(strpos($uphone, 'cell') !== FALSE) {
			if(!$client['cellphone']) $client['cellphone'] = $ph;
			else if(!$client['cellphone2']) $client['cellphone2'] = $ph;
			else if(!$client['fax']) $client['fax'] = $ph;
		}
		else { // home
			if(!$client['homephone']) $client['homephone'] = $ph;
			else if(!$client['workphone']) $client['workphone'] = $ph;
			else if(!$client['cellphone']) $client['cellphone'] = $ph;
			else if(!$client['cellphone2']) $client['cellphone2'] = $ph;
			else if(!$client['fax']) $client['fax'] = $ph;
			else $notes[] = "Alt. Contact $ph";
		}
	}
	
	if($email = rowAtHeader($row, 'Email'))	{
		$end = strpos($email, ' ') !== FALSE ? strpos($email, ' ') : strlen($email);
		$client['email'] = substr($email, 0, $end);
		if($space) $notes[] = "Email: $email";
	}

	$client['street1'] = rowAtHeader($row, 'Street1');
	$client['city'] = rowAtHeader($row, 'City');
	$client['state'] = rowAtHeader($row, 'State');
	$client['zip'] = rowAtHeader($row, 'Zip');



	if($notes) $client['notes'] = join("\n", $notes);
	
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
	echo "<p>";

}

function fixStrutNMutt($row, $ignore=null) {
//Active Status,	Customer	Balance,	Balance Total,	Company	,Mr, Mrs,	First Name	M.I.	Last Name	Contact	Phone	Fax	
//Alt. Phone	Alt. Contact	Email	Bill to 1	Bill to 2	Bill to 3	Bill to 4	Bill to 5	Ship to 1	Ship to 2	Ship to 3	Ship to 4	
//Ship to 5	Customer Type	Terms	Rep	Sales Tax Code	Tax item	Resale Num	Account No.	Credit Limit	Job Status	Job Type	
//Job Description	Start Date	Projected End	End Date	Note																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																								
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$fname = rowAtHeader($row, 'First Name');
	$lname = rowAtHeader($row, 'Last Name');
	if($email = rowAtHeader($row, 'Email'))	{
		updateTable('tblclient', array('email'=>$email), 
								"fname = '".mysqli_real_escape_string($fname)."' AND lname = '".mysqli_real_escape_string($lname)."'", 1);
		echo "Added [$email] to client $fname $lname'<P>";
	}
}


function handleQuickBooksFromStrutNMutt($row, $ignore=null) {
//Active Status,	Customer	Balance,	Balance Total,	Company	,Mr, Mrs,	First Name	M.I.	Last Name	Contact	Phone	Fax	
//Alt. Phone	Alt. Contact	Email	Bill to 1	Bill to 2	Bill to 3	Bill to 4	Bill to 5	Ship to 1	Ship to 2	Ship to 3	Ship to 4	
//Ship to 5	Customer Type	Terms	Rep	Sales Tax Code	Tax item	Resale Num	Account No.	Credit Limit	Job Status	Job Type	
//Job Description	Start Date	Projected End	End Date	Note																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																								
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>(rowAtHeader($row, 'Active Status') == 'Active'), 'setupdate'=>date('Y-m-d'));
	$client['fname'] = rowAtHeader($row, 'First Name');
	$client['lname'] = rowAtHeader($row, 'Last Name');
	if($phone = rowAtHeader($row, 'Phone')) {
		$phone = "*$phone";
		if(strpos($phone, 'cel') !== FALSE) $client['cellphone'] = $phone;
		else if(strpos($phone, 'work') !== FALSE) $client['workphone'] = $phone;
		else $client['homephone'] = $phone;
	}
	if($fax = rowAtHeader($row, 'Fax')) {
		if(strpos($fax, 'home') !== FALSE) $client['homephone'] = $fax;
		else if(strpos($fax, 'cell') !== FALSE && $client['cellphone']) $client['fax'] = $fax;
		else if(strpos($fax, 'cell') !== FALSE) $client['cellphone'] = $fax;
	}
	
	$client['fname2'] = rowAtHeader($row, 'Alt. Contact');
	$client['cellphone2'] = rowAtHeader($row, 'Alt. Phone');

	if($email = rowAtHeader($row, 'Email'))	{
		$end = strpos($email, ' ') === FALSE ? strlen($email) : strpos($email, ' ');
		$client['email'] = substr($email, 0, end);
		if($space) $notes[] = "Email: $email";
	}
	if($petnames = (rowAtHeader($row, 'Bill to 4') ? rowAtHeader($row, 'Bill to 2') : null))
		$petnames = handlePetNames($petnames);
		
	if(rowAtHeader($row, 'Bill to 5')) {
		getCityStateZip(rowAtHeader($row, 'Bill to 5'), $client);
		$client['street1'] = rowAtHeader($row, 'Bill to 4');
	}
	else if(rowAtHeader($row, 'Bill to 4')) {
		getCityStateZip(rowAtHeader($row, 'Bill to 4'), $client);
		$client['street1'] = rowAtHeader($row, 'Bill to 3');
	}
	else if(rowAtHeader($row, 'Bill to 3')) {
		getCityStateZip(rowAtHeader($row, 'Bill to 3'), $client);
		$client['street1'] = rowAtHeader($row, 'Bill to 2');
	}
	
	if($shipTo1 = rowAtHeader($row, 'Ship to 1'))
		$notes[] = "Ship to: $shipTo1/".rowAtHeader($row, 'Ship to 2').'/'
								.rowAtHeader($row, 'Ship to 3').'/'.rowAtHeader($row, 'Ship to 4');
	
	if($terms = rowAtHeader($row, 'Terms')) $notes[] = "Terms: $terms";
	if($referral = rowAtHeader($row, 'Customer Type')) $notes[] = "Referral: $referral";
	
	/*$sitters = explodePairsLine('SW|48||LT|50');
	if($prov = rowAtHeader($row, 'Rep')) {
		$provs = explode('&', $prov);
		$client['defaultproviderptr'] = $sitters[$provs[0]];
		if(count($provs) > 1) $notes[] = "Mult Sitters: $prov";
	}*/
	if($rep = rowAtHeader($row, 'Rep')) $notes[] = "Rep: $rep";
	if($note = rowAtHeader($row, 'Note')) $notes[] = "NOTE:\n$note";
	
	if($notes) $client['notes'] = join("\n", $notes);
	
	$clientptr = saveNewClient($client);
	if($petnames) foreach($petnames as $petname) {
		$pet = array('ownerptr'=>$clientptr, 'name'=> $petname);
		insertTable('tblpet', $pet, 1);
	}
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
foreach((array)$pets as $pet) echo "<br>{$pet['name']} [{$pet['type']}] {$pet['breed']}";
	echo "<p>";

}

function handleQuickBooksFromHappyTailsOfPhilly($row, $ignore=null) {
	//CUST	NAME	REFNUM	TIMESTAMP	BADDR1(pets, usually)	BADDR2	BADDR3	BADDR4	BADDR5	SADDR1	SADDR2	SADDR3	SADDR4	SADDR5	PHONE1	PHONE2	FAXNUM	EMAIL	NOTE	CONT1	CONT2	CTYPE	TERMS	TAXABLE	SALESTAXCODE	LIMIT	RESALENUM	REP	TAXITEM	NOTEPAD	SALUTATION	COMPANYNAME	FIRSTNAME	MIDINIT	LASTNAME	CUSTFLD1	CUSTFLD2	CUSTFLD3	CUSTFLD4	CUSTFLD5	CUSTFLD6	CUSTFLD7	CUSTFLD8	CUSTFLD9	CUSTFLD10	CUSTFLD11	CUSTFLD12	CUSTFLD13	CUSTFLD14	CUSTFLD15	JOBDESC	JOBTYPE	JOBSTATUS	JOBSTART	JOBPROJEND	JOBEND	HIDDEN	DELCOUNT	PRICELEVEL
	/*
		If BADDR4 like "PHILA"
			city,state,zip = BADDR4
			street1 = BADDR3
			pets=BADDR1
		If BADDR3 like "PHILA"
			city,state,zip = BADDR3
			street1 = BADDR2
		If BADDR2 like "PHILA"
			city,state,zip = BADDR2
			street1 = BADDR1
		ELSE street1 = BADDR1
	*/
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d', rowAtHeader($row, 'TIMESTAMP')));
	if(strpos(strtoupper(rowAtHeader($row, 'BADDR4')), "PHILA") === 0) {
		getCityStateZip(rowAtHeader($row, 'BADDR4'), $client);
		$client['street1'] = rowAtHeader($row, 'BADDR3');
		$petcol = explode('-', (array)rowAtHeader($row, 'BADDR1'));
	}
	else if(strpos(strtoupper(rowAtHeader($row, 'BADDR3')), "PHILA") === 0) {
		getCityStateZip(rowAtHeader($row, 'BADDR3'), $client);
		$client['street1'] = rowAtHeader($row, 'BADDR2');
	}
	else if(strpos(strtoupper(rowAtHeader($row, 'BADDR2')), "PHILA") === 0) {
		getCityStateZip(rowAtHeader($row, 'BADDR2'), $client);
		$client['street1'] = rowAtHeader($row, 'BADDR1');
	}
	else $client['street1'] = rowAtHeader($row, 'BADDR1');
	$client['homephone'] = rowAtHeader($row, 'PHONE1');
	$client['workphone'] = rowAtHeader($row, 'PHONE2');
	$client['email'] = rowAtHeader($row, 'EMAIL');
	$emergencyContact = rowAtHeader($row, 'CONT1');
	$client['notes'] = rowAtHeader($row, 'TERMS');
	if($petField = rowAtHeader($row, 'COMPANYNAME')) {
		if($petField == $client['street1']) unset($client['street1']);
		$petcol = explode('-', $petField);
	}
	if($petcol) {
		foreach(handlePetNames($petcol[0]) as $name)
			$pets[] = array('name' => $name, 'type'=>guessTypeByBreed($petcol[1]), 'breed'=>$petcol[1]);
	}
	handleFnameSpaceLname(rowAtHeader($row, 'NAME'), $client, $fnameKey='fname', $lnameKey='lname');

	$client['active'] = rowAtHeader($row, 'HIDDEN') == 'Y' ? 0 : 1;
	
	
	if(findClientByName(trim(rowAtHeader($row, 'NAME')))) {
		echo "<font color=red>Client already exists: {$client['fname']} {$client['lname']}</font><p>";
		return;
	}
	
	
	$clientptr = saveNewClient($client);
	if($pets) foreach($pets as $pet) {
		$pet['ownerptr'] =$clientptr;
		insertTable('tblpet', $pet, 1);
	}
	echo "Added client {$client['fname']} {$client['lname']}";
foreach(explode(',','street1,city,state,zip') as $f) {$addr[]=$client[$f];unset($client[$f]);}
echo "<br>".oneLineAddress($addr).'<br>'.print_r($client, 1);
foreach((array)$pets as $pet) echo "<br>{$pet['name']} [{$pet['type']}] {$pet['breed']}";
	echo "<p>";
}

function headerIndex($header) {
	global $dataHeaders;
	return array_search($header, $dataHeaders);
}

function rowAtHeader($row, $header) {
	return trim($row[ headerIndex($header)]);
}

function handleBettaWalkaExport($row, $ignore=null) {
	//Acct Last	Acct First	Pet(s) First	Address	City	State	Zip	Email
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal || !trim($label)) continue;
		else if($label == 'Acct Last') $client['lname'] = $trimVal;
		else if($label == 'Acct First') $client['fname'] = $trimVal;
		//else if($label == 'Phone') $client['homephone'] = ($trimVal ? "*$trimVal" : $trimVal);
		//else if($label == 'Alt. Phone') $client['workphone'] = $trimVal;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Pet(s) First') $petNames = handlePetNames($trimVal);
		else if($label == 'Email') $badEmails = processEmails($trimVal, $client);
	}
	if(in_array("{$client['fname']} {$client['lname']}", (array)$ignore)) {
		echo "Skipped: {$client['fname']} {$client['lname']}<p>";
		return;
	}
	if($badEmails) $client['notes'][] .= "Invalid emails disallowed: ".join(', ', $badEmails);
	$client['notes'] = join("\n", (array)$client['notes']);
	
	$clientptr = saveNewClient($client);
	if($petNames) foreach($petNames as $name) {
		if(!trim($name)) continue;
		$pet = array('name'=>$name, 'ownerptr'=>$clientptr);
		insertTable('tblpet', $pet, 1);
	}
	echo "Added client {$client['fname']} {$client['lname']}";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";
}

function handlePetCarersBendigo($row) {
	//First Name		Last Name		Phone		Alt. Phone		Street1		City		State		Post Code		Email		Referral Source
	if(in_array(trim($row[1]), array('Schifferle', 'Sessions'))) return;
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal || !trim($label)) continue;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Phone') $client['homephone'] = ($trimVal ? "*$trimVal" : $trimVal);
		else if($label == 'Alt. Phone') $client['workphone'] = $trimVal;
		else if($label == 'Street1') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Post Code') $client['zip'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Referral Source') $client['note'] = "Referral Source: $trimVal";
	}
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
	echo "<p>";
}

function handleDownwardDog($row) {
	//First Name,Last Name,Pet Name,Dog/ Cat,Street Addrss,City,State,Zip,Telephone (cell),Telephone (work),Email,
	// Vet Name,Vet Address,Vet City,Vet State,Vet Zip,Vet Number

	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$pettypes = array('D'=>'Dog', 'C'=>'Cat');
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Street Addrss') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Telephone (cell)') $client['cellphone'] = $trimVal;
		else if($label == 'Telephone (work)') $client['workphone'] = $trimVal;
		else if($label == 'Pet Name') $petNames = handlePetNames($trimVal);
		else if($label == 'Dog/ Cat') {
			foreach($petNames as $nm)
				$pets[$nm] = array('name'=>$nm, 'type'=>$pettypes[$trimVal]);
		}
	}
	$clientptr = saveNewClient($client);
	if($pets) foreach($pets as $name => $pet) {
		if(!trim($name)) continue;
		$pet = array('name'=>$name, 'ownerptr'=>$clientptr, 'type'=>$pet['type']);
		insertTable('tblpet', $pet, 1);
	}
	if($clinicname = $row[array_search('Vet Name', $dataHeaders)]) {
			$clinicptr = findClinicByName($clinicname);
			if(!$clinicptr) {
				$clinicptr = insertTable('tblclinic', 
					array('clinicname' => $clinicname,
								'officephone' => $row[array_search('Vet Number', $dataHeaders)],
								'street1' => $row[array_search('Vet Address', $dataHeaders)],
								'city' => $row[array_search('Vet City', $dataHeaders)],
								'state' => $row[array_search('Vet State', $dataHeaders)],
								'zip' => $row[array_search('Vet Zip', $dataHeaders)]), 1);
			}
			$client['clinicptr'] = $clinicptr;
	}
	
	echo "Added client {$client['fname']} {$client['lname']}";
	echo "<p>";
	
	
}

function handlePeakCityPuppy($row) {
	// issues: 
	// - Ship to (almost ?) always equals Bill to
	// - Ship to 3/Bill to 3 is sometimes Apt #, sometimes citystatezip
	// - Customer type looks like a referral field
//print_r($row);	
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	ensureCustFields('Territory|oneline||Status|oneline||Client Type|oneline');
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		else if($label == 'Last name') $client['lname'] = $trimVal;
		else if($label == 'First name') $client['fname'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Street address') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'ZIP') $client['zip'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Mobile') $client['cellphone'] = $trimVal;
		else if($label == 'Home Phone') $client['homephone'] = $trimVal;
		else if($label == 'Phone') $client['workphone'] = $trimVal;
		else if($label == 'TERRITORY') $customFields[$custFieldsByLabel['Territory']] = $trimVal;
		else if($label == 'STATUS') {
			$customFields[$custFieldsByLabel['Status']] = $trimVal;
			$client['active'] = $trimVal == 'ACTIVE';
		}
		else if($label == 'RANK') $customFields[$custFieldsByLabel['Client Type']] = $trimVal;
		else if($label == 'Note') $client['notes'] = $trimVal;
	}
	// No email data!
	$clientptr = saveNewClient($client);
	if($customFields) 
		saveClientCustomFields($clientptr, $customFields, $pairsOnly=true);
	echo "Added client {$client['fname']} {$client['lname']}";
	echo "<p>";

}


function handleHart2Hart($row) {  // Quicken Customker list
//"Last Name","First Name","Address","City",DUH,"Zip Code","Cell Phone"

	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $dataHeaders;
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'), 'state'=>'WA');
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'First Name') $client['fname'] = $trimVal;
		//else if($label == 'E-mail Address') $client['email'] = $trimVal;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'Zip Code') $client['zip'] = $trimVal;
		else if($label == 'Cell Phone') $client['cellphone'] = $trimVal;
	}
	// No email data!
	$clientptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}";
	echo "<p>";

}




function handlePuppyUprisingPetRow($row) {
	static $rowCount;
	if(!$row[1]) return;
	$rowCount = max($rowCount, 1);
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders, $finalNote, $added;
	ensureCustFields('Allergies/Diet|text||Health Problems|text||Medications|text'
										.'Feeding Instructions|text||Behavioral Issues|text||Aggression/ Fear Triggers|text||Dog friendly?|text'
										.'||Things they love|text||Dog parks?|text||Um...Pooping?|text||Other info|text', 'PETPREFIX');
	$pet =  array('active'=>1);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		else if($label == 'Pet Name') $pet['name'] = $trimVal;
		else if($label == 'Species') $pet['type'] = $trimVal;
		else if($label == 'Breed/Physical Description') $pet['description'] = $trimVal;
		else if($label == 'Sex') {
			$trimVal = strtoupper($trimVal[0]);
			$pet['sex'] = $trimVal == 'F' ? 'f' : ($trimVal == 'M' ? 'm' : null);
			if(!$pet['sex']) unset($pet['sex']);
		}
		else if($label == 'Human(s) Name(s)') {
			$names = handlePetNames($trimVal);
			if(!$names) {
				$error = "Bad row (no owner): $rowCount<p>";
				echo "$error<p>"; 
				return;
			}
			else $clientptr = findClientByName($names[0]);
			if(!$clientptr) {
				$error = "Owner not found ({$names[0]}) for {$pet['name']} row: $rowCount";
				echo "$error<p>"; 
				$finalNote .= "$error<br>";
				return;
			}
			$pet['ownerptr'] = $clientptr;
		}
		else if($custFieldsByLabel[$label]) $customFields[$custFieldsByLabel[$label]] = $trimVal;
	}
	insertTable('tblpet', $pet, 1);
	echo "Added: ".print_r($pet, 1).'<br>';
	$added += 1;
}

function handlePuppyUprising($row) {
	// issues: 
	// - Ship to (almost ?) always equals Bill to
	// - Ship to 3/Bill to 3 is sometimes Apt #, sometimes citystatezip
	// - Customer type looks like a referral field
//print_r($row);	
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	if($dataHeaders[0] == 'Pet Name') return handlePuppyUprisingPetRow($row);
	ensureCustFields('Other Owners|text||Other Emergency Contact|text||Code|oneline||Keys|oneline||Veterinarian|text'
										.'||Litter Box Location|text||Towels|text||Plastic Bags|text||Keys/Doors|text||Sitting Info|text');
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Pet(s) name(s)') {
			$petNames = handlePetNames($trimVal);
			foreach((array)$petNames as $petName) 
				if(strpos($petName, '-')) {
					$petName = explode('-', $petName);
					$pets[trim($petName[0])] = trim($petName[1]);
				}
				else $pets[$petName] = '';
		}
		else if($label == 'Human(s) Name(s)') {
			$names = handlePetNames($trimVal);
			if(!$names) {echo "Bad row (no owner): $rowCount<p>"; return;}
			handleFnameSpaceLname($names[0], $client, $fnameKey='fname', $lnameKey='lname');
			if($names[1]) handleFnameSpaceLname($names[1], $client, $fnameKey='fname2', $lnameKey='lname2');
			if(count($names) > 2) $customFields[$custFieldsByLabel['Other Owners']] = $trimVal;
		}
		else if($label == 'code') $customFields[$custFieldsByLabel['Code']] = $trimVal;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'keys') $customFields[$custFieldsByLabel['Keys']] = $trimVal;
		else if($label == 'Phone Number(s) (Cell)') $client['cellphone'] = $trimVal;
		else if($label == 'Phone Number 2') $client['homephone'] = $trimVal;
		else if($label == 'Phone Number(s) (Other)') $client['cellphone2'] = $trimVal;
		else if($label == 'Emergency Contact') {
			$parts = explode(' ', $trimVal);
			if(count($parts)) {
				$number = array_pop($parts);
				$name = str_replace("\n", " ", join(' ', $parts));
			}
			else $name = str_replace("\n", " ", $trimVal);
			$emergencyContact = array('name'=>$name, 'homephone'=>$number);
		}
		else if($label == 'Local Emergency Contact') {
			$parts = explode(' ', $trimVal);
			if(count($parts)) {
				$number = array_pop($parts);
				$name = str_replace("\n", " ", join(' ', $parts));
			}
			else $name = str_replace("\n", " ", $trimVal);
			$neighbor = array('name'=>$name, 'homephone'=>$number);
		}
		else if($label == 'Other Emergency Contact') $customFields[$custFieldsByLabel['Other Emergency Contact']] = $trimVal;
		else if($label == 'Veterinarian') $customFields[$custFieldsByLabel['Veterinarian']] = $trimVal;
		else if($label == 'Directions') $client['directions'] = $trimVal;
		else if($label == 'Where are leashes?') $client['leashloc'] = $trimVal;
		else if($label == "Where's the litter box(es)?") $customFields[$custFieldsByLabel['Litter Box Location']] = $trimVal;
		else if($label == "Towels?") $customFields[$custFieldsByLabel['Towels']] = $trimVal;
		else if($label == "Plastic bags?") $customFields[$custFieldsByLabel['Plastic Bags']] = $trimVal;
		else if($label == 'Where is food kept?') $client['foodloc'] = $trimVal;
		else if($label == "Keys/ door info") $customFields[$custFieldsByLabel['Keys/Doors']] = $trimVal;
		else if($label == "House info for Sitting") $customFields[$custFieldsByLabel['Sitting Info']] = $trimVal;
	}
	// No email data!
	$clientptr = saveNewClient($client);
echo "CUSTOM: ".print_r($customFields, 1).'<p>';
	if($customFields) 
		saveClientCustomFields($clientptr, $customFields, $pairsOnly=true);
	if($emergencyContact) saveClientContact('emergency', $clientptr, $emergencyContact);
	if($neighbor) saveClientContact('neighbor', $clientptr, $neighbor);
	if($pets) foreach($pets as $name => $descr) {
		if(!trim($name)) continue;
		$pet = array('name'=>$name, 'ownerptr'=>$clientptr, 'description'=>$descr, 
									'type'=>findType($descr, explode(',', 'Guinea Pig,Dog,Cat,Rat,Turtle,Fish,Bird,Parrot')) );
		insertTable('tblpet', $pet, 1);
	}
	echo "Added client {$client['fname']} {$client['lname']}";
	echo "<p>";

}

function handleLuckyMuttsQuickBooks($row) {
	// issues: 
	// - Ship to (almost ?) always equals Bill to
	// - Ship to 3/Bill to 3 is sometimes Apt #, sometimes citystatezip
	// - Customer type looks like a referral field
//print_r($row);	
	if(!$row[1]) return;
	static $rowCount;
	$rowCount++;
	global $custFieldsByLabel, $dataHeaders;
	ensureCustFields('Customer Type');
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	if(trim($row[array_search('First Name', $dataHeaders)]) 
			&& trim($row[array_search('Last Name', $dataHeaders)])) {
		$client['lname'] = trim($row[array_search('Last Name', $dataHeaders)]);
		$client['fname'] = trim($row[array_search('First Name', $dataHeaders)]);
	}
	if($b4 = trim($row[array_search('Bill to 4', $dataHeaders)])) {
		$mailstreet2 = $row[array_search('Bill to 3', $dataHeaders)];
		$row[array_search('Bill to 3', $dataHeaders)] = $b4;
	}
	if($b4 = trim($row[array_search('Ship to 4', $dataHeaders)])) {
		$street2 = $row[array_search('Ship to 3', $dataHeaders)];
		$row[array_search('Ship to 3', $dataHeaders)] = $b4;
	}
	if(trim($row[array_search('Ship to 1', $dataHeaders)]) != trim($row[array_search('Bill to 1', $dataHeaders)]))
		echo "<p>Bill to/Ship to disagreement row $rowCount: ".$row[array_search('Ship to 1', $dataHeaders)]."<p>";
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Customer' && !$client['lname']) 
			handleFnameSpaceLname($trimVal, $client, $fnameKey='fname', $lnameKey='lname');
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Phone') $client['phone'] = $trimVal;
		else if($label == 'Bill to 2') {
			if($trimVal == trim($row[array_search('Ship to 2', $dataHeaders)]))
				continue;
			$client['mailstreet1'] = $trimVal;
			$client['mailstreet2'] = $mailstreet2;
			$mailaddr = array();
			getCityStateZip($row[array_search('Bill to 3', $dataHeaders)], $mailaddr);
			//echo "<b>Bang! </b>".print_r($mailaddr, 1).'<p>';
			foreach($mailaddr as $k=>$v) $client["mail$k"] = $v;
		}
		else if($label == 'Ship to 2') {
			$client['street1'] = $trimVal;
			$client['street2'] = $street2;
		}
		else if($label == 'Ship to 3') {
			getCityStateZip($row[array_search('Ship to 3', $dataHeaders)], $client);
			//echo print_r($client, 1).'<p>';
		}
		else if($label == 'Customer Type') 
			$customFields[$custFieldsByLabel[$label]] = $trimVal;
	}

	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	$clientptr = saveNewClient($client);
	if($customFields) 
		saveClientCustomFields($clientptr, $customFields, $pairsOnly=true);
		
	echo "Added client {$client['fname']} {$client['lname']}";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";

}

function ensureCustFields($fieldscsv, $pet=null) {
	global $custFieldsByLabel;
	if($pet) $PREFIX = 'pet';
	$rowCount++;
	if(!$custFieldsByLabel) {
		require_once "preference-fns.php";
		require_once "custom-field-fns.php";
		if(strpos($fieldscsv, '|')) {
			$types = explodePairsLine($fieldscsv);
			$customFields = array_keys($types);
		}
		else $customFields = explode(',',$fieldscsv);
		$allCustFields = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property LIKE '{$PREFIX}custom%'");
		foreach($allCustFields as $key => $descr)
			$definedFields[substr($descr, 0 , strpos($descr, '|'))] = substr($key, strlen("{$PREFIX}custom"));
		if($definedFields) $maxFieldNum = max($definedFields);
		foreach($customFields as $fieldname) {
			if(!$definedFields[$fieldname]) {
				$definedFields[$fieldname] = ($maxFieldNum += 1);
				$type = $types[$fieldname] ? $types[$fieldname] : 'oneline';
				setPreference("{$PREFIX}custom$maxFieldNum", "$fieldname|1|$type|1");
			}
		}
		$allCustFields = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property LIKE '{$PREFIX}custom%'");
		foreach($allCustFields as $key => $descr)
			$custFieldsByLabel[substr($descr, 0 , strpos($descr, '|'))] = $key;
		echo "FIELDS BY LABEL: ".print_r($custFieldsByLabel, 1).'<p>';
	}
}
	

function handleALegUpPetsCustRow($row) {
	if(!($originalId = $row[0])) return;
	static $custFieldsByLabel, $rowCount;
	$rowCount++;
	if(!$custFieldsByLabel) {
		require_once "preference-fns.php";
		require_once "custom-field-fns.php";
		$customFields = 
			explode(',',
			'services,pricing,walk_client,app_area,found_alegup,found_detail,key_details,other_pets,plants,mail');
		$allCustFields = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property LIKE 'custom%'");
		foreach($allCustFields as $key => $descr)
			$definedFields[substr($descr, 0 , strpos($descr, '|'))] = substr($key, strlen('custom'));
		if($definedFields) $maxFieldNum = max($definedFields);
		foreach($customFields as $fieldname) {
			if(!$definedFields[$fieldname]) {
				$definedFields[$fieldname] = ($maxFieldNum += 1);
				setPreference("custom$maxFieldNum", "$fieldname|1|oneline|1");
			}
		}
		$allCustFields = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property LIKE 'custom%'");
		foreach($allCustFields as $key => $descr)
			$custFieldsByLabel[substr($descr, 0 , strpos($descr, '|'))] = $key;
	}
	
	
	global $dataHeaders;
	
	$client =  array('active'=>1, 'setupdate'=>date('Y-m-d'));
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($custFieldsByLabel[$label]) $customFields[$custFieldsByLabel[$label]] = $trimVal;
		else if($label == 'email') $client['email2'] = $trimVal;
		else if($label == 'register_date') $client['activationdate'] = $trimVal;
		else if($label == 'contact1_fname') $client['fname'] = $trimVal;
		else if($label == 'contact1_lname') $client['lname'] = $trimVal;
		else if($label == 'contact1_email1') $client['email'] = $trimVal;
		else if($label == 'contact1_cell_phone') $client['cellphone'] = $trimVal;
		else if($label == 'contact1_home_phone') $client['homephone'] = $trimVal;
		else if($label == 'contact1_work_phone') $client['workphone'] = $trimVal;
		//customer_contact2 (compound value. (what's the format?) e.g., "Yosh, Halberstam, yosh.halberstam@gmail.com, 647-393-5479, 416-591-5364, 416-978-4537, CHECKED")

		else if($label == 'customer_contact2' && $trimVal) {
			$contact2 = array_map('trim', explode(',', $trimVal));
			$client['lname2'] = $contact2[0];
			$client['fname2'] = $contact2[1];
			if($contact2[2]) $client['email2'] = $contact2[2];
			for($i=3; $i<=5; $i++) if(trim($contact2[$i])) $c2phones[] = trim($contact2[$i]);
			if($c2phones) $client['cellphone2'] = $c2phones[0];
			if(count($c2phones) > 1) $client['notes'][] = "Contact2: $trimVal";
		}
		
		else if($label == 'emergency_contact' && $trimVal) {
			$econtact = array_map('trim', explode(',', $trimVal));
			$client['emergency']['name'] = "{$econtact[0]} {$econtact[1]}";
			$client['emergency']['location'] = $econtact[2];
			$client['emergency']['cellphone'] = $econtact[3];
			$client['emergency']['homephone'] = $econtact[4];
			$client['emergency']['workphone'] = $econtact[5];
		}
		else if($label == 'entry') $client['directions'][] = "entry: $trimVal";
		else if($label == 'parking') $client['parkinginfo'] = $trimVal;
		else if($label == 'home_details') $client['directions'][] = "home_details: $trimVal";
		else if($label == 'code') $client['garagegatecode'] = $trimVal;
		else if(in_array($label, array('add_num','add_name','add_type','add_dir')) && $trimVal) 
			$client['street1'][] = $trimVal;
		else if($label == 'add_suite' && $trimVal) $client['street2'] = $trimVal;
		else if($label == 'add_city' && $trimVal) $client['city'] = $trimVal;
		else if($label == 'add_prov' && $trimVal) $client['state'] = $trimVal;
		else if($label == 'add_postal' && $trimVal) $client['zip'] = $trimVal;
	}
	
	if($client['street1']) $client['street1'] = join(' ', $client['street1']);
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	if($client['directions']) $client['directions'] = join("\n", $client['directions']);
	
	$key = array();
	if($row[array_search('pickup_key', $dataHeaders)]) $key['description'][] = 'pickup_key: '.$row[array_search('pickup_key', $dataHeaders)];
	if($row[array_search('return_key', $dataHeaders)]) $key['description'][] = 'return_key: '.$row[array_search('return_key', $dataHeaders)];
	if($key['description']) $key['description'] = join(' ', $key['description']);

	if($clinicname = $row[array_search('vet_name', $dataHeaders)]) {
		$clinicptr = findClinicByName($clinicname);
		if(!$clinicptr) {
			$clinicptr = insertTable('tblclinic', 
				array('clinicname' => $clinicname,
							'officephone' => $row[array_search('vet_phone', $dataHeaders)],
							'street1' => $row[array_search('vet_address', $dataHeaders)]), 1);
		}
		$client['clinicptr'] = $clinicptr;
	}
	
	if($client['email'] && !isEmailValid( $client['email'])) { // see field-utils.php
		$badEmails[] = $client['email'];
		unset($client['email']);
	}
	if($client['email2'] && !isEmailValid( $client['email2'])) { // see field-utils.php
		$badEmails[] = $client['email2'];
		unset($client['email2']);
	}
	
	if(!$client['fname'] || !$client['lname']) {
		echo "<font color=red>Bad row (fname, lname): $rowCount</font><p>";
		return;
	}
	else if(!$client['fname']) !$client['fname'] = '-unknown-';
	else if(!$client['lname']) !$client['lname'] = '-unknown-';
	
	$clientptr = saveNewClient($client);

	if($client['emergency']) saveClientContact('emergency', $clientptr, $client['emergency']);
	if($customFields) 
		saveClientCustomFields($clientptr, $customFields, $pairsOnly=true);
	
	$petNames = fetchCol0("SELECT name FROM tblpet WHERE ownerptr = $originalId");
	
	updateTable('tblpet', array('ownerptr'=>$clientptr), "ownerptr = $originalId", 1);
	
	echo "Added client {$client['fname']} {$client['lname']} associated with pets [".join(' - ', $petNames)."]";
	if($badEmails) echo "<br>... but rejected these bad email addresses: [".join('], [', $badEmails).']';
	echo "<p>";
}




/*
id
acc_status (Active - 1,190, Disabled - 2, Flag - 1, New - ~900)
neg_bookings (10, 0) IGNORE
status_date (mostly unused) IGNORE
services (e.g., Dog Walk,Boarding, Cat Visit) CUST FIELD
recurring_dates (e.g., "1,2"?) IGNORE
recurring_start IGNORE
password (very few values: walk, cat, meow, bb, board) IGNORE
email ALT EMAIL
pricing (On Command,One Dg Rate, etc) CUST FIELD
walk_client (Yes/No) CUST FIELD
service1_credits (mostly unused) IGNORE
service1_avg_cost (mostly unused) IGNORE
service2_credits (mostly unused) IGNORE
service2_avg_cost (mostly unused) IGNORE
app_area (int ??) CUST FIELD
timeslot (10, 30, blank) IGNORE
rights (always 1) IGNORE
register_date (necessary? may be a timestamp) setup date
status (0 - ~1,238 or 1 - ~762) IGNORE
lat IGNORE
lng IGNORE
address IGNORE
add_num
add_name
add_type
add_dir
add_suite
add_city
add_prov
add_postal
contact1_fname
contact1_lname
contact1_email1
contact1_cell_phone
contact1_home_phone
contact1_work_phone
customer_contact2 (compound value. (what's the format?) e.g., "Yosh, Halberstam, yosh.halberstam@gmail.com, 647-393-5479, 416-591-5364, 416-978-4537, CHECKED")
emergency_contact (compound value. (what's the format?) e.g., "Melissa, Bebee, 19 Grant St. Apt #2, 647-233-9393, , "
found_alegup (referral) CUST FIELD "Referral" (part 1)
found_detail (referral note) CUST FIELD "Referral" (part 2)
customer_note (unused)
entry (note - where to enter house?) DIRECTIONS TO HOME
parking 
home_details DIRECTIONS TO HOME
pickup_key (e.g., "With A Leg Up", "Will be dropped off") KEY DESCRIPTION
return_key (mostly blank, "Drop in mail slot", "To A Leg Up", "To Concierge") KEY DESCRIPTION
code (mostly unused.  meaning?) GARAGE GATE CODE
key_details (used about 400/2000 times: e.g., "Hidden at back", "in BBQ") CUST FIELD
vet_name
vet_address - clinic street 1
vet_phone
other_pets (notes about other pets) CUST FIELD
plants (used 43 times) CUST FIELD
mail (instructions) CUST FIELD
other (unused) IGNORE

*/





function handleALegUpPetsRow($row) {
	if(!$row[0]) return;
	static $petCustFieldsByLabel;
	if(!$petCustFieldsByLabel) {
		require_once "preference-fns.php";
		require_once "custom-field-fns.php";
		$customFields = 
			explode(',',
			'pet_from,weight,microchip,tattoo,allergies,illnesses,hot_spots,medications'
			.',on_leash,off_leash,leashed,trained,obedience,discipline,commands,crated'
			.',house_training,travel,energy,fears,personality,manners,dry_feed_brand'
			.',dry_feed_instructions,wet_feed_brand,wet_feed_instructions,feed_am'
			.',feed_noon,feed_pm,feed_measure,treats,feed_location,litter_type'
			.',litter_disposal,litter_location,hiding,habits');

		$allCustFields = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property LIKE 'petcustom%'");
		foreach($allCustFields as $key => $descr)
			$definedFields[substr($descr, 0 , strpos($descr, '|'))] = substr($key, strlen('petcustom'));
		if($definedFields) $maxFieldNum = max($definedFields);
		foreach($customFields as $fieldname) {
			if(!$definedFields[$fieldname]) {
				$definedFields[$fieldname] = ($maxFieldNum += 1);
				setPreference("petcustom$maxFieldNum", "$fieldname|1|oneline|1");
			}
		}
		$allCustFields = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property LIKE 'petcustom%'");
		foreach($allCustFields as $key => $descr)
			$petCustFieldsByLabel[substr($descr, 0 , strpos($descr, '|'))] = $key;
	}
		
	global $dataHeaders;
	$pet =  array('active'=>1);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'customer') $pet['ownerptr'] = $trimVal;
		else if($label == 'pet_name') $pet['name'] = $trimVal;
		else if($label == 'type') $pet['type'] = $trimVal;
		else if($petCustFieldsByLabel[$label]) $customFields[$petCustFieldsByLabel[$label]] = $trimVal;
		else if($label == 'sex' && $trimVal) $pet['sex'] = strtolower($trimVal[0]);
		else if($label == 'birthdate' && $trimVal) $pet['dob'] = date('m/d/Y', $trimVal);
		else if($label == 'colour') $pet['color'] = $trimVal;
		else if($label == 'breed') $pet['breed'] = $trimVal;
		else if($label == 'fixed') $pet['fixed'] = $trimVal == 'Yes' ? 1 : '0';
		else if($label == 'years_owned' && $trimVal) $pet['notes'][] = "years_owned: $trimVal";
		else if($label == 'notes' && $trimVal) $pet['notes'][] = $trimVal;
	}
	if($pet['notes']) $pet['notes'] = join("\n", $pet['notes']);
	$petId = insertTable('tblpet', $pet, 1);
	if($customFields) savePetCustomFields($petId, $customFields, 0);
}
/*
pet_from CUSTOM
weight CUST FIELD
microchip CUST FIELD
tattoo CUST FIELD short
allergies CUST FIELD short
hot_spots CUST FIELD short
medications CUST FIELD short
...
habits
years_owned NOTES
notes NOTES

*/
	
function handleBlueDog($row) {
	global $dataHeaders;
	$client = array('active'=>$row[0]);
	$pets = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == "Dog's Name") {
			$petNames = breakBy($trimVal, array("\n", " and ", " & "));
			foreach((array)$petNames as $petName) 
				if(strpos($petName, '-')) {
					$petName = explode('-', $petName);
					$pets[trim($petName[0])] = trim($petName[1]);
				}
				else $pets[$petName] = '';
			}
		else if($label == 'First Name') $client['fname'] = $trimVal;
		else if($label == 'Last Name') $client['lname'] = $trimVal;
		else if($label == 'Phone') $client['homephone'] = $trimVal;
		else if($label == 'Alt. Phone') $client['cellphone2'] = $trimVal;
		else if($label == 'Alt. Contact') $client['fname2'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Bill to 2') $client['street1'] = $trimVal;
		else if($label == 'Bill to 3') getCityStateZip($trimVal, $client);
		if($client['city'] && substr($client['city'], strlen($client['city']) - 1) == ',')
			$client['city'] = substr($client['city'], 0, strlen($client['city']) - 1);
	}
	$ownerptr = saveNewClient($client);
	$client = getClient($ownerptr);

	if($pets) foreach($pets as $name => $descr) {
		$pet = array('name'=>$name, 'ownerptr'=>$ownerptr);
		insertTable('tblpet', $pet, 1);
	}
	echo "Added client {$client['fname']} {$client['lname']} with pets (".join(', ', array_keys($pets)).")<p>";
}

	
	
function handlePuppyUprisingOLD($row) {
	global $dataHeaders;
	$client = array('active'=>$row[0]);
	$pets = array();
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Pet(s) name(s)') {
			$petNames = breakBy($trimVal, array("\n", " and ", " & "));
			foreach((array)$petNames as $petName) 
				if(strpos($petName, '-')) {
					$petName = explode('-', $petName);
					$pets[trim($petName[0])] = trim($petName[1]);
				}
				else $pets[$petName] = '';
			}
		else if($label == 'Human(s) Name(s)') {
			if(!$trimVal) {echo "ERROR: ".print_r($row, 1).'<p>'; return;}
			$clientNames = breakBy($trimVal, array("\n", " and ", " & "));
			foreach($clientNames as $i => $name) {
				if($i==0) handleFnameSpaceLname($name, $client, $fnameKey='fname', $lnameKey='lname');
				if($i==1) handleFnameSpaceLname($name, $client, $fnameKey='fname2', $lnameKey='lname2	');
			}
		}
		else if($label == 'code') $client['notes'][] = "code: $trimVal";
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'keys	') $client['notes'][] = "keys: $trimVal";
		else if($label == 'Phone Number(s) (Cell)') {
			foreach(decompose($trimVal, ',') as $i => $phone) {
				if($i==0) $client['cellphone'] = trim($phone);
				else if($i==1) $client['cellphone2'] = trim($phone);
				else $client['notes'][] = "Other Cell Phone: $trimVal";
			}
		}
		else if($label == 'Phone Number 2') {
			foreach(decompose($trimVal, ',') as $i => $phone) {
				if($i==0) $client['workphone'] = trim($phone);
				else $client['notes'][] = "Other Phone 2: $trimVal";
				//if($i==1) $client['workphone'] = trim($phone);
			}
		}
			else if($label == 'Phone Number(s) (Other)') $client['homephone'] = $trimVal;
		else if(in_array($label, array('Emergency Contact', 'Local Emergency Contact', 'Other Emergency Contact')))
			$client['notes'][] = "$label: $trimVal";
		else if(in_array($label, array('Veterinarian (Name, Location, Phone)')))
			$client['notes'][] = "Veterinarian: $trimVal";
		else if($label == 'Directions') $client['directions'] = $trimVal;
		else if($label == 'Where are leashes?') $client['leashloc'] = $trimVal;
		else if($label == "Where's the litter box(es)?") $client['notes'][] = "Litterbox: $trimVal";
		else if($label == "Towels?") $client['notes'][] = "Towels: $trimVal";
		else if($label == "Plastic bags?") $client['notes'][] = "Plastic bags: $trimVal";
		else if($label == 'Where is food kept?') $client['foodloc'] = $trimVal;
		else if($label == 'Keys/ door info') $client['alarminfo'] = $trimVal;
		else if($label == 'House info for Sitting') $client['notes'][] = "House Info: $trimVal";
	// Pet(s) name(s)	Human(s) Name(s)	code	Address 	keys	Phone Number(s) (Cell)	Phone Number 2	Phone Number(s) (Other)	Emergency Contact	Local Emergency Contact	Other Emergency Contact	Veterinarian (Name, Location, Phone)	Directions	Where are leashes?	Where's the litter box(es)?	Towels?	Plastic bags?	Where is food kept?	Keys/ door info	House info for Sitting						Other people that live in your home																																																																																																																																																																																																																																						
	}
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	$client = getClient($ownerptr);

	if($pets) foreach($pets as $name => $descr) {
		$pet = array('name'=>$name, 'ownerptr'=>$ownerptr, 'description'=>$descr, 
									'type'=>findType($descr, explode(',', 'Guinea Pig,Dog,Cat,Rat,Turtle,Fish,Bird,Parrot')) );
		insertTable('tblpet', $pet, 1);
	}
	echo "Added client {$client['fname']} {$client['lname']} with pets (".join(', ', array_keys($pets)).")<p>";
}

function findType($str, $arr) {
	if($found = likeOneOf($str, $arr))
		return $found;
	return guessTypeByBreed($str);
}

function likeOneOf($str, $arr) {
	$str = strtoupper($str);
	foreach($arr as $pat)
		if(strpos($str, strtoupper($pat)) !== FALSE)
			return $pat;
}

function guessTypeByBreed($str) {
	$rabbits = 'rabbit';
	$birds = 'bird';
	$turtles = 'turtle';
	$cats = 'cat,tuxedo,siamese,kitten,kitty,tabby,pek,dsh,dsc,shorthair,short hair,dsh,dlh,maine,coon,persian,Chartreaux,Chartreux,calico';
	$dogs = 'cattle,golden,bernard,beagle,jack,russell,terrier,cairn,hound,collie,dachshund,pit,bull,mutt,mix,chihuahua,shepherd,weim,corg,dane,pomer'
					.',schnauzer,tzu,lab,oodle,mastiff,pug,poo,havanese,pit,lab,boxer,retriev,bull,orki,spaniel,britn,chow,ridge,wheaton,russel,king'
					.',eskimo,rott,hound,bijon,bishon,maltese,westie,sheltie,dog,cavachon,shiba,inu,pup,cavalier,aussie,coton,terrior,oodle,sheherd,shep,border,wemer'
					.'visla,wire,Catahoula,ibizan,dach,rhod,ridgeb,springer,bassett,bichon,lhasa,Brittany,pondengo,pyrenees,doberman,setter,akita';
	if($match = likeOneOf($str, explode(',', $dogs))) return 'Dog';
	if($match = likeOneOf($str, explode(',', $cats))) return 'Cat';
	if($match = likeOneOf($str, explode(',', $rabbits))) return 'Rabbit';
	if($match = likeOneOf($str, explode(',', $birds))) return 'Bird';
	if($match = likeOneOf($str, explode(',', $turtles))) return 'Turtle';
}

function breakBy($str, $arr) {
	foreach($arr as $sepr)
		if(strpos($str, $sepr)) {
//echo "BREAK: $str with [".($sepr == "\n" ? 'EOL' : $sepr)."]<br>";
			return decompose($str, $sepr);
		}
	return array($str);
}
	
function handleBettaWalka($row) {
// Acct Last	Acct First	Pet(s) First	Address	City	State	Zip	Email

	global $dataHeaders, $customFields, $multilines;
	$client = array('active'=>$row[0]);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Acct Last') {
			$client['lname'] = $trimVal;
			if(strpos(strtoupper($trimVal), 'IGNORE') === 0)
				return;
		}
		else if($label == 'Acct First') $client['fname'] = $trimVal;
		else if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Pet(s) First') $petNames = handlePetNames($trimVal);
	}
	$ownerptr = saveNewClient($client);
	foreach($petNames as $name) insertTable('tblpet', array('name'=>$name, 'ownerptr'=>$ownerptr), 1);
	echo "Added client {$client['fname']} {$client['lname']} with pets (".join(', ', $petNames).")<p>";
}
	
function handleGoldCoastPets($row) {
// name,phones,emails,street1,citystate,zip,vets,pets,notes,notes2,notes3
	global $dataHeaders, $customFields, $multilines;
	$client = array('active'=>$row[0]);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'name') handleFnameSpaceLname($trimVal, $client);
		else if($label == 'phones') explodeInto($trimVal, ';', 'homephone,workphone,cellphone,cellphone2,fax,pager', $client);
		else if($label == 'emails') explodeInto($trimVal, ';', 'email,email2', $client);
		else if($label == 'street1') $client['street1'] = $trimVal;
		else if($label == 'citystate') {
			$parts = explode(' ', $trimVal);
			if($parts) {
				$client['state'] = array_pop($parts);
				if($parts) $destination['city'] = join(' ', $parts);
			}
		}
		else if($label == 'zip') $client['zip'] = $trimVal;
		else if($label == 'vets') $client['custom']['custom1'] = $trimVal;
		else if($label == 'pets') $client['custom']['custom2'] = $trimVal;
		else if($label == 'notes') $client['notes'][] = $trimVal;
		else if($label == 'notes2') $client['notes'][] = $trimVal;
		else if($label == 'notes3') $client['notes'][] = $trimVal;
	}
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	foreach((array)$client['custom'] as $k => $v)
		insertTable('relclientcustomfield', array('fieldname'=>$k, 'value'=>$v, 'clientptr'=>$ownerptr), 1);
	echo "Added client {$client['fname']} {$client['lname']} <p>";
}
	
function handleWisconsinPetcare($row) {
	global $dataHeaders, $customFields, $multilines;
	$client = array('active'=>1);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Customer') handleFnameSpaceLname($trimVal, $client);
		else if($label == 'Address') {
			extrapolateFromZIP($trimVal, $client);
			if(strlen($trimVal) >= 45) $client['notes'][] = "Address: $trimVal";
		}
		else if($label == 'Email Address') $client['email'] = $trimVal;
		else if($label == 'Phone') {
			if(strlen($trimVal) <= 45) $client['homephone'] = $trimVal;
			else $client['notes'][] = "Phone: $trimVal";
		}
		else if($label == 'Animal') $petNames = handlePetNames($row[$i]);
		else if($label == 'Rate') $client['notes'][] = "Rate: $trimVal";
		else if($label == 'Note') $client['notes'][] = "Note: $trimVal";
		else if($label == 'Vet' && $trimVal) {
			$clinic = findClinicByName($trimVal);
			if(!$clinic) insertTable('tblclinic', array('clinicname' => $trimVal), 1);
			$client['clinicptr'] = $clinic;
		}
	}
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	foreach($petNames as $name) insertTable('tblpet', array('name'=>$name, 'ownerptr'=>$ownerptr), 1);
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(' - ', $petNames)."]<p>";
	
}

function handleDogWalkingNetwork($row) {
	global $dataHeaders, $customFields, $multilines;
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Customer') {
			$parts = explode('-', $trimVal);
			if($parts[0]) handleFnameSpaceLname($parts[0], $client);
			if(!$client['lname']) $client['lname'] = 'Unknown';
			if(!$client['fname']) $client['fname'] = 'Unknown';
			if($client['lname'] == 'Gardner') {
				echo "<b>Client Gardner ignored.</b><p>";
				return;
			}
			if($parts[1]) $petNames = handlePetNames($parts[1]);
			else $petNames = array();
		}
		if(!$trimVal) continue;
		else if($label == 'Email') $client['email'] = $trimVal;
		else if($label == 'Phone Numbers') {
			$nums = explode("\n", $trimVal);
			foreach($nums as $num) {
				if(strpos($num, 'Phone: ') === 0) $client['homephone'] = substr($num, strlen('Phone: '));
				else if(strpos($num, 'Mobile: ') === 0) $client['cellphone'] = substr($num, strlen('Mobile: '));
			}
		}
		else if($label == 'Billing Street') $client['street1'] = $trimVal;
		else if($label == 'Billing City') $client['city'] = $trimVal;
		else if($label == 'Billing State') $client['state'] = $trimVal;
		else if($label == 'Billing Zip') $client['zip'] = $trimVal;
		else if($label == 'Credit Card #') $client['notes'][] = "$label: $trimVal";
		else if($label == 'CC Expires') $client['notes'][] = "$label: $trimVal";
		else if($label == 'Payment Method') $client['notes'][] = "$label: $trimVal";
		else if($label == 'Note') $client['notes'][] = "$trimVal";
		else if($label == 'Other') $client['notes'][] = "Other: $trimVal";
	}
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	foreach($petNames as $name) insertTable('tblpet', array('name'=>$name, 'ownerptr'=>$ownerptr), 1);
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(' - ', $petNames)."]<p>";
}

function explodeInto($value, $sepr, $fields, &$client) {
	$fields = is_array($fields) ? $fields : explode(',', $fields);
	$n = 0;
	foreach(explode($sepr, $value) as $v) {
		$client[$fields[$n]] = $v;
		$n++;
	}
}
	
function appendValue($row, $field, $nospace=null) {
	global $thisdb, $dataHeaders, $lastClient;
	if($thisdb == 'nannydolittle') {
		if(!$lastClient) return;
		if(!($val = $row[array_search($field, $dataHeaders)])) return;
		$map = array('LAST'=>'lname', 'FIRST'=>'fname', 'HOME'=>'homephone', 'CELL'=>'cellphone', 'WORK'=>'workphone');
		$sepr = $nospace ? '' : ' ';
		if($target = $map[$field]) {
			$val = val($val);
			$vals = array($target=>sqlVal("CONCAT_WS('$sepr', $target, $val)"));
			updateTable('tblclient', $vals, "clientid = $lastClient", 1);
		}
		$map = array('SOURCE'=>'custom1', 'REASON'=>'custom3');
		if($target = $map[$field]) {
			$val = val($val);
			$vals = array('value'=>sqlVal("CONCAT_WS('$sepr', value, $val)"));
			updateTable('relclientcustomfield', $vals, "clientptr = $lastClient AND fieldname = '$target'", 1);
		}
	}
}

function handleAddress($val, &$client) {
	$addr = array_map('trim', explode("\n", $val));
	if(!$addr) return;
	getCityStateZip(array_pop($addr), $client);
	if(count($addr) > 1) $client['street2'] = $addr[1];
	if($addr) $client['street1'] = $addr[0];
}

function handleLnameCommaFname($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	$parts = array_map('trim', explode(',', $str));
	if(count($parts)) {
		$destination[$lnameKey] = $parts[0];
		unset($parts[0]);
		if(count($parts)) $destination[$fnameKey] = join(' ', $parts);
	}
}

function handleLnameCommaFnameWithAnds($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	// handles "Smith, Betty and Jim", 
	$parts = array_map('trim', explode(',', $str));
	if(count($parts)) {
		$destination[$lnameKey] = $parts[0];
		if(count($parts) == 1) return;
		// The rest is one or more first names
		$str = $parts[1];
		foreach(decompose($str, "\n") as $x00)
			foreach(decompose($x00, '+') as $x0)
				foreach(decompose($x0, ' and ') as $x1)
					foreach(decompose($x1, '&') as $x2)
						$firstnames[] = $x2;
		if(!$firstnames) return;
		$destination[$fnameKey] = $firstnames[0];
		if($firstnames[1]) {
			$destination['lname2'] = $destination[$lnameKey];
			$destination['fname2'] = $firstnames[1];
		}
	}
}

function handleLnameCommaFnameWithSlashesAndAnds($str, &$destination, $fnameKey='fname', $lnameKey='lname') {
	// if slash is found, treat that as a separator between two full names
	// .. first fullname name must be lname, fname
	// subsequent fullnames after first fullname may be lname,fname or "fname lnmae"
	// if no slash is found, allow for mult first names sharing lname, divided by "and" or "and"
	// allow for "and" or &" as joiners of separate first names
	$first = 1;
	foreach(decompose($str, "/") as $x) {
		if($first) handleLnameCommaFnameWithAnds($x, $destination, $fnameKey, $lnameKey);
		else {
			if(strpos($x, ",") !== FALSE)
				handleLnameCommaFname($x, $destination, $fnameKey='fname2', $lnameKey='lname2');
			else
				handleFnameSpaceLname($x, $destination, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname');
		}
		$first = 0;
	}
}

function handlePetNames($str) {
	foreach(decompose($str, "\n") as $x00)
		foreach(decompose($x00, '+') as $x0)
			foreach(decompose($x0, ' and ') as $x1)
				foreach(decompose($x1, ',') as $x2)
					foreach(decompose($x2, '&') as $x3)
						foreach(decompose($x3, '/') as $x4)
						$petnames[] = $x4;
	return $petnames;
}

function handleDashOrSlashList($str) {
	foreach(decompose($str, '-') as $x3)
		foreach(decompose($x3, '/') as $x4)
		$parts[] = $x4;
	return $parts;
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

function handleFnameSpaceLnameWithAnds($str, &$destination) {
	foreach(decompose($str, "\n") as $x00)
		foreach(decompose($x00, '+') as $x0)
			foreach(decompose($x0, ' and ') as $x1)
				foreach(decompose($x1, '&') as $x2)
					$fullnames[] = $x2;
	if(!$fullnames) return;
	handleFnameSpaceLname($fullnames[0], $destination, $fnameKey='fname', $lnameKey='lname', $singleNameDefaultKey='fname');
	if($fullnames[1]) {
		handleFnameSpaceLname($fullnames[1], $destination, $fnameKey='fname2', $lnameKey='lname2', $singleNameDefaultKey='fname');
		if(!$destination['lname']) $destination['lname'] = $destination['lname2'];
	}	
}

function multiDecompose($str, $delims, $trim=true) {
	// usage: multiDecompose('Manny, Jack & Moe', array('&', ',', ' ')))
	if(!$delims) return $str;
	$reversed = array_reverse($delims);
	$delim = array_pop($reversed);
	$delims = array_reverse($reversed);
	foreach(decompose($str, $delim) as $part)
		foreach((array)multiDecompose($part, $delims) as $subpart)
			$parts[] = $trim ? trim($subpart) : $subpart;
	return $parts;
}


function decompose($str, $delim) {
	return array_map('trim', explode($delim, $str));
}



function extrapolateFromZIP($str, &$destination) {
	global $dbhost, $db, $dbuser, $dbpass;
	$parts = explode(' ', $str);
	if($zip = array_pop($parts)) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$zip = fetchFirstAssoc("SELECT * FROM zipcodes2 WHERE zipcode = '$zip' LIMIT 1");
		if($zip) {
			$destination['city'] = $zip['city'];
			$destination['state'] = $zip['state'];
			$destination['zip'] = $zip['zipcode'];
		}
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'force');
		$destination['street1'] = $str;
	}
}

function wrangleAddress($str, &$destination) {
	$lines = explode("\n", $str);
	if(count($lines) == 1) { // one line address
		$firstcomma = strpos($str, ",");
		$destination['street1'] = trim(substr($str, 0, $firstcomma));
		$theRest = trim(substr($str, $firstcomma+1));
		getCityStateZip($theRest, $destination);
	}
	else {
		getCityStateZip(array_pop($lines), $destination);
		$destination['street1'] = trim($lines[0]);
		if(count($lines) > 1) $destination['street2'] = trim($lines[1]);
	}
}
		

function getCityStateZip($str, &$destination) { // assumes ZIP, not ZIP+4
	$str = str_replace('  ', ' ', $str);
	$parts = explode(' ', $str);
	if($parts) {
		if($parts[count($parts)-1] == 'image') array_pop($parts);
		if($parts && preg_match('/^\d{5}([\-]\d{4})?$/', $parts[count($parts)-1])) $destination['zip'] = array_pop($parts);
		if($parts && strlen($parts[count($parts)-1]) == 2) $destination['state'] = array_pop($parts);
//echo "[[".print_r($parts, 1)."]]<br>";		
		if($parts) {
			$city = trim(join(' ', $parts));
			if($city && strrpos($city, ',') == strlen($city)-1)
				$city = substr($city, 0, strlen($city)-1);
			$destination['city'] = $city;
		}
	}
}
// AD HOCS ==============================================================

function handlePetAssist($row) {
	global $dataHeaders, $customFields, $multilines;
	if(!$customFields && !($customFields = getCustomFields(true))) {
	setPreference("custom1", "Email 3|1|oneline|1|1");
	setPreference("custom2", "Company|1|oneline|1|1");
	setPreference("custom3", "Business Phone 2|1|oneline|1|1");
	setPreference("custom4", "Pet Birthday|1|oneline|1|1");
	setPreference("custom5", "Categories|1|oneline|1|1");
	}
	$client = array('active'=>1);
	$fnames = explode(' and ', trim($row[array_search('FirstName', $dataHeaders)]));
	$lnames = explode(' - ', trim($row[array_search('LastName', $dataHeaders)]));
	$client['fname'] = $fnames[0];
	$client['lname'] = $lnames[0];
	$client['fname2'] = $fnames[1];
	$client['lname2'] = $lnames[1];
	
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		else if($label == 'Company') $client['custom']['custom2'] = $trimVal;
		else if($label == 'BusinessStreet') $client['street1'] = $trimVal;
		else if($label == 'BusinessCity') $client['city'] = $trimVal;
		else if($label == 'BusinessState') $client['state'] = $trimVal;
		else if($label == 'BusinessPostalCode') $client['zip'] = $trimVal;
		else if($label == 'HomeStreet') $client['street1'] = $trimVal;
		else if($label == 'HomeCity') $client['city'] = $trimVal;
		else if($label == 'HomeState') $client['state'] = $trimVal;
		else if($label == 'HomePostalCode') $client['zip'] = $trimVal;
		else if($label == 'BusinessPhone') $client['workphone'] = $trimVal;
		else if($label == 'BusinessPhone2') $client['custom']['custom3'] = $trimVal;
		else if($label == 'HomePhone') $client['homephone'] = $trimVal;
		else if($label == 'MobilePhone') $client['cellphone'] = $trimVal;
		else if($label == 'Birthday' && strpos($trimVal, '0') !== 0) $client['custom']['custom4'] = $trimVal;
		else if($label == 'Categories') $client['custom']['custom5'] = $trimVal;
		else if($label == 'EmailAddress') $client['email'] = $trimVal;
		else if($label == 'Email2Address') $client['email2'] = $trimVal;
		else if($label == 'Email3Address') $client['email2'] = $client['custom']['custom1'] = $trimVal;
		else if($label == 'Notes') $client['officenotes'] = cleanseString($trimVal);
	}
	//if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	if($client['custom']) 
	saveClientCustomFields($ownerptr, $client['custom'], $pairsOnly=true);
echo "Added client {$client['fname']} {$client['lname']}.<p>";
}

function handleCrestviewPets($row) {
	global $dataHeaders, $customFields, $multilines;
	if(!$customFields && !($customFields = getCustomFields(true))) {
		$customFields = array();
		foreach($customFields as $i => $label) {
			//label|active|onelineORtextORboolean|visitsheet|clientvisible
			setPreference("custom".($i+1), "$label|1|oneline|1|0");
		}
	}
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if($label == 'Client Number') {
			if($trimVal) {
				$multilines[$clientnum = $trimVal] += 1;
				$clientpart = $multilines[$clientnum];
			}
			else $clientpart = 1;
		}
		if(!$trimVal) continue;
		if($label == 'Nickname') ;
		else if($label == 'Email Address') {
			if($clientpart == 1) $client['email'] = $trimVal;
			else if($clientpart == 2) $client['email2'] = $trimVal;
			else $client['notes'][] = 'email: '.$trimVal;
		}
		else if($label == 'First Name' && !$client['fname']) $client['fname'] = $trimVal;
		else if($label == 'Middle Name' && !$client['middlename']) {
			$client['middlename'] = $trimVal;
			$client['fname'] .= " $trimVal";
		}
		else if($label == 'Last Name' && !$client['lname']) $client['lname'] = $trimVal;
		else if($label == 'Home Phone') {
			if($clientpart == 1) $client['homephone'] = $trimVal;
			else $client['notes'][] = 'home: '.$trimVal;
		}
		else if($label == 'Business Phone') {
			if($clientpart == 1) $client['workphone'] = $trimVal;
			else $client['notes'][] = 'work: '.$trimVal;
		}
		else if($label == 'Mobile Phone') {
			if($clientpart == 1) $client['cellphone'] = $trimVal;
			else $client['notes'][] = 'work: '.$trimVal;
		}
		else if($label == 'Business Fax') $client['fax'] = $trimVal;
		else if($label == 'Home Address') $client['street1'] = $trimVal;
	}
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	echo "Added client {$client['fname']} {$client['lname']}.<p>";
}
		
		
function fixQueeniesPets($row) {		
	global $dataHeaders, $customFields;
//print_r($dataHeaders); exit;	
	$name = $row[array_search('First name, Owner 1', $dataHeaders)].' '.$row[array_search('Last name, Owner 1', $dataHeaders)];
	$clients = fetchCol0("SELECT clientid FROM tblclient 
																	WHERE CONCAT_WS(' ', fname, lname) = '".
																	mysqli_real_escape_string($name ? $name : '')."' LIMIT 1");
	if(!$clients) {
		echo "<font color=red>Could not find $name</font><br>";
		return;
	}
	else if(count($clients) > 1) {
		echo "<font color=red>Multiple clients named $name</font><br>";
		return;
	}
	$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = {$clients[0]}");
	$old = "{$client['street1']} {$client['city']} {$client['state']} {$client['zip']}";
	$oldArr = array();
	foreach(explode(',', 'street1,city,state,zip') as $k) $oldArr[$k] = $client[$k];
	echo "Found: ({$clients[0]}) $name:<br>----- current value: $old<br>";	
	
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Address') $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
	}
	
	if($client['city'] == 'Phila' && 
			(strtolower($oldArr['city']) != 'Philadelphia')) $client['city'] = 'Philadelphia';
	$ignoreClients = array(1155,1165,1166,1179,1195,1233,1241,1267,1272);
	if(in_array($clients[0], $ignoreClients)) echo "IGNORED.<p>";
	else if($old != "{$client['street1']} {$client['city']} {$client['state']} {$client['zip']}") {
		foreach(explode(',', 'street1,city,state,zip') as $k) {
			$new[$k] = $oldArr[$k] != $client[$k] ? "<font color=red>{$client[$k]}</font>" : $client[$k];
		}

		if($_REQUEST['change']) updateTable('tblclient', $client, "clientid = {$clients[0]}");
		echo "<font color=blue>----- will change to ",join(' ', $new),"</font><br>";	
	}
	else echo "----- No change.<br>";	
}

function handleQueeniesPets($row) {
	global $dataHeaders, $customFields;
	if(!$customFields && !($customFields = getCustomFields(true))) {
		$customFields = array('Timeframe','Neighborhood','Good/Bad combos','Suggested Walk','Em Clinic');
		foreach($customFields as $i => $label) {
			//label|active|onelineORtextORboolean|visitsheet|clientvisible
			setPreference("custom".($i+1), "$label|1|oneline|1|0");
		}
	}
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		$trimVal = trim($row[$i]);
		if(!$trimVal) continue;
		if($label == 'Contract Date') ;
		else if($label == 'Pets (short)') $petNames = handlePetNames($row[$i]);
		else if($label == 'Last name, Owner 1') $client['lname'] = $trimVal;
		else if($label == 'First name, Owner 1') $client['fname'] = $trimVal;
		else if($label == 'Last name, Owner 2') $client['lname2'] = $trimVal;
		else if($label == 'First name, Owner 2') $client['fname2'] = $trimVal;
		else if($label == 'Address'  && $i==6) $client['street1'] = $trimVal;
		else if($label == 'City') $client['city'] = $trimVal;
		else if($label == 'State') $client['state'] = $trimVal;
		else if($label == 'Zip') $client['zip'] = $trimVal;
		else if($label == 'Owner 1 Email') $client['email'] = $row[$i];
		else if($label == 'Owner 2 Email') $client['email2'] = $row[$i];
		else if($label == 'Cell1') $client['cellphone'] = $trimVal;
		else if($label == 'Cell2') $client['cellphone2'] = $trimVal;
		else if($label == 'Home') $client['homephone'] = $trimVal;
		else if($label == 'Work') $client['workphone'] = $trimVal;
		else if($label == 'Other') $client['fax'] = $trimVal;
		else if($label == 'Note' && $trimVal) $client['notes'][] = $trimVal;
		else if($label == 'Prim. Caregiver') $client['defaultproviderptr'] = findProviderByNickname($trimVal);
		else if($label == 'Timeframe') $client['custom']['custom1'] = $trimVal;
		else if($label == 'Neighborhood') $client['custom']['custom2'] = $trimVal;
		else if($label == 'Good/Bad combos') $client['custom']['custom3'] = $trimVal;
		else if($label == 'Suggested Walk') $client['custom']['custom4'] = $trimVal;
		else if($label == 'Em Clinic') $client['custom']['custom5'] = $trimVal;
		else if($label == 'Em Contact') $client['emergency']['name'] = $trimVal;
		else if($label == 'Em Rel') $client['emergency']['note'] = $trimVal;
		else if($label == 'Em Phone') $client['emergency']['homephone'] = $trimVal;
		else if($label == 'Em Phone2') $client['emergency']['cellphone'] = $trimVal;
		else if($label == 'Em Keys?') $client['emergency']['haskey'] = strtolower($trimVal) == 'yes' ? 1 : '0';
		else if($label == 'Em 2') $client['neighbor']['name'] = $trimVal;
		else if($label == 'Em2 Rel') $client['neighbor']['note'] = $trimVal;
		else if($label == 'Em2 Phone') $client['neighbor']['homephone'] = $trimVal;
		else if($label == 'Em2 Keys?') $client['neighbor']['haskey'] = strtolower($trimVal) == 'yes' ? 1 : '0';
		else if($label == 'Clinic') $client['clinicptr'] = findClinicByName($trimVal);
		else if($label == 'Vet' && $trimVal) $client['notes'][] = "Vet: $trimVal";
		else if($label == 'Vet2' && $trimVal) $client['notes'][] = "Vet2: $trimVal";
		else if($label == 'Clinic2' && $trimVal) $client['notes'][] = "Clinic2: $trimVal";
		
	} //trainingmode
	if($client['notes']) $client['notes'] = join("\n", $client['notes']);
	$ownerptr = saveNewClient($client);
	foreach($petNames as $name) insertTable('tblpet', array('name'=>$name, 'ownerptr'=>$ownerptr), 1);
	if($client['emergency']) saveClientContact('emergency', $ownerptr, $client['emergency']);
	if($client['neighbor']) saveClientContact('neighbor', $ownerptr, $client['neighbor']);
	if($client['custom']) 
		saveClientCustomFields($ownerptr, $client['custom'], $pairsOnly=true);
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(' - ', $petNames)."]<p>";
}

function findVetByName($nm) {
	return fetchRow0Col0("SELECT vetid FROM tblvet WHERE CONCAT_WS(' ', fname, lname)  = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findClinicByName($nm) {
	return fetchRow0Col0("SELECT clinicid FROM tblclinic WHERE clinicname = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findClientByName($nm) {
	return fetchRow0Col0("SELECT clientid FROM tblclient WHERE CONCAT_WS(' ', fname, lname) = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findSitterByName($nm) {
	return fetchRow0Col0("SELECT providerid FROM tblprovider WHERE CONCAT_WS(' ', fname, lname) = '".mysqli_real_escape_string($nm ? $nm : '')."' LIMIT 1");
}

function findProviderByNickname($nn) {
	return fetchRow0Col0("SELECT providerid FROM tblprovider WHERE nickname = '".mysqli_real_escape_string($nn ? $nn : '')."' LIMIT 1");
}

function handleRowCahills($row) {
	global $dataHeaders;
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		if(!$row[$i]) continue;
		if($label == '') ;
		else if($label == 'Client') handleLnameCommaFname($row[$i], $client);
		else if($label == 'Company Name') $petNames = handlePetNames($row[$i]);
		else if($label == 'Email') $client['email'] = $row[$i];
		else if($label == 'Phone Numbers') {
			foreach(array_map('trim', explode("\n", $row[$i])) as  $num) {
				if(strpos($num, 'Phone:') !== FALSE) $client['homephone'] = trim(substr($num, strlen('Phone:')));
				else if(strpos($num, 'Fax:') !== FALSE) $client['fax'] = trim(substr($num, strlen('Fax:')));
				else if(strpos($num, 'Mobile:') !== FALSE) $client['cellphone'] = trim(substr($num, strlen('Mobile:')));
			}
		}
		else if($label == 'Billing Address') handleAddress($row[$i], $client);
		else if($label == 'Note') $client['notes'] = $row[$i];
	}
	$ownerptr = saveNewClient($client);
	foreach($petNames as $name) insertTable('tblpet', array('name'=>$name, 'ownerptr'=>$ownerptr), 1);
	echo "Added client {$client['fname']} {$client['lname']} with pets [".join(' - ', $petNames)."]<p>";
}

function handleRowNannydolittle($row) {
	global $thisdb, $dataHeaders, $lastClient;
	// #	LAST 	FIRST	PET	TYPE	B-DAY	ADDRESS	CITY	ZIP	HOME 	CELL 	WORK 	SOURCE
	if(strpos($row[0], '-') === FALSE) { //non-primary row
		if($petname = $row[array_search('PET', $dataHeaders)]) {
			$pet = array('name'=>$row[array_search('PET', $dataHeaders)], 'type'=>$row[array_search('TYPE', $dataHeaders)]);
			if($dob = $row[array_search('B-DAY', $dataHeaders)]) 
				$pet['dob'] = date('Y-m-d', strtotime($dob));
			$pet['ownerptr'] = $lastClient;
			$petId = insertTable('tblpet', $pet, 1);
			echo "<br>... WITH PET: {$pet['name']}";
		}
		appendValue($row, 'LAST');
		appendValue($row, 'FIRST');
		appendValue($row, 'HOME');
		appendValue($row, 'CELL');
		appendValue($row, 'WORK');
		appendValue($row, 'SOURCE');
		appendValue($row, 'REASON');
		return;
	}
	$client = array('active'=>!$_REQUEST['inactive']);
	foreach($dataHeaders as $i => $label) {
		if(!$row[$i]) continue;
		if($label == '#') $custom2 = $row[$i];
		if($label == 'LAST') $client['lname'] = $row[$i];
		if($label == 'FIRST') $client['fname'] = $row[$i];
		if($label == 'PET' && $row[$i]) 
			$pet = array('name'=>$row[$i], 'type'=>$row[array_search('TYPE', $dataHeaders)]);
		if($label == 'B-DAY' && $pet) $pet['dob'] = date('Y-m-d', strtotime($row[$i]));
		if($label == 'ADDRESS') $client['street1'] = $row[$i];
		if($label == 'CITY') $client['city'] = $row[$i];
		$client['state'] = 'WA';
		if($label == 'ZIP') $client['zip'] = $row[$i];
		if($label == 'HOME') $client['homephone'] = $row[$i];
		if($label == 'CELL') $client['cellphone'] = $row[$i];
		if($label == 'WORK') $client['workphone'] = $row[$i];
		if($label == 'SOURCE') $source = $row[$i];
		if($label == 'REASON') $reason = $row[$i];
	}
	saveNewClient($client);
	echo "<p>CREATED CLIENT: {$client['fname']} {$client['lname']}";
	$newClientId = mysqli_insert_id();
	$lastClient = $newClientId;
	if($source) {
		replaceTable("relclientcustomfield", 
			array('clientptr'=>$newClientId, 'fieldname'=>'custom1', 'value'=>$source), 1);
		echo "<p>... SOURCE: $source";
	}
	if($reason) {
		replaceTable("relclientcustomfield", array('clientptr'=>$newClientId, 'fieldname'=>'custom3', 'value'=>$reason), 1);
		echo "<p>... REASON: $reason";
	}
	replaceTable("relclientcustomfield", array('clientptr'=>$newClientId, 'fieldname'=>'custom2', 'value'=>$row[0]), 1);
	if($pet) {
		$pet['ownerptr'] = $newClientId;
		$petId = insertTable('tblpet', $pet, 1);
		echo "<br>... WITH PET: {$pet['name']}";
	}
}

function getCSVRow($strm) {
	global $delimiter, $multiline;
	return $multiline ? mygetcsv($strm) : fgetcsv($strm, 0, $delimiter);
}

function mygetcsv($strm) {  // handles EOLS inside quotes, as long as quotes balance
	global $delimiter;
	// read/append lines until the quote count is even. tokenize line ends.
	$quoteCount = 0;
	$EOLN = "MY#END#OF#LINE";
	do {
		$line = fgets($strm);
		$line = str_replace("\r", "", $line);
		$line = str_replace("\n", $EOLN, $line);
		for($i=0; $i < strlen($line); $i++) if($line[$i] == '"') $quoteCount++;
		$multiline .= $line;
	}
	while($quoteCount % 2 == 1);
	$line = $multiline;
	
	// read the CSV
	$sstrm = fopen("data://text/plain,$line" , 'r');
	$csv = fgetcsv($sstrm, 0, $delimiter);

	// detokenize the CSV elements
	foreach($csv as $i => $v) $csv[$i] = str_replace($EOLN, "\n", $v);
	return $csv;
}

function OLDmygetcsv($strm) {  // handles EOLS inside quotes, as long as quotes balance
	global $delimiter;
	$quoteCount = 0;
	$totalCSV = array();
	do {
		$line = fgets($strm);
		$line = str_replace("\r", "", $line);
		for($i=0; $i < strlen($line); $i++) if($line[$i] == '"') $quoteCount++;
		$sstrm = fopen("data://text/plain,$line" , 'r');
		$csv = fgetcsv($sstrm, 0, $delimiter);
		if(!$totalCSV) $totalCSV = $csv;
		else {
			$totalCSV[count($totalCSV)-1] .= "\n".substr($csv[0], 0, strlen($csv[0])-1);
			for($i=1; $i < count($csv); $i++) $totalCSV[] = $csv[$i];
		}
	}
	while($quoteCount % 2 == 1);
	return $totalCSV;
}



function skipRow($row) {
	global $thisdb, $dataHeaders;
	if($thisdb == 'nannydolittle') {
		if($row[0] == '#') return true;
	}
}
	

function findCityState($zip) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	require_once "zip-lookup.php";
	extract($_REQUEST);
	$cityState = explode('|', lookUpZip($zip, 'noecho'));
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'force');
	return $cityState[0] ? $cityState : null;
}

function getValueAtHeader($row, $headerName) {
	global $dataHeaders;
	static $reverse;
	if(!$reverse)
		foreach($dataHeaders as $k => $v)
			$reverse[$v] = $k;
	return $row[$reverse[$headerName]];
}
