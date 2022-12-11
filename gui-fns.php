<?
// gui-fns.php
//********** TABLE GENERATION
function explodePairsLine($line) {
	foreach(explode('||', $line) as $piece) {
		$pair = explode('|', $piece);
		$pairs[$pair[0]] = $pair[1];
	}
	return $pairs;
}

function explodePairPerLine($lines, $sepr='|') {
	foreach(explode("\n", $lines) as $piece) {
		$pair = explode($sepr, trim($piece));
		$pairs[$pair[0]] = $pair[1];
	}
	return $pairs;
}

function noBreaks($str) {
	return str_replace(' ', '&nbsp;', $str);
}

function tableMockup($columnDataLine, $dataLines, $sortableCols, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $rowClasses=null, $colClasses=null) {
	// columnDataLine: id1|label1||id2|label2...
	$columns = explodePairsLine($columnDataLine);
	$colKeys = array_keys($columns);
	// dataLines: col1|col2|...
	foreach(explode("\n", $dataLines) as $line) {
		$row = array();
		foreach(explode('|', trim($line)) as $cell) {
		  $row[current($colKeys)] = $cell;
		  next($colKeys);
		}
		reset($colKeys);
		$data[] = $row;
	}
	// sortableCols: col1|col2|...
	$columnSorts = array();
	if($sortableCols) foreach(explode('|', trim($sortableCols)) as $col) $columnSorts[$col] = null;

	tableFrom($columns, $data, $attributes, $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses);
}

function quickTable($associations, $extra=null, $style=null, $repeatHeaders=0) {
	if(!$associations) {
		echo "No data";
		return;
	}
	$keys = array_keys($associations);
	echo "<table $extra>";
	$index = 0;
		foreach($associations as $ass) {
			if($index == 0 || ($repeatHeaders && ($index % $repeatHeaders == 0)))
				echo "<tr class='quicktableheaders'><td>".join('</td><td>', array_keys($associations[$keys[0]])). "</td></tr>\n";

			echo "<tr>";
			foreach($ass as $k => $v) echo "<td>$v</td>";
			echo "</tr>\n";
		$index++;
		}
	echo "</table>";
}

function csvTable($file, $extra=null, $style=null, $repeatHeaders=0) {
	if(!$file) {
		echo "No data";
		return;
	}
	$strm = fopen($file, 'r');
	echo "<table $extra>";
	$index = 0;
		while($row = guigetcsv($strm)) {
			//$row = explode(',', trim($row));
			if(!$headers) $headers = $row;
			if($index && ($repeatHeaders && ($index % $repeatHeaders == 0)))
				echo "<tr class='quicktableheaders'><td>".join('</td><td>', $headers). "</td></tr>\n";
			$class = !$index ? "class = 'quicktableheaders'" : '';
			echo "<tr $class>";
			foreach($row as $v) echo "<td>$v</td>";
			echo "</tr>\n";
		$index++;
		}
	echo "</table>";
}

function guigetcsv($strm) {  // handles EOLS inside quotes, as long as quotes balance
	global $delimiter;
	$quoteCount = 0;
	$totalCSV = array();
	do {
		$line = fgets($strm);
		for($i=0; $i < strlen($line); $i++) if($line[$i] == '"') $quoteCount++;
		$sstrm = fopen("data://text/plain,$line" , 'r');
		$csv = fgetcsv($sstrm, 0, $delimiter);
		if(!$totalCSV) $totalCSV = $csv;
		else {
			$totalCSV[count($totalCSV)-1] .= "\n".substr($csv[0], 0, strlen($csv[0])-1);
			for($i=1; $i < count($csv); $i++) $totalCSV[] = $csv[$i];
		}
	}
	while($quoteCount % 2 == 1);
	return $totalCSV;
}



function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	if(TRUE || mattOnlyTEST()) return NEWtableFrom($columns, $data, $attributes, $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses, $sortClickAction);
	return OLDtableFrom($columns, $data, $attributes, $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses, $sortClickAction);
}

function NEWtableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
  // $columns - columnKey => label
  // $data - array or query result set
  // $columnSorts - columnKey => null|asc|desc - the order to sort in when col is clicked and col was not the previous sort criterion
  //  $colClasses trumps $dataCellClass
  // $sortClickAction - JS function name which takes 'sortKey' and 'direction' (asc/desc) as args
  
  //$class = $class ? "class = $class" : '';
  // $headerClass may be a string, array, or null
  // if array (col=>class), header classes will be assigned individually, else all headers will share the class
  // if a col's class is null we will use 'sortableListHeader'
  
  global $maxTableRows;
    
  $headerClass = !$headerClass ? 'sortableListHeader' : (is_array($headerClass) ? $headerClass : $headerClass);
  
  $rawClass = $class;
  $class = $class ? "class = '$class'" : '';
  $headerRowClass = $headerRowClass ? "class = '$headerRowClass'" : '';
  $dataCellClass = $dataCellClass ? "class = '$dataCellClass'" : "class = 'sortableListCell'";
  $columnSorts = $columnSorts ? $columnSorts : array();
  echo "<table $attributes $class>\n";
  tableHeaderRow($columns, $columnSorts, $sortClickAction, $headerClass);
//echo "<tr><td colspan=8>>>>>".print_r($rowClasses, 1);	
  $rowNumber = 0;
  if($maxTableRows) $maxCountDown = $maxTableRows;
  if($data) {
		if(is_array($data)) reset($data);
		$row = is_array($data) ? current($data) : mysqli_fetch_array($data, MYSQL_ASSOC);
		$i = is_array($data) ? count($data) : mysqli_num_rows($data);
		while($i > 0) {
		// $data may be an array or a query result
			if($maxTableRows && !$maxCountDown) {
				$maxCountDown = $maxTableRows;
				// break the table
				echo "\n</table>\n";
				if(!$class || !strpos($class, 'breakbefore')) $class .= "class = 'breakbefore $rawClass'";
				echo "<table $attributes $class>\n";
				tableHeaderRow($columns, $columnSorts, $sortClickAction, $headerClass);
			}

			if($maxTableRows) $maxCountDown = $maxCountDown - 1;

			if(isset($row['#CUSTOM_ROW#'])) {
				echo $row['#CUSTOM_ROW#'];
			}
			else {
				$rowExtras = isset($row['#ROW_EXTRAS#']) ? $row['#ROW_EXTRAS#'] : '';
				$rowClass = $rowClasses && isset($rowClasses[$rowNumber]) ? "class='{$rowClasses[$rowNumber]}'" : '';
				echo "<tr $rowClass $rowExtras>\n";
		//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($colClasses); }    
				foreach($columns as $col => $unused) {
					$cellClass = $colClasses && isset($colClasses[$col]) ? "class='{$colClasses[$col]}'" : $dataCellClass;
					echo "<td $cellClass>".(!$row[$col] ? '&nbsp;' : $row[$col])."</td>";
				}
				echo "\n</tr>\n";
			}
			$rowNumber++;
			$row = is_array($data) ? next($data) : mysqli_fetch_array($data, MYSQL_ASSOC);
			$i -= 1;
		} // while
  } // if data
    echo "\n</table>\n";
}

function OLDtableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
  // $columns - columnKey => label
  // $columnSorts - columnKey => null|asc|desc - the order to sort in when col is clicked and col was not the previous sort criterion
  //  $colClasses trumps $dataCellClass
  // $sortClickAction - JS function name which takes 'sortKey' and 'direction' (asc/desc) as args
  
  //$class = $class ? "class = $class" : '';
  // $headerClass may be a string, array, or null
  // if array (col=>class), header classes will be assigned individually, else all headers will share the class
  // if a col's class is null we will use 'sortableListHeader'
  
  global $maxTableRows;
  $headerClass = !$headerClass ? 'sortableListHeader' : (is_array($headerClass) ? $headerClass : $headerClass);
  
  $rawClass = $class;
  $class = $class ? "class = '$class'" : '';
  $headerRowClass = $headerRowClass ? "class = '$headerRowClass'" : '';
  $dataCellClass = $dataCellClass ? "class = '$dataCellClass'" : "class = 'sortableListCell'";
  $columnSorts = $columnSorts ? $columnSorts : array();
  echo "<table $attributes $class>\n";
  tableHeaderRow($columns, $columnSorts, $sortClickAction, $headerClass);
//echo "<tr><td colspan=8>>>>>".print_r($rowClasses, 1);	
  $rowNumber = 0;
  if($maxTableRows) $maxCountDown = $maxTableRows;
  if($data) foreach($data as $row) {
	// $data may be an array or a query result
		if($maxTableRows && !$maxCountDown) {
			$maxCountDown = $maxTableRows;
			// break the table
			echo "\n</table>\n";
			if(!$class || !strpos($class, 'breakbefore')) $class .= "class = 'breakbefore $rawClass'";
  		echo "<table $attributes $class>\n";
			tableHeaderRow($columns, $columnSorts, $sortClickAction, $headerClass);
		}
		
		if($maxTableRows) $maxCountDown = $maxCountDown - 1;
		
		if(isset($row['#CUSTOM_ROW#'])) {
			echo $row['#CUSTOM_ROW#'];
      $rowNumber++;
			continue;
		}
		
		$rowExtras = isset($row['#ROW_EXTRAS#']) ? $row['#ROW_EXTRAS#'] : '';
    $rowClass = $rowClasses && isset($rowClasses[$rowNumber]) ? "class='{$rowClasses[$rowNumber]}'" : '';
    $rowNumber++;
    echo "<tr $rowClass $rowExtras>\n";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($colClasses); }    
    foreach($columns as $col => $unused) {
			$cellClass = $colClasses && isset($colClasses[$col]) ? "class='{$colClasses[$col]}'" : $dataCellClass;
      echo "<td $cellClass>".(!$row[$col] ? '&nbsp;' : $row[$col])."</td>";
		}
    echo "\n</tr>\n";
  }
    echo "\n\n</tbody></table>\n";
}

function tableHeaderRow($columns, $columnSorts, $sortClickAction, $headerClass) {
  echo "<thead><tr $headerRowClass>\n";
  if($columnSorts)
		$sortReadyURL = thisURLMinusThisParam(null, 'sort');
  if($columns) foreach($columns as $key => $label) {
    $headerLink = $label;
    if(array_key_exists($key, $columnSorts)) {   // IF SORTABLE
			$current_sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : '';
			if($current_sort) {   // IF SORT HAS BEEN SPECIFIED IN REQUEST
				$current_sort = explode("_", $current_sort);
			  if($current_sort[0] == $key) {  // IF THIS IS THE CURRENT SORT CRITERION
			    $sort_dir = $current_sort[1] == 'desc' ? 'asc' : 'desc';
			    $sortWidget = $sort_dir == 'asc' ? 'art/sort_down.gif' : 'art/sort_up.gif';
			    $sortWidget = "<img src='$sortWidget' width=10 height=10 border=0>";
				}
				else {
				  $sortWidget = '';
			    $sort_dir = $columnSorts[$key];
				}
			}
			else {
				$sortWidget = '';
			  $sort_dir = $columnSorts[$key];
			}
			if(!$sortClickAction) $headerLink = "<a href='$sortReadyURL"."sort=$key"."_$sort_dir'>$label $sortWidget</a>";
			else $headerLink = "<a class='fauxlink' onClick='$sortClickAction(\"$key\",\"$sort_dir\")'>$label $sortWidget</a>";
		}
		$classString = is_array($headerClass) ? (isset($headerClass[$key]) ? $headerClass[$key] : 'sortableListHeader') : $headerClass;
		$classString = "class='$classString'";
    echo "<th $classString>$headerLink</th>";
	}
  echo "</tr></thead><tbody>\n";
}	

function thisURLMinusSortParam($url=null) {
  $url = $url ? $url : $_SERVER["REQUEST_URI"];
  $parts = explode("?", $url);
  $firstPart = current($parts);
  //$firstPart = $firstPart[0] == "/" ? substr($firstPart, 1) : $firstPart;
  next($parts);
  $parts = current($parts) ?  explode("&", current($parts)) : array();
  $remainingParts = array();
  foreach($parts as $part)
    if(strpos($part, "sort=") !== 0)
      $remainingParts[] = $part;
  return $firstPart.(!$remainingParts ? '?' : ('?'.join("&", $remainingParts)."&"));
}

function thisURLMinusThisParam($url=null, $param) {
	return thisURLMinusParams($url, array($param));
}

