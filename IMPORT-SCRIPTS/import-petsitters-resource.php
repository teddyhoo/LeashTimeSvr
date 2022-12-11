<? // import-petsitters-resource.php

set_time_limit(5);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "custom-field-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "client-fns.php";
require_once "preference-fns.php";
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

$businessIdentifier = $businessIdentifier ? $businessIdentifier : "(DB: <font color=blue>{$_SESSION['preferences']['bizName']}}</font>)";

if($_POST && $_POST['lastdb'] != $db) 
	$error = "WARNING - You are no longer logged in to the same database.<p><a href='import-petsitters-resource.php'>Please Refresh this page</a>";
else if($_POST['clientdata']) {
	require_once "import-petsitters-resource-clients.php";
	$message = importClients($showUnhandled=false);
}
else if($_POST['sitterdata']) {
	require_once "import-petsitters-resource-sitters.php";
	$message = importSitters($showUnhandled=false);
}
require "frame-bannerless.php";
?>
<h2><?= $businessIdentifier ?></h2><h2>Petsitters Resource Data Import Page </h2>
<?
if($error) {
	echo "<span style='color:red;font-size:1.5em;'>$error</span><p>";
	exit;
}
else if($message) echo "<font color='darkgreen'>$message</font><p>";

?>
<table><tr><td valign=top width=50%>
<table border=1>
<tr><td>
<form method='POST' name='addsitter'>
<?
hiddenElement('lastdb', $db);
echoButton('', 'Add Sitters', 'document.addsitter.submit()');
fauxLink('Clear', 'document.getElementById("sitterdata").value = "";');
echo "<td style='color:blue;font-size:2em;'>Sitters</td>";
?>
<tr><td colspan=2>Select all sitters and <b>Print Sitter(s)</b>, and then <ol><li>Ctrl-A, Ctrl-C in the report.<li>Ctrl-V below and click 'Add Sitters'</ol><br>
<textarea onclick='this.select()' id='sitterdata' name='sitterdata' rows=10, cols=80><?= $_REQUEST['sitterdata'] ?></textarea>
</form>
<tr><td>
<form method='POST' name='addclient'>
<?
hiddenElement('lastdb', $db);
echoButton('', 'Add Client Data', 'document.addclient.submit()');
fauxLink('Clear', 'document.getElementById("clientdata").value = "";');
echo "<td style='color:darkgreen;font-size:2em;'>Client (Account)</td>";
?>
<tr><td colspan=2>Select all clients and click <b>Print Client(s)</b>, then select all Households and click <b>Print Client Household(s)</b>, then select all Pets and click <b>Print Client Pet(s)</b>.<br>For each report: <ol><li>Ctrl-A, Ctrl-C in the report.<li>Ctrl-V below and click 'Add Client Data'</ol><br>
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
