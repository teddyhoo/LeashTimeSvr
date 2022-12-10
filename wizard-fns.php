<? //wizard-fns.php

function getSpec($wizardFile) {
	$spec = parse_ini_file($wizardFile, true);
	$lastSectionKey = null;
	foreach($spec as $k => $v) 
		if(is_array($v) && !$v['INACTIVE']) {
			if($lastSectionKey) {
				$spec[$lastSectionKey]['nextsection'] = $k;
				$spec[$k]['prevsection'] = $lastSectionKey;
			}
			$lastSectionKey = $k;
		}
	return $spec;
}
	
function getSection($wizardFile, $section) {
	$spec = getSpec($wizardFile); //parse_ini_file($wizardFile, true);
	return $spec[$section];
}

function display($wizardFile, $section, $source=null, $error=null) {
	global $initialHelp;
	$spec = getSpec($wizardFile); //parse_ini_file($wizardFile, true);
	if($error) $spec[$section]['error'] = $error;
	$helpHeight = $spec['initialhelp'] ? array($spec['initialhelp']) :  $spec['initialhelpheight'];
	$initialHelp = $spec['initialhelp'] 
									? $spec['initialhelp'] 
									: ($spec['initialhelpheight'] ? "<img src='art/spacer.gif' height={$spec['initialhelpheight']} width=1>" : '');
	$reversedSpecSectionKeys = array_reverse(array_keys($spec));
	if($section == $reversedSpecSectionKeys[0]) $_SESSION['wizardOnceThrough'] = 1;
	displaySection($spec[$section], $section, $source, $spec['initialhelpheight']);
	navBar($spec, $section);
	//print_r($spec);
}

function sectionTitles($wizardFile) {
	if(is_string($wizardFile)) $spec = getSpec($wizardFile); //parse_ini_file($spec, true);
	foreach($spec as $k => $v) if(is_array($v)) $sects[$k] = $v['title'];
	return $sects;
}

function getSections($wizardFile) {
	if(is_string($wizardFile)) $spec = getSpec($wizardFile); //parse_ini_file($spec, true);
	foreach($spec as $k => $v) if(is_array($v)) $sects[$k] = $v;
	return $sects;
}

