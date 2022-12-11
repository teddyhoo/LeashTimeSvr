<?
// preference-fns.php

//require_once "common/init_session.php";
//require_once "common/db_fns.php";
require_once "js-gui-fns.php";

function getStandardUserPreferenceKeys() {
	$str = 'postcardsEnabled,suppressRevenueDisplay,servicesListSpan,sortClientsByFirstName,requestPageSort'
					.',showrateinclientschedulelist,emailFromLabel,showZeroVisitCountSitters,googlecreds'
					.',homePageMode,lastRecentSMSReviewDate,showMobileArrivalAndCompletionTimes,frameLayout'
					.',mobileVersionPreferred,mobileVersionOverride,webUIOnMobileDisabled,showVisitCount'
					.',requestcoordinator,firstCallToday,mobileappenabled,provuisched_client,colorCodeWAGSitters'
					.',clientChangeHistoryEnabled';
	return explode(',', $str);
}

function getUserPreferences($userid, $keys=null) {
	$filter = $keys ? "AND property IN ('".join("','", $keys)."')" : '';
	return fetchKeyValuePairs("SELECT property, value FROM tbluserpref WHERE userptr = $userid $filter");
}

function getUserPreference($userid, $property, $decrypted=false, $skipDefault=false) {
	$assoc = fetchFirstAssoc("SELECT property, value FROM tbluserpref WHERE userptr = $userid AND property = '$property' LIMIT 1");
	if($assoc['value'] && $decrypted) {
		require_once "encryption.php";
		return lt_decrypt($assoc['value']);
	}
	else if($assoc) return $assoc['value'];
	else if(!$skipDefault) return $_SESSION["preferences"][$property];
}

function setUserPreference($userid, $property, $value, $encrypted=false) {
//print_r($_SESSION["preferences"]);	
	if($value === null) deleteTable('tbluserpref', "userptr = $userid AND property='$property'", 1);
	else {
		if($encrypted) {
			require_once "encryption.php";
			$value = lt_encrypt($value);
		}
		replaceTable('tbluserpref', array('userptr' => $userid, 'property' => $property, 'value'=>$value), 1);
	}
}

function setUserPreferenceList($userid, $property, $simpleArray, $delimiter='|') {
	setUserPreference($userid, $property, join($delimiter, $simpleArray));
}

function getClientPreferences($clientid, $keys=null) {
	$filter = $keys ? "AND property IN ('".join("','", $keys)."')" : '';
	return fetchKeyValuePairs("SELECT property, value FROM tblclientpref WHERE clientptr = $clientid $filter");
}

function getClientPreference($clientid, $property, $arrayIfDefault=false) {
	$assoc = fetchFirstAssoc("SELECT property, value FROM tblclientpref WHERE clientptr = $clientid AND property = '$property' LIMIT 1");
	if($assoc) return $assoc['value'];
	else {
		$prefs = getRequestScopePreferences();
		return $arrayIfDefault 
				? array($prefs[$property]) 
				: $prefs[$property];
	}
}

function getMultipleClientPreferences($clientid, $properties) {
	$prefs = getRequestScopePreferences();
	$properties = is_string($properties) ? explode(',', $properties) : $properties;
	$props = "'".join("','", $properties)."'";
	$vals = fetchKeyValuePairs("SELECT property, value FROM tblclientpref WHERE clientptr = $clientid AND property IN ($props)");
	foreach($properties as $prop)
		if(!isset($vals[$prop])) $vals[$prop] = $prefs[$prop];
	return $vals;
}

function setClientPreference($clientid, $property, $value) {
//print_r($_SESSION["preferences"]);	
	if($value === null) deleteTable('tblclientpref', "clientptr = $clientid AND property='$property'", 1);
	else replaceTable('tblclientpref', array('clientptr' => $clientid, 'property' => $property, 'value'=>$value), 1);
}

function getAppointmentProperty($appointmentid, $property) {
	return fetchRow0Col0("SELECT value FROM tblappointmentprop WHERE appointmentptr = $appointmentid AND property = '$property' LIMIT 1");
}

