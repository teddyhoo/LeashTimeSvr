<?php
	require_once "log-cc.php";

	$gatewayURL = 'https://post.transactionexpress.com/PostMerchantService.svc/CreditCardSale';

	$postRequest = 'GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IndustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=5713174584';

	$postRequest = escapeCommandLine($postRequest);
	$gatewayURL = escapeCommandLine($gatewayURL);
	$postRequest = '"'.$post.'"';
	$gatewayURL = '"'.$gatewayURL.'"';
			
	$data = shell_exec('python cc-bridge.py "application/x-www-form-urlencode" $gatewayURL $postRequest');

	echo ($data);

?>