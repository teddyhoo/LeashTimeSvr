<? // leashtime-event-class.php
require "request-fns.php";
class LeashTimeEvent
{
    // property declaration
    private $accountId;
    private $accountToken;
    private $senderNumber;
  

 function __construct($accountId, $accountToken, $senderNumber) {
	 require_once "encryption.php";
	 $this->accountId = $accountId;
	 }
}
