<?
/* viewClinic.php
*
* Parameters: 
* id - id of clinic to be viewed
*/

/* Game Plan
1. Offer a window for specifying a new clinic
2. validate information before saving
3. On save, if error, redisplay window.  Else invoke updateClinicChoices(selectElementId) in parent window
*/

// Verify login information here
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "vet-fns.php";
if(userRole() == 'o') locked('o-');
else if(userRole() == 'd') locked('d-');
else locked('p-');
extract($_REQUEST);

if(!isset($id)) $error = "ClinicID not specified.";

$windowTitle = "View Clinic";
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
?>
<div style='padding: 10px;padding-top:0px;'>
<h2>View Veterinary Clinic</h2>
<?
displayClinicSummary($id);
echo "<p>";
echoButton('', "Edit Clinic", "document.location.href=\"editClinic.php?id=$id\"");
echo " ";
echoButton('', "Quit", 'confirmAndClose()');
echo " ";
echoButton('', "Add Veterinarians", "document.location.href=\"addNewVet.php?clinicId=$id&sel=&allowAnother=1\"");
echo " ";
echoButton('', "Delete Clinic", 'deleteClinic()', 'HotButton', 'HotButtonDown');
?>
</div>
<?
if(TRUE || mattOnlyTEST()) {
	$clients = fetchAssociations("SELECT * FROM tblclient WHERE clinicptr=$id order by active desc, lname, fname", 1);
	$count = count($clients) ? count($clients) : 'none';
	foreach($clients as $client)
		$inactiveFound = !$client['active'] || $inactiveFound;
	$legend = $inactiveFound ? ".  &dagger; = Inactive (former) client." : "";
	echo "Associated clients: $count$legend<p>";
	$wasActive = true;
	foreach($clients as $i => $client) {
		$inactive = !$client['active'] ? "&dagger; " : "";
		if($i == 0 && !$inactive) echo "<u>Active Clients</u><br>";
		if($inactive && $wasActive) {
			$wasActive = false;
			echo "<p><u>Inactive clients</u><br>";
		}
		echo "$inactive{$client['fname']} {$client['lname']} (@{$client['clientid']})<br>";
	}
}
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function confirmAndClose() {
	if(true || confirm("Ok to close without saving this veterinarian?")) window.close();
}

function deleteClinic() {
	document.location.href='deleteClinic.php?id=<?= $id ?>';
}


</script>
</body>
</html>
