<?
// prov-time-off-page.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";


// Determine access privs
$locked = locked('p-');


if($_REQUEST['showvideo']) {
	echo  file_get_contents("http://training.leashtime.com/beta/?vid=hDVH9tf9UJw");
	exit;
}

$provider = $_SESSION["providerid"];


$pageTitle = "{$_SESSION["shortname"]}'s Scheduled Time Off";

include "frame.html";
// ***************************************************************************
?>
<div
	style='display:inline-block;float:right;font-size:7pt;cursor:pointer;padding-left: 7px;'>
	<span onclick="openConsoleWindow('timeoffcalendar', 'timeoff-sitter-calendar.php?provid=<?= $providers ?>&editable=1',850,700)">
	<? if(TRUE || mattOnlyTEST()) { ?>
	<img src='art/timeoff-calendar-56x46.jpg' title='Open the Sitter Time Off Calendar'><br>View time off calendar.
	<? } else { ?>
	<img src='art/clock20.gif' title='Open the Sitter Time Off Calendar'> Click to view Sitter time off calendar.
	<? } ?>
	</span>
	<p><div style='display:block;float:right;border: solid black 1px;background:white;width:110px;height:70px;text-align:center;padding:5px;font-size:10pt;font-weight:bold;color:blue;cursor:pointer;'
			       onclick='showVideo()'>
			Click here to watch the video <i>Time Off for Sitters</i>
						</div>	
</div>
<? if(TRUE || staffOnlyTEST()) { ?>	
<? } ?>						
<div style='display:block;' id='timeoffdiv'></div>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function showVideo() {
		$.fn.colorbox({href: "prov-time-off-page.php?showvideo=1", 
		width:"870", height:"700", scrolling: true, opacity: "0.3", iframe: "true"});
}

function toggleOldTimeOff(el) {
	$('#toggleTimePleaseWait').toggle();
	el.innerHTML = el.innerHTML == 'Show Old Time Off' ? 'Hide Old Time Off' : 'Show Old Time Off';
	$('.oldTO').toggle();
	$('#toggleTimePleaseWait').toggle();
}

ajaxGet("provider-time-off.php", 'timeoffdiv');

</script>	
<?
include "frame-end.html";
exit;	


// ***************************************************************************
// ***************************************************************************
// ***************************************************************************
// ***************************************************************************
// EVERYTHING AFTER THIS POINT IS OBSELETE
// ***************************************************************************
// ***************************************************************************
// ***************************************************************************
// ***************************************************************************


require_once "js-gui-fns.php";




if($_POST) {  // OBSELETE
	$timeoffdata = $_POST['timeoffdata'];
	$oldUnassignedAppts = getUnassignedAppointmentIDsDuringTimeOff($providerid);  // collect unassigned appts which may be reassigned
	$delta = updateTimeOffData($timeoffdata, $provider);
	$numUnassignedAppointments = applyProviderTimeOffToAppointments($provider, $oldUnassignedAppts);
	// Generate a message from the provider to the system
	$originator = getProvider($provider);
	$subject = "{$_SESSION["shortname"]}'s revised Time Off Schedule";
	$filter = "firstdayoff >= '".(date('Y-m-d'))."'";
	$msgbody = timeOffChanges($delta)."<hr><b>{$_SESSION["shortname"]}'s revised Time Off Schedule:</b>".getProviderTimeOffTable($provider, $filter, false);
	$note = "$msgbody";
	$msg = array('inbound'=>1,'datetime'=>date('Y-m-d H:i:s'),
								'transcribed'=>'','body'=>$note,'subject'=>$subject, 
								'correspaddr'=>"{$originator['fname']} {$originator['lname']} <{$originator['email']}>",
								'correspid'=>$originator['providerid'], 'correstable'=>'tblprovider',
								'originatorid'=>$originator['providerid'], 'originatortable'=>'tblprovider');
	insertTable('tblmessage', $msg, 1);
	require_once "event-email-fns.php";
	require_once "comm-fns.php";
	notifyStaff('t', $subject, $note);
	$message = "{$_SESSION["shortname"]}'s  Time Off Schedule has been saved.  Appointments unassigned: $numUnassignedAppointments.";
}


