<?
/* request-review.php
*
* Parameters:
* id - id of request to be edited.  officenotes and resolved are the only editable fields.
* updateList - list in window opener to update after save
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('+c-,+p-');

require_once "request-fns.php";

require_once "client-fns.php";
require_once "gui-fns.php";
require_once "client-profile-request-fns.php";
require_once "client-sched-request-fns.php";
require_once "client-services-fns.php";



require_once "client-schedule-fns.php";

extract($_REQUEST);

// $newVersion = mattOnlyTEST(); -- moved to request-fns.php


if(!isset($id)) $error = "Request ID not specified.";
$noEdit = $id < 0; // don't offer visit editing buttons is appointment viewer
$id = abs($id);
$source = getClientRequest($id);
if($_SESSION["clientid"] && $source['clientptr'] != $_SESSION["clientid"]) {
	echo "Invalid request.";
	exit;
}
if($_SESSION['providerid'] &&	$source['providerptr'] != $_SESSION["providerid"]) {
	echo "Invalid request.";
	exit;
}

// #############################################################################
$windowTitle = "Client Request";
$customStyles = ".sectionHead {font-size:1.1em;background:lightblue;border:solid black 1px;font-weight:bold;margin:15px;}";
if($_SESSION["responsiveClient"]) {
	$sizeKludge = !$_SESSION["deskTopUser"] ? "font-size:2.2em !important;" : '';
	$extraHeadContent = "<style>
	body {background-image:none !important; height:100%; box-sizing: border-box; $sizeKludge} 
	.tiplooks {font-size:14pt;}
	</style>"
	.'<script src="responsiveclient/assets/js/libs/jquery/jquery-1.11.2.min.js"></script>';
	$extraHeadContent .=  <<<RESPONSIVE
	<script>
	/*
	var contentWidthQuery = window.matchMedia("(max-width: 650px)");
	// Attach listener function on state changes
	contentWidthQuery.addListener(setFontSize);

	function setFontSize(query) {
		var narrow = query.matches;
		alert(narrow);
		if(narrow && !'{$_SESSION["deskTopUser"]}') {
		 $('body').css("font-size", "1.5em;");
		 //alert('narrow: font-size: '+$('body').css("font-size"));
		}
		else {
		 $('body').css("font-size", "0.7em");
		 //alert('wide: font-size: '+$('body').css("font-size"));
		}
	} 
	$(document).ready(function(){
		setFontSize(contentWidthQuery);
	});
	*/
	</script>
RESPONSIVE;
}

require "frame-bannerless.php";
echo "<style>.notelabel {width:80px;}</style>";
if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
$source = getClientRequest($id);
$source['date'] = longestDayAndDateAndTime(strtotime($source['received']));
//print_r($source);exit;
//echo "{$_SESSION["deskTopUser"]}";

$closeAction = $lightbox == 'colorbox' ? 'parent.$.fn.colorbox.close();' : 'window.close()';
?>
<div onclick='<?= $closeAction ?>' title='Close this.' style='color:#808080;cursor:pointer;position:absolute;right:3px;top:0px;font-size:3.3em;font-weight:bold;'>&#10005;<!-- &#9746 --></div> 	
<div style='padding: 10px;padding-top:0px;'>
<?

if($source['requesttype'] == 'SystemNotification') displayNotification($source, $updateList);
else if($source['requesttype'] == 'ValuePackRefills') {
	require_once "value-pack-fns.php";
	displayValuePackRefillsRequest($source, $updateList);
}
else if($source['requesttype'] == 'Reminder') displayReminder($source, $updateList);
else if($source['requesttype'] == 'VisitReport') {
	startForm($source, $updateList, 'Visit Report');
	require_once "appointment-client-notification-fns.php";
//if(mattOnlyTEST()) print_r($source);
	displayVisitReportRequest($source, $updateList);
	$noCommOrViewBox = true;
}
else {
	$noCommOrViewBox = in_array($source['requesttype'], array('TimeOff','ICInvoice'));
	displayRequestViewer($source, $updateList);
}

echo "<p align='center'>";

echo " ";

echo "</p>";
echo "</form>";


