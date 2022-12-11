<? // address-corrector-map.php
/*
Show a map for a given client showing:
client address
addresses of sitters within a specified radius
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "appointment-fns.php";
require_once "js-gui-fns.php";
require_once "google-map-utils.php";

$locked = locked('o-');
tallyPage('client-provider-map.php');

$IMGHOST = "https://{$_SERVER["HTTP_HOST"]}/";
//$IMGHOST = "";

extract(extractVars('client,prov,action,coords', $_REQUEST));



$person = $client ? getClient($client) : getProvider($prov);
$editURL = $client ? "client-edit.php?id=$client" : "provider-edit.php?id=$prov";
$personTypeLabel = $client ? 'Client' : 'Sitter';

if(!$addr = googleAddress($person)) 
	$_SESSION['frame_message'] = $error = "There is not sufficient address information on file for {$client['fname']} {$client['lname']} to produce a map.";

if($action == 'dropit') {
	deleteTable('geocodes', "address = '$addr'", 1);
	$deleted = mysqli_affected_rows();
	$message = "Dropped the coordinates for [$addr] ($deleted).  Will try the city/state formula.";
}
if($action == 'fixit') {
	deleteTable('geocodes', "address = '$addr'", 1);
	$deleted = mysqli_affected_rows();
	$message = "Dropped the coordinates for [$addr] ($deleted).  Here's the ZIP approach.";
	$addr = googleAddress($person, 'forceZIP');
}
if($action == 'fillin') {
	deleteTable('geocodes', "address = '$addr'", 1);
	$coords = explode(',', $coords);
	$lat= trim($coords[0]);
	$lon= trim($coords[1]);
	replaceTable('geocodes', array('address'=>$addr, 'lat'=>$lat, 'lon'=>$lon), 1);
	$message = "Updated geocoordinates to {$_REQUEST['coords']} [$addr].";
}
// ============================
$breadcrumbs = "<a href='$editURL'>{$person['fname']} {$person['lname']}</a>";
$pageTitle = "Fix This Address's Location";

if($pop) include "frame-bannerless.php";
else include "frame.html";
if($error) {
	if(!$pop) include "frame-end.html";
	else echo "<h2>$pageTitle</h2><span class='fontSize1_2em'>$error</span>";
	exit;
}
echo "<div class='tiplooks'>$message</div>";

echo "<form name='cmap' style='display:inline;font-size:12px;' METHOD='POST'>";
hiddenElement('id', $id);
hiddenElement('action', $action);
hiddenElement('type', $type);

if($action == 'fixit') {
	echo "If the map below still looks wrong, you can ";
	echoButton('', 'Revert to Last Map', 'go("dropit")');
}
else {
	echo "If the map below looks wrong, you can ";
	echoButton('', 'Try Using the ZIP Code', 'go("fixit")');
}
echo "  ";
labeledInput('or use: ', 'coords');
echo "  ";
echoButton('', 'Go', 'go("fillin")');
echo "</form><p>";


$clientHomeLabel = "$personTypeLabel Home";
if($pop) {
	$clientHomeLabel = "Home of $personTypeLabel {$client['fname']} {$client['lname']}";
	echo "<p>";
}

?>
<img src='art/pin-blue.png'>- <?= $clientHomeLabel ?>

<style>
.maplabel {color:black;font-size:12px;}
.addrTable {margin-left: 1px;}
.addrTable td {border: solid darkgrey 0px;}
</style>

<?

$markers = array();

$person['address'] = $addr; //htmlFormattedAddress($add);
if($_POST)
	$marker = getCoordinatesFromGoogleWithRetries($addr, $triesLeft=3);
else
	$marker = getLatLon($addr, $triesLeft=3);
	
if(is_array($marker)) 
	replaceTable('geocodes', array('address'=>googleAddress($person), 'lat'=>$marker['lat'], 'lon'=>$marker['lon']), 1);
else echo "ERROR: $marker<p>";
echo "[[$addr]] == ".print_r($marker, 1);

$geocode = $marker;
$marker['address'] = googleAddress($person);
$marker['icon'] = 'person';
$marker['zIndex'] = 999;
$marker['hovertext'] = str_replace("'", "&apos;", "{$person['fname']} {$person['lname']}'s home");
$marker['infotext'] = str_replace("'", "&apos;", "{$person['fname']} {$person['lname']}'s home<br>{$person['address']}");
$markers[] = $marker;


if($notes) {
	echo "<p>Note:<ul>";
	foreach($notes as $note) echo "<li>$note\n";
	echo "</ul>";
}
//print_r($markers);exit;
?>
<div id="map" style='height:700px; width:700px;'></div>
<?

function dumpControlJavascript() {
	echo <<<JS
<script language='javascript'>
function go(action) {
	if(action == 'fillin' && !(document.getElementById('coords').value.trim())) {
		alert('Lat, Long must be supplied.');
		return;
	}
	document.getElementById('action').value = action;
	document.cmap.submit();
}
JS;

	dumpPopCalendarJS();
	
	echo <<<JS
init();
</script>
JS;
}

dumpControlJavascript();
//$map->printMap();
//$map->printOnLoad();
$googleMapAPIKey;  // comes from init_session.php
?>
<script language='javascript'>
function initMap() {
	var options = {mapTypeIds: ["ROADMAP"]};//new google.maps.MapTypeControlOptions;
	//options.mapTypeIds = {ROADMAP};
	var map = new google.maps.Map(document.getElementById('map'), {
		mapTypeControlOptions: options,
		zoom: 10
		//center: new google.maps.LatLng(-33.863276, 151.207977),
		//zoom: 12
	});
	
	var infoWindow = new google.maps.InfoWindow;
	var markers = /* JSON.parse('*/<?= json_encode($markers)  ?>/*') */;
	
	//var markers = JSON.parse('[{"lat":"38.8815","lon":"-77.1741","address":"250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046",
	//"googleaddress":"250 S Maple Ave, Falls Church, VA","icon":"client","zIndex":999,"hovertext":"Elroy Krum's home","infotext":"Elroy Krum's home<br>250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046"},{"lat":"38.8362","lon":"-77.1089","address":"4713 West Braddock Road #10, Alexandria, VA","googleaddress":"4713 West Braddock Road #10, Alexandria, VA","icon":"provider","zIndex":0,"hovertext":"Brian Martinez&apos;s home","infotext":"Brian Martinez&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>4713 West Braddock Road #10<br>Alexandria, VA 22311<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=42\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8975","lon":"-77.1297","address":"5015 23rd Road N, ARLINGTON, VA","googleaddress":"5015 23rd Road N, ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"John Masters&apos;s home","infotext":"John Masters&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>5015 23rd Road N<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=17\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8932","lon":"-77.1135","address":"1810 N. Taylor St., ARLINGTON, VA","googleaddress":"1810 N. Taylor St., ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"Josh Odmark&apos;s home","infotext":"Josh Odmark&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>1810 N. Taylor St.<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=20\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8834","lon":"-77.1765","address":"40 James St, 3b, Falls Church, VA","googleaddress":"40 James St, 3b, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Cam Stull&apos;s home","infotext":"Cam Stull&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>40 James St, 3b<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=27\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8854","lon":"-77.1715","address":"228 Governers Ct, Falls Church, VA","googleaddress":"228 Governers Ct, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Elizabeth Tanner&apos;s home","infotext":"Elizabeth Tanner&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>228 Governers Ct<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=22\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"}]');	
	var icons = {
			'fixit': { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-blue.png"
						}
		}
	var bounds  = new google.maps.LatLngBounds();
	for(var i = 0; i < markers.length; i++) {
		markerinfo = markers[i];
//alert(	icons[markerinfo.icon].url); break;	
		var point = new google.maps.LatLng(
		                  parseFloat(markerinfo.lat),
		                  parseFloat(markerinfo.lon));

		var marker = new google.maps.Marker({
                map: map,
                position: point,
                icon: icons[markerinfo.icon],
                zIndex: markerinfo.zIndex,
                title: markerinfo.hovertext.replace('&apos;', "'")
              });
              
		marker.bubble = new google.maps.InfoWindow({content:markerinfo.infotext});
		marker.addListener('click', function() {
                this.bubble.open(map, this);
							});
              
		//bounds.extend(point);
	}
	//map.fitBounds(bounds);       // auto-zoom
	//map.panToBounds(bounds);     // auto-center
	map.setOptions({'center':point});
	
}

</script>
<script async defer
src="https://maps.googleapis.com/maps/api/js?key=<?= $googleMapAPIKey ?>&callback=initMap">
</script>
<?
if(!$pop) include "frame-end.html";
