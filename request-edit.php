<?
/* request-edit.php
*
* Parameters:
* id - id of request to be edited.  officenotes and resolved are the only editable fields.
* updateList - list in window opener to update after save
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "encryption.php"; // for lt_decrypt

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');

if($_REQUEST['id']) {
	if(fetchRow0Col0("SELECT requesttype FROM tblclientrequest WHERE requestid = {$_REQUEST['id']} LIMIT 1")
			== 'BizSetup') {
		globalRedirect("request-edit-setup.php?id={$_REQUEST['id']}");
		exit;
	}
}


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

if($scheduleChangeDetail) {  // AJAX 
	require_once "request-safety.php";
	echo scheduleChangeDetail($scheduleChangeDetail, $visitid);
	exit;
}

function findMatchingClients($lname, $email) {
	$findClientsWithLastName = leashtime_real_escape_string($lname);
	$emailCheck = leashtime_real_escape_string($email);
	return fetchAssociations(
		"SELECT clientid, active, street1, city, CONCAT_WS(' ', fname, lname) as name, email
			FROM tblclient 
			WHERE lname = '$findClientsWithLastName' OR email = '$emailCheck'
			ORDER BY lname, fname");
}

if($findClientsWithLastName) {  // AJAX
	$findClientsWithLastName = leashtime_real_escape_string($findClientsWithLastName);
	$emailCheck = leashtime_real_escape_string($emailCheck);
	$matches = findMatchingClients($findClientsWithLastName, $emailCheck);
	echo json_encode($matches);
	exit;
}

if($findClientFor) {  // AJAX
	echo fetchRow0Col0("SELECT clientptr FROM tblclientrequest WHERE requestid = $findClientFor LIMIT 1");
	exit;
}

if($reassign) {  // AJAX - reassign request to admin $adm
	if($reassign == -1) $reassign = 0;
	if($adm == -1 && deleteTable('tblclientrequestprop', "requestptr=$reassign AND property='owner'", 1)) {
		logChange($reassign, 'requestassignment', 'm', 'unassigned');
		echo $reassign;
	}
	else if(replaceTable('tblclientrequestprop', array('requestptr'=>$reassign, 'property'=>'owner', 'value'=>$adm), 1)) {
		logChange($reassign, 'requestassignment', 'm', $adm);
		echo $reassign;
	}
	else {
		logError($_SESSION['auth_user_id'].'|'.mysql_error().'|'.$sql);
		echo "ERROR";
	}
	exit;
}

if($assignmenthistory) {  // AJAX - echo a reassignment history table
	$rows = requestAssignmentHistory($assignmenthistory);
	if(!$rows) echo 'Never assigned.';
	else tableFrom(array('time'=>'Time','assignedto'=>'Assigned To', 'assignedby'=>'Assigned By'), $rows,
									$attributes=null, $class=null, $headerClass='sortableListHeader',
									$headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null,
									$rowClasses=null, $colClasses=null, $sortClickAction=null);
	exit;
}

if($_POST) {
	$oldRequest = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $id LIMIT 1");
	if(!$oldRequest['resolved'] && $_REQUEST['resolved'] && $_REQUEST['notifyuser']) {

		$openNotifierComposer = "openNotifierComposer($id)";
		$openNotifierComposerDefinition = "
		function openNotifierComposer(requestid) {
			window.resizeTo(700, 500);
			document.location.href='request-notification-composer.php?pop=1&clientrequest='+requestid;
		}
		";
		/* close() dropped for iPad/iPhone compatibility
		$openNotifierComposer = "openNotifierComposer($id);window.close();";
		$openNotifierComposerDefinition = "
		function openNotifierComposer(requestid) {
			openConsoleWindow('noticewindow', 'request-notification-composer.php?pop=1&clientrequest='+requestid,700,500);
		}
		";*/

	}
//print_r($oldRequest);echo "<p>>>$openNotifierComposer";print_r($_REQUEST);		exit;
	if($billingreminder) {
		if($operation == 'saveAndRedirect' ) {
			$_REQUEST['officenotes'] = $_REQUEST['officenotes'] ? "{$_REQUEST['officenotes']}\n" : '';
			$now = shortDateAndTime();
			if(strpos($redirecturl, 'payment-edit') !== false)
				$_REQUEST['officenotes'] .= "Last charged from here: $now";
			else if(strpos($redirecturl, 'prepayment-invoice-view') !== false
							|| strpos($redirecturl, 'billing-statement-view') !== false
							|| strpos($redirecturl, 'billing-invoice-view') !== false)
				$_REQUEST['officenotes'] .= "Statement last sent from here: $now";
			else if(strpos($redirecturl, 'notify-schedule.php') !== false)
				$_REQUEST['officenotes'] .= "Confirmation last sent from here: $now";
			updateClientRequest($_REQUEST);
			globalRedirect($redirecturl);
			exit;
		}
		else updateClientRequest($_REQUEST);
	}
	else if($operation == 'sendVisitReport') {
		require_once "appointment-client-notification-fns.php";
		if($error = approveVisitReport($_POST))
			$operation = 'noclose';
		logChange($id, 'tblclientrequest', 'm', $_SESSION['auth_user_id'].'|Visit report sent.') ;			
	}
	else if($operation == 'sendVisitReportFeedback') {
		require_once "appointment-client-notification-fns.php";
		print_r(sendVisitReportFeedback($_POST));
		exit;
	}
	else if($operation == 'applyProfileChanges') {
		//print_r($_POST);exit;
		$profileChangeRequests = getProfileChangeRequestsForRequestID($id, $clientptr);
		//print_r($profileChangeRequests);exit;
		$checkboxkeys = 'XXemergencycarepermission,active,fixed,haskey,dropphoto';
		//if(array_key_exists('sms_primaryphone_workphone', $_POST)) $textButtonsOffered = true;
		$textButtonsOffered = true;

		// apply client changes
		$clientFieldNames = explode(',', 'fname,lname,fname2,lname2,email,email2,primaryphone,cellphone,cellphone2,homephone,workphone,fax,pager,'.
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
				else if(strpos($key, 'phone')) { // if TEXT widget not offered in request editor, preserve "T"
					$oldclient = $oldclient ? $oldclient : fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientptr");
					if(!$textButtonsOffered && textMessageEnabled($oldclient[$key])) $val = "T$val";
					else if($textButtonsOffered && $_POST["sms_primaryphone_$key"]) $val = "T$val";
//if(mattOnlyTEST()) {print_r($profileChangeRequests); echo "<p>"; print_r($_POST);echo "<p>$key: $val";echo "<p>textButtonsOffered: $textButtonsOffered";exit;}
					if($primaryphone == $key) $val = "*$val";
//if(mattOnlyTEST() && ($key == 'cellphone')) {echo print_r($_POST,1)."<hr>sms_primaryphone_$key [{$_POST["sms_primaryphone_$key"]}]".print_r($val,1);exit;}
//if(mattOnlyTEST() && ($key == 'workphone')) {print_r($_POST);exit;}
				}
				$changes[$key] = $val;
			}
//if(mattOnlyTEST()) {echo "$clientptr [$primaryphone] ".print_r($changes, 1);				exit;}
			if($changes) updateTable('tblclient', $changes, "clientid = $clientptr", 1);
//echo "[[[[clientptr: ".print_r($changes, 1)."]]]]<p>$clientptr";exit;
		}

		// apply pet changes
		$proceedWithSection = false;
		foreach($profileChangeRequests as $key => $unused)
			if(is_numeric($index = nameSuffix($key))) $proceedWithSection = true;

		$petIdsByIndex = array();
		if($proceedWithSection) {
			$petPostFields = array();
			foreach($profileChangeRequests as $key => $valFromClient) {
				$index = nameSuffix($key);
				if(strpos($key, 'petid_') === 0) {
					$petIdsByIndex[$index] = $valFromClient;
					continue;
				}
				if(is_numeric($index)) {
					$field = fieldNameBase($key);
					if($field == 'photo') {
						$val = $id;  // stash the request id in photo
					}
					else if(strpos($checkboxkeys, $field)) $val = $valFromClient ? 1 : 0;
					else $val = $_POST[$key];
					$petPostFields[$index-1][$field] = $val;
				}
			}
			$existingPets = getClientPets($clientptr);
			$errors = array();
			foreach($petPostFields as $index => $fields) {
				//$petId = $index >= count($existingPets) ? null : $existingPets[$index]['petid'];
				$petId = $index >= count($existingPets) ? null : $petIdsByIndex[$index+1]; //$existingPets[$index]['petid'];

				if(!$petId && !trim($fields["name"]))
					$petPostFields[$index]["name"] = trim($_POST["name_".($index+1)]) ? trim($_POST["name_".($index+1)]) : "NO NAME SUPPLIED";
				if($result = saveClientPetChanges($index, $petPostFields[$index], $clientptr, $petId))
					$errors[] = $result;
			}
			if($errors)
				$error = count($errors) == 1
					? $errors[0]
					: '<ul><li>'.join('<li>', $errors).'</ul>';
		}
