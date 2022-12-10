<? // maint-logins.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";
require_once "login-fns.php";

$locked = locked('z-');
extract(extractVars('limit,bizdb,showbrowsers,loginid,find_unknown,find_sitters,find_clients,find_managers,find_dispatchers,failuresonly,find_staff,pattern,browserpattern,dayspast', $_REQUEST));

$hackerIPs = likelyHackerIPs();
$likelyHackersAgents = likelyHackerAgents();
if($_REQUEST['hackers']) {
	$hackerAgents = join("<br>", likelyHackerAgents());
	sort($hackerIPs);
	$hackerIPs = join("<br>", $hackerIPs);
	echo "<table><tr><td><b>Agent Patterns</b><p>$hackerAgents<tr><td><b>Agent IPs</b><p>$hackerIPs</table>";
	exit;
}

$limit = intValueOrZero($limit);
$dayspast = intValueOrZero($dayspast);
$validDB = preg_match("/^[a-zA-Z0-9_]*$/", $bizdb);
if(!$validDB) {echo "err."; exit;}

if($loginid) { // dump what we know about this loginid
	if(suspiciousUserName($loginid)) exit;
	$fields = "tu.rights,tu.userid,tu.fname,tu.lname,IF(tu.password,'yes','no') as password,IF(tu.temppassword,'yes','no'), tu.email,tu.agreementptr, tu.agreementdate, tu.active as useractive";
	$user = fetchFirstAssoc("SELECT $fields, bizname, bizid, db, dbhost, dbuser, dbpass
														FROM tbluser tu LEFT JOIN tblpetbiz ON bizid = bizptr WHERE loginid = '$loginid' LIMIT 1");
														
// rights,userid,fname,lname,IF(password,'yes','no'),IF(temppassword,'yes','no'), email,agreementptr, agreementdate										
														
														
	if(!$user) {echo "[$loginid] is an unknown login id"; exit;}
	//function labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false) 
	$inactive = !$user['useractive'] ? '<font color=red>INACTIVE</font>' : '';
	echo "<table>";
	labelRow('Login ID:', $name, "$loginid [{$user['rights']}]");
	if($inactive) labelRow($inactive, '', $inactive);
	labelRow('User ID:', $name, $user['userid']);
	labelRow('Name:', $name, "{$user['fname']} {$user['lname']}");
	labelRow('Business:', $name, "{$user['bizname']} ({$user['bizid']})");
	labelRow('Password:', $name, ($user['password'] ? 'set' : 'not set'));
	labelRow('Temp Password:', $name, $user['temppassword']);
	labelRow('Email:', $name, $user['email']);
	labelRow('Agreement:', $name, ($user['agreementptr'] ? $user['agreementdate'] : 'no'));
	if(!$user['rights']) labelRow('Error:', $name, 'No rights found', null, 'warning');
	echo "<tr><td colspan=2><hr></td></tr>";
	if($user['rights']) {
		$role = $user['rights'][0];
		reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass'], 1);
		if($role == 'p') {
			$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");
			$person = $provider;
			if(!$person) {
				$warning = 'No sitter with this userid was found';
				if($client) $warning .= "<br>However, the client {$client['fname']} {$client['lname']} DOES have this login id";
				labelRow('Error:', $name, $warning, null, 'warning', null, null, 1);
			}
			else {
				labelRow('Sitter:', $name, "{$person['fname']} {$person['lname']} ({$person['nickname']})");
				labelRow('Sitter ID:', $name, $person['providerid']);
			}
		}
		if($role == 'c') {
			$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE userid = {$user['userid']} LIMIT 1");
			$person = $client;
			if(!$person) {
				$warning = 'No client with this userid was found';
				if($provider) $warning .= "<br>However, the sitter {$provider['fname']} {$provider['lname']} DOES have this login id";
				labelRow('Error:', $name, $warning, null, 'warning', null, null, 1);
			}
			else {
				labelRow('Client:', $name, "{$person['fname']} {$person['lname']} ({$person['nickname']})");
				labelRow('Client ID:', $name, $person['clientid']);
			}
		}
	}
	echo "</table>";
	exit;
}
$dayspast = $dayspast ? $dayspast : 10;
$dbs = fetchKeyValuePairs("SELECT bizname, db FROM tblpetbiz ORDER BY bizname");
$dbs = array_merge(array('Pick a biz'=>'', '-- All --'=>-1, '-- Unknown --'=>-2), $dbs);
$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
$conditions[] = "LastUpdateDate >= ADDDATE(CURDATE(), '-$dayspast days')";
if($failuresonly) $conditions[] = 'success = 0';
if($bizdb && $bizdb != -1 && $bizdb != -2) $conditions[] = "db = '$bizdb'";
if($bizdb == -2) $conditions[] = "FailureCause = 'U'";
if($find_unknown) $roleFilter[] = "rights IS NULL";
if($find_sitters) $roleFilter[] = "rights LIKE 'p-%'";
if($find_clients) $roleFilter[] = "rights LIKE 'c-%'";
if($find_managers) $roleFilter[] = "rights LIKE 'o-%'";
if($find_dispatchers) $roleFilter[] = "rights LIKE 'd-%'";
if($find_staff) $roleFilter[] = "rights LIKE 'z-%'";
if($roleFilter) $conditions[] = "(".join(' OR ', $roleFilter).")";
if($browserpattern == 'hacker') {
	foreach(likelyHackerAgents() as $p) $pats[] = "browser LIKE '%$p%'";
	foreach(likelyHackerIPs() as $p) $pats[] = "RemoteAddress LIKE '%$p%'";
	$conditions[] = "(".join(' OR ', $pats).")";
}
else if($browserpattern) {
	foreach(explode('|', $browserpattern) as $p) $pats[] = "browser LIKE '%$p%'";
	$conditions[] = "(".join(' OR ', $pats).")";
}
$where = $conditions ? join(' AND ', $conditions) : '';
$where = $where ?  "$where" : '1=1';
$limit = $limit ? $limit : 400;

if($bizdb) {
	$result = doQuery($sql = "SELECT tbllogin.loginid, success, rights, failurecause, bizid, bizname, 	remoteaddress, browser, LastUpdateDate as time, tbluser.active
FROM tbllogin
LEFT JOIN tbluser ON tbluser.loginid = tbllogin.loginid
LEFT JOIN tblpetbiz ON bizid = bizptr
WHERE $where
ORDER BY LastUpdateDate DESC LIMIT $limit");
}
else if($pattern || $conditions) {
	if($pattern) {
		$patterns = array_map('trim', explode(',', $pattern));
		foreach($patterns as $i => $pat) {
			if(strpos($pat, '*') !== FALSE) $pat = str_replace('*', '%', $pat);
			$tests[] = "tbllogin.loginid LIKE '".mysql_real_escape_string($pat)."'";
		}
	}
	$sql = "SELECT tbllogin.loginid, success, rights, failurecause, bizid, bizname, 	remoteaddress, browser, LastUpdateDate as time, tbluser.active
FROM tbllogin
LEFT JOIN tbluser ON tbluser.loginid = tbllogin.loginid
LEFT JOIN tblpetbiz ON bizid = bizptr
WHERE $where AND (".($tests ? join(' OR ', $tests) : "1=1").")  
ORDER BY LastUpdateDate DESC LIMIT $limit";
	$result = doQuery($sql);
}
//if(mattOnlyTEST()) echo "<hr>$sql<hr>";


$windowTitle = 'Recent Logins';

include 'frame-maintenance.php';
if($bizdb) echo "<h2>".($bizdb == -1 ? 'All' : ($bizdb == -2 ? 'Unknown' : $bizdb))." Recent Logins</h2>";
?>
<style>
.biztable td {padding-left:30px;font-size:10pt;background:white;}
</style>

<?
labeledInput('In the past days:', 'dayspast', $dayspast);
echo "<br>";
selectElement('Business:', 'bizdb', $bizdb, $dbs, "go(this)");
echo "<img src='art/spacer.gif' width=10 height=1>";
labeledCheckBox('Show browsers', 'showbrowsers', $showbrowsers, 0, 0, "toggleBrowserColumn(this)", 1);
echo "<img src='art/spacer.gif' width=10 height=1>";
if(!($find_unknown || $find_sitters || $find_clients || $find_managers || $find_dispatchers)) {
	$find_unknown = 1;
	$find_managers = 1;
	$find_sitters = 1;
	$find_managers = 1;
	$find_dispatchers = 1;
	$find_staff = 1;
}
$go = "go(document.getElementById(\"bizdb\"))";
labeledCheckBox('unknown', 'find_unknown', $find_unknown, null, null, $go, 1);
echo " ";;
labeledCheckBox('sitters', 'find_sitters', $find_sitters, null, null, $go, 1);
echo " ";;
labeledCheckBox('clients', 'find_clients', $find_clients, null, null, $go, 1);
echo " ";;
labeledCheckBox('managers', 'find_managers', $find_managers, null, null, $go, 1);
echo " ";;
labeledCheckBox('dispatchers', 'find_dispatchers', $find_dispatchers, null, null, $go, 1);
echo " ";;
labeledCheckBox('staff', 'find_staff', $find_staff, null, null, $go, 1);
echo " ";;
labeledCheckBox('Show Login Failures Only', 'failuresonly', $failuresonly, 0, 0, $go, 1);
echo "<p>";
labeledInput('Login ID:', 'pattern', $pattern, $labelClass=null, $inputClass='Input40Chars', $onBlur=null, $maxlength=null, $noEcho=false);
echo " (use * for wildcard) ";
labeledInput('Browser patterns:', 'browserpattern', $browserpattern, $labelClass=null, $inputClass='verylonginput', $onBlur=null, $maxlength=null, $noEcho=false);
echo " (use | for separator) ";
echoButton('find', 'Find', "go(this)"); echo " ";
if($browserpattern == 'hacker') fauxLink('hackers', "ajaxGet(\"maint-logins.php?hackers=1\", \"detail\")", 0, "Examine hacker filter.");
?>
<?
echo "<p><table><tr><td><table class=biztable>";
$failures = explodePairsLine("0|Ok||L|Account locked||P|Bad password||U|Unknown user||I|Inactive User||R|RightsMissingOrMismatched||F|No Business found||B|Business inactive||M|Missing organization||O|Organization inactive||C|No cookie||S|No Native Sitter App Access||X|Wrong User Type for Native Sitter App||T|Native login with temp password||D|Logins disabled for this role");

$roles = explodePairsLine("p|P||o|O||c|C||d|D");
$titles = explodePairsLine("p|P = Sitter||o|O = Owner / Manager||c|C = Client||d|D = Dispatcher");
echo join(' - ', $titles);
foreach((array)$hackerIPs as $ip) $hackerIPKeys[$ip] = 1;
foreach((array)$likelyHackersAgents as $agent) $hackerAgentKeys[$agent] = 1;
if($result) while($line = mysql_fetch_assoc($result)) {
	$time = date('m/d/Y H:i:s', strtotime($line['time']));
	$color = $line['failurecause'] ? "style='background:pink'" : '';
	$failure = $line['failurecause'] ? $line['failurecause'] : '0';
	$failure = $failure ? loginFailureExplanation($failure) : 'Ok';//$failures[$failure];
	if(!$failure) $failure = "??{$line['failurecause']}??";
	$inactive = !$line['active'] ? 'X ' : '';
	$link = $inactive.fauxLink($line['loginid'], "ajaxGet(\"maint-logins.php?loginid={$line['loginid']}\", \"detail\")", 1, "[{$line['loginid']}]");
	$role = $roles[$line['rights'] ? substr($line['rights'], 0, 1) : ''];
	//$title = $titles[$line['rights'] ? $titles[substr($line['rights'], 0, 1)] : ''];
	$bizName = $bizdb != -1 ? '' : "<td $color>{$line['bizname']}";
	$hackerIPs = likelyHackerIPs();
	$remoteAddressColor = 
		$hackerIPKeys[$line['remoteaddress']] ? "class='warning'" : (
		$line['remoteaddress'] == mattOnlyAddress() ? "class='bold darkgreen' title=\"It's Matt!\"" : '');
	$browserColorClass =	$hackerAgentKeys[$line['browser']] ? 'warning' : '';
	echo "<tr><td $color>$time<td $color>$role<td $color>$link<td $color>$failure$bizName<td $remoteAddressColor $color>{$line['remoteaddress']}<td class='browsertoggle $browserColorClass' $color>{$line['browser']}";
//print_r($line);	
}
if(!$role) echo "<tr><td>Nothing found: $sql";
echo "</table></td><td valign=top id='detail'></td></tr></table>";
include "refresh.inc";
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function toggleBrowserColumn(el) {
	var show = '<?= strpos($_SERVER["HTTP_USER_AGENT"], 'MSIE') !== false ? 'block' : 'table-cell' ?>';
	$('.browsertoggle').css('display', (el.checked ? '' : 'none'));
}
toggleBrowserColumn(document.getElementById('showbrowsers'));
function drop(id) {
	if(!confirm("Delete "+id+"?")) return;
	var val = escape(document.getElementById(id).value);
	ajaxGetAndCallWith('maint-prefs.php?delete=1&bizdb=<?= $bizdb ?>&prop='+id, ok, 0-id);
}

function go(el) {
	var pat = document.getElementById("pattern").value;
	var browserpat = document.getElementById("browserpattern").value;
	if(el.id == 'find') {
		//if(!pat) return;
		//document.location.href="maint-logins.php?pattern="+pat;
		//return;
	}
	var bizdb = el.selectedIndex ? el.options[el.selectedIndex].value : "";
	document.location.href="maint-logins.php?bizdb="+bizdb
			+"&dayspast="+(document.getElementById("dayspast").value)
			+"&showbrowsers="+(document.getElementById("showbrowsers").checked ? 1 : 0)
			+"&failuresonly="+(document.getElementById("failuresonly").checked ? 1 : 0)
			+"&find_unknown="+(document.getElementById("find_unknown").checked ? 1 : 0)
			+"&find_sitters="+(document.getElementById("find_sitters").checked ? 1 : 0)
			+"&find_clients="+(document.getElementById("find_clients").checked ? 1 : 0)
			+"&find_managers="+(document.getElementById("find_managers").checked ? 1 : 0)
			+"&find_dispatchers="+(document.getElementById("find_dispatchers").checked ? 1 : 0)
			+"&find_staff="+(document.getElementById("find_staff").checked ? 1 : 0)
			+(pat ? "&pattern="+pat : "")
			+(browserpat ? "&browserpattern="+browserpat : "")
			;
	//document.location.href="maint-logins.php?bizdb="+el.options[document.getElementById(\"bizdb\").selectedIndex].value"
}

function newProp() {
	var prop = escape(document.getElementById('new').value);
	var val = escape(document.getElementById('newval').value);
	if(!prop) {alert('Specify a property name');return;}
	ajaxGetAndCallWith('maint-prefs.php?bizdb=<?= $bizdb ?>&new='+prop+'&val='+val, ok, prop)
}

function ok(arg, result) {
	if(result.indexOf('REFRESHNOW') != -1) refresh();
	else alert(arg+' saved: '+result);
	}
</script>
