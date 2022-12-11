<?
/* payment-edit-v0.php
*
* (This version introduces VOID payments)
*
* Parameters: 
* id - id of payment to be edited
* - or -
* client - id of client to be credited
* amount (optional)
* successDestination (optional) - as a global or request arg.  If supplied, after successful payment append paymentptr and
* set page to that destination
* suppressSavePaymentButton (optional) - as a global or request arg.
*
* credit may not be modified (except for reason) once amountused > 0
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "invoice-fns.php";
require_once "gratuity-fns.php";
require_once "cc-processing-fns.php";
require_once "item-note-fns.php";
require_once "js-gui-fns.php";

if($_SERVER['REMOTE_ADDR'] == '173.73.2.113') {
	$time0 = microtime(1);
}

$auxiliaryWindow = true; // prevent login from appearing here if session times out
$preserveSuccessDestination = $successDestination;
// Verify login information here
locked('o-');
$fields = 'saveCredit,voidCredit,deleteCredit,reapplyCredit,chargeCard,id,client,amount,externalreference,sourcereference,reason'
					.',voidreason,payout,payoutDisabledButChecked,gratuity,tipnote,linitial,itemnote,issuedate,hide,loginReturn,'
					.'successDestination,';
for($i=1;$i<=5;$i++) $fields .= "gratuityProvider_$i,percent_$i";
extract(extractVars($fields, $_REQUEST));  // saveCredit, chargeCard, id, client, amount, externalreference, sourcereference, reason, payout, gratuity, 
										 //gratuityProvider_1, percent_1 ...gratuityProvider_5, percent_5, tipnote
if($preserveSuccessDestination && !$successDestination) $successDestination = $preserveSuccessDestination;
if($_REQUEST['suppressSavePaymentButton']) $suppressSavePaymentButton = $_REQUEST['suppressSavePaymentButton'];

}
 }

$error = false;
// User has hit the "Charge Credit Card" button
// Confirm !expireCCAuthorization
if($loginReturn) $_REQUEST['PaymentEditorDblClickToken'] = $loginReturn; // preserve the token from the last payment editor

if($saveCredit && $client && $_SESSION['PaymentEditorDblClickToken'] != $_REQUEST['PaymentEditorDblClickToken']) {
		$error = "Button was clicked twice.  Please do not double-click buttons in LeashTime.<p>";
		$stopOnError = true;
}
 }

if(!$error && $saveCredit && $chargeCard && $client) {

	if(!adequateRights('*cc')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
		$error = "Insufficient Access Rights to charge client's card.";
		$chargeCard = false;
	}
	if(!$error) {
		$loginNeeded = is_array($expiration = expireCCAuthorization());
		if($loginNeeded) {
			$args = array("saveCredit=1","chargeCard=1","client=$client","reason=".urlencode ($reason), "amount=$amount",
												"externalreference=".urlencode ($externalreference),
												"sourcereference=".urlencode ($sourcereference),
												"issuedate=".date('Y-m-d H:i:s', ($issuedate ? strtotime($issuedate) : time())),
												"loginReturn={$_REQUEST['PaymentEditorDblClickToken']}",
												"gratuity=$gratuity",
												"tipnote=$tipnote",
												"payout=$payout",
												"successDestination=$successDestination");
			if($suppressSavePaymentButton) $args[] = "suppressSavePaymentButton=$suppressSavePaymentButton";
			foreach($_POST as $k => $v) {
				if(strpos($k, 'dollar_') === 0) {
					$args[] = "$k=$v";
					$n = substr($k, strlen('dollar_'));
					$args[] = "gratuityProvider_$n={$_POST["gratuityProvider_$n"]}";
				}
			}
			$backlink = substr($_SERVER["SCRIPT_NAME"], 1);  // usually payment-edit.php, but may be billing-invoice-charge-email.php
			$backlink = "payment-edit.php?".join('&', $args);
			include "cc-login.php";
			exit;
		}
	}
}

unset($_SESSION['PaymentEditorDblClickToken']);
//if($_POST) {echo "ItemNote: [$itemnote] ID: $id ERROR: $error saveCredit: [$saveCredit]";exit;}
 }

if(!$error && $saveCredit) {
	if($id) {
		$client = fetchRow0Col0($sql = "SELECT clientptr FROM tblcredit WHERE creditid = $id LIMIT 1");
		$credit = array('reason'=>$reason, 'externalreference'=>$externalreference, 'sourcereference'=>$sourcereference, 
			'issuedate'=>date('Y-m-d', strtotime($issuedate)), 'hide'=>$hide);
		//if(fetchRow0Col0("SELECT creditid FROM tblcredit WHERE payment AND creditid = $id AND amountused = 0.00 LIMIT 1"))
		//	$credit['amount'] = $amount;
		updateTable('tblcredit', addModificationFields($credit), "creditid = $id", 1);
//echo "[$itemnote]";exit;
		if($itemnote) {
			updateNote(array('itemtable'=>'tblcredit', 'itemptr'=>$id), $itemnote);
		}
		if($payout || $payoutDisabledButChecked) {
			updateTable('tblgratuity', array('tipnote'=>$tipnote), "paymentptr = $id", 1);
		}
	}
	else {
		if($client) {
			$newPaymentId = null;
			if($chargeCard) {
/*if(mattOnlyTEST()) {
print_r($_REQUEST);
exit;
}*/
}
}
				$success = payElectronically($client, null, $amount, $reason, null, null, ($gratuity ? $gratuity : 0));
 }
				$newPaymentId = $latestPaymentId; //$latestPaymentId is globally set in payElectronically
				if(is_array($success)) {
					if($success['FAILURE']) $error = $success['FAILURE'];
					else $error = 'ERROR: '.ccLastMessage($success);;
				}
				else {
					$successMessage = "Transaction # $success approved.";
				}
			}
			else {
				$paymentAmount = $gratuity ? $amount - $gratuity : $amount;
				$paymentAmount = $paymentAmount ? $paymentAmount : "0";  // avoid NUL not allowed when payment is all gratuity
				$credit = array('payment'=>1, 'reason'=>$reason, 'amount'=>$paymentAmount, 'clientptr'=>$client, 
											'issuedate'=>date('Y-m-d', strtotime($issuedate)), 
											'externalreference'=>$externalreference, 'sourcereference'=>$sourcereference);
				$newPaymentId = insertTable('tblcredit', addCreationFields($credit), 1);
				payOffClientBillables($client);
			}
			if($newPaymentId && $payout) createGratuities($_REQUEST, $client, date('Y-m-d H:i:s', strtotime($issuedate)), $newPaymentId);
		}
		
	}
	if(!$error) {
		$closeWindow = 'window.close();';
		if($successDestination) 
			$closeWindow = "document.location.href='$successDestination$newPaymentId';";
		else if($successMessage) {
			$windowTitle = 'Success!';
			require "frame-bannerless.php";
			echo "<center><h2>$successMessage</h2>\n<p>\n<input type='button' value='Done' onClick='window.close();'>";
if($_SERVER['REMOTE_ADDR'] == '173.73.2.113') {
	logChange(999, 'payment-edit', 'T', "Time: ".(microtime(1) - $time0));
	echo "<hr>Time: ".(microtime(1) - $time0);
}
			$closeWindow = '';
		}
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', '$client');$closeWindow</script>";
		exit;
	}
}
else if(isset($voidCredit) && $voidCredit) {
	//$tempConstraint = $_SESSION['staffuser'] ? '' : "AND amountused = 0.00";
	if($client = fetchRow0Col0("SELECT clientptr FROM tblcredit WHERE creditid = $voidCredit $tempConstraint LIMIT 1")) {
		voidCredit($voidCredit, $voidreason, $hide, $_REQUEST['retainGratuities']);
		$windowTitle = 'Payment voided.';
		$successMessage = "Payment #$voidCredit has been voided.";
	}
	else {
		$windowTitle = 'Payment NOT voided.';
		$successMessage = "Payment #$voidCredit could not be voided.";
	}
	require "frame-bannerless.php";
	echo "<center><h2>$successMessage</h2>\n<p>\n<input type='button' value='Done' onClick='window.close();'>";
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', '$client');</script>";
if($_SERVER['REMOTE_ADDR'] == '173.73.2.113') {
	logChange(999, 'payment-edit', 'T', "Time: ".(microtime(1) - $time0));
	echo "<hr>Time: ".(microtime(1) - $time0);
}
	exit;
}
else if(isset($deleteCredit) && $deleteCredit) {
	if($client = fetchRow0Col0("SELECT clientptr FROM tblcredit WHERE creditid = $deleteCredit LIMIT 1")) {
		deleteCredit($deleteCredit, $voidreason, $hide);
		$windowTitle = 'Payment deleted.';
		$successMessage = "Payment #$deleteCredit has been deleted.";
	}
	else {
		$windowTitle = 'Payment NOT deleted.';
		$successMessage = "Payment #$deleteCredit could not be deleted.";
	}
	require "frame-bannerless.php";
	echo "<center><h2>$successMessage</h2>\n<p>\n<input type='button' value='Done' onClick='window.close();'>";
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', '$client');</script>";
	exit;
}

