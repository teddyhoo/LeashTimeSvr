<? // possible-duplicate-clients.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require "client-fns.php";
require "pet-fns.php";

$locked = locked('o-');

extract(extractVars('fname,lname,justNames', $_REQUEST));  

$orderClause = "ORDER BY lname, fname";
$dbFname = mysqli_real_escape_string($fname);
$dbLname = mysqli_real_escape_string($lname);
$exact = fetchAssociationsKeyedBy("SELECT * FROM tblclient WHERE fname = '$dbFname' AND lname = '$dbLname' $orderClause", 'clientid');

$allKeys = array_keys($exact);

$fInitial = mysqli_real_escape_string($fname) ? $fname[0] : null;
if($fInitial) {
	$close = fetchAssociationsKeyedBy("SELECT * FROM tblclient WHERE fname like '$fInitial%' AND lname = '$dbLname' $orderClause", 'clientid');
	$keep = array_diff(array_keys($close), $allKeys);
	foreach(array_keys($close) as $key) if(!in_array($key, $keep)) unset($close[$key]);
	$allKeys = array_merge($allKeys, $keep);
}
	
if($lname) {
	$lastMatch = fetchAssociationsKeyedBy("SELECT * FROM tblclient WHERE lname = '$dbLname' $orderClause", 'clientid');
	$keep = array_diff(array_keys($lastMatch), $allKeys);
	foreach(array_keys($lastMatch) as $key) if(!in_array($key, $keep)) unset($lastMatch[$key]);
	$allKeys = array_merge($allKeys, $keep);
}
foreach(array($exact, $close, $lastMatch) as $arr) {
	if($arr) {
		foreach($arr as $client) {
			if($justNames) {
				$names[] = "{$client['fname']} {$client['lname']}";
				if(!$client['active']) $names[count($names)-1] .= " (inactive)";
			}
			else {
				$emailphone = array();
				if($client['email']) $emailphone[] = $client['email'];
				if(primaryPhoneNumber($client)) $emailphone[] = primaryPhoneNumber($client);
				if($emailphone) $emailphone = join(', ', $emailphone);
				$address = array();
				foreach(array('street1', 'street2', 'city') as $fiels) $address[] = $client[$field];
				$row = array('name'=>clientLink($client), 'emailphone'=>$emailphone, 
										'pets'=>getClientPetNames($client['clientid']), 
										'address'=>truncatedLabel(oneLineAddress($address), 24));
				$rows[] = $row;
			}
		}
	}
}
if($justNames) {
	echo $names ? join('|', $names) : '';
	exit;
}
?>
<h3>These existing clients have names similar to <?= "$fname $lname" ?></h3>
<?
$columns = explodePairsLine('name|Client||emailphone|Email/Phone||pets|Pets||address|Address');
tableFrom($columns, $rows, 'width=100%; style="border:solid black 1px;margin-left:5px; "', $class, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);

function clientLink($client) {
	$inactive = $client['active'] ? '' : '<font color=red> (inactive)</font>';
	return "{$client['fname']} {$client['lname']}$inactive";
}