<? // incomplete-prov-mobile.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";
require_once "key-fns.php";

locked('p-');

extract(extractVars('date,delta,hidecompletedtoggle,showvisitcount', $_REQUEST));
// date is the first date
// numdays is the numdays to show
$numdays = 7;

if($hidecompletedtoggle) {  // AJAX
	$_SESSION['hidecompletedvisits'] = $_SESSION['hidecompletedvisits'] ? 0 : 1;
	echo $_SESSION['hidecompletedvisits'] ? "Show Completed Visits" : "Hide Completed Visits";
	exit;	
}

if($showvisitcount) {  // AJAX
	require_once "preference-fns.php";
	$_SESSION['showVisitCount'] = $_SESSION['showVisitCount'] ? 0 : 1;
	setUserPreference($_SESSION['auth_user_id'], 'showVisitCount', $_SESSION['showVisitCount']);
	echo $_SESSION['showVisitCount'] ? "Hide Visit Count" : "Show Visit Count";
	exit;	
}

$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d', strtotime("-".($numdays+1)." days"));
if($delta) $date = date('Y-m-d', strtotime("$delta days", strtotime($date)));

if($_SESSION['preferences']['providersScheduleRetrospectionLimit']) {
	$lookBack = $_SESSION['preferences']['providersScheduleRetrospectionLimit'];
	$earliestDateAllowed = strtotime("-$lookBack days", strtotime(date('Y-m-d')));
	$date = date('Y-m-d', max($earliestDateAllowed, strtotime($date)));
}




$date2 = date('Y-m-d', strtotime("+$numdays days", strtotime($date)));
//echo "[$date] +$numdays =  [$date2]";exit;
$provid = $_SESSION["providerid"];
$completedFilter = $_SESSION['hidecompletedvisits'] ? "AND completed IS NULL" : '';
$sql = "SELECT appointmentid, appt.clientptr, date, appt.timeofday, starttime, endtime, canceled, completed,
				appt.pets, canceled, pendingchange, note, CONCAT_WS(' ', fname, lname) as name, nokeyrequired
				FROM tblappointment appt 
				LEFT JOIN tblclient ON clientid = clientptr
				WHERE providerptr = $provid $completedFilter AND date >= '$date' AND date < '$date2' 
				ORDER BY date, starttime, endtime";