function getAppointmentProperties($appointmentid, $properties=null) {
	if($properties) {
		$properties = is_array($properties) ? $properties : explode(',', $properties);
		$properties = join("','", $properties);
		$filter = " AND property IN ('$properties')";
	}
	return fetchKeyValuePairs("SELECT property, value FROM tblappointmentprop WHERE appointmentptr = $appointmentid $filter", 1);
}

function setAppointmentProperty($appointmentid, $property, $value) {
//print_r($_SESSION["preferences"]);	
	if($value === null) deleteTable('tblappointmentprop', "appointmentptr = $appointmentid AND property='$property'", 1);
	else replaceTable('tblappointmentprop', array('appointmentptr' => $appointmentid, 'property' => $property, 'value'=>$value), 1);
}

function getServiceTypeProperty($servicetypeid, $property) {
	return fetchRow0Col0("SELECT value FROM tblservcietypeprop WHERE servicetypeptr = $servicetypeid AND property = '$property' LIMIT 1");
}

function getServiceTypeProperties($servicetypeid, $keys=null) {
	$filter = $keys ? "AND property IN ('".join("','", $keys)."')" : '';
	return fetchKeyValuePairs("SELECT property, value FROM tblservcietypeprop WHERE servicetypeptr = $servicetypeid $filter");
}

function setServiceTypeProperty($servicetypeid, $property, $value) {
//print_r($_SESSION["preferences"]);	
	if($value === null) deleteTable('tblservcietypeprop', "servicetypeptr = $servicetypeid AND property='$property'", 1);
	else replaceTable('tblservcietypeprop', array('servicetypeptr' => $servicetypeid, 'property' => $property, 'value'=>$value), 1);
}

function getProviderPreferences($providerid, $keys=null) {
	$filter = $keys ? "AND property IN ('".join("','", $keys)."')" : '';
	return fetchKeyValuePairs("SELECT property, value FROM tblproviderpref WHERE providerptr = $providerid $filter");
}

function getProviderPreference($providerid, $property, $arrayIfDefault=false) {
	$assoc = fetchFirstAssoc("SELECT property, value FROM tblproviderpref WHERE providerptr = $providerid AND property = '$property'");
	if($assoc) return $assoc['value'];
	else {
		
		$prefs = getRequestScopePreferences();
		return $arrayIfDefault 
				? array($prefs[$property]) 
				: $prefs[$property];
	}
}

function getRequestScopePreferences() {
	global $prefs;
	if($_SESSION) return $_SESSION["preferences"];
	else if(TRUE) {
		$prefs = fetchPreferences();
		return $prefs; // I suspect that there is a logic in the else clause below...
	}
	else {
		global $lastRequestScopeDB, $db;
		if(!$prefs || $lastRequestScopeDB != $db) $prefs = fetchPreferences();
		$lastRequestScopeDB = $db;
		return $prefs;
	}
}
	

function setProviderPreference($providerid, $property, $value) {
//print_r($_SESSION["preferences"]);	
	if($value === null) deleteTable('tblproviderpref', "providerptr = $providerid AND property='$property'", 1);
	else replaceTable('tblproviderpref', array('providerptr' => $providerid, 'property' => $property, 'value'=>$value), 1);
}

/* Maybe later...
ALTER TABLE `tblclientpref`
ADD COLUMN  `created` datetime default NULL,
ADD COLUMN  `modified` datetime default NULL,
ADD COLUMN  `createdby` int(11) default NULL,
ADD COLUMN  `modifiedby` int(11) default NULL
*/

function fetchPreference($prop) { // force it
	return fetchRow0Col0("SELECT value FROM tblpreference WHERE property = '$prop' LIMIT 1", 1);
}

function refreshPreferences() {
	$_SESSION['preferences'] = fetchPreferences();
}

