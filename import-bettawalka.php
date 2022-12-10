<? // import-bettawalka.php

set_time_limit(5);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "custom-field-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "item-note-fns.php";

if(!$_SESSION['staffuser']) {
	//echo "This page is for LT Staff only at this point.";
	//exit;
}

if(userRole() != 'o' && userRole() != 'd') {
	echo "You must be logged in as a manager.";
	exit;
}

locked('d-');

if($_SESSION) {
	if(isset($_SESSION["bizfiledirectory"])) {
		$headerBizLogo = $_SESSION["bizfiledirectory"];
		if(file_exists($_SESSION["bizfiledirectory"].'logo.jpg')) $headerBizLogo .= 'logo.jpg';
		else if(file_exists($_SESSION["bizfiledirectory"].'logo.gif')) $headerBizLogo .= 'logo.gif';
		else $headerBizLogo = '';
		if($headerBizLogo) {
			$dimensions = getimagesize($headerBizLogo);
			$logoX = $dimensions[0] ? 780 - $dimensions[0] : 511;
			if($_SESSION['staffuser']) {
				if($_SESSION['bizname']) $title = str_replace("'", "", $_SESSION['bizname']);
				$title = "title='$title [{$_SESSION['bizptr']}] $db'";
			}
			$businessIdentifier = "<img src='$headerBizLogo' $title />";
		}
	}
}

$businessIdentifier = $businessIdentifier ? $businessIdentifier : "(DB: <font color=blue>{$_SESSION['preferences']['bizName']}</font>)";

if($_POST && $_POST['lastdb'] != $db) 
	$error = "WARNING - You are no longer logged in to the same database.<p><a href='import-bettawalka.php'>Please Refresh this page</a>";
else if($_POST['clientdata']) {
	require_once "import-one-bettawalka-client-and-pets.php";
	$message = importClient($showUnhandled=false);
}
else if($_POST['sitterdata']) {
	require_once "import-one-bettawalka-sitter.php";
	$message = importSitter($showUnhandled=false);
}
require "frame-bannerless.php";
?>
<h2><?= $businessIdentifier ?></h2><h2>BettaWalka Data Import Page </h2>
In another window, go to <a href='https://bettawalka.net/'>https://bettawalka.net/</a>
<?
if($error) {
	echo "<span style='color:red;font-size:1.5em;'>$error</span><p>";
	exit;
}
else if($message) echo "<font color='darkgreen'>$message</font><p>";

?>
<table><tr><td valign=top>
<table border=1>
<tr><td>
<form method='POST' name='addsitter'>
<?
hiddenElement('lastdb', $db);
echoButton('', 'Add Sitter', 'document.addsitter.submit()');
fauxLink('Clear', 'document.getElementById("sitterdata").value = "";');
echo "<td style='color:blue;font-size:2em;'>Sitter (Minder)</td>";
//$label =  "Ctrl-A, Ctrl-C in a BettaWalka Minder client page.  Ctrl-V below and click 'Add Sitter'";
//textRow($label, 'sitterdata', $clientdata, $rows=10, $cols=80);
?>
<tr><td colspan=2>Ctrl-A, Ctrl-C in a BettaWalka Active Walkers or Inactive Walkers page.<br>Ctrl-V below and click 'Add Sitter'<br>
<textarea onclick='this.select()' id='sitterdata' name='sitterdata' rows=10, cols=80><?= $_REQUEST['sitterdata'] ?></textarea>
</form>
<tr><td>
<form method='POST' name='addclient'>
<?
hiddenElement('lastdb', $db);
echoButton('', 'Add Client', 'document.addclient.submit()');
fauxLink('Clear', 'document.getElementById("clientdata").value = "";');
echo "<td style='color:darkgreen;font-size:2em;'>Client (Account)</td>";
//$label =  "Ctrl-A, Ctrl-C in a BettaWalka Account page.  Ctrl-V below and click 'Add Client'";
//textRow($label, 'clientdata', $sitterdata, $rows=10, $cols=80);
?>
<tr><td colspan=2>Ctrl-A, Ctrl-C in a BettaWalka Active Accounts or Inactive Accounts page.<br>Ctrl-V below and click 'Add Client'<br>
<textarea onclick='this.select()' id='clientdata' name='clientdata' rows=10, cols=80><?= $_REQUEST['clientdata'] ?></textarea>
</form>
</table>
<td valign=top>
<table><tr>
<td valign=top>Sitters<br>
<? foreach(fetchCol0("SELECT CONCAT_WS('', CONCAT_WS(', ', lname, fname), ' [',if(active,'active', 'inactive'), ']') 
												FROM tblprovider order by lname, fname") as $nm) echo "$nm<br>"; 
?>
<td valign=top>Clients<br>
<? foreach(fetchCol0("SELECT CONCAT_WS('', CONCAT_WS(', ', lname, fname), ' [',if(active,'active', 'inactive'), ']') 
												FROM tblclient order by lname, fname") as $nm) echo "$nm<br>";
?>
</table>
</table>
