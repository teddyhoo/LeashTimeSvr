<? // cc-overview.php
/*
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

locked('o-');

if(!staffOnlyTEST()) {
	$error = "LeashTime Staff Use Only.";
}

else {
	if($_POST["dropallcc"]) {
		$activeCardIDs = fetchCol0("SELECT ccid FROM tblcreditcard WHERE active = 1");
		if(TRUE) {
			require_once "cc-processing-fns.php";
			foreach($activeCardIDs as $ccid) dropCC($ccid);
		}
		$bagTextId = insertTable('tbltextbag', array('referringtable'=>'tblchangelog', 'body'=>join(',', $activeCardIDs)), 1);
		logChange('-999', 'tblcreditcard', 'm', $note="mass dropped ".count($activeCardIDs)." cards|textbag:$bagTextId");
		$_SESSION['frame_message'] = "<h2 class='warning'>All ".count($activeCardIDs)." Cards Have Been Dropped!</h2>".join(', ', $activeCardIDs);
	}

	if($_POST["dropallach"]) {
		$activeACHIDs = fetchCol0("SELECT acctid FROM tblecheckacct WHERE active = 1");
		if(TRUE) {
			require_once "cc-processing-fns.php";
			foreach($activeACHIDs as $acctid) dropACH($acctid);
		}
		$bagTextId = insertTable('tbltextbag', array('referringtable'=>'tblchangelog', 'body'=>join(',', $activeACHIDs)), 1);
		logChange('-999', 'tblecheckacct', 'm', $note="mass dropped ".count($activeACHIDs)." ach/echeck accts|textbag:$bagTextId");
		$_SESSION['frame_message'] = "<h2 class='warning'>All ".count($activeACHIDs)." ACH Accounts Have Been Dropped!</h2>".join(', ', $activeACHIDs);
	}

	$activeCards = fetchAssociations(
		"SELECT cc.*, if(cc.x_exp_date < CURDATE(), 1, 0) as expired, 
						lname, fname, CONCAT_WS(' ', fname, lname) as name,
						c.active as clientactive,
						CONCAT_WS(' ', x_first_name, x_last_name) as cardname
		 FROM tblcreditcard cc
		 LEFT JOIN tblcreditcardinfo ON ccptr = ccid
		 LEFT JOIN tblclient c ON clientid = clientptr
		 WHERE cc.active = 1
		 ORDER BY fname, lname"
	);
	$activeACHs = fetchAssociations(
		"SELECT ach.*,
						lname, fname, CONCAT_WS(' ', fname, lname) as name,
						c.active as clientactive
		 FROM tblecheckacct ach
		 LEFT JOIN tblecheckacctinfo ON acctptr = acctid
		 LEFT JOIN tblclient c ON clientid = clientptr
		 WHERE ach.active = 1
		 ORDER BY fname, lname"
	);
}
if($error) 
	$_SESSION['user_notice'] = "<h2 class='warning'>$error</h2>";
$breadcrumbs = fauxLink('Client Credit Cards Report', "document.location.href=\"reports-credit-cards.php\"", 1);
include "frame.html";
if(!$error) {
	foreach($activeCards as $i => $cc) {
		if(!$cc['clientactive'])  $activeCards[$i]['name'] = "<span class='warning'>{$cc['name']}</span>";
		$activeCards[$i]['expires'] = date('m/y', strtotime($cc['x_exp_date']));
		if($cc['expired']) {
			$expCount += 1;
			$activeCards[$i]['expires'] = "<span class='warning'>{$activeCards[$i]['expires']}</span>";
		}
		$rowClasses[] = $cc['expired'] ? 'cardexpired' : 'cardnotexpired';
		
	}
	echo "<h2>Current Cards</h2>";
	fauxLink('All Cards', "showCards(\"all\")");
	echo " - ";
	fauxLink('Expired Cards Only', "showCards(\"cardexpired\")");
	echo " - ";
	fauxLink('Unexpired Cards Only', "showCards(\"cardnotexpired\")");
	echoButton('', 'Drop All Cards', 'dropAllCards()', 'closeButton', 'closeButtonDown');
	echo "<p>Current Cards: <b>".count($activeCards)."</b> Expired Cards: <b class='warning'>$expCount</b> Unexpired Cards: <b>".(count($activeCards)-$expCount)."</b>";
	$columns = explodePairsLine('name|Client||company|Company||last4|Last 4||expires|Expires||gateway|Gateway||cardname|Card Name');
	tableFrom($columns, $activeCards, $attributes='BORDER=1', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
	
	foreach($activeACHs as $i => $ach) {
		if(!$ach['clientactive'])  $activeACHs[$i]['name'] = "<span class='warning'>{$ach['name']}</span>";		
	}
	echo "<h2>Current ACH Accounts</h2>";
	echo "<p>Current ACH Accounts: <b>".count($activeACHs)."</b>";
	echoButton('', 'Drop All ACH Accts', 'dropAllACHs()', 'closeButton', 'closeButtonDown');
	$columns = explodePairsLine('name|Client||last4|Acct Number||abacode|ABA Code||gateway|Gateway||vaultid|Vault ID||acctname|Card Name');
	tableFrom($columns, $activeACHs, $attributes='BORDER=1', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
}
?>
<form name='dropallform' method='POST'>
<input type='hidden' id='dropallcc' name='dropallcc' value=''>
<input type='hidden' id='dropallach' name='dropallach' value=''>
</form>
<script>
function dropAllCards() {
	if(!confirm('This will DROP ALL CARDS in the database.  This cannot be undone.  Continue?')) {
		alert('Drop operation aborted.');
		return;
	}
	document.getElementById('dropallcc').value = 1;
	document.dropallform.submit();
}

function dropAllACHs() {
	if(!confirm('This will DROP ALL ACH ACCOUNTS in the database.  This cannot be undone.  Continue?')) {
		alert('Drop operation aborted.');
		return;
	}
	document.getElementById('dropallach').value = 1;
	document.dropallform.submit();
}

function showCards(classname) {
	if(classname == 'all') $('.cardexpired, .cardnotexpired').show();
	else $('.cardexpired, .cardnotexpired').hide();
	$('.'+classname).show();
}
</script>
<?
include "frame-end.html";

