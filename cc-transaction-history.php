<? // cc-transaction-history.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "cc-processing-fns.php";


// Determine access privs
if(userRole() == 'o') $locked = locked('o-');
else $locked = locked('*cm');

extract(extractVars('client,id,currentOnly', $_REQUEST));
$filter = $id ? "AND ccid = $id" : ($currentOnly ? "AND active = 1" : "");
$cards = fetchAllClientCCs($client, $filter);
$cards = $cards ? $cards : array(0);
$adhoccards = fetchAllClientAdHocCCs($client, $filter);
$adhoccards = $adhoccards ? $adhoccards : array(0);
$achs = fetchAllClientACHs($client, $filter, 'display');
$achs = $achs ? $achs : array(0);
$actions = "ccpaymentadhoc|Payment||ccpayment|Payment||ccrefund|Refund||achpayment|Payment||achrefund|Refund";
$actions = explodePairsLine($actions);
$epaytypes = "ccpaymentadhoc|CC *||ccpayment|CC||ccrefund|CC||achpayment|ACH||achrefund|ACH";
$epaytypes = explodePairsLine($epaytypes);
unset($cards[0]);  // reject null card ids
unset($achs[0]);  // reject null ach ids

if($cards)
	$whereClauses[] = "(itemptr IN (".join(',', array_keys($cards)).") AND itemtable IN ('".join("','", array('ccpayment', 'ccrefund'))."'))";
if($achs)
	$whereClauses[] = "(itemptr IN (".join(',', array_keys($achs)).") AND itemtable IN ('".join("','", array('achpayment', 'achrefund'))."'))";

$messages = !$whereClauses ? array() 
			: fetchAssociations(
	"SELECT * FROM tblchangelog 
	 WHERE 
	 	".join(' OR ', $whereClauses)."
	 ORDER BY time ASC");
unset($adhoccards[0]);  // reject null adhoc card ids
if($adhoccards) $moreMessages = 
	fetchAssociations(
		"SELECT * FROM tblchangelog 
		 WHERE 
		 	itemptr IN (".join(',', array_keys($adhoccards)).") AND itemtable IN ('".join("','", array('ccpaymentadhoc', 'ccrefundadhoc'))."')
	 ORDER BY time ASC");
foreach((array)$moreMessages as $msg) $messages[] = $msg;

function cmpTime($a, $b) { return $a['time'] <= $b[time]; }
usort($messages, 'cmpTime');

$users = array();
foreach($messages as $m) $users[] = $m['user'];
if($users) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$usernames = fetchAssociationsKeyedBy("SELECT userid, loginid, rights, email FROM tbluser WHERE userid IN (".join(',', $users).")", 'userid');
	list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
	include "common/init_db_petbiz.php";
}
else $usernames = array();
	 
$columns = "time|Date / Time||action|Action||amount|Amount||epaytype|Type||acct|Account||transaction|Transaction||user|User||note||Note";
$columns = explodePairsLine($columns);

$rows = array();
foreach($messages as $msg) {
	$row = array();
	$row['time'] = shortDateAndTime(strtotime($msg['time']), 'mil');
	$row['action'] = $actions[$msg['itemtable']];
	$row['epaytype'] = $epaytypes[$msg['itemtable']];
	$hintStyle =  "style='text-decoration:underline;text-decoration-style: dashed;'";
	if($msg['itemtable'] == 'ccpayment' || $msg['itemtable'] == 'ccrefund') {
		$card = $cards[$msg['itemptr']];
		$row['acct'] = $card['company'].' '.$card['last4'];
		// add account holder name as title
		$cardInfo = fetchFirstAssoc("SELECT * FROM tblcreditcardinfo WHERE ccptr = {$card['ccid']}");
		$row['acct'] = "<span $hintStyle title='".safeValue($cardInfo['x_first_name'].' '.$cardInfo['x_last_name'])."'>{$row['acct']}</span>";
	}
	else if($msg['itemtable'] == 'ccpaymentadhoc') {
		$card = $adhoccards[$msg['itemptr']];
		$row['acct'] = $card['company'].' '.$card['last4'];
		// add account holder name as title
	}
	else {
		$ach = $achs[$msg['itemptr']];
		$row['acct'] = $ach['acctnum'];
		$row['acct'] = "<span $hintStyle title='".safeValue($ach['acctname'])."'>{$row['acct']}</span>";
	}
	$row['user'] = $usernames[$msg['user']]['loginid'];
//if(mattOnlyTEST())	print_r($msg);
	$result = parseNote($msg['note']);
//if(mattOnlyTEST() && 	in_array($row['time'], array('07/10/2015 09:43', '03/22/2015 21:59'))) echo print_r($msg['note'],1).'<br>';
	$row['transaction'] = $result['transaction'];
	$row['amount'] = "$".$result['amount'];
	$note = "{$result['status']} ";
	if($result['reason']) $note .= $result['reason'];
	if($result['title']) $note = "<span style='text-decoration:underline;' title='".safeValue($result['title'])."'>$note</span>";
	$row['note'] = $note;
	if($result['rawerror'] && staffOnlyTEST()) {
		$rawResponse = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $result['rawerror']));
		//$rawResponse = str_replace("<", '&lt;', str_replace(">", '&gt;', $rawResponse));
		//$rawResponse = str_replace("'", '&quot;', safeValue($rawResponse));
		$rawResponse = str_replace("'", '&quot;', $rawResponse);
		$rawResponse = addslashes($rawResponse);
		if(strpos((string)$_SESSION['preferences']['ccGateway'], 'Authorize') !== FALSE) {
			if($gateway = getGatewayObject($_SESSION['preferences']['ccGateway'])) {
				$rawResponse = $result['rawerror'];
				$htmlResp = $gateway->labeledResponseHTML(explode('|', $rawResponse));
				$rawResponse .= '<hr>'.str_replace("\n","", str_replace("\"", '&quot;', safeValue($htmlResp)));
			}
		}
		$row['note'] .= ' '.fauxLink('&#9888;', "showRawError(\"$rawResponse\");", 1, 'STAFF ONLY. show error');
	}
	$rowClasses[] = $card['active'] ? '' : 'oldcard';
	$rows[] = $row;
}

