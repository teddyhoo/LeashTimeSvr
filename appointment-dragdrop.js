/* appointment-dragdrop.js */

document.onmouseup = mouseUp;
document.onmousemove = dragOnMouseMove;
var dragObject     = null;

var dragOrigColor     = null;
var dragAppt     = null;
var dragZone     = null;
var dragSelectionColor     = '#72CA00';

function appointmentDown(object, appt, zone) {
  dragObject = object; 
  dragAppt = appt;
  dragOrigColor = object.style.background;
	dragObject.style.background = dragSelectionColor;
	document.body.style.cursor = "no-drop";
	document.getElementById("appointments").style.cursor="pointer";
	document.getElementById("allappointments").style.cursor = 
	  document.reassignmentform.otherproviders.value ? "pointer" : "no-drop";
	document.getElementById("reassigned").style.cursor = zone == "reassigned" ? "pointer" : "no-drop";
	dragZone = zone;
	return false;
}


function appointmentUp(object, zone) {
	if(!dragObject) return false;
	if(zone != dragZone) {
		if(zone == 'allappointments') {
			if(document.reassignmentform.otherproviders.value == 0) {
				alert("Please choose a sitter first.");
				return;
			}
			reassignAppointment(dragAppt, document.reassignmentform.otherproviders.value, document.reassignmentform.allproviders.value);
		}
		else if(zone == 'appointments') reassignAppointment(dragAppt, null, null);
		else if(zone == 'reassigned') {/* do nothing */
		  var origcolor = document.getElementById('reassigned').style.background;
			document.getElementById('reassigned').style.background='blue';
			document.getElementById('reassigned').style.background='red';
			document.getElementById('reassigned').style.background='green';
			document.getElementById('reassigned').style.background='yellow';
			document.getElementById('reassigned').style.background=origcolor;
		}
		else alert(zone);
	}
	clearDragObjects();
	return true;
}

function dragOnMouseMove(e) {
	//var x = e ? e.screenX : event.screenX;
	//document.getElementById('xxx').innerHTML="["+x+"]";
	var stop = false;
	if(dragObject != null) {
		var iconStyle = document.getElementById("icon").style;
		if(iconStyle.display != "inline") {
		  iconStyle.display="inline";
		  iconStyle.zIndex=99;
		}

		var coords = mouseCoords(e || window.event);
		
		var contentPosition = getAbsolutePosition(document.getElementById('ContentDiv'));
		coords.x -= contentPosition.x;
		coords.y -= contentPosition.y;

		iconStyle.left=coords.x+'px';//-16
		iconStyle.top=coords.y+'px';//-20
	  return stop;
	}
	return !stop;
}

function clearDragObjects() {
	document.getElementById("icon").style.display="none";
	document.getElementById("icon").style.zIndex=-1;
	
	document.body.style.cursor = "default";
	document.getElementById("appointments").style.cursor="default";
	document.getElementById("allappointments").style.cursor="default";
	document.getElementById("reassigned").style.cursor = "default";
	if(dragObject != null) {
		dragObject.style.background=dragOrigColor;
	  dragObject.style.cursor=null;
  }
  dragOrigColor = null;
	dragObject = null;
	dragAppt = null;
	dragZone = null;
}

function getAbsolutePositionForEvent(event) {
	return getAbsolutePosition(event.target);
}

function getAbsolutePosition(element) {
    var r = { x: element.offsetLeft, y: element.offsetTop };
    if (element.offsetParent) {
      var tmp = getAbsolutePosition(element.offsetParent);
      r.x += tmp.x;
      r.y += tmp.y;
    }
    return r;
  };
  
function topParent(el) {
	if(el.parentNode == document.body) return el;
	return topParent(el.parentNode);
}	

function mouseCoords(ev){
	if(ev.pageX || ev.pageY){
		return {x:ev.pageX, y:ev.pageY};
	}
	return {
		x:ev.clientX + document.body.scrollLeft - document.body.clientLeft,
		y:ev.clientY + document.body.scrollTop  - document.body.clientTop
	};
}

function mouseUp(ev){
	clearDragObjects();
}

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
	var start = dbDate(document.reassignmentform.starting.value);
	var end = dbDate(document.reassignmentform.ending.value);
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
//alert("appointment-dragdrop-ajax.php?fn=appointments&prov="+provider+dateArg);	
  ajaxGet("appointment-dragdrop-ajax.php?fn=appointments&prov="+provider+dateArg, "appointments");
  ajaxGet("appointment-dragdrop-ajax.php?fn=reassigned&prov="+provider+dateArg, "reassigned");
  ajaxGet("appointment-dragdrop-ajax.php?fn=allappointments&prov="+otherprovider+"&exclude="+provider+dateArg, "allappointments");
  var wagButton = provider == 0 || otherprovider == 0
   ? "" 
   : "<img src='art/spacer.gif' width=20 height=1>"+
   	 "<input value='Week View for Both' class='Button' onclick='openWAG(\""+provider+","+otherprovider+
     "\")' onmouseover='this.className=\"ButtonDown\"' onmouseout='this.className=\"Button\"' type='button'>";
  //alert(wagButton);
	document.getElementById('workingdate').innerHTML = 
		end && end != start ? 'Working Dates: '+start+' - '+end : 'Working Date: '+start+wagButton;
	ajaxGetAndCallWith("appointment-dragdrop-ajax.php?reassignmentCount=1", updatePendingNotice, 'unused');
}

function pleaseWait(divname) {
	document.getElementById(divname).innerHTML = '<i>Please wait while this updates.</i>';
}

function ajaxGet(url, target) {
  var xh = getxmlHttp();
  xh.open("GET",url,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { 
		if(target) {
			document.getElementById(target).innerHTML=xh.responseText; 
		}} 
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
