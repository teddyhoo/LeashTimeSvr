<? //temptemp.php
// ids may be one id or id1,id2,...
// callers: calendar-package-irregular.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-fns.php";

locked('o-');

if($db !== 'fetch210') {echo "must be in N. ARl.";exit;}

$appts = array(13376,13377,13378,13379,13380,13388,13389,13390,13395);

//foreach($appts as $appt) recreateAppointmentBillable($appt);