function displaySection($section, $wizardpageid, $source=null, $initialHelpHeight=null) {
	global $lineHelp, $initialHelp;
	if($section['initialhelp']) $initialHelp = $section['initialhelp'];
	else if($section['initialhelpheight']) $initialHelp = "<img src='art/spacer.gif' height={$section['initialhelpheight']} width=1>";
	require_once "gui-fns.php";
	echo "\n".'<script type="text/javascript" src="jquery-1.7.1.min.js"></script>';
	echo "\n<div class='wizarddiv'>";
	if($section['title']) echo "<span class='wizardtitle'>{$section['title']}</span><hr>";
	if($section['error']) echo "<p><div class='wizarderror' id='wizarderror'>{$section['error']}</div>";
	if($section['blurb']) echo "<p><div class='wizardblurb' id='wizardblurb'>{$section['blurb']}</div>";
	echo "<form name='wizardform' method='POST'>";
	hiddenElement('wizardpageid', $wizardpageid);
	$source = $source ? $source : ($section["datasource"] == 'preferences' ? $_SESSION['preferences'] : '');
	//print_r($source);
	//print_r($section);
	$helpDivStyle = $section['helpdivstyle'] ? "style='{$section['helpdivstyle']}'" : '';
	echo "\n<div class='wizardhelpdiv' id='wizardhelpdiv' $helpDivStyle>$initialHelp</div>";
	echo "\n<table class='wizardtable'>";
	if($section['controlsontop']) navButtons($section);

	$fieldTypes = array();
	$lineHelp = array();
	for($i=1; isset($section["field_$i"]); $i++) {
		$label = $section["label_$i"];
		if($section["required_$i"]) {
			$requiredNote = "<font color=red>* required.</font>";
			$label = "$label <font color=red>*</font>";
		}
		$type = $section["type_$i"] ? $section["type_$i"] : 'text';
		$fieldTypes[] = $type;
		if($section["descr_$i"]) $lineHelp[$section["field_$i"]] = $section["descr_$i"];
		$inputClass = isset($section["cssclass_$i"]) ? $section["cssclass_$i"] : 'wizardtextinput';
		$inputValue = $section["nodisplay_$i"] ? '' : $source[$section["field_$i"]];
//if($i==1) {echo "<tr><td>".print_r($section, 1);}		
		if($type == 'text') inputRow($label, $section["field_$i"], $inputValue, $labelClass=null, $inputClass, $rowId=null,  $rowStyle=null, $onBlur=$section["onblur_$i"]);
		else if($type == 'password') passwordRow($label, $section["field_$i"], '', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null);
		else if($type == 'radio') 
			displayRadioButtons($label, $section["field_$i"], $section["options_$i"], $source[$section["field_$i"]], $section["onclick_$i"], $section["descr_$i"], $section["breakevery_$i"]);
		else if($type == 'checkbox') 
			displayCheckBoxes($label, $section["field_$i"], $section["options_$i"], $source[$section["field_$i"]], $section["onclick_$i"], $section["breakevery_$i"]);
		else if(strpos($type, 'picklist') === 0) 
			displayPickList($label, $section["field_$i"], $section["options_$i"], $source[$section["field_$i"]]);
		else if(strpos($type, 'list') === 0) {
			displayPickList($label, $section["field_$i"], $section["options_$i"], $source[$section["field_$i"]], 'simple');
		}
		else if($type == 'boolean') {
			$val = isset($source[$section["field_$i"]]) ? $source[$section["field_$i"]] : (
							isset($section["default_$i"]) ? $section["default_$i"] : 0);
			displayRadioButtons($label, $section["field_$i"], 'yes|1||no|0', ($val ? 1 : 0), $section["onclick_$i"], $section["descr_$i"]);
		}
		else if($type == 'percentage') 
			inputRow($label, $section["field_$i"], $inputValue, $labelClass=null, $inputClass, $rowId=null,  $rowStyle=null, $onBlur=$section["onblur_$i"]);
		else if($type == 'sortlist') 
			displaySortableListEditor($section["field_$i"], $source[$section["field_$i"]], $section["delim_$i"], $section["show_$i"]);
		else if($type == 'matrix') 
			displayMatrixEditor($section["field_$i"], $source, $section["columns_$i"], $section["show_$i"], $section["total_$i"], $section["firstindex_$i"]);
		else if($type == 'textarea') 
			displayTextArea($label, $section["field_$i"], $source[$section["field_$i"]], $section["rows_$i"], $section["cols_$i"]);
		else if($type == 'timewindow') 
			displayTimePicker($label, $section["field_$i"], $source[$section["field_$i"]]);
		else if($type == 'include') 
			includeField($section, $i, $source);
	}
	if($requiredNote) echo "<tr><td colspan=2>$requiredNote</td></tr>";

	if(!$section['controlsontop']) navButtons($section);

	echo "</table>";
	hiddenElement('destination', '');
	echo "</form>";
	echo "</div>";
	echo "
<script language='javascript'>
function goto(destination) {
	if(typeof tinymce !== 'undefined') { tinymce.triggerSave(); }
	
	document.getElementById('destination').value = destination;
	if((typeof prevalidate == 'function') && !prevalidate()) return; // to warn, but to interpose only at user preference
	if((typeof validate != 'function') || validate())
		document.wizardform.submit();
}
function done() {
	if(typeof tinymce !== 'undefined') { tinymce.triggerSave(); }
	if((typeof finalAct == 'function') && finalAct())
		return;
	if(parent) {
		if(parent.refresh) parent.refresh();
		parent.$.fn.colorbox.close();
	}
}
";
	if(in_array('sortlist', $fieldTypes))
	echo "		
		function addAnother2(el, num, property) {
	var blockdisplay = 'list-item';
  document.getElementById('buttspan'+property+num).style.display='none';
  document.getElementById('buttspan'+property+(num+1)).style.display='inline';
  document.getElementById('li_new'+(num+1)).style.display=blockdisplay;
  document.getElementById('li_new'+(num+1)).disabled=false;
  document.getElementById(property+'_visible').value=parseInt(document.getElementById(property+'_visible').value)+1;
}
";


	foreach($lineHelp as $k => $v) {
		echo "$('#$k').parent().parent().mouseover(
			function() {
				document.getElementById('wizardhelpdiv').innerHTML = '$v';
			}
		);\n";
		echo "$('#$k').parent().parent().mouseout(
			function() {
				document.getElementById('wizardhelpdiv').innerHTML = '$initialHelp';
		}
		);\n"; 
	}
	echo "
