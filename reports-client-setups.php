<? // reports-client-setups.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "invoice-fns.php";
require_once "field-utils.php";
require_once "client-flag-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('sort,csv,date,print,flagFilterType', $_REQUEST));

$flagFilterType = $flagFilterType ? $flagFilterType : 'all';

$pageTitle = "Clients Set Up Recently";


if($date) {
	$date = date('Y-m-d', strtotime($date));
	$since = date('m/d/Y', strtotime($date));
}
foreach($_REQUEST as $k => $v) {
	if($v && strpos($k, 'flag_') === 0) {
		$flags[] = substr($k, strlen('flag_'));
	}
}
if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	$extraHeadContent = "<style>.selected {background-color:red} .flagtd {padding:3px;}</style>";
	include "frame.html";
	// ***************************************************************************
	if($date) echo "Generated ".longestDayAndDateAndTime()."<p>";
?>
	<form name='reportform' method='POST'>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	
	calendarSet('Find clients set up since', 'date', $value=$date, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null);
	echo "&nbsp;";
	echoButton('', 'Print Report', "spawnPrinter()");
	hiddenElement('csv','');
	echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV()");
	if(getBizFlagList()) {
		echo "<table><tr><td valign=top>"
			.join(' ', radioButtonSet('flagFilterType', $flagFilterType, array('all'=>'all', 'any'=>'any'), $onClick=null))
			.'</td><td>';
		compactClientFlagPicker(join(',', (array)$flags), null, false, 22); 
		echo "</td></tr></table>";
	}
?>
	</form>
	<script language='javascript' src='common.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript'>
	function toggleFlag(td, i) {
		var newVal = $('#flag_'+i)[0].value == '1' ? '0' : '1';
		$('#flag_'+i)[0].value = newVal;
		if(newVal == 1) td.addClass('selected');
		else td.removeClass('selected');
	}
	
	function spawnPrinter() {
		if(MM_validateForm(
			'date','','R',
			'date','','isDate',
			'date','','isPastDate')) {
			var flagFilter = "&flagFilterType="+$("input[@name=flagFilterType]:checked").val();
			var flags = '';
			$('.flagtd').each(function(i, el) { flags += "&"+el.children[0].id+"="+el.children[0].value; });
			openConsoleWindow('reportprinter', 
				'reports-client-setups.php?print=1&date='+document.getElementById('date').value
					+flagFilter+flags, 700,700);
		}
	}
	function genCSV() {
		if(!MM_validateForm(
			'date','','R',
			'date','','isDate',
			'date','','isPastDate')) return;
		document.getElementById('csv').value=1;
		document.reportform.submit();
		document.getElementById('csv').value=0;
	}
	function genReport() {
		if(!MM_validateForm(
			'date','','R',
			'date','','isDate',
			'date','','isPastDate')) return;
		document.reportform.submit();
	}
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Setups.csv ");
	dumpCSVRow("Clients Without Service Since $since");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
}
else if($since) {
	$windowTitle = "Clients Set Up since $since";
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>$windowTitle</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
}

