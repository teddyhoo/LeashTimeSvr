<? // transfirst-txp-gateway-class.php
/*
Transaction Express (TXP) gateway for ACH.
$auth structure:
$auth = array('x_login' =>lt_encrypt('7777778612'), 'x_tran_key'=>lt_encrypt('P4KD8XS3D8Y7LNXL')); // 1111222233334444

x_login - used by TXP
x_aux_key - used by TXP


*/
// test trans key: dEJTb9M8Sh5ueD93KqJ433439jUua86v

/* TXP CERT TEST CREDS
Gateway ID: 7777778137

RegKey: QSS7BX85BANQ5B8A

Hosted Key: 0eca325d-508d-42d0-8d75-89d5a05b23cf

 

Cert Log-In Credentials

https://vt.cert.transactionexpress.com
User ID: 7777778137_ADMIN
Temp Password: zpje0PS$
*/

/* Curl lib

curl 7.64.1 (x86_64-apple-darwin19.0) 
libcurl/7.64.1 (SecureTransport) 
LibreSSL/2.8.3 
zlib/1.2.11 
nghttp2/1.39.2
Release-Date: 2019-03-27
Protocols: 
dict file ftp ftps gopher http https imap imaps ldap ldaps pop3 pop3s rtsp smb smbs smtp smtps telnet tftp 
Features: AsynchDNS GSS-API HTTP2 HTTPS-proxy IPv6 Kerberos Largefile libz MultiSSL NTLM NTLM_WB SPNEGO SSL UnixSockets

curl 7.19.7 (x86_64-redhat-linux-gnu) 
libcurl/7.19.7 
NSS/3.18 
Basic ECC 
zlib/1.2.3 
libidn/1.18 
libssh2/1.4.2
Protocols: tftp ftp telnet dict ldap ldaps http file https ftps scp sftp 
Features: GSS-Negotiate IDN IPv6 Largefile NTLM SSL libz 
*/

require_once "encryption.php";
require_once "abstract-merchant-gateway.php";

class TransFirstTXPGateway extends AbstractMerchantGateway
{
        // property declaration

    private $lastCCErrorId;
		
    public $ach_enabled = true; 
    private $TXPGatewayURL = "https://post.transactionexpress.com/PostMerchantService.svc/";
    private $TXPTestGatewayURL = "https://post.cert.transactionexpress.com/PostMerchantService.svc/"; // ??? "Certification Post URL"
		private $txpACHResponseFields = array();
		private $txpCCResponseFields = array();
    
		function __construct() {
			require_once "log-cc.php";
			//requestWrite("CLASS TransFirstTXPGateway");
			$this->txpACHResponseFields = $this->getTXPACHResponseCodes();
			$this->txpCCResponseFields = $this->getTXPCCResponseCodes();
//bagText("CONSTRUCT! ".print_r($this->txpCCResponseFields,1));			

		}
 