</script>
";	
}

function navButtons($section) { //controlsontop
	echo "<tr><td>";
	if($section["prevsection"]) echoButton('', '< Back', "goto(\"{$section["prevsection"]}\")");
	else echo '&nbsp;';
	echo "</td><td align='right'>";
	if($section["nextsection"]) echoButton('', 'Next >', "goto(\"{$section["nextsection"]}\")");
	else echoButton('', 'Done', 'done()');
	echo "</td></tr>";
}

function navBar($spec, $thisPage=null) {
	if(!$spec['navbar'] || ($spec['navbar'] == 'oncethrough' && !$_SESSION['wizardOnceThrough'])) return; 
	foreach($spec as $key => $section) {
		if(is_array($section)) {
			$n++;
			$id = strip_tags($key);
			$class = $thisPage == $key ? "class='current'" : '';
			$tds[] = "<td $class>".fauxLink($n, "goto(\"$id\")", 1, strip_tags($section['title']))."</td>";
		}
	}
	if($tds) 
		echo "Page: <style>.current {background:lightblue;} .pagetable {border-collapse:separate} .pagetable td {border:solid lightblue 2px;font-size:1.5em;padding-left:4px;padding-right:4px;}}</style><table cellspacing=8 class='pagetable' style='display:inline;'><tr>"
					.join('', $tds)
					."</tr></table>";
}

function displayTimePicker($label, $name, $val) {
	$val = $val ? $val : '12:00 pm-2:00 pm';
	$times = explode('-', $val);
	$start = explode(' ', $times[0]);
	$end = explode(' ', $times[1]);
	echo "<tr><td valign='top'>$label</td><td>";
	timeGroup($name, 'Starting', $start);
	echo "<p>";
	timeGroup($name, 'Ending', $end);
	hiddenElement($name, '');
	echo "</td></tr>";
}

function timeGroup($name, $part, $time) {
	$hoursMinutes = explode(':', $time[0]);
	for($i=1; $i <= 12; $i++) $hours[$i] = $i;
	$minutes = array('00'=>0,'15'=>15,'30'=>30,'45'=>45,'60'=>60,'59'=>59);
	$ampmOptiond = array('am'=>'am','pm'=>'pm');
	labeledSelect("$part: ", $part."H_$name", $hoursMinutes[0], $hours);
	echo " ";
	labeledSelect('', $part."M_$name", $hoursMinutes[1], $minutes);
	echo " ";
	labeledSelect('', $part."A_$name", $time[1], array('am'=>'am','pm'=>'pm'));
}

function displayTextArea($label, $name, $val, $rows=null, $cols=null) {
	$rows = $rows ? $rows : 3;
	$cols = $cols ? $cols : 20;
	textRow($label, $name, $val, $rows, $cols, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null);
}

