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

class SolverasGateway  {
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
		//requestWrite("SolverasGateway constructor TEST|TEST|TEST");	
	}
}
