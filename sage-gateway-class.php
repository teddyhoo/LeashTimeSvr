<? // sage-gateway-class.php

require_once "abstract-merchant-gateway.php";
class SageGateway extends AbstractMerchantGateway
{
    // property declaration
	private $lastCCErrorId;
	public $ach_enabled = false; 
	
    
	private $sageTransactionCodes = array('Sale'=>'01', 'AuthOnly'=>'02', 'Force/PriorAuthSale'=>'03', 'Void'=>'04', 'Credit'=>'06', 'PriorAuthSale by Reference'=>'11');

	private $sageAuthResponseTemplate = array();

	private $responseCodes = array(
			'A' => 'Approved',
			'E' => 'Front End Error / Non-Approved',
			'X' => 'Gateway End Error / Non-Approved');

	private $cvvCodes = array(
			'M' => 'Match',
			'N' => 'CVV No Match',
			'P' => 'Not Processed',
			'S' => 'Merchant Has Indicated that CVV2 Is Not Present',
			'U' => 'Issuer is not certified and/or has not provided Visa Encryption Keys'
			);
			
	private $riskCodes = array(
			'01' => 'Max Sale Exceeded',
			'02' => 'Min Sale Not Met',
			'03' => '1 Day Volume Exceeded',
			'04' => '1 Day Usage Exceeded',
			'05' => '3 Day Volume Exceeded',
			'06' => '3 Day Usage Exceeded',
			'07' => '15 Day Volume Exceeded',
			'08' => '15 Day Usage Exceeded',
			'09' => '30 Day Volume Exceeded',
			'10' => '30 Day Usage Exceeded',
			'11' => 'Stolen or Lost Card',
			'12' => 'AVS Failure'
			);
			
	private $avsCodes = array(
			'X' => 'Exact; match on address and 9 Digit Zip Code',
			'Y' => 'Yes; match on address and 5 Digit Zip Code',
			'A' => 'Address matches, Zip does not',
			'W' => '9 Digit Zip matches, address does not',
			'Z' => '5 Digit Zip matches, address does not',
			'N' => 'No; neither zip nor address	match',
			'U' => 'Unavailable',
			'R' => 'Retry',
			'E' => 'Error',
			'S' => 'Service Not Supported',
			'' => 'Service Not Supported',
			'D' => 'Match Street Address and Postal Code match for International Transaction',
			'M' => 'Match Street Address and Postal Code match for International Transaction',
			'B' => 'Partial Match Street Address Match for International Transaction. Postal Code not verified due to incompatible formats',
			'P' => 'Partial Match Postal Codes match for International Transaction but street address not verified due to	incompatible formats HTTPS Bankcard Specifications 7',
			'C' => 'No Match Street Address and	Postal Code not verified for International Transaction due to	incompatible formats',
			'I' => 'No Match Address Information not verified by International issuer',
			'G' => 'Not Supported Non-US. Issuer does not participate'
			);
			
