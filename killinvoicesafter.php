<? // killinvoicesafter.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

extract($_GET);

if(!$date) {echo "NO DATE";exit;}

$invoices = fetchCol0("SELECT invoiceid FROM tblinvoice WHERE asofdate >= '$date'");
echo "INVOICES [".count($invoices)."]: ".join(',',$invoices).'<p>';

$billables = fetchCol0("SELECT billableptr FROM relinvoiceitem WHERE invoiceptr IN (".join(',',$invoices).')');
echo "BILLABLES [".count($billables)."]: ".join(',',$billables).'<p>';

$appointments = fetchCol0("SELECT itemptr FROM tblbillable WHERE billableid IN (".join(',',$billables).") AND itemtable = 'tblappointment'");
echo "APPOINTMENTS [".count($appointments)."]: ".join(',',$appointments).'<p>';

$packages = fetchCol0("SELECT itemptr FROM tblbillable WHERE billableid IN (".join(',',$billables).") AND itemtable = 'tblrecurringpackage'");
echo "PACKAGES [".count($packages)."]: ".join(',',$packages).'<p>';

$payments = fetchCol0("SELECT DISTINCT paymentptr FROM relbillablepayment WHERE billableptr IN (".join(',',$billables).')');
echo "BILLABLE PAYMENTS [".count($payments)."]: ".join(',',$payments).'<p>';

$billableDecrements = array();
foreach(fetchAssociations("SELECT * FROM relbillablepayment WHERE billableptr IN (".join(',',$billables).')') as $payment)
	$billableDecrements[$payment['billableptr']] += $payment['amount'];
echo "BILLABLE DECREMENTS [".count($billableDecrements).'] '.print_r($billableDecrements, 1).'<p>';
	

// PROVIDERS

if($appointments) $payables = fetchCol0("SELECT payableid FROM tblpayable WHERE itemptr IN (".join(',',$appointments).") AND itemtable = 'tblappointment'");
if($packages) $payables = array_merge($payables,
				fetchCol0("SELECT payableid FROM tblpayable WHERE itemptr IN (".join(',',$packages).") AND itemtable = 'tblrecurringpackage'"));
echo "PAYABLES [".count($payables)."]: ".join(',',$payables).'<p>';



$providerpayments = fetchCol0("SELECT DISTINCT providerpaymentptr FROM relproviderpayablepayment WHERE payableptr IN (".join(',',$payables).')');
echo "PROVIDER PAYMENTS [".count($providerpayments)."]: ".join(',',$providerpayments).'<p>';


//exit;

//CLIENT
doQuery("DELETE FROM tblinvoice WHERE invoiceid IN (".join(',',$invoices).')');
echo mysql_affected_rows()." rows deleted from tblinvoice<p>";

doQuery("DELETE FROM relinvoiceitem WHERE invoiceptr IN (".join(',',$invoices).')');
echo mysql_affected_rows()." rows deleted from relinvoiceitem<p>";

doQuery("DELETE FROM relinvoicecredit WHERE invoiceptr IN (".join(',',$invoices).')');
echo mysql_affected_rows()." rows deleted from relinvoicecredit<p>";

doQuery("DELETE FROM relpastdueinvoice WHERE  currinvoiceptr IN (".join(',',$invoices).')');
echo mysql_affected_rows()." rows deleted from relpastdueinvoice<p>";

doQuery("DELETE FROM relbillablepayment WHERE billableptr IN (".join(',',$billables).')');
echo mysql_affected_rows()." rows deleted from relbillablepayment<p>";

foreach($billableDecrements as $billableptr => $decrement)
	doQuery("UPDATE tblbillable set paid = paid - $decrement WHERE billableid = $billableptr");
	
//PROVIDER
if($providerpayments) doQuery("DELETE FROM tblproviderpayment WHERE paymentid IN (".join(',',$providerpayments).')');
echo mysql_affected_rows()." rows deleted from tblproviderpayment<p>";

if($payables) doQuery("UPDATE tblpayable SET paid = '0.0' WHERE payableid IN (".join(',',$payables).')');
echo mysql_affected_rows()." rows deleted from tblpayable<p>";


