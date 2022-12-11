<? // solveras-callback.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "cc-processing-fns.php";
require_once "client-fns.php";
require_once "solveras-gateway-class.php";
require_once "request-fns.php";

if($_SESSION['preferences']['ccGateway'] == 'Solveras') require_once "solveras-gateway-class.php";
else if($_SESSION['preferences']['ccGateway'] == 'TransFirstV1') require_once "transfirst-nmi-txp-gateway-class.php";



$op = $_GET['op'];
$oplocks = array('clientpayment'=>'T-', 'clientcc'=>'c-', 'clientach'=>'c-', 0=>'o-'); // T == TEMPCLIENT
$locked = locked($oplocks[$op] ? $oplocks[$op] : $oplocks[0]);
}

$auth = merchantInfoSupplied();
//	'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");
if(!in_array($_SESSION['preferences']['ccGateway'], array('Solveras', 'TransFirstV1'))) {
	echo "ERROR: Wrong Gateway";
	exit;
}
if(!$auth) {
	echo 'Incomplete merchant authorization.';
	exit;
}
$tokenId = $_GET['token-id'];

//print_r($_GET);
$op = $_GET['op'];
//echo "clientcc!";
if($op == 'clientcc' || (strpos($op, 'mgrcc_') === 0)) handleNewCC($op, $auth, $tokenId);
else if($op == 'clientach' || (strpos($op, 'mgrach_') === 0)) handleNewACH($op, $auth, $tokenId);
if($op == 'clientpayment') {
	$solverasCallback = 1;
	require "solveras-client-cc-pay.php";
}

function handleNewACH($op, $auth, $tokenId) {
	$client = $op == 'clientach' ? $_SESSION['clientid'] : substr($op, strlen('mgrach_'));
	$responseArray = getResponseArray($auth, $tokenId);
//print_r($responseArray);exit;
	if(!$responseArray) $_SESSION['solveras_error'] = "Error: No response from Solveras: ".date('Y-m-d H:i:s');
	else if($responseArray['result'] == 2) { // Declined - use result-text
		if($op == 'clientach') $_SESSION['client-own-account-cc-time'] = 1; // open CC editor when we return to account page
		$_SESSION['solveras_error'] = "Declined: ".$responseArray['result-text'];
	}
	else if($responseArray['result'] == 3) { // Error - use result-text
		if($op == 'clientach') $_SESSION['client-own-account-cc-time'] = 1; // open CC editor when we return to account page
		$_SESSION['solveras_error'] = "Error: ".$responseArray['result-text'];
	}
	else {
		$ach = array(
			'clientptr'=>$client,
			'abacode'=>$responseArray['routing-number'], // masked, last4 clear
			'acctnum'=>$responseArray['account-number'],
			'acctname'=>$responseArray['account-name'],
			'accttype'=>$responseArray['account-type'],
			'acctentitytype'=>$responseArray['entity-type'],
			'x_address'=>$responseArray['address1'],
			'x_city'=>$responseArray['city'],
			'x_state'=>$responseArray['state'],
			'x_zip'=>$responseArray['postal'],
			'x_country'=>$responseArray['country'],
			'x_phone'=>$responseArray['phone'],
			'autopay'=> ($responseArray['fax'] ? 1 : '0'),
			'gateway'=>$_SESSION['preferences']['ccGateway'],
			'vaultid'=>$responseArray['customer-vault-id'],
			'encrypted'=>0
			);
		//$authorization = $_SESSION['staffuser'] ? authorizeCC($ach) : 1;
		if(is_array($authorization)) {
			$cause = isset($authorization['FAILURE']) ? $authorization['FAILURE'] : ccLastMessage($authorization);
			$_SESSION['solveras_error'] =  "Authorization failed: ".$cause;
		}
		else {
			$acctid = saveNewACH($ach); // deactivates old acct
			$ach['acctid'] = $acctid;
			saveACHInfo($ach); 
			setCreditCardIsRequiredIfNecessary();			
			//unset($_SESSION["creditCardIsRequired"]);
			$request= array('resolved' => 0, 'requesttype' => 'ACHSupplied', 'clientptr'=>$client, 'note'=>"E-Checking (ACH) Info supplied");
			saveNewClientRequest($request, $client);
		}
	}
	if(userRole() != 'c') globalRedirect("ach-edit.php?client=$client&updateopener=1");
	else if($_SESSION['solveras_error']) {
		$request= array('resolved' => 0, 'requesttype' => 'ACHSupplyFailure', 'clientptr'=>$client, 
										'note'=>"E-Checking (ACH) Info entry attempt failed:<p>{$_SESSION['solveras_error']}");
		saveNewClientRequest($request, $client);
		globalRedirect('client-own-account-ach.php');
	}
	else globalRedirect('client-own-account.php');
}

