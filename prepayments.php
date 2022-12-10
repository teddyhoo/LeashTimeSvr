<? // prepayments.php -- show prepayments
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "prepayment-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";

set_time_limit(14 * 60);

// Determine access privs
$locked = locked('o-');
$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#gi');

extract($_REQUEST);

$_SESSION['billing_use_once_token'] = mt_rand();

//$pastDueDays = $_SESSION['preferences']['pastDueDays'];
$nullChoice = 'email';

$pageTitle = "Billing";

$date = $firstDay ? date('n/j/Y', strtotime($firstDay)) : ''; 
$lastdate = isset($lastDay) && $lastDay ? $lastDay :  '';
$lookahead = $lastdate && $date ? round((strtotime($lastdate) - strtotime($date)) / 86400) : null; // 24 * 60 * 60

if($_SESSION['staffuser']) { $utime = microtime(1); }
$prepayments = findPrepayments($firstDay, $lookahead, null, $includeall);
if($_SESSION['staffuser']) { screenLog("findPrepayments: [$firstDay + $lookahead] ".(microtime(1) - $utime)." sec"); }
include "frame.html";
// ***************************************************************************
/*
QUESTIONS:
1. What does the prepayment invoice show?  Packages and appointments or just packages or just a dollar amount?
2. How is a client's prepayment invoice displayed?
3. Some clients prefer email, some mail.  Deal with this as in the Invoice list page?
5. A client disappears from this page when?  When Amt Due is 0?  After first day of last prepaid package in lookahead period?

Find sum all NR package prices that are prepaid and that begin in the next $lookahead days, grouped by client ordered by client.



*/
function interpretInterval($intervalLabel) {
	$firstDayThisMonthInt = strtotime(date("Y-m-01"));
	if($intervalLabel == 'Last Month') {
		$start = shortDate(strtotime(date("Y-m-01", strtotime("-1 month", $firstDayThisMonthInt))));
		$end = shortDate(strtotime(date("Y-m-t", strtotime("-1 month", $firstDayThisMonthInt))));
		$month = date("F", strtotime("-1 month", $firstDayThisMonthInt));
	}
	else if($intervalLabel == 'Next Month') {
		$start = shortDate(strtotime(date("Y-m-01", strtotime("+1 month", $firstDayThisMonthInt))));
		$end = shortDate(strtotime(date("Y-m-t", strtotime("+1 month", $firstDayThisMonthInt))));
		$month = date("F", strtotime("+1 month", $firstDayThisMonthInt));
	}
	else if($intervalLabel == 'This Month') {
		$start = shortDate(strtotime(date("Y-m-01")));
		$end = shortDate(strtotime(date("Y-m-t")));
		$month = date("F");
	}
	else if($intervalLabel == 'Last Week') {
		$end = shortDate(strtotime("last Sunday"));
		$start = shortDate(strtotime("last Monday", strtotime($end)));
	}
	else if($intervalLabel == 'Next Week') {
		$start = shortDate(strtotime("next Monday"));
		$end = shortDate(strtotime("next Sunday", strtotime($start)));
	}
	else if($intervalLabel == 'This Week') {
		if(date('l') == "Monday") $start = shortDate();
		else $start = shortDate(strtotime("last Monday"));
		$end = shortDate(strtotime("next Sunday", strtotime($start)));
	}
	return array($start, $end, $month);
}

?>
<style>
.bluebar {width:99.85%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;
					text-align:center;font-size:1.1em;padding-top:5px;margin-bottom:2px;}
</style>
<?
//echoButton('', 'Print Selected Invoices', 'printSelectedInvoices()');
//echoButton('', 'Email Selected Invoices', 'emailSelectedInvoices()');
echo "<form name='showform'>";
echo "<span style='font-size:1.1em'>";

echo "<b>Show: </b><img src='art/spacer.gif' width=15 height=1>";
list($start, $end, $month) = interpretInterval('Last Month');
echoButton('', $month, "showInterval(\"$start\", \"$end\")");
list($start, $end, $month) = interpretInterval('This Month');
echo "<img src='art/spacer.gif' width=15 height=1> ";
echoButton('', $month, "showInterval(\"$start\", \"$end\")");
list($start, $end, $month) = interpretInterval('Next Month');
echo "<img src='art/spacer.gif' width=15 height=1> ";
echoButton('', $month, "showInterval(\"$start\", \"$end\")");
echo "<p>";





