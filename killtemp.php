<? // killtemp.php
/*require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";


if($db != 'skylinetails') {echo "DANGER!";exit;}

$sitters = array(6,8,11);
$sitters = fetchCol0("SELECT providerid FROM tblprovider WHERE providerid NOT IN (".join(',', $sitters).")");
//$clients = fetchCol0("SELECT clientid FROM tblclient WHERE defaultproviderptr NOT IN (".join(',', $sitters).")");

print_r($sitters);
foreach($sitters as $id) wipeProvider($id);
echo "<P>";
//print_r($clients);
//foreach($clients as $id) wipeClient($id);
*/