<? // credit-fns-addendum.php

function ePaymentMessageSubjectAndBody($client, $clientOriginated, $paymentSourceType, $paymentSourceDesc, $amount, $gratuity, $transactionid) {
	$templateLabel = $clientOriginated ? '#STANDARD - Thanks for your Credit Card/Bank Account Payment' 
										: '#STANDARD - Credit Card/Bank Account Charged';
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$templateLabel' LIMIT 1", 1);
	if(!$template) return;
	require_once "gui-fns.php";
	require_once "comm-fns.php";
	require_once "comm-composer-fns.php";
	
	$result['subject'] = str_replace('#PAYMENTTYPE#', $paymentSourceType, $template['subject']);

	$totalDollars = dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
	if($gratuity) 
		$gratuityInclusion = 
			", which includes the ".dollarAmount($gratuity, $cents=true, $nullRepresentation='', $nbsp=' ')
			." gratuity";
	$result['body'] = preprocessMessage($template['body'], $client);
	$result['body'] = str_replace('#PAYMENTTYPE#', $paymentSourceType, $result['body']);
	$result['body'] = str_replace('#PAYMENTSOURCE#', $paymentSourceDesc, $result['body']);
	$result['body'] = str_replace('#PAYMENTAMOUNT#', $totalDollars, $result['body']);
	$result['body'] = str_replace('#GRATUITY#', $gratuityInclusion, $result['body']);
	$result['body'] = str_replace('#TRANSACTIONID#', $transactionid, $result['body']);
	return $result;
}