function displayCheckBoxes($label, $name, $picklist, $value, $onClick=null,  $breakEveryN=null) {
	echo "<tr><td>$label</td><td>";
	$options = explodePairsLine($picklist);
	$selections = is_string($value) ? explode(',', $value) : $value;
//print_r($_SESSION['preferences']['ccAcceptedList']);echo "<p>";print_r($picklist);echo "<p>";print_r($options);echo "<p>";print_r($selections);	
	foreach($options as $boxlabel => $boxvalue) {
		$n++;
		$checked = in_array($boxvalue, (array)$selections);
		labeledCheckbox($boxlabel, "$name"."_$boxvalue", $checked, $labelClass=null, $inputClass=null, $onClick, $boxFirst=true, $noEcho=false);
		if($breakEveryN) {
			echo "<br>";
			$n = 1;
		}
	}
	echo "</td></tr>";
}

function displayRadioButtons($label, $name, $picklist, $value, $onClick=null, $help=null,  $breakEveryN=null) {
	global $lineHelp;
	$options = explodePairsLine($picklist);
	if($help) $lineHelp[$name.'_'.current($options)] = $help;
//echo "<tr><td>".print_r($help, 1);
	radioButtonRow($label, $name, $value, $options, $onClick, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN);
}

function displayPickList($label, $name, $picklist, $value, $simple=false) {
	$rawOptions = $simple ? explode(',', $picklist) : explodePairsLine($picklist);
	foreach($rawOptions as $optlabel => $opt) {
		if(!$simple) $options[$optlabel] = $opt;
		else $options[$opt] = $opt;
	}	
	echo "<tr><td>$label</td><td>";
  selectElement('', $name, $value, $options);
	echo "</td></tr>";
}

function displaySortableListEditor($prefix, $values, $delimiter=null, $show) {
	if(is_string($values)) 	$values = explode($delimiter, $values);
	hiddenElement($prefix, join($delimiter, (array)$values));
	echo "<tr><td>";
	echo "Drag and drop boxes to reorder the list.<br>Click once to edit a label.  (Use cursor keys rather than mouse while editing).<br>Double-click to select the whole label.<p>";
	$totalFields = max($show, count($values)+10);
	$visibleFields = max(count($values), $show);
	hiddenElement($prefix."_visible", $visibleFields);
	$itemList = array();
	foreach((array)$values as $val) {
		$elName = "pref_$prefix".count($itemList);
//if(mattOnlyTEST()) $itemList[$val] = "<img src='art/drag.gif' width=10 height=10> $val"; else 
		$itemList[$val] = "<img src='art/drag.gif' width=10 height=10> <input class='standardInput' id='$elName' name='$elName' value='$val' size=30 autocomplete='off'>";
  }
if(TRUE) require_once "dragsortJQ.php"; 
else require_once "dragsort.php";
	echo headerInsert('sortList');
	$extraLIs = array();
	for($i=count($itemList);$i<$totalFields;$i++) {
		$elName = "pref_$prefix$i";
//echo "i: $i visibleFields: $visibleFields (".($i <= $visibleFields-1)."), ";
		$extraLIs[] = extraLI($i, $elName, ($i <= $visibleFields-1));
	}
	echoSortList($itemList, 'sortList', $numbered=false, join("", $extraLIs));
	$showButton = true;
	for($i=$visibleFields;$i<$totalFields;$i++) {
		$elName = "pref_$prefix$i";
		$bStyle = $showButton ? "style='display:inline;'" : "style='display:none;'";
		$showButton = false;
		echo ($i < $totalFields-1)
			? "<span id= 'buttspan$prefix$i' $bStyle>".echoButton("addAnother_$prefix$i", "Add another", "addAnother2(this, $i, \"$prefix\")", '','',true)."<br></span>\n"
			: "<span id= 'buttspan$prefix$i' $bStyle>To add more, please Save Changes first and reopen this editor.<br></span>\n";
	}
	echo "</td></tr>";
}

function includeField($section, $fieldIndex, $source) {
	echo "<tr><td>";
	$fieldname = $section["field_{$fieldIndex}"];
	include $section["include_{$fieldIndex}"];
	echo "</td></tr>";
}
	
