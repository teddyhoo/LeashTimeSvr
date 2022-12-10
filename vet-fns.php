<?
// vet-fns.php
/*  Supports addNewVet.php, editVet.php, and other vet-enabled windows.
*
Vet rules:
A client can have 0 or 1 vets.
A client can have 0 or 1 vet clinics.
When a client has both, the vet must belong to the clinic.
When a vet is added to a clinic, he stays there until deleted.  Vet's clinic cannot change.
Vets cannot be added to sole practitioner clinics.
*/
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$EOLN = '##';
function collectVetFormErrors() {  // // server-side validation of $_POST. return null if ok, a string otherwise
}

function collectClinicFormErrors() {  // // server-side validation of $_POST. return null if ok, a string otherwise
}

function fetchAllClinicOptionsSelecting($selectedClinic=null) {
	global $EOLN;
	return fetchClinicOptionsSelectingWithTopOptions($selectedClinic, "||--Select a Clinic--$EOLN-1||--All Clinics--$EOLN-2||--Add New Clinic--");
}

function fetchSpecificClinicOptionsSelecting($selectedClinic=null) {
	global $EOLN;
	return fetchClinicOptionsSelectingWithTopOptions($selectedClinic, "||--Select a Clinic--$EOLN-2||--Add New Clinic--");
}

function fetchClinicOptionsSelectingWithTopOptions($selectedClinic=null, $topOptions='') {
	global $EOLN;
	$sql = "SELECT clinicid, clinicname, solepractitioner, CONCAT_WS(' ',fname,lname) AS practitioner FROM tblclinic ORDER BY clinicname, lname, fname  ASC";
	$clinics = fetchAssociations($sql);
	$clinicOptions = $topOptions;
	foreach($clinics as $clinic) {
		$label = $clinic['clinicname'];
		if($clinic['solepractitioner']) $label = "$label ({$clinic['practitioner']})";
		$label = addslashes($label);
		$clinicOptions .= "$EOLN{$clinic['clinicid']}|".($clinic['clinicid'] == $selectedClinic ? '1' : '')."|$label";
	}
	return $clinicOptions;
}

function fetchAllVetOptionsSelecting($selectedVet, $clinic=-1) { // clinic == -1 means any clinic, clinic == 0 means no clinic)
	global $EOLN;
	$extraCols = '';
	$extraJoins = '';
	if(!$clinic) {
		$clinic = 0;
		$orNull = "OR clinicPtr IS NULL";
	}
	else if($clinic == -1) {
		$extraCols = ", clinicname";
		$extraJoins = "LEFT JOIN tblclinic ON clinicid = clinicptr";
	}
	$sql = "SELECT vetid, clinicptr, CONCAT_WS(' ',tblvet.fname,tblvet.lname) AS label $extraCols FROM tblvet".($clinic == -1 ? " $extraJoins" : " WHERE clinicPtr = $clinic $orNull")." ORDER BY tblvet.lname, tblvet.fname ASC";
	$vets = fetchAssociations($sql);
	//echo "$sql<p>".print_r($vets,1);
	$vetOptions = "||--Select a Veterinarian--$EOLN-2||--Add New Vet--";
	foreach($vets as $vet) {
		if($clinic == -1) $label = $vet['label'].' ('.($vet['clinicname'] ? $vet['clinicname'] : 'No Clinic').')';
		else $label = $vet['label'];
		$label = addslashes($label);
		$vetOptions .= "$EOLN{$vet['vetid']}|".($vet['vetid'] == $selectedVet ? '1' : '')."|$label|{$vet['clinicptr']}";
	}
	return $vetOptions;
}


function saveNewClinic($outData = null) { // use $_POST
  $outData = $outData ? $outData : array_merge($_POST);
  unset($outData['clinicid']);
  unset($outData['saveClinic']);
  return insertTable('tblclinic', $outData, 1);
}

function saveClinic() { // use $_POST
  $outData = array_merge($_POST);
  $clinicId = $outData['clinicid'];
  unset($outData['clinicid']);
  unset($outData['saveClinic']);
  return updateTable('tblclinic', $outData, "clinicid=$clinicId", 1);

}

function getClinic($clinicId) {
	return fetchFirstAssoc("SELECT * FROM tblclinic WHERE clinicid = $clinicId LIMIT 1");
}

