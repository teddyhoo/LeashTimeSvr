<? // email-fns.php
function isSMTPHostExperimental() {
	return false;
	$host = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'emailHost' LIMIT 1");
	return in_array(strtolower($host), array('smtp.zoho.com', 'smtp.live.com', 'smtp.mail.yahoo.com')) 
		? $host
		: null; 
}

function getSMTPStatus() {
	$prefs = $_SESSION['preferences'];
	if($prefs['emailHost']) {
		$status = '<u>External SMTP Server</u>';
		if(!$prefs['emailUser'] || !$prefs['emailPassword']) $status .= 'Email User Name and Password are required';
		else {
			foreach(array('smtpPort'=>'SMTP Port','smtpSecureConnection'=>'Secure Connection','emailFromAddress'=>'Sender Email Address') 
				as $k=>$label) if(!$prefs[$k]) $missing[] = $label;
			if($missing) {
				$last = array_pop($missing);
				$missing = ($missing ? join(', ', $missing).' and/or ' : '').$last;
				$mods[] = "$missing may be required";
			}
		}
	}
	else {
		$status = "<u>LeashTime's SMTP Server</u>";
		if($prefs['emailUser'] || $prefs['emailPassword']) $mods[] =  'Email User Name and Password should not be supplied.';
		if($prefs['smtpPort']) $mods[] =  'SMTP Port should not be supplied.';
		if($prefs['smtpSecureConnection']) $mods[] =  'Secure Connection should not be supplied.';
	}
	if($mods) $status .= ': '.join(', ', $mods);
	return $status;
}


function flog($str) {
  global $flogMe;
  if(!$flogMe) return;
  $strm = fopen('debug.htm','a');
  fwrite($strm, $str);
  fwrite($strm, "<br>\n");
  fclose($strm);
}

function sendEmailFromLeashTimeSMTPServer($recipients, $subject, $body, $cc=null, $html=null, $senderLabel='', $bcc=null, $extraHeaders=null, $attachments=null) {
	// $toRecipients is array or CSV
	// make email sender fall back on $installationSettings;
	// WORKS IN $_SESSION ONLY	
	$keys = explode(',', 'emailHost,emailUser,emailPassword,smtpPort');
	foreach($keys as $k) {
		$savedPrefs[$k] = $_SESSION['preferences'][$k];
		unset($_SESSION['preferences'][$k]);
	}
	$returnVal = sendEmail($recipients, $subject, $body, $cc, $html, $senderLabel, $bcc, $extraHeaders, $attachments);
	foreach($keys as $k) $_SESSION['preferences'][$k] = $savedPrefs[$k];
}

function sendEmail($recipients, $subject, $body, $cc=null, $html=null, $senderLabel='', $bcc=null, $extraHeaders=null, $attachments=null) {
	// $toRecipients is array or CSV

if((FALSE && mattOnlyTEST())) {
global $flogMe,$installationSettings;$flogMe=1;
foreach(explode(',', 'emailHost,emailUser,emailPassword,smtpPort,smtpSecureConnection,emailFromAddress') as $k) $oot[] = "$k=>".mPrefSet($k);
flog('<b>'.date('Y-m-d H:i')."</b> ".join(', ', $oot));
}
	// return null on success or a string on failure	
	if(!$extraHeaders['Reply-to'] && mPrefSet('defaultReplyTo'))
		$extraHeaders['Reply-to'] = mPrefSet('defaultReplyTo');
	return sendEmailViaSMTPServer($recipients, $subject, $body, $cc, $html, $senderLabel, $bcc, $extraHeaders, $attachments);
}

