<? //visit-sheet-mobile.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "vet-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";
require_once "appointment-fns.php";
require_once "visit-sheet-fns.php";
require_once "key-fns.php";
require_once "custom-field-fns.php";

locked('vc');

extract($_REQUEST);

$noContactInfo = $_SESSION['preferences']['suppresscontactinfo'] && userRole() == 'p';

$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
if($delta) $date = date('Y-m-d', strtotime("$delta days", strtotime($date)));

$client = getClient($id);
if($noappointments) $date = null;
else $appointments = getDayAppointments($id, $date);
$googleAddress = "{$client['street1']} {$client['zip']}";

$vet = $client['vetptr'] ? getVet($client['vetptr']) : '';

$clinic = $client['clinicptr'] ? getClinic($client['clinicptr']) : '';

$pets = getActiveClientPets($id);


$secureMode = false; //$_SESSION['preferences']['secureClientInfo'];

if($secureMode  || !$_SESSION['secureKeyEnabled']) $keyLabel = "&nbsp;";
else {
	$keys = getClientKeys($id);
	$keyLabel = '';
	if($keys) {
		$useKeyDescriptions = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'];
		if($useKeyDescriptions) $keyDescription = $keys[0]['description'];
		$keyLabel = sprintf("%04d", $keys[0]['keyid']);
		/*if(!$keyDescription && isset($provider))  {// find his copy of the key
	//echo "PROV: $provider<p>";print_r($keys);exit;  
			foreach($keys[0] as $k => $possessor) {
				if(strpos($k, 'possessor') !== 0) continue;
				if($possessor == $provider) {
					$keyLabel .= '-'.sprintf("%02d", substr($k, 9));
					break;
				}
			}
		}*/
		$hasKey = $_SESSION["providerid"] && in_array($_SESSION["providerid"], keyProviders($keys[0]));
		if($hasKey) $keyIcon = "<img width=15 height=15 src='art/green-key.gif'>";
		else $keyIcon = "<img width=15 height=15 src='art/no-key.gif'>";
		$keyLabel = $keyDescription ? "$keyIcon $keyDescription" : $keyIcon."#$keyLabel";
		if(!$hasKey) $keyLabel = "<font color='red'>$keyLabel</font>";
		$safes = getKeySafes(); // for keyComment
		$providerNames = getProviderNames(); // for keyComment
		$idLine = $keyLabel; //sprintf("%04d", $keys[0]['keyid']);
		$keySection = "Key: ".($keyDescription ? "$keyDescription" : "$idLine")."<br>"
			.($useKeyDescriptions || !$keys[0]['description'] ? '' : "Description: {$keys[0]['description']}<br>")
			.($keys[0]['locklocation'] ? "Lock Location: {$keys[0]['locklocation']}<br>" : '')
			.($keys[0]['bin'] ? "Key Hook: {$keys[0]['bin']}<br>" : '')
			.'Copies: '.keyComment($keys[0])
			;
//if($useKeyDescriptions)	{echo $keyLabel;exit;}	
	}
}





$contacts = getClientContacts($id);

$providerNames = isset($providerNames) ? $providerNames : getProviderShortNames();


$data = array_merge($client);

if($keySection) $data['key'] = $keySection;



$othername = safeValue(trim("{$client['fname2']} {$client['lname2']}"));
if($othername) $data['othername'] = $othername;
if($client['defaultproviderptr']) {

	$data['provider'] = $providerNames[$client['defaultproviderptr']];
}
if($vet) {
	$data['vet'] = fullname($vet).' - '.$vet['officephone'];
	$data['vet'] = fauxLink($data['vet'], "document.location.href=\"viewVet-mobile.php?id={$vet['vetid']}\"", 1, 1);
}
if($clinic) {
	$data['clinic'] = $clinic['clinicname'].' - '.$clinic['officephone'];
	$data['clinic'] = fauxLink($data['clinic'], "document.location.href=\"viewVet-mobile.php?clinic=1&id={$clinic['clinicid']}\"", 1, 1);
}

$visitSheetOnly = !fetchPreference('showAllCustomFieldsInMobileSitterApps');

