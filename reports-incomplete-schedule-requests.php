<? // reports-incomplete-schedule-requests.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
//require_once "provider-fns.php";
require_once "client-sched-request-fns.php";

$locked = locked('o-');

if($inks = findIncompleteScheduleRequestClients($returnAll=1)) { // $returnAll=FALSE means: just look for yesterday's crop
	// $inks: $clientptr => $time
	$notification = notificationTextForIncompleteScheduleRequests($inks);
}
include "frame.html";
echo $notification;
include "frame-end.html";
