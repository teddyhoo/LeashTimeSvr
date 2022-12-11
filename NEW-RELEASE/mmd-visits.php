<? // mmd-visits.php
// Mobile Manager Dashboard script to return visits in a date range
/* parameters:
	start - start date formatted YYYY-mm-dd
	end - end date formatted YYYY-mm-dd
	withtimeoff - OPTIONAL, when "1", include sitter time off instances
	sitterids - OPTIONAL, comma-separated list of sitter IDs
	clientids - OPTIONAL, comma-separated list of client IDs
	sortby  - OPTIONAL, sitter|client|time
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "mmd-api.php";

if(userRole() == 'd') locked('d-');
else locked('o-');

$headers = apache_request_headers();
//if(mattOnlyTEST()) {echo json_encode($headers);exit;}
foreach($headers as $hdr=>$val)
    if(strtoupper($hdr) == 'CONTENT-TYPE') $contentType = strtoupper($val);

if(strpos("$contentType", 'JSON') !== FALSE) {
    $INPUT_ARRAY = json_decode(file_get_contents('php://input'), true);   // $INPUT_ARRAY is a variable defined in this script only
}
else {
	$INPUT_ARRAY = $_REQUEST;
}

extract(extractVars('start,end,withtimeoff,providerids,clientids,sortby', $INPUT_ARRAY));

if(!array_key_exists('withtimeoff', $_REQUEST)) $withtimeoff = 1;

if(!strtotime($start)) $visits = array('error'=>($start ? 'start parameter is invalid' :  'start parameter is required'));
if(!strtotime($end)) $visits = array('error'=>($end ? 'end parameter is invalid' :  'end parameter is required'));
if($sortby && !in_array($sortby, array('sitter', 'client', 'time'))) 
	$visits = array('error'=>'If supplied, sortby must be one of sitter, client, or time');
else $visits = getVisits($start, $end, $withtimeoff, $sortby, $providerids, $clientids);

if($visits) {
	require_once "appointment-client-notification-fns.php";
	require_once "provider-fns.php";
	require_once "appointment-fns.php";
	require_once "visit-performance-fns.php";
	$reports = visitReportList($start, $end, $clientptrs=null);
	foreach($visits as $i => $visit) {
		$report = $reports[$visit['appointmentid']];
		if($report) 
			$visits[$i]['report'] = $report;
		if($report['appointmentid']) 
			$visits[$i]['performance'] = performancePacket($visit);
	}
}


header("Content-type: application/json");
echo json_encode($visits);

/*
$show = explode(',', 'date,timeofday,sittersort,clientsort,service');
foreach($visits as $i => $v) {
	$item = null;
	foreach($show as $k) $item[$k] = $v[$k];
	$items[] = $item;
}
require_once "gui-fns.php";
quickTable($items, $extra='border=1');
*/