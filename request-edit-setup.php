<?
/* request-edit-setup.php
*
* Parameters: 
* id - id of the LeashTime business setup request to be edited.
* updateList - list in window opener to update after save
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "request-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "client-profile-request-fns.php";
require_once "client-sched-request-fns.php";
require_once "client-services-fns.php";

include "client-schedule-fns.php";
include "client-flag-fns.php";

// Verify login information here
locked('o-');
extract($_REQUEST);

$updateList = 'clientrequests';

if(!isset($id)) $error = "Request ID not specified.";

if($findClientFor) {  // AJAX
	echo fetchRow0Col0("SELECT clientptr FROM tblclientrequest WHERE requestid = $findClientFor LIMIT 1");
	exit;
}

if($id && $clientptr) { // set clientptr and redirect
	updateTable('tblclientrequest', array('clientptr'=>$clientptr), "requesttype = 'BizSetup' AND requestid = $id", 1);
	//print_r(fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $id LIMIT 1"));
	$custom29 = fetchRow0Col0("SELECT value FROM relclientcustomfield WHERE clientptr = $clientptr AND fieldname = 'custom29'");
	$trialFlag = fetchRow0Col0("SELECT value FROM tblclientpref WHERE clientptr = $clientptr AND property LIKE 'flag_%' AND value LIKE '1|%'");
//echo "[{$clientptr}] [{$custom29}] [{$trialFlag}]<p>";
//print_r(fetchAssociations("SELECT * FROM tblclientpref WHERE clientptr = $clientptr"));
//exit;	
	if(!$custom29) {
		if($trialFlag) {
			$trialFlag = explode('|', $trialFlag);
			if($trialFlag[1]) {
//print_r($trialFlag[1]);
//exit;
				$custom29 = $trialFlag[1];
				// we could check for date format here if we wanted
				$result = replaceTable('relclientcustomfield', 
											array('clientptr' => $clientptr, 'fieldname' => 'custom29', 
											'value'=>$custom29), 1);
			}
//print_r($result);
//exit;
		}
		if(!$custom29) { // look for trial login message
			$date = fetchRow0Col0(
				"SELECT datetime 
					FROM tblmessage 
					WHERE correspid = '$clientptr' AND correstable = 'tblclient' AND subject = 'Leashtime Trial Login'
					ORDER BY datetime DESC", 1);
			if($date) {
				$custom29 = date('n/j/Y', strtotime($date));
				$result = replaceTable('relclientcustomfield', 
											array('clientptr' => $clientptr, 'fieldname' => 'custom29', 
											'value'=>$custom29), 1);
			}
		}
	}
	echo "<script language='javascript'>document.location.href = 'request-edit-setup.php?id=$id';</script>";
}

if($_POST) {
	
	
	
	$oldRequest = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $id LIMIT 1");
	if(!$oldRequest['resolved'] && $_REQUEST['resolved'] && $_REQUEST['notifyuser']) {
		$openNotifierComposer = ";openNotifierComposer($id)";
		$openNotifierComposerDefinition = "
		function openNotifierComposer(requestid) {
			openConsoleWindow('noticewindow', 'request-notification-composer.php?pop=1&clientrequest='+requestid,700,500);
		}
		";
	}
//print_r($oldRequest);echo "<p>>>$openNotifierComposer";print_r($_REQUEST);		exit;
	updateClientRequest($_REQUEST);
	$updateList = $updateList ? "'$updateList'" : 'null';
	echo "<script language='javascript' src='common.js'></script><script language='javascript'>
	$openNotifierComposerDefinition"
	."if(window.opener.update) window.opener.update($updateList, null)$openNotifierComposer;window.close();</script>";
}


// #############################################################################
$windowTitle = "Edit Business Setup Request";
$customStyles = ".sectionHead {font-size:1.1em;background:lightblue;border:solid black 1px;font-weight:bold;margin:15px;}";

require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
$source = getClientRequest($id);
$source['date'] = longestDayAndDateAndTime(strtotime($source['received']));
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<?

displayRequestEditor($source, $updateList);

echo "<p align='center'>";
echoButton('', "Save As-Is", "checkAndSubmit()");
echo " ";
if(!$source['resolved']) {
	if($specialResolutionButton) {
		echo " $specialResolutionButton";
	}
	echoButton('', "Resolve and Save", "checkAndSubmit(\"resolve\")");
	if(!in_array($source['requesttype'], array('SystemNotification', 'Reminder', 'BillingReminder'))) {
		echo " ";
		labeledCheckbox('Notify User', 'notifyuser', $_SESSION['preferences']['requestResolutionEmail']);
		echo " ";
	}
}
else 
	echoButton('', "Mark Unresolved and Save", "checkAndSubmit(\"unresolve\")");
echo " ";
echoButton('', "Quit", 'window.close()');
echo "</p>";
echo "</form>";

if(!$noCommOrViewBox) {
	echo "<table width='95%'>\n<tr>\n";
	if($source['requesttype'] != 'SystemNotification') {
		echo "\n<td style='border: solid black 1px;padding:7px;vertical-align:top;text-align:right;'>Communicate: ";
		echoButton('', "Email", "openComposer(\"{$source['clientptr']}\", \"{$source['lname']}\", \"{$source['fname']}\", \"{$source['email']}\", \"{$source['requestid']}\")");
		echo "\n";
		echoButton('', "Log Call", "openLogger(\"{$source['clientptr']}\", \"phone\")");
		if(tableExists('tblreminder')) {
			echo "<br>\n";
			echoButton('', "Set a Reminder", "openConsoleWindow(\"remindereditor\", \"reminder-edit.php?pop=1&client={$source['clientptr']}\",700,500)");
		}
		echo "</td>";
	}
	if($source['clientptr']) {
		echo "\n<td style='border: solid black 1px;padding:7px;vertical-align:top;'>View Client's: ";
		echoButton('', "Visits", "editClient({$source['clientptr']}, \"services\")");
		echo "\n";
		echoButton('', "Account", "editClient({$source['clientptr']}, \"account\")");
		echo "\n";
		echoButton('', "Notes", "editClient({$source['clientptr']}, \"services\", \"notes\")");
		echo "\n</td>";
	}
	else if(!in_array($source['requesttype'], array('SystemNotification', 'Reminder'))) {
		echo "\n<td>";
		echoButton('', "New Client", "newClient($id)");
		echo "\n</td>";
	}
	echo "\n</table>";	
}


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


?>

</div>

<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 

<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>
<script language='javascript'>

function findClient(requestid) {
	 $.fn.colorbox({href:"request-edit-find-client.php?requestid="+requestid, width:"500", height:"500", iframe: true, scrolling: true, opacity: "0.3"});
			$setupReq = simplexml_load_string(urldecode($_POST['setupDetails']));
}

function setClientptr(clientid) {
	document.location.href = "request-edit-setup.php?id="+<?= $id ?>+"&clientptr="+clientid;
}

function openNotifierComposer(requestid) {
	openConsoleWindow('noticewindow', 'request-notification-composer.php?pop=1&clientrequest='+requestid,700,500);
}

function newClient(id) {
	window.opener.location.href='client-edit.php?requestid='+id;
}
	
function arrangeMeeting(id, mdate, mtime) {
	if(id == '') {
		ajaxGetAndCallWith('request-edit.php?findClientFor='+document.getElementById('requestid').value, 
			tryAgainToArrangeMeeting, mdate+' '+mtime);
	}
	else window.opener.location.href='client-meeting.php?clientptr='+id+'&startdate='+mdate+'&timeofday='+mtime;
}

function tryAgainToArrangeMeeting(dateTime, id) {
	if(id=='')
		alert('Please use the New Client button below to register this client first.');
	else {
		dateTime = dateTime.split(' ');
		arrangeMeeting(id, dateTime[0], dateTime[1]);
	}
}

function editClient(id, tab, action) {
	var url = 'client-edit.php?tab='+tab+'&id='+id;
	if(action == 'notes') url += '&viewallnotes=1';
	window.opener.location.href=url;
}

function setFollowOnReminder(requestid) {
	checkAndSubmit('resolve');
	openConsoleWindow('messageLogger', 'reminder-edit.php?pop=1&clientrequest='+requestid,700,500);
}
	
function checkAndSubmit(action) {
	if(action == 'resolve') {
		document.getElementById('resolved').value = 1;
	}
	else if(action == 'unresolve') document.getElementById('resolved').value = 0;
	document.requesteditor.submit();
}


function declineOrHonorRequest(id, honor) {
	honor = honor ? 1 : 0;
	var xh = getxmlHttp();
	xh.open("GET","request-accept-decline-ajax.php?request="+id+"&honor="+honor,true);
	xh.onreadystatechange=function() { if(xh.readyState==4) {
		//document.getElementById("cancelappts").innerHTML=xh.responseText;
		updateOpenerAndClose(xh.responseText);
	}
	}
  xh.send(null);
}

function updateOpenerAndClose(text) {
	if(window.opener.update) {
		window.opener.update('appointments');
		window.opener.update('clientrequests');
	}
	var notifyuserEl = document.getElementById("notifyuser");
	if(notifyuserEl && notifyuserEl.checked)
		openNotifierComposer(document.getElementById("requestid").value);
	window.close();
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

function openComposer(clientid, lname, fname, email, requestid) {
	var args = clientid ? 'client='+clientid : 'lname='+lname+'&fname='+fname+'&email='+email+'&prospect='+requestid;
	openConsoleWindow('emailcomposer', 'comm-composer.php?'+args,500,500);
}

function openLogger(clientid, emailOrPhone) {
	if(clientid)
		openConsoleWindow('messageLogger', 'comm-logger.php?client='+clientid+'&log='+emailOrPhone,500,500);
	else alert("You cannot log calls for this person until he is a client.");
}

function parentSwitch(el) {
	if(window.opener) window.opener.location.href=el.getAttribute('dest');
	window.close();
}

</script>
</body>
</html>
<?
function startForm($source, $updateList, $header) {
	$source['date'] = shortDate(strtotime($source['received']));
	echo "<h2>$header</h2>";
	echo "<form name='requesteditor' method='POST'>";
	hiddenElement('updateList', $updateList);
	hiddenElement('requestid', $source['requestid']);
	hiddenElement('operation', '');
	hiddenElement('resolved', $source['resolved']);
}

function displayNotification($source, $updateList) {
	startForm($source, $updateList, 'System Notification:');
	echo "\n<table width=100%>\n";
	labelRow('Date:', '', $source['date']);
	echo "\n</table>";
	echo "<div style='margin:5px; padding:5px; background:white;'>{$source['note']}</div><p>";
}

function displayRequestEditor($source, $updateList) {
	global $apptFields, $knownSourceFields;
	
	$knownSourceFields = explode(',', 'phone,fname,lname,requestid,clientptr,providerptr,whentocall,email,date'
																		.',address,street1,street2,city,state,zip,pets,note,requesttype');
	
	if($source['clientptr']) {
		$client = getOneClientsDetails($source['clientptr'], array('address', 'phone', 'lname', 'fname', 'pets', 'email'));
		$source['address'] = $client['address'];
		$source['phone'] = $source['phone'] ? $source['phone'] : $client['phone'];
		$source['fname'] = $source['fname'] ? $source['fname'] : $client['fname'];
		$source['lname'] = $source['lname'] ? $source['lname'] : $client['lname'];
		$source['email'] = $client['email'];
		if($client['pets']) $petsToShow[] = join("\n",$client['pets']);
		if($source['pets']) $petsToShow[] = $client['pets'] ? "(from request) {$source['pets']}" : $source['pets'];
		
		$source['pets'] = $petsToShow ? join("\n---------\n",$petsToShow) : '';
	}
	
	startForm($source, $updateList, "Client Request: ".requestLabel($source));
	if(dbTEST('leashtimecustomers')) fauxLink('Find client', 'findClient(document.getElementById("requestid").value)');
	hiddenElement('clientptr', $source['clientptr']);
	if($source['providerptr']) {
		require_once "provider-fns.php";
		$pname = getProvider($source['providerptr']);
		$pname = providerShortName($pname)." ({$pname['fname']} {$pname['lname']})";
		echo "<h3>Submitted by: $pname</h3>";
	}
	echo "\n<hr>\n";
	echo "\n<table width=100%>\n";
	echo "<tr><td valign=top><table width=100%>";
	if($client) {
		if($_SESSION['staffuser']) {
			require_once "client-flag-fns.php";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<tr><td colspan=2>".print_r($source, 1); }
			
			$flagPanel = clientFlagPanel($source['clientptr'], $officeOnly=false, $noEdit=true, $contentsOnly=true);
			echo "<tr><td colspan=2>$flagPanel</td></tr>";
		}
		
		$requestClientLink = reqClientLink($source);
		labelRow('From:', '', $requestClientLink, null, null, null, null, 'raw');
		$actualNameLink = reqClientLink($source, 'actual');
		if($requestClientLink != $actualNameLink) 
			labelRow('Client:', '', $actualNameLink, null, null, null, null, 'raw');
	}
	else {
	  labelRow('Business Name:', '', $source['fname']);
	  labelRow('Proprietor:', '', $source['lname']);
	}
	labelRow('Phone:', '', $source['phone']);
	labelRow('When to call:', '', $source['whentocall']);
	labelRow('Email:', '', $source['email']);
	echo "\n</table></td>\n";
	echo "<td valign=top><table width=100%>";
	labelRow('Date:', '', $source['date']);
	labelRow('Address:', '', '');
	if($source['address']) {
		echo "<tr><td colspan=2 style='padding-left:13px'>".str_replace("\n", '<br>', $source['address'])."</td></tr>";
	}
	else {
		$addr = array($source['street1'], $source['street2'], $source['city'], $source['state'], $source['zip']);
		echo "<tr><td colspan=2 style='padding-left:13px'>".htmlFormattedAddress($addr)."</td></tr>";
	}
	labelRow('Pets:', '', '');
	echo "<tr><td colspan=2 style='padding-left:13px'>".str_replace("\n", '<br>', $source['pets'])."</td></tr>";
	if($source['clientptr']) 
		$custom29 = fetchRow0Col0("SELECT value FROM relclientcustomfield 
							WHERE clientptr = {$source['clientptr']} AND fieldname = 'custom29'", 1);
	labelRow('Trial began:', '', $custom29);
	echo "\n</table></td></tr>\n";
	echo "\n</table>";
	echo "<table width=75%>";
	
	$note = $source['note'];
	echo "<tr><td>Note:</td><td style='border:solid black 1px;'>".prettyXML($note)."</td></tr>";
//echo "BANG!";exit;	
	displayExtraFields($source);
	
	echo "</table></td></tr>";
	
	
	
	
	echo "<tr><td colspan=2>&nbsp;</td></tr>";
	if($source['requesttype'] == 'cancel' || $source['requesttype'] == 'uncancel') {
		showCancellationTable($source, $source['requesttype'] == 'uncancel');
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
	}
	else if($source['requesttype'] == 'change') {
		showChangeTable($source);
	}
	else if($source['requesttype'] == 'Schedule') {
		$schedule['clientptr'] = $source['clientptr'];
	// $offerGenerateButton should probably be true when request has been declined or enddate and/or startdate are past
		$offerGenerateButton=true;
		$existingSchedule = !$offerGenerateButton
			? false
			: fetchFirstAssoc(
					"SELECT * 
						FROM tblservicepackage
						WHERE clientptr = {$source['clientptr']} 
						AND irregular = 1
						AND startdate = '".date('Y-m-d', strtotime($schedule['start']))."'
						AND enddate = '".date('Y-m-d', strtotime($schedule['end']))."'");
		showScheduleTable($schedule, $offerGenerateButton, $existingSchedule, $source);
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
	}
	echo "<tr><td colspan=3>Office Notes:</td></tr>";
	echo "<tr><td colspan=3><textarea id='officenotes' name='officenotes' rows=4 cols=80 class='sortableListCell'>".$source['officenotes']."</textarea></td></tr>";
	echo "<tr><td colspan=2>";
	//labeledCheckbox('Resolved:', 'resolved', $source['resolved']);
	echo "\n</td>\n";
	$viewLink = $source['clientptr'] 
		? fauxLink('Edit Client in Main Window', "window.opener.location.href=\"client-edit.php?id={$source['clientptr']}\"", 1)
		: fauxLink('View Client List in Main Window', "window.opener.location.href=\"client-list.php\"", 1);
	/*echo "</td><td>$viewLink</td>";*/
	echo "</tr>";
	echo "\n</table>";

}

