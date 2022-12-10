<? //itinerary.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "appointment-fns.php";
require_once "GoogleMapAPI.class.php";
//require_once "visit-sheet-fns.php";
require_once "key-fns.php";

//print_r($_POST);echo "<p>{$_SERVER['SCRIPT_NAME']}";exit;	
/*
Called from:
1. Itinerary button: ($noControls == false, $emailprovider == false, $provider, etc)
2. This page, "Send to Provider" button: ($noControls == false, $emailprovider == true, $provider, etc)
3. This page (file_get_contents): 
		($noControls == true, $emailprovider == false, no $_SESSION)
		$db, $dbhost, $dbuser, $dbpass, $token

*/
if($_REQUEST['noControls']) { // Case #3
	include "common/init_db_common.php";
	include "response-token-fns.php";
	$token = $_REQUEST['token'] ? consumeTokenRow($_REQUEST['token']) : null;
	if(!$token)
		locked('vc');
	else {
		// extract $db, $dbhost, $dbuser, $dbpass from request
		extract($_REQUEST);
		include "common/init_db_petbiz.php";
		$lockChecked = true;
	}
}
else locked('vc');

extract($_REQUEST);

if(userRole() == 'p') $provider = $_SESSION["providerid"];

if($emailprovider) { // Case #2
	$person = getProvider($provider);
	// confirm provider email address
	if(!$person['email']) $error = 'This provider has no email address.';  // SHOULD NOT HAPPEN; BUTTON NOT OFFERED WHEN EMAIL ABSENT
	else {
		$this_dir = substr($_SERVER['SCRIPT_NAME'],0,strrpos($_SERVER['SCRIPT_NAME'],"/"));
		list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
		include "common/init_db_common.php";
		include "response-token-fns.php";
		$token = generateSecurityToken($_SESSION['bizptr']);
		list($db, $dbhost, $dbuser, $dbpass) = array($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
		include "common/init_db_petbiz.php";
		$startingaddress = urlencode($startingaddress);
		$date = date('Y-m-d', strtotime($date));
		$parms = array('noControls=1',"reordering=$reordering","generate=$generate","provider=$provider","date=$date",
										"change=$change","startingaddress=$startingaddress","firstappointmentstart=$firstappointmentstart",
										"token=$token","db=$db","dbhost=$dbhost","dbuser=$dbuser","dbpass=$dbpass");
		$parms = join('&', $parms);
		
//echo "$mein_host$this_dir/itinerary.php?$parms";exit;
		$itinerary = file_get_contents("$mein_host$this_dir/itinerary.php?$parms");
		$parms = array("reordering=$reordering","generate=$generate","date=$date","pop=visitsheets",
										"change=$change","startingaddress=$startingaddress","firstappointmentstart=$firstappointmentstart");
		$parms = join('&', $parms);
		$link = urlencode("itinerary.php?$parms");
		$link = "<a href='$mein_host$this_dir/index.php?pop=$link'>Your Itinerary</a>";
		$body = "Dear ".providerShortName($person).',<p>Here is your visit itinerary.<p>'.
						"To view this in a web browser and see turn-by-turn directions, click here: $link<p>".$itinerary;
		include "comm-fns.php";
		//enqueueEmailNotification
		notifyByEmail($person, "Your Itinerary", $body, null, null, 'html');
		echo "<script language='javascript'>window.close();</script>";
		exit;
	}
}

$editable = isset($noControls) && $noControls ? false : true;

//print_r($_REQUEST);

if(userRole() == 'p' && $_SESSION["providerid"] != $provider) {
  echo "<h2>Insufficient rights to view this page..<h2>";
  exit;
}

$reordering = isset($reordering) && $reordering ? explode(',', $reordering) : array();

if($generate) {
}
else if($change) {
	$v = $reordering[$index+$change];
	$reordering[$index+$change] = $reordering[$index];
	$reordering[$index] = $v;
}

$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

$providerAppts = getProviderDayAppointments($provider, $date);

if(!$providerAppts) {
	echo "<h2>There are no visits to build a route</h2>";
	exit;
}

//print_r($providerAppts);exit;
$clientIds = array();

foreach($providerAppts as $appt) $clientIds[] = $appt['clientptr'];

$clientIds = array_unique($clientIds);

$clientDetails = getClientDetails($clientIds, array('googleaddress', 'address'));

/*
$itinerary = getAddressList($providerAppts, $clientDetails);
$itinerary = 'from: '.join(' to: ', $itinerary);
//echo $itinerary;exit;
*/

$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
$providerNames = getProviderShortNames();

?>
<html>
<head><title></title>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" />
</head>
<body style='padding:20px;' <?= !$generate ? '' :  'onload="onLoad();"  onunload="GUnload()"' ?>>
<table class='heading' width=100%><tr>
<td>Provider: <?= $providerNames[$provider]."'s Itinerary" ?></td>
<td align=right><?= date('F j, Y', strtotime($date)) ?></td>
</table>
<p>
<?

$stops = array();
foreach($providerAppts as $appt) {
	//$stopKey = $appt['appointmentid'].'.'.md5($appt['timeofday']); /// WHY md5?
	$stopKey = 'x'.$appt['appointmentid'];
	$stops[$stopKey][] = $appt;
}
$stopLists = array_values($stops);
$stopKeys = array_keys($stops);

//echo "REORDER[{$_REQUEST['reordering']}] => [[";print_r($reordering);echo "]]<p>";		
//echo "<<";print_r($stops);echo ">><p>";		

$warning = "";
if(count($reordering)) {
	if(count($reordering) > count($stops)) $warning = "Stops have been dropped from this itinerary since it was last rearranged.  Please review it carefully.";
	$reorderedStops = array();
	for($n=0; $n < count($stops); $n++) {
		$i = $reordering[$n];
		$reorderedStops[$stopKeys['x'.$i]] = $stops[$stopKeys['x'.$i]];
	}
	foreach($stopKeys as $stopKey) // if stops have been added since the reordering, plug add them to the list
	  if(!isset($reorderedStops[$stopKey])) {
	  	$reorderedStops['x'.$stopKey] = $stops['x'.$stopKey];
	  	$warning = "Stops have been added to this itinerary since it was last rearranged.  Please review it carefully.";
		}
	$stops = $reorderedStops;
//echo "[[";print_r($reorderedStops);echo "]]<p>";		
}
else {
	$reordering = array();
	for($i = 0; $i < count($stops); $i++) $reordering[] = $i;
}

$reordering = join(',', $reordering);


providerItinerary($stops, $clientDetails, $warning);

?>
<div id="directions"></div>

<?
function providerItinerary(&$stops, &$clientDetails, $warning) {
	global $reordering, $noconstraints, $provider, $startingaddress, $firstappointmentstart, $generate, $editable;
	$stopLists = array_values($stops);
	$stopKeys = array_keys($stops);
	if(isset($_SESSION)) {
		$secureMode = $_SESSION['preferences']['secureClientInfo'];
		$serviceNames = $_SESSION['servicenames'];
	}
	else {
		$secureMode = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'secureClientInfo'");
		$serviceNames = getServiceNamesById();
	}
	
	$providerNames = getProviderNames();
	$markerCol = $generate ? 'marker|Stop||' : '';
	$arrowCol = $editable ? "arrow|&nbsp;||" : '';
	$columns = explodePairsLine("$arrowCol$markerCol"."client|Client||service|Service||address|Address");
  $rollover = "onMouseOver='highlightRow(this,1)' onMouseOut='highlightRow(this,0)'";
	$rows = array();
	$stopIndex = 0;
	$lastAddress = null;
	$lastMarker = null;
	foreach($stops as $stopKey => $stopAppts) {
		$appt = $stopAppts[0];
		$row = array();
		$row[0] = 	$secureMode ? $clientDetails[$appt['clientptr']]['clientid'] : $clientDetails[$appt['clientptr']]['clientname'];
		$row[1] = 	$appt['timeofday'];
		$row[2] = 	$clientDetails[$appt['clientptr']]['address'];
		$arrow = upArrow($stopIndex, $stopLists);
		
		if(!$generate || $row[2] == $lastAddress) $marker = '';
		else {
			if(!$lastMarker) $marker = $firstappointmentstart ? 'A' : 'B';
			else $marker = chr(ord($lastMarker)+1);
			$lastMarker = $marker;
			"<b>$marker</b>";
		}
		$markerTD = $generate ? "<td class='topline'>$marker</td>" : '';
		$lastAddress = $row[2];
		$arrowCell = $editable ? "<td class='topline'>$arrow</td>" : '';
		$rows[] = array('#CUSTOM_ROW#'=>"<tr id='$stopKey"."_top' $rollover>$arrowCell$markerTD<td class='topline'>{$row[0]}</td><td class='topline'>{$row[1]}</td><td class='topline'>{$row[2]}</td></tr>");
		$firstAppt = true;
		foreach($stopAppts as $index => $appt) {
			$row = array();
			if($firstAppt) $row['arrow'] =  downArrow($stopIndex, $stopLists);
			else $firstAppt = false;
			$row['client'] = '&nbsp';
			$row['service'] = $serviceNames[$appt['servicecode']];
			$row['address'] = 	$appt['pets'];
			$row['#ROW_EXTRAS#'] = "id='$stopKey"."_$index' $rollover";
			$rows[] = $row;
		}
		$stopIndex++;
	}
	
	echo "<style>.joblistcolheader  {font-size: 1.05em;
  padding-bottom: 5px; 
  border-collapse: collapse;
  text-align:left;}
  .topline { border-top: solid black 1px;}
  .heading { font-size:2em; }
 </style>\n";
 	if($editable) {
		echo "<form name='itinerary' method='POST'>";

		if($warning) echo "<span style='color:red;font-weight:bold;'>WARNING:</span> $warning<p>";
		
		echo "You can reorder your appointments as you like using the Up and Down arrows.  Click the <b>Generate Directions</b> button to show turn-by-turn directions.<p>";

		echoButton('', 'Generate Directions', 'generateDirections()');

		echo ' ';
		if(!$startingaddress) {
			$providerDetails = getProviderDetails(array($provider), array('googleaddress'));
			$startingaddress = $providerDetails[$provider]['googleaddress'];
		}
		labeledInput("Starting address: ", 'startingaddress', $startingaddress, null, 'emailInput');
		labeledCheckbox("Start from first appointment", 'firstappointmentstart', $firstappointmentstart, null, null, 'firstAppointmentCheck()')  ;
		echo "<p>";

		labeledCheckbox("Don't enforce appointment time order", 'noconstraints', $noconstraints, null, null, 'document.itinerary.submit()')  ;

		if(userRole() == 'o' && $provider['email']) echoButton('', "Send to Provider",  'sendToProvider()');
		if($generate) echoButton('', "Print this page",  'javascript:window.print()', 'HotButton', 'HotButtonDown');

		echo "<p>";
	}
	
	echo "<p><div style='border: solid black 1px'>";
  //tableFrom($columns, $rows, 'WIDTH=100%', 'jobstable', null, null, null, null, null, array('keylabel' => 'keylabel'));
  
  
  
  
  tableFrom($columns, $rows, "WIDTH=100% style='background:white;'", 'jobstable', 'joblistcolheader');
	echo "</div>";
	return $stops;
}

