<? // cc-recent-declined-payments.php
// INCOMPLETE
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "js-gui-fns.php";
require_once "cc-processing-fns.php";
require_once "invoice-fns.php";

/* grouped by client, show:
- recent charge failures (date and message), most recent first
- client balance
- last payment made by client
- link to last message sent to client
- link to the client account tab
- link to open a problem payment problem composer
*/

// Determine access privs
$locked = locked('o-');

extract(extractVars('bydate,csv,start', $_REQUEST));
if($id) {
	$filter = "AND ccid = $id";
	$achs = fetchAllClientACHs($id, $filter, 'display');
}
else if($currentOnly) $filter = "AND active = 1";
$achs = $achs ? $achs : array(0);
$actions = "ccpaymentadhoc|Payment||ccpayment|Payment||ccrefund|Refund||achpayment|Payment||achrefund|Refund";
$actions = explodePairsLine($actions);
$epaytypes = "ccpaymentadhoc|CC *||ccpayment|CC||ccrefund|CC||achpayment|ACH||achrefund|ACH";
$epaytypes = explodePairsLine($epaytypes);
$date = date('Y-m-d', strtotime($start ? $start : '- 14 days'));

// first find all payment errors since date
// note foramt: Declined-Over Limit|Amount:9.12|Trans:1811115581|Gate:Solveras|ErrorID:115
$messages =  fetchAssociations($sql = 
	"SELECT * FROM tblchangelog 
	 WHERE 
		time >= '$date 00:00:00'
	 	AND itemtable IN ('ccpayment', 'achpayment', 'ccpaymentadhoc')
	 	AND note NOT LIKE 'Approved%'
	 ORDER BY time DESC", 1);

//if(mattOnlyTEST()) {print_r($messages);exit;}

$users = array();
foreach($messages as $i => $m) {
	if(!in_array($m['itemtable'], array('ccpayment', 'ccpaymentadhoc', 'achpayment'))) {
		//echo print_r($m, 1).'<br>';
		//continue;
		/* Not outfitted to handle refunds!
		Array
		(
		    [itemtable] => ccrefund
		    [itemptr] => 538
		    [operation] => p
		    [user] => 28543
		    [time] => 2015-05-27 10:19:10
		    [note] => Approved-7.00|2692948133
		)
		*/
	}
	$users[] = $m['user'];
	//$cards[] = array($m['itemtable'], $m['itemptr']); // this looks wrong in light of the usage below
	$table = $m['itemtable'] == 'ccpayment' ? 'tblcreditcard' : (
					 $m['itemtable'] == 'ccpaymentadhoc' ? 'tblcreditcardadhoc' : 
					 'tblecheckacct');
	$idfield = $m['itemtable'] == 'achpayment' ? 'acctid' : 'ccid';
	
	// KLUDGE start
	if($m['itemtable'] == 'ccrefund') {
		$table = 'tblcreditcard';
		$idfield = 'ccid';
	}
	// KLUDGE end
	
	
	$client = fetchFirstAssoc(
		"SELECT clientid, CONCAT_WS(' ', fname, lname) as name , CONCAT_WS(' ', lname, fname) as sortname, $table.*
			FROM $table 
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE $idfield = {$m['itemptr']} LIMIT 1");
	if($m['itemtable'] == 'ccpayment' && !$cards[$m['itemptr']]) $cards[$m['itemptr']] = $client;  // this looks wrong
	else if($m['itemtable'] == 'ccpaymentadhoc' && !$adhoccards[$m['itemptr']]) $adhoccards[$m['itemptr']] = $client;  // this looks wrong
	else if($m['itemtable'] != 'ccpayment' && !$achs[$m['itemptr']]) $achs[$m['itemptr']] = $client;
	$messages[$i]['clientid'] = $client['clientid'];
	$messages[$i]['clientname'] = $client['name'];
	$messages[$i]['record'] = "{$m['itemtable']}/{$m['itemptr']}";
	//echo print_r($messages[$i],1);exit;
}

if(!$bydate) usort($messages, 'msgSort');

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
	 
//$columns = "time|Date / Time||action|Action||paymentptr|ID||amount|Amount||epaytype|Type||clientname|Client||acct|Account||transaction|Transaction||user|User||note||Note";
$columns = "time|Date / Time||epaytype|Type||clientname|Client||acct|Account||transaction|Transaction||user|User||note||Note";
$columns = explodePairsLine($columns);
if(!$bydate) unset($columns['clientname']);

$rows = array();
foreach($messages as $msg) {
	if(!$bydate && $msg['clientid'] != $lastClient) {
		$lastClient = $msg['clientid'];
		if($lastPayment = fetchLastPayment($lastClient)) {
			$lastPayDate = shortDate(strtotime($lastPayment['issuedate']));
			https://leashtime.com/prepayment-history-viewer.php?client=871
		}
		$accountBalance = getAccountBalance($lastClient, /*includeCredits=*/true, /*allBillables*/false);
		$color = $accountBalance <= 0 ? 'green' : 'red';
		$accountBalance = $accountBalance == 0 ? 'PAID' : ($accountBalance < 0 ? dollarAmount(abs($accountBalance)).'cr' : dollarAmount($accountBalance));
		$balanceDisplay = "Balance: <span style='color:$color;font-weight:bold;'>$accountBalance</span> ";
		$row = array('#CUSTOM_ROW#'=>
		"<tr><td colspan=4 style='font-size:1.2em;font-weight:bold;'>{$msg['clientname']} [id: {$msg['clientid']}]</td>
			<td colspan=1 style='font-size:1.1em;font-weight:regular;'>$balanceDisplay Last Payment: ($lastPayDate) ".dollarAmount($lastPayment['amount'])."</td>
		</tr>");
		$rowClasses[] = '';
		$rows[] = $row;
	}
	$row = array();
	$row['clientname'] = $msg['clientname'] ? $msg['clientname'] : $msg['record'] ;
	
	$row['time'] = shortDateAndTime(strtotime($msg['time']), 'mil');
	$row['action'] = $actions[$msg['itemtable']];
	$row['epaytype'] = $epaytypes[$msg['itemtable']];
	if($msg['itemtable'] == 'ccpayment') {
		$card = $cards[$msg['itemptr']];
		$row['acct'] = $card['company'].' '.$card['last4'];
	}
	else /*KLUDGE*/if($msg['itemtable'] == 'ccrefund') {
		$card = $cards[$msg['itemptr']];
		$row['acct'] = $card['company'].' '.$card['last4'];
	}
	else if($msg['itemtable'] == 'ccpaymentadhoc') {
		$card = $adhoccards[$msg['itemptr']];
		$row['acct'] = $card['company'].' '.$card['last4'];
	}
	else {
		$ach = $achs[$msg['itemptr']];
		$ach['acctnum'] = $ach['encrypted'] ? maskedAcctNum($ach['acctnum']) : $ach['acctnum'];

		$row['acct'] = $ach['acctnum'];
	}
	$row['user'] = $usernames[$msg['user']]['loginid'];
	$result = parseNote($msg['note']);
	$row['transaction'] = $result['transaction'];
//echo $row['transaction'];exit;
	if(in_array($m['itemtable'], array('ccpayment', 'ccpaymentadhoc')))
		$row['paymentptr'] = fetchRow0Col0("SELECT creditid FROM tblcredit WHERE externalreference = 'CC: {$row['transaction']}' LIMIT 1", 1);

	$row['amount'] = $csv ? (($row['action'] == 'Refund' ? -1 : 1) * $result['amount']) : (
										$result['amount'] ? dollarAmount($result['amount']) : '--');
	if($csv) {
		$note = $result['status'];
		if($result['title']) $note .= ": {$result['title']}";
	}
	else {
		$note = "{$result['status']} ";
		if($result['reason']) $note .= $result['reason'];
		if($result['title']) $note = "<span style='text-decoration:underline;' title='"
			.safeValue($result['title'])."' onclick='details(this)' card='{$row['acct']}' transaction='{$row['transaction']}'>$note</span>";
	}
	$row['note'] = $note;
	$rowClasses[] = $card['active'] ? 'futuretask' : 'oldcard';
	$rows[] = $row;
}

// =================================================================
$pageTitle = "Failed Electronic Payments since $date";
$breadcrumbs = "<a href='reports.php'>Reports</a>";	

if(!$csv) {
	if(!$messages) $_SESSION['frame_message'] = "No electronic transactions found.";
	$layout = $_SESSION['frameLayout'];
	$_SESSION['frameLayout'] = 'fullScreenTabletView';
	unset($_SESSION['bannerLogo']);
	require "frame.html";
	$_SESSION['frameLayout'] = $layout;
	unset($_SESSION['bannerLogo']);
	
	
	calendarSet('Starting:', 'start', ($start ? $start : date('m/d/Y', strtotime('-14 days'))));
	echo ' ';
	labeledCheckbox('Order by date', 'bydate', $bydate, $labelClass=null, $inputClass=null, 
									$onClick='go(0)');
	echo ' ';
	echoButton('', 'Submit', 'go(0)');
	echo ' ';
	echoButton('', 'Generate CSV', 'go(1)');

	echo "<style>.oldcard {background: lightgray;}</style>";
	echo "<p>Shaded transactions were made using previously registered credit cards which may or may not be the same as the currently active card.<p>";
	echo "CC * = card supplied by user for one-time use.<p>";

	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
?>
<script language='javascript'>
function details(el) {
	var transid = el.getAttribute('transaction') != '' ? 'trans ID: '+el.getAttribute('transaction')+' - ' : '';
	$.fn.colorbox(
		{html:el.getAttribute('card')+': '+transid+el.title,
		width:'650', height:'250', iframe: false, scrolling: true, opacity: '0.3'});
}
</script>
<?



	include "frame-end.html";
	?>
<script language='javascript' src='check-form.js'></script>

<script language='javascript'>
function go(csv) {
	var bydate = document.getElementById('bydate').checked ? 1 : 0;
	var start = document.getElementById('start').value;
	document.location.href="cc-recent-declined-payments.php?bydate="+bydate+"&start="+start+"&csv="+csv;
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
	header("Content-Disposition: attachment; filename=Recent-E-transactions.csv ");
	dumpCSVRow($pageTitle);
	dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
	if(!$messages) dumpCSVRow("No electronic transactions found.");
	dumpCSVRow($columns);
	foreach($rows as $row) 	dumpCSVRow($row, array_keys($columns));
}
// =================================================================

function parseNote($note) {
	$result = array();
	$parts = explode('-', $note);
	$result['status'] = $parts[0];
	$parts = explode('|', $parts[1]);
	if($result['status'] == 'Approved') {
		$result['amount'] = $parts[0];
		$result['transaction'] = $parts[1];
	}
	else {
		$result['reason'] = $parts[0];
		// Declined-This transaction has been declined.|Amount:2.00|Trans:3574472823|Gate:Authorize.net|ErrorID:172
		// 2|1|2|This transaction has been declined.|000000|U|3574472823|||2.00|CC|auth_capture||Ted|Hooban||22085 Chelsy Paige Sq|ASHBURN|VA|20148|USA|||||||||||||||||E88631C92BF364DC7FCA08CBDD03B36E|N||||||||||||XXXX5299|MasterCard||||||||||||||||
		for($i=1;$i<count($parts);$i++) {
			if(strpos($parts[$i], 'Gate:') === 0) $gateway = substr($parts[$i], strlen('Gate:'));
			else if(strpos($parts[$i], 'ErrorID:') === 0) {
				$ccErrordId = substr($parts[$i], strlen('ErrorID:'));
				if($gateway && $error = fetchRow0Col0("SELECT response FROM tblcreditcarderror WHERE errid = '$ccErrordId' LIMIT 1")) {
					if($gateway = getGatewayObject($gateway))
						$message = $gateway->ccLastMessage($error);
					if($message) $result['title'] = $message;
				}
			}
			
			else if(strpos($parts[$i], 'Trans:') === 0) $result['transaction'] = substr($parts[$i], strlen('Trans:'));
		}
	}
	return $result;
}

function fetchLastPayment($clientptr) {
	return fetchFirstAssoc("SELECT * FROM tblcredit WHERE payment = 1 AND clientptr = $clientptr ORDER BY issuedate DESC", 1);
}