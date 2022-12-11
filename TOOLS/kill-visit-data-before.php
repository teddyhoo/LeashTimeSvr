<? //kill-visit-data-before.php?date=&invoicedate=
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

extract($_GET);
if(!$date) {
	echo "No Date";
	exit;
}
if(!$invoicedate) $invoicedate = $date;
$invoiceids = join(',', fetchCol0("SELECT invoiceid FROM tblinvoice WHERE date < '$invoicedate'", 1));
$payableids = join(',', fetchCol0("SELECT payableid FROM tblpayable WHERE date < '$date'", 1));
$creditids = join(',', fetchCol0("SELECT creditid FROM tblcredit WHERE issuedate < '$date'", 1));
$appointmentids = join(',', fetchCol0("SELECT appointmentid FROM tblappointment WHERE date < '$date'", 1));
$provpaymentids = join(',', fetchCol0("SELECT paymentid FROM tblproviderpayment WHERE paymentdate < '$date'", 1));

//relproviderpayablepayment > providerpaymentptr
if($invoiceids) {
	doQuery("DELETE FROM relinvoicecan WHERE invoiceptr IN ($invoiceids)", 1);
	doQuery("DELETE FROM relinvoicecredit WHERE invoiceptr IN ($invoiceids)", 1);
	doQuery("DELETE FROM relinvoiceitem WHERE invoiceptr IN ($invoiceids)", 1);
	doQuery("DELETE FROM relpastdueinvoice WHERE oldinvoiceptr IN ($invoiceids)", 1);
	doQuery("DELETE FROM tblinvoice WHERE invoiceid IN ($invoiceids)", 1);
}

if($payableids) {
	doQuery("DELETE FROM tblpayable WHERE payableid IN ($payableids)", 1);
	doQuery("DELETE FROM relproviderpayablepayment WHERE payableptr IN ($payableids)", 1);
}


if($creditids) {
	doQuery("DELETE FROM tblcredit WHERE creditid IN ($creditids)", 1);
	doQuery("DELETE FROM relrefundcredit WHERE creditptr IN ($creditids)", 1);
	doQuery("DELETE FROM relbillablepayment WHERE paymentptr IN ($creditids)", 1);
}

if($appointmentids) {
	doQuery("DELETE FROM tblappointment WHERE appointmentid IN ($appointmentids)", 1);
	doQuery("DELETE FROM tblothercomp WHERE appointmentptr IN ($appointmentids)", 1);
	doQuery("DELETE FROM relapptdiscount WHERE appointmentptr IN ($appointmentids)", 1);
}

if($provpaymentids) {
	doQuery("DELETE FROM relproviderpayablepayment WHERE providerpaymentptr IN ($provpaymentids)", 1);
}

doQuery("DELETE FROM tblbillable WHERE itemdate < '$date'", 1);
doQuery("DELETE FROM tblproviderpayment WHERE paymentdate < '$date'", 1);
doQuery("DELETE FROM tblgratuity WHERE issuedate < '$date'", 1);
doQuery("DELETE FROM tblnegativecomp WHERE date < '$date'", 1);
doQuery("DELETE FROM tblothercharge WHERE issuedate < '$date'", 1);
doQuery("DELETE FROM tblsurcharge WHERE date < '$date'", 1);
doQuery("DELETE FROM tblrefund WHERE issuedate < '$date'", 1);

doQuery("DELETE FROM tblservicepackage 
	WHERE (onedaypackage = 0 AND enddate < '$date')
		OR (onedaypackage = 1 AND startdate < '$date')", 1);

/*
Paid in prepayments

Petaholics appointments, billables, payables, invoices, invoiceitems before 2/15/2010


DELETE FROM petaholics.tblappointment WHERE date < '2010-02-15';
DELETE FROM petaholics.tblbillable WHERE itemdate < '2010-02-15';
DELETE FROM petaholics.tblpayable WHERE date < '2010-02-15';
DELETE FROM petaholics.tblinvoice WHERE date < '2010-02-15';

DELETE FROM tblappointments

DELETE FROM tblinvoice
*/