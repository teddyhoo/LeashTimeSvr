<? //id-user.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

locked('+o-,+z-');
if(!staffOnlyTEST() && userRole() != 'z') {
	echo "Staff Only.";
	exit;
}
$id = $_REQUEST['id'];
if($id) {
	$u = fetchFirstAssoc(
		"SELECT u.*, bizid, bizname, db, CONCAT_WS(' ', fname, lname) as uname
			FROM tbluser u
			LEFT JOIN tblpetbiz ON bizid = bizptr
			WHERE userid = $id
			LIMIT 1");
}
?>
<h2>User Lookup</h2>
<? 
if($u) {
	$types = explodePairsLine('o|Manager||d|Dispatcher||p|Sitter||c|Client||z||LT Staff');
	$type = $types[$u['rights'][0]] ? $types[$u['rights'][0]] : '??';
	$bizname = $u['bizname'] ? "({$u['bizid']}) {$u['bizname']} [{$u['db']}]" : '<i>No biz</i>';
	echo "User {$u['userid']} - [{$u['loginid']}] {$u['uname']} [$type] Business: $bizname";
}
?>
<form method='POST'>
ID: <input name='id'> <input type=submit>
</form>