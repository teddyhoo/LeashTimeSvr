<? //visit-sheets-client.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "appointment-fns.php";
require_once "GoogleMapAPI.class.php";

locked('vc');

extract($_REQUEST);

if(userRole() == 'p' && $_SESSION["providerid"] != $provider) {
  echo "<h2>Insufficient rights to view this page..<h2>";
  exit;
}

$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

function printMultiMapJS() {
		$_output .= 'var points = [];' . "\n";
		$_output .= 'var markers = [];' . "\n";
		$_output .= 'var counter = 0;' . "\n";
				$_output .= 'var sidebar_html = "";' . "\n";
				$_output .= 'var marker_html = [];' . "\n";

				$_output .= 'var to_htmls = [];' . "\n";
				$_output .= 'var from_htmls = [];' . "\n";
				$_output .= 'var icon = [];' . "\n";

		echo $_output;
 }                           


?>
<script language='javascript'>
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
}

</script>
<style type="text/css">
	v\:* {
		behavior:url(#default#VML);
	}
</style>

<?

$index = 0;
$mapId = "map_$index";
include "visit-sheet.php";

