<? // providers-map.php
/*
Show a map  of sitters
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

extract(extractVars('requestid,id,radius,units,pop,prov,minutesago', $_REQUEST)); // None of these are used here.  3/8/2021

// SPECIAL
if($prov) { // show a provider snapshot
	require "frame-bannerless.php";
	providerAvailabilitySnapShot($prov);
	exit;
}

else  {
	$sitters = fetchAssociationsKeyedBy("SELECT * FROM tblprovider WHERE active =  1 ORDER BY lname, fname", 'providerid');
	$sitterAddrs = array();
	$homelessSitters = array();
	foreach($sitters as $sitter) {
		if($addr = googleAddress($sitter)) {
			$sitterAddrs[$sitter['providerid']] = $addr;
			$sitterLocs[$sitter['providerid']] = getLatLon($addr);
		}
		else $homelessSitters[] = $sitter['providerid'];
	}
	if(!$sitterAddrs) $_SESSION['frame_message'] = "No sitter address information was found.";
	
	
	
	
	$sitters = fetchAssociationsKeyedBy("SELECT * FROM tblprovider WHERE active =  1 ORDER BY lname, fname", 'providerid');

	if(!$sitterLocs) $_SESSION['frame_message'] = "No recent sitter location information was found.";
	$providerNames = fetchKeyValuePairs(
				"SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name
					FROM tblprovider
					WHERE active = 1
					ORDER BY name");
	$allPins = googlePinFileNames(globalURL('art'));
	foreach(array_keys($providerNames) as $i => $pid) {
		$providerPins[$pid] = $allPins[$i];
	}
}
// ============================
$breadcrumbs = fauxLink("Sitter List", 'document.location.href="provider-list.php"', 1, "Sitter list.");
$pageTitle = "Sitter Homes";
if($pop) include "frame-bannerless.php";
else include "frame.html";
if($error) {
	if(!$pop) include "frame-end.html";
	else echo "<h2>$pageTitle</h2><span class='fontSize1_2em'>$error</span>";
	exit;
}

	echo "<div onclick='$(\"#legend\").toggle()' style='width:90%;display:block;cursor:pointer;font-size:1.2em;padding:5px;'>
		".echoButton('null', 'Map Key', "$(\"#legend\").toggle()", 1, 2); // border:solid #C0FFFF 3px;
	echo "<table id='legend' style='display:none;margin-top:10px;width:100%;'><tr>";
	$numcols = 6;
	$tdwidth = round(100/$numcols);
	foreach($providerNames as $id => $name) {
		if($col == $numcols) {$col = 0; echo "</tr>"; if($printed < count($providerNames)) echo "<tr>";}
		$col += 1;
		$printed += 1;
		echo "<td style='width:$tdwidth%'><img src='{$providerPins[$id]}'}> $name</td>";
	}
	echo "</tr></table>";
	echo "</div>";

?>
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

//echo 'Prov: '.googleAddress($prov).'<br>';
//$addresses = array(googleAddress($prov));
//echo "<p>CLIENT: ".print_r($clientGeocode, 1).'<br>';
foreach($sitterLocs as $provid=>$coord) {
//if(mattOnlyTest() && 	$client['clientid']	== 765) echo "<p>".print_r($appointmentTracks, 1)."<p>".print_r($tracks, 1);
//print_r($client);
//echo "{$sitter['providerid']}<br>\n";
	$sitter = $sitters[$provid];
	$details = "<br>"
		.fauxLink('Details...', "openDetails({$coord['providerptr']})", 
							1, 'Sitter details');
	$html = "{$sitter['fname']} {$sitter['lname']}'s home";
	$marker = $coord; //getLatLon($addr);
	$marker['address'] = personsHTMLAddress($sitter);
	$marker['googleaddress'] = $sitterAddrs[$provid];
	$marker['icon'] = $providerPins[$provid];
	//echo "[[$provid: {$marker['icon']}]]<br>";
	//$marker['zIndex'] = $iconToUse == 'defaultprovider' ? 1 : 0;
	$marker['hovertext'] = str_replace("'", "&apos;", "{$sitter['fname']} {$sitter['lname']}'s home");
	$marker['infotext'] = $html.'<hr>'.$marker['address'];
	$markers[] = $marker;
	
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
function googlePinFileNames($root='art') {
	static $fnames;
	if($fnames) return $fnames;
	$colors = explode(',', 'red,yellow,orange,paleblue,green,blue,purple,darkgreen,pink,brown');
	$letters = explode(',', 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z');
	$colorOffset = $letterOffset = 0;
	$alphabetCycles = 0;
	while(count($fnames) < 26 * count($colors)) {
		$fnames[] = "$root/googlemapmarkers/{$colors[$colorOffset]}_Marker{$letters[$letterOffset]}.png";
		$colorOffset += 1;
		$letterOffset += 1;
		if($colorOffset == count($colors)) $colorOffset = 0;
		if($letterOffset == 26) {
			$letterOffset = 0;
			$alphabetCycles += 1;
			$colorOffset = $alphabetCycles;
		}
	}
	return $fnames;
}


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
                icon: {url:markerinfo.icon},
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
