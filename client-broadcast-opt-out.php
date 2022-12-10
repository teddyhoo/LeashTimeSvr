<? // client-broadcast-opt-out.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php"; // for SYSTEM_USER

if($_SESSION['auth_user_id'] != SYSTEM_USER) {
	echo "System user access only.";
	session_unset();
	session_destroy();
	exit;
}

require_once "preference-fns.php";

setClientPreference($_REQUEST['client'], 'optOutMassEmail', true);

?>
<head>
<style>
body {background-image:url("art/PRODUCTION_PLAIN.gif")}
h2 {font-family: arial,helvetica,sans-serif;font-size:14pt;font-weight:bold;}
.box {padding:20px;background: white; border:solid black 1px;font-family: arial,helvetica,sans-serif;font-size:12pt;}
</style>
</head>
<body>
<center>
<div class='box' style='width:500px;'>
<h2>Your Email Address Has Been Removed</h2>
Your email address has been removed from our list.
<p>
Please contact 
<?
$bizName = $_SESSION['preferences']['bizName'];
echo $bizName ? "<b>$bizName</b>" : 'your pet care provider';
echo $_SESSION['preferences']['bizEmail']
			? ' at '.$_SESSION['preferences']['bizEmail']
			: '';
?>

if you wish to be added back on the list.
<p>
<? 
$bizHomePage = $_SESSION['preferences']['bizHomePage'];
if($bizName && $bizHomePage) echo "Go to <a href='$bizHomePage'>$bizName</a>.";

session_unset();
session_destroy();
