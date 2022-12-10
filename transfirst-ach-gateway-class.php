<? // transfirst-ach-gateway-class.php
// test login id: 6zz6m5N4Et
// test trans key: 9V9wUv6Yd92t27t5

// doc: 

class TransFirstACHGateway
{
    // property declaration
    private $lastCCErrorId;
    public $ach_enabled = true; 
    private $TXPGatewayURL = "https://post.transactionexpress.com/PostMerchantService.svc/";
    private $TXPTestGatewayURL = "https://post.cert.transactionexpress.com/PostMerchantService.svc/"; // ??? "Certification Post URL"
		private $txpACHResponseFields = array();
		private $txpCCResponseFields = array();
		


	function __construct() {
		$this->txpACHResponseFields = $this->getTXPACHResponseCodes();
	}
 
	function merchantInfoLabels() {
	 return array('x_tran_key'=>'Transaction Key', 'x_login'=>'Transaction Express Login', 'x_aux_key'=>'Transaction Express Password');
	}
 
 
  function executeTransaction($auth, $cc, $transactionType, $x_amount, $otherData=null) {
		return $this->executeTXPTransaction($auth, $cc, $transactionType, $x_amount, $otherData);
	}
	
  function executeTXPTransaction($auth, $cc, $transactionType, $x_amount, $otherData=null) {
		// $cc = paymentSource (cc or ach info)  ach: acctnum and abacode are both decrypted at this point
		global $ccTestMode, $ccDebug, $duplicateWindow;
		$transactionTypes = 
			array('ACH'=>array('CREDIT'=>'CREDIT', 'VOID'=>'ACHRefundOrVoid', 'SALE'=>'ACHSale', 'VALIDATE'=>'AUTH_ONLY'));
		$transactionSource = $cc['acctnum'] ? 'ACH' : 'CC';
		
		//if($ccTestMode && $cc['x_card_num'] == '4222222222222') $x_amount = 1;  // dollar amount is set to the desired response code (1=approved)
		
		if(!$duplicateWindow && $transactionType == 'VALIDATE') $duplicateWindow = 0; // minimum
		$duplicateWindow = (string)max($duplicateWindow, 0);

		$postRequest = $this->createAccountPostRequest($auth, $cc, $x_amount);
if(mattOnlyTEST()) { echo "$postRequest<hr>"; }

		$gatewayURL = $ccTestMode ? $this->TXPTestGatewayURL : $this->TXPGatewayURL;
		$gatewayURL .= $transactionTypes[$transactionSource][$transactionType];
//echo "transactionSource: $transactionSource<hr>transactionType: $transactionType<hr>postRequest: ".print_r($postRequest, 1)."<br>gatewayURL: ".print_r($gatewayURL, 1);
		$result = $this->sendPOSTviaCurl($postRequest, $gatewayURL);
		$response = $this->resultAsResponseArray($result);
		if($ccDebug) {
			echo "<font color=green>"; foreach($params as $p) echo "$p<br>"; echo "</font><p>";
			echo "<font color=blue>".print_r($result, 1)."</font><p>";
			if($response) echo "<p>".$this->labeledResponseHTML($result)."<p>";
		}

		if(!$response) {
			$final = array('FAILURE'=>'No response code');
			//if(ccDEVTestMode()) $final['FAILURE'] .= ' ['.$gatewayURL . "?" . join('&', $params).']';
			return $final;
		}
		else if($response['ResponseCode'] == 0) return $response['tranNr'];
		else {
			$sourcetable = $cc['acctnum'] ? 'tblecheckacct' : 'tblcreditcard';
			$this->logCCTransactionError($cc['clientptr'], $sourcetable, $cc['ccid'], $result);
			return $response;  
		}
		
	}
	
	function resultAsResponseArray($resultString) {
		//ResponseCode=0&tranNr=1237753&PostDate=2011-12-07T01:22:40.000&Amount=995&Message=Transaction+Processed
		foreach(explode('&', $resultString) as $part) {
			$part = explode('=', $part);
			$response[$part[0]] = $part[1];
		}
		return $response;
	}
	
	function labeledResponseHTML($result) {
		return $result;
	}
	