function thisURLMinusParams($url=null, $params) {
  $url = $url ? $url : $_SERVER["REQUEST_URI"];
  if(strpos('?&', substr($url, -1)) !== false) $url = substr($url, 0, -1);
  $parts = explode("?", $url);
  $firstPart = current($parts);
  //$firstPart = $firstPart[0] == "/" ? substr($firstPart, 1) : $firstPart;
  next($parts);
  $parts = current($parts) ?  explode("&", current($parts)) : array();
  $remainingParts = array();
  foreach($parts as $part) {
		$includeIt = true;
    foreach($params as $param)
      if(strpos($part, "$param=") === 0) $includeIt = false;
    if($includeIt) $remainingParts[] = $part;
	}
  return $firstPart.(!$remainingParts ? '?' : ('?'.join("&", $remainingParts)."&"));
}

/* Form building functions */

function echoButton($id, $label, $onClick='', $class='', $downClass='', $noEcho=false, $title=null) {
	$class = $class ? $class : 'Button';
	$downClass = $downClass ? $downClass : 'ButtonDown';
	$onClick = $onClick ? "onClick='$onClick'" : '';
	$title = $title ? "title='$title'" : '';
	$label = safeValue($label);
	if($noEcho)
	  return "<input type='button' id='$id' name='$id' value='$label' class='$class' $onClick $title
	           onMouseOver='this.className=\"$downClass\"' onMouseOut='this.className=\"$class\"'>";
	echo "<input type='button' id='$id' name='$id' value='$label' class='$class' $onClick $title
						onMouseOver='this.className=\"$downClass\"' onMouseOut='this.className=\"$class\"'>";
}

function inputButton($label, $onClick=null, $name=null, $id=null, $style=null) {
	$onClick = $onClick ? "onClick='$onClick'" : '';
	$name = $name ? "name='$name'" : '';
	$id = $id ? "id='$id'" : '';
	$style = $style ? "style='$style'" : 'class="defaultButton"';
	$label = safeValue($label);
	echo "<input type=button value='$label' $name $id $onClick $style>";
}

function inputLine($label, $name) {
	echo "<b>$label</b> <input name='$name'><br>";
}

function hiddenElement($name, $value=null, $inputClass=null, $noEcho=false) {
	$value = safeValue($value);
	$inputClass = $inputClass ? "class='$inputClass'" : '';
	$s = "<input type='hidden' id='$name' name='$name' value='$value' $inputClass>";
	if(!$noEcho) echo $s;
	else return $s;
}

function radioButtonRow($label, $name, $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null, $nonBreakingSpaceLabels=true, $extraContent=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : '';
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onClick = $onClick ? "onClick='$onClick'" : '';
	echo "<tr $rowId $rowStyle>\n  <td $labelClass>$label</td><td $inputClass>";
	$col = 0;
	foreach($options as $cbLabel => $cbValue) {
		$checked = $cbValue == $value ? 'CHECKED' : '';
		$cbValue = safeValue($cbValue);
		$cbLabel = $nonBreakingSpaceLabels ? str_replace(' ', '&nbsp;', $cbLabel) : $cbLabel;
		echo "\n\t<input type='radio' $onClick name='$name' id='$name"."_$cbValue' value='$cbValue' $checked> <label for='$name"."_$cbValue'>$cbLabel</label> ";
		if($breakEveryN) {
			$col++;
			if($col == $breakEveryN) {
				$col = 0;
				echo "<br>";
			}
		}
	}
	echo "$extraContent</td></tr>\n";
}

function radioButtonSet($name, $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null, $rawLabel=false) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : '';
	$onClick = $onClick ? "onClick='$onClick'" : '';
	foreach($options as $cbLabel => $cbValue) {
		$checked = $cbValue == $value ? 'CHECKED' : '';
		$cbValue = safeValue($cbValue);
		if(!$rawLabel) $cbLabel = str_replace(' ', '&nbsp;', $cbLabel);
		$radios[] = "<input type='radio' $onClick name='$name' id='$name"."_$cbValue' value='$cbValue' $checked> <label for='$name"."_$cbValue'>$cbLabel</label> ";
	}
	return $radios;
}

function safeValue($v) { // return a string value without single quotes (for inclusion as a single-quoted value in INPUT elements)
	// return a string safe to use as an element's value
	//return !$v ? $v : htmlentities("$v", ENT_QUOTES);
	return !$v ? $v : str_replace("'", "&apos;", (string)$v);
	//return addslashes($v);
}

function fullname($obj) { //for client or provider
  return !$obj ? '' : safeValue("{$obj['fname']} {$obj['lname']}");
}

function truncatedLabel($str, $length) {
	if(strlen($str) <= $length) return $str;
	return substr($str, 0, $length-3).'...';
}

function selectRow($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onChange = $onChange ? "onChange='$onChange'" : '';
	echo "<tr $rowId $rowStyle>\n  <td $labelClass><label for='$name'>$label</label></td><td $inputClass>";
	echo "<select name='$name' id='$name' $onChange $inputClass>";
	if(is_string($options)) echo "\n$options\n";
	else if($options) foreach($options as $optLabel => $optValue) {
		if($optValue && is_array($optValue)) echo optionGroup($optLabel, $optValue, $value);
		else if(is_array($optValue)) /* no-op */;
		else {
			$checked = $optValue == $value ? 'SELECTED' : '';

			//echo "\n\t<option value='$optValue' $checked>"."[$optValue == $value] = [".($optValue == $value)."]$optLabel</option>\n";
			echo "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
		}
	}
	echo "\n</select></td>$extraTDs</tr>\n";
}
	
function selectElement($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null) {
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$onChange = $onChange ? "onChange='$onChange'" : '';
	$title = $title ? "title='$title'" : '';
	$out = '';
	if($label) $out .= "<label $labelClass for='$name'>$label</label> ";
	$out .= "<select name='$name' id='$name' $onChange $inputClass $title>";
	if(is_string($options)) 		$out .= "\n$options\n";
	else if($options) foreach($options as $optLabel => $optValue) {
		if($optValue && is_array($optValue)) $out .= optionGroup($optLabel, $optValue, $value);
		else if(is_array($optValue)) /* no-op */;
		else {
			$checked = $optValue == $value ? 'SELECTED' : '';
			$optValue = safeValue($optValue);
			$out .= "\n\t<option value='$optValue' $checked {$optExtras[$optLabel]}>$optLabel</option>\n";
		}
	}
	$out .= "\n</select>\n";
	if($noEcho) return $out;
	else echo $out;
}