function fetchPreferences() {
	global $db, $dbhost, $dbuser, $dbpass;
	if(isset($_SESSION) && $_SESSION['dbhost']) {
		mysqli_close();
		mysqli_connect($_SESSION["dbhost"], $_SESSION["dbuser"], $_SESSION["dbpass"]);
		mysqli_select_db($_SESSION["db"]);
	}
	$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
	foreach($prefs as $k => $v) $prefs[$k] = stripslashes($v);
	if(isset($_SESSION) && $_SESSION['dbhost']) {
		mysqli_close();
		mysqli_connect($dbhost, $dbuser, $dbpass);
		mysqli_select_db($db);  // select previously-selected db
	}
	// do NOT return CC merchant info
	unset($prefs['x_login']);
	unset($prefs['x_tran_key']);
	return $prefs;
}

function getPreference($property) {
	return $_SESSION['preferences'][$property];
}

function setPreference($property, $value) {
//print_r($_SESSION["preferences"]);	
	if(!$value) deleteTable('tblpreference', "property='$property'", 1);
  //else if(!array_key_exists($property, $_SESSION["preferences"])) 
	//  insertTable('tblpreference', array('property' => $property,'value'=>$value), 1);
	//else updateTable('tblpreference', array('value'=>$value), "property='$property'", 1);
	else replaceTable('tblpreference', array('property' => $property, 'value'=>$value), "property='$property'", 1);
	if($_SESSION["preferences"]) $_SESSION["preferences"][$property] = $value;  // do NOT set $_SESSION
	//$_SESSION["preferences"][$property] = $value;
}

function setPreferencePairList($property, $assocArray, $pairDelimiter='||', $equalsDelimiter='|') {
	$values = array();
	foreach($assocArray as $prop =>$val) $values[] = "$prop$equalsDelimiter$val";
	setPreference($property, join($pairDelimiter, $values));
}

function setPreferenceList($property, $simpleArray, $delimiter='|') {
	setPreference($property, join($delimiter, $simpleArray));
}

function preferenceListEditorTable2($property, $delimiter='|', $sortable=null) {
	echo "Drag and drop boxes to reorder the list.<br>Click once to edit a label.<br>Double-click to select the whole label.<p>";
	$values = explode($delimiter, $_SESSION["preferences"][$property]);
	$totalFields = count($values)+10;
	$visibleFields = count($values);
	hiddenElement($property."_visible", $visibleFields);
	$itemList = array();
	foreach($values as $val) {
		$elName = "pref_$property".count($itemList);
		$itemList[$val] = "<img src='art/drag.gif' width=10 height=10> <input class='standardInput' id='$elName' name='$elName' value='$val' size=30 autocomplete='off'>";
  }
	require_once "dragsortJQ.php";
	echo headerInsert('sortList');
	$extraLIs = array();
	for($i=count($itemList);$i<$totalFields;$i++) {
		$elName = "pref_$property$i";
		//$extraLIs[] = "<li id='li_new$i' style='display:none;' ondblclick='selectClick(this);' onclick='focusClick(this);' disabled=true><img src='art/drag.gif' width=10 height=10> <input class='standardInput' id='$elName' name='$elName' value='' size=30 autocomplete='off'></li>\n";
		$extraLIs[] = "<li class='ui-state-default' id='li_new$i' style='padding-top:0px;padding-bottom:0px;display:none;' onclick='focusClick(this);' ondblclick='selectClick(this);' disabled=true><img src='art/drag.gif' width=10 height=10> <input class='standardInput' id='$elName' name='$elName' value='' size=30 autocomplete='off'></li>";
	}
	echoSortList($itemList, 'sortList', $numbered=false, join("", $extraLIs));
	$showButton = true;
	for($i=count($itemList);$i<$totalFields;$i++) {
		$elName = "pref_$property$i";
		$bStyle = $showButton ? "style='display:inline;'" : "style='display:none;'";
		$showButton = false;
		echo ($i < $totalFields-1)
			? "<span id= 'buttspan$property$i' $bStyle>".echoButton("addAnother_$property$i", "Add another", "addAnother2(this, $i, \"$property\")", '','',true)."<br></span>\n"
			: "<span id= 'buttspan$property$i' $bStyle>To add more, please Save Changes first and reopen this editor.<br></span>\n";
	}
	echo "<p>";
}