	function logCCTransactionError($clientid, $sourcetable, $ccid, $rawResponse) {
		$response = $this->resultAsResponseArray($rawResponse);
		insertTable('tblcreditcarderror', 
							array('transactionid' => $response['tranNr'], 
								'time' => date('Y-m-d H:i:s'),
								'clientptr' => $clientid,
								'ccptr' => $ccid,
								'sourcetable' => $sourcetable,
								'response' => ($rawResponse ? $rawResponse : sqlVal("''")))
							);
		$this->lastCCErrorId = mysql_insert_id();
	}
	
	function createPostRequest($pairs) {
		foreach($pairs as $k=>$v) $chunks[] = "$k=$v";
		return join("&", $chunks); // "\n&"
	}
	
	function createAccountPostRequest($auth, $account, $amount) {
		$pairs = array(
			'GatewayID' => lt_decrypt($auth['x_login']),
			'RegKey' => lt_decrypt($auth['x_tran_key']),
			'SecCode' => 1,
			'AccountNumber' => $account['acctnum'],
			'RoutingNumber' => $account['abacode'],
			'Amount' => $amount,
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
			if($account['x_state']) $pairs['State'] = $account['x_state'];
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
		return $this->createPostRequest($pairs);
	}
	
	function sendPOSTviaCurl($postRequest,$gatewayURL) {
	 // helper function demonstrating how to send the xml with curl

		$ch = curl_init(); // Initialize curl handle
		curl_setopt($ch, CURLOPT_URL, $gatewayURL); // Set POST URL

		$headers = array();
		$headers[] = "Content-type: application/x-www-form-urlencoded";  // text/plain
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Add http headers to let it know we're sending XML
		curl_setopt($ch, CURLOPT_FAILONERROR, 1); // Fail on errors
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Allow redirects
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return into a variable
		curl_setopt($ch, CURLOPT_PORT, 443); // Set the port number
		curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Times out after 15s
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postRequest); // Add XML directly in POST

		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		$CURL_SSLVERSION_TLSv1_2 = 6;
		curl_setopt ($ch, CURLOPT_SSLVERSION, $CURL_SSLVERSION_TLSv1_2);

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
		
		/* Example response: 
		ResponseCode=0&tranNr=1237753&PostDate=2011-12-07T01:22:40.000&Amount=995&Message=Transaction+Processed
		
		ResponseCode (an2)
		  Result of the transaction request based on the “Severity” response element returned by Check Gateway.
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

		$msg = $this->txpACHResponseFields[$response['ResponseCode']].'-'.$response['Message'];
		return $msg."|Amount:{$response['Amount']}|Trans:{$response['tranNr']}|Gate:TransFirst|ErrorID:".$this->lastCCErrorId;
	}
	
	function ccLastMessage($result) {
		// called after a transaction is attempted AND in the Billing tab Electronic Transactions report cc-transaction-history.php
		if(!is_array($result)) {
			//ResponseCode=0&tranNr=1237753&PostDate=2011-12-07T01:22:40.000&Amount=995&Message=Transaction+Processed
			foreach(explode('&', $result) as $part) {
				$part = explode('=', $part);
				$response[$part[0]] = $part[1];
			}
		}
		else $message = $result;
//screenLog($this->labeledResponseHTML($response));
		$message = $this->txpACHResponseFields[$response['ResponseCode']];
		if($response['ResponseCode'] != 0) {
			$message = $this->txpACHResponseFields[$response['ResponseCode']];
			if($response) $message .= ' '.$response['Message'].'.';
		}
		return $message;
	}
	
	function ccTransactionId($result) {
		foreach(explode('&', $result) as $part) {
			$part = explode('=', $part);
			$response[$part[0]] = $part[1];
		}
		return $response['tranNr'];
	}
	
	
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
1
R06
Returned per ODFI’s Request
2
R07
Authorization Revoked by Consumer
1
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
R99 Check21 1 High Risk: considered Unauthorized by NACHA, and considered the payment processor. 2 Considered a ChargeBack by the payment processor. 3 Dishonored Return code 4 Contested Dishonored / Corrected Return Entry code
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

}