if($pets) {
	foreach($pets as $pet) {
		$pet = array_map('safeValue', $pet);
		$sex = $pet['sex'] == 'm' ? 'Male ' : ($pet['sex'] == 'f' ? 'Female ' : '');
		$fixed = $pet['fixed'] ? 'fixed' : 'not fixed';
		$descr = "<b>{$pet['name']}</b> - $sex{$pet['type']} ($fixed)";
		$more = trim("{$pet['color']} {$pet['breed']}");
		if($more) $descr .= "<br>$more";
		$more = petAge($pet);
		if($more) $descr .= "<br>Age: $more";
		$more = $pet['description'];
		if($more) $descr .= "<br>$more";
		//$more = $pet['notes'];
		$more = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $pet['notes']));

		if($more) $descr .= "<br>Note: $more";
		if($_SESSION['custom_pet_fields_enabled']) {
			
			if(TRUE || mattOnlyTEST()) $petProps['rowstyle'] = 'border-top: 1px solid gray;';
			
			ob_start();
			ob_implicit_flush(0);
			echo "<table style='border-collapse: collapse;'>"; // border-collapse added to allow tr border
			dumpPetCustomFieldRows($pet, $visitSheetOnly, $oneColumn=0, $hideEmptyNonBooleans=false, $petProps);
			echo "</table>";
			$descr .= ob_get_contents();
			//echo 'XXX: '.ob_get_contents();exit;
			ob_end_clean();
		}
		$data['pets'][] = $descr;
	}
	$data['pets'] = 'Urgent Veterinary Care has<br>'.
	     ($data['emergencycarepermission'] ? '' : "<span style='font-weight: bold;'><u>NOT</u></span> ").'been authorized.<p>'.
	     join('<p> ', $data['pets']);
}
if($contacts) {
	$types = array('emergency'=>'emergency Contact','neighbor'=>'Trusted Neighbor');
	foreach($contacts as $contact) {
		if(!$contact['type']) continue; // should not happen
		$descr = '';
		$more = trim($contact['name']);
		if($more) $descr .= $more;
		$descr .= '<br>'.($contact['haskey'] ? 'Has key to house' : 'Does not have key to house');
		$more = trim($contact['location']);
		if($more) $descr .= "<br>$more";
		foreach(array('cellphone','homephone','workphone') as $k) {
			$more = trim($contact[$k]);
			if($more) $descr .= "<br>(".$k[0].")$more";
		}
		$more = trim($contact['note']);
		if($more) $descr .= "<br>$more";
		$data[$contact['type']] = $descr;
	}
}
$data['status'] = $client['active'] ? 'Active' : 'Inactive';
if($client['prospect']) $data['status'] .= " Prospect";

$add = addressFields($client);
if($add) $data['homeaddress'] = $add;
$add = addressFields($client,'mail');
if($add) $data['mailaddress'] = $add;

if($id && $_SESSION["flags_enabled"]) {
	require_once "client-flag-fns.php";
	$flagPanel = clientFlagPanel($id, $officeOnly=true, $noEdit=false, $contentsOnly=false, $onClick='showFlagLegend()');
	$flagLegend = clientFlagLegend($id, $officeOnly=false, $class='flagLegend', $style=null);
	if($flagLegend) {
		$start = strpos($flagLegend, 'COUNT[')+strlen('COUNT[');
		$flagCount = substr($flagLegend, $start, strpos($flagLegend, ']', $start)-$start);
	}
	$flagCount = 0;
	$pagingInUse = $flagCount > 9;
	if($pagingInUse) {
		require_once "js-gui-fns.php";
		$flagLegend = "<div style='width:280px;height:183px;overflow:scroll;display:block;'>$flagLegend</div>";
		ob_start();
		ob_implicit_flush(0);
		pagingBox($flagLegend);
		$flagLegend = str_replace("\n", ' ', addslashes(ob_get_contents()));
		ob_end_clean();
	}
}

$delayPageContent = 0;
if(!$frameOutput) {
	$pageIsPrivate = true;	
	require_once "mobile-frame.php";
	$frameOutput = 1;
	echo "	
<style>
.topline td {/*font-size: 1.08em;*/font-weight:bold;}
td {vertical-align:top;}
.labelcell {
  /* font-size: 1.08em; */
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
  background: #FF8B00;
  color:black;
  font-weight: bold;
}
.dataCell {
  /* font-size: 1.08em; */
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
}
.jobstable {background: white;color:black;}
.jobstablecell {
		padding-bottom: 4px; 
		border-collapse: collapse;
		vertical-align: top;
		border-top: solid black 1px;
	}
.sortableListHeader {
}
.XsortableListCell {
		font-size: 0.85em; 
}
.dateRow {background: yellow;font-weight:bold;text-align:center;border:solid black 1px;}

.noHpadding td {
	padding-left: 0px;
	padding-right: 0px;
}
.flagLegend {}
</style>
";
}

