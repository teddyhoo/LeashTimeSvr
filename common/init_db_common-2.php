<?
require_once "common/db_fns.php";

error_reporting(2039);
define("DEBUG",1);
$installationSettings = ensureInstallationSettings($SUBDIRECTORY); // $SUBDIRECTORY will be non-null for corp scripts
echo "SET: ";print_r($installationSettings);

$dbhost = $installationSettings['dbhost'];
$db = "petcentral"; //$installationSettings['db'];
$dbuser = "leashtime"; //$installationSettings['dbuser'];
$dbpass = "sdh++HS_sdkh2k96g42jd6384cnwe";//$installationSettings['dbpass'];
echo "CONNECT MYSQL WITH credentials:\n";
echo "$dbhost  --  $dbuser  --  $dbpass";
$lnk = mysql_connect($dbhost, $dbuser, $dbpass);

if ($lnk < 1) {
	$errMessage="Not able to connect: invalid database username and/or password.";
}

$lnk1 = mysql_select_db($db);

if(mysql_error()) echo mysql_error();

?>
