<? // frame-maintenance.php

function loginAndEditElements($bizid) {
	return "<a href='maint-edit-biz.php?id=$bizid'>edit</a> <img src='art/branch.gif' onclick='stafflogin($bizid)'>";
}
	

function getBizUsers($biz) {
	list($dbhost, $db, $dbuser, $dbpass) = array($biz['dbhost'], $biz['db'], $biz['dbuser'], $biz['dbpass']);
	$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);

	if ($lnk < 1) {
		$errMessage ="Not able to connect: invalid database username and/or password.";
		echo $errMessage;
	}

	if(!mysqli_select_db($db)) echo "Failed to select [$db]: ".mysqli_error();
	
	$users = array();
	foreach(fetchAssociations("SELECT * FROM tblprovider") as $u) 
		if($u['userid']) $users[$u['userid']] = $u;
	$result = doQuery("SELECT * FROM tblclient");
  while($u = mysqli_fetch_array($result, MYSQL_ASSOC))
   if($u['userid']) $users[$u['userid']] = $u;
//echo "BANG!<p>";print_r(count($users));exit;
	foreach($users as $id => $u) $users[$id]['name'] = $u['fname'].' '.$u['lname'];

	include "common/init_db_common.php";
	return $users;
	
}

if(!function_exists('getMaintRights')) {
	function getMaintRights() {
		$rights = $_SESSION['rights'];
		return explode(',', (strlen($rights) > 2 ? substr($rights, 2) : ''));
	}
}
// Tiers|maint-count-providers.php||
// ||Olark|olark-agent.php
$line = 'Businesses|maint-dbs.php||Search|maint-name-search.php||Olark|olark-agent.php||Reports|maint-cross-report.php'
					.'||Users|maint-users.php||Errors|maint-log.php||Client Setup|setup/client-setup.php'
					.'||Config Check|maint-config-check.php||Prefs|maint-prefs.php||Logins|maint-logins.php'
					.'||Change Password|maint-password-change.php||Amnesia|maint-amnesia.php||Clocks|clocks||Logout|login-page.php?logout=1';
$titlesRaw = 'Search|Find a person in LeashTime';
foreach(explode('||', $titlesRaw) as $piece) {
	$pair = explode('|', $piece);
	$titles[$pair[0]] = $pair[1];
}
					
foreach(explode('||', $line) as $piece) {
	$pair = explode('|', $piece);
		if(!mattOnlyTEST()) {
			if(in_array($pair[0], array('XXOlark','Reports')))
				continue;
		}

	if($pair[0] == 'Clocks') {
		if($_SESSION['auth_login_id'] != 'maestro') continue;
		$links[] = "<a href='javascript:showClocks()'>{$pair[0]}</a>";
		continue;
	}
	if($pair[0] == 'Olark') {
		$links[] = "<a href='javascript:olarkLogin()'>{$pair[0]}</a>";
		continue;
	}
	$rights = $_SESSION['rights'];
	if(strlen($rights) > 2) {
		$rights = explode(',', substr($rights, 2));
		$businessesOnly = FALSE; //in_array('b', $rights);
		if($businessesOnly && !in_array($pair[0], array('Businesses', 'Search', 'Logout', 'Change Password', 'Logins', 'Clocks')))
			continue;
	}

	if($bizdb) {
		if($pair[1] == 'maint-prefs.php') $pair[1] .= "?bizdb=$bizdb";
		else if($pair[1] == 'maint-count-providers.php') $pair[1] .= "?date=".date('Y-m-01')."&bizdb=$bizdb";
	}
	$title = $titles[$pair[0]] ? "title='{$titles[$pair[0]]}'" : '';
	$label = $pair[0];
	if($label == 'Search') $label .= "<img title='{$titles[$pair[0]]}' src='{$pathHome}art/flag-person-question.gif' height=13 width=14>";
	$links[] = "<a href='$pathHome"."{$pair[1]}' $title>$label</a>";
	if($pair[0] == 'Businesses') $links[count($links)-1] .= "<img title='Find a biz by name or manager name' src='{$pathHome}art/magnifier.gif' onclick='quickFind()' height=13 width=14>";
}

