<? // client-cluster-map2018.php
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
require_once "google-map-utils.php";

$locked = locked('o-');


$IMGHOST = "https://{$_SERVER["HTTP_HOST"]}/";
//$IMGHOST = "";

extract(extractVars('pid,id,radius,units,pop', $_REQUEST));

if($id) $focus = getClient($id);
else if($pid) $focus = getProvider($pid);
$focusType = $focus['clientid'] ? 'client' : 'provider';

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

if(!googleAddress($focus)) 
	$_SESSION['frame_message'] = $error = "There is not sufficient address information on file for {$focus['fname']} {$focus['lname']} to produce a map.";
else  {
	$addArray = array();
	foreach(array('street1','street2','city','state','zip') as $f)
		$addArray[$f] = $focus[$f];
	$focus['address'] = htmlFormattedAddress($addArray);
	$html = "{$focus['fname']} {$focus['lname']}'s home<br>{$focus['address']}";
	
	
	$markers = array();

	$focus['address'] = htmlFormattedAddress($focus);
	if($focusGoogleAddress = googleAddress($focus)) {
		$marker = getLatLon($focusGoogleAddress);
		if(is_string($marker)) {
			$error = "Google could not find a location for [$focusGoogleAddress]";
		}
		else {
			$focusGeocode = $marker;
			$marker['address'] = personsHTMLAddress($focus);
			$marker['googleaddress'] = $focusGoogleAddress;
			$marker['icon'] = 'focus';
			$marker['zIndex'] = 999;
			$marker['hovertext'] = str_replace("'", "&apos;", "{$focus['fname']} {$focus['lname']}'s home");
			$marker['infotext'] = str_replace("'", "&apos;", "{$focus['fname']} {$focus['lname']}'s home<br>{$marker['address']}");
			$markers[] = $marker;
		}
	}
	
	
	
	// NEARBY CLIENTS
	if($focus['clientid']) $excludeFocusClient = "AND clientid != {$focus['clientid']}";
	$result = doQuery("SELECT * FROM tblclient WHERE active =  1 $excludeFocusClient ORDER BY lname, fname", 'clientid');
	$clientAddressesFound = 0;
	$homelessClients = array();
	$closeClients = array();
  while($cclient = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		$prospectiveClients += $cclient['prospect'] ? 1 : 0;
		if($cclient['clientid'] == $focus['clientid']) continue;
		if($addr = googleAddress($cclient)) {
			if(distance($focusGeocode, $addr) < $radius) {
				$cclient['address'] =personsHTMLAddress($cclient); //personsHTMLAddress // htmlFormattedAddress
				$marker = getLatLon($addr);
				$marker['address'] = $cclient['address'];
				$marker['googleaddress'] = $addr;
				$marker['icon'] = $cclient['prospect'] ? 'prospect' : 'client';
				$marker['zIndex'] = 100;
				$marker['hovertext'] = str_replace("'", "&apos;", "{$cclient['fname']} {$cclient['lname']}'s home");
				$marker['infotext'] = str_replace("'", "&apos;", "{$cclient['fname']} {$cclient['lname']}'s home<br>{$marker['address']}");
				$markers[] = $marker;
				$closeClients[$cclient['clientid']] = $cclient;
				$clientAddressesFound += 1;
			}
		}
		else $homelessClients[] = $cclient['clientid'];
	}
	if(!$clientAddressesFound) $_SESSION['frame_message'] = "No client address information was found.";

	// NEARBY SITTERS
	$result = doQuery("SELECT * FROM tblprovider WHERE active =  1 ORDER BY lname, fname", 'clientid');
	$sitterAddressesFound = 0;
	$homelessSitters = array();
	$closeSitters = array();
  while($sitter = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		if($sitter['providerid'] == $focus['providerid']) continue;
		if($addr = googleAddress($sitter)) {
			if(distance($focusGeocode, $addr) < $radius) {
				$sitter['address'] =personsHTMLAddress($sitter); //personsHTMLAddress // htmlFormattedAddress
				$marker = getLatLon($addr);
				$marker['address'] = $sitter['address'];
				$marker['googleaddress'] = $addr;
				$marker['icon'] = 'provider';
				$marker['zIndex'] = 10;
				$marker['hovertext'] = str_replace("'", "&apos;", "{$sitter['fname']} {$sitter['lname']}'s home");
				$marker['infotext'] = str_replace("'", "&apos;", "{$sitter['fname']} {$sitter['lname']}'s home<br>{$marker['address']}");
				$markers[] = $marker;
				$closeSitters[$sitter['providerid']] = $sitter;
				$sitterAddressesFound += 1;
			}
		}
		else $homelessSitters[] = $cclient['clientid']; // ????
	}
	//if(!$sitterAddressesFound) $_SESSION['frame_message'] = "No client address information was found.";
}

