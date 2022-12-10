<? // cancel-recurring-schedule.php
// AJAX
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "js-gui-fns.php";

locked('o-');

if($_REQUEST['uncancel']) { // AJAX from services tab
	uncancelRecurringSchedule($_REQUEST['uncancel']);
	exit;
}
if($_POST) { // from lightbox
	cancelRecurringSchedule($_POST['packageid'], $_POST['date']);
	echo "<script language='javascript'>if(parent.update) parent.update('appointments', '');parent.$.fn.colorbox.close();</script>";
	exit;
}


// else Cancel button from services tab

$extraHeadContent = '<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>';
require "frame-bannerless.php";
$package = getPackage(($packageid = $_REQUEST['packageid']), 'R');
if(!$package) $error = "Package $packageid is not a recurring package";
else $client = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$package['clientptr']}");
echo "<h2>Cancel {$client}'s<br>Ongoing schedule</h2>";
echo "<form name='cancelsched' method='POST'>";
hiddenElement('packageid', $packageid);
calendarSet('on', 'date', '');
echo ' ';
echoButton('', 'Cancel This Schedule', 'go()', 'HotButton', 'HotButtonDown');
echo "<p>&nbsp<p>";
echoButton('', 'Quit without canceling', 'parent.$.fn.colorbox.close();');
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function go() {
	if(MM_validateForm(
		  'date', '', 'R',
		  'date', '', 'isDate'))
		 document.cancelsched.submit();
}
<?
dumpPopCalendarJS();
?>
</script>

