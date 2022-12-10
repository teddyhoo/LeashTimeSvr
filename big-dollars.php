<? // big-dollars.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$locked = locked('o-');//locked('o-');

if(!$_SESSION["bizname"]) {
	echo "Nope!  You gotta be logged in to a business.";
	exit;
}

if(!staffOnlyTEST()) {
	echo "Nope!  You gotta be logged in as LeashTime Staff.";
	exit;
}


if($_POST['proceed']) {
	echo "Updating tblappointment... ";
	doQuery("ALTER TABLE `tblappointment` CHANGE `charge` `charge` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblappointment` CHANGE `adjustment` `adjustment` FLOAT(6,2) NULL", 1);
	doQuery("ALTER TABLE `tblappointment` CHANGE `rate` `rate` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblappointment` CHANGE `bonus` `bonus` FLOAT(6,2) NULL", 1);
	echo "done. <p>Updating relapptdiscount... ";
	doQuery("ALTER TABLE `relapptdiscount` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating relbillablepayment... ";
	doQuery("ALTER TABLE `relbillablepayment` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating relclientcharge... ";
	doQuery("ALTER TABLE `relclientcharge` CHANGE `charge` `charge` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `relclientcharge` CHANGE `extrapetcharge` `extrapetcharge` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating relinvoiceitem... ";
	doQuery("ALTER TABLE `relinvoiceitem` CHANGE `charge` `charge` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `relinvoiceitem` CHANGE `prepaidamount` `prepaidamount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating relproviderrate... ";
	doQuery("ALTER TABLE `relproviderrate` CHANGE `rate` `rate` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating relrefundcredit... ";
	doQuery("ALTER TABLE `relrefundcredit` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating tblbillable... ";
	doQuery("ALTER TABLE `tblbillable` CHANGE `charge` `charge` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblbillable` CHANGE `paid` `paid` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblbillable` CHANGE `tax` `tax` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating tblcredit... ";
	doQuery("ALTER TABLE `tblcredit` CHANGE `voidedamount` `voidedamount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating tbldiscount... ";
	doQuery("ALTER TABLE `tbldiscount` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating tblgratuity... ";
	doQuery("ALTER TABLE `tblgratuity` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating tblinvoice... ";
	doQuery("ALTER TABLE `tblinvoice` CHANGE `discountamount` `discountamount` FLOAT(7,2) NULL", 1);
	doQuery("ALTER TABLE `tblinvoice` CHANGE `subtotal` `subtotal` FLOAT(7,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblinvoice` CHANGE `tax` `tax` FLOAT(7,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblinvoice` CHANGE `pastbalancedue` `pastbalancedue` FLOAT(7,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblinvoice` CHANGE `origbalancedue` `origbalancedue` FLOAT(7,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblinvoice` CHANGE `creditsapplied` `creditsapplied` FLOAT(7,2) NULL", 1);
	echo "done. <p>Updating tblothercharge... ";
	doQuery("ALTER TABLE `tblothercharge` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating tblothercomp... ";
	doQuery("ALTER TABLE `tblothercomp` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	echo "done. <p>Updating tblpayable... ";
	doQuery("ALTER TABLE `tblpayable` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblpayable` CHANGE `paid` `paid` FLOAT(6,2) NOT NULL", 1);
	
	// tblpayment is NOT USED
	echo "done. <p>Updating tblproviderpayment... ";
	doQuery("ALTER TABLE `tblproviderpayment` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblproviderpayment` CHANGE `adjustment` `adjustment` FLOAT(6,2) NULL", 1);
	echo "done. <p>Updating tblrecurringpackage... ";
	doQuery("ALTER TABLE `tblrecurringpackage` CHANGE `weeklyadjustment` `weeklyadjustment` FLOAT(7,2) NULL", 1);
	doQuery("ALTER TABLE `tblrecurringpackage` CHANGE `totalprice` `totalprice` FLOAT(7,2) NULL", 1);
	echo "done. <p>Updating tblrefund... ";
	doQuery("ALTER TABLE `tblrefund` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
	// tblscheduleplanservice is NOT USED
	echo "done. <p>Updating tblservice... ";
	doQuery("ALTER TABLE `tblservice` CHANGE `charge` `charge` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblservice` CHANGE `adjustment` `adjustment` FLOAT(6,2) NULL", 1);
	doQuery("ALTER TABLE `tblservice` CHANGE `rate` `rate` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblservice` CHANGE `bonus` `bonus` FLOAT(6,2) NULL", 1);
	echo "done. <p>Updating tblservicepackage... ";
	doQuery("ALTER TABLE `tblservicepackage` CHANGE `packageprice` `packageprice` FLOAT(7,2) NULL", 1);

	echo "done. <p>Updating tblservicetype... ";
	doQuery("ALTER TABLE `tblservicetype` CHANGE `defaultcharge` `defaultcharge` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblservicetype` CHANGE `defaultrate` `defaultrate` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblservicetype` CHANGE `extrapetrate` `extrapetrate` FLOAT(6,2) NULL", 1);
	doQuery("ALTER TABLE `tblservicetype` CHANGE `extrapetcharge` `extrapetcharge` FLOAT(6,2) NULL", 1);

	echo "done. <p>Updating tblsurcharge... ";
	doQuery("ALTER TABLE `tblsurcharge` CHANGE `charge` `charge` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblsurcharge` CHANGE `rate` `rate` FLOAT(6,2) NOT NULL", 1);

	echo "done. <p>Updating tblsurchargetype... ";
	doQuery("ALTER TABLE `tblsurchargetype` CHANGE `defaultcharge` `defaultcharge` FLOAT(6,2) NOT NULL", 1);
	doQuery("ALTER TABLE `tblsurchargetype` CHANGE `defaultrate` `defaultrate` FLOAT(6,2) NOT NULL", 1);
	echo "done.";
	echo "<p><a href='index.php'>Home</a>";
	exit;
}

echo "<h2>{$_SESSION['bizname']} ({$_SESSION['db']})</h2>";
?>
Click the Go button to increase the maximum dollar amounts recordable in this database to $9,999.99.<p>
<form method = 'POST'>
<input type='submit' value='Go'>
<input type='hidden' name='proceed' value='1'>
</form>