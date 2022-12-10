<? // mobile-client-edit-day.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "mobile-client-fns.php";

locked('c-');

extract(extractVars('date', $_REQUEST));

	require_once "appointment-fns.php"; // for briefTimeOfDay($appt)
	
	$prevDay = date('Y-m-d', strtotime("-1 day", strtotime($date)));
	$nextDay = date('Y-m-d', strtotime("+1 day", strtotime($date)));
	
	$extraHeadContent = swipeHEADContent().
		"<script type='text/javascript'>

			function processSwipe() {
				var swipedElement = document.getElementById(triggerElementID);
				var date = '';
				if ( swipeDirection == 'left' ) {
					date = '$nextDay';
				} else if ( swipeDirection == 'right' ) {
					date = '$prevDay';
				} else if ( swipeDirection == 'up' ) {
					window.scrollBy(0, 100);
				} else if ( swipeDirection == 'down' ) {
					window.scrollBy(0, -100);
				}
				if(date) document.location.href='mobile-client-edit-day.php?date='+date;
			}
			
			function editDay(day) {
				document.location.href='mobile-client-edit-day.php?date='+day;
			}
			
			
			function changeVisit(visitid, cancel) {
				var op = cancel == 1 ? 'cancel' : 'change';
				document.location.href = 'client-request-appointment.php?mobile=1&operation='+op+'&id='+visitid;
			}
	</script>";
	require_once "mobile-frame-client.php";
	//dumpCalendarLooks(35, $descriptionColor='blue');
?>
<div id="swipeBox" 
		style='background:white;'
		ontouchstart="touchStart(event,'swipeBox');" 
		ontouchend="touchEnd(event);" 
		ontouchmove="touchMove(event);" 
		ontouchcancel="touchCancel(event);"
>
<?
dumpDayEditorVisits($date, $_SESSION['clientid']);
?>
</div>


