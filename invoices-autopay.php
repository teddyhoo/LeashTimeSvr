<? // invoices-autopay.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

//if($db == 'leashtimecustomers' || $db == 'runningdogtestdb' || $db == 'themonsterminders' || $_SERVER['REMOTE_ADDR'] == '68.225.89.173') include "invoices-autopayNEW.php";
//else include "invoices-autopayOLD.php";

// OPEN TO ALL!
include "invoices-autopayNEW.php";