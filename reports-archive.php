<? // reports-archive.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "reports-archive-fns.php";
require_once "gui-fns.php";

locked('o-');

$label = $_REQUEST['label'];
$pageTitle = $_REQUEST['pageTitle'] ? $_REQUEST['pageTitle'] : "$label Reports";
$type = $_REQUEST['type'];
$id = $_REQUEST['id'];

if($_REQUEST['delete']) {
	hideArchivedReport($_REQUEST['delete']);
	$goTo = substr($_SERVER["REQUEST_URI"], 1, strpos($_SERVER["REQUEST_URI"], '&delete=')-1);
	globalRedirect($goTo);
}
if($id) {
	$report = getArchivedReport($id);
	include "frame-bannerless.php";
	if(!$report) echo "Report #$id not found.";
	else {
		$created = shortDateAndTime(strtotime($report['created']));
		$creator = getUserByID($report['createdby']);
		$creator = $creator ? "{$creator['fname']} {$creator['lname']}" : '???';
		echo "<h2>{$report['label']}</h2>Generated $created by $creator<p>";
		echo $report['body'];
	}
	exit;
}
	

$reports = getArchivedReportsOfTypeSummaries($type, $includeHidden=false);
include "frame.html";

if(!$reports) {
	if($label) echo "No $label reports were found.";
	else echo "No reports of $type were found.";
}
else {
	foreach($reports as $report) {
		$created = strtotime($report['created']);
		$table[] = 
			array(
				'created' =>shortDateAndTime($created),
				'label'=>linkForReport($report),
				'redX' => redX($report));
		}
	$columns = explodePairsLine("created|Created||label|Label||redX| ");
	tableFrom($columns, $table, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}

include "frame-end.html";

function redX($report) {
	return "<span class='warning' style='cursor:pointer;' onClick='hideReport({$report['reportid']})' title='Delete this report.'>&#10060;</span>";
}

function linkForReport($report) { // temp solution
	$creator = getUserByID($report['createdby']);
	$creator = $creator ? "{$creator['fname']} {$creator['lname']}" : '???';
	return fauxLink($report['label'], 
		"openConsoleWindow(\"archive-report-viewer\", 
													\"reports-archive.php?id={$report['reportid']}\",
													700,600)", 1, "View this report by $creator.");
}
?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function hideReport(id) {
	if(confirm('Delete this report?'))
		document.location.href="<?= substr($_SERVER["REQUEST_URI"], 1) ?>&delete="+id;
}
</script>
