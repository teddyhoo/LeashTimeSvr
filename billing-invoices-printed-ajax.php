<? //billing-invoices-printed-ajax.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "comm-fns.php";
require_once "billing-fns.php";
locked('o-');
extract($_REQUEST);

if($ids) {
	$ids = explode(',', $_REQUEST['ids']);
	foreach($ids as $id) {
		$msg = array('transcribed'=>'mail', 'correspid'=>$id, 'correstable'=>'tblclient', 'subject'=>$standardMessageSubject,
									'body'=>getBillingInvoiceContents($id, $firstDay, $lookahead, $literal, false), 'mgrname' => getUsersFromName());
		$msg['mgrname'] = getUsersFromName();
		logOutgoingMessage($msg);
	}
}

