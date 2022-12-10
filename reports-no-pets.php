<? // reports-no-pets.php
// report clients with no pets (without pets)
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

// Determine access privs
$locked = locked('o-');

$pageTitle = "Clients without Pets";
include "frame.html";

$clients = fetchAssociations("SELECT clientid, lname, fname, notes, CONCAT_WS(' ', street1, zip) as addr FROM tblclient WHERE active=1 ORDER BY lname, fname");

echo "<table border=1><tr><th>Client<th>Address<th>Pet?";
foreach($clients as $client) {
	$pets = fetchRow0Col0("SELECT COUNT(*) FROM tblpet WHERE ownerptr = {$client['clientid']}", 1);
	if(!$pets) {
		$petless += 1;
		$pets = $client['notes'] ? $client['notes'] : '';
		if($pets) {
			$pets = explode("\n", $pets);
			$pet = '';
			$i = 0;
			for($i=0; $i < count($pets); $i++) {
				if(strpos($pets[$i], 'isit') 
					|| strpos($pets[$i], 'ransaction') 
					|| strpos($pets[$i], 'Spend')) continue;
				else {
					$pet = $pets[$i];
					break;
				}
			}
		}
		//$pet = print_r($client['notes'], 1);
		echo "<tr><td>";
		fauxLink("{$client['fname']} {$client['lname']}", "openClient({$client['clientid']})");
		echo "</td><td>{$client['addr']}</td><td>$pet</td>";
		echo "</tr>";
	}
}
echo "</table>";
echo "Petless clients: $petless";

$clients = fetchAssociations("SELECT clientid, lname, fname, CONCAT_WS(' ', street1, zip) as addr FROM tblclient 
WHERE active=1 AND (lname LIKE 'UNKNOWN%' OR fname LIKE 'UNKNOWN%')
ORDER BY lname, fname");
echo "<h2>\"Unknown\" Clients (".count($clients).")</h2><table border=1><tr><th>Client<th>Address<th>Pet?";
foreach($clients as $client) {
	$pets = fetchCol0("SELECT name FROM tblpet WHERE ownerptr = {$client['clientid']}", 1);
	$pets = $pets ? join(", ", $pets) : "<i>None</i>";
	echo "<tr><td>";
	fauxLink("{$client['fname']} {$client['lname']}", "openClient({$client['clientid']})");
	echo "</td><td>{$client['addr']}</td><td>$pets</td>";
	echo "</tr>";
}
echo "</table>";

include "frame-end.html";
?>
<script src='common.js'></script>
<script>
function openClient(id) {
	var url = "client-edit.php?tab=basic&id="+id;
	openConsoleWindow('petless', url,800, 600);
}
</script>