//if(mattOnlyTEST()) {echo "BANG!<p>".print_r($errors,1); exit;}

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
//if(mattOnlyTEST()) echo "Existing contacts: ".print_r($existingContacts,1)."<p>Contact ID: $contactId<p>";
//if(mattOnlyTEST()) logChange(-999, 'DEBUG', 'm', 'FIELDS: '.print_r($fields, 1));
if(mattOnlyTEST()) logChange(-999, 'DEBUG', 'm', 'profileChangeRequests: '.print_r($profileChangeRequests, 1));
				if($fields['contactid'] != $contactId)
					unset($fields['contactid']);
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
		if($proceedWithSection) saveClientCustomFields($clientptr, $pairs, 'pairsonly');

		$change = leashtime_real_escape_string(shortDateAndTime()." Honored by {$_SESSION['auth_username']} ({$_SESSION['auth_user_id']})");
		updateTable('tblclientrequest',
			array('resolved'=>1, 'resolution'=>'honored', 'officenotes'=>sqlVal("CONCAT_WS('\\n','$change', officenotes)")),
			"requestid = $id", 1);
		logChange($id, 'tblclientrequest', 'm', $_SESSION['auth_user_id'].'|Request honored and resolved.') ;
	}
	else if($operation == 'delete' && $oldRequest['requesttype'] == 'Spam') {
		deleteTable('tblclientrequest', "requestid = {$oldRequest['requestid']}", 1);
	}
	else if($operation == 'notspam' && $oldRequest['requesttype'] == 'Spam') {
		$change = leashtime_real_escape_string(shortDateAndTime()." Marked as a legitimate Prospect by {$_SESSION['auth_username']} ({$_SESSION['auth_user_id']})");
		updateTable('tblclientrequest',
			array('resolved'=>0, 
						'resolution'=>'',
						'requesttype'=>'Prospect',
						'officenotes'=>sqlVal("CONCAT_WS('\\n','$change', officenotes)")),
			"requestid = $id", 1);
		logChange($id, 'tblclientrequest', 'm', $_SESSION['auth_user_id'].'|Request marked as legitimate.') ;
	}
	else if($operation == 'markspam' && $oldRequest['requesttype'] == 'Prospect') {
		$change = leashtime_real_escape_string(shortDateAndTime()." Marked as SPAM by {$_SESSION['auth_username']} ({$_SESSION['auth_user_id']})");
		updateTable('tblclientrequest',
			array('resolved'=>1, 
						'resolution'=>'markedspam',
						'requesttype'=>'Spam',
						'officenotes'=>sqlVal("CONCAT_WS('\\n','$change', officenotes)")),
			"requestid = $id", 1);
		logChange($id, 'tblclientrequest', 'm', $_SESSION['auth_user_id'].'|Request marked as spam.') ;
	}
	else {
		updateClientRequest($_REQUEST);
	}
	$updateList = $updateList && $updateList != 'null' ? "'$updateList'" : 'null';
	echo "<script language='javascript' src='common.js'></script>\n<script language='javascript'>
	if(window.opener.update) window.opener.update($updateList, null);\n";
	if($openNotifierComposer) echo "$openNotifierComposerDefinition$openNotifierComposer;\n";
	else if($operation != 'noclose' && !$errors) echo "window.close()";
	echo "</script>";
}

$petFieldOrder = array('name','type','sex','breed','color','fixed','dob','description','active','notes');

// #############################################################################
$windowTitle = "Edit Client Request";
$customStyles = ".sectionHead {font-size:1.1em;background:lightblue;border:solid black 1px;font-weight:bold;margin:15px;}";

require "frame-bannerless.php";
echo "<style>.notelabel {width:80px;}</style>";
if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
$source = getClientRequest($id);
$source['date'] = longestDayAndDateAndTime(strtotime($source['received']));
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<?

if($source['requesttype'] == 'SystemNotification') displayNotification($source, $updateList);
else if($source['requesttype'] == 'ValuePackRefills') {
	require_once "value-pack-fns.php";
	displayValuePackRefillsRequest($source, $updateList);
}
else if($source['requesttype'] == 'Reminder') displayReminder($source, $updateList);
else if($source['requesttype'] == 'BillingReminder') {
	startForm($source, $updateList, 'Billable Schedule:');
	require_once "billing-reminder-fns.php";
	billableNRScheduleRequestEditor($source, $updateList);
	$noCommOrViewBox =  1;
}
else if($source['requesttype'] == 'UnassignedVisitOffer') {
	$noCommOrViewBox = true;
	displayUnassignedVisitOffer($source, $updateList);
}
else if($source['requesttype'] == 'VisitReport') {
	startForm($source, $updateList, 'Visit Report');
	require_once "appointment-client-notification-fns.php";
if(mattOnlyTEST()) {
	//print_r($source);
		$extraFields = getExtraFields($source);
		$appointmentID = $extraFields['x-appointmentptr'];
		fauxLink('Edit Appointment (Matt Only)', "document.location.href=\"appointment-edit.php?id=$appointmentID\"");
}

	displayVisitReportRequest($source, $updateList);
	$noCommOrViewBox = true;
}
else {
	$noCommOrViewBox = in_array($source['requesttype'], array('TimeOff','ICInvoice'));
	displayRequestEditor($source, $updateList);
}

echo "<p align='center'>";

if(staffOnlyTEST() || dbTEST('tlcpetsitter')) {
	if($source['requesttype'] == 'ICInvoice'
		&& ($hiddenFields = getHiddenExtraFields($source))
		&& ($provid = $hiddenFields['providerptr'])) {
			$url = "comm-composer.php?provider=$provid&clientrequest=$id";
			echoButton('', "Email", "document.location.href=\"$url\"");
			echo " ";
	}
}

if($source['requesttype'] == 'UnassignedVisitOffer') {
		echoButton('', "Email Sitter", "openProviderComposer({$source['providerptr']}, \"re: your Unassigned Visit Offer\")");
		echo "<img src='art/spacer.gif' WIDTH=20>";
}

$saveAsIsLabel = $newVersion ? "Save Notes" : "Save As-Is";
if(!$newVersion || $source['requesttype'] != 'SystemNotification') echoButton('', $saveAsIsLabel, "checkAndSubmit()");
echo " ";

