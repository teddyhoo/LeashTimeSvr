<? // client-own-edit-include.php
// included in client-own-edit.php and client-prov-edit.php
// NOT in client-edit.php
// $clientPetContext: null (all) or 'client' or 'pet_{petid}' or 'pet_new'
require_once "js-gui-fns.php";
require_once "preference-fns.php";
$_SESSION["preferences"] = fetchPreferences();


$client['vetname'] = $client['vetptr'] ? nameOf(getVet($client['vetptr'])) : '';
$client['clinicname'] = $client['clinicptr'] ? nameOf(getClinic($client['clinicptr'])) : '';

if($error) echo "<font color='red'>$error</font>";

$FLOATINGSUBMIT = dbTEST('dogslife,tonkatest') && $_SESSION["responsiveClient"];
//=====================================
?>
<style>
.sectionHead {font-size:1.1em;background:lightblue;border:solid black 1px;font-weight:bold;margin:15px;}
</style>

<form name='clienteditor' method='post' enctype='multipart/form-data'>
<?
if(!$FLOATINGSUBMIT) echoButton('','Submit Change Request', 'submitChanges()');
if($FLOATINGSUBMIT) {
	echo "<div class='floater'>";
	echo "<input type='button' onclick='submitChanges()' value='Submit Change Request' class='btn btn-success' title=''>";
	//echo "<br><img src='art/spacer.gif' height=300 width=2>";
	//echoButton('helpButton', 'Help', "showHelp(\"$action\")", "Button", "ButtonDown");
	echo "</div>";

}



if($clientPetContext) {
	echo "<img src='art/spacer.gif' width=20 height=1>";
	echoButton('','Cancel Request', 'cancelRequest()', 'HotButton', 'HotButtonDown');;
}
hiddenElement('MAX_FILE_SIZE', $maxBytes); // see pet-fns.php
hiddenElement('clientid', ($id ? $id : ''));
hiddenElement('continueEditing', '');
hiddenElement('checkboxes', ''); 
$version = 2;
hiddenElement('version', $version); 
$TESTBORDER1 = FALSE && mattOnlyTEST() ? 'border=1 bordercolor=red' : ''; //   '';//
echo "<table $TESTBORDER1><tr><th colspan=2 width=315>&nbsp;</th><th class='storedValue'>Information on Record</th></tr>";