function optionGroup($label, $options, $value) {
	$s = "<optgroup label=\"$label\">";
	foreach($options as $optLabel => $optValue) {
		if($optValue && is_array($optValue)) $out .= optionGroup($optLabel, $optValue, $value);
		else {
			$checked = 'X'.$optValue == 'X'.$value ? 'SELECTED' : '';  // This is ludicrous, but '0' was equating to "O_11"
			$optValue = safeValue($optValue);
			$s .= "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
		}
	}
	return $s."</optgroup>\n";
}
		
	
function labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	//$value = $rawValue ? $value : safeValue($value);
	require_once "field-utils.php";	
	//$value = cleanseString($value);
	//if($inputClass) $value = "<span $inputClass>$value</span>";
	$labelEl = $name ? "<label for='$name'>$label</label>" : $label;
	echo "<tr $rowId $rowStyle>
  <td $labelClass>$labelEl</td><td id='$name'>$value</td></tr>\n";
}

function oneByTwoLabelRows($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$value = $rawValue ? $value : safeValue($value);
	require_once "field-utils.php";	
	$value = cleanseString($value);
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td></tr>
  <tr $rowId $rowStyle><td id='$name' $inputClass>$value</td></tr>\n";
}

	
function inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null) {
	global $allowAutoCompleteOnce, $allowAutoCompleteForScript;
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$value = safeValue($value);
	$autoCompleteOff = "autocomplete='off'";
	if($allowAutoCompleteOnce || $allowAutoCompleteForScript) {
		$autoCompleteOff = '';
		$allowAutoCompleteOnce = false;
	}
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td>
  <td>$inputCellPrepend<input $inputClass id='$name' name='$name' value='$value' $onBlur $autoCompleteOff>$extraContent</td></tr>\n";
}


function inputTextBoxRow($label, $name, $rows, $cols, $maxlength=null, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null) {
	global $allowAutoCompleteOnce, $allowAutoCompleteForScript;
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$maxlength = $maxlength ? "maxlength = $maxlength" : '';
	$value = safeValue($value);
	$autoCompleteOff = "autocomplete='off'";
	if($allowAutoCompleteOnce || $allowAutoCompleteForScript) {
		$autoCompleteOff = '';
		$allowAutoCompleteOnce = false;
	}
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td>
  <td>$inputCellPrepend
  <textarea $inputClass rows=$rows cols=$cols id='$name' name='$name' $maxlength $onBlur $autoCompleteOff>$value</textarea>
  $extraContent</td></tr>\n";
}



function inputTwoRows($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null) {
	global $allowAutoCompleteOnce, $allowAutoCompleteForScript;
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$value = safeValue($value);
	$autoCompleteOff = "autocomplete='off'";
	if($allowAutoCompleteOnce || $allowAutoCompleteForScript) {
		$autoCompleteOff = '';
		$allowAutoCompleteOnce = false;
	}
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td></tr><tr $rowId $rowStyle>
  <td>$inputCellPrepend<input $inputClass id='$name' name='$name' value='$value' $onBlur $autoCompleteOff>$extraContent</td></tr>\n";
}

function currencyRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $currencyMark=null) {
	$currencyMark = $currencyMark ? $currencyMark : getCurrencyMark();
	$prependClass = $labelClass ? "class='$labelClass'" : '';
	$inputCellPrepend = "<span $prependClass>$currencyMark </span>";
	return inputRow($label, $name, $value, $labelClass, $inputClass, $rowId,  $rowStyle, $onBlur, $extraContent, $inputCellPrepend);
}

function countdownInputRow($maxLength, $label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $position='afterinput') {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$value = safeValue($value);
	$countdownId = "countdown_$name";
	$onKeyUp = "onkeyup='document.getElementById(\"$countdownId\").innerHTML = ($maxLength - this.value.length)+\" chars left\"'";
	$span = "<span id=\"$countdownId\" style='color:green;'></span>";
	$afterInput = $position == 'afterinput' ? "&nbsp;$span" : '';
	$afterLabel = $position == 'afterlabel' ? "&nbsp;$span" : '';
	$underInput = $position == 'underinput' ? "<br>$span" : '';
	$underLabel = $position == 'underlabel' ? "<br>$span" : '';
	echo "<tr $rowId $rowStyle>"
  ."<td $labelClass><label for='$name'>$label</label>$afterLabel$underLabel</td><td><input maxlength=$maxLength $inputClass id='$name' name='$name' value='$value' $onBlur $onKeyUp autocomplete='off'>"
  ."$afterInput$underInput</td></tr>\n";
}

function countdownInput($maxLength, $name, $value=null, $inputClass=null, $onBlur=null, $position='afterinput') {
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$value = safeValue($value);
	$countdownId = "countdown_$name";
	$onKeyUp = "onkeyup='document.getElementById(\"$countdownId\").innerHTML = ($maxLength - this.value.length)+\" chars left\"'";
	$span = "<span id=\"$countdownId\" style='color:green;'></span>";
	$afterInput = $position == 'afterinput' ? "&nbsp;$span" : '';
	$afterLabel = $position == 'afterlabel' ? "&nbsp;$span" : '';
	$underInput = $position == 'underinput' ? "<br>$span" : '';
	$underLabel = $position == 'underlabel' ? "<br>$span" : '';
	echo "<input maxlength=$maxLength $inputClass id='$name' name='$name' value='$value' $onBlur $onKeyUp autocomplete='off'>"
  ."$afterInput$underInput\n";
}

function passwordRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$value = safeValue($value);
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$name'>$label</label></td><td><input $inputClass type='password' id='$name' name='$name' value='$value' $onBlur  autocomplete='off'></td></tr>\n";
}

