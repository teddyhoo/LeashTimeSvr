<?
/* gratuity-edit.php
*
* Parameters: 
* client - id of client to paying the gratuity
* issuedate - unix timestamp of issuedate or dateTime string
*
* gratuity may not be modified (except for tipnote)
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "gratuity-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
$fields = 'client,issuedate,saveGratuity,gratuity,tipnote,';
for($i=1;$i<=5;$i++) $fields .= "gratuityProvider_$i,dollar_$i";

extract(extractVars($fields, $_REQUEST));

$error = false;

if(!$error && $saveGratuity) {
	if($issuedate) {
		$gratuity = array('tipnote'=>$tipnote);
		updateTable('tblgratuity', $gratuity, "clientptr = $client && issuedate = '$issuedate'", 1);
	}
	else {
		foreach($_REQUEST as $key => $provider) {
			if(strpos($key, 'gratuityProvider_') === FALSE) continue;
			if($provider == -1) $provider = 0;  // Handle the "Unassigned" case
			$index = substr($key, strlen('gratuityProvider_'));
			if($_REQUEST["dollar_$index"]) {
				$portion = $_REQUEST["dollar_$index"];
				$newGratuity = array('paymentptr'=>0, 'tipnote'=>$tipnote, 'amount'=>$portion, 'clientptr'=>$client, 'issuedate'=>date('Y-m-d H:i:s'),
											'providerptr'=>$provider);
				insertTable('tblgratuity', $newGratuity, 1);
				makeGratuityNoticeMemo($newGratuity);
			}
		}
	}
	if(!$error) {
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', null);window.close();</script>";
		exit;
	}
}
else if(isset($deleteCredit) && $deleteCredit) {
	if(fetchRow0Col0("SELECT creditid FROM tblcredit WHERE creditid = $deleteCredit AND amountused = 0.00 LIMIT 1"))
		doQuery("DELETE FROM tblcredit WHERE creditid = $deleteCredit");
	exit;
}

else if(!$issuedate && !$client) { // Client search
	findClients();
}

$operation = 'Add';
if($issuedate) {
	$operation = 'Edit';
	$prettyIssueDate = shortDate(strtotime($gratuity['issuedate']));
}
else {
	$gratuity = array('client'=>$client);
}

if($client) {
	$clientName = getOneClientsDetails($client);
	$clientName = $clientName['clientname'];
}

$header = "$operation a Gratuity";
	

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
	echo "<form name='editgratuity' method='POST'>";
	hiddenElement('client', $client);
	hiddenElement('saveGratuity', 1);
	if($issuedate) 	hiddenElement('issuedate', $issuedate);
	$tipGroup = $issuedate ? getIndependentGroup($client, $issuedate) : array();
	gratuitySection($client, null, $tipGroup, strtotime($issuedate));
	echo "</form><p>";
	echoButton('', "Save Gratuity", "checkAndSubmit()");;
}
?>
</div>
<?
		if(staffOnlyTEST() && $issuedate) {
			echo "<p>";
			$grats = fetchAssociations(
				"SELECT *, fname, lname 
					FROM tblgratuity 
					LEFT JOIN tblprovider ON providerid = providerptr
					WHERE clientptr = $client AND issuedate = '$issuedate'", 1);
			foreach($grats as $grat) {
				fauxLink("Analyze Gratuity #{$grat['gratuityid']}: {$grat['fname']} {$grat['lname']} \${$grat['amount']}", "parent.location.href=\"gratuity-analysis.php?id={$grat['gratuityid']}\"");
				echo "<br>";
			}
		}
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
var currencyMark = '<?= getCurrencyMark() ? getCurrencyMark() : '$' ?>';
</script>
<script language='javascript' src='gratuity-fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

var totalPaymentField = '';

function deleteCredit() {
	if(confirm("Are you sure you want to delete this credit?\nClick Ok to delete the credit.")) {
		document.location.href='payment-edit.php?deleteCredit=<?= $id ?>';
		//window.close();
	}
}

setPrettynames('client','Client','amount','Total Payment');	
	
function initialPick(initial) {
  document.location.href='payment-edit.php?linitial='+initial;
}

function pickClient(id, clientname, packageid) {
  document.location.href='payment-edit.php?client='+id;
}

function update(x, y, z) {
	if(window.opener.update) window.opener.update('account', null);
	document.location.href='payment-edit.php?id='+document.getElementById('id').value;
}

function checkAndSubmit() {
	if(MM_validateForm(
		<?= gratuityValidationArgs($issuedate) ?>
		)) {
		document.editgratuity.submit();
	}
}

if(document.editgratuity && document.editgratuity.gratuity) {
	document.editgratuity.gratuity.select();
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

if(!function_exists('dollars')) {
	function dollars($amount) {
		$amount = $amount ? $amount : 0;
		return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');

	}
}

function dumpGratuityForm($clientid) {
	labeledCheckbox('Pay out gratuity', '', null, null, null, 
			"document.getElementById(\"gratuityDIV\").style.display=(this.checked ? \"inline\" : \"none\")", $boxFirst=false);
	echo "<div id='gratuityDIV' style='display:none;'>";
	echo "<span style='font-size:1.2em;font-weight:bold;'>What portion of the Total Payment will be paid out in gratuities?</span><p>";
	gratuitySection($clientid, 'amount');
	echo "</div>";
}