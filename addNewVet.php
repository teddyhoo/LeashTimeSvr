<?
/* addNewVet.php?
*
* Parameters: 
* sel - name of selectElement to be returned on completion of vet creation
* clinicId - id of clinic to pre-select
*/

/* Game Plan
1. Offer a window for specifying a new vet
2. validate information before saving
3. On save, if error, redisplay window.  Else invoke updateVetChoices(selectElementId) in parent window
*/

// Verify login information here
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "vet-fns.php";
require "zip-lookup.php";

extract($_REQUEST);

$clinicId = isset($clinicId) ? $clinicId : null;

if(!isset($sel)) $error = "SelectElementID not specified.";
else if($_POST) {
  // verify $_POST parameters
  $error = collectVetFormErrors();
  if(!$error) {
		$vetId = saveNewVet();
		
		$clinicId = $_POST['clinicptr'] ? $_POST['clinicptr'] : 0;
  	if($addAnother) 
  	  echo "<script language='javascript'>\n".
  	         "document.location.href='addNewVet.php?clinicId=$clinicId&sel=&allowAnother=1';\n</script>";
  	else {
			$vetName = fetchRow0Col0("SELECT CONCAT_WS(' ',fname, lname) as fullname FROM tblvet WHERE vetid = $vetId");
			if($clinicId) $clinicName = fetchRow0Col0("SELECT clinicname FROM tblclinic WHERE clinicid = $clinicId");
			$_SESSION['frame_message'] = "$vetName added".($clinicName ? " to $clinicName" : '');
			echo "<script language='javascript'>
  							if(window.opener && window.opener.updateVetChoices) 
  								window.opener.updateVetChoices('$sel', $vetId, $clinicId);
  							else if(window.opener && window.opener.updateClinicChoices) 
  								window.opener.updateClinicChoices('frame_message', null);
  							window.close();</script>";
		}
  	exit();
	}
}

$windowTitle = 'Add a New Vet';
$extraHeadContent = 
'<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';


require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
?>
<div style='padding: 10px;padding-top:0px;'>
<h2>Add a New Vet</h2>
<?
displayVetForm(null, null, $clinicId);
echoButton('', "Save New Vet", 'checkAndSubmit()');
if($allowAnother) {
  echo " ";
  echoButton('', "Save and Add Another", 'saveAndAdd()');
  echo " ";
  echoButton('', "Back", "document.location.href=\"viewClinic.php?id=$clinicId\";");
}
echo " ";
echoButton('', "Quit", 'confirmAndClose()');
$clinicOptionData = fetchSpecificClinicOptionsSelecting($source['clinicptr']); // $source is set by displayVetForm
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function clinicChanged(selectElement) {
	if(selectElement.options[selectElement.selectedIndex].value == -2) { //OPEN AN "ADD CLINIC" window
	  //openConsoleWindow('addvet', 'addNewClinic.php?sel='+selectElement.id,700,520);
	  $.fn.colorbox({href:'addNewClinic.php?sel='+selectElement.id, iframe:true, width:"540", height:"500", scrolling: true, opacity: "0.3"});
	  selectElement.selectedIndex = 0;
	}
}


<? dumpvetValidationJS(); ?>
<? if(function_exists('dumpZipLookupJS'))  dumpZipLookupJS(); ?>
<? include "select-builder.js"; ?>
function confirmAndClose() {
	if(confirm("Ok to close without saving a new veterinarian?")) 
		if(window.opener) window.close();
}

if(document.editvet.clinicptr.type == 'select-one')  
  rebuildSelectOptions('clinicptr', "<?= $clinicOptionData ?>");
  
function updateClinicChoices(selectElementId, newClinicId) {
	if(newClinicId == -1) newClinicId = 0;
	var xh = getxmlHttp();
	xh.open("GET","vet-list-ajax.php?options=allClinicChoices",true);
	xh.onreadystatechange=function() { if(xh.readyState==4) rebuildSelectOptions(selectElementId, xh.responseText, newClinicId); }
	xh.send(null);
}

  

$('#clinicptr').change(function(event){clinicChanged(event.target);});
</script>
</body>
</html>
