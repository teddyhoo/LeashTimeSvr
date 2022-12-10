<? // reports-monthly-billables.php
// Show all monthly billabes for a given month
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('month,year', $_REQUEST));

		
$pageTitle = "Monthly Billables";


$breadcrumbs = "<a href='reports.php'>Reports</a>";	
$extraHeadContent = '<style>.billables td {font-size: 1.2em;padding:5px;padding-right:10px;}</style>';
include "frame.html";


for($i=0; $i<12; $i++) $months[date('F', strtotime("$i/1/2013"))] = $i;
for($i=2008; $i<2020; $i++) $years[$i] = $i;

$month = $_REQUEST['month'] ? $_REQUEST['month'] : date('m');
$year = $_REQUEST['year'] ? $_REQUEST['year'] : date('Y');
echo "<form method='post'>";
labeledSelect('Month: ', 'month', $month, $months);
echo " ";
labeledSelect('Year: ', 'year', $year, $years);
hiddenElement('show',1);
echoButton('', 'Show', 'this.form.submit()');
echo "</form>";
if($_REQUEST['show']) {
	$monthYear = date('Y-m-d', strtotime("$year-$month-01"));
	$billables = fetchAssociations(
		"SELECT b.* , CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(', ', lname, fname) as sortname
			FROM tblbillable b
			LEFT JOIN tblclient ON clientid = b.clientptr
			WHERE monthYear = '$monthYear' AND superseded = 0
			ORDER BY sortname, billabledate");
	if(!$billables) echo "<hr>No Monthly Billables Found for ".date('F Y', strtotime($monthYear));
	else {
		foreach($billables as $i => $b) $billables[$i]['client'] =
			fauxLink("{$b['client']} ({$b['clientptr']})", "viewClient({$b['clientptr']})", $noEcho=true, $title='Edit client in a separate window');
		echo "<h2>Monthly Billables for ".date('F Y', strtotime($monthYear))." (".substr($monthYear, 0, 7).")</h2>";
		$columns = 'billableid|ID||client|Client||charge|Charge||paid|Paid||tax|Tax||billabledate|Billable Date||itemdate|Item Date';
		$columns = explodePairsLine($columns);
		tableFrom($columns, $billables, $attributes="WIDTH:100% border:1", $class='billables');
	}
}

?>
<script language=javascript>
function viewClient(id) {
  var w = window.open("",'editclient',
    'directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width=800,height=700');
  w.document.location.href='client-edit.php?tab=services&id='+id;
  if(w) w.focus();
	
}
</script>
<?
	include "frame-end.html";
