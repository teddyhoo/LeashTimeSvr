<? // client-sched-request-fns.php

function setLatestScheduleStep2Time($formPost) {
	// used in client-sched-makerV2.php
	require_once "preference-fns.php";
	setClientPreference($_SESSION['clientid'], 'latestScheduleStep2Time', date('Y-m-d H:i:s'));
	//$savedPost = json_encode($formPost);
	$savedPost = generatePayloadVersion0($formPost);
	$bagTextId = insertTable('tbltextbag', array('referringtable'=>'tblclientpref', 'body'=>$savedPost), 1);
	setClientPreference($_SESSION['clientid'], 'latestIncompleteScheduleTextId', "$bagTextId");
}

function clearLatestScheduleStep2Time() {
	// used in client-sched-makerV2.php
	require_once "preference-fns.php";
	setClientPreference($_SESSION['clientid'], 'latestScheduleStep2Time', null);
	$savedScheduleTextId = getClientPreference($_SESSION['clientid'], 'latestIncompleteScheduleTextId');
	if($savedScheduleTextId)
		deleteTable('tbltextbag', "textbagid=$savedScheduleTextId", 1);
	setClientPreference($_SESSION['clientid'], 'latestIncompleteScheduleTextId', null);
}

function getLatestIncompleteSchedulePost($clientid, $decoded=false) {
	$savedScheduleTextId = getClientPreference($clientid, 'latestIncompleteScheduleTextId');
	if($savedScheduleTextId)
		$postText = fetchRow0Col0("SELECT body FROM tbltextbag WHERE textbagid=$savedScheduleTextId LIMIT 1", 1);
	if($postText) return $decoded ? json_decode($postText, 'assoc') : $postText;
}

function scheduleDescriptionFromPost($clientptr, $note) {
	$schedule = scheduleFromNote($note);
//print_r($schedule);exit;	
	$requestnote = explode("\n", $note);  // $schedule['note'];, if we ever add it
	$requestnote = $requestnote[2];  // $schedule['note'];, if we ever add it
	//if($requestnote) $requestnote = urldecode(str_replace("\n", "<br>", str_replace("\n\n", "<p>", trim($requestnote))));
	if($requestnote) $requestnote = urldecode(trim($requestnote));
	else $requestnote = "";

	$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = '$clientptr' LIMIT 1");
	
	// Note format
	// line 0: start|end|totalCharge
	// line 2: service|service|..<>service|service|..<>
	// service: servicecode#timeofday#pets
	// returns array(start|end|totalCharge, services=>(service1, service2, ...), (service1, service2, ...),...)
	// $schedule['services'] format: day0=>array(array(servicecode,timeofday,pets,charge),...), day1=>(...)
	$dayAsTime = strtotime($schedule['start']);
	foreach((array)($schedule['services']) as $day) {
		$numServices += count($day);
		if(count($day)) {
			$activeDays += 1;
			$dayVisits[] = longDayAndDate($dayAsTime);
			foreach($day as $service) 
				$dayVisits[] = "{$service['timeofday']} {$_SESSION['servicenames'][$service['servicecode']]} {$service['pets']}";
			$dayVisits[] = "";	
		}
		$dayAsTime = strtotime("+1 day", $dayAsTime);
	}
	$message = "Client $clientName has requested $numServices visits on $activeDays days between "
							.longDayAndDate(strtotime($schedule['start'])).' and '
							.longDayAndDate(strtotime($schedule['end'])).".\n\n";
	if($requestnote) $message .= "==========\nNote: $requestnote\n==========\n\n";
	$message .= join("\n", $dayVisits);
	
	return $message;
}
	

function findIncompleteScheduleRequestClients($returnAll=false) {
	require_once "preference-fns.php";
	$yesterday = date('Y-m-d', strtotime("-1 day"));
	$returnAll = $returnAll ? '' : "AND value LIKE '$yesterday%'";
	$sql = "SELECT clientptr, value FROM tblclientpref WHERE property = 'latestScheduleStep2Time' $returnAll";
	return fetchKeyValuePairs($sql);
}