function sendEmailViaSMTPServer($toRecipients, $subject, $body, $cc = null, $html=null, $senderLabel='', $bcc=null, $extraHeaders=null, $attachments=null) { // e.g., $senderLabel= 'Beth from Biz Name'
	// $toRecipients is array or CSV
	global $installationSettings, $suppressErrorLoggingOnce; // from common/db_fns.php
	$body	= word_wrap($body);					
	/*
	$host = mPrefSet('emailHost') ? mPrefSet('emailHost') : $installationSettings['smtphost'];
	if(FALSE && ($_SERVER['REMOTE_ADDR'] == 'X68.225.89.173' || in_array(strtolower($host), array('smtp.zoho.com'))))
		return sendEmailViaXPertMailerSMTPServer($toRecipients, $subject, $body, $cc, $html, $senderLabel, $bcc, $extraHeaders);
	else if(TRUE || $_SERVER['REMOTE_ADDR'] == '68.225.89.173' && in_array(strtolower($host), array('smtp.live.com', 'smtp.mail.yahoo.com'))) {
	*/
	require_once "email-swiftmailer-fns.php";
	return sendEmailViaSwiftMailerSMTPServer($toRecipients, $subject, $body, $cc, $html, $senderLabel, $bcc, $extraHeaders, $attachments);
	//}
	/*require_once "Mail.php";
	require_once('Mail/mime.php');
	
	$username = mPrefSet('emailUser') ? mPrefSet('emailUser') : $installationSettings['smtpuser'];
	$password = mPrefSet('emailPassword') ? mPrefSet('emailPassword') : $installationSettings['smtppassword'];
	$port = mPrefSet('smtpPort', 25);
	$auth = mPrefSet('smtpAuthentication', true) ? true : false;
	$smtpArgs = 	
		array ('host' => $host,
			'username' => $username,
			'password' => $password,
			'port' => $port,
			'auth' => $auth,
			'timeout'=>30
			);
	
	$from = firstNonEmpty(array(mPrefSet('emailFromAddress'), 'notice@leashtime.com')) ;
	$senderLabel = firstNonEmpty(array($senderLabel, mPrefSet('shortBizName'), mPrefSet('bizName'), "Notices from LeashTime"));
	
		$from = "$senderLabel <$from>";
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($smtpArgs); exit;}
		//$to = $toRecipients && is_array($toRecipients) ? join(', ', $toRecipients) : trim($toRecipients);
		$to = filterOutgoingEmailAddressesToString($toRecipients);
		//$cc = $cc && is_array($cc) ? join(', ', $cc) : $cc;
		$cc = filterOutgoingEmailAddressesToString($cc);
		//$bcc = $bcc && is_array($bcc) ? join(', ', $bcc) : $bcc;
		$bcc = filterOutgoingEmailAddressesToString($bcc);
		$globalBcc = mPrefSet('emailBCC') ? filterOutgoingEmailAddressesToString(mPrefSet('emailBCC')) : ''; //"notice@leashtime.com";
		if($globalBcc) $bcc = join(', ', array($globalBcc, $bcc));

	foreach(array($to, $cc, $bcc) as $x) 
		if(trim($x)) $recipients[] = $x;
	if($recipients) $recipients = join(', ', $recipients);
	
	$headers = array ('From' => prepareAddress($from),
										'Subject' => $subject);
	if($to) $headers['To'] = $to;
	if($bcc) $headers['Bcc'] = $bcc;
	if($cc) $headers['Cc'] = $cc;
	if($extraHeaders) foreach($extraHeaders as $k => $v) $headers[$k] = $v;
	
	
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "HTML: $html<p>headers: ".print_r($headers,1).'<p>recipients: '.print_r($recipients,1).'<p>';exit;}
	
	$smtp = Mail::factory('smtp', $smtpArgs);
	
	if($html) list($body, $headers) = mimeBodyAndHeaders($body, $headers);

	if($recipients) {  // all recipients may have been filtered out
		if(in_array('tblchangelog', fetchCol0("SHOW TABLES"))) logChange(99, 'send email', 'x', "toRecipients: [".print_r($toRecipients,1)."] To: [$to]");
		$mail = $smtp->send($recipients, $headers, $body);

		if (PEAR::isError($mail)) {
			$error = $mail->getMessage();
			if(!$suppressErrorLoggingOnce) logError(print_r($error, 1));
			$suppressErrorLoggingOnce = false;
			return $error;
		}
	}
	$suppressErrorLoggingOnce = false;
	return null;
	*/
}

function filterOutgoingEmailAddressesToString($addresses) {
	if(!$addresses) return $addresses;
	if(!is_array($addresses)) $addresses = array_map('trim', explode(',', $addresses));
	foreach($addresses as $address)
		if(strpos($address, 'X_OUT_') === FALSE)
			$out[] = $address;
	if($out) return join(',', $out);
}

function firstNonEmpty($options) {
	foreach($options as $option) if($option) return $option;
}

function boolean($val) { return $val ? true : false; }

