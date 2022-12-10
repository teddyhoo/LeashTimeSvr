<?
/* refund-edit.php
*
* Parameters: 
* id - id of refund to be edited
* - or -
* client - id of client to be credited
* payment - id of payment refund is for
*
* credit may not be modified (except for reason) once amountused > 0
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if(FALSE && staffOnlyTEST()) require_once "refund-editNEW.php";
else require_once "refund-editOLD.php";