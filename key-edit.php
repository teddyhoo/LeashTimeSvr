<? //key-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "pet-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";

require_once "key-fns.php";

// Determine access privs
$needed = '@ka,@ki,@#km';
locked($needed);
//if(mattOnlyTEST()) echo print_r($_SESSION['rights'],1)."<br>vs $needed: ".adequateRights($needed);
$right = keyManagementRight();

extract($_REQUEST);

if(!isset($back)) {
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	$back = "$mein_host$this_dir/index.php";
}

if($_POST) {
	$keyId = saveKey(array_merge($_POST));
//echo $back;exit;		
//print_r($_POST);
	if($popup) {
		echo "<script language='javascript'>window.close();if(window.opener.refresh) window.opener.refresh();</script>";
		exit;
	}
	$_SESSION['frame_message'] = "Key saved.";
	if($keyId) $back = globalURL("key-edit.php?id=$keyId");	
	if($another) 	header ("Location: key-launch-edit.php");
	else header ("Location: $back");
	exit();
}
//phpinfo();
$pageTitle = "Edit Key";

if(isset($popup)) echo <<<POP
<body style='padding:10px;'>
<h2>$pageTitle</h2>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" /> 
POP;
else include "frame.html";
// ***************************************************************************
if($message) {
	echo "<p style='color:green;'>$message</p>";
	unset($_POST['message']);
}
if(!isset($id) && isset($client))
	$id = fetchRow0Col0("SELECT keyid FROM tblkey WHERE clientptr = $client");
$key = getKey($id);

if(!$key && $client) {
	echo "No key is registered for this client.<p>";
	$key = array('clientptr'=>$client);
}




echo "<table><tr><td valign=top>";
echo "<table>
<form name='keyeditor' method='POST'><tr><td style='width:370px;'>";
hiddenElement('another', '');
keyTableForEditor($key);
if(isset($back)) hiddenElement('back', $back);
echo "</td><td valign=top>";
echo "</td></tr></table></div>";
if($right == 'ka') {
	echoButton('', 'Save Changes', 'checkAndSubmit()', null, null);
	echo " ";
	echoButton('', 'Save Changes & Edit Another', 'checkAndSubmit("another")', null, null);
	echo " ";
}
else echo "<span class='tiplooks'>No Changes will be saved.</span>";
echoButton('', 'Cancel', "document.location.href=\"$back\"", null, null);
echo " ";
echo "</form>";
if(isset($id)) echoButton('', 'Print All Key Labels', "printAllKeyLabels(\"{$key['keyid']}\")", null, null);
echo " ";
fauxLink('<img src="art/help.jpg" height=20 width=20 style="position:relative;top:5px;">', 'printHint()', 0, 'Click here for printing help.');
echo "</td><td valign=top>";
$needyProviders = providersWhoNeedKeyForDaysAhead($key, 14);
if($needyProviders) {
	echo "<table width=300><tr><th colspan=3 align=center>Sitters Who Need This Key Within 14 Days</th></tr>";
	echo "<tr><th>Sitter</th><th>Date required</th><th>&nbsp;</th></tr>";
	foreach($needyProviders as $prov) {
		$date = shortDate(strtotime($prov['date']));
		$pName = $prov['provider'] ? $prov['provider'] : '<i>Unassigned</i>';
		echo "<tr><td>$pName</td><td>$date</td>";
		if($prov['providerptr'])
			echo "<td><img style='cursor:pointer;' src='art/request-checkout.gif' 
								onClick='requestCheckout($id, {$prov['providerptr']}, \"{$prov['date']}\")' 
								title='Email key check-out notification.'></td>";
		else echo "<tr><td>&nbsp;</td>";
	}
	echo "</tr></table>";
}
if($needyProviders) echo "<hr>";
echo "<p style='text-align:center;font-weight:bold;'>Key History</p>";
if(isset($id)) echo keyHistoryTable($id);
echo "</td></tr></table>";
echo "<br><img src='art/spacer.gif' width=1 height=300>";
?>

