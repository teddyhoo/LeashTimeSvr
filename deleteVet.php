<?
/* deleteVet.php
*
* Parameters: 
* id - id of vet to be deleted
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

if(!isset($id)) $error = "VetID not specified.";
else if(isset($confirm)) {
  // verify $_POST parameters
  $error = false;
  if(!$error) {
	  $vetName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) as name FROM tblvet WHERE vetid = $id LIMIT 1");
	  deleteVet($id);
	  if(mysqli_error()) {echo mysqli_error(); exit;};
  	echo "<script language='javascript'>window.opener.updateAfterDeletion(escape('$vetName'), -1);window.close();</script>";
  	exit();
	}
}

$windowTitle = 'Delete Vet';
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
?>
<div style='padding: 10px;padding-top:0px;'>
<h2 class='warning'>Delete Veterinarian?</h2>
<?
displayVetSummary($id);
//displayClinicClientInfo($id);
echoButton('', "Delete Vet", 'deleteVet()', 'HotButton', 'HotButtonDown');
echo " ";
echoButton('', "Back", 'cancel()');
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function cancel() {
	window.location.href='viewVet.php?id=<?= $id ?>';
}
function deleteVet() {
	document.location.href='deleteVet.php?id=<?= $id ?>&confirm=true';
}


</script>
</body>
</html>
