<?
//property-edit2.php?prop

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "preference-fns.php";

// Determine access privs
$locked = locked('o-');

if($_POST) {
  extract($_POST);
  if($proptype == 'list') {
		foreach($_POST as $key => $val)
			if(strpos($key, "ref_$property") && trim($val))
		    $vals[] = trim($val);
    setPreferenceList($property, $vals);
	}
	else if($proptype == 'boolean') {
	  setPreference($property, $propertyValue);
	}
	else //if($proptype == 'string')
	  setPreference($property, stripslashes($propertyValue));	  

	$widgetName = isset($widgetName) ? $widgetName : '';
	//$propertyValue = htmlentities($_SESSION['preferences'][$property], ENT_QUOTES);
	$propertyValue = $_SESSION['preferences'][$property];
	
	$billDayKeys = array('bimonthlyBillOn1', 'bimonthlyBillOn2');
	if(in_array($property, $billDayKeys)) {
		if(!$monthlyBillOnCB || !is_numeric($propertyValue)) $monthlyBillOn = '';
		else if($monthlyBillOnCB) $monthlyBillOn = $propertyValue;
		
		// ensure $monthlyBillOn is set
		if(!$monthlyBillOn) {
			$billDays = array();
			foreach($billDayKeys as $key)
				if(is_numeric($_SESSION['preferences'][$key])) $billDays[$key] = $_SESSION['preferences'][$key];
			if(!$billDays) $billDays = array(1);
			foreach($billDays as $alternative)
				if($alternative != $propertyValue) $monthlyBillOn = $alternative;
			$monthlyBillOn = $monthlyBillOn ? $monthlyBillOn : current($billDays);
			//print_r($monthlyBillOn);
		}
		if($monthlyBillOn != $_SESSION['preferences']['monthlyBillOn']) {
			setPreference('monthlyBillOn', $monthlyBillOn);
			$propertyValue .= ",monthlyBillOn";
		}
	}
	
	if($_POST['applyToAllSittersOption']) { // set all to the default
		foreach(fetchCol0("SELECT providerid FROM tblprovider") as $provid)
			setProviderPreference($provid, $property, null/*$propertyValue*/);
	}
	//exit;
	
	echo "<script language='javascript'>window.opener.updateProperty(\"$property\", \"$propertyValue\");window.close();</script>";
	exit;
}

$windowTitle = 'Preference Editor';
require "frame-bannerless.php";

extract($_GET);
$description = explode('|',$prop);
?>
<h2 style='padding-top:0px;'>Property: <?= $description[1] ?></h2>

<? // CUSTOM KLUDGE
if(in_array($description[0], array('clientCreditCardRequired', 'offerClientCreditCardMenuOption'))) {
	require "cc-processing-fns.php";
	if(!(merchantInfoSupplied()))
		echo "<p class='fontSize1_2em warning'><span class='fontSize1_2em bold'>You have no Credit Card Gateway set up.</span><br>
					Until you have a Credit Card Gateway set up, this value should be set to <b>\"no\"</b>.</p>";

}

?>




<form name='propertyeditor' method='POST'>
<?
hiddenElement('property', $description[0]);
hiddenElement('proptype', $description[2]);
hiddenElement('widgetName', isset($widgetName) ? $widgetName : '');
// petTypes|Pet Types|list  or bizName|Business Name|string


$preferences = $_SESSION['preferences'];
$propertyValue = $preferences[$description[0]];

$editorType = $description[2];

$instructionIndex = in_array($editorType, explode(',', 'string,email,int,password,boolean')) ? 3 : 4;

if($description[$instructionIndex]) echo "<p class='tiplooks' style='text-align:left;'>{$description[$instructionIndex]}</p>";

if($editorType == 'list') {
	$sortable = isset($description[3]) && $description[3] == 'sortable';
  preferenceListEditorTable2($description[0], '|', $sortable);
}
else if(in_array($description[0], array('bimonthlyBillOn1', 'bimonthlyBillOn2')) && $preferences['monthlyServicesPrepaid']) {
	$options = isset($description[3]) && $description[3] ? explode(',',$description[3]) : array();
  selectElement('', 'propertyValue', $propertyValue, array_combine($options, $options));
  labeledCheckBox('Bill for Fixed Price Monthly Schedules on this day', 'monthlyBillOnCB', $preferences[$description[0]] == $preferences['monthlyBillOn']);
}
else if($editorType == 'string')
  labeledInput('', 'propertyValue', $propertyValue, '','VeryLongInput');
else if($editorType == 'email')
  labeledInput('', 'propertyValue', $propertyValue, '','VeryLongInput');
else if($editorType == 'int' || $editorType == 'float' || $editorType == 'maxint' || $editorType == 'maxminint')
  labeledInput('', 'propertyValue', $propertyValue, '','');
else if($editorType == 'password')
  labeledPassword('', 'propertyValue', null, '','VeryLongInput');
else if($editorType == 'boolean')
  selectElement('', 'propertyValue', $propertyValue, array('yes'=>1, 'no'=>0));
else if($editorType == 'picklist') {
	$rawOptions = isset($description[3]) && $description[3] ? explode(',',$description[3]) : array();
	foreach($rawOptions as $opt) {
		$pair = explode('=>', $opt);
		if(count($pair) == 2) $options[$pair[0]] = $pair[1];
		else $options[$pair[0]] = $pair[0];
	}
  selectElement('', 'propertyValue', $propertyValue, $options);
}
if($_GET['applyToAllSittersOption'] && staffOnlyTEST()) { // opened to staff 7/2/2020
	echo "<img src='art/spacer.gif' width=20 height=1>";
	labeledCheckBox('Apply to all existing sitters', 'applyToAllSittersOption', false, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
}
echo "<p>";

echoButton('', "Save {$description[1]}", 'saveAndQuit()');
echo " ";
echoButton('', "Quit", 'window.close()');

$constraints = '';
if($editorType == 'float') $constraints = "'propertyValue','','FLOAT'";
else if($editorType == 'unsignedfloat') $constraints = "'propertyValue','','UNSIGNEDFLOAT'";
else if($editorType == 'int') $constraints = "'propertyValue','','INT'";
else if($editorType == 'maxint') {
	$constraints = "'propertyValue','','INT','propertyValue','{$description[3]}','MAX'";
}
else if($editorType == 'maxminint') {
	$maxMin = explode(',', $description[3]);
	$constraints = "'propertyValue','','INT','propertyValue','{$maxMin[0]}','MIN','propertyValue','{$maxMin[1]}','MAX'";
}
else if($editorType == 'unsignedint') $constraints = "'propertyValue','','UNSIGNEDINT'";
else if($editorType == 'email') $constraints = "'propertyValue','','isEmail'";

if($constraints) {
?>
<script language='javascript' src='check-form.js'></script>
<? } 
?>
<script language='javascript'>
<?
if($constraints) echo "setPrettynames('propertyValue','{$description[1]}');\n"
?>
function saveAndQuit() {
<? if($constraints) echo "if(!MM_validateForm($constraints)	) return;"; ?>
	
	document.propertyeditor.submit();
}

<? 
dumpPrefsJS();
?>
</script>