<div style='display:none' id='printhint'>
<span class='fontSize1_1em'>
<span class='fontSize1_1em boldfont'>Printing Key Labels</span>
<p>
When printing out key labels, we want to make sure that Adobe Acrobat (or
whatever PDF reader software you use) does not change the size of the printed labels, 
because LeashTime sizes them specially to fit particular paper labels.
<p>
In most programs you can control the printed size in the <b>Print...</b> dialog that 
appears when you go to print the key labels.
<p>
Look for a setting called "Page Scaling" and make sure it is set to "none".  If there 
is no such option and there is instead a check box labeled "Fit to page", make sure that
it is unchecked.  Different versions of Acrobat offer this setting in different ways.  
You may need to hunt a bit.  And of course, the settings will probably differ if you do not
use Adobe Acrobat.
</span>
</div>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

function printHint() {
	$.fn.colorbox({html:$('#printhint').html(), width:"550", height:"470", scrolling: true, opacity: "0.3"});
}


function printKeyLabel(keyid, copy ) {
	var bin = document.keyeditor.bin.value;
	openConsoleWindow('labelprinter', "label-print.php?keys="+keyid+'-'+copy+"&bin="+urlencode(bin), 300, 200);
}

function printAllKeyLabels(keyid) {
	var bin = document.keyeditor.bin.value;
	var numCopies = document.getElementById('copies').options[document.getElementById('copies').selectedIndex].value;
	if(numCopies == 0) {
		alert("There must be at least one copy to proceed.  Add a copy and Save Changes.  Then you can print.");
		return;
	}
	var keys = [];
	for(var i=1;i<=numCopies;i++) keys[keys.length] = keyid+'-'+i;
	openConsoleWindow('labelprinter', "label-print.php?keys="+keys.join(',')+"&bin="+urlencode(bin), 300, 200);
}

function requestCheckin(keyid, copy) {
	openConsoleWindow('keymsgcomposer', "key-checkin-request.php?key="+keyid+'-'+copy, 440, 500);
}

function requestCheckout(keyid, prov, date) {
	openConsoleWindow('keymsgcomposer', "key-checkout-request.php?key="+keyid+'&prov='+prov+'&date='+date, 440, 500);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}


function showKeyCopies(sel) {
	var num = sel.options[sel.selectedIndex].value;
	for(var i=1; i<=<?= $maxKeyCopies ?>; i++) {
		var displayMode = i <= num ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
		document.getElementById("row_possessor_"+i).style.display=displayMode;
	}
}

function ensureOneKey() {
	var arr = ['locklocation', 'description', 'bin'];
	var found = false;
	for(var i=0;i<arr.length;i++)
		if(document.getElementById(arr[i]).value.length != 0) found = true;
	if(found && document.getElementById('copies').value == 0)
	  document.getElementById('copies').value = 1;
}

function checkAndSubmit(another) { // 0002-23
	var keyid = document.keyeditor.keyid.value;
	if(another) document.keyeditor.another.value = 1;
	if(keyid && document.keyeditor.bin.value != document.keyeditor.originalbin.value) {
		if(confirm("You changed the Key Hook to ["+document.keyeditor.bin.value+"] from ["+
								document.keyeditor.originalbin.value+"]\n\nDo you want to reprint the key label(s)?\n\n"+
								"Press Ok to print labels and save, or Cancel to skip printing."))
				printAllKeyLabels(keyid);
	}
	document.keyeditor.submit(keyid);
}

/**
*
*  URL encode / decode
*  http://www.webtoolkit.info/
*
**/
 
function urlencode(string) {
	return escape(_utf8_encode(string));
}

// private method for UTF-8 encoding
function _utf8_encode(string) {
	string = string.replace(/\r\n/g,"\n");
	var utftext = "";
	for (var n = 0; n < string.length; n++) {
		var c = string.charCodeAt(n);
		if (c < 128) {
			utftext += String.fromCharCode(c);
		}
		else if((c > 127) && (c < 2048)) {
			utftext += String.fromCharCode((c >> 6) | 192);
			utftext += String.fromCharCode((c & 63) | 128);
		}
		else {
			utftext += String.fromCharCode((c >> 12) | 224);
			utftext += String.fromCharCode(((c >> 6) & 63) | 128);
			utftext += String.fromCharCode((c & 63) | 128);
		}
	}
	return utftext;
}


</script>
<?
// ***************************************************************************
if(!isset($popup))include "frame-end.html";
?>

