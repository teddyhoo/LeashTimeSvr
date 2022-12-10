<?
// prospect-summary.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "request-fns.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-');


extract(extractVars('requestStart,requestEnd,unresolvedOnly,offset,showType,assignedTo,sort,csv', $_REQUEST));

$showUnresolvedcheckboxes = staffOnlyTEST() || $_SESSION['preferences']['enableBulkRequestResolution'];


if($_POST) {
	$requestStartDB = date('Y-m-d', strtotime($requestStart));
	$requestEndDB = date('Y-m-d', strtotime($requestEnd));
	$requests = fetchAssociations(
		"SELECT *, fname as bizname, lname as owner
			FROM tblclientrequest 
			WHERE requesttype='Prospect' 
				AND received >= '$requestStartDB 00:00:00'
				AND received <= '$requestEndDB 00:00:00'
			ORDER BY received", 1);
	foreach($requests as $i => $request) {
		$requests[$i]['date'] = shortDate(strtotime($request['received']));
		if(!$csv) {
			$requests[$i]['note'] = prettyNote($request['note']);
			$requests[$i]['officenotes'] = prettyNote($request['officenotes']);
		}
		else {
			$requests[$i]['emailsupplied'] = $request['email'] ? 'yes' : 'no';
			$requests[$i]['phonesupplied'] = $request['phone'] ? 'yes' : 'no';
		}
		if($request['clientptr']) 
			$goldstar = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = {$request['clientptr']} AND property LIKE 'flag_%' AND value like '2|%'");
		$requests[$i]['goldstar'] = $goldstar ? 'yes' : 'no';
		$requests[$i]['isclient'] = $request['clientptr'] ? 'yes' : 'no';
		$requests[$i]['newsletter'] = strpos($request['note'], 'NEWSLETTER') ? 'yes' : 'no';
		$requests[$i]['trial'] = strpos($request['note'], 'FREE TRIAL') ? 'yes' : 'no';
		$requests[$i]['questionable'] = strpos(strtoupper($request['note']), 'HTTP') ? 'yes' : 'no';
	}
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
			//$val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
			return "\"$val\"";
		}

		header("Content-Type: text/csv");
		header("Content-Disposition: inline; filename=ProspectSummary.csv ");
		dumpCSVRow("Prospect Summary");
		dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
		dumpCSVRow("Period: $requestStart - $requestEnd");
		dumpCSVRow("");
		$columns = explodePairsLine('date|Date||bizname|Biz Name||owner|Owner||note|Note||officenotes|Office Notes||questionable|Questionable||trial|Trial?||isclient|Is Client||goldstar|Gold Star||newsletter|Newsletter||emailsupplied|Email||phonesupplied|Phone');
		dumpCSVRow($columns);
		if(!$requests) echo "There are no requests in the time frame specified.";
		else {
			foreach($requests as $row)
				dumpCSVRow($row, array_keys($columns));
		}
		exit;
	}
	
}


if(!isset($_REQUEST['unresolvedOnly'])) $unresolvedOnly = true;
// clientname|Client||requesttype|Request||date|Date||address|Address||phone|Phone


$pageTitle = 'Prospect Summary Report';
include "frame.html";
// ***************************************************************************

echo "<form name='showform' id='showform' method='POST'>";  // buttons do a redirect
//function calendarSet($label, $name, $value=null, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null) {

calendarSet('Start Date', 'requestStart', $requestStart, null, null, true, 'requestEnd');
echo ' ';
calendarSet('End Date', 'requestEnd', $requestEnd);
echo ' ';
labeledCheckbox('CSV', 'csv', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null);
echo ' ';
echoButton('showrequestsButton', 'Show', "showRequests()"); 

echo "</form>\n";

function prettyNote($note) {
	return str_replace("\n", "<br>", str_replace("\n\n", "<p>", $note));
}
if($_POST) {
	if(!$requests) echo "No requests found.";
	else {
		$columns= explodePairsLine('date|Date||bizname|Biz Name||owner|Owner||note|Note||officenotes|Office Notes');
		tableFrom($columns, $data=$requests, $attributes='border=1 width=50%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);

	}
}

if(!$requests) { ?>
<img src='art/spacer.gif' height=300 width=1>
<? } ?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
//alert($('input[spam="1"]').length);
if($('input[spam="1"]').length == 0) $(".spamRelated").toggle();

setPrettynames('requestStart', 'Start Date', 'requestEnd', 'End Date');

function update(aspect, text) {
	showRequests();
}

function showRequests(toggle, more, sort) {
	var requestStart = $('#requestStart').val();
	var requestEnd = $('#requestEnd').val();
	if(MM_validateForm(
		'requestStart','','isDate', 
		'requestEnd', '', 'isDate', 
		'requestStart', 'requestEnd', 'datesInOrder')) {
			document.showform.submit();
	}
}



<? dumpPopCalendarJS(); ?>
</script>
<?


// ***************************************************************************
include "frame-end.html";