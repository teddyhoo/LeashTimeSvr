<? //surcharge-delete.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "invoice-fns.php";
require_once "surcharge-fns.php";
locked('o-');

if($_SESSION['surchargesenabled'] && $_GET['id']) {
	$ids = explode(',', $_GET['id']);
	foreach($ids as $id)
  	dropSurcharges($id);
}

echo "done!";

