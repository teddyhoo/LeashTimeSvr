<?
/* prepayment-get-info.php
*
* Parameters: 
* id - id of client
* firstDay
* lookahead
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "prepayment-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "cc-processing-fns.php";

// Verify login information here
locked('o-');

$id = $_REQUEST['id'];

$firstDayInt = strtotime($_REQUEST['firstDay'] ? $_REQUEST['firstDay'] : date('Y-m-d'));
$firstDayDB = date('Y-m-d', $firstDayInt);

$prepayments = findPrepayments($_REQUEST['firstDay'], $_REQUEST['lookahead'], $id);
$repeatCustomers = !$clientids ? array() : fetchCol0(
		"SELECT DISTINCT correspid 
			FROM tblmessage 
				WHERE correspid = $id AND inbound = 0 AND correstable = 'tblclient' 
					AND subject like '%$prepaidInvoiceTag%'");

if(!$prepayments) {}
$pp = $prepayments[$id];

$cb = "<input type='checkbox' id='client_{$pp['clientptr']}' name='client_{$pp['clientptr']}'>";
if(!$pp) $clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $id LIMIT 1");
$clientname = prepaymentClientLink($pp ? $pp : array('clientptr'=>$id, 'clientname'=>$clientname));
$invoiceby = $pp['invoiceby'] ? $pp['invoiceby'] : $nullChoice;
$invoicecell = echoButton('', 'Send', "viewInvoice($id, \"$invoiceby\", \"{$pp['email']}\")", 'SmallButton', 'SmallButtonDown', 1).
										' '.historyLink($pp['clientptr'], $repeatCustomers);
$prepaymentValue = $pp['prepayment'] ? $pp['prepayment'] : 0;									

/*$creditValue = fetchRow0Col0("SELECT sum(amount-amountused) 
																FROM tblcredit 
																WHERE clientptr = $id");
$prepaymentdollars = $prepaymentValue ? dollars($pp['prepayment']) : 0;
if($creditValue + 1 > $prepaymentValue) 
	$prepaymentdollars .= "<span style='color:green;font-variant:small-caps;font-weight:bold;'> (Paid)</span>";


$creditFlag = $prepaymentValue - $creditValue < 0 ? 'cr' : '';
$netdue = dollarAmount(abs($prepaymentValue - $creditValue)).$creditFlag;
																
$credits = dollarAmount($creditValue);
																*/
// ######################################################																
// availableCredits is built during findPrepayments  
$creditValue = isset($availableCredits[$id]) ? $availableCredits[$id] : 0.0;
																
																
$prepaymentdollars = dollars($prepaymentValue);
if($creditValue > 0 && ($creditValue + 1 > $prepaymentValue)) { //(abs($creditValue + 1 >= $prepaymentValue) )
	$prepaymentdollars .= "<span style='color:green;font-variant:small-caps;font-weight:bold;'> (Paid)</span>";
}
$credits = dollars($creditValue);
$creditFlag = $prepaymentValue - $creditValue < 0 ? 'cr' : '';
$tobecharged = max(0, $prepaymentValue - $creditValue);
$netdue = dollarAmount(abs($prepaymentValue - $creditValue)).$creditFlag;
if($creditFlag) $netdue = "<font color='green'>{$pp['netdue']}</font>";
																
// ######################################################																




$payment = paymentLink($id, 
												number_format(max(0, $prepaymentValue - $creditValue), 2, '.', ','))
												.'&nbsp;&nbsp;'
												.ccStatusDisplayForClientId($id);

// cb,clientname,invoicestatus,prepayment,credits,payment
echo "cb|$cb|clientname|$clientname|invoicestatus|$invoicecell|prepayment|$prepaymentdollars|credits|$credits|netdue|$netdue|payment|$payment";