if(!$clientPetContext || ($clientPetContext == 'client')) { // START CLIENT PROFILE EDIT PART 1
	// BASIC SECTION
	$raw = explode(',', "$rawBasicFields");
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	$noContactInfo = $_SESSION['preferences']['suppresscontactinfo'] && userRole() == 'p';
	if($noContactInfo) {
		foreach(explode(',', 'email,email2,homephone,cellphone,cellphone2,workphone,homephone,fax,pager') as $x)
			unset($fields[$x]);
	}
	
	
	foreach($fields as $key => $label) {
		$val = isset($client[$key]) ? $client[$key] : '';
		//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
		if(in_array($key, array('active', 'prospect'))) {
			checkboxRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
		}
		else if($key == 'email') deltaInputRow($label.':', $key, $val, $val, null, 'emailInput', $dbTable='tblclient');
		else if($key == 'email2') deltaInputRow($label.':', $key, $val, $val, null, 'emailInput', $dbTable='tblclient');
		else if(strpos($key, 'phone')) {
			if(!$firstPhoneDone) {
				$firstPhoneDone = true;
				$textDirections = "<br>(T) means a phone can accept Text messages.";
				deltaLabelRow('', '', "Mark circle for primary phone number$textDirections", $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
			}
			deltaPhoneRow($label.':', $key, $val, $val);
		}
		else if($key == 'notes') deltaTextRow($label.':', $key, $val, $val);
		else deltaInputRow($label.':', $key, $val, $val, $labelClass=null, $inputClass='standardInput', $dbTable='tblclient');
	}

	echo "<tr><td>&nbsp;</td><tr>";
	echo "<tr><td class='sectionHead' colspan=3>Addresses</td><tr>";

	deltaAddressTable('Home Address', '', $client, $client);
	echo "<tr><td>&nbsp;</td><tr>";
	deltaAddressTable('Mailing Address', 'mail', $client, $client);
} // // END CLIENT PROFILE EDIT PART 1
if(!$clientPetContext || strpos($clientPetContext, 'pet_') === 0) { // START PET PROFILE EDIT
	echo "<tr><td>&nbsp;</td><tr>";
	echo "<tr class='sectionHead'><td colspan=3>Pets</td><tr>";

	echo "<tr><td colspan=3>";
	petTable(getClientPets($id), $client, true, 'To add new pets, please give us a call.');
	originalPetsDiv(getClientPets($id), $client);
	echo "</td></tr>";
} // END PET PROFILE EDIT
if(!$clientPetContext || ($clientPetContext == 'client')) { // START CLIENT PROFILE EDIT PART 2
	echo "<tr><td>&nbsp;</td><tr>";
	echo "<tr><td class='sectionHead' colspan=3>Home Info</td><tr>";

	$raw = explode(',', $homeFields);
	$fields = array();
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	foreach($fields as $key => $label) {
		$val = isset($client[$key]) ? $client[$key] : '';
		//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
		if(in_array($key, array('active', 'prospect'))) {
			checkboxRow($label.':', $key, $val, $labelClass=null, $inputClass='standardInput');
		}
		else if(in_array($key, array('directions', 'alarminfo'))) deltaTextRow($label.':', $key, $val, $val);
		else deltaInputRow($label.':', $key, $val, $val, null, 'emailInput', $dbTable='tblclient');
	}


	$noEmergencyContactInfo = $_SESSION['preferences']['suppressEmergencyContactinfo'] && userRole() == 'p';
	if(!$noEmergencyContactInfo) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Emergency Contacts</td><tr>";
			$contacts = getKeyedClientContacts($id);
			if($noEmergencyContactInfo) $contacts = array();
			$contact = isset($contacts['emergency']) ? $contacts['emergency'] : array();	
			deltaContactRows($contact, 'emergency', $contact);
			$contact = isset($contacts['neighbor']) ? $contacts['neighbor'] : array();
			deltaContactRows($contact, 'neighbor', $contact);
	}

	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<tr><td>CUSTOM: ".print_r(getCustomFields('active', !'visitsheetonly', null, 'clientvisibleonly'),1); }
	$clientvisibleonly = userRole() == 'c';
	$visitsheetonly = userRole() == 'p';
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<tr><td>CUSTOM: visitsheetonly: [$visitsheetonly] clientvisibleonly: [$clientvisibleonly]"; }
	if($customFields = getCustomFields('active', $visitsheetonly, null, $clientvisibleonly)) {

		$customFields = displayOrderCustomFields($customFields, 'custom');
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Custom Fields</td><tr>";
		$clientCustomFields = getClientCustomFields($id);
		foreach($customFields as $key => $descr) {
			if($descr[2] == 'oneline')
				deltaInputRow($descr[0].':', $key, $clientCustomFields[$key], $clientCustomFields[$key], null, 'streetInput', $dbTable=null); // custom fields are all text, 64KB
			else if($descr[2] == 'text')
				deltaTextRow($descr[0].':', $key, $clientCustomFields[$key], $clientCustomFields[$key], 3, 40);
			else if($descr[2] == 'boolean')
				deltaCheckboxRow($descr[0].':', $key, $clientCustomFields[$key], $clientCustomFields[$key]);
			else if($descr[2] == 'file') {
				$value0 = clientDocumentFileLink($key, $clientCustomFields[$key], $id, $editable=false);
				deltaDocumentFileRow($descr[0].':', $key, $value=null, $value0, $labelClass=null, $inputClass=null, $dbTable=null, $rowId=null,  $rowStyle=null, $onBlur=null);
			}
				;//deltaCheckboxRow($descr[0].':', $key, $clientCustomFields[$key], $clientCustomFields[$key]);
		}
	}
}// END CLIENT PROFILE EDIT PART 2
echo "</table>";
echoButton('','Submit Change Request', 'submitChanges()');
if($clientPetContext) {
	echo "<img src='art/spacer.gif' width=20 height=1>";
	echoButton('','Cancel Request', 'cancelRequest()', 'HotButton', 'HotButtonDown');;
}
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>

<? if(mattOnlyTEST() && ($jqueryVersion = $_SESSION["responsiveClient"])) { ?>
<link rel="stylesheet" href="jquery-ui.css"></link>
<style>
.ui-datepicker { width: 14em; }
/*
.ui-datepicker table {font-size: 2.0em;}
.ui-datepicker .ui-datepicker-title select {font-size: 2.0em;}
.ui-datepicker .ui-datepicker-title {font-size: 2.0em;}
*/
.ui-widget {font-size: 1.0em;}


/* Icons
----------------------------------*/

/* states and images */
.ui-icon {
	width: 16px;
	height: 16px;
}
.ui-widget-header .ui-icon {
	background-image: url("jquery-ui-1.8.20/css/ui-lightness/images/ui-icons_222222_256x240.png"); //images/ui-icons_444444_256x240.png
}

.ui-state-hover .ui-icon,
.ui-state-focus .ui-icon,
.ui-button:hover .ui-icon,
.ui-button:focus .ui-icon {
	background-image: url("jquery-ui-1.8.20/css/ui-lightness/images/ui-icons_228ef1_256x240.png"); //images/ui-icons_555555_256x240.png
}

</style>

<script language='javascript'>
</script>
<?
} 
else {
?>
<script language='javascript' src='popcalendar.js'></script>
<?
} 
?>


