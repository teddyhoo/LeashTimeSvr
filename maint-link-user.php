<?
//maint-link-user.php
// Identify an LT user as being the same person as another user (or group of LT users)

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";


// Verify login information here
locked('z-');
extract(extractVars('userid,unlink', $_REQUEST));

$userid = $userid ? $userid : $unlink;
$user = fetchFirstAssoc(
	"SELECT bizptr, loginid, temppassword, userid, rights, active, 
				lname, fname, email, db, bizname, isowner, activebiz
	FROM tbluser
	LEFT JOIN tblpetbiz ON bizid = bizptr
	WHERE userid = '$userid'");
$user['linkgroup'] = fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = '$userid' AND property = 'linkgroup' LIMIT 1", 1);
	
if($unlink) {
	$userid = $_REQUEST['unlink'];
	deleteTable('tbluserpref', "userptr = '$userid' AND property = 'linkgroup'", 1);
	unset($user['linkgroup']);
}
else if($_POST) {
	$candids = $user['linkgroup'] ? array() : array($userid);
	foreach($_POST as $k => $v)
		if(strpos($k, 'cand_') === 0) 
			$candids[] = substr($k, strlen('cand_'));
	if(!$candids)
		$error = "no candidates!";
	else {
		// there should be zero or one groups among the candidates and the main user
		$groups = fetchCol0("SELECT distinct value FROM tbluserpref WHERE property = 'linkgroup' AND userptr IN ($userid,".join(',', $candids).")", 1);
		if(count($groups) > 1) $error = "More than one group was indicated.  First unlink candidates as desired.";
		else {
			if(count($groups) == 0) {
				$greatestGroupID = fetchRow0Col0("SELECT value FROM tbluserpref WHERE property = 'linkgroup' ORDER BY value DESC LIMIT 1", 1);
				$groupId = $greatestGroupID + 1;
			}
			else $groupId = $groups[0];
			require_once "preference-fns.php";
			foreach($candids as $candid)
				setUserPreference($candid, 'linkgroup', $groupId);
			$message = "Added ".count($candids)." users to group $groupId.";
			$user['linkgroup'] = fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = '$userid' AND property = 'linkgroup' LIMIT 1", 1);
		}
	}
}
	
$fname = $user['fname'];
$lname = $user['lname'];
	
// find other managers and dispatchers with the same name
$candidates = fetchAssociations(
	"SELECT bizptr, loginid, temppassword, userid, rights, active, 
				lname, fname, email, isowner, activebiz,
				db, bizname
	FROM tbluser 
	LEFT JOIN tblpetbiz ON bizid = bizptr
	WHERE userid != '$userid' AND fname = '$fname' AND lname = '$lname' AND ltstaffuserid = 0");
	
foreach($candidates as $i => $candidate)
	$candidates[$i]['linkgroup'] = 
		fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = '{$candidates[$i]['userid']}' AND property = 'linkgroup' LIMIT 1", 1);

$windowTitle = 'User Links';
require "frame-bannerless.php";
?>
<h2>Link Users</h2><b>User: <?= "$fname $lname" ?> (<?= "$userid" ?>)</b> username: <b><?= $user['loginid'] ?></b> email: <b><?= $user['email'] ?> </b><br>
Biz: <?= "({$user['bizptr']}) {$user['bizname']} [{$user['db']}]" ?><br>
Group: <b><?= $user['linkgroup'] ? "<span style='color:blue'>{$user['linkgroup']}</span>" : "<span class='warning'>UNLINKED</span>" ?></b>
<? if($user['linkgroup']) 
	fauxLink('unlink', "if(confirm(\"Unlink this user?\")) unlink()"); 
?>
<p>
<?
if($error) echo "<font color=red>$error</font><p>";
else if($message) echo "<font color=green>$message</font><p>";
?>
<form name=linkform method='POST'>
<?
hiddenElement('userid', $userid);
hiddenElement('unlink');
echoButton('', 'Link', 'link()');
?>
<p>
<table border=1 bordercolor=black>
<tr><th>&nbsp;<th>User ID<th>User<th>Username<th>Biz ID<th>Biz Name<th>Biz DB<th>Group</tr>
<?
$roles = array('o'=>'M', 'd'=>'D', 'p'=>'S', 'c'=>'C', 'O'=>'O');
foreach($candidates as $cand) {
	$useridlabel = $cand['active'] ? $cand['userid'] : "<font color=red>{$cand['userid']}</font>";
	$canbizname = $cand['bizname'] ? $cand['bizname'] : "<font color=red>{$cand['bizname']}</font>";
	$linkgroup = $cand['linkgroup'];
	$checkbox = FALSE && $linkgroup ? '' : "<input type='checkbox' id='cand_{$cand['userid']}' name='cand_{$cand['userid']}'>";
	$username = "<a href='maint-link-user.php?userid={$cand['userid']}' title='{$cand['email']}' >{$cand['loginid']}</a>";
	if($cand['isowner']) $role = 'O';
	else $role = $cand['rights'][0];
	$role = $roles[$role];
	echo "<tr><td>$checkbox<td>($role) $useridlabel</td><td>$fname $lname</td><td>$username</td><td>{$cand['bizptr']}</td><td>$canbizname</td><td>{$cand['db']}</td><td style='color:blue;font-weight:bold;'>$linkgroup</td></tr>";
}
?>
</table>
<p>
O(wner), M(anager), D(ispatcher), S(itter), C(lient)
<script language='javascript'>
function unlink() {
	document.getElementById('unlink').value = <?= $userid ?>;
	document.linkform.submit();
}

function link() {
	var els = document.linkform.elements;
	var selected = 0;
	for(var i=0; i < els.length; i++)
		if(els[i].checked) selected++;
	if(selected == 0) {
		alert("Please select at least one user to link to first.");
		return;
	}
	else document.linkform.submit();
}
</script>