function labeledSelect($label, $name, $value=null, $options=null, $labelClass=null, $inputClass=null, $onChange=null, $noEcho=false) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$onChange = $onChange ? "onChange='$onChange'" : '';
	if($noEcho) {
		ob_start();
		ob_implicit_flush(0);
	}
	echo "\n<label for='$name' $labelClass>$label</label>";
	echo "<select name='$name' id='$name' $onChange $inputClass>";
	if($options) {
		if(is_string($options)) 		$out .= "\n$options\n";
		else if($options) foreach($options as $optLabel => $optValue) {
			if($optValue && is_array($optValue)) echo optionGroup($optLabel, $optValue, $value);
			else if(is_array($optValue)) /* no-op */;
			else {
				$checked = $optValue == $value ? 'SELECTED' : '';
				echo "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
			}
		}
	}
	echo "\n</select>\n";
	if($noEcho) {
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
}

function labeledInput($label, $name, $value=null, $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=null, $noEcho=false) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$maxlength = $maxlength ? "maxlength='$maxlength'" : '';
	$value = safeValue($value);
	if($noEcho) {
		ob_start();
		ob_implicit_flush(0);
	}	
	echo "<label $labelClass for='$name'>$label</label> <input $inputClass id='$name' name='$name' value='$value' $onBlur $maxlength  autocomplete='off'>\n";
	if($noEcho) {
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
}

function labeledPassword($label, $name, $value=null, $labelClass=null, $inputClass=null, $onBlur=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$value = safeValue($value);
	echo "<label $labelClass for='$name'>$label</label> <input type='password' $inputClass id='$name' name='$name' value='$value' $onBlur>\n";
}

function labeledCheckbox($label, $name, $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$onBlur = $onClick ? "onClick='$onClick'" : '';
	$checked = $value ? 'CHECKED' : '';
	if($noEcho) {
		ob_start();
		ob_implicit_flush(0);
	}
	$title = $title ? "title = \"$title\"" : '';
	if($boxFirst) echo "<input type='checkbox' $inputClass id='$name' name='$name' $checked $onBlur> <label $labelClass for='$name' $title>$label</label>\n";
	else echo "<label $labelClass for='$name' $title>$label</label> <input type='checkbox' $inputClass id='$name' name='$name' $checked $onBlur>\n";
	if($noEcho) {
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
}

function labeledCheckboxWithId($label, $name, $id, $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$onBlur = $onClick ? "onClick='$onClick'" : '';
	$checked = $value ? 'CHECKED' : '';
	if($noEcho) {
		ob_start();
		ob_implicit_flush(0);
	}
	$title = $title ? "title = \"$title\"" : '';
	if($boxFirst) echo "<input type='checkbox' $inputClass id='$id' name='$name' $checked $onBlur> <label $labelClass for='$id' $title>$label</label>\n";
	else echo "<label $labelClass for='$id' $title>$label</label> <input type='checkbox' $inputClass id='$id' name='$name' $checked $onBlur>\n";
	if($noEcho) {
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
}

function helpButton($title='', $onClick='') {
	$title = $title ? "title=\"$title\"" : '';
	$onClick = $onClick ? "onClick=\"$onClick\"" : '';
	echo "<img src=\"art/help.jpg\" height=22 width=22 $title $onClick>";
}

function labeledRadioButton($label, $name, $value=null, $selectedValue=null, $onClick=null, $labelClass=null, $inputClass=null, $labelFirst=null) {
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : '';
	$onClick = $onClick ? "onClick='$onClick'" : '';
	$checked = $selectedValue == $value ? 'CHECKED' : '';
	$value = safeValue($value);
	if($labelFirst) echo "\n\t<label $labelClass for='$name"."_$value'>$label</label> <input type='radio' $onClick name='$name' id='$name"."_$value' value='$value' $checked $inputClass> ";
	else echo "\n\t<input type='radio' $onClick name='$name' id='$name"."_$value' value='$value' $checked $inputClass> <label $labelClass for='$name"."_$value'>$label</label> ";
}

function checkboxRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null, $boxFirst=false) {
  // DON'T USE ONCHANGE FOR IE6
	$rowClass = $rowClass ? "class='$rowClass'" : '';
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onChange = $onChange ? "onChange='$onChange'" : '';
	$checked = $value ? 'CHECKED' : '';
	if($boxFirst) echo "<tr $rowId $rowStyle $rowClass>
  <td><input type='checkbox' $inputClass id='$name' name='$name' $checked $onChange>$extraContent</td><td $labelClass><label for='$name'>$label</label></td></tr>\n";
	else echo "<tr $rowId $rowStyle $rowClass>
  <td $labelClass><label for='$name'>$label</label></td><td><input type='checkbox' $inputClass id='$name' name='$name' $checked $onChange>$extraContent</td></tr>\n";
}

$phoneRowHints = array('Click here to designate this number to receive Text Messages.', 'Click here to disallow Text Messages to this number.');
function phoneRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $groupname=null) {
	global $phoneRowHints;
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass phone'" : "class='standardInput phone'";
	$rowId = $rowId ? "id='$rowId'" : '';
	$rowStyle = $rowStyle ? "style='$rowStyle'" : '';
	$onBlur = $onBlur ? "onBlur='$onBlur'" : '';
	$groupname = $groupname ? $groupname : 'primaryphone';
	$selected = strpos($value, '*') === 0 ? 'CHECKED' : '';
	$textMessageEnabled = textMessageEnabled($value) ? 1 : 0;
	$value = strippedPhoneNumber($value); //strpos($value, '*') === 0 ? substr($value, 1) : $value;
	$value = safeValue($value);
	$smsButton = $textMessageEnabled ? 'SMS-yes.gif' : 'SMS-no.gif';
	$smsButtonTitle = $phoneRowHints[$textMessageEnabled];
	$smsToggle = "<img id='smsimg_$groupname"."_$name' height=15 width=15 src='art/$smsButton' style='cursor:pointer;' onClick='selectTextMessageTarget(\"$name\", \"$groupname\")' title=\"$smsButtonTitle\">";
	hiddenElement("sms_$groupname"."_$name", $textMessageEnabled);
	echo "<tr $rowId $rowStyle>
  <td $labelClass><label for='$groupname"."_$name'>$label</label></td>
  <td><input type='radio' name='$groupname' id='$groupname"."_$name' value='$name' $selected>$smsToggle&nbsp;<input $inputClass id='$name' name='$name' value='$value' $onBlur autocomplete='off'></td></tr>\n";
}

