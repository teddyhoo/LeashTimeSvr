<? // icalendar-fns.php
// see also vcal-fns.php
// from https://gist.github.com/jakebellacera/635416
// Variables used in this script:
// $summary - text title of the event
// $datestart - the starting date (in seconds since unix epoch)
// $dateend - the ending date (in seconds since unix epoch)
// $address - the event's address
// $uri - the URL of the event (add http://)
// $description - text description of the event
// $filename - the name of this file for saving (e.g. my-event-name.ics)
//
// Notes:
// - the UID should be unique to the event, so in this case I'm just using
// uniqid to create a uid, but you could do whatever you'd like.
//
// - iCal requires a date format of "yyyymmddThhiissZ". The "T" and "Z"
// characters are not placeholders, just plain ol' characters. The "T"
// character acts as a delimeter between the date (yyyymmdd) and the time
// (hhiiss), and the "Z" states that the date is in UTC time. Note that if
// you don't want to use UTC time, you must prepend your date-time values
// with a TZID property. See RFC 5545 section 3.3.5
//
// - The Content-Disposition: attachment; header tells the browser to save/open
// the file. The filename param sets the name of the file, so you could set
// it as "my-event-name.ics" or something similar.
//
// - Read up on RFC 5545, the iCalendar specification. There is a lot of helpful
// info in there, such as formatting rules. There are also many more options
// to set, including alarms, invitees, busy status, etc.
//
// https://www.ietf.org/rfc/rfc5545.txt
 
// 1. Set the correct headers for this file
//header('Content-type: text/calendar; charset=utf-8');
//header('Content-Disposition: attachment; filename=' . $filename);
 
// 2. Define helper functions
 
// Converts a unix timestamp to an ics-friendly format
// NOTE: "Z" means that this timestamp is a UTC timestamp. If you need
// to set a locale, remove the "\Z" and modify DTEND, DTSTAMP and DTSTART
// with TZID properties (see RFC 5545 section 3.3.5 for info)
//
// Also note that we are using "H" instead of "g" because iCalendar's Time format
// requires 24-hour time (see RFC 5545 section 3.3.12 for info).
function dateToCal($timestamp) {
return date('Ymd\THis\Z', $timestamp);
}
 
// Escapes a string of characters
function escapeString($string) {
return preg_replace('/([\,;])/','\\\$1', $string);
}

function iCalWrap($str) {
	return str_replace("\r", "=0D=0A=", str_replace("\n", "=0D=0A=", escapeString($str)));
}


function dumpCalendar($events) {
	echo <<<CALENDARSTART
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//hacksw/handcal//NONSGML v1.0//EN
CALSCALE:GREGORIAN
CALENDARSTART;
	foreach($events as $event) echoEvent($event);
	echo "END:VCALENDAR";
}

function echoEvent($event) {
	$dateend = dateToCal(strtotime($event['eventend']));
	$datestart = dateToCal(strtotime($event['eventstart']));
	$uniqid = uniqid();
	$timestamp = dateToCal(time());
	$address = iCalWrap($event['address']);
	$description = iCalWrap($event['description']);
	$url = iCalWrap('https://leashtime.com');
	$summary = iCalWrap($event['note']);
	echo <<<EVENT
BEGIN:VEVENT
DTEND:$dateend
UID:$uniqid
DTSTAMP:$timestamp
LOCATION:$address
DESCRIPTION:$description
URL;VALUE=URI:$url
SUMMARY:$summary
DTSTART:$datestart
END:VEVENT

EVENT;
}

function eventFromAppt($appt) {
	static $serviceTypes;
	if($appt['servicecode']) {
		require_once "service-fns.php";
		if(!$serviceTypes) 
			$serviceTypes = getAllServiceNamesById($refresh=0, $noInactiveLabel=true, $setGlobalVar=false);
			
		$pets = $item['pets'];
		if($pets == 'All Pets') {
			$pets = fetchCol0("SELECT name FROM tblpet WHERE ownerptr = '{$item['clientptr']}' AND active = 1 ORDER BY name");
			$pets = $pets ? join(',', $pets) : 'All Pets';
		}
		if($item['canceled']) $status = 'CANCELED: ';
		$appt['description'] = "$status{$serviceTypes[$item['servicecode']]} ($pets)";
		$appt['description'] = "$status{$serviceTypes[$item['servicecode']]} ($pets)";
	}
	$appt['eventstart'] = $appt['date'].' '.$appt['starttime'];
	$dateInt = strtotime($appt['date']);
	$endtime = substr($appt['timeofday'], strpos($appt['timeofday'], '-')+1);
	if(strtotime($endtime) < strtotime($appt['starttime']))
		$endtime = strtotime(date('Y-m-d', strtotime("+ 1 day", $dateInt))." $endtime");
	else $endtime = strtotime(date('Y-m-d', $dateInt)." $endtime");

	$appt['eventend'] = date('Y-m-d H:i:s', $endtime);
	
	return $appt;
	
}

function eventFromTimeOff($timeoff) {}

function events($apptsAndTimesOff) {
	$events = array();
	foreach($apptsAndTimesOff as $obj) {
		if($obj['appointmentid']) $events[] = eventFromAppt($obj);
		else $events[] = eventFromTimeOff($obj);
	}
	return $events;
}

function dumpICalendarPage($apptsAndTimesOff) {
	header('Content-type: text/calendar; charset=utf-8');
	header('Content-Disposition: attachment; filename=' . $filename);
	dumpCalendar(events($apptsAndTimesOff));
}