<? // sitter-profile-chooser.php
/*
args: id = provider id --or-- fixed = provider id
template: one of the template identifiers: photoleft, photoright, barebones, barebonesphoto
//https://leashtime.com/sitter-profile-chooser.php?id=42&emailedprofiletemplate=photoleft
//https://leashtime.com/sitter-profile-chooser.php?id=42
//https://leashtime.com/sitter-profile-chooser.php?fixed=42
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "preference-fns.php";
require_once "gui-fns.php";
require "provider-profile-fns.php";

// Determine access privs
$locked = locked('+o-,+d-,#as');
$fixed = $_GET['fixed'];
$id = $fixed ? $fixed : $_GET['id'];
if($id < 0) {
	$message = 'Please chose a sitter with a profile.';
	$id = 0;
}

$emailedprofiletemplate = $_GET['emailedprofiletemplate'];
if(TRUE) { //$_GET['format'] == 'email'
	if($_GET['template']) {
		setPreference('emailedSitterProfileTemplate', $_GET['template']);
		echo 'OK';
		exit;
	}
	
require_once "frame-bannerless.php";
?>
<form name='prfilechooserform' method="POST">
<?
echoButton('ok', 'OK', $onClick='done()', $class='', $downClass='', $noEcho=false, "Select this client's profile'");
echo " ";
echoButton('cancel', 'Cancel', $onClick='$.fn.colorbox.close();', $class='', $downClass='', $noEcho=false, "Make no selection.");
echo "<p>";

if($message) echo "<p class='tiplooks'>$message</p>";
if($fixed) hiddenElement('fixedid', $id);
else {
	$options = array('-- Select a sitter - '=>0);
	$sitters = fetchKeyValuePairs(
		"SELECT providerid, CONCAT_WS(' ', fname, lname)
			FROM tblprovider
			WHERE active=1
			ORDER BY lname, fname");
	foreach($sitters as $provid => $name) {
		if(!getProviderProfileFields($provid)) {
			$name = "$name - no profile";
			$provid = -1;
			$optExtras[$name] = "style='font-style:italic;color:grey' title='This sitter has no profile to send'";
		}
		else $optExtras[$name] = "title='Choose this sitter's profile'";
		$options[$name] = $provid;
	}
	echo "<p>";
	selectElement('Sitter:', 'id', $value=$id, $options, $onChange='reload()', $labelClass=null, $inputClass=null, $noEcho=false, $optExtras, $title=null);
}
$options = sitterProfileEmailTemplateOptions();
$currentChoice = $emailedprofiletemplate ? $emailedprofiletemplate : $_SESSION['preferences']['emailedSitterProfileTemplate'];
labeledSelect("Selected profile style: ", 'emailedprofiletemplate', 
							$currentChoice, $options, $labelClass=null, $inputClass=null, $onChange='reload()', $noEcho=false);
?>
</form>
<?
	
	echo "<hr>";
	if($id > 0) dumpEmailProfile($id, $emailedprofiletemplate);
?>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script language='javascript'>
function reload(el) {
	var provider = <?= $fixed ? "'fixed=$fixed'" : 
										"'id='+document.getElementById(\"id\").options[document.getElementById(\"id\").selectedIndex].value"
									?>;
	var template = document.getElementById('emailedprofiletemplate').options[document.getElementById('emailedprofiletemplate').selectedIndex].value;
	document.location.href='sitter-profile-chooser.php?'+provider+'&emailedprofiletemplate='+template;
}
</script>
<?
}