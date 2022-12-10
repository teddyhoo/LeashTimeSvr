<? // schedule-plan-edit.php
/* This editor will open in a lightbox.
id = (optional) scheduleplan id
client (optional) client id
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "client-fns.php";
require_once "pet-fns.php";
require_once "provider-fns.php";
require_once "schedule-plan-fns.php";
require_once "service-fns.php";

include "weekday-grid.php";
include "petpick-grid.php";
include "time-framer-mouse.php";

// Determine access privs
$locked = locked('o-');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract($_REQUEST);

if($id) $plan = fetchFirstAssoc("SELECT * FROM tblscheduleplan WHERE planid = $id LIMIT 1", 1);
if($client) $clientDetails = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient WHERE clientid = $client LIMIT 1", 1);






if($errors) {
	echo "<font color='red'>WARNING:<ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
}

$allPetNames = $client ? getClientPetNames($client) : '';

$serviceTypes = getStandardRates();
$serviceSelections = array_merge(array(''=>0), getServiceSelections());
//$activeProviderSelections = getActiveProviderSelections(null, $clientDetails['zip']);
$activeProviderSelections = availableProviderSelectElementOptions($clientDetails, null, 'Primary Sitter');
makePetPicker('petpickerbox',getActiveClientPets($client), $petpickerOptionPrefix);
makeWeekdayGrid('weekdays');
makeTimeFramer('timeFramer');
if($id) { // existing service package
	$services = getPlanServices($packageid);
	//echo '['.print_r($package,1).']';exit;
}
else { // new service
  // if $client is set, do not allow $client to be modified
	$services = array();
}

?>
<head>
  <link rel="stylesheet" href="style.css" type="text/css" /> 
  <link rel="stylesheet" href="pet.css" type="text/css" /> 
</head>

<form name='scheduleplanform' method=POST>
<? 
hiddenElement('client', $client); 
hiddenElement('planid', $packageid);
labeledInput('Plan Name:', 'name', $plan['name']);
echo "<p>";
if($id) hiddenElement('client', $plan['client']);
else {
	echo "This plan is for: ";
	$radios = radioButtonSet('client', $client, array($clientDetails['clientname']=>$client, 'All Clients'=>0), $onClick='clientChanged(this)', $labelClass=null, $inputClass=null);
	foreach($radios as $radio) echo "$radio ";
}
echo "<p>";
selectElement('Customize a standard plan:', 'baseplan', $value=null, getGlobalSchedulePlanSelectOptions(true, '--Choose a plan--'), 'basePlanChanged(this)');
echo "<p>";
$activeProviderSelections = availableProviderSelectElementOptions($clientDetails, null, 'Primary Sitter');
echo "<div id='primaryProviderDiv'>";
selectElement('Primary Sitter', 'primaryProvider', $clientDetails['defaultproviderptr'], $activeProviderSelections, $onChange="setPrimaryProvider(this, true)");
echo "</div>";

//schedulePlanServiceTabs(&$services, &$activeProviders, &$serviceSelections);
?>
<script language='javascript'>
function clientChanged(el) {
	document.getElementById('primaryProvider').parentNode.style.display= (el.value > 0 ? 'block' : 'none');
	document.getElementById('primaryProvider').selectedIndex=0;
}

function setPrimaryProvider(primarysel) {
	var provider = primarysel.options[primarysel.selectedIndex].value;
	var rows = document.getElementsByTagName('tr');
	for(var i=0; i < rows.length; i++) {
		var rowid = rows[i].id;
		if(rowid && rowid.indexOf('_service_row_') > -1) {
			var tab = rowid.substring(0, rowid.indexOf('_')+1);  // e.g., first
			var rownum = rowid.substring(rowid.lastIndexOf('_')); // e.g., _2
			if(true || !document.getElementById(tab+'servicecode'+rownum).selectedIndex) {
				var pselect = document.getElementById(tab+'providerptr'+rownum);
				if(!pselect) alert("setPrimaryProvider: "+tab+'providerptr'+rownum+" not found.");
				for(var o=0; o < pselect.options.length; o++)
					pselect.options[o].selected = pselect.options[o].value == provider;
			}
		}
	}
	//updateAllServiceVals();
}	



</script>