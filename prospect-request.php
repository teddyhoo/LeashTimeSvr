<?
//prospect-request.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "preference-fns.php";
require_once "request-fns.php";

set_time_limit(60);

extract($_POST);
//extract($_REQUEST);


if(!$pbid) {
	echo "No business id supplied.";
	killSessionAndExit();
}
$petBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $pbid");
if(!$petBiz) {
	echo "Business not found.";
	killSessionAndExit();
}
mysql_close();
$_SESSION["dbhost"] = $petBiz["dbhost"];
$_SESSION["db"] = $petBiz["db"];
$_SESSION["dbuser"] = $petBiz["dbuser"];
$_SESSION["dbpass"] = $petBiz["dbpass"];

require_once "common/init_db_petbiz.php";

$prefs = fetchPreferences($db);
setLocalTimeZone($prefs['timeZone']);

if(!$prefs) {
	echo "No business preferences found.";
	killSessionAndExit();
}
if(!$prefs['acceptProspectRequests']) {
	echo "This business does not accept prospective client requests.<p>
				Please consult the Preferences page and change the \"Accept Prospect Requests\" setting<p>
				to make this business accept prospective client requests.";
	killSessionAndExit();
}

saveNewProspectRequest($_POST);

if($prefs['prospectRequestResponse']) { // the Staff Only URL or text
	if(strpos($prefs['prospectRequestResponse'], 'http') === 0) $goback = $prefs['prospectRequestResponse'];
	else {
//if(mattOnlyTEST()) {echo globalURL("prospect-request-thankyou.php?bizid=$pbid&goback=$goback");exit;}
		globalRedirect("prospect-request-thankyou.php?bizid=$pbid&goback=$goback");
		exit;
	}
}

if($goback) {  // MAY be set by // $prefs['prospectFormGoBackURL']
	header("Location: $goback");
	killSessionAndExit();
}
else {
	globalRedirect("prospect-request-thankyou.php?bizid=$pbid&goback=$goback");
}
killSessionAndExit();



