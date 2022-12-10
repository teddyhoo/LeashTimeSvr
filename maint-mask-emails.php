<? // maint-mask-emails.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";
require_once "email-address-masking-fns.php";

$locked = locked('z-');
extract(extractVars('action,newbizdb,bizdb,prop,val,new,target', $_REQUEST));

if($bizdb && $action == 'mask') {
	if($bizdb != $newbizdb) $error = 'Database mismatch.';
	else {
		$affected = maskAllEmails($bizdb, $target);
		$msg = "Addresses masked: $affected.";
	}
}

else if($bizdb && $action == 'unmask') {
	if($bizdb != $newbizdb) $error = 'Database mismatch.';
	else {
		$affected = unmaskAllEmails($bizdb, $target);
		$msg = "Addresses unmasked: $affected.";
	}
}

$bizdb = $newbizdb;

$dbs = fetchKeyValuePairs("SELECT db, db FROM tblpetbiz ORDER BY db");
$dbs = array_merge(array('Pick a biz'=>''), $dbs);
$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
if($bizdb) {
	$stats = getEmailMaskingStats($bizdb);
}

include 'frame-maintenance.php';
if($bizdb) echo "<h2>$bizdb Email Addresses</h2>";
?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
selectElement('Business:', 'newbizdb', $bizdb, $dbs, "document.location.href=\"maint-mask-emails.php?newbizdb=\"+this.options[this.selectedIndex].value+\"&bizdb=\"+document.getElementById(\"bizdb\").value");
hiddenElement('bizdb', $bizdb);

if($msg) echo "<p style='color:darkgreen'>$msg</p>";
if($error) echo "<p style='color:red'>$error</p>";
if($stats) {
	echoButton('', 'Mask Emails', "var newbiz = document.getElementById(\"newbizdb\");document.location.href=\"maint-mask-emails.php?action=mask&newbizdb=\"+newbiz.options[newbiz.selectedIndex].value+\"&bizdb=\"+document.getElementById(\"bizdb\").value");
	echo ' ';
	echoButton('', 'Unmask Emails', "var newbiz = document.getElementById(\"newbizdb\");document.location.href=\"maint-mask-emails.php?action=unmask&newbizdb=\"+newbiz.options[newbiz.selectedIndex].value+\"&bizdb=\"+document.getElementById(\"bizdb\").value");
?>
<p><table width=500 border=1 bordercolor=black>
<tr><th>&nbsp;<th>Total<th>Masked<th>Unmasked
<tr><td>Client Email 1<td><?= $stats['clientEmails'] ?><td><?= $stats['clientEmailsMasked'] ?><td><?= $stats['clientEmailsUnmasked'] ?>
     <td><?
     	echoButton('', 'Mask Emails', "var newbiz = document.getElementById(\"newbizdb\");document.location.href=\"maint-mask-emails.php?action=mask&target=client&newbizdb=\"+newbiz.options[newbiz.selectedIndex].value+\"&bizdb=\"+document.getElementById(\"bizdb\").value");
		 	echo ' ';
		 	echoButton('', 'Unmask Emails', "var newbiz = document.getElementById(\"newbizdb\");document.location.href=\"maint-mask-emails.php?action=unmask&target=client&newbizdb=\"+newbiz.options[newbiz.selectedIndex].value+\"&bizdb=\"+document.getElementById(\"bizdb\").value");
     ?>
<tr><td>Client Email 2<td><?= $stats['clientEmail2s'] ?><td><?= $stats['clientEmail2sMasked'] ?><td><?= $stats['clientEmail2sUnmasked'] ?>
<tr><td>Provider Email<td><?= $stats['providerEmails'] ?><td><?= $stats['providerEmailsMasked'] ?><td><?= $stats['providerEmailsUnmasked'] ?>	
     <td><?
     	echoButton('', 'Mask Emails', "var newbiz = document.getElementById(\"newbizdb\");document.location.href=\"maint-mask-emails.php?action=mask&target=provider&newbizdb=\"+newbiz.options[newbiz.selectedIndex].value+\"&bizdb=\"+document.getElementById(\"bizdb\").value");
		 	echo ' ';
		 	echoButton('', 'Unmask Emails', "var newbiz = document.getElementById(\"newbizdb\");document.location.href=\"maint-mask-emails.php?action=unmask&target=provider&newbizdb=\"+newbiz.options[newbiz.selectedIndex].value+\"&bizdb=\"+document.getElementById(\"bizdb\").value");
     ?>
</table>
<?
}
