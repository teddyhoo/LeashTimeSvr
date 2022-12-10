<? // confirmation-resend-ajax.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "confirmation-fns.php";
require_once "comm-fns.php";

$locked = locked('o-');

$result = resendConfirmationRequest($_REQUEST['id'], false);

if(is_string($result)) echo $result; // error