if(staffOnlyTEST()) {
	if($source['requestid']) {
		$changes = fetchAssociations(
				"SELECT * 
					FROM tblchangelog 
					WHERE itemtable = 'tblclientrequest' AND itemptr = {$source['requestid']}
					ORDER BY time ASC", 1);
		if($changes) {
			foreach($changes as $change) $ids[$change['user']] = 1;
			$mgrs = getManagers(array_keys($ids));
			foreach($changes as $change)
				$changeTable[] = array(
						'time'=>shortDateAndTime(strtotime($change['time'])),
						'admin'=>"{$mgrs[$change['user']]['fname']} {$mgrs[$change['user']]['lname']}",
						'change'=>$change['note']);
			echo "<div onclick='var dst = document.getElementById(\"changes\").style; dst.display= dst.display == \"none\" ? \"block\" : \"none\";'>...<br><div id='changes' style='display:none;'>";
			quickTable($changeTable, $extra='border=1 bordercolor=gray');
			echo "</div></div>";
			
		}
		else echo "No changes.";
	}
} // mattOnly

if($extraFields = getExtraFields($source)) {
	if($extraFields['form_referer']) {
		if(mattOnlyTEST() || dbTEST('doggiewalkerdotcom,doggywalkerarlington'))
			echo "<p>Referrer: {$extraFields['form_referer']}";
	}
}

?>

</div>

<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>

<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>




<script language='javascript'>


function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

function openComposer(clientid, lname, fname, email) {
	var args = clientid ? 'client='+clientid : 'prospect='+<?= $id ?>+'&lname='+lname+'&fname='+fname+'&email='+email;
	openConsoleWindow('emailcomposer', 'comm-composer.php?'+args,500,500);
}

<?


if($source['requesttype'] == 'Schedule') {
	$schedule = scheduleFromNote($source['note']);
}
if($source['requesttype'] == 'Profile')
	dumpphoneDisplayJS();
if($source['requesttype'] == 'VisitReport') {
	// Done above: require_once "appointment-client-notification-fns.php";
	dumpVisitReportRequestJS($source);
}
?>

</script>
</body>
</html>
<?
function startForm($source, $updateList, $header) {
	global $newVersion;
	$source['date'] = shortDate(strtotime($source['received']));
	if($newVersion) {
		$unresolvedclass = $source['resolved'] ? '' : 'boldfont';
		$resolvedclass = !$source['resolved'] ? '' : 'boldfont';
		echo "<table width=100% style='cursor:pointer''
					title='Click here to include or exclude this from the Home Page active request list.'><tr><td class='h2'>$header</td><td style='text-align:right'>";
		echo "<table border=1 bordercolor='black' align=right>";
		echo "<tr><td class='$unresolvedclass fontSize1_1em' style='text-align:center;background:yellow;' onclick='activate(true)'>ACTIVE</td></tr>";
		echo "<tr><td class='$resolvedclass fontSize1_1em' style='text-align:center;;background:lightgrey' onclick='activate(false)'>ARCHIVED</td></tr>";
		echo "</table></td></tr></table>";
	}
	else echo "<h2>$header</h2>";
	echo "<form name='requesteditor' method='POST'>";
	hiddenElement('updateList', $updateList);
	hiddenElement('requestid', $source['requestid']);
	hiddenElement('operation', '');
	hiddenElement('resolved', $source['resolved']);
}

function displayRequestViewer($source, $updateList) {
	global $apptFields, $knownSourceFields, $id, $noEdit;


	$knownSourceFields = explode(',', 'phone,fname,lname,requestid,clientptr,providerptr,whentocall,email,date'
																		.',address,street1,street2,city,state,zip,pets,note,requesttype');

	if($source['clientptr']) {
		$client = getOneClientsDetails($source['clientptr'], array('address', 'phone', 'lname', 'fname', 'activepets', 'email'));

		
		$source['address'] = $client['address'];
		$source['phone'] = $source['phone'] ? $source['phone'] : $client['phone'];
		$source['fname'] = $source['fname'] ? $source['fname'] : $client['fname'];
		$source['lname'] = $source['lname'] ? $source['lname'] : $client['lname'];
		$source['email'] = $client['email'];
		if($client['pets']) {
			if(TRUE) {
				// augment client pets with pet names
				$cps = fetchCol0(
					"SELECT CONCAT(name, IF(type IS NULL, '', CONCAT(' (', type, ')')))
						FROM tblpet WHERE ownerptr = {$source['clientptr']} AND active = 1 ORDER BY name",1);
				$petsToShow[] = join("\n",$cps);
			}
			else $petsToShow[] = join("\n",$client['pets']); // activepets
		}
		if($source['pets']) $petsToShow[] = $client['pets'] ? "(from request) {$source['pets']}" : $source['pets'];

		$source['pets'] = $petsToShow ? join("\n---------\n",$petsToShow) : '';
	}

	$requestTitles = explodePairsLine('TimeOff|Time Off Request||UnassignedVisitOffer|Unassigned Visit Offer||ICInvoice|IC Invoice');
	$title = $requestTitles[$source['requesttype']] ? $requestTitles[$source['requesttype']] : "Client Request: ".requestLabel($source);
	if($source['requesttype'] == 'ICInvoice') $title .= " #{$source['requestid']}";
	startForm($source, $updateList, $title);
	hiddenElement('clientptr', $source['clientptr']);
	if($source['providerptr']) {
		require_once "provider-fns.php";
		$pname = getProvider($source['providerptr']);
		$pname = providerShortName($pname)." ({$pname['fname']} {$pname['lname']})";
		echo "<h3>Submitted by: $pname</h3>";
	}
	$status = $source['resolution'] ? $source['resolution'] : ($source['resolved'] ? 'Resolved' : 'Pending');
	
	$statusWarning = $status == 'approved' ? '' : 
		"style='cursor:pointer;text-decoration:underline;text-decoration-style: dotted;' title='Unless a request has been APPROVED, the schedule changes or actions requested should not be assumed as complete.'";
	echo "Status: <span class='fontSize1_5em' $statusWarning>$status</span><br>";
	echo '<p>Request received: '.$source['date'];
	echo "\n<hr>\n";
	echo "\n<table width=100%>\n";
	if(FALSE) {
	}
	else {
		echo "<tr><td valign=top><table width=500>";
		echo "\n</table></td></tr>\n";

		echo "<tr><td colspan=2><table width=90%>";
		$note = $source['note'];
		if($source['requesttype'] == 'Schedule') {
			$schedule = scheduleFromNote($note);
			$note = explode("\n", $source['note']);  // $schedule['note'];, if we ever add it
			$note = urldecode($note[2]);
		}
			
		if(array_key_exists('note', $source)  && $source['requesttype'] != 'schedulechange') {
			if($source['requesttype'] == 'ICInvoice' & $noEdit) {
				$note = str_replace('"appointment-view.php?&id=', '"appointment-view.php?&id=-', $note);
			}
			else $note = str_replace("\n", '<br>', $note);
			$noteLabel = $source['requesttype'] == 'change' ? 'Requested changes:' : 'Note:';
			echo "<tr><td class='notelabel'>$noteLabel</td><td style='border:solid black 1px;'>".$note."</td></tr>";
		}
	}
	displayExtraFields($source);

	echo "</table></td></tr>"; // WHY?!!!!!




	echo "<tr><td colspan=2>&nbsp;</td></tr>";
	if($source['requesttype'] == 'cancel' || $source['requesttype'] == 'uncancel') {
		showCancellationTable($source, $source['requesttype'] == 'uncancel', 'nobuttons');
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
	}
	else if($source['requesttype'] == 'change') {
		showChangeTable($source);
	}
	else if($source['requesttype'] == 'Profile') {
		showProfileChangeDisplayTable($source);
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
	}
	else if($source['requesttype'] == 'Schedule') {
		$schedule['clientptr'] = $source['clientptr'];
	// $offerGenerateButton should probably be true when request has been declined or enddate and/or startdate are past
		showRequestCalendar($schedule, $source['clientptr']);
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
	}
	else if($source['requesttype'] == 'schedulechange') {
		require_once "request-safety.php";
		echo scheduleChangeDetail($source['requestid']);
	}
	echo "<tr><td colspan=2>";
	//labeledCheckbox('Resolved:', 'resolved', $source['resolved']);
	echo "\n</td>\n";
	echo "</tr>";
	echo "\n</table>";

}


// ***********************************************

function addressTable($label, $prefix, $client, $client0, $showSuppliedClientFieldsOnly=false) {
	$raw = explode(',', 'street1,Address,street2,Address 2,city,City,state,State,zip,ZIP');
	for($i=0;$i < count($raw) - 1; $i+=2) {
		$fields[$raw[$i]] = $raw[$i+1];
	}
	foreach($fields as $k => $v)
		if(array_key_exists($prefix.$k, $client))
			$keepGoing = 1;
	if(!$keepGoing) return;
	$i18Props = getI18NProperties() ? getI18NProperties() : array();
	foreach(array('state'=>'statelabel', 'zip'=>'zipcodelabel') as $k => $i18)
		if($i18Props[$i18]) $fields[$k] = $i18Props[$i18];
	echo "<tr><td>$label</td></tr>";
	foreach(array('zip','street1','street2','city','state') as $base) {
		$key = $prefix.$base;
		$displayFn = 'labelRow';
		if($base != 'state') $displayFn($fields[$base].':', $key, $client[$key], $client0[$key], '', 'streetInput');
		else $displayFn($fields[$base].':', $key, $client[$key], $client0[$key]);
	}
}


function displayPetByPetIdDescriptionWithNoPhoto($pets, $petId, $theseFieldsOnly=null, $heading=null) {
	global $petFields;

	if($heading) echo "<tr><td colspan=2 class='storedValue' style='font-weight:bold';>$heading</td></tr>";
	foreach($pets as $petIndex => $pet) {
		if($pet['petid'] == $petId) {
			$petIndex = $petIndex + 1;
			break;
		}
	}
	// display description in 3 column widths, with photo occupying the third width
	if($petIndex > count($pets)) {
		echo "<tr><td colspan=3>This is a new pet.</td></tr><tr><td colspan=3><hr></td></tr>";
		return;
	}
	foreach($petFields as $key=>$label)  {
		if($theseFieldsOnly && !in_array($key, $theseFieldsOnly)) continue;
		$val = $pet[$key];
		if($key == 'sex') $val = $val == 'm' ? 'Male' : ($val == 'f' ? 'Female' : 'Unspecified');
		else if($key == 'fixed' || $key == 'active') $val = $val ? 'Yes' : 'No';
		else if($key == 'dob') $val = $val ? shortDate(strtotime($val)) : '';
		echo "<tr><td class='storedValue' style='border-top: 1px solid black'>$label:</td><td class='storedValue' style='border-top: 1px solid black'>$val</td>";
		if($key == 'name' && $pet['photo']) {
			echo "<td rowspan=10 class='storedValue'>Photo Supplied</td>";
		}
		echo "</tr>";
	}
	$customFields = getCustomFields('active', 'visitsheetonly', getPetCustomFieldNames(), 'clientvisibleonly');
	$petCustomFields = getPetCustomFields($pet['petid']);
	foreach($customFields as $key=>$descr)  {
		if($theseFieldsOnly && !in_array($key, $theseFieldsOnly)) continue;
		$val = $descr[2] == 'boolean' ? ($petCustomFields[$key] ? 'yes' : 'no') :
		($descr[2] == 'boolean' ? safeValue($petCustomFields[$key]) : $petCustomFields[$key]);
		echo "<tr><td class='storedValue' style='border-top: 1px solid black'>{$descr[0]}:</td>
				<td class='storedValue' style='border-top: 1px solid black'>$val</td>";
	}
	echo "<tr><td colspan=3><hr></td></tr>";
}



function displayPetDescriptionWithPhoto($pets, $petIndex, $theseFieldsOnly=null, $heading=null) {
	global $petFields;

	if($heading) echo "<tr><td colspan=2 class='storedValue' style='font-weight:bold';>$heading</td></tr>";

	// display description in 3 column widths, with photo occupying the third width
	if($petIndex > count($pets)) {
		echo "<tr><td colspan=3>This is a new pet.</td></tr><tr><td colspan=3><hr></td></tr>";
		return;
	}
	$pet = $pets[$petIndex-1];
	foreach($petFields as $key=>$label)  {
		if($theseFieldsOnly && !in_array($key, $theseFieldsOnly)) continue;
		$val = $pet[$key];
		if($key == 'sex') $val = $val == 'm' ? 'Male' : ($val == 'f' ? 'Female' : 'Unspecified');
		else if($key == 'fixed' || $key == 'active') $val = $val ? 'Yes' : 'No';
		else if($key == 'dob') $val = $val ? shortDate(strtotime($val)) : '';
		echo "<tr><td class='storedValue' style='border-top: 1px solid black'>$label:</td><td class='storedValue' style='border-top: 1px solid black'>$val</td>";
		if($key == 'name' && $pet['photo']) {
			echo "<td rowspan=10 class='storedValue'>Photo supplied.</td>";
		}
		echo "</tr>";
	}
	$customFields = getCustomFields('active', 'visitsheetonly', getPetCustomFieldNames(), 'clientvisibleonly');
	$petCustomFields = getPetCustomFields($pet['petid']);
	foreach($customFields as $key=>$descr)  {
		if($theseFieldsOnly && !in_array($key, $theseFieldsOnly)) continue;
		$val = $descr[2] == 'boolean' ? ($petCustomFields[$key] ? 'yes' : 'no') :
		($descr[2] == 'boolean' ? safeValue($petCustomFields[$key]) : $petCustomFields[$key]);
		echo "<tr><td class='storedValue' style='border-top: 1px solid black'>{$descr[0]}:</td>
				<td class='storedValue' style='border-top: 1px solid black'>$val</td>";
	}
	echo "<tr><td colspan=3><hr></td></tr>";
}

function showChangeTable($source) {
	$scope = explode('_', $source['scope']);
	if($scope[0] == 'sole') $where = "WHERE appointmentid = {$scope[1]}";
	else if($scope[0] == 'day')
		$where = "WHERE clientptr = {$source['clientptr']} AND date = '{$scope[1]}'";
	$appts = fetchAssociations("SELECT * FROM tblappointment $where");
	echo "<tr><td id='cancelappts' colspan=2 style='border: solid black 1px;'>";
	echo "<p class='tiplooks'>Please note that you must edit the visit(s) below to make the above requested changes.</p>";
	clientScheduleTable($appts, array('buttons'));
	if($source['resolution']) echo "Resolution: request ".$source['resolution'];
	else {
		if(staffOnlyTEST()) {
			$applyNoteArg = ', "&applyNote="+document.getElementById("applyNote").checked';
			$changeLabel = "Changes Have Been Made";
		}
		else $changeLabel = "Changes Have Been Made";
		if(staffOnlyTEST() && $source['note']) {
			echo " ";
			$grouping = strpos($source['scope'], 'sole_') === 0 ? "visit" : "day's visits";
			$title = "If this box is checked, the Note above will be added to the $grouping when [$changeLabel] button is clicked.";
			labeledCheckbox("Add the note above to the $grouping", 'applyNote', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title);
		}
	}
	echo "</td></tr>";
}


// SCHEDULE REQUEST START
function showRequestCalendar($schedule, $clientptr) { // showlive (if supplied) is a client ID  // scheduleFromNote
	// copied/merged from client-sched-request-fns.php, client-sched-preview-popup.php
	global $showlive, $globalServiceSelections;
	$showlive = $clientptr;
	$start = $schedule['start'];
	$end = $schedule['end'];
	$price = $schedule['totalCharge'];
	// days = day|day|...
	// day = service,service,...
	// service = timeofday#servicecode
	$days = array();
	foreach($schedule['services'] as $day) {
		$servs = array();
		foreach($day as $group) $servs[] = $group['timeofday'].'#'.$group['servicecode'];
		$days[] = join(',',$servs);
	}

	$month = date('F', strtotime($start));
	$rowHeight = 100;
	echo <<<STYLE
	<style>
	.previewcalendar { background:white;width:100%;border:solid black 2px;margin:5px; }

	.previewcalendar td {border:solid black 1px;width:14.29%;}
	.app {border:solid black 1px;background:#B7FFDB;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}
	.empty {border:solid black 1px;background:lightgrey;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

	.month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

	.dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
	.daynumber {font-size:1.5em;font-weight:bold;text-align:right;display:block;}
	.scheduled {color:#666666;}
	.canceled {color:red;text-decoration:line-through;}
	.proposed {font-weight:bold;}
	</style>
STYLE;
	$numAppointments = 0;
	$noVisitDays = 0;
	$scheduleDays = 0;
	foreach($days as $appts) {
		if($appts) $numAppointments += count(explode(',', $appts));
		else $noVisitDays++;
		$scheduleDays++;
	}

	$globalServiceSelections = array_flip(getClientServices());
	$globalActualServiceSelections = getAllServiceNamesById();
	$currencyMark = getCurrencyMark();

	if($showlive) { ?>
	<!-- span id='legendlabel' style='font-weight:bold;color:green;' onclick="document.getElementById('legend').style.display='block';this.style.display='none';">Legend >></span>
	<div id='legend' style='display:none;padding:5px;width:160px;border: solid 1px black;' onclick="document.getElementById('legendlabel').style.display='inline';this.style.display='none';">
	<span  style='font-weight:bold;color:green;'><< Legend:</span><br><span class='proposed'>Requested Visit</span><br><span class='scheduled'>Existing visit</span><br><span class='canceled'>Canceled Existing visit</span>
	</div -->
	<span>Legend: </span> <span class='proposed'>Requested Visit</span>&nbsp;-&nbsp;<span class='scheduled'>Existing visit</span>&nbsp;-&nbsp;<span class='canceled'>Canceled Existing visit</span>
	<?
	}
	?>
	<table style='font-size:1.4em;width:100%;text-align:center;'>
	<tr><td colspan=3>
	Schedule starts on : <b><?= longDayAndDate(strtotime($start)) ?></b> and ends on <b><?= longDayAndDate(strtotime($end))."</b> ($scheduleDays days)<p>" ?>
	</td></tr>
	<? if(!$_SESSION['preferences']['suppressClientSchedulerPriceDisplay'])
			$priceCell = '';
		 else $priceCell = "<td>Price: ".$currencyMark.number_format($price, 2)."<td>"
	?>
	<tr><td>Visits: <?= $numAppointments ?></td><td>Days without visits: <?= $noVisitDays ?></td><?= $priceCell ?><td></tr>
	</table>


	<table class='previewcalendar'>
	<?
	$start = date('Y-m-d',strtotime($start));
	$end = date('Y-m-d',strtotime($end));
	$apptdays = $days;
	$month = '';
	$dayN = 0;
	for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
		$dow = date('w', strtotime($day));
		if($month != date('F', strtotime($day))) {
			if($dow && $month) {  // finish prior month, if any
				for($i=$dow; $i < 7; $i++) echo "<td>&nbsp;</td>";
				echo "</tr>";
			}
			$month = date('F', strtotime($day));
			echoMonthBar($month);
			echo "<tr>";
			for($i=0; $i < $dow; $i++) echo "<td>&nbsp;</td>";
		}
		if(!$dow) echo "</tr><tr>";
		if($showlive) echoDayBoxWithExistingVisits($day, $apptdays[$dayN]);
		else echoDayBox($day, $apptdays[$dayN]);
		$dayN++;
	}
	if($dow && $month) {  // finish prior month, if any
		for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
		echo "</tr>";
	}
	?>
	</table>
	<?	
}

function echoMonthBar($month) {
	$days = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
	echo "<tr><td class='month' colspan=7>$month</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function echoDayBoxWithExistingVisits($day, $appts) {
	global $globalServiceSelections, $globalActualServiceSelections, $showlive;
	$dom = date('j', strtotime($day));
	$appts = explode(',', $appts);
	foreach(getClientApptsForDay($showlive, $day) as $appt) $appts[] = $appt;
	if($appts) usort($appts, 'apptStartTimeSort');
	$class = $appts && $appts[0] ? 'app' : 'empty';
	echo "<td class='$class'><div class='daynumber'>$dom</div>";
	if($class == 'empty') echo "<span style='color:red'>No visits.</span>";
	else echo "<span style='color:blue'>".count($appts)." visit".(count($appts) == 1 ? '' : 's').".</span>";
	foreach($appts as $timeAndService) {
		if(!$timeAndService) continue;
		$pair = explode('#', $timeAndService);
		if(count($pair) == 2) echo "<hr><span class='proposed'>{$pair[0]}<br>{$globalServiceSelections[$pair[1]]}</span>\n";
		else echo
			"<hr><span class={$pair[2]}>{$pair[0]}<br>{$globalServiceSelections[$pair[1]]}</span>\n";
	}
	echo "</td>";
}

function getClientApptsForDay($clientptr, $day) {
	return fetchCol0(
		"SELECT CONCAT_WS('#', timeofday, servicecode, IF(canceled, 'canceled', 'scheduled'), IF(providerptr=0, 'Unassigned', IFNULL(nickname, CONCAT_WS(' ', fname, lname))))
			FROM tblappointment
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE clientptr = $clientptr AND date = '$day'");
}

function echoDayBox($day, $appts) {
	global $globalServiceSelections;
	$dom = date('j', strtotime($day));
	$appts = explode(',', $appts);
	if($appts) usort($appts, 'apptStartTimeSort');
	$class = $appts && $appts[0] ? 'app' : 'empty';
	echo "<td class='$class'><div class='daynumber'>$dom</div>";
	if($class == 'empty') echo "<span style='color:red'>No visits.</span>";
	else echo "<span style='color:blue'>".count($appts)." visit".(count($appts) == 1 ? '' : 's').".</span>";
	foreach($appts as $timeAndService) {
		if(!$timeAndService) continue;
		$pair = explode('#', $timeAndService);
		echo "<hr>{$pair[0]}<br>{$globalServiceSelections[$pair[1]]}\n";
	}
	echo "</td>";
}

function apptStartTimeSort($a, $b) {
	return strcmp(getApptStartTime($a), getApptStartTime($b));
}
	
function getApptStartTime($s) {
	$end = strpos($s, '-');
	return date('H:i', strtotime(substr($s, 0, $end)));
}
// SCHEDULE REQUEST END
