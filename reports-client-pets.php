<? // reports-client-pets.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "invoice-fns.php";
require_once "field-utils.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('sort,csv', $_REQUEST));

		
$pageTitle = "Clients and Pets";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
	echo "Includes only Active clients and Active pets.  Generated ".longestDayAndDateAndTime()."<p>";
?>
	<form name='reportform' method='POST'>
<?
	//echoButton('', 'Generate Report', 'genReport()');
	//echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	echoButton('', 'Print Report', "spawnPrinter()");
	hiddenElement('csv','');
	echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV()");
?>
	</form>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	function spawnPrinter() {
		//document.location.href='reports-revenue.php?print=1&start=$pstart&end=$pend&reportType=$reportType'>
		if(MM_validateForm()) {
			openConsoleWindow('reportprinter', 'reports-client-pets.php?print=1', 700,700);
		}
	}
	function genCSV() {
		document.getElementById('csv').value=1;
		document.reportform.submit();
		document.getElementById('csv').value=0;
	}
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-and-Pets.csv ");
	dumpCSVRow("Clients and Pets");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
}
else {
	$windowTitle = 'Clients and Pets';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payments Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
}

$orderBy = "lname, fname";
if($sort) {
	$sortParts = explode('_', $sort);
	$orderBy = join(' ',$sortParts);
	if($sortParts[0] == 'citystate') $orderBy .= ", lname, fname";
}


$clientCount = fetchRow0Col0("SELECT COUNT(*) FROM tblclient WHERE active = 1");


