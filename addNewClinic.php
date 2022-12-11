<?
/* addNewClinic.php?
*
* Parameters: 
* sel - name of selectElement to be returned on completion of clinic creation
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
require "zip-lookup.php";
extract($_REQUEST);

if(!isset($sel)) $error = "SelectElementID not specified.";
else if($_POST) {
  // verify $_POST parameters
  $error = collectClinicFormErrors();
  if(!$error) {
		$clinicId = saveNewClinic();
  	echo "<script language='javascript'>
  	if(window.opener) {
			window.opener.updateClinicChoices('$sel', $clinicId);
			window.close();
		}
  	else if(window.parent) {
			window.parent.updateClinicChoices('$sel', $clinicId);
			window.parent.$.fn.colorbox.close();
		}
  	
  	</script>";
  	exit();
	}
}

$windowTitle = 'Add a New Clinic';
$extraHeadContent = <<<JQUERY
  <link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
	<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
JQUERY;
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
?>
<div style='padding: 10px;padding-top:0px;'>
<h2>Add a New Clinic</h2>
<?
if(getI18Property('country') == 'US')
	fauxLink('Find a U.S. Veterinary Clinic',
						'$.fn.colorbox({href:"vets-us-find.php", iframe:true,  width:"690", height:"470", scrolling: true, opacity: "0.3"})');
displayClinicForm();
echoButton('', "Save New Clinic", 'checkAndSubmit()');
echo " ";
echoButton('', "Quit", 'confirmAndClose()');
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
<? dumpClinicValidationJS(); ?>
<? if(function_exists('dumpZipLookupJS'))  dumpZipLookupJS(); ?>
function confirmAndClose() {
	if(confirm("Ok to close without saving a new veterinary clinic?")) {
		if(window.opener) window.close();
		else if(window.parent) window.parent.$.fn.colorbox.close();
	}
}

function update(aspect, id) {
	if(aspect != 'clinic') return;
	ajaxGetAndCallWith('vets-us-find.php?xmlfor='+id, populate, 1);
}

function populate(arg, resultxml) {
	
	var root = getDocumentFromXML(resultxml).documentElement;
	if(root.tagName == 'ERROR') {
		alert(root.nodeValue);
		return;
	}
	var direct = 'clinicname,street1,street2,city,state,zip,officephone'.split(',');
	var notes = '';
	var kids = root.childNodes;
	
	for(var i=0; i < kids.length; i++) {
		var tag = kids[i].tagName;
		var val = kids[i].firstChild.nodeValue;
		var next = false;
		for(var j=0;j<direct.length;j++) {
			if(tag == direct[j]) {
				document.getElementById(tag).value = val;
				next = true;
			}
		}
		if(next) continue;
		var indirect = 'url,category,subcategory,webmetatitle,webmetadescription,webmetakeys'.split(',');
		var labels = {url:'URL', category:'Service', subcategory:'Specialty',
									webmetatitle:'Web Title',webmetadescription:'Web Description',webmetakeys:'Web Keys'};
		for(var j=0;j<indirect.length;j++) {
			if(jstrim(val) && tag == indirect[j]) {
				if(notes != '') notes += '\n';
				notes += labels[tag]+': '+val;
//if(!confirm(tag+'['+labels[tag]+']'+': '+val)) return;		
//if(!confirm(notes)) return;		
			}
		}
	}
	document.getElementById('notes').value = notes;
	alert('Remember to click "Save New Clinic"');
}

</script>
</body>
</html>
