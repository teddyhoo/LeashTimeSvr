<? // maint-edit-prefs.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "preference-fns.php";


// Verify login information here
locked('z-');
extract(extractVars('id,property,value,action', $_REQUEST));
if($id) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$id'");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
}

if($action == 'save') {
	setPreference($property, $value);
	$message = "Property <b>$property</b> set to [<b>$value</b>]";
}
else if($action == 'fetch') {
	$value = fetchPreference($property);
	echo $value;
	exit;
}

//include 'frame-maintenance.php';
?>
<link rel="stylesheet" href="pet.css" type="text/css" /> 
<?
if($id) echo "<h2>Preferences for: {$biz['bizname']}</h2>";
else {
	echo  "<h2><font color=red>No business specified.</font></h2>";
	exit;
}


if($error) echo "<font color=red>$error.</font><p>";
if($message) echo "<font color=green>$message.</font><p>";
?>
<form name='prefform' method='POST'>
<?
labeledInput('Property: ', 'property', $property, $labelClass=null, $inputClass='VeryLongInput', $onBlur='updateValue()');
echo " ";
$options = explodePairsLine('--Select--|0||appointmentCalendarColumns|appointmentCalendarColumns||composerSignature|composerSignature||disableAllClientLogins|disableAllClientLogins');
labeledSelect($label, $name, $value=null, $options, $labelClass=null, $inputClass=null, $onChange='setProp(this)');
?>
<br>Value:<br>
<textarea rows=10 cols=80 id='value' name='value'><?= $value ?></textarea>
<?
hiddenElement('action', '');
echoButton('', 'Save', 'savePref()');
?>
</form>

<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>

<script language='javascript'>
function updateValue() {
	var prop = document.getElementById('property').value;
	if(!(jstrim(prop))) return;
	ajaxGetAndCallWith("maint-edit-prefs.php?id=<?= $id ?>&action=fetch&property="+prop, postUpdateValue, 0);
}

function setProp(el) {
	if(el.selectedIndex==0) return;
	document.getElementById('property').value = el.options[el.selectedIndex].value;
	el.selectedIndex = 0;
	updateValue();
}

function postUpdateValue(arg, text) {
	document.getElementById('value').value = text;
}

function savePref() {
	var prop = jstrim(document.getElementById('property').value);
	if(!prop) {alert('No property specified.'); return;}
	document.getElementById('property').value = prop;
	document.getElementById('action').value = 'save';
	document.prefform.submit();
}
</script>
