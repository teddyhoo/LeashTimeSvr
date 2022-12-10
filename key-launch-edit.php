<? //key-launch-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";

require_once "key-fns.php";

$scriptName = 'key-launch-edit.php';


// Determine access privs
locked('+ka,+ki,#km');
$right = keyManagementRight();

extract($_REQUEST);

$pageTitle = "Edit Key";

if($keyid) {
	$keyDescription = identifyKey($keyid);
	if(is_string($keyDescription)) $error = $keyDescription;
	if(!is_string($keyDescription)) {
		$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
		header("Location: $mein_host$this_dir/key-edit.php?id={$keyDescription['key']['keyid']}");
		exit;
	}
}
else if(isset($description) && $description) {
	$pattern = $exact ? $description : str_replace('*', '%', "%$description%");
	$keyMatches = fetchCol0("SELECT keyid FROM tblkey WHERE description LIKE '$pattern'");
	if(count($keyMatches) == 1) {
		$keyDescription = identifyKey($keyMatches[0]);
		if(is_string($keyDescription)) $error = $keyDescription;
		else {
			$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
			header("Location: $mein_host$this_dir/key-edit.php?id={$keyDescription['key']['keyid']}");
			exit;
		}
	}
	else if($keyMatches) $error = "This description matches more than one key.";
	else $error = "No keys with this description found.";
}

include "frame.html";
// ***************************************************************************
if($message) {
	echo "<p style='color:green;'>$message</p>";
	unset($_POST['message']);
}
?>
<form name='keychoiceform' method='POST'>
<table><tr><td valign=top>
<table width=400>
<?

// if error, offer button to reset and try again
	echo "<tr><td colspan=2><h2>Step 1: Identify Key</h2></td></tr>\n";
	if($error) echo "<tr><td><span style='color:red;'>$error</span>";
	if($keyMatches) {
		$keys = fetchKeyValuePairs("SELECT keyid, CONCAT_WS(' ', fname, lname), lname, fname
																FROM tblkey
																LEFT JOIN tblclient ON clientid = clientptr
																WHERE keyid IN (".join(',', $keyMatches).")
																ORDER BY lname, fname");
		foreach($keys as $keyid => $label) $keys[$keyid] = fauxLink($label, "document.location.href=\"key-edit.php?id=$keyid\"", 1);
		echo "<p>Please select one of the following clients or re-enter your search:<ul><li>".join('<li>', $keys)."</ul>";
		$keyid = '';
	}
	echo "<tr><td>";
	labeledInput('Key ID:', 'keyid', $keyid);
	echo "<p>or<p>";
	labeledInput('Key Description:', 'description', $description);
	$exact = isset($exact) ? $exact : ($error ? $exact : 1);
	labeledCheckbox('exact', 'exact', $exact, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
	echoButton('', 'Enter Key', 'initialSubmit()');
	echo "<p>or ";
	echoButton('', 'Find Client Key', 'findClientKey()');
	echo "</table>";
	

?>
<td valign=top>
<table><tr>
<td valign=top><img src='art/edit-key.gif'>
</table>
</td>
</tr>
</table>
</form>
<?
// ***************************************************************************
include "frame-end.html";
$backLink = $mein_host.$_SERVER["PHP_SELF"].'?keyid='.$keyid;

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function findClientKey() {
	openConsoleWindow('clientkeypicker', 'client-key-picker.php?loc=all', 600, 650);
}
	
function update(id) {
	document.keychoiceform.keyid.value = id;
	document.keychoiceform.submit();
}

function editKey(id) {
	document.location.href='key-edit.php?id='+id+'&back=<?= urlencode($backLink); ?>';
}

function confirmCheckOut(keyid,copy) {
	document.keychoiceform.confirm.value=keyid+'|'+copy+'|'+document.keychoiceform.dest.value;
	document.keychoiceform.submit();
}

function startOver() {
	document.location.href='key-check-out.php';
}

function initialSubmit() {
	var oneRequired, 
		keyid = jstrim(document.keychoiceform.keyid.value), 
		description = jstrim(document.keychoiceform.description.value);
	if((keyid && description) || !(keyid || description))
		oneRequired = "Either Key ID or Key description (but not both) must be supplied";
	if(MM_validateForm(oneRequired, '', 'MESSAGE'))
		document.keychoiceform.submit();
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function autoSubmit() { // 0002-23
	var kval = ''+document.keychoiceform.keyid.value;
	if(kval.length < 7) return true;
	var validChars = "0123456789";

	for(var i=0;i<7;i++) {
		if(i == 4 && (kval.charAt(i) != '-')) return true;
		else if (i != 4 && (validChars.indexOf(kval.charAt(i)) == -1)) return true;
	}
	document.keychoiceform.submit();
}


if(document.keychoiceform.keyid) {
	document.keychoiceform.keyid.onkeyup=autoSubmit;
	document.keychoiceform.keyid.onpaste=autoSubmit;
	document.keychoiceform.keyid.focus();
}
</script>