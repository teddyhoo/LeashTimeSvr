<? // home-prov-mobile.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";
require_once "key-fns.php";

locked('p-');

extract(extractVars('date,delta,hidecompletedtoggle,showvisitcount,showarrivalandcompletiontimestoggle', $_REQUEST));

$calendarTest = true; //in_array($_SESSION['auth_login_id'], array('dlifebeth', 'Xjessica', 'mmtestsit'));
$showArrivalAndCompletionTimes = getUserPreference($_SESSION['auth_user_id'], 'showMobileArrivalAndCompletionTimes');
$arrivalCompletionTimesMenuLabel = "Arr/Compl Times";

if($hidecompletedtoggle) {  // AJAX
	$_SESSION['hidecompletedvisits'] = $_SESSION['hidecompletedvisits'] ? 0 : 1;
	echo $_SESSION['hidecompletedvisits'] ? "Show Completed Visits" : "Hide Completed Visits";
	exit;	
}

if($showarrivalandcompletiontimestoggle) {  // AJAX
	$showArrivalAndCompletionTimes = !$showArrivalAndCompletionTimes;
	setUserPreference($_SESSION['auth_user_id'], 'showMobileArrivalAndCompletionTimes', 
													($showArrivalAndCompletionTimes ? $showArrivalAndCompletionTimes : 0));
	echo $showArrivalAndCompletionTimes ? "Hide $arrivalCompletionTimesMenuLabel" : "Show $arrivalCompletionTimesMenuLabel";
	exit;	
}

if($showvisitcount) {  // AJAX
	require_once "preference-fns.php";
	$_SESSION['showVisitCount'] = $_SESSION['showVisitCount'] ? 0 : 1;
	setUserPreference($_SESSION['auth_user_id'], 'showVisitCount', $_SESSION['showVisitCount']);
	echo $_SESSION['showVisitCount'] ? "Hide Visit Count" : "Show Visit Count";
	exit;	
}

$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
if($delta) $date = date('Y-m-d', strtotime("$delta days", strtotime($date)));
$provid = $_SESSION["providerid"];
$completedFilter = $_SESSION['hidecompletedvisits'] ? "AND completed IS NULL" : '';
$sql = "SELECT appointmentid, appt.clientptr, appt.timeofday, starttime, endtime, canceled, completed,
				appt.pets, canceled, pendingchange, note, CONCAT_WS(' ', fname, lname) as name, nokeyrequired, servicecode,
				packageptr, recurringpackage, highpriority
				FROM tblappointment appt 
				LEFT JOIN tblclient ON clientid = clientptr
				WHERE providerptr = $provid $completedFilter AND date = '$date' 
				ORDER BY date, starttime, endtime, lname, fname";
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
		$allClientIds = array_keys($missingKeyIds);
		$missingKeyIds = fetchKeyValuePairs("
			SELECT clientptr, CONCAT('-', keyid) 
			FROM tblkey 
			WHERE clientptr IN (".join(',', $allClientIds).")");
		// restore client ids where client has no key at all
		// clients w/o keys are weeded out...
		if($missingKeyIds && $useKeyDescriptions) 
			$missingDescriptions = fetchKeyValuePairs("
				SELECT clientptr, description 
				FROM tblkey 
				WHERE clientptr IN (".join(',', array_keys($missingKeyIds)).")");
		if(TRUE || mattOnlyTEST()) foreach($allClientIds as $cid) if(!$missingKeyIds[$cid]) {
			$missingKeyIds[$cid] = -1;
			if(!$missingDescriptions[$cid]) $missingDescriptions[$cid] = 'need key';
		}
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
		$appts[$i]['key'] = $descLabel ? $descLabel : 'need key'; //'#'.sprintf("%04d", 0-$key);
		$appts[$i]['nokey'] = true;
	}
	else $appts[$i]['key'] = $descLabel ? $descLabel : '#'.sprintf("%04d", $key);
	if($appt['canceled']) $canceledCount++;

}
$delayPageContent = 1;
$pageOptions = $_SESSION['hidecompletedvisits']
? "<option value='hidecompletedtoggle'>Show All Visits"
: "<option value='hidecompletedtoggle'>Hide Completed Visits";


