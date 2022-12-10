<? // client-ui-mockup.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";


if($thisBiz = $_GET['bizid']) {
	$_SESSION["uidirectory"] = "bizfiles/biz_$thisBiz/clientui/";
	if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$thisBiz/";
}

else {
	$_SESSION["uidirectory"] = "{$_SESSION["bizfiledirectory"]}clientui/";
}
if(!$_SESSION["uidirectory"]) {
	echo "Sorry.  Incomplete request.";
	exit;
}

$topdir = substr($_SESSION["uidirectory"], 0, strpos($_SESSION["uidirectory"], 'clientui'));
$disLogo = getHeaderBizLogo($topdir);


//echo "UI: ({$_SESSION["bizptr"]}) ".file_exists($_SESSION["uidirectory"].'style.css');

include "frame-client.html";

echoButton('', 'This is a Button');
echo "<div style='text-align:center;padding-top:30px'>Invoice Header/Banner Logo<br><img border=1 src='$disLogo'></div>";

echo "<br><img src='art/spacer.gif' width=1 height=500>";

if($_GET['bizid']) {
	unset($_SESSION["uidirectory"]);
	unset($_SESSION["bizfiledirectory"]);
	unset($_SESSION['bannerLogo']);
}
?>
<script language=javascript>
$(document).ready(function(){
	$('a').attr('href', "javascript:mockupOnly()");
});

function mockupOnly(){$.fn.colorbox({html:"<h2>This is a mockup page.</h2>", width:"750", height:"470", scrolling: true, opacity: "0.3"});}
</script>
<?
include "frame-end.html";

