<? // geotrack.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$locked = locked('o-');

$codes = fetchAssociations(
	"SELECT tblgeotrack.*, CONCAT_WS(' ', fname, lname) as client
		FROM tblgeotrack 
		LEFT JOIN tblappointment ON appointmentid = appointmentptr
		LEFT JOIN tblclient ON clientid = clientptr
		ORDER BY date");

foreach($codes as $i => $code) {
  if($name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE userid = {$code['userptr']} LIMIT 1"))
  	$codes[$i]['user'] = $name;
  if(!$name && ($name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE userid = {$code['userptr']} LIMIT 1")))
  	$codes[$i]['user'] = $name;
}
$columns = explodePairsLine('user|User||date|Date||lat|Latitude||lon|Longitude||speed|Speed||heading|Heading||appointmentptr|Visit||client|Client');
tableFrom($columns, $codes, 'border=1 bordercolor=black', $class, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);