else if(isset($reapplyCredit) && $reapplyCredit) {
	if($client = fetchRow0Col0("SELECT clientptr FROM tblcredit WHERE creditid = $reapplyCredit LIMIT 1")) {
		$showInvoice = $_REQUEST['showinvoice'];
		reapplyCredit($reapplyCredit, $showInvoice);
		$windowTitle = 'Payment reapplied.';
		$successMessage = "Payment #$reapplyCredit has been reapplied.";
	}
	else {
		$failed = true;
		$windowTitle = 'Payment NOT reapplied.';
		$successMessage = "Payment #$reapplyCredit could NOT be reapplied.";
	}
	if(TRUE || $failed) {
		require "frame-bannerless.php";
		echo "<center><h2>$successMessage</h2>\n<p>\n<input type='button' value='Done' onClick='window.close();'>";
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', '$client');</script>";
		exit;
	}
}

else if(!$id && !$client) { // Client search
	findClients();
}

$operation = 'Add';
if($suppressSavePaymentButton) $operation = 'Charge';
if($id) {
	$operation = 'Edit';
	$credit = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $id");
	if(strpos($credit['externalreference'], 'CC: ') === 0) {
		$transactionid = substr($credit['externalreference'], strlen('CC: '));
		$creditcc = substr($credit['sourcereference'], strlen('CC: '));
		$wasCCPayment = fetchFirstAssoc("SELECT note FROM tblchangelog WHERE itemtable = 'ccpayment' AND note like '%|$transactionid' LIMIT 1");
		if(!$wasCCPayment)
			$wasCCPayment = fetchFirstAssoc("SELECT note FROM tblchangelog WHERE itemtable = 'ccpaymentadhoc' AND note like '%|$transactionid' LIMIT 1");
	}
	else if(strpos($credit['externalreference'], 'ACH: ') === 0) {
		$transactionid = substr($credit['externalreference'], strlen('ACH: '));
		$creditach = substr($credit['sourcereference'], strlen('ACH: '));
		$wasACHPayment = fetchFirstAssoc("SELECT note FROM tblchangelog WHERE itemtable = 'achpayment' AND note like '%|$transactionid' LIMIT 1");
	}
	
	$client = $credit['clientptr'];
	$prettyIssueDate = shortDate(strtotime($credit['issuedate']));
}
else {
	$credit = array('client'=>$client);
}