require_once "preference-fns.php";
$pageOptions .= $showArrivalAndCompletionTimes
	? "<option value='showarrivalandcompletiontimestoggle'>Hide $arrivalCompletionTimesMenuLabel"
	: "<option value='showarrivalandcompletiontimestoggle'>Show $arrivalCompletionTimesMenuLabel";


$pageOptionsAtEnd = $_SESSION['showVisitCount']
? "<option value='showvisitcount'>Hide Visit Count"
: "<option value='showvisitcount'>Show Visit Count";
// ==================================
require_once "mobile-frame.php";


//require_once "preference-fns.php";
//$_SESSION["preferences"] = fetchPreferences();


$displayDate = longerDayAndDate(strtotime($date));
if($_SESSION['showVisitCount']) {
	if($canceledCount)  //  && mattOnlyTEST()
		$canceledCount = "/<span style='color:#FFA7A7'>$canceledCount</span>";
	$displayDate .= " (".count($appts)."$canceledCount)";
}
if($calendarTest) $displayDate = "<div id='dateDisplayed'>$displayDate</div>";
?>
<div class='pageoptiondiv' id='contentdiv'>
<table class='lean pageoptiondiv'>
<tr>
	<td><img style='cursor:pointer;' onclick='changeDay(-1)' src='art/prev_day.gif' height=20 ></td>
	<td><?= $displayDate ?></td>
	<td><img style='cursor:pointer;' onclick='changeDay("+1")' src='art/next_day.gif' height=20></td></tr>
