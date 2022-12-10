<? // reports-client-dropouts.php

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
extract(extractVars('sort,csv,date,print,flagFilterType,excludenevers,omitpets', $_REQUEST));

$flagFilterType = $flagFilterType ? $flagFilterType : 'all';

$pageTitle = "Clients Without Service";


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
	if($date) echo "Generated ".longestDayAndDateAndTime()."<p>Only active clients shown.  Spreadsheet shows inactive clients as well.";
?>
	<form name='reportform' method='POST'>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	
	calendarSet('Find clients without visits since', 'date', $value=$date, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null);
	echo "&nbsp;";
	echoButton('', 'Print Report', "spawnPrinter()");
	hiddenElement('csv','');
	echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV()");
	echo "<p>";
	$options = array('Show all'=>0, 'Exclude clients with no visits ever'=>1, 'Show Only clients with no visits ever'=>-1);
	$radios = radioButtonSet('excludenevers', $excludenevers, $options, $onClick=null, $labelClass=null, $inputClass=null);
	foreach($radios as $button) echo "$button ";
	
	labeledCheckbox('Omit Pet Details', 'omitpets', $omitpets, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
	
if(getBizFlagList()) {
		echo "<table><tr><td colspan=2 style='padding-top:5px;'>If you select flags below, it will show only clients with:</td></tr><tr><td valign=top>"
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
				'reports-client-dropouts.php?print=1&date='+document.getElementById('date').value
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
	header("Content-Disposition: inline; filename=Client-Without-Service.csv ");
	dumpCSVRow("Clients Without Service Since $since");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
}
else if($since) {
	$windowTitle = "Clients Without Service since $since";
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
	$activeclients = fetchCol0($sql = 
		"SELECT DISTINCT clientptr 
			FROM tblappointment 
			WHERE canceled IS NULL
				AND date >= '$date'");
	$dropouts = $activeclients ? "AND clientid NOT IN (".join(',', $activeclients).")" : "AND 1=0";

	$activeOnly = $csv ? '1=1' : 'active = 1';
	
	$clients = fetchAssociationsKeyedBy($sql = 
		"SELECT *, CONCAT_WS(', ',lname, fname) as clientname, CONCAT_WS(', ', city, state, zip) as citystate, email, setupdate
			FROM tblclient 
			WHERE $activeOnly $dropouts
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
		$lastVisitDates = fetchKeyValuePairs(
			"SELECT clientptr, date 
				FROM tblappointment
				WHERE canceled IS NULL
					AND clientptr IN (".join(',', array_keys($clients)).")	
				ORDER by date, starttime");
		foreach($lastVisitDates as $clientptr => $lastdate) $clients[$clientptr]['last'] = $lastdate;
//if(mattOnlyTEST()) {echo "lastVisitDates	[3522]: {$lastVisitDates	[3522]}";exit;}
		if($excludenevers) foreach($clients as $i => $client) {
			if($excludenevers == -1) { if($client['last']) unset($clients[$i]); }
			else if(!$client['last']) unset($clients[$i]);
		}
			else  unset($clients[$i]);
		//foreach($clients as $clientptr => $client) if(!$client['last']) unset($clients[$clientptr]);
		if(!$omitpets) {
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
	}

	function collateRows($csv=false) {
		require_once "key-fns.php";
		global $clients, $clientPets, $omitpets, $safes;
		$safes = getKeySafes();
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
			$row['active'] = $row['active'] ? 'yes' : 'no';
			$row['phones'] = join(' ', $phones);
			if($client['email'] && !$csv) $row['phones'] = "{$client['email']}<br>{$row['phones']}";
			$row['address'] = join(', ', array_diff(array($row['street1'], $row['street2']), array('')));
			$row['citystate'] = join(', ', array_diff(array($row['city'], $row['state'], $row['zip']), array('')));
			$rows[] = $row;

			$lastVisitDate = $row['last'] ? "Last visit: ".date('m/d/Y', strtotime($row['last'])) : 'Never visited';
			
			$key = getClientKeys($client['clientid']);
			$keysHTML = null;
			if($key) {
				$key= $key[0];
				$locations = keyCopyLocationLabels($key);
				if(!$locations) $keysHTML = "{$key['keyid']} - no copies";
				else {
					foreach($locations as $copylabel => $loc) $keysHTML[] = "$copylabel: $loc";
					$keysHTML = join(', ', $keysHTML);
				}
			}
			else $keysHTML = "No Key";
			if(!$csv) $keysHTML = "<p>Keys: $keysHTML";

			$pets = $clientPets[$client['clientid']];
			$clientAdded = "Client set up: ".shortDate(strtotime($client['setupdate'])).". ";
			if($omitpets) {
				if($csv) {
					$rows[count($rows)-1]['last'] = $row['last'] ? date('m/d/Y', strtotime($row['last'])) : '--';
					$rows[count($rows)-1]['keys'] = $keysHTML;
				}
				else $rows[] = array('#CUSTOM_ROW#'=>"<tr><td>$clientAdded<u>$lastVisitDate</u>$keysHTML</td></tr>");
			}
			if(!$pets && !$omitpets) {
				if($csv) {
					$row['last'] = $row['last'] ? date('m/d/Y', strtotime($row['last'])) : '--';
					$row['keys'] = $keysHTML;
					$row['nopets'] = true;
					$rows[] = $row;
				}
				else $rows[] = array('#CUSTOM_ROW#'=>"<tr><td>$clientAdded<u>$lastVisitDate</u>$keysHTML</td><td colspan=3 style='padding-bottom:10px;font-style:italic;'>No Pets</td></tr>");
			}
			else foreach((array)$pets as $pet) {
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
					foreach($customFields as $key => $custField) {
						$parts[$key] = custValue($custField, $custVals[$key]);
						$parts['last'] = $row['last'] ? date('m/d/Y', strtotime($row['last'])) : '--';
						$parts['keys'] = $keysHTML;
					}
					$rows[] = $parts;
				}
				else {
					$nonulls = array();
					foreach($parts as $p) if($p) $nonulls[] = $p;
					$rows[] = array('#CUSTOM_ROW#'=>"<tr><td>$clientAdded<u>$lastVisitDate</u>$keysHTML</td><td colspan=3 style='padding-bottom:10px;'>".join(', ', $nonulls)."</td></tr>");
				}
				$lastVisitDate = "&nbsp;";
			}

		}
		return $rows;
	}

	function custValue($field, $value) {
		if($field[2] == 'boolean') return $value ? 'yes' : 'no';
		else return $value;
	}

	function clientPetsTable() {
		global $clients;
		
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
		echo "Found ".count($clients)." clients.";
		tableFrom($columns, $rows, "width='$width'", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
	}
}

function clientPetsCSV() {
	global $customFields, $omitpets;
	$rows = collateRows('csv');
//if(mattOnlyTEST()) {echo "ROWS: ".count($rows);	exit;}
	$columns = "lname|Last Name||fname|First Name||clientid|Client ID||active|Active||last|Last Visit||keys|Keys"
							."||name|Pet||type|Type||breed|Breed||sex|Sex||color|Color||fixed|Fixed||age|Age||birthday|Birthday"
							."||phones|Telephone (primary phone in bold)||address|Address||citystate|City||email|Email"
							;
	$columns = explodePairsLine($columns);
	
	if($omitpets) 
		foreach(explode(',', 'name,type,breed,sex,color,fixed,age,birthday') as $f)
			$columns[$f] = '';
	
	require_once "custom-field-fns.php";
	foreach((array)$customFields as $key => $custField)
		$columns[$key] = $custField[0];
	
	dumpCSVRow($columns);
	foreach($rows as $row) {
//if($row['lname'] == 'Goodwin' && mattOnlyTEST()) {print_r($row);exit;}
		if($row['age']) $row['age'] = substr($row['age'], 0, strpos($row['age'], ' '));
		if($row['clientid']) {
			//$client0 = array($row['clientname'], $row['clientid']);
			$client0 = array('lname'=>$row['lname'], 'fname'=>$row['fname'], 'clientid'=>$row['clientid'], 'active'=>$row['active']);
			$client1 = array('phones'=>$row['phones'], 'address'=>$row['address'], 'citystate'=>$row['citystate'], 'email'=>$row['email']);
			// if showing pets, then don't dump the line yet.instead write a line for each pet
			if(!$omitpets && !$row['nopets']) continue;
		}
		$padding = count($row) == 1 ? array('','','','','') : array();
		$finalRow = $client0;
		foreach($row as $k => $v) $finalRow[$k] = $v;
		foreach($padding as $k => $v) $finalRow[$k] = $v;
		foreach($client1 as $k => $v) $finalRow[$k] = $v;
		dumpCSVRow($finalRow, array_keys($columns));
	}
}

function clientPetsCSV2() {
	global $clients, $clientPets, $omitpets, $safes;
	global $customFields, $omitpets;
	require_once "key-fns.php";
	$safes = getKeySafes();
//if(mattOnlyTEST()) {echo "ROWS: ".count($rows);	exit;}
	$columns = "lname|Last Name||fname|First Name||clientid|Client ID||active|Active||last|Last Visit||keys|Keys"
							."||name|Pet||type|Type||breed|Breed||sex|Sex||color|Color||fixed|Fixed||age|Age||birthday|Birthday"
							."||phones|Telephone (primary phone in bold)||address|Address||citystate|City||email|Email"
							;
	$columns = explodePairsLine($columns);
	
	if($omitpets) {
		foreach(explode(',', 'name,type,breed,sex,color,fixed,age,birthday') as $f)
			$columns[$f] = '';
		$columns['name'] = 'Pets';
	}
	
	require_once "custom-field-fns.php";
	$customFields = $customFields ? $customFields : 
		//getCustomFields($activeOnly=true); 
		displayOrderCustomFields(
			getCustomFields($activeOnly=true, $visitSheetOnly=false, $fieldNames=getPetCustomFieldNames(), 
												$clientVisibleOnly=false), 'petcustom');
	foreach((array)$customFields as $key => $custField)
		$columns[$key] = $custField[0];
	
	dumpCSVRow($columns);
	
	// UGH ***********************************

	foreach($clients as $client) {
		$row = $client;
		$primaryPhoneField = primaryPhoneField($client);
		$phones = array();
		foreach(array( 'homephone', 'cellphone', 'workphone', 'cellphone2') as $fld) {
			$phone = strippedPhoneNumber($client[$fld]);
			if(!trim($phone)) continue;
			$phone = "({$fld[0]})$phone";
			if($fld == $primaryPhoneField)
				$phone = "*$phone";
			$phones[] = $phone;
		}
		$row['active'] = $row['active'] ? 'yes' : 'no';
		$row['phones'] = join(' ', $phones);
		$row['address'] = join(', ', array_diff(array($row['street1'], $row['street2']), array('')));
		$row['citystate'] = join(', ', array_diff(array($row['city'], $row['state'], $row['zip']), array('')));

		$key = getClientKeys($client['clientid']);
		$keysHTML = null;
		if($key) {
			$key= $key[0];
			$locations = keyCopyLocationLabels($key);
			if(!$locations) $keysHTML = "{$key['keyid']} - no copies";
			else {
				foreach($locations as $copylabel => $loc) $keysHTML[] = "$copylabel: $loc";
				$keysHTML = join(', ', $keysHTML);
			}
		}
		else $keysHTML = "No Key";

		$pets = $clientPets[$client['clientid']];
		$row['last'] = $row['last'] && $row['last'] != '12/31/1969' ? date('m/d/Y', strtotime($row['last'])) : "--";
//if(mattOnlyTEST()) {echo "\npets: [".print_r($pets,1)."] && omitpets: [$omitpets]\n";exit;}
		if(!$pets && !$omitpets) {
			$row['keys'] = $keysHTML;
			// dump petless client line
			$client0 = array('lname'=>$row['lname'], 'fname'=>$row['fname'], 'clientid'=>$row['clientid'], 'active'=>$row['active']);
			$client1 = array('phones'=>$row['phones'], 'address'=>$row['address'], 'citystate'=>$row['citystate'], 'email'=>$row['email']);
			// dumpcsv $parts
			$padding = count($parts) == 1 ? array('','','','','') : array();
			$finalRow = $client0;
			foreach($row as $k => $v) $finalRow[$k] = $v;
			foreach($padding as $k => $v) $finalRow[$k] = $v;
			foreach($client1 as $k => $v) $finalRow[$k] = $v;
			dumpCSVRow($finalRow, array_keys($columns));
		}
		else if($omitpets)  {
			$row['keys'] = $keysHTML;
			// dump petless client line
			$client0 = array('lname'=>$row['lname'], 'fname'=>$row['fname'], 'clientid'=>$row['clientid'], 'active'=>$row['active']);
			$client1 = array('phones'=>$row['phones'], 'address'=>$row['address'], 'citystate'=>$row['citystate'], 'email'=>$row['email']);
			// dumpcsv $parts
			require_once "pet-fns.php";
			$padding = array();
			$padding['name'] = getClientPetNames($client['clientid'], $inactiveAlso=false, $englishList=false);
			$finalRow = $client0;
			foreach($row as $k => $v) $finalRow[$k] = $v;
			foreach($padding as $k => $v) $finalRow[$k] = $v;
			foreach($client1 as $k => $v) $finalRow[$k] = $v;
			dumpCSVRow($finalRow, array_keys($columns));
		}
		else foreach((array)$pets as $pet) {
			$parts = array();
			foreach(explode(',','name,type,breed,sex,color,fixed') as $fld)
				if($pet[$fld] || $csv) $parts[$fld] = $pet[$fld];
			$parts['sex'] = $parts['sex'] == 'm' ? 'Male' : ($parts['sex'] == 'f' ? 'Female' : 'Unspecified sex');
			$parts['fixed'] = $parts['fixed'] ? 'Fixed' : 'Not fixed';
			$parts['age'] = $pet['age'];
			$parts['birthday'] = $pet['birthday'] ? $pet['birthday'] : '';
			$parts['last'] = $row['last'];
			$parts['keys'] = $keysHTML;
			require_once "custom-field-fns.php";
			global $customFields;
			$customFields = $customFields ? $customFields : 
				//getCustomFields($activeOnly=true); 
				displayOrderCustomFields(
					getCustomFields($activeOnly=true, $visitSheetOnly=false, $fieldNames=getPetCustomFieldNames(), 
														$clientVisibleOnly=false), 'petcustom');
			$custVals = fetchKeyValuePairs("SELECT fieldname, value FROM relpetcustomfield WHERE petptr = {$pet['petid']}");
			foreach($customFields as $key => $custField) {
				$parts[$key] = custValue($custField, $custVals[$key]);
			}
			$client0 = array('lname'=>$row['lname'], 'fname'=>$row['fname'], 'clientid'=>$row['clientid'], 'active'=>$row['active']);
			$client1 = array('phones'=>$row['phones'], 'address'=>$row['address'], 'citystate'=>$row['citystate'], 'email'=>$row['email']);
			// dumpcsv $parts
			$padding = count($parts) == 1 ? array('','','','','') : array();
			$finalRow = $client0;
			foreach($parts as $k => $v) $finalRow[$k] = $v;
			foreach($padding as $k => $v) $finalRow[$k] = $v;
			foreach($client1 as $k => $v) $finalRow[$k] = $v;
			dumpCSVRow($finalRow, array_keys($columns));
			$lastVisitDate = "&nbsp;";
		}

	}
	// UGHHHHHHH




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
	if(TRUE || mattOnlyTEST()) clientPetsCSV2();
	else clientPetsCSV();
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