 	function ccValidationTests() {
 		// return javascript validation tests specific to this gateway
 		return <<<JS
 		var nameTest = '';
 		var fullname = ""+document.getElementById('x_first_name').value+' '+document.getElementById('x_last_name').value;
 		if(fullname.length > 61) nameTest = 'Name on card may not be any longer than 61 characters.';
JS;
 	}
 	function ccValidationExtraArgs() {
 		// return javascript extra validation arguments for  specific to this gateway
 		// must start with a comma
 		return <<<JS2
 		,
 		nameTest, '', 'MESSAGE',
 		'x_address', '50', 'MAXLENGTH',
 		'x_city', '40', 'MAXLENGTH',
 		'x_state', '2', 'MAXLENGTH',
 		'x_state', '2', 'MINLENGTH',
 		'x_zip', '9', 'MAXLENGTH',
 		'x_phone', '15', 'MAXLENGTH',
 		'x_phone', 'FULL_US', 'PHONE',
JS2;
 	}
	function supportsACH() {
		return true;
	}
	function necessaryMerchantInfoKeys() {
		return array(
					'x_login',
					'x_aux_key');
	}
    function merchantInfoLabels() {
		 return array(
		 				'x_login'=>'Gateway ID', 
		 				'x_aux_key'=>'Registration Key',
		 				'x_tran_key'=>'');
		}
        function supportsRefund($ccOrACH) {
		 return $ccOrACH == 'cc';  // no refund for ACH, only void
		}
		// $cc must be null for adHoc sales
		function executeTransaction($auth, $cc, $transactionType, $x_amount, $otherData=null) {
			// $cc = paymentSource (cc or ach info)  ach: acctnum and abacode are both decrypted at this point
			global $ccTestMode, $ccDebug, $duplicateWindow;

			$transactionTypes = 
				array('ACH'=>array('SALE'=>'ACHSale','VOID'=>'ACHRefundOrVoid'),
							'CC'=>array('SALE'=>'CreditCardSale','VOID'=>'CreditCardVoid' ,'CREDIT'=>'CreditCardRefund', 'VALIDATE'=>''));

			$transactionSource = $cc['acctnum'] ? 'ACH' : 'CC';

			if(!$duplicateWindow && $transactionType == 'VALIDATE') $duplicateWindow = 0; // minimum
			$duplicateWindow = (string)max($duplicateWindow, 0);
			$specificTransaction = $transactionTypes[$transactionSource][$transactionType];
			if($specificTransaction == 'ACHSale') 
				$postRequest = $this->createTXPACHSalePostRequest($auth, $cc, $x_amount);
			else if($specificTransaction == 'ACHRefundOrVoid')  
				$postRequest = $this->createTXPACHVoidPostRequest($auth, $otherData['transactionId']);
			else if($specificTransaction == 'CreditCardSale') {
				$postRequest = $this->createTXPCCSalePostRequest($auth, $cc, $x_amount);
				global $db;
				if($db == 'pamperedpetspa' || $db == 'leashtimecustomers') {
					$maskedRequest = $cc;
					$maskedRequest['x_card_num'] = '<CARD>';
					$maskedRequest['x_card_code'] = '<CARDCODE>';
					$maskedRequest = $this->createTXPCCSalePostRequest($auth, $maskedRequest, $x_amount);
					logChange(9999, 'maskedtsysrequest', 'T', $maskedRequest);
				}
			}
			else if($specificTransaction == 'CreditCardVoid') 
				$postRequest = $this->createTXPCCVoidOrRefundPostRequest($auth, $cc, $x_amount, $specificTransaction, $otherData['transactionId']);
			else if($specificTransaction == 'CreditCardRefund') 
				$postRequest = $this->createTXPCCVoidOrRefundPostRequest($auth, $cc, $x_amount, $specificTransaction, $otherData['transactionId']);
			$TXPTestMode = dbTEST('dogslife');

			$gatewayURL = $TXPTestMode ? $this->TXPTestGatewayURL : $this->TXPGatewayURL;
			$gatewayURL .= $specificTransaction;
			if($result = $this->sendPOSTviaCurl($postRequest, $gatewayURL)) {
				if(strpos($result, '"') === 0) $result = substr($result, 1);
				if(strrpos($result, '"') == strlen($result)-1) $result = substr($result, 0, strlen($result)-1);
			}
			if($result == "ErrorMessage=ExceptionOccurredInWebExpress") 
				logLongError("TXP $transactionSource Error ($result) following post: ($postRequest)");
			$response = $this->resultAsResponseArray($result);
			if($ccDebug) {
				echo "<font color=green>"; foreach($params as $p) echo "$p<br>"; echo "</font><p>";
				echo "<font color=blue>".print_r($result, 1)."</font><p>";
				if($response) echo "<p>".$this->labeledResponseHTML($result)."<p>";
			}
			$responseCodeFound = array_key_exists('ResponseCode', $response);
			if(!$response) {
				$final = array('FAILURE'=>'No response code');
				//if(ccDEVTestMode()) $final['FAILURE'] .= ' ['.$gatewayURL . "?" . join('&', $params).']';
				return $final;
			}
			else if($responseCodeFound && $response['ResponseCode'] == '00' && trim("{$response['tranNr']}")) 
				return $response['tranNr'];
			if(TRUE) { // there was an error
				$sourcetable = $transactionSource == 'ACH' ? 'tblecheckacct' : 'tblcreditcard';
				$sourceid = $cc['acctid'] ? $cc['acctid'] : $cc['ccid'];
				$this->logCCTransactionError($cc['clientptr'], $sourcetable, $sourceid, $result);
				return $response;  
			}
		}
		function createPostRequest($pairs) { // TXP
			foreach($pairs as $k=>$v) $chunks[] = "$k=$v";
			return join("&", $chunks); // "\n&"
		}
		function createTXPCCSalePostRequest($auth, $account, $amount) { // TXP
			$formattedAmount = number_format($amount * 100, 0, $dec_point = "." , $thousands_sep = "" );
			$pairs = array(
				'GatewayID' => lt_decrypt($auth['x_login']),
				'RegKey' => lt_decrypt($auth['x_aux_key']),
				'IndustryCode' => 2,
				'AccountNumber' => $account['x_card_num'],
				'CVV2' => $account['x_card_code'],
				'ExpirationDate' => date('ym', strtotime($account['x_exp_date'])),
				'Amount' => $formattedAmount, 
				'FullName' => "{$account['x_first_name']} {$account['x_last_name']}",
				'Address1' => $account['x_address'],
				'City' => $account['x_city'],
				'State' => strtoupper(''.$account['x_state']),
				'Zip' => $account['x_zip'],
				'PhoneNumber' => $account['x_phone'],
			);
			if($account['x_address']) $pairs['Address1'] = $account['x_address'];
			if($account['x_city']) $pairs['City'] = $account['x_city'];
			if($account['x_zip']) $pairs['Zip'] = $account['x_zip'];
			if($account['x_phone']) $pairs['PhoneNumber'] = $account['x_phone'];
			return $this->createPostRequest($pairs);
		}
		function createTXPCCVoidOrRefundPostRequest($auth, $cc, $amount, $specificTransaction, $transactionId) { // TXP
			// specificTransaction = CreditCardVoid/CreditCardRefund
			// refundEPayment() adds 'email' and 'cardcompany' (guessed) to $cc
			// guessed is more reliable than client-entered, since clients might enter "MC" or "Amex"
			$pairs = array(
				'GatewayID' => lt_decrypt($auth['x_login']),
				'RegKey' => lt_decrypt($auth['x_aux_key']),
				'tranNr' => $transactionId);
			if($cc['email']) $pairs['Email'] = $cc['email'];
			if($specificTransaction == 'CreditCardRefund') {
				$formattedAmount = number_format($amount * 100, 0, $dec_point = "." , $thousands_sep = "" );
				$pairs['Amount'] = $formattedAmount;
			}
			else if($specificTransaction == 'CreditCardVoid') {
				/*
				Indicates if transaction is being voided because fraud is suspected.
				Valid values: Y / N
				Condition:
				- Mandatory for MasterCard transactions for which the transaction to be voided 
					was card-not-present (i.e., industry code of original sale = 0 or 2).
				- Value should NOT be set for card-present MasterCard transactions.
				- Value should NOT be set for card types other than MasterCard.
				
				NOTE: 
					industry code of all our sales is '2'
					at present LeashTime does not ask manager WHY she is voiding a payment, so we answer 'N'
				*/
				if($cc['cardcompany'] == 'MasterCard') $pairs['SuspectFraud'] = 'N';
			}
			//foreach($pairs as $k=>$v) $show[$k]="[$v]"; echo print_r($show,1).'<hr>';
			return $this->createPostRequest($pairs);
		}
		function createTXPACHVoidPostRequest($auth, $transactionId) { // TXP
			$pairs = array(
				'GatewayID' => lt_decrypt($auth['x_login']),
				'RegKey' => lt_decrypt($auth['x_aux_key']),
				'tranNr' => $transactionId);
			return $this->createPostRequest($pairs);
		}
		function createTXPACHSalePostRequest($auth, $account, $amount) { // TXP
			$formattedAmount = number_format($amount * 100, 0, $dec_point = "." , $thousands_sep = "" );
			$pairs = array(
				'GatewayID' => lt_decrypt($auth['x_login']),
				'RegKey' => lt_decrypt($auth['x_aux_key']),
				'SecCode' => 1,
				'AccountNumber' => $account['acctnum'],
				'RoutingNumber' => $account['abacode'],
				'Amount' => $formattedAmount, // translated into 'minor denomination'
				//'CheckNumber' => ,???
				//'Descriptor' => ,
				// 'CustRefID' => , clientid?
				'FullName' => $account['acctname'],
				//'Address1' => ,
				//'Address2' => ,
				//'City' => ,
				//'State' => ,
				//'Zip' => ,
				//'PhoneNumber' => ,
				//'DOB' => ,
				//'SSN' => ,
				//'Email' => ,
				);
				if($account['x_address']) $pairs['Address1'] = $account['x_address'];
				if($account['x_city']) $pairs['City'] = $account['x_city'];
				if($account['x_state']) $pairs['State'] = strtoupper(''.$account['x_state']);
				if($account['x_zip']) $pairs['Zip'] = $account['x_zip'];
				if($account['x_phone']) $pairs['PhoneNumber'] = $account['x_phone'];
				$pairs['FullName'] = $account['acctname'];
				if($account['acctnum']) {
					$pairs['AccountNumber'] = $account['acctnum'];
					$pairs['RoutingNumber'] = $account['abacode'];
				}
				else {
					$pairs['AccountNumber'] = $account['x_card_num'];
					$pairs['CVV2'] = $account['x_card_code'];
					$pairs['ExpirationDate'] = date('YYMM', strtotime($account['x_exp_date']));
				}
			//print_r($pairs); exit;		
			return $this->createPostRequest($pairs);
		}
		function sendPOSTviaCurl($postRequest,$gatewayURL) { // TXP
		 	// helper function demonstrating how to send the xml with curl
			//$certificate = '../../security/cacert.pem';
			//$ch = curl_init(); // Initialize curl handle
			/*
				curl_setopt($ch, CURLOPT_URL, $gatewayURL); // Set POST URL
				$headers = array();
				$headers[] = "Content-type: application/x-www-form-urlencoded";  // text/plain
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Add http headers to let it know we're sending XML
				curl_setopt($ch, CURLOPT_FAILONERROR, 1); // Fail on errors
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Allow redirects
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return into a variable
				curl_setopt($ch, CURLOPT_PORT, 443); // Set the port number
				curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Times out after 15s
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postRequest); // Add XML directly in POST
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($ch, CURLOPT_CAINFO, $certificate);
				curl_setopt($ch, CURLOPT_CAPATH, $certificate);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
				$CURL_SSLVERSION_TLSv1_2 = 6;
				curl_setopt ($ch, CURLOPT_SSLVERSION, $CURL_SSLVERSION_TLSv1_2);

				if (!($data = curl_exec($ch))) {
					logLongError('CURL ERROR: '.curl_error($ch));
					print  "<hr>curl error =>" .curl_error($ch) ."\n";
					throw New Exception(" CURL ERROR :" . curl_error($ch));
					requestWrite("\n----------\nPOST REQUEST:$postRequest\nCURL ERROR: ".curl_error($ch)."\n");
				}
				curl_close($ch);
			*/
			require_once "log-cc.php";
			$postRequest = '"'.$postRequest.'"';
			$data  = shell_exec("python3 cc-bridge.py application/x-www-form-urlencoded $gatewayURL $postRequest TSYS");
			requestWrite("TSYS: $gatewayURL\n$postRequest\n\nRESPONSE: $data");
			return $data;
			/* 
				Example response: 
				ResponseCode=0&tranNr=1237753&PostDate=2011-12-07T01:22:40.000&Amount=995&Message=Transaction+Processed

				ResponseCode (an2)
					Result of the transaction request based on the �Severity� response element returned by Check Gateway.
					Valid values:
					? 0 = Accepted.
					? All other codes = Declined.
				tranNr (n..19)
					Transaction number.
				PostDate (dateTime)
					Date and time when the transaction is posted.
				Amount(n..12)
					Approved/declined amount of the transaction.
				Message (ans1024)
					User friendly outcome of the request; in the case of declined transactions, this element will provide 
					information about the error.
			*/
		}
		function ccErrorLogMessage($result, $amount=null) {  // $amount is ignored here
			return	$this->ccErrorLogMessageTXP($result, $amount);
		}
		
