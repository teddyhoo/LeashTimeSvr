<?
/* viewVet.php
*
* Parameters: 
* id - id of clinic to be edited
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

if(!isset($id)) $error = "VetID not specified.";

$windowTitle = "View Vet";
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
$vet = fetchFirstAssoc("SELECT CONCAT_WS(' ', fname, lname) as name, clinicptr FROM tblvet WHERE vetid = $id");
?>
<div style='padding: 10px;padding-top:0px;'>
<h2>View Veterinarian: <?= $vet['name'] ?></h2>
<?
displayVetSummary($id, 'linkToClinic');
echo "<p>";
echoButton('', "Edit Veterinarian", "document.location.href=\"editVet.php?id=$id\"");
echo " ";
echoButton('', "Quit", 'window.confirmAndClose()');
echo " ";
echoButton('', "Delete Veterinarian", 'deleteVet()', 'HotButton', 'HotButtonDown');

if($vet['clinicptr']) {
	echo "<p>";
	displayClinicSummary($vet['clinicptr'], $vetsToo=false);
}
?>
</div>

<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
<? dumpClinicValidationJS(); ?>
function confirmAndClose() {
	if(true || confirm("Ok to close without saving this veterinarian?")) window.close();
}

function deleteVet() {
	document.location.href='deleteVet.php?id=<?= $id ?>';
}


</script>
</body>
</html>
