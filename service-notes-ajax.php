<?
//service-notes-ajax.php
// Show all notes associated with a service, its appointments, its client, and its pets

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "service-fns.php";
require_once "request-fns.php";
require_once "pet-fns.php";

$locked = locked('o-');


$packageid = $_GET['packageid'];
$showSections = 'all';
?>
<head>
<link rel="stylesheet" href="pet.css" type="text/css" />
<style>
.labelcol { background:verylightblue;font-weight:bold;vertical-align:top; }
</style>
</head>
<body>
<?

echo "<table width=100%>\n<span style='font-size:1.1em'>";
//if($showSections != 'all') echo "<a href='".basename($_SERVER['SCRIPT_NAME'])."?show=all'>Show All Notes</a>\n";
//else echo "<a href='".basename($_SERVER['SCRIPT_NAME'])."?show=none'>Hide All Preferences</a>\n";
echo " <span class='tiplooks'>Click on a bar to shrink the section.  Click it again to expand it.<p></span></span>";

$n = 1;
if($packageid) {
	$package = getPackage($packageid);			
	$clientid = $package['clientptr'];
	$recurring = !isset($package['enddate']);
	$history = findPackageIdHistory($packageid, $clientid, $recurring);
	$history[] = $packageid;

	//package notes
	noteSection($package['notes'], 'Package Notes', $n);
	$n++;
}
else $clientid = $_GET['clientid'];

if($_SESSION['staffuser']) {
	requestSection($clientid, $n);
	$n++;
	futureRemindersSection($clientid, $n);
	$n++;
}

commSection($clientid, $n);
$n++;