function displayMatrixEditor($prefix, $source, $columnsXML, $show, $totalNumber, $firstIndex=null) {
	// columns: array of key=>label
	// columnsXML: <cols><col key='key1' size='15'>label</col><col key='key1' size='25'>label</row>...</cols>
	// NOPE valuesXML: <top><row><col key='key1'>val</col><col key='key1'>val</col>...</row>...</top>
	echo "<tr><td><table><tr>";
	foreach(simplexml_load_string($columnsXML)->children() as $i => $xmlcol) {
		$attrs = (array)$xmlcol->attributes();
		$attrs = $attrs['@attributes'];
		$sizes[$attrs['key']] = $attrs['size'];
		$types[$attrs['key']] = $attrs['type'];
		$colKeys[] = $attrs['key'];
		echo "<th>".(string)$xmlcol."</th>";
	}
	echo "</tr>";
//echo 	"<tr><td>[$prefix] ".print_r($source, 1);
	$firstIndex = $firstIndex ? $firstIndex : 1;
	$lastIndex = $firstIndex + $totalNumber - 1;
	for($num = $firstIndex; $source["{$prefix}_{$num}_{$colKeys[0]}"]; $num++) {
		$rowId = "{$prefix}_{$num}";
//echo 	"<tr><td>[[{$rowId}_{$colKeys[0]}]]<br>";
		$next = $num+1;
		echo "<tr id='$rowId'>";
		foreach($colKeys as $key) {
			$size = $sizes[$key] ? "size={$sizes[$key]}" : '';
			if($types[$key] == 'check')
				echo "<td><input type='checkbox' id='{$rowId}_$key' name='{$rowId}_$key' $size ".($source["{$rowId}_{$key}"] ? 'CHECKED' : '')."></td>";
			else echo "<td><input id='{$rowId}_$key' name='{$rowId}_$key' $size value='".safeValue($source["{$rowId}_{$key}"])."'></td>";
		}
		echo "</tr>";
	}
//echo 	"<tr><td>show: $show num: $num totalNumber: $totalNumber lastIndex: $lastIndex";
	$show = min(max($show+$firstIndex-1,$num+1, 1), $lastIndex);
	
	for(; $num <= $lastIndex; $num++) {
		$rowId = "{$prefix}_{$num}";
		$next = $num+1;
		$hide = $num > $show ? 'display:none;' : '';
		$reveal = $next > $show && $num < $lastIndex ? 
			"onKeyUp='$(\"#{$prefix}_{$next}\").show()'" 
			//"onKeyUp='reveal(\"{$prefix}_{$next}\")'" 
			: '';
		echo "<tr id='{$prefix}_{$num}' style='$hide'>";
		foreach($colKeys as $key) {
			$size = $sizes[$key] ? "size={$sizes[$key]}" : '';
			if($types[$key] == 'check')
				echo "<td><input type='checkbox' id='{$rowId}_$key' name='{$rowId}_$key' $size></td>";
			else echo "<td><input id='{$rowId}_$key' name='{$rowId}_$key' $size value='' $reveal></td>";
			$reveal = '';
		}
		echo "</tr>";
	}
	echo "</table></td></tr>";
}

function extraLI($i, $elName, $visible=false) {
	$style = $visible ? 'display:;' : 'display:none;';
	$disabled = $visible ? '' : 'disabled=true';
	if(TRUE) return "<li class='ui-state-default' id='li_new$i' style='padding-top:0px;padding-bottom:0px;$style' onclick='focusClick(this);' ondblclick='selectClick(this);' $disabled><img src='art/drag.gif' width=10 height=10> <input class='standardInput' id='$elName' name='$elName' value='' size=30 autocomplete='off'></li>";
	else return "<li id='li_new$i' style='display:none;' ondblclick='selectClick(this);' onclick='focusClick(this);' disabled=true><img src='art/drag.gif' width=10 height=10> <input class='standardInput' id='$elName' name='$elName' value='' size=30 autocomplete='off'></li>\n";
}
