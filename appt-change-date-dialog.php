<? // appt-change-date-dialog.php
// use this is a light box from the appointment editor
// -- or maybe swap it in for the editor?
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "appointment-fns.php";
require_once "service-fns.php";
require_once "preference-fns.php";

if(requestIsJSON()) $_REQUEST = getJSONRequestInput();
$id = $_REQUEST['id'];
$appt = getAppointment($id, $withNames=true, $withPayableData=false, $withBillableData=false);

$results = moveEligibility($appt);
// $appt['client'], $appt['provider'], 

if(!$_REQUEST['test'] && $results['forbidden']) {
	echo "<ul><li>".join("<li>", $results['status'])."</ul>";
	exit;
}

if($_REQUEST['test']) {
	$results = moveEligibility($appt, $newDate=date('Y-m-d', strtotime($_REQUEST['test'])));
	header("Content-type: application/json");
	echo json_encode($results);
	exit;
}

if($_REQUEST['newdate']) {
	$move = moveEligibility($appt, $newDate=date('Y-m-d', strtotime($_REQUEST['newdate'])));
	if(!$move['forbidden']) {
		// array('oldAppointment'=>$oldAppt, 'newAppointment'=>$appt)
		$oldAndNew = changeAppointmentDate($id, $newDate);
		$apptid = $oldAndNew['newAppointment']['appointmentid'];
	}
	if($_POST) {
		echo "<script>
			if(window.opener && window.opener.update) window.opener.update('appointments', null);
			document.location.href='appointment-edit.php?id=$apptid';
			</script>";
		exit;
	}
	else ; // figure out what to do in case of JSON
}

function countPhrase($arr, $singular, $plural=null) {
	$count = count($arr);
	return "$count ".($count == 1 ? $singular : ($plural ? $plural : $singular.'s'));//.print_r($arr, 1)
}