function preferenceListEditorTable($property, $delimiter='|', $sortable=null) {
	$values = explode($delimiter, $_SESSION["preferences"][$property]);
	echo "<table>\n";
	$totalFields = count($values)+10;
	$visibleFields = count($values)+1;
	hiddenElement($property."_visible", $visibleFields);
	//echo "<tr><th colspan=2>$property List</th></tr>\n";
	for($i=1; $i <= $totalFields; $i++) {
		$val = $i <= count($values) ? $values[$i-1] : '';
		$display = $i <= $visibleFields ? $_SESSION['tableRowDisplayMode'] : 'none';
		echo "\n<tr id='row_pref_$property$i' style='display:$display'><td>";
		echo $i == 1 ? '&nbsp' : "<img src='art/sort_up.gif' onClick='moveUp(\"pref_$property\", $i)'>";
		$downVisibility = $i < $visibleFields ? 'visible' : 'hidden';
		echo "</td>\n   <td id='down_$i' style='visibility:$downVisibility'>";
		echo $i >= $totalFields ? '&nbsp' : "<img src='art/sort_down.gif' onClick='moveDown(\"pref_$property\", $i)'>";
		echo "</td><td>";
		
		labeledInput('', "pref_$property$i", $val, $labelClass=null, $inputClass=null, $onBlur=null);
		//inputRow('', "pref_$property$i", $val, null, null, "row_pref_$property$i",  "display:$display");
		echo "</td><tr>";
		
		$display = $i == $visibleFields + 1  ? $_SESSION['tableRowDisplayMode'] : 'none';
		echo "<tr id='addAnother_$property$i' style='display:$display'>\n";
		if($i < $totalFields) {
			$next = $i+1;
			echo "<td>&nbsp;</td><td>&nbsp</td><td>";
		  echoButton('', "Add another", 
		   "addAnother(this, $i, \"$property\")");
		}
		else echo "<td colspan=3 class='tiplooks'>To add more, please Save Changes first and reopen this editor.";
		echo "</td>\n</tr>";
	}
	echo "</table>\n";
}

function preferencesTable($sections, &$help, $showSections, $userprefs=false, $explanations=null, $sectionParams=null) { // array('label1'=>petTypes|Pet Types|list||bizName|Business Name|string||...
	global $Maint_editor_user_id;
	$n = 1;
	echo "<table width=100%>\n<span style='font-size:1.1em'><tr><td>";
	$userArg = $Maint_editor_user_id ? "&id=$Maint_editor_user_id" : '';
	if($showSections != 'all') echo "<a href='".basename($_SERVER['SCRIPT_NAME'])."?show=all$userArg'>Show All Preferences</a>\n";
	else echo "<a href='".basename($_SERVER['SCRIPT_NAME'])."?show=none$userArg'>Hide All Preferences</a>\n";
	echo " - <span class='tiplooks'>Click on a bar to expand the section.  Click it again to shrink it.</span></span>";
	
	echo "<img src='art/spacer.gif' width=70 height=1>";
	if(FALSE && !$userprefs) echoButton('', 'Setup Wizard', 
							'$.fn.colorbox({href:"biz-setup-wizard.php", width:"750", height:"470", scrolling: true, opacity: "0.3", iframe: "true"});',
							'BigButton', 'BigButtonDown', null, 'Use this wizard to set essential preferences.');
	if(!$userprefs && staffOnlyTEST()) {
		fauxLink('Preference Usage', 'openConsoleWindow("prefusage", "report-preference-usage.php", 500, 500)');
	}
	echo "</td></tr>";

	$explanations = (array)$explanations;
	foreach($sections as $label => $section) {
		startAShrinkSection($label, "section$n", 
														($showSections != 'all' && !in_array($n, (array)$showSections)));
		$n++;

		preferencesEditorLauncher($section, $help, $userprefs, $explanations[$label], $sectionParams[$label]['applyToAll']);
		endAShrinkSection();
		//echo "<h3>$label</h3>\n";
	}
	echo "</table>\n";
}

