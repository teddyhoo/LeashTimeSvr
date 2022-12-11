<?// import-pet-birthdays-pops.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";

//$rawReferralTypes = '?,Unknown,C,Client,F,Friend,G,Vet,N,Advertisement,U,Website/websearch,X,Unknown,Z,Petsitter,H,Adopt-a-Hwy Sign,D,Direct Mail,E,Email,P,PSI,Y,Yellow Pages Directory,A,?';

//$rawReferralTypes = '?,Unknown,H,Unknown,5,AAA,4,Apparel/Promotional Giveaway,J,Business Card/Business Card Magnet,A,Car Magnet/Decal,C,Client,3,Corporate Partner,B,Craigslist,E,Direct Email,D,Direct Mail,K,Door Hanger/Brochure/Flyer,V,Google,L,Groomer,I,Industry Event,1,Networking Group,O,Online Yellow Pages,Q,Other Online Directory,U,Other Online Directory,N,Paid Print Ad,Z,Pet Sitter,P,Pet Sitters International/Petsit.com,2,Pet Store,7,PETCO,W,Printed White Pages,Y,Printed Yellow Pages,R,Radio,F,Respond.com,8,Systino Referral,T,Television,S,Trainer,G,Vet,M,Word Of Mouth,X,Yahoo!';
extract($_REQUEST);

$file = "/var/data/clientimports/$file";


echo "<hr>";

$delimiter = strpos($file, '.xls') ? "\t" : ',';
$strm = fopen($file, 'r');
$line0 = fgets($strm);
$delimiter = strpos($line0, "\t") ? "\t" : ',';
rewind($strm);
$dataHeaders = array_map('trim', fgetcsv($strm, 0, $delimiter));// consume first line (field labels)

echo "dataHeaders: ".count($dataHeaders).':<br>'.join(',', $dataHeaders).'<hr>';

$n = 1;

$noPetsAtAll = !fetchAssociations("SELECT * FROM tblpet");
$petsModified = 0;
$sexes = explodePairsLine('Female|f||Male|m');
while($row = fgetcsv($strm, 0, $delimiter)) {
	$n++;
	// HANDLE EMPTY LINES
	if(!$row) {echo "<font color=red>Empty Line #$n</font><br>";continue;}
	// HANDLE CONTINUATIONS OF INCOMPLETE LINES
	$clientLabel = rowAtHeader($row, 'Client');
	if($clientLabel) {
		$clients = fetchAssociations("SELECT * FROM tblclient WHERE CONCAT_WS(' ', fname, lname) = '".mysqli_real_escape_string($clientLabel)."'");
		if(!$clients) echo "<font color=red>Client [$clientLabel] not found.<br></font>";
		else if(count($clients) > 1) echo "<font color=red>Client name [$clientLabel] refers to ".count($clients)." clients.<br></font>";
		if(count($clients) != 1) continue;
		$client = count($clients) == 1 ? $clients[0] : null;
	}
	$clientid = $client['clientid'];
	$pet = rowAtHeader($row, 'Name');
	$pets = fetchAssociations("SELECT * FROM tblpet WHERE ownerptr = $clientid AND name = '".mysqli_real_escape_string($pet)."'");
	if(!$pets) {
		echo "<font color=red>Pet [$pet] not found.<br></font>";
		//continue;
		// create pet if there are no pets at all
		if(TRUE || $noPetsAtAll) {
			$pets = array(array('name'=>$pet, 'type'=>rowAtHeader($row, 'Type'), 'ownerptr'=>$clientid));
			insertTable('tblpet', $pets[0], 1);
			echo "<font color=red>CREATED [$pet].<br></font>";
			$pets[0]['petid'] = mysqli_insert_id();
		}
	}
	else if(count($pets) > 1) echo "<font color=orange>Pet name [$pet] refers to ".count($pets)." pets owned by $clientLabel.<br></font>";
	$pet = $pets[0];
	foreach((array)$pet as $k => $v) if(!$v) unset($pet[$k]);
	
	$sex = $sexes[rowAtHeader($row, 'Gender')];
	$mod = array('sex'=>$sex);
	if(rowAtHeader($row, 'Month') != 'N/A') {
		$year = rowAtHeader($row, 'Year') == 'N/A' ? date('Y') : rowAtHeader($row, 'Year');
		$day = rowAtHeader($row, 'Day') == 'N/A' ? 1 : rowAtHeader($row, 'Day');
		$dob = rowAtHeader($row, 'Month').' '.$day.', '.$year;
		$dob = date('Y-m-d', strtotime($dob));
		$mod['dob'] = $dob;
	}
	echo print_r($pet, 1)." mod: ".print_r($mod, 1)."<br>";
	updateTable('tblpet', $mod, "petid = {$pet['petid']}", 1);
	$petsModified++;

}
echo "<hr>$n pets considered.<br>";
if($petsModified) echo "Modified $petsModified pets.<hr>";
echo "<hr>";

function headerIndex($header) {
	global $dataHeaders;
	return array_search($header, $dataHeaders);
}

function rowAtHeader($row, $header) {
	return trim($row[ headerIndex($header)]);
}

