<?
// getSearchMatches.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Determine access privs
if(locked('o-', true)) return '';



extract($_REQUEST);

//$includeEmails from $_REQUEST

if($pat == '??') {
	echo "POP:cheatsheet:".globalURL('getSearchMatches.php?cheatsheet=1');
	exit;
}
if($cheatsheet) {
	$dictionary =
"$...|Search for sitters (e.g., \$jones)
-...|Search for inactive clients (e.g., -smith) or sitters (e.g., -\$jones)
NNNN-NN|Search for client by Key ID (e.g., 0032-02)
#...|Search for an active client and jump to the client's Key page. (e.g., #smith)<br><b>#-...</b> for inactive clients (e.g., #-smith)
hook:...|Search for client by Key hook (e.g., hook:3 or hook:JB-22)
@clientid|Search for client by Client ID (e.g., @750)
(...|Search for client by login username. e.g., (miller
+NNN...|Search for client by phone number.  Numbers only match.<br>Example: +70355 will find clients with these phone numbers:<br>(703)551-1212, 270-355-1598, 301/703/5520, etc
??|Open up this cheat sheet.


";
	require_once "gui-fns.php";
	$dictionary = explodePairPerLine($dictionary);
	if(staffOnlyTEST()) {
		$dictionary['== STAFF ONLY =='] = null;
		$dictionary['!userid'] = "Search for client by userid (e.g., !98234)";
		require_once "search-fns.php";
		$dictionary[shortCutEditorWakeWord()] = "Shortcut wake word";
		foreach(getShortCuts() as $k=>$v) $dictionary[$k] = $v;		
	}

	require_once "frame-bannerless.php";
	echo "<h2>Search Usage</h2>";
	echo "<p style='font-size:1.2em;'>Typing part of a client name, pet name, address, etc. will bring up "
				."a list of active clients to choose from as you type.<br>&nbsp;<br>  - Examples: Mary, Tucker, 3rd St<br>&nbsp;<br>But the following patterns will produce different results:</p>";
	echo "<style>td {font-size:1.2em;border-bottom:solid gray 1px;}</style>";
	echo "<table>";
	foreach($dictionary as $k=>$v)
		echo "<tr><td style='font-weight:bold'>$k</td><td>$v</td></tr>";
	echo "</table>";
	echo "<p style='font-size:1.2em;'>See also: <a target='_blank' href=\"https://leashtime.com/info/how-to-use-the-search-box-in-leashtime/\">&#127902; How To Use The Search Box in LeashTime</a></p>";
}

require_once "search-fns.php";
if(($shortcuts = getShortCuts()) && $shortcuts[$pat]) {
	echo "GOTO:{$shortcuts[$pat]}";
	exit;
}

if(staffOnlyTEST()) {
	if($pat == shortCutEditorWakeWord()) {
		echo "POP:shortcuteditor:".globalURL('getSearchMatches.php?shortcuteditor=1');
		exit;
	}
	else if($_POST['shortcuteditor']) {
		postSearchShortCuts($_POST);
		echo "SAVED.<p>";
		editShortCuts();
		exit;
	}
	else if($_GET['shortcuteditor']) {
		editShortCuts();
		exit;
	}
	else {
	}

}



if(!$clientsOnly) {
	// email queue
	if($pat == ".q" && $_SESSION['staffuser']) {
		echo "GOTO:email-queue.php";
		exit;
	}

	// find key
	if($_SESSION['secureKeyEnabled'] && preg_match('/^[0-9]{4}\-[0-9]{2}$/', $pat)) {
		require_once "key-fns.php";
		$key = getKey($pat);
		if($key) {
			$parts = explode('-', $pat);
			if(count($parts) == 2) {
				$copy = (int)($parts[1]);
				$loc = $key["possessor$copy"];
				if($loc && strpos("$loc", 'safe') !== FALSE) $destination = "key-check-out.php?keyid=$pat";
				else if($loc) $destination = "key-check-in.php?keyid=$pat";
				else $destination = "key-edit.php?id=$pat";
				echo "GOTO:$destination";
				exit;
			}
		}
	}
	// find invoice id
	else if(preg_match('/^LT[0-9]{4,5}$/', strtoupper($pat))) {
		$invoiceid = substr($pat, 2);
		//$invoiceid = fetchRow0Col0("SELECT invoiceid FROM tblinvoice WHERE invoiceid = $invoiceid LIMIT 1");
		//if($invoiceid) echo "POP:invoiceviewer:invoice-view.php?id=$invoiceid";
		$invoice = fetchFirstAssoc("SELECT * FROM tblinvoice WHERE invoiceid = $invoiceid LIMIT 1");
		//print_r($invoice);
		if($invoice) {
			$clientptr = $invoice['clientptr'];
			$date = $invoice['date'];
		}
		if($clientptr) {
			echo "GOTO:client-edit.php?tab=account&id=$clientptr&invoiceStart=$date";
			exit;
		}
	}
	// training mode
	else if(strtoupper($pat) == 'TRAININGMODE'){
		require_once("training-fns.php");
		turnOnTraningMode();
		$_SESSION['user_notice'] = welcomeText();
		echo "GOTO:index.php";
		exit;
	}

	// end training mode
	else if(strtoupper($pat) == 'ENDTRAINING'){
		require_once("training-fns.php");
		turnOffTrainingMode();
			echo "GOTO:index.php";
			exit;
	}

	if(isset($pat)) $pat = leashtime_real_escape_string($pat);
	
	// find staff
	if(strpos($pat, '//STAFF//') === 0) {
		searchStaff(substr($pat, strlen('//STAFF//')));
		exit;
	}

	// find sitters
	if(strpos($pat, '$') === 0 || strpos($pat, '-$') === 0) {
		searchProviders($pat);
		exit;
	}

	// find keys for client found using pattern
	if(strpos($pat, '#') === 0) { // ????
		searchClientKeys($pat);
		exit;
	}
	
	// find keys for key hook
	if(strpos($pat, 'hook:') === 0) { // ????
		findClientsByKeyHook($pat, 'hook:');
		exit;
	}
	

}

echo join('||', findClients($pat));


function findClientsByKeyHook($pat, $prefix) {
	if(strlen($pat) > strlen('hook#')) {
		$subpat = substr($pat, strlen('hook#'));
		$clientptrs = fetchCol0("SELECT clientptr FROM tblkey WHERE bin LIKE '%$subpat%'");
		if($clientptrs) {
			$baseQuery = "SELECT clientid, fname, lname,
												CONCAT_WS(' ',fname,lname) as name, 
												CONCAT_WS(', ',lname,fname) as sortname FROM tblclient 
											WHERE clientid IN (".join(',', $clientptrs).") ORDER BY lname, fname";
			$clients = fetchAssociationsKeyedBy("$baseQuery $orderBy $limit", 'clientid');
			if($clients) {
				//if(!$idSearch && $globalPattern) uasort($clients, 'clientSort');
				$clientIds =  join(',',array_keys($clients));
				$clientPets = fetchAssociations("SELECT ownerptr as clientptr, name FROM tblpet WHERE active AND ownerptr IN ($clientIds)");
				$petLists = array();
				if(!defined('truncatedLabel')) {
					function truncatedLabel($str, $length) {
						if(strlen($str) <= $length) return $str;
						return substr($str, 0, $length-3).'...';
					}
				}
				if($clientPets) {
					foreach($clientPets as $cp) $petLists[$cp['clientptr']][] = $cp['name'];
					foreach($petLists as $k => $v) $clients[$k]['petnames'] = " (".truncatedLabel(join(',',$v), 12).")";
				}
				foreach($clients as $client)
					$rows[] = "{$client['clientid']}|{$client['name']}{$client['petnames']}";
				echo join('||', $rows);
			}
		}
	}
}


function findClients($pat){
	// @xxxx = find by client ID
	// !xxxx = find by client userid
	// *xxxx = find by client loginid
	// +nnnn = find by client phone number
	global $globalPattern;
	$rawPat = $pat;

	$petstoo = 1;
	$clients = array();
	$pets = array();
	$idSearch = $pat && preg_match('/^\@[0-9]{1,5}$/', $pat);
	if($idSearch && strlen($pat) == 1) return;
	$userIdSearch = $pat && preg_match('/^\![0-9]{1,7}$/', $pat);
	if($userIdSearch && strlen($pat) == 1) return;
	$loginIdSearch = $pat && preg_match('/^\(/', $pat);
	if($loginIdSearch && strlen($pat) == 1) return;
	$phoneSearch = $pat && preg_match('/^\-{0,1}\+[0-9]{1,7}$/', $pat);
//if(mattOnlyTEST()) {echo "PAT[$pat] phoneSearch[$phoneSearch]||ddd";exit;}

	$activestatus = 1;
	if(strpos($pat, '-') === 0) {
		$activestatus = '0';
		$pat = substr($pat, 1);
		if(strlen($pat) < 2)
			return array("");
	}
	$activityClause = strpos($pat, '@') === 0 || strpos($pat, '!') === 0 ? '1=1' : "tblclient.active = $activestatus";
	
	if($petstoo && !$idSearch && !$userIdSearch && !$loginIdSearch) {
		$petactivity = staffOnlyTEST() ? "1=1" : "tblpet.active = 1";
		$dagger = "&#8224;";
		$baseQuery = "SELECT clientid, CONCAT_WS('&nbsp;',fname,lname, 
										concat('(',if(tblpet.active=1, '', '$dagger'), name,')')) as name 
									 FROM tblpet left join tblclient on ownerptr = clientid
									 WHERE $petactivity AND $activityClause";
		$orderBy = "ORDER BY lname, fname, tblpet.name";
		//$limit = "LIMIT 15";
		if(isset($pat)) {
			if(strpos($pat, '*') !== FALSE) $pat = str_replace  ('*', '%', $pat);
			else {
				$pat = "%$pat%";
				$globalPattern = $pat;
			}
			$baseQuery = "$baseQuery AND tblpet.name like '$pat'";
			//$numFound = mysql_num_rows(mysql_query($baseQuery));
			//if($numFound)
				$pets = fetchAssociationsKeyedBy("$baseQuery $orderBy $limit", 'clientid');
		}
	}

	$baseQuery = "SELECT clientid, fname, lname,
											CONCAT_WS(' ',fname,lname) as name, 
											CONCAT_WS(', ',lname,fname) as sortname,
											email, email2,
											userid
										FROM tblclient 
										WHERE $activityClause";
	$orderBy = "ORDER BY lname, fname";
	//$limit = "LIMIT 15";
	if(isset($pat)) {
		if($idSearch || $userIdSearch || $loginIdSearch) $pat = substr($pat, 1);
		else {
			if(strpos($pat, '*') !== FALSE) $pat = str_replace  ('*', '%', $pat);
			else $pat = "%$pat%";
		}
		
		if($idSearch) 
			$baseQuery = "$baseQuery AND clientid = $pat";
		else if($userIdSearch)
			$baseQuery = "$baseQuery AND userid = $pat";
		else if($loginIdSearch) {
			$userids = findClientUserIdsByLoginId($pat);
			if(!$userids) $baseQuery = "$baseQuery AND 1=0";  // return nothing
			else $baseQuery = "$baseQuery AND userid IN (".join(',', $userids).")"; // find clients for these user ids
		}
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
							." OR cellphone2 like '$pat'"
							." OR street1 like '$pat'"
							." OR mailstreet1 like '$pat'"
							.") ";


		//$numFound = mysql_num_rows(mysql_query($baseQuery));
		//if($numFound)
		if($phoneSearch) $clients = phoneSearch($rawPat); else // ...
		$clients = fetchAssociationsKeyedBy("$baseQuery $orderBy $limit", 'clientid');
		if($clients) {
			//if(!$idSearch && $globalPattern) uasort($clients, 'clientSort');
			
			if($userIdSearch) {
				// if user IDs found, eliminate all that do not belong to this biz
				foreach($clients as $client) 
					$clientUserIds[$client['userid']] = $client['clientid'];
				$disallowedClientUserIds = userIdsNotInThisDatabase(array_keys($clientUserIds));
				foreach($disallowedClientUserIds as $userid) 
					unset($clients[$clientUserIds[$userid]]);
			}
			
			$clientIds =  join(',',array_keys($clients));
			$clientPets = fetchAssociations(
				"SELECT ownerptr as clientptr, name 
					FROM tblpet 
					WHERE active AND ownerptr IN ($clientIds)");
			$petLists = array();
			if(!defined('truncatedLabel')) {
				function truncatedLabel($str, $length) {
					if(strlen($str) <= $length) return $str;
					return substr($str, 0, $length-3).'...';
				}
			}
			if($clientPets) {
				foreach($clientPets as $cp) $petLists[$cp['clientptr']][] = $cp['name'];
				foreach($petLists as $k => $v) $clients[$k]['petnames'] = " (".truncatedLabel(join(',',$v), 12).")";
			}
		}
	}

	$rows = array();

	if(true) {
//if(mattOnlyTEST()) echo print_r(count($clients), 1).'///';		
		$groups = smartSort($clients, $rawPat); 
		foreach((array)$clients as $client) unset($pets[$client['clientid']]);
		foreach((array)$groups['lastnames'] as $client) $rows[] = "{$client['clientid']}|{$client['name']}{$client['petnames']}";
		if($groups['firstnames']) {
			if($rows) $rows[] = "--";
			foreach($groups['firstnames'] as $client) $rows[] = "{$client['clientid']}|{$client['name']}{$client['petnames']}";
		}
		if($pets) {
			if($rows) $rows[] = "--";
			foreach($pets as $client)
				$rows[] = "{$client['clientid']}|{$client['name']}";
		}
		if($groups['others']) {
			if($rows) $rows[] = "--";
			foreach($groups['others'] as $client) $rows[] = "{$client['clientid']}|{$client['name']}{$client['petnames']}";
		}
	}
	else { // NEVER REACHED
		foreach($clients as $client) {
			unset($pets[$client['clientid']]);
			$rows[] = "{$client['clientid']}|{$client['name']}{$client['petnames']}";
		}
		if($pets) {
			if($clients) $rows[] = "--";
			foreach($pets as $client)
				$rows[] = "{$client['clientid']}|{$client['name']}";
		}
	}
	//if(mattOnlyTEST()) $rows[] = "-99|$rawPat";
	if(staffOnlyTEST() && is_numeric($rawPat)) {
		// phone search
		foreach($rows as $i => $row) {
			$id = substr($row, 0, strpos($row, '|'));
			$nums = fetchFirstAssoc("SELECT homephone, workphone, cellphone, cellphone2 FROM tblclient WHERE clientid = $id LIMIT 1");
			if($nums) require_once "field-utils.php";
			$found = null;
			foreach($nums as $num)
				if(strpos($num, $rawPat) !== FALSE)
					$found[] = canonicalUSPhoneNumber($num);
			if($found) 
				$rows[$i] = "$id|(".join(', ', $found).") ".substr($row, strpos($row, '|')+1);
		}
	}
	
	global $includeEmails;
	if($rows && $includeEmails) {
		foreach($rows as $row) {
			$rowId = substr($row, 0, strpos($row, '|')); 
			if($rowId) $emails[] = $rowId;
		}
		$emails = fetchAssociationsKeyedBy("SELECT clientid, email, email2 FROM tblclient WHERE (email IS NOT NULL OR email2 IS NOT NULL) AND clientid IN (".join(',', $emails).")", 'clientid', 1);
		foreach($rows as $i=>$row) {
			$clientid = substr($row, 0, strpos($row, '|'));
//if($row['email']) return array(print_r($emails,1));			
			$emailFields = $emails[$clientid];
			$add = array();
			if(strlen("{$emailFields['email']}") > 0) $add[] = $emailFields['email'];
			if(strlen("{$emailFields['email2']}") > 0) $add[] = $emailFields['email2'];
			if($add)$rows[$i] = $row.'|'.join(',', $add);
		}
	}

	return $rows;
	
}

function phoneSearch($rawPat) {
	$inactive = $rawPat[0] == '-' ? 1 : 0;
	$pat = substr($rawPat, 1+$inactive);
	$result = doQuery(
		"SELECT clientid, homephone, workphone, cellphone, cellphone2
			FROM tblclient
			WHERE (homephone IS NOT NULL OR workphone IS NOT NULL OR cellphone IS NOT NULL OR cellphone2 IS NOT NULL) AND "
				.($inactive ? 'active=0' : 'active=1'), 1);
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  	foreach($row as $k=>$v) {
			if($k == 'clientid' || !$v) continue;
			$rawnum = preg_replace("/[^0-9]/", "", $v);
			if(!$rawnum) continue;
//echo "[$pat] IN [$rawnum] ? ".strpos($rawnum, $pat)."<br>";
			if(strpos("$rawnum", "$pat") !== FALSE) {
				$clientids[] = $row['clientid'];
				$numbers[$row['clientid']] = $v;
				break;
			}
		}
	}
	if(!$clientids) return array();
	$found = fetchAssociationsKeyedBy(
		"SELECT clientid, fname, lname,
						CONCAT_WS(' ',fname,lname) as name, 
						CONCAT_WS(', ',lname,fname) as sortname,
						userid
			FROM tblclient
			WHERE clientid IN (".join(',', $clientids).")", 'clientid', 1);
	
	// prepend found phone to name
	foreach($found as $clientid => $row)
		$found[$clientid]['name'] = "({$numbers[$row['clientid']]}) {$row['name']}";
	return $found;
}
	
  	

function findClientUserIdsByLoginId($pat) {
	if(!$pat) return array();
	$pat = leashtime_real_escape_string("%$pat%");
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require_once "common/init_db_common.php";
	$foundUsers = fetchCol0("SELECT userid FROM tbluser WHERE loginid LIKE '$pat' AND bizptr = {$_SESSION['bizptr']} AND rights LIKE 'c-%'", 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $foundUsers;
}

function userIdsNotInThisDatabase($userIds) {
	if(!$userIds) return array();
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require_once "common/init_db_common.php";
	$disallowedClientUserIds = fetchCol0(
		"SELECT userid 
			FROM tbluser 
			WHERE userid IN (".join(',', $userIds).")
				AND bizptr != {$_SESSION['bizptr']}", 1);
	return $disallowedClientUserIds;
}

function trimmedArray($arr, $limit) {
	if(!$arr) return $arr;
	for($i=0; $i<min(count($arr), $limit); $i++)
		$result[] = $arr[$i];
	return $result;
}

function smartSort(&$clients, $pat) {
	// find clients whose last names or first names START with $pat
	// and move them to the top of the list
	if(!$clients) return $clients;
	$pat = strtoupper($pat);
	foreach($clients as $client) {
		if(strpos(strtoupper($client['lname']), $pat) === 0) $lnames[] = $client;
		else if(strpos(strtoupper($client['fname']), $pat) === 0) $fnames[] = $client;
		else $others[] = $client;
	}
	
	// limit the numbers returned
	$maxReturned = 45;
	$lnames = trimmedArray($lnames, 15);
	$fnames = trimmedArray($fnames, 15);
	$others = trimmedArray($others, 15);
//echo  "$pat<hr>".print_r($lnames, 1).'<hr>'.print_r($fnames, 1).'<hr>'.print_r($others, 1).'<hr>';
	$result['lastnames'] = $lnames;
	$result['firstnames'] = $fnames;
	$result['others'] = $others;
	return $result;
}

/*function clientSort($a, $b) {
	global $globalPattern;
	$matchA = strpos($a['name'], $globalPattern) !== FALSE ? "a" : "z";
	$matchB = strpos($b['name'], $globalPattern) !== FALSE ? "a" : "z";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo "[[{$a['name']}: $matchA, {$b['name']}: $matchB]]";
	return ($result = strcmp($matchA, $matchB)) < 0 ? -1 : (
	       $result > 0 ? 1 : strcmp($a['sortname'], $b['sortname']));
}*/

function searchClientKeys($pat){
	if(strlen($pat) < 3) return;
	if(!(adequateRights('ka') || adequateRights('#km'))) return;
	$rows = findClients(substr($pat, 1));
	if($rows) echo 'KEYS:'.join('||',$rows);
}

function searchStaff($pat) {
	global $includeEmails;
	$bigPat = strtoupper($pat);
	$mgrs= getManagers($ids=null, $ltStaffAlso=false);
	foreach($mgrs as $mgr) {
		if(strpos(strtoupper($mgr['name']), $bigPat) !== FALSE ||
			strpos(strtoupper("{$mgr['email']}"), $bigPat) !== FALSE ||
			strpos(strtoupper("{$mgr['loginid']}"), $bigPat) !== FALSE) {
			$row = "{$mgr['userid']}|{$mgr['name']}";
			if($includeEmails && $mgr['email']) $row .= "|{$mgr['email']}";
			$rows[] = $row;
		}
	}
	if($rows) echo 'STAFF:'.join('||',$rows);
}

	
function searchProviders($pat) {
	// strip leading $
	$activeTest = 'active=1';
	if(strpos($pat, '$')===0) $pat = substr($pat, 1);
	else if(strpos($pat, '-$')===0) {
		$pat = substr($pat, 2);
		$activeTest = 'active=0';
	}
	
	if(strlen($pat) < 2) return;
	$baseQuery = "SELECT providerid, 
										if(nickname IS NULL, CONCAT_WS(' ',fname,lname), 
											CONCAT_WS(' ',CONCAT_WS(' ',fname,lname), CONCAT('(', nickname, ')'))) as name FROM tblprovider 
										WHERE $activeTest";
	$orderBy = "ORDER BY lname, fname";
	$limit = "LIMIT 15";
	if(isset($pat)) {
		if(strpos($pat, '*') !== FALSE) $pat = str_replace  ('*', '%', $pat);
		else $pat = "%$pat%";

		$baseQuery = "$baseQuery AND 
			(CONCAT_WS(' ',fname,lname) like '$pat' OR nickname like '$pat' 
				OR email like '$pat' 
				OR homephone like '$pat'
				OR workphone like '$pat'
				OR cellphone like '$pat'
				OR street1 like '$pat'
				)";

		//$numFound = mysql_num_rows(mysql_query($baseQuery));
		//if($numFound)
		$providers = fetchAssociationsKeyedBy("$baseQuery $orderBy $limit", 'providerid');
		foreach($providers as $provider) 
			$rows[] = "{$provider['providerid']}|{$provider['name']}";
			
	global $includeEmails;
//if($includeEmails) {echo 	"BANG!";exit;}
	if($rows && $includeEmails) {
		foreach($rows as $row) {
			$rowId = substr($row, 0, strpos($row, '|')); 
			if($rowId) $emails[] = $rowId;
		}
		$emails = fetchAssociationsKeyedBy("SELECT providerid, email FROM tblprovider WHERE email IS NOT NULL AND providerid IN (".join(',', $emails).")", 'providerid', 1);
		foreach($rows as $i=>$row) {
			$provid = substr($row, 0, strpos($row, '|'));
//if($row['email']) return array(print_r($emails,1));			
			$emailFields = $emails[$provid];
			$add = array();
			if(strlen("{$emailFields['email']}") > 0) $add[] = $emailFields['email'];
			if($add)$rows[$i] = $row.'|'.join(',', $add);
		}
	}
			
			
			
			
		if($rows) echo 'PROVIDERS:'.join('||',$rows);
	}
}
	