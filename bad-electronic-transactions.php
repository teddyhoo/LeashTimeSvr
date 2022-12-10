<?
// bad-electronic-transactions.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "cc-processing-fns.php";
require_once "invoice-fns.php";
require_once "js-gui-fns.php";

// Determine access privs
$locked = locked('o-');

if($_GET['detail']) {
	
function getChargeErrorData($ccerrorid) {
	$error = fetchFirstAssoc("SELECT * FROM tblcreditcarderror WHERE errid = $ccerrorid LIMIT 1");
	$changeLogItemTable = $error['sourcetable'] == 'tblcreditcard' ? 'ccpayment' : 'achpayment';
	$changelogEntry = fetchFirstAssoc(
		"SELECT * 
			FROM tblchangelog 
			WHERE itemtable = '$changeLogItemTable' 
				AND note LIKE '%ErrorID:$ccerrorid%' LIMIT 1", 1);
	$source = getPaymentSourceFromChangeLog($changelogEntry);
	$data = array(
		'clientptr' => $error['clientptr'],
		'time' => $error['time']);
	$data['balance'] = getAccountBalance($error['clientptr'], /*includeCredits=*/true, /*allBillables*/false);
	foreach(parseChangeLogPaymentNote($changelogEntry['note']) as $k => $v)
		$data[$k] = $v;
	if($error['sourcetable'] == 'tblcreditcard') {
		$data['sourcetype'] = 'Charge card';
		$data['ccid'] = $error['ccptr'];
	}
	else {
		$data['sourcetype'] = 'Bank account';
		$data['acctid'] = $error['ccptr'];
	}
	foreach(clearSource($source) as $k => $v)
		$data[$k] = $v;
	return $data;
}

print_r(getChargeErrorData($_GET['detail']) );
exit;

}




extract(extractVars('starting,ending', $_REQUEST));

if(!$_POST) {
	if(!$starting) $starting = date('n/j/Y');
	if(!$ending) $ending = $starting;
}
$dbstart = date('Y-m-d', strtotime($starting));
$dbend = date('Y-m-d', strtotime($ending));

/*$errors = fetchAssociations(
	"SELECT err.*, CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(', ', lname, fname) as sortclient
		FROM tblcreditcarderror err
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE SUBSTRING(time, 1, 10) >= '$dbstart' AND SUBSTRING(time, 1, 10) <= '$dbend'
		ORDER BY time ASC, sortclient ASC");
foreach($errors as $err) {
	$table = $err['sourcetable'] == 'ccpayment' ? 'tblcreditcard' : (
					 $err['sourcetable'] == 'ccpaymentadhoc' ? 'tblcreditcardadhoc' : 
					 'tblecheckacct');
	$idfield = $err['sourcetable'] == 'achpayment' ? 'acctid' : 'ccid';
	$err['source'] = fetchFirstAssoc("SELECT * FROM $table WHERE $idfield = {$err['ccptr']} LIMIT 1");
}*/
$messages =  fetchAssociations($sql = 
	"SELECT * FROM tblchangelog 
	 WHERE 
	 	itemtable IN ('ccpayment', 'achpayment')
	 	AND SUBSTRING(time, 1, 10) >= '$dbstart' AND SUBSTRING(time, 1, 10) <= '$dbend'
	 	AND note NOT LIKE 'Approved-%'
	 ORDER BY time ASC");
foreach($messages as $i => $msg) {
	$table = $msg['itemtable'] == 'ccpayment' ? 'tblcreditcard' : 'tblecheckacct';
	$idfield = $table == 'tblecheckacct' ? 'acctid' : 'ccid';
	$source = fetchFirstAssoc(
		"SELECT $table.*, CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(', ', lname, fname) as sortclient, userid as clientuserid
		FROM $table
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE $idfield = {$msg['itemptr']} LIMIT 1");
	$row['time'] = shortDateAndTime(strtotime($msg['time']));
	$row['date'] = "<span title='{$row['time']}'>".shortDate(strtotime($msg['time']))."</span>";
	$row['paymenttype'] = $source['ccid'] ? 'CC' : 'ACH';
	
	$row['account'] = clearSourceDescription($source);
	
	
	$row['client'] = $source['client'];
	
	$details = parseChangeLogPaymentNote($msg['note']);
	$transactionAmount = $details['amount'];
	if($transactionAmount && strlen($transactionAmount) == 12) $transactionAmount = number_format($transactionAmount/100,2);
	$row['amount'] = $transactionAmount == '' ? '?' : $transactionAmount; 
	//$row['amount'] = $details['amount'] ? $details['amount'] : '?';
	$row['ccerrorid'] = $details['errorid'];
	$row['checkbox'] = "<input type='checkbox' $inputClass id='error_{$details['errorid']}' name='error_{$details['errorid']}' $checked $onBlur>";
	$row['balance'] = number_format(getAccountBalance($source['clientptr'], /*includeCredits=*/true, /*allBillables*/false), 2);
	$comms = "prepayment-history-viewer.php?client={$source['clientptr']}";
	$row['comms'] = fauxLink("<span style='font-variant:small-caps'>[Log]</span>",
		"$.fn.colorbox({href:\"$comms\", width:\"700\", height:\"700\", iframe: true, scrolling: true, opacity: \"0.3\"});",
		$noEcho=true, $title='Show recent emails');
	$row['clientattempt'] = $source['clientuserid'] == $msg['userptr'];
	$action = "openConsoleWindow(\"ccwarning\", \"bad-electronic-transactions-email.php?id={$details['errorid']}\",750,650)";
	$errorMessage = $details['title'];
	if($row['paymenttype'] == 'ACH') $errorMessage = "<span style='color:red;font-variant:small-caps'>[ACH] </span>$errorMessage";
	$row['details'] = fauxLink($errorMessage, "openConsoleWindow(\"ccwarning\", \"bad-electronic-transactions-email.php?id={$details['errorid']}\",750,650)", 1, 'Click to compose email.');// "<a target='xbasasdsd' onclick='$action'>{$details['title']}</a>";
	$rows[$i] = $row;
}


$pageTitle = "Failed Electronic Payments";
$extraHeadContent = "
<script language='javascript' src='common.js'></script>
<style>
.markThis {font-size:1.1em;}
.highlighted {background-color:yellow;}
.leftpad {padding-left:10px;}
</style>
";

include "frame.html";
?>
<form name='ccerrorsform' method='POST'>
<?
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
hiddenElement('action', $value=null);
echoButton(null, 'Show', 'showErrors()');
?>
</form>
<p class='tiplooks'>You can double-click a row to mark it, perhaps after you have sent an email.  Click an error to compose a message.</p>
<?
$cols = array(/*'checkbox'=>'',*/ 'date'=>'Date', 'client'=>'Client','amount'=>'Charge', 'balance'=>'Bal', 'comms'=>'Email', 'details'=>'Error');
foreach(explode(',', 'client,date,amount,balance') as $c) $colClasses[$c] = 'markThis';
foreach(explode(',', 'balance,amount,details') as $c) $colClasses[$c] .= ' leftpad';
if($rows)
	tableFrom($cols, $data=$rows, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
else
	echo "<p class='fontSize1_1em'>No failed payments found for date range.</p>";
//quickTable($rows);
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function showErrors() {
	if(!checkForm()) return;
	document.getElementById('action').value='show';
	document.ccerrorsform.submit();
}
function checkForm() {
	//if(scheduleIsIncomplete()) return;
	//return;

	if(!MM_validateForm('client', '', 'R',
		  'starting', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'R',
		  'ending', '', 'isDate',
		  'starting', 'ending', 'datesInOrder'
		  ))
		  return false;
	return true;
}
<? dumpPopCalendarJS(); ?>
$('.markThis').dblclick(function(ev) {
	//alert($(ev.currentTarget).parent()[0]+': '+$(ev.currentTarget).parent().hasClass('highlighted'));
	$(ev.currentTarget).parent().toggleClass('highlighted');
	
	});
</script>
<img src='art/spacer.gif' height=200>
<?
include "frame-end.html";

