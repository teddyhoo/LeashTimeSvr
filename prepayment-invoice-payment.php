<?
/* prepayment-invoice-payment.php
*
* Parameters: 
* client - clientid of client
* amount - amount of invoice to be paid
*
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract($_REQUEST);
$reason = "Payment made"; // Prepayment payment received

include "payment-edit.php";
exit;
