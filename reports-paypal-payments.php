<? // reports-paypal-payments.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "js-gui-fns.php";
require_once "cc-processing-fns.php";


// Determine access privs
$locked = locked('o-');

extract(extractVars('byclient,csv,start', $_REQUEST));

$achs = $achs ? $achs : array(0);
$date = date('Y-m-d', strtotime($start ? $start : '- 14 days'));


// Date / Time	Action	Amount	Type	Client	Account	Transaction	User
$sort = $byclient ? 'sortname ASC' : 'issuedate ASC';
$payments = fetchAssociations(
	"SELECT p.*, CONCAT_WS(' ', c.fname, c.lname) as clientname, CONCAT_WS(' ', c.fname, c.lname) as sortname 
		FROM tblcredit p
		LEFT JOIN tblclient c ON clientid = clientptr
		WHERE payment = 1 AND issuedate >= '$date 00:00:00'
			AND sourcereference LIKE '%paypal%'
		ORDER BY $sort");

$users = array();
foreach($payments as $i => $m) {
	if($m['createdby']) $users[] = $m['createdby'];
	//$cards[] = array($m['itemtable'], $m['itemptr']); // this looks wrong in light of the usage below
}

//if($byclient) usort($messages, 'msgSort');

function msgSort($a, $b) {
	$result = strcmp($a['sortname'], $b['sortname']);
	if($result == 0) return 0 - strcmp($a['time'], $b['time']);
	return $result;
}

if($users) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$usernames = fetchAssociationsKeyedBy("SELECT userid, loginid, rights, email FROM tbluser WHERE userid IN (".join(',', $users).")", 'userid');
	list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
	include "common/init_db_petbiz.php";
}
else $usernames = array();
	 
$columns = "time|Date / Time||amount|Amount||sourcereference|Type||clientname|Client||externalreference|Transaction||user|User||reason||Note";
$columns = explodePairsLine($columns);
//if(!$bydate) unset($columns['clientname']);

$rows = array();
foreach($payments as $msg) {
	if($byclient && $msg['clientptr'] != $lastClient) {
		$lastClient = $msg['clientptr'];
		$row = array('#CUSTOM_ROW#'=>"<tr><td colspan=8 style='font-size:1.2em;font-weight:bold;'>{$msg['clientname']} [id: {$msg['clientptr']}]</td></tr>");
		$rowClasses[] = '';
		$rows[] = $row;
	}
	$row = array();
	$row['clientname'] = $msg['clientname'];
	$mtime = $msg['created'] ? $msg['created'] : $msg['issuedate'];
	$row['time'] = shortDateAndTime(strtotime($mtime), 'mil');
	$row['sourcereference'] = $msg['sourcereference'];
	$row['externalreference'] = $msg['externalreference'];
	$row['user'] = $usernames[$msg['createdby']]['loginid'];
	$amount = $msg['voidedamount'] ? $msg['voidedamount'] : $msg['amount'];
	$totalAmount += $amount;
	$row['amount'] = $csv ? $amount : dollarAmount($amount);
	$note = $msg['reason'];
	if(!$csv) {
		if($result['title']) $note = "<span style='text-decoration:underline;' title='"
			.safeValue($result['title'])."' onclick='details(this)' card='{$row['acct']}' transaction='{$row['transaction']}'>$note</span>";
	}
	$row['note'] = $note;
	//$rowClasses[] = $card['active'] ? 'futuretask' : 'oldcard';
	$rows[] = $row;
}

// =================================================================
$pageTitle = "PayPal Payments since $date";
$breadcrumbs = "<a href='reports.php'>Reports</a>";	

if(!$csv) {
	if(!$payments) $_SESSION['frame_message'] = "No electronic transactions found.";
	$layout = $_SESSION['frameLayout'];
	$_SESSION['frameLayout'] = 'fullScreenTabletView';
	unset($_SESSION['bannerLogo']);
	require "frame.html";
	$_SESSION['frameLayout'] = $layout;
	unset($_SESSION['bannerLogo']);
	
	
	calendarSet('Starting:', 'start', ($start ? $start : date('m/d/Y', strtotime('-14 days'))));
	echo ' ';
	labeledCheckbox('Order by client', 'byclient', $byclient, $labelClass=null, $inputClass=null, 
									$onClick='go(0)');
	echo ' ';
	echoButton('', 'Submit', 'go(0)');
	echo ' ';
	echoButton('', 'Generate CSV', 'go(1)');
	
	echo "<p>Total: ".dollarAmount($totalAmount);

	echo "<style>.oldcard {background: lightgray;}</style>";

	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
function details(el) {
	var transid = el.getAttribute('transaction') != '' ? 'trans ID: '+el.getAttribute('transaction')+' - ' : '';
	$.fn.colorbox(
		{html:el.getAttribute('card')+': '+transid+el.title,
		width:'650', height:'250', iframe: false, scrolling: true, opacity: '0.3'});
}
<? dumpPopCalendarJS(); ?>
</script>
<?



	include "frame-end.html";
	?>
<script language='javascript' src='check-form.js'></script>

<script language='javascript'>
function go(csv) {
	var byclient = document.getElementById('byclient').checked ? 1 : 0;
	var start = document.getElementById('start').value;
	document.location.href="reports-paypal-payments.php?byclient="+byclient+"&start="+start+"&csv="+csv;
}
<? dumpPopCalendarJS(); ?>
</script>
	<?
}
else {
	function dumpCSVRow($row, $cols=null) {
		if(!$row) echo "\n";
		if(is_array($row)) {
			if($cols) {
				$nrow = array();
				if(is_string($cols)) $cols = explode(',', $cols);
				foreach($cols as $k) $nrow[] = $row[$k];
				$row = $nrow;
			}
			echo join(',', array_map('csv',$row))."\n";
		}
		else echo csv($row)."\n";
	}

	function csv($val) {
		$val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
		$val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
		$val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
		return "\"$val\"";
	}
	
	header("Content-Type: text/csv");
	header("Content-Disposition: attachment; filename=Recent-Paypal-Payments.csv ");
	dumpCSVRow($pageTitle);
	dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
	if(!$payments) dumpCSVRow("No electronic transactions found.");
	dumpCSVRow($columns);
	foreach($rows as $row) 	dumpCSVRow($row, array_keys($columns));
}
// =================================================================

