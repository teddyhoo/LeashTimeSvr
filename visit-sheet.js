var points = [];
var markers = [];
var counter = 0;
var sidebar_html = "";
var marker_html = [];
var to_htmls = [];
var from_htmls = [];
var icon = [];
var loadableMaps = [];

function onLoad() {
  if (!GBrowserIsCompatible()) {
    alert("Sorry, the Google Maps API is not compatible with this browser.");
    return;
  }
  // loadableMaps[loadableMaps.length] = [mapid, lat, lon, markers = [[lat, lon, title, html, tooltip]]
  for(var i = 0; i < loadableMaps.length; i++) {
	  var mapObj = document.getElementById(loadableMaps[i][0]);
	  if (mapObj == 'undefined' || mapObj == null) return;
	  map = new GMap2(mapObj);
	  map.setCenter(new GLatLng(loadableMaps[i][1], loadableMaps[i][2]), 16, G_NORMAL_MAP);
	  map.addControl(new GLargeMapControl());
	  map.addControl(new GMapTypeControl());
	  map.addControl(new GScaleControl());
	  var markers = loadableMaps[i][3];
	  var point;
	  var marker;
	  for(var m = 0; m < markers.length; m++) {
		point = new GLatLng(markers[m][0],markers[m][1]);
		marker = createMarker(point,markers[m][3],markers[m][4], 0,markers[m][5]);
	  	map.addOverlay(marker);
	  }
  }
  //gdir = new GDirections(null, document.getElementById("directions"));
	//GEvent.addListener(gdir, "load", onGDirectionsLoad);
	//GEvent.addListener(gdir, "error", handleErrors);
	//gdir.load("<?= $itinerary ?>",
	//	{ "locale": "en_US" });

}

/*	function handleErrors(){
	 if (gdir.getStatus().code == G_GEO_UNKNOWN_ADDRESS)
		 alert("No corresponding geographic location could be found for one of the specified addresses. This may be due to the fact that the address is relatively new, or it may be incorrect.\nError code: " + gdir.getStatus().code);
	 else if (gdir.getStatus().code == G_GEO_SERVER_ERROR)
		 alert("A geocoding or directions request could not be successfully processed, yet the exact reason for the failure is not known.\n Error code: " + gdir.getStatus().code);

	 else if (gdir.getStatus().code == G_GEO_MISSING_QUERY)
		 alert("The HTTP q parameter was either missing or had no value. For geocoder requests, this means that an empty address was specified as input. For directions requests, this means that no query was specified in the input.\n Error code: " + gdir.getStatus().code);

//   else if (gdir.getStatus().code == G_UNAVAILABLE_ADDRESS)  <--- Doc bug... this is either not defined, or Doc is wrong
//     alert("The geocode for the given address or the route for the given directions query cannot be returned due to legal or contractual reasons.\n Error code: " + gdir.getStatus().code);

	 else if (gdir.getStatus().code == G_GEO_BAD_KEY)
		 alert("The given key is either invalid or does not match the domain for which it was given. \n Error code: " + gdir.getStatus().code);

	 else if (gdir.getStatus().code == G_GEO_BAD_REQUEST)
		 alert("A directions request could not be successfully parsed.\n Error code: " + gdir.getStatus().code);

	 else alert("An unknown error occurred.");

}*/

function onGDirectionsLoad(){ 
		// Use this function to access information about the latest load()
		// results.

		// e.g.
		// document.getElementById("getStatus").innerHTML = gdir.getStatus().code;
	// and yada yada yada...
}
