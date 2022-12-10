<? // request-vcard.php
set_include_path(get_include_path().':'.'/var/www/prod/vcard-master/src');
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "VCard.php";
require_once "request-fns.php";
require_once "client-fns.php";

use JeroenDesloovere\VCard\VCard;

locked('o-');

$source = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$_REQUEST['requestid']} LIMIT 1");
if(!$source) {echo "Bad request.";exit;}
if($source['clientptr']) {
	$client = getOneClientsDetails($source['clientptr'], array('address', 'phone', 'lname', 'fname', 'activepets', 'email', 'street1', 'street2', 'city', 'state', 'zip'));
	$source['address'] = $source['address'] ? $source['address'] : $client['address'];
	$source['phone'] = $source['phone'] ? $source['phone'] : $client['phone'];
	$source['fname'] = $source['fname'] ? $source['fname'] : $client['fname'];
	$source['lname'] = $source['lname'] ? $source['lname'] : $client['lname'];
	$source['email'] = $source['email'] ? $source['email'] : $client['email'];
	$source['phone'] = $source['phone'] ? $source['phone'] : $client['phone'];
	if($client['pets']) $petsToShow[] = join(", ",$client['pets']); // activepets
	if($source['pets']) $petsToShow[] = $client['pets'] ? "(from request) {$source['pets']}" : $source['pets'];
	if($petsToShow) $petsToShow = $petsToShow ? join(", ",$petsToShow) : '';
}
//print_r($source);
$vcard = new VCard();
// define variables
$lastname = $source['lname'];
$firstname = $source['fname'];
$additional = '';
$prefix = '';
$suffix = '';

// add personal data
$vcard->addName($lastname, $firstname, $additional, $prefix, $suffix);

// add work data
//$vcard->addCompany('Siesqo');
//$vcard->addJobtitle('Web Developer');
if($source['email']) $vcard->addEmail($source['email']);
$vcard->addPhoneNumber($source['phone'], 'PREF;HOME');
//$vcard->addPhoneNumber(123456789, 'WORK');
$add = addressParts($source);
//$vcard->addAddress(null, $add['extended'], $add['street'], $add['city'], $add['region'], $add['zip'], null, 'HOME:POSTAL');
//print_r($add);exit;
$fullname = null;//"$firstname $lastname";
$vcard->addAddress($fullname, $add['extended'], $add['street'], $add['city'], $add['region'], $add['zip'], null, 'HOME;POSTAL');
if($petsToShow) $vcard->addNote($petsToShow);
/*
        $name = '',
        $extended = '',
        $street = '',
        $city = '',
        $region = '',
        $zip = '',
        $country = '',
        $type = 'WORK;POSTAL'
*/
//$vcard->addURL('http://www.jeroendesloovere.be');

//$vcard->addPhoto(__DIR__ . '/landscape.jpeg');

// return vcard as a string
//return $vcard->getOutput();

// return vcard as a download
$vcard->download(); //getOutput();

function addressParts($source) {
	if($source['address']) {
		$arr = array();
		//$source = handleAddress($val, $arr);		
	}
	return
		array(
			'extended'=> $source['street2'],
			'street'=> $source['street1'],
			'city'=> $source['city'],
			'region'=> $source['state'],
			'zip'=> $source['zip'],
			'country'=> 'USA' //$source['zip']
			);
}

function handleAddress($val, &$client) {
	$addr = array_map('trim', explode("\n", $val));
	if(!$addr) return;
	getCityStateZip(array_pop($addr), $client);
	if(count($addr) > 1) $client['street2'] = $addr[1];
	if($addr) $client['street1'] = $addr[0];
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
