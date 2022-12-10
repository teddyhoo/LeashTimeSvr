<? // cc-charge-clients.php
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
set_time_limit(15 * 60);

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "billing-fns.php";
require_once "cc-processing-fns.php";

// Determine access privs
$locked = locked('o-');

extract(extractVars('reason,goahead,section', $_REQUEST));

//print_r($_POST);exit;
//Array ( [client_4] => on [client_277] => on [charge_4] => 180.00 [ccstatus_4] => CC_NO_AUTOPAY [charge_277] => 286.00 [ccstatus_277] => NO_CC ) 

//if(dbTEST('tonkapetsitters')) {echo "GET: [[".print_r($_GET, 1).']]';exit;}	
if($_POST) {
	$noChargeGroup = array();
	$noCCGroup = array();
	$expiredCCGroup = array();
	$noAutopayGroup = array();
	$validCCGroup = array();
	foreach($_POST as $key => $unused) {
		if(!($client = getClientData($key, $_POST))) continue;
		$clients[$client['clientptr']] = $client;		
		if(!$goahead) {
			if($client['charge'] < 1) $noChargeGroup[$client['clientptr']] = $client;
			else if($client['ccstatus'] == 'NO_CC') $noCCGroup[$client['clientptr']] = $client;
			else if($client['ccstatus'] == 'CC_EXPIRED')  $expiredCCGroup[$client['clientptr']] = $client;
			else if($client['ccstatus'] == 'CC_NO_AUTOPAY')  $noAutopayGroup[$client['clientptr']] = $client;
			else if($client['ccstatus'] == 'CC_VALID')  $validCCGroup[$client['clientptr']] = $client;
		}
	}
	if(!$noCCGroup && !$expiredCCGroup && !$noAutopayGroup && !$noChargeGroup) $goahead = true;
	
	//firstDay='+firstDayArg('firstDay_'+section)+'&lookahead
	extract(extractVars('firstDay,lookahead,literal', $_POST));
	if($firstDay) $_SESSION["invoiceArgs_$section"] = 
		array('firstDay'=>$firstDay, 'lookahead'=>$lookahead, 'literal'=>$literal, 'section'=>$section);
}

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
	return $data;
}

if(!$goahead) {
	$pageTitle = "Credit Card Problems";
}
else {
	$pageTitle = "Credit Card Payment Report";
}

if($_REQUEST['iframe']) {
	$extraBodyStyle = 'background-image:url(null);background-color:white;';
	require "frame-bannerless.php";
	if($_REQUEST['empty']) {
		echo "<style>
.bluebar {width:99.85%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;
					text-align:center;font-size:1.1em;padding-top:5px;margin-bottom:2px;}
</style>";

		if(getChargeLog()) echo getChargeLog(); 
		else {
?>
		<h2>Charge Results</h2>
		<span class='fontSize1_1em'>When you click the [ Charge Selected Clients ] button, progress and results will appear in this panel.<span>
<?		
		} // if empty
		exit;
	} // if empty
} // if iframe
else include "frame.html";
?>
<style>
.bluebar {width:99.85%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;
					text-align:center;font-size:1.1em;padding-top:5px;margin-bottom:2px;}
</style>

<?