// ============================
$breadcrumbs = "<a href='$focusType-edit.php?id=$id'>{$focus['fname']} {$focus['lname']}</a>";
$pageTitle = "Active clients who live within a $radius $units radius of {$focus['fname']} {$focus['lname']}";
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
if(TRUE || $clientAddressesFound) {
	echo "<form name='cmap' style='display:inline;font-size:12px;' METHOD='POST'>";
	echoButton('', 'Show', 'go()');
	//labeledInput($label, $name, $value=null, $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=null, $noEcho=false)
	labeledInput(' active clients who live within a ', 'radius', $radius, null, 'radiusInput');
	hiddenElement('id', $id);
	$unitOptions = array('mi'=>'mi', 'km'=>'km');
	selectElement('', 'units', $units, $unitOptions);
	echo " radius of the ".strtolower($focusLabelType)."'s home.";
if(staffOnlyTEST() || dbTEST('profpetsit')) {echo " ";fauxLink('Filter', 'offerChooser()');}
	echo "</form>";


}

?>
<br>Homes: <img src='art/pin-sunset.png'>- <?= $focusHomeLabel ?>
<img src='art/spacer.gif' height=1 width=10>
<? if($clientAddressesFound) { ?>
<? if($prospectiveClients) echo "<img src='art/pin-green.png'>- Prospective Client "; ?>
<img src='art/pin-blue.png'>- Actual Client 
<img src='art/pin-yellow.png'>- Sitter 
<img src='art/spacer.gif' height=1 width=10>
<?
}

$t0 = time();
if($closeClients || $closeSitters)  {
	echo "<span class='addressList' style='display:none;' onclick=\"$('.addressList').toggle();\"><b>Hide Address List</b></span>";
	echo "<span class='addressList' style='display:inline;' onclick=\"$('.addressList').toggle();\"><b><u>Show Address List</u></b></span>";
	if($closeSitters) {
		echo "<p class='addressList' style='display:none;'>Sitter Addresses</p>";
		echo "<ul class='addressList' style='display:none;'>";
		foreach($closeSitters as $id=>$sitter) {
			//$name = fetch
			echo "<li>{$sitter['fname']} {$sitter['lname']} - {$sitter['address']}\n";
		}
		echo "</ul>";
	}
	if($closeClients) {
		echo "<p class='addressList' style='display:none;'>Client Addresses</p>";
		echo "<ul class='addressList' style='display:none;'>";
		foreach($closeClients as $id=>$client) {
			//$name = fetch
			echo "<li>{$client['fname']} {$client['lname']} - {$client['address']}\n";
		}
		echo "</ul>";
	}
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
var map;
var nonTargetMarkerLabels = []; // to make pins removable
var nonTargetMarkers = []; // to make pins removable
var nonTargetMarkerIcons = []; // to make pins removable
var chooserHTML;
function initMap() {
	var options = {mapTypeIds: ["ROADMAP"]};//new google.maps.MapTypeControlOptions;
	//options.mapTypeIds = {ROADMAP};
	map = new google.maps.Map(document.getElementById('map'), {
		mapTypeControlOptions: options
		//center: new google.maps.LatLng(-33.863276, 151.207977),
		//zoom: 12
	});
	
	var infoWindow = new google.maps.InfoWindow;
	var markers = /* JSON.parse('*/<?= json_encode($markers)  ?>/*') */;
	
	//var markers = JSON.parse('[{"lat":"38.8815","lon":"-77.1741","address":"250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046",
	//"googleaddress":"250 S Maple Ave, Falls Church, VA","icon":"client","zIndex":999,"hovertext":"Elroy Krum's home","infotext":"Elroy Krum's home<br>250 S Maple Ave<br>Apt 4C<br>Falls Church, VA 22046"},{"lat":"38.8362","lon":"-77.1089","address":"4713 West Braddock Road #10, Alexandria, VA","googleaddress":"4713 West Braddock Road #10, Alexandria, VA","icon":"provider","zIndex":0,"hovertext":"Brian Martinez&apos;s home","infotext":"Brian Martinez&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>4713 West Braddock Road #10<br>Alexandria, VA 22311<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=42\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8975","lon":"-77.1297","address":"5015 23rd Road N, ARLINGTON, VA","googleaddress":"5015 23rd Road N, ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"John Masters&apos;s home","infotext":"John Masters&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>5015 23rd Road N<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=17\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8932","lon":"-77.1135","address":"1810 N. Taylor St., ARLINGTON, VA","googleaddress":"1810 N. Taylor St., ARLINGTON, VA","icon":"provider","zIndex":0,"hovertext":"Josh Odmark&apos;s home","infotext":"Josh Odmark&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>1810 N. Taylor St.<br>ARLINGTON, VA 22207<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=20\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8834","lon":"-77.1765","address":"40 James St, 3b, Falls Church, VA","googleaddress":"40 James St, 3b, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Cam Stull&apos;s home","infotext":"Cam Stull&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>40 James St, 3b<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=27\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"},{"lat":"38.8854","lon":"-77.1715","address":"228 Governers Ct, Falls Church, VA","googleaddress":"228 Governers Ct, Falls Church, VA","icon":"provider","zIndex":0,"hovertext":"Elizabeth Tanner&apos;s home","infotext":"Elizabeth Tanner&apos;s home<div style=&apos;display:block;padding-left:10px;&apos;>228 Governers Ct<br>Falls Church, VA 22046<\/div><br><a class=&apos;fauxlink&apos; onClick=&apos;openConsoleWindow(\"provdetails\",\"client-provider-map.php?prov=22\", 500,400)&apos; title=&apos;Sitter details&apos;  >Details...<\/a>"}]');	
	var icons = {
		focus: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-sunset.png"
						},
		client: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-blue.png"
						},
		prospect: { // anchor: -- by default, bottom center
							url: "<?= $IMGHOST ?>art/pin-green.png"
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

<? if(mattOnlyTEST()) { ?>if(typeof markerinfo.hovertext == 'undefined') alert(markerinfo);<? } ?>          
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
		if(markerinfo.icon != 'focus') {
			nonTargetMarkers.push(marker);
			nonTargetMarkerLabels.push(markerinfo.hovertext.trim());
			nonTargetMarkerIcons.push("<img src='"+marker.icon.url+"' style='vertical-align:middle;'>");
		}
              
		bounds.extend(point);
	}
	map.fitBounds(bounds);       // auto-zoom
	map.panToBounds(bounds);     // auto-center
	
}
</script>

