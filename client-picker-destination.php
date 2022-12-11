<?
// client-picker-destination.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
include "gui-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);
$recurring = isset($recurring) ? $recurring : 0;

$baseQuery = "SELECT clientid, packageid, CONCAT_WS(' ',fname,lname) as name, CONCAT_WS(', ',street1, city) as address 
							FROM tblclient
							LEFT JOIN tblrecurringpackage ON clientid = clientptr
              WHERE active AND (packageid is null OR tblrecurringpackage.current=1)";

if(isset($pattern)) {
  if(strpos($pattern, '*') !== FALSE) $pattern = str_replace  ('*', '%', $pattern);
  else $pattern = "%$pattern%";
  $baseQuery = "$baseQuery AND CONCAT_WS(' ',fname,lname) like '$pattern'";
  $numFound = mysqli_num_rows(mysqli_query($baseQuery));
  if($numFound)
    $clients = fetchAssociations("$baseQuery ORDER BY lname, fname LIMIT 15");
}
else if(isset($linitial)) {
  $baseQuery = "$baseQuery AND lname like '$linitial%' ORDER BY lname, fname";
  $clients = fetchAssociations("$baseQuery");
  $numFound = count($clients);
}
else {
  $baseQuery = "$baseQuery ORDER BY lname, fname LIMIT 15";
  $clients = fetchAssociations("$baseQuery");
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
<h2>Pick a Client</h2>
<form name=findclients method=post>
<input name=target type=hidden value='<?= $target ?>'>
<input name=pattern size=10 autocomplete='off'> <? echoButton('', 'Search', "document.location.href=\"client-picker.php?target=$target&pattern=\"+document.findclients.pattern.value") ?>
</form>
<p>
<?
for($i = ord('A'); $i <= ord('Z'); $i++) {
  $c = chr($i);
  echo " <a href=client-picker.php?linitial=$c&target=$target>$c</a>";
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
<tr><th>Client</th><th>Address</th></tr>
<?
foreach($clients as $client) {
  $name = htmlentities($client['name'], ENT_QUOTES);
  $address = $client['address'];
  if($address[0] == ",") $address = substr($address, 1);
  echo "<tr><td><a href=# onClick='pickClient({$client['clientid']}, \"$name\", \"{$client['packageid']}\")'>{$client['name']}</a></td><td>$address</td></tr>\n";
}
?>
</table>

<script>
function pickClient(id, clientname, packageid) {  // add a parameter to this page: 'recurring' and go to "packageid=" only when it is true
	var recurring = <?= $recurring ?>;
	var packageid = packageid ? packageid : '0';
	if(recurring && packageid != '0') parameter = "packageid="+packageid;
	else parameter = "client="+id;
  window.opener.document.location.href='<?= $dest ?>.php?'+parameter;
  window.close();

}
</script>
<?
}
?>
</body>