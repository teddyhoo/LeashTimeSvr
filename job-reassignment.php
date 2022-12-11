<?
// job-reassignment.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
$iPadReassignmentTweaksEnabled = /*$_SESSION['frameLayout'] == 'fullScreenTabletView' &&*/ isIPad();
$touchPunchReassignmentTweaksEnabled = !$iPadReassignmentTweaksEnabled  && (agentIsATablet());
															  
//$TABLET = agentIsATablet() ? '[TABLET]' : '[REG]';
if($iPadReassignmentTweaksEnabled) include "ipad-dragdrop-fns.php";  // WARNING: A CHANGE HERE MUST BE MADE IN appointment-dragdrop-ajax.php AS WELL
else if($touchPunchReassignmentTweaksEnabled) include "touchpunch-dragdrop-fns.php";  // WARNING: A CHANGE HERE MUST BE MADE IN appointment-dragdrop-ajax.php AS WELL
else include "dragdrop-fns.php";

// Determine access privs
$locked = locked('o-');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');


extract($_REQUEST);

//$activeProviderSelections = array_merge(array('--Select a Sitter--' => ''), getActiveProviderSelections());


$pageTitle = "Job Reassignment <img title='Remember to click \"Make All Reassignments\"' src='art/reassignmentspending.gif' id='pendingnotice' style='position:relative;top:11px;display:none'>";
include "frame.html";
if($roDispatcher) {
	echo "Insufficient access rights.";
	include "frame-end.html";
	exit;
}

// ***************************************************************************
?>
<style>
.daybanner {text-align:center;font-weight:bold;background:lightblue;border:solid black 2px;}
</style>
<script language='javascript' src='check-form.js'></script>
<?
$fromprov = isset($fromprov) ? $fromprov : 0;
echo "<script language='javascript'>var initialprovider = ".($fromprov ? $fromprov : 0).";</script>\n"; 

$date = isset($date) ? $date : shortDate();

dropOldReassignments();

echoAppointmentReassignmentForm($fromProv, array($date, $date), "style='padding:10px;cellspacing:5;cellpadding:5;width: 100%;'");

?>

<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
<? dumpPopCalendarJS(); ?>
setPrettynames('date','Date');
function update(target, val) { // called by appointment-edit
	refresh(); // implemented below
}

function openWAG(provs) {
	if(!MM_validateForm(
					'starting','','R', 
					'starting','','isDate', 
					'starting','not','isPastDate',
					'ending','','isDate', 
					'ending','not','isPastDate'					
					)) return;
	LastWAGProvs = provs;
	openConsoleWindow("wag", "wag.php?starting="+document.reassignmentform.starting.value+"&providers="+provs+"&showreassignments=1",750,700);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function updatePendingNotice(unused, show) {
	$('#pendingnotice').css('display',(show ? 'inline' : 'none'));
}

updatePendingNotice(null, <?= fetchRow0Col0("SELECT count(*) FROM relreassignment") ? 'true' : 'false' ?>);
</script>

<?
if($iPadReassignmentTweaksEnabled) 
	echo "<script language=javascript src='webkitdragdrop.js'></script>
				<script language=javascript src='ipad-appointment-dragdrop.js'></script>
				";
else if($touchPunchReassignmentTweaksEnabled) 
	echo '<link href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/base/jquery-ui.css" rel="stylesheet" type="text/css" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/jquery-ui.min.js"></script>
<script src="jquery.ui.touch-punch.min.js"></script>
<script language=javascript src="touchpunch-appointment-dragdrop.js"></script>
				';
else echo "<script language=javascript src='appointment-dragdrop.js'></script>";
?>
<img src='art/spacer.gif' width=1 height=200>
<?
include "js-refresh.php";
if(FALSE && mattOnlyTEST()) {
?>
<script language=javascript>
function updateSecondDate(secondElId, dateval, firstEl) {
	if(!dateval) dateval = dateFromElement(firstEl);
	//var val2 = document.getElementById(secondElId).value;
	//if(!val2) return;
	//val2 = getDateForString(val2, "mm/dd/yyyy");
	//if(!val2) return;
	//if(dateval > val2) document.getElementById(secondElId).value = firstEl.value;
	var val2 = document.getElementById(secondElId);
	if(!val2) return;
	val2 = val2.value;
	if(val2) {
var before = val2;
	val2 = getDateForString(val2, "mm/dd/yyyy");
alert('['+before+']'+val2);
		if(!val2) return;
	}
	if(!val2 || dateval > val2) document.getElementById(secondElId).value = firstEl.value;
}	

function getDateForString(datestr, format) {
				dateFormat = format;
				formatChar = ' ';
				aFormat = dateFormat.split(formatChar);
				if (aFormat.length < 3) {
					formatChar = '/';
					aFormat = dateFormat.split(formatChar);
					if (aFormat.length < 3) {
						formatChar = '.';
						aFormat = dateFormat.split(formatChar);
						if (aFormat.length < 3) {
							formatChar = '-';
							aFormat = dateFormat.split(formatChar);
							if (aFormat.length < 3) {
								formatChar = '';					// invalid date format
							}
						}
					}
				}

				tokensChanged = 0;

				if (formatChar != "") {
					aData =	datestr.split(formatChar);			// use user's date
					for (i=0; i<3; i++) {
						if ((aFormat[i] == "d") || (aFormat[i] == "dd")) {
							dateSelected = parseInt(aData[i], 10);
							tokensChanged++;
						} else if ((aFormat[i] == "m") || (aFormat[i] == "mm")) {
							monthSelected = parseInt(aData[i], 10) - 1;
							tokensChanged++;
						} else if (aFormat[i] == "yyyy") {
							yearSelected = parseInt(aData[i], 10);
							tokensChanged++;
						} else if (aFormat[i] == "mmm") {
							for (j=0; j<12; j++) {
								if (aData[i] == monthName[language][j]) {
									monthSelected=j;
									tokensChanged++;
								}
							}
						} else if (aFormat[i] == "mmmm") {
							for (j=0; j<12; j++) {
								if (aData[i] == monthName2[language][j]) {
									monthSelected = j;
									tokensChanged++;
								}
							}
						}
					}
				}

				if ((tokensChanged != 3) || isNaN(dateSelected) || isNaN(monthSelected) || isNaN(yearSelected)) {
				  return null;
				}
				else {
alert(yearSelected+'-'+monthSelected+'-'+dateSelected);
 					var dd = new Date();
					dd.setFullYear(yearSelected);
					dd.setMonth(monthSelected, dateSelected);
					//dd.setDate(dateSelected);
					return dd;
				}
 }
</script>
<?
}
// ***************************************************************************
if(!$touchPunchReassignmentTweaksEnabled && strpos($_SERVER["HTTP_USER_AGENT"], 'ndroid')) 
	$_SESSION['user_notice'] = 
		"<h1>WARNING:<p>The essential drag and drop functionality of this page will not work on your mobile device.
		<p>Please use a supported non-mobile device (or an iPad) or use one of LeashTime's other methods to reassign visits.</h1>";
include "frame-end.html";
?>
