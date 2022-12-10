<? // client-flags.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-flag-fns.php";

$locked = locked('o-');

if($_POST['flagidtodrop']) {  // POST FROM LIGHTBOX
	$flagId = $_POST['flagidtodrop'];
	include "frame-bannerless.php";
	foreach($_POST as $k => $v)
		if(strpos($k, 'client_') === 0) $clients[] = substr($k, strlen('client_'));
	foreach((array)$clients as $clientid)
		dropClientFlag($clientid, $flagId);
	echo "<div class='tiplooks'>Dropped flag from ".count($clients)." clients. $idstr</div>";
	flagReportTableWithDelete($flagId);
	exit;
}

if($_POST['retireflagid']) {  // POST FROM LIGHTBOX
	$_SESSION['frame_message'] = retireBizFlag( $_POST['retireflagid']);
	// refresh parent page
	echo "<script>parent.document.location.href = 'client-flags.php'</script>";
	exit;
}

if($_REQUEST['flagid']) {  // LIGHTBOX CALL
	$extraHeadContent = '<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" />
	<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
	<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';
	include "frame-bannerless.php";
	if(staffOnlyTEST()) flagReportTableWithDelete($_REQUEST['flagid']);
	else flagReportTable($_REQUEST['flagid']);
	exit;
}
if($_REQUEST['billingflagid']) {  // LIGHTBOX CALL
	$extraHeadContent = '<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" />
	<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
	<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>';
	include "frame-bannerless.php";
	if(staffOnlyTEST()) flagReportTableWithDelete($_REQUEST['billingflagid'], 'billing');
	else flagReportTable($_REQUEST['billingflagid'], 'billing');
	exit;
}
if($_POST) {
	for($i=1; isset($_POST["flag_title$i"]); $i++) {
		if($_POST["flag_title$i"]) {
			setPreference("biz_flag_$i", ($_POST["officeonly_$i"] ? '1' : '0').'|'.$_POST["flag_src_$i"].'|'.$_POST["flag_title$i"]);
		}
		else setPreference("biz_flag_$i", null);
	}
	for($i=1; $i <= $maxBillingFlags; $i++) {
		setPreference("billing_flag_$i", billFlagSrc($i).'|'.$_POST["billflag_title$i"]);
		if(staffOnlyTEST() || customizedBillingFlagsEnabled()/* BILLFLAGCHOOSER */) setPreference("billing_flag_$i", $_POST["billflag_src_$i"].'|'.$_POST["billflag_title$i"]);
	}
	$_SESSION['frame_message'] = "Your changes have been saved.";
}
$_SESSION["preferences"] = fetchPreferences();

$pageTitle .= 'Client Flags';

include "frame.html";

echo "<p align=right>";
//echoButton('', 'Edit Menu Order', 'openConsoleWindow("serviceSorderEdit", "services-order-edit.php",400,700)');
//echo "&nbsp;&nbsp;";
echoButton('', 'Save Changes', 'saveChanges()');
echo "<p>";

echo "<form name='flagger' method='POST'>";
if($_SESSION['preferences']['betaBillingEnabled']) {
	echo "<h2>Billing Flags</h2>";
	billingFlagTable();
	echo "<h2>Other Client Flags</h2>";
}
bizFlagTable();
echo "</form>";
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function saveChanges() {
	var field, missingLabels, dupLabels=new Array(), allLabels=new Array();
	
	for(var i=1; field = document.getElementById('flag_title'+i); i++) {
//if(!confirm('hey'))	return;
		var title = jstrim(field.value);
		if(title == '' && (document.getElementById('src_'+i).src.indexOf('art/emptyFlagIcon.jpg') < 0)) 
			missingLabels = "All non-blank flags should have titles";
		else {
			for(var j=0; j < allLabels.length; j++) {
				if(allLabels[j] == title) dupLabels[dupLabels.length] = title;
				else allLabels[j] = title;
			}
		}
	}
	if(dupLabels.length) dupLabels = "There are duplicate titles: "+dupLabels.join(', ');
	else dupLabels = null;
	if(MM_validateForm(missingLabels, "","MESSAGE", dupLabels, "","MESSAGE"))
		document.flagger.submit();
}
</script>
<?
include "frame-end.html";
