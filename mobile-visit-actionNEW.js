// mobile-visit-action.js

function visitAction(action, id, update) {
		$.fn.colorbox({href: "mobile-visit-action.php?update=1&id="+id+"&operation="+action, width:"280", height:"300", top: "5px", iframe:true, scrolling: "auto", opacity: "0.3"});
}

function arrived(id) {
	getCoords();
	var args = new Array();
	args[args.length] = 'lat='+document.getElementById('lat').value;
	args[args.length] = 'lon='+document.getElementById('lon').value;
	args[args.length] = 'speed='+document.getElementById('speed').value;
	args[args.length] = 'heading='+document.getElementById('heading').value;
	args[args.length] = 'accuracy='+document.getElementById('accuracy').value;
	args[args.length] = 'geoerror='+document.getElementById('geoerror').value;
	ajaxGetAndCallWith("mobile-visit-action.php?operation=arrived&id="+id+"&"+args.join('&'), 
		function(arg,txt) {
			var response = txt.split('|');
			if(response[0] == 'MSG' && response[1] == 'ARRIVED') {
				document.getElementById('arrivedbutton').style.display='none';
				alert('Arrived '+response[2]);
			}
			else alert(txt);
		}
	, 0);
}

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

function getCoords() {
	if(useWatchPosition == undefined)	getCoordsWithGetCurrentPosition();
	else getCoordsWithWatchPosition();
}

function getCoordsWithWatchPosition() {
	if(navigator.geolocation) {
		var lat, lon, speed, heading, geoerror, watch_position_id;
		watch_position_id = navigator.geolocation.watchPosition(
			function(position) {
				document.getElementById('lat').value = position.coords.latitude;
				document.getElementById('lon').value = position.coords.longitude;
				document.getElementById('speed').value = position.coords.speed;
				document.getElementById('heading').value = position.coords.heading;
				document.getElementById('accuracy').value = position.coords.accuracy;
				document.getElementById('pleasewait').style.display = 'none';
				navigator.geolocation.clearWatch(watch_position_id)
			}, 
			function(error) {
				geoerror = error.code;
				switch (error.code)  {
					case 0: 
					alert("Error retrieving location");
					break;

					case 1:
					alert("User denied");
					break;

					case 2: 
					alert("Browser cannot determine location");
					break;

					case 3: 
					alert("Time out");
					break;
				}
				document.getElementById('geoerror').value = geoerror;
				document.getElementById('pleasewait').style.color = 'red';
				document.getElementById('pleasewait').innerHTML = 'Location unavailable';
		},
		{enableHighAccuracy: true, timeout:20000, maximumAge:300000} // accept cached positions up to five minutes old.  times out in 20 seconds
		);
	}
}


function getCoordsWithGetCurrentPosition() {
	if(navigator.geolocation) {
		var lat, lon, speed, heading, geoerror;
		navigator.geolocation.getCurrentPosition(
			function(position) {
				document.getElementById('lat').value = position.coords.latitude;
				document.getElementById('lon').value = position.coords.longitude;
				document.getElementById('speed').value = position.coords.speed;
				document.getElementById('heading').value = position.coords.heading;
				document.getElementById('accuracy').value = position.coords.accuracy;
				document.getElementById('pleasewait').style.display = 'none';
			}, 
			function(error) {
				geoerror = error.code;
				switch (error.code)  {
					case 0: 
					alert("Error retrieving location");
					break;

					case 1:
					alert("User denied");
					break;

					case 2: 
					alert("Browser cannot determine location");
					break;

					case 3: 
					alert("Time out");
					break;
				}
				document.getElementById('geoerror').value = geoerror;
				document.getElementById('pleasewait').style.color = 'red';
				document.getElementById('pleasewait').innerHTML = 'Location unavailable';
		},
		{enableHighAccuracy: true, timeout:20000, maximumAge:300000} // accept cached positions up to five minutes old.  times out in 20 seconds
		);
/*
If the PositionOptions parameter to getCurrentPosition or watchPosition is omitted, the default value used for the enableHighAccuracy attribute is false. The same default value is used in ECMAScript when the enableHighAccuracy property is omitted.

The timeout attribute denotes the maximum length of time (expressed in milliseconds) that is allowed to pass from the call to getCurrentPosition() or watchPosition() until the corresponding successCallback is invoked. If the implementation is unable to successfully acquire a new Position before the given timeout elapses, and no other errors have occurred in this interval, then the corresponding errorCallback must be invoked with a PositionError object whose code attribute is set to TIMEOUT. Note that the time that is spent obtaining the user permission is not included in the period covered by the timeout attribute. The timeout attribute only applies to the location acquisition operation.

If the PositionOptions parameter to getCurrentPosition or watchPosition is omitted, the default value used for the timeout attribute is Infinity. If a negative value is supplied, the timeout value is considered to be 0. The same default value is used in ECMAScript when the timeout property is omitted.

In case of a getCurrentPosition() call, the errorCallback would be invoked at most once. In case of a watchPosition(), the errorCallback could be invoked repeatedly: the first timeout is relative to the moment watchPosition() was called or the moment the user's permission was obtained, if that was necessary. Subsequent timeouts are relative to the moment when the implementation determines that the position of the hosting device has changed and a new Position object must be acquired.

The maximumAge attribute indicates that the application is willing to accept a cached position whose age is no greater than the specified time in milliseconds. If maximumAge is set to 0, the implementation must immediately attempt to acquire a new position object. Setting the maximumAge to Infinity must determine the implementation to return a cached position regardless of its age. If an implementation does not have a cached position available whose age is no greater than the specified maximumAge, then it must acquire a new position object. In case of a watchPosition(), the maximumAge refers to the first position object returned by the implementation.

If the PositionOptions parameter to getCurrentPosition or watchPosition is omitted, the default value used for the maximumAge attribute is 0. If a negative value is supplied, the maximumAge value is considered to be 0. The same default value is used in ECMAScript when the maximumAge property is omitted. 
*/
	}
}
