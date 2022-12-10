<? // maint-name-search.php
// use this script by hand to modify all LT biz databases
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";


// exit;



$locked = locked('z-');

$databases = fetchCol0("SHOW DATABASES");

$pattern = $_REQUEST['pattern'];
if(!$pattern || $pattern == '*') $error = "Pattern is not specific enough.";

$windowTitle = 'Person Search';
include 'frame-maintenance.php';

?>
<form name=personsearch method=post>
<h2>Find a Person</h2>
<? 
labeledInput('Name:', 'pattern', $pattern);
echo ' ';
echoButton('', 'Find', 'document.personsearch.submit()'); 
echo "<p><font color=red>Red</font> users are inactive.  Use * for wildcards.";
?> 
</form>
<script language='javascript'>
document.getElementById('pattern').focus();
</script>

<?
if($error) {
	echo "<p>$error";
	exit;
}

function findUsers($pat) {
	if(strpos($pat, '*') !== FALSE) $pat = str_replace  ('*', '%', $pat);
	else $pat = "%$pat%";
	$sql = "SELECT userid, loginid, CONCAT_WS('&nbsp;',fname, lname) as name, email, bizptr, rights 
						FROM tbluser
						WHERE "
								."CONCAT_WS(' ',fname,lname) like '$pat'"
								." OR email like '$pat'"
								." OR loginid like '$pat'"
								." OR userid like '$pat'";
	//echo "<p>$sql<p>";
	return fetchAssociationsKeyedBy($sql, 'userid');
}

					
$users = findUsers($pattern);
//print_r($users);
//$allLoginIds = fetchAssociationsKeyedBy("SELECT userid, loginid, bizptr FROM tbluser", 'userid');
$dbs = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE activebiz=1", 'db');
uksort($dbs, 'cistrcmp');

function bizPtrAndLoginIdForUserId($userid) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$arr = fetchFirstAssoc("SELECT bizptr, loginid FROM tbluser WHERE userid = $userid LIMIT 1", 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $arr;
}

foreach($dbs as $biz) {
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	$bizptr = $biz['bizid'];
	$lnk = mysql_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		echo "Not able to connect: invalid database username and/or password.\n";
	}
	$lnk1 = mysql_select_db($db);
	if(mysql_error()) echo mysql_error();
	$tables = fetchCol0("SHOW TABLES");
	if(!in_array('tblpreference', $tables)) continue;
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");

	$clients = findClients($pattern);
	foreach((array)$clients as $i => $x) {
		if($x['userid']) {
			$u = bizPtrAndLoginIdForUserId($x['userid']);
			if($u['bizptr'] == $bizptr) {
				$clients[$i]['loginid'] = $u['loginid'];
				$clientLogins[$clients[$i]['loginid']] = $x['userid'];
			}
		}
	}
	$providers = searchProviders($pattern);
	foreach((array)$providers as $i => $x) {
		if($u['bizptr'] == $bizptr) {
			$providers[$i]['loginid'] = $u['loginid'];
			$providerLogins[$providers[$i]['loginid']] = $x['userid'];
		}
	}

	foreach($users as $i => $user) {
		if($user['bizptr'] != $biz['bizid']) continue;
		if($user['rights'][0] == 'c' && !isset($clientLogins[$user['loginid']])) {
			$person = fetchFirstAssoc("SELECT CONCAT_WS(' ', fname, lname) as label, clientid as id, tblclient.* FROM tblclient WHERE userid = {$user['userid']} LIMIT 1");
			$person['loginid'] = $user['loginid']; 
			$person['useruserid'] = $user['userid']; 
			$clients[count($clients)] = $person;
			unset($users[$i]);
		}
		else if($user['rights'][0] == 'p' && !isset($providerLogins[$user['loginid']])) {
			$person = fetchFirstAssoc("SELECT CONCAT_WS(' ', fname, lname) as label, providerid as id, tblprovider.* FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");
			$person['loginid'] = $user['loginid']; 
			$person['useruserid'] = $user['userid']; 
			$providers[count($providers)] = $person;
			unset($users[$i]);
		}
	}
	
	
	$userids = array_merge(array_map('userId', (array)$clients), array_map('userId', (array)$providers));
	$roles = array('o'=>'Manager','p'=>'Sitter', 'c'=>'Client', 'd'=>'Dispatcher');
	$bizUsers = array();
	foreach($users as $user) if($user['bizptr'] == $bizptr) $bizUsers[] = $user;
	if($clients || $providers|| $bizUsers) {
		echo "<hr><a href='maint-edit-biz.php?id=$bizptr'><b>$bizName ($db)</a></b>: <img src='art/branch.gif' onclick='stafflogin($bizptr)'><p>";
		if($clients) echo "<u>Clients</u>:<ul><li>".join('<li>', array_map('nameLine', $clients))."</ul>";
		if($providers) echo "<u>Sitters</u>:<ul><li>".join('<li>', array_map('nameLine', $providers))."</ul>";
		foreach($bizUsers as $user) {
			$userLink = fauxLink($user['userid'],
														"openConsoleWindow(\"logineditor\", \"maint-edit-user.php?userid={$user['userid']}&bizptr=$bizptr\", 600,400)",
														1);
			
			if($user['bizptr'] != $bizptr || in_array($user['userid'], $userids)) continue;
			echo "<u>{$roles[$user['rights'][0]]}</u>: {$user['name']} [username: {$user['loginid']}] [userid: $userLink] [{$user['email']}]<br>";
		}
	}
}

