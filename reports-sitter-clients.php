<? // reports-sitter-clients.php


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";

$locked = locked('o-#vr');

$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient ORDER BY lname, fname");
$sitters = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) FROM tblprovider ORDER BY lname, fname");

$result = doQuery("SELECT DISTINCT concat( providerptr, '_', clientptr ) FROM tblappointment WHERE completed");

if($_REQUEST['byclient']) {
	while($row = mysql_fetch_row($result)) {
		$pair = explode('_', $row[0]);
		$sitterclients[$pair[1]][] = $pair[0];
	}
	echo "<h2>Sitters Who Have Served Clients</h2>";
	echo "<table>";
	foreach($clients as $id => $clientname) {
		if(!$sitterclients[$id]) continue;
		echo "<tr><td><b>$clientname (".count($sitterclients[$id])." sitters)</b></td></tr>";
		foreach($sitters as $sitterptr => $name)
			if(in_array($sitterptr, $sitterclients[$id])) 
				echo "<tr><td>{$sitters[$sitterptr]}</td></tr>";
	}
	echo "</table>";
}
else {
	while($row = mysql_fetch_row($result)) {
		$pair = explode('_', $row[0]);
		$sitterclients[$pair[0]][] = $pair[1];
	}

	echo "<h2>Clients Served by Sitters</h2>";
	echo "<table>";
	foreach($sitters as $id => $provname) {
		if(!$sitterclients[$id]) continue;
		echo "<tr><td><b>$provname (".count($sitterclients[$id])." clients)</b></td></tr>";
		foreach($clients as $clientptr => $name)
			if(in_array($clientptr, $sitterclients[$id])) 
				echo "<tr><td>{$clients[$clientptr]}</td></tr>";
	}
	echo "</table>";
}