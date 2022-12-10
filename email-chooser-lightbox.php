<? // email-chooser-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "frame-bannerless.php";

$prompt = $_REQUEST['prompt'];
$prompt = $prompt ? $prompt : 'Type: ';
$update = $_REQUEST['update'];
if($_REQUEST['title']) echo "<h2>{$_REQUEST['title']}</h2>";
?>
<div id='searchbar' style='width:95%;padding-top:5px;'>
<? /*$_SESSION["mobiledevice"] */ if($screenIsMobile /*|| $_SERVER['REMOTE_ADDR'] == '68.225.89.173'*/) {
} ?>
<form name='chooser'>
<?
$role = $_REQUEST['role'] ? $_REQUEST['role'] : 'sitter'; 

hiddenElement('email', '');
//labeledInput('ROLE;', 'role', $value=$role, $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=null, $noEcho=false);
hiddenElement('role', $role);
hiddenElement('targetElement', $_REQUEST['targetElement']);
echoButton('', 'Cancel', 'quit()');
echo "<p>";

$options = explodePairsLIne('Sitters|sitter||Clients|client||Staff|staff');

//unset($options['Staff']);

$radios = radioButtonSet('ROLERADIOS', $value=$role, $options, $onClick="changeRole(this)", $labelClass=null, $inputClass=null, $rawLabel=false);
echo "Role: ".join(' ', $radios)."<p>";
?>

<?= "<img src='art/spacer.gif' width=40 height=1>".$prompt ?> <input id='searchbox' onKeyUp="showMatches(this)" onMouseout="delayhidemenu()"> <p><span id='providername'></span> <span id='chosenlabel' style='display:none'> chosen.<br>&nbsp;<br><?= $noteline ?>Click  'Ok' to continue<br>or choose another client<br>or click 'Cancel' to return without making a choice.</p>

<div id='results' style='width: 400px;height:100px;'></div>

</form>
</div>
<style>
td {padding:4px;}
</style>
<script language='javascript' src='popitmenu2.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function changeRole(choiceEl) {
	var forName, labels = document.getElementsByTagName('label');
	for(var i=0; i< labels.length; i++)
		if((forName = labels[i].getAttribute('for')).indexOf('ROLERADIOS') == 0) {
			var el = document.getElementById(forName);
			labels[i].className = el.value == choiceEl.value ? 'fontSize1_3em' : '';
		}

	document.getElementById("role").value = choiceEl.value;
	showMatches(document.getElementById("searchbox"));
}

function showMatches(element, test) {
	if(element.value.length < 2) return;
	var pat = escape(element.value);
	if(pat[0] == '$' || pat[0] == '-' || pat[0] == '#' ) pat = pat.substring(1);
	var role = document.getElementById('role').value;
	var prefix = role == 'sitter' ? '$' : (role == 'client' ? '' : (role == 'staff' ? '//STAFF//' : '???'));
	pat = prefix+escape(element.value);
	ajaxGetAndCallWith('getSearchMatches.php?includeEmails=1&pat='+pat, rebuildMenu, element);
}

function rebuildMenu(element, content) {
	if(!content) {
		showResults('No results.');
		return;
	}
	var url = 'client-edit.php?tab=services&id=';
	var html = '<p class="tiplooks">Click any email address below to choose it.</p>';
	html += '<table border=1 bordercolor=grey>';
	var arr = content.split('||');
	for(var i = 0; i < arr.length; i++) {
		if(arr[i] == '--') html += '<hr>';
		else if(arr[i] == '-+-') html += '<hr style="border: 0;color: #9E9E9E;background-color: #9E9E9E;height: 1px;">';
		else {
			var line = arr[i].split('|');
			var providerid = line[0];
			if(providerid.indexOf('PROVIDERS:') == 0 )
				providerid = providerid.substring('PROVIDERS:'.length);
			var label = line[1].replace(/'/g, "&apos;"); // '
			html += '<tr><td>'+label+'</td>';
			if(line.length == 3) { // emails
				var emails = ""+line[2];
				emails = emails.split(',');
				if(emails.length == 0) html += '<td><i>No email address</i></td>';
				else for(var j = 0; j < emails.length; j++) {
					emails[j] = '<a onclick=\'setChoice("'+emails[j]+'")\'>'+emails[j]+'</a>';
				}
				html += '<td>'+emails.join(', ')+'</td>';
			}
			else html += '<td><i>No email address</i></td>';
		}
		html += '</tr>';
	}
	html += '</table>';
	showResults(html);
	//delayhidemenu();
}

function showResults(html) {
	document.getElementById('results').innerHTML = html;
}

function setChoice(email) {
	var error = '';
	var targetElement = document.getElementById('targetElement').value;
	if(!parent) error = "no parent found.";
	else if(!parent.update) error = "parent has no update function.";
	else if(!targetElement) error = "no targetElement chosen.";
	else parent.update(targetElement, email);
	if(error) alert(error);
	// close lightbox
	else parent.$.fn.colorbox.close();
	return;
}

function checkAndSubmit() {
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

changeRole(document.getElementById('ROLERADIOS_'+document.getElementById('role').value));
</script>
