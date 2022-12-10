<? //key-lost.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "pet-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";

require_once "key-fns.php";

$scriptName = 'key-lost.php';

// Determine access privs
locked('+ka,+ki,+#km');
$right = keyManagementRight();

extract($_REQUEST);

$pageTitle = "Report Key Lost";

if($confirm) {
	$parts = explode('|', $confirm);
	if(transferKey($parts[0], $parts[1], 'missing'));
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	header("Location: $mein_host$this_dir/$scriptName?message=Key+%23+{$parts[0]}+has+been+reported+lost.");
}
else if(isset($keyid) && $keyid) {
	$keyDescription = identifyKey($keyid);
	if(is_string($keyDescription)) $error = $keyDescription;
}
else if(isset($description) && $description) {
	$pattern = $exact ? $description : str_replace('*', '%', "%$description%");
	$keyMatches = fetchCol0("SELECT keyid FROM tblkey WHERE description LIKE '$pattern'");
	if(count($keyMatches) == 1) {
		$keyDescription = identifyKey($keyMatches[0]);
		if(is_string($keyDescription)) $error = $keyDescription;
	}
	else if($keyMatches) $error = "This description matches more than one key.";
	else $error = "No keys with this description found.";
}

include "frame.html";
// ***************************************************************************
if(!$safes) {
	echo "<h2>You must define at least one <a href='key-safe-editor.php'>Key Safe</a> before you can report any keys lost.</h2>";
	include "frame-end.html";
	exit;
}

if($message) {
	echo "<p style='color:green;'>$message</p>";
	unset($_POST['message']);
}
?>
<form name='lostKeyForm' method='POST'>
<table border=0 bordercolor=red><tr><td valign=top>
<table width=400 border=0 bordercolor=blue>
<?
if(!$error && $right == 'ki' && $keyDescription && $keyDescription['possessor'] != $_SESSION["providerid"]) 
	$error = "This key is not checked out to you.";

