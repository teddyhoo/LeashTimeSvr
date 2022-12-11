<?
// survey-submission-email.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "survey-fns.php";
require_once "provider-fns.php";
require_once "js-gui-fns.php";
require_once "comm-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$locked = locked('o-,#rs');


if($_POST['tags']) $_REQUEST['id'] = $_POST['tags'];
extract(extractVars('id', $_REQUEST));
$submission = fetchSurveySubmission($id);

ob_start();
ob_implicit_flush(0);
$omitOfficeNotes = true;
//require_once "survey-submission-view.php";
displaySurveySubmission($submission, array('no reply'=>"<i>no reply</i>"));

$submissionHTML = ob_get_contents();
//echo 'XXX: '.ob_get_contents();exit;
ob_end_clean();

if($_POST && !$error) {
	require_once "comm-composer-fns.php";
	extract(extractVars('mgrname,replyto,correspid,corresname,tags,user,correspaddr,subject,msgbody,id,tags', $_REQUEST));

	
	$recipients = array("\"$corresname\" <$correspaddr>");
} //$recipients
	/*$allSuppliedEmails[] = $correspaddr;
	if($_POST['clientemail2']) {
		$multiAddresses = explode(',', $_POST['clientemail2']);
		if(count($multiAddresses) == 1) {
			$allSuppliedEmails[] = $_POST['clientemail2'];
			$recipients[] = "\"$spousename\" <{$_POST['clientemail2']}>";
		}
		else foreach($multiAddresses as $addr) {
			$allSuppliedEmails[] = $addr;
			$recipients[] = "$addr";
		}
		//$correspondents[] = array('tblclient', $correspid);
	}
	*/
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
	
	if(userRole() == 'p') {
		$prefs = $_SESSION['preferences'];
		$replyTo = $_SESSION["provider_email"];
		if(!$replyTo || $prefs['replyToOfficeInSitterToClientEmail'])
			$replyTo = $prefs['defaultReplyTo'] ? $prefs['defaultReplyTo'] : $prefs['bizEmail'];
		$extraHeaders = array('Reply-to'=> $replyTo);
	}
	else $extraHeaders = null;
	
	$msg = $_POST;
	$msg['body'] = $msg['msgbody'] ? $msg['msgbody'] : $msg['body'];
	$msg['body'] = preprocessMessage($msg['body'], $correspondent);
	$msg['msgbody'] = $msg['body'];
	$msgbody = $msg['body'];
	$msgbody .= $submissionHTML;
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
	if(!$error) {  // Save outbound message
		$msg['correspaddr'] = $correspaddr; //join(', ', $$allSuppliedEmails);
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
		else if(mattOnlyTEST() && !$correspid) { // should not happen
			$msg['correspid'] = -99;
			$msg['correstable'] = 'unknown';
			saveOutboundMessage($msg);
		}

		echo "<script language='javascript'>if(window.opener && window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
	}
}


//print_r($submission);

$messageBody = "Please review the submission below.";
$messageSubject = $submission['surveyname'];
$formAction = "survey-submission-email.php";
$tags = $id;

if($submission['submittertable'] == 'tblclient') $_REQUEST['client'] = $submission['submitterid'];
else if($submission['submittertable'] == 'tblprovider') $_REQUEST['provider'] = $submission['submitterid'];
else if($submission['submittertable'] == 'tbluser') $_REQUEST['user'] = $submission['submitterid'];

$pageTitle = "Email ".$submission['surveyname'];
$suppressToLine = true;
require_once "comm-composer.php";

echo "<hr>$submissionHTML<hr>";