function deleteClinicAndVets($clinicId) {
	doQuery("DELETE FROM tblclinic WHERE clinicid = $clinicId");
	doQuery("DELETE FROM tblvet WHERE clinicptr = $clinicId");
	return mysql_affected_rows();
}

function deleteVet($vetId) {
	doQuery("DELETE FROM tblvet WHERE vetid = $vetId");
	return mysql_affected_rows();
}

function saveNewVet() { // use $_POST
  $outData = array_merge($_POST);
  unset($outData['vetid']);
  unset($outData['saveVet']);
  unset($outData['addAnother']);
  return insertTable('tblvet', $outData, $showErrors=1);
}

function saveVet() { // use $_POST
  $outData = array_merge($_POST);
  $vetId = $outData['vetid'];
  unset($outData['vetid']);
  unset($outData['saveVet']);
  unset($outData['addAnother']);
  return updateTable('tblvet', $outData, "vetid=$vetId", 1);

}

function getVet($vetId) {
	return fetchFirstAssoc("SELECT * FROM tblvet WHERE vetid = $vetId LIMIT 1");
}

$props =
"fname|First Name
clinicname|Clinic
lname|Last Name
fullname|Name
veterinarian|Veterinarian
clinicptr|Veterinary Clinic
street1|Address
street2|Address 2
city|City
state|State
zip|ZIP
email|Email
officephone|Office Phone
cellphone|Cell Phone
homephone|Home Phone
fax|Fax
pager|Pager
notes|Notes
afterhours|After Hours";

$vetFieldLabels = array();
foreach(explode("\n",$props)  as $line) {
	$pair = explode("|",trim($line));
	$vetFieldLabels[$pair[0]] = $pair[1];
}

$props =
"clinicname|Clinic Name
solepractitioner|Sole Practitioner
fname|First Name
lname|Last Name
street1|Address
street2|Address 2
city|City
state|State
zip|ZIP
email|Email
officephone|Office Phone
cellphone|Cell Phone
homephone|Home Phone
fax|Fax
pager|Pager
notes|Notes
afterhours|After Hours
directions|Directions";

$clinicFieldLabels = array();
foreach(explode("\n",$props)  as $line) {
	$pair = explode("|",trim($line));
	$clinicFieldLabels[$pair[0]] = $pair[1];
}

function myVetInputRow($field, $rowId=null) {
  global $source, $vetFieldLabels;
	inputRow("{$vetFieldLabels[$field]}:", $field, $source[$field], '', '', $rowId);
}

function myClinicInputRow($field, $rowId=null) {
  global $source, $clinicFieldLabels;
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null) {
	$rowStyle = $rowId ? 'font-family:inherit;' : '';
	inputRow("{$clinicFieldLabels[$field]}:", $field, $source[$field], '', '', $rowId, $rowStyle);
}

function myClinicLabelRow($field, $rowId=null) {
  global $source, $clinicFieldLabels;
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null) {
	$rowStyle = $rowId ? 'font-family:inherit;' : '';
	if($source[$field])	labelRow("{$clinicFieldLabels[$field]}:", $field, $source[$field], '', '', $rowId, $rowStyle);
}

function myVetLabelRow($field, $rowId=null) {
  global $source, $vetFieldLabels;
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null) {
	$rowStyle = $rowId ? 'font-family:inherit;' : '';
	if($source[$field])	labelRow("{$vetFieldLabels[$field]}:", $field, $source[$field], '', '', $rowId, $rowStyle);
}

