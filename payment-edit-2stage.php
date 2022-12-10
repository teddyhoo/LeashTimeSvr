<?
/* payment-edit-2stage.php
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
require_once "service-fns.php";
require_once "payment-dedicated-fns.php";


if($_SERVER['REMOTE_ADDR'] == '173.73.2.113') {
	$time0 = microtime(1);
}

$auxiliaryWindow = true; // prevent login from appearing here if session times out
$preserveSuccessDestination = $successDestination;
// Verify login information here
locked('o-');
$fields = 'saveCredit,voidCredit,deleteCredit,reapplyCredit,chargeCard,id,client,amount,externalreference,sourcereference,reason'
					.',voidreason,payout,payoutDisabledButChecked,gratuity,tipnote,linitial,itemnote,issuedate,hide,loginReturn,'
					.'successDestination,applyspecific,payby,action';
for($i=1;$i<=5;$i++) $fields .= ",gratuityProvider_$i,percent_$i";
extract(extractVars($fields, $_REQUEST));  // saveCredit, chargeCard, id, client, amount, externalreference, sourcereference, reason, payout, gratuity, 
										 //gratuityProvider_1, percent_1 ...gratuityProvider_5, percent_5, tipnote
if($preserveSuccessDestination && !$successDestination) $successDestination = $preserveSuccessDestination;
if($_REQUEST['suppressSavePaymentButton']) $suppressSavePaymentButton = $_REQUEST['suppressSavePaymentButton'];
$returned = $_POST['action'] == 'back';
if($returned) {
	$saveCredit = 0;
	$chargeCard = 0;
}
else {
	$chargeCard = $payby == 'epay' ? 1 : '0';
}

//if(mattOnlyTEST()) echo "[$sourcereference] returned: $returned<p>".print_r($_POST, 1);		// why is sourcereference missing on return?								 

$stage = 1;
//if(mattOnlyTEST()) {print_r($successDestination); eXxit;}
//if(mattOnlyTEST()) {echo "4: ".print_r($successDestination, 1).'<br>----'.print_r($_REQUEST, 1).'<br>----'; }
// STAGE 2 -- when applyspecific
if(!$returned && $action != 'paySpecific' && $applyspecific) {
	$stage = 2;
	require_once "service-fns.php";
	function cmplinedate($a, $b) {
		return strcmp((string)$a['linedate'], (string)$b['linedate']);
	}

	$targets = getPaymentApplicationTargets($client);
	
	$windowTitle = "Apply Payment to...";
	$extraBodyStyle = "padding:20px;";
	require "frame-bannerless.php";
	$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ' , fname, lname) FROM tblclient WHERE clientid = $client LIMIT 1");
	$message = "Use this ".dollarAmount($amount)
							." ".($payby == 'epay' ? 'electronic' : $payby)
							." payment"
							.($gratuity ? "<br>(".dollarAmount($amount-$gratuity)." after gratuities)<br>" : "")
							." to pay the expenses selected below for<br>$clientName.";
	
	if(!$targets) $message .= "<p>There are no visits, surcharges, or other charges to apply this payment to.";
	echo "<span class='h2'>$message</span><p>";
	//print_r($_POST);
	if(!$targets) {
		echoButton('backbutton', 'Go Back', 'goBack()');
	}
	else if($targets) {
		echo "<table width=500><tr><td>";

		fauxLink('Select All', 'selectAll(1)');
		echo ' - ';
		fauxLink('Deselect All', 'selectAll(0)');
		echo ' <div class="tiplooks" style="display:inline;padding-left:10px;" id="selectionCount"></div> ';
		echo "</td><td>";
		echoButton('backbutton', 'Go Back', 'goBack()');
		echo "</td><td align=right>";
		$_SESSION['PaymentEditorDblClickToken'] = time();
		hiddenElement('PaymentEditorDblClickToken', $_SESSION['PaymentEditorDblClickToken']);		
		echoButton('payspecificbbutton', 'Pay', 'paySpecific()', 'BigButton', 'BigButtonDown');
		echo "</td></tr></table>";
		echo "<form name='targetform' method='POST'>";
		hiddenElement('action', '');
		
		foreach($_POST as $k=>$v) {
			if($k == 'PaymentEditorDblClickToken') continue;
			echo "\n";
			if($k == 'paybuttonlocked') $v = '';
			hiddenElement($k, $v);
		}
		
		echo "\n";
		// Deal with PaymentEditorDblClickToken here?
		
		echo "<table width=500>";
		if($targets['other']) {
			usort($targets['other'], 'issueDateCmp');
			startAShrinkSection("Miscellaneous Charges", 'other', $hidden=false, $extraStyle='');
			echo "<table width=100%>"; //  MISC shrink
			foreach($targets['other'] as $item) {
				$date = shortDate(strtotime($item['linedate']));
				$itemamount = dollarAmount($item['amount']);
				echo "<tr><td width=20><input type='checkbox' id='other_{$item['chargeid']}' name='other_{$item['chargeid']}'></td>";
				echo "<td><label for='other_{$item['chargeid']}'>$date</label></td><td>$itemamount</td><td>{$item['label']}</td></tr>";
			}
			echo "</table>"; //  end MISC shrink
			endAShrinkSection();
		}
//if(mattOnlyTEST()) echo "<pre>".print_r($targets, 1)."</pre>";
		if($targets['nonrecurring']) {
			startAShrinkSection("Short Term Schedules", 'shortterm', $hidden=false, $extraStyle='');
			echo "<table width=100%>"; //  EZ shrink
			
			foreach($targets['nonrecurring'] as $packageid => $itemlist) {
//if(mattOnlyTEST()) echo "<hr>[$packageid]<pre>".print_r($targets['nonrecurring'], 1)."</pre>";
				$packageDescriptions[$packageid] = nonRecurringScheduleDetails($packageid);
/*				$finalcharge = 0;
				foreach($itemlist as $item) 
					$finalcharge += $item['finalcharge'];
				$packageDescriptions[$packageid]['amount'] = dollarAmount($finalcharge);
*/
			}
			
			uasort($packageDescriptions, 'startDateCmp');
			
			foreach($packageDescriptions as $packageid =>$item) {
				$date = $item['linedate'];
				$itemamount = $item['amount'];
				echo "<tr><td width=20><input type='checkbox' id='package_$packageid' name='package_$packageid'></td>";
				echo "<td><label for='package_$packageid'>$date</label></td><td>$itemamount</td><td>{$item['label']}</td></tr>";
			}
			echo "</table>"; // end EZ shrink
			endAShrinkSection();
		}
		if($targets['monthly']) {
			startAShrinkSection("Monthly Schedules", 'monthly', $hidden=false, $extraStyle='');
			echo "<table width=100%>";  // monthly shrink
			
			foreach($targets['monthly'] as $item) {
				$date = $item['label'];
				$itemamount = dollarAmount($item['amount']);
				$itemamount = "<span style='text-decoration:underline' title='Paid: ".dollarAmount($item['paid'])." Still Owed: ".dollarAmount($item['owed'])."'>$itemamount</span>";
				
				$billableid = $item['billableid'];
				echo "<tr><td width=20><input type='checkbox' id='monthly_$billableid' name='package_$billableid'></td>";
				echo "<td><label for='monthly_$billableid'>$date</label></td><td>$itemamount</td></tr>";
			}
			echo "</table>"; // end monthly shrink
			endAShrinkSection();
		}
		if($targets['weekly']) {
			startAShrinkSection("Regular Ongoing Visits", 'weekly', $hidden=false, $extraStyle='');
			echo "<table width=100%>";  // weekly shrink
			
			$items = $targets['weekly'];
			usort($items, 'dateTimeCmp');
			
			foreach($items as $item) {
				$date = shortDate(strtotime($item['linedate'])).' '.$item['timeofday'];
				$itemamount = dollarAmount($item['finalcharge']);
				$itemid = '@@UNKNOWN@@';
				if($item['appointmentid']) $itemid = "visit_{$item['appointmentid']}";
				else if($item['surchargeid']) $itemid = "surcharge_{$item['surchargeid']}";

				echo "<tr><td width=20><input type='checkbox' id='$itemid' name='$itemid'></td>";
				echo "<td><label for='$itemid'>$date</label></td><td>$itemamount</td><td>{$item['label']}</td></tr>";
			}
			echo "</table>"; // end weekly shrink
			endAShrinkSection();
		}
		echo "</table>";
		//echo "<pre>".print_r($targets, 1)."</pre>";
		$columns = explodePairsLine('linedate|Date||type| ||label|Description||owed||Owed');
		// Group table into: Short Term Schedule Visits, Ongoing Schedule Visits, Miscellaneous Charges, Monthly Packages
		if($targets['nonrecurring']) {
			// sort by linedate
			uksort($targets['nonrecurring'], 'cmplinedate');
		}
	}
	$saveCredit = 0;