if($noappointments) $dateHTML = '';
else {
	$noRestrictions =  $_SESSION['preferences']['sitterCanViewSchedulesOnDaysWithNoVisits'];
	
	$yesterdaysappts = getDayAppointments($id, date('Y-m-d', strtotime('-1 day', strtotime($date))));
	// if none of the appts are assigned to this sitter, then don't offer them
	foreach($yesterdaysappts as $i => $appt) if($appt['providerptr'] != $_SESSION["providerid"]) unset($yesterdaysappts[$i]);
	$yesterdaysappts = $noRestrictions || $yesterdaysappts ? "<img style='cursor:pointer;' onclick='changeDay(-1)' src='art/prev_day.gif' height=20 >" : '&nbsp;';
	
	$tomorrowsappts = getDayAppointments($id, date('Y-m-d', strtotime('+1 day', strtotime($date))));
	// if none of the appts are assigned to this sitter, then don't offer them
	foreach($tomorrowsappts as $i => $appt) if($appt['providerptr'] != $_SESSION["providerid"]) unset($tomorrowsappts[$i]);
	$tomorrowsappts = $noRestrictions || $tomorrowsappts ? "<img style='cursor:pointer;' onclick='changeDay(\"+1\")' src='art/next_day.gif' height=20>" : '&nbsp;';
	
	$displayDate = date('D M j, Y', strtotime($date));
	$displayDate = "
<table class='lean noHpadding'>
<tr>
	<td>$yesterdaysappts</td>
	<td onclick='goHome(this.innerHTML)'>$displayDate</td>
	<td>$tomorrowsappts</td></tr>
</table>";
}

if($date && $_SESSION['preferences']['providersScheduleRetrospectionLimit']) {
	$earliestDateAllowed = strtotime("-{$_SESSION['preferences']['providersScheduleRetrospectionLimit']} days", strtotime(date('Y-m-d')));
	$tooEarly = strtotime(date('Y-m-d', strtotime($date))) < $earliestDateAllowed;
	if($tooEarly) {
		echo "Visits before ".shortNaturalDate($earliestDateAllowed)." are not viewable.";
		exit;
	}
}

?>

<table class='topline' width=100% border=0 bordercolor=red><tr>
<td colspan=1>Client: <?= fullname($client) ?></td>
<td style='text-align:right'><?= addressLink('Map', $googleAddress) ?></td>
</tr>
<tr>
<td align=left><?= $displayDate ?></td>
<td align=right style='padding-top:4px;'><?= $keyLabel ?></td>
</tr>
<tr><td colspan=2 align=center><?= $flagPanel ?></td></tr>
</table>
<table width=100% border=0 bordercolor=red>
<?
?>
<?
echo "<tr><td colspan=1>";

reconnectPetBizDB();
if(!$noappointments) appointmentsTable($appointments);
echo "</td></tr>";

if($_SESSION['preferences']['enableLastVisitNote']) {
	$lastNote = lastVisitNote($id); // appointment-fns.php // appointmentid, note, providerptr, completed,  sitter
	if(is_array($lastNote)) {
		$completed = $lastNote['mobilecomplete'] 
			? longestDayAndDateAndTime(strtotime($lastNote['mobilecomplete']))
			: longestDayAndDate(strtotime($lastNote['completed']));
		$note = $lastNote['note'] ? $lastNote['note'] : '<i>No note</i>';
		$note = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $note));
		$sitter = $lastNote['sitter'] ? "<b>Sitter:</b> {$lastNote['sitter']}" : '';
		$payload = array("<b>$completed</b>", $sitter, $note);
		$payload = join('<br>', $payload);
	}
	else $payload = $lastNote;
	customLabelRow('Last Visit:', '', $payload, 'labelcell','sortableListCell','','','raw', 'oneCol');
}




$fields = explodePairsLine('email|Email||cellphone|Cell Phone||homephone|Home Phone||workphone|Work Phone||othername|Alt Name||email2|Alt Email||cellphone2|Alt Phone||'.
	               'fax|FAX||pager|Pager||provider|Primary Sitter||pets|Pets||vet|Veterinarian||clinic|Veterinary Clinic||'.
	               'emergency|Emergency Contact||neighbor|Trusted Neighbor');
