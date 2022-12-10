<?  // training-fns.php

// ALTER TABLE `tblclient` ADD `training` TINYINT NOT NULL DEFAULT '0';
// ALTER TABLE `tblprovider` ADD `training` TINYINT NOT NULL DEFAULT '0';

function trainingModeIsOn() {
	return $_SESSION['trainingMode'];
}

function turnOnTraningMode() {
	$_SESSION['trainingMode'] = 1;
}

function turnOffTrainingMode() {
	$_SESSION['trainingMode'] = 0;
	eliminateTrainingData();
}

function eliminateTrainingData() {
	//ini_set('memory_limit','512M');
	$tables = fetchCol0("SHOW TABLES");
	$users = array_merge(
			fetchCol0("SELECT userid FROM tblclient WHERE training = 1 AND userid IS NOT NULL", 1),
			fetchCol0("SELECT userid FROM tblprovider WHERE training = 1 AND userid IS NOT NULL", 1));
	if($users) doQuery("DELETE FROM tbluserpref WHERE userptr IN (".join(',', $users).")", 1);
	
	$trainingClients = fetchCol0("SELECT clientid FROM tblclient WHERE training = 1", 1);
	$trainingClientsString = join(',', $trainingClients);
	// client records
	doQuery("DELETE FROM tblclient WHERE training = 1", 1);
	$trainingProviders = fetchCol0("SELECT providerid FROM tblprovider WHERE training = 1");
	$trainingProvidersString = join(',', $trainingProviders);
	// sitter records
	doQuery("DELETE FROM tblprovider WHERE training = 1", 1);
	
	
	// CLIENTS
	if($trainingClientsString) {
		$clientAppts = fetchCol0("SELECT appointmentid FROM tblappointment WHERE clientptr IN ($trainingClientsString)", 1);
		// custom fields
		doQuery("DELETE FROM relclientcustomfield WHERE clientptr IN ($trainingClientsString)", 1);
		// visits
		doQuery("DELETE FROM tblappointment WHERE clientptr IN ($trainingClientsString)", 1);
		// visit discounts
		doQuery("DELETE FROM relapptdiscount WHERE clientptr IN ($trainingClientsString)", 1);
		// client charges
		doQuery("DELETE FROM relclientcharge WHERE clientptr IN ($trainingClientsString)", 1);
		// client custom fields
		doQuery("DELETE FROM relclientcharge WHERE clientptr IN ($trainingClientsString)", 1);
		// client discounts
		doQuery("DELETE FROM relclientdiscount WHERE clientptr IN ($trainingClientsString)", 1);
		// invoice items
		doQuery("DELETE FROM relinvoiceitem WHERE clientptr IN ($trainingClientsString)", 1);
		// client preferences
		doQuery("DELETE FROM tblclientpref WHERE clientptr IN ($trainingClientsString)", 1);
		// client profile requests
		doQuery("DELETE FROM tblclientprofilerequest WHERE clientptr IN ($trainingClientsString)", 1);
		// client requests
		doQuery("DELETE FROM tblclientrequest WHERE clientptr IN ($trainingClientsString)", 1);
		// contacts
		doQuery("DELETE FROM tblcontact WHERE clientptr IN ($trainingClientsString)", 1);
		// credits
		$credits = fetchCol0("SELECT creditid FROM tblcredit WHERE clientptr IN ($trainingClientsString)", 1);
		doQuery("DELETE FROM tblcredit WHERE clientptr IN ($trainingClientsString)", 1);
		// invoices
		$invoices = fetchCol0("SELECT invoiceid FROM tblinvoice WHERE clientptr IN ($trainingClientsString)", 1);
		doQuery("DELETE FROM tblinvoice WHERE clientptr IN ($trainingClientsString)", 1);
		// keys
		doQuery("DELETE FROM tblkey WHERE clientptr IN ($trainingClientsString)", 1);
		// keylog
		doQuery("DELETE FROM tblkeylog WHERE clientptr IN ($trainingClientsString)", 1);
		// messages
		doQuery("DELETE FROM tblmessage WHERE correspid IN ($trainingClientsString) AND correstable = 'tblclient'", 1);
		// preferences
		doQuery("DELETE FROM tblclientpref WHERE clientptr IN ($trainingClientsString)", 1);
		// services
		doQuery("DELETE FROM tblservice WHERE clientptr IN ($trainingClientsString)", 1);
		// payments
		doQuery("DELETE FROM tblpayment WHERE clientptr IN ($trainingClientsString)", 1);
		// pets
		$petIds = fetchCol0("SELECT petid FROM tblpet WHERE ownerptr IN ($trainingClientsString)", 1);
		doQuery("DELETE FROM tblpet WHERE ownerptr IN ($trainingClientsString)", 1);
		if($petIds) doQuery("DELETE FROM relpetcustomfield WHERE petptr IN (".join(',', $petIds).")", 1);
		// refunds
		doQuery("DELETE FROM tblrefund WHERE clientptr IN ($trainingClientsString)", 1);
		// surcharges
		doQuery("DELETE FROM tblsurcharge WHERE clientptr IN ($trainingClientsString)", 1);
		// other charges
		doQuery("DELETE FROM tblothercharge WHERE clientptr IN ($trainingClientsString)", 1);
		// packages
		$npackages = fetchCol0("SELECT packageid FROM tblservicepackage WHERE clientptr IN ($trainingClientsString)", 1);
		$npackagesString = join(',', $npackages);
		$rpackages = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE clientptr IN ($trainingClientsString)", 1);
		$rpackagesString = join(',', $rpackages);
		if($npackagesString) {
			doQuery("DELETE FROM tblpackageid WHERE packageid IN ($npackagesString)", 1);
			doQuery("DELETE FROM tblservicepackage WHERE packageid IN ($npackagesString)", 1);
		}
		if($rpackagesString) {
			doQuery("DELETE FROM tblpackageid WHERE packageid IN ($rpackagesString)", 1);
			doQuery("DELETE FROM tblrecurringpackage WHERE packageid IN ($rpackagesString)", 1);
		}
		// billables
		$clientBillables = fetchCol0("SELECT billableid FROM tblbillable WHERE clientptr IN ($trainingClientsString)", 1);
	}
	// confirmations
	$clientPhrase = $trainingClientsString ? "(respondentptr IN ($trainingClientsString) AND respondenttable = 'tblclient')" : "1=2";
	$providerPhrase = $trainingProvidersString ? "(respondentptr IN ($trainingProvidersString) AND respondenttable = 'tblprovider')" : "1=2";
	$confids = fetchCol0("SELECT confid FROM tblconfirmation WHERE $clientPhrase OR $providerPhrase");
	if($confids) {
		doQuery("DELETE FROM tblconfirmation WHERE confid IN (".join(',', $confids).")", 1);
		doQuery("DELETE FROM tblqueuedconf WHERE confid IN (".join(',', $confids).")", 1);
	}
	if($invoices) { //relinvoicecan, relinvoicecredit, relpastdueinvoice
		doQuery("DELETE FROM relinvoicecan WHERE invoiceptr IN (".join(',', $invoices).")", 1);
		doQuery("DELETE FROM relinvoicecredit WHERE invoiceptr IN (".join(',', $invoices).")", 1);
		doQuery("DELETE FROM relpastdueinvoice WHERE currinvoiceptr IN (".join(',', $invoices).")", 1);
	}
	
	if($credits) { //relrefundcredit
		doQuery("DELETE FROM relrefundcredit WHERE creditptr IN (".join(',', $credits).")", 1);
	}
	
	if($clientBillables) {
		doQuery("DELETE FROM relbillablepayment WHERE billableptr IN (".join(',', $clientBillables).")", 1);
		doQuery("DELETE FROM tblbillable WHERE billableid IN (".join(',', $clientBillables).")", 1);
	}

	// PROVIDERS
	if($trainingProvidersString) {
		doQuery("UPDATE tblclient SET defaultproviderptr=NULL WHERE defaultproviderptr IN ($trainingProvidersString)", 1);
		// provider payable payments
		doQuery("DELETE FROM relproviderpayablepayment WHERE providerptr IN ($trainingProvidersString)", 1);
		// provider rates
		doQuery("DELETE FROM relproviderrate WHERE providerptr IN ($trainingProvidersString)", 1);
		// provider zips
		doQuery("DELETE FROM relreassignment WHERE providerptr IN ($trainingProvidersString)", 1);
		// gratuities
		doQuery("DELETE FROM tblgratuity WHERE providerptr IN ($trainingProvidersString)", 1);
		// negativecomp
		doQuery("DELETE FROM tblnegativecomp WHERE providerptr IN ($trainingProvidersString)", 1);
		// othercomp
		doQuery("DELETE FROM tblothercomp WHERE providerptr IN ($trainingProvidersString)", 1);
		// payables
		doQuery("DELETE FROM tblpayable WHERE providerptr IN ($trainingProvidersString)", 1);
		// memos
		doQuery("DELETE FROM tblprovidermemo WHERE providerptr IN ($trainingProvidersString)", 1);
		// payments
		doQuery("DELETE FROM tblproviderpayment WHERE providerptr IN ($trainingProvidersString)", 1);
		// provider prefs
		doQuery("DELETE FROM tblproviderpref WHERE providerptr IN ($trainingProvidersString)", 1);
		// timeoff
		doQuery("DELETE FROM tbltimeoff WHERE providerptr IN ($trainingProvidersString)", 1);
		// messages
		doQuery("DELETE FROM tblmessage WHERE correspid IN ($trainingProvidersString) AND correstable = 'tblprovider'", 1);
		// preferences
		doQuery("DELETE FROM tblproviderpref WHERE providerptr IN ($trainingProvidersString)", 1);
		// provider zips
		if(in_array('relproviderzip', $tables)) doQuery("DELETE FROM relproviderzip WHERE providerptr IN ($trainingProvidersString)", 1);
	}
	
	if($users) {
		global $dbhost, $db, $dbuser, $dbpass;
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		include "common/init_db_common.php";
		doQuery("DELETE FROM tbluser WHERE userid IN (".join(',', $users).")", 1);
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, $force=1);
	}
}

function welcomeText() {
	return 
"<style>
h2 {font-size:18pt;}
p {font-size:12pt;}
</style>
<h2>You Have Entered Training Mode</h2>
<p>In this mode, any clients you create will be temporary, and will be deleted when you leave Training Mode,
along with their schedules, contacts, pets, and other associated information.
<p>
Likewise, any Sitters you create in Training Mode are also temporary.
<p>
However, any changes you make to permanent clients or sitters will be permanent, so please be careful
what you do in Training Mode.  We recommend that you keep your Training data and your \\\"real\\\" data separate.
For example, you should avoid assigning \\\"real\\\" sitters to \\\"training\\\" schedules.
<p>
When you wish to leave Training Mode,  click the [Leave Training Mode] link near the top of the page or simply log out.
";	
}
	// unused: relservicediscount
	
	
	/*
        // client preferences
        // contacts
        // keys
        // key log entries
        // sitter records
        // sitter rates
        // sitter preferences
        // sitter memos
        // sitter time off
        // pets
        // schedules
        // visits
        // charges
        // surcharges
        // billables
        // invoices
        // credits/payments
        // refunds
        // discount records
        // payables
        // provider payments
        // negative compensation
        // gratuities
        // messages
	*/