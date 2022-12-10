<? // provider-chooser-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "frame-bannerless.php";

$prompt = $_REQUEST['prompt'];
$prompt = $prompt ? $prompt : 'Choose Sitter: ';
$update = $_REQUEST['update'];
?>
<div id='searchbar' style='width:95%;padding-top:5px;'>
<? /*$_SESSION["mobiledevice"] */ if($screenIsMobile /*|| $_SERVER['REMOTE_ADDR'] == '68.225.89.173'*/) {
require_once "gui-fns.php";
echo " ";
} ?>
<form name='chooser'>
<?
hiddenElement('providerid', '');
echoButton('', 'Ok', 'checkAndSubmit()');
echo "<img src='art/spacer.gif' width=20 height=1>";
echoButton('', 'Cancel', 'quit()');
echo "<p>";

if($_GET['note'])
	$noteline = "<span id='noteline' style='display:none;'>{$_GET['note']}: <input id='note' style='width:200px'></span><p>";
?>

<?= "<img src='art/spacer.gif' width=40 height=1>".$prompt ?> <input id='searchbox' onKeyUp="showMatches(this)" onMouseout="delayhidemenu()"> <p><span id='providername'></span> <span id='chosenlabel' style='display:none'> chosen.<br>&nbsp;<br><?= $noteline ?>Click  'Ok' to continue<br>or choose a different sitter<br>or click 'Cancel' to return without making a choice.</p>
<input type='hidden' name='providerid' id='providerid'>
</form>
</div>
<script language='javascript' src='popitmenu2.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function showMatches(element, test) {
	if(element.value.length < 2) return;
	var pat = escape(element.value);
	if(pat[0] == '$' || pat[0] == '-' || pat[0] == '#' ) pat = pat.substring(1);
	pat = "$"+escape(element.value);
	ajaxGetAndCallWith('getSearchMatches.php?pat='+pat, rebuildMenu, element);
}

function rebuildMenu(element, content) {
	if(!content) {
		showmenu(element,'');
		return;
	}
	var url = 'client-edit.php?tab=services&id=';
	var html = '';
	var arr = content.split('||');
	for(var i = 0; i < arr.length; i++) {
		if(arr[i] == '--') html += '<hr>';
		else if(arr[i] == '-+-') html += '<hr style="border: 0;color: #9E9E9E;background-color: #9E9E9E;height: 1px;">';
		else {
			var line = arr[i].split('|');
			var providerid = line[0];
			if(providerid.indexOf('PROVIDERS:') == 0 )
				providerid = providerid.substring('PROVIDERS:'.length);
			var providername = line[1].replace(/'/g, "&apos;"); // '
			<? $onFocusBlur = "onFocus='this.className=\"popitfocus\"' onBlur='this.className=\"popitmenu\"'"; ?>
			html += '<a onclick=\'setChoice('+providerid+', "'+providername+'")\''+" onFocus='this.className=\"popitfocus\"' onBlur='this.className=\"popitmenu\"'>"+line[1]
			+""
			+'</a>';
		}
	}
	showmenu(element,html);
	//delayhidemenu();
}

function setChoice(id, name) {
	document.getElementById('providerid').value=id;
	var providername = document.getElementById('providername');
	providername.innerHTML = name;
	providername.style.display='inline';
	if(document.getElementById('noteline')) document.getElementById('noteline').style.display='inline'; 
	//else alert('bang!');
	document.getElementById('chosenlabel').style.display='inline';
}

function checkAndSubmit() {
	var error = '';
	var id;
	if((id = document.getElementById('providerid').value) != '') {
		// update parent
		var note = document.getElementById('note') ? document.getElementById('note').value : null;
		if(!parent) error = "no parent found.";
		else if(!parent.update) error = "parent has no update function.";
		else if(!id) error = "no client chosen.";
		else parent.update('<?= $update ?>', id+'|'+document.getElementById('providername').innerHTML+(note ? "|"+note : ""));
		if(error) alert(error);
		// close lightbox
		else parent.$.fn.colorbox.close();
		return;
	}
	alert('Please choose a sitter first, or click the Cancel button to quit.');
}

function quit() {
	var error;
	if(!parent) error = "no parent found.";
	else if(!parent.colorbox) error = "parent has no colorbox.";
	if(error) alert(error);
	else parent.$.fn.colorbox.close();
}

document.forms[0].searchbox.focus();
if(document.getElementById('note')) {
	document.getElementById('note').addEventListener('keypress', 
		function (e) {
			var keyCode = (e.keyCode ? e.keyCode : e.which);
			var ban = {124: 'pipe', 39: 'apostrophe', 34: 'dubbaquote'}
			//alert(keyCode);
			if(typeof ban[keyCode] != 'undefined') {
				e.preventDefault();
			}
		});
;
}

</script>
