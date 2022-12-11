<? // client-cluster-map.php
/*
Show a map for a given client showing:
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

$locked = locked('o-');

$IMGHOST = "https://{$_SERVER["HTTP_HOST"]}/";
//$IMGHOST = "";

extract(extractVars('id,radius,units,pop', $_REQUEST));

$client = getClient($id);
$radius = trim($radius) ? (float)(trim($radius)) : trim($radius);
$radius = $radius ? $radius : ($_SESSION['client-cluster-search-radius'] ? $_SESSION['client-cluster-search-radius'] : 1);
$_SESSION['client-cluster-search-radius'] = $radius;
if($units) $_SESSION['client-cluster-search-units'] = $units;
if(!$units && !$_SESSION['client-cluster-search-units']) {
	$distanceUnit = getI18Property('distanceunit', 'mile,mi');
	$distanceUnit = explode(',', $distanceUnit);
	$units = $distanceUnit[1];
}
else if(!$units) $units = $_SESSION['client-cluster-search-units'];

if(!googleAddress($client)) 
	$_SESSION['frame_message'] = $error = "There is not sufficient address information on file for {$client['fname']} {$client['lname']} to produce a map.";
else  {
	$clients = fetchAssociationsKeyedBy("SELECT * FROM tblclient WHERE active =  1 AND clientid != {$client['clientid']} ORDER BY lname, fname", 'clientid');
	$clientAddrs = array();
	$homelessClients = array();
	foreach($clients as $cclient) {
		if($addr = googleAddress($cclient)) $clientAddrs[$cclient['clientid']] = $addr;
		else $homelessClients[] = $cclient['clientid'];
	}
	if(!$clientAddrs) $_SESSION['frame_message'] = "No client address information was found.";
}

// ============================
$breadcrumbs = "<a href='client-edit.php?id=$id'>{$client['fname']} {$client['lname']}</a>";
$pageTitle = "Clients who live within a $radius $units radius of {$client['fname']} {$client['lname']}";
$extraHeadContent = '<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<style>#radius {width:30px;}
.noteselement {cursor:pointer;}</style>';
if($pop) include "frame-bannerless.php";
else include "frame.html";
if($error) {
	if(!$pop) include "frame-end.html";
	else echo "<h2>$pageTitle</h2><span class='fontSize1_2em'>$error</span>";
	exit;
}
if($clientAddrs) {
	echo "<form name='cmap' style='display:inline;font-size:12px;' METHOD='POST'>";
	echoButton('', 'Show', 'go()');
	//labeledInput($label, $name, $value=null, $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=null, $noEcho=false)
	labeledInput(' clients who live within a ', 'radius', $radius, null, 'radiusInput');
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

?>
<img src='art/pin-sunset.png'>- <?= $clientHomeLabel ?>
<img src='art/spacer.gif' height=1 width=10>
<? if($clientAddrs) { ?>
<img src='art/pin-green.png'>- Prospective Client Home 
<img src='art/pin-blue.png'>- Actual Client Home 
<img src='art/spacer.gif' height=1 width=10>
<?
}

foreach($clients as $i => $cclient) {
	$add = array();
	foreach(array('street1','street2','city','state','zip') as $f) {
		$add[$f] = $cclient[$f];
		$clients[$i]['address'] = htmlFormattedAddress($add);
	}
}


function googleAddress($person) {
	if($person['city'] && $person['state'] ) $addr = trim("{$person['street1']}, {$person['city']}, {$person['state']}");
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
//print_r($clients);exit;
// =========================================================
// copied from googleMap.php
$mapId = 'singlemap';
?>

<style>
.maplabel {color:black;font-size:12px;}
.addrTable {margin-left: 1px;}
.addrTable td {border: solid darkgrey 0px;}
</style>

<?    
$googleVersion = 3;
if($googleVersion == 3) require_once('GoogleMapAPIv3.php');
else require_once('GoogleMapAPI.classSINGLE.php');

$googleAPIKey = $googleMapAPIKey;  //from init_session.php  //"ABQIAAAAK5DZh3ZV8WE3KqE3qwLoOBRTd7GOr-Pj_JdPg_LHg_41MAgVahQ0k8jOTF9nSngAVbLuLRvC8HT0ew"; // for iwmr.info
$map = new GoogleMapAPI($mapId);
$map->map_type = "ROADMAP";
if($googleVersion != 3) $map->setAPIKey($googleAPIKey); // unnecessary in v3
}



// !! $map->setLookupService('YAHOO');
// setup database for geocode caching
//    echo "BEFORE<P>";
/*
CREATE TABLE `geocodes` (
`address` VARCHAR(255) NOT NULL DEFAULT '',
`lat` FLOAT NOT NULL DEFAULT 0,
`lon` FLOAT NOT NULL DEFAULT 0,
PRIMARY KEY(`address`)
)
ENGINE = InnoDB;
*/
$map->setDSN("mysql://$dbuser:$dbpass@$dbhost/$db");

