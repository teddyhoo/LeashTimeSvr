<?
// client-picker-destination.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
include "gui-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);
$recurring = isset($recurring) ? $recurring : 0;

$baseQuery = "SELECT clientid, packageid, CONCAT_WS(' ',fname,lname) as name, CONCAT_WS(', ',street1, city) as address 
							FROM tblclient
							LEFT JOIN tblrecurringpackage ON clientid = clientptr
              WHERE active AND (packageid is null OR tblrecurringpackage.current=1)";

if(isset($pattern)) {
  if(strpos($pattern, '*') !== FALSE) $pattern = str_replace  ('*', '%', $pattern);
  else $pattern = "%$pattern%";
  $baseQuery = "$baseQuery AND CONCAT_WS(' ',fname,lname) like '$pattern'";
  $numFound = mysqli_num_rows(mysqli_query($baseQuery));
  if($numFound)
    $clients = fetchAssociations("$baseQuery ORDER BY lname, fname LIMIT 15");
}
else if(isset($linitial)) {
  $baseQuery = "$baseQuery AND lname like '$linitial%' ORDER BY lname, fname";
  $clients = fetchAssociations("$baseQuery");
  $numFound = count($clients);
}
else {
  $numFound = mysqli_num_rows(mysqli_query($baseQuery));
  $baseQuery = "$baseQuery ORDER BY lname, fname LIMIT 15";
  $clients = fetchAssociations("$baseQuery");
}


$packageSummaries = array();
if($clients) {
	foreach($clients as $client) $clientIds[] = $client['clientid'];
	$clientIds = join(',',$clientIds);
	$sql = "SELECT clientptr, cancellationdate, CONCAT(if(monthly, 'Monthly', 'Weekly'), if(cancellationdate, ' (Canceled)', '')) as kind
	        FROM tblrecurringpackage WHERE current and clientptr IN ($clientIds)";
	$packageSummaries = fetchAssociationsKeyedBy($sql, 'clientptr');
	foreach($packageSummaries as $client => $pckg) $packageSummaries[$client] = $packageSummaries[$client]['kind'];
	$packageSummaries = $packageSummaries ? $packageSummaries : array();
	
	$sql = "SELECT clientptr, count(*)
	        FROM tblservicepackage WHERE current and cancellationdate is null and clientptr IN ($clientIds)
	        GROUP BY clientptr";
	foreach(fetchAssociationsKeyedBy($sql, 'clientptr') as $client => $pckg)
	  $packageSummaries[$client] = $packageSummaries[$client]  
	    ? $packageSummaries[$client].' and Short Term'
	    : 'Short Term';
}
	
$displayablePattern = $pattern;
if(strpos($displayablePattern, '%') === 0) $displayablePattern = substr($displayablePattern, 1);
if(strrpos($displayablePattern, '%') === strlen($displayablePattern)-1) $displayablePattern = substr($displayablePattern, 0, -1);
$displayablePattern = str_replace('%', '*', $displayablePattern);
$pageTitle = "Schedule Visits";

include "frame.html";
// ***************************************************************************

?>
<style>
.results td {padding-left: 10px; font-size: 1.05em; }
.results th {padding-left: 10px; font-size: 1.05em; }
.quarter {width: 25%; font-size: 1.05em; }
.shortterm {background:lightgreen;}
.ongoing {background:khaki;text-align:center;}
.highlightedinitial {background:darkblue;color:white;font-weight:bold;flow:inline;padding-left:5px;padding-right:5px;}
</style>
</head>
<body style='margin-left: 10px;'>
<link href="style.css" rel="stylesheet" type="text/css" />
<link href="pet.css" rel="stylesheet" type="text/css" />
<? 
$explanation = "onMouseOver=explain(this) onMouseOut=clearExplanation()"; 
$rads = explodePairsLine('oneday|One-day||ezschedule|EZ Schedule||multiday|Pro Schedule||weekly|Regular Per-Visit');

$recurringCount = 2;
if($inclMonthly = $_SESSION['preferences']['monthlyServicesPrepaid']) {
	$rads['monthly'] = 'Fixed Monthly Price';
	$recurringCount = 1;
}

if($_SESSION['preferences']['hideProScheduleAtTop'] && $_SESSION['preferences']['hideProScheduleAtBottom'])
	unset($rads['multiday']);
if($_SESSION['preferences']['hideOneDayScheduleAtTop'] && $_SESSION['preferences']['hideOneDayScheduleAtBottom'])
	unset($rads['oneday']);
$nonrecurringCount = count($rads) - $recurringCount;
	
	
	
	
?>
<h3>Step 1: What Kind of Schedule?</h3>
<table width=75% >
<form name='packageform'>
<tr><td class='shortterm' width='50%' id='shortterm' colspan=<?= $nonrecurringCount ?> align=center <?= $explanation ?>>Short Term</td><td class='ongoing' id='ongoing' colspan=<?= $recurringCount ?> align=center <?= $explanation ?>>Ongoing</td></tr>
<tr>
<?
foreach($rads as $key => $label) {
	$disabled = false; //$key == 'oneday' ? 'disabled' : '';
	$tdclass = strpos($key, 'ly') ? 'ongoing' : 'shortterm';
	$checked = $ptype == $key ? 'CHECKED' : '';
  echo "<td class='$tdclass' $explanation id='td$key'><input $disabled value='$key' id='$key' name='packagetype' type='radio' $checked onclick='document.getElementById(\"step2div\").style.display=\"inline\"'> <label for='$key'>$label</label></td>\n";
}
?>
</tr>
</form>
</table>
<p>
<div style='height:20px;font-size:1.1em;' id='explanationdiv' colspan=4></div>

