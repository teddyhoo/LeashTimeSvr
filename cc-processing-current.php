<? //cc-processing-fns.php

// AUTH_CAPTURE (default), AUTH_ONLY, CAPTURE_ONLY, CREDIT, PRIOR_AUTH_CAPTURE, VOID
// RIGHTS: cc - credit card processing permission, cm - credit card info management permission
// 

require_once "encryption.php";

$ccTestMode = FALSE; // $db == 'dogslife';


function ccDEVTestMode() { global $db; return FALSE && staffOnlyTEST(); /*&& $db == 'dogslife';*/}

$ccDebug = false;
/* TEST SERVER CREDS
login: beIIsFR3T+sCD6SQDHzNbVY1dhmiq8w1knVLh4yf9e0=
trans id: 3qJzs31pG5ihGUVdc8BzbkGRaNo3jqiHm9ICkSD8zgI=
*/
$greatestCCPayment = 9999;
$ccExpirationMinutes = 15;
$duplicateWindow = 120; // -1; // 120 // don't allow a duplicate transaction within this many seconds

function setCreditCardIsRequiredIfNecessary() {
	if($_SESSION['preferences']['clientCreditCardRequired'] && ($clientid = $_SESSION["clientid"])) {
		if(!getPrimaryPaySource($clientid)) $_SESSION["creditCardIsRequired"] = 1;
		else unset($_SESSION["creditCardIsRequired"]);
	}
}
function merchantInfoSupplied() {
	$auth = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property = 'ccGateway' OR property = 'x_login' OR property = 'x_tran_key' OR property = 'x_aux_key'");
	//print_r(getGatewayObject($auth['ccGateway']));
	if(!$auth['ccGateway']) return;
	else {
		$gateway = getGatewayObject($auth['ccGateway']);	
		foreach($gateway->necessaryMerchantInfoKeys() as $neededKey) {
			if(!$auth[$neededKey]) return;
//echo "$neededKey: ".lt_decrypt($auth[$neededKey]).'<br>';
		}
	}
	return $auth;	
	//OLD if($auth['ccGateway'] && $auth['x_login'] && $auth['x_tran_key']) return $auth;
}
function getAcceptedCreditCardTypes($preference=null) {
	return explode(',', (string)($preference ? $preference : $_SESSION['preferences']['ccAcceptedList']));
}
function getAcceptedCreditCardTypeOptions($nullOption, $preference=null) {
	$options = $nullOption ? array($nullOption => '') : array();
	$types = getAcceptedCreditCardTypes($preference);
	foreach($types as $type)
		$options[$type] = $type;
	if(!$types) $options['No Credit Card Types Defined'] = $type;
	return $options;
}
// Return an array on failure or a transaction id on success
function authorizeCC($cc) {
	return executeCCTransaction(1, $cc, 'VALIDATE');
}
// Return an array on failure or a transaction id on success
function makeERefund($x_amount, $cc, $transactionid, $paymentAmount=null) {
	// DO NOT TRY VOID UNLESS $x_amount == $paymentAmount
	if($x_amount == $paymentAmount) {// !staffOnlyTEST() || 
		$result = executeCCTransaction($x_amount, $cc, 'VOID', $transactionid);
if(mattOnlyTEST()) logChange(999, 'test', 'z', "VOID result for transaction: {$transactionid}[{$paymentAmount} - {$x_amount}]");
	}
//if(mattOnlyTEST()) { logLongError("VOID [$x_amount == $paymentAmount] result: ".print_r($result, 1)); }
	$gatewayObject = getGatewayObject($_SESSION['preferences']['ccGateway']);
	if((!$result || is_array($result)) && $gatewayObject->supportsRefund($cc['ccid'] ? 'cc' : 'ach')) {
		//logChange(999, 'test', 'z', print_r($result,1));
		$result = executeCCTransaction($x_amount, $cc, 'CREDIT', $transactionid);
//if(mattOnlyTEST()) { logLongError("CREDIT [$transactionid] result: ".print_r($result, 1)); }
		return $result;
	}
	else return $result;
}

// Return an array on failure or a transaction id on success
function makeEPayment($x_amount, $paymentSource=null, $noLoginPayment=false) {  // $paymentSource is a CC or ACH
	return executeCCTransaction($x_amount, $paymentSource, 'SALE', $transactionId=null, $noLoginPayment);
}

function getGatewayObject($gatewayName) {

	if($gatewayName == 'Authorize.net')  {
		require_once "authorizenet-gateway-class.php";
		$gatewayObject = new AuthorizeNetGateway();
	}
	else if($gatewayName == 'TransFirstTransactionExpress') { 
		require_once "transfirst-txp-gateway-class.php";
		$gatewayObject = new TransFirstTXPGateway();
	}
	else if($gatewayName == 'Solveras') { //'Solveras'] = 'TestCCGateway'
		require_once "solveras-gateway-class.php";
		$gatewayObject = new SolverasGateway();
	}
	else if($gatewayName == 'TransFirstV1') { 
		require_once "transfirst-nmi-txp-gateway-class.php";
		$gatewayObject = new TransFirstNMITXPGateway();
	}
	else if($gatewayName == 'SAGE' || $gatewayName == 'Sage') { // why, matt why?
		require_once "sage-gateway-class.php";
		$gatewayObject = new SageGateway();
	}
	else if($gatewayName == 'TestCCGateway') { //'Test CC Gateway'] = 'TestCCGateway'
		require_once "test-gateway-class.php";
		$gatewayObject = new TestCCGateway();
	}
	else if($gatewayName == 'Authorize.netTEST2') { //'Test CC Gateway'] = 'TestCCGateway'
		require_once "authorizenet-gateway-class2.php";
		$gatewayObject = new AuthorizeNetGateway2();
	}
	return $gatewayObject;
}