if(!$goahead) {
	echo "<form name='allclients' method='POST' action='cc-charge-clients.php'><p>";
	if(count($expiredCCGroup)+count($noAutopayGroup)+count($validCCGroup)) {
		echo "Not all of the clients you selected have a valid credit card or e-checking account marked 'Primary' set up for AutoPay.<p>";
		echoButton('', 'Charge Selected Clients', 'chargeSelectedClients()');
	}
	else if(!$validCCGroup) echo "Not one of the clients you selected has both an amount to be paid and a valid credit card or e-checking account marked 'Primary' set up for AutoPay.<p>";
	if($noCCGroup) {
		echo "<p><div class='bluebar'>Clients with no valid payment source on file marked 'Primary'</div></p>";
		sectionTable($noCCGroup, false);
	}
	if($noChargeGroup) {
		echo "<p><div class='bluebar'>Clients who owe nothing</div></p>";
		sectionTable($noChargeGroup, false);
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
		echo "<p><div class='bluebar'>Clients valid credit cards or e-checking accounts ready to charge</div></p>";
		sectionTable($validCCGroup, true);
	}
	$clearCCs = isset($clearCCs) ? $clearCCs : getClearCCs(array_keys($clients), 'primaryToo');
	hiddenElement("section", $section);
	hiddenElement("goahead", '');
	hiddenElement("billing_use_once_token", $_SESSION['billing_use_once_token']); // mattOnlyTEST
	foreach($clients as $clientid => $client) {
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

	if($_SESSION['billing_use_once_token'] != $_REQUEST['billing_use_once_token']) {
		echo "<h2>Refreshing this page is not allowed.</h2>";
		if(!$_REQUEST['iframe']) include "frame-end.html";
		unset($_SESSION['billing_use_once_token']);
		exit;
	}
	unset($_SESSION['billing_use_once_token']);
	
	chargeLog("<p><div class='bluebar'>Charged Client Accounts: ".shortDateAndTime()."</div></p>\n", 'restart');
	// status|Status||clientname|Client||chargedollars|Charge||ccLabel|Credit Card||note|Note
	chargeLog("<table width='95%'><tr><th>Status</th><th>Client</th><th>Charge</th><th>Credit Card</th><th>Note</th></tr>\n");
	echo "<p><div id='mainstatus' class='bluebar'>Charging Client Accounts</div></p>";
	chargingTable();
	$clientChargeJSArray = array();
	foreach($clients as $clientid => $client)
		$clientChargeJSArray[] = "'$clientid|{$client['charge']}'";
	$clientChargeJSArray = join(',', $clientChargeJSArray);
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
var clientcharges = [<?= $clientChargeJSArray ?>];
var count = 0;
sendChargeRequest(0, '');
function sendChargeRequest(client, responseText) {
//alert('Client: '+client+':\n'+responseText);
	var now = new Date();
	document.getElementById('mainstatus').innerHTML = 
			'Charging Client Account'
			+ (count >= clientcharges.length 
					? "s Complete at "+(new Date().format("m/d/Y g:i a"))
					: '# '+(count + 1)+'/'+clientcharges.length
				); 
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
		// Following two lines cause cross-origin Javascript blocking, even in non-slidetest mode (presumably when window has an opener)
		//if(window.parent && window.parent.update) window.parent.update('account', client);//"slideout"
		//else if(window.opener && window.opener.update) window.opener.update('account', client);
	}
	if(count >= clientcharges.length) return;
	var parts = clientcharges[count].split('|');
	document.getElementById('status_'+parts[0]).innerHTML = 'processing';
	var invoiceSectionKeyArg = '&invoiceSectionKey=<?= $section ? "invoiceArgs_$section" : ''?>';
	ajaxGetAndCallWith('ajax-charge-client.php?client='+parts[0]+"&amount="+parts[1]+invoiceSectionKeyArg, sendChargeRequest, parts[0]);
	count = count + 1;
}

</script>
<?
}

function chargingTable() {
	global $clientNames, $clearCCs, $clients, $noCCGroup, $expiredCCGroup;
	$clearCCs = isset($clearCCs) ? $clearCCs : getClearCCs(array_keys($clients), 'primaryToo');
	$clientNames = isset($clientNames) ? $clientNames : getClientDetails(array_keys((array)$clients));
	$rows = array();
	foreach((array)$clients as $clientid => $client) {
		if($noCCGroup[$clientid] || $expiredCCGroup[$clientid]) continue;
		$row = array();
		$row['status'] = "<span id='status_$clientid' style=''>untried</span>";
		$row['note'] = "<span id='note_$clientid'></span>";
		$row['clientname'] = $clientNames[$clientid]['clientname'];
		$row['chargedollars'] = dollarAmount($client['charge']);
		$cc = $clearCCs[$clientid];
		
		if($cc) {
			$cardLabel = $autopay = $cc['autopay'] ? ' [auto]' : '';
			$expiration = $cc['acctid'] ? '' : "Exp: ".shortExpirationDate($cc['x_exp_date']);
			$row['ccLabel']  = "{$cc['company']} ************{$cc['last4']} ".$expiration.$cardLabel;
		}
		else $row['ccLabel'] = 'None';
		$rows[] = $row;
	}
	$attributes = 'WIDTH=90%';
	$columns = explodePairsLine('status|Status||clientname|Client||chargedollars|Charge||ccLabel|Credit Card||note|Note');
	if(!$rows) echo "There are no clients to charge.";
	else
	tableFrom($columns, $rows, $attributes, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}

function sectionTable($group, $allowSelection) {
	global $clientNames, $clearCCs, $clients;
	$clientNames = isset($clientNames) ? $clientNames : getClientDetails(array_keys($clients));
	$clearCCs = isset($clearCCs) ? $clearCCs : getClearCCs(array_keys($clients), 'primaryToo');
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
			$expiration = $cc['acctid'] ? '' : "Exp: ".shortExpirationDate($cc['x_exp_date']);
			$row['ccLabel'] = "{$cc['company']} ************{$cc['last4']} ".$expiration.$cardLabel;
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
if(!$_REQUEST['iframe']) include "frame-end.html";
			
			
function clientLink($pp) {
	global $clientNames;
	return "<a href=#
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id={$pp['clientptr']}\",700,500)'>
	       {$clientNames[$pp['clientptr']]['clientname']}</a> ";
}
			