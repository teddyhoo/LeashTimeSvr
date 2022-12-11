<? //itinerary2018.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "appointment-fns.php";
require_once "key-fns.php";

//print_r($_POST);echo "<p>{$_SERVER['SCRIPT_NAME']}";exit;	
/*
Called from:
1. Itinerary button: ($noControls == false, $emailprovider == false, $provider, etc)
2. This page, "Send to Sitter" button: ($noControls == false, $emailprovider == true, $provider, etc)
3. This page (file_get_contents): 
		($noControls == true, $emailprovider == false, no $_SESSION)
		$db, $dbhost, $dbuser, $dbpass, $token

*/
$thisFileName = substr($_SERVER["SCRIPT_NAME"], 1); // strip off "/"


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

if($emailprovider && !$parms) { // Case #2
	$person = getProvider($provider);
	// confirm provider email address
	if(!$person['email']) $error = 'This sitter has no email address.';  // SHOULD NOT HAPPEN; BUTTON NOT OFFERED WHEN EMAIL ABSENT
	else {
		$this_dir = substr($_SERVER['SCRIPT_NAME'],0,strrpos($_SERVER['SCRIPT_NAME'],"/"));
		list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
		include "common/init_db_common.php";
		include "response-token-fns.php";
		$token = generateSecurityToken($_SESSION['bizptr'], 'itinerary_token');
		list($db, $dbhost, $dbuser, $dbpass) = array($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
		include "common/init_db_petbiz.php";
		$startingaddress = urlencode($startingaddress);
		$date = date('Y-m-d', strtotime($date));
		$parms = array('noControls=1',"reordering=$reordering","generate=$generate","provider=$provider","date=$date",
										"change=$change","startingaddress=$startingaddress","firstappointmentstart=$firstappointmentstart",
										"token=$token","db=$db","dbhost=$dbhost","dbuser=$dbuser","dbpass=$dbpass");
		//$parms = join('&', $parms);
		
//echo "$mein_host$this_dir/itinerary.php?$parms";exit;
		//$itinerary = file_get_contents("$mein_host$this_dir/itinerary.php?$parms");
		
		ob_start();
		ob_implicit_flush(0);
		include "$thisFileName";
		$itinerary = ob_get_contents();
		ob_end_clean();

}

		
		
		$parms = array("reordering=$reordering","generate=$generate","date=".dbDate($date),"pop=visitsheets",
										"change=$change","startingaddress=$startingaddress","firstappointmentstart=$firstappointmentstart");
		$link = urlencode("$thisFileName?".join('&', $parms));
		$href = globalURL("index.php?pop=$link");
		$link = "<a href='$href'>Your Itinerary</a>";
		$body = "Dear ".providerShortName($person).',<p>Here is your visit itinerary.<p>'.
						"To view this in a web browser and see turn-by-turn directions, click here: $link<p>".$itinerary;
		include "comm-fns.php";
		//enqueueEmailNotification
		if(!($error = notifyByEmail($person, "Your Itinerary", $body, null, null, 'html'))) {
			echo "<script language='javascript'>window.close();</script>";
			exit;
		}
		else {
			$noControls = 0;
		}
	}
}
else if($parms) {
	$noControls = 1;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($parms);}
	//extract($parms);
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

if(userRole() == 'p' && $_SESSION['preferences']['providersScheduleRetrospectionLimit']) {
	$earliestDateAllowed = strtotime("-{$_SESSION['preferences']['providersScheduleRetrospectionLimit']} days", strtotime(date('Y-m-d')));
	$tooEarly = strtotime($date) < $earliestDateAllowed;
	if($tooEarly) {
		echo "<h2>Visits before ".shortNaturalDate($earliestDateAllowed)." are not viewable.<h2>";
  	exit;
	}
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
<body style='padding:20px;'>
<table class='heading' width=100%><tr>
<td>Sitter: <?= $providerNames[$provider]."'s Itinerary" ?></td>
<td align=right><?= date('F j, Y', strtotime($date)) ?></td>
</table>
<p>
<?

if($error) echo "<span class='warning'>$error</span><p>";

$stops = array();
foreach($providerAppts as $appt) {
	$stopKey = $appt['appointmentid'].'|'.md5($appt['timeofday']); /// WHY md5?
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
		$reorderedStops[$stopKeys[$i]] = $stops[$stopKeys[$i]];
	}
	foreach($stopKeys as $stopKey) // if stops have been added since the reordering, plug add them to the list
	  if(!isset($reorderedStops[$stopKey])) {
	  	$reorderedStops[$stopKey] = $stops[$stopKey];
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

	require_once "itinerary-fns.php";

	providerItinerary($stops, $clientDetails, $warning);

?>
<div id="directions"></div>

<?

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
<? if(!mattOnlyTEST()) { ?>	
	//alert("Temporarily unavailable.");
	//return;
<? } ?>	
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

// GENERATE
if($generate) {
	$googleMapAPIKey;  //from init_session.php
	if($startingaddress && !$firstappointmentstart) $addresses[] = $startingaddress;
  foreach($stops as $stopKey => $stop) {
		if($_POST["stop_$stopKey"]) 
			$addresses[] = $clientDetails[$stop[0]['clientptr']]['googleaddress'];
	}

	if(count($addresses) < 2) echo "Error: there must be at least two addresses.";
	else {
		$end = array_pop($addresses);
		$start = $addresses[0];
		for($i = 1; $i < count($addresses); $i++)
			$waypoints[] = array('location'=>$addresses[$i], 'stopover'=>true);			
		$waypoints = json_encode($waypoints);
		echo <<<DIRECTIONS
			<div id="directions"></div>

			<script>
				function initDirections() {
					var directionsDisplay = new google.maps.DirectionsRenderer;
					var directionsService = new google.maps.DirectionsService;

					directionsDisplay.setPanel(document.getElementById('directions'));

					calculateAndDisplayRoute(directionsService, directionsDisplay);
				}

				function calculateAndDisplayRoute(directionsService, directionsDisplay) {
					directionsService.route({
						origin: "$start",
						destination: "$end",
						travelMode: 'DRIVING',
						waypoints: $waypoints
					}, function(response, status) {
						if (status === 'OK') {
							directionsDisplay.setDirections(response);
						} else {
							window.alert('Directions request failed due to ' + status);
						}
					});
				}
			</script>
			<script async defer
			src="https://maps.googleapis.com/maps/api/js?key=$googleMapAPIKey&callback=initDirections">
			</script>
DIRECTIONS;
	}
}// END GENERATE

?>