function notificationTextForIncompleteScheduleRequests($clientStalls) {
	require_once "field-utils.php";
	$yesterday = date('Y-m-d', strtotime("-1 day"));
	
	require_once "email-template-fns.php";
	ensureStandardTemplates($type='client');
	
	$template = fetchRow0Col0("SELECT templateid FROM tblemailtemplate WHERE label = '#STANDARD - Problems Scheduling?'", 1);
	if(!$template) $subj = "Having problems?";
	$output = "<h2>Incomplete Schedule Requests</h2>"
		."The following clients started a schedule request but stopped short of submitting it:<p>"
		."<table border=1><tr><th>Client</th><th>Time</th><th>phone</th><th>email</th></tr>";
	foreach($clientStalls as $clientptr => $time) {
		$client = fetchFirstAssoc(
			"SELECT CONCAT_WS(' ', fname, lname) as name, lname, fname, email 
				FROM tblclient WHERE clientid = $clientptr LIMIT 1", 1);
		$fname = safeValue($client['fname']);
		$lname = safeValue($client['lname']);
		$name = fauxLink($client['name'], "openConsoleWindow(\"clientview\", \"client-view.php?id=$clientptr\",650,600)", 1, 'View client in pop up.');
		$email = !$client['email'] ? '' : fauxLink($client['email'], "openComposer($clientptr, \"$lname\", \"$fname\", \"{$client['email']}\", \"$subj\", \"$template\")", 1, 'Write an email to the client.');
		$clientPhones = fetchFirstAssoc(
			"SELECT homephone, cellphone, workphone, cellphone2
				FROM tblclient WHERE clientid = $clientptr LIMIT 1", 1);
		$labels = null;
		foreach($clientPhones as $fld => $phone) {
			if(!$phone) continue;
			$label = "({$fld[0]})".strippedPhoneNumber($phone);
			if($phone[0] == '*') $label = "<b>$label</b>";
			$labels[] = $label;
		}
		if($labels) $labels = join('<br>', $labels);
		$stallTime = substr($time, 0, 8) == $yesterday ? 'Yesterday' : shortDateAndDay(strtotime($time));
		$stallTime .= ' '.date('g:i a', strtotime($time));
		$description = '<i>No schedule details available.</i>';
		if($savedPost = getLatestIncompleteSchedulePost($clientptr))
			$description = str_replace("\n", "<br>", str_replace("\n\n", "<p>", scheduleDescriptionFromPost($clientptr, $savedPost)));
		$stallTime = fauxLink($stallTime, "$(\"#details_$clientptr\").toggle()", 1, "Show details");
		$output .= "<tr><td>$name</td><td>$stallTime</td><td>$labels</td><td>$email</td></tr>";
		$output .= "<tr><td colspan=4 id='details_$clientptr' style='display:none;'>$description</td></tr>";
	}
	$output .= "</table>";
	$output .= "<p>This information is provided in case you want to reach out to the relevant clients.";
	$output .= "<p>The client name link opens a pop up viewer on the client&apos;s information.<br>The email link opens an email composer.";
	return $output;
}
		
function generateIncompleteScheduleRequestSystemNotification($force=false) {
	require_once "preference-fns.php";
	// see: client-sched-previewV2.php
	$lastChecked = fetchPreference('incompleteSchedulesLastChecked');
	// if incompleteScheduleNotificationCheckMinutes, make sure that interval has passed since last check
	$interval = 60; //fetchPreference('incompleteScheduleNotificationCheckMinutes');
	if(!$force) {
		if(!$lastChecked) setPreference('incompleteSchedulesLastChecked', date('Y-m-d H:i:s'));
		if($interval && $lastChecked && (strtotime($lastChecked) + ($interval*60) > time())) 
			return;
		else setPreference('incompleteSchedulesLastChecked', date('Y-m-d H:i:s'));
	}
	$returnAll = FALSE;
//if(dbTEST('dogslife')) {
//	setPreference('TESTTEST', "$lastChecked interval: [$interval] returnAll: [$returnAll]");
//}
	if($inks = findIncompleteScheduleRequestClients($returnAll)) { // $returnAll=FALSE means: just look for yesterday's crop
		// $inks: $clientptr => $time
		$notification = notificationTextForIncompleteScheduleRequests($inks);
		//echo count($inks)." schedules found:<br>$notification";
		require_once "request-fns.php";
		$result =  saveNewSystemNotificationRequest("Incomplete Schedule Requests", $notification, $extraFields = null, $clientptr=null);
		foreach($inks as $clientid => $timestamp) {
			setClientPreference($clientid, 'latestScheduleStep2Time', null);
		}
		return $result;
	}
	//else echo "No inc schedules found.";
}
	


function scheduleFromNote($note) { // version 0: proprietary format
	// Note format (version 0)
	// line 0: start|end|totalCharge
	// line 2: service|service|..<>service|service|..<>
	// service: servicecode#timeofday#pets
	// return array(start|end|totalCharge, services=>(service1, service2, ...), (service1, service2, ...),...)
	
	if(!trim("$note")) return null;
	
	if(scheduleRequestPayloadIsJSON($note))
		return scheduleFromNoteVersion1($note);
	
	
	
	$lines = explode("\n", $note);

	$heading = explode('|', trim($lines[0]));
	$schedule = array('start' => $heading[0], 'end' => $heading[1],  'totalCharge' => $heading[2]);
	$days = explode('<>', trim($lines[1]));
	foreach($days as $i => $day) {
		$services = array();
		foreach(explode('|', $day) as $serv) {
			if($serv) {
				$serv = explode('#', $serv);
				$services[] = serviceArray($serv[0], $serv[1], $serv[2], 0);
			}
		}
		$schedule['services'][] = $services;
	}
	if($lines[2]) $schedule['note'] = urldecode($lines[2]);

	return $schedule;
}

function scheduleFromNoteVersion1($note) {
	$schedule = json_decode($note, 'assoc'); // assume the standard version one representation of schedule details
	getDayGroupedServices($schedule);
	return $schedule;
}

function scheduleRequestPayloadIsJSON($requestNote) {
	// Return false if the old (pre-JSON) form is apparent in the request note
	// Do not validate the JSON here.
	if(!trim("$requestNote")) return null;
	$lines = explode("\n", $requestNote);
	if(($line = $lines[0]) && count($heading = explode('|', trim("$line"))) != 3)
		return true;
	return false;
}

function generatePayloadVersion0($source) {
	global $longScheduleRequestTEST;
	extract($source);
	$payload = "$start|$end|$totalCharge\n";
	$services = array();
	$days = array();
	$day = '';

	if($longScheduleRequestTEST) {
		if($_POST['payload']) $postPayload = json_decode($_POST['payload'], $assoc=true);
		if(!$postPayload) $_POST['note'] .="\n BAD PAYLOAD? {$_POST['payload']}";
	}
	else $postPayload = $_POST;

	foreach($postPayload as $key=>$val) {
		if(strpos($key, 'servicecode_') === 0) {
			$specifier = substr($key, strlen('servicecode_'));
			$dayService = explode('_', $specifier);
			if($day && $dayService[0] != $day) {
				$days[] = join('|', $services);
				$services = array();
			}
//if($longScheduleRequestTEST && $dayService[0] != max($day, 1)) {
//echo "<hr>DAY: $day DAYSERVICE: {$dayService[0]}<p>";for($i=$day+1;$i<$dayService[0];$i++) $days[] = "";} // for empty days
			$day = $dayService[0];
//echo "$key => $specifier<br>";			
			if($postPayload["timeofday_$specifier"]) 
				$services[] = $postPayload["servicecode_$specifier"].'#'.$postPayload["timeofday_$specifier"].'#'.$postPayload["pets_$specifier"];
		}
	}
	$days[] = join('|', $services);
	$payload .= join('<>', $days);
	$payload .= "\n".urlencode($_POST['note']);
	return $payload;
}



function describeRequestedSchedule($requestOrRequestId, $style=null) {
	$request = is_array($requestOrRequestId) ? $requestOrRequestId
							: fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestOrRequestId");
	require_once "client-sched-request-fns.php";
	require_once "pet-fns.php";
	$allPets = getClientPets($request['clientptr']);
	$allServiceLabels = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype", 1);
	foreach($allPets as $pet) $petsByName[$pet['name']] = $pet;
	$schedule = scheduleFromNote($request['note']);
	$schedule['duration'] = count($schedule['services']);
	foreach($schedule['services'] as $dayNumber => $visits) {
		if($visits) {
			$schedule['dayswithvisits'] += 1; 
			$schedule['numberofvisits'] += count($visits); 
		}
		foreach($visits as $i => $visit) {
			$schedule['services'][$dayNumber][$i]['service'] = $allServiceLabels[$visit['servicecode']];
			$serviceTypesRequested[$allServiceLabels[$visit['servicecode']]] = 1;
			if($visit['pets']) {
				if($visit['pets'] == 'All Pets') {
					foreach($allPets as $pet) 
						$petsInvolved[$pet['name']] = $pet;
				}
				else foreach(explode(', ', $visit['pets']) as $petName) 
					$petsInvolved[$petName] = $petsByName[$petName];
			}
		}
	}
	// in the new json version, 'pets' is supplied as a string.  
	// This is not useful in this context, and actually gets in the way,
	// so we wipe 'pets' before proceeding.
	unset($schedule['pets']);
	foreach((array)$petsInvolved as $pet)
		$schedule['pets'][] = $pet['name'].($pet['type'] ? " ({$pet['type']})" : '');
	$schedule['numpets'] = count($schedule['pets']);
	if($schedule['pets']) $schedule['pets'] = join(', ', $schedule['pets']);
	if($serviceTypesRequested) $schedule['servicetypes'] = join(', ', array_keys($serviceTypesRequested));
	
	if($schedule['start'] == $schedule['end']) $desc = "Date: ".longestDayAndDate(strtotime($schedule['start']));
	else $desc = "Dates: ".longestDayAndDate(strtotime($schedule['start']))." to ".longestDayAndDate(strtotime($schedule['end']));
	
	$style = $style ? $style : 'Dates Only';
	if($style == 'Dates Only') return $desc;
	
	$desc .= "<br>(Duration {$schedule['duration']} days, {$schedule['numberofvisits']} visits,  {$schedule['dayswithvisits']} days with visits)";
	if(count($serviceTypesRequested) == 1) $desc .= "<br>Service: {$schedule['servicetypes']}";
	else $desc .= "<p>Services: {$schedule['servicetypes']}";
	if($schedule['numpets'] == 0) $desc .= "<br>Pet(s) served: none.";
	else if($schedule['numpets'] == 1) $desc .= "<br>Pet served: {$schedule['pets']}";
	else $desc .= "<p>Pet(s) served: {$schedule['pets']}";
	if($schedule['note']) $desc .= "<p>Note:<br>{$schedule['note']}";
	
	if($style == 'Detailed') {
		$desc .=  "<hr>";
		$dayAsTime = strtotime($schedule['start']);
		foreach($schedule['services'] as $dayNumber => $visits) {
			$desc .= "<p>".longDayAndDate($dayAsTime);
			if(!$visits) $desc .= " - NO VISITS";
			else foreach($visits as $i => $visit) {
				//if($visit) {print_r($visit);exit;}
				$desc .= "<br>{$visit['timeofday']} - {$_SESSION['servicenames'][$visit['servicecode']]} - {$visit['pets']}";
			}
			$dayAsTime = strtotime("+1 day", $dayAsTime);
		}
		$desc .=  "<hr>";
		
	}
	return $desc;
	
}				
		

function dumpScheduleLooks($daysToShow, $descriptionColor) {
	$width = 700 / $daysToShow;
	echo <<<LOOKS
<style>
.monthLook {text-align:center;}
.domLook {text-align:center;font-size:2em;}
.dowLook {text-align:center;font-size:1.2em;}
.dayblock {border-right: solid gray 1px; width: {$width}px;}
.dateblock {border: solid black 2px;}
.dateblockweekend {background: #E0FFFF; border: solid black 2px;}
.serviceblock {background: 	#FFFACD;padding:1px;margin-left:1px;margin-top:5px;margin-bottom:5px;border-bottom:solid black 1px;}
.descriptiontable {background:$descriptionColor;width:100%;}
.descriptiontable td {padding-bottom:0px;padding-top:0px;}
.previousnextlink {font-size:1.08em;font-weight:bold;text-decoration:none;}
</style>
LOOKS;
}

function displayDay($day, $index, $services=null, $viewOnly=false) {
	global $daysToShow;
	$time = strtotime($day);
	$blockclass = in_array(date('N', $time), array(6,7)) ? 'dateblockweekend' : 'dateblock';
	echo "<table id='daytable_$index' class='dayblock'>";
	echo "<tr><td style='padding-left:2px;padding-right:2px;'><table width=100% class='$blockclass'>";
	echo "<tr><td class='monthLook'>".date('F', $time)."</td></tr>";
	echo "<tr><td class='domLook'>".date('j', $time)."</td></tr>";
	echo "<tr><td class='dowLook'>".date('l', $time)."</td></tr>";
	echo "</table><td></tr>";
	echo "<tr><td>";
	if($viewOnly) 
		foreach($services as $service)
			serviceViewer($service, null, 'block');
	else dayBlockEditor($index);
	echo "</td></tr>";
	echo "</table>";
}

function dayBlockEditor($dayIndex) {
	global $maxServicesPerDay;
	$maxServicesPerDay = 10;
	$displayOn = 'block';
	for($i=1; $i<=$maxServicesPerDay; $i++) {
		// DON'T DISPLAY EDITORS INITIALLY
		$display = 'none';
		
		if($_REQUEST['payload']) $requestPayload = json_decode($_REQUEST['payload'], $assoc=true);
		else $requestPayload = $_REQUEST;
		
		$viewerDisplay = $requestPayload["servicecode_$dayIndex"."_$i"] ? 'block' : 'none';
		// $display = /*($serviceCount == 0 && $i==1 && $dayIndex==1) || */$_REQUEST["servicecode_$dayIndex"."_$i"] ? $displayOn : 'none';
		$specifier = "$dayIndex"."_$i";

		$service = serviceArray($requestPayload["servicecode_$specifier"], 
														$requestPayload["timeofday_$specifier"], 
														$requestPayload["pets_$specifier"],
														$requestPayload["charge_$specifier"]);
		serviceEditor($service, $specifier, $display);
		serviceViewer($service, $specifier, $viewerDisplay);
	}
	echoButton('', 'Add a Service', "addService($dayIndex)", $class='Button AddAService', $downClass='ButtonDown AddAService');
}

function serviceArray($service, $timeofday, $pets, $charge) {
	return array('servicecode'=>$service, 'timeofday'=>$timeofday, 'pets'=>$pets, 'charge'=>$charge );
}

function serviceViewer($service, $specifier, $display) {
	echo "<div id='serviceview_$specifier' class='serviceview' style='display:$display;border-bottom:solid black 1px;margin-bottom:7px;'>";
	echo serviceDescription($service, $specifier, $display);
	echo "</div>";
}

function serviceDescription($service, $specifier=null, $display) {
	global $globalServiceSelections, $scheduleDays;
	$serviceSelections = array_merge(array(''=>''), $globalServiceSelections);
	$servicelabel = array_search($service["servicecode"], $serviceSelections);
	if(!$servicelabel) $servicelabel = 
		"<i title='Service type is inactive'>["
			.fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = '{$service["servicecode"]}'")
			."]</i>";
	if(!$servicelabel || !$service["timeofday"]) return '';
	ob_start();
	ob_implicit_flush(0);

	echo "<table align=center class='descriptiontable'>";
	$serviceSelections = array_merge(array(''=>''), $globalServiceSelections);
	echo "<tr><td colspan=3>$servicelabel</td><tr>\n";
  
	echo "<tr><td colspan=3>{$service['timeofday']}</td></tr>";
	//echo "<tr><td colspan=3>{$service['pets']}</td></tr>";
	$dayIndex = explode('_', $specifier);
	$dayIndex = $dayIndex[0];
	if($specifier) {
		$specArgs = str_replace('_', ', ', $specifier);
		echo "<tr><td>";
		fauxLink('Copy', "copyAction(this, $specArgs)", $noEcho=false, $title=null, $id=null, $class='VisitCopyLink fauxlink');
		echo '</td><td>';
		fauxLink('Edit', "editAction($specArgs)", $noEcho=false, $title=null, $id=null, $class='VisitEditLink fauxlink');
		echo '</td><td>';
		fauxLink('Delete', "deleteService($specArgs)", null, 'Delete this service', null, 'VisitDeleteLink redfauxlink');
		echo "</td></tr>";
		echo "<tr id='copyrow_$specifier' style='display:none;'>
			<td colspan=3 class='fauxlink'><a onclick='copyToTomorrow($specArgs)'>...to Tomorrow</a>"
			.($dayIndex > 1 && $dayIndex < $scheduleDays ? "<br><a onclick='copyToAllFutureDays($specArgs)'>...to All Future Days</a>" : '')
			."<br><a onclick='copyToAllDays($specArgs)'>...to All Days</a></td></tr>";
	}
	echo "</table>";

	$str = ob_get_contents();
	ob_end_clean();
	return $str;
}

function serviceEditor($service, $specifier, $display) {
	global $client, $scheduleDays, $globalServiceSelections, $defaultPetsChoice;
	$colspan = 2;
	echo "<div id='service_$specifier' class='serviceblock' style='display:$display;'>";
	echo "<table align=center border=0 bordercolor=red>";
	echo "<tr><td colspan=$colspan>Service<br>";
	$serviceSelections = array_merge(array(''=>''), $globalServiceSelections);
	foreach($serviceSelections as $label=>$val) $options .= "<option title='Hello' value='$val'>$label</option>";
  
	selectElement('', "servicecode_$specifier", $service["servicecode"], $serviceSelections, "setCharge(this, \"charge_$specifier\")");
	echo "</tr>";
	echo "<tr style='display:none'><td colspan=$colspan>Charge: $<span id='span_charge_$specifier'>{$service["charge"]}</span></td></tr>";
	hiddenElement("charge_$specifier", $service["charge"]);
	echo "<tr><td colspan=$colspan>Time of Day<br>";
	//
//service-fns.php buttonDiv($divid, $formelementid, $onClick, $label, $value='', $extraStyle=null, $title=null, $class=null)
	buttonDiv("div_timeofday_$specifier", "timeofday_$specifier", 
						"if(document.getElementById(\"ContentDiv\")) showTimeFramerInContentDiv(event, \"div_timeofday_$specifier\");
						 else showTimeFramer(event, \"div_timeofday_$specifier\");",
						$service["timeofday"], $service["timeofday"], 'background:white;', null, 'timeofdaybuttondiv');
	echo "</td></tr>";
	echo "<tr><td colspan=$colspan>Pets<br>";
	$petsValue = $service["pets"] ? $service["pets"] : $defaultPetsChoice;
	buttonDiv("div_pets_$specifier", "pets_$specifier", 
							"if(document.getElementById(\"ContentDiv\")) showPetGridInContentDiv(event, \"div_pets_$specifier\");
							 else showPetGrid(event, \"div_pets_$specifier\")",
						 $petsValue, $petsValue, 'background:white;', null, 'petspecifierbuttondiv');
	echo "</td></tr>";
	
	echo "<tr><td>";
	$specArgs = str_replace('_', ', ', $specifier);
	fauxLink('Done', "doneAction($specArgs)");
	echo '</td><td>';
	fauxLink('Delete', "deleteService($specArgs)", null, 'Delete this service', null, 'redfauxlink');
	echo "</td></tr>";
	echo "</table></div>";
}
//##############################################
function dumpClientScheduleDisplayJS($displayOn, $numDays) {
	global $daysToShow;
	$daysToShow = $daysToShow ? $daysToShow : 3;
	echo <<<JS
if (navigator.appName == "Microsoft Internet Explorer")
	document.getElementsByNameArray = 

	function(name){
		var out = []; 
		function getElementsByNameDelegate(elem, eName, results) {
		 if(elem.name && elem.name == eName) results.push(elem);
		 for(var i=0; i<elem.childNodes.length;i++) 
			getElementsByNameDelegate(elem.childNodes[i], eName, results);
		}
		getElementsByNameDelegate(document, name, out);
		return out;
	}
else document.getElementsByNameArray = 
	function(name){
		var out = [];
		els = document.getElementsByName(name);
		for(var i=0;i<els.length;i++)
			out.push(els.item(i));
		return out;
	};
	
var numDays = $numDays;
var blockStyle = '$displayOn';

function getElement(id) { var el; if(!(el = document.getElementById(id))) alert('Bad element id: '+id); else return el;}

function slideRight() {
	// go back one day
	if(getElement('day_1').style.display != 'none') {
		alert('This is the first day.');
		return;
	}
	// make the last invisible day before the first visible day visible
	for(var i=1; i <= numDays; i++) 
		if(getElement('day_'+i).style.display != 'none') {
				getElement('day_'+(i-1)).style.display = blockStyle;
				break
			}
	// make the last visible day invisible 
	for(; i <= numDays; i++) { //alert(i);
		if(getElement('day_'+i).style.display == 'none') {
				break
			}
	}
	getElement('day_'+(i-1)).style.display = 'none';

	// show nextLink if last day is invisible
	if(getElement('day_'+numDays).style.display == 'none') {
		var nextLinks = document.getElementsByNameArray('nextLink');
		nextLinks[0].style.display = 'inline';
		nextLinks[1].style.display = 'inline';
	}
	if(document.getElementById('day_1').style.display != 'none') {
		var previousLinks = document.getElementsByNameArray('previousLink');
		previousLinks[0].style.display = 'none';
		previousLinks[1].style.display = 'none';
	}
}

function slideToEnd() {
	var daysToShow = document.getElementById('daysToShow');
	daysToShow = daysToShow == null ? $daysToShow : daysToShow.value;
	for(var day = numDays; day > 0 && day > numDays - daysToShow; day--)
		document.getElementById('day_'+day).style.display = blockStyle;
	for(day = day-1; day > 0; day--)
		document.getElementById('day_'+day).style.display = 'none';
	if(getElement('day_1').style.display == 'none') {
		var previousLinks = document.getElementsByNameArray('previousLink');
		previousLinks[0].style.display = 'inline';
		previousLinks[1].style.display = 'inline';
	}
	if(getElement('day_'+numDays).style.display != 'none') {
		var nextLinks = document.getElementsByNameArray('nextLink');
		nextLinks[0].style.display = 'none';
		nextLinks[1].style.display = 'none';
	}
}

function slideToStart() {
	var daysToShow = document.getElementById('daysToShow');
	daysToShow = daysToShow == null ? $daysToShow : daysToShow.value;
	for(var day = 1; day <= numDays && day <= daysToShow; day++)
		document.getElementById('day_'+day).style.display = blockStyle;
	for(day = day+1; day <= numDays; day++)
		document.getElementById('day_'+day).style.display = 'none';
	if(getElement('day_1').style.display == 'none') {
		var previousLinks = document.getElementsByNameArray('previousLink');
		previousLinks[0].style.display = 'none';
		previousLinks[1].style.display = 'none';
	}
	if(getElement('day_'+numDays).style.display == 'none') {
		var nextLinks = document.getElementsByNameArray('nextLink');
		nextLinks[0].style.display = 'inline';
		nextLinks[1].style.display = 'inline';
	}
}

function slideLeft() {
	var start = new Date().getTime();
	// go forward one day
	if(getElement('day_'+numDays).style.display != 'none') {
		alert('This is the last day.');
		return;
	}
	// make the first visible day invisible
	for(var i=1; i <= numDays; i++) 
		if(getElement('day_'+i).style.display != 'none') {
				document.getElementById('day_'+i).style.display = 'none';
				break;
			}
	// make the first invisible day visible 
	for(i=i+1; i <= numDays; i++) {
		if(getElement('day_'+i).style.display == 'none') {
				getElement('day_'+i).style.display = blockStyle;
				break;
			}
	}
	// show previousLink if first day is invisible
	if(getElement('day_1').style.display == 'none') {
		var previousLinks = document.getElementsByNameArray('previousLink');
		previousLinks[0].style.display = 'inline';
		previousLinks[1].style.display = 'inline';
	}
	if(getElement('day_'+numDays).style.display != 'none') {
		var nextLinks = document.getElementsByNameArray('nextLink');
		nextLinks[0].style.display = 'none';
		nextLinks[1].style.display = 'none';
	}
	
//alert('time: '+(new Date().getTime()-start)+' ms');	
	return false;
}


JS;
}

function showScheduleTableWizardVersion($schedule, $offerGenerateButton=null, $request=null) {  // called from request-edit.php and...
	global $globalServiceSelections;
	static $currencyMark;
	if(!$currencyMark) $currencyMark = getCurrencyMark();
	$globalServiceSelections = getClientServices();
	$daysToShow = 6;
	$descriptionColor = '#B7FFDB';
	echo "<tr><td id='profilechanges' colspan=2 style='border: solid black 1px;background-color:white;'>";
	dumpScheduleLooks($daysToShow, $descriptionColor);
	//array(start|end|totalCharge, services=>(service1, service2, ...), (service1, service2, ...),...)
	$start = longDayAndDate(strtotime($schedule['start']));
	$end = longDayAndDate(strtotime($schedule['end']));
	$totalCharge = number_format($schedule['totalCharge'], 2);
	$numAppointments = 0;
	$noVisitDays = 0;
	$scheduleDays = 0;
	foreach($schedule['services'] as $group) {
		if($group) $numAppointments += count($group);
		else $noVisitDays++;
		$scheduleDays++;
	}
	
	if($offerGenerateButton) {
		$editButton = 
			echoButton('', 'Edit Schedule', scheduleWizardAction($schedule, $request['requestid']), '', '', true);
	}
		
	echo <<<HEADER
	<table style='width:100%;text-align:center;'>
	<tr><td colspan=3>
	Schedule starts on : <b>$start</b> and ends on <b>$end</b> ($scheduleDays days)<p>
	</td></tr>
	<tr><td>Visits: $numAppointments</td><td>Days without visits: $noVisitDays</td><td>Price: $currencyMark$totalCharge<td></tr>
	</table>
HEADER;
 	$previousLink = fauxLink("<span class='previousnextlink'>&lt; Show Previous Days</span>", 'slideRight()', 1, 'Show prevous days', '', 'previousnextlink');
 	$nextLink = fauxLink("<span class='previousnextlink'>Show Following Days ></span>", 'slideLeft()', 1, 'Show Following days', '', 'previousnextlink');
	$navRow1 = "<tr><td colspan=2>$previousLink</td><td style='text-align:center;vertical-align:top;'>$editButton</td><td colspan=".($daysToShow-4)." align='right'>$nextLink</td></tr>";
	$navRow2 = "<tr><td colspan=2>$previousLink</td><td colspan=".($daysToShow-2)." align='right'>$nextLink</td></tr>";

	echo "<table style='width:100%;border-collapse:collapse;padding-left:0px;padding-right:0px;'>$navRow1<tr>";

	$nDays = 1;
	$displayOn = $_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';

	$day = date('Y-m-d', strtotime($schedule['start']));
	foreach(getDayGroupedServices($schedule) as $index => $group) { 
		$dayNum = $index + 1;
		$displayStatus = $index < $daysToShow ? $displayOn : 'none';
		echo "<td id='day_$dayNum' style='display:$displayStatus;vertical-align:top;'>\n";
		displayDay($day, $dayNum, $group, 'displayOnly');
		echo "</td>\n";
		$day = date('Y-m-d', strtotime('+1 day', strtotime($day)));
	}
	echo "</tr>$navRow2</table></td></tr>";
}

function getDayGroupedServices(&$schedule) {
	if($schedule['services']) 
		return $schedule['services'];
	$day = date('Y-m-d', strtotime($schedule['start']));
	$lastDay = date('Y-m-d', strtotime($schedule['end']));
	$index = 0;
	while($day <= $lastDay) {
		foreach($schedule['visits'] as $visit) {
			if($visit['date'] == $day)
				$schedule['services'][$index][] = $visit;
		}
		$day = date('Y-m-d', strtotime('+1 day', strtotime($day)));
		$index += 1;
	}
	return $schedule['services'];
}



function showScheduleTable($schedule, $offerGenerateButton=null, $packageToEdit=null, $request=null) {  // called from request-edit.php and...
	// called from request-edit.php and...
	global $globalServiceSelections, $daysToShow;
	static $currencyMark;
	if(!$currencyMark) $currencyMark = getCurrencyMark();
	$globalServiceSelections = getClientServices();
	$daysToShow = 6;
	$descriptionColor = '#B7FFDB';
	echo "<tr><td id='profilechanges' colspan=2 style='border: solid black 1px;background-color:white;'>";
	dumpScheduleLooks($daysToShow, $descriptionColor);
	//array(start|end|totalCharge, services=>(service1, service2, ...), (service1, service2, ...),...)
	$start = longDayAndDate(strtotime($schedule['start']));
	$end = longDayAndDate(strtotime($schedule['end']));
	$totalCharge = number_format($schedule['totalCharge'], 2);
	$numAppointments = 0;
	$noVisitDays = 0;
	$scheduleDays = 0;
	foreach(getDayGroupedServices($schedule) as $group) {
		if($group) $numAppointments += count($group);
		else $noVisitDays++;
		$scheduleDays++;
	}
	
	$previewButton = echoButton('', 'Preview Schedule', previewAction($schedule), '', '', true);
	$otherButton = '';
	if($offerGenerateButton) {
		/*if(!staffOnlyTEST()) $otherButton = $packageToEdit
				//? echoButton('', 'Edit Schedule', editScheduleAction($packageToEdit), '', '', true)
				? echoButton('', 'Edit Schedule', "window.opener.document.location.href=\"service-irregular.php?packageid={$packageToEdit['packageid']}\"", '', '', true)
				: echoButton('', 'Create Schedule', "window.opener.document.location.href=\"service-irregular-create.php?request={$request['requestid']}\"", 'BigButton', 'BigButtonDown', true);
				*/
		$otherButton = $packageToEdit
				//? echoButton('', 'Edit Schedule', editScheduleAction($packageToEdit), '', '', true)
				? echoButton('', 'Edit Schedule', "scheduleButtonAction(this, \"service-irregular.php?packageMustExist=1&packageid={$packageToEdit['packageid']}\")", '', '', true)
				: echoButton('', 'Create Schedule', "scheduleButtonAction(this, \"service-irregular-create.php?request={$request['requestid']}\")", 'BigButton', 'BigButtonDown', true);
				
		global $db;
		if(!$packageToEdit) {
			ob_start();
			ob_implicit_flush(0);
			echo "<br>Sitter: ";
			availableProviderSelectElement($schedule['clientptr'], $date=null, 'chosenprovider', $nullChoice, $choice, $onchange, $offerUnassigned=false);
			$str = ob_get_contents();
			//echo 'XXX: '.ob_get_contents();exit;
			ob_end_clean();
			$otherButton .= $str;
		}
	}
		
	echo <<<HEADER
	<table style='width:100%;text-align:center;'>
	<tr><td colspan=3>
	Schedule starts on <b>$start</b> and ends on <b>$end</b> ($scheduleDays days)<p>
	</td></tr>
	<tr><td>Visits: $numAppointments</td><td>Days without visits: $noVisitDays</td><td>Price: $currencyMark$totalCharge<td></tr>
	</table>
HEADER;
	$previousLink = mattOnlyTEST() 
		? ' '.fauxLink("<span class='previousnextlink'>&#9673;</span>", 'slideToStart()', 1, 'Show First days', '', 'previousnextlink')
		: '';
 	$previousLink .= fauxLink("<span class='previousnextlink'>&lt; Show Previous Days</span>", 'slideRight()', 1, 'Show prevous days', '', 'previousnextlink');
 	$nextLink = fauxLink("<span class='previousnextlink'>Show Following Days ></span>", 'slideLeft()', 1, 'Show Following days', '', 'previousnextlink');
	$navRow1 = "<tr><td colspan=2>$previousLink</td><td style='text-align:center;vertical-align:top;'>$previewButton</td><td style='text-align:center;vertical-align:top;'>$otherButton</td><td colspan=".($daysToShow-4)." align='right'>$nextLink</td></tr>";
	$navRow2 = "<tr><td colspan=2>$previousLink</td><td colspan=".($daysToShow-2)." align='right'>$nextLink</td></tr>";

	echo "<table style='width:100%;border-collapse:collapse;padding-left:0px;padding-right:0px;'>$navRow1<tr>";

	$nDays = 1;
	$displayOn = $_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';

	$day = date('Y-m-d', strtotime($schedule['start']));
	
	foreach($schedule['services'] as $index => $group) { 
		$dayNum = $index + 1;
		$displayStatus = $index < $daysToShow ? $displayOn : 'none';
		echo "<td id='day_$dayNum' style='display:$displayStatus;vertical-align:top;'>\n";
		displayDay($day, $dayNum, $group, 'displayOnly');
		echo "</td>\n";
		$day = date('Y-m-d', strtotime('+1 day', strtotime($day)));
	}
	echo "</tr>$navRow2</table></td></tr>";
}


function previewAction($schedule) {
	$start = urlencode($schedule['start']);
	$end = urlencode($schedule['end']);
	$totalCharge = $schedule['totalCharge'];
	// days = day|day|...
	// day = service,service,...
	// service = timeofday#servicecode
	$days = array();
	foreach(getDayGroupedServices($schedule) as $day) {
		$servs = array();
		foreach($day as $group) $servs[] = $group['timeofday'].'#'.$group['servicecode'];
		$days[] = join(',',$servs);
	}
	$days = urlencode(join('|', $days));
	$url = "\"client-sched-preview-popup.php?start=$start&end=$end&days=$days&price=$totalCharge&showlive={$schedule['clientptr']}\"";
	return "openConsoleWindow(\"schedulepreview\", $url, 900,700)";
}

function scheduleWizardAction($schedule, $requestid) {
	$start = urlencode($schedule['start']);
	$end = urlencode($schedule['end']);
	$totalCharge = $schedule['totalCharge'];
	// days = day|day|...
	// day = service,service,...
	// service = timeofday#servicecode
	$days = array();
	foreach($schedule['services'] as $day) {
		$servs = array();
		foreach($day as $group) $servs[] = $group['timeofday'].'#'.$group['servicecode'];
		$days[] = join(',',$servs);
	}
	$days = urlencode(join('|', $days));
	$url = "\"schedule-requested-visits-wizard.php?start=$start&end=$end&days=$days&price=$totalCharge&clientptr={$schedule['clientptr']}&reqid=$requestid\"";
	return "document.location.href=$url";
}

function editScheduleAction($schedule) {
	$packageid = $schedule['packageid'];
	$url = "\"calendar-package-irregular.php?packageid=$packageid&primary=\"";
	return "openConsoleWindow(\"schedulepreview\", $url, 900,700);window.opener.location.href=\"service-irregular.php?packageid=$packageid\";window.close();'";
}