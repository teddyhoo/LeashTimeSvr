<? // mobilemutts-client-credentials.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-template-fns.php";
$bizName = $_SESSION['preferences']['bizName'];
echo "<h2>$bizName ($db)</h2>";
$template = $templates = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#UNDELETABLE - Client Login Credentials' ORDER BY label");
$clients = fetchAssociationsKeyedBy(
	"SELECT userid, fname, lname, email, clientid
	FROM tblclient 
	WHERE userid IS NOT NULL AND email IS NOT NULL
	ORDER BY lname, fname", 'userid');
	
// find clients with May or June visits
$springclients = fetchCol0(
	"SELECT clientptr 
		FROM tblappointment 
		WHERE date >= '2015-05-01' AND date <= '2015-06-30'");

$templatebody = $template['body'];
$templatebody = str_replace("\r", "", $templatebody);
$templatebody = str_replace("\n\n", "<p>", $templatebody);
$templatebody = str_replace("#BIZNAME#", $bizName, $templatebody);
$templatebody = str_replace("#BIZEMAIL#", $_SESSION['preferences']['bizEmail'], $templatebody);
$templatebody = str_replace("#BIZPHONE#", $_SESSION['preferences']['bizPhone'], $templatebody);

require_once "common/init_db_common.php";
$creds = fetchAssociationsKeyedBy(
	"SELECT userid, loginid, temppassword 
		FROM tbluser 
		WHERE userid IN (".join(',', array_keys($clients)).")", 'userid');
foreach($clients as $userid => $client) {
	echo "<hr><hr>";
	$user = $creds[$userid];
	if(in_array($client['clientid'], $springclients)) {
		echo "<font color=green>NOTE -- Ignoring {$client['fname']} {$client['lname']} because of May or June visits.</font>";
		continue;
	}
	if(!$user['temppassword']) {
		echo "<font color=red>WARNING -- No TEMPPASSWORD set for {$client['fname']} {$client['lname']}</font>";
		continue;
	}
	$body =  str_replace("#FIRSTNAME#", "{$client['fname']}", $templatebody);
	$body =  str_replace("#LOGINID#", "{$user['loginid']}", $body);
	$body =  str_replace("#TEMPPASSWORD#", "{$user['temppassword']}", $body);
	$body =  str_replace("#EMAIL#", "{$client['email']}", $body);
	echo "{$template['subject']}<p>{$client['email']}<hr>$body";
}