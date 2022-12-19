<?php
	$post = 'GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&t&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=5713174584';
	//$post = preg_replace('/&/', '\&', $post);
	$post = '"'.$post.'"';
	$request = shell_exec("python3 cc-bridge.py \"application/x-www-form-urlencoded\" \"https://post.transactionexpress.com/PostMerchantService.svc/CreditCardVoid\" $post");

	//$request = shell_exec("python3 cc-bridge.py arg1 arg2 arg3")
	echo ("RESPONSE: " . $request);
?>