<?
require_once "common/db_fns.php";

error_reporting(2039);
define("DEBUG",1);

$installationSettings = ensureInstallationSettings($SUBDIRECTORY); // $SUBDIRECTORY will be non-null for corp scripts
//echo "SET: ";print_r($installationSettings);
$dbhost = $installationSettings['dbhost'];
$db = $installationSettings['db'];
$dbuser = $installationSettings['dbuser'];
$dbpass = $installationSettings['dbpass'];
$lnk = mysql_connect($dbhost, $dbuser, $dbpass);

if ($lnk < 1) {
	$errMessage="Not able to connect: invalid database username and/or password.";
}

$lnk1 = mysql_select_db($db);

if(mysql_error()) echo mysql_error();

?>
