<? // client-login-setup.php
// 1. ensure that email addresses are unique among clients.  report if they are not.
// 2. report clients who lack email addresses
// 3. report clients where email address is already in use as a login id
// 4. For non-problem clients, offer login ids and token passwords (in inputs), with checkboxes
// 5. Offer "set credentials" button

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";

$locked = locked('o-');
if(!staffOnlyTEST()) {
	echo "Insufficient access rights.";
	exit;
}
extract($_REQUEST);

function genPassword($client) {
	if($_GET['zip'] && $client['zip']) return $client['zip'];
	else return randomToken();
}

if($_POST) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require_once "common/init_db_common.php";
	foreach($_POST as $key => $val) {
		if(strpos($key, 'cb_') !== 0) continue;
		$clientid = substr($key, strlen('cb_'));
		$loginid = trim($_POST['loginid_'.$clientid]);
		if(!$loginid) $emptyLoginIds++;
		else $loginIds[] = mysql_real_escape_string($loginid);
	}
	if($loginIds) $badLoginIds = fetchCol0("SELECT loginid FROM tbluser WHERE loginid IN ('".join("','", $loginIds)."')");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	if($badLoginIds || $emptyLoginIds) {
		if($badLoginIds) echo "<font color=red>No credentials set up.  These Login IDs are already in use:<ul>"
			.join('<li>', $badLoginIds).'</ul></font>';
		if($emptyLoginIds) echo "<p><font color=red>$emptyLoginIds of the login ids are empty.</font>";
	}
	else {
		foreach($_POST as $key => $val) {
			if(strpos($key, 'cb_') !== 0) continue;
			$clientid = substr($key, strlen('cb_'));
			$email = fetchRow0Col0("SELECT email FROM tblclient WHERE clientid = $clientid LIMIT 1");
			$data = array('bizptr'=>$_SESSION["bizptr"], 'orgptr'=>$_SESSION["orgptr"],
										'loginid'=>$_POST['loginid_'.$clientid],
										'email'=>$email,
										'rights'=>basicRightsForRole('client'),
										'active'=>1,
										'temppassword'=>$_POST['pass_'.$clientid]
										);
			$newuser = addSystemLogin($data, 'clientOrProviderOnly');
			if(is_string($newuser)) echo "<font color=red>$newuser</font><p>";
			else {
				$newusers++;
				updateTable('tblclient', array('userid'=>$newuser['userid']), "clientid=$clientid", 1);
			}
		}
		if($newusers) echo "<font color=darkgreen>$newusers system logins added.</font><p>";
	}
		

}

$clientsWithLogins = fetchAssociationsKeyedBy(
		"SELECT clientid, userid, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname FROM tblclient WHERE active=1 AND userid IS NOT NULL", 'userid');


if($db == 'leashtime') $emailPhrase = "if(email IS NOT NULL AND email <> '', CONCAT('LT-', email), '') as email";
else $emailPhrase = "email";

$clientsWithoutLogins = fetchAssociationsKeyedBy(
		"SELECT clientid, $emailPhrase, lname, fname, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname, zip FROM tblclient WHERE active=1 AND userid IS NULL", 'clientid');
		
require "common/init_db_common.php";

if($clientsWithLogins) {
	$loginIds = fetchAssociationsKeyedBy("SELECT userid, loginid, temppassword FROM tbluser WHERE userid IN (".join(',', array_keys($clientsWithLogins)).")", 'userid');
	foreach($loginIds as $userid => $login) {
		$clientsWithLogins[$userid]['loginid'] = $login['loginid'];
		$clientsWithLogins[$userid]['temppassword'] = $login['temppassword'];
	}
}

foreach($clientsWithoutLogins as $clientid => $client)
	if($client['email']) $clientEmails[] = mysql_real_escape_string($client['email']);
	
if($clientEmails) $badEmails = 
	fetchAssociationsKeyedBy(
		"SELECT loginid, userid, bizname 
			FROM tbluser 
			LEFT JOIN tblpetbiz ON bizid = bizptr
			WHERE loginid IN ('".join("','", $clientEmails)."')", 'loginid');
//print_r($badEmails);			
	
foreach($clientsWithoutLogins as $clientid => $client) {
	if($badEmails[$client['email']]) {
		$clientsWithoutLogins[$clientid]['bademail'] = $client['email'];
		$clientsWithoutLogins[$clientid]['bademailbiz'] = $badEmails[$client['email']]['bizname'];
		unset($clientsWithoutLogins[$clientid]['email']);
	}
	if($client['email']) $duplicates[strtoupper($client['email'])] += 1;
	$confirmUnique[] = strtoupper($client['email']);
}

if($confirmUnique) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require  "common/init_db_common.php";
	$confirmUnique = array_map('mysql_real_escape_string', $confirmUnique);
	$moreDups = fetchCol0("SELECT loginid FROM tbluser WHERE loginid IN ('".join("','", $confirmUnique)."')", 1);
	foreach($moreDups as $dup) $duplicates[strtoupper($dup)] += 1;
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
}

function loginOKToUse($loginid) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require  "common/init_db_common.php";
	$found = fetchRow0Col0("SELECT loginid FROM tbluser WHERE loginid = '$loginid'", 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	return $found ? false : $loginid;
}