function dumpPhoneRowJS() {
	global $phoneRowHints, $phoneNumbersDigitsOnly;
	if($phoneNumbersDigitsOnly || $_SESSION['preferences']['phoneNumbersDigitsOnly'])
		$phoneFieldRestriction = <<<RESTRICT
$('.phone').keypress(function(e) {
	var keyCode = (e.keyCode ? e.keyCode : e.which);
	var allowed = {8:1, 9:1, 13:1};
	var ctrls ={97:1, 118:1, 120:1, 121:1, 122:1};
	//alert(keyCode+': '+(typeof allowed[keyCode] == 'undefined'));
	if(!((keyCode > 47 && keyCode) < 58 || 
				typeof allowed[keyCode] != 'undefined' 
				|| (e.ctrlKey && typeof ctrls[keyCode] != 'undefined'))) {
		e.preventDefault();
	}
});
RESTRICT;
	echo <<<JS
function selectTextMessageTarget(phoneName, groupname) {
	var imgel = document.getElementById('smsimg_'+groupname+'_'+phoneName);
	var hiddenElement = document.getElementById('sms_'+groupname+'_'+phoneName);
	var oldstate = hiddenElement.value == 1 ? '1' : '0';
	hiddenElement.value = oldstate == '1' ? '0' : '1';
	imgel.src = oldstate == '0' ?  'art/SMS-yes.gif' : 'art/SMS-no.gif';
	imgel.title = oldstate == '0' ?  '{$phoneRowHints[1]}' : '{$phoneRowHints[0]}';
	/*if(oldstate == '0') {
		var allInputs = document.getElementsByTagName('input');
		for(var i=0;i<allInputs.length;i++) // turn off others if this one was just turned on
			if(allInputs[i].id && allInputs[i].id.indexOf('sms_'+groupname+'_') == 0 && allInputs[i] != hiddenElement) {
				allInputs[i].value = 0;
			}
		for(var i=0;i<document.images.length;i++) {// turn off others if this one was just turned on
			var im = document.images[i];
			if(im.id && im.id.indexOf('smsimg_'+groupname+'_') == 0 && im != imgel) {
				im.src = 'art/SMS-no.gif';
				im.title = '{$phoneRowHints[0]}';
			}
		}
	}
	*/
}

$phoneFieldRestriction
JS;
}

function textRow($label, $name, $value=null, $rows=3, $cols=20, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2) {
	$rowClass = $rowClass ? "class='$rowClass'" : '';
	$labelClass = $labelClass ? "class='$labelClass'" : '';
	$inputClass = $inputClass ? "class='$inputClass'" : "class='standardInput'";
	$rowId = $rowId ? "id=$rowId" : '';
	$rowStyle = $rowStyle ? "style=$rowStyle" : '';
	$maxlength = $maxlength ? "maxlength = $maxlength" : '';
	//$value = safeValue($value);
	echo "<tr $rowId $rowStyle $rowClass>
	<td colspan=$textColSpan><label $labelClass for='$name'>$label</label><br><textarea $inputClass rows=$rows cols=$cols id='$name' name='$name' $maxlength>$value</textarea></td></tr>\n";
}

function textDisplayRow($label, $name, $value=null, $emptyTextDisplay=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $convertEOLs=true, $rowClass=null) {
	if(!$value && $emptyTextDisplay) $value = $emptyTextDisplay;
	if(!$value) return;
	$rowClass = $rowClass ? "class=$rowClass" : '';
	$labelClass = $labelClass ? "class=$labelClass" : '';
	$inputClass = $inputClass ? "class=$inputClass" : '';
	$rowId = $rowId ? "id=$rowId" : '';
	$rowStyle = $rowStyle ? "id=$rowStyle" : '';
	if($convertEOLs) $value = str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r", "", $value)));
	echo "<tr $rowId $rowClass $rowStyle>
	<td colspan=2><label $labelClass for='$name'>$label</label><div $inputClass>$value</div></td></tr>\n";
}

function selectLine($label, $name, $values) {
	echo "<b>$label</b> <select name='$name'>";
	foreach($values as $label => $value) {
	  $value = safeValue($value);
		echo "<option value='$value'>$label</option>\n";
	}
	echo "</select><br>";
}

function valueFrom($key, $arr1, $arr2, $default=null) {
	if(isset($arr1[$key])) return $arr1[$key];
	else if(isset($arr2[$key])) return $arr2[$key];
	return $default;
}

function makeEmailLink($email, $label=null, $nullCase = null, $length=null) {
	// for now, do a mailto.  In future, open a custom system email composer
	if(!$email) return $nullCase;
	if($length) $label = truncatedLabel($label, $length);
	return "<a href='mailto:$email'>".($label ? $label : $email)."</a>";
}

function caseInsensitiveComparison($a, $b) {
	return strcmp(strtoupper($a), strtoupper($b));
}

function htmlFormattedAddress($addr) {
	// order = street1,street2,city,state,zip
	$s = current($addr) ? current($addr).'<br>' : ''; // street1
	next($addr);
	$s.= current($addr) ? current($addr).'<br>' : ''; // street2
	next($addr);
	$v = current($addr); // city
	next($addr);
	if(current($addr)) {
		$s.= $v ? "$v, ".current($addr) : current($addr);
		$v = 1;
	}
	else $s.= $v;
}
	next($addr);
	if(current($addr)) {
		if($v) $s.= ' ';
		$s.= current($addr);
	}
//if($test) {$s .= "<hr>$test";
	return $s;
}

function oneLineAddress($addr) {
	// order = street1,street2,city,state,zip
	$s = current($addr) ? current($addr).', ' : ''; // street1
	next($addr);
	$s.= current($addr) ? current($addr).', ' : ''; // street2
	next($addr);
	$v = current($addr); 
	next($addr);
	if(current($addr)) {
		$s.= $v ? "$v, ".current($addr) : current($addr);
		$v = 1;
	}
	else $s.= $v;
	next($addr);
	if(current($addr)) {
		if($v) $s.= ' ';
		$s.= current($addr);
	}
	return $s;
}

function dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp='&nbsp;') {
	//return is_numeric($amount) ? "$".sprintf("%01.2f", $amount) : ($amount == null ? $nullRepresentation : $amount);
	//static $currencyMark;
	//if(!$currencyMark) $currencyMark = getCurrencyMark();
	$currencyMark = getCurrencyMark(); // getCurrencyMark() caches currencyMark and watches for country changes
	//$currencyMark = '$';
	$precision = $cents ? 2 : 0;
	return is_numeric($amount) 
		? "$currencyMark$nbsp".number_format((abs($amount) < .01 ? 0 : $amount), $precision, '.', ',') : (
		$amount == null ? $nullRepresentation : $amount);
}

