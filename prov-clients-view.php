<?
//prov-clients-view.php

require_once "common/init_session.php";
include "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "pet-fns.php";

// Verify login information here
locked('o-');
extract($_REQUEST);

if(!isset($provider)) $error = "Sitter ID not specified.";
else {
  $prov = getProvider($provider);
  $title = fullname($prov)."'s Clients";
}


$windowTitle = $title;
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

?>
<div style='padding: 10px;padding-top:0px;'>
<h2><?= $title ?></h2>
As of: <?= shortDateAndTime(time()) ?><p>
<?
$clients = fetchAssociationsKeyedBy("SELECT clientid, CONCAT_WS(' ', tblclient.fname, tblclient.lname) as clientname 
                              FROM tblclient WHERE active AND defaultproviderptr = $provider ORDER BY tblclient.lname, tblclient.fname", 'clientid');
echo "<b>Designated clients:</b> <p>";                              
if($clients) {
  $pets = getPetNamesForClients(array_keys($clients));
  echo "<table style='font-size:1.1em'>";
	foreach($clients as $client) {
  	echo "<tr><td>";
  	fauxLink($client['clientname'], "openConsoleWindow(\"viewclient\", \"client-view.php?id={$client['clientid']}\",700,500)'");
  	echo "</td><td>{$pets[$client['clientid']]}</tr>";
	}
  echo "</table>";
}
else echo /*fullname($prov).*/'none.';

$today = date('Y-m-d');
if($clients) $exclude = "AND clientptr NOT IN (".join(',', array_keys($clients)).")";
$clients = fetchCol0("SELECT distinct clientptr 
											FROM tblappointment 
											WHERE providerptr = $provider 
												AND date >= '$today'
												$exclude");

echo "<p><b>Assigned to future visits for:</b> <p>";                              
if($clients) {
  $pets = getPetNamesForClients($clients);
	$clients = fetchAssociations("SELECT clientid, CONCAT_WS(' ', tblclient.fname, tblclient.lname) as clientname 
                              FROM tblclient WHERE clientid IN (".join(',', $clients).")");
  echo "<table style='font-size:1.1em'>";
	foreach($clients as $client) {
  	echo "<tr><td>";
  	fauxLink($client['clientname'], "openConsoleWindow(\"viewclient\", \"client-view.php?id={$client['clientid']}\",700,500)'");
  	echo "</td><td>{$pets[$client['clientid']]}</tr>";
	}
  echo "</table>";
}
else echo /*fullname($prov).*/'no clients.';
?>
</div>
<script language='javascript'>
<?  ?>
function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

</script>
</body>
</html>
