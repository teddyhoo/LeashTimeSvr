<?
require_once "common/db_fns.php";

error_reporting(2039);
define("DEBUG",1);
$installationSettings = ensureInstallationSettings($SUBDIRECTORY); // $SUBDIRECTORY will be non-null for corp scripts
echo "SET: ";print_r($installationSettings);

$dbhost = $installationSettings['dbhost'];
$db = "petcentral";
$dbuser = "leashtime";
$dbpass = "sdh++H5_sdkh2k96g42jd6384cnwe";
echo "CONNECT MYSQL WITH credentials:\n";
echo "$dbhost  --  $dbuser  --  $dbpass";
$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);

if ($lnk < 1) {
	$errMessage="Not able to connect: invalid database username and/or password.";
}

$lnk1 = mysqli_select_db($db);

if(mysqli_error()) echo mysqli_error();

?>