function displayVetSummary($vetId, $linkToClinic=false) {
	global $source, $vetFieldLabels;
	$source = getVet($vetId);
	echo "\n<table width=100%>\n";
	echo "<tr><td valign=top>\n<table>\n"; // COL 1
	$fullname = "{$source['fname']} {$source['lname']}";
 	labelRow($vetFieldLabels['fullname'].':', '', $fullname);
 	$addr = array();
	foreach(array('street1','street2', 'city', 'state', 'zip') as $k) $addr[] = $source[$k];
	$oneLineAddr = oneLineAddress($addr);
	$addr = htmlFormattedAddress($addr);
	if($addr) $addr = 
		fauxLink('(Map)', "openConsoleWindow(\"vetmap\", \"http://maps.google.com/maps?hl=en&q=$oneLineAddr\", 700, 700)", 
							1, 'Map this address').' '.$addr;
	if($addr)	labelRow('Address:', '', $addr, null, null, null, null, 'raw');
	if($source['clinicptr']) {
		$name = fetchRow0Col0("SELECT clinicname FROM tblclinic WHERE clinicid = {$source['clinicptr']} LIMIT 1");
		if($linkToClinic) $name = "<a href='viewClinic.php?id={$source['clinicptr']}'>$name</a>";
	  labelRow($vetFieldLabels['clinicname'].':', '', $name, null, null, null, null, true);
	}
	echo "</td></tr></table><td valign=top style='padding-left: 5px'><table>"; // COL 2

	myVetLabelRow('email');
	myVetLabelRow('officephone');
	myVetLabelRow('cellphone');
	myVetLabelRow('homephone');
	myVetLabelRow('fax');
	myVetLabelRow('pager');
	echo "</td></tr></table></td></tr>"; // END COL 2


	echo "<tr><td valign=top colspan=2><table>"; // NOTES
	$rows = 3;
	$cols = 50;
//textDisplayRow($label, $name, $value=null, $emptyTextDisplay=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null) {

	textDisplayRow("Notes:", 'notes', $source['notes']);
	textDisplayRow("After Hours:", 'afterhours', $source['afterhours']);
	textDisplayRow("Directions:", 'directions', $source['directions']);
	echo "</table></td></tr>"; // COL 2
	echo "</table>";

}

function displayVetForm($vetId=null, $action=null, $vetClinic=null) {
  // null $vetId means new vet
  // if isset($_POST['saveVet']) use fields from $_POST
  // else if($vetId) use saved vet data
  global $source, $vetFieldLabels;
  $action = $action ? "action = '$action" : '';
  if(isset($_POST['saveVet'])) $source = $_POST;
  else if($vetId) $source = getVet($vetId);
  else {
		$source = array();
		if($vetClinic) $source['clinicptr'] = $vetClinic;
	}
  echo "<form name='editvet' method='POST' $action>\n";
  hiddenElement('saveVet');
  hiddenElement('addAnother');
  hiddenElement('vetid', $vetId);
  echo "<table>";
  echo "<tr><td valign=top><table>"; // COL 1
  myVetInputRow('fname');
  myVetInputRow('lname');
  myVetInputRow('street1');
  myVetInputRow('street2');
  myVetInputRow('city');
  myVetInputRow('state');
  if(function_exists('dumpZipLookupJS')) 
    inputRow($vetFieldLabels['zip'], 'zip', $source['zip'], null, null, null,  null, $onBlur='lookUpZip(this.value, "unused")');
  else myVetInputRow('zip');
  echo "</td></tr></table><td valign=top style='padding-left: 5px'><table>\n"; // COL 2
  if(!$vetId) selectRow($vetFieldLabels['clinicptr'].':', 'clinicptr', $source['clinicptr']);
  else {
		hiddenElement('clinicptr', $source['clinicptr']);
		$clinicLabel = !$source['clinicptr'] ? 'No Clinic' : fetchRow0Col0("SELECT clinicname FROM tblclinic WHERE clinicid = {$source['clinicptr']}");
		labelRow($vetFieldLabels['clinicptr'].':', '', $clinicLabel);
	}
  myVetInputRow('email');
  myVetInputRow('officephone');
  myVetInputRow('cellphone');
  myVetInputRow('homephone');
  myVetInputRow('fax');
  myVetInputRow('pager');
  echo "</td></tr></table></td></tr>"; // END COL 2


  echo "<tr><td valign=top colspan=2><table>"; // NOTES
	$rows = 3;
	$cols = 50;
	textRow("Notes:", 'notes', $source['notes'], $rows, $cols);
	textRow("After Hours:", 'afterhours', $source['afterhours'], $rows, $cols);
	textRow("Directions:", 'directions', $source['directions'], $rows, $cols);
  echo "</table></td></tr>"; // COL 2
	
  echo "</table>";
  echo "</form>\n";
}

	

