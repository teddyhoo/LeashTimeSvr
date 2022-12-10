<? // routing-endpoint.php
/*
Usage:
	https://leashtime.com/routing-endpoint.php?sitters=1&username=i3&password=pass4512
	https://leashtime.com/routing-endpoint.php?visits=1&username=i3&password=pass4512&start=2018-02-26&end=2018-02-28
*/
require_once "routing-api.php";

if($_REQUEST['sitters']) {
	$activeOnly = $_REQUEST['all'] ? 0 : 1;
	header("Content-type: application/json");
	$sitters = getSitters($_REQUEST['username'], $_REQUEST['password'], $activeOnly); // 'i3', 'pass4512'
	if(!$sitters) $sitters = array('sittercount'=>0);
	else $sitters = array('sittercount'=>count($sitters), 'sitters'=>$sitters);
	echo json_encode($sitters);  
}
else if($_REQUEST['visits']) {
	header("Content-type: application/json");
	$includeUnassigned = $_REQUEST['unassigned'];
	$visits = getSitterVisits(
		$_REQUEST['username'], // 'i3', 'pass4512'
		$_REQUEST['password'], 
		$_REQUEST['start'], 
		$_REQUEST['end'], 
		$includeUnassigned, 
		$maskClientNames=true);
	if(!$visits) $visits = array('visitcount'=>0);
	else $visits = array('visitcount'=>count($visits), 'visits'=>$visits);
	echo json_encode($visits);
}
else if($_REQUEST['timeoff']) {
	header("Content-type: application/json");
	$bySitter = $_REQUEST['bysitter'];
	$timeoff = getSitterTimeOff(
		$_REQUEST['username'], // 'i3', 'pass4512'
		$_REQUEST['password'], 
		$_REQUEST['start'], 
		$_REQUEST['end'],
		$bySitter);
	$timeoff = array('timeoff'=>$timeoff);
	echo json_encode($timeoff);
}
endRequestSession();