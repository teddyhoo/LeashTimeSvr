<? // reports-memos-on-deck.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
locked('o-');
$memos = fetchAssociations(
	"SELECT CONCAT_WS(' ', fname, lname) as sitter, datetime, note 
		FROM tblprovidermemo
		LEFT JOIN tblprovider ON providerid = providerptr", 1);
	
$pageTitle = 'Sitter Memos on Deck';	
	
if($_GET['lightbox']) echo "<h2>$pageTitle</h2>";	
else include "frame.html";

foreach($memos as $i => $memo) $memos[$i]['note'] = substr($memo['note'], strpos($memo['note'], '|')+1);

quickTable($memos, $extra=null, $style=null, $repeatHeaders=0);

if(!$_GET['lightbox']) include "frame-end.html";