function preferencesEditorLauncher($prefKeysAndTypes, &$help, $userprefs=false, $explanationString=null, $applyToAll=null) { // petTypes|Pet Types|list||bizName|Business Name|string||...
	// $applyToAll: null|sitters|clients
	global $Maint_editor_user_id;
	echo "<table style='font-size:1.05em;'>\n";
	if($explanationString) foreach(explode('||', $explanationString) as $expl) {
		$expl = explode('|', $expl);
		$explanations[$expl[0]] = $expl[1];
	}
	


	foreach(explode('||', $prefKeysAndTypes) as $groupStr) {
		if($groupStr == 'HR') {
			echo '<tr><td colspan=2><hr></td></tr>';
			continue;
		}
		$group = explode('|', $groupStr);
		$groupStr = urlencode($groupStr);
		
		$title = $help[$group[0]] ? $help[$group[0]] : 'Edit this property';

		if($userprefs) {
			$generalEditor = 'editUserProp';
			$userId = $Maint_editor_user_id ? $Maint_editor_user_id : $_SESSION['auth_user_id'];
			$rawVal = $val = getUserPreference($userId, $group[0]);
		}
		else { 
			$generalEditor =  'editProp';
			$rawVal = $val = $_SESSION["preferences"][$group[0]];
		}
		
		if($group[0] == 'requestlistcolumns') {
			$vlabs = explodePairsLine('address|Address||phone|Phone||clientname|Client||requesttype|Type||date|Date||condnote|Any Note||note|Request Note||officenotes|Office Notes');
			$val = '';
			$vparts = array();
			foreach(explode('|', (string)$rawVal) as $part)
				if($part) $vparts[] = $vlabs[$part];
			if($vparts) $val = join(', ', $vparts);
		}
		
		if($group[0] != 'providerScheduleEmailPrefs' && strip_tags((string)$val) != $val)
			$val = "<b><span class='tiplooks' title='This value has HTML code which is not displayed here.  Click the link at left to edit it.'>[HTML]</span></b> ".strip_tags((string)$val);
		if($group[2] == 'customlightbox') /*$val =*/ $label = fauxLink($group[1], customLightboxLauncher($group), 1, $title);
		else if($group[2] == 'client_boolean') /*$val =*/ $label = fauxLink($group[1], booleanLightboxLauncher($group, 'client'), 1, $title);
		else if($group[2] == 'provider_boolean') /*$val =*/ $label = fauxLink($group[1], booleanLightboxLauncher($group, 'provider'), 1, $title);
		else if(strpos($group[2], 'custom') === 0) /*$val =*/ $label = fauxLink($group[1], customLauncher($group), 1, $title);
		else $label = fauxLink($group[1], "$generalEditor(\"$groupStr\", null, \"$userId\", \"$applyToAll\")", 1, $title);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') screenLog("{$group[0]}: [$rawVal]");
		if($group[2] == 'list') $val = join('<br>', explode('|', $val));
		else if(in_array($group[2], array('boolean', 'custom_boolean', 'client_boolean', 'provider_boolean'))) $val = $rawVal ? 'yes' : 'no';
		else if($group[2] == 'password') {
			$len = strlen($val);
			$val = '';
			for($i=0; $i < $len; $i++) $val .= '*';
		}
		else if(in_array($group[0], array('bimonthlyBillOn1', 'bimonthlyBillOn2')) &&
						$val == $_SESSION["preferences"]['monthlyBillOn'] && $_SESSION["preferences"]['monthlyServicesPrepaid'])
						$val .= " (fixed-price monthly schedule bill date)";
		else if($group[2] == 'page') {
			$label = fauxLink($group[1], "document.location.href=\"{$group[3]}\"", 1, $title);
		 }
		$anchorName = $group[1];
		if($explanations) $explanation = "<td class='tiplooks' style='text-align:left;padding-left:5px;'>{$explanations[$group[0]]}</td>";
		global $staffOnlyPreferences;
		$style = in_array($group[0], (array)$staffOnlyPreferences) ? "style='font-style:italic;font-weight:bold;'" : '';
		echo "<tr><td id='prop_{$group[0]}' valign='top' $style>$label</td><td style='text-align:top;padding-left:10px;'>$val</td>$explanation</tr>\n";
	} // </a>
	echo "</table>\n";
}

