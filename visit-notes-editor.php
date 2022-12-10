<? // visit-notes-editor.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "appointment-fns.php";

$locked = locked('o-');
extract(extractVars('client,starting,ending', $_REQUEST));

if($_POST) {
	foreach($_POST as $key => $val)
		if(strpos($key, 'id_') !== FALSE) $ids[] = substr($key, strlen('id_'));
	if($ids)
		updateTable('tblappointment', array('note'=>$_POST['note']), "appointmentid IN (".join(', ', $ids).")", 1);
	$message = "Visit notes changed: ".count($ids);
	$updateParent = true;
}

if($starting) $dates[] = "date >= '".date('Y-m-d', strtotime($starting))."'";
if($ending) $dates[] = "date <= '".date('Y-m-d', strtotime($ending))."'";
$dates = $dates ? join(' AND ', $dates) : '';
if($starting || $ending) {
	$appts = fetchAssociations(
		"SELECT v.*, IFNULL(nickname, CONCAT_WS(' ', p.fname, p.lname)) as provider, t.label as service
			FROM tblappointment v
			LEFT JOIN tblprovider p ON providerid = providerptr
			LEFT JOIN tblservicetype t ON servicetypeid = servicecode
			WHERE clientptr = $client AND $dates
			ORDER BY date, starttime");
}

include "frame-bannerless.php";
// ***************************************************************************
echo "<div style='text-align:right;width:100%'>";
echoButton('', 'Quit', 'parent.$.fn.colorbox.close();');
echo "</div>";
if($message) echo "<p style='color:darkgreen'>$message</p>";

echo "<form name='noteform' method='POST'>";
hiddenElement('client', $client);

if($appts) {
	echo "Write a note to overwrite the notes on the visits selected below: ";;
	echoButton('', 'Apply Note', 'applyNote()');
	echo "<textarea class='fontSize1_2em' rows=5 cols=80 name='note' id='note'></textarea><p>";
}


calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
echo "&nbsp;";
calendarSet('ending:', 'ending', $ending);
echo " ";
echoButton('showAppointments', 'Show', 'searchForAppointments()');
echo "<p align='center'>";
foreach(array(
							'Last Week'=>'Last Week',
							'This Week'=>'This Week',
							'Next Week'=>'Next Week', 
							date("M", strtotime("- 1 month"))=>'Last Month',
							date("M")=>'This Month',
							date("M", strtotime("+ 1 month"))=>'Next Month') 
				as $label => $val) {
	if($subseqentLink) echo " - ";
	else echo " ";
	$subseqentLink = 1;
	fauxLink($label, "showInterval(\"$val\")");
}
echo "</p>";

if($appts) {
	foreach($appts as $i => $appt) {
		$futurity = appointmentFuturity($appt);
		if($appt['canceled']) $rowClass = 'canceledtask';
		else if($appt['completed']) $rowClass = 'completedtask';
		else if($futurity == -1) {
			if(!$appt['completed']) $rowClass = 'noncompletedtask';
		}
		else if($appt['highpriority']) $rowClass = 'highprioritytask';
		else $rowClass = 'futuretask';
		if($i % 2) $rowClass .= 'EVEN';
		$checked = $_REQUEST["id_{$appt['appointmentid']}"] ? 'CHECKED' : '';
		$cbid = "id_{$appt['appointmentid']}";
		$rows[] = array(
			'#CUSTOM_ROW#'=>
				"<tr class='$rowClass' onclick='document.getElementById(\"$cbid\").click()'>"
				."<td><input class='cb' type='checkbox' $checked name='$cbid' id='$cbid' onclick='var e = arguments[0] || window.event;e.cancelBubble = true;'></td>"
				."<td>{$appt['date']}</td>"
				."<td>{$appt['timeofday']}</td>"
				."<td>{$appt['service']}</td>"
				."</tr>");
		if(trim($appt['note']))
			$rows[] = array(
				'#CUSTOM_ROW#'=>
					"<tr class='$rowClass'>"
					."<td colspan=4><b>Note:</b> {$appt['note']}</td>"
					."</tr>");
		$columns = explodePairsLine(' | ||date|Date||timeofday|Time||service|Service');

	}
	fauxLink('Select All', 'selectAll(1)');
	echo " - ";
	fauxLink('Deselect All', 'selectAll(0)');
	tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, $sortableCols, $rowClasses, $colClasses/*, 'sortAppointments'*/);
	echo "</form>";
}

foreach(array('Last Month','Last Week','This Week','This Month','Next Week', 'Next Month') as $intervalLabel)
	$intervals[] = "'$intervalLabel':'".interpretInterval($intervalLabel)."'";
$intervals = "  var intervals = {".join(',', $intervals)."};\n";

function interpretInterval($intervalLabel) {
	if($intervalLabel == 'Last Month') {
		$start = date("m/01/Y", strtotime("-1 month"));
		$end = date("m/t/Y", strtotime("-1 month"));
	}
	else if($intervalLabel == 'Next Month') {
		$start = date("m/01/Y", strtotime("+1 month"));
		$end = date("m/t/Y", strtotime("+1 month"));
	}
	else if($intervalLabel == 'This Month') {
		$start = date("m/01/Y");
		$end = date("m/t/Y");
	}
	else if($intervalLabel == 'Last Week') {
		$end = date("m/d/Y", strtotime("last Sunday"));
		$start = date("m/d/Y", strtotime("last Monday", strtotime($end)));
	}
	else if($intervalLabel == 'Next Week') {
		$start = date("m/d/Y", strtotime("next Monday"));
		$end = date("m/d/Y", strtotime("next Sunday", strtotime($start)));
	}
	else if($intervalLabel == 'This Week') {
		if(date('l') == "Monday") $start = date("m/d/Y");
		else $start = date("m/d/Y", strtotime("last Monday"));
		$end = date("m/d/Y", strtotime("next Sunday", strtotime($start)));
	}
	return "$start|$end";
}

?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>

<script language='javascript'>
<? if($updateParent) echo "if(parent.update) parent.update('appointments');\n"; ?>
function applyNote() {
	var sel = 0;
	var note = jstrim(document.getElementById('note').value);
	$('.cb').each(function(ind, el) {sel += (el.checked ? 1 : 0);});
	if(sel == 0) {
		alert('Please select some visits first.');
		return;
	}
	if(note.length == 0 && !confirm('You are about to CLEAR the note field on '+sel+' visit(s).  Continue?'))
		return;
	document.noteform.submit();
}

function selectAll(on) {
	$('.cb').each(function(ind, el) {el.checked = on;});
}

function showInterval(interval) {
  <?= $intervals ?>;
  interval = intervals[interval];
	if(interval) {
		interval = interval.split('|');
		document.getElementById('starting').value = interval[0];
		document.getElementById('ending').value = interval[1];
		document.getElementById('showAppointments').click();
	}
}

function searchForAppointments() {
  if(!MM_validateForm(
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) return;
	var client = document.getElementById('client').value;
	var starting = document.getElementById('starting').value;
	var ending = document.getElementById('ending').value;
	if(!starting && !ending) {
		alert('Either Start date or End date (or both) must be supplied.');
		return;
	}
	if(starting) starting = '&starting='+escape(starting);
	if(ending) ending = '&ending='+escape(ending);
	document.location.href="visit-notes-editor.php?client="+client
													+starting
													+ending;
}
<?
dumpPopCalendarJS();
?>
</script>
<?
// ***************************************************************************
