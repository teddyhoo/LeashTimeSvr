<? // client-visit-notification-email-template-setup.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if(!$db) {
	echo "<h1>You must log in to a business to run this script.</h1>";
	exit;
}
if(!staffOnlyTEST()) {
	echo "<h1>Only LeashTime Staff can run this script</h1>";
	exit;
}
if(dbTEST('leashtimecustomers')) {
	echo "<h1>DO NOT RUN THIS SCRIPT IN LeashTime Customers</h1>";
	exit;
}
if(!adequateRights('o-')) {
	echo "<h1>You don't have permissions to run this script.</h1>";
	exit;
}
locked('o-');

?>
<h1>Company: <?= $_SESSION['preferences']['bizName']  ?> -  Database: <?= $db ?></h1>
<?
$labels = fetchCol0("SELECT label FROM tblemailtemplate WHERE label IN ('#STANDARD - Visit Completed','#STANDARD - Sitter Arrived')");

foreach($labels as $label) echo "There is already a <b>".substr($label, strlen('#STANDARD - ')).'</b> template.<p>';

if($_POST['go']) {
	
	$sql = 
		"INSERT INTO `tblemailtemplate` (`label`, `subject`, `body`, `targettype`, `personalize`, `salutation`, `farewell`, `active`, `extratokens`) VALUES
		('#STANDARD - Visit Completed', 'Visit completed', 'Hi #RECIPIENT#,\r\n\r\nThis note is to inform you that #SITTER# finished a visit to care for #PETS# at your home on #DATE# at #TIME#.#IF_NOVISITNOTE#\r\n\r\nIt is always a pleasure to visit with #PETS#.#END_NOVISITNOTE##IF_VISITNOTE#<hr>Visit note:\r\n\r\n#VISITNOTE#<hr>#END_VISITNOTE#\r\n\r\nSincerely,\r\n\r\n#BIZNAME#', 'other', 0, NULL, '', 1, ''),
		('#STANDARD - Sitter Arrived', 'Sitter arrival', 'Hi #RECIPIENT#,\r\n\r\nThis note is to inform you that #SITTER# arrived to care for #PETS# at your home on #DATE# at #TIME#.\r\n\r\nSincerely,\r\n\r\n#BIZNAME#', 'other', 0, NULL, '', 1, '');";
	doQuery($sql);
	if(mysqli_error()) echo "PROBLEM: ".mysqli_error();
	else 	echo mysqli_affected_rows()." rows added.";
	exit;
}
?>
This script will add two email templates:
<form method='POST'><input type=submit value='Add Them'><input type=hidden name='go' value='1'></form>
<div style='border:solid black 1px;display:block;padding:5px;background:lightblue;width:600px;'>
<b>Sitter Arrived</b><br>
Subject: Sitter arrival<br>
Message:<br>
Hi #RECIPIENT#,<p>This note is to inform you that #SITTER# arrived to care for #PETS# at your home on #DATE# at #TIME#.<p>Sincerely,<p>#BIZNAME#
</div>
<p>
<div style='border:solid black 1px;display:block;padding:5px;background:lightblue;width:600px;'>
<b>Visit Completed</b><br>
Subject: Visit completed<br>
Message:<br>
Hi #RECIPIENT#,<p>This note is to inform you that #SITTER# finished a visit to care for #PETS# at your home on #DATE# at #TIME#.#IF_NOVISITNOTE#<p>It is always a pleasure to visit with #PETS#.#END_NOVISITNOTE##IF_VISITNOTE#<hr>Visit note:<p>#VISITNOTE#<hr>#END_VISITNOTE#<p>Sincerely,<p>#BIZNAME#
</div>
