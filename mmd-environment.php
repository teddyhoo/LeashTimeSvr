<? // mmd-environment.php
// Mobile Manager Dashboard script to return visits in a date range
/* parameters:
	timeframes - OPTIONAL, return named time frames
	servicetypes - OPTIONAL, return service types
	surchargetypes - OPTIONAL, return surcharge types
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "mmd-api.php";

if(userRole() == 'd') locked('d-');
else locked('o-');

extract(extractVars($allParams = 'timeframes,servicetypes,surchargetypes', $_REQUEST));

foreach(explode(',', $allParams) as $p) if($_REQUEST[$p]) $proceed = 1;

$result = $proceed ? getEnvironment($timeframes, $servicetypes, $surchargetypes)
					: array('error'=>'No environment lists requested.');

header("Content-type: application/json");
echo json_encode($result);