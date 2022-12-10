<? // filter-clients-custom-fields.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "custom-field-fns.php";

$locked = locked('o-');//locked('o-');

if($_POST) {
	foreach($_POST as $k => $v) {
		if($v) { 
			list($action, $field) = explode('_', $k);
			$filterFields[$field] = array('action'=>$action, 'value'=>$v);
		}
	}
	//updateCustomFilter(json, label) {customFilterDescription($customfieldsJSON)
	$maxFields = 2;
	$label = $filterFields ? customFilterDescription($filterFields) : 'Filter by custom fields';
	if(strlen($label) > 100)
		$label = customFilterDescription($filterFields, $maxFields)."... and ".(count($filterFields)-$maxFields)." other fields";
	$json = json_encode($filterFields);
	echo "<hr>".print_r($filterFields,1)."<hr>".print_r($label, 1)."<hr>".print_r($json,1);
	echo 
		"<script>
		parent.updateCustomFilter($json, '$label');
		parent.$.fn.colorbox.close();
		</script>";
	exit;
}

?>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" /> 
<?
$orderedFields = displayOrderCustomFields(getCustomFields(), '');

echo "<table style='width:100%'><tr><td>";
echoButton('', 'Go', 'document.custom.submit()');
echo "</td><td style='text-align:right;'>";
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
echo "</td></tr></table>";

if($_GET['filter'] && $_GET['filter'] != 'null') {
	//echo "FILTER: [".print_r($_GET['filter'], 1)."]<p>";
	$filter = json_decode($_GET['filter'], 'assoc');
	//$existingFilter = json_decode($_SESSION['clientFilterJSON'], 'assoc');
	//echo "FILTER: [".print_r($existingFilter, 1)."]<p>";
	foreach($filter as $fieldName => $actionValue)
		$vals["{$actionValue['action']}_$fieldName"] = $actionValue['value'];
}
		
echo "<h2>Filter by custom fields</h2>";
echo "<form method='POST' name='custom' id='custom'>";
foreach($orderedFields as $fldname => $fld) {
	$row = array('id'=>$fldname, 'label'=>$fld[0]);
	$row['yes'] = 
		$fld[2] == 'boolean' ? 
			labeledCheckbox('Yes', "yes_$fldname", $vals["yes_$fldname"], $labelClass=null, $inputClass=null, "boxChecked(this, \"$fldname\")", $boxFirst=true, $noEcho=true, $title=null) : (
		$fld[2] == 'file' ? 
			labeledCheckbox('Supplied', "yes_$fldname", $vals["yes_$fldname"], $labelClass=null, $inputClass=null, "boxChecked(this, \"$fldname\")", $boxFirst=true, $noEcho=true, $title=null) :
		labeledCheckbox('has any', "any_$fldname", $vals["any_$fldname"], $labelClass=null, $inputClass=null, "boxChecked(this, \"$fldname\")", $boxFirst=false, $noEcho=true, $title=null)
		.' '.labeledInput('or', "pat_$fldname", $vals["pat_$fldname"], $labelClass=null, $inputClass=null, "textEntered(this, \"$fldname\")", $maxlength=null, $noEcho=true))
		;
		
	$row['no'] = 
		$fld[2] == 'boolean' ? 
			labeledCheckbox('No', "no_$fldname", $vals["no_$fldname"], $labelClass=null, $inputClass=null, "boxChecked(this, \"$fldname\")", $boxFirst=true, $noEcho=true, $title=null) : (
		$fld[2] == 'file' ? 
			labeledCheckbox('Not Supplied', "no_$fldname", $vals["no_$fldname"], $labelClass=null, $inputClass=null, "boxChecked(this, \"$fldname\")", $boxFirst=true, $noEcho=true, $title=null) :
		labeledCheckbox('Not Supplied', "no_$fldname", $vals["no_$fldname"], $labelClass=null, $inputClass=null, "boxChecked(this, \"$fldname\")", $boxFirst=true, $noEcho=true, $title=null));
	$rows[] = $row;
}
$columns = explodePairsLine("label|Field||yes| ||no| ");
//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null)
tableFrom($columns, $rows);
echo "</form>";
?>
<script>
function boxChecked(el, fieldname) {
	if(el.checked) {
		unCheckIfThere("yes_"+fieldname);
		unCheckIfThere("no_"+fieldname);
		unCheckIfThere("any_"+fieldname);
		el.checked = true;
		if(el = document.getElementById("pat_"+fieldname)) {
			el.value = '';
		}
	}
}

function unCheckIfThere(id) {
	if(document.getElementById(id)) document.getElementById(id).checked = false;
}

function textEntered(el, fieldname) {
	if(el.value) {
		unCheckIfThere("no_"+fieldname);
		unCheckIfThere("any_"+fieldname);
	}
}


</script>
