<?
/* appointment-view-mobile.php
*
* Parameters: 
* id - id of appointment to be edited
*/

require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "appointment-fns.php";
include "service-fns.php";
include "key-fns.php";
require_once "preference-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$noWindowOpen = '/Windows CE|Windows Phone/i';
$noWindowOpen = preg_match($noWindowOpen, $_SERVER['HTTP_USER_AGENT']);

// Verify login information here
locked('va');
extract($_REQUEST);

if(!isset($id)) $error = "Appointment ID not specified.";

else {
	$source = getAppointment($id, 'withNames');
	$source['arrived'] = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = $id AND event = 'arrived' LIMIT 1");
}
if($_SESSION['preferences']['providersScheduleRetrospectionLimit']) {
	$earliestDateAllowed = strtotime("-{$_SESSION['preferences']['providersScheduleRetrospectionLimit']} days", strtotime(date('Y-m-d')));
	$tooEarly = strtotime(date('Y-m-d', strtotime($source['date']))) < $earliestDateAllowed;
	if($tooEarly) {
		$error = "Visits before ".shortNaturalDate($earliestDateAllowed)." are not viewable.";
		$source = null;
	}
}
$pageIsPrivate = true;	
require "mobile-frame.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
  exit;
}

$now = date('Y-m-d H:i:s');
//echo "$date {$appt['endtime']}  : $now ";exit;	
	$rowClass = $source['canceled'] ? 'canceledtask' : (
							$source['completed'] ? 'completedtask' : (
							strcmp("{$source['date']} {$source['endtime']}", $now) < 0 ? 'noncompletedtask' : ''));