if($_SESSION["auth_login_id"] == 'maestro') {
	require "common/init_db_common.php";
	$ltbizZUZU = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1", 1);
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	reconnectPetBizDB($ltbizZUZU['db'], $ltbizZUZU['dbhost'], $ltbizZUZU['dbuser'], $ltbizZUZU['dbpass'], $force=true);
	$requests = fetchAssociations("SELECT * FROM tblclientrequest WHERE resolved = 0 AND requesttype = 'BizSetup' ORDER BY received DESC");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, $force=true);
	require "common/init_db_common.php";
	foreach($requests as $req) $warning[] = "{$req['fname']} {$req['lname']} (".date('m/d', strtotime($req['received'])).')';
	if($warning) {
		$warning = "SETUPS WAITING:\n".join(",\n", $warning);
		$links[] = "<img src='/art/pulsar25.gif' title=\"$warning\">";
	}
}

if(TRUE || !strpos($_SERVER["HTTP_USER_AGENT"], 'MSIE')) 
echo
'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';

else echo
'<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN">'


?>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />  
	<link rel="stylesheet" href="style.css" type="text/css" /> 
	<link rel="stylesheet" href="pet.css" type="text/css" /> 
	<script type="text/javascript" src="<?= $pathHome ?>jquery_1.3.2_jquery.min.js"></script>
	<script type="text/javascript" src="<?= $pathHome ?>colorbox/jquery.colorbox.js"></script>
  <link rel="stylesheet" href="c<?= $pathHome ?>olorbox/example1/colorbox.css" type="text/css" />
  <title>LeashTime STAFF<?= $windowTitle ? ": $windowTitle" : ''?> </title> 
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
	function showClocks() {
		$.fn.colorbox(
			{href: "clocks.php", 
			 width:"850", height:"270", iframe:true, scrolling: "auto", opacity: "0.3"	});
	}
	
	function olarkLogin() {
		$.fn.colorbox(
			{href: "olark-agent.php", 
			 width:"350", height:"270", iframe:true, scrolling: "auto", opacity: "0.3"	});
	}
	
	function quickFind() {
		$.fn.colorbox(
			{href: "maint-biz-search.php", 
			 width:"800", height:"800", iframe:false, scrolling: "auto", opacity: "0.3",
			 onComplete: function() {$('#pat').focus();}
			});
	}
	
	function search(el) {
		var pat = el.value;
		if(!pat || pat.length < 2) return;
		$.ajax({url: 'maint-biz-search.php?pat='+pat, success: function(data) {$('#resultsDiv').html(data);}});
	}


	</script>
  
</head> 
<? if(!in_array('b', getMaintRights())) { ?> 
<? } ?>
<?= ''//!in_array('b', getMaintRights()) ? 'left:35px;' : '' ?>
<img src='<?= $pathHome ?>art/lightning-smile-small.jpg' onclick='stafflogin(68)' style='position:absolute;left:0px;top:0px;width:30px;height:33px;cursor:pointer;' title='Login to LeashTime Customers'>
<div style='font-size:1.2em;position:relative;left:35px;height:25px'>
<? echo join(' - ', $links); 
if(FALSE && mattOnlyTEST()) {  // FALSEd out 6/22/2021 This is where the indicator showing that the LeashTime Sentinel was not checking in used to be isplayed.
	$lastHeartbeat = fetchRow0Col0("SELECT time FROM tblchangelog WHERE itemtable = 'heartbeat' ORDER BY time desc LIMIT 1");
	$now = fetchRow0Col0("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s');");
	$lastHeartbeatPretty = date('m/d/Y H:i', strtotime($lastHeartbeat));
	if((strtotime($now) - strtotime($lastHeartbeat)) > 30*60)
		echo "<br><img style='margin-top:2px;' src='art/red_blinking_led_18.gif' title='No heartbeat probe since $lastHeartbeatPretty'>";
	else echo "<br><img style='margin-top:2px;' src='art/red_dark_led_18.gif' title='Last heartbeat probe: $lastHeartbeatPretty'>";
}

?>
</div>
<p>