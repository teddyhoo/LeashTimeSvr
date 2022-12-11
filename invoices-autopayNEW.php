<? // invoices-autopay.php
require_once "credit-fns.php";
require_once "cc-processing-fns.php";
require_once "comm-fns.php";

set_time_limit(300);

$listInvoices=1;
$tab = 'autopay';


if($_REQUEST['originalAsOfDate']) $asOfDate = $_REQUEST['originalAsOfDate'];
include "invoices-top.php";

//echo "***DEV****";

if(!adequateRights('*cc')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
	$error = "Insufficient Access Rights to charge client credit cards or bank accounts.";
	echo "<font color=red>$error</font>";
	include "invoices-bottom.php";
	exit;
}


$merchantAuthorizationProblem = merchantAuthorizationProblem();

// echo "REQUEST: [{$_REQUEST['autopagetoken']}] SESSION: [{$_SESSION['autopagetoken']}]";exit;

if(!$merchantAuthorizationProblem && $originalAsOfDate 
	 &&("{$_REQUEST['autopagetoken']}" != "{$_SESSION['autopagetoken']}")) 
	$resend = "This operation cannot be repeated.";
	
$_SESSION['autopagetoken'] = microtime(1);

if(!$resend && !$merchantAuthorizationProblem && $originalAsOfDate) { //OBSELETE when ajax-invoice-charge-client.php is approved // "Charge Clients" button was hit  

	$clientIds = array();
	$emailCount = 0;
	$errors = array();
	foreach($_POST as $key => $val) {
		if(strpos($key, 'client_') === 0) {
			$clientid = substr($key, strlen('client_'));
			$charge = $_POST["charge_$clientid"];
			if(!$charge) continue;
			$cc = getActivePaySource($clientid); //getCC($_POST["ccid_$clientid"], $clientid);
			if(!$cc || !is_array($cc)) {
				$err = "Active credit card or ACH info not found for {$clientDetails[$clientid]['clientname']} [$clientid].  Please send LeashTime Support this note.";
				$errors[] = $err;
				logError("invoice-autopay[native POST]: $err");
				continue;
			}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "Client: [$clientid] charge: [$charge]".print_r($cc, 1); exit; }			
			$clientIds[] = $clientid;
			$sendReceipt = $_POST["emailreceipt_$clientid"];
			
			// try to charge the client
			if($charge > $greatestCCPayment) continue; //return array('FAILURECODE'=>LT_EXCESSIVE_CHARGE);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "client: $clientid charge: $charge cc: ";print_r($cc); exit;}			
			$transactionid = payElectronically($clientid, $cc, $charge, "Auto-Pay", $sendReceipt, $dontApplyPayment=false);
//$transactionid = array(1);			
			
			if(is_array($transactionid)) {
				$errors[] = "Electronic transaction failed for {$clientDetails[$clientid]['clientname']}.";
				continue;
			}
			// if successful, create the invoice and email a receipt
			//$ccPayment format: amount|transactionId|ccid|company|last4|acctid
			$invoiceid = createCustomerInvoiceAsOf($clientid, $asOfDate, "$charge|$transactionid|{$cc['ccid']}|{$cc['company']}|{$cc['last4']}|{$cc['acctid']}");
			// register the payment
			// PAYMENT IS REGISTERED IN payElectronically -- applyEPayment($clientid, $cc, $charge, "Auto-Pay", $transactionid, $sendReceipt);
			if($sendReceipt) {
				if($message = emailInvoice($clientDetails[$clientid], $invoiceid, $cc)) $errors[] = $message;
				else $emailCount++;
			}
			
		}
	}
	// after this we'll need to assemble the results section of the page...
}

