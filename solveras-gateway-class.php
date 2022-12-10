<? // solveras-gateway-class.php
/*
https://secure.solverasgateway.com
Username: Leashtime
Password: Test1234
*/
// test trans key: dEJTb9M8Sh5ueD93KqJ433439jUua86v
require_once "encryption.php";
require_once "abstract-merchant-gateway.php";
require_once "log-cc.php";

class SolverasGateway extends AbstractMerchantGateway {
	// property declaration
	private $lastCCErrorId;	
	public $ccGatewayURL = "https://secure.nmi.com/api/v2/three-step";
    public $ccTestGatewayURL = "https://secure.nmi.com/api/v2/three-step"; 
    public $ach_enabled = false; 
    
	private $CVVResponseCodes = array(
			'M' => 'CVV2/CVC2 Match',
			'N' => 'CVV2/CVC2 No Match',
			'P' => 'Not Processed',
			'S' => 'Merchant has indicated that CVV2/CVC2 is not present on the card',
			'U' => 'Issuer is not certified and/or has not provided Visa encryption keys'
	);	
	private $AVSResponseCodes = array(
			'X' => 'Exact Match, 9-character numeric ZIP',
			'Y' => 'Exact Match, 5-character numeric ZIP',
			'D' => 'Exact Match, 5-character numeric ZIP',
			'M' => 'Exact Match, 5-character numeric ZIP',
			'A' => 'Address Match only',
			'B' => 'Address Match only',
			'W' => '9-character numeric ZIP Match only',
			'Z' => '5-character ZIP Match only',
			'P' => '5-character ZIP Match only',
			'L' => '5-character ZIP Match only',
			'N' => 'No address or ZIP Match',
			'C' => 'No address or ZIP Match',
			'U' => 'Address unavailable',
			'G' => 'Non-U.S. issuer does not participate',
			'I' => 'Non-U.S. issuer does not participate',
			'R' => 'Issuer system unavailable',
			'E' => 'Not a mail/phone order',
			'S' => 'Service not supported',
			'0' => 'AVS Not AVailable',
			'O' => 'AVS Not AVailable',
			'B' => 'AVS Not AVailable'
	);
	private $SpecificErrorCodes = array(
			100 => 'Transaction was approved',
			200 => 'Transaction was declined by Processor',
			201 => 'Do Not Honor',
			202 => 'Insufficient Funds',
			203 => 'Over Limit',
			204 => 'Transaction Not Allowed',
			220 => 'Incorrect Payment Data',
			221 => 'No Such Card Issuer',
			222 => 'No Card Number on file with Issuer',
			223 => 'Expired Card',
			224 => 'Invalid Expiration Date',
			225 => 'Invalid Card Security Code',
			240 => 'Call Issuer for further information',
			250 => 'Pick Up Card',
			251 => 'Lost Card',
			252 => 'Stolen Card',
			253 => 'Fraudulent Card',
			260 => 'Declined with further instructions Available (see response text)',
			261 => 'Declined - Stop All Recurring Payments',
			262 => 'Declined - Stop This Recurring Program',
			263 => 'Declined - Update Cardholder Data Available',
			264 => 'Declined - Retry in a few days',
			300 => 'Transaction was Rejected by Gateway',
			400 => 'Transaction Error Returned bhy Processor',
			410 => 'Invalid Merchant Configuration',
			411 => 'Merchant Account is Inactive',
			420 => 'Communication Error',
			421 => 'Communication Error with Issuer',
			430 => 'Duplicate Transaction at Processor',
			440 => 'Processor Format Error',
			441 => 'Invalid Transaction Information',
			460 => 'Processor Feature not AVailable',
			461 => 'Unsupported Card Type'
	);

	function __construct() {
		requestWrite("SolverasGateway constructor TEST|TEST|TEST");	
	}