//echo "{$appt['date']} {$appt['endtime']} : $now";
$visitStatus = 
	$source['canceled'] ? ": <span class='warning'>Canceled</span>" : (
	$source['completed'] ? ": Completed" : (
	strcmp("{$source['date']} {$source['endtime']}", $now) < 0 ? "
	: <span class='noncompletedtask'>Unreported</span>" : ''));
if(!$source['completed'] && !$source['canceled'] && !$source['arrived'] && date('Y-m-d') == $source['date']
		&& $_SESSION['preferences']['mobileOfferArrivedButton']) {
		$arrivalButton = "<img id='arrivedbutton' src='art/arrivedbutton.gif' onclick='arrived($id)' style='vertical-align:top;padding-left:3px'> ";
}
$tod = str_replace(' ', '&nbsp;', str_replace('-', '<br>', $source['timeofday']));

//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<style>
.labelcell {
  Xfont-size: 1.08em; 
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
  background: #FF8B00;
  color:black;
  font-weight: bold;
}
.dataCell {
  Xfont-size: 1.08em; 
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
}
.flagLegend {}

</style>
<span style='vertical-align:top;font-size:1.5em;font-weight:bold;'><?= $arrivalButton ?>Visit<?=  $visitStatus ?> <?= $source['highpriority'] && !$source['canceled'] ? '<font color=red>(High Priority)</font>' : '' ?></span>
<table style='width:100%' >
<tr><td style='vertical-align:top' onclick="document.location.href='index.php?date=<?= $source['date'] ?>'"><? echo todaysDateTable($source['date'], 'width:40px;z-index:999;color:black;' // position:absolute;right:10px;top:95px;
																			.'font-family: arial, sans-serif, helvetica;'
		 																	.'border-color:brown;'
		 																	.'border-top:solid brown 1px;border-left:solid brown 1px;'); ?>
</td>
<td style='vertical-align:top;font-size:1.8em;padding-top:0px;'><?= $tod ?></td>
<td style='vertical-align:top;padding-top:0px;'>
<?
if(!$source['canceled'] && !$source['completed'] && strcmp($source['date'], date('Y-m-d', strtotime('tomorrow'))) < 0) { // disallow future visits?
	echo "<img src='art/accepted_70X29.png' onclick='visitAction(\"complete\", $id, 0)'>";
}

// ICONS: http://www.iconfinder.com/browse/iconset/function_icon_set/#readme 
// Function icons by Liam McKay License: Free for commercial use (Include link to package)
if($_SESSION['preferences']['sittersCanRequestVisitCancellation']) {
	echo "<p style='font-size:0.5em;'>";
	echo "<img src='art/paper_content_pencil_24.png' onclick='visitAction(\"change\", $id, 0)'> ";
	echo "<img src='art/spacer.gif' width=15 height=1>";
	if(!$source['canceled'])
		echo "<img src='art/cancel_24.png' onclick='visitAction(\"cancel\", $id, 0)'> ";
	else 
		echo "<img src='art/add_24.png' onclick='visitAction(\"uncancel\", $id, 0)'> ";
	echo "</p>";
}

if(getUserPreference($_SESSION["auth_user_id"], 'postcardsEnabled') == 1 // ... and not 'selected'
		&& !getClientPreference($source['clientptr'], 'noPostcards')
	) {
	echo "<img src='art/spacer.gif' width=15 height=1>";
	if($noWindowOpen) echo
		"<a href='postcard-composer-mobile.php?clientid={$source['clientptr']}&visit=$id&sametab={$id}'>
			<img border=0 src='art/comm-compose-email.png'></a>"; // 'art/postcard-button.jpg'
	else echo "<img src='art/comm-compose-email.png' onclick='openPostcard()'> "; // 'art/postcard-button.jpg'
}
$canDoSMS = $_SESSION["preferences"]['enableSMS'] && $_SESSION['preferences']['enableClientSMS'];
$canDoSMS = $canDoSMS && $_SESSION['preferences']['enableSitterToClientSMS'];
//if(loginidsOnlyTEST('dlifebeth')) echo "canDoSMS [$canDoSMS]";
if($canDoSMS) {
	echo "<img src='art/spacer.gif' width=0 height=1>";
	echo
			"<a href='sms-prov-client.php?appt={$id}'><img border=0 src='art/comm-send-sms.png'></a>";
}
?>
</td>
</tr>
</table>
<table style='width:100%'>
<?
$pets = $source['pets'];
if($source['pets'] == 'All Pets') {
	require_once "pet-fns.php";
	$pets = getClientPetNames($source['clientptr']);
	$pets = $pets ? $pets : 'All Pets';
}
$pets = $pets ? " (<span class='petfont'>$pets</span>)" : '';
$useKeyDescriptions = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'];
$key = fetchFirstAssoc("SELECT * FROM tblkey WHERE clientptr = {$source['clientptr']}");
$noKeyNeeded = fetchRow0Col0("SELECT nokeyrequired FROM tblclient WHERE clientid = {$source['clientptr']}");		 
if($key) {
	$haveKey = in_array($source['providerptr'], keyProviders($key));
	$keyLabel = $useKeyDescriptions	? $key['description'] : '';
	$keyLabel = $keyLabel ? $keyLabel : (
							$haveKey ? formattedProviderKeyId($key, $source['providerptr']) : "#{$key['keyid']}");
	if($source['providerptr'] && !$haveKey)	
		$keyLabel = noKeyIcon($source['clientptr'])." You need key $keyLabel for client.";
}
else if($noKeyNeeded) $keyLabel = 'No key required.';
else $keyLabel = noKeyIcon($source['clientptr'])." No Key found for client.";
	
	
	


if($id && $_SESSION["flags_enabled"]) {
	require_once "client-flag-fns.php";
	//$flagPanel = clientFlagPanel($source['clientptr'], $officeOnly=true);
	$flagPanel = clientFlagPanel($source['clientptr'], $officeOnly=true, $noEdit=false, $contentsOnly=false, $onClick='showFlagLegend()');
	$flagLegend = clientFlagLegend($source['clientptr'], $officeOnly=false, $class='flagLegend', $style=null);
	$start = strpos($flagLegend, 'COUNT[')+strlen('COUNT[');
	$flagCount = $flagLegend ? substr($flagLegend, $start, strpos($flagLegend, ']', $start)-$start) : 0;
	$pagingInUse = $flagCount > 6;

	if($pagingInUse) {
		require_once "js-gui-fns.php";
		$flagLegend = "<div style='width:280px;height:90px;overflow:scroll;display:block;'>$flagLegend</div>";
		ob_start();
		ob_implicit_flush(0);
		pagingBox($flagLegend);
		$flagLegend = str_replace("\n", ' ', addslashes(ob_get_contents()));
		ob_end_clean();
	}

}

oneByTwoLabelRows("Client $flagPanel", 'client', "{$source['client']}$pets", 'labelcell', 'dataCell', $rowId=null,  $rowStyle=null, $rawValue=true);

$currPackage = findCurrentPackageVersion($source['packageptr'], $source['clientptr'], $source['recurringpackage']);
$table = $source['recurringpackage'] ? 'tblrecurringpackage' : 'tblservicepackage';
$currPackageNote =  fetchRow0Col0("SELECT notes FROM $table WHERE packageid = $currPackage LIMIT 1");

if($currPackageNote)
	oneByTwoLabelRows('Schedule Note', 'note', $currPackageNote, 'labelcell', 'dataCell', $rowId=null,  $rowStyle=null, $rawValue=true);
if($source['note'])
	oneByTwoLabelRows('Note', 'note', $source['note'], 'labelcell', 'dataCell', $rowId=null,  $rowStyle=null, $rawValue=true);
oneByTwoLabelRows("Key", 'client', $keyLabel, 'labelcell', 'dataCell', $rowId=null,  $rowStyle=null, $rawValue=true);
oneByTwoLabelRows('Sitter', 'provider', "{$source['provider']}", 'labelcell', 'dataCell');
$service = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$source['servicecode']}");
oneByTwoLabelRows('Service Type', 'service', $service, 'labelcell', 'dataCell');
$rateBonus = dollarAmount($source['rate']).' / '.dollarAmount($source['bonus']);
oneByTwoLabelRows('Rate / Bonus', 'ratebonus', $rateBonus, 'labelcell', 'dataCell', $rowId=null,  $rowStyle=null, $rawValue=true);
?>
</table>
<?
echo "<form>"; // used by getCoords() and arrived()
hiddenElement('lat', null);
hiddenElement('lon', null);
hiddenElement('speed', null);
hiddenElement('heading', null);
hiddenElement('accuracy', null);
hiddenElement('geoerror', null);
echo "</form>";


$showClientCharge = userRole() == 'o';
$showProviderRate = userRole() != 'c';
$otherComp = getOtherCompForAppointment($id);
if($otherComp) $source['cancelcomp'] = $otherComp['compid'];

echo "<p>";

/*$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

if(adequateRights('ea') && !isset($noedit) && !$roDispatcher) {
  echoButton('', "Edit Visit", "document.location.href=\"appointment-edit.php?updateList=$updateList&id=$id\"");
  echo " ";
}
if($roDispatcher || userRole() == 'p') {
  if($_SESSION['preferences']['sittersCanRequestVisitCancellation']) {
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
*/
?>
</div>


<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="jquery.busy.js"></script> 	
<script type="text/javascript">jQuery().busy("defaults", { img: 'art/busy.gif', offset : 0, hide : false });</script> 	
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>

<? $mobileVisitActionJavascript =	loginidsOnlyTEST('shinego,koxford,joshslade,testbenball,dlifebeth,tablet3,sgnote2,apple5') ? "mobile-visit-actionV2.js" : "mobile-visit-action.js"; ?>
<script type="text/javascript" src="<?= $mobileVisitActionJavascript ?>"></script>
<? if($pagingInUse) {
			dumpPagingBoxStyle();
			dumpPagingBoxJS('includescripttags');
	}
?>	
<script language='javascript'>
var arrivedDone = false; // avoid double-tapping
function update(x) {
	document.location.href='<?= $_SERVER["REQUEST_URI"] ?>';
}	

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function openPostcard() {
	openConsoleWindow('postcard', "postcard-composer-mobile.php?clientid=<?= $source['clientptr'] ?>&visit=<?= $id ?>", 500, 500);
}

function showFlagLegend() {
	$.fn.colorbox({	html: "<?= $flagLegend ?>",	width:"280", height:"200", iframe:false, scrolling: "auto", opacity: "0.3"});
}

</script>
</body>
</html>