function emailInvoice($client, $invoiceid, $cc) {
	global $clientDetails;
	if(!$client['email']) return "No email address found for {$client['clientname']}.\n";
	$standardInvoiceMessage = 
		"Hi #RECIPIENT#,<p>Here is your latest invoice reflecting the latest charge to your #PAYMENTMODE#."
			."<p>Sincerely,<p>#BIZNAME#";
	$standardMessageSubject = "Your Invoice";
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Autopay Email'");
	if($template) {
		$standardInvoiceMessage = $template['body'];
		$standardMessageSubject = $template['subject'];
	}
	
	if(strpos($standardInvoiceMessage, '#PETS#') !== FALSE) {
		require_once "pet-fns.php";
		$petnames = getClientPetNames($client['clientid'], false, true);
	}

	$paymentMode = $cc['ccid'] ? "credit card" : "bank account";	
	require_once "email-template-fns.php";
	
	$msgbody = mailMerge($standardInvoiceMessage, 
												array('#RECIPIENT#' => $client['clientname'],
															'#PAYMENTMODE#' => $paymentMode,
															'#FIRSTNAME#' => $client['fname'],
															'#LASTNAME#' => $client['lname'],
															'#BIZNAME#' => $_SESSION['bizname'],
															'#PETS#' => $petnames,
															'#LOGO#' => templateLogoIMG()
															));
	$msgbody = plainTextToHtml($msgbody);						

	$msgbody =  
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" />'
  .$msgbody
  ."<hr style='page-break-after:always;'>"
	.getInvoiceContents($invoiceid);

	updateTable('tblinvoice', array('notification'=>'email', 'lastsent'=>sqlVal('CURDATE()')), "invoiceid = $invoiceid");

	//if(notifyByEmail($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html'))
	//	echo "Failed to email invoice to {$client['clientname']} ({$client['email']}):\n$error\n";
	//else $invoiceCount++;
	enqueueEmailNotification($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html');
	return null;
}



// for each client collect:
//	last invoice, if any, or array(clientid)

if($linitial && ($linitial != 'ALL'))
	foreach($clientDetails as $k => $client)
		if(strpos(strtoupper($client['lname']), strtoupper($linitial)) === 0)
			$clientIds[] = $k;
$filter = $clientIds ? "WHERE clientptr IN (".join(',', $clientIds).")" : '';
//$invoices = fetchAssociationsKeyedBy("SELECT * FROM tblinvoice $filter ORDER BY date, invoiceid", 'clientptr');
//foreach(array_keys($clientDetails) as $clientid)
//	if(!isset($invoices[$clientid])) $invoices[$clientid] = array('clientptr'=>$clientid);

?>
<style>
.highlightedinitial {background:darkblue;color:white;font-weight:bold;flow:inline;padding-left:5px;padding-right:5px;}
</style>
<?

//################ INVOICES TO AUTOPAY
echo "<p><div class='bluebar'>Invoices to AutoPay</div></p>";


if($merchantAuthorizationProblem) 
	echo "<p><font color='red'>ERROR: $merchantAuthorizationProblem</font><p>"
				."Please see the <b>Credit Card Merchant Info</b> setting on the <a href='preference-list.php'>Preferences Page</a> to address this issue.<p>";
if($emailCount) echo "<font color='green'>$emailCount invoices were emailed.</font><p>";
if($errors) {
	echo "<font color='red'>WARNING:<ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
}
// find all users with Amount Due 


echo "<div style='position:relative;float:right';>";
echoButton('chargebutton','Generate Invoices & Charge Selected Clients','chargeSelectedClients()');
echo "</div>";
echo fauxLink('Select All', "selectAll(\"client\", 1)", 'Select all current invoices for printing.');
echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>";
echo fauxLink('Deselect All', "selectAll(\"client\", 0)", 'Clear all current invoice selections.');
echo "<p>";


$clientReceiptPrefs = fetchKeyValuePairs("SELECT clientptr, value FROM tblclientpref WHERE property = 'autoEmailCreditReceipts'");
$defaultReceiptPref = $_SESSION["preferences"]['autoEmailCreditReceipts'];
$autopayerSources = fetchAssociationsKeyedBy("SELECT * FROM tblcreditcard WHERE active=1 AND primarypaysource=1 AND autopay=1", 'clientptr');
$autopayerACHs = fetchAssociationsKeyedBy("SELECT * FROM tblecheckacct WHERE active=1 AND primarypaysource=1 AND autopay=1", 'clientptr');
foreach($autopayerACHs as $clientptr => $ach) $autopayerSources[$clientptr] = $ach;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo "==>".join(", ", $clientIds);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo "==>".join(", ", array_keys($clientDetails));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo " 820 ==>".print_r($autopayerSources[820], 1);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo "820==>".amountDue(820, $asOfDate);
$chargeIds = array();
$columns = explodePairsLine('cb| ||client|Client||amountDue|Amount Due||charge|Charge||status|Last Payment||emailReceipt|Email receipt');
$colClasses = array('amountDue'=>'dollaramountcell');


screenLog("Time from start to clientDetails LOOP: ".round((microtime(1)-$page_start_time)*1000)." ms");


$rows = array();
$displayedClientIds = array();
foreach($clientDetails as $clientid => $client) {
//if($_SESSION['staffuser']) {echo print_r($clientDetails[820], 1); exit;}
	
	if(!$autopayerSources[$clientid]) continue;
//$amdstart = microtime(1); // this is the slow one
	$amountDue = amountDue($clientid, $asOfDate);
//$amdtotal += microtime(1)-$amdstart;
	if($amountDue <= 0) continue;
	$totaldue += $amountDue;
	$displayedClientIds[] = $clientid;
	//$amountDue = number_format($amountDue, 2, '.', ',');
	$amountDueLink = fauxLink(dollarAmount($amountDue), "viewInvoicePreview($clientid)", 1);
	$cb = "<input type='checkbox' id='client_$clientid' name='client_$clientid'>";
	$emailReceipt = isset($clientReceiptPrefs[$clientid]) ? $clientReceiptPrefs[$clientid] : $defaultReceiptPref;
	$checked = $emailReceipt ? 'CHECKED' : '';
	$emailReceipt = "<input type='checkbox' id='emailreceipt_$clientid' name='emailreceipt_$clientid' $checked>";
	$charge = "<input id='charge_$clientid' name='charge_$clientid' value='$amountDue' autocomplete='off'>";
	$chargeIds["charge_$clientid"] = "\"charge_$clientid\",\"Charge for ".addslashes($client['clientname'])."\"";
	$clientLink = clientLink($client);
//if(staffOnlyTEST()) 	$clientLink = "<a href='client-edit.php?tab=account&id=$clientid' target='OTHER'>@#@</a> ".$clientLink;
	$lastApproval = lastApproval($autopayerSources[$clientid]);
	$totalApproved += $lastApproval[1];
	$rows[$clientid] = array('cb'=>$cb, 'client'=>$clientLink, 'amountDue'=>$amountDueLink, 'charge'=>$charge,
								'emailReceipt'=> $emailReceipt,
								'status'=>$lastApproval[0]);
}

screenLog("amountDue time: ".round($amdtotal*1000)." ms");

$allTimes = array('lastInvoiceDatesByClient'=>$lidbctotal, 
	'getInvoiceAccountBalance'=> $giabtotal, 'getUninvoicedBillables'=> $gubtotal, 
	'getTotalClientCreditsSinceLastInvoice'=> $gtccslitotal);
foreach($allTimes as $label => $totaltimetorun)
	screenLog("$label time: ".round($totaltimetorun*1000)." ms");
	
screenLog("Time from start to clientDetails LOOP COMPLETION: ".round((microtime(1)-$page_start_time)*1000)." ms");

$totaldue = dollarAmount($totaldue);
$totalApproved = dollarAmount($totalApproved);
$rows = array_merge(array(array(
	'#CUSTOM_ROW#'=>"\n<tr><td>&nbsp;</td><td class='dollaramountcell' style='font-weight:bold'>Total:</td>
	<td class='dollaramountcell'>$totaldue</td>
	<td class='dollaramountcell' style='font-weight:bold'>Total:</td>
	<td class='dollaramountcell' style='text-align: left;'>$totalApproved</td>")),
	(array)$rows);
$rowClasses = array_merge(array(null), (array)$rowClasses);
		
	$tableWidth = $oneClient ? '90%' : '100%';
if($rows) {

screenLog("Time up until findIncompleteJobsResultSet: ".round((microtime(1)-$page_start_time)*1000)." ms");
//$timeBeforeFindIncompleteJobsResultSet = microtime(1);
	$result = findIncompleteJobsResultSet('1970-01-01', $asOfDate, $prov=null, $sort=null, join(',', $displayedClientIds), $limit=null);
screenLog("Time from start to finish findIncompleteJobsResultSet: ".round((microtime(1)-$page_start_time)*1000)." ms");


	$incompleteClients = array();
	if($result) {
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC))
		 $incompleteClients[$row['clientptr']] = $incompleteClients[$row['clientptr']]+1;
	}
	foreach($incompleteClients as $clientid => $num)
		$rows[$clientid]['amountDue'] = "<span title='$num incomplete visits.' style='background-color:yellow;'>{$rows[$clientid]['amountDue']}</span>";
}


?>
<form name= 'chargeclientsform' method='POST' <? if(ccDEVTestMode()) echo "action='cc-invoice-charge-clients.php'"?>>
<?
hiddenElement('originalAsOfDate', $asOfDate);
hiddenElement('autopagetoken', $_SESSION['autopagetoken']);
foreach($displayedClientIds as $clientid) 
	hiddenElement("ccid_$clientid", $autopayerSources[$clientid]['ccid']);

if($_SESSION['staffuser']) screenLog('clients: '.count($rows));
tableFrom($columns, $rows, "WIDTH=$tableWidth",null,null,null,null,$colSorts,$rowClasses, $colClasses, 'aSortClickAction');
?>
</form>
<?
//################ SUCCESSFULLY COMPLETED
echo "<p><div class='bluebar'>Successfully Completed Transactions for Period</div></p>";
/*$lastBillDates = array();
$lastBillDate = null;
foreach(array('bimonthlyBillOn1', 'bimonthlyBillOn2') as $k) {
	$n = $_SESSION['preferences'][$k];
	if($n && ($n != '--')) $lastBillDates[] = $n;
}
if($lastBillDates) {
	sort($lastBillDates);
	$dom = date('j');
	foreach($lastBillDates as $bd) if($dom > $bd) $lastBillDate = $bd;
}

if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r( $lastBillDate); }
*/

// $lastBillDate was set in invoices-top.php

if($lastBillDate) {
	//$lastBillDate = date("Y-m-d", strtotime(date("Y-m-$lastBillDate")));
	$rows = fetchAssociations(
		"SELECT * from tblchangelog 
			WHERE itemptr > 0 AND operation = 'p' 
				AND (itemtable = 'ccpayment' OR itemtable = 'achpayment') 
				AND `time` > '$lastBillDate' ORDER BY `time` desc");
	if($rows) {
		$ccids = array();
		$achids = array();
		foreach($rows as $i => $row) {
			if($row['itemtable'] == 'ccpayment') $ccids[] = $row['itemptr'];
			if($row['itemtable'] == 'achpayment') $achids[] = $row['itemptr'];
		}
		if(!$ccids) $ccids = array(-1);
		if(!$achids) $achids = array(-1);
		$clientsByACH = fetchKeyValuePairs("SELECT acctid, clientptr FROM tblecheckacct WHERE acctid IN (".join(',', $achids).")");
		$clientsByCC = fetchKeyValuePairs("SELECT ccid, clientptr FROM tblcreditcard WHERE ccid IN (".join(',', $ccids).")");
		if($clientsByCC || $clientsByACH) $recentInvoices = 
			fetchKeyValuePairs(
							"SELECT clientptr, invoiceid 
								FROM tblinvoice 
								WHERE clientptr IN (".join(',', array_merge($clientsByCC, $clientsByACH)).") ORDER BY date");

		$columns = explodePairsLine('date|Date||client|Client||amountDue|Amount Due||payment|Payment');
		$colClasses = array('amountDue'=>'dollaramountcell', 'payment'=>'dollaramountcell');
		foreach($rows as $i => $row) {
			$clientid = $row['itemtable'] == 'ccpayment' ? $clientsByCC[$row['itemptr']] : $clientsByACH[$row['itemptr']];
			if(!$clientid) continue;
			$rows[$i]['amountDue'] = amountDue($clientid, $asOfDate);
			$rows[$i]['amountDue'] = $rows[$i]['amountDue'] ? dollarAmount($rows[$i]['amountDue']) : paidLink($recentInvoices[$clientid]);
			$rows[$i]['client'] = $clientDetails[$clientid]['clientname'];
			// Approved-100.09|2151350682
			if(strpos($row['note'], 'Approved-') !== 0) {
				unset($rows[$i]);
				continue;
			}
			$notes = explode('|', substr($row['note'], strlen('Approved-')));
			$transid = $notes[1];
			if($transid) 
				$creditid = fetchRow0Col0(
					"SELECT creditid 
						FROM tblcredit 
						WHERE payment = 1 
							AND (externalreference = 'CC: $transid' OR externalreference = 'ACH: $transid')");
			$rows[$i]['payment'] = $notes[0] && $creditid ? paymentLinkAutoPayVersion(dollarAmount($notes[0]), $creditid) : $notes[0];
			$rows[$i]['date'] = shortDateAndTime(strtotime($row['time']), 'mil');
			$rowClasses[] = ($rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask');
		}
		$tableWidth = '80%';
		
		tableFrom($columns, $rows, "WIDTH=$tableWidth",null,null,null,null,$colSorts=null,$rowClasses, $colClasses, 'aSortClickAction');
	}
}
if($_SESSION['staffuser']) screenLog("Time to run invoices-autopay.php: ".round((microtime(1)-$page_start_time)*1000)." ms");
			
function clientLink($client) {
	//if(prettyNames.length == 0) setPrettynames("charge_771","Charge for TEST (0TestyPets)","charge_1220","Charge for Debbie Negus (A+ Pets & Plants)","charge_812","Charge for Queenie's Pets (Adina SILBERSTEIN)","charge_795","Charge for Amanda's Pet Care (Amanda Carlson)","charge_792","Charge for omidog (Erin Markish)","charge_815","Charge for Tickled Paws, LLC. (Inga DaMota)","charge_797","Charge for Malibu Pet Care (Jackie Bass)","charge_767","Charge for 203 Pets (Jason Hoffman)","charge_760","Charge for Dog Walking DC (JJ Scheele)","charge_788","Charge for Dog Camp LA LLC (Jula Bell)","charge_751","Charge for No Worries 4 Pets (Katherine McCarter)","charge_796","Charge for Purrs N Woofs (Kim Stordahl)","charge_1094","Charge for Laura the Pet Nanny (Laura Beattie)","charge_773","Charge for Wisconsin Pet Care (Lori Mendelsohn Thomas)","charge_800","Charge for Cahill's Critters (Megan Cahill)","charge_777","Charge for Nanny Dolittle (Michelle Reece)","charge_798","Charge for Comfort Paws, pet sitting and dog walking ser (Patti Reichel)","charge_785","Charge for Sacramento Pet Care (Rick Oldham)","charge_770","Charge for DogCentric Dog Walking Services (Sheila Stinson)","charge_748","Charge for Belly Rubs (Teresa Hogge)","charge_1134","Charge for White Rock Pet Services (Tiffany Hammer Manson)","charge_766","Charge for Paws a Go Go (Tina Gerin)","charge_844","Charge for Dirty Feet Pet Care Abby Cunningham","charge_1514","Charge for Saving Grace Pet Care NoMa Adam Friberg","charge_1750","Charge for Agi's dogs Agnes Miko","charge_1974","Charge for American Dogs LLC AJ Bredensteiner","charge_1393","Charge for Dog Walks by Alana Rose Alana Rose","charge_1328","Charge for Pawlosophy Alexis Hamm","charge_1039","Charge for Fur'st Love Pet Care Allison Sorokin","charge_1830","Charge for Kent County Pet Sitting, LLC Alyssa Edwards","charge_871","Charge for AGV Pet Sitting & More Amanda Gomez-Vidal","charge_1177","Charge for Happy Paws Pet Sitting Service Angela Kearney","charge_1649","Charge for Poochy Doos, LLC Ann Jones","charge_1020","Charge for Lucky Mutts and More Pet Services Anne Manske","charge_1295","Charge for PupperScruff Pets, LLC Annie Wolfson","charge_855","Charge for Calm Pets, Happy Pets LLC April Richards","charge_1950","Charge for Ready Pet Go Ashley Burgherr","charge_1190","Charge for Dogs Gone Walking LLC Barbara Woodward","charge_861","Charge for 2 Paws up Inc Barbie Klapp","charge_1744","Charge for Kenosha Pet Sitter, LLC Ben Johnson","charge_835","Charge for Paws Pet Care Betheny Green","charge_1584","Charge for Always Happy Tails Brook Dollison","charge_1977","Charge for Sandy Paws Bryony Aviles","charge_1957","Charge for Happy Tails of South Jersey Cara Ruth","charge_1390","Charge for Dog City Chicago Carine","charge_1257","Charge for Wagamuffin Pet Care LLC Carla Ferris","charge_1225","Charge for DogOn Fitness Carol Brooks","charge_1174","Charge for Pawsome Pets, Inc. Carole Nazar","charge_881","Charge for All Breed Care Carolyn Noble","charge_1280","Charge for Pawsitively Priceless Petsitting Carolyn Sturgill","charge_1117","Charge for Pleasant Pet Services, LLC Chris Mignot","charge_992","Charge for BlueDog Chris Palermo","charge_1817","Charge for Uniquely Feline Christine Bender","charge_1636","Charge for Sunshine Pet and Home Care Christopher Dill","charge_1482","Charge for Manitowoc Pet Sitters Cindi Ashbeck","charge_1763","Charge for BAR Pet Services Craig Robertson","charge_1004","Charge for Carey Pet & Home Care Cris Carey","charge_1560","Charge for The Furry Godmother of Conejo Valley Dale Thall","charge_1896","Charge for Dog Treks Dan Harlow","charge_1987","Charge for Where My Dogs At! Danny Paton","charge_1331","Charge for In Home Pet Sitters Darlene Krause","charge_853","Charge for Sleepy Paws Pet Care Dave Westwood","charge_1354","Charge for Critter Caretakers Pet Sitting Dawn Dubelbeis","charge_872","Charge for Lots of Luv'n Debbie Goodall","charge_912","Charge for Happy Bones Pet Sitting Debbie Mursch","charge_898","Charge for iPet Chicago Diego Leon","charge_1075","Charge for Pitter pat Donna King","charge_876","Charge for Auntie Em Emery Fitzgerald","charge_1749","Charge for Olivera Pet Care Emily Olivera","charge_857","Charge for The Gratefull Dog Emily Shervin","charge_1259","Charge for Perfect Pair Animal Care Erika Hunter","charge_1021","Charge for Le pooch Erin Knable","charge_1948","Charge for The Pet GurL Erin McInnis","charge_1152","Charge for All Walks of Life Evan Mayes","charge_1046","Charge for Hooves, Paws and Claws pet sitting Gail Hernandez","charge_847","Charge for Priceless Pet Care Ginger Hendry","charge_825","Charge for Saving Grace Pet Care Grace Steckler","charge_984","Charge for P.U.P.S.- Puppy Uprising Pet Sitters Greg Dean","charge_1302","Charge for Pawsitive Steps Halie Dodson","charge_1767","Charge for Good Dog Pet Care Inga DaMota","charge_1586","Charge for DogOn Fitness MD Jackie Fahr","charge_1365","Charge for Denver Dog Joggers Jacob Venter","charge_1426","Charge for Pawfect Day Jacqueline Rivera","charge_1823","Charge for Miss Jane's Pet Sitting Jane Mitchell","charge_1633","Charge for Faithful Companions Pet Sitting Jason Carlucci","charge_924","Charge for Pampered Pets Jeffrey Brashear","charge_2000","Charge for Mobile Mutts North Jennifer Gladysz","charge_1976","Charge for Mobile Mutts South Jennifer Gladysz","charge_1748","Charge for Faithful Friends Pet Care Jennifer Godshall","charge_1738","Charge for Five Paws of Delaware County Jennifer Hardy","charge_1275","Charge for Rufus and Delilah Jennifer Shafton","charge_1516","Charge for Bull City Pet Sitting Jennifer Thornburg","charge_1512","Charge for Bright Star Pet Sitting Jessica Frost","charge_2005","Charge for Jess' Four Paws Pet Sitting Jessica McKinney","charge_811","Charge for Higgins and Friends Pet Sitting Jill Weissenbach","charge_814","Charge for Crestview Pet Care (Pawsitive Pooch) Jillian Enright","charge_1778","Charge for My Pet's Friend, LLC Joe Whistler","charge_2019","Charge for Surf Sitters pet care and dogwalk john barrile","charge_1587","Charge for Comfy Creatures John Chapman","charge_1388","Charge for Jordan's Pet Care Jordan Sachs","charge_2015","Charge for Canine Adventure LLC Joshua Rickey","charge_1707","Charge for Pets R Us Pet Sitting Services LLC Josie Webb","charge_1417","Charge for Furever Friends Pet Sitting, LLC Julie Azud","charge_1268","Charge for It's a Ruff Life Julie Clifford","charge_1165","Charge for The Pooper Scoopers, Inc. Kandra Witkowski","charge_841","Charge for TLC House & Pet Sitting Service Kara Jenkins","charge_1310","Charge for Laughing Pets Atlanta Karen E. Levy","charge_1965","Charge for Wunderbar Pet Sitting & Dog Walking Karla Roch","charge_1502","Charge for Kate's Critter Care Kate Turlington","charge_1701","Charge for Pampered Petz LLC Kathleen Fitzsimmons","charge_1313","Charge for Comfy Cozy Pet Sitting Kathy Henderson","charge_1096","Charge for Peace of Mind Pet Sitting Katie LeBarre","charge_2037","Charge for Windy City Paws Katie Pape","charge_1519","Charge for The Groove Hound Kelly & Gert Schooley","charge_2056","Charge for Kelly's Pet Sitting, LLC Kelly Hall","charge_1061","Charge for Hart 2 Hart Pet Care Kim Hart","charge_1855","Charge for Keep Me Company Pet Sitting Kim Lehman","charge_1440","Charge for Pampered Paws Pet Sitting Kim Rhoades","charge_846","Charge for The Pet Elf Inc Kim Waite-Williams","charge_1670","Charge for Pawfect Pet Lovers Care Kimberly A Pevaroff","charge_1542","Charge for Home Comforts Pet Sitting Service LLC Kimberly Panico","charge_1352","Charge for West Hartford Pet Sitters Kimberly Thomas","charge_856","Charge for Pet Sitters Unlimited, LLC Kirsten Hall","charge_1228","Charge for From the Paws Up Kristal Luedloff","charge_1104","Charge for Beyond Barks LLC Kristy Houck","charge_879","Charge for Paws Luv Walks Kylie Tsangaris","charge_1878","Charge for Posh Pet Services Lara A. Pitek","charge_1267","Charge for PURRfect Pet Companion Laura Daugherty","charge_1991","Charge for Pawsitively Pooches Lauren Piner","charge_819","Charge for Happy at Home Pet Sitting Lea Kachler-Leake","charge_995","Charge for Peak City Puppy - Professional Pet Care Lesley Lovelace","charge_1780","Charge for Sidehill Sitters Liana Sanders","charge_1013","Charge for Barnyards & Backyards Farm and Pet Sitting Lily Marie Plasse","charge_1479","Charge for Critter Sitters, LLC Lindsay Jones","charge_1488","Charge for Four Paws Pet Sitting Services Lois Kelly","charge_1081","Charge for Don't Leave Me This Way Pet Sitting Lorraine Hough","charge_1503","Charge for Lucy's Pet Care Lucy Moore","charge_821","Charge for The Dog Walking Network Lynne Ruffini","charge_1293","Charge for Love and Kisses Pet Sitting Maureen McCarthy","charge_1705","Charge for Beantown Hounds Meghan Grabau","charge_911","Charge for Happy Tails Animal Care Melanie Michon","charge_1406","Charge for Houston's Best Pet Sitters Melanie Whitman","charge_1505","Charge for Northbrook Pet Nannies Melissa Morreale","charge_1759","Charge for Crofton Dog Walkers Melissa Scott","charge_919","Charge for Two Dads Pet Services Michael Howell","charge_1368","Charge for Little Sweethearts Pet Sitting Michelle Bollinger","charge_1093","Charge for Tails on Trails, LLC Michelle Cohn","charge_1775","Charge for Menagerie Pet Sitting Michelle Miller","charge_1413","Charge for Mike's Walkers LLC Mike Davis","charge_1859","Charge for Pet Pampering Plus Mindy Nance","charge_816","Charge for My Pet's Buddy Miranda Murdock","charge_890","Charge for Urban ReTreat Chicago Molly Marino","charge_1863","Charge for "Good Dog!" Walking and Sitting LLC Molly Obert","charge_1940","Charge for Life is dog Nance Moran","charge_1261","Charge for Caring Paws Pet Sitting Services Nancy Petrino","charge_1051","Charge for Happier At Home Pet Sitting, LLC (OH) Nichole Kelland","charge_1044","Charge for PAWS & ANCHOR Nicole Crowley","charge_824","Charge for Personable Pet Care Nicole Linden","charge_1899","Charge for Got Paws Nikki Ledingham","charge_1006","Charge for Creature Comfort Custom Concierge Care Olivier Palladin","charge_2018","Charge for inTune Pet Services Patrick Chesnut","charge_1339","Charge for Happy Paws Pet Sitting Plus Rick Neale","charge_859","Charge for Good Doggy Rita Nemeth","charge_980","Charge for VIP Pet Services Robin Perdue","charge_863","Charge for Gwinnett Pet Watchers Robin Taylor","charge_1714","Charge for City Kitty Sitter Ruth Crampton","charge_864","Charge for Wuffy Walks Ruth Pistell","charge_1185","Charge for Dog Days NJ Ryan Roberts","charge_1887","Charge for Critter Sitters Sandy Hafenbrack","charge_2003","Charge for Spoiled Rotten Pet Sitting Sara Paulik","charge_983","Charge for Sarah's Pet Sitting Sarah MacDonald","charge_1566","Charge for Prestige Pet-Sitting Agencies, LLC Sarah Roller","charge_1276","Charge for Kings & Queens House Sitting and Pet Care Scherree Smith","charge_1661","Charge for Creature Comforts Pet Sitting Service Sherry Nichols","charge_1630","Charge for Valley Pet Sitting Shira Winitzky","charge_1231","Charge for Urban Tailz Sue Bowman","charge_1755","Charge for Urban Tailz in the loop Sue Bowman","charge_869","Charge for Fur Friendly Pet Sitting Sue Leach","charge_1862","Charge for Doghouse Girls Sue Loeffler","charge_1412","Charge for Waggy Tails Pet Sitting + Dog Walking Summer Tannhauser","charge_1575","Charge for Daisys Playful Pups LLC Sunil Ramkissoon","charge_1224","Charge for Lucky Pawz Pet Care Susan Lundvig","charge_1880","Charge for PTC Pet Services Tanya Fairbanks","charge_988","Charge for Leader of the Pack Pet Care, Inc. Tari Rutherford","charge_1166","Charge for 4 Pets' Sake Pet Sitting Services Terri Robinson","charge_1145","Charge for (The Pet Nanny and Dog Walker) Tess Ross","charge_1485","Charge for Theresa's Tender Care Theresa Dinuzzo","charge_914","Charge for Pet Nanny Services Tracie Gallagher","charge_1910","Charge for Watch Dog Pet Sitting, Inc. Tracie Grinnell","charge_1773","Charge for Martin Critter Care Petsitting and Dogwalking Tracy Martin","charge_850","Charge for Gold Coast Pet Sitting Vanessa","charge_1979","Charge for Animals Reign, llc Vicki Holt","charge_938","Charge for Desert Dog Pet Care Virginia","charge_1450","Charge for HouseBroken Vivian Villegas","charge_1305","Charge for House Calls Pet Sitting Wendy Tom");	
	global $autopayerSources;
	if($problem = primaryPaySourceProblem($autopayerSources[$client['clientid']])) {
		$problem = str_replace(' ', '&nbsp;', $problem);
		$problem = " <span style='color:red;font-variant:small-caps'>$problem</span>";
	//if(isAnExpiredCard($autopayerSources[$client['clientid']])) {
	//	$expiration = " <span style='color:red;font-variant:small-caps'>card&nbsp;expired</span>";
	}
	$editor = $autopayerSources[$client['clientid']]['ccid'] ? 
							"cc-edit.php?client={$client['clientid']}"
							: "ach-edit.php?client={$client['clientid']}";
	return fauxLink($client['clientname'], 
					"openConsoleWindow(\"cceditor\", \"$editor\",420,470);",
					1).$problem;
}

function paymentLinkAutoPayVersion($label, $paymentptr) {
	return fauxLink($label, "editPayment($paymentptr)", 1, "View this payment");
}

function paidLink($invoiceptr) { //invoiceIdDisplay($invoiceptr)
	if(!$invoiceptr) return 'PAID';
	return fauxLink('PAID', "viewInvoice($invoiceptr, \"$email\")", 1, 'View the most recent invoice');
}
?>

<script language='javascript'>

function editPayment(paymentptr) {
	openConsoleWindow('paymentview', 'payment-edit.php?id='+paymentptr, 500, 300);
}

function viewInvoicePreview(clientptr) {
	var asOfDate = asOfDate = escape('<?= $_REQUEST['originalAsOfDate'] ? $_REQUEST['originalAsOfDate'] : $asOfDate ?>');	
	openConsoleWindow('invoiceview', 'invoice-edit.php?autopayview=1&client='+clientptr+'&asOfDate='+asOfDate, 800, 800);
}

function getSelectedClientIds() {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) {
		var elid = els[i].id;
		if(els[i].type == 'checkbox' && elid.indexOf('client_') == 0 && els[i].checked) 
			sels[sels.length] = elid.substring(elid.indexOf('_')+1);
	}
	return sels;
}

function chargeSelectedClients() {//alert('Patience, Ted, patience.'); return;

<? //if($merchantAuthorizationProblem) echo "alert('Please correct the $merchantAuthorizationProblem problem first.'); return;\n"; ?>
	if(prettyNames.length == 0) setPrettynames(<?= join(",\n", $chargeIds) ?>);

	var sels = getSelectedClientIds();
	sels = sels.join(',');
	if(sels.length == 0) {
		alert('Please select one or more clients to charge.');
		return;
	}
  if(!MM_validateForm(
<?
$conditions = array();
foreach($chargeIds as $id => $unused) {
	$conditions[] = "'$id', '', 'UNSIGNEDFLOAT'";
	$conditions[] = "'$id', '$greatestCCPayment', 'MAX'";
}
echo join(",\n", $conditions);
?>
	)) return;
	
	// check for login
	ajaxGetAndCallWith('cc-login-needed-ajax.php', chargeClientsCallback, null);
	//document.chargeclientsform.submit();
}

/* Schematic:
chargeSelectedClients() => cc-login-needed-ajax.php => chargeClientsCallback
chargeClientsCallback submits form if logged in or => ccLoginDialog
LoginDialog window => (quit) or => ccLoginDialogAction
ccLoginDialogAction() => cc-login-needed-ajax.php => chargeClientsCallback
*/


function chargeClientsCallback(unused, loginNeeded) {
//alert(loginNeeded);
	if(loginNeeded == "1") ccLoginDialog();
	else if (loginNeeded == "2") document.location.href='index.php';
	else {
		document.getElementById('chargebutton').disabled = true;
		// estimate time to completion
		var estimate = getSelectedClientIds().length * 1 /* second */;
		estimate = estimate < 60 ? 'a minute' : 'several minutes';
		document.getElementById('pleasewaitbox').style.display='block';
		document.getElementById('pleasewaitbox').innerHTML =
		  "<span style='font-size:2.5em;font-weight:bold;'>Please Wait</span>"
		  +"<span style='font-size:1.75em;font-weight:bold;'><p>while the invoices are generated and clients are billed.</span>"
		  +"<span style='font-size:1.5em;font-weight:bold;'><p>This may take "+estimate+".</span>"
		  +"<span style='font-size:1.2em;font-weight:bold;'><p>Please do <font color=red>not</font> refresh the page.</span>"
		document.chargeclientsform.submit();  // USED TO CALL THIS SCRIPT.  NOW LAUNCHES cc-invoice-charge-clients.php.
	}
}

function ccLoginDialog() {
	document.getElementById('password').value='';
	document.getElementById('passwordbox').style.display='block';
}

function ccLoginDialogAction() {
	var p = document.getElementById('password').value;
	document.getElementById('password').value='';
	document.getElementById('passwordbox').style.display='none';
	// check for login
	ajaxGetAndCallWith('cc-login-needed-ajax.php?p='+p, chargeClientsCallback, null);
}

</script>
<div id='passwordbox' style='position:absolute;top:200px;left:300px;width:200px;height:75px;display:none;border: solid black 2px;padding:10px;background:white;'>
Please enter your password:<p>
<input type='password' id='password' autocomplete='off'><p>
<input type='button' value='Ok' onClick="ccLoginDialogAction()"></input>&nbsp;&nbsp;<input type='button' value='Cancel' onClick="alert('No clients charged.');document.getElementById('passwordbox').style.display='none'"></input>
</div>
<div id='pleasewaitbox' style='text-align:center;position:absolute;top:200px;left:170px;width:350px;height:175px;display:none;border: solid black 2px;padding:10px;background:white;'>
</div>
<script language='javascript'>
//ccLoginDialog();
</script>
<?
// ***************************************************************************
include "invoices-bottom.php";

function lastApproval($card) {
	if($card['ccid']) {
		$itemtable = 'ccpayment';
		$itemptr = $card['ccid'];
	}
	else {
		$itemtable = 'achpayment';
		$itemptr = $card['acctid'];
	}
	$lastChange = fetchFirstAssoc("SELECT * from tblchangelog WHERE itemptr = $itemptr AND itemtable = '$itemtable' ORDER BY time desc LIMIT 1");
	if(!$lastChange) return array('-', 0);
	$status = explode('-', $lastChange['note']);
	
	$title = '';
	if($status[0] == 'Approved') {
		$detail = explode('|', $status[1]);
		$status = 'Approved: '.dollarAmount($detail[0]);
		$paid = $detail[0];
	}
	else if($status[0] == 'Error' || $status[0] == 'Declined') {
		$detail = explode('|', $status[1]);
		$status = "<u>{$status[0]}</u>";
		if(count($detail) > 1) {
			$amt = explode(':', $detail[1]);
			$status .= ' '.dollarAmount($amt[1]);
		}
		$title = "title= \"".getDetailedErrorMessage($detail)."\"";
	}
	else $status = $lastChange['note']; //print_r($status, 1);  //$status[0]; 
	return array("<span $title>".shortDate(strtotime($lastChange['time']))." $status", $paid);
}

function getDetailedErrorMessage($changeLogNoteSections) {
	foreach((array)$changeLogNoteSections as $str) {
		if(strpos($str, 'ErrorID:') === 0)
			$errid = substr($str, strlen('ErrorID:'));
		else if(strpos($str, 'Gate:') === 0)
			$gatewayName = substr($str, strlen('Gate:'));
	}
	if($errid && $gatewayName) {
		$err = fetchRow0Col0("SELECT response FROM tblcreditcarderror WHERE errid = $errid LIMIT 1");
//screenLog("[$err]");		
		$gateway = getGatewayObject($gatewayName);
		return $gateway->ccLastMessage($err);
	}
	return $changeLogNoteSections[0];
}

function amountDue($clientid, $asOfDate=null) {
		global $lastInvoiceDatesByClient;
		
		global $lidbctotal, $giabtotal, $gubtotal, $gtccslitotal;
		$lidbcstart = microtime(1);
		if(!isset($lastInvoiceDatesByClient)) $lastInvoiceDatesByClient = lastInvoiceDatesByClient();
		$lidbctotal += microtime(1)-$lidbcstart;
		/*
	To figure amount due:
	1. Add up unpaid invoices and calculate Previous balance
	2. Use all uninvoiced billables to calculate subtotal
	3. Consume credits to pay billables and calculate payments and credits
	4. Find all uninvoiced billables
	5. Collect all credits/payments between last two billing dates
	*/

		// find prior invoices with unpaid balances [array(invoiceid=>balance, ...)] and
		/*$unpaidInvoiceIds = fetchCol0("SELECT invoiceid FROM tblinvoice WHERE clientptr = $clientid AND paidinfull IS NULL");
		$priorInvoiceBalances = array();
		foreach($unpaidInvoiceIds as $oldInvoice) {
			$priorInvoiceBalances[$oldInvoice] = getUnpaidInvoiceBalance($oldInvoice);
		}
		$accountBalance = array_sum($priorInvoiceBalances);	*/
		$giabstart = microtime(1);
		$accountBalance = getInvoiceAccountBalance($clientid);
		$giabtotal += microtime(1)-$giabstart;
//if($_SESSION['staffuser'] && $clientid==820) echo "accountBalance 820==>$accountBalance";

		//$accountBalance = getAccountBalanceIncludingBillablesAsOf($clientid, $asOfDate);
	
		/*$pastBalanceDue = fetchRow0Col0("SELECT origbalancedue FROM tblinvoice WHERE clientptr = $clientid ORDER BY date DESC, invoiceid DESC LIMIT 1");
		if(!$pastBalanceDue) $pastBalanceDue = 0;
		*/
		$conditions = array();
		if($asOfDate) $conditions[] = "itemdate <= '".date('Y-m-d', strtotime($asOfDate))."'";
		$conditions = $conditions ? "AND ".join(' AND ', $conditions) : '';
		
		if(TRUE || staffOnlyTEST()) {  // published 7/21/2015
			$gubstart = microtime(1);
			$clientTotals = getUninvoicedBillableTotals($clientid, $conditions);
			$gubtotal += microtime(1)-$gubstart;
			$subtotal = $clientTotals['subtotal'];
			$unpaidSubtotal =  $clientTotals['unpaidSubtotal'];
			$asOfDate = $clientTotals['asOfDate'];
		}
		else {  // OLD version -- scales poorly and very slow
			$subtotal = 0;
			$unpaidSubtotal =  0;
			$asOfDate = '1970-01-01';
			$gubstart = microtime(1);
			$billables = getUninvoicedBillables($clientid, $conditions); // Include paid billables for subtotal.  -- AND charge > paid 
			$gubtotal += microtime(1)-$gubstart;
			foreach($billables as $billable) {
				$subtotal += $billable['charge'];
				$unpaidSubtotal += $billable['charge'] - $billable['paid'];
				$asOfDate = date('Y-m-d', max(strtotime($asOfDate), strtotime($billable['itemdate'])));
			}
		}
		//$tax = figureTaxForBillables($billables);
//if($clientid == 320) {echo "unpaidSubtotal [$unpaidSubtotal]  accountBalance [$accountBalance]   tax[$tax]";exit;}
		
	//foreach($billables as $b) echo print_r($b, 1).'<br>';exit;	
		$creditsApplied = 0;
		$gtccslistart = microtime(1);
		$creditsApplied = getTotalClientCreditsSinceLastInvoice($clientid);
		$gtccslitotal += microtime(1)-$gtccslistart;
		
//if(mattOnlyTEST() && $clientid == 1290) echo "unpaidSubtotal [$unpaidSubtotal] + accountBalance [$accountBalance] + tax [$tax]<p>";
//if(mattOnlyTEST() && $clientid == 1290) { return round(100 * ($unpaidSubtotal /*+ $accountBalance*/)) / 100 ;}
		return round(100 * ($unpaidSubtotal + $accountBalance)) / 100 ;
}
