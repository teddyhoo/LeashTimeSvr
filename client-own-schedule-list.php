<?
// client-own-schedule-list.php
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

$client = $_SESSION["clientid"];

$starting = shortDate();
$days = $_SESSION['preferences']['clientschedulelookaheaddays'];
$days = $days ? $days : 45;
$ending = shortDate(strtotime("+$days days"));


$pageTitle = "Home: {$_SESSION["clientname"]}'s Schedule";

//if(mattOnlyTEST()) {echo "{$_SESSION['user_notice']}<hr>";}

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
//if(mattOnlyTEST()) {echo "$template<hr><hr>";exit;}
	$_SESSION['user_notice'] = $template;
}	
//if(mattOnlyTEST()) {echo "{$_SESSION['user_notice']}<hr><hr>";exit;}

include "frame-client.html";
// ***************************************************************************
?>
<style>
.pending-requests-table td {font-size: 1.3em;}
</style>
<!-- script type="text/javascript" src="jquery_1.3.2_jquery.min.js">></script -->
<script type="text/javascript" src="jquery.cycle.2.84.js"></script>
<script type="text/javascript" src="jquery.easing.1.1.1.js"></script>

<? 
if(FALSE && $_SESSION['preferences']['postcardsEnabled']) {
	echo "<table><tr><td style='vertical-align:center'>";
	$unreadPostCard = fetchRow0Col0("SELECT cardid FROM tblpostcard WHERE viewed IS NULL AND suppressed IS NULL ORDER BY created DESC LIMIT 1", 1);
	if($unreadPostCard) echo "<a href='postcard-viewer.php?open=$unreadPostCard'><img src='art/postcard-button.jpg' border=0> <img src='art/newpostcard.gif' border=0></a>";
	else echo "<a href='postcard-viewer.php'><img src='art/postcard-button.jpg' border=0> <img src='art/postcards.jpg' border=0></a>";
	
	echo "</td></tr></table>";
	
}
?>



<div id='clientappts'>
<? 

include "client-schedule-cal.php"; ?>
 </div>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>


<? if($_SESSION['preferences']['enableclientuimultidaycancel']) { 
require_once "request-safety.php";
dumpClientScheduleChangeJS();

 } ?>



var unresolvedRequests;
function viewPendingRequests() {
	$.fn.colorbox({html:unresolvedRequests, width:"500", height:"300", scrolling: true, opacity: "0.3"});
}

function updatePendingRequests(unused, response) {var el;
	if(!(el = document.getElementById('pending-requests'))) return;
	response = JSON.parse(response);
	if(!response.result || response.result == '') {
		el.innerHTML = '';
		unresolvedRequests = '';
	}
	else {
		el.innerHTML = response.result.link;
		unresolvedRequests = response.result.listhtml;
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


<? dumpPopCalendarJS(); ?>	

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

function searchForAppointments(calendarview) {
	searchForAppointmentsWithSort('', calendarview);
}

function searchForAppointmentsWithSort(sort, calendarview) {
  if(MM_validateForm(
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var client = <?= $_SESSION["clientid"] ?>;
		var starting = document.getElementById('starting').value;
		var ending = document.getElementById('ending').value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		if(sort) sort = '&sort='+sort;
		var url = calendarview ? 'client-schedule-cal.php' : 'client-schedule-list.php';
    //ajaxGet(url+'?client='+client+starting+ending+sort+"&targetdiv=clientappts", 'clientappts')
    ajaxGetAndCallWith(url+'?client='+client+starting+ending+sort+"&targetdiv=clientappts", postSearch, 0);
	}
}

function postSearch(unused, response) {
	document.getElementById('clientappts').innerHTML = response;
	$('#petphotos').cycle({
			fx: 'fade',
			easing: 'backout',
			delay:  -8000
	});
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
	var calendarview = document.getElementById('calendarview') ? true : false;
	searchForAppointmentsWithSort(field+'_'+dir, calendarview);
}

function update(target, val) {
	if(target == 'appointments') {
		var calendarview = document.getElementById('calendarview') ? true : false;
		searchForAppointmentsWithSort('', calendarview);
	}
	populatePendingRequests();
}

function showVisitReport(id) {
	$.fn.colorbox({href:"visit-report.php?id="+id, width:"600", height:"700", scrolling: true, opacity: "0.3"});
}

//searchForAppointments(true);


$(document).ready(function(){
	$('#petphotos').cycle({
			fx: 'fade',
			easing: 'backout',
			delay:  -8000
	});
	populatePendingRequests();
});
</script>

<?
include "js-refresh.php";

// ***************************************************************************
echo "<br><img src='art/spacer.gif' width=1 height=300>";
include "frame-end.html";
?>
