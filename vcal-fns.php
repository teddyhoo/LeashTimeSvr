<?  // vcal-fns.php
// SEE ALSO THE MORE COMPLETE icalendar-fns.php

function dumpICalendarContent($items, $filename=null, $disposition='attachment') {
	// $filename *.vcs
	// $disposition=inline or attachment
	$filename = $filename ? "; filename=$filename" : '';
	header("Content-Type: text/x-iCalendar");
	header("Content-Disposition: $disposition $filename");
	dumpICalendar($items);
}
	

function dumpICalendar($items) {
	$eoln = "\r\n";
	echo "BEGIN:VCALENDAR$eoln"."VERSION:2.0$eoln";
	echo "PRODID:LeashTime$eoln";
	foreach($items as $item) dumpVEvent($item);
	echo "END:VCALENDAR$eoln";
}


function dumpVEvent($item, $cancel=false) {
	static $serviceTypes;
	$eoln = "\r\n";
	global $db;
	echo "BEGIN:VEVENT$eoln";
	echo "DTSTAMP:".iCalDate(date('Y-m-d H:i:s')).$eoln;
	if($item['appointmentid']) echo "UID:$db/{$item['appointmentid']}$eoln";
	if($item['servicecode']) {
		require_once "service-fns.php";
		if(!$serviceTypes) 
			$serviceTypes = getAllServiceNamesById($refresh=0, $noInactiveLabel=true, $setGlobalVar=false);
		echo "SUMMARY:{$serviceTypes[$item['servicecode']]} ({$item['pets']})$eoln";
	}
	if($item['note']) echo "DESCRIPTION;ENCODING=QUOTED-PRINTABLE:".iCalWrap($item['note'])."$eoln";
	$date = $item['date'];
	if($item['starttime'])
		echo "DTSTART:".iCalDate("$date {$item['starttime']}").$eoln;
	if($item['endtime']) {
		if(strcmp($item['endtime'], $item['starttimetime']) < 0)
			$date = date("+1 day", strtotime($date)); // <== wrong!
		echo "DTEND:".iCalDate("$date {$item['endtime']}").$eoln;
	}
	if($cancel) echo "METHOD:CANCEL$eoln";
	if($item['providerptr']) {
		$provider = fetchFirstAssoc("SELECT CONCAT_WS(' ', fname, lname) as name, email FROM tblprovider WHERE providerid = {$item['providerptr']}", 1);
		echo "ATTENDEE;ROLE=Sitter;CN={$provider['name']}:{$provider['email']}".$eoln;
	}
	echo "END:VEVENT$eoln";
}

function iCalDate($datetime) {
	$time = strtotime($datetime);
	return gmdate('Ymd', $time).'T'.gmdate('His', $time).'Z';
}

function iCalWrap($str) {
	return str_replace("\r", "=0D=0A=", str_replace("\n", "=0D=0A=", $str));
}


//DTSTART:19970714T170000Z
//DTEND:19970715T035959Z
//GEO:lat;lon
//METHOD:
//ATTENDEE;CN=John Smith:mailto:jimdo@example.com
//METHOD:CANCEL
//STATUS:CANCELLED