?>
<script language='javascript'>
function paySpecific() {
	// MAKE SURE AT LEAST ONE CHARGE IS SELECTED
	var sels = 0;
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			if(cbs[i].checked) sels += 1;
	if(!sels) alert('Please select at least one expense to pay first.');
	else {
		document.getElementById('action').value = 'paySpecific';
		checkAndSubmit(document.targetform);
	}
}

function goBack() {
	document.getElementById('action').value='back';
	document.targetform.submit();
}

function selectAll(on) {
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			cbs[i].checked = on ? true : false;
	//updateSelectionCount();
}
function updateSelectionCount() {
	var cbs = document.getElementsByTagName('input');
	var boxcount = 0;
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled &&
				cbs[i].checked)
					boxcount++;
	document.getElementById('selectionCount').innerHTML = "Items selected: "+boxcount;
}
<?	dumpShrinkToggleJS();	?>

	</script>
<?
}

//echo "DONE: !returned [".(!$returned)."] && applyspecific [$applyspecific]<hr>";



$error = false;
// User has hit the "Charge Credit Card" button
// Confirm !expireCCAuthorization
if($loginReturn) $_REQUEST['PaymentEditorDblClickToken'] = $loginReturn; // preserve the token from the last payment editor

if($saveCredit && $client && $_SESSION['PaymentEditorDblClickToken'] != $_REQUEST['PaymentEditorDblClickToken']) {
		$error = "Button was clicked twice.  Please do not double-click buttons in LeashTime.<p>";
		$stopOnError = true;
}
//if(mattOnlyTEST()) {echo "<br>3: ".print_r($successDestination, 1); }