/*function displayExtraFields($source) {
	$extraFields = getExtraFields($source);
	if($extraFields) {
		foreach($extraFields as $key => $value) {
			$keyParts = explode('-', $key);
			if(count($keyParts) < 3) continue;
			list($ignore, $ftype, $label) = $keyParts;
			if(strpos($label, 'meetingdate') === 0 || strpos($label, 'meetingtime') === 0) {
				if(strpos($label, 'meetingtime') === 0) continue;
				$n = substr($label, -1);
				if(!$extraFields["x-oneline-meetingdate$n"]) continue;
				$mdate = shortDate(strtotime($extraFields["x-oneline-meetingdate$n"]));
				$mtime = $extraFields["x-oneline-meetingtime$n"];
				labelRow("Meeting requested: $mdate $mtime", '', 
					echoButton('', 'Arrange Meeting', "arrangeMeeting(\"{$source['clientptr']}\", \"".urlencode($mdate)."\", \"".urlencode($mtime)."\")", $class='', 
											$downClass='', $noEcho=true, 'You can change date and time later.'),
					$labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
			}
			else if(in_array($ftype, array('select', 'oneline', 'radio')))
				labelRow($label.':', '', $value);
			else if($ftype == 'checkbox')
				labelRow($label.':', '', ($value ? 'yes' : 'no'));
			else if($ftype == 'multiline')
				echo "<tr><td valign=top>$label:</td><td>".str_replace("\n", '<br>', $value)."</td></tr>";
		}
	}
}	

*/
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
			$boxSize = array(200, 200);
			$photo = $pet['photo'];
			if(!file_exists($photo)) $photo = 'art/photo-unavailable.jpg';
			$dims = photoDimsToFitInside($photo, $boxSize);
			echo "<td rowspan=10 class='storedValue'><img src='pet-photo.php?version=display&id={$pet['petid']}' width={$dims[0]} height={$dims[1]}></td>";
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
	clientScheduleTable($appts, array('buttons'));
	if($source['resolution']) echo "Resolution: request ".$source['resolution'];
	else {
		echoButton('','Make Changes', "declineOrHonorRequest({$source['requestid']}, 1)");
		echo " ";
		echoButton('','Decline Request', "declineOrHonorRequest({$source['requestid']}, 0)");
	}
	echo "</td></tr>";
}