<script language='javascript'>
setPrettynames('fname', 'First Name', 'lname', 'Last Name', 'email', 'Email', 'email2', 'Alt. Email');

function photoTooLargeMessage(n) {
	var attachment = document.getElementById('photo_'+n);
	if(attachment && attachment.value == "") return;
	if(window.FileReader) { // compatibility: see http://caniuse.com/filereader
		if(attachment.files && (attachment.files.length == 0 || attachment.files[0].size > <?= $maxBytes ?>)) {
			var forPet = document.getElementById('name_'+n).value;
			if(forPet) forPet = "for "+forPet;
			return "The photo file "+forPet+" ["+attachment.value+"] is too large (greater than <?= number_format($maxBytes) ?> bytes) to upload.";
		}
	}
}

function validateChanges() { // NEW TEST
<?
if(!$clientPetContext) {
		echo <<<VAL
	var badPetNames = null;
	for(var i = 1; document.getElementById('name_'+i); i++)
		if(!badPetNames)
			badPetNames = petNameProblem(document.getElementById('name_'+i).value);
	document.getElementById('fname').value = jstrim(document.getElementById('fname').value);
	document.getElementById('lname').value = jstrim(document.getElementById('lname').value);
	
	var args = 
		['fname', '', 'R',
		'lname', '', 'R',
		'email','','isEmail', 
		'email2','','isEmail', 
		badPetNames,'','MESSAGE'];
VAL;
}
else if(clientPetContext == 'client') {
		echo <<<VAL
	document.getElementById('fname').value = jstrim(document.getElementById('fname').value);
	document.getElementById('lname').value = jstrim(document.getElementById('lname').value);
	
	var args = 
		['fname', '', 'R',
		'lname', '', 'R',
		'email','','isEmail', 
		'email2','','isEmail'];
VAL;
}
else  { // pets only
		echo <<<VAL
	var badPetNames = null;
	for(var i = 1; document.getElementById('name_'+i); i++)
		if(!badPetNames)
			badPetNames = petNameProblem(document.getElementById('name_'+i).value);
	var args = 
		[badPetNames,'','MESSAGE'];
VAL;
}


?>
	for(var i = 1; document.getElementById('name_'+i); i++) {
		var bigPhoto = photoTooLargeMessage(i);
		if(bigPhoto) {
			args[args.length] = bigPhoto;
			args[args.length] = '';
			args[args.length] = 'MESSAGE';
		}
	}
		
	if(MM_validateFormArgs(args)) return true;
}

function cancelRequest() {
	if(!confirm('Are you sure you want to cancel this request?')) return;
	document.location.href='client-own-edit-segmented.php';
}

function submitChanges() {
	// return true if there are problems that prevent form submission
	/*var badPetNames = null;
	for(var i = 1; document.getElementById('name_'+i); i++)
		if(!badPetNames)
			badPetNames = petNameProblem(document.getElementById('name_'+i).value);
	document.getElementById('fname').value = jstrim(document.getElementById('fname').value);
	document.getElementById('lname').value = jstrim(document.getElementById('lname').value);
	
	if(!MM_validateForm(
		'fname', '', 'R',
		'lname', '', 'R',
		'email','','isEmail', 
		'email2','','isEmail', 
		badPetNames,'','MESSAGE')) return true;*/
	if(!validateChanges()) return true;
	
	// disable all unchanged fields
	var frmels = document.clienteditor.elements;
	var orig;
	for(var i=0;i<frmels.length;i++) {
		if(frmels[i].name) {
			if(orig = document.getElementById('orig_'+frmels[i].name)) {
				var cmpValue;
				if(frmels[i].type == 'checkbox') cmpValue = frmels[i].checked ? 'Yes' : 'No';
				else if(frmels[i].type == 'select-one') cmpValue = frmels[i].options[frmels[i].selectedIndex].value;
				else if(frmels[i].type.indexOf('text') != -1 || frmels[i].type == 'hidden') {
					cmpValue = frmels[i].value;
<? if($version != 2) { ?>
					if(frmels[i].name.indexOf('phone') != -1) {
						if(frmels[i-1].checked)	{
							cmpValue = '*'+cmpValue;
							frmels[i].value = cmpValue;
						}
					}
<? } ?>					
				}
				
<? if($version == 2) { ?>
				if(isAClientPhoneField(frmels[i].id)) {
					var newNumber = saveablePhoneNumber(frmels[i].name);
					var origNumber = originalPhoneNumber(frmels[i].name);
					if(newNumber == origNumber)
						frmels[i].disabled = true;
					else {
						//alert(newNumber+' = '+origNumber);
						frmels[i].value = newNumber;
					}
				}
				else 
<? } ?>
				if(frmels[i].type == 'hidden' && frmels[i].id.indexOf('sms_') == 0) {
					if(orig.value == frmels[i].value)
						frmels[i].disabled = true;
					orig.disabled = true;
				}
				else if(frmels[i].type.indexOf('radio') != -1) {
					if(frmels[i].checked && (frmels[i].value == orig.innerHTML)) {
						frmels[i].disabled = true;
				  }
				}
				else if(frmels[i].type == 'checkbox') {
					if(cmpValue != orig.innerHTML) {
						//alert(frmels[i].name+': '+cmpValue+' '+orig.id+': ['+orig.innerHTML+']');
						var cblist = document.getElementById('checkboxes');
						if(cblist.value) cblist.value = cblist.value+'||';
						cblist.value = cblist.value+frmels[i].name+'|'+(frmels[i].checked ? 1 : 0);
					}
					frmels[i].disabled = true;
				}
				else if(eqivalentString(cmpValue, orig.innerHTML)) {
					frmels[i].disabled = true;
				}
<? if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { ?>
//else document.write( frmels[i].type+'<p>\n ['+comparisonString(cmpValue)+']<br>\n['+comparisonString(orig.innerHTML)+']<br>');
<? } ?>
				
			}
		}
	}
	//return;
	
	// submit remaining changes
	document.clienteditor.submit();
}

