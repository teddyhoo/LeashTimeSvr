<? //wag.php -- week at a glance 10:20 - 12:40

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "contact-fns.php";
require_once "service-fns.php";
require_once "preference-fns.php";

require_once "prov-schedule-fns.php";
require_once "day-calendar-fns.php";
require_once "js-gui-fns.php";


// Determine access privs
$auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-');

set_time_limit(3 * 60);

$max_rows = 6000;

extract($_REQUEST);

$showCanceled = getPreference('wagShowCanceled');

if(in_array('showCanceled', array_keys($_REQUEST))) {
	$showCanceled = $_REQUEST['showCanceled'];
	setPreference('wagShowCanceled', $showCanceled);
}

$showWagReassignmentSource = isset($showreassignments) && $showreassignments;

if(!$_POST) {
	if(!$starting) $starting = date('n/j/Y');
	if(!$ending) $ending = date('n/j/Y', strtotime("+7 days"));
}
$providers = isset($providers) ? explode(',', $providers) : '';
if($providers && in_array(-2, $providers)) $providers = '';  // all providers
//function getProviderAppointmentCountAndQuery($starting, $ending, $sort=null, $provider, $offset, $max_rows, $filterANDPhrase='', $joinPhrase='', $additionalCols='') {
	// $filterANDPhrase - e.g., "AND canceled IS NULL";
$filter = $showCanceled ? '' : "AND canceled IS NULL";


pageTimeOn();

$found = getProviderAppointmentCountAndQuery(dbDate($starting), dbDate($ending), 'date_ASC', $providers, $offset, $max_rows, 
						$filter, 'LEFT JOIN tblservice ON serviceid = serviceptr LEFT JOIN tblservicepackage ON packageid = tblservice.packageptr', 'recurring, onedaypackage');
$numFound = 0+substr($found, 0, strpos($found, '|'));
$query = substr($found, strpos($found, '|')+1);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo $query; } 
$appts = $numFound ? fetchAssociations($query) : array();

screenLogPageTime("Time to fetch appointments.");

$providerNames = getProviderShortNames();

screenLogPageTime("getProviderNames.");

$nonRecPackageIds = array();
$currentNRPackageIds = array();
foreach($appts as $ind => $appt) {
	$wagProviderIDs[] = $appts[$ind]['providerptr'];
	$appts[$ind]['provider'] = $providerNames[$appts[$ind]['providerptr']];
	if(!$appt['recurring']) {
		$nonRecPackageIds[] = $appt['packageptr'];
		if(!$currentNRPackageIds[$appt['packageptr']]) {
			$currentVersion = findCurrentPackageVersion($appt['packageptr'], $appt['clientptr'], false);
			if($currentVersion) $currentNRPackageIds[$appt['packageptr']] = $currentVersion;
		}
		$appts[$ind]['currentpackageptr'] = $currentNRPackageIds[$appt['packageptr']];
	}
}

screenLogPageTime("find NR currentversions.");


if(array_key_exists('colorCodeSitters', $_REQUEST)) {
	setUserPreference($_SESSION['auth_user_id'], 'colorCodeWAGSitters', $_REQUEST['colorCodeSitters']);
}
$colorCodeSitters = getUserPreference($_SESSION['auth_user_id'], 'colorCodeWAGSitters'); //mattOnlyTEST() || staffOnlyTEST();
if($wagProviderIDs && $colorCodeSitters) {
	$providerColors = providerColors(array_unique($wagProviderIDs));
}

screenLogPageTime("choose providerColors.");

$nonRecPackageIds = join(',', $nonRecPackageIds);
$originalServiceProviders = originalServiceProviders($appts);
/*$nonRecurringRanges = !$nonRecPackageIds ? array() 
													 : fetchAssociationsKeyedBy(
														 "SELECT packageid, startdate as start, enddate as end 
														 	FROM tblservicepackage 
														 	WHERE packageid IN ($nonRecPackageIds)", 'packageid');
*/
if($currentNRPackageIds) {
	$sql = 
		 "SELECT packageid, startdate as start, enddate as end 
			FROM tblservicepackage 
			WHERE packageid IN (".join(',', $currentNRPackageIds).")";
}																
	$nonRecurringRanges = !$nonRecPackageIds ? array() 
														 : fetchAssociationsKeyedBy($sql, 'packageid');
	foreach($currentNRPackageIds as $old => $curr) {
		$nonRecurringRanges[$old] = $nonRecurringRanges[$curr];
	}
	//echo "CURR: $currentNRPackageIds<hr>ALL: $nonRecPackageIds<hr>".print_r($nonRecurringRanges, 1)."<hr>";
}