function sendEmailViaXPertMailerSMTPServer($toRecipients, $subject, $body, $cc = null, $html=null, $senderLabel='', $bcc=null, $extraHeaders=null) { // e.g., $senderLabel= 'Beth from Biz Name'
	global $installationSettings, $suppressErrorLoggingOnce; // from common/db_fns.php
	
	define('DISPLAY_XPM4_ERRORS', true); // display XPM4 errors
	$path = explode(':', get_include_path());
	foreach($path as $dir) {
		if(strpos($dir, 'xpertmailer')) $alreadyDone = 1;
		else if(strpos($dir, '/var/www/') === 0)
			$home = $dir;
	}
	if(!$home) $home = '.';  // should not happen normally
	if(!$alreadyDone)
		set_include_path(get_include_path().":$home/xpertmailer");
	require_once 'MAIL.php'; // path to 'MAIL.php' file from XPM4 package
	//require_once 'SMTP.php'; // path to 'MAIL.php' file from XPM4 package
	//require_once 'MIME.php'; // path to 'MAIL.php' file from XPM4 package

	$from = firstNonEmpty(array(mPrefSet('emailFromAddress'), 'notice@leashtime.com')) ;
	$senderLabel = firstNonEmpty(array($senderLabel, mPrefSet('shortBizName'), mPrefSet('bizName'), "Notices from LeashTime"));
	
	$from = "$senderLabel <$from>";
								
	$host = mPrefSet('emailHost') ? mPrefSet('emailHost') : $installationSettings['smtphost'];
	$username = mPrefSet('emailUser') ? mPrefSet('emailUser') : $installationSettings['smtpuser'];
	$password = mPrefSet('emailPassword') ? mPrefSet('emailPassword') : $installationSettings['smtppassword'];
	$port = mPrefSet('smtpPort') ? mPrefSet('smtpPort') : 25;
	$auth = mPrefSet('smtpAuthentication') ? mPrefSet('smtpAuthentication') : true;
	$ssl = mPrefSet('smtpSecureConnection', null);
	if($ssl == 'no') $ssl = null;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "$host, $username, $port, $auth, $ssl"; exit;}

	$to = $toRecipients && is_array($toRecipients) ? $toRecipients : explode(',', $toRecipients);
	$cc = $cc && is_array($cc) ? join(', ', $cc) : $cc;
	if($bcc) $bcc = is_array($bcc) ? $bcc : explode(',', $bcc);
	$globalBcc = mPrefSet('emailBCC') ? mPrefSet('emailBCC') : ''; //"notice@leashtime.com";
	if($globalBcc) {
		foreach(explode(',', $globalBcc) as $addr) $bcc[] = $addr;
	}
	if($bcc) $bcc = join(', ', $bcc);
	
	define('DISPLAY_XPM4_ERRORS', true); // display XPM4 errors
	
	set_error_handler('xpertmailerErrorHandler', $error_types = E_ALL);
	
	// connect to 'destination.tld' MX zone
	// string ssl possible values are: tls, ssl, sslv2 or sslv3
	//$conn = SMTP::connect ($host, (integer)$port, $username, $password, $ssl, (integer)($timeout=30)); //  [, string name [, resource context [, string auth_type ]]]]]]]] 
	$mail = new MAIL;
	
	$conn = $mail->connect($host, (integer)$port, $username, $password, $ssl, (integer)($timeout=30)); //  [, string name [, resource context [, string auth_type ]]]]]]]] 
	
	if($conn) {
		//, $recipients, $message, $headers['From']
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r(addressList($to)); exit;}		
		foreach(addressList($to) as $parts) $mail->addTo($parts[0], $parts[1]);
		foreach(addressList($cc) as $parts) $mail->addCc($parts[0], $parts[1]);
		foreach(addressList($bcc) as $parts) $mail->addBcc($parts[0], $parts[1]);
		if($html) $mail->html($body);
		else $mail->text($body);
		$mail->subject($subject);
		$from = addressParts($from);
		$mail->from($from[0], $from[1]);
		if($extraHeaders) foreach($extraHeaders as $k => $v) $mail->AddHeader($k, $v);
;
		// send mail