function safeLoginID($client) {
	$loginId = array($client['fname'], $client['lname'], $_SESSION['bizptr']);
	$loginId = join('.', $loginId);
	$loginId = preg_replace("/(?![.-])\p{P}/u", "", $loginId);
  $loginId = preg_replace('/\s+/', '', $loginId);
	return $loginId;
}

//print_r($duplicates);
function sortByName($a, $b) {return strcmp($a['sortname'], $b['sortname']);}
uasort($clientsWithoutLogins, 'sortByName');
$rows[] = array('name'=>'<b>Issues</b>');
foreach($clientsWithoutLogins as $clientid => $client) {
	$row = array('name'=>$client['name']);
	if($duplicates[strtoupper($client['email'])] > 1) $row['loginid'] = "<font color=red>Email address [{$client['email']}] is a duplicate.</font>";
	else if($client['bademail']) $row['loginid'] = "<font color='#ff8888'>Email address [{$client['bademail']}] is already in use as a login id in {$client['bademailbiz']}.</font>";
	else if(strlen($client['email']) > 65) $row['loginid'] = "<font color=red>Email address too long: {$client['email']}</font>";
	else if(!$client['email']) $row['loginid'] = "<font color=red>No Email address found.</font>";
	else continue;
	$rows[] = $row;
}
$rows[] = array('#CUSTOM_ROW#'=>'<tr><td colspan=4>&nbsp;</td></tr>');
$rows[] = array('name'=>'<b>Clients without logins</b>');
$numAssignable = 0;
foreach($clientsWithoutLogins as $clientid => $client) {
	$baddies += 1;
	$row = array('name'=>$client['name']);
	if($duplicates[strtoupper($client['email'])] > 1) $row['loginid'] = "<font color=red>Email address [{$client['email']}] is a duplicate.</font>";
	else if($client['bademail']) $row['loginid'] = "<font color='#ff8888'>Email address [{$client['bademail']}] is already in use as a login id in {$client['bademailbiz']}.</font>";
	else if(strlen($client['email']) > 65) $row['loginid'] = "<font color=red>Email address too long: {$client['email']}</font>";
	//else if(!$client['email']) $row['loginid'] = "<font color=red>No Email address found.</font>";
	else if(!$client['email']) {
		if(!$_GET['tryfullname']) $row['loginid'] = "<font color=red>No Email address found.</font>";
		else {
			$nameloginid = safeLoginID($client);
			if(loginOKToUse($nameloginid)) {
				$row['cb'] = "<input id='cb_$clientid'  name='cb_$clientid' type='checkbox'>\n";
				$row['loginid'] = "<input id='loginid_$clientid'  name='loginid_$clientid' size=40 value='$nameloginid'>\n";
				$row['password'] = "<input id='pass_$clientid'  name='pass_$clientid' size=15 value=".genPassword($client).">\n"
					." <font color=red>No Email address found. This will work.</font>";
				$numAssignable += 1;
			}
			else $row['loginid'] = "<font color=red>No Email address found.  Cannot use [$nameloginid]</font>";
		}
	}
	else if($client['email']) {
		$baddies -= 1;
		$row['cb'] = "<input id='cb_$clientid'  name='cb_$clientid' type='checkbox'>\n";
		$row['loginid'] = "<input id='loginid_$clientid'  name='loginid_$clientid' size=40 value='{$client['email']}'>\n";
		$row['password'] = "<input id='pass_$clientid'  name='pass_$clientid' size=15 value=".genPassword($client).">\n";
		$numAssignable += 1;
	}
	$rows[] = $row;
}
$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=3>$baddies clients can NOT be assigned credentials.</td></tr>");
$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=3>$numAssignable clients can be assigned credentials.</td></tr>");
$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=3>".count($clientsWithLogins)." clients already have credentials.</td></tr>");
$rows[] = array('name'=>'<b>Clients with logins</b>');
uasort($clientsWithLogins, 'sortByName');
foreach($clientsWithLogins as $userid => $client) {
	$rows[] = array('name'=>$client['name'], 'loginid'=>"<font color=darkgreen>{$client['loginid']}</font>");
	if($_REQUEST['temps']) 
		$rows[count($rows)-1]['password'] = $client['temppassword'];
}

echo "<h2><font color=red>{$_SESSION['preferences']['bizName']}</font> Active Client Logins</h2>";

$columns = explodePairsLine('cb| ||name|Client||loginid|Login ID||password|Temp Password');
echo "<form name='loginidform' method='POST'>";
fauxLink('Select All', 'selectAll(1)');
echo " - ";
fauxLink('Deselect All', 'selectAll(0)');
echo " - ";
echoButton('', 'Assign Credentials', 'assignCredentials()');
tableFrom($columns, $rows, '');
echo "</form>";
?>
<script language='javascript'>

function assignCredentials() {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') > 0 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert('Please select one or more clients first.');
		return;
	}
	document.loginidform.submit();
}

function selectAll(onoff) {
	var inputs;
	inputs = document.getElementsByTagName('input');
	for(var i=0; i < inputs.length; i++)
		if(inputs[i].type=='checkbox')
			inputs[i].checked = (onoff ? 1 : 0);
}

</script>