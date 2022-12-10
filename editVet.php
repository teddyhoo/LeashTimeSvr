<?
/* editVet.php?
*
* Parameters: 
* id - id of vet to be edited
*/

/* Game Plan
1. Offer a window for specifying a new vet
2. validate information before saving
3. On save, if error, redisplay window.  Else invoke updateClinicChoices(selectElementId) in parent window
*/

// Verify login information here
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "vet-fns.php";
require "zip-lookup.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

locked('o-');
extract($_REQUEST);

if(!isset($id)) $error = "VetID not specified.";
else if($_POST) {
  // verify $_POST parameters
  $error = collectVetFormErrors();
  if(!$error) {
		$clinicId = saveVet();
  	echo "<script language='javascript'>if(window.opener.refresh)window.opener.refresh();window.close();</script>";
  	exit();
	}
}

$windowTitle = 'Edit Vet';
require "frame-bannerless.php";


if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
?>
<div style='padding: 10px;padding-top:0px;'>
<h2>Edit Veterinarian</h2>
<?
displayVetForm($id);
echoButton('', "Save Veterinarian", 'checkAndSubmit()');
echo " ";
echoButton('', "Quit", 'confirmAndClose()');
echo " ";
echoButton('', "Delete Veterinarian", 'deleteVet()', 'HotButton', 'HotButtonDown');
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
<? dumpVetValidationJS(); ?>
<? if(function_exists('dumpZipLookupJS'))  dumpZipLookupJS(); ?>
function confirmAndClose() {
	if(true || confirm("Ok to close without saving this veterinarian?")) window.close();
}

function deleteVet() {
	document.location.href='deleteVet.php?id=<?= $id ?>';
}


</script>
</body>
</html>