//echo "toRecipients: [".print_r($toRecipients,1)."]	[".print_r($recipients,1)."]	From: [{$headers['From']}]<p>";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($mail);echo "<p>";}		
		$error = !$mail->send($conn);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($mail);echo "<p>";exit;}		
	}
	
	else {
		$error = 1;
		$conectionError = "BadCustomSMTPSettings";
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "[[".print_r($recipients, 1)."]]"; }
	if ($error) {
		$error = join(', ', $mail->Result);
		if($conectionError) $error = "Failed to connect: $error";
		if(!$suppressErrorLoggingOnce) logError($error);		
		$suppressErrorLoggingOnce = false;
		return $reportedError ? $reportedError : $error;
	} 
	$suppressErrorLoggingOnce = false;
	return null;
}

function getInBox() {
	global $installationSettings, $suppressErrorLoggingOnce; // from common/db_fns.php
	
	define('DISPLAY_XPM4_ERRORS', true); // display XPM4 errors
	$path = explode(':', get_include_path());
	foreach($path as $dir) {
		if(strpos($dir, 'xpertmailer')) $alreadyDone = 1;
		else if(strpos($dir, '/var/www/') === 0)
			$home = $dir;
	}
	if(!$alreadyDone)
		set_include_path(get_include_path().":$home/xpertmailer");
	require_once 'POP3.php'; // path to 'MAIL.php' file from XPM4 package

	$host = mPrefSet('inboxHost') ? mPrefSet('inboxHost') : $installationSettings['inboxHost'];
	$username = mPrefSet('inboxUserName') ? mPrefSet('inboxUserName') : $installationSettings['inboxUserName'];
	$password = mPrefSet('inboxPassword') ? mPrefSet('inboxPassword') : $installationSettings['inboxPassword'];
	$port = mPrefSet('inboxPort') ? mPrefSet('inboxPort') : 25;
	$ssl = mPrefSet('inboxSSL', null);
	if($ssl == 'no') $ssl = null;
	
	set_error_handler('xpertmailerErrorHandler', $error_types = E_ALL);
	
	// connect to 'destination.tld' MX zone
	// string ssl possible values are: tls, ssl, sslv2 or sslv3
	//$conn = SMTP::connect ($host, (integer)$port, $username, $password, $ssl, (integer)($timeout=30)); //  [, string name [, resource context [, string auth_type ]]]]]]]] 
foreach($_SESSION['preferences'] as $k=>$v) if(strpos($k, 'inb') === 0) echo "$k: $v<br>";	
	
	print_r( array($host, $username, $password, (integer)$port, $ssl, (integer)($timeout=30)));
	$conn = POP3::Connect($host, $username, $password, (integer)$port, $ssl, (integer)($timeout=30)); //  [, string name [, resource context [, string auth_type ]]]]]]]] 
	$list = POP3::pList($conn) or die(print_r($_RESULT));
	echo "<p>Found: ".count($list)." messages in Inbox.<p>";
	for($i=0;$i<3;$i++) {
		if(isset($list[$i])) {
			$msgId = $list[$i];
			echo "Message: $i [$msgId]<p>";
			$r = POP3::pRetr($conn, 1) or die(print_r($_RESULT));
			print_r($r); 
			echo "<hr>";
		}
	}
	
}

function xpertmailerErrorHandler($errno, $errstr, $errfile, $errline) {
		if(in_array($errno, array(E_WARNING || E_NOTICE))) return true;
		logError("xpertmailer error: [$errno] $errstr [$errfile: $errline]");
		return true;  // done with handling error.  Let process continue.
}

function mimeBodyAndHeaders($body, $headers) {
	$mime = new Mail_mime("\n"); // "\n" arg is necessary
	$mime->_build_params['html_encoding'] = '7bit';
	$mime->setHTMLBody($body);
	$txtBody = str_replace("<p>","\r\n\r\n", $body);
	$txtBody = str_replace("<br>","\r\n", $txtBody);	
	$mime->setTXTBody(strip_tags($txtBody));
	return array($mime->get(), $mime->headers($headers)); // get() must be called before headers()
}

function addressList($addresses) { // [[email,label], [email,label]...]
	$newAdds = array();
	if(!$addresses) return $newAdds;
	if(!is_array($addresses)) $addresses = explode(',', $addresses);
	foreach((array)$addresses as $addr)
		$newAdds[] = addressParts($addr);
	return $newAdds;
}