		function ccErrorLogMessageTXP($result, $amount=null) {  // $amount is ignored here
			// called after a transaction is attempted
			if(!is_array($result)) {
				//ResponseCode=0&tranNr=1237753&PostDate=2011-12-07T01:22:40.000&Amount=995&Message=Transaction+Processed
				foreach(explode('&', $result) as $part) {
					$part = explode('=', $part);
					$response[$part[0]] = $part[1];
				}
			}
			else $response = $result;
			if($response['FAILURE']) return 'Error-'.$response['FAILURE'];
			//if(mattOnlyTEST()) echo print_r($response,1)."<hr>";
			$errmessage = urldecode($response['ErrorMessage'] ? $response['ErrorMessage'] : $response['Message']);
			$primaryMessageArray = array_key_exists('CVV2Response', (array)$response) 
				? @$this->txpCCResponseFields
				: @$this->txpACHResponseFields;

			$msg = $primaryMessageArray[$response['ResponseCode']].'-'.$this->TXPErrorMessagePayload($errmessage);
			return $msg."|Amount:{$response['Amount']}|Trans:{$response['tranNr']}|Gate:TransFirstV1|ErrorID:".$this->lastCCErrorId;
		}
		function ccLastMessage($result) {
			// called after a transaction is attempted AND in the Billing tab Electronic Transactions report cc-transaction-history.php
			return $this->ccLastMessageTXP($result);
		}
		function ccLastMessageTXP($result) {
			if(!is_array($result)) {
				//ResponseCode=0&tranNr=1237753&PostDate=2011-12-07T01:22:40.000&Amount=995&Message=Transaction+Processed
				foreach(explode('&', $result) as $part) {
					$part = explode('=', $part);
					$response[$part[0]] = $part[1];
				}
			}
			else $response = $result;
			$errmessage = urldecode($response['ErrorMessage'] ? $response['ErrorMessage'] : $response['Message']);
			if($errmessage) $message = $this->TXPErrorMessagePayload($errmessage);
			else if($response['ResponseCode'] != '00') {
				$primaryMessageArray = array_key_exists('CVV2Response', (array)$response) 
					? @$this->txpCCResponseFields
					: @$this->txpACHResponseFields;
				
				if(array_key_exists('CVV2Response', (array)$response))
					$message = $this->txpCCResponseFields[$response['ResponseCode']];
				else $message = $primaryMessageArray[$response['ResponseCode']];
				if($response) $message .= ' '.$errmessage;
			}
			return $message;
		}
		function TXPErrorMessagePayload($errorMessage) {
			if(!$errorMessage) return "No error details.";			
			$rawmatches = "'bankRtNr'+is+not+valid|Bank Routing Number is not valid.||'acctNr'+is+not+valid|Account Number is not valid.||'reqAmt'+is+not+valid.|Amount is not valid.";
			foreach(explode('||', $rawmatches) as $match) {
				$match = explode('|', $match);
				$matches[$match[0]] = $match[1];
			}
			foreach($matches as $pat => $msg)
				if(strpos($errorMessage, $pat)) return $msg;
				
			if(strpos($errorMessage, ':') !== FALSE) {
				$parts = explode(':', $errorMessage);
				if($parts[1]) return urldecode($parts[1]);
			}
			if($errorMessage) return $errorMessage;
		}
		function ccTransactionId($result) {
			return ccTransactionIdTXP($result);
		}
		function ccTransactionIdTXP($result) {
			foreach(explode('&', $result) as $part) {
				$part = explode('=', $part);
				$response[$part[0]] = $part[1];
			}
			return $response['tranNr'];
		}
		function logCCTransactionError($clientid, $sourcetable, $ccid, $rawResponse) {
			$response = $this->resultAsResponseArray($rawResponse);
			insertTable('tblcreditcarderror', 
								array('transactionid' => ($response['tranNr'] ? $response['tranNr'] : '0'), 
									'time' => date('Y-m-d H:i:s'),
									'clientptr' => $clientid,
									'ccptr' => $ccid,
									'sourcetable' => $sourcetable,
									'response' => ($rawResponse ? $rawResponse : sqlVal("''"))), 1
								);
			$this->lastCCErrorId = mysql_insert_id();
		}
		function resultAsResponseArray($resultString) { // TXP
			//"ResponseCode=0&tranNr=1237753&PostDate=2011-12-07T01:22:40.000&Amount=995&Message=Transaction+Processed"
			// if enclosed in dubbaquotes, strip it
			//echo '['.print_r($resultString,1).']<hr>';
			//echo join('<br>', explode('&', $resultString)).'<hr>';
			foreach(explode('&', $resultString) as $part) {
				$part = explode('=', $part);
			//echo print_r($part,1).'<hr>';
				$response[$part[0]] = $part[1];
			}
			return $response;
		}