function isAClientPhoneField(fieldname) {
	var el = document.getElementById(fieldname);
	var section = el.getAttribute('section');
	return document.getElementById(section+'_'+fieldname);
}

function saveablePhoneNumber(fieldname) {
	var el = document.getElementById(fieldname);
	var section = el.getAttribute('section');
	var primary = document.getElementById(section+'_'+fieldname).checked ? '*' : '' ;
	var textable = document.getElementById('sms_'+section+'_'+fieldname).value == '1' ? 'T' : '' ;
	var strippednumber = document.getElementById(fieldname).value;
	return primary+textable+strippednumber;
}

function originalPhoneNumber(fieldname) {
	var rawfield = document.getElementById('orig_'+fieldname);
	var primary = rawfield.className.indexOf('boldfont') != -1 ? '*' : '' ;
	var textFlagged = rawfield.innerHTML.indexOf('SMS-');
	var textable = rawfield.innerHTML.indexOf('SMS-yes') != -1 ? 'T' : '' ;
	if(textFlagged) {
		var contentStart = rawfield.innerHTML.indexOf('>')+1;
		var contentEnd = rawfield.innerHTML.indexOf('</B>') == -1 ? rawfield.innerHTML.length : rawfield.innerHTML.indexOf('</B>');
	}
	var strippednumber = !textFlagged ? rawfield.innerHTML : rawfield.innerHTML.substring(contentStart, contentEnd);
	return primary+textable+strippednumber;
}


function eqivalentString(a, b) {
<?  ?>
	return comparisonString(a) == comparisonString(b);
}

function comparisonString(a) {
	return (''+a).replace('&amp;', '&');
}

function photoAction(el) {
	var section = el.id.split('_')[1];
	if(el.type=='checkbox' && el.checked) { 
		var uploader = document.getElementById('photo_'+section);
		if(navigator.appName == 'Microsoft Internet Explorer') {
			var clone = uploader.cloneNode(false);
			clone.onchange = uploader.onchange;
			uploader.parentNode.replaceChild(clone,uploader);
		}
		else uploader.value = '';
	}
	else if(el.type=='file' && el.value) {document.getElementById('dropphoto_'+section).checked=false;}
}

var mediaWidthQuery = window.matchMedia("(max-width: 60000px)");
// Attach listener function on state changes
mediaWidthQuery.addListener(scoochFloater);

function scoochFloater(query) {
	var narrow = query.matches; 
	$('.floater').css('right', (narrow ? '3px' : '80px'));
}

scoochFloater(mediaWidthQuery);


<? 
dumpPhoneRowJS();
dumpPetJS();
if($_SESSION["responsiveClient"]) dumpJQueryDatePickerJS();
else dumpPopCalendarJS();
if(function_exists('dumpCustomFieldJavascript')) dumpCustomFieldJavascript($id);

?>

function thingsToDoWhenLoaded() {
<?
if($_SESSION['preferences']['client-ui-hide-directions-field']) {
	//echo "\nalert(document.getElementById('directions').parentNode());";
	echo "$('#directions').parent().parent().css('display','none');";
}
if(mattOnlyTEST() && $_SESSION["responsiveClient"]) {
	echo "initializeCalendarImageWidgets();\n";
	//echo "alert($('.calendarwidget').toArray().length);";
}
?>
}
$(document).ready(function(){thingsToDoWhenLoaded();});

</script>