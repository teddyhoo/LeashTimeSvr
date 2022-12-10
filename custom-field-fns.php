<?
// custom-field-fns.php

// custom field preferences are stored in tblpreference
//format: label|active|onelineORtextORboolean|visitsheet|clientvisible
// hyperlink added 12/20/2013 
// file added 12/19/2016 
// 0-label, 1-active, 2-type, 3-visitsheetonly, 4-clientvisible

require_once "preference-fns.php";
require_once "gui-fns.php";

function customFieldDisplayOrder($prefix) {
	$order = getPreference("order_$prefix");
	$items = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE '$prefix%'");
	if($order) $order = explode(',', (string)$order);
	else 
		for($i=1; isset($items["$prefix$i"]); $i++)
			$order[] = "$prefix$i";
	$itemList = array();
	foreach((array)$order as $field)
		$itemList[$field] = substr($items[$field], 0, strpos($items[$field], '|'));
	foreach($items as $field => $desc)
		if(!isset($itemList[$field]))
			$itemList[$field] = substr($items[$field], 0, strpos($items[$field], '|'));
	return $itemList;
}

function displayOrderCustomFields($customFields, $prefix) {
	$order = customFieldDisplayOrder($prefix);
	$out = array();
	foreach($order as $key => $label) {
		
		if(isset($customFields[$key])) $out[$key] = $customFields[$key];
	}
	return $out;
}

function getCustomFields($activeOnly=false, $visitSheetOnly=false, $fieldNames=null, $clientVisibleOnly=false) {
	if($fieldNames === null) $fieldNames = getCustomFieldNames();
	$fields = array();
	foreach($fieldNames as $fieldName) {
		$field = explode('|', $_SESSION['preferences'][$fieldName]);
//if(mattOnlyTEST()) { echo "$fieldName: [".print_r($field, 1)."]<br>";}
		/*$clientVisibleOnly = 
			$clientVisibleOnly   // client-only asked for
			&& (($_SESSION['auth_login_id'] == 'ekrum' || clientVisibleEnabledForDb()) // gate is open
					|| $field[3]);  */
		if(!($_SESSION['auth_login_id'] == 'ekrum' || clientVisibleEnabledForDb())) $field[4] = $field[3];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "Vis Only $fieldName: [$clientVisibleOnly]<br>";}
		
		if((!$activeOnly || $field[1]) 
			&& (!$visitSheetOnly || $field[3])
			&& (!$clientVisibleOnly || $field[4]))
			$fields[$fieldName] = $field;
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "getCustomFields fields: [".print_r($fields, 1)."]<br>";}
	return $fields;
}

// CLIENT FIELDS
function getCustomFieldNames() {
	$names = array();
	$nums = array();
	foreach($_SESSION['preferences'] as $key => $name)
		if(strpos($key, 'custom') === 0) $nums[] = substr($key, strlen('custom'));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog("nums:".print_r($nums, 1)."<br>");}
		
	sort($nums);
	foreach($nums as $num) $names[] = "custom$num";
	//for($i=1;isset($_SESSION['preferences']["custom$i"]); $i++) $names[] = "custom$i";
	return $names;
}
	
function getClientCustomField($client, $field) {
	if(!$client) return null;
	return fetchRow0Col0("SELECT value FROM relclientcustomfield WHERE clientptr = $client AND fieldname = '$field'");
}

function getClientCustomFields($client) {
	if(!$client) return array();
	return fetchKeyValuePairs("SELECT fieldname, value FROM relclientcustomfield WHERE clientptr = $client");
}

function saveClientCustomFields($clientid, $pairs, $pairsOnly=false) {
	$trios = array();
	$customFields = getCustomFields(true);
	foreach($customFields as $fieldName => $descr) {
		if($pairsOnly && !isset($pairs[$fieldName])) continue;
		$val = isset($pairs[$fieldName]) ? $pairs[$fieldName] : '';
		if($descr[2] == 'boolean') 
			$trios[] = "($clientid, '$fieldName', ".($val ? 1 : 0).")";
		else if(isset($pairs[$fieldName]))
			$trios[] = "($clientid, '$fieldName', ".val($val).")";
	}
	$trios = join(', ',$trios);
	if($trios) doQuery("REPLACE relclientcustomfield (clientptr, fieldname, value) VALUES $trios");
}

