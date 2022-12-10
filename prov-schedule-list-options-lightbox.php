<?
// prov-schedule-list-options-lightbox.php
require_once "prov-schedule-fns.php";
/* args:
provui: if 1, controls display in provider UI
*/


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
//require_once "field-utils.php";
//require_once "provider-fns.php";
require_once "preference-fns.php";

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox

if(!$_REQUEST['provui']) {
	$roleView = "Manager's View";
	$props = getUserPreferences($_SESSION['auth_user_id']);
	if($_POST) {
		if($_REQUEST['setforallmanagers']) {
			list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
			include "common/init_db_common.php";
			// find all owners and dispatchers for this business
			$userids = fetchCol0("SELECT userid 
														FROM tbluser 
														WHERE bizptr = {$_SESSION["bizptr"]} 
															AND (rights LIKE 'o-%' OR rights LIKE 'd-%')", 1);
			// AND ltstaffuserid > 0
			reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);
			//echo "BANG!";print_r($userids);exit;
			foreach($userids as $userid) {
				setUserPreference($userid, 'provsched_client', $_POST['provsched_client']);
				setUserPreference($userid, 'provsched_hidephone', ($_POST['provsched_hidephone'] ? 0 : 1));
				setUserPreference($userid, 'provsched_hideaddress', ($_POST['provsched_hideaddress'] ? 0 : 1));
				setUserPreference($userid, 'provsched_start', $_POST['provsched_start']);
			}
		}
		else {
			setUserPreference($_SESSION['auth_user_id'], 'provsched_client', $_POST['provsched_client']);
			setUserPreference($_SESSION['auth_user_id'], 'provsched_hidephone', ($_POST['provsched_hidephone'] ? 0 : 1));
			setUserPreference($_SESSION['auth_user_id'], 'provsched_hideaddress', ($_POST['provsched_hideaddress'] ? 0 : 1));
			setUserPreference($_SESSION['auth_user_id'], 'provsched_start', $_POST['provsched_start']);
		}

		echo "<script language='javascript'>if(parent.searchForAppointments) parent.searchForAppointments();parent.$.fn.colorbox.close();</script>";
	}
}
else {
	$roleView = "Sitter's View";
	foreach(explode(',', 'client,hidephone,hideaddress,start,hidepay') as $prop)
		$props["provsched_$prop"] = $_SESSION['preferences']["provuisched_$prop"];
	if($_POST) {
		setPreference('provuisched_client', $_POST['provsched_client']);
		setPreference('provuisched_hidephone', ($_POST['provsched_hidephone'] ? 0 : 1));
		setPreference('provuisched_hideaddress', ($_POST['provsched_hideaddress'] ? 0 : 1));
		setPreference('provuisched_start', $_POST['provsched_start']);
		setPreference('provuisched_hidepay', ($_POST['provsched_hidepay'] ? 0 : 1));

		echo "<script language='javascript'>parent.$.fn.colorbox.close();</script>";
	}
}

include "frame-bannerless.php";
?>

<h2>Sitter Schedule List Properties <span style='font-size:0.8em'>(<?= $roleView ?>)</span></h2>
<form method='POST' name='provschedprops'>
<?
hiddenElement('provui', $_REQUEST['provui']);
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
<b>Specify settings...</b>
<?  
if(!$_REQUEST['provui'] && mattOnlyTEST()) {
	labeledCheckbox('for ALL office staff', 'setforallmanagers', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title='Apply these stiings to ALL office staff');
}
?>
<table>
<?
$options = array('Client name'=>'fullname', 
									'Last name (Pets)'=>'name/pets', 
									'Pets (Last name)'=>'pets/name', 
									'Client name (Pets)'=>'fullname/pets'); 
$choice = $props['provsched_client'] ? $props['provsched_client'] : 'fullname';
radioButtonRow('Client Column:', 'provsched_client', $choice, $options, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
checkboxRow('Show Phone Number Column:', 'provsched_hidephone', !$props['provsched_hidephone']);
checkboxRow('Show Address Column:', 'provsched_hideaddress', !$props['provsched_hideaddress']);
$options = array('Show Start and End Times'=>'timeofday', 
									'Show Start Time Only'=>'starttime'); 
$choice = $props['provsched_start'] ? $props['provsched_start'] : 'timeofday';
radioButtonRow('Time Column:', 'provsched_start', $choice, $options);
if($_REQUEST['provui']) checkboxRow('Show Pay Column:', 'provsched_hidepay', !$props['provsched_hidepay']);
?>
</table>
<p>
<b>... or choose:</b><p>
<?
$showPayColumn = $_REQUEST['provui'] ? "<td>$ 9.00" : '';
$exampleColor = 'gainsboro';
echoButton('', 'Classic Layout', 'classicLayout()');
?>
<table bgcolor=<?= $exampleColor ?> border=1 bordercolor=black><tr>
<td>John Smith<td>703-678-2987<td>235 Montrose Avenue Apt 12<td>Dog Walk - 15 minutes<td>11:00 am-1:00 pm<?= $showPayColumn ?>
</tr></table>
<p>
<?
echoButton('', 'Pets-First Layout', 'petsFirstLayout()');
?>
<table bgcolor=<?= $exampleColor ?> border=1 bordercolor=black><tr>
<td>Rex, Queenie, Jack (Smith)<td>703-678-2987<td>235 Montrose Avenue Apt 12<td>Dog Walk - 15 minutes<td title='11:00 am-1:00 pm'>11:00 am<?= $showPayColumn ?>
</tr></table>
<p>
<?
echoButton('', 'Streamlined Layout', 'streamlinedLayout()');
?>
<table bgcolor=<?= $exampleColor ?> border=1 bordercolor=black><tr>
<td>Rex, Queenie, Jack (Smith)<td>Dog Walk - 15 minutes<td title='11:00 am-1:00 pm'>11:00 am<?= $showPayColumn ?>
</tr></table>
</form>
<script language='javascript'>
var hidepayEl = document.getElementById('provsched_hidepay');

function classicLayout() {
	document.getElementById('provsched_client_fullname').checked = true;
	document.getElementById('provsched_start_timeofday').checked = true;
	document.getElementById('provsched_hidephone').checked = true;
	document.getElementById('provsched_hideaddress').checked = true;
	if(hidepayEl) hidepayEl.checked = true;
	alert('Click "Save Preferences" to finish.');
}

function petsFirstLayout() {
	document.getElementById('provsched_client_pets/name').checked = true;
	document.getElementById('provsched_start_starttime').checked = true;
	document.getElementById('provsched_hidephone').checked = true;
	document.getElementById('provsched_hideaddress').checked = true;
	if(hidepayEl) hidepayEl.checked = true;
	alert('Click "Save Preferences" to finish.');
}

function streamlinedLayout() {
	document.getElementById('provsched_client_pets/name').checked = true;
	document.getElementById('provsched_start_starttime').checked = true;
	document.getElementById('provsched_hidephone').checked = false;
	document.getElementById('provsched_hideaddress').checked = false;
	if(hidepayEl) hidepayEl.checked = true;
	alert('Click "Save Preferences" to finish.');
}

function save() {
	document.provschedprops.submit();
}
</script>