function displayClinicSummary($clinicId, $vetsToo=true) {
	global $source, $clinicFieldLabels;
	$source = getClinic($clinicId);
	echo "\n<table width=100%>\n";
	echo "<tr><td valign=top>\n<table>\n"; // COL 1
	myClinicLabelRow('clinicname');
	$sole = $source['solepractitioner'] ? "{$source['fname']} {$source['lname']}" : 'no';
	labelRow($clinicFieldLabels['solepractitioner'].':', '', $sole);
	$addr = array();
	foreach(array('street1','street2', 'city', 'state', 'zip') as $k) $addr[] = $source[$k];
	$oneLineAddr = oneLineAddress($addr);
	$addr = htmlFormattedAddress($addr);
	if($addr) $addr = 
		fauxLink('(Map)', "openConsoleWindow(\"clinicmap\", \"http://maps.google.com/maps?hl=en&q=$oneLineAddr\", 700, 700)", 
							1, 'Map this address').' '.$addr;
	if($addr)	labelRow('Address:', '', $addr, null, null, null, null, true);
	echo "</td></tr></table><td valign=top style='padding-left: 5px'><table>"; // COL 2

	myClinicLabelRow('email');
	myClinicLabelRow('officephone');
	myClinicLabelRow('cellphone');
	myClinicLabelRow('homephone');
	myClinicLabelRow('fax');
	myClinicLabelRow('pager');
	echo "</td></tr></table></td></tr>"; // END COL 2


	echo "<tr><td valign=top colspan=2><table>"; // NOTES
	$rows = 3;
	$cols = 50;
//textDisplayRow($label, $name, $value=null, $emptyTextDisplay=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null) {

	textDisplayRow("Notes:", 'notes', $source['notes']);
	textDisplayRow("After Hours:", 'afterhours', $source['afterhours']);
	textDisplayRow("Directions:", 'directions', $source['directions']);
	echo "</table></td></tr>"; // COL 2
	echo "<tr><td colspan=2>&nbsp;</td></tr>";
	if($vetsToo) displayClinicVetListSummary($clinicId, $source['solepractitioner']);

	echo "</table>";

}

function displayClinicVetListSummary($clinicId, $solepractitioner) {
	global $source, $vetFieldLabels;
	if($solepractitioner) {
		echo "<tr><td colspan=2>The veterinarian listed above is the sole practioner at this clinic.</td></tr>";
		return;
	}
	$vets = fetchAssociations("SELECT *, CONCAT_WS('&nbsp;', fname, lname) as veterinarian FROM tblvet WHERE clinicptr = $clinicId ORDER BY lname, fname asc");
	if(!$vets) {
		echo "<tr><td colspan=2>No Veterinarians associated with this clinic</td></tr>";
		return;
	}
	$cols = array('veterinarian', 'officephone', 'cellphone', 'homephone', 'pager', 'email');
	echo "<tr><td colspan=2><table width=100%>\n<tr>";
	foreach($cols as $col) echo "<th>{$vetFieldLabels[$col]}</th>";
	echo "</tr>";
	foreach($vets as $vet) {
	  echo "</tr>";
	  foreach($cols as $col) {
			$val = $vet[$col];
			if($col == 'veterinarian') $val = "<a href=# onClick=\"document.location.href='viewVet.php?id={$vet['vetid']}'\">$val</a>";
      else if($col == 'email') $val = makeEmailLink($val, $val);
		  echo "<td>$val</td>";
		}
	echo "</tr>";
	}
	echo "</table>";	
}

