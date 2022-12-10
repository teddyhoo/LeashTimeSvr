<? // client-filter-map.php
/*
Show a map for clients based on a filter search showing:
client address
addresses of other clients within a specified radius
prospective clients as green pins
actual clients as blue pins
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "appointment-fns.php";
require_once "js-gui-fns.php";
require_once "google-map-utils.php";

$locked = locked('o-');


$IMGHOST = "https://{$_SERVER["HTTP_HOST"]}/";
//$IMGHOST = "";

extract(extractVars('refresh,pop', $_REQUEST));

if($refresh && $_SESSION['clientListIDString']) {
	$ids = $_SESSION['clientListIDString'];
	$result = doQuery("SELECT * FROM tblclient WHERE clientid IN ($ids)", 'clientid');
	$clientAddressesFound = array();
	$homelessClients = array();
  while($cclient = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$prospectiveClients += $cclient['prospect'] ? 1 : 0;
		if($addr = googleAddress($cclient)) {
			$cclient['address'] = personsHTMLAddress($cclient); //personsHTMLAddress // htmlFormattedAddress
			$marker = getLatLon($addr);
			if($marker) {
				$marker['address'] = $cclient['address'];
				$marker['googleaddress'] = $addr;
				$marker['icon'] = $cclient['prospect'] ? 'prospect' : 'client';
				$marker['zIndex'] = 100;
				$marker['hovertext'] = str_replace("'", "&apos;", "{$cclient['fname']} {$cclient['lname']}'s home");
				$marker['infotext'] = str_replace("'", "&apos;", "{$cclient['fname']} {$cclient['lname']}'s home<br>{$marker['address']}");
				$markers[] = $marker;
				$clientAddressesFound[] = $cclient;
			}
			else $noLocationClients[] = $cclient;
		}
		else $homelessClients[] = $cclient;
	}
	if(!$clientAddressesFound) $_SESSION['frame_message'] = "No client address information was found.";
	if($refresh < 2 && ($numFound = count($clientAddressesFound)) > 500) {
		$_SESSION['frame_message'] = "$numFound client addresses were found.  It may take a while to build the map.  "
			.fauxLink('Click to Continue', "document.location.href=\"client-filter-map.php?refresh=2\"", 1, 2);
		$clientAddressesFound = null;
		$markers = array();
	}
	else unset($_SESSION['clientListIDString']);
}

// ============================
$breadcrumbs = "<a href='client-list.php'>Clients</a>";
$pageTitle = "Client Map";
$extraHeadContent = '<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<style>#radius {width:30px;}
.noteselement {cursor:pointer;}
.maplabel {color:black;font-size:12px;}
.addrTable {margin-left: 1px;}
.addrTable td {border: solid darkgrey 0px;}
</style>';

if($pop) include "frame-bannerless.php";
else include "frame.html";
if($error) {
	if(!$pop) include "frame-end.html";
	else echo "<h2>$pageTitle</h2><span class='fontSize1_2em'>$error</span>";
	exit;
}
$focusLabelType = $focus['clientid'] ? 'Client' : 'Sitter';
$focusHomeLabel = "$focusLabelType Home";
if($pop) {
	$focusHomeLabel = "$focusLabelType {$focus['fname']} {$focus['lname']}";
	echo "<p>";
}
?>
<form name='cmap' style='display:inline;font-size:12px;' METHOD='POST'>
	<? echoButton('', 'Choose Clients', 'go()'); ?>
</form>

<p>
<? if($clientAddressesFound) { ?>
<? if($prospectiveClients) echo "<img src='art/dot-green.png' >- Prospective Client "; ?>
<img src='art/spacer.gif' height=1 width=10>
<img src='art/dot-red.png'> - Actual Client 
<img src='art/spacer.gif' height=1 width=10>
<?
echo ($clientAddressesFound ? count($clientAddressesFound) : 'No')." clients shown.<br>";

$t0 = time();
	echo "<p>";
	echo "<span class='addressList' style='display:none;' onclick=\"$('.addressList').toggle();\"><b>Hide Address List</b></span>";
	echo "<span class='addressList' style='display:inline;' onclick=\"$('.addressList').toggle();\"><b><u>Show Address List</u></b></span>";
	echo "<table class='addressList' style='display:none;'><tr>";
	if($clientAddressesFound) {
		echo "<td valign=top><b>Client Addresses</b><p>";
		echo "<ul class='addressList' style='display:none;'>";
		foreach($clientAddressesFound as $id=>$client) {
			//$name = fetch
			echo "<li>{$client['fname']} {$client['lname']}<br>{$client['address']}\n";
		}
		echo "</ul>";
		echo "</td>";
	}
	if($noLocationClients) { //$homelessClients
		echo "<td valign=top><b>Unlocatable Clients</b><p>";
		echo "<ul class='addressList' style='display:none;'>";
		foreach($noLocationClients as $id=>$client) {
			//$name = fetch
			echo "<li>{$client['fname']} {$client['lname']}<br>{$client['address']}\n";
		}
		echo "</ul>";
		echo "</td>";
	}
	if($homelessClients) { 
		echo "<td valign=top><b>Clients w/ No address</b><p>";
		echo "<ul class='addressList' style='display:none;'>";
		foreach($homelessClients as $id=>$client) {
			//$name = fetch
			echo "<li>{$client['fname']} {$client['lname']}<br>{$client['address']}\n";
		}
		echo "</ul>";
		echo "</td>";
	}
	echo "</tr></table>";
}


if($notes) {
	echo "<p>Note: <span class='noteselement' style='display:none;' onclick=\"$('.noteselement').toggle();\"><b>Hide Notes</b></span>";
	echo "<span class='noteselement' style='display:inline;' onclick=\"$('.noteselement').toggle();\">Some clients have no address info.  <b><u>Click here</u></b> to see the list.</span>";
	echo "<ul class='noteselement' style='display:none;'>";
	foreach($notes as $note) echo "<li>$note\n";
	echo "</ul>";
}


/// $map->directions = true;

function dumpControlJavascript() {
	echo <<<JS
<script src='popcalendar.js' language='javascript'></script>
<script src='check-form.js' language='javascript'></script>
<script src='common.js' language='javascript'></script>
<script language='javascript'>
function go() {
	if(!MM_validateForm('radius', '', 'R', 'radius', '', 'isFloat'))
		return;
	document.cmap.submit();
}
JS;

	dumpPopCalendarJS();
	echo <<<JS
init();
</script>
JS;
}
?>
<div id="map" style='height:700px; width:700px;'></div>
<?
dumpControlJavascript();
$googleMapAPIKey;  // comes from init_session.php
?>
<script language='javascript'>
function go() {
	openConsoleWindow('filterwindow', 'filter-clients.php',880,600);
}

function update(aspect, result) {
	document.location.href = 'client-filter-map.php?refresh=1';
}

function initMap() {
	var markers = /* JSON.parse('*/<?= json_encode($markers)  ?>/*') */;
	
	if(markers.length == 0) return;
	
	var options = {mapTypeIds: ["ROADMAP"]};//new google.maps.MapTypeControlOptions;
	//options.mapTypeIds = {ROADMAP};
	var map = new google.maps.Map(document.getElementById('map'), {
		mapTypeControlOptions: options
		//center: new google.maps.LatLng(-33.863276, 151.207977),
		//zoom: 12
	});
	
	var infoWindow = new google.maps.InfoWindow;
	
	//var markers = JSON.parse('[{"lat":"38.8815","lon":"-77.1741","address":"250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046",
	//"googleaddress":"250 S Maple Ave, Falls Church, VA","icon":"client","zIndex":999,"hovertext":"Elroy Krum's home","infotext":"Elroy Krum's home<br>250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046"},{"lat":"38.8362","lon":"-77.1089","address":"4713 West Braddock Road #10, Alexandria, VA","googleaddress":"4713 West Braddock Road #10, Alexandria, VA","icon":"provider","zIndex":0,"hovertext":"Brian Martinez&apos;s home","infotext":"Brian Martinez&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>4713 West Braddock Road #10<br>Alexandria, VA 22311<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=42\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8975","lon":"-77.1297","address":"5015 23rd Road N, ARLINGTON, VA","googleaddress":"5015 23rd Road N, ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"John Masters&apos;s home","infotext":"John Masters&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>5015 23rd Road N<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=17\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8932","lon":"-77.1135","address":"1810 N. Taylor St., ARLINGTON, VA","googleaddress":"1810 N. Taylor St., ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"Josh Odmark&apos;s home","infotext":"Josh Odmark&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>1810 N. Taylor St.<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=20\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8834","lon":"-77.1765","address":"40 James St, 3b, Falls Church, VA","googleaddress":"40 James St, 3b, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Cam Stull&apos;s home","infotext":"Cam Stull&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>40 James St, 3b<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=27\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8854","lon":"-77.1715","address":"228 Governers Ct, Falls Church, VA","googleaddress":"228 Governers Ct, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Elizabeth Tanner&apos;s home","infotext":"Elizabeth Tanner&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>228 Governers Ct<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=22\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"}]');	
	var icons = {
		focus: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-sunset.png"
						},
		client: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/dot-red.png"
						},
		prospect: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/dot-green.png"
						},
		provider: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-yellow.png"
						}
		}
	var bounds  = new google.maps.LatLngBounds();
	for(var i = 0; i < markers.length; i++) {
		markerinfo = markers[i];
//alert(	icons[markerinfo.icon].url); break;	
		var point = new google.maps.LatLng(
		                  parseFloat(markerinfo.lat),
		                  parseFloat(markerinfo.lon));

<? if(mattOnlyTEST()) { ?>if(typeof markerinfo.hovertext == 'undefined') alert(Object.values(markerinfo));<? } ?>          
		var hovertext = markerinfo.hovertext == null ? '???' : markerinfo.hovertext;
		var marker = new google.maps.Marker({
                map: map,
                position: point,
                icon: icons[markerinfo.icon],
                zIndex: markerinfo.zIndex,
                title: hovertext.replace('&apos;', "'")
              });
		marker.bubble = new google.maps.InfoWindow({content:markerinfo.infotext});
		marker.addListener('click', function() {
                this.bubble.open(map, this);
							});
              
		bounds.extend(point);
	}
	map.fitBounds(bounds);       // auto-zoom
	map.panToBounds(bounds);     // auto-center
	
}
</script>

<script async defer
src="https://maps.googleapis.com/maps/api/js?key=<?= $googleMapAPIKey ?>&callback=initMap">
</script>

<?
if($screenLog) echo $screenLog;
if(!$pop) include "frame-end.html";
