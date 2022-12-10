<? // invoice-preview-generate.php
/* Two Modes:
Automatic - called by AJAX: 
	clients: create invoices for supplied client ids
	asOfDate: select billables with itemdates up to asOfDate
	target: invoke target with resulting invoice ids.
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-fns.php";
require_once "invoice-gui-fns.php";
require_once "comm-fns.php";
$locked = locked('o-'); 


if($_GET && $_GET['clients']) { // Automatic mode
	extract($_REQUEST);
	$clients = fetchAssociationsKeyedBy("SELECT * FROM tblclient WHERE clientid IN ({$_GET['clients']})", 'clientid');
	foreach($clients as $id => $client) {
		if(!$client['email']) {
			replaceTable('tblemailedinvoicepreview', array('clientptr'=>$id, 'attempted'=>date('Y-m-d H:i:s'), 'failed'=>1), 1);
			$addresslessClients++;
		}
		else {
			$previewId = replaceTable('tblemailedinvoicepreview', array('clientptr'=>$id, 'email'=>$client['email']), 1);
			$preview = getInvoicePreviewContents($id, $asOfDate, $previewId);
			$msgbody =  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> '
			.$preview;
//echo $msgbody;
			enqueueEmailNotification($client, 'Your Invoice Preview', $msgbody, null, $_SESSION["auth_login_id"], 'html');
			$previewsToSend++;
		}
	}
	if($addresslessClients) echo "$addresslessClients of the selected clients have no email address.\n";
	echo "$previewsToSend invoice previews will be sent out in the next few minutes.";
}






/*
CREATE TABLE IF NOT EXISTS `tblemailedinvoicepreview` (
  `previewid` int(11) NOT NULL auto_increment,
  `clientptr` int(11) NOT NULL,
  `attempted` datetime default NULL,
  `failed` tinyint(4) NOT NULL,
  `email` varchar(100) default NULL,
  PRIMARY KEY  (`previewid`),
  UNIQUE KEY `clientptr` (`clientptr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

*/