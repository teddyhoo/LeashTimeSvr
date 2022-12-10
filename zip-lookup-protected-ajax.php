<?
//zip-lookup-protected-ajax.php 
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
include "zip-lookup.php";
extract($_REQUEST);
lookUpProtectedZip($zip);
?>
