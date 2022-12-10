<? // comm-composer-support.php -- send email to manager from support
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "comm-composer-fns.php";
require_once "email-template-fns.php";

if(userRole() == 'o' && staffOnlyTEST()) locked('o-');
else $locked = locked('z-');

extract(extractVars('replyto,all,'
											.'correspaddr,subject,msgbody,mgrname,lname,fname,email,tags,template,forwardid', $_REQUEST));

list($db1, $dbhost1, $dbuser1, $dbpass1) = array($db, $dbhost, $dbuser, $dbpass);
require "common/init_db_common.php";
list($dbLT, $dbhostLT, $dbuserLT, $dbpassLT) = array($db, $dbhost, $dbuser, $dbpass);
$managers = fetchAssociations(
	"SELECT *, CONCAT_WS(' ', fname, lname) as name
		FROM tbluser
		WHERE bizptr = {$_SESSION["bizptr"]}
			AND ltstaffuserid = 0
			AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
		ORDER BY SUBSTRING(rights FROM 1 FOR 1) DESC, lname ASC, fname ASC");
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);

if($_POST && !$error) {
	$recipients = array("$correspaddr");
	$allSuppliedEmails[] = $correspaddr;
	if($_POST['clientemail2']) {
		$allSuppliedEmails[] = $_POST['clientemail2'];
		$recipients[] = "{$_POST['clientemail2']}";
		//$correspondents[] = array('tblclient', $correspid);
	}
	
	if($reference && $all) {  // use BCC addresses only
		if(strpos($reference['correspaddr'], '|')) {
			$adds = array();
			$parts = explode('|', $reference['correspaddr']);
			foreach($parts as $labelList) {
				$labelList = explode(':', $labelList);
				if(strtoupper($labelList[1]) == 'BCC' && trim($labelList[1])) $bcc = $labelList[1];
			}
		}
	}
		
	$msg = $_POST;
	$msg['body'] = $msg['msgbody'] ? $msg['msgbody'] : $msg['body'];
	$msg['body'] = preprocessMessage($msg['body'], $correspondent);
	$msg['msgbody'] = $msg['body'];
	$msgbody = $msg['body'];
	if($forwardid) {
		$msgbody .= forwardedContent($forwardid);
		$msg['msgbody'] = $msgbody;
	}
	$attachments = null;
	if(strpos($msgbody, '#EMBEDDEDLOGOSRC#') !== FALSE) {
		$imagepath = 	getHeaderBizLogo($_SESSION["bizfiledirectory"]);
		if($imagepath) {
			$imagepath = "https://{$_SERVER["HTTP_HOST"]}/$imagepath";
			$attachments[] = array('imagetoken'=>'#EMBEDDEDLOGOSRC#', 'imagepath'=>$imagepath);
//echo 		print_r($attachments,1);exit;
		}
	}
	
	// override sender preferences here
	switchMailPrefs($switchback=false);
	
	if($error = sendEmail($recipients, $subject, $msgbody, '', 'html', $mgrname, $bcc, $extraHeaders, $attachments)) {
		
		if($error == 'BadCustomSMTPSettings')
			$error = "SMTP Server connection settings (host, port, username, or password) are incorrect.<p>"
								."Please review your "
								.fauxLink('<b>Outgoing Email</b> preferences', 'if(window.opener) window.opener.location.href="preference-list.php?show=4";window.close();', 1, 'Go there now')
								." in ADMIN > Preferences";
		include "frame-bannerless.php";
		//echo "Mail error:<p>".print_r($error, 1);
		//exit;
	}
	if(FALSE && !$error) {  // Save outbound message
		$msg['correspaddr'] = join(', ', $allSuppliedEmails);
		if($correspid && userRole() == 'p') {
			$msg['inbound'] = 0;
			$msg['originatorid'] = $_SESSION["providerid"];
			$msg['originatortable'] = 'tblprovider';
			$msg['datetime'] = date('Y-m-d H:i:s');
			$msg['transcribed'] = '';
			$msg['tags'] = $tags;

			foreach($msg as $field =>$val) 
				if(!in_array($field, $msgFields)) unset($msg[$field]);  // $msgFields defined in coom-fns.php
			insertTable('tblmessage', $msg, 1);

			require_once "event-email-fns.php";
			$notifees = notifyStaff('k', "Sitter note to client ({$_SESSION["shortname"]} => $corresname): $subject", $msg['body']);
			if(!$notifees && !$providerids) {
				$note = "Message from: {$_SESSION['fullname']}\n\nTo: Manager (none on notification duty)\n\n{$msg['body']}";
				$msg2 = array('inbound'=>1,'datetime'=>date('Y-m-d H:i:s'),
											'transcribed'=>'','body'=>$note,'subject'=>$subject, 
											'correspaddr'=>"{$_SESSION['fullname']} <{$_SESSION['provider_email']}>",
											'correspid'=>$_SESSION['providerid'], 'correstable'=>'tblprovider',
											'originatorid'=>$msg['originatorid'], 'originatortable'=>'tblprovider');
				insertTable('tblmessage', $msg2, 1);
			}

		}
		else if($correspid) {
			saveOutboundMessage($msg);
		}
		else if(mattOnlyTEST() && !$correspid) {
			$msg['correspid'] = -99;
			$msg['correstable'] = 'unknown';
			saveOutboundMessage($msg);
		}

		//echo "<script language='javascript'>if(window.opener && window.opener.update) window.opener.update('messages', null);window.close();</script>";
	}
	if(!$error) echo "<script language='javascript'>if(window.opener && window.opener.update) window.opener.update('messages', null);window.close();</script>";
	// restore sender preferences here
	switchMailPrefs($switchback=true);

}