function htmlVersion($val) {
	if(!$val) return;
	return str_replace("\n\n", "<p>", str_replace("\n", "<br>", trim($val)));
}

	
// PET FIELDS
function getPetCustomFieldNames() {
	$names = array();
	$nums = array();
	foreach($_SESSION['preferences'] as $key => $name)
		if(strpos($key, 'petcustom') === 0) $nums[] = substr($key, strlen('petcustom'));
		
	sort($nums);
	foreach($nums as $num) $names[] = "petcustom$num";
	return $names;
	
	//$names = array();
	//for($i=1;isset($_SESSION['preferences']["petcustom$i"]); $i++) $names[] = "petcustom$i";
	//return $names;
}
	
function getPetCustomField($petid, $field) {
	if(!$petid) return null;
	return fetchRow0Col0("SELECT value FROM relpetcustomfield WHERE petptr = $petid AND fieldname = '$field'");
}

function getPetCustomFields($petid, $raw=false) {
	if(!$petid) return array();
	$fields =  fetchKeyValuePairs("SELECT fieldname, value FROM relpetcustomfield WHERE petptr = $petid");
	if(!$raw) foreach($fields as $k => $v) $fields[$k] = htmlVersion($v);
	return $fields;
}

function savePetCustomFields($petid, $pairs, $number, $pairsOnly=false) {
	require_once "field-utils.php";
	
	$trios = array();
	$customFields = getCustomFields(true, false, getPetCustomFieldNames());
	if(!$customFields) return;
	foreach($customFields as $fieldName => $descr) {
		// assume that pairs keys are "petN_custom1", "petN_custom2", ...
		$prefix = $number ? "pet$number"."_" : '';    //substr($fieldName, strpos($fieldName, '_'));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($pairs); exit;}		
		if($pairsOnly && !array_key_exists("$fieldName", $pairs)) continue;
		$val = isset($pairs["$prefix$fieldName"]) ? $pairs["$prefix$fieldName"] : $pairs["$fieldName"];
		if($descr[2] == 'boolean') 
			$trios[] = "($petid, '$fieldName', ".($val ? 1 : 0).")";
		else if(isset($pairs["$prefix$fieldName"]) || isset($pairs["$fieldName"]))
			$trios[] = "($petid, '$fieldName', ".val((string)$val).")"; //val(cleanseString((string)$val))
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($trios); exit;}
//if($number == 3 && $_SERVER['REMOTE_ADDR'] == '68.225.89.173') {  print_r("$prefix$fieldName: $val [{$descr[2]}]".print_r($trios,1));exit;}
	$trios = join(', ',$trios);
	if($trios) doQuery("REPLACE relpetcustomfield (petptr, fieldname, value) VALUES $trios");
}

// END PETS
	
