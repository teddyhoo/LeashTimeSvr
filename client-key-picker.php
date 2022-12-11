<?
// client-key-picker.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
include "gui-fns.php";
include "key-fns.php";
include "provider-fns.php";
include "pet-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
//$locked = locked('o-');
locked('+ka,+ki,+#km');

extract($_REQUEST);

$match = $loc == 'all' ? 'IS NOT NULL' : ($loc == 'safe' ? "like 'safe%'" :  "like 'safe%'");

$baseQuery = "SELECT clientid, defaultproviderptr, CONCAT_WS(' ',fname,lname) as name, CONCAT_WS(', ',lname,fname) as sortname, CONCAT_WS(', ',street1, city) as address,
		CONCAT_WS(',',
			if(possessor1 $match,concat('1-',possessor1),''),
			if(possessor2 $match,concat('2-',possessor2),''),
			if(possessor3 $match,concat('3-',possessor3),''),
			if(possessor4 $match,concat('4-',possessor4),''),
			if(possessor5 $match,concat('5-',possessor5),'')) as safekeys, keyid, bin
		FROM tblclient
		LEFT JOIN tblkey ON clientptr = clientid
                  WHERE active";
$orderBy = "ORDER BY lname, fname";
$limit = "LIMIT 15";
if(isset($pattern)) {
  if(strpos($pattern, '*') !== FALSE) $pattern = str_replace  ('*', '%', $pattern);
  else $pattern = "%$pattern%";
  $patternQuery = "$baseQuery AND CONCAT_WS(' ',fname,lname) like '$pattern'";
  $clientIdsForMatchingPets = fetchCol0("SELECT ownerptr FROM tblpet WHERE name like '$pattern'");
  if($clientIdsForMatchingPets) {
  	$patternQuery .= " OR clientid IN (".join(',', $clientIdsForMatchingPets).")";
	}
  $numFound = mysqli_num_rows(mysqli_query($patternQuery));
  if($numFound)
    $clients = fetchAssociations("$patternQuery $orderBy $limit");  
}
else if(isset($linitial)) {
  $baseQuery = "$baseQuery AND lname like '$linitial%'";
  $clients = fetchAssociations("$baseQuery $orderBy");
  $numFound = count($clients);
}
else {
  $clients = fetchAssociations("$baseQuery $orderBy $limit");
  $numFound = count($clients);
}
?>
<head><title>Pick a Client</title>
<style>
.results td {padding-left: 10px;}
.results th {padding-left: 10px;}
</style>
</head>
<body style='margin-left: 10px;'>
<link href="style.css" rel="stylesheet" type="text/css" />
<link href="pet.css" rel="stylesheet" type="text/css" />
<h2>Find a Client</h2>
<?  echoButton('', 'Close', 'window.close()', 'closeButton', 'closeButtonDown'); ?>
<form name=findclients method=post>
<input name=target type=hidden value='<?= $target ?>'>
<input name=pattern size=10 autocomplete='off'> <? echoButton('', 'Search', "document.location.href=\"client-key-picker.php?loc=$loc&target=$target&pattern=\"+document.findclients.pattern.value") ?>
</form>
<p>
<?
for($i = ord('A'); $i <= ord('Z'); $i++) {
  $c = chr($i);
  echo " <a href=client-key-picker.php?linitial=$c&target=$target&loc=$loc>$c</a>";
  if($c != 'Z') echo "-";
}
?>
<p>
<?
if(isset($baseQuery)) {
  echo ($numFound ? $numFound : 'No')." clients found.  ";
  if($numFound > count($clients)) echo count($clients)." shown.";
?>
<p>

<table class='results'>
<tr><th>Client / Pets</th><th>Key Hook</th><th>Available Keys</th><th>Address</th></tr>
<?
function keyLinks($keyId, $safeKeys) {
	if(!$keyId) return '';
  foreach(explode(',',$safeKeys) as $copy) {
		$copyAndLoc = explode('-', $copy);
		$loc = keyLoc($copyAndLoc[1]);
		$fullKeyId = formattedKeyId($keyId, $copyAndLoc[0]);
		if($copy) $s[] = "<a href=# onClick='pickKey(\"$fullKeyId\")''>$fullKeyId ($loc)</a>";
	}
	return !$s ? '' : join(', ', $s);
}

function keyLoc($loc) {
	global $safes, $providerNames;
	if(isset($safes[$loc])) return trim(str_replace('--','',$safes[$loc]));
	else if($loc == 'client') return 'Client';
	else if($loc == 'missing') return 'Missing';
	else return $providerNames[$loc];
}
$color = 'white';
if($clients) {
	foreach($clients as $client) $ids[] = $client['clientid'];
	$pets = getPetNamesForClients($ids);

	$color = 'white';
	$providerNames = getProviderShortNames();;
	foreach($clients as $client) {
		$color = $color == 'white' ? 'lightgrey' : 'white';
		$name = addslashes($client['name']);
		$address = $client['address'];
		$petNames = $pets[$client['clientid']] ? $pets[$client['clientid']] : '<i>No Pets</i>';
		if($address[0] == ",") $address = substr($address, 1);
		echo "<tr style='background-color:$color'><td>{$client['name']}<br><span style='color:green'>$petNames</span></td><td>{$client['bin']}</td>
		<td>".keyLinks($client['keyid'], $client['safekeys'])."
		<td>$address</td></tr>\n";
	}
}

?>
</table>

<script>
function pickKey(id) {
  //if(window.opener.keyPicked) window.opener.keyPicked(id);
  //alert(window.opener.printKeyLabel);
  if(window.opener && window.opener.update) window.opener.update(id);
  <? if($_SESSION["mobiledevice"] || $_SESSION["tabletdevice"]) echo "\nwindow.close();"; 
  ?>
}
</script>
<?
}
?>
</body>