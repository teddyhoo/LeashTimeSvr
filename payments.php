<?
/* payments.php
*
* modes: 
* client - display recent activity in lightbox
* else - show full page
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "invoice-fns.php";


// Verify login information here
locked('o-');
$fields = 'pattern,linitial,client,includeinactive';
extract(extractVars($fields, $_REQUEST));  // saveCredit, chargeCard, id, client, amount, externalreference, sourcereference, reason, payout, gratuity, 
										 //gratuityProvider_1, percent_1 ...gratuityProvider_5, percent_5, tipnote

if($client) {  // LIGHTBOX MODE
	recentActivity($client);
	exit;
}



$error = false;

findClients();

$pageTitle = "Add a Payment";
	
require "frame.html";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

//print_r($source);exit;

// ###################################################################################################
// CASE 1: Creating a Payment for an Unspecified Client
?>	
<form name="findclients" method="post" action="payments.php">
<input id='linitial' name='linitial' type=hidden value='<?= $linitial ?>'>
<input id='pattern' name='pattern' value='<?= safeValue($pattern); ?>' size=10 autocomplete='off'> <? echoButton('', 'Search', "search()") ?>
<? if(mattOnlyTEST() || dbTEST('pawlosophy')) {
		echo " "; 
		labeledCheckbox('Include Inactive Clients', 'includeinactive', $includeinactive, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title='Include inactive clients in the results.');
	 }
?>
</form>
<p style='font-size: 1.4em;font-weight:normal;'>
<?
echo $linitial == 'all' 
	? "<span class='highlightedinitial'>All</span> - "
	: "<a class='fauxlink' onClick='initialPick(\"all\")'>All</a> - ";
for($i = ord('A'); $i <= ord('Z'); $i++) {
  $c = chr($i);
  //echo " <a href=client-picker.php?linitial=$c&target=$target>$c</a>";
  if(isset($linitial) && $linitial == $c) echo "<span class='highlightedinitial'>$c</span>";
  else echo " <a class='fauxlink' onClick='initialPick(\"$c\")'>$c</a>";
  if($c != 'Z') echo " - ";
}
?>
<p>
<?
	if(isset($baseQuery)) {
		echo ($numFound ? $numFound : 'No')." clients found.  ";
		if($numFound > count($clients)) echo count($clients)." shown.";
?>
<p>
<style>
.results td {font-size:1.1em;padding-right:10px;padding-bottom:4px;}
</style>
<table class='results'>
<tr><th>Client</th><th>Status</th><th style='text-align:right;padding-right:10px;'>Balance</th><th>&nbsp;</th></tr>
<?
		foreach($clients as $client) {
			//$address = $client['address'];
			//if($address[0] == ",") $address = substr($address, 1);
			$balance = $balances[$client['clientid']];
			if($balance == 0) $balance = 'PAID UP';
			else if($balance < 0) $balance = "<span style='color:green'>".dollarAmount(abs($balance)).'CR</span>';
			else $balance = dollarAmount($balance);
			$balance = accountBalanceLink($client['clientid'], $balance);
			$clientName = htmlentities($client['name'], ENT_QUOTES);
			echo "<tr><td><a href=# onClick='pickClient({$client['clientid']}, \"$clientName\", \"{$client['packageid']}\")'>$clientName</a></td>";
			echo "<td>{$client['status']}</td>\n";
			
			echo "<td style='text-align:right;'>$balance</td>\n";
			echo "<td>".
				paymentLink($client['clientid'], 0)
				."</td></tr>\n";
			
		}
	}

// ###################################################################################################
?>
</table>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function initialPick(initial) {
	var includeinactive = document.getElementById('includeinactive');
	includeinactive = includeinactive && includeinactive.checked ? '&includeinactive=1' : '';
  document.location.href='payments.php?linitial='+initial+includeinactive;
}

function pickClient(id) {
	openConsoleWindow("clientviewer", "client-view.php?id="+id,600,400);
}

setPrettynames('pattern', "Search term");
function search() {
	var includeinactive = document.getElementById('includeinactive');
	includeinactive = includeinactive && includeinactive.checked ? '&includeinactive=1' : '';
	if(MM_validateForm("pattern", "", "R"))
		document.location.href='payments.php?pattern='+escape(document.getElementById('pattern').value)+includeinactive;
}

function update(aspect, client) {
	var arg;
	var includeinactive = document.getElementById('includeinactive');
	includeinactive = includeinactive && includeinactive.checked ? '&includeinactive=1' : '';
	if(document.getElementById('linitial').value) arg = 'linitial='+document.getElementById('linitial').value;
	else arg = 'pattern='+escape(document.getElementById('pattern').value);
	document.location.href='payments.php?'+arg+includeinactive;
}

function viewRecentActivity(client) {
	$.fn.colorbox({href:"payments.php?client="+client, width:"600", height:"400", scrolling: true, opacity: "0.3"});
}



</script>
<?

require "frame-end.html";

function findClients() {
	global $baseQuery, $pattern, $linitial, $numFound, $clients, $balances, $includeinactive;
	$clients = array();
	$includeInactivePhrase = $includeinactive ? "1=1" : "active=1";
	$baseQuery = "SELECT clientid, packageid, current, CONCAT_WS(' ',fname,lname) as name, 
									IF(active = 1, 'active', 'inactive') as status,
									CONCAT_WS(', ',street1, city) as address 
								FROM tblclient
								LEFT JOIN tblrecurringpackage ON clientid = clientptr
								WHERE $includeInactivePhrase ANDALSO
								ORDER BY lname, fname, current ASC"; // AND (packageid is null OR tblrecurringpackage.current=1)

	if(isset($pattern)) {
		if(strpos($pattern, '*') !== FALSE) $realPattern = str_replace  ('*', '%', $pattern);
		else $realPattern = "%$pattern%";
		$baseQuery = str_replace("ANDALSO", "AND CONCAT_WS(' ',fname,lname) like '$realPattern'", $baseQuery);
		//$baseQuery = "$baseQuery AND CONCAT_WS(' ',fname,lname) like '$realPattern'";
		//$numFound = mysql_num_rows(mysql_query($baseQuery));
		//if($numFound)
			//$clients = fetchAssociations("$baseQuery ORDER BY lname, fname"); // LIMIT 15
		$all = array_merge(fetchAssociationsKeyedBy("$baseQuery", 'clientid', 1));
		$numFound = count($all);
		for($i=0; $i<=15 && $all[$i]; $i++) $clients[] = $all[$i];
		
	}
	else if(isset($linitial)) {
		if($linitial == 'all') $baseQuery = str_replace("ANDALSO", "", $baseQuery);
		else $baseQuery = str_replace("ANDALSO", "AND lname like '$linitial%'", $baseQuery);
		$baseQuery = "$baseQuery";
		//$clients = fetchAssociations("$baseQuery");
		//$numFound = count($clients);
		$all = array_merge(fetchAssociationsKeyedBy("$baseQuery", 'clientid', 1));
		$numFound = count($all);
	//if(mattOnlyTEST()) screenLog( $baseQuery);	
		$clients = $all;
	//for($i=0; $i<=15 && $all[$i]; $i++) $clients[] = $all[$i];
		
	}
	else {
		//$numFound = mysql_num_rows(mysql_query($baseQuery));
		$baseQuery = str_replace("ANDALSO", "", $baseQuery);
		$baseQuery = "$baseQuery"; // LIMIT 15
		//$clients = fetchAssociations("$baseQuery");
		$all = array_merge(fetchAssociationsKeyedBy("$baseQuery ", 'clientid', 1));
		$numFound = count($all);
		for($i=0; $i<=15 && $all[$i]; $i++) $clients[] = $all[$i];
	}

	$balances = array();
	if($clients)
		foreach($clients as $client) 
			$balances[$client['clientid']] = getAccountBalance($client['clientid'], /*includeCredits=*/true, /*allBillables*/false);;
}

