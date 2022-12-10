<?
// client-own-account.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if($_SESSION['preferences']['enableTransactionExpressAdapter']
   || in_array(
		 		$_SESSION['preferences']['ccGateway'], 
		 		array('TransFirstV1', 'TransFirstTransactionExpress')))
 require_once "client-own-accountNEW.php";
else require_once "client-own-accountORIG.php";