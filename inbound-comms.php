<? // inbound-comms.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

$locked = locked('o-');

$starting = $starting? date('Y-m-d', strtotime($starting)) : '';
$ending = $ending? date('Y-m-d', strtotime($ending)) : '';
$pageTitle = "Inbound Communication Log";

// #########################
include "frame.html";
echo "<div id='inboundmsgs'></div>";
hiddenElement('userid', $id);
hiddenElement('correspondents', $correspondents);
echo "<img src='art/spacer.gif' height=300>";


?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
function addReminder(type, id) {
	//https://leashtime.com/reminder-edit.php?pop=1&provider=2
	//https://leashtime.com/reminder-edit.php?pop=1&client=47
	openConsoleWindow('reminder', 'reminder-edit.php?pop=1&'+type+'='+id,600,500);
}

function searchForMessages() {
	searchForMessagesWithSort('');
}

function sortMessages(field, dir) {
	searchForMessagesWithSort(field+'_'+dir);
}

function checkEmail(addressField) {
	var addr;
	if(!(addr = jstrim(document.getElementById(addressField).value))) alert('Please supply an email address first.');
	else if(!validEmail(addr))  alert('The format of this email address is not valid.');
	else ajaxGetAndCallWith("ajax-email-check.php?email="+addr, postEmailCheck, addressField);	
}

function postEmailCheck(addressField, response) {
	alert(response);
}



//setPrettynames('msgsstarting,Starting date for messages,msgsending,Starting date for messages');
function searchForMessagesWithSort(sort) {
	var correspondentRadios = document.getElementsByName('correspondents');
	var correspondents = '';
	for(var i=0; i < correspondentRadios.length; i++) {
		var radio = correspondentRadios[i];
		if(radio.checked) correspondents = radio.value;
	}
	
	var includerequestsBoxes = document.getElementsByName('includerequests');
	var includerequests = [];
	for(var i=0; i < includerequestsBoxes.length; i++) {
		var box = includerequestsBoxes[i];
		if(box.checked) includerequests[includerequests.length] = box.id;
	}
//alert(includerequests);
	includerequests = includerequests.join(',');
  if(MM_validateForm(
		  'msgsstarting', '', 'isDate',
		  'msgsending', '', 'isDate')) {
		var userid = document.getElementById('userid').value;
		var starting = document.getElementById('msgsstarting').value;
		var ending = document.getElementById('msgsending').value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		if(sort) sort = '&sort='+sort;
		correspondents = "&correspondents="+correspondents;
		includerequests = "&includerequests="+includerequests;
//alert('?id='+userid+starting+ending+sort+correspondents+includerequests);		
		var url = 'inbound-comms-list.php';
    ajaxGet(url+'?id='+userid+starting+ending+sort+correspondents+includerequests, 'inboundmsgs')
	}
}

function openComposer() {
	openConsoleWindow('emailcomposer', 'manager-comm-composer.php?user=<?= $id ?>',500,500);
}

function openLogger(emailOrPhone) {
	openConsoleWindow('messageLogger', 'comm-logger.php?user=<?= $id ?>&log='+emailOrPhone,500,500);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function selectAlRequestTypes(checked) {
	$("input[name='includerequests']").prop('checked', checked);
}


var starting = '<?= date('Y-m-d', strtotime("-30 days")) ?>';
ajaxGet('inbound-comms-list.php?id=<?= $id ?>&starting='+starting, 'inboundmsgs');

function goToCommTab(type, id) {
	var label = type == 'provider' ? 'sitter' : type;
	if(confirm("You are about to leave this page to go to this "+label+"'s Communication tab."))
		document.location.href = type + "-edit.php?tab=communication&id="+id;
}

function goToReminders(type, id) {
	var label = type == 'provider' ? 'sitter' : type;
	if(confirm("You are about to leave this page to go to this "+label+"'s Reminders page."))
		document.location.href = "reminders.php?"+type+"="+id;
}
<? dumpPopCalendarJS(); ?>
</script>


<?

include "frame-end.html";
