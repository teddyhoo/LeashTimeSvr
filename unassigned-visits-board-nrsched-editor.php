<?
// unassigned-visits-board-nrsched-editor.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "time-framer-mouse.php";
require_once "preference-fns.php";
require_once "service-fns.php";
require_once "unassigned-visits-board-fns.php";

locked('o-');


require "frame-bannerless.php";
echo "<div class='fontSize1_0em'>";
$id = $_REQUEST['id'];
$package = getCurrentNRPackage($id);
$uvb = fetchUVBEntryForNRPackage($package);
//echo print_r($_REQUEST, 1)."<hr>uvb: ".print_r($uvb, 1).'<hr>';
if($_REQUEST['delete']) {
	deleteTable('tblunassignedboard', "uvbid = {$uvb['uvbid']}", 1);
	$message = "Deleted this Unassigned Message Board Advertisement.";
}
if($_POST) {
	if($_REQUEST['delete']) ; //no-op
	else if($uvb) {
		$uvb['packageptr'] = $package['packageid'];
		$uvb['clientptr'] = $package['clientptr'];
		$uvb['uvbnote'] = $_POST['description'];
		$packdesc = packageDescriptionAsVisit($package);
		$packdesc['note'] = $_POST['description'];
		$uvb['uvbdate'] = $packdesc['date'];
		$uvb['uvbnote'] = $_POST['description']; // WHY WON'T THIS SAVE???
		$uvb['uvbtod'] = $packdesc['timeofday'];
		$uvb['modified'] = date('Y-m-d H:i:s');
		$uvb['modifiedby'] = $_SESSION['auth_user_id'];
		updateTable('tblunassignedboard', $uvb, 1);
		$message = "Updated this Unassigned Message Board Advertisement.";
	}
	else {
		$packdesc = packageDescriptionAsVisit($package);
		$packdesc['note'] = $_POST['description'];
		$uvb = 
			array(
				'packageptr'=>$package['packageid'],
				'uvbnote'=>$_POST['description'], 
				'uvbdate'=>$packdesc['date'],   
				'uvbtod'=>$packdesc['timeofday'],   
				'created'=>date('Y-m-d H:i:s'),
				'createdby'=>$_SESSION['auth_user_id']);
		$uvbid = insertTable('tblunassignedboard', $uvb, 1);
		$message = "Added this Unassigned Message Board Advertisement.";
	}
	
	echo "<span class'fonSize1_2em'>$message";
	if(!$_REQUEST['delete']) dumpListing($packdesc, $uvb['uvbnote']);
	echo "</span>";
	echoButton('', 'Done', 'parent.$.fn.colorbox.close();'); 

	exit;
} // $_POST
?>
<h2>Unassigned Visit Board Listing for a Schedule</h2>

<p>
<form method='POST' name='mainform'>
<?  
$packdesc = packageDescriptionAsVisit($id);

$apptcount = $packdesc['unassignedvisitcount'];

echo "<table width=97%><tr><td>";
if($apptcount) echoButton('', ($uvb ? 'Update Listing' : 'Add Listing'), 'saveListing()'); 
echo "<img src='art/spacer.gif' width=20 height=1>";
echoButton('', 'Quit', 'parent.$.fn.colorbox.close();'); 
echo "</td>";
if($uvb) {
	echo "<td align=right>";
echoButton('', 'Delete Listing', 'saveListing("delete")', 'HotButton', 'HotButtonDown'); 
	echo "</td>";
}
echo "</tr></table>";
hiddenElement('id', $id);

if($uvb) {
	$status = 'listed';
	$statuscolor = 'blue';
}
else {
	$status = ' not listed';
	$statuscolor = 'red';
}
	
echo "<p><span class='fontSize1_1em'>This package is currently <span style='color:$statuscolor;font-weight:bold''>$status</span> on the Unassigned Visits Board</span><p>";
echo "<p><span class='fontSize1_1em'>This package currently has ".($apptcount ? $apptcount : '<font color=red>no</font>')." future unassigned visit".($apptcount == 1 ? '' : 's').".</span>";
if($apptcount) {
	dumpListing($packdesc);
?>
<table width=300>
<tr><td><b>Description:<b></td/tr>
</table>
<textarea class='fontSize1_3em' name='description' id='description' cols=80 rows=10><?= $uvb ? $uvb['uvbnote'] : $package['notes'] ?></textarea>
<?
} 

?>
</form>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function saveListing(deleteOpt) {
	if(deleteOpt) {
		if(confirm('Drop the listing for this schedule?')) 
			document.mainform.action = document.mainform.action+"&delete=1";
		else return;
	}
	document.mainform.submit();
}
</script>

<?

