<? // client-own-schedule-fullcalendar.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "pet-fns.php";

// Determine access privs
$locked = locked('c-');

$max_rows = 100;

extract($_REQUEST);


if(mattOnlyTEST()) {
	require_once "preference-fns.php";
	$_SESSION['preferences'] = fetchPreferences();
}

$USE_MULTI_VISIT_MODS_PAGE = true;


/*if("$clist" == "1") $_SESSION["SHOW_CALENDAR_AS_LIST"] = 1;
if("$clist" === "0") $_SESSION["SHOW_CALENDAR_AS_LIST"] = 0;
$SHOW_CALENDAR_AS_LIST = $_SESSION["SHOW_CALENDAR_AS_LIST"];*/

if($oldframe == 1) $_SESSION["OLDFRAME"] = 1;
if("$oldframe" === "0") $_SESSION["OLDFRAME"] = 0;
$OLDFRAME = $_SESSION["OLDFRAME"];


$client = $_SESSION["clientid"];

/*$starting = shortDate();
$days = $_SESSION['preferences']['clientschedulelookaheaddays'];
$days = $days ? $days : 45;
$ending = shortDate(strtotime("+$days days"));*/


$pageTitle = "<i class=\"md md-home\"></i> Schedule"; // {$_SESSION["clientname"]}'s

}

$clientLoginNotice = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'clientLoginNotice'");
$alreadyDelivered = $_SESSION['clientLoginNoticeDelivered'];
if($clientLoginNotice && !$alreadyDelivered) {
	$_SESSION['clientLoginNoticeDelivered'] = 1;
	if($clientLoginNotice) {
		$clientLoginNotice = json_decode($clientLoginNotice, 'assoc');
		if($clientLoginNotice['props']) {
			require_once "screen-notice-fns.php";
			$template = composeTimelyScreenNotice($clientLoginNotice);
		}
		else {
			$message = "<span class='fontSize1_5em'>".$clientLoginNotice['message']."</span>";
			$template = "<h2>{$clientLoginNotice['title']}</h2>$message";
		}
	}
}
	$_SESSION['user_notice'] = $template;
}	
}

