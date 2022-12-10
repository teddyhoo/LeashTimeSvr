<? // cc-edit.php
/* Params
client - id of client: edit current cc
cc (optional): edit cc for client

Allow only one active cc per client.
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if($_SESSION['preferences']['enableTransactionExpressAdapter']) require_once "cc-editNEW.php";
else require_once "cc-editORIG.php";