if(!$newVersion) {
	if(!$source['resolved']) {
		if($specialResolutionButton) {
			echo " $specialResolutionButton";
		}
		echoButton('', "Resolve and Save", "checkAndSubmit(\"resolve\")");
		if(!in_array($source['requesttype'], array('SystemNotification', 'Reminder', 'BillingReminder', 'VisitReport', 'UnassignedVisitOffer'))) {
			echo " ";
			labeledCheckbox('Notify User', 'notifyuser', $_SESSION['preferences']['requestResolutionEmail']);
			echo " ";
		}
	}
	else
		echoButton('', "Mark Unresolved and Save", "checkAndSubmit(\"unresolve\")");
	echo " ";
	echoButton('', "Quit", 'window.close()');

	if(userIsACoordinator()) {
		echo "<p>";
		requestOwnerPullDownMenu($source);
	}


}
else {
	hiddenElement('notifyuser', '');
}
if($source['requesttype'] == 'Spam') {
	echo " ";
	echoButton('', "Delete", "checkAndSubmit(\"delete\")", 'HotButton', 'HotButtonDown');
}
if((staffOnlyTEST() || dbTEST('queeniespets')) && $source['requesttype'] == 'Prospect') {
	echo " ";
	echoButton('', "Mark as SPAM", "checkAndSubmit(\"markspam\")", 'HotButton', 'HotButtonDown');
}
if((staffOnlyTEST() || dbTEST('queeniespets')) && $source['requesttype'] == 'Spam') {
	echo " ";
	echoButton('', "This is NOT SPAM", "checkAndSubmit(\"notspam\")", 'BlueButton', 'BlueButtonDown');
}
echo "</p>";
echo "</form>";