/*function showCancellationTable($source, $uncancel=null) {
	$scope = explode('_', $source['scope']);
	if($scope[0] == 'sole') $where = "WHERE appointmentid = {$scope[1]}";
	else if($scope[0] == 'day') 
		$where = "WHERE clientptr = {$source['clientptr']} AND date = '{$scope[1]}'";
	$appts = fetchAssociations("SELECT * FROM tblappointment $where");
	echo "<tr><td id='cancelappts' colspan=2 style='border: solid black 1px;'>";
	clientScheduleTable($appts, array('buttons'));
	
	if($source['resolution']) echo "Resolution: request ".$source['resolution'];
	else {
		if($uncancel)
			echoButton('','Un-Cancel Visit'.($scope[0] == 'sole' ? '' : 's'), "cancelAppointments({$source['requestid']})");
		else
			echoButton('','Cancel Visit'.($scope[0] == 'sole' ? '' : 's'), "cancelAppointments({$source['requestid']})");
		echo " ";
		echoButton('','Decline Request', "declineOrHonorRequest({$source['requestid']}, 0)");
	}
	echo "</td></tr>";
}*/

function reqClientLink($source, $useActualName=null) {
	if($useActualName) 
		$source = fetchFirstAssoc("SELECT *, clientid as clientptr FROM tblclient WHERE clientid = {$source['clientptr']}", 1);
	
	return <<<HTML
	<a  class='fauxlink' onClick="window.opener.location.href='client-edit.php?id={$source['clientptr']}'" title='@{$source['clientptr']}'>{$source['fname']} {$source['lname']}</a>
HTML;
}
