<?
// nonrecurring-confict-resolution-form.php
// included by service-nonrepeating
/*
  $conflicts['timeConflicts'] = findTimeConflicts($newpackageid, $clientptr); // date=>new|old=>conflicts
  $conflicts['unassignedAppointments'] = findUnassignedAppointments($newpackageid);// date=>conflicts
  $conflicts['customConflicts'] = findCustomConflicts($packageid);  //date=>conflicts
*/

$clientIds = array();
foreach($conflicts as $conftype => $group) {
  if($conftype == 'timeConflicts')
    foreach($group as $date => $newOld) {
      $dates[] = $date;
      foreach($newOld['old'] as $row) {
				$clientIds[] = $row['clientptr'];
				$row['timeconflict'] = 1;
				$problems[$date][] = $row;
			}
    }
  else foreach($group as $date => $rows) {
			$dates[] = $date;
			foreach($rows as $row) {
				$clientIds[] = $row['clientptr'];
				$problems[$date][] = $row;
			}
  }
}
//print_r($problems);exit;
// Find client ID
$newAppointments = getAllTBDAppointments($packageid, false);
foreach($newAppointments as $date => $rows)
	foreach($rows as $row)
		if($row['clientptr']) $clientId = $row['clientptr'];
$clientId = $_REQUEST['client'];
$clientDetails = getOneClientsDetails($clientId);
$providerNames = getProviderShortNames();

$dates = array_unique($dates);
sort($dates);

echo "<h2>Your Changes Have Been Saved</h2>
<table width='100%'><tr><td>...but there are appointment issues you may wish to address:</td><td align=right>";
echoButton('','Delete Selected Visits','checkAndSubmit()', 'HotButton', 'HotButtonDown');
echo " ";
echoButton('','Quit',"checkAndSubmit(\"quit\")");
echo "</td></tr></table>";
//echo "PACKAGE: $packageid ";//print_r($newAppointments);
//echo print_r($problems);
echo "<form name='apptkiller' method='POST'>\n";
hiddenElement('packageid', $packageid);
hiddenElement('action', '');
hiddenElement('notify', $notify);
hiddenElement('killAppointments', 1);
hiddenElement('client', $_POST['client']);
echo "<table width='100%'>";
$duplicates = array();
foreach($problems as $day => $rows) {
	// display the day header
  echo "<tr><td colspan=6 style='background:lightblue;font-weight:bold;'>".longerDayAndDate(strtotime($day))."</td></tr>\n";
	// display the day's new appointments
	if($newAppointments[$day]) foreach($newAppointments[$day] as $appt) displayAppointmentConflict($appt, 'new');
	// display the day's problem appointments (minus any unassigned new appointments)
	foreach($problems[$day] as $appt) {
		if(!in_array($appt['appointmentid'], $duplicates))
		  if($appt['packageptr'] != $packageid) displayAppointmentConflict($appt, false);
		$duplicates[] = $appt['appointmentid'];
	}
}
echo "</table>";
echo "</form>";
function displayAppointmentConflict($appt, $new=false) {
	global $clientDetails, $providerNames;
	$class = $new ? '' : 'class="olderappointment"';
	$idstr = "'appt_{$appt['appointmentid']}'";
	$date = shortDate(strtotime($appt['date']));
	$prov = $appt['providerptr'] ? $providerNames[$appt['providerptr']] : "<span class='warning'>Unassigned</span>";
	$timeofday = $appt['timeconflict'] ? "<span class='warning'>{$appt['timeofday']}</span>" : $appt['timeofday'];
	//echo "<tr $class><td colspan=7>".print_r($appt, 1)."</td>";
	echo "<tr $class><td><input type='checkbox' id=$idstr name=$idstr></td>";
	echo "<td>$timeofday</td>";
	echo "<td>{$clientDetails[$appt['clientptr']]['clientname']}</td>";
	echo "<td>{$appt['pets']}</td>";
	echo "<td>".serviceLink($appt)."</td>";
	echo "<td>$prov</td>";
	echo "</tr\n";
}

function clientLink($clientptr, $clients) {
	return "<a href=#
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id=$clientptr\",700,500)'>
	       {$clientDetails[$appt['clientptr']]['clientname']}</a> ";
}
function serviceLink($row) {
	$petsTitle = $row['pets'] 
	  ? htmlentities("Pets: {$row['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	$targetPage = 'appointment-view.php';
	$label = $row['custom'] ? '<b>(M)</b> ' : '';
	$label .= $_SESSION['servicenames'][$row['servicecode']];
	return "<a href=# 
	       onClick='openConsoleWindow(\"editappt\", \"$targetPage?id={$row['appointmentid']}\",530,450)' 
	       >$label</a>"; //title='$petsTitle'
}
?>
<script language='javascript'>
function checkAndSubmit(action) {
	if(action == 'quit') {
		document.getElementById('action').value = 'quit';
		document.apptkiller.submit();
			    return;
	}
	
	for(var i=0;i<document.apptkiller.elements.length;i++)
	  if(document.apptkiller.elements.item(i).checked) {
	    document.apptkiller.submit();
	    return;
		}
	alert("No appointments were selected for deletion.\n Use the Quit button if you wish to retain all the appointments.");
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

</script>