		function labeledResponseHTML($result) { // TXP
			return $result;
		}

/* RESPONSE: 
SimpleXMLElement Object ( 
	[result] => 1 
	[result-text] => SUCCESS 
	[transaction-id] => 1424734727 
	[result-code] => 100 
	[authorization-code] => 123456 
	[avs-result] => N 
	[action-type] => sale 
	[amount] => 50 
	[industry] => ecommerce 
	[processor-id] => ccprocessora 
	[currency] => USD 
	[customer-id] => 1745460407 
	[customer-vault-id] => 1745460407 
	[billing] => SimpleXMLElement Object ( [billing-id] => 548591072 [first-name] => a [last-name] => z [address1] => 820 Follin Lane Vienna 4 [city] => VIENNA [state] => VA [postal] => 22180 [country] => US [phone] => jula 321-590-8369 [company] => Visa [fax] => on [cc-number] => 4xxxxxxxxxxx1111 [cc-exp] => 1217 ) 
	[shipping] => SimpleXMLElement Object ( [shipping-id] => 1047769166 ) ) 
*/		
		

		



		function getTXPCCResponseCodes() {
			static $raw;
			$rawCCResponseFields = <<<RAWRESPONSEFIELDS
00
Approved or completed successfully
01
Refer to card issuer
02
Refer to card issuer, special condition
03
Invalid merchant
04
Pick-up card
05
Do not honor
06
Error
07
Pick-up card, special condition
08
Honor with identification (this is a decline response when a card not present transaction) If you receive an approval in a card not present environment, you will need to void the transaction.
09
Request in progress
10
Approved, partial authorization
11
VIP Approval (this is a decline response for a card not present transaction)
12
Invalid transaction
13
Invalid amount
14
Invalid card number
15
No such issuer
16
Approved, update track 3
17
Customer cancellation
18
Customer dispute
19
Re-enter transaction
20
Invalid response
21
No action taken
22
Suspected malfunction
23
Unacceptable transaction fee
24
File update not supported
25
Unable to locate record
26
Duplicate record
27
File update field edit error
28
File update file locked
29
File update failed
30
Format error
31
Bank not supported
33
Expired card, pick-up
34
Suspected fraud, pick-up
35
Contact acquirer, pick-up
36
Restricted card, pick-up
37
Call acquirer security, pick-up
38
PIN tries exceeded, pick-up
39
No credit account
40
Function not supported
41
Lost card, pick-up
42
No universal account
43
Stolen card, pick-up
44
No investment account
45
Account closed
46
Identification required
47
Identification cross-check required
48
No customer record
49
Reserved for future Realtime use
50
Reserved for future Realtime use
51
Not sufficient funds
52
No checking account
53
No savings account
54
Expired card
55
Incorrect PIN
56
No card record
57
Transaction not permitted to cardholder
58
Transaction not permitted on terminal
59
Suspected fraud
60
Contact acquirer
61
Exceeds withdrawal limit
62
Restricted card
63
Security violation
64
Original amount incorrect
65
Exceeds withdrawal frequency
66
Call acquirer security
67
Hard capture
68
Response received too late
69
Advice received too late (the response from a request was received too late )
70
Reserved for future use
71
Reserved for future Realtime use
72
Reserved for future Realtime use
73
Reserved for future Realtime use
74
Reserved for future Realtime use
75
PIN tries exceeded
76
Reversal: Unable to locate previous message (no match on Retrieval Reference Number)/ Reserved for future Realtime use
77
Previous message located for a repeat or reversal, but repeat or reversal data is inconsistent with original message/ Intervene, bank approval required
78
Invalid/non-existent account -- Decline (MasterCard specific)/ Intervene, bank approval required for partial amount
79
Already reversed (by Switch)/ Reserved for client-specific use (declined)
80
No financial Impact (Reserved for declined debit)/ Reserved for client-specific use (declined)
81
PIN cryptographic error found by the Visa security module during PIN decryption/ Reserved for client-specific use (declined)
82
Incorrect CVV/ Reserved for client-specific use (declined)
83
Unable to verify PIN/ Reserved for client-specific use (declined)
84
Invalid Authorization Life Cycle -- Decline (MasterCard) or Duplicate Transaction Detected (Visa)/ Reserved for client-specific use (declined)
85
No reason to decline a request for Account Number Verification or Address Verification/ Reserved for client-specific use (declined)
86
Cannot verify PIN/ Reserved for client-specific use (declined)
87
Reserved for client-specific use (declined)
88
Reserved for client-specific use (declined)
89
Reserved for client-specific use (declined)
90
Cut-off in progress
91
Issuer or switch inoperative
92
Routing error
93
Violation of law
94
Duplicate Transmission (Integrated Debit and MasterCard)
95
Reconcile error
96
System malfunction
97
Reserved for future Realtime use
98
Exceeds cash limit
99
Reserved for future Realtime use
1106
Reserved for future Realtime use
0A
Reserved for future Realtime use
A0
Reserved for future Realtime use
A1
ATC not incremented
A2
ATC limit exceeded
A3
ATC configuration error
A4
CVR check failure
A5
CVR configuration error
A6
TVR check failure
A7
TVR configuration error
A8 to BZ
Reserved for future Realtime use
B1
Surcharge amount not permitted on Visa cards or EBT Food Stamps/ Reserved for future Realtime use
B2
Surcharge amount not supported by debit network issuer/ Reserved for future Realtime use
C0
Unacceptable PIN
C1
PIN Change failed
C2
PIN Unblock failed
C3 to D0
Reserved for future Realtime use
D1
MAC Error
D2 to E0
Reserved for future Realtime use
E1
Prepay error
E2 to MZ
Reserved for future Realtime use
N1
Network Error within the TXP platform
N0 to ZZ
Reserved for client-specific use (declined) (except N1)
N0
Force STIP/ Reserved for client-specific use (declined)
N3
Cash service not available/ Reserved for client-specific use (declined)
N4
Cash request exceeds Issuer limit/ Reserved for client-specific use (declined)
N5
Ineligible for re-submission/ Reserved for client-specific use (declined)
N7
Decline for CVV2 failure/ Reserved for client-specific use (declined)
N8
Transaction amount exceeds preauthorized approval amount/ Reserved for client-specific use (declined)
P0
Approved; PVID code is missing, invalid, or has expired
P1
Declined; PVID code is missing, invalid, or has expired/ Reserved for client-specific use (declined)
P2
Invalid biller Information/ Reserved for client-specific use (declined)/ Reserved for client-specific use (declined)
R0
The transaction was declined or returned, because the cardholder requested that payment of a specific recurring or installment payment transaction be stopped/ Reserved for client-specific use (declined)
R1
The transaction was declined or returned, because the cardholder requested that payment of all recurring or installment payment transactions for a specific merchant account be stopped/ Reserved for client-specific use (declined)
Q1
Card Authentication failed/ Reserved for client-specific use (declined)
XA
Forward to Issuer/ Reserved for client-specific use (declined)
XD
Forward to Issuer/ Reserved for client-specific use (declined)
RAWRESPONSEFIELDS;
			foreach(explode("\n", $rawCCResponseFields) as $line) {
				if(!$k) $k = trim($line);
				else {
					$responseFields[$k] = trim($line);
					$k = null;
				}
			}
			return $responseFields;
		}


