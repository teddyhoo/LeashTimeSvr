<? // intake-form-client.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "intake-form-fns.php";
require_once "custom-field-fns.php";
?>
<style>
.blankline {display:inline;border-bottom:solid black 1px;}
.linetable {display:block;}
.linetable td {padding-top:5px; padding-left:0px;}
.sectionhead {font-weight:bold;}
.checked {font-decoration:underline;font-weight:bold;}
</style>
<?
if($_REQUEST['id']) {
	require_once "client-fns.php";
	require_once "vet-fns.php";
	$client = getClient($_REQUEST['id']);
	foreach(getOneClientsDetails($_REQUEST['id'], $additionalFields=null) as $k => $v)
		$client[$k] = $v;
	if($client['vetptr']) $client['vet'] =  fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblvet WHERE vetid = {$client['vetptr']} LIMIT 1");
	if($client['clinicptr']) $client['clinic'] =  fetchRow0Col0("SELECT clinicname FROM tblclinic WHERE clinicid = {$client['clinicptr']} LIMIT 1");
}
$clientName = $client ? $client['clientname'] : $_REQUEST['clientname'];
echo "<h2>Client Intake form".($clientName ? " for client $clientName" : '')."</h2>";


$space = "<img src='art/spacer.gif' width=30 height=1>";


if(!$clientName) oneLineEntry('Client Name');
oneLineEntry('Client Email', 300, false, $client['email']);
phoneLineEntry('Home phone', 300, false, $client['homephone']);
phoneLineEntry('Cell phone', 300, false, $client['cellphone']);
phoneLineEntry('Work phone', 300, false, $client['workphone']);
oneLineEntry('FAX', 300, false, $client['fax']);
oneLineEntry('Pager', 300, false, $client['pager']);
echo "<hr>";
$altName = $client['fname2'] || $client['lname2'] ? "{$client['fname2']} {$client['lname2']}" : '';
oneLineEntry('Alt Name', 300, false, $altName);
oneLineEntry('Alt Email', 300, false, $client['email2']);
phoneLineEntry('Alt phone', 300, false, $client['cellphone2']);
//echo "<hr>";
oneLineEntry('Veterinary Clinic', 300, false, $client['clinic']);
oneLineEntry('Veterinarian', 300, false, $client['vet']);
oneLineEntry('Referral');
textBox('Notes', $width=700, $height=100, $client['notes']);

newSection("Address");
echo "Home Address";
oneLineEntry('Address',400, false, $client['street1']);
oneLineEntry('Address',400, false, $client['street2']);
lineStart();
oneLineEntry('City',200, true, $client['city']);
oneLineEntry('State',75, true, $client['state']);
oneLineEntry('ZIP',100, true, $client['zip']);
lineEnd();

echo "<hr>Mailing Address$space";oneLineCheckbox('Use Home Address',1);

oneLineEntry('Address',400, false, $client['mailstreet1']);
oneLineEntry('Address',400, false, $client['mailstreet2']);
lineStart();
oneLineEntry('City',200, true, $client['mailcity']);
oneLineEntry('State',75, true, $client['mailstate']);
oneLineEntry('ZIP',100, true, $client['mailzip']);
lineEnd();

newSection("Home Info");
oneLineEntry('Leash / Pet Carrier Location',500, false, $client['leashloc']);
oneLineEntry('Food Location',500, false, $client['foodloc']);
oneLineEntry('Parking Info',500, false, $client['parkinginfo']);
oneLineEntry('Garage / Gate Code',500, false, $client['garagegatecode']);
textBox('Directions to Home', $width=700, $height=100, $client['directions']);

newSection("Key");
oneLineCheckbox('No Key Required', false, $client['nokeyrequired']);
$key = fetchFirstAssoc("SELECT * FROM tblkey WHERE clientptr = '{$client['clientid']}' LIMIT 1");
oneLineEntry('Lock Location',500, false, $key['locklocation']);
oneLineEntry('Description',500, false, $key['description']);

newSection("Alarm");
oneLineEntry('Alarm Company',500, false, $client['alarmcompany']);
oneLineEntry('Alarm Company Phone',200, false, $client['alarmcophone']);
textBox('Alarm Info', $width=700, $height=100, $client['alarminfo']);

newSection("Emergency");
$contact = fetchFirstAssoc("SELECT * FROM tblcontact WHERE clientptr = '{$client['clientid']}' AND type = 'emergency' LIMIT 1");
oneLineEntry('Emergency Contact', 500, false, $contact['name']);
oneLineEntry('Location', 500, false, $contact['location']);
phoneLineEntry('Home phone', 300, false, $contact['homephone']);
phoneLineEntry('Cell phone', 300, false, $contact['cellphone']);
phoneLineEntry('Work phone', 300, false, $contact['workphone']);
oneLineCheckbox('Has Key to the house', $noRow=false, $contact['note']);
textBox('Note', $width=700, $height=100, $contact['note']);

$contact = fetchFirstAssoc("SELECT * FROM tblcontact WHERE clientptr = '{$client['clientid']}' AND type = 'neighbor' LIMIT 1");
oneLineEntry('Trusted Neighbor', 500, false, $contact['name']);
oneLineEntry('Location', 500, false, $contact['location']);
phoneLineEntry('Home phone', 300, false, $contact['homephone']);
phoneLineEntry('Cell phone', 300, false, $contact['cellphone']);
phoneLineEntry('Work phone', 300, false, $contact['workphone']);
oneLineCheckbox('Has Key to the house', $noRow=false, $contact['haskey']);
textBox('Note', $width=700, $height=100, $contact['note']);


newSection("Custom Fields");
$fields = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'custom%'");
$order = array_keys(customFieldDisplayOrder('custom'));
 
$clientCustom = fetchKeyValuePairs("SELECT fieldname, value FROM relclientcustomfield WHERE clientptr = '{$client['clientid']}'");
foreach($order as $key) {
	$field = $fields[$key];
	$descr = explode('|', $field);
	if(!$descr[1] || !$descr[3]) continue; // private
	if($descr[2] == 'oneline') oneLineEntry($descr[0], 500, false, $clientCustom[$key]);
	if($descr[2] == 'boolean') {
		$checked = $client['clientid'] ? $clientCustom[$key] : noBooleanValue();
		oneLineBoolean($descr[0], $noRow=false, $checked);
	}
	if($descr[2] == 'text') textBox($descr[0], $width=700, $height=100, $clientCustom[$key]);

}

function compareZ($a, $b) {
	$a = substr($a, strlen('custom'));
	$b = substr($b, strlen('custom'));
	if($a < $b) return -1;
	else if($a > $b) return 1;
}
