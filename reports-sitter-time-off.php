<?
// reports-sitter-time-off.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "js-gui-fns.php";

locked('o-');
extract(extractVars('provider,start,end,activeOnly,csv', $_REQUEST));
if($start) {
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$pClause = $provider == -1 ? '1=1' : "toi.providerptr = $provider";
	
	if(TRUE || mattOnlyTEST()) $activeOnly = $activeOnly ? "AND active = 1" : "";
	
	$timesOff = fetchAssociations(
		"SELECT toi.*, 
				pat.date as pdate,
				pat.note as pnote,
				pat.until,
				CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(',', lname, fname) as sortname
			FROM tbltimeoffinstance toi
			LEFT JOIN tbltimeoffpattern pat ON patternid = patternptr
			LEFT JOIN tblprovider ON providerid = toi.providerptr
			WHERE $pClause AND toi.date >= '$start' AND toi.date <= '$end' $activeOnly
			ORDER BY toi.date, sortname", 1);
			
	if($timesOff) {
		$mgrs = getManagers();
		//if(mattOnlyTEST()) print_r($mgrs);
		foreach($mgrs as $userid => $mgr) $users[$userid] = safeValue("[d] {$mgr['fname']} {$mgr['lname']}");
		foreach($timesOff as $i => $to) $providers[$to['providerptr']] = null;
		$providers = fetchKeyValuePairs("SELECT userid, CONCAT(fname, ' ', lname) FROM tblprovider WHERE providerid IN (".join(',',array_keys($providers)).")", 1);
		foreach($providers as $userid => $name) $users[$userid] =safeValue($name);
	}
	
	if($csv) {
		function dumpCSVRow($row, $cols=null) {
	//echo "R: ".print_r($row,1)."\nC: ".print_r($cols,1)."\n";
			if(!$row) echo "\n";
			if(is_array($row)) {
				if($cols) {
					$nrow = array();
					if(is_string($cols)) $cols = explode(',', $cols);
					foreach($cols as $k) $nrow[] = $row[$k];
					$row = $nrow;
				}
				echo join(',', array_map('csv',$row))."\n";
			}
			else echo csv($row)."\n";
		}

		function csv($val) {
			$val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
			$val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
			$val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
			return "\"$val\"";
		}

		header("Content-Type: text/csv");
		$columns = explodePairsLine('date|Date||sitter|Sitter||tod|Time||note|Note||user|Created By||created|Created||patternptr|Pattern||pdate|Starting||until|Ending');
		$activeOnly = $activeOnly ? "-Active-Only" : "";
		header("Content-Disposition: attachment; filename=Sitter-Times-Off$activeOnly.csv ");
		dumpCSVRow("Sitter Times Off");//.str_replace('-', ' ', $specificType)
		dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
		dumpCSVRow($columns);
		foreach($timesOff as $i => $to) {
			$row = array('date'=>shortDate(strtotime($to['date'])), 'sitter'=>$to['name'], 
										'tod'=> ($to['timeofday'] ? $to['timeofday'] : 'All Day'));
			$row['patternptr'] = $to['patternptr'] ? $to['patternptr'] : '--';
			$row['created'] = $to['created'];
			$row['pdate'] = $to['pdate'];
			$row['until'] = $to['until'];
			$row['user'] = $users[$to['createdby']];
			$row['note'] = $to['note'] ? $to['note'] : $to['pnote'];
			dumpCSVRow($row, array_keys($columns));
		}
		exit;
	}  // END csv
		
	else {
		foreach($timesOff as $i => $to) {
			$row = array('date'=>shortDate(strtotime($to['date'])), 'sitter'=>$to['name'], 
										'tod'=> ($to['timeofday'] ? $to['timeofday'] : 'All Day'));
			$descr = '';
			if($to['pdate']) $descr = shortNaturalDate(strtotime($to['pdate']), !'noYear');
			if($to['until']) $descr .= '-'.shortNaturalDate(strtotime($to['until']), !'noYear');
			$descr = $descr ? "$descr.  " : '';

			$title = "{$descr}Created by {$users[$to['createdby']]} at ".shortDate(strtotime($to['created']))." at ".date('n:i a', strtotime($to['created']));
			$row['tod'] = "<span title='$title' class='titlehint'>{$row['tod']}</span>";
			$row['note'] = 
					!$to['note'] ? '' : (
					$to['note'] == $to['pnote'] ? "<i title='This note applies to a range of times off'>{$to['pnote']}</i>" : $to['note']);


			$rows[] = $row;
			$rowClasses[] = $i % 2 ? 'futuretask' : 'futuretaskEVEN';
		}
		if(!$rows) $message = "No time off found.";
	}
}
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	?>
	<h2>Sitter Time Off Report</h2>
	<form method='POST' name='timeoffform'>
	<?
	$activeProviderSelections 
		= array_merge(array('--Select a Sitter--' => NULL, '--All Sitters--' => -1), getAllProviderSelections($availabilityDate, $zip, $separateActiveFromInactive=true));
		//= array_merge(array('--Select a Sitter--' => NULL, '--All Sitters--' => -1), getProviderSelections($availabilityDate, $zip, $status=null)); //getActiveProviderSelections());
	selectElement('Sitter:', "provider", $provider, $activeProviderSelections);

	calendarSet('Starting:', 'start', $start, null, null, true, 'end');
	calendarSet('ending:', 'end', $end);
	echo " ";
	if(TRUE || mattOnlyTEST()) labeledCheckbox('Active Sitters Only', 'activeOnly', $activeOnly, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);	
	echo "<img src='art/spacer.gif' width=5>";
	echoButton('', 'Show', 'checkAndSubmit()');
	if(TRUE || mattOnlyTEST()) echoButton('', 'Download Spreadsheet', "genCSV()");
	hiddenElement('csv', '');
?>
	</form>
	<?
	if($message) echo $message;
	else if($rows) {
//if(mattOnlyTEST()) echo "<hr>".count($rows)."<hr>";		
		$columns = explodePairsLine('date|Date||sitter|Sitter||tod|Time||note|Note');
		tableFrom($columns, $rows, 'WIDTH=100%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
	//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null)
	}
	if(!$rows) echo "<img src='art/spacer.gif' height=220>";
	?>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('provider','Sitter choice','start','Starting Date','end', 'Ending Date');	

	function checkAndSubmit() {
		if(MM_validateForm(
				'provider', '', 'R',
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			var provider = document.timeoffform.provider.value;
			//if(provider == 0) 
			var start = document.timeoffform.start.value;
			var end = document.timeoffform.end.value;
			if(start) start = '&start='+start;
			if(end) end = '&end='+end;
			document.timeoffform.submit();
			//document.location.href='reports-sitter-time-off.php?provider='+provider+start+end;
		}
	}

	<?
	dumpPopCalendarJS();


	?>
	function showCalendarX(ctl,date,c,d,e,f) {
			var datePosition = getAbsolutePosition(document.getElementById(date.id));
			//alert(datePosition.x-offset.x);
			//alert(datePosition.y-offset.y);
	}

	function genCSV() {
		if(MM_validateForm(
				'start', '', 'R',
				'start', '', 'isDate',
				'end', '', 'R',
				'end', '', 'isDate')) {
			document.getElementById('csv').value=1;
		  document.timeoffform.submit();
			document.getElementById('csv').value=0;
		}
	}
	</script>

	<?
	include "frame-end.html";