$clientDetails = getOneClientsDetails($client);
$windowTitle = "{$clientDetails['clientname']}'s Credit Card Transactions";
$extraBodyStyle = 'background:white;';
require "frame-bannerless.php";
echo '<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
			 <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>
			 <link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" />
			 <script type="text/javascript">
			 function showRawError(err) {
			 	$.fn.colorbox({html:err, width:500, height:300, scrolling: true, opacity: "0.3"});
				}
			 </script>
			 ';
echo "<h2>$windowTitle</h2>";
if(!$messages) {
	echo "No credit card transactions found for {$clientDetails['clientname']}.";
	exit;
}
echo "<style>.oldcard {background: lightgray;}</style>";
echo "Shaded transactions were made using previously registered credit cards which may or may not be the same as the currently active card.<p>";
echo "CC * = card supplied by user for one-time use.<p>";
tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);

function parseNote($note) {
	$result = array();
	//$parts = explode('-', $note);
	if(($dash = strpos($note, '-')) !== FALSE) {
		$parts[0] = substr($note, 0, $dash);
		$parts[1] = substr($note, $dash+1, strlen($note)-($dash+1));
	}
	else $parts[0] = $note;
	
	$result['status'] = $parts[0];
	$parts = explode('|', $parts[1]);
//if(mattOnlyTEST()) echo print_r($note,1)."<hr>";			
	if($result['status'] == 'Approved') {
		$result['amount'] = $parts[0];
		$result['transaction'] = $parts[1];
	}
	else {
		
		$result['reason'] = $parts[0];
		// Declined-This transaction has been declined.|Amount:2.00|Trans:3574472823|Gate:Authorize.net|ErrorID:172
		// 2|1|2|This transaction has been declined.|000000|U|3574472823|||2.00|CC|auth_capture||Ted|Hooban||22085 Chelsy Paige Sq|ASHBURN|VA|20148|USA|||||||||||||||||E88631C92BF364DC7FCA08CBDD03B36E|N||||||||||||XXXX5299|MasterCard||||||||||||||||
		for($i=1;$i<count($parts);$i++) {
			if(strpos($parts[$i], 'Gate:') === 0) $gateway = substr($parts[$i], strlen('Gate:'));
			else if(strpos($parts[$i], 'ErrorID:') === 0) {
				$ccErrordId = substr($parts[$i], strlen('ErrorID:'));
				if($gateway && $error = fetchRow0Col0("SELECT response FROM tblcreditcarderror WHERE errid = '$ccErrordId' LIMIT 1")) {
					if($gateway = getGatewayObject($gateway))
						$message = $gateway->ccLastMessage($error);
					if($message) $result['title'] = $message;
				}
				$result['rawerror'] = $error;
			}
			
			else if(strpos($parts[$i], 'Trans:') === 0) $result['transaction'] = substr($parts[$i], strlen('Trans:'));
		}
	}
	return $result;
}