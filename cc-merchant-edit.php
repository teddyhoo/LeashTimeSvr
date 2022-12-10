<? // cc-merchant-edit.php
/* Params
none
*/

require_once "common/init_session.php";
if($_SESSION['preferences']['enableTransactionExpressAdapter']) require_once "cc-merchant-editDEV.php";
else require_once "cc-merchant-editOLD.php";