function displayDateTime($datestr) {
	return shortDateAndTime(strtotime($datestr));
}

function displayDate($datestr) {
	return longerDayAndDate(strtotime($datestr));
}

function abbreviatedDisplayDate($datestr) {
	$time = $datestr ? strtotime($datestr) : time();
	return shortNaturalDate($time).' '.date('D', $time);
}

function getServiceName($servicecode) {
	if(isset($_SESSION) && $_SESSION)
		return $_SESSION['allservicenames'][$servicecode];
}

function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) {
	$title = $title ? "title='$title'" : '';
	$onClick = $onClick ? "onClick='$onClick'" : '';
	$class = $class ? $class : 'fauxlink';
	$style = $style ? "style='$style'" : '';
	$id = $id ? "id='$id'" : '';
	if($noEcho) return "<a class='$class' $onClick $title $id $style>$label</a>";
	echo "<a class='$class' $onClick $title $id $style>$label</a>";
}

function optionalAlert() {
	if(isset($_SESSION['optional_alert_message'])) {
		echo "alert(\"{$_SESSION['optional_alert_message']}\");\n";
		unset($_SESSION['optional_alert_message']);
	}
}

function setOptionalAlert($msg) {
	if(is_array($msg)) $msg = join('<p>', $msg);
	
	$_SESSION['user_notice'] = "<span style='font-size:1.3em'>$msg</span>";
}


function constrainedAddressTable($label, $prefix, $client) {
	$raw = explode(',', 'street1,Address,street2,Address 2,city,City,state,State,zip,ZIP');  
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	echo "<table width=100%>\n";
	echo "<tr><td>$label</td><td>".googleMapLink($prefix)."</td></tr>";
	foreach(array('zip','street1','street2','city','state') as $base) {
		$key = $prefix.$base;
		if($base == 'zip' && function_exists('dumpZipLookupJS')) {
			$zipCodes = fetchCol0("SELECT zip FROM tblzipcodeslocal ORDER BY zip");
			$options = array('Select ZIP'=>null);
			if(!$zipCodes) $options = array('No ZIP Codes defined!'=>null);
			else foreach($zipCodes as $zip) $options[$zip] = $zip;
			//selectRow($fields[$base], $key, $client[$key], $options, $onChange="lookUpZip(this.value, \"$prefix\")");
			$zipProtected = $client[$key] ? in_array($client[$key], $options) : false;
			$widgets = selectElement('', $key, $client[$key], $options, 
									"lookUpZip(this.value, \"$prefix\");document.getElementById(\"$key"."_unprotected\").value = \"\"", null, null, $noEcho=true);
			ob_start();
			ob_implicit_flush(0);
			labeledInput('', "$key"."_unprotected", ($zipProtected ? '' : $client[$key]) , 
																null, null, "checkUnprotectedZip(this.value, \"$prefix\", \"\", \"$key\")", $maxlength=null);
			$widgets .= ob_get_contents();
			ob_end_clean();
			//$widgets .= labeledInput('', "$key_unprotected", ($zipProtected ? '' : $client[$key]) , 
			//													null, null, "checkUnprotectedZip(this.value,, \"$prefix\")", $maxlength=null);
			labelRow($fields[$base], '', $widgets, null, null, null,  null, $rawValue=true);
		}
		else if($base != 'state' && $base != 'city') inputRow($fields[$base].':', $key, $client[$key], '', 'streetInput');
		else {
			hiddenElement($key, $client[$key]);
			labelRow($fields[$base].':', "label_$key", $client[$key]);
		}
	}
	echo "</table>\n";
}

function addressTable($label, $prefix, $client, $constrained=false, $useHomeAddressCheckbox=false, $zipsUnique=true) {
	$country = getI18Property('country');
	$zipsUnique = !mattOnlyTEST() || !in_array($country, array('AU', 'BE'));
	if($constrained && $_SESSION['preferences']['restrictTerritory']) return constrainedAddressTable($label, $prefix, $client);
	$raw = explode(',', 'street1,Address,street2,Address 2,city,City,state,State,zip,ZIP / Post Code');  
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	echo "<table width=100%>\n"; 
	$mailtohome = !isset($client['mailtohome']) || $client['mailtohome'] ? 'CHECKED' : '';
	$useHomeAddressCheckbox = $useHomeAddressCheckbox ? "<input type='checkbox' id='mailtohome' name='mailtohome' onClick='mailToHomeClicked(this, \"$prefix\")' $mailtohome><label for='mailtohome'> Use home address</label> -" : '';
	$mapCorrector = !mattOnlyTEST() ? " " 
		: " ".fauxlink("Correct It", "openConsoleWindow(\"corrector\", \"address-corrector-map.php?client={$client['clientid']}\",800,800)", 1, 2);
	echo "<tr><td>$label</td><td>$useHomeAddressCheckbox ".googleMapLink($prefix)." $mapCorrector</td></tr>";
	foreach(array('zip','street1','street2','city','state') as $base) {
		$key = $prefix.$base;
		if($base == 'zip' && function_exists('dumpZipLookupJS'))
			inputRow($fields[$base], $key, $client[$key], $labelClass=null, $inputClass='standardInput', null,  null, $onBlur="lookUpZip(this.value, \"$prefix\")");
		else if($base == 'city') {
			if(!$zipsUnique) 
				$choices = " ".fauxLink('choose', "openCityChooser(\"$prefix\")')", true, 'Choose City based on postal code.', $prefix.'_citybutton')
										."<div style='display:none;' id='{$prefix}_citychoices'>No cities to choose - ".
										fauxLink('(close)', "document.getElementById(\"{$prefix}_citychoices\").style.display=\"none\"", true).
										"</div>";
			inputRow($fields[$base].':', $key, $client[$key], '', 'streetInput', null, null, null, $extraContent=$choices);
		}
		else if($base != 'state') inputRow($fields[$base].':', $key, $client[$key], '', 'streetInput');
		else inputRow($fields[$base].':', $key, $client[$key]);
	}
	echo "</table>\n";
}

