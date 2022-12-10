<? // billingBETA2015.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "client-flag-fns.php";
require_once "cc-processing-fns.php";
require_once "billing-fns.php";
require_once "billing-fns-pu.php";

set_time_limit(5 * 60);

define('SCRIPTNAME', substr($_SERVER["SCRIPT_NAME"], 1)); //


// Determine access privs
$locked = locked('o-');
$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#gi');

$omitRevenue = fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = {$_SESSION["auth_user_id"]} AND property = 'suppressRevenueDisplay' LIMIT 1");
//getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay');

extract($_REQUEST);

$goMinilog = mattOnlyTEST();

$slideTest = mattOnlyTEST() || dbTEST('tonkapetsitters');
//$pastDueDays = $_SESSION['preferences']['pastDueDays'];
$ccStatus = array();
$ccStatusRAW = <<<CCSTATUS
No Valid Primary Credit Card or E-check acct. on file,nocc.gif,NO_CC
Card expired: #CARD#,ccexpired.gif,CC_EXPIRED
Autopay not enabled: #CARD#,ccnoautopay.gif,CC_NO_AUTOPAY
Valid card on file: #CARD#,ccvalid.gif,CC_VALID
E-check acct. on file: #CARD#,ccvalid.gif,ACH_VALID
CCSTATUS;
foreach(explode("\n", $ccStatusRAW) as $line) {
	$set = explode(",", trim($line));
	$ccStatus[$set[2]] = $set;
}
$sections = array();
if(isset($firstDay_recurring)) {
	foreach(array('recurring', 'nonrecurring', 'monthly') as $sct) {
		$sections[$sct]['firstDay'] = date('n/j/Y', strtotime($_REQUEST["firstDay_$sct"]));
		$sections[$sct]['lastDay'] = date('n/j/Y', strtotime($_REQUEST["lastDay_$sct"]));
		$sections[$sct]['lookahead'] = round((strtotime($_REQUEST["lastDay_$sct"]) - strtotime($_REQUEST["firstDay_$sct"])) / 86400);
		$sections[$sct]['lookaheadLastDayInt'] = 
			strtotime("+ {$sections[$sct]['lookahead']} days", strtotime($sections[$sct]['firstDay']));
	}
}
else {
	$date = $firstDay ? date('n/j/Y', strtotime($firstDay)) : ''; //date('n/j/Y'); 
	$lastdate = isset($lastDay) && $lastDay ? $lastDay : ''; //date('Y-m-d', strtotime('+ 6 days', strtotime($date)));
	if($lastdate) {
		$lookahead = round((strtotime($lastdate) - strtotime($date)) / 86400); // 24 * 60 * 60
		$lookaheadLastDayInt = strtotime("+ $lookahead days", strtotime($date));
	}
	foreach(array('recurring', 'nonrecurring', 'monthly') as $sct) {
		$sections[$sct]['firstDay'] = $date;
		$sections[$sct]['lastDay'] = $lastdate;
		$sections[$sct]['lookahead'] = $lookahead;
		$sections[$sct]['lookaheadLastDayInt'] = $lookaheadLastDayInt;
	}
}
if($clientupdate) {
	// return XML:
	// if prepayment remains, return new values to be displayed
	$recurring = isClientRecurring($clientupdate); // null OR 'monthly'OR 'recurring'
	$section = $recurring ? $recurring : 'nonrecurring';
	$lookaheadLastDayInt = $sections[$section]['lookaheadLastDayInt'];
	$prepayments = findBillingTotalsWithPriorUnpaids(
			($firstDay = $sections[$section]['firstDay']), 
			($lookahead = $sections[$section]['lookahead']), 
			array($clientupdate), 
			$recurring, 
			$literal);
	//global $prepaidInvoiceTag, $repeatCustomers, $date, $lastdate, $lookahead, $lookaheadLastDayInt, $availableCredits, $clearCCs;
	//$columns = explodePairsLine('cb| ||clientname|Client||invoicecell|Invoice Status||netdue|Net Due||payment|Payment'); // ||prepaymentdollars|Payment Due||credits|Credits
	//if($goMinilog) $test = print_r("findBillingTotals($firstDay,$lookahead,array($clientupdate),$recurring,$literal)",1);
	if($test) $test = "<test>$test</test>";
	$prepayments = (array)$prepayments;
	$clientids = array_keys($prepayments);
	$repeatCustomers = getRepeatCustomers($clientids);
	
	$availableCredits[$clientupdate] = getUnusedClientCreditTotal($clientupdate);
	$clearCCs= $_SESSION['ccenabled'] ? getClearCCs($clientids) : array();
	completePrepaymentsArray($prepayments, $repeatCustomers, $recurring, $section);
	
	if($prepayments && ($row = $prepayments[$clientupdate]) && $row['netdueraw'] > 0) {
		// construct return
		//$row = $prepayments[$clientupdate];
		$newpayamount = sprintf("%01.2f", max(0, $row['netdueraw']));
		//print_r($row);
	}
	else {
		$row['netduedollars'] = 'PAID';
		$newpayamount = 'NOPAYLINK';
	}
	if($lastDate = statementLastSent($clientupdate, array($clientupdate)))
			$viewRecent = $lastDate ? shortDate(strtotime($lastDate)) : 'History';
	echo "<update><clientid>$clientupdate</clientid><section>$section</section>"
				."<netdue><![CDATA[{$row['netduedollars']}]]></netdue><newpayamount>$newpayamount</newpayamount>"
				."<viewRecent>$viewRecent</viewRecent>$test</update>";
	exit;
}

//if($goMinilog) {echo "is_float($lookahead): ".(is_float($lookahead))."<p>";print_r($_REQUEST);exit;}
if($section && (is_float($lookahead) || $lookahead)) {
	$recurring = in_array($section, array('monthly', 'recurring'));
	if($recurring) $clientids = findCurrentRecurringClients($section == 'monthly');
	else {
		$clientids = array_merge(findCurrentRecurringClients(1), findCurrentRecurringClients(0));
		
		$sql = "SELECT clientid 
					FROM tblclient "
					.($clientids ? "WHERE clientid NOT IN (".join(',', $clientids).")" : '');
		$clientids = fetchCol0($sql);
	}
$utime = microtime(1);

if($goMinilog) { $minilog[] = "<b>START : ".date('Y-m-d H:i:s')."<br>"; }
if($goMinilog) { $minilog[] = "<b>Found : ".count($clientids).' '.($recurring ? '' : 'non-')."recurring clients in ".(sprintf('%0.6f', microtime(1) - $utime)).' seconds.<br>'; $btime = microtime(1);}
//echo( "findBillingTotals($firstDay, $lookahead, "./*join(', ', $clientids)*/count($clientids).", $recurring, $literal)<p>");	
	$prepayments = findBillingTotalsWithPriorUnpaids($firstDay, $lookahead, $clientids, $recurring, $literal);
if($goMinilog) { $minilog[] = "<b>DEBORAH BAKER : ".print_r($prepayments[20485], 1)."<br>"; }
	
//if($goMinilog) if(TRUE) { ksort($prepayments); echo "XXX<hr><p>\n".print_r($prepayments,1);exit;}
	
//if($goMinilog) {echo "findBillingTotals($firstDay, $lookahead, $clientids, $recurring, $literal);";exit;}	
//findBillingTotals(2012-11-01, 29, Array, , );
if($goMinilog) { $minilog[] = "<b>It took ".(sprintf('%0.6f', microtime(1) - $btime)).' seconds to calculate totals.<br>'; $utime = microtime(1);}

	if($csv) dumpPrepaymentsCSV($prepayments, $section != 'nonrecurring', $section, $literal);
	else dumpPrepaymentsTable($prepayments, $section != 'nonrecurring', $section, $literal, !$hidezerodue);
//if($goMinilog) echo  "client 905: ".print_r($prepayments[905],1);	
if($goMinilog) { $minilog[] = "<b>Dumped table in ".(sprintf('%0.6f', microtime(1) - $utime)).' seconds.<br>'; $utime = microtime(1);}
if($goMinilog) echo join('', $minilog);
if($goMinilog) { $minilog[] = "<b>END : ".date('Y-m-d H:i:s'); }
	exit;
}

//$pageTitle = "Billing";