function customFieldsTable($client, $customFields, $prefix=null, $groupClass=null, $hideEmpties=false) {
//if(mattOnlyTEST()) print_r($client);	
//if(mattOnlyTEST()) echo "<pre>".print_r($customFields,1)."</pre>";
	echo "<p><table class='sortableListCell'>";
	foreach($customFields as $key => $descr) {
		$label = $descr[0];
		if($label && $label[strlen($label)-1] != '?' && $label[strlen($label)-1] != ':') $label .= ':';
		// consider the row to be 'narrow' for previously one-line fields
		$narrow = 1 && TRUE; //mattOnlyTEST();
		$rowStyle = $hideEmpties && !$client[$key] ? 'display:none;' : '';
		$id = $prefix.$key;
		if(TRUE) {
		$showAll = fetchPreference('showAllCustomFieldsInMobileSitterApps');
		if(!$descr[3]) { // NOT VISIT SHEETS
			$labelClass = 'italicized warning';
			$appVisibility = $showAll ? ', but included in mobile sitter apps.' : ' or mobile sitter apps.';
			$legend[3] = "<span class='$labelClass'>red italics</span> = Not included in visit sheets$appVisibility  Not shown to client.";
		}
		else if(clientVisibleEnabledForDb() && !$descr[4]) { // NOT CLIENTS
			$labelClass = 'italicized purplewarning';
			$legend[4] = "<span class='$labelClass'>purple italics</span> = Included in visit sheets and sitter apps.  Not shown to client.";
		}
		else $labelClass = '';
		//$descr[3] ? '' : 'italicized warning'; //print_r($descr);
		}
		if($descr[2] == 'oneline' || $descr[2] == 'hyperlink') {
			if($narrow) {
				//echo "<tr style='$rowStyle'><td colspan=2 class='$labelClass $groupClass'><label for='$id'>{$label}</label></td></tr>";
				//echo "<tr style='$rowStyle'><td colspan=2>
				echo "<tr style='$rowStyle'><td colspan=2 class='$labelClass'><label for='$id'>{$label}</label><br>";
				echo "
					<input id='$id' name='$id' class='input600 $groupClass' value='".safeValue($client[$key])."'></td></tr>";
			}
			else {
				inputRow($label, $id, $client[$key], $labelClass, "input600 $groupClass", null, $rowStyle);
			}
		}
		//else if($descr[2] == 'hyperlink')
		//  inputRow($descr[0].':', $id, $client[$key], $labelClass, "input600 $groupClass", null, $rowStyle);
		  
		else if($descr[2] == 'file') {
//if(mattOnlyTEST()) echo "<tr><td>BANG![groupClass: $groupClass][$rowStyle]"."<pre>".print_r($descr,1)."</pre>";			
//if(mattOnlyTEST()) echo "<pre>".print_r($descr,1)."</pre>";
//if(mattOnlyTEST()) echo "<pre>KEY: [$key]".print_r($client,1)."</pre>";
			if($valueCellContent = $client[$key]) {
				$remotefileid = $valueCellContent;
				if(!($validRemoteFileId = is_numeric($remotefileid) && $remotefileid > 0 && $remotefileid == round($remotefileid))) 
					$linkLabel = 'File missing: ['.truncatedLabel($remotefileid, 10).']';
				else {
					$valueCellContent = fetchRow0Col0(
						"SELECT remotepath 
							FROM tblremotefile 
							WHERE remotefileid = $remotefileid
								AND ownerptr = {$client['clientid']} AND ownertable = 'tblclient' LIMIT 1", 1);
					if(!$valueCellContent) {
						$linkLabel = 'File missing [$remotefileid].';
						$validRemoteFileId = false;
					}
					else $linkLabel = basename($valueCellContent);
				}
			}
			else $linkLabel = '';
			
			$pencil = "<div class=\"fa fa-pencil fa-1x\" style=\"display:inline;color:gray;\"></div>";
			if(userRole() != 'c') $valueCellContent = fauxLink($pencil, "setFile(\"$id\")",	$noEcho=true, $title='Assign a file');
			else $valueCellContent = '';
			$valueCellContent .= hiddenElement($id, $client[$key], $inputClass=null, $noEcho=true);
			$valueCellContent .= " "
					.fauxLink($linkLabel, "openDocument(\"$id\");", $noEcho=true, 
																		$title='View this file', "{$id}_link");
			$valueCellContent = "<span class='$groupClass'>$valueCellContent</span>";
		  labelRow($label, null, $valueCellContent, $labelClass, null, null, $rowStyle, $rawValue=true);
//labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
		}

		else if($descr[2] == 'text')		
			textRow($label, $id, $client[$key], 3, 40, $labelClass, "$groupClass", null, $rowStyle);
		else if($descr[2] == 'boolean') {
			if($narrow) {
				//echo "<tr style='$rowStyle'><td colspan=2 class='$labelClass $groupClass'><label for='$id'>$label</label></td></tr>";
				//echo "<tr style='$rowStyle'><td colspan=2>
				echo "<tr style='$rowStyle'><td colspan=2 class='$labelClass $groupClass'><label for='$id'>$label</label><br>";
				echo "
				<input id='$id' name='$id' type='checkbox' class='$groupClass' ".($client[$key] ? 'CHECKED' : '')."></td></tr>";
			}
			else {
				checkboxRow($label, $id, $client[$key], "$groupClass $labelClass");
			}
		}
	}
	if($legend) {
		echo "<tr><td colspan=2><hr></td></tr>";
		foreach($legend as $explanation)
			echo "<tr><td colspan=2 style='text-align:left;padding-top:7px;'>$explanation</td></tr>";
		}
	echo "</table>";
}

function clientDocumentFileLink($id, $remotefileid, $clientid, $editable=false) {
	if($remotefileid) {
		if(!($validRemoteFileId = is_numeric($remotefileid) && $remotefileid > 0 && $remotefileid == round($remotefileid))) 
			$label = 'File missing: ['.truncatedLabel($remotefileid, 10).']';
		else {
			$valueCellContent = fetchRow0Col0(
				"SELECT remotepath 
					FROM tblremotefile 
					WHERE remotefileid = $remotefileid
						AND ownerptr = $clientid AND ownertable = 'tblclient' LIMIT 1", 1);
			if(!$valueCellContent) {
				$label = 'File missing [$remotefileid].';
				$validRemoteFileId = false;
			}
			else $label = basename($valueCellContent);
		}
	}
	else $label = '';

	$pencil = "<div class=\"fa fa-pencil fa-1x\" style=\"display:inline;color:gray;\"></div>";
	if($editable) $valueCellContent = fauxLink($pencil, "setFile(\"$id\")",	$noEcho=true, $title='Assign a file');
	else $valueCellContent = '';
	$valueCellContent .= hiddenElement($id, $remotefileid, $inputClass=null, $noEcho=true);
	$valueCellContent .= " "
			.fauxLink($label, "openDocument(\"$id\");", $noEcho=true, 
																$title='View this file', "{$id}_link");
	//$valueCellContent = "<span class='$groupClass'>$valueCellContent</span>";
	return $valueCellContent;
}

function dumpCustomFieldJavascript($clientid) {
	//$TEST = mattOnlyTEST() ?  "alert(objString);" : '';
	echo <<<CUSTOMFIELDJS
// Custom Field Javascript FNS
function openDocument(elid) {
	var remotefileid = document.getElementById(elid).value;
	openConsoleWindow("fileviewer", "client-file-view.php?id="+remotefileid, 700, 700);
}

CUSTOMFIELDJS;

	if(adequateRights('#ec')) echo <<<CUSTOMFIELDJS2
function setFile(elid) {
	// open a lightbox on remote-file-chooser.php?client=$clientid&field="+elid
	$.fn.colorbox({href:"remote-file-chooser.php?client=$clientid&field="+elid, width:"500", height:"500", iframe: "true", scrolling: true, opacity: "0.3"});
}

function updateFileCustomField(objString) {
	var obj = JSON.parse(objString);
	$TEST
	$('#'+obj.field).val(obj.remotefileid);
	$('#'+obj.field+"_link").html(obj.remotename);
}


CUSTOMFIELDJS2;
}
 


function dumpCustomFieldRows($client, $visitSheetOnly=false, $oneColumn=0, $hideEmptyNonBooleans=false) {
	$clientCustomFields = getClientCustomFields($client['clientid']);
	$standardFields = getCustomFields(true);
	$standardFields = displayOrderCustomFields($standardFields, 'custom');
	
	foreach($standardFields  as $key => $descr) { //  && ()
		if($visitSheetOnly && !$descr[3]) continue;
		$custValue = $clientCustomFields[$key];
		if($descr[2] == 'boolean') 
			customLabelRow($descr[0].':', '', ($custValue ? 'Yes' : 'No'), 'labelcell','sortableListCell', '', '', '', $oneColumn);
		else if(!$hideEmptyNonBooleans || $custValue) {
			if($descr[2] == 'oneline')
				customLabelRow($descr[0].':', '', $custValue, 'labelcell','sortableListCell', '', '', '', $oneColumn);
			else if($descr[2] == 'hyperlink')
				customLabelRow($descr[0].':', '', $custValue, 'labelcell','sortableListCell', '', '', '', $oneColumn);
			else if($descr[2] == 'file') {
				if(!($validRemoteFileId = is_numeric($custValue) && $custValue > 0 && $custValue == round($custValue))) {
					//$label = 'File missing: ['.truncatedLabel($custValue, 10).']';
					$fname = null;
				}
				else $fname = fetchRow0Col0("SELECT remotepath FROM tblremotefile WHERE remotefileid = '$custValue' LIMIT 1",1);
				if($fname) {
					$fname = basename($fname);
					$onclick= "openConsoleWindow(\"fileviewer\", \"client-file-view.php?id=$custValue\", 700, 700);";
					$custValue = fauxLink($fname, $onclick, 1, 'View this file');
					customLabelRow($descr[0].':', '', $custValue, 'labelcell','sortableListCell', '', '', 'raw', $oneColumn);
				}
			}
			else if($descr[2] == 'text') {
				if($custValue) $custValue = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $custValue));
				customLabelRow($descr[0].':', '', $custValue, 'labelcell','sortableListCell','','','raw', $oneColumn);
			}
		}
	}
}