	private $errorCodes = array(
			'000000' => 'INTERNAL SERVER ERROR',
			'900000' => 'INVALID T_ORDERNUM',
			'900001' => 'INVALID C_NAME',
			'900002' => 'INVALID C_ADDRESS',
			'900003' => 'INVALID C_CITY',
			'900004' => 'INVALID C_STATE',
			'900005' => 'INVALID C_ZIP',
			'900006' => 'INVALID C_COUNTRY',
			'900007' => 'INVALID C_TELEPHONE',
			'900008' => 'INVALID C_FAX',
			'900009' => 'INVALID C_EMAIL',
			'900010' => 'INVALID C_SHIP_NAME',
			'900011' => 'INVALID C_SHIP_ADDRESS',
			'900012' => 'INVALID C_SHIP_CITY',
			'900013' => 'INVALID C_SHIP_STATE',
			'900014' => 'INVALID C_SHIP-ZIP',
			'900015' => 'INVALID C_SHIP_COUNTRY',
			'900016' => 'INVALID C_CARDNUMBER',
			'900017' => 'INVALID C_EXP',
			'900018' => 'INVALID C_CVV',
			'900019' => 'INVALID T_AMT',
			'900020' => 'INVALID T_CODE',
			'900021' => 'INVALID T_AUTH',
			'900022' => 'INVALID T_REFERENCE',
			'900023' => 'INVALID T_TRACKDATA',
			'900024' => 'INVALID T_TRACKING_NUMBER',
			'900025' => 'INVALID T_CUSTOMER_NUMBER',
			'900026' => 'INVALID T_SHIPPING_COMPANY',
			'900027' => 'INVALID T_RECURRING',
			'900028' => 'INVALID T_RECURRING_TYPE',
			'900029' => 'INVALID T_RECURRING_INTERVAL',
			'900030' => 'INVALID T_RECURRING_INDEFINITE',
			'900031' => 'INVALID T_RECURRING_TIMES_TO_PROCESS',
			'900032' => 'INVALID T_RECURRING_NON_BUSINESS_DAYS',
			'900033' => 'INVALID T_RECURRING_GROUP',
			'900034' => 'INVALID T_RECURRING_START_DATE',
			'900035' => 'INVALID T_PIN',
			'910000' => 'SERVICE NOT ALLOWED',
			'910001' => 'VISA NOT ALLOWED',
			'910002' => 'MASTERCARD NOT ALLOWED',
			'910003' => 'AMEX NOT ALLOWED',
			'910004' => 'DISCOVER NOT ALLOWED',
			'910005' => 'CARD TYPE NOT ALLOWED',
			'911911' => 'SECURITY VIOLATION',
			'920000' => 'ITEM NOT FOUND',
			'920001' => 'CREDIT VOL EXCEEDED',
			'920002' => 'AVS FAILURE',
			'999999' => 'INTERNAL SERVICE ERROR'
		);
		
	function necessaryMerchantInfoKeys() {
		return array(
					'x_login',
					'x_tran_key');
	}

 function merchantInfoLabels() {
	 return array('x_login'=>'Merchant Login', 'x_tran_key'=>'Merchant Transaction Key', 'x_aux_key'=>'');
 }

		
 function __construct() {
		// field,length,start(1-based?)
		$sageAuthResponseFields = <<<ARF
STX,1,1
status,1,2
code,6,3
message,32,9
frontend,2,41
cvv,1,43
avs,1,44
risk,2,45
reference,10,47
fieldsep,1,57
ordernum,-,-
fieldsep2,1,-
recurring,1
fieldsep3,1,-
etx,1,-
ARF;
	foreach(explode("\n", $sageAuthResponseFields) as $i =>$line) {
		$parts = explode(',', trim($line));
		$this->sageAuthResponseTemplate[$parts[0]] = $parts;
	}
 } // END CONSTRUCTOR
 
	function supportsRefund($ccOrACH) {
	 return $ccOrACH == 'ach' ? TRUE : ($ccOrACH == 'cc' ? TRUE : FALSE);
	}
 
 
  function executeTransaction($auth, $cc, $transactionType, $x_amount, $otherData=null) {
		global $ccTestMode, $ccDebug, $duplicateWindow;
		$transactionTypes = array('CREDIT'=>$this->sageTransactionCodes['Credit'], 
															'SALE'=>$this->sageTransactionCodes['Sale'],
															'VALIDATE'=>$this->sageTransactionCodes['AuthOnly']);
		$pairs = array(
			'M_id' => lt_decrypt($auth['x_login']),  // 12 digits
			'M_key' => lt_decrypt($auth['x_tran_key']), // 12 digits
			'T_code' => $transactionTypes[$transactionType],
			'T_amt' => $x_amount, // Up to 15 digits with a decimal point (no dollar symbol)
		);
		if($otherData['transactionId']) $pairs['T_reference'] = $otherData['transactionId'];
		// ADD CREDIT CARD FIELDS HERE
		if(!is_array($ccFields = $this->ccFields($cc))) return array('FAILURE'=>$ccFields);
		foreach($ccFields as $key=>$value)
			$pairs[$key] = urlEncode(trim($value));

		$params = array();
		foreach($pairs as $k=>$v)
			$params[] = "$k=$v";
		set_error_handler('SSLWarningHandler', E_WARNING); // This suppresses: Warning: file_get_contents() [function.file-get-contents]: SSL: fatal protocol error in /var/www/prod/cc-processing-fns.php		
		$gatewayURL = "https://www.sagepayments.net/cgi-bin/eftBankcard.dll?transaction";
		$result = file_get_contents($gatewayURL . "?" . join('&', $params));

		if($ccDebug) {
			echo "<font color=green>"; foreach($params as $p) echo "$p<br>"; echo "</font><p>";
			echo "<font color=blue>".print_r($result, 1)."</font><p>";
			if($result) echo "<p>".$this->labeledResponseHTML($result)."<p>";
		}
		
		$status = $this->responseField($result, 'status');
		if(!$status) return array('FAILURE'=>'No response code');
		if($status == 'A') return $this->responseField($result, 'reference');
		else {
			$this->logCCTransactionError($cc['clientptr'], 'tblcreditcard', $cc['ccid'], $result);
			return array($result);  
		}
		
	}
	
