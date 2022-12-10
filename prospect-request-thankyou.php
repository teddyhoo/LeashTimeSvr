<?
// prospect-request-thankyou.php
// invoked by prospect-request.php
// args: bizid and goback
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "preference-fns.php";
require_once "request-fns.php";

extract($_REQUEST);

if(!$bizid) {
	echo "No business id supplied.";
	exit;
}

require_once('common/init_db_common.php');
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizid' LIMIT 1");
if(!$biz)  { echo "No business found for ID supplied."; exit; }
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);

$prefs = fetchPreferences($db);

$prospectRequestResponse = $prefs['prospectRequestResponse'];
$bizName = $prefs['bizName'];
$bizHomePage = $prefs['bizHomePage'];

$prospectRequestResponse = str_replace('#HOMEPAGELINK#', "<a href='$bizHomePage'>$bizName</a>", $prospectRequestResponse);

$_SESSION["uidirectory"] = "bizfiles/biz_$bizid/clientui/";
if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$bizid/";
$suppressMenu = 1;
include "frame-client.html";
echo "<div style='font-size:1.1em;'>";
echo $prospectRequestResponse ? $prospectRequestResponse : "<h2>Thanks for contacting us.</h2><p>We will respond as soon as possible.<p>{$prefs['bizName']}";
echo "</div>";
$noCommentButton = true;
include "frame-end.html";
session_unset();
session_destroy();





