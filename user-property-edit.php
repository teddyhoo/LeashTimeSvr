<?
//user-property-edit.php?prop

require_once "common/init_session.php";
require "preference-fns.php";

// Determine access privs
if(userRole() == 'z') {
	$locked = locked('z-');
	$userId = $_REQUEST['userid'];
	require_once "common/init_db_common.php";
	$user = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = '$userId'");
	if(!$user) $error = "Bad userid.";
	else if(!$user['bizptr']) $error = "This is for business users only (not LT staff).";
	if($error) {echo $error; exit;}
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$user['bizptr']}'");
	if(!$biz) $error = "Bad biz: {$user['bizptr']}.";
	if($error) {echo $error; exit;}
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
}
else {
	$locked = locked('o-');
	$userId = $_SESSION['auth_user_id'];
	require_once "common/init_db_petbiz.php";
}

if($_POST) {
  extract($_POST);
  if($proptype == 'list') {
		foreach($_POST as $key => $val)
			if(strpos($key, "ref_$property") && trim($val))
		    $vals[] = trim($val);
    setUserPreferenceList($userId, $property, $vals);
	}
	else if($proptype == 'boolean') {
	  setUserPreference($userId, $property, $propertyValue);
	}
	else //if($proptype == 'string')
	  setUserPreference($userId, $property, stripslashes($propertyValue));	  

	$widgetName = isset($widgetName) ? $widgetName : '';
	//$propertyValue = htmlentities($_SESSION['preferences'][$property], ENT_QUOTES);
	$propertyValue = getUserPreference($userId, $property);
	
	
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
<form name='propertyeditor' method='POST'>
<?
if(userRole() == 'z-') hiddenElement('userid', $userId);
hiddenElement('property', $description[0]);
hiddenElement('proptype', $description[2]);
hiddenElement('widgetName', isset($widgetName) ? $widgetName : '');
// petTypes|Pet Types|list  or bizName|Business Name|string


$propertyValue = getUserPreference($userId, $description[0]);
if($description[2] == 'list') {
	$sortable = isset($description[3]) && $description[3] == 'sortable';
  preferenceListEditorTable2($description[0], '|', $sortable);
}
else if($description[2] == 'string')
  labeledInput('', 'propertyValue', $propertyValue, '','VeryLongInput');
else if($description[2] == 'int' || $description[2] == 'float')
  labeledInput('', 'propertyValue', $propertyValue, '','');
else if($description[2] == 'password')
  labeledPassword('', 'propertyValue', null, '','VeryLongInput');
else if($description[2] == 'boolean')
  selectElement('', 'propertyValue', $propertyValue, array('yes'=>1, 'no'=>0));
else if($description[2] == 'picklist') {
	$rawOptions = isset($description[3]) && $description[3] ? explode(',',$description[3]) : array();
	foreach($rawOptions as $opt) {
		$pair = explode('=>', $opt);
		if(count($pair) == 2) $options[$pair[0]] = $pair[1];
		else $options[$pair[0]] = $pair[0];
	}
  selectElement('', 'propertyValue', $propertyValue, $options);
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
