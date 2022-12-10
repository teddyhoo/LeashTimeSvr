<? // cc-invoice-charge-clients.php
/*
* params:
* client_*
* charge_*
* ccstatus_*
* reason_*
* reason - for a shared reason
* goahead = skip eligibility analysis and charge clients
*
* Charge each client account and record the result.
* 1. Determine eligibility of each client. 
* 2. If goahead or all eligible, charge each client.
* 3. Else if some ineligible, display eligibilty report and stop.
* 4. After charges are processed, display a charge report
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";

// Determine access privs
$locked = locked('o-');

extract(extractVars('reason,goahead', $_REQUEST));

$asOfDate = $_REQUEST['asOfDate'] ? $_REQUEST['asOfDate'] : $_REQUEST['originalAsOfDate'];

//print_r($_POST);exit;
//Array ( [client_4] => on [client_277] => on [charge_4] => 180.00 [ccstatus_4] => CC_NO_AUTOPAY [charge_277] => 286.00 [ccstatus_277] => NO_CC ) 

if($_POST) {
	$noCCGroup = array();
	$expiredCCGroup = array();
	$noAutopayGroup = array();
	$validCCGroup = array();
	foreach($_POST as $key => $unused) {
		if(!($client = getClientData($key, $_POST))) continue;
		$clients[$client['clientptr']] = $client;
	}
	$clearCCs = $_SESSION['ccenabled'] ? getClearCCs(array_keys((array)$clients)) : array();
	foreach($clients as $clientid => $client) {
		$status = $clearCCs[$clientid] ? ccStatus($clearCCs[$clientid]) : '';
		$status = $status ? $status[2] : 'NO_CC';
		$client['ccstatus'] = $status;
		$clients[$clientid] = $client;
		if(!$goahead) {
			if($client['ccstatus'] == 'NO_CC') $noCCGroup[$client['clientptr']] = $client;
			else if($client['ccstatus'] == 'CC_EXPIRED')  $expiredCCGroup[$client['clientptr']] = $client;
			else if($client['ccstatus'] == 'CC_NO_AUTOPAY')  $noAutopayGroup[$client['clientptr']] = $client;
			else if($client['ccstatus'] == 'CC_VALID')  $validCCGroup[$client['clientptr']] = $client;
		}

	}
	
	if(!$noCCGroup && !$expiredCCGroup && !$noAutopayGroup) $goahead = true;
}
//print_r($_POST);exit;

/*
echo "POST: ".print_r($_POST, 1)."<p>";
echo "clients: ".print_r($clients, 1)."<p>";
echo "noCCGroup: ".print_r($noCCGroup, 1)."<p>";
echo "expiredCCGroup: ".print_r($expiredCCGroup, 1)."<p>";
echo "noAutopayGroup: ".print_r($noAutopayGroup, 1)."<p>";
echo "validCCGroup: ".print_r($validCCGroup, 1)."<p>";
exit;
*/


function getClientData($key, $source) {
	if(strpos($key, 'client_') !== 0) return null;
	$clientid = substr($key, 7);
	$data = array('clientptr'=> $clientid, 'checkbox'=>$key, 'charge'=>$source["charge_$clientid"], 'ccstatus'=>$source["ccstatus_$clientid"]);
	if($source["reason_$clientid"]) $data['reason'] = $source["reason_$clientid"];
	$data['sendReceipt'] = $source["emailreceipt_$clientid"];
//echo "$key: ".print_r($data, 1)."<br>";	
	return $data;
}

if(!$goahead) {
	$pageTitle = "Credit Card Problems";
}
else {
	$pageTitle = "Credit Card Payment Report";
}
function ccStatus($cc) {
	global $ccStatus;
	if(!$ccStatus) {
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
	}

	if(!$cc) return $ccStatus['NO_CC'] ;
	else if(strtotime(date('Y-m-t', strtotime($cc['x_exp_date']))) < strtotime('Y-m-d')) return $ccStatus['CC_EXPIRED'];
	else if(!$cc['autopay']) return $ccStatus['CC_NO_AUTOPAY'];
	else return $ccStatus['CC_VALID'];
}

