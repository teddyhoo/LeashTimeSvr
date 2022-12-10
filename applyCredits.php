<? // applyCredits.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "invoice-fns.php";

if(!$_REQUEST['id']) {
	echo "No client ID supplied.";
	exit;
}

payOffClientBillables($_REQUEST['id']);