screenLogPageTime("find recurring currentversions.");


foreach($appts as $key => $appt)
	if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
		if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
			$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];


screenLogPageTime("find NR originalServiceProviders.");

$nextButton = false;
$prevButton = false;
$firstPageButton = false;
$lastPageButton = false;
if($numFound > $max_rows) {
	if($offset > 0) {
		$prevButton = true;
		$firstPageButton = true;
	}
	if($numFound - $offset > $max_rows) {
		$nextButton = true;
		$lastPageButton = true;
	}
}

$searchResults = ($numFound ? $numFound : 'No')." appointment".($numFound == 1 ? '' : 's')." found.  ";
$dataRowsDisplayed = min($numFound - $offset, $max_rows);
if($numFound > $max_rows) $searchResults .= "$dataRowsDisplayed appointments shown. ";

require_once "preference-fns.php";
$omitRevenue = getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay');


if(basename($_SERVER['SCRIPT_NAME']) == 'wag.php' && !isset($_REQUEST['providers'])) {
	$revenue = 0;
	foreach($appts as $obj) $revenue += $obj['charge'];
	if(!$omitRevenue) $searchResults .= " Total revenue: ".dollarAmount($revenue);
}
if($numFound > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('offset'));
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).">Show Next ".min($numFound - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numFound - $numFound % $max_rows).">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
}  


$pageTitle = "Week At A Glance";
if($providers) {
	foreach($providers as $pid) $chosenProviders[] = $providerNames[$pid] ? $providerNames[$pid] : "<span style='color:red;font-style:italic;'>Unassigned</span>";
	if(count($chosenProviders) > 2) $pageTitle .= " for  ".join(', ', $chosenProviders);
	else $pageTitle .= " for  ".join(' and ', $chosenProviders);
}


//include "frame.html";
// ***************************************************************************

?>
<head><title>Week-At-A-Glance</title>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" /> 
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>
</head>
<body>
<style type="text/css">
body {background:white;padding:10px;}
.chargeCell {padding-top:0px;text-align=right;vertical-align:middle;}
.tinyButton {cursor:pointer;display:inline}
</style>
<h2><?= $pageTitle ?></h2>
<form name='provschedform'>
<p style='font-weight:bold;font-size:1.2em;'>
<?   ?>
</p>
<? 
hiddenElement('providers', $_REQUEST['providers']);
hiddenElement('detailView', $_REQUEST['detailView']);
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
echo " ";
echoButton('', 'Show', 'checkAndSubmit()');
//labeledCheckbox('show canceled visits', 'showCanceled', $showCanceled, null, null, null, 'boxFirst');
$sc = $showCanceled ? '1' : '';
hiddenElement('showCanceled', $sc);
hiddenElement('colorCodeSitters', $colorCodeSitters);
$sc = $sc == 1 ? '' : '1';
$minimizeAllDaysLabel = 'Minimize All Days';
$maximizeAllDaysLabel = 'Maximize All Days';

$leanWAGView = TRUE; //$_SESSION['preferences']['enableLeanWAGView'] || (staffOnlyTEST() && TRUE); // TRUE; //!mattOnlyTEST()
$cancelAct = $sc == 1 ? 'Show' : 'Hide';