function moveEligibility($appt, $newDate=null) { // return array('status'=>array(...), 'forbidden'=>true|false)
	$allowRecurringMoves = staffOnlyTEST();
	if(!$appt) $status[] = "Visit #$id not found.";
	else {
		if($appt['date'] < date('Y-m-d')) $status[] = 'Visits before today cannot be moved.';
		if($appt['canceled']) $status[] = 'Canceled visits cannot be moved.';
		if($appt['completed']) $status[] = 'Completed visits cannot be moved.';
		if($appt['recurringpackage'] && !$allowRecurringMoves) $status[] = 'Recurring visits cannot be moved.';
		else {
			$package = getPackage($appt['packageptr']);
			if($package['enddate']) {
				// for the purposes of handling onedaypackages
				if($package['enddate'] == '0000-00-00') $package['enddate'] = $package['startdate'];
				if(!($package = getCurrentNRPackage($appt['packageptr'], $appt['clientptr'])))
					 $status[] = 'No schedule found for this visit..';
			}
			else { // recurring
				$packs = getCurrentClientPackages($appt['clientptr'], 'tblrecurringpackage');
				if(!($packs))
					 $status[] = 'No schedule found for this visit.';
				else $package = $packs[0];
			}
		}
		
		$dayUncanceledSurcharges = fetchAssociations(
			"SELECT *, label
				FROM tblsurcharge s
					LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
				WHERE s.date = '{$appt['date']}' 
							AND clientptr = {$appt['clientptr']}
							AND canceled IS NULL", 1);
							
		foreach($dayUncanceledSurcharges as $surch) {
			$justification = justifySurcharge($surch);
			if(!$justification || !in_array($appt['appointmentid'], $justification))
				continue;
			if($surch['completed']) $completedSurcharges[] = $surch;
			else $uncanceledSurcharges[] = $surch;
		}
		
		//$completedSurcharges = fetchKeyValuePairs("SELECT surchargecode 
		//																				FROM tblsurcharge 
		//																			WHERE appointmentptr = {$appt['appointmentid']} AND completed IS NOT NULL");
		if($completedSurcharges)
			$status[] = 'Visits with associated completed surcharges cannot be moved.';
		$results['status'] = $status;
	}
	if($status) $results['forbidden'] = true;
	if(!$newDate) return $results;
	
	$prettyNewDate = shortDate(strtotime($newDate));
	$results = array('id'=>$id, 'newdate'=>$newDate);
	// return a JSON array indicating any consequences of moving appt to this date.
	// params: id, test(= a date)
	if($newDate < date('Y-m-d')) $status[] = 'Visits cannot be moved to the past.';
	if($newDate == $appt['date']) $status[] = "This visit is already scheduled for ".date('l', strtotime($prettyNewDate))." $prettyNewDate.";
	// $status may already be partially poulated above
	if($package && $package['enddate']) {
		if($newDate < $package['startdate']) $status[] = 'Visits cannot be moved to a date before the schedue start date.  Please edit the schedule before moving this visit.';
		if($newDate > $package['enddate']) $status[] = 'Visits cannot be moved to a date after the schedue end date.  Please edit the schedule before moving this visit.';
	}
	else if($package) { // recurring
		// should we restrict based on suspend dates?
		// startdate, suspenddate, resumedate, cancellationdate
		if($newDate < $package['startdate']) $status[] = 'Visits cannot be moved to a date before the schedue start date.  Please edit the schedule before moving this visit.';
		if($package['cancellationdate'] && $newDate >= $package['cancellationdate']) 
			$status[] = 'Visits cannot be moved to the schedue cancellation date or after.  Please edit the schedule before moving this visit.';
		if($package['suspenddate'] && $newDate >= $package['suspenddate'] && $newDate < $package['resumedate']) 
			$status[] = 'Visits cannot be moved to a date when service is suspended.  Please edit the schedule before moving this visit.';
	}
	if($status) $results['forbidden'] = true;
	if(!$status) {
		//echo "providerIsOff({$appt['providerptr']}, $newDate, {$appt['timeofday']}): [".providerIsOff($appt['providerptr'], $newDate, $appt['timeofday'])."]\n<p>";
		if($appt['providerptr'] && providerIsOff($appt['providerptr'], $newDate, $appt['timeofday']))
			$status[] = "Because of time off, {$appt['provider']} will not be able to handle this visit on $prettyNewDate, so the visit will be unasigned.";
		if(detectVisitCollision($appt, $appt['providerptr']))
			$status[] = "Because of a previously scheduled exclusive visit, {$appt['provider']} will not be able to handle this visit on $prettyNewDate, so the visit will be unasigned.";


		//$uncanceledSurcharges = fetchKeyValuePairs("SELECT surchargecode 
		//																				FROM tblsurcharge 
		//																			WHERE appointmentptr = {$appt['appointmentid']} AND canceled IS NULL");
																					
		if($uncanceledSurcharges) {
			$labels = array();
			foreach($uncanceledSurcharges as $type) $labels[] = $type['label'];
			$labels = join(', ', $labels);
			$status[] = countPhrase($uncanceledSurcharges, 'surcharge')." ($labels) associated with this visit will be deleted.";
		}
		$newAppt = array_merge($appt);
		$newAppt['date'] = $newDate;
		$newSurcharges = findApplicableSurcharges($newAppt);
		if($newSurcharges) {
			$labels = array();
			foreach($newSurcharges as $type) $labels[] = $type['label'];
			$labels = join(', ', $labels);
			$status[] = countPhrase($newSurcharges, 'surcharge')." ($labels) associated will be created as a result of moving the date.";
		}
//echo "BANG! ".print_r($results, 1);		
	}
	$results['status'] = $status ? $status : array("This visit can be moved to $prettyNewDate without any problems.");
	return $results;
}
	

$todayTile = todaysDateTable($appt['date'], "position:absolute;right:10px;top:10px;width:40px;z-index:998;'
		 																	.'border-color:brown;'
		 																	.'border-top:solid brown 1px;border-left:solid brown 1px;");

$service = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$appt['servicecode']} LIMIT 1", 1);
$service = $appt['recurringpackage'] && $appt['serviceptr'] ? "(recurring) $service" : $service;
$description = "<div style='display:block;border:solid #EEEEEE 0px'><b>{$appt['client']} Visit:</b>  {$appt['timeofday']}"
					 // &#8226; = bullet
					."<p>$service &#8226; Sitter: ".($appt['provider'] ? $appt['provider'] : 'Unassigned')
					."</div>";
?>
<header>
<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;padding:10px;}</style>
</header>
<body>
<span class='fontSize1_1em'><?= $description.$todayTile ?></span><p>

<form name='changedateform' method='POST'>
<span class='fontSize1_3em'><b>Move visit to </b> </span>
<?
//calendarSet($label, $name, $value=null, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null)
hiddenElement('originaldate', shortDate(strtotime($appt['date'])));
calendarSet('', 'newdate', shortDate(strtotime($appt['date'])), $labelClass=null, $inputClass='fontSize1_3em', true, !'secondDay', $onChange='testDate()');
?>
 <span id='dow' class='fontSize1_1em' style='color:green;font-weight:bold;'></span></span><div id='consequences'></div>
<?
if($appt['recurringpackage'] && $appt['serviceptr']) 
	echo "<p><div class='tiplooks fontSize1_1em'>Because this visit is an original part of a recurring schedule<br>
				when you change the date, the visit on ".date('M j', strtotime($appt['date']))." will be canceled<br>
				and a new visit just like it will be created on the chosen day.</div>";
