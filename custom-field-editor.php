<?
// custom-field-editor.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "custom-field-fns.php";

// Determine access privs
$locked = locked('o-');

$species = $_REQUEST['species'] ? $_REQUEST['species'] : 'Client';
$prefix = $species == 'Client' ? '' : 'pet';


if($_POST) {
	saveCustomFieldSpecs($prefix);
	setPreference('showAllCustomFieldsInMobileSitterApps', $_POST['showAllCustomFields']);
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	//if($species == 'Client') header("Location: $mein_host$this_dir/client-list.php");
	//else 
	$_SESSION['frame_message'] = "Changes have been saved.";
}

$pageTitle = "Custom $species Fields";
$breadcrumbs = ($species == 'Pet' ? "<a href='custom-field-editor.php'>Custom Client Fields</a>" 
																	:	"<a href='custom-field-editor.php?species=Pet'>Custom Pet Fields</a>")
							.' <img src="art/help.jpg" width=30 onclick="showHelp()">';;
$extraHeadContent = '<script language="javascript">function showHelp() {
$.fn.colorbox({html:"'.addSlashes(helpString()).'", width:"550", height:"400", scrolling: true, opacity: "0.3"});
}</script><style>.hilist li {padding-top:7px;}</style>';

	$_SESSION['preferences'] = fetchPreferences(); // don't know why bogus empty fields keep getting added, but...
include "frame.html";
// ***************************************************************************
echo "<form name='customfieldform' method='POST'>";
echo "<p align='right'>";
//if($_SESSION['staffuser'])
	echoButton('', 'Edit List Order', 'editOrder()');
echo " ";
echoButton('', "Save Custom $species Fields", 'checkAndSubmit()');
hiddenElement('species', $species);
if(strtoupper($species) == 'PET') $fields = getCustomFields($activeOnly=false, $visitSheetOnly=false, getPetCustomFieldNames());
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog("fields in editor:".print_r(getPetCustomFieldNames(), 1)."<br>");}

if(staffOnlyTEST()) {
	$showAllCustomFields = $_SESSION['preferences']['showAllCustomFieldsInMobileSitterApps'];
	echo "<p class='fontSize1_1em'>In mobile sitter apps, show ";
	labeledRadioButton('Only "Visit Sheet" Custom Client and Pet Fields', 'showAllCustomFields', $value=0, $showAllCustomFields, $onClick=null, $labelClass=null, $inputClass=null, $labelFirst=null);
	labeledRadioButton('ALL Custom Client and Pet Fields', 'showAllCustomFields', $value=1, $showAllCustomFields, $onClick=null, $labelClass=null, $inputClass=null, $labelFirst=null);
}

customFieldSpecEditor($fields, $prefix);

echo "</form>";

?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function update() {
	document.location.href='custom-field-editor.php?species=<?= $species ?>';
}

function editOrder() {
	if(!confirm('Changes will be saved before proceeding.  OK?')) return;
	checkAndSubmit();
	openConsoleWindow("serviceSorderEdit", "custom-order-edit.php?prefix=<?= $prefix ?>custom",400,700);
}

function checkAndSubmit() {
	//var maxCustomFields = <?= $maxCustomFields ?>;
	var msgargs = [];
	for(var i=1; document.getElementById('custom'+i); i++) 
	  if(document.getElementById('active_'+i).checked &&
	     	!document.getElementById('custom'+i).value.replace(/^\s\s*/, '').replace(/\s\s*$/, '')) {
	    msgargs[msgargs.length] = 'Custom field #'+i+' must have a label if you mark it active.';
	    msgargs[msgargs.length] = '';
	    msgargs[msgargs.length] = 'MESSAGE';
		}
  if(!MM_validateFormArgs(msgargs)) 
		  return false;
	document.customfieldform.submit();
}

function updateCheckBoxToMatchCheckBox(cbox1, cbox2, whenCbox2Is) {
	if(typeof cbox1 == undefined && typeof cbox2 == undefined) return;
//alert('['+cbox1.checked+'] ['+cbox2.checked+'] ['+whenCbox2Is+']');	
	if(cbox2.checked == whenCbox2Is) cbox1.checked = cbox2.checked;
}

</script>
<p><img src='art/spacer.gif' height=300>
<?
// ***************************************************************************

include "frame-end.html";

function helpString() {
	global $species;
	$help = <<<HELP
<h2 style='text-align:center'>Working with Custom Fields</h2>
<span style='font-size:1.2em'><p>This page lets you set up your own special fields for {$species}s.
<p>It is pretty straightforward, but here are a few points to keep in mind.<ul class="hilist">
<li>The "Active" box of a field must be checked, or it will not appear anywhere.
<li>Unless the "Visit Sheets" box of a field is checked, it will not appear in <b>Visit Sheets</b>, 
<b>The Mobile Sitter App</b>, or in the <b>Client Profile</b> that your clients can fill out.  
It will be visible only to admins and to sitters using the Web version of LeashTime.
<li>In other words, the "Visit Sheets" box <u>must</u> be checked for a field you want your clients to see in their profiles.
<li>Use the <b>Edit List Order</b> to change the display order of the custom fields.  Do <u>not</u> switch labels around on this page.
</ul></span>
HELP;
	return trim(str_replace("\r", "", str_replace("\n", "", $help)));
}

?>

