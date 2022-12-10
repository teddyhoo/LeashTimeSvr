<?
/* credit-edit.php
*
* Parameters: 
* id - id of credit to be edited
* - or -
* client - id of client to be credited
*
* This version introduces VOID credit
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "invoice-fns.php";
require_once "item-note-fns.php";
require_once "js-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract($_REQUEST);

if($saveCredit && $client && $_SESSION['CreditEditorDblClickToken'] != $_REQUEST['CreditEditorDblClickToken']) {
		$error = "Button was clicked twice.  Please do not double-click buttons in LeashTime.<p>";
		$stopOnError = true;
}
unset($_SESSION['CreditEditorDblClickToken']);


if($saveCredit) {
	if($id) {
		if(fetchRow0Col0("SELECT creditid FROM tblcredit WHERE creditid = $id AND amountused > 0.00 LIMIT 1")) 
			$credit = array('reason'=>$reason, 'issuedate'=>date('Y-m-d H:i:s', strtotime($issuedate)), 'hide'=>$hide, 'bookkeeping'=>$bookkeeping);
		else $credit = array('reason'=>$reason, 'amount'=>$amount, 'issuedate'=>date('Y-m-d H:i:s', strtotime($issuedate)), 'hide'=>$hide, 'bookkeeping'=>$bookkeeping);
		$credit['hide'] = $credit['hide'] ? $credit['hide'] : '0';
		$credit['bookkeeping'] = $credit['bookkeeping'] ? $credit['bookkeeping'] : '0';
		updateTable('tblcredit', addModificationFields($credit), "creditid = $id", 1);
		if($itemnote) {
			updateNote(array('itemtable'=>'tblcredit', 'itemptr'=>$id), $itemnote);
		}
	}
	else {
		if($client) {
			$credit = array('reason'=>$reason, 'amount'=>$amount, 'clientptr'=>$client, 'issuedate'=>date('Y-m-d H:i:s', strtotime($issuedate)), 'hide'=>$hide, 'bookkeeping'=>$bookkeeping);
			$credit['created'] = date('Y-m-d H:i:s');
			$credit['createdby'] = $_SESSION['auth_user_id'];
			$credit['hide'] = $credit['hide'] ? $credit['hide'] : '0';
			$credit['bookkeeping'] = $credit['bookkeeping'] ? $credit['bookkeeping'] : '0';
			insertTable('tblcredit', addCreationFields($credit), 1);
			payOffClientBillables($client);
		}
	}
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', null);window.close();</script>";
	exit;
}
else if(isset($voidCredit) && $voidCredit) {
	//$tempConstraint = $_SESSION['staffuser'] ? '' : "AND amountused = 0.00";
	if($client = fetchRow0Col0("SELECT clientptr FROM tblcredit WHERE creditid = $voidCredit $tempConstraint LIMIT 1")) {
		voidCredit($voidCredit, $voidreason, $hide);
		$windowTitle = 'Credit voided.';
		$successMessage = "Credit #$voidCredit has been voided.";
	}
	else {
		$windowTitle = 'Credit NOT voided.';
		$successMessage = "Credit #$voidCredit could not be voided.";
	}
	require "frame-bannerless.php";
	echo "<center><h2>$successMessage</h2>\n<p>\n<input type='button' value='Done' onClick='window.close();'>";
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', '');</script>";
	exit;
}
else if(isset($deleteCredit) && $deleteCredit) {
	if($client = fetchRow0Col0("SELECT clientptr FROM tblcredit WHERE creditid = $deleteCredit LIMIT 1")) {
		deleteCredit($deleteCredit, $voidreason);
		$windowTitle = 'Credit deleted.';
		$successMessage = "Credit #$deleteCredit has been deleted.";
	}
	else {
		$windowTitle = 'Credit NOT deleted.';
		$successMessage = "Credit #$deleteCredit could not be deleted.";
	}
	require "frame-bannerless.php";
	echo "<center><h2>$successMessage</h2>\n<p>\n<input type='button' value='Done' onClick='window.close();'>";
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', '');</script>";
	exit;
}


else if(!$id && !$client) { // Client search
	findClients();
}

$operation = 'Create';
if($id) {
	$operation = 'Edit';
	$credit = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $id");
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

$header = "$operation a Credit";

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
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<h2><?= $header.($id ? " to $clientName on $prettyIssueDate" : ($client ? " to $clientName" : '')) ?></h2>
<?

// ###################################################################################################
// CASE 1: Creating a Credit for an Unspecified Client
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
	echo "<form name='editcredit' method='POST'>";
	hiddenElement('client', $client);
	hiddenElement('saveCredit', 1);
	echo "<table>";
	if(!$id) {
		//echo "Client: $clientName";
		//inputRow('Date:', 'issuedate', shortDate());
		calendarSet('Date:', 'issuedate', shortDate());
		inputRow('Amount:', 'amount', '', '', 'dollarinput');
	}
	else {
		if($credit['amountused'] > 0.0) {
			inputRow('Date:', 'issuedate', $prettyIssueDate);
			//labelRow('Date Issued:', '', $prettyIssueDate);
			labelRow('Amount:', '', dollars($credit['amount']), null, null, null, null, 'raw');
			labelRow('Amount Used:', '', dollars($credit['amountused']), null, null, null, null, 'raw');
		}
		else {
			inputRow('Date:', 'issuedate', $prettyIssueDate);
			//labelRow('Date Issued:', '', $prettyIssueDate);
			if($credit['voided']) {
				$voidedDate = shortDate(strtotime($credit['voided']));				
				$paymentDisplay = "<font color='red'>".dollars($credit['voidedamount'])." ($voidedDate)</font>";
				labelRow('VOIDED Credit:', '', $paymentDisplay, null, null, null, null, 'raw');
			}
			else inputRow('Amount:', 'amount', $credit['amount'], '', 'dollarinput');
			//inputRow('Amount:', 'amount', $credit['amount'], '', 'dollarinput');
		}
	}
	countdownInputRow(45, 'Note:', 'reason', $credit['reason'], '', 'Input45Chars', null, null, null, 'afterlabel');
	if(staffOnlyTEST() || $credit['voided']) {
		$prefix = $credit['voided'] ? '' : '(Staff Only) ';
		radioButtonRow("{$prefix}Hide this credit on future invoices?", 'hide', $credit['hide'], array('Yes'=>1, 'No'=>0), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
		radioButtonRow("{$prefix}Mark as a bookkeeping credit?", 'bookkeeping', $credit['bookkeeping'], array('Yes'=>1, 'No'=>0), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
	}
	if($credit['voided'] && itemNoteIsEnabled()) {
		$itemnote = getItemNote('tblcredit', $credit['creditid'], 0);
		textRow('Void Note', 'itemnote', $itemnote['note'], $rows=3, $cols=90);
	}

	echo "</table>";
	$_SESSION['CreditEditorDblClickToken'] = time();
	hiddenElement('CreditEditorDblClickToken', $_SESSION['CreditEditorDblClickToken']);
	echo "</form><p>";
	echoButton('', "Save Credit", "checkAndSubmit()");
	echo " ";
	echoButton('', "Quit", 'window.close()');
	//echo " ";
	//if($id && $credit['amountused'] == 0.0) echoButton('', " Credit", 'voidCredit()', 'HotButton', 'HotButtonDown');
	if($id && !$credit['voided'] /*&& ($_SESSION['staffuser'] || $credit['amountused'] == 0.0)*/ /*&& (strpos($credit['sourcereference'], 'CC:') === FALSE)*/) {
		echo " ";
		echoButton('', "VOID Credit", 'confirmAndVoid()', 'HotButton', 'HotButtonDown');
}
	if($id && !$refund && staffOnlyTEST()) {
		echo " ";
		echoButton('', "Delete Credit", 'confirmAndDelete()', 'HotButton', 'HotButtonDown');
		}
		
	if($id) {
		$clientRecord = fetchFirstAssoc("SELECT userid, CONCAT_WS(' ' , fname, lname) as name FROM tblclient WHERE clientid = {$credit['clientptr']} LIMIT 1");
		if($credit['createdby'] == $clientRecord['userid'])
			$creatorName = "client {$clientRecord['name']}";
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require_once "common/init_db_common.php";
		if(!$creatorName)
			$creatorName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = '{$credit['createdby']}' LIMIT 1");
		if($credit['modified']) {
			$modifiedByName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = '{$credit['modifiedby']}' LIMIT 1");
		}
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
		if($creatorName || $modifiedByName) {
			echo "<div style='font-size:0.8em;'>";
			echo !$creatorName ? '' : "Registered ".shortDateAndTime(strtotime($credit['created']))." by $creatorName<br>";
			echo !$modifiedByName ? '' : "Last edited ".shortDateAndTime(strtotime($credit['modified']))." by $modifiedByName<br>";
			echo "</div>";
		}
		
		
		
			if(staffOnlyTEST()) {
				list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
				require "common/init_db_common.php";
				if(!$creatorName)
					$creatorName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = '{$credit['createdby']}' LIMIT 1");
				if($credit['modified']) {
					$modifiedByName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = '{$credit['modifiedby']}' LIMIT 1");
				}
				reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);

				echo "<div onclick='$(\"#analysis\").toggle();' title='Staff Only analysis'>...";
				echo "<div id='analysis' style='padding:4px;display:none;width:500px;background:lightyellow;border:solid black 1px'><u>LT Staff Analysis:</u><p>";
				echo "Created: ".shortDateAndTime(strtotime($credit['created']))." by $creatorName ({$credit['createdby']})";

				if($credit['voided']) echo "<p>VOIDED: ".shortDateAndTime(strtotime($credit['voided']));
				if($modifiedByName) echo "<p>Last Modified: ".shortDateAndTime(strtotime($credit['modified']))." by $modifiedByName ({$credit['modifiedby']})";
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
			
			if(staffOnlyTEST() && $id) {
				if(!$billablesTable = creditAppliedToTable($credit)) 
					echo "<div>Not yet applied to any visits or charges.</div>";
				else
					echo "<div onclick='$(\"#billablestable\").toggle();' class='fauxlink'>Applied To...</div>$billablesTable";
			}
					

		}  // END if($id)
		
		
		
		
		
		
		
}

