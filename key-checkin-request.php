<?
// key-checkin-request.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "key-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('+ka,+#km');//locked('o-'); 


extract($_REQUEST);
$clientMsg = "Dear ##Client##,\n\nPlease get us a copy of your house keys at your earliest convenience.\n\nSincerely,";
$providerMsg = "Dear ##Sitter##,\n\nPlease return your copy of ##Client##'s house keys (labeled ##KeyLabel##) to us at your earliest convenience.\n\nSincerely,";
$clientSubject = "Please get us a key";
$providerSubject = "Please return key ##KeyLabel##";

$pair = explode('-', $key);

$key = getKey($pair[0]);

$possessor = $key["possessor{$pair[1]}"];
$clientDetails = getOneClientsDetails($key['clientptr']);

if($possessor == 'client') {
	$client = $key['clientptr'];
	$messageBody = str_replace('##Client##', $clientDetails['clientname'], $clientMsg);
	$messageSubject = $clientSubject;
}
else {
	$provider = $possessor;
	$provDetails = getProvider($provider);
	$messageBody = str_replace('##Client##', $clientDetails['clientname'], $providerMsg);
	$messageBody = str_replace('##Sitter##', providerShortName($provDetails), $messageBody);
	$messageBody = str_replace('##KeyLabel##', formattedKeyId($pair[0], $pair[1]), $messageBody);
	$messageSubject = str_replace('##KeyLabel##', formattedKeyId($pair[0], $pair[1]), $providerSubject);
}
include "comm-composer.php";

