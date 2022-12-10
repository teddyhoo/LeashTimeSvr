<? // short-term-schedule-history.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
//require_once "temp-service-fns.php";
require_once "service-fns.php";

$clientid = $_GET['id'];

if($_GET['ajax']) {
	dumpNonRecurringSchedules2($clientid, $maxnumber=-1);
if(mattOnlyTEST()) echo "<hr>$screenLog";	
	exit;
}

$schedules = getCurrentClientPackages($clientid, 'tblservicepackage');

if(count($schedules) > 100) $warning = "There are ".count($schedules)." schedules to show.  Please wait...";
$clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientid LIMIT 1");

$windowTitle .=  "$clientname's Short Term Schedule History";
require "frame-bannerless.php";
dumpNRSectionStyle();
?>
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<h2><?= $windowTitle ?></h2>
<div id='contents'></div>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
<?  
dumpUpdateNRIconsJS();
?>
document.getElementById('contents').innerHTML = '<?= $warning ?>';
//ajaxGet("short-term-schedule-history.php?ajax=1&id=<?= $clientid ?>", 'contents');
//dumpNRSectionJS("ajaxGet(\"short-term-schedule-history.php?ajax=1&id=$clientid\", 'contents');");
$.ajax({
	url: "short-term-schedule-history.php?ajax=1&id=<?= $clientid ?>",
	success: function(data) {
					$('#contents').html(data);
					updateNRIcons(); 
				}
});

function viewNRCalendar(packageid, irreg) {
	var dest = irreg ? "calendar-package-irregular.php" : "calendar-package-nr.php";
	openConsoleWindow("viewcalendar", dest+"?packageid="+packageid, 900, 700);
}

function editNRScheduleInParentWindow(url) {
	window.opener.location.href = url;
	<? if(isIPad()) echo "alert('Please switch back to the main LeashTime window to view this schedule.');" ?>
}


</script>
