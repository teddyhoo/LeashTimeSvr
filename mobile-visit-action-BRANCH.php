<? // mobile-visit-action-BRANCH.php
/* mobile-visit-action.php (copied from client-request-appointment.php)
*
* Parameters: 
* id - id of appointment to be edited
*/

require_once "common/init_session.php";
include "common/init_db_petbiz.php";

if(FALSE) require_once "mobile-visit-action-NEW.php";
else require_once "mobile-visit-action-OLD.php";
