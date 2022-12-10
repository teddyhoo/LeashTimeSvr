<? // diag-recent-emails.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "comm-fns.php";
require "gui-fns.php";
require "provider-fns.php";

// Determine access privs
$locked = locked('o-');

$pageTitle = "Recent Emails";

include "frame-bannerless.php";

$emails = fetchAssociations("SELECT * FROM tblmessage WHERE transcribed IS NULL ORDER BY datetime DESC LIMIT 40");

$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");
$providerNames = getProviderNames();

foreach($emails as $i => $email) {
	$rowClasses[] = $class = $class == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';
	if($email['correstable'] == 'tblclient')
		$emails[$i]['name'] = $clientNames[$email['correspid']].' [C]';
	else if($email['correstable'] == 'tblprovider')
		$emails[$i]['name'] = $providerNames[$email['correspid']].' [P]';
	$emails[$i]['subject'] = fauxLink($email['subject'], "openConsoleWindow(\"messagecomposer\", \"comm-view.php?id={$email['msgid']}&section=\", 800, 600);", 1);
}

$columns = explodePairsLine('datetime|Date||name|Correspondent||correspaddr|Email||subject||Subject');
tableFrom($columns, $emails, 'width=100%', null, null, null, null, null, $rowClasses);
?>
<script language='javascript' src='common.js'></script>