function cistrcmp($a, $b) {return strcmp(strtolower($a),strtolower($b)); }
function nameLine($arr) {
	global $bizptr;
	$userid = $arr['userid'] ? $arr['userid'] : $arr['useruserid'];
	$userLink = fauxLink($userid,
												"openConsoleWindow(\"logineditor\", \"maint-edit-user.php?userid=$userid&bizptr=$bizptr\", 600,400)",
												1);
	
	$loginid = $arr['loginid'] ? "[username: {$arr['loginid']}]" : '';
	$line = "{$arr['label']} $loginid [{$arr['id']}] [userid: $userLink] {$arr['email']}";
	$line = $arr['active'] ? $line : "<font color=red>$line</font>";
	return "<span title=\"{$arr['title']}\">$line </span>";
}
function userId($arr) { return $arr['userid']; }

function findClients($pat){
	$petstoo = 1;
	$clients = array();
	$pets = array();
	$idSearch = $pat && preg_match('/^\@[0-9]{1,5}$/', $pat);
	if($idSearch && strlen($pat) == 1) return;

	if($petstoo && !$idSearch) {
		$baseQuery = "SELECT tblclient.*, CONCAT_WS('&nbsp;',fname,lname, concat('(',name,')')) as name
											FROM tblpet LEFT JOIN tblclient on ownerptr = clientid
											WHERE tblpet.active AND tblclient.active";
		$orderBy = "ORDER BY lname, fname, tblpet.name";
		//$limit = "LIMIT 15";
		if(isset($pat)) {
			if(strpos($pat, '*') !== FALSE) $pat = str_replace  ('*', '%', $pat);
			else $pat = "%$pat%";
			$baseQuery = "$baseQuery AND tblpet.name like '$pat'";
			//$numFound = mysql_num_rows(mysql_query($baseQuery));
			//if($numFound)
				$pets = fetchAssociationsKeyedBy("$baseQuery $orderBy $limit", 'clientid');
		}
	}

	$baseQuery = "SELECT *, CONCAT_WS(' ',fname,lname) as name FROM tblclient 
										WHERE 1=1";
	$orderBy = "ORDER BY lname, fname";
	$limit = "LIMIT 15";
	if(isset($pat)) {
		if($idSearch) $pat = substr($pat, 1);
		else {
			if(strpos($pat, '*') !== FALSE) $pat = str_replace  ('*', '%', $pat);
			else $pat = "%$pat%";
		}
		
		if($idSearch) 
			$baseQuery = "$baseQuery AND clientid = $pat";
		else
			//$baseQuery = "$baseQuery AND CONCAT_WS(' ',fname,lname) like '$pat'";
			$baseQuery = "$baseQuery AND ("
							."CONCAT_WS(' ',fname,lname) like '$pat'"
							." OR CONCAT_WS(' ',fname2,lname2) like '$pat'"
							." OR email like '$pat'"
							." OR email2 like '$pat'"
							." OR homephone like '$pat'"
							." OR workphone like '$pat'"
							." OR cellphone like '$pat'"
							." OR street1 like '$pat'"
							." OR mailstreet1 like '$pat'"
							.") ";

		
		
		//$numFound = mysql_num_rows(mysql_query($baseQuery));
		//if($numFound)
		$clients = fetchAssociationsKeyedBy("$baseQuery $orderBy $limit", 'clientid');
		if($clients) {
			$clientIds =  join(',',array_keys($clients));
			$clientPets = fetchAssociations("SELECT ownerptr as clientptr, name FROM tblpet WHERE active AND ownerptr IN ($clientIds)");
			$petLists = array();
			if($clientPets) {
				foreach($clientPets as $cp) $petLists[$cp['clientptr']][] = $cp['name'];
				foreach($petLists as $k => $v) $clients[$k]['petnames'] = " (".truncatedLabel(join(',',$v), 12).")";
			}
		}
	}

	$rows = array();

	foreach($clients as $client) {
		unset($pets[$client['clientid']]);
		$client['id'] = $client['clientid'];
		$client['label'] = "{$client['name']}{$client['petnames']}";
		$client['title'] = null;
//print_r($client); exit;		
		foreach(explode(',', 'email,email2,homephone,workphone,cellphone,street1,mailstreet1') as $fld)
			if($client[$fld]) $client['title'][] = $client[$fld];
		$client['title'] = $client['title'] ? join(', ', $client['title']) : '';
		$rows[] = $client;
	}
	if($pets) {
		if($clients) $rows[] = "--";
		foreach($pets as $client)
			$client['id'] = $client['clientid'];
			$client['label'] = $client['name'];
			$rows[] = $client;
	}

	return $rows;
	
}

