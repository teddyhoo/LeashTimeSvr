<?
// client-request-page.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "request-fns.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-');


extract(extractVars('requestStart,requestEnd,unresolvedOnly,offset,showType,assignedTo,sort,client,resolveRequests,deleteSpam,deleteRequest', $_REQUEST));

$showUnresolvedcheckboxes = staffOnlyTEST() || $_SESSION['preferences']['enableBulkRequestResolution'];


if($_POST && $resolveRequests) {
	//print_r($_POST);
	foreach($_POST as $k => $v)
		if(strpos($k, 'req_') === 0) $ids[] = substr($k, strlen('req_'));
	if($ids) {
		if($deleteSpam) {
			deleteTable('tblclientrequest', "requesttype='Spam' AND requestid IN (".join(',', $ids).")", 1);
			$deleted = mysqli_affected_rows();
			$_SESSION['frame_message'] = "$deleted requests deleted.";
			logChange($deleted, 'tblclientrequest', 'd', "$deleted requests deleted");
		}
		else if($deleteRequest) {
			deleteTable('tblclientrequest', "requestid IN (".join(',', $ids).")", 1);
			$deleted = mysqli_affected_rows();
			$_SESSION['frame_message'] = "$deleted requests deleted.";
			logChange($deleted, 'tblclientrequest', 'd', "$deleted requests deleted");
		}
		else {
			updateTable('tblclientrequest', array('resolved'=>1), "requestid IN (".join(',', $ids).")", 1);
			$modified = mysqli_affected_rows();
			$_SESSION['frame_message'] = "$modified requests resolved.";
			logChange($modified, 'tblclientrequest', 'm', "$modified requests resolved");
		}
	}
	else $_SESSION['frame_message'] = "No requests resolved.";
}


if(!isset($_REQUEST['unresolvedOnly'])) $unresolvedOnly = true;
// clientname|Client||requesttype|Request||date|Date||address|Address||phone|Phone


$pageTitle = 'Client Request Report';
include "frame.html";
// ***************************************************************************

echo "<form name='showform'>";  // buttons do a redirect
echo "<span style='font-size:1.1em'>";
//function calendarSet($label, $name, $value=null, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null) {

calendarSet('Start Date', 'requestStart', $requestStart, null, null, true, 'requestEnd');
hiddenElement('origFirstDay', date('Y-m-d', strtotime($requestStart)));
echo ' ';
calendarSet('End Date', 'requestEnd', $requestEnd);

$showTypes = array('-- All Types --'=>0);
//print_r($requestTypes);
foreach($requestTypes as $val => $label) $showTypes[$label] = $val;
// label => value
/*$typeRadios = radioButtonSet('showTypes', $showTypes, $requestTypes, $onClick=null, $labelClass=null, $inputClass=null);
echo "<p>";
foreach($typeRadios as $radio) echo "$radio ";*/
echo '<p>';
selectElement('Show request type:', 'showType', $showType, $showTypes, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false);
echo ' ';
labeledInput('Client name filter: ', 'client', $client);
echo ' ';
echoButton('showrequestsButton', 'Show', "showRequests()"); 

$officeAssignmentsEnabled = staffOnlyTEST() && $_SESSION['preferences']['enablerequestassignments'];

if($officeAssignmentsEnabled) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	if(!staffOnlyTEST()) $NOLTSTAFF = " AND ltstaffuserid = 0";
	$managers = fetchAssociationsKeyedBy(
		"SELECT CONCAT_WS(' ', fname, lname) as name, userid, loginid
			FROM tbluser 
			WHERE bizptr = {$_SESSION['bizptr']} AND active=1
			AND (rights LIKE 'o-%' OR rights LIKE 'd-%') $NOLTSTAFF
			ORDER BY lname, fname", 'userid');
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	echo '<p>';
	$assignOptions = 
		array('- to anyone or no one -'=>0, '- to any manager -'=>-1, '- to no one -'=>-2);
	foreach($managers as $userid=>$mgr) if($mgr['name']) $assignOptions[$mgr['name']] = $userid;
	selectElement('Assigned:', 'assignedTo', $assignedTo, $assignOptions, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false);
}




$showResolved = $unresolvedOnly 
	? fauxLink('Show resolved requests also', "showRequests(\"toggle\")", 'noEcho', '', 'showHideRequests')
	: fauxLink('Hide resolved requests', "showRequests(\"toggle\", 20)", 'noEcho', '', 'showHideRequests');
