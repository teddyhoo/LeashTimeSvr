<? // export-providers.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "export-fns.php";

$locked = locked('o-');

$status = $_REQUEST['status'];
$status = $status == 'active' ? "AND active = 1" : ($status == 'inactive' ? "AND active = 0" : '1=1');

//clientCSV(659, $_REQUEST['fields']);exit;

$providerids = fetchCol0("SELECT providerid FROM tblprovider WHERE $status AND training = 0");

$contentType = 'text/csv';
if(strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') ) {
	//$contentType = 'application/Save';
}
	
header("Cache-Control: no-store, no-cache");
header("Pragma:");
header("Content-Type: $contentType");
header("Content-Disposition: attachment; filename=Sitters.csv");

$columns = getProviderColumns($_REQUEST['fields']);
echo join(',', array_map('csv', $columns))."\n";
foreach($providerids as $providerptr)
	echo providerCSV($providerptr, $_REQUEST['fields'])."\n";
