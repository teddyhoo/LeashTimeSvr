<?
// prov-job-completion.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "day-calendar-fns.php";

// Determine access privs
$locked = locked('p-');

$max_rows = 999;

extract($_REQUEST);

$provider = $_SESSION["providerid"];

if($_POST) {
	require_once "appointment-fns.php";
	
	$allAppts = array();
	$allIds = array();
	$notedappts = array();
	foreach($_POST as $id => $unused)
	  if(strpos($id, 'job_') === 0) {
			$apptid = substr($id, 4);
			if($_POST["note_$apptid"]) $notedappts[] = $apptid;
	    else $ids[] = $apptid;
	    $allIds[] = $apptid;
		}
	$now = date('Y-m-d H:i:s');
	if($ids) {
		$strIds = join(',',$ids);
		$mods = withModificationFields(array('completed'=>$now, 'canceled'=>null));
	  updateTable('tblappointment', $mods, "appointmentid IN ($strIds)", 1);
		if($_SESSION['surchargesenabled']) markAppointmentSurchargesComplete($strIds);
		// setAppointmentDiscounts($ids, true); //  discounted charge has not changed
	}
	foreach($notedappts as $id) {
	  $note = $_REQUEST["note_$id"];
	  $mods = withModificationFields(array('completed'=>$now, 'note'=>$note, 'canceled'=>null));
//echo "NOTE: $note";exit;	//.print_r($_POST,1);exit;		  
	  updateTable('tblappointment', $mods, "appointmentid = $id", 1);
		if($_SESSION['surchargesenabled']) markAppointmentSurchargesComplete($id);
	}
	//setAppointmentDiscounts($notedappts, true); //  discounted charge has not changed
	if($allIds) {
		require_once "invoice-fns.php";
		createBillablesForNonMonthlyAppts($allIds);
		foreach($allIds as $id)
			logAppointmentStatusChange(array('appointmentid'=>$id, 'completed' => 1), "Mult visits complete - provider.");
		$_SESSION['user_notice'] = 
				"<h2 style='text-align:center'>Visits marked complete: ".count($allIds)."</h2><center>"
				.fauxLink('Continue', "$.fn.colorbox.close()", 1, null, null, 'fontSize1_6em')
				."</center>";
	}

}

$appts = array();
if($provider) {
	$appts = getProviderIncompleteJobs($provider, !isset($alldays));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r(count(countAllProviderIncompleteJobs($provider, null, !'timeWindowPast') )); }	
	$numFound = count($appts);
	$moreJobs = isset($alldays) ? 0 : countAllProviderIncompleteJobs($provider, null, !'timeWindowPast') > count($appts);
}
$searchResults = ($numFound ? $numFound : 'No')." appointment".($numFound == 1 ? '' : 's')." found.  ";
$dataRowsDisplayed = min($numFound - $offset, $max_rows);
if($numFound > $max_rows) $searchResults .= "$dataRowsDisplayed appointments shown. ";
if($numFound > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('offset'));
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).">Show Next ".min($numFound - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numFound - $numFound % $max_rows).">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
}  

$pageTitle = "{$_SESSION["shortname"]}'s Visits to be marked complete";

include "frame.html";
// ***************************************************************************
?>
<style>
.dateRow {background: lightblue;font-weight:bold;text-align:center;}
</style>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "</tr></table>";
?>

<h3 align=center><?= isset($alldays) ? "All Incomplete Visits" : "Today's Incomplete Visits" ?></h3>

<?
if($moreJobs) 
  echo "<p align=center><a href='prov-job-completion.php?alldays=1'>You have incomplete visits from previous days.  Please click here to review.</a></p>";

$columns = explodePairsLine('completed|&nbsp;||client|Client||timeofday|Time of Day||servicecode|Service||pets|Pets');
$rows = array();
$lastDate = null;
foreach($appts as $appt) {
  $appt['date'] = shortDate(strtotime($appt['date']));
	$appId = $appt['appointmentid'];
  if($lastDate != $appt['date']) {
		$lastDate = $appt['date'];
    $rows[] = array('#CUSTOM_ROW#'=>"<tr><td class='dateRow' colspan=".(count($columns)+1).">".longerDayAndDate(strtotime($lastDate))."</td></tr>");
  }
  $appt['completed'] = "<input type='checkbox' name='job_$appId' id='job_$appId'>";
  $appt['servicecode'] = fauxLink($_SESSION['servicenames'][$appt['servicecode']], "openConsoleWindow(\"editappt\", \"appointment-view.php?id=$appId\",530,450)", 1);
  $rows[] = $appt;
  if(true /*$appt['note']*/) {
		$note = safeValue($appt['note']);
		if(staffOnlyTEST()) {
		$chars = 255 - strlen((string)$note);
		$note = "<textarea cols=85 rows=2 maxlength=255
							id='note_$appId' name='note_$appId' 
							onKeyDown='noteChanged(this, \"job_$appId\")'
							onChange='noteChanged(this, \"job_$appId\")' autocomplete='off'>$note</textarea>";
    $rows[] = array('#CUSTOM_ROW#'=>"<tr><td>&nbsp;</td><td style='padding-bottom:10px;' class='sortableListCell' colspan="
    			.count($columns)."><table border=0><tr><td valign=top>Note:<br><span class='tiplooks' id='countdown_$appId'>$chars chars left</span></td><td>$note</td></tr></table></td></tr>");} else {
		$note = "<input maxlength=255 style='width:720px;' value='$note' 
							id='note_$appId' name='note_$appId' 
							onKeyDown='noteChanged(this, \"job_$appId\")'
							onChange='noteChanged(this, \"job_$appId\")' autocomplete='off'>";
    $rows[] = array('#CUSTOM_ROW#'=>"<tr><td>&nbsp;</td><td style='padding-bottom:10px;' class='sortableListCell' colspan=".count($columns).">Note: $note</td></tr>");}
	}
}


fauxLink('Check all Visits Completed', 'checkAllJobs(true)');
echo "<img src='art/spacer.gif' width=20 height=1>";
fauxLink('Un-Check all Visits', 'checkAllJobs(false)');
echo "<img src='art/spacer.gif' width=20 height=1>";
echoButton('', 'Mark all checked visits completed', 'saveJobs()');
echo "<form name='jobsform' method='POST'>";
tableFrom($columns, $rows, 'WIDTH=100%');
echo "</form>";

if($dataRowsDisplayed < 5) { ?>
<div style='height:100px;'></div>
<?
}
?>
<script language='javascript'>

function noteChanged(notefield, checkboxname) {
	if(notefield.value.length > 0)
		document.getElementById(checkboxname).checked =  true;
	var countdown, n = checkboxname.substring(checkboxname.indexOf('_')+1);
	if(countdown = document.getElementById('countdown_'+n))
		countdown.innerHTML = (255 - notefield.value.length)+' chars left';
}		

function saveJobs() {
	var n = 0;
	var el = document.jobsform.elements;
	for(var i=0;i<el.length;i++)
	  if(el[i].name.indexOf('job_') > -1 && el[i].checked) n++;
	if(n == 0) {
		alert('Please select one or more visits to mark as complete first.');
		return;
	}
	document.jobsform.submit();	
}

function checkAllJobs(state) {
	var el = document.jobsform.elements;
	for(var i=0;i<el.length;i++)
	  if(el[i].name.indexOf('job_') > -1) el[i].checked = (state ? true : false);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

</script>

<?
// ***************************************************************************

include "frame-end.html";
?>
