<? // default-provider-set-ajax.php
// Determine access privs
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$locked = locked('o-');

extract($_REQUEST);

updateTable('tblclient', array('defaultproviderptr'=>$provider), "clientid = $client", 1);

echo 'ok';