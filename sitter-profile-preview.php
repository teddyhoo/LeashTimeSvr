<? // sitter-profile-preview.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "preference-fns.php";
require_once "gui-fns.php";
require "provider-profile-fns.php";

// Determine access privs
$locked = locked('+o-,+d-,#as');

if($_GET['format'] == 'email') {
	if($_GET['template']) {
		setPreference('emailedSitterProfileTemplate', $_GET['template']);
		echo 'OK';
		exit;
	}
	
	$options = sitterProfileEmailTemplateOptions();
	$currentChoice = $_SESSION['preferences']['emailedSitterProfileTemplate'];
	labeledSelect("Selected profile template for all emailed sitter profiles: ", 'emailedprofiletemplate', 
								$currentChoice, $options, $labelClass=null, $inputClass=null, $onChange='changeTemplate(this)', $noEcho=false);
	echo "<hr>";
	dumpEmailProfile($_GET['id']);
?>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script language='javascript'>
function changeTemplate(el) {
	$.ajax({
	  url: "sitter-profile-preview.php?<?= "id={$_GET['id']}&format={$_GET['format']}&template=" ?>"
					+el.options[el.selectedIndex].value
	}).done(function(data) { 
	  if(data == 'OK') {
			window.parent.preview('email');
			return true;
		}
		else alert(data);
	});
	
}
</script>
<?
}