function customLightboxLauncher($group) {
	$dims = $group[4] ? explode(',', $group[4]) : array(500,300);
	return "$(document).ready(function(){\$.fn.colorbox({href:\"{$group[3]}\", width:\"{$dims[0]}\", height:\"{$dims[1]}\", scrolling: true, iframe: true, opacity: \"0.3\"})})";
}

function booleanLightboxLauncher($group, $type) {
	$dims = $group[4] ? explode(',', $group[4]) : array(500,320);
	return "$(document).ready(function(){\$.fn.colorbox({href:\"preference-global-individual.php?label={$group[1]}&type=$type&key={$group[0]}\", width:\"{$dims[0]}\", height:\"{$dims[1]}\", scrolling: true, iframe: true, opacity: \"0.3\"})})";
}

function customLauncher($group) {
	$dims = $group[4] ? $group[4] : ",600,300";
	return "openConsoleWindow(\"propEditor\", \"{$group[3]}\" $dims)";
}

function yesNoLabel($value) {
	return $value ? 'Yes' : 'No';
}

function heritableYesNoOptionsRow($label, $property, $value) { // value is inherited if an array
		$value = is_array($value) ? -1 : $value;
		$options = array("Yes"=>1, "No"=>0, "default (".yesNoLabel($_SESSION["preferences"][$property]).")"=>-1); 
		//checkboxRow($label, $property, $value);
		radioButtonRow($label, $property, $value, $options);
}

function dumpPrefsJS() {
  echo <<<FUNC
function addAnother(el, num, property) {
	var blockdisplay = navigator.userAgent.toLowerCase().indexOf("msie") != -1 ? "block" : "table-row";
  document.getElementById("addAnother_"+property+num).style.display="none";
  document.getElementById("addAnother_"+property+(num+1)).style.display=blockdisplay;
  document.getElementById("row_pref_"+property+(num+1)).style.display=blockdisplay;
  document.getElementById(property+"_visible").value=num;
  document.getElementById("down_"+(num-1)).style.visibility='visible';
//alert(document.getElementById("down_"+(num-1)).id+" vis: "+document.getElementById("down_"+(num-1)).style.visibility);	
}
  
function addAnother2(el, num, property) {
	var blockdisplay = "list-item";
  document.getElementById("buttspan"+property+num).style.display="none";
  document.getElementById("buttspan"+property+(num+1)).style.display='inline';
  document.getElementById("li_new"+(num+1)).style.display=blockdisplay;
  document.getElementById("li_new"+(num+1)).disabled=false;
  document.getElementById(property+"_visible").value=parseInt(document.getElementById(property+"_visible").value)+1;
//alert(document.getElementById("down_"+(num-1)).id+" vis: "+document.getElementById("down_"+(num-1)).style.visibility);	
}
  
function editProp(groupStr,widgetName,unused,applyToAll) {
	var widgetParam = widgetName ? '&widgetName='+widgetName : '';
	var extraParams = '';
	if(applyToAll == 'sitters') extraParams += '&applyToAllSittersOption=1';
	openConsoleWindow('propEditor', 'property-edit2.php?prop='+groupStr+widgetParam+extraParams,500,300);
}	

function editUserProp(groupStr,widgetName,userid) {
	var widgetParam = widgetName ? '&widgetName='+widgetName : '';
	if(userid && typeof userid != 'undefined') userid = '&userid='+userid;
	openConsoleWindow('propEditor', 'user-property-edit.php?prop='+groupStr+widgetParam+userid,500,300);
}	

function moveUp(prefix, num) {
  var temp = document.getElementById(prefix+(num-1)).value;
  document.getElementById(prefix+(num-1)).value = document.getElementById(prefix+num).value;
  document.getElementById(prefix+num).value = temp;
}	

function moveDown(prefix, num) {
  var temp = document.getElementById(prefix+(num+1)).value;
  document.getElementById(prefix+(num+1)).value = document.getElementById(prefix+num).value;
  document.getElementById(prefix+num).value = temp;
}	

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

FUNC;

}