function paymentLink($clientid, $amount) {
	$url = "prepayment-invoice-payment.php?client=$clientid&amount=$amount";
		return fauxLink('Pay', "openConsoleWindow(\"paymentwindow\", \"$url\",600,400)", 1, "Record a payment for this prepayment invoice.");
}

function accountBalanceLink($clientid, $label) {
	return fauxLink($label, "viewRecentActivity($clientid)", 1, "View recent activity on this account.");
}

function recentActivity($client) {

	$refunds = fetchAssociations(
		"SELECT refundid as id, amount, issuedate as date, reason, paymentptr, 'Refund' as type
		 FROM tblrefund
		 WHERE clientptr = $client
		 ORDER BY date DESC, refundid DESC");

	$credits = fetchAssociations(
		"SELECT *, creditid as id, issuedate as date, amount-amountused as amountleft, if(payment = 1, 'Payment', 'Credit') as type 
		 FROM tblcredit 
		 WHERE clientptr = $client 
		 ORDER BY date DESC");	 

	$client = getOneClientsDetails($client, array('email'));


	$extraBodyStyle = 'padding:10px;background:white;';
	require "frame-bannerless.php";

	if($error) {
		echo $error;
		exit;
	}
	echo "<h2>{$client['clientname']}'s Recent Activity</h2>";
	echo "<p align=center>";
	fauxLink("View {$client['clientname']}'s Account", "parent.document.location.href=\"client-edit.php?id={$client['clientid']}&tab=account\";window.close();");
	echo "</p>";
	$columns = explodePairsLine($columns.'date|Date||id|Action||amount|Amount||reason|Note');
	$colClasses = array('subtotal'=>'dollaramountcell');

	$actions = array_merge($refunds, $credits);
	function cmpDates($a, $b) {return 0-strcmp($a['date'], $b['date']); }
	usort($actions, 'cmpDates');
	$numActions = count($actions);
	while(current($actions)) {
		if($i < 50) $finalActions[] = current($actions);
		next($actions);
	}

	foreach((array)$finalActions as $action) {
		$row = array();
		$type = $action['type'];
		$row['id'] = "$type #{$action['id']}";
		if(in_array($type, array('Credit', 'Payment'))) {
			$credit = $action;
			$row['date'] = shortDate(strtotime($credit['date']));
			$row['amount'] = dollarAmount($credit['amount']);
			
			if($credit['voided']) {
				require_once "item-note-fns.php";
				$voidReason = getItemNote('tblcredit', $credit['creditid']);
				$voidReason = $voidReason ? truncatedLabel($voidReason['note'], 25) : '';
				$voidedDate = shortDate(strtotime($credit['voided']));
				$row['reason'] = "<font color=red>VOID ($voidedDate): \${$credit['voidedamount']} ".$voidReason.'</font>';
			}
			else $row['reason'] = $credit['reason'];
		}
		else if($type == 'Refund') {
			$refund = $action;
			$row['date'] = shortDate(strtotime($refund['date']));
			$row['amount'] = dollarAmount($refund['amount']);
			$row['reason'] = "(of Payment #{$refund['paymentptr']}) {$refund['reason']}";
		}
		$rowClass = strpos($rowClass, 'EVEN') ? 'futuretask' : 'futuretaskEVEN';
		$rowClasses[] = $rowClass;
		$rows[] = $row;
	}
	//tableFrom($columns, $data, $attributes, $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses)
	tableFrom($columns, $rows, 'WIDTH=100% ',null,null,null,null,$colSorts,$rowClasses, $colClasses, 'sortInvoices');
}