if(!$error && $saveCredit && $chargeCard && $client) {

	if(!adequateRights('*cc')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
		$error = "Insufficient Access Rights to charge client's card.";
		$chargeCard = false;
	}
	if(!$error) {
		$loginNeeded = is_array($expiration = expireCCAuthorization());
		if($loginNeeded) {
			$args = array("saveCredit=1","chargeCard=1","client=$client","payby=$payby", "reason=".urlencode ($reason), "amount=$amount",
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
			$backlink = substr($_SERVER["SCRIPT_NAME"], 1);  // usually payment-edit-2stage.php, but may be billing-invoice-charge-email.php
			$backlink = "payment-edit-2stage.php?".join('&', $args);
			include "cc-login.php";
			exit; // loginNeeded
		}
	}
}

unset($_SESSION['PaymentEditorDblClickToken']);
//if($_POST) {echo "ItemNote: [$itemnote] ID: $id ERROR: $error saveCredit: [$saveCredit]";eXxit;}
//if(mattOnlyTEST()) {echo "<br>2: ".print_r($successDestination, 1); }

if(!$error && $saveCredit) {
	if($id) {
		$client = fetchRow0Col0($sql = "SELECT clientptr FROM tblcredit WHERE creditid = $id LIMIT 1");
		$credit = array('reason'=>$reason, 'externalreference'=>$externalreference, 'sourcereference'=>$sourcereference, 
			'issuedate'=>date('Y-m-d', strtotime($issuedate)), 'hide'=>$hide);
		//if(fetchRow0Col0("SELECT creditid FROM tblcredit WHERE payment AND creditid = $id AND amountused = 0.00 LIMIT 1"))
		//	$credit['amount'] = $amount;
		updateTable('tblcredit', addModificationFields($credit), "creditid = $id", 1);
//echo "[$itemnote]";eXxit;
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
//if(mattOnlyTEST()) {echo "<br>1: ".print_r($successDestination, 1); eXxit;}
//if(mattOnlyTEST()) {
//echo "CHARGE: $chargeCard<p>".print_r($_REQUEST,1);
//exit;
//}
//if(mattOnlyTEST()) {print_r($_POST);exit;}
				$success = payElectronically($client, null, $amount, $reason, null, 'dontApplyPayment', ($gratuity ? $gratuity : 0));
				$newPaymentId = $latestPaymentId; //$latestPaymentId is globally set in payElectronically
				if(is_array($success)) {
					if($success['FAILURE']) $error = $success['FAILURE'];
					else $error = 'ERROR: '.ccLastMessage($success);
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
			}
			if(!$error) {	
				if($action == 'paySpecific') {
					// Here is where we deal with the selected items to apply the payment.
					// Register the items as targets for the payment
					dedicatePayment($client, $newPaymentId, $_POST);  // record the manager's dedication prefs
					spendDedicatedPayment($client, $newPaymentId); // spend as much of the payment as you can on the dedicated items
				}
				payOffClientBillables($client); // spend the rest of the payment (and all client's credits, if necessary) on outstanding billables

				if($newPaymentId && $payout) createGratuities($_REQUEST, $client, date('Y-m-d H:i:s', strtotime($issuedate)), $newPaymentId);
			}
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
if(0 && $_SERVER['REMOTE_ADDR'] == '173.73.2.113') {
	logChange(999, 'payment-edit', 'T', "Time: ".(microtime(1) - $time0));
	echo "<hr>Time: ".(microtime(1) - $time0);
}
			$closeWindow = '';
		}
/*if(mattOnlyTEST()) {
print_r($_REQUEST);
eXxit;
}*/
		echo "<script language='javascript'>/* *success */ if(window.opener.update) window.opener.update('account', '$client');$closeWindow</script>";
		exit; // Success
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
	echo "<script language='javascript'>/* voided */ if(window.opener.update) window.opener.update('account', '$client');</script>";
if($_SERVER['REMOTE_ADDR'] == '173.73.2.113') {
	logChange(999, 'payment-edit', 'T', "Time: ".(microtime(1) - $time0));
	echo "<hr>Time: ".(microtime(1) - $time0);
}
	exit;  // voidCredit
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
	echo "<script language='javascript'>/* deleted */ if(window.opener.update) window.opener.update('account', '$client');</script>";
	exit; // deleteCredit
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
if($stage == 1) {
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
	$customStyles = '
									 input[type="radio"]:checked+label {font-weight: normal; font-size:1.3em;}';
	$extraHeadContent = "<style>.extraHigh {line-height:23px;padding-top:5px;}.extraWideLabel {width:150px;}</style>";
	require "frame-bannerless.php";

	if($error) {  // very low level error
		echo "<p style='color:red'>$error</p>";
		if($stopOnError) {
			echo '<center>';
			echoButton('', 'Close Window', 'window.close();');
			echo '</center>';
			echo "<script language='javascript'> /* error */ if(window.opener.update) window.opener.update('account', '$client');</script>";
			exit; // stopOnError
		}
	}

	if(!function_exists('dollars')) {
		function dollars($amount) {
			$amount = $amount ? $amount : 0;
			return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
		}
	}

	//print_r($source);eXxit;
	?>
	<div style='padding: 10px;padding-top:0px;'>
	<h2><?= $header.($id ? " from $clientName on $prettyIssueDate" : ($client ? " from $clientName" : '')) ?></h2>
	<?
if($id) {	
	if($paymentDedication = describePaymentDedication($id)) {
		echo "This payment was applied first to <ul>";
		foreach($paymentDedication as $line) echo "<li>$line";
		echo "</ul>";
	}
}

	// ###################################################################################################
	// CASE 1: Creating a Payment for an Unspecified Client
	if(!$id && !$client) {
	?>	
	<h3>Step 1: Pick a Client</h3>
	<form name="findclients" method="post" action="payment-edit-2stage.php">
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
	else { // STAGE 1 FORM
		echo "<form name='editcredit' method='POST' action='payment-edit-2stage.php'>";
		if($successDestination) {echo "\n"; hiddenElement('successDestination', $successDestination);echo "\n";}
		hiddenElement('client', $client);
		hiddenElement('saveCredit', 1);
		hiddenElement('chargeCard', $chargeCard);
		echo "<table>";
		if(!$id) {
			//echo "Client: $clientName";
			$amount = isset($amount) ? $amount : (isset($invoice) ? $invoice['balancedue'] : '');
			//inputRow('Date:', 'issuedate', shortDate());
			calendarSet('Date:', 'issuedate', ($issuedate ? shortDate(strtotime($issuedate)) : shortDate()));
if(staffOnlyTEST()) {
	$accountBalance = getAccountBalance($client, /*includeCredits=*/true, /*allBillables*/false);
	$accountBalance = " Account balance (Staff Only): ".dollarAmount($accountBalance);
}
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null)

			inputRow('Total Payment:', 'amount', $amount, '', 'dollarinput', null, null, null, $accountBalance);
			if(!$id) {
				$applyOptions = array('Specific Charges'=>"1", 'Oldest Charges First'=>"0");
				$applyMethod = "1";
				radioButtonRow('Apply Payment to', 'applyspecific', $value=$applyMethod, $applyOptions, $onClick='applySpecificClicked()', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=3);
				//checkboxRow('Apply to Specific Charges<br>(shown next)', 'applyspecific', $value=$applyspecific, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange='applySpecificClicked()', $rowClass=null);
				$paybyOptions = array();
				$ePaySource = getPrimaryPaySourceTypeAndID($client);
				if($ePaySource && $_SESSION['ccenabled'] && adequateRights('*cc')) 
					$sourceLabel = $ePaySource['ccid'] ? 'Credit Card' : 'Bank Account';
				if($sourceLabel) {
					$icon = $ePaySource['ccid'] ? '<img src="art/payby-creditcard.jpg"> ' : '';
					$paybyOptions[$icon.$sourceLabel] = 'epay';
					if(!$payby) $payby = 'epay';
				}
				$paybyOptions['<img src="art/payby-check.png"> Check '] = 'check';
				$paybyOptions['<img src="art/payby-cash.png"> Cash'] = 'cash';
				$paybyOptions['<img src="art/payby-paypal.png">'] = 'paypal';
				$paybyOptions['<img src="art/payby-wire.png">'] = 'wire';
				$paybyOptions[' Other'] = 'other';

				radioButtonRow('Pay by...', 'payby', $value=$payby, $paybyOptions, $onClick='paybyClicked(this)', 
												$labelClass='extraWideLabel', $inputClass='extraHigh', $rowId=null,  $rowStyle=null, 
												$breakEveryN=1, $nonBreakingSpaceLabels=false);
			}
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
			$externalReference = $returned ? $externalreference : ($credit['externalreference'] ? $credit['externalreference'] : $externalreference);
			$sourcereference = $returned ? $sourcereference : $credit['sourcereference'];
			inputRow('Check/transaction #:', 'externalreference', $externalReference, '', 'Input45Chars');
			if(staffOnlyTEST()) $extraContent = 
				fauxLink('Lookup (Staff Only) ', "openConsoleWindow(\"lookup\", \"past-check-accounts.php?id=$client\", 400, 420)", 1, 2);

			inputRow('Source Account:', 'sourcereference', $sourcereference, '', 'Input45Chars', $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent);
//function inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null)
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
		echo "</form><p>"; // editcredit


		$buttonLabel = $id && !$suppressSavePaymentButton ? 'Save Payment' : (!$id ? 'Make Payment' : null);
		if($buttonLabel) {
			echoButton('paybutton', $buttonLabel, "checkAndSubmit(document.editcredit)");
			echo "<img src='art/spacer.gif' width=30 height=1>";
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
			if((!$refund || staffOnlyTEST()) && !$credit['voided']) {/*&& (strpos($credit['sourcereference'], 'CC:') === FALSE)*/
				echoButton('', "VOID Payment", 'confirmAndVoid()', 'HotButton', 'HotButtonDown');
			}

			if(!$refund && $_SESSION['staffuser']) {/*&& (strpos($credit['sourcereference'], 'CC:') === FALSE)*/
				echoButton('', "Delete Payment", 'confirmAndDelete()', 'HotButton', 'HotButtonDown');
			}

			if($credit['amountused'] > 0.0) {
				$billables = fetchAssociations(
					"SELECT b.*, bp.amount as applied
						FROM relbillablepayment bp
						LEFT JOIN tblbillable b ON billableid = billableptr
						WHERE paymentptr = $id");
				echo billableTable($billables);
			}

			if(staffOnlyTEST() && $tipGroup && !$voided) {
				echo "<p>";
				fauxLink('Analyze Gratuity', "parent.location.href=\"gratuity-analysis.php?paymentptr=$id\"");
			}
			$client = fetchFirstAssoc("SELECT userid, CONCAT_WS(' ' , fname, lname) as name FROM tblclient WHERE clientid = {$credit['clientptr']} LIMIT 1");
			if($credit['createdby'] == $client['userid'])
				$creatorName = "client {$client['name']}";
			if(staffOnlyTEST()) {
				list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
				require_once "common/init_db_common.php";
				if(!$creatorName)
					$creatorName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = '{$credit['createdby']}' LIMIT 1");
				if($credit['modified']) {
					$modifiedByName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = '{$credit['modifiedby']}' LIMIT 1");
				}
				reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);

if($credit['voided'] && staffOnlyTEST()) {echo " "; echoButton('', "Reapply Payment", 'reapplyCredit()', 'BlueButton', 'BlueButtonDown');			}
				echo "<div onclick='$(\"#analysis\").toggle();'>...";
				echo "<div id='analysis' style='padding:4px;display:none;width:500px;background:lightyellow;border:solid black 1px'><u>LT Staff Analysis:</u><p>";
				echo "Created: ".shortDateAndTime(strtotime($credit['created']))." by $creatorName ({$credit['createdby']})";

				if($credit['voided']) echo "<p>VOIDED: ".shortDateAndTime(strtotime($credit['voided']));
				if($modifiedByName) echo "<p>Last Modified: ".shortDateAndTime(strtotime($credit['modified']))." by $modifiedByName ({$credit['modifiedby']})";
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
				$parts = fetchAssociations("SELECT itemtable, itemptr, monthyear, itemdate, billabledate, charge, paid
																		FROM relbillablepayment
																		LEFT JOIN tblbillable ON billableid = billableptr
																		WHERE paymentptr = {$credit['creditid']}");
				if($parts) {
					echo "<p>Applied to Billables:<table border=1><tr><th>Type<th>Item<th>Item Date<th>Billable Date<th>Charge<th>Total Paid";
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
			if($creatorName || $modifiedByName) {
				echo "<div style='font-size:0.8em;'>";
				echo !$creatorName ? '' : "Registered ".shortDateAndTime(strtotime($credit['created']))." by $creatorName<br>";
				echo !$modifiedByName ? '' : "Last edited ".shortDateAndTime(strtotime($credit['modified']))." by $modifiedByName<br>";
				echo "</div>";
			}
			
		}  // END if($id)
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

<? } // if $stage == 1)
?>

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

if(document.getElementById('payout')) document.getElementById('payout').disabled = <?= $id ? 'true' : 'false' ?>;

function confirmAndDelete() {
	if(confirm("Sure you want to DELETE this payment?"))
		document.location.href='payment-edit-2stage.php?deleteCredit=<?= $id ?>';
}

function reapplyCredit() {
	//if(true || confirm("Sure you want to REAPPLY this payment?"))
	var showinvoice = '';
	if(confirm("Do you want to display this payment on future invoices?"))
		showinvoice = '&showinvoice=1';
	document.location.href='payment-edit.php?reapplyCredit=<?= $id ?>'+showinvoice;
}

function confirmAndVoid() {
	$.fn.colorbox({html:document.getElementById('confirmation').innerHTML, width:"500", height:"260", scrolling: true, opacity: "0.3"});
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






setPrettynames('client','Client','amount','Total Payment', 'gratuity', 'Gratuity Amount', 'issuedate', 'Date', 'payby', 'Pay By');	
	
function initialPick(initial) {
  document.location.href='payment-edit-2stage.php?linitial='+initial;
}

function pickClient(id, clientname, packageid) {
  document.location.href='payment-edit-2stage.php?client='+id;
}

function update(x, y, z) {
	if(window.opener.update) window.opener.update('account', null);
	if(document.getElementById('id'))
		document.location.href='payment-edit-2stage.php?id='+document.getElementById('id').value;
}

function checkAndSubmit(formToSubmit) {
	var noEpayAllowed = null;
	var stage = "<?= $stage ?>";
	if(document.getElementById('paybuttonlocked').value == 1) return;
<? if($_SESSION['ccenabled'] &&  !merchantInfoSupplied()) {?>
	if(document.getElementById('payby_epay') && document.getElementById('payby_epay').checked)
		noEpayAllowed = "Merchant credit card processing information is not set.";
<? } ?>	
	
	var payoutChecked = document.getElementById('payout') ? document.getElementById('payout').checked : false;
	
	if(!payoutChecked && MM_validateForm(
		'client', '', 'R',
		'amount', '', 'R',
		'amount', '', 'UNSIGNEDFLOAT',
		'issuedate', '', 'R',
		'issuedate', '', 'isDate',
		<? if(!$id) echo "'payby', '', (stage == 1 ? 'RRADIO' : 'R'),\n" ?>
		noEpayAllowed, '', 'MESSAGE'
		)) {
		document.getElementById('paybuttonlocked').value = 1;
		formToSubmit.submit();
	}
	else if(payoutChecked && MM_validateForm(
		'client', '', 'R',
		'amount', '', 'R',
		'amount', '', 'UNSIGNEDFLOAT',
		'issuedate', '', 'R',
		'issuedate', '', 'isDate',
		<? if(!$id) echo "'payby', '', (stage == 1 ? 'RRADIO' : 'R'),\n" ?>
		noEpayAllowed, '', 'MESSAGE'
		<?= $stage == 1 ? ", ".gratuityValidationArgs($id) : '' ?>
		)) {
		document.getElementById('paybuttonlocked').value = 1;
		formToSubmit.submit();
	}
	
}

function applySpecificClicked() {
	if(!document.getElementById('applyspecific_1')) return;
	var label = document.getElementById('applyspecific_1').checked ? 'Next' : 'Make Payment';
	document.getElementById('paybutton').value = label;
}

function paybyClicked(el) {
	<? if(!$id) { ?>
	var val, elform = (el ? el.form : document.forms[0]), 
		display = '<?= $_SESSION['tableRowDisplayMode'] ?>';
	for(var i=0; i<elform.elements.length; i++) {
		var el2 = elform.elements[i];
		if(el2.type=='radio' && el2.name == 'payby' && el2.checked)
			val = el2.value;
	}
	if(val == 'epay') display='none';
	document.getElementById('sourcereference').parentNode.parentNode.style.display=display;
	document.getElementById('externalreference').parentNode.parentNode.style.display=display;
	if(val == 'cash') document.getElementById('sourcereference').value = 'Cash';
	else if(val == 'paypal') document.getElementById('sourcereference').value = 'PayPal';
	else document.getElementById('sourcereference').value = '';
	<? } ?>
}

function payoutClicked() {
	if(document.getElementById("gratuityDIV"))
		document.getElementById("gratuityDIV").style.display=(document.getElementById("payout").checked ? "block" : "none");
}

if(document.editcredit && document.editcredit.amount) {
	document.editcredit.amount.select();
}
paybyClicked();
applySpecificClicked();
payoutClicked();

/*if $_POST['payout'] */
if(document.getElementById('gratuityProvider_1') && document.getElementById('gratuityProvider_1').type != 'hidden')
	updatePortion(1);

<?
dumpPopCalendarJS();

dumpShrinkToggleJS();
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

function dumpTipGroupDIV($tipGroup) {
	labeledCheckbox('Pay out gratuity', 'payout', 1, null, null, "", $boxFirst=false);
	hiddenElement('payoutDisabledButChecked', 1);
	echo "<p><div style='border: solid black 1px;'>";
	gratuitySection($clientid, null, $tipGroup);
	echo "</div>";
}

function dumpGratuityForm($clientid) {
	labeledCheckbox('Pay out gratuity', 'payout', $_POST['payout'], null, null, 
			"payoutClicked()", $boxFirst=false);
	echo "<div id='gratuityDIV' style='display:none;border: solid black 1px;'>";
	echo "<span style='font-size:1.2em;font-weight:bold;'>What portion of the Total Payment will be paid out in gratuities?</span><p>";
	gratuitySection($clientid, 'amount');
	echo "</div>";
}

function issueDateCmp($a, $b) { 	return strcmp($a['issuedate'], $b['issuedate']); }
function startDateCmp($a, $b) { 	return strcmp($a['startdate'], $b['startdate']); }
function dateTimeCmp($a, $b) { 	return strcmp(trim("{$a['date']} {$a['starttime']}"), trim("{$b['date']} {$b['starttime']}")); }

function nonRecurringScheduleDetails($packageid) {
	global $targets, $allCurrentPackageIds, $nonRecurringHistories;

	$p = fetchFirstAssoc("SELECT * FROM tblservicepackage WHERE packageid = $packageid LIMIT 1");
	//$allvisits = fetchAllAppointmentsForNRPackage($p, $p['clientptr']);
	
	$history = $nonRecurringHistories[$packageid];
	$history = join(',', $history);
//if(mattOnlyTEST()) echo "### ID: $packageid, CLIENT: {$p['clientptr']} HIST: $history";
	$allvisits = fetchAssociations(
		"SELECT * 
			FROM tblappointment
			WHERE packageptr IN ($history) AND canceled IS NULL
			ORDER BY date, starttime");
//$xcx = 0; foreach($allvisits as $v) $xcx += $v['charge']+$v['adjustment'];
//if(mattOnlyTEST()) echo "ID: $packageid, CLIENT: $clientptr TOTAL: $xcx visits:<hr>".print_r($targets,1);
	
	foreach($allvisits as $appt) $allDays[$appt['date']] = 1;
	
	$details['startdate'] = $p['startdate'];
	$details['linedate'] = shortDate(strtotime($p['startdate']));
	if($p['enddate'] && $p['enddate'] != $p['startdate']) $details['linedate'] .= " - ".shortDate(strtotime($p['enddate']));
	$visitTypes = array();
	foreach($targets['nonrecurring'][$packageid] as $appt)
		if($appt['servicecode']) $visitTypes[] = $appt['servicecode'];
	$visitTypes = join(', ', array_unique($visitTypes));
	$details['label'] = "<span title=".safeValue($visitTypes).">"
				.($p['irregular'] ? 'EZ Schedule' : $visitTypes)
				.' ('
				.count($allDays)." day".(count($allDays) > 1 ? 's' : '')
				." / "
				.count($allvisits)." visit".(count($allvisits) > 1 ? 's' : '')
				.")</span>";
	//$details['amount'] = dollarAmount($p['packageprice']);
	// override this packageprice, which may be wrong
	// 1. for each uncanceled visit, calculate the total charge
	$packagePrice = 0;
	$totalPaid = 0;
	$totalOwed = 0;
	foreach($allvisits as $appt) {
		$item = applicableVisitOrSurcharge('tblappointment', $appt['appointmentid'], 'includePaidItems');
		$totalPaid += $item['paid'];
		$totalOwed += $item['finalcharge'] - $item['paid'];
		$packagePrice += $item['finalcharge'];
	}
	// 2. for each uncanceled surcharge, calculate the total charge
	$allsurcharges = fetchAssociations(
		"SELECT * 
			FROM tblsurcharge
			WHERE packageptr IN ($history) AND canceled IS NULL
			ORDER BY date, starttime");
	foreach($allsurcharges as $surch) {
		$item = applicableVisitOrSurcharge('tblsurcharge', $surch['surchargeid'], 'includePaidItems');
		$totalPaid += $item['paid'];
		$totalOwed += $item['finalcharge'] - $item['paid'];
		$packagePrice += $item['finalcharge'];
	}
	$details['amount'] = dollarAmount($packagePrice);
	$details['amount'] = 
		"<span style='text-decoration:underline' title='Paid: ".dollarAmount($totalPaid)." Still Owed: ".dollarAmount($totalOwed)."'>{$details['amount']}</span>";
	return $details;
}

function getPaymentApplicationTargets($clientid) {
	global $allCurrentPackageIds, $nonRecurringHistories, $allHistories;
	// find all unpaid, non-canceled visits, EVEN MONTHLY VISITS
	$sql = 
		"SELECT v.appointmentid, v.charge+IFNULL(v.adjustment, 0) as visitcharge,  v.packageptr,
			IFNULL(d.amount, 0) as discount, b.billableid, 
			IF(b.charge IS NULL, 
				v.charge+IFNULL(v.adjustment, 0)-IFNULL(d.amount, 0),
				b.charge - b.paid
				) as owed,
			v.charge+IFNULL(v.adjustment, 0)-IFNULL(d.amount, 0) as amount,
			b.charge as bcharge, date as linedate, date as date,
			v.starttime,
			timeofday,
			v.recurringpackage as recurring,
			t.label as label
			FROM tblappointment v
			LEFT JOIN relapptdiscount d ON d.appointmentptr = v.appointmentid
			LEFT JOIN tblbillable b ON b.itemptr = v.appointmentid AND b.itemtable = 'tblappointment' AND b.superseded = 0
			LEFT JOIN tblservicetype t ON t.servicetypeid = v.servicecode
			WHERE v.clientptr = $clientid AND v.canceled IS NULL
				AND v.charge+IFNULL(v.adjustment, 0) > 0
				AND (b.paid IS NULL OR b.paid < b.charge)
			ORDER BY date, starttime";
	$allAppts = fetchAssociations($sql);
	
	$allRecurringPackages = fetchAssociationsKeyedBy("SELECT * FROM tblrecurringpackage WHERE clientptr = $clientid", 'packageid');
	
	// UNSET MONTHLY VISITS
	foreach($allAppts as $i => $appt)
		if($allRecurringPackages[$appt['packageptr']['monthly']])
			unset($allAppts[$i]);
	
	
	//ALL recurring visits will be lumped together
	/*$currentRecurringPackages = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE clientptr = $clientid and current = 1");
	$recurringHistories = findPackageHistories($clientid, 'R'); // $version => $history
	$allCurrentPackageIds = array();
	foreach($recurringHistories as $version => $history) {
		foreach($allAppts as $i => $appt) {
			if(!array_key_exists($appt['packageptr'], $allCurrentPackageIds)) 
				if(in_array($appt['packageptr'], $history) && in_array($version, $currentRecurringPackages))
					$allCurrentPackageIds[$appt['packageptr']] = $version;
			$allAppts[$i]['currentpackage'] = $allCurrentPackageIds[$appt['packageptr']];
		}
	}*/

	$allCurrentPackageIds = array();
	$allNonRecurringPackages = fetchAssociations("SELECT * FROM tblservicepackage WHERE clientptr = $clientid", 'packageid');
	$nonRecurringHistories = findPackageHistories($clientid, 'N');	
	$recurringHistories = findPackageHistories($clientid, 'R');
	foreach($nonRecurringHistories as $k => $v) $allHistories[$k] = $v;
	foreach($recurringHistories as $k => $v) $allHistories[$k] = $v;
	//$currentNonRecurringPackages = fetchCol0("SELECT packageid FROM tblservicepackage WHERE clientptr = $clientid and current = 1");
	// PROBLEM: Sometimes old packages are remaining current!  Capture only the latest current package
	foreach($nonRecurringHistories as $version => $history) {
		foreach($allAppts as $i => $appt) {
			if(!array_key_exists($appt['packageptr'], (array)$allCurrentPackageIds)) 
				if(in_array($appt['packageptr'], (array)$history) && $version == findLatestPackageVersion($version, $nonRecurringHistories)) { // in_array($version, $currentNonRecurringPackages)
					$allCurrentPackageIds[$appt['packageptr']] = $version;
					//$currentNonRecurringPackages[] = $version;
				}
			$allAppts[$i]['currentpackage'] = $allCurrentPackageIds[$appt['packageptr']];
		}
	}
	
	$sql = 
		"SELECT s.surchargeid, s.charge, s.packageptr,
			b.billableid, b.paid, s.date as linedate,
			s.date as date,
			s.starttime as starttime,
			IF(b.charge IS NULL, 
				s.charge,
				b.charge - b.paid
				) as owed,
			s.charge as amount,
			CONCAT_WS(' ', 'Surcharge:', t.label) as label
			FROM tblsurcharge s
			LEFT JOIN tblbillable b ON b.superseded = 0 AND b.itemptr = s.surchargeid AND b.itemtable = 'tblsurcharge'
			LEFT JOIN tblsurchargetype t ON t.surchargetypeid = s.surchargecode
			WHERE s.clientptr = $clientid AND s.canceled IS NULL
				AND s.charge > 0
				AND (b.billableid IS NULL OR b.paid < b.charge)";
	$allSurcharges = fetchAssociations($sql);
	global $errors;
	$testHistories = mattOnlyTEST() ? $allHistories : $nonRecurringHistories; // I no longer remember why this was done
//if(mattOnlyTEST()) echo "PACKS: <u><li>".join('<li>', array_keys($testHistories))."</u><p>==>".print_r(findLatestPackageVersion(3110, $testHistories));
	
	/*foreach($testHistories as $version => $history) {
		foreach($allSurcharges as $i => $surch) {
			if(!$surch) continue;
			$surchPack = $surch['packageptr'];
if(mattOnlyTEST() && $surch) echo "$surchPack: ".print_r($history, 1)."<p>";
			if(!array_key_exists($surchPack, $allCurrentPackageIds)) 
				if(in_array($surchPack, (array)$history)  && $version == findLatestPackageVersion($version, $testHistories))  //&& in_array($version, $currentNonRecurringPackages))
					$allCurrentPackageIds[$surchPack] = $version;
//if(mattOnlyTEST() && $version == 3110) echo print_r($surch, 1)."<p>$version={$surch['packageptr']} [".print_r($history, 1)."]: ".print_r($allCurrentPackageIds, 1);
			if(!$allCurrentPackageIds[$surchPack]) {
				if(mattOnlyTEST()) $errors[] = "Surcharge #{$surch['surchargeid']} ({$surch['date']} - {$surch['label']}) belongs to nonexistent package #{$surch['packageptr']}";
				unset($allSurcharges[$i]);
			}
			else $allSurcharges[$i]['currentpackage'] = $allCurrentPackageIds[$surch['packageptr']];
		}
	}*/
	
	foreach($allSurcharges as $i => $surch) {
		$surchPack = $surch['packageptr'];
		foreach($testHistories as $version => $history) {
			if(!array_key_exists($surchPack, $allCurrentPackageIds)) 
				if(in_array($surchPack, (array)$history)  && $version == findLatestPackageVersion($version, $testHistories))  //&& in_array($version, $currentNonRecurringPackages))
					$allCurrentPackageIds[$surchPack] = $version;
		}
	}
	foreach($allSurcharges as $i => $surch) {
		if(!$allCurrentPackageIds[$surchPack]) {
			if(staffOnlyTEST()) $errors[] = "Surcharge #{$surch['surchargeid']} ({$surch['date']} - {$surch['label']}) belongs to nonexistent package #{$surch['packageptr']}";
			unset($allSurcharges[$i]);
		}
		else $allSurcharges[$i]['currentpackage'] = $allCurrentPackageIds[$surch['packageptr']];
	}
	
	
	
//if(mattOnlyTEST() && $errors) echo "ERRORS: <u><li>".join('<li>', $errors)."</u>";
	// WHY DO WE IGNORE SURCHARGES FROM RECURRING SCHEDULES?
//if(mattOnlyTEST()) print_r($allSurcharges);	
	$allSurcharges = array_merge($allSurcharges);
	
		
	foreach($allAppts as $i => $appt) {
		// figure total for each appt, with billable or not
		$discountedAndTaxed = applicableVisitOrSurcharge('tblappointment', $appt['appointmentid']);
		$appt['finalcharge'] = $discountedAndTaxed['finalcharge'];
//if($discountedAndTaxed['bcharge'] == 0.0) print_r($discountedAndTaxed);
		$allAppts[$i]['finalcharge'] = $appt['finalcharge'];
		$allAppts[$i]['owed'] = $appt['billableid'] 
			? $appt['bcharge'] - $appt['paid'] 
			: $appt['finalcharge'];
		if($appt['recurring']) $targets['weekly'][] = $appt;
		else $targets['nonrecurring'][$appt['currentpackage']][] = $appt;
	}
	
//echo "COUNT nonrecurring schedules: ".count($targets['nonrecurring']).'';	
//foreach($targets['nonrecurring'] as $id => $visits) echo "<br>[$id] ".count($visits);
//echo "<hr>COUNT weekly visits: ".count($targets['weekly']).'<hr>';	
	
	
	foreach($allSurcharges as $i => $surch) {
//if(mattOnlyTEST() && !$surch['surchargeid']) echo "<p>Surch: [".print_r($surch,1)."]";	
		if(!$surch['surchargeid']) continue; // popped up in dogslife.  prob nothing, b
		$discountedAndTaxed = applicableVisitOrSurcharge('tblsurcharge', $surch['surchargeid']);
		$surch['finalcharge'] = $discountedAndTaxed['finalcharge'];
		$recurring = array_key_exists($surch['packageptr'], $allRecurringPackages);
		$allSurcharges[$i]['finalcharge'] = $surch['finalcharge'];
		$allSurcharges[$i]['owed'] = $surch['billableid'] 
			? $surch['paid'] - $surch['bcharge'] 
			: $surch['finalcharge'];
		if($recurring) $targets['weekly'][] = $surch;
		else $targets['nonrecurring'][$surch['currentpackage']][] = $surch;
	}
	
	
	// Handle Misc charges
	$sql = 
		"SELECT tblothercharge.*, b.billableid, b.charge-b.paid as owed, issuedate as linedate, reason as label
			FROM tblothercharge
			LEFT JOIN tblbillable b ON b.superseded = 0 AND b.itemptr = chargeid AND b.itemtable = 'tblothercharge'
			WHERE tblothercharge.clientptr = $clientid
				AND amount > 0
				AND (b.billableid IS NULL OR b.paid < b.charge)";
	$allCharges = fetchAssociations($sql);
	foreach($allCharges as $charge) $targets['other'][$charge['chargeid']] = $charge;
	

	// Handle Monthlies
	$sql = 
		"SELECT p.*, b.charge as amount, b.billableid, paid, b.charge-b.paid as owed, monthyear,
			monthyear as linedate
			FROM tblbillable b
			LEFT JOIN tblrecurringpackage p ON p.packageid = b.itemptr
			WHERE b.clientptr = $clientid 
				AND itemtable = 'tblrecurringpackage'
				AND b.paid < b.charge
			ORDER BY monthyear";
	$targets['monthly'] = fetchAssociations($sql);
	foreach($targets['monthly'] as $i => $target)
		$targets['monthly'][$i]['label'] = date('F Y', strtotime($target['monthyear']))." Service";

	/*$owed = 0;
	foreach((array)($targets['recurring']) as $appt) $owed += $appt['owed'];
	$targets['recurring']['owed'] = $owed;
	
	$owed = 0;
	foreach((array)($targets['nonrecurring']) as $packageptr => $appts) {
		foreach($appts as $appt)
			$owed += $appt['owed'];
	}
	if($owed) $targets['norecurring'][$packageptr]['owed'] = $owed;
	*/
//if(mattOnlyTEST()) echo "<hr>targets: ".print_r($targets,1);
	return $targets;
}
	
	
	
	
if($_SERVER['REMOTE_ADDR'] == '173.73.2.113') {
	logChange(999, 'payment-edit', 'T', "Time: ".(microtime(1) - $time0));
	echo "<hr>Time: ".(microtime(1) - $time0);
}