include "frame.html";
// ***************************************************************************

if($message) echo "<span style='pagenote'>$message</span><p>";
?>
<form name='timeoffscheduler' method='POST'>
<?
hiddenElement('timeoffdata', '');
// NOTE: timeoff-sitter-calendar.php itself will limit access to the provider's own schedule
echo "	
<div onclick=\"openConsoleWindow('timeoffcalendar', 'timeoff-sitter-calendar.php?provid={$provider['providerid']}&editable=1',850,700)\"
		style='display:inline-block;float:right;font-size:7pt;cursor:pointer;'>
		<img src='art/clock20.gif' title='Open the Sitter Time Off Calendar'> Click to view Sitter time off calendar.</div>
";

$pastTimeOff = getProviderTimeOffTable($provider, "firstdayoff < NOW()", $asterisk=false);
if($pastTimeOff) echo "<h3>Past Time Off</h3>$pastTimeOff<hr>";
?>
<h3>Future Time Off</h3>
<div style='display:block;' id='timeoffdiv'></div>
<hr>
<?
echoButton('', 'Save Schedule', 'submitForm()');
echo " ";
echoButton('', 'Quit', 'if(confirm("Any changes will be discarded.  Proceed?")) document.location.href="prov-time-off-page.php"');
?>
</form>

<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function addAnother(next) {
	document.getElementById("timeoffrow_"+next).style.display='<?= $_SESSION['tableRowDisplayMode'] ?>';
	document.getElementById("addanotherrow_"+next).style.display='<?= $_SESSION['tableRowDisplayMode'] ?>';
	document.getElementById("addanotherrow_"+(next-1)).style.display='none';
}

var deletions = [];
function deleteLine(number) {
	number = parseInt(number);
	// Clear line values
	if(document.getElementById("timeoffrow_"+number)) {
		document.getElementById('firstdayoff_'+number).value = '';
		document.getElementById('lastdayoff_'+number).value = '';
		if(document.getElementById('timeoff_'+number).value)
			deletions[deletions.length] = document.getElementById('timeoff_'+number).value;
		document.getElementById('timeoff_'+number).value = '';
  }
	// for number while servicecode_number(number + 1) copy line (number + 1) to number
	var lastvisibleline = number;
	for(var i=number; document.getElementById("timeoffrow_"+(i+1)); i++) {
		if(document.getElementById("timeoffrow_"+(i+1)).style.display != 'none')
		  lastvisibleline = i+1;
			document.getElementById("firstdayoff_"+i).value = document.getElementById("firstdayoff_"+(i+1)).value;
			document.getElementById("lastdayoff_"+i).value = document.getElementById("lastdayoff_"+(i+1)).value;
			document.getElementById('timeoff_'+i).value = document.getElementById("timeoff_"+(i+1)).value;
	}
	//alert(lastnumber);
	// if number is < last line then clear last line 
	if(number < lastvisibleline) {
		document.getElementById('firstdayoff_'+lastvisibleline).value = '';
		document.getElementById('lastdayoff_'+lastvisibleline).value = '';
		document.getElementById('timeoff_'+lastvisibleline).value = '';
	}		
	// set last line invisible
	if(lastvisibleline > 1) {
		document.getElementById("timeoffrow_"+lastvisibleline).style.display='none';
		document.getElementById('addanotherrow_'+(lastvisibleline)).style.display='none';
		lastvisibleline = lastvisibleline - 1;
		document.getElementById('addanotherrow_'+(lastvisibleline)).style.display='<?= $_SESSION['tableRowDisplayMode'] ?>';
	}
}

