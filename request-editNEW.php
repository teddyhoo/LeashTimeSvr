<?
/* request-editNEW.php
*
* Parameters: 
* id - id of request to be edited.  officenotes and resolved are the only editable fields.
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

// Verify login information here
locked('o-');
extract($_REQUEST);

if(!isset($id)) $error = "Request ID not specified.";

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
	if($operation == 'applyProfileChanges') {
		//print_r($_POST);exit;
		$profileChangeRequests = getProfileChangeRequestsForRequestID($id, $clientptr);
		//print_r($profileChangeRequests);exit;
		$checkboxkeys = 'XXemergencycarepermission,active,fixed,haskey,dropphoto';
		
		
		// apply client changes
		$clientFieldNames = explode(',', 'fname,lname,fname2,lname2,email,primaryphone,cellphone,homephone,workphone,fax,pager,'.
										'notes,zip,street1,street2,city,state,mailzip,mailstreet1,mailstreet2,mailcity,mailstate,emergencycarepermission,'.
										//'leashloc,foodloc,parkinginfo,garagegatecode,directions,alarmcompany,alarmcophone,alarmpassword,alarmlocation,armalarm,disarmalarm');
										'leashloc,foodloc,parkinginfo,garagegatecode,directions,alarmcompany,alarmcophone,alarminfo');
		$proceedWithSection = false;
		foreach($profileChangeRequests as $key => $unused)
			if(in_array($key, $clientFieldNames) && isset($_POST[$key])) $proceedWithSection = true;
		if($proceedWithSection) {
			$changes = array();
			foreach($profileChangeRequests as $key => $unused) {
				if(!in_array($key, $clientFieldNames)) continue;
				$val = $_POST[$key];
				if(strpos($checkboxkeys, $key)) $val = $val ? 1 : 0;
				else if(strpos($key, 'phone') && ($primaryphone == $key)) $val = "*$val";
				$changes[$key] = $val;
			}
			if($changes) updateTable('tblclient', $changes, "clientid = $clientptr", 1);
//echo "[[[[clientptr: ".print_r($changes, 1)."]]]]<p>$clientptr";exit;			
		}

		// apply pet changes
		$proceedWithSection = false;
		foreach($profileChangeRequests as $key => $unused)
			if(is_numeric($index = nameSuffix($key))) $proceedWithSection = true;
		if($proceedWithSection) {
			$petPostFields = array();
			foreach($profileChangeRequests as $key => $valFromClient) {
				if(is_numeric($index = nameSuffix($key))) {
					$field = substr($key, 0, strpos($key, '_'));
					if($field == 'photo') $val = $id;  // stash the request id in photo
					else if(strpos($checkboxkeys, $field)) $val = $valFromClient ? 1 : 0;
					else $val = $_POST[$key];
					$petPostFields[$index-1][$field] = $val;
				}
			}
			$existingPets = getClientPets($clientptr);
			$errors = array();

			foreach($petPostFields as $index => $fields) {
				$petId = $index >= count($existingPets) ? null : $existingPets[$index]['petid'];
				if($result = saveClientPetChanges($index, $petPostFields[$index], $clientptr, $petId))
					$errors[] = $result;
			}
			if($errors)
				$error = count($errors) == 1 
					? $errors 
					: '<ul><li>'.join('<li>', $errors).'</ul>';
		}

		// apply contact changes
		$proceedWithSection = false;
		$contactSuffixes = array('emergency', 'neighbor');
		foreach($profileChangeRequests as $key => $unused)
			if(in_array(nameSuffix($key), $contactSuffixes)) $proceedWithSection = true;

		if($proceedWithSection) {
			//		create new contacts as necessary
			$contactPostFields = array();
			foreach($profileChangeRequests as $key => $unused) {
				if(in_array(($index = nameSuffix($key)), $contactSuffixes)) {
					$field = substr($key, 0, strpos($key, '_'));
					$val = $_POST[$key];
					if(strpos($checkboxkeys, $field)) $val = $val ? 1 : 0;
					$contactPostFields[$index][$field] = $val;
				}
			}
			$existingContacts = getKeyedClientContacts($clientptr);
			foreach($contactPostFields as $index => $fields) {
				$contactId = $index >= count($contactFields) ? null : $existingContacts[$index]['contactid'];
				$fields['type'] = $index;
				saveContactChanges($fields, $clientptr, $contactId);
			}
		}
		
		// apply custom field changes
		$customFields = getCustomFields('active');
		$proceedWithSection = false;
		foreach($profileChangeRequests as $key => $val)
			if(array_key_exists($key, $customFields)) {
				$proceedWithSection = true;
				$pairs[$key] = $val;
			}
		if($proceedWithSection) saveClientCustomFields($clientptr, $pairs);
		
		
		updateTable('tblclientrequest', 
			array('resolved'=>1, 'resolution'=>'honored', 'officenotes'=>sqlVal("CONCAT_WS('\\n','$dateTime', officenotes)")), "requestid = $id", 1);
	}
	else {
		updateClientRequest($_REQUEST);
	}
	$updateList = $updateList ? "'$updateList'" : 'null';
	echo "<script language='javascript' src='common.js'></script><script language='javascript'>
	$openNotifierComposerDefinition"
	."if(window.opener.update) window.opener.update($updateList, null)$openNotifierComposer;window.close();</script>";
}

$petFieldOrder = array('name','type','sex','breed','color','fixed','dob','description','active','notes');

// #############################################################################
$windowTitle = "Edit Client Request";
$customStyles = ".sectionHead {font-size:1.1em;background:lightblue;border:solid black 1px;font-weight:bold;margin:15px;}";

require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
$source = getClientRequest($id);
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<?

if($source['requesttype'] == 'SystemNotification') displayNotification($source, $updateList);
else if($source['requesttype'] == 'Reminder') displayReminder($source, $updateList);
else {
	displayRequestEditor($source, $updateList);
}

echo "<p align='center'>";
echoButton('', "Save As-Is", "checkAndSubmit()");
echo " ";
if(!$source['resolved']) {
	if($specialResolutionButton) {
		echo " $specialResolutionButton";
	}
	echoButton('', "Resolve and Save", "checkAndSubmit(\"resolve\")");
	if(!in_array($source['requesttype'], array('SystemNotification', 'Reminder'))) {
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


if($_SESSION['staffuser'])
{
	
	
echo "<table width='95%'>\n<tr>\n";
if($source['requesttype'] != 'SystemNotification') {
	echo "<td style='border: solid black 1px;padding:7px;vertical-align:top;text-align:right;'>Communicate: ";
	echoButton('', "Email", "openComposer(\"{$source['clientptr']}\", \"{$source['lname']}\", \"{$source['fname']}\", \"{$source['email']}\")");
	echo " ";
	echoButton('', "Log Call", "openLogger(\"{$source['clientptr']}\", \"phone\")");
	if(tableExists('tblreminder')) {
		echo "<br>";
		echoButton('', "Set a Reminder", "openConsoleWindow(\"remindereditor\", \"reminder-edit.php?pop=1&client={$source['clientptr']}\",700,500)");
	}
	echo "</td>";
}
if($source['clientptr']) {
  echo "<td style='border: solid black 1px;padding:7px;vertical-align:top;'>View Client's: ";
  echoButton('', "Visits", "editClient({$source['clientptr']}, \"services\")");
	echo " ";
  echoButton('', "Account", "editClient({$source['clientptr']}, \"account\")");
	echo " ";
  echoButton('', "Notes", "editClient({$source['clientptr']}, \"services\", \"notes\")");
	echo "</td>";
}
else if(!in_array($source['requesttype'], array('SystemNotification', 'Reminder'))) {
  echo " ";
  echoButton('', "New Client", "newClient($id)");
}
echo "</table>";	
	
}

else {
if($source['requesttype'] != 'SystemNotification') {
	echo "<div style='border: solid black 1px;display:inline;padding:7px;'>Communicate: ";
	echoButton('', "Email", "openComposer(\"{$source['clientptr']}\", \"{$source['lname']}\", \"{$source['fname']}\", \"{$source['email']}\")");
	echo " ";
	echoButton('', "Log Call", "openLogger(\"{$source['clientptr']}\", \"phone\")");
	if(tableExists('tblreminder')) {
		echo "<br>";
		echoButton('', "Set a Reminder", "openConsoleWindow(\"remindereditor\", \"reminder-edit.php?pop=1&client={$source['clientptr']}\",700,500)");
	}
	echo "</div>";
}
if($source['clientptr']) {
  echo "<img src='art/spacer.gif' width=30 height=1><div style='border: solid black 1px;display:inline;padding:7px;'>View Client's: ";
  echoButton('', "Visits", "editClient({$source['clientptr']}, \"services\")");
	echo " ";
  echoButton('', "Account", "editClient({$source['clientptr']}, \"account\")");
	echo " ";
  echoButton('', "Notes", "editClient({$source['clientptr']}, \"services\", \"notes\")");
	echo "</div>";
}
else if(!in_array($source['requesttype'], array('SystemNotification', 'Reminder'))) {
  echo " ";
  echoButton('', "New Client", "newClient($id)");
}
}







?>

</div>

<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

function openNotifierComposer(requestid) {
	openConsoleWindow('noticewindow', 'request-notification-composer.php?pop=1&clientrequest='+requestid,700,500);
}

function newClient(id) {
	window.opener.location.href='client-edit.php?requestid='+id;
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
		<? if($source['requesttype'] == 'Profile') { ?>
			applyProfileChanges(<?= $source['requestid'] ?>);
			return;
		<? } ?>
		document.getElementById('resolved').value = 1;
	}
	else if(action == 'unresolve') document.getElementById('resolved').value = 0;
	document.requesteditor.submit();
}

function applyProfileChanges() {
	var badPetNames = '';
	for(var i = 1; document.getElementById('name_'+i); i++)
		if(document.getElementById('name_'+i).value.indexOf(',') > -1)
			badPetNames++;
  if(MM_validateForm(  // should probably make more thorough checks here
		  badPetNames, '', 'MESSAGE')) {
		document.getElementById('resolved').value = 1;
		document.requesteditor.operation.value = 'applyProfileChanges';
		document.requesteditor.submit();
	}
}
	

function declineOrHonorRequest(id, honor) {
	honor = honor ? 1 : 0;
	var xh = getxmlHttp();
	xh.open("GET","request-accept-decline-ajax.php?request="+id+"&honor="+honor,true);
	xh.onreadystatechange=function() { if(xh.readyState==4) {
		//document.getElementById("cancelappts").innerHTML=xh.responseText;
		updateOpenerAndClose();
	}
	}
  xh.send(null);
}

function cancelAppointments(id) {
	var xh = getxmlHttp();
//alert("appointment-request-cancel-ajax.php?request="+id);
	xh.open("GET","appointment-request-cancel-ajax.php?request="+id,true);
	xh.onreadystatechange=function() { if(xh.readyState==4) {
		document.getElementById("cancelappts").innerHTML=xh.responseText;
		updateOpenerAndClose();
	}
	}
  xh.send(null);
}

function updateOpenerAndClose() {
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

function openComposer(clientid, lname, fname, email) {
	var args = clientid ? 'client='+clientid : 'lname='+lname+'&fname='+fname+'&email='+email;
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

<? 
if($source['requesttype'] == 'Schedule') {
	$schedule = scheduleFromNote($source['note']);
	$displayOn = $_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';
	dumpClientScheduleDisplayJS($displayOn, count($schedule['services'])); 
}
	?>

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

function displayReminder($source, $updateList) {
	global $specialResolutionButton;
	startForm($source, $updateList, "Reminder:");
	echo "\n<table width=100%>\n";
	labelRow('Date:', '', $source['date']);
	labelRow('Subject:', '', $source['street1']);
	$hiddenFields = getHiddenExtraFields($source);
	displayExtraFields($source);
	if($hiddenFields['clientptr']
		 && $client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = {$hiddenFields['clientptr']}")) {
		labelRow('Email:', '', $client['email']);
		require_once "field-utils.php";
		if($phone = primaryPhoneNumber($client)) {
			$phonefield = primaryPhoneField($client);
			$phonefield = $phonefield ? "({$phonefield[0]}) " : '';
			labelRow('Phone:', '', "$phonefield$phone");
		}
	}
	echo "<tr><td>Note:</td></tr>";
	echo "<tr><td colspan=2 bgcolor=white>".str_replace("\n", '<br>', $source['note'])."</td></tr>";
	echo "<tr><td colspan=3>Office Notes:</td></tr>";
	echo "<tr><td colspan=3><textarea id='officenotes' name='officenotes' rows=4 cols=80 class='sortableListCell'>".$source['officenotes']."</textarea></td></tr>";
	echo "\n</table>";
	if(strlen($hiddenFields['sendon']) == 10 // one-time
	   && !$hiddenFields['remindercode']) {  // non-group
	   $specialResolutionButton = echoButton('', 'Resolve and Set Follow-on Reminder', "setFollowOnReminder({$source['requestid']})", null, null, 1);
	}
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
		$source['pets'] = $client['pets'] ? join("\n",$client['pets']) : '';
	}
	
	startForm($source, $updateList, "Client Request: ".requestLabel($source));
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
	if($client) labelRow('Client:', '', reqClientLink($source), null, null, null, null, 'raw');
	else {
	  labelRow('First Name:', '', $source['fname']);
	  labelRow('Last Name:', '', $source['lname']);
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
	echo "\n</table></td></tr>\n";
	
	echo "<tr><td colspan=2><table width=75%>";
	$note = $source['note'];
	if($source['requesttype'] == 'Schedule') {
		$schedule = scheduleFromNote($note);
		$note = explode("\n", $source['note']);  // $schedule['note'];, if we ever add it
		$note = urldecode($note[2]);
	}
	echo "<tr><td>Note:</td><td>".str_replace("\n", '<br>', $note)."</td></tr>";
	
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
	else if($source['requesttype'] == 'Profile') {
		showProfileChangeTable($source);
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
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

function displayExtraFields($source) {
	$extraFields = getExtraFields($source);
	if($extraFields) {
		foreach($extraFields as $key => $value) {
			$keyParts = explode('-', $key);
			if(count($keyParts) < 3) continue;
			list($ignore, $ftype, $label) = $keyParts;
			if(in_array($ftype, array('select', 'oneline', 'radio')))
				labelRow($label.':', '', $value);
			else if($ftype == 'checkbox')
				labelRow($label.':', '', ($value ? 'yes' : 'no'));
			else if($ftype == 'multiline')
				echo "<tr><td valign=top>$label:</td><td>".str_replace("\n", '<br>', $value)."</td></tr>";
		}
	}
}	

function showProfileChangeTable($source) {
	global $rawBasicFields, $petFields, $homeFields;
		echo "<tr><td id='profilechanges' colspan=2 style='border: solid black 1px;background-color:white;'><table width=100%>";
	
	//if($_SESSION["auth_login_id"] != 'matt') return;
	
	$client = getClient($source['clientptr']);
	$changes = getProfileChangeRequests($source);
	echo "<table style=''><tr><th colspan=2 width=315>&nbsp;</th><th>Current Value</th></tr>";

	// BASIC SECTION
	$raw = explode(',', "$rawBasicFields");
	$fields = array();
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	$sectionChange = false;
	foreach($changes as $key => $val) 
		if(isset($fields[$key]) || isset($fields["mail$key"])) $sectionChange = $key;
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Client Profile Changes</td><tr>";
		foreach($changes as $key => $val) {
			$label = $fields[$key];
			if(!array_key_exists($key, $fields)) continue;
			$cval = isset($client[$key]) ? $client[$key] : '';
			if($key == 'email') deltaInputRow($label.':', $key, $val, $cval, null, 'emailInput');
			else if($key == 'notes') deltaTextRow($label.':', $key, $val, $cval);
			else if(strpos($key, 'phone')) deltaPhoneRow($label.':', $key, $val, $cval);
			else deltaInputRow($label.':', $key, $val, $cval, $labelClass=null, $inputClass='standardInput');
			if($key == 'vetname' || $key == 'clinicname') 
			  echo "<tr><td colspan=3 class='tiplooks'>Please note that you must edit the client's profile manually to change the above setting.</td></tr>";
			
		}
	}

	// ADDRESS SECTION
	$raw = explode(',', 'street1,Address,street2,Address 2,city,City,state,State,zip,ZIP');
	$fields = array();
	for($i=0;$i < count($raw) - 1; $i+=2) {
		$fields[$raw[$i]] = $raw[$i+1];
		$fields["mail".$raw[$i]] = $raw[$i+1];
	}
	$sectionChange = false;
	foreach($changes as $key => $val) 
		if(isset($fields[$key])) $sectionChange = $key;
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Addresses</td><tr>";

		deltaAddressTable('Home Address', '', $changes, $client, true);
		echo "<tr><td>&nbsp;</td><tr>";
		deltaAddressTable('Mailing Address', 'mail', $changes, $client, true);
	}

	// PETS SECTION
	$sectionChange = false;
	$prefixes = 'XXname_,type_,sex_,breed_,color_,fixed_,dob_,description_,active_,notes_,dropphoto_,photo_';
	foreach($changes as $key => $val)
		if(strpos($key, '_') 
				&& strpos($prefixes, substr($key, 0, strpos($key, '_')+1))
				&& is_numeric(nameSuffix($key)))
			$sectionChange = $key;
	if($sectionChange || isset($changes['emergencycarepermission'])) {
		uksort($changes, 'petSort');
		$pets = getClientPets($client['clientid']);
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr class='sectionHead'><td colspan=3>Pets</td><tr>";
		if(isset($changes['emergencycarepermission']))
			deltaCheckboxRow('Emergency Care Permission:', 'emergencycarepermission', 
												 $changes['emergencycarepermission'],$changes['emergencycarepermission']);

		$lastPetIndex = 0;
		foreach($changes as $key => $val) {
			$petIndex = nameSuffix($key);
			if(!is_numeric($petIndex)) continue;
			$field = substr($key, 0, strpos($key, '_'));
		//	show old values for previous section, if any, and start new pet section if index is not same as last
			if($petIndex != $lastPetIndex) {
				if($lastPetIndex) displayPetDescriptionWithPhoto($pets, $lastPetIndex);
				$lastPetIndex = $petIndex;
				$pet = isset($pets[$petIndex-1]) ? $pets[$petIndex-1] : array('name'=> 'New Pet');;
				echo "<tr><td colspan=3 style='text-align:center;text-decoration:underline;'>".($pet['name'] ? $pet['name'] : 'Unnamed Pet')."</td></tr>";
			}
			$label = $field == 'dropphoto' ? 'Drop Photo': $petFields[$field];
			if($field == 'sex') radioButtonRow("$label:", $key, $val, array('Male'=>'m', 'Female'=>'f'), '');
			else if($field == 'fixed' || $field == 'active' || $field == 'dropphoto') checkboxRow("$label:", $key, $val);
			else if($field == 'notes') {
				textRow($label.':', $key, $val, $rows=1, $cols=30);
			}
			else if($field == 'photo') {
				$photoName = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/{$source['requestid']}"."_$petIndex.jpg";
				if(!file_exists($photoName)) {
					$photoName = 'art/photo-unavailable.jpg';
					$src = $photoName;
				}
				else {
					$src = "pet-photo.php?version=client&id={$source['requestid']}"."_$petIndex";
				}
				$boxSize = array(300, 300);
				$dims = photoDimsToFitInside($photoName, $boxSize);
				echo "<tr><td valign=top>New Photo:</td><td colspan=2><img src='$src' WIDTH=$dims[0] HEIGHT={$dims[1]}></td></tr>";
			}
			else inputRow($label.':', $key, $val);
		}
		displayPetDescriptionWithPhoto($pets, $lastPetIndex);
	}

	// HOME SECTION
	$raw = explode(',', $homeFields);
	$fields = array();
	for($i=0;$i < count($raw) - 1; $i+=2) $fields[$raw[$i]] = $raw[$i+1];
	$sectionChange = false;
	foreach($changes as $key => $val) 
		if(isset($fields[$key])) $sectionChange = $key;
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Home Info</td><tr>";
		foreach($changes as $key => $val) {
			$label = $fields[$key];
			if(!array_key_exists($key, $fields)) continue;
			$cval = isset($client[$key]) ? $client[$key] : '';
			if($key == 'directions') deltaTextRow($label.':', $key, $val, $cval);
			else if(strpos($key, 'phone')) deltaPhoneRow($label.':', $key, $val, $cval);
			else deltaInputRow($label.':', $key, $val, $cval, $labelClass=null, $inputClass='standardInput');
		}
	}

	// EMERGENCY SECTION
	$sectionChange = false;
	$prefixes = 'name_,location_,homephone_,workphone_,cellphone_,haskey_,note_';
	foreach($changes as $key => $val)
		if(strpos($key, '_') 
				&& strpos($prefixes, substr($key, 0, strpos($key, '_')+1)) !== FALSE
				&& !is_numeric(nameSuffix($key)))
			$sectionChange = $key;
	if($sectionChange ) {
		$contacts = getKeyedClientContacts($client['clientid']);
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Emergency Contacts</td><tr>";
		$contact = isset($contacts['emergency']) ? $contacts['emergency'] : array();
		$contactChanges = contactChanges($changes, '_emergency');
		if($contactChanges) deltaContactRows($contactChanges, 'emergency', $contact, true);
		$contact = isset($contacts['neighbor']) ? $contacts['neighbor'] : array();
		$contactChanges = contactChanges($changes, '_neighbor');
		if($contactChanges) deltaContactRows($contactChanges, 'neighbor', $contact, true);
	}

	// CUSTOM FIELDS
	if($customFields = getCustomFields('active')) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Custom Fields</td><tr>";
		$clientCustomFields = getClientCustomFields($client['clientid']);

		foreach($customFields as $key => $descr) {
			if(!$changes[$key]) continue;
			if($descr[2] == 'oneline')
				deltaInputRow($descr[0].':', $key, $changes[$key], $clientCustomFields[$key], 'streetInput');
			else if($descr[2] == 'text')
				deltaTextRow($descr[0].':', $key, $changes[$key], $clientCustomFields[$key], 3, 40);
			else if($descr[2] == 'boolean')
				deltaCheckboxRow($descr[0].':', $key, $changes[$key], $clientCustomFields[$key]);
		}
	}
	echo "</table>";
	if($source['resolution']) echo "Resolution: request ".$source['resolution'];
	else {
		echoButton('','Make Changes', "applyProfileChanges({$source['requestid']})");
		echo " ";
		echoButton('','Decline Request', "declineOrHonorRequest({$source['requestid']}, 0)");
	}
	echo "</td></tr>";

}

function nameSuffix($name) {
	if(strpos($name, '_') === false) return null;
	return substr($name, strpos($name, '_')+1);
}


function fieldOrder($a, $b) {
	global $petFieldOrder;
	$ai = array_search(substr($a, 0 , strpos($a, '_')), $petFieldOrder);
	$bi = array_search(substr($b, 0 , strpos($b, '_')), $petFieldOrder);
	return $ai < $bi ? -1 : ($ai > $bi ? 1 : 0);
}

function petSort($a, $b) {
	$asfx = substr($a, strpos($a, '_')+1);
	$asfx = is_numeric($asfx) ? (int)$asfx : 999;
	$bsfx = substr($b, strpos($b, '_')+1);
	$bsfx = is_numeric($bsfx) ? (int)$bsfx : 99;
	return $asfx < $bsfx ? -1 : ($asfx > $bsfx ? 1 : 
	  ($asfx == 999 ? 0 : fieldOrder($a, $b)));
}

function contactChanges($changes, $suffix) {
	$fields = array();
	foreach($changes as $key => $val)
		if(strpos($key, $suffix))
		  $fields[substr($key, 0, strpos($key, $suffix))] = $val;
	return $fields;
}

function displayPetDescriptionWithPhoto($pets, $petIndex) {
	global $petFields;
	
	// display description in 3 column widths, with photo occupying the third width
	if($petIndex > count($pets)) {
		echo "<tr><td colspan=3>This is a new pet.</td></tr><tr><td colspan=3><hr></td></tr>";
		return;
	}
	$pet = $pets[$petIndex-1];
	foreach($petFields as $key=>$label)  {
		$val = $pet[$key];
		if($key == 'sex') $val = $val == 'm' ? 'Male' : ($val == 'f' ? 'Female' : 'Unspecified');
		else if($key == 'fixed' || $key == 'active') $val = $val ? 'Yes' : 'No';
		else if($key == 'dob') $val = $val ? shortDate(strtotime($val)) : '';
		echo "<tr><td class='storedValue'>$label:</td><td class='storedValue'>$val</td>";
		if($key == 'name' && $pet['photo']) {
			$boxSize = array(200, 200);
			$photo = $pet['photo'];
			if(!file_exists($photo)) $photo = 'art/photo-unavailable.jpg';
			$dims = photoDimsToFitInside($photo, $boxSize);
			echo "<td rowspan=10 class='storedValue'><img src='pet-photo.php?version=display&id={$pet['petid']}' width={$dims[0]} height={$dims[1]}></td>";
		}
		echo "</tr>";
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

function showCancellationTable($source, $uncancel=null) {
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
}

function reqClientLink($source) {
	return <<<HTML
	<a  class='fauxlink' onClick="window.opener.location.href='client-edit.php?id={$source['clientptr']}'">{$source['fname']} {$source['lname']}</a>
HTML;
}
