<? // schedule-requested-visits-wizard.php
// start,end,days,price,clientptr,reqid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('c-');
extract(extractVars('start,end,days,price,clientptr,reqid,chosenprovider', $_REQUEST)); // clientptr (if supplied) is a client ID
$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientptr LIMIT 1");
$existingSchedules = existingSchedulesToOffer($clientptr, $start, $end, $marginDays=15);

$windowTitle = 'Schedule Requested Visits';
//$extraBodyStyle = 'background:white;';
require "frame-bannerless.php";

$stage = 1;

if($_POST['createOrAdd'] == 'add') { // STAGE 2
	$stage = 2;
	$sitter = $chosenprovider 
		? 'by '.fetchRow0Col0("SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider WHERE providerid = $chosenprovider")
		: '[Unassigned]';
	
	echo "<h2>Add the requested visits $sitter to...</h2>";
?>
<form name='addvisits' method='POST'>
<table>
<?
	$histories = findPackageHistories($clientptr);
	foreach($existingSchedules as $schedule) {
		$desc = scheduleDescription($schedule);
		$options["{$desc['start']} EZ Schedule {$desc['price']} ({$desc['duration']}) - {$desc['status']}"] = $schedule['packageid'];
	}
	hiddenElement('request', $reqid);
	hiddenElement('chosenprovider', $_POST['chosenprovider']);
	radioButtonRow('', 'targetschedule', $value=$defaultAction, $options, $onClick=null, $labelClass=null, $inputClass='fontSize1_3em', $rowId=null,  $rowStyle=null, $breakEveryN=1);
?>
<tr><td><img src='art/spacer.gif' height=20 width=1></td></tr>
<tr>
<td style='text-align:center'><? echoButton('next', 'Finish', 'addVisitsToSchedule()'); ?></td>
<td style='text-align:center'><? echoButton('next', 'Back', "document.location.href=\"{$_POST['firstpage']}\""); ?></td>
<td style='text-align:center'><? echoButton('', 'Quit', "document.location.href=\"request-edit.php?id={$_POST['request']}&updateList=clientrequests\""); ?></td>
</tr>
</table>
</form>
<?
}

else if($_POST['targetschedule']) { // STAGE 3
	$stage = 3;
	require_once "pet-fns.php";
	require_once "request-fns.php";
	require_once "client-sched-request-fns.php";
	$request = $_POST['request'];
	updateTable('tblclientrequest', array('resolved'=>1, 'resolution'=>'honored'), "requestid=$request", 1);
	$source = getClientRequest($request);	
	$schedule = scheduleFromNote($source['note']);
	$packageptr = $_POST['targetschedule'];
	$package = getPackage($packageptr, $R_or_N_orNull='N');


	if(strtotime($schedule['start']) < strtotime($package['startdate'])) $mods['startdate'] = dbDate($schedule['start']);
	if(strtotime($schedule['end']) > strtotime($package['enddate'])) $mods['enddate'] = dbDate($schedule['end']);
	$lines = explode("\n", $source['note']);
	$mods['packageprice'] = $schedule['totalCharge'] + $package['packageprice'];
	$mods['notes'] .= count($lines) > 2 ? urldecode($lines[2]) : '';
//echo "<pre>"."Source:\n".print_r($source, 1)."\n========\n"."Schedule:\n".print_r($schedule, 1)."\n========\n"."Package:\n".print_r($package, 1)."\n========\n"."Mods:\n".print_r($mods, 1)."\n========\n"."packageptr:\n".print_r($packageptr, 1)."\n========\n"."</pre>";exit;
	
	$day = $schedule['start'];
	$clientPets = getClientPetNames($source['clientptr']);
//echo "<pre>".print_r($schedule, 1)."</pre>";exit;
	if($schedule['services']) foreach($schedule['services'] as $i => $dayServices) { // NEVER HAPPENS!
		$clientCharges = getClientCharges($package['client']);
		$standardCharges = getStandardCharges();
		$standardRates = getStandardRates();

		if($dayServices) {
			foreach($dayServices as $newTask) {
	//echo print_r($newTask, 1).'<br>';					
				$newTask['clientptr'] = $package['clientptr'];
				$newTask['providerptr'] = $chosenprovider ? $chosenprovider : '0';
				$newTask['serviceid'] = '0';
				$newTask['packageptr'] = $packageptr;
				$newTask['charge'] = calculateServiceCharge($package['client'], $newTask['servicecode'], $newTask['pets'], $clientPets, $clientCharges, $standardCharges);
				$newTask['rate'] = calculateServiceRate($newTask['providerptr'], $newTask['servicecode'], $newTask['pets'], $clientPets, $newTask['charge'], null, $standardRates);
				$appt = createAppointment(false, null, $newTask, strtotime($day));  // NEVER HAPPENS!
				$addedAppts += 1;
				if($_SESSION['surchargesenabled']) {
					require_once "surcharge-fns.php";
					updateAppointmentAutoSurcharges($appt);  // NEVER HAPPENS!
				}
			}
		}
		$day = date('Y-m-d', strtotime('+1 day', strtotime($day)));
	}
	updateTable('tblservicepackage', withModificationFields($mods), "packageid = $packageptr", 1);
	$sitter = $chosenprovider 
		? 'by '.fetchRow0Col0("SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider WHERE providerid = $chosenprovider")
		: '[Unassigned]';
	logChange('tblservicepackage', $packageptr, 'm', "Request $request added $addedAppts visit(s) $sitter");
?>
<script language='javascript'>
window.opener.location.href="service-irregular.php?packageid=<?= $packageptr?>";
window.close();
</script>
<?
	exit;
}


