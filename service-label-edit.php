<? // service-label-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "service-fns.php";

locked('o-');
$id = $_REQUEST['id'];
$currentLabel = fetchRow0Col0("SELECT label FROM tblservicetype where servicetypeid = $id");
if($_POST['action'] == 'change') {
	updateTable('tblservicetype', array('label'=>$_POST['typelabel']), "servicetypeid = $id", 1);
	$_SESSION['frame_message'] = 'Service label changed.';
	echo "<script language='javascript'>parent.location.href = 'service-types.php';</script>";
	exit;
}

$allServiceTypeLabels = fetchKeyValuePairs("SELECT servicetypeid, UPPER(label) FROM tblservicetype");
$extraBodyStyle = 'background-image:url("");font-size:1.05em;';
require_once "frame-bannerless.php";
?>
You are editing the label for service type #<b><?= $id ?></b>, which is currently labeled
<p align='center'><b><?= $currentLabel ?></b></p>
Please be aware that if you change this label, every visit of this type (past, current, and future) will bear
the new label, and will not retain the old label.
<p>
Any change you make here will take effect immediately.
<p>
<form name='labeleditor' method='POST'>
<? hiddenElement('action', 'change'); ?>
Label: <input type='text' name='typelabel' id='typelabel' value='<?= $currentLabel ?>' class='VeryLongInput'>
<p class='fontSize0_7em'>
<? echoButton('', 'Save Label', 'checkAndSubmit()', 'BigButton', 'BigButtonDown'); ?>
<img src='art/spacer.gif' width=20 height=1>
<? echoButton('', 'Quit', 'quit()'); ?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('typelabel', 'Some label');
function quit() {parent.$.fn.colorbox.close();}

function checkAndSubmit() {
	var ucaseTypelabel = trim(document.getElementById('typelabel').value.toUpperCase());
	var needsTrimming = 
		ucaseTypelabel == document.getElementById('typelabel').value.toUpperCase() ? '' : 'Please elimate spaces before and after the label.';
	var duplicate = '';
	<? foreach($allServiceTypeLabels as $typeId => $ucaseLabel)
			$uCaseLabels[] = "$typeId, \"".htmlentities(safeValue(trim($ucaseLabel)))."\""; 
	?>
	var ucaseLabels = [<?= join(', ', $uCaseLabels) ?>]; 
	for(var i=0; i < ucaseLabels.length; i+=2) 
	//alert(ucaseLabels[i]+": "+ucaseLabels[i+1]);
		if(ucaseLabels[i] != <?= $id ?> && ucaseLabels[i+1] == ucaseTypelabel)
			duplicate = "There is already another service type with this name.";
	if(!MM_validateForm('typelabel', '', 'R', duplicate, '', 'MESSAGE', needsTrimming, '', 'MESSAGE')) return;
	document.labeleditor.submit();
}
</script>
	