function recipientsTable($clientid, $messageTypes=null) {
	// modes = 0:primary, 1:Alternate, -1:None, 2:Both
	if(!$messageTypes) $messageTypes = explode(',','enhancedvisitreport');
	$emails = fetchFirstAssoc("SELECT email, email2 FROM tblclient WHERE clientid = $clientid LIMIT 1", 1);
	$typeLabels = explodePairsLine('evr|Enhanced Visit Report');
	$columns = explodePairsLine('label| ||email|Primary||email2|Alt. Email||none|Neither');
	$rowValues = fetchKeyValuePairs("SELECT property, value FROM tblclientpref WHERE clientptr = $clientid AND property LIKE 'emailrecips_%'", 1);
	foreach($typeLabels as $type=>$label) {
		$row = array('label'=>$label);
		$lineValue = $rowValues["emailrecips_$type"];
		$row['email'] = labeledCheckbox('', "emailrecips_{$type}_email", in_array($lineValue, array(0,2)), $labelClass=null, $inputClass=null, "recipClick(this, \"$type\", 0)", $boxFirst=false, $noEcho=true, $title='Send to primary email address.');
		$row['email2'] = labeledCheckbox('', "emailrecips_{$type}_email2", in_array($lineValue, array(1,2)), $labelClass=null, $inputClass=null, "recipClick(this, \"$type\", 1)", $boxFirst=false, $noEcho=true, $title='Send to alternate email address.');
		$row['none'] = labeledCheckbox('', "emailrecips_{$type}_none", ($lineValue == -1), $labelClass=null, $inputClass=null, "recipClick(this, \"$type\", -1)", $boxFirst=false, $noEcho=true, $title='Send to neither email address.');
		$rows[] = $row;
	}
	echo "Current addresses:<ul>";
	foreach($emails as $k=>$email) echo "<li>{$columns[$k]} address: ".($email ? $email : '--');
	echo "</ul>";
	tableFrom($columns, $rows, $attributes='border=1 bordercolor=lightgrey', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
	echo <<<JS

<script language='javascript'>
function recipClick(thisel, propname, elval) {
	var thisElChecked = thisel.checked;
	if(thisElChecked) {
		if(elval == -1) {
			document.getElementById('emailrecips_'+propname+'_email').checked = false;
			document.getElementById('emailrecips_'+propname+'_email2').checked = false;
		}
		else document.getElementById('emailrecips_'+propname+'_none').checked = false;
	}
}
</script>
JS;
}

function getRecipientsPreferences($args) {
	foreach($args as $k => $v) {
		if(strpos($k, 'emailrecips_') !== 0) continue;
		$parts = explode('_', $k);
		$lines[$parts[1]][$parts[2]] = $v;
	}
	$prefs = array();
	foreach((array)$lines as $prop => $boxes) {
		if($boxes['email'] && $boxes['email2']) $prefs['emailrecips_'.$prop] = 2;
		else if($boxes['email']) $prefs['emailrecips_'.$prop] = 0;
		else if($boxes['email2']) $prefs['emailrecips_'.$prop] = 1;
		else $prefs['emailrecips_'.$prop] = -1;
	}
	return $prefs;
}


?>