$initialUnresolvedLimit=20;
$sepr = "<img src='art/spacer.gif' width=20 height=1>";
if(!$unresolvedOnly) 
	$showMore = 
		fauxLink('Show more requests', "showRequests(0, \"more\")", 'noEcho', '', 'showMoreRequests')
		.($offset 
			? $sepr.fauxLink("Show first $initialUnresolvedLimit", "showRequests(0, $initialUnresolvedLimit)", 'noEcho', '', 'showMoreRequests')
			: '');

echo "\n\n";
echo "</form>";
$filterParams = null;
require_once "preference-fns.php";
if($_REQUEST['sort']) {
	setUserPreference($_SESSION['auth_user_id'], 'requestPageSort', $_REQUEST['sort']);
	$filterParams['sort'] = $_REQUEST['sort'];
}
else if($savedSort = getUserPreference($_SESSION['auth_user_id'], 'requestPageSort'))
	$filterParams['sort'] = $savedSort;
else
	$filterParams['sort'] = 'requesttype_asc';
$prettySort = str_replace('_', ' ', $filterParams['sort']).'ending';

echo "<p>$showResolved $sepr $showMore (sorted by $prettySort)<p>";
$spamFeatureAccess = true; // staffOnlyTEST() || dbTEST('sarahrichpetsitting');
if($showUnresolvedcheckboxes) {
	//echo "<p>";
	echo "<form name='resolveform' method='POST' action='client-request-page.php?resolveRequests=1'>";
	fauxLink('Select All', 'selectAll(1)');
	echo " - ";
	fauxLink('Deselect All', 'selectAll(0)');
	echo " - ";
	if($spamFeatureAccess) {
		fauxLink('Select All SPAM', 'selectAllSpam(0)', $noEcho=false, $title='Select all requests shown that are marked as spam', $id=null, $class='spamRelated');
		echo " - ";
	}
	echoButton('', 'Mark Selected Requests Resolved', 'markResolved()');
	if($spamFeatureAccess) {
		echo " - ";
//function echoButton($id, $label, $onClick='', $class='', $downClass='', $noEcho=false, $title=null) {
		
		echoButton('', 'Delete Selected Spam', 'deleteSelectedSpam()', 'HotButton spamRelated', 'HotButtonDown', $noEcho=false, $title='Delete any of the selected messages that are marked as spam' );
	}
	if(staffOnlyTEST()) {
		echo " - ";
		echoButton('', 'Delete Selected Requests (StaffOnly)', 'deleteSelectedRequestsSTAFFONLY()', 'HotButton', 'HotButtonDown' );
	}
}
echo "\n\n";

$columnSorts = explodePairsLine('clientname|asc||requesttype|asc||date|asc||');
if($_REQUEST['requestStart']) $filterParams['requestStart'] = $_REQUEST['requestStart'];
if($_REQUEST['requestEnd']) $filterParams['requestEnd'] = $_REQUEST['requestEnd'];
if($_REQUEST['showType']) $filterParams['showType'] = $_REQUEST['showType'];
if($_REQUEST['assignedTo']) $filterParams['assignedTo'] = $_REQUEST['assignedTo'];
if($_REQUEST['client']) $filterParams['client'] = $_REQUEST['client'];

//clientRequestSection($updateList, $unresolvedOnly=true, $offset=0, $initialUnresolvedLimit=20, $filterParams=null)
$initialUnresolvedLimit=20;
$showCount = true; //mattOnlyTEST();
clientRequestSection($updateList, $unresolvedOnly, $offset, $initialUnresolvedLimit, $filterParams, $unresolvedcheckboxes=$showUnresolvedcheckboxes);


if($spamFeatureAccess) {
hiddenElement('deleteSpam', 0);
hiddenElement('deleteRequest', 0);
echo "</form>";
}
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
//alert($('input[spam="1"]').length);
if($('input[spam="1"]').length == 0) $(".spamRelated").toggle();

setPrettynames('requestStart', 'Start Date', 'requestEnd', 'End Date');

function update(aspect, text) {
	showRequests();
}

