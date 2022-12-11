function getxmlHttp() {
  try {
  // Firefox, Opera 8.0+, Safari
  var xmlHttp=new XMLHttpRequest();
  } catch (e) {
    // Internet Explorer
    try {
    xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
    } catch (e) {
      try {
        xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
      } catch (e) {
        alert("Your browser does not support AJAX!");
        return false;
      }
    }
  }
  return xmlHttp;
}


function ajaxGet(url, target) { // fetch url contents into target
  var xh = getxmlHttp();
  xh.open("GET",url,true);
  //if(document.getElementById(target) == null) alert(url+': '+target);
  
  xh.onreadystatechange=function() { if(xh.readyState==4) { if(target) document.getElementById(target).innerHTML=xh.responseText; } }
  xh.send(null);
}

function ajaxGetAndCallWith(url, callbackfn, argument) {
  var xh = getxmlHttp();
  xh.open("GET",url,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { 
		callbackfn(argument, xh.responseText); 
		}
	}
  xh.send(null);
}

/*function submitAJAXForm(form, url, targetElement) {
  var DataToSend = formArguments(form);
  url +=  url.indexOf('?') >= 0 ? '&' : '?';
  alert(url+DataToSend);
  ajaxGet(url+DataToSend,targetElement);
}*/

function submitAJAXForm(params, url, target) {
	submitAJAXFormAndCallWith(params, url, function(target, responseText) {
		if(!document.getElementById(target)) alert('Could not find target ['+target+']');
			document.getElementById(target).innerHTML=responseText;
		}, target);
}

function submitAJAXFormAndCallWith(params, url, callbackfn, argument) {
	//var url = "get_data.php";
	//var params = "lorem=ipsum&name=binny";
  var xh = getxmlHttp();
	xh.open("POST", url, true);
	//Send the proper header information along with the request
	xh.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xh.setRequestHeader("Content-length", params.length);
	xh.setRequestHeader("Connection", "close");

  xh.onreadystatechange=function() { if(xh.readyState==4) { 
		callbackfn(argument, xh.responseText); 
		}
	}
	xh.send(params);
}


function formArguments(form) {
  fields = new Array();
  for(i=0;i<form.elements.length;i++)
    if(form.elements[i].name &&
        (((form.elements[i].type != 'checkbox') && form.elements[i].value) ||
         ((form.elements[i].type == 'checkbox') && form.elements[i].checked)))
      fields[fields.length] = form.elements[i].name + "=" + escape(form.elements[i].value);
  var DataToSend = fields.join("&");
  return DataToSend;
}

function formArgumentsFromObject(anObj) {
	var fieldNames = Object.getOwnPropertyNames(anObj);
  var fields = new Array();
  for(i=0;i<fieldNames.length;i++)
      fields[fields.length] = fieldNames[i] + "=" + escape(anObj[fieldNames[i]]);
  var DataToSend = fields.join("&");
  return DataToSend;
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