function stopsCanSwitch(&$stopA, &$stopB, $echo=0) {
	global $noconstraints;
	if($noconstraints) return true;
if($echo)echo "<p>COMPARE: A: {$stopA['starttime']}	- {$stopA['endtime']} with B: {$stopB['starttime']}	- {$stopB['endtime']}";
	
	return (strcmp($stopA['starttime'], $stopB['starttime']) <= 0 &&
	        strcmp($stopA['endtime'], $stopB['starttime']) >= 0) ||
	       (strcmp($stopB['starttime'], $stopA['starttime']) <= 0 &&
	        strcmp($stopB['endtime'], $stopA['starttime']) >= 0);
}	        

function upArrow($stopIndex, &$stops) {
	//echo "<p>INDEX: $stopIndex STOPS: ".print_r($stops,1);
	if($stopIndex == 0 || count($stops) == 1) return '&nbsp;';
	if(stopsCanSwitch($stops[$stopIndex][0], $stops[$stopIndex-1][0]))
	  return "<img style='cursor:pointer;' src='art/sort_up.gif' 
	            onClick='move(-1, $stopIndex)'>";
	else return '&nbsp;';
}	  

function downArrow($stopIndex, &$stops) {
	if($stopIndex + 1 == count($stops)) return '&nbsp;';
	if(stopsCanSwitch($stops[$stopIndex][0], $stops[$stopIndex+1][0]))
	  return "<img style='cursor:pointer;' src='art/sort_down.gif' 
	            onClick='move(1, $stopIndex)'>";
	else return '&nbsp;';
}