echoButton('showprepayments', 'Show', "changeLookahead()"); 
echo ' ';
calendarSet('Start Date', 'firstDay', $date);

hiddenElement('origFirstDay', date('Y-m-d', strtotime($date)));
echo ' ';

		
calendarSet('End Date', 'lastDay', $lastdate);

hiddenElement('lookahead', $lookahead);

hiddenElement('origLookahead', $lookahead);

labeledCheckbox('Include all visits', 'includeall', $includeall, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title='Include all visits of prepaid nonrecurring schedules, even visits outside the date range.');
hiddenElement('origincludeall', $includeall);

echo "</form>";
$lookaheadLastDayInt = strtotime("+ $lookahead days", strtotime($date));

//foreach($prepayments as $prepayment) if($prepayment['clientptr']==620) echo '<br>'.print_r($prepayment,1);
//echo "$firstDay, $lookahead<br>";

function lnameFnameSort($a, $b) {
	return ($cmp = strcmp(strtoupper($a['lname']), strtoupper($b['lname']))) == 0 
			? strcmp(strtoupper($a['fname']), strtoupper($b['fname'])) 
			: $cmp;
}	

uasort($prepayments, 'lnameFnameSort');

$clientids = array_keys($prepayments);					
//========================================
			
$clientIdList = join(',', $clientids);
$repeatCustomers = !$clientids ? array() : fetchCol0(
		"SELECT DISTINCT correspid 
			FROM tblmessage 
				WHERE correspid IN ($clientIdList) AND inbound = 0 AND correstable = 'tblclient' 
					AND subject like '%$prepaidInvoiceTag%'");
					
					
$credits = !$clientids ? array() 
					: fetchKeyValuePairs("SELECT clientptr, sum(amount-amountused) 
																FROM tblcredit 
																WHERE clientptr IN ($clientIdList)
																GROUP BY clientptr");
//foreach($credits as $clientid => $credit) $credits[$clientid] = dollars($credit);

$clearCCs = $_SESSION['ccenabled'] ? getClearCCs($clientids) : array();

$ccStatus = array();
$ccStatusRAW = <<<CCSTATUS
No Credit Card on file,nocc.gif,NO_CC
Card expired: #CARD#,ccexpired.gif,CC_EXPIRED
Autopay not enabled: #CARD#,ccnoautopay.gif,CC_NO_AUTOPAY
Valid card on file: #CARD#,ccvalid.gif,CC_VALID
E-check acct. on file: #CARD#,ccvalid.gif,ACH_VALID
CCSTATUS;
foreach(explode("\n", $ccStatusRAW) as $line) {
	$set = explode(",", trim($line));
	$ccStatus[$set[2]] = $set;
}

foreach($prepayments as $index => $pp) {
	$prepaymentValue = $pp['prepayment'];
	if(round($prepaymentValue*100) == 0) {
		//unset($prepayments[$index]);
		//continue;
	}
	// availableCredits is built during findPrepayments  
	$creditValue = isset($availableCredits[$pp['clientptr']]) ? $availableCredits[$pp['clientptr']] : 0.0;
	//$creditValue = isset($availableCredits[$pp['clientptr']]) ? $credits[$pp['clientptr']] : 0.0;
//if($_SESSION['staffuser'] && $pp['clientptr']==981)	screenLog("[[".isset($availableCredits[$pp['clientptr']])."]]");
	$pp['clientname'] = prepaymentClientLink($pp);
	$pp['prepaymentdollars'] = dollars($prepaymentValue);
	if($creditValue >= 0 && ($creditValue + 1 > $prepaymentValue)) { //(abs($creditValue + 1 >= $prepaymentValue) )
		$pp['prepaymentdollars'] .= "<span style='color:green;font-variant:small-caps;font-weight:bold;'> (Paid)</span>";
	}
	$pp['credits'] = dollars($creditValue);
	$creditFlag = $prepaymentValue - $creditValue < 0 ? 'cr' : '';
	$pp['tobecharged'] = max(0, $prepaymentValue - $creditValue);
	$pp['netdue'] = dollarAmount(abs($prepaymentValue - $creditValue)).$creditFlag;
	if($creditFlag) $pp['netdue'] = "<font color='green'>{$pp['netdue']}</font>";
	$pp['payment'] = paymentLink($pp['clientptr'], sprintf("%01.2f", max(0, $prepaymentValue - $creditValue))).'&nbsp;&nbsp;'.ccStatusDisplay($pp); // $prepayments[$index] is modified for autopay
	$invoiceby = $pp['invoiceby'] ? $pp['invoiceby'] : $nullChoice;
	$pp['invoicecell'] = echoButton('', 'View', "viewInvoice({$pp['clientptr']}, \"$invoiceby\", \"{$pp['email']}\")", 'SmallButton', 'SmallButtonDown', 1).
											' '.historyLink($pp['clientptr'], $repeatCustomers);
	$pp['cb'] = "<input type='checkbox' id='client_{$pp['clientptr']}' name='client_{$pp['clientptr']}'>";
											
	$prepayments[$index] = $pp;
	//$pp['accountbalance'] = getAccountBalance($pp['clientptr'], 1);
	//if($pp['accountbalance'] < 0) $pp['accountbalance'] = abs($pp['accountbalance']).'cr';
}


echo "<p><div id='prepaymentsdueby' style='padding: 3px;border: solid black 1px;'>Prepayments due by: ".date('F j', $lookaheadLastDayInt)."</div>";
echo "</span>";

//$clientDetails = getClientDetails(array_keys($actBalances), array('invoiceby'), 'sorted');
echo "<form name='allclients' method='POST' action='cc-charge-clients.php'><p>";
if($_SESSION['ccenabled']) echoButton('', 'Charge Selected Clients', 'chargeSelectedClients()');
hiddenElement("billing_use_once_token", $_SESSION['billing_use_once_token']); // mattOnlyTEST

$attributes = 'WIDTH=100%';
$showAllClients = false;
$columns = explodePairsLine('cb| ||clientname|Client||invoicecell|Invoice Status||prepaymentdollars|Payment Due||credits|Credits||netdue|Net Due||payment|Payment');

//echo "<h3 style='text-align:center;'>Prepayment Invoices to Mail</h3>";
echo "<p><div class='bluebar'>Mail Statements</div></p>";
$sortedInvoices = array();
foreach($prepayments as $pp) {
	if($pp['invoiceby'] == 'mail' || (!$pp['invoiceby'] && $nullChoice == 'mail')) {
		$sortedInvoices[$pp['clientptr']] = $pp;
	}
}
echo "<div style='position:relative;float:right;'>";
if($sortedInvoices) echoButton('','Generate & Mail Prepayment Invoices to Selected Clients','printSelectedInvoices()');
echo "</div>";
if($sortedInvoices) {
	echo fauxLink('Select All', "selectAll(\"invoicestomail\", 1)", 'Select all current invoices for printing.');
	echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1 />";
	echo fauxLink('Deselect All', "selectAll(\"invoicestomail\", 0)", 'Clear all current invoice selections.');
	echo "<p>";
}

//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
foreach((array)$sortedInvoices as $pp) 
	$rowClasses[] = $rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';

if(!$sortedInvoices) echo "There are no Prepayment Invoices to be mailed.";
else
tableFrom($columns, $sortedInvoices, $attributes, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);


// ##################### EMAIL
//echo "<h3 style='text-align:center;'>Prepayment Invoices to Email</h3>";
echo "<p><div class='bluebar'>Payments Due</div></p>";

$sortedInvoices = array();
foreach($prepayments as $pp) {
	if($pp['invoiceby'] != 'mail' || (!$pp['invoiceby'] && $nullChoice == 'email')) {
		$pp['#ROW_EXTRAS#'] = "id='clientrow_{$pp['clientptr']}'";
		
		if(!$pp['email'] && $pp['autopay']) {
			$pp['cb'] .= "<span style='color:red;font-size:0.8em;'><br>No Email</span>";
		}
		else if(!$pp['email']) $pp['cb'] = "<span style='color:red;font-size:0.8em;'>No Email</span>";
		$sortedInvoices[$pp['clientptr']] = $pp;
	}
}
echo "<div style='position:relative;float:right;'>";
// Generate & Email Prepayment Invoices to Selected Clients
if($sortedInvoices && !$readOnly) echoButton('','Email Statements to Selected Clients','emailSelectedInvoices()');
echo "</div>";
if($sortedInvoices) {
	echo fauxLink('Select All', "selectAll(\"invoicestoemail\", 1)", 'Select all current invoices for emailing.');
	echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1 />";
	echo fauxLink('Deselect All', "selectAll(\"invoicestoemail\", 0)", 'Clear all current invoice selections.');
	echo "<p>";
}

//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
foreach((array)$sortedInvoices as $pp) 
	$rowClasses[] = $rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';
if(!$sortedInvoices) echo "There are no Prepayment Invoices to be emailed.";
else
tableFrom($columns, $sortedInvoices, $attributes, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);


foreach($clientids as $clientid) {
	hiddenElement("charge_$clientid", $prepayments[$clientid]['tobecharged']);
	$status = $clearCCs[$clientid] ? ccStatus($clearCCs[$clientid]) : '';
	$status = $status ? $status[2] : 'NO_CC';
	hiddenElement("ccstatus_$clientid", $status);
}

echo "</form>";
// ***************************************************************************
include "refresh.inc";	
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
<? dumpPopCalendarJS(); ?>
setPrettynames('firstDay','Start Date', 'lastDay','End Date', 'lookahead','Lookahead');

function firstDayArg(argName) {
	argName = argName ? argName : 'firstDay';
	var mdy = document.getElementById(argName).value;
	if(mdy.indexOf('/') > 0) {
		mdy = mdy.split('/');
		return mdy[2]+'-'+mdy[0]+'-'+mdy[1];
	}
	else if (mdy.indexOf('.') > 0) {
		mdy = mdy.split('.');
		return mdy[2]+'-'+mdy[1]+'-'+mdy[0];
	}
}

function showInterval(startDate, endDate) {
	document.getElementById('firstDay').value = startDate;
	document.getElementById('lastDay').value = endDate;
	changeLookahead();
}

var windowdisabled = false;
function changeLookahead() {
	if(windowdisabled) {alert('Please wait...'); return;}
	var lastDay = document.getElementById('lastDay').value;
	var includeall = document.getElementById('includeall') != null  && document.getElementById('includeall').checked ? '1' : '';
	if(MM_validateForm(
		'firstDay','','R', 
		'firstDay','','isDate', 
		'lastDay', '', 'R', 
		'lastDay', '', 'isDate', 
		'firstDay', 'lastDay', 'datesInOrder')) {
			windowdisabled = true;
			$('#prepaymentsdueby').busy("busy");		
			//busyImage();
			document.location.href='prepayments.php?firstDay='+firstDayArg()+'&lastDay='+firstDayArg('lastDay')+'&includeall='+includeall;
	}
}

function viewInvoice(clientid, invoiceby, email) {
	var lookahead = document.getElementById('lookahead').value;
	var origincludeall = document.getElementById('origincludeall') && document.getElementById('origincludeall').value;
	var args = '&firstDay='+firstDayArg()+'&lookahead='+lookahead
								+"&invoiceby="+invoiceby+'&email='+email
								+(origincludeall ? '&includeall=1' : '');
	openConsoleWindow('invoiceview', 'prepayment-invoice-view.php?id='+clientid+args, 800, 800);
}

function viewClient(clientid) {
	openConsoleWindow('clientview', 'client-view.php?id='+clientid, 700, 500);
}

function viewRecent(clientid) {
	openConsoleWindow('recentview', 'prepayment-history-viewer.php?client='+clientid, 700, 500);
}



function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function selectAll(group, onoff) {
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].name.indexOf('client_') == 0)
			els[i].checked = onoff;
}

