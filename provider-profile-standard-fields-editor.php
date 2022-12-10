<? // provider-profile-standard-fields-editor.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "provider-profile-fns.php";
require "preference-fns.php";

// Determine access privs
$locked = locked('o-');

if($_POST) {
	setStandardProviderProfileFieldNames($_POST);
	$_SESSION['frame_message'] = 'Changes Saved.';
	globalRedirect('provider-profile-standard-fields-editor.php');
}

$pageTitle = "Standard Sitter Profile Fields";
//$breadcrumbs = '';;

	$_SESSION['preferences'] = fetchPreferences(); // don't know why bogus empty fields keep getting added, but...
include "frame.html";
// ***************************************************************************
$standardFields = getStandardProviderProfileFieldNames();
echo "<form name='standardfieldsform' method='POST'><table>";
echoButton('', "Save Fields", 'checkAndSubmit()');

foreach($standardFields as $i => $nm) {
	$n = $i+1;
	inputRow("Field #$n: ", 'provprofilefield'.sprintf('%03d', $n), $nm, $labelClass=null, $inputClass='VeryLongInput');
}
for($i = 1; $i < 5; $i++)
	inputRow('Field #'.($n+$i).': ', 'provprofilefield'.sprintf('%03d', $n+$i), '', 
									$labelClass=null, $inputClass='VeryLongInput');
?>
</table>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function checkAndSubmit() {
	//var maxCustomFields = <?= $maxCustomFields ?>;
	var els = document.getElementsByTagName('input');
	var found = new Array();
	var dups = false;
	for(var i=1; i < els.length; i++) {
		if(els[i].type != 'text') continue;
		var val = els[i].value;
		//alert(els[i].type+' '+els[i].name);
	  if(val != null && els[i].value != '') {
			els[i].value = (val = val.trim());
			for(var j=0; j < found.length; j++)
				if(found[j] == val) dups = true;
		}
		if(dups) break;
		else found[found.length] = val;
	}
	var msgargs = [];
	if(dups) {
		msgargs[msgargs.length] = 'Field labels must be unique.';
		msgargs[msgargs.length] = '';
		msgargs[msgargs.length] = 'MESSAGE';
	}
  if(msgargs.length > 0 && !MM_validateFormArgs(msgargs)) 
		  return false;
	document.standardfieldsform.submit();
}
</script>

<?
// ***************************************************************************
include "frame-end.html";