<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>	


<script async defer
src="https://maps.googleapis.com/maps/api/js?key=<?= $googleMapAPIKey ?>&callback=initMap">
</script>
<script>
function getChooserHTML() {
	var checkedCount = 0;
	var chooserHTML = '';
	for (var i=0; i < nonTargetMarkers.length; i++) {
		let marker = nonTargetMarkers[i];
		let key = nonTargetMarkerLabels[i];
		var CHECKED = marker.getMap() != null ? 'CHECKED' : '';
		checkedCount += CHECKED ? 1 : 0;
		chooserHTML += "<li><input id='cb_"+i+"' type='checkbox' "+CHECKED+" onclick='toggle("+i+")'> "
			+"<label for='cb_"+i+"'>"+nonTargetMarkerIcons[i]+' '+nonTargetMarkerLabels[i]+"</label>";
	}
	chooserHTML += "</ul></form>";
	CHECKED = checkedCount == nonTargetMarkers.length ? 'CHECKED' : '';
	
	chooserHTML = 
		"Show only the following:<form><ul>"+
		"<li><input id='allboxes' type='checkbox' "+CHECKED+" onclick='selectAll(this)'>"+
		"<label for='allboxes'>All Pins</label>"+		chooserHTML;
	return chooserHTML;
}

function toggle(i) {
	var marker = nonTargetMarkers[i];
//alert(marker.getMap());	
	marker.setMap(marker.getMap() ? null : map); // map is global
	$('#allboxes').prop( "checked", false);
}

function offerChooser() {
	$.fn.colorbox({html:getChooserHTML(), width:"300", height:"500", scrolling: true, opacity: "0.3"});
}

function selectAll(el) {
	var on = el.checked;
	for (var i=0; i < nonTargetMarkers.length; i++) {
		var marker = nonTargetMarkers[i];
		$('input').prop( "checked", on);
		marker.setMap(on ? map : null); // map is global
	}
}
</script>

<?
if($screenLog) echo $screenLog;
if(!$pop) include "frame-end.html";