<div id='step2div' style='<?= isset($pattern) || isset($linitial) ? '' : 'display:none;' ?>'>
<h3>Step 2: Pick a Client</h3>
<form name=findclients method=post>
<input name=target type=hidden value='<?= $target ?>'>
<input name=pattern size=10 value="<?= $displayablePattern ?>" style='font-size: 1.4em;font-weight:normal;' autocomplete='off'> <? echoButton('', 'Search', "search()") ?>
</form>
<p style='font-size: 1.4em;font-weight:normal;'>
<?
for($i = ord('A'); $i <= ord('Z'); $i++) {
  $c = chr($i);
  //echo " <a href=client-picker.php?linitial=$c&target=$target>$c</a>";
  if(isset($linitial) && $linitial == $c) echo "<span class='highlightedinitial'>$c</span>";
  else echo " <a class='fauxlink' onClick='initialPick(\"$c\")'>$c</a>";
  if($c != 'Z') echo " - ";
}
?>
<p>
<?
if(isset($baseQuery)) {
  echo ($numFound ? $numFound : 'No')." clients found.  ";
  if($numFound > count($clients)) echo count($clients)." shown.";
?>
<p>

<table class='results'>

<?
$clientids = array();
if($clients) {
	foreach($clients as $client) $clientids[$client['clientid']] = $client;
	echo "<tr><th>Client</th><th>Address</th><th>Packages</th></tr>";
}
foreach($clientids as $clientid => $client) {
	
  $address = $client['address'];
  if($address[0] == ",") $address = substr($address, 1);
  
  $clientName = htmlentities($client['name'], ENT_QUOTES);
  echo "<tr><td><a href=# onClick='pickClient({$client['clientid']}, \"$clientName\", \"{$client['packageid']}\")'>$clientName</a></td><td>$address</td><td>";
  echo isset($packageSummaries[$client['clientid']]) 
          ? "<span style='color:green'>{$packageSummaries[$client['clientid']]}</span>"
          : '&nbsp;';
  echo "</td></tr>\n";
}
?>
</table>
</div>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function search() {
	var ptype = getCheckedValue(document.packageform.packagetype);
  document.location.href='package-create.php?pattern='+document.findclients.pattern.value+'&ptype='+ptype;
}

function initialPick(initial) {
	var ptype = getCheckedValue(document.packageform.packagetype);
  document.location.href='package-create.php?linitial='+initial+'&ptype='+ptype;
}

function explain(td) {
	var str =
	(td.id == 'shortterm' ? 'A Short-Term package has a pre-determined <b>start date</b> and <b>end date</b>.' :
	(td.id == 'ongoing' ? 'An Ongoing package has a start date but <b>no pre-determined end date</b>.  New visits are created for it automatically over time.' :
	(td.id == 'tdoneday' ? 'A One-Day package schedules one or more visits to occur on exactly <b>one day</b>.' :
	(td.id == 'tdmultiday' ? 'A Pro Schedule vacation package schedules a <b>pattern</b> of visits over a pre-determined range of days.' :
	(td.id == 'tdezschedule' ? 'An EZ Schedule vacation package schedules <b>individual</b> visits over a pre-determined range of days.' :
	(td.id == 'tdweekly' ? 'A Regular Per-Visit package schedules a <b>weekly pattern</b> of regular visits to be billed upon completion.' :
	(/*td.id == 'monthly'*/ 'A Fixed Price Monthly package schedules a pattern of regular visits and calculates a <b>fixed monthly price</b> for them.')))))));
	document.getElementById('explanationdiv').innerHTML=str;
}

function clearExplanation() {
	document.getElementById('explanationdiv').innerHTML='';
}


function pickClient(id, clientname, packageid) {  // add a parameter to this page: 'recurring' and go to "packageid=" only when it is true
  var ptype = getCheckedValue(document.packageform.packagetype);
  if(!ptype) {
		alert('Please choose a package type before choosing a client.');
		return;
	}
	var dest =
	  (ptype == 'weekly' ? 'service-repeating' :
	  (ptype == 'monthly' ? 'service-monthly' :
	  (ptype == 'oneday' ? 'service-oneday' :
	  (ptype == 'ezschedule' ? 'service-irregular' :
	  (/*ptype == 'multiday' */ 'service-nonrepeating')))));
	var recurring = ptype == 'weekly' || ptype == 'monthly';
	var packageid = packageid ? packageid : '0';
	if(recurring && packageid != '0') parameter = "packageid="+packageid;
	else parameter = "client="+id;
  document.location.href=dest+'.php?'+parameter;
}

function getCheckedValue(radioObj) {
	if(!radioObj)
		return "";
	var radioLength = radioObj.length;
	if(radioLength == undefined)
		if(radioObj.checked)
			return radioObj.value;
		else
			return "";
	for(var i = 0; i < radioLength; i++) {
		if(radioObj[i].checked) {
			return radioObj[i].value;
		}
	}
	return "";
}




</script>
<p><img src='art/spacer.gif' height=300>
<?
}
// ***************************************************************************
include "frame-end.html";
?>