function printSelectedInvoices() {
	var sels = getSelections('Please select one or more invoices to print.');
	if(sels.length == 0) return;
	var lookahead = document.getElementById('lookahead').value;
	var args = '&firstDay='+firstDayArg()+'&lookahead='+lookahead;
	
	openConsoleWindow('invoiceprint', 'prepayment-invoice-print.php?ids='+sels+args, 700, 500);
}

function emailSelectedInvoices() {
	var sels = getSelections('Please select one or more invoices to email.');
<? //if(mattOnlyTEST()) echo "alert(sels);return;"; ?>
	if(sels.length == 0) return;
	var lookahead = document.getElementById('lookahead').value;
	var args = '&firstDay='+firstDayArg()+'&lookahead='+lookahead;
	ajaxGetAndCallWith('prepayment-invoice-email.php?send=1&ids='+sels+args, reportEmailSuccess, null);
}

function chargeSelectedClients() {
<? if($_SESSION['ccenabled'] &&  !merchantInfoSupplied()) {?>
	alert("Merchant credit card processing information is not set.");
	return;
<? } ?>	
	var sels = getSelections('Please select one or more clients to charge.');
	if(sels.length == 0) return;
	var successaction = escape('window.opener.document.allclients.submit();');
	openConsoleWindow("ccloginwindow", "cc-login.php?successaction="+successaction,600,500);
	//document.allclients.submit();
}

