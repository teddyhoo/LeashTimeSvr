<?
/* deleteClinic.php
*
* Parameters: 
* id - id of clinic to be deleted
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

extract($_REQUEST);

if(!isset($id)) $error = "ClinicID not specified.";
else if(isset($confirm)) {
  // verify $_POST parameters
  $error = false;
  if(!$error) {
		$clinicName = fetchRow0Col0("SELECT clinicname FROM tblclinic WHERE clinicid = $id LIMIT 1");
	  $vetsDeleted = deleteClinicAndVets($id);
  	echo "<script language='javascript'>window.opener.updateAfterDeletion(escape('$clinicName'), $vetsDeleted);window.close();</script>";
  	exit();
	}
}

$windowTitle = 'Delete Clinic';
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
?>
<div style='padding: 10px;padding-top:0px;'>
<h2 class='warning'>Delete Clinic and Veterinarians?</h2>
<?
displayClinicSummary($id);
//displayClinicClientInfo($id);
echoButton('', "Delete Clinic", 'deleteClinic()', 'HotButton', 'HotButtonDown');
echo " ";
echoButton('', "Back", 'cancel()');
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function cancel() {
	window.location.href='viewClinic.php?id=<?= $id ?>';
}

function confirmAndClose() {
	if(true || confirm("Ok to close without saving this veterinarian?")) window.close();
}

function deleteClinic() {
	document.location.href='deleteClinic.php?id=<?= $id ?>&confirm=true';
}


</script>
</body>
</html>
