<? // google-map-utils.php

function googleAddress($person, $forceZIP=false) {

	if($person) $person = array_map('trim', $person);
	if(!$forceZIP && $person['city'] && $person['state'] ) $addr = "{$person['street1']}, {$person['city']}, {$person['state']}";
	else 	$addr = trim("{$person['street1']} {$person['zip']}");
	/*if(FALSE && mattOnlyTEST()) {
	        // Google may do a bad job with $address.  Use the alternate address if one is available
	        $altTable = $_SESSION['altgeocodetable'];
	        if(!$altTable) {
						$allTables = fetchCol0("SHOW TABLES");
						if(in_array('geocodealtaddr', $allTables)) $altTable = 'geocodealtaddr';
						else $altTable = 'none';
						$_SESSION['altgeocodetable'] = $altTable;
					}
					if($altTable != 'none') {
						$altAddress = fetchRow0Col0("SELECT altaddress FROM $altTable WHERE address = '".addslashes($addr)."' LIMIT 1", 1); // don't use mysqli_real_escape_string here
						if($altAddress) $addr = $altAddress;
					}
	}*/
	return $addr;
}

function convertFeet($feet) {
	$unit = explode(',', getI18Property('distanceunit', 'mile,mi'));
	if($unit[1] == 'mi') $frac = $feet / 5280;
	else if($unit[1] == 'km') $frac = $feet / 3280.84;
	$resolution = $frac > 1 ? 2 : 3;
	return sprintf("%.{$resolution}f", $frac)." {$unit[1]}.";
}

function convertMeters($meters, $preciseAlso=false) {
	$unit = explode(',', getI18Property('distanceunit', 'mile,mi'));
	$finalUnit = $unit[1];
	if($finalUnit == 'mi') {
		$frac = $meters * 0.000621371;
		if($preciseAlso) $preciseUnit = 'feet';
		$meters = round($meters * 3.28084);
	}
	else if($finalUnit == 'km') {
		$frac = $meters / 1000;
		if($preciseAlso) $preciseUnit = 'm';
	}
	$resolution = $frac > 1 ? 2 : 3;
	return sprintf("%.{$resolution}f", $frac)." $finalUnit."
			.($preciseAlso ? " (=$meters $preciseUnit)" : '');
}

function getLatLonOLD($toAddress) { // OLD OLD OLD
	
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	global $googleMapAPIKey, $dbuser, $dbpass, $dbhost, $db;
	$coords = fetchFirstAssoc("SELECT lat, lon FROM geocodes WHERE address = '".leashtime_real_escape_string($toAddress)."' LIMIT 1", 1);
	if(!$coords) {
		$url = "https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($toAddress)."key=$googleMapAPIKey";
		$response = file_get_contents($url);
		
		$response = json_decode($response);
	  
		if($response->status == 'OK') {
			$coords['lat'] = $response->results[0]->geometry->location->lat;
			$coords['lon'] = $response->results[0]->geometry->location->lng;
			insertTable('geocodes', array('address'=>$toAddress, 'lat'=>$coords['lat'], 'lon'=>$coords['lon']), 1);
		} 
		else {
			logError("Google Map error finding address [$toAddress]: ".$response->status);
		}
	}
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1); 
	return $coords;
}

function getLatLon($toAddress) {
	if(!$toAddress) return null;
	
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	global $googleMapAPIKey, $dbuser, $dbpass, $dbhost, $db;
	$coords = fetchFirstAssoc("SELECT lat, lon FROM geocodes WHERE address = '".leashtime_real_escape_string($toAddress)."' LIMIT 1", 1);
	if(!$coords) {
		$coords = getCoordinatesFromGoogleWithRetries($toAddress, $triesLeft=3);
		if($coords) {
			insertTable('geocodes', array('address'=>$toAddress, 'lat'=>$coords['lat'], 'lon'=>$coords['lon']), 1);
		} 
	}
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1); 
	return $coords;
}

function getCoordinatesFromGoogleWithRetries($addr, $triesLeft) {
	while(!is_array($coords) && $triesLeft > 0) {
		if($coords && $coords != 'OVER_QUERY_LIMIT') break;
		$coords = getCoordinatesFromGoogle($addr);
		if(!is_array($coords)) {
			$triesLeft -= 1;
			usleep(250);
		}
	}
	//if($triesLeft < 3) echo "tries left: $triesLeft<p>";
	return $coords;
}

function getCoordinatesFromGoogle($addr) {
	global $googleMapAPIKey, $googleMapGeocodingAPIKey;
	
	//$googleMapGeocodingAPIKey = "AIzaSyAoIC_JqjTucOXpvgACxeHsCNygd2h-RKU";
	if(!$googleMapGeocodingAPIKey) { //set up on 8/9/2018.  can be removed in a day or two
		global $installationSettings;
		refreshInstallationSettings();
		$googleMapGeocodingAPIKey = $installationSettings["googleMapGeocodingAPIKey"];
	}
	$url = "https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($addr)."&key=$googleMapGeocodingAPIKey";
	$response = file_get_contents($url);

	$response = json_decode($response);

	if($response->status == 'OK') {
		$coords['lat'] = $response->results[0]->geometry->location->lat;
		$coords['lon'] = $response->results[0]->geometry->location->lng;
		return $coords;
	} 
	else {
		//logError("Google Map error finding address [$addr]: ".$response->status);
		logError("Google Map error finding address [$addr]: ".print_r($response, 1));
		return $response->status;
	}
}
	

function distance($clientGeocode, $addrOrLatLon, $unitsToReturn=null) {
	// in KM, unless global $units is 'mi'
	global $units;
	$unitsToReturn = $unitsToReturn ? $unitsToReturn : $units;
	$geocode = $addrOrLatLon && is_array($addrOrLatLon) ? $addrOrLatLon : getLatLon($addrOrLatLon);
	$lat1 = (float)$geocode['lat'];
	$lon1 = (float)$geocode['lon'];
	$lat2 = (float)$clientGeocode['lat'];
	$lon2 = (float)$clientGeocode['lon'];
}
	
  $dist = rad2deg(acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2)))) * 60 * 1.1515;
//echo "[$dist] $addrOrLatLon: ".print_r($geocode, 1).'<br>';
	$unitsToReturn = $unitsToReturn ? $unitsToReturn : $units;
	$unitsToReturn = $unitsToReturn ? $unitsToReturn : 'km'; /* default KM */
	$factors = array('mi'=>1, 'km'=>1.609344, 'ft'=>5280, 'm'=>1609.344);
	$factor = $factors[$unitsToReturn];
	$finalDistance = $dist * $factor;
}
	return $unitsToReturn == 'mi' ? round($finalDistance, 1) : $finalDistance;
}

function personsHTMLAddress($person) {
	$add = array();
	foreach(array('street1','street2','city','state','zip') as $f) {
		$add[$f] = $person[$f];
	}
	return htmlFormattedAddress($add);
}