function packageDescriptionAsVisit($packageptr) {
	/* return an EZ Schedule description that will fit in like a visit
			e.g.  15 visits ending 10/21/2012 (Dog Walk 15, Play Time, Pet Taxi) Client: John Doe Pets: Spunky, Chewie
	*/
	require_once "service-fns.php";
	require_once "pet-fns.php";
	$package = is_array($packageptr) ? $packageptr : getCurrentNRPackage($packageptr);
	$details = explode(',', $_SESSION['preferences']['unassignedboarddetails']);
	$appts = fetchAllAppointmentsForNRPackage($package, $clientptr=null);
	$today = date('Y-m-d');
	foreach($appts as $i => $appt) {
		if($appt['canceled'] || strcmp($appt['date'], $today) < 0 || $appt['providerptr']) unset($appts[$i]);
		else {
			$tods[] = $appt['timeofday'];
			$startDate = $startDate ? $startDate : $appt['date'];
			$endDate = $appt['date'];
		}
	}
//echo "{$today}[[$startDate]]<p>";	
	$tods = $tods ? array_unique($tods) : array();
	$services = array();
	$pets = array();
	if($appts) {
		$servTypes = $_SESSION['servicenames'];
		foreach($appts as $appt) $services[$servTypes[$appt['servicecode']]] = null;
		$services = " (".join(', ', array_keys($services)).")";
	}
	$client = 
		fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$package['clientptr']} LIMIT 1");
	foreach(getActiveClientPets($package['clientptr']) as $pet) {
		//$pettype = $pet['type'] ? " ({$pet['type']})" : '';"{$pet['name']}$pettype"
		$clientPets[$pet['name']] = $pet['type'];
	}
	foreach($appts as $appt) {
		foreach(explode(',', (string)$appt['pets']) as $pet) {
			if($pet == 'All Pets') $showAllPets = 1;
			$pets[$pet] = 1;
		}
	}
	if($showAllPets) $pets = $clientPets;
	if($pets) 
		foreach(array_keys($pets) as $name) {
			$label = null;
			$label[] = $name;
			//$label[] = $clientPets[$name] ? "({$clientPets[$name]})" : '';
			$petLabels[] = $label ? join(' ', $label) : '(unknown)';
		}
	else $petLabels[] = '(unknown)';
	$petLabels = ' '.join(', ', $petLabels);
	
	return array(
		'client'=>$client,
		'visitnote'=>count($appts)." unassigned visits starting ".shortNaturalDate(strtotime($startDate),1)." and ending ".shortNaturalDate(strtotime($endDate),1)
							/*.($package['notes'] ? "\n".$package['notes'] : '')*/,
		'date'=>$startDate,
		'timeofday'=>(count($tods) == 1 ? $tods[0] : 'various'), 
		'service'=>$services,
		'pets'=>$petLabels,
		'locale'=>$locale,
		'unassignedvisitcount'=>count($appts));
}

function dumpListing($packdesc, $includeDescription=false) {
	$details = explode(',', fetchPreference('unassignedboarddetails'));
	$details[] = 'date';
	$alldetails = explodePairsLine(
		"date|Date||timeofday|Time of Day||client|Client Name||street1|Street||city|City||locale|Neighborhood||service|Service Type"
		.'||pets|Pets||pettypes|Pet Type(s)||visitnote|Visit Notes');
	foreach($details as $detail) if(strpos($detail, 'c_') === 0) $chosenLocaleField = $detail;
	if(!$chosenLocaleField) unset($alldetails['locale']);
	else {
		$chosenLocaleField = substr($chosenLocaleField, strlen('c_'));
		$localeLabel = fetchPreference($chosenLocaleField);
		if(!$localeLabel) unset($alldetails['locale']);
		else {
			$alldetails['locale'] = substr($localeLabel, 0, strpos($localeLabel, '|'));
		}
	}
	foreach($alldetails as $detail => $label) {
		if($detail == 'pettypes' && !$columns['pets']) $columns['pets'] = $alldetails['pets'];
		else if(in_array($detail, $details) || $detail == 'locale') $columns[$detail] = $label;
		if($detail == 'client') $showClientNames = 1;
	}

	$row = $packdesc;
	$row['date'] = $row['date'] ? longDayAndDate(strtotime($row['date'])) : 'No future visits to advertise.';
	if(is_array($row['service'])) $row['service'] = join(', ', $row['service']);
	echo "<table class='fontSize1_2em'><tr><td style='font-weight:bold;'>Listing</td></tr>";
	foreach($columns as $col => $label) {
		//labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false) 
		labelRow($label.':', '', $row[$col]);
	}
	if($includeDescription) 
		labelRow('Description:', '', 
								str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r", "", $includeDescription))));
	echo "</table>";
}