$breadcrumbs .= ($breadcrumbs ? ' - ' : '')."<a href='invoices-autopay.php?asOfDate=$asOfDate'>AutoPay Invoices</a>";
include "frame.html";
?>
<style>
.bluebar {width:99.85%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;
					text-align:center;font-size:1.1em;padding-top:5px;margin-bottom:2px;}
</style>

<?
if(!$goahead) {
	echo "<form name='allclients' method='POST' action='cc-invoice-charge-clients.php'><p>";
	echo "Not all of the clients you selected have valid credit cards set up for AutoPay.<p>";
	echoButton('', 'Charge Selected Clients', 'chargeSelectedClients()');
	if($noCCGroup) {
		echo "<p><div class='bluebar'>Clients with no credit card on file</div></p>";
		sectionTable($noCCGroup, false);
	}
	if($expiredCCGroup) {
		echo "<p><div class='bluebar'>Clients with expired credit cards</div></p>";
		sectionTable($expiredCCGroup, true);
	}
	if($noAutopayGroup) {
		echo "<p><div class='bluebar'>Clients who have not elected to allow AutoPay</div></p>";
		sectionTable($noAutopayGroup, true);
	}
	if($validCCGroup) {
		echo "<p><div class='bluebar'>Clients valid credit cards ready to charge</div></p>";
		sectionTable($validCCGroup, true);
	}
	$clearCCs = isset($clearCCs) ? $clearCCs : getClearCCs(array_keys((array)$clients));
	hiddenElement("goahead", '');
	foreach((array)$clients as $clientid => $client) {
		hiddenElement("charge_$clientid", $client['charge']);
		hiddenElement("ccstatus_$clientid", $client['ccstatus']);
	}
	
	echo "</form>";
?>

<script language='javascript'>
function chargeSelectedClients() {
	var sels = getSelections('Please select one or more clients to charge.');
	if(sels.length == 0) return;
	document.getElementById('goahead').value = 1;
	document.allclients.submit();
}

function getSelections(emptyMsg) {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') != -1 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert(emptyMsg);
		return;
	}
	return sels;
}

</script>
<?
}
else { // $goahead
	echo "<p><div id='mainstatus' class='bluebar'>Charging Client Accounts</div></p>";
	chargingTable();
	$clientChargeJSArray = array();
	foreach($clients as $clientid => $client)
		$clientChargeJSArray[] = "'$clientid|{$client['charge']}|{$client['sendReceipt']}'";
	$clientChargeJSArray = join(',', $clientChargeJSArray);
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
var clientcharges = [<?= $clientChargeJSArray ?>];
var count = 0;
var abort = false;

sendChargeRequest(0, '');
function sendChargeRequest(client, responseText) {
//alert('Client: '+client+':\n'+responseText);
	var now = new Date();
	var bannerHTML = '';
	if(client == 0) abort = 0;
	else if(abort) {
		bannerHTML = 
				'User aborted the payment process after '+count+'/'
				+clientcharges.length+' transactions at ' 
				+(new Date().format("m/d/Y g:i a")); 
		}
	else bannerHTML = 
			'Charging Client Account'
			+ (count >= clientcharges.length 
					? "s Complete at "+(new Date().format("m/d/Y g:i a"))
					: '# '+(count + 1)+'/'+clientcharges.length
					  +' <input type=button onclick="abort=1" value=Stop style="background:red">'
				); 
				
	document.getElementById('mainstatus').innerHTML = bannerHTML;
	if(client > 0) {
		var response = responseText.split('##');  // approved|err|declined##transactionid##note
		var status = jstrim(response[0]);
		var color = 'black';
		var note;
		if(status != 'approved') {
			document.getElementById('status_'+client).style.color = 'red';
			note = (response[1] > 0 ? 'Trans #'+response[1]+': ' : '')+response[2];
		}
		else {
			document.getElementById('status_'+client).style.color = 'green';
			note = 'Trans #'+response[1];
		}
		document.getElementById('note_'+client).innerHTML = note;
		document.getElementById('status_'+client).innerHTML = status;
		if(window.opener && window.opener.update) window.opener.update('account', client);
	}
	if(abort) return;
	if(count >= clientcharges.length) return;
	var parts = clientcharges[count].split('|');
	document.getElementById('status_'+parts[0]).innerHTML = 'processing';
	// client, amount, reason, asOfDate, sendReceipt
	ajaxGetAndCallWith('ajax-invoice-charge-client.php?client='+parts[0]+"&amount="+parts[1]
												+"&asOfDate="+'<?= date('Y-m-d', strtotime($asOfDate)) ?>'+
												+"&sendReceipt="+parts[2], sendChargeRequest, parts[0]);
	count = count + 1;
}

</script>
<?
}