function displayClinicForm($clinicId=null, $action=null) {
  // null $clinicId means new clinic
  // if isset($_POST['saveClinic']) use fields from $_POST
  // else if($clinicId) use saved clinic data
  global $source, $hiddenStyle, $visibleStyle, $clinicFieldLabels;
  $action = $action ? "action = '$action" : '';
  if(isset($_POST['saveClinic'])) $source = $_POST;
  else if($clinicId) $source = getClinic($clinicId);
  else $source = array();
  echo "<form name='editclinic' method='POST' $action'>\n";
  hiddenElement('saveClinic');
  hiddenElement('clinicid', $clinicId);
  hiddenElement('solepractitioner', $source['solepractitioner']);
  echo "\n<table>\n";
  echo "<tr><td valign=top>\n<table>\n"; // COL 1
  myClinicInputRow('clinicname');
  radioButtonRow($clinicFieldLabels['solepractitioner'].':', 
      'solepractitioner', $source['solepractitioner'], 
      array('Yes'=>1,'No' =>0), 'solePractitionerChanged(this.value)');
  myClinicInputRow('fname','fnamerow');
  myClinicInputRow('lname','lnamerow');
  myClinicInputRow('street1');
  myClinicInputRow('street2');
  myClinicInputRow('city');
  myClinicInputRow('state');
  if(function_exists('dumpZipLookupJS')) 
    inputRow($clinicFieldLabels['zip'], 'zip', $source['zip'], null, null, null,  null, $onBlur='lookUpZip(this.value, "unused")');
  else myClinicInputRow('zip');
  echo "</td></tr></table><td valign=top style='padding-left: 5px'><table>"; // COL 2
	
  myClinicInputRow('email');
  myClinicInputRow('officephone');
  myClinicInputRow('cellphone');
  myClinicInputRow('homephone');
  myClinicInputRow('fax');
  myClinicInputRow('pager');
  echo "</td></tr></table></td></tr>"; // END COL 2


  echo "<tr><td valign=top colspan=2><table>"; // NOTES
	$rows = 3;
	$cols = 50;
	textRow("Notes:", 'notes', $source['notes'], $rows, $cols);
	textRow("After Hours:", 'afterhours', $source['afterhours'], $rows, $cols);
	textRow("Directions:", 'directions', $source['directions'], $rows, $cols);
  echo "</table></td></tr>"; // COL 2
	
  echo "</table>";
  echo "</form>\n";
}

function dumpvetValidationJS() {
	global $vetFieldLabels;
	$prettyNames = array();
	foreach($vetFieldLabels as $key => $label) {
		$prettyNames[] = $key;
		$prettyNames[] = $label;
	}
	$prettyNames = "'".join("','", $prettyNames)."'";
	
	echo <<<JS
setPrettynames($prettyNames);	
	
function checkAndSubmit() {
	if(MM_validateForm(
		'fname', '', 'R',
		'lname', '', 'R'
		)) document.editvet.submit();
}
	
function saveAndAdd() {
	if(MM_validateForm(
		'fname', '', 'R',
		'lname', '', 'R'
		)) {
		document.editvet.addAnother.value=1;
		document.editvet.submit();
	}
}
JS;

dumpSupplyLocationInfo();
}

function dumpClinicValidationJS() {
	global $clinicFieldLabels, $source;
	$prettyNames = array();
	foreach($clinicFieldLabels as $key => $label) {
		$prettyNames[] = $key;
		$prettyNames[] = $label;
	}
	$prettyNames = "'".join("','", $prettyNames)."'";
	
	echo <<<JS
setPrettynames($prettyNames);	
	
function checkAndSubmit() {
	if(document.editclinic.solepractitioner.value == '1') {
	  if(MM_validateForm(
		  'fname', '', 'R',
		  'lname', '', 'R',
		  'clinicname', '', 'R'
		  )) document.editclinic.submit();
	}
	else if(MM_validateForm(
		  'clinicname', '', 'R'
		  )) document.editclinic.submit();
}

function solePractitionerChanged(val) {
	var lval = val == '1';
	document.editclinic.solepractitioner.value=val;
	var isIE6 = navigator.userAgent.toLowerCase().indexOf("msie") != -1;
	var blockDisplayStyle = isIE6 ? 'block' : 'table-row';
	document.getElementById('fnamerow').style.display=(!lval ? 'none' : blockDisplayStyle);
	document.getElementById('lnamerow').style.display=(!lval ? 'none' : blockDisplayStyle);
}

solePractitionerChanged({$source['solepractitioner']});
JS;

dumpSupplyLocationInfo();
}

