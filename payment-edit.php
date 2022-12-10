<?
/* payment-edit.php
*
* (This version introduces VOID payments)
*
* Parameters: 
* id - id of payment to be edited
* - or -
* client - id of client to be credited
* amount (optional)
* successDestination (optional) - as a global or request arg.  If supplied, after successful payment append paymentptr and
* set page to that destination
* suppressSavePaymentButton (optional) - as a global or request arg.
*
* credit may not be modified (except for reason) once amountused > 0
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
if($_SESSION['preferences']['enablededicatedpayments'])
	require "payment-edit-2stage.php";
else require "payment-edit-v0.php";