if($client) {
	$clientName = getOneClientsDetails($client);
	$clientName = $clientName['clientname'];
}

$header = "$operation a Payment";
	

$windowTitle = $header;
require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
  if($stopOnError) {
		echo '<center>';
		echoButton('', 'Close Window', 'window.close();');
		echo '</center>';
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', '$client');</script>";
		exit;
	}
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
<h2><?= $header.($id ? " from $clientName on $prettyIssueDate" : ($client ? " from $clientName" : '')) ?></h2>
<?

// ###################################################################################################
// CASE 1: Creating a Payment for an Unspecified Client
if(!$id && !$client) {
?>	
<h3>Step 1: Pick a Client</h3>
<form name="findclients" method="post" action="payment-edit.php">
<input name=target type=hidden value='<?= $target ?>'>
<input name=pattern size=10 autocomplete='off'> <? echoButton('', 'Search', "search()") ?>
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
	echo "<form name='editcredit' method='POST' action='payment-edit.php'>";
	if($successDestination) {echo "\n"; hiddenElement('successDestination', $successDestination);echo "\n";}
	hiddenElement('client', $client);
	hiddenElement('saveCredit', 1);
	hiddenElement('chargeCard', '');
	echo "<table>";
	if(!$id) {
		//echo "Client: $clientName";
		$amount = isset($amount) ? $amount : (isset($invoice) ? $invoice['balancedue'] : '');
		//inputRow('Date:', 'issuedate', shortDate());
		$initialDateTime = $issuedate ? strtotime($issuedate) : time();
		calendarSet('Date:', 'issuedate', shortDate($initialDateTime));
		inputRow('Total Payment:', 'amount', $amount, '', 'dollarinput');
	}
	else {
		$tipGroup = getPaymentTipGroup($id);
		if($credit['amountused'] > 0.0) {
			inputRow('Date:', 'issuedate', $prettyIssueDate);
			//labelRow('Date:', '', $prettyIssueDate);
			$paymentDisplay = str_replace('&nbsp;', ' ', dollarAmount($credit['amount']));
			if($tipGroup) $paymentDisplay .= ' + Gratuity: '.str_replace('&nbsp;', ' ', dollarAmount($tipGroup['total']));
			labelRow('Payment:', '', $paymentDisplay, null, null, null, null, 'raw');
			labelRow('Amount Used:', '', dollarAmount($credit['amountused']), null, null, null, null, 'raw');
		}
		else {
			inputRow('Date:', 'issuedate', $prettyIssueDate);
			//labelRow('Date:', '', $prettyIssueDate);
			if($tipGroup) {
				$gratuityDisplayDisplay = ' + Gratuity: '.str_replace('&nbsp;', ' ', dollarAmount($tipGroup['total']));
				$paymentLabel = 'Payment:';
			}
			else {
				$gratuityDisplayDisplay = '';
				$paymentLabel = 'Total Payment:';
			}
				
			if($credit['voided']) {
				$voidedDate = shortDate(strtotime($credit['voided']));				
				$paymentDisplay = "<font color='red'>".dollarAmount($credit['voidedamount'])." ($voidedDate)</font>";
				labelRow('VOIDED Payment:', '', $paymentDisplay, null, null, null, null, 'raw');
			}
			else inputRow($paymentLabel, 'amount', $credit['amount'], '', 'dollarinput');
			if($tipGroup) labelRow('', '', $gratuityDisplayDisplay, null, null, null, null, 'raw');
		}
		hiddenElement('id', $id);
	}
	if($wasCCPayment) {
		labelRow('CC transaction #:', '', $transactionid);
		labelRow('Credit Card:', '', $creditcc);
		hiddenElement('externalreference', $credit['externalreference']);
		hiddenElement('sourcereference', $credit['sourcereference']);
	}
	else if($wasACHPayment) {
		labelRow('ACH transaction #:', '', $transactionid);
		labelRow('Bank Account:', '', $creditach);
		hiddenElement('externalreference', $credit['externalreference']);
		hiddenElement('sourcereference', $credit['sourcereference']);
	}
	else if($suppressSavePaymentButton) {
		hiddenElement('externalreference', $credit['externalreference']);
		hiddenElement('sourcereference', $credit['sourcereference']);
	}
	else {
		$extRefVal = $credit['externalreference'] ? $credit['externalreference'] : $externalreference;
		inputRow('Check/transaction #:', 'externalreference', $extRefVal, '', 'Input45Chars');
		$srcRefVal = $credit['sourcereference'] ? $credit['sourcereference'] : $sourcereference;
		inputRow('Source Account:', 'sourcereference', $srcRefVal, '', 'Input45Chars');
	}
	$reason = isset($reason) ? $reason : (isset($invoice) ? 'Invoice # '.invoiceIdDisplay($invoice['invoiceid']) : $credit['reason']);
	//inputRow('Note:', 'reason', $reason, '', 'VeryLongInput');
	countdownInputRow(45, 'Note:', 'reason', $reason, '', 'Input45Chars', null, null, null, 'afterlabel');
	
	
	/*if($credit['voided']) {
		radioButtonRow('Hide this payment on future invoices?', 'hide', $credit['hide'], array('Yes'=>1, 'No'=>0), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
	}*/
	if(staffOnlyTEST() || $credit['voided']) {
		$prefix = $credit['voided'] ? '' : '(Staff Only) ';
		radioButtonRow("{$prefix}Hide this payment on future invoices?", 'hide', $credit['hide'], array('Yes'=>1, 'No'=>0), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
	}
	
	
	
	if($credit['voided'] && itemNoteIsEnabled()) {
		$itemnote = getItemNote('tblcredit', $credit['creditid'], 0);
		textRow('Void Note', 'itemnote', $itemnote['note'], $rows=3, $cols=90);
	}

	echo "</table>";
	
	if(!$credit['voided']) {
		if($tipGroup) dumpTipGroupDIV($tipGroup); // show gratuity
		else dumpGratuityForm($client);
	}
	else hiddenElement('payout', 0);

	
	hiddenElement('paybuttonlocked', '');
	$_SESSION['PaymentEditorDblClickToken'] = time();
	hiddenElement('PaymentEditorDblClickToken', $_SESSION['PaymentEditorDblClickToken']);
//if($_SESSION['staffuser']) echo "[{$_SESSION['PaymentEditorDblClickToken']}]";	
	echo "</form><p>";
	
	if(FALSE && mattOnlyTEST()) {
		$savePaymentLabel = $id ? "Save Changes" : "Register Payment";
		echoButton('', $savePaymentLabel, "checkAndSubmit()");
		echo "<img src='art/spacer.gif' width=30 height=1>";
		$ePaySource = getPrimaryPaySourceTypeAndID($client);
		if(!$id && $_SESSION['ccenabled'] && adequateRights('*cc') && $ePaySource) {
			$sourceLabel = $ePaySource['ccid'] ? 'Credit Card' : 'Bank Account';
			$chargeLabel = "Charge $sourceLabel";
			echoButton('', $chargeLabel, "chargeCreditCard($client)", 'BigButton', 'BigButtonDown');
			echo "<img src='art/spacer.gif' width=30 height=1>";
		}
	}
	else {
		if(!$suppressSavePaymentButton) {
			echoButton('', "Save Payment", "checkAndSubmit()");
			echo "<img src='art/spacer.gif' width=30 height=1>";
		}
		$ePaySource = getPrimaryPaySourceTypeAndID($client);
			
		if($sourceProblem = primaryPaySourceProblem($client))
			echo "$sourceProblem<img src='art/spacer.gif' width=30 height=1>";
		else if(!$id && $_SESSION['ccenabled'] && adequateRights('*cc') && $ePaySource) {
			$sourceLabel = $ePaySource['ccid'] ? 'Credit Card' : 'Bank Account';
			$chargeLabel = "Charge $sourceLabel";
			echoButton('', $chargeLabel, "chargeCreditCard($client)");
			echo "<img src='art/spacer.gif' width=30 height=1>";
		}
	}
		
	echoButton('', "Quit", 'window.close()');
	
	if($_SESSION['preferences']['enableManualPaymentReceipts']) {
		echo "<img src='art/spacer.gif' width=30 height=1>";
		fauxLink('Send Receipt', 'openThankYouNote()', 0, 'Open an email composer.');
	}
	
	
	echo "<image src='art/spacer.gif' WIDTH=100 HEIGHT=1>";
	if($id) {
		$billablesPaid = fetchRow0Col0("SELECT billableptr FROM relbillablepayment WHERE paymentptr = $id LIMIT 1");
		$refundWindowHeight = $billablesPaid ? 700 : 320;
		// find a refund for this payment
		$refund = fetchFirstAssoc("SELECT * FROM tblrefund WHERE paymentptr = '$id' LIMIT 1");
		if($refund)
			fauxLink("This payment was refunded", 
								"openConsoleWindow(\"refundedit\", \"refund-edit.php?id={$refund['refundid']}\", 600, 220)",
								null, 'Click here to view refund details.');
		else if(!$credit['voided']) echoButton('', "Refund This Payment", 
													"openConsoleWindow(\"refundedit\", \"refund-edit.php?payment=$id&client=$client\", 600, $refundWindowHeight)",
													'HotButton', 'HotButtonDown');
		echo " ";
		if(!$credit['voided']) {/*&& (strpos($credit['sourcereference'], 'CC:') === FALSE)*/
		  echoButton('', "VOID Payment", 'confirmAndVoid()', 'HotButton', 'HotButtonDown');
		}

		if(!$refund && $_SESSION['staffuser']) {/*&& (strpos($credit['sourcereference'], 'CC:') === FALSE)*/
		  echoButton('', "Delete Payment", 'confirmAndDelete()', 'HotButton', 'HotButtonDown');
		}


		if(staffOnlyTEST() && $tipGroup && !$voided) {
			echo "<p>";
			fauxLink('Analyze Gratuity', "parent.location.href=\"gratuity-analysis.php?paymentptr=$id\"");
		}
		
		$client = fetchFirstAssoc("SELECT userid, CONCAT_WS(' ' , fname, lname) as name FROM tblclient WHERE clientid = {$credit['clientptr']} LIMIT 1");
		if($credit['createdby'] == $client['userid'])
			$creatorName = "client {$client['name']}";
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require_once "common/init_db_common.php";
		if(!$creatorName)
			$creatorName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = '{$credit['createdby']}' LIMIT 1");
		if($credit['modified']) {
			$modifiedByName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = '{$credit['modifiedby']}' LIMIT 1");
		}
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
		
		if(staffOnlyTEST()) {
			if($id) {
				echo "<img src='art/spacer.gif' width=30 height=1>";
				fauxLink('View as Dedicated', "document.location.href=\"payment-edit-2stage.php?id=$id\"");
			}
			
			
if($credit['voided'] && staffOnlyTEST()) {echo " "; echoButton('', "Reapply Payment", 'reapplyCredit()', 'BlueButton', 'BlueButtonDown');			}
			
			echo "<div onclick='$(\"#analysis\").toggle();'>...";
			echo "<div id='analysis' style='padding:4px;display:none;width:500px;background:lightyellow;border:solid black 1px'><u>LT Staff Analysis:</u><p>";
			echo "Created: ".shortDateAndTime(strtotime($credit['created']))." by $creatorName";

			if($credit['voided']) echo "<p>VOIDED: ".shortDateAndTime(strtotime($credit['voided']));
			if($modifiedByName) echo "<p>Last Modified: ".shortDateAndTime(strtotime($credit['modified']))." by $modifiedByName";
			$associations = '';
			if($arr = fetchCol0("SELECT invoiceptr FROM relinvoicerefund WHERE refundptr = $id"))
				echo ($associations .= "<p>Associated with invoice(s): #".join(', #', $arr)."<br>");
			if($arr = fetchAssociationsKeyedBy(
					"SELECT refundptr, relrefundcredit.amount, tblrefund.issuedate as refunddate, tblrefund.amount as refundamount 
						FROM relrefundcredit LEFT JOIN tblrefund ON refundid = refundptr WHERE creditptr = $id", 'refundptr')) {
				foreach($arr as $k => $v) $arr[$k] = "#$k ({$v['refunddate']}, {$v['refundamount']}) amount refunded: {$v['amount']}";
				echo ($associations .= "<p>Associated with refund(s): <br>".join('<br>', $arr)."<br>");
			}
			if(!$associations) echo "<p>Unassociated with any refunds.";
			$parts = fetchAssociations($sql = 
				"SELECT itemtable, itemptr, monthyear, itemdate, billabledate, charge, paid
				FROM relbillablepayment
				LEFT JOIN tblbillable ON billableid = billableptr
				WHERE paymentptr = {$credit['creditid']}");
			if($parts) {
				echo "<p>Billables:<table border=1><tr><th>Type<th>Item<th>Item Date<th>Billable Date<th>Charge<th>Total Paid";
				foreach($parts as $part) {
					$itemDate = shortDate(strtotime($part['itemdate']));
					$billableDate = shortDate(strtotime($part['billabledate']));
					$type = $part['monthyear'] ? 'Monthly Fixed' : ($part['itemtable'] == 'tblappointment' ? 'Visit' : $part['itemtable']);
					echo "<tr><td>$type<td>{$part['itemptr']}<td>$itemDate<td>$billableDate
								<td>{$part['charge']}<td>{$part['paid']}";
				}
				echo "</table>";
			}
			else echo "<p>Not applied to any billables.";
			echo "</div></div>";
		}
		
		if((TRUE || staffOnlyTEST() || $_SESSION['preferences']['enablePaymentAppliedToLink']) && $id) { //staffOnlyTEST
			if(!$billablesTable = creditAppliedToTable($credit)) 
				echo "<div>Not yet applied to any visits or charges.</div>";
			else
				echo "<div onclick='$(\"#billablestable\").toggle();' class='fauxlink'>Applied To...</div>$billablesTable";
		}
		
		
		if($creatorName || $modifiedByName) {
			echo "<div style='font-size:0.8em;'>";
			echo !$creatorName ? '' : "Registered ".shortDateAndTime(strtotime($credit['created']))." by $creatorName<br>";
			echo !$modifiedByName ? '' : "Last edited ".shortDateAndTime(strtotime($credit['modified']))." by $modifiedByName<br>";
			echo "</div>";
		}
		
	}
	//echo " ";
	//if($id && ($_SESSION['staffuser'] || $credit['amountused'] == 0.0) && (strpos($credit['sourcereference'], 'CC:') === FALSE)) 
	//	echoButton('', "VOID Payment", 'voidCredit()', 'HotButton', 'HotButtonDown');
}

function confirmVoidDialog($credit) {
	if($credit['voided']) return;
	ob_start();
	ob_implicit_flush(0);
	$descr = ob_get_contents();
	echo "<table><tr><td colspan=2><h2>You are about to VOID this payment</h2></td></tr>";
	countdownInputRow(60, "Please explain why (optional):", 'voidreason', "", $labelClass=null, $inputClass='Input45Chars', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');	
	radioButtonRow('Hide this payment on future invoices?', 'hide', $value=1, array('Yes'=>1, 'No'=>0), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
	if(staffOnlyTEST() && fetchRow0Col0("SELECT gratuityid FROM tblgratuity WHERE paymentptr = {$credit['creditid']}")) 
		radioButtonRow('Retain gratuities?', 'retainGratuities', $value=0, array('Yes'=>1, 'No'=>0), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
	
	echo "</table>";
	echoButton('', "VOID Payment", 'parent.$.fn.colorbox.close();voidCredit(document.getElementById("voidreason").value, document.getElementById("hide_1").checked, (document.getElementById("retainGratuities_1") ? document.getElementById("retainGratuities_1").checked : ""))', 'HotButton', 'HotButtonDown');
	echo " ";
	echoButton('', "Quit - Do not VOID", 'parent.$.fn.colorbox.close();');
	$descr = ob_get_contents();
	ob_end_clean();
	return $descr;
}


?>
</div>

<div id='confirmation' style='display:none'><?= $id ? confirmVoidDialog($credit) : '' ?></div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
var currencyMark = '<?= getCurrencyMark() ? getCurrencyMark() : '$' ?>';
</script>
<script language='javascript' src='gratuity-fns.js'></script>
<script language='javascript' src='common.js'></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

var totalPaymentField = 'amount';

document.getElementById('payout').disabled = <?= $id ? 'true' : 'false' ?>;

function confirmAndDelete() {
	if(confirm("Sure you want to DELETE this payment?"))
		document.location.href='payment-edit.php?deleteCredit=<?= $id ?>';
}

function reapplyCredit() {
	//if(true || confirm("Sure you want to REAPPLY this payment?"))
	var showinvoice = '';
	if(confirm("Do you want to display this payment on future invoices?"))
		showinvoice = '&showinvoice=1';
	document.location.href='payment-edit.php?reapplyCredit=<?= $id ?>'+showinvoice;
}

function confirmAndVoid() {
	$.fn.colorbox({html:document.getElementById('confirmation').innerHTML, width:"500", height:"200", scrolling: true, opacity: "0.3"});
}

function voidCredit(reason, hide, retainGratuities) {
	document.location.href='payment-edit-2stage.php?voidCredit=<?= $id ?>&voidreason='+escape(reason)
	+'&hide='+(hide ? 1 : 0)
	+'&retainGratuities='+(retainGratuities ? 1 : 0);
}

function openThankYouNote() {
	var amount = '<?= $credit['amount'] ?>' != '' ? '<?= $credit['amount'] ?>' : document.getElementById('amount').value;
	var gratuity = document.getElementById('gratuityTotal') ? document.getElementById('gratuityTotal').value : '';
	if(gratuity != '' && document.getElementById('id') == null) amount = amount - gratuity;
	var clientid = document.getElementById('client').value;
	if(amount == '') alert('No amount has been specified.');
	//alert(amount+" / "+gratuity);
	//alert(amount+" / "+gratuity);
	openConsoleWindow('thanksemail', 
		'payment-thankyou-email.php?ids='+clientid+'&totalAmount='+amount+'&gratuity='+gratuity, 650, 650);
			
}






setPrettynames('client','Client','amount','Total Payment', 'gratuity', 'Gratuity Amount', 'issuedate', 'Date');	
	
function initialPick(initial) {
  document.location.href='payment-edit.php?linitial='+initial;
}

function pickClient(id, clientname, packageid) {
  document.location.href='payment-edit.php?client='+id;
}

function chargeCreditCard() {
<? if($_SESSION['ccenabled'] &&  !merchantInfoSupplied()) { ?>
	alert("Merchant credit card processing information is not set.");
	return;
<? } ?>	
	document.getElementById('chargeCard').value=1;
	checkAndSubmit();
}

function update(x, y, z) {
	if(window.opener.update) window.opener.update('account', null);
	if(document.getElementById('id'))
		document.location.href='payment-edit-2stage.php?id='+document.getElementById('id').value;
}

function checkAndSubmit() {
	if(document.getElementById('paybuttonlocked').value == 1) return;
	if(!document.getElementById('payout').checked && MM_validateForm(
		'client', '', 'R',
		'amount', '', 'R',
		'amount', '', 'UNSIGNEDFLOAT',
		'issuedate', '', 'R',
		'issuedate', '', 'isDate'
		)) {
		document.getElementById('paybuttonlocked').value = 1;
		document.editcredit.submit();
	}
	else if(document.getElementById('payout').checked && MM_validateForm(
		'client', '', 'R',
		'amount', '', 'R',
		'amount', '', 'FLOAT',
		'issuedate', '', 'R',
		'issuedate', '', 'isDate',
		<?= gratuityValidationArgs($id) ?>
		)) {
		document.getElementById('paybuttonlocked').value = 1;
		document.editcredit.submit();
	}
	
}

if(document.editcredit && document.editcredit.amount) {
	document.editcredit.amount.select();
}

<?
dumpPopCalendarJS();
?>

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
		$numFound = mysqli_num_rows(mysqli_query($baseQuery));
		if($numFound)
			$clients = fetchAssociations("$baseQuery ORDER BY lname, fname LIMIT 15");
	}
	else if(isset($linitial)) {
		$baseQuery = "$baseQuery AND lname like '$linitial%' ORDER BY lname, fname";
		$clients = fetchAssociations("$baseQuery");
		$numFound = count($clients);
	}
	else {
		$numFound = mysqli_num_rows(mysqli_query($baseQuery));
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

function dumpTipGroupDIV($tipGroup) {
	labeledCheckbox('Pay out gratuity', 'payout', 1, null, null, "", $boxFirst=false);
	hiddenElement('payoutDisabledButChecked', 1);
	echo "<p><div style='border: solid black 1px;'>";
	gratuitySection($clientid, null, $tipGroup);
	echo "</div>";
}

function dumpGratuityForm($clientid) {
	labeledCheckbox('Pay out gratuity', 'payout', null, null, null, 
			"document.getElementById(\"gratuityDIV\").style.display=(this.checked ? \"block\" : \"none\")", $boxFirst=false);
	echo "<div id='gratuityDIV' style='display:none;border: solid black 1px;'>";
	echo "<span style='font-size:1.2em;font-weight:bold;'>What portion of the Total Payment will be paid out in gratuities?</span><p>";
	gratuitySection($clientid, 'amount');
	echo "</div>";
}

if($_SERVER['REMOTE_ADDR'] == '173.73.2.113') {
	logChange(999, 'payment-edit', 'T', "Time: ".(microtime(1) - $time0));
	echo "<hr>Time: ".(microtime(1) - $time0);
}