function dumpPetCustomFieldRows($pet, $visitSheetOnly=false, $oneColumn=0, $hideEmptyNonBooleans=false, $props=null) {
	if($props) {
		$rowStyle = $props['rowstyle'];
	}
	$petCustomFields = getPetCustomFields($pet['petid']);
	$fields = getCustomFields($activeOnly=true, $visitSheetOnly, getPetCustomFieldNames());
	$fields = displayOrderCustomFields($fields, 'petcustom');
	foreach($fields as $key => $descr) {
		if(!$petCustomFields[$key] || ($visitSheetOnly && !$descr[3])) continue;
		if($descr[2] == 'boolean') 
			customLabelRow($descr[0].':', '', ($petCustomFields[$key] ? 'Yes' : 'No'), '','sortableListCell', '', $rowStyle, '', $oneColumn);
		else if(!$hideEmptyNonBooleans || $petCustomFields[$key]) {
			if($descr[2] == 'oneline')
				customLabelRow($descr[0].':', '', $petCustomFields[$key], '','sortableListCell', '', $rowStyle, '', $oneColumn);
			if($descr[2] == 'hyperlink')
				customLabelRow($descr[0].':', '', $petCustomFields[$key], '','sortableListCell', '', $rowStyle, '', $oneColumn);
			else if($descr[2] == 'file') {
				$remoteFileId = $petCustomFields[$key];
				if(!($validRemoteFileId = is_numeric($remoteFileId) && $remoteFileId > 0 && $remoteFileId == round($remoteFileId))) {
					//$label = 'File missing: ['.truncatedLabel($custValue, 10).']';
					$fname = null;
				}
				else $fname = fetchRow0Col0("SELECT remotepath FROM tblremotefile WHERE remotefileid = '$remoteFileId' LIMIT 1",1);
				if($fname) {
					$fname = basename($fname);
					$onclick= "openConsoleWindow(\"fileviewer\", \"client-file-view.php?id={$petCustomFields[$key]}\", 700, 700);";
					$custValue = fauxLink($fname, $onclick, 1, 'View this file');
					customLabelRow($descr[0].':', '', $custValue, '','sortableListCell', '', $rowStyle, 'raw', $oneColumn);
				}
			}
			else if($descr[2] == 'text')
				customLabelRow($descr[0].':', '', $petCustomFields[$key], '','sortableListCell','',$rowStyle,'raw', $oneColumn);
		}
	}
}