function chargingTable() {
	global $clientNames, $clearCCs, $clients, $noCCGroup, $expiredCCGroup;
	$clientNames = isset($clientNames) ? $clientNames : getClientDetails(array_keys($clients));
	$rows = array();
	foreach($clients as $clientid => $client) {
		if($noCCGroup[$clientid] || $expiredCCGroup[$clientid]) continue;
		$row = array();
		$row['status'] = "<span id='status_$clientid' style=''>untried</span>";
		$row['note'] = "<span id='note_$clientid'></span>";
		$row['clientname'] = $clientNames[$clientid]['clientname'];
		$row['chargedollars'] = dollarAmount($client['charge']);
		$cc = $clearCCs[$clientid];
		if($cc) {
			$cardLabel = $autopay = $cc['autopay'] ? ' [auto]' : '';
			$row['ccLabel'] = "{$cc['company']} ************{$cc['last4']} Exp: ".shortExpirationDate($cc['x_exp_date']).$cardLabel;
		}
		else $row['ccLabel'] = 'None';
		$rows[] = $row;
	}
	$attributes = 'WIDTH=90%';
	$columns = explodePairsLine('status|Status||clientname|Client||chargedollars|Charge||ccLabel|Credit Card||note|Note');
	if(!$rows) echo "None.";
	else
	tableFrom($columns, $rows, $attributes, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}

function sectionTable($group, $allowSelection) {
	global $clientNames, $clearCCs, $clients;
	$clientNames = isset($clientNames) ? $clientNames : getClientDetails(array_keys($clients));
	$clearCCs = isset($clearCCs) ? $clearCCs : getClearCCs(array_keys($clients));
	$rows = array();
	foreach($group as $clientid => $client) {
		$row = array();
		$row['cb'] = $allowSelection 
			? "<input type='checkbox' id='client_$clientid' name='client_$clientid' CHECKED>"
			: '&nbsp';
		$row['clientname'] = clientLink($client);
		$row['chargedollars'] = dollarAmount($client['charge']);
		$cc = $clearCCs[$clientid];
		if($cc) {
			$cardLabel = $autopay = $cc['autopay'] ? ' [auto]' : '';
			$row['ccLabel'] = "{$cc['company']} ************{$cc['last4']} Exp: ".shortExpirationDate($cc['x_exp_date']).$cardLabel;
		}
		else $row['ccLabel'] = 'None';
		$rows[] = $row;
	}
	$attributes = 'WIDTH=90%';
	$columns = explodePairsLine('cb| ||clientname|Client||chargedollars|Charge||ccLabel|Credit Card');
	if(!$rows) echo "None.";
	else
	tableFrom($columns, $rows, $attributes, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}			
// ***************************************************************************
include "frame-end.html";
			
			
function clientLink($pp) {
	global $clientNames;
	return "<a href=#
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id={$pp['clientptr']}\",700,500)'>
	       {$clientNames[$pp['clientptr']]['clientname']}</a> ";
}
			