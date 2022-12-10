<? // past-providers.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

locked('o-');

$client= $_REQUEST['client'];

$apptProviders = fetchCol0("SELECT DISTINCT providerptr FROM tblappointment WHERE clientptr = $client AND canceled IS NULL");

if($apptProviders) {
	$providers = fetchKeyValuePairs("SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) 
																	FROM tblprovider 
																	WHERE active AND providerid IN (".join(',', $apptProviders).")");
	print_r($providers);
}