<? //email-export.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-,#ex');

extract($_REQUEST);  // if POSTed from here, id will be null, but clientid may be set

if($_POST) {
	if(isset($since)) { // client
		$report_title = "Email addresses of ".($since ? "clients active since $since" : "all active clients");
		$clients = '';
		if($since) {
			$since = date('Y-m-d', strtotime($since));
			$clients = fetchCol0("SELECT distinct(clientptr) FROM tblappointment WHERE canceled IS NULL AND date >= '$since'");
			if(!$clients) $clients = -1;
			else $clients = "AND clientid IN (".join(',',$clients).")";
		}
		if($clients != -1) 
			$emails = fetchCol0("SELECT email FROM tblclient WHERE active and email IS NOT NULL $clients");
	}
	else {
		$report_title = "Email addresses of ".($activeprovidersonly ? 'active' : 'all')." sitters";
		$activeprovidersonly = isset($activeprovidersonly) ? "AND active" : '';
		$emails = fetchCol0("SELECT distinct(email) FROM tblprovider WHERE email IS NOT NULL $activeprovidersonly");
	}
	if(!$emails) $message = "No email addresses found.";
	else {
		$filename = isset($since) ? ($since ? "Clients_since_$since" : "All_active_clients") : (isset($activeprovidersonly) ? "Active" : 'All').'sitters';
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=$filename.csv ");

		echo "$report_title\n\n".join("\n",$emails);
		exit;
	}
}

include "frame.html";
// ***************************************************************************
?>
<h2>Email Address Export</h2>
<? if($message) echo "<font color=red>$message</font><p>"; ?>
<form method="POST" name='clientfrm'>
<?
echoButton('', "Export Client Emails", 'document.clientfrm.submit()');
echo " ";
calendarSet('with appointments since:', 'since', $since);
?>
</form>
<p>
<form method="POST" name='providerfrm'>
<?
echoButton('', "Export Sitter Emails", 'document.providerfrm.submit()');
hiddenElement('x',1);
echo " ";
labeledCheckbox('Active sitters only', 'activeprovidersonly', $activeprovidersonly);
?>
</form>
<script language='javascript' src='popcalendar.js'></script>

<script language='javascript'>
<? dumpPopCalendarJS(); ?>
</script>
<p><img src='art/spacer.gif' height=300>
<?
// ***************************************************************************
include "frame-end.html";
?>