if(!$error && $keyDescription['copyNumber'] && !$keyDescription['badCopyMessage']) {
	hiddenElement('confirm','');
	echo "<tr><td colspan=2><h2>Step 2: Report Key Lost</h2></td></tr>\n";
	echo "<tr><td colspan=2>";
	if($keyDescription['possessor'] != 'missing')
	  echoButton('', 'Report Key Lost', 
	  						"confirmKeyLost({$keyDescription['key']['keyid']}, {$keyDescription['copyNumber']})",
	  						"BigButton", "BigButtonDown");
	echo "</td></tr>\n";
}	
if(is_string($keyDescription)) $error = $keyDescription;
if(!$error && $keyDescription) {
	$keyLabel = formattedKeyId($keyDescription['key']['keyid'], $keyDescription['copyNumber']);
	echo "<tr><td colspan=2>&nbsp;</td></tr>";
	labelRow('Key ID:', '', $keyLabel);
	labelRow('', '', "<img src='barcode/image.php?code=$keyLabel&style=196&type=C128A&width=120&height=60&xres=1&font=5'>",
	          '', '', '', '', 'raw');
	labelRow('Key Hook:', '', $keyDescription['key']['bin']);
	$clientLabel = $keyDescription['client']['clientname'];
	if($keyDescription['client']['pets']) $clientLabel .= " (".petNamesCommaList($keyDescription['client']['pets'], 40).")";
	labelRow('Client:', '', $clientLabel, '', 'fontSize1_2em boldfont');
//print_r($keyDescription);	

	if($keyDescription['copyNumber']) {
		labelRow('Copy #:', '', $keyDescription['copyNumber']);
		if(!$keyData['badCopyMessage']) labelRow('Key location::', '', $keyDescription['possessorname']);
	}
	//else if($keyDescription['badCopyMessage']) labelRow('Note:', '', $keyDescription['badCopyMessage']);
	else if($keyDescription['badCopyMessage']) { 
		$noteToShow = $keyDescription['badCopyMessage'];
		$key = $keyDescription['key'];
		$keyLinks = array();
		for($i=1; $i <= $maxKeyCopies; $i++)
			if($key["possessor$i"])
				$keyLinks[] = fauxLink(noBreaks(formattedKeyId($key['keyid'], $i)." (".lookUpKeyPossessor($key["possessor$i"]).")"), 
																"keyPicked(\"".formattedKeyId($key['keyid'], $i)."\")", 'noEcho');
		if($keyLinks) $noteToShow .= "<br>Please select one of these copies to report missing:<br>"
																	.join(', ', $keyLinks)
																	."<br>or<br>".fauxLink("Edit the Key", "editKey({$key['keyid']})", 'noEcho');
		else $noteToShow .= "<br>There are no known copies of this key.<br>Please edit the key to continue.";
		hiddenElement('keyid', '');
		labelRow('Note:', '', $noteToShow, null, 'tiplooksleft messagetextarea', null, null, true);
	}

	
//print_r($keyDescription);	exit;
	
	if($keyDescription['possessor'] == 'missing') {
	  labelRow('Note:', '', '<b>Key has already been reported missing.</b>', null, null, null, null, 'raw');
			$showConfirmButton = false;
	}
	else if($keyDescription['copyNumber']) {
		// if copyNumber and badCopyMessage, offer to save this as a new copy with that number
		if($keyDescription['badCopyMessage']) {
			labelRow('Note:', '', $keyDescription['badCopyMessage'], null, null, null, null, 'raw');
			$showConfirmButton = false;
		}
		// else offer to confirm key reported lost
		else $showConfirmButton = true;
	}
	echo "</table><p>";
	if($showConfirmButton) {
		//hiddenElement('confirm','');
		//echoButton('', 'Check In Key', "confirmCheckIn({$keyDescription['key']['keyid']}, {$keyDescription['copyNumber']})");
	  //echo '<p>';
	}
	if($right == 'ka') echoButton('', 'Edit Key', "editKey({$keyDescription['key']['keyid']})");
	echo '  ';
	echoButton('', 'Re-enter Key ID', 'startOver()');
}
else {
// if error, offer button to reset and try again
	echo "<tr><td colspan=2><h2>Step 1: Identify Key</h2></td></tr>\n";
	if($error) echo "<tr><td><span style='color:red;'>$error</span>";
	if($keyMatches) {
		$keys = fetchKeyValuePairs("SELECT keyid, CONCAT_WS(' ', fname, lname), lname, fname
																FROM tblkey
																LEFT JOIN tblclient ON clientid = clientptr
																WHERE keyid IN (".join(',', $keyMatches).")
																ORDER BY lname, fname");
		foreach($keys as $keyid => $label) $keys[$keyid] = fauxLink($label, "document.location.href=\"$scriptName?keyid=$keyid\"", 1);
		echo "<p>Please select one of the following clients or re-enter your search:<ul><li>".join('<li>', $keys)."</ul>";
		$keyid = '';
	}
	echo "<tr><td>";
	labeledInput('Key ID:', 'keyid', $keyid);
	echo "<span class='tiplooks'>e.g., 0242-01</span><p>or<p>";
	labeledInput('Key Description:', 'description', $description);
	$exact = isset($exact) ? $exact : ($error ? $exact : 1);
	labeledCheckbox('exact', 'exact', $exact, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
	echoButton('', 'Enter Key', 'initialSubmit()');
	echo "<p>or ";
	echoButton('', 'Find Client Key', 'findClientKey()');
	echo "</table>";
}
?>
<td valign=top>
<table><tr>
<td valign=top><img src='art/housekey.gif'>
<td valign=middle><img src='art/key-arrow.gif'>
<td valign=bottom><img src='art/lost.gif'>
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
	keyPicked(id);
}

function keyPicked(id) {
	document.location.href='key-lost.php?keyid='+id;
	//document.lostKeyForm.keyid.value = id;
	//document.lostKeyForm.submit();
}

function editKey(id) {
	document.location.href='key-edit.php?id='+id+'&back=<?= urlencode($backLink); ?>';
}

function confirmKeyLost(keyid,copy) {
	document.lostKeyForm.confirm.value=keyid+'|'+copy;
	document.lostKeyForm.submit();
}

function startOver() {
	document.location.href='<?= $scriptName ?>';
}

function initialSubmit() {
	var oneRequired, 
		keyid = jstrim(document.lostKeyForm.keyid.value), 
		description = jstrim(document.lostKeyForm.description.value);
	if((keyid && description) || !(keyid || description))
		oneRequired = "Either Key ID or Key description (but not both) must be supplied";
	if(MM_validateForm(oneRequired, '', 'MESSAGE'))
		document.lostKeyForm.submit();
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
	var kval = ''+document.lostKeyForm.keyid.value;
	if(kval.length < 7) return true;
	var validChars = "0123456789";

	for(var i=0;i<7;i++) {
		if(i == 4 && (kval.charAt(i) != '-')) return true;
		else if (i != 4 && (validChars.indexOf(kval.charAt(i)) == -1)) return true;
	}
	document.lostKeyForm.submit();
}

if(document.lostKeyForm.keyid) {
	document.lostKeyForm.keyid.onkeyup=autoSubmit;
	document.lostKeyForm.keyid.onpaste=autoSubmit;
	document.lostKeyForm.keyid.focus();
}
</script>