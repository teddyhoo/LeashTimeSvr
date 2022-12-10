<?
//maint-edit-rights.php

/* Rules:
1. Only an Super-user may use this page.
X. An owner may only edit logins in the same petbiz

Inputs: (R = required, * = optional, @ = one required among all @'s
[R] id - bizid
*/

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";

// Verify login information here
locked('z-');
extract(extractVars('id', $_REQUEST));
$id = intValueOrZero($id);
$user = fetchFirstAssoc("SELECT *, left(rights,1) as role FROM tbluser WHERE userid = '$id'");
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$user['bizptr']}'");

if($_POST) {
	$rights = array();
	foreach($_POST as $k => $v) {
		if(strpos($k, 'right_') === 0) {
			$rt = substr($k, strlen('right_'));
			if(strlen("$rt") > 4) {
				echo "Bad input.";
				exit;
			}
			$rights[] = $rt;
		}
	}
//print_r(($user['role'].'-'.join(',',$rights)));		exit;		
	updateTable('tbluser', array('rights'=>($user['role'].'-'.join(',',$rights))), "userid = $id");
	header("Location: ".globalURL("maint-edit-biz.php?id={$biz['bizid']}"));
	exit;
	//$user = fetchFirstAssoc("SELECT *, left(rights,1) as role FROM tbluser WHERE userid = '$id'");
}



$roles = explodePairsLine('o|Owner||c|Client||p|Sitter||z|Leashime Maintainer||d|Dispatcher');
$allRights = fetchAssociationsKeyedBy("SELECT * FROM tblrights ORDER BY sequence", 'key');
$rightsForId = explode(',', substr($user['rights'], 2));

include 'frame-maintenance.php';
$bizUsers = getBizUsers($biz);
$name = ($user['lname'] ? "{$user['fname']} {$user['lname']}" : $bizUsers[$user['userid']]['name']);

echo "<h2><a href='maint-edit-biz.php?id={$biz['bizid']}'>{$biz['bizname']}</a>: $name [login: {$user['loginid']}]</h2>";
echo "Role: ".$roles[$user['role']].'<p>'?>
<style>
.biztable td {padding-left:10px;}
</style>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function update() {refresh();}
</script>
<form name='rightsedit' method='POST'>
<table>
<?
hiddenElement('id', $id);
$dispatcherStarted =  false;
$creditCardPermission = true;
foreach($allRights as $right) {
	if($right['dispatcheronly'] && !$dispatcherStarted) {
		$dispatcherStarted =  true;
		echo "<tr><td colspan=3 style='font-weight:bold;margin-top:5px;'>&nbsp;";
		echo "<tr><td colspan=3 style='font-weight:bold;margin-top:5px;border-top:solid black 1px'>Dispatcher Rights";
	}
	if($right['key'][0] != '*' && $creditCardPermission) {
		$creditCardPermission =  false;
		echo "<tr><td colspan=3 style='font-weight:bold;margin-top:5px;border-top:solid black 1px'>&nbsp;";
	}
	echo "<tr><td>";
	labeledCheckbox($right['label']." ({$right['key']})", 'right_'.$right['key'], in_array($right['key'], $rightsForId), $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
	echo "</td><td style='padding-top:7px;'>{$right['description']}</td></tr>";
}
echo "</table>";
echoButton('', 'Save', 'document.rightsedit.submit()');
echo "</form>";
include "refresh.inc";

