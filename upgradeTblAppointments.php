<?
/* upgradeTblAppointments.php
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$servs = join(',',fetchCol0("SELECT serviceid FROM tblservice WHERE recurring= 1"));
doQuery("UPDATE tblappointment SET recurringpackage=1 WHERE serviceptr IN ($servs)");
