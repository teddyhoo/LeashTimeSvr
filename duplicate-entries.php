<? // duplicate-entries.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$locked = locked('o-');

$table = $_GET['table'];
$address = "CONCAT_WS(' | ', street1,street2,city,state,zip) as address";
$nameField = array(
	'tblclient'=>"CONCAT_WS(' ', fname,lname)",
	'tblprovider'=>"CONCAT_WS(' ', fname,lname)",
	'tblclinic'=>"clinicname");
$nameField = $nameField[$table];
$fields = array(
	'tblclient'=>"clientid as id,$nameField as name, active, $address, CONCAT_WS(' | ', homephone, cellphone, workphone) as phones",
	'tblprovider'=>"providerid as id,$nameField as name, active, $address, CONCAT_WS(' | ', homephone, cellphone, workphone) as phones",
	'tblclinic'=>"clinicid as id,$nameField as name, $address, CONCAT_WS(' | ', homephone, cellphone, officephone) as phones");
$fields = $fields[$table];
$groups = fetchAssociationsKeyedBy("SELECT count(*) as num, $fields FROM $table GROUP BY $nameField", 'name');

foreach($groups as $group) if($group['num'] > 1) $duplicateNames[] = $group['name'];

if(!$duplicateNames) {
	echo "No duplicate names in $table.";
	exit;
}
//print_r($groups);
//echo '<hr>'.join('<br>', $duplicateNames).'<hr>';

foreach($duplicateNames as $name) 
	foreach(fetchAssociations("SELECT $fields FROM $table WHERE $nameField = '$name'") as $row)
		$duplicates[] = $row;

require_once "gui-fns.php";

quickTable($duplicates);
