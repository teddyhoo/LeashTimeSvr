<? // mmd-clients.php
// Mobile Manager Dashboard script to return clients identified by clientid
/* parameters:
	clientids - comma-separated
	changedsince - OPTIONAL datetime YYYY-mm-dd HH:ii:ss  -- 2018-03-27 14:32:45
	
	if changedsince supplied, return only those clients which have been saved since that datetime
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "mmd-api.php";

if(userRole() == 'd') locked('d-');
else locked('o-');

$headers = apache_request_headers();
}
foreach($headers as $hdr=>$val)
    if(strtoupper($hdr) == 'CONTENT-TYPE') $contentType = strtoupper($val);

if(strpos("$contentType", 'JSON') !== FALSE) {
    $INPUT_ARRAY = json_decode(file_get_contents('php://input'), true);   // $INPUT_ARRAY is a variable defined in this script only
}
else {
	$INPUT_ARRAY = $_REQUEST;
}


$ids = trim("{$INPUT_ARRAY['clientids']}");
if(!$ids) $clients = array('error'=>'no clientids supplied');
else {
	$ids = explode(',', $ids);
	foreach($ids as $id){
		if(!is_numeric("$id")) {
				$clients = array('error'=>'bad clientids supplied');
				break;
		}
	}
	if(!$error) {
		if($INPUT_ARRAY['changedsince']) {
			$changedSince = strtotime($INPUT_ARRAY['changedsince']);
			$lastSaved = fetchKeyValuePairs(
				"SELECT clientptr, value
					FROM tblclientpref 
					WHERE clientptr IN ({$INPUT_ARRAY['clientids']})
						AND property = 'lastSaved'", 1);
		}
		$clients = array();
		foreach($ids as $id) {
			if($lastSaved && $lastSaved[$id]) {
				$lastSaved = substr($lastSaved[$id], 0, strpos($lastSaved[$id], '|'));  // 2018-10-29 12:44:23|Matt Lindenfelser|526
				if(strtotime($lastSaved) < $changedSince)
					continue; // skip clients that hae not been updated
			}
			$client = populateClient($id);
			$clients[] = $client ? $client : array('error'=>'not found', 'clientid'=>$id);
		}
		$clients = array('clientcount'=>count($clients), 'clients'=>$clients);
	}
}

header("Content-type: application/json");
echo json_encode($clients);  

