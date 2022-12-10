<?
// client-own-schedule-list-ajax.php
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


$pageTitle = "AJAX Home: {$_SESSION["clientname"]}'s Schedule";

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


<form name="clientschedform">
<input type="hidden" id="client" name="client" value="<?= $client ?>">
<?
$starting = date('Y-m-d');
$ending = date('Y-m-d', strtotime("+30 days"));

calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
echo "&nbsp;";
calendarSet('ending:', 'ending', $ending);
echo " ";
echoButton('showAppointments', 'Show', 'searchForAppointments(true)');

echo "<p id='pending-requests' style='text-align:right;font-size:1.5em'></p>";  // updated by JSON ajax-pending-requests.php

echo "<table><tr><td>"
.echoButton('', "Cancel Selected Visits", "generateScheduleChangeRequest(\"cancel\")", null, null, 'noEcho', 'Click here to cancel selected visits.')
."</td><td>"
.echoButton('', "UnCancel Selected Visits", "generateScheduleChangeRequest(\"uncancel\")", null, null, 'noEcho', 'Click to uncancel selected visits.')
."</td><td>"
.echoButton('', "Change Selected Visits", "generateScheduleChangeRequest(\"change\")", null, null, 'noEcho', 'Click here to make changes to selected visits.')
."</td></tr>"
."</table>";

?>
<div id='clientappts'> </div>
</form>



<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>


<? if($_SESSION['preferences']['enableclientuimultidaycancel'] || $_SESSION["responsiveClient"]) { 
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

function searchForAppointments() {
	searchForAppointmentsWithSort('');
}

function searchForAppointmentsWithSort(sort) {
	//if(sort) sort = '&sort='+sort;
  if(MM_validateForm(
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var starting = document.getElementById('starting').value;
		var ending = document.getElementById('ending').value;
		//alert(starting+" => "+ending);
		performSearch(starting, ending);
	}
}

function performInitialSearch() {
		var client = <?= $_SESSION["clientid"] ?>;
		var starting = '<?= date('Y-m-d'); ?>';
		var ending = '<?= date('Y-m-d', strtotime("+30 days")); ?>';
		performSearch(starting, ending);
}

function performSearch(starting, ending) {
		var client = <?= $_SESSION["clientid"] ?>;
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

function handleSearchAjaxResults(data, textStatus, jQxhr) {
//alert(JSON.stringify(data));

<? 
		if($_SESSION['preferences']['warnOfLateScheduling'] 
				&& ($warningDays = $_SESSION['preferences']['lastSchedulingDays']))
			$warningDays = 0 + $warningDays;
		if($warningDays) echo "var warningDays = {$_SESSION['preferences']['lastSchedulingDays']};\n";
		else echo "var warningDays = '';\n";
?>
	var visits = data.visits;
	var servicetypes = data.servicetypes;
	var html = '<table><tr><th>&nbsp;<th>Time<th>Service<th>Detail</tr>';
		//alert(JSON.stringify(servicetypes));
	var lastDateStr = '';
	var todayDate = new Date();
	visits.forEach(function(visit, index) {
		if(visit.date != lastDateStr)
			html += "<tr><td colspan=3 style='font-weight: bold;color:blue;'>"+visit.date+"</td></tr>";
		lastDateStr = visit.date;
		var svc = visit.clientservicelabel;
		if(!svc || typeof svc == 'undefined') {
			svc = servicetypes[""+visit.servicecode];
			svc = typeof svc == 'undefined' ? "[code: "+visit.servicecode+"]" : svc.label;
		}
		var cbclass = 'visitcheckbox';
		var visitDate = visit.date.split('-');
		visitDate = new Date(visitDate[0], visitDate[1], visitDate[2]);
		var daysAhead = daysBetween(visitDate,todayDate);
		if(daysAhead < 1) cbclass += " visitIsInThePast";
		else if(warningDays && daysAhead <= warningDays) cbclass += " visitIsSoon";
		// visitIsInThePast visitIsSoon
		html += "<tr><td><input type='checkbox' class='"+cbclass+"' id='visit_"+visit.appointmentid+"'><td>"+visit.timeofday+"<td>"+svc+"<td>"+JSON.stringify(visit)+"<tr>";
		});
	html += "</table>";
	$('#clientappts').html(html);
	//$('#clientappts').html(JSON.stringify(data));
}

function daysBetween(d1,d2){
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


$(document).ready(function(){
	$('#petphotos').cycle({
			fx: 'fade',
			easing: 'backout',
			delay:  -8000
	});
	populatePendingRequests();
	performInitialSearch();
});
</script>

<?
include "js-refresh.php";

// ***************************************************************************
echo "<br><img src='art/spacer.gif' width=1 height=300>";
include "frame-end.html";
?>