function gatewayIsNMI($gateway=null) { // gateway uses the NMI API for ach or cc
	if(!$gateway) $gateway = $_SESSION['preferences']['ccGateway'];
	return in_array($gateway, array('Solveras', 'TransFirstV1'));
}

// Return an array on failure or a transaction id on success
function executeCCTransaction($x_amount, $cc=null, $transactionType, $transactionId=null, $noLoginPayment=false) {
	global $gatewayObject;
	if(is_array($expiration = expireCCAuthorization($noLoginPayment))) return $expiration;
	if(!$noLoginPayment) $locked = locked('*cc'); // must have credit card processing permission
	$auth = merchantInfoSupplied();
	if(!$auth)
		return array('FAILURE'=>'Incomplete merchant authorization.');
	if(!$x_amount)
		return array('FAILURE'=>'Amount not supplied.');
	$gatewayObject = getGatewayObject($auth['ccGateway']);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r(lt_decrypt($auth['x_tran_key']));exit;}
	return $gatewayObject->executeTransaction($auth, $cc, $transactionType, $x_amount, array('transactionId' => $transactionId));
}


function ccErrorLogMessage($result, $amount=null) {
	// called after a transaction is attempted
	global $gatewayObject;
	return $gatewayObject->ccErrorLogMessage($result, $amount);
}

function ccLastMessage($result) {
	// called after a transaction is attempted
	global $gatewayObject;
	return $gatewayObject->ccLastMessage($result);
}

function ccTransactionId($result) {
	global $gatewayObject;
	return $gatewayObject->ccTransactionId($result);
}



function SSLWarningHandler($errno, $errstr) {
	// This suppresses the spurious: 
	// Warning: file_get_contents() [function.file-get-contents]: SSL: fatal protocol error in /var/www/prod/cc-processing-fns.php
	if(strpos($errstr, 'SSL: fatal protocol error')) return true; // ignore
}	

function merchantAuthorizationProblem() {
	if(!merchantInfoSupplied())
		return 'Incomplete merchant authorization information';
}

$ccDbFields = explode(',', 'ccid,x_card_num,x_card_code,last4,x_exp_date,company,active,clientptr,useclientinfo,autopay,vaultid,gateway');
$achDbFields = explode(',', 'acctid,active,abacode,bank,acctnum,last4,acctname,accttype,acctentitytype,autopay,clientptr,vaultid,gateway,encrypted'); //useclientinfo,

