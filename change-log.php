<? //change-log.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "provider-fns.php";

$sql = "SELECT * FROM tblchangelog ORDER BY time";

$result = mysqli_query($sql);
?>
<table border=1 bordercolor=black><tr><th>Time</th><th>Operation</th><th>Object</th><th>ID</th><th>User</th><th>Note</th></tr>
<?
$objects = array('tblservicepackage'=>'Non-Rec Package','tblrecurringpackage'=>'Recurring Package','tblappointment'=>'Appointment',
		  'tblclient'=>'Client', 'tblprovider'=>'Provider');
$clientDetails = getClientDetails(fetchCol0("SELECT clientid FROM tblclient"));
$providerNames = getProviderShortNames();
$ops = array('c'=>'create','m'=>'modify','d'=>'delete');

function objectType($object) {
	global $objects;
	return isset($objects[$object]) ? $objects[$object] : $object;
}	

function objectLabel($object, $id) {
	global $clientDetails, $providerNames;
	if($object == 'tblclient') return $clientDetails[$id]['clientname']."($id)";
	else if($object == 'tblprovider') return $providerNames[$id]."($id)";
	return $id;
}

function merge_names_in($str) {
	$table = strpos($str, 'rovider') ? 'tblprovider' : (strpos($str, 'lient') ? 'tblclient' : 'XXX');
	while(($start = strpos($str, '[')) !== FALSE) {
		$end = strpos($str, ']');
		//echo "<P>$str<br>".substr($str, $start+1, $end-($start+1));
		$label = objectLabel($table, substr($str, $start+1, $end-($start+1)));
		$str = str_replace(substr($str, $start, $end+1-$start), $label, $str);
	}
	return $str;
}

while($row = mysqli_fetch_assoc($result)) {
	$data = array();
	$data[] = date('m/d/Y H:i:s', strtotime($row['time']));
	$data[] = $ops[$row['operation']];
	$data[] = objectType($row['itemtable']);
	$data[] = objectLabel($row['itemtable'], $row['itemptr']);
	$data[] = $row['user'];
	$data[] = merge_names_in($row['note']);
	echo '<tr><td>'.join('</td><td>',$data)."</td></tr>\n";
}
?>
</table>
