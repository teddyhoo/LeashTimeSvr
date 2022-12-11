<? // client-scheduler-json-post.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
if(userRole() == 'c') locked('c-');
else locked('o-');

if(userRole() != 'c') {
	$error = "Operation disallowed: Code ".userRole();
	logChange(-99999, 'clientScheduler', 'm', "JSON schedule error: $error");
}
if(!$error) {
	$json = file_get_contents('php://input');
	if($_REQUEST['TEST']) $json = '{"start":"07/24/2019","end":"07/27/2019","servicecode":"29","prettypets":"All Pets","pets":"All Pets","visits":[{"date":"07/24/2019","servicecode":"29","timeofday":"5:00 pm-7:00 pm","pets":"All Pets"},{"date":"7/25/2019","servicecode":"29","timeofday":"9:00 am-11:00 am","pets":"All Pets"},{"date":"7/25/2019","servicecode":"29","timeofday":"3:00 pm-5:00 pm","pets":"All Pets"},{"date":"7/25/2019","servicecode":"29","timeofday":"7:00 pm-9:00 pm","pets":"All Pets"},{"date":"7/26/2019","servicecode":"29","timeofday":"9:00 am-11:00 am","pets":"All Pets"},{"date":"7/26/2019","servicecode":"29","timeofday":"3:00 pm-5:00 pm","pets":"All Pets"},{"date":"7/26/2019","servicecode":"29","timeofday":"7:00 pm-9:00 pm","pets":"All Pets"},{"date":"07/27/2019","servicecode":"29","timeofday":"6:00 am-9:00 am","pets":"All Pets"}],"note":"noice.","totaldays":4,"visitdays":4}';
	
	// Convert all dates in json to db-friendly format
	$scheduleJSON = json_decode($json, 'assoc');
	$scheduleJSON['start'] = date('Y-m-d', strtotime($scheduleJSON['start']));
	$scheduleJSON['end'] = date('Y-m-d', strtotime($scheduleJSON['end']));
	foreach($scheduleJSON['visits'] as $i => $visit)
		$scheduleJSON['visits'][$i]['date'] = date('Y-m-d', strtotime($visit['date']));
	//$scheduleJSON['services'] = $scheduleJSON['visits'];
	//unset($scheduleJSON['visits']);
	$json = json_encode($scheduleJSON);
	
	
	
	
	//insertTable('tbltextbag', array('referringtable'=>'jsonscheduler', 'body'=>$json), 1);


	if($json) {    // SUBMIT SCHEDULE
		// This come POSTed from a form in a JSON request
		$clientptr = userRole() == 'c' ? $_SESSION['clientid'] : 47; // really SHOULD be userRole c
		require_once "request-fns.php";
		require_once "client-fns.php";
		require_once "preference-fns.php";
		$request = array('note'=>$json, 'requesttype'=>'Schedule', 'clientptr'=>$clientptr);

	logChange(-99999, 'client-scheduler-jsn-post.php', 'm', $note="RECEIVED: [$json]");
		if(!($requestID = saveNewClientRequest($request, true))) {
	logChange(-99999, 'client-scheduler-jsn-post.php', 'm', $note="FAILED?");
			$error = mysqli_error();
			logChange($clientptr, 'clientScheduler', 'm', "JSON schedule error: $error");

		}
		else {
			logChange($clientptr, 'clientScheduler', 'm', "JSON schedule request recieved.");
		}
	//logChange(-99999, 'client-scheduler-jsn-post.php', 'm', $note="SUCCEDED or FAILED");
	}
	else $error = 'No schedule data supplied.';
}
//logChange(-99999, 'client-scheduler-jsn-post.php', 'm', $note="ERROR: [$error] REQUEST: [$requestID]");

header("Content-type: application/json");
if($error) echo json_encode(array('error'=>$error));
else echo json_encode(array('success'=>$requestID));
