<?
/* service-repeating.php
*
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
if($_SESSION['preferences']['enableMultiWeekRecurring'] /*dbTEST('dogslife') */ && !$_REQUEST['original']) 
	require_once "service-repeatingV2.php";
else require_once "service-repeatingORIGINAL.php";;

