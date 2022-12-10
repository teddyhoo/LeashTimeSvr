<?
// inbound-comms-list.php
// accessed via Inbound Comms page
require_once "comm-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);
$includerequests = explode(',', "$includerequests");
// No paging supported because of the mix of requests and messages returned!
$starting = $starting? date('Y-m-d', strtotime($starting)) : '';
$ending = $ending? date('Y-m-d', strtotime($ending)) : '';
if($starting || $ending) {
	if($starting && $ending) $filter = "datetime BETWEEN '$starting 00:00:00' AND '$ending  23:59:59'";
	else if($starting) $filter = "datetime >=  '$starting 00:00:00'";
	else $filter = "datetime <=  '$ending  23:59:59'";
}
$correspondents = $correspondents ? $correspondents : 'CLIENTSANDSITTERS';
$comms = getCommsFor($correspondents, $filter, false, $totalMsg=false, $postFilterClause='', $inboundOnly=true);
$filteredComms = array();
foreach($comms  as $comm) {
	if(!$comm['requesttype'] || in_array($comm['requesttype'], $includerequests))
		$filteredComms[] = $comm;
}
$comms = $filteredComms;

$numFound = count($comms);
$searchResults = ($numFound ? $numFound : 'No')." message".($numFound == 1 ? '' : 's')." found.  ";
?>
<form name='messagelistform'>
<? 

echoButton('showMessages', 'Show', 'searchForMessages()');
echo " \n";
calendarSet('Between:', 'msgsstarting', $starting, null, null, true, 'msgsending');
echo "&nbsp;";
$ending = $ending ? $ending : date('m/d/Y');
calendarSet('and:', 'msgsending', $ending);
echo " \n";
$options = array('Clients'=>'CLIENTS', 'Sitters'=>'SITTERS', 'Both'=>'CLIENTSANDSITTERS');
echo "<img src='art/spacer.gif' width=15>";
echo "For: ".join(' ', radioButtonSet('correspondents', $correspondents, $options, $onClick=null, $labelClass=null, $inputClass=null, $rawLabel=false));
echo "<img src='art/spacer.gif' width=15>";
//labeledCheckbox("Include requests...", 'hideRequests', $hideRequests, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title='Show only non-Request messages');
echo "<span onclick='$(\"#requesttypesdiv\").toggle()' style='text-decoration: underline;'>Include requests...</span>
<div id='requesttypesdiv' style='display:none;background:palegreen'>";
fauxLink('Select All', 'selectAlRequestTypes(1)');
echo ' - ';
fauxLink('Deselect All', 'selectAlRequestTypes(0)');
echo "<table><tr>";
require_once "request-fns.php";
asort($requestTypes);
$chunks = 4;
$chunks = count($requestTypes) / $chunks + ( count($requestTypes) % $chunks ? 1 : 0);
$chunks = array_chunk($requestTypes, $chunks, $preserve_keys=true);

foreach($chunks as $chunk) {
	echo "<td style='vertical-align:top;'>";
	foreach($chunk as $rtype => $label) {
//		echo "[$label] ['includerequests[]'] [$rtype] [$includerequests]<br>";
		$checked = in_array($rtype, $includerequests);
		labeledCheckboxWithId($label, 'includerequests', $rtype, $checked, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title='');
		echo "<br>";
	}
	echo "</td>";
}
//print_r($requestTypes);
echo "</tr></table></div>";