	 function ccValidationTests() {
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


	function necessaryMerchantInfoKeys() {
		return array('x_tran_key');
	}
	function supportsACH() {
		return FALSE;
	}
	function merchantInfoLabels() {
		return array('x_login'=>'', 'x_tran_key'=>'Merchant Transaction Key', 'x_aux_key'=>'');
	}
	function supportsRefund($ccOrACH) {
		return $ccOrACH == 'ach' ? FALSE : ($ccOrACH == 'cc' ? TRUE : FALSE);
	}
	function getApiKey($auth) {
		global $ccTestMode;
		//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "TESTMODE: [$ccTestMode] ";print_r(lt_decrypt($auth['x_tran_key']));exit;}
		if($ccTestMode) 
			return 'dEJTb9M8Sh5ueD93KqJ433439jUua86v';			
		return lt_decrypt($auth['x_tran_key']);
	}
	function startClientPayRequest($auth, $client, $op, $amount) {
		// return a form-url
		//	'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");
		$apiKey = $this->getApiKey($auth);
		$redirectURL = "https://{$_SERVER["HTTP_HOST"]}/solveras-callback.php?op=$op"; // use a onetime token
		$ipAddress = $_SESSION['REMOTE_ADDR'] ? "<ip-address>{$_SESSION['REMOTE_ADDR']}</ip-address>" : '';

		$xmlRequest = new DOMDocument('1.0','UTF-8');
		$xmlRequest->formatOutput = true;
		$xmlAddCust = $xmlRequest->createElement('sale');
		$this->appendXmlNode($xmlAddCust,'api-key',$apiKey);
		$this->appendXmlNode($xmlAddCust,'redirect-url',$redirectURL);
		$this->appendXmlNode($xmlAddCust,'amount', $amount);
		$this->appendXmlNode($xmlAddCust,'ip-address', $SERVER['REMOTE_ADDR']);
		$xmlBillingAddress = $xmlRequest->createElement('billing');
		$this->appendXmlNode($xmlBillingAddress,'first-name', $client['fname']);
		$this->appendXmlNode($xmlBillingAddress,'last-name', $client['lname']);
		$this->appendXmlNode($xmlBillingAddress,'email', $client['email']);
		$xmlAddCust->appendChild($xmlBillingAddress);
		$xmlRequest->appendChild($xmlAddCust);

		$responseXml = $this->sendXMLGatewayRequest($xmlRequest);
		if(!$responseXml) 
			return array('Error', 'Error: No response from Solveras: '.date('Y-m-d H:i:s'));
		$response = simplexml_load_string($responseXml);
		$responseArray = array();
		foreach($response->children() as $field)
			$responseArray[$field->getName()] = (string)$field;
		if($responseArray['result'] == 2) 
			return array('Declined', $responseArray['result-text']); // Declined - use result-text
		else if($responseArray['result'] == 3) {
			return array('Error', $responseArray['result-text'] ); // Error - use result-text .'<p>'.htmlentities($requestXml)
		}
		else 
			return $responseArray['form-url'];
	}
    function startAuthRequest($auth, $client, $op) {
		// return a form-url
		//	'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");
		$apiKey = $this->getApiKey($auth);
		$redirectURL = "https://{$_SERVER["HTTP_HOST"]}/solveras-callback.php?op=$op"; // use a onetime token
		$ipAddress = $_SESSION['REMOTE_ADDR'] ? "<ip-address>{$_SESSION['REMOTE_ADDR']}</ip-address>" : '';
		$xmlRequest = new DOMDocument('1.0','UTF-8');
		$xmlRequest->formatOutput = true;
		$xmlAddCust = $xmlRequest->createElement($vaultid ? 'update-customer' : 'add-customer');
		$this->appendXmlNode($xmlAddCust,'api-key',$apiKey);
		$this->appendXmlNode($xmlAddCust,'redirect-url',$redirectURL);
		$xmlBillingAddress = $xmlRequest->createElement('billing');
		$this->appendXmlNode($xmlBillingAddress,'first-name', $client['fname']);
		$this->appendXmlNode($xmlBillingAddress,'last-name', $client['lname']);
		$this->appendXmlNode($xmlBillingAddress,'email', $client['email']);
		$xmlAddCust->appendChild($xmlBillingAddress);
		$xmlRequest->appendChild($xmlAddCust);
		$responseXml = $this->sendXMLGatewayRequest($xmlRequest);
		if(!$responseXml)
			return array('Error', 'Error: No response from Solveras: '.date('Y-m-d H:i:s'));
		$response = simplexml_load_string($responseXml);
		$responseArray = array();
		foreach($response->children() as $field)
			$responseArray[$field->getName()] = (string)$field;
		if($responseArray['result'] == 2) 
			return array('Declined', $responseArray['result-text']); // Declined - use result-text
		else if($responseArray['result'] == 3) {
			return array('Error', $responseArray['result-text'] ); // Error - use result-text .'<p>'.htmlentities($requestXml)
		}
		else
			return $responseArray['form-url'];
	}
	function executeTransaction($auth, $cc, $transactionType, $x_amount, $otherData=null) {
		global $ccTestMode, $ccDebug, $duplicateWindow;
		$transactionTypes = array('CREDIT'=>'refund', 'VOID'=>'void', 'SALE'=>'sale', 'VALIDATE'=>'auth');
		$transactionTag = $transactionTypes[$transactionType];
		$apiKey = $this->getApiKey($auth);			
		$xmlRequest = new DOMDocument('1.0','UTF-8');
		$xmlRequest->formatOutput = true;
		$xmlTransaction = $xmlRequest->createElement($transactionTag);
		$this->appendXmlNode($xmlTransaction,'api-key',$apiKey);
		if($cc && !in_array($transactionTag, array('void', 'refund')))  // $cc is null for adHoc CC payments
			$this->appendXmlNode($xmlTransaction,'customer-vault-id',$cc['vaultid']);
		$this->appendXmlNode($xmlTransaction,'amount',$x_amount);
		if($otherData['transactionId']) $this->appendXmlNode($xmlTransaction,'transaction-id', $otherData['transactionId']);
		if($otherData['order-description']) $this->appendXmlNode($xmlTransaction,'order-description', $otherData['order-description']);
		$xmlRequest->appendChild($xmlTransaction);
		$responseXml = $this->sendXMLGatewayRequest($xmlRequest);
		return $this->distillXmlResponseAndLogCCTransactionError($responseXml, $cc);  
	}
	function distillXmlResponseAndLogCCTransactionError($responseXml, $cc) {
		if(!$responseXml) return array('FAILURE'=>'Error: No response from Solveras: '.date('Y-m-d H:i:s'));
		$response = is_string($responseXml) ? simplexml_load_string($responseXml) : $responseXml;  // handle string or object
			
		$responseArray = array();
			foreach($response->children() as $field)
				$responseArray[$field->getName()] = (string)$field;
			if(!$responseArray['result']) 
				$final = array('FAILURE'=>'Error: No response code');
			if($responseArray['result'] == 2) 
				$final = array('Declined', $responseXml); // Declined - use result-text
			else if($responseArray['result'] == 3) {
				$final = array('Error', $responseXml); // Error - use result-text
			}
			else $final = $responseArray['transaction-id'];

			if($responseArray['result'] > 1) {
				$forCreditCard = $cc['x_exp_date'] || !$cc;
				$sourcetable = $forCreditCard ? 'tblcreditcard' : 'tblecheckacct';
				$sourceid = $forCreditCard ? ($cc['ccid'] ? $cc['ccid'] : -999): ($cc['acctid'] ? $cc['acctid'] : '0');
				$this->logCCTransactionError($cc['clientptr'], $sourcetable, $sourceid, $response);
			}
			return $final;  
	}	
	function executeThreeStepTransaction($auth, $cc, $transactionType, $x_amount, $otherData=null) {
	}
	function ccErrorLogMessage($result, $amount=null) {  // $amount is ignored here
			if(is_array($result) && $response['FAILURE']) return 'Error-'.$response['FAILURE']; // $response ?!?!
			$response = is_string($result[1]) ? simplexml_load_string($result[1]) :$result[1] ;
			$responseArray = array();
			foreach($response->children() as $field)
				$responseArray[$field->getName()] = (string)$field;
			$primary = $responseArray['result'] == 2 ? 'Declined' : 'Error';
			$specificError = $this->SpecificErrorCodes[$responseArray['result-code']];
			$msg = $primary.'-'.$specificError;
			return $msg."|Amount:{$responseArray['amount']}|Trans:{$responseArray['transaction-id']}|Gate:Solveras|ErrorID:".$this->lastCCErrorId;
	}
	function ccLastMessage($result) {
			$xml = is_string($result) ? $result : $result[1];
			$response = is_string($xml) ? simplexml_load_string($xml) : $xml;
			if(!is_object($response)) {
				return  "No transaction attempted.";
			}
			$responseArray = array();
			foreach($response->children() as $field)
				$responseArray[$field->getName()] = (string)$field;

			$message = $this->SpecificErrorCodes[$responseArray['result-code']].'. ';
			if($responseArray['result'] != 1) {
				if($responseArray['cvv-result']) {  // report only if the response includes this field
					$v = $this->CVVResponseCodes[$responseArray['cvv-result']];
					if($v  && in_array($responseArray['cvv-result'], array('N', 'S', 'U')))
						$message .= " CVV: $v. ";
				}
				if($responseArray['avs-result']) {// report only if the response includes this field
					$v = $this->AVSResponseCodes[$responseArray['avs-result']];

					$message .= " AVS: $v. ";
				}
				if($responseArray['result-text']) {
					$message .= " ".$responseArray['result-text'];
				}
			}
			return $message;
	}
	function ccTransactionId($result) {
			$response = simplexml_load_string($result[1]);
			$responseArray = array();
			foreach($response->children() as $field)
				$responseArray[$field->getName()] = (string)$field;
			return $responseArray['transaction-id'];
	}
	function logCCTransactionError($clientid, $sourcetable, $ccid, $rawResponse) {
			$response = is_string($rawResponse) ? $rawResponse : $rawResponse->saveXML();
			//$responseArray = array();
			//foreach($response->children() as $field)
				//$responseArray[$field->getName()] = (string)$field;
			insertTable('tblcreditcarderror', 
								array('transactionid' => ($responseArray['transaction-id'] ? $responseArray['transaction-id'] : '0'), 
									'time' => date('Y-m-d H:i:s'),
									'clientptr' => $clientid,
									'ccptr' => $ccid,
									'sourcetable' => $sourcetable,
									'response' => ($response ? $response : sqlVal("''"))),
									1
								);
			$this->lastCCErrorId = mysql_insert_id();
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
		
	function performStep3($auth, $tokenId) {
			$apiKey = $this->getApiKey($auth);
			$xmlRequest = new DOMDocument('1.0','UTF-8');
			$xmlRequest->formatOutput = true;
			$xmlComplete = $xmlRequest->createElement('complete-action');
			$this->appendXmlNode($xmlComplete,'api-key',$apiKey);
			$this->appendXmlNode($xmlComplete,'token-id',$tokenId);
			$xmlRequest->appendChild($xmlComplete);
			$responseXml = $this->sendXMLGatewayRequest($xmlRequest);
			if(!$responseXml) return null;

			//echo htmlentities($responseXml->saveHTML());exit;			
			return simplexml_load_string($responseXml);
	}
		
	function sendXMLGatewayRequest($xml) {
			global $ccTestMode;
			$url = $ccTestMode ? $this->ccTestGatewayURL : $this->ccGatewayURL;
			return $this->sendXMLviaCurl($xml, $url);
	}
		
	function sendXMLviaCurl($xmlRequest,$gatewayURL) {
			/*$ch = curl_init(); // Initialize curl handle
			curl_setopt($ch, CURLOPT_URL, $gatewayURL); // Set POST URL
			$headers = array();
			$headers[] = "Content-type: text/xml";
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Add http headers to let it know we're sending XML
			$xmlString = $xmlRequest->saveXML();
			curl_setopt($ch, CURLOPT_FAILONERROR, 1); // Fail on errors
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Allow redirects
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return into a variable
			curl_setopt($ch, CURLOPT_PORT, 443); // Set the port number
			curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Times out after 25s
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString); // Add XML directly in POST
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			$CURL_SSLVERSION_TLSv1_2 = 6;
			curl_setopt ($ch, CURLOPT_SSLVERSION, $CURL_SSLVERSION_TLSv1_2);
			if (!($data = curl_exec($ch))) {
				logLongError('CURL ERROR: '.curl_error($ch));
				//print  "curl error =>" .curl_error($ch) ."\n";
				//throw New Exception(" CURL ERROR :" . curl_error($ch));
			}
			curl_close($ch);*/
			require_once "log-cc.php";
			$postRequest = '"'.$postRequest.'"';
			$data = shell_exec("python3 cc-bridge.py 'text/xml' $gatewayURL $xmlString SOLVERAS");
			requestWrite("SOLVERAS: $gatewayURL\n$xmlString\n\nRESPONSE: $data");
			return $data;
	}

	// Helper function to make building xml dom easier
	function appendXmlNode($parentNode,$name, $value) {
					$tempNode = new DOMElement($name,htmlentities($value));
					$parentNode->appendChild($tempNode);
	}

    function OLDstartAuthRequest($auth) {
		$apiKey = $this->getApiKey($auth);
		$redirectURL = "https://{$_SERVER["HTTP_HOST"]}/solveras_auth_callback.php"; // use a onetime token
		$ipAddress = $_SESSION['REMOTE_ADDR'] ? "<ip-address>{$_SESSION['REMOTE_ADDR']}</ip-address>" : '';
		$requestXml = "<add-customer>
			api-key=$apiKey
			redirect-url=$redirectURL
			<billing>
			first-name=testF
			last-name=testL
			</billing>
			</add-customer>";
		$responseXml = $this->sendXMLGatewayRequest($requestXml);
		$response = simplexml_load_string($responseXml);
		$responseArray = array();
		foreach($response->children() as $field)
			$responseArray[$field->getName()] = (string)$field;
		if($responseArray['result'] == 2) 
			return array('Declined', $responseArray['result-text']); // Declined - use result-text
		else if($responseArray['result'] == 3) {
			return array('Error', $responseArray['result-text'] .'<p>'.htmlentities($requestXml)); // Error - use result-text
		}
		else 
			return $responseArray['form-url'];
	}


	function httpsPost($url, $strRequest) {
		/*$ch=curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1) ;
		curl_setopt($ch, CURLOPT_POSTFIELDS, $strRequest);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array ("Content-Type: text/xml; charset=utf-8"));
		$CURL_SSLVERSION_TLSv1_2 = 6;
		curl_setopt ($ch, CURLOPT_SSLVERSION, $CURL_SSLVERSION_TLSv1_2);
		$result = curl_exec($ch);
		curl_close($ch);*/
		require_once "log-cc.php";
		$postRequest =  '"'.$strRequest.'"';
		$result = shell_exec("python3 cc-bridge.py 'text/xml' $gatewayURL $xmlString SOLVERAS");
		requestWrite("SOLVERAS: $url\n$strRequest\n\nRESPONSE: $result");

		return $result;
	} 
}