function googleMapLink($prefix) {
	return fauxLink('Google Map', "openGoogleMap(\"$prefix\")", true, 'Open a Google Map on this location');
}

function todaysDateTable($date=null, $extraStyle='', $noStyle=false, $justStyle=false) {
	if(!$noStyle || $justStyle)
	  echo "<style>
	.oneDayCalendarPage {border:solid black 2px;background:white;width:90px}
	.oneDayCalendarPage td {padding-top:0px;padding-bottom:0px;}
	.monthline {font-size:8pt;}
	.domline {font-size:16pt;font-weight:bold;text-align:center;}
	.dowline {font-size:8pt;;text-align:center;}
	.dowlinesmall {font-size:7pt;;text-align:center;}
	</style>
";
	if($justStyle) return;
	$date = $date ? $date  : date('Y-m-d');
	$time = strtotime($date);
	$dow = date('l', $time);  // l = full, D = char
	$dom = date('j', $time);
	$mon = date('M', $time);  // F = full, M = 3char
	$year = date('Y', $time);
	$dowclass = $dow == 'Wednesday' ?  'dowlinesmall' : 'dowline';

	return "<table class='oneDayCalendarPage' style='$extraStyle'>
	<tr class='monthline'><td>$mon</td><td style='text-align:right'>$year</td></tr>
	<tr class='domline'><td colspan=2>$dom</td></tr>
	<tr class='$dowclass'><td colspan=2>$dow</td></tr>
</table>";
}

function fancyVisitTile($date, $tod, $label, $id=null, $extraDivStyle=null) { // for emailing n the absence of pet.css or style.css
	require_once "gui-fns.php";

	$s = "<style>
		.oneDayCalendarPage {border:solid black 2px;background:white;width:90px}
		.oneDayCalendarPage td {padding-top:0px;padding-bottom:0px;}
		.oneDayCalendarPage td {font-family: arial, helvetica, sans serif}
		.monthline {font-size:0.8em;}
		.domline {font-size:1.5em;font-weight:bold;text-align:center;}
		.dowline {font-size:0.8em;text-align:center;}
		.dowlinesmall {font-size:0.7em;;text-align:center;}
		</style>
	";
	$s .= "<div id='$id' style='font-family: arial, helvetica, sans serif;font-size:0.9em;$extraDivStyle'>";
	$s .= todaysDateTable(date('Y-m-d', strtotime($date)), $extraStyle='', $noStyle=true, $justStyle=false);
	$s .= "$label<br>$tod";
	$s .= "</div>";
	$s = str_replace("\n", " ", $s);
	return $s;
}


function telephoneSMSDialogueHTML($name=null, $tel=null, $sms=false, $class=false) {
	$name =  $name ? $name : '#NAME#';
	$tel = $tel ? numbersOnly($tel) : '#TEL#';
	if(!isMobileTelephone()) {
		return "<span class='$class'>Contact $name</span><table align='center'><tr>"
					."<td>This device cannot be used to make standard telephone calls or text messages.</td>"
					."</tr></table>";
	}
	return "<span class='$class'>Contact $name</span><table align='center'><tr>"
					."<td align='center'><a href='tel:$tel'><img border=0 src='art/mobile-call-phone.png'></a><br>Call</td>"
					.($sms ? "<td align='center'><a href='sms:$tel'><img border=0 src='art/mobile-text.png'></a><br>Text Message</td>" : '')
					."</tr></table>";
}

function numbersOnly($str) {
	for($i=0; $i < count($str); $i++)
		if(is_numeric($str[$i])) $out .= $str[$i];
	return $out;
}


function prettyXML($obj, $level=0) {
	if(!$obj && !is_object($obj)) return;
	$pix = (int)$level * 10;
	if(is_string($obj)) {
		libxml_use_internal_errors (true);
		$obj = simplexml_load_string($obj);
		if(!$obj) {
			$out = "<div style='margin-left:{$pix}px;'>XML ERRORS:<br>";
			foreach(libxml_get_errors() as $error) {
			  // handle errors here
			  //print_r($error);
			  $out .= "Level: {$error->level} code:{$error->code} line: {$error->line} col:{$error->column} message: ".htmlentities($error->message)."<br>";
			}
			return "$out</div>";
    }
	}
	
	$out = "<div style='margin-left:{$pix}px;'><u>".$obj->getName()."</u>";
	foreach($obj->attributes() as $a => $b) $out .= " [$a]=$b";
	$out .= "<div style='color:verydarkgrey;margin-left:3px;'>".(string)$obj."</div>";
	foreach ($obj->children() as $child) $out .= prettyXML($child, $level+1);
	return "$out</div>";
}

function usStatesArray() {
	$raw ='Alabama|AL||Alaska|AK||Arizona|AZ||Arkansas|AR||California|CA||Colorado|CO||Connecticut|CT||Delaware|DE||District of Columbia|DC||Florida|FL||Georgia|GA||Hawaii|HI||Idaho|ID||Illinois|IL||Indiana|IN||Iowa|IA||Kansas|KS||Kentucky|KY||Louisiana|LA||Maine|ME||Maryland|MD||Massachusetts|MA||Michigan|MI||Minnesota|MN||Mississippi|MS||Missouri|MO||Montana|MT||Nebraska|NE||Nevada|NV||New Hampshire|NH||New Jersey|NJ||New Mexico|NM||New York|NY||North Carolina|NC||North Dakota|ND||Ohio|OH||Oklahoma|OK||Oregon|OR||Pennsylvania|PA||Rhode Island|RI||South Carolina|SC||South Dakota|SD||Tennessee|TN||Texas|TX||Utah|UT||Vermont|VT||Virginia|VA||Washington|WA||West Virginia|WV||Wisconsin|WI||Wyoming|WY||American Samoa|AS||Guam|GU||Northern Mariana Islands|MP||Puerto Rico|PR||U.S. Virgin Islands|VI||Federated States of Micronesia|FM||Marshall Islands|MH||Palau|PW';
	return explodePairsLine($raw);
}


function imageDimensionsScaledToFit($imgfile, $width, $height) {
	$dims = getimagesize($imgfile);
	$factor = min($width / $dims[0], $height / $dims[1]);
	return array(round($dims[0] * $factor), round($dims[1] * $factor));
}

?>