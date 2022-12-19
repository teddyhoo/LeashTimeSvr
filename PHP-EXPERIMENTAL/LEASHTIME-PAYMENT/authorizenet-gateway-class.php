<? // authorizenet-gateway-class.php
// test login id: 6zz6m5N4Et
// test trans key: 9V9wUv6Yd92t27t5

require_once "abstract-merchant-gateway.php";
class AuthorizeNetGateway extends AbstractMerchantGateway
{
    // property declaration
    private $lastCCErrorId;
    public $ach_enabled = false; 
    private $ccGatewayURL = "https://secure2.authorize.net/gateway/transact.dll"; // new URL: https://secure2.authorize.net/gateway/transact.dll
    private $ccTestGatewayURL = "https://test.authorize.net/gateway/transact.dll"; 
    private $responseCodes = array(
	0 => 'Unknown',
	1 => 'Approved',
	2 => 'Declined',
	3 => 'Error',
	4 => 'Held for Review');
	
		private $avsResponses = array();

		//private $cardResponseCodes = array('M '=> 'Match', 'N' => 'No Match', 'P' => 'Not Processed', 'S' => 'Should have been present', 'U' => 'Issuer unable to process request');
		private $cardResponseCodes = array('M '=> 'Match', 'N' => 'No Match on CCV code', 'P' => 'CCV code not Processed', 'S' => 'CCV code should have been present', 'U' => 'Issuer unable to process CCV request');
	
		private $CAVResponseCodes = array(
	'' => 'CAVV not validated',
	'0' => 'CAVV not validated because erroneous data was submitted',
	'1' => 'CAVV failed validation',
	'2' => 'CAVV passed validation',
	'3' => 'CAVV validation could not be performed; issuer attempt incomplete',
	'4' => 'CAVV validation could not be performed; issuer system error',
	'5' => 'Reserved for future use',
	'6' => 'Reserved for future use',
	'7' => 'CAVV attempt - failed validation - issuer available (U.S.-issued card/non-U.S acquirer)',
	'8' => 'CAVV attempt - passed validation - issuer available (U.S.-issued card/non-U.S. acquirer)',
	'9' => 'CAVV attempt - failed validation - issuer unavailable (U.S.-issued card/non-U.S. acquirer)',
	'A' => 'CAVV attempt - passed validation - issuer unavailable (U.S.-issued card/non-U.S. acquirer)',
	'B' => 'CAVV passed validation, information only, no liability shift'
);	
	

		private $authnetResponseFields = array();
		
 function merchantInfoLabels() {
	 return array('x_login'=>'Merchant Login', 'x_tran_key'=>'Merchant Transaction Key', 'x_aux_key'=>'');
 }
 
 function necessaryMerchantInfoKeys() {
	 return array(
				'x_login',
				'x_tran_key');
 }
 
	function supportsRefund($ccOrACH) {
	 return $ccOrACH == 'cc';
	}
 
 


 function __construct() {
		$rawAVS = <<<RAWAVS
A = Address (Street) matches, ZIP does not
B = Address information not provided for AVS check
E = AVS error
G = Non-U.S. Card Issuing Bank
N = No Match on Address (Street) or ZIP
P = AVS not applicable for this transaction
R = Retry - System unavailable or timed out
S = Service not supported by issuer
U = Address information is unavailable
W = Nine digit ZIP matches, Address (Street) does not match
X = Address (Street) and nine digit ZIP match
Y = Address (Street) and five digit ZIP match
Z = Five digit ZIP matches, Address (Street) does not match
RAWAVS;
	foreach(explode("\n", $rawAVS) as $line) {
		$pair = explode(' = ', trim($line));
		$this->avsResponses[$pair[0]] = $pair[1];
	}
		
		$rawResponseFields = <<<RAWRESPONSEFIELDS
RESPONSE_CODE
RESPONSE_SUBCODE
RESPONSE_REASON_CODE
RESPONSE_REASON_TEXT
AUTH_CODE
AVS_RESPONSE
TRANSACTION_ID
INVOICE_NUM
DESCRIPTION
AMOUNT
METHOD
TRANSACTION_TYPE
CUSTOMER_ID
FIRST_NAME
LAST_NAME
COMPANY
ADDRESS
CITY
STATE
ZIP
COUNTRY
PHONE
FAX
EMAIL
SHIPTO_FIRST_NAME
SHIPTO_LAST_NAME
SHIPTO_COMPANY
SHIPTO_ADDRESS
SHIPTO_CITY
SHIPTO_STATE
SHIPTO_ZIP
SHIPTO_COUNTRY
TAX
DUTY
FREIGHT
TAX_EXEMPT
PO_NUMBER
MD5
CARD_CODE_RESPONSE
CARDHOLDER_AUTH_VERIFICATION_RESP
SPLIT_TENDER_ID
REQUESTED_AMOUNT
BALANCE_ON_CARD
ACCOUNT_NUMBER_LAST_4
CARD_TYPE
RAWRESPONSEFIELDS;
	foreach(explode("\n", $rawResponseFields) as $i =>$line) {
		define('AUTHNET_'.trim($line), $i);
		$this->authnetResponseFields[$i] = trim($line);
	}
 } // END CONSTRUCTOR
 
