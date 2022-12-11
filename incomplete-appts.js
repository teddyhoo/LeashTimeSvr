// incomplete-appts.js

var incompleteClient = 0;

function showAllIncomplete() {
	var enddate = new Date();
	document.incompleteform.incompletestart.value = '';
	var usformat = true;
	if(shortdateformat != undefined) usformat = shortdateformat.indexOf('/') > 0;
	if(usformat)
		document.incompleteform.incompleteend.value = enddate.getMonth()+1+'/'+enddate.getDate()+'/'+enddate.getFullYear();
	else 
		document.incompleteform.incompleteend.value = enddate.getDate()+'.'+(enddate.getMonth()+1)+'.'+enddate.getFullYear();
	showIncomplete();
}

function showIncomplete(futureAlso) {
	futureAlso = typeof futureAlso == 'undefined' ? '' : futureAlso;
  if(MM_validateForm('incompletestart', '', 'isDate', 'incompleteend', '', 'isDate')) {
		updateIncompleteAppointments(null, futureAlso);
	}
}

function updateIncompleteAppointments(sortArg, futureAlso) {
	var url = 'incomplete-appts-section.php?updateList=incomplete_list&futurealso='+futureAlso+'&';
	var startdate = document.getElementById('incompletestart').value;
	var enddate = document.getElementById('incompleteend').value;
	if(undefined == sortArg) sortArg = '';
	sortArg = '&sort='+sortArg;
	var clientArg = incompleteClient > 0 ? '&client='+incompleteClient : '';
//alert(url+'starting='+startdate+'&ending='+enddate+sortArg+clientArg);	
	pleaseWaitWhileIncompleteListIsBuilt();
	ajaxGet(url+'starting='+startdate+'&ending='+enddate+sortArg+clientArg, 'incomplete_list');
}

function pleaseWaitWhileIncompleteListIsBuilt() {
	document.getElementById('incomplete_list').innerHTML = '<p style=\"font-size:1.1em\">Please wait while the list is built...</p>';
}

var lastSort;

function sortIncompleteJobs(key, direction) {
	lastSort = key+'_'+direction;
	updateIncompleteAppointments(lastSort);
}

function markVisitsComplete() {
	operateOnIncompleteSelections('complete', 'pleasewait');
}

function sendReminders() {
	operateOnIncompleteSelections('sendReminders', 'pleasewait');
	//alert("The reminders will be sent in the next few minutes.");
}

function operateOnIncompleteSelections(operation, pleasewait) {
	var choices = collectIncompleteApptSelections();

	if(choices[0].length + choices[1].length == 0) {
		alert("You must first select at least one incomplete visit.");
		return;
	}
	if(pleasewait) document.getElementById('incomplete_list').innerHTML = 'Please wait.';
	
	var url = 'incomplete-appts-handle.php?operation='+operation+'&ids='+choices[0].join(',')+'&surcharges='+choices[1].join(',');
  var xh = getxmlHttp();
  xh.open("GET",url,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { 
		update('incomplete_list', null);
		if(operation = 'complete') updateIncompleteSectionHeader(xh.responseText);
		} 
	}
  xh.send(null);
}


function updateIncompleteSectionHeader(responseText) {
	if(typeof responseText == 'string' && responseText.indexOf('|') == 0) {
		alert(responseText.substring(1));
		return;
	}
	else numIncomplete = responseText;
	if(!document.getElementById('section_title_incomplete')) return;
	if(numIncomplete == -1) {
		var xh = getxmlHttp();
		var url = 'incomplete-appts-handle.php?operation=countincomplete';
		xh.open("GET",url,true);
		xh.onreadystatechange=function() { if(xh.readyState==4) { 
			updateIncompleteSectionHeader(xh.responseText);
			} 
		}
		xh.send(null);
	}
	document.getElementById('section_title_incomplete').innerHTML = "Incomplete Visits (Total: "+numIncomplete+")";
	if(parent && parent.update) parent.update('incompletecount', numIncomplete);
}

function collectIncompleteApptSelections() {
	var selectedAppts = new Array();
	var selectedSurcharges = new Array();
	for(var i=0;i<document.incompleteform.elements.length;i++) {
		var el = document.incompleteform.elements[i];
		if(el.name && (el.name.indexOf('appt_') == 0)) {
		  if(el.checked) selectedAppts[selectedAppts.length] = el.name.substring(5);
		}
		if(el.name && (el.name.indexOf('sur_') == 0)) {
		  if(el.checked) selectedSurcharges[selectedSurcharges.length] = el.name.substring(4);
		}
	}
	var result = new Array();
	result[0] = selectedAppts;
	result[1] = selectedSurcharges;
	return result;
}

