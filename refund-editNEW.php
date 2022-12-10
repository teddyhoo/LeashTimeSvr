<?
/* refund-editNEW.php
*
* Parameters: 
* id - id of refund to be edited
* - or -
* client - id of client to be credited
* payment - id of payment refund is for
*
* credit may not be modified (except for reason) once amountused > 0
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "refund-fns.php";
require_once "invoice-fns.php";
require_once "cc-processing-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract($_REQUEST);  //consumeExistingCredit
$error = false;
// User has hit the "Charge Credit Card" button
// Confirm !expireCCAuthorization
if($payment) { //CC: ID91AFiOoB
	$paymentDetail = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = '$payment' LIMIT 1");
	if($paymentDetail['externalreference']) {
		if(strpos($paymentDetail['externalreference'], 'CC: ') === 0)
			$paymentCCTransactionId = substr($paymentDetail['externalreference'], strlen('CC: '));
		else if(strpos($paymentDetail['externalreference'], 'ACH: ') === 0) {
			$isAnACHTransaction = true;
			$paymentCCTransactionId = substr($paymentDetail['externalreference'], strlen('ACH: '));
		}
	}
}

if($loginReturn) $_REQUEST['RefundEditorDblClickToken'] = $loginReturn; // preserve the token from the last payment editor
if($saveRefund && $client && $_SESSION['RefundEditorDblClickToken'] != $_REQUEST['RefundEditorDblClickToken']) {
		$error = "Button was clicked twice.  Please do not double-click buttons in LeashTime.<p>";
		$stopOnError = true;
}

if(!$error && $saveRefund && $refundCard && $client) {

	if(!adequateRights('*cc')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
		$error = "Insufficient Access Rights to refund client's card or checking account.";
		$refundCard = false;
	}
	if(!$error) {
		$loginNeeded = is_array($expiration = expireCCAuthorization());
		if($loginNeeded) {
			$args = array("saveRefund=1","refundCard=1","client=$client","reason=".urlencode ($reason), "amount=$amount",
												"externalreference=".urlencode($externalreference),
												"sourcereference=".urlencode($sourcereference),
												"loginReturn={$_REQUEST['RefundEditorDblClickToken']}",
												"payment=$payment",
												"consumeExistingCredit=$consumeExistingCredit");
			$backlink = "refund-edit.php?".join('&', $args);
			include "cc-login.php";
			exit;
		}
	}
}

if(!$error && $saveRefund) {
	if($id) {
		$refund = array('reason'=>$reason, 'externalreference'=>$externalreference, 'sourcereference'=>$sourcereference);
		updateTable('tblrefund', $refund, "refundid = $id", 1);
	}
	else {
		if($client) {  // when would client ever be null?
			if($refundCard) {
				// We should probably null-check $paymentCCTransactionId here, but we may wish to allow non-referential refunds in the future				
				$success = refundEPayment($client, null, $amount, $reason, null, $payment, $paymentCCTransactionId);
				if(is_array($success) && $success['REFUNDID']) {
					$successMessage = "Transaction # {$success['TRANSACTIONID']} approved.";
					$refundptr = $success['REFUNDID'];
				}
				else if(is_array($success)) {
					if($success['FAILURE']) $error = $success['FAILURE'];
					else $error = 'ERROR: '.ccLastMessage($success);
				}
			}
			else {
				$refund = array('reason'=>$reason, 'amount'=>$amount, 'clientptr'=>$client, 'issuedate'=>date('Y-m-d'),
											'externalreference'=>$externalreference, 'sourcereference'=>$sourcereference, "paymentptr"=>$payment);
				$refundptr = insertTable('tblrefund', $refund, 1);
			}
		}
	}
	if(!$error) {
		$creditToGenerate = $amount;
		if($consumeExistingCredit) {
			$credits = getClientCredits($client, 1);
			$totalCredit = 0;
			foreach($credits as $credit) $totalCredit += $credit['amountleft'];
			$creditToGenerate = $amount - refundCredits($credits, $refundptr, min($totalCredit, $amount));
		}
		if($consumeExistingCredit && $creditToGenerate > 0) {
			// generate a credit in the amount of the remaining amount
			$newCredit = array('payment'=>0,'externalreference'=>null, 'sourcereference'=>null, 'reason'=>'Refund', 
													'amount'=>$creditToGenerate,
													'clientptr'=>$client, 'issuedate'=>date('Y-m-d H:i:s'));
			$newCredit['creditid'] = insertTable('tblcredit', addCreationFields($newCredit), 1);
			$newCredit['amountleft'] = $creditToGenerate;
			$newCredit['amountused'] = 0; 
			// refund the credit
			$newCredit = array($newCredit);
			refundCredits($newCredit, $refundptr, $creditToGenerate);
		}
		$closeWindow = 'window.close();';
		if($successMessage) {
			$windowTitle = 'Success!';
			require "frame-bannerless.php";
			echo "<center><h2>$successMessage</h2>\n<p>\n<input type='button' value='Done' onClick='window.close();'>";
			$closeWindow = '';
		}
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', null);$closeWindow</script>";
		exit;
	}
}
else if(isset($deleteCredit) && $deleteCredit) {
	if(fetchRow0Col0("SELECT refundid FROM tblrefund WHERE refundid = $deleteCredit LIMIT 1"))
		doQuery("DELETE FROM tblrefund WHERE refundid = $deleteCredit");
	exit;
}

else if(!$id && !$client) { // Client search
	findClients();
}

$operation = 'Add';
if($id) {
	$operation = 'Edit';
	$refund = fetchFirstAssoc("SELECT * FROM tblrefund WHERE refundid = $id");
	$extRef = $refund['externalreference'];
	if(strpos($extRef, ($extRefPrefix = 'CC: ')) === 0 || strpos($extRef, ($extRefPrefix = 'ACH: ')) === 0) {
		$transactionid = substr($refund['externalreference'], strlen($extRefPrefix));
		$refundcc = substr($refund['sourcereference'], strlen($extRefPrefix));
		$changeLogTable = $extRefPrefix == 'CC: ' ? 'ccrefund' : 'achrefund';
		$wasERefund = fetchFirstAssoc("SELECT note FROM tblchangelog WHERE itemtable = '$changeLogTable' AND note like '%|$transactionid' LIMIT 1");
	}
	$client = $refund['clientptr'];
	$prettyIssueDate = shortDate(strtotime($refund['issuedate']));
}
else {
	$refund = array('client'=>$client);
}

if($client) {
	$clientName = getOneClientsDetails($client);
	$clientName = $clientName['clientname'];
	$availCredits = fetchRow0Col0("SELECT sum(amount - amountused) FROM tblcredit WHERE clientptr = $client");
}

$header = "$operation a Refund <font color=red>NEW</font>";
	

$windowTitle = $header;
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

if(!function_exists('dollars')) {
	function dollars($amount) {
		$amount = $amount ? $amount : 0;
		return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
	}
}

//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<h2><?= $header.($id ? " to $clientName on $prettyIssueDate" : ($client ? " to $clientName" : '')) ?></h2>
<?

// ###################################################################################################
// CASE 1: Creating a Payment for an Unspecified Client
if(!$id && !$client) {
?>	
<h3>Step 1: Pick a Client</h3>
<form name="findclients" method="post" action="refund-edit.php">
<input name=target type=hidden value='<?= $target ?>'>
<input name=pattern size=10> <? echoButton('', 'Search', "search()") ?>
</form>
<p style='font-size: 1.4em;font-weight:normal;'>
<?
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

<table class='results'>
<tr><th>Client</th><th>Address</th><th>Packages</th></tr>
<?
		foreach($clients as $client) {
			$address = $client['address'];
			if($address[0] == ",") $address = substr($address, 1);

			$clientName = htmlentities($client['name'], ENT_QUOTES);
			echo "<tr><td><a href=# onClick='pickClient({$client['clientid']}, \"$clientName\", \"{$client['packageid']}\")'>$clientName</a></td><td>$address</td><td>";
			echo isset($packageSummaries[$client['clientid']]) 
							? "<span style='color:green'>{$packageSummaries[$client['clientid']]}</span>"
							: '&nbsp;';
			echo "</td></tr>\n";
		}
	}
}
// ###################################################################################################
else {
	echo "<form name='editrefund' method='POST' action='refund-edit.php'>";
	hiddenElement('client', $client);
	hiddenElement('saveRefund', 1);
	hiddenElement('refundCard', '');
	hiddenElement('availCredits', $availCredits);
	$_SESSION['RefundEditorDblClickToken'] = time();
	hiddenElement('RefundEditorDblClickToken', $_SESSION['RefundEditorDblClickToken']);
	echo "<table>";
	if(!$id) {
		//echo "Client: $clientName";
		$amount = isset($amount) ? $amount : (isset($paymentDetail) ? $paymentDetail['amount'] : '');
		inputRow('Refund Amount:', 'amount', $amount, '', 'dollarinput');
		if($availCredits > 0) {
			//hiddenElement('consumeExistingCredit', true);

			echo "<tr><td colspan=2>";
			labeledCheckbox("Consume existing credits: ( currently ".dollarAmount($availCredits).")",
											'consumeExistingCredit', true, null, null, null, true);
			echo "</tr>";
		}
	}
	else {
		labelRow('Date:', '', $prettyIssueDate);
		labelRow('Refund Amount:', '', dollars($refund['amount']), null, null, null, null, 'raw');
		hiddenElement('id', $id);
	}
	if($wasERefund) {
		if($extRefPrefix == 'CC: ') {
			labelRow('CC transaction #:', '', $transactionid);
			labelRow('Credit Card:', '', $refundcc); 
		}
		else {
			labelRow('ACH transaction #:', '', $transactionid);
			labelRow('Account Num:', '', $refundcc);
		}
		hiddenElement('externalreference', $refund['externalreference']);
		hiddenElement('sourcereference', $refund['sourcereference']);
	}
	else {
		inputRow('Check/transaction #:', 'externalreference', $refund['externalreference'], '', 'VeryLongInput');
		inputRow('Source Account:', 'sourcereference', $refund['sourcereference'], '', 'VeryLongInput');
	}
	hiddenElement('payment', $payment);
	$reason = /*isset($reason) ? $reason : (isset($paymentDetail) ? 'Payment # '.print_r($paymentDetail,1) :*/ $refund['reason'];
	inputRow('Note:', 'reason', $reason, '', 'VeryLongInput');
	if($payment) labelRow('Refund for payment:', '', paymentSummary($paymentDetail));
		echo "</table>";
	echo "</form><p>";
	
	echoButton('', "Save Refund", "checkAndSubmit()");
	echo " ";
	if(!$id && $paymentCCTransactionId && $_SESSION['ccenabled'] && adequateRights('*cc')) {
		$source = getPrimaryPaySource($client);
		if(($source['acctid'] && $isAnACHTransaction) || ($source['ccid'] && !$isAnACHTransaction)) {
			$buttonLabel = $isAnACHTransaction ? "Refund ACH Account" : "Refund Credit Card";
			echoButton('', $buttonLabel, "refundCreditCard($client)");
			echo " ";
		}
	}
	echoButton('', "Quit", 'window.close()');
	
	if($payment) $billablesPaid = fetchAssociations(
		"SELECT tblbillable.* 
			FROM relbillablepayment 
			LEFT JOIN tblbillable ON billableid = billableptr 
			WHERE paymentptr = $payment
			ORDER BY itemdate");
	if($billablesPaid)
		foreach($billablesPaid as $billable) $allItemIds[$billable['itemtable']][] = $billable['billableid'];
		$idFields = explodePairsLine('tblappointment|appointmentid||tblrecurringpackage|packageid'
																	.'||tblothercharge|chargeid||tblsurcharge|surchargeid');
		$joins = explodePairsLine('tblappointment|LEFT JOIN tblservicetype ON servicetypeid = servicecode'
															.'||tblsurcharge|LEFT JOIN tblservicetype ON surchargetypeid = surchargecode');
		$descriptions = explodePairsLine('tblappointment|, label as descr||tblsurcharge|, label as description');
		foreach((array)$allItemIds as $table => $ids) {
			$idField = $idFields[$table];
			$join = $joins[$table];
			$descr = $descriptions[$table];
			$items[$table] = fetchAssociationsKeyedBy($sql = "SELECT *, $idField as itemid $descr FROM $table $join WHERE $idField IN (".join(',', $ids).")", $idField);
//echo join(',', $ids)."<br>";
print_r(array_keys($items['tblappointment']));
		}
	foreach((array)$billablesPaid as $i => $billable) {
			$billablesPaid[$i]['displaydate'] = shortDate(strtotime($billable['itemdate']));
			$billablesPaid[$i]['description'] = $items[$billable['itemtable']][$billable['itemptr']];
			if($billable['itemtable'] == 'tblrecurringpackage') 
				$billablesPaid[$i]['description'] = date('M D', strtotime($billable['monthyear']));
		}
//print_r(count($items['tblappointment'])	);
		$columns = explodePairsLine('displaydate|Date||charge|Amount||Date||description|Description');
		tableFrom($columns, $billablesPaid, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

setPrettynames('client','Client','amount','Refund Amount', 'availCredits', 'Current client credit balance');	
	
function initialPick(initial) {
  document.location.href='refund-edit.php?linitial='+initial;
}

function pickClient(id, clientname, packageid) {
  document.location.href='refund-edit.php?client='+id;
}

function refundCreditCard() {
	document.getElementById('refundCard').value=1;
	checkAndSubmit();
}

function checkAndSubmit() {
	if(MM_validateForm(
		'client', '', 'R',
		'amount', '', 'R',
		'amount', '', 'UNSIGNEDFLOAT'
		)) {
		document.editrefund.submit();
		//window.close();
	}
}

if(document.editrefund) {
	document.editrefund.amount.select();
}
</script>
</body>
</html>
<?
function findClients() {
	global $baseQuery, $pattern, $linitial, $numFound, $clients, $packageSummaries;
	
	$baseQuery = "SELECT clientid, packageid, CONCAT_WS(' ',fname,lname) as name, CONCAT_WS(', ',street1, city) as address 
								FROM tblclient
								LEFT JOIN tblrecurringpackage ON clientid = clientptr
								WHERE active AND (packageid is null OR tblrecurringpackage.current=1)";

	if(isset($pattern)) {
		if(strpos($pattern, '*') !== FALSE) $pattern = str_replace  ('*', '%', $pattern);
		else $pattern = "%$pattern%";
		$baseQuery = "$baseQuery AND CONCAT_WS(' ',fname,lname) like '$pattern'";
		$numFound = mysql_num_rows(mysql_query($baseQuery));
		if($numFound)
			$clients = fetchAssociations("$baseQuery ORDER BY lname, fname LIMIT 15");
	}
	else if(isset($linitial)) {
		$baseQuery = "$baseQuery AND lname like '$linitial%' ORDER BY lname, fname";
		$clients = fetchAssociations("$baseQuery");
		$numFound = count($clients);
	}
	else {
		$numFound = mysql_num_rows(mysql_query($baseQuery));
		$baseQuery = "$baseQuery ORDER BY lname, fname LIMIT 15";
		$clients = fetchAssociations("$baseQuery");
	}

	$packageSummaries = array();
	if($clients) {
		foreach($clients as $client) $clientIds[] = $client['clientid'];
		$clientIds = join(',',$clientIds);
		$sql = "SELECT clientptr, if(monthly, 'Monthly', 'Weekly') as kind
						FROM tblrecurringpackage WHERE current and cancellationdate is null and clientptr IN ($clientIds)";
		$packageSummaries = fetchAssociationsKeyedBy($sql, 'clientptr');
		foreach($packageSummaries as $client => $pckg) $packageSummaries[$client] = $packageSummaries[$client]['kind'];
		$packageSummaries = $packageSummaries ? $packageSummaries : array();

		$sql = "SELECT clientptr, count(*)
						FROM tblservicepackage WHERE current and cancellationdate is null and clientptr IN ($clientIds)
						GROUP BY clientptr";
		foreach(fetchAssociationsKeyedBy($sql, 'clientptr') as $client => $pckg)
			$packageSummaries[$client] = $packageSummaries[$client]  
				? $packageSummaries[$client].' and Short Term'
				: 'Short Term';
	}
}

function paymentSummary($paymentDetail) {
	$dref = $paymentDetail['externalreference'];
	$externalRef =  
		strpos($dref, 'CC: ') === 0 ? '#'.substr($dref, strlen('CC: '))	: (
		strpos($dref, 'ACH: ') === 0 ? '#'.substr($dref, strlen('ACH: '))	: 
		$paymentDetail['externalreference']);
	return shortDate(strtotime($paymentDetail['issuedate']))." - {$paymentDetail['sourcereference']} $externalRef -
						\${$paymentDetail['amount']}";
}

if(!function_exists('dollars')) {
	function dollars($amount) {
		$amount = $amount ? $amount : 0;
		return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
	}
}