  function executeTransaction($auth, $cc, $transactionType, $x_amount, $otherData=null) {
		global $ccTestMode, $ccDebug, $duplicateWindow;
		$transactionTypes = array('CREDIT'=>'CREDIT', 'VOID'=>'VOID', 'SALE'=>'AUTH_CAPTURE', 'VALIDATE'=>'AUTH_ONLY');
		if($ccTestMode && $cc['x_card_num'] == '4222222222222') $x_amount = 1;  // dollar amount is set to the desired response code (1=approved)
		if(!$duplicateWindow && $transactionType == 'VALIDATE') $duplicateWindow = 0; // minimum
		$duplicateWindow = (string)max($duplicateWindow, 0);

		$pairs = array(
			//'x_test_request' => $ccTestMode ? '1' : '0',  // When set to true, tests can be performed on live accounts
			'x_login' => lt_decrypt($auth['x_login']),  // Up to 20 characters
			'x_tran_key' => lt_decrypt($auth['x_tran_key']), // 16 characters
			'x_type' => $transactionTypes[$transactionType],
			'x_relay_response' => 0,
			'x_delim_data' => 1,
			'x_delim_char' => '|',
			'x_duplicate_window' => $duplicateWindow, 
			'x_amount' => $x_amount, // Up to 15 digits with a decimal point (no dollar symbol)
			'x_exp_date' => date('m/Y', strtotime($cc['x_exp_date'])) // MMYY,MM/YY,MM-YY, MMYYYY, MM/YYYY,MM-YYYY
		);
		if($otherData['transactionId']) 
			$pairs['x_trans_id'] = $otherData['transactionId'];
		
		$usePostMethod = TRUE; //staffOnlyTEST(); // && dbTEST('doggiewalkerdotcom'); //mattOnlyTEST();  // TBD Switch to TRUE to activate
		foreach($cc as $key=>$value)
			if(!isset($pairs[$key]) && strpos($key, 'x_') === 0 && $value)
				$pairs[$key] = urlEncode(trim($value));
		$params = array();
		foreach($pairs as $k=>$v) {
			$params[] = "$k=$v";
		}
		set_error_handler('SSLWarningHandler', E_WARNING); // This suppresses: Warning: file_get_contents() [function.file-get-contents]: SSL: fatal protocol 
		$gatewayURL = $ccTestMode ? $this->ccTestGatewayURL : $this->ccGatewayURL;
		if($usePostMethod) {
			$result = $this->sendPOSTviaCurl(join('&', $params), $gatewayURL);
		}
		else {
			$result = file_get_contents($gatewayURL . "?" . join('&', $params));
		}
		
		
		if(FALSE && staffOnlyTEST()) {
			echo "Params: ".join('&', $params).'<hr>'.$result;
			exit;
		}

		$response = explode('|', $result);
		if($ccDebug) {
			echo "<font color=green>"; foreach($params as $p) echo "$p<br>"; echo "</font><p>";
			echo "<font color=blue>".print_r($result, 1)."</font><p>";
			if($response) echo "<p>".$this->labeledResponseHTML($response)."<p>";
		}

		if($response[AUTHNET_RESPONSE_CODE] == 0) {
			$final = array('FAILURE'=>'No response code');
			//if(ccDEVTestMode()) $final['FAILURE'] .= ' ['.$gatewayURL . "?" . join('&', $params).']';
			return $final;
		}
		else if($response[AUTHNET_RESPONSE_CODE] == 1) return $response[AUTHNET_TRANSACTION_ID];
		else {
			$this->logCCTransactionError($cc['clientptr'], 'tblcreditcard', $cc['ccid'], $result);
			return $response;  
		}
		
	}
	