if($goMinilog) { $utime = microtime(1); }
if($goMinilog) { screenLog("findPrepayments: [$firstDay + $lookahead] ".(microtime(1) - $utime)." sec"); }
if(staffOnlyTEST()) $breadcrumbs = "<a href='https://leashtime.com/bad-electronic-transactions.php'>Failed Payments Report</a>";

$extraHeadContent = '<script type="text/javascript" src="jquery.tablesorter.min.js"></script>';
include "frame.html";
?>
<style>
.filterSelected {border:solid lightblue 6px;}
.filterUnselected {border:solid white 6px;}
.bluebar {width:99.85%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;
					text-align:center;font-size:1.1em;padding-top:5px;margin-bottom:2px;}
.header {text-decoration: underline;  cursor: pointer;}

</style>
<?

if($slideTest) echo "<div style='padding-left:40px;'>"; // for slideout

//$billFlagFilePattern = "art/billflag-#INDEX#.jpg";
//$allClientsFlag = "art/billflag-all.jpg";
if(TRUE || useBlockBillingFlags()) {
	$billFlagFilePattern = "art/billing-block/billflag-#INDEX#.png";
	$allClientsFlag = "art/billing-block/billing-all.png";
}
if(staffOnlyTEST() /* BILLFLAGCHOOSER */) $billingFlagList = getBillingFlagList();

echo "<table width=100%><tr><td class='h2' style='width:100%;'>Billing</td><td class='flagbutton fontSize1_3em' style='display:none;'>Show:</td>";
$class = $flagFilter ? 'filterUnselected' : 'filterSelected';
echo "<td><img id='allflags' src='$allClientsFlag' class='$class filter flagbutton' style='display:none;' onclick='filterClick(this)' title='Show all clients.'></td>";
for($i=1; $i <= $maxBillingFlags; $i++) {
	$legend = $_SESSION['preferences']["billing_flag_$i"];
	$legend = $legend ? substr($legend, strpos($legend, '|')+1) : "Show all clients with this billing flag.";
	$legend = $legend ? $legend : "Show all clients with this billing flag.";;
	$class = $flagFilter == $i ? 'filterSelected' : 'filterUnselected';
	$billFlagFile = str_replace('#INDEX#', $i, $billFlagFilePattern);
	if($billingFlagList) $billFlagFile = $billingFlagList[$i]['src'];
	echo "<td><img id='flag$i' src='$billFlagFile' class='$class filter flagbutton' style='display:none;' onclick='filterClick(this)' title='$legend'></td>";
}
echo "</tr></table>";
// ***************************************************************************
/*
QUESTIONS:
1. What does the prepayment invoice show?  Packages and appointments or just packages or just a dollar amount?
2. How is a client's prepayment invoice displayed?
3. Some clients prefer email, some mail.  Deal with this as in the Invoice list page?
5. A client disappears from this page when?  When Amt Due is 0?  After first day of last prepaid package in lookahead period?

Find sum all NR package prices that are prepaid and that begin in the next $lookahead days, grouped by client ordered by client.
*/

//echoButton('', 'Print Selected Invoices', 'printSelectedInvoices()');
//echoButton('', 'Email Selected Invoices', 'emailSelectedInvoices()');

//foreach($prepayments as $prepayment) if($prepayment['clientptr']==620) echo '<br>'.print_r($prepayment,1);
//echo "$firstDay, $lookahead<br>";

function lnameFnameSort($a, $b) {
	return ($cmp = strcmp(strtoupper($a['lname']), strtoupper($b['lname']))) == 0 
			? strcmp(strtoupper($a['fname']), strtoupper($b['fname'])) 
			: $cmp;
}	

function fnameLnameSort($a, $b) {
	return ($cmp = strcmp(strtoupper($a['fname']), strtoupper($b['fname']))) == 0 
			? strcmp(strtoupper($a['lname']), strtoupper($b['lname'])) 
			: $cmp;
}	

echo "</span>";

//========================================
// MONTHLY
$Mclientids = findCurrentRecurringClients('monthly');
//echo print_r($Rclientids,1).'<p>';

		$sections['monthly']['firstDay'] = $date;
		$sections['monthly']['lastDay'] = $lastdate;
		$sections['monthly']['lookahead'] = $lookahead;
		$sections['monthly']['lookaheadLastDayInt'] = $lookaheadLastDayInt;


if($Mclientids) {
	echo "<p><div class='bluebar'>Fixed Price Monthly Schedule Clients</div></p>";
	if($lookahead) $prepayments = findBillingTotalsWithPriorUnpaids($sections['monthly']['firstDay'], $sections['monthly']['lookahead'], $Mclientids, 'monthly', $literal);

	echo "<div id='monthly'>";
	dumpPrepaymentsTable($prepayments, 'recurring', 'monthly', $literal, !$hidezerodue);
	echo "</div>";
}
//========================================
// RECURRING
$Rclientids = findCurrentRecurringClients(!'monthly');
if($Rclientids) {
	echo "<p><div class='bluebar'>Regular Per-Visit Recurring Schedule Clients</div></p>";
	//echo print_r($Rclientids,1).'<p>';
	if($lookahead) $prepayments = findBillingTotalsWithPriorUnpaids($sections['recurring']['firstDay'], $sections['recurring']['lookahead'], $Rclientids, 'recurring', $literal);

	echo "<div id='recurring'>";
	dumpPrepaymentsTable($prepayments, 'recurring', 'recurring', $literal, !$hidezerodue);
	echo "</div>";
}
foreach($Mclientids as $id) $Rclientids[] = $id;

//========================================
// NON RECURRING
$sql = "SELECT clientid 
				FROM tblclient "
				.($Rclientids ? "WHERE clientid NOT IN (".join(',', $Rclientids).") ORDER BY clientid" : '');
