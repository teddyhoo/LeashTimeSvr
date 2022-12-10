<? // payroll-projection-detail.php
// prov - provider id. 
// start, end

//https://leashtime.com/payroll-projection-detail.php?prov=6&start=2010-01-01&end=2010-07-08

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "service-fns.php";

// Determine access privs
$locked = locked('vh');

extract($_REQUEST);
$start = date("Y-m-d", strtotime($start));
$end = date("Y-m-d", strtotime($end));

$sql = "SELECT tblappointment.*,
				CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(' ', lname, fname) as sortname
				FROM tblappointment 
				LEFT JOIN tblclient ON clientid = clientptr
				WHERE canceled IS NULL AND providerptr = $prov AND date >= '$start' AND date <= '$end'
				ORDER BY date, starttime";
$visits = fetchAssociations($sql);

$sql = "SELECT *,
				CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(' ', lname, fname) as sortname
				FROM tblsurcharge 
				LEFT JOIN tblclient ON clientid = clientptr
				WHERE canceled IS NULL AND providerptr = $prov AND date >= '$start' AND date <= '$end'
				ORDER BY date, starttime";
foreach(fetchAssociations($sql) as $surcharge) $visits[] = $surcharge;

$sql = "SELECT comp.*, amount as rate, servicecode, canceled, clientptr, timeofday, pets, CONCAT_WS(' ', fname, lname) as client
				FROM tblothercomp comp
				LEFT JOIN tblappointment ON appointmentid = appointmentptr
				LEFT JOIN tblclient ON clientid = tblappointment.clientptr
				WHERE comp.providerptr = $prov AND comp.date >= '$start' AND comp.date <= '$end'
				ORDER BY date";
foreach(fetchAssociations($sql) as $othercomp) $visits[] = $othercomp;
$endtime = "$end 23:59:59";
$sql = "SELECT *, amount as rate, issuedate as date, CONCAT_WS(' ', fname, lname) as client
				FROM tblgratuity 
				LEFT JOIN tblclient ON clientid = clientptr
				WHERE providerptr = $prov AND issuedate >= '$start' AND issuedate <= '$endtime'
				ORDER BY date";
foreach(fetchAssociations($sql) as $othercomp) $visits[] = $othercomp;

if(!function_exists('dateSort')) {
	function dateSort($a, $b) {
		return strcmp($a['date'], $b['date']) 
			? strcmp($a['date'], $b['date'])
			: strcmp($a['starttime'], $b['starttime']);
	}

	function clientSort($a, $b) {
		return strcmp($a['sortname'], $b['sortname']) 
			? strcmp($a['sortname'], $b['sortname'])
			: dateSort($a, $b);
	}
}

usort($visits, ($detailsort == 'date' ? 'dateSort' : 'clientSort'));

$aggregateView = 1;
$allServiceNames = getAllServiceNamesById($refresh=1, $noInactiveLabel=false, $setGlobalVar=false);
$allSurchargeNames = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
if($csv) {
	$sitter = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $prov LIMIT 1");
	$serviceHours = fetchKeyValuePairs("SELECT servicetypeid, hours FROM tblservicetype");
}
foreach($visits as $visit) {
	$service = $visit['servicecode'] ? $allServiceNames[$visit['servicecode']] : (
		$visit['gratuityid'] ? 'Gratuity' : (
		$visit['surchargecode'] ? "Surcharge: ".$allSurchargeNames[$visit['surchargecode']] : $visit['descr']));
	if($visit['canceled']) $service .= ' (canceled)';
	$client = $visit['client'] ? $visit['client'] : ($visit['descr'] ? 'Other Compensation' : '');
	$pay = $visit['rate']+$visit['bonus'];
	$charge = $visit['charge']+$visit['adjustment'];
	if(!$csv) $pay = dollarAmount($pay);
	$rows[] = array('date'=>shortDate(strtotime($visit['date'])),
								'sitter'=>$sitter,
								'pay'=>$pay,
								'charge'=>$charge,
								'client'=>$client,
								'timeofday'=>$visit['timeofday'],
								'service'=>$service,
								'servicecode'=>$visit['servicecode'],
								'hours'=>$serviceHours[$visit['servicecode']],
								'rev'=>dollarAmount($visit['charge']+$visit['adjustment']),
								'pets'=>$visit['pets'],
								'city'=>$clientLocs[$visit['clientptr']]['city'],
								'zip'=>$clientLocs[$visit['clientptr']]['zip']
								);
}
$columns = explodePairsLine('date|Date||service|Service||rev|Revenue||pay|Pay||client|Client||timeofday|Time of Day||pets|Pets');
$colClasses = array('pay'=>'dollaramountcell', 'rev'=>'dollaramountcell');
$headerClass = array('pay'=>'dollaramountheader_right');
if($csv) {
	foreach($rows as $row) {
		unset($row['rev']);
		dumpCSVRow($row);
	}
}
else tableFrom($columns, $rows, 'width=700', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses);

/*
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
*/