	function sendPOSTviaCurl($postRequest, $gatewayURL) {

		require_once "log-cc.php";
		$postRequest = '"'.$postRequest.'"';
		$data  = shell_exec("python3 cc-bridge.py application/x-www-form-urlencoded $gatewayURL $postRequest AUTHORIZE");
		requestWrite("AUTHORIZE.NET: $gatewayURL\n$postRequest\n\nRESPONSE: " . $data);
		return $data;
	 	// helper function demonstrating how to send the xml with curl
		/*
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
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		if (!($data = curl_exec($ch))) {
			logLongError('CURL ERROR: '.curl_error($ch));
			throw New Exception(" CURL ERROR :" . curl_error($ch));
		}
		curl_close($ch);
		*/
	


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
	
	function labeledResponseHTML($response) {
		foreach($response as $i => $field) {
			$v = $field;
			if($i == AUTHNET_RESPONSE_CODE) $v = $this->responseCodes[$v];
			else if($i == AUTHNET_CARD_CODE_RESPONSE) $v = $this->cardResponseCodes[$v];
			else if($i == AUTHNET_RESPONSE_REASON_CODE) $v = "[$v] ".$this->getAuthorizeNetReason($v);
			else if($i == AUTHNET_AVS_RESPONSE) $v = $this->avsResponses[$v];
			else if($i == AUTHNET_CARDHOLDER_AUTH_VERIFICATION_RESP) { // applies only to Visa
				if($response[52] == 'Visa') // What!? no CONST for Card company?
					$v = $this->CAVResponseCodes[$v];
			}
			$style = strlen($v) ? '' : "style='color:gray;'";
			$s .= "<SPAN $style>".sprintf("%02d",$i+1).". {$this->authnetResponseFields[$i]}:</SPAN> $v<br>\n";
		}
		return $s;
	}
	
	function logCCTransactionError($clientid, $sourcetable, $ccid, $rawResponse) {
		$response = explode('|', $rawResponse);
		insertTable('tblcreditcarderror', 
							array('transactionid' => $response[AUTHNET_TRANSACTION_ID], 
								'time' => date('Y-m-d H:i:s'),
								'clientptr' => $clientid,
								'ccptr' => $ccid,
								'sourcetable' => $sourcetable,
								'response' => ($rawResponse ? $rawResponse : sqlVal("''")))

							);
		$this->lastCCErrorId = mysqli_insert_id();
	}						
	
	function ccErrorLogMessage($result, $amount=null) {  // $amount is ignored here
		$response = is_array($result) ? $result : explode('|', $result);
		if($response['FAILURE']) return 'Error-'.$response['FAILURE'];

		$msg = $this->responseCodes[$response[AUTHNET_RESPONSE_CODE]].'-'.$response[AUTHNET_RESPONSE_REASON_TEXT];
		return $msg."|Amount:{$response[AUTHNET_AMOUNT]}|Trans:{$response[AUTHNET_TRANSACTION_ID]}|Gate:Authorize.net|ErrorID:".$this->lastCCErrorId;
	}
	
	function ccLastMessage($result) {
		global $AUTHNET_SUBCODES;
		$response = is_array($result) ? $result : explode('|', $result);
		//screenLog($this->labeledResponseHTML($response));
		//$message = $response[AUTHNET_RESPONSE_REASON_TEXT];
		$message = $this->getAuthorizeNetReason($response[AUTHNET_RESPONSE_REASON_CODE]);
		if($response[AUTHNET_RESPONSE_CODE] != 1) {
			if(count($response) > AUTHNET_CARD_CODE_RESPONSE) {  // report only if the response includes this field
				$v = $response[AUTHNET_CARD_CODE_RESPONSE];
				if($v  && $v != 'M') 	$message .= ' '.$this->cardResponseCodes[$v].'.';
			}
			if(count($response) > AUTHNET_AVS_RESPONSE) {// report only if the response includes this field
			
				$v = $response[AUTHNET_AVS_RESPONSE];
		//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { /*print_r($response);echo "<p>";*/ echo "[AUTHNET_AVS_RESPONSE = ($v) {$this->avsResponses[$v]}]<p>"; }
				if($v && !in_array($v, array('X', 'Y', 'P'))) 
					$message .= ' '.$this->avsResponses[$v].'.';
			}
			if(count($response) > AUTHNET_CARDHOLDER_AUTH_VERIFICATION_RESP) {// report only if the response includes this field
				$v = $response[AUTHNET_CARDHOLDER_AUTH_VERIFICATION_RESP];
				if($v != 2  && $v !== '') 
					$message .= ' '.$this->CAVResponseCodes[$v].'.';
			}
		}
		return $message;
	}
	
	function ccTransactionId($result) {
		$response = explode('|', $result);
		return $response[AUTHNET_TRANSACTION_ID];
	}
	
	function getAuthorizeNetReason($code) {
		static $codes;
		if(!$codes) $codes =
												array(
	1 => array('This transaction has been approved.', 1),
	2 => array('This transaction has been declined.', 2),
	3 => array('This transaction has been declined.', 2),
	4 => array('This transaction has been declined.', 2), // The code returned from the processor indicating that the card used needs to be picked up.,
	5 => array('A valid amount is required.', 3), // The value submitted in the amount field did not pass validation for a number.,
	6 => array('The credit card number is invalid.', 3),
	7 => array('The credit card expiration date is invalid.', 3), // The format of the date submitted was incorrect.,
	8 => array('The credit card has expired.', 3),
	9 => array('The ABA code is invalid.', 3), // The value submitted in the x_bank_aba_code field did not pass validation or was not for a valid financial institution.,
	10 => array('The account number is invalid.', 3), // The value submitted in the x_bank_acct_num field did not pass validation.,
	11 => array('A duplicate transaction has been submitted.', 3), // A transaction with identical amount and credit card information was submitted two minutes prior.,
	12 => array('An authorization code is required but not present.', 3), // A transaction that required x_auth_code to be present was submitted without a value.,
	13 => array('The merchant API Login ID is invalid or the account is inactive.', 3),
	14 => array('The Referrer or Relay Response URL is invalid.', 3), // The Relay Response or Referrer URL does not match the merchant's configured value(s) or is absent. Applicable only to SIM and WebLink APIs.,
	15 => array('The transaction ID is invalid.', 3), // The transaction ID value is non-numeric or was not present for a transaction that requires it (i.e., VOID, PRIOR_AUTH_CAPTURE, and CREDIT).,
	16 => array('The transaction was not found.', 3), // The transaction ID sent in was properly formatted but the gateway had no record of the transaction.,
	17 => array('The merchant does not accept this type of credit card.', 3), // The merchant was not configured to accept the credit card submitted in the transaction.,
	18 => array('ACH transactions are not accepted by this merchant.', 3), // The merchant does not accept electronic checks.,
	19 => array('An error occurred during processing. Please try again in 5 minutes.', 3),
	20 => array('An error occurred during processing. Please try again in 5 minutes.', 3),
	21 => array('An error occurred during processing. Please try again in 5 minutes.', 3),
	22 => array('An error occurred during processing. Please try again in 5 minutes.', 3),
	23 => array('An error occurred during processing. Please try again in 5 minutes.', 3),
	24 => array('The Nova Bank Number or Terminal ID is incorrect. Call Merchant Service Provider.', 3),
	25 => array('An error occurred during processing. Please try again in 5 minutes.', 3),
	26 => array('An error occurred during processing. Please try again in 5 minutes.', 3),
	27 => array('The transaction resulted in an AVS mismatch. The address provided does not match billing address of cardholder.', 2),
	28 => array('The merchant does not accept this type of credit card.', 2), // The Merchant ID at the processor was not configured to accept this card type.,
	29 => array('The Paymentech identification numbers are incorrect. Call Merchant Service Provider.', 2),
	30 => array('The configuration with the processor is invalid. Call Merchant Service Provider.', 2),
	31 => array('The FDC Merchant ID or Terminal ID is incorrect. Call Merchant Service Provider.', 2), // The merchant was incorrectly set up at the processor.,
	32 => array('This reason code is reserved or not applicable to this API.', 3),
	33 => array('FIELD cannot be left blank.', 3), // The word FIELD will be replaced by an actual field name. This error indicates that a field the merchant specified as required was not filled in. Please see the Form Fields section of the Merchant Integration Guide for details.,
	34 => array('The VITAL identification numbers are incorrect. Call Merchant Service Provider.', 2), // The merchant was incorrectly set up at the processor.,
	35 => array('An error occurred during processing. Call Merchant Service Provider.', 2), // The merchant was incorrectly set up at the processor.,
	36 => array('The authorization was approved, but settlement failed.', 3),
	37 => array('The credit card number is invalid.', 2),
	38 => array('The Global Payment System identification numbers are incorrect. Call Merchant Service Provider.', 2), // The merchant was incorrectly set up at the processor.,
	40 => array('This transaction must be encrypted.', 3),
	41 => array('This transaction has been declined.', 2), // Only merchants set up for the FraudScreen.Net service would receive this decline. This code will be returned if a given transaction's fraud score is higher than the threshold set by the merchant.,
	43 => array('The merchant was incorrectly set up at the processor. Call your Merchant Service Provider.', 3), // The merchant was incorrectly set up at the processor.,
	44 => array('This transaction has been declined.', 2), // The card code submitted with the transaction did not match the card code on file at the card issuing bank and the transaction was declined.,
	45 => array('This transaction has been declined.', 2), // This error would be returned if the transaction received a code from the processor that matched the rejection criteria set by the merchant for both the AVS and Card Code filters.,
	46 => array('Your session has expired or does not exist. You must log in to continue working.', 3),
	47 => array('The amount requested for settlement may not be greater than the original amount authorized.', 3), // This occurs if the merchant tries to capture funds greater than the amount of the original authorization-only transaction.,
	48 => array('This processor does not accept partial reversals.', 3), // The merchant attempted to settle for less than the originally authorized amount.,
	49 => array('A transaction amount greater than $[amount] will not be accepted.', 3), // The transaction amount submitted was greater than the maximum amount allowed.,
	50 => array('This transaction is awaiting settlement and cannot be refunded.', 3), // Credits or refunds can only be performed against settled transactions. The transaction against which the credit/refund was submitted has not been settled, so a credit cannot be issued.,
	51 => array('The sum of all credits against this transaction is greater than the original transaction amount.', 3),
	52 => array('The transaction was authorized, but the client could not be notified; the transaction will not be settled.', 3),
	53 => array('The transaction type was invalid for ACH transactions.', 3), // If x_method = ECHECK, x_type cannot be set to CAPTURE_ONLY.,
	54 => array('The referenced transaction does not meet the criteria for issuing a credit.', 3),
	55 => array('The sum of credits against the referenced transaction would exceed the original debit amount.', 3), // The transaction is rejected if the sum of this credit and prior credits exceeds the original debit amount,
	56 => array('This merchant accepts ACH transactions only; no credit card transactions are accepted.', 3), // The merchant processes eCheck.Net transactions only and does not accept credit cards.,
	57 => array('An error occurred in processing. Please try again in 5 minutes.', 3),
	58 => array('An error occurred in processing. Please try again in 5 minutes.', 3),
	59 => array('An error occurred in processing. Please try again in 5 minutes.', 3),
	60 => array('An error occurred in processing. Please try again in 5 minutes.', 3),
	61 => array('An error occurred in processing. Please try again in 5 minutes.', 3),
	62 => array('An error occurred in processing. Please try again in 5 minutes.', 3),
	63 => array('An error occurred in processing. Please try again in 5 minutes.', 3),
	65 => array('This transaction has been declined.', 2), // The transaction was declined because the merchant configured their account through the Merchant Interface to reject transactions with certain values for a Card Code mismatch.,
	66 => array('This transaction cannot be accepted for processing.', 3), // The transaction did not meet gateway security guidelines.,
	68 => array('The version parameter is invalid.', 3), // The value submitted in x_version was invalid.,
	69 => array('The transaction type is invalid.', 3), // The value submitted in x_type was invalid.,
	70 => array('The transaction method is invalid.', 3), // The value submitted in x_method was invalid.,
	71 => array('The bank account type is invalid.', 3), // The value submitted in x_bank_acct_type was invalid.,
	72 => array('The authorization code is invalid.', 3), // The value submitted in x_auth_code was more than six characters in length.,
	73 => array('The driver\'s license date of birth is invalid.', 3), // The format of the value submitted in x_drivers_license_dob was invalid.,
	74 => array('The duty amount is invalid.', 3), // The value submitted in x_duty failed format validation.,
	75 => array('The freight amount is invalid.', 3), // The value submitted in x_freight failed format validation.,
	76 => array('The tax amount is invalid.', 3), // The value submitted in x_tax failed format validation.,
	77 => array('The SSN or tax ID is invalid.', 3), // The value submitted in x_customer_tax_id failed validation.,
	78 => array('The Card Code (CVV2/CVC2/CID) is invalid.', 3), // The value submitted in x_card_code failed format validation.,
	79 => array('The driver\'s license number is invalid.', 3), // The value submitted in x_drivers_license_num failed format validation.,
	80 => array('The driver\'s license state is invalid.', 3), // The value submitted in x_drivers_license_state failed format validation.,
	81 => array('The requested form type is invalid.', 3), // The merchant requested an integration method not compatible with the AIM API.,
	82 => array('Scripts are only supported in version 2.5.', 3), // The system no longer supports version 2.5; requests cannot be posted to scripts.,
	83 => array('The requested script is either invalid or no longer supported.', 3), // The system no longer supports version 2.5; requests cannot be posted to scripts.,
	84 => array('This reason code is reserved or not applicable to this API.', 3),
	85 => array('This reason code is reserved or not applicable to this API.', 3),
	86 => array('This reason code is reserved or not applicable to this API.', 3),
	87 => array('This reason code is reserved or not applicable to this API.', 3),
	88 => array('This reason code is reserved or not applicable to this API.', 3),
	89 => array('This reason code is reserved or not applicable to this API.', 3),
	90 => array('This reason code is reserved or not applicable to this API.', 3),
	91 => array('Version 2.5 is no longer supported.', 3),
	92 => array('The gateway no longer supports the requested method of integration.', 3),
	97 => array('This transaction cannot be accepted.', 3), // Applicable only to SIM API. Fingerprints are only valid for a short period of time. If the fingerprint is more than one hour old or more than 15 minutes into the future, it will be rejected. This code indicates that the transaction fingerprint has expired.,
	98 => array('This transaction cannot be accepted.', 3), // Applicable only to SIM API. The transaction fingerprint has already been used.,
	99 => array('This transaction cannot be accepted.', 3), // Applicable only to SIM API. The server-generated fingerprint does not match the merchant-specified fingerprint in the x_fp_hash field.,
	100 => array('The eCheck.Net type is invalid.', 3), // Applicable only to eCheck.Net. The value specified in the x_echeck_type field is invalid.,
	101 => array('The given name on the account and/or the account type does not match the actual account.', 3), // Applicable only to eCheck.Net. The specified name on the account and/or the account type do not match the NOC record for this account.,
	102 => array('This request cannot be accepted.', 3), // A password or Transaction Key was submitted with this WebLink request. This is a high security risk.,
	103 => array('This transaction cannot be accepted.', 3), // A valid fingerprint, Transaction Key, or password is required for this transaction.,
	104 => array('This transaction is currently under review.', 3), // Applicable only to eCheck.Net. The value submitted for country failed validation.,
	105 => array('This transaction is currently under review.', 3), // Applicable only to eCheck.Net. The values submitted for city and country failed validation.,
	106 => array('This transaction is currently under review.', 3), // Applicable only to eCheck.Net. The value submitted for company failed validation.,
	107 => array('This transaction is currently under review.', 3), // Applicable only to eCheck.Net. The value submitted for bank account name failed validation.,
	108 => array('This transaction is currently under review.', 3), // Applicable only to eCheck.Net. The values submitted for first name and last name failed validation.,
	109 => array('This transaction is currently under review.', 3), // Applicable only to eCheck.Net. The values submitted for first name and last name failed validation.,
	110 => array('This transaction is currently under review.', 3), // Applicable only to eCheck.Net. The value submitted for bank account name does not contain valid characters.,
	116 => array('The authentication indicator is invalid.', 3), // This error is only applicable to Verified by Visa and MasterCard SecureCode transactions. The ECI value for a Visa transaction; or the UCAF indicator for a MasterCard transaction submitted in the x_authentication_indicator field is invalid.,
	117 => array('The cardholder authentication value is invalid.', 3), // This error is only applicable to Verified by Visa and MasterCard SecureCode transactions. The CAVV for a Visa transaction; or the AVV/UCAF for a MasterCard transaction is invalid.,
	118 => array('The combination of authentication indicator and cardholder authentication value is invalid.', 3), // This error is only applicable to Verified by Visa and MasterCard SecureCode transactions. The combination of authentication indicator and cardholder authentication value for a Visa or MasterCard transaction is invalid. For more information, see the "Cardholder Authentication" section of this document.,
	119 => array('Transactions having cardholder authentication values cannot be marked as recurring.', 3), // This error is only applicable to Verified by Visa and MasterCard SecureCode transactions. Transactions submitted with a value in x_authentication_indicator and x_recurring_billing=YES will be rejected.,
	120 => array('An error occurred during processing. Please try again.', 3), // The system-generated void for the original timed-out transaction failed. (The original transaction timed out while waiting for a response from the authorizer.),
	121 => array('An error occurred during processing. Please try again.', 3), // The system-generated void for the original errored transaction failed. (The original transaction experienced a database error.),
	122 => array('An error occurred during processing. Please try again.', 3), // The system-generated void for the original errored transaction failed. (The original transaction experienced a processing error.),
	123 => array('This account has not been given the permission(s) required for this request.', 3), // The transaction request must include the API Login ID associated with the payment gateway account.,
	127 => array('The transaction resulted in an AVS mismatch. The address provided does not match billing address of cardholder.', 2), // The system-generated void for the original AVS-rejected transaction failed.,
	128 => array('This transaction cannot be processed.', 3), // The customer's financial institution does not currently allow transactions for this account.,
	130 => array('This payment gateway account has been closed.', 3), // IFT: The payment gateway account status is Blacklisted.,
	131 => array('This transaction cannot be accepted at this time.', 3), // IFT: The payment gateway account status is Suspended-STA.,
	132 => array('This transaction cannot be accepted at this time.', 3), // IFT: The payment gateway account status is Suspended-Blacklist.,
	141 => array('This transaction has been declined.', 2), // The system-generated void for the original FraudScreen-rejected transaction failed.,
	145 => array('This transaction has been declined.', 2), // The system-generated void for the original card code-rejected and AVS-rejected transaction failed.,
	152 => array('The transaction was authorized, but the client could not be notified; the transaction will not be settled.', 3), // The system-generated void for the original transaction failed. The response for the original transaction could not be communicated to the client.,
	165 => array('This transaction has been declined.', 2), // The system-generated void for the original card code-rejected transaction failed.,
	170 => array('An error occurred during processing. Please contact the merchant.', 3), // Concord EFS - Provisioning at the processor has not been completed.,
	171 => array('An error occurred during processing. Please contact the merchant.', 2), // Concord EFS - This request is invalid.,
	172 => array('An error occurred during processing. Please contact the merchant.', 2), // Concord EFS - The store ID is invalid.,
	173 => array('An error occurred during processing. Please contact the merchant.', 3), // Concord EFS - The store key is invalid.,
	174 => array('The transaction type is invalid. Please contact the merchant.', 2), // Concord EFS - This transaction type is not accepted by the processor.,
	175 => array('The processor does not allow voiding of credits.', 3), // Concord EFS - This transaction is not allowed. The Concord EFS processing platform does not support voiding credit transactions. Please debit the credit card instead of voiding the credit.,
	180 => array('An error occurred during processing. Please try again.', 3), // The processor response format is invalid.,
	181 => array('An error occurred during processing. Please try again.', 3), // The system-generated void for the original invalid transaction failed. (The original transaction included an invalid processor response format.),
	185 => array('This reason code is reserved or not applicable to this API.', 3),
	193 => array('The transaction is currently under review.', 4), // The transaction was placed under review by the risk management system.,
	200 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The credit card number is invalid.,
	201 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The expiration date is invalid.,
	202 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The transaction type is invalid.,
	203 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The value submitted in the amount field is invalid.,
	204 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The department code is invalid.,
	205 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The value submitted in the merchant number field is invalid.,
	206 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The merchant is not on file.,
	207 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The merchant account is closed.,
	208 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The merchant is not on file.,
	209 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. Communication with the processor could not be established.,
	210 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The merchant type is incorrect.,
	211 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The cardholder is not on file.,
	212 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The bank configuration is not on file,
	213 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The merchant assessment code is incorrect.,
	214 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. This function is currently unavailable.,
	215 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The encrypted PIN field format is invalid.,
	216 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The ATM term ID is invalid.,
	217 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. This transaction experienced a general message format problem.,
	218 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The PIN block format or PIN availability value is invalid.,
	219 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The ETC void is unmatched.,
	220 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The primary CPU is not available.,
	221 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. The SE number is invalid.,
	222 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. Duplicate auth request (from INAS).,
	223 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. This transaction experienced an unspecified error.,
	224 => array('This transaction has been declined.', 2), // This error code applies only to merchants on FDC Omaha. Please re-enter the transaction.,
	243 => array('Recurring billing is not allowed for this eCheck.Net type.', 3), // The combination of values submitted for x_recurring_billing and x_echeck_type is not allowed.,
	244 => array('This eCheck.Net type is not allowed for this Bank Account Type.', 3), // The combination of values submitted for x_bank_acct_type and x_echeck_type is not allowed.,
	245 => array('This eCheck.Net type is not allowed when using the payment gateway hosted payment form.', 3), // The value submitted for x_echeck_type is not allowed when using the payment gateway hosted payment form.,
	246 => array('This eCheck.Net type is not allowed.', 3), // The merchant's payment gateway account is not enabled to submit the eCheck.Net type.,
	247 => array('This eCheck.Net type is not allowed.', 3), // The combination of values submitted for x_type and x_echeck_type is not allowed.,
	248 => array('The check number is invalid.', 3), // Invalid check number. Check number can only consist of letters and numbers and not more than 15 characters.,
	250 => array('This transaction has been declined.', 2), // This transaction was submitted from a blocked IP address.,
	251 => array('This transaction has been declined.', 2), // The transaction was declined as a result of triggering a Fraud Detection Suite filter.,
	252 => array('Your order has been received. Thank you for your business!', 4), // The transaction was accepted, but is being held for merchant review. The merchant can customize the customer response in the Merchant Interface.,
	253 => array('Your order has been received. Thank you for your business!', 4), // The transaction was accepted and was authorized, but is being held for merchant review. The merchant can customize the customer response in the Merchant Interface.,
	254 => array('Your transaction has been declined.', 2), // The transaction was declined after manual review.,
	261 => array('An error occurred during processing. Please try again.', 3), // The transaction experienced an error during sensitive data encryption and was not processed. Please try again.,
	270 => array('The line item [item number] is invalid.', 3), // A value submitted in x_line_item for the item referenced is invalid.,
	271 => array('The number of line items submitted is not allowed. A maximum of 30 line items can be submitted.', 3), // The number of line items submitted exceeds the allowed maximum of 30.,
	288 => array('Merchant is not registered as a Cardholder Authentication participant. This transaction cannot be accepted.', 3), // The merchant has not indicated participation in any Cardholder Authentication Programs in the Merchant Interface.,
	289 => array('This processor does not accept zero dollar authorization for this card type.', 3), // Your credit card processing service does not yet accept zero dollar authorizations for Visa credit cards. You can find your credit card processor listed on your merchant profile.,
	290 => array('One or more required AVS values for zero dollar authorization were not submitted.', 3), // When submitting authorization requests for Visa, the address and zip code fields must be entered.,
	300 => array('The device ID is invalid.', 3), // The value submitted for x_device_id is invalid.,
	301 => array('The device batch ID is invalid.', 3), // The value submitted for x_device_batch_id is invalid.,
	302 => array('The reversal flag is invalid.', 3), // The value submitted for x_reversal is invalid.,
	303 => array('The device batch is full. Please close the batch.', 3), // The current device batch must be closed manually from the POS device.,
	304 => array('The original transaction is in a closed batch.', 3), // The original transaction has been settled and cannot be reversed.,
	305 => array('The merchant is configured for auto-close.', 3), // This merchant is configured for auto-close and cannot manually close batches.,
	306 => array('The batch is already closed.', 3), // The batch is already closed.,
	307 => array('The reversal was processed successfully.', 1), // The reversal was processed successfully.,
	308 => array('Original transaction for reversal not found.', 1), // The transaction submitted for reversal was not found.,
	309 => array('The device has been disabled.', 3), // The device has been disabled.,
	310 => array('This transaction has already been voided.', 1), // This transaction has already been voided.,
	311 => array('This transaction has already been captured', 1), // This transaction has already been captured.,
	315 => array('The credit card number is invalid.', 2), // This is a processor-issued decline.,
	316 => array('The credit card expiration date is invalid.', 2), // This is a processor-issued decline.,
	317 => array('The credit card has expired.', 2), // This is a processor-issued decline.,
	318 => array('A duplicate transaction has been submitted.', 2), // This is a processor-issued decline.,
	319 => array('The transaction cannot be found.', 2) //This is a processor-issued decline.
	);
	screenLog("COUNT: ".count($codes)." code: [$code])");

		return $codes[$code][0];
	}
}

