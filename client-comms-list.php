<?
// client-comms-list.php
// for inclusion in the client editor services tab
require_once "comm-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);

// No paging supported because of the mix of requests and messages returned!
$starting = $starting? date('Y-m-d', strtotime($starting)) : '';
$ending = $ending? date('Y-m-d', strtotime($ending)) : '';
if($starting || $ending) {
	if($starting && $ending) $filter = "datetime >= '$starting 00:00:00' AND datetime <= '$ending 23:59:59'";
	else if($starting) $filter = "datetime >=  '$starting 00:00:00'";
	else $filter = "datetime <=  '$ending 23:59:59'";
}
$client = getOneClientsDetails($id);
$client['name'] = $client['clientname'];
$comms = getCommsFor($client, $filter);
$numFound = count($comms);
$searchResults = ($numFound ? $numFound : 'No')." message".($numFound == 1 ? '' : 's')." found.  ";

?>
<form name='messagelistform'>
<? 
echoButton('showMessages', 'Show', 'searchForMessages()');
echo " ";
calendarSet('Between:', 'msgsstarting', $starting, null, null, true, 'msgsending');
echo "&nbsp;";
$ending = $ending ? $ending : date('m/d/Y');
calendarSet('and:', 'msgsending', $ending);
echo " \n";

//$useGraphicCommButtons = staffOnlyTEST() || $_SESSION['preferences']['enableMessageArchiveFeature'] || $_SESSION['preferences']['enableSitterProfiles'] || $_SESSION['preferences']['enableClientFilesFeatures'];
$useGraphicCommButtons = TRUE; // enableClientFilesFeatures enabled 12/6/2018
if($useGraphicCommButtons) {
	$localprefs = $_SESSION['preferences'];
	$showSMSButton = $_SESSION['preferences']['enableSMS'] && $_SESSION['preferences']['enableClientSMS'] ? 1 : 0;// mattOnlyTEST();
//if(mattOnlyTEST()) echo "[[[{$_SESSION['preferences']['enableClientSMS']}]]]";	
	$buttonCount = 5 + $showSMSButton; // changed from 4 when enableClientFilesFeatures enabled 12/6/2018
	$notMatt = !mattOnlyTEST();
	$notMatt = FALSE;
	foreach(explode(',', 'enableSitterProfiles,enableMessageArchiveFeature') as $k) 
		if($localprefs[$k])
			$buttonCount += 1;
	$hspace = round(6 * 18 / $buttonCount);
	$buttonSpacer = "<img src='art/spacer.gif' width=$hspace height=35>";
	$buttonStyle = " class='fa fa-envelope-o fa-3x' style='display:inline;width:27;height:27;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
	echo '<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';	
	echo "$buttonSpacer<div class='fa fa-envelope-o fa-2x' $buttonStyle title='Compose email' onclick='openComposer()'></div>";
	if(/*$notMatt && */$localprefs['enableSitterProfiles']) 
		echo "$buttonSpacer<div class='fa fa-user fa-2x' $buttonStyle title='Send sitter profile'
					onclick='openConsoleWindow(\"emailcomposer\", \"sitter-profile-email.php?client=$id\",500,500);'></div>";
	echo "$buttonSpacer<div class='fa fa-envelope-square fa-2x' $buttonStyle title='Log email message' onclick='openLogger(\"email\")'></div>";

	echo "$buttonSpacer<div class='fa fa-phone-square fa-2x' $buttonStyle title='Log phone call' onclick='openLogger(\"phone\")'></div>";

	
	
if(mattOnlyTEST()) {
	echo "$buttonSpacerXXX<span class='fa-layers' $buttonStyle title='Log text message' onclick='openLogger(\"text\")'>
	   <i class='fas fa-square'></i>
	   <i class='fas fa-mobile fa-inverse' data-fa-transform='shrink-6'></i>
	</span>";
}
	

	echo "$buttonSpacer<div class='fa fa-cog fa-2x' $buttonStyle title='Communication preferences' 
					onclick='document.location.href=\"comm-prefs-client.php?id=$id\"'></div>";
					
	if($showSMSButton) { // $showSMSButton
		echo "$buttonSpacer<div class='fa fa-mobile fa-2x' $buttonStyle title='View Mobile Message Stream' 
				onclick=\"openConsoleWindow('smsview', 'sms-view.php?client=$id',750,650)\">
				</div>";
	}
					
	if($localprefs['enableMessageArchiveFeature']) {
		echo "$buttonSpacer<div class='fa fa-archive fa-2x' $buttonStyle title='View Archived Email' 
				onclick='document.location.href=\"reports-email-archived.php?clientid=$id\"'></div>";
	}
	if(TRUE) {  // enableClientFilesFeatures enabled 12/6/2018
		echo "$buttonSpacer<div class='fa fa-file fa-2x' $buttonStyle title='View Client Documents' 
				onclick='openConsoleWindow(\"clientdocs\", \"client-files.php?id=$id\",700,700);'></div>";
	}
	if(tableExists('tblreminder')) {
		echo "$buttonSpacer";
		$reminderButton = echoButton('', 'Reminders', "document.location.href=\"reminders.php?client=$id\"", 'SmallButton', 'SmallButtonDown', 0, 'Edit automatic reminders');
		echo " ";
	}
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
}*/
else {
	echo "<img src='art/spacer.gif' height=25 width=1>";
	echo " ";
	echoButton('showMessages', 'Compose Email', 'openComposer()');
	echo " ";
	echoButton('showMessages', 'Log Email', 'openLogger("email")');
	echo " ";
	echoButton('showMessages', 'Log Call', 'openLogger("phone")');
	echo " ";
	if(tableExists('tblreminder')) {
		$reminderButton = echoButton('', 'Reminders', "document.location.href=\"reminders.php?client=$id\"", 'SmallButton', 'SmallButtonDown', 0, 'Edit automatic reminders');
		echo " ";
	}
	fauxLink('Comm Preferences', "document.location.href=\"comm-prefs-client.php?id=$id\"", false, 'Go to the Client Communications Preferences page.');
}
?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";


echo "</tr></table>";
?>

<p>
<div style='background:white;border: solid black 1px'>
<?
commsTable($comms, $sort);
?>
</div>