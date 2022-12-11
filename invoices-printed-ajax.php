<? //invoices-printed-ajax.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
locked('o-');
extract($_REQUEST);

if($ids) {
	updateTable('tblinvoice', array('notification'=>'mail', 'lastsent'=>date('Y-m-d')), "invoiceid IN ($ids)", 1);
  //echo mysqli_error();
}

