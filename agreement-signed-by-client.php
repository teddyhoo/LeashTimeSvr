<? // agreement-signed-by-client.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "agreement-fns.php";

locked('o-');
$clientid = $_REQUEST['clientid'];
if(!$clientid) $error = "No client ID supplied.";
else {
	$client = fetchFirstAssoc("SELECT userid, CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = $clientid", 1);
	if(!$client) $error = "No signed client was found for client ID $clientid.";
	else $agreementSigned = clientAgreementSigned($client['userid']);
}
if(!$agreementSigned) $error = "No signed agreement found for client ID $clientid ({$client['userid']}).";

if($error) {
	echo $error;
	exit;
}
$fullAgreement = getServiceAgreement($agreementSigned['agreementid'], $withoutTerms=0);
$terms = $fullAgreement['terms'];
if(!$fullAgreement['html']) $terms = htmlizeAgreementText($terms);
?>
<html language="en">
<body style='font: regular 10pt Arial'>
<?
	echo $terms;
	echo "<hr>";
	$signedTime = strtotime($agreementSigned['agreementdate']);
	echo "Agreed to by {$client['name']} on ".shortDate($signedTime)." at ".date('h:i a', $signedTime);
	?>
</body>
