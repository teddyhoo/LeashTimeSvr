/* ipad-appointment-dragdrop.js */


/* AJAX Calls */

function cancelAll() {
	if(!confirm("Are you sure?")) return;
  var xh = getxmlHttp();
  xh.open("GET","appointment-dragdrop-ajax.php?fn=cancelAll",true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { 
		refreshAppointmentReassignmentTables();} 
	}
  xh.send(null);
}

var LastWAGProvs = '';
function executeAll() {
  var xh = getxmlHttp();
  xh.open("GET","appointment-dragdrop-ajax.php?fn=executeAll",true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { 
		refreshAppointmentReassignmentTables();} 
	}
  xh.send(null);
  if(LastWAGProvs != '') openWAG(LastWAGProvs);
	alert("All reassignments performed.");
}

function cancelReassignment(dragAppt, backTo) {
	if(!confirm("Cancel reassignment (return to "+backTo+")?")) return;
  var xh = getxmlHttp();
  xh.open("GET","appointment-dragdrop-ajax.php?fn=cancelReassignment&appt="+dragAppt,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { 
		refreshAppointmentReassignmentTables();} 
	}
  xh.send(null);
}

function reassignAppointment(dragAppt, prov, origprov) {
  var xh = getxmlHttp();
  prov = prov == null ? "" : prov;
  xh.open("GET","appointment-dragdrop-ajax.php?fn=reassignappt&appt="+dragAppt+"&prov="+prov+"&origprov="+origprov,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) {
		if(xh.responseText) alert(xh.responseText);
		else refreshAppointmentReassignmentTables();} 
	}
  xh.send(null);
}

function refreshAppointmentReassignmentTables() {
	setPrettynames('starting','Starting date','ending','Ending date');
	if(!MM_validateForm(
					'starting','','R', 
					'starting','','isDate', 
					'starting','not','isPastDate',
					'ending','','isDate', 
					'ending','not','isPastDate',
					'starting','ending','datesInOrder'
					)) return;
	var start = document.reassignmentform.starting.value;
	var end = document.reassignmentform.ending.value;
	var dateArg = "&start="+start+"&end="+end;
	var provider = document.reassignmentform.allproviders ? document.reassignmentform.allproviders.value : 0;
	if(initialprovider) {
		provider = initialprovider;
		initialprovider = null;
	}
	var otherprovider = document.reassignmentform.otherproviders ? document.reassignmentform.otherproviders.value : 0;
	pleaseWait("appointments");
	pleaseWait("reassigned");
	pleaseWait("allappointments");
  ajaxGetAppts("appointment-dragdrop-ajax.php?fn=appointments&prov="+provider+dateArg, "appointments");
  ajaxGetAppts("appointment-dragdrop-ajax.php?fn=reassigned&prov="+provider+dateArg, "reassigned");
  ajaxGetAppts("appointment-dragdrop-ajax.php?fn=allappointments&prov="+otherprovider+"&exclude="+provider+dateArg, "allappointments");
  var wagButton = provider == 0 || otherprovider == 0
   ? "" 
   : "<img src='art/spacer.gif' width=20 height=1>"+
   	 "<input value='Week View for Both' class='Button' onclick='openWAG(\""+provider+","+otherprovider+
     "\")' onmouseover='this.className=\"ButtonDown\"' onmouseout='this.className=\"Button\"' type='button'>";
  //alert(wagButton);
	document.getElementById('workingdate').innerHTML = 
		end && end != start ? 'Working Dates: '+start+' - '+end : 'Working Date: '+start+wagButton;
		
	//$('#reassigned').each(function(i, el) {alert(el.id);});
	webkit_drop.add('allappointments', {
		onDrop:function(el) {
			var dest = document.reassignmentform.otherproviders.value;
			var srcZone = el.getAttribute('zone');
			if(srcZone == 'allappointments') return;
			var fromProv = srcZone == 'appointments' ? document.reassignmentform.allproviders.value : '';
			if(dest == 0) {
				alert('Please choose a sitter or "Unassigned" first.');
				return;
			}
			reassignAppointment(el.id.substring('dap_'.length), dest, fromProv);
			return true;
		}});

	webkit_drop.add('appointments', {
		onDrop:function(el) {
			var dest = document.reassignmentform.allproviders.value;
			var srcZone = el.getAttribute('zone');
			if(srcZone == 'appointments') return;
			var fromProv = srcZone == 'allappointments' ? document.reassignmentform.otherproviders.value : '';
			if(dest == 0) {
				alert('Please choose a sitter or "Unassigned" first.');
				return;
			}
			reassignAppointment(el.id.substring('dap_'.length), null, null);
			return true;
		}});

}

function pleaseWait(divname) {
	document.getElementById(divname).innerHTML = '<i>Please wait while this updates.</i>';
}

function ajaxGetAppts(url, target) {
  var xh = getxmlHttp();
  xh.open("GET",url,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { 
		if(target) {
			document.getElementById(target).innerHTML=xh.responseText; 
		}
		$('.dragappt').each(function(i, el) {
			//alert(el.id);
			new webkit_draggable(el.id, {revert:true});
			});
} 
	}
  xh.send(null);
}


function getxmlHttp() {
  //if(xmlHttp) return;
  try {
  // Firefox, Opera 8.0+, Safari
  var xmlHttp=new XMLHttpRequest();
  } catch (e) {
    // Internet Explorer
    try {
    var xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
    } catch (e) {
      try {
        var xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
      } catch (e) {
        alert("Your browser does not support AJAX!");
        return false;
      }
    }
  }
  return xmlHttp;
}


refreshAppointmentReassignmentTables();

