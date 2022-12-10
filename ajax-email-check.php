<? // ajax-email-check.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "smtp-validate-class.php";

locked('o-');
extract(extractVars('email', $_REQUEST));

$emails = explode(',', $email);

$checker = new SMTP_validateEmail;
$response = $checker->validate($emails, 'notice@leashtime.com');
if(count($response) > 1) {
	foreach($response as $email => $result)
		echo "$email: ".($result ? 'Ok' : 'Failed').'<br>';
}
else foreach($response as $email => $result)
		echo $result ? 'This email address is valid.' : 'Invalid email address.';
