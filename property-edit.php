<?
//property-edit.php?prop

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "preference-fns.php";

// Determine access privs
$locked = locked('o-');

if($_POST) {
  extract($_POST);
  if($proptype == 'list') {
		for($i=1;$i <= $_POST[$property.'_visible']; $i++)
		  if(trim($_POST["pref_$property$i"]))
		    $vals[] = trim($_POST["pref_$property$i"]);
    setPreferenceList($property, $vals);
	}
	else if($proptype == 'boolean') {
	  setPreference($property, $propertyValue);
	}
	else //if($proptype == 'string')
	  setPreference($property, $propertyValue);
	  
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
	
	//exit;
	
	echo "<script language='javascript'>alert(\"<?= $propertyValue ?>\");window.opener.updateProperty(\"$widgetName\", escape(\"$propertyValue\"));window.close();</script>";
	exit;
}
?>
<head>
  <title>Preference Editor</title>
  <link rel="stylesheet" href="style.css" type="text/css" /> 
  <link rel="stylesheet" href="pet.css" type="text/css" /> 

</head>
<body style='padding: 10px;'>
<form name='propertyeditor' method='POST'>
<?
extract($_GET);
$description = explode('|',$prop);
hiddenElement('property', $description[0]);
hiddenElement('proptype', $description[2]);
hiddenElement('widgetName', isset($widgetName) ? $widgetName : '');
// petTypes|Pet Types|list  or bizName|Business Name|string


echo "<h2>Property: {$description[1]}</h2>";
$preferences = $_SESSION['preferences'];
$propertyValue = $preferences[$description[0]];
if($description[2] == 'list') {
	$sortable = isset($description[3]) && $description[3] == 'sortable';
  preferenceListEditorTable($description[0], '|', $sortable);
}
else if(in_array($description[0], array('bimonthlyBillOn1', 'bimonthlyBillOn2')) && $preferences['monthlyServicesPrepaid']) {
	$options = isset($description[3]) && $description[3] ? explode(',',$description[3]) : array();
  selectElement('', 'propertyValue', $propertyValue, array_combine($options, $options));
  labeledCheckBox('Bill for Prepaid Monthly Schedules on this day', 'monthlyBillOnCB', $preferences[$description[0]] == $preferences['monthlyBillOn']);
}
else if($description[2] == 'string')
  labeledInput('', 'propertyValue', $propertyValue, '','VeryLongInput');
else if($description[2] == 'int')
  labeledInput('', 'propertyValue', $propertyValue, '','');
else if($description[2] == 'password')
  labeledPassword('', 'propertyValue', $propertyValue, '','VeryLongInput');
else if($description[2] == 'boolean')
  selectElement('', 'propertyValue', $propertyValue, array('yes'=>1, 'no'=>0));
else if($description[2] == 'picklist') {
	$options = isset($description[3]) && $description[3] ? explode(',',$description[3]) : array();
  selectElement('', 'propertyValue', $propertyValue, array_combine($options, $options));
}
echo "<p>";
echoButton('', "Save {$description[1]}", 'saveAndQuit()');
echo " ";
echoButton('', "Quit", 'window.close()');

$constraints = '';
if($description[2] == 'float') $constraints = "'propertyValue','','FLOAT'";
else if($description[2] == 'unsignedfloat') $constraints = "'propertyValue','','UNSIGNEDFLOAT'";
else if($description[2] == 'int') $constraints = "'propertyValue','','INT'";
else if($description[2] == 'unsignedint') $constraints = "'propertyValue','','UNSIGNEDINT'";

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