function parentRedirect($url) {
	if($_SESSION["responsiveClient"]) { // since we cannot test for iframe=1
		echo "<script>parent.document.location.href='$url?iframe=1';</script>";
		}
	else globalRedirect($url);
}

function handleNewCC($op, $auth, $tokenId) {
	$client = $op == 'clientcc' ? $_SESSION['clientid'] : substr($op, strlen('mgrcc_'));
	$responseArray = getResponseArray($auth, $tokenId);
	if(!$responseArray) $_SESSION['solveras_error'] = "Error: No response from Solveras: ".date('Y-m-d H:i:s');
	else if($responseArray['result'] == 2) { // Declined - use result-text
		if($op == 'clientcc') $_SESSION['client-own-account-cc-time'] = 1; // open CC editor when we return to account page
		$_SESSION['solveras_error'] = "Declined: ".$responseArray['result-text'];
	}
	else if($responseArray['result'] == 3) { // Error - use result-text
		if($op == 'clientcc') $_SESSION['client-own-account-cc-time'] = 1; // open CC editor when we return to account page
		$_SESSION['solveras_error'] = "Error: ".$responseArray['result-text'];
	}
	else {
		$expDate = $responseArray['cc-exp'];  //MMYY
		$expDate = '20'.substr($expDate,2).'-'.substr($expDate, 0, 2).'-01';
		$cc = array(
			'clientptr'=>$client,
			'company'=>guessMaskedCreditCardCompany($responseArray['cc-number']),
			'x_card_num'=>$responseArray['cc-number'], // masked, last4 clear
			'x_card_code'=>'XXX',
			'x_exp_date'=>$expDate,
			'x_first_name'=>$responseArray['first-name'],
			'x_last_name'=>$responseArray['last-name'],
			'x_address'=>$responseArray['address1'],
			'x_city'=>$responseArray['city'],
			'x_state'=>$responseArray['state'],
			'x_zip'=>$responseArray['postal'],
			'x_country'=>$responseArray['country'],
			'x_phone'=>$responseArray['phone'],
			'autopay'=> ($responseArray['fax'] ? 1 : '0'),
			'gateway'=>$_SESSION['preferences']['ccGateway'],
			'vaultid'=>$responseArray['customer-vault-id']
			);
		$authorization = $_SESSION['staffuser'] ? authorizeCC($cc) : 1;
		if(is_array($authorization)) {
			$cause = isset($authorization['FAILURE']) ? $authorization['FAILURE'] : ccLastMessage($authorization);
			$_SESSION['solveras_error'] =  "Authorization failed: ".$cause;
		}
		else {
			$ccid = saveNewCC($cc); // deactivates old CC
			$cc['ccid'] = $ccid;
			
			saveCCInfo($cc);
			unset($_SESSION["creditCardIsRequired"]);
			$request= array('resolved' => 0, 'requesttype' => 'CCSupplied', 'clientptr'=>$client, 'note'=>"Credit card supplied");
			saveNewClientRequest($request, $client);
		}
	}
	if(userRole() != 'c') globalRedirect("cc-edit.php?client=$client&updateopener=1");
	else if($_SESSION['solveras_error']) {
		$request= array('resolved' => 0, 'requesttype' => 'CCSupplyFailure', 'clientptr'=>$client, 
										'note'=>"Credit Card entry attempt failed:<p>{$_SESSION['solveras_error']}");
		if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') saveNewClientRequest($request, $client);
		parentRedirect('client-own-account-cc.php');
	}
	else parentRedirect('client-own-account.php');
}

function getResponseArray($auth, $tokenId) {
	$gatewayObject = getGatewayObject($_SESSION['preferences']['ccGateway']);
//echo "Ready...";
	$response = $gatewayObject->performStep3($auth, $tokenId); // xml object
	if(!$response) return null;
//echo "OP $op RESPONSE: [";print_r($response);	echo "]";
	$responseArray = array('responseXml'=>$response);
	$strings = explode(',', 'result,result-text,result-code,action-type,customer-vault-id');
	foreach($response->children() as $field) {
		if(in_array($field->getName(), $strings))
			$responseArray[$field->getName()] = (string)$field;
		else if($field->getName() == 'billing') {
			foreach($field->children() as $billfield)
				$responseArray[$billfield->getName()] = (string)$billfield;
		}
	}
	return $responseArray;
}
