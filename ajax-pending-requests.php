<? // ajax-pending-requests.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";


if(userRole() == 'c') {
	locked('c-');
	$clientid = $_SESSION["clientid"];
}
else {
	locked('o-');
	$clientid = $_REQUEST["clientid"];
}

header("Content-type: application/json");
echo json_encode(array('result'=>pendingRequestsLinkAndList($clientid)));

function pendingRequestsLinkAndList($clientid) {
	if(!(/* mattOnlyTEST() || **/$_SESSION['preferences']['enableClientPendingRequests'] || dbTEST('tonkatest,dogslife'))) return array();
	$typeLabels = explodePairsLine("Profile|Profile Change Request||cancel|Cancellation Request||uncancel|Un-Cancelation Request||change|Visit Change Request||Schedule|Schedule Request||General|General Request||schedulechange|Schedule Change");
	$requests = fetchAssociations(
		"SELECT * 
			FROM tblclientrequest
			WHERE clientptr = $clientid
				AND resolved = 0
				AND requesttype IN ('".join("','", array_keys($typeLabels))."')
				ORDER BY received", 1);
	if(count($requests) > 0) {	
		$link =  fauxLink(count($requests)." pending request".(count($requests) == 1 ? '' : 's'), 'viewPendingRequests()', 1, "Review your pending requests", 'pendingvisitslink');
		foreach($requests as $req) {
			$label = $typeLabels[$req['requesttype']];
			$label = fauxLink($label, "viewRequest({$req['requestid']})", 1, "Review this pending request");
			$t = strtotime($req['received']);
			$rows[] = "<tr><td>".shortDateAndDay($t)." ".date('h:i a', $t)."</td><td>$label</td></tr>";
		}
		$unresolvedRequests = "<h3>Pending Requests</h3><table class='pending-requests-table'>".join("  ", $rows)."</table>";
	}
	return array('link'=>$link, 'listhtml'=>$unresolvedRequests, 'numrequests'=>count($requests));
}