//visit notes
$appts = fetchAssociations(
	"SELECT date, timeofday, starttime, note, canceled, completed
					FROM tblappointment
					WHERE clientptr = '$clientid' AND NOTE IS NOT NULL AND NOTE <> '' 
						AND note NOT LIKE '%Marked complete by manager'
						AND note NOT LIKE '%Appointment scheduled retroactively.'
					ORDER BY date DESC, starttime DESC");
$note = "<table>";
foreach($appts as $appt) {
	$date = shortDate(strtotime($appt['date'])).'<br><font color=gray>'.str_replace(' ', '&nbsp;', $appt['timeofday']).'</font>';
	$note .= "<tr><td class='labelcol'>$date</td><td>".noteOrNot($appt['note'])."</td></tr>";
}
$note .= "</table>";
noteSection($note, 'Visit Notes', $n);
$n++;

//client notes
$client = getClient($clientid);
$note = "<table>";
if($_SESSION["officenotes_logbook_enabled"]) {
	//$note .= "<tr><td class='labelcol'>Office Notes (private)</td><td>".fauxLink('View Office Notes Log', "showOfficeNotesLog()", 1)."</td></tr>";
	$note .= "<tr><td class='labelcol'>Office Notes (private)</td><td id='officenotessection'></span>";

}
else $note .= "<tr><td class='labelcol'>Office Notes (private)</td><td>".noteOrNot($client['officenotes'])."</td></tr>";
$note .= "<tr><td class='labelcol'>Notes</td><td>".noteOrNot($client['notes'])."</td></tr>";
$note .= "</table>";
noteSection($note, "Notes on client: {$client['fname']} {$client['lname']}", $n);
$n++;

	
//client notes
$pets = getClientPets($clientid, 'name,notes');
$note = "<table>";
foreach($pets as $pet)
	$note .= "<tr><td class='labelcol'>{$pet['name']}</td><td>".noteOrNot($pet['notes'])."</td></tr>";
$note .= "</table>";
noteSection($note, 'Pet Notes', 4);
echo "</table>\n";

function futureRemindersSection($clientid, $n) {
	if(!tableExists('tblreminder')) return;
	global $showSections;
	$reminders = fetchAssociations($sql = 
					"SELECT * 
						FROM tblreminder 
						WHERE clientptr = $clientid
							AND CHAR_LENGTH(sendon) < 10");  // Monday-Sunday or 1-31
							
	foreach($reminders as $reminder) {
		if(is_numeric($reminder['sendon'])) {
			$date = min(date("t"), $reminder['sendon']);
			if(strtotime(date("m/$date/Y")) > strtotime(date('Y-m-d'))) 
				$date = shortDate(strtotime(date("m/$date/Y")));
			else {
				$aMonthHence = strtotime("+1 month");
				$nextMonth = date('m', $aMonthHence); 
				$date = min(date("t", strtotime("+1 month")), $reminder['sendon']);
				$date = shortDate(strtotime(date("m/$date/Y")));
			}
		}
		else { // dow
			if(date('l') == $reminder['sendon']) $date = shortDate(strtotime("+ 1 week"));
			else $date = shortDate(strtotime("next ".$reminder['sendon']));
		}
		$reminder['date'] = $date;
		$rows[] = array('subject'=>$reminder['subject'], 'date'=>$date,
										'sortdate'=>date('Y-m-d', strtotime($date)), 
										'label'=>$reminder['label']);
	}
	
if(tableExists('tblreminder')) {
	$reminders = fetchAssociations($sql = 
					"SELECT * 
						FROM tblreminder 
						WHERE clientptr = $clientid
							AND CHAR_LENGTH(sendon) = 10");  // explicit date
}
	if(!$reminders) return;
	foreach($reminders as $reminder) {
		$date = shortDate(strtotime($reminder['sendon']));
		$rows[] = array('subject'=>$reminder['subject'], 'date'=>$date,
										'sortdate'=>date('Y-m-d', strtotime($date)), 
										'label'=>$reminder['label']);
	}
	usort($rows, 'commDateSort');
	startAShrinkSection('Upcoming Reminders', "section$n", ($showSections != 'all' && !in_array($n, $showSections)));
	$columns = array('date'=>'Date', 'subject'=>'Subject');
	$headerClass = 'sortableListHeader left';
	tableFrom($columns, $rows, 'WIDTH=100%', null, $headerClass, null, null, $sortableCols, $rowClasses, $colClasses, 'sortAppointments');
//tableFrom($columns, $rows, 'width=90%', $class=null, $headerClass=null, $headerRowClass, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
	endAShrinkSection();
}

function requestSection($clientid, $n) {
	global $showSections;
	$requests = fetchAssociations($sql = 
					"SELECT * 
						FROM tblclientrequest 
						WHERE clientptr = $clientid
							AND resolved = 0 ORDER BY received DESC");
							
	if(!$requests) return;
	require_once "comm-fns.php";
	global $requestTypes;
	foreach($requests as $request) {
		$received = shortDate(strtotime($request['received']));
		$resolution = $request['resolution'] ? $request['resolution'] : 'unresolved';
		//$subject = $request['requesttype'] == 'Reminder' ? "Reminder: {$request['street1']}" : $request['requesttype'];
		
		if($request['requesttype'] == 'Reminder') $subject = "Reminder: {$request['street1']}";
		else $subject = "Request: {$requestTypes[$request['requesttype']]}";
		
		$subjectClick = "openConsoleWindow(\"viewrequest\", \"request-edit.php?id={$request['requestid']}\",610,500)";
		$subject = fauxLink($subject, $subjectClick , 1);
		$rows[] = array('subject'=>$subject, 'received'=>$received,
										'sortdate'=>$request['received'], 'sortsubj'=>$request['street1']);
	}
	startAShrinkSection('Unresolved Requests', "section$n", ($showSections != 'all' && !in_array($n, $showSections)), 'background: red');
	$columns = array('received'=>'Date', 'subject'=>'Subject');
	$headerClass = 'sortableListHeader left';
	tableFrom($columns, $rows, 'WIDTH=100%', null, $headerClass, null, null, $sortableCols, $rowClasses, $colClasses, 'sortAppointments');
//tableFrom($columns, $rows, 'width=90%', $class=null, $headerClass=null, $headerRowClass, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
	endAShrinkSection();
}

function noteSection($str, $label, $n) {
	global $showSections;
	startAShrinkSection($label, "section$n", ($showSections != 'all' && !in_array($n, $showSections)));
	echo noteOrNot($str);
	endAShrinkSection();
}

function noteOrNot($str) {
	return $str ? $str : '<span style="color:green;font-style:italic;">-- No Note --</font>';
}

function commSection($clientid) {
	//if($_SERVER['REMOTE_ADDR'] != '68.225.89.173') return;
	require_once "comm-fns.php";
	$client = getClient($clientid);
	$comms = getCommsFor($client, "1=1", true, true, "ORDER BY datetime DESC LIMIT 10");
	if($comms) usort($comms, 'commRevDateSort');
	for($i=0;$i < count($comms) && $i < 10; $i++) $trimmed[] = $comms[$i];
	if($comms) $comms = $trimmed;
	//usort($comms, 'commDateSort');
	$rows = array();
	foreach((array)$comms as $comm) {
		$row['date'] = $comm['date'];
		$row['subject'] = $comm['type'] == 'request' //|| ($_SERVER['REMOTE_ADDR'] != '68.225.89.173')
			? $comm['subject'] 
			: fauxLink($comm['sortsubject'], "showMessage({$comm['listid']}, -1)", 1);
		$row['sender'] = $comm['sender'];
		$rows[] = $row;
		if($comm['type'] == 'message')
			$rows[] = array('#CUSTOM_ROW#'=> 
					"<tr id='row_{$comm['listid']}' style='display:none'><td colspan=3 style='padding-top:2px;padding-bottom:5px;border:solid orange 2px;'>{$comm['body']}</td></tr>\n");
	}
	global $showSections;
	$columns = array('date'=>'Date', 'subject'=>'Subject', 'sender'=>'Sender');
	startAShrinkSection('Recent Communication', "commsection", ($showSections != 'all' && !in_array("commsection", $showSections)));
	tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, $sortableCols, $rowClasses, $colClasses, 'sortAppointments');

}

function commRevDateSort($a, $b) {
	return strcmp($b['sortdate'], $a['sortdate']);
}

function commDateSort($a, $b) {
	return strcmp($a['sortdate'], $b['sortdate']);
}
?>
<script language='javascript'>

function showMessage(n, show) {
	var id = 'row_'+n;
	if(show == -1) {
		var displaystate = document.getElementById(id).style.display == 'none' ? 0 : 1;
		show = displaystate ? 0 : 1;
	}
	if(show) for(var i=0; document.getElementById('row_'+i); i++) document.getElementById('row_'+i).style.display = 'none';
	document.getElementById(id).style.display = show ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
}

function showOfficeNotesLog(summaryid, noteid) {
	var url = "logbook-editor.php?itemtable=client-office&itemptr=<?= $clientid ?>"
												+"&updateaspect=officenotes&&printable=1"
												+"&title=Office+Notes"
												+"&returnURL=<?= substr($_SERVER["REQUEST_URI"], 1) ?>"
												+"&returnTag=Back+To+All+Notes";
	if(summaryid && typeof summaryid != 'undefined') 
		url += "&targetid="+noteid+"&itemptr="+summaryid;
	$.fn.colorbox({href:url, width:"700", height:"650", iframe: true, scrolling: true, opacity: "0.3"});
	
}

function update(target, value) {	
	if(value && (typeof value == 'string') && value.indexOf('alert') != -1) alert(value);	
	
	if(target == 'officenotes') {
		var url = 'logbook-editor.php?summaryitemtable=client-office&summaryid=<?= $clientid ?>'
							+'&summarycount=3&summarytitle='+escape('Click to View Office Notes Log')
							+'&summarytotal=yes'
							//+"&returnURL=<?= substr($_SERVER["REQUEST_URI"], 1) ?>"
							//+"&returnTag=Back+To+All+Notes"
							+"&logOpenFunction=showOfficeNotesLog";
		ajaxGetAndCallWith(url, 
									function(arg, returnText) {
										returnText = returnText.split('##ENDCOUNT##');
										//document.getElementById('officenotescount').innerHTML = returnText[0];
										document.getElementById('officenotessection').innerHTML = returnText[1];
									},
									1);
	}
}

update('officenotes', 1); 


<? 

dumpShrinkToggleJS();
?>
</script>