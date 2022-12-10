<? // calendar-package-irregular-embedded.php
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
require_once "time-framer-mouse.php"; // for change time...


//extract(extractVars('packageid,primary', $_REQUEST));

$client = fetchFirstAssoc(
	"SELECT *, CONCAT_WS(' ', fname, lname) as clientname
	FROM tblclient
	WHERE clientid = $client");
if(!$client) $error = 'Package not found.';
else {
}

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
<div style='/*background-color:#ddddff*/background-image:url("art/bluebarfade.jpg");display:block;padding:3px;padding-top:0px;'>
<table width=100% bordercolor=black border=0>
<tr><td width=150>
<span style='font-size:1.1em;font-weight:bold;'>With </span><div style='border:solid red 2px;display:inline;padding:2px;'>Selected Visits</div>: 
</td>
<td align=left colspan=2><select id='ezscheduleaction' onChange='operationChanged(this)'>
<option value=''>-- Pick Something to Do --
<!-- option value='clearsels'>Clear Selections -->
<option value='reassign'>Reassign to...
<option value='discount'>Apply Discount...
<option value='service_change'>Change Service...
<option value='time_change'>Change Time...<? // made public 11/30/2020 ?>
<option value='cancel'>Cancel
<option value='uncancel'>Un-Cancel (Reactivate)
<option value='delete'>Delete
<option value='copyall'>Copy to All Days
<option value='copyallfuture'>Copy to All Future Days
<option value='copyeverytwo'>Copy Every Other Day Forward
<option value='copyeverythree'>Copy Every Three Days Forward
<? if(dbTEST('k9krewe')) echo "<option value='copyevery7'>Copy Every Week Forward
<option value='copyevery14'>Copy Every Other Week Forward
<option value='copyevery21'>Copy Every 3rd Week Forward
<option value='copyevery28'>Copy Every 4th Week Forward
";
?>
</select>
<?
makeTimeFramer('timeFramer', 'narrow');


echo " ";
fauxLink('Select All Visits', 'selectAll(1)');
echo " - ";
fauxLink('De-select All Visits', 'selectAll(0)');
echo "<span class='tiplooks'>&nbsp;&nbsp;&nbsp;Click on the dog to select a visit.</span>";

$clientDiscount = fetchRow0Col0("SELECT discountptr FROM relclientdiscount WHERE clientptr = {$client['clientid']} LIMIT 1");
if($clientDiscount) {
	$history = findPackageIdHistory($packageid, $client['clientid'], false);
	$history[] = $packageid;
	$history = join(',', $history);
	$appts = fetchCol0("SELECT * FROM tblappointment WHERE packageptr IN ($history) ORDER BY date, starttime");
	if($appts) {
		$discounts = array_unique(fetchCol0("SELECT discountptr FROM relapptdiscount WHERE appointmentptr IN (".join(',', $appts).")"));
		$numDiscountedAppts = count($discounts);
		$discounts = array_unique($discounts);
		if($discounts != array($clientDiscount) || $numDiscountedAppts != $appts) {
			$clientDiscount = fetchRow0Col0("SELECT label FROM tbldiscount WHERE discountid = $clientDiscount LIMIT 1");
			$discountMessage = "Current discount for this client: $clientDiscount. Not all of these visits have this discount";
		}
	}
}

?>
</td><td align='right'>
<? //echoButton('', 'Done', 'doneAction()') ?>
</td></tr>
<tr><td>
<div style='display:inline' id='selectionCount'></div>
<p></td><td>
<?
providerSelectElement($client);
serviceSelectElement();
timeofDayElement();
?>
<div style='display:inline' id='discountEditor'>
<?
dumpServiceDiscountEditor($clientDetails);

echoButton('', 'Apply', 'applyDiscount()');
?>
</div>
</td>
<td align=right><?= $saveButtonHTML ?></td></tr></table></div>



<?
}
// #############################
echo "<span class='tiplooks'>$discountMessage</span>";
echo "<div style='padding:0px;position:relative;left:-5px;'>";
irregularPackageTable($packageid, $client['clientid'], $primaryProvider);
echo "</div>";
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

var lastService = 0;

function update(aspect, message) {  //  OVERRIDDEN BY update() in service-irregular.php
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
			style.borderWidth= 'thick';
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
			style.borderWidth= 'thick';
	}