function customLabelRow($label, $name, $value, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $rawValue=null, $oneColumn=0) {
	//echo "LABEL: $label NAME: $name VALUE: $value";exit;
	if($oneColumn) oneByTwoLabelRows($label, $name, $value, $labelClass, $inputClass, $rowId, $rowStyle, $rawValue, $oneColumn);
	else labelRow($label, $name, $value, $labelClass, $inputClass, $rowId, $rowStyle, $rawValue, $oneColumn);
}

function preprocessCustomFields(&$client) {  // OBSELETE
	foreach(getCustomFields(true) as $key => $field) {
		if($field[2] == 'boolean')
    	$client[$key] = isset($client[$key]) && $client[$key] ? 1 : 0;
	}
}

function clientVisibleEnabledForDb() {
	global $db;  // for test constraint below only
	return $_SESSION['preferences']['enableClientVisibleCustomFields'] ;
		// turn this second condition off after Jan 20 2015
		//|| in_array($db, explode(',', 'tickledpaws,themonsterminders,bluedogpetcarema'));
}

function saveCustomFieldSpecs($prefix=null) { // prefix may be 'pet' or null
//if($prefix) {print_r($_POST);exit;}
	foreach($_POST as $k => $v) {
		if(strpos($k, 'custom') !== 0) continue;
		$i = 0+substr($k, strlen('custom'));
		$val = stripslashes($_POST["custom$i"]).'|'.(isset($_POST["active_$i"]) && $_POST["active_$i"] ? 1 : 0).'|'.
		        $_POST["type_$i"].'|'.(isset($_POST["visitsheet_$i"]) && $_POST["visitsheet_$i"] ? 1 : 0);
		        
		if(staffOnlyTEST() || clientVisibleEnabledForDb())
			$val .= '|'.(isset($_POST["clientvisible_$i"]) && $_POST["clientvisible_$i"] ? 1 : 0);
		if(trim($_POST["custom$i"])) setPreference($prefix."custom$i", $val);
		else setPreference($prefix."custom$i", null);
	}
}

