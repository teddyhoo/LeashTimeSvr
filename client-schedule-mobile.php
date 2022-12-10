<? // client-schedule-mobile.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";
require_once "key-fns.php";
require_once "mobile-client-fns.php";

locked('c-');

extract(extractVars('date,delta,hidecompletedtoggle,showvisitcount,showarrivalandcompletiontimestoggle', $_REQUEST));

$calendarTest = true; //in_array($_SESSION['auth_login_id'], array('dlifebeth', 'Xjessica', 'mmtestsit'));
// ==================================

require_once "appointment-fns.php"; // for briefTimeOfDay($appt)
$date = $date ? $date : date('Y-m-d');
$prevMonthStart = date('Y-m-d', strtotime("-1 month", strtotime($date)));
$nextMonthStart = date('Y-m-d', strtotime("+1 month", strtotime($date)));

$extraHeadContent = swipeHEADContent().
	"<script type='text/javascript'>

		function processSwipe() {
			var swipedElement = document.getElementById(triggerElementID);
			var date = '';
			if ( swipeDirection == 'left' ) {
				date = '$nextMonthStart';
			} else if ( swipeDirection == 'right' ) {
				date = '$prevMonthStart';
			} else if ( swipeDirection == 'up' ) {
				window.scrollBy(0, 100);
			} else if ( swipeDirection == 'down' ) {
				window.scrollBy(0, -100);
			}
			if(date) document.location.href='client-schedule-mobile.php?date='+date;
		}

		function editDay(day) {
			document.location.href='mobile-client-edit-day.php?date='+day;
		}
</script>";
require_once "mobile-frame-client.php";
?>
<div id="swipeBox" 
	style=''
	ontouchstart="touchStart(event,'swipeBox');" 
	ontouchend="touchEnd(event);" 
	ontouchmove="touchMove(event);" 
	ontouchcancel="touchCancel(event);"
>
<?
dumpMonth($date);
?>
</div>
<?

require_once "mobile-frame-client-end.php";