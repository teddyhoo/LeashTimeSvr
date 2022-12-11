<?
/* charge-edit.php
*
* Parameters: 
* id - id of charge to be edited
* - or -
* client - id of client to be charged
*
* charge may not be modified (except for reason) once XXX
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "credit-fns.php";
require_once "gui-fns.php";
require_once "item-note-fns.php";
require_once "js-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract(extractVars('id,amount,reason,issuedate,lastday,client,saveCharge,linitial,target,pattern,oldamount,itemnote,itemnotesessionvar,deleteCharge', $_REQUEST));

$autoCredit = $_SESSION['preferences']['autocredit'];
// {"reason": "Test credit 15%", "amount": 15, "percent": "true", "issuedate": "3/24/2020", "expires": "3/27/2020",}

if($autoCredit) {

	$autoCredit = json_decode($autoCredit, 'assoc');
	if($autoCredit['expires'] && strtotime($autoCredit['expires']) < time())
		$autoCredit = null;
}

// itemnotesessionvar, when present, is the name of a $_SESSION variable which should be used
// to fetch a value for itemnote.  This feature was introduced to deal with painfully long itemnotes
// being passed in via GETs.  itemnotesessionvar should be unset after use.
if($itemnotesessionvar) {
//echo  "[$itemnotesessionvar]<hr>{$_SESSION[$itemnotesessionvar]}<hr>".print_r($_SESSION,1);exit;
//echo  "FRUITIES_55<hr>{$_SESSION["FRUITIES_55"]}";exit;
	
	$itemnote = $_SESSION[$itemnotesessionvar];
//	unset($_SESSION[$itemnotesessionvar]);
}

if($saveCharge) {
	if($id) {
		
		$charge = array('reason'=>$reason, 'amount'=>$amount);
		updateTable('tblothercharge', $charge, "chargeid = $id", 1);
		if(itemNoteIsEnabled()) {
			if($itemnote)
				updateNote(array('itemtable'=>'tblothercharge', 'itemptr'=>$id, 'priornoteptr'=>0), $itemnote);
			else deleteNote(array('itemtable'=>'tblothercharge', 'itemptr'=>$id));
		}
		$charge['issuedate'] = date('Y-m-d', strtotime($issuedate));
		require_once "invoice-fns.php";
		if(!($skipNewBillable = (float)$amount == (float)$oldamount))
			supersedeChargeBillable($id);
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', 'charge');window.close();</script>";
		//exit;
	}
	else {
		if($client) {
			$charge = array('reason'=>$reason, 'amount'=>$amount, 'clientptr'=>$client, 'issuedate'=>date('Y-m-d', strtotime($issuedate)));
			$chargeid = insertTable('tblothercharge', $charge, 1);
			// create a billable and pay it off if possible -- see appt completion code
			if(itemNoteIsEnabled()) {
				if($itemnote) {
					updateNote(array('itemtable'=>'tblothercharge', 'itemptr'=>$chargeid, 'priornoteptr'=>0), $itemnote);
					//replaceTable('relitemnote', array('note'=>$itemnote, 'itemtable'=>'tblothercharge', 'itemptr'=>$chargeid), 1);
				}
			}
			// SEE ABOVE $autoCredit = $_SESSION['preferences']['autocredit'];
			if($autoCredit) {
				$creditAmount = 
					$autoCredit['percent'] ? round($amount*$autoCredit['amount']) / 100
					: $autoCredit['amount'];
				$issueDate = !$autoCredit['issuedate'] ? date('Y-m-d H:i:s') 
											: date('Y-m-d H:i:s', strtotime($autoCredit['issuedate']));
				require_once "invoice-fns.php";
				$credit = array(
					'clientptr'=>$client,
					'amount'=>$creditAmount,
					'reason'=>$autoCredit['reason'],
					'issuedate'=>$issueDate,
					'amountused'=>'0',
					'payment'=>'0',
					'includesgratuity'=>'0',
					'bookkeeping'=>'0');
				insertTable('tblcredit', addCreationFields($credit), 1);
				payOffClientBillables($client);

			}
		}
	
	}
	if(!$skipNewBillable && ($billableChargeId = ($id ? $id : $chargeid))) {
		$newBillable = array('clientptr'=>$client, 'itemptr'=>$billableChargeId, 'itemtable'=>'tblothercharge', 
												'charge'=>($amount ? $amount : '0.0'), 'itemdate'=> $charge['issuedate'], 'billabledate'=>date('Y-m-d')); /*, 'paid'=>0*/
		// One would prefer to consume credits and then create a billable with 'paid' pre-determined,
		// but to associate a billable with one or more credits, we need to create the billable first
		// and then pay it off.
		$billableId = insertTable('tblbillable', $newBillable, 1);
		$credits = getClientCredits($client, 1);
		$paid = consumeCredits($credits, $charge['amount'], $billableId);
		// Since charge is brand new, we do not need to check to see if an invoice need to be marked paid
		if($paid) updateTable('tblbillable', array('paid'=>$paid), "billableId = $billableId",1);
	}
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', 'charge', '$client');window.close();</script>";
	exit;
} // END if $saveCharge
else if(isset($deleteCharge) && $deleteCharge) {
	if(fetchRow0Col0("SELECT chargeid FROM tblothercharge WHERE chargeid = $deleteCharge  LIMIT 1")) {
		doQuery("DELETE FROM tblothercharge WHERE chargeid = $deleteCharge");
		require_once "invoice-fns.php";
		$billable = fetchFirstAssoc(
			"SELECT billableid, itemdate, charge, clientptr 
			 FROM tblbillable 
			 WHERE superseded = 0 AND itemptr = $deleteCharge AND itemtable = 'tblothercharge' LIMIT 1", 1);
		$billableid = $billable['billableid'];
		/*$billableid = fetchRow0Col0(
			"SELECT billableid 
			 FROM tblbillable 
			 WHERE superseded = 0 AND itemptr = $deleteCharge AND itemtable = 'tblothercharge' LIMIT 1");*/
		//supersedeChargeBillable($deleteCharge);
		unpayBillable($billableid);
		deleteTable('tblbillable', "billableid = $billableid");
		if(itemNoteIsEnabled())
			deleteNote(array('itemtable'=>'tblothercharge', 'itemptr'=>$deleteCharge));
		logChange($billable['clientptr'], 'tblclient', 'd', $note="Misc charge [$deleteCharge] deleted by staff: ".dollarAmount($billable['charge'])." {$billable['itemdate']}");
	}
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', 'charge');window.close();</script>";
	exit;
}

else if(!$id && !$client) { // Client search
	findClients();
}

$operation = 'Create';
if($id) {
	$operation = 'Edit';
	$charge = fetchFirstAssoc("SELECT * FROM tblothercharge WHERE chargeid = $id");
	if(itemNoteIsEnabled())
		$itemnote = getItemNote('tblothercharge', $id);
		if($itemnote) $itemnote = $itemnote['note'];
		//$itemnote = fetchRow0Col0("SELECT  FROM relitemnote WHERE itemtable = 'tblothercharge' AND itemptr = $id LIMIT 1");
	$client = $charge['clientptr'];
	$prettyIssueDate = shortDate(strtotime($charge['issuedate']));
}
else {
	$issdate = $issuedate ? shortDate(strtotime($issuedate)) : '';
	$charge = array('client'=>$client, 'amount'=>$amount, 'reason'=>$reason, 'issuedate'=>$issdate, 'itemnote'=>$itemnote);
}

if($client) {
	$clientName = getOneClientsDetails($client);
	$clientName = $clientName['clientname'];
}

$header = "$operation a Miscellaneous Charge";

$windowTitle = $header;
require "frame-bannerless.php";	

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<h2><?= $header.($id ? " against $clientName's account on $prettyIssueDate" : ($client ? " against $clientName's account" : '')) ?></h2>
<?

// ###################################################################################################
// CASE 1: Creating a Charge for an Unspecified Client
if(!$id && !$client) {
?>	
<h3>Step 1: Pick a Client</h3>
<form name=findclients method=post>
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
	echo "<form name='editcharge' method='POST'>";
	hiddenElement('client', $client);
	hiddenElement('saveCharge', 1);
	hiddenElement('lastday', 1);
	echo "<table>";
	if(!$id) {
		if($autoCredit) {
			$acAmt = $autoCredit['percent'] ? 
				"{$autoCredit['amount']}% of this charge amount" 
				: dollarAmount($autoCredit['amount']);
			$expiration = $autoCredit['expires'] ? shortDate(strtotime($autoCredit['expires'])) : 'no expiration';
			echo "<tr><td colspan=2>A credit of $acAmt will be added to the account when this charge is saved."
							."<br>Reason: {$autoCredit['reason']}<br>Expires: $expiration</td></tr>";
		}
		//echo "Client: $clientName";
		//inputRow('Charge Date:', 'issuedate', $charge['issuedate']);
		calendarSet('Charge Date:', 'issuedate', $charge['issuedate']);
		if($lastday) labelRow('', '', 'Must be a date on or before '.shortNaturalDate(strtotime($lastday)), null, 'tiplooksleft');
		inputRow('Amount:', 'amount', $amount, '', 'dollarinput');
	}
	else {
		hiddenElement('issuedate', $prettyIssueDate);
		labelRow('Date Issued:', '', $prettyIssueDate);
		inputRow('Amount:', 'amount', $charge['amount'], '', 'dollarinput');
		hiddenElement('oldamount', $charge['amount']);
	}
	inputRow('Note:', 'reason', ($charge['reason'] ? $charge['reason'] : $reason), '', 'VeryLongInput');
	
	if(itemNoteIsEnabled()) {
		textRow('Invoice Note', 'itemnote', $itemnote, $rows=3, $cols=90);
		echo "<tr><td>";echoButton('', 'View Note', 'viewNote()');echo "</td></tr>";
	}
	
	
		echo "</table>";
	echo "</form><p>";
	echoButton('savecharge', "Save Charge", "checkAndSubmit()");
	echo " ";
	echoButton('', "Quit", 'window.close()');
	echo " ";
	if($id) {
		echoButton('', "Delete Charge", 'deleteCharge()', 'HotButton', 'HotButtonDown');
		echo "<img src='art/help.jpg' height=20 title='Yes, this un-pays the billable first.' onclick=\"alert('Yes, this un-pays the billable first.')\">";
	}

	if($id && staffOnlyTEST()) {
		echo "<div onclick='$(\"#analysis\").toggle();' title='Staff Only analysis'>...";
		echo "<div id='analysis' style='padding:4px;display:none;width:500px;background:lightyellow;border:solid black 1px'><u>LT Staff Analysis:</u><p>";
		$billables = fetchAssociations("SELECT * FROM tblbillable WHERE itemptr = $id AND itemtable = 'tblothercharge' ORDER BY billableid", 1);
		foreach($billables as $bill) {
			$action = $notFirst ? "Modified" : "Created";
			echo "$action ".shortDateAndTime(strtotime($bill['billabledate']))." (".dollarAmount($bill['charge']).") paid: "
				.($bill['paid'] ? dollarAmount($bill['paid']) : 'No')."<br>";
			if($bill['paid']) {
				$rbps = fetchAssociations(
					"SELECT rbp.*, cred.amount as creditamt, cred.issuedate, payment
						FROM relbillablepayment rbp
						LEFT JOIN tblcredit cred ON creditid = paymentptr
						WHERE billableptr = {$bill['billableid']}
						ORDER BY issuedate", 1);
				foreach($rbps as $rbp) {
					echo "... paid with ".($rbp['payment'] ? 'payment' : 'credit')
								." #{$rbp['paymentptr']} ".shortDateAndTime(strtotime($rbp['issuedate']))." using "
								.dollarAmount($rbp['amount'])." / ".dollarAmount($rbp['creditamt'])."<br>";
				}
			}
			$notFirst = true;
		}
		echo "</div>";
	}


}
?>
</div>

<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

function viewNote() {
	$.fn.colorbox({html:document.getElementById('itemnote').value, width:"400", height:"300", scrolling: true, opacity: "0.3"});	

}

function deleteCharge() {
	if(confirm("Are you sure you want to delete this charge?\nClick Ok to delete the charge.")) {
		document.location.href='charge-edit.php?deleteCharge=<?= $id ?>';
		//window.close();
	}
}

setPrettynames('client','Client','amount','Amount', 'issuedate','Charge Date');	
	
function initialPick(initial) {
  document.location.href='charge-edit.php?linitial='+initial;
}

function pickClient(id, clientname, packageid) {
  document.location.href='charge-edit.php?client='+id;
}

function checkAndSubmit() {
	if(MM_validateForm(
		'client', '', 'R',
		'amount', '', 'R',
		'amount', '', 'UNSIGNEDFLOAT',
		'issuedate', '', 'R',
		'issuedate', '', 'isDate',
<? if($lastday) echo "'issuedate', '$lastday', 'isDateAfterNot',\n"; ?>
		'issuedate', '', 'isDate'
		)) {
		document.getElementById('savecharge').disabled = true;  // try to prevent double click
		document.editcharge.submit();
		//window.close();
	}
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

function dollars($amount) {
	$amount = $amount ? $amount : 0;
	return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
}
	
