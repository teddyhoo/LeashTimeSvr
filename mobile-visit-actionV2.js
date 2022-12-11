// mobile-visit-actionV2.js

function visitAction(action, id, update) {
	var isiPad = navigator.userAgent.match(/iPad/i) != null;
	if(typeof isBigger == 'undefined') {var isBigger = false; }
	var width = "340";
	var height = "300";
	if(isiPad || isBigger) {
		width = "640";
		height = "500";
		//alert('w: '+width+'h: '+height);
	}
	else  {
		width = "340";
		height = "300";
	}
	$.fn.colorbox({href: "mobile-visit-action.php?update=1&id="+id+"&operation="+action, width:width, height:height, top: "5px", iframe:true, scrolling: "auto", opacity: "0.3"});
	//if(typeof testT == 'function') testT(width+':'+height);
}

function arrived(id) {
	if(typeof arrivedDone != 'undefined' && arrivedDone) return;
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
				alert('Arrived: '+response[2]);
				//if(parent) parent.$.fn.colorbox.close();
				if(parent && parent.update) parent.update('arrived',0);  // actually refreshes the page only when arrival time is to be shown
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
	//if(typeof useWatchPosition != 'undefined')getCoordsWithGetCurrentPosition();
	//else 
	getCoordsWithAccurateCurrentPosition();
}

function getCoordsWithAccurateCurrentPosition() {
	if(navigator.geolocation) {
		var lat, lon, speed, heading, geoerror, watch_position_id;
		document.getElementById('pleasewait').innerHTML = "<img src='art/finding-location-progress-bar.gif'>";
		/*navigator.geolocation.getCurrentPosition(
			function(roughPosition) {
				document.getElementById('lat').value = roughPosition.latitude;
				document.getElementById('lon').value = roughPosition.longitude;
				document.getElementById('speed').value = roughPosition.speed;
				document.getElementById('heading').value = roughPosition.heading;
				document.getElementById('accuracy').value = roughPosition.accuracy;
			});*/
		navigator.geolocation.getAccurateCurrentPosition(
			function(position) {
				if(typeof position == 'undefined') {
					document.getElementById('pleasewait').style.color = 'darkred';
					document.getElementById('pleasewait').innerHTML = 'Location not determined.';
					return;
				}
				document.getElementById('lat').value = position.coords.latitude;
				document.getElementById('lon').value = position.coords.longitude;
				document.getElementById('speed').value = position.coords.speed;
				document.getElementById('heading').value = position.coords.heading;
				document.getElementById('accuracy').value = position.coords.accuracy;
				//document.getElementById('pleasewait').style.display = 'none';
				document.getElementById('pleasewait').innerHTML = 'Accuracy: '+position.coords.accuracy+' meters';
			}, 
			function(error) {
				geoerror = error.code;
				var msg;
				switch (error.code)  {
					case 0: 
					msg = "Error retrieving location";
					break;

					case 1:
					//alert("User denied");
					msg = "User denied location access";
					break;

					case 2: 
					msg = "Browser cannot determine location";
					break;

					case 3: 
					msg = "Browser cannot determine location: Time out";
					break;
					
					case -1:
					msg = "";
					break;
					
				}
				document.getElementById('geoerror').value = geoerror;
				document.getElementById('pleasewait').style.color = 'darkred';
				document.getElementById('pleasewait').innerHTML = msg;
			},
			function() { //progress
				//document.getElementById('pleasewait').innerHTML += '.';
			}, 
			{maxWait:20*1000 /* milliseconds */, desiredAccuracy:30 /* meters */});
	}
}


// ====== VERSION 2 ======
// geolocationSuccess - success Callback
// geolocationError - error  Callback
// geoprogress - progress callback
// options - same as getCurrentPosition, except for:
/*

    desiredAccuracy: The accuracy in meters that you consider "good enough". Once a location is found that meets this criterion, your callback will be called.
    maxWait: How long you are willing to wait (in milliseconds) for your desired accuracy. Once the function runs for maxWait milliseconds, it will stop trying and return the last location it was able to acquire. NOTE: If the desired accuracy is not achieved before the timeout, the onSuccess is still called. You will need to check the accuracy to confirm that you got what you expected. I did this because it's a "desired" accuracy, not a "required" accuracy. You can of course change this easily.


Copyright (C) 2013 Greg Wilson
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

navigator.geolocation.getAccurateCurrentPosition = function (geolocationSuccess, geolocationError, geoprogress, options) {
	var lastCheckedPosition,
	locationEventCount = 0,
	watchID,
	timerID;
	options = options || {};
	var checkLocation = function (position) {
		lastCheckedPosition = position;
		locationEventCount = locationEventCount + 1;
		// We ignore the first event unless it's the only one received because some devices seem to send a cached
		// location even when maxaimumAge is set to zero
		if ((position.coords.accuracy <= options.desiredAccuracy) && (locationEventCount > 1)) {
			clearTimeout(timerID);
			navigator.geolocation.clearWatch(watchID);
			foundPosition(position);
		} 
		else {
			geoprogress(position);
		}
	};
	var stopTrying = function () {
		navigator.geolocation.clearWatch(watchID);
		foundPosition(lastCheckedPosition);
		geolocationError({code:-1});
	};
	var onError = function (error) {
	clearTimeout(timerID);
	navigator.geolocation.clearWatch(watchID);
	geolocationError(error);
	};
	var foundPosition = function (position) {
		geolocationSuccess(position);
	};
	if (!options.maxWait) options.maxWait = 10000; // Default 10 seconds
	if (!options.desiredAccuracy) options.desiredAccuracy = 20; // Default 20 meters
	if (!options.timeout) options.timeout = options.maxWait; // Default to maxWait
	options.maximumAge = 0; // Force current locations only
	options.enableHighAccuracy = true; // Force high accuracy (otherwise, why are you using this function?)
	watchID = navigator.geolocation.watchPosition(checkLocation, onError, options);
	timerID = setTimeout(stopTrying, options.maxWait); // Set a timeout that will abandon the location loop
};


// ====== VERSION 1 ======
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
				var msg;
				switch (error.code)  {
					case 0: 
					msg = "Error retrieving location";
					break;

					case 1:
					//alert("User denied");
					msg = "Location unavailable: User denied";
					break;

					case 2: 
					msg = "Browser cannot determine location";
					break;

					case 3: 
					msg = "Browser cannot determine location: Time out";
					break;
				}
				document.getElementById('geoerror').value = geoerror;
				document.getElementById('pleasewait').style.color = 'red';
				document.getElementById('pleasewait').innerHTML = msg;
		},
		{enableHighAccuracy: true, timeout:20000, maximumAge:300000} // accept cached positions up to five minutes old.  times out in 20 seconds
		);
	}
}

// ====== VERSION 0 ======
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
					//alert("User denied");
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

