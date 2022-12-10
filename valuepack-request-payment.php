<? // valuepack-request-payment.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "billing-fns.php";
require_once "value-pack-fns.php";


$subject = "Vaue Pack Invoice";

$templateBody = <<<BODY
#LOGO#

Dear #FIRSTNAME#,

We have set up a new package of pre-paid #VISITS# visits for you at the cost of #PRICE#.

Please click the Pay Now button below to pay for this package by credit card.

#PAYNOWBUTTON#

As always, we thank you for your business.

Kind regards,

#BIZNAME#
BODY;


$htmlMessage = true;

$pack = getValuePack($_REQUEST['id']);
$clientid = $pack['clientptr'];
$payNowInfo = array('note'=>"Prepaid {$pack['visits']} visit package", 'amount'=>$pack['price']);

$payNowButton = payNowLink(fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = '$clientid' LIMIT 1"), $payNowInfo);
//KLUDGE
$payNowButton = str_replace('&note', '&amp;note', $payNowButton);

$message = str_replace('#VISITS#', $pack['visits'], $templateBody);
$message = str_replace('#PRICE#', dollarAmount($pack['price']), $message);
$message = str_replace('#PAYNOWBUTTON#', $payNowButton, $message);

include "user-notify.php";
