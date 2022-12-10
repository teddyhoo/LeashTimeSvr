<? // client-provider-handy-map.php
/*
Show a map for a given client showing:
client address
and locations of sitters within the last N minutes
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

extract(extractVars('requestid,id,radius,units,pop,prov,minutesago', $_REQUEST));

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

if(!googleAddress($client)) 
	$_SESSION['frame_message'] = $error = "There is not sufficient address information on file for {$client['fname']} {$client['lname']} to produce a map.";
else  {
	$minutesago = $minutesago ? $minutesago : 30;
	// STEP 1: Find apptids for today
	$today = date('Y-m-d');
	$apptids = fetchCol0($sql = "SELECT appointmentid FROM tblappointment WHERE date = '$today'");
	// STEP 2: For each visit, collect the latest coord for that visit
	$recents = array();
	foreach($apptids as $apptid) {
		$recents[$apptid] = fetchFirstAssoc(
			"SELECT * FROM tblgeotrack
			WHERE appointmentptr = $apptid AND lat != -999
			ORDER BY date DESC
			LIMIT 1", 1);
		//  if no coord found, use the client location with start time
		if(!$recents[$apptid]) {
			// $recents[$apptid] = get lat/lon of client's house, date+starttime,  client's name, and set event = 'home',
			$visitclient = fetchFirstAssoc(
				"SELECT lname, fname, CONCAT_WS(' ', fname, lname) as client, 
						CONCAT(date, ' ', starttime) as date,
						street1, street2, city, state, zip,
						'home' as 'event'
					FROM tblappointment
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE appointmentid = $apptid
					LIMIT 1", 1);
			if($visitclient 
					&& ($visitclientAdress = googleAddress($visitclient))
					&& ($latLon = getLatLon($visitclientAdress))) {
				foreach($latLon as $k => $v)
					$visitclient[$k] = $v;
				$recents[$apptid] = $visitclient;
			}
		}
		if($recents[$apptid]) 
			$recents[$apptid]['providerptr'] = 
				fetchRow0Col0("SELECT providerptr FROM tblappointment WHERE appointmentid = $apptid LIMIT 1", 1);

	}
	function datecmp($a, $b) {
		if($result = strcmp($a['date'], $b['date'])) return $result;
		return strcmp($a['date'], $b['date']); 
	}
	usort($recents, 'datecmp');
	
	// find latest location for each sitter
	$sitterLocs =  array();
	foreach($recents as $coord) {
		// visit must have a sitter and coord date must be in the past
		if(!$coord['providerptr'] || strtotime($coord['date']) > time()) continue;
		// 'home' does not override specific coords
		if($coord['event'] == 'home'  && $sitterLocs[$coord['providerptr']]['event'] != 'home') 
			continue;
		$sitterLocs[$coord['providerptr']] = $coord;
	}

//print_r($recents); exit;	
	
	// STEP 3: weed out future times and past times older than N minutes
	$earliestAllowed = time() - $minutesago*60;
	$showCoords = array();
	foreach($sitterLocs as $providerptr => $coord) {
		$coorddate = strtotime($coord['date']);
		if($coorddate > $earliestAllowed)
			$showCoords[$providerptr] = $coord;
	}
}
//echo "earliestAllowed: ".date('Y-m-d H:i:s', $earliestAllowed)."\n";
//print_r($showCoords); exit;	
	
	
//if(mattOnlyTEST()) print_r(googleAddress($client));	
	$sitters = fetchAssociationsKeyedBy("SELECT * FROM tblprovider WHERE active =  1 ORDER BY lname, fname", 'providerid');

	if(!$showCoords) $_SESSION['frame_message'] = "No recent sitter location information was found.";

// ============================
$breadcrumbs = "<a href='client-edit.php?id=$id'>{$client['fname']} {$client['lname']}</a>";
$pageTitle = "Sitters of {$client['fname']} {$client['lname']} in the last $minutesago minutes";
$extraHeadContent = '<style>#radius {width:30px;}</style>';
if($pop) include "frame-bannerless.php";
else include "frame.html";
if($error) {
	if(!$pop) include "frame-end.html";
	else echo "<h2>$pageTitle</h2><span class='fontSize1_2em'>$error</span>";
	exit;
}

echo "<form name='cmap' style='display:inline;font-size:12px;' METHOD='POST'>";
echoButton('', 'Show', 'go()');
labeledInput(' sitters who were within a ', 'radius', $radius, null, 'radiusInput');
hiddenElement('id', $id);
$unitOptions = array('mi'=>'mi', 'km'=>'km');
selectElement('', 'units', $units, $unitOptions);
echo " radius of the client's home in the last $minutesago minutes";
echo "</form>";
$clientHomeLabel = "Client Home";
if($pop) {
	$clientHomeLabel = "Home of Client {$client['fname']} {$client['lname']}";
	echo "<p>";
}

?>
<p>
<img src='art/pin-sunset.png'>- <?= $clientHomeLabel ?>
<img src='art/spacer.gif' height=1 width=10>
<img src='art/pin-big-bluedot.png'>- arrived 
<img src='art/spacer.gif' height=1 width=10>
<img src='art/pin-big-greendot.png'>- marked complete 
<img src='art/spacer.gif' height=1 width=10>
<img src='art/pin-big-reddot.png'>- one the move 
<img src='art/spacer.gif' height=1 width=10>
<img src='art/pin-big-graydot.png'>- unreported, might be here 
<?

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
foreach($showCoords as $coord) {
//if(mattOnlyTest() && 	$client['clientid']	== 765) echo "<p>".print_r($appointmentTracks, 1)."<p>".print_r($tracks, 1);
//print_r($client);
//echo "{$sitter['providerid']}<br>\n";
	$events = array('mv'=>'on the move', 'arrived'=>'arrived', 'completed'=>'finished visit', 'home'=>'might be here');
	if(distance($clientGeocode, $coord) < $radius 	) {
		$sitter = $sitters[$coord['providerptr']];
		$details = "<br>"
			.fauxLink('Details...', "openDetails({$coord['providerptr']})", 
								1, 'Sitter details');
		$prettytime = date('h:i a', strtotime($coord['date']));
		$eventdesc = ago($coord['date'])." - {$events[$coord['event']]}";
		$html = "{$sitter['fname']} {$sitter['lname']}'s location at {$prettytime}<br>$eventdesc#DETAILS#";
		$marker = $coord; //getLatLon($addr);
		$marker['address'] = $addr;
		$marker['googleaddress'] = googleAddress($sitter);
		$marker['icon'] = $coord['event'];
		$marker['zIndex'] = $iconToUse == 'defaultprovider' ? 1 : 0;
		$marker['hovertext'] = str_replace("'", "&apos;", "{$sitter['fname']} {$sitter['lname']}'s last location");
		$marker['infotext'] = str_replace('#DETAILS#', "$details", str_replace("'", "&apos;", $html));
		$markers[] = $marker;
	}
	
	//else if(!$sitterAddrs[$sitter['providerid']]) $notes[] = "No home address was found for {$sitter['fname']} {$sitter['lname']}.";
//echo 'Client: '.googleAddress($client).'<br>';
}


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
		mv: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-big-reddot.png"
						},
		arrived: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-big-bluedot.png"
						},
		completed: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-big-greendot.png"
						},
		home: { // anchor: 
							url: "<?= $IMGHOST ?>art/pin-big-graydot.png"
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