$appts = fetchAssociations($sql);
foreach($appts as $appt) {
//print_r($appt);exit;
	if($appt['pets'] == 'All Pets') $allPets[$appt['clientptr']] = null;
	if(!$appt['nokeyrequired']) $allKeys[$appt['clientptr']] = null;
}
if($allKeys) {
	//$_SESSION['preferences']['mobileKeyDescriptionForKeyId'] = $_SERVER['REMOTE_ADDR'] == '68.225.89.173';
	$useKeyDescriptions = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'];
	foreach(getproviderKeys($provid) as $key) {
		$providerKeyIds[$key['clientptr']] = $key['keyid'];
		$providerKeys[$key['clientptr']] = $key;
	}
	foreach((array)$allKeys as $clientptr => $empty)
		if(!$providerKeyIds[$clientptr]) $missingKeyIds[$clientptr] = null;
	if($missingKeyIds) {
		$missingKeyIds = fetchKeyValuePairs("
			SELECT clientptr, CONCAT('-', keyid) 
			FROM tblkey 
			WHERE clientptr IN (".join(',', array_keys($missingKeyIds)).")");
		if($useKeyDescriptions && $missingKeyIds) 
			$missingDescriptions = fetchKeyValuePairs("
				SELECT clientptr, description 
				FROM tblkey 
				WHERE clientptr IN (".join(',', array_keys($missingKeyIds)).")");
		foreach((array)$missingKeyIds as $clientptr => $keyid)
			$providerKeyIds[$clientptr] = $keyid;
	}
}
foreach((array)$allPets as $clientptr => $empty)
	$allPets[$clientptr] = getClientPetNames($clientptr);
foreach($appts as $i => $appt) {
	$clientptr = $appt['clientptr'];														
	$key = $providerKeyIds[$clientptr];
	$descLabel = $useKeyDescriptions	
		? ($providerKeys[$clientptr] 
			? $providerKeys[$clientptr]['description'] 
			: $missingDescriptions[$clientptr])
		: '';
	if(!$key) $key = '--';
	else if($key < 0) {
		$appts[$i]['key'] = $descLabel ? $descLabel : '#'.sprintf("%04d", 0-$key);
		$appts[$i]['nokey'] = true;
	}
	else $appts[$i]['key'] = $descLabel ? $descLabel : '#'.sprintf("%04d", $key);
}
$delayPageContent = 1;
$pageOptions = $_SESSION['hidecompletedvisits']
? "<option value='hidecompletedtoggle'>Show All Visits"
: "<option value='hidecompletedtoggle'>Hide Completed Visits";

$pageOptionsAtEnd = $_SESSION['showVisitCount']
? "<option value='showvisitcount'>Hide Visit Count"
: "<option value='showvisitcount'>Show Visit Count";
// ==================================
require_once "mobile-frame.php";
$displayDate = longerDayAndDate(strtotime($date));
if($_SESSION['showVisitCount']) $displayDate .= " (".count($appts).")";
?>
<div class='pageoptiondiv' id='contentdiv'>
<table class='lean pageoptiondiv' style='font-size:0.8em'>
<tr>
	<td><?= month3Date(strtotime($date)).' - '.month3Date(strtotime("-1 day", strtotime($date2))) ?></td>
	<? if($earliestDateAllowed == strtotime($date)) echo "<td>&nbsp;</td>"; else { ?>
	<td style='cursor:pointer;vertical-align:center;' onclick="changeDay(-<?= $numdays ?>)"><img valign='middle' src='art/prev_day.gif' height=20 > Back</td>
	<? } ?>
	<? if($date2 < date('Y-m-d', strtotime("-1 day"))) { ?>
	<td style='cursor:pointer;' onclick="changeDay('+<?= $numdays ?>')">Forward <img valign='middle' src='art/next_day.gif' height=20 ></td>
	<? } ?>
</table>
</div>
<style>
.rfloat {float:right;margin-left: 2px;}
/*.note {background: url('art/note-bg.gif') no-repeat center center;}*/
.note {color:red;}
.daterow {font-weight:bold; font-color:black;}
</style>
<div class='pagecontentdiv'>
<table class='visitlist'>
<?
if(!$appts) {
	$more = TRUE || $completedFilter ? ' Uncompleted ' : ' ';
	echo "<tr style='text-align:center;padding-top:10px;color:green;font-style:italic;'><td>No$more"."Visits This Week.<td></tr>";
}
foreach($appts as $appt)  {
	if($appt['date'] != $thisDay) {
		echo
		"<tr class='daterow'><td colspan=3>"
		.longDayAndDate(strtotime($appt['date']))
		."</td></tr>";
		$thisDay = $appt['date'];
	}
	$tod = str_replace(' ', '&nbsp;', str_replace('-', '<br>', $appt['timeofday']));
	if($appt['nokey']) {
		$keyClass = "class='nokey'";
		$keyClick = '';//"onclick=\"alert('You do not have a key for this client')\"";
	}
	else {
		$keyClass = "";
		$keyClick = '';//"onclick='alert(\"Client key number.\")'";
	}

	$pets = $appt['pets'] == 'All Pets' ? ($allPets[$appt['clientptr']] ? $allPets[$appt['clientptr']] : 'All Pets') : $appt['pets'];
	$pets = $pets ? " (<span class='petfont'>$pets</span>)" : '';
	$now = date('Y-m-d H:i:s');
//echo "$date {$appt['endtime']}  : $now ";exit;	
	$rowClass = $appt['canceled'] ? 'canceledtask' : (
							$appt['completed'] ? 'completedtask' : (
							strcmp("$date {$appt['endtime']}", $now) < 0 ? 'noncompletedtask' : ''));
	$rowClass = $rowClass ? "class='$rowClass'" : '';
	$completeButton = $appt['canceled'] || $appt['completed'] || strcmp($date, date('Y-m-d', strtotime('tomorrow'))) >= 0
				? ''
				: 	"<br><img src='art/accepted_38X16.png' width=38 height=18
											onclick='visitAction(\"complete\", {$appt['appointmentid']}, \"update\")'> ";
											//onmouseover='expandButton(this, 2)'
											//onmouseout='shrinkButton(this, 2)'
	$timeclass = $appt['note'] ? "class='note'" : '';
	
	$clientLabel = "{$appt['name']}$pets";
	if(dbTEST('agisdogs,tonkatest,dogslife')) {
		require_once "mobile-prov-fns.php";
		$clientLabel = visitListClientLabel($appt);
	}
  echo
"<tr $rowClass>
	<td><a $timeclass href='appointment-view-mobile.php?id={$appt['appointmentid']}'>$notehint$tod</a>
	<td style='padding-left:3px;'><a href='visit-sheet-mobile.php?id={$appt['clientptr']}&date=$date'>$clientLabel</a>
	<td $keyTitle	style='text-align:center;'><span $keyClass $keyClick>{$appt['key']}</span>$completeButton</tr>
<tr><td class='visitlistsepr' colspan=3>&nbsp;</tr>
";
}
?>
</table>

</div> <!-- pagecontentdiv -->

<? if(!$isMobile) { ?>
</div><!-- TESTFRAME -->
<? } ?>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="ajax_fns.js"></script>
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<? $mobileVisitActionJavascript =	loginidsOnlyTEST('shinego,koxford,joshslade,testbenball,dlifebeth,tablet3,sgnote2,apple5') ? "mobile-visit-actionV2.js" : "mobile-visit-action.js"; ?>
<script type="text/javascript" src="<?= $mobileVisitActionJavascript ?>"></script>
<script language='javascript'>
function changeDay(by) {
	document.location.href='incomplete-prov-mobile.php?date=<?= $date ?>&delta='+by;
}
function hidecompletedtoggle() {
	ajaxGetAndCallWith('home-prov-mobile.php?hidecompletedtoggle=1', 
												function() {update(0,0);},
												0);
}

function showvisitcount() {
	ajaxGetAndCallWith('home-prov-mobile.php?showvisitcount=1', 
												function() {update(0,0);},
												0);
}

function update(aspect, arg) {
	document.location.href='<?= $_SERVER["REQUEST_URI"] ?>';
}

</script>