$NRclientids = fetchCol0($sql);
if($NRclientids) {
	if(FALSE && !$firstDay) {
		$date = date('n/1/Y'); 
		$lastdate = date('n/t/Y');
		$lookahead = round((strtotime($lastdate) - strtotime($date)) / 86400); // 24 * 60 * 60
	}
	echo "<p><div class='bluebar'>Non-Recurring Schedule Clients</div></p>";
	if($lookahead) $prepayments = findBillingTotalsWithPriorUnpaids($sections['nonrecurring']['firstDay'], $sections['nonrecurring']['lookahead'], $NRclientids, !'recurring', $literal);
if($goMinilog) screenLog( "findBillingTotals($firstDay, $lookahead, "./*join(', ', $NRclientids)*/count($NRclientids).", !'recurring', $literal)");	
	//echo print_r(array_keys($prepayments),1).'<p>';
	echo "<div id='nonrecurring'>";
	dumpPrepaymentsTable($prepayments, !'recurring', 'nonrecurring', $literal, !$hidezerodue);
	echo "</div>";
}
//========================================
function dumpPrepaymentsCSV($prepayments, $recurring, $section, $literal) {
	global $prepaidInvoiceTag, $repeatCustomers, $date, $lastdate, $lookahead, $lookaheadLastDayInt, $availableCredits, $clearCCs;
	$prepayments = (array)$prepayments;
	$clientids = array_keys($prepayments);
	if($clientids) {
		$idString = join(',', $clientids);
		$repeatCustomers = fetchCol0(
			"SELECT DISTINCT correspid 
				FROM tblmessage 
					WHERE correspid IN ($idString) AND inbound = 0 AND correstable = 'tblclient' 
							AND ".statementSubjectPattern());
						//AND subject like '%$prepaidInvoiceTag%'");
		$repeatCustomers = 
			array_merge((array)$repeatCustomers,
									fetchCol0("SELECT DISTINCT clientptr FROM tblcredit WHERE payment = 1 AND voided IS NULL AND clientptr IN ($idString)"));
		$repeatCustomers = 
			array_merge((array)$repeatCustomers, fetchCol0("SELECT DISTINCT clientptr FROM tblrefund WHERE clientptr IN ($idString)"));
		$repeatCustomers = 
			array_merge((array)$repeatCustomers, fetchCol0("SELECT DISTINCT clientptr FROM tblothercharge WHERE clientptr IN ($idString)"));
		$repeatCustomers = array_unique($repeatCustomers);
	}
	else $repeatCustomers = array();
	
	foreach($clientids as $clientid) $availableCredits[$clientid] = getUnusedClientCreditTotal($clientid);
	$clearCCs= $_SESSION['ccenabled'] ? getClearCCs($clientids) : array();
	foreach($prepayments as $pp) {
		$sumNetDue += $pp['prepayment'];
		//$sumCredits += $pp['paid'];
		$creditValue = isset($availableCredits[$pp['clientid']]) ? max(0.0, $availableCredits[$pp['clientid']] - $pp['owedprior']) : 0.0;
		$sumCredits += $creditValue + $pp['paid'];		
	}
	completePrepaymentsArray($prepayments, $repeatCustomers, $recurring, $section, $csv=1);
//if($goMinilog) echo "$section: ".print_r($prepayments,1).'<p>';
//print_r($prepayments);
	uasort($prepayments, 'lnameFnameSort');


	$clientIdList = join(',', $clientids);

	$columns = explodePairsLine('clientname|Client||invoicecell|Invoice Last Sent||netdue|Net Due'); // ||prepaymentdollars|Payment Due||credits|Credits

	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Billing-$section.csv ");
	dumpCSVRow("Billing Summary - $section ".($literal ? '[Literal]' : '[Not Literal]'));
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $date - $lastdate");
	echo ($lookaheadLastDayInt ? "Payments due by: ".date('F j', $lookaheadLastDayInt) : '')."\n";
	echo "Total Credits: $".sprintf("%02f", $sumCredits)."\n";
	dumpCSVRow("");
	dumpCSVRow(array('','', dollarAmount($paymentsDue)));
	dumpCSVRow($columns);														  	
	if(!$prepayments && $lookahead) echo "There are no $section clients to deal with in the time frame specified.";
	else {
		foreach($prepayments as $row)
			dumpCSVRow($row, array_keys($columns));
	}
}

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


function getRepeatCustomers($clientids) {
	global $prepaidInvoiceTag;
	if($clientids) {
		$idString = join(',', $clientids);
		$repeatCustomers = fetchCol0(
			"SELECT DISTINCT correspid 
				FROM tblmessage 
					WHERE correspid IN ($idString) AND inbound = 0 AND correstable = 'tblclient' 
						AND ".statementSubjectPattern());
					//AND subject like '%$prepaidInvoiceTag%'");
		$repeatCustomers = 
			array_merge((array)$repeatCustomers,
									fetchCol0("SELECT DISTINCT clientptr FROM tblcredit WHERE payment = 1 AND voided IS NULL AND clientptr IN ($idString)"));
		$repeatCustomers = 
			array_merge((array)$repeatCustomers, fetchCol0("SELECT DISTINCT clientptr FROM tblrefund WHERE clientptr IN ($idString)"));
		$repeatCustomers = 
			array_merge((array)$repeatCustomers, fetchCol0("SELECT DISTINCT clientptr FROM tblothercharge WHERE clientptr IN ($idString)"));
		$repeatCustomers = array_unique($repeatCustomers);
	}
	else $repeatCustomers = array();
	return $repeatCustomers;
}

function dumpPrepaymentsTable(&$prepayments, $recurring, $section, $literal, $showZeroNetDue=true) {
	global $prepaidInvoiceTag, $repeatCustomers, $date, $lastdate, $lookahead, $lookaheadLastDayInt, 
					$availableCredits, $clearCCs, $globalPriorUnpaidToggleNeeded;
	global $goMinilog, $minilog;
	$prepayments = (array)$prepayments;
	$clientids = array_keys($prepayments);
	
	
	
if($goMinilog) {$t0 = $utime = microtime(1);}
	$repeatCustomers = getRepeatCustomers($clientids);
//if($goMinilog) {$minilog[] = "getRepeatCustomers (found ".count($repeatCustomers)."): ".(microtime(1) - $t0)." secs.<br>"; $utime = microtime(1); }
	
	foreach($clientids as $clientid) $availableCredits[$clientid] = getUnusedClientCreditTotal($clientid);
//if($goMinilog) {$minilog[] = "getUnusedClientCreditTotal (for ".count($clientids)." clients): ".(microtime(1) - $utime)." secs.<br>"; $utime = microtime(1);}
	$clearCCs= $_SESSION['ccenabled'] ? getClearCCs($clientids, 1) : array();
//if($goMinilog) {$minilog[] = "getClearCCs (for ".count($clientids)." clients): ".(microtime(1) - $utime)." secs.<br>"; $utime = microtime(1);}
//if($goMinilog) {$minilog[] = "interim: ".(microtime(1) - $utime)." secs.<br>"; $utime = microtime(1);}
	completePrepaymentsArray($prepayments, $repeatCustomers, $recurring, $section);
	$columns = explodePairsLine('cb| ||payment|Payment||clientname|Client||flags|Flags||invoicecell|History||netdue|Net Due'); // ||prepaymentdollars|Payment Due||credits|Credits
	if($globalPriorUnpaidToggleNeeded) { // set in completePrepaymentsArray
		$columns['netdue'] = "<img src='art/redgreen12.png' onclick='handlePriorUnpaidGlobally(event)' title='Include or Exclude Prior Unpaids for all.'> {$columns['netdue']}";
	}
//if($goMinilog) {$minilog[] = "completePrepaymentsArray : ".(microtime(1) - $utime)." secs.<br>"; $utime = microtime(1);}
//if($goMinilog) echo "$section: ".print_r($prepayments,1).'<p>';
//$showZeroNetDue = !$goMinilog;	
	if(!$showZeroNetDue) {
		foreach($prepayments as $i => $pp) {
//if(mattOnlyTEST()) echo print_r($pp, 1).'<br>';
//if(mattOnlyTEST()) echo "{$pp['fname']} {$pp['lname']} {$pp['prepayment']} &lt;= {$pp['creditvalue']}<hr>";
			$shouldBeHidden = TRUE ? ($pp['netdueraw'] <= 0) : ($pp['prepayment'] <= $pp['creditvalue']);
			//if($pp['prepayment'] <= $pp['creditvalue'])
			if($shouldBeHidden)
				unset($prepayments[$i]);
			/*else {
				$sumNetDue += ($pp['prepayment']);
				$sumCredits += $pp['creditvalue'];		
			}*/
		}
	}
	foreach($prepayments as $pp) {
		$sumNetDue += $pp['prepayment'];
		//$sumCredits += $pp['paid'];
		$creditValue = isset($availableCredits[$pp['clientid']]) ? max(0.0, $availableCredits[$pp['clientid']] - $pp['owedprior']) : 0.0;
		$sumCredits += $creditValue + $pp['paid'];		
	}
//if($goMinilog) print_r($prepayments);
	//$sumNetDue = $omitRevenue ? '' : ($sumNetDue ? 'Total Due: '.dollarAmount($sumNetDue) : '');
	//$sumCredits = $omitRevenue ? '' : ($sumCredits ? 'Total Credits: '.dollarAmount($sumCredits) : '');
	$sumNetDue = $sumNetDue ? 'Total Due: '.dollarAmount($sumNetDue) : '';
	$sumCredits = $sumCredits ? 'Total Credits: '.dollarAmount($sumCredits) : '';

	$sortFn = dbTEST('leashtimecustomers') ? 'fnameLnameSort' : 'lnameFnameSort';
	uasort($prepayments, $sortFn);

	foreach($prepayments as $pp) 
		$rowClasses[] = $rowClass = $rowClass == 'clientrow futuretask' ? 'clientrow futuretaskEVEN' : 'clientrow futuretask';

	$clientIdList = join(',', $clientids);

	echo "<form name='showform_$section'>";	
	
	echo "<table><tr><td style='font-size:1.1em'>";
	//echo "<span style='font-size:1.1em'>";
	calendarSet('Start Date', "firstDay_$section", $date);

	hiddenElement("origFirstDay_$section", date('Y-m-d', strtotime($date)));
	echo ' ';


	calendarSet('End Date', "lastDay_$section", $lastdate);
	echo ' ';
	echoButton('showprepayments_$section', 'Show', "changeLookahead(\"$section\", this)"); 
	echo "<img src='art/spacer.gif' width=15 height=1> ";

	$lastMonthValues = interpretInterval('Last Month');
	echoButton('', $lastMonthValues[2], "showInterval(\"$section\", this, \"{$lastMonthValues[0]}\", \"{$lastMonthValues[1]}\")");
	echo "<img src='art/spacer.gif' width=10 height=1> ";
	$thisMonthValues = interpretInterval('This Month');
	echoButton('', $thisMonthValues[2], "showInterval(\"$section\", this, \"{$thisMonthValues[0]}\", \"{$thisMonthValues[1]}\")");
	echo "<img src='art/spacer.gif' width=10 height=1> ";
	$nextMonthValues = interpretInterval('Next Month');
	echoButton('', $nextMonthValues[2], "showInterval(\"$section\", this, \"{$nextMonthValues[0]}\", \"{$nextMonthValues[1]}\")");
	//echo "<img src='art/spacer.gif' width=20 height=1> ";

	echo "<td><td style='font-size:1.0em'>";
	labeledCheckbox('Literal', "literal_$section", $literal, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, 
									$title='Include only visits and charges from Start Date to End Date.');
	if(TRUE || $goMinilog)	{
		echo "<br>";
		labeledCheckbox('Hide zero due', "hidezerodue_$section", !$showZeroNetDue, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, 
										$title='Hide clients who owe nothing..');
	}
	echo "</td></tr></table>";
	if($goMinilog && dbTEST('leashtimecustomers')) fauxLink('dumpy', "dumpIt(\"$section\")");


	hiddenElement("lookahead_$section", $lookahead);
	hiddenElement("origLookahead_$section", $lookahead);
	hiddenElement("section", $section);

	hiddenElement("originalliteral_$section", $literal);
	$paymentsDue = $lookaheadLastDayInt ? "Payments due by: ".date('F j', $lookaheadLastDayInt) : '';
	echo "<p><table id='prepaymentsdueby_$section' style='padding: 3px;border: solid black 1px;width:100%'>
	<tr><td>$paymentsDue</td><td id='sumNetDue_$section'>$sumNetDue</td><td id='sumCredits_$section'>$sumCredits</td></tr></table>";
	echo "</span>";
	echo "</form>";
	$incompleteLastDate = $literal ? $lastdate : date('Y-m-d');
	if($incompleteLastDate) $incompleteLastDate = date('Y-m-d', min(strtotime(date('Y-m-d')), strtotime($incompleteLastDate)));
	$incompleteStartDate = $literal ? $date : null;
	if($incompleteStartDate) $incompleteStartDate = date('Y-m-d', min(strtotime(date('Y-m-d')), strtotime($incompleteStartDate)));
	$incompleteVisitsResultSet = findIncompleteJobsResultSet($incompleteStartDate, $incompleteLastDate);
	if(($resultSize = mysql_num_rows($incompleteVisitsResultSet)) > 0) {
		$dateRange = "incompleteend=$incompleteLastDate";
		$dateRange .= $incompleteStartDate ? "&incompletestart=$incompleteStartDate" : "&showIncomplete=days60";
		$dateRange = "showIncomplete=1&$dateRange";
		$literalDateRange = $literal ? ' in this date range' : '';
		$incompleteLink =	fauxLink('here', '$(document).ready(function(){$.fn.colorbox({href:"incomplete-appts-lightbox.php?'.$dateRange.'", width:"750", height:"470", scrolling: true, opacity: "0.3", iframe: "true"});});',
								true);
		echo "<p align='center'>There are currently ".$resultSize." incomplete jobs$literalDateRange.  Click $incompleteLink to view them.</p>";
	}


	//$clientDetails = getClientDetails(array_keys($actBalances), array('invoiceby'), 'sorted');
	global $slideTest;
	$target = $slideTest ? "target='cc-charge-iframe' action='cc-charge-clients.php?iframe=1'" : "action='cc-charge-clients.php'";
	echo "<form name='charge_$section' method='POST' $target><p>";
	
	if($goMinilog) { // #SENDINVOICES
		hiddenElement('section', $section);  // WARNING: this name/id is not unique on the page
		hiddenElement('firstDay', '');  // WARNING: this name/id is not unique on the page
		hiddenElement('lookahead', '');  // WARNING: this name/id is not unique on the page
		hiddenElement('literal', '');  // WARNING: this name/id is not unique on the page
	}	
	if($prepayments) {
		if($_SESSION['ccenabled']) echoButton('', 'Charge Selected Clients', "chargeSelectedClients(\"$section\", this)");
		$group = $recurring ? $section : 'nonrecurring';
		echo ' ';
		echo fauxLink('Select All', "selectAll(\"$group\", 1)", 'Select all current invoices for printing.');
		echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1 />";
		echo fauxLink('Deselect All', "selectAll(\"$group\", 0)", 'Clear all current invoice selections.');
		echo ' ';
		if(!$readOnly) echoButton('','Email Statements to Selected Clients',"emailSelectedInvoices(\"$section\")");
		echo " <span id='stats_$section'>".count($prepayments)." clients found.</span>";
		echo "<p>";
		if($_SESSION['preferences']['enableBilling2EmailTemplates']) {
			$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email'");
			if($template) $clientEmailTemplates['Invoice Email'] = 0;
			else $clientEmailTemplates['--'] = 0;//$template['templateid'];
			require_once "email-template-fns.php";
			$templates = ensureStandardTemplates('client');
//$templates = false;			
			if($templates) {
				foreach($templates as $template) {
					$datum = $template;
					$systemPrefix = strpos($template['label'], standardPrefix()) === 0 ? standardPrefix() : (
														strpos($template['label'], undeletablePrefix()) === 0 ? undeletablePrefix() : null);
					// ignore other standard or undeletable templates
					if($systemPrefix) continue;
					$baseLabel = strpos($datum['label'], $systemPrefix) === 0 ? '<b>'.substr($datum['label'], strlen($systemPrefix)).'</b>' : $datum['label'];
					$clientEmailTemplates[$baseLabel] = $datum['templateid'];
				}
			}
			if(count($clientEmailTemplates) > 1 || !array_key_exists('--',$clientEmailTemplates)) {
				selectElement('Email Template', "templateid_$section", $value=0, $options=$clientEmailTemplates);
			}
		}
	}
	//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) 
	/*echo "<div style='position:relative;float:right;'>";
	if($sortedInvoices) echoButton('','Generate & Mail Prepayment Invoices to Selected Clients','printSelectedInvoices()');
	echo "</div>";
	*/

	if(!$prepayments && $lookahead) echo "There are no $section clients to deal with in the time frame specified.";
	else {
		tableFrom($columns, $prepayments, "id='clientstable_$section' WIDTH=100% border=1 bordercolor=black", $class='clientstable', $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
		foreach(array_keys($prepayments) as $clientid) {
			hiddenElement("charge_$clientid", $prepayments[$clientid]['netdueraw']);
			$status = $clearCCs[$clientid] ? ccStatus($clearCCs[$clientid]) : '';
			$status = $status ? $status[2] : 'NO_CC';
			hiddenElement("ccstatus_$clientid", $status);
		}
	}
	echo "</form>";
}


function completePrepaymentsArray(&$prepayments, &$repeatCustomers, $recurring, $group, $csv=false) {
	global $availableCredits, $literal, $globalPriorUnpaidToggleNeeded;
	global $minilog;
	
$timingDebug = false; // 	mattOnlyTEST()
if($timingDebug) {$t0 = $utime = microtime(1); $methTimes = array();}
	foreach($prepayments as $index => $pp) {
		// availableCredits is built during findPrepayments  
		// 'owedprior' = amount owed (charge - paid) for all items before the start date
		$creditValue = isset($availableCredits[$pp['clientid']]) ? max(0.0, $availableCredits[$pp['clientid']] - $pp['owedprior']) : 0.0;
		$priorPayableAmount = min($availableCredits[$pp['clientid']], $pp['owedprior']);
		$prepaymentValue = $pp['prepayment'] - $priorPayableAmount;  // NOTE: do not include prior incomplete visits/surcharges payable with existing credit

//if($goMinilog && $index == 965) echo  "<br>prepaymentValue: $prepaymentValue = pp['prepayment']: {$pp['prepayment']} - priorPayableAmount: $priorPayableAmount;";
//if($goMinilog && $index == 1237) echo  "client 1237 prepayment: {$pp['prepayment']} credits: {$availableCredits[$pp['clientid']]} owedprior: {$pp['owedprior']} priorPayableAmount: $priorPayableAmount";;	
		// Payment Due = Subtotal of prior unpaid charges not marked with a [C] + Subtotal of ALL current charges
//if($goMinilog && $index == 1225) print_r($availableCredits[$pp['clientid']]);
//if($index == 930) screenLog("$index: prepayment {$pp['prepayment']} - priorPayableAmount: $priorPayableAmount = $prepaymentValue");
		//$creditValue = isset($availableCredits[$pp['clientid']]) ? $credits[$pp['clientid']] : 0.0;
	//if($goMinilog && $pp['clientid']==981)	screenLog("[[".isset($availableCredits[$pp['clientid']])."]]");
		if(!$csv) {
			$pp['clientname'] = prepaymentClientLink($pp, $nameOnly=true, $sortable=true);
			$pp['flags'] = billingPageFlagPanel($pp);
if($timingDebug) {$methTimes['prepaymentClientLink'] += microtime(1) - $utime; $utime = microtime(1); }
			$pp['prepaymentdollars'] = dollars($prepaymentValue);
		}
		
		$netdue = $prepaymentValue - $pp['paid']  - $creditValue + $pp['paidprior'];  // #NETDUENB#
//if($goMinilog && $index == 965) echo  "<br>".print_r($pp,1)."<br>client 1225 netdue: $netdue = prepaymentValue: $prepaymentValue - paid: \${$pp['paid']} - creditValue: $creditValue + paidprior: {$pp['paidprior']}";
		if(!$csv && $netdue <= 0) { 
			$pp['prepaymentdollars'] .= "<span style='color:green;font-variant:small-caps;font-weight:bold;'> (Paid)</span>";
		}
		$pp['creditvalue'] = $creditValue + $pp['paid'];
		$pp['credits'] = dollars($creditValue + $pp['paid']);
//if($pp['clientid'] == 1098) echo "{$prepaymentValue} - {$pp['paid']} - {$creditValue}";		
		$creditFlag = $netdue < 0 && abs($netdue) >= 0.01 ? 'cr' : '';
//if($goMinilog && $creditFlag)	echo "PP: [$netdue]<p>";
		if($csv) $pp['netdue'] = $netdue;
		else {
			$pp['netdueraw'] = $netdue;
			
			
			echoButton('', 'View', "viewInvoice({$pp['clientid']}, \"$invoiceby\", \"{$pp['email']}\", \"$group\")", 'SmallButton', 'SmallButtonDown', 1).
												' '.
			$pp['netduedollars'] = $creditFlag 
				? dollarAmount(abs($netdue)).$creditFlag
				: fauxLink(dollarAmount(abs($netdue)), "viewInvoice({$pp['clientid']}, \"$invoiceby\", \"{$pp['email']}\", \"$group\")", 
										'noEcho', "View the invoice.", "netdueamount_{$pp['clientid']}");
			//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)
			
			$priorUnpaidButtonOnClick = "handlePriorUnpaid(this, \"netdueamount_{$pp['clientid']}\", {$pp['owedprior']})";
			$priorUnpaidButtonTitle = "Includes \${$pp['owedprior']} prior unpaid";
			if(!$literal) {
				$priorUnpaidButtonTitle = "Includes prior unpaid charges";
				$priorUnpaidButtonOnClick = "alert(\"$priorUnpaidButtonTitle\")";
			}
			$priorunpaidButton =
				!$pp['owedprior'] ? ''
				: "<span class='unpaidbutton' includeunpaid=1 id='unpaidbutton{$pp['clientid']}' 
							clientid = '{$pp['clientid']}'
							owedprior = '{$pp['owedprior']}'
							style='color:green;font-size:2.5em;cursor:pointer;' title='$priorUnpaidButtonTitle'
							onclick='$priorUnpaidButtonOnClick'>"
						."&#8226;</span>";
			$globalPriorUnpaidToggleNeeded = $globalPriorUnpaidToggleNeeded || ($priorunpaidButton && $priorUnpaidButtonOnClick);
			// SEE ALSO updatePriorUnpaid
			$totalNetdueDollars = "totalNetdueDollars='".dollars($netdue)."'";
			$netdueMinusUnpaidDollars = $pp['owedprior'] ? "netdueMinusUnpaidDollars='".dollars($prepaymentValue - $pp['owedprior'])."'" : '';
			$netDueAttrValue = sprintf("%01.2f", max(0, $pp['netdueraw']));
			$owedPriorAttrValue = sprintf("%01.2f", max(0, $pp['owedprior']));
			$pp['netdue'] = 
				"<span $totalNetdueDollars $netdueMinusUnpaidDollars owedprior=$owedPriorAttrValue due=$netDueAttrValue id='netdue_{$pp['clientid']}_{$group}'>
				$priorunpaidButton {$pp['netduedollars']}
				</span>";
			if($creditFlag) $pp['netdue'] = "<font color='green'>{$pp['netdue']}</font>";
		}
if($timingDebug) {$utime = microtime(1);}
		$paymentLink = dynamicPaymentLink($pp['clientid'], $group);
if($timingDebug) {$methTimes['paymentLink'] += microtime(1) - $utime; $utime = microtime(1); }
		$ccStatusDisplay = ccStatusDisplay($pp);
if($timingDebug) {$methTimes['ccStatusDisplay'] += microtime(1) - $utime; $utime = microtime(1); }
		$pp['payment'] = $ccStatusDisplay.'&nbsp;&nbsp'.$paymentLink; // $prepayments[$index] is modified for autopay
		$invoiceby = $pp['invoiceby'] ? $pp['invoiceby'] : $nullChoice;
		if($csv) $pp['invoicecell'] = statementLastSent($pp['clientid'], $repeatCustomers);
		else {
			$historyLink = historyLinkSimple($pp['clientid'], $repeatCustomers, null, null, $group);
if($timingDebug) {$methTimes['historyLink'] += microtime(1) - $utime; $utime = microtime(1); }
			$pp['invoicecell'] = $historyLink.lastTransactionLabel($index);
		}
		$pp['cb'] = "<input type='checkbox' id='client_{$pp['clientid']}' name='client_{$pp['clientid']}' group='$group'>";
		if(!$pp['email']) $pp['cb'] .= " <img src='art/no-email.gif' title='No email address'>"; 

		$prepayments[$index] = $pp;
		//$pp['accountbalance'] = getAccountBalance($pp['clientid'], 1);
		//if($pp['accountbalance'] < 0) $pp['accountbalance'] = abs($pp['accountbalance']).'cr';
	}
if($timingDebug) {
	$minilog[] = "completePrepaymentsArray time spent on<br>";
	foreach((array)$methTimes as $k => $sum) $minilog[] = "__$k: $sum secs.<br>";
	$minilog[] = "completePrepaymentsArray Total time: ".(microtime(1) - $t0)."<br>";
}
}

function lastTransactionLabel($clientptr) {
	$never = '1971-01-01';
	$lastEPayment = fetchRow0Col0(
			"SELECT issuedate FROM tblcredit 
				WHERE clientptr = $clientptr
					AND payment = 1
					AND (sourcereference LIKE 'CC:%' OR sourcereference LIKE 'ACH:%')
					ORDER BY issuedate DESC
					LIMIT 1");
	$lastEPayment = $lastEPayment ? $lastEPayment : $never;
	$lastError = fetchRow0Col0(
			"SELECT `time` FROM tblcreditcarderror 
				WHERE clientptr = $clientptr
					ORDER BY `time` DESC
					LIMIT 1");
	$lastError = $lastError ? $lastError : $never;
	if(strcmp($lastError, $lastEPayment) > 0 && $lastError != $never) {
		$color = 'red';
		$label = 'Declined: '.shortNaturalDate(strtotime($lastError));
	}
	else if ($lastEPayment != $never){
		$color = 'green';
		$label = 'Approved: '.shortNaturalDate(strtotime($lastEPayment));
	}
	if($label) {
		$label = "<br><span style='color:XCOLORX;font-size:0.75em;font-variant: small-caps;'>$label</span>";
		return str_replace('XCOLORX', $color, $label);
	}
}

function parseErrorNote($error) { // UNUSED
	$note = fetchRow0Col0("SELECT note FROM tblchangelog WHERE note LIKE '%|ErrorID:{$error['errorid']}' LIMIT 1");
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



// ***************************************************************************
include "refresh.inc";	
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
var billingFlagPanelToUpdate;
<? 	require_once "client-flag-fns.php";
clientFlagPanelJS('withname');
?>
function editFlags(clientid) { // redefine editFlags!
	billingFlagPanelToUpdate = 'flagpanel'+clientid;
	$.fn.colorbox({href: "client-flag-picker.php?withname=1&clientptr="+clientid, width:"600", height:"470", iframe:true, scrolling: "auto", opacity: "0.3"});
}
<?		



dumpPopCalendarJS(); 
?>
setPrettynames('firstDay','Start Date', 'lastDay','End Date', 'lookahead','Lookahead');

function filterClick(el) {
	if(el.id == 'allflags') {
		$('.filter').removeClass('filterSelected');
		$('.filter').addClass('filterUnselected');
		$(el).addClass('filterSelected');
		$(el).removeClass('filterUnselected');
	}
	else {
		$('#allflags').removeClass('filterSelected');
		$('#allflags').addClass('filterUnselected');
		var elIsSelected = $(el).hasClass('filterSelected') ;
		$(el).removeClass(elIsSelected ? 'filterSelected' : 'filterUnselected');
		$(el).addClass(elIsSelected ? 'filterUnselected' : 'filterSelected');
	}
	if($('.filterSelected').length == 0) {
		$('#allflags').removeClass('filterUnselected');
		$('#allflags').addClass('filterSelected');
	}		
	applyFilter();
}

function setupSorts() {
	$('.clientstable').tablesorter({headers:{0:{sorter:false}, 3:{sorter:false}}, textExtraction:sortFodder});
}
	
function sortFodder(tdel) {
	var credit, attr;
	var content = $(tdel).text();
	if(content.indexOf('$') != -1) { // handle dollars
		content = content.substring(content.indexOf('$')+1);
		if((credit =( content.indexOf('cr')) > 0))
			content = content.substring(0, content.indexOf('cr'));
		content = Number(content);
		if(credit) content = 0 - content;
	}
	else if(attr = $(tdel).children(":nth-child(1)").attr('ccstatusimg')) { // CC status tdel.innerHTML.indexOf('ccstatusimg') != -1
	//NO_CC,CC_EXPIRED,CC_NO_AUTOPAY,CC_VALID,ACH_VALID
		if(attr == 'CC_VALID') content='A ';
		else if(attr == 'CC_VALID') content='A ';
		else if(attr == 'ACH_VALID') content='B ';
		else if(attr == 'CC_NO_AUTOPAY') content='E ';
		else if(attr == 'CC_EXPIRED') content='C ';
		else if(attr == 'NO_CC') content='D ';
		content= content+$(tdel).parent().children(":nth-child(3)").text(); // index starts at 1
	}
	return content;
}


function applyFilter() {
	// for each selected filter images
	var showAll = false;
	var onflags = new Array();
	$('.filterSelected').each(
		function(n, el) {
			onflags[onflags.length] = ".cbill"+el.id;
			if(el.id == 'allflags') showAll = true;
		});

	if(showAll) $('tr').show();
	else {
		$('.billflagpanel').parent().parent().hide();
		for(var i=0; i<onflags.length; i++) {
			//alert(onflags[i]);
			$(onflags[i]).parent().parent().show();
			// find row checkbox, if any
			
		}
	}
	// in every invisible row clear the checkbox
	$('.clientrow :hidden').each(
		function(i, el) {
			el.checked = false;
			el.disabled = true;
		});
	$('.clientrow :visible').each(
		function(i, el) {
			el.disabled = false;
		});
		
	var currencymark = '<?= getCurrencyMark() ?>';
	
	$('.clientstable').each(
		function(i, el) {
			var sectiontable = document.getElementById(el.id);
			var statsid = el.id.split('_');
			var statsel = document.getElementById('stats_'+statsid[1]);
			if(statsel) {
				var rowcount = 0;
				var shown = 0;
				var due = 0;
				//alert('#'+el.id+' tr');
				$('#'+el.id+' tbody tr.clientrow').each(
					function(j, row) {
						rowcount += 1;
					});
				$('#'+el.id+' tbody tr.clientrow:visible').each(
					function(j, row) {
						shown += 1;
						var clientid = row.children[0].children[0].id.substring('client_'.length); // checkbox id
						var chargel = document.getElementById('charge_'+clientid); // hidden charge_ element
						if(chargel != null && chargel.getAttribute('value') != '')
							due += parseFloat(chargel.getAttribute('value'));
					});
				due = due == 0.0 ? 'Nothing due.' :  'Due: '+currencymark+" "+commafy(Math.round(due*100)/100);
				<? //if($omitRevenue) echo "due = '';\n" ?>
				statsel.innerHTML = rowcount+" clients found. "+shown+" shown. "+due;
			}
		});
		
		
}
	

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

var lastButtonHit = null;

function showInterval(section, element, startDate, endDate) {
	document.getElementById('firstDay_'+section).value = startDate;
	document.getElementById('lastDay_'+section).value = endDate;
	changeLookahead(section, element);
}

function dumpIt(section) {
	var lastDay = document.getElementById('lastDay_'+section).value;
	var literal = document.getElementById('literal_'+section) != null  
									&& document.getElementById('literal_'+section).checked ? '1' : '';
	var hidezerodue = document.getElementById('hidezerodue_'+section) != null  
									&& document.getElementById('hidezerodue_'+section).checked ? '1' : '';
	if(MM_validateForm(
		'firstDay_'+section,'','R', 
		'firstDay_'+section,'','isDate', 
		'lastDay_'+section, '', 'R', 
		'lastDay_'+section, '', 'isDate', 
		'firstDay_'+section, 'lastDay_'+section, 'datesInOrder')) {
			//alert('billing.php?csv=1&firstDay='+firstDayArg('firstDay_'+section)+'&lastDay='+firstDayArg('lastDay_'+section)+'&literal='+literal
			//					+'&section='+section);
			document.location.href='<?= SCRIPTNAME ?>?csv=1&firstDay='+firstDayArg('firstDay_'+section)+'&lastDay='+firstDayArg('lastDay_'+section)+'&literal='+literal
								+'&section='+section+'&hidezerodue='+hidezerodue;
	}
}

function changeLookahead(section, element) {
	if(lastButtonHit) {alert('Please wait...'); return;}
	var lastDay = document.getElementById('lastDay_'+section).value;
	var literal = document.getElementById('literal_'+section) != null  
									&& document.getElementById('literal_'+section).checked ? '1' : '';
	var hidezerodue = document.getElementById('hidezerodue_'+section) != null  
									&& document.getElementById('hidezerodue_'+section).checked ? '1' : '';
	if(MM_validateForm(
		'firstDay_'+section,'','R', 
		'firstDay_'+section,'','isDate', 
		'lastDay_'+section, '', 'R', 
		'lastDay_'+section, '', 'isDate', 
		'firstDay_'+section, 'lastDay_'+section, 'datesInOrder')) {
			lastButtonHit = element;
			$('#prepaymentsdueby_'+section).busy("busy");		
			//busyImage();
			//document.location.href='billing.php?firstDay='+firstDayArg()+'&lastDay='+firstDayArg('lastDay')+'&literal='+literal;
			ajaxGetAndCallWith('<?= SCRIPTNAME ?>?firstDay='+firstDayArg('firstDay_'+section)+'&lastDay='+firstDayArg('lastDay_'+section)+'&literal='+literal
								+'&section='+section+'&hidezerodue='+hidezerodue,
								function(aspect, result) {
									$('.flagbutton').show();
									$('#prepaymentsdueby_'+section).busy("hide");
									lastButtonHit = null;
									document.getElementById(section).innerHTML = result;
									applyFilter();
									setupSorts();
								},
								null);
	}
}

function viewInvoice(clientid, invoiceby, email, section) {
	var lookahead = document.getElementById('lookahead_'+section).value;
	var originalliteral = document.getElementById('originalliteral_'+section) && document.getElementById('originalliteral_'+section).value;
	var excludePriorUnpaid = 
		document.getElementById('unpaidbutton'+clientid) != null
		&& document.getElementById('unpaidbutton'+clientid).getAttribute('includeunpaid') == '0';
	//includeunpaid="0" id="unpaidbutton1219"
	var args = '&firstDay='+firstDayArg('firstDay_'+section)+'&lookahead='+lookahead
								+'&email='+email
								+(originalliteral ? '&literal=1' : '')
								+(excludePriorUnpaid ? '&excludePriorUnpaid=1' : '');
								//includeunpaid="0" id="unpaidbutton1219"
	openConsoleWindow('invoiceview', 'billing-statement-view.php?id='+clientid+args, 800, 800);
}

function openPaymentWindow(clientid) {
	openConsoleWindow("paymentwindow", 
		"prepayment-invoice-payment.php?client="+clientid+"&amount="+document.getElementById('charge_'+clientid).value,
		600,400); 
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
  w.document.location.href=url;
  if(w) w.focus();
}

function selectAll(group, onoff) {
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) {
<? if(FALSE && mattOnlyTEST()) { ?>
if(els[i].type == 'checkbox'  && els[i].name.indexOf('client_') == 0) 
	alert('GROUP ['+group+'] disabled: ['+els[i].disabled+'] index: ['+els[i].name+'] group: ['+els[i].getAttribute('group')+']');
<? } ?>
		if(els[i].type == 'checkbox' && !els[i].disabled && els[i].name.indexOf('client_') == 0 && els[i].getAttribute('group') == group) {
			els[i].checked = onoff;
		}
	}
}


function handlePriorUnpaid(el, netdueamount_clientid, owedprior) {
	// netdueamount_clientid is the net due fauxlink id
	var elid = el.id;
	var message = "<div style='text-align:center;font-size:1.5em'>Include $"+owedprior+" of prior unpaid charges in the invoice?<p>"
	+"<input type='button' value='yes' onclick='updatePriorUnpaid(\""+elid+"\", \""+netdueamount_clientid+"\", 1)'>"
	+"<input type='button' value='no' onclick='updatePriorUnpaid(\""+elid+"\", \""+netdueamount_clientid+"\", 0)'></div>";
	$.fn.colorbox({html:message, width:"500", height:"147", scrolling: true, opacity: "0.3"});
}

function handlePriorUnpaidGlobally(event) {
	// netdueamount_clientid is the net due fauxlink id
	event.stopPropagation();
	var message = "<div style='text-align:center;font-size:1.5em'>Include prior unpaid charges in the invoices?<p>"
	+"<input type='button' value='yes' onclick='setPriorUnpaidGlobally(1)'>"
	+"<input type='button' value='no' onclick='setPriorUnpaidGlobally(0)'></div>";
	$.fn.colorbox({html:message, width:"500", height:"147", scrolling: true, opacity: "0.3"});
}

function setPriorUnpaidGlobally(includePrior) {
	$('.unpaidbutton').each(
		function(index, el) {
			updatePriorUnpaid(el, 'netdueamount_'+el.getAttribute('clientid'), includePrior);
		});
}

function updatePriorUnpaid(el, netdueamount_clientid, includePrior) {
	// netdueamount_clientid is the net due fauxlink id
//alert(typeof el);	
	if(typeof el == 'string') el = document.getElementById(el);
	
	
//alert("el: "+el+" includePrior: "+includePrior+" netdue: "+netdue);	
	el.style.color = includePrior ? 'green' : 'red';
	el.setAttribute('includeunpaid', includePrior);
	//alert(netdueamount_clientid+': '+el.getAttribute('includeunpaid'));
	//owedprior={$pp['owedprior']} due={$pp['netdueraw']}
	var fauxlink = document.getElementById(netdueamount_clientid);
	if(fauxlink != null) { // where there are credits
		var daddy = fauxlink.parentNode;
		var netdue = includePrior == 1 
			? daddy.getAttribute('totalNetdueDollars') 
			: daddy.getAttribute('netdueMinusUnpaidDollars') ;
		fauxlink.innerHTML = netdue;
		var clientid = netdueamount_clientid.split('_');
		clientid = clientid[1];
		document.getElementById('charge_'+clientid).value = 
			includePrior 
			? daddy.getAttribute('netdueraw')
			: daddy.getAttribute('due') - daddy.getAttribute('owedprior');
	}
	
	
	$.fn.colorbox.close();
}
	
	
function printSelectedInvoices(section) {
	var sels = getSelections(section, 'Please select one or more invoices to print.');
	if(sels.length == 0) return;
	var lookahead = document.getElementById('lookahead').value;
	var args = '&firstDay='+firstDayArg('firstDay_'+section)+'&lookahead='+lookahead;
	// 	document.forms['charge_'+section].literal.value = document.forms['showform_'+section].elements['literal_'+section].checked;
	args += '&literal='+(document.forms['showform_'+section].elements['literal_'+section].checked ? '1' : '0');
	openConsoleWindow('invoiceprint', 'billing-invoice-print.php?ids='+sels+args, 700, 500);
}

function emailSelectedInvoices(section) {
	var sels = getSelections(section, 'Please select one or more invoices to email.');
	var excludes;
	if(sels.length > 0) excludes = getExcludedPriorUnpaidSelections(sels);
	if(sels.length == 0) return;
	var lookahead = document.getElementById('lookahead_'+section).value;
	var includePayNowLink = "<?= $_SESSION['preferences']['includePayNowLink'] ?>";
	var args = '&firstDay='+firstDayArg('firstDay_'+section)+'&lookahead='+lookahead+'&includePayNowLink='+includePayNowLink;
	args += '&literal='+(document.forms['showform_'+section].elements['literal_'+section].checked ? '1' : '0');
	if(excludes != null) args += "&excludePriors="+excludes;
	if(document.forms['showform_'+section].elements['templateid_'+section]) alert('hooboy');
<? //if(mattOnlyTEST()) echo "alert(document.getElementById('templateid_'+section));\n" // ['templateid']?>
	var templateSelect = document.getElementById('templateid_'+section);
	if(templateSelect != null && typeof templateSelect != 'undefined')
			args += '&templateid='+templateSelect.options[templateSelect.selectedIndex].value;
<? //if(mattOnlyTEST()) echo "alert('billing-statement-email.php?send=1&ids='+sels+args);"; ?>
	ajaxGetAndCallWith('billing-statement-email.php?send=1&ids='+sels+args, reportEmailSuccess, null);
}

function chargeSelectedClients(section) {
<? if($_SESSION['ccenabled'] &&  !merchantInfoSupplied()) {?>
	alert("Merchant credit card processing information is not set.");
	return;
<? } ?>	
	var sels = getSelections(section, 'Please select one or more clients to charge.');
	if(sels.length == 0) return;
<? if($slideTest) { // #SENDINVOICES 
		if($goMinilog) { ?>
	document.forms['charge_'+section].firstDay.value = document.forms['showform_'+section].elements['firstDay_'+section].value;
	document.forms['charge_'+section].lookahead.value = document.forms['showform_'+section].elements['lookahead_'+section].value;
	document.forms['charge_'+section].literal.value = document.forms['showform_'+section].elements['literal_'+section].checked;
	  <? } ?>
	var successaction = escape('window.opener.slideOutCharge(window.opener.document.charge_'+section+');');
<? } else { ?>
	var successaction = escape('window.opener.document.charge_'+section+'.submit();');
<? } ?>
	openConsoleWindow("ccloginwindow", "cc-login.php?successaction="+successaction,600,500);
}

function getSelections(section, emptyMsg) {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') > 0 && els[i].checked && els[i].getAttribute('group') == section) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert(emptyMsg);
		return;
	}
	return sels;
}

function getExcludedPriorUnpaidSelections(ids) {
	if(ids.length == 0) return;
	ids = ids.split(',');
	var excludes = new Array();
	var el;
	for(var i = 0; i < ids.length; i++) {
		if((el = document.getElementById("unpaidbutton"+ids[i])) !== null && el.getAttribute('includeunpaid') != "1") 
			excludes[excludes.length] = el.getAttribute('clientid');
	}
	if(excludes.length > 0) return excludes.join(',');
}

function reportEmailSuccess(argument, txt) {
	alert(txt);
	update();
}

function update(target, aspect) {
	// target: account aspect: 1633 row: client_1633
//alert('target: '+target+' aspect: '+aspect+' row: '+"client_"+aspect);
	if(aspect > 0 && target == 'account') {
		// find a tr whose id starts with "client_"+aspect
		var rowid = null;
		var trs = document.getElementsByTagName('tr');
//alert('rows: '+trs.length);		
		for(var i = 0; i < trs.length; i++)
			if(trs[i].id == "client_"+aspect)
				rowid = trs[i].id;
//alert('rowid: '+rowid+' asOfDate: '+document.getElementById('asOfDate').value);
		var firstDayR = document.getElementById('firstDay_recurring');
		if(firstDayR) firstDayR = firstDayR.value;
		var firstDayNR = document.getElementById('firstDay_nonrecurring');
		if(firstDayNR) firstDayNR = firstDayNR.value;
		var firstDayM = document.getElementById('firstDay_monthly');
		if(firstDayM) firstDayM = firstDayM.value;
		var lastDayR = document.getElementById('lastDay_recurring');
		if(lastDayR) lastDayR = lastDayR.value;
		var lastDayNR = document.getElementById('lastDay_nonrecurring');
		if(lastDayNR) lastDayNR = lastDayNR.value;
		var lastDayM = document.getElementById('lastDay_monthly');
		if(lastDayM) lastDayM = lastDayM.value;
		var refreshURL = "<?= SCRIPTNAME ?>?clientupdate="+aspect
												//+"&firstDay="+document.getElementById('origFirstDay').value
												+"&firstDay_recurring="+firstDayR+"&lastDay_recurring="+lastDayR
												+"&firstDay_nonrecurring="+firstDayNR+"&lastDay_nonrecurring="+lastDayNR
												+"&firstDay_monthly="+firstDayM+"&lastDay_monthly="+lastDayM;
<? //if(mattOnlyTEST()) echo "alert('refreshURL: '+refreshURL);" ?>
//refreshURL: billing.php?clientupdate=1633&firstDay_recurring=null&lastDay_recurring=null&firstDay_nonrecurring=03/01/2015&lastDay_nonrecurring=03/31/2015&firstDay_monthly=null&lastDay_monthly=null
		ajaxGetAndCallWith(refreshURL, updateClientRowCallback, rowid)
	}
	//update('flags', \"$flagPanel\")
<? if(staffOnlyTEST()) { ?>
	else if(target == 'flags') {
//alert(aspect);
		document.getElementById(billingFlagPanelToUpdate).innerHTML = processFlagHTML(aspect);
	}
<? } ?>
	else refresh();
}

function processFlagHTML(html) {
	// html is panel div contents: a series of flag imgs, billing flags last:
	// e.g.,  ...<img src="bizfiles/biz_3/clientui/flags/keys.jpg" title="test"><img src="art/spacer.gif" height="1" width="20"> <img src="art/billflag-1.jpg" title="pita"> <img src="art/billflag-3.jpg" title="three"> <img src="art/billflag-4.jpg" title="four">
	// gather the the billing flags and recast them as 12x12 IMGs
	if(html.indexOf('Click to') >= 0) return html;
	var spacerStr = 'width=20> ';
	var start = html.indexOf(spacerStr);
	if(start == -1) return html;
//alert('start: '+start);
	start = start + spacerStr.length+1;

//<img src='art/flag-snarl.jpg' title='danger'><img src='art/spacer.gif' height=1 width=20> <img src='art/billflag-1.jpg' title='pita' >
//alert('start+: '+start+' ==> '+(html.length-1));
//alert(html);
	html = html.substring(start, html.length-1);  // strip first < and last > AFTER spacer
	var out='';
	var arr = html.split('> <');
//alert(arr);
	for(var i=0; i<arr.length; i++)
		out += " <"+arr[i]+" height=15 width=15>";
	return out;
}

function updateClientRowCallback(rowid, data) {
	// <update><clientid>$clientupdate</clientid><section>$section</section><netdue>{$row['netdue']}</netdue><paylink>$payLink</paylink></update>
	//alert(data);
	var root = getDocumentFromXML(data).documentElement;
	//alert(root);
<? //if(mattOnlyTEST()) echo "alert(root.tagName);return;" ?>
	if(root.tagName == 'ERROR') {
		alert(root.nodeValue);
		return;
	}
	var kids = root.childNodes;
	var rowvals = {};
	for(var i=0; i < kids.length; i++) {
		var tag = kids[i].tagName;
		var val = kids[i].firstChild.nodeValue;
		rowvals[tag] = val;
	}
	//alert('#netdue_'+rowvals.clientid+'_'+rowvals.section);
	//alert('#paylink_'+rowvals.clientid+'_'+rowvals.section);
	if(rowvals.test) alert(rowvals.test);
	$('#netdue_'+rowvals.clientid+'_'+rowvals.section).html(rowvals.netdue);
	$('#netdue_'+rowvals.clientid+'_'+rowvals.section).attr('due', rowvals.newpayamount);
	if(rowvals.newpayamount == 'NOPAYLINK') {
		$('#paylink_'+rowvals.clientid+'_'+rowvals.section).html('');
		$('#paylink_'+rowvals.clientid+'_'+rowvals.section).prop(function() {});
	}
	else {
//alert(	$('#paylink_'+rowvals.clientid+'_'+rowvals.section)[0].id);
//alert(rowvals.paylink);	
		$('#paylink_'+rowvals.clientid+'_'+rowvals.section).click(
			function() {
				openPaymentWindow(rowvals.clientid); });
	}
	$('#viewRecent_'+rowvals.clientid+'_'+rowvals.section).html(rowvals.viewRecent);
<? //if(mattOnlyTEST()) echo "alert('********************!');return;" ?>
	applyFilter();		
	
}

</script>
<img src='art/spacer.gif' width=1 height=150>

<?
if($goMinilog) { screenLog("(almost) Total page time: ".(microtime(1) - $utime)." sec"); }


if($slideTest) { 
	echo "</div>";
	$slideoutWidth = 750;
	$slideoutHeight = 400;
	$tabWidth = 35;
	$iframeWidth = $slideoutWidth - $tabWidth - 5;
	$iframeHeight = $slideoutHeight - 47;
	$shrunkPos = 0-($slideoutWidth-$tabWidth);
	$transactions = strtoupper('Transactions');
	for($i=0;$i<strlen($transactions);$i++) $transactionsLabel .= "<br>{$transactions[$i]}";
	$transactionsLabel = "<div style='text-align:center;font-size:1.1em;font-weight:bold;'>$transactionsLabel</div>";
	$closeButton = "<span style='color:red;font-weight:bold'>&lt;&lt;&lt;<br>Close</span><p>$transactionsLabel"; 
	$showButton = "<span style='color:green;font-weight:bold'>&gt;&gt;&gt;<br>Show</span><p>$transactionsLabel"; 
?>
<div id="slideout">
    <div id="toggle"><?= $showButton ?></div>
    <!-- br style="clear: right" / -->
    <iframe name='cc-charge-iframe' src='cc-charge-clients.php?iframe=1&empty=1' width=<?= $iframeWidth ?> height=<?= $iframeHeight ?>></iframe>
</div>
<script language='javascript'>
function slideOutCharge(thisform) {
	$('#slideout').animate({ left: 0 }, 'slow', function() {
	            $('#toggle').html("<?= $closeButton ?>");
        });
  thisform.submit();
}
$(document).scroll(
		function() {
			$('#slideout').offset({ top: Math.max($(document).scrollTop(), 230), left: $('#slideout').offset().left });
		}
);

$('#toggle').toggle( 
    function() {
        $('#slideout').animate({ left: 0 }, 'slow', function() {
            $('#toggle').html("<?= $closeButton ?>");
        });
    }, 
    function() {
        $('#slideout').animate({ left: <?= $shrunkPos ?> }, 'slow', function() {
            $('#toggle').html("<?= $showButton ?>");
        });
    }
);

function sortTable(headerLink) {
	// use link to
	// 		find table
	//    determine sort
	//    if date sort, sort oldest to newest
	// table = headerLimk.parent.parent.
}

</script>
<style>
#slideout { position: absolute; height: <?= $slideoutHeight ?>px; width: <?= $slideoutWidth ?>px; border: 1px dotted gray; background: lightblue; color: black; top:50px; left: <?= $shrunkPos ?>px; padding-top: 20px;}
#toggle { float: right; cursor:pointer; }
</style>
<?	
}



include "frame-end.html";

function interpretInterval($intervalLabel) {
	$firstDayThisMonthInt = strtotime(date("Y-m-01"));
	if($intervalLabel == 'Last Month') {
		$start = shortDate(strtotime(date("Y-m-01", strtotime("-1 month", $firstDayThisMonthInt))));
		$end = shortDate(strtotime(date("Y-m-t", strtotime("-1 month", $firstDayThisMonthInt))));
		$month = date("M", strtotime("-1 month", $firstDayThisMonthInt));
	}
	else if($intervalLabel == 'Next Month') {
		$start = shortDate(strtotime(date("Y-m-01", strtotime("+1 month", $firstDayThisMonthInt))));
		$end = shortDate(strtotime(date("Y-m-t", strtotime("+1 month", $firstDayThisMonthInt))));
		$month = date("M", strtotime("+1 month", $firstDayThisMonthInt));
	}
	else if($intervalLabel == 'This Month') {
		$start = shortDate(strtotime(date("Y-m-01")));
		$end = shortDate(strtotime(date("Y-m-t")));
		$month = date("M");
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