// *************	
		//if(sels) reselect(sels, 'surcharge');
	}
	if(window.opener && window.opener.update) {
		window.opener.update('appointments', 'refresh');
	}
	if(message && message.indexOf('MESSAGE:') == 0) alert(message.substring('MESSAGE:'.length));
}

var ezEditorHeight = 590, ezEditorWidth = 615;

<? if(strpos($_SERVER["HTTP_USER_AGENT"], 'iPad') !== FALSE) echo "\nezEditorHeight = 670;\n"; ?>

function addBillable(day, packageid, clientid, prov) {
	var args = '?date='+day+'&packageptr='+packageid+'&clientptr='+clientid+'&providerptr='+prov;
	$.fn.colorbox({href: "ez-edit.php"+args, width:ezEditorWidth, height:ezEditorHeight, iframe:true, scrolling: "auto", opacity: "0.3"});
}

function editBillable(id, objtype) {
	var args = '?id='+id+'&objtype='+objtype;
	$.fn.colorbox({href: "ez-edit.php"+args, width:ezEditorWidth, height:ezEditorHeight, iframe:true, scrolling: "auto", opacity: "0.3"});
}

function reselect(sels, idtype) {
	for(var i=0;i<sels.length;i++) {
	//if(!document.getElementById("appt_"+sels[i])) alert("SEL: appt_"+sels[i]	);	
			document.getElementById(idtype+"_"+sels[i]).value = 1;
			//selectionStyle(document.getElementById("appttd_"+sels[i]).style, 1);
			//var style = document.getElementById("appttd_"+sels[i]).style;
			var style = document.getElementById(idtype+"_"+sels[i]).parentNode.style;
			style.borderColor= 'red';
			style.borderWidth= 'thick';
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
	else {
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("appointment-reassign.php?prov="+prov+"&ids="+sels, update, sels);
	}
}

function setService(element) {
	var service = element.options[element.selectedIndex].value;
	if(!service) return;
	var sels = collectSelections('appt');
	if(!sels) alert('No appointments were selected.');
	else {
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("appointment-change-service.php?servicecode="+service+"&ids="+sels, update, sels);
	}
}

function changeVisitTime(elid) {
	var tod = document.getElementById(elid).innerHTML;
	//alert(elid);
	if(!tod) return;
	// validate time frame here
	if(!RegExp('^[0-9].*$').test(tod)) return;
	var sels = collectSelections('appt');
	if(!sels) alert('No appointments were selected.');
	else {
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("appointment-change-time.php?tod="+tod+"&ids="+sels, update, sels);
	}
}

function applyDiscount() {
	var discount = document.getElementById("discount")
	discount = discount.options[discount.selectedIndex].value.split('|')[0];
	var memberid = document.getElementById("memberid").value;
	var sels = collectSelections('appt');
	if(!sels) alert('No appointments were selected.');
	$('.BlockContent-body').busy("busy");
	
//alert("appointment-discount.php?discount="+discount+"&memberid="+memberid+"&ids="+sels);	
<? //if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { ?>
//alert("appointment-discount.php?discount="+discount+"&memberid="+memberid+"&ids="+sels);
//return;
<? //} ?>
	ajaxGetAndCallWith("appointment-discount.php?discount="+discount+"&memberid="+memberid+"&ids="+sels, update, sels);
}

function cancelSelections(cancelFlg) {
	var sels = collectSelections('appt');
	$('.BlockContent-body').busy("busy");
	ajaxGetAndCallWith("appointment-cancel.php?cancel="+cancelFlg+"&id="+sels, update, sels);
	sels = collectSelections('surcharge');
	$('.BlockContent-body').busy("busy");
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
	if(toDelete.length > 0) {
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("appointment-delete.php?from=EZ+Editor+Menu&id="+toDelete.join(','), update, '');
	}
	toDelete = new Array();
	sels = collectSelections('surcharge').split(',');
	for(var i = 0; i < sels.length; i++) 
		if(!in_array(sels[i], undeletableSurcharges))
			toDelete[toDelete.length] = sels[i];
	if(toDelete.length > 0) {
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("surcharge-delete.php?id="+toDelete.join(','), update, '');
	}
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
	document.getElementById("servicecode_selection").style.display = action == 'service_change' ? 'inline' : 'none';
	document.getElementById("hideabletimeofdayDIV").style.display = action == 'time_change' ? 'inline' : 'none';
	
	document.getElementById("discountEditor").style.display = action == 'discount' ? 'inline' : 'none';
	if(action == 'discount') discountChanged(document.getElementById("discount"));

	
	if(action == 'cancel') {cancelSelections(1);  element.selectedIndex = 0; }
	if(action == 'delete') {deleteSelections();  element.selectedIndex = 0; }
	else if(action == 'uncancel') {cancelSelections(0); element.selectedIndex = 0; }
	else if(action == 'copyall') {pasteAllDays(); element.selectedIndex = 0; }
	else if(action == 'copyallfuture') {pasteAllFutureDays(); element.selectedIndex = 0; }
	else if(action == 'copyeverytwo') {pasteAtDayInterval(2); element.selectedIndex = 0; }
	else if(action == 'copyeverythree') {pasteAtDayInterval(3); element.selectedIndex = 0; }
	else if(action == 'copyevery7') {pasteAtDayInterval(7); element.selectedIndex = 0; }
	else if(action == 'copyevery14') {pasteAtDayInterval(14); element.selectedIndex = 0; }
	else if(action == 'copyevery21') {pasteAtDayInterval(21); element.selectedIndex = 0; }
	else if(action == 'copyevery28') {pasteAtDayInterval(28); element.selectedIndex = 0; }
	else if(action == 'reassign' || action == 'service_change') ;
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
	else if(action == 'time_change') {
		//alert('boop');
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
	$('.BlockContent-body').busy("busy");
	var url = "appointments-copy.php?client=<?= $client['clientid'] ?>&packageid=<?= $packageid ?>&target="+targetRange+'_'+day+"&sels="+sels;
	ajaxGetAndCallWith(url, update, sels);

}

function pasteHere(day) {
	var sels = collectSelections('appt');
	if(!sels)  {
		alert("You must first select some visits to copy.");
		return;
	}
	var url = "appointments-copy.php?client=<?= $client['clientid'] ?>&packageid=<?= $packageid ?>&target="+day+"&sels="+sels;
	$('.BlockContent-body').busy("busy");

	ajaxGetAndCallWith(url, update, sels);

}

function pasteAllDays() {
	pasteHere('all')
}

function collectSelectedDaysAndWarn(apptSurchargeOrAll) {
	var daysar;
	if((daysar = collectSelectedDays('appt')).length == 1
		|| (daysar.length > 0
				&& confirm('Appointments have been selected on multiple days.\nOnly appointments on the first day will be copied.\nContinue?')))
		return daysar;
	return null;
}

function pasteAllFutureDays() {
	var daysar;
	if(daysar = collectSelectedDaysAndWarn('appt'))
		pasteFuture('allfuture', daysar)
}

function pasteAtDayInterval(interval) {
	var daysar;
	if(daysar = collectSelectedDaysAndWarn('appt'))
		pasteFuture('futureskip_'+interval, daysar)
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
	style.borderWidth= onOff ? 'thick' : 'thin';
}

function discountChanged(el) {
	var displayMode = el.selectedIndex == 0
											|| el.options[el.selectedIndex].value.split('|')[1] == 0
										? 'none'
										: 'inline';
	document.getElementById('memberidrow').style.display = displayMode;
}

<? dumpTimeFramerJS('timeFramer'); ?>

operationChanged(document.getElementById('ezscheduleaction'));
displaySelectionCount();


</script>
<?
function providerSelectElement($client) {
	availableProviderSelectElement($client, $date=null, 'provider_selection', '--Select a Sitter--', $choice=null, "setProvider(this)", 'includeUnassigned');
}


function serviceSelectElement() {
  $serviceSelections = array_merge(array('--Select a Service--'=>''), getServiceSelections());
  labeledSelect('', "servicecode_selection", $value=null, $serviceSelections, $labelClass=null, $inputClass=null, 'setService(this)', $noEcho=false);
}

function timeofDayElement() {
	echo "<div id='hideabletimeofdayDIV' style='display:none;'>";
	buttonDiv('timeofdayDIV', 'timeofday', 		"showTimeFramerInContentDiv(null/*event*/, \"timeofdayDIV\");", 'Choose a time frame', $value='', $extraStyle='display:inline;width:300px;padding-right:3px;', $title=null, $class=null);
	echo " ";
	echoButton('', 'Change Time', 'changeVisitTime("timeofdayDIV")', 'Button', 'ButtonDown');
	echo "</div>";
}