/*$sql = 
	"SELECT *, CONCAT_WS(', ',lname, fname) as clientname, CONCAT_WS(', ', city, state, zip) as citystate, email
		FROM tblclient 
		WHERE active = 1
		ORDER BY $orderBy";
//$clients = fetchAssociationsKeyedBy($sql);
$clients = array();
if(!($result = doQuery($sql))) return null;
while($row = mysql_fetch_array($result, MYSQL_ASSOC))
	$clients[$row['clientid']] = $row;
mysql_free_result($result);
*/
//$allPets = !$clients ? array() : fetchAssociations("SELECT * FROM tblpet WHERE active = 1 AND ownerptr IN (".join(',', array_keys($clients)).") ORDER BY name");
$clientPets = array();
$allPetsQuery = doQuery(
	"SELECT p.* 
		FROM tblpet p 
		LEFT JOIN tblclient c ON clientid = ownerptr
		WHERE p.active = 1 AND c.active = 1
		ORDER BY name");
//foreach($allPets as $i => $pet) {
if($clientCount > 0)  while($pet = mysql_fetch_array($allPetsQuery, MYSQL_ASSOC)) {
	if($dob = strtotime($pet['dob'])) {
		$thisYear = date('Y');
		$today = date('Y-m-d');
		$age = $thisYear - date('Y', $dob);
		if($today < "$thisYear".date('-m-d', $dob)) $age -= 1;
		$pet['age'] = $age;
		$pet['birthday'] = shortNaturalDate($dob, 'noYear');
		$pet['dateOfBirth'] = shortDate($dob);
	}
	$clientPets[$pet['ownerptr']][] = $pet;
}

mysql_free_result($allPetsQuery);

function collateRows($csv=false) {
	global $orderBy, $clientPets;
	$sql = 
		"SELECT *, CONCAT_WS(', ',lname, fname) as clientname, CONCAT_WS(', ', city, state, zip) as citystate, email
			FROM tblclient 
			WHERE active = 1
			ORDER BY $orderBy";
	//$clients = fetchAssociationsKeyedBy($sql);
	if(!($result = doQuery($sql))) return null;
	$rows = array();
	while($client = mysql_fetch_array($result, MYSQL_ASSOC)) {
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
		$row['address'] = join(', ', array_diff(array($row['street1'], $row['street2']), array('')));
		$row['citystate'] = join(', ', array_diff(array($row['city'], $row['state'], $row['zip']), array('')));

		$rows[] = $row;
		$pets = $clientPets[$client['clientid']];
		if(!$pets) {
			if($csv) $rows[] = array('name'=>'No Pets', '', '', '', '', '', '', '',);
			else $rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=3 style='padding-bottom:10px;font-style:italic;'>No Pets</td></tr>");
		}
		else foreach($pets as $pet) {
			$parts = array('petid'=>$pet['petid']);
			foreach(explode(',','name,type,breed,sex,color,fixed') as $fld)
				if($pet[$fld] || $csv) $parts[$fld] = $pet[$fld];
			$parts['sex'] = $parts['sex'] == 'm' ? 'Male' : ($parts['sex'] == 'f' ? 'Female' : 'Unspecified sex');
			$parts['fixed'] = $parts['fixed'] ? 'Fixed' : 'Not fixed';
			$parts['age'] = $pet['age'] ? "{$pet['age']} years" : '';
			$parts['birthday'] = $pet['birthday'] ? $pet['birthday'] : '';
			$parts['dateOfBirth'] = $pet['dateOfBirth'] ? $pet['dateOfBirth'] : '';
			if($csv) {
				require_once "custom-field-fns.php";
/*				global $customFields;
				$customFields = $customFields ? $customFields : 
					//getCustomFields($activeOnly=true); 
					displayOrderCustomFields(
						getCustomFields($activeOnly=true, $visitSheetOnly=false, $fieldNames=getPetCustomFieldNames(), 
															$clientVisibleOnly=false), 'petcustom');
				$custVals = fetchKeyValuePairs("SELECT fieldname, value FROM relpetcustomfield WHERE petptr = {$pet['petid']}");
				foreach($customFields as $key => $custField)
					$parts[$key] = custValue($custField, $custVals[$key]);
*/
				$rows[] = $parts;
			}
			else {
				$nonulls = array();
				foreach($parts as $p) if($p) $nonulls[] = $p;
				$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=3 style='padding-bottom:10px;'>".join(', ', $nonulls)."</td></tr>");
			}
		}
	}
	return $rows;
}

function getPetCustomFieldsForCSV($petid) {
	global $customFields;
//$parts['petid'] = $petid;	
	$custVals = fetchKeyValuePairs("SELECT fieldname, value FROM relpetcustomfield WHERE petptr = $petid");
	foreach($customFields as $key => $custField)
		$parts[$key] = custValue($custField, $custVals[$key]);
	return $parts;
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
	$columns = explodePairsLine("clientname|Client||phones|Telephone (primary phone in bold)||address|Address||citystate|City");
	$columnSorts = array('clientname'=>null, 'citystate'=>null);
	if($sort) {
		$sort = explode('_', $sort);
		$columnSorts[$sort[0]] = $sort[1];
	}
	tableFrom($columns, $rows, "width='$width'", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
}

function clientPetsCSV() {
	require_once "custom-field-fns.php";
	global $customFields;
	$customFields = 
		//getCustomFields($activeOnly=true); 
		displayOrderCustomFields(
			getCustomFields($activeOnly=true, $visitSheetOnly=false, $fieldNames=getPetCustomFieldNames(), 
												$clientVisibleOnly=false), 'petcustom');

	$rows = collateRows('csv');
	$columns = "lname|Last Name||fname|First Name||clientid|Client ID"
							."||name|Pet||type|Type||breed|Breed||sex|Sex||color|Color||fixed|Fixed||age|Age||birthday|Birthday"
							."||dateOfBirth|DOB"
							."||phones|Telephone (primary phone in bold)||address|Address||citystate|City||email|Email"
							;
	$columns = explodePairsLine($columns);
	
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
		foreach($row as $k => $v) $finalRow[$k] = $v;
		foreach($padding as $k => $v) $finalRow[$k] = $v;
		foreach($client1 as $k => $v) $finalRow[$k] = $v;
		// add custom fields to finalRow
		if($row['petid']) {
			$parts = getPetCustomFieldsForCSV($row['petid']);
			foreach($parts as $k =>$part) // there may be no pets
				$finalRow[$k] = $part;
		}
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
else clientPetsTable();
	
	//paymentsTable($clients, $sort);
if(!$print){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
	echo "<script language='javascript' src='check-form.js'></script>";
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
<script language='javascript'>
function sortClick(sortKey, direction) {
	document.location.href='reports-client-pets.php?sort='+sortKey+'_'+direction;
}
</script>