function gatherTimeOffFields() {
	var fields = 'deletions|';
	if(deletions.length > 0) fields += deletions.join('|');
	for(var i=1; document.getElementById("timeoffrow_"+i); i++)
		if(document.getElementById("timeoffrow_"+i).style.display != 'none')
			if(jstrim(document.getElementById("firstdayoff_"+i).value) && // skim out double blanks
			   jstrim(document.getElementById("lastdayoff_"+i).value)) {
				fields += ',' + document.getElementById("timeoff_"+i).value + '|' + 
								document.getElementById("firstdayoff_"+i).value + '|' +
								document.getElementById("lastdayoff_"+i).value;
				if(document.getElementById("div_timeofday_"+i))
					fields += '|'+document.getElementById("div_timeofday_"+i).innerHTML;
			}
	return fields;
	
}


function updateProviderTimeOff() {
	if(!document.getElementById('showpasttimeoff')) return;
	var showpasttimeoff = document.getElementById('showpasttimeoff').checked ? 1 : 0;
	ajaxGet('provider-time-off.php?id=<?= $id ?>&showpasttimeoff='+showpasttimeoff, 'timeoffdiv');
}

var vArgs = [];

function submitForm() {
	
	setvArgs();
	//alert(vArgs);return;
  if(MM_validateFormArgs(vArgs)) {
		document.timeoffscheduler.timeoffdata.value=gatherTimeOffFields();
		document.timeoffscheduler.submit();
	}
}

ajaxGet("provider-time-off.php", 'timeoffdiv');
		

function setvArgs() {
	if(vArgs.length > 0) return;
	for(var i=1; document.getElementById("timeoffrow_"+i); i++)
		if(document.getElementById('timeoffrow_'+i).style.display != 'none')
			if(jstrim(document.getElementById('firstdayoff_'+i).value) || // allow double blanks
				 jstrim(document.getElementById('lastdayoff_'+i).value)) {
				 vArgs = vArgs.concat(['firstdayoff_'+i, 'lastdayoff_'+i, 'inseparable',
																'firstdayoff_'+i, '', 'isDate',
																'lastdayoff_'+i, '', 'isDate',
																'firstdayoff_'+i, 'lastdayoff_'+i, 'datesInOrder']);
				 setPrettynames('firstdayoff_'+i, "Time Off Starting Date #"+i, 'lastdayoff_'+i, "Time Off Ending Date #"+i);
			}
}

<?
require_once "time-framer-mouse.php";
dumpPopCalendarJS();
dumpTimeFramerJS('timeFramer');

?>
</script>
<div style='height:300px;'></div>
<?
makeTimeFramer('timeFramer', 'narrow', null, 'clearButton');
// ***************************************************************************

include "frame-end.html";

function timeOffChanges($delta) {
	$out = '';
	if($delta['changes']) {
		$out .= "<b>Changes:</b><br>";
		foreach($delta['changes'] as $change) $out .= timeOffOneLineDescription($change)."<br>";
	}
	if($delta['deletions']) {
		$out .= "<p><b>Dropped Time Off:</b><br>";
		foreach($delta['deletions'] as $change) $out .= "<font color=red>".timeOffOneLineDescription($change)."</font><br>";
	}
	if($delta['insertions']) {
		$out .= "<p><b>New Time off:</b><br>";
		foreach($delta['insertions'] as $change) $out .= "<font color=blue>".timeOffOneLineDescription($change)."</font><br>";
	}
	return $out;
}

function getProviderTimeOffTable($prov, $filter='', $showAsterisk=false) {
	$filter = $filter ? "AND $filter" : '';
	$times = fetchAssociations("SELECT * FROM tbltimeoff WHERE providerptr = $prov $filter ORDER BY firstdayoff");
	if(!$times) return "No time off is currently scheduled.";
	$table = "<table>";
	$asterisk = false;
	foreach($times as $off) {
		$scheduledToday = substr($off['whenscheduled'], 0 , 10) == date('Y-m-d');
		$asterisk = $asterisk || $scheduledToday;
		$table .= "<tr><td>".($showAsterisk && $scheduledToday ? '[*]' : '&nbsp;')."</td><td>";
		$table .= timeOffOneLineDescription($off)."</td><tr>";
	}
	if($showAsterisk && $asterisk) $table .= "<tr><td colspan=2>[*]  = Scheduled today.</td>";
	$table .= "</table>";
	return $table;
}

	

?>
