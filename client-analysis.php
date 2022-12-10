<?
// client-analysis.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "client-analysis-fns.php";

// Determine access privs
$locked = locked('o-');

$pageTitle = "Client Analysis";

include "frame.html";
// ***************************************************************************
extract($_POST);

$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE active");
?>
<form method = 'POST'>

<?
echoButton('', 'Ignore:', 'document.forms[0].submit()');
?>
<table width=80%>
<tr><td>
<?
labeledCheckbox('Email Info', 'email', $email);echo "<td>";
labeledCheckbox('Phone Info', 'phone', $phone);echo "<td>";
labeledCheckbox('Login Info', 'login', $login);echo "<td>";
labeledCheckbox('Alarm Info', 'alarm', $alarm);echo "<td>";
labeledCheckbox('Vet Info', 'vet', $vet);
?>
<tr><td>
<?
labeledCheckbox('Emergency Info', 'contact', $contact);echo "<td>";
labeledCheckbox('Sitter Info', 'provider', $provider);echo "<td>";
labeledCheckbox('Address Info', 'address', $address);echo "<td>";
labeledCheckbox('Pets', 'pets', $pets);echo "<td>";
labeledCheckbox('Keys', 'key', $key);
?>
</tr></table></form><p>
<?

$exclude = array_keys($_POST);
echoButton('','Show/Hide Rules',
  'if(document.getElementById("rules").style.display == "none") document.getElementById("rules").style.display="inline";'.
  'else document.getElementById("rules").style.display="none";');
?>
<div id='rules' style='display:none;'><p><? echo rules() ?></div>
<?

echo "<table>";
foreach($clientids as $clientid)
	if($problems = clientProblems($clientid, $exclude)) {
		$clientDetails = getOneClientsDetails($clientid);
		$link = fauxLink($clientDetails['clientname'], "openConsoleWindow(\"clienteditor\", \"client-edit.php?id=$clientid\",800,800)", 'Edit Client');
		echo "<tr><td valign=top>$link</td>".
						"<td valign=top>".join('<br>',$problems)."</td></tr>";
	}
echo 	"</table>";
?>
<script language='javascript'>
function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

</script>
<?
// ***************************************************************************

include "frame-end.html";
exit;
