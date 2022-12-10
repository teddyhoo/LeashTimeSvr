<? // reports-remote-files-usage.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

locked('o-');

$breadcrumbs = "<a href='reports.php'>Reports</a>";	

if(!in_array('tblremotefile', fetchCol0("SHOW TABLES"))) {
	require "frame.html";
	echo "Client Documents have never been used in this business.";
	require "frame-end.html";
	exit;
}
$byClient = array();
foreach(fetchAssociations("SELECT * FROM tblremotefile", 1) as $f)
	$byClient[$f['ownerptr']][] = $f;

$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(', ', lname, fname) FROM tblclient", 1);

asort($clientNames);

foreach($byClient as $nm => $files) {
	$total +=  count($files);
}


require "frame.html";
echo "<h2Remote File Usage</h2>";

echo "$total files are stored remotely for ".count($byClient)." clients<p>";

foreach($clientNames as $id => $client) {
	if($byClient[$id]) // echo "$client: ".count($byClient[$id])."<br>";
	$rows[] = array('Client'=>$client, 'Count'=>count($byClient[$id]));
}
quickTable($rows, $extra=null, $style=null, $repeatHeaders=0);

	
require "frame-end.html";