		function getTXPACHResponseCodes() {
			static $raw;
			$rawACHResponseFields = <<<RAWRESPONSEFIELDS
00
Credit Processed. 
06 
Bank routing number and account number are required. 
06 
Bank routing number validation negative (ABA). 
06 
Bank routing number validation negative (district). 06 Connection reset 06 Connection refused 06 Consumer name cannot be more than 50 characters. 06 Consumer verification negative. 06 Failed to process message: Missing Login Id 06 Internal connection fatal error. 06 Invalid Status Code 500 06 javax.xml.bind.UnmarshalException: unexpected element (uri:"", local:"Exception"). Expected elements are <{}Notes>,<{}Message> 06 MerchantLogin_Auth returned 0 tables instead of 1. (F=1) 06 Only Debit transactions may be refunded. 06 peer not authenticated 06 Phone number area/exchange code is invalid. 06 Read timed out 06 SEC Code is not configured for submission on this account. 06 There is already an open DataReader associated with this Command which must be closed first. 06 Threshold exceeded: Accounts Per Consumer Max 06 Threshold exceeded: Single Transaction Amount Max 06 Transaction (Process ID xx) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction. 06 UserName not found in MerchantLogin 06 You don't have access to submit an ACH Refund.
06 
Unable to Complete Transaction(The target server failed to respond) 
12 
Bank routing number and account number are required. 
12 
Bank routing number validation negative (ABA). 
12
Bank routing number validation negative (district). 
12 
Connection reset 
12 
Connection refused 
12 
Consumer name cannot be more than 50 characters. 
12 
Consumer verification negative. 
12 
Failed to process message: Missing Login Id 
12 
Internal connection fatal error. 
12 
Invalid Status Code 500 
12 
javax.xml.bind.UnmarshalException: unexpected element (uri:"", local:"Exception"). Expected elements are <{}Notes>,<{}Message> 
12 
MerchantLogin_Auth returned 0 tables instead of 1. (F=1) 
12 
Only Debit transactions may be refunded. 
12 
peer not authenticated 
12 
Phone number area/exchange code is invalid. 
12 
Read timed out 
12 
SEC Code is not configured for submission on this account. 
12 
There is already an open DataReader associated with this Command which must be closed first. 
12 
Threshold exceeded: Accounts Per Consumer Max 12 Threshold exceeded: Single Transaction Amount Max 
12 
Transaction (Process ID xx) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction. 
12 
UserName not found in MerchantLogin 
12 
You don't have access to submit an ACH Refund. 
12 
Unable to Complete Transaction(The target server failed to respond)
R01
Insufficient Funds (NSF)
R02
Account Closed
R03
No Account / Unable to Locate Account
R04
Invalid Account Number
R05
Unauthorized Debit to Consumer Account Using Corporate SEC Code
R06
Returned per ODFI�s Request
R07
Authorization Revoked by Consumer
R08
Payment Stopped
R09
Uncollected Funds
R10
Customer Advises Not Authorized, Notice Not Provided, Improper Source Document, or Amount of Entry Not Accurately Obtained from Source Document
R11
Check Truncation Entry Return
R12
Account Sold to Another DFI
R13
Invalid ACH Routing Number (formerly: RDFI Not Qualified to Participate)
R14
Representative Payee Deceased or Unable to Continue in that Capacity
R15
Beneficiary or Account Holder (Other Than a Representative Payee) Deceased
R16
Account Frozen
R17
File Record Edit Criteria
R18
Improper Effective Entry Date
R19
Amount Field Error
R20
Non-Transaction Account
R21
Invalid Company Identification
R22
Invalid Individual ID Number
R23
Credit Entry Refused by Receiver
R24
Duplicate Entry
R25
Addenda Error
R26
Mandatory Field Error
R27
Trace Number Error
R28
Routing Number Check Digit Error
R29
Corporate Customer Advises Not Authorized
R30
RDFI Not Participant in Check Truncation Program
R31
Permissible Return Entry
R32
RDFI Non-Settlement
R33
Return of XCK Entry
R34
Limited Participation DFI
R35
Return of Improper Debit Entry
R36
Return of Improper Credit Entry
R37
Source Document Presented for Payment
R38
Stop Payment on Source Document
R39
Improper Source Document
R40
Return of ENR Entry by Federal Government Agency (ENR only)
R41
Invalid Transaction Code (ENR only)
R42
Routing Number / Check Digit Error (ERN only)
R43
Invalid DFI Account Number (ENR only)
R44
Invalid Individual ID Number / Identification Number (ENR only)
R45
Invalid Individual Name / Company Name (ENR only)
R46
Invalid Representative Payee Indicator (ENR only)
R47
Duplicate Enrollment (ENR only)
R50
State Law Affecting RCK Acceptance
R51
Item is Ineligible, Notice Not Provided, Signature Not Genuine, Item Altered, or Amount of Entry Not Accurately Obtained from Item
R52
Stop Payment on Item
R53
Item and ACH Entry Presented for Payment
R61
Misrouted Return
R62
Incorrect Trace Number
R63
Incorrect Dollar Amount
R64
Incorrect Individual Identification
R65
Incorrect Transaction Code
R66
Incorrect Company Identification
R67
Duplicate Return
R68
Untimely Return
R69
Multiple Errors
R70
Permissible Return Entry Not Accepted
R71
Misrouted Dishonored Return
R72
Untimely Dishonored Return
R73
Timely Original Return
R74
Corrected Return
R75
Original Return Not a Duplicate
R76
No Errors Found
R80
Cross-Border Payment Coding Error
R81
Non-Participant in Cross-Border Program
R82
Invalid Foreign Receiving DFI Identification
R83
Foreign Receiving DFI Unable to Settle
R84
Entry Not Processed by OGO
R99
Check21 1 High Risk: considered Unauthorized by NACHA, and considered the payment processor. 2 Considered a ChargeBack by the payment processor. 3 Dishonored Return code 4 Contested Dishonored / Corrected Return Entry code
B0
Originated
B1
Routing Number Failed Check Digit Validation
B2
Routing Number is Missing
B3
Account Number is Missing
B9
Name is Missing
B10
Name is Invalid
B11
Amount is Missing
B12
Amount is Invalid
B13
Account Type is Missing
B14
Account Type is Invalid
B15
Company Code is Invalid
B20
SEC Code is Missing
B21
Credit Transaction for WEB or TEL SEC Code
B22
SEC Code is Invalid
B23
FH_Template_ID is Missing or Invalid
B51
Dollars Daily Max Threshold Exceeded
B52
Dollars Monthly Max Threshold Exceeded
B53
Transactions Daily Max Threshold Exceeded
B54
Transactions Monthly Max Threshold Exceeded
B55
Dollars Daily per Consumer Max Threshold Exceeded
B61
Duplicate Entry
B63
Company is Suspended
B64
Bank Account Blocked (ChargeBack)
B65
Bank Account Blocked (NOC)
B66
Company is Terminated
B67
Credit Reserve Balance Exceeded
B69
ORAC
B75
Merchant Requested Manual Cancel
B81
Selected for Random Telephone Inquiry
B82
Selected for Random Email Inquiry
B90
MyECheck: address is invalid
B91
MyECheck: RDFI is missing in RoutingNumbers table
B95
Declined on the Web
B96
Consumer Requested Block
B97
RDFI Stopped
B99
Unvalidated
RAWRESPONSEFIELDS;
			foreach(explode("\n", $rawACHResponseFields) as $line) {
				if(!$k) $k = trim($line);
				else {
					$responseFields[$k] = trim($line);
					$k = null;
				}
			}
			return $responseFields;
		}

