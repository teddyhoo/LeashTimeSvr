<?
/* service-monthly.php
*
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
if($_SESSION['preferences']['enableMultiWeekRecurring'] /*dbTEST('dogslife') */ && !$_REQUEST['original']) require_once "service-monthlyV2.php";
else require_once "service-monthlyORIGINAL.php";;

