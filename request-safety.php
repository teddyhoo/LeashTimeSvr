<? // request-safety.php

function showScheduleChangeTable($source, $noButtons=false) {
	// show a list of visits
	// above the list for Change requests, a note reminds the mgr that changes must be made manually
	// Column order: Date, Time Window, Service, Sitter, Charge, Pets
	// each visit will have a button to execute the cancel/uncancel individually (NOT for changes)
	// upon execution, the row will update to reflect the current state of the visit
	// for missing visits, the "shadow" row will be shown, shaded in gray
	// for all existing visits
	
	// NOTE: Neither visits nor originals should be assumed to be in any particular order
	
	$details = getHiddenExtraFields($source);
	
	$visits = json_decode($details['visitsjson'], 'assoc');
	$visitnotes = array();
	foreach($visits as $visit) {
		if($visit['note']) 
			$visitnotes[$visit['id']] = $visit['note'];
	}
	$originals = json_decode($details['origvisits'], 'assoc');
	uasort($originals, 'cmpdatetime');
	
	$labels = array('cancel'=>'Cancellation', 'uncancel'=>'Uncancellation', 'change'=>'Change');
	$verbs = array('cancel'=>'Cancel', 'uncancel'=>'Uncancel', 'change'=>'Change');
	$request['subject'] = "{$labels[$changes['changetype']]} Request from {$client['fname']} {$client['lname']}";;

	$changetype = $details['changetype'];
	$operation = $verbs[$changetype];
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype", 1);
	
	$showCharges = !getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay');
	
	foreach(json_decode($details['visitsjson'], 'assoc') as $vc) 
		$visitChanges[$vc['id']] = $vc;
	$appts = fetchAssociationsKeyedBy($sql = 
		"SELECT appointmentid, a.*,
				CONCAT_WS(' ', p.fname, p.lname) as sitter,
				s.label as service
			FROM tblappointment a
				LEFT JOIN tblprovider p ON providerid = providerptr
				LEFT JOIN tblservicetype s ON servicetypeid = servicecode
			WHERE appointmentid IN (".join(',', array_keys($visitChanges)).")
			ORDER BY date, starttime", 'appointmentid', 1);
	
	echo "<tr><td id='cancelappts' colspan=2 style='border: solid black 1px;'>";
	$origSummary = fauxLink(" 	&#x25B2; View visits as they were", "showOriginal(0)", 1, $origTitle, null, !'fontSize1_4em');
	if($source['fname'] && $source['lname']) echo "Request submitted by {$source['fname']} {$source['lname']}<br>";
	$visitCountLabel = count($visitChanges) == 1 ? 'visit' : count($visitChanges)." visits";
	echo "<h3>Request to <span class='fontSize1_4em'>{$operation}</span> the following $visitCountLabel:   $origSummary</h3>";
//print_r($originals);
	$requestResolution = $source['resolution'];
	if($noButtons || $requestResolution) 
		echo "Resolution: request ".($requestResolution ? $requestResolution : 'declined')."<p>";
	/*else */if($appts) {
		if($changetype != 'change' && !$requestResolution) {
			fauxLink('Select All Visits', "$(\".visit\").prop(\"checked\", 1)");
			echo " - ";
			fauxLink('Deselect All Visits', "$(\".visit\").prop(\"checked\", 0)");
		}
		
		echo "<table style='width:100%;background:white;'>";
		// Details = (maybe) checkbox and original details icon
		$cols = explode(',', '&nbsp;,&nbsp;,Time,Service,Pets,Sitter');
		if($showCharges) $cols[] = 'Charge';
		echo "<tr>";
		foreach($cols as $col) echo "<th>$col</th>";
		echo "</tr>";
		foreach($originals as $orig) {
			if($date != $orig['date']) {
				$date = $orig['date'];
				echo "<tr><th style='text-align:center;background:lightblue;' colspan=".count($cols).">".shortDateAndDay(strtotime($date))."</th></tr>";
				$oddrow = false;
			}
				
			$apptid = $orig['appointmentid'];
//echo "<tr><td>".print_r($appts, 1);			
			$origLinkClass = 'fontSize1_4em';
			if(!($appt = $appts[$apptid])) {
				$origLink = fauxLink(" 	&#x2327;", "showOriginal($apptid)", 1, 'View this visit as it was when the request was submitted.', null, $origLinkClass);
				echo "<tr style='background:#ababab'><td>&nbsp;</td><td>$origLink</td>
						<td colspan=".(count($cols)-1)."><span class='fontSize1_4em'>&#x21e6; </span>
							Click to see DELETED VISIT details: {$orig['timeofday']} {$services[$orig['servicecode']]} </td></tr>";
				$oddrow = false;
			}
			else {
				$origTitle = 'View this visit as it was when the request was submitted.';
				if($visitnotes[$apptid]) {
					$origLinkClass .= "  warning";
					$origTitle .= ' CLICK TO VIEW CHANGE NOTE.';
				}
				$oddrow = !$oddrow;
				$rowclass = $appt['completed'] 
									? 'completedtask' 
									: ($appt['canceled'] ? 'canceledtask' : 'noncompletedtask');
				if(!$oddrow) $rowclass .= 'EVEN';
				$origLink = fauxLink(" 	&#x25B2;", "showOriginal($apptid)", 1, $origTitle, null, $origLinkClass);
				echo "<tr class='$rowclass'>";
				echo $changetype == 'change' || $requestResolution ? "<td>&nbsp;</td>" : "<td>".selectionBox($apptid)."</td>";
				$service = visitLink($appt);
				$chargeCell = $showCharges ? "<td>".dollarAmount($appt['charge']+$appt['adjustment'])."</td>" : '';
				echo "<td>$origLink</td><td>{$appt['timeofday']}</td><td>$service</td><td>{$appt['pets']}</td>
							<td>{$appt['sitter']}</td>$chargeCell";
				echo "</tr>";
			}
		}
		echo "</table>";
	}

	if(!$requestResolution) {
		if($changetype == 'uncancel') {
			echoButton('','Un-Cancel Selected Visits', "cancelSelectedAppointments({$source['requestid']})");
		}
		else if($changetype == 'cancel') {
			echoButton('','Cancel Selected Visits', "cancelSelectedAppointments({$source['requestid']})");
			if(staffOnlyTEST() || dbTEST('sarahrichpetsitting')) {
				labeledCheckbox('... and set sitter to unassigned', 'unassign', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title='When canceling, unassign the sitter as well.');
				echo "&nbsp;";
			}
		}
		else 
			echo "<span class='tiplooks'>Make Changes Manually.  Then Resolve and Save below - </span>";
		echo " ";
		echoButton('','Decline Request', "declineOrHonorRequest({$source['requestid']}, 0)");
	}
	echo "</td></tr>";
}

function cmpdatetime($a, $b) {
	$a = strtotime($a['date']." ".substr($a['timeofday'], 0, strpos($a['timeofday'], '-')));
	$b = strtotime($b['date']." ".substr($b['timeofday'], 0, strpos($b['timeofday'], '-')));
	return $a > $b ? 1 : ($a < $b ? -1 : 0);
}

function scheduleChangeDetail($requestORrequestid, $visitid=null) {
	$request = is_array($requestORrequestid) ? $requestORrequestid 
		: fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestORrequestid LIMIT 1", 1);
	if($request['requesttype'] != 'schedulechange') {
		echo "wrong database?";
		exit;
	}
	$details = getHiddenExtraFields($request);
//return print_r($details, 1);	
	
	$visits = json_decode($details['visitsjson'], 'assoc');
	$visitnotes = array();
	foreach($visits as $visit) {
		if($visit['note']) 
			$visitnotes[$visit['id']] = $visit['note'];
		$visitids[] = $visit['id'];
	}
	$exists = fetchKeyValuePairs(
			"SELECT appointmentid, 1 FROM tblappointment WHERE appointmentid IN (".join(',', $visitids).")", 1);
	$originals = json_decode($details['origvisits'], 'assoc');
	uasort($originals, 'cmpdatetime');
	$received = strtotime($request['received']);
	require_once "appointment-fns.php";
	
	ob_start();
	ob_implicit_flush(0);
	global $requestTypes;
	echo "<b>Visit Status at time of {$requestTypes[$details['changetype']]} request (".shortDate($received)." ".date('h:i a', $received)."):</b><p>";
	if(!$visitid && $request['note']) 
		echo "<b>Request note</b><div style='width:95%;border:solid gray 1px;padding:3px;'>{$request['note']}</div>";
	echo "<table style='width:100%;background:white;'>";
	// Details = (maybe) checkbox and original details icon
	$cols = explode(',', 'Time,Service,Pets');
	$showCharges = userRole() != 'c' && !getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay');
	$showSitters = userRole() != 'c';
	if($showSitters) $cols[] = 'Sitter';
	if($showCharges) $cols[] = 'Charge';
	$columnCount = count($cols);
	echo "<tr>";
	foreach($cols as $col) echo "<th>$col</th>";
	echo "</tr>";
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype", 1);
	foreach($originals as $orig) {
		list($start, $end) = explode(',',$orig['timeofday']);
		$orig['starttime'] = date('H:i:s', strtotime($start));
		$orig['endtime'] = date('H:i:s', strtotime($end));
		$futurity = appointmentFuturity($orig, $received);
		$apptid = $orig['appointmentid'];
		if($visitid && $visitid != $apptid) continue;
		if($date != $orig['date']) {
			$date = $orig['date'];
			echo "<tr><th style='text-align:center;background:lightblue;' colspan=$columnCount>".shortDateAndDay(strtotime($date))."</th></tr>";
			$oddrow = false;
		}
		$oddrow = !$oddrow;
		$rowclass = $orig['completed'] 
							? 'completedtask' 
							: ($orig['canceled'] ? 'canceledtask'
							: ($futurity == -1 ? 'noncompletedtask' : 'futuretask'));
		if(!$oddrow) $rowclass .= 'EVEN';
		echo "<tr class='$rowclass'>";
		$service = $services[$orig['servicecode']];
		if($showSitters) {
			$sitter = $orig['providerptr'] 
			? fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$orig['providerptr']} LIMIT 1", 1)
			: 'Unassigned';
			$sitterCell	= "<td>$sitter</td>";
		}
		if($orig['adjustment']) $chargeTitle = "charge: ".dollarAmount($orig['charge'])."+ adjustment: ".dollarAmount($orig['adjustment']);
		else $chargeTitle = '';
		$chargeTitle = $chargeTitle ? "title='$chargeTitle'" : '';
		$chargeCell = $showCharges ? "<td $chargeTitle>".dollarAmount($orig['charge']+$orig['adjustment'])."</td>" : '';
		echo "<td>{$orig['timeofday']}</td><td>$service</td><td>{$orig['pets']}</td>
					$sitterCell$chargeCell";
		echo "</tr>";
		
		if($orig['note']) echo "<tr class='$rowclass'><td colspan=$columnCount>Note: {$orig['note']}</td></tr>";
		if($visitnotes[$apptid]) echo "<tr class='$rowclass'><td colspan=$columnCount><span style='color:red;font-weight:bold;'>Change note:</span> {$visitnotes[$apptid]}</td></tr>";
		if(!$exists[$apptid]) echo "<tr class='$rowclass'><td colspan=$columnCount>THIS VISIT [#$apptid] WAS LATER DELETED.</td></tr>";
	}
	echo "</table>";
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function selectionBox($apptid) {
	return labeledCheckbox($label, "visit_$apptid", $value=$apptid, $labelClass=null, $inputClass='visit', $onClick=null, $boxFirst=true, $noEcho=true, $title=null);
}

function visitLink($appt) {
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	$editScript = $roDispatcher ? "appointment-view.php?id=" : "appointment-edit.php?id=";
	$editScript .= $appt['appointmentid'];
	
	return fauxLink($appt['service'], 
		"openConsoleWindow(\"editappt\", \"$editScript\", {$_SESSION['dims']['appointment-edit']})", 1, "Edit this visit");
}



function clientScheduleChangeDetailEditTable($appts, $includeNoteInputs=true, $operation=null) {
	require_once "appointment-fns.php";
	
	ob_start();
	ob_implicit_flush(0);
	hiddenElement("operation", $operation);
	echo "<table style='width:100%;background:white;border:solid lightgrey 1px'>";
	// Details = (maybe) checkbox and original details icon
	$cols = explode(',', 'Time,Service,Pets');
	if(FALSE) $cols[] = 'Sitter';
	$columnCount = count($cols);
	echo "<tr>";
	foreach($cols as $col) echo "<th>$col</th>";
	echo "</tr>";
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype", 1);
	foreach($appts as $appt) {
		$apptid = $appt['appointmentid'];
		if($date != $appt['date']) {
			$date = $appt['date'];
			echo "<tr><th style='text-align:center;background:lightblue;' colspan=$columnCount>".shortDateAndDay(strtotime($date))."</th></tr>";
			$oddrow = false;
		}
		$oddrow = !$oddrow;
		$rowclass = $appt['completed'] 
							? 'completedtask' 
							: ($appt['canceled'] ? 'canceledtask'
							: 'futuretask');
		if(!$oddrow) $rowclass .= 'EVEN';
		echo "<tr class='$rowclass' style='border-top:solid lightgrey 1px;'>";
		$service = $services[$appt['servicecode']];
		//$sitter = $appt['providerptr'] 
		//	? fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$appt['providerptr']} LIMIT 1", 1)
		//	: 'Unassigned';
		// $sitterCell = "<td>$sitter</td>";
		//if($appt['adjustment']) $chargeTitle = "charge: ".dollarAmount($appt['charge'])."+ adjustment: ".dollarAmount($appt['adjustment']);
		//else $chargeTitle = '';
		//$chargeTitle = $chargeTitle ? "title='$chargeTitle'" : '';
		//$chargeCell = $showCharges ? "<td $chargeTitle>".dollarAmount($appt['charge']+$appt['adjustment'])."</td>" : '';
		echo "<td>{$appt['timeofday']}</td><td>$service</td><td>{$appt['pets']}</td>
					$sitterCell$chargeCell";
		hiddenElement("visit_{$appt['appointmentid']}", $appt['appointmentid']);
		echo "</tr>";
		
		if($includeNoteInputs) 
			echo "<tr class='$rowclass'><td colspan=$columnCount>"
				.labeledInput('Note:', "visitnote_{$appt['appointmentid']}", $value=null, $labelClass=null, $inputClass='VeryLongInput requestorchangenote', $onBlur=null, $maxlength=null, $noEcho=true)
				."</td></tr>";
		else hiddenElement("visitnote_{$appt['appointmentid']}", $appt['appointmentid']);
	}
	echo "</table>";
	$out = ob_get_contents();
	ob_end_clean();
	echo $out;
}


function dumpClientScheduleChangeJS($options=null) {
	if(!($visitImminentMessage = $_SESSION['preferences']['visitImminentMessage']))
		$visitImminentMessage = 
			'Please note that owing to the lateness of this request, we may not be able to change this visit.';
	if(!($multiVisitsImminentMessage = $_SESSION['preferences']['multiVisitsImminentMessage']))
		$multiVisitsImminentMessage = 
			'Please note that owing to the lateness of this request, we may not be able to change these visits.';
	if($options) {
		if($options['backURL'])	
			$optionalParams[] = "back=".urlEncode($options['backURL']);
		$optionalParams = "&".join('&', $optionalParams);
	}
	echo <<<JS

function generateScheduleChangeRequest(operation, urlOnly) {
	var ids = [];
	var pastVisits = 0;
	var soonVisits = 0;
	var warning = [];
	var eol = String.fromCharCode(13);
	var optionalParams = '$optionalParams';
	$('.visitcheckbox').each(function(index, el) {
			if(el.checked) {
				ids[ids.length] = el.id.substr("visit_".length);
				if($(el).hasClass('visitIsInThePast')) pastVisits += 1; //"visitIsInThePast" "visitIsSoon"
				if($(el).hasClass('visitIsSoon')) soonVisits += 1; //"visitIsInThePast" "visitIsSoon"
			}
		});
	if(ids.length == 0) alert("Please choose at least one visit first.");
	else {
		if(pastVisits > 0) warning.push(pastVisits+" of the selected visits "+isAre(pastVisits)+" in the past.");
		if(soonVisits > 0) {
			warning.push(soonVisits+" of the selected visits "+isAre(soonVisits)+" imminent.");
			warning.push('$multiVisitsImminentMessage');
		}
		if(warning.length > 0) {
			warning = warning.join(eol, warning)
								+ eol+eol+"Because of this, there may be some difficulty in making schedule changes for these visits.";
			alert(warning);
		}
		ids = ids.join(',');
		var url = "client-own-schedule-change-request.php?op="+operation+"&lightbox=1&&ids="+ids+optionalParams
		if(typeof urlOnly == 'undefined' || !urlOnly)
			document.location.href=url;
		else 
			return url; //lightBoxIFrame(url+"lightbox=1", 300, 200)
	}
}


function isAre(num) { return num == 1 ? "is" : "are"; }

function visitCheckBoxClicked(cbox) {
	if(cbox.checked) {
		var msg = false;
		if($(cbox).hasClass('visitIsInThePast')) msg = 'This visit is in the past.';
		if($(cbox).hasClass('visitIsSoon')) msg = '$visitImminentMessage';
		if(msg) alert(msg);
	}
}

JS;
}


function dumpScheduleChangeJS() {
	global $id, $source;
	if($source['requesttype'] == 'cancel' && anyVisitsInScopeMarkedComplete($source))
		$CANCELWARNINGJS = "if(!confirm('One or more of these visits has been marked complete.'+'    '+'Click OK to Cancel Visit(s) anyway.')) return;";
	echo <<<JS
function cancelSelectedAppointments(requestid) { // handles cancel and uncancel
	var ids = [];
	$('.visit').each(function(index, el) {
			if(el.checked) ids[ids.length] = el.id.substr("visit_".length);
		});
	if(ids.length == 0) {
		alert("Please choose at least one visit first.");
		return;
	}
	ids = ids.join(',');
	
	$CANCELWARNINGJS

	// borrowed from cancelAppointments(id)
	var xh = getxmlHttp();
//alert("appointment-request-cancel-ajax.php?request="+id);
	var unassign = $('#unassign').prop('checked') ? '1' : '0';
//alert(unassign);
	xh.open("GET","appointment-request-cancel-ajax.php?request="+requestid+"&ids="+ids+"&unassign="+unassign,true);
	xh.onreadystatechange=function() { if(xh.readyState==4) {
		document.getElementById("cancelappts").innerHTML=xh.responseText;
		updateOpenerAndClose();
	}
	}
  xh.send(null);
}

function showOriginal(apptid) {
	var url = "request-edit.php?scheduleChangeDetail=$id&visitid="+apptid;
	var boxheight = apptid == 0 ? 500 : 250;
	$.fn.colorbox({href:url, width:"550", height:boxheight, scrolling: true, opacity: "0.3"});
}
	
JS;
}

function scheduleNotificationSummary($requestORrequestid, $visitid=null) {
	$request = is_array($requestORrequestid) ? $requestORrequestid 
		: fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestORrequestid LIMIT 1", 1);
	if($request['requesttype'] != 'schedulechange') {
		echo "wrong database?";
		exit;
	}
	$details = getHiddenExtraFields($request);
//return print_r($details, 1);	
	
	$visits = json_decode($details['visitsjson'], 'assoc');
	$visitnotes = array();
	foreach($visits as $visit) {
		if($visit['note']) 
			$visitnotes[$visit['id']] = $visit['note'];
		$visitids[] = $visit['id'];
	}
	$exists = fetchKeyValuePairs(
			"SELECT appointmentid, 1 FROM tblappointment WHERE appointmentid IN (".join(',', $visitids).")", 1);
	$originals = json_decode($details['origvisits'], 'assoc');
	uasort($originals, 'cmpdatetime');
	$received = strtotime($request['received']);
	require_once "appointment-fns.php";
	
	ob_start();
	ob_implicit_flush(0);
	global $requestTypes;

	if(!$visitid && $request['note']) 
		echo "<b>You wrote</b><div style='width:95%;border:solid gray 1px;padding:3px;'>{$request['note']}</div>";
	echo "<table style='width:100%;background:white;'>";
	// Details = (maybe) checkbox and original details icon
	$cols = explode(',', 'Time,Service,Pets');
	$showCharges = false;
	$showSitters = false;
	if($showSitters) $cols[] = 'Sitter';
	if($showCharges) $cols[] = 'Charge';
	$columnCount = count($cols);
	echo "<tr>";
	foreach($cols as $col) echo "<th>$col</th>";
	echo "</tr>";
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype", 1);
	foreach($originals as $orig) {
		list($start, $end) = explode(',',$orig['timeofday']);
		$orig['starttime'] = date('H:i:s', strtotime($start));
		$orig['endtime'] = date('H:i:s', strtotime($end));
		$futurity = appointmentFuturity($orig, $received);
		$apptid = $orig['appointmentid'];
		if($visitid && $visitid != $apptid) continue;
		if($date != $orig['date']) {
			$date = $orig['date'];
			echo "<tr><th style='text-align:center;background:lightblue;' colspan=$columnCount>".shortDateAndDay(strtotime($date))."</th></tr>";
			$oddrow = false;
		}
		$oddrow = !$oddrow;
		/*$rowclass = $orig['completed'] 
							? 'completedtask' 
							: ($orig['canceled'] ? 'canceledtask'
							: ($futurity == -1 ? 'noncompletedtask' : 'futuretask'));
		if(!$oddrow) $rowclass .= 'EVEN';*/
		echo "<tr>"; //  class='$rowclass'
		$service = $services[$orig['servicecode']];
		if($showSitters) {
			$sitter = $orig['providerptr'] 
			? fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$orig['providerptr']} LIMIT 1", 1)
			: 'Unassigned';
			$sitterCell	= "<td>$sitter</td>";
		}
		if($orig['adjustment']) $chargeTitle = "charge: ".dollarAmount($orig['charge'])."+ adjustment: ".dollarAmount($orig['adjustment']);
		else $chargeTitle = '';
		$chargeTitle = $chargeTitle ? "title='$chargeTitle'" : '';
		$chargeCell = $showCharges ? "<td $chargeTitle>".dollarAmount($orig['charge']+$orig['adjustment'])."</td>" : '';
		echo "<td>{$orig['timeofday']}</td><td>$service</td><td>{$orig['pets']}</td>$sitterCell$chargeCell";
		echo "</tr>";
		
		if($orig['note']) echo "<tr class='$rowclass'><td colspan=$columnCount>Note: {$orig['note']}</td></tr>";
		if($visitnotes[$apptid]) echo "<tr class='$rowclass'><td colspan=$columnCount><span style='color:red;font-weight:bold;'>Change note:</span> {$visitnotes[$apptid]}</td></tr>";
		if(!$exists[$apptid]) echo "<tr class='$rowclass'><td colspan=$columnCount>THIS VISIT [#$apptid] WAS LATER DELETED.</td></tr>";
	}
	echo "</table>";
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

