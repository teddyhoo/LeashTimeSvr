// common.js

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  if(typeof w == 'undefined') return; //alert("Could not open w ["+w+"] ["+windowname+"url: "+url);
    
  w.document.location.href=url;
  if(w) w.focus();
}

function formatInteger(num, length) {
    return (num / Math.pow(10, length)).toFixed(length).substr(2);
}

function stopEventPropagation(event) { // stop event from bubbling up
	event = event || window.event;
	if (event.stopPropagation) event.stopPropagation(); // W3C standard variant
	else event.cancelBubble = true; // IE variant
}

function commafy( num ) {
    var str = num.toString().split('.');
    if (str[0].length >= 4) {
        str[0] = str[0].replace(/(\d)(?=(\d{3})+$)/g, '$1,');
    }
    if (str[1] && str[1].length >= 4) {
        str[1] = str[1].replace(/(\d{3})/g, '$1 ');
    }
    return str.join('.');
}

function busyImage(x,y) {
	// for when I can't make jqueryBusy work
	// add a busy image element in the middle of the window
	var oImg=document.createElement("img");
	oImg.setAttribute('src', 'art/busy.gif');
	oImg.setAttribute('alt', 'na');
	oImg.style.position = 'absolute';
	if(document.body && document.body.clientWidth) {
		var x = document.body.clientWidth / 2;
		var y = document.body.clientHeight / 2;
	}
	else if(typeof window.innerWidth != 'undefined') {
		var x = window.innerWidth / 2;
		var y = window.innerHeight / 2;
	}
	else if(document.documentElement && document.documentElement.clientWidth) {
		var x = document.documentElement.clientWidth / 2;
		var y = document.documentElement.clientHeight / 2;
	}
	oImg.style.left = x;
	oImg.style.top = y;
	oImg.style.width = 22;
	oImg.style.height = 22;
	var root = document.getElementsByTagName('body');
	root[0].appendChild(oImg);
}


function makeArray() {
	var arr = new Array();
	for(var i=0; i<makeArray.arguments.length;i++)
		arr[arr.length] = makeArray.arguments[i];
  return arr;
}

function safeDate(date) {
	if(!date) return;
	date = date.split('/');
	if(date.length != 3) return date;
	if(date[0].length < 2) date[0] = '0'+date[0];
	if(date[1].length < 2) date[1] = '0'+date[1];
	return date[2]+'-'+date[0]+'-'+date[1];
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function numbersOnly(str) {
	return str.replace(/[^0-9]+/g, '');
}

// Simulates PHP's date function
// var myDate = new Date();
// alert(myDate.format('M jS, Y')); // May 11th, 2006 
Date.prototype.format = function(format) {
	var returnStr = '';
	var replace = Date.replaceChars;
	for (var i = 0; i < format.length; i++) {
		var curChar = format.charAt(i);
		if (replace[curChar]) {
			returnStr += replace[curChar].call(this);
		} else {
			returnStr += curChar;
		}
	}
	return returnStr;
};
Date.replaceChars = {
	shortMonths: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
	longMonths: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
	shortDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
	longDays: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
	
	// Day
	d: function() { return (this.getDate() < 10 ? '0' : '') + this.getDate(); },
	D: function() { return Date.replaceChars.shortDays[this.getDay()]; },
	j: function() { return this.getDate(); },
	l: function() { return Date.replaceChars.longDays[this.getDay()]; },
	N: function() { return this.getDay() + 1; },
	S: function() { return (this.getDate() % 10 == 1 && this.getDate() != 11 ? 'st' : (this.getDate() % 10 == 2 && this.getDate() != 12 ? 'nd' : (this.getDate() % 10 == 3 && this.getDate() != 13 ? 'rd' : 'th'))); },
	w: function() { return this.getDay(); },
	z: function() { return "Not Yet Supported"; },
	// Week
	W: function() { return "Not Yet Supported"; },
	// Month
	F: function() { return Date.replaceChars.longMonths[this.getMonth()]; },
	m: function() { return (this.getMonth() < 9 ? '0' : '') + (this.getMonth() + 1); },
	M: function() { return Date.replaceChars.shortMonths[this.getMonth()]; },
	n: function() { return this.getMonth() + 1; },
	t: function() { return "Not Yet Supported"; },
	// Year
	L: function() { return "Not Yet Supported"; },
	o: function() { return "Not Supported"; },
	Y: function() { return this.getFullYear(); },
	y: function() { return ('' + this.getFullYear()).substr(2); },
	// Time
	a: function() { return this.getHours() < 12 ? 'am' : 'pm'; },
	A: function() { return this.getHours() < 12 ? 'AM' : 'PM'; },
	B: function() { return "Not Yet Supported"; },
	g: function() { return this.getHours() % 12 || 12; },
	G: function() { return this.getHours(); },
	h: function() { return ((this.getHours() % 12 || 12) < 10 ? '0' : '') + (this.getHours() % 12 || 12); },
	H: function() { return (this.getHours() < 10 ? '0' : '') + this.getHours(); },
	i: function() { return (this.getMinutes() < 10 ? '0' : '') + this.getMinutes(); },
	s: function() { return (this.getSeconds() < 10 ? '0' : '') + this.getSeconds(); },
	// Timezone
	e: function() { return "Not Yet Supported"; },
	I: function() { return "Not Supported"; },
	O: function() { return (-this.getTimezoneOffset() < 0 ? '-' : '+') + (Math.abs(this.getTimezoneOffset() / 60) < 10 ? '0' : '') + (Math.abs(this.getTimezoneOffset() / 60)) + '00'; },
	T: function() { var m = this.getMonth(); this.setMonth(0); var result = this.toTimeString().replace(/^.+ \(?([^\)]+)\)?$/, '$1'); this.setMonth(m); return result;},
	Z: function() { return -this.getTimezoneOffset() * 60; },
	// Full Date/Time
	c: function() { return "Not Yet Supported"; },
	r: function() { return this.toString(); },
	U: function() { return this.getTime() / 1000; }
};

function setInputValue(name, value) {
	// case 1: unary element
	var el;
	if(el = document.getElementById(name)) {
		if(el.type == 'text') el.value = value;
		else if(el.type == 'checkbox') el.checked = value ? 1 : 0;
		else if(el.type == 'textarea') el.innerHTML = value;
		else if(el.type == 'select-one') {
			for(var i = 0; i < el.options.length; i++)
				if(el.options[i].value == value)
					el.options[i].selected = true;
		};
		return;
	}
	var group = new Array();
	var els = document.getElementsByTagName('input');
	for(var i=0;i<els.length;i++)
		if(els[i].name == name) group[group.length] = els[i];
	for(i = 0; i < group.length; i++)
		group[i].checked = group[i].value == value;
}

function getDocumentFromXML(xml) {
	try //Internet Explorer
		{
		xmlDoc=new ActiveXObject("Microsoft.XMLDOM");
		xmlDoc.async="false";
		xmlDoc.loadXML(xml);
		return xmlDoc;
		}
	catch(e)
		{
		parser=new DOMParser();
		xmlDoc=parser.parseFromString(xml,"text/xml");
		return xmlDoc;
		}
}

function eventFire(el, etype){
  if (el.fireEvent) {
    el.fireEvent('on' + etype);
  } else {
    var evObj = document.createEvent('Events');
    evObj.initEvent(etype, true, false);
    el.dispatchEvent(evObj);
  }
}