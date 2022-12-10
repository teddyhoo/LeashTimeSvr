<? // calendar-package-irregular.php
/* 
	inputs:
	packageid
	primary - primary provider
*/	


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";
require_once "appointment-calendar-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$locked = locked('o-');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract(extractVars('packageid,primary', $_REQUEST));

$client = fetchFirstAssoc(
	"SELECT *, CONCAT_WS(' ', fname, lname) as clientname
	FROM tblservicepackage LEFT JOIN tblclient ON clientid = clientptr 
	WHERE packageid = '$packageid' AND irregular=1");
if(!$client) $error = 'Package not found.';
else {
}
$windowTitle = "EZ Schedule for {$client['clientname']}";
$extraBodyStyle = 'background:white;';
require "frame-bannerless.php";

// ***************************************************************************
if($error) {
	echo "<font color='red'></font>";
	exit;
}
/*echo "<table width=100%>
<tr><td style='font-weight:bold;font-size:1.5em'>$windowTitle</td><td align='right'>";
echoButton('', 'Done', 'doneAction()');
echo "</td><tr></table>";
*/
dumpCalendarLooks(100, 'lightblue');

// #############################
if(!$roDispatcher) {
?>
<div style='background-color:#ddddff;display:block;padding:3px;'>
<table width=100%>
<tr><td width=150>
<span style='font-size:1.1em;font-weight:bold;'>With </span><div style='border:solid red 2px;display:inline;padding:2px;'>Selected Visits</div>: 
</td>
<td align=left><select id='action' onChange='operationChanged(this)'>
<option value=''>-- Pick One --
<!-- option value='clearsels'>Clear Selections -->
<option value='reassign'>Reassign to...
<option value='discount'>Apply Discount...
<option value='cancel'>Cancel
<option value='uncancel'>Un-Cancel (Reactivate)
<option value='delete'>Delete
<option value='copyall'>Copy to All Days
<option value='copyallfuture'>Copy to All Future Days
<option value='copyeverythree'>Copy Every Three Days Forward
</select>
<?
echo " ";
fauxLink('Select All Visits', 'selectAll(1)');
echo " - ";
fauxLink('De-select All Visits', 'selectAll(0)');
echo "<span class='tiplooks'>&nbsp;&nbsp;&nbsp;Click on the dog to select individual visits.</span>";

?>
</td><td align='right'>
<? echoButton('', 'Done', 'doneAction()') ?>
</td></tr>
<tr><td>
<div style='display:inline' id='selectionCount'></div>
<p></td><td>
<?
providerSelectElement($client);
?>
<div style='display:inline' id='discountEditor'>
<?
dumpServiceDiscountEditor($clientDetails);
echoButton('', 'Apply', 'applyDiscount()');
?>
</div></td></tr></table></div>



<?
}
// #############################
irregularPackageTable($packageid, $client['clientid'], $primary);

include "js-refresh.php";
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='appointment-calendar-fns.js'></script>
<script language='javascript'>

var apptSelectionCache = '';
var surchargeSelectionCache = '';
function cacheSelections(clear) {
	if(clear) {
		apptSelectionCache = '';
		surchargeSelectionCache = '';
	}
	else {
		apptSelectionCache = collectSelections('appt');
		surchargeSelectionCache = collectSelections('surcharge');
	}
}

function update(aspect, message) {
	refresh(); 
	var sels = '';
	if(false && aspect == 'appointments') {
		var idtype;
		sels = apptSelectionCache ? apptSelectionCache.split(',') : null;
// *************	
	idtype = 'appt';
	if(sels) for(var i=0;i<sels.length;i++) {
	//if(!document.getElementById("appt_"+sels[i])) alert("SEL: appt_"+sels[i]	);	
			document.getElementById(idtype+"_"+sels[i]).value = 1;
			//selectionStyle(document.getElementById("appttd_"+sels[i]).style, 1);
			//var style = document.getElementById("appttd_"+sels[i]).style;
			var style = document.getElementById(idtype+"_"+sels[i]).parentNode.style;
			style.borderColor= 'red';
			style.borderWidth= 3;
	}
// *************	
		//if(sels) reselect(sels, 'appt');
		sels = surchargeSelectionCache ? surchargeSelectionCache.split(',') : null;
//alert(sels);		
// *************	
	idtype = 'surcharge';
	if(sels) for(var i=0;i<sels.length;i++) {
	//if(!document.getElementById("appt_"+sels[i])) alert("SEL: appt_"+sels[i]	);	
			document.getElementById(idtype+"_"+sels[i]).value = 1;
			//selectionStyle(document.getElementById("appttd_"+sels[i]).style, 1);
			//var style = document.getElementById("appttd_"+sels[i]).style;
			var style = document.getElementById(idtype+"_"+sels[i]).parentNode.style;
			style.borderColor= 'red';
			style.borderWidth= 3;
	}
// *************	
		//if(sels) reselect(sels, 'surcharge');
	}
	if(window.opener && window.opener.update) {
		window.opener.update('appointments', 'refresh');
	}
	if(message && message.indexOf('MESSAGE:') == 0) alert(message.substring('MESSAGE:'.length));
}

function reselect(sels, idtype) {
	for(var i=0;i<sels.length;i++) {
	//if(!document.getElementById("appt_"+sels[i])) alert("SEL: appt_"+sels[i]	);	
			document.getElementById(idtype+"_"+sels[i]).value = 1;
			//selectionStyle(document.getElementById("appttd_"+sels[i]).style, 1);
			//var style = document.getElementById("appttd_"+sels[i]).style;
			var style = document.getElementById(idtype+"_"+sels[i]).parentNode.style;
			style.borderColor= 'red';
			style.borderWidth= 3;
	}
}

function doneAction() {
	/*var w = window.open("x",'editappt',
		'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+5+',height='+5);
	w.close();*/
	if(window.opener && window.opener.document.getElementById('quitButton')) 
		window.opener.document.getElementById('quitButton').click();
	window.open('', '_self', '');  // for chrome, IE
	window.close();
}

function setProvider(element) {
	var prov = element.options[element.selectedIndex].value;
	if(!prov) return;
	var sels = collectSelections('appt');
	if(!sels) alert('No appointments were selected.');
	else ajaxGetAndCallWith("appointment-reassign.php?prov="+prov+"&ids="+sels, update, sels);
}

function applyDiscount() {
	var discount = document.getElementById("discount")
	discount = discount.options[discount.selectedIndex].value.split('|')[0];
	var memberid = document.getElementById("memberid").value;
	var sels = collectSelections('appt');
	if(!sels) alert('No appointments were selected.');
	ajaxGetAndCallWith("appointment-discount.php?discount="+discount+"&memberid="+memberid+"&ids="+sels, update, sels);
}

function cancelSelections(cancelFlg) {
	var sels = collectSelections('appt');
	ajaxGetAndCallWith("appointment-cancel.php?cancel="+cancelFlg+"&id="+sels, update, sels);
	sels = collectSelections('surcharge');
	ajaxGetAndCallWith("surcharge-cancel.php?cancel="+cancelFlg+"&id="+sels, update, sels);
}

function in_array(el, els) {
	for(var i = 0; i < els.length; i++)
		if(els[i] == el) return true;
	return false;
}

function deleteSelections(cancelFlg) {
	var undeletableAppointments = document.getElementById('undeletableAppointments').innerHTML.split(',');
	var undeletableSurcharges = document.getElementById('undeletableSurcharges').innerHTML.split(',');
	var sels = collectSelections('appt').split(',');
	var toDelete = new Array();
	for(var i = 0; i < sels.length; i++) 
		if(!in_array(sels[i], undeletableAppointments))
			toDelete[toDelete.length] = sels[i];
	if(toDelete.length > 0) 
		ajaxGetAndCallWith("appointment-delete.php?id="+toDelete.join(','), update, '');
	
	toDelete = new Array();
	sels = collectSelections('surcharge').split(',');
	for(var i = 0; i < sels.length; i++) 
		if(!in_array(sels[i], undeletableSurcharges))
			toDelete[toDelete.length] = sels[i];
	if(toDelete.length > 0) 
		ajaxGetAndCallWith("surcharge-delete.php?id="+toDelete.join(','), update, '');
	
}

function toggleSelection(elid, newstate) {
	var id = elid.split('_')[1];
	var newstate = isNaN(newstate) ? (document.getElementById(elid).value == '1' ? 0 : '1') : newstate;
	document.getElementById(elid).value = newstate;
	selectionStyle(document.getElementById("appttd_"+id).style, newstate);
	displaySelectionCount();
}

function operationChanged(element) {
	var action = element.options[element.selectedIndex].value;
	document.getElementById("provider_selection").style.display = action == 'reassign' ? 'inline' : 'none';
	
	document.getElementById("discountEditor").style.display = action == 'discount' ? 'inline' : 'none';
	if(action == 'discount') discountChanged(document.getElementById("discount"));

	
	if(action == 'cancel') {cancelSelections(1);  element.selectedIndex = 0; }
	if(action == 'delete') {deleteSelections();  element.selectedIndex = 0; }
	else if(action == 'uncancel') {cancelSelections(0); element.selectedIndex = 0; }
	else if(action == 'copyall') {pasteAllDays(); element.selectedIndex = 0; }
	else if(action == 'copyallfuture') {pasteAllFutureDays(); element.selectedIndex = 0; }
	else if(action == 'copyeverythree') {pasteEveryThreeDays(); element.selectedIndex = 0; }
	else if(action == 'reassign') ;
	else if(action == 'clearsels') {
		var allTDs = document.getElementsByTagName('td');
		for(var i=0;i<allTDs.length;i++)
			if(allTDs[i].id.indexOf('appttd_') == 0) {
				selectionStyle(allTDs[i].style, 'off');
				var id = allTDs[i].id.split('_')[1];
				document.getElementById("appt_"+id).value = 0;
			}
		displaySelectionCount();
		element.selectedIndex = 0; 
	}
}

function readyToPaste(day) {
	if(countSelections() == 0) showNoSelNote(day, 1);
	else showPasteLink(day, 1);
}

function showPasteLink(day, show) {
	document.getElementById('dom_'+day).style.display= show ? 'inline' : 'none';
	if(!show) showNoSelNote(day, show);
	return true;
}

function showNoSelNote(day, show) {
	document.getElementById('nosel_'+day).style.display= show ? 'inline' : 'none';
	return true;
}

function pasteFuture(targetRange, selectedSourceDays, interval) {
	var day = selectedSourceDays ? selectedSourceDays[0].substring('box_'.length) : null;
	var sels = collectSelections('appt', day);
	if(!sels)  {
		alert("You must first select some visits to copy.");
		return;
	}
	ajaxGetAndCallWith("appointments-copy.php?client=<?= $client['clientptr'] ?>&packageid=<?= $packageid ?>&target="+targetRange+'_'+day+"&sels="+sels, 
		update, sels);

}

function pasteHere(day) {
	var sels = collectSelections('appt');
	if(!sels)  {
		alert("You must first select some visits to copy.");
		return;
	}
	ajaxGetAndCallWith("appointments-copy.php?client=<?= $client['clientptr'] ?>&packageid=<?= $packageid ?>&target="+day+"&sels="+sels, update, sels);

}

function pasteAllDays() {
	pasteHere('all')
}

function pasteAllFutureDays() {
	var daysar;
	if((daysar = collectSelectedDays('appt')).length == 1
		|| confirm('Appointments have been selected on multiple days.\nOnly appointments on the first day will be copied.\nContinue?'))
		pasteFuture('allfuture', daysar)
}

function pasteEveryThreeDays() {
	var daysar;
	if((daysar = collectSelectedDays('appt')).length == 1
		|| confirm('Appointments have been selected on multiple days.\nOnly appointments on the first day will be copied.\nContinue?'))
		pasteFuture('futureskip_3', daysar)
}

function collectSelectedDays(apptSurchargeOrAll) {
	apptSurchargeOrAll = !apptSurchargeOrAll ? 'all' : apptSurchargeOrAll;
	var all = apptSurchargeOrAll == 'all';
	var allEls = document.getElementsByTagName('input');
	var parent;
	var days={};
	for(var i=0;i<allEls.length;i++) {
		var id = allEls[i].id;
		if(allEls[i].value == 1 && 
			  (((all || apptSurchargeOrAll == 'appt') && id.indexOf('appt_') == 0) ||
			   ((all || apptSurchargeOrAll == 'surcharge') && id.indexOf('surcharge_') == 0))
			) {
				days[getBoxDay(allEls[i])] = 1
			}
	}
  var daysar = [];
  for(var i in days) daysar.push(i);
	return daysar;
}

function getBoxDay(el) {
	var parent = el;
	while(parent = parent.parentNode)
		if(parent.id && (parent.id.indexOf('box_') == 0))
			return parent.id;
}

function displaySelectionCount() {
	document.getElementById("selectionCount").innerHTML = "Visits selected: "+countSelections();
}

function collectSelections(apptSurchargeOrAll, day) {
	apptSurchargeOrAll = !apptSurchargeOrAll ? 'all' : apptSurchargeOrAll;
	var all = apptSurchargeOrAll == 'all';
	var sels=[];
	var allEls = document.getElementsByTagName('input');
	for(var i=0;i<allEls.length;i++) {
		var id = allEls[i].id;
		if(allEls[i].value == 1 && 
			  (((all || apptSurchargeOrAll == 'appt') && id.indexOf('appt_') == 0) ||
			   ((all || apptSurchargeOrAll == 'surcharge') && id.indexOf('surcharge_') == 0))
			) {
			//== null || (getBoxDay(getBoxDay(allEls[i]) == 'box_'+day)))
			sels[sels.length] = id.split('_')[1];
		}
	}
	return sels.join(',');
}

function selectAll(state) {
	var sels=[];
	var allEls = document.getElementsByTagName('input');
	for(var i=0;i<allEls.length;i++)
		if(allEls[i].id.indexOf('appt_') == 0)
			toggleSelection(allEls[i].id, state);
}

function countSelections() {
	var n=0;
	var allEls = document.getElementsByTagName('input');
	for(var i=0;i<allEls.length;i++)
		if(allEls[i].id.indexOf('appt_') == 0 && allEls[i].value == 1) {
			var style = document.getElementById("appttd_"+allEls[i].id.split('_')[1]).style;
			selectionStyle(style, 'on');
			n++;
		}
	return n;
}

function selectionStyle(style, onOff) {
	onOff = (onOff == 'off' || onOff == 0) ? false : onOff;
	style.borderColor= onOff ? 'red' : 'black';;
	style.borderWidth= onOff ? 3 : 1;
}

function discountChanged(el) {
	var displayMode = el.selectedIndex == 0
											|| el.options[el.selectedIndex].value.split('|')[1] == 0
										? 'none'
										: 'inline';
	document.getElementById('memberidrow').style.display = displayMode;
}



operationChanged(document.getElementById('action'));
displaySelectionCount();


</script>
<?
function providerSelectElement($client) {
	availableProviderSelectElement($client, $date=null, 'provider_selection', '--Select a Sitter--', $choice=null, "setProvider(this)");
}

	