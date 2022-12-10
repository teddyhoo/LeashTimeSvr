<? // client-own-comms.php
// NOTE - thgis shows only log messages, not requests, because request-edit.php
//  is a big hairy beast that probably needs refactoring before we can substitute in
// a "request-view.php" alternative in the comms list subject links.

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "client-fns.php";
require_once "comm-fns.php";
require_once "preference-fns.php";

// Determine access privs
$locked = locked('c-');

$max_rows = 100;

extract($_REQUEST);
$id = $_SESSION["clientid"];

if($starting) {
	$starting = $starting? date('Y-m-d', strtotime($starting)) : '';
	$ending = $ending? date('Y-m-d', strtotime($ending)) : '';
	if($starting || $ending) {
		if($starting && $ending) $filter = "datetime >= '$starting 00:00:00' AND datetime <= '$ending 23:59:59'";
		else if($starting) $filter = "datetime >=  '$starting 00:00:00'";
		else $filter = "datetime <=  '$ending 23:59:59'";
	}
	$client = getOneClientsDetails($id);
	$client['name'] = $client['clientname'];
	$typeLabels = explodePairsLine("Profile|Profile Change Request||cancel|Cancellation Request||uncancel|Un-Cancelation Request||change|Visit Change Request||Schedule|Schedule Request||General|General Request");
//getCommsFor($correspondent, $filter, $clientflg=true, $totalMsg=false, $postFilterClause='', $inboundOnly=false, $excludeHidden=false) {
	$comms = getCommsFor(
							$client, $filter, reur, false, 
							"AND requesttype IN ('".join("','", array_keys($typeLabels))."')", 
							$inboundOnly=false, $excludeHidden=true);
	//print_r($comms);
	//foreach($comms as $i=>$item)
	//	if(strpos($item['subject'], 'msgviewer') === FALSE)
	//		unset($comms[$i]);	
	$numFound = count($comms);
	$searchResults = ($numFound ? $numFound : 'No')." message".($numFound == 1 ? '' : 's')." found.  ";

	?>
	<form name='messagelistform'>
	<? 
	hiddenElement('client', $id);
	//echoButton('showMessages', 'Show', 'searchForMessages()');
	//echo " ";
	calendarSet('From:', 'msgsstarting', $starting, null, null, true, 'msgsending', '', null, null, 'jqueryversion');
	echo "&nbsp;";
	$ending = $ending ? $ending : date('m/d/Y');
	calendarSet('to:', 'msgsending', $ending, null, null, true, null, '', null, null, 'jqueryversion');
	echo "<i style='margin-left:9px;color:gray;' title='Show visits for these dates.' id='showAppointments' onclick='searchForMessages()' class=\"fa fa-search fa-2x\" aria-hidden=\"true\"></i>";
//calendarSet('through:', 'ending', $ending, null, null, true, null, '', null, null, 'jqueryversion');
	
	echo " \n";

	$useGraphicCommButtons = true;
	if($useGraphicCommButtons) {
		$localprefs = $_SESSION['preferences'];
		$buttonCount = 1;
		$notMatt = !mattOnlyTEST();
		$hspace = round(18);
		//$buttonSpacer = "<img src='art/spacer.gif' width=$hspace height=35>";
		//$buttonStyle = " class='fa fa-envelope-o fa-3x' style='display:inline;width:27;height:27;color:gray;cursor:pointer;'"; //"style='width:5px height:5px;'";
		//echo "$buttonSpacer<div class='fa fa-envelope-o fa-2x' $buttonStyle title='Compose email' onclick='openComposer()'></div>";
		//if(dbTEST('dogslife,tonkatest')) echo "$buttonSpacer<div class='fa fa-picture-o fa-2x' $buttonStyle title='View Pet Gallery' onclick='document.location.href=\"gallery1.php\"'></div>";
		$buttonLooks = "margin-left:9px;color:gray;cursor:pointer;";
		$buttonStyle = "style=$buttonLooks'"; //"style='width:5px height:5px;'";
		$buttonClass = " class='fa fa-envelope-o fa-2x'";
		echo "<div $buttonClass $buttonStyle title='Compose email' onclick='openComposer()'></div>";
		echo '<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';	
		$buttonClass = " class='fa fa-picture-o fa-2x'";
		//if(dbTEST('dogslife,tonkatest')) echo "<div $buttonClass $buttonStyle title='View Pet Gallery' onclick='document.location.href=\"gallery1.php\"'></div>";
		if($localprefs['offerPhotoGalleryToClients']) echo "<div $buttonClass $buttonStyle title='View Pet Gallery' onclick='document.location.href=\"gallery1.php\"'></div>";
	}
	else {
		echo "<img src='art/spacer.gif' height=25 width=1>";
		echo " ";
		echoButton('compose', 'Compose Email', 'openComposer()');
		echo " ";
		echoButton('logEmail', 'Log Email', 'openLogger("email")');
		echo " ";
		echoButton('logCall', 'Log Call', 'openLogger("phone")');
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


	echo "</tr></table><p><div style='background:white;border: solid black 1px'>";
	if($numFound) commsTable($comms, $sort);
	echo "</div>";
	exit;
}

$pageTitle = "Messages";

if($_SESSION["responsiveClient"]) {
	$pageTitle = "<i class=\"fa fa-inbox\"></i> Messages";
	$extraHeadContent = "<style>body {font-size:1.2em;} .tiplooks {font-size:14pt;}</style>";
	include "frame-client-responsive.html";
	$frameEndURL = "frame-client-responsive-end.html";
}
else if(userRole() == 'c') {
	$extraHeadContent = <<<COLOR
	<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
	<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
COLOR;
	include "frame-client.html";
	$frameEndURL = "frame-end.html";
}



?>
<div id='clientmsgs'></div>
<img src='art/spacer.gif' height=300>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<!-- script language='javascript' src='popcalendar.js'></script -->
<script language='javascript'>

function searchForMessages() {
	searchForMessagesWithSort('');
}

function sortMessages(field, dir) {
	searchForMessagesWithSort(field+'_'+dir);
}


//setPrettynames('msgsstarting,Starting date for messages,msgsending,Starting date for messages');
function searchForMessagesWithSort(sort) {
	
  if(MM_validateForm(
		  'msgsstarting', '', 'isDate',
		  'msgsending', '', 'isDate')) {
		var starting = document.getElementById('msgsstarting').value;
		var ending = document.getElementById('msgsending').value;
		doSearch(starting, ending, sort);
	}
}

function doSearch(starting, ending, sort) {
		starting = starting ? '&starting='+starting : '';
		ending = ending ? '&ending='+ending : '';
		sort = sort ? '&sort='+sort : '';
		var url = 'client-own-comms.php';
		$.ajax({
				url: url+'?x=1'+starting+ending+sort,
				//dataType: 'json', // comment this out to see script errors in the console
				type: 'post',
				//contentType: 'application/json',
				//data: JSON.stringify(data),
				processData: false,
				success: handleSearchAjaxResults,
				error: <?= mattOnlyTEST() ? 'submitFailed' : 'handleSearchAjaxResults' // until I figure this out...Figured it out! ?>
				});
}

function handleSearchAjaxResults(data, textStatus, jQxhr) {
	$('#clientmsgs').html(data);
	initializeCalendarImageWidgets();
}

function submitFailed(data, textStatus, jQxhr) {
	let message = 'Error encountered:<br>'
		+<?= mattOnlyTEST() ? 'errorThrown' : '"Please notify support."' ?>;
	console.log(message );
}

function openComposer() {
	document.location.href='client-own-request.php';
}


<? dumpJQueryDatePickerJS(); //dumpPopCalendarJS(); ?>	


d = new Date();
d.setTime(d.getTime()-(30*24*3600*1000));
starting = d.getMonth()+1+'/'+d.getDate()+'/'+d.getFullYear();
//ajaxGet('client-own-comms.php?id=<?= $id ?>&starting='+starting, 'clientmsgs');
doSearch(starting, null, null);
</script>
<?

$onLoadFragments = array("initializeCalendarImageWidgets();"); // , "doSearch('$starting', '$ending')"
require $frameEndURL;