function dumpSupplyLocationInfo() {
  if(function_exists('dumpZipLookupJS')) {
	  echo <<<JS2
	
function supplyLocationInfo(cityState,addressGroupId) {
	var cityState = cityState.split('|');
	if(cityState[0] && cityState[1]) {
		var city = document.getElementById('city');
		var state = document.getElementById('state');
		var needConfirmation = false;
		needConfirmation = needConfirmation || (city.value.length > 0 && (city.value.toUpperCase() != cityState[0].toUpperCase()));
		needConfirmation = needConfirmation || (state.value.length > 0 && (state.value.toUpperCase() != cityState[1].toUpperCase()));
		if(!needConfirmation || confirm("Overwrite city and state with "+cityState[0]+", "+cityState[1]+"?")) {
		  if(city.value.toUpperCase() != cityState[0].toUpperCase()) city.value = cityState[0];
		  if(state.value.toUpperCase() != cityState[1].toUpperCase()) state.value = cityState[1];
		}
	}
}
JS2;
  }
}

function dumpClinicAndVetSelectElementJS($clinicSelectId, $vetSelectId, $clinicData, $vetData) {
	$debug = mattOnlyTEST() ? '1' : '0';
	
	foreach(fetchKeyValuePairs("SELECT vetid, IF(clinicptr=0 OR clinicptr IS NULL,-1,clinicptr) FROM tblvet") as $vet => $clinic) {
		$vetClinicData[] = "[$vet, $clinic]";
	}
	$vetClinicData = "[".join(', ', (array)$vetClinicData)."]";
	
	echo <<<JS
	var debug = $debug;
function clinicChanged(selectElement) {
	if(selectElement.options[selectElement.selectedIndex].value == -2) { //OPEN AN "ADD CLINIC" window
	  openConsoleWindow('addvet', 'addNewClinic.php?sel='+selectElement.id,700,520);
	  selectElement.selectedIndex = 0;
	}
	else updateVetChoices('$vetSelectId', 0);
}

function vetChanged(selectElement) {
	var vetSelection = selectElement.options[selectElement.selectedIndex].value;
	if(vetSelection == -2) { //OPEN AN "ADD VET" window
		var clinicid = document.getElementById('$clinicSelectId').value;
		if(!clinicid) clinicid = -1;
	  openConsoleWindow('addvet', 'addNewVet.php?sel='+selectElement.id+"&clinicId="+clinicid,580,530);
	  selectElement.selectedIndex = 0;
	}
	else if(vetSelection != '') {
		var clinicForVet = getClinicForVet(vetSelection);
		//if(debug) alert(clinicForVet);
		pickSelectOptionWithValue('$clinicSelectId', clinicForVet);
	}
	//updateClinicChoices('$clinicSelectId', selectElement.options[selectElement.selectedIndex].value);
}

function updateClinicChoices(selectElementId, newClinicId) {
	if(newClinicId == -1) newClinicId = 0;
	var xh = getxmlHttp();
	xh.open("GET","vet-list-ajax.php?options=allClinicChoices",true);
	xh.onreadystatechange=function() { if(xh.readyState==4) rebuildSelectOptions(selectElementId, xh.responseText, newClinicId); }
	xh.send(null);
}

function updateVetChoices(selectElementId, newVetId) {
	var xh = getxmlHttp();
	xh.open("GET","vet-list-ajax.php?options=allVets"+"&clinicId="+document.getElementById('$clinicSelectId').value,true);   // TBD: pass in clientId from SELECT element on this page
	xh.onreadystatechange=function() { 
																		if(xh.readyState==4) {
																			updateVetClinics(xh.responseText);
																			rebuildSelectOptions(selectElementId, xh.responseText, newVetId);
																			pickSelectOptionWithValue('$clinicSelectId', getClinicForVet(newVetId));
																		}
																	}
	xh.send(null);
}

//var vetClinics = new Array();  // vetId => clinicId
var vetClinics = $vetClinicData;

function updateVetClinics(rawData) {
	//if(debug) alert(rawData);

	vetClinics = new Array();
	if(rawData == 'nochange') return;
	var rows = getSelectOptionData(rawData);
	var n = 0;
	for(var i=0;i < rows.length; i++)
	  if(rows[i][0] > -1)
	    vetClinics[n++] = new Array(rows[i][0],rows[i][3]);
}

function getClinicForVet(vetId) {
	for(var i=0;i < vetClinics.length; i++)
	  if(vetClinics[i][0] == vetId) return vetClinics[i][1];
	return -1;
}

JS;
readfile("select-builder.js");
echo "\nrebuildSelectOptions('$clinicSelectId', \"$clinicData\");
rebuildSelectOptions('$vetSelectId', \"$vetData\");\n";

}
?>