if(!$leanWAGView) {
	echo ' ';
	fauxLink("$cancelAct Canceled Visits", "document.provschedform.showCanceled.value=\"$sc\";checkAndSubmit()");
	echo "<img src='art/spacer.gif' width=40 height=1>";
	$detailSummaryButtonLabel = $detailView ? 'Summary View' : 'Detail View';
	echoButton('detailSummary', $detailSummaryButtonLabel, 'toggleSummaryDetailView()');
	echo "<img src='art/spacer.gif' width=10 height=1>";
	echoButton('minMaxDays', $minimizeAllDaysLabel, 'toggleMinMaxDays()');
	echo "<img src='art/spacer.gif' width=10 height=1>";
	echoButton('', 'Printable List', 'printableList()');
}
else if($leanWAGView) {
	hiddenElement('minMaxDays', $minimizeAllDaysLabel);
	$sitterColorLabel = $colorCodeSitters ? "No Sitter Color Codes" : "Sitter Color Codes";
	$actions = array(
		'-- Options --' => '',
		"$cancelAct Canceled Visits" => 'showHide',
		($detailView ? 'Summary View' : 'Detail View') => 'summaryDetail',
		$minimizeAllDaysLabel => 'minMax',
		$sitterColorLabel => 'toggleSitterColor',
		'Printable List' => 'printableList');
if(TRUE || staffOnlyTEST()) 	$actions['Master Schedule'] = 'masterSchedule';

	$actions['Filter Sitters'] = 'filterSitters';
//	if(staffOnlyTEST()) 
	$actions['Workload Report'] = 'workload';
	echo "<img src='art/spacer.gif' width=10 height=1>";
	selectElement('', 'wagOptions', $value=null, $actions, $onChange='wagAction(this)', $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
	echo "<br>";
}

$clientmode = $clientmode ? $clientmode : 'fullname';
$wagPrimaryNameMode = $clientmode;
$radios = radioButtonSet('clientmode', $clientmode, 
array('Client Name'=>'fullname', 
			'Last Name (Pets)'=>'name/pets',
			'Pet Names (last name)'=>'pets/name', 
			'Pet Names Only'=>'pets', 
), $onClick=null, $labelClass=null, $inputClass=null);
foreach($radios as $radio) echo $radio;

/*if(staffOnlyTEST() || dbTEST('peakcitypuppy,tonkapetsitters,carolinapetcare')) {
if(!$leanWAGView) {
	echo "<img src='art/spacer.gif' width=10 height=1>";
	echoButton('', 'Filter Sitters', 'filterSitters()');
}
}*/

?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "</tr></table>";
?>

<p>
<?
//OBSELETE, see prov-schedule-fns.php: $timesOfDayRaw = getPreference('appointmentCalendarColumns');
//OBSELETE: if(!$timesOfDayRaw) $timesOfDayRaw = 'Morning,07:00:00,Midday,11:00:00,Afternoon,15:00:00,Evening,19:00:00';
//OBSELETE: $timesOfDayRaw = explode(',',$timesOfDayRaw);
//OBSELETE: for($i=0;$i < count($timesOfDayRaw)-1; $i+=2) $timesOfDay[$timesOfDayRaw[$i+1]] = $timesOfDayRaw[$i];

$toProviders = $providers ? $providers : fetchCol0("SELECT providerid FROM tblprovider WHERE active = 1");
foreach($toProviders as $provid) {
	$timeOffRows = getProviderTimeOffInRange($provid, array(dbDate($starting), dbDate($ending)));
	foreach($timeOffRows as $to) {
		$to['timeoff'] = 'TIME OFF';
		$to['provider'] = $providerNames[$to['providerptr']];
		$appts[] = $to;
	}
//echo "TO: ".print_r($timeOffRows, 1);
}

// ###################################
if($detailView) providerCalendarTable($appts, null, 'dumpBriefWAGAppointment', true, $omitRevenue);
else providerCalendarTable($appts, null, 'dumpOneLineWAGAppointment', true, true, $omitRevenue);
// ###################################


if($screenLog) echo "<div style='background:lightgrey;'>$screenLog</div>";


if($dataRowsDisplayed < 5) { ?>
<div style='height:100px;'></div>
<?
}
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('provider','Provider','starting','Starting Date','ending', 'Ending Date');	

function filterSitters() {
	$.fn.colorbox({href:"provider-chooser.php?providerids="+document.getElementById('providers').value, iframe: "true", width:"750", height:"470", scrolling: true, opacity: "0.3"});
}

function workLoadReport() {
  if(!MM_validateForm(
		  'starting', '', 'isDate',
		  'starting', '', 'R',
		  'ending', '', 'isDate',
		  'ending', '', 'R')) return;
	var opener = window.opener;
	if(!opener) alert('Cannot find the main window!');
	else {
		opener.location.href='reports-workload.php?starting='
													+document.getElementById('starting').value
													+'&ending='+document.getElementById('ending').value;
		alert("Please see the Workload Report in the main LeashTime window.");
	}
}

	
function wagAction(optionsEl)	 {
	if(optionsEl.selectedIndex == 0) return;
	var choice = optionsEl.options[optionsEl.selectedIndex].value;
	//alert(choice);
	optionsEl.selectedIndex = 0;
	if(choice == 'showHide') {document.provschedform.showCanceled.value="<?= $sc ?>"; return checkAndSubmit();}
	if(choice == 'summaryDetail') return toggleSummaryDetailView();
	if(choice == 'minMax') return toggleMinMaxDays();
	if(choice == 'printableList') return printableList();
	if(choice == 'masterSchedule') return masterSchedule();
	if(choice == 'filterSitters') return filterSitters();
	if(choice == 'toggleSitterColor') return toggleSitterColor();
	if(choice == 'workload') return workLoadReport();
}

function findPageOptionElement(optName) {
	var el = document.getElementById('wagOptions');
	if(!el) return;
	for(var i=0; i < el.options.length; i++)
		if(el.options[i].value == optName)
			return el.options[i];
}
	


function masterSchedule() {
  if(MM_validateForm(
		  'starting', '', 'isDate',
		  'starting', '', 'R',
		  'ending', '', 'isDate',
		  'ending', '', 'R')) {
		var starting = document.provschedform.starting.value;
		var ending = document.provschedform.ending.value;
		if(starting) starting = 'starting='+starting;
		if(ending) ending = '&ending='+ending;
		var url = 'https://<?= $_SERVER["HTTP_HOST"] ?>/master-schedule.php?'+starting+ending;
		openConsoleWindow('multidayvisitlist', url,700,800)
	}
}

function printableList() {
  if(MM_validateForm(
		  'starting', '', 'isDate',
		  'starting', '', 'R',
		  'ending', '', 'isDate',
		  'ending', '', 'R')) {
		var starting = document.provschedform.starting.value;
		var ending = document.provschedform.ending.value;
		if(starting) starting = 'starting='+starting;
		if(ending) ending = '&ending='+ending;
		var url = 'https://<?= $_SERVER["HTTP_HOST"] ?>/reports-multi-day-visits.php?sortbytime=1&'+starting+ending;
		openConsoleWindow('multidayvisitlist', url,700,800)
	}
}

function checkAndSubmit() {
  if(MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var starting = document.provschedform.starting.value;
		var ending = document.provschedform.ending.value;
		var providers = document.provschedform.providers.value;
		var detailView = document.provschedform.detailView.value;
		var showCanceled = document.provschedform.showCanceled.value;
		var colorCodeSitters = document.provschedform.colorCodeSitters.value;
		
		var clientmodes = document.getElementsByName('clientmode');
		var clientmode = '';
		for(var i=0;i<clientmodes.length;i++)
			if(clientmodes[i].checked)
				clientmode = clientmodes[i].value;
		if(starting) starting = 'starting='+starting;
		if(ending) ending = '&ending='+ending;
		showCanceled = showCanceled ? '&showCanceled=1' : '&showCanceled=0';
		colorCodeSitters = colorCodeSitters == 1 ? '&colorCodeSitters=1' : '&colorCodeSitters=0';
		detailView = detailView == 1 ? '&detailView=1': '';
		clientmode = '&clientmode='+clientmode;
		if(providers) providers = '&providers='+providers;
    document.location.href='wag.php?'+starting+ending+providers+detailView+showCanceled+clientmode+colorCodeSitters;
	}
}

function printVisitSheets() {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.starting.value;
	var ending = document.provschedform.ending.value;
	var provider = document.provschedform.provider.value;
	var message;
	if(!starting) message = "No starting date has been supplied.\nPrint today's Visit Sheets?";
	else if(ending != starting) message = "Print Visit Sheets for "+starting+"?";
	if(message && !confirm(message)) return;
	openConsoleWindow('visitsheets', 'visit-sheets.php?provider='+provider+'&date='+starting,750,700);
}

function setUpRoute() {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.starting.value;
	var ending = document.provschedform.ending.value;
	var provider = document.provschedform.provider.value;
	var message;
	if(!starting) message = "No starting date has been supplied.\nSet up today's Visit Route?";
	else if(ending != starting) message = "Set up Visit Route for "+starting+"?";
	if(message && !confirm(message)) return;
	openConsoleWindow('visitsheets', 'itinerary.php?provider='+provider+'&date='+starting,750,700);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

function reassignJobs() {
	var provider = document.provschedform.provider.value;
	var starting = document.provschedform.starting.value;
	document.location.href='job-reassignment.php?fromprov='+provider+'&date='+starting;
}

function openComposer(provider) {
	openConsoleWindow('emailcomposer', 'comm-composer.php?provider='+provider,500,500);
}

function apptEd(id) {
	<? 
		$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
		$url = $roDispatcher ? "appointment-view.php?id=" : "appointment-edit.php?updateList=&id=";
	?>
	openConsoleWindow("editappt", "<?= $url ?>"+id,<?= $_SESSION['dims']['appointment-edit'] ?>);
}

function quickEdit(id) { // for the Detail View, where the visit links call quickEdit
	apptEd(id);
}

function toggleSubsection(prefix) {
	var els = document.getElementsByTagName('tr');
	for(var i = 0; i < els.length; i++) 
		if(els[i].id && els[i].id.indexOf(prefix) == 0) {
			els[i].style.display = els[i].style.display == 'none' ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
			var n = els[i].id.split('_');
			n = n[1];
			document.getElementById('prov-shrink-'+n).src = (els[i].style.display == 'none' ? 'art/down-black.gif' : 'art/up-black.gif');
			//'prov-shrink-
		}
}			

function toggleDate(rowId) {
	var el = document.getElementById(rowId+'_headers');
	el.style.display = el.style.display == 'none' ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
	var el = document.getElementById(rowId+'_row');
	el.style.display = el.style.display == 'none' ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
	var n = rowId.split('_');
	n = n[1];
	document.getElementById('day-shrink-'+n).src = (el.style.display == 'none' ? 'art/down-black.gif' : 'art/up-black.gif');
}

function toggleSummaryDetailView() {
	var newState = document.getElementById('detailView').value ? 0 : 1;
	document.getElementById('detailView').value = newState;
	checkAndSubmit();
}

function OLDtoggleSummaryDetailView() {
	toggleMinMaxDays(1);;
	var newState = document.getElementById('detailSummary').value.indexOf('etail') > -1 ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
	var els = document.getElementsByTagName('tr');
	for(var i = 0; i < els.length; i++) 
		if(els[i].id && els[i].id.indexOf('provider_') == 0)
			els[i].style.display = newState;
	if(newState == 'none') document.getElementById('detailSummary').value = '<?= $detailViewLabel?>';
	else document.getElementById('detailSummary').value = '<?= $summaryViewLabel?>';
}

function toggleMinMaxDays(max) {
	var newState = max || document.getElementById('minMaxDays').value.indexOf('ax') > -1 ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
	var els = document.getElementsByTagName('tr');
	for(var i = 0; i < els.length; i++) 
		if(els[i].id && els[i].id.indexOf('dateappointments_') == 0)
			els[i].style.display = newState;
	var leanoption = findPageOptionElement('minMax');
	if(leanoption)  // "lean" display option
		leanoption.innerHTML = newState == 'none' ? '<?= $maximizeAllDaysLabel?>' : '<?= $minimizeAllDaysLabel?>';
	if(newState == 'none') document.getElementById('minMaxDays').value = '<?= $maximizeAllDaysLabel?>';
	else document.getElementById('minMaxDays').value = '<?= $minimizeAllDaysLabel?>';
}

function toggleSitterColor() {
	var val = document.getElementById('colorCodeSitters').value;
//alert(val);		
	document.getElementById('colorCodeSitters').value = val ==  '1' ? '0' : '1';
	checkAndSubmit();
}

<?
dumpPopCalendarJS();
?>

function update(target, val) { // called by appointment-edit
	if(target == 'providers') {
		document.getElementById('providers').value = val;
		checkAndSubmit();
		return;
	}
	refresh(); // implemented below
	if(window.opener.update) window.opener.update('appointments');
}

function showNote(elid) {
	var el = document.getElementById(elid);
	el.style.display = el.style.display == 'none' ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
}

function cancelAppt(appt, cancelFlg) {
	ajaxGetAndCallWith("appointment-cancel.php?cancel="+cancelFlg+"&id="+appt, update, 0);
}


</script>

<?
include "js-refresh.php";

// ***************************************************************************

//include "frame-end.html";
?>

