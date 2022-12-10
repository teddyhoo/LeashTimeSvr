<? // client-provider-map2018.php
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

extract(extractVars('requestid,id,radius,units,pop,prov', $_REQUEST));

// SPECIAL
if($prov) { // show a provider snapshot
	require "frame-bannerless.php";
	providerAvailabilitySnapShot($prov);
	exit;
}

$client = $id ? getClient($id) : (
					$requestid ? fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestid LIMIT 1", 1) 
					: null);
if($requestid && $client['clientptr']) $client = getClient($client['clientptr']);

$radius = trim($radius) ? (float)(trim($radius)) : trim($radius);
$radius = $radius ? $radius : ($_SESSION['client-provider-search-radius'] ? $_SESSION['client-provider-search-radius'] : 5);
$_SESSION['client-provider-search-radius'] = $radius;
if($units) $_SESSION['client-provider-search-units'] = $units;
if(!$units && !$_SESSION['client-provider-search-units']) {
	$distanceUnit = getI18Property('distanceunit', 'mile,mi');
	$distanceUnit = explode(',', $distanceUnit);
	$units = $distanceUnit[1];
}
else if(!$units) $units = $_SESSION['client-provider-search-units'];

$preferredProviders = getPreferredProviderIds($client['clientid']);
$doNotServeProviders = providerIdsWhoWillNotServeClient($client['clientid']);

if(!googleAddress($client)) 
	$_SESSION['frame_message'] = $error = "There is not sufficient address information on file for {$client['fname']} {$client['lname']} to produce a map.";
else  {
//if(mattOnlyTEST()) print_r(googleAddress($client));	
	$sitters = fetchAssociationsKeyedBy("SELECT * FROM tblprovider WHERE active =  1 ORDER BY lname, fname", 'providerid');
	if($client['defaultproviderptr'] && $sitters[$client['defaultproviderptr']]) $defaultSitter = $sitters[$client['defaultproviderptr']];
	$sitterAddrs = array();
	$homelessSitters = array();
	foreach($sitters as $sitter) {
		if($addr = googleAddress($sitter)) $sitterAddrs[$sitter['providerid']] = $addr;
		else $homelessSitters[] = $sitter['providerid'];
	}
	if(!$sitterAddrs) $_SESSION['frame_message'] = "No sitter address information was found.";
}

// ============================
$breadcrumbs = "<a href='client-edit.php?id=$id'>{$client['fname']} {$client['lname']}</a>";
$pageTitle = "Sitters who live within a $radius $units radius of {$client['fname']} {$client['lname']}";
$extraHeadContent = '<style>#radius {width:30px;}</style>';
if($pop) include "frame-bannerless.php";
else include "frame.html";
if($error) {
	if(!$pop) include "frame-end.html";
	else echo "<h2>$pageTitle</h2><span class='fontSize1_2em'>$error</span>";
	exit;
}
if($sitterAddrs) {
	echo "<form name='cmap' style='display:inline;font-size:12px;' METHOD='POST'>";
	echoButton('', 'Show', 'go()');
	labeledInput(' sitters who live within a ', 'radius', $radius, null, 'radiusInput');
	
	if(staffOnlyTEST()) {
		echo "<div style='float:right'>";
		fauxLink('In the last 30 minutes...', "document.location.href=\"client-provider-handy-map.php?pop=1&id=$id\"", 0, 2);
		echo "</div>";	
	}
	
	
	
	hiddenElement('id', $id);
	$unitOptions = array('mi'=>'mi', 'km'=>'km');
	selectElement('', 'units', $units, $unitOptions);
	echo " radius of the client's home.";
	echo "</form>";
	$clientHomeLabel = "Client Home";
	if($pop) {
		$clientHomeLabel = "Home of Client {$client['fname']} {$client['lname']}";
		echo "<p>";
	}
}


foreach($sitters as $i => $sitter) {
	$add = array();
	foreach(array('street1','street2','city','state','zip') as $f) {
		$add[$f] = $sitter[$f];
		$sitters[$i]['address'] = htmlFormattedAddress($add);
	}
}

