<? // appointment-client-notification-fns.php

function clientShouldBeNotified($clientid) {
	$props = "enhancedVisitReportArrivalTime,enhancedVisitReportCompletionTime"
			.",enhancedVisitReportVisitNote,enhancedVisitReportMoodButtons"
			.",enhancedVisitReportPetPhoto,enhancedVisitReportRouteMap";
	require_once "preference-fns.php";
	foreach(explode(',', $props) as $prop)
		if(getClientPreference($clientid, $prop))
			return true;
}

function notifyClient($source, $event, $note) {
	$templateLabel = $event == 'arrived' ?  '#STANDARD - Sitter Arrived' : '#STANDARD - Visit Completed';
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$templateLabel'");

	if($template) {
		$standardMessage = $template['body'];
		$standardMessageSubject = $template['subject'];
	}
	else  {
		$standardMessage = $event == 'arrived' 
		? "Hi #RECIPIENT#,\n\nThis note is to inform you that #SITTER# arrived to care for #PETS# at your home on #DATE# at #TIME#.\n\nSincerely,\n\n#BIZNAME#" 
		: "Hi #RECIPIENT#,\n\nThis note is to inform you that #SITTER# finished a visit to care for #PETS# at your home on #DATE# at #TIME#.\n\nSincerely,\n\n#BIZNAME#";
		$standardMessageSubject = $event == 'arrived' 
		? "Sitter arrival"
		: "Visit completed";
	}
	
	if(dbTEST('tonkatest')) $standardMessage .= "\n\n<span style='font-size:0.6em;'>[{$source['appointmentid']}]</span>"; // azcreaturecomforts,tlcpetsitter
	
	if($event == 'arrived') {
		$trackTime = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = '{$source['appointmentid']}' AND event = 'arrived' LIMIT 1");
		if(!$trackTime) ;// ???
		else $eventTime = strtotime($trackTime);
	}
	else if($event == 'completed') {
		$trackTime = $source['completed'];
		if(!$trackTime) $trackTime = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = '{$source['appointmentid']}' AND event = 'completed' LIMIT 1");
		if(!$trackTime) ;// ???
		else $eventTime = strtotime($trackTime);
	}
	$eventTime = $eventTime ? $eventTime : time();
	$date = month3Date($eventTime);
	$time = date('g:i a', $eventTime);
	$client = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient WHERE clientid = {$source['clientptr']}");
	$pets = $source['pets'];
	if($pets == 'All Pets') {
		require_once "pet-fns.php";
		$pets = getClientPetNames($source['clientptr'], $inactiveAlso=false, $englishList=true);
	}
	else if(count($names = explode(', ', $pets)) > 1) {
		$lastName = array_pop($names);
		$pets = join(', ', $names)." and $lastName";
	}


	if(($start = strpos($standardMessage, '#IF_VISITNOTE#')) !== FALSE
			&& ($end = strpos($standardMessage, '#END_VISITNOTE#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($standardMessage, 0, $start);
		$bracketTextStart = $start+strlen('#IF_VISITNOTE#');
		$bracketText = $note ? substr($standardMessage, $bracketTextStart, $end-$bracketTextStart) : '';;
		$messagePart2 = substr($standardMessage, $end+strlen('#END_VISITNOTE#'));
		$standardMessage = "$messagePart1$bracketText$messagePart2";
	}
	if(($start = strpos($standardMessage, '#IF_NOVISITNOTE#')) !== FALSE
			&& ($end = strpos($standardMessage, '#END_NOVISITNOTE#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($standardMessage, 0, $start);
		$bracketTextStart = $start+strlen('#IF_NOVISITNOTE#');
		$bracketText = $note ? '' : substr($standardMessage, $bracketTextStart, $end-$bracketTextStart);

		$messagePart2 = substr($standardMessage, $end+strlen('#END_NOVISITNOTE#'));
		$standardMessage = "$messagePart1$bracketText$messagePart2";
	}
	if(strpos($standardMessage, '#SITTER#') !== FALSE) {
		$sitterName = getDisplayableProviderName($source['providerptr'], $overrideAsClient=true); //$_SESSION["fullname"]
		if(is_array($sitterName)) 
			$sitterName = "your sitter";
	}
	require_once "provider-fns.php"; // to be sure -- required for getDisplayableProviderName()
	// assumes sitter is logged in user
	$subs = array('#RECIPIENT#'=>$client['clientname'],
								'#FIRSTNAME#'=>$client['fname'],
								'#LASTNAME#'=>$client['lname'],
								'#SITTERFIRSTNAME#'=>$_SESSION["auth_userfname"], 
								'#SITTER#'=>$sitterName, 
								'#PETS#'=>$pets, 
								'#DATE#'=>$date, 
								'#TIME#'=>$time, 
								'#BIZNAME#'=>$_SESSION["bizname"],
								'#BIZEMAIL#'=>$_SESSION["preferences"]["bizEmail"],
								'#BIZPHONE#'=>$_SESSION["preferences"]["bizPhone"],
								'#BIZHOMEPAGE#'=>$_SESSION["preferences"]["bizHomePage"],
								'#BIZLOGINPAGE#'=>"http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION["bizptr"]}",
								'#LOGO#'=>logoIMG(),
								'#VISITNOTE#'=>$note);
	foreach($subs as $token => $sub)
		$standardMessage = str_replace($token, $sub, $standardMessage);
	require_once "comm-fns.php";
	$html = strcmp($standardMessage, strip_tags($standardMessage)) != 0;
	enqueueEmailNotification($client, $standardMessageSubject, $standardMessage, null, $mgrname=null, $html);
}
	

/*
Enhanced Visit Report Request

if(sent) 
	show identity of approving manager
	do not offer approve/send button
	display sent message
	
else offer editing of boxes of arrived/completed emails (NOT "display on client home page"), 
			mood buttons, sitter notes, map, pet photo
		offer and editing of sitter note
*/

function displayVisitReportRequest($request, $updateList) {
	global $OFFER_SET_PET_PHOTO;
	$extraFields = getExtraFields($request);
	
	require_once "client-flag-fns.php";
	require_once "appointment-fns.php";
	require_once "comm-fns.php";

	$clientptr = $extraFields['x-clientptr'];
	$clientDetails = getOneClientsDetails($clientptr, array('email'));
	$appointment = getAppointment($extraFields['x-appointmentptr'], $withNames=true, $withPayableData=false, $withBillableData=false);
	if(TRUE || $extraFields['x-messageptr']) {
		$xmessageptr = $extraFields['x-messageptr'];
		// if x-messageptr is negative, the message was sent by email queue
		if(!$xmessageptr) {
			// find the id of a message sent to this client where tags == "vrq{$appointment['appointmentid']}"
			$xmessageptr = fetchRow0Col0(
				"SELECT msgid FROM tblmessage 
					WHERE correspid = {$request['clientptr']} AND correstable = 'tblclient'
						AND (tags = 'vrq{$request['requestid']}' OR tags = 'vr{$request['requestid']}')
						ORDER BY datetime DESC
						LIMIT 1");
			// update the extrafields with this id
			
		}
		$allEmails = fetchKeyValuePairs("SELECT msgid, `datetime` FROM tblmessage 
				WHERE correspid = {$request['clientptr']} AND correstable = 'tblclient'
					AND (tags = 'vrq{$extraFields['x-appointmentptr']}' OR tags = 'vr{$extraFields['x-appointmentptr']}')
					ORDER BY datetime DESC", 1);
		if($xmessageptr) {
			$sentMessage = getMessage($xmessageptr);
			if(!$sentMessage) $sentMessage = getArchivedMessage($xmessageptr);
		}
	}
	
	// allow for delayed send visit reports
	if($appointment) // appt may have been deleted since report filed...
	 $statusVars = fetchKeyValuePairs(
		"SELECT property, value 
			FROM tblappointmentprop WHERE appointmentptr = {$appointment['appointmentid']} 
				AND property IN ('emailAfter', 'lastReport', 'queuedMsgForRequest')", 1);
	if(!$sentMessage && $statusVars['lastReport']) {
		$sentMessage = getMessage($statusVars['lastReport']);
		if(!$sentMessage) $sentMessage = getArchivedMessage($statusVars['lastReport']);
	}

	
	
	if($_SESSION["flags_enabled"]) { 
		$flags = clientFlagPanel($clientptr, $officeOnly=false, $noEdit=true, $contentsOnly=false, $onClick=null, $includeBillingFlags=false);
	}

	echo "<h2 style='font-size:1.5em'>{$clientDetails['clientname']} $flags</h2>";
	if($appointment) {
		$service = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$appointment['servicecode']} LIMIT 1");
		echo "Submitted by: {$extraFields['x-providername']} at ".longestDayAndDateAndTime(strtotime($request['received']));
		$visitidDisplay = mattOnlyTEST() ? " <i>({$extraFields['x-appointmentptr']})</i>" : '';
		echo "<p>Visit$visitidDisplay: ".shortDate(strtotime($appointment['date']))." {$appointment['timeofday']} $service Pets: {$appointment['pets']}";

	}
	else echo "Note: This visit referred to has been deleted.";
	echo "<p>";

	if($appointment) $statDesc = checkAndSetLastReport($appointment['appointmentid']); // lastReport, queued, slated
	
	if(!is_array($statDesc)) $statDesc = null;
	if($statDesc['slated']) $statDesc = "Slated to be sent after ".date('g:i a', strtotime($statDesc['slated'])).'.';
	else if($statDesc['queued']) $statDesc = "Waiting to be sent at ".date('g:i a', strtotime($statDesc['queued'])).'.';
	
	if($sentMessage) {
		$prettySent = longestDayAndDateAndTime(strtotime($sentMessage['datetime']));
		echo "Sent to client $prettySent by: {$extraFields['x-sentby']}<br>";
		if(mattOnlyTEST()) {
			// lastReport, queued, slated
			if($statDesc) echo "  $statDesc";
			if($statusVars) {
				foreach($statusVars as $k => $v) $statusVars[$k] = "[$k] $v";
				echo " <i>[".date('H:i:s')."] ".join(' ', $statusVars)."</i><br>";
			}
		}
		if(!$_REQUEST['edit']) 	{
			echo "\n";
			echoButton('editButton', 'Edit &amp; Send Again', 
				$onClick='editRequest()', $class='', $downClass='', $noEcho=false, $title=null);
			$revision = visibleNoteAndChangeNotice($appointment['appointmentid'], $extraFields['x-note']); // array('revisednote'=>..., 'changenotice'=>...)
			if($revision)
				echo "\n {$revision['changenotice']}\n<div style='display:none;' id='visitreportnote'>{$extraFields['x-note']}</div>\n";

			
			//echo "ALL: ".print_r($allEmails,1);
		}
	}
	else {
		if($extraFields['x-messageptr']) echo "Message sent, but message cannot not be found!<br>";
		else {
			echo $appointment ? "Not yet sent to client." : "";
			echo "<br>";
		}
	}
	$reportFields = visitReportData($extraFields);

	if($reportFields['VISITPHOTOURL'] && $OFFER_SET_PET_PHOTO) {
		echo "<p>";
		fauxLink("Use visit photo as a pet's profile photo...", 'setPetPhoto()', $noEcho=false, $title='Choose a pet and use this photo in its profile');
		echo "<br>";
	}
	echo "\n<table width=100%>\n";
	echo "<tr><td style='border: solid black 1px;background-color:white;'>";
	if($sentMessage && !$_REQUEST['edit']) {// if sent, show the message as it was sent
		echo $sentMessage['body'];
	}
	else {// display a form to be edited
		dumpVisitReportEditorFormElementRows($extraFields, $appointment['appointmentid']);
	}
	echo "</td></tr></table>";
	echo "<table border=0>\n"; // manager's editor
	echo "<tr><td colspan=3>Office Notes:</td></tr>";
	echo "<tr><td colspan=3><textarea id='officenotes' name='officenotes' rows=4 cols=80 class='sortableListCell'>"
					.$request['officenotes']."</textarea></td></tr>";
	echo "</table>"; // manager's editor
}

$OFFER_SET_PET_PHOTO = $_SESSION['preferences']['enableVisitPhotoToPetPhoto'];

function dumpVisitReportEditorFormElementRows($extraFields, $appointmentid=null) {
	global $OFFER_SET_PET_PHOTO;
	//if(!$appointmentid)  return;
	
	$report = visitReportData($extraFields);
}
	$includeCheck = inclusionPreferences($extraFields['x-clientptr'], $includeFields=null);			
	echo "<table border=0><tr><td colspan=2>Include the following:</td><tr>\n"; // manager's editor
	$labels = explodePairsLine('ARRIVED|Arrived||COMPLETED|Completed||MOODBUTTON|Mood||MAPROUTEURL|Visit Map||NOTE|Note||VISITPHOTOURL|Photo');
	$allButtonImages = moodButtonImages(); // mood=>(('title'=>'', 'file'=>'basename'))
	foreach(explode(',', 'ARRIVED,COMPLETED,MOODBUTTON,MAPROUTEURL') as $key) {
		if($key == 'MOODBUTTON') {
			
			foreach((array)$report['MOODBUTTON'] as $mood=>$rawval) {
				if($rawval == 'yes' || $rawval == 1)
					$showButtons[] = "<img height=30 width=30 src='art/{$allButtonImages[$mood]['file']}' title='{$allButtonImages[$mood]['title']}'>";
			}
			$extraContent = $showButtons ? join(' ', $showButtons) : 'No mood buttons checked.';
		}
		else if($key == 'MAPROUTEURL') {
			$extraContent = "<a onclick=\"window.opener.location.href='{$report[$key]}'\">View Live Map in Main Window</a>";
			if(staffOnlyTEST() && $appointmentid && ($mapshot = visitMapURL($appointmentid, $internalUse=true))) {
				$extraContent .= " <a onclick='$.fn.colorbox({href: \"$mapshot\", iframe: true, width:500, height: 500, scrolling: true, opacity: \"0.3\"});'><img src='art/map-icon.jpg'> View Map Snapshot</a>";
			}
		}
		else {
			$dateVal = $report[$key];
			if(!$dateVal && $key == 'COMPLETED') {
				$dateVal = fetchRow0Col0("SELECT completed FROM tblappointment WHERE appointmentid = {$extraFields['x-appointmentptr']} LIMIT 1", 1);
			}
			$extraContent = $dateVal ? longestDayAndDateAndTime(strtotime($dateVal)) : '- not found -';
		}
		checkboxRow($labels[$key], $key, $value=$includeCheck[$key], $labelClass=null, $inputClass=null, 
									$rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, " $extraContent");
	}
	$appointmentid = $appointmentid ? $appointmentid : $extraFields['x-appointmentptr'];
	echo "\n<tr><td colspan=2>";
	hiddenElement('savenotechanges', '0');
	$clientVisibleNote = extractSitterNote($report['NOTE']);
	$revision = visibleNoteAndChangeNotice($appointmentid, $clientVisibleNote); // array('revisednote'=>..., 'changenotice'=>...)
	if($revision) {
		echo "<tr><td style='display:none;' id='visitreportnote'>$clientVisibleNote</td></tr>";
		$clientVisibleNote = $revision['revisednote'];
		$changedNoteNotice = $revision['changenotice'];

	}
	hiddenElement('originalNote', $clientVisibleNote);
			
	labeledCheckbox("Include Note:", 'INCLUDENOTE', $value=$includeCheck['NOTE'], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
	if($changedNoteNotice) echo "<img src='art/spacer.gif' width=20 height=1>".$changedNoteNotice; // may be null
	echo "<br><textarea name='NOTE' id='NOTE' rows=10 cols=75 class='fontSize1_2em'>$clientVisibleNote</textarea>";
	echo "</td></tr>\n";
	if($report['VISITPHOTOURL']) {
		$filecacheid = getAppointmentProperty($appointmentid, 'visitphotocacheid');
		$localPath = getCachedFileAndUpdateExpiration($filecacheid);
		$maxDims = array(540, 540);
		$fileSize = round(filesize($localPath) / 1024 / 1024);
		$fileSize = $fileSize == 0 ? "< 1MB" : $fileSize;
		$dims = getimagesize($localPath);
		if($dims[0] > $maxDims[0] || $dims[1] > $maxDims[1]) {
			$height = $dims[1];
			$width = $dims[0];
			
// KLUDGE -- FIX FOR EXIF PHOTO ROTATION HANDLING -- FIREFOX ONLY
$agent = $_SERVER["HTTP_USER_AGENT"];
if(strpos($agent, 'irefox') !== FALSE) {
    $exif = exif_read_data($localPath);
    if($orientation = $exif['Orientation']) {
        if($orientation == 6 | $orientation == 8) {
					$transfer = $height;
					$height = $width;
					$width = $transfer;
					$photoStyle = "style='image-orientation: from-image;'"; // works in FF, not Chrome
				}
		}
}
			$maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
			$percent = $maxDim / max($width, $height);
			$newwidth = round($width * $percent);
			$newheight = round($height * $percent);
			$height = number_format($height);
			$width = number_format($width);
			$scaling = "height=$newheight width=$newwidth";
		}
		
		$photo = "<img id='visitphoto' src='$localPath' $scaling $photoStyle>";
		echo "\n<tr><td colspan=2>";
		labeledCheckbox("Include Photo: (actual size $width pixels wide X $height pixels high.  File size: $fileSize MB)", 'INCLUDEPHOTO', $value=$includeCheck['VISITPHOTOURL'], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
		$TEST_ROTATE = $_SESSION['preferences']['enablevisitphotorotation'] || staffOnlyTEST();
		if($TEST_ROTATE || $OFFER_SET_PET_PHOTO) {
			echo "<p>";
			if($OFFER_SET_PET_PHOTO) {
				fauxLink("Use this photo as a pet's profile photo...", 'setPetPhoto()', $noEcho=false, $title='Choose a pet and use this photo in its profile');
			}
			if($TEST_ROTATE) {
				echo " ";
				$clockwise = "<span class='bold fontSize1_2em'>&#8635;</span>";
				fauxLink("{$clockwise}Rotate this photo...", 'rotatePhoto()', $noEcho=false, $title='Reorient this photo');
			}
		}
		echo "</td></tr>\n";
	}
	else $photo = '- No photo supplied. -';
	echo "\n<tr><td colspan=2>$photo</td></tr>\n";
	echo "<tr><td colspan=3>";
	echoButton('sendbutton', 'Send Now', $onClick='sendVisitReport()', $class='', $downClass='', $noEcho=false, $title=null);
	if(dbTEST('sarahrichpetsitting') || staffOnlyTEST()) {
	echo "<img src='art/spacer.gif' width=20 height=1>";
	echoButton('sendbutton', 'Sitter Feedback', $onClick='sendVisitReportFeedback()', $class='', $downClass='', $noEcho=false, $title=null);
	}
	echo "</td></tr>";

	echo "</table>"; // manager's editor
}

function dumpPhotoRotationJS($id) {
	echo <<<ROTATIONJS
function rotatePhoto() {
	openConsoleWindow('photorotator', 'appointment-photo-rotator.php?id=$id',600,600);
}

function update(aspect, data) {
	if(aspect == 'photo') {
		let src = $('#visitphoto').attr('src');
		if(src.indexOf('?') > 0) src += '&x='; // get around caching
		else src += '?x=';
		src += (new Date()).getTime();
		$('#visitphoto').attr('src', src);
		if(data == 'rightangle') {
			let w = $('#visitphoto').attr('width');
			$('#visitphoto').attr('width', $('#visitphoto').attr('height'));
			$('#visitphoto').attr('height', w);
		}
	}
}

ROTATIONJS;
}

function visibleNoteAndChangeNotice($appointmentid, $clientVisibleNote) {
	$clientVisibleNote = extractSitterNote($clientVisibleNote);
	if($_SESSION['preferences']['TEST_VISIT_NOTE_MANAGER_EDITS']) {
		$visitNote = fetchRow0Col0("SELECT note FROM tblappointment WHERE appointmentid = $appointmentid LIMIT 1", 1);
		if($visitNote != $clientVisibleNote) {
			//echo "<tr><td style='display:none;' id='visitreportnote'>$clientVisibleNote</td></tr>";
			if(!$clientVisibleNote) $changedNoteNotice = '<i>The sitter submitted no note originally.</i>';
			else {
				$clientVisibleNote = str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r", "", safeValue($clientVisibleNote))));
				$sitterVersionLink = fauxLink('note', 
					//"$.fn.colorbox({html: \"<b>Sitter&apos;s original note</b><p>\"+$(\"#visitreportnote\").html(), iframe: false, width:350, height: 350, scrolling: true, opacity: \"0.3\"});",
					"$.fn.colorbox({html: \"<b>Sitter&apos;s original note</b><p>$clientVisibleNote\", iframe: false, width:350, height: 350, scrolling: true, opacity: \"0.3\"});",
					'noecho', 'View what the sitter wrote.');
				$changedNoteNotice = "<i>The note has been changed from the $sitterVersionLink the sitter entered.</i>";
			}
			return array('revisednote'=>$visitNote, 'changenotice'=>$changedNoteNotice);
		}
	}
	return null;
}

function dumpVisitReportClientDisplay($appointmentid) {
	global $OFFER_SET_PET_PHOTO;
	
	$report = visitReportDataForApptId($appointmentid);
	$includeCheck = inclusionPreferences($report['clientptr'], $includeFields=null);			
	echo "<table border=0><tr><td colspan=2></td><tr>\n"; // manager's editor
	$labels = explodePairsLine('ARRIVED|Arrived||COMPLETED|Completed||MOODBUTTON|Mood||MAPROUTEURL|Visit Map||NOTE|Note||VISITPHOTOURL|Photo');
	$allButtonImages = moodButtonImages(); // mood=>(('title'=>'', 'file'=>'basename'))
	foreach(explode(',', 'ARRIVED,COMPLETED,MOODBUTTON,MAPROUTEURL') as $key) {
		if($key == 'MOODBUTTON') {
	
			
			foreach($report['MOODBUTTON'] as $mood=>$rawval) {
				if($rawval == 'yes' || $rawval == 1)
					$showButtons[] = "<img height=30 width=30 src='art/{$allButtonImages[$mood]['file']}' title='{$allButtonImages[$mood]['title']}'>";
			}
			$extraContent = $showButtons ? join(' ', $showButtons) : 'No mood buttons checked.';
		}
		else if($key == 'MAPROUTEURL') $extraContent = "<a onclick=\"window.opener.location.href='{$report[$key]}'\">View Map in Main Window</a>";
		//else $extraContent = $report[$key] ? longestDayAndDateAndTime(strtotime($report[$key])) : '- not found -';
		else {
			$dateVal = $report[$key];
			if(!$dateVal && $key == 'COMPLETED') {
				$dateVal = fetchRow0Col0("SELECT completed FROM tblappointment WHERE appointmentid = $appointmentid LIMIT 1", 1);
			}
			$extraContent = $dateVal ? longestDayAndDateAndTime(strtotime($dateVal)) : '- not found -';
			if(!$dateVal && $key == 'COMPLETED' && mattOnlyTEST()) $extraContent = "--{$report[$key]}--";
		}
		//checkboxRow($labels[$key], $key, $value=$includeCheck[$key], $labelClass=null, $inputClass=null, 
		//							$rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, " $extraContent");
		labelRow($labels[$key], '$name', $value=$extraContent, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
	}
	$clientVisibleNote = extractSitterNote($report['NOTE']);
	if($clientVisibleNote) {
		//echo "\n<tr><td colspan=2>Note</td></tr>";
		echo "\n<tr><td colspan=2>$clientVisibleNote</td></tr>";
	}	
	if($report['VISITPHOTOURL']) {
		$filecacheid = getAppointmentProperty($appointmentid, 'visitphotocacheid');
		$localPath = getCachedFileAndUpdateExpiration($filecacheid);
		$maxDims = array(540, 540);
		$fileSize = round(filesize($localPath) / 1024 / 1024);
		$fileSize = $fileSize == 0 ? "< 1MB" : $fileSize;
		$dims = getimagesize($localPath);
		if($dims[0] > $maxDims[0] || $dims[1] > $maxDims[1]) {
			$height = $dims[1];
			$width = $dims[0];
			$maxDim = $height > $width ? $maxDims[1] : $maxDims[0];
			$percent = $maxDim / max($width, $height);
			$newwidth = round($width * $percent);
			$newheight = round($height * $percent);
			$height = number_format($height);
			$width = number_format($width);
			$scaling = "height=$newheight width=$newwidth";
		}
		$photo = "<img src='$localPath' $scaling>";
	}
	else $photo = '- No photo supplied. -';
	echo "\n<tr><td colspan=2>$photo</td></tr>\n";

	echo "</table>"; // client's view
}

function dumpVisitReportRequestJS($request) {
	$extraFields = getExtraFields($request);
	$id = $extraFields['x-appointmentptr'];
	$mattOnlyTest = $_SESSION['preferences']['TEST_VISIT_NOTE_MANAGER_EDITS'] ? 'true' : 'false';
	
	$args = array_merge($_REQUEST);
	if(!array_key_exists('edit', $_REQUEST)) $args['edit'] = 1;
	foreach($args as $k => $v) $urlArgs[] = "$k=$v";
	$urlArgs = join('&', $urlArgs);
	
	echo <<<JS
	
function setPetPhoto() {
		openConsoleWindow('photosetter', 'pet-photo-reassign.php?v=$id',750,450);
}

function editRequest() {
	document.location.href='request-edit.php?$urlArgs';
}
	
function sendVisitReport() {
	document.getElementById('operation').value = 'sendVisitReport';
	var fields = new Array('ARRIVED','COMPLETED','MOODBUTTON','MAPROUTEURL','INCLUDENOTE','INCLUDEPHOTO');
	var checked = 0;
	for(var i = 0; i < fields.length; i++)
		if(document.getElementById(fields[i]) && document.getElementById(fields[i]).checked) checked += 1;
	if(checked == 0) alert('Please select at least one element of the report.');
	else {
		if(document.getElementById('savenotechanges') 
			&& document.getElementById('NOTE').value 
				 !=	document.getElementById('originalNote').value
			&& $mattOnlyTest && confirm('If you want to save the modified visit note, click OK.'))
				document.getElementById('savenotechanges').value = 1;
		document.requesteditor.submit();
	}
}

function sendVisitReportFeedback() {  // to sitter
	document.getElementById('operation').value = 'sendVisitReportFeedback';
	var fields = new Array('ARRIVED','COMPLETED','MOODBUTTON','MAPROUTEURL','INCLUDENOTE','INCLUDEPHOTO');
	var checked = 0;
	for(var i = 0; i < fields.length; i++)
		if(document.getElementById(fields[i]) && document.getElementById(fields[i]).checked) checked += 1;
	if(checked == 0) alert('Please select at least one element of the report.');
	else  document.requesteditor.submit();
}

JS;
dumpPhotoRotationJS($id);
}

function createVisitReportRequest($appt, $buttonsJSON, $note, $messageptr, $sentby=null) {
	$providerName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$appt['providerptr']} LIMIT 1");
	return array(
		'requesttype'=>'VisitReport',
		'x-appointmentptr'=>$appt['appointmentid'],
		'x-clientptr'=>$appt['clientptr'],
		'x-providerptr'=>$appt['providerptr'],
		'x-providername'=>$providerName,
		'x-buttons'=>$buttonsJSON,
		'x-note'=>$note,
		'x-messageptr'=>$messageptr,
		'x-sentby'=>$sentby,
		);
}

function sendEnhancedVisitReportEmail($appointmentOrApptid, $immediately=true, $template=null, $includeFields=null, $requestptr=null) {
	// $includeFields will be a string like 'ARRIVED,MAPROUTEURL,COMPLETED' when manager approves a request
	// when $includeFields === null, client prefs are consulted. $includeFields should never be empty when a manager approves.
	
	// return $messagePtr on success or an array of errors
	require_once "appointment-fns.php";
	if(is_array($appointmentOrApptid)) {
		$appt = $appointmentOrApptid;
		$appointmentid = $appt['appointmentid'];
	}
	else {
		$appointmentid = $appointmentOrApptid;
		$appt = getAppointment($appointmentid, $withNames=true);
	}
	$errorData['appointmentid'] = $appointmentid;
	if(!$appt) {
		logError("sendEnhancedVisitReportEmail failed: appt [$appointmentid] not found.");
		return array("Visit [$appointmentid] not found.", $errorData);
	}
	$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = '{$appt['clientptr']}' LIMIT 1");	
	if(!$client) {
		logError("sendEnhancedVisitReportEmail failed: client [{$appt['clientptr']}] for appt [$appointmentid] not found.");
		$errorData['clientptr'] = $appt['clientptr'];
		return array("Client [{$appt['clientptr']}] not found.", $errorData);
	}
	
	$cc = null;
	// ======================================================
global $ALTEmailAddressTEST; // THIS IS REFERENCED IN native-visit-update.php
if($ALTEmailAddressTEST = TRUE) { //enabled for all 2020-10-04 $_SESSION['preferences']['enableVisitReportAltEmail']
	$recipientChoice = fetchRow0Col0("SELECT value FROM tblclientpref WHERE clientptr = {$client['clientid']} AND property = 'emailrecips_evr'", 1);
	// modes = 0:primary, 1:Alternate, -1:None, 2:Both
	if($recipientChoice == -1) {
		$client['email'] = null; // Send to  "Neither" email address
		logError("sendEnhancedVisitReportEmail failed: Neither email address for client [{$appt['clientptr']}] was a designated target.");
		$errorData['noDesignatedTargets'] = 1;
		return array("No email address is selected to receive Enhanced Visit Reports.", $errorData);
	}
	else if($recipientChoice == 1) { // Send ONLY to alt email
		if(!$client['email2']) {
			logError("sendEnhancedVisitReportEmail failed: No alt email address for client [{$appt['clientptr']}] found and primary email not selected.");
			$errorData['noAltEmailAddress'] = 1;
			return array("No Alt email address found for client.", $errorData);
		}
		$client['email'] = $client['email2'];
	}
	else if($recipientChoice == 2 && $client['email2']) { // Send to both primary email alt email
		// if no PRIMARY email 
		if(!$client['email']) $client['email'] = $client['email2'];
		else $cc = $client['email2'];
	}
}
	// ======================================================
	
	if(!$client['email']) {
		logError("sendEnhancedVisitReportEmail failed: No email address for client [{$appt['clientptr']}] found.");
		$errorData['noEmailAddress'] = 1;
		return array("No email address found for client.", $errorData);
	}
	
	// record which details may be viewed by the client later
	$allowedReportDetails = inclusionPreferences($appt['clientptr'], $includeFields);
	require_once "preference-fns.php";
	setAppointmentProperty($appointmentid, 'reportPublicDetails', join(',', array_keys($allowedReportDetails)));

	
	require_once "comm-fns.php";
	$message = enhancedVisitReport($appt, $internalUse=false, $template=null, $includeFields);
if(dbTEST('dogslife') && !$_SESSION) insertTable('tbltextbag', array('referringtable'=>'app-cli-not', 'body'=>print_r($message, 1)), 1);
	$messageBody = $message['body'];
	$subject = $message['subject'];

	if($immediately) {
		$senderName = $_SESSION["providerfullname"] ? $_SESSION["providerfullname"] : $_SESSION["auth_username"];
		
}
		if(!($error = notifyByEmail($client, $subject, $messageBody, $cc, $senderName, 'html'))) {// returns error on fail,null on success
		// $_SESSION["auth_username"] $_SESSION["fullname"]
			$messagePtr = mysqli_insert_id();
			updateTable('tblmessage', array('tags'=>"vr$appointmentid"), "msgid={$messagePtr}", 'showerrors');
		}
	}
	else {
		//function enqueueEmailNotification($person, $subject, $body, $cc=null, $mgrname, $html=false, $originator=null, $tags=null, $attachments=null) 

		$messagePtr = enqueueEmailNotification($client, $subject, $messageBody, $cc, null, 'html', $originator=null, $tags="vrq$appointmentid");  // returns array on fail
		if(is_array($messagePtr)) $error = $messagePtr[0];
		else if($messagePtr) $messagePtr = 0-$messagePtr;  // message has NOT been sent
	}

	if($error) {
		logError("sendEnhancedVisitReportEmail($appointmentid)): ".mysqli_error().", ".print_r($messagePtr,1));
		return array("Attempt to email visit report for visit #$appointmentid failed at ".date('H:i:s'), $errorData);
	}
	else if($immediately) {
		// attach the message to the appointment
		replaceTable('tblappointmentprop', 
									array('appointmentptr'=>$appointmentid, 'property'=>'lastReport', 'value'=>$messagePtr), 1);
	}
	return $messagePtr;
}

function enhancedVisitReportEmail($appointmentOrApptid, $immediately=true, $template=null, $includeFields=null, $requestptr=null) {
	// $includeFields will be a string like 'ARRIVED,MAPROUTEURL,COMPLETED' when manager approves a request
	// when $includeFields === null, client prefs are consulted. $includeFields should never be empty when a manager approves.
	
	// return $messagePtr on success or an array of errors
	require_once "appointment-fns.php";
	if(is_array($appointmentOrApptid)) {
		$appt = $appointmentOrApptid;
		$appointmentid = $appt['appointmentid'];
	}
	else {
		$appointmentid = $appointmentOrApptid;
		$appt = getAppointment($appointmentid, $withNames=true);
	}
	$errorData['appointmentid'] = $appointmentid;
	if(!$appt) {
		logError("enhancedVisitReportEmailBody failed: appt [$appointmentid] not found.");
		return array("Visit [$appointmentid] not found.", $errorData);
	}
	$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = '{$appt['clientptr']}' LIMIT 1");	
	if(!$client) {
		logError("enhancedVisitReportEmailBody failed: client [{$appt['clientptr']}] for appt [$appointmentid] not found.");
		$errorData['clientptr'] = $appt['clientptr'];
		return array("Client [{$appt['clientptr']}] not found.", $errorData);
	}
	
	$cc = null;
	// ======================================================

	// record which details may be viewed by the client later
	$allowedReportDetails = inclusionPreferences($appt['clientptr'], $includeFields);
	require_once "preference-fns.php";
	
	require_once "comm-fns.php";
	$message = enhancedVisitReport($appt, $internalUse=false, $template=null, $includeFields);
	return $message;

}

function visitReportErrorNotification($error) {
	$message = $error[0];
	$errorData = $error[1];
	$appt = fetchFirstAssoc(
		"SELECT appointmentid, clientid,
				CONCAT_WS(' ', c.fname, c.lname) as client,
				c.email as email,
				c.email2 as email2,
				CONCAT_WS(' ', p.fname, p.lname) as sitter,
				label,
				date, timeofday, pets
			FROM tblappointment
			LEFT JOIN tblclient c ON clientid = clientptr
			LEFT JOIN tblprovider p ON providerid = providerptr
			LEFT JOIN tblservicetype ON servicetypeid = servicecode
			WHERE appointmentid = {$errorData['appointmentid']}
			LIMIT 1", 1);
	$notification['subject'] = "Visit Report email to {$appt['client']} failed";
	$email = $appt['email'] ? $appt['email'] : "<i>-- none supplied --</i>";
	$email2 = $appt['email2'] ? $appt['email2'] : "<i>-- none supplied --</i>";
	$clientLink = fauxLink($appt['client'], "if(window.opener) window.opener.location.href=\"client-edit.php?id={$appt['clientid']}\"", 'noecho', 'Edit this client');
	$notification['message'] = 
		"{$notification['subject']}:<p>$message<p>Visit"
		."<br>Client: $clientLink"
		."<br>Primary Email: $email"
		."<br>Alt Email: $email2"
		."<br>Sitter: {$appt['sitter']}"
		."<br>Date: ".shortDate(strtotime($appt['date']))
		."<br>Time of Day: {$appt['timeofday']}"
		."<br>Pets: {$appt['pets']}"
		."<p><a href='visit-report.php?id={$appt['appointmentid']}'>Review the Visit Report</a>";
	return $notification;
}

function orderDelayedVisitReportEmail($appointmentid, $delaySeconds=60, $requestPtr=null) {
	// tell LeashTime to generate a VR email and add it to the emil queue some time
	// AFTER delaySeconds have elapsed.
	
	$dateTime = date('Y-m-d H:i:s', strtotime("+ $delaySeconds seconds"));
	replaceTable('tblappointmentprop', 
								array('appointmentptr'=>$appointmentid, 'property'=>'emailAfter', 'value'=>"$requestPtr|$dateTime"), 1);
}
	
function emailWaitingVisitReports() {
	
	// HAMSTRUNG!
	return;
	
	
	
	// CALLED just before the email queue is serviced
	// Find all appointments for which VR's should be generated (those for which there is an 'emailAfter' property)
	// With each one found that has a scheduled time that is on or after the present moment
	// generate and queue up a VR email message and remove the 'emailAfter' property
	chdir(dirname(__FILE__)); // for logo
	// STEP 1: For reports that were previously queued, check to see if they were sent
	$queuedMessages = fetchKeyValuePairs("SELECT appointmentptr, value FROM tblappointmentprop WHERE property = 'queuedMsgForRequest'");
	foreach($queuedMessages as $appointmentid => $msgPtrReqPtrDate) {
		checkAndSetLastReport($appointmentid, $msgPtrReqPtrDate);
	}
	// STEP 2: Queue up "ripe" visit reports
	$appointmentTimes = fetchKeyValuePairs("SELECT appointmentptr, value FROM tblappointmentprop WHERE property = 'emailAfter'");
	$now = date('Y-m-d H:i:s');
	foreach($appointmentTimes as $appointmentid => $requestPtrAndEmailAfter) {
		list($requestptr, $emailAfter) = explode('|', $requestPtrAndEmailAfter);
		if(strcmp($emailAfter, $now) <= 0) {
			//sendEnhancedVisitReportEmail($appointmentOrApptid, $immediately=true, $template=null, $includeFields=null, $requestptr=null)
			$queuedMessagePtr = sendEnhancedVisitReportEmail($appointmentid, $immediately=false, $template=null, $includeFields=null, $requestptr);
			// we will ignore possible queueing errors here and delete emailAfter...
			deleteTable('tblappointmentprop', "appointmentptr = $appointmentid AND property = 'emailAfter'", 1);
			// ... but if there is no error, we will add a reminder to set "lastReport" after the message has disappeared from the queue
			if(!is_array($queuedMessagePtr))
				replaceTable('tblappointmentprop', 
											array('appointmentptr'=>$appointmentid, 'property'=>'queuedMsgForRequest', 'value'=>"$queuedMessagePtr|$requestPtr|$now"), 1);
		}
	}
}

function checkAndSetLastReport($appointmentid, $queuedMessage=null) {
	// check to see if appointment has a queuedMsgForRequest that has been sent
	// and set lastReport for the appointment if it does
	// if a transmission is queued, return an array with lastReport and the time the message was queued
	// else if a transmission was queued and sent, set and return lastReport
	// else return lastReport
	if(!$appointmentid) return;
	$statusVars = fetchKeyValuePairs(
		"SELECT property, value 
			FROM tblappointmentprop WHERE appointmentptr = $appointmentid 
				AND property IN ('emailAfter', 'lastReport')", 1);
	$emailAfter = $statusVars['emailAfter'];
	$lastReport = $statusVars['lastReport'];
	$queuedMessage = $queuedMessage ? $queuedMessage
						: fetchRow0Col0(
								"SELECT value 
									FROM tblappointmentprop 
									WHERE appointmentptr = $appointmentid AND property = 'queuedMsgForRequest' LIMIT 1", 1);
	if($queuedMessage) {
		list($qmsgptr, $requestptr, $date) = explode('|', $queuedMessage['value']);
		$qmsgptr = abs($qmsgptr);
		if(!fetchRow0Col0("SELECT emailid FROM tblqueuedemail WHERE emailid = $qmsgptr LIMIT 1", 1)) {
			deleteTable('tblappointmentprop', "appointmentptr = $appointmentid AND property = 'queuedMsgForRequest'", 1);
			// the report was sent tagged with "vrq{appointmentid}".  Find the latest message about this appt
			$clientptr = fetchRow0Col0("SELECT clientptr FROM tblappointment WHERE appointmentid = $appointmentid LIMIT 1", 1);
			$anHourAgo = date('Y-m-d H:i:s', strtotime("- 1 HOUR"));
			$sentMessagePtr = fetchRow0Col0(
				"SELECT msgid 
					FROM tblmessage
					WHERE correspid = $clientptr 
						AND datetime > '$anHourAgo'
						AND tags = 'vrq$appointmentid'
					ORDER BY msgid DESC
					LIMIT 1", 1);
			replaceTable('tblappointmentprop', 
										array('appointmentptr'=>$appointmentid, 'property'=>'lastReport', 'value'=>$sentMessagePtr), 1);
			return $sentMessagePtr;
		}
		// else if not yet sent...
		else {
			$lastReport = array('lastReport'=>$lastReport, 'queued'=>$date);
		}
	}
	else {

		if($emailAfter) {
			$emailAfter = explode('|', $emailAfter); // e.g., 7397|2019-05-25 15:59:46
			$lastReport = array('lastReport'=>$lastReport, 'slated'=>$emailAfter[1]);
		}
	}
	return $lastReport;
}


function standardEnhancedVisitReportEmailTemplateSubject() {
	return '#SHORTPETS# Visit Report';
}

function standardEnhancedVisitReportNoteTemplateBody() {
	return "Dear #RECIPIENT#,<p>#IF_VISITNOTE#Your sitter wrote:<p>#VISITNOTE# #END_VISITNOTE#
#IF_NOVISITNOTE#It's always a pleasure to care for #PETS#.#END_NOVISITNOTE#";
}


function standardEnhancedVisitReportEmailTemplateBody() {
	if(petOwnerPortalVRTest()) return standardEnhancedVisitReportNoteTemplateBody();
	return "#LOGO#
			Dear #FIRSTNAME#,#STARTVISITPHOTOURL#<img src='#VISITPHOTOURL#' width=500 style='float:right;padding-left:7px;'>#ENDVISITPHOTOURL#
 
The visit for #PETS# has been completed by #SITTER#. 
 
#SERVICETYPE#
------------------------------------
#STARTARRIVED#Arrived: #ARRIVED##ENDARRIVED#
#STARTCOMPLETED#Completed: #COMPLETED##ENDCOMPLETED#
 
#NOTE# 

#STARTMOODBUTTON#<img src='#MOODBUTTON#' title='#MOODBUTTONLABEL#' alt='#MOODBUTTONLABEL#' width=40  style='padding-left:7px'>#ENDMOODBUTTON#

#STARTMAPROUTEURL#<a href='#MAPROUTEURL#'>Visit Map</a>#ENDMAPROUTEURL#";
}

function generateEnhancedVisitReportResponseURL($appt, $bizptr) {
	$nugget = visitReportDataPacketNugget($appt['appointmentid']);
	return globalURL("visit-report-ext.php?nugget=$nugget");
	
	// CODE BELOW IS OBSELETE
	$redirecturl = "visit-report-ext.php?bizid=$bizptr&id={$appt['appointmentid']}&token=";
	$systemlogin = 9999999;
	//print_r(array(	$bizptr, array('clientid'=>$appt['clientptr']), $redirecturl, $systemlogin, $expires=null, $appendToken=true));
	//echo "<hr>";
	return generateResponseURL($bizptr, array('clientid'=>$appt['clientptr']), $redirecturl, $systemlogin, $expires=null, $appendToken=true);
}

//$petOwnerPortalVRTest = dbTEST('dogslife'); //mattOnlyTEST();
function petOwnerPortalVRTest() { return dbTEST('dogslife'); }

function enhancedVisitReportEmailTemplate($includeValue=null, $visitPhotoURL=null, $mapRouteURL=null) {
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Enhanced Visit Report Email'");
	if(!$template)
		$template = array(
			'label'=>'#STANDARD - Enhanced Visit Report Email',
			'subject'=> standardEnhancedVisitReportEmailTemplateSubject(),
			'targettype'=>'other', 'personalize'=>0,
			'salutation'=>'', 'farewell'=>sqlVal("''"), 'active'=>1,
			'body'=>standardEnhancedVisitReportEmailTemplateBody(),
			'extratokens'=>'#SITTERFIRSTNAME#, #SITTERNICKNAME#.  (#SHORTPETS# in the Subject line)');
	//global $petOwnerPortalVRTest;
if(petOwnerPortalVRTest()) {
	//$stageDir = "../html/wordpressmktg/sandbox/communications/visit-report";
	$stageDir = "../html/wordpressmktg/sandbox-new/email/visit-reports";
	
	// for the benefit of delayed visit reports...
	if(!$_SESSION) $stageDir = "/var/www/html/wordpressmktg/sandbox-new/email/visit-reports";
	
	// We are going to use the enhancedVisitReportEmailTemplate as a template for the
	// sitter's note INSIDE the new enhanced visit report
	// this will be accomplished in preprocessENHANCEDVRMessage using the #ENHANCED_REPORT_SITTER_NOTE# token
	
	$specificTemplate = "evr-mail-full.html";
	if(TRUE) {
		$yesPhoto = $includeValue["VISITPHOTOURL"] && $visitPhotoURL;
		$yesMap = $includeValue["MAPROUTEURL"] && $mapRouteURL;
}
		if($yesPhoto && $yesMap) $specificTemplate = "evr-mail-full.html";
		else if($yesPhoto) $specificTemplate = "evr-mail-image.html";
		else if($yesMap) $specificTemplate = "evr-mail-map.html";
		else $specificTemplate = "evr-mail-default.html";
	}
	
	$templatePath = "$stageDir/$specificTemplate";
}
	$template['body'] = file_get_contents($templatePath)
											; //."<br>[$specificTemplate][$visitPhotoURL][".print_r($includeValue, 1)."]";
	 // enhanced-visit-report-template-2017-09-07.html enhanced-visit-report-template-2017-10-10.html
}
}
	return $template;
}

function enhancedVisitReport($appt, $internalUse=false, $template=null, $includeFields=null) {

	// return array('subject'=>..., 'body'=>...);
	if($_SESSION) $bizptr = $_SESSION['bizptr'];
	else {
			global $biz;
			$bizptr = $biz['bizid'];
	}
	$includeValue = inclusionPreferences($appt['clientptr'], $includeFields);
	$visitPhotoURL = visitPhotoURL($appt['appointmentid'], $internalUse, $bizptr);
	$mapRouteURL = visitMapURL($appt['appointmentid'], $internalUse, $bizptr);
	
			
	$template = $template ? $template : enhancedVisitReportEmailTemplate($includeValue, $visitPhotoURL, $mapRouteURL);	
	
	require_once "appointment-fns.php";
	//$appt = getAppointment($appt['appointmentid'], $withNames=true);
	$sitter = $appt['provider'];
	$subject = str_replace('#SHORTPETS#', petsSubstitution($appt, $short=true), $template['subject']);
	$message =  preprocessVRMessage($template['body'], $appt, $visitPhotoURL, $mapRouteURL, $client=null, $includeFields);
	return array('subject'=>$subject, 'body'=>$message);
}

function enhancedOnlineVisitReportHTML($appt, $internalUse=false, $template=null, $includeFields=null) {
	// return array('subject'=>..., 'body'=>...);
	if($_SESSION) $bizptr = $_SESSION['bizptr'];
	else {
			global $biz;
			$bizptr = $biz['bizid'];
	}
	$includeValue = inclusionPreferences($appt['clientptr'], $includeFields);
	$visitPhotoURL = visitPhotoURL($appt['appointmentid'], $internalUse, $bizptr);
	$mapRouteURL = visitMapURL($appt['appointmentid'], $internalUse, $bizptr);
	
			
	$template = $template ? $template : enhancedVisitReportOnlineTemplate($includeValue, $visitPhotoURL, $mapRouteURL);	
		
		
		
	
	require_once "appointment-fns.php";
	//$appt = getAppointment($appt['appointmentid'], $withNames=true);
	$message =  preprocessVRMessage($template['body'], $appt, $visitPhotoURL, $mapRouteURL, $client=null, $includeFields);
	return $message;
}

function enhancedVisitReportOnlineTemplate($includeValue=null, $visitPhotoURL=null, $mapRouteURL=null) {
	$stageDir = "../html/wordpressmktg/sandbox/communications/visit-report/online/html/leashtime";
	
	
	$specificTemplate = "index.html";
	
	$template['body'] = file_get_contents("$stageDir/$specificTemplate")
											; //."<br>[$specificTemplate][$visitPhotoURL][".print_r($includeValue, 1)."]";
	 // enhanced-visit-report-template-2017-09-07.html enhanced-visit-report-template-2017-10-10.html

	//return $template;
	
	// NEW!!!!!
	$yesPhoto = $includeValue["VISITPHOTOURL"] && $visitPhotoURL;
	$yesMap = $includeValue["MAPROUTEURL"] && $mapRouteURL;
	if($yesPhoto && $yesMap) $specificTemplate = "evr-online-full.html";
	else if($yesPhoto) $specificTemplate = "evr-online-img.html";
	else if($yesMap) $specificTemplate = "evr-online-map.html";
	else $specificTemplate = "evr-online-default.html";
	
if($_GET['debug']) {echo "yesPhoto[$yesPhoto] [$visitPhotoURL]<p>yesMap[$yesMap] [$mapRouteURL]<p>template [$specificTemplate]";exit;}
	$frameTemplate = "index.html";
	$template['body'] = file_get_contents("$stageDir/$frameTemplate");
	
	$template['body'] = str_replace("#VISIT_REPORT#", file_get_contents("$stageDir/$specificTemplate"), $template['body']);

	return $template;
}

function visitPhotoURL($appointmentid, $internalUse=false, $bizptr=null) {
	
	require_once "preference-fns.php";
}
	if(!getAppointmentProperty($appointmentid, 'visitphotocacheid')) return null;
	require_once "remote-file-storage-fns.php";
	if(!$internalUse) return getAppointmentPhotoPublicURL($appointmentid, $bizptr);
	else return globalURL("appointment-photo.php?id=$appointmentid");
}

function visitPhotoNuggetURL($appointmentid, $bizptr=null) {
	require_once "preference-fns.php";
	if(!getAppointmentProperty($appointmentid, 'visitphotocacheid')) return null;
	require_once "remote-file-storage-fns.php";
	$nugget = visitReportDataPacketNugget($appointmentid, $bizptr);
	return globalURL("appointment-photo.php?nugget=$nugget");
}

function visitMapURL($appointmentid, $internalUse=false, $bizptr=null) {
	require_once "preference-fns.php";
	if(!getAppointmentProperty($appointmentid, 'visitmapcacheid')) return null;
	require_once "remote-file-storage-fns.php";
	if(!$internalUse) $url = getAppointmentMapPublicURL($appointmentid, $bizptr);
	else $url = globalURL("appointment-map.php?id=$appointmentid");
	return $url;
}

function visitMapNuggetURL($appointmentid, $bizptr=null) {
	require_once "preference-fns.php";
	if(!getAppointmentProperty($appointmentid, 'visitmapcacheid')) return null;
	require_once "remote-file-storage-fns.php";
	$nugget = visitReportDataPacketNugget($appointmentid, $bizptr);
	return globalURL("appointment-map.php?nugget=$nugget");
}

function logoIMG($bizptr='') {
	$bizfiledirectory = $_SESSION["bizfiledirectory"] ? $_SESSION["bizfiledirectory"] : "bizfiles/biz_$bizptr/";
	$headerBizLogo = getHeaderBizLogo($bizfiledirectory);
	$host = $_SERVER["HTTP_HOST"] ? $_SERVER["HTTP_HOST"] : 'leashtime.com';
	return $headerBizLogo ? "<img src='https://$host/$headerBizLogo' $attributes>" :'';
}

function setVisitReportPublic($appointmentid, $visible) {
	// client may view the report in the client UI (if report is available)
	// this is set once the visit report is emailed or approved
	require_once "preference-fns.php";
	setAppointmentProperty($appointmentid, 'reportIsPublic', ($visible ? date('Y-m-d H:i:s') : null));
}

function isVisitReportClientViewable($appointmentid) {
	// report must be set as publicly viewable and there must be info
	if(isVisitReportPublic($appointmentid)) {
		return true;
		//$props = fetchKeyValuePairs("SELECT value FROM tblappointmentprop WHERE appointmentptr = $appointmentid");
		// find at least one appointment prop in the following list
		//$checkProps[] = 'visitphotocacheid'
	}
}	

function isVisitReportPublic($appointmentid) {
	require_once "preference-fns.php";
	return getAppointmentProperty($appointmentid, 'reportIsPublic');
}	

function sendVisitReport($form) { // from visit-report.php
	$appt = getAppointment($form['id'], $withNames=true, $withPayableData=false, $withBillableData=false);
	$appt['note'] = $form['NOTE'];
	foreach(explode(',', 'ARRIVED,COMPLETED,MOODBUTTON,MAPROUTEURL,INCLUDENOTE,INCLUDEPHOTO') as $key)
		if($form[$key]) $includeFields[] = $key;
	$includeFields = join(',', $includeFields);
	setVisitReportPublic($form['id'], true);
	return sendEnhancedVisitReportEmail($appt, 'immediately', $template=null, $includeFields);
}

function sendVisitReportFeedback($form) { // from visit-report.php.  Open a composer to the sitter
	if($form['requestid']) {
		$req = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$form['requestid']} LIMIT 1", 1);
		$extraFields = getExtraFields($req);
		$apptid = $extraFields['x-appointmentptr'];
	}
	else $apptid = $form['id'];
	$appt = getAppointment($apptid, $withNames=true, $withPayableData=false, $withBillableData=false);
	$appt['note'] = $form['NOTE'];
	foreach(explode(',', 'ARRIVED,COMPLETED,MOODBUTTON,MAPROUTEURL,INCLUDENOTE,INCLUDEPHOTO') as $key)
		if($form[$key]) $includeFields[] = $key;
	$includeFields = join(',', $includeFields);
	$messageAndSubject = enhancedVisitReportEmail($appt, $immediately=true, $template=null, $includeFields=null, $requestptr=null);
	//array('subject'=>$subject, 'body'=>$message);
	$messageBody = "\n<hr>".$messageAndSubject['body'];
	
	//extract(extractVars('replyto,all,client,provider,prospect,user,correspid,corresname,spousename,clientemail2,correstable,'
	//'correspaddr,subject,msgbody,mgrname,lname,fname,email,tags,template,forwardid', $_REQUEST));
	global $formAction;
	$formAction = 'action = "comm-composer.php"';
	$_POST = null; // to prevent comm-composer from trying to send
	$_REQUEST['subject'] = 'Fwd: '.$messageAndSubject['subject'];
	//global $provider;
	//$provider = $appt['providerptr'];
	$_REQUEST['provider'] = $appt['providerptr'];
	require_once "comm-composer.php";
}

function fieldDisplayCode($fields) {
	$allFields = explode(',', 'ARRIVED,COMPLETED,MOODBUTTON,MAPROUTEURL,INCLUDENOTE,INCLUDEPHOTO');
	$allFields = array_flip($allFields);
	foreach($fields as $field)
		$code += pow(2, $allFields[$field]);
	return $code;
}

function fieldDisplayFromCode($code) {
	$allFields = explode(',', 'ARRIVED,COMPLETED,MOODBUTTON,MAPROUTEURL,INCLUDENOTE,INCLUDEPHOTO');
	foreach($allFields as $i => $field)
		if($code & pow(2, $i)) $fields[] = $field;
	return $fields;
}


function approveVisitReport($form) { // from request-edit.php
	require_once "appointment-fns.php";
	$request = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$form['requestid']} LIMIT 1");
	if(!$request) {
		$error = "Request [{$form['requestid']}] not found.";
	}
	else {
		$extraFields = getExtraFields($request);
		$appointmentid = $extraFields['x-appointmentptr'];
		if($form['savenotechanges']) {
			if($_SESSION['preferences']['TEST_VISIT_NOTE_MANAGER_EDITS']) {
				$mods =array('note'=>$form['NOTE']);
				updateTable('tblappointment', 
											withModificationFields($mods), 
											"appointmentid=$appointmentid", 1);
				logChange($appointmentid, 'tblappointment', 'm', 'Visit report sender: manager changed note.');
			}
		}
		$appt = getAppointment($appointmentid, $withNames=true, $withPayableData=false, $withBillableData=false);
		$appt['note'] = $form['NOTE'];
		foreach(explode(',', 'ARRIVED,COMPLETED,MOODBUTTON,MAPROUTEURL,INCLUDENOTE,INCLUDEPHOTO') as $key)
			if($form[$key]) $includeFields[] = $key;
		$includeFields = join(',', $includeFields);
		
		setVisitReportPublic($appt['appointmentid'], true);

		$messageptr = sendEnhancedVisitReportEmail($appt, 'immediately', $template=null, $includeFields);
		if(is_array($messageptr)) {
			$error = $messageptr[0];
		}
		else {
			$extraFields['x-messageptr'] = $messageptr;
			$extraFields['x-sentby'] = "(manager) {$_SESSION['auth_username']}";
			foreach($extraFields as $key => $value) 
				$extraFieldElements .= "<extra key=\"$key\"><![CDATA[$value]]></extra>";
			$request['extrafields'] = "<extrafields>$extraFieldElements</extrafields>";
			$request['resolved'] = 1;
			$request['resolution'] = 'honored';
			$change = mysqli_real_escape_string(shortDateAndTime()." Honored by {$_SESSION['auth_username']} ({$_SESSION['auth_user_id']})");
			if($form['officenotes']) $change .= "\n{$form['officenotes']}";
			$request['officenotes'] = sqlVal("CONCAT_WS('\\n','$change', officenotes)");
			updateTable('tblclientrequest', $request,	"requestid = {$form['requestid']}", 1);
		}
	}
	return $error;
}

function visitReportData($extraFields) {
}	
	$arrivedCompleted = fetchKeyValuePairs(
			"SELECT event, date 
				FROM tblgeotrack 
				WHERE appointmentptr = '{$extraFields['x-appointmentptr']}'
				AND event IN ('arrived', 'completed')");
	$data['ARRIVED'] = $arrivedCompleted['arrived'];
	$data['COMPLETED'] = $arrivedCompleted['completed'];
	$data['MOODBUTTON'] = json_decode($extraFields['x-buttons'], $assoc=true);
	$data['MAPROUTEURL'] = globalURL("visit-map.php?id={$extraFields['x-appointmentptr']}");
	$data['VISITPHOTOURL'] = visitPhotoURL($extraFields['x-appointmentptr'], $internalUse=true);
	$data['NOTE'] = $extraFields['x-note'];
	return $data;
}

function visitReportDataForApptId($apptid) {
	$data = fetchFirstAssoc(
		"SELECT clientptr, c.lname as clientlname, c.fname as clientfname, 
			providerptr, p.lname as providerlname, p.fname as providerfname,
			label as service,
			pets, note as NOTE
		 FROM tblappointment
		 LEFT JOIN tblclient c ON clientid = clientptr
		 LEFT JOIN tblprovider p ON providerid = providerptr
		 LEFT JOIN tblservicetype ON servicetypeid = servicecode
		 WHERE appointmentid = $apptid LIMIT 1");
	//$data['clientptr'] = $apptDetails['clientptr'];
	//$data['NOTE'] =  $apptDetails['note'];
	$arrivedCompleted = fetchKeyValuePairs(
			"SELECT event, date 
				FROM tblgeotrack 
				WHERE appointmentptr = '$apptid'
				AND event IN ('arrived', 'completed')");
	$data['ARRIVED'] = $arrivedCompleted['arrived'];
	$data['COMPLETED'] = $arrivedCompleted['completed'];
	$data['MAPROUTEURL'] = globalURL("visit-map.php?id=$apptid");
	$data['VISITPHOTOURL'] = visitPhotoURL($apptid, $internalUse=true);
	$data['MOODBUTTON'] = array();
	$moods = fetchKeyValuePairs(
		"SELECT property, value 
			FROM tblappointmentprop 
			WHERE appointmentptr = $apptid
			AND property LIKE 'button_%'");
	foreach($moods as $k => $v)
		$data['MOODBUTTON'][substr($k, strlen('button_'))] = $v;
	return $data;
}

function preprocessVRMessage($message, $appt, $visitPhotoURL, $mapRouteURL, $client=null, $includeFields=null) {
	//global $petOwnerPortalVRTest;
	if(petOwnerPortalVRTest()) return preprocessENHANCEDVRMessage($message, $appt, $visitPhotoURL, $mapRouteURL, $client, $includeFields);
	
	if($_SESSION) $localSession = $_SESSION;
	else {
		//chdir(dirname(__FILE__)); // for logo
		global $biz;
		$localSession['preferences'] = fetchKeyValuePairs("SELECT * FROM tblpreference", 1);
		$localSession['auth_user_id'] = -999;
		$localSession['auth_username'] = '';
		$localSession['bizptr'] = $biz['bizid'];
	}
	if(!$client) $client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = '{$appt['clientptr']}' LIMIT 1");
	$appointmentid = $appt['appointmentid'];
	if(strpos($message, '#MANAGER#') !== FALSE) {
		$managerNickname = fetchRow0Col0(
			"SELECT value 
				FROM tbluserpref 
				WHERE userptr = {$localSession['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
		$message = str_replace('#MANAGER#', ($managerNickname ? $managerNickname : $localSession["auth_username"]), $message);
	}
	
	if(strpos($message, '#SITTERFIRSTNAME#') !== FALSE 
			|| strpos($message, '#SITTERNICKNAME#') !== FALSE)
		$prov = fetchFirstAssoc("SELECT fname, lname, nickname FROM tblprovider WHERE providerid = {$appt['providerptr']} LIMIT 1");
	
	$message = str_replace('#EMAIL#', $client['email'], $message);
	$message = str_replace('#BIZID#',  $localSession["bizptr"], $message);
	$message = str_replace('#BIZHOMEPAGE#', $localSession['preferences']['bizHomePage'], $message);
	$message = str_replace('#BIZEMAIL#', $localSession['preferences']['bizEmail'], $message);
	$message = str_replace('#BIZPHONE#', $localSession['preferences']['bizPhone'], $message);
	$message = str_replace('#BIZLOGINPAGE#', "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$localSession['bizptr']}", $message);

	//$message = str_replace('#NOTE#', $appt['note'], $message);
	$message = str_replace('#SITTER#', $appt['provider'], $message);
	$message = str_replace('#VISITDATE#', shortDate(strtotime($appt['date'])), $message);
	$message = str_replace('#SITTERFIRSTNAME#', $prov['fname'], $message);
	$message = str_replace('#SITTERNICKNAME#', $prov['nickname'], $message);
	$message = str_replace('#RECIPIENT#', $appt['client'], $message);
	$message = str_replace('#FIRSTNAME#', $client['fname'], $message);
	$message = str_replace('#LASTNAME#', $client['lname'], $message);
	$message = str_replace('#LOGO#', logoIMG($localSession["bizptr"]), $message);
	$serviceType = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = '{$appt['servicecode']}' LIMIT 1");
	$message = str_replace('#SERVICETYPE#', $serviceType, $message);
	
	
	$bizName = $localSession['preferences']['shortBizName'] 
		? $localSession['preferences']['shortBizName'] 
		: $localSession['preferences']['bizName'];
	$message = str_replace('#BIZNAME#', $bizName, $message);
	if($client['clientid'] && strpos($message, '#PETS#') !== FALSE) {
		$message = str_replace('#PETS#', petsSubstitution($appt), $message);
	}
	
	if($target['clientid']) {
		if(strpos($message, '#OPTOUT#') !== FALSE) 
			$message = replaceOptOutToken($message, $target);
	}
	
	$arrivedCompleted = fetchKeyValuePairs(
			"SELECT event, date 
				FROM tblgeotrack 
				WHERE appointmentptr = '$appointmentid'
				AND event IN ('arrived', 'completed')");
				
	if(!$arrivedCompleted['completed']) {
		// it may be completed but there may be no geotrack
		$arrivedCompleted['completed'] = fetchRow0Col0("SELECT completed FROM tblappointment WHERE appointmentid = $appointmentid LIMIT 1", 1);
	}
	
	$includeValue = inclusionPreferences($client['clientid'], $includeFields);
}
}
	$message = str_replace('#NOTE#', ($includeValue['NOTE'] ? extractSitterNote($appt['note']) : ''), $message);
	
	foreach(array('ARRIVED', 'COMPLETED') as $event) {
		$replacement = '';
		if($substInfo = bracketedContent($message, "$event")) {
			if($includeValue[$event] && $arrivedCompleted[strtolower($event)]) {
				$replacement = 
					str_replace("#$event#", 
											date('g:i a', strtotime($arrivedCompleted[strtolower($event)])), 
											$substInfo['template']);
			}
			$message = str_replace($substInfo['fullpattern'], $replacement, $message);
		}
	}
}
	
	if($substInfo = bracketedContent($message, "VISITPHOTOURL")) {
		if($includeValue["VISITPHOTOURL"] && $visitPhotoURL) {
			$replacement = str_replace("#VISITPHOTOURL#", $visitPhotoURL, $substInfo['template']);
		}
		$message = str_replace($substInfo['fullpattern'], $replacement, $message);
	}
		
	$replacement = '';
	if($substInfo = bracketedContent($message, "MAPROUTEURL")) {
		
		$mapRouteURL = $mapRouteURL ? $mapRouteURL : globalURL("visit-map.php?id=$appointmentid");
		if($includeValue["MAPROUTEURL"] && $mapRouteURL) {
			$replacement = str_replace("#MAPROUTEURL#", $mapRouteURL, $substInfo['template']);
		}
		$message = str_replace($substInfo['fullpattern'], $replacement, $message);
	}
		
if(mattOnlyTEST()) {$message .= "#STARTMAPROUTE##MAPROUTE##ENDMAPROUTE#";}

    if($substInfo = bracketedContent($message, "MAPROUTE")) {
        //$mapRouteURL = globalURL("visit-map.php?id=$appointmentid&noframe=1");
        if($includeValue["MAPROUTEURL"] && $mapRouteURL) {
						require_once "slug-image.php";
						require_once "visit-map-include.php";
						$mapRouteURL = makeMap($appointmentid, $mapOptions=array('static'=>1, 'returnurl'=>1));
						$slugUrl = makeSlugURL($mapRouteURL);
						$mapHTML = "<img src='$slugUrl'>";
            $replacement = str_replace("#MAPROUTE#", $mapHTML, $substInfo['template']);
        }
        $message = str_replace($substInfo['fullpattern'], $replacement, $message);
    }

	$buttonImages = moodButtonImages(); // mood=>(('title'=>'', 'file'=>'basename'))
		
	$replacements = '';
	if($substInfo = bracketedContent($message, "MOODBUTTON")) {
		$buttons = fetchCol0(
			"SELECT property 
				FROM tblappointmentprop 
				WHERE appointmentptr = '$appointmentid'
				AND property LIKE 'button_%' AND value = 1");
		if($includeValue["MOODBUTTON"] && $buttons) {
			foreach($buttons as $button) {
				$buttonImage = $buttonImages[substr($button, strlen('button_'))]['file'];
				$buttonTitle = $buttonImages[substr($button, strlen('button_'))]['title'];
				$replacements[] = str_replace("#MOODBUTTON#", globalURL("art/$buttonImage"), 
																				str_replace("#MOODBUTTONLABEL#", $buttonTitle, $substInfo['template']));
			}
			$replacement = join('', $replacements);
		}
		else $replacement = '';
		$message = str_replace($substInfo['fullpattern'], $replacement, $message);
	}
		
		
	//$message = str_replace('#SENDER#', $signature, $message);
	//echo $body.'<p>';
	$hasBreakTags = strpos($message, '<p') !== FALSE || strpos($message, '<br') !== FALSE;
	if(!$hasBreakTags) {
		$message = str_replace("\r", "", $message);
		$message = str_replace("\n\n", "<p>", $message);
		$message = str_replace("\n", "<br>", $message);
	}
	
	return $message;
}

function preprocessENHANCEDVRMessage($message, $appt, $visitPhotoURL, $mapRouteURL, $client=null, $includeFields=null) {
	
/*
href="#ENHANCED_REPORT_ONLINE#"
src="#ENHANCED_REPORT_BANNER#"
#ENHANCED_REPORT_PETS#
#ENHANCED_REPORT_VISIT_DATE#
#ENHANCED_REPORT_SERVICE#
#ENHANCED_REPORT_SITTER#
#ENHANCED_REPORT_SITTER_NOTE#
#STARTARRIVED#<span id="start-time">#ARRIVED#</span>#ENDARRIVED#
#STARTENHANCED_REPORT_TO#<small id="start-to-end" style="opacity:.6;"> to </small>#ENDENHANCED_REPORT_TO#
#STARTCOMPLETED#<span id="end-time">#COMPLETED#</span>#ENDCOMPLETED#
#STARTMOODBUTTON#<img src='#MOODBUTTON#' title='#MOODBUTTONLABEL#' alt='#MOODBUTTONLABEL#' style="float: left; width:28px;margin-right:16px;opacity: .9" >#ENDMOODBUTTON#
url(#ENHANCED_REPORT_VISIT_PHOTO_600_PX#)
#ENHANCED_REPORT_VISIT_PHOTO_HEIGHT#
#ENHANCED_REPORT_VISIT_NOTE#
data-saferedirecturl="#ENHANCED_REPORT_BIZ_URL#">Contact #ENHANCED_REPORT_BIZ_NAME#
*/	
	
	if(FALSE && $_SESSION) $localSession = $_SESSION;
	else {
		//chdir(dirname(__FILE__)); // for logo
		global $biz;
		$localSession['preferences'] = fetchKeyValuePairs("SELECT * FROM tblpreference", 1);
		$localSession['auth_user_id'] = -999;
		$localSession['auth_username'] = '';
		$localSession['bizptr'] = $biz['bizid'];
	}
	if(!$client) $client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = '{$appt['clientptr']}' LIMIT 1");
	$appointmentid = $appt['appointmentid'];
	if(strpos($message, '#MANAGER#') !== FALSE) {
		$managerNickname = fetchRow0Col0(
			"SELECT value 
				FROM tbluserpref 
				WHERE userptr = {$localSession['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
		$message = str_replace('#MANAGER#', ($managerNickname ? $managerNickname : $localSession["auth_username"]), $message);
	}
	
	if(strpos($message, '#SITTERFIRSTNAME#') !== FALSE 
			|| strpos($message, '#SITTERNICKNAME#') !== FALSE)
		$prov = fetchFirstAssoc("SELECT fname, lname, nickname FROM tblprovider WHERE providerid = {$appt['providerptr']} LIMIT 1");
	
	$message = str_replace('#EMAIL#', $client['email'], $message);
	$message = str_replace('#BIZID#',  $localSession["bizptr"], $message);
	$message = str_replace('#ENHANCED_REPORT_BIZ_URL#', $localSession['preferences']['bizHomePage'], $message);
	$message = str_replace('#BIZEMAIL#', $localSession['preferences']['bizEmail'], $message);
	$message = str_replace('#BIZPHONE#', $localSession['preferences']['bizPhone'], $message);
	$message = str_replace('#BIZLOGINPAGE#', "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$localSession['bizptr']}", $message);

	//$message = str_replace('#NOTE#', $appt['note'], $message);
	$message = str_replace('#SITTER#', $appt['provider'], $message);
	$message = str_replace('#SITTERFIRSTNAME#', $prov['fname'], $message);
	$message = str_replace('#SITTERNICKNAME#', $prov['nickname'], $message);
	$message = str_replace('#RECIPIENT#', $appt['client'], $message);
	$message = str_replace('#FIRSTNAME#', $client['fname'], $message);
	$message = str_replace('#LASTNAME#', $client['lname'], $message);
	$message = str_replace('#LOGO#', logoIMG($localSession["bizptr"]), $message);
	$serviceType = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = '{$appt['servicecode']}' LIMIT 1");
	$message = str_replace('#SERVICETYPE#', $serviceType, $message);
	$loginPageURL = globalURL("login-page.php?bizid={$localSession['bizptr']}");
	$message = str_replace('#LOGINPAGEURL#', $loginPageURL, $message);
	
	// NEW ======================================
	require_once "gui-fns.php";
	if(strpos($message, '#ENHANCED_REPORT_ONLINE#')) {
		require_once "appointment-fns.php";
		require_once "response-token-fns.php";
		$theResponse = generateEnhancedVisitReportResponseURL($appt, $_SESSION['bizptr']);
		$message = str_replace('#ENHANCED_REPORT_ONLINE#', $theResponse, $message);
	}
	if(strpos($message, '#ENHANCED_REPORT_BANNER#')) {
		$bannerURL = enhancedVisitReportBannerURL();
		$message = str_replace('#ENHANCED_REPORT_BANNER#', $bannerURL, $message);
	}
	
	$message = str_replace('#ENHANCED_REPORT_VISIT_DATE#', longDate(strtotime($appt['date'])), $message);
	
	$message = str_replace('#ENHANCED_REPORT_SERVICE#', $serviceType, $message);
	
	if(TRUE /* is sitter name permitted ? full name, firstname, nickname? */) {
		$sitterName = getDisplayableProviderName($appt['providerptr'], 'overrideasclient');
		$message = str_replace('#ENHANCED_REPORT_SITTER#', $sitterName, $message);
	}
	
	
	if(($start = strpos($message, '#IF_SITTERNAME#')) !== FALSE
			&& ($end = strpos($message, '#END_SITTERNAME#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($message, 0, $start);
		$bracketTextStart = $start+strlen('#IF_SITTERNAME#');
		$bracketText = $sitterName ? substr($message, $bracketTextStart, $end-$bracketTextStart) : '';;
		$messagePart2 = substr($message, $end+strlen('#END_SITTERNAME#'));
		if($sitterName) $message = "$messagePart1$bracketText$messagePart2";
		else $message = "$messagePart1$messagePart2";
		
	}
	if(($start = strpos($message, '#IF_NOSITTERNAME#')) !== FALSE
			&& ($end = strpos($message, '#END_NOSITTERNAME#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($message, 0, $start);
		$bracketTextStart = $start+strlen('#IF_NOSITTERNAME#');
		$bracketText = $sitterName ? '' : substr($message, $bracketTextStart, $end-$bracketTextStart);

		$messagePart2 = substr($message, $end+strlen('#END_NOSITTERNAME#'));
		if(!$sitterName) $message = "$messagePart1$bracketText$messagePart2";
		else $message = "$messagePart1$messagePart2";
	}
	
	$loggedIn = $_SESSION['auth_user_id'];
	if(($start = strpos($message, '#IF_LOGGEDIN#')) !== FALSE
			&& ($end = strpos($message, '#END_LOGGEDIN#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($message, 0, $start);
		$bracketTextStart = $start+strlen('#IF_LOGGEDIN#');
		$bracketText = substr($message, $bracketTextStart, $end-$bracketTextStart);
		$messagePart2 = substr($message, $end+strlen('#END_LOGGEDIN#'));
		if($loggedIn) $message = "$messagePart1$bracketText$messagePart2";
		else $message = "$messagePart1$messagePart2";
		
	}
	if(($start = strpos($message, '#IF_NOTLOGGEDIN#')) !== FALSE
			&& ($end = strpos($message, '#END_NOTLOGGEDIN#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($message, 0, $start);
		$bracketTextStart = $start+strlen('#IF_NOTLOGGEDIN#');
		$bracketText = substr($message, $bracketTextStart, $end-$bracketTextStart);
		$messagePart2 = substr($message, $end+strlen('#END_NOTLOGGEDIN#'));
		if(!$loggedIn) $message = "$messagePart1$bracketText$messagePart2";
		else $message = "$messagePart1$messagePart2";
	}

	if(($start = strpos($message, '#INCLUDEFILE#')) !== FALSE
			&& ($end = strpos($message, '#END_INCLUDEFILE#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($message, 0, $start);
		$bracketTextStart = $start+strlen('#INCLUDEFILE#');
		$includeFile = substr($message, $bracketTextStart, $end-$bracketTextStart);
		$bracketText = file_get_contents($includeFile);
		$messagePart2 = substr($message, $end+strlen('#END_INCLUDEFILE#'));
		$message = "$messagePart1$bracketText$messagePart2";
	}

	// End NEW ======================================
	
	$bizName = $localSession['preferences']['shortBizName'] 
		? $localSession['preferences']['shortBizName'] 
		: $localSession['preferences']['bizName'];
	$message = str_replace('#ENHANCED_REPORT_BIZ_NAME#', $bizName, $message);
	if($client['clientid'] && strpos($message, '#ENHANCED_REPORT_PETS#') !== FALSE) {
		$petNames = petsSubstitution($appt);
		$petNames = $petNames ? $petNames : "Your Pet";
		$message = str_replace('#ENHANCED_REPORT_PETS#', $petNames, $message);
	}
	
	if($target['clientid']) {
		if(strpos($message, '#OPTOUT#') !== FALSE) 
			$message = replaceOptOutToken($message, $target);
	}
	
	$arrivedCompleted = fetchKeyValuePairs(
			"SELECT event, date 
				FROM tblgeotrack 
				WHERE appointmentptr = '$appointmentid'
				AND event IN ('arrived', 'completed')");
				
	if(!$arrivedCompleted['completed']) {
		// it may be completed but there may be no geotrack
		$arrivedCompleted['completed'] = fetchRow0Col0("SELECT completed FROM tblappointment WHERE appointmentid = $appointmentid LIMIT 1", 1);
	}
	
	$substInfo = bracketedContent($message, "ENHANCED_REPORT_COMPLETION_STATUS");
	if($arrivedCompleted['completed'] && $substInfo)
		$message = str_replace($substInfo['fullpattern'], $substInfo['template'], $message);
	else $message = str_replace($substInfo['fullpattern'], '', $message);
	
	
	$includeValue = inclusionPreferences($client['clientid'], $includeFields);
	
}
}


	
	
	/*
	// We are going to use the enhancedVisitReportEmailTemplate preference as a template for the
	// sitter's note INSIDE the new enhanced visit report
	// this will be accomplished in preprocessENHANCEDVRMessage using the #ENHANCED_REPORT_VISIT_NOTE# token
	*/
	
	$theNoteToInclude = $includeValue['NOTE'] ? extractSitterNote($appt['note']) : '';
	$theNoteToInclude = framedVisitNoteForEnhancedVisitReport($theNoteToInclude, $appt);

	$message = str_replace('#ENHANCED_REPORT_VISIT_NOTE#', $theNoteToInclude, $message);
	
	
	
	
	
	$arrivedPlusCompletedCount = 0;
	foreach(array('ARRIVED', 'COMPLETED') as $event) {
		$replacement = '';
		if($substInfo = bracketedContent($message, "$event")) {
//echo "XXXX$event: ".print_r($substInfo, 1)."<hr>";			
			if($includeValue[$event] && $arrivedCompleted[strtolower($event)]) {
				$replacement = 
					str_replace("#$event#", 
											date('g:i a', strtotime($arrivedCompleted[strtolower($event)])), 
											$substInfo['template']);
				$arrivedPlusCompletedCount += 1;
			}
			else $replacement = '';
			$message = str_replace($substInfo['fullpattern'], $replacement, $message);
		}
	}
	
	$substInfo = bracketedContent($message, "ENHANCED_REPORT_TO");
	if($arrivedPlusCompletedCount > 1 && $substInfo)
		$message = str_replace($substInfo['fullpattern'], $substInfo['template'], $message);
	else $message = str_replace($substInfo['fullpattern'], '', $message);
	
	$replacement = '';
	if($substInfo = bracketedContent($message, "ENHANCED_REPORT_VISIT_PHOTO_600_PX")) {
		if($includeValue["VISITPHOTOURL"] && $visitPhotoURL) {
			$replacement = str_replace("#ENHANCED_REPORT_VISIT_PHOTO_600_PX#", $visitPhotoURL, $substInfo['template']);
		}
		$message = str_replace($substInfo['fullpattern'], $replacement, $message);
	}
		
	$replacement = '';
}

	if($substInfo = bracketedContent($message, "MAPROUTEURL")) {		
		if($includeValue["MAPROUTEURL"] && $mapRouteURL) {
			$replacement = str_replace("#MAPROUTEURL#", $mapRouteURL, $substInfo['template']);
		}
		$message = str_replace($substInfo['fullpattern'], $replacement, $message);
	}
		
    if($substInfo = bracketedContent($message, "MAPROUTE")) {
        //$mapRouteURL = globalURL("visit-map.php?id=$appointmentid&noframe=1");
        if($includeValue["MAPROUTEURL"] && $mapRouteURL) {
            ob_start();
            ob_implicit_flush(0);
            $clientView = true;
						$id = $appointmentid;
						$noframe = true;
						
						global $lockChecked;
						$lockChecked = true;
						require "visit-map-include.php";
						makeMap();  // SHIT! why does this bypass the output buffer and go straight to stdout?!
            //echo file_get_contents($mapRouteURL);
            $mapHTML = ob_get_contents();
            ob_end_clean();
            $replacement = str_replace("#MAPROUTE#", $mapHTML, $substInfo['template']);
        }
        $message = str_replace($substInfo['fullpattern'], $replacement, $message);
    }

//echo "GLOB: ".print_r(glob('art/mood*'),1);
//foreach(glob('art/mood*') as $f) echo "<br>".basename($f);	
	$buttonImages = moodButtonImages(); // mood=>(('title'=>'', 'file'=>'basename'))
		
	$replacements = '';
	if($substInfo = bracketedContent($message, "MOODBUTTON")) {
		$buttons = fetchCol0(
			"SELECT property 
				FROM tblappointmentprop 
				WHERE appointmentptr = '$appointmentid'
				AND property LIKE 'button_%' AND value = 1");
		if($includeValue["MOODBUTTON"] && $buttons) {
			foreach($buttons as $button) {
				$buttonImage = $buttonImages[substr($button, strlen('button_'))]['file'];
				$buttonTitle = $buttonImages[substr($button, strlen('button_'))]['title'];
				$replacements[] = str_replace("#MOODBUTTON#", globalURL("art/$buttonImage"), 
																				str_replace("#MOODBUTTONLABEL#", $buttonTitle, $substInfo['template']));
			}
			$replacement = join('', $replacements);
		}
		else $replacement = '';
		$message = str_replace($substInfo['fullpattern'], $replacement, $message);
	}
		
		
	//$message = str_replace('#SENDER#', $signature, $message);
	//echo $body.'<p>';
	$hasBreakTags = strpos($message, '<p') !== FALSE || strpos($message, '<br') !== FALSE;
	if(!$hasBreakTags) {
		$message = str_replace("\r", "", $message);
		$message = str_replace("\n\n", "<p>", $message);
		$message = str_replace("\n", "<br>", $message);
	}
	
	return $message;
}

function savedEnhancedVisitReportTemplate() {
	return fetchFirstAssoc("SELECT body FROM tblemailtemplate WHERE label = '#STANDARD - Enhanced Visit Report Email'");
}

function visitReportDataPacketNugget($appointmentid, $bizptr=null) {
	$bizptr = $bizptr ? $bizptr : $_SESSION["bizptr"];
	require_once "encryption.php";
	return urlencode(lt_encrypt("bizid=$bizptr&id=$appointmentid"));
}


function visitReportDataPacket($appointmentid, $internalUse=false) { // to be returned as a JSON array 

	$prefs = $_SESSION['preferences'];
	$trans = array('bizName'=>'BIZNAME', 'shortBizName'=>'BIZSHORTNAME', 'bizEmail'=>'BIZEMAIL', 'bizHomePage'=>'BIZHOMEPAGE', 'bizPhone'=>'BIZPHONE');
	foreach(explode('|', 'bizName|shortBizName|bizEmail|bizHomePage|bizPhone') as $p)
		$pack[$trans[$p]] = $prefs[$p];
		
	if($bizAddress = $prefs['bizAddressJSON']) {
		$bizAddress = json_decode($bizAddress);
		$pack['BIZADDRESS1'] = $bizAddress->street1;
		$pack['BIZADDRESS2'] = $bizAddress->street2;
		$pack['BIZCITY'] = $bizAddress->city;
		$pack['BIZSTATE'] = $bizAddress->state;
		$pack['BIZZIP'] = $bizAddress->zip;
	}
	else {
		$bizAddress = explode('|', $prefs['bizAddress']);
		$pack['BIZADDRESS1'] = $bizAddress[0];
		$pack['BIZADDRESS2'] = $bizAddress[1];
		$pack['BIZCITY'] = $bizAddress[2];
		$pack['BIZSTATE'] = $bizAddress[3];
		$pack['BIZZIP'] = $bizAddress[4];
	}
	
	$pack['BIZLOGINPAGE'] = "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION["bizptr"]}";

	$report = visitReportDataForApptId($appointmentid);
	
	require_once "preference-fns.php";
	if($_SESSION) $bizptr = $_SESSION['bizptr'];
	else {
			global $biz;
			$bizptr = $biz['bizid'];
	}
	$report['VISITPHOTOURL'] = visitPhotoURL($appointmentid, $internalUse, $bizptr);
	$report['MAPROUTEURL'] = visitMapURL($appointmentid, $internalUse, $bizptr);


	$trans = array('clientptr'=>'CLIENTID', 'clientfname'=>'CLIENTFNAME', 'clientlname'=>'CLIENTLNAME', 'providerptr'=>'SITTERID');
	foreach($trans as $k =>$p)
		$pack[$p] = $report[$k];
	require_once "provider-fns.php";
	$pack['SITTER'] = getDisplayableProviderName($report['providerptr']);
	
	$includeValue = inclusionPreferences($report['clientptr'], $includeFields);
//global $db;echo "$db: ".print_r($includeValue, 1).'<hr>';	
	foreach($includeValue as $k => $includeField) {
		if($includeField) {
			$pack[$k] = $report[$k];
			if($k == 'VISITPHOTOURL')
				$pack['VISITPHOTONUGGETURL'] = visitPhotoNuggetURL($appointmentid, $bizptr);
			if($k == 'MAPROUTEURL')
				$pack['MAPROUTENUGGETURL'] = visitMapNuggetURL($appointmentid, $bizptr=null);
		}
	}
	//$pack = array_merge($pack, $report);
	$pets = $report['pets'];
	if($pets == 'All Pets') {
		require_once "pet-fns.php";
		$pack['PETS'] = getClientPetNames($report['clientptr'], $inactiveAlso=false, $englishList=false);
		$pack['PETSENGLISH'] = getClientPetNames($report['clientptr'], $inactiveAlso=false, $englishList=true);;
	}
	else if(count($names = explode(', ', $pets)) > 1) {
		$pack['PETS'] = $names;
		$lastName = array_pop($names);
		$pack['PETSENGLISH'] = join(', ', $names)." and $lastName";
	}
	else /* 1 pet */ {
		$pack['PETS'] = array($pets);
		$pack['PETSENGLISH'] = $pets;
	}
	return $pack;
}

function framedVisitNoteForEnhancedVisitReport($note, $appt) {
	$clientptr = $appt['clientptr'];
	$standardMessage = savedEnhancedVisitReportTemplate();
	if($standardMessage)
		$standardMessage = $standardMessage['body'];
	else 
		$standardMessage = standardEnhancedVisitReportEmailTemplateBody();
	
	if(($start = strpos($standardMessage, '#IF_VISITNOTE#')) !== FALSE
			&& ($end = strpos($standardMessage, '#END_VISITNOTE#')) !== FALSE
			&& $end > $start) {
		if(!$note) $standardMessage =  str_replace(substr($standardMessage, $start, $end+strlen('#END_VISITNOTE#')-$start), '', $standardMessage);
		else {
			$messagePart1 = substr($standardMessage, 0, $start);
			$bracketTextStart = $start+strlen('#IF_VISITNOTE#');
			$bracketText = $note ? substr($standardMessage, $bracketTextStart, $end-$bracketTextStart) : '';;
			$messagePart2 = substr($standardMessage, $end+strlen('#END_VISITNOTE#'));
			$standardMessage = "$messagePart1$bracketText$messagePart2";
		}
	}
	if(($start = strpos($standardMessage, '#IF_NOVISITNOTE#')) !== FALSE
			&& ($end = strpos($standardMessage, '#END_NOVISITNOTE#')) !== FALSE
			&& $end > $start) {
		if($note) $standardMessage =  str_replace(substr($standardMessage, $start, $end+strlen('#END_NOVISITNOTE#')-$start), '', $standardMessage);
		else {
			$messagePart1 = substr($standardMessage, 0, $start);
			$bracketTextStart = $start+strlen('#IF_NOVISITNOTE#');
			$bracketText = $note ? '' : substr($standardMessage, $bracketTextStart, $end-$bracketTextStart);

			$messagePart2 = substr($standardMessage, $end+strlen('#END_NOVISITNOTE#'));
			$standardMessage = "$messagePart1$bracketText$messagePart2";
		}
	}
	$client = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient WHERE clientid = '$clientptr'");
	$pets = $appt['pets'];
	if($pets == 'All Pets') {
		require_once "pet-fns.php";
		$pets = getClientPetNames($clientptr, $inactiveAlso=false, $englishList=true);
	}
	else if(count($names = explode(', ', $pets)) > 1) {
		$lastName = array_pop($names);
		$pets = join(', ', $names)." and $lastName";
	}
	$displayableSitterName = getDisplayableProviderName($appt['providerptr']);
	$subs = array('#RECIPIENT#'=>$client['clientname'],
								'#FIRSTNAME#'=>$client['fname'],
								'#LASTNAME#'=>$client['lname'],
								'#SITTER#'=>$displayableSitterName, 
								'#PETS#'=>$pets, 
								'#BIZNAME#'=>$_SESSION["bizname"],
								'#BIZEMAIL#'=>$_SESSION["preferences"]["bizEmail"],
								'#BIZPHONE#'=>$_SESSION["preferences"]["bizPhone"],
								'#BIZHOMEPAGE#'=>$_SESSION["preferences"]["bizHomePage"],
								'#BIZLOGINPAGE#'=>"http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION["bizptr"]}",
								//'#LOGO#'=>logoIMG(),
								'#VISITNOTE#'=>$note);
	foreach($subs as $token => $sub)
		$standardMessage = str_replace($token, $sub, $standardMessage);
	return $standardMessage;
}	

function enhancedVisitReportBannerURL() { // FOR #ENHANCED_REPORT_BANNER#
	$bizfiledirectory = "bizfiles/biz_{$_SESSION['bizptr']}/";
	if(file_exists($bizfiledirectory)
		&& file_exists($bizfiledirectory.'clientui') 
		&& file_exists($bizfiledirectory.'clientui/Header.jpg'))
		return globalURL($bizfiledirectory.'clientui/Header.jpg');
		
	// else return logo
	if(file_exists($bizfiledirectory)) {
		$headerBizLogo = $bizfiledirectory;
		if(file_exists($bizfiledirectory.'logo.jpg')) $headerBizLogo .= 'logo.jpg';
		else if(file_exists($bizfiledirectory.'logo.gif')) $headerBizLogo .= 'logo.gif';
		else if(file_exists($bizfiledirectory.'logo.png')) $headerBizLogo .= 'logo.png';
		else $headerBizLogo = '';
		if($headerBizLogo) return globalURL($headerBizLogo);
	}
}

function inclusionPreferences($clientid, $includeFields=null) {
	if($includeFields) $includeFields = explode(',', $includeFields); // e.g., 'ARRIVED,MAPROUTEURL,COMPLETED'
	$inclusionPairs =
		"ARRIVED|enhancedVisitReportArrivalTime
			COMPLETED|enhancedVisitReportCompletionTime
			MOODBUTTON|enhancedVisitReportMoodButtons
			MAPROUTEURL|enhancedVisitReportRouteMap
			VISITPHOTOURL|enhancedVisitReportPetPhoto
			NOTE|enhancedVisitReportVisitNote";
	foreach(explode("\n", $inclusionPairs) as $line) {
		$line = explode('|', trim($line));
		if($includeFields) $includeValue[$line[0]] = in_array($line[0], $includeFields);
		else $includeValue[$line[0]] = getClientPreference($clientid, $line[1]);
	}
	
	if($includeFields) {
		$includeValue['VISITPHOTOURL'] = 
			in_array('INCLUDEPHOTO', $includeFields)
			|| in_array('VISITPHOTOURL', $includeFields)  // why, oh why?
			;
		$includeValue['NOTE'] = 
			in_array('INCLUDENOTE', $includeFields)
			|| in_array('NOTE', $includeFields)  // why, oh why?
			;
	}

	return $includeValue;
}

/*function extractSitterNote($str) {
	// handles format:
	// [VISIT: 06:01 AM] Nice work today with early morning walk.   [MGR NOTE]
	if(!strpos(''.$str, '[MGR NOTE]')) return trim($str);
	$start = strpos($str, ']')+1;
	return trim(substr($str, $start, strpos($str, '[MGR NOTE]')-$start));
}*/

function extractSitterNote($str) {
	// handles format:
	// [VISIT: 06:01 AM] Nice work today with early morning walk.   [MGR NOTE]
	// handles substitution tokens %26=>ampersand
	$str = str_replace("%26", "&", $str);
	$str = str_replace("%2f", "/", $str);
	
	$mgrNoteStart = strpos(''.$str, '[MGR NOTE]');
	$visitStart = strpos(''.$str, '[VISIT: ');
	if($mgrNoteStart === FALSE && $visitStart === FALSE) return trim($str);
	$start = $visitStart !== FALSE ? strpos($str, ']')+1 : 0;
	$end = $mgrNoteStart !== FALSE ? $mgrNoteStart : strlen($str); 
	return trim(substr($str, $start, $end-$start));
}


function moodButtonImages() { // mood=>(('title'=>'', 'file'=>'basename'))
	$rawmoods =
		"angry|angry|mood-angry.png
		cat|cat sit|mood-catsit.png
		happy|happy|mood-happy.png
		hungry|hungry|mood-hungry.png
		litter|litter|mood-litter.png
		pee|pee|mood-pee.png
		play|play|mood-play.png
		poo|poo|mood-poo.png
		sad|sad|mood-sad.png
		shy|shy|mood-shy.png
		sick|sick|mood-sick.png";
	foreach(explode("\n", $rawmoods) as $line) {
		$mood = explode("|", trim($line));
		$buttonImages[$mood[0]] = array('title'=>$mood[1], 'file'=>$mood[2]);
	}
	return $buttonImages;
}

function petsSubstitution($appt, $short=false) {
	$pets = $appt['pets'];
	
	if($pets == 'All Pets') {
		require_once "pet-fns.php";
		$pets = getPetNamesForClients(array($appt['clientptr']), $inactiveAlso=false, $englishList=true);
		$pets = $pets[$appt['clientptr']];
	}
	if($short) {
		require_once "gui-fns.php";
		$pets = truncatedLabel($pets, 25);
	}
	return $pets;
}

function bracketedContent($message, $token) {
	$leftBracket = "#START$token#";
	$leftBracketStart = strpos($message, $leftBracket);
	$rightBracket = "#END$token#";
	$rightBracketStart = strpos($message, $rightBracket);
	$arr = array();
	if($leftBracketStart === FALSE || $rightBracketStart === FALSE) return $arr;
	$arr['fullpattern'] = substr($message, $leftBracketStart, $rightBracketStart+strlen($rightBracket)-$leftBracketStart);
	$arr['template'] = substr($arr['fullpattern'], 
														strlen($leftBracket), 
														strpos($arr['fullpattern'], $rightBracket)-strlen($leftBracket));
	return $arr;
}

function visitReportList($start=null, $end=null, $clientptrs=null, $fullReports=false) {
	// IGNORES receivedonly submittedonly publishedonly, for now
	if($start) {
		$start = date('Y-m-d', strtotime($start));
		$dateTest[] = "date >= '$start'";
	}
	if($end) {
		$end = date('Y-m-d', strtotime($end));
		$dateTest[] = "date <= '$end'";
	}
	
	if(!$clientptrs && !($start && $end))
		$error = array('error'=>"if no clientid is specified, both start and end must be supplied.");
	else if($dateTest) $dateTest = "AND (".join(' AND ', $dateTest).")";
	else if(!$clientptrs) 
		$error = array('error'=>"at least one of start, end, and clientid must be supplied.");
		
	if($clientptrs && is_array($clientptrs))
		$clientptrs = join(',', $clientptrs);
		
	if(!$error) {
		$propertiesClause = //$published 
			"property IN ('"
					.join("','", explode(',', 'reportsubmissiondate,reportsubmissiontype,reportIsPublic,reportPublicDetails,visitphotocacheid,visitmapcacheid'))
					."')";
		$clientTest = $clientptrs ? "AND clientptr IN ($clientptrs)" : "";
		$reports = array();

		$result = doQuery(($sql = 
			"SELECT appointmentptr, property, value
				FROM tblappointmentprop
				LEFT JOIN tblappointment ON appointmentid = appointmentptr
				WHERE 
					$propertiesClause
					$clientTest
					$dateTest"), 1);

		while($row = leashtime_next_assoc($result))
			$reports[$row['appointmentptr']][$row['property']] = $row['value'];

	// receivedonly - when supplied and not null/zero, omit visits where no report elements have been received
	// submittedonly - when supplied and not null/zero, include only reports that have been submitted
	// publishedonly - when supplied and not null/zero, include only reports that have been published (sent to user)
		foreach($reports as $apptid => $report) {
			if($report['reportsubmissiondate']) $submittedReports[$apptid] = $apptid;
			if($report['reportIsPublic']) $publishedReports[$apptid] = $apptid;
		}
		

		if($receivedonly && count($reports) == 0) $appts = array();
		else if($submittedonly && count($submittedReports) == 0) $appts = array();
		else if($publishedonly && count($publishedReports) == 0) $appts = array();
		else {
			if($publishedonly) $apptids = $publishedReports;
			else if($submittedonly) $apptids = $submittedReports;
			else if($receivedonly) $apptids = array_keys($reports);
			else $apptids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE canceled IS NULL $clientTest $dateTest", 1);
			
}		

			if($apptids) {
				$appts = fetchAssociationsKeyedBy(
					"SELECT appointmentid, date as visitdate, 
							timeofday as visittimeframe, 
							label as service,
							providerptr,
							clientptr,
							pendingchange
						FROM tblappointment
						LEFT JOIN tblservicetype ON servicetypeid = servicecode
						WHERE appointmentid IN (".join(',',$apptids).")
						ORDER BY date, starttime", 'appointmentid', 1);

				foreach($appts as $apptid => $appt) {
					if($report = $reports[$apptid])
						foreach($report as $key => $val)
							$appt[$key] = $val;
					if($appt['reportIsPublic']) {
						$appt['reportPublishedDatePretty'] = date('Y-m-d', strtotime($appt['reportIsPublic']));
						$appt['reportPublishedTimePretty'] = date('h:i a', strtotime($appt['reportIsPublic']));
					}
					$appt['visitdatepretty'] = shortDate(strtotime($appt['visitdate']));
					$appt['sitter'] = getDisplayableProviderName($appt['providerptr']);
					$appt['url'] = globalURL("visit-report-data.php?id=$apptid");
					$nugget = visitReportDataPacketNugget($apptid);
					$appt['externalurl'] = globalURL("visit-report-data.php?nugget=$nugget");
					$appt['status'] = 
						$appt['reportIsPublic'] ? 'published' : (
						$appt['reportsubmissiondate'] ? 'submitted' : (
						$reports[$appt['appointmentid']] ? 'maporphotoreceived' : 
						'noreportdatareceived'));
					if($appt['pendingchange'] && $appt['pendingchange'] < 0) $appt['pendingchangetype'] = 'cancel';
					else {
						$req = fetchFirstAssoc(
							"SELECT requesttype, extrafields FROM tblclientrequest 
							  WHERE requestid = ".abs($appt['pendingchange'])
							  ." LIMIT 1", 1);
						if($req['requesttype'] != 'schedulechange') $appt['pendingchangetype'] = $req['requesttype'];
						else { // handle new requesttype: schedulechange
							require_once "request-fns.php";
							$extras = getHiddenExtraFields($req);
							$appt['pendingchangetype'] = $extras['changetype'];
						}
					}
						
					//$fullReports = mattOnlyTEST();
					if($fullReports) {
						$appt['reportdata'] = visitReportDataPacket($appt['appointmentid'], $internalUse=true);
					}
					$finalReports[$appt['appointmentid']] = $appt;
				}
			}
		}
		return $finalReports;
	}
	else return $error;
}	