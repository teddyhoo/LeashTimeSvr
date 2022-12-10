<? // home-client-mobile.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";
require_once "key-fns.php";
require_once "mobile-client-fns.php";

locked('c-');

extract(extractVars('date,delta,hidecompletedtoggle,showvisitcount,showarrivalandcompletiontimestoggle', $_REQUEST));

$calendarTest = true; //in_array($_SESSION['auth_login_id'], array('dlifebeth', 'Xjessica', 'mmtestsit'));
// ==================================
$delayPageContent = 1;
$extraHeadContent = "<style>
body {
	background-color:#7177F4; 
	background-image:url('art/bg-mobile-client-blue-big.jpg'); 
	background-repeat:no-repeat; 
	background-attachment:fixed;
	background-position:center top;
	-webkit-background-size: cover;
	-moz-background-size: cover;
	-o-background-size: cover;
	background-size: cover
}
.toctable {background-color:transparent;border-collapse:collapse;}
.toctable td {
	padding:10px; font-family:Arial, Helvetica, sans-serif; color: black; font-size:1.2em;background-color:transparent;
	border-bottom:solid black 1px;border-left-width:0px;border-right-width:0px;} 
</style>";

require_once "mobile-frame-client.php";
$scheduleMakerURL = $_SESSION['preferences']['simpleClientScheduleMaker']
    					? "client-own-schedule-request.php?mobileclient=1"
    					: "client-sched-makerV2.php?mobileclient=1";
    					
$choices = "calendar|client-schedule-mobile.php
calendar-edit|$scheduleMakerURL
email|client-own-request.php?mobileclient=1
key|password-change-page-mobile-client.php
profile|client-own-edit.php?mobileclient=1
account|client-own-account.php?mobileclient=1";
$choices = explodePairsLine(str_replace("\r\n", "||", $choices));
$choices['profile'] = $profileEditURL;
$labels = "calendar|Schedule||calendar-edit|Request Visits||email|Contact Us||key|Change Password||profile|Profile||account|Account";
$labels = explodePairsLine($labels);
?>
<table class="toctable" align=center>
<?
foreach($choices as $imgname => $url) {
	if($imgname == 'account' && !$_SESSION['preferences']['offerClientUIAccountPage']) continue;
	$onclick = "onclick=\"document.location.href='$url'\"";
	echo "<tr><td $onclick><img src=\"art/mobileclient/$imgname.png\" ></td><td $onclick>{$labels[$imgname]}</td></tr>\n";
}
?>
</table>
<?


require_once "mobile-frame-client-end.php";