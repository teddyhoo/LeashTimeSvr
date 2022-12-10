<? // request-edit-find-client.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "frame-bannerless.php";

$requestid = $_REQUEST['requestid'];
$setupdetails = fetchRow0Col0("SELECT note FROM tblclientrequest WHERE requestid = $requestid", 1);

$setupReq = simplexml_load_string($setupdetails);
//print_r($setupReq);
$owner = leashtime_real_escape_string(trim($setupReq->owner_fname).' '.trim($setupReq->owner_lname));
$bizName = leashtime_real_escape_string("{$setupReq->bizName}");
$bizEmail = leashtime_real_escape_string("{$setupReq->bizEmail}");
$ownerEmail = leashtime_real_escape_string("{$setupReq->owner_email}");

function choiceLinks($choices) {
	$links = array();
	foreach($choices as $choice) {
		$active = $choice['active'] ? '' : "[inactive]";
		$links[] = fauxLink("{$choice['fname']} ({$choice['lname']}) @{$choice['clientid']} $active",
													"chooseClient({$choice['clientid']})", 1, "@{$choice['clientid']}");
	}
	if(!$links) $links = array('-- none found --');
	return join('<br>', $links);
}

$matches['owner'] = fetchAssociationsKeyedBy("SELECT clientid, active, lname, fname FROM tblclient WHERE lname = '$owner'", 'clientid');
echo "<p><b>owner [{$owner}]</b><br>".choiceLinks($matches['owner']);

$matches['bizName'] = fetchAssociationsKeyedBy("SELECT clientid, active, lname, fname FROM tblclient WHERE fname = '$bizName'", 'clientid');
echo "<p><b>bizName [{$bizName}]</b><br>".choiceLinks($matches['bizName']);

$matches['ownerEmail'] = fetchAssociationsKeyedBy("SELECT clientid, active, lname, fname FROM tblclient WHERE email = '$ownerEmail'", 'clientid');
echo "<p><b>ownerEmail [{$ownerEmail}]</b><br>".choiceLinks($matches['bizName']);

echo "<hr>
<p>Manual: <input id='manualID'> <input type='button' value='Go' onclick='chooseClient(-1)'>";
?>

<script language='javascript'>
function chooseClient(clientid) {
	if(clientid == -1) clientid = document.getElementById('manualID').value;
	if(clientid == '') {
		alert('No manual ID supplied.');
		return;
	}
	if(confirm('Associate this request with clientid '+clientid+"?"))
		parent.setClientptr(clientid);
}
</script>