hiddenElement('date', date('Y-m-d', strtotime($date)));
hiddenElement('reordering', $reordering);
hiddenElement('provider', $provider);
hiddenElement('change', '0');
hiddenElement('index', '0');
hiddenElement('generate', '0');
hiddenElement('emailprovider', '0');
?>
</form>
<script language='javascript'>
function firstAppointmentCheck() {
  if(document.itinerary != undefined) 
  	document.itinerary.startingaddress.disabled = document.itinerary.firstappointmentstart.checked;
}
function move(change, index) {
	document.itinerary.change.value = change;
	document.itinerary.index.value = index;
	document.itinerary.submit();
}

function highlightRow(row,onOff) {
	color = onOff ? 'lightgreen' : 'white';
	key = row.id.split('_');
	key = key[0];
	//alert(key);
	document.getElementById(key+'_top').style.background = color;
	for(var i=0; document.getElementById(key+'_'+i); i++)
	  document.getElementById(key+'_'+i).style.background = color;
}

function generateDirections() {
	document.itinerary.generate.value = 1;
	document.itinerary.submit();
}

function sendToProvider() {
	document.itinerary.generate.value = 0;
	document.itinerary.emailprovider.value = 1;
	document.itinerary.submit();
}

firstAppointmentCheck();
</script>

