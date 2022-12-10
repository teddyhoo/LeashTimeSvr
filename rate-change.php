<?
/* rate-change.php
*
* modes: 
* client - display rate info in div
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
$fields = 'pattern,linitial,client,clientcopy,applyto';
extract(extractVars($fields, $_REQUEST));  // saveCredit, chargeCard, id, client, amount, externalreference, sourcereference, reason, payout, gratuity, 
										 //gratuityProvider_1, percent_1 ...gratuityProvider_5, percent_5, tipnote

if($client) {  // DISPLAY MODE
	priceList($client);
	exit;
}

if($clientcopy) {  // COPY MODE
	priceList($clientcopy, $copy=1);
	exit;
}

if($applyto) {  // APPLY MODE
	$prices = fetchAssociationsKeyedBy("SELECT * FROM relclientcharge WHERE clientptr = $applyto", 'servicetypeptr');
	foreach($_POST as $key => $val) {
		$val = trim($val);
		$servType = strpos($key, 'servicecharge_') === 0 ? substr($key, strlen('servicecharge_')) : null;
		if($servType) {
			//$servTax = trim($_POST['servicetax_'.$servType]);
//echo "$key: [val: $val] [servicetax_$servType = {$_POST['servicetax_'.$servType]}]<p>";			
			//$servTax = strlen($servTax) ? $servTax  : '-1';
			//if($servTax == 0) $servTax = '0.0';
			$charge = strlen($val)? $val : -1;
			if($charge == 0) $charge = '0.0';
			$price = $prices[$servType];
			if(!$price) 
				$price =  array('clientptr'=>$applyto, 
										'servicetypeptr'=>$servType,
										'taxrate'=>-1);
			$price['charge'] = $charge;
			$price['note'] = $price['note'] ? $price['note'] : sqlVal("''");
//screenLog("$key: $val ==> ".print_r($price,1));
			replaceTable('relclientcharge', $price, 1);
		}
	}
	$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $applyto LIMIT 1");
	$_SESSION['frame_message'] = "$clientName's custom charges have been updated.";
}



$error = false;

findClients();

$pageTitle = "Make Client Price Changes";
	
require "frame.html";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

//print_r($source);exit;

// ###################################################################################################
// CASE 1: Creating a Payment for an Unspecified Client
?>	
<form name="findclients" method="post" action="payment-edit.php">
<input id='linitial' name='linitial' type=hidden value='<?= $linitial ?>'>
<input id='pattern' name='pattern' value='<?= safeValue($pattern); ?>' size=10 autocomplete='off'> <? echoButton('', 'Search', "search()") ?>
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
<style>
.results td {font-size:1.1em;padding-right:10px;padding-bottom:4px;}
</style>
<table id='wholecontent'><tr>
<td style='vertical-align:top;width:300px;'>
<table class='results' width='80%'>
<tr><th>Client</th></tr>
<?
		foreach($clients as $client) {
			//$address = $client['address'];
			//if($address[0] == ",") $address = substr($address, 1);
			$clientName = htmlentities($client['name'], ENT_QUOTES);
			echo "<tr><td><a href=# onClick='pickClient({$client['clientid']}, \"$clientName\", \"{$client['packageid']}\")'>$clientName</a></td>";
			echo "</tr>\n";
			
		}
	}

// ###################################################################################################
?>
</table>
</td>
<td id='clientdetail' style='vertical-align:top;padding:10px;background:lightblue;width:80%'>&nbsp;</td>
</tr>
</table>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function initialPick(initial) {
  document.location.href='rate-change.php?linitial='+initial;
}

function pickClient(id) {
	ajaxGet("rate-change.php?client="+id, 'clientdetail');
}

function preservePrices(id) {
	ajaxGet("rate-change.php?clientcopy="+id, 'clientdetail');
}

setPrettynames('pattern', "Search term");
function search() {
	if(MM_validateForm("pattern", "", "R"))
		document.location.href='rate-change.php?pattern='+escape(document.getElementById('pattern').value);
}

function update(aspect, client) {
	var arg;
	if(document.getElementById('linitial').value) arg = 'linitial='+document.getElementById('linitial').value;
	else arg = 'pattern='+escape(document.getElementById('pattern').value);
	document.location.href='rate-change.php?'+arg;
}

function viewRecentActivity(client) {
	$.fn.colorbox({href:"rate-change.php?client="+client, width:"600", height:"400", scrolling: true, opacity: "0.3"});
}

var violations = new Array();

function applyChanges() {
	violations = new Array();
	$("input").each(function(index, element) {
		if(!element.id || element.id.indexOf('servicecharge_') == -1) return;
		var v = jstrim(element.value);
		if(v == '') return;
		if(!isFloat(v) || v < 0) {
			var label = element.parentNode.parentNode.childNodes[0].innerHTML;//
			violations[violations.length] = "["+label+"] must be blank or a positive dollar amount.";
		}
});
	if(violations.length > 0) {
		violations = violations.join('\n');
		alert('Changes not saved!\n\n'+violations);
		return;
	}
	document.servicecharges.submit();
}



</script>
<?

require "frame-end.html";

function findClients() {
	global $baseQuery, $pattern, $linitial, $numFound, $clients, $balances;
	
	$baseQuery = "SELECT clientid, packageid, CONCAT_WS(' ',fname,lname) as name, CONCAT_WS(', ',street1, city) as address 
								FROM tblclient
								LEFT JOIN tblrecurringpackage ON clientid = clientptr
								WHERE active AND (packageid is null OR tblrecurringpackage.current=1)";

	if(isset($pattern)) {
		if(strpos($pattern, '*') !== FALSE) $realPattern = str_replace  ('*', '%', $pattern);
		else $realPattern = "%$pattern%";
		$baseQuery = "$baseQuery AND CONCAT_WS(' ',fname,lname) like '$realPattern'";
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


function priceList($id, $copy=null) {
	require_once "service-fns.php";
	global $rawServiceTypeFields;
	$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $id LIMIT 1");
	$standardTaxRate = $_SESSION['preferences']['taxRate'] ? $_SESSION['preferences']['taxRate'] : '';
	$standardRates = getStandardRates();
	$charges = getClientCharges($id, false);
	echo '<form name="servicecharges" method="post">';
	hiddenElement('applyto', $id);
	echo "<table style='width:100%;border:solid black 1px;padding:100px;background:white;'>";
	echo "<tr><td colspan=3 style='text-align:center;font-weight:bold;font-size:1.5em'>$clientName<br>Custom Service Prices</td>";
	echo "<td colspan=3>\n";
	if(!$copy) echoButton('', 'Preserve Prices', "preservePrices($id)");
	echo " ";
	echoButton('', 'Save Changes', "applyChanges()");
	echo "</tr>\n";
	echo "<tr><th>&nbsp;</th><th style='text-align:right;'>Standard Price</th><th>Price</th></tr>\n"; // <th>Standard Tax</th><th>Tax Rate %</th>
	foreach($standardRates as $key => $service) {
		$stndRate = $service['defaultcharge'];
		$charge = !isset($charges[$key]) || $charges[$key]['charge'] < 0 ? '' : $charges[$key]['charge'];
		if($copy && $charge == '') $charge = $stndRate;
		//$service['defaultrate'].($service['ispercentage'] ? '%' : '');
		$color = $copy && ($stndRate != $charge) ? 'background:pink;' : '';
		echo "<tr><td>{$service['label']}</td><td style='text-align:right;$color'>\$ $stndRate</td><td>";
		labeledInput('', 'servicecharge_'.$key, $charge, null, 'dollarinput');
		$thisStandardTaxRate = $service['taxable'] ?  "$standardTaxRate %" : '';
		echo "</td>";
		//echo "<td>$thisStandardTaxRate</td><td>";
		//$taxRate = $charges[$key]['taxrate'] >= 0 ? $charges[$key]['taxrate'] : '';
		//labeledInput('', 'servicetax_'.$key, $taxRate, null, 'dollarinput');
		$rawServiceTypeFields = ($rawServiceTypeFields ? "$rawServiceTypeFields|||" : '').'servicecharge_'.$key.','.$service['label'];
		echo "</tr>\n";
	}
	echo "</table>\n";
	echo "</form>";
}
