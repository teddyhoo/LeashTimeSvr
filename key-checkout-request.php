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

$providerMsg = "Dear ##Sitter##,\n\nYou will need a copy ##Client##'s house keys (labeled ##KeyLabel##-XX) by ##Date##.  Please come to the office to retrieve them at your earliest convenience.\n\nSincerely,";
$providerSubject = "Please pick up key ##KeyLabel##";

$keyLabel = sprintf("%04d", $key);
$key = getKey($key);

$clientDetails = getOneClientsDetails($key['clientptr']);
$providerDetails = getProvider($prov);
$date = longDayAndDate(strtotime($date));
$messageBody = str_replace('##Client##', $clientDetails['clientname'], $providerMsg);
$messageBody = str_replace('##Sitter##', providerShortName($providerDetails), $messageBody);
$messageBody = str_replace('##KeyLabel##', $keyLabel, $messageBody);
$messageBody = str_replace('##Date##', $date, $messageBody);
$messageSubject = str_replace('##KeyLabel##', $keyLabel, $providerSubject);

$provider = $prov;
include "comm-composer.php";