// enter YOUR Google Map Key
//$map->setAPIKey('ABQIAAAAK5DZh3ZV8WE3KqE3qwLoOBRsBIsYDhcC3JmPleuN_fBYmFbolhQLQWhOCpB61BznRvFDWQlAfoGwlQ');
$map->_db_cache_table = 'geocodes';    

//$map->disableZoomEncompass();

/**
 * adds a map marker by address
 *
 * @param string $address the map address to mark (street/city/state/zip)
 * @param string $title the title display in the sidebar
 * @param string $html the HTML block to display in the info bubble (if empty, title is used)
 */
// create some map markers
// createMarkerIcon($iconImage,$iconShadowImage = '',$iconAnchorX = 'x',$iconAnchorY = 'x',$infoWindowAnchorX = 'x',$infoWindowAnchorY = 'x')
$clientIcon = $map->createMarkerIcon("{$IMGHOST}art/pin-sunset.png",$iconShadowImage = '',$iconAnchorX = 'x',$iconAnchorY = 'x',$infoWindowAnchorX = 'x',$infoWindowAnchorY = 'x');
$provIcon = $map->createMarkerIcon("{$IMGHOST}art/pin-blue.png",$iconShadowImage = '',$iconAnchorX = 'x',$iconAnchorY = 'x',$infoWindowAnchorX = 'x',$infoWindowAnchorY = 'x');
//$reddotIcon = $map->createMarkerIcon("{$IMGHOST}art/pin-reddot.png",$iconShadowImage = '',$iconAnchorX = 'x',$iconAnchorY = 'x',$infoWindowAnchorX = 'x',$infoWindowAnchorY = 'x');
$defaultSitterIcon = $map->createMarkerIcon("{$IMGHOST}art/pin-green.png",$iconShadowImage = '',$iconAnchorX = 'x',$iconAnchorY = 'x',$infoWindowAnchorX = 'x',$infoWindowAnchorY = 'x');
//$overdueicon = $map->createMarkerIcon("{$IMGHOST}art/pin-pulsating.gif",$iconShadowImage = '',$iconAnchorX = 'x',$iconAnchorY = 'x',$infoWindowAnchorX = 'x',$infoWindowAnchorY = 'x');


$label = "<span class=maplabel>$googleAddress</span>";
//$map->sidebar = false;
$add = array();
foreach(array('street1','street2','city','state','zip') as $f)
	$add[$f] = $client[$f];
$client['address'] = htmlFormattedAddress($add);
$html = "{$client['fname']} {$client['lname']}'s home<br>{$client['address']}";
if($addr = googleAddress($client)) {
	if($googleVersion == 3) 
		$map->addMarkerByAddress($addr,"{$client['fname']} {$client['lname']}'s home", $html,
																	"{$client['fname']} {$client['lname']}'s home",
																	"{$IMGHOST}art/pin-sunset.png",
																	"{$IMGHOST}art/pin-shadow.png");
	else {
		$map->addMarkerByAddress($addr,"{$client['fname']} {$client['lname']}'s home", $html);
		$map->_icons[] = $clientIcon;
	}
	$clientGeocode = getLatLon($addr);
}