// Return an error string on failure or a cc array on success
function getCC($ccid, $clientid, $inactiveAlso=false) {
	$inactiveAlso = $inactiveAlso ? '' : 'AND active';
	if(is_array($expiration = expireCCAuthorization())) return $expiration['FAILURE'];
	$cc = fetchFirstAssoc(
		"SELECT tblcreditcard.*,  tblcreditcardinfo.*
			FROM tblcreditcard
			LEFT JOIN tblcreditcardinfo ON ccptr = ccid
			WHERE ccid = $ccid AND clientptr = $clientid $inactiveAlso");
	if(!$cc) return null;
	$cc['x_card_num'] = lt_decrypt($cc['x_card_num']);
	$cc['x_card_code'] = lt_decrypt($cc['x_card_code']);
	if($cc['useclientinfo']) fetchClientInfo($cc);

	return $cc;
}

function fetchClientInfo(&$cc) {
	require_once "client-fns.php";
	$clientDetails = getOneClientsDetails($cc['clientptr'], array('addressparts', 'lname', 'fname', 'phone'));
	
	/*foreach(explode(',', 'homephone,cellphone,workphone,cellphone2') as $fieldName) {
		if($clientDetails[$fieldName]) {
			$phone = $clientDetails[$fieldName];
			if(substr($phone, 0, 1) == '*') $phone = substr($phone, 1);
			$cc['x_phone'] = $phone;
			break;
		}
	}*/
	
	require_once "field-utils.php";
	if($primaryPhoneNumber = primaryPhoneNumber($clientDetails))
		$cc['x_phone'] = preg_replace("/[^0-9]/", "", $primaryPhoneNumber); // allow only numeric



	$address = $clientDetails['street1'] ? $clientDetails['street1'] : '';
	if($clientDetails['street2'])  
		$address = $address ? "$address ".$clientDetails['street2'] : $clientDetails['street2'];
	$cc['x_address'] = $address;
	foreach(explode(',', 'zip,city,state') as $fieldName)
		$cc["x_$fieldName"] = $clientDetails[$fieldName];
	$cc['x_company'] = '';
	$cc['x_country'] = 'USA';
	$cc['x_first_name'] = $clientDetails['fname'];
	$cc['x_last_name'] = $clientDetails['lname'];
}

function getActivePaySource($clientid, $specificSourceId=null) {
	$ref = getActiveClientCC($clientid, true, $specificSourceId);
	if(!$ref && achEnabled()) $ref = getActiveClientACH($clientid, true, $specificSourceId);
	return $ref;
}

function getPrimaryPaySource($clientid) {
	$ref = getActiveClientCC($clientid, 'primary');
	if(!$ref && achEnabled()) $ref = getActiveClientACH($clientid, 'primary');
	return $ref;
}

function getPrimaryPaySourceTypeAndID($clientid) {
	$cc = fetchFirstAssoc(
			"SELECT ccid, company, autopay 
				FROM tblcreditcard 
				WHERE clientptr = $clientid AND active=1 AND primarypaysource = 1");
	if($cc) return $cc;
	return fetchFirstAssoc(
			"SELECT acctid 
				FROM tblecheckacct 
				WHERE clientptr = $clientid AND active AND primarypaysource = 1");
}

function getClearPrimaryPaySource($clientid) {
	$ref = getClearCC($clientid, 'primary');
	if ($ref != null) {
		return $ref;
	} else {
		return null;
	}

	//if(!$ref && achEnabled()) $ref = getClearACH($clientid, 'primary');
	return $ref;
}

function primaryPaySourceProblem($clientidOrSourceArray) {
	// no source? no problem!
	$src = is_array($clientidOrSourceArray) ? $clientidOrSourceArray : getClearPrimaryPaySource($clientidOrSourceArray);
	if($src) {
		$cardGatewayIsNMI = gatewayIsNMI($src['gateway']);
		$currentGatewayIsNMI = gatewayIsNMI($_SESSION['preferences']['ccGateway']);
		if($src['acctid']) {
			if(gatewayConflict($src))
				return "ACH info for wrong gateway";
		}
		else {
			if(isAnExpiredCard($cc))
				return "card expired";
			else {
				if($cardGatewayIsNMI != $currentGatewayIsNMI)
					return "Credit Card info for wrong gateway";
			}
		}
	}
}


function isAnExpiredCard($cc) {
	if($cc['ccid']) {
		$expDate = date('Y-m-t', strtotime($cc['x_exp_date']));
		return strcmp($expDate, date('Y-m-d')) < 0;
	}
}

function achEnabled() {
	if(!$_SESSION['preferences']['ccGateway'] 
			|| !in_array('tblecheckacct', fetchCol0("SHOW TABLES"))
		) return false;
	if($gateway = getGatewayObject($_SESSION['preferences']['ccGateway']))
		return $gateway->supportsACH() && $_SESSION['preferences']['gatewayOfferACH'];
/*	return 
		in_array('tblecheckacct', fetchCol0("SHOW TABLES")) 
		&& gatewayIsNMI($_SESSION['preferences']['ccGateway'])
		&& $_SESSION['preferences']['gatewayOfferACH'];*/
}

function setPrimaryPaySource($source) {
	if($source['acctid']) {
		updateTable('tblcreditcard', array('primarypaysource'=>0), "clientptr = {$source['clientptr']}", 1);
		if(achEnabled()) {
			updateTable('tblecheckacct', array('active'=>0, 'primarypaysource'=>0), 
										"clientptr = {$source['clientptr']} AND acctid != {$source['acctid']}", 1);
			updateTable('tblecheckacct', array('active'=>1, 'primarypaysource'=>1), 
										"clientptr = {$source['clientptr']} AND acctid = {$source['acctid']}", 1);
		}
	}
	else if($source['ccid']) {
		if(achEnabled()) updateTable('tblecheckacct', array('primarypaysource'=>0), "clientptr = {$source['clientptr']}", 1);		
		updateTable('tblcreditcard', array('active'=>0, 'primarypaysource'=>0), 
									"clientptr = {$source['clientptr']} AND ccid != {$source['ccid']}", 1);
		updateTable('tblcreditcard', array('active'=>1, 'primarypaysource'=>1), 
									"clientptr = {$source['clientptr']} AND ccid = {$source['ccid']}", 1);
	}
}

function getPaymentSourceFromChangeLog($msg) {  // $msg is from tbchangelog
	$table = $msg['itemtable'] == 'ccpayment' ? 'tblcreditcard' : 'tblecheckacct';
	$idfield = $table == 'tblecheckacct' ? 'acctid' : 'ccid';
	return fetchFirstAssoc(
		"SELECT $table.*, CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(', ', lname, fname) as sortclient, userid as clientuserid
		FROM $table
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE $idfield = {$msg['itemptr']} LIMIT 1");
}

function clearSourceDescription($achOrCC) {
	$clear = clearSource($achOrCC);
	if($clear['acctnum']) return "ACH: {$clear['abacode']} / {$clear['acctnum']}";
	else return "{$clear['company']} ****{$clear['last4']} Exp: ".date('m/y', strtotime($clear['x_exp_date']));
}

function clearSource($achOrCC) {
	if($achOrCC['ccid'])
		return fetchFirstAssoc(
				"SELECT company, last4, x_exp_date, autopay, clientptr, primarypaysource, gateway, ccid 
					FROM tblcreditcard 
				WHERE ccid = {$achOrCC['ccid']}");
	else {
		$ach = fetchFirstAssoc(
				"SELECT abacode, bank, acctnum, acctname, accttype, acctentitytype, autopay, clientptr, encrypted, primarypaysource, gateway, last4, acctid
					FROM tblecheckacct 
					WHERE acctid = {$achOrCC['acctid']}");
		if($ach['encrypted'])
			$ach['acctnum'] = maskedAcctNum($ach['acctnum']);
		return $ach;
	}
}

function mattGetACH($ach) {
	$ach = fetchFirstAssoc(
			"SELECT abacode, bank, acctnum, acctname, accttype, acctentitytype, autopay, clientptr, encrypted, primarypaysource, gateway, last4, acctid
				FROM tblecheckacct 
				WHERE acctid = {$ach['acctid']}");
	if($ach['encrypted'])
		$ach['acctnum'] = lt_decrypt($ach['acctnum']);
	return $ach;
}
	

function parseChangeLogPaymentNote($note) { // return array with details of credit card payment from the change log
	$result = array();
	if(($dash = strpos($note, '-')) !== FALSE) {
		$parts[0] = substr($note, 0, $dash);
		$parts[1] = substr($note, $dash+1, strlen($note)-($dash+1));
	}
	else $parts[0] = $note;
	$result['status'] = $parts[0];
	$parts = explode('|', $parts[1]);
	if($result['status'] == 'Approved') {
		$result['amount'] = $parts[0];
		$result['transaction'] = $parts[1];
	}
	else {
		$result['reason'] = $parts[0];
		// Declined-This transaction has been declined.|Amount:2.00|Trans:3574472823|Gate:Authorize.net|ErrorID:172
		// 2|1|2|This transaction has been declined.|000000|U|3574472823|||2.00|CC|auth_capture||Ted|Hooban||22085 Chelsy Paige Sq|ASHBURN|VA|20148|USA|||||||||||||||||E88631C92BF364DC7FCA08CBDD03B36E|N||||||||||||XXXX5299|MasterCard||||||||||||||||
		for($i=1;$i<count($parts);$i++) {
			if(strpos($parts[$i], 'Gate:') === 0) $gateway = substr($parts[$i], strlen('Gate:'));
			else if(strpos($parts[$i], 'ErrorID:') === 0) {
				$ccErrordId = substr($parts[$i], strlen('ErrorID:'));
				$result['errorid'] = $ccErrordId;
				if($gateway && $error = fetchRow0Col0("SELECT response FROM tblcreditcarderror WHERE errid = '$ccErrordId' LIMIT 1")) {
					if($gateway = getGatewayObject($gateway))
						$message = $gateway->ccLastMessage($error);
					if($message) $result['title'] = $message;
				}
			}
			
			else if(strpos($parts[$i], 'Trans:') === 0) $result['transaction'] = substr($parts[$i], strlen('Trans:'));
			else if(strpos($parts[$i], 'Amount:') === 0) $result['amount'] = substr($parts[$i], strlen('Amount:'));
		}
	}
	return $result;
}


// ====ACH-SPECIFIC===========================================================================================

function fetchAllClientACHs($clientid, $filter='', $mode=null) {
	if(!achEnabled()) return array();
	$achs = fetchAssociationsKeyedBy("SELECT * FROM tblecheckacct WHERE clientptr = $clientid $filter", 'acctid');
	if($mode == 'display')
	  foreach($achs as $i => $ach)
			if($ach['encrypted'])
				$achs[$i]['acctnum'] = maskedAcctNum($ach['acctnum']);
	return $achs;
}

// Return an error string on failure or a cc array on success
function getActiveClientACH($clientid, $primaryToo=false, $specificACH=null) {
	if(is_array($expiration = expireCCAuthorization())) return $expiration['FAILURE'];
	$primaryToo = $primaryToo ? "AND primarypaysource = 1" : '';
	$specificACH = $specificACH ? " AND acctid = $specificACH" : '';
	$ach = fetchFirstAssoc(
			"SELECT tblecheckacct.*, tblecheckacctinfo.* 
				FROM tblecheckacct 
				LEFT JOIN tblecheckacctinfo ON acctptr = acctid
				WHERE clientptr = $clientid AND active $primaryToo $specificACH");
	if(!$ach) return null;
	$ach['acctnum'] = $ach['encrypted'] ? lt_decrypt($ach['acctnum']) : $ach['acctnum'];
//print_r($ach);	
	//if($ach['useclientinfo']) fetchClientInfo($ach);
	return $ach;
}

function getClearACH($clientid, $primaryToo=false) {
	
	$primaryToo = $primaryToo ? "AND primarypaysource = 1" : '';
	$ach = fetchFirstAssoc(
			"SELECT acctid, vaultid, abacode, bank, acctnum, acctname, accttype, acctentitytype, autopay, clientptr, encrypted, primarypaysource, gateway, last4, acctid
				FROM tblecheckacct 
				WHERE clientptr = $clientid AND active=1 $primaryToo");
	if(!$ach) return;
//	if(FALSE && $_SESSION['preferences']['ccGateway'] != $ach['gateway']) {  // ach should be usable in any non-NMI
	if(gatewayConflict($ach)) {
		$ach['invalid'] = 1;
		$ach['acctnum'] = "info not valid for your gateway {$_SESSION['preferences']['ccgateway']}";
	}
	else if($ach['encrypted'])
		$ach['acctnum'] = maskedAcctNum($ach['acctnum']);
	return $ach;
}

function gatewayConflict($paymentSource) {
	if($paymentSource['acctid']) {  // ach info stored in Solveras is no longer accessible
//if(mattOnlyTEST()) echo "BANGBANG!";	
		return $paymentSource['vaultid'];
	}
	$nmiGateways = gatewayIsNMI($_SESSION['preferences']['ccGateway']) ? 1 : 0;
	$nmiGateways += gatewayIsNMI($paymentSource['gateway']) ? 1 : 0;
	return $nmiGateways == 1; // if one of the gateways is NMI and the other is not...
}

function maskedAcctNum($num) {
	$num = lt_decrypt($num);
	for($i=0; $i < strlen($num); $i++)
		$out[$i] = ($i < strlen($num)-4 ? '*' : $num[$i]);
	return $out ? join('', $out) : '';
}

function getClearACHs($clientids, $primaryToo=false) {
	$primaryToo = $primaryToo ? "AND primarypaysource = 1" : '';
	$achs = !$clientids 
			? array() 
			: fetchAssociationsKeyedBy(
			"SELECT abacode, bank, acctnum, acctname, accttype, acctentitytype, autopay, clientptr, encrypted, primarypaysource, gateway
				FROM tblecheckacct 
				WHERE clientptr IN (".join(',', $clientids).") AND active=1 $primaryToo", 'clientptr');
	foreach($achs as $i => $ach)
		if($ach['encrypted']) 
			$achs[$i]['acctnum'] = maskedAcctNum($ach['acctnum']);
}


// Return an array on failure or a cc id on success
function saveNewACH($data, $changeNote=null) {
	global $ccTestMode, $achDbFields;
	if(is_array($expiration = expireCCAuthorization())) return $expiration;
	foreach($data as $k=>$v) 
		if(!in_array($k, $achDbFields)) 
			unset($data[$k]);
	$data['active'] = 1;
	//$data['useclientinfo'] = $data['useclientinfo'] ? 1 : 0;
	$data['autopay'] = $data['autopay'] ? 1 : 0;
	$data['last4'] = substr($data['acctnum'], -4);
	$data['encrypted'] = 1;
	$data['gateway'] = $_SESSION['preferences']['ccGateway'];
	$data['acctnum'] = lt_encrypt($data['acctnum']);
//print_r($data);	
//echo "<p>";print_r($data);echo "<P>".lt_decrypt($data['x_card_code']);exit;	
	addCreationFields($data);
	$id = insertTable('tblecheckacct', $data, 1); //tblecheckacctinfo
	$data['acctid'] = $id;
	setPrimaryPaySource($data);
	$changeNote = $changeNote ? $changeNote : '.';
	logChange($id, 'tblecheckacct', 'c', $changeNote);
	return $id;
}

/*function saveACHInfo($data) {
	global $ccTestMode;
	$data['ccptr'] = $data['ccid'];
	$fields = explode(',', 'ccptr,x_first_name,x_last_name,x_company,x_address,x_city,x_state,x_zip,x_country,x_phone');
	foreach($data as $k => $v)
		if(!in_array($k, $fields)) 
			unset($data[$k]);
	$id = insertTable('tblcreditcardinfo', $data, $ccTestMode);
}
*/
// Return an array on failure or true on success
function saveACH($data) {
	return updateTable('tblecheckacct', array('autopay'=>($data['autopay'] ? '1' : '0')), "acctid = '{$data['acctid']}'", mattOnlyTEST());
}

function dropACH($acctid) {
	global $ccTestMode;$ccTestMode=1;
	if($acctid && updateTable('tblecheckacct', array('active'=>'0', 'primarypaysource'=>0), "acctid = $acctid", $ccTestMode))
		logChange($acctid, 'tblecheckacct', 'm', 'dropped.');
}


// ====CC-SPECIFIC===========================================================================================
function fetchAllClientCCs($clientid, $filter='') {
	return fetchAssociationsKeyedBy("SELECT * FROM tblcreditcard WHERE clientptr = $clientid $filter", 'ccid');
}

function fetchAllClientAdHocCCs($clientid, $filter='') {
	return fetchAssociationsKeyedBy("SELECT * FROM tblcreditcardadhoc WHERE clientptr = $clientid $filter", 'ccid');
}

// Return an error string on failure or a cc array on success
function getActiveClientCC($clientid, $primaryToo=false, $specificCC=null) {
	if(is_array($expiration = expireCCAuthorization())) return $expiration['FAILURE'];
	$primaryToo = $primaryToo ? "AND primarypaysource = 1" : '';
	$specificCC = $specificCC ? " AND ccid = $specificCC" : '';
	$cc = fetchFirstAssoc(
			"SELECT tblcreditcard.*,  tblcreditcardinfo.* 
				FROM tblcreditcard 
				LEFT JOIN tblcreditcardinfo ON ccptr = ccid
				WHERE clientptr = $clientid AND active=1 $primaryToo $specificCC");
	if(!$cc) return null;
	$cc['x_card_num'] = lt_decrypt($cc['x_card_num']);
//print_r($cc);	
	$cc['x_card_code'] = lt_decrypt($cc['x_card_code']);
//echo "<p>";print_r($cc);exit;	
	if($cc['useclientinfo']) fetchClientInfo($cc);
	return $cc;
}

function getClearCC($clientid, $primaryToo=false) {
	$primaryToo = $primaryToo ? "AND primarypaysource = 1" : '';
	$sql = "SELECT company, last4, x_exp_date, autopay, clientptr, primarypaysource, gateway, ccid 
				FROM tblcreditcard 
				WHERE clientptr = $clientid AND active=1 $primaryToo";
	$query = fetchFirstAssoc($sql);
	if ($query == null) 
		echo "QUERY: ZIPPO<p>";
	else {
		echo "QUERY: $query";
	}
	return $query;
}

function getClearCCs($clientids, $primaryToo=false) {
	$primaryToo = $primaryToo ? "AND primarypaysource = 1" : '';
	if($_SESSION['preferences']['ccGateway'] == 'Solveras')
		$solverasOnly = "AND gateway = 'Solveras'";
	if(!$clientids) return array(); 
	else $ccs = fetchAssociationsKeyedBy(
			"SELECT ccid, company, last4, x_exp_date, autopay, clientptr, primarypaysource, gateway 
				FROM tblcreditcard 
				WHERE clientptr IN (".join(',', $clientids).") AND active=1 $primaryToo $solverasOnly", 'clientptr');
	if(achEnabled()) {
		$achs = fetchAssociationsKeyedBy(
			"SELECT acctid, last4, autopay, clientptr, primarypaysource, gateway 
				FROM tblecheckacct 
				WHERE clientptr IN (".join(',', $clientids).") AND active=1 $primaryToo $solverasOnly", 'clientptr');
		foreach($achs as $clientptr =>  $ach) $ccs[$clientptr] = $ach;
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($ccs);}
	
	return $ccs;
}

// Return an array on failure or a cc id on success
function saveNewCC($data, $changeNote=null) {
	global $ccTestMode, $ccDbFields;
	if(is_array($expiration = expireCCAuthorization())) return $expiration;
	foreach($data as $k=>$v) 
		if(!in_array($k, $ccDbFields)) 
			unset($data[$k]);
	$data['active'] = 1;
	$data['active'] = 1;
	$data['useclientinfo'] = $data['useclientinfo'] ? 1 : 0;
	$data['autopay'] = $data['autopay'] ? 1 : 0;
	$data['last4'] = substr($data['x_card_num'], -4);
	$data['x_card_num'] = lt_encrypt($data['x_card_num']);
//print_r($data);	
	$data['x_card_code'] = lt_encrypt($data['x_card_code']);
//echo "<p>";print_r($data);echo "<P>".lt_decrypt($data['x_card_code']);exit;	
	$data['x_exp_date'] = date('Y-m-1', strtotime($data['x_exp_date']));
	addCreationFields($data);
	$id = insertTable('tblcreditcard', $data, $ccTestMode);
	$data['ccid'] = $id;
	setPrimaryPaySource($data);
	$changeNote = $changeNote ? $changeNote : '.';
	logChange($id, 'tblcreditcard', 'c', $changeNote);
	return $id;
}

function saveNewAdHocCC($data, $changeNote=null) {
	$data['last4'] = substr($data['x_card_num'], -4);
	$data['x_exp_date'] = date('Y-m-01', strtotime($data['x_exp_date']));
	addCreationFields($data);
	$allFields =explode(',', 'ccid,last4,x_exp_date,company,clientptr,created,modified,createdby,modifiedby,gateway');
	foreach($data as $k =>$v) 
		if(!in_array($k, $allFields)) unset($data[$k]);
	$data['createdby'] = $data['createdby'] ? $data['createdby'] : '0';
	$id = insertTable('tblcreditcardadhoc', $data, 1);
	logChange($id, 'tblcreditcardadhoc', 'c', ($changeNote ? $changeNote : '.'));
	return $id;
}

function saveCCInfo($data) {
	global $ccTestMode;
	$data['ccptr'] = $data['ccid'];
	$fields = explode(',', 'ccptr,x_first_name,x_last_name,x_company,x_address,x_city,x_state,x_zip,x_country,x_phone');
	foreach($data as $k => $v)
		if(!in_array($k, $fields)) 
			unset($data[$k]);
	$id = insertTable('tblcreditcardinfo', $data, $ccTestMode);

}

function saveACHInfo($data) {
	$data['acctptr'] = $data['acctid'];
//if(mattOnlyTEST()) {print_r($data);	exit;}
	$fields = explode(',', 'acctptr,x_company,x_address,x_city,x_state,x_zip,x_country,x_phone');
	foreach($data as $k => $v)
		if(!in_array($k, $fields)) 
			unset($data[$k]);
	$id = insertTable('tblecheckacctinfo', $data, 1);

}

// Return an array on failure or true on success
function saveCC($data) {
	// allow only x_exp_date and active to change
	global $ccTestMode, $ccDbFields;
	if(is_array($expiration = expireCCAuthorization())) return $expiration;
	foreach($data as $k=>$v) 
		if(!in_array($k, $ccDbFields)) 
			unset($data[$k]);
	$ccid = $data['ccid'];
	unset($data['ccid']);
	unset($data['last4']);
	unset($data['x_card_num']);
	unset($data['x_card_code']);
	unset($data['clientptr']);
	$data['active'] = $data['active'] ? 1 : 0;
	$data['autopay'] = $data['autopay'] ? 1 : 0;
	$data['useclientinfo'] = $data['useclientinfo'] ? 1 : 0;
	$data['x_exp_date'] = date('Y-m-1', strtotime($data['x_exp_date']));
	addModificationFields($data);
	logChange($ccid, 'tblcreditcard', 'm', '.');
	return updateTable('tblcreditcard', $data, "ccid = $ccid", $ccTestMode);
}

function dropCC($ccid) {
	global $ccTestMode;//$ccTestMode=1;
	if(updateTable('tblcreditcard', array('active'=>'0', 'primarypaysource'=>0), "ccid = $ccid", $ccTestMode))
		logChange($ccid, 'tblcreditcard', 'm', 'dropped.');
}

// Return an array on failure or a timeout (strtotime) on success
function expireCCAuthorization($noLogin=false) {
	global $ccExpirationMinutes;
	if(!$noLogin && !staffOnlyTEST() && userRole() != 'c') { 
		if(!isset($_SESSION['ccTimeout'])) return array('FAILURE'=>'NOLOGIN');
		if(time() - $_SESSION['ccTimeout'] > $ccExpirationMinutes * 60) return array('FAILURE'=>'TIMEOUT');
	}
	$_SESSION['ccTimeout'] = time() + $ccExpirationMinutes * 60;
	return $_SESSION['ccTimeout'];
}

function ccLogin($password) {
	require_once "login-fns.php";
	if(!login($_SESSION["auth_login_id"], $password)) {
		session_unset();
		session_destroy();
		return false;
	}
	else $_SESSION['ccTimeout'] = time() + $ccExpirationMinutes * 60;
	return $_SESSION['ccTimeout'];
}

function expirationDate($date) {
	return $date ? date('m/Y', strtotime($date)) : '';
}

function shortExpirationDate($date) {
	return $date ? date('m/y', strtotime($date)) : '';
}

function guessMaskedCreditCardCompany($num) { // used where?
  $prefixes = array(3=>'American Express', 4=>'Visa', 5=>'MasterCard', 6=>'Discover');
 	return $prefixes[$num[0]] ? $prefixes[$num[0]] : 'unknown';
}

function guessCreditCardCompany($cardnum) {
/*
		* Visa:  All Visa card numbers start with a 4. New cards have 16 digits. Old cards have 13.
    * MasterCard:  All MasterCard numbers start with the numbers 51 through 55. All have 16 digits.
    * American Express: ^3[47][0-9]{13}$ American Express card numbers start with 34 or 37 and have 15 digits.
    * Diners Club: ^3(?:0[0-5]|[68][0-9])[0-9]{11}$ Diners Club card numbers begin with 300 through 305, 36 or 38. All have 14 digits. There are Diners Club cards that begin with 5 and have 16 digits. These are a joint venture between Diners Club and MasterCard, and should be processed like a MasterCard.
    * Discover: ^6(?:011|5[0-9]{2})[0-9]{12}$ Discover card numbers begin with 6011 or 65. All have 16 digits.
    * JCB: ^(?:2131|1800|35\d{3})\d{11}$ JCB cards beginning with 2131 or 1800 have 15 digits. JCB cards beginning with 35 have 16 digits. 	
 */
 $objRegExp = '/^4[0-9]{12}(?:[0-9]{3})?$/';
 if(preg_match($objRegExp, $cardnum)) return 'Visa';
 $objRegExp = '/^5[1-5][0-9]{14}$/';
 if(preg_match($objRegExp, $cardnum)) return 'MasterCard';
 $objRegExp = '/^3[47][0-9]{13}$/';
 if(preg_match($objRegExp, $cardnum)) return 'American Express';
 $objRegExp = '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/';
 if(preg_match($objRegExp, $cardnum)) return 'Diners Club';
 $objRegExp = '/^6(?:011|5[0-9]{2})[0-9]{12}$/';
 if(preg_match($objRegExp, $cardnum)) return 'Discover';
 $objRegExp = '/^(?:2131|1800|35\d{3})\d{11}$/';
 if(preg_match($objRegExp, $cardnum)) return 'JCB';
}

function validRoutingNumber($pat) {
	$objRegExp = '/^((0[0-9])|(1[0-2])|(2[1-9])|(3[0-2])|(6[1-9])|(7[0-2])|80)([0-9]{7})$/';
	return preg_match($objRegExp, $pat);
}

//=============================================
function creditCardEntryJS() {
	return <<<CCCJS
function checkCC(el) {
	var ccnum = ccNumLooksValid(el.value);
	if(!ccnum) {
		alert("Credit card number is invalid.");
		return;
	}
	el.value = ccnum;
	if(document.getElementById('company').value) return;
	var v = el.value.replace(/[^0-9]+/g, '');
	var guess = guessCreditCardCompany(v);
	if(guess && confirm('Is this a '+guess+'?'))
		document.getElementById('company').value = guess;
}

function warnIfCCFormatInvalid(el) {
	if(!ccNumLooksValid(el.value)) {
		alert("Credit card number is invalid.");
		return;
	}
}

function ccNumLooksValid(str) {
  var objRegExp;
  str = str.replace(/[ -]+/g, '');
  objRegExp = /^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})$/;
	return objRegExp.test(str) ? str : false;
}

function guessCreditCardCompany(str) {
/*
		* Visa:  All Visa card numbers start with a 4. New cards have 16 digits. Old cards have 13.
    * MasterCard:  All MasterCard numbers start with the numbers 51 through 55. All have 16 digits.
    * American Express: ^3[47][0-9]{13}$ American Express card numbers start with 34 or 37 and have 15 digits.
    * Diners Club: ^3(?:0[0-5]|[68][0-9])[0-9]{11}$ Diners Club card numbers begin with 300 through 305, 36 or 38. All have 14 digits. There are Diners Club cards that begin with 5 and have 16 digits. These are a joint venture between Diners Club and MasterCard, and should be processed like a MasterCard.
    * Discover: ^6(?:011|5[0-9]{2})[0-9]{12}$ Discover card numbers begin with 6011 or 65. All have 16 digits.
    * JCB: ^(?:2131|1800|35\d{3})\d{11}$ JCB cards beginning with 2131 or 1800 have 15 digits. JCB cards beginning with 35 have 16 digits. 	
 */
  var objRegExp;
  objRegExp = /^4[0-9]{12}(?:[0-9]{3})?$/;
 	if(objRegExp.test(str)) return 'Visa';
  objRegExp = /^5[1-5][0-9]{14}$/;
 	if(objRegExp.test(str)) return 'MasterCard';
  objRegExp = /^3[47][0-9]{13}$/;
 	if(objRegExp.test(str)) return 'American Express';
  objRegExp = /^3(?:0[0-5]|[68][0-9])[0-9]{11}$/;
 	if(objRegExp.test(str)) return 'Diners Club';
  objRegExp = /^6(?:011|5[0-9]{2})[0-9]{12}$/;
 	if(objRegExp.test(str)) return 'Discover';
  objRegExp = /^(?:2131|1800|35\d{3})\d{11}$/;
 	if(objRegExp.test(str)) return 'JCB';
  return '';
}

function useHomeAddress() {
	var keys = 'address,city,state,zip,phone'.split(',');
	for(var i=0;i<keys.length;i++)
		document.getElementById('x_'+keys[i]).value = document.getElementById('h_'+keys[i]).value;
}

function replaceCC() {
	if(document.getElementById('ccformtable').style.display=='none') {
		document.getElementById('ccformtable').style.display='inline';
		document.getElementById('replaceCCButton').value='Save New Credit Card';
		document.getElementById('replaceCCButton').onclick=replaceCC;
	}
	else if(MM_validateFormArgs(ccFormArgsToTest())) {
		document.getElementById('action').value='replace';
		document.cceditor.submit();
	}

}

function createCC() {
  if(MM_validateFormArgs(ccFormArgsToTest())) {
		document.getElementById('action').value='create';
		document.cceditor.submit();
	}
}

function dropCC() {
	document.getElementById('action').value='drop';
	document.cceditor.submit();
}

function ccFormArgsToTest() {
	var args;
	if(true/*!document.getElementById('ccid').value*/)
	  args = [
		  'x_card_num', '', 'R',
		  'x_card_num', '', 'validCC',
		  //'x_card_code', '', 'R',
		  'x_card_code', '3', 'MINLENGTH',
		  'x_card_code', '4', 'MAXLENGTH'
		  ];
	else args = [];
	var extraArgs = [
		  'x_exp_date', '', 'R',
		  'expmonth', '', 'R',
		  'expyear', '', 'R',
		  'company', '', 'R'];
	setPrettynames(
					'x_card_num', 'Credit Card Number', 'x_card_code', 'Credit Card Verification Number',
					'x_exp_date','Expiration','expmonth','Expiration Month', 'expyear', 'Expiration Year', 'company', 'Credit Card Company');
		  
	for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];
	if(true /*!document.getElementById('useclientinfo').checked*/) {
		setPrettynames('x_first_name','First Name','x_last_name','Last Name', 'x_address', 'Address', 'x_city', 'City',
										'x_state', 'State', 'x_zip', 'ZIP', 'x_country', 'Country', 'x_phone', 'Phone');

		extraArgs = [
				'x_first_name', '', 'R',
				'x_last_name', '', 'R',
				'x_address', '', 'R',
				'x_city', '', 'R',
				'x_state', '', 'R',
				'x_zip', '', 'R',
				'x_country', '', 'R',
				'x_phone', '', 'R'
				];
		for(var i=0;i<extraArgs.length;i++) args[args.length] = extraArgs[i];
	}
	return args;
/*
5491132285827692
*/
}	
CCCJS;
}

function getAllCardTypes() {
	$raw = "mc|MasterCard|cc-mastercard-37.gif||visa|Visa|cc-visa-37.gif||amex|American Express|cc-amex-37.gif||discover|Discover|cc-discover-37.gif";
	foreach(explode('||', $raw) as $card) {
		$card =  explode('|', $card);
		$cards[$card[0]] = array('key'=>$card[0], 'label'=>$card[1], 'img'=>$card[2]);
	}
	return $cards;
}	



/*

CREATE TABLE IF NOT EXISTS `tblecheckacct` (
  `acctid` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(4) NOT NULL,
  `abacode` varchar(9) NOT NULL,
  `bank` varchar(50) DEFAULT NULL,
  `acctnum` varchar(50) CHARACTER SET utf8 NOT NULL,
  `last4` varchar(4) NOT NULL,
  `acctname` varchar(50) NOT NULL,
  `accttype` varchar(20) DEFAULT NULL,
  `acctentitytype` varchar(20) DEFAULT NULL,
  `autopay` tinyint(1) NOT NULL,
  `clientptr` int(11) NOT NULL,
  `created` date NOT NULL,
  `modified` date DEFAULT NULL,
  `createdby` int(11) NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `gateway` varchar(40) DEFAULT NULL,
  `vaultid` varchar(100) DEFAULT NULL,
  `encrypted` tinyint(4) NOT NULL COMMENT 'if 0, sensitive values are already masked',
  `primarypaysource` tinyint(4) NOT NULL,
  PRIMARY KEY (`acctid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


CREATE TABLE IF NOT EXISTS `tblecheckacctinfo` (
  `acctptr` int(11) NOT NULL,
  `x_company` varchar(50) DEFAULT NULL,
  `x_address` varchar(60) NOT NULL,
  `x_city` varchar(40) NOT NULL,
  `x_state` varchar(40) NOT NULL,
  `x_zip` varchar(20) NOT NULL,
  `x_country` varchar(60) NOT NULL,
  `x_phone` varchar(25) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;



*/