$extraHeadContent = "
<script src='responsiveclient/assets/js/libs/fullcalendar-4.3.1/packages/core/main.js'></script>
<script src='responsiveclient/assets/js/libs/fullcalendar-4.3.1/packages/interaction/main.js'></script>
<script src='responsiveclient/assets/js/libs/fullcalendar-4.3.1/packages/list/main.js'></script>
<script src='responsiveclient/assets/js/libs/fullcalendar-4.3.1/packages/daygrid/main.js'></script>
<script src='responsiveclient/assets/js/libs/fullcalendar-4.3.1/packages/timegrid/main.js'></script>
<link href='fullcalendar-4.3.1/packages/core/main.css' rel='stylesheet' />
<link href='fullcalendar-4.3.1/packages/daygrid/main.css' rel='stylesheet' />
<style>
.COMPLETED {color: black; background: #90EE90}
.INCOMPLETE {color: black; background: #FFFFE0}
.CANCELED {color: gray; background: #FFC0CB;} /* text-decoration:line-through !important; <== not applied uniformly */
.SOON {color: #494949; background: #FEFF99}
input.visitcheckbox {} /* TBD: Charles, pls do something nice with the checkbox look */
.BlockContent a:hover {color: black;}
.fc-list-heading-alt {padding-left:30px;}
.fc-event:hover {color:blue;}
</style>
";

if($OLDFRAME) include "frame-client.html"; else 
include "frame-client-responsive.html";
// ***************************************************************************
?>
<style>
body {font-size:1.2em;}
.pending-requests-table td {font-size: 1.0em;}
#pending-requests {position:absolute;right:5px; top:0px;}
</style>
<!-- script type="text/javascript" src="jquery_1.3.2_jquery.min.js">></script -->
<!-- script type="text/javascript" src="jquery.cycle.2.84.js"></script -->
<!-- script type="text/javascript" src="jquery.easing.1.1.1.js"></script -->

<? 
if(FALSE && $_SESSION['preferences']['postcardsEnabled']) {
	echo "<table><tr><td style='vertical-align:center'>";
	$unreadPostCard = fetchRow0Col0("SELECT cardid FROM tblpostcard WHERE viewed IS NULL AND suppressed IS NULL ORDER BY created DESC LIMIT 1", 1);
	if($unreadPostCard) echo "<a href='postcard-viewer.php?open=$unreadPostCard'><img src='art/postcard-button.jpg' border=0> <img src='art/newpostcard.gif' border=0></a>";
	else echo "<a href='postcard-viewer.php'><img src='art/postcard-button.jpg' border=0> <img src='art/postcards.jpg' border=0></a>";
	
	echo "</td></tr></table>";
	
}
?>

<form name="clientschedform">
<input type="hidden" id="client" name="client" value="<?= $client ?>">
<?
$starting = date('Y-m-01');
$ending = date('Y-m-d', strtotime("+6 months", strtotime($starting)));

if(dbTEST('dogslife,tonkatest')) {
	$starting = date('Y-m-01', strtotime("-3 months"));
	$ending = date('Y-m-d', strtotime("+3 months"));
}

echo "<div class='btn btn-default btn-labeled' id='pending-requests' style='text-align:right;font-size:1.0em;'></div>";  // updated by JSON ajax-pending-requests.php


$modAction = $USE_MULTI_VISIT_MODS_PAGE ? 'multiVisitMods' : 'generateScheduleChangeRequest';

if(TRUE || mattOnlyTEST()) {
	$noChangesAllowed = $_SESSION['preferences']['suppressChangeButtonOnVisits'];
	/*echo "<table><tr><td style='padding-right:10px;'>"
		.echoButton('', "Request Visits", "document.location.href=\"client-sched-makerV3.php\"", 'btn btn-success pull-right ', null, 'noEcho', 'Click here to cancel or change visits.')
		."</td><td>"
		.($noChangesAllowed ? '' 
			 : echoButton('', "Change Visits", "showChangePanel()", 'btn btn-info', null, 'noEcho', 'Click here to cancel or change visits.')
			)
		."</td></tr>"
		."</table>";*/
	echo "<div class='row'>"
		.echoButton('', "Request Visits", "document.location.href=\"client-sched-makerV3.php\"", 'btn btn-success pull-right btn-raised ', null, 'noEcho', 'Click here to cancel or change visits.')
		.($noChangesAllowed ? '' 
			 : echoButton('', "Change/Cancel", "showChangePanel()", 'btn btn-info  pull-right m-r-10', null, 'noEcho', 'Click here to cancel or change visits.')
			)
		."</div>";

	echo "<div id='ChangePanel' style='display:none;text-align:center;'>"
	.echoButton('', "Cancel Visits", "$modAction(\"cancel\")", 'btn btn-danger', null, 'noEcho', 'Click here to cancel selected visits.')
	."<p>&nbsp;</p>"
	.echoButton('', "UnCancel Visits", "$modAction(\"uncancel\")", 'btn btn-success', null, 'noEcho', 'Click to uncancel selected visits.')
	."<p>&nbsp;</p>"
	.echoButton('', "Change Visits", "$modAction(\"change\")", 'btn btn-info', null, 'noEcho', 'Click here to make changes to selected visits.')
	."</div>";
}

else echo "<table><tr><td>"
.echoButton('', "Cancel Visits", "$modAction(\"cancel\")", 'btn btn-danger', null, 'noEcho', 'Click here to cancel selected visits.')
."</td><td>"
.echoButton('', "UnCancel Visits", "$modAction(\"uncancel\")", 'btn btn-success', null, 'noEcho', 'Click to uncancel selected visits.')
."</td><td>"
.echoButton('', "Change Visits", "$modAction(\"change\")", 'btn btn-info', null, 'noEcho', 'Click here to make changes to selected visits.')
."</td></tr>"
."</table>";

echo "<p>&nbsp;</p>";
calendarSet('View:', 'starting', $starting, null, null, true, 'ending', '', null, null, 'jqueryversion');
echo "&nbsp;";
calendarSet('through:', 'ending', $ending, null, null, true, null, '', null, null, 'jqueryversion');
echo " ";
//echoButton('showAppointments', 'Show', 'searchForAppointments(true)'); 
echo '<input type="button" id="" name="" value="GO" class="btn btn-info btn-sm m-r-10" id="showAppointments" onclick="searchForAppointments(true)" title="Show visits for these dates.">'; // pull-right 
//echo "<i style='margin-left:9px;color:gray;' title='Show visits for these dates.' id='showAppointments' onclick='searchForAppointments(true)' class=\"fa fa-search fa-2x\" aria-hidden=\"true\"></i>";
?>

<div id='working' style='margin-left:10px;color:gray;'  class='fa fa-cog fa-spin fa-2x'></div>
<div id='clientappts'> </div>
</form>



<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script src='fullcalendar-4.3.1/packages/core/main.js'></script>
<script src='fullcalendar-4.3.1/packages/interaction/main.js'></script>
<script src='fullcalendar-4.3.1/packages/daygrid/main.js'></script>
<script language='javascript'>


<? if($_SESSION['preferences']['enableclientuimultidaycancel']) { 
require_once "request-safety.php";
dumpClientScheduleChangeJS();

 } ?>



var unresolvedRequests;

function multiVisitMods(action) {
	document.location.href="client-own-multivisit-mods.php?action="+action;
}

function viewPendingRequests() {
	//jQuery.fn.colorbox({html:unresolvedRequests, width:"500", height:"300", scrolling: true, opacity: "0.3"});
	lightBoxHTML(unresolvedRequests, 360, 150);
}

function updatePendingRequests(unused, response) {var el;
	if(!(el = document.getElementById('pending-requests'))) return;
	response = JSON.parse(response);
	if(!response.result || response.result == '' || response.result.numrequests == 0) {
		el.innerHTML = '';
		unresolvedRequests = '';
		$('#pending-requests').css('display','none');
	}
	else {
		// response array('link'=>$link, 'listhtml'=>$unresolvedRequests, 'numrequests'=>count($requests));
		// link has id 'pendingvisitslink'
		el.innerHTML = '<i  class="fa fa-adjust text-danger"></i> <span class="label label-warning">'+response.result.numrequests+'</span>'+response.result.link;
		$('#pendingvisitslink').prop('numrequests', 'pending');
		$('#pendingvisitslink').prop('fullLabel', 'pending requests');
		unresolvedRequests = response.result.listhtml;
		makeWidthAdjustments();
		$('#pending-requests').css('display','block');
	}
	// response will be an array, because it is returned in JSON content type
	//$pendingRequestsLinkAndListResult = pendingRequestsLinkAndList($client);
	//$link = $pendingRequestsLinkAndListResult['link'];
	//$unresolvedRequests = $pendingRequestsLinkAndListResult['listhtml'];
	//ajaxGetAndCallWith("ajax-pending-requests.php", updatePendingRequests, 0);
}

function populatePendingRequests() {
	if(!(el = document.getElementById('pending-requests'))) return;
	ajaxGetAndCallWith("ajax-pending-requests.php", updatePendingRequests, 0);
}

function viewRequest(id) {
	openConsoleWindow('requestviewer', 'request-review.php?id='+id,730,450);
}

function showChangePanel() {
	lightBoxHTML($('#ChangePanel').html(), 200, 150);
}


<? dumpJQueryDatePickerJS(); //dumpPopCalendarJS(); ?>	

var lateCancellationWarning = "<?= $_SESSION['preferences']['cancellationDeadlineWarning'] ?>";

function cancelAppt(id, tooLateToCancel) {
	openConsoleWindow('apptrequest', 'client-request-appointment.php?operation=cancel&id='+id,530,450)
}

function uncancelAppt(id, tooLateToCancel) {
	openConsoleWindow('apptrequest', 'client-request-appointment.php?operation=uncancel&id='+id,530,450)
}

function changeAppt(id) {
	openConsoleWindow('apptrequest', 'client-request-appointment.php?operation=change&id='+id,530,450)
}

function searchForAppointments() {
	$('#working').toggle();
	searchForAppointmentsWithSort('');
}

function searchForAppointmentsWithSort(sort) {
	//if(sort) sort = '&sort='+sort;
  if(MM_validateForm(
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var starting = document.getElementById('starting').value;
		var ending = document.getElementById('ending').value;
		
		performSearch(starting, ending);
	}
}

var before = {
	starting: "<?= date('Y-m-d', strtotime('-6 months', strtotime($starting))) ?>",
	ending: "<?= date('Y-m-d', strtotime('-1 day', strtotime($starting))) ?>"
}
var after = {
	starting: "<?= date('Y-m-d', strtotime('+1 day', strtotime($ending))) ?>",
	ending: "<?= date('Y-m-d', strtotime('+6 months', strtotime($ending))) ?>"
}
var addingMode = false;

var lastHighlightedDate = null;
function performInitialSearch() {
		var starting = '<?= $starting; ?>';
		var ending = '<?= $ending ?>';
		performSearch(starting, ending);
}

var startingDate = '';
function performSearch(starting, ending) {
		var client = <?= $_SESSION["clientid"] ?>;
		startingDate = starting;
		var data =
			'timeframes=1'
			+'&servicetypes=1'
			+'&surchargetypes=1'
			+'&start='+starting
			+'&end='+ending;
		//alert("["+data+"]");
		$.ajax({
				url: 'client-own-scheduler-data.php?'+data,
				dataType: 'json', // comment this out to see script errors in the console
				type: 'post',
				//contentType: 'application/json',
				//data: JSON.stringify(data),
				processData: false,
				success: handleSearchAjaxResults,
				error: <?= mattOnlyTEST() ? 'submitFailed' : 'handleSearchAjaxResults' // until I figure this out...Figured it out! ?>
				});
}

var today = new Date();
var todayYMD = "<?= date('Y-m-d'); ?>";
var calendarObject, calendarEl, visitDetails = {};
var stashedVisits = null;
var stashedServiceTypes = null;

var showCalendarAsList = false; //'<?= $SHOW_CALENDAR_AS_LIST ? 'true' : 'false' ?>';
var mediaWidthQuery = window.matchMedia("(max-width: 770px)");
// Attach listener function on state changes
mediaWidthQuery.addListener(makeWidthAdjustments);

function makeWidthAdjustments() {
	showCalendarAsList = mediaWidthQuery.matches;
	setCalendar();
	populateCalendar();
	//alert('bonk! narrow: '+showCalendarAsList);
	//console.log('bonk! narrow: '+showCalendarAsList );
	$('#pendingvisitslink').html(
		showCalendarAsList ? $('#pendingvisitslink').prop('numrequests')
			: $('#pendingvisitslink').prop('fullLabel'));

} // alert("showCalendarAsList: "+showCalendarAsList);


document.addEventListener('DOMContentLoaded', function() {
	calendarEl = document.getElementById('clientappts');
	setCalendar();
	//makeWidthAdjustments(mediaWidthQuery);
});

function setCalendar() {
	// https://fullcalendar.io/docs/view-specific-options
//alert(calendarObject);
	if(calendarObject) calendarObject.destroy();
	<?
	/*
$clientUI .= "||clientUICalendarOmitYear|Omit Year view option in client's calendar|boolean"
					."||clientUICalendarOmitDay|Omit Day view option in client's calendar|boolean"
					."||clientUICalendarOmitWeek|Omit Week view option in client's calendar|boolean"
					."||clientUICalendarOmitToday|Omit Today button in client's calendar|boolean"
					."||clientUICalendarIncludePets|Include pet names in client's calendar|boolean";

listDay: { buttonText: 'day' },
listWeek: { buttonText: 'week' },
listYear: { buttonText: 'year' },
listMonth: { buttonText: 'month' }
TITLE FORMATS
	{ year: 'numeric', month: 'long' }                  // like 'September 2009', for month view
	{ year: 'numeric', month: 'short', day: 'numeric' } // like 'Sep 13 2009', for week views
	{ year: 'numeric', month: 'long', day: 'numeric' }  // like 'September 8 2009', for day views
	*/
	
	$titleFormats = 
		array('listDay'=>"titleFormat: { year: 'numeric', month: 'short', day: 'numeric' }",
					'listWeek'=>"titleFormat: { year: 'numeric', month: 'short', day: 'numeric' }",
					'listMonth'=>"titleFormat: { year: 'numeric', month: 'short' }",
					'listYear'=>"titleFormat: { year: 'numeric' }"
					);
			
	foreach(explode(',', 'day,week,month,year') as $span) {
			$cappedSpan = ucfirst($span);
			if(!$_SESSION['preferences']["clientUICalendarOmit$cappedSpan"]) {
				$listViewNames[] = "list$cappedSpan";
				$titleFormat = $titleFormats["list$cappedSpan"];
				$listViews[] = "list$cappedSpan: {buttonText: '{$span}', $titleFormat}";
				
				$gridViews[] = "dayGrid$cappedSpan: {buttonText: '{$span}'}";
				$gridViewNames[] = "dayGrid$cappedSpan";
				//$gridViews[] = "{$span}Grid: {buttonText: '{$span}'}";
				//$gridViewNames[] = "{$span}Grid";
			}
	}
	
	$todayButton = !$_SESSION['preferences']['clientUICalendarOmitToday'] ? 'today' : '';
	if(count($listViews) == 1) $listViews = array();
	if(count($listViewNames) == 1) $listViewNames = array();
	if(count($gridViews) == 1) $gridViews = array();
	if(count($gridViewNames) == 1) $gridViewNames = array();
	$listViews = join(",\n\t", $listViews)."\n";
	$listViewNames = join(',', $listViewNames);
	$gridViews = join(", \n\t", $gridViews)."\n";
	$gridViewNames = join(',', $gridViewNames);
	?>
	if(showCalendarAsList) {
		calendarObject = new FullCalendar.Calendar(calendarEl, {
			plugins: [ 'interaction', 'list' ],
			defaultView: 'listMonth',

			// customize the button names,
			// otherwise they'd all just say "list"
			views: { <?= $listViews ?>},
			header: {
				left: 'prev,next <?= $todayButton ?>',
				//left: 'prevYear,prev,next,nextYear today',
				center: 'title',
				right: '<?= $listViewNames // listDay,listWeek,listMonth,listYear ?>'
			},
			defaultDate: todayYMD,
			editable: true,
			eventLimit: true, // allow "more" link when too many events
			events: [],
			eventRender: renderVisitEvent,
			eventStartEditable: false // disable dragging
			});
		} 
	else {
	//alert("<?= trim($XXXgridViews) ?>");
		calendarObject = new FullCalendar.Calendar(calendarEl, {
			plugins: [ 'interaction', 'dayGrid' ],
			views: { <?= $gridViews ?> },
			header: {
				left: 'prev,next <?= $todayButton ?>',
				//left: 'prevYear,prev,next,nextYear today',
				center: 'title',
				right: '<?= $gridViewNames ?>'
			},
			defaultDate: todayYMD,
			editable: true,
			eventLimit: true, // allow "more" link when too many events
			events: [],
			eventRender: <?= TRUE || mattOnlyTEST() ? 'renderVisitEvent' : 'renderVisitEventWithCheckBoxes' ?>,
			eventStartEditable: false // disable dragging
			});
	}
}

function renderVisitEvent(info) {
	// second pass - use Home page to launch visit details lightbox
	// info.el, info.event
	/*
	event - The associated Event Object.
	el - The HTML element that is being rendered. It has already been populated with the correct time/title text.
	isMirror - true if the element being rendered is a “mirror” from a user drag, resize, or selection (see selectMirror). false otherwise.
	isStart- true if the element being rendered is the starting slice of the event’s range. false otherwise.
	isEnd - true if the element being rendered is the ending slice of the event’s range. false otherwise.
	view - The current View Object.
	*/
	// info.event.id looks like: visitevent_9988
	var appointmentid = info.event.id.split('_');
	appointmentid = appointmentid[1];
	var cbclass = '';
	if($(info.el).hasClass('visitIsSoon')) cbclass += ' visitIsSoon';
	else if($(info.el).hasClass('visitIsInThePast')) cbclass += ' visitIsInThePast';
	var cb = "<input class='"+cbclass+"' type='hidden' id='visit_"+appointmentid+"'>";
	
	var title = [];
	title.push(
		$(info.el).hasClass('COMPLETED') ? 'Visit completed.' : (
		$(info.el).hasClass('CANCELED') ? 'Visit canceled.' : (
		$(info.el).hasClass('INCOMPLETE') ? '' : (
		$(info.el).hasClass('SOON') ? 'Visit will be occurring within '+warningDays+' days.' : '')))
		);
	
	
	var suffix = [];
	if($(info.el).hasClass('COMPLETED')) {
		suffix.push('<i class="fa fa-check-circle"></i>'); // 
	}
	if($(info.el).hasClass('SOON')) {
		suffix.push('<i class="fa fa-clock-o"></i>'); // 
	}
	
	if($(info.el).hasClass('PENDINGCHANGE')) {
		suffix.push('<i  class="fa fa-adjust text-danger"></i>'); // 
		title.push('A change request concerning this visit is pending.');
	}
	
	suffix = ' '+suffix.join(' ');
	
	title = title.join(' ');
	$(info.el).attr('title', title);
	
	//<?= "suppressTimeFrameDisplayInCLientUI [".$_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI']."]" ?>
	
	var noTimeFrames = <?= $_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'] ? 'true' : 'false' ?>;
	let detail = visitDetails[appointmentid];
<?= mattOnlyTEST() ? "if(typeof detail == 'undefined' || detail == null || detail == '') alert('Info missing ['+appointmentid+']'+' :'+JSON.stringify([]).replace(/,/g, ',\\n')+'.');" : '' ?>

	var timeofday = typeof detail == 'undefined' ? '???' : detail.timeofday;
	var times = noTimeFrames ? ["", ""] : timeofday.split('-');
	var nextDayArrow = info.isStart ? '' : "&#9654; ";
	var nextDaySuffix = info.isStart ? '' : " (prev day)";
	var petNames = <?= $_SESSION['preferences']['clientUICalendarOmitPets'] ? '""' 
										: "typeof detail == 'undefined' ? '???' : visitDetails[appointmentid].pets" ?>;
	petNames = typeof petNames == 'undefined' ? '' : petNames;
	if(showCalendarAsList) {  // LIST VIEW
		//alert('noTimeFrames: '+noTimeFrames+" => "+JSON.stringify(times));
		// 3-element row
		var cellContents = times;
		cellContents.push($(info.el).find(">:nth-child(3)").html()+suffix);
		if(noTimeFrames) {
			cellContents[0] = cellContents[2];
			cellContents[2] = '';
		}
		if($(info.el).hasClass('visitIsInThePast')) {
			//$(info.el).find(">:first-child").html(cellContents[0]);
			//$(info.el).find(">:nth-child(2)").html(cellContents[1]);
			//$(info.el).find(">:nth-child(3)").html(cellContents[2]);
			$(info.el).find(">:first-child").html(nextDayArrow+cellContents[2]+nextDaySuffix);
			$(info.el).find(">:nth-child(2)").html(timeofday);
			$(info.el).find(">:nth-child(3)").html(petNames); // reserved for pets
		}
		
		else {
			$(info.el).find(">:first-child").html(
				cb+"&nbsp;"
				+"<label for='visit_"+appointmentid+"'>"
				+nextDayArrow+cellContents[2] //+cellContents[0]
				+nextDaySuffix+"</label>");
			$(info.el).find(">:nth-child(2)").html(
				"<label for='visit_"+appointmentid+"'>"
				+timeofday //+cellContents[1]
				+"</label>");
			$(info.el).find(">:nth-child(3)").html(
				"<label for='visit_"+appointmentid+"'>"
				+petNames //+cellContents[2]  // reserved for pets
				+"</label>");
			/* 
				<td class="fc-list-item-time fc-widget-content">11:30am</td>
				<td class="fc-list-item-marker fc-widget-content"><span class="fc-event-dot"></span></td>
				<td class="fc-list-item-title fc-widget-content"><a>Dog Walk</a></td>
			*/
		}
	}
	else {
		if(noTimeFrames) $(info.el).find(">:first-child").find(">:first-child").html('');
		if($(info.el).hasClass('visitIsInThePast')) {
		//alert($(info.el).find(">:first-child").find(">:first-child")[0]);
			info.el.innerHTML = nextDayArrow+info.el.innerHTML+nextDaySuffix+suffix;
		}
		else if(info.isStart) {
			info.el.innerHTML = 
				cb
				+"&nbsp;<label for='visit_"+appointmentid+"'>"
				+nextDayArrow+info.el.innerHTML
				+nextDaySuffix+suffix+"</label>";
		}
		else { // second day, show a right pointing triangle
			var serviceSpan = $(info.el).find(">:first-child").find(">:first-child");
			serviceSpan.html("&#9654; "+serviceSpan.html()+nextDaySuffix+suffix);
		}
	}
	$(info.el).children().click(function() {visitViewer(appointmentid);});
		
	// https://fullcalendar.io/docs/eventRender
	//$(info.el).find(">:first-child").addClass('CANCELED');
	//https://fullcalendar.io/docs/event-object
}


function visitViewer(appointmentid) {
	lightBoxIFrame('client-visit-snapshot.php?id='+appointmentid, 360, 500); // 360 is the max width for iPhone 10
}

function renderVisitEventWithCheckBoxes(info) {
	// first pass - use Home page to select visits for cancel/uncancel
	// info.el, info.event
	/*
	event - The associated Event Object.
	el - The HTML element that is being rendered. It has already been populated with the correct time/title text.
	isMirror - true if the element being rendered is a “mirror” from a user drag, resize, or selection (see selectMirror). false otherwise.
	isStart- true if the element being rendered is the starting slice of the event’s range. false otherwise.
	isEnd - true if the element being rendered is the ending slice of the event’s range. false otherwise.
	view - The current View Object.
	*/
	// info.event.id looks like: visitevent_9988
	var appointmentid = info.event.id.split('_');
	appointmentid = appointmentid[1];
	var cbclass = 'visitcheckbox';
	if($(info.el).hasClass('visitIsSoon')) cbclass += ' visitIsSoon';
	else if($(info.el).hasClass('visitIsInThePast')) cbclass += ' visitIsInThePast';
	var cb = "<input class='"+cbclass+"' type='checkbox' id='visit_"+appointmentid+"'>";
	
	var title = [];
	title.push(
		$(info.el).hasClass('COMPLETED') ? 'Visit completed.' : (
		$(info.el).hasClass('CANCELED') ? 'Visit canceled.' : (
		$(info.el).hasClass('INCOMPLETE') ? '' : (
		$(info.el).hasClass('SOON') ? 'Visit will be occurring within '+warningDays+' days.' : '')))
		);
	
	
	var suffix = [];
	if($(info.el).hasClass('COMPLETED')) {
		suffix.push('<i class="fa fa-check-circle"></i>'); // 
	}
	if($(info.el).hasClass('SOON')) {
		suffix.push('<i class="fa fa-clock-o"></i>'); // 
	}
	
	if($(info.el).hasClass('PENDINGCHANGE')) {
		suffix.push('<i  class="fa fa-adjust text-danger"></i>'); // 
		title.push('A change request concerning this visit is pending.');
	}
	
	suffix = ' '+suffix.join(' ');
	
	title = title.join(' ');
	$(info.el).attr('title', title);
	
	//<?= "suppressTimeFrameDisplayInCLientUI [".$_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI']."]" ?>
	
	var noTimeFrames = <?= $_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'] ? 'true' : 'false' ?>;
	
	var timeofday = visitDetails[appointmentid].timeofday;
	var times = noTimeFrames ? ["", ""] : timeofday.split('-');
	if(showCalendarAsList) {  // LIST VIEW
		//alert('noTimeFrames: '+noTimeFrames+" => "+JSON.stringify(times));
		// 3-element row
		var cellContents = times;
		cellContents.push($(info.el).find(">:nth-child(3)").html()+suffix);
		if(noTimeFrames) {
			cellContents[0] = cellContents[2];
			cellContents[2] = '';
		}
		if($(info.el).hasClass('visitIsInThePast')) {
			//$(info.el).find(">:first-child").html(cellContents[0]);
			//$(info.el).find(">:nth-child(2)").html(cellContents[1]);
			//$(info.el).find(">:nth-child(3)").html(cellContents[2]);
			$(info.el).find(">:first-child").html(cellContents[2]);
			$(info.el).find(">:nth-child(2)").html(timeofday);
			$(info.el).find(">:nth-child(3)").html(''); // reserved for pets
		}
		
		else {
			$(info.el).find(">:first-child").html(
				cb+"&nbsp;"
				+"<label for='visit_"+appointmentid+"'>"
				+cellContents[2] //+cellContents[0]
				+"</label>");
			$(info.el).find(">:nth-child(2)").html(
				"<label for='visit_"+appointmentid+"'>"
				+timeofday //+cellContents[1]
				+"</label>");
			$(info.el).find(">:nth-child(3)").html(
				"<label for='visit_"+appointmentid+"'>"
				+"" //+cellContents[2]  // reserved for pets
				+"</label>");
			/* 
				<td class="fc-list-item-time fc-widget-content">11:30am</td>
				<td class="fc-list-item-marker fc-widget-content"><span class="fc-event-dot"></span></td>
				<td class="fc-list-item-title fc-widget-content"><a>Dog Walk</a></td>
			*/
		}
	}
	else {
		if(noTimeFrames) $(info.el).find(">:first-child").find(">:first-child").html('');
		if($(info.el).hasClass('visitIsInThePast')) {
		//alert($(info.el).find(">:first-child").find(">:first-child")[0]);
			info.el.innerHTML = info.el.innerHTML+suffix;
		}
		else if(info.isStart) {
			info.el.innerHTML = 
				cb
				+"&nbsp;<label for='visit_"+appointmentid+"'>"
				+info.el.innerHTML
				+suffix+"</label>";
		}
		else { // second day, show a right pointing triangle
			var serviceSpan = $(info.el).find(">:first-child").find(">:first-child");
			serviceSpan.html("&#9654; "+serviceSpan.html()+suffix);
		}
	}
		
	// https://fullcalendar.io/docs/eventRender
	//$(info.el).find(">:first-child").addClass('CANCELED');
	//https://fullcalendar.io/docs/event-object
}

var hideCanceledVisits = <?= $_SESSION['preferences']['clientUICalendarOmitCanceledVisits'] ? 'true' : 'false' ?>;
function handleSearchAjaxResults(data, textStatus, jQxhr) {
/*
{"visits":[
{"date":"2020-02-08","starttime":"11:30:00","endtime":"14:30:00",
"timeofday":"11:30 am-2:30 pm","appointmentid":"236284","providerptr":"2","servicecode":"25",
"charge":"65.00","adjustment":null,"rate":"0.00","bonus":null,"packagetype":"ongoing",
"status":"INCOMPLETE","hours":"00:00","formattedhours":"0.000","arrived":null,"completed":null,
"servicelabel":"Overnight - 1 pet","tax":null,"visitreport":null,"pendingchange":null,
"clientservicelabel":"Overnight","sitter":{"none":true},
"visitreportstatus":{"received":0,"photo":"nophoto","sent":0,
"url":"https://LeashTime.com/visit-report-data.php?id=236284",
"externalurl":"https://LeashTime.com/visit-report-data.php?nugget=jtFZXCCTeOcfb%2FWybzCoMugmU4e%2BAXGi"},
"pendingchangetype":null}},

*/
	
<?= false && mattOnlyTEST() ? 'alert("handleSearchAjaxResults addingMode: ["+addingMode+"]");' : '' ?>	
	if(addingMode) stashedVisits = [];
	if(hideCanceledVisits) data.visits.forEach(function(visit, index) {
			if(!stashedVisits) stashedVisits = [];
			if(visit.status != 'CANCELED') stashedVisits.push(visit);
		});
	else stashedVisits = data.visits;
	
<? if(TRUE) { // leave room to make the label substitution subject to manager preference ?>
	data.visits.forEach(function(visit, index) {
		if(visit.status == 'CANCELED') data.visits[index].clientservicelabel = 'CANCELED';
	});
<? } ?>
	
	stashedServiceTypes = data.servicetypes;
	
	populateCalendar();
	if($('#working').css('display') != 'none') $('#working').toggle();
	
	if(false && mattOnlyTEST()) {
		if(before != null) {
			addingMode = true;
			var starting = before.starting;
			var ending = before.ending;
			before = null;
	<?= false && mattOnlyTEST() ? 'alert("poop");console.log("performSearch before: "+addingMode)' : '' ?>	
			if(<?= false && mattOnlyTEST() ? 'true' : 'false' ?>) performSearch(starting, ending);
		}
		else if(after != null) {
			addingMode = true;
			var starting = after.starting;
			var ending = after.ending;
			after = null;
	<?= false && mattOnlyTEST() ? 'console.log("performSearch after: "+addingMode+" "+starting+"-"+ending)' : '' ?>	
			if(<?= false && mattOnlyTEST() ? 'true' : 'false' ?>) performSearch(starting, ending);
		}
	}
	if(lastHighlightedDate == null) {
		lastHighlightedDate = new Date();
	}
	calendarObject.gotoDate(lastHighlightedDate);

}

<? 
if($_SESSION['preferences']['warnOfLateScheduling'] 
		&& ($warningDays = $_SESSION['preferences']['lastSchedulingDays']))
	$warningDays = 0 + $warningDays;
if($warningDays) echo "var warningDays = {$_SESSION['preferences']['lastSchedulingDays']};\n";
else echo "var warningDays = '';\n";
?>

function populateCalendar() {
	var addVisits = addingMode == 1 || addingMode == true;
	addingMode = false;
	if(!addVisits)
		calendarObject.getEvents().forEach(function(calEvent, index) {
			calEvent.remove();
		});
	if(stashedVisits == null) return;
	var visits = stashedVisits;
<?= false && mattOnlyTEST() ? "if(addVisits) alert('Adding '+stashedVisits.length+' visits.');" : '' ?>
<?= false && mattOnlyTEST() ? "if(addVisits) alert('visitDetails: '+JSON.stringify(visitDetails).replace(/,/g, ',\\n'));" : '' ?>

	var servicetypes = stashedServiceTypes;
	//var html = '<table><tr><th>&nbsp;<th>Time<th>Service<th>Detail</tr>';
//alert(JSON.stringify(servicetypes));
	var lastDateStr = '';
	var todayDate = new Date();
	visits.forEach(function(visit, index) {
<?= false && mattOnlyTEST() ? 'alert(JSON.stringify(visit)+"\n.");' : '' ?>	
		if(visit.date != lastDateStr) ; // no-op
		lastDateStr = visit.date;
		var svc = visit.clientservicelabel;
		if(!svc || typeof svc == 'undefined') {
			svc = servicetypes[""+visit.servicecode];
			svc = typeof svc == 'undefined' ? visit.servicelabel : svc.label;
		}
		var cbclass = '';
		var visitDate = visit.date.split('-');
		visitDate = new Date(visitDate[0], visitDate[1]-1, visitDate[2]); // month os zero-based
		var daysAhead = daysBetween(visitDate,todayDate);
		var timeliness = null;
		if(daysAhead >= 1) {
			timeliness = 'past';
			cbclass += " visitIsInThePast";
		}
		else if(warningDays && Math.abs(daysAhead) <= warningDays) {
			timeliness = 'soon';
			cbclass += " visitIsSoon";
		}
		var eventStatus = visit.status.toUpperCase(); // INCOMPLETE, COMPLETED, CANCELED
		//if(eventStatus == 'INCOMPLETE' && timeliness == 'soon') eventStatus = 'SOON';
		cbclass += " "+eventStatus;
		
		if(visit.pendingchange) cbclass += " PENDINGCHANGE";

		
		var visitEvent = {};
		visitEvent.title = svc;
		visitEvent.start = visit.date+"T"+visit.starttime;
		if(visit.starttime.localeCompare(visit.endtime) > 0) {// if starttime is AFTER endtime
			var tomorrow = visitDate; //new Date();
			tomorrow.setDate(tomorrow.getDate() + 1);
			visitEvent.end = tomorrow.getFullYear()+'-'+(tomorrow.getMonth()+1)+'-'+tomorrow.getDate()+"T"+visit.endtime;
		}
		else visitEvent.end = visit.date+"T"+visit.endtime;
<?= false && mattOnlyTEST() ? 'alert(JSON.stringify(visitEvent)+"\n.");' : '' ?>	
		//alert(visitDate+" <= "+todayDate+" "+daysAhead+" "+cbclass+" warning limit["+warningDays+"]");
		visitEvent.classNames = [cbclass];
		visitEvent.id = 'visitevent_'+visit.appointmentid;
		
<?= false && mattOnlyTEST() ? "if(addVisits) alert('Adding #'+index+' :'+JSON.stringify(visit.appointmentid).replace(/,/g, ',\\n')+'.');" : '' ?>
		calendarObject.addEvent(visitEvent);
		visitDetails[visit.appointmentid] = 
			{appointmentid: visit.appointmentid, timeofday: visit.timeofday, pets: visit.pets};
	//alert(JSON.stringify(visitDetails[visit.appointmentid]));
	}); // foreach call
<?= false && mattOnlyTEST() ? "if(addVisits) alert('FINISHED Adding '+stashedVisits.length+' visits.');" : '' ?>
	//alert(new Date(mdyArray[2], mdyArray[0]-1, mdyArray[1]));
	var mdyArray ;
	if(startingDate.indexOf('-') == -1) mdyArray = mdy(startingDate);
	else {
		mdyArray = startingDate.split('-'); // actually it is ymd
		mdyArray = new Array(mdyArray[1], mdyArray[2], mdyArray[0]);
	}
	//alert(startingDate+"=>"+JSON.stringify(mdyArray));
	calendarObject.gotoDate(new Date(mdyArray[2], mdyArray[0]-1, mdyArray[1]));
	
	if(!addVisits) {
		<?= false && mattOnlyTEST() ? "alert('OI!');" : ';' ?>
		calendarObject.render();
	}
	//$('.CANCELED').attr('title', 'Visit canceled.');	
	//$('.COMPLETED').attr('title', 'Visit completed.');	
	//$('.INCOMPLETE').attr('title', '');	
	//$('.SOON').attr('title', 'Visit will be occurring within <?= $warningDays ?> days.');	
	
	/*$('.CANCELED').find(">:first-child").addClass('CANCELED');
	$('.COMPLETED').find(">:first-child").addClass('COMPLETED');
	$('.INCOMPLETE').find(">:first-child").addClass('INCOMPLETE');
	$('.SOON').find(">:first-child").addClass('SOON');
	
	$('.CANCELED').find(">:first-child").children().addClass('CANCELED');
	$('.COMPLETED').find(">:first-child").children().addClass('COMPLETED');
	$('.INCOMPLETE').find(">:first-child").children().addClass('INCOMPLETE');
	$('.SOON').find(">:first-child").children().addClass('SOON');*/
	//html += "</table>";
	//$('#clientappts').html(html);
	//$('#clientappts').html(JSON.stringify(data));
}

function daysBetween(d1,d2) {
 return toDays(toUTC(d2)-toUTC(d1));
}

function toDays(d) {
 d = d || 0;
 return d / 24 / 60 / 60 / 1000;
}

function toUTC(d) {
 if(!d || !d.getFullYear)return 0;
 return Date.UTC(d.getFullYear(),
              d.getMonth(),d.getDate());
}

function submitFailed(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'
		+<?= mattOnlyTEST() ? 'errorThrown' : '"Please notify support."' ?>;
	console.log(message );
	$('#working').toggle();

}

function postSearch(unused, response) {
	document.getElementById('clientappts').innerHTML = response;
	/*$('#petphotos').cycle({
			fx: 'fade',
			easing: 'backout',
			delay:  -8000
	});*/
	populatePendingRequests();

}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function sortAppointments(field, dir) {
	var calendarview = true; //document.getElementById('calendarview') ? true : false;
	searchForAppointmentsWithSort(field+'_'+dir, calendarview);
}

function update(target, val) {
	if(target == 'appointments') {
		var calendarview = true; //document.getElementById('calendarview') ? true : false;
		searchForAppointmentsWithSort('', calendarview);
	}
	populatePendingRequests();
}

function showVisitReport(id) {
	$.fn.colorbox({href:"visit-report.php?id="+id, width:"600", height:"700", scrolling: true, opacity: "0.3"});
}

//searchForAppointments(true);


/* $(document).ready(function(){
	$('#petphotos').cycle({
			fx: 'fade',
			easing: 'backout',
			delay:  -8000
	});
	populatePendingRequests();
	performInitialSearch();
});
*/

// TEST
<? if(FALSE && mattOnlyTEST()) { ?>	
function init() {
	$('#calendar').css('background:yellow;z-index:9999');
	alert($('#calendar').css('y'));
		if (!ns4)
		{
			//if (!ie) yearNow += 1900;
			if(yearNow < 1900) yearNow += 1900;

			crossobj=(dom)?document.getElementById('calendar').style : ie? document.all.calendar : document.calendar;
			hideCalendar();

			crossMonthObj = (dom) ? document.getElementById('selectMonth').style : ie ? document.all.selectMonth : document.selectMonth;

			crossYearObj = (dom) ? document.getElementById('selectYear').style : ie ? document.all.selectYear : document.selectYear;

			monthConstructed = false;
			yearConstructed = false;

			if (showToday == 1) {
				document.getElementById('lblToday').innerHTML =	'<font color="#000066">' + todayString[language] + ' <a onmousemove="window.status=\''+gotoString[language]+'\'" onmouseout="window.status=\'\'" title="'+gotoString[language]+'" style="'+styleAnchor+'" href="javascript:monthSelected=monthNow;yearSelected=yearNow;constructCalendar();">'+dayName[language][(today.getDay()-startAt==-1)?6:(today.getDay()-startAt)]+', ' + dateNow + ' ' + monthName[language][monthNow].substring(0,3) + ' ' + yearNow + '</a></font>';
			}

			sHTML1 = '<span id="spanLeft" style="border:1px solid #36f;cursor:pointer" onmouseover="swapImage(\'changeLeft\',\'left2.gif\');this.style.borderColor=\'#8af\';window.status=\''+scrollLeftMessage[language]+'\'" onclick="decMonth()" onmouseout="clearInterval(intervalID1);swapImage(\'changeLeft\',\'left1.gif\');this.style.borderColor=\'#36f\';window.status=\'\'" onmousedown="clearTimeout(timeoutID1);timeoutID1=setTimeout(\'StartDecMonth()\',500)" onmouseup="clearTimeout(timeoutID1);clearInterval(intervalID1)">&nbsp<img id="changeLeft" src="'+imgDir+'left1.gif" width="10" height="11" border="0">&nbsp</span>&nbsp;';
			sHTML1 += '<span id="spanRight" style="border:1px solid #36f;cursor:pointer" onmouseover="swapImage(\'changeRight\',\'right2.gif\');this.style.borderColor=\'#8af\';window.status=\''+scrollRightMessage[language]+'\'" onmouseout="clearInterval(intervalID1);swapImage(\'changeRight\',\'right1.gif\');this.style.borderColor=\'#36f\';window.status=\'\'" onclick="incMonth()" onmousedown="clearTimeout(timeoutID1);timeoutID1=setTimeout(\'StartIncMonth()\',500)" onmouseup="clearTimeout(timeoutID1);clearInterval(intervalID1)">&nbsp<img id="changeRight" src="'+imgDir+'right1.gif" width="10" height="11" border="0">&nbsp</span>&nbsp;';
			sHTML1 += '<span id="spanMonth" style="border:1px solid #36f;cursor:pointer" onmouseover="swapImage(\'changeMonth\',\'drop2.gif\');this.style.borderColor=\'#8af\';window.status=\''+selectMonthMessage[language]+'\'" onmouseout="swapImage(\'changeMonth\',\'drop1.gif\');this.style.borderColor=\'#36f\';window.status=\'\'" onclick="popUpMonth()"></span>&nbsp;';
			sHTML1 += '<span id="spanYear" style="border:1px solid #36f;cursor:pointer" onmouseover="swapImage(\'changeYear\',\'drop2.gif\');this.style.borderColor=\'#8af\';window.status=\''+selectYearMessage[language]+'\'" onmouseout="swapImage(\'changeYear\',\'drop1.gif\');this.style.borderColor=\'#36f\';window.status=\'\'" onclick="popUpYear()"></span>&nbsp;';

			document.getElementById('caption').innerHTML = sHTML1;

			bPageLoaded=true;
		}
	}
<? } //mattOnlyTEST()) ?>	

</script>
<script language='javascript'><? if(mattOnlyTEST()) echo "alert(datepicker);"; ?></script>

<?
include "js-refresh.php";

$onLoadFragments = array("init();", "populatePendingRequests();", "performInitialSearch();", "initializeCalendarImageWidgets();", "makeWidthAdjustments();");

// ***************************************************************************
//echo "<br><img src='art/spacer.gif' width=1 height=300>";

if($OLDFRAME) include "frame-end.html"; else 
include "frame-client-responsive-end.html";
?>
