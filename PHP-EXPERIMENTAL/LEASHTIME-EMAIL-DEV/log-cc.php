<?php
	function requestWrite($str) {
		$strm = fopen('./cctest/cc.htm','a');
		fwrite($strm,$str);
		fclose($strm);
	}

	function logTransactionAttemp($transaction_type, $gateway, $requestMade) {
		$strm = fopen('./cctest/cc.htm','a');
		$recordWrite = $gateway . '--> ' . $transaction_type . ' --> ' . $requestMade . '\n';
		fwrite($strm, $recordWrite);
		fclose($strm);
	}

	function checkPasswordChar($rChar) {
		$request = $rChar;
		$regex_chars = array(
			'!' =>  '&\#33\;', 
			'$' => '&\#36\;', 
			'#' => '&\#35\;',
			';' => '&\#59\;',
		);
		foreach ($regex_chars as $key => $value) {
			if ($key == $rChar) {
				return $value;
			}
		}
		return $rChar;	
	}

	function escapeCommandLine($request) {
		$request_array = str_split($request, 1);
		$new_request = '';
		for ($i = 0; $i < count($request_array); $i++) {
			$newChar  = checkPasswordChar($request_array[$i]);
			$new_request .= $newChar;
		}
		return $new_request;
	}


/*
ResponseCode=00&tranNr=004441059122&PostDate=2022-11-17T10:15:08.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=F2FE40&AVSCode=Y&CVV2Response=M&CAVVResultCode=
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py Content-type:application/x-www-form-urlencocded CreditCardSale "a=b&c=d&e=f"
ARGV 1: Content-type:application/x-www-form-urlencocded
ARGV 2: CreditCardSale
ARGV 3: a=b&c=d&e=f
Traceback (most recent call last):
  File "cc-bridge.py", line 74, in <module>
    processTransaction(msgdic, content_type, transaction_type)
  File "cc-bridge.py", line 62, in processTransaction
    handleResponse("ResponseCode=00&tranNr=004441059122&PostDate=2022-11-17T10:15:08.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=F2FE40&AVSCode=Y&CVV2Response=M&CAVVResultCode=")
  File "cc-bridge.py", line 37, in handleResponse
    k,v = split(detail)
NameError: name 'split' is not defined
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py Content-type:application/x-www-form-urlencocded CreditCardSale "a=b&c=d&e=f"
ARGV 1: Content-type:application/x-www-form-urlencocded
ARGV 2: CreditCardSale
ARGV 3: a=b&c=d&e=f
Traceback (most recent call last):
  File "cc-bridge.py", line 74, in <module>
    processTransaction(msgdic, content_type, transaction_type)
  File "cc-bridge.py", line 62, in processTransaction
    handleResponse("ResponseCode=00&tranNr=004441059122&PostDate=2022-11-17T10:15:08.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=F2FE40&AVSCode=Y&CVV2Response=M&CAVVResultCode=")
  File "cc-bridge.py", line 37, in handleResponse
    k,v = detail.split()
ValueError: not enough values to unpack (expected 2, got 1)
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py Content-type:application/x-www-form-urlencocded CreditCardSale "a=b&c=d&e=f"
ARGV 1: Content-type:application/x-www-form-urlencocded
ARGV 2: CreditCardSale
ARGV 3: a=b&c=d&e=f
ResponseCode=00
tranNr=004441059122
PostDate=2022-11-17T10:15:08.000
Amount=000000000100
AmtDueRemaining=0
CardBalance=
Auth=F2FE40
AVSCode=Y
CVV2Response=M
CAVVResultCode=
ResponseCode=00&tranNr=004441059122&PostDate=2022-11-17T10:15:08.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=F2FE40&AVSCode=Y&CVV2Response=M&CAVVResultCode=
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py Content-type:application/x-www-form-urlencocded CreditCardSale "a=b&c=d&e=f"
ARGV 1: Content-type:application/x-www-form-urlencocded
ARGV 2: CreditCardSale
ARGV 3: a=b&c=d&e=f
ResponseCode 00
tranNr 004441059122
PostDate 2022-11-17T10:15:08.000
Amount 000000000100
AmtDueRemaining 0
CardBalance 
Auth F2FE40
AVSCode Y
CVV2Response M
CAVVResultCode 
ResponseCode=00&tranNr=004441059122&PostDate=2022-11-17T10:15:08.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=F2FE40&AVSCode=Y&CVV2Response=M&CAVVResultCode=
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py Content-type:application/x-www-form-urlencocded CreditCardSale "a=b&c=d&e=f"
  File "cc-bridge.py", line 42
    if k == 'ResponseCode' && v == '00':
                            ^
SyntaxError: invalid syntax
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py Content-type:application/x-www-form-urlencocded CreditCardSale "a=b&c=d&e=f"
ARGV 1: Content-type:application/x-www-form-urlencocded
ARGV 2: CreditCardSale
ARGV 3: a=b&c=d&e=f
SUCCESS: ResponseCode=00&tranNr=004441059122&PostDate=2022-11-17T10:15:08.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=F2FE40&AVSCode=Y&CVV2Response=M&CAVVResultCode=

edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardSale" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardSale
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
"ResponseCode=00&tranNr=004441203932&PostDate=2022-11-17T11:06:47.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=9F19DA&AVSCode=Y&CVV2Response=M&CAVVResultCode="
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardSale" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardSale
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
finished request with response: "ResponseCode=00&tranNr=004441208232&PostDate=2022-11-17T11:08:29.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=796E40&AVSCode=Y&CVV2Response=M&CAVVResultCode="
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
"ResponseCode=00&tranNr=004441210002&PostDate=2022-11-17T11:09:14.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=B3A338&AVSCode=Y&CVV2Response=M&CAVVResultCode="
finished request with response: "ResponseCode=00&tranNr=004441210002&PostDate=2022-11-17T11:09:14.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=B3A338&AVSCode=Y&CVV2Response=M&CAVVResultCode="
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
"ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
finished request with response: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
finished request with response: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
HANDLE RESPONSE: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
HANDLE RESPONSE: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
Traceback (most recent call last):
  File "cc-bridge.py", line 85, in <module>
    processTransaction(msgdic, content_type, transaction_type)
  File "cc-bridge.py", line 65, in processTransaction
    handleResponse(transact.text)
  File "cc-bridge.py", line 46, in handleResponse
    response_details = transact_response.text.split('&')
AttributeError: 'str' object has no attribute 'text'
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
HANDLE RESPONSE: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
"ErrorCode=51332

Name=Validation+Error

ErrorMessage=Validation+Error+Fault"

edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
HANDLE RESPONSE: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
HANDLE RESPONSE: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
HANDLE RESPONSE: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
"ErrorCode
Name
ErrorMessage
edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"

ARGV 1: application/x-www-form-urlencoded
ARGV 2: CreditCardRefund
ARGV 3: GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584
HANDLE RESPONSE: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"
FAIL
FAIL: "ErrorCode=51332&Name=Validation+Error&ErrorMessage=Validation+Error+Fault"

edwardhooban@Edwards-MacBook-Pro LEASHTIME-EMAIL-DEV % 
*/
	/*
		ResponseCode=00&
		tranNr=004438939262&
		PostDate=2022-11-16T10:54:39.000&
		Amount=000000000100&
		AmtDueRemaining=0&
		CardBalance=&
		Auth=C0DCB2&
		AVSCode=Y&
		CVV2Response=M&
		CAVVResultCode="
		<Response [200]>

	*/
?>


