<? // reports-birthdays.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('month', $_REQUEST));

$month = $month ? $month : date('F');
		
$pageTitle = "Pet Birthdays for $month";

$pets = fetchAssociations(
	"SELECT name, dob, ownerptr, fname, lname, street1, street2, city, state, zip
		FROM tblpet 
		LEFT JOIN tblclient ON clientid = ownerptr
		WHERE tblpet.active AND tblclient.active = 1 AND dob IS NOT NULL", 1);
foreach($pets as $i => $pet) {
	if(!strtotime($pet['dob']) || 
		($month != 'all' && date('F', strtotime($pet['dob'])) != $month))
		unset($pets[$i]);
	else {
		$pets[$i]['sortdate'] = date('m/d', strtotime($pet['dob']));
		$pets[$i]['listdate'] = month3Date(strtotime($pet['dob']));
	}
}

usort($pets, 'cmpDate');
function cmpDate($a, $b) { return strcmp($a['sortdate'], $b['sortdate']); }



$datelesspets = fetchAssociations(
	"SELECT name, type, ownerptr, fname, lname, CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(',', lname, fname) as sortname, email, email2, street1, street2, city, state, zip
		FROM tblpet 
		LEFT JOIN tblclient ON clientid = ownerptr
		WHERE tblpet.active AND tblclient.active = 1 AND (dob IS NULL OR dob = '12/31/1969')", 1);
usort($datelesspets, 'cmpNames');
function cmpNames($a, $b) {
	$result = strcmp($a['sortname'], $b['sortname']);
	return $result ? $result : strcmp($a['name'], $b['name']); 
}

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";
		
	include "frame.html";
	
	// ***************************************************************************
	
			
	
function dumpCalendarLooks($rowHeight, $descriptionColor) {
	global $appDayColor;
	echo <<<LOOKS
<style>
.addresstable { background:white;width:99%;border:solid black 0px;margin:5px; }
.addresstable td { font-size:1.1em; }
.quicktableheaders td {font-weight:bold;}
.monthnames { background:white;width:99%;border:solid black 0px;margin:5px; }
.monthnames td { font-size:1.2em;text-align:center; }
.previewcalendar { background:white;width:99%;border:solid black 2px;margin:5px; }

.previewcalendar td {border:solid black 1px;width:14.29%;}
.appday {border:solid black 1px;background:$appDayColor;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}
.apptable td {border:solid black 0px;}
.empty {border:solid black 1px;background:white;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

.month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

.dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
.daytop {padding:0px;margin:0px;width:100%}
.daynumber {display:inline;font-size:1.5em;font-weight:bold;text-align:right;width:50px;}
.addtimeoffplus {clear:right;float:right;padding-right:5px;}
.apptcontrols {cursor:pointer;float:left;margin-right:3px;height:10px;width:10px; border:solid darkgray 1px;}
.monthlink {font-size:0.75em;padding-left:20px;padding-right:20px;display:inline;}
</style>
LOOKS;
}

function echoMonthBar($month) {
	$days = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
	/*$baseLink = "timeoff-sitter-calendar.php?provid=$provid&editable=$editableTimeOff&date=";
	$prevMonthStart = date('Y-m-d', strtotime("-1 month", strtotime($day)));
	$prev = date('F Y', strtotime($prevMonthStart));
	$prev = "<div class='monthlink'><a href='$baseLink$prevMonthStart'>$prev</a></div>";
	$nextMonthStart = date('Y-m-d', strtotime("+1 month", strtotime($day)));
	$next = date('F Y', strtotime($nextMonthStart));
	$next = "<div class='monthlink'><a href='$baseLink$nextMonthStart'>$next</a></div>";*/
	echo "<tr><td class='month' colspan=7>$prev$month$next</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function echoDayBox($day, $provid, $editableTimeOff=false) {
	global $pastEditingAllowed, $editable, $unassignedDays;
	$dom = date('j', strtotime($day));
	$provid = $provid ? $provid : '0';
	if($pastEditingAllowed ||  $editable && $day >= date('Y-m-d'))
		$addLink = fauxLink('<img src="art/ez-add.gif">', "editTimeOff(null, $provid, \"$day\")", true, "Add new time off.");
	$content = petsBornToday($dom);
	//$class = $content ? 'appday' : 'empty';
	$class = 'appday';
	echo "<td class='$class' style='position:relative' id='box_$day' valign='top'>
		<div class='daytop'>
			<div class='daynumber'>$dom </div>
		</div>
		";
	if($class == 'empty') ;
	else {
		echo "<table class='apptable'>";
		echo "<tr><td style='text-align:left;color:black'>$content</td></tr>";
		echo "</table>";
	}
	echo "</td>";
}

function petsBornToday($dom) {
	global $pets;
	foreach((array)$pets as $pet)
		if(date('j', strtotime($pet['dob'])) == $dom) $names[] = "{$pet['name']} ({$pet['lname']})";
	return join('<br>', (array)$names);
}

	dumpCalendarLooks(100, $descriptionColor='unused');

	echo "<table class='monthnames'><tr>";
	echo "<td><a href='reports-birthdays.php?month=all'>All</a></td>";
	for($i=1;$i<13;$i++) {
		$f = date('F', strtotime("$i/1/2012"));
		$m = date('M', strtotime("$i/1/2012"));
		echo "<td><a href='reports-birthdays.php?month=$f'>$m</a></td>";
	}
	echo "</table>";

	if($month != 'all') {

		echo "<table class='previewcalendar'  border=1 bordercolor=black>";

		$monthStart = strtotime("$month 1 ".date('Y'));
		$monthEnd = strtotime("$month ".date('t', $monthStart)." ".date('Y'));
		//echo date('m/d/Y', $monthStart).'<p>'.date('t', $monthStart);exit;
		$month = '';
		$dayN = 0;
		// allow for appts before start...
		for($day = date('Y-m-d', $monthStart); $day <= date('Y-m-d', $monthEnd); $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
			$dow = date('w', strtotime($day));
			if($month != date('F', strtotime($day))) {
				$month = date('F', strtotime($day));
				echoMonthBar($month);
				echo "<tr>";
				for($i=0; $i < $dow; $i++) echo "<td>&nbsp;</td>";
			}
			if(!$dow) echo "</tr><tr>";
			echoDayBox($day, $provid, $editable);
			$dayN++;
		}
		if($dow && $month) {  // finish prior month, if any
			for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
			echo "</tr>";
		}
		echo "</table><p>";
	}
	
	foreach((array)$pets as $pet)
		$rows[] = array('Day'=>$pet['listdate'], 'Pet'=>$pet['name'], 'Owner'=>"{$pet['fname']} {$pet['lname']}",
										'Address'=>"{$pet['street1']} {$pet['street2']} {$pet['city']} {$pet['state']} {$pet['zip']}");
	
	if($rows) quickTable($rows, "class='addresstable'");
	
	
	if((mattOnlyTEST() || dbTEST('bluedogpetcarema')) && $datelesspets) {
		echo "<script language='javascript' src='common.js'></script>\n\n";
		foreach($datelesspets as $i => $pet) {
			$email = $pet['email'] ? $pet['email'] : $pet['email2'];
			if($email)
				$datelesspets[$i]['email']= 
					fauxLink($email, 
										"openConsoleWindow(\"emailcomposer\", \"comm-composer.php?client={$pet['ownerptr']}\",680,580);",
										$noEcho=true, $title='Write to this client');
		}
		echo "<p>";
		fauxLink('Pets without Birthdays', '$("#datelesspets").toggle();');
		echo "<p>";
		$columns = explodePairsLine('name|Pet||type|Type||client|Owner||email|Email');
		tableFrom($columns, $datelesspets, $attributes='id="datelesspets" style="display:none;"', $class=null, $headerClass='sortableListHeader');
	}
	
	//if(!$print && !$csv){
		echo "<br><img src='art/spacer.gif' width=1 height=300>";
	// ***************************************************************************
		include "frame-end.html";
}




function rowSort($a, $b) {
	global $sortKey;
	return strcmp(strtoupper($a[$sortKey]), strtoupper($b[$sortKey]));
}
	

function dumpCSVRow($row) {
	if(!$row) echo "\n";
	if(is_array($row)) echo join(',', array_map('csv',$row))."\n";
	else echo csv($row)."\n";
}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function openRequest(id) {
	openConsoleWindow("viewrequest", "request-edit.php?id="+id+"&updateList=requests",610,600);
}

function payProjectionDetail(prov) {
	var td = document.getElementById('detail_'+prov);
	if(td.innerHTML) {
		$.fn.removeClass("selectedbackground");
		td.innerHTML = '';
	}
	else {
		$.fn.addClass("selectedbackground");
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("payroll-projection-detail.php?start=<?= date('Y-m-d', strtotime($start)) ?>&end=<?= date('Y-m-d', strtotime($end)) ?>&prov="+prov, fillInDetail, 'detail_'+prov);
		//ajaxGet("payroll-projection-detail.php?start=<?= date('Y-m-d', strtotime($start)) ?>&end=<?= date('Y-m-d', strtotime($end)) ?>&prov="+prov, 'detail_'+prov);
	}
}

function fillInDetail(divid, html) {
	document.getElementById(divid).innerHTML = html;
	$('.BlockContent-body').busy("hide");
}

function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	//var providers = document.getElementById('providers');
	//providers = providers.options[providers.selectedIndex].value;
	document.location.href='reports-logins.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end; //+'&providers='+providers
}
</script>