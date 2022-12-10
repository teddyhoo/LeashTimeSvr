<? // confirmations.php

// preference-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
require_once "provider-fns.php";

// Determine access privs
$locked = locked('o-');

//$pageTitle = "Confirmations";
$to = $_REQUEST['to'] ? $_REQUEST['to'] : 'providers';
$other = $to == 'providers' ? 'clients' : 'providers';
$tab = $_REQUEST['tab'] ? $_REQUEST['tab'] : 'confirmed';
$toLabels = array('clients'=>'Client', 'providers'=>'Sitter');

include "frame.html";
?>
<style>
.bluebar {width:99.85%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;
					text-align:center;font-size:1.1em;padding-top:5px;margin-bottom:2px;}
.tabrow {width:98.5%;}					
.tabrow td {padding:0px;}					
.tabplain {margin:2px;padding: 10px 4px 10px 4px;text-align:center;font: bold 1.1em arial,sans-serif;background:palegreen;}
.tabselected {margin:2px;padding: 10px 4px 10px 4px;text-align:center;font: bold 1.3em arial,sans-serif;background:#CCFFCC;}
</style>

<span class='h2'>Confirmations</span><p>
<span style='font-weight:bold;font-size:16px;text-decoration:underline;'><?= $toLabels[$to] ?> Confirmations</span>
<img src='art/spacer.gif' width=20 height=1>
<a href='confirmations.php?to=<?= $other ?>'><?= $toLabels[$other] ?> Confirmations</a><p>
<?
// ***************************************************************************
$managerIds = getManagerUsers();
$providers = fetchKeyValuePairs("SELECT userid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name from tblprovider WHERE userid IS NOT NULL");
$labelAndIds = array("confirmed"=>"Recently Confirmed", "overdue"=>'Overdue', "declined"=>'Recently Declined', "pending"=>"Pending", "others"=>"Others");

$basicSQL = $to == 'clients'
	?
	"SELECT conf.*, clientid as recipientid, CONCAT_WS(' ', tblclient.fname, tblclient.lname) as recipient, 
			msg.msgid,  msg.subject, msg.datetime, tblclientrequest.note as response
	 FROM tblconfirmation conf
	 LEFT JOIN tblclient ON clientid = respondentptr
	 LEFT JOIN tblmessage msg ON msg.msgid = msgptr
	 LEFT JOIN tblclientrequest ON requestid = responsemsgptr
	 WHERE respondenttable = 'tblclient'"
	 :
	"SELECT conf.*, providerid as recipientid, CONCAT_WS(' ', tblprovider.fname, tblprovider.lname) as recipient, 
			msg.msgid,  msg.subject, msg.datetime, tblclientrequest.note as response
	 FROM tblconfirmation conf
	 LEFT JOIN tblprovider ON providerid = respondentptr
	 LEFT JOIN tblmessage msg ON msg.msgid = msgptr
	 LEFT JOIN tblclientrequest ON requestid = responsemsgptr
	 WHERE respondenttable = 'tblprovider'";
	
$filters = array(	
	'overdue'=>" AND conf.resolution = 'pending' AND expiration > NOW() AND NOW() >= due ORDER BY due DESC",
	'confirmed'=>" AND conf.resolution = 'received' AND FROM_DAYS(TO_DAYS(resolutiondate)+7) > NOW() ORDER BY due DESC",
	'declined'=>" AND conf.resolution = 'declined' AND FROM_DAYS(TO_DAYS(resolutiondate)+7) > NOW() ORDER BY due DESC",
	'pending'=>" AND conf.resolution = 'pending' AND NOW() < due ORDER BY due DESC",
	'others'=>" AND confid NOT IN (##LISTEDCONFS##) AND FROM_DAYS(TO_DAYS(expiration)+14) > NOW() ORDER BY due DESC");
	
$allConfs = array();
$listedConfs = array(-99);
foreach($filters as $tabname => $filter) {
	$filter = str_replace('##LISTEDCONFS##', join(',', $listedConfs), $filter);
	$confs = fetchAssociationsKeyedBy($basicSQL.$filter, 'confid');
	$listedConfs = array_merge($listedConfs, array_keys($confs));
	$allConfs[$tabname] = $confs;
}
	
$resolutionTitle = ' ';
if($tab == 'overdue') {
	$extraColumns = 'due,buttons';
}
else if($tab == 'confirmed') {
	$extraColumns = 'resolutiondate,resolvedby';
	$resolutionTitle = 'Confirmed';
}
else if($tab == 'declined') {
	$extraColumns = 'resolutiondate,resolvedby';
	$resolutionTitle = 'Declined';
}
else if($tab == 'pending') {
	$extraColumns = 'due,buttons';
}
else if($tab == 'others') {
	$extraColumns = 'resolutiondate,due,status,resolvedby';
	$resolutionTitle = 'Resolved';
}
	
$confirmations = $allConfs[$tab];
$listedConfs = array_merge($listedConfs, array_keys($confirmations));

echo "<table class='tabrow'><tr>";
foreach($labelAndIds as $key => $label) {
	$w = (100 / count($labelAndIds));
	echo "<td style='width:$w%'>";
	if($tab == $key) echo "<div class='tabselected'>$label (".count($allConfs[$key]).")</div>";
	else echo "<div class='tabplain'><a href='confirmations.php?to=$to&tab=$key'>$label (".count($allConfs[$key]).")</a></div>";
	echo "</td>";
}
echo "</tr></table>";



?>
	
<h3><?= $labelAndIds[$tab] ?></h3>
<?
	confirmationTable($confirmations, $extraColumns, $resolutionTitle); 

// #############################################
function confirmationTable($confirmations, $extraColumns, $resolutionTitle='_') {
	global $managerIds, $providers;
	if(!$confirmations) {
		echo "None found.";
		return;
	}
	$allColumns = explodePairsLine("sent|Sent||due|Due||expires|Expires||resolutiondate|$resolutionTitle||recipient|Recipient||subject|Subject||status|Status||resolvedby|$resolutionTitle By||buttons| ");
	$optionalColumns = explode(',', 'resolutiondate,due,expires,status,resolvedby,buttons');
	$extraColumns = explode(',', $extraColumns);
	foreach($allColumns as $key => $label)
		if(!in_array($key, $optionalColumns) || (in_array($key, $extraColumns)))
			$columns[$key] = $label;
	$numCols = count($columns);
	$rows = array();
	$rowClass = 'futuretaskEVEN';
	foreach($confirmations as $conf) {
		$row = array();
		$row['sent'] = datetimeOrNull($conf['datetime']);
		//$row['expires'] = datetimeOrNull($conf['expiration']);
		$expires = datetimeOrNull($conf['expiration']);
		$row['due'] = "<span title='Expires: $expires'>".datetimeOrNull($conf['due'])."</span>";
		$row['resolutiondate'] = datetimeOrNull($conf['resolutiondate']);
		$row['recipient'] = $conf['respondenttable'] == 'tblclient' ? clientLink($conf['recipientid'], $conf['recipient']) : $conf['recipient'];
		$row['subject'] = messageLink($conf['msgid'], $conf['subject'], $conf['msgsection']);
		$row['status'] = 
			$conf['resolution'] != 'pending'
				? $conf['resolution']
				: (time() >= strtotime($conf['expiration'])
						? 'expired'
						: (time() >= strtotime($conf['due']) 
								? 'overdue'
								: 'pending'));

		$resolvedby = 
		$row['resolvedby'] = $conf['resolvedby'] 
												? ($managerIds[$conf['resolvedby']] 
														? $managerIds[$conf['resolvedby']] 
														: ($providers[$conf['resolvedby']] 
															? $providers[$conf['resolvedby']]
															: 'Client')) 
												: '&nbsp;';
												
		if(in_array('buttons', $optionalColumns))
			$row['buttons'] = 
													"<img style='cursor:pointer;border: darkgray black 1px;' width=16 height=10
															   onClick='resendConfirmationRequest({$conf['msgptr']})'
																title='Re-send this confirmation request' src='art/tiny-email-message.gif'> "
													. "<img style='cursor:pointer;border: darkgray black 1px;' width=13 height=13
															   onClick='cancelConfirmation({$conf['confid']})'
																title='Cancel this confirmation request' src='art/delete.gif'> "
													."<img style='cursor:pointer;border: darkgray black 1px;' width=11 height=11
															   onClick='receivedConfirmation({$conf['confid']})'
																title='Mark this confirmation received.' src='art/greencheck.gif'> ";
		$rows[] = $row;
		$rowClasses[] = $rowClass;
		if($conf['response']) $reason = $conf['response'];
		else if($conf['note']) $reason = $conf['note'];
		else $reason = '';
		if($reason) {
			if(strpos($reason, '</a><p>')) $reason = substr($reason, strpos($reason, '</a><p>')+strlen('</a><p>'));
			$reason = truncatedLabel(strip_tags($reason), 100);
			if($conf['responsemsgptr']) $reason = 'Response: '.requestLink($conf['responsemsgptr'], $reason);
			$rows[] = array('#CUSTOM_ROW#'=> "\n<tr class='$rowClass'><td colspan=$numCols style='font-style:italic;'>$reason</td></tr>");
			$rowClasses[] = $rowClass;
		}
		
		$rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
		
	}
	tableFrom($columns, $rows, 'WIDTH=100% ',null,null,null,null,null,$rowClasses);
}

function messageLink($msgid, $subject, $msgsection) {
	return fauxLink($subject, "openConsoleWindow(\"messagecomposer\", \"comm-view.php?id=$msgid&section=$msgsection\", 600, 600);", 1);
}

function requestLink($msgid, $subject) {
	return fauxLink($subject, "openConsoleWindow(\"messagecomposer\", \"request-edit.php?id=$msgid\", 610, 600);", 1);
}
function clientLink($clientid, $subject) {
	return fauxLink($subject, "openConsoleWindow(\"viewclient\", \"client-view.php?id=$clientid\", 500, 700);", 1);
}
function datetimeOrNull($date) {
	return !$date ? '&nbsp;' : shortDateAndTime(strtotime($date), 'mil');
}

include "js-refresh.php";
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function cancelConfirmation(confid) {
	var note;
	note = prompt('You can enter a reason for the cancellation here if you like and click OK\nor click Cancel if you change your mind.');
	if(typeof note == 'string')
		ajaxGetAndCallWith('confirmation-mod-ajax.php?action=cancel&id='+confid+'&note='+note, refresh, 1);
}

function receivedConfirmation(confid) {
	var note;
	note = prompt('You can enter a note about the confirmation here if you like\nand click OK to mark it received\nor click Cancel if you change your mind.');
	if(typeof note == 'string')
		ajaxGetAndCallWith('confirmation-mod-ajax.php?action=receive&id='+confid+'&note='+note, refresh, 1);
}


function resendConfirmationRequest(msgid) {
	ajaxGetAndCallWith('confirmation-resend-ajax.php?id='+msgid, postresend, 1);
}

function postresend(a,b) {
	if(b) alert(b);
	else alert('Request re-sent.');
}

</script>
<?
include "frame-end.html";