function getSelections(emptyMsg) {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') > 0 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert(emptyMsg);
		return;
	}
	return sels;
}

function reportEmailSuccess(argument, txt) {
	alert(txt);
	update();
}

function update(target, aspect) {
//alert('target: '+target+' aspect: '+aspect+' row: '+"clientrow_"+aspect);
	if(aspect > 0 && target == 'account') {
		// find a tr whose id starts with "clientrow_"+aspect
		var rowid;
		var trs = document.getElementsByTagName('tr');
//alert('rows: '+trs.length);		
		for(var i = 0; i < trs.length; i++)
			if(trs[i].id == "clientrow_"+aspect)
				rowid = trs[i].id;
//alert('rowid: '+rowid+' asOfDate: '+document.getElementById('asOfDate').value);
		ajaxGetAndCallWith("prepayment-get-info.php?id="+aspect
												+"&firstDay="+document.getElementById('origFirstDay').value
												+"&lookahead="+document.getElementById('origLookahead').value
												, updateClientRowCallback, rowid)
	}
	else refresh();
}

function updateClientRowCallback(rowid, data) {
	//alert('row: '+rowid+' data: '+data);	
	//data: acountbalance|$ 237.00|invoice|200|invoicelabel|LT0200|paid|0|throughdate|12/04/2009|currinv|$ 612.00|amountdue|$ 612.00|uninvoiced|$ 375.00|incompleteJobs|1
	// $cols = array_flip(explode(',', 'cb,clientname,acountbalance,invoice,throughdate,currinv,amountdue,uninvoiced'));
	
	//echo "cb|$cb|clientname|$clientname|invoicestatus|$invoicecell|prepayment|$prepaymentdollars|credits|$credits|payment|$payment";

	var row = document.getElementById(rowid);
	var parts = rowid.split('_');
	var client = parts[1];
	
//alert('row: '+row+' td: '+getTD(row, 'acountbalance'));
//alert(rowid+': '+document.getElementById(rowid).innerHTML);
//alert(describeRow(document.getElementById(rowid)));
//'placeholder,cb,clientname,invoicestatus,prepayment,credits,payment'.split(',');
	getTD(row, 'clientname').innerHTML = getValue(data, 'clientname');
	getTD(row, 'invoicestatus').innerHTML = getValue(data, 'invoicestatus');
	getTD(row, 'prepayment').innerHTML = getValue(data, 'prepayment');
	getTD(row, 'netdue').innerHTML = getValue(data, 'netdue');
	getTD(row, 'credits').innerHTML = getValue(data, 'credits');
	getTD(row, 'payment').innerHTML = getValue(data, 'payment');
}

function getValue(data, key) {
	var arr = data.split('|');
	for(var i=0;i < arr.length - 1; i+= 2)
		if(arr[i] == key)
			return arr[i+1];
}

function getTD(row, key) {
	var arr = 'placeholder,cb,clientname,invoicestatus,prepayment,credits,netdue,payment'.split(',');
	for(var i=0;i < arr.length; i++)
		if(arr[i] == key)
			return row.childNodes[i];
}


</script>


<?
if($_SESSION['staffuser']) { screenLog("(almost) Total page time: ".(microtime(1) - $utime)." sec"); }

include "frame-end.html";
?>