//echo 'Prov: '.googleAddress($prov).'<br>';
//$addresses = array(googleAddress($prov));
//echo "<p>CLIENT: ".print_r($clientGeocode, 1).'<br>';
//echo "<pre>".print_r($clientAddrs, 1)."<pre>";
foreach($clients as $cclient) {
//if(mattOnlyTest() && 	$client['clientid']	== 765) echo "<p>".print_r($appointmentTracks, 1)."<p>".print_r($tracks, 1);
//print_r($client);
	if(($addr = $clientAddrs[$cclient['clientid']]) &&
			distance($clientGeocode, $clientAddrs[$cclient['clientid']]) < $radius) {
		$html = "{$cclient['fname']} {$cclient['lname']}'s home<div style='display:block;padding-left:10px;'>{$cclient['address']}</div>";
	
		$iconToUse = $cclient['prospect'] ? "pin-green.png" : "pin-blue.png";
		if($googleVersion == 3) {
}
			$map->addMarkerByAddress($addr,"{$cclient['fname']} {$cclient['lname']}'s home", $html,
																		"{$cclient['fname']} {$cclient['lname']}'s home",
																		"{$IMGHOST}art/$iconToUse",
																		"{$IMGHOST}art/pin-shadow.png");
		}
		else {
			$map->addMarkerByAddress($addr,"{$cclient['fname']} {$cclient['lname']}'s home", $html);
			$map->_icons[] = $iconToUse;
		}
		//$overdueicon;
		//$addresses[] = $addr;
	}
	else if(!$clientAddrs[$cclient['clientid']]) $notes[] = "No home address was found for {$cclient['fname']} {$cclient['lname']}.";
//echo 'Client: '.googleAddress($client).'<br>';
}

function getLatLon($toAddress) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	$googleVersion = 3;
	//if($googleVersion == 3) require_once('GoogleMapAPIv3.php');
	//else require_once('GoogleMapAPI.classSINGLE.php');
	static $map;
	global $googleMapAPIKey, $dbuser, $dbpass, $dbhost, $db;
	if(!$map) {
    $map = new GoogleMapAPI('xyz');
    $map->setDSN("mysql://$dbuser:$dbpass@$dbhost/$db");
    if($googleVersion != 3) $map->setAPIKey($googleMapAPIKey);
    $map->_db_cache_table = 'geocodes';
	}
	$coords = $map->getGeocode($toAddress);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1); 
	return $coords;
}


function distance($clientGeocode, $sitterAddr) {
	global $units;
	$geocode = getLatLon($sitterAddr);
	$lat1 = $geocode['lat'];
	$lon1 = $geocode['lon'];
	$lat2 = $clientGeocode['lat'];
	$lon2 = $clientGeocode['lon'];
	
  $dist = round(rad2deg(acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2)))) * 60 * 1.1515, 1);
//echo "[$dist] $sitterAddr: ".print_r($geocode, 1).'<br>';
	return $units == 'mi' ? $dist : $dist * 1.609344; // in miles
}



if($notes) {
	echo "<p>Note: <span class='noteselement' style='display:none;' onclick=\"$('.noteselement').toggle();\"><b>Hide Notes</b></span>";
	echo "<span class='noteselement' style='display:inline;' onclick=\"$('.noteselement').toggle();\">Some clients have no address info.  <b>Click here</b> to see the list.</span>";
	echo "<ul class='noteselement' style='display:none;'>";
	foreach($notes as $note) echo "<li>$note\n";
	echo "</ul>";
}

$map->setWidth('700px');
$map->setHeight('700px');
$map->setInfoWindowTrigger('click');  //mouseover
$map->directions = true;
?>
<head>
<?php $map->printHeaderJS(); ?>
<?php $map->printMapJS($index); ?>
</head>
<body>
<?php 



function dumpControlJavascript() {
	echo <<<JS
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
$map->printMap();
$map->printOnLoad();
if(!$pop) include "frame-end.html";