function OLDsaveCustomFieldSpecs($prefix=null) { // prefix may be 'pet' or null
//if($prefix) {print_r($_POST);exit;}
	for($i=1; isset($_POST["custom$i"]) /*&& $_POST["custom$i"]*/; $i++) {
		$val = stripslashes($_POST["custom$i"]).'|'.(isset($_POST["active_$i"]) && $_POST["active_$i"] ? 1 : 0).'|'.
		        $_POST["type_$i"].'|'.(isset($_POST["visitsheet_$i"]) && $_POST["visitsheet_$i"] ? 1 : 0);
		        
		if($_SESSION['staffuser'] || clientVisibleEnabledForDb())
			$val .= '|'.(isset($_POST["clientvisible_$i"]) && $_POST["clientvisible_$i"] ? 1 : 0);
        
		if(trim($_POST["custom$i"])) setPreference($prefix."custom$i", $val);
		else setPreference($prefix."custom$i", null);
	}
}

function customFieldSpecEditor($fields=null, $prefix=null) {	
	$fields = $fields !== null ? $fields : getCustomFields();
//if(mattOnlyTEST()) print_r($fields);			
	
	//$fieldKeys = array_merge($fields);
	$nFields = array();
	$order = customFieldDisplayOrder($prefix."custom");
	foreach(array_keys($order) as $i => $key) {
		$nFields[$i+1] = $key;
		unset($fieldKeys[$key]);
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog("nFields:".print_r($nFields, 1)."<br>");}
//if($_SESSION['staffuser']) print_r($nFields);	
	foreach((array)$fieldKeys as $key => $field) $nFields[] = $key;
	
	echo "<table width='100%' class='sortableListCell'>";
	$clientVisible = staffOnlyTEST() || clientVisibleEnabledForDb() ? '<th>Client<br>Visible</th>' : '';
	echo "<tr><th>&nbsp;</th><th>Active</th><th>Label</th><th>Type</th><th>Visit<br>Sheets</th>$clientVisible</tr>";
	$used = array();
	for($n=1; $n<=count($fields)+5; $n++) {
		$fieldKey = $nFields[$n];
		if($fieldKey) $used[] = $fieldKey;
		else {
			for($u=1; in_array($prefix."custom$u", $used); $u++) ;
			$fieldKey = $prefix."custom$u";
			$used[] = $prefix."custom$u";
		}
		
		$i =  substr($fieldKey, strlen($prefix."custom"));
		echo "<tr><td>&nbsp;</td><td>"; /*Custom #".($i)*/
		labeledCheckbox('',"active_$i", $fields[$fieldKey][1]);
		echo "</td><td>";
		if(mattOnlyTEST()) echo "<span>{$prefix}custom$i</span>";
		labeledInput('', "custom$i", $fields[$fieldKey][0], null, 'input300');
		echo "</td><td>";
		$type = isset($fields[$fieldKey]) ? $fields[$fieldKey][2] : 'oneline';
		labeledRadioButton('One line', "type_$i", 'oneline', $type);
		labeledRadioButton('Multi-line', "type_$i", 'text', $type);
if(TRUE) echo "<br>"; // enableClientFilesFeatures enabled 12/6/2018
		labeledRadioButton('Checkbox', "type_$i", 'boolean', $type);
if(FALSE && mattOnlyTEST()) labeledRadioButton('Link', "type_$i", 'hyperlink', $type);
if(TRUE) labeledRadioButton('File', "type_$i", 'file', $type); // enableClientFilesFeatures enabled 12/6/2018
		echo "</td><td>";
		labeledCheckbox('',"visitsheet_$i", $fields[$fieldKey][3], 
										null, null, "updateCheckBoxToMatchCheckBox(clientvisible_$i, this, 0)");
		echo "</td>";
		if($clientVisible) {
//if(mattOnlyTEST() && $i == 34) print_r($fields[$fieldKey]);			
			echo "</td><td>";
			labeledCheckbox('',"clientvisible_$i", $fields[$fieldKey][4], 
										null, null, "updateCheckBoxToMatchCheckBox(visitsheet_$i, this, 1)");
			echo "</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
}

// Client Filter fns
function customFilterDescription($tests, $maxFields=null) { // array('custom1'=>array('action'=>yes|no|pat, 'value'=>pattern))
	$orderedFields = displayOrderCustomFields(getCustomFields(), '');
	$n = 0;
	foreach($tests as $fieldName =>$test) {
		$field = $orderedFields[$fieldName];
		$isBoolean = $field[2] == 'boolean';
		$desc[] = "{$field[0]}"
			.($test['action'] == 'yes' ? ($isBoolean ? " = yes" : " supplied") : (
				$test['action'] == 'any' ? ($isBoolean ? " = yes" : " supplied") : (
				$test['action'] == 'no' ? ($isBoolean ? " = no" : " not supplied") : (
				$test['action'] == 'pat' ? " matching [{$test['value']}]" : '??'))));
		$n += 1;
		if($maxFields && $n == $maxFields) break;
	}
	return $desc ? join(', ', $desc) : null;
}

function filterByCustomFields($tests, $clientids=null) {
	$orderedFields = displayOrderCustomFields(getCustomFields(), '');
	foreach($orderedFields as $fieldName => $field) 
		if($field[2] == 'boolean') $booleanFields[$fieldName] = 1;

	$clientidsTest = $clients ? "clientptr IN (".join(',', $clientids).") AND" : '';;
	foreach($tests as $fieldName =>$test) {
		if($test['action'] == 'no') {
			$notSuppliedFields[] = $fieldName;
			unset($tests[$fieldName]);
		}
	}
	
	foreach($tests as $fieldName =>$test) {
		if($test['action']== 'pat') {
			$pat = $test['value'];
			$patNot = '';
			if($pat[0] == '-' || $pat[0] == '^') {
				$pat = substr($pat, 1);
				$patNot = 'NOT';
			}
		}
		$positiveTest[] = "(fieldname = '$fieldName' AND "
							.($test['action']== 'pat' ? "value $patNot LIKE '%".leashtime_real_escape_string($pat)."%'" 
									: "value IS NOT NULL AND value != ''"
											.($booleanFields[$fieldName] ? " AND value != '0'" : ''))
							.")";
	}
	if($positiveTest) {
		$clientids = fetchCol0($sql =
				"SELECT DISTINCT clientptr 
					FROM relclientcustomfield 
					WHERE \n$clientidsTest\n".join("\nAND ", 	$positiveTest), 1);
		echo "<hr>$sql";
		if(!$clientids) return array();
		$clientidsTest = "clientptr IN (".join(',', $clientids).") AND";
	}
	if($notSuppliedFields) {
		$excludeTheseClients = fetchCol0($sql =
				"SELECT DISTINCT clientptr 
					FROM relclientcustomfield WHERE 
						$clientidsTest
							fieldname IN ('".join("','", $notSuppliedFields)."')
							AND value IS NOT NULL AND value != ''"
							.($booleanFields[$fieldName] ? " AND value != '0'" : ''), 1);
		
//echo "<hr>$sql";				
		if($clientids) $clientids = array_diff($clientids, $excludeTheseClients);
		else if($excludeTheseClients) {
			$clientids = fetchCol0($sql =
				"SELECT clientid 
					FROM tblclient 
					WHERE clientid NOT IN (".join(',', $excludeTheseClients).")", 1);
//echo "<hr>$sql";
		}
	}
	return $clientids;
}

// OBSELETE
function portCustomFields() {
	include "client-fns.php";
	foreach(fetchAssociations("SELECT * FROM tblclient") as $client) {
		$pairs = array();
		for($i=1;$i<=5;$i++)
			if($client["custom$i"])
				$pairs["custom$i"] = $client["custom$i"];
		
		saveClientCustomFields($client["clientid"], $pairs);
	}
}

/*
CREATE TABLE IF NOT EXISTS `relpetcustomfield` (
  `petptr` int(11) NOT NULL,
  `fieldname` varchar(10) NOT NULL COMMENT 'petcustomN',
  `value` text,
  PRIMARY KEY  (`petptr`,`fieldname`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
*/