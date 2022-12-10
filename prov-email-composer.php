<?
// prov-email-composer.php

// allow a provider to see other providers in a list and compose email to one or more of them

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "field-utils.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "event-email-fns.php";

// Determine access privs
$locked = locked('p-');

if(!$_SESSION['preferences']['allowProvidertoProviderEmail']) {
	$pageTitle = "This Feature Is Disabled";
	include "frame.html";
	include "frame-end.html";
	exit;
}
	
extract(extractVars('subject,msgbody', $_REQUEST));

$provider = $_SESSION["providerid"];
$managerOnly = -99;

if($_POST) {
	$providerids = array();
	foreach($_POST as $k => $v)
		if(strpos($k, 'p_') === 0) {
			$parts = explode('_', $k);
			if($parts[1] != $managerOnly)
				$providerids[] = $parts[1];
		}
	$originator = $provider ? fetchFirstAssoc("SELECT * FROM tblprovider WHERE providerid = $provider LIMIT 1") : null;
	if($providerids) {
		$recipients = fetchAssociations(
				"SELECT *, CONCAT_WS(' ', fname, lname) as fullname FROM tblprovider WHERE providerid IN ("
				.join(',', $providerids)
				.")");
		
		foreach($recipients as $recipient) $names[] = $recipient['fullname'];
		$msgbody = "Memo to: ".join(", ", $names)."\n\n$msgbody";
		$recipientsPlus = $recipients;
		if($_SESSION["provider_email"]) {
			$recipientsPlus[] = fetchFirstAssoc(
				"SELECT *, CONCAT_WS(' ', fname, lname) as fullname FROM tblprovider WHERE providerid = $provider LIMIT 1");
		}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { /*print_r($recipientsPlus);*/  print_r(staffToNotify('e')); exit; }		
		$sender = $originator;
		enqueueMassEmailNotification($noPersons=array(), $subject, $msgbody, $cc=null, $bcc=$recipientsPlus, null, $html=false, $originator);

		$note = "Email from: {$_SESSION["fullname"]}\n\n$msgbody";
	}
	else $note = "Email from: {$_SESSION["fullname"]}\n\nTo: On-Duty Manager\n\n$msgbody";
	
	$notifees = notifyStaff('e', "Sitter note: $subject", $note);
	if(!$notifees && !$providerids) {
		$note = "Message from: {$_SESSION["fullname"]}\n\nTo: Manager (none on notification duty)\n\n$msgbody";
		$msg = array('inbound'=>1,'datetime'=>date('Y-m-d H:i:s'),
									'transcribed'=>'','body'=>$note,'subject'=>$subject, 
									'correspaddr'=>"{$originator['fname']} {$originator['lname']} <{$originator['email']}>",
									'correspid'=>$originator['providerid'], 'correstable'=>'tblprovider',
									'originatorid'=>$originator['originatorid'], 'originatortable'=>'tblprovider');
		insertTable('tblmessage', $msg, 1);
	}
	
	$others = array();
	if(count($recipients)) $others[] = ' sent to '.count($recipients)." provider(s)";
	if($_SESSION["provider_email"]) $others[] = "CC'd to you";
	
	$message = $notifees ? "Email sent to the On-Duty Manager" : "Message sent to Management";
	if($others) $message .= join(' and ', array((!$notifees ? ' and email ' : ''), join(' and ', $others)));
	$message .= '.';
}






$providers = fetchAssociations(
	"SELECT *, CONCAT_WS( ' ', fname, lname, 
			if( nickname IS NULL , '', CONCAT( ' (', nickname, ')' ) ) )  AS fullname,
			ifnull(nickname, CONCAT_WS( ' ', fname, lname)) as shortname
		FROM tblprovider
		WHERE active =1 "//.($provider ? "AND providerid <> $provider" : '')
					.' ORDER BY lname, fname');
		
$keyClients = getKeyClients();
$allKeyClients = array();
if($keyClients) {
	foreach($keyClients as $p => $clients) 
		foreach($clients as $client)
			$allKeyClients[] = $client;
	$allKeyClients = getClientDetails(array_unique($allKeyClients));
}



$pageTitle = "Send an Email";

include "frame.html";
// ***************************************************************************
//echo date('H:i:s').'<p>';
if($message) {
	echo "<span class='pagenote'><b>$message</b><p></span><p>";
	echoButton('', 'Send Another Email', "document.location.href=\"prov-email-composer.php\"");
	include "frame-end.html";
	exit;
}

