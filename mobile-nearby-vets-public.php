<? // mobile-nearby-vets-public.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "qrcode-fns.php";
require_once "google-map-utils.php";

extract($_REQUEST);
if($_REQUEST['lat'] && !$_REQUEST['go']) {
	include "zip-lookup.php";
	$zip = zipFromCoords($_REQUEST['lat'], $_REQUEST['lon']);
	if($zip) {
		echo $zip;
		exit;
	}
}



$agent = $_SERVER["HTTP_USER_AGENT"];
$tokens = getMobileUserAgentTokensString(); // ini_session.php
$maybe = 'iPad';
$tokens = explode(',',$tokens);
$ismobile = false;
foreach($tokens as $token) if(strpos($agent, $token) !== FALSE) $ismobile = true;

//$ismobile = $ismobile || ($_SERVER['REMOTE_ADDR'] == '68.225.89.173');


if(!$ismobile && !$_REQUEST['mobile']) { 
	globalRedirect("web-nearby-vets-public.php");
	exit;
}
if(strpos($_SERVER["REQUEST_URI"], '/vets') === 0) {
	// not sure why this is necessary, but the headers shows wrong otherwise
	globalRedirect("mobile-nearby-vets-public.php?mobile=1");  // mobile=1 is for testing
	exit;
}

// ==================================
$noOptions = 1;
$homeLink = 'mobile-nearby-vets-public.php?mobile=1';
$leashtimeLink = "<a href='https://{$_SERVER["HTTP_HOST"]}'><img src='art/LeashTimeTM127x31.jpg' style='border:0px;padding-bottom:5px;'></a>";
$bizName = "<div style='position:relative; left:0px; top:-20px; font-size:0.8em;'>$leashtimeLink<br>Find a Vet</div>";
require_once "mobile-frame.php";
require_once "gui-fns.php";

define(MAX_DIST, 999999);

if($go) {
	if($_POST) {
		$thisAddress = trim(($city ? trim($city).', '.trim($state) : '').' '.trim($zipcode));
		$lat = null;
	}
	$distanceUnit = getI18Property('distanceunit', 'mile,mi');
	$miles = $distanceUnit == 'mile,mi';
	$distanceUnit = explode(',', $distanceUnit);
	$distanceUnit = $distanceUnit[1];
	if($thisAddress) $vets = getVetsWithAddresses(null, null, $miles, $thisAddress);
	else if($lat) $vets = getVetsWithAddresses($lat, $lon, $miles);
	if(!$vets) echo "No vets found.<p>";
	else { 	
?>
<div class='pagecontentdiv'>
The nearest vets to <?= $lat ? 'Your Location' : "<span class='petfont tip'>$thisAddress</span>" ?>:<p>
<table class='lean pagecontentdiv'>
<?
	foreach($vets as $vet) {
		$safeVetName = safeValue($vet['clinicname']);
		$editVetAction = "document.location.href=\"viewVet-mobile-public.php?id={$vet['clinicid']}\"";
		$vetLink = fauxLink($vet['clinicname'], $editVetAction, 1, 1);		
		
		$phoneLink = $vet['phone'] ? fauxLink($vet['phone'], "openCallBox(\"$safeVetName\", \"{$vet['phone']}\")", 1, 1).' - ' : '';
		$mapLink = fauxLink($vet['address'], $editVetAction, 1, 1);
		$distance = $vet['distance'] == MAX_DIST ? '?' : $vet['distance'];
		if($distance != '?') {
			$googleAddress = urlencode($vet['address']);
			$url = "http://maps.google.com/maps?&um=1&daddr=$googleAddress&sa=X&oi=geocode_result&ct=directions-to&resnum=1";
			$distance = "<a href='$url' target='directions'>$distance $distanceUnit</a>";
		}
		echo "<tr><td>$distance</td><td>$vetLink</td></tr>"
		."<tr><td colspan=2 class='tinynote' style='text-align:left;'>$phoneLink  $mapLink</td></tr>"
		."<tr><td class='visitlistsepr' colspan=2>&nbsp;</tr>";
	}
?>
</table>
</div>
<?
	}
}

function cmpDistance($a, $b) {
	$a = $a['distance'];
	$b = $b['distance'];
	return $a < $b ? -1 : ($a == $b ? 0 : 1);
}

function getVetsWithAddresses($lat, $lon, $miles, $address=null) {
	if($address) {
		$coords = getLatLon($address);
		$lat = $coords['lat'];
		$lon = $coords['lon'];
	}
	$phones = explode(',', 'officephone,cellphone,pager,homephone');
	
	$q = "SELECT street1, street2, city, state, zip, clinicid, clinicname, officephone, cellphone, pager, homephone,
							 IF(lname, CONCAT_WS(' ', fname, lname, creds), '') as vet,
							 (((acos(sin((".$lat."*pi()/180)) * sin((`lat`*pi()/180))+cos((".$lat."*pi()/180)) * cos((`lat`*pi()/180)) * cos(((".$lon."- `lon`)*pi()/180))))*180/pi())*60*1.1515) as distance 
				FROM vetclinic_us
				WHERE lat IS NOT NULL AND lon IS NOT NULL AND duplicate =0 AND LENGTH( CONCAT_WS( '', street1, city, state, zip ) ) >0 AND 
				((city IS NOT NULL AND LENGTH(TRIM(city)) > 0)
					OR (zip IS NOT NULL AND LENGTH(TRIM(zip)) > 0))
				ORDER BY distance ASC
				LIMIT 20";
	$list = fetchAssociations($q);
	
	foreach($list as $i => $clinic) {
		$list[$i]['address'] = $clinic['address'] = oneLineAddress($clinic);
		$list[$i]['distance'] = (int)($clinic['distance'] * 100) / 100.0;
//echo "<font color=red>{$list[$i]['distance']}</font> {$clinic['address']}<br>";
		foreach($phones as $fld) if($phone = $clinic[$fld]) break;
		$list[$i]['phone'] = $phone;
	}
	return $list;
}