function switchMailPrefs($switchback=false) {
	global $installationSettings, $mailPrefs;
	if(!$switchback) {
		$mailPrefs['emailFromAddress'] = $_SESSION['preferences']['emailFromAddress'];
		$mailPrefs['shortBizName'] = $_SESSION['preferences']['shortBizName'];
		$mailPrefs['bizName'] = $_SESSION['preferences']['bizName'];
		$mailPrefs['emailHost'] = $_SESSION['preferences']['emailHost'];
		$mailPrefs['emailUser'] = $_SESSION['preferences']['emailUser'];
		$mailPrefs['emailPassword'] = $_SESSION['preferences']['emailPassword'];
		$mailPrefs['smtpPort'] = $_SESSION['preferences']['smtpPort'];
		$mailPrefs['smtpSecureConnection'] = $_SESSION['preferences']['smtpSecureConnection'];
		$mailPrefs['emailBCC'] = $_SESSION['preferences']['emailBCC'];
		$mailPrefs['defaultReplyTo'] = $_SESSION['preferences']['defaultReplyTo'];
		
		$_SESSION['preferences']['emailFromAddress'] = 'support@leashtime.com';
		$_SESSION['preferences']['shortBizName'] = 'LeashTime';
		$_SESSION['preferences']['bizName'] = 'LeashTime';
		$_SESSION['preferences']['emailHost'] = $installationSettings['smtphost'];
		$_SESSION['preferences']['emailUser'] = $installationSettings['smtpuser'];
		$_SESSION['preferences']['emailPassword'] = $installationSettings['smtppassword'];
		$_SESSION['preferences']['smtpPort'] = $installationSettings['smtpPort'];
		$_SESSION['preferences']['smtpSecureConnection'] = null;
		$_SESSION['preferences']['emailBCC'] = null;
		$_SESSION['preferences']['defaultReplyTo'] = null;
		//foreach($mailPrefs as $k => $v) echo "BEFORE $k: $v<br>";
	}
	else {
		foreach($mailPrefs as $k => $v) {
			$_SESSION['preferences'][$k] = $v;
			//echo "AFTER $k: $v<br>";
		}
	}
}


$messageSubject = $subject;
$messageBody = $message;
$message = null; // necessary since message is a status message in comm-composer
// find manager email
foreach($managers as $mgr) {
	if($mgr['isowner']) {
		$email = $mgr['email'];
		break;
	}
}


$email = $email ? $email : $managers[0]['email'];
$clientemail2 = $_SESSION['preferences']['bizEmail'];
$formAction = 'ACTION = "comm-composer-support.php"';
include "comm-composer.php";
