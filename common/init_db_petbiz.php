<?
if(!isset($_SESSION)) require_once "common/init_session.php";
require_once "common/db_fns.php";

ensureInstallationSettings();

error_reporting(2039);
define("DEBUG",1);

extract($_SESSION); // use instead: extract(extractVars('dbhost,dbuser,db,dbpass', $_SESSION)); 

reconnectPetBizDB();

setLocalTimeZone();
?>
