<? // reports-crm-pipeline.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "custom-field-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType,csv', $_REQUEST));

		
$pageTitle = "CRM Pipeline";

$clients = fetchAssociations("SELECT * FROM tblclient ORDER BY fname");
$custFields = getCustomFields($activeOnly=1);
foreach($clients as $client) {
	$custVals = getClientCustomFields($client['clientid']);
	$milestones = array();
	$highest = null;
	foreach($custVals as $fname => $val)
		if($val) {
			$highest = $fname;
			$milestones[] = $custFields[$highest][0];
		}
	$row = array('name'=>$client['fname'], 'status'=>$custFields[$highest][0]);
	$rowClasses[] = ($rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask');
	$row['milestones'] = join(', ', $milestones);
	$rows[] = $row;
}
$columns = explodePairsLine('name|Client||status|Status||milestones|Milestones');
$breadcrumbs = "<a href='reports.php'>Reports</a>";	
include "frame.html";
tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses, $sortClickAction=null);
include "frame-end.html";