$orderBy = "lname, fname";
if($sort) {
	$sortParts = explode('_', $sort);
	$orderBy = join(' ',$sortParts);
	if($sortParts[0] == 'citystate') $orderBy .= ", lname, fname";
}
if($date) {
	$clients = fetchAssociationsKeyedBy($sql = 
		"SELECT *, CONCAT_WS(', ',lname, fname) as clientname, CONCAT_WS(', ', city, state, zip) as citystate, email
			FROM tblclient 
			WHERE active = 1 AND setupdate >= '$date'
			ORDER BY $orderBy", 'clientid');
	
	if($flags) foreach($clients as $clientid => $client) {
		$clientFlagIds = array();
		foreach(getClientFlags($clientid) as $cflag)
			$clientFlagIds[] = $cflag['flagid'];
		$match = $flagFilterType == 'all' ? 1 : 0;
		foreach($flags as $flag) {
			if($flagFilterType == 'all' && !in_array($flag, $clientFlagIds)) {
				$match = 0;
				break;
			}
			else if($flagFilterType == 'any' && in_array($flag, $clientFlagIds)) {
				$match = 1;
				break;
			}
		}
		if(!$match)
			unset($clients[$clientid]);
	}
			
//print_r('<p>'.$sql);			
			
	if($clients) { 
		$allPets = !$clients ? array() : fetchAssociations("SELECT * FROM tblpet WHERE active = 1 AND ownerptr IN (".join(',', array_keys($clients)).") ORDER BY name");
		$clientPets = array();
		foreach($allPets as $i => $pet) {
			if($dob = strtotime($pet['dob'])) {
				$thisYear = date('Y');
				$today = date('Y-m-d');
				$age = $thisYear - date('Y', $dob);
				if($today < "$thisYear".date('-m-d', $dob)) $age -= 1;
				$pet['age'] = $age;
				$pet['birthday'] = shortNaturalDate($dob, 'noYear');
			}
			$clientPets[$pet['ownerptr']][] = $pet;

		}
	}

	function collateRows($csv=false) {
		global $clients, $clientPets;
		$rows = array();
		foreach($clients as $client) {
			$row = $client;
			$primaryPhoneField = primaryPhoneField($client);
			$phones = array();
			foreach(array( 'homephone', 'cellphone', 'workphone', 'cellphone2') as $fld) {
				$phone = strippedPhoneNumber($client[$fld]);
				if(!trim($phone)) continue;
				$phone = "({$fld[0]})$phone";
				if($fld == $primaryPhoneField)
					$phone = $csv ? "*$phone" : "<b>$phone</b>";
				$phones[] = $phone;
			}
			$row['phones'] = join(' ', $phones);
			if($client['email'] && !$csv) $row['phones'] = "{$client['email']}<br>{$row['phones']}";
			$row['address'] = join(', ', array_diff(array($row['street1'], $row['street2']), array('')));
			$row['citystate'] = join(', ', array_diff(array($row['city'], $row['state'], $row['zip']), array('')));
			$row['setupdate'] = shortDate(strtotime($row['setupdate']));
			$rows[] = $row;

			//$lastVisitDate = $row['last'] ? "Last visit: ".date('m/d/Y', strtotime($row['last'])) : 'Never visited';

			$setupdate = $row['setupdate'];
			$pets = $clientPets[$client['clientid']];
			if(!$pets) {
				if($csv) {
					$rows['pets'] = "No Pets";
					//$rows['last'] = $rows['last'] ? date('m/d/Y', strtotime($row['last'])) : '--';
					$rows['setupdate'] = $row['setupdate'];
				}
				else $rows[] = array('#CUSTOM_ROW#'=>"<tr><td><u>$setupdate</u></td><td colspan=3 style='padding-bottom:10px;font-style:italic;'>No Pets</td></tr>");
			}
			else foreach($pets as $pet) {
				$parts = array();
				foreach(explode(',','name,type,breed,sex,color,fixed') as $fld)
					if($pet[$fld] || $csv) $parts[$fld] = $pet[$fld];
				$parts['sex'] = $parts['sex'] == 'm' ? 'Male' : ($parts['sex'] == 'f' ? 'Female' : 'Unspecified sex');
				$parts['fixed'] = $parts['fixed'] ? 'Fixed' : 'Not fixed';
				$parts['age'] = $pet['age'] ? "{$pet['age']} years" : '';
				$parts['birthday'] = $pet['birthday'] ? $pet['birthday'] : '';
				if($csv) {
					require_once "custom-field-fns.php";
					global $customFields;
					$customFields = $customFields ? $customFields : 
						//getCustomFields($activeOnly=true); 
						displayOrderCustomFields(
							getCustomFields($activeOnly=true, $visitSheetOnly=false, $fieldNames=getPetCustomFieldNames(), 
																$clientVisibleOnly=false), 'petcustom');
					$custVals = fetchKeyValuePairs("SELECT fieldname, value FROM relpetcustomfield WHERE petptr = {$pet['petid']}");
					foreach($customFields as $key => $custField)
						$parts[$key] = custValue($custField, $custVals[$key]);
					//$parts['last'] = $row['last'] ? date('m/d/Y', strtotime($row['last'])) : '--';
					$parts['setupdate'] = $setupdate;
					$rows[] = $parts;
				}
				else {
					$nonulls = array();
					foreach($parts as $p) if($p) $nonulls[] = $p;
					$rows[] = array('#CUSTOM_ROW#'=>"<tr><td><u>$setupdate</u></td><td colspan=3 style='padding-bottom:10px;'>".join(', ', $nonulls)."</td></tr>");
				}
				//$lastVisitDate = "&nbsp;";
				$setupdate = $csv ? '' : "&nbsp;";
			}

		}
		return $rows;
	}

	function custValue($field, $value) {
		if($field[2] == 'boolean') return $value ? 'yes' : 'no';
		else return $value;
	}

	function clientPetsTable() {
		$rows = collateRows();
		foreach($rows as $row) 
			$rowClasses[]	= $row['clientid'] ? 'futuretaskEVEN' : 'futuretask';
		$width = '95%'; //$_REQUEST['print'] ? '60%' : '45%';
		$columns = explodePairsLine("clientname|Client||phones|Email / Telephone (primary in bold)||address|Address||citystate|City");
		$columnSorts = array('clientname'=>null, 'citystate'=>null);
		if($sort) {
			$sort = explode('_', $sort);
			$columnSorts[$sort[0]] = $sort[1];
		}
		tableFrom($columns, $rows, "width='$width'", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
	}
}

function clientPetsCSV() {
	global $customFields;
	$rows = collateRows('csv');
	$columns = "lname|Last Name||fname|First Name||clientid|Client ID||setupdate|Set Up"
							."||name|Pet||type|Type||breed|Breed||sex|Sex||color|Color||fixed|Fixed||age|Age||birthday|Birthday"
							."||phones|Telephone (primary phone in bold)||address|Address||citystate|City||email|Email"
							;
	$columns = explodePairsLine($columns);
	
	require_once "custom-field-fns.php";
	foreach($customFields as $key => $custField)
		$columns[$key] = $custField[0];
	
	
	
	dumpCSVRow($columns);
	foreach($rows as $row) {
		if($row['age']) $row['age'] = substr($row['age'], 0, strpos($row['age'], ' '));
		if($row['clientid']) {
			//$client0 = array($row['clientname'], $row['clientid']);
			$client0 = array('lname'=>$row['lname'], 'fname'=>$row['fname'], 'clientid'=>$row['clientid']);
			$client1 = array('phones'=>$row['phones'], 'address'=>$row['address'], 'citystate'=>$row['citystate'], 'email'=>$row['email']);
			continue;
		}
		$padding = count($row) == 1 ? array('','','','','') : array();
		$finalRow = $client0;
		foreach((array)$row as $k => $v) $finalRow[$k] = $v;
		foreach($padding as $k => $v) $finalRow[$k] = $v;
		foreach($client1 as $k => $v) $finalRow[$k] = $v;
		dumpCSVRow($finalRow, array_keys($columns));
	}
}

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

if($csv) {
	clientPetsCSV();
	exit;
}
else if($date) clientPetsTable();
if(!$csv && !$print) echo "<br><img src='art/spacer.gif' width=1 height=300>";

if(!$print){
	echo "<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='popcalendar.js'></script>

	<script language='javascript'>
	function sortClick(sortKey, direction) {
		var start = '<?= $start ?>';
		var end = '<?= $end ?>';
		var clients = '<?= $clients ?>';
		document.location.href='reports-client-pets.php?sort='+sortKey+'_'+direction;
	}
	";
	dumpPopCalendarJS();
	echo "</script>";
// ***************************************************************************
	include "frame-end.html";
}
else {
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
}


?>
