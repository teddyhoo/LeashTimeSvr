<? // registration-request.php

/*
Step 1: offer form
 requires bizid and clientid -- to generate an email to the client
 check to see if active client exists.
 if not, error, exit.
 if no email, error, exit
 if user registered already, error (contact bizname) and exit
 offer a form that asks
 	are you {client name}? (checkbox)
 	click "Submit" and we will send you an email to confirm you are you
 	and contains anti-spam elements
 	
Step 2: generate email.  include a response token (encrypted URL)

Step 3: if response token checks out create a client request

*/
require_once "common/init_session.php";

if(($bizptr = $_REQUEST['start']) && ($clientid = $_REQUEST['client'])) { // STEP 1
	include "common/init_db_common.php";
	$petbiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizptr = $bizptr LIMIT 1", 1);
	if(!$petbiz) 
		$error = "BIZNOTFOUND";
	else if(!$petbiz["activebiz"]) 
		$error = "BIZNOTACTIVE";
	else if($petbiz['lockout'] && strcmp($petbiz['lockout'], date('Y-m-d')) < 1)
		$error = "BIZLOCKEDOUT";
	else {
		list($dbhost, $db, $dbuser, $dbpass) = array($petbiz['dbhost'], $petbiz['db'], $petbiz['dbuser'], $petbiz['dbpass']);
		include "common/init_db_common.php";
		reconnectPetBizDB($db, $dbhost, $dbuser, $dbpass, 1);
		if(!($client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = {$_REQUEST['client']} LIMIT 1", 1)))
			$error = "CLIENTNOTFOUND";
		else if(!$client["active"]) 
			$error = "CLIENTNOTACTIVE";
		else if($client["userid"]) 
			$error = "CLIENTALREADYREGISTERED";
		else if(!$client["email"]) 
			$error = "CLIENTNOEMAIL";
		else $offerRegistrationToClient = $client;
	}
}
else $error = "INSUFFICIENTINFO";

if($error) {
	if(in_array($error, explode("BIZNOTACTIVE,BIZLOCKEDOUT")) 
		displayError("We are sorry, but we cannot register you to log in to {$petbiz['bizname']} at this time. [n]";
	else if$error = "BIZNOTFOUND") 
		displayError("We are sorry, but there is insufficient information to register you to log in at this time. [m]";
	else if$error = "CLIENTNOTFOUND") 
		displayError("We are sorry, but you must be a client to log in. [c]";
	else if$error = "CLIENTNOTACTIVE") 
		displayError("We are sorry, but you must be an active client to log in. [a]";
	else if$error = "CLIENTALREADYREGISTERED") 
		displayError("Please contact {$petbiz['bizname']} to obtain your login credentials. [r]";
	else if$error = "CLIENTALREADYREGISTERED") 
		displayError("Please provide {$petbiz['bizname']} with you email so you can be sent login credentials. [e]";
	exit;
}
if($offerRegistrationToClient) { // STEP 1
	echo offerRegistration($offerRegistrationToClient);
}
