<? // reports-login-diagnostics.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

$locked = locked('o-');

if($_GET['logins']) {
	if((int)$_GET['logins'] != $_GET['logins']) {
		echo "Bad input";
		exit;
	}
	require_once "common/init_db_common.php";
	$loginid = fetchRow0Col0($sql = "SELECT loginid FROM tbluser WHERE userid = {$_GET['logins']} AND bizptr = {$_SESSION['bizptr']} LIMIT 1", 1);
	$loginidLabel = $loginid ? $loginid : "<i>none found</i>";
	echo "<h3>Logins by {$_GET['nm']} <img src='art/help.jpg' onclick='showHelp()' height='20' width='20'></h3>(login ID: <u>$loginidLabel</u>)<p>";
	if(!$loginid) {
		echo "User not found.";
		exit;
	}
	$sql = "SELECT * FROM tbllogin WHERE loginid = '$loginid' ORDER BY lastupdatedate DESC LIMIT 300";
  if(!($result = doQuery($sql))) echo "No logins found for [$loginid]";
  else {
		$failures = explodePairsLine("|Ok||0|Ok||L|Account locked||P|Bad password||U|Unknown user||I|Inactive User||R|RightsMissingOrMismatched||F|No Business found||B|Business inactive||M|Missing organization||O|Organization inactive||C|No cookie||D|Logins disabled for this role");

		echo "<table width=100%>";
		while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$rowClass = $row['FailureCause'] ? 'canceledtask' : '';
			echo "<tr class='$rowClass'><td>".shortDateAndTime(strtotime($row['LastUpdateDate']), 'military').'</td>'
						."<td>{$failures[$row['FailureCause']]}</td>"
						."<td title='{$row['browser']}'>{$row['RemoteAddress']}</td></tr>";
		}
		echo "</table>";
		exit;
	}
}

extract(extractVars('clientpat,providerpat,adminpat,userid', $_REQUEST));

if($_POST) {
	if($pat = $clientpat) {
		$type = 'client';
	}
	else if($pat = $providerpat) {
		$type = 'provider';
	}
	if($pat) {
		$persons = fetchAssociations("SELECT {$type}id, CONCAT_WS(' ', fname, lname) as name, userid, email, active
																		FROM tbl$type 
																		WHERE CONCAT_WS(' ', fname, lname) LIKE '%$pat%'
																		ORDER BY lname, fname");
	}
	else if($pat = $adminpat) {
		$type = 'admin';
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require_once "common/init_db_common.php";
		$persons = fetchAssociations($sql = "SELECT CONCAT_WS(' ', fname, lname) as name, userid, email, active
																		FROM tbluser 
																		WHERE bizptr = {$_SESSION['bizptr']}
																			AND ltstaffuserid = 0
																			AND (rights LIKE 'd-%' OR rights LIKE 'o-%')
																			AND CONCAT_WS(' ', fname, lname) LIKE '%$pat%'
																		ORDER BY lname, fname", 1);
	}
}
$breadcrumbs = "<a href='reports.php'>Reports</a>";
$extraHeadContent = '<style>.namepattern {width:100px;}</style>
<script language="javascript">
function findClient(el) {if($("#clientpat").val().trim() == "") alert("Please supply a name"); else el.form.submit();}
function findSitter(el) {if($("#providerpat").val().trim() == "") alert("Please supply a name"); else el.form.submit();}
function findAdmin(el) {if($("#adminpat").val().trim() == "") alert("Please supply a name"); else el.form.submit();}
function showDetails(userid,name) {	$("#detail").html("Searching..."); $.ajax({url:"reports-login-diagnostics.php?logins="+userid+"&nm="+name,
					success: function(data) {$("#detail").html(data)}});}
</script>
';
include "frame.html";
// ***************************************************************************
?>
<h2>Troubleshoot Login Problems</h2>
<div class='tiplooks' style='text-align:left;'>Enter part of a name to find the person you are trying to help.</div>
<table><tr><td valign=top width=50%>
<table width=100%>
<?
//function labeledInput($label, $name, $value=null, $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=null, $noEcho=false) {
//echoButton($id, $label, $onClick='', $class='', $downClass='', $noEcho=false, $title=null)
echo "<tr><td><form method=POST>".labeledInput('Client name:', 'clientpat', $clientpat, $labelClass=null, 'namepattern', $onBlur=null, $maxlength=null, $noEcho=true)
								.'<td>'.echoButton('', 'Find', 'findClient(this)',  $class='', $downClass='', $noEcho=true, $title='Find clients to help.')
								."</form></td></tr>";
echo "<tr><td><form method=POST>".labeledInput('Sitter name:', 'providerpat', $providerpat, $labelClass=null, 'namepattern', $onBlur=null, $maxlength=null, $noEcho=true)
								.'<td>'.echoButton('', 'Find', 'findSitter(this)',  $class='', $downClass='', $noEcho=true, $title='Find sitters to help.')
								."</form></td></tr>";
echo "<tr><td><form method=POST>".labeledInput('Admin name:', 'adminpat', $adminpat, $labelClass=null, 'namepattern', $onBlur=null, $maxlength=null, $noEcho=true)
								.'<td>'.echoButton('', 'Find', 'findAdmin(this)',  $class='', $downClass='', $noEcho=true, $title='Find managers, admins, and dispatchers to help.')
								."</form></td></tr>";
?>
</table>
<?
if($pat) {
	$labels = explodePairsLine('client|Clients||provider|Sitters||admin|Admin Staff');
	echo "<h3>".$labels[$type]." matching [$pat]</h3>";
	if(!$persons) echo "None found.";
	else {
		foreach($persons as $person) {
//function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) {
			$label = $person['name'].($person['active'] ? '' : "<font color=red>(inactive)</font>");
			if(!$person['userid']) echo "$label -- no login set<br>";
			else {
				fauxLink($label, "showDetails({$person['userid']}, \"".addslashes($person['name'])."\")", $noEcho=false, $title='Click for login history.');
				echo "<br>";
			}
		}
	}
}
?>
</td>
<td valign=top id='detail'></td></tr></table>
<div style='display:none' id='helptext'>
<h3>So what does this tell me?</h3>
The list of logins (if any) shown here tell you when someone tried to login with the LeashTime login ID (or username) shown.
<p>
If the user mistyped the login ID when logging in, that attempt will <b>not</b> show up here.  So if
a user&apos;s assigned login ID is <font color=green>barky992@yahoo.com</font>, but she tries logging in as 
just <font color=red>barky992</font> instead, that login attempt will not appear here.
<p>
<b>Missing</b> logins can be just as important as the logins that show up here.  A login may be missing due to a typo or
absent-minded mistake by the user or the accidental inclusion of spaces before or after the login ID, or they might indicate 
that the user&apos;s computer failed to contact the LeashTime 
server at all.
<p>
Successful attempts (from the server&apos;s point of view) will show <b>Ok</b> next to the date and time.
<p>
Unsuccessful attempts are highlighted in pink and show the cause of the failure instead of "Ok".
<p>
The user&apos;s <b>internet (IP) address</b> is shown to the right.  Hovering the mouse over the IP address shows
the <b>User Agent</b> of the user&apos;s web browser, which offers hints about the type of computer and type of browser
the person was using during the login attempt.
</div>
<script language="javascript">
function showHelp(el) {$.fn.colorbox({html:$('#helptext').html(), width:"550", height:"400", scrolling: true, opacity: "0.3"});}
</script>
<?
// ***************************************************************************
	include "frame-end.html";