$latDiv = "<div style='border: solid black 1px;background:#f8f8f8;padding:3px;display:block;font-size:1.1em'>"
."<b>This Location</b>   <span style='font-size:0.7em'> (Tap here)<br>(Latitude: LAT  Longitude: LON)<br></span>"
."</div>";
if($lat) {
	$initalLat = str_replace('LAT', $lat, str_replace('LON', $lon, $latDiv));
	$showLat = 'inline';
	$url = "mobile-nearby-vets-public.php?mobile=1&go=1&lat=$lat&lon=$lon";
}
else $showLat = 'none';
?>


Find the vets closest to:<p>
<form name='lookup' method='POST'>
<span id='latlonspan' style='display:<?= $showLat ?>;'>
<center><a id='latlon' href='<?= $url ?>'><?= $initalLat ?></a></center><center>- OR -</center><p></p>
</span>
<table border=0>
<tr><td colspan=2 class='tip'><b>Required: ZIP or City+State</b>
<tr><td>
Address: <input id='street1' name='street1' value='<?= $_REQUEST['street1'] ?>'><br>
City: <input id='city' name='city' value='<?= $_REQUEST['city'] ?>'><br>
State: <input id='state' name='state' value='<?= $_REQUEST['state'] ?>'><br>
ZIP: <input id='zipcode' name='zipcode' value='<?= $_REQUEST['zipcode'] ?>'>
<input type='hidden' name='go' id='go'>
</td>
<td><input type=button value='Go' style='font-size:2em' onclick='goNow()'>
</td></tr></table>
</form>

<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="jquery.busy.js"></script> 	
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<script type="text/javascript" src="common.js"></script>
<script type="text/javascript" src="ajax_fns.js"></script>
<script language='javascript'>
var callBox = "<?= telephoneSMSDialogueHTML($name=null, $tel=null, $sms=false, $class=false); ?>";

function openCallBox(telname, tel) {
	var box = callBox;
	box = box.replace('#NAME#', telname);
	box = box.replace(/#TEL#/g, tel);
	$.fn.colorbox({	html: box,	width:"280", height:"200", iframe:false, scrolling: "auto", opacity: "0.3"});
}

function getCoords() {

	if(navigator.geolocation) {
		var lat, lon, speed, heading, geoerror;
		navigator.geolocation.getCurrentPosition(
			function(position) {
				lat = position.coords.latitude;
				lon = position.coords.longitude;
				setZipCode(lat, lon);
				setLatLon(lat, lon);
				//document.getElementById('speed').value = position.coords.speed;
				//document.getElementById('heading').value = position.coords.heading;
				//document.getElementById('pleasewait').style.display = 'none';
			}, 
			function(error) {
				geoerror = error.code;
				switch (error.code)  {
					case 0: 
					alert("Error retrieving location");
					break;

					case 1:
					alert("User denied");
					break;

					case 2: 
					alert("Browser cannot determine location");
					break;

					case 3: 
					alert("Time out");
					break;
				}
				alert(geoerror);
				//document.getElementById('geoerror').value = geoerror;
				//document.getElementById('pleasewait').style.color = 'red';
				//document.getElementById('pleasewait').innerHTML = 'Location unavailable';
		},
		{enableHighAccuracy: true, timeout:2000, maximumAge:300000} // accept cached positions up to five minutes old.  times out in 20 seconds
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
function goNow() {
	var city = document.getElementById('city').value,
			state = document.getElementById('state').value,
			zipcode = document.getElementById('zipcode').value;
	if(jstrim(zipcode) || (jstrim(city) && jstrim(state))) {
		document.getElementById('go').value = 1;
		document.lookup.submit();
	}
	else alert("Either ZIP or City+State must be supplied.");
}

function setLatLon(lat, lon) {
	document.getElementById('latlon').innerHTML = 
	"<div style='border: solid black 1px;background:#f8f8f8;padding:3px;display:block;'>"
	+"This Location   <span style='font-size:0.7em'> (Tap here)<br>(Latitude: "+lat+"  Longitude: "+lon+")<br></span>"
	+"</div>";
	
	document.getElementById('latlon').href = "mobile-nearby-vets-public.php?mobile=1&go=1&lat="+lat+"&lon="+lon;
	document.getElementById('latlonspan').style.display = 'inline';
}
function setZipCode(lat, lon) {
	ajaxGetAndCallWith('mobile-nearby-vets-public.php?mobile=1&lat='+lat+'&lon='+lon, 
		function(arg, text) {document.getElementById('zipcode').value = text;}
		, 0)
}
//document.write('hop-');
<? if(!$zipcode && !$lat) echo "getCoords();\n"; ?>
//document.write('la!');
</script>