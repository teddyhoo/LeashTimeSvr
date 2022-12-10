<?
// key-safe-editor.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "key-safe-fns.php";

// Determine access privs
locked('+#ka,+#km');

//print_r($_POST);exit;
if($_POST) {
	require_once "preference-fns.php";
	setPreference('keyAdminsCanSeeAdminOnlySafes', $_POST['keyAdminsCanSeeAdminOnlySafes']);
	saveKeySafes();
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	$_SESSION['frame_message'] = "Key Safe changes saved.";
	//header("Location: $mein_host$this_dir/index.php");
}
$pageTitle = "Key Safes";

include "frame.html";
// ***************************************************************************
echo "<form name='keysafeform' method='POST'>";
if($_SESSION['preferences']['enableAdminOnlyKeySafes']) {
	$keyAdminsCanSeeAdminOnlySafes = $_SESSION['preferences']['keyAdminsCanSeeAdminOnlySafes'];
	$opts = array('yes'=>1, 'no'=>0);
	$opts = radioButtonSet('keyAdminsCanSeeAdminOnlySafes', $keyAdminsCanSeeAdminOnlySafes, $opts, $onClick=null, $labelClass=null, $inputClass=null, $rawLabel=false);
	echo "<span class='fontSize1_1em'>Sitters with Key Admin rights can see Admin Only Key Safes: '</span>";
	echo join(' ', $opts);
	echo "<br><span class='tiplooks'><img src='art/spacer.gif' width=10>If \"no\", only office staff can access ADMIN Only Key Safes.</span><p>";
}

keySafeEditor();
echoButton('', 'Save Key Safes', 'checkAndSubmit()');
echo "</form>";

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function checkAndSubmit() {
	var maxKeySafes = <?= $maxKeySafes ?>;
	var msgs = [];
	var labelsFound = [];
	for(var i=1; i <= maxKeySafes; i++) {
		var label = document.getElementById('safe'+i).value.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
	  if(document.getElementById('active_'+i).checked && !label) {
	    
	    msgs[msgs.length] = 'Key Safe #'+i+' must have a label if you mark it active.';
	    msgs[msgs.length] = '';
	    msgs[msgs.length] = 'MESSAGE';
	   }
		else if(label || document.getElementById('active_'+i).checked) {
			for(var f=0; f < labelsFound.length; f++) {
				if(label == labelsFound[f]) {
					msgs[msgs.length] = 'The label '+label+' cannot be used more than once.';
					msgs[msgs.length] = '';
					msgs[msgs.length] = 'MESSAGE';
				}
			}
			labelsFound[labelsFound.length] = label;
		}
	}

	if(!MM_validateFormArgs(msgs)) return false;
	document.keysafeform.submit();
}

</script>
<p><img src='art/spacer.gif' height=300>
<?
// ***************************************************************************

include "frame-end.html";
?>