</table>
</div>
<style>
.rfloat {float:right;margin-left: 2px;}
.arr {font-weight:bold;color:blue;}
.cmpl {font-weight:bold;color:yellow;}
/*.note {background: url('art/note-bg.gif') no-repeat center center;}*/
.note {color:red;}
.tinynote {color: <?= $_SESSION['preferences']['mobileSitterVisitNoteColor'] ? $_SESSION['preferences']['mobileSitterVisitNoteColor'] : 'black' ?>;}
</style>
<div class='pagecontentdiv'>
<table class='visitlist' cellspacing=0>
<?
if(!$appts) {
	$more = $completedFilter ? ' Uncompleted ' : ' ';
	echo "<tr style='text-align:center;padding-top:10px;color:green;font-style:italic;'><td>No$more"."Visits Today.<td></tr>";
}
$detailedVisits = $_SESSION['preferences']['mobileDetailedListVisit'];
if($detailedVisits) $allServiceNames = array_map('trim', fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype"));

if($appts && $detailedVisits) {
	$displayNoteLength = 65;
	require_once "service-fns.php";
	foreach($appts as $appt) $packs[$appt['packageptr']] = $appt;

	foreach((array)$packs as $id => $appt) {
		$curr = findCurrentPackageVersion($id, $appt['clientptr'], $appt['recurringpackage']);

		$latest[$id] = $curr;
		if($curr && !isset($packnotes[$curr])) {
			$table = $appt['recurringpackage'] ? 'tblrecurringpackage' : 'tblservicepackage';
			$packnotes[$curr] = fetchRow0Col0("SELECT notes FROM $table WHERE packageid = $curr LIMIT 1");
		}
	}
}

if($_SESSION['preferences']['providersScheduleRetrospectionLimit']) {
	$earliestDateAllowed = strtotime("-{$_SESSION['preferences']['providersScheduleRetrospectionLimit']} days", strtotime(date('Y-m-d')));
	$tooEarly = strtotime(date('Y-m-d', strtotime($date))) < $earliestDateAllowed;
}


if($tooEarly && $appts)  echo
"<tr>	<td colspan=3>Visits from before ".shortNaturalDate($earliestDateAllowed)." are not viewable.<br></td></tr>
";

else foreach($appts as $appt)  {
	$tod = str_replace(' ', '&nbsp;', str_replace('-', '<br>', $appt['timeofday']));
	if(FALSE && (mattOnlyTEST() || dbTEST('tonkapetsitters,tonkatest')) && $appt['highpriority']) 
		$tod = "<img src='art/highpriority.gif' style='float:left;'>$tod";
	if($showArrivalAndCompletionTimes) { 
		if($appt['completed']) 	$tod = '<span class="cmpl">done:</span> '.date('h:i a', strtotime($appt['completed']));
		else {
			$arr = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = {$appt['appointmentid']} AND event = 'arrived'", 1);
			if($arr) $tod = '<span class="arr">arrived:</span> '.date('h:i a', strtotime($arr));
		}
	}
	

	if($appt['note']) $appt['note'] = strip_tags($appt['note']);  // disallow HTML tags here
	
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
	if(strcmp($appt['endtime'], $appt['starttime']) < 0)
		$apptEndDate = date('Y-m-d', strtotime("+1 day", strtotime($date)));
	else $apptEndDate = $date;
	$rowClass = $appt['canceled'] ? 'canceledtask' : (
							$appt['completed'] ? 'completedtask' : (
							strcmp("$apptEndDate {$appt['endtime']}", $now) < 0 ? 'noncompletedtask' : ''));

	if(TRUE || mattOnlyTEST() || dbTEST('tonkapetsitters,tonkatest')) { // this has no effect on the iPad.  Crap!
		if($appt['highpriority']) {
			$rowClass .= ' highpriority top row';
			if($detailedVisits) $detailRowClass = 'highpriority bottom row';
			else $rowClass .= ' highpriority bottom row';
		}
		else $detailRowClass = null;
	}
	
	$rowClassEquals = $rowClass ? "class='$rowClass'" : '';
	$completeButton = $appt['canceled'] || $appt['completed'] || strcmp($date, date('Y-m-d', strtotime('tomorrow'))) >= 0
				? ''
				: 	"<br><img src='art/accepted_38X16.png' width=38 height=18
											onclick='visitAction(\"complete\", {$appt['appointmentid']}, \"update\")'> ";
											//onmouseover='expandButton(this, 2)'
											//onmouseout='shrinkButton(this, 2)'
	if(!$detailedVisits) {
		$timeclass = $appt['note'] ? "class='note'" : '';
	}
	else {
		$ignoreNotes = array("[START]","[FINISH]", "[START][FINISH]");
		$packageNote = $packnotes[$latest[$appt['packageptr']]];
		$packageNote = $packageNote && $appt['note'] ? "<span style='color:blue;'> $packageNote</span>" : $packageNote;
		$note = 
			$row['note'] && !in_array(strtoupper(str_replace("\r", '', str_replace("\n", '', $appt['note']))), $ignoreNotes)
				? $appt['note'] : (
			$appt['appointmentid'] 
					? ($appt['note'] ? "{$appt['note']}\n" : '').$packageNote 
			: '');
		if((TRUE || mattOnlyTEST() || dbTEST('tonkatest,tonkapetsitters')) && $appt['pendingchange']) {
				$pendingNote = "<span style='color: red; font-weight: bold;' colspan=2>"
									.($appt['pendingchange'] < 0 ? 'Cancel' : 'Change')
									." Pending</span> ";
				$allowExtraNoteLength = strlen($pendingNote) - strlen(strip_tags($pendingNote));
				$note = $pendingNote.$note;
		}
		else $allowExtraNoteLength = 0;
	}

	//else $note = $appt['note'] ? $appt['note'] : $packnotes[$latest[$appt['packageptr']]];
	if(FALSE && (mattOnlyTEST() || dbTEST('tonkapetsitters')) && $appt['highpriority']) 
		$highpriority = "<img src='art/highpriority.gif' style='float:left;'><font color='red'>HIGH PRIORITY</font> ";
	else $highpriority = "";
	
	//$clientLabel = "{$appt['name']}$pets";
	//if(dbTEST('agisdogs,tonkatest,dogslife')) {
		require_once "mobile-prov-fns.php";
		$clientLabel = visitListClientLabel($appt);
	//}
	
  echo
"<tr $rowClassEquals>
	<td><a $timeclass href='appointment-view-mobile.php?id={$appt['appointmentid']}'>$notehint$tod</a>
	<td style='padding-left:3px;'><a href='visit-sheet-mobile.php?id={$appt['clientptr']}&date=$date'>$clientLabel</a>
	<td $keyTitle	style='text-align:center;'><span $keyClass $keyClick>{$appt['key']}</span>$completeButton</tr>"
.(!$detailedVisits ? '' :
		"\n<tr class='$detailRowClass'><td colspan=3 class='$rowClass tinynote'>$highpriority({$allServiceNames[$appt['servicecode']]}) "
			.(!$note ? '' : truncatedLabel($note, $allowExtraNoteLength+$displayNoteLength)
			."</td></tr>"))
."<tr><td class='visitlistsepr' colspan=3>&nbsp;</tr>
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
<!-- link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/ -->
<!-- script type="text/javascript" src="jquery_1.8_jquery-ui.min.js"></script -->
 
<!-- PROBLEM: on smartphone, the lightbox opens too far down the page
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
-->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.5.2/jquery.min.js"></script>
<script type="text/javascript" src="colorbox/jquery.colorboxV1.3.17.1.js"></script>
<link href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
<style>
/*.ui-datepicker-calendar td, th { font-size:0.8em; width:10px;}*/
/*.ui-datepicker-title { font-size:0.5em; }*/
</style>
<? $mobileVisitActionJavascript =	loginidsOnlyTEST('shinego,koxford,joshslade,testbenball,dlifebeth,tablet3,sgnote2,apple5')? "mobile-visit-actionV2.js" : "mobile-visit-action.js"; ?>
<script type="text/javascript" src="<?= $mobileVisitActionJavascript ?>"></script>
<script language='javascript'>
function changeDay(by) {
	document.location.href='index.php?date=<?= $date ?>&delta='+by;
}
function hidecompletedtoggle() {
	ajaxGetAndCallWith('home-prov-mobile.php?hidecompletedtoggle=1', 
												function() {update(0,0);},
												0);
}

function showarrivalandcompletiontimestoggle() {
	ajaxGetAndCallWith('home-prov-mobile.php?showarrivalandcompletiontimestoggle=1', 
												function() {update(0,0);},
												0);
}

function showvisitcount() {
	ajaxGetAndCallWith('home-prov-mobile.php?showvisitcount=1', 
												function() {update(0,0);},
												0);
}

function update(aspect, arg) {
	<?
	if(!$showArrivalAndCompletionTimes) // refresh after arrival marked ONLY if showArrivalAndCompletionTimes
		echo "if(aspect=='arrived') {
						$.fn.colorbox.close();
						return;
						}";
	?>
	document.location.href='<?= $_SERVER["REQUEST_URI"] ?>';
}

function testT(s) {
	<? 	//if(IPAddressTEST('173.23.162.252')) echo 'alert("hello 4 u:"+s);'; ?>
}


var dateValue  = '<?= date('m/d/Y', strtotime($date)) ?>';
<? if($calendarTest) {  ?>
	$(function() {
		$("#dateDisplayed").click(function() {$("#dateDisplayed").datepicker(
				{defaultDate: dateValue,
				onSelect: function(dateText, inst) {
										if(true || dateText != dateValue) document.location.href='index.php?date='+escape(dateText); 
										// How to make this goddam calendar go away?!
										//$("#dateDisplayed").datepicker("hide");
										//$("#dateDisplayed").datepicker("destroy");
										//$("#dateDisplayed").datepicker("widget").css('display:none');
										//alert(inst);
										//alert($("#dateDisplayed").datepicker("widget"));
									}
				});});
	});

<? }  ?>
</script>