function searchProviders($pat) {
	// strip leading $
	$baseQuery = "SELECT *, 
										if(nickname IS NULL, CONCAT_WS(' ',fname,lname),
											CONCAT_WS(' ',CONCAT_WS(' ',fname,lname), CONCAT('(', nickname, ')'))) as name 
								FROM tblprovider 
								WHERE 1=1";
	$orderBy = "ORDER BY lname, fname";
	//$limit = "LIMIT 15";
	if(isset($pat)) {
		if(strpos($pat, '*') !== FALSE) $pat = str_replace  ('*', '%', $pat);
		else $pat = "%$pat%";
		$baseQuery = "$baseQuery AND (CONCAT_WS(' ',fname,lname) like '$pat' OR nickname like '$pat' OR email like '$pat')";
		//$numFound = mysql_num_rows(mysql_query($baseQuery));
		//if($numFound)
		$providers = fetchAssociationsKeyedBy("$baseQuery $orderBy $limit", 'providerid');
		foreach($providers as $provider) {
			$provider['id'] = $provider['providerid'];
			$provider['label'] = $provider['name'];
			$provider['title'] = null;
			foreach(explode(',', 'email,homephone,workphone,cellphone,street1') as $fld)
				if($provider[$fld]) $provider['title'][] = $provider[$fld];
			$provider['title'] = $provider['title'] ? join(', ', $provider['title']) : '';
			
			$rows[] = $provider;
		}
		return $rows;
	}
}
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>
function stafflogin(bizid) {
	ajaxGetAndCallWith("lt-staff-login.php?bizptr="+bizid, confirmLogin, bizid)
}

function confirmLogin(bizid, response) {
	if(response.indexOf("FAIL") > -1) alert(response);
	else if(response == "SUCCESS") document.location.href='index.php';
	else if(confirm(response))
		ajaxGetAndCallWith("lt-staff-login.php?confirmed=1&bizptr="+bizid, confirmLogin, bizid)
}
</script>