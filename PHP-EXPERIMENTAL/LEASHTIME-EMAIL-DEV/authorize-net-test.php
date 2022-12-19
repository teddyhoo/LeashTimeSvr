<?php
	/*require_once "log-cc.php";

	$gatewayURL = 'https://post.transactionexpress.com/PostMerchantService.svc/CreditCardSale';

	$postRequest = 'GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IndustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=5713174584';

	$postRequest = escapeCommandLine($postRequest);
	$gatewayURL = escapeCommandLine($gatewayURL);
	$postRequest = '"'.$post.'"';
	$gatewayURL = '"'.$gatewayURL.'"';
			
	$data = shell_exec('python cc-bridge.py "application/x-www-form-urlencode" $gatewayURL $postRequest');

	echo ($data);*/

	$ch = curl_init(); // Initialize curl handle
	curl_setopt($ch, CURLOPT_URL, $gatewayURL); // Set POST URL
	$headers = array();
	$headers[] = "Content-type: application/x-www-form-urlencoded";  // text/plain
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Add http headers to let it know we're sending XML
	curl_setopt($ch, CURLOPT_FAILONERROR, 1); // Fail on errors
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Allow redirects
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return into a variable
	curl_setopt($ch, CURLOPT_PORT, 443); // Set the port number
	curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Times out after 15s
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postRequest); // Add XML directly in POST

	$CURL_SSLVERSION_TLSv1_2 = 6;
	curl_setopt ($ch, CURLOPT_SSLVERSION, $CURL_SSLVERSION_TLSv1_2);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);


	// This should be unset in production use. With it on, it forces the ssl cert to be valid
	// before sending info.
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	if (!($data = curl_exec($ch))) {
		logLongError('CURL ERROR: '.curl_error($ch));
		//print  "<hr>curl error =>" .curl_error($ch) ."\n";
		//throw New Exception(" CURL ERROR :" . curl_error($ch));
	}
	//echo "<hr>[[".print_r($data,1)."]]";
	curl_close($ch);
	return $data;

?>