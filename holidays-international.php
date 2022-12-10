<? // holidays-international.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";

if(!$_REQUEST['year']) { echo "Need to specify year."; exit;}

require_once "holidays-future.php";

if(findHolidayProblems($_REQUEST['year'])) exit;

holidayTables($_REQUEST['year']);