// ***************************************************************************
$month = date('F', strtotime($start));
$rowHeight = 100;
?>
<style>
.previewcalendar { background:white;width:100%;border:solid black 2px;margin:5px; }

.previewcalendar td {border:solid black 1px;width:14.29%;}
.app {border:solid black 1px;background:#B7FFDB;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}
.empty {border:solid black 1px;background:lightgrey;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

.month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

.dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
.daynumber {font-size:1.5em;font-weight:bold;text-align:right;display:block;}
.scheduled {color:#666666;}
.canceled {color:red;text-decoration:line-through;}
.proposed {font-weight:bold;}
</style>
<?
$numAppointments = 0;
$noVisitDays = 0;
$scheduleDays = 0;
foreach(explode('|', $days) as $appts) {
	if($appts) $numAppointments += count(explode(',', $appts));
	else $noVisitDays++;
	$scheduleDays++;
}

$globalServiceSelections = array_flip(getClientServices());
$globalActualServiceSelections = getAllServiceNamesById();
$currencyMark = getCurrencyMark();

if($stage == 1) {
 ?>
<form name='scheduleform' method='POST'>
<h2>With the requested visits...</h2>
<table>
<?
$options = explodePairsLine('Create a New EZ Schedule|create||Add them to an existing EZ Schedule|add');
foreach($options as $k => $v) if($v == 'add') $addOption = $k;
if(!$existingSchedules) {
	unset($options[$addOption]);
	$defaultAction = 'create';
}

hiddenElement('clientptr', $clientptr);
hiddenElement('request', $reqid);
hiddenElement('start', $start);
hiddenElement('end', $end);
hiddenElement('firstpage', "schedule-requested-visits-wizard.php?{$_SERVER['QUERY_STRING']}");
echo "<tr><td><table>";
radioButtonRow('', 'createOrAdd', $value=$defaultAction, $options, $onClick='createOrAddClicked(this)', $labelClass=null, $inputClass='fontSize1_3em', $rowId=null,  $rowStyle=null, $breakEveryN=1);
echo "</table></td><td style='font-weight:bold;font-size:1.3em;padding-top:5px;padding-left:7px;'>
<table><tr><td>... and assign them to:</td>
<td colspan=2 style='text-align:left;padding-left:5px;''>";
availableProviderSelectElement($clientptr, $date=null, 'chosenprovider', $nullChoice, $choice, $onchange, $offerUnassigned=false);
echo "</td></tr></table></td></tr>";
?>
<tr><td><img src='art/spacer.gif' height=20 width=1></td></tr>
<tr><td style='text-align:center'><? echoButton('next', 'Finish', 'checkAndSubmit()'); ?></td><td style='text-align:center'><? echoButton('', 'Quit', 'quit()'); ?></td></tr>
</table>
<p>
<?
}
?>
<hr>
<table>
<tr>
<td class='h2' style='vertical-align:top'>Requested Visits</td>
<td style='vertical-align:top;padding-left:40px;' class='fontSize1_3em bold'>Client: <b><?= $clientName ?></b></td>
<td style='vertical-align:top;padding-left:40px;'>
<table><tr>
<td style='font-weight:bold;font-size:1.1em;vertical-align:top;padding-right:7px;'>Legend:</td>
<td style='vertical-align:top;'><span class='proposed'>Requested Visit</span><br><span class='scheduled'>Existing visit</span><br><span class='canceled'>Canceled Existing visit</span>
</td>
</tr>
</table>
</td>
</tr>
</table>
</form>
<table style='font-size:1.4em;width:100%;text-align:center;'>
<tr><td colspan=3>
Schedule starts on : <b><?= longDayAndDate(strtotime($start)) ?></b> and ends on <b><?= longDayAndDate(strtotime($end))."</b> ($scheduleDays days)<p>" ?>
</td></tr>
<tr><td>Visits: <?= $numAppointments ?></td><td>Days without visits: <?= $noVisitDays ?></td><td>Price: <?= $currencyMark.number_format($price, 2) ?><td></tr>
</table>


<table class='previewcalendar'>
<?
$start = date('Y-m-d',strtotime($start));
$end = date('Y-m-d',strtotime($end));
$apptdays = explode('|', $days);
$month = '';
$dayN = 0;
for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
	$dow = date('w', strtotime($day));
	if($month != date('F', strtotime($day))) {
		if($dow && $month) {  // finish prior month, if any
			for($i=$dow; $i < 7; $i++) echo "<td>&nbsp;</td>";
			echo "</tr>";
		}
		$month = date('F', strtotime($day));
		echoMonthBar($month);
		echo "<tr>";
		for($i=0; $i < $dow; $i++) echo "<td>&nbsp;</td>";
	}
	if(!$dow) echo "</tr><tr>";
	if($clientptr) echoDayBoxWithExistingVisits($day, $apptdays[$dayN]);
	else echoDayBox($day, $apptdays[$dayN]);
	$dayN++;
}
if($dow && $month) {  // finish prior month, if any
	for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
	echo "</tr>";
}
?>
</table>
<?
function echoMonthBar($month) {
	$days = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
	echo "<tr><td class='month' colspan=7>$month</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function echoDayBoxWithExistingVisits($day, $appts) {
	global $globalServiceSelections, $globalActualServiceSelections, $clientptr;
	$dom = date('j', strtotime($day));
	$appts = explode(',', $appts);
	foreach(getClientApptsForDay($clientptr, $day) as $appt) $appts[] = $appt;
	if($appts) usort($appts, 'apptStartTimeSort');
	$class = $appts && $appts[0] ? 'app' : 'empty';
	echo "<td class='$class'><div class='daynumber'>$dom</div>";
	if($class == 'empty') echo "<span style='color:red'>No visits.</span>";
	else echo "<span style='color:blue'>".count($appts)." visit".(count($appts) == 1 ? '' : 's').".</span>";
	foreach($appts as $timeAndService) {
		if(!$timeAndService) continue;
		$pair = explode('#', $timeAndService);
		if(count($pair) == 2) echo "<hr><span class='proposed'>{$pair[0]}<br>{$globalServiceSelections[$pair[1]]}</span>\n";
		else echo
			"<hr><span class={$pair[2]}>{$pair[0]}<br>{$globalActualServiceSelections[$pair[1]]}<br>{$pair[3]}</span>\n";
	}
	echo "</td>";
}

function getClientApptsForDay($clientptr, $day) {
	return fetchCol0(
		"SELECT CONCAT_WS('#', timeofday, servicecode, IF(canceled, 'canceled', 'scheduled'), IF(providerptr=0, 'Unassigned', IFNULL(nickname, CONCAT_WS(' ', fname, lname))))
			FROM tblappointment
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE clientptr = $clientptr AND date = '$day'");
}

function echoDayBox($day, $appts) {
	global $globalServiceSelections;
	$dom = date('j', strtotime($day));
	$appts = explode(',', $appts);
	if($appts) usort($appts, 'apptStartTimeSort');
	$class = $appts && $appts[0] ? 'app' : 'empty';
	echo "<td class='$class'><div class='daynumber'>$dom</div>";
	if($class == 'empty') echo "<span style='color:red'>No visits.</span>";
	else echo "<span style='color:blue'>".count($appts)." visit".(count($appts) == 1 ? '' : 's').".</span>";
	foreach($appts as $timeAndService) {
		if(!$timeAndService) continue;
		$pair = explode('#', $timeAndService);
		echo "<hr>{$pair[0]}<br>{$globalServiceSelections[$pair[1]]}\n";
	}
	echo "</td>";
}

function apptStartTimeSort($a, $b) {
	return strcmp(getApptStartTime($a), getApptStartTime($b));
}
	
function getApptStartTime($s) {
	$end = strpos($s, '-');
	return date('H:i', strtotime(substr($s, 0, $end)));
}

function existingSchedulesToOffer($clientptr, $start, $end, $marginDays=15) {
	// return details of client's EZ schedules that overlap 
	// the time frame defined by start-marginDays to end+marginDays
	$periodStart = date('Y-m-d', strtotime("- $marginDays days", strtotime($start)));
	$periodEnd = date('Y-m-d', strtotime("+ $marginDays days", strtotime($end)));
	$sql = 
			"SELECT * 
					FROM tblservicepackage
					WHERE clientptr = $clientptr 
					AND irregular = 1
					AND current = 1
					AND ((startdate >= '$periodStart' AND startdate <= '$periodEnd')
						 OR (startdate <= '$periodStart' AND enddate >= '$periodStart'))";
	return fetchAssociations($sql);
}

function scheduleDescription($schedule) {
	global $histories;
	$appts = fetchCol0("SELECT date FROM tblappointment WHERE canceled IS NULL AND packageptr IN(".join(',', $histories[$schedule['packageid']]).")");
	$duration = count(array_unique($appts));
	$appts = count($appts);
	$duration = "$duration day".($duration == 1 ? '' : 's');
	$appts = "$appts visit".($appts == 1 ? '' : 's');
	$charge = $schedule['packageprice'] ? dollarAmount($schedule['packageprice']) : '--';
	$serviceNames = 'EZ Schedule';
	$page = 'service-irregular.php';
	$title = $schedule['notes'] ? "title=\"".safeValue(truncatedLabel($schedule['notes'], 100))."\"" : '';
	$editLink = "<a href='$page?packageid={$schedule['packageid']}' $title>$serviceNames</a>";
	$start = shortDate(strtotime($schedule['startdate'])).' - '.shortDate(strtotime($schedule['enddate']));

	$status = null;
	if($schedule['cancellationdate']) {
		$status = 'Canceled effective: '.shortDate(strtotime($schedule['cancellationdate']));
	}
	else if($schedule['enddate'] < $today) $status = 'Ended '.shortDate(strtotime($schedule['enddate']));
	$status = $status ? $status : 'Active';

	return array('start'=>$start,'services'=>$serviceNames,'price'=>$charge,'duration'=>"$duration / $appts", 'status'=>$status);
}


?>
<script language='javascript'>
function quit() {
	document.location.href='request-edit.php?id=<?= $reqid ?>&updateList=clientrequests';
}

function createOrAddClicked(el) {
	document.getElementById('next').value= el.value == 'create' ? 'Finish' : 'Next';
}

function checkAndSubmit() {
	if(!(document.getElementById('createOrAdd_create').checked || document.getElementById('createOrAdd_add').checked))
		alert('You must first choose how to schedule the visits.');
	if(document.getElementById('createOrAdd_create').checked)
		scheduleButtonAction(this, "service-irregular-create.php?request="+<?= $reqid ?>)
	else document.scheduleform.submit();
}

function scheduleButtonAction(button, url) {
	//"scheduleButtonAction(this, \"service-irregular-create.php?request={$request['requestid']}\")"
	//"scheduleButtonAction(this, \"service-irregular-add.php?request={$request['requestid']}\")"
	
	button.disabled = true;
	if(document.getElementById('chosenprovider')) 
		url += '&chosenprovider='+document.getElementById('chosenprovider').value;
	window.opener.document.location.href=url;
	window.close();
}

function addVisitsToSchedule() {
	var choice=false, els = document.addvisits.elements;
	for(var i=0; i<els.length; i++)
		if(els[i].type == 'radio' && els[i].checked)
			choice = els[i].value;
	if(!choice)
		alert('You must first choose a schedule to place the visits.');
	else document.addvisits.submit();
	//alert(choice);
}

<? if($stage == 1) echo "window.resizeTo(Math.max(800, window.outerWidth), window.outerHeight);"; ?>

</script>