<? // cc-login-needed-ajax.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "cc-processing-fns.php";

// if cc login is needed, return "1".  Allow caller to call back to this file to log the user in.  Return "2" on failure.

locked('+*cm,+*cc');

if($_REQUEST['p']) { // try to login
	ccLogin($_REQUEST['p']);
}
if(is_array(expireCCAuthorization())) echo $_REQUEST['p'] ? "2" : "1";