//print_r($clients);exit;
// =========================================================
// copied from googleMap.php
?>

<style>
.maplabel {color:black;font-size:12px;}
.addrTable {margin-left: 1px;}
.addrTable td {border: solid darkgrey 0px;}
</style>

<?

$markers = array();

$client['address'] = personsHTMLAddress($client); //htmlFormattedAddress($add);
if($clientGoogleAddress = googleAddress($client)) {
//if(mattOnlyTEST()) echo "<hr>Client:".print_r($add,1)."<br>";	
	$marker = getLatLon($clientGoogleAddress);
	$clientGeocode = $marker;
	$marker['address'] = $client['address'];
	$marker['googleaddress'] = $clientGoogleAddress;
	$marker['icon'] = 'client';
	$marker['zIndex'] = 999;
	$marker['hovertext'] = str_replace("'", "&apos;", "{$client['fname']} {$client['lname']}'s home");
	$marker['infotext'] = str_replace("'", "&apos;", "{$client['fname']} {$client['lname']}'s home<br>{$client['address']}");
	$markers[] = $marker;
}

//echo 'Prov: '.googleAddress($prov).'<br>';
//$addresses = array(googleAddress($prov));
//echo "<p>CLIENT: ".print_r($clientGeocode, 1).'<br>';
foreach($sitters as $sitter) {
//if(mattOnlyTest() && 	$client['clientid']	== 765) echo "<p>".print_r($appointmentTracks, 1)."<p>".print_r($tracks, 1);
//print_r($client);
//echo "{$sitter['providerid']}<br>\n";
	if(($addr = $sitterAddrs[$sitter['providerid']]) &&
			distance($clientGeocode, $addr) < $radius) {
		$details = "<br>"
			.fauxLink('Details...', "openDetails({$sitter['providerid']})", 
								1, 'Sitter details');
		$nickname = $sitter['nickname'] ? " ({$sitter['nickname']})" : '';

		$html = "{$sitter['fname']} {$sitter['lname']}'s$nickname home<div style='display:block;padding-left:10px;'>{$sitter['address']}</div>#DISTINCTION##DETAILS#";
	
		$distinction = '';
		if(staffOnlyTEST() || $_SESSION['preferences']['preferredAndBannedSittersInMaps']) {
			if($preferredsitter = in_array($sitter['providerid'], $preferredProviders)) {
				$preferredProvidersOnMap = true;
				$distinction = "<span style='color:green;font-weight:bold;'>(PREFERRED)</span><p>";
			}
			if($donotservesitter = in_array($sitter['providerid'], $doNotServeProviders)) {
				$doNotServeProvidersOnMap = true;
				$distinction = "<span style='color:red;font-weight:bold;'>(DO NOT ASSIGN)</span><p>";
			}
		}
			$iconToUse = 
				$sitter['providerid'] == $defaultSitter['providerid'] ? 'defaultprovider' : (
				$preferredsitter ? 'preferredprovider' : (
				$donotservesitter ? 'donotserveprovider' : (
				'provider')));
		//$map->addMarkerByAddress($addr,"{$sitter['fname']} {$sitter['lname']}'s home", $html);
		//$map->_icons[] = $iconToUse;
		$marker = getLatLon($addr);
		$marker['address'] = $addr;
		$marker['googleaddress'] = googleAddress($sitter);
		$marker['icon'] = $iconToUse;
		$marker['zIndex'] = $iconToUse == 'defaultprovider' ? 1 : 0;
		$marker['hovertext'] = str_replace("'", "&apos;", "{$sitter['fname']} {$sitter['lname']}'s$nickname home");
		$marker['infotext'] = str_replace('#DETAILS#', "$details", str_replace("'", "&apos;", $html));
		$marker['infotext'] = str_replace('#DISTINCTION#', "$distinction", $marker['infotext']);
		$markers[] = $marker;
	}
	
	else if(!$sitterAddrs[$sitter['providerid']]) $notes[] = "No home address was found for {$sitter['fname']} {$sitter['lname']}.";
//echo 'Client: '.googleAddress($client).'<br>';
}