function showRequests(toggle, more, sort) {
	if(typeof toggle == 'undefined') toggle = null;
	if(typeof more == 'undefined') more = null;
	if(typeof sort == 'undefined') sort = '<?= $_REQUEST['sort'] ?>';
	var toggleEl = document.getElementById('showHideRequests');
	var unresolvedOnly = <?= $unresolvedOnly ? 1 : '0' ?>;
	if(toggle) unresolvedOnly = unresolvedOnly ? 0 : 1; 
	var offset = <?= $offset ? $offset : '0' ?>;
	if(more) {
		if(typeof more == 'number') offset = 0;
		else offset = offset + <?= $initialUnresolvedLimit ?>;
	}
	var requestStart = document.getElementById('requestStart').value;
	var requestEnd = document.getElementById('requestEnd').value;
	var showType = document.getElementById('showType');
	showType = showType.options[showType.selectedIndex].value;
	<? if($officeAssignmentsEnabled) { ?>
	var assignedTo = document.getElementById('assignedTo');
	assignedTo = assignedTo.options[assignedTo.selectedIndex].value;
	<? } ?>
	var client = jstrim(document.getElementById('client').value);
	if(MM_validateForm(
		'requestStart','','isDate', 
		'requestEnd', '', 'isDate', 
		'requestStart', 'requestEnd', 'datesInOrder')) {
			document.location.href='client-request-page.php?requestStart='+requestStart+'&requestEnd='+requestEnd
															+'&unresolvedOnly='+unresolvedOnly
															+'&offset='+offset
															+'&showType='+showType
															<? if($officeAssignmentsEnabled) { ?>
															+'&assignedTo='+assignedTo
															<? } ?>
															+'&sort='+sort
															+'&client='+client;
	}
}

function selectAllSpam() {
	$('input[spam="1"]').prop("checked", true);
}

function deleteSelectedSpam() {
	var selectedSpam = 0;
	$('input[spam="1"]').each(function(ind, el) { if(el.checked) selectedSpam+=1;});
	if(selectedSpam == 0) {
		alert('Please select some spam to delete first.');
		return;
	}
	if(!confirm("Delete "+selectedSpam+" Prospect Spam Requests?"))
		return;
	document.resolveform.deleteSpam.value = 1;		
	document.resolveform.submit();		
}	


function deleteSelectedRequestsSTAFFONLY() {
	var selectedRequest = 0;
	$('input[type="checkbox"]').each(function(ind, el) { if(el.checked) selectedRequest+=1;});
	if(selectedRequest == 0) {
		alert('Please select some requests to delete first.');
		return;
	}
	if(!confirm("Delete "+selectedRequest+" Requests?"))
		return;
	document.resolveform.deleteRequest.value = 1;		
	document.resolveform.submit();		
}	



function selectAll(onoff) {
	var inputs;
	inputs = document.getElementsByTagName('input');
	for(var i=0; i < inputs.length; i++)
		if(inputs[i].type=='checkbox' && inputs[i].id.indexOf('req_') == 0)
			inputs[i].checked = (onoff ? 1 : 0);
}

function markResolved() {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('req_') == 0 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert('Please select one or more clients first.');
		return;
	}
	document.resolveform.submit();
}

// ************** REQUEST MARKER SCRIPT *******************
function requestMarkerClicked(elid, event) { // elid = requestid
	var readonly = <?= userIsACoordinator() ? 'false' : 'true' ?>; // will be true when user has no coordinator permission
	if(readonly) $('#r'+elid).toggle();
	else {
		var markerform = "<span style='font-size:1.2em'>This request is "+$('#r'+elid).html()+'<hr>'
		+"<form name='assignrequest' method='POST'>"
		+"<? 
			$s = str_replace("\n", "", requestOwnerPullDownMenu($request, $label='Reassign to:', $noEchoNow=true)); 
			$s = str_replace("id='owner'", "id='owner' requestid='#REQID#'", $s);
			echo $s;
			?>"
		+"</form></span>";
		markerform = markerform.replace("#REQID#", elid);
		$.fn.colorbox({html:markerform, width:"400", height:"400", scrolling: true, opacity: "0.3"});
	}
	if (event.stopPropagation) event.stopPropagation(); // W3C standard variant
	else event.cancelBubble = true; // IE variant
}

function assignRequest(el) {
	var val = el.options[el.selectedIndex].value;
	var reqid = el.getAttribute('requestid');
	if(val == 0) val = -1;
	ajaxGetAndCallWith('request-edit.php?id='+reqid+'&reassign='+reqid+'&adm='+el.options[el.selectedIndex].value, 
		assignedTo, null);
		
}

function assignedTo(argument, responseText) {
	if(responseText == 'ERROR') {
		alert('Sorry, no can do.');
		return;
	}
	if(window.update) {
		window.update('clientrequests');
	}
}

function showRequestHistory() {
	// make an ajax call and dump the reults to requesthistorydiv
	var reqid = document.getElementById('owner').getAttribute('requestid');
	ajaxGet('request-edit.php?id='+reqid+'&assignmenthistory='+reqid, 'requesthistorydiv');
}
// ************** REQUEST MARKER SCRIPT *******************

<? dumpPopCalendarJS(); ?>
</script>
<img src='art/spacer.gif' height=300 width=1>
<?


// ***************************************************************************
include "frame-end.html";