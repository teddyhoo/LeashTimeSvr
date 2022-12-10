<?
/* appointment-view.php
*
* Parameters: 
* id - id of appointment to be edited
* if id is negative, do not offer edit/cancel buttons
*/

require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "appointment-fns.php";
include "service-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('va');
extract($_REQUEST);

if(!isset($id)) $error = "Appointment ID not specified.";
else {
	$noEdit = $id < 0;
	$id = abs($id);
	$source = getAppointment($id);
	$package = getPackage($source['packageptr']);
	$packageType = $package['monthly'] ? 'Monthly Recurring' :
	               ($package['onedaypackage'] ? 'One Day' :
	               ($package['enddate'] ? 'Nonrecurring' : 'Weekly Recurring'));
  $source['packageType']	= $packageType;
  $sittersOwnAppt = userRole() != 'p' || $source['providerptr'] == $_SESSION["providerid"];
}

$windowTitle = "View Visit: ($packageType Package)";
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<h2>View Visit <?= $source['highpriority'] && !$source['canceled'] ? '<font color=red>(High Priority)</font>' : '' ?></h2>
<?
$showClientCharge = userRole() == 'o';
/*$showProviderRate = 
	userRole() != 'c'
	&& (userRole() != 'p'
			|| ($source['providerptr'] == $_SESSION["providerid"]));*/
$showProviderRate = 
	userRole() == 'o' ? true : (
	userRole() == 'c' ? false : (
	userRole() == 'p' ? $source['providerptr'] == $_SESSION["providerid"] : (
	userRole() == 'd' ? adequateRights('#pa') : false))); // #pa = Payroll Access
	
	
$otherComp = getOtherCompForAppointment($id);
if($otherComp) $source['cancelcomp'] = $otherComp['compid'];

displayAppointment($source, $showClientCharge, $showProviderRate);
echo "<p>";

$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

if(adequateRights('ea') && !isset($noedit) && !$roDispatcher) {
  echoButton('', "Edit Visit", "document.location.href=\"appointment-edit.php?updateList=$updateList&id=$id\"");
  echo " ";
}
if($roDispatcher || (userRole() == 'p' && $sittersOwnAppt)) {
  if($_SESSION['preferences']['sittersCanRequestVisitCancellation'] && !$noEdit) {
		echoButton('', "Edit Visit", "document.location.href=\"client-request-appointment.php?id=$id&operation=change\"");
		echo " ";
		if(!$source['canceled'])
			echoButton('', "Cancel Visit", "document.location.href=\"client-request-appointment.php?id=$id&operation=cancel\"");
		else 
			echoButton('', "Reactivate Visit", "document.location.href=\"client-request-appointment.php?id=$id&operation=uncancel\"");
		echo " ";
	}
}
echoButton('', "Quit", 'window.close()');
//echo " ";
//echoButton('', "Delete Visit", 'deleteAppointment()', 'HotButton', 'HotButtonDown');
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
<?  ?>
function confirmAndClose() {
	if(true || confirm("Ok to close without saving this veterinarian?")) window.close();
}

function deleteAppointment() {
	document.location.href='deleteAppointment.php?id=<?= $id ?>';
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

</script>
</body>
</html>