if($noContactInfo) {
	unset($fields['email2']);
	unset($fields['email']);
	unset($fields['cellphone']);
	unset($fields['cellphone2']);
	unset($fields['homephone']);
	unset($fields['workphone']);
	unset($fields['fax']);
	unset($fields['pager']);
}
dumpFields($fields, 'oneCol');

$fields = explodePairsLine('key|Keys||homeaddress|Home Address||mailaddress|Mailing Address||directions|Directions to Home||'.
								 'leashloc|Leash Location||foodloc|Food Location||parkinginfo|Parking Info||garagegatecode|Garage/Gate Code');
if(!$keySection) unset($fields['key']);


if($googleAddress) $data['homeaddress'] = addressLink($data['homeaddress'], $googleAddress);
//if($data['mailaddress']) $data['mailaddress'] = addressLink($data['mailaddress']);
dumpFields($fields, 'oneCol');

if(!$secureMode) dumpAlarmTable(null, 'oneCol');
else {
  //$fields = explodePairsLine('companyandphone|Company||alarmpassword|Password||armalarm|Arm||disarmalarm|Disarm||alrmlocation|Location');
  $fields = explodePairsLine('companyandphone|Company||alarminfo|Alarm Info');
	$anyalarm = '';
  foreach(array_keys($fields) as $field) $anyalarm .= isset($data[$field]) ? $data[$field] : '';
  if($anyalarm)
		echo "<tr><td><hr>Please consult summary list for Alarm info.<hr></td></tr>";
}

if(dbTEST('jordanspetcare')) {
	// find visits for today
	$apptids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE clientptr = $id AND date = '$date' AND canceled IS NULL");
	// find latest event datetime
	if($apptids)
		$last = fetchRow0Col0($sql ="SELECT date FROM tblgeotrack WHERE appointmentptr IN (".join(',', $apptids).") ORDER BY date DESC LIMIT 1", 1);
		//print_r($last);
	//if($last) echo "<tr><td>Last activity: ".date('g:i a', strtotime($last))."</td></tr>";
	if($last) echo "Last activity: ".date('g:i a', strtotime($last))."<p>";
}

$fields = explodePairsLine('notes|Notes');

dumpFields($fields, 'oneCol');

dumpCustomFieldRows($data, $visitSheetOnly, 'oneCol');
?>
</table></td></tr>
<tr><td>
<?
dumpPetThumbnails($pets);

function addressLink($label, $googleAddress) {
	$fulladr = urlencode($googleAddress);
	if(!trim($label)) $label = '';
	else ;//$label = truncatedLabel($label, 24);
	return "<a href='http://maps.google.com/maps?output=mobile&t=m&q=$fulladr'>$label</a>";  //http://mapki.com/wiki/Google_Map_Parameters
}
?>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="jquery.busy.js"></script> 	
<script type="text/javascript">jQuery().busy("defaults", { img: 'art/busy.gif', offset : 0, hide : false });</script> 	
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<script type="text/javascript" src="common.js"></script>
<script language='javascript'>
var callBox = "<?= telephoneSMSDialogueHTML($name=null, $tel=null, $sms=false, $class=false); ?>";
var callBoxSMS = "<?= telephoneSMSDialogueHTML($name=null, $tel=null, $sms=true, $class=false); ?>";

function changeDay(by) {
	document.location.href='visit-sheet-mobile.php?<?= "noappointments=$noappointments&id=$id&date=$date" ?>&delta='+by;
}

function openCallBox(telname, tel, sms) {
	var box = sms ? callBoxSMS : callBox;
	box = box.replace('#NAME#', telname);
	box = box.replace(/#TEL#/g, tel);
	$.fn.colorbox({	html: box,	width:"280", height:"200", iframe:false, scrolling: "auto", opacity: "0.3"});
}

function showFlagLegend() {
	$.fn.colorbox({	html: "<?= $flagLegend ?>",	width:"280", height:"300", iframe:false, scrolling: "auto", opacity: "0.3"});
}

function goHome(date) {
	document.location.href='https://<?= $_SERVER["HTTP_HOST"] ?>/index.php?date='+escape(date);
}

</script>
<? if($pagingInUse) {
			dumpPagingBoxStyle();
			dumpPagingBoxJS('includescripttags');
	}
?>	