function confirmVoidDialog() {
	if($credit['voided']) return;
	ob_start();
	ob_implicit_flush(0);
	$descr = ob_get_contents();
	echo "<table><tr><td colspan=2><h2>You are about to VOID this credit</h2></td></tr>";
	countdownInputRow(60, "Please explain why (optional):", 'voidreason', "", $labelClass=null, $inputClass='Input45Chars', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');	
	radioButtonRow('Hide this credit on future invoices?', 'hide', $value=1, array('Yes'=>1, 'No'=>0), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
	echo "</table>";
	echoButton('', "VOID Credit", 'parent.$.fn.colorbox.close();voidCredit(document.getElementById("voidreason").value, document.getElementById("hide_1").checked)', 'HotButton', 'HotButtonDown');
	echo " ";
	echoButton('', "Quit - Do not VOID", 'parent.$.fn.colorbox.close();');
	$descr = ob_get_contents();
	ob_end_clean();
	return $descr;
}


?>
</div>
<div id='confirmation' style='display:none'><?= $id ? confirmVoidDialog() : '' ?></div>

<script language='javascript' src='check-form.js'></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

function confirmAndDelete() {
	<? $systemCredit = 
		strpos($credit['reason'], '(v: ') && strpos($credit['reason'], ' b: ')
			? 'This is a system-created bookkeeping credit.\nYou should NOT delete it unless\nyou really know what you are doing.\n\n'
			: '';
	?>
	if(confirm("<?= $systemCredit ?>Sure you want to DELETE this credit?"))
		document.location.href='credit-edit.php?deleteCredit=<?= $id ?>';

}

function confirmAndVoid() {
	$.fn.colorbox({html:document.getElementById('confirmation').innerHTML, width:"500", height:"200", scrolling: true, opacity: "0.3"});
}

function voidCredit(reason, hide) {
	document.location.href='credit-edit.php?voidCredit=<?= $id ?>&voidreason='+escape(reason)+'&hide='+(hide ? 1 : 0);
}

setPrettynames('client','Client','amount','Amount');	
	
function initialPick(initial) {
  document.location.href='credit-edit.php?linitial='+initial;
}

function pickClient(id, clientname, packageid) {
  document.location.href='credit-edit.php?client='+id;
}

function checkAndSubmit() {
	if(MM_validateForm(
		'client', '', 'R',
		'amount', '', 'R',
		'amount', '', 'UNSIGNEDFLOAT'
		)) {
		document.editcredit.submit();
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

function dollars($amount) {
	$amount = $amount ? $amount : 0;
	return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
}
	
