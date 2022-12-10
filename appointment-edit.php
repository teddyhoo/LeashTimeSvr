<?
/* appointment-edit.php
*
* Mode 1 Parameters: 
* id - id of appointment to be edited
*
* Mode 2 Parameters: 
* date - date of appointment to be created
* clientptr - clientptr of appointment to be created
* providerptr (optional) - providerptr of appointment to be created
* packageptr - packageptr of appointment to be created
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

/*if(mattOnlyTEST()) require "appointment-editSAVEMATT.php";
else if($_SESSION['preferences']['useNewRateCalculations'] || dbTEST('careypet')) require "appointment-editCANDIDATE.php";*/
if(TRUE) require "appointment-editCANDIDATE.php";
else require "appointment-editSAVE.php";