if(!$noCommOrViewBox) {
	echo "<table width='95%'>\n<tr>\n";
	if($source['requesttype'] != 'SystemNotification') {
		echo "\n<td style='border: solid black 1px;padding:7px;vertical-align:top;text-align:right;'>Communicate: ";
		echoButton('', "Email",
			"openComposer(\"{$source['clientptr']}\", \"".safeValue($source['lname'])."\", "
			."\"".safeValue($source['fname'])."\", \"".safeValue($source['email'])."\")");
		echo "\n";
		echoButton('', "Log Call", "openLogger(\"{$source['clientptr']}\", \"phone\")");
		if(tableExists('tblreminder') || $source['requesttype'] == 'Schedule') echo "<br>\n";
		if($source['requesttype'] == 'Schedule') {
			echoButton('', "Send to Sitter", "openConsoleWindow(\"sendtositter\", \"comm-schedule-request-to-sitter-composer.php?requestid={$source['requestid']}\",700,500)");
		}
		if(TRUE // enabled 3/6/2019
				//$_SESSION['preferences']['enableSendRequestToSitters'] || dbTEST('tlcpetsitter,db4pawspetsitting,scvgotpaws')
				// REMOVE dbTEST('tlcpetsitter,db4pawspetsitting,scvgotpaws') after Feb 1
				&& ($source['requesttype'] == 'General' 
							|| $source['requesttype'] == 'Prospect'
							|| ($source['requesttype'] == 'Profile' 
							&& (/*staffOnlyTEST() ||*/ TRUE || $_SESSION['preferences']['enableSendToSittersInProfileRequests']))
							)) {
			echoButton('', "Send to Sitter", "sendToSitter()");
			// echoButton('', "Send to Sitter", "openConsoleWindow(\"sendtositter\", \"comm-general-request-to-sitter-composer.php?requestid={$source['requestid']}\",700,500)");
		}
		if(tableExists('tblreminder')) {
			echoButton('', "Set a Reminder", "openConsoleWindow(\"remindereditor\", \"reminder-edit.php?pop=1&client={$source['clientptr']}\",700,500)");
		}
		if(/*mattOnlyTEST() || */dbTest('dogslife,k9krewe')) {
			echoButton('', "Add to Address Book", "document.location.href=\"request-vcard.php?requestid={$source['requestid']}\"");
			//echoButton('', "Add to Address Book", "ajaxGet(\"request-vcard.php?requestid={$source['requestid']}\", \"nada\");");
			//echo "<span style='display:hidden;' id='nada'></span>";
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

if($extraFields = getExtraFields($source)) {
	if($extraFields['form_referer']) {
		if(mattOnlyTEST() || dbTEST('doggiewalkerdotcom,doggywalkerarlington'))
			echo "<p>Referrer: {$extraFields['form_referer']}";
		if(mattOnlyTEST())
			echo "<p>User Agent: {$extraFields['form_user_agent']}<p>Resolution: [{$source['resolution']}]";
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

<?
	if($source['requesttype'] == 'schedulechange') {
		require_once "request-safety.php";
		dumpScheduleChangeJS();
	}
?>

function findDupLastName(callback) {
	if(callback) {
		var list = JSON.parse(callback);
		var inactive = 0;
		var out = '';
		if(list.length == 0) out = 'No duplicate last names found.';
		else {
			out = 'Clients with similar names:<p>';
			out += '<table border=1 bordercolor="gray">';
			for(var i=0; i<list.length; i++) {
				var client = list[i];
				var color = client.active == "1" ? 'black' : 'red';
				if(client.active != "1") inactive += 1;
				var link = '<a class="fauxlink" onclick="showClientInMainWindow('+client.clientid+')">'
										+client.name+'</a>'
				out += '<tr style="color:'+color+'"><td>@'+client.clientid+'</td><td>'+link+'</td><td>'
				+(client.street1 ? client.street1 : '<i>No street address</i>')
				+'</td><td>'+(client.city ? client.city : '<i>No city</i>')+'</td></tr>'
				+'<tr style="color:'+color+'"><td colspan=4 align=right>'+(client.email ? client.email : '<i>No email</i>')+'</td></tr>'
				;
			}
			out += '</table>';
			out += '<p>Click a client to view in main window.  ';
			if(inactive > 0) out += 'Clients in <span style="color:red">red</span> are inactive.';
		}
		$.fn.colorbox({html: out, width:"550", height:"310", scrolling: true, opacity: "0.3"});
	}
	else if(document.getElementById('lastNameCheck')) {
		var lname = document.getElementById('lastNameCheck').value;
		var email = document.getElementById('emailNameCheck').value;
		var url = "?findClientsWithLastName="+lname+"&emailCheck="+email;
		var xh = getxmlHttp();
		xh.open("GET",url,true);
		xh.onreadystatechange=function() { if(xh.readyState==4) { 
			findDupLastName(xh.responseText); 
			}
		}
		xh.send(null);
	}
}

function showClientInMainWindow(id) {
	window.opener.location.href="client-edit.php?id="+id;
}

function viewSchedule(id, typeid) {
	var url = typeid == 1 ? 'service-irregular.php' : ( // EZ
						typeid == 2 ? 'service-oneday.php' : ( // One Day
						typeid == 3 ? 'service-nonrepeating.php' : (// Pro
						typeid == 4 ? 'service-monthly.php' : ( // Monthly
						typeid == 5 ? 'service-repeating.php' : ''))));
	window.opener.location.href=url+"?packageid="+id;
	alert("Look in the main LeashTime window to see the schedule.");
}


function showRequestHistory() {
	// make an ajax call and dump the reults to requesthistorydiv
	var reqid = <?= $id ?>;
	ajaxGet('request-edit.php?id='+reqid+'&assignmenthistory='+reqid, 'requesthistorydiv');
}


function scheduleButtonAction(button, url) {
	button.disabled = true;
	var officenotes = document.getElementById('officenotes').value;
	if(document.getElementById('chosenprovider'))
		url += '&chosenprovider='+document.getElementById('chosenprovider').value
				+'&officenotes='+officenotes;
	window.opener.document.location.href=url;
	window.close();
}

function openNotifierComposer(requestid) {
	window.resizeTo(700, 500);
	document.location.href='request-notification-composer.php?pop=1&clientrequest='+requestid;
}


function update(aspect, text) {
	if(aspect == 'reload') {
		document.location.href='request-edit.php?id=<?= $id ?>';
	}
}

function updateOpenerAndClose(text) {
	if(window.opener.update) {
		window.opener.update('appointments');
		window.opener.update('clientrequests');
	}
	var notifyuserEl = document.getElementById("notifyuser");
	if(notifyuserEl && notifyuserEl.checked)
		openNotifierComposer(document.getElementById("requestid").value);
	else window.close();
}

<? /* OLD -- window closing dropped since this did not work on iPad/iPhone
function openNotifierComposer(requestid) {
	openConsoleWindow('noticewindow', 'request-notification-composer.php?pop=1&clientrequest='+requestid,700,500);
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
*/ ?>

function newClient(id) {
	window.opener.location.href='client-edit.php?requestid='+id;
}

function arrangeMeeting(id, mdate, mtime) {
	if(id == '') {
		ajaxGetAndCallWith('request-edit.php?findClientFor='+document.getElementById('requestid').value,
			tryAgainToArrangeMeeting, mdate+' '+mtime);
	}
	else window.opener.location.href='client-meeting<?= FALSE && staffOnlyTEST() ? "NEW" : '' ?>.php?clientptr='+id+'&startdate='+mdate+'&timeofday='+mtime;
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

function activate(active) {
	var wasactive = document.getElementById('resolved').value == 1 ? false : true;
	document.getElementById('resolved').value = active ? 0 : 1;
	document.getElementById('operation').value = 'noclose';
	if(!active && wasactive)
		document.getElementById('notifyuser').checked = confirm('Notify the user?');
	document.requesteditor.submit();
}

function checkAndSubmit(action) {
	document.getElementById('operation').value = ''; // in case it has been set previously by a Send Now button
	if(action == 'abort') return;
	else if(action == 'markspam') {
		if(!confirm("This Prospect Request will be marked as SPAM and resolved."))
			return;
		document.getElementById('operation').value = 'markspam';
	}
	else if(action == 'notspam') {
		if(!confirm("This Request will be designated a legitimate Prospect and marked unresolved."))
			return;
		document.getElementById('operation').value = 'notspam';
	}
	else if(action == 'resolve') {
		<? if($source['requesttype'] == 'Profile') { ?>
			if(confirm('This will APPLY the changes shown above.  Proceed?')) applyProfileChanges(<?= $source['requestid'] ?>);
			else {
				alert('No changes made.');
				document.requesteditor.submit('abort');
			}
			return;
		<? }
			else if(/*staffOnlyTEST() && */$source['requesttype'] == 'Schedule') { ?>
			
			if(document.getElementById('existingSchedule') && !document.getElementById('existingSchedule').value) {
				if(!confirm('WARNING!\n\nYou have not created a schedule from this request!'
										+'\n\nClick CANCEL and then click Create Schedule\nif you really meant to create a schedule.\n'))
					return;
			}
		
		<?
		}
		?>
		document.getElementById('resolved').value = 1;
	}
	else if(action == 'unresolve') document.getElementById('resolved').value = 0;
	else if(action == 'delete') document.getElementById('operation').value = 'delete';
	document.requesteditor.submit();
}

function applyProfileChanges() {
	var badPetNames = '';
	for(var i = 1; document.getElementById('name_'+i); i++)
		if(document.getElementById('name_'+i).value.indexOf(',') > -1)
			badPetNames = 'Pet names may not include commas';
  if(MM_validateForm(  // should probably make more thorough checks here
		  badPetNames, '', 'MESSAGE')) {
		if(document.getElementById('notifysitterscheckbox') && document.getElementById('notifysitterscheckbox').checked)
			sendToSitter();
		document.getElementById('resolved').value = 1;
		document.requesteditor.operation.value = 'applyProfileChanges';
		document.requesteditor.submit();
	}
}


function declineOrHonorRequest(id, honor, extraArgs) {
	if(typeof extraArgs == 'undefined') extraArgs = '';
	honor = honor ? 1 : 0;
	var xh = getxmlHttp();
	xh.open("GET","request-accept-decline-ajax.php?request="+id+"&honor="+honor+extraArgs,true);
	xh.onreadystatechange=function() { if(xh.readyState==4) {
		//document.getElementById("cancelappts").innerHTML=xh.responseText;
		updateOpenerAndClose(xh.responseText);
	}
	}
  xh.send(null);
}

function sendToSitter() {
	openConsoleWindow("sendtositter", "comm-general-request-to-sitter-composer.php?requestid=<?= $source['requestid'] ?>",700,500);
}


function cancelAppointments(id) { // handles cancel and uncancel
	<? //if(mattOnlyTEST()){ echo "/* source(".print_r($source,1).")*/"; }
		if($source['requesttype'] == 'cancel' && anyVisitsInScopeMarkedComplete($source))
				echo "if(!confirm('One or more of these visits has been marked complete.'+'    '+'Click OK to Cancel Visit(s) anyway.')) return;";
	?>

	var xh = getxmlHttp();
//alert("appointment-request-cancel-ajax.php?request="+id);
	var url = "appointment-request-cancel-ajax.php?request=";
	xh.open("GET",url+id,true);
	xh.onreadystatechange=function() { if(xh.readyState==4) {
		document.getElementById("cancelappts").innerHTML=xh.responseText;
		updateOpenerAndClose();
	}
	}
  xh.send(null);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

function openComposer(clientid, lname, fname, email, subj, template) {
	var args = clientid ? 'client='+clientid : 'prospect='+<?= $id ?>+'&lname='+lname+'&fname='+fname+'&email='+email;
	if(subj != null && subj != '' && typeof subj != 'undefined') 
		args +='&subject='+subj;
	if(template != null && template != '' && typeof template != 'undefined') 
		args +='&template='+template;
//<? if(mattOnlyTEST()) echo "alert(args);"; ?>		
	openConsoleWindow('emailcomposer', 'comm-composer.php?'+args,650,500);
}

function openProviderComposer(providerid, subject) {
	var args = 'provider='+providerid;
	if(subject && typeof subject != 'undefined') args += "&subject="+subject;
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
	if($_SESSION['preferences']['enablerequestassignments']) {
?>
function assignRequest(el) {
	var val = el.options[el.selectedIndex].value;
	if(val == 0) val = -1;
	if(confirm('Assign this request?')) {
		ajaxGetAndCallWith('request-edit.php?id=<?= $id ?>&reassign=<?= $id ?>&adm='+el.options[el.selectedIndex].value,
			assignedTo, null);
	}
	else el.selectedIndex = document.getElementById('lastowner').value;

}

function assignedTo(argument, responseText) {
	if(responseText == 'ERROR') {
		alert('Sorry, no can do.');
		return;
	}
	document.getElementById('lastowner').value = responseText == '' ? '0' : responseText;
	if(window.opener.update) {
		window.opener.update('clientrequests');
	}
	//if(confirm('Close this request?')) window.close();
}

<?
}

if($source['requesttype'] == 'Schedule') {
	$schedule = scheduleFromNote($source['note']);
	$displayOn = $_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';
	dumpClientScheduleDisplayJS($displayOn, count($schedule['services']));
}
if($source['requesttype'] == 'Profile')
	dumpPhoneRowJS();
if($source['requesttype'] == 'VisitReport') {
	// Done above: require_once "appointment-client-notification-fns.php";
	dumpVisitReportRequestJS($source);
}

if(FALSE && mattOnlyTEST()) {
	echo "\n$('.standardInput').attr('placeholder', 'field cleared by client');";
	echo "\n$('.emailInput').attr('placeholder', 'field cleared by client');";
	echo "\n$('.streetInput').attr('placeholder', 'field cleared by client');";
	echo "\n$('textarea').attr('placeholder', 'field cleared by client');";

}
?>

var uvbLookInMainWindowHintOff = "<?= $_SESSION['sessionpreferences']['UVBLookInMainWindowHintOff'] ?>";
function uvbLookInMainWindowHint() {
	if(uvbLookInMainWindowHintOff) return;
<? if(!(TRUE || dbTEST('dogsgonewalking'))) { // mattOnlyTEST()?>
	alert("Look for the highlighted (orange) visit in the main LeashTime window.");
<? } 
	 else {
		 // offer a lightbox instead with a button to discontinue display of this hine in this session
	   	//$gotItURLNugget = urlencode(lt_encrypt('{"UVBLookInMainWindowHintOff":"1", "scope":"session"}'));
	   	$hint = "Look for the highlighted (orange) visit in the main LeashTime window.";
	   	$hint .= "<p><input type='button' id='' name='' value='Got it!' class='Button' onClick='turnOffUVBLookInMainWindowHint()'>";
	   	$hint = "<div class='fontSize1_5em'>$hint</div>";
	   	echo "\t$.fn.colorbox({html:\"$hint\", width:'500', height:'200', iframe: false, scrolling: true, opacity: '0.3'});";
	 } ?>
	 
}

function turnOffUVBLookInMainWindowHint() {
	uvbLookInMainWindowHintOff=1;
	var nugget = '<?=  urlencode(lt_encrypt('{"UVBLookInMainWindowHintOff":"1", "scope":"session"}')); ?>';
	setFlag(nugget);
	$.fn.colorbox.close();
}

function setFlag(nugget) {
	ajaxGetAndCallWith("set-flag.php?nugget="+nugget,setFlagDone, null);
}
function setFlagDone(a, b) {
	<? // if(mattOnlyTEST()) echo "alert(a+' : '+b);"; ?>
}

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

function displayNotification($source, $updateList) {
	startForm($source, $updateList, 'System Notification:');
	echo "\n<table width=100%>\n";
	labelRow('Date:', '', $source['date']);
	$extraFields = getExtraFields($source);
	if($_SESSION["staffuser"]) {
		labelRow('Created by:', '', $extraFields['creator']);
	}
	if($extraFields['submissionid'] || $extraFields['submissionsdigest']) {
		require_once "survey-fns.php";
		if(surveysAreEnabled() && adequateRights('#rs')) {
			if($extraFields['submissionid']) {
				$url = "reports-survey-submissions.php?detail={$extraFields['submissionid']}";
				//$url = globalURL("reports-survey-submissions.php?detail={$extraFields['submissionid']}");
				$action = "$.fn.colorbox({href:\"$url\", width:\"550\", height:\"300\", scrolling: true, iframe: \"true\", opacity: \"0.3\"});";
			}
			else {
				$action = "if(window.opener) {if(confirm(\"Visit Survey Submissions in main window?\")) window.opener.document.location.href=\"reports-survey-submissions.php\";} else alert(\"Main window is no longer open.\");";
				fauxLink('View Survey Submissions in main LeashTime window', $action);
			}
			echo "<tr><td>";
			fauxLink('View Report', $action);
			echo "</td></tr>";
		}
	}
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


function displayUnassignedVisitOffer($source, $updateList) {
	global $specialResolutionButton;
	$hiddenFields = getHiddenExtraFields($source);
	$extraFields = getExtraFields($source);
	startForm($source, $updateList, "Unassigned Visit Offer From: {$extraFields['x-label-Requestor']}");
	echo "\n<table width=100%>\n";
	labelRow('Date:', '', $source['date']);
	//labelRow('Subject:', '', $source['street1']);
	labelRow('&nbsp;', '', '&nbsp;');
	displayUVBFields($source);
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
}

function displayUVBFields($source) {
	require_once "unassigned-visits-board-fns.php";
	cleanUpUVB();
	$extraFields = getAllExtraFieldAssociations($source);
	//echo "<tr colspan=2>".print_r($extraFields, 1);	
	foreach((array)$extraFields as $key => $assoc) {
		if($assoc['type'] == 'hidden')
			$uvb = fetchFirstAssoc("SELECT * FROM tblunassignedboard WHERE uvbid = {$assoc['value']} LIMIT 1");
		else if($key == 'x-label-Requestor') {
			//no-op  //labelRow('Requestor:', '', $assoc['value'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=$style, $rawValue = TRUE);
		}
		else {
			$style = "";
			$title = "";

			$keyParts = explode('-', $key);
			list($ignore, $ftype, $label) = $keyParts;
			if(!$uvb) {
				$style = "color:gray;font-style:italic;";
				$value ="<span title='This item is no longer listed on the Unassigned Visit Board.'>{$assoc['value']}</span>";
			}
			else {
				if(!$uvb['appointmentptr'] && $uvb['packageptr']) {
					require_once "service-fns.php";
					$package = getPackage($uvb['packageptr']);
					if($package)
						$package = getCurrentNRPackage($uvb['packageptr']);
					if(!$package) {
						$style = "color:gray;font-style:italic;";
						$value = "<span title='This schedule no longer exists.'>{$assoc['value']}</span>";
					}
					else $value = fauxLink($assoc['value'], "window.opener.location.href=\"service-irregular.php?packageid={$package['packageid']}\";alert(\"Review and edit the schedule in the main LeashTime window.\");", 1, 'Edit this schedule');
				}
				else if($uvb['appointmentptr']) {
					$details = fetchFirstAssoc(
						"SELECT date, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as luckysitter, packageptr
							FROM tblappointment
							LEFT JOIN tblprovider on providerid = providerptr
							WHERE appointmentid = {$uvb['appointmentptr']} LIMIT 1");
					//$redirectToDate = fetchRow0Col0("SELECT date FROM tblappointment WHERE appointmentid = {$uvb['appointmentptr']} LIMIT 1");
					if(!$details) {
						$style = "color:gray;font-style:italic;";
						$value = "<span title='This visit no longer exists.'>{$assoc['value']}</span>";
					}
					else {
						$visitpackage = getPackage($details['packageptr']);
						if(!$visitpackage['current'])
							$visitpackage = getPackage(findCurrentPackageVersion($visitpackage['packageid'], $visitpackage['clientptr'], !$visitpackage['enddate']));

						if($visitpackage['irregular']) $editor = 1;  // EZ
						else if($visitpackage['enddate'] == '0000-00-00') $editor = 2; // One Day
						else if($visitpackage['enddate']) $editor = 3; // Pro
						else if($visitpackage['monthly']) $editor = 4; // Monthly
						else $editor = 5;
						$visitpackage = "<img onclick=\"viewSchedule({$visitpackage['packageid']}, $editor)\" src=\"art/popcalendar.gif\"style='width:18px;height:11px;cursor:pointer;' title=\"View this visit's schedule\".>";
						
						$warningChar = '&#9888;';
						$sitter = $details['luckysitter'] ? safeValue($details['luckysitter']) : '';
						$value = $sitter ? "<span style='color:black;background:gold;font-size:1.1em;' title=\"Visit has been assigned to $sitter\">$warningChar</span> " : '';
						$value .= fauxLink($assoc['value'], "window.opener.location.href=\"client-edit.php?id={$uvb['clientptr']}&tab=services&redirectToDate=$redirectToDate&highlightAppt={$uvb['appointmentptr']}\";uvbLookInMainWindowHint();", 1, 'Edit this visit.');
						$value .= " $visitpackage";
						//$value .= print_r($uvb, 1);
					}
				}
				else {
					$value = shortDate(strtotime($uvb['uvbdate'])).": ".($uvb['uvbnote'] ? $uvb['uvbnote'] : '<i>No description.</i>');
				}
			}
			if(in_array($ftype, array('label')))
				labelRow($label.':', '', $value, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=$style, $rawValue = TRUE);
		}
	  }
}



function displayRequestEditor($source, $updateList) {
	global $apptFields, $knownSourceFields, $id;


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
	if($source['requesttype'] == 'ICInvoice') $title .= " #$id";
	if($source['requesttype'] =='TimeOff') {
		$extraFields = getExtraFields($source);
		if(array_key_exists('x-label-Deleted Time Off', $extraFields)) {
			$title = "<font color='red'>Canceled </font>".$title;
			$timeOffCanceled = true;
		}
		$hiddenFields = getHiddenExtraFields($source);
		if($hiddenFields['added_pattern']) $addedTimeOffPattern = $hiddenFields['added_pattern'];
		else if($hiddenFields['updated_instance']) $updatedTimeOffInstance = $hiddenFields['updated_instance'];
		$timeOffProviderPtr = $hiddenFields['providerid'];
	}
	startForm($source, $updateList, $title);
	hiddenElement('clientptr', $source['clientptr']);
	if($source['providerptr']) {
		require_once "provider-fns.php";
		$pname = getProvider($source['providerptr']);
		$pname = providerShortName($pname)." ({$pname['fname']} {$pname['lname']})";
		echo "<h3>Submitted by: $pname</h3>";
	}
	echo "\n<hr>\n";
	echo "\n<table width=100%>\n";
	if(in_array($source['requesttype'], array('TimeOff','UnassignedVisitOffer'))) {
		labelRow('Received:', '', shortDateAndTime(strtotime($source['received'])));
		$note = str_replace("\n", '<br>', $source['note']);
		echo "<tr><td>Note:</td><td style='border:solid black 1px;'>$note</td></tr>";
	}
	else if(in_array($source['requesttype'], array('ICInvoice'))) {
		labelRow('Received:', '', shortDateAndTime(strtotime($source['received'])));
		$extraFields = getExtraFields($source);
		$startDate = $extraFields['x-label-Starting'] ? date('Y-m-d', strtotime($extraFields['x-label-Starting'])) : '';
		$throughDate = date('Y-m-d', strtotime($extraFields['x-label-Ending']));
		$payrollButton =
			echoButton('', 'Go to Sitter Payment',
				"if(window.opener) window.opener.location.href=\"provider-payment.php?startDate=$startDate&throughDate=$throughDate\"",
				'', '',	'noecho', 'Review Payroll');
//if(mattOnlyTEST()) {echo $source['note']; exit;		}
		echo "<tr><td>Note:</td><td style='border:solid black 1px;'>$payrollButton<p>{$source['note']}</td></tr>";
	}
	else {
		echo "<tr><td valign=top><table width=100%>";
		if($client) {
			if($_SESSION['staffuser']  || $_SESSION['preferences']['showClientFlagsInRequests']  || dbTEST('menageriepetsitting')) {
				require_once "client-flag-fns.php";
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<tr><td colspan=2>".print_r($source, 1); }

				$flagPanel = clientFlagPanel($source['clientptr'], $officeOnly=false, $noEdit=true, $contentsOnly=true);
				echo "<tr><td colspan=2>$flagPanel</td></tr>";
			}

			labelRow('Client:', '', reqClientLink($source), null, null, null, null, 'raw');
		}
		else {
			labelRow('First Name:', '', $source['fname']);
if(TRUE // published 4/22/2019 -- ($_SESSION['preferences']['enableProspectDuplicateClientDetection']) 
		&& findMatchingClients($source['lname'], $source['email'])) {
		//&& fetchRow0Col0("SELECT clientid FROM tblclient WHERE lname = '".leashtime_real_escape_string($source['lname'])."'")) {
$dupsCheck = ' '.fauxLink('Duplicate?', 'findDupLastName(null)', 1, 'Look up this last name');
hiddenElement('lastNameCheck', $source['lname']);
hiddenElement('emailNameCheck', $source['email']);
}
			labelRow('Last Name:', '', $source['lname'].$dupsCheck);
		}
		labelRow('Phone:', '', $source['phone']);
		labelRow('When to call:', '', $source['whentocall']);
		labelRow('Email:', '', $source['email']);
		echo "\n</table></td>\n";
		echo "<td valign=top><table width=100%>";
		labelRow('Date:', '', $source['date']);
		if(TRUE || staffOnlyTEST() || dbTEST('tlcpetsitter,db4pawspetsitting')) 
			$mapWindowOpener = fauxLink('Nearby Sitters', "openConsoleWindow(\"requestmap\", \"client-provider-map.php?pop=1&requestid=$id\",700,700);", 1, "Open map of nearby sitters.");
//labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false) 
		labelRow('Address:', '', $mapWindowOpener);
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

		echo "<tr><td colspan=2><table width=90%>";
		$note = $source['note'];
		if($source['requesttype'] == 'Schedule') {
			$schedule = scheduleFromNote($note);
//if(mattOnlyTEST()) echo "JSON: $note<hr>is JSON: [".scheduleRequestPayloadIsJSON($note)."]<hr>".print_r($schedule, 1);			
			$note = explode("\n", $source['note']);  // $schedule['note'];, if we ever add it
			$note = urldecode($note[2]);
		}
		$noteLabel = $source['requesttype'] == 'change' ? 'Requested changes:' : 'Note:';
		echo "<tr><td class='notelabel'>$noteLabel</td><td style='border:solid black 1px;'>".str_replace("\n", '<br>', $note)."</td></tr>";
	}
	displayExtraFields($source);

	echo "</table></td></tr>"; // WHY?!!!!!




	echo "<tr><td colspan=2>&nbsp;</td></tr>";
	$hiddenFields = getHiddenExtraFields($source);
	if($hiddenFields['visitdetails']) {
		$html = str_replace("\n", "", str_replace('"', "&quot;", str_replace("'", "&apos;", $hiddenFields['visitdetails'])));
		$html = "<center><u>Original Visit Details</u></center>".$html;
		$showVisitDetails = "$.fn.colorbox({html:\"$html\", width:\"550\", height:\"300\", scrolling: true, opacity: \"0.3\"});";
		$visitDetailsInclusion =
			"<tr><td>"
			.fauxLink('Show original visit details', $showVisitDetails, $noEcho=true, 'Click here if the visits have disappeared.')
			."</td></tr>";
	}
	if($source['requesttype'] == 'cancel' || $source['requesttype'] == 'uncancel') {
		showCancellationTable($source, $source['requesttype'] == 'uncancel');
		if($visitDetailsInclusion) echo $visitDetailsInclusion;
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
	}
	else if($source['requesttype'] == 'change') {
		showChangeTable($source);
		if($visitDetailsInclusion) echo $visitDetailsInclusion;
	}
	else if($source['requesttype'] == 'Profile') {
		showProfileChangeEditorTable($source);
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
	}
	else if($source['requesttype'] == 'Schedule') {
		$schedule['clientptr'] = $source['clientptr'];
	// $offerGenerateButton should probably be true when request has been declined or enddate and/or startdate are past
		$offerGenerateButton=adequateRights('#ev');
		if(FALSE && staffOnlyTEST() && mattOnlyTEST() && dbTEST('dogslife')) {
			showScheduleTableWizardVersion($schedule, $offerGenerateButton, $source);
		}
		else {
			$existingSchedule = !$offerGenerateButton
				? false
				: fetchFirstAssoc(
						"SELECT *
							FROM tblservicepackage
							WHERE clientptr = {$source['clientptr']}
							AND irregular = 1
							AND current = 1
							AND startdate = '".date('Y-m-d', strtotime($schedule['start']))."'
							AND enddate = '".date('Y-m-d', strtotime($schedule['end']))."'");
			showScheduleTable($schedule, $offerGenerateButton, $existingSchedule, $source);
		}
		hiddenElement('existingSchedule', ($existingSchedule? $existingSchedule['packageid'] : null));
		echo "<tr><td colspan=2>&nbsp;</td></tr>";
	}
	else if($addedTimeOffPattern || $updatedTimeOffInstance) {
		if(($addedTimeOffPattern 
				&& fetchRow0Col0("SELECT patternid FROM tbltimeoffpattern WHERE patternid = $addedTimeOffPattern LIMIT 1", 1))
			|| ($updatedTimeOffInstance 
				&& fetchRow0Col0("SELECT timeoffid FROM tbltimeoffinstance WHERE timeoffid = $updatedTimeOffInstance LIMIT 1", 1))
			) {
			$parameter = $addedTimeOffPattern ? "pattern=$addedTimeOffPattern" : "instance=$updatedTimeOffInstance";
			$url = "timeoff-killer.php?$parameter";
			$killerLink = 
				fauxLink('Delete This Time Off...',
									"if(confirm(\"You are about to delete this time off.\"))
										ajaxGetAndCallWith(\"$url\",openProviderComposer, $timeOffProviderPtr);", 'noEcho', '');
		}
		else {
			$itemtable = $addedTimeOffPattern ? 'tbltimeoffpattern' : 'tbltimeoffinstance';
			$itemptr = $addedTimeOffPattern ? $addedTimeOffPattern : $updatedTimeOffInstance;
			$deletion = fetchFirstAssoc("SELECT * FROM tblchangelog WHERE operation = 'd' AND itemtable = '$itemtable' AND itemptr = '$itemptr' LIMIT 1", 1);
			if($deletion) {
				$datetime = ' '.shortDateAndTime(strtotime($deletion['time']));
				$killer = getManagers(array($deletion['user']));
			}
			if($killer) $killer = " by ".$killer[$deletion['user']]['name'];
			$killerLink = "The time off was deleted $datetime $killer";
		}
		echo "\n\n<p>$killerLink<p>\n\n";
	}
	else if($source['requesttype'] == 'schedulechange') {
		require_once "request-safety.php";
		showScheduleChangeTable($source, $noButtons=false);
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

function XdisplayExtraFields($source) {  // moved to request-fns.php
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
			else if(in_array($ftype, array('select', 'oneline', 'radio', 'label')))
				labelRow($label.':', '', $value, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, ($rawValue = TRUE || $ftype == 'label'));
			else if($ftype == 'checkbox')
				labelRow($label.':', '', ($value ? 'yes' : 'no'));
			else if($ftype == 'multiline')
				echo "<tr><td valign=top>$label:</td><td>".str_replace("\n", '<br>', $value)."</td></tr>";
		}
	}
}

function showProfileChangeEditorTable($source) {
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
//if(mattOnlyTEST()) print_r($changes);
	foreach($changes as $key => $val)
		if(isset($fields[$key]) || isset($fields["mail$key"])) $sectionChange = $key;
//if(mattOnlyTEST()) print_r($sectionChange);
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Client Profile Changes</td><tr>";
		foreach($changes as $key => $val) {
			$label = $fields[$key];
			if(!array_key_exists($key, $fields)) continue;
			$cval = isset($client[$key]) ? $client[$key] : '';
			if($key == 'email') deltaInputRow($label.':', $key, $val, $cval, null, 'emailInput', $dbTable='tblclient');
			else if($key == 'notes') deltaTextRow($label.':', $key, $val, $cval, 3, 60);
			else if(strpos($key, 'phone')) deltaPhoneRow($label.':', $key, $val, $cval);


			else if(mattOnlyTEST() && $key == 'vetname' || $key == 'clinicname') {
				if($key == 'vetname' && $client['vetptr'] && ($vetName = fetchRow0Col0("SELECT CONCAT_WS(' ',fname, lname) FROM tblvet WHERE vetid = {$client['vetptr']}")))
					$cval = $vetName;
				else if($key == 'clinicname' && $client['clinicptr'] && ($clinicName = fetchRow0Col0("SELECT clinicname FROM tblclinic WHERE clinicid = {$client['clinicptr']}")))
					$cval = $clinicName;
				deltaInputRow($label.':', $key, $val, $cval, $labelClass=null, $inputClass='standardInput');
			}
			else deltaInputRow($label.':', $key, $val, $cval, $labelClass=null, $inputClass='standardInput', $dbTable='tblclient');

			if($key == 'vetname' || $key == 'clinicname') {
				$editLink = 'edit the client&apos;s profile manually';
				$tooltip = 'Click here to edit the client&apos;s profile in the main LeashTime window.';
				if(TRUE || mattOnlyTEST()) $editLink = fauxLink($editLink, 'opener.location.href="client-edit.php?tab=basic&id='.$client['clientid'].'"', 1, $tooltip);
			  echo "<tr><td colspan=3 class='tiplooks'>Please note that you must $editLink to change the above setting.</td></tr>";
			}
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
	$customPetFields = getCustomFields('active', !'visitsheetonly', getPetCustomFieldNames(), !'clientvisibleonly');

	$pets = getClientPets($client['clientid']);
	$newPets = array();
	$petsById = array();
	foreach($pets as $p) $petsById[$p['petid']] =  $p;
	$petIdsByIndex = array();
	foreach($changes as $key => $val) {
		if(strpos($key, 'petid_') === 0) {
			if($val !== NULL) {
				$petIdsByIndex[nameSuffix($key)] = $val;
			}
		}
	}


//if(mattOnlyTEST()) print_r($petIdsByIndex);


	foreach($changes as $key => $val) {
		if(strpos($key, 'petid_') === 0) continue;
		$petIndex = nameSuffix($key);
		if(strpos($key, '_')
				&& (strpos($key, '_petcustom') || strpos($prefixes, substr($key, 0, strpos($key, '_')+1)))
				&& is_numeric(nameSuffix($key)))
			$sectionChange = $key;
	}

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<tr><td colspan=2>CHANGES: ".print_r($changes, 1). "[{$sectionChange}]"; }
	if($sectionChange || isset($changes['emergencycarepermission'])) {
		uksort($changes, 'petSort');
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr class='sectionHead'><td colspan=3>Pets</td><tr>";
		if(isset($changes['emergencycarepermission']))
			deltaCheckboxRow('Emergency Care Permission:', 'emergencycarepermission',
												 $changes['emergencycarepermission'],$changes['emergencycarepermission']);

		$lastPetIndex = 0;

		$allPetChanges = array();
		foreach($changes as $key => $val) {
			if(strpos($key, 'petid_') === 0) continue;
			$petIndex = nameSuffix($key);
			if(!is_numeric($petIndex)) continue;
			$thisPetId = $petIdsByIndex[$petIndex];
			$field = fieldNameBase($key);  //substr($key, 0, strpos($key, '_'));
			$allPetChanges[$thisPetId][$field] = $val;
		}

		foreach($changes as $key => $val) {
			if(strpos($key, 'petid_') === 0) continue;
			$petIndex = nameSuffix($key);
			if(!is_numeric($petIndex)) continue;
			$thisPetId = $petIdsByIndex[$petIndex];
			$field = fieldNameBase($key);  //substr($key, 0, strpos($key, '_'));
			$changedFieldNames[] = $field;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($changedFieldNames); }
		//	show old values for previous section, if any, and start new pet section if index is not same as last
			$isNewPet = !$thisPetId;

			if($isNewPet) {
				if($newPets[$petIndex]) $pet = $newPets[$petIndex];
				else $newPets[$petIndex] = ($pet = array('name'=> 'New Pet'));
			}
			else $pet = $petsById[$thisPetId];

			//$pet =  $thisPetId ? $petsById[$thisPetId] : array('name'=> 'New Pet');
//if(mattOnlyTEST()) echo "<tr><td>[$thisPetId] ".print_r($petsById, 1);
			// $pet =!$isNewPet ? $pets[$petIndex-1] : array('name'=> 'New Pet');
//if($pet['name']	== 'New Pet' && mattOnlyTEST()) $pet['name'] = "{$pet['name']} ($petIndex)";
			if($petIndex != $lastPetIndex/*$thisPetId != $lastPetId*/) {
				if($lastPetId) displayPetByPetIdDescriptionWithPhoto($pets, $lastPetId, $changedFieldNames, $heading='Current Pet State', $petChanges=$allPetChanges[$lastPetId]);
				$lastPetIndex = $petIndex;
				$lastPetId = $thisPetId;
				echo "<tr><td colspan=3 style='text-align:center;text-decoration:underline;'>".($pet['name'] ? $pet['name'] : 'Unnamed Pet')."</td></tr>";
				if($isNewPet && !trim((string)$changes["name_$petIndex"])) inputRow('Name:', "name_$petIndex", "NO NAME SUPPLIED");
				//if(mattOnlyTEST()) {echo "<tr><td colspan=2>".print_r($changes, 1);}
			}
			$label = $field == 'dropphoto' ? 'Drop Photo': $petFields[$field];
			$customFieldType = null;
			if(!$label) {
				$label = substr($key, strpos($key, '_')+1);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($customPetFields); }
				$customField = $customPetFields[$label];
				$label = $customField[0];
				$customFieldType = $customField[2];
			}

			if($field == 'sex') radioButtonRow("$label:", $key, $val, array('Male'=>'m', 'Female'=>'f'), '');
			else if($customFieldType == 'boolean' || $field == 'fixed' || $field == 'active' || $field == 'dropphoto') checkboxRow("$label:", $key, $val);
			else if($customFieldType == 'text' || $field == 'notes') {
				textRow($label.':', $key, $val, $rows=1, $cols=60, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=3);
			}
			else if($field == 'photo') {
				$photoName = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/{$source['requestid']}"."_$petIndex.jpg";
				if(!file_exists($photoName)) $photoName = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/{$source['requestid']}"."_$petIndex.jpeg";
				if(!file_exists($photoName)) $photoName = "{$_SESSION['bizfiledirectory']}photos/pets/fromClient/{$source['requestid']}"."_$petIndex.png";
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
		if($thisPetId) displayPetByPetIdDescriptionWithPhoto($pets, $lastPetId, $changedFieldNames, $heading='Current Pet State', $petChanges=$allPetChanges[$lastPetId]);
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
			//if($key == 'directions') deltaTextRow($label.':', $key, $val, $cval, 3, 60);
			if(in_array($key, array('directions','alarminfo'))) deltaTextRow($label.':', $key, $val, $cval, 3, 60);
			else if(strpos($key, 'phone')) deltaPhoneRow($label.':', $key, $val, $cval);
			else deltaInputRow($label.':', $key, $val, $cval, $labelClass=null, $inputClass='standardInput', $dbTable='tblclient');
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
	$customFields = getCustomFields('active', 'visitsheetonly',null, 'clientvisibleonly');
	$sectionChange = array_intersect(array_keys($customFields), array_keys($changes));
	if($sectionChange) {
		echo "<tr><td>&nbsp;</td><tr>";
		echo "<tr><td class='sectionHead' colspan=3>Custom Fields</td><tr>";
		$clientCustomFields = getClientCustomFields($client['clientid']);
//print_r($changes);
		foreach($customFields as $key => $descr) {
//echo "Key $key ({$descr[0]} - {$descr[2]}): [{$changes[$key]}] isset: (".array_key_exists($key, $changes).")<br>";
			if(!array_key_exists($key, $changes)) continue; // && $descr[2] != 'boolean'
			if($descr[2] == 'oneline')
				deltaInputRow($descr[0].':', $key, $changes[$key], $clientCustomFields[$key], 'streetInput');
			else if($descr[2] == 'text')
				deltaTextRow($descr[0].':', $key, $changes[$key], $clientCustomFields[$key], 3, 60);
			else if($descr[2] == 'boolean')
				deltaCheckboxRow($descr[0].':', $key, $changes[$key], $clientCustomFields[$key]);
		}
	}
	echo "</table>";
	if($source['resolution']) echo "Resolution: request ".$source['resolution'];
	else if(adequateRights('#ec')) {
		echoButton('','Make Changes', "applyProfileChanges({$source['requestid']})");
		echo " ";
		echoButton('','Decline Request', "declineOrHonorRequest({$source['requestid']}, 0)");
		$sendToSitterEnabled = TRUE; //$_SESSION['preferences']['enableSendToSittersInProfileRequests'];
		if(staffOnlyTEST() || $sendToSitterEnabled) labeledCheckBox('Notify sitter(s) of changes', 'notifysitterscheckbox', !'value', !'labelclass', '!inputclass', '!onclick', 'boxfirst', !'noecho', 'Open a composer to notifiy sitters');
	}
	echo "</td></tr>";

}

// look in client-profile-fns.php -- function displayPetByPetIdDescriptionWithPhoto(...)


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
	echo "<p class='tiplooks'>Please note that you must edit the visit(s) below to make the above requested changes.</p>";
	clientScheduleTable($appts, array('buttons'));
	if($source['resolution']) echo "Resolution: request ".$source['resolution'];
	else {
		if(staffOnlyTEST()) {
			$applyNoteArg = ', "&applyNote="+document.getElementById("applyNote").checked';
			$changeLabel = "Changes Have Been Made";
		}
		else $changeLabel = "Changes Have Been Made";
		echoButton('', $changeLabel, "declineOrHonorRequest({$source['requestid']}, 1 $applyNoteArg)");
		echo " ";
		echoButton('','Decline Request', "declineOrHonorRequest({$source['requestid']}, 0)");
		if(staffOnlyTEST() && $source['note']) {
			echo " ";
			$grouping = strpos($source['scope'], 'sole_') === 0 ? "visit" : "day's visits";
			$title = "If this box is checked, the Note above will be added to the $grouping when [$changeLabel] button is clicked.";
			labeledCheckbox("Add the note above to the $grouping", 'applyNote', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title);
		}
	}
	echo "</td></tr>";
}

// showCancellationTable($source, $uncancel=null, $noButtons=false) -- moved to request-fns.php

function reqClientLink($source) {
	return <<<HTML
	<a  class='fauxlink' onClick="window.opener.location.href='client-edit.php?id={$source['clientptr']}'">{$source['fname']} {$source['lname']}</a>
HTML;
}
