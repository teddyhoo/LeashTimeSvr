<? //wipe-all-visits-bills-and-payroll.php

$target = 'fetchdummyXXX';

require_once "common/init_session.php";
include "common/init_db_petbiz.php";

locked('o-');
if(!$_SESSION['staffuser']) {
	echo "Must be staff";
	exit;
}

if($db != $target) {
	echo "Must be logged in to $target";
	exit;
}

$tables = explode("\n",
"relapptdiscount
relbillablepayment
relinvoicecredit
relinvoiceitem
relpastdueinvoice
relproviderpayablepayment
relrefundcredit
relservicediscount
tblappointment
tblbillable
tblchangelog
tblclientprofilerequest
tblclientrequest
tblcredit
tblgratuity
tblinvoice
tblnegativecomp
tblothercharge
tblothercomp
tblpayable
tblpayment
tblproviderpayment
tblrecurringpackage
tblrefund
tblservice
tblservicepackage
tblsurcharge");

foreach($tables as $table) doQuery("TRUNCATE TABLE $table");