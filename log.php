<?php
	function serverlog($str, $func_name,$src_file) {
		$date = date('m-d-y h:i:s');
	    $strm = fopen('./logtest/debug.htm','a');
	    fwrite($strm, "<UL>\n");
	    fwrite($strm,"\t<LI>".$date . "\n");
	    fwrite($strm,"\t<LI>".$src_file . "\n");
	    fwrite($strm,"\t<LI>".$func_name . "\n");
	    foreach ($str as $key => $val) {
	    	fwrite($strm, "\t<LI>". $key . "  =>  ". $val."\n");
	    }
	    fwrite($strm, "<\UL>\n");
	    fwrite($strm, "<br>\n");
	    fclose($strm);
	 }

	 function writeResponse($status) {
		echo "<HTML>\n";
		echo "<HEAD>Test log entry</HEAD>\n";
		echo "<BODY>\n";
		echo "<DIV>\n";
		echo "<P>Test log entry successful, but you should check anyway\n";
	 }

	/*$toRecipients = "ted@leashtime.com";
	$subject = "SUBJECT";
	$body = "BODY";
	$cc = null;
	$html = false;
	$senderLabel = '  ';
	$bcc  = null;
	$extraHeaders = null;
	$attachments = null;

	$testMessage = array(
		"recipients" => $toRecipients,
		"subject" => $subject,
		"body" => $body,
		"cc" => $cc,
		"html" => $html,
		"senderLabel" => $senderLabel,
		"bcc" => $bcc,
		"extraHeaders" => $extraHeaders,
		"attachments" => $attachments
	);
	serverlog($testMessage, "function funcy", "source.php");
	writeResponse('GOOD');*/

?>