<?
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
if($_SESSION['staffuser']) require "credit-editNEW.php";
else require "credit-editOLD.php";