$officePhone = $_SESSION['preferences']['bizPhone'];
$officePhone = $officePhone ? "<br><b>Office: $officePhone</b>" : '';
$cols = array(array('shortname'=>'On-Duty Manager','fullname'=>"On-Duty Manager ONLY$officePhone",'providerid'=>$managerOnly, 'email'=>'x')); 
$cols = array_merge($cols, $providers);
$cols = array_chunk($cols, max((count($cols) / 3)+(count($cols) % 3 ? 1 : 0), 1), true);
$cols = array_map('array_values', $cols);
echo "<span class='pagenote'>If assigned, the On-Duty Manager is emailed a copy of all provider messages.</span><p>";
echo "<form name='providermsg' method='POST'><table style='width:100%'>";
for($r=0;$r < count($cols[0]); $r++) {
	echo "<tr>";
	for($c=0;$c < count($cols); $c++) {
		echo "<td>";
		if(!($p = $cols[$c][$r])) {
			echo "&nbsp;";
			continue;
		}
		$pid = $p['providerid'];

		$keys = null;
		if($keyClients[$pid]) 
			foreach($keyClients[$pid] as $client)  $keys[] = $allKeyClients[$client]['clientname'];
		$nameTitle = addslashes($keys ? 'Has keys for: '.join(', ', $keys)	 : 'Has no keys');
		//echo "<font color=red>$nameTitle</font>";
		
		$notSoMuch = $p['providerid'] == $provider 
			? safeValue("Your email address ".($p['email'] ? "is {$p['email']}" : "has not been supplied."))
			: 'No email';
		if($p['email']  && $p['providerid'] != $provider) 
			echo "<input type='checkbox' id='p_$pid' name='p_$pid' shortname='{$p['shortname']}' onChange='updateRecips($pid)'>";
		else echo "<img src='art/notsomuch.gif' height=13 title='$notSoMuch'>";
		echo " <label for='p_$pid' title=\"$nameTitle\">{$p['fullname']}<br>";
		foreach(array('homephone', 'cellphone', 'workphone') as $k) {
			$num = str_replace(' ', '&nbsp;', strippedPhoneNumber($p[$k]));
			if($k == primaryPhoneField($p) && $num) echo "<b>($k[0])$num</b> ";
			else if($num) echo "($k[0])$num ";
			else echo "&nbsp;";
		}
		echo "</label></td>";
	}
	echo "</tr>";
}
echo "</table>";
echo "<p>";
echo "<table>";
inputRow('Subject:', 'subject', (isset($messageSubject) ? $messageSubject : ''), null, 'VeryLongInput');
labelRow('To:', 'recips', '');
textRow('Message:', 'msgbody', $messageBody, $rows=20, $cols=80);
echo "</table></form>";

echoButton('', 'Send Message', 'checkAndSend()');
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function updateRecips(pid) {
	var names = new Array();
	var clear = pid == <?= $managerOnly ?>;
	var inputs = document.getElementsByTagName('input');
	
	for(var i = 0; i < inputs.length; i++) {
		var cb = inputs[i];
		if(cb.type == 'checkbox' && cb.checked) {
			if(clear && cb.id != 'p_'+<?= $managerOnly ?>) {
				cb.checked = false;
			}
			else if(!clear) document.getElementById('p_<?= $managerOnly ?>').checked = false;
		}
	}
	
	for(var i = 0; i < inputs.length; i++) {
		var cb = inputs[i];
		if(cb.type == 'checkbox' && cb.checked)
			names[names.length] = cb.getAttribute('shortname');
	}
	
	document.getElementById('recips').innerHTML = names.join(', ');
}
	

setPrettynames('subject','Subject','msgbody','Message');	

function checkAndSend() {
	var nonechosen = 'You must first check at least one recipient.';
	var inputs = document.getElementsByTagName('input');
	for(var i = 0; i < inputs.length; i++)
		if(inputs[i].type == 'checkbox' && inputs[i].checked)
			nonechosen = '';
	if(MM_validateForm(nonechosen, '', 'MESSAGE',
											'subject','', 'R',
											'msgbody','', 'R'
											))
  		document.providermsg.submit();
}

</script>

<?
include "js-refresh.php";

// ***************************************************************************

include "frame-end.html";

function getKeyClients() {
	$keyClients = array();
	$result = doQuery("SELECT possessor1, possessor2, possessor4, possessor4, possessor5, clientptr
										 FROM tblkey
										 WHERE possessor1 IS NOT NULL OR possessor2 IS NOT NULL OR 
										 				possessor4 IS NOT NULL OR possessor4 IS NOT NULL OR possessor5 IS NOT NULL");
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		for($i = 0; $i < 5; $i++) {
			$k = "possessor$i";
			if($row[$k] && is_numeric($row[$k])) $keyClients[$row[$k]][] = $row['clientptr'];
		}
	}
	foreach($keyClients as $p => $clients) $keyClients[$p] = array_unique($clients);
	return $keyClients;
}