?>
<img src='art/pin-sunset.png'>- <?= $clientHomeLabel ?>
<img src='art/spacer.gif' height=1 width=10>
<? if($sitterAddrs) { ?>
<img src='art/pin-blue.png'>- Sitter Home 
<img src='art/spacer.gif' height=1 width=10>
<? if($defaultSitter && $sitterAddrs[$defaultSitter['providerid']]) { ?>
<img src='art/pin-green.png'> - Default Sitter 
<? } ?>
<? if($preferredProvidersOnMap) { ?>
<img src='art/spacer.gif' height=1 width=10>
<img src='art/pin-lightblue.png'> - Preferred Sitter 
<? } ?>
<? if($doNotServeProvidersOnMap) { ?>
<img src='art/spacer.gif' height=1 width=10>
<img src='art/pin-blue-NOT.png'> - Do Not Assign Sitter 
<? } ?>
<? }	else if($defaultSitter) echo "No address info for default sitter ({$defaultSitter['fname']} {$defaultSitter['lname']})";
 ?>
<?

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
<script src='common.js' language='javascript'></script>
<script src='popcalendar.js' language='javascript'></script>
<script src='check-form.js' language='javascript'></script>
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
		mapTypeControlOptions: options
		//center: new google.maps.LatLng(-33.863276, 151.207977),
		//zoom: 12
	});
	
	var infoWindow = new google.maps.InfoWindow;
	var markers = /* JSON.parse('*/<?= json_encode($markers)  ?>/*') */;
	
	//var markers = JSON.parse('[{"lat":"38.8815","lon":"-77.1741","address":"250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046",
	//"googleaddress":"250 S Maple Ave, Falls Church, VA","icon":"client","zIndex":999,"hovertext":"Elroy Krum's home","infotext":"Elroy Krum's home<br>250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046"},{"lat":"38.8362","lon":"-77.1089","address":"4713 West Braddock Road #10, Alexandria, VA","googleaddress":"4713 West Braddock Road #10, Alexandria, VA","icon":"provider","zIndex":0,"hovertext":"Brian Martinez&apos;s home","infotext":"Brian Martinez&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>4713 West Braddock Road #10<br>Alexandria, VA 22311<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=42\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8975","lon":"-77.1297","address":"5015 23rd Road N, ARLINGTON, VA","googleaddress":"5015 23rd Road N, ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"John Masters&apos;s home","infotext":"John Masters&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>5015 23rd Road N<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=17\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8932","lon":"-77.1135","address":"1810 N. Taylor St., ARLINGTON, VA","googleaddress":"1810 N. Taylor St., ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"Josh Odmark&apos;s home","infotext":"Josh Odmark&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>1810 N. Taylor St.<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=20\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8834","lon":"-77.1765","address":"40 James St, 3b, Falls Church, VA","googleaddress":"40 James St, 3b, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Cam Stull&apos;s home","infotext":"Cam Stull&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>40 James St, 3b<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=27\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8854","lon":"-77.1715","address":"228 Governers Ct, Falls Church, VA","googleaddress":"228 Governers Ct, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Elizabeth Tanner&apos;s home","infotext":"Elizabeth Tanner&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>228 Governers Ct<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=22\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"}]');	
	var icons = {
		client: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-sunset.png"
						},
		provider: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-blue.png"
						},
		preferredprovider: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-lightblue.png"
						},
		donotserveprovider: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-blue-NOT.png"
						},
		defaultprovider: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-green.png"
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
              
		bounds.extend(point);
	}
	map.fitBounds(bounds);       // auto-zoom
	map.panToBounds(bounds);     // auto-center
	
}

function openDetails(providerid) {
	openConsoleWindow("provdetails","client-provider-map.php?prov="+providerid, 500,400);
}

</script>
<script async defer
src="https://maps.googleapis.com/maps/api/js?key=<?= $googleMapAPIKey ?>&callback=initMap">
</script>
<?
if(!$pop) include "frame-end.html";
