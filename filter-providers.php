<? // filter-providers.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "provider-fns.php";
require_once "js-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('o-');//locked('o-'); 

extract(extractVars('start,end,status', $_REQUEST));

if($_POST) {
	$filterDescription = array();
	$filterDescription[] = ($status ? $status : 'all').' providers';
	$filter = array();
	if($start) $filter[] = "date >= '".date('Y-m-d', strtotime($start))."'";
	if($end) $filter[] = "date <= '".date('Y-m-d', strtotime($end))."'";
	if($start || $end) {
		$filterDescription[] = "with services on dates";
		if($start) $filterDescription[] = "starting ".shortDate(strtotime($start));
		if($end) $filterDescription[] = ($start ? 'and ' : '')."ending ".shortDate(strtotime($end));
	}
	$providerIds = array();
	if($filter) $providerIds = 
			fetchCol0("SELECT DISTINCT providerptr 
									FROM tblappointment 
									WHERE canceled IS NULL "
									. ($filter ? "AND ".join(' AND ', $filter) : ''));
	$statusSQL = "SELECT providerid FROM tblprovider";
	if($status) $statusSQL .= " WHERE active = ".($status == 'active' ? 1 : 0);
	$statusSQL .= " ORDER BY lname, fname";
	$providerIds = 
		$providerIds 
			? array_unique(array_intersect($providerIds,  fetchCol0($statusSQL)))
			: fetchCol0($statusSQL);
					
	$result = "<root><filter>".join(' ', $filterDescription)."</filter>"
						."<ids>".join(',', $providerIds)."</ids>"
						."<start>$start</start>"
						."<end>$start</end>"
						."<status>$status</status>"
						."</root>";
						
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('filter', \"$result\");window.close();</script>";
	exit;
}
require "frame-bannerless.php";

?>
<h2>Find Sitters</h2>
<form method='POST'>
<table>
<?
radioButtonRow('Who are:', 'status', $status, array('Active'=>'active','Inactive'=>'inactive','Either status'=>''), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
echo "<tr><td>With visits:</td><td>";
calendarSet('Starting:', 'start', $start, null, null, true, 'end');
echo "</td></tr><tr><td>&nbsp;</td><td>";
calendarSet(' and ending:', 'end', $end, null, null, true, null);
echo "</td></tr>";
echo "<tr><td colspan=2 style='padding-top:20px'>";
echoButton('', 'Find Sitters', 'document.forms[0].submit()');
echoButton('', 'Close', 'window.close()', 'closeButton', 'closeButtonDown');
echo "</td></tr>";
?>
</table>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
<?
dumpPopCalendarJS();
?>
</script>