function addressParts($addr) {
	if(strpos($addr, '<')) {
		$parts[0] = trim(substr($addr, strpos($addr, '<')+1, strpos($addr, '>')-(strpos($addr, '<')+1)));
		$parts[1] = trim(substr($addr, 0, strpos($addr, '<')));
		if(strpos($parts[1], '"') !== FALSE) {
			$parts[1] = trim(substr($parts[1], strpos($parts[1], '"')+1, strrpos($parts[1], '"')-(strpos($parts[1], '"')+1)));
		}
	}
	else $parts = array(trim($addr), '');
	return $parts;
}

function prepareAddress($add) {
	//if(strpos($add, '<') === false) return "<$add>";
	return $add;
}

function mPrefSet($key, $default=null) { 
	global $scriptPrefs, $installationSettings;
	return isset($_SESSION["preferences"][$key]) ? $_SESSION["preferences"][$key] 
	       : (isset($scriptPrefs[$key]) ? $scriptPrefs[$key] 
	       : (isset($installationSettings[$key]) ? $installationSettings[$key] : $default) ); 
}

function mailMerge($message, $values) {
	if($message)
		foreach($values as $key => $sub) 
			if($key) $message = str_replace($key, $sub, $message);
	return $message;
}

function htmlToPlainText($text) {
	$text =  str_replace('<p>', "\n\n", $text);
	$text =  str_replace('<br>', "\n", $text);
	return $text;
}

function plainTextToHtml($text) {
	$text =  str_replace("\n\n", '<p>', $text);
	$text =  str_replace("\n", '<br>', $text);
	return $text;
}

function dangerousAttachmentFileExtensions() {
	return explodePairPerLine(
	"ADE|Microsoft Access Project Extension 
	ADP|Microsoft Access Project 
	BAS|Visual Basic Class Module 
	BAT|Batch File 
	CHM|Compiled HTML Help File 
	CMD|Windows NT Command Script 
	COM|MS-DOS Application 
	CPL|Control Panel Extension 
	CRT|Security Certificate 
	EXE|Application 
	HLP|Windows Help File 
	HTA|HTML Applications 
	INF|Setup Information File 
	INS|Internet Communication Settings 
	ISP|Internet Communication Settings 
	JS|JScript File 
	JSE|JScript Encoded Script File 
	LNK|Shortcut 
	MDB|Microsoft Access Application
	MDE|Microsoft Access MDE Database
	MSC|Microsoft Common Console Document
	MSI|Windows Installer Package
	MSP|Windows Installer Patch
	MST|Visual Test Source File
	PCD|Photo CD Image
	PIF|Shortcut to MS-DOS Program
	REG|Registration Entries
	SCR|Screen Saver
	SCT|Windows Script Component
	SHS|Shell Scrap Object
	URL|Internet Shortcut (Uniform Resource Locator)
	VB|VBScript File
	VBE|VBScript Encoded Script File
	VBS|VBScript Script File
	WSC|Windows Script Component
	WSF|Windows Script File
	WSH|Windows Scripting Host Settings File");
}

################################################################################
/* word_wrap($string, $cols, $prefix)
 *
 * Takes $string, and wraps it on a per-word boundary (does not clip
 * words UNLESS the word is more than $cols long), no more than $cols per
 * line. Allows for optional prefix string for each line. (Was written to
 * easily format replies to e-mails, prefixing each line with "> ".
 *
 * Copyright 1999 Dominic J. Eidson, use as you wish, but give credit
 * where credit due.
 */
function word_wrap($string, $cols = 900, $prefix = "") {

	$t_lines = split( "\n", $string);
        $outlines = "";

	while(list(, $thisline) = each($t_lines)) {
	    if(strlen($thisline) > $cols) {

		$newline = "";
		$t_l_lines = split(" ", $thisline);

		while(list(, $thisword) = each($t_l_lines)) {
		    while((strlen($thisword) + strlen($prefix)) > $cols) {
			$cur_pos = 0;
			$outlines .= $prefix;

			for($num=0; $num < $cols-1; $num++) {
			    $outlines .= $thisword[$num];
			    $cur_pos++;
			}

			$outlines .= "\n";
			$thisword = substr($thisword, $cur_pos, (strlen($thisword)-$cur_pos));
		    }

		    if((strlen($newline) + strlen($thisword)) > $cols) {
			$outlines .= $prefix.$newline."\n";
			$newline = $thisword." ";
		    } else {
			$newline .= $thisword." ";
		    }
		}

		$outlines .= $prefix.$newline."\n";
	    } else {
		$outlines .= $prefix.$thisline."\n";
	    }
	}
	return $outlines;
}