		function getTXPGeneralErrorCodes() {
			static $raw;
			$rawErrorFields = <<<RAWRESPONSEFIELDS
50000
Undefined error code. Please check the error message.
50001
Database exception.
50002
Unhandled exception.
50003
This is returned if the following is true: No response was received from the upstream entity (i.e., eSocket Server) for a given amount of time. Default is 30 seconds.
50004
No record found.
50005
Too many records found.
50006
Record already exists.
50007
Database access failure.
50008
Authentication failed because of incomplete information (e.g., password not specified).
50009
No record updated.
50010
Access denied.
50011
Schema validation error.
50012
Authentication failed because of wrong information (e.g., wrong password).
50013
System failure.
50014
User is active but locked.
50015
User is inactive.
50016
User is inactive and locked.
50017
Password is expired.
50019
User must change password.
50020
Old password and new password are the same but not expected.
50021
Request is expected but not set.
50022
User is not linked to a merchant or group.
50023
Password or Registration Key failed WSDL validation.
50024
Failed to send email.
50025
User ID and password are set, but Merchant ID is not set.
50026
Active/Active initialization failed.
50027
Encryption/decryption failed.
50028
Invalid Date Format
800002
Given Credentials are not authenticated and/or Access Denied
RAWRESPONSEFIELDS;
			foreach(explode("\n", $rawErrorFields) as $line) {
				if(!$k) $k = trim($line);
				else {
					$responseFields[$k] = trim($line);
					$k = null;
				}
			}
			return $responseFields;
		}
		

}