$useGraphicCommButtons = staffOnlyTEST() || $_SESSION['preferences']['enableMessageArchiveFeature'] || $_SESSION['preferences']['enableSMS'];
if(FALSE && $useGraphicCommButtons) {
	$showSMSButton = $_SESSION['preferences']['enableSMS'] ? 1 : 0;
	$showMessageArchiveButton = $_SESSION['preferences']['enableMessageArchiveFeature'] ? 1 : 0;
	$numButtons = 4 + $showSMSButton + $showMessageArchiveButton;
	$spacerWidth = ($numButtons + 2 - 4) * 5;
	$buttonSpacer = "<img src='art/spacer.gif' width=$spacerWidth height=35>";
	$buttonStyle = " class='fa fa-envelope-o fa-3x' style='display:inline;width:27;height:27;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
	echo '<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';	
	echo "$buttonSpacer<div class='fa fa-envelope-o fa-2x' $buttonStyle title='Compose email' onclick='openComposer()'></div>";
	echo "$buttonSpacer<div class='fa fa-envelope-square fa-2x' $buttonStyle title='Log email message' onclick='openLogger(\"email\")'></div>";

	echo "$buttonSpacer<div class='fa fa-phone-square fa-2x' $buttonStyle title='Log phone call' onclick='openLogger(\"phone\")'></div>";

	/*echo "$buttonSpacer<div class='fa fa-cog fa-2x' $buttonStyle title='Communication preferences' 
					onclick='document.location.href=\"comm-prefs-provider.php?id=$id\"'></div>";
	if($showSMSButton) {
		echo "$buttonSpacer<div class='fa fa-mobile fa-2x' $buttonStyle title='View Mobile Message Stream' 
				onclick=\"openConsoleWindow('smsview', 'sms-view.php?provider=$id',750,650)\">
				</div>";
	}*/
	
	/* if($showMessageArchiveButton) { // NOT COMPLETELY UPGRADED FOR MANAGERS
		echo "$buttonSpacer<div class='fa fa-archive fa-2x' $buttonStyle title='View Archived Email' 
				onclick='document.location.href=\"reports-email-archived.php?userid=$id\"'></div>";
	}
	
	echo "$buttonSpacer";
	if(tableExists('tblreminder')) {
		$reminderButton = echoButton('', 'Reminders', "document.location.href=\"reminders.php?provider=$id\"", 'SmallButton', 'SmallButtonDown', 0, 'Edit automatic reminders');
		echo " ";
	}
	*/
}
/*else if($useGraphicCommButtons) {
	$buttonSpacer = "<img src='art/spacer.gif' width=9 height=35>";
	$buttonStyle = "width=27 height=27"; //"style='width:5px height:5px;'";
	echo "$buttonSpacer<img src='art/comm-compose-email.png' title='Compose email' onclick='openComposer()' $buttonStyle>";
	if($_SESSION['preferences']['enableSitterProfiles']) 
		echo "$buttonSpacer<img src='art/comm-send-sitter-profile.png' title='Send sitter profile' 
				onclick='openConsoleWindow(\"emailcomposer\", \"sitter-profile-email.php?client=$id\",500,500);' $buttonStyle>";
	echo "$buttonSpacer<img src='art/comm-log-email.png' title='Log email message' onclick='openLogger(\"email\")' $buttonStyle>";
	echo "$buttonSpacer<img src='art/comm-log-phonecall.png' title='Log phone call' onclick='openLogger(\"phone\")' $buttonStyle>";
	echo "$buttonSpacer<img src='art/comm-settings.png' title='Communication preferences ' 
					onclick='document.location.href=\"comm-prefs-client.php?id=$id\"' $buttonStyle>";
	if($_SESSION['preferences']['enableMessageArchiveFeature']) {
		echo "$buttonSpacer<img src='art/comm-log-archive.png' title='View Archived Email' 
			onclick='document.location.href=\"reports-email-archived.php?clientid=$id\"' $buttonStyle>";
	}
	echo "$buttonSpacer";
	if(tableExists('tblreminder')) {
		$reminderButton = echoButton('', 'Reminders', "document.location.href=\"reminders.php?client=$id\"", 'SmallButton', 'SmallButtonDown', 0, 'Edit automatic reminders');
		echo " ";
	}
}

if($_SESSION['preferences']['enableMessageArchiveFeature']) {
	$buttonSpacer = "<img src='art/spacer.gif' width=23 height=1>";
	$buttonStyle = "width=27 height=27"; //"style='width:5px height:5px;'";
	echo "$buttonSpacer<img src='art/comm-compose-email.png' title='Compose email' onclick='openComposer()' $buttonStyle>";
	echo "$buttonSpacer<img src='art/comm-log-email.png' title='Log email message' onclick='openLogger(\"email\")' $buttonStyle>";
	echo "$buttonSpacer<img src='art/comm-log-phonecall.png' title='Log phone call' onclick='openLogger(\"phone\")' $buttonStyle>";
	echo "$buttonSpacer<img src='art/comm-settings.png' title='Communication preferences ' 
					onclick='document.location.href=\"comm-prefs-provider.php?id=$id\"' $buttonStyle>";
	if($_SESSION['preferences']['enableMessageArchiveFeature']) {
		echo "$buttonSpacer<img src='art/comm-log-archive.png' title='View Archived Email' 
			onclick='document.location.href=\"reports-email-archived.php?providerid=$id\"' $buttonStyle>";
	}
	echo "$buttonSpacer";
	if(tableExists('tblreminder')) {
		$reminderButton = echoButton('', 'Reminders', "document.location.href=\"reminders.php?provider=$id\"", 'SmallButton', 'SmallButtonDown', 0, 'Edit automatic reminders');
		echo " ";
	}
}*/




?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";


echo "</tr></table>";
?>

<p>
<div style='background:white;border: solid black 1px'>
<?
$sort= $sort ? $sort : 'date_desc';
inboundCommsTable($comms, $sort);
?>
</div>