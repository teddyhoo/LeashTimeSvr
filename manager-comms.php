<? // manager-comms.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

$locked = locked('o-');

$id = $_REQUEST['id'];
$mgrs = getManagers(array($id), $ltStaffAlso=false);
$mgr = $mgrs[$id];
$mgr['name'] = "{$mgr['fname']} {$mgr['lname']}";

$starting = $starting? date('Y-m-d', strtotime($starting)) : '';
$ending = $ending? date('Y-m-d', strtotime($ending)) : '';
$pageTitle = "{$mgr['name']}'s Communication";

// #########################
include "frame.html";
echo "<div id='managermsgs'></div>";
echo "<img src='art/spacer.gif' height=300>";
hiddenElement('userid', $id);


?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
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
	
  if(MM_validateForm(
		  'msgsstarting', '', 'isDate',
		  'msgsending', '', 'isDate')) {
		var userid = document.getElementById('userid').value;
		var starting = document.getElementById('msgsstarting').value;
		var ending = document.getElementById('msgsending').value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		if(sort) sort = '&sort='+sort;
		var url = 'manager-comms-list.php';
    ajaxGet(url+'?id='+userid+starting+ending+sort, 'managermsgs')
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
var starting = '<?= date('Y-m-d', strtotime("-30 days")) ?>';
if(<?= $id ? 'true' : 'false' ?>) ajaxGet('manager-comms-list.php?id=<?= $id ?>&starting='+starting, 'managermsgs');





<? dumpPopCalendarJS(); ?>
</script>


<?

include "frame-end.html";
