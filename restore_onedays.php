<? // restore_onedays.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if($db != 'savinggrace') {
    echo "WRONG DATABASE!!";
    exit;
}

// Find the package id of every "missing" nonrecurring package (oneday, pro, ez schedule)
// return as an array where packageid is the index and the value is a placeholder
$missingPackIds = fetchKeyValuePairs(
	"SELECT distinct(packageptr), 1
		FROM tblappointment
		LEFT JOIN tblservicepackage ON packageid = packageptr
		WHERE recurringpackage = 0 AND packageid IS NULL");

echo count($missingPackIds)." packages.<p>";


// These are the names of the restored nonrecurring package tables from RackSpace 
// in reverse chronological order
// to ensure that we are getting the most recent versions of any packages found first
$tables = 'temp_nrp8,temp_nrp7,temp_nrp5,temp_nrp4,temp_nrp3,temp_nrp2,temp_nrp1';

// For each restored NR package table
foreach(explode(',', $tables) as $table) {
	// Find the packages named in $missingPackIds
	$found = fetchAssociations("SELECT * FROM $table WHERE packageid IN (".join(',', array_keys($missingPackIds)).")");
	// For each found missing package
	foreach($found as $pack) {
		// If it is not a OneDay package or its is a OneDay before Sep 1, ignore it
		if(!$pack['onedaypackage'] || strcmp($pack['startdate'], '2011-09-01') < 0) continue;
		//Otherwise add the package to the NR package table...
		echo "Restoring package {$pack['packageid']}<br>";
		insertTable('tblservicepackage', $pack, 1);
		// ... and remove the package id from the list of missing packages
		unset($missingPackIds[$pack['packageid']]);
	}
}