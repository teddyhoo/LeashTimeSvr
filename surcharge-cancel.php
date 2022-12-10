<? //surcharge-cancel.php
// id may be one id or id1,id2,...
// callers: calendar-package-irregular.php, client-edit.php, homepage_owner.php, prov-own-schedule-list.php
//          prov-schedule-list.php, wag.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "surcharge-fns.php";
require_once "invoice-fns.php";
require_once "provider-memo-fns.php";
locked('o-');
$cancel = $_GET['cancel'] ? date('Y-m-d H:i:s') : null;

$cancellation = withModificationFields(array('canceled'=>$cancel, 'completed'=>null));
if(!$_GET['id']) {
	logError("No ID: ".$_SERVER["REQUEST_URI"]);
	exit;
}
updateTable('tblsurcharge', $cancellation, "surchargeid IN ({$_GET['id']})", 1);
foreach(explode(',', $_GET['id']) as $id)
	logChange($id, 'tblsurcharge', 'm', $note=($cancel ? 'canceled' : 'uncanceled'));
// undo billable - surcharge is now either canceled or uncompleted

$ids = explode(',', $_GET['id']);
foreach($ids as $id) {
	supersedeSurchargeBillable($id);
	$surcharge = getSurcharge($id, false, true, true);
	//logAppointmentStatusChange($appt, 'cancel/uncancel button');
	if(!((int)($surcharge['billpaid'] + $surcharge['providerpaid']))) {
		if($surcharge['payableid']) deleteTable('tblpayable', "payableid = {$surcharge['payableid']}", 1);
	}
	//makeClientVisitStatusChangeMemo($appt['providerptr'], $appt['clientptr'], $id, $cancel);
	// echo providers 
	$sections = array($surcharge['providerptr'] ? $surcharge['providerptr'] : '0');
	echo join(',', $sections);
}