<?


if($generate) {
	if($startingaddress && !$firstappointmentstart) $addresses[] = $startingaddress;
  foreach($stops as $stop)
		$addresses[] = $clientDetails[$stop[0]['clientptr']]['googleaddress'];
	$itinerary = array();
  foreach($addresses as $i => $address)
    if(!$i || ($address != $addresses[$i-1]))
      $itinerary[] = $address;
	
	$itinerary = 'from: '.join(' to: ', $itinerary);
	
	//echo $itinerary.'<p>';
	//$googleMapAPIKey is from init_session.php	
?>	
<script src=" http://maps.google.com/?file=api&amp;v=2.x&amp;key=<?= $googleMapAPIKey ?>"
	type="text/javascript"></script>
<script language='javascript'>

function onLoad() {
  if (!GBrowserIsCompatible()) {
    alert("Sorry, the Google Maps API is not compatible with this browser.");
    return;
  }

  gdir = new GDirections(null, document.getElementById("directions"));
	GEvent.addListener(gdir, "load", onGDirectionsLoad);
	GEvent.addListener(gdir, "error", handleErrors);
	//alert("<?= $itinerary ?>");
	gdir.load("<?= $itinerary ?>",
		{ "locale": "en_US" });

}

function handleErrors(){
	 if (gdir.getStatus().code == G_GEO_UNKNOWN_ADDRESS)
		 alert("No corresponding geographic location could be found for one of the specified addresses. This may be due to the fact that the address is relatively new, or it may be incorrect.\nError code: " + gdir.getStatus().code);
	 else if (gdir.getStatus().code == G_GEO_SERVER_ERROR)
		 alert("A geocoding or directions request could not be successfully processed, yet the exact reason for the failure is not known.\n Error code: " + gdir.getStatus().code);

	 else if (gdir.getStatus().code == G_GEO_MISSING_QUERY)
		 alert("The HTTP q parameter was either missing or had no value. For geocoder requests, this means that an empty address was specified as input. For directions requests, this means that no query was specified in the input.\n Error code: " + gdir.getStatus().code);

//   else if (gdir.getStatus().code == G_UNAVAILABLE_ADDRESS)  <--- Doc bug... this is either not defined, or Doc is wrong
//     alert("The geocode for the given address or the route for the given directions query cannot be returned due to legal or contractual reasons.\n Error code: " + gdir.getStatus().code);

	 else if (gdir.getStatus().code == G_GEO_BAD_KEY)
		 alert("The given key is either invalid or does not match the domain for which it was given. \n Error code: " + gdir.getStatus().code);

	 else if (gdir.getStatus().code == G_GEO_BAD_REQUEST)
		 alert("A directions request could not be successfully parsed.\n Error code: " + gdir.getStatus().code);

	 else alert("An unknown error occurred.");

}

function onGDirectionsLoad(){ 
		// Use this function to access information about the latest load()
		// results.

		// e.g.
		// document.getElementById("getStatus").innerHTML = gdir.getStatus().code;
	// and yada yada yada...
}

</script>
<?
}
?>