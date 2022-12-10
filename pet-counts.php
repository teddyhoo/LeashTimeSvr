<? // pets-counts.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

locked('o-');
$unspecifiedLabel = '--unspecified--';
$petcounts = fetchKeyValuePairs("SELECT IFNULL(type, '$unspecifiedLabel') as type, COUNT(*) as count FROM tblpet GROUP BY type",1);

if($_GET['from']) {
	$fromtest = $_GET['from'] == $unspecifiedLabel ? " IS NULL" : " = '{$_GET['from']}'";
	doQuery("UPDATE tblpet SET type = '{$_GET['to']}' WHERE type $fromtest", 1);
	$changed = leashtime_affected_rows();
	header("Location: ".globalURL("pet-counts.php?message=$changed+pets+of+type+{$_GET['from']}+changed+to+{$_GET['to']}."));
	exit;
	echo "<p>";print_r($_GET); echo "<hr>";
}
else if($_GET['message']) {
	$message = "<span class='fontSize1_4em'><hr>{$_GET['message']}<hr></span>";
}
?>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" />
<p class='tipLooks fontSize1_5em'>Click on a pet type label to change the type of those pets to something else.</p>
<?= $message ?>
<table class='fontSize1_3em'><tr><th>Pet Type</th><th>Count</td></th>
<?
foreach($petcounts as $k => $v) {
	echo "<tr><td><a class=\"fauxlink\" petlabel=\"$k\" pettype=\"$v\" onclick='petclick(this)'>$k</td><td>$v</td></tr>";
	$countpairs[] =  "\"$k\": $v";
}
$countpairs = $countpairs ? join(',', $countpairs) : '0';
?>
</table>
<script language='javascript'>
<?= 'var countpairs = {'.$countpairs.'};'."\n" ?>
function petclick(el) {
	var fromcount = el.getAttribute('pettype');
	var fromlabel = el.getAttribute('petlabel');
	var tolabel = prompt("Change "+fromcount+" pets of type "+fromlabel+" to...");
	if(tolabel == null || tolabel.trim() == '') {
		alert("Action canceled.");
		return;
	}
	tolabel = tolabel.trim();
	var already = countpairs[tolabel];
	if(typeof already != 'undefined')
		if(!confirm("There are already "+already+" pets of this type.  Proceed?"))
			return;
	document.location.href = 'pet-counts.php?from='+fromlabel+"&to="+tolabel;
}
</script>
