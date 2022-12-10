<? // twiliosays.php
// this must be hosted somewhere other than LeashTime, since it is
// useful ONLY when LT is unresponsive.
// if the stethoscope host is ever made public, it can be placed there

if($_REQUEST['say']) {
	header('Content-Type: text/xml');
	echo str_replace('XXX', $_REQUEST['say'],
		'<?xml version="1.0" encoding="UTF-8"?>'
		.'<Response><Say voice="alice">Leash Times server is not responding.  XXX. Leash Times server is not responding.  XXX</Say></Response>');
		// .'<Play>http://demo.twilio.com/docs/classic.mp3</Play>';
//
	exit;
}