<? // visit-map-include2018.php
/*
Show a map for a given visit showing:
client address
sitter coordinates for that day
*/
//assume session is set up

require_once "client-fns.php";
require_once "provider-fns.php";
require_once "appointment-fns.php";
require_once "js-gui-fns.php";
require_once "google-map-utils.php";

function makeMap($id, $mapOptions=null) {
	// if no mapOptions, echo an active map
	//   if 'fullwindow' frame it in a regular LT window
	// else if 'static' set up a static map image
	//    if 'returnurl' return a static map URL
	//    else echo a static map IMG
	// if 'includeprovider' include the sitter's home
	
	global $lockChecked;
	
	$noframe = !$mapOptions['fullwindow'];

	extract(extractVars('showcoords', $_REQUEST));

	$appt = getAppointment($id);
	if($clientView && $appt['clientptr'] != $_SESSION["clientid"]) {
		$pageTitle = "Insufficient permissions";
		if(!$noframe) include "frame-client.html";
		echo "<h3>Your rights to view this map are insufficient.</h3>";
		echo "{$appt['clientptr']} != {$_SESSION['clientid']}";
		if(!$noframe) include "frame-end.html";
		exit;
	}
	if($showcoords) {
		function distance($lat1, $lon1, $lat2, $lon2, $unit) {
			/*::  Passed to function:                                                    :*/
			/*::    lat1, lon1 = Latitude and Longitude of point 1 (in decimal degrees)  :*/
			/*::    lat2, lon2 = Latitude and Longitude of point 2 (in decimal degrees)  :*/
			/*::    unit = the unit you desire for results                               :*/
			/*::           where: 'M' is statute miles (default)                         :*/
			/*::                  'K' is kilometers                                      :*/
			/*::                  'N' is nautical miles                                  :*/
			$theta = $lon1 - $lon2;
			$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
			$dist = acos($dist);
			$dist = rad2deg($dist);
			$miles = $dist * 60 * 1.1515;
			$unit = strtoupper($unit);

			if ($unit == "K") {
				return ($miles * 1.609344);
			} else if ($unit == "N") {
					return ($miles * 0.8684);
				} else {
						return $miles;
					}
		}

		function kilometersToFeet($k) { return number_format($k*3280.84, 9); }	

		$tracks = fetchAssociations("SELECT * FROM tblgeotrack 
																WHERE appointmentptr = $id
																ORDER BY date");
		if(!$tracks) echo "No GPS coordinates found.";
		else {
			echo "Accuracy is expressed in meters.<p>";
			$tracks[0]['Distance (feet)'] = 0;
			for($i=1; $i<count($tracks); $i++)
				$tracks[$i]['Distance'] = kilometersToFeet(distance($tracks[$i-1]['lat'], $tracks[$i-1]['lon'], $tracks[$i]['lat'], $tracks[$i]['lon'], 'K'));
			$columns = array_keys($tracks[0]);
			quickTable($tracks, $extra='border=1', $style=null, $repeatHeaders=0);
		}
		exit;
	}



	$prov = $appt['providerptr'] ? getProvider($appt['providerptr']) : null;
	$client = getOneClientsDetails($appt['clientptr'], array('addressparts'));
	$date = date('Y-m-d', strtotime($appt['date']));

	$allowUnassigned = staffOnlyTEST() || dbTEST('doggiewalkerdotcom');

	$elsewhereThreshold = 300; // feet

	$IMGHOST = "https://{$_SERVER["HTTP_HOST"]}/";
	//$IMGHOST = "";

	//echo "PROV ".print_r($prov,1);


	// ============================
	if(!$mapOptions['returnurl']) {
		if(!$clientView) {
			$breadcrumbs[] = "<a href='client-edit.php?id={$client['clientid']}'>Client: {$client['clientname']}</a>";
			if($prov) $breadcrumbs[] = "<a href='provider-edit.php?id={$prov['providerid']}'>Sitter: {$prov['fname']} {$prov['lname']}</a>";
			if(mattOnlyTEST()) $breadcrumbs[] = "<a href='provider-map.php?id={$prov['providerid']}&date={$appt['date']}'>{$prov['fname']} Day Map</a>";
			if(staffOnlyTEST()) $breadcrumbs[] = "<a href='visit-map.php?id=$id&showcoords=1'>Show Coords</a>";
			$breadcrumbs = join(" - ", $breadcrumbs);
		}
		$pageTitle = "Visit route on ".longestDayAndDate(strtotime($date));
		if($noframe) ;
		else if($clientView) include "frame-client.html";
		else include "frame.html";

		if($clientView) $clientAndPets = "{$appt['pets']}";
		else $clientAndPets = "({$appt['pets']}) {$client['clientname']}";

		echo "<h3>{$appt['timeofday']} "
			."".fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$appt['servicecode']} LIMIT 1", 1)." $clientAndPets</h3>";
	?>
	<img src='art/pin-sunset.png'>- Client Home
	<img src='art/spacer.gif' height=1 width=10>
	<img src='art/pin-blue.png'>- Arrival 
	<img src='art/spacer.gif' height=1 width=10>
	<img src='art/dot-red.png'>- Route 
	<img src='art/spacer.gif' height=1 width=10>
	<img src='art/pin-green.png'> - Completion 
	<img src='art/spacer.gif' height=1 width=10>
	<img src='art/pin-yellow.png'> - Sitter Home 
<?
	}
	$markers = array();

	$client['address'] = personsHTMLAddress($client); //htmlFormattedAddress($add);
	if($clientGoogleAddress = googleAddress($client)) {
		$marker = getLatLon($clientGoogleAddress);
		$marker['address'] = $client['address'];
		$marker['googleaddress'] = $clientGoogleAddress;
		$marker['icon'] = 'client';
		$marker['zIndex'] = 999;
		$marker['hovertext'] = str_replace("'", "&apos;", "{$client['fname']} {$client['lname']}'s home");
		$marker['infotext'] = str_replace("'", "&apos;", "{$client['fname']} {$client['lname']}'s home<br>{$client['address']}");
		$markers[] = $marker;
	}
	else $notes[] = "No home address was found for {$client['clientname']}.";

	if($mapOptions['includeprovider']) {
		$prov['address'] = personsHTMLAddress($prov); //htmlFormattedAddress($add);
		if($provGoogleAddress = googleAddress($prov)) {
			$marker = getLatLon($provGoogleAddress);
			$marker['address'] = $prov['address'];
			$marker['googleaddress'] = $clientGoogleAddress;
			$marker['icon'] = 'provider';
			$marker['zIndex'] = 500;
			$marker['hovertext'] = str_replace("'", "&apos;", "{$prov['fname']} {$prov['lname']}'s home");
			$marker['infotext'] = str_replace("'", "&apos;", "{$prov['fname']} {$prov['lname']}'s home<br>{$prov['address']}");
			$markers[] = $marker;
		}
		else $notes[] = "No home address was found for {$prov['fname']} {$prov['lname']}.";
	}

	$tracks = array();
	$tracks = fetchAssociations("SELECT * FROM tblgeotrack 
															WHERE appointmentptr = $id
															AND !(lat = 0 AND lon = 0)
															ORDER BY date");

	foreach($tracks as $track) if($track['event'] == 'completed') $completionTime = $track['date'];
	$tracks = clusterTracks($tracks, $map);  // sets up $appointmentTracks
	foreach($tracks as $track) {
		if(strpos($track['event'], 'arrived') !== FALSE)
			$hasArrived = true;
		$title = ($br = strpos($track['time'], '<br>')) ? substr($track['time'], 0, $br).'...' : $track['time'];
		$polyPoints[] = array('lat'=>$track['lat'], 'lng'=>$track['lon']);
		$marker = array_merge($track);
		$marker['icon'] = $track['event']; // arrived|completed|arrived_completed|mv
		$marker['zIndex'] = 100;
		$marker['hovertext'] = str_replace("<br>", "\n", $track['time']);
		$marker['infotext'] = $track['time'];
		$markers[] = $marker;
		if($hasCompleted = ($completionTime && $completionTime <= $track['date'])) break;
	}
	
	if($mapOptions['static']) {
		// https://developers.google.com/maps/documentation/maps-static/dev-guide
		/* https://maps.googleapis.com/maps/api/staticmap?center=Brooklyn+Bridge,New+York,NY&zoom=13&size=600x300&maptype=roadmap
				&markers=color:blue%7Clabel:S%7C40.702147,-74.015794&markers=color:green%7Clabel:G%7C40.711614,-74.012318
				&markers=color:red%7Clabel:C%7C40.718217,-73.998284 
				&key=YOUR_API_KEY*/
		foreach($markers as $marker) $locs[] = "{$marker['lat']},{$marker['lon']}";
		$locs = join('|', $locs);
		$size = $mapOptios['size'] ? $mapOptios['size'] : "300x300";
		$options = "&scale=1&format=JPEG&maptype=roadmap"; // path, visible
		$style = "icon:{$IMGHOST}art/pin-reddot.png";
		global $googleMapAPIKey;
		$url =  "https://maps.googleapis.com/maps/api/staticmap?key=$googleMapAPIKey&size=$size&markers=$style|$locs";
		if($mapOptions['returnurl']) return $url;
		else echo "<img src='$url' title='Visit map'>";
		exit;
	}
	?>

	<style>
	.maplabel {color:black;font-size:12px;}
	.visitTable {margin-left: 1px;}
	.visitTable td {border: solid darkgrey 1px;}
	.elsewhere {color: blue;font-weight:bold;}
	.whoknowswhere {color: blue;font-weight:bold;font-style:italic;}
	</style>

	<?    
	if($notes) {
		echo "<p>Note:<ul>";
		foreach($notes as $note) echo "<li>$note\n";
		echo "</ul>";
	}

	//require "common/init_db_common.php"; // // for buildNoticeText()
	
	$markerJSON = json_encode($markers);
	$polyPointsJSON = json_encode($polyPoints);
	global $googleMapAPIKey;

	echo <<<MAPSTUFF
	<div id="map" style='height:700px; width:700px;'></div>
	<script>
	function initMap() {
		var options = {mapTypeIds: ["ROADMAP"]};//new google.maps.MapTypeControlOptions;
		//options.mapTypeIds = {ROADMAP};
		var map = new google.maps.Map(document.getElementById('map'), {
			mapTypeControlOptions: options
			//center: new google.maps.LatLng(-33.863276, 151.207977),
			//zoom: 12
		});
		
		var infoWindow = new google.maps.InfoWindow;
		var markers = /* JSON.parse('*/$markerJSON/*') */;
		
		var icons = { //arrived|completed|arrived_completed|mv
			client: { // anchor: -- by default, bottom center
								url: "{$IMGHOST}art/pin-sunset.png"
							},
			provider: { // anchor: 
								url: "{$IMGHOST}art/pin-yellow.png"
							},
			completed: { // anchor: 
								url: "{$IMGHOST}art/pin-green.png"
							},
			arrived: { // anchor: 
								url: "{$IMGHOST}art/pin-blue.png"
							},
			arrived_completed: { // anchor: 
								url: "{$IMGHOST}art/pin-blueslashgreen.png"
							},
			mv: {     anchor: new google.maps.Point(4, 4),
								url: "<?= $IMGHOST ?>art/pin-reddot.png"
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
		var polyPoints = $polyPointsJSON;
		for(var i=0; i<polyPoints.length; i++) {
			polyPoints[i].lat =  parseFloat(polyPoints[i].lat);
			polyPoints[i].lng =  parseFloat(polyPoints[i].lng);
		}
		var trackPath = new google.maps.Polyline({
			map: map,
			path: polyPoints,
			geodesic: true,
			strokeColor: '#0000FF',
			strokeOpacity: 0.5,
			strokeWeight:4
		});
		map.fitBounds(bounds);       // auto-zoom
		map.panToBounds(bounds);     // auto-center
		
	}
	</script>
	<script async defer
	src="https://maps.googleapis.com/maps/api/js?key=$googleMapAPIKey&callback=initMap">
	</script>
MAPSTUFF;

	if(!$noframe) include "frame-end.html";
}

function clusterTracks($tracks, $map) {
	global $appointmentTracks; // IS THIS USED?
	$radius = 6.096 / 1000; /* radius in KM */ // 6.096 m = 20 ft
	$newTracks = array();
	$allTracks = array(); // *ugh*
	$appts = array();
	$clientHomes = array();
	foreach($tracks as $i => $track) {
		$track['seq'] = $i;
		$added = false;
		$permaTrack = &$track;
		foreach($newTracks as $i => $newTrack) {
			if(distance($track, $newTrack) < $radius) {
				$newTracks[$i]['time'][] = $track['date'];
				$permaTrack = &$newTracks[$i];
				if($track['event'] == 'arrived') $permaTrack['event'] = 'arrived';
				if($track['event'] == 'completed') $permaTrack['event'] = 'completed';
				$allTracks[] = $permaTrack;
//if(mattOnlyTEST()) {print_r($permaTrack);exit;}				
				$added = true;
				break;
			}
		}
		if($track['appointmentptr']) {
			$appt = $appts[$track['appointmentptr']];
			if(!$appt) {
				$appt = fetchFirstAssoc($sql = "SELECT CONCAT_WS(' ', fname, lname) as name, pets, timeofday, clientptr, street1, zip 
																FROM tblappointment
																LEFT JOIN tblclient ON clientid = clientptr
																WHERE appointmentid = {$track['appointmentptr']} LIMIT 1");
				$appts[] = $appt;
			}
																
			if(!isset($clientHomes[$appt['clientptr']])) {
				$googleAdd = googleAddress($appt);
				if($googleAdd) $clientHomes[$appt['clientptr']] = getLatLon($googleAdd);
			}
			
			
//if(mattOnlyTEST()) echo print_r()."<p>";			
			if(($homeLoc = $clientHomes[$appt['clientptr']])
					&& !$track['error']
					&& ($delta = distance($track, $homeLoc)) > $radius) {
				$permaTrack['clientdeltafeet'] = $delta;
			}
			else if(!$homeLoc) $permaTrack['clientdeltafeet'] = '-';
			else if($track['error']) $permaTrack['clientdeltafeet'] = '-';
			$permaTrack['visits'][$track['date']] = ($track['event'] == 'mv' ? ' route' : " {$track['event']}");
//if(mattOnlyTEST()) echo "<hr>CLIENT: {$appt['clientptr']}<p>HOME: ".print_r($homeLoc,1)."<p> DELTA: {$permaTrack['clientdeltafeet']} / $radius<br>\n".print_r($permaTrack, 1);
			$track['clientdeltafeet'] = $permaTrack['clientdeltafeet'];
			if(array_key_exists('accuracy', $track))
				$track['clientdeltaerror'] = " +/- ".convertMeters($track['accuracy'], $preciseAlso=true);
		}
		
		if(!$added) {
			$track['time'] = array($track['date']);
			$newTracks[] = $track;
			$allTracks[] = $track;
		}
		$appointmentTracks[$track['appointmentptr']][$track['event']] = $track;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($track['visits']); echo "<br>"; }																
	}
//if(mattOnlyTEST()) {print_r($allTracks); }
if(TRUE || mattOnlyTEST()) $newTracks = $allTracks;
	foreach($newTracks as $i => $track) {
		sort($track['time']);
		foreach($track['time'] as $j => $t) {
			$newTracks[$i]['time'][$j] = date('h:i a', strtotime($t)).$track['visits'][$t];
			$trackEvents = array();
			foreach($track['visits'] as $v) {
				$trackEvents[strpos($v, 'arrived') !== FALSE ? 'arrived' : (strpos($v, 'completed') !== FALSE ? 'completed' : 'mv')] = 1;
			}
			if($trackEvents['arrived'] && $trackEvents['completed']) $newTracks[$i]['event'] = 'arrived_completed';
			else if($trackEvents['arrived']) $newTracks[$i]['event'] = 'arrived';
			else if($trackEvents['completed']) $newTracks[$i]['event'] = 'completed';
		}
		$newTracks[$i]['time'] = join("<br>", $newTracks[$i]['time']);
	}
	return $newTracks;
				
}