echoButton('changedate', 'Change Date', 'changeDate(this)');
echo "<img src='art/spacer.gif' width=90 height=30>";
echoButton('quit', 'Quit', "document.location.href=\"appointment-edit.php?id=$id\"");
?>
</form>
<script src='popcalendar.js'></script>
<script src='check-form.js'></script>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script>
function changeDate(el) {
	var warning;
	if($(el).attr('forbidden') != 'false') {
		$('#consequences').css('color','red');
		warning = 'Please see the note.';
	}
	else {
		let newdate = mdy($('#newdate').val());
		let originaldate = mdy($('#originaldate').val());
		if(JSON.stringify(newdate) == JSON.stringify(originaldate))
			warning = 'This visit is already scheduled on '+$('#newdate').val();
	}
		
	if(!MM_validateForm(
		warning, '', 'MESSAGE',
		'newdate', '', 'R',
		'newdate', '', 'isDate',
		'newdate', '', 'isFutureDate'
		)) return;
		document.changedateform.submit();
		//alert('Okay!');
}

setPrettynames('newdate','The new date');
function testDate() {
	if(MM_validateForm(
		'newdate', '', 'R',
		'newdate', 'NOT', 'isPastDate',
		)) {
		let newdate = new Date(document.getElementById('newdate').value);
		let dows = 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'.split(',');
		document.getElementById('dow').innerHTML = dows[newdate.getDay()];
		let payload = JSON.stringify({test: $('#newdate').val(), id: <?= $id ?>});
		//alert(payload);
		$.ajax({
				url: 'appt-change-date-dialog.php',
				dataType: 'json', // comment this out to see script errors in the console
				type: 'post',
				contentType: 'application/json',
				data: payload,
				processData: false,
				success: function(resultdata, textStatus, jQxhr) { processTestResult(resultdata); },
				error: submitFailed // until I figure this out...Figured it out! ?>
				});

	}
}

function processTestResult(result) {
	$('#consequences').css('color', 'black');
	//alert(JSON.stringify(result));
	$('#consequences').html('<ul><li>'+result.status.join('<li>')+'</ul>');
	if(result.forbidden) $('#changedate').attr('forbidden', result.forbidden);
	else $('#changedate').attr('forbidden', false);
}

function submitFailed(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'
		+<?= mattOnlyTEST() ? 'errorThrown' : '"Please notify support."' ?>;
	console.log(message );
	<?= mattOnlyTEST() ? 'console.log("jqXhr: "+jqXhr);console.log("textStatus: "+textStatus);' : '' ?>
}


<? dumpPopCalendarJS(); ?>

</script>

<? /*
Rules for changing a visit date:

    A visit not yet scheduled may not be changed (EZ Schedule Visit Editor)
    A past visit date may not be changed.
    A completed visit many not be changed.
    A canceled visit many not be changed.
    A visit date may not changed to a past date.
    A recurring visit may not be changed because the daily scheduler will try to recreate the moved visit.
    A non-recurring visit may not be moved outside of the date range of the schedule.
    When changing a visit date (clicking the [Change Date] button in the calendar view:
    - Check to see if the visit will become unassigned.  Note the reason.
    - Check to see if any scheduled discount will be removed/unavailable
    - Check to see if any automatic surcharge(s) will be added or removed.  If surcharges are to be removed but are marked complete, note the reason.

In the Visit Editor, show the [Change Visit Date] button iff:

    Visit date is in the future.
    Visit is incomplete.
    Visit is nonrecurring.

[Change Visit Date] opens a "Change Day" lightbox with a calendar widget.

Change Day Lightbox (parameter: id)

    When a day is selected, make an ajax call to lightbox script (with "id=xxx&test=1") to determine
        If the visit will become unassigned (with explanation)
        If the scheduled discount (if any) will be removed/unavailable
        If any surcharges will be added or removed.  Disable the [Change Date] button if any completed surcharges are involved.
    Display any status message (including "Click [Change Date] below to change the date.", if no objections."
    Change Date button action:
        Post back to lightbox script (with "id=xxx&newdate=2019-11-12")
        Report error in lightbox and leave open.
        On success:
            if($oldproviderptr && $oldproviderptr != $providerptr)
                      makeClientVisitChangeMemo($oldproviderptr, $clientptr, $appointmentid);
            // if old appointment and new appointment's provider is not $providerptr
            if($providerptr == $oldAndNew['newAppointment']['providerptr'])
                       makeClientVisitChangeMemo($providerptr, $clientptr, $appointmentid);
            call parent.opener.update('appointments', "oldproviderptr,newproviderptr ==> in both cases max(ptr, 0) in case of unassigned")
            in parent (if lightbox), document.location.href='client-edit.php?id=..."

Light Box

New Date: [           ]  <== onChange, test=1

*/