	function responseField($result, $field) {
		$field = $this->sageAuthResponseTemplate[$field];
		return substr($result, $field[2]-1, $field[1]);
	}
	
	function ccFields($cc) {
		$email = fetchRow0Col0("SELECT email FROM tblclient WHERE clientid = {$cc['clientptr']} LIMIT 1");
		if(!$email) return "Client email is required for credit card transactions.";
		return array(
			'C_name' => "{$cc['x_first_name']} {$cc['x_last_name']}",
			'C_address' => $cc['x_address'],
			'C_city' => $cc['x_city'],
			'C_state' => $cc['x_state'],
			'C_zip' => $cc['x_zip'],
			'C_country' => $cc['x_country'],
			'C_email' => $email,
			'C_cardnumber' => $cc['x_card_num'],
			'C_exp' => date('my', strtotime($cc['x_exp_date'])),
			'C_cvv' => $cc['x_card_code']
			);
	}

	
	
	function labeledResponseHTML($result) {
		$i=0;
		foreach($this->sageAuthResponseTemplate as $key => $field) {
			if($key == 'fieldsep') break;
			$v = substr($result, $field[2]-1, $field[1]);
			if($key == 'status') $v = $this->responseCodes[$v];
			else if($key == 'cvv') $v = $this->cvvCodes[$v];
			else if($key == 'avs') $v = $this->avsCodes[$v];
			else if($key == 'risk') $v = $this->riskCodes[$v];
			$style = strlen($v) ? '' : "style='color:gray;'";
			$s .= "<SPAN $style>".sprintf("%02d",$i+1).". $key:</SPAN> $v<br>\n";
			$i++;
		}
		return $s;
	}

	function logCCTransactionError($clientid, $sourcetable, $ccid, $result) {
		$transactionid = $this->responseField($result, 'reference');
		if(!$transactionid) $transactionid = '-';
		insertTable('tblcreditcarderror', 
							array('transactionid' => $this->responseField($result, 'reference'), 
								'time' => date('Y-m-d h:i:s'),
								'clientptr' => $clientid,
								'ccptr' => $ccid,
								'sourcetable' => $sourcetable,
								'response' => ($result ? $result : sqlVal("''")))

							);
		$this->lastCCErrorId = mysql_insert_id();
	}						
	
	function ccErrorLogMessage($result, $amount=null) {  // SAGE result does not include amount
		if(is_array($result) && $result['FAILURE']) return 'Error-'.$result['FAILURE'];
		if(is_array($result)) $result = current($result);
		$msg = 'Error-'.trim($this->responseField($result, 'message'));
		return $msg."|Amount:$amount|Trans:".$this->responseField($result, 'reference')."|Gate:SAGE|ErrorID:".$this->lastCCErrorId;
	}
	
	function ccLastMessage($result) {
		if(is_array($result)) $result = current($result);
//print_r($result);		
		$message = $this->responseField($result, 'message');
		
		/*if($this->responseField($result, 'status') != 'A') {
			
			$v = $this->responseField($result, 'cvv');
			if($v  && $v != 'M') 	$message .= ' '.$this->cvvCodes[$v];
			
			$v = $this->responseField($result, 'avs');
			if($v && !in_array($v, array('X', 'Y', 'P'))) 
				$message .= ' '.$this->avsResponses[$v];
				
			$v = $this->responseField($result, 'risk');
			if($v != 2  && $v !== '') 
				$message .= ' '.$this->riskCodes[$v];
		}*/
		
		return $message;
	}
	
	function ccTransactionId($result) {
		return 	$this->responseField($result, 'reference');
	}
}




