<?
//provider-payables-inc.php
require_once "js-gui-fns.php";
require_once "pay-fns.php";
$dbStarting = $starting;
if($includeCalendarWidgets || $strictDateRange) {
	$starting = isset($starting) ? $starting : shortDate(strtotime(date('Y-m-01')));
	$ending = isset($ending) ? ($ending ? $ending : shortDate()) : shortDate();
	$firstDay = date('Y-m-d', strtotime($starting));
	$lastDay = date('Y-m-d', strtotime($ending));
	//print_r($payables);exit;
}

$through = date('Y-m-d', strtotime(isset($through) ? $through : ($ending ? $ending : date('Y-m-d'))));

generatePayables($through, $id);
//
$payables = getUnpaidPayables($through, $id);
getPayableDetails($payables);

if($csv) {
	function dumpCSVRow($row, $cols=null) {
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
	header("Content-Disposition: inline; filename=Sitter-Payables-$client.csv ");
	dumpCSVRow("Payables for Sitter $clientName");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $starting - $ending");
	dumpCSVRow("");
	payablesCSV($payables, $noEdit=true, $showPaid=false, $noLinks=true, $suppressCols='');
}


//if(mattOnlyTEST()) print_r($payables);
if($_SESSION['preferences']['sittersPaidHourly']) {
	$visitIds = array();
	$totalSeconds = 0;
	foreach($payables as $p)
		if($p['itemtable'] == 'tblappointment')
			$visitIds[] = $p['itemptr'];
	if($visitIds) {
		$allHours = fetchCol0(
			"SELECT hours 
				FROM tblappointment 
				LEFT JOIN tblservicetype ON servicetypeid = servicecode 
				WHERE appointmentid IN (".join(',', $visitIds).")");
		foreach($allHours as $hours) {
			//echo "$hours = ".(strtotime($hours) - strtotime('00:00'))."<br>";
			if($hours)
				$totalSeconds += (strtotime($hours) - strtotime('00:00:00'));
		}
	}
	if($totalSeconds) $totalHours = ((integer)($totalSeconds/3600)).':'.date('i', $totalSeconds);
}

if($id) getNegativePaymentDetails($payables, $through, $id);
//print_r($payables);
//if(mattOnlyTEST()) {echo "$through<p>";	foreach($payables as $payable) echo print_r($payable,1).'<br>';}


foreach($payables as $i => $payable) {
	//echo "[{$payable['date']} : [$firstDay] : [$lastDay]] a: ".strcmp($firstDay, $payable['date'])." b: ".strcmp($payable['date'], $lastDay)."<br>";
	if(($includeCalendarWidgets || $strictDateRange) &&
		(strcmp($firstDay, $payable['date']) > 0 ||
		 strcmp($payable['date'], $lastDay) > 0)) {
			 unset($payables[$i]);
			 continue;
		 }
	$due += $payable['amount']-$payable['paid'];
	if($payable['itemtable'] == 'tblappointment') {
		$visitCount += 1;
		if($_SESSION['preferences']['sittersPaidHourly']) {
			if(!$travelAllowanceSet) {
				$travelAllowanceSet = true;
				$travelAllowance = 
					fetchRow0Col0("SELECT value FROM tblproviderpref WHERE providerptr = $id AND property = 'travelAllowance'");
				}
			$mileageCompensation += $travelAllowance;
		}
	}
	
	if(isset($payable['clientptr'])) $clientids[] = $payable['clientptr'];
}

if(!$csv) {
	$dollarsDue = dollarAmount($due);
	$prettyStart = $starting ? "from ".longerDayAndDate(strtotime($starting)).'<br>' : '';
	echo "<h2 $h2class align=center>$provider's Payables $prettyStart through: ".longerDayAndDate(strtotime($through))
						."<br>(".($visitCount ? $visitCount : 'No')." visits)";//."<br> $dollarsDue ($visitCount visits)";
	if($mileageCompensation)  ; //echo " + ".dollarAmount($mileageCompensation)." travel compensation";
	echo "<br>Total: ".dollarAmount($due);
	if($totalHours) echo " (Time: $totalHours)";
	echo "</h2>";
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173' || $db == 'woofies' || $db == 'fetch210' ||  $db == 'metrotails') 
	if(!$print) {
		echo "<div id='pagecontrols'>";
		if($_REQUEST['summary'] && userRole() != 'p') {
			echoButton('', 'Show Details', 
									"document.location.href=\"provider-payables.php?id=$id&starting=$starting&ending=$ending\"",
									'Button', 'ButtonDown', 
									false, "Show details for this period.");
		}
		else {

			if(userRole() != 'p' && ($_SESSION['staffuser'] || $_SESSION['preferences']['enableSitterPayablesSummary']))						
				echoButton('', 'Show Summary', 
										"document.location.href=\"provider-payables.php?id=$id&starting=$starting&ending=$through&summary=1\"",
										null, null, 
										false, "Show summary for this period.");


			if(userRole() != 'p') {
				echoButton('', 'Create Negative Compensation', 'createNegativeCompensation("")', 'HotButton', 'HotButtonDown', 
										false, "Specify an amount to be deducted from this sitter's pay.");
				echo " ";

			if($_SESSION['staffuser'])							
				echoButton('', 'List Negative Compensation', 'showNegativeCompensation("")', null, null, 
										false, "Specify an amount to be deducted from this sitter's pay.");
			}
// spreadsheet unicode character &#x25A6; "Square with orthogonal crosshatch fill "
			if(staffOnlyTEST() && $starting && $ending)	echo " ".fauxLink("<img width=15 style=\"cursor:pointer\" src=\"art/spreadsheet-32x32.png\">", "document.location.href=\"{$_SERVER['REQUEST_URI']}&csv=1\"", 'noecho', 'STAFF ONLY - Dump to a spreadsheet');
			if(staffOnlyTEST())	echo " ".fauxLink('Print <span class="tiplooks"> - STAFF ONLY FOR NOW</span>', 'printThisPage()', 'noecho');
		}

		$includeCalendarWidgets = $includeCalendarWidgets && !$_REQUEST['summary'];

		if($includeCalendarWidgets) {
			require_once "js-gui-fns.php";
			echo "<p>";
			echoButton('', 'Show', 'showVisitsInRange()');
			echo " ";

			calendarSet('Visits starting:', 'starting', $starting, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName='ending', $onChange='', $onFocus=null, $firstDayName=null);
			echo " ";
			calendarSet('ending:', 'ending', $through, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName='starting');
		}


		if($extraButtons) echo " $extraButtons";
		echo "</div>"; // id='pagecontrols'
	}

	if($_REQUEST['summary'] && userRole() != 'p') payableSummaryTable($payables);
	else payablesTable($payables, false, false, ($noLinks = $mobile), $suppressCols); // $suppressCols may be set by the includer 
	//function payablesTable(&$payables, $noEdit=false, $showPaid=false, $noLinks=false, $suppressCols='') 

	if(!(/*mattOnlyTEST() && */$noJavascript)) { 
		// the script was interfering with layout/operation when it was made part of the request note
		// in one particular instance (https://leashtime.com/support/admin/admin_ticket.php?track=HQ1-TL6-WS69)
		// so I now omit this unnecessary content from the IC Invoice.
		
		if($includeCalendarWidgets) echo "<script language='javascript' src='popcalendar.js'></script>";
		?>

		<script language='javascript'>
		function printThisPage() {
			document.getElementById("pagecontrols").style.display="none";window.print();
		}

		function update(target, val) { // called by appointment-edit, neg-compensation-edit.
			if(window.opener && window.opener.update) window.opener.update(target, <?= $id ?>);
			refresh(); // implemented below
		}

		function createNegativeCompensation(billable) {
			//alert('Dammit, Ted, I told you patience is a virtue!');return;
			var url = 'neg-compensation-edit.php?provider=<?= $id ?>&billableptr='+billable+'&lastday=<?= $through ?>';
			openConsoleWindow('paydocker', url,600,250);
		}

		function showNegativeCompensation() {
			//alert('Dammit, Ted, I told you patience is a virtue!');return;
			var url = 'negative-compensation-report.php?id=<?= $id ?>&starting=<?= $starting ?>&through=<?= $through ?>';
			openConsoleWindow('paydocker', url,600,250);
		}

		function openConsoleWindow(windowname, url,wide,high) {
			var w = window.open("",windowname,
				'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
			w.document.location.href=url;
			if(w) w.focus();
		}


		<?
		if($includeCalendarWidgets) dumpPopCalendarJS();
		?>



		</script>
	<?
		include "js-refresh.